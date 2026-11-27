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
		// El plural en condiciones: con un solo sitio decía «De 1 sitios no se
		// sabe nada», y una falta de ortografía en el subtítulo de una
		// pantalla hace dudar de los números que hay debajo.
		$sin_agente_vis > 0
			? esc_html( sprintf(
				'De %s no se sabe nada: %s el plugin instalado.',
				SWPK_Vista::plural( $sin_agente_vis, 'sitio', 'sitios' ),
				1 === $sin_agente_vis ? 'le falta' : 'les falta'
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
	?>

	<?php
	// Él preguntó, viendo esta pantalla, qué la diferencia de Vulnerabilidades:
	// «aquí veo lo vulnerable y aquí veo lo seguro — no es lo mismo». Tenía
	// razón: son dos cosas distintas y ninguna de las dos pantallas lo decía.
	// Una línea, y el detalle en la burbuja, para quien no tenga el modelo
	// mental en la cabeza.
	?>
	<p class="swpk-comoasi-linea">
		Aquí están <strong>las normas</strong>: lo que todo sitio debería cumplir, tenga lo que
		tenga instalado. Las fallas conocidas de sus plugins y temas están en
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=panel-kinsta-vulnerabilidades' ) ); ?>">Vulnerabilidades</a>.
		<?php echo wp_kses_post( SWPK_Vista::ayuda(
			'No son lo mismo y no se arreglan igual. Una norma la incumple el sitio por cómo está '
			. 'configurado —no exige HTTPS, publica sus nombres de usuario— y se arregla cambiando '
			. 'esa configuración. Una vulnerabilidad la trae un plugin o un tema en una versión con '
			. 'una falla ya descubierta, y se arregla actualizándolo. Un sitio puede cumplir las 73 '
			. 'normas y tener diez plugins con falla, y al revés.'
		) ); ?>
	</p>

	<?php require SWPK_RUTA . 'includes/admin/vistas/seguridad-' . $seccion . '.php'; ?>

</div>
