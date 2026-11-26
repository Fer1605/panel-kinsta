<?php
/**
 * Monitor de caídas.
 *
 *   "Cuando el sitio se cae o hay una mala configuración... esa notificación
 *    también debería llegar a nosotros. El sitio se cayó, nos debería llegar
 *    una notificación." — 07:09
 *
 * ─── Por qué el panel vigila por su cuenta en vez de esperar a Kinsta ───────
 *
 * Porque un aviso que depende de Kinsta no sirve para enterarse de que Kinsta
 * se cayó. Esa es la razón de fondo por la que el panel vive en Plesk y no en
 * Kinsta — el jefe lo dijo así: "para que cuando pase algo, Plesk no se cae. Si
 * pasa algo en Kinsta, Plesk sigue funcionando" (06:48).
 *
 * Que las alertas de Kinsta también le lleguen al equipo es otra cosa, y se
 * arregla en MyKinsta agregando una dirección de distribución como usuario de
 * la empresa. Son minutos y no es programación. Las dos cosas se complementan.
 *
 * ─── Qué cuenta como "caído" ────────────────────────────────────────────────
 *
 * No solo que no responda. El jefe dijo "se cae o hay una mala configuración",
 * y hay un caso muy común que devuelve 200 y parece sano: el sitio contesta
 * pero con "Error al establecer una conexión con la base de datos" o con el
 * error crítico de WordPress. Para el visitante ese sitio está caído, así que
 * aquí también.
 *
 *   arriba  → respondió 2xx o 3xx y el contenido no delata un error
 *   abajo   → no respondió, tardó demasiado, devolvió 5xx, o devolvió una
 *             página de error de WordPress
 *   raro    → 4xx. Puede ser un cortafuegos bloqueándonos a nosotros y no una
 *             caída real, así que se marca pero no se grita
 *
 * ─── Por qué no avisa al primer fallo ───────────────────────────────────────
 *
 * Un corte de red de tres segundos no es una caída. Hacen falta dos sondeos
 * fallidos seguidos —diez minutos— para declararlo caído y avisar. Un monitor
 * que grita por cada parpadeo deja de leerse en una semana, y entonces no
 * sirve para nada.
 */

defined( 'ABSPATH' ) || exit;

class SWPK_Monitor {

	public const HOOK = 'swpk_sondeo';

	/**
	 * Cada cuánto se sondea.
	 *
	 * `hourly` es de WordPress, no nuestro, y eso importa: el intervalo propio
	 * de cinco minutos que había antes es exactamente lo que no llegó a
	 * registrarse nunca al activar el plugin. Ver programar().
	 *
	 * Y es una hora porque es lo que el hosting puede dar. Ver esta_al_dia().
	 */
	public const INTERVALO = 'hourly';

	private const TIMEOUT        = 12;
	private const CONCURRENCIA   = 15;
	/**
	 * Cuántos sondeos fallidos hacen falta para decir que un sitio está caído.
	 *
	 * Era 2, y la segunda opinión llegaba en el sondeo siguiente. Con el
	 * sondeo cada cinco minutos eso costaba cinco minutos de retraso; con el
	 * sondeo cada hora costaría DOS HORAS, y entonces el panel diría «reviso
	 * cada hora» mientras avisa a las dos. Así que la segunda opinión se pide
	 * dentro de la misma corrida, unos segundos después: ver confirmar(). Un
	 * corte de red de tres segundos sigue sin contar como caída, que es para
	 * lo que estaba la regla.
	 */
	private const FALLOS_PARA_AVISAR = 1;

	/** Cuánto se espera antes de preguntar por segunda vez. */
	private const ESPERA_CONFIRMAR = 8;

	/** Cuánto puede pasar sin sondeo antes de decir que no está corriendo. */
	private const MARGEN = 2 * HOUR_IN_SECONDS;

	/**
	 * Cuántas horas sin reporte hacen que un agente cuente como callado.
	 *
	 * 48 y no 26: el agente reporta una vez al día, así que un día perdido
	 * puede ser el reloj del sitio yendo flojo. Dos ya no.
	 */
	private const HORAS_PARA_CALLADO = 48;

	/** De qué sitios caídos ya se avisó hoy. sitio_id => 'Y-m-d'. */
	private const OPCION_RECORDADOS = 'swpk_caidas_recordadas';

	/** De qué agentes callados ya se avisó hoy. sitio_url => 'Y-m-d'. */
	private const OPCION_CALLADOS = 'swpk_agentes_avisados';

	/**
	 * A partir de qué proporción de bloqueados la corrida habla de NOSOTROS.
	 *
	 * ─── El hallazgo que obliga a esto ──────────────────────────────────────
	 *
	 * Medido el 25 de noviembre de 2026, desde el propio servidor del panel y
	 * contra los MISMOS catorce sitios, dos veces seguidas:
	 *
	 *   saliendo por 34.46.111.53  →  403 en los catorce
	 *   saliendo por 35.239.102.121 →  200 en los catorce
	 *
	 * O sea: los sitios no bloquean al panel. Cloudflare tiene UNA de las IP
	 * de salida de Pantheon en su lista negra y la otra limpia, y Pantheon no
	 * garantiza desde cuál sale cada contenedor — su documentación dice que
	 * las IP de salida son dinámicas y que no publican rangos. Así que cada
	 * corrida es una moneda al aire.
	 *
	 * Lo que eso significa para el panel es concreto: una corrida que sale por
	 * la IP mala no mide nada, y hasta hoy esa corrida PISABA lo que había
	 * medido la corrida buena de hace una hora. Un dato bueno de hace una hora
	 * vale infinitamente más que un «no se pudo ver» de ahora.
	 *
	 * Así que cuando casi todo vuelve bloqueado, la conclusión no es «la
	 * cartera está tapada»: es «salimos por la IP mala». Se dice así, no se
	 * escribe nada encima, y a la hora siguiente se vuelve a intentar.
	 *
	 * 60 % y no 100 %: puede haber a la vez sitios bloqueados de verdad.
	 */
	private const CIEGOS_PARA_DUDAR = 0.6;

