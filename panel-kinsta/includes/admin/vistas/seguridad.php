<?php
/**
 * Seguridad: el tablero de cumplimiento, la lista de normas y las órdenes.
 *
 * Tres pestañas y no tres entradas en el menú, porque son tres caras de lo
 * mismo: la regla, quién la cumple, y qué se hizo al respecto.
 *
 * Abre en el tablero a propósito. Es la pestaña que contesta «¿cómo estamos?»,
 * que es la pregunta con la que uno entra. La lista de normas se abre cuando
 * hay que cambiar una regla, que es cada varios meses.
 */

defined( 'ABSPATH' ) || exit;

$seccion = isset( $_GET['seccion'] ) ? sanitize_key( (string) $_GET['seccion'] ) : 'cumplimiento';

if ( ! in_array( $seccion, [ 'cumplimiento', 'normas', 'ordenes' ], true ) ) {
	$seccion = 'cumplimiento';
}

$url_sec = static fn( string $s ) => add_query_arg(
	[ 'page' => 'panel-kinsta-seguridad', 'seccion' => $s ],
	admin_url( 'admin.php' )
);

$conteos_ordenes = SWPK_Ordenes::conteos();
$en_camino       = $conteos_ordenes[ SWPK_Ordenes::PENDIENTE ] + $conteos_ordenes[ SWPK_Ordenes::ENTREGADA ];
?>
<div class="wrap">
	<h1>Seguridad</h1>
	<?php
	SWPK_Admin::mostrar_mensaje();
	SWPK_Admin::aviso_demo();
	SWPK_Admin::tira_de_perfil();
	?>

	<h2 class="nav-tab-wrapper">
		<a class="nav-tab <?php echo 'cumplimiento' === $seccion ? 'nav-tab-active' : ''; ?>"
			href="<?php echo esc_url( $url_sec( 'cumplimiento' ) ); ?>">Cómo estamos</a>
		<a class="nav-tab <?php echo 'normas' === $seccion ? 'nav-tab-active' : ''; ?>"
			href="<?php echo esc_url( $url_sec( 'normas' ) ); ?>">Las normas</a>
		<a class="nav-tab <?php echo 'ordenes' === $seccion ? 'nav-tab-active' : ''; ?>"
			href="<?php echo esc_url( $url_sec( 'ordenes' ) ); ?>">Qué se pidió<?php
			if ( $en_camino > 0 ) {
				printf( ' <span class="swpk-pelota">%d</span>', (int) $en_camino );
			}
		?></a>
	</h2>

	<?php require SWPK_RUTA . 'includes/admin/vistas/seguridad-' . $seccion . '.php'; ?>
</div>
