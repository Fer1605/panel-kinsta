<?php
/**
 * Correos: tres pestañas.
 *
 *   Qué sale y a quién     el interruptor, los tres avisos y cuándo salen
 *   Cómo están redactados  las plantillas
 *   Historial de envíos    cada correo que salió, con su fecha y su destinatario
 *
 * Tres pestañas y no tres entradas en el menú, porque son la misma cosa vista
 * de tres maneras: qué se manda, qué dice lo que se manda, y qué se mandó. La
 * tercera estaba suelta en el menú como «Bitácora» hasta que el jefe la puso
 * en su sitio: «la bitácora no son movimientos, es la bitácora de los correos
 * que se enviaron; debería estar ahí dentro».
 *
 * Se entra por la primera, que es la que se mira todos los días.
 */

defined( 'ABSPATH' ) || exit;

$seccion = isset( $_GET['seccion'] ) ? sanitize_key( (string) $_GET['seccion'] ) : 'envios';

if ( ! in_array( $seccion, [ 'envios', 'plantillas', 'bitacora' ], true ) ) {
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
				'texto'  => 'Los avisos',
				'url'    => $url_sec( 'envios' ),
				'activa' => 'envios' === $seccion,
			],
			[
				'texto'  => 'Cómo están redactados',
				'n'      => $tocadas > 0 ? $tocadas : '',
				'url'    => $url_sec( 'plantillas' ),
				'activa' => 'plantillas' === $seccion,
			],
			[
				'texto'  => 'Historial de envíos',
				'url'    => $url_sec( 'bitacora' ),
				'activa' => 'bitacora' === $seccion,
			],
		] )
	);
	?>
	<?php SWPK_Admin::mostrar_mensaje(); ?>

	<?php require SWPK_RUTA . 'includes/admin/vistas/correos-' . $seccion . '.php'; ?>

</div>
