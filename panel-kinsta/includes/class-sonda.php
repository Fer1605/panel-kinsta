<?php
/**
 * Lo que se puede saber de un sitio sin entrar en él.
 *
 * ─── Por qué hace falta esto ────────────────────────────────────────────────
 *
 * Hasta ahora todas las normas las medía el agente, desde dentro del sitio, y
 * el panel solo interpretaba su reporte (ver SWPK_Normas::medir). Eso está bien
 * para lo que únicamente se ve desde dentro —si el editor de código está
 * abierto, qué usuarios hay— pero es la fuente equivocada para tres cosas, y en
 * un caso daba un resultado falso en producción:
 *
 *   1. XML-RPC. El agente leía el filtro `xmlrpc_enabled` de WordPress, que
 *      dice si WordPress *serviría* XML-RPC. No dice nada de si la petición
 *      llega a WordPress. En Kinsta no llega: Nginx devuelve 403 antes de que
 *      PHP arranque. Comprobado el 18/11/2026 en 14 sitios de la cartera: 403
 *      en los 14. Así que el panel podía estar diciendo «XML-RPC abierto» de un
 *      sitio donde nadie puede entrar por ahí.
 *
 *   2. PHP en la carpeta de subidas. El agente sí hace una prueba de verdad
 *      —escribe un .php, lo pide, lo borra— pero en Kinsta la regla de Nginx
 *      devuelve 403, y el agente trataba cualquier respuesta que no fuera 2xx
 *      como «no se pudo comprobar». Un 403 ahí es el mejor resultado posible:
 *      el servidor se niega incluso a entregar el archivo. Mientras se contara
 *      como «no sé», la norma se quedaba en «sin dato» para siempre.
 *
 *   3. Los nombres de usuario. La norma «no existe un usuario admin» vale poco
 *      si el sitio publica los nombres en /wp-json/wp/v2/users. Medido: abierto
 *      en 13 de 14, y en varios aparece nuestra propia cuenta de mantenimiento.
 *
 * ─── Lo que esto NO es ──────────────────────────────────────────────────────
 *
 * No es un escáner de vulnerabilidades ni una prueba de penetración. Son cuatro
 * peticiones sin credenciales a un sitio público: tres GET y un POST a
 * xmlrpc.php pidiendo la lista de métodos, que no cambia nada. Es lo mismo que
 * haría cualquiera desde un navegador. No se intenta entrar, no se prueban
 * contraseñas, no se toca nada del sitio.
 *
 * ─── La regla que decide si esto sirve o es ruido ───────────────────────────
 *
 * Nunca concluir por el código HTTP solo. Un 403 de Cloudflare y un «ese
 * archivo no existe» se parecen desde fuera, y muchos sitios contestan 200 con
 * la página de error del tema. Así que cada comprobación exige que el contenido
 * calce, y cuando no se puede distinguir, el resultado es «no se sabe» y no
 * «aprobado». Inventarse una buena noticia es peor que no tener el dato.
 */

defined( 'ABSPATH' ) || exit;

class SWPK_Sonda {

	/** Un transient por sitio. Vive 25 horas: hasta la corrida de mañana. */
	private const CACHE = 'swpk_sonda_';
	private const VIDA  = 25 * HOUR_IN_SECONDS;

	private const TIMEOUT      = 12;
	private const CONCURRENCIA = 15;

	/** El cuerpo del POST a xmlrpc.php. Pide la lista de métodos y nada más. */
	private const XMLRPC_CUERPO = '<?xml version="1.0"?><methodCall><methodName>system.listMethods</methodName><params></params></methodCall>';

