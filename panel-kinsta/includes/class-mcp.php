<?php
/**
 * El panel, hablando MCP: un conector remoto que se agrega pegando una URL.
 *
 * ─── Por qué esto y no el archivo de Node ───────────────────────────────────
 *
 * El conector de `conector-mcp/` funciona, pero pide bajar una carpeta del
 * repositorio, tener Node y editar un archivo de configuración a mano. Fer lo
 * cortó en seco, y tenía razón:
 *
 *   "Nadie tiene acceso al repositorio, amigo. Y no vamos a estar haciendo
 *    eso (...) solo le das en conectores, agregar un conector personalizado,
 *    ingresas un nombre, ingresas la URL del servidor del MCP, le das
 *    conectar."
 *
 * Eso es un MCP remoto, y lo sirve el propio panel. El panel ya tiene los
 * datos, ya tiene las rutas y ya sabe quién puede ver qué; lo único que
 * faltaba era que hablara el idioma.
 *
 * ─── Lo que hay que saber antes de probarlo ─────────────────────────────────
 *
 * Claude Desktop NO se conecta desde la computadora de quien lo usa. Manda la
 * URL a la nube de Anthropic y es la nube la que abre la conexión al servidor.
 * De ahí se siguen tres cosas que no son opinables:
 *
 *   - Tiene que ser HTTPS y público. `localhost` y las direcciones privadas
 *     las rechaza de entrada, así que esto NO se puede probar desde una
 *     máquina de la casa ni siquiera por quien la tiene enfrente.
 *   - El transporte es Streamable HTTP: JSON-RPC por POST a una sola ruta.
 *   - Si el servidor estuviera detrás de un cortafuegos, hay que dejar entrar
 *     al rango de salida de Anthropic.
 *
 * ─── Cómo se identifica ─────────────────────────────────────────────────────
 *
 * El secreto va en la propia URL, que es lo que permite que conectarse sea
 * pegar una dirección y nada más. La URL ES la credencial: quien la tenga,
 * consulta. Por eso:
 *
 *   - Sale de una llave, con su mismo alcance de solo lectura y su mismo
 *     recorte por perfil. Revocar la llave mata la URL y la contraseña a la vez.
 *   - Se guarda cifrada, no en claro: en la base de datos vive su huella. Se
 *     enseña una sola vez, igual que la contraseña, por el mismo motivo.
 *   - Cada llamada queda escrita en `swpk_accesos`, marcada como MCP.
 *
 * La alternativa era OAuth, con su pantalla de "iniciar sesión" por persona.
 * Es más fino y es a dónde habría que ir si un día cada quien debe entrar con
 * lo suyo; hoy sería pedirle al jefe que haga más pasos para conseguir lo
 * mismo, que es exactamente lo que pidió no hacer.
 */

defined( 'ABSPATH' ) || exit;

class SWPK_Mcp {

	/** La versión del protocolo que contestamos si el cliente no pide otra. */
	public const PROTOCOLO = '2024-11-05';

	/** Marca si la petición que corre por dentro entró por MCP, para el registro. */
	public static bool $en_curso = false;

	public static function init(): void {
		add_action( 'rest_api_init', [ __CLASS__, 'ruta' ] );
	}

	public static function ruta(): void {
		register_rest_route(
			SWPK_Api::ESPACIO,
			'/mcp/(?P<token>[a-f0-9]{32})',
			[
				'methods'             => 'POST',
				'callback'            => [ __CLASS__, 'atender' ],
				'permission_callback' => [ __CLASS__, 'comprobar' ],
			]
		);
	}

