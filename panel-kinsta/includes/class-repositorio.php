<?php
/**
 * ¿El módulo sigue vivo en el repositorio de WordPress, o lo cerraron?
 *
 * ─── Por qué hace falta ─────────────────────────────────────────────────────
 *
 * Una vulnerabilidad tiene finales muy distintos y el panel los enseñaba igual:
 *
 *   - El autor todavía no ha sacado el arreglo. Hay que esperar, o desactivar
 *     el módulo mientras tanto. Puede resolverse la semana que viene.
 *   - WordPress cerró el módulo: lo sacó del repositorio y no va a llegar
 *     ningún arreglo por ahí nunca. Esperar no es una opción: hay que
 *     reemplazarlo.
 *
 * Wordfence dice si hay parche, pero no dice si el módulo sigue existiendo. Eso
 * solo lo sabe el repositorio, y lo contesta en una petición.
 *
 * ─── El fallo que esto arregla (27-11-2026) ─────────────────────────────────
 *
 * Heraldos Costa Rica tiene Modern Events Calendar Lite 5.15.5. El panel decía
 * «Crítico · Hay parche» y no ofrecía ninguna actualización. Fer preguntó lo
 * único que se puede preguntar ahí: «dice que hay un parche, pero no tiene una
 * actualización… entonces, ¿qué es lo que podemos hacer?».
 *
 * La respuesta estaba a una petición de distancia: wordpress.org cerró ese
 * plugin el 11 de mayo de 2022 por violar las normas. La versión que corrige la
 * falla es la 7.13.0 y el sitio tiene la 5.15.5, pero esa versión no va a
 * llegar nunca por WordPress, porque el plugin ya no está ahí. Lo que hay que
 * hacer no es actualizar: es reemplazarlo.
 *
 * El panel no lo decía porque esta clase tiraba la respuesta a la basura. El
 * repositorio contesta **404 con el motivo del cierre en el cuerpo**:
 *
 *   {"error":"closed","closed":true,"closed_date":"2022-05-11",
 *    "reason_text":"Guideline Violation", ...}
 *
 * y el código de antes miraba solo el código HTTP: «404 → no se sabe». Así que
 * los cinco módulos cerrados de la cartera —cuatro de ellos cerrados por un
 * **problema de seguridad**— salían etiquetados «Hay parche: actualizar», que
 * es exactamente el consejo contrario al correcto.
 *
 * ─── Por qué hay que preguntar por el tipo ──────────────────────────────────
 *
 * Los temas tienen su propia API. Y los slugs se repiten entre las dos: existe
 * un **plugin** llamado `twentytwenty` que WordPress cerró en 2024, y existe el
 * **tema** Twenty Twenty, que está vivo en su versión 3.3. Preguntando siempre
 * por la API de plugins, el tema por defecto de WordPress salía marcado como
 * «cerrado por un problema de seguridad». Por eso cada tipo pregunta en su
 * puerta.
 *
 * Se pregunta poco: solo por los slugs que aparecen en algún hallazgo, y la
 * respuesta se guarda una semana. Un módulo no se cierra y se reabre en siete
 * días.
 */

defined( 'ABSPATH' ) || exit;

class SWPK_Repositorio {

	private const OPCION = 'swpk_repositorio_estado';
	private const DIAS   = 7;

	public const ABIERTO    = 'abierto';
	public const CERRADO    = 'cerrado';
	public const NO_SE_SABE = 'no_se_sabe';

	/**
	 * @return array<string,array{estado:string,visto:string,cerrado_el?:string,motivo?:string}>
	 */
	private static function cache(): array {
		$v = get_option( self::OPCION, [] );
		return is_array( $v ) ? $v : [];
	}

	/** La clave del cache lleva el tipo, porque un mismo slug puede ser las dos cosas. */
	private static function clave( string $slug, string $tipo ): string {
		return ( 'tema' === $tipo ? 'tema:' : 'plugin:' ) . $slug;
	}

	/**
	 * Todo lo que se sabe del módulo en el repositorio.
	 *
	 * @return array{estado:string,cerrado_el:string,motivo:string}
	 */
	public static function ficha( string $slug, string $tipo = 'plugin' ): array {
		$slug = sanitize_key( str_replace( '_', '-', $slug ) );
		$tipo = 'tema' === $tipo ? 'tema' : 'plugin';

		$vacia = [ 'estado' => self::NO_SE_SABE, 'cerrado_el' => '', 'motivo' => '' ];

		if ( '' === $slug ) {
			return $vacia;
		}

		$cache = self::cache();
		$clave = self::clave( $slug, $tipo );

		if ( isset( $cache[ $clave ]['visto'] ) ) {
			$edad = time() - strtotime( (string) $cache[ $clave ]['visto'] );
			if ( $edad < self::DIAS * DAY_IN_SECONDS ) {
				return [
					'estado'     => (string) $cache[ $clave ]['estado'],
					'cerrado_el' => (string) ( $cache[ $clave ]['cerrado_el'] ?? '' ),
					'motivo'     => (string) ( $cache[ $clave ]['motivo'] ?? '' ),
				];
			}
		}

		$ficha = self::preguntar( $slug, $tipo );

		// Un fallo de red no se guarda: así se vuelve a preguntar en la
		// siguiente vuelta en vez de quedarse con una conclusión sacada de un
		// timeout.
		if ( self::NO_SE_SABE !== $ficha['estado'] ) {
			$cache[ $clave ] = $ficha + [ 'visto' => gmdate( 'Y-m-d H:i:s' ) ];
			update_option( self::OPCION, $cache, false );
		}

		return $ficha;
	}