	/**
	 * Por qué IP salió esta petición.
	 *
	 * Es el dato que pide soporte del hosting para mirar su lista de bloqueo,
	 * y el único que el panel no puede saber por su cuenta: desde dentro, un
	 * contenedor no ve su propia IP pública. Solo se pregunta cuando una
	 * corrida vuelve casi entera bloqueada, que es cuando hace falta.
	 */
	public static function ip_de_salida(): string {
		$r = wp_remote_get( 'https://api.ipify.org', [ 'timeout' => 8 ] );

		if ( is_wp_error( $r ) ) {
			return '';
		}

		$ip = trim( (string) wp_remote_retrieve_body( $r ) );

		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	}
	private const LENTO_MS       = 5000;

	/**
	 * Frases con las que el cortafuegos del hosting dice "a ti no te contesto".
	 *
	 * ─── Por qué esto merece un estado propio ───────────────────────────────
	 *
	 * El 23 de noviembre de 2026, 106 de los 109 sitios figuraban en el panel
	 * con "Devolvió 403. Puede ser un cortafuegos bloqueando al monitor". El
	 * jefe abrió uno en el navegador, cargó perfecto, y preguntó lo único que
	 * se puede preguntar: "entonces, ¿por qué aquí dice que no?" (20:26).
	 *
	 * La causa no era de los sitios: el panel vive en Pantheon y los sitios
	 * están en el hosting vigilado, detrás de Cloudflare, que bloquea las IP
	 * de salida de Pantheon. La respuesta trae su propia confesión en el
	 * cuerpo: dice que no se puede acceder al dominio del hosting.
	 *
	 * Así que el panel no estaba midiendo nada y lo contaba como un hallazgo.
	 * Eso es exactamente lo que la regla 3 prohíbe, en espejo: no decir "está
	 * bien" cuando no se sabe vale también al revés — no decir "algo le pasa"
	 * cuando lo único que pasa es que desde aquí no se puede ver.
	 *
	 * Un sitio así queda en estado `ciego`: no cuenta como caída, no dispara
	 * aviso y no se pinta como problema. Se dice lo que es.
	 */
	private const SENALES_DE_BLOQUEO = [
		'Attention Required! | Cloudflare',
		'You are unable to access',
		'Sorry, you have been blocked',
		'This website is using a security service to protect itself',
		'cf-error-details',
	];

	/** Frases que significan "caído" aunque el servidor conteste 200. */
	private const SENALES_DE_ERROR = [
		'Error establishing a database connection',
		'Error al establecer una conexión con la base de datos',
		'There has been a critical error on this website',
		'Ha ocurrido un error crítico en esta web',
		'Ha habido un error crítico en esta web',
	];

	public static function init(): void {
		add_action( self::HOOK, [ __CLASS__, 'sondear_todos' ] );

		// Y se programa aquí además de en la activación, igual que la sonda y
		// la revisión diaria. Ver programar(): en los paneles instalados antes
		// de este arreglo el sondeo NUNCA quedó puesto.
		self::programar();
	}

