<?php
/**
 * Recibe los reportes de los agentes instalados en los sitios.
 *
 * Dos rutas y nada más:
 *   POST /wp-json/swpk/v1/registro  — una vez por sitio, con la clave de
 *                                      registro. El panel le entrega a ese
 *                                      sitio su propio token y su secreto.
 *   POST /wp-json/swpk/v1/reporte   — todos los días, firmado con ese secreto.
 *
 * Por qué un secreto por sitio y no uno compartido: con uno compartido, un
 * sitio comprometido podría inventar reportes a nombre de cualquier otro —
 * decir, por ejemplo, que el sitio de otro cliente no tiene administradores
 * nuevos. Con uno por sitio, lo que se filtre en un sitio solo sirve para ese.
 */

defined( 'ABSPATH' ) || exit;

class SWPK_Receptor {

	public const ESPACIO = 'swpk/v1';
	public const VENTANA = 300;

	public static function init(): void {
		add_action( 'rest_api_init', [ __CLASS__, 'rutas' ] );
	}

	public static function rutas(): void {
		register_rest_route(
			self::ESPACIO,
			'/registro',
			[
				'methods'             => 'POST',
				'callback'            => [ __CLASS__, 'registro' ],
				'permission_callback' => '__return_true', // se valida con la clave, adentro
			]
		);

		register_rest_route(
			self::ESPACIO,
			'/reporte',
			[
				'methods'             => 'POST',
				'callback'            => [ __CLASS__, 'reporte' ],
				'permission_callback' => '__return_true', // se valida con la firma, adentro
			]
		);
	}

	/** La clave con la que un agente se registra. Se genera sola la primera vez. */
	public static function clave_registro(): string {
		$clave = (string) get_option( 'swpk_clave_registro', '' );

		if ( '' === $clave ) {
			$clave = wp_generate_password( 32, false, false );
			update_option( 'swpk_clave_registro', $clave, false );
		}

		return $clave;
	}

	public static function registro( WP_REST_Request $peticion ): WP_REST_Response|WP_Error {
		$datos = (array) $peticion->get_json_params();

		$clave = (string) ( $datos['clave'] ?? '' );
		$sitio = esc_url_raw( (string) ( $datos['sitio'] ?? '' ) );

		if ( ! hash_equals( self::clave_registro(), $clave ) ) {
			return new WP_Error( 'swpk_clave', 'Clave de registro incorrecta.', [ 'status' => 403 ] );
		}

		if ( '' === $sitio ) {
			return new WP_Error( 'swpk_sitio', 'Falta la dirección del sitio.', [ 'status' => 400 ] );
		}

		global $wpdb;
		$tabla = SWPK_Tablas::tabla( 'agentes' );

		// Un re-registro renueva el secreto. Es lo que se quiere: si hay que
		// revocar un sitio, se borra su fila y se vuelve a registrar.
		$token   = wp_generate_password( 32, false, false );
		$secreto = wp_generate_password( 64, false, false );

		$existe = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$tabla} WHERE sitio_url = %s", $sitio ) );

		$fila = [
			'sitio_url'     => $sitio,
			'nombre'        => sanitize_text_field( (string) ( $datos['nombre'] ?? '' ) ),
			'token'         => $token,
			'secreto'       => $secreto,
			'registrado_en' => current_time( 'mysql' ),
		];

		if ( $existe ) {
			$wpdb->update( $tabla, $fila, [ 'id' => (int) $existe ] );
		} else {
			$wpdb->insert( $tabla, $fila );
		}

		SWPK_Eventos::registrar(
			'agente_registrado',
			'',
			sprintf( 'El agente se registró en %s', $sitio ),
			'info'
		);

		return new WP_REST_Response( [ 'token' => $token, 'secreto' => $secreto ], 200 );
	}

	public static function reporte( WP_REST_Request $peticion ): WP_REST_Response|WP_Error {
		global $wpdb;

		$token = (string) $peticion->get_header( 'x-swa-token' );
		$marca = (int) $peticion->get_header( 'x-swa-marca' );
		$firma = (string) $peticion->get_header( 'x-swa-firma' );
		$cuerpo = (string) $peticion->get_body();

		if ( '' === $token || '' === $firma ) {
			return new WP_Error( 'swpk_sin_firma', 'Falta el token o la firma.', [ 'status' => 401 ] );
		}

		$tabla  = SWPK_Tablas::tabla( 'agentes' );
		$agente = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tabla} WHERE token = %s", $token ) );

		if ( ! $agente ) {
			return new WP_Error( 'swpk_token', 'Token desconocido.', [ 'status' => 403 ] );
		}

		// La marca de tiempo entra en la firma y se rechaza lo viejo: así nadie
		// puede guardar un reporte de hace un mes y volver a mandarlo para tapar
		// que hoy apareció un administrador nuevo.
		if ( abs( time() - $marca ) > self::VENTANA ) {
			return new WP_Error( 'swpk_marca', 'La marca de tiempo está fuera de rango.', [ 'status' => 401 ] );
		}

		$esperada = hash_hmac( 'sha256', $marca . "\n" . $cuerpo, (string) $agente->secreto );
		if ( ! hash_equals( $esperada, $firma ) ) {
			return new WP_Error( 'swpk_firma', 'La firma no corresponde.', [ 'status' => 403 ] );
		}

		$datos = json_decode( $cuerpo, true );
		if ( ! is_array( $datos ) ) {
			return new WP_Error( 'swpk_json', 'El cuerpo no es JSON válido.', [ 'status' => 400 ] );
		}

		$wpdb->update(
			$tabla,
			[
				'ultimo_reporte'      => current_time( 'mysql' ),
				'version'             => sanitize_text_field( (string) ( $datos['agente'] ?? '' ) ),
				'puede_enviar_correo' => ! empty( $datos['correo']['puede_enviar'] ) ? 1 : 0,
				'error_correo'        => sanitize_text_field( (string) ( $datos['correo']['error'] ?? '' ) ),
				'dos_pasos'           => ! empty( $datos['dos_pasos']['activo'] ) ? 1 : 0,
				'xmlrpc_abierto'      => ! empty( $datos['xmlrpc_abierto'] ) ? 1 : 0,
			],
			[ 'id' => (int) $agente->id ]
		);

		$cambios = SWPK_Usuarios::procesar( (string) $agente->sitio_url, (array) ( $datos['usuarios'] ?? [] ) );

		return new WP_REST_Response(
			[ 'ok' => true, 'usuarios' => count( (array) ( $datos['usuarios'] ?? [] ) ), 'cambios' => $cambios ],
			200
		);
	}

	/** @return array<int,object> */
	public static function agentes(): array {
		global $wpdb;
		$tabla = SWPK_Tablas::tabla( 'agentes' );
		return $wpdb->get_results( "SELECT * FROM {$tabla} ORDER BY nombre ASC" ) ?: [];
	}

	/** Un agente que lleva más de 48 horas sin reportar es, por sí mismo, una señal. */
	public static function callados( int $horas = 48 ): array {
		global $wpdb;
		$tabla  = SWPK_Tablas::tabla( 'agentes' );
		$limite = SWPK_Frescura::limite( $horas * HOUR_IN_SECONDS );

		return $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$tabla} WHERE ultimo_reporte IS NULL OR ultimo_reporte < %s", $limite )
		) ?: [];
	}
}
