<?php
/**
 * Lo que toca: la cartera agrupada por ARREGLO, no por sitio.
 *
 * ─── Por qué existe esta pantalla ───────────────────────────────────────────
 *
 * «Todo en verde» no era que el panel mintiera: era que los sitios que
 * administramos estén al día. Y eso convierte al panel en una lista de trabajo,
 * no solo en un informe.
 *
 * El resto del panel está ordenado por sitio, que es como se mira cuando
 * alguien pregunta «¿cómo está Cassesa?». Pero el trabajo no se hace así. Si
 * hay que encender la actualización automática en catorce sitios, abrir
 * catorce fichas y repetir el mismo clic catorce veces es el camino largo. Lo
 * que hace falta es la lista al revés: el arreglo arriba, y debajo en cuántos
 * sitios hay que hacerlo.
 *
 * ─── Qué NO lleva ───────────────────────────────────────────────────────────
 *
 * Tiempos. «No quiero que enviemos información de cuánto tiempo nos toma
 * realizar un ajuste (...) porque eso no lo sabemos. A cada quien le puede
 * tomar un tiempo distinto.» Se dice qué hay que hacer y en cuántos sitios;
 * cuánto tarda cada quien no es un dato del panel.
 *
 * ─── Los sitios que no entran en la cuenta ──────────────────────────────────
 *
 * Los del cliente y los de desarrollo tienen huecos por acuerdo, no por
 * descuido. Van en su propia sección, contados aparte, para que el total de
 * arriba diga la verdad sobre lo que sí es responsabilidad nuestra.
 */

defined( 'ABSPATH' ) || exit;

$todos = SWPK_Perfiles::sitios_visibles();

// ── De qué sitios hablamos ──────────────────────────────────────────────────
//
// Nuestros de verdad: los de auto_update. Los de desarrollo son nuestros pero
// se tocan cuando toca, y los del cliente no se tocan sin pedirlo. Esos dos
// grupos se cuentan al final, no en la lista de trabajo.
$nuestros = [];
$aparte   = [];

foreach ( $todos as $s ) {
	$tag_s = (string) $s->tag;

	if ( SWPK_Etiquetas::AUTO === $tag_s ) {
		$nuestros[] = $s;
	} elseif ( SWPK_Etiquetas::DESARROLLO === $tag_s || SWPK_Etiquetas::NO_UPDATE === $tag_s ) {
		$aparte[] = $s;
	}
}

$ids_nuestros = array_map( static fn( $s ) => (string) $s->sitio_id, $nuestros );
$nombre_de    = [];
foreach ( $todos as $s ) {
	$nombre_de[ (string) $s->sitio_id ] = (string) $s->nombre_visible;
}

// ── Los montones ────────────────────────────────────────────────────────────
//
// Cada montón es un arreglo distinto, con su propia manera de hacerse. El
// orden es el de la mano que lo hace: primero lo que puede hacer el panel
// solo, luego lo que pide entrar al sitio, al final lo que hay que hablar con
// alguien. No por gravedad: por quién tiene que moverse.
$montones = [
	'agente_puede' => [
		'titulo' => 'Lo puede hacer el panel',
		'bajada' => 'Se le manda la orden al agente y el sitio lo hace solo. No hay que entrar a ninguno.',
		'quien'  => 'panel',
		'filas'  => [],
	],
	'entrar' => [
		'titulo' => 'Hay que entrar al sitio',
		'bajada' => 'Alguien abre el escritorio del sitio y lo hace. Son ajustes manuales, no cambios de código.',
		'quien'  => 'nosotros',
		'filas'  => [],
	],
	'hablar' => [
		'titulo' => 'Hay que hablarlo antes',
		'bajada' => 'Cambia algo que el cliente nota, o puede romper el sitio. No se toca sin avisar.',
		'quien'  => 'cliente',
		'filas'  => [],
	],
];

// ── 1. Módulos con el interruptor de automático apagado ─────────────────────
//
// Ojo con una trampa que el panel tenía: muchos módulos salen «sin
// automático» y deben salir así. El agente, los de Kinsta y los nuestros no
// están en el repositorio de WordPress, así que encenderles el interruptor no
// haría nada. Lo que de verdad es trabajo son los que TIENEN una versión
// esperando: ahí el interruptor sí cambia algo.
$auto = SWPK_Inventario::auto_update_de_varios( $ids_nuestros );

$apagados_con_version = [];
$sin_parche_todavia   = [];
$hay_parche           = [];
$cerrados             = [];

