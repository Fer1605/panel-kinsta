<?php
/**
 * El reporte diario al panel.
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
	 * Lo que se manda. Nada más que esto.
	 *
	 * @return array<string,mixed>
	 */
	public static function armar(): array {
		$usuarios = [];

		// Solo los roles que importan. Un sitio con 3.000 clientes de tienda no
		// tiene por qué mandar 3.000 filas todos los días: lo que se vigila son
		// los que tienen control. "Yo lo que quiero son administradores, pero
		// son los que tienen control" (04:16).
		$roles_vigilados = (array) apply_filters(
			'swa_roles_vigilados',
			[ 'administrator', 'editor', 'shop_manager' ]
		);

		foreach ( get_users( [ 'role__in' => $roles_vigilados ] ) as $u ) {
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
				'activo'       => SWA_Dos_Factores::activo(),
				'roles'        => (array) SWA_Ajustes::obtener( '2fa_roles', [] ),
				'gracia_hasta' => (int) SWA_Ajustes::obtener( '2fa_gracia_hasta', 0 ),
			],
			'correo'        => [
				'puede_enviar' => (bool) ( $correo['ok'] ?? false ),
				'error'        => (string) ( $correo['error'] ?? '' ),
				'probado'      => (string) ( $correo['probado'] ?? '' ),
			],
			'xmlrpc_abierto' => (bool) apply_filters( 'xmlrpc_enabled', true ),
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

	public static function enviar(): true|WP_Error {
		$a = SWA_Ajustes::todos();

		if ( ! SWA_Ajustes::registrado() ) {
			return new WP_Error( 'swa_sin_registro', 'Este sitio todavía no está registrado en el panel.' );
		}

		$cuerpo = wp_json_encode( self::armar() );
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

		return true;
	}
}
