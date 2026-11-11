<?php
/**
 * Vulnerabilidades: la lista de sitios con algo pendiente, en resumen.
 *
 * Aquí no se ven las vulnerabilidades una por una — con 107 sitios eso era una
 * página infinita. Se ve cuánto tiene cada sitio, y se entra al que interese.
 */
defined( 'ABSPATH' ) || exit;

$todos    = SWPK_Perfiles::sitios_visibles();
$tag      = SWPK_Vista::tag_pedido();
$nivel    = SWPK_Vista::nivel_pedido();
$busqueda = SWPK_Vista::busqueda_pedida();

$por_tag   = SWPK_Vista::conteo_por_tag( $todos );
$del_grupo = SWPK_Vista::filtrar( $todos, $tag, '', '' );

$tiene_algo = static fn( $s ) => ! in_array(
	SWPK_Riesgo::nivel_del_sitio( $s->sitio_id ),
	[ SWPK_Riesgo::AL_DIA, SWPK_Riesgo::SIN_DATOS ],
	true
);

$con_algo = array_values( array_filter( SWPK_Vista::filtrar( $todos, $tag, $nivel, $busqueda ), $tiene_algo ) );

/**
 * El desglose de cada grupo, para la tarjeta.
 *
 * La tarjeta no puede decir "82": eso se lee como "82 sitios con
 * vulnerabilidades". Dice "31 de 82" y abajo qué pasa con los otros 51.
 */
$detalles = [];
foreach ( SWPK_Etiquetas::nombres() as $t => $nombre_tag ) {
	$del_t = SWPK_Vista::filtrar( $todos, $t, '', '' );
	if ( ! $del_t ) {
		continue;
	}

	$pend = 0;
	$dia  = 0;
	$sin  = 0;

	foreach ( $del_t as $s ) {
		$n = SWPK_Riesgo::nivel_del_sitio( $s->sitio_id );
		if ( SWPK_Riesgo::AL_DIA === $n ) {
			++$dia;
		} elseif ( SWPK_Riesgo::SIN_DATOS === $n ) {
			++$sin;
		} else {
			++$pend;
		}
	}

	$resto = [];
	if ( $dia ) {
		$resto[] = $dia . ' al día';
	}
	if ( $sin ) {
		$resto[] = $sin . ' sin escanear';
	}

	$detalles[ $t ] = [
		'n'   => $pend,
		'de'  => count( $del_t ),
		'sub' => $resto ? implode( ' · ', $resto ) : 'todos con algo pendiente',
	];
}

$al_dia    = $detalles[ $tag ]['de'] ?? 0;
$sin_datos = 0;

$orden = [ SWPK_Riesgo::CRITICO => 0, SWPK_Riesgo::ALTO => 1, SWPK_Riesgo::MEDIO => 2, SWPK_Riesgo::BAJO => 3 ];
usort(
	$con_algo,
	static fn( $a, $b ) => ( $orden[ SWPK_Riesgo::nivel_del_sitio( $a->sitio_id ) ] ?? 9 )
		<=> ( $orden[ SWPK_Riesgo::nivel_del_sitio( $b->sitio_id ) ] ?? 9 )
);

$por_nivel = SWPK_Vista::conteo_por_nivel( $del_grupo );
// En esta pantalla solo hay sitios con pendientes, así que esos dos filtros
// no devolverían nada. Se ponen en cero para que el filtro los esconda.
$por_nivel[ SWPK_Riesgo::AL_DIA ]    = 0;
$por_nivel[ SWPK_Riesgo::SIN_DATOS ] = 0;

[ $en_pantalla, $pagina, $paginas ] = SWPK_Vista::paginar( $con_algo, SWPK_Vista::pagina_pedida(), 15 );
?>
<div class="wrap">
	<h1>Vulnerabilidades y pendientes</h1>
	<?php SWPK_Admin::mostrar_mensaje(); ?>
	<?php SWPK_Admin::tira_de_perfil(); ?>
	<?php SWPK_Admin::aviso_demo(); ?>

	<?php SWPK_Vista::selector_de_grupo( 'panel-kinsta-vulnerabilidades', $tag, $por_tag, [], $detalles ); ?>

	<?php if ( '' === $tag ) : ?>
		</div>
		<?php return; ?>
	<?php endif; ?>

	<?php SWPK_Vista::abrir_barra(); ?>
		<?php SWPK_Vista::filtro_de_riesgo( $nivel, $por_nivel ); ?>
		<?php SWPK_Vista::buscador( 'panel-kinsta-vulnerabilidades', $busqueda, [ 'tag' => $tag, 'nivel' => $nivel ] ); ?>
	<?php SWPK_Vista::cerrar_barra(); ?>

	<?php SWPK_Vista::paginador( $pagina, $paginas, count( $con_algo ), 'sitios con pendientes' ); ?>

	<?php if ( ! $en_pantalla ) : ?>
		<div class="notice notice-success inline"><p>
			Nada pendiente en este grupo con ese filtro.
		</p></div>
	<?php endif; ?>

	<?php foreach ( $en_pantalla as $s ) : ?>
		<?php
		$hallazgos = SWPK_Riesgo::hallazgos( $s->sitio_id );
		$vulns     = 0;
		$sin_cta   = 0;
		foreach ( $hallazgos as $h ) {
			if ( 'actualizacion_pendiente' === $h['motivo'] ) {
				continue;
			}
			++$vulns;
			$v = $h['vulnerabilidades'][0] ?? null;
			if ( $v && SWPK_Textos::quien_puede( (string) ( $v->cvss_vector ?? '' ), (string) $v->descripcion )['sin_cuenta'] ) {
				++$sin_cta;
			}
		}
		$url = SWPK_Vista::url_del_sitio( (string) $s->sitio_id, 'panel-kinsta-vulnerabilidades' );
		?>
		<div class="swpk-ficha">
			<div>
				<p class="swpk-ficha-nom">
					<?php echo wp_kses_post( SWPK_Admin::nivel_badge( SWPK_Riesgo::nivel_del_sitio( $s->sitio_id ) ) ); ?>
					<?php echo esc_html( $s->nombre_visible ); ?>
				</p>
				<span class="swpk-ficha-dom"><?php echo esc_html( $s->dominio ); ?></span>
				<?php if ( $sin_cta ) : ?>
					<p style="margin:6px 0 0;color:#b32d2e;font-size:13px">
						<?php echo (int) $sin_cta; ?> se <?php echo 1 === $sin_cta ? 'puede' : 'pueden'; ?>
						aprovechar sin tener cuenta en el sitio
					</p>
				<?php endif; ?>
			</div>

			<div class="swpk-ficha-cifras">
				<div class="swpk-cifra">
					<b style="color:<?php echo $vulns ? '#b32d2e' : '#008a20'; ?>"><?php echo (int) $vulns; ?></b>
					<span>con vulnerabilidad</span>
				</div>
				<div class="swpk-cifra">
					<b><?php echo count( $hallazgos ) - $vulns; ?></b>
					<span>por actualizar</span>
				</div>
				<a class="button button-primary" href="<?php echo esc_url( $url ); ?>">Ver detalle</a>
			</div>
		</div>
	<?php endforeach; ?>

	<?php if ( $en_pantalla ) : ?>
		<?php SWPK_Vista::paginador( $pagina, $paginas, count( $con_algo ), 'sitios con pendientes' ); ?>
	<?php endif; ?>

	<p class="swpk-atribucion"><?php echo wp_kses_post( SWPK_Wordfence::atribucion() ); ?></p>
</div>
