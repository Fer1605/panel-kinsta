<?php
/**
 * ¿El plugin sigue vivo en el repositorio de WordPress, o lo cerraron?
 *
 * ─── Por qué hace falta ─────────────────────────────────────────────────────
 *
 * Una vulnerabilidad sin parche tiene dos finales muy distintos, y el panel los
 * enseñaba igual:
 *
 *   - El autor todavía no ha sacado el arreglo. Hay que esperar, o desactivar
 *     el plugin mientras tanto. Puede resolverse la semana que viene.
 *   - WordPress cerró el plugin: lo sacó del repositorio y no va a haber
 *     arreglo nunca. Ahí esperar no es una opción, hay que reemplazarlo.
 *
 * Wordfence dice si hay parche, pero no dice si el plugin sigue existiendo. Eso
 * solo lo sabe el repositorio, y lo contesta en una petición.
 *
 * ─── Cómo se pregunta, y por qué así ────────────────────────────────────────
 *
 * La API de wordpress.org devuelve el plugin si está publicado y un error si no
 * está. «No está» puede ser dos cosas —cerrado, o que nunca estuvo ahí, como
 * los nuestros y los de pago— y no se pueden confundir: decir «WordPress lo
 * cerró» de un plugin de pago sería mentira. Por eso hay tres respuestas y la
 * tercera es «no se sabe», que es la que se usa cuando no hay con qué decidir.
 *
 * Se pregunta poco: solo por los slugs que tienen una vulnerabilidad sin parche
 * —hoy son 12 en toda la cartera— y la respuesta se guarda una semana. Un
 * plugin no se cierra y se reabre en siete días.
 */

defined( 'ABSPATH' ) || exit;

class SWPK_Repositorio {

	private const OPCION = 'swpk_repositorio_estado';
	private const DIAS   = 7;

	public const ABIERTO    = 'abierto';
	public const CERRADO    = 'cerrado';
	public const NO_SE_SABE = 'no_se_sabe';

	/**
	 * @return array<string,array{estado:string,visto:string}>
	 */
	private static function cache(): array {
		$v = get_option( self::OPCION, [] );
		return is_array( $v ) ? $v : [];
	}

	public static function estado( string $slug ): string {
		$slug = sanitize_key( $slug );

		if ( '' === $slug ) {
			return self::NO_SE_SABE;
		}

		$cache = self::cache();

		if ( isset( $cache[ $slug ]['visto'] ) ) {
			$edad = time() - strtotime( (string) $cache[ $slug ]['visto'] );
			if ( $edad < self::DIAS * DAY_IN_SECONDS ) {
				return (string) $cache[ $slug ]['estado'];
			}
		}

		$estado = self::preguntar( $slug );

		$cache[ $slug ] = [ 'estado' => $estado, 'visto' => gmdate( 'Y-m-d H:i:s' ) ];
		update_option( self::OPCION, $cache, false );

		return $estado;
	}

	/**
	 * Lo que contesta el repositorio, traducido a nuestras tres respuestas.
	 *
	 * Un fallo de red NO es «cerrado»: es «no se sabe», y además no se guarda
	 * como respuesta buena, para volver a preguntar en la siguiente vuelta en
	 * vez de quedarse con una conclusión sacada de un timeout.
	 */
	private static function preguntar( string $slug ): string {
		$r = wp_remote_get(
			'https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&request[slug]=' . rawurlencode( $slug ),
			[ 'timeout' => 12, 'headers' => [ 'Accept' => 'application/json' ] ]
		);

		if ( is_wp_error( $r ) ) {
			return self::NO_SE_SABE;
		}

		$codigo = (int) wp_remote_retrieve_response_code( $r );
		$cuerpo = json_decode( (string) wp_remote_retrieve_body( $r ), true );

		if ( 200 === $codigo && is_array( $cuerpo ) && ! empty( $cuerpo['slug'] ) ) {
			// Publicado. Ojo: el repositorio también devuelve 200 con el aviso
			// de cierre en algunos casos, y entonces lo dice en `closed`.
			return empty( $cuerpo['closed'] ) ? self::ABIERTO : self::CERRADO;
		}

		// 404 con este error concreto significa que el slug no está publicado.
		// Puede ser un cierre o puede ser un plugin que nunca estuvo ahí —los
		// nuestros, los de pago—, y el repositorio no distingue los dos casos.
		// Como no se puede saber, no se inventa.
		if ( 404 === $codigo ) {
			return self::NO_SE_SABE;
		}

		return self::NO_SE_SABE;
	}

	/**
	 * Pregunta de golpe por varios slugs. Se llama desde la corrida diaria.
	 *
	 * @param array<int,string> $slugs
	 */
	public static function refrescar( array $slugs ): int {
		$n = 0;

		foreach ( array_unique( array_filter( $slugs ) ) as $slug ) {
			self::estado( (string) $slug );
			++$n;
		}

		return $n;
	}
}
