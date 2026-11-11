<?php
/**
 * El reporte imprimible de un sitio.
 *
 *   "La pantalla que justifica el proyecto es la bitácora: es la que se abre en
 *    una junta incómoda, se filtra por un sitio y se exporta en PDF."
 *
 * ─── Por qué no se genera el PDF desde PHP ──────────────────────────────────
 *
 * Porque meter una librería de PDF significa cargar megas de código en cada
 * petición, pelearse con las fuentes y los acentos, y chocar con los límites de
 * memoria del servidor. El navegador ya sabe hacer PDF, y lo hace mejor: esta
 * página está pensada para imprimirse, y "Guardar como PDF" en el diálogo de
 * impresión entrega el archivo.
 *
 * Cero dependencias, cero problemas de memoria, y el resultado se ve igual en
 * cualquier máquina.
 */
defined( 'ABSPATH' ) || exit;

$sitio_id = isset( $_GET['sitio'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['sitio'] ) ) : '';
$sitio    = $sitio_id ? SWPK_Inventario::obtener( $sitio_id ) : null;

if ( ! $sitio || ! SWPK_Perfiles::puede_ver_sitio( $sitio_id ) ) {
	echo '<div class="wrap"><h1>Reporte</h1><div class="notice notice-error"><p>Ese sitio no existe o no está entre los tuyos.</p></div></div>';
	return;
}

$desde = isset( $_GET['desde'] ) && $_GET['desde'] ? sanitize_text_field( wp_unslash( (string) $_GET['desde'] ) ) : gmdate( 'Y-m-01' );
$hasta = isset( $_GET['hasta'] ) && $_GET['hasta'] ? sanitize_text_field( wp_unslash( (string) $_GET['hasta'] ) ) : current_time( 'Y-m-d' );

global $wpdb;

$avisos = $wpdb->get_results(
	$wpdb->prepare(
		'SELECT * FROM ' . SWPK_Tablas::tabla( 'bitacora' ) . ' WHERE sitio_id = %s AND enviado_en BETWEEN %s AND %s ORDER BY enviado_en DESC',
		$sitio_id,
		$desde . ' 00:00:00',
		$hasta . ' 23:59:59'
	)
) ?: [];

$cambios = $wpdb->get_results(
	$wpdb->prepare(
		'SELECT * FROM ' . SWPK_Tablas::tabla( 'eventos' ) . ' WHERE sitio_id = %s AND ocurrio_en BETWEEN %s AND %s ORDER BY ocurrio_en DESC',
		$sitio_id,
		$desde . ' 00:00:00',
		$hasta . ' 23:59:59'
	)
) ?: [];

