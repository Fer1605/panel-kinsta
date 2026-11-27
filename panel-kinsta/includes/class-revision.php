<?php
/**
 * Lo que la plataforma sabe de cada sitio y el panel no estaba leyendo.
 *
 * ─── De dónde sale esto ─────────────────────────────────────────────────────
 *
 * Del audio del 18/11/2026. El jefe lo pidió casi endpoint por endpoint, sin
 * saber que lo eran:
 *
 *   «habría que ver si Kinsta nos da de alguna manera si algún sitio sube su
 *    pico. Por ejemplo, hoy estoy recibiendo 100 visitas y de repente pum, pasa
 *    a 500, porque Kinsta registra todo eso en su panel»            [06:40]
 *
 *   «si Kinsta te permite bloquear bots —normas de seguridad: bloquear bots
 *    chinos, rusos—. Kinsta tiene un panel de bots, hay que ver si todas esas
 *    configuraciones están bien, si se puede leer dentro de la API»  [07:03]
 *
 *   «¿y cómo lo analizamos? Tenemos la data, tenemos los logs. El tráfico
 *    normal, ¿cuál es? El tráfico normal de este proyecto, porque puede variar
 *    por proyecto, es de uno a cien. Ahorita van 500, pues eso ya no es
 *    normal»                                                         [08:11]
 *
 * Esa última frase es la especificación de `trafico_normal`, y está resuelta
 * tal cual: la línea base es la mediana de los treinta días anteriores DE ESE
 * SITIO, no un número fijo para todos.
 *
 * ─── Lo que NO se lee, y por qué ────────────────────────────────────────────
 *
 * Las versiones de WordPress y PHP, los plugins vulnerables, los que tienen
 * actualización y los instalados pero apagados ya están en el inventario que
 * la corrida diaria guarda. Volver a pedirlos sería gastar 108 peticiones para
 * traer lo que ya está en la base. Esas normas las miden los comprobadores
 * leyendo `swpk_fotos` y `swpk_sitios`.
 *
 * ─── Por qué una clase aparte y no la fuente ────────────────────────────────
 *
 * SWPK_Fuente es la abstracción que permite que mañana haya Pantheon sin tocar
 * nada de lo que está encima. Esto de aquí es puro Kinsta —protección de bots,
 * respaldos de la plataforma, su analítica— y meterlo en la interfaz obligaría
 * a Pantheon a implementar trece métodos que no tiene. Así que vive aparte y se
 * apaga sola cuando la fuente no es Kinsta.
 *
 * ─── Solo lectura ───────────────────────────────────────────────────────────
 *
 * Todas las peticiones salen por SWPK_Http, que únicamente implementa get().
 * Kinsta tiene endpoints para CAMBIAR el nivel de bots, las IP permitidas y
 * las reglas de redirección; ninguno se toca. El panel mide y avisa.
 */

defined( 'ABSPATH' ) || exit;

class SWPK_Revision {

	/**
	 * Corre a las 4:30, antes que todo lo demás.
	 *
	 * Son trece peticiones por sitio y el límite de Kinsta es 120 por minuto
	 * —el cliente se guarda en 100—. Medido contra la cuenta real: 13,9
	 * segundos por sitio, o sea unos 25 minutos para los 108.
	 * Por eso tiene su propio horario y no va dentro de la corrida diaria: dos
	 * tareas largas pegadas es una tarea que el límite de tiempo de PHP mata a
	 * la mitad, y se pierden las dos.
	 */
	public const HOOK = 'swpk_revision_diaria';

	private const CACHE = 'swpk_revision_';

	/** Por qué sitio iba la última vuelta. Ver correr(). */
	private const CURSOR = 'swpk_revision_cursor';

	/** El reloj de respaldo y la cola, igual que en la sonda. Ver SWPK_Relevo. */
	public const HOOK_TANDA = 'swpk_revision_tanda';

	private const COLA      = 'swpk_revision_cola';
	private const POR_TANDA = 4;
	private const SEGUNDOS  = 25;
	private const VIDA  = 25 * HOUR_IN_SECONDS;