foreach ( $nuestros as $s ) {
	$sid = (string) $s->sitio_id;

	foreach ( SWPK_Riesgo::hallazgos( $sid ) as $h ) {
		$v = $h['vulnerabilidades'][0] ?? null;

		if ( 'actualizacion_pendiente' === $h['motivo'] ) {
			if ( '' === (string) $h['auto_update'] && '' !== (string) $h['version_disponible'] ) {
				$apagados_con_version[ (string) $h['slug'] ][] = $sid;
			}
			continue;
		}

		if ( $v && (int) ( $v->parcheada ?? 0 ) ) {
			$hay_parche[ (string) $h['slug'] ][] = $sid;
		} elseif ( $v && SWPK_Repositorio::CERRADO === SWPK_Repositorio::estado( (string) $h['slug'] ) ) {
			$cerrados[ (string) $h['slug'] ][] = $sid;
		} else {
			$sin_parche_todavia[ (string) $h['slug'] ][] = $sid;
		}
	}
}

$fila = static function ( string $que, string $como, array $por_slug ): ?array {
	if ( ! $por_slug ) {
		return null;
	}

	$sitios = [];
	foreach ( $por_slug as $lista ) {
		foreach ( $lista as $sid ) {
			$sitios[ $sid ] = true;
		}
	}

	$modulos = 0;
	foreach ( $por_slug as $lista ) {
		$modulos += count( $lista );
	}

	return [
		'que'     => $que,
		'como'    => $como,
		'modulos' => $modulos,
		'sitios'  => array_keys( $sitios ),
		'detalle' => $por_slug,
	];
};

$f = $fila(
	'Encender la actualización automática',
	'En el escritorio del sitio, Plugins → Activar las actualizaciones automáticas. Solo en los que tienen una versión esperando: en los demás el interruptor no hace nada porque el módulo no está en el repositorio de WordPress.',
	$apagados_con_version
);
if ( $f ) {
	$montones['entrar']['filas'][] = $f;
}

$f = $fila(
	'Actualizar el módulo: ya hay parche',
	'El autor sacó la versión arreglada. Se resuelve actualizando.',
	$hay_parche
);
if ( $f ) {
	$montones['entrar']['filas'][] = $f;
}

$f = $fila(
	'Reemplazar el plugin: WordPress lo cerró',
	'No va a haber arreglo nunca. Hay que buscar con qué sustituirlo, y eso cambia algo que el cliente usa.',
	$cerrados
);
if ( $f ) {
	$montones['hablar']['filas'][] = $f;
}

$f = $fila(
	'Esperar el parche, o desactivar mientras tanto',
	'El autor todavía no lo ha arreglado. Desactivar quita el riesgo pero también quita la función, así que se avisa antes.',
	$sin_parche_todavia
);
if ( $f ) {
	$montones['hablar']['filas'][] = $f;
}

// ── 2. Las normas de seguridad que el panel sí sabe arreglar ────────────────
//
// Estas son las que no piden que nadie entre a ningún sitio: el panel le manda
// la orden al agente y el agente la ejecuta. Es el montón que de verdad se
// resuelve con un botón.
$por_norma   = [];
$cumplimiento = SWPK_Normas::cumplimiento();

foreach ( SWPK_Normas::todas() as $norma ) {
	if ( ! (int) ( $norma->arreglable ?? 0 ) || ! (int) ( $norma->activa ?? 1 ) ) {
		continue;
	}

	$clave     = (string) $norma->clave;
	$incumplen = [];

	foreach ( $nuestros as $s ) {
		$celda = $cumplimiento[ (string) $s->sitio_id ][ $clave ] ?? null;

		if ( $celda && SWPK_Normas::FALLA === (string) $celda->estado ) {
			$incumplen[] = (string) $s->sitio_id;
		}
	}

	if ( $incumplen ) {
		$por_norma[ $clave ] = [
			'que'     => (string) $norma->nombre,
			'como'    => (string) ( $norma->problema ?? '' ),
			'modulos' => count( $incumplen ),
			'sitios'  => $incumplen,
			'detalle' => [],
		];
	}
}

foreach ( $por_norma as $n ) {
	$montones['agente_puede']['filas'][] = $n;
}

$total_tareas = 0;
$total_sitios = [];
foreach ( $montones as $m ) {
	foreach ( $m['filas'] as $fl ) {
		++$total_tareas;
		foreach ( $fl['sitios'] as $sid ) {
			$total_sitios[ $sid ] = true;
		}
	}
}
?>