	public static function estado( string $slug, string $tipo = 'plugin' ): string {
		return self::ficha( $slug, $tipo )['estado'];
	}

	/**
	 * «WordPress lo cerró el 11 de mayo de 2022 por un problema de seguridad.»
	 *
	 * Devuelve cadena vacía si no está cerrado o si no se sabe por qué.
	 */
	public static function frase_cierre( string $slug, string $tipo = 'plugin' ): string {
		$f = self::ficha( $slug, $tipo );

		if ( self::CERRADO !== $f['estado'] ) {
			return '';
		}

		$frase = 'WordPress lo sacó de su repositorio';

		if ( '' !== $f['cerrado_el'] ) {
			$frase .= ' el ' . date_i18n( 'j \d\e F \d\e Y', (int) strtotime( $f['cerrado_el'] ) );
		}

		if ( '' !== $f['motivo'] ) {
			$frase .= ' por ' . $f['motivo'];
		}

		return $frase . '.';
	}

	/** Cerrado por un problema de seguridad: el caso que no admite esperar. */
	public static function cerrado_por_seguridad( string $slug, string $tipo = 'plugin' ): bool {
		$f = self::ficha( $slug, $tipo );

		return self::CERRADO === $f['estado']
			&& false !== mb_stripos( $f['motivo'], 'seguridad' );
	}

	/**
	 * Lo que contesta el repositorio, traducido.
	 *
	 * @return array{estado:string,cerrado_el:string,motivo:string}
	 */
	private static function preguntar( string $slug, string $tipo ): array {
		$vacia = [ 'estado' => self::NO_SE_SABE, 'cerrado_el' => '', 'motivo' => '' ];

		$url = 'tema' === $tipo
			? add_query_arg(
				[ 'action' => 'theme_information', 'request[slug]' => $slug ],
				'https://api.wordpress.org/themes/info/1.2/'
			)
			: add_query_arg(
				[ 'action' => 'plugin_information', 'request[slug]' => $slug ],
				'https://api.wordpress.org/plugins/info/1.2/'
			);

		$r = wp_remote_get( $url, [ 'timeout' => 12, 'headers' => [ 'Accept' => 'application/json' ] ] );

		if ( is_wp_error( $r ) ) {
			return $vacia;
		}

		$cuerpo = json_decode( (string) wp_remote_retrieve_body( $r ), true );

		if ( ! is_array( $cuerpo ) ) {
			return $vacia;
		}

		// ── El cierre viene en el CUERPO, no en el código HTTP ──────────────
		//
		// El repositorio contesta 404 cuando el módulo no se puede descargar,
		// y eso incluye tanto «lo cerramos» como «nunca estuvo aquí». La
		// diferencia está en el cuerpo, y es toda la diferencia del mundo:
		// de un plugin de pago no se puede decir «WordPress lo cerró».
		if ( ! empty( $cuerpo['closed'] ) || 'closed' === ( $cuerpo['error'] ?? '' ) ) {
			return [
				'estado'     => self::CERRADO,
				'cerrado_el' => (string) ( $cuerpo['closed_date'] ?? '' ),
				'motivo'     => self::motivo_en_castellano( (string) ( $cuerpo['reason'] ?? '' ), (string) ( $cuerpo['reason_text'] ?? '' ) ),
			];
		}

		if ( ! empty( $cuerpo['slug'] ) || ! empty( $cuerpo['version'] ) ) {
			return [ 'estado' => self::ABIERTO, 'cerrado_el' => '', 'motivo' => '' ];
		}

		// «Plugin not found» / «Theme not found»: puede ser un cierre muy viejo
		// o puede ser un módulo que nunca estuvo ahí —los nuestros, los de
		// pago—, y el repositorio no distingue los dos casos. Como no se puede
		// saber, no se inventa.
		return $vacia;
	}

	/** Los motivos de cierre que publica el repositorio, en castellano. */
	private static function motivo_en_castellano( string $razon, string $texto ): string {
		$mapa = [
			'security-issue'       => 'un problema de seguridad',
			'guideline-violation'  => 'incumplir las normas del repositorio',
			'author-request'       => 'petición de su autor',
			'licensing-trademark'  => 'un problema de licencia o de marca',
			'merged-into-core'     => 'haberse integrado en el propio WordPress',
			'unused'               => 'llevar demasiado tiempo sin usarse',
			'reserved'             => 'quedar reservado por WordPress',
		];

		if ( isset( $mapa[ $razon ] ) ) {
			return $mapa[ $razon ];
		}

		// Lo que diga el repositorio, pero en minúscula para que encaje en la
		// frase. Si no dijo nada, no se inventa un motivo.
		return '' !== $texto ? mb_strtolower( $texto ) : '';
	}

	/**
	 * Pregunta de golpe por varios módulos. Se llama desde la corrida diaria.
	 *
	 * @param array<int,array{slug:string,tipo:string}|string> $modulos
	 */
	public static function refrescar( array $modulos ): int {
		$n     = 0;
		$vistos = [];

		foreach ( $modulos as $m ) {
			$slug = is_array( $m ) ? (string) ( $m['slug'] ?? '' ) : (string) $m;
			$tipo = is_array( $m ) ? (string) ( $m['tipo'] ?? 'plugin' ) : 'plugin';

			if ( '' === $slug ) {
				continue;
			}

			$clave = self::clave( $slug, $tipo );
			if ( isset( $vistos[ $clave ] ) ) {
				continue;
			}
			$vistos[ $clave ] = true;

			self::ficha( $slug, $tipo );
			++$n;
		}

		return $n;
	}
}
