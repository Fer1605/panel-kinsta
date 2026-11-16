<?php
/**
 * El tablero: cómo está cada sitio contra cada norma.
 *
 * ─── Por qué NO abre con una cuadrícula ─────────────────────────────────────
 *
 * Lo primero que sale de la mano es una matriz: 107 filas de sitios por 10
 * columnas de normas, con palomitas y tachitas. Se ve muy bien en una captura
 * y es inútil para trabajar: para saber qué hacer hay que recorrer mil celdas
 * con el dedo y después ir a buscar cada sitio.
 *
 * El jefe ya rechazó esa forma de presentar las cosas, dos veces:
 *
 *   «el panel debe ser a nivel sistema darme la info rapidamente y no pequeños
 *    resumenes de numeros, (...) rapido tendria que captar que esta bien y que
 *    esta mal»                                      (WhatsApp, 11/11/2026)
 *   «Individual, mucha información, pero no en paquete.»   (audio corto)
 *
 * Así que esto abre por norma, con los sitios nombrados debajo de cada una. Se
 * lee como una lista de pendientes, que es lo que es. La cuadrícula completa
 * sigue estando, al final, plegada: sirve para una auditoría o para una
 * captura, no para el día a día.
 */

defined( 'ABSPATH' ) || exit;

$sitios  = SWPK_Perfiles::sitios_visibles();
$tablero = SWPK_Normas::tablero( $sitios );
$normas  = $tablero['normas'];

$url_sitio = static fn( string $id ) => add_query_arg(
	[ 'page' => 'panel-kinsta-sitio', 'sitio' => $id, 'de' => 'panel-kinsta-seguridad' ],
	admin_url( 'admin.php' )
);

/** El nombre con el que se le dice a una persona qué sitio es. */
$nombre_de = static fn( object $s ) => (string) ( $s->nombre_visible ?: $s->nombre ?: $s->sitio_id );

// Las normas que hoy tienen algo que atender, lo peor primero. Una norma que
// nadie incumple no abre la pantalla: baja al final, dicha en una línea.
$con_fallas = [];
$cumplidas  = [];

foreach ( $normas as $n ) {
	$clave = (string) $n->clave;
	$r     = $tablero['por_norma'][ $clave ];

	if ( $r['falla'] > 0 ) {
		$con_fallas[] = $r;
	} else {
		$cumplidas[] = $r;
	}
}

usort(
	$con_fallas,
	static function ( $a, $b ) {
		$peso = [ 'alta' => 0, 'media' => 1, 'baja' => 2 ];
		$pa   = $peso[ (string) $a['norma']->severidad ] ?? 1;
		$pb   = $peso[ (string) $b['norma']->severidad ] ?? 1;

		return $pa !== $pb ? $pa <=> $pb : $b['falla'] <=> $a['falla'];
	}
);

// Qué sitios incumplen cada norma, con nombre. Es el dato que la pantalla
// existe para dar.
$quienes_fallan = [];
$quienes_huecos = [];

foreach ( $tablero['filas'] as $f ) {
	foreach ( $f['celdas'] as $clave => $celda ) {
		if ( SWPK_Normas::FALLA === $celda['estado'] ) {
			$quienes_fallan[ $clave ][] = [ 'sitio' => $f['sitio'], 'detalle' => $celda['detalle'] ];
		} elseif ( SWPK_Normas::SIN_DATO === $celda['estado'] ) {
			$quienes_huecos[ $clave ][] = [ 'sitio' => $f['sitio'], 'detalle' => $celda['detalle'] ];
		}
	}
}

$sin_agente = 0;

foreach ( $sitios as $s ) {
	if ( ! SWPK_Ordenes::agente_de( (string) $s->sitio_id ) ) {
		++$sin_agente;
	}
}
?>

