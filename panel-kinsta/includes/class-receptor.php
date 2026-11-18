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
 * La respuesta del reporte es, además, por donde bajan las órdenes. No hay una
 * tercera ruta para eso a propósito: una ruta más sería una puerta más que
 * proteger, y el reporte ya va firmado, ya está autenticado y ya pasa por aquí.
 * El agente pregunta cada cinco minutos con un reporte liviano; la respuesta le
 * dice lo que tiene pendiente. Ver SWPK_Ordenes.
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
			// Por las dos cosas: la dirección con la que el plugin se presenta
			// y el id con el que Kinsta conoce al sitio. Se resuelve una vez,
			// aquí, en vez de emparejar dominios cada vez que haga falta cruzar.
			'sitio_id'      => SWPK_Usuarios::sitio_id( $sitio ),
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

		// ── Liviano o completo ──────────────────────────────────────────────
		//
		// El agente pregunta por órdenes cada cinco minutos, y ese reporte no
		// trae usuarios ni normas: sería mandar la lista entera 288 veces al
		// día para preguntar si hay algo que hacer.
		//
		// Por eso NADA de lo que sigue se escribe por ausencia. Si el reporte
		// no trae un dato, el dato no se toca. Escribirlo como `0` cuando no
		// vino sería peor que no tenerlo: un reporte liviano apagaría en el
		// panel la verificación en dos pasos de un sitio que la tiene puesta, y
		// `procesar()` con la lista vacía daría de baja a TODOS los usuarios
		// del sitio y anotaría un evento por cada uno. Eso no es un detalle de
		// estilo, es la diferencia entre un panel y una alarma que miente.

		$liviano = ! empty( $datos['liviano'] );

		$campos = [ 'ultimo_reporte' => current_time( 'mysql' ) ];

		if ( isset( $datos['agente'] ) ) {
			$campos['version'] = sanitize_text_field( (string) $datos['agente'] );
		}

		if ( isset( $datos['correo'] ) && is_array( $datos['correo'] ) ) {
			$campos['puede_enviar_correo'] = ! empty( $datos['correo']['puede_enviar'] ) ? 1 : 0;
			$campos['error_correo']        = sanitize_text_field( (string) ( $datos['correo']['error'] ?? '' ) );
		}

		if ( isset( $datos['dos_pasos'] ) && is_array( $datos['dos_pasos'] ) ) {
			$campos['dos_pasos'] = ! empty( $datos['dos_pasos']['activo'] ) ? 1 : 0;
		}

		if ( isset( $datos['xmlrpc_abierto'] ) ) {
			$campos['xmlrpc_abierto'] = $datos['xmlrpc_abierto'] ? 1 : 0;
		}

		$wpdb->update( $tabla, $campos, [ 'id' => (int) $agente->id ] );

		$cambios = isset( $datos['usuarios'] ) && is_array( $datos['usuarios'] )
			? SWPK_Usuarios::procesar( (string) $agente->sitio_url, $datos['usuarios'] )
			: 0;

		// El sitio_id se resolvió al registrar. Si por lo que sea está vacío
		// —un sitio que se registró antes de que esa columna existiera— se
		// vuelve a resolver aquí en vez de dejar el cumplimiento sin dueño.
		$sitio_id = (string) $agente->sitio_id;

		if ( '' === $sitio_id ) {
			$sitio_id = SWPK_Usuarios::sitio_id( (string) $agente->sitio_url );

			if ( '' !== $sitio_id ) {
				$wpdb->update( $tabla, [ 'sitio_id' => $sitio_id ], [ 'id' => (int) $agente->id ] );
			}
		}

		// Las normas se miden con el reporte en la mano, no después: lo que
		// hace falta para medirlas es justo lo que acaba de llegar.
		// Lo de la tienda se guarda ANTES de medir, porque de ahí sale el tipo
		// de sitio y el tipo decide qué normas aplican.
		if ( ! $liviano && '' !== $sitio_id && ! empty( $datos['tienda'] ) ) {
			SWPK_Tienda::guardar( $sitio_id, (array) $datos['tienda'] );
			SWPK_Tipos::clasificar( $sitio_id );
		}

		$normas = ( ! $liviano && '' !== $sitio_id ) ? SWPK_Normas::medir( $sitio_id, $datos ) : [];

		// Un agente que no se puede emparejar con ningún sitio del inventario
		// es un agujero silencioso: reporta todos los días, el panel recibe
		// todo, y el cumplimiento se mide contra nadie — así que ese sitio
		// aparece «sin medir» para siempre sin que nada explique por qué.
		//
		// Pasa cuando el dominio con el que el plugin se presenta no coincide
		// con ninguno del hosting: un sitio en una dirección temporal, uno que
		// todavía no está en el inventario, o uno que vive en otro proveedor.
		// Se anota como evento para que se vea, y una sola vez al día para que
		// no llene la bitácora.
		if ( ! $liviano && '' === $sitio_id ) {
			SWPK_Eventos::registrar(
				'agente_sin_sitio',
				'',
				sprintf(
					'%s tiene el agente reportando, pero su dirección no coincide con ningún sitio del inventario, así que sus normas de seguridad no se pueden medir.',
					(string) $agente->sitio_url
				),
				'aviso',
				'sin-sitio-' . md5( (string) $agente->sitio_url )
			);
		}

		// Y el cumplimiento que cambió puede volver urgente un sitio que el
		// Resumen tenía por tranquilo, así que el recorrido en caché se olvida.
		if ( $normas ) {
			SWPK_Estado::olvidar();
		}

		// Lo que el agente contesta de órdenes anteriores, antes de darle más:
		// así una orden que falló no se queda contada como pendiente.
		$cerradas = SWPK_Ordenes::recibir_resultados(
			(string) $agente->sitio_url,
			(array) ( $datos['ordenes_hechas'] ?? [] )
		);

		return new WP_REST_Response(
			[
				'ok'       => true,
				'usuarios' => count( (array) ( $datos['usuarios'] ?? [] ) ),
				'cambios'  => $cambios,
				'normas'   => count( $normas ),
				'cerradas' => $cerradas,
				// Lo último del cuerpo y lo único que el agente tiene que
				// obedecer. Va firmado por venir de esta ruta, que ya validó
				// token, marca de tiempo y firma antes de llegar aquí.
				'ordenes'  => SWPK_Ordenes::entregar( (string) $agente->sitio_url ),
			],
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
