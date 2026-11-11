<?php
/**
 * Verificación en dos pasos por código al correo.
 *
 * ─── Cómo funciona, que es el camino de siempre ─────────────────────────────
 *
 *  1. El usuario escribe su contraseña y WordPress la valida como siempre.
 *  2. Antes de dejarlo entrar le quitamos la sesión recién creada
 *     (wp_clear_auth_cookie) y guardamos un testigo de corta duración.
 *  3. Le mandamos un código de seis dígitos por correo y le mostramos la
 *     pantalla del segundo paso.
 *  4. Si el código es correcto, ahí sí le entregamos la sesión
 *     (wp_set_auth_cookie). Si no, se cuenta el intento.
 *
 * ─── Las reglas que hacen que esto sea seguro y no decorativo ───────────────
 *
 *  - El código se genera con random_int, que es criptográficamente seguro.
 *    rand() y mt_rand() son predecibles y no sirven para esto.
 *  - El código se guarda CIFRADO, nunca en texto plano. Si alguien llega a la
 *    base de datos, no se lleva códigos usables.
 *  - Caduca a los 10 minutos y es de un solo uso.
 *  - Cinco intentos fallidos y queda bloqueado 15 minutos.
 *  - El testigo de la pantalla intermedia impide que alguien mande un código
 *    para otro usuario sin haber pasado la contraseña.
 *  - Todas las comparaciones de testigos usan hash_equals.
 *  - Códigos de respaldo, también cifrados, para quien se quede sin correo.
 *
 * ─── LO QUE ESTO NO PROTEGE. Importante. ────────────────────────────────────
 *
 * Esto protege el formulario de login, y nada más. NO protege XML-RPC ni la
 * API REST con contraseñas de aplicación, porque esas vías no pasan por aquí.
 * Un sitio con XML-RPC abierto sigue aceptando usuario y contraseña por ahí
 * aunque la verificación en dos pasos esté activa. Por eso el agente ofrece
 * cerrarlo, y por eso hay que cerrarlo en la misma visita si nadie lo usa.
 *
 * ─── Salida de emergencia ───────────────────────────────────────────────────
 *
 * Si alguien queda encerrado y hay que entrar ya, en wp-config.php:
 *     define( 'SWA_2FA_APAGADO', true );
 * Eso apaga la verificación en todo el sitio sin tocar la base de datos.
 */

defined( 'ABSPATH' ) || exit;

class SWA_Dos_Factores {

	private const META_HASH     = '_swa_codigo';
	private const META_EXPIRA   = '_swa_codigo_expira';
	private const META_INTENTOS = '_swa_intentos';
	private const META_BLOQUEO  = '_swa_bloqueado_hasta';
	private const META_TESTIGO  = '_swa_testigo';
	private const META_RESPALDO = '_swa_respaldo';
	private const META_LISTO    = '_swa_configurado';

	/**
	 * Cuántos códigos seguidos pueden quedar sin usarse antes de que el propio
	 * plugin se apague.
	 *
	 * ─── Por qué hace falta esto ────────────────────────────────────────────
	 *
	 * La prueba de correo se hace una vez, al instalar. Pero el correo se puede
	 * romper DESPUÉS: el SMTP se cae, se vence una credencial, el proveedor
	 * empieza a marcar los mensajes como spam. Y el caso traicionero es que
	 * wp_mail sigue diciendo que sí: el mensaje sale del servidor y nunca llega.
	 *
	 * Desde dentro del sitio no hay forma de saber si un correo llegó. Pero sí
	 * hay una señal indirecta y bastante clara: si se piden cinco códigos
	 * seguidos y ninguno se usa, es que no están llegando.
	 *
	 * Cuando eso pasa, el plugin se apaga solo. Vale más un sitio con una capa
	 * menos que un sitio al que nadie puede entrar.
	 */
	private const CODIGOS_PERDIDOS = 5;

	private const VIGENCIA      = 600;  // 10 minutos
	private const MAX_INTENTOS  = 5;
	private const BLOQUEO       = 900;  // 15 minutos

	public static function init(): void {
		if ( self::apagado() ) {
			return;
		}

		add_action( 'wp_login', [ __CLASS__, 'interceptar' ], 10, 2 );
		add_action( 'login_form_swa_verificar', [ __CLASS__, 'procesar_verificacion' ] );

		if ( SWA_Ajustes::obtener( 'cerrar_xmlrpc' ) ) {
			add_filter( 'xmlrpc_enabled', '__return_false' );
		}
	}

