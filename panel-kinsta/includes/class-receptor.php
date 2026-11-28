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

		// ── El resumen del registro de actividad ────────────────────────────
		//
		// Son las cuentas del día, no las filas: dos o tres kilobytes. Se
		// guarda en una opción por sitio y no en una tabla porque es UNA fila
		// que se reescribe cada día, que es justo para lo que sirve una
		// opción. El detalle no está aquí: vive en el sitio y se pide con el
		// verbo `traer_bitacora` cuando alguien lo necesita.
		if ( isset( $datos['bitacora'] ) && is_array( $datos['bitacora'] ) ) {
			update_option(
				'swpk_bitacora_' . md5( (string) $agente->sitio_url ),
				[
					'recibido' => current_time( 'mysql' ),
					'resumen'  => $datos['bitacora'],
				],
				false
			);
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

		// Este sitio ya tiene quien lo mire por dentro.
		//
		// La columna `cobertura_agente` existía desde el principio y NADIE la
		// escribía: se leía en tres sitios —aquí al medir normas, en la sonda y
		// en la API— y nacía en cero, así que el panel daba por hecho que
		// ningún sitio tenía agente aunque estuvieran todos reportando.
		//
		// Se vio el 19 de noviembre de 2026, al conectar los 95 de golpe: 95
		// agentes registrados, 95 reportes recibidos, 341 administradores
		// guardados… y «sitios con cobertura de agente: 0 de 108».
		//
		// Un dato que solo se lee y nunca se escribe no da error: da una
		// respuesta tranquila y equivocada, que es peor.
		if ( '' !== $sitio_id ) {
			$wpdb->update(
				SWPK_Tablas::tabla( 'sitios' ),
				[ 'cobertura_agente' => 1 ],
				[ 'sitio_id' => $sitio_id ]
			);
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

	/**
	 * Por qué este sitio dejó de reportar, hasta donde el panel puede saberlo.
	 *
	 * ─── Para qué existe ────────────────────────────────────────────────────
	 *
	 * El aviso de «un sitio dejó de reportar» decía las tres causas posibles y
	 * dejaba al lector elegir: «alguien desactivó el plugin, el sitio está en
	 * mantenimiento, o el reloj del sitio dejó de correr». El 27 de noviembre
	 * de 2026 salieron dos de esos correos el mismo día, uno por cada causa
	 * distinta, y los dos decían exactamente lo mismo. Quien los recibió tuvo
	 * que ir a averiguar cuál era cuál —y uno de los dos resultó ser un sitio
	 * que se había ido del hosting hacía una semana.
	 *
	 * El panel ya tenía lo que hacía falta para distinguirlos; solo que no lo
	 * miraba al escribir el correo. Esto lo mira.
	 *
	 * ─── Lo que se cruza, y por qué esos tres ───────────────────────────────
	 *
	 * Nada de esto pide una petición nueva. Son tres datos que ya están
	 * guardados y que, juntos, separan los tres casos:
	 *
	 *   · `inventario_en`  cuándo leyó el HOSTING los plugins de ese sitio.
	 *   · `version_wp`     qué versión de WordPress ve el hosting ahí.
	 *   · el estado del monitor, que dice si desde aquí se le puede ver.
	 *
	 * El primero es el que más dice. Si el hosting tampoco está leyendo ese
	 * entorno, el silencio del agente no es cosa del agente: es que ahí ya no
	 * hay nada que leer. Un entorno suspendido o un dominio que se movió de
	 * proveedor se ven exactamente así.
	 *
	 * ─── Lo que NO se puede saber, y por eso no se dice ─────────────────────
	 *
	 * La API del hosting no tiene campo de «entorno suspendido». Se comprobó
	 * el 27 de noviembre de 2026 contra `/sites`, `/sites/{id}` y
	 * `/sites/{id}/environments`: el sitio suspendido venía con `status: live`
	 * y `is_blocked: false`, igual que los 108 sanos. Ese cartel existe en el
	 * panel del hosting y en ningún sitio más.
	 *
	 * Así que aquí no se afirma «está suspendido». Se dice lo que sí se midió
	 * —que el hosting lleva días sin leerlo— y se manda a mirarlo allá. Es la
	 * regla 3: decir lo que se sabe, no lo que se deduce con ganas.
	 */
	public static function motivo_del_silencio( object $agente ): string {
		global $wpdb;

		$sitio_id = (string) ( $agente->sitio_id ?? '' );

		if ( '' === $sitio_id ) {
			return 'Este agente no está emparejado con ningún sitio del hosting, así que de él '
				. 'no se puede decir más que esto: dejó de llamar.';
		}

		$sitio = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT inventario_en, version_wp, suspendido FROM ' . SWPK_Tablas::tabla( 'sitios' ) . ' WHERE sitio_id = %s',
				$sitio_id
			)
		);

		// Si ya está marcado como suspendido, eso es la respuesta y no hace
		// falta deducir nada: la corrida diaria lo decidió con las tres
		// señales en la mano. Ver SWPK_Inventario::revisar_suspendidos().
		if ( ! empty( $sitio->suspendido ) ) {
			return 'Este sitio está marcado como suspendido: en el hosting, su entorno vivo ya '
				. 'no tiene WordPress. No es que el sitio falle — es que el WordPress que '
				. 'vigilábamos ahí ya no está.';
		}

		$visto = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT estado FROM ' . SWPK_Tablas::tabla( 'monitor' ) . ' WHERE sitio_id = %s',
				$sitio_id
			)
		);

		// ── 1. El hosting tampoco lo está leyendo ───────────────────────────
		//
		// Lo primero que se mira, porque es lo único que apunta a que el
		// problema no está en el agente sino en que ese entorno ya no es
		// nuestro. El corte son cinco días: el hosting relee la cartera a
		// diario, así que tres días de retraso entran dentro de lo normal y
		// cinco ya no.
		$leido = (string) ( $sitio->inventario_en ?? '' );
		$edad  = SWPK_Frescura::edad( $leido );

		// «Nunca leído» no es lo mismo que «dejó de leerse», y confundirlos
		// convierte un sitio recién dado de alta —o uno cuya lectura falló esa
		// vuelta— en un sitio que se fue del hosting. Se dice lo que hay: nada.
		if ( null === $edad ) {
			return 'El panel no tiene registro de que el hosting haya leído este sitio, así que '
				. 'desde aquí no se puede decir por qué se calló.';
		}

		if ( $edad > self::DIAS_SIN_LEER * DAY_IN_SECONDS ) {
			return sprintf(
				'El hosting tampoco está leyendo este sitio desde el %s%s. Un entorno suspendido '
				. 'o un dominio que se movió a otro proveedor se ven así. Conviene abrirlo en el '
				. 'panel del hosting antes que en el sitio.',
				SWPK_Frescura::fmt( 'j \d\e F', $leido ),
				'' === (string) ( $sitio->version_wp ?? '' ) ? ', y tampoco reporta qué versión de WordPress tiene' : ''
			);
		}

		// ── 2. Desde aquí no se le puede ver ────────────────────────────────
		if ( 'ciego' === $visto ) {
			return 'El cortafuegos del hosting bloquea al monitor del panel, así que desde aquí '
				. 'no se puede comprobar nada de este sitio. El silencio del agente puede ser '
				. 'cualquier cosa; esto no lo aclara.';
		}

		if ( 'abajo' === $visto ) {
			return 'Además, el sitio no está respondiendo. Mientras esté caído, el agente no '
				. 'tiene cómo reportar.';
		}

		// ── 3. El sitio está vivo y el hosting lo lee: es el reloj ──────────
		return 'El hosting lo sigue leyendo y el sitio responde, así que el sitio está bien. Lo '
			. 'que no está corriendo es su reloj: el agente solo reporta cuando una visita llega '
			. 'hasta PHP, y en un sitio servido entero desde caché eso puede no pasar en días.';
	}

	/**
	 * Cuántos días sin que el hosting lea un sitio hacen sospechar.
	 *
	 * El hosting relee la cartera a diario, pero no todos los sitios caen en
	 * la misma vuelta: medido el 27 de noviembre de 2026, de 107 sitios al día
	 * 69 tenían la lectura de ayer, 9 de anteayer y 23 de hace tres días. Con
	 * el corte en tres días saldrían veintitrés avisos falsos.
	 */
	private const DIAS_SIN_LEER = 5;

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
