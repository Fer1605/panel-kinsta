<?php
/**
 * Resumen: una sola tabla con los 107 sitios, del peor al que no tiene nada.
 *
 * Tercera versión de esta pantalla, y las dos anteriores sobran por lo mismo.
 * La primera abría con ocho contadores y el jefe los rechazó. La segunda los
 * cambió por una cola de trabajo con etiquetas de color, píldoras rojas y
 * cuatro apartados, y salió peor: más desorden, no menos.
 *
 * Lo que pidió, dicho tres veces de tres maneras, es lo mismo: "algo simple",
 * "mucha información, pero no en paquete", "veo las últimas notificaciones y
 * actualizaciones, eso únicamente". Eso es una tabla.
 *
 * Así que aquí no hay secciones, ni tarjetas, ni colores de adorno. Una línea
 * que dice cómo está la cosa, y debajo los 107 sitios en una tabla ordenada de
 * peor a mejor. El orden hace el trabajo que antes hacían los títulos: lo que
 * hay que atender está arriba por ser lo peor, no por estar en un apartado.
 *
 * El rojo se usa una sola vez y para una sola cosa: que la falla se pueda
 * aprovechar desde internet sin tener cuenta en el sitio. Si el rojo marcara
 * tres cosas distintas volvería a no marcar ninguna.
 */
defined( 'ABSPATH' ) || exit;

$estado    = SWPK_Estado::calcular();
$veredicto = SWPK_Estado::veredicto( $estado );
$filas     = SWPK_Estado::tabla( $estado );

$nombres_nivel = SWPK_Riesgo::nombres() + [ 'caido' => 'Caído' ];
$nombres_grupo = SWPK_Etiquetas::nombres();

$visibles = array_map( static fn( $s ) => (string) $s->sitio_id, SWPK_Perfiles::sitios_visibles() );
$eventos  = array_filter(
	SWPK_Eventos::recientes( 60 ),
	static fn( $e ) => SWPK_Perfiles::puede( SWPK_Perfiles::VER_TODO ) || in_array( (string) $e->sitio_id, $visibles, true )
);
$eventos  = array_slice( $eventos, 0, 20 );
$etq_evt  = SWPK_Eventos::etiquetas();
?>
<div class="wrap">
	<h1>Panel Kinsta</h1>
	<?php SWPK_Admin::mostrar_mensaje(); ?>
	<?php SWPK_Frescura::aviso(); ?>
	<?php SWPK_Admin::tira_de_perfil(); ?>
	<?php SWPK_Admin::aviso_demo(); ?>
	<?php if ( SWPK_Perfiles::puede( SWPK_Perfiles::ADMINISTRAR ) ) { SWPK_Admin::barra_acciones(); } ?>

	<?php if ( ! $filas ) : ?>

		<div class="notice notice-info"><p>
			Todavía no hay inventario. Dale a <strong>Correr ahora</strong> para traer los sitios
			y tomar el primer inventario.
		</p></div>

	<?php else : ?>

		<p class="swpk-linea">
			<strong><?php echo esc_html( $veredicto['titulo'] ); ?>.</strong>
			<?php echo esc_html( $veredicto['detalle'] ); ?>
		</p>

		<table class="widefat striped swpk-tabla">
			<thead>
				<tr>
					<th>Sitio</th>
					<th style="width:120px">Grupo</th>
					<th style="width:100px">Estado</th>
					<th>Qué tiene pendiente</th>
					<th style="width:110px">Revisado</th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $filas as $f ) : ?>
				<tr>
					<td>
						<a href="<?php echo esc_url( SWPK_Vista::url_del_sitio( $f['sitio_id'], 'resumen' ) ); ?>">
							<?php echo esc_html( $f['nombre'] ); ?>
						</a>
					</td>
					<td class="swpk-gris"><?php echo esc_html( $nombres_grupo[ $f['tag'] ] ?? $f['tag'] ); ?></td>
					<td><?php echo esc_html( $nombres_nivel[ $f['nivel'] ] ?? $f['nivel'] ); ?></td>
					<td>
						<?php if ( 'caido' === $f['nivel'] ) : ?>
							<?php echo esc_html( $f['explicacion'] ); ?>
							<?php if ( ! empty( $f['desde'] ) ) : ?>
								<span class="swpk-gris">desde <?php echo esc_html( SWPK_Frescura::fmt( 'j M, H:i', $f['desde'] ) ); ?></span>
							<?php endif; ?>
						<?php elseif ( SWPK_Riesgo::SIN_DATOS === $f['nivel'] ) : ?>
							<span class="swpk-gris"><?php echo esc_html( SWPK_Admin::proveedor() ); ?> no lo ha escaneado: no sabemos qué tiene.</span>
						<?php elseif ( '' === $f['modulo'] ) : ?>
							<span class="swpk-gris">—</span>
						<?php else : ?>
							<?php
							// Solo la primera frase. El resto de la explicación es
							// para el mensaje al cliente, no para una tabla que se
							// recorre con la vista: en una celda, dos renglones de
							// más por fila son cien renglones de más en la página.
							$corta = (string) $f['explicacion'];
							$punto = strpos( $corta, '. ' );
							if ( false !== $punto ) {
								$corta = substr( $corta, 0, $punto + 1 );
							}
							?>
							<strong><?php echo esc_html( $f['modulo'] ); ?></strong>
							<span class="swpk-mono"><?php echo esc_html( $f['version'] ); ?><?php echo '' !== $f['disponible'] ? ' → ' . esc_html( $f['disponible'] ) : ''; ?></span>
							<?php if ( $f['cuantos'] > 1 ) : ?>
								<span class="swpk-gris">y <?php echo (int) $f['cuantos'] - 1; ?> más</span>
							<?php endif; ?>
							<br><?php echo esc_html( $corta ); ?>
							<?php if ( $f['publicas'] ) : ?>
								<br><span class="swpk-rojo">
									<?php
									printf(
										esc_html( 1 === $f['publicas']
											? '%d se aprovecha desde internet sin cuenta'
											: '%d se aprovechan desde internet sin cuenta' ),
										(int) $f['publicas']
									);
									?>
								</span>
							<?php endif; ?>
						<?php endif; ?>
					</td>
					<td class="swpk-gris">
						<?php echo esc_html( '' !== $f['revisado_en'] ? SWPK_Frescura::fmt( 'j M, H:i', $f['revisado_en'] ) : 'nunca' ); ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<?php SWPK_Frescura::sello(); ?>

		<h2>Lo que cambió desde la corrida anterior</h2>
		<?php if ( ! $eventos ) : ?>
			<p>
				Nada todavía. La historia empieza con la segunda corrida: el panel compara el
				inventario de hoy con el de ayer, y hoy no hay con qué comparar. Kinsta no guarda un
				historial consultable, así que no se puede reconstruir hacia atrás.
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
						<td><?php echo esc_html( SWPK_Frescura::fmt( 'j M, H:i', $e->ocurrio_en ) ); ?></td>
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