	/**
	 * El plugin se apaga solo cuando deduce que los códigos no están llegando.
	 *
	 * No borra nada ni toca al usuario: solo deja de pedir el segundo paso y
	 * anota por qué, para que el panel lo reporte en su siguiente corrida y
	 * alguien vaya a ver qué pasó con el correo de ese sitio.
	 */
	private static function apagarse_sola( int $perdidos ): void {
		if ( ! SWA_Ajustes::obtener( '2fa_activo' ) ) {
			return;
		}

		SWA_Ajustes::guardar( [ '2fa_activo' => 0 ] );

		update_option(
			'swa_2fa_apagada_sola',
			[
				'cuando'   => current_time( 'mysql' ),
				'perdidos' => $perdidos,
				'motivo'   => sprintf(
					'Se pidieron %d códigos seguidos y ninguno se usó. Lo más probable es que el correo de este sitio no esté llegando.',
					$perdidos
				),
			],
			false
		);

		do_action( 'swa_2fa_apagada_sola', $perdidos );
	}

	/** @return array<string,mixed> */
	public static function apagado_automatico(): array {
		$v = get_option( 'swa_2fa_apagada_sola', [] );
		return is_array( $v ) ? $v : [];
	}

	public static function apagado(): bool {
		return defined( 'SWA_2FA_APAGADO' ) && SWA_2FA_APAGADO;
	}

	public static function activo(): bool {
		return ! self::apagado() && (bool) SWA_Ajustes::obtener( '2fa_activo' );
	}

