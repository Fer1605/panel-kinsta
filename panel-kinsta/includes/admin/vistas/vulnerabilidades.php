<?php
/**
 * Vulnerabilidades y pendientes. Mismo recorrido que Sitios: primero el grupo,
 * después el filtro, y de a una página por vez.
 */
defined( 'ABSPATH' ) || exit;

$todos    = SWPK_Perfiles::sitios_visibles();
$tag      = SWPK_Vista::tag_pedido();
$nivel    = SWPK_Vista::nivel_pedido();
$busqueda = SWPK_Vista::busqueda_pedida();
$abierto  = isset( $_GET['sitio'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['sitio'] ) ) : '';

$por_tag = SWPK_Vista::conteo_por_tag( $todos );

// Aquí solo interesan los sitios que tienen algo pendiente.
$con_algo = array_values(
	array_filter(
		SWPK_Vista::filtrar( $todos, $tag, $nivel, $busqueda ),
		static fn( $s ) => ! in_array(
			SWPK_Riesgo::nivel_del_sitio( $s->sitio_id ),
			[ SWPK_Riesgo::AL_DIA, SWPK_Riesgo::SIN_DATOS ],
			true
		)
	)
);

$orden = [ SWPK_Riesgo::CRITICO => 0, SWPK_Riesgo::ALTO => 1, SWPK_Riesgo::MEDIO => 2, SWPK_Riesgo::BAJO => 3 ];
usort(
	$con_algo,
	static fn( $a, $b ) => ( $orden[ SWPK_Riesgo::nivel_del_sitio( $a->sitio_id ) ] ?? 9 )
		<=> ( $orden[ SWPK_Riesgo::nivel_del_sitio( $b->sitio_id ) ] ?? 9 )
);

$por_nivel = SWPK_Vista::conteo_por_nivel( SWPK_Vista::filtrar( $todos, $tag, '', '' ) );
unset( $por_nivel[ SWPK_Riesgo::AL_DIA ], $por_nivel[ SWPK_Riesgo::SIN_DATOS ] );

