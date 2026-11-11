<?php
/** Los sitios, agrupados por etiqueta. */
defined( 'ABSPATH' ) || exit;

$sitios  = SWPK_Perfiles::sitios_visibles();
$asignar = SWPK_Perfiles::puede( SWPK_Perfiles::ADMINISTRAR );
$asesoras = $asignar ? SWPK_Perfiles::asesoras() : [];
$nombres = SWPK_Etiquetas::nombres();

$por_tag = [];
foreach ( $sitios as $s ) {
	$por_tag[ $s->tag ][] = $s;
}
?>
<div class="wrap">
	<h1>Sitios</h1>
	<?php SWPK_Admin::mostrar_mensaje(); ?>
	<?php SWPK_Admin::tira_de_perfil(); ?>
	<?php SWPK_Admin::aviso_demo(); ?>

	<?php if ( ! $sitios ) : ?>
		<div class="notice notice-info"><p>
			<?php
			echo SWPK_Perfiles::puede( SWPK_Perfiles::VER_TODO )
				? 'No hay inventario todavía. Corré la sincronización desde el Resumen.'
				: 'Todavía no tenés sitios asignados. Pedile a dirección que te los asigne desde esta misma pantalla.';
			?>
		</p></div>
	<?php endif; ?>

	<?php if ( $asignar && $sitios ) : ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="swpk_asignar">
		<?php wp_nonce_field( 'swpk_asignar' ); ?>
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
					<th style="width:170px">Asesora</th>
					<?php if ( $asignar ) : ?>
						<th style="width:200px">Contacto del cliente</th>
					<?php endif; ?>
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
					<td>
						<?php if ( $asignar ) : ?>
							<select name="asesora[<?php echo esc_attr( $s->sitio_id ); ?>]" style="width:100%">
								<option value="0">— sin asignar —</option>
								<?php foreach ( $asesoras as $a ) : ?>
									<option value="<?php echo (int) $a->ID; ?>" <?php selected( (int) $s->asesora_id, (int) $a->ID ); ?>>
										<?php echo esc_html( $a->display_name ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						<?php else : ?>
							<?php
							$a = $s->asesora_id ? get_user_by( 'id', (int) $s->asesora_id ) : null;
							echo esc_html( $a->display_name ?? '—' );
							?>
						<?php endif; ?>
					</td>
					<?php if ( $asignar ) : ?>
						<td>
							<input type="text" style="width:100%"
								name="contacto[<?php echo esc_attr( $s->sitio_id ); ?>]"
								value="<?php echo esc_attr( (string) $s->contacto_cliente ); ?>"
								placeholder="<?php echo SWPK_Etiquetas::es_nuestro( $tag ) ? 'lo da el agente' : 'correo del cliente'; ?>">
						</td>
					<?php endif; ?>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	<?php endforeach; ?>

	<?php if ( $asignar && $sitios ) : ?>
		<p style="max-width:840px;color:#50575e;margin-top:18px">
			<strong>El contacto del cliente importa sobre todo en los <em>no auto update</em>.</strong>
			En los sitios nuestros, los destinatarios del aviso salen del agente instalado: son los
			administradores del sitio. En los que administra el cliente no hay agente —y son justo
			los que más hay que avisar— así que su correo tiene que escribirse aquí a mano o el
			aviso no tiene a dónde salir.
		</p>
		<?php submit_button( 'Guardar asignaciones' ); ?>
		</form>
	<?php endif; ?>
</div>
