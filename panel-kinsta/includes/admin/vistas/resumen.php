<?php
/**
 * Inicio: el diario de lo que pasó. No un tablero del estado de la cartera.
 *
 * ─── Por qué esta pantalla cambió tres veces ────────────────────────────────
 *
 * Las tres versiones anteriores contestaban "¿cómo está la cartera?" y por eso
 * siempre salía o un paquete de contadores o un muro de 107 filas. Pero eso no
 * es lo que pidió:
 *
 *   "Entro a ese panel y veo las últimas notificaciones y actualizaciones.
 *    Eso, su únicamente."                                              (07:58)
 *
 *   "Mira, en esta corrida del día pasó esto. No pasó nada, perfecto, se queda
 *    como está. Eso no me afecta."                                     (04:34)
 *
 * Esa segunda frase es la que faltaba leer. No abre el panel a evaluar 107
 * sitios: lo abre a ver qué pasó desde la última vez. Y una pantalla de estado
 * tiene que resumir 107 cosas siempre, mientras que una de "qué pasó" tiene
 * cinco filas en un día normal — y en un día tranquilo puede decir que no pasó
 * nada, que es exactamente la respuesta que él dijo que le sirve.
 *
 * Ninguna de las tres versiones anteriores podía decir "no pasó nada".
 *
 * ─── Lo que un diario no cuenta, y cómo se tapa ─────────────────────────────
 *
 * Un sitio que lleva tres semanas con una falla sin atender no genera eventos:
 * no cambió nada. Por eso arriba va una línea que dice cuántos siguen
 * pendientes de antes, con su enlace. Es el único puente que hace falta.
 *
 * Los 107 sitios viven en la pantalla de Sitios, agrupados por etiqueta y con
 * los botones para actuar. Ahí es donde uno va a trabajar; aquí, a enterarse.
 */
defined( 'ABSPATH' ) || exit;

$estado   = SWPK_Estado::calcular();
$sin_leer = count( $estado['urgentes'] );

$sitios = [];
foreach ( SWPK_Perfiles::sitios_visibles() as $s ) {
	$sitios[ (string) $s->sitio_id ] = $s;
}

$eventos = array_filter(
	SWPK_Eventos::recientes( 120 ),
	static fn( $e ) => isset( $sitios[ (string) $e->sitio_id ] ) || SWPK_Perfiles::puede( SWPK_Perfiles::VER_TODO )
);

// Agrupados por día, el de hoy primero. El día de hoy aparece siempre, tenga
// o no tenga nada: es el que contesta "¿pasó algo?".
$hoy      = current_time( 'Y-m-d' );
$por_dia  = [ $hoy => [] ];
foreach ( $eventos as $e ) {
	$dia = substr( (string) $e->ocurrio_en, 0, 10 );
	$por_dia[ $dia ][] = $e;
}

$por_dia = array_slice( $por_dia, 0, 8, true );
$etq_evt = SWPK_Eventos::etiquetas();
$grupos  = SWPK_Etiquetas::nombres();
$de_hoy  = count( $por_dia[ $hoy ] ?? [] );
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

		<p class="swpk-linea">
			<strong>
				<?php
				echo 0 === $de_hoy
					? esc_html( sprintf( 'Hoy no ha cambiado nada en los %d sitios.', (int) $estado['total'] ) )
					: esc_html( sprintf( 'Hoy cambiaron %d cosas.', $de_hoy ) );
				?>
			</strong>
			<?php if ( $sin_leer ) : ?>
				Y <?php echo (int) $sin_leer; ?> sitio(s) siguen con algo sin atender desde antes:
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=panel-kinsta-vulnerabilidades' ) ); ?>">verlos</a>.
			<?php else : ?>
				Y no hay nada sin atender de antes.
			<?php endif; ?>
		</p>

		<table class="widefat striped swpk-tabla">
			<thead>
				<tr>
					<th style="width:70px">Hora</th>
					<th style="width:210px">Sitio</th>
					<th style="width:130px">Grupo</th>
					<th>Qué pasó</th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $por_dia as $dia => $del_dia ) : ?>
				<tr class="swpk-dia">
					<td colspan="4">
						<?php
						// ucfirst y no CSS: text-transform:capitalize mayusculiza
						// cada palabra y deja "Jueves 12 De Noviembre".
						echo esc_html( ucfirst( SWPK_Frescura::fmt( 'l j \d\e F', $dia . ' 12:00:00' ) ) );
						?>
						<?php if ( $dia === $hoy ) : ?><span class="swpk-gris"> · hoy</span><?php endif; ?>
					</td>
				</tr>
				<?php if ( ! $del_dia ) : ?>
					<tr>
						<td colspan="4" class="swpk-gris">
							No cambió nada.
							<?php if ( $dia === $hoy && ! SWPK_Frescura::corrida_al_dia() ) : ?>
								<strong style="color:#b32d2e">Ojo: la corrida de hoy tampoco pasó, así que esto no quiere decir que no haya pasado nada.</strong>
							<?php endif; ?>
						</td>
					</tr>
				<?php else : ?>
					<?php foreach ( $del_dia as $e ) : ?>
						<?php $s = $sitios[ (string) $e->sitio_id ] ?? null; ?>
						<tr>
							<td class="swpk-gris"><?php echo esc_html( SWPK_Frescura::fmt( 'H:i', $e->ocurrio_en ) ); ?></td>
							<td>
								<?php if ( $s ) : ?>
									<a href="<?php echo esc_url( SWPK_Vista::url_del_sitio( (string) $e->sitio_id, 'resumen' ) ); ?>">
										<?php echo esc_html( $s->nombre_visible ); ?>
									</a>
								<?php else : ?>
									<span class="swpk-gris">—</span>
								<?php endif; ?>
							</td>
							<td class="swpk-gris"><?php echo esc_html( $s ? ( $grupos[ $s->tag ] ?? $s->tag ) : '' ); ?></td>
							<td<?php echo 'critico' === $e->severidad ? ' class="swpk-rojo"' : ''; ?>>
								<?php echo esc_html( $etq_evt[ $e->tipo ] ?? $e->tipo ); ?>:
								<?php echo esc_html( $e->titulo ); ?>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			<?php endforeach; ?>
			</tbody>
		</table>

		<p class="swpk-nota">
			Esto es lo que cambió, no el estado de la cartera. Los 107 sitios, agrupados por
			etiqueta y con los botones para revisarlos uno por uno, están en
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=panel-kinsta-sitios' ) ); ?>">Sitios</a>.
		</p>

		<?php SWPK_Frescura::sello(); ?>

	<?php endif; ?>

	<p class="swpk-atribucion"><?php echo wp_kses_post( SWPK_Wordfence::atribucion() ); ?></p>
</div>