<?php if ( ! $normas ) : ?>
	<div class="notice notice-warning inline" style="margin-top:16px">
		<p>
			No hay ninguna norma encendida, así que no hay nada que medir.
			<a href="<?php echo esc_url( add_query_arg( [ 'page' => 'panel-kinsta-seguridad', 'seccion' => 'normas' ], admin_url( 'admin.php' ) ) ); ?>">Ver las normas</a>.
		</p>
	</div>
	<?php return; ?>
<?php endif; ?>

<?php
// ── El veredicto, en una línea ───────────────────────────────────────────────
//
// Antes de cualquier tabla. Si lo único que alguien lee de esta pantalla es
// esta frase, tiene que haberse llevado lo importante.
$total_fallas = array_sum( array_column( $tablero['por_norma'], 'falla' ) );
?>
<div class="swpk-veredicto <?php echo $total_fallas > 0 ? 'swpk-veredicto-mal' : 'swpk-veredicto-bien'; ?>">
	<?php if ( $total_fallas > 0 ) : ?>
		<strong><?php echo esc_html( SWPK_Vista::plural( (int) $tablero['fallando'], 'sitio incumple', 'sitios incumplen' ) ); ?></strong>
		alguna de las <?php echo (int) count( $normas ); ?> normas,
		<?php echo esc_html( SWPK_Vista::plural( $total_fallas, 'incumplimiento en total', 'incumplimientos en total' ) ); ?>.
	<?php else : ?>
		<strong>Ninguno de los <?php echo (int) $tablero['total']; ?> sitios incumple ninguna norma.</strong>
	<?php endif; ?>

	<?php if ( $tablero['sin_dato'] > 0 ) : ?>
		<span class="swpk-veredicto-mas">
			Y de <?php echo esc_html( SWPK_Vista::plural( (int) $tablero['sin_dato'], 'sitio', 'sitios' ) ); ?>
			no se sabe: nunca han reportado.
		</span>
	<?php endif; ?>
</div>

<?php if ( $sin_agente > 0 ) : ?>
	<div class="notice notice-warning inline">
		<p>
			<strong><?php echo esc_html( SWPK_Vista::plural( $sin_agente, 'sitio no tiene', 'sitios no tienen' ) ); ?> el agente instalado.</strong>
			De esos, el panel no puede comprobar casi ninguna norma: estas cosas —si el editor de código está
			abierto, si alguien puede registrarse, si PHP se ejecuta en las subidas— solo se ven desde dentro
			del sitio. Aparecen como «sin medir», que no es lo mismo que «está bien».
		</p>
	</div>
<?php endif; ?>