<div class="wrap swpk-wrap">
	<h1>Lo que toca</h1>
	<p class="swpk-bajada">
		La cartera ordenada por arreglo, no por sitio: cada línea es una cosa que hacer y en cuántos
		sitios hay que hacerla. Sin estimaciones de tiempo: el panel no sabe cuánto tarda cada quien.
	</p>

	<?php
	SWPK_Vista::caja_abrir(
		sprintf(
			'%d cosa%s que hacer en %d sitio%s de actualización automática',
			$total_tareas,
			1 === $total_tareas ? '' : 's',
			count( $total_sitios ),
			1 === count( $total_sitios ) ? '' : 's'
		),
		sprintf( '<span class="swpk-gris">De %d sitios nuestros con actualización automática</span>', count( $nuestros ) ),
		'',
		true
	);
	?>

	<?php if ( ! $total_tareas ) : ?>
		<div class="notice notice-success inline"><p>
			<strong>No hay nada pendiente</strong> en los sitios de actualización automática.
		</p></div>
	<?php endif; ?>

	<?php foreach ( $montones as $clave_m => $m ) : ?>
		<?php if ( ! $m['filas'] ) : ?>
			<?php continue; ?>
		<?php endif; ?>

		<h2 class="swpk-toca-titulo"><?php echo esc_html( $m['titulo'] ); ?></h2>
		<p class="swpk-gris" style="margin:-6px 0 12px"><?php echo esc_html( $m['bajada'] ); ?></p>

		<table class="widefat striped swpk-tabla">
			<thead>
				<tr>
					<th>Qué hay que hacer</th>
					<th style="width:120px">Cuántos</th>
					<th style="width:38%">Cómo se hace</th>
					<th style="width:150px"></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $m['filas'] as $fl ) : ?>
				<tr>
					<td><strong><?php echo esc_html( $fl['que'] ); ?></strong>
						<?php if ( $fl['detalle'] ) : ?>
							<br><span class="swpk-mono swpk-gris" style="font-size:12px">
								<?php
								$slugs = array_slice( array_keys( $fl['detalle'] ), 0, 4 );
								echo esc_html( implode( ', ', $slugs ) );
								if ( count( $fl['detalle'] ) > 4 ) {
									echo esc_html( sprintf( ' y %d más', count( $fl['detalle'] ) - 4 ) );
								}
								?>
							</span>
						<?php endif; ?>
					</td>
					<td>
						<strong><?php echo (int) count( $fl['sitios'] ); ?></strong>
						<span class="swpk-gris"><?php echo esc_html( 1 === count( $fl['sitios'] ) ? 'sitio' : 'sitios' ); ?></span>
						<?php if ( $fl['detalle'] ) : ?>
							<br><span class="swpk-gris" style="font-size:12px">
								<?php echo (int) $fl['modulos']; ?> módulo<?php echo 1 === (int) $fl['modulos'] ? '' : 's'; ?>
							</span>
						<?php endif; ?>
					</td>
					<td class="swpk-gris"><?php echo esc_html( $fl['como'] ); ?></td>
					<td>
						<?php
						// Lleva a la lista de sitios ya filtrada, que es donde se
						// hace el trabajo. Un botón que «lo arregla todo» desde
						// aquí es la siguiente vuelta: primero hay que poder ver
						// a quiénes les toca.
						$url = 'agente_puede' === $clave_m
							? admin_url( 'admin.php?page=panel-kinsta-seguridad' )
							: admin_url( 'admin.php?page=panel-kinsta-sitios&tag=auto_update' );
						?>
						<a class="button button-small" href="<?php echo esc_url( $url ); ?>">Ver los sitios</a>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	<?php endforeach; ?>

	<?php SWPK_Vista::caja_cerrar(); ?>

	<?php
	// ── Los que no entran en la cuenta, y por qué ───────────────────────────
	$por_tag_aparte = [];
	foreach ( $aparte as $s ) {
		$por_tag_aparte[ (string) $s->tag ][] = $s;
	}

	SWPK_Vista::caja_abrir(
		sprintf( '%d sitios que no entran en esa cuenta', count( $aparte ) ),
		'',
		'',
		true
	);
	?>

	<p>
		No están sin atender: están <strong>fuera del acuerdo de mantenerlos al día</strong>, y por eso
		no se suman arriba. Si se contaran, el total diría que vamos peor de lo que vamos —y si se
		escondieran, diría que la cartera está más limpia de lo que está.
	</p>

	<table class="widefat striped swpk-tabla">
		<tbody>
		<?php foreach ( $por_tag_aparte as $tag_a => $lista ) : ?>
			<tr>
				<th scope="row" style="width:220px">
					<?php echo wp_kses_post( SWPK_Admin::grupo_badge( (string) $tag_a ) ); ?>
				</th>
				<td style="width:90px"><strong><?php echo (int) count( $lista ); ?></strong></td>
				<td class="swpk-gris">
					<?php
					echo esc_html(
						SWPK_Etiquetas::NO_UPDATE === $tag_a
							? 'Son del cliente. No se les actualiza nada sin que lo pidan, así que tener módulos viejos es lo esperado, no un descuido nuestro.'
							: 'Están en construcción o en pruebas. Se ponen al día cuando salen a producción, no antes.'
					);
					?>
				</td>
				<td style="width:150px">
					<a class="button button-small"
						href="<?php echo esc_url( admin_url( 'admin.php?page=panel-kinsta-sitios&tag=' . rawurlencode( (string) $tag_a ) ) ); ?>">
						Ver los sitios
					</a>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>

	<?php SWPK_Vista::caja_cerrar(); ?>
</div>
