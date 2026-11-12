<?php
/**
 * Resumen: qué está bien y qué está mal, sin tener que interpretar nada.
 *
 * Esta pantalla abría con ocho contadores y el jefe los rechazó dos veces, con
 * meses de diferencia: "mucha información, pero no en paquete" en la reunión, y
 * "no pequeños resumenes de numeros" por escrito. Ahora abre con una frase que
 * dice el estado, y debajo la lista de lo que hay que atender con el nombre del
 * sitio puesto. Los conteos por grupo siguen en la pantalla de Sitios, que es
 * donde sirven para filtrar.
 *
 * El trabajo de armar todo eso vive en SWPK_Estado; aquí solo se pinta.
 */
defined( 'ABSPATH' ) || exit;

$estado    = SWPK_Estado::calcular();
$veredicto = SWPK_Estado::veredicto( $estado );

$visibles = array_map(
	static fn( $s ) => (string) $s->sitio_id,
	SWPK_Perfiles::sitios_visibles()
);

$eventos = array_filter(
	SWPK_Eventos::recientes( 60 ),
	static fn( $e ) => SWPK_Perfiles::puede( SWPK_Perfiles::VER_TODO ) || in_array( (string) $e->sitio_id, $visibles, true )
);
$eventos = array_slice( $eventos, 0, 25 );
$etq_evt = SWPK_Eventos::etiquetas();