	/** ¿A este usuario le toca el segundo paso? */
	public static function le_toca( WP_User $usuario ): bool {
		if ( ! self::activo() ) {
			return false;
		}

		$roles = (array) SWA_Ajustes::obtener( '2fa_roles', [] );
		if ( ! array_intersect( $roles, (array) $usuario->roles ) ) {
			return false;
		}

		// Periodo de gracia: se avisa primero y se obliga después. Obligar el
		// mismo día en que se instala es la forma más rápida de generar una
		// llamada enojada.
		$gracia = (int) SWA_Ajustes::obtener( '2fa_gracia_hasta', 0 );
		if ( $gracia > time() && ! get_user_meta( $usuario->ID, self::META_LISTO, true ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Se dispara cuando WordPress ya validó la contraseña y entregó la sesión.
	 * Aquí se la quitamos y mandamos al segundo paso.
	 */
	public static function interceptar( string $login, WP_User $usuario ): void {
		if ( ! self::le_toca( $usuario ) ) {
			return;
		}

		// Un sitio que no puede mandar correo no puede pedir códigos por
		// correo: dejaría a todo el mundo afuera. Mejor dejar entrar y que el
		// panel reporte que este sitio tiene el correo roto.
		if ( ! SWA_Correo::puede_enviar() ) {
			return;
		}

		wp_clear_auth_cookie();

		$testigo = wp_generate_password( 40, false, false );
		update_user_meta( $usuario->ID, self::META_TESTIGO, wp_hash_password( $testigo ) );

		self::generar_y_enviar( $usuario );

		$redirigir = isset( $_REQUEST['redirect_to'] )
			? esc_url_raw( wp_unslash( (string) $_REQUEST['redirect_to'] ) )
			: admin_url();

		$recordar = ! empty( $_REQUEST['rememberme'] );

		self::pantalla( $usuario, $testigo, $redirigir, $recordar );
		exit;
	}

	private static function generar_y_enviar( WP_User $usuario ): void {
		// random_int, no rand ni mt_rand: esos son predecibles.
		$codigo = str_pad( (string) random_int( 0, 999999 ), 6, '0', STR_PAD_LEFT );

		update_user_meta( $usuario->ID, self::META_HASH, wp_hash_password( $codigo ) );
		update_user_meta( $usuario->ID, self::META_EXPIRA, time() + self::VIGENCIA );
		delete_user_meta( $usuario->ID, self::META_INTENTOS );

		// Se cuenta al pedirlo; se pone en cero al usarlo. Si el contador sube
		// sin bajar, los códigos no están llegando a nadie.
		$perdidos = (int) get_option( 'swa_codigos_sin_usar', 0 ) + 1;
		update_option( 'swa_codigos_sin_usar', $perdidos, false );

		if ( $perdidos >= self::CODIGOS_PERDIDOS ) {
			self::apagarse_sola( $perdidos );
		}

		$sitio = get_bloginfo( 'name' );

		wp_mail(
			$usuario->user_email,
			sprintf( 'Tu código de acceso a %s: %s', $sitio, $codigo ),
			sprintf(
				'<p>Hola %s,</p>'
				. '<p>Alguien está entrando a <strong>%s</strong> con tu usuario. Tu código es:</p>'
				. '<p style="font-size:30px;font-weight:700;letter-spacing:5px;margin:22px 0">%s</p>'
				. '<p>Vence en 10 minutos y sirve una sola vez.</p>'
				. '<p style="color:#666">Si no fuiste vos, alguien tiene tu contraseña. Cambiala ya y avisanos.</p>',
				esc_html( $usuario->display_name ),
				esc_html( $sitio ),
				esc_html( $codigo )
			),
			[ 'Content-Type: text/html; charset=UTF-8' ]
		);
	}

	private static function pantalla( WP_User $usuario, string $testigo, string $redirigir, bool $recordar, string $error = '' ): void {
		login_header( 'Verificación en dos pasos', '', new WP_Error() );
		?>
		<form name="swa" id="loginform" action="<?php echo esc_url( site_url( 'wp-login.php?action=swa_verificar', 'login_post' ) ); ?>" method="post">
			<?php if ( '' !== $error ) : ?>
				<div id="login_error" style="margin-bottom:16px"><?php echo esc_html( $error ); ?></div>
			<?php endif; ?>

			<p style="margin-bottom:14px">
				Te mandamos un código de seis dígitos a
				<strong><?php echo esc_html( self::ocultar_correo( $usuario->user_email ) ); ?></strong>.
			</p>

			<p>
				<label for="swa_codigo">Código</label>
				<input type="text" name="swa_codigo" id="swa_codigo" class="input"
					inputmode="numeric" autocomplete="one-time-code" maxlength="20"
					style="letter-spacing:4px;font-size:20px" autofocus>
			</p>

			<input type="hidden" name="swa_usuario" value="<?php echo (int) $usuario->ID; ?>">
			<input type="hidden" name="swa_testigo" value="<?php echo esc_attr( $testigo ); ?>">
			<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirigir ); ?>">
			<input type="hidden" name="rememberme" value="<?php echo $recordar ? '1' : ''; ?>">

			<p class="submit">
				<input type="submit" class="button button-primary button-large" value="Entrar" style="width:100%">
			</p>

			<p style="color:#666;font-size:12px;margin-top:14px">
				¿No te llegó? Revisá la carpeta de spam. Si tenés códigos de respaldo, podés usar uno aquí.
			</p>
		</form>
		<?php
		login_footer();
	}

	public static function procesar_verificacion(): void {
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			wp_safe_redirect( wp_login_url() );
			exit;
		}

		$usuario_id = isset( $_POST['swa_usuario'] ) ? (int) $_POST['swa_usuario'] : 0;
		$testigo    = isset( $_POST['swa_testigo'] ) ? (string) wp_unslash( $_POST['swa_testigo'] ) : '';
		$codigo     = isset( $_POST['swa_codigo'] ) ? trim( (string) wp_unslash( $_POST['swa_codigo'] ) ) : '';
		$redirigir  = isset( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( (string) $_POST['redirect_to'] ) ) : admin_url();
		$recordar   = ! empty( $_POST['rememberme'] );

		$usuario = get_user_by( 'id', $usuario_id );
		if ( ! $usuario instanceof WP_User ) {
			wp_safe_redirect( wp_login_url() );
			exit;
		}

		// El testigo prueba que esta persona ya pasó la contraseña. Sin esto,
		// cualquiera podría probar códigos contra cualquier usuario.
		$testigo_guardado = (string) get_user_meta( $usuario->ID, self::META_TESTIGO, true );
		if ( '' === $testigo_guardado || ! wp_check_password( $testigo, $testigo_guardado ) ) {
			wp_safe_redirect( wp_login_url() );
			exit;
		}

		$bloqueado = (int) get_user_meta( $usuario->ID, self::META_BLOQUEO, true );
		if ( $bloqueado > time() ) {
			self::pantalla(
				$usuario,
				$testigo,
				$redirigir,
				$recordar,
				sprintf( 'Demasiados intentos. Probá de nuevo en %d minutos.', (int) ceil( ( $bloqueado - time() ) / 60 ) )
			);
			exit;
		}

		if ( self::codigo_valido( $usuario, $codigo ) || self::respaldo_valido( $usuario, $codigo ) ) {
			self::limpiar( $usuario->ID );
			update_user_meta( $usuario->ID, self::META_LISTO, 1 );

			// Llegó y se usó: el correo funciona. Contador a cero.
			delete_option( 'swa_codigos_sin_usar' );

			wp_set_auth_cookie( $usuario->ID, $recordar );
			do_action( 'swa_segundo_paso_superado', $usuario );

			wp_safe_redirect( $redirigir ?: admin_url() );
			exit;
		}

		$intentos = (int) get_user_meta( $usuario->ID, self::META_INTENTOS, true ) + 1;
		update_user_meta( $usuario->ID, self::META_INTENTOS, $intentos );

		if ( $intentos >= self::MAX_INTENTOS ) {
			update_user_meta( $usuario->ID, self::META_BLOQUEO, time() + self::BLOQUEO );
			delete_user_meta( $usuario->ID, self::META_HASH );
			do_action( 'swa_bloqueo_por_intentos', $usuario );

			self::pantalla( $usuario, $testigo, $redirigir, $recordar, 'Demasiados intentos. Quedó bloqueado 15 minutos.' );
			exit;
		}

		self::pantalla(
			$usuario,
			$testigo,
			$redirigir,
			$recordar,
			sprintf( 'El código no es correcto. Te quedan %d intentos.', self::MAX_INTENTOS - $intentos )
		);
		exit;
	}

	private static function codigo_valido( WP_User $usuario, string $codigo ): bool {
		if ( '' === $codigo ) {
			return false;
		}

		$expira = (int) get_user_meta( $usuario->ID, self::META_EXPIRA, true );
		if ( $expira < time() ) {
			return false;
		}

		$hash = (string) get_user_meta( $usuario->ID, self::META_HASH, true );
		if ( '' === $hash ) {
			return false;
		}

		return wp_check_password( $codigo, $hash );
	}

	private static function respaldo_valido( WP_User $usuario, string $codigo ): bool {
		if ( '' === $codigo ) {
			return false;
		}

		$codigos = get_user_meta( $usuario->ID, self::META_RESPALDO, true );
		if ( ! is_array( $codigos ) || ! $codigos ) {
			return false;
		}

		foreach ( $codigos as $i => $hash ) {
			if ( wp_check_password( $codigo, (string) $hash ) ) {
				// Un código de respaldo se gasta al usarse.
				unset( $codigos[ $i ] );
				update_user_meta( $usuario->ID, self::META_RESPALDO, array_values( $codigos ) );
				return true;
			}
		}

		return false;
	}

	/** Genera códigos de respaldo. Se muestran UNA vez; en la base van cifrados. */
	public static function generar_respaldo( int $usuario_id, int $cuantos = 8 ): array {
		$planos = [];
		$hashes = [];

		for ( $i = 0; $i < $cuantos; $i++ ) {
			$c        = strtoupper( wp_generate_password( 10, false, false ) );
			$planos[] = $c;
			$hashes[] = wp_hash_password( $c );
		}

		update_user_meta( $usuario_id, self::META_RESPALDO, $hashes );

		return $planos;
	}

	public static function cuantos_respaldo( int $usuario_id ): int {
		$c = get_user_meta( $usuario_id, self::META_RESPALDO, true );
		return is_array( $c ) ? count( $c ) : 0;
	}

	private static function limpiar( int $usuario_id ): void {
		delete_user_meta( $usuario_id, self::META_HASH );
		delete_user_meta( $usuario_id, self::META_EXPIRA );
		delete_user_meta( $usuario_id, self::META_INTENTOS );
		delete_user_meta( $usuario_id, self::META_TESTIGO );
		delete_user_meta( $usuario_id, self::META_BLOQUEO );
	}

	/** fernanda@ejemplo.com → f******a@ejemplo.com */
	private static function ocultar_correo( string $correo ): string {
		$partes = explode( '@', $correo );
		if ( 2 !== count( $partes ) ) {
			return '***';
		}

		$u = $partes[0];
		if ( strlen( $u ) <= 2 ) {
			return str_repeat( '*', strlen( $u ) ) . '@' . $partes[1];
		}

		return $u[0] . str_repeat( '*', strlen( $u ) - 2 ) . $u[ strlen( $u ) - 1 ] . '@' . $partes[1];
	}
}
