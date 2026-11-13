<?php
/**
 * Inicio: el diario de lo que pasó, agrupado por sitio.
 *
 * ─── Por qué agrupado, y no una fila por cambio ─────────────────────────────
 *
 * La primera versión del diario ponía una fila por evento. Con los datos
 * reales eso son 128 filas en un día normal, casi todas del mismo puñado de
 * sitios, y la pantalla se volvía un scroll interminable donde no se encuentra
 * nada:
 *
 *   "tengo como 20.000 cosas de un solo sitio y aparte se vuelve súper largo
 *    el scroll (...) imagínate que se actualicen 80 y tantos sitios con 5
 *    plugins cada uno, a qué hora se encuentra qué está pasando"
 *
 * Tenía razón, y el cálculo también: 80 sitios por 5 módulos son 400 filas.
 * Ahora es una fila por SITIO y por día, con el resumen de lo que le pasó. Los
 * mismos 128 cambios caben en unas 40 filas, y cada una dice de qué sitio es.
 *
 * Y lleva buscador, que es lo otro que faltaba: con 108 sitios, buscar uno a
 * ojo por el scroll no es buscar.
 */
defined( 'ABSPATH' ) || exit;

$estado   = SWPK_Estado::calcular();
$sin_leer = count( $estado['urgentes'] );
$busqueda = SWPK_Vista::busqueda_pedida();

$sitios = [];
foreach ( SWPK_Perfiles::sitios_visibles() as $s ) {
	$sitios[ (string) $s->sitio_id ] = $s;
}

$puede_todo = SWPK_Perfiles::puede( SWPK_Perfiles::VER_TODO );
$eventos    = array_filter(
	SWPK_Eventos::recientes( 600 ),
	static fn( $e ) => $puede_todo || isset( $sitios[ (string) $e->sitio_id ] )
);

// Agrupado por día y, dentro del día, por sitio. El día de hoy aparece
// siempre, tenga algo o no: es el que contesta "¿pasó algo?".
$hoy     = current_time( 'Y-m-d' );
$por_dia = [ $hoy => [] ];

foreach ( $eventos as $e ) {
	$sitio  = $sitios[ (string) $e->sitio_id ] ?? null;
	$nombre = (string) ( $sitio->nombre_visible ?? '' );

	if ( '' !== $busqueda && false === stripos( $nombre, $busqueda ) ) {
		continue;
	}

	$dia   = substr( (string) $e->ocurrio_en, 0, 10 );
	$clave = (string) $e->sitio_id;

	if ( ! isset( $por_dia[ $dia ][ $clave ] ) ) {
		$por_dia[ $dia ][ $clave ] = [
			'sitio'    => $sitio,
			'nombre'   => '' !== $nombre ? $nombre : (string) $e->sitio_id,
			'hora'     => (string) $e->ocurrio_en,
			'grave'    => false,
			'mirar'    => false,
			'por_tipo' => [],
		];
	}

	$fila = &$por_dia[ $dia ][ $clave ];
	$fila['por_tipo'][ $e->tipo ][] = $e;
	if ( 'critico' === $e->severidad ) {
		$fila['grave'] = true;
	}
	if ( ! SWPK_Eventos::es_rutinario( (string) $e->tipo ) ) {
		$fila['mirar'] = true;
	}
	unset( $fila );
}

// Un día se parte en dos montones, pero los dos se ENSEÑAN: arriba lo que
// necesita atención, abajo lo que salió bien solo.
//
// Antes el segundo montón solo se contaba, y eso dejaba días que decían
// "nada que mirar" justo encima de una línea admitiendo que sí hubo
// movimiento:
//
//   "no puede ser que hoy no se haya actualizado ninguno (...) si tiene
//    registro de que un sitio hizo movimientos, aquí debería de aparecer"
//
// Tenía razón: un contador no es el dato, es la promesa de que el dato está
// en otra parte. Siguen separados —mezclarlos entierra lo urgente entre 128
// actualizaciones que salieron bien— pero ya no se esconde nada.
$solos_dia   = [];
$resumen_dia = [];

foreach ( $por_dia as $dia => $del_dia ) {
	$mirar = [];
	$solos = [];
	$bien  = [ 'sitios' => 0, 'modulos' => 0 ];

	foreach ( $del_dia as $clave => $f ) {
		if ( ! empty( $f['mirar'] ) ) {
			$mirar[ $clave ] = $f;
			continue;
		}

		$solos[ $clave ] = $f;
		++$bien['sitios'];

		foreach ( $f['por_tipo'] as $lista ) {
			$bien['modulos'] += count( $lista );
		}
	}

	$por_dia[ $dia ]     = $mirar;
	$solos_dia[ $dia ]   = $solos;
	$resumen_dia[ $dia ] = $bien;
}