	/**
	 * Lo que la sonda averiguó de un sitio, o un arreglo vacío si nunca se le
	 * pasó o si ya caducó.
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
	 * @return array{sondeados:int,xmlrpc_abierto:int,usuarios_expuestos:int}
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

		$resumen = [ 'sondeados' => count( $r ), 'xmlrpc_abierto' => 0, 'usuarios_expuestos' => 0 ];

		foreach ( $r as $uno ) {
			if ( 'responde' === ( $uno['xmlrpc'] ?? '' ) ) {
				++$resumen['xmlrpc_abierto'];
			}
			if ( (int) ( $uno['usuarios_rest'] ?? 0 ) > 0 ) {
				++$resumen['usuarios_expuestos'];
			}
		}

		update_option(
			'swpk_ultima_sonda',
			[ 'cuando' => current_time( 'mysql' ) ] + $resumen,
			false
		);

		return $resumen;
	}

	/**
	 * El motor. Arma todas las peticiones de todos los sitios y las lanza en
	 * paralelo, igual que SWPK_Monitor::sondear() — cuatro por sitio y 108
	 * sitios es demasiado para hacerlo en fila.
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

		// Un nombre al azar por corrida: así la respuesta no puede venir de una
		// caché de una corrida anterior.
		$azar = wp_generate_password( 10, false, false );

		$peticiones = [];

		foreach ( $objetivos as $sitio_id => $dominio ) {
			$base = self::base( (string) $dominio );

			$peticiones[] = [ 'sitio' => (string) $sitio_id, 'que' => 'xmlrpc', 'url' => $base . 'xmlrpc.php', 'post' => self::XMLRPC_CUERPO ];
			$peticiones[] = [ 'sitio' => (string) $sitio_id, 'que' => 'usuarios_rest', 'url' => $base . 'wp-json/wp/v2/users', 'post' => null ];
			$peticiones[] = [ 'sitio' => (string) $sitio_id, 'que' => 'uploads_php', 'url' => $base . 'wp-content/uploads/swpk-' . $azar . '.php', 'post' => null ];
			$peticiones[] = [ 'sitio' => (string) $sitio_id, 'que' => 'uploads_txt', 'url' => $base . 'wp-content/uploads/swpk-' . $azar . '.txt', 'post' => null ];
		}

		$crudo = self::lanzar( $peticiones );

		// Agrupar por sitio y traducir a veredictos.
		$por_sitio = [];

		foreach ( $crudo as $fila ) {
			$por_sitio[ $fila['sitio'] ][ $fila['que'] ] = $fila;
		}

		$salida = [];

		foreach ( $por_sitio as $sitio_id => $partes ) {
			$uno = [
				'cuando'        => current_time( 'mysql' ),
				'xmlrpc'        => self::leer_xmlrpc( $partes['xmlrpc'] ?? [] ),
				'usuarios_rest' => self::leer_usuarios( $partes['usuarios_rest'] ?? [] ),
				'uploads_regla' => self::leer_uploads( $partes['uploads_php'] ?? [], $partes['uploads_txt'] ?? [] ),
			];

			set_transient( self::CACHE . $sitio_id, $uno, self::VIDA );
			$salida[ $sitio_id ] = $uno;
		}

		return $salida;
	}

	private static function base( string $dominio ): string {
		if ( str_starts_with( $dominio, 'http://' ) || str_starts_with( $dominio, 'https://' ) ) {
			return trailingslashit( $dominio );
		}

		return 'https://' . trailingslashit( $dominio );
	}

	/**
	 * @param array<int,array<string,mixed>> $peticiones
	 * @return array<int,array<string,mixed>>
	 */
	private static function lanzar( array $peticiones ): array {
		$hechas = [];

		foreach ( array_chunk( $peticiones, self::CONCURRENCIA ) as $tanda ) {
			$multi  = curl_multi_init();
			$mangos = [];

			foreach ( $tanda as $i => $p ) {
				$ch = curl_init();

				$opciones = [
					CURLOPT_URL            => $p['url'],
					CURLOPT_RETURNTRANSFER => true,
					// Sin seguir redirecciones: un 301 a la portada es un dato,
					// y seguirlo convertiría «no existe» en «200 con HTML».
					CURLOPT_FOLLOWLOCATION => false,
					CURLOPT_TIMEOUT        => self::TIMEOUT,
					CURLOPT_CONNECTTIMEOUT => 8,
					CURLOPT_SSL_VERIFYPEER => true,
					CURLOPT_USERAGENT      => 'PanelKinsta/' . SWPK_VERSION . ' (revision de configuracion)',
					// 24 KB alcanzan para ver si el cuerpo calza. Nadie necesita
					// bajarse la lista entera de usuarios de un sitio.
					CURLOPT_RANGE          => '0-24576',
				];

				if ( null !== $p['post'] ) {
					$opciones[ CURLOPT_POST ]       = true;
					$opciones[ CURLOPT_POSTFIELDS ] = $p['post'];
					$opciones[ CURLOPT_HTTPHEADER ] = [ 'Content-Type: text/xml' ];
					// Un POST con Range no tiene sentido y algunos servidores
					// lo rechazan.
					unset( $opciones[ CURLOPT_RANGE ] );
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
					'sitio'  => $tanda[ $i ]['sitio'],
					'que'    => $tanda[ $i ]['que'],
					'codigo' => (int) curl_getinfo( $ch, CURLINFO_RESPONSE_CODE ),
					'cuerpo' => (string) curl_multi_getcontent( $ch ),
					'error'  => $errores[ spl_object_id( $ch ) ] ?? (int) curl_errno( $ch ),
				];

				curl_multi_remove_handle( $multi, $ch );
				curl_close( $ch );
			}

			curl_multi_close( $multi );
		}

		return $hechas;
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

		// Llegó a WordPress. Si contestó con una falla, la puerta está cerrada
		// desde dentro; si contestó la lista, está abierta.
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

		if ( 200 !== (int) $p['codigo'] ) {
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
	 * ¿El servidor tiene una regla que no deja servir .php en /uploads?
	 *
	 * Se piden dos archivos que no existen, uno .php y uno .txt, con el mismo
	 * nombre al azar. Si el .php da 403 y el .txt no, hay una regla que mira la
	 * extensión. Si los dos dan lo mismo, no hay regla y lo único que protege
	 * la carpeta es que PHP no esté configurado para ejecutar ahí — y eso solo
	 * lo prueba el agente, escribiendo un archivo de verdad.
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
}
