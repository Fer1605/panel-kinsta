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

/**
 * A dónde lleva el nombre de un sitio desde esta pantalla.
 *
 * A su pestaña de NORMAS, no a la de módulos pendientes. Se entra aquí
 * preguntando «¿qué le falta a este sitio en seguridad?» y se aterrizaba en la
 * lista de plugins por actualizar, que es otra cosa: «¿qué pasa si yo quiero
 * saber cuáles son todas las normas de seguridad de un sitio que le debo de
 * mejorar? Yo aquí no las veo».
 */
$url_sitio = static fn( string $id ) => add_query_arg(
	[
		'page'    => 'panel-kinsta-sitio',
		'sitio'   => $id,
		'seccion' => 'normas',
		'de'      => 'panel-kinsta-seguridad',
	],
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

<?php
// ── Lo que hay que atender ───────────────────────────────────────────────────
$con_fallas = [];
$cumplidas  = [];

foreach ( $normas as $n ) {
	$r = $tablero['por_norma'][ (string) $n->clave ];
	if ( $r['falla'] > 0 ) { $con_fallas[] = $r; } else { $cumplidas[] = $r; }
}

// ── Cómo vamos ───────────────────────────────────────────────────────────────
//
// La tarjeta que la maqueta aprobada pone arriba de esta pantalla. Es lo
// mismo que dice el veredicto de abajo, pero en números y de un vistazo: el
// jefe pidió "rapido tendria que captar que esta bien y que esta mal", y una
// frase de tres renglones no es un vistazo.
//
// Los tres trozos suman el total a propósito. «Sin revisar» no es un relleno:
// es la parte de la cartera de la que este panel no sabe nada, y esconderla
// dejaría una dona que promete saber más de lo que sabe.
$trozos_cumpl = [
	[
		'n'     => (int) $al_dia_medibles,
		'color' => SWPK_Riesgo::colores()[ SWPK_Riesgo::AL_DIA ],
		'texto' => 'Cumplen todo',
	],
	[
		'n'     => (int) $fallando_medibles,
		'color' => SWPK_Riesgo::colores()[ SWPK_Riesgo::CRITICO ],
		'texto' => 'Les falta algo',
	],
	[
		'n'     => (int) count( $ciegos ),
		'color' => SWPK_Riesgo::colores()[ SWPK_Riesgo::SIN_DATOS ],
		'texto' => 'Sin revisar',
	],
];

SWPK_Vista::rejilla_abrir();

// La dona iba a cinco columnas y al lado, a siete, la lista de problemas. La
// lista es larga y la dona mide lo que mide un círculo, así que debajo de ella
// quedaba media pantalla en blanco: «ese espacio en blanco ahí no le gusta».
// Ahora la dona es una tira baja a todo lo ancho y la lista va entera debajo.
SWPK_Vista::caja_abrir(
	'Estado de cumplimiento',
	esc_html( SWPK_Vista::plural( count( $normas ), 'norma activa', 'normas activas' ) ),
	'swpk-c12'
);

SWPK_Vista::dona( $trozos_cumpl, (int) $tablero['total'], 'sitios' );

// Las normas que no incumple nadie. Van aquí dentro y no en una tarjeta
// propia: decir lo que está bien es parte de decir cómo vamos, y una
// pantalla que solo enseña problemas se lee como si todo estuviera mal.
if ( $cumplidas && $medibles ) {
	$limpias = array_filter( $cumplidas, static fn( $r ) => $r['cumple'] >= 1 );

	if ( $limpias ) {
		// Plegado a propósito: que algo esté bien hay que afirmarlo —si no, un
		// panel solo enseña desgracias— pero no puede ocupar media pantalla por
		// delante de lo que sí hay que atender. El número se ve sin abrir.
		printf(
			'<details style="margin:18px 0 6px"><summary class="swpk-titulin" style="cursor:pointer">Lo que está bien <span class="swpk-mudo">(%s)</span></summary>',
			esc_html( SWPK_Vista::plural( count( $limpias ), 'norma que nadie incumple', 'normas que nadie incumple' ) )
		);
		echo '<ul class="swpk-bien-lista">';

		foreach ( $limpias as $r ) {
			printf(
				'<li><strong>%s</strong> — %s, ninguno la incumple.</li>',
				esc_html( (string) $r['norma']->nombre ),
				esc_html( SWPK_Vista::plural( (int) $r['cumple'], 'sitio la cumple', 'sitios la cumplen' ) )
			);
		}

		echo '</ul></details>';
	}
}

SWPK_Vista::caja_cerrar();

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

// ── Filtrar los problemas por gravedad y por norma ───────────────────────────
//
// Con diez normas encendidas esta parte son diez bloques largos, y lo que uno
// viene a hacer casi siempre es «enséñame solo lo grave» o «enséñame solo lo
// de la verificación en dos pasos».
//
// Las opciones salen de las normas que DE VERDAD están fallando, no del
// catálogo entero: ofrecer una norma que cumple todo el mundo es ofrecer un
// filtro que solo sabe dejar la pantalla vacía.
$sev_posibles = [];
$normas_malas = [];

foreach ( $con_fallas as $r ) {
	$sev = (string) $r['norma']->severidad;
	$sev_posibles[ $sev ] = ucfirst( $sev );
	$normas_malas[ (string) $r['norma']->clave ] = (string) $r['norma']->nombre;
}

$orden_sev = [ 'alta' => 0, 'media' => 1, 'baja' => 2 ];
uksort( $sev_posibles, static fn( $a, $b ) => ( $orden_sev[ $a ] ?? 9 ) <=> ( $orden_sev[ $b ] ?? 9 ) );

$sev_pedida   = SWPK_Vista::opcion_pedida( 'sev', $sev_posibles );
$norma_pedida = SWPK_Vista::opcion_pedida( 'norma', $normas_malas );

// ── «Ya ni sé dónde arreglo yo las cosas» ───────────────────────────────────
//
// De las 73 normas, el panel solo puede arreglar cinco: son las que el agente
// sabe cambiar desde dentro del sitio. Las demás salen con «Hay que hacerlo a
// mano», y como las que fallan en más sitios son justo de esas, se puede
// recorrer la pantalla entera sin tropezarse con un botón de «Arreglarlo».
// Este filtro deja a la vista solo lo que sí se puede pedir desde aquí.
$arreglables = [ 'si' => 'Solo lo que podemos arreglar', 'no' => 'Solo lo que va a mano' ];
$puede_arreglar = static fn( $norma ): bool => '' !== SWPK_Ordenes::orden_de_la_norma( (string) $norma->clave )
	&& 1 === (int) $norma->arreglable;

$cuantas_arreglables = count( array_filter(
	$con_fallas,
	static fn( $r ) => $puede_arreglar( $r['norma'] )
) );

$arreglo_pedido = SWPK_Vista::opcion_pedida( 'arreglo', $arreglables );

$con_fallas_todas = $con_fallas;

if ( '' !== $sev_pedida || '' !== $norma_pedida || '' !== $arreglo_pedido ) {
	$con_fallas = array_values(
		array_filter(
			$con_fallas,
			static function ( $r ) use ( $sev_pedida, $norma_pedida, $arreglo_pedido, $puede_arreglar ) {
				if ( '' !== $sev_pedida && (string) $r['norma']->severidad !== $sev_pedida ) {
					return false;
				}
				if ( '' !== $norma_pedida && (string) $r['norma']->clave !== $norma_pedida ) {
					return false;
				}
				if ( '' !== $arreglo_pedido && $puede_arreglar( $r['norma'] ) !== ( 'si' === $arreglo_pedido ) ) {
					return false;
				}
				return true;
			}
		)
	);
}

// ── Que esto no sea una página sin fondo ────────────────────────────────────
//
// Cada norma que falla saca un bloque, y dentro el bloque sacaba la lista
// COMPLETA de sitios que la incumplen. Con 95 sitios reportando, una sola
// norma puede traer ochenta renglones, y hay decenas de normas: la pantalla
// se volvía un pozo por el que nunca se termina de bajar.
//
// Ahora: seis bloques por página, y dentro de cada bloque los primeros cinco
// sitios con un enlace para ver el resto. Al pedir una norma concreta sí se
// abre entera, porque entonces es justo lo que se vino a ver.
$POR_BLOQUE = 5;

[ $con_fallas_pagina, $pag_fallas, $pags_fallas ] = SWPK_Vista::paginar(
	$con_fallas,
	SWPK_Vista::pagina_pedida( 'pf' ),
	4
);
?>

<?php
// ── Tres vistas, no tres tablones uno debajo de otro ────────────────────────
//
// «Tengo que hacer demasiado scroll, hay demasiadas cosas aquí puestas,
// entonces ponle pestañas.» Eran tres tablas largas seguidas: los problemas
// agrupados por norma, los sitios uno por uno, y los huecos sin medir. Ahora
// se elige cuál se mira. La dona de arriba se queda siempre, porque es el
// resumen de las tres.
// ── Lo que falta por revisar, de los sitios que SÍ se pueden revisar ────────
//
// Esto antes listaba las diez normas con «107 sitios» al lado, que era repetir
// diez veces la misma noticia: que falta instalar el plugin. Ahora solo sale
// lo que está sin revisar en un sitio que sí tiene plugin, que es un hueco de
// verdad y no el mismo aviso otra vez. Se cuenta aquí arriba porque su número
// va en la sub-pestaña.
$huecos_reales = [];

foreach ( $medibles as $f ) {
	foreach ( $f['celdas'] as $clave => $celda ) {
		if ( SWPK_Normas::SIN_DATO === $celda['estado'] ) {
			$huecos_reales[ $clave ][] = $f['sitio'];
		}
	}
}

$normas_con_hueco = [];
foreach ( $normas as $n ) {
	if ( ! empty( $huecos_reales[ (string) $n->clave ] ) ) {
		$normas_con_hueco[] = $n;
	}
}

[ $huecos_pagina, $pag_hue, $pags_hue ] = SWPK_Vista::paginar( $normas_con_hueco, SWPK_Vista::pagina_pedida( 'ph' ), 10 );

$VISTAS = [
	'norma' => 'Por norma',
	'sitio' => 'Por sitio',
	'hueco' => 'Sin información',
];

$vista = isset( $_GET['vista'] ) ? sanitize_key( (string) $_GET['vista'] ) : 'norma';

if ( ! isset( $VISTAS[ $vista ] ) ) {
	$vista = 'norma';
}

// Cambiar de vista no arrastra la página ni los filtros de la otra: son
// listas distintas, y la página seis de una no existe en la otra.
$url_vista = static fn( string $v ) => add_query_arg(
	[ 'page' => 'panel-kinsta-seguridad', 'seccion' => 'cumplimiento', 'vista' => $v ],
	admin_url( 'admin.php' )
);

$cuenta_vista = [
	'norma' => count( $con_fallas_todas ),
	'sitio' => count( $medibles ),
	'hueco' => count( $normas_con_hueco ),
];
?>

<div class="swpk-subpestanas"><?php
	echo SWPK_Vista::pastillas( array_map(
		static fn( $v, $etq ) => [
			'texto'  => $etq,
			'n'      => $cuenta_vista[ $v ] > 0 ? $cuenta_vista[ $v ] : '',
			'url'    => $url_vista( $v ),
			'activa' => $v === $vista,
		],
		array_keys( $VISTAS ),
		array_values( $VISTAS )
	) ); // phpcs:ignore WordPress.Security.EscapeOutput
?></div>

<?php if ( 'norma' === $vista && $con_fallas_todas ) : ?>
	<?php
	// El aviso de qué hace «Arreglarlo» va en la burbuja del título y no en un
	// párrafo: es una advertencia que hay que poder leer, no un texto que haya
	// que leer cada vez que se entra.
	SWPK_Vista::caja_abrir(
		'Lo que hay que arreglar' . SWPK_Vista::ayuda(
			'«Arreglarlo» cambia la configuración en el sitio de verdad. Lo ejecuta el agente que está '
			. 'instalado en ese sitio: apaga el editor de código, cierra XML-RPC, enciende la verificación '
			. 'en dos pasos. Queda anotado quién lo pidió y cuándo, y se sigue en «Los arreglos que '
			. 'pedimos». No pasa en el mismo segundo: tarda entre unos minutos y unas horas, según el '
			. 'tráfico del sitio. Lo que el agente no sabe hacer sale marcado como «Hay que hacerlo a '
			. 'mano», y ahí el panel solo avisa.'
		),
		esc_html( sprintf(
			'%d de %d se pueden arreglar desde aquí',
			$cuantas_arreglables,
			count( $con_fallas_todas )
		) ),
		'swpk-c12',
		false
	);
	?>

	<?php
	// Por qué casi todo dice «Hay que hacerlo a mano».
	//
	// De las 73 normas, el agente sabe cambiar cinco. Las demás son cosas que
	// se arreglan entrando al sitio —una cabecera del servidor, una política
	// de contenido— y el panel solo puede avisar. Sin esta frase, se recorren
	// veintiún bloques sin tropezarse con un botón y la conclusión razonable
	// es que el botón desapareció: «ya ni sé dónde arreglo yo las cosas».
	$sabe_arreglar = SWPK_Ordenes::normas_que_sabe_arreglar();
	?>
	<p class="swpk-aviso-corto">
		<?php
		if ( 0 === $cuantas_arreglables ) {
			echo 'Ahora mismo no hay nada que el panel pueda arreglar solo: lo que falla son normas que hay que ir a cambiar al sitio.';
		} else {
			echo esc_html( sprintf(
				'%s desde aquí; el resto hay que ir a cambiarlo al sitio.',
				SWPK_Vista::plural( $cuantas_arreglables, 'problema se arregla', 'problemas se arreglan' )
			) );
		}

		echo wp_kses_post( SWPK_Vista::ayuda(
			'El agente sabe cambiar estas ' . count( $sabe_arreglar ) . ' normas, y ninguna más: '
			. implode( '; ', $sabe_arreglar ) . '. Las demás —una cabecera del servidor, una política de '
			. 'contenido, una versión de PHP— no están a su alcance: el panel las mide y avisa, y el '
			. 'arreglo lo hace una persona entrando al sitio. Los sitios que lleva el cliente quedan '
			. 'fuera aunque la norma sea de las cinco: se miden, no se tocan.'
		) );
		?>
	</p>

	<?php
	SWPK_Vista::barra_de_filtros(
		'panel-kinsta-seguridad',
		[
			'sev'   => [
				'etiqueta' => 'Gravedad',
				'opciones' => $sev_posibles,
				'actual'   => $sev_pedida,
				'todas'    => 'Toda gravedad',
			],
			'norma' => [
				'etiqueta' => 'Qué norma',
				'opciones' => $normas_malas,
				'actual'   => $norma_pedida,
				'todas'    => 'Cualquier norma',
			],
			'arreglo' => [
				'etiqueta' => 'Se puede arreglar',
				'opciones' => $arreglables,
				'actual'   => $arreglo_pedido,
				'todas'    => 'Da igual',
			],
		],
		// Los dos filtros de esta pantalla son dos formularios distintos, así
		// que cada uno tiene que llevarse puesto lo del otro. Si no, filtrar
		// por gravedad borraba el sitio que se estaba buscando abajo.
		[ 'seccion' => 'cumplimiento', 'vista' => $vista, 'q' => SWPK_Vista::busqueda_pedida() ]
	);
	?>

	<?php if ( ! $con_fallas ) : ?>
		<div class="notice notice-info inline"><p>Ningún problema coincide con ese filtro.</p></div>
	<?php endif; ?>

	<?php
	// Qué hace el botón, dicho antes de que nadie lo pulse.
	//
	// «Arreglar» en un panel puede querer decir cuatro cosas, de «lo anoto
	// para que alguien lo haga» a «lo acabo de cambiar». Si no se dice cuál,
	// se pulsa a ciegas — y esto cambia configuración en el sitio de un
	// cliente, que es justo donde nadie debería pulsar a ciegas.
	?>
	

	<?php foreach ( $con_fallas_pagina as $r ) : ?>
		<?php
		$n     = $r['norma'];
		$clave = (string) $n->clave;
		$lista = $quienes_fallan[ $clave ] ?? [];
		$orden = SWPK_Ordenes::orden_de_la_norma( $clave );

		// Entera solo si se pidió esta norma; si no, los primeros y un enlace.
		$lista_entera = $lista;
		$recortada    = '' === $norma_pedida && count( $lista ) > $POR_BLOQUE;

		if ( $recortada ) {
			$lista = array_slice( $lista, 0, $POR_BLOQUE );
		}
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

			<?php // Resumido: el texto entero se lee al abrir la norma sola. ?>
			<p class="swpk-norma-porque" title="<?php echo esc_attr( (string) $n->porque ); ?>"><?php
				echo esc_html(
					'' !== $norma_pedida
						? (string) $n->porque
						: SWPK_Vista::recortar( (string) $n->porque, 160 )
				);
			?></p>

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

			<?php if ( $recortada ) : ?>
				<p class="swpk-norma-pie">
					<a href="<?php echo esc_url( add_query_arg(
						[ 'page' => 'panel-kinsta-seguridad', 'seccion' => 'cumplimiento', 'norma' => $clave ],
						admin_url( 'admin.php' )
					) ); ?>">
						Ver los <?php echo (int) count( $lista_entera ); ?> sitios que lo tienen
					</a>
				</p>
			<?php endif; ?>

			<?php
			$nuestros = array_values(
				array_filter(
					$lista_entera,
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
	<?php SWPK_Vista::paginador( $pag_fallas, $pags_fallas, count( $con_fallas ), 'problemas', 'problema', 'pf' ); ?>
	<?php SWPK_Vista::caja_cerrar(); ?>
<?php elseif ( 'norma' === $vista ) : ?>
	<div class="notice notice-success inline"><p>Ningún sitio incumple una norma de seguridad.</p></div>
<?php endif; ?>

<?php if ( 'hueco' === $vista && $huecos_reales ) : ?>
	<?php SWPK_Vista::caja_abrir(
		'Sin información' . SWPK_Vista::ayuda(
			'No son problemas: es lo que el panel todavía no sabe de sitios que sí puede revisar. Un '
			. 'sitio sin revisar puede estar perfecto o puede estar mal, y decir cualquiera de las dos '
			. 'cosas sería inventar.'
		),
		esc_html( SWPK_Vista::plural( count( $normas_con_hueco ), 'norma', 'normas' ) ),
		'swpk-c12',
		true
	); ?>

	<table class="widefat striped swpk-tabla">
		<thead><tr><th style="width:34%">La norma</th><th>De qué sitios no se sabe</th></tr></thead>
		<tbody>
		<?php foreach ( $huecos_pagina as $n ) : ?>
			<?php
			$clave   = (string) $n->clave;
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
	<?php SWPK_Vista::paginador( $pag_hue, $pags_hue, count( $normas_con_hueco ), 'normas', 'norma', 'ph' ); ?>
	<?php SWPK_Vista::caja_cerrar(); ?>
<?php endif; ?>

<?php if ( 'sitio' === $vista && $medibles ) : ?>
	<?php SWPK_Vista::caja_abrir(
		'Normas sin cumplir por sitio',
		esc_html( SWPK_Vista::plural( count( $medibles ), 'sitio', 'sitios' ) ),
		'swpk-c12',
		true
	); ?>

	<?php
	$como_posibles = [
		'falla'  => 'Le falta algo',
		'hueco'  => 'Con normas sin revisar',
		'limpio' => 'Todo en orden',
	];

	$como_pedido  = SWPK_Vista::opcion_pedida( 'como', $como_posibles );
	$busca_sitio  = SWPK_Vista::busqueda_pedida();
	$medibles_vis = $medibles;

	if ( '' !== $como_pedido || '' !== $busca_sitio ) {
		$aguja        = strtolower( remove_accents( $busca_sitio ) );
		$medibles_vis = array_values(
			array_filter(
				$medibles,
				static function ( $f ) use ( $como_pedido, $aguja, $nombre_de ) {
					if ( 'falla' === $como_pedido && $f['fallas'] < 1 ) {
						return false;
					}
					if ( 'hueco' === $como_pedido && $f['huecos'] < 1 ) {
						return false;
					}
					if ( 'limpio' === $como_pedido && ( $f['fallas'] > 0 || $f['huecos'] > 0 ) ) {
						return false;
					}
					if ( '' === $aguja ) {
						return true;
					}
					$pajar = strtolower( remove_accents( (string) $nombre_de( $f['sitio'] ) . ' ' . (string) $f['sitio']->dominio ) );
					return str_contains( $pajar, $aguja );
				}
			)
		);
	}

	SWPK_Vista::barra_de_filtros(
		'panel-kinsta-seguridad',
		[
			'como' => [
				'etiqueta' => 'Cómo está',
				'opciones' => $como_posibles,
				'actual'   => $como_pedido,
				'todas'    => 'Listas y por construir',
			],
		],
		[ 'seccion' => 'cumplimiento', 'vista' => $vista, 'sev' => $sev_pedida, 'norma' => $norma_pedida, 'arreglo' => $arreglo_pedido ],
		$busca_sitio
	);

	// Esto puede llegar a 108 filas. «No quiero tablas infinitas»: se pagina
	// con el mismo mecanismo que el resto del panel, así que el número de
	// página viaja en la dirección y se puede enlazar y compartir.
	[ $medibles_pagina, $pag_med, $pags_med ] = SWPK_Vista::paginar(
		$medibles_vis,
		SWPK_Vista::pagina_pedida()
	);
	?>

	<?php if ( ! $medibles_vis ) : ?>
		<div class="notice notice-info inline"><p>Ningún sitio coincide con ese filtro.</p></div>
	<?php endif; ?>

	<table class="widefat striped swpk-tabla">
		<thead>
			<tr>
				<th style="width:34%">Sitio</th>
				<th style="width:13%">Cumple</th>
				<th style="width:15%">Le falta</th>
				<th style="width:15%">Sin revisar</th>
				<th style="width:160px"></th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ( $medibles_pagina as $f ) : ?>
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
						<a href="<?php echo esc_url( $url_sitio( (string) $f['sitio']->sitio_id ) ); ?>">
							<span class="swpk-etq swpk-sev-alta"><?php echo (int) $f['fallas']; ?> normas sin cumplir</span>
						</a>
					<?php else : ?>
						<span class="swpk-mudo">nada</span>
					<?php endif; ?>
				</td>
				<td>
					<?php echo $f['huecos'] > 0 ? (int) $f['huecos'] . ' sin medir' : '<span class="swpk-mudo">nada</span>'; ?>
				</td>
				<td style="text-align:right">
					<?php
					// El botón, además del nombre y del número. Es el camino
					// que faltaba: de esta pantalla a las normas de UN sitio.
					?>
					<a class="button button-small" href="<?php echo esc_url( $url_sitio( (string) $f['sitio']->sitio_id ) ); ?>">
						Ver sus normas
					</a>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>

	<?php SWPK_Vista::paginador( $pag_med, $pags_med, count( $medibles_vis ), 'sitios revisables', 'sitio revisable' ); ?>
	<?php SWPK_Vista::caja_cerrar(); ?>
<?php endif; ?>


<?php SWPK_Vista::rejilla_cerrar(); ?>

<?php
// Lo que antes iba encima de las tarjetas: la pregunta que contesta la
// pantalla, el veredicto en una frase y el porqué de los sitios de los que no
// se sabe nada. La maqueta no pone nada entre la cabecera y la primera
// tarjeta, y esto son tres bloques de texto que empujaban la dona fuera de la
// pantalla. Lo que dicen sigue estando; lo que cambia es que ya no estorban.
?>


<?php

?>

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


<p class="swpk-atribucion" style="margin-top:22px">
	El panel no comprueba estas normas por su cuenta: las mira el plugin instalado en cada sitio y las manda
	en su reporte. Así que lo que se ve es siempre cómo estaba ese sitio la última vez que reportó, y por eso
	cada dato lleva su fecha en la ficha del sitio.
</p>
