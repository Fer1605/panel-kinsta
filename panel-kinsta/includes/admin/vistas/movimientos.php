<?php
/**
 * Movimientos: qué cambió en los sitios, y cuándo.
 *
 * Era una pestaña dentro de Bitácora. En la maqueta que aprobó el jefe es una
 * entrada del menú, entre Caídas y Correos, y tiene razón en serlo: Bitácora
 * guarda lo que se AVISÓ y esto guarda lo que PASÓ. La segunda se mira todos
 * los días; la primera, cuando alguien reclama.
 *
 * El cuerpo es el mismo archivo que usaba la pestaña, sin tocar: lo único que
 * cambia es que ahora tiene su propia pantalla y su propia dirección.
 */
defined( 'ABSPATH' ) || exit;

$url_sec = static fn( string $s ) => add_query_arg(
	[ 'page' => 'movimientos' === $s ? 'panel-kinsta-movimientos' : 'panel-kinsta-bitacora' ],
	admin_url( 'admin.php' )
);
?>
<div class="wrap">
	<?php
	SWPK_Vista::cabecera(
		'Movimientos',
		'Qué cambió en los sitios, y cuándo.',
		SWPK_Vista::pastillas( [
			[ 'texto' => 'Movimientos',    'url' => $url_sec( 'movimientos' ),    'activa' => true ],
			[ 'texto' => 'Notificaciones', 'url' => $url_sec( 'notificaciones' ), 'activa' => false ],
		] )
	);
	?>
	<?php SWPK_Admin::mostrar_mensaje(); ?>
	<?php SWPK_Admin::tira_de_perfil(); ?>

	<?php require SWPK_RUTA . 'includes/admin/vistas/bitacora-movimientos.php'; ?>

	<p class="swpk-atribucion"><?php echo wp_kses_post( SWPK_Wordfence::atribucion() ); ?></p>
</div>
