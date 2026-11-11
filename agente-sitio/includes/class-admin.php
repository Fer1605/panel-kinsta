<?php
/** Pantalla de ajustes del agente. Una sola, lo más simple posible. */

defined( 'ABSPATH' ) || exit;

class SWA_Admin {

	public static function init(): void {
		add_action( 'admin_menu', [ __CLASS__, 'menu' ] );
		add_action( 'admin_post_swa_guardar', [ __CLASS__, 'guardar' ] );
		add_action( 'admin_post_swa_probar_correo', [ __CLASS__, 'probar_correo' ] );
		add_action( 'admin_post_swa_registrar', [ __CLASS__, 'registrar' ] );
		add_action( 'admin_post_swa_reportar', [ __CLASS__, 'reportar' ] );
		add_action( 'admin_post_swa_respaldo', [ __CLASS__, 'respaldo' ] );
	}

	public static function menu(): void {
		add_options_page( 'Agente Solución Web', 'Agente Solución Web', 'manage_options', 'agente-sw', [ __CLASS__, 'pantalla' ] );
	}

	private static function url( string $accion ): string {
		return wp_nonce_url( admin_url( 'admin-post.php?action=' . $accion ), $accion );
	}

	public static function pantalla(): void {
		$a      = SWA_Ajustes::todos();
		$correo = SWA_Correo::estado();
		$puede  = SWA_Correo::puede_enviar();
		?>
		<div class="wrap">
			<h1>Agente Solución Web</h1>

			<?php if ( ! empty( $_GET['swa_msg'] ) ) : ?>
				<?php
				[ $tipo, $texto ] = array_pad( explode( ':', sanitize_text_field( wp_unslash( (string) $_GET['swa_msg'] ) ), 2 ), 2, '' );
				printf( '<div class="notice notice-%s"><p>%s</p></div>', 'error' === $tipo ? 'error' : 'success', esc_html( urldecode( $texto ) ) );
				?>
			<?php endif; ?>

			<?php if ( SWA_Dos_Factores::apagado() ) : ?>
				<div class="notice notice-warning"><p>
					<strong>La verificación en dos pasos está apagada por constante.</strong>
					Hay un <code>define( 'SWA_2FA_APAGADO', true )</code> en wp-config.php. Es la salida
					de emergencia; conviene quitarla cuando ya no haga falta.
				</p></div>
			<?php endif; ?>

			<h2>1. Conexión con el panel</h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="swa_guardar">
				<?php wp_nonce_field( 'swa_guardar' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="panel_url">Dirección del panel</label></th>
						<td><input type="url" class="regular-text" name="panel_url" id="panel_url" value="<?php echo esc_attr( $a['panel_url'] ); ?>" placeholder="https://..."></td>
					</tr>
					<tr>
						<th scope="row"><label for="clave_registro">Clave de registro</label></th>
						<td>
							<input type="password" class="regular-text" name="clave_registro" id="clave_registro" value="" autocomplete="off"
								placeholder="<?php echo '' !== $a['clave_registro'] ? '•••••••• (ya configurada)' : ''; ?>">
							<p class="description">Está en el panel, en Ajustes. Solo se usa una vez: al registrarse, el panel le entrega a este sitio su propio secreto.</p>
						</td>
					</tr>
					<tr>
						<th scope="row">Estado</th>
						<td>
							<?php if ( SWA_Ajustes::registrado() ) : ?>
								<span style="color:#008a20">✓ Registrado en el panel</span>
								<?php $ult = get_option( 'swa_ultimo_reporte', '' ); ?>
								<p class="description">
									Último reporte: <?php echo esc_html( $ult ?: 'ninguno todavía' ); ?>
									<?php $err = get_option( 'swa_ultimo_error', '' ); ?>
									<?php if ( $err ) : ?>
										<br><span style="color:#d63638">Último error: <?php echo esc_html( $err ); ?></span>
									<?php endif; ?>
								</p>
							<?php else : ?>
								<span style="color:#d63638">Sin registrar</span>
							<?php endif; ?>
						</td>
					</tr>
				</table>
				<?php submit_button( 'Guardar' ); ?>
			</form>

			<p>
				<a class="button" href="<?php echo esc_url( self::url( 'swa_registrar' ) ); ?>">Registrar este sitio</a>
				<a class="button" href="<?php echo esc_url( self::url( 'swa_reportar' ) ); ?>">Mandar el reporte ahora</a>
			</p>

			<hr>

			<h2>2. El correo de este sitio</h2>
			<p style="max-width:720px">
				Antes de activar la verificación en dos pasos hay que comprobar que el sitio puede
				enviar correo. Si no puede, el código nunca llega y los administradores quedan
				afuera de su propio sitio.
			</p>
			<p>
				<?php if ( $puede ) : ?>
					<strong style="color:#008a20">✓ Este sitio sí envía correo.</strong>
					Probado el <?php echo esc_html( (string) ( $correo['probado'] ?? '' ) ); ?>.
				<?php elseif ( $correo ) : ?>
					<strong style="color:#d63638">✗ El envío falló.</strong>
					<?php echo esc_html( (string) ( $correo['error'] ?: 'sin detalle del error' ) ); ?>
				<?php else : ?>
					<strong style="color:#dba617">Todavía no se ha probado.</strong>
				<?php endif; ?>
			</p>
			<p><a class="button" href="<?php echo esc_url( self::url( 'swa_probar_correo' ) ); ?>">Probar el envío</a></p>

			<hr>

			<h2>3. Verificación en dos pasos</h2>

			<?php if ( ! $puede ) : ?>
				<div class="notice notice-error inline" style="max-width:720px"><p>
					<strong>No se puede activar todavía.</strong> Primero hay que lograr que el sitio
					envíe correo. Activarla ahora dejaría a los administradores sin poder entrar.
				</p></div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="swa_guardar">
				<?php wp_nonce_field( 'swa_guardar' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">Activar</th>
						<td>
							<label>
								<input type="checkbox" name="2fa_activo" value="1" <?php checked( $a['2fa_activo'] ); ?> <?php disabled( ! $puede ); ?>>
								Pedir un código por correo al entrar
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row">A qué roles</th>
						<td>
							<?php
							$todos = wp_roles()->get_names();
							foreach ( $todos as $slug => $nombre ) :
								?>
								<label style="display:inline-block;margin-right:16px">
									<input type="checkbox" name="2fa_roles[]" value="<?php echo esc_attr( $slug ); ?>"
										<?php checked( in_array( $slug, (array) $a['2fa_roles'], true ) ); ?>>
									<?php echo esc_html( $nombre ); ?>
								</label>
							<?php endforeach; ?>
							<p class="description">
								Administrador y editor. <strong>Nunca suscriptor ni cliente de tienda:</strong>
								pedirle un código por correo a cada comprador tumba la venta.
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="gracia">Periodo de gracia</label></th>
						<td>
							<select name="gracia_dias" id="gracia">
								<option value="">Sin gracia: obligatorio desde ya</option>
								<option value="3">3 días</option>
								<option value="7" selected>7 días</option>
								<option value="15">15 días</option>
							</select>
							<p class="description">
								Durante la gracia, quien todavía no lo ha usado entra sin código. Sirve para
								avisar primero y obligar después.
								<?php if ( (int) $a['2fa_gracia_hasta'] > time() ) : ?>
									<br><strong>Vence el <?php echo esc_html( wp_date( 'j \d\e F, H:i', (int) $a['2fa_gracia_hasta'] ) ); ?>.</strong>
								<?php endif; ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">XML-RPC</th>
						<td>
							<label>
								<input type="checkbox" name="cerrar_xmlrpc" value="1" <?php checked( $a['cerrar_xmlrpc'] ); ?>>
								Cerrar XML-RPC
							</label>
							<p class="description">
								La verificación en dos pasos protege el formulario de login y nada más.
								XML-RPC acepta usuario y contraseña por otra vía y no pasa por ella. Si
								nadie lo usa en este sitio, conviene cerrarlo en la misma visita.
								<br><strong>Ojo:</strong> lo usan la app de WordPress y algunas integraciones.
							</p>
						</td>
					</tr>
				</table>
				<?php submit_button( 'Guardar' ); ?>
			</form>

			<h3>Códigos de respaldo</h3>
			<p>
				Tenés <strong><?php echo (int) SWA_Dos_Factores::cuantos_respaldo( get_current_user_id() ); ?></strong>
				códigos de respaldo sin usar. Sirven para entrar si te quedás sin acceso al correo.
			</p>
			<p><a class="button" href="<?php echo esc_url( self::url( 'swa_respaldo' ) ); ?>">Generar códigos nuevos</a></p>

			<?php
			$recien = get_transient( 'swa_respaldo_' . get_current_user_id() );
			if ( is_array( $recien ) && $recien ) :
				delete_transient( 'swa_respaldo_' . get_current_user_id() );
				?>
				<div class="notice notice-warning">
					<p><strong>Guardá estos códigos ahora. No se van a volver a mostrar.</strong></p>
					<p style="font-family:ui-monospace,Menlo,monospace;font-size:15px;line-height:2">
						<?php echo esc_html( implode( '   ', $recien ) ); ?>
					</p>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	private static function volver( string $mensaje, bool $error = false ): void {
		wp_safe_redirect(
			admin_url( 'options-general.php?page=agente-sw&swa_msg=' . ( $error ? 'error:' : 'ok:' ) . rawurlencode( $mensaje ) )
		);
		exit;
	}

	public static function guardar(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Sin permiso.' );
		}
		check_admin_referer( 'swa_guardar' );

		$nuevos = [];

		if ( isset( $_POST['panel_url'] ) ) {
			$nuevos['panel_url'] = esc_url_raw( wp_unslash( (string) $_POST['panel_url'] ) );
		}

		if ( isset( $_POST['clave_registro'] ) && '' !== $_POST['clave_registro'] ) {
			$nuevos['clave_registro'] = sanitize_text_field( wp_unslash( (string) $_POST['clave_registro'] ) );
		}

		// Las casillas solo llegan cuando están marcadas, así que el formulario
		// de arriba (conexión) no debe tocar los ajustes de abajo (2FA).
		if ( isset( $_POST['2fa_roles'] ) || isset( $_POST['2fa_activo'] ) || isset( $_POST['gracia_dias'] ) ) {
			$nuevos['2fa_activo']    = isset( $_POST['2fa_activo'] ) ? 1 : 0;
			$nuevos['cerrar_xmlrpc'] = isset( $_POST['cerrar_xmlrpc'] ) ? 1 : 0;
			$nuevos['2fa_roles']     = isset( $_POST['2fa_roles'] )
				? array_map( 'sanitize_key', (array) wp_unslash( $_POST['2fa_roles'] ) )
				: [];

			$dias = isset( $_POST['gracia_dias'] ) ? (int) $_POST['gracia_dias'] : 0;
			if ( $dias > 0 && ! SWA_Ajustes::obtener( '2fa_activo' ) ) {
				// La gracia empieza a contar al activar, no al guardar.
				$nuevos['2fa_gracia_hasta'] = time() + $dias * DAY_IN_SECONDS;
			}
		}

		SWA_Ajustes::guardar( $nuevos );
		self::volver( 'Ajustes guardados.' );
	}

	public static function probar_correo(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Sin permiso.' );
		}
		check_admin_referer( 'swa_probar_correo' );

		$e = SWA_Correo::probar();

		$e['ok']
			? self::volver( 'El correo salió bien, a ' . $e['destino'] . '. Revisá que haya llegado.' )
			: self::volver( 'El envío falló: ' . ( $e['error'] ?: 'sin detalle' ), true );
	}

	public static function registrar(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Sin permiso.' );
		}
		check_admin_referer( 'swa_registrar' );

		$r = SWA_Reporte::registrar();
		is_wp_error( $r ) ? self::volver( $r->get_error_message(), true ) : self::volver( 'Sitio registrado en el panel.' );
	}

	public static function reportar(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Sin permiso.' );
		}
		check_admin_referer( 'swa_reportar' );

		$r = SWA_Reporte::enviar();
		is_wp_error( $r ) ? self::volver( $r->get_error_message(), true ) : self::volver( 'Reporte enviado.' );
	}

	public static function respaldo(): void {
		if ( ! current_user_can( 'read' ) ) {
			wp_die( 'Sin permiso.' );
		}
		check_admin_referer( 'swa_respaldo' );

		$codigos = SWA_Dos_Factores::generar_respaldo( get_current_user_id() );
		set_transient( 'swa_respaldo_' . get_current_user_id(), $codigos, 300 );

		self::volver( 'Códigos generados. Guardalos: no se vuelven a mostrar.' );
	}
}
