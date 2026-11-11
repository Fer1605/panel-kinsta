<?php
/**
 * El motor de riesgo: convierte datos en una frase que se puede copiar.
 *
 * Cruza lo que hay instalado (foto del día) con el feed de Wordfence y decide
 * qué tan urgente es cada cosa. La severidad NO se inventa: se usa el rating
 * que publica Wordfence (None, Low, Medium, High, Critical), porque inventar
 * cortes propios es la forma más rápida de perder credibilidad cuando el
 * cliente pregunta de dónde salió el número.
 */

defined( 'ABSPATH' ) || exit;

class SWPK_Riesgo {

	public const CRITICO = 'critico';
	public const ALTO    = 'alto';
	public const MEDIO   = 'medio';
	public const BAJO    = 'bajo';
	public const AL_DIA  = 'al_dia';

	public static function nombres(): array {
		return [
			self::CRITICO => 'Crítico',
			self::ALTO    => 'Alto',
			self::MEDIO   => 'Medio',
			self::BAJO    => 'Bajo',
			self::AL_DIA  => 'Al día',
		];
	}

	public static function colores(): array {
		return [
			self::CRITICO => '#b32d2e',
			self::ALTO    => '#d63638',
			self::MEDIO   => '#dba617',
			self::BAJO    => '#2271b1',
			self::AL_DIA  => '#008a20',
		];
	}

	private static function de_rating( string $rating ): string {
		return match ( strtolower( $rating ) ) {
			'critical' => self::CRITICO,
			'high'     => self::ALTO,
			'medium'   => self::MEDIO,
			'low'      => self::BAJO,
			default    => self::BAJO,
		};
	}

	private static function peso( string $nivel ): int {
		return match ( $nivel ) {
			self::CRITICO => 4,
			self::ALTO    => 3,
			self::MEDIO   => 2,
			self::BAJO    => 1,
			default       => 0,
		};
	}

	/**
	 * Hallazgos de un sitio: qué tiene instalado que esté vulnerable o atrasado,
	 * con el por qué y la versión que lo arregla.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function hallazgos( string $sitio_id, string $fecha = '' ): array {
		global $wpdb;

		$tabla = SWPK_Tablas::tabla( 'fotos' );
		$fecha = '' !== $fecha ? $fecha : (string) $wpdb->get_var( "SELECT MAX(fecha) FROM {$tabla}" );

		if ( '' === $fecha ) {
			return [];
		}

		$filas = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$tabla} WHERE sitio_id = %s AND fecha = %s", $sitio_id, $fecha )
		) ?: [];

		$hallazgos = [];

		foreach ( $filas as $fila ) {
			$vulns = self::vulnerabilidades_que_aplican( $fila->slug, $fila->version );

			if ( $vulns ) {
				$peor = self::BAJO;
				foreach ( $vulns as $v ) {
									$nivel = self::de_rating( (string) $v->cvss_rating );
					if ( self::peso( $nivel ) > self::peso( $peor ) ) {
						$peor = $nivel;
					}
				}

				$hallazgos[] = [
					'nivel'              => $peor,
					'motivo'             => 'vulnerabilidad',
					'slug'               => $fila->slug,
					'titulo'             => $fila->titulo,
					'version'            => $fila->version,
					'version_disponible' => $fila->version_disponible,
					'vulnerabilidades'   => $vulns,
					'auto_update'        => $fila->auto_update,
				];
				continue;
			}

			// Kinsta dice que la versión instalada es vulnerable pero el feed no
			// nos dio el detalle. Se reporta igual: el dato de Kinsta vale, solo
			// que sin el "por qué".
			if ( $fila->es_vulnerable ) {
				$hallazgos[] = [
					'nivel'              => self::ALTO,
					'motivo'             => 'vulnerable_sin_detalle',
					'slug'               => $fila->slug,
					'titulo'             => $fila->titulo,
					'version'            => $fila->version,
					'version_disponible' => $fila->version_disponible,
					'vulnerabilidades'   => [],
					'auto_update'        => $fila->auto_update,
				];
				continue;
			}

			if ( $fila->hay_actualizacion ) {
				$hallazgos[] = [
					'nivel'              => self::BAJO,
					'motivo'             => 'actualizacion_pendiente',
					'slug'               => $fila->slug,
					'titulo'             => $fila->titulo,
					'version'            => $fila->version,
					'version_disponible' => $fila->version_disponible,
					'vulnerabilidades'   => [],
					'auto_update'        => $fila->auto_update,
				];
			}
		}

		usort(
			$hallazgos,
			static fn( $a, $b ) => self::peso( $b['nivel'] ) <=> self::peso( $a['nivel'] )
		);

		return $hallazgos;
	}

	/** @return array<int,object> */
	public static function vulnerabilidades_que_aplican( string $slug, string $version ): array {
		$aplican = [];

		foreach ( SWPK_Wordfence::para_slug( $slug ) as $v ) {
			$rangos = json_decode( (string) $v->rangos, true );
			$rangos = is_array( $rangos ) ? $rangos : [];

			foreach ( $rangos as $rango ) {
				if ( is_array( $rango ) && SWPK_Versiones::en_rango( $version, $rango ) ) {
					$aplican[] = $v;
					break;
				}
			}
		}

		return $aplican;
	}

	/** El nivel de riesgo de un sitio: el peor de sus hallazgos. */
	public static function nivel_del_sitio( string $sitio_id ): string {
		$peor = self::AL_DIA;

		foreach ( self::hallazgos( $sitio_id ) as $h ) {
			if ( self::peso( $h['nivel'] ) > self::peso( $peor ) ) {
				$peor = $h['nivel'];
			}
		}

		return $peor;
	}

	/**
	 * ¿El plugin fue retirado del repositorio de WordPress?
	 *
	 * Un plugin retirado no va a recibir parches nunca más, así que una
	 * vulnerabilidad en él no se arregla actualizando: hay que cambiarlo. Es
	 * una conversación distinta con el cliente y vale distinguirla.
	 * Se cachea 12 horas para no castigar a api.wordpress.org.
	 */
	public static function esta_abandonado( string $slug ): bool {
		$cache = get_transient( 'swpk_cerrado_' . $slug );
		if ( false !== $cache ) {
			return '1' === $cache;
		}

		$r = wp_remote_get(
			add_query_arg(
				[ 'action' => 'plugin_information', 'request[slug]' => $slug ],
				'https://api.wordpress.org/plugins/info/1.2/'
			),
			[ 'timeout' => 10 ]
		);

		$cerrado = false;
		if ( ! is_wp_error( $r ) ) {
			$datos   = json_decode( (string) wp_remote_retrieve_body( $r ), true );
			$cerrado = is_array( $datos ) && 'closed' === ( $datos['error'] ?? '' );
		}

		set_transient( 'swpk_cerrado_' . $slug, $cerrado ? '1' : '0', 12 * HOUR_IN_SECONDS );

		return $cerrado;
	}
}
