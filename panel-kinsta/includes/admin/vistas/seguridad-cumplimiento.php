<?php
/**
 * Qué le falta a cada sitio.
 *
 * ─── Lo que se rehízo, y por qué ────────────────────────────────────────────
 *
 * La primera versión de esta pantalla no se entendía. Fer la leyó entera y
 * salió con una lista de preguntas que son todas justas:
 *
 *  1. El bloque se titulaba con la REGLA en afirmativo —«No existe un usuario
 *     llamado admin»— y justo debajo la fila decía que sí existe. Leído de
 *     corrido, el panel se contradecía solo. Ahora el título de un bloque de
 *     fallos dice el PROBLEMA, no la regla. Ver SWPK_Normas::problema().
 *
 *  2. No se entendía por qué un solo sitio tenía datos. El veredicto de arriba
 *     ahora lo dice con todas sus letras: de cuántos sitios se puede saber
 *     algo, por qué, y cuáles son.
 *
 *  3. La cuadrícula de 108 filas por 10 columnas era, en sus palabras,
 *     «demasiado ruido visual, todo aparece como incógnito». Tenía razón: con
 *     un solo sitio medido eran 1.070 celdas con un signo de interrogación.
 *     Se quitó. En su lugar va la lista de los sitios que SÍ se pueden medir,
 *     que hoy es corta y mañana será la útil.
 *
 * ─── Por qué abre por norma y no por sitio ──────────────────────────────────
 *
 * Porque el trabajo se hace por norma: «hay que ponerle doble autenticación a
 * estos cuatro» es una tarea; «el sitio tal tiene tres cosas» son tres tareas
 * de tres tipos distintos. Y porque el jefe ya pidió dos veces lo mismo: la
 * información con el nombre del sitio puesto, no contadores que hay que
 * interpretar y después ir a buscar.
 */

defined( 'ABSPATH' ) || exit;

$sitios  = SWPK_Perfiles::sitios_visibles();
$tablero = SWPK_Normas::tablero( $sitios );
$normas  = $tablero['normas'];

$url_sitio = static fn( string $id ) => add_query_arg(
	[ 'page' => 'panel-kinsta-sitio', 'sitio' => $id, 'de' => 'panel-kinsta-seguridad' ],
	admin_url( 'admin.php' )
);

$nombre_de = static fn( object $s ) => (string) ( $s->nombre_visible ?: $s->nombre ?: $s->sitio_id );

// Qué sitios tienen el plugin. Una consulta y no 108: la de antes preguntaba
// agente por agente dentro del bucle.
global $wpdb;
$con_plugin = array_flip(
	$wpdb->get_col( 'SELECT sitio_id FROM ' . SWPK_Tablas::tabla( 'agentes' ) . " WHERE sitio_id <> ''" ) ?: []
);

$medibles = [];
$ciegos   = [];

foreach ( $tablero['filas'] as $f ) {
	if ( isset( $con_plugin[ (string) $f['sitio']->sitio_id ] ) ) {
		$medibles[] = $f;
	} else {
		$ciegos[] = $f;
	}
}

$fallando_medibles = count( array_filter( $medibles, static fn( $f ) => $f['fallas'] > 0 ) );
$al_dia_medibles   = count( $medibles ) - $fallando_medibles;
?>

<?php if ( ! $normas ) : ?>
	<div class="notice notice-warning inline" style="margin-top:16px">
		<p>
			No hay ninguna norma encendida, así que no hay nada que revisar.
			<a href="<?php echo esc_url( add_query_arg( [ 'page' => 'panel-kinsta-seguridad', 'seccion' => 'normas' ], admin_url( 'admin.php' ) ) ); ?>">Ver la lista de normas</a>.
		</p>
	</div>
	<?php return; ?>
<?php endif; ?>

