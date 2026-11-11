<?php
/** Resumen: el panel "en paquete" que pidió el jefe (00:00). */
defined( 'ABSPATH' ) || exit;

$conteo   = SWPK_Inventario::conteo_por_tag();
$sitios   = SWPK_Inventario::todos();
$nombres  = SWPK_Etiquetas::nombres();
$eventos  = SWPK_Eventos::recientes( 25 );
$etq_evt  = SWPK_Eventos::etiquetas();

$criticos    = 0;
$con_pend    = 0;
$sin_2fa     = 0;
$nuestros    = 0;
foreach ( $sitios as $s ) {
	$nivel = SWPK_Riesgo::nivel_del_sitio( $s->sitio_id );
	if ( in_array( $nivel, [ SWPK_Riesgo::CRITICO, SWPK_Riesgo::ALTO ], true ) ) {
		++$criticos;
	}
	if ( SWPK_Riesgo::AL_DIA !== $nivel ) {
		++$con_pend;
	}
	if ( SWPK_Etiquetas::es_nuestro( $s->tag ) ) {
		++$nuestros;
		if ( ! $s->tiene_2fa ) {
			++$sin_2fa;
		}
	}
}

$mes_desde = current_time( 'Y-m-01 00:00:00' );
$mes_hasta = current_time( 'Y-m-d H:i:s' );
$avisos    = 0;
foreach ( $sitios as $s ) {
	$avisos += SWPK_Bitacora::conteo( $s->sitio_id, $mes_desde, $mes_hasta );
}
?>
<div class="wrap">
	<h1>Panel Kinsta</h1>
	<?php SWPK_Admin::mostrar_mensaje(); ?>
	<?php SWPK_Admin::aviso_demo(); ?>
	<?php SWPK_Admin::barra_acciones(); ?>

	<?php if ( ! $sitios ) : ?>
		<div class="notice notice-info"><p>
			Todavía no hay inventario. Dale a <strong>Correr ahora</strong> para traer los sitios
			y tomar la primera foto.
		</p></div>
	<?php else : ?>

		<div class="swpk-tarjetas">
			<?php foreach ( $nombres as $tag => $nombre ) : ?>
				<?php if ( 0 === $conteo[ $tag ] && SWPK_Etiquetas::SIN === $tag ) { continue; } ?>
				<div class="swpk-tarjeta">
					<div class="swpk-n"><?php echo (int) $conteo[ $tag ]; ?></div>
					<div class="swpk-r"><?php echo esc_html( $nombre ); ?></div>
				</div>
			<?php endforeach; ?>
		</div>

		<div class="swpk-tarjetas">
			<div class="swpk-tarjeta">
				<div class="swpk-n" style="color:#b32d2e"><?php echo (int) $criticos; ?></div>
				<div class="swpk-r">Sitios con riesgo alto o crítico</div>
			</div>
			<div class="swpk-tarjeta">
				<div class="swpk-n"><?php echo (int) $con_pend; ?></div>
				<div class="swpk-r">Sitios con algo pendiente</div>
			</div>
			<div class="swpk-tarjeta">
				<div class="swpk-n"><?php echo (int) $sin_2fa; ?> <span style="font-size:15px;color:#50575e">de <?php echo (int) $nuestros; ?></span></div>
				<div class="swpk-r">Sitios nuestros sin doble autenticación</div>
			</div>
			<div class="swpk-tarjeta">
				<div class="swpk-n"><?php echo (int) $avisos; ?></div>
				<div class="swpk-r">Avisos enviados este mes</div>
			</div>
		</div>

		<h2>Lo que pasó en la última corrida</h2>
		<?php if ( ! $eventos ) : ?>
			<p>
				Nada todavía. La historia empieza con la segunda corrida: el panel compara la foto
				de hoy con la de ayer, y hoy no hay con qué comparar. Kinsta no guarda un historial
				consultable, así que no se puede reconstruir hacia atrás.
			</p>
		<?php else : ?>
			<table class="widefat striped swpk-tabla">
				<thead>
					<tr>
						<th style="width:140px">Cuándo</th>
						<th style="width:170px">Qué</th>
						<th>Sitio</th>
						<th>Detalle</th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $eventos as $e ) : ?>
					<?php $sitio = SWPK_Inventario::obtener( $e->sitio_id ); ?>
					<tr>
						<td><?php echo esc_html( mysql2date( 'j M, H:i', $e->ocurrio_en ) ); ?></td>
						<td><?php echo esc_html( $etq_evt[ $e->tipo ] ?? $e->tipo ); ?></td>
						<td><?php echo esc_html( $sitio->nombre_visible ?? $e->sitio_id ); ?></td>
						<td><?php echo esc_html( $e->titulo ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	<?php endif; ?>

	<p class="swpk-atribucion"><?php echo wp_kses_post( SWPK_Wordfence::atribucion() ); ?></p>
</div>
