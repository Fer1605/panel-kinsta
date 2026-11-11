<?php
/** Los sitios, agrupados por etiqueta. */
defined( 'ABSPATH' ) || exit;

$sitios  = SWPK_Inventario::todos();
$nombres = SWPK_Etiquetas::nombres();

$por_tag = [];
foreach ( $sitios as $s ) {
	$por_tag[ $s->tag ][] = $s;
}
?>
<div class="wrap">
	<h1>Sitios</h1>
	<?php SWPK_Admin::mostrar_mensaje(); ?>
	<?php SWPK_Admin::aviso_demo(); ?>

	<?php if ( ! $sitios ) : ?>
		<div class="notice notice-info"><p>No hay inventario todavía. Corré la sincronización desde el Resumen.</p></div>
	<?php endif; ?>

	<?php foreach ( $nombres as $tag => $nombre ) : ?>
		<?php if ( empty( $por_tag[ $tag ] ) ) { continue; } ?>

		<h2><?php echo esc_html( $nombre ); ?> <span style="color:#50575e;font-weight:400">(<?php echo count( $por_tag[ $tag ] ); ?>)</span></h2>

		<?php if ( ! SWPK_Etiquetas::es_nuestro( $tag ) ) : ?>
			<p style="color:#50575e;max-width:760px">
				Estos sitios los administra el cliente. Quedan fuera de la doble autenticación y de
				la lectura de usuarios: no podemos instalarles nada mientras no se les venda. Lo que
				sí se les vigila es todo lo que viene de Kinsta —plugins, versiones y
				vulnerabilidades— que es justo lo que hay que avisarles.
			</p>
		<?php endif; ?>

		<table class="widefat striped swpk-tabla">
			<thead>
				<tr>
					<th>Sitio</th>
					<th style="width:90px">Riesgo</th>
					<th style="width:150px">Pendientes</th>
					<th style="width:80px">WordPress</th>
					<th style="width:60px">PHP</th>
					<th style="width:160px">Doble autenticación</th>
					<th style="width:120px">Usuarios</th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $por_tag[ $tag ] as $s ) : ?>
				<?php
				$hallazgos = SWPK_Riesgo::hallazgos( $s->sitio_id );
				$nivel     = SWPK_Riesgo::nivel_del_sitio( $s->sitio_id );
				$vulns     = 0;
				foreach ( $hallazgos as $h ) {
					if ( 'actualizacion_pendiente' !== $h['motivo'] ) {
						++$vulns;
					}
				}
				?>
				<tr>
					<td>
						<strong><?php echo esc_html( $s->nombre_visible ); ?></strong><br>
						<span class="swpk-mono" style="color:#50575e"><?php echo esc_html( $s->dominio ); ?></span>
					</td>
					<td><?php echo wp_kses_post( SWPK_Admin::nivel_badge( $nivel ) ); ?></td>
					<td>
						<?php if ( $vulns ) : ?>
							<strong style="color:#b32d2e"><?php echo (int) $vulns; ?> con vulnerabilidad</strong><br>
						<?php endif; ?>
						<?php echo count( $hallazgos ) - $vulns; ?> por actualizar
					</td>
					<td><?php echo esc_html( $s->version_wp ?: '—' ); ?></td>
					<td><?php echo esc_html( $s->version_php ?: '—' ); ?></td>
					<td>
						<?php if ( ! SWPK_Etiquetas::es_nuestro( $tag ) ) : ?>
							<span style="color:#50575e">Fuera de alcance</span>
						<?php elseif ( $s->tiene_2fa ) : ?>
							<span style="color:#008a20">✓ <?php echo esc_html( $s->plugin_2fa ); ?></span>
						<?php else : ?>
							<span style="color:#d63638">Pendiente</span>
						<?php endif; ?>
					</td>
					<td>
						<?php if ( ! SWPK_Etiquetas::es_nuestro( $tag ) ) : ?>
							<span style="color:#50575e">Fuera de alcance</span>
						<?php elseif ( $s->cobertura_agente ) : ?>
							<span style="color:#008a20">✓ Con agente</span>
						<?php else : ?>
							<span style="color:#dba617">Sin cobertura</span>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	<?php endforeach; ?>
</div>