$hallazgos = SWPK_Riesgo::hallazgos( $sitio_id );
$regreso   = SWPK_Vista::url_de_regreso();
?>
<style>
	@media print {
		#adminmenumain, #wpadminbar, #wpfooter, .swpk-no-imprimir { display: none !important; }
		#wpcontent, #wpbody-content { margin-left: 0 !important; padding: 0 !important; }
		.swpk-reporte { border: 0 !important; padding: 0 !important; }
		body { background: #fff !important; }
		.swpk-rep-tabla { page-break-inside: auto; }
		.swpk-rep-tabla tr { page-break-inside: avoid; }
	}
	.swpk-reporte { background:#fff; border:1px solid #c3c4c7; border-radius:6px; padding:34px 40px; max-width:900px; margin-top:14px }
	.swpk-rep-cab { border-bottom:2px solid #1d2327; padding-bottom:14px; margin-bottom:22px }
	.swpk-rep-cab h2 { margin:0 0 4px; font-size:22px }
	.swpk-rep-meta { color:#50575e; font-size:13px; margin:0 }
	.swpk-rep-h3 { font-size:15px; margin:26px 0 8px; padding-bottom:5px; border-bottom:1px solid #dcdcde }
	.swpk-rep-tabla { width:100%; border-collapse:collapse; font-size:13px }
	.swpk-rep-tabla th { text-align:left; padding:7px 8px; border-bottom:1px solid #c3c4c7; color:#50575e; font-size:12px }
	.swpk-rep-tabla td { padding:7px 8px; border-bottom:1px solid #f0f0f1; vertical-align:top }
	.swpk-rep-pie { margin-top:26px; padding-top:12px; border-top:1px solid #dcdcde; color:#646970; font-size:11px; line-height:1.5 }
</style>

<div class="wrap">
	<div class="swpk-no-imprimir">
		<?php SWPK_Vista::volver( $regreso['url'], $regreso['texto'] ); ?>

		<form method="get" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;margin-bottom:6px">
			<input type="hidden" name="page" value="panel-kinsta-reporte">
			<input type="hidden" name="sitio" value="<?php echo esc_attr( $sitio_id ); ?>">
			<label>Desde<br><input type="date" name="desde" value="<?php echo esc_attr( $desde ); ?>"></label>
			<label>Hasta<br><input type="date" name="hasta" value="<?php echo esc_attr( $hasta ); ?>"></label>
			<button class="button">Cambiar el periodo</button>
			<button type="button" class="button button-primary" onclick="window.print()">Imprimir o guardar en PDF</button>
			<a class="button" href="<?php echo esc_url( SWPK_Exportar::url( 'bitacora', [ 'sitio' => $sitio_id, 'desde' => $desde, 'hasta' => $hasta ] ) ); ?>">Bajar en Excel</a>
		</form>
		<p style="color:#50575e;margin:0 0 4px">
			En el diálogo de impresión, elegí <strong>Guardar como PDF</strong> como destino.
		</p>
	</div>

	<div class="swpk-reporte">
		<div class="swpk-rep-cab">
			<h2>Reporte de mantenimiento y notificaciones</h2>
			<p class="swpk-rep-meta">
				<strong><?php echo esc_html( $sitio->nombre_visible ); ?></strong> ·
				<?php echo esc_html( $sitio->dominio ); ?><br>
				Periodo: del <?php echo esc_html( mysql2date( 'j \d\e F \d\e Y', $desde ) ); ?>
				al <?php echo esc_html( mysql2date( 'j \d\e F \d\e Y', $hasta ) ); ?><br>
				Generado el <?php echo esc_html( current_time( 'j \d\e F \d\e Y, H:i' ) ); ?>
			</p>
		</div>

		<h3 class="swpk-rep-h3">1. Qué se le notificó al cliente en este periodo</h3>
		<?php if ( ! $avisos ) : ?>
			<p style="color:#50575e">No se envió ningún aviso de este sitio en el periodo seleccionado.</p>
		<?php else : ?>
			<table class="swpk-rep-tabla">
				<thead>
					<tr><th style="width:130px">Fecha</th><th style="width:110px">Tipo</th><th>Asunto</th><th style="width:200px">Destinatarios</th><th style="width:110px">Resultado</th></tr>
				</thead>
				<tbody>
				<?php foreach ( $avisos as $a ) : ?>
					<tr>
						<td><?php echo esc_html( mysql2date( 'd/m/Y H:i', $a->enviado_en ) ); ?></td>
						<td><?php echo esc_html( SWPK_Bitacora::tipos()[ $a->tipo ] ?? $a->tipo ); ?></td>
						<td><?php echo esc_html( (string) $a->asunto ); ?></td>
						<td style="font-size:12px"><?php echo esc_html( (string) $a->destinatarios ); ?></td>
						<td><?php echo esc_html( 'enviado' === $a->resultado ? 'Enviado' : (string) $a->resultado ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<p style="color:#646970;font-size:12px;margin-top:8px">
				Total: <strong><?php echo count( $avisos ); ?></strong> notificación(es) en el periodo.
			</p>
		<?php endif; ?>

		<h3 class="swpk-rep-h3">2. Qué cambió en el sitio</h3>
		<?php if ( ! $cambios ) : ?>
			<p style="color:#50575e">No se registraron cambios en el periodo seleccionado.</p>
		<?php else : ?>
			<table class="swpk-rep-tabla">
				<thead><tr><th style="width:130px">Fecha</th><th style="width:170px">Qué pasó</th><th>Detalle</th></tr></thead>
				<tbody>
				<?php foreach ( array_slice( $cambios, 0, 120 ) as $c ) : ?>
					<tr>
						<td><?php echo esc_html( mysql2date( 'd/m/Y H:i', $c->ocurrio_en ) ); ?></td>
						<td><?php echo esc_html( SWPK_Eventos::etiquetas()[ $c->tipo ] ?? $c->tipo ); ?></td>
						<td><?php echo esc_html( $c->titulo ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<h3 class="swpk-rep-h3">3. Estado al día de hoy</h3>
		<?php if ( ! $hallazgos ) : ?>
			<p style="color:#50575e">
				<?php
				echo SWPK_Riesgo::SIN_DATOS === SWPK_Riesgo::nivel_del_sitio( $sitio_id )
					? 'No hay información de qué hay instalado en este sitio: el proveedor de hosting no lo ha revisado.'
					: 'El sitio no tiene módulos pendientes de actualizar ni vulnerabilidades conocidas.';
				?>
			</p>
		<?php else : ?>
			<table class="swpk-rep-tabla">
				<thead><tr><th>Módulo</th><th style="width:150px">Versión</th><th style="width:80px">Nivel</th><th>Situación</th></tr></thead>
				<tbody>
				<?php foreach ( $hallazgos as $h ) : ?>
					<?php $v = $h['vulnerabilidades'][0] ?? null; ?>
					<tr>
						<td><?php echo esc_html( SWPK_Textos::limpiar_titulo( (string) $h['titulo'] ) ); ?></td>
						<td><?php echo esc_html( $h['version'] ); ?><?php echo '' !== $h['version_disponible'] ? ' → ' . esc_html( $h['version_disponible'] ) : ''; ?></td>
						<td><?php echo esc_html( SWPK_Riesgo::nombres()[ $h['nivel'] ] ?? '' ); ?></td>
						<td>
							<?php
							echo $v
								? esc_html( SWPK_Textos::explicar( (string) $v->titulo, (string) $v->descripcion ) . ( $v->cve ? ' (' . $v->cve . ')' : '' ) )
								: ( 'vulnerable_sin_detalle' === $h['motivo'] ? 'Versión marcada como vulnerable por el proveedor' : 'Actualización pendiente' );
							?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<div class="swpk-rep-pie">
			Este reporte lo generó automáticamente el panel de mantenimiento a partir de los registros
			del sistema. Cada notificación listada quedó guardada con su fecha, su destinatario y su
			contenido en el momento en que se envió.<br>
			Los datos de vulnerabilidades provienen de Wordfence Intelligence (Defiant, Inc.), usados
			bajo su licencia. Los identificadores CVE son de MITRE.
		</div>
	</div>
</div>
