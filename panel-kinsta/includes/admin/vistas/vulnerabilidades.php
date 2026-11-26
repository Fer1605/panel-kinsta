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
$clave_cache = 'swpk_hallazgos2_' . get_current_user_id();
$todas       = get_transient( $clave_cache );

if ( ! is_array( $todas ) ) {
	$todas = [];

	foreach ( $todos as $s ) {
		foreach ( SWPK_Riesgo::hallazgos( (string) $s->sitio_id ) as $h ) {
			$v = $h['vulnerabilidades'][0] ?? null;

			// ── Lo que faltaba: QUÉ es la falla ─────────────────────────
			//
			// Esta pantalla decía cuántas hay y de qué módulo, y nunca qué
			// hace la falla. «No estoy entendiendo qué vulnerabilidad es.»
			// Tenía razón, y lo peor es que el dato ya estaba guardado: el
			// feed de Wordfence trae el título, la descripción y el CVE, y
			// SWPK_Textos ya sabe traducirlos a una frase que se entiende.
			// Solo había que enseñarlo.
			$quien = $v
				? SWPK_Textos::quien_puede(
					(string) ( $v->cvss_vector ?? '' ),
					(string) ( $v->descripcion ?? '' )
				)
				: [ 'sin_cuenta' => false, 'frase' => '' ];

			$todas[] = [
				'sitio_id'     => (string) $s->sitio_id,
				'nombre'       => (string) $s->nombre_visible,
				'dominio'      => (string) $s->dominio,
				'tag'          => (string) $s->tag,
				'revisado'     => (string) ( $s->inventario_en ?? '' ),
				'nivel'        => (string) $h['nivel'],
				'motivo'       => (string) $h['motivo'],
				'modulo'       => SWPK_Textos::limpiar_titulo( (string) $h['titulo'] ),
				'version'      => (string) $h['version'],
				'disponible'   => (string) $h['version_disponible'],
				'sin_cuenta'   => (bool) $quien['sin_cuenta'],
				'quien'        => (string) ( $quien['frase'] ?? '' ),
				'que_permite'  => $v
					? SWPK_Textos::explicar( (string) ( $v->titulo ?? '' ), (string) ( $v->descripcion ?? '' ) )
					: '',
				'titulo_vuln'  => $v ? (string) ( $v->titulo ?? '' ) : '',
				'cve'          => $v ? (string) ( $v->cve ?? '' ) : '',
				'cuantas'      => count( $h['vulnerabilidades'] ),
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

// Las opciones de sitio salen de los hallazgos que hay, no del inventario:
// ofrecer un sitio sin nada pendiente es ofrecer una tabla vacía. Con el
// número de hallazgos al lado, que es lo que se viene a saber.
$cuenta_sitio = [];
$nombre_sitio = [];

foreach ( $del_grupo as $f ) {
	$por_nivel[ $f['nivel'] ] = ( $por_nivel[ $f['nivel'] ] ?? 0 ) + 1;
	$modulos[ $f['modulo'] ]  = $f['modulo'];

	$cuenta_sitio[ $f['sitio_id'] ] = ( $cuenta_sitio[ $f['sitio_id'] ] ?? 0 ) + 1;
	$nombre_sitio[ $f['sitio_id'] ] = (string) $f['nombre'];
}

ksort( $modulos, SORT_NATURAL | SORT_FLAG_CASE );
$modulo = SWPK_Vista::opcion_pedida( 'modulo', $modulos );

$sitios_opciones = [];
foreach ( $nombre_sitio as $id_s => $nom_s ) {
	$sitios_opciones[ $id_s ] = sprintf( '%s (%d)', $nom_s, (int) $cuenta_sitio[ $id_s ] );
}
asort( $sitios_opciones, SORT_NATURAL | SORT_FLAG_CASE );

$sitio_filtro = SWPK_Vista::opcion_pedida( 'sitio_f', $sitios_opciones );

// ── Filtrar por qué se puede hacer con ella ─────────────────────────────────
//
// Es la pregunta con la que se abre esta pantalla un lunes: «¿qué puedo
// arreglar hoy?». Las que tienen parche se resuelven actualizando; las que no,
// no hay nada que hacer todavía; las de un plugin cerrado hay que
// reemplazarlas. Mezcladas en una sola lista de 2.755 no se distinguen.
$parche_filtro = SWPK_Vista::opcion_pedida(
	'parche',
	[ 'hay' => 'Hay parche', 'sin' => 'Sin parche todavía', 'cerrado' => 'Cerrado por WordPress' ]
);

$filas = array_values( array_filter(
	$del_grupo,
	static function ( $f ) use ( $nivel, $modulo, $busqueda, $sitio_filtro, $parche_filtro ) {
		if ( '' !== $sitio_filtro && $f['sitio_id'] !== $sitio_filtro ) {
			return false;
		}
		if ( '' !== $parche_filtro ) {
			$v_f = $f['vulnerabilidades'][0] ?? null;

			if ( ! $v_f ) {
				return false;
			}

			if ( (int) ( $v_f->parcheada ?? 0 ) ) {
				$caso = 'hay';
			} elseif ( SWPK_Repositorio::CERRADO === SWPK_Repositorio::estado( (string) $f['slug'] ) ) {
				$caso = 'cerrado';
			} else {
				$caso = 'sin';
			}

			if ( $caso !== $parche_filtro ) {
				return false;
			}
		}
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

	<?php
	// La otra mitad de la aclaración que pidió al no distinguir esta pantalla
	// de Seguridad. La burbuja es la misma en las dos, a propósito: es la misma
	// explicación, y quien la lea en cualquiera de las dos ya no vuelve a
	// preguntarlo.
	?>
	<p class="swpk-comoasi-linea">
		Aquí están <strong>las fallas conocidas</strong> de los plugins y temas instalados, y lo que
		está pendiente de actualizar. Lo que todo sitio debería cumplir, tenga lo que tenga
		instalado, está en
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=panel-kinsta-seguridad' ) ); ?>">Seguridad</a>.
		<?php echo wp_kses_post( SWPK_Vista::ayuda(
			'No son lo mismo y no se arreglan igual. Una norma la incumple el sitio por cómo está '
			. 'configurado —no exige HTTPS, publica sus nombres de usuario— y se arregla cambiando '
			. 'esa configuración. Una vulnerabilidad la trae un plugin o un tema en una versión con '
			. 'una falla ya descubierta, y se arregla actualizándolo. Un sitio puede cumplir las '
			. 'normas y tener diez plugins con falla, y al revés.'
		) ); ?>
	</p>

	<?php // Sin la parte de la lista: esta pantalla la dice abajo, con más detalle.
	SWPK_Frescura::aviso( false ); ?>
	<?php
	// La frase de la lista de Wordfence, antes de nada.
	//
	// Esta pantalla vive de esa lista, así que lo primero que hay que saber es
	// si está al día — y sobre todo, distinguir «Wordfence no ha publicado
	// nada» de «no hemos comprobado». El sello de abajo da las fechas; esto da
	// la frase, que es lo que se lee de verdad.
	$wf = SWPK_Wordfence::como_va();
	?>
	<?php
	ob_start();
	SWPK_Admin::boton_exportar( 'vulnerabilidades' );
	$boton_excel = ob_get_clean();
	?>

	<?php
	SWPK_Vista::caja_abrir(
		'Todo lo que hay que parchar',
		'<span style="margin-right:12px">Lo más grave primero</span>' . $boton_excel,
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
			'sitio_f' => [
				'etiqueta' => 'Sitio',
				'opciones' => $sitios_opciones,
				'actual'   => $sitio_filtro,
				'todas'    => 'Todos los sitios',
			],
			'modulo' => [
				'etiqueta' => 'Módulo',
				'opciones' => $modulos,
				'actual'   => $modulo,
				'todas'    => 'Todos los módulos',
			],
			'parche' => [
				'etiqueta' => 'Qué se puede hacer',
				'opciones' => [
					'hay'     => 'Hay parche: actualizar',
					'sin'     => 'Sin parche todavía',
					'cerrado' => 'Cerrado por WordPress',
				],
				'actual'   => $parche_filtro,
				'todas'    => 'Qué se puede hacer',
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
					<th>Qué pasa, y qué hay que hacer</th>
					<th style="width:120px">Gravedad</th>
					<th style="width:110px">Revisado</th>
					<th style="width:110px"></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $en_pantalla as $f ) : ?>
				<?php
				// ── Qué dice cada fila, y en qué orden ──────────────────────
				//
				// Primero el sitio. Luego qué pasa, en castellano. Luego qué
				// hay que hacer. Antes empezaba por el módulo y nunca decía
				// qué era la falla: «no estoy entendiendo qué vulnerabilidad
				// es… de primero debería aparecer el sitio».
				//
				// La frase de quién puede aprovecharla sale de SWPK_Textos,
				// que la deduce del vector del CVE. Decía «se puede aprovechar
				// sin tener cuenta en el sitio», que no se entiende; ahora
				// dice quién puede usarla y desde dónde.
				$hay_version = '' !== $f['disponible'] && $f['disponible'] !== $f['version'];

				$que_pasa = match ( $f['motivo'] ) {
					'actualizacion_pendiente' => sprintf( '%s está desactualizado', $f['modulo'] ),
					'vulnerable_sin_detalle'  => sprintf( '%s tiene una versión marcada como vulnerable', $f['modulo'] ),
					default                   => 1 < (int) $f['cuantas']
						? sprintf( '%s tiene %d fallas conocidas', $f['modulo'], (int) $f['cuantas'] )
						: sprintf( '%s tiene una falla conocida', $f['modulo'] ),
				};

				$que_hacer = $hay_version
					? sprintf( 'Hay que actualizarlo a la %s. Tiene la %s.', $f['disponible'], $f['version'] )
					: sprintf( 'No hay versión nueva publicada. Tiene la %s.', $f['version'] );

				// Lo que va en la burbujita: el nombre técnico con el que
				// Wordfence la publicó, para poder buscarla, y su CVE.
				$detalle = '';
				if ( '' !== (string) $f['titulo_vuln'] ) {
					$detalle = 'Wordfence la publicó como: ' . $f['titulo_vuln'] . '.';
				}
				if ( '' !== (string) $f['cve'] ) {
					$detalle .= ( '' !== $detalle ? ' ' : '' ) . 'Identificador ' . $f['cve'] . '.';
				}
				if ( 'vulnerable_sin_detalle' === $f['motivo'] ) {
					$detalle = SWPK_Admin::proveedor() . ' marca esta versión como vulnerable, pero el feed de Wordfence todavía no publicó el detalle de qué falla es.';
				}
				if ( 'actualizacion_pendiente' === $f['motivo'] ) {
					$detalle = 'No hay ninguna vulnerabilidad conocida en la versión instalada. Es una actualización pendiente: se pone al día para no acumular, no porque urja hoy.';
				}
				?>
				<tr>
					<td>
						<a href="<?php echo esc_url( SWPK_Vista::url_del_sitio( $f['sitio_id'], 'panel-kinsta-vulnerabilidades' ) ); ?>">
							<strong><?php echo esc_html( $f['nombre'] ); ?></strong>
						</a>
						<br><?php echo SWPK_Admin::grupo_badge( $f['tag'] ); ?>
					</td>
					<td>
						<strong><?php echo esc_html( $que_pasa ); ?></strong>
						<?php echo wp_kses_post( SWPK_Vista::ayuda( $detalle ) ); ?>

						<?php if ( '' !== (string) $f['que_permite'] ) : ?>
							<br><span><?php echo esc_html( $f['que_permite'] ); ?></span>
						<?php endif; ?>

						<?php if ( $f['sin_cuenta'] ) : ?>
							<br><span class="swpk-rojo">
								Cualquiera desde internet puede usarla, sin necesidad de tener una cuenta en el sitio.
							</span>
						<?php elseif ( '' !== (string) $f['quien'] ) : ?>
							<br><span class="swpk-gris"><?php echo esc_html( $f['quien'] ); ?></span>
						<?php endif; ?>

						<br><span class="swpk-gris"><?php echo esc_html( $que_hacer ); ?></span>
					</td>
					<td>
						<?php echo wp_kses_post( SWPK_Admin::nivel_badge( (string) $f['nivel'] ) ); ?>
						<?php if ( 'actualizacion_pendiente' === $f['motivo'] ) : ?>
							<br><span class="swpk-gris">solo actualización</span>
						<?php else : ?>
							<?php $pild = SWPK_Admin::parche_badge( $f['vulnerabilidades'][0] ?? null, (string) $f['slug'] ); ?>
							<?php if ( '' !== $pild ) : ?>
								<div style="margin-top:6px"><?php echo wp_kses_post( $pild ); ?></div>
							<?php endif; ?>
						<?php endif; ?>
					</td>
					<td class="swpk-gris">
						<?php echo esc_html( '' !== $f['revisado'] ? SWPK_Frescura::fmt( 'j M, H:i', $f['revisado'] ) : '—' ); ?>
					</td>
					<td style="text-align:right">
						<a class="button button-small" href="<?php
							echo esc_url( add_query_arg(
								'solo',
								'vuln',
								SWPK_Vista::url_del_sitio( $f['sitio_id'], 'panel-kinsta-vulnerabilidades' )
							) );
						?>">Ver el sitio</a>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<?php SWPK_Vista::paginador( $pagina, $paginas, count( $filas ), 'hallazgos', 'hallazgo' ); ?>
	<?php endif; ?>
	<?php SWPK_Vista::caja_cerrar(); ?>

	<?php
	// El sello, la tira de perfil y los botones van AL PIE, como en la maqueta:
	// entre la cabecera y la primera tarjeta ella no pone nada. Arriba, el
	// sello solo —que son cuatro renglones de fechas— empujaba la tabla fuera
	// de la pantalla.
	?>
	<p class="swpk-lista <?php echo $wf['al_dia'] ? '' : 'swpk-lista-mal'; ?>">
		<?php echo esc_html( $wf['frase'] ); ?>
	</p>

	<?php SWPK_Admin::aviso_demo(); ?>

	<p class="swpk-nota">
		La gravedad no la inventa el panel: es la que publica Wordfence Intelligence para cada
		vulnerabilidad.
	</p>

	<p class="swpk-atribucion"><?php echo wp_kses_post( SWPK_Wordfence::atribucion() ); ?></p>
</div>
