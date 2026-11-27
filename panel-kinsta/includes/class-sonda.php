<?php
/**
 * Lo que se puede saber de un sitio sin entrar en él.
 *
 * ─── Por qué hace falta esto ────────────────────────────────────────────────
 *
 * Hasta el 18/11/2026 todas las normas las medía el agente, desde dentro del
 * sitio, y el panel solo interpretaba su reporte (ver SWPK_Normas::medir). Eso
 * está bien para lo que únicamente se ve desde dentro —si el editor de código
 * está abierto, qué usuarios hay— pero es la fuente equivocada para todo lo que
 * la pregunta es, literalmente, «qué ve un desconocido desde internet».
 *
 * Y en un caso daba un resultado falso: el agente leía el filtro `xmlrpc_enabled`
 * de WordPress, que dice si WordPress *serviría* XML-RPC. No dice si la petición
 * le llega. En Kinsta no le llega —Nginx devuelve 403 antes de que PHP arranque—
 * así que el panel podía marcar «XML-RPC abierto» en un sitio donde nadie puede
 * entrar por ahí.
 *
 * ─── Lo que esto NO es ──────────────────────────────────────────────────────
 *
 * No es un escáner de vulnerabilidades ni una prueba de penetración. Son
 * peticiones sin credenciales a un sitio público: GET en su mayoría, un POST a
 * xmlrpc.php pidiendo la lista de métodos y un TRACE, ninguno de los cuales
 * cambia nada. Es lo mismo que haría cualquiera desde un navegador. No se
 * intenta entrar, no se prueban contraseñas, no se envía ningún formulario y no
 * se toca nada del sitio. A install.php y a repair.php se les mira si pintan el
 * formulario; nunca se les envía.
 *
 * ─── Las tres reglas que deciden si esto sirve o es ruido ───────────────────
 *
 * 1. NUNCA concluir por el código HTTP solo. Muchos sitios contestan 200 con la
 *    página de error del tema, así que un 200 no significa «ahí está». Cada
 *    ruta trae el patrón que su contenido tiene que calzar, y si no calza es
 *    «no está» aunque el código sea 200.
 *
 * 2. «Bloqueado» NO es «aprobado». Un 403 de Cloudflare y un «ese archivo no
 *    existe» se parecen desde fuera. El .git puede estar ahí detrás de una
 *    regla que alguien apague mañana. Por eso hay tres estados y no dos:
 *    expuesto, ausente y bloqueado — y «bloqueado» se reporta como «no se sabe»,
 *    no como que está bien.
 *
 * 3. Una sonda de control por sitio. Se pide una ruta que con certeza no existe
 *    y se compara con la portada. Si el sitio contesta a la ruta inventada lo
 *    mismo que a una real, sus resultados no sirven y se marcan como no
 *    fiables en bloque, en vez de producir veinte hallazgos falsos.
 *
 * ─── Sobre no tumbar los sitios de los clientes ─────────────────────────────
 *
 * Son unas cuarenta peticiones por sitio y 108 sitios. Lanzadas sitio por sitio
 * seguidas, la gestión de bots de Cloudflare empezaría a bloquear a media
 * corrida y —esto es lo peligroso— todo lo que viniera después se vería como
 * «bloqueado» sin que nada lo avisara. Por eso las peticiones van intercaladas:
 * la lista se arma dando vueltas entre todos los sitios, así que dos peticiones
 * seguidas al mismo sitio quedan separadas por las de los otros ciento siete.
 * Con 108 sitios y 15 en paralelo, cada sitio recibe una petición cada varios
 * segundos.
 */

defined( 'ABSPATH' ) || exit;

class SWPK_Sonda {

	/**
	 * La sonda corre sola, aparte de la corrida diaria.
	 *
	 * Medido: unas cuarenta peticiones por sitio, 4,4 segundos por sitio con
	 * quince en paralelo. En los 108 son unos ocho minutos. Meter eso dentro de
	 * SWPK_Sincronizador::correr() —que ya hace sus propias llamadas a Kinsta—
	 * daría una tarea de cron lo bastante larga como para que el límite de
	 * tiempo de PHP la mate a media corrida, y entonces se perderían las dos
	 * cosas. Separadas, si una falla la otra sigue.
	 *
	 * Va a las 5:30, una hora antes del inventario, para que cuando alguien
	 * abra el panel por la mañana las dos cosas estén frescas.
	 */
	public const HOOK = 'swpk_sonda_diaria';

	/**
	 * El reloj de la red: cada tanda pide la siguiente por su cuenta, y esto
	 * solo está por si el sitio no se puede llamar a sí mismo.
	 */
	public const HOOK_TANDA = 'swpk_sonda_tanda';

	/** Dónde se quedó la corrida. Es una opción, no un transitorio: no caduca. */
	private const COLA = 'swpk_sonda_cola';

	/** Para que dos tandas no saquen sitios de la misma cola a la vez. */
	private const CANDADO = 'swpk_sonda_candado';

	/**
	 * Cuántos sitios lleva una tanda y cuánto puede durar.
	 *
	 * Diez sitios son unas 250 peticiones, que con quince en paralelo salen
	 * en menos de un minuto. El reloj manda sobre la cantidad: si un día los
	 * sitios contestan lentos, la tanda hace menos sitios, pero termina.
	 */
	/**
	 * Un sitio por tanda, y cuarenta segundos de margen.
	 *
	 * Con el ritmo de POR_SEGUNDO, un sitio son unas 58 peticiones, o sea
	 * medio minuto. Diez sitios serían cinco minutos en una sola petición
	 * web, y el servidor la corta mucho antes.
	 */
	private const POR_TANDA = 1;
	private const SEGUNDOS  = 40;

	/** Un transient por sitio. Vive 25 horas: hasta la corrida de mañana. */
	private const CACHE = 'swpk_sonda_';
	private const VIDA  = 25 * HOUR_IN_SECONDS;

	/**
	 * Cuántas peticiones por segundo, como mucho, contra los sitios.
	 *
	 * ─── De dónde sale este número ──────────────────────────────────────────
	 *
	 * 27-11-2026, de noche. La corrida marcaba 20 sitios con un archivo
	 * alcanzable desde fuera, y resultaron ser los 20 PRIMEROS por orden
	 * alfabético: las dos primeras tandas. Del 21 en adelante, todo contesta
	 * 403 con el cuerpo de Cloudflare —«Sorry, you have been blocked»—, y con
	 * un agente de usuario de Chrome también. O sea que no es el agente: es
	 * la IP de salida del panel, bloqueada para todo.
	 *
	 * Lo dispara el propio panel. Una corrida son 6.264 peticiones en ocho
	 * minutos: trece por segundo contra sitios del mismo hosting. Cualquier
	 * cortafuegos con límite por IP corta eso, y después nos deja fuera un
	 * buen rato. El panel se estaba cegando solo, y de paso cegaba al monitor
	 * de caídas: es la causa del 23 de noviembre, cuando 106 sitios figuraban
	 * caídos sin estarlo.
	 *
	 * Dos por segundo: la corrida pasa de ocho minutos a una hora larga. Es
	 * una tarea de madrugada que no la espera nadie, y a cambio los datos son
	 * de verdad. Un dato medido en una hora vale más que seis mil peticiones
	 * en ocho minutos de las que solo sirven las primeras mil.
	 *
	 * Si algún día hace falta subirlo, el techo está entre las 1.000 y las
	 * 1.200 peticiones seguidas: es donde se cortó la corrida de ese día.
	 */
	private const POR_SEGUNDO = 2;

	private const TIMEOUT      = 12;
	private const CONCURRENCIA = 15;

	/** Cuántos bytes del cuerpo hacen falta para ver si el contenido calza. */
	private const TROZO = 24576;

	/** El cuerpo del POST a xmlrpc.php. Pide la lista de métodos y nada más. */
	private const XMLRPC_CUERPO = '<?xml version="1.0"?><methodCall><methodName>system.listMethods</methodName><params></params></methodCall>';

	/** Un origen que no es de nadie, para ver si el sitio acepta a cualquiera. */
	private const ORIGEN_AJENO = 'https://origen-que-no-existe.invalid';

	public const EXPUESTO  = 'expuesto';
	public const AUSENTE   = 'ausente';
	public const BLOQUEADO = 'bloqueado';
	public const PROTEGIDO = 'protegido';
	public const NO_SE_PUDO = 'no_se_pudo';

	/**
	 * Las extensiones contra las que se compara un bloqueo.
	 *
	 * ─── Por qué existe esto ────────────────────────────────────────────────
	 *
	 * Al principio cualquier 403 se contaba como «bloqueado: no se sabe», y en
	 * Kinsta eso dejaba las veintidós normas de esta sección en «sin dato» en
	 * todos los sitios. Inservible.
	 *
	 * Midiendo se vio que hay dos cosas distintas detrás del mismo 403:
	 *
	 *   /wp-config.php.bak      403      /loquesea.php.bak    403
	 *   /wp-content/debug.log   403      /loquesea.log        403
	 *   /phpinfo.php            403      /loquesea.php        404
	 *
	 * En las dos primeras la plataforma se niega a servir ESE TIPO de archivo,
	 * exista o no. Entonces da igual si el archivo está: nadie lo puede bajar.
	 * Eso es protección de verdad y se cuenta como cumple, diciendo por qué.
	 *
	 * En la tercera, una dirección inexistente con la misma extensión contesta
	 * 404 normal. O sea que el 403 fue para esa petición en concreto: nos retó
	 * el cortafuegos. Ahí no se aprendió nada y se dice que no se sabe.
	 *
	 * Es la diferencia entre un bloque de normas que sirve y veintidós filas
	 * que dicen «no sé».
	 */
	private const EXTENSIONES_DE_CONTROL = [ 'bak', 'save', 'old', 'tilde', 'txt', 'log', 'env', 'json', 'zip', 'sql', 'html', 'dir', 'dot' ];

