<?php
/**
 * Exportación a Excel.
 *
 *   "Un panel dinámico donde yo ponga usuario y contraseña, entro a ese panel y
 *    veo las últimas notificaciones y actualizaciones. Y esa data la puedo
 *    exportar." — 07:58
 *
 * ─── Dos detalles que parecen menores y no lo son ───────────────────────────
 *
 * 1. El BOM. Excel abre un CSV en UTF-8 sin BOM interpretando los acentos como
 *    basura: "Librería Académica" se vuelve "LibrerÃ­a AcadÃ©mica". Son tres
 *    bytes al principio del archivo y es la diferencia entre un reporte que se
 *    puede mandar y uno que hay que arreglar a mano.
 *
 * 2. Punto y coma. Excel en español separa columnas por ";" y no por ",". Con
 *    coma, todo el reporte cae en una sola columna.
 *
 * Se exporta lo que se está viendo, con los filtros puestos. Exportar siempre
 * todo obligaría a filtrar otra vez en Excel, que es justo lo que se quería
 * evitar.
 */

defined( 'ABSPATH' ) || exit;

class SWPK_Exportar {

	public static function init(): void {
		add_action( 'admin_post_swpk_exportar', [ __CLASS__, 'exportar' ] );
	}

	public static function url( string $tipo, array $filtros = [] ): string {
		return wp_nonce_url(
			add_query_arg(
				array_merge( [ 'action' => 'swpk_exportar', 'tipo' => $tipo ], array_filter( $filtros ) ),
				admin_url( 'admin-post.php' )
			),
			'swpk_exportar'
		);
	}

	public static function exportar(): void {
		if ( ! SWPK_Perfiles::puede( SWPK_Perfiles::VER ) ) {
			wp_die( 'Sin permiso.' );
		}
		check_admin_referer( 'swpk_exportar' );

		$tipo = isset( $_GET['tipo'] ) ? sanitize_key( (string) $_GET['tipo'] ) : 'sitios';

		[ $nombre, $cabeceras, $filas ] = match ( $tipo ) {
			'vulnerabilidades' => self::datos_vulnerabilidades(),
			'bitacora'         => self::datos_bitacora(),
			'caidas'           => self::datos_caidas(),
			default            => self::datos_sitios(),
		};

		self::mandar_csv( $nombre, $cabeceras, $filas );
	}

	/** @param array<int,array<int,string>> $filas */
	private static function mandar_csv( string $nombre, array $cabeceras, array $filas ): void {
		$archivo = sprintf( '%s-%s.csv', $nombre, current_time( 'Y-m-d' ) );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="' . $archivo . '"' );

		$salida = fopen( 'php://output', 'w' );

		// El BOM, para que Excel entienda los acentos.
		fwrite( $salida, "\xEF\xBB\xBF" );

		fputcsv( $salida, $cabeceras, ';' );
		foreach ( $filas as $fila ) {
			fputcsv( $salida, $fila, ';' );
		}

		fclose( $salida );
		exit;
	}

	/** Los sitios que el usuario puede ver, con los filtros de la pantalla. */
	private static function sitios_filtrados(): array {
		return SWPK_Vista::filtrar(
			SWPK_Perfiles::sitios_visibles(),
			SWPK_Vista::tag_pedido(),
			SWPK_Vista::nivel_pedido(),
			SWPK_Vista::busqueda_pedida()
		);
	}

	private static function datos_sitios(): array {
		$filas = [];

		foreach ( self::sitios_filtrados() as $s ) {
			$hallazgos = SWPK_Riesgo::hallazgos( (string) $s->sitio_id );
			$vulns     = count( array_filter( $hallazgos, static fn( $h ) => 'actualizacion_pendiente' !== $h['motivo'] ) );
			$asesora   = $s->asesora_id ? get_user_by( 'id', (int) $s->asesora_id ) : null;

			$filas[] = [
				$s->nombre_visible,
				$s->dominio,
				SWPK_Etiquetas::nombres()[ $s->tag ] ?? $s->tag,
				SWPK_Riesgo::nombres()[ SWPK_Riesgo::nivel_del_sitio( (string) $s->sitio_id ) ] ?? '',
				(string) $vulns,
				(string) ( count( $hallazgos ) - $vulns ),
				$s->version_wp,
				$s->version_php,
				$s->tiene_2fa ? 'Sí' : 'No',
				$asesora->display_name ?? '',
				$s->contacto_cliente,
				$s->inventario_en ? mysql2date( 'd/m/Y H:i', (string) $s->inventario_en ) : 'nunca',
			];
		}

		return [
			'sitios',
			[ 'Sitio', 'Dominio', 'Grupo', 'Estado', 'Con vulnerabilidad', 'Por actualizar', 'WordPress', 'PHP', 'Doble paso', 'Asesora', 'Contacto del cliente', 'Revisado por el proveedor' ],
			$filas,
		];
	}

