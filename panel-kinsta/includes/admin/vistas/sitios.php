<?php
/**
 * Sitios: primero se elige el grupo, después se filtra, y el resultado va
 * paginado. Con 107 sitios, una sola lista con todo encima no se lee.
 */
defined( 'ABSPATH' ) || exit;

$todos    = SWPK_Perfiles::sitios_visibles();
$asignar  = SWPK_Perfiles::puede( SWPK_Perfiles::ADMINISTRAR );
$asesoras = $asignar ? SWPK_Perfiles::asesoras() : [];

$tag      = SWPK_Vista::tag_pedido();
$nivel    = SWPK_Vista::nivel_pedido();
$busqueda = SWPK_Vista::busqueda_pedida();

$por_tag   = SWPK_Vista::conteo_por_tag( $todos );
$del_grupo = SWPK_Vista::filtrar( $todos, $tag, '', '' );
$por_nivel = SWPK_Vista::conteo_por_nivel( $del_grupo );

$filtrados = SWPK_Vista::filtrar( $todos, $tag, $nivel, $busqueda );
[ $pagina_de_sitios, $pagina, $paginas ] = SWPK_Vista::paginar( $filtrados, SWPK_Vista::pagina_pedida() );
?>
<div class="wrap">
	<h1>Sitios</h1>
	<?php SWPK_Admin::mostrar_mensaje(); ?>
	<?php SWPK_Admin::tira_de_perfil(); ?>
	<?php SWPK_Admin::aviso_demo(); ?>

	<?php if ( ! $todos ) : ?>
		<div class="notice notice-info"><p>
			<?php
			echo SWPK_Perfiles::puede( SWPK_Perfiles::VER_TODO )
				? 'No hay inventario todavía. Corré la sincronización desde el Resumen.'
				: 'Todavía no tenés sitios asignados. Pedile a dirección que te los asigne.';
			?>
		</p></div>
		</div>
		<?php return; ?>
	<?php endif; ?>

	<?php SWPK_Vista::selector_de_grupo( 'panel-kinsta-sitios', $tag, $por_tag ); ?>

	<?php if ( '' === $tag ) : ?>
		</div>
		<?php return; ?>
	<?php endif; ?>

	<?php if ( ! SWPK_Etiquetas::es_nuestro( $tag ) ) : ?>
		<p style="color:#50575e;max-width:800px;margin:0">
			Estos sitios los administra el cliente. Quedan fuera de la doble autenticación y de la
			lectura de usuarios: no podemos instalarles nada mientras no se les venda. Lo que sí se
			vigila es todo lo que viene del hosting, que es justo lo que hay que avisarles.
		</p>
	<?php endif; ?>

	<?php SWPK_Vista::abrir_barra(); ?>
		<?php SWPK_Vista::filtro_de_riesgo( $nivel, $por_nivel ); ?>
		<?php SWPK_Vista::buscador( 'panel-kinsta-sitios', $busqueda, [ 'tag' => $tag, 'nivel' => $nivel ] ); ?>
	<?php SWPK_Vista::cerrar_barra(); ?>
	<?php SWPK_Vista::paginador( $pagina, $paginas, count( $filtrados ) ); ?>

	<?php if ( ! $pagina_de_sitios ) : ?>
		<div class="notice notice-info inline"><p>Ningún sitio coincide con ese filtro.</p></div>
	<?php else : ?>

		<?php if ( $asignar ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="swpk_asignar">
			<?php wp_nonce_field( 'swpk_asignar' ); ?>
		<?php endif; ?>

		<table class="widefat striped swpk-tabla">
			<thead>
				<tr>
					<th>Sitio</th>
					<th style="width:95px">Estado</th>
					<th style="width:140px">Pendientes</th>
					<th style="width:85px">WordPress</th>
					<th style="width:60px">PHP</th>
					<?php if ( SWPK_Etiquetas::es_nuestro( $tag ) ) : ?>
						<th style="width:120px">Doble paso</th>
					<?php endif; ?>
					<th style="width:165px">Asesora</th>
					<?php if ( $asignar ) : ?>
						<th style="width:190px">Contacto del cliente</th>
					<?php endif; ?>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $pagina_de_sitios as $s ) : ?>
				<?php
				$nivel_s   = SWPK_Riesgo::nivel_del_sitio( $s->sitio_id );
				$hallazgos = SWPK_Riesgo::hallazgos( $s->sitio_id );
				$vulns     = 0;
				foreach ( $hallazgos as $h ) {
					if ( 'actualizacion_pendiente' !== $h['motivo'] ) {
						++$vulns;
					}
				}
				?>
				<tr>
					<td>
						<a href="<?php echo esc_url( SWPK_Vista::url_del_sitio( (string) $s->sitio_id, 'panel-kinsta-sitios' ) ); ?>">
							<strong><?php echo esc_html( $s->nombre_visible ); ?></strong>
						</a><br>
						<span class="swpk-mono" style="color:#50575e"><?php echo esc_html( $s->dominio ); ?></span>
					</td>
					<td><?php echo wp_kses_post( SWPK_Admin::nivel_badge( $nivel_s ) ); ?></td>
					<td>
						<?php if ( SWPK_Riesgo::SIN_DATOS === $nivel_s ) : ?>
							<span style="color:#8c8f94">el hosting no ha escaneado este sitio</span>
						<?php else : ?>
							<?php if ( $vulns ) : ?>
								<strong style="color:#b32d2e"><?php echo (int) $vulns; ?> con vulnerabilidad</strong><br>
							<?php endif; ?>
							<?php echo count( $hallazgos ) - $vulns; ?> por actualizar
						<?php endif; ?>
					</td>
					<td><?php echo esc_html( $s->version_wp ?: '—' ); ?></td>
					<td><?php echo esc_html( $s->version_php ?: '—' ); ?></td>
					<?php if ( SWPK_Etiquetas::es_nuestro( $tag ) ) : ?>
						<td>
							<?php if ( $s->tiene_2fa ) : ?>
								<span style="color:#008a20">✓ <?php echo esc_html( $s->plugin_2fa ); ?></span>
							<?php else : ?>
								<span style="color:#d63638">Pendiente</span>
							<?php endif; ?>
						</td>
					<?php endif; ?>
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

		<?php if ( $asignar ) : ?>
			<?php if ( ! SWPK_Etiquetas::es_nuestro( $tag ) ) : ?>
				<p style="max-width:820px;color:#50575e;margin-top:12px">
					<strong>En este grupo el contacto del cliente hay que escribirlo a mano.</strong>
					En los sitios nuestros los destinatarios del aviso los da el agente instalado, pero
					aquí no hay agente — y son justo los que más hay que avisar.
				</p>
			<?php endif; ?>
			<?php submit_button( 'Guardar esta página' ); ?>
			</form>
		<?php endif; ?>

		<?php SWPK_Vista::paginador( $pagina, $paginas, count( $filtrados ) ); ?>
	<?php endif; ?>
</div>
