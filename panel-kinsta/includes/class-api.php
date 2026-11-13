<?php
/**
 * La puerta de consulta del panel.
 *
 * ─── Por qué existe ─────────────────────────────────────────────────────────
 *
 * El jefe lo pidió en dos líneas, después de aprobar la lógica:
 *
 *   "recuerda que tendra fases asi que las consultas y procesos mejor si
 *    tienen api de consumo"
 *
 *   "debe poder consultarse o correrse manualmente los procesos o consultar un
 *    sitio por si alguien pregunta puedo hacerlo manual y no esperar"
 *
 * ─── Una sola puerta ────────────────────────────────────────────────────────
 *
 * Quien consulte le pregunta al panel y el panel contesta con todo, incluido lo
 * que vino del hosting y ya está guardado. No hay que ir a Kinsta por separado,
 * y la llave de Kinsta no sale de aquí: si hace falta el dato fresco, se
 * dispara un proceso y el panel pregunta con su propia llave.
 *
 * ─── Sin lógica propia, a propósito ────────────────────────────────────────
 *
 * Cada ruta envuelve la misma función que alimenta la pantalla equivalente. No
 * hay una consulta escrita dos veces en este archivo. Es la única forma de que
 * el panel y la API no se puedan contradecir: si mañana cambia cómo se calcula
 * el nivel de un sitio, cambia en los dos lados porque es el mismo código.
 *
 * ─── Lo que esta API NO hace ───────────────────────────────────────────────
 *
 * Escribir. No asigna asesoras, no marca nada como atendido, no toca ningún
 * sitio. Lee, y dispara los tres procesos que el panel ya dispara con un botón.
 * El día que haga falta escribir desde fuera, será una decisión aparte.
 */

defined( 'ABSPATH' ) || exit;

class SWPK_Api {

	/** El mismo espacio que ya usan los agentes. */
	public const ESPACIO = 'swpk/v1';

	/** Tope de filas por página. Más que esto no es una consulta, es un volcado. */
	public const TOPE = 200;

	public static function init(): void {
		add_action( 'rest_api_init', [ __CLASS__, 'rutas' ] );
	}

	public static function rutas(): void {
		$leer  = [ __CLASS__, 'puede_leer' ];
		$obrar = [ __CLASS__, 'puede_obrar' ];

		$lectura = [
			'/estado'           => 'estado',
			'/sitios'           => 'sitios',
			'/vulnerabilidades' => 'vulnerabilidades',
			'/usuarios'         => 'usuarios',
			'/eventos'          => 'eventos',
			'/bitacora'         => 'bitacora',
		];

		foreach ( $lectura as $ruta => $metodo ) {
			register_rest_route(
				self::ESPACIO,
				$ruta,
				[
					'methods'             => 'GET',
					'callback'            => [ __CLASS__, $metodo ],
					'permission_callback' => $leer,
				]
			);
		}

		register_rest_route(
			self::ESPACIO,
			'/sitios/(?P<id>[A-Za-z0-9._-]+)',
			[
				'methods'             => 'GET',
				'callback'            => [ __CLASS__, 'sitio' ],
				'permission_callback' => $leer,
			]
		);

		// ── Procesos ────────────────────────────────────────────────────────
		//
		// Van por POST aunque no escriban nada afuera, porque cuestan: la
		// corrida completa son más de 200 peticiones al hosting. Un GET se
		// repite solo (un navegador, una caché, un enlace abierto dos veces) y
		// eso no debe poder disparar 200 peticiones sin querer.

		register_rest_route(
			self::ESPACIO,
			'/sitios/(?P<id>[A-Za-z0-9._-]+)/analizar',
			[
				'methods'             => 'POST',
				'callback'            => [ __CLASS__, 'analizar_sitio' ],
				'permission_callback' => $obrar,
			]
		);

		register_rest_route(
			self::ESPACIO,
			'/analizar',
			[
				'methods'             => 'POST',
				'callback'            => [ __CLASS__, 'analizar' ],
				'permission_callback' => $obrar,
			]
		);

		register_rest_route(
			self::ESPACIO,
			'/sondear',
			[
				'methods'             => 'POST',
				'callback'            => [ __CLASS__, 'sondear' ],
				'permission_callback' => $obrar,
			]
		);
	}

	// ── Permisos ─────────────────────────────────────────────────────────────