	/**
	 * Las rutas que se piden, y qué tiene que traer el cuerpo para que cuente.
	 *
	 * El patrón es lo que separa un hallazgo de verdad de la página de error del
	 * tema devuelta con código 200. Las rutas sin patrón se juzgan solo por el
	 * código, y son las que no pueden dar un falso positivo por contenido.
	 *
	 * `{dominio}` se sustituye por el dominio sin puntos: los respaldos que
	 * alguien deja en la raíz suelen llamarse como el sitio.
	 *
	 * @return array<string,array{rutas:array<int,string>,patron:string}>
	 */
	private static function rutas(): array {
		return [
			// ── Lo que delata la configuración ────────────────────────────
			'config_respaldo' => [
				'control' => [ 'bak', 'save', 'old', 'tilde', 'txt' ],
				'rutas'  => [
					'wp-config.php.bak',
					'wp-config.php.save',
					'wp-config.php.old',
					'wp-config.php~',
					'wp-config.txt',
				],
				'patron' => '/DB_PASSWORD|DB_NAME|<\?php/i',
			],
			'debug_log' => [
				'control' => [ 'log', 'log' ],
				'rutas'  => [ 'wp-content/debug.log', 'debug.log' ],
				// El formato de fecha que escribe WordPress, o un error de PHP.
				'patron' => '/\[\d{2}-\w{3}-\d{4}|PHP (Fatal|Warning|Notice|Parse|Deprecated)/i',
			],
			'delatores' => [
				'control' => [ 'dot', 'dot', 'json', 'json', 'dot' ],
				'rutas'  => [ '.git/config', '.env', 'composer.json', 'package.json', '.DS_Store' ],
				'patron' => '/\[core\]|^\s*[A-Z_]{3,}=|"(require|dependencies|name)"\s*:|\x00\x00\x00\x01Bud1/i',
			],
			'volcados' => [
				'control' => [ 'zip', 'sql', 'sql', 'zip', 'zip', 'sql' ],
				'rutas'  => [ 'backup.zip', 'backup.sql', 'database.sql', 'site.zip', '{dominio}.zip', '{dominio}.sql' ],
				'patron' => '/(DROP|CREATE|LOCK) TABLE|INSERT INTO|^PK\x03\x04/i',
			],
			'phpinfo' => [
				'control' => [ 'php', 'php', 'php' ],
				'rutas'  => [ 'phpinfo.php', 'info.php', 'adminer.php' ],
				'patron' => '/phpinfo\(\)|PHP Version|Adminer|<title>phpinfo/i',
			],
			'restos_instalador' => [
				'control' => [ 'php', 'txt', 'dir', 'php' ],
				'rutas'  => [ 'installer.php', 'installer-log.txt', 'dup-installer/', 'emergency.php' ],
				'patron' => '/Duplicator|DUPLICATOR|installer|emergency|password reset/i',
			],

			// ── Puertas de mantenimiento de WordPress ─────────────────────
			//
			// Se mira si pintan el formulario que SÍ hace algo; nunca se les
			// envía nada.
			//
			// Aquí hubo un falso positivo y vale la pena dejarlo escrito. El
			// primer patrón era `/<form|WordPress &rsaquo;|repair|...)/` y daba
			// «expuesto» en todos los sitios probados. Resulta que las dos
			// páginas contestan 200 aunque estén cerradas: install.php dice «Ya
			// instalado» y repair.php dice «para permitir la reparación añade
			// WP_ALLOW_REPAIR a wp-config.php». Las dos traen la palabra
			// «repair» y un <form> de login, así que el patrón calzaba con la
			// respuesta SEGURA.
			//
			// Lo que de verdad distingue una de otra son los nombres de los
			// campos del formulario, que WordPress no traduce: `weblog_title`
			// solo aparece cuando install.php pide los datos del sitio nuevo, y
			// `repair=1` solo cuando repair.php ofrece los botones. Por eso el
			// patrón mira atributos de HTML y no texto: el texto cambia con el
			// idioma del sitio, y en la cartera hay sitios en español y en
			// inglés.
			'instalador' => [
				'control' => [ 'php', 'php' ],
				'rutas'  => [ 'wp-admin/install.php', 'wp-admin/maint/repair.php' ],
				'patron' => '/weblog_title|name=["\']repair["\']|\?repair=1|famous five-minute/i',
			],

			// ── Listado de carpetas ───────────────────────────────────────
			// Dos formas: la tabla de Apache y el <pre> de Nginx. Buscar solo
			// la de Apache, como hace WPScan, se pierde la de Nginx — que es
			// justo la que corre en Kinsta.
			'listado' => [
				// Aquí un 403 no es «no se sabe»: es la respuesta que se
				// quiere. La pregunta no es si la carpeta existe —existe— sino
				// si muestra lo que tiene dentro, y negarse es decir que no.
				'bloqueo_es_bueno' => true,
				'control' => [ 'dir', 'dir' ],
				'rutas'  => [ 'wp-content/uploads/', 'wp-content/plugins/' ],
				'patron' => '/<title>Index of |<h1>Index of |\[To Parent Directory\]|<pre><a href="\.\.\/">/i',
			],

			// ── Lo que solo revela la versión ─────────────────────────────
			'readme' => [
				'control' => [ 'html' ],
				'rutas'  => [ 'readme.html' ],
				'patron' => '/WordPress|Semantic Personal Publishing/i',
			],
		];
	}

	public static function init(): void {
		add_action( self::HOOK, [ __CLASS__, 'empezar' ] );
		add_action( self::HOOK_TANDA, [ __CLASS__, 'tanda' ] );

		// El relevo entre tandas. Va sin sesión a propósito: lo pide el panel
		// a sí mismo, no una persona. Lo que lo protege es la llave de la
		// corrida. Es el mismo mecanismo de SWPK_Trabajo::encadenar(), y está
		// aquí por el mismo motivo: en Pantheon el reloj real pasa cada hora,
		// y a ese ritmo una corrida de nueve tandas tardaría nueve horas.
		add_action( 'wp_ajax_nopriv_swpk_sonda_relevo', [ __CLASS__, 'relevo' ] );
		add_action( 'wp_ajax_swpk_sonda_relevo', [ __CLASS__, 'relevo' ] );

		// Y se programa aquí además de en la activación, para que los paneles
		// que ya estaban instalados antes de que esto existiera también la
		// tengan. programar() ya comprueba si está puesta.
		self::programar();
	}

