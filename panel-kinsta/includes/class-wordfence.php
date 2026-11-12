<?php
/**
 * El feed de vulnerabilidades de Wordfence Intelligence.
 *
 * ─── Para qué sirve esto, que es la pregunta justa ──────────────────────────
 *
 * Kinsta nos dice, por cada plugin instalado, un sí o un no:
 * is_version_vulnerable. Nada más. No dice cuál es la falla, ni qué tan grave
 * es, ni qué versión la arregla, ni qué le puede pasar al cliente.
 *
 * Y lo que el jefe pidió es justamente lo otro: "hay que mandarle un reporte a
 * las asesoras, decirle: oye, este sitio está crítico, deberíamos de actualizar
 * módulo 1, módulo 2, módulo 3. Actualmente está en su versión esta y lo
 * pasamos a esta versión" (02:06), y "algo que ellas puedan copiar y pegar en su
 * cotización" (02:22). Un sí/no no se copia en una cotización. Hace falta el
 * POR QUÉ.
 *
 * Wordfence Intelligence es una lista pública de todas las vulnerabilidades
 * conocidas de plugins y temas de WordPress, con nombre, CVE, severidad (0 a
 * 10), descripción y qué versión la corrige. Es gratis, uso comercial
 * permitido, y solo pide una cuenta para una llave.
 *
 * En una frase: Kinsta dice "este carro tiene una luz encendida"; Wordfence
 * dice "es la banda de distribución, se puede romper en marcha, y se arregla
 * con esta pieza".
 *
 * ─── Dos detalles técnicos que condicionan el diseño ────────────────────────
 *
 * 1. No se puede consultar por plugin. Los dos endpoints devuelven el feed
 *    COMPLETO y no aceptan parámetros. Entonces: una descarga al día, se
 *    guarda local, y el cruce se hace de nuestro lado. Para este proyecto
 *    conviene así.
 * 2. El archivo es grande (decenas de MB). json_decode del archivo entero
 *    reventaría la memoria de PHP. Se lee en trozos con un separador propio
 *    que va entregando un registro a la vez.
 *
 * Atribución obligatoria por licencia: donde se muestren estos datos —el panel
 * Y los correos— va el enlace al registro de la vulnerabilidad y el aviso de
 * copyright de Defiant. Está en SWPK_Wordfence::atribucion().
 * ────────────────────────────────────────────────────────────────────────────
 */

defined( 'ABSPATH' ) || exit;

class SWPK_Wordfence {

	private const URL_PRODUCCION = 'https://www.wordfence.com/api/intelligence/v3/vulnerabilities/production';

	/**
	 * ─── El freno ───────────────────────────────────────────────────────────
	 *
	 * El 11 de noviembre de 2026, probando, se descargó el feed cuatro veces en
	 * cuarenta minutos. Wordfence mandó un aviso por correo advirtiendo que
	 * seguir así puede terminar en la **suspensión de la llave**.
	 *
	 * El feed se publica una vez al día: pedirlo más seguido no trae nada nuevo.
	 * Así que el límite va aquí, en el código, y no en que alguien se acuerde:
	 *
	 *  - No se vuelve a pedir si hace menos de MINIMO_ENTRE_DESCARGAS que se
	 *    bajó bien. No es un error: simplemente no hace falta.
	 *  - Si Wordfence contesta 429, se anota hasta cuándo no insistir, y la
	 *    espera se va duplicando. Insistir contra un 429 es justo lo que lleva
	 *    a que suspendan la llave.
	 */
	private const MINIMO_ENTRE_DESCARGAS = 20 * HOUR_IN_SECONDS;
	private const ESPERA_INICIAL         = 6 * HOUR_IN_SECONDS;
	private const ESPERA_MAXIMA          = 48 * HOUR_IN_SECONDS;

	private const OPCION_ESPERA = 'swpk_wordfence_no_antes_de';

	/** Hasta cuándo no hay que volver a pedirle nada a Wordfence. */
	public static function esperar_hasta(): int {
		return (int) get_option( self::OPCION_ESPERA, 0 );
	}

	private static function anotar_429(): void {
		$previa = (int) get_option( 'swpk_wordfence_espera_previa', 0 );
		$espera = $previa ? min( $previa * 2, self::ESPERA_MAXIMA ) : self::ESPERA_INICIAL;

		update_option( 'swpk_wordfence_espera_previa', $espera, false );
		update_option( self::OPCION_ESPERA, time() + $espera, false );
	}

