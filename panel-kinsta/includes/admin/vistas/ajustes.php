<?php
/**
 * Ajustes: dos pestañas.
 *
 *   El panel            la llave del hosting, los grupos, cómo corre
 *   Conectarse (API)    cómo consultarlo desde fuera
 *
 * «Conectarse (API)» era una entrada suelta del menú y casi nadie la abría dos
 * veces: «no entiendo, es un montón de info que no voy a leer». Es un ajuste
 * del panel como los demás, así que vive aquí, detrás de una pestaña, y deja
 * el menú más corto.
 */
defined( 'ABSPATH' ) || exit;

$seccion = isset( $_GET['seccion'] ) ? sanitize_key( (string) $_GET['seccion'] ) : 'panel';

if ( ! in_array( $seccion, [ 'panel', 'correos', 'api' ], true ) ) {
	$seccion = 'panel';
}

$url_sec = static fn( string $s ) => add_query_arg(
	[ 'page' => 'panel-kinsta-ajustes', 'seccion' => $s ],
	admin_url( 'admin.php' )
);
?>
<div class="wrap">
	<?php
	SWPK_Vista::cabecera(
		'Ajustes',
		match ( $seccion ) {
			'correos' => 'A qué direcciones nuestras sale cada aviso.',
			'api'     => 'Cómo consultar el panel desde fuera, o desde Claude.',
			default   => 'La llave del hosting, los grupos y los tiempos.',
		},
		SWPK_Vista::pastillas( [
			[
				'texto'  => 'El panel',
				'url'    => $url_sec( 'panel' ),
				'activa' => 'panel' === $seccion,
			],
			[
				'texto'  => 'Correos',
				'url'    => $url_sec( 'correos' ),
				'activa' => 'correos' === $seccion,
			],
			[
				'texto'  => 'Conectarse (API)',
				'url'    => $url_sec( 'api' ),
				'activa' => 'api' === $seccion,
			],
		] )
	);
	?>
	<?php SWPK_Admin::mostrar_mensaje(); ?>

	<?php require SWPK_RUTA . 'includes/admin/vistas/ajustes-' . $seccion . '.php'; ?>

	<?php
	// El sello de frescura —de cuándo es cada dato— vive en «Cómo está el
	// panel», que es la tarjeta que contesta eso. Suelto al pie de las tres
	// pestañas era «puro estorbo», y en la de correos no venía a cuento: ahí
	// no hay ningún dato con fecha.
	?>
</div>