	/** La dirección que se pega en Claude Desktop. */
	public static function url( string $token ): string {
		return rest_url( SWPK_Api::ESPACIO . '/mcp/' . $token );
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Identificación
	// ─────────────────────────────────────────────────────────────────────────

	public static function comprobar( WP_REST_Request $peticion ): bool|WP_Error {
		$llave = SWPK_Llaves::por_token( (string) $peticion->get_param( 'token' ) );

		if ( ! $llave ) {
			// Sin login no hay a quién anotarle la llamada, pero el intento sí
			// queda: una URL de MCP que empieza a fallar es o una llave
			// revocada que alguien sigue usando, o alguien probando.
			SWPK_Api::anotar_llamada( 'MCP · token que no existe', false );

			return new WP_Error(
				'swpk_mcp_sin_llave',
				'Esa dirección no corresponde a ninguna llave. Puede que la hayan revocado desde el panel.',
				[ 'status' => 401 ]
			);
		}

		wp_set_current_user( $llave->ID );

		return true;
	}

	// ─────────────────────────────────────────────────────────────────────────
	// El protocolo
	// ─────────────────────────────────────────────────────────────────────────

	public static function atender( WP_REST_Request $peticion ): WP_REST_Response {
		$mensaje = $peticion->get_json_params();

		if ( ! is_array( $mensaje ) ) {
			return self::fallar( null, -32700, 'Eso no es JSON.' );
		}

		$id     = $mensaje['id'] ?? null;
		$metodo = (string) ( $mensaje['method'] ?? '' );
		$params = (array) ( $mensaje['params'] ?? [] );

		// Sin id es una notificación: el protocolo pide 202 y ningún cuerpo.
		if ( null === $id ) {
			return new WP_REST_Response( null, 202 );
		}

		if ( 'initialize' === $metodo ) {
			SWPK_Api::anotar_llamada( 'MCP · se conectó', true );

			return self::responder(
				$id,
				[
					'protocolVersion' => (string) ( $params['protocolVersion'] ?? self::PROTOCOLO ),
					'capabilities'    => [ 'tools' => (object) [] ],
					'serverInfo'      => [
						'name'    => 'Panel Kinsta',
						'version' => defined( 'SWPK_VERSION' ) ? SWPK_VERSION : '1.0.0',
					],
					'instructions'    => 'Este es el panel de monitoreo de los sitios. Contesta con datos guardados, '
						. 'no con suposiciones, y toda respuesta trae de cuándo es el dato. Para cualquier pregunta '
						. 'del tipo "¿cómo estamos?" empieza por panel_estado.',
				]
			);
		}

		if ( 'ping' === $metodo ) {
			return self::responder( $id, (object) [] );
		}

		if ( 'tools/list' === $metodo ) {
			// Solo las tres claves del protocolo. Las que empiezan con guion bajo
			// son nuestras —a qué ruta va cada herramienta, qué exige— y no tienen
			// por qué viajar: dicen de la casa más de lo que hace falta.
			$publicas = array_map(
				static fn( array $t ) => [
					'name'        => $t['name'],
					'description' => $t['description'],
					'inputSchema' => $t['inputSchema'],
				],
				array_values( self::herramientas() )
			);

			return self::responder( $id, [ 'tools' => $publicas ] );
		}

		if ( 'tools/call' === $metodo ) {
			return self::llamar( $id, $params );
		}

		// Lo que no sean herramientas se contesta como manda el protocolo, en
		// vez de con un error genérico que el cliente registraría como caída.
		if ( in_array( $metodo, [ 'resources/list', 'prompts/list' ], true ) ) {
			$clave = 'resources/list' === $metodo ? 'resources' : 'prompts';

			return self::responder( $id, [ $clave => [] ] );
		}

		return self::fallar( $id, -32601, sprintf( 'Este servidor no hace "%s".', $metodo ) );
	}

	private static function llamar( $id, array $params ): WP_REST_Response {
		$nombre = (string) ( $params['name'] ?? '' );
		$donde  = self::herramientas()[ $nombre ] ?? null;

		if ( ! $donde ) {
			return self::fallar( $id, -32602, sprintf( 'Aquí no hay una herramienta "%s".', $nombre ) );
		}

		$args = (array) ( $params['arguments'] ?? [] );

		foreach ( $donde['_requiere'] as $obligatorio ) {
			if ( empty( $args[ $obligatorio ] ) ) {
				return self::responder( $id, self::texto( sprintf( 'Hace falta "%s".', $obligatorio ), true ) );
			}
		}

		// La herramienta no hace nada por su cuenta: arma la misma petición que
		// haría cualquiera desde fuera y la manda por la puerta de siempre. Así
		// el alcance, el recorte por perfil y el registro son los mismos, sin
		// una sola línea repetida.
		$ruta = $donde['_ruta'];

		if ( isset( $donde['_en_la_ruta'] ) ) {
			$ruta = str_replace( '{id}', rawurlencode( (string) $args[ $donde['_en_la_ruta'] ] ), $ruta );
			unset( $args[ $donde['_en_la_ruta'] ] );
		}

		$interna = new WP_REST_Request( 'GET', '/' . SWPK_Api::ESPACIO . $ruta );

		foreach ( $args as $clave => $valor ) {
			$interna->set_param( (string) $clave, $valor );
		}

		self::$en_curso = true;
		$respuesta      = rest_do_request( $interna );
		self::$en_curso = false;

		if ( $respuesta->is_error() ) {
			$error = $respuesta->as_error();

			return self::responder(
				$id,
				self::texto(
					sprintf( '%s (%d)', $error->get_error_message(), (int) $respuesta->get_status() ),
					true
				)
			);
		}

		return self::responder(
			$id,
			self::texto( (string) wp_json_encode( $respuesta->get_data(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) )
		);
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Las herramientas: las mismas siete rutas de lectura, con los mismos
	// nombres de parámetro. Las tres que disparan procesos no se entregan.
	// ─────────────────────────────────────────────────────────────────────────

	private static function herramientas(): array {
		$fecha  = [ 'type' => 'string', 'description' => 'Fecha en formato AAAA-MM-DD.' ];
		$niveles = [ 'critico', 'alto', 'medio', 'bajo' ];
		$grupos  = [ 'desarrollo', 'auto_update', 'no_update', 'sin_etiqueta' ];

		$h = [
			'panel_estado' => [
				'_ruta'       => '/estado',
				'description' => 'El veredicto del panel hoy: cuántos sitios necesitan atención, cuáles no pueden '
					. 'esperar, cuáles están caídos y de cuáles no se sabe nada. Empieza por aquí para cualquier '
					. 'pregunta del tipo "¿cómo estamos?" o "¿hay algo urgente?".',
				'inputSchema' => [ 'type' => 'object', 'properties' => (object) [] ],
			],
			'panel_sitios' => [
				'_ruta'       => '/sitios',
				'description' => 'La cartera de sitios con su nivel de riesgo, su grupo y de cuándo es el dato de '
					. 'cada uno. Con 108 sitios conviene filtrar: sin filtros la respuesta es larga.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'grupo'      => [ 'type' => 'string', 'enum' => $grupos, 'description' => 'desarrollo y auto_update son sitios que administramos nosotros; no_update son del cliente.' ],
						'nivel'      => [ 'type' => 'string', 'enum' => $niveles ],
						'q'          => [ 'type' => 'string', 'description' => 'Busca en el nombre del sitio.' ],
						'pagina'     => [ 'type' => 'integer' ],
						'por_pagina' => [ 'type' => 'integer' ],
					],
				],
			],
			'panel_sitio' => [
				'_ruta'       => '/sitios/{id}',
				'_en_la_ruta' => 'id',
				'description' => 'La ficha completa de un sitio: hallazgos de seguridad, quién tiene acceso, qué le '
					. 'pasó últimamente y qué se le avisó al cliente. El id sale de panel_sitios, campo sitio_id.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [ 'id' => [ 'type' => 'string', 'description' => 'El sitio_id que devuelve panel_sitios.' ] ],
					'required'   => [ 'id' ],
				],
			],
			'panel_vulnerabilidades' => [
				'_ruta'       => '/vulnerabilidades',
				'description' => 'Los hallazgos de seguridad de toda la cartera, uno por fila, con el sitio, el '
					. 'módulo, qué permite hacer la falla y desde cuándo. Para "¿qué hay que parchar?".',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'nivel'      => [ 'type' => 'string', 'enum' => $niveles ],
						'sitio'      => [ 'type' => 'string', 'description' => 'Un sitio_id.' ],
						'pagina'     => [ 'type' => 'integer' ],
						'por_pagina' => [ 'type' => 'integer' ],
					],
				],
			],
			'panel_usuarios' => [
				'_ruta'       => '/usuarios',
				'description' => 'Quién tiene control de cada sitio nuestro, con su rol y su correo. Esto no lo da '
					. 'el hosting: lo reporta el plugin instalado en cada sitio.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [ 'sitio' => [ 'type' => 'string', 'description' => 'Un sitio_id.' ] ],
				],
			],
			'panel_eventos' => [
				'_ruta'       => '/eventos',
				'description' => 'Qué cambió y cuándo: actualizaciones, vulnerabilidades que aparecieron o se '
					. 'resolvieron, caídas, administradores nuevos. Para preguntas con fechas, como "qué pasó esta '
					. 'semana" o "cuántas veces se actualizó este sitio este mes".',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'desde'      => $fecha,
						'hasta'      => $fecha,
						'sitio'      => [ 'type' => 'string', 'description' => 'Un sitio_id.' ],
						'tipo'       => [ 'type' => 'string' ],
						'por_pagina' => [ 'type' => 'integer' ],
					],
				],
			],
			'panel_bitacora' => [
				'_ruta'       => '/bitacora',
				'description' => 'Qué se le notificó a quién y cuándo, con el resultado del envío. Es el registro de '
					. 'lo que se le avisó al cliente, no de lo que pasó en el sitio — para eso está panel_eventos.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'desde'      => $fecha,
						'hasta'      => $fecha,
						'tipo'       => [ 'type' => 'string' ],
						'sitio'      => [ 'type' => 'string', 'description' => 'Un sitio_id.' ],
						'cuerpo'     => [ 'type' => 'boolean', 'description' => 'true para traer también el texto que se mandó.' ],
						'por_pagina' => [ 'type' => 'integer' ],
					],
				],
			],
		];

		foreach ( $h as $nombre => $datos ) {
			$h[ $nombre ]['name']      = $nombre;
			$h[ $nombre ]['_requiere'] = $datos['inputSchema']['required'] ?? [];
		}

		return $h;
	}

	/** Lo que se le entrega a Claude: el catálogo sin nuestras claves internas. */
	public static function catalogo(): array {
		return array_map(
			static fn( array $t ) => [
				'nombre' => $t['name'],
				'que'    => $t['description'],
			],
			array_values( self::herramientas() )
		);
	}

	// ─────────────────────────────────────────────────────────────────────────

	private static function texto( string $t, bool $falla = false ): array {
		return [
			'content' => [ [ 'type' => 'text', 'text' => $t ] ],
			'isError' => $falla,
		];
	}

	private static function responder( $id, $resultado ): WP_REST_Response {
		return new WP_REST_Response( [ 'jsonrpc' => '2.0', 'id' => $id, 'result' => $resultado ], 200 );
	}

	private static function fallar( $id, int $codigo, string $mensaje ): WP_REST_Response {
		return new WP_REST_Response(
			[ 'jsonrpc' => '2.0', 'id' => $id, 'error' => [ 'code' => $codigo, 'message' => $mensaje ] ],
			200
		);
	}
}