[ $en_pantalla, $pagina, $paginas ] = SWPK_Vista::paginar( $con_algo, SWPK_Vista::pagina_pedida(), 5 );
?>
<div class="wrap">
	<h1>Vulnerabilidades y pendientes</h1>
	<?php SWPK_Admin::mostrar_mensaje(); ?>
	<?php SWPK_Admin::tira_de_perfil(); ?>
	<?php SWPK_Admin::aviso_demo(); ?>

	<?php SWPK_Vista::selector_de_grupo( 'panel-kinsta-vulnerabilidades', $tag, $por_tag ); ?>

	<?php if ( '' === $tag ) : ?>
		</div>
		<?php return; ?>
	<?php endif; ?>

	<?php SWPK_Vista::filtro_de_riesgo( $nivel, $por_nivel ); ?>
	<?php SWPK_Vista::buscador( 'panel-kinsta-vulnerabilidades', $busqueda, [ 'tag' => $tag, 'nivel' => $nivel ] ); ?>
	<?php SWPK_Vista::paginador( $pagina, $paginas, count( $con_algo ), 'sitios con pendientes' ); ?>

	<?php if ( ! $en_pantalla ) : ?>
		<div class="notice notice-success inline"><p>
			Nada pendiente en este grupo con ese filtro.
		</p></div>
	<?php endif; ?>

	<?php foreach ( $en_pantalla as $s ) : ?>
		<?php
		$hallazgos = SWPK_Riesgo::hallazgos( $s->sitio_id );
		$nivel_s   = SWPK_Riesgo::nivel_del_sitio( $s->sitio_id );
		$es_este   = $abierto === $s->sitio_id;
		$graves    = array_values(
			array_filter(
				$hallazgos,
				static fn( $h ) => in_array( $h['nivel'], [ SWPK_Riesgo::CRITICO, SWPK_Riesgo::ALTO ], true )
			)
		);
		$total_graves = count( $graves );
		$sobran       = max( 0, $total_graves - SWPK_Textos::MAXIMO_EN_MENSAJE );
		$graves       = array_slice( $graves, 0, SWPK_Textos::MAXIMO_EN_MENSAJE );
		?>
		<h2 style="margin-top:30px">
			<?php echo wp_kses_post( SWPK_Admin::nivel_badge( $nivel_s ) ); ?>
			<?php echo esc_html( $s->nombre_visible ); ?>
			<span style="font-weight:400;color:#50575e;font-size:13px">
				· <?php echo count( $hallazgos ); ?> hallazgo(s)
			</span>
		</h2>

		<table class="widefat striped swpk-tabla">
			<thead>
				<tr>
					<th>Módulo</th>
					<th style="width:155px">Versión</th>
					<th style="width:95px">Nivel</th>
					<th>Por qué importa</th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( array_slice( $hallazgos, 0, $es_este ? 100 : 6 ) as $h ) : ?>
				<tr>
					<td>
						<strong><?php echo esc_html( $h['titulo'] ); ?></strong><br>
						<span class="swpk-mono" style="color:#50575e"><?php echo esc_html( $h['slug'] ); ?></span>
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
							<?php $v = $h['vulnerabilidades'][0]; ?>
							<?php echo esc_html( SWPK_Textos::explicar( (string) $v->titulo, (string) $v->descripcion ) ); ?>
							<?php if ( '' !== (string) $v->cve ) : ?>
								<br><span class="swpk-mono" style="color:#50575e"><?php echo esc_html( (string) $v->cve ); ?></span>
							<?php endif; ?>
						<?php elseif ( 'vulnerable_sin_detalle' === $h['motivo'] ) : ?>
							<span style="color:#d63638">El hosting marca esta versión como vulnerable</span>, pero
							el feed todavía no trae el detalle.
						<?php else : ?>
							<span style="color:#50575e">Solo actualización pendiente, sin vulnerabilidad conocida.</span>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			<?php if ( ! $es_este && count( $hallazgos ) > 6 ) : ?>
				<tr>
					<td colspan="4" style="color:#50575e">
						y <?php echo count( $hallazgos ) - 6; ?> más…
					</td>
				</tr>
			<?php endif; ?>
			</tbody>
		</table>

		<p style="margin-top:10px">
			<?php $url = add_query_arg( [ 'page' => 'panel-kinsta-vulnerabilidades', 'tag' => $tag, 'nivel' => $nivel, 'p' => $pagina, 'sitio' => $s->sitio_id ], admin_url( 'admin.php' ) ); ?>
			<?php if ( $es_este ) : ?>
				<a class="button" href="<?php echo esc_url( remove_query_arg( 'sitio' ) ); ?>">Cerrar</a>
			<?php else : ?>
				<a class="button button-primary" href="<?php echo esc_url( $url ); ?>">Ver el mensaje para la asesora</a>
			<?php endif; ?>
		</p>

		<?php if ( $es_este ) : ?>
			<?php $plano = SWPK_Textos::para_asesora( $s, $hallazgos ); ?>
			<div class="swpk-mensaje" id="msg-<?php echo esc_attr( $s->sitio_id ); ?>">
				<p class="swpk-mensaje-intro">
					<?php if ( $graves ) : ?>
						El sitio <strong><?php echo esc_html( $s->nombre_visible ); ?></strong> tiene
						<strong><?php echo (int) $total_graves; ?></strong> módulo(s) con una vulnerabilidad de
						seguridad conocida públicamente.
						<?php if ( $sobran ) : ?>
							Estos son los <?php echo count( $graves ); ?> más graves; los otros
							<?php echo (int) $sobran; ?> están en el reporte completo:
						<?php else : ?>
							Recomendamos actualizarlos:
						<?php endif; ?>
					<?php else : ?>
						El sitio <strong><?php echo esc_html( $s->nombre_visible ); ?></strong> tiene
						<?php echo count( $hallazgos ); ?> módulo(s) con actualización pendiente. Ninguno
						tiene una vulnerabilidad conocida hoy, pero mantenerlos al día es lo que evita
						llegar a ese punto.
					<?php endif; ?>
				</p>

				<?php foreach ( $graves as $i => $h ) : ?>
					<?php $v = $h['vulnerabilidades'][0] ?? null; ?>
					<div class="swpk-mod">
						<div class="swpk-mod-nombre"><?php echo (int) $i + 1; ?>. <?php echo esc_html( $h['titulo'] ); ?></div>
						<div class="swpk-mod-ver">
							Versión instalada <?php echo esc_html( $h['version'] ); ?>
							→ actualizar a <b><?php echo esc_html( $h['version_disponible'] ?: 'la última disponible' ); ?></b>
							<?php if ( $v && '' !== (string) $v->cvss_rating ) : ?>
								· riesgo <?php echo esc_html( strtolower( (string) $v->cvss_rating ) ); ?>
								(<?php echo esc_html( (string) $v->cvss_score ); ?> sobre 10)
							<?php endif; ?>
						</div>
						<?php if ( $v ) : ?>
							<p class="swpk-mod-que"><?php echo esc_html( SWPK_Textos::explicar( (string) $v->titulo, (string) $v->descripcion ) ); ?></p>
							<?php $corrige = SWPK_Textos::version_que_corrige( $v, (string) $h['version_disponible'] ); ?>
							<?php if ( '' !== $corrige ) : ?>
								<p class="swpk-mod-ojo">
									Atención: la versión <?php echo esc_html( $h['version_disponible'] ); ?> todavía
									está afectada. Hay que llegar a la <?php echo esc_html( $corrige ); ?> o posterior.
								</p>
							<?php endif; ?>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>

				<?php if ( $graves ) : ?>
					<p class="swpk-cierre">
						Mientras el sitio siga con estas versiones, queda expuesto a un ataque
						automatizado: estas fallas se publican y los robots las buscan por internet sin
						elegir a quién.
					</p>
				<?php endif; ?>

				<textarea id="plano-<?php echo esc_attr( $s->sitio_id ); ?>" style="position:absolute;left:-9999px" readonly><?php echo esc_textarea( $plano ); ?></textarea>
				<p class="swpk-copiar">
					<button type="button" class="button button-primary"
						onclick="var t=document.getElementById('plano-<?php echo esc_js( $s->sitio_id ); ?>');t.select();document.execCommand('copy');this.textContent='✓ Copiado';">
						Copiar el texto
					</button>
					<span style="color:#50575e;margin-left:8px">listo para pegar en una cotización o un correo</span>
				</p>
			</div>
		<?php endif; ?>
	<?php endforeach; ?>

	<?php if ( $en_pantalla ) : ?>
		<?php SWPK_Vista::paginador( $pagina, $paginas, count( $con_algo ), 'sitios con pendientes' ); ?>
	<?php endif; ?>

	<p class="swpk-atribucion"><?php echo wp_kses_post( SWPK_Wordfence::atribucion() ); ?></p>
</div>
