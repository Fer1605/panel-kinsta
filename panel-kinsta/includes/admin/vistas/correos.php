<?php
/**
 * Correos: dos pestañas.
 *
 *   Qué sale        el interruptor, los tres avisos y cuándo salen
 *   Las plantillas  cómo están redactados
 *
 * Dos pestañas y no dos entradas en el menú, porque son la misma cosa vista de
 * dos maneras: qué se manda, y qué dice lo que se manda. Y se entra por la
 * primera, que es la que se mira todos los días; la redacción se cambia cada
 * varios meses.
 */

defined( 'ABSPATH' ) || exit;

$seccion = isset( $_GET['seccion'] ) ? sanitize_key( (string) $_GET['seccion'] ) : 'envios';

if ( ! in_array( $seccion, [ 'envios', 'plantillas' ], true ) ) {
	$seccion = 'envios';
}

$url_sec = static fn( string $s ) => add_query_arg(
	[ 'page' => 'panel-kinsta-correos', 'seccion' => $s ],
	admin_url( 'admin.php' )
);

// Cuántas plantillas están tocadas, para decirlo en la pestaña. Si alguien
// cambió la redacción de un aviso que sale a cien clientes, eso conviene verlo
// sin tener que entrar a mirarlas una por una.
$tocadas = 0;
foreach ( array_keys( SWPK_Plantillas::catalogo() ) as $clave ) {
	if ( SWPK_Plantillas::esta_tocada( (string) $clave ) ) {
		++$tocadas;
	}
}
?>
<div class="wrap">
	<?php
	SWPK_Vista::cabecera(
		'Correos',
		'Los avisos que salen solos, a quién le llegan y qué dicen.',
		SWPK_Vista::pastillas( [
			[
				'texto'  => 'Qué sale y a quién',
				'url'    => $url_sec( 'envios' ),
				'activa' => 'envios' === $seccion,
			],
			[
				'texto'  => 'Cómo están redactados',
				'n'      => $tocadas > 0 ? $tocadas : '',
				'url'    => $url_sec( 'plantillas' ),
				'activa' => 'plantillas' === $seccion,
			],
		] )
	);
	?>
	<?php SWPK_Admin::mostrar_mensaje(); ?>

	<?php require SWPK_RUTA . 'includes/admin/vistas/correos-' . $seccion . '.php'; ?>

	<?php
	// El sello de frescura va en TODAS las pantallas, como en la maqueta.
	// Estaba solo en cuatro, y es justo el dato que convierte una pantalla en
	// evidencia: de cuándo es lo que se está mirando. Una pantalla sin él
	// afirma sin fecha.
	SWPK_Frescura::sello();
	?>
</div>