	/**
	 * Cuántas veces la mediana tiene que superarse para llamarlo pico.
	 *
	 * El jefe dio el ejemplo de 100 a 500, que son cinco veces. Tres es más
	 * sensible sin ser ruidoso: un sitio informativo que dobla su tráfico un
	 * día es normal, uno que lo triplica merece una mirada. Es criterio
	 * nuestro, no de ningún estándar, y por eso está aquí arriba y con nombre.
	 */
	private const VECES_PARA_PICO = 3;

	/** Cuántos 5xx por hora, de media, ya no son «algo pasó». */
	private const ERRORES_POR_HORA = 20;

	/** Cuántas peticiones a wp-login en el log ya parecen una oleada. */
	private const LOGINS_PARA_OLEADA = 50;

	public static function init(): void {
		add_action( self::HOOK, [ __CLASS__, 'correr' ] );
		add_action( self::HOOK_TANDA, [ __CLASS__, 'tanda' ] );

		add_action( 'wp_ajax_nopriv_swpk_revision_relevo', [ __CLASS__, 'relevo' ] );
		add_action( 'wp_ajax_swpk_revision_relevo', [ __CLASS__, 'relevo' ] );
		self::programar();
	}

	public static function programar(): void {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( SWPK_Frescura::cuando( 'tomorrow 04:30' ), 'daily', self::HOOK );
		}
	}

	public static function desprogramar(): void {
		$ts2 = wp_next_scheduled( self::HOOK_TANDA );
		if ( $ts2 ) {
			wp_unschedule_event( $ts2, self::HOOK_TANDA );
		}

		$ts = wp_next_scheduled( self::HOOK );
		if ( $ts ) {
			wp_unschedule_event( $ts, self::HOOK );
		}
	}

	/**
	 * ¿Hay con qué?
	 *
	 * Con los datos de prueba esto no tiene sentido, y con Pantheon tampoco:
	 * estos doce endpoints son de Kinsta. La comparación va sin distinguir
	 * mayúsculas porque `proveedor()` devuelve el nombre para enseñarlo en
	 * pantalla —«Kinsta»— y no una clave.
	 */
	public static function disponible(): bool {
		$f = swpk_fuente();

		return ! $f->es_demo() && 'kinsta' === strtolower( $f->proveedor() );
	}

	/**
	 * Lo que la revisión averiguó de un sitio.
	 *
	 * @return array<string,mixed>
	 */
	public static function de( string $sitio_id ): array {
		if ( '' === $sitio_id ) {
			return [];
		}

		$v = get_transient( self::CACHE . $sitio_id );

		return is_array( $v ) ? $v : [];
	}

	public static function olvidar( string $sitio_id = '' ): void {
		if ( '' !== $sitio_id ) {
			delete_transient( self::CACHE . $sitio_id );

			return;
		}

		global $wpdb;
		$wpdb->query(
			"DELETE FROM {$wpdb->options}
			  WHERE option_name LIKE '_transient_swpk_revision_%'
			     OR option_name LIKE '_transient_timeout_swpk_revision_%'"
		);
	}

	/**
	 * Revisar toda la cartera y medir lo que de ahí sale.
	 *
	 * @return array<string,mixed>
	 */
	/**
	 * Arranca la revisión del día. La llama el reloj.
	 *
	 * ─── Por qué esto cambió otra vez ───────────────────────────────────────
	 *
	 * El 25 de noviembre esto se arregló a medias: se le puso un cursor para
	 * que cada día siguiera donde se había quedado, dando la vuelta a la
	 * cartera en varios días. Mejor que nada, pero el 27 de noviembre la
	 * opción seguía diciendo lo mismo todos los días:
	 *
	 *     {"estado":"corriendo","arranco_en":23,"total":109}
	 *
	 * «Corriendo» desde las 4:35 de la mañana, con 23 sitios hechos de 109.
	 * O sea: el cursor avanzaba, sí, pero la revisión no terminaba NUNCA, y
	 * de las diez normas que salen de aquí había 84 sitios sin medir. Dar la
	 * vuelta en cinco días significa que el dato de un sitio tiene cinco días
	 * cuando le toca.
	 *
	 * Son trece llamadas a la API de Kinsta por sitio, mil cuatrocientas en
	 * total, y el cliente frena solo para no pasarse del límite de Kinsta:
	 * catorce minutos largos. Eso no cabe en una petición, y pedir tiempo
	 * otra vez no basta cuando quien corta es el servidor web.
	 *
	 * Así que va por tandas y se encadena sola, igual que la sonda: cuatro
	 * sitios o veinticinco segundos, lo que llegue antes. Ver SWPK_Relevo
	 * para la parte que costó encontrar.
	 *
	 * @return array<string,mixed>
	 */
	public static function correr(): array {
		if ( ! self::disponible() ) {
			update_option(
				'swpk_ultima_revision',
				[ 'cuando' => current_time( 'mysql' ), 'estado' => 'saltada', 'saltado' => 'La fuente no es la API de Kinsta.' ],
				false
			);

			return [ 'saltado' => 'La fuente no es la API de Kinsta.' ];
		}

		// Si quedó una corrida a medias, se sigue esa en vez de empezar de
		// cero: volver a empezar es lo que hacía que la cola de la cartera no
		// se midiera nunca.
		$cola = get_option( self::COLA, null );

		if ( is_array( $cola ) && ! empty( $cola['pend'] ) ) {
			return [ 'retomada' => count( $cola['pend'] ) ] + self::avanzar();
		}

		$pend = [];

		foreach ( SWPK_Inventario::todos() as $s ) {
			if ( '' !== trim( (string) $s->entorno_id ) ) {
				$pend[ (string) $s->sitio_id ] = (string) $s->entorno_id;
			}
		}

		$cola = [
			'inicio'  => current_time( 'mysql' ),
			'pend'    => $pend,
			'total'   => count( $pend ),
			'resumen' => [ 'revisados' => 0, 'fallos' => 0, 'medidos' => 0 ],
			'llave'   => wp_generate_password( 32, false ),
			'latido'  => time(),
		];

		update_option( self::COLA, $cola, false );
		update_option(
			'swpk_ultima_revision',
			[ 'cuando' => current_time( 'mysql' ), 'estado' => 'corriendo', 'total' => count( $pend ) ],
			false
		);

		return [ 'arrancada' => true, 'total' => $cola['total'] ] + self::avanzar();
	}

	/** Lo que llama el reloj de respaldo. */
	public static function tanda(): void {
		self::avanzar();
	}

	/**
	 * Revisa unos cuantos sitios y deja pedida la siguiente tanda.
	 *
	 * @param int $segundos 0 = hasta terminar (línea de comandos y pruebas).
	 * @return array<string,mixed>
	 */
	public static function avanzar( int $segundos = self::SEGUNDOS ): array {
		$cola = get_option( self::COLA, null );

		if ( ! is_array( $cola ) ) {
			return [];
		}

		if ( empty( $cola['pend'] ) ) {
			return self::cerrar( $cola );
		}

		if ( ! SWPK_Relevo::tomar( 'revision' ) ) {
			return [ 'ocupado' => true ];
		}

		$hasta = $segundos > 0 ? microtime( true ) + $segundos : INF;

		do {
			$tanda = array_slice( (array) $cola['pend'], 0, self::POR_TANDA, true );

			if ( ! $tanda ) {
				break;
			}

			foreach ( array_keys( $tanda ) as $id ) {
				unset( $cola['pend'][ $id ] );
			}

			$cola['latido'] = time();
			update_option( self::COLA, $cola, false );

			foreach ( $tanda as $sitio_id => $entorno_id ) {
				// Trece llamadas a una API de fuera pueden tardar diez
				// segundos largos: cada sitio vuelve a pedir tiempo.
				if ( function_exists( 'set_time_limit' ) ) {
					@set_time_limit( 60 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
				}

				$r = self::revisar_uno( (string) $sitio_id, (string) $entorno_id );

				++$cola['resumen']['revisados'];
				$cola['resumen']['fallos'] += count( (array) ( $r['fallos'] ?? [] ) );

				SWPK_Normas::medir( (string) $sitio_id, [], SWPK_Normas::COMPROBADORES_DE_REVISION );
				++$cola['resumen']['medidos'];
			}

			$cola['latido'] = time();
			update_option( self::COLA, $cola, false );
		} while ( ! empty( $cola['pend'] ) && microtime( true ) < $hasta );

		SWPK_Relevo::soltar( 'revision' );

		if ( ! empty( $cola['pend'] ) ) {
			SWPK_Relevo::pedir( 'swpk_revision_relevo', (string) ( $cola['llave'] ?? '' ), self::HOOK_TANDA );

			return [ 'pendientes' => count( $cola['pend'] ) ] + (array) $cola['resumen'];
		}

		return self::cerrar( $cola );
	}

	/** Si hay una revisión a medias, cuántos sitios le faltan. */
	public static function pendientes(): int {
		$cola = get_option( self::COLA, null );

		return is_array( $cola ) ? count( (array) ( $cola['pend'] ?? [] ) ) : 0;
	}

	/** Cierra la revisión: las normas de inventario, el resumen y a dormir. */
	private static function cerrar( array $cola ): array {
		// Estas salen del inventario y no de la API, así que se miden aunque
		// la revisión no haya podido leer nada.
		foreach ( SWPK_Inventario::todos() as $sitio ) {
			SWPK_Normas::medir( (string) $sitio->sitio_id, [], SWPK_Normas::COMPROBADORES_DE_INVENTARIO );
		}

		$resumen = (array) ( $cola['resumen'] ?? [] );

		update_option(
			'swpk_ultima_revision',
			[
				'cuando' => current_time( 'mysql' ),
				'empezo' => (string) ( $cola['inicio'] ?? '' ),
				'estado' => 'terminada',
				'total'  => (int) ( $cola['total'] ?? 0 ),
			] + $resumen,
			false
		);

		delete_option( self::COLA );
		delete_option( self::CURSOR );

		SWPK_Estado::olvidar();

		return $resumen;
	}

	/** El relevo entre tandas. Lo autoriza la llave, no una sesión. */
	public static function relevo(): void {
		$cola = get_option( self::COLA, null );

		if ( ! is_array( $cola ) || '' === (string) ( $cola['llave'] ?? '' ) ) {
			wp_die( '', '', [ 'response' => 204 ] );
		}

		if ( ! hash_equals( (string) $cola['llave'], SWPK_Relevo::llave_pedida() ) ) {
			wp_die( '', '', [ 'response' => 403 ] );
		}

		ignore_user_abort( true );

		self::avanzar();

		wp_die( '', '', [ 'response' => 204 ] );
	}

	/**
	 * Las trece lecturas de un sitio.
	 *
	 * Un endpoint que falle no tumba a los demás: se anota en `fallos` y se
	 * sigue. Un dato incompleto es peor que uno completo, pero mucho mejor que
	 * ninguno — y sobre todo, se ve cuál falta.
	 *
	 * @return array<string,mixed>
	 */
	public static function revisar_uno( string $sitio_id, string $entorno_id ): array {
		if ( ! self::disponible() || '' === $entorno_id ) {
			return [];
		}

		$ajustes = SWPK_Ajustes::todos();
		$http    = new SWPK_Http( (string) $ajustes['api_key'] );
		$empresa = (string) $ajustes['company_id'];
		$base    = '/sites/environments/' . rawurlencode( $entorno_id );

		$fallos = [];

		$pedir = static function ( string $ruta, array $query = [] ) use ( $http, &$fallos ) {
			$r = $http->get( $ruta, $query );

			if ( is_wp_error( $r ) ) {
				$fallos[] = $ruta . ': ' . $r->get_error_message();

				return null;
			}

			return $r;
		};

		$https     = $pedir( $base . '/force-https-status' );
		$nivel     = $pedir( $base . '/bot-protection/level' );
		$ia        = $pedir( $base . '/bot-protection/ai-crawlers' );
		$excep     = $pedir( $base . '/bot-protection/exception-rules' );
		$respaldos = $pedir( $base . '/backups' );
		$ssh       = $pedir( $base . '/ssh/get-status' );
		$ips       = $pedir( $base . '/ssh/get-allowed-ips' );
		$sftp      = $pedir( $base . '/additional-sftp-accounts' );
		$redir     = $pedir( $base . '/redirect-rules' );
		$negadas   = $pedir( '/sites/tools/denied-ips', [ 'environment_id' => $entorno_id ] );
		$visitas   = $pedir( $base . '/analytics/visits', [ 'company_id' => $empresa, 'time_span' => '30_days' ] );
		$codigos   = $pedir( $base . '/analytics/response-codes', [ 'company_id' => $empresa, 'time_span' => '24_hours' ] );
		$registro  = $pedir( $base . '/logs', [ 'file_name' => 'access', 'lines' => 2000 ] );

		$uno = [
			'cuando'        => current_time( 'mysql' ),
			'fallos'        => $fallos,
			'https'         => null === $https ? null : (string) ( $https['force_https_status'] ?? '' ),
			'bots_nivel'    => null === $nivel ? null : (string) ( $nivel['bot_protection']['level'] ?? '' ),
			'bots_ia'       => null === $ia ? null : (string) ( $ia['bot_protection']['ai_crawlers_blocking'] ?? '' ),
			'bots_excep'    => null === $excep ? null : count( (array) ( $excep['bot_protection']['exception_rules'] ?? [] ) ),
			'ssh_abierto'   => null === $ssh ? null : (bool) ( $ssh['environment']['active_container']['is_ssh_enabled'] ?? false ),
			'ips_sftp'      => null === $ips ? null : count( (array) ( $ips['environment']['active_container']['sftp_ip_allowlist'] ?? [] ) ),
			'sftp_extra'    => null === $sftp ? null : count( (array) ( $sftp['environment']['additional_sftp_accounts'] ?? [] ) ),
			'redirecciones' => null === $redir ? null : (int) ( $redir['environment']['container_info']['redirect_rules']['redirect_rules_count'] ?? 0 ),
			'ips_negadas'   => null === $negadas ? null : count( (array) ( $negadas['environment']['ip_list'] ?? [] ) ),
			'respaldo'      => self::leer_respaldos( $respaldos ),
			'trafico'       => self::leer_visitas( $visitas ),
			'errores'       => self::leer_codigos( $codigos ),
			'logins'        => self::leer_logins( $registro ),
		];

		set_transient( self::CACHE . $sitio_id, $uno, self::VIDA );

		return $uno;
	}

	/**
	 * El respaldo más reciente, en horas.
	 *
	 * @return array<string,mixed>
	 */
	private static function leer_respaldos( ?array $r ): array {
		if ( null === $r ) {
			return [ 'horas' => null, 'cuantos' => null ];
		}

		$lista = (array) ( $r['environment']['backups'] ?? [] );

		if ( ! $lista ) {
			return [ 'horas' => null, 'cuantos' => 0 ];
		}

		$ultimo = 0;

		foreach ( $lista as $b ) {
			// Kinsta los da en milisegundos.
			$ultimo = max( $ultimo, (int) round( ( (int) ( $b['created_at'] ?? 0 ) ) / 1000 ) );
		}

		return [
			'horas'   => $ultimo ? (int) floor( ( time() - $ultimo ) / HOUR_IN_SECONDS ) : null,
			'cuantos' => count( $lista ),
		];
	}

	/**
	 * El tráfico de hoy contra la mediana de los días anteriores.
	 *
	 * Esto es, literalmente, lo que pidió el jefe: «el tráfico normal de este
	 * proyecto, PORQUE PUEDE VARIAR POR PROYECTO». La línea base no es un
	 * número que alguien escriba: es lo que ese sitio hace normalmente.
	 *
	 * Se usa la mediana y no el promedio porque un solo día de pico arrastra el
	 * promedio hacia arriba y entonces el pico siguiente ya no se nota. La
	 * mediana no se mueve por un día raro, que es justo lo que se quiere.
	 *
	 * Y el último día se deja fuera del cálculo de la base: si entrara, el pico
	 * estaría compitiendo contra sí mismo.
	 *
	 * @return array<string,mixed>
	 */
	private static function leer_visitas( ?array $r ): array {
		if ( null === $r ) {
			return [ 'hoy' => null, 'base' => null, 'veces' => null ];
		}

		$serie = (array) ( $r['analytics']['analytics_response']['data'][0]['dataset'] ?? [] );
		$dias  = [];

		foreach ( $serie as $punto ) {
			$dias[] = (int) ( $punto['value'] ?? 0 );
		}

		if ( count( $dias ) < 5 ) {
			return [ 'hoy' => null, 'base' => null, 'veces' => null, 'pocos_datos' => true ];
		}

		$hoy      = (int) array_pop( $dias );
		$anteriores = $dias;
		sort( $anteriores );

		$n       = count( $anteriores );
		$mediana = 0 === $n % 2
			? (int) round( ( $anteriores[ (int) ( $n / 2 ) - 1 ] + $anteriores[ (int) ( $n / 2 ) ] ) / 2 )
			: (int) $anteriores[ (int) floor( $n / 2 ) ];

		return [
			'hoy'   => $hoy,
			'base'  => $mediana,
			'veces' => $mediana > 0 ? round( $hoy / $mediana, 1 ) : null,
			'tope'  => self::VECES_PARA_PICO,
		];
	}

	/**
	 * Cuántos errores de servidor por hora.
	 *
	 * @return array<string,mixed>
	 */
	private static function leer_codigos( ?array $r ): array {
		if ( null === $r ) {
			return [ 'por_hora' => null ];
		}

		$datos  = (array) ( $r['analytics']['analytics_response']['data'] ?? [] );
		$cincos = 0;
		$horas  = 0;

		foreach ( $datos as $fila ) {
			$codigo = (string) ( $fila['response_code'] ?? '' );
			$puntos = (array) ( $fila['data'] ?? [] );

			$horas = max( $horas, count( $puntos ) );

			if ( '5' !== substr( $codigo, 0, 1 ) ) {
				continue;
			}

			foreach ( $puntos as $punto ) {
				$cincos += (int) ( $punto['value'] ?? 0 );
			}
		}

		return [
			'por_hora' => $horas > 0 ? (int) round( $cincos / $horas ) : 0,
			'total'    => $cincos,
			'tope'     => self::ERRORES_POR_HORA,
		];
	}

	/**
	 * Cuántas peticiones a wp-login.php hay en el trozo de registro leído.
	 *
	 * Es una señal, no una cuenta exacta: se leen las últimas 2000 líneas, el
	 * borde absorbe parte del ataque antes de que el origen lo vea, y la caché
	 * se come otro tanto. Así que un número bajo NO prueba que no haya ataque;
	 * un número alto sí prueba que algo está pasando. El detalle de la norma lo
	 * dice así, porque si no alguien va a leer «0» como «estamos tranquilos».
	 *
	 * @return array<string,mixed>
	 */
	private static function leer_logins( ?array $r ): array {
		if ( null === $r ) {
			return [ 'cuantos' => null ];
		}

		$texto = (string) ( $r['environment']['container_info']['logs'] ?? '' );

		if ( '' === $texto ) {
			return [ 'cuantos' => null ];
		}

		return [
			'cuantos' => substr_count( $texto, 'wp-login.php' ) + substr_count( $texto, '/xmlrpc.php' ),
			'lineas'  => substr_count( $texto, "\n" ) + 1,
			'tope'    => self::LOGINS_PARA_OLEADA,
		];
	}
}