	/**
	 * Quién puede leer.
	 *
	 * Nada de `__return_true`: se comprueba el mismo permiso que comprueba la
	 * pantalla. Y una llave de ventas ve lo de ventas — los sitios que tiene
	 * asignados y nada más — porque `SWPK_Perfiles::sitios_visibles()` es la
	 * misma función en los dos lados.
	 */
	public static function puede_leer( WP_REST_Request $peticion ): bool|WP_Error {
		return self::comprobar( $peticion, SWPK_Perfiles::VER );
	}

	/** Quién puede disparar procesos. Leer no alcanza. */
	public static function puede_obrar( WP_REST_Request $peticion ): bool|WP_Error {
		return self::comprobar( $peticion, SWPK_Perfiles::ADMINISTRAR );
	}

	/**
	 * Toda llamada queda anotada, la que pasa y la que se rechaza.
	 *
	 * Las contraseñas de aplicación de WordPress se saltan la verificación en
	 * dos pasos, porque no pasan por el formulario de entrada. Es el precio de
	 * que un programa pueda consultar sin una persona delante. Lo que lo hace
	 * aceptable es que quede escrito quién llamó, a qué y desde dónde — igual
	 * que una entrada al panel — y que la llave se pueda revocar.
	 */
	/**
	 * Anota la llamada — salvo cuando WordPress está volviendo a preguntar solo
	 * para armar la cabecera `Allow`.
	 *
	 * `rest_send_allow_header()` corre enganchada a `rest_post_dispatch`, o sea
	 * DESPUÉS de haber contestado, y vuelve a llamar al `permission_callback` de
	 * cada manejador para decidir qué métodos anunciar. Resultado medido: una
	 * sola petición HTTP dejaba dos filas idénticas en el registro, con un
	 * segundo de diferencia.
	 *
	 * En un registro que existe para poder probar quién consultó qué, contar
	 * doble no es un detalle de limpieza: convierte "esta llave consultó 400
	 * veces" en una cifra que no se puede defender delante de nadie. Y no se
	 * veía en las pruebas de adentro, porque `rest_do_request` no pasa por
	 * `rest_post_dispatch` — solo salía por HTTP, que es por donde entran las
	 * llaves de verdad.
	 *
	 * Se descarta esa segunda pasada y no las repetidas en general: de las dos
	 * formas de equivocarse, anotar de más es ruido y anotar de menos es una
	 * mentira, así que lo que se calla es exactamente este caso conocido.
	 */
	private static function anotar_llamada( string $ruta, bool $permitido ): void {
		if ( doing_filter( 'rest_post_dispatch' ) ) {
			return;
		}

		SWPK_Accesos::anotar_api( $ruta, $permitido );
	}

	private static function comprobar( WP_REST_Request $peticion, string $permiso ): bool|WP_Error {
		$ruta = $peticion->get_method() . ' ' . $peticion->get_route();

		if ( ! is_user_logged_in() ) {
			self::anotar_llamada( $ruta, false );

			return new WP_Error(
				'swpk_sin_identidad',
				'Hay que identificarse. Usa una contraseña de aplicación de WordPress con autenticación básica.',
				[ 'status' => 401 ]
			);
		}

		if ( ! SWPK_Perfiles::puede( $permiso ) ) {
			self::anotar_llamada( $ruta, false );

			return new WP_Error(
				'swpk_sin_permiso',
				SWPK_Perfiles::ADMINISTRAR === $permiso
					? 'Esa llave puede consultar, pero no disparar procesos.'
					: 'Esa cuenta no tiene acceso al panel.',
				[ 'status' => 403 ]
			);
		}

		self::anotar_llamada( $ruta, true );

		return true;
	}

	// ── El sobre ─────────────────────────────────────────────────────────────

	/**
	 * Toda respuesta va dentro de un sobre que dice de cuándo es el dato.
	 *
	 * Por lo mismo que lo dicen las pantallas, los Excel y los PDF: quien lea
	 * esto dentro de seis meses —o un programa, que no sospecha— tiene que
	 * poder distinguir "los sitios están bien" de "esto es de hace dos
	 * semanas". Un JSON sin fecha es una afirmación sin fecha.
	 *
	 * @param array<string,mixed> $datos
	 */
	private static function responder( array $datos ): WP_REST_Response {
		return new WP_REST_Response(
			$datos + [
				'frescura'    => self::frescura(),
				'generado_en' => current_time( 'mysql' ),
				'zona'        => wp_timezone_string(),
			],
			200
		);
	}

