<?php
/**
 * Registros: lo que pasó, y quién lo hizo.
 *
 * Dos pestañas:
 *
 *   Qué pasó en los sitios   los cambios del inventario, día a día
 *   Quién entró al panel     entradas, salidas e intentos fallidos
 *
 * Antes eran dos entradas del menú —«Movimientos» y «Accesos»— y el jefe las
 * juntó en una sola frase: «eso debería estar en movimientos, todo lo que han
 * hecho». Es la misma pregunta hecha dos veces, una sobre los sitios y otra
 * sobre nosotros, así que se miran en el mismo sitio.
 *
 * Y se llama Registros porque «Movimientos» no se entendía: «debería llamarse
 * registros, histórico o logs».
 */
defined( 'ABSPATH' ) || exit;

$seccion = isset( $_GET['seccion'] ) ? sanitize_key( (string) $_GET['seccion'] ) : 'sitios';

if ( ! in_array( $seccion, [ 'sitios', 'accesos' ], true ) ) {
	$seccion = 'sitios';
}

// Quien no puede ver los accesos no tiene esa pestaña, ni entrando a mano.
$ve_accesos = SWPK_Perfiles::puede( SWPK_Perfiles::VER_ACCESOS );

if ( 'accesos' === $seccion && ! $ve_accesos ) {
	$seccion = 'sitios';
}

$url_sec = static fn( string $s ) => add_query_arg(
	[ 'page' => 'panel-kinsta-movimientos', 'seccion' => $s ],
	admin_url( 'admin.php' )
);

$pastillas = [
	[
		'texto'  => 'En los sitios',
		'url'    => $url_sec( 'sitios' ),
		'activa' => 'sitios' === $seccion,
	],
];

if ( $ve_accesos ) {
	$pastillas[] = [
		'texto'  => 'En el panel',
		'url'    => $url_sec( 'accesos' ),
		'activa' => 'accesos' === $seccion,
	];
}
?>
<div class="wrap">
	<?php
	// «Registros» tampoco se entendía. Esta pantalla contesta una sola
	// pregunta —«¿qué pasó, y cuándo?»— con dos respuestas: lo que pasó en
	// los sitios y lo que hicimos nosotros en el panel. Historial lo dice sin
	// que haya que abrirlo para saberlo.
	SWPK_Vista::cabecera(
		'Historial',
		'sitios' === $seccion
			? 'Qué cambió en cada sitio y cuándo: actualizaciones, plugins nuevos, usuarios y caídas.'
				. SWPK_Vista::ayuda( 'Esto se guarda pase lo que pase con los correos: aunque hoy no se le esté avisando a nadie, el movimiento queda escrito.' )
			: 'Quién entró al panel, cuándo, y quién lo intentó sin lograrlo.',
		SWPK_Vista::pastillas( $pastillas )
	);
	?>
	<?php SWPK_Admin::mostrar_mensaje(); ?>

	<?php if ( 'accesos' === $seccion ) : ?>
		<?php require SWPK_RUTA . 'includes/admin/vistas/registros-accesos.php'; ?>
	<?php else : ?>
		<?php require SWPK_RUTA . 'includes/admin/vistas/bitacora-movimientos.php'; ?>
		<p class="swpk-atribucion"><?php echo wp_kses_post( SWPK_Wordfence::atribucion() ); ?></p>
	<?php endif; ?>

</div>