<p class="swpk-intro">
	Esta pantalla contesta una sola pregunta: <strong>¿qué le falta a cada sitio para cumplir nuestras
	normas de seguridad?</strong> Abajo están los problemas encontrados, agrupados por norma y con el
	nombre del sitio puesto.
</p>

<?php
// ── El veredicto ─────────────────────────────────────────────────────────────
//
// Antes decía «1 sitio incumple… y de 107 no se sabe», que deja la pregunta
// obvia sin contestar: ¿y por qué de 107 no se sabe? Ahora lo primero que se
// explica es de cuántos sitios se puede saber algo y por qué.
?>
<?php if ( ! $medibles ) : ?>
	<div class="swpk-veredicto swpk-veredicto-ciego">
		<strong>Todavía no se puede revisar ningún sitio.</strong>
		Estas normas no se ven desde fuera —si el editor de código está abierto, si alguien puede
		registrarse, si el servidor ejecuta archivos subidos— solo se ven desde dentro del sitio, y para eso
		hace falta el plugin instalado. No lo tiene ninguno de los <?php echo (int) $tablero['total']; ?> todavía.
	</div>
<?php else : ?>
	<div class="swpk-veredicto <?php echo $fallando_medibles > 0 ? 'swpk-veredicto-mal' : 'swpk-veredicto-bien'; ?>">
		<?php if ( $fallando_medibles > 0 ) : ?>
			<strong>
				<?php echo esc_html( SWPK_Vista::plural( $fallando_medibles, 'sitio tiene algo', 'sitios tienen algo' ) ); ?>
				que arreglar.
			</strong>
		<?php else : ?>
			<strong>Los sitios que se pueden revisar están en orden.</strong>
		<?php endif; ?>

		<span class="swpk-veredicto-mas">
			El panel puede revisar <?php echo (int) count( $medibles ); ?> de los
			<?php echo (int) $tablero['total']; ?> sitios: son los que tienen el plugin instalado.
			<?php if ( $al_dia_medibles > 0 && $fallando_medibles > 0 ) : ?>
				De esos, <?php echo (int) $al_dia_medibles; ?> cumplen todo.
			<?php endif; ?>
			<?php if ( $ciegos ) : ?>
				De los otros <?php echo (int) count( $ciegos ); ?> no se sabe nada, ni bueno ni malo.
			<?php endif; ?>
		</span>
	</div>
<?php endif; ?>

<?php if ( $ciegos ) : ?>
	<div class="notice notice-warning inline">
		<p>
			<strong>Por qué de <?php echo (int) count( $ciegos ); ?> sitios no se sabe nada.</strong>
			Casi ninguna de estas normas se puede comprobar desde fuera: hay que estar dentro del sitio para
			saber si el editor de código está abierto, si cualquiera puede registrarse o si el servidor
			ejecuta un archivo PHP subido por un formulario. Eso lo mira el plugin que va instalado en cada
			sitio, y <strong>falta instalarlo en <?php echo (int) count( $ciegos ); ?></strong>.
			Mientras tanto esos sitios no aparecen aquí: ni como buenos ni como malos, porque no lo sabemos.
		</p>
	</div>
<?php endif; ?>

<?php
// ── Lo que hay que atender ───────────────────────────────────────────────────
$con_fallas = [];
$cumplidas  = [];

foreach ( $normas as $n ) {
	$r = $tablero['por_norma'][ (string) $n->clave ];
	if ( $r['falla'] > 0 ) { $con_fallas[] = $r; } else { $cumplidas[] = $r; }
}

usort(
	$con_fallas,
	static function ( $a, $b ) {
		$peso = [ 'alta' => 0, 'media' => 1, 'baja' => 2 ];
		$pa = $peso[ (string) $a['norma']->severidad ] ?? 1;
		$pb = $peso[ (string) $b['norma']->severidad ] ?? 1;

		return $pa !== $pb ? $pa <=> $pb : $b['falla'] <=> $a['falla'];
	}
);

$quienes_fallan = [];