	/** @return array<string,mixed> */
	private static function frescura(): array {
		$relojes = [];
		foreach ( SWPK_Frescura::relojes() as $r ) {
			$relojes[] = [
				'que'    => $r['que'],
				'cuando' => $r['cuando'],
				'al_dia' => (bool) $r['al_dia'],
				'si_no'  => $r['al_dia'] ? '' : $r['si_no'],
			];
		}

		return [
			// "Obtenido" es cuándo el panel le preguntó al hosting, que no es
			// lo mismo que cuándo el hosting miró el sitio: eso va por sitio,
			// en `revisado_en`.
			'obtenido_en'    => SWPK_Frescura::corrida(),
			'corrida_al_dia' => SWPK_Frescura::corrida_al_dia(),
			'hay_problema'   => SWPK_Frescura::hay_problema(),
			'relojes'        => $relojes,
			'proveedor'      => SWPK_Admin::proveedor(),
			'modo_demo'      => swpk_fuente()->es_demo(),
		];
	}

	// ── Lectura ──────────────────────────────────────────────────────────────

	public static function estado( WP_REST_Request $peticion ): WP_REST_Response {
		$estado = SWPK_Estado::calcular();

		return self::responder(
			[
				'veredicto' => SWPK_Estado::veredicto( $estado ),
				'conteo'    => [
					'sitios'     => (int) $estado['total'],
					'caidos'     => count( $estado['caidos'] ),
					'urgentes'   => count( $estado['urgentes'] ),
					'pendientes' => count( $estado['resto'] ),
					'al_dia'     => (int) $estado['al_dia'],
					'sin_datos'  => count( $estado['sin_datos'] ),
					'nuestros'   => (int) $estado['nuestros'],
					'sin_2fa'    => (int) $estado['sin_2fa'],
				],
				// Las listas, no solo los números: es lo que el jefe pidió de
				// la pantalla y vale igual aquí. "No en paquete."
				'caidos'     => $estado['caidos'],
				'urgentes'   => $estado['urgentes'],
				'pendientes' => $estado['resto'],
				'sin_datos'  => $estado['sin_datos'],
			]
		);
	}

	public static function sitios( WP_REST_Request $peticion ): WP_REST_Response {
		$filtrados = SWPK_Vista::filtrar(
			SWPK_Perfiles::sitios_visibles(),
			sanitize_key( (string) $peticion->get_param( 'grupo' ) ),
			sanitize_key( (string) $peticion->get_param( 'nivel' ) ),
			sanitize_text_field( (string) $peticion->get_param( 'q' ) )
		);

		[ $pagina, $por_pagina ] = self::paginacion( $peticion );
		$trozo                   = array_slice( $filtrados, ( $pagina - 1 ) * $por_pagina, $por_pagina );

		$filas = [];
		foreach ( $trozo as $s ) {
			$filas[] = self::sitio_en_corto( $s );
		}

		return self::responder(
			[
				'sitios'  => $filas,
				'cuantos' => count( $filtrados ),
				'pagina'  => $pagina,
				'paginas' => max( 1, (int) ceil( count( $filtrados ) / $por_pagina ) ),
			]
		);
	}

	public static function sitio( WP_REST_Request $peticion ): WP_REST_Response|WP_Error {
		$sitio_id = (string) $peticion->get_param( 'id' );

		$sitio = self::exigir_sitio( $sitio_id );
		if ( is_wp_error( $sitio ) ) {
			return $sitio;
		}

		return self::responder(
			[
				'sitio'      => self::sitio_en_corto( $sitio ) + [
					'entorno_id'       => (string) $sitio->entorno_id,
					'estado_hosting'   => (string) $sitio->estado,
					'contacto_cliente' => (string) $sitio->contacto_cliente,
					'plugin_2fa'       => (string) $sitio->plugin_2fa,
					'estado_2fa'       => (string) $sitio->estado_2fa,
					'cobertura_agente' => (bool) $sitio->cobertura_agente,
				],
				'hallazgos'  => SWPK_Riesgo::hallazgos( $sitio_id ),
				'usuarios'   => self::usuarios_de( $sitio_id ),
				'eventos'    => SWPK_Eventos::recientes( 100, $sitio_id ),
				'bitacora'   => SWPK_Bitacora::listar( 100, $sitio_id ),
				'disponible' => self::monitor_de( $sitio_id ),
			]
		);
	}