	private static function limpiar_espera(): void {
		delete_option( self::OPCION_ESPERA );
		delete_option( 'swpk_wordfence_espera_previa' );
	}

	/** Cuándo sería la próxima descarga, para enseñarlo en pantalla. */
	public static function proxima_descarga(): int {
		$espera = self::esperar_hasta();
		if ( $espera > time() ) {
			return $espera;
		}

		$ultima = self::ultima_en_epoch();

		return $ultima ? $ultima + self::MINIMO_ENTRE_DESCARGAS : time();
	}

	public static function atribucion(): string {
		return sprintf(
			'Datos de vulnerabilidades: <a href="%s" target="_blank" rel="noopener">Wordfence Intelligence</a> (Defiant, Inc.), bajo su <a href="%s" target="_blank" rel="noopener">licencia de uso</a>. Identificadores CVE: MITRE.',
			'https://www.wordfence.com/threat-intel/',
			'https://www.wordfence.com/wti-community-edition-terms-and-conditions/'
		);
	}

	/**
	 * Descarga el feed y guarda solo las vulnerabilidades del software que
	 * tenemos instalado. Sin ese filtro la tabla crecería a decenas de miles de
	 * filas que nunca vamos a consultar.
	 */
	public static function actualizar(): int|WP_Error {
		// Esto NO depende del modo de Kinsta, a propósito. Son dos fuentes
		// independientes: se puede estar trabajando con un inventario de prueba
		// y aun así cruzar contra las vulnerabilidades reales. De hecho es la
		// mejor combinación para enseñar el panel, porque los CVE que salen son
		// de verdad. Lo único que decide es si hay llave.
		$llave = (string) SWPK_Ajustes::obtener( 'wordfence_key' );

		if ( '' === $llave ) {
			return SWPK_Ajustes::en_modo_demo()
				? self::cargar_demo()
				: new WP_Error(
					'swpk_sin_wordfence',
					'Falta la llave de Wordfence Intelligence. Es una cuenta gratuita en wordfence.com, sección Integrations.'
				);
		}

		// Primer freno: un 429 reciente. Insistir es lo que lleva a la suspensión.
		$espera = self::esperar_hasta();
		if ( $espera > time() ) {
			return new WP_Error(
				'swpk_wf_en_espera',
				sprintf(
					'Wordfence pidió esperar. No se vuelve a intentar hasta el %s.',
					SWPK_Frescura::fmt( 'j \d\e F, H:i', $espera )
				)
			);
		}

		// Segundo freno: ya se bajó hoy. El feed se publica una vez al día.
		$ultima = self::ultima_en_epoch();
		if ( $ultima && ( time() - $ultima ) < self::MINIMO_ENTRE_DESCARGAS ) {
			return new WP_Error(
				'swpk_wf_reciente',
				sprintf(
					'El feed ya se descargó el %s. Se publica una vez al día, así que no hace falta volver a pedirlo todavía.',
					SWPK_Frescura::fmt( 'j \d\e F \a \l\a\s H:i', $ultima )
				)
			);
		}

		$slugs = self::slugs_instalados();
		if ( ! $slugs ) {
			return new WP_Error( 'swpk_sin_inventario', 'Todavía no hay inventario: primero hay que sincronizar los sitios.' );
		}

		$destino   = wp_tempnam( 'swpk-wordfence' );
		$respuesta = wp_remote_get(
			self::URL_PRODUCCION,
			[
				'timeout'  => 180,
				'stream'   => true,
				'filename' => $destino,
				'headers'  => [
					'Authorization' => 'Bearer ' . $llave,
					'Accept'        => 'application/json',
				],
			]
		);

		if ( is_wp_error( $respuesta ) ) {
			@unlink( $destino );
			return $respuesta;
		}

		$codigo = (int) wp_remote_retrieve_response_code( $respuesta );
		if ( 429 === $codigo ) {
			@unlink( $destino );
			self::anotar_429();

			return new WP_Error(
				'swpk_wf_limite',
				sprintf(
					'Wordfence devolvió 429 (demasiadas peticiones). No se volverá a intentar hasta el %s. Insistir puede hacer que suspendan la llave.',
					SWPK_Frescura::fmt( 'j \d\e F, H:i', self::esperar_hasta() )
				)
			);
		}
		if ( $codigo < 200 || $codigo >= 300 ) {
			@unlink( $destino );
			return new WP_Error( 'swpk_wf_http', sprintf( 'Wordfence devolvió %d al descargar el feed.', $codigo ) );
		}

		$guardadas = 0;
		self::recorrer_feed(
			$destino,
			static function ( array $registro ) use ( $slugs, &$guardadas ): void {
				if ( self::guardar_si_interesa( $registro, $slugs ) ) {
					++$guardadas;
				}
			}
		);

		@unlink( $destino );
		self::limpiar_espera();
		update_option( 'swpk_wordfence_actualizado', current_time( 'mysql' ), false );

		return $guardadas;
	}

