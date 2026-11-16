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
	<h1>Correos</h1>
	<?php SWPK_Admin::mostrar_mensaje(); ?>

	<h2 class="nav-tab-wrapper">
		<a class="nav-tab <?php echo 'envios' === $seccion ? 'nav-tab-active' : ''; ?>"
			href="<?php echo esc_url( $url_sec( 'envios' ) ); ?>">Qué sale y a quién</a>
		<a class="nav-tab <?php echo 'plantillas' === $seccion ? 'nav-tab-active' : ''; ?>"
			href="<?php echo esc_url( $url_sec( 'plantillas' ) ); ?>">Cómo están redactados<?php
			if ( $tocadas > 0 ) {
				printf( ' <span class="swpk-pelota">%d</span>', (int) $tocadas );
			}
		?></a>
	</h2>

	<?php require SWPK_RUTA . 'includes/admin/vistas/correos-' . $seccion . '.php'; ?>
</div>
