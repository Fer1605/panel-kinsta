<?php
/**
 * Las órdenes que este sitio va a recoger al panel, y cómo las ejecuta.
 *
 * ─── La dirección del tráfico no cambia ─────────────────────────────────────
 *
 * Este sitio sigue sin abrir ninguna puerta. No hay una ruta nueva esperando
 * peticiones de internet: es el sitio el que pregunta, con el mismo reporte
 * firmado de siempre, y la respuesta del panel trae lo que haya pendiente.
 * Quien no tenga el secreto de este sitio no puede contestarle nada.
 *
 * ─── Lo que una orden puede y no puede hacer ────────────────────────────────
 *
 * La lista de lo que el agente sabe hacer está aquí, en el código, y es corta.
 * Una orden que no esté en esta lista se rechaza y se le dice al panel. Eso
 * significa que ni el panel ni nadie que logre hablar como el panel puede
 * pedirle a este sitio algo que no esté escrito aquí: no hay «ejecutá esto»,
 * no hay «instalá aquello», no hay código que viaje en la orden. Seis verbos,
 * y se acabó.
 *
 * ─── El editor de código y una promesa que no se puede hacer ────────────────
 *
 * `DISALLOW_FILE_EDIT` es una constante de `wp-config.php`, y un plugin no
 * debería escribir en ese archivo: un error de escritura deja el sitio del
 * cliente en blanco. Entonces el agente hace lo que sí puede hacer sin riesgo:
 * define la constante él mismo, temprano, en cada carga.
 *
 * El efecto es real —el editor desaparece del escritorio— pero depende de que
 * el agente siga activo. Por eso el reporte dice **quién** la cerró, y el panel
 * muestra «cerrado por el agente» en vez de «cerrado», que sería prometer algo
 * que no es verdad si alguien desactiva el plugin.
 */

defined( 'ABSPATH' ) || exit;

class SWA_Ordenes {

	public const HOOK = 'swa_preguntar_ordenes';

	/** Cada cuánto pregunta. Ver el comentario de programar(). */
	public const CADA = 'swa_cinco_minutos';

	public static function init(): void {
		add_filter( 'cron_schedules', [ __CLASS__, 'intervalo' ] );
		add_action( self::HOOK, [ __CLASS__, 'preguntar' ] );

		// Además de en la activación. Ver programar(): en los sitios donde ya
		// está instalado, la pregunta cada cinco minutos nunca quedó puesta.
		self::programar();

		// La constante se define aquí y no en el ejecutor, porque hay que
		// definirla en CADA carga de la página, no una sola vez.
		if ( SWA_Ajustes::obtener( 'cerrar_editor' ) && ! defined( 'DISALLOW_FILE_EDIT' ) ) {
			define( 'DISALLOW_FILE_EDIT', true );
		}
	}

	/**
	 * @param array<string,array<string,mixed>> $horarios
	 * @return array<string,array<string,mixed>>
	 */
	public static function intervalo( array $horarios ): array {
		$horarios[ self::CADA ] = [
			'interval' => 5 * MINUTE_IN_SECONDS,
			'display'  => 'Cada cinco minutos (Agente Solución Web)',
		];

		return $horarios;
	}

	/**
	 * Programa la pregunta cada cinco minutos.
	 *
	 * Hay que decir el precio en voz alta: el reloj de WordPress no es un
	 * reloj, es «lo que se ejecuta cuando alguien visita el sitio». En un sitio
	 * con tráfico esto corre cada cinco minutos de verdad; en un sitio que
	 * nadie visita de noche, la orden espera a la primera visita de la mañana.
	 * No se puede prometer masivo e instantáneo, y el panel lo dice así.
	 *
	 * ─── Y por qué se asegura el intervalo aquí ──────────────────────────
	 *
	 * Esto estaba roto en todos los sitios y no se veía. `wp_schedule_event()`
	 * comprueba que la recurrencia exista en `wp_get_schedules()`; si no
	 * existe devuelve false sin lanzar nada. Nuestro `swa_cinco_minutos` lo
	 * mete `init()`, que cuelga de `plugins_loaded` — y al ACTIVAR el plugin
	 * `plugins_loaded` ya pasó, porque WordPress incluye el archivo y dispara
	 * el gancho de activación dentro de una petición donde el plugin todavía
	 * no estaba activo. Así que la pregunta cada cinco minutos no se programó
	 * nunca: las órdenes del panel esperaban al reporte diario.
	 *
	 * El reporte diario sí quedó puesto porque usa `daily`, que WordPress trae
	 * de fábrica. Por eso el fallo pasó desapercibido: el agente reportaba.
	 */
	public static function programar(): void {
		if ( wp_next_scheduled( self::HOOK ) ) {
			return;
		}

		if ( ! isset( wp_get_schedules()[ self::CADA ] ) ) {
			add_filter( 'cron_schedules', [ __CLASS__, 'intervalo' ] );
		}

		wp_schedule_event( time() + 60, self::CADA, self::HOOK );
	}

