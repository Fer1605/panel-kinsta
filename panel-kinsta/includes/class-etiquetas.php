<?php
/**
 * Traduce las etiquetas del proveedor a los tres grupos del panel.
 *
 * ─── Las etiquetas no se escriben a mano ────────────────────────────────────
 *
 * El panel las descubre solo: lee las que vienen en la respuesta del proveedor,
 * cuenta cuántos sitios tiene cada una y adivina a qué grupo corresponde por las
 * palabras de su nombre.
 *
 * Pedirle a alguien que escriba "NoUpdate(Externo SW)" tal cual, sin un espacio
 * de más ni de menos, es pedir un error de dedo que dejaría 13 sitios fuera del
 * reporte sin que nadie se entere. Y mañana, con otro proveedor, los nombres
 * serán otros o el concepto ni existirá.
 *
 * Lo único que queda a mano es corregir una adivinanza equivocada, y se corrige
 * eligiendo de una lista de las etiquetas que de verdad existen, no escribiendo.
 */

defined( 'ABSPATH' ) || exit;

class SWPK_Etiquetas {

	public const DESARROLLO = 'desarrollo';
	public const AUTO       = 'auto_update';
	public const NO_UPDATE  = 'no_update';
	public const SIN        = 'sin_etiqueta';

	public const OPCION_MAPA = 'swpk_mapa_etiquetas';

	/**
	 * Las etiquetas que de verdad existen en la cuenta, con cuántos sitios tiene
	 * cada una y a qué grupo se asignó.
	 *
	 * @return array<string,array{nombre:string,sitios:int,tag:string,manual:bool}>
	 */
	public static function descubrir(): array {
		global $wpdb;

		$tabla = SWPK_Tablas::tabla( 'sitios' );
		$filas = $wpdb->get_col( "SELECT etiquetas FROM {$tabla}" ) ?: [];
		$mapa  = self::mapa_guardado();

		$encontradas = [];

		foreach ( $filas as $json ) {
			$etiquetas = json_decode( (string) $json, true );
			if ( ! is_array( $etiquetas ) ) {
				continue;
			}

			foreach ( $etiquetas as $e ) {
				$nombre = trim( (string) ( $e['name'] ?? '' ) );
				if ( '' === $nombre ) {
					continue;
				}

				$clave = strtolower( $nombre );

				if ( ! isset( $encontradas[ $clave ] ) ) {
					$encontradas[ $clave ] = [
						'nombre' => $nombre,
						'sitios' => 0,
						'tag'    => $mapa[ $clave ] ?? self::adivinar( $nombre ),
						'manual' => isset( $mapa[ $clave ] ),
					];
				}

				++$encontradas[ $clave ]['sitios'];
			}
		}

		uasort( $encontradas, static fn( $a, $b ) => $b['sitios'] <=> $a['sitios'] );

		return $encontradas;
	}

	/** @return array<string,string> etiqueta en minúsculas => grupo */
	public static function mapa_guardado(): array {
		$m = get_option( self::OPCION_MAPA, [] );
		return is_array( $m ) ? $m : [];
	}

	public static function guardar_mapa( array $mapa ): void {
		$limpio = [];

		foreach ( $mapa as $etiqueta => $tag ) {
			$etiqueta = strtolower( trim( (string) $etiqueta ) );
			$tag      = sanitize_key( (string) $tag );

			if ( '' !== $etiqueta && isset( self::nombres()[ $tag ] ) ) {
				$limpio[ $etiqueta ] = $tag;
			}
		}

		update_option( self::OPCION_MAPA, $limpio, false );
	}

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
	 * @param array<int,array{id?:string,name?:string}> $etiquetas Lo que viene en la respuesta.
	 */
	public static function clasificar( array $etiquetas ): string {
		$mapa = self::mapa_guardado();

		foreach ( $etiquetas as $etiqueta ) {
			$nombre = trim( (string) ( $etiqueta['name'] ?? '' ) );
			if ( '' === $nombre ) {
				continue;
			}

			// Una corrección a mano manda sobre la adivinanza.
			$clave = strtolower( $nombre );
			if ( isset( $mapa[ $clave ] ) ) {
				return $mapa[ $clave ];
			}

			$adivinado = self::adivinar( $nombre );
			if ( self::SIN !== $adivinado ) {
				return $adivinado;
			}
		}

		return self::SIN;
	}

	/**
	 * A qué grupo suena el nombre de una etiqueta.
	 *
	 * Dos cosas que importan:
	 *
	 *  - El orden: "no auto update" contiene "auto update", así que lo negativo
	 *    se revisa primero o todo cae en el grupo equivocado.
	 *  - La negación puede venir pegada. Una etiqueta real se llama
	 *    "NoUpdate(Externo SW)", sin espacio, y un \b entre "no" y "update" no
	 *    existe ahí. Por eso no se usa \b al final.
	 */
	public static function adivinar( string $nombre ): string {
		$n = strtolower( trim( remove_accents( $nombre ) ) );

		if ( '' === $n ) {
			return self::SIN;
		}

		$tiene_update = str_contains( $n, 'update' ) || str_contains( $n, 'actualiza' );
		$niega        = preg_match( '/\b(no|sin|not)\s*-?\s*(update|auto|actualiza)/', $n ) === 1
			|| str_contains( $n, 'externo' );

		if ( $tiene_update && $niega ) {
			return self::NO_UPDATE;
		}

		if ( $tiene_update && ( str_contains( $n, 'auto' ) || str_contains( $n, 'automat' ) ) ) {
			return self::AUTO;
		}

		if ( str_contains( $n, 'desarrollo' ) || str_contains( $n, 'develop' ) || str_contains( $n, 'staging' ) || preg_match( '/\bdev\b/', $n ) ) {
			return self::DESARROLLO;
		}

		if ( str_contains( $n, 'produc' ) || str_contains( $n, 'live' ) ) {
			return self::AUTO;
		}

		return self::SIN;
	}
}
