<?php
/**
 * Vulnerabilidades: un renglón por hallazgo, no por sitio.
 *
 * ─── Por qué cambió ─────────────────────────────────────────────────────────
 *
 * Esta pantalla enseñaba una ficha por sitio con dos cifras —«4 con
 * vulnerabilidad, 9 por actualizar»— y un botón para entrar. Servía para saber
 * a qué sitio ir, pero no contestaba la pregunta que de verdad se hace:
 * «¿quién tiene este plugin vulnerable?». Para eso había que entrar a los 38
 * sitios uno por uno.
 *
 * La maqueta que el jefe aprobó la resuelve de otra forma: una tabla plana,
 * un renglón por hallazgo, con el sitio y el módulo en la misma fila y un
 * filtro por módulo. Así «enseñame todos los sitios con Elementor vulnerable»
 * son dos clics.
 *
 * ─── Lo que cuesta ──────────────────────────────────────────────────────────
 *
 * Armar la tabla obliga a abrir los hallazgos de cada sitio, que es lo que ya
 * hacía la versión anterior para pintar sus cifras — el costo es el mismo. Se
 * guarda cinco minutos en caché porque ahora cada cambio de filtro recarga la
 * pantalla, y pagar el recorrido entero por mover un desplegable no tiene
 * sentido.
 */
defined( 'ABSPATH' ) || exit;

$todos    = SWPK_Perfiles::sitios_visibles();
$tag      = SWPK_Vista::tag_pedido();
$nivel    = SWPK_Vista::nivel_pedido();
$busqueda = SWPK_Vista::busqueda_pedida();

$por_tag = SWPK_Vista::conteo_por_tag( $todos );

/**
 * Todos los hallazgos de los sitios que esta persona puede ver, aplanados.
 *
 * La caché es por persona porque una asesora ve su cartera y no la de todos,
 * y la corrida diaria la olvida al terminar para que nadie vea datos viejos
 * sin que se le avise.
 */
$clave_cache = 'swpk_hallazgos_' . get_current_user_id();
$todas       = get_transient( $clave_cache );

if ( ! is_array( $todas ) ) {
	$todas = [];

	foreach ( $todos as $s ) {
		foreach ( SWPK_Riesgo::hallazgos( (string) $s->sitio_id ) as $h ) {
			$v = $h['vulnerabilidades'][0] ?? null;

			$todas[] = [
				'sitio_id'   => (string) $s->sitio_id,
				'nombre'     => (string) $s->nombre_visible,
				'dominio'    => (string) $s->dominio,
				'tag'        => (string) $s->tag,
				'revisado'   => (string) ( $s->inventario_en ?? '' ),
				'nivel'      => (string) $h['nivel'],
				'motivo'     => (string) $h['motivo'],
				'modulo'     => SWPK_Textos::limpiar_titulo( (string) $h['titulo'] ),
				'version'    => (string) $h['version'],
				'disponible' => (string) $h['version_disponible'],
				'sin_cuenta' => $v && SWPK_Textos::quien_puede(
					(string) ( $v->cvss_vector ?? '' ),
					(string) ( $v->descripcion ?? '' )
				)['sin_cuenta'],
			];
		}
	}

	set_transient( $clave_cache, $todas, 5 * MINUTE_IN_SECONDS );
}

// Lo del grupo elegido. De aquí salen las cuentas de las pastillas y las
// opciones del desplegable de módulos: si salieran de lo ya filtrado, elegir
// un módulo dejaría ese módulo como única opción y no habría forma de cambiar
// de idea sin quitar el filtro.
$del_grupo = array_values( array_filter(
	$todas,
	static fn( $f ) => '' === $tag || $f['tag'] === $tag
) );

$por_nivel = [];
$modulos   = [];

foreach ( $del_grupo as $f ) {
	$por_nivel[ $f['nivel'] ] = ( $por_nivel[ $f['nivel'] ] ?? 0 ) + 1;
	$modulos[ $f['modulo'] ]  = $f['modulo'];
}

ksort( $modulos, SORT_NATURAL | SORT_FLAG_CASE );
$modulo = SWPK_Vista::opcion_pedida( 'modulo', $modulos );