	public static function vulnerabilidades( WP_REST_Request $peticion ): WP_REST_Response {
		$nivel = sanitize_key( (string) $peticion->get_param( 'nivel' ) );
		$solo  = sanitize_text_field( (string) $peticion->get_param( 'sitio' ) );

		$hallazgos = [];

		foreach ( SWPK_Perfiles::sitios_visibles() as $s ) {
			$sitio_id = (string) $s->sitio_id;

			if ( '' !== $solo && $sitio_id !== $solo ) {
				continue;
			}

			foreach ( SWPK_Riesgo::hallazgos( $sitio_id ) as $h ) {
				if ( '' !== $nivel && $h['nivel'] !== $nivel ) {
					continue;
				}

				$hallazgos[] = [
					'sitio_id' => $sitio_id,
					'nombre'   => (string) $s->nombre_visible,
					'dominio'  => (string) $s->dominio,
					'grupo'    => (string) $s->tag,
					// La fecha de ESTE sitio, no la de la corrida: el hosting
					// no vuelve a mirar los 108 el mismo día, así que un
					// hallazgo puede venir de un dato de hace una semana y hay
					// que decirlo en la misma fila.
					'revisado_en' => (string) ( $s->inventario_en ?? '' ),
				] + $h;
			}
		}

		usort(
			$hallazgos,
			static fn( $a, $b ) => SWPK_Riesgo::peso( $b['nivel'] ) <=> SWPK_Riesgo::peso( $a['nivel'] )
		);

		[ $pagina, $por_pagina ] = self::paginacion( $peticion );

		return self::responder(
			[
				'hallazgos' => array_slice( $hallazgos, ( $pagina - 1 ) * $por_pagina, $por_pagina ),
				'cuantos'   => count( $hallazgos ),
				'pagina'    => $pagina,
				'paginas'   => max( 1, (int) ceil( count( $hallazgos ) / $por_pagina ) ),
			]
		);
	}

	public static function usuarios( WP_REST_Request $peticion ): WP_REST_Response|WP_Error {
		$solo = sanitize_text_field( (string) $peticion->get_param( 'sitio' ) );

		if ( '' !== $solo ) {
			$sitio = self::exigir_sitio( $solo );
			if ( is_wp_error( $sitio ) ) {
				return $sitio;
			}

			return self::responder(
				[
					'sitio_id' => $solo,
					'usuarios' => self::usuarios_de( $solo ),
					'nota'     => self::nota_de_usuarios(),
				]
			);
		}

		$por_sitio = [];
		foreach ( SWPK_Perfiles::sitios_visibles() as $s ) {
			$lista = self::usuarios_de( (string) $s->sitio_id );
			if ( ! $lista ) {
				continue;
			}

			$por_sitio[] = [
				'sitio_id' => (string) $s->sitio_id,
				'nombre'   => (string) $s->nombre_visible,
				'usuarios' => $lista,
			];
		}

		return self::responder( [ 'sitios' => $por_sitio, 'nota' => self::nota_de_usuarios() ] );
	}

	public static function eventos( WP_REST_Request $peticion ): WP_REST_Response {
		$desde = self::fecha( $peticion, 'desde' );
		$hasta = self::fecha( $peticion, 'hasta' );
		$sitio = sanitize_text_field( (string) $peticion->get_param( 'sitio' ) );
		$tipo  = sanitize_key( (string) $peticion->get_param( 'tipo' ) );

		[ , $limite ] = self::paginacion( $peticion );

		$eventos = ( '' !== $desde || '' !== $hasta )
			? SWPK_Eventos::entre(
				'' !== $desde ? $desde : '2020-01-01',
				'' !== $hasta ? $hasta : current_time( 'Y-m-d' ),
				$sitio,
				$tipo,
				$limite
			)
			: SWPK_Eventos::recientes( $limite, $sitio );

		return self::responder(
			[
				'eventos'   => self::solo_visibles( $eventos ),
				'etiquetas' => SWPK_Eventos::etiquetas(),
				// El historial empieza el día que el panel entró a producción.
				// El hosting no guarda nada consultable hacia atrás, así que lo
				// anterior no existe en ninguna parte — no es que falte aquí.
				'desde_que' => SWPK_Inventario::ultima_sincronizacion(),
			]
		);
	}

