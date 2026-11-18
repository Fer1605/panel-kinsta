<?php
/**
 * Lo que el agente contó de la tienda de un sitio.
 *
 * Es un cajón, no una clase con lógica: el agente lo mide dentro del sitio
 * —ver SWA_Tienda— y llega pegado a su reporte. Aquí se guarda para que lo
 * puedan leer la clasificación (SWPK_Tipos) y los comprobadores de las normas
 * de tienda sin tener el reporte en la mano.
 *
 * Vive en un transient de 25 horas, como las demás lecturas del día: si el
 * agente deja de reportar, el dato caduca en vez de quedarse mintiendo.
 */

defined( 'ABSPATH' ) || exit;

class SWPK_Tienda {

	private const CACHE = 'swpk_tienda_';
	private const VIDA  = 25 * HOUR_IN_SECONDS;

	/** @return array<string,mixed> */
	public static function de( string $sitio_id ): array {
		if ( '' === $sitio_id ) {
			return [];
		}

		$v = get_transient( self::CACHE . $sitio_id );

		return is_array( $v ) ? $v : [];
	}

	/** @param array<string,mixed> $datos */
	public static function guardar( string $sitio_id, array $datos ): void {
		if ( '' === $sitio_id || ! $datos ) {
			return;
		}

		set_transient( self::CACHE . $sitio_id, $datos, self::VIDA );
	}

	public static function olvidar( string $sitio_id = '' ): void {
		if ( '' !== $sitio_id ) {
			delete_transient( self::CACHE . $sitio_id );

			return;
		}

		global $wpdb;
		$wpdb->query(
			"DELETE FROM {$wpdb->options}
			  WHERE option_name LIKE '_transient_swpk_tienda_%'
			     OR option_name LIKE '_transient_timeout_swpk_tienda_%'"
		);
	}
}