	/**
	 * Recorre el feed entregando un registro a la vez, sin cargarlo entero.
	 *
	 * La raíz del feed es un objeto cuyas llaves son UUID de Wordfence. Este
	 * lector va contando llaves y comillas para saber dónde empieza y termina
	 * cada valor de primer nivel, y solo decodifica ese trozo. El consumo de
	 * memoria es el de un registro, no el del archivo.
	 *
	 * @param callable(array):void $por_cada
	 */
	public static function recorrer_feed( string $ruta, callable $por_cada ): void {
		$fh = fopen( $ruta, 'rb' );
		if ( ! $fh ) {
			return;
		}

		$profundidad = 0;
		$en_texto    = false;
		$escapado    = false;
		$buffer      = '';
		$capturando  = false;

		while ( ! feof( $fh ) ) {
			$trozo = fread( $fh, 65536 );
			if ( false === $trozo ) {
				break;
			}

			$largo = strlen( $trozo );
			for ( $i = 0; $i < $largo; $i++ ) {
				$c = $trozo[ $i ];

				if ( $capturando ) {
					$buffer .= $c;
				}

				if ( $en_texto ) {
					if ( $escapado ) {
						$escapado = false;
					} elseif ( '\\' === $c ) {
						$escapado = true;
					} elseif ( '"' === $c ) {
						$en_texto = false;
					}
					continue;
				}

				if ( '"' === $c ) {
					$en_texto = true;
					continue;
				}

				if ( '{' === $c || '[' === $c ) {
					++$profundidad;
					if ( 2 === $profundidad && '{' === $c && ! $capturando ) {
						$capturando = true;
						$buffer     = '{';
					}
					continue;
				}

				if ( '}' === $c || ']' === $c ) {
					--$profundidad;
					if ( 1 === $profundidad && $capturando ) {
						$capturando = false;
						$registro   = json_decode( $buffer, true );
						$buffer     = '';
						if ( is_array( $registro ) ) {
							$por_cada( $registro );
						}
					}
				}
			}
		}

		fclose( $fh );
	}

	/** @param array<int,string> $slugs */
	private static function guardar_si_interesa( array $registro, array $slugs ): bool {
		$software = $registro['software'] ?? [];
		if ( ! is_array( $software ) ) {
			return false;
		}

		$guardo = false;

		foreach ( $software as $sw ) {
			$slug = (string) ( $sw['slug'] ?? '' );
			if ( '' === $slug || ! in_array( $slug, $slugs, true ) ) {
				continue;
			}

			self::guardar(
				[
					'wf_id'                => (string) ( $registro['id'] ?? '' ),
					'titulo'               => (string) ( $registro['title'] ?? '' ),
					'tipo_software'        => 'theme' === ( $sw['type'] ?? '' ) ? 'tema' : (string) ( $sw['type'] ?? 'plugin' ),
					'slug'                 => $slug,
					'cve'                  => (string) ( $registro['cve'] ?? '' ),
					'cve_enlace'           => (string) ( $registro['cve_link'] ?? '' ),
					'cvss_score'           => isset( $registro['cvss']['score'] ) ? (float) $registro['cvss']['score'] : null,
					'cvss_rating'          => (string) ( $registro['cvss']['rating'] ?? '' ),
					// El vector dice, en clave, si hace falta cuenta para explotarlo y
					// si alguien tiene que hacer clic. Es lo más concreto que da el feed.
					'cvss_vector'          => (string) ( $registro['cvss']['vector'] ?? '' ),
					'informativa'          => ! empty( $registro['informational'] ) ? 1 : 0,
					'descripcion'          => (string) ( $registro['description'] ?? '' ),
					'remediacion'          => (string) ( $sw['remediation'] ?? '' ),
					'parcheada'            => ! empty( $sw['patched'] ) ? 1 : 0,
					'rangos'               => array_values( (array) ( $sw['affected_versions'] ?? [] ) ),
					'versiones_parcheadas' => (array) ( $sw['patched_versions'] ?? [] ),
					'publicada'            => (string) ( $registro['published'] ?? '' ),
				]
			);

			$guardo = true;
		}

		return $guardo;
	}