foreach ( $tablero['filas'] as $f ) {
	foreach ( $f['celdas'] as $clave => $celda ) {
		if ( SWPK_Normas::FALLA === $celda['estado'] ) {
			$quienes_fallan[ $clave ][] = [ 'sitio' => $f['sitio'], 'detalle' => $celda['detalle'] ];
		}
	}
}
?>

<?php if ( $con_fallas ) : ?>
	<h2 style="margin-top:28px">Lo que hay que arreglar</h2>
	<p class="swpk-mudo" style="margin:-4px 0 14px">
		Cada bloque es un problema encontrado. Debajo, los sitios que lo tienen.
	</p>

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
				<?php // El PROBLEMA, no la regla: aquí abajo van los sitios que lo tienen. ?>
				<h3><?php echo esc_html( SWPK_Normas::problema( $n ) ); ?></h3>
				<span class="swpk-norma-cuenta">
					en <?php echo esc_html( SWPK_Vista::plural( (int) $r['falla'], 'sitio', 'sitios' ) ); ?>
				</span>
			</div>

			<p class="swpk-norma-porque"><?php echo esc_html( (string) $n->porque ); ?></p>

			<p class="swpk-norma-regla">
				La norma dice: <em><?php echo esc_html( (string) $n->nombre ); ?></em>
			</p>

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
						<td style="width:160px;text-align:right">
							<?php if ( '' !== $orden && (int) $n->arreglable === 1 && SWPK_Etiquetas::NO_UPDATE !== (string) $item['sitio']->tag ) : ?>
								<a class="button button-small"
									href="<?php echo esc_url( SWPK_Admin::url_arreglar( $clave, (string) $item['sitio']->sitio_id ) ); ?>">Arreglarlo</a>
							<?php elseif ( SWPK_Etiquetas::NO_UPDATE === (string) $item['sitio']->tag ) : ?>
								<span class="swpk-mudo" title="Es un sitio que administra el cliente: lo revisamos, pero no le cambiamos nada sin que lo pida.">Lo lleva el cliente</span>
							<?php else : ?>
								<span class="swpk-mudo" title="Esto no lo puede cambiar el panel: hay que entrar a hacerlo.">Hay que hacerlo a mano</span>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<?php
			$nuestros = array_values(
				array_filter(
					$lista,
					static fn( $i ) => SWPK_Etiquetas::NO_UPDATE !== (string) $i['sitio']->tag
						&& isset( $con_plugin[ (string) $i['sitio']->sitio_id ] )
				)
			);
			?>
			<?php if ( '' !== $orden && (int) $n->arreglable === 1 && count( $nuestros ) > 1 ) : ?>
				<p class="swpk-norma-pie">
					<a class="button"
						href="<?php echo esc_url( SWPK_Admin::url_arreglar( $clave, '' ) ); ?>"
						onclick="return confirm('Se va a pedir el arreglo en <?php echo (int) count( $nuestros ); ?> sitios. No pasa al instante: cada sitio lo aplica la próxima vez que se comunique con el panel. ¿Seguimos?')">
						Arreglarlo en los <?php echo (int) count( $nuestros ); ?> de golpe
					</a>
					<span class="swpk-mudo">Los sitios que lleva el cliente quedan fuera.</span>
				</p>
			<?php endif; ?>
		</div>
	<?php endforeach; ?>
<?php endif; ?>

<?php
// ── Lo que falta por revisar, de los sitios que SÍ se pueden revisar ─────────
//
// Esto antes listaba las diez normas con «107 sitios» al lado, que era repetir
// diez veces la misma noticia: que falta instalar el plugin. Ahora solo sale
// lo que está sin revisar en un sitio que sí tiene plugin, que es un hueco de
// verdad y no el mismo aviso otra vez.
$huecos_reales = [];

foreach ( $medibles as $f ) {
	foreach ( $f['celdas'] as $clave => $celda ) {
		if ( SWPK_Normas::SIN_DATO === $celda['estado'] ) {
			$huecos_reales[ $clave ][] = $f['sitio'];
		}
	}
}
?>