// De los que salieron bien solos se enseñan los primeros diez y el resto se
// manda a Movimientos, filtrado por ese día. Enseñarlos todos era lo que
// pedía Fer y era lo correcto —un contador no es el dato— pero un día movido
// son 60 u 80 renglones de buenas noticias, y entonces el Resumen deja de ser
// un resumen. Diez caben en pantalla y bastan para ver de qué va el día; los
// demás están a un clic, en la pantalla que existe justamente para verlos
// todos.
//
// Los que necesitan atención NO se capan: esos son la razón de abrir esto.
$tope_solos = 10;

$por_dia  = array_slice( $por_dia, 0, 8, true );
$etq_evt  = SWPK_Eventos::etiquetas();
$de_hoy   = count( $por_dia[ $hoy ] ?? [] );
$bien_hoy = $resumen_dia[ $hoy ] ?? [ 'sitios' => 0, 'modulos' => 0 ];

/** "3 módulos actualizados: Elementor, WooCommerce y 1 más" */
$resumir = static function ( array $por_tipo ) use ( $etq_evt ): string {
	$trozos = [];

	foreach ( $por_tipo as $tipo => $lista ) {
		$nombres = [];
		foreach ( array_slice( $lista, 0, 3 ) as $e ) {
			$nombres[] = SWPK_Textos::limpiar_titulo( (string) $e->titulo );
		}

		$texto = sprintf( '%s: %s', $etq_evt[ $tipo ] ?? $tipo, implode( ' · ', $nombres ) );
		if ( count( $lista ) > 3 ) {
			$texto .= sprintf( ' y %d más', count( $lista ) - 3 );
		}

		$trozos[] = $texto;
	}

	return implode( ' — ', $trozos );
};

/**
 * Un renglón de sitio. Lo usan los dos montones a propósito: lo que necesita
 * atención y lo que salió bien solo se leen igual —misma hora, mismo nombre,
 * misma etiqueta, mismo detalle— y lo único que cambia es el tono. Así nadie
 * tiene que aprender dos formatos para leer un día.
 */
