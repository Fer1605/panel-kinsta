<?php
/**
 * El reporte al panel: el diario, y el que sale en el momento.
 *
 * Este archivo arma y manda el reporte. Quién dispara el envío son dos: el
 * reloj, una vez al día, y SWA_Vigilante, en el instante en que cambia quién
 * tiene control del sitio.
 *
 * El sitio llama al panel, no al revés. Así este sitio no abre ninguna puerta
 * nueva: no queda un endpoint esperando peticiones de internet, que es el tipo
 * de cosa que se vuelve un problema dos años después.
 *
 * De paso, que el reporte DEJE de llegar también es información: un sitio que
 * lleva 48 horas sin reportar aparece marcado en el panel. Un sitio caído, o
 * uno donde alguien desactivó el agente, se delata solo.
 */

defined( 'ABSPATH' ) || exit;

class SWA_Reporte {

	public const HOOK = 'swa_reporte_diario';

	public static function init(): void {
		add_action( self::HOOK, [ __CLASS__, 'enviar' ] );
	}

	public static function programar(): void {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			// Escalonado por sitio: si 94 agentes llaman a la misma hora, el
			// panel recibe 94 peticiones en el mismo minuto. El desfase sale
			// del propio dominio, así que es estable entre corridas.
			$desfase = crc32( home_url() ) % 3600;
			wp_schedule_event( strtotime( 'tomorrow 05:00' ) + $desfase, 'daily', self::HOOK );
		}
	}

	public static function desprogramar(): void {
		$ts = wp_next_scheduled( self::HOOK );
		if ( $ts ) {
			wp_unschedule_event( $ts, self::HOOK );
		}
	}

	/**
	 * Los roles que se vigilan.
	 *
	 * Un sitio con 3.000 clientes de tienda no tiene por qué mandar 3.000 filas:
	 * lo que se vigila son los que tienen control. "Yo lo que quiero son
	 * administradores, pero son los que tienen control" (04:16).
	 *
	 * @return array<int,string>
	 */
	public static function roles_vigilados(): array {
		return (array) apply_filters(
			'swa_roles_vigilados',
			[ 'administrator', 'editor', 'shop_manager' ]
		);
	}

	/**
	 * Quién tiene control de este sitio ahora mismo.
	 *
	 * Vive aparte de armar() porque el vigilante la usa para sacar la huella y
	 * decidir si un cambio amerita aviso, sin tener que armar el reporte entero.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function usuarios_vigilados(): array {
		$usuarios = [];

		foreach ( get_users( [ 'role__in' => self::roles_vigilados() ] ) as $u ) {
			$usuarios[] = [
				'id'        => (int) $u->ID,
				'login'     => $u->user_login,
				'correo'    => $u->user_email,
				'nombre'    => $u->display_name,
				'roles'     => array_values( (array) $u->roles ),
				'alta'      => $u->user_registered,
				'dos_pasos' => (bool) get_user_meta( $u->ID, '_swa_configurado', true ),
			];
		}

		return $usuarios;
	}

	/**
	 * Lo que se manda. Nada más que esto.
	 *
	 * @return array<string,mixed>
	 */
	public static function armar( bool $liviano = false ): array {
		// El reporte liviano es el de «¿hay algo que hacer?», cada cinco
		// minutos. No lleva usuarios ni normas: con 94 sitios eso serían
		// 27.000 reportes completos al día para preguntar algo que casi
		// siempre se contesta con «nada». El panel está hecho para no escribir
		// nada por ausencia, así que un liviano no borra ni apaga nada.
		if ( $liviano ) {
			return [
				'agente'  => SWA_VERSION,
				'sitio'   => home_url(),
				'liviano' => true,
				'generado' => gmdate( 'c' ),
			];
		}

		$usuarios        = self::usuarios_vigilados();
		$roles_vigilados = self::roles_vigilados();

		$correo = SWA_Correo::estado();

		return [
			'agente'        => SWA_VERSION,
			'sitio'         => home_url(),
			'nombre'        => get_bloginfo( 'name' ),
			'wp'            => get_bloginfo( 'version' ),
			'php'           => PHP_VERSION,
			'generado'      => gmdate( 'c' ),
			'usuarios'      => $usuarios,
			'roles_vigilados' => $roles_vigilados,
			'dos_pasos'     => [
				'activo'            => SWA_Dos_Factores::activo(),
				'roles'             => (array) SWA_Ajustes::obtener( '2fa_roles', [] ),
				'gracia_hasta'      => (int) SWA_Ajustes::obtener( '2fa_gracia_hasta', 0 ),
				'apagado_automatico' => SWA_Dos_Factores::apagado_automatico(),
			],
			'correo'        => [
				'puede_enviar' => SWA_Correo::puede_enviar(),
				'envio_ok'     => (bool) ( $correo['ok'] ?? false ),
				'confirmado'   => (bool) ( $correo['confirmado'] ?? false ),
				'error'        => (string) ( $correo['error'] ?? '' ),
				'probado'      => (string) ( $correo['probado'] ?? '' ),
			],
			'xmlrpc_abierto' => (bool) apply_filters( 'xmlrpc_enabled', true ),
			// Lo del audio de normas de seguridad. Son cosas que solo se saben
			// estando dentro del sitio: el panel no las puede ver desde fuera.
			'normas'        => SWA_Normas::medir(),
			// Los archivos que sube la gente por un formulario. Solo cuentas y
			// sí/no: el nombre de un archivo subido no sale del sitio. Ver
			// SWA_Formularios.
			'formularios'   => SWA_Formularios::medir( ! $liviano ),
			// Configuración, permisos e integridad. Lento de calcular, así que
			// va en caché de doce horas y solo se rehace en el reporte diario.
			'fondo'         => SWA_Fondo::medir( ! $liviano ),
			// Quién cerró el editor importa tanto como que esté cerrado: si lo
			// cerró el agente, se abre de nuevo al desactivar el plugin.
			'editor_codigo_por' => ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS ) || ( defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT && ! SWA_Ajustes::obtener( 'cerrar_editor' ) )
				? 'wp-config'
				: ( SWA_Ajustes::obtener( 'cerrar_editor' ) ? 'agente' : '' ),
		];
	}

	/** Registra este sitio en el panel y guarda el token y el secreto propios. */
	public static function registrar(): true|WP_Error {
		$a = SWA_Ajustes::todos();

		if ( '' === $a['panel_url'] || '' === $a['clave_registro'] ) {
			return new WP_Error( 'swa_faltan_datos', 'Falta la dirección del panel o la clave de registro.' );
		}

		$r = wp_remote_post(
			trailingslashit( $a['panel_url'] ) . 'wp-json/swpk/v1/registro',
			[
				'timeout' => 20,
				'headers' => [ 'Content-Type' => 'application/json' ],
				'body'    => wp_json_encode(
					[
						'clave'  => $a['clave_registro'],
						'sitio'  => home_url(),
						'nombre' => get_bloginfo( 'name' ),
					]
				),
			]
		);

		if ( is_wp_error( $r ) ) {
			return $r;
		}

		$codigo = (int) wp_remote_retrieve_response_code( $r );
		$datos  = json_decode( (string) wp_remote_retrieve_body( $r ), true );

		if ( 200 !== $codigo || ! is_array( $datos ) || empty( $datos['token'] ) || empty( $datos['secreto'] ) ) {
			return new WP_Error(
				'swa_registro',
				sprintf( 'El panel respondió %d: %s', $codigo, is_array( $datos ) ? ( $datos['message'] ?? 'sin detalle' ) : 'respuesta ilegible' )
			);
		}

		SWA_Ajustes::guardar(
			[
				'token'   => (string) $datos['token'],
				'secreto' => (string) $datos['secreto'],
			]
		);

		return true;
	}

	/**
	 * Manda el reporte y ejecuta lo que el panel haya dejado pendiente.
	 *
	 * @param bool                           $liviano    Sin usuarios ni normas: solo preguntar.
	 * @param array<int,array<string,mixed>> $resultados Lo que quedó por contarle al panel.
	 */
	public static function enviar( bool $liviano = false, array $resultados = [] ): true|WP_Error {
		$a = SWA_Ajustes::todos();

		if ( ! SWA_Ajustes::registrado() ) {
			return new WP_Error( 'swa_sin_registro', 'Este sitio todavía no está registrado en el panel.' );
		}

		$datos = self::armar( $liviano );

		// Los resultados de órdenes anteriores viajan en el mismo viaje. Si no
		// se los pasaron, se recogen de la cola: así un reporte diario también
		// arrastra lo que un intento anterior no logró contar.
		$pendientes = $resultados ?: SWA_Ordenes::pendientes_de_contar();

		if ( $pendientes ) {
			$datos['ordenes_hechas'] = $pendientes;
		}

		$cuerpo = wp_json_encode( $datos );
		$marca  = time();
		$firma  = SWA_Firma::firmar( (string) $cuerpo, $marca, $a['secreto'] );

		$r = wp_remote_post(
			trailingslashit( $a['panel_url'] ) . 'wp-json/swpk/v1/reporte',
			[
				'timeout' => 25,
				'headers' => [
					'Content-Type'  => 'application/json',
					'X-SWA-Token'   => $a['token'],
					'X-SWA-Marca'   => (string) $marca,
					'X-SWA-Firma'   => $firma,
				],
				'body'    => $cuerpo,
			]
		);

		if ( is_wp_error( $r ) ) {
			update_option( 'swa_ultimo_error', $r->get_error_message(), false );
			return $r;
		}

		$codigo = (int) wp_remote_retrieve_response_code( $r );
		if ( $codigo < 200 || $codigo >= 300 ) {
			$mensaje = sprintf( 'El panel respondió %d.', $codigo );
			update_option( 'swa_ultimo_error', $mensaje, false );
			return new WP_Error( 'swa_reporte', $mensaje );
		}

		update_option( 'swa_ultimo_reporte', current_time( 'mysql' ), false );
		delete_option( 'swa_ultimo_error' );

		// El panel confirmó, así que lo que ya se le contó se puede olvidar.
		if ( $pendientes ) {
			SWA_Ordenes::olvidar_contados( $pendientes );
		}

		// Y lo último: ejecutar lo que el panel dejó pendiente. Va aquí, al
		// final, porque solo se obedece a una respuesta que llegó por esta
		// ruta — que es la única que va firmada con el secreto de este sitio.
		$respuesta = json_decode( (string) wp_remote_retrieve_body( $r ), true );

		if ( is_array( $respuesta ) && ! empty( $respuesta['ordenes'] ) && is_array( $respuesta['ordenes'] ) ) {
			SWA_Ordenes::ejecutar( $respuesta['ordenes'] );
		}

		// El panel ya conoce esta lista. Sirve igual si el envío salió del
		// reporte diario o del aviso instantáneo: después de cualquiera de los
		// dos, lo que hay aquí y lo que hay allá coinciden.
		// La huella solo se renueva cuando de verdad se mandó la lista. Un
		// liviano no la lleva, así que decir «el panel ya la conoce» taparía
		// un alta de administrador hasta el día siguiente.
		if ( ! $liviano ) {
			SWA_Vigilante::recordar_huella();
		}

		return true;
	}
}