<?php if ( $con_fallas ) : ?>
	<h2 style="margin-top:28px">Lo que hay que atender</h2>

	<?php foreach ( $con_fallas as $r ) : ?>
		<?php
		$n     = $r['norma'];
		$clave = (string) $n->clave;
		$lista = $quienes_fallan[ $clave ] ?? [];
		$orden = SWPK_Ordenes::orden_de_la_norma( $clave );
		?>
		<div class="swpk-norma-bloque">
			<div class="swpk-norma-cabeza">
				<span class="swpk-etq swpk-sev-<?php echo esc_attr( (string) $n->severidad ); ?>">
					<?php echo esc_html( ucfirst( (string) $n->severidad ) ); ?>
				</span>
				<h3><?php echo esc_html( (string) $n->nombre ); ?></h3>
				<span class="swpk-norma-cuenta">
					<?php echo esc_html( SWPK_Vista::plural( (int) $r['falla'], 'sitio', 'sitios' ) ); ?>
				</span>
			</div>

			<p class="swpk-norma-porque"><?php echo esc_html( (string) $n->porque ); ?></p>

			<table class="widefat striped swpk-norma-tabla">
				<tbody>
				<?php foreach ( $lista as $item ) : ?>
					<tr>
						<td style="width:30%">
							<a href="<?php echo esc_url( $url_sitio( (string) $item['sitio']->sitio_id ) ); ?>"><strong><?php
								echo esc_html( $nombre_de( $item['sitio'] ) );
							?></strong></a>
							<?php echo SWPK_Admin::grupo_badge( (string) $item['sitio']->tag ); ?>
						</td>
						<td><?php echo esc_html( (string) $item['detalle'] ); ?></td>
						<td style="width:150px;text-align:right">
							<?php if ( '' !== $orden && (int) $n->arreglable === 1 && SWPK_Etiquetas::NO_UPDATE !== (string) $item['sitio']->tag ) : ?>
								<a class="button button-small"
									href="<?php echo esc_url( SWPK_Admin::url_arreglar( $clave, (string) $item['sitio']->sitio_id ) ); ?>">Arreglar</a>
							<?php elseif ( SWPK_Etiquetas::NO_UPDATE === (string) $item['sitio']->tag ) : ?>
								<span class="swpk-mudo" title="Es un sitio que administra el cliente: se mide, no se cambia.">Es del cliente</span>
							<?php else : ?>
								<span class="swpk-mudo">A mano</span>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<?php if ( '' !== $orden && (int) $n->arreglable === 1 ) : ?>
				<?php
				// Cuántos de los que fallan son nuestros. En los del cliente no
				// se actúa, así que ofrecer "arreglar los 14" cuando 3 son del
				// cliente sería prometer algo que el panel va a rechazar.
				$nuestros = array_values(
					array_filter(
						$lista,
						static fn( $i ) => SWPK_Etiquetas::NO_UPDATE !== (string) $i['sitio']->tag
							&& SWPK_Ordenes::agente_de( (string) $i['sitio']->sitio_id )
					)
				);
				?>
				<?php if ( count( $nuestros ) > 1 ) : ?>
					<p class="swpk-norma-pie">
						<a class="button"
							href="<?php echo esc_url( SWPK_Admin::url_arreglar( $clave, '' ) ); ?>"
							onclick="return confirm('Se va a pedir el arreglo en <?php echo (int) count( $nuestros ); ?> sitios nuestros. No es inmediato: cada sitio lo aplica cuando pregunte al panel. ¿Seguimos?')">
							Arreglar en los <?php echo (int) count( $nuestros ); ?> sitios nuestros
						</a>
						<span class="swpk-mudo">Los del cliente quedan fuera, y los que no tienen agente también.</span>
					</p>
				<?php endif; ?>
			<?php endif; ?>
		</div>
	<?php endforeach; ?>
<?php endif; ?>

<?php
// ── Lo que falta medir ───────────────────────────────────────────────────────
$huecos_totales = array_sum( array_column( $tablero['por_norma'], 'sin_dato' ) );
?>
<?php if ( $huecos_totales > 0 ) : ?>
	<h2 style="margin-top:28px">Lo que todavía no se ha medido</h2>
	<p style="max-width:860px;color:#3c434a">
		No es una lista de problemas: es la lista de lo que el panel no sabe. Un sitio sin medir puede estar
		perfecto o puede estar mal, y decir cualquiera de las dos cosas sería inventar.
	</p>

	<table class="widefat striped" style="max-width:980px">
		<thead>
			<tr>
				<th>Norma</th>
				<th style="width:120px">Sin medir</th>
				<th>Por qué</th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ( $normas as $n ) : ?>
			<?php
			$clave = (string) $n->clave;
			$r     = $tablero['por_norma'][ $clave ];

			if ( $r['sin_dato'] < 1 ) {
				continue;
			}

			$ejemplo = $quienes_huecos[ $clave ][0]['detalle'] ?? '';
			?>
			<tr>
				<td><strong><?php echo esc_html( (string) $n->nombre ); ?></strong></td>
				<td><?php echo esc_html( SWPK_Vista::plural( (int) $r['sin_dato'], 'sitio', 'sitios' ) ); ?></td>
				<td>
					<?php echo esc_html( $ejemplo ); ?>
					<?php if ( 'declarativa' === (string) $n->comprobador ) : ?>
						<br><span class="swpk-mudo">Esta se marca a mano, sitio por sitio, desde la ficha del sitio.</span>
					<?php endif; ?>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