$fila = static function ( array $f, bool $solo ) use ( $resumir ): void {
	?>
	<tr<?php echo $solo ? ' class="swpk-solo"' : ''; ?>>
		<td class="swpk-gris"><?php echo esc_html( SWPK_Frescura::fmt( 'H:i', $f['hora'] ) ); ?></td>
		<td>
			<?php if ( $f['sitio'] ) : ?>
				<a href="<?php echo esc_url( SWPK_Vista::url_del_sitio( (string) $f['sitio']->sitio_id, 'panel-kinsta' ) ); ?>">
					<?php echo esc_html( $f['nombre'] ); ?>
				</a>
			<?php else : ?>
				<?php echo esc_html( $f['nombre'] ); ?>
			<?php endif; ?>
		</td>
		<td><?php echo $f['sitio'] ? SWPK_Admin::grupo_badge( (string) $f['sitio']->tag ) : ''; ?></td>
		<td<?php echo $f['grave'] ? ' class="swpk-rojo"' : ''; ?>>
			<?php echo esc_html( $resumir( $f['por_tipo'] ) ); ?>
		</td>
	</tr>
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

		<p class="swpk-linea">
			<strong>
				<?php
				if ( 0 === $de_hoy && 0 === $bien_hoy['sitios'] ) {
					echo esc_html( sprintf( 'Hoy no ha cambiado nada en los %d sitios.', (int) $estado['total'] ) );
				} elseif ( 0 === $de_hoy ) {
					echo esc_html( sprintf(
						'Hoy ningún sitio necesita atención. Hubo movimiento en %s, y %s.',
						SWPK_Vista::plural( (int) $bien_hoy['sitios'], 'sitio', 'sitios' ),
						1 === (int) $bien_hoy['sitios'] ? 'salió bien solo' : 'salieron bien solos'
					) );
				} else {
					echo esc_html( sprintf( 'Hoy %s atención.', SWPK_Vista::plural( $de_hoy, 'sitio necesita', 'sitios necesitan' ) ) );
				}
				?>
			</strong>
			<?php if ( $sin_leer ) : ?>
				Y <?php echo esc_html( SWPK_Vista::plural( $sin_leer, 'sitio sigue', 'sitios siguen' ) ); ?> con algo sin atender desde antes:
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=panel-kinsta-vulnerabilidades' ) ); ?>">verlos</a>.
			<?php else : ?>
				Y no hay nada sin atender de antes.
			<?php endif; ?>
		</p>

		<?php SWPK_Vista::abrir_barra(); ?>
			<span class="swpk-gris">Los últimos días, un renglón por sitio: lo que necesita atención y lo que salió bien solo.</span>
			<span>
				<?php SWPK_Vista::buscador( 'panel-kinsta', $busqueda, [] ); ?>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=panel-kinsta-bitacora&seccion=movimientos' ) ); ?>">
					Ver todo el historial
				</a>
			</span>
		<?php SWPK_Vista::cerrar_barra(); ?>

		<p class="swpk-leyenda">
			<?php echo SWPK_Admin::grupo_badge( SWPK_Etiquetas::DESARROLLO ); ?>
			<?php echo SWPK_Admin::grupo_badge( SWPK_Etiquetas::AUTO ); ?>
			<span>los administramos nosotros</span>
			<span class="swpk-gris">·</span>
			<?php echo SWPK_Admin::grupo_badge( SWPK_Etiquetas::NO_UPDATE ); ?>
			<span>es del cliente: no se le toca nada sin pedirlo</span>
		</p>

		<table class="widefat striped swpk-tabla">
			<thead>
				<tr>
					<th style="width:70px">Hora</th>
					<th style="width:210px">Sitio</th>
					<th style="width:130px">Grupo</th>
					<th>Qué le pasó</th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $por_dia as $dia => $del_dia ) : ?>
				<?php
				$bien  = $resumen_dia[ $dia ] ?? [ 'sitios' => 0, 'modulos' => 0 ];
				$solos = $solos_dia[ $dia ] ?? [];
				$resto = [];

				if ( $del_dia ) {
					$resto[] = SWPK_Vista::plural( count( $del_dia ), 'sitio necesita atención', 'sitios necesitan atención' );
				}
				if ( $solos ) {
					$resto[] = SWPK_Vista::plural( count( $solos ), 'salió bien solo', 'salieron bien solos' );
				}
				?>
				<tr class="swpk-dia<?php echo $dia === $hoy ? ' swpk-dia-hoy' : ''; ?>">
					<td colspan="4">
						<span class="swpk-dia-fila">
							<span class="swpk-dia-f">
								<?php echo esc_html( ucfirst( SWPK_Frescura::fmt( 'l j \d\e F', $dia . ' 12:00:00' ) ) ); ?><?php if ( $dia === $hoy ) : ?> &mdash; hoy<?php endif; ?>
							</span>
							<span class="swpk-dia-r">
								<?php echo esc_html( $resto ? implode( ' · ', $resto ) : 'sin cambios' ); ?>
							</span>
						</span>
					</td>
				</tr>

				<?php if ( ! $del_dia && ! $solos ) : ?>
					<tr>
						<td colspan="4" class="swpk-gris">
							<?php if ( '' !== $busqueda ) : ?>
								Ese sitio no cambió nada este día.
							<?php else : ?>
								No hubo ningún cambio: todo quedó igual que el día anterior.
								<?php if ( $dia === $hoy && ! SWPK_Frescura::corrida_al_dia() ) : ?>
									<strong class="swpk-rojo">Ojo: la corrida de hoy tampoco pasó, así que esto no quiere decir que no haya pasado nada.</strong>
								<?php endif; ?>
							<?php endif; ?>
						</td>
					</tr>
				<?php else : ?>
					<?php foreach ( $del_dia as $f ) { $fila( $f, false ); } ?>

					<?php if ( $solos ) : ?>
						<tr class="swpk-sub">
							<td colspan="4">
								<?php
								printf(
									'Salieron bien solos — nadie tuvo que entrar a ninguno (%s)',
									esc_html( SWPK_Vista::plural( (int) $bien['modulos'], 'movimiento', 'movimientos' ) )
								);
								?>
							</td>
						</tr>
						<?php foreach ( array_slice( $solos, 0, $tope_solos, true ) as $f ) { $fila( $f, true ); } ?>

						<?php if ( count( $solos ) > $tope_solos ) : ?>
							<tr class="swpk-mas">
								<td colspan="4">
									<a href="<?php echo esc_url(
										add_query_arg(
											[
												'page'    => 'panel-kinsta-bitacora',
												'seccion' => 'movimientos',
												'desde'   => $dia,
												'hasta'   => $dia,
											],
											admin_url( 'admin.php' )
										)
									); ?>">
										<?php
										printf(
											'Ver los %s de este día &rarr;',
											esc_html( SWPK_Vista::plural( count( $solos ) - $tope_solos, 'sitio restante', 'sitios restantes' ) )
										);
										?>
									</a>
									<span class="swpk-gris">todos los movimientos del día, en Bitácora</span>
								</td>
							</tr>
						<?php endif; ?>
					<?php endif; ?>
				<?php endif; ?>
			<?php endforeach; ?>
			</tbody>
		</table>

		<p class="swpk-nota">
			Esto es lo que cambió estos días, no el estado de la cartera. Los <?php echo (int) $estado['total']; ?>
			sitios agrupados por etiqueta, con los botones para revisarlos uno por uno, están en
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=panel-kinsta-sitios' ) ); ?>">Sitios</a>.
		</p>

		<?php SWPK_Frescura::sello(); ?>

	<?php endif; ?>

	<p class="swpk-atribucion"><?php echo wp_kses_post( SWPK_Wordfence::atribucion() ); ?></p>
</div>