	public static function desprogramar(): void {
		$ts = wp_next_scheduled( self::HOOK );

		if ( $ts ) {
			wp_unschedule_event( $ts, self::HOOK );
		}
	}

	/**
	 * Pregunta al panel si le dejaron algo, y ejecuta lo que venga.
	 *
	 * El reporte que manda es liviano a propósito: sin usuarios y sin normas.
	 * Mandar la lista entera 288 veces al día para preguntar «¿hay algo?» le
	 * costaría al panel 27.000 peticiones gordas diarias con 94 sitios.
	 */
	public static function preguntar(): true|WP_Error {
		if ( ! SWA_Ajustes::registrado() ) {
			return new WP_Error( 'swa_sin_registro', 'Este sitio todavía no está registrado en el panel.' );
		}

		$r = SWA_Reporte::enviar( true, self::pendientes_de_contar() );

		return $r;
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Lo que queda por contarle al panel
	// ─────────────────────────────────────────────────────────────────────────

	private const COLA_RESULTADOS = 'swa_resultados_ordenes';

	/**
	 * Los resultados que todavía no se le han podido contar al panel.
	 *
	 * Si el panel no contesta —se cayó, cambió de dirección— el resultado no se
	 * pierde: se guarda y viaja en el siguiente intento. Sin esto, una orden
	 * ejecutada con éxito podría quedar para siempre como «entregada» en el
	 * panel solo porque falló la petición de vuelta.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function pendientes_de_contar(): array {
		$cola = get_option( self::COLA_RESULTADOS, [] );

		return is_array( $cola ) ? $cola : [];
	}

	public static function olvidar_contados( array $contados ): void {
		if ( ! $contados ) {
			return;
		}

		$ids  = array_map( static fn( $c ) => (int) ( $c['id'] ?? 0 ), $contados );
		$cola = array_values(
			array_filter(
				self::pendientes_de_contar(),
				static fn( $c ) => ! in_array( (int) ( $c['id'] ?? 0 ), $ids, true )
			)
		);

		update_option( self::COLA_RESULTADOS, $cola, false );
	}

	private static function anotar_resultado( int $id, bool $ok, string $texto ): void {
		$cola = self::pendientes_de_contar();

		$cola[] = [ 'id' => $id, 'ok' => $ok, 'resultado' => $texto ];

		// Un tope, para que un panel caído una semana no llene la tabla de
		// opciones con resultados que ya nadie va a leer.
		update_option( self::COLA_RESULTADOS, array_slice( $cola, -100 ), false );
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Ejecutar
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * Ejecuta las órdenes que vinieron en la respuesta del panel.
	 *
	 * @param array<int,array<string,mixed>> $ordenes
	 */
	public static function ejecutar( array $ordenes ): int {
		$hechas = 0;

		foreach ( $ordenes as $o ) {
			$id   = (int) ( $o['id'] ?? 0 );
			$tipo = (string) ( $o['tipo'] ?? '' );

			if ( $id <= 0 || '' === $tipo ) {
				continue;
			}

			$metodo = 'hacer_' . preg_replace( '/[^a-z_]/', '', $tipo );

			if ( ! method_exists( __CLASS__, $metodo ) ) {
				self::anotar_resultado( $id, false, sprintf( 'Este agente no sabe hacer «%s».', $tipo ) );
				continue;
			}

			try {
				$r = self::$metodo( (array) ( $o['carga'] ?? [] ) );
			} catch ( Throwable $e ) {
				// Que una orden reviente no debe tumbar el sitio del cliente ni
				// dejar las demás órdenes sin ejecutar.
				self::anotar_resultado( $id, false, 'Error al ejecutar: ' . $e->getMessage() );
				continue;
			}

			if ( is_wp_error( $r ) ) {
				self::anotar_resultado( $id, false, $r->get_error_message() );
				continue;
			}

			self::anotar_resultado( $id, true, (string) $r );
			++$hechas;
		}

		return $hechas;
	}

	private static function hacer_activar_dos_pasos( array $carga ): string|WP_Error {
		if ( SWA_Dos_Factores::activo() ) {
			return 'Ya estaba activa.';
		}

		SWA_Ajustes::guardar( [ '2fa_activo' => 1 ] );

		return 'Verificación en dos pasos activada.';
	}

	/**
	 * Exige la verificación a los administradores.
	 *
	 * Con periodo de gracia de siete días, y esto no es por comodidad: sin
	 * gracia, el administrador que no haya configurado su segundo paso se
	 * queda fuera de su propio sitio en la siguiente entrada. Una norma de
	 * seguridad que deja al cliente afuera se desactiva el mismo día y no
	 * vuelve a encenderse nunca.
	 */
	private static function hacer_exigir_dos_pasos( array $carga ): string|WP_Error {
		$roles = (array) SWA_Ajustes::obtener( '2fa_roles', [] );

		if ( ! in_array( 'administrator', $roles, true ) ) {
			$roles[] = 'administrator';
		}

		$gracia = (int) SWA_Ajustes::obtener( '2fa_gracia_hasta', 0 );
		$nueva  = time() + 7 * DAY_IN_SECONDS;

		SWA_Ajustes::guardar(
			[
				'2fa_activo'       => 1,
				'2fa_roles'        => array_values( array_unique( $roles ) ),
				'2fa_gracia_hasta' => max( $gracia, $nueva ),
			]
		);

		return sprintf(
			'Exigida a los administradores, con una semana de gracia (hasta el %s) para que configuren su segundo paso.',
			wp_date( 'j/n/Y', max( $gracia, $nueva ) )
		);
	}

	private static function hacer_apagar_editor( array $carga ): string|WP_Error {
		if ( defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT && ! SWA_Ajustes::obtener( 'cerrar_editor' ) ) {
			return 'Ya estaba cerrado en wp-config.php, que es más firme que esto.';
		}

		SWA_Ajustes::guardar( [ 'cerrar_editor' => 1 ] );

		return 'Editor de código cerrado por el agente. Se abre de nuevo si alguien desactiva el plugin — para cerrarlo de forma definitiva hay que poner DISALLOW_FILE_EDIT en wp-config.php.';
	}

	private static function hacer_cerrar_xmlrpc( array $carga ): string|WP_Error {
		if ( SWA_Ajustes::obtener( 'cerrar_xmlrpc' ) ) {
			return 'Ya estaba cerrado.';
		}

		SWA_Ajustes::guardar( [ 'cerrar_xmlrpc' => 1 ] );

		return 'XML-RPC cerrado.';
	}

	private static function hacer_cerrar_registro( array $carga ): string|WP_Error {
		if ( ! get_option( 'users_can_register' ) ) {
			return 'Ya estaba cerrado.';
		}

		update_option( 'users_can_register', 0 );

		return 'Registro de usuarios cerrado.';
	}

	/**
	 * Cambia la contraseña de un usuario nuestro.
	 *
	 * Tres cosas que esta función NO hace, y cada una es a propósito:
	 *
	 *  - No crea el usuario si no existe. Una orden de cambiar contraseña que
	 *    de pronto crea un administrador nuevo es una puerta, no un arreglo.
	 *  - No toca a nadie que no sea administrador. El reseteo masivo es para
	 *    nuestras cuentas; los usuarios del cliente no son asunto nuestro.
	 *  - No manda correo al usuario. Lo manda el panel, a nosotros, cuando lo
	 *    decidamos — no 94 correos automáticos a 94 buzones.
	 */
	private static function hacer_resetear_clave( array $carga ): string|WP_Error {
		$login = sanitize_user( (string) ( $carga['login'] ?? '' ), true );
		$clave = (string) ( $carga['clave'] ?? '' );

		if ( '' === $login || '' === $clave ) {
			return new WP_Error( 'swa_reseteo', 'La orden llegó sin usuario o sin contraseña.' );
		}

		$usuario = get_user_by( 'login', $login );

		if ( ! $usuario ) {
			return new WP_Error(
				'swa_reseteo_sin_usuario',
				sprintf( 'En este sitio no existe un usuario «%s», así que no se cambió nada.', $login )
			);
		}

		if ( ! in_array( 'administrator', (array) $usuario->roles, true ) ) {
			return new WP_Error(
				'swa_reseteo_no_admin',
				sprintf( '«%s» existe pero no es administrador aquí. No se tocó.', $login )
			);
		}

		// Esto cierra todas sus sesiones abiertas, que es justo el punto: la
		// contraseña nueva no sirve de nada si la sesión vieja sigue viva.
		wp_set_password( $clave, $usuario->ID );

		return sprintf( 'Contraseña de «%s» cambiada y sesiones cerradas.', $login );
	}
}
