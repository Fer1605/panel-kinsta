<?php
/**
 * Comparación de versiones.
 *
 * Trampa real: muchas versiones de plugins de WordPress no son semver válido
 * ("1.2.3.4", "2.0-beta", "1.10"). Comparar como texto da "1.9 > 1.10", que es
 * falso y haría que el panel diga que un sitio está al día cuando no lo está.
 * version_compare de PHP sí lo hace bien; lo único que hay que cuidar es
 * normalizar antes.
 */

defined( 'ABSPATH' ) || exit;

class SWPK_Versiones {

	public static function normalizar( string $version ): string {
		$v = trim( $version );
		$v = preg_replace( '/[^0-9A-Za-z.\-+]/', '', $v ) ?? '';
		return $v;
	}

	public static function es_menor( string $a, string $b ): bool {
		$a = self::normalizar( $a );
		$b = self::normalizar( $b );
		if ( '' === $a || '' === $b ) {
			return false;
		}
		return version_compare( $a, $b, '<' );
	}

	/**
	 * ¿La versión instalada cae dentro de un rango afectado de Wordfence?
	 *
	 * El comodín "*" de Wordfence solo vale si es el único carácter de la
	 * cadena: "1.*" hace match literal del asterisco, no es un comodín.
	 *
	 * @param array{from_version?:string,from_inclusive?:bool,to_version?:string,to_inclusive?:bool} $rango
	 */
	public static function en_rango( string $instalada, array $rango ): bool {
		$instalada = self::normalizar( $instalada );
		if ( '' === $instalada ) {
			return false;
		}

		$desde     = self::normalizar( (string) ( $rango['from_version'] ?? '' ) );
		$hasta     = self::normalizar( (string) ( $rango['to_version'] ?? '' ) );
		$desde_inc = (bool) ( $rango['from_inclusive'] ?? true );
		$hasta_inc = (bool) ( $rango['to_inclusive'] ?? true );

		if ( '*' === $desde && '*' === $hasta ) {
			return true;
		}

		if ( '' !== $desde && '*' !== $desde ) {
			$op = $desde_inc ? '>=' : '>';
			if ( ! version_compare( $instalada, $desde, $op ) ) {
				return false;
			}
		}

		if ( '' !== $hasta && '*' !== $hasta ) {
			$op = $hasta_inc ? '<=' : '<';
			if ( ! version_compare( $instalada, $hasta, $op ) ) {
				return false;
			}
		}

		return true;
	}
}