/** Una fila de la cola de trabajo. */
$pintar_item = static function ( array $f ): void {
	$url = SWPK_Vista::url_del_sitio( $f['sitio_id'], 'resumen' );
	?>
	<li class="swpk-item">
		<div class="swpk-item-cab">
			<?php echo wp_kses_post( SWPK_Admin::nivel_badge( $f['nivel'] ) ); ?>
			<a class="swpk-item-sitio" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $f['nombre'] ); ?></a>
			<?php if ( $f['publicas'] ) : ?>
				<span class="swpk-item-pub">
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
		</div>
		<div class="swpk-item-det">
			<?php if ( '' !== $f['modulo'] ) : ?>
				<strong><?php echo esc_html( $f['modulo'] ); ?></strong>
				<?php if ( '' !== $f['version'] ) : ?>
					<span class="swpk-mono"><?php echo esc_html( $f['version'] ); ?><?php echo '' !== $f['disponible'] ? ' → ' . esc_html( $f['disponible'] ) : ''; ?></span>
				<?php endif; ?>
				·
			<?php endif; ?>
			<?php echo esc_html( $f['explicacion'] ); ?>
			<?php if ( $f['cuantos'] > 1 ) : ?>
				<span class="swpk-item-mas">y <?php echo (int) $f['cuantos'] - 1; ?> módulo(s) más en este sitio</span>
			<?php endif; ?>
		</div>
	</li>
	<?php
};
?>
<div class="wrap">
	<h1>Panel Kinsta</h1>
	<?php SWPK_Admin::mostrar_mensaje(); ?>
	<?php SWPK_Frescura::aviso(); ?>
	<?php SWPK_Admin::tira_de_perfil(); ?>
	<?php SWPK_Admin::aviso_demo(); ?>
	<?php if ( SWPK_Perfiles::puede( SWPK_Perfiles::ADMINISTRAR ) ) { SWPK_Admin::barra_acciones(); } ?>

	<?php if ( ! $estado['total'] ) : ?>

		<div class="notice notice-info"><p>
			Todavía no hay inventario. Dale a <strong>Correr ahora</strong> para traer los sitios
			y tomar el primer inventario.
		</p></div>

	<?php else : ?>

		<div class="swpk-veredicto swpk-v-<?php echo esc_attr( $veredicto['tono'] ); ?>">
			<div class="swpk-v-titulo"><?php echo esc_html( $veredicto['titulo'] ); ?></div>
			<div class="swpk-v-detalle"><?php echo esc_html( $veredicto['detalle'] ); ?></div>
		</div>

		<?php if ( $estado['caidos'] ) : ?>
			<h2 class="swpk-seccion">No responden ahora mismo</h2>
			<ul class="swpk-cola">
				<?php foreach ( $estado['caidos'] as $c ) : ?>
					<li class="swpk-item">
						<div class="swpk-item-cab">
							<span class="swpk-nivel" style="background:#b32d2e">Caído</span>
							<a class="swpk-item-sitio" href="<?php echo esc_url( SWPK_Vista::url_del_sitio( $c['sitio_id'], 'resumen' ) ); ?>"><?php echo esc_html( $c['nombre'] ); ?></a>
						</div>
						<div class="swpk-item-det">
							<?php echo esc_html( $c['motivo'] ?: 'No contesta.' ); ?>
							<?php if ( $c['desde'] ) : ?>
								<span class="swpk-item-mas">desde <?php echo esc_html( SWPK_Frescura::fmt( 'j M, H:i', $c['desde'] ) ); ?></span>
							<?php endif; ?>
						</div>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<?php if ( $estado['urgentes'] ) : ?>
			<h2 class="swpk-seccion">Lo que no puede esperar</h2>
			<ul class="swpk-cola">
				<?php foreach ( $estado['urgentes'] as $f ) { $pintar_item( $f ); } ?>
			</ul>
		<?php endif; ?>

		<?php if ( $estado['resto'] ) : ?>
			<h2 class="swpk-seccion">Lo de esta semana</h2>
			<ul class="swpk-cola swpk-cola-suave">
				<?php foreach ( array_slice( $estado['resto'], 0, 8 ) as $f ) { $pintar_item( $f ); } ?>
			</ul>
			<?php if ( count( $estado['resto'] ) > 8 ) : ?>
				<p class="swpk-nota">
					Y <?php echo count( $estado['resto'] ) - 8; ?> sitio(s) más con cosas pendientes, ninguna urgente.
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=panel-kinsta-vulnerabilidades' ) ); ?>">Verlos todos</a>.
				</p>
			<?php endif; ?>
		<?php endif; ?>

		<?php if ( $estado['sin_datos'] ) : ?>
			<h2 class="swpk-seccion">De estos no sabemos qué tienen</h2>
			<ul class="swpk-cola swpk-cola-suave">
				<?php foreach ( $estado['sin_datos'] as $s ) : ?>
					<li class="swpk-item">
						<div class="swpk-item-cab">
							<span class="swpk-nivel" style="background:#8c8f94">Sin datos</span>
							<a class="swpk-item-sitio" href="<?php echo esc_url( SWPK_Vista::url_del_sitio( $s['sitio_id'], 'resumen' ) ); ?>"><?php echo esc_html( $s['nombre'] ); ?></a>
						</div>
						<div class="swpk-item-det">
							<?php echo esc_html( SWPK_Admin::proveedor() ); ?> no lo ha escaneado, así que su lista de
							módulos llega vacía. <em>Vacía no quiere decir limpia: quiere decir que no sabemos.</em>
						</div>
					</li>
				<?php endforeach; ?>
			</ul>
			<p class="swpk-nota">
				Esa lista la arma <?php echo esc_html( SWPK_Admin::proveedor() ); ?> por su cuenta, sitio por sitio y
				cada uno a su ritmo, así que normalmente aparece sola en unos días. Si pasa una semana así,
				vale la pena abrirles un ticket.
			</p>
		<?php endif; ?>

		<div class="swpk-bien">
			<strong>Lo que sí está bien:</strong>
			<?php echo (int) $estado['al_dia']; ?> de <?php echo (int) $estado['total']; ?> sitios no tienen nada pendiente<?php
			if ( ! $estado['caidos'] ) {
				echo ' · ninguno está caído';
			}
			// El doble paso solo se nombra aquí cuando ya hay alguno. "0 de 94
			// ya lo tienen" no es una buena noticia, y meterlo en la línea de
			// lo que está bien es la clase de maquillaje que vuelve inútil a
			// un panel.
			$con_2fa = (int) $estado['nuestros'] - (int) $estado['sin_2fa'];
			if ( $con_2fa > 0 ) {
				printf(
					' · %d de %d sitios nuestros ya tienen la verificación en dos pasos',
					$con_2fa,
					(int) $estado['nuestros']
				);
			}
			?>.
		</div>

		<?php if ( $estado['nuestros'] && (int) $estado['nuestros'] === (int) $estado['sin_2fa'] ) : ?>
			<p class="swpk-nota">
				Pendiente aparte, que no es de hoy ni de esta semana: ninguno de los
				<?php echo (int) $estado['nuestros']; ?> sitios nuestros tiene todavía la verificación en dos
				pasos. Se activa al instalar el plugin, sitio por sitio.
			</p>
		<?php endif; ?>

		<?php SWPK_Frescura::sello(); ?>

		<h2 class="swpk-seccion">Lo que cambió desde la corrida anterior</h2>
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
