<?php
/**
 * Traduce las etiquetas de MyKinsta a los tres tags del panel.
 *
 * El jefe dijo "desarrollo productivo, desarrollo auto update y no auto update,
 * CREO que se llama" (00:26). O sea que ni él tiene los nombres exactos. En vez
 * de esperar ese dato para empezar, el panel adivina por palabras y deja que se
 * corrija a mano en Ajustes cuando lo sepamos.
 */

defined( 'ABSPATH' ) || exit;

class SWPK_Etiquetas {

	public const DESARROLLO = 'desarrollo';
	public const AUTO       = 'auto_update';
	public const NO_UPDATE  = 'no_update';
	public const SIN        = 'sin_etiqueta';

	public static function nombres(): array {
		return [
			self::DESARROLLO => 'Desarrollo',
			self::AUTO       => 'Auto update',
			self::NO_UPDATE  => 'No auto update',
			self::SIN        => 'Sin etiqueta',
		];
	}

	/** ¿Es un sitio que administramos nosotros? Decide quién entra al 2FA y a la lectura de usuarios. */
	public static function es_nuestro( string $tag ): bool {
		return in_array( $tag, [ self::DESARROLLO, self::AUTO ], true );
	}

	/**
	 * @param array<int,array{id?:string,name?:string}> $etiquetas Lo que viene en siteLabels.
	 */
	public static function clasificar( array $etiquetas ): string {
		$ajustes = SWPK_Ajustes::todos();

		$mapa_manual = [
			trim( strtolower( (string) $ajustes['etiqueta_no_update'] ) )  => self::NO_UPDATE,
			trim( strtolower( (string) $ajustes['etiqueta_auto'] ) )       => self::AUTO,
			trim( strtolower( (string) $ajustes['etiqueta_desarrollo'] ) ) => self::DESARROLLO,
		];
		unset( $mapa_manual[''] );

		foreach ( $etiquetas as $etiqueta ) {
			$nombre = strtolower( trim( (string) ( $etiqueta['name'] ?? '' ) ) );
			if ( '' === $nombre ) {
				continue;
			}

			if ( isset( $mapa_manual[ $nombre ] ) ) {
				return $mapa_manual[ $nombre ];
			}

			// Adivinanza por palabras. Dos cosas que importan:
			//
			//  - El orden: "no auto update" contiene "auto update", así que lo
			//    negativo se revisa primero o todo cae en el grupo equivocado.
			//  - La negación puede venir pegada. Una etiqueta real se llama
			//    "NoUpdate(Externo SW)", sin espacio, y un \b entre "no" y
			//    "update" no existe ahí. Por eso no se usa \b al final.
			$tiene_update = str_contains( $nombre, 'update' ) || str_contains( $nombre, 'actualiza' );
			$niega        = preg_match( '/\b(no|sin|not)\s*-?\s*(update|auto|actualiza)/', $nombre ) === 1
				|| str_contains( $nombre, 'externo' );

			if ( $tiene_update && $niega ) {
				return self::NO_UPDATE;
			}
			if ( $tiene_update && ( str_contains( $nombre, 'auto' ) || str_contains( $nombre, 'automat' ) ) ) {
				return self::AUTO;
			}
			if ( str_contains( $nombre, 'desarrollo' ) || str_contains( $nombre, 'develop' ) || str_contains( $nombre, 'dev' ) ) {
				return self::DESARROLLO;
			}
		}

		return self::SIN;
	}
}