<?php endif; ?>

<?php if ( $cumplidas ) : ?>
	<h2 style="margin-top:28px">Lo que está bien</h2>
	<ul class="swpk-bien-lista">
	<?php foreach ( $cumplidas as $r ) : ?>
		<?php if ( $r['cumple'] < 1 ) { continue; } ?>
		<li>
			<strong><?php echo esc_html( (string) $r['norma']->nombre ); ?></strong> —
			<?php echo esc_html( SWPK_Vista::plural( (int) $r['cumple'], 'sitio la cumple', 'sitios la cumplen' ) ); ?>,
			ninguno la incumple.
		</li>
	<?php endforeach; ?>
	</ul>
<?php endif; ?>

<?php
// ── La cuadrícula completa, plegada ──────────────────────────────────────────
//
// Existe porque una auditoría la pide y porque a veces hay que ver un sitio
// concreto en todas sus normas de un golpe. Va plegada para que no sea lo
// primero que se ve.
?>
<details class="swpk-cuadricula">
	<summary>Ver la cuadrícula completa: los <?php echo (int) $tablero['total']; ?> sitios por las <?php echo (int) count( $normas ); ?> normas</summary>

	<p style="color:#50575e;margin:10px 0">
		Cada celda dice cómo estaba ese sitio en esa norma la última vez que reportó. Pasa el cursor por una
		celda para ver el detalle y la fecha.
	</p>

	<div class="swpk-cuadricula-scroll">
		<table class="widefat striped swpk-matriz">
			<thead>
				<tr>
					<th class="swpk-matriz-sitio">Sitio</th>
					<?php foreach ( $normas as $n ) : ?>
						<th class="swpk-matriz-col" title="<?php echo esc_attr( (string) $n->nombre ); ?>">
							<span><?php echo esc_html( (string) $n->nombre ); ?></span>
						</th>
					<?php endforeach; ?>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $tablero['filas'] as $f ) : ?>
				<tr>
					<td class="swpk-matriz-sitio">
						<a href="<?php echo esc_url( $url_sitio( (string) $f['sitio']->sitio_id ) ); ?>"><?php
							echo esc_html( $nombre_de( $f['sitio'] ) );
						?></a>
					</td>
					<?php foreach ( $normas as $n ) : ?>
						<?php
						$celda = $f['celdas'][ (string) $n->clave ] ?? [ 'estado' => SWPK_Normas::SIN_DATO, 'detalle' => '', 'medido_en' => '' ];
						$titulo = SWPK_Normas::etiqueta( (string) $celda['estado'] );

						if ( '' !== (string) $celda['detalle'] ) {
							$titulo .= ' — ' . $celda['detalle'];
						}

						if ( ! empty( $celda['medido_en'] ) ) {
							$titulo .= ' (medido el ' . SWPK_Frescura::fmt( 'j/n/Y H:i', (string) $celda['medido_en'] ) . ')';
						}
						?>
						<td class="swpk-celda swpk-celda-<?php echo esc_attr( (string) $celda['estado'] ); ?>"
							title="<?php echo esc_attr( $titulo ); ?>">
							<?php
							echo esc_html(
								match ( (string) $celda['estado'] ) {
									SWPK_Normas::CUMPLE    => 'Sí',
									SWPK_Normas::FALLA     => 'No',
									SWPK_Normas::NO_APLICA => '—',
									default                => '?',
								}
							);
							?>
						</td>
					<?php endforeach; ?>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</details>

<p class="swpk-atribucion" style="margin-top:20px">
	Estas normas no las comprueba el panel por su cuenta: las trae el agente instalado en cada sitio, en su
	reporte. Lo que se ve es, siempre, cómo estaba el sitio la última vez que reportó — por eso cada celda
	lleva su fecha.
</p>