$filas = array_values( array_filter(
	$del_grupo,
	static function ( $f ) use ( $nivel, $modulo, $busqueda ) {
		if ( '' !== $nivel && $f['nivel'] !== $nivel ) {
			return false;
		}
		if ( '' !== $modulo && $f['modulo'] !== $modulo ) {
			return false;
		}
		if ( '' !== $busqueda ) {
			$aguja = strtolower( remove_accents( $busqueda ) );
			$pajar = strtolower( remove_accents( $f['nombre'] . ' ' . $f['dominio'] . ' ' . $f['modulo'] ) );
			if ( ! str_contains( $pajar, $aguja ) ) {
				return false;
			}
		}
		return true;
	}
) );

// Lo más grave primero y, a igualdad de gravedad, lo que se aprovecha sin
// tener cuenta en el sitio: eso es lo que buscan los ataques automáticos.
usort(
	$filas,
	static fn( $a, $b ) =>
		[ SWPK_Riesgo::peso( $b['nivel'] ), (int) $b['sin_cuenta'], $a['nombre'] ]
		<=> [ SWPK_Riesgo::peso( $a['nivel'] ), (int) $a['sin_cuenta'], $b['nombre'] ]
);

$sitios_tocados = count( array_unique( array_column( $filas, 'sitio_id' ) ) );

[ $en_pantalla, $pagina, $paginas ] = SWPK_Vista::paginar( $filas, SWPK_Vista::pagina_pedida(), 25 );