	/**
	 * Pone el sondeo cada hora, si no estaba.
	 *
	 * ─── Lo que enseñó el fallo que esto arregla ────────────────────────
	 *
	 * Antes el sondeo iba con un intervalo nuestro, `swpk_cinco_minutos`, y
	 * no quedó programado NUNCA, en ninguna instalación. `wp_schedule_event()`
	 * comprueba que la recurrencia exista en `wp_get_schedules()`, y si no
	 * existe devuelve `false` sin decir nada: ni excepción, ni aviso, ni
	 * registro. Ese intervalo lo metía un filtro enganchado en `init()`, que
	 * cuelga de `plugins_loaded` — y al ACTIVAR el plugin `plugins_loaded` ya
	 * pasó, porque WordPress incluye el archivo y dispara el gancho de
	 * activación dentro de una petición donde el plugin todavía no estaba
	 * activo. Los demás relojes usan `daily` y `weekly` y por eso sí estaban.
	 *
	 * Ahora usa `hourly`, que WordPress trae de fábrica, así que el fallo no
	 * puede repetirse. Y de paso se comprueba lo que quedó puesto, porque una
	 * función que devuelve `false` en vez de quejarse necesita que alguien
	 * mire el resultado.
	 */
	public static function programar(): void {
		$puesto = wp_next_scheduled( self::HOOK );

		// Los paneles que vienen de la versión de cinco minutos traen el
		// evento con una recurrencia que ya no existe. Un evento así corre una
		// vez y no se vuelve a poner: hay que cambiarlo, no dejarlo estar.
		if ( $puesto && self::INTERVALO !== wp_get_schedule( self::HOOK ) ) {
			self::desprogramar();
			$puesto = false;
		}

		if ( ! $puesto ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, self::INTERVALO, self::HOOK );
		}
	}

	public static function desprogramar(): void {
		$ts = wp_next_scheduled( self::HOOK );
		if ( $ts ) {
			wp_unschedule_event( $ts, self::HOOK );
		}
	}

	/**
	 * Sondea todos los sitios del inventario que tengan dominio.
	 *
	 * @return array{sondeados:int,abajo:int,cambios:int}
	 */
	public static function sondear_todos(): array {
		$objetivos = [];
		$ausentes  = [];

		foreach ( SWPK_Inventario::todos() as $sitio ) {
			$dominio = trim( (string) $sitio->dominio );
			if ( '' === $dominio ) {
				continue;
			}

			// ── Un sitio borrado no es un sitio caído ───────────────────────
			//
			// «El sitio ya no existe. Lo sabemos porque lo borramos.
			//  Claramente el panel no lo va a saber, solo lo ve, está entre
			//  los registros, pero simplemente no responde.»
			//
			// Sí lo sabe, y lo sabía desde el primer día: cuando el hosting
			// deja de devolver un sitio, el panel se lo apunta en
			// `ausencias`. Lo que no hacía era CREERSE ese dato aquí, así
			// que seguía tocándole la puerta a un dominio borrado, no le
			// contestaba nadie, y lo contaba como caída. Dos pantallas del
			// panel diciendo cosas distintas del mismo sitio: una «se cayó»
			// y la otra «va por 3 de 5 para darlo de baja».
			//
			// La cuenta de cinco sigue igual, porque así lo pidió el jefe
			// —«si después de cinco intentos de estar verificando que el
			// dominio no existe, no responde, decir: hemos dado de baja»
			// (02:19)— y porque una API que falla un día no puede borrar un
			// sitio vivo. Lo que cambia es QUÉ SE DICE mientras tanto: «el
			// hosting ya no lo devuelve, 3 de 5» en vez de «sin responder».
			// Ni cuenta como caída, ni dispara aviso, ni ensucia la dona.
			if ( (int) ( $sitio->ausencias ?? 0 ) > 0 ) {
				$ausentes[ (string) $sitio->sitio_id ] = [
					'estado' => 'ausente',
					'codigo' => 0,
					'ms'     => 0,
					'motivo' => sprintf(
						'El hosting ya no devuelve este sitio (%d de %d comprobaciones). No se sondea: lo más probable es que lo hayan borrado.',
						(int) $sitio->ausencias,
						SWPK_Inventario::AUSENCIAS_PARA_BAJA
					),
					'url'    => self::url( $dominio ),
				];
				continue;
			}

			$objetivos[ (string) $sitio->sitio_id ] = self::url( $dominio );
		}

		if ( ! $objetivos && ! $ausentes ) {
			return [ 'sondeados' => 0, 'abajo' => 0, 'cambios' => 0 ];
		}

		$resultados = $objetivos
			? self::confirmar( self::sondear( $objetivos ), $objetivos )
			: [];

		// Los ausentes entran en el mismo saco para que su fila se escriba y
		// la pantalla los enseñe por lo que son, no por lo que eran.
		$resultados += $ausentes;

		$abajo   = 0;
		$cambios = 0;
		$ciegos  = 0;

		foreach ( $resultados as $r ) {
			if ( 'ciego' === ( $r['estado'] ?? '' ) ) {
				++$ciegos;
			}
		}

		// ¿Esta corrida habla de los sitios, o de por dónde salimos?
		// Ver CIEGOS_PARA_DUDAR.
		$sondeados   = max( 1, count( $objetivos ) );
		$corrida_mal = $ciegos >= $sondeados * self::CIEGOS_PARA_DUDAR;
		$ip          = $corrida_mal ? self::ip_de_salida() : '';

		foreach ( $resultados as $sitio_id => $r ) {
			// En una corrida mala, lo bloqueado NO se escribe: se deja lo que
			// midió la última corrida buena, que es un dato de verdad. Lo que
			// sí contestó se guarda, porque eso sí se midió.
			if ( $corrida_mal && 'ciego' === ( $r['estado'] ?? '' ) ) {
				continue;
			}

			if ( 'abajo' === $r['estado'] ) {
				++$abajo;
			}
			$cambios += self::guardar( (string) $sitio_id, $r ) ? 1 : 0;
		}

		update_option(
			'swpk_ultimo_sondeo',
			[
				'cuando'       => current_time( 'mysql' ),
				'sondeados'    => count( $resultados ),
				'abajo'        => $abajo,
				'ciegos'       => $ciegos,
				'corrida_mal'  => $corrida_mal,
				'ip_de_salida' => $ip,
			],
			false
		);

		// Los dos recordatorios diarios viajan con la corrida del sondeo, y no
		// con un cron propio, a propósito.
		//
		// Esta corrida está COMPROBADO que pasa: es la que escribió la línea de
		// arriba. Un `wp_schedule_event()` nuevo no tiene esa garantía — en
		// este mismo panel el sondeo de cinco minutos no quedó programado
		// NUNCA y nadie se enteró durante semanas (ver programar()). Un aviso
		// que existe para que no se pierda nada no puede colgar de un reloj
		// que puede no estar puesto.
		//
		// Cada uno se manda una vez al día: ellos mismos llevan la cuenta.
		if ( ! $corrida_mal ) {
			self::recordar_caidas();
		}

		self::avisar_agentes_callados();

		return [ 'sondeados' => count( $resultados ), 'abajo' => $abajo, 'cambios' => $cambios ];
	}

	/**
	 * Pide una segunda opinión de los que salieron caídos.
	 *
	 * Un sitio que no contesta una vez casi nunca está caído: es la red, un
	 * segundo de carga del servidor, un cortafuegos con el día torcido. Por
	 * eso nunca se avisó al primer fallo. Lo que cambia es CUÁNDO llega la
	 * segunda opinión: antes, en el sondeo siguiente; ahora, ocho segundos
	 * después, aquí mismo. Así una caída de verdad se ve dentro de la misma
	 * corrida en vez de en la de dentro de una hora.
	 *
	 * Cuesta ocho segundos y solo cuando hay algo caído. Si no hay ninguno
	 * —que es lo normal— esto no hace nada.
	 *
	 * @param array<string,array<string,mixed>> $resultados lo que salió
	 * @param array<string,string>              $objetivos  sitio_id => url
	 * @return array<string,array<string,mixed>>
	 */
	private static function confirmar( array $resultados, array $objetivos ): array {
		$dudosos = [];

		foreach ( $resultados as $sitio_id => $r ) {
			if ( 'abajo' === ( $r['estado'] ?? '' ) && isset( $objetivos[ $sitio_id ] ) ) {
				$dudosos[ $sitio_id ] = $objetivos[ $sitio_id ];
			}
		}

		if ( ! $dudosos ) {
			return $resultados;
		}

		sleep( self::ESPERA_CONFIRMAR );

		foreach ( self::sondear( $dudosos ) as $sitio_id => $segundo ) {
			$resultados[ $sitio_id ] = $segundo;
		}

		return $resultados;
	}

	/**
	 * Sondea UN sitio y guarda el resultado.
	 *
	 * sondear() solo mide: ni arma la dirección ni escribe nada. Esto es lo que
	 * hace falta detrás del botón de "analizar este sitio ahora", y vive aquí
	 * para que no haya dos sitios distintos donde se decide qué cuenta como
	 * caído.
	 *
	 * @return array<string,mixed> lo que devolvió el sondeo, vacío si no hay dominio
	 */
	public static function sondear_uno( string $sitio_id, string $dominio ): array {
		$dominio = trim( $dominio );
		if ( '' === $dominio ) {
			return [];
		}

		$r = self::sondear( [ $sitio_id => self::url( $dominio ) ] );
		$uno = $r[ $sitio_id ] ?? [];

		if ( $uno ) {
			self::guardar( $sitio_id, $uno );
		}

		return $uno;
	}

	private static function url( string $dominio ): string {
		if ( str_starts_with( $dominio, 'http://' ) || str_starts_with( $dominio, 'https://' ) ) {
			return $dominio;
		}
		return 'https://' . $dominio . '/';
	}

	/**
	 * Hace los sondeos en paralelo.
	 *
	 * Con 107 sitios, uno detrás de otro no funciona: a doce segundos de
	 * espera cada uno, un día malo son veinte minutos de corrida. Por eso va
	 * con curl en paralelo, de quince en quince, y acaba en medio minuto.
	 *
	 * Nota de auditoría: esto habla con los sitios públicos de los clientes,
	 * nunca con la API de Kinsta. Las peticiones a Kinsta salen únicamente de
	 * includes/kinsta/class-http.php y son solo GET.
	 *
	 * @param array<string,string> $objetivos sitio_id => url
	 * @return array<string,array<string,mixed>>
	 */
	public static function sondear( array $objetivos ): array {
		if ( ! function_exists( 'curl_multi_init' ) ) {
			return self::sondear_uno_por_uno( $objetivos );
		}

		$resultados = [];

		foreach ( array_chunk( $objetivos, self::CONCURRENCIA, true ) as $tanda ) {
			$multi   = curl_multi_init();
			$mangos  = [];

			foreach ( $tanda as $sitio_id => $url ) {
				$ch = curl_init();
				curl_setopt_array(
					$ch,
					[
						CURLOPT_URL            => $url,
						CURLOPT_RETURNTRANSFER => true,
						CURLOPT_FOLLOWLOCATION => true,
						CURLOPT_MAXREDIRS      => 4,
						CURLOPT_TIMEOUT        => self::TIMEOUT,
						CURLOPT_CONNECTTIMEOUT => 8,
						CURLOPT_SSL_VERIFYPEER => true,
						CURLOPT_USERAGENT      => 'PanelKinsta/' . SWPK_VERSION . ' (monitor de disponibilidad)',
						// Con el cuerpo se detecta el sitio que contesta 200 pero
						// muestra un error de base de datos. 48 KB alcanzan.
						CURLOPT_RANGE          => '0-49152',
					]
				);
				curl_multi_add_handle( $multi, $ch );
				$mangos[ $sitio_id ] = $ch;
			}

			do {
				$estado = curl_multi_exec( $multi, $corriendo );
				if ( $corriendo ) {
					curl_multi_select( $multi, 1.0 );
				}
			} while ( $corriendo && CURLM_OK === $estado );

			// Trampa de curl en paralelo: curl_errno() sobre el handle suelto
			// se queda en 0 aunque la petición haya fallado. El número de error
			// real solo sale de la cola de resultados, y hay que vaciarla antes
			// de soltar los handles. Sin esto, un dominio que no resuelve y un
			// servidor caído se ven exactamente igual: "no respondió".
			$errores = [];
			while ( false !== ( $info = curl_multi_info_read( $multi ) ) ) {
				$errores[ spl_object_id( $info['handle'] ) ] = (int) $info['result'];
			}

			foreach ( $mangos as $sitio_id => $ch ) {
				$cuerpo = (string) curl_multi_getcontent( $ch );
				$codigo = (int) curl_getinfo( $ch, CURLINFO_RESPONSE_CODE );
				$ms     = (int) round( (float) curl_getinfo( $ch, CURLINFO_TOTAL_TIME ) * 1000 );

				// En paralelo, curl_error() suele venir vacío: hay que leer el
				// número de error. Y vale la pena traducirlo, porque para
				// soporte no es lo mismo "el dominio no resuelve" —un problema
				// de DNS, posiblemente del cliente— que "el servidor rechazó la
				// conexión", que es el hosting. Con "no respondió" a secas
				// alguien tiene que ir a averiguarlo a mano.
				$numero = $errores[ spl_object_id( $ch ) ] ?? (int) curl_errno( $ch );
				$error  = self::explicar_error( $numero, (string) curl_error( $ch ) );

				$resultados[ $sitio_id ] = self::juzgar( $codigo, $ms, $cuerpo, $error, $tanda[ $sitio_id ] );

				curl_multi_remove_handle( $multi, $ch );
				curl_close( $ch );
			}

			curl_multi_close( $multi );
		}

		return $resultados;
	}

	/** @param array<string,string> $objetivos */
	private static function sondear_uno_por_uno( array $objetivos ): array {
		$resultados = [];

		foreach ( $objetivos as $sitio_id => $url ) {
			$inicio = microtime( true );
			$r      = wp_remote_get( $url, [ 'timeout' => self::TIMEOUT, 'redirection' => 4 ] );
			$ms     = (int) round( ( microtime( true ) - $inicio ) * 1000 );

			$resultados[ $sitio_id ] = is_wp_error( $r )
				? self::juzgar( 0, $ms, '', $r->get_error_message(), $url )
				: self::juzgar(
					(int) wp_remote_retrieve_response_code( $r ),
					$ms,
					substr( (string) wp_remote_retrieve_body( $r ), 0, 49152 ),
					'',
					$url
				);
		}

		return $resultados;
	}

	/** Traduce el número de error de curl a algo que se pueda leer en el correo a soporte. */
	private static function explicar_error( int $numero, string $crudo ): string {
		if ( 0 === $numero ) {
			return '';
		}

		$conocidos = [
			CURLE_COULDNT_RESOLVE_HOST => 'El dominio no resuelve (problema de DNS)',
			CURLE_COULDNT_CONNECT      => 'El servidor rechazó la conexión',
			CURLE_OPERATION_TIMEDOUT   => 'Se acabó el tiempo de espera',
			CURLE_SSL_CONNECT_ERROR    => 'Falló la conexión segura (SSL)',
			CURLE_TOO_MANY_REDIRECTS   => 'Demasiadas redirecciones',
		];

		if ( defined( 'CURLE_PEER_FAILED_VERIFICATION' ) ) {
			$conocidos[ CURLE_PEER_FAILED_VERIFICATION ] = 'El certificado del sitio no es válido o está vencido';
		}

		// libcurl moderno devuelve 60 para el fallo de verificación del
		// certificado, que no siempre coincide con la constante de PHP. Es un
		// caso demasiado común —un certificado que venció el fin de semana— como
		// para dejarlo caer en el mensaje genérico.
		$conocidos[60] = 'El certificado del sitio no es válido o está vencido';

		if ( isset( $conocidos[ $numero ] ) ) {
			return $conocidos[ $numero ];
		}

		return '' !== $crudo ? $crudo : sprintf( 'Error de red (%d)', $numero );
	}

	private static function juzgar( int $codigo, int $ms, string $cuerpo, string $error, string $url ): array {
		if ( '' !== $error || 0 === $codigo ) {
			return [
				'estado' => 'abajo',
				'codigo' => $codigo,
				'ms'     => $ms,
				'motivo' => $error ?: 'No respondió',
				'url'    => $url,
			];
		}

		if ( $codigo >= 500 ) {
			return [ 'estado' => 'abajo', 'codigo' => $codigo, 'ms' => $ms, 'motivo' => 'El servidor devolvió ' . $codigo, 'url' => $url ];
		}

		// El caso traicionero: contesta bien pero la página es un error.
		foreach ( self::SENALES_DE_ERROR as $senal ) {
			if ( false !== stripos( $cuerpo, $senal ) ) {
				return [
					'estado' => 'abajo',
					'codigo' => $codigo,
					'ms'     => $ms,
					'motivo' => 'Responde ' . $codigo . ' pero muestra un error de WordPress',
					'url'    => $url,
				];
			}
		}

		// Un 4xx con la firma del cortafuegos no es un hallazgo del sitio: es
		// que a nosotros no nos dejan mirar. Ver SENALES_DE_BLOQUEO.
		if ( $codigo >= 400 ) {
			foreach ( self::SENALES_DE_BLOQUEO as $senal ) {
				if ( false !== stripos( $cuerpo, $senal ) ) {
					return [
						'estado' => 'ciego',
						'codigo' => $codigo,
						'ms'     => $ms,
						'motivo' => 'El hosting del sitio bloquea al monitor del panel, así que desde aquí no se puede medir si está arriba',
						'url'    => $url,
					];
				}
			}

			return [
				'estado' => 'raro',
				'codigo' => $codigo,
				'ms'     => $ms,
				'motivo' => 'Devolvió ' . $codigo . '. Puede ser un cortafuegos bloqueando al monitor',
				'url'    => $url,
			];
		}

		return [
			'estado' => 'arriba',
			'codigo' => $codigo,
			'ms'     => $ms,
			'motivo' => $ms > self::LENTO_MS ? sprintf( 'Responde, pero tardó %.1f s', $ms / 1000 ) : '',
			'url'    => $url,
		];
	}

	/** Guarda el resultado y, si el estado cambió de verdad, deja evento y avisa. @return bool hubo cambio */
	private static function guardar( string $sitio_id, array $r ): bool {
		global $wpdb;

		$tabla = SWPK_Tablas::tabla( 'monitor' );
		$ahora = current_time( 'mysql' );

		$previo = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tabla} WHERE sitio_id = %s", $sitio_id ) );

		$fallo  = 'abajo' === $r['estado'];
		$fallos = $fallo ? ( (int) ( $previo->fallos_seguidos ?? 0 ) + 1 ) : 0;

		// No se avisa por un parpadeo: el que llega aquí como caído ya falló
		// dos veces, la segunda ocho segundos después de la primera. Ver
		// confirmar() y FALLOS_PARA_AVISAR.
		$estado_publico = $previo->estado ?? 'desconocido';
		$cambio         = false;

		if ( $fallo && $fallos >= self::FALLOS_PARA_AVISAR ) {
			$estado_publico = 'abajo';
		} elseif ( ! $fallo ) {
			$estado_publico = $r['estado'];
		}

		$datos = [
			'sitio_id'        => $sitio_id,
			'url'             => $r['url'],
			'estado'          => $estado_publico,
			'codigo'          => $r['codigo'],
			'ms'              => $r['ms'],
			'motivo'          => mb_substr( (string) $r['motivo'], 0, 190 ),
			'fallos_seguidos' => $fallos,
			'ultimo_sondeo'   => $ahora,
		];

		if ( ! $previo ) {
			$datos['desde'] = $ahora;
			$wpdb->insert( $tabla, $datos );
			return false; // Primera vez: línea base, no se avisa.
		}

		$antes = (string) $previo->estado;

		if ( $antes !== $estado_publico ) {
			$cambio          = true;
			$datos['desde']  = $ahora;

			if ( 'abajo' === $estado_publico ) {
				$datos['caidas_mes'] = (int) $previo->caidas_mes + 1;
				self::avisar_caida( $sitio_id, $r );
			} elseif ( 'ausente' === $estado_publico ) {
				// De «caído» a «ya no está en el hosting» NO es una
				// recuperación: el sitio no volvió, es que dejamos de
				// preguntarle. Mandar «volvió a responder» aquí sería
				// mentir, y además es el correo que menos se quiere recibir
				// de un sitio que se acaba de borrar. El de baja sale solo
				// cuando toque, desde SWPK_Inventario.
				SWPK_Insistencia::olvidar( $sitio_id );
			} elseif ( 'abajo' === $antes ) {
				$segundos = max( 0, strtotime( $ahora ) - strtotime( (string) $previo->desde ) );
				$datos['segundos_caido'] = (int) $previo->segundos_caido + $segundos;
				self::avisar_recuperacion( $sitio_id, $segundos );
			}
		}

		$wpdb->update( $tabla, $datos, [ 'id' => (int) $previo->id ] );

		return $cambio;
	}

	private static function avisar_caida( string $sitio_id, array $r ): void {
		$sitio  = SWPK_Inventario::obtener( $sitio_id );
		$nombre = $sitio->nombre_visible ?? $sitio_id;

		SWPK_Eventos::registrar(
			SWPK_Eventos::SITIO_CAIDO,
			$sitio_id,
			sprintf( '%s dejó de responder: %s', $nombre, $r['motivo'] ),
			'critico',
			'',
			$r
		);

		// Y aquí ya NO se vuelve uno callado si no hay a quién avisarle.
		//
		// Antes esta función hacía `if ( ! $soporte ) return;`, y el 23 de
		// noviembre de 2026 eso costó la confianza en el panel: un sitio llevaba
		// horas caído, el jefe entró al Historial, vio la caída y no vio ni un
		// aviso, ni uno fallido. En producción `correo_soporte` estaba vacío,
		// así que el panel se enteró, lo apuntó como evento y se guardó el
		// aviso para sí mismo. "Sitio caído, pero nunca me avisó. Y aquí no
		// dice que notificó, no notificó" (16:44).
		//
		// Un aviso que no salió es información: `enviar()` lo deja en la
		// bitácora como `sin_destinatario`. Callarse es lo único que no vale.
		$soporte = self::destinatarios_caida();

		if ( ! SWPK_Correos::encendido( 'caida' ) ) {
			SWPK_Bitacora::registrar(
				SWPK_Bitacora::CAIDA,
				$soporte,
				sprintf( '[Caído] %s — aviso apagado', $nombre ),
				'',
				$sitio_id,
				'apagado',
				'El aviso de caída está apagado en Correos → Los avisos. La caída sí quedó anotada.'
			);

			return;
		}

		// El texto sale de la plantilla editable, no de aquí.
		//
		// Estaba escrito a mano en este archivo, y eso lo dejaba fuera de la
		// pantalla de Plantillas: era el único correo que el panel manda y que
		// nadie podía cambiar sin tocar código. Los avisos de caída son los que
		// más se leen a deshoras, así que conviene poder ajustarles el tono.
		$marcas = [
			'{{sitio}}'  => esc_html( $nombre ),
			'{{url}}'    => esc_html( (string) $r['url'] ),
			'{{cuando}}' => esc_html( SWPK_Frescura::fmt( 'j \d\e F, H:i', current_time( 'mysql' ) ) ),
			'{{motivo}}' => esc_html( (string) $r['motivo'] ),
		];

		SWPK_Bitacora::enviar(
			SWPK_Bitacora::CAIDA,
			$soporte,
			SWPK_Plantillas::asunto( 'caida', [ '{{sitio}}' => $nombre ] ),
			SWPK_Plantillas::caida( $marcas ),
			$sitio_id
		);
	}

	private static function avisar_recuperacion( string $sitio_id, int $segundos ): void {
		$sitio  = SWPK_Inventario::obtener( $sitio_id );
		$nombre = $sitio->nombre_visible ?? $sitio_id;
		$cuanto = self::duracion( $segundos );

		SWPK_Eventos::registrar(
			SWPK_Eventos::SITIO_REPUESTO,
			$sitio_id,
			sprintf( '%s volvió a responder. Estuvo caído %s', $nombre, $cuanto ),
			'ok',
			'',
			[ 'segundos' => $segundos ]
		);

		// Lo mismo que en avisar_caida(): sin destinatario se registra, no se
		// calla. Un "volvió a responder" que nadie recibió también hay que
		// poder verlo.
		$soporte = self::destinatarios_caida();

		if ( ! SWPK_Correos::encendido( 'repuesto' ) ) {
			return;
		}

		$marcas = [
			'{{sitio}}'  => esc_html( $nombre ),
			'{{cuanto}}' => esc_html( $cuanto ),
		];

		SWPK_Bitacora::enviar(
			SWPK_Bitacora::CAIDA,
			$soporte,
			SWPK_Plantillas::asunto( 'repuesto', [ '{{sitio}}' => $nombre ] ),
			SWPK_Plantillas::repuesto( $marcas ),
			$sitio_id
		);
	}

	/**
	 * El recordatorio de que un sitio SIGUE caído.
	 *
	 * ─── Por qué hacía falta ────────────────────────────────────────────────
	 *
	 * Porque el aviso de caída sale UNA vez, en el momento en que el sitio
	 * cambia de estado, y no vuelve a salir nunca. Un sitio que lleva dos días
	 * sin responder es, desde el punto de vista del correo, un sitio del que
	 * no se ha vuelto a saber nada.
	 *
	 * Pasó de verdad: un sitio de la cartera dejó de responder el 23 de
	 * noviembre de 2026 a las 15:35. El aviso de ese momento ni siquiera
	 * salió, porque aún no había destinatario de soporte configurado. Y como
	 * el aviso solo se dispara en el cambio de estado, nunca iba a volver a
	 * intentarlo. Dos días después el panel lo seguía sondeando cada hora, lo
	 * seguía viendo caído, y no lo había dicho ni una sola vez.
	 *
	 * Es el mismo razonamiento que el jefe pidió para las asesoras —«ya te
	 * notificamos primera vez… segunda vez… tercera vez» (19:20)— aplicado a
	 * lo que más urge.
	 *
	 * Una vez al día y no una por hora: veinticuatro correos diarios del mismo
	 * sitio caído se convierten en una regla de bandeja a los dos días, y
	 * entonces el aviso de verdad tampoco se lee.
	 */
	private static function recordar_caidas(): void {
		global $wpdb;

		if ( ! SWPK_Correos::encendido( 'sigue_caido' ) ) {
			return;
		}

		$tabla = SWPK_Tablas::tabla( 'monitor' );
		$hoy   = SWPK_Frescura::fmt( 'Y-m-d', current_time( 'mysql' ) );

		$caidos = $wpdb->get_results( "SELECT * FROM {$tabla} WHERE estado = 'abajo'" ) ?: [];

		if ( ! $caidos ) {
			// Y se limpia la cuenta: si no hay nada caído, no hay de qué
			// acordarse. Así un sitio que se cae otra vez dentro de un mes
			// recibe su recordatorio el primer día, no al siguiente.
			delete_option( self::OPCION_RECORDADOS );
			return;
		}

		$recordados = get_option( self::OPCION_RECORDADOS, [] );
		$recordados = is_array( $recordados ) ? $recordados : [];
		$nuevos     = [];

		foreach ( $caidos as $c ) {
			$id    = (string) $c->sitio_id;
			$desde = (string) $c->desde;

			// El día en que se cayó NO lleva recordatorio: ese día ya salió el
			// aviso de caída, y dos correos del mismo sitio el mismo día es
			// justo el ruido que hace que se dejen de leer.
			$el_dia_que_cayo = SWPK_Frescura::fmt( 'Y-m-d', $desde );
			$ya_hoy          = ( $recordados[ $id ] ?? '' ) === $hoy;

			$nuevos[ $id ] = $recordados[ $id ] ?? '';

			if ( $hoy === $el_dia_que_cayo || $ya_hoy ) {
				continue;
			}

			$sitio  = SWPK_Inventario::obtener( $id );
			$nombre = $sitio->nombre_visible ?? $id;
			$cuanto = self::duracion( max( 0, SWPK_Frescura::ahora_ts() - strtotime( $desde ) ) );

			$marcas = [
				'{{sitio}}'    => esc_html( (string) $nombre ),
				'{{url}}'      => esc_html( (string) $c->url ),
				'{{cuanto}}'   => esc_html( $cuanto ),
				'{{desde}}'    => esc_html( SWPK_Frescura::fmt( 'j \d\e F, H:i', $desde ) ),
				'{{revisado}}' => esc_html( SWPK_Frescura::fmt( 'j \d\e F, H:i', (string) $c->ultimo_sondeo ) ),
				'{{motivo}}'   => esc_html( (string) $c->motivo ),
			];

			SWPK_Bitacora::enviar(
				SWPK_Bitacora::CAIDA,
				self::destinatarios_caida(),
				SWPK_Plantillas::asunto(
					'sigue_caido',
					[ '{{sitio}}' => (string) $nombre, '{{cuanto}}' => $cuanto ]
				),
				SWPK_Plantillas::sigue_caido( $marcas ),
				$id
			);

			$nuevos[ $id ] = $hoy;
		}

		update_option( self::OPCION_RECORDADOS, $nuevos, false );
	}

	/**
	 * El aviso de que el agente de un sitio dejó de reportar.
	 *
	 * ─── Por qué esto es un aviso y no una fila más de una pantalla ─────────
	 *
	 * `SWPK_Receptor::callados()` existía desde hace tiempo y se usaba en un
	 * solo sitio: la respuesta de la API. O sea, en ninguno — nadie entra a
	 * mirar la API por gusto. El 25 de noviembre de 2026 el agente de un sitio
	 * llevaba CINCO DÍAS callado y nadie lo sabía.
	 *
	 * Un agente callado no es una caída: el sitio puede estar perfecto y
	 * respondiendo. Es peor en una cosa concreta y mejor en otra. Peor, porque
	 * de ese sitio el panel deja de saber lo que solo sabe por el agente —sus
	 * administradores, la verificación en dos pasos, las normas de dentro— y
	 * lo que enseñe en pantalla es una foto vieja que no se presenta como
	 * vieja. Mejor, porque no urge igual.
	 *
	 * Eso es la regla 3 otra vez: no decir «está bien» cuando lo que pasa es
	 * «hace cinco días que no lo miro».
	 */
	private static function avisar_agentes_callados(): void {
		if ( ! SWPK_Correos::encendido( 'agente_callado' ) ) {
			return;
		}

		$hoy = SWPK_Frescura::fmt( 'Y-m-d', current_time( 'mysql' ) );

		$avisados = get_option( self::OPCION_CALLADOS, [] );
		$avisados = is_array( $avisados ) ? $avisados : [];

		$callados = SWPK_Receptor::callados( self::HORAS_PARA_CALLADO );
		$nuevos   = [];

		foreach ( $callados as $a ) {
			$clave         = (string) $a->sitio_url;
			$nuevos[ $clave ] = $avisados[ $clave ] ?? '';

			if ( ( $avisados[ $clave ] ?? '' ) === $hoy ) {
				continue;
			}

			$ultimo = (string) ( $a->ultimo_reporte ?? '' );
			$cuanto = '' === $ultimo
				? 'desde que se instaló'
				: self::duracion( max( 0, SWPK_Frescura::ahora_ts() - strtotime( $ultimo ) ) );

			$marcas = [
				'{{sitio}}'  => esc_html( (string) ( $a->nombre ?: $a->sitio_url ) ),
				'{{url}}'    => esc_html( (string) $a->sitio_url ),
				'{{cuanto}}' => esc_html( $cuanto ),
				'{{ultimo}}' => esc_html(
					'' === $ultimo ? 'nunca' : SWPK_Frescura::fmt( 'j \d\e F \d\e Y, H:i', $ultimo )
				),
			];

			SWPK_Bitacora::enviar(
				SWPK_Bitacora::CAIDA,
				self::destinatarios_caida(),
				SWPK_Plantillas::asunto( 'agente_callado', [ '{{sitio}}' => (string) ( $a->nombre ?: $a->sitio_url ) ] ),
				SWPK_Plantillas::agente_callado( $marcas ),
				(string) $a->sitio_id
			);

			$nuevos[ $clave ] = $hoy;
		}

		update_option( self::OPCION_CALLADOS, $nuevos, false );
	}

	/** @return array<int,string> */
	private static function destinatarios_soporte(): array {
		$crudo = (string) SWPK_Ajustes::obtener( 'correo_soporte', '' );
		return array_values( array_filter( array_map( 'trim', explode( ',', $crudo ) ) ) );
	}

	/**
	 * A quién se le avisa de una caída: soporte, más quien esté en la lista
	 * aparte de caídas.
	 *
	 * Son dos listas y no una porque son dos cosas distintas: soporte lee el
	 * resumen de todos los días, y de una caída hay que enterarse aunque sea
	 * domingo. El jefe lo pidió con nombre: "y cuando se caiga, agrega a
	 * Luisa" (18:04).
	 *
	 * @return array<int,string>
	 */
	private static function destinatarios_caida(): array {
		$extra = (string) SWPK_Ajustes::obtener( 'correo_caidas', '' );

		return array_values(
			array_unique(
				array_merge(
					self::destinatarios_soporte(),
					array_filter( array_map( 'trim', explode( ',', $extra ) ) )
				)
			)
		);
	}

	public static function duracion( int $segundos ): string {
		if ( $segundos < 60 ) {
			return $segundos . ' segundos';
		}
		if ( $segundos < 3600 ) {
			return sprintf( '%d minutos', (int) round( $segundos / 60 ) );
		}
		$horas   = (int) floor( $segundos / 3600 );
		$minutos = (int) round( ( $segundos % 3600 ) / 60 );
		return $minutos ? sprintf( '%dh %dmin', $horas, $minutos ) : sprintf( '%d horas', $horas );
	}

	/** @return array<int,object> */
	public static function estados(): array {
		global $wpdb;
		$tabla = SWPK_Tablas::tabla( 'monitor' );
		return $wpdb->get_results( "SELECT * FROM {$tabla} ORDER BY FIELD(estado,'abajo','raro','desconocido','arriba','ciego','ausente'), ms DESC" ) ?: [];
	}

	public static function ultimo_sondeo(): array {
		$v = get_option( 'swpk_ultimo_sondeo', [] );
		return is_array( $v ) ? $v : [];
	}

	/**
	 * ¿Está corriendo de verdad?
	 *
	 * WP-Cron no es un reloj: es «lo que se ejecuta cuando alguien pide una
	 * página». En un panel que casi nadie visita, el sondeo puede no ocurrir —
	 * y un monitor que no corre es peor que no tener monitor, porque da falsa
	 * tranquilidad. Por eso esto se comprueba y se dice en pantalla.
	 *
	 * El margen es de dos horas, no de dos minutos, y el motivo es el hosting.
	 * El panel vive en Pantheon, que trae `DISABLE_WP_CRON` puesto de fábrica
	 * y ejecuta el cron UNA VEZ POR HORA. No hay manera de sondear cada cinco
	 * minutos ahí sin montar algo aparte, así que el sondeo es cada hora y el
	 * panel lo dice así. Dos horas sin sondeo son dos vueltas perdidas: eso ya
	 * no es el reloj yendo flojo, es que no está corriendo.
	 */
	public static function esta_al_dia(): bool {
		$u = self::ultimo_sondeo();
		if ( empty( $u['cuando'] ) ) {
			return false;
		}
		return ( SWPK_Frescura::ahora_ts() - strtotime( (string) $u['cuando'] ) ) < self::MARGEN;
	}
}