<?php if ( $huecos_reales ) : ?>
	<h2 style="margin-top:28px">Lo que falta revisar</h2>
	<p class="swpk-mudo" style="margin:-4px 0 12px;max-width:860px">
		Esto no son problemas: es lo que el panel todavía no sabe de sitios que sí puede revisar. Un sitio
		sin revisar puede estar perfecto o puede estar mal, y decir cualquiera de las dos cosas sería
		inventar.
	</p>

	<table class="widefat striped" style="max-width:980px">
		<thead><tr><th style="width:42%">La norma</th><th>Dónde falta, y por qué</th></tr></thead>
		<tbody>
		<?php foreach ( $normas as $n ) : ?>
			<?php
			$clave = (string) $n->clave;
			if ( empty( $huecos_reales[ $clave ] ) ) { continue; }
			$quienes = $huecos_reales[ $clave ];
			?>
			<tr>
				<td><strong><?php echo esc_html( (string) $n->nombre ); ?></strong></td>
				<td>
					<?php
					echo esc_html(
						implode( ', ', array_map( $nombre_de, array_slice( $quienes, 0, 6 ) ) )
						. ( count( $quienes ) > 6 ? ' y ' . ( count( $quienes ) - 6 ) . ' más' : '' )
					);
					?>
					<br>
					<span class="swpk-mudo">
						<?php
						echo 'declarativa' === (string) $n->comprobador
							? 'Esta no se puede comprobar sola: alguien tiene que entrar a la ficha del sitio y marcarla.'
							: 'El sitio todavía no ha mandado este dato. Llega con su próximo reporte.';
						?>
					</span>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
<?php endif; ?>

<?php if ( $medibles ) : ?>
	<h2 style="margin-top:28px">Sitio por sitio</h2>
	<p class="swpk-mudo" style="margin:-4px 0 12px">
		Los <?php echo (int) count( $medibles ); ?> que el panel puede revisar, con su cuenta.
		<?php if ( $ciegos ) : ?>
			Los otros <?php echo (int) count( $ciegos ); ?> aparecerán aquí según se les vaya instalando el plugin.
		<?php endif; ?>
	</p>

	<table class="widefat striped" style="max-width:980px">
		<thead>
			<tr>
				<th style="width:34%">Sitio</th>
				<th style="width:16%">Cumple</th>
				<th style="width:16%">Le falta</th>
				<th>Sin revisar</th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ( $medibles as $f ) : ?>
			<tr>
				<td>
					<a href="<?php echo esc_url( $url_sitio( (string) $f['sitio']->sitio_id ) ); ?>"><strong><?php
						echo esc_html( $nombre_de( $f['sitio'] ) );
					?></strong></a>
					<?php echo SWPK_Admin::grupo_badge( (string) $f['sitio']->tag ); ?>
				</td>
				<td><?php echo (int) $f['cumples']; ?> normas</td>
				<td>
					<?php if ( $f['fallas'] > 0 ) : ?>
						<span class="swpk-etq swpk-sev-alta"><?php echo (int) $f['fallas']; ?> cosas</span>
					<?php else : ?>
						<span class="swpk-mudo">nada</span>
					<?php endif; ?>
				</td>
				<td>
					<?php echo $f['huecos'] > 0 ? (int) $f['huecos'] . ' normas' : '<span class="swpk-mudo">nada</span>'; ?>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
<?php endif; ?>

<?php if ( $cumplidas && $medibles ) : ?>
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

<p class="swpk-atribucion" style="margin-top:22px">
	El panel no comprueba estas normas por su cuenta: las mira el plugin instalado en cada sitio y las manda
	en su reporte. Así que lo que se ve es siempre cómo estaba ese sitio la última vez que reportó, y por eso
	cada dato lleva su fecha en la ficha del sitio.
</p>
