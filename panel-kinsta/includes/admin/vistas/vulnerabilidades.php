<?php
/**
 * Vulnerabilidades y pendientes, sitio por sitio, con el texto listo para la
 * asesora. Esta pantalla es la que convierte el panel en una herramienta de
 * venta y no solo de monitoreo.
 */
defined( 'ABSPATH' ) || exit;

$sitios = SWPK_Inventario::todos();
$abierto = isset( $_GET['sitio'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['sitio'] ) ) : '';

$con_hallazgos = [];
foreach ( $sitios as $s ) {
	$h = SWPK_Riesgo::hallazgos( $s->sitio_id );
	if ( $h ) {
		$con_hallazgos[] = [ 'sitio' => $s, 'hallazgos' => $h, 'nivel' => SWPK_Riesgo::nivel_del_sitio( $s->sitio_id ) ];
	}
}

usort(
	$con_hallazgos,
	static function ( $a, $b ) {
		$orden = [ SWPK_Riesgo::CRITICO => 0, SWPK_Riesgo::ALTO => 1, SWPK_Riesgo::MEDIO => 2, SWPK_Riesgo::BAJO => 3, SWPK_Riesgo::AL_DIA => 4 ];
		return ( $orden[ $a['nivel'] ] ?? 9 ) <=> ( $orden[ $b['nivel'] ] ?? 9 );
	}
);
?>
<div class="wrap">
	<h1>Vulnerabilidades y pendientes</h1>
	<?php SWPK_Admin::mostrar_mensaje(); ?>
	<?php SWPK_Admin::aviso_demo(); ?>

	<?php if ( ! $con_hallazgos ) : ?>
		<div class="notice notice-success"><p>
			No hay nada pendiente, o todavía no se ha corrido la primera sincronización.
		</p></div>
	<?php endif; ?>

	<?php foreach ( $con_hallazgos as $bloque ) : ?>
		<?php
		$s         = $bloque['sitio'];
		$hallazgos = $bloque['hallazgos'];
		$url       = add_query_arg(
			[ 'page' => 'panel-kinsta-vulnerabilidades', 'sitio' => $s->sitio_id ],
			admin_url( 'admin.php' )
		);
		$esta_abierto = $abierto === $s->sitio_id;
		?>
		<h2 style="margin-top:28px">
			<?php echo wp_kses_post( SWPK_Admin::nivel_badge( $bloque['nivel'] ) ); ?>
			<?php echo esc_html( $s->nombre_visible ); ?>
			<span style="font-weight:400;color:#50575e;font-size:13px">
				· <?php echo esc_html( SWPK_Etiquetas::nombres()[ $s->tag ] ); ?>
				· <?php echo count( $hallazgos ); ?> hallazgo(s)
			</span>
		</h2>

		<table class="widefat striped swpk-tabla">
			<thead>
				<tr>
					<th>Módulo</th>
					<th style="width:160px">Versión</th>
					<th style="width:100px">Nivel</th>
					<th>Por qué importa</th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $hallazgos as $h ) : ?>
				<tr>
					<td>
						<strong><?php echo esc_html( $h['titulo'] ); ?></strong><br>
						<span class="swpk-mono" style="color:#50575e"><?php echo esc_html( $h['slug'] ); ?></span>
						<?php if ( '' === $h['auto_update'] && SWPK_Etiquetas::es_nuestro( $s->tag ) ) : ?>
							<br><span style="color:#dba617;font-size:12px">Sin actualización automática</span>
						<?php endif; ?>
					</td>
					<td>
						<span class="swpk-mono"><?php echo esc_html( $h['version'] ); ?></span>
						<?php if ( '' !== $h['version_disponible'] ) : ?>
							→ <span class="swpk-mono" style="color:#008a20"><?php echo esc_html( $h['version_disponible'] ); ?></span>
						<?php endif; ?>
					</td>
					<td><?php echo wp_kses_post( SWPK_Admin::nivel_badge( $h['nivel'] ) ); ?></td>
					<td>
						<?php if ( ! empty( $h['vulnerabilidades'] ) ) : ?>
							<?php foreach ( $h['vulnerabilidades'] as $v ) : ?>
								<div style="margin-bottom:6px">
									<?php echo esc_html( (string) $v->titulo ); ?>
									<?php if ( '' !== (string) $v->cvss_rating ) : ?>
										<span style="color:#50575e">
											(<?php echo esc_html( (string) $v->cvss_rating ); ?>
											<?php echo esc_html( (string) $v->cvss_score ); ?>/10)
										</span>
									<?php endif; ?>
									<?php if ( '' !== (string) $v->cve ) : ?>
										<br><span class="swpk-mono" style="color:#50575e"><?php echo esc_html( (string) $v->cve ); ?></span>
									<?php endif; ?>
								</div>
							<?php endforeach; ?>
						<?php elseif ( 'vulnerable_sin_detalle' === $h['motivo'] ) : ?>
							Kinsta marca esta versión como vulnerable, pero el feed no trae el detalle.
						<?php else : ?>
							<span style="color:#50575e">Solo actualización pendiente, sin vulnerabilidad conocida.</span>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<p style="margin-top:10px">
			<?php if ( $esta_abierto ) : ?>
				<a class="button" href="<?php echo esc_url( remove_query_arg( 'sitio' ) ); ?>">Ocultar el texto para la asesora</a>
			<?php else : ?>
				<a class="button" href="<?php echo esc_url( $url ); ?>">Ver el texto para la asesora</a>
			<?php endif; ?>
		</p>

		<?php if ( $esta_abierto ) : ?>
			<p style="color:#50575e;margin-bottom:4px">
				Listo para copiar y pegar en una cotización. Sin tecnicismos que haya que traducir.
			</p>
			<textarea class="swpk-copiable" readonly onclick="this.select()"><?php
				echo esc_textarea( SWPK_Textos::para_asesora( $s, $hallazgos ) );
			?></textarea>
		<?php endif; ?>
	<?php endforeach; ?>

	<p class="swpk-atribucion"><?php echo wp_kses_post( SWPK_Wordfence::atribucion() ); ?></p>
</div>