	private static function datos_vulnerabilidades(): array {
		$filas = [];

		foreach ( self::sitios_filtrados() as $s ) {
			foreach ( SWPK_Riesgo::hallazgos( (string) $s->sitio_id ) as $h ) {
				$v     = $h['vulnerabilidades'][0] ?? null;
				$quien = $v ? SWPK_Textos::quien_puede( (string) ( $v->cvss_vector ?? '' ), (string) $v->descripcion ) : null;

				$filas[] = [
					$s->nombre_visible,
					SWPK_Etiquetas::nombres()[ $s->tag ] ?? $s->tag,
					SWPK_Textos::limpiar_titulo( (string) $h['titulo'] ),
					$h['slug'],
					$h['version'],
					$h['version_disponible'],
					SWPK_Riesgo::nombres()[ $h['nivel'] ] ?? '',
					$v ? (string) $v->cvss_score : '',
					$v ? (string) $v->cve : '',
					$quien ? $quien['frase'] : '',
					$v ? SWPK_Textos::explicar( (string) $v->titulo, (string) $v->descripcion ) : '',
					'' === $h['auto_update'] ? 'No' : 'Sí',
				];
			}
		}

		return [
			'vulnerabilidades',
			[ 'Sitio', 'Grupo', 'Módulo', 'Identificador', 'Versión instalada', 'Actualizar a', 'Nivel', 'Gravedad (0-10)', 'CVE', 'Quién puede aprovecharlo', 'Qué permite', 'Actualización automática' ],
			$filas,
		];
	}

	/**
	 * La bitácora: el reporte que se abre en una junta incómoda.
	 *
	 * Se puede acotar por fechas, porque la pregunta nunca es "mandame todo":
	 * es "de tal fecha a tal fecha, qué le avisaron a este cliente".
	 */
	private static function datos_bitacora(): array {
		global $wpdb;

		$desde = isset( $_GET['desde'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['desde'] ) ) : '';
		$hasta = isset( $_GET['hasta'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['hasta'] ) ) : '';
		$sitio = isset( $_GET['sitio'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['sitio'] ) ) : '';

		$donde  = [ '1=1' ];
		$params = [];

		if ( '' !== $sitio ) {
			$donde[]  = 'sitio_id = %s';
			$params[] = $sitio;
		}
		if ( '' !== $desde ) {
			$donde[]  = 'enviado_en >= %s';
			$params[] = $desde . ' 00:00:00';
		}
		if ( '' !== $hasta ) {
			$donde[]  = 'enviado_en <= %s';
			$params[] = $hasta . ' 23:59:59';
		}

		$sql = 'SELECT * FROM ' . SWPK_Tablas::tabla( 'bitacora' ) . ' WHERE ' . implode( ' AND ', $donde ) . ' ORDER BY enviado_en DESC';
		$registros = $params ? $wpdb->get_results( $wpdb->prepare( $sql, ...$params ) ) : $wpdb->get_results( $sql );

		$tipos  = SWPK_Bitacora::tipos();
		$estado = [
			'enviado'          => 'Enviado',
			'simulado'         => 'De prueba (no salió)',
			'apagado'          => 'No salió: envíos apagados',
			'sin_destinatario' => 'Sin destinatario',
			'fallo'            => 'Falló el envío',
		];

		$filas = [];
		foreach ( (array) $registros as $r ) {
			$s = SWPK_Inventario::obtener( (string) $r->sitio_id );

			if ( ! SWPK_Perfiles::puede( SWPK_Perfiles::VER_TODO ) && $s && ! SWPK_Perfiles::puede_ver_sitio( (string) $r->sitio_id ) ) {
				continue;
			}

			$filas[] = [
				mysql2date( 'd/m/Y H:i', $r->enviado_en ),
				$tipos[ $r->tipo ] ?? $r->tipo,
				$s->nombre_visible ?? '',
				(string) $r->destinatarios,
				(string) $r->asunto,
				$estado[ $r->resultado ] ?? (string) $r->resultado,
				(string) $r->error,
			];
		}

		return [
			'bitacora',
			[ 'Fecha', 'Tipo de aviso', 'Sitio', 'Destinatarios', 'Asunto', 'Resultado', 'Detalle' ],
			$filas,
		];
	}

	private static function datos_caidas(): array {
		$filas = [];

		foreach ( SWPK_Monitor::estados() as $e ) {
			$s = SWPK_Inventario::obtener( (string) $e->sitio_id );

			$filas[] = [
				$s->nombre_visible ?? $e->sitio_id,
				(string) $e->url,
				match ( $e->estado ) {
					'abajo'  => 'Sin responder',
					'raro'   => 'Respuesta rara',
					'arriba' => 'Responde',
					default  => 'Sin datos',
				},
				(string) $e->codigo,
				$e->ms ? number_format( $e->ms / 1000, 2 ) . ' s' : '',
				(string) $e->motivo,
				$e->desde ? mysql2date( 'd/m/Y H:i', (string) $e->desde ) : '',
				(string) $e->caidas_mes,
				$e->segundos_caido ? SWPK_Monitor::duracion( (int) $e->segundos_caido ) : '',
			];
		}

		return [
			'caidas',
			[ 'Sitio', 'Dirección', 'Estado', 'Código', 'Tiempo de respuesta', 'Detalle', 'Desde', 'Caídas del mes', 'Tiempo caído acumulado' ],
			$filas,
		];
	}
}