	public static function bitacora( WP_REST_Request $peticion ): WP_REST_Response {
		$desde = self::fecha( $peticion, 'desde' );
		$hasta = self::fecha( $peticion, 'hasta' );
		$tipo  = sanitize_key( (string) $peticion->get_param( 'tipo' ) );
		$sitio = sanitize_text_field( (string) $peticion->get_param( 'sitio' ) );

		[ , $limite ] = self::paginacion( $peticion );

		$avisos = ( '' !== $desde || '' !== $hasta )
			? SWPK_Bitacora::entre(
				'' !== $desde ? $desde : '2020-01-01',
				'' !== $hasta ? $hasta : current_time( 'Y-m-d' ),
				$tipo,
				$limite
			)
			: SWPK_Bitacora::listar( $limite, $sitio );

		// El cuerpo del correo pesa y casi nunca es lo que se consulta. Se dice
		// que está y se puede pedir aparte con ?cuerpo=1.
		$con_cuerpo = (bool) $peticion->get_param( 'cuerpo' );
		$filas      = [];

		foreach ( self::solo_visibles( $avisos ) as $a ) {
			$fila = (array) $a;
			if ( ! $con_cuerpo ) {
				unset( $fila['cuerpo'] );
			}
			$filas[] = $fila;
		}

		return self::responder( [ 'avisos' => $filas, 'tipos' => SWPK_Bitacora::tipos() ] );
	}

	// ── Procesos ─────────────────────────────────────────────────────────────

	public static function analizar_sitio( WP_REST_Request $peticion ): WP_REST_Response|WP_Error {
		$sitio_id = (string) $peticion->get_param( 'id' );

		$sitio = self::exigir_sitio( $sitio_id );
		if ( is_wp_error( $sitio ) ) {
			return $sitio;
		}

		$r = SWPK_Sincronizador::analizar_sitio( $sitio_id );
		if ( is_wp_error( $r ) ) {
			return $r;
		}

		// Lo mismo que dice el botón, y por la misma razón: una lista vacía no
		// es "no tiene nada instalado", es "el hosting no contestó". Un
		// programa que lea esto tiene que poder distinguirlo, así que no se
		// contesta `modulos: 0` y se calla.
		return self::responder(
			[
				'sitio_id' => $sitio_id,
				'modulos'  => (int) $r['modulos'],
				'eventos'  => (int) $r['eventos'],
				'sondeo'   => (string) $r['sondeo'],
				'sin_datos' => (bool) $r['sin_datos'],
				'aviso'    => $r['sin_datos']
					? sprintf(
						'%s no devolvió la lista de módulos de este sitio. Lo guardado sigue siendo el dato de la última corrida: no quiere decir que el sitio esté limpio.',
						SWPK_Admin::proveedor()
					)
					: '',
			]
		);
	}

	public static function analizar( WP_REST_Request $peticion ): WP_REST_Response {
		$resumen = SWPK_Sincronizador::correr();

		return self::responder( [ 'corrida' => $resumen ] );
	}

	public static function sondear( WP_REST_Request $peticion ): WP_REST_Response {
		return self::responder( [ 'sondeo' => SWPK_Monitor::sondear_todos() ] );
	}

	// ── Piezas compartidas ───────────────────────────────────────────────────

	/**
	 * Un sitio, reducido a lo que cualquiera que consulte necesita.
	 *
	 * @return array<string,mixed>
	 */
	private static function sitio_en_corto( object $s ): array {
		$sitio_id = (string) $s->sitio_id;
		$nivel    = SWPK_Riesgo::nivel_del_sitio( $sitio_id );
		$asesora  = (int) $s->asesora_id ? get_user_by( 'id', (int) $s->asesora_id ) : null;

		return [
			'sitio_id'          => $sitio_id,
			'nombre'            => (string) $s->nombre_visible,
			'dominio'           => (string) $s->dominio,
			'grupo'             => (string) $s->tag,
			'grupo_nombre'      => SWPK_Etiquetas::nombres()[ $s->tag ] ?? (string) $s->tag,
			'nivel'             => $nivel,
			'nivel_nombre'      => SWPK_Riesgo::nombres()[ $nivel ] ?? $nivel,
			'version_wp'        => (string) $s->version_wp,
			'version_php'       => (string) $s->version_php,
			'tiene_2fa'         => (bool) $s->tiene_2fa,
			'asesora'           => $asesora ? $asesora->display_name : '',
			'asesora_id'        => (int) $s->asesora_id,
			'revisado_en'       => (string) ( $s->inventario_en ?? '' ),
			'dias_sin_revisar'  => SWPK_Inventario::dias_sin_escanear( $s ),
			'dato_viejo'        => SWPK_Inventario::dato_viejo( $s ),
		];
	}

