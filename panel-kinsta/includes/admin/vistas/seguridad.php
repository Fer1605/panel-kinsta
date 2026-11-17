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
	// El subtítulo de la maqueta: lo primero que hay que saber de esta pantalla
	// es de cuántos sitios NO puede decir nada, porque casi ninguna de estas
	// normas se ve desde fuera.
	$sin_agente_vis = count( array_filter(
		SWPK_Perfiles::sitios_visibles(),
		static fn( $s ) => empty( $s->cobertura_agente )
	) );

	SWPK_Vista::cabecera(
		'Seguridad',
		$sin_agente_vis > 0
			? esc_html( sprintf(
				'De %d sitios no se sabe nada: les falta el plugin instalado.',
				$sin_agente_vis
			) )
			: 'Las normas que cada sitio tiene que cumplir, y qué le falta a cada uno.',
		SWPK_Vista::pastillas( [
			[
				'texto'  => 'Qué le falta a cada sitio',
				'url'    => $url_sec( 'cumplimiento' ),
				'activa' => 'cumplimiento' === $seccion,
			],
			[
				'texto'  => 'Los arreglos que pedimos',
				'n'      => $en_camino > 0 ? $en_camino : '',
				'url'    => $url_sec( 'ordenes' ),
				'activa' => 'ordenes' === $seccion,
			],
			[
				'texto'  => 'Qué revisamos',
				'url'    => $url_sec( 'normas' ),
				'activa' => 'normas' === $seccion,
			],
		] )
	);
	?>
	<?php
	SWPK_Admin::mostrar_mensaje();
	SWPK_Admin::aviso_demo();
	SWPK_Admin::tira_de_perfil();
	?>

	<?php require SWPK_RUTA . 'includes/admin/vistas/seguridad-' . $seccion . '.php'; ?>
</div>
