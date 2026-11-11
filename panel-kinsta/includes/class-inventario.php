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

	public static function ultima_sincronizacion(): string {
		return (string) get_option( 'swpk_ultima_sincronizacion', '' );
	}
}