	/**
	 * El sitio, si existe y si quien pregunta puede verlo.
	 *
	 * Se contesta 404 en los dos casos a propósito: a una llave de ventas que
	 * pregunta por un sitio que no es suyo no se le confirma que ese sitio
	 * exista en la cartera.
	 *
	 * Devuelve `object` y no `object|WP_Error` porque un WP_Error también es un
	 * objeto y PHP rechaza la unión. Quien llame comprueba con is_wp_error().
	 */
	private static function exigir_sitio( string $sitio_id ): object {
		$sitio = SWPK_Inventario::obtener( $sitio_id );

		if ( ! $sitio || ! SWPK_Perfiles::puede_ver_sitio( $sitio_id ) ) {
			return new WP_Error( 'swpk_sin_sitio', 'Ese sitio no está.', [ 'status' => 404 ] );
		}

		return $sitio;
	}

	/** @return array<int,object> */
	private static function usuarios_de( string $sitio_id ): array {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT login, correo, nombre, roles, alta, dos_pasos, visto_en FROM '
					. SWPK_Tablas::tabla( 'usuarios' ) . ' WHERE sitio_id = %s ORDER BY roles ASC, login ASC',
				$sitio_id
			)
		) ?: [];
	}

	/**
	 * Lo que hay que decir junto a la lista de usuarios, siempre.
	 *
	 * Esta lista no viene del hosting: la manda el plugin instalado en cada
	 * sitio nuestro. De un sitio sin plugin la lista llega vacía, y vacía no
	 * quiere decir "no tiene administradores".
	 */
	private static function nota_de_usuarios(): string {
		$callados = count( SWPK_Receptor::callados() );

		return $callados
			? sprintf(
				'Esta lista la reporta el plugin de cada sitio. %d sitio(s) llevan más de 48 horas sin reportar, así que de esos lo que hay aquí puede estar viejo.',
				$callados
			)
			: 'Esta lista la reporta el plugin instalado en cada sitio, no el hosting. De un sitio sin plugin llega vacía, y vacía no quiere decir que no tenga administradores.';
	}

	/** @return array<string,mixed> */
	private static function monitor_de( string $sitio_id ): array {
		foreach ( SWPK_Monitor::estados() as $e ) {
			if ( (string) $e->sitio_id === $sitio_id ) {
				return (array) $e;
			}
		}

		return [ 'estado' => 'desconocido', 'motivo' => 'Todavía no se ha sondeado este sitio.' ];
	}

	/**
	 * Quita de una lista las filas de sitios que quien pregunta no puede ver.
	 *
	 * Los eventos y los avisos se guardan de toda la cartera; una llave de
	 * ventas no debe recibir los de los demás solo porque la consulta sea otra.
	 *
	 * @param array<int,object> $filas
	 * @return array<int,object>
	 */
	private static function solo_visibles( array $filas ): array {
		if ( SWPK_Perfiles::puede( SWPK_Perfiles::VER_TODO ) ) {
			return $filas;
		}

		$mios = [];
		foreach ( SWPK_Perfiles::sitios_visibles() as $s ) {
			$mios[ (string) $s->sitio_id ] = true;
		}

		return array_values(
			array_filter(
				$filas,
				// Una fila sin sitio (un fallo de la corrida, por ejemplo) es
				// de la cartera entera, no de un sitio: no se le entrega.
				static fn( $f ) => '' !== (string) $f->sitio_id && isset( $mios[ (string) $f->sitio_id ] )
			)
		);
	}

	/** @return array{0:int,1:int} */
	private static function paginacion( WP_REST_Request $peticion ): array {
		$pagina = max( 1, (int) $peticion->get_param( 'pagina' ) );
		$cuanto = (int) $peticion->get_param( 'por_pagina' );
		$cuanto = $cuanto > 0 ? min( $cuanto, self::TOPE ) : 50;

		return [ $pagina, $cuanto ];
	}

	/** Una fecha YYYY-MM-DD, o cadena vacía si lo que vino no lo es. */
	private static function fecha( WP_REST_Request $peticion, string $nombre ): string {
		$crudo = sanitize_text_field( (string) $peticion->get_param( $nombre ) );

		return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $crudo ) ? $crudo : '';
	}

	// ── Para la pantalla de "Conectarse" ────────────────────────────────────

	/**
	 * Las rutas, en palabras. Vive aquí y no en la vista para que la pantalla
	 * no pueda quedarse describiendo rutas que ya no existen.
	 *
	 * @return array<int,array<string,string>>
	 */
	public static function catalogo(): array {
		return [
			[
				'metodo' => 'GET',
				'ruta'   => '/estado',
				'que'    => 'El veredicto del panel y las listas de lo que hay que atender, lo caído y lo que no se sabe.',
				'quien'  => 'Consulta',
				'params' => '',
			],
			[
				'metodo' => 'GET',
				'ruta'   => '/sitios',
				'que'    => 'La cartera con su nivel de riesgo, su grupo y de cuándo es el dato de cada uno.',
				'quien'  => 'Consulta',
				'params' => 'grupo, nivel, q, pagina, por_pagina',
			],
			[
				'metodo' => 'GET',
				'ruta'   => '/sitios/{id}',
				'que'    => 'La ficha completa: hallazgos, quién tiene acceso, qué le pasó y qué se le avisó.',
				'quien'  => 'Consulta',
				'params' => '',
			],
			[
				'metodo' => 'GET',
				'ruta'   => '/vulnerabilidades',
				'que'    => 'Los hallazgos de toda la cartera, uno por fila, con el sitio al que pertenecen.',
				'quien'  => 'Consulta',
				'params' => 'nivel, sitio, pagina, por_pagina',
			],
			[
				'metodo' => 'GET',
				'ruta'   => '/usuarios',
				'que'    => 'Quién tiene control de cada sitio nuestro. Esto no lo da el hosting: lo reporta el plugin.',
				'quien'  => 'Consulta',
				'params' => 'sitio',
			],
			[
				'metodo' => 'GET',
				'ruta'   => '/eventos',
				'que'    => 'Qué cambió y cuándo: actualizaciones, vulnerabilidades, caídas, administradores nuevos.',
				'quien'  => 'Consulta',
				'params' => 'desde, hasta, sitio, tipo, por_pagina',
			],
			[
				'metodo' => 'GET',
				'ruta'   => '/bitacora',
				'que'    => 'Qué se le notificó a quién y cuándo, con el resultado del envío.',
				'quien'  => 'Consulta',
				'params' => 'desde, hasta, tipo, sitio, cuerpo',
			],
			[
				'metodo' => 'POST',
				'ruta'   => '/sitios/{id}/analizar',
				'que'    => 'Relee ese sitio del hosting ahora mismo, sin tocar a los demás. Tarda unos segundos.',
				'quien'  => 'Dirección',
				'params' => '',
			],
			[
				'metodo' => 'POST',
				'ruta'   => '/analizar',
				'que'    => 'La corrida completa, lo mismo que el botón "Correr ahora". Tarda varios minutos.',
				'quien'  => 'Dirección',
				'params' => '',
			],
			[
				'metodo' => 'POST',
				'ruta'   => '/sondear',
				'que'    => 'Revisa si todos los sitios responden, ahora.',
				'quien'  => 'Dirección',
				'params' => '',
			],
		];
	}

	/** La base de la que salen todas las rutas. */
	public static function base(): string {
		return rest_url( self::ESPACIO );
	}

	/**
	 * ¿Se puede llegar a esta API desde fuera?
	 *
	 * Mientras el panel corra en una máquina de la casa, la respuesta es no, y
	 * decirlo es parte de la propuesta: no hay nada que arreglar en el código,
	 * hace falta un servidor. Callarlo haría que alguien pruebe la llave desde
	 * su casa, no le funcione, y crea que la API está mal hecha.
	 */
	public static function alcanzable(): bool {
		$anfitrion = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );

		foreach ( [ 'localhost', '127.0.0.1', '::1' ] as $local ) {
			if ( $anfitrion === $local ) {
				return false;
			}
		}

		return ! ( str_ends_with( $anfitrion, '.local' ) || str_ends_with( $anfitrion, '.test' ) || str_ends_with( $anfitrion, '.localhost' ) );
	}
}
