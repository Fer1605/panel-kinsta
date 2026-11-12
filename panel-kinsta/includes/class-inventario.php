<?php
/**
 * El inventario consolidado: la tabla de sitios y sus consultas.
 *
 * "Individual, mucha información, pero no en paquete" (00:00). Esto es el
 * paquete.
 */

defined( 'ABSPATH' ) || exit;

class SWPK_Inventario {

	/** Lee los sitios de la fuente y los guarda. Devuelve cuántos. */
	public static function sincronizar(): int|WP_Error {
		global $wpdb;

		$sitios = swpk_fuente()->sitios();
		if ( is_wp_error( $sitios ) ) {
			return $sitios;
		}

		$tabla = SWPK_Tablas::tabla( 'sitios' );
		$ahora = current_time( 'mysql' );
		$n     = 0;

		foreach ( $sitios as $sitio ) {
			if ( '' === $sitio['sitio_id'] ) {
				continue;
			}

			$datos = [
				'sitio_id'       => $sitio['sitio_id'],
				'entorno_id'     => $sitio['entorno_id'],
				'nombre'         => $sitio['nombre'],
				'nombre_visible' => $sitio['nombre_visible'],
				'estado'         => $sitio['estado'],
				'dominio'        => $sitio['dominio'],
				'etiquetas'      => wp_json_encode( $sitio['etiquetas'] ),
				'tag'            => SWPK_Etiquetas::clasificar( $sitio['etiquetas'] ),
				'version_wp'     => $sitio['version_wp'],
				'version_php'    => $sitio['version_php'],
				'visto_en'       => $ahora,
			];

			$existe = $wpdb->get_var(
				$wpdb->prepare( "SELECT id FROM {$tabla} WHERE sitio_id = %s", $sitio['sitio_id'] )
			);

			if ( $existe ) {
				$wpdb->update( $tabla, $datos, [ 'id' => (int) $existe ] );
			} else {
				$wpdb->insert( $tabla, $datos );
			}
			++$n;
		}

		update_option( 'swpk_ultima_sincronizacion', $ahora, false );

		return $n;
	}

	/** @return array<int,object> */
	public static function todos(): array {
		global $wpdb;
		$tabla = SWPK_Tablas::tabla( 'sitios' );
		return $wpdb->get_results( "SELECT * FROM {$tabla} ORDER BY tag ASC, nombre_visible ASC" ) ?: [];
	}

	public static function obtener( string $sitio_id ): ?object {
		global $wpdb;
		$tabla = SWPK_Tablas::tabla( 'sitios' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tabla} WHERE sitio_id = %s", $sitio_id ) );
	}

	/** @return array<string,int> */
	public static function conteo_por_tag(): array {
		global $wpdb;
		$tabla = SWPK_Tablas::tabla( 'sitios' );
		$filas = $wpdb->get_results( "SELECT tag, COUNT(*) AS n FROM {$tabla} GROUP BY tag" ) ?: [];

		$conteo = array_fill_keys( array_keys( SWPK_Etiquetas::nombres() ), 0 );
		foreach ( $filas as $fila ) {
			$conteo[ $fila->tag ] = (int) $fila->n;
		}

		return $conteo;
	}

	/**
	 * A partir de cuántos días el inventario de un sitio deja de ser confiable.
	 *
	 * El proveedor no escanea todos los sitios a la vez: cada uno lleva su
	 * propio ritmo. Un dato de hace tres días está bien; uno de hace tres
	 * semanas puede estar escondiendo una actualización pendiente que salió
	 * después, y eso es casi tan engañoso como no tener dato.
	 */
	public const DIAS_PARA_DUDAR = 14;

	/** Días desde que el proveedor miró este sitio. null = nunca lo miró. */
	public static function dias_sin_escanear( object $sitio ): ?int {
		if ( empty( $sitio->inventario_en ) ) {
			return null;
		}

		$cuando = strtotime( (string) $sitio->inventario_en );
		if ( ! $cuando ) {
			return null;
		}

		// Aquí SÍ va time() y no SWPK_Frescura::ahora_ts(), al revés que en el
		// resto del panel: `inventario_en` no lo escribe current_time(), lo
		// escribe Kinsta, y Kinsta lo da en UTC. Comparar un instante en UTC
		// contra el reloj local lo correría seis horas.
		//
		// Si algún día esta columna pasa a guardarse en hora local, esta línea
		// hay que cambiarla con ella.
		return (int) floor( ( time() - $cuando ) / DAY_IN_SECONDS );
	}

	public static function dato_viejo( object $sitio ): bool {
		$dias = self::dias_sin_escanear( $sitio );
		return null !== $dias && $dias >= self::DIAS_PARA_DUDAR;
	}

	public static function ultima_sincronizacion(): string {
		return (string) get_option( 'swpk_ultima_sincronizacion', '' );
	}
}