	public static function programar(): void {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( SWPK_Frescura::cuando( 'tomorrow 05:30' ), 'daily', self::HOOK );
		}
	}

	public static function desprogramar(): void {
		foreach ( [ self::HOOK, self::HOOK_TANDA ] as $hook ) {
			$ts = wp_next_scheduled( $hook );
			if ( $ts ) {
				wp_unschedule_event( $ts, $hook );
			}
		}
	}

	/**
	 * Sondear la cartera y medir lo que de ahí sale, por tandas.
	 *
	 * ─── Por qué por tandas: esto nunca había terminado ─────────────────────
	 *
	 * 27-11-2026. El panel enseñaba 27 normas en «falta medirlo» en los 107
	 * sitios, y la explicación que yo le había dado a Fer era el cortafuegos.
	 * Era falsa. La verdadera estaba a un `get_option` de distancia:
	 * `swpk_ultima_sonda` no existía. **Esta corrida no había terminado ni una
	 * sola vez en producción.**
	 *
	 * Son 108 sitios por unas 25 peticiones cada uno. Tarda diez minutos, y
	 * ni el reloj de Pantheon ni una sesión de línea de comandos aguantan
	 * tanto: el proceso muere, y como lo único que se guardaba al final era
	 * el resumen, no quedaba nada. Diez minutos de trabajo, todos los días, a
	 * la basura. Medido: forzada a mano terminó en 108 sondeados, 1 sin rutas
	 * fiables, y Heraldos pasó de 27 normas sin medir a 4.
	 *
	 * Es la misma lección de SWPK_Trabajo, que ya estaba escrita en este
	 * repositorio hace nueve días: **un trabajo de minutos no cabe en una
	 * petición.** Allí se vio porque alguien pulsó un botón y salió un 504.
	 * Aquí no se vio porque nadie mira un cron que falla en silencio.
	 *
	 * Así que ahora es igual que la corrida: la cola se guarda, cada tanda
	 * hace unos pocos sitios, deja escrito dónde quedó y pide la siguiente.
	 * Si el servidor mata una tanda, se pierde esa tanda, no el día.
	 *
	 * ─── Y lo de «una corrida ciega no borra lo bueno» ──────────────────────
	 *
	 * Esa protección sigue, pero ahora es por sitio y no por corrida. Antes,
	 * si el 60 % de la cartera venía sin rutas fiables, no se medía NADA.
	 * Ahora, el sitio que vino sin rutas fiables no se mide —se queda con lo
	 * de ayer, que es lo que esa regla quería— y los demás sí. Protege lo
	 * mismo sin tirar el trabajo de los otros cien.
	 *
	 * @return array<string,mixed> en qué quedó el arranque
	 */
	public static function empezar(): array {
		$objetivos = [];

		foreach ( SWPK_Inventario::todos() as $sitio ) {
			$dominio = trim( (string) $sitio->dominio );
			if ( '' !== $dominio ) {
				$objetivos[ (string) $sitio->sitio_id ] = $dominio;
			}
		}

		$cola = [
			'inicio'  => current_time( 'mysql' ),
			'pend'    => $objetivos,
			'total'   => count( $objetivos ),
			'resumen' => [
				'sondeados'        => 0,
				'rutas_no_fiables' => 0,
				'expuestos'        => 0,
				'medidos'          => 0,
				'peticiones'       => 0,
				'caidas'           => 0,
			],
			// Nace y muere con la corrida. Es lo único que deja al panel
			// pedirse la siguiente tanda a sí mismo sin sesión de nadie.
			'llave'   => wp_generate_password( 32, false ),
			'latido'  => time(),
		];

		update_option( self::COLA, $cola, false );

		// La primera tanda se hace aquí mismo, sin pedírsela a nadie.
		//
		// Quien llama a esto es el reloj, y el aviso al propio panel para la
		// siguiente tanda sale de un proceso de línea de comandos que termina
		// enseguida: a veces la petición no llega a salir. Medido el
		// 27-11-2026: la primera vez arrancó, la segunda se quedó en 108
		// pendientes nueve minutos. Si la corrida hace una tanda antes de
		// soltar el testigo, al menos avanza siempre, y el encadenado se
		// vuelve a intentar al final de cada tanda, ya desde una petición web,
		// que es donde sí funciona.
		return [ 'arrancada' => true, 'total' => $cola['total'] ] + self::avanzar();
	}

	/** Lo que llama el reloj de respaldo. Ver init(). */
	public static function tanda(): void {
		self::avanzar();
	}

	/**
	 * Hace una tanda y deja pedida la siguiente.
	 *
	 * @param int $segundos Cuánto puede durar. Con 0 no para hasta terminar:
	 *                      es para la línea de comandos y las pruebas.
	 *
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

		// Si ya hay alguien avanzando, este se aparta: los dos sacarían
		// sitios de la misma cola y cada uno guardaría su versión.
		if ( ! self::candado() ) {
			return [ 'ocupado' => true ];
		}

		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 120 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}

		$hasta = $segundos > 0 ? microtime( true ) + $segundos : INF;

		do {
			$tanda = array_slice( (array) $cola['pend'], 0, self::POR_TANDA, true );

			if ( ! $tanda ) {
				break;
			}

			// Fuera de la cola ANTES de sondear: si el servidor mata esta
			// tanda a mitad, la siguiente sigue con los que faltan en vez de
			// volver a intentar los mismos para siempre.
			foreach ( array_keys( $tanda ) as $id ) {
				unset( $cola['pend'][ $id ] );
			}

			$cola['latido'] = time();
			update_option( self::COLA, $cola, false );

			self::$con_freno = true;

			$antes = self::contadores();
			$r     = self::sondear( $tanda );
			$desp  = self::contadores();

			// Se arrastran en la cola porque cada tanda es un proceso nuevo y
			// los contadores nacen en cero. Sirven para ver de un vistazo si
			// una corrida fue limpia o si la red se portó mal.
			$cola['resumen']['caidas']     = (int) ( $cola['resumen']['caidas'] ?? 0 ) + ( $desp['caidas'] - $antes['caidas'] );
			$cola['resumen']['peticiones'] = (int) ( $cola['resumen']['peticiones'] ?? 0 ) + ( $desp['peticiones'] - $antes['peticiones'] );

			foreach ( array_keys( $tanda ) as $sitio_id ) {
				$uno = $r[ (string) $sitio_id ] ?? [];

				++$cola['resumen']['sondeados'];

				if ( empty( $uno['rutas_fiables'] ) ) {
					++$cola['resumen']['rutas_no_fiables'];
					continue;
				}

				foreach ( (array) ( $uno['rutas'] ?? [] ) as $estado ) {
					if ( self::EXPUESTO === $estado ) {
						++$cola['resumen']['expuestos'];
						break;
					}
				}

				self::medir_uno( (string) $sitio_id );
				++$cola['resumen']['medidos'];
			}

			$cola['latido'] = time();
			update_option( self::COLA, $cola, false );
		} while ( ! empty( $cola['pend'] ) && microtime( true ) < $hasta );

		self::soltar_candado();

		if ( ! empty( $cola['pend'] ) ) {
			self::encadenar( (string) ( $cola['llave'] ?? '' ) );

			return [ 'pendientes' => count( $cola['pend'] ) ] + (array) $cola['resumen'];
		}

		return self::cerrar( $cola );
	}

	/** Mide las normas que salen de la sonda, para un sitio. */
	private static function medir_uno( string $sitio_id ): void {
		$sitio = SWPK_Inventario::obtener( $sitio_id );

		if ( ! $sitio ) {
			return;
		}

		$cuales = SWPK_Normas::COMPROBADORES_DE_SONDA;

		// La prueba de PHP en subidas la contesta mejor el agente. Desde
		// aquí solo se mide donde no hay agente, para no pisar lo bueno
		// con lo parcial.
		if ( ! (int) $sitio->cobertura_agente ) {
			$cuales[] = SWPK_Normas::COMPROBADOR_DE_AMBOS;
		}

		SWPK_Normas::medir( $sitio_id, [], $cuales );
	}

	/** Cierra la corrida: deja el resumen y borra la cola. */
	private static function cerrar( array $cola ): array {
		$resumen = (array) ( $cola['resumen'] ?? [] );

		$sondeados  = max( 1, (int) ( $resumen['sondeados'] ?? 0 ) );
		$no_fiables = (int) ( $resumen['rutas_no_fiables'] ?? 0 );

		// Ya no decide si se mide o no —eso se decidió sitio por sitio—, pero
		// se sigue anotando: una corrida en la que casi nadie contestó bien es
		// una corrida que hay que mirar, y la IP de salida dice por dónde fue.
		$resumen['corrida_mal'] = $no_fiables >= $sondeados * 0.6;

		if ( $resumen['corrida_mal'] ) {
			$resumen['ip_de_salida'] = SWPK_Monitor::ip_de_salida();
		}

		update_option(
			'swpk_ultima_sonda',
			[ 'cuando' => current_time( 'mysql' ), 'empezo' => (string) ( $cola['inicio'] ?? '' ) ] + $resumen,
			false
		);

		delete_option( self::COLA );

		SWPK_Estado::olvidar();

		return $resumen;
	}

	/** Si hay una corrida a medias, cuántos sitios le faltan. */
	public static function pendientes(): int {
		$cola = get_option( self::COLA, null );

		return is_array( $cola ) ? count( (array) ( $cola['pend'] ?? [] ) ) : 0;
	}

	/**
	 * Arranca la corrida. Se queda por el nombre: es lo que llamaban el reloj
	 * y la línea de comandos. Ya no hace el trabajo, lo pone en marcha.
	 *
	 * @return array<string,mixed>
	 */
	public static function correr(): array {
		return self::empezar();
	}

	private static function candado(): bool {
		return SWPK_Relevo::tomar( 'sonda' );
	}

	private static function soltar_candado(): void {
		SWPK_Relevo::soltar( 'sonda' );
	}

	/** Ver SWPK_Relevo: ahí está por qué esto no se manda sin esperar. */
	private static function encadenar( string $llave ): void {
		SWPK_Relevo::pedir( 'swpk_sonda_relevo', $llave, self::HOOK_TANDA );
	}

	/**
	 * El relevo: hace una tanda y, al terminar, pide la siguiente.
	 *
	 * No mira permisos porque no hay usuario: la manda el panel. Lo que
	 * comprueba es la llave de la corrida en curso, que es aleatoria y vive
	 * solo mientras dura esa corrida.
	 */
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
	 * Lo que la sonda averiguó de un sitio, o vacío si nunca se le pasó.
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

	/** Para el botón de «analizar este sitio ahora» y para las pruebas. */
	public static function olvidar( string $sitio_id = '' ): void {
		if ( '' !== $sitio_id ) {
			delete_transient( self::CACHE . $sitio_id );

			return;
		}

		global $wpdb;
		$wpdb->query(
			"DELETE FROM {$wpdb->options}
			  WHERE option_name LIKE '_transient_swpk_sonda_%'
			     OR option_name LIKE '_transient_timeout_swpk_sonda_%'"
		);
	}

	/**
	 * Sondea un sitio y guarda el resultado.
	 *
	 * @return array<string,mixed> vacío si el sitio no tiene dominio
	 */
	public static function sondear_uno( string $sitio_id, string $dominio ): array {
		$r = self::sondear( [ $sitio_id => $dominio ] );

		return $r[ $sitio_id ] ?? [];
	}

	/**
	 * Sondea toda la cartera.
	 *
	 * @return array<string,int>
	 */
	public static function sondear_todos(): array {
		$objetivos = [];

		foreach ( SWPK_Inventario::todos() as $sitio ) {
			$dominio = trim( (string) $sitio->dominio );
			if ( '' !== $dominio ) {
				$objetivos[ (string) $sitio->sitio_id ] = $dominio;
			}
		}

		$r = self::sondear( $objetivos );

		$resumen = [
			'sondeados'      => count( $r ),
			'rutas_no_fiables' => 0,
			'expuestos'      => 0,
		];

		foreach ( $r as $uno ) {
			if ( empty( $uno['rutas_fiables'] ) ) {
				++$resumen['rutas_no_fiables'];
				continue;
			}
			foreach ( (array) ( $uno['rutas'] ?? [] ) as $estado ) {
				if ( self::EXPUESTO === $estado ) {
					++$resumen['expuestos'];
					break;
				}
			}
		}

		return $resumen;
	}

	/**
	 * El motor.
	 *
	 * Nota de auditoría: esto habla con los sitios públicos de los clientes,
	 * nunca con la API de Kinsta. Las peticiones a Kinsta salen únicamente de
	 * includes/kinsta/class-http.php y son solo GET.
	 *
	 * @param array<string,string> $objetivos sitio_id => dominio
	 * @return array<string,array<string,mixed>>
	 */
	public static function sondear( array $objetivos ): array {
		if ( ! $objetivos || ! function_exists( 'curl_multi_init' ) ) {
			return [];
		}

		// Un nombre al azar por corrida: así ninguna respuesta puede venir de
		// la caché de una corrida anterior.
		$azar = wp_generate_password( 10, false, false );

		$por_sitio = [];

		foreach ( $objetivos as $sitio_id => $dominio ) {
			$por_sitio[ (string) $sitio_id ] = self::plan( (string) $sitio_id, (string) $dominio, $azar );
		}

		$crudo = self::lanzar( self::intercalar( $por_sitio ) );

		$agrupado = [];
		foreach ( $crudo as $fila ) {
			$agrupado[ $fila['sitio'] ][ $fila['que'] ] = $fila;
		}

		// ── Lo que no llegó se vuelve a pedir ───────────────────────────────
		//
		// 27-11-2026. Un mismo sitio salía con nueve rutas en «bloqueado»
		// dentro de la corrida completa, y con esas mismas rutas en
		// «protegido», «ausente» y una «expuesta» cuando se le sondeaba
		// aparte. No era el tamaño de la tanda: sondeándolo en grupos de 1,
		// 10 y 30 sale idéntico y bien.
		//
		// La diferencia está en una petición que se cae. Para decir «esto lo
		// niega el servidor a todo el mundo» hace falta la respuesta de una
		// dirección inventada con la misma extensión. Si ESA petición no
		// llega —se acaba el tiempo, se corta la conexión—, control_bloqueado()
		// devuelve false, y entonces el 403 del archivo real pasa de
		// «protegido» a «bloqueado», que en la pantalla sale como «falta
		// medirlo». O sea: una respuesta perdida de una dirección que no
		// existe convierte una norma medida en una norma sin medir.
		//
		// Una corrida son unas 2.700 peticiones. Que se caigan unas cuantas
		// es normal y no hay que arreglarlo con tiempos de espera más largos,
		// que harían la corrida eterna: se vuelven a pedir, y ya. Son pocas,
		// y esta vez van solas, sin otras 2.700 delante.
		$reintentos = self::fallidas( $por_sitio, $agrupado );

		if ( $reintentos ) {
			// Un respiro antes de volver a pedir: si lo que pasó es que nos
			// estaban limitando, repreguntar en el mismo segundo no sirve.
			sleep( 3 );

			foreach ( self::lanzar( self::intercalar_plano( $reintentos ) ) as $fila ) {
				// Solo si esta vez sí contestó. Una segunda caída no mejora
				// nada, y pisar la primera respuesta perdería el motivo.
				if ( empty( $fila['error'] ) && ! self::limitada( $fila ) ) {
					$agrupado[ $fila['sitio'] ][ $fila['que'] ] = $fila;
				}
			}
		}

		self::$caidas    += count( $reintentos );
		self::$peticiones += count( $crudo );

		// Segunda pasada, solo para lo que quedó sin explicar.
		//
		// Un 403 que el control de la extensión no explica puede seguir siendo
		// una regla de la plataforma, pero keyeada por NOMBRE de archivo.
		// Comprobado: composer.json contesta 403 incluso dentro de una carpeta
		// que no existe, mientras que una dirección .json inventada contesta
		// 404. O sea que la regla mira el nombre.
		//
		// Para distinguirlo se pide la MISMA ruta colgando de una carpeta
		// inventada. Si también viene bloqueada, es regla general y el archivo
		// no lo puede bajar nadie. Va en una segunda pasada porque solo hace
		// falta para las pocas rutas que quedaron en duda, y pedirla para
		// todas sería duplicar la corrida entera.
		$segunda = [];

		foreach ( $agrupado as $sitio_id => $partes ) {
			$base = self::base( (string) ( $objetivos[ $sitio_id ] ?? '' ) );

			foreach ( self::rutas() as $grupo => $def ) {
				foreach ( $def['rutas'] as $i => $ruta ) {
					if ( ! empty( $def['bloqueo_es_bueno'] ) ) {
						continue;
					}

					$clave = 'ruta:' . $grupo . ':' . $i;

					if ( self::BLOQUEADO !== self::clasificar( $partes[ $clave ] ?? [], $def['patron'] ) ) {
						continue;
					}

					if ( self::control_bloqueado( $partes, (string) ( $def['control'][ $i ] ?? '' ) ) ) {
						continue; // ya lo explicó la extensión
					}

					$segunda[] = [
						'sitio' => (string) $sitio_id,
						'que'   => 'ctrln:' . $grupo . ':' . $i,
						'url'   => $base . 'swpk-control-' . $azar . '/' . str_replace( '{dominio}', 'x', $ruta ),
						'post'  => null,
					];
				}
			}
		}

		if ( $segunda ) {
			$caidas_2 = [];

			foreach ( self::lanzar( self::intercalar_plano( $segunda ) ) as $fila ) {
				$agrupado[ $fila['sitio'] ][ $fila['que'] ] = $fila;

				if ( ! empty( $fila['error'] ) ) {
					$caidas_2[ $fila['sitio'] . '|' . $fila['que'] ] = true;
				}
			}

			// Lo mismo que arriba, para la pasada del nombre: una caída aquí
			// también termina en «falta medirlo».
			$otra = [];
			foreach ( $segunda as $p ) {
				if ( isset( $caidas_2[ $p['sitio'] . '|' . $p['que'] ] ) ) {
					$otra[] = $p;
				}
			}

			if ( $otra ) {
				self::$caidas += count( $otra );

				foreach ( self::lanzar( self::intercalar_plano( $otra ) ) as $fila ) {
					if ( empty( $fila['error'] ) ) {
						$agrupado[ $fila['sitio'] ][ $fila['que'] ] = $fila;
					}
				}
			}
		}

		$salida = [];

		foreach ( $agrupado as $sitio_id => $partes ) {
			$uno = self::interpretar( $partes, (string) ( $objetivos[ $sitio_id ] ?? '' ) );
			$uno = self::conservar_lo_medido( (string) $sitio_id, $uno );

			set_transient( self::CACHE . $sitio_id, $uno, self::VIDA );
			$salida[ $sitio_id ] = $uno;
		}

		return $salida;
	}

	/**
	 * Un «no se pudo» de hoy no borra un «sí se pudo» de ayer.
	 *
	 * ─── Qué problema resuelve ──────────────────────────────────────────────
	 *
	 * «bloqueado» no es un hallazgo: es «no pude averiguarlo». Significa que
	 * el archivo contestó 403 y la dirección inventada de control no, así que
	 * no se sabe si el 403 es una regla para todos o un cortafuegos cortando
	 * solo a la sonda. En la pantalla eso sale como «falta medirlo», que es
	 * justo lo que el jefe no quiere leer.
	 *
	 * Y no sale igual en todas las corridas. Medido el 27-11-2026 en un mismo
	 * sitio, el mismo día, con la misma versión: sondeado aparte da
	 * «protegido, ausente, expuesto»; dentro de la corrida completa, las
	 * nueve rutas en «bloqueado». No es el tamaño de la tanda —en grupos de
	 * 1, 10 y 30 sale idéntico y bien— ni son peticiones que se caigan: de
	 * 6.264 peticiones de una corrida se cayeron 4.
	 *
	 * Mientras no se sepa por qué, lo que no se puede hacer es tirar una
	 * medición buena de ayer para poner un «no sé» de hoy. Es la misma regla
	 * que ya gobierna la corrida entera —ver correr() y
	 * SWPK_Monitor::CIEGOS_PARA_DUDAR—, aplicada a cada ruta.
	 *
	 * El dato viejo no se queda para siempre: se guarda de dónde viene y
	 * cuándo se midió, y a los tres días deja de valer y la norma vuelve a
	 * «falta medirlo», que entonces sí será verdad.
	 *
	 * @param array<string,mixed> $uno
	 * @return array<string,mixed>
	 */
	private static function conservar_lo_medido( string $sitio_id, array $uno ): array {
		$viejo = self::de( $sitio_id );

		if ( ! $viejo ) {
			return $uno;
		}

		if ( empty( $viejo['rutas'] ) || empty( $uno['rutas'] ) ) {
			$uno['rutas'] = $uno['rutas'] ?? [];
		}

		$ahora   = current_time( 'mysql' );
		$limite  = strtotime( $ahora ) - 3 * DAY_IN_SECONDS;
		$dudosos = [ self::BLOQUEADO, self::NO_SE_PUDO ];
		$buenos  = [ self::PROTEGIDO, self::AUSENTE, self::EXPUESTO ];

		// Lo mismo con XML-RPC. La corrida diaria ya no lo pide —ver plan()—,
		// así que llega siempre como «no se pudo»; si alguien lo midió con el
		// botón, ese dato manda.
		if ( 'no_se_pudo' === (string) ( $uno['xmlrpc'] ?? '' ) ) {
			$antes_x = (string) ( $viejo['xmlrpc'] ?? '' );

			if ( '' !== $antes_x && 'no_se_pudo' !== $antes_x ) {
				$uno['xmlrpc']    = $antes_x;
				$uno['xmlrpc_de'] = (string) ( $viejo['xmlrpc_de'] ?? $viejo['cuando'] ?? '' );
			}
		}

		foreach ( (array) $uno['rutas'] as $grupo => $estado ) {
			if ( ! in_array( (string) $estado, $dudosos, true ) ) {
				continue;
			}

			$antes = (string) ( $viejo['rutas'][ $grupo ] ?? '' );

			if ( ! in_array( $antes, $buenos, true ) ) {
				continue;
			}

			$cuando = (string) ( $viejo['rutas_de'][ $grupo ] ?? $viejo['cuando'] ?? '' );

			if ( '' === $cuando || strtotime( $cuando ) < $limite ) {
				continue; // ya es viejo de verdad: que diga que no se sabe
			}

			$uno['rutas'][ $grupo ]    = $antes;
			$uno['rutas_de'][ $grupo ] = $cuando;
		}

		return $uno;
	}

	/** «Ahora no te contesto»: el borde nos está limitando, no es un dato. */
	private static function limitada( array $r ): bool {
		return in_array( (int) ( $r['codigo'] ?? 0 ), [ 429, 500, 502, 503, 504 ], true );
	}

	/**
	 * Si esta pasada va frenada.
	 *
	 * La corrida diaria sí: son 6.264 peticiones seguidas y es lo que nos
	 * bloquea la IP. El botón de «analizar este sitio ahora» no: son 58
	 * peticiones sueltas, no mueven ninguna aguja, y hay una persona
	 * esperando delante de la pantalla. Lo que hace daño es el volumen
	 * sostenido, no una consulta.
	 */
	private static bool $con_freno = false;

	/** Cuántas peticiones se cayeron y cuántas se hicieron, en esta corrida. */
	private static int $caidas     = 0;
	private static int $peticiones = 0;

	/** @return array{caidas:int,peticiones:int} */
	public static function contadores(): array {
		return [ 'caidas' => self::$caidas, 'peticiones' => self::$peticiones ];
	}

	/**
	 * Las peticiones del plan que no trajeron respuesta.
	 *
	 * @param array<string,array<int,array<string,mixed>>> $por_sitio
	 * @param array<string,array<string,array<string,mixed>>> $agrupado
	 * @return array<int,array<string,mixed>>
	 */
	private static function fallidas( array $por_sitio, array $agrupado ): array {
		$fuera = [];

		foreach ( $por_sitio as $sitio_id => $lista ) {
			foreach ( $lista as $p ) {
				$r = $agrupado[ (string) $sitio_id ][ (string) $p['que'] ] ?? null;

				if ( ! is_array( $r ) || ! empty( $r['error'] ) || self::limitada( $r ) ) {
					$fuera[] = $p;
				}
			}
		}

		return $fuera;
	}

	/**
	 * Todas las peticiones de un sitio.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private static function plan( string $sitio_id, string $dominio, string $azar ): array {
		$base  = self::base( $dominio );
		$plano = preg_replace( '/[^a-z0-9]/i', '', self::anfitrion( $dominio ) ) ?: 'sitio';

		$p = [];

		$pon = static function ( string $que, string $url, array $extra = [] ) use ( &$p, $sitio_id ): void {
			$p[] = array_merge( [ 'sitio' => $sitio_id, 'que' => $que, 'url' => $url ], $extra );
		};

		// La sonda de control: dos rutas que con certeza no existen. Si el
		// sitio contesta a estas como si existieran, todo lo demás sobra.
		$pon( 'control', $base . 'swpk-control-' . $azar );
		$pon( 'control_php', $base . 'swpk-control-' . $azar . '.php' );

		// La portada, con sus cabeceras y su certificado.
		$pon( 'portada', $base, [ 'seguir' => true, 'cert' => true ] );

		// CORS: la misma portada, pero diciendo que venimos de otro sitio.
		$pon( 'cors', $base, [ 'origen' => self::ORIGEN_AJENO ] );

		// El otro anfitrión: si el sitio es www, el pelado, y al revés.
		$otro = self::otro_anfitrion( $dominio );
		if ( '' !== $otro ) {
			$pon( 'canonico', 'https://' . $otro . '/' );
		}

		$pon( 'trace', $base, [ 'metodo' => 'TRACE' ] );
		$pon( 'robots', $base . 'robots.txt' );
		// ── XML-RPC: nunca en la corrida de toda la cartera ─────────────────
		//
		// 27-11-2026, por la noche. Soporte de Kinsta, textual:
		//
		//   «La razón por la que fuiste bloqueado es que hay muchas
		//    solicitudes XML-RPC a múltiples sitios alojados con nosotros en
		//    un corto período de tiempo. Más de 50 sitios.»
		//
		// Eran nuestras. Esta línea, una por sitio, 108 POST a xmlrpc.php en
		// ocho minutos. Visto desde el cortafuegos de Kinsta eso no se
		// distingue de un ataque de fuerza bruta repartido por la cartera, y
		// con razón: es literalmente la misma forma. Nos bloquearon la IP de
		// salida, y con ella se cegaron el sondeo de normas Y el monitor de
		// caídas.
		//
		// Así que desde la corrida diaria no se pide más. Se queda solo para
		// el botón de «analizar este sitio ahora»: un sitio, una petición,
		// cuando alguien la pide. Un sitio no es un patrón.
		//
		// El coste es real y hay que decirlo: la norma `xmlrpc_cerrado` solo
		// se puede afirmar desde fuera —el dato del agente da falsos
		// positivos, está explicado en comprobar_xmlrpc_cerrado()—, así que
		// en los sitios donde nadie pulse el botón se quedará en «falta
		// medirlo». Es el precio de no parecer un atacante.
		if ( ! self::$con_freno ) {
			$pon( 'xmlrpc', $base . 'xmlrpc.php', [ 'post' => self::XMLRPC_CUERPO ] );
		}
		$pon( 'usuarios_rest', $base . 'wp-json/wp/v2/users' );
		$pon( 'autor', $base . '?author=1' );
		$pon( 'uploads_php', $base . 'wp-content/uploads/swpk-' . $azar . '.php' );
		$pon( 'uploads_txt', $base . 'wp-content/uploads/swpk-' . $azar . '.txt' );

		// Un control por cada extensión que se va a pedir, para poder decir si
		// un 403 es «la plataforma no sirve este tipo de archivo» o «nos
		// bloquearon a nosotros». Ver EXTENSIONES_DE_CONTROL.
		foreach ( self::EXTENSIONES_DE_CONTROL as $ext ) {
			$pon( 'ctrl:' . $ext, $base . self::ruta_de_control( $azar, $ext ) );
		}

		// Las de la tienda. Se piden siempre: cuestan tres peticiones y en un
		// sitio sin WooCommerce contestan 404, que es la respuesta correcta.
		$pon( 'wc_pedidos', $base . 'wp-json/wc/v3/orders' );
		$pon( 'wc_clientes', $base . 'wp-json/wc/v3/customers' );
		$pon( 'wc_logs', $base . 'wp-content/uploads/wc-logs/' );

		foreach ( self::rutas() as $grupo => $def ) {
			foreach ( $def['rutas'] as $i => $ruta ) {
				$pon( 'ruta:' . $grupo . ':' . $i, $base . str_replace( '{dominio}', $plano, $ruta ) );
			}
		}

		return $p;
	}

	/**
	 * Mezcla las peticiones dando vueltas entre los sitios.
	 *
	 * Es lo que evita que un sitio reciba cuarenta peticiones seguidas y la
	 * gestión de bots empiece a cortarlas a media corrida.
	 *
	 * @param array<string,array<int,array<string,mixed>>> $por_sitio
	 * @return array<int,array<string,mixed>>
	 */
	private static function intercalar( array $por_sitio ): array {
		$salida = [];
		$largo  = 0;

		foreach ( $por_sitio as $lista ) {
			$largo = max( $largo, count( $lista ) );
		}

		for ( $i = 0; $i < $largo; $i++ ) {
			foreach ( $por_sitio as $lista ) {
				if ( isset( $lista[ $i ] ) ) {
					$salida[] = $lista[ $i ];
				}
			}
		}

		return $salida;
	}

	/** El nombre inventado que se pide para cada extensión. */
	private static function ruta_de_control( string $azar, string $ext ): string {
		$base = 'swpk-control-' . $azar;

		return match ( $ext ) {
			'tilde' => $base . '.php~',
			'dir'   => $base . '/',
			// Nginx niega los archivos que empiezan con punto, y eso no tiene
			// que ver con la extensión: .git/config, .env y .DS_Store caen por
			// el punto, no por lo que vaya después.
			'dot'   => '.' . $base,
			default => $base . '.' . $ext,
		};
	}

	/**
	 * Lo mismo que intercalar(), para una lista que ya viene plana.
	 *
	 * @param array<int,array<string,mixed>> $lista
	 * @return array<int,array<string,mixed>>
	 */
	private static function intercalar_plano( array $lista ): array {
		$por_sitio = [];

		foreach ( $lista as $p ) {
			$por_sitio[ $p['sitio'] ][] = $p;
		}

		return self::intercalar( $por_sitio );
	}

	private static function anfitrion( string $dominio ): string {
		$d = preg_replace( '#^https?://#i', '', trim( $dominio ) );

		return strtolower( trim( (string) strtok( (string) $d, '/' ) ) );
	}

	private static function base( string $dominio ): string {
		return 'https://' . trailingslashit( self::anfitrion( $dominio ) );
	}

	/**
	 * El mismo sitio con www, o sin www si ya lo trae.
	 *
	 * Aquí había una suposición que no aguantó los datos: se pedía el otro
	 * nombre solo si el dominio tenía un punto, para no inventar
	 * «www.panel.ejemplo.com». Pero media cartera es de Guatemala y usa
	 * «.com.gt» y «.org.gt», que tienen dos puntos siendo dominios normales.
	 * Comprobado: www.care.org.gt, www.worldvision.org.gt y www.unispice.com
	 * contestan 301, o sea que el otro nombre existe y la norma se estaba
	 * saltando en todos ellos.
	 *
	 * Así que no se adivina: se pide siempre. Si el nombre no existe, la
	 * petición falla y eso se lee como «el sitio responde por un solo nombre»,
	 * que es la respuesta correcta. Cuesta una petición por sitio.
	 */
	private static function otro_anfitrion( string $dominio ): string {
		$h = self::anfitrion( $dominio );

		if ( '' === $h || filter_var( $h, FILTER_VALIDATE_IP ) ) {
			return '';
		}

		return str_starts_with( $h, 'www.' ) ? substr( $h, 4 ) : 'www.' . $h;
	}

	/**
	 * @param array<int,array<string,mixed>> $peticiones
	 * @return array<int,array<string,mixed>>
	 */
	private static function lanzar( array $peticiones ): array {
		$hechas = [];

		foreach ( array_chunk( $peticiones, self::CONCURRENCIA ) as $tanda ) {
			$arranco = microtime( true );

			// Cada tanda vuelve a pedir tiempo. Sin esto, una corrida de ocho
			// minutos muere a la mitad en cualquier servidor con el límite por
			// omisión, y lo peor es que moriría en silencio.
			if ( function_exists( 'set_time_limit' ) ) {
				@set_time_limit( 60 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			}

			$multi      = curl_multi_init();
			$mangos     = [];
			$cabeceras  = [];

			foreach ( $tanda as $i => $p ) {
				$ch = curl_init();

				$cabeceras[ $i ] = '';

				$opciones = [
					CURLOPT_URL            => $p['url'],
					CURLOPT_RETURNTRANSFER => true,
					// Por omisión no se siguen redirecciones: un 301 a la
					// portada es un dato, y seguirlo convertiría «no existe»
					// en «200 con HTML».
					CURLOPT_FOLLOWLOCATION => ! empty( $p['seguir'] ),
					CURLOPT_MAXREDIRS      => 5,
					CURLOPT_TIMEOUT        => self::TIMEOUT,
					CURLOPT_CONNECTTIMEOUT => 8,
					CURLOPT_SSL_VERIFYPEER => true,
					CURLOPT_USERAGENT      => 'PanelKinsta/' . SWPK_VERSION . ' (revision de configuracion)',
					CURLOPT_HEADERFUNCTION => static function ( $ch, $linea ) use ( &$cabeceras, $i ) {
						$cabeceras[ $i ] .= $linea;

						return strlen( $linea );
					},
				];

				if ( ! empty( $p['cert'] ) ) {
					$opciones[ CURLOPT_CERTINFO ] = true;
				}

				if ( ! empty( $p['origen'] ) ) {
					$opciones[ CURLOPT_HTTPHEADER ] = [ 'Origin: ' . $p['origen'] ];
				}

				if ( ! empty( $p['metodo'] ) ) {
					$opciones[ CURLOPT_CUSTOMREQUEST ] = $p['metodo'];
					$opciones[ CURLOPT_NOBODY ]        = false;
				}

				if ( ! empty( $p['post'] ) ) {
					$opciones[ CURLOPT_POST ]       = true;
					$opciones[ CURLOPT_POSTFIELDS ] = $p['post'];
					$opciones[ CURLOPT_HTTPHEADER ] = [ 'Content-Type: text/xml' ];
				} else {
					// Un Range en un POST no tiene sentido y algunos
					// servidores lo rechazan.
					$opciones[ CURLOPT_RANGE ] = '0-' . self::TROZO;
				}

				curl_setopt_array( $ch, $opciones );
				curl_multi_add_handle( $multi, $ch );
				$mangos[ $i ] = $ch;
			}

			do {
				$estado = curl_multi_exec( $multi, $corriendo );
				if ( $corriendo ) {
					curl_multi_select( $multi, 1.0 );
				}
			} while ( $corriendo && CURLM_OK === $estado );

			// El número de error real solo sale de la cola, y hay que vaciarla
			// antes de soltar los handles. Es la misma trampa documentada en
			// SWPK_Monitor::sondear().
			$errores = [];
			while ( false !== ( $info = curl_multi_info_read( $multi ) ) ) {
				$errores[ spl_object_id( $info['handle'] ) ] = (int) $info['result'];
			}

			foreach ( $mangos as $i => $ch ) {
				$hechas[] = [
					'sitio'     => $tanda[ $i ]['sitio'],
					'que'       => $tanda[ $i ]['que'],
					'codigo'    => (int) curl_getinfo( $ch, CURLINFO_RESPONSE_CODE ),
					'cuerpo'    => (string) curl_multi_getcontent( $ch ),
					'cabeceras' => (string) ( $cabeceras[ $i ] ?? '' ),
					'url_final' => (string) curl_getinfo( $ch, CURLINFO_EFFECTIVE_URL ),
					'cert'      => empty( $tanda[ $i ]['cert'] ) ? [] : (array) ( curl_getinfo( $ch, CURLINFO_CERTINFO ) ?: [] ),
					'error'     => $errores[ spl_object_id( $ch ) ] ?? (int) curl_errno( $ch ),
				];

				curl_multi_remove_handle( $multi, $ch );
				curl_close( $ch );
			}

			curl_multi_close( $multi );

			// El freno. Ver POR_SEGUNDO: sin esto el panel se bloquea solo.
			// Se mide por tanda y no por petición porque las de una tanda
			// salen a la vez; lo que importa es el ritmo medio.
			if ( self::$con_freno ) {
				$debe = count( $tanda ) / max( 1, self::POR_SEGUNDO );
				$va   = microtime( true ) - $arranco;

				if ( $va < $debe ) {
					usleep( (int) ( ( $debe - $va ) * 1000000 ) );
				}
			}
		}

		return $hechas;
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Leer lo que contestaron
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * @param array<string,array<string,mixed>> $p
	 * @return array<string,mixed>
	 */
	private static function interpretar( array $p, string $dominio ): array {
		// La sonda de control invalida las comprobaciones que preguntan «¿está
		// este archivo?», porque en un sitio que contesta 200 a cualquier
		// dirección ese «200» no significa nada. No invalida las demás: las
		// cabeceras de la portada, el certificado y las direcciones que SÍ
		// existen —xmlrpc.php, la lista de usuarios— se siguen leyendo bien.
		// Tirar todo sería tan deshonesto como dar por buenos los hallazgos.
		$uno = [
			'cuando'        => current_time( 'mysql' ),
			'rutas_fiables' => self::control_sano( $p['control'] ?? [], $p['control_php'] ?? [] ),
		];

		$uno['xmlrpc']        = self::leer_xmlrpc( $p['xmlrpc'] ?? [] );
		$uno['usuarios_rest'] = self::leer_usuarios( $p['usuarios_rest'] ?? [] );
		$uno['uploads_regla'] = self::leer_uploads( $p['uploads_php'] ?? [], $p['uploads_txt'] ?? [] );
		$uno['autor']         = self::leer_autor( $p['autor'] ?? [] );
		$uno['trace']         = self::leer_trace( $p['trace'] ?? [] );
		$uno['robots']        = self::leer_robots( $p['robots'] ?? [] );
		$uno['canonico']      = self::leer_canonico( $p['portada'] ?? [], $p['canonico'] ?? null );
		$uno['cors']          = self::leer_cors( $p['cors'] ?? [] );
		$uno['cabeceras']     = self::leer_cabeceras( $p['portada'] ?? [] );
		$uno['mixto']         = self::leer_mixto( $p['portada'] ?? [], $dominio );
		$uno['certificado']   = self::leer_certificado( $p['portada'] ?? [] );
		$uno['rutas']         = $uno['rutas_fiables'] ? self::leer_rutas( $p ) : [];
		$uno['tienda_api']    = self::leer_wc_api( $p );
		$uno['tienda_logs']   = self::leer_wc_logs( $p['wc_logs'] ?? [] );

		return $uno;
	}

	/**
	 * ¿Se puede confiar en lo que contesta este sitio?
	 *
	 * Se pidieron dos rutas inventadas. Si alguna contesta 200 con un cuerpo
	 * de verdad, el sitio devuelve algo para cualquier dirección y entonces
	 * ningún «200» de los demás significa nada.
	 */
	private static function control_sano( array $a, array $b ): bool {
		foreach ( [ $a, $b ] as $c ) {
			if ( ! $c ) {
				return false;
			}

			// Un error de red en el control: no se puede afirmar nada del resto.
			if ( $c['error'] ) {
				return false;
			}

			if ( in_array( (int) $c['codigo'], [ 200, 206 ], true ) && strlen( (string) $c['cuerpo'] ) > 512 ) {
				return false;
			}
		}

		return true;
	}

	/** ¿Es una pantalla de bloqueo del borde y no una respuesta del sitio? */
	private static function lo_corto_el_borde( array $r ): bool {
		$cab = strtolower( (string) ( $r['cabeceras'] ?? '' ) );
		$cue = (string) ( $r['cuerpo'] ?? '' );

		if ( ! str_contains( $cab, 'cf-ray' ) && ! str_contains( $cab, 'cf-mitigated' ) ) {
			return false;
		}

		// «Just a moment…» es la pantalla de reto, no una de bloqueo, y la
		// diferencia importa: un reto lo pasa cualquier navegador de verdad.
		// O sea que un archivo detrás de un reto NO está protegido de una
		// persona, solo de nuestro sondeo. Por eso cuenta como «no se sabe» y
		// nunca como protegido.
		return (bool) preg_match(
			'/you have been blocked|attention required|just a moment|enable javascript and cookies|error code\s*:?\s*10\d\d|cloudflare to restrict access/i',
			$cue
		);
	}

	/**
	 * Clasifica una respuesta en expuesto / ausente / bloqueado / no se pudo.
	 *
	 * El patrón es lo que hace esto utilizable: sin él, cualquier sitio que
	 * conteste su página de error con código 200 se vería como si tuviera
	 * todos los archivos del mundo.
	 */
	private static function clasificar( array $r, string $patron ): string {
		if ( ! $r || $r['error'] ) {
			return self::NO_SE_PUDO;
		}

		if ( self::lo_corto_el_borde( $r ) ) {
			return self::BLOQUEADO;
		}

		$codigo = (int) $r['codigo'];

		if ( in_array( $codigo, [ 401, 403, 451 ], true ) ) {
			return self::BLOQUEADO;
		}

		// ── 429 y 5xx no son «ese archivo no está» ──────────────────────────
		//
		// Es «ahora no te contesto». Tratarlos como AUSENTE tiene dos caras y
		// las dos son malas: en la ruta real convierte un «no sé» en un
		// «cumple», que es la mentira que la regla 3 prohíbe; y en la
		// dirección de control hace que control_bloqueado() diga que no, con
		// lo cual el 403 del archivo real pasa de «protegido» a «bloqueado»,
		// que en la pantalla es «falta medirlo».
		//
		// Esto último es lo que estaba pasando el 27-11-2026: el mismo sitio
		// sondeado aparte salía bien y dentro de la corrida completa salía con
		// nueve rutas sin medir. Aparte son 250 peticiones; dentro de la
		// corrida van unas 6.000 seguidas desde la misma IP, y a partir de
		// cierto punto el borde empieza a contestar con limitaciones.
		if ( in_array( $codigo, [ 429, 500, 502, 503, 504 ], true ) ) {
			return self::NO_SE_PUDO;
		}

		if ( ! in_array( $codigo, [ 200, 206 ], true ) ) {
			return self::AUSENTE;
		}

		$cuerpo = (string) $r['cuerpo'];

		if ( '' === $patron ) {
			return '' !== trim( $cuerpo ) ? self::EXPUESTO : self::AUSENTE;
		}

		// 200 pero el contenido no es lo que debería: es la página de error del
		// tema, o una redirección servida como 200.
		return preg_match( $patron, $cuerpo ) ? self::EXPUESTO : self::AUSENTE;
	}

	/**
	 * El veredicto de cada grupo de rutas.
	 *
	 * Un grupo está expuesto si cualquiera de sus rutas lo está. Si ninguna lo
	 * está pero alguna quedó bloqueada, el grupo queda bloqueado: no se sabe.
	 *
	 * @param array<string,array<string,mixed>> $p
	 * @return array<string,string>
	 */
	private static function leer_rutas( array $p ): array {
		$salida = [];

		foreach ( self::rutas() as $grupo => $def ) {
			$estados = [];

			foreach ( array_keys( $def['rutas'] ) as $i ) {
				$estado = self::clasificar( $p[ 'ruta:' . $grupo . ':' . $i ] ?? [], $def['patron'] );

				// Un 403 puede ser la plataforma negándose a servir ese tipo de
				// archivo —y entonces da igual si está, porque nadie lo baja— o
				// el cortafuegos cortando nuestra petición, y entonces no se
				// aprendió nada. Lo dice el control de la misma extensión.
				if ( self::BLOQUEADO === $estado ) {
					$explicado = ! empty( $def['bloqueo_es_bueno'] )
						|| self::control_bloqueado( $p, (string) ( $def['control'][ $i ] ?? '' ) )
						|| self::bloqueado_por_nombre( $p[ 'ctrln:' . $grupo . ':' . $i ] ?? [] );

					if ( $explicado ) {
						$estado = self::PROTEGIDO;
					}
				}

				$estados[] = $estado;
			}

			// Lo peor manda: un archivo expuesto pesa más que cuatro ausentes,
			// y «no se sabe» pesa más que «protegido».
			foreach ( [ self::EXPUESTO, self::BLOQUEADO, self::PROTEGIDO, self::AUSENTE ] as $peor ) {
				if ( in_array( $peor, $estados, true ) ) {
					$salida[ $grupo ] = $peor;
					continue 2;
				}
			}

			$salida[ $grupo ] = self::NO_SE_PUDO;
		}

		return $salida;
	}

	/** ¿La misma ruta bajo una carpeta inventada también vino bloqueada? */
	private static function bloqueado_por_nombre( array $r ): bool {
		if ( ! $r || $r['error'] || self::lo_corto_el_borde( $r ) ) {
			return false;
		}

		return in_array( (int) $r['codigo'], [ 401, 403, 451 ], true );
	}

	/**
	 * ¿La dirección inventada con esta misma extensión también vino bloqueada?
	 *
	 * Si sí, el servidor no sirve ese tipo de archivo exista o no: es una regla
	 * de la plataforma y no una pista sobre este archivo en concreto.
	 */
	private static function control_bloqueado( array $p, string $ext ): bool {
		if ( '' === $ext ) {
			return false;
		}

		$r = 'php' === $ext ? ( $p['control_php'] ?? [] ) : ( $p[ 'ctrl:' . $ext ] ?? [] );

		if ( ! $r || $r['error'] ) {
			return false;
		}

		if ( self::lo_corto_el_borde( $r ) ) {
			return false; // al control también lo retaron: no prueba nada
		}

		return in_array( (int) $r['codigo'], [ 401, 403, 451 ], true );
	}

	/**
	 * Las rutas de WooCommerce que entregan pedidos y clientes.
	 *
	 * Tienen que pedir credenciales. Un 401 o un 403 es lo correcto; un 404 es
	 * que no hay WooCommerce. Lo que no puede pasar es un 200 con datos.
	 *
	 * @return array<int,string>
	 */
	private static function leer_wc_api( array $p ): array {
		$abiertas = [];

		foreach ( [ 'wc_pedidos' => 'los pedidos', 'wc_clientes' => 'los clientes' ] as $clave => $que ) {
			$r = $p[ $clave ] ?? [];

			if ( ! $r || $r['error'] || ! in_array( (int) $r['codigo'], [ 200, 206 ], true ) ) {
				continue;
			}

			// Un 200 tiene que traer un arreglo JSON para ser un hallazgo: el
			// 200 con la página de error del tema no entrega nada.
			$datos = json_decode( (string) $r['cuerpo'], true );

			if ( is_array( $datos ) && $datos && isset( $datos[0] ) ) {
				$abiertas[] = $que;
			}
		}

		return $abiertas;
	}

	/** ¿La carpeta de registros de WooCommerce lista lo que tiene dentro? */
	private static function leer_wc_logs( array $r ): ?bool {
		if ( ! $r || $r['error'] ) {
			return null;
		}

		if ( ! in_array( (int) $r['codigo'], [ 200, 206 ], true ) ) {
			return false;
		}

		return (bool) preg_match( '/<title>Index of |<h1>Index of |<pre><a href="\.\.\/">/i', (string) $r['cuerpo'] );
	}

	/**
	 * ¿Se puede entrar por XML-RPC?
	 *
	 *   'responde'   el servidor lo sirvió y WordPress contestó la lista de
	 *                métodos: la puerta está abierta de verdad.
	 *   'rechaza'    llegó a WordPress y WordPress dijo que no.
	 *   'bloqueado'  no llegó a WordPress. Es lo que hace Kinsta.
	 *   'no_se_pudo' no se pudo averiguar.
	 */
	private static function leer_xmlrpc( array $p ): string {
		if ( ! $p || $p['error'] ) {
			return 'no_se_pudo';
		}

		$codigo = (int) $p['codigo'];
		$cuerpo = (string) $p['cuerpo'];

		if ( in_array( $codigo, [ 401, 403, 404, 405, 410, 451, 501 ], true ) ) {
			return 'bloqueado';
		}

		if ( 200 !== $codigo ) {
			return 'no_se_pudo';
		}

		// Un 200 que no trae respuesta XML-RPC es la página de error del tema o
		// una pantalla de bloqueo. No es «abierto».
		if ( ! str_contains( $cuerpo, 'methodResponse' ) ) {
			return 'bloqueado';
		}

		return str_contains( $cuerpo, 'faultCode' ) ? 'rechaza' : 'responde';
	}

	/**
	 * ¿Cuántos nombres de usuario publica el sitio?
	 *
	 * Se devuelve la cuenta, nunca los nombres: el panel no necesita guardar
	 * quién es quién en el sitio de un cliente para decir que están expuestos.
	 *
	 * @return int|null null si no se pudo averiguar
	 */
	private static function leer_usuarios( array $p ): ?int {
		if ( ! $p || $p['error'] ) {
			return null;
		}

		if ( ! in_array( (int) $p['codigo'], [ 200, 206 ], true ) ) {
			return 0;
		}

		$datos = json_decode( (string) $p['cuerpo'], true );

		if ( ! is_array( $datos ) ) {
			return 0;
		}

		$n = 0;

		foreach ( $datos as $u ) {
			if ( is_array( $u ) && isset( $u['slug'] ) ) {
				++$n;
			}
		}

		return $n;
	}

	/**
	 * ¿/?author=1 redirige a /author/<nombre>/ y así revela el nombre?
	 *
	 * Se devuelve si lo revela, no el nombre.
	 *
	 * @return bool|null null si no se pudo averiguar
	 */
	private static function leer_autor( array $p ): ?bool {
		if ( ! $p || $p['error'] ) {
			return null;
		}

		$codigo = (int) $p['codigo'];

		if ( ! in_array( $codigo, [ 301, 302, 307, 308 ], true ) ) {
			return false;
		}

		return (bool) preg_match( '#^location:\s*\S*/author/[^/\s]+#im', (string) $p['cabeceras'] );
	}

	/** @return bool|null */
	private static function leer_trace( array $p ): ?bool {
		if ( ! $p || $p['error'] ) {
			return null;
		}

		// Un TRACE atendido contesta 200 y repite la petición en el cuerpo.
		return 200 === (int) $p['codigo'] && str_contains( strtolower( (string) $p['cuerpo'] ), 'trace /' );
	}

	/**
	 * ¿El sitio le pide a los buscadores que no lo indexen?
	 *
	 * @return bool|null true si pide no ser indexado
	 */
	private static function leer_robots( array $p ): ?bool {
		if ( ! $p || $p['error'] ) {
			return null;
		}

		if ( ! in_array( (int) $p['codigo'], [ 200, 206 ], true ) ) {
			return false;
		}

		return (bool) preg_match( '/^\s*Disallow:\s*\/\s*$/im', (string) $p['cuerpo'] );
	}

	/**
	 * ¿Hay un solo anfitrión canónico?
	 *
	 *   'canonico'   uno sirve y el otro redirige hacia él.
	 *   'duplicado'  los dos contestan 200: contenido duplicado, y las cookies
	 *                y el HSTS quedan repartidos entre dos nombres.
	 *   'solo_uno'   el otro nombre no existe. No es un problema.
	 *   'no_se_pudo' no se pudo averiguar.
	 */
	private static function leer_canonico( array $portada, ?array $otro ): string {
		if ( ! $portada || $portada['error'] ) {
			return 'no_se_pudo';
		}

		if ( null === $otro || ! $otro ) {
			return 'solo_uno';
		}

		// Error de red o de certificado al pedir el otro nombre: casi siempre
		// es que no existe, que es lo que queremos.
		if ( $otro['error'] ) {
			return 'solo_uno';
		}

		$codigo = (int) $otro['codigo'];

		if ( in_array( $codigo, [ 301, 302, 307, 308 ], true ) ) {
			return 'canonico';
		}

		if ( in_array( $codigo, [ 200, 206 ], true ) ) {
			return 'duplicado';
		}

		return 'solo_uno';
	}

	/**
	 * ¿El sitio acepta peticiones con credenciales desde cualquier origen?
	 *
	 * Es la penalización más grande que pone Mozilla Observatory, y con razón:
	 * abre los datos de quien esté con la sesión iniciada a cualquier página
	 * que la víctima visite. Pero solo cuenta si viene junto con el permiso de
	 * credenciales — un `*` a secas en contenido público no es un problema.
	 *
	 * @return bool|null
	 */
	private static function leer_cors( array $p ): ?bool {
		if ( ! $p || $p['error'] ) {
			return null;
		}

		$cab = self::cabeceras( $p );

		$permitido   = strtolower( trim( $cab['access-control-allow-origin'] ?? '' ) );
		$credenciales = strtolower( trim( $cab['access-control-allow-credentials'] ?? '' ) );

		if ( '' === $permitido ) {
			return false;
		}

		$abierto = '*' === $permitido || str_contains( $permitido, 'origen-que-no-existe.invalid' );

		return $abierto && 'true' === $credenciales;
	}

	/**
	 * Las cabeceras de seguridad de la portada.
	 *
	 * @return array<string,mixed>
	 */
	private static function leer_cabeceras( array $p ): array {
		if ( ! $p || $p['error'] ) {
			return [];
		}

		$cab = self::cabeceras( $p );
		$csp = strtolower( $cab['content-security-policy'] ?? '' );

		$hsts     = $cab['strict-transport-security'] ?? '';
		$max_age  = 0;
		if ( preg_match( '/max-age\s*=\s*(\d+)/i', $hsts, $m ) ) {
			$max_age = (int) $m[1];
		}

		return [
			'hsts_max_age' => $max_age,
			'marco'        => '' !== ( $cab['x-frame-options'] ?? '' ) || str_contains( $csp, 'frame-ancestors' ),
			'referrer'     => strtolower( trim( $cab['referrer-policy'] ?? '' ) ),
			'csp'          => $csp,
			'permissions'  => '' !== ( $cab['permissions-policy'] ?? '' ),
			'xss_obsoleta' => isset( $cab['x-xss-protection'] ) && '0' !== trim( $cab['x-xss-protection'] ),
		];
	}

	/**
	 * ¿La portada carga recursos por http:// dentro de una página https?
	 *
	 * Solo cuentan los recursos —script, img, link, iframe—, no los enlaces:
	 * un <a href="http://..."> a otro sitio no es contenido mixto.
	 *
	 * @return int|null cuántos encontró
	 */
	private static function leer_mixto( array $p, string $dominio ): ?int {
		if ( ! $p || $p['error'] || ! in_array( (int) $p['codigo'], [ 200, 206 ], true ) ) {
			return null;
		}

		$cuerpo = (string) $p['cuerpo'];
		$n      = 0;

		if ( preg_match_all( '/<(script|img|iframe|link|source|video|audio)\b[^>]*\b(src|href)\s*=\s*["\']http:\/\/[^"\']+/i', $cuerpo, $m ) ) {
			$n = count( $m[0] );
		}

		return $n;
	}

	/**
	 * El certificado: a cuántos días vence, y si cubre el nombre pedido.
	 *
	 * @return array<string,mixed>
	 */
	private static function leer_certificado( array $p ): array {
		if ( ! $p ) {
			return [ 'ok' => null ];
		}

		// Un error de certificado hace fallar la petición entera, y eso es
		// precisamente el hallazgo.
		$errores_de_cert = [ 51, 60, 83 ]; // peer failed verification, CA, issuer check

		if ( $p['error'] ) {
			return in_array( (int) $p['error'], $errores_de_cert, true )
				? [ 'ok' => false, 'motivo' => 'El certificado no es válido para este sitio.' ]
				: [ 'ok' => null ];
		}

		$cert = (array) ( $p['cert'] ?? [] );

		if ( ! $cert || empty( $cert[0]['Expire date'] ) ) {
			return [ 'ok' => true, 'dias' => null ];
		}

		$vence = strtotime( (string) $cert[0]['Expire date'] );

		return [
			'ok'   => true,
			'dias' => $vence ? (int) floor( ( $vence - time() ) / DAY_IN_SECONDS ) : null,
		];
	}

	/**
	 * ¿Se ejecuta PHP en la carpeta de subidas?
	 *
	 *   'bloquea'    hay regla: ni siquiera entrega el archivo.
	 *   'sin_regla'  no hay regla por extensión.
	 *   'no_se_pudo' no se pudo averiguar.
	 */
	private static function leer_uploads( array $php, array $txt ): string {
		if ( ! $php || ! $txt || $php['error'] || $txt['error'] ) {
			return 'no_se_pudo';
		}

		$cp = (int) $php['codigo'];
		$ct = (int) $txt['codigo'];

		if ( 403 === $cp && 403 !== $ct ) {
			return 'bloquea';
		}

		if ( $cp === $ct ) {
			return 'sin_regla';
		}

		return 'no_se_pudo';
	}

	/**
	 * Las cabeceras del último tramo, en minúsculas.
	 *
	 * Importa que sea el último: con redirecciones seguidas vienen varios
	 * bloques pegados, y las que valen son las de la página que se ve.
	 *
	 * @return array<string,string>
	 */
	private static function cabeceras( array $p ): array {
		$crudo = (string) ( $p['cabeceras'] ?? '' );

		// Cada bloque empieza con una línea de estado.
		$bloques = preg_split( '/\r?\n\r?\n/', trim( $crudo ) ) ?: [];
		$ultimo  = (string) end( $bloques );

		$salida = [];

		foreach ( preg_split( '/\r?\n/', $ultimo ) ?: [] as $linea ) {
			if ( ! str_contains( $linea, ':' ) ) {
				continue;
			}

			[ $k, $v ] = explode( ':', $linea, 2 );

			$salida[ strtolower( trim( $k ) ) ] = trim( $v );
		}

		return $salida;
	}
}
