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

	public const HOOK      = 'swpk_sondeo';
	public const INTERVALO = 'swpk_cinco_minutos';

	private const TIMEOUT        = 12;
	private const CONCURRENCIA   = 15;
	private const FALLOS_PARA_AVISAR = 2;
	private const LENTO_MS       = 5000;

	/** Frases que significan "caído" aunque el servidor conteste 200. */
	private const SENALES_DE_ERROR = [
		'Error establishing a database connection',
		'Error al establecer una conexión con la base de datos',
		'There has been a critical error on this website',
		'Ha ocurrido un error crítico en esta web',
		'Ha habido un error crítico en esta web',
	];

	public static function init(): void {
		add_filter( 'cron_schedules', [ __CLASS__, 'intervalo' ] );
		add_action( self::HOOK, [ __CLASS__, 'sondear_todos' ] );
	}

	public static function intervalo( array $horarios ): array {
		$horarios[ self::INTERVALO ] = [
			'interval' => 5 * MINUTE_IN_SECONDS,
			'display'  => 'Cada cinco minutos (Panel Kinsta)',
		];
		return $horarios;
	}

	public static function programar(): void {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + 60, self::INTERVALO, self::HOOK );
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

		foreach ( SWPK_Inventario::todos() as $sitio ) {
			$dominio = trim( (string) $sitio->dominio );
			if ( '' === $dominio ) {
				continue;
			}
			$objetivos[ (string) $sitio->sitio_id ] = self::url( $dominio );
		}

		if ( ! $objetivos ) {
			return [ 'sondeados' => 0, 'abajo' => 0, 'cambios' => 0 ];
		}

		$resultados = self::sondear( $objetivos );

		$abajo   = 0;
		$cambios = 0;

		foreach ( $resultados as $sitio_id => $r ) {
			if ( 'abajo' === $r['estado'] ) {
				++$abajo;
			}
			$cambios += self::guardar( (string) $sitio_id, $r ) ? 1 : 0;
		}

		update_option(
			'swpk_ultimo_sondeo',
			[ 'cuando' => current_time( 'mysql' ), 'sondeados' => count( $resultados ), 'abajo' => $abajo ],
			false
		);

		return [ 'sondeados' => count( $resultados ), 'abajo' => $abajo, 'cambios' => $cambios ];
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
	 * Con 107 sitios, uno detrás de otro no funciona: si varios tardan en
	 * contestar, la corrida de cinco minutos no termina antes de la siguiente.
	 * Por eso va con curl en paralelo, de quince en quince.
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

		if ( $codigo >= 400 ) {
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

		// Solo se declara caído tras dos fallos seguidos. Mientras tanto el
		// estado guardado sigue siendo el anterior: no se avisa por un parpadeo.
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

		$soporte = self::destinatarios_soporte();
		if ( ! $soporte ) {
			return;
		}

		SWPK_Bitacora::enviar(
			SWPK_Bitacora::CAIDA,
			$soporte,
			sprintf( '[Caído] %s', $nombre ),
			sprintf(
				'<p><strong>%s no está respondiendo.</strong></p>'
				. '<p>Dirección: <a href="%s">%s</a><br>Detectado: %s<br>Qué pasó: %s</p>'
				. '<p>El monitor revisa cada cinco minutos. Cuando vuelva, les llega otro correo con cuánto estuvo caído.</p>',
				esc_html( $nombre ),
				esc_url( (string) $r['url'] ),
				esc_html( (string) $r['url'] ),
				esc_html( current_time( 'j \d\e F, H:i' ) ),
				esc_html( (string) $r['motivo'] )
			),
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

		$soporte = self::destinatarios_soporte();
		if ( ! $soporte ) {
			return;
		}

		SWPK_Bitacora::enviar(
			SWPK_Bitacora::CAIDA,
			$soporte,
			sprintf( '[Repuesto] %s', $nombre ),
			sprintf(
				'<p><strong>%s volvió a responder.</strong></p><p>Estuvo caído %s.</p>',
				esc_html( $nombre ),
				esc_html( $cuanto )
			),
			$sitio_id
		);
	}

	/** @return array<int,string> */
	private static function destinatarios_soporte(): array {
		$crudo = (string) SWPK_Ajustes::obtener( 'correo_soporte', '' );
		return array_values( array_filter( array_map( 'trim', explode( ',', $crudo ) ) ) );
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
		return $wpdb->get_results( "SELECT * FROM {$tabla} ORDER BY FIELD(estado,'abajo','raro','desconocido','arriba'), ms DESC" ) ?: [];
	}

	public static function ultimo_sondeo(): array {
		$v = get_option( 'swpk_ultimo_sondeo', [] );
		return is_array( $v ) ? $v : [];
	}

	/**
	 * ¿Está corriendo de verdad?
	 *
	 * WP-Cron se dispara con las visitas al sitio. En un panel que casi nadie
	 * visita, eso significa que el sondeo puede no ocurrir — y un monitor que
	 * no corre es peor que no tener monitor, porque da falsa tranquilidad.
	 * Por eso esto se comprueba y se dice en pantalla.
	 */
	public static function esta_al_dia(): bool {
		$u = self::ultimo_sondeo();
		if ( empty( $u['cuando'] ) ) {
			return false;
		}
		return ( time() - strtotime( (string) $u['cuando'] ) ) < 20 * MINUTE_IN_SECONDS;
	}
}
