<?php
/**
 * Seguridad: el tablero de cumplimiento, la lista de normas y las órdenes.
 *
 * Tres pestañas y no tres entradas en el menú, porque son tres caras de lo
 * mismo: la regla, quién la cumple, y qué se hizo al respecto.
 *
 * Los nombres de las pestañas son preguntas, no categorías. «Cómo estamos»,
 * «Las normas» y «Qué se pidió» describían bien el contenido y no decían nada:
 * había que entrar a cada una para saber qué era. Ahora el nombre contesta de
 * antemano qué se va a encontrar.
 *
 * El orden sigue el trabajo, no la lógica del programa: primero qué falta,
 * después qué se pidió arreglar, y al final la lista de reglas. Las reglas
 * estaban en medio y rompían el hilo — la pestaña de arreglos decía «cuando en
 * la pestaña anterior le das a Arreglarlo» y la anterior era la de reglas,
 * donde no hay nada que arreglar. La lista se abre cuando hay que cambiar una
 * norma, que es cada varios meses; no es por donde se entra.
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
	<?php
	SWPK_Vista::cabecera(
		'Seguridad',
		'Las normas que cada sitio tiene que cumplir, y qué le falta a cada uno.'
	);
	?>
	<?php
	SWPK_Admin::mostrar_mensaje();
	SWPK_Admin::aviso_demo();
	SWPK_Admin::tira_de_perfil();
	?>

	<h2 class="nav-tab-wrapper">
		<a class="nav-tab <?php echo 'cumplimiento' === $seccion ? 'nav-tab-active' : ''; ?>"
			href="<?php echo esc_url( $url_sec( 'cumplimiento' ) ); ?>">Qué le falta a cada sitio</a>
		<a class="nav-tab <?php echo 'ordenes' === $seccion ? 'nav-tab-active' : ''; ?>"
			href="<?php echo esc_url( $url_sec( 'ordenes' ) ); ?>">Los arreglos que pedimos<?php
			if ( $en_camino > 0 ) {
				printf( ' <span class="swpk-pelota">%d</span>', (int) $en_camino );
			}
		?></a>
		<a class="nav-tab <?php echo 'normas' === $seccion ? 'nav-tab-active' : ''; ?>"
			href="<?php echo esc_url( $url_sec( 'normas' ) ); ?>">Qué revisamos</a>
	</h2>

	<?php require SWPK_RUTA . 'includes/admin/vistas/seguridad-' . $seccion . '.php'; ?>
</div>
