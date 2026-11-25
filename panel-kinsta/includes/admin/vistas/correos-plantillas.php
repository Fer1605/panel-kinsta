<?php
/**
 * Correos · Cómo están redactados: la lista de avisos y su botón.
 *
 * ─── Qué se puede cambiar aquí, y qué no ────────────────────────────────────
 *
 * Se cambia la prosa: el asunto y lo que acompaña. No se cambia el bloque de
 * datos —la lista de sitios con sus pendientes, la tabla de lo que cambió—
 * porque eso lo arma el panel con la información de cada día.
 *
 * Ese bloque entra por una marca. Donde se escriba `{{sitios}}`, ahí va. Se
 * puede mover, se le puede poner texto antes o después, y se puede quitar si
 * se quiere un aviso sin él. Lo que no se puede es escribirlo a mano.
 *
 * ─── Por qué esta pantalla ya no trae el editor dentro ──────────────────────
 *
 * Porque el editor se abre encima, en una ventana, y lo hace desde aquí y
 * desde la pestaña de al lado sin cambiar de pantalla:
 *
 *   «ver y editar el texto… ¿por qué lo levantas así? Levantalo en un modal.
 *    NO ME MANDES A PANTALLITAS DE PANTALLITAS.»                       [17:11]
 *
 * Antes esta pestaña hacía las dos cosas a la vez: la lista arriba y, debajo,
 * el editor de la que estuviera seleccionada. Elegir otra recargaba la
 * pantalla entera. Ahora la lista es una lista, y el editor —con sus marcas y
 * su vista previa— vive en `correos-editor.php`, que es el mismo para las dos
 * pestañas.
 */

defined( 'ABSPATH' ) || exit;

$catalogo = SWPK_Plantillas::catalogo();

$tocadas = 0;
foreach ( array_keys( $catalogo ) as $clave ) {
	if ( SWPK_Plantillas::esta_tocada( (string) $clave ) ) {
		++$tocadas;
	}
}
?>

<p class="swpk-aviso-corto">
	Aquí se cambia cómo está escrito cada aviso, no a quién le llega ni cuándo sale: eso está en
	<a href="<?php echo esc_url( add_query_arg( [ 'page' => 'panel-kinsta-correos', 'seccion' => 'envios' ], admin_url( 'admin.php' ) ) ); ?>">la otra pestaña</a>.
	<?php echo wp_kses_post( SWPK_Vista::ayuda(
		'Se escribe en texto normal, con una línea en blanco entre párrafos; el panel le pone el '
		. 'formato, la cabecera, el logo y el pie al enviarlo. Lo que el panel arma con los datos de '
		. 'cada día —la lista de sitios con sus pendientes, la tabla de lo que cambió, el recuadro de '
		. 'cuántas veces se actualizó— no se escribe a mano: entra por una marca, y donde pongas esa '
		. 'marca, ahí va el bloque entero.'
	) ); ?>
</p>

<?php
SWPK_Vista::caja_abrir(
	'Las plantillas de los avisos',
	esc_html(
		SWPK_Vista::plural( count( $catalogo ), 'plantilla', 'plantillas' )
		. ( $tocadas ? sprintf( ', %d editada%s', $tocadas, 1 === $tocadas ? '' : 's' ) : '' )
	),
	'',
	true
);
?>
<table class="widefat striped swpk-tabla">
	<thead>
		<tr>
			<th>El aviso</th>
			<th style="width:200px">Cómo está el asunto</th>
			<th style="width:180px;text-align:right"></th>
		</tr>
	</thead>
	<tbody>
	<?php foreach ( $catalogo as $clave => $c ) : ?>
		<?php $actual = SWPK_Plantillas::obtener( (string) $clave ); ?>
		<tr>
			<td>
				<strong><?php echo esc_html( (string) $c['nombre'] ); ?></strong>
				<?php if ( SWPK_Plantillas::esta_tocada( (string) $clave ) ) : ?>
					<span class="swpk-mudo">(editado)</span>
				<?php endif; ?>
				<br><span class="swpk-gris"><?php echo esc_html( (string) $c['quien'] ); ?></span>
			</td>
			<td><span class="swpk-gris" style="font-size:12px"><?php echo esc_html( SWPK_Vista::recortar( $actual['asunto'], 70 ) ); ?></span></td>
			<td style="text-align:right">
				<button type="button" class="button button-primary" data-swpk-editar="<?php echo esc_attr( (string) $clave ); ?>">
					Ver y editar
				</button>
			</td>
		</tr>
	<?php endforeach; ?>
	</tbody>
</table>
<?php SWPK_Vista::caja_cerrar(); ?>

<p class="swpk-nota">
	La vista previa no manda nada.
	<?php echo wp_kses_post( SWPK_Vista::ayuda(
		'Dibuja el correo con el texto que tengas escrito ahora, esté guardado o no, y con datos de '
		. 'ejemplo. Los bloques que arma el panel con los datos del día salen marcados con un '
		. 'recuadro que dice qué va ahí: enseñar una tabla inventada que luego no coincide sería '
		. 'peor que no enseñarla. Para leer el correo de verdad, con los datos de hoy, está '
		. '«Mandar una prueba» en la otra pestaña.'
	) ); ?>
</p>

<?php require SWPK_RUTA . 'includes/admin/vistas/correos-editor.php'; ?>