	private static function guardar( array $v ): void {
		global $wpdb;
		$tabla = SWPK_Tablas::tabla( 'vulnerabilidades' );

		$fila = [
			'wf_id'                => $v['wf_id'],
			'titulo'               => $v['titulo'],
			'tipo_software'        => $v['tipo_software'],
			'slug'                 => $v['slug'],
			'cve'                  => $v['cve'],
			'cve_enlace'           => $v['cve_enlace'] ?? '',
			'cvss_score'           => $v['cvss_score'],
			'cvss_rating'          => $v['cvss_rating'],
			'cvss_vector'          => (string) ( $v['cvss_vector'] ?? '' ),
			'informativa'          => (int) $v['informativa'],
			'descripcion'          => $v['descripcion'],
			'remediacion'          => $v['remediacion'],
			'parcheada'            => (int) $v['parcheada'],
			'rangos'               => wp_json_encode( $v['rangos'] ),
			'versiones_parcheadas' => wp_json_encode( $v['versiones_parcheadas'] ),
			'publicada'            => self::fecha( (string) $v['publicada'] ),
		];

		$existe = $wpdb->get_var(
			$wpdb->prepare( "SELECT id FROM {$tabla} WHERE wf_id = %s AND slug = %s", $v['wf_id'], $v['slug'] )
		);

		if ( $existe ) {
			$wpdb->update( $tabla, $fila, [ 'id' => (int) $existe ] );
		} else {
			$wpdb->insert( $tabla, $fila );
		}
	}

	/** Las fechas del feed vienen "YYYY-MM-DD hh:mm:ss" en UTC. */
	private static function fecha( string $crudo ): ?string {
		$crudo = trim( $crudo );
		if ( '' === $crudo ) {
			return null;
		}
		$ts = strtotime( $crudo . ' UTC' );
		return $ts ? gmdate( 'Y-m-d H:i:s', $ts ) : null;
	}

	/** @return array<int,string> */
	public static function slugs_instalados(): array {
		global $wpdb;
		$tabla = SWPK_Tablas::tabla( 'fotos' );
		$slugs = $wpdb->get_col( "SELECT DISTINCT slug FROM {$tabla}" ) ?: [];
		return array_values( array_filter( array_map( 'strval', $slugs ) ) );
	}

	/** @return array<int,object> */
	public static function para_slug( string $slug ): array {
		global $wpdb;
		$tabla = SWPK_Tablas::tabla( 'vulnerabilidades' );
		return $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$tabla} WHERE slug = %s AND informativa = 0", $slug )
		) ?: [];
	}

	/**
	 * La última descarga como instante real, no como texto de hora local.
	 *
	 * Hace falta porque esta clase mezcla dos relojes sin remedio: la fecha de
	 * la descarga se guarda en hora local (current_time) y el freno de espera
	 * en segundos de verdad (time()). Compararlas directo funciona solo
	 * mientras el sitio esté en UTC; con la zona de Guatemala puesta, el freno
	 * de 20 horas se abriría seis horas antes de tiempo, que es justo lo que
	 * puede terminar con la llave suspendida.
	 */
	private static function ultima_en_epoch(): int {
		$texto = self::ultima_actualizacion();
		if ( '' === $texto ) {
			return 0;
		}

		return (int) strtotime( get_gmt_from_date( $texto ) );
	}

	public static function ultima_actualizacion(): string {
		return (string) get_option( 'swpk_wordfence_actualizado', '' );
	}

	/** Carga las vulnerabilidades de prueba cuando el panel corre en modo demo. */
	private static function cargar_demo(): int {
		$fuente = new SWPK_Fuente_Demo();
		$n      = 0;

		foreach ( $fuente->vulnerabilidades_demo() as $v ) {
			$v['cve_enlace'] = '';
			$v['publicada']  = gmdate( 'Y-m-d H:i:s', time() - 86400 * 20 );
			self::guardar( $v );
			++$n;
		}

		update_option( 'swpk_wordfence_actualizado', current_time( 'mysql' ), false );

		return $n;
	}
}