$colores = SWPK_Riesgo::colores();
$nombres = SWPK_Riesgo::nombres();
?>
<div class="wrap">
	<?php
	// Las pastillas de gravedad, con la cuenta de hallazgos de cada una.
	$pastillas_sev = [];

	foreach ( [ SWPK_Riesgo::CRITICO, SWPK_Riesgo::ALTO, SWPK_Riesgo::MEDIO, SWPK_Riesgo::BAJO ] as $sev ) {
		if ( empty( $por_nivel[ $sev ] ) ) {
			continue;
		}

		$pastillas_sev[] = [
			'texto'  => $nombres[ $sev ],
			'n'      => (int) $por_nivel[ $sev ],
			'url'    => add_query_arg(
				array_filter( [
					'page'  => 'panel-kinsta-vulnerabilidades',
					'tag'   => $tag,
					'nivel' => $nivel === $sev ? '' : $sev,
				] ),
				admin_url( 'admin.php' )
			),
			'activa' => $nivel === $sev,
		];
	}

	SWPK_Vista::cabecera(
		'Vulnerabilidades y pendientes',
		esc_html( sprintf(
			'%s en %s.',
			SWPK_Vista::plural( count( $filas ), 'hallazgo', 'hallazgos' ),
			SWPK_Vista::plural( $sitios_tocados, 'sitio', 'sitios' )
		) ),
		SWPK_Vista::pastillas( $pastillas_sev )
	);
	?>
	<?php SWPK_Admin::mostrar_mensaje(); ?>
	<?php // Sin la parte de la lista: esta pantalla la dice abajo, con más detalle.
	SWPK_Frescura::aviso( false ); ?>
	<?php SWPK_Admin::tira_de_perfil(); ?>
	<?php SWPK_Admin::aviso_demo(); ?>
	<?php
	// La frase de la lista de Wordfence, antes de nada.
	//
	// Esta pantalla vive de esa lista, así que lo primero que hay que saber es
	// si está al día — y sobre todo, distinguir «Wordfence no ha publicado
	// nada» de «no hemos comprobado». El sello de abajo da las fechas; esto da
	// la frase, que es lo que se lee de verdad.
	$wf = SWPK_Wordfence::como_va();
	?>
	<p class="swpk-lista <?php echo $wf['al_dia'] ? '' : 'swpk-lista-mal'; ?>">
		<?php echo esc_html( $wf['frase'] ); ?>
	</p>

	<?php SWPK_Frescura::sello(); ?>

	<?php SWPK_Vista::abrir_barra(); ?>
		<span></span>
		<span><?php SWPK_Admin::boton_exportar( 'vulnerabilidades' ); ?></span>
	<?php SWPK_Vista::cerrar_barra(); ?>

	<?php
	SWPK_Vista::caja_abrir(
		'Todo lo que hay que parchar',
		esc_html( SWPK_Vista::plural( count( $filas ), 'hallazgo', 'hallazgos' ) ),
		'',
		true
	);

	$opciones_sev = [];
	foreach ( [ SWPK_Riesgo::CRITICO, SWPK_Riesgo::ALTO, SWPK_Riesgo::MEDIO, SWPK_Riesgo::BAJO ] as $sev ) {
		if ( ! empty( $por_nivel[ $sev ] ) ) {
			$opciones_sev[ $sev ] = sprintf( '%s (%d)', $nombres[ $sev ], (int) $por_nivel[ $sev ] );
		}
	}

	SWPK_Vista::barra_de_filtros(
		'panel-kinsta-vulnerabilidades',
		[
			'nivel' => [
				'etiqueta' => 'Gravedad',
				'opciones' => $opciones_sev,
				'actual'   => $nivel,
				'todas'    => 'Toda gravedad',
			],
			'tag' => [
				'etiqueta' => 'Grupo',
				'opciones' => array_intersect_key( SWPK_Etiquetas::nombres(), array_filter( $por_tag ) ),
				'actual'   => $tag,
				'todas'    => 'Todos los grupos',
			],
			'modulo' => [
				'etiqueta' => 'Módulo',
				'opciones' => $modulos,
				'actual'   => $modulo,
				'todas'    => 'Todos los módulos',
			],
		],
		[],
		$busqueda,
		'sitio o módulo…'
	);
	?>

	<?php SWPK_Vista::paginador( $pagina, $paginas, count( $filas ), 'hallazgos', 'hallazgo' ); ?>

	<?php if ( ! $en_pantalla ) : ?>
		<div class="notice notice-success inline"><p>
			Nada pendiente con ese filtro.
		</p></div>
	<?php else : ?>
		<table class="widefat striped swpk-tabla">
			<thead>
				<tr>
					<th style="width:230px">Sitio</th>
					<th>Módulo</th>
					<th style="width:150px">Gravedad</th>
					<th style="width:130px">Revisado</th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $en_pantalla as $f ) : ?>
				<tr>
					<td>
						<a href="<?php echo esc_url( SWPK_Vista::url_del_sitio( $f['sitio_id'], 'panel-kinsta-vulnerabilidades' ) ); ?>">
							<?php echo esc_html( $f['nombre'] ); ?>
						</a>
						<br><?php echo SWPK_Admin::grupo_badge( $f['tag'] ); ?>
					</td>
					<td>
						<strong><?php echo esc_html( $f['modulo'] ); ?></strong>
						<br>
						<span class="swpk-gris">
							<?php
							if ( '' !== $f['disponible'] && $f['disponible'] !== $f['version'] ) {
								echo esc_html( sprintf( 'tiene la %s, hay la %s', $f['version'], $f['disponible'] ) );
							} else {
								echo esc_html( sprintf( 'versión %s', $f['version'] ) );
							}
							?>
						</span>
						<?php if ( $f['sin_cuenta'] ) : ?>
							<br><span class="swpk-rojo">se puede aprovechar sin tener cuenta en el sitio</span>
						<?php elseif ( 'vulnerable_sin_detalle' === $f['motivo'] ) : ?>
							<br><span class="swpk-gris">
								<?php echo esc_html( SWPK_Admin::proveedor() ); ?> la marca vulnerable, sin detalle publicado
							</span>
						<?php endif; ?>
					</td>
					<td>
						<span class="swpk-nivel" style="color:<?php echo esc_attr( $colores[ $f['nivel'] ] ?? '' ); ?>">
							<?php echo esc_html( $nombres[ $f['nivel'] ] ?? $f['nivel'] ); ?>
						</span>
						<?php if ( 'actualizacion_pendiente' === $f['motivo'] ) : ?>
							<br><span class="swpk-gris">solo actualización</span>
						<?php endif; ?>
					</td>
					<td class="swpk-gris">
						<?php echo esc_html( '' !== $f['revisado'] ? SWPK_Frescura::fmt( 'j M, H:i', $f['revisado'] ) : '—' ); ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<?php SWPK_Vista::paginador( $pagina, $paginas, count( $filas ), 'hallazgos', 'hallazgo' ); ?>
	<?php endif; ?>
	<?php SWPK_Vista::caja_cerrar(); ?>

	<p class="swpk-atribucion"><?php echo wp_kses_post( SWPK_Wordfence::atribucion() ); ?></p>
</div>
