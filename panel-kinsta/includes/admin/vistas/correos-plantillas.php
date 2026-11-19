<?php
/**
 * Correos · Cómo están redactados: editar el asunto y el texto de cada aviso.
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
 * El jefe pidió esta pantalla para no tener que pedirnos un cambio de redacción
 * cada vez. Son tres y no cinco: tres son los correos que el panel manda hoy.
 */

defined( 'ABSPATH' ) || exit;

$catalogo = SWPK_Plantillas::catalogo();

// Cuál se está editando. Una sola a la vez: tres cajas de texto largas, una
// debajo de otra, es una pantalla en la que no se sabe dónde se está.
$cual = isset( $_GET['plantilla'] ) ? sanitize_key( (string) $_GET['plantilla'] ) : '';

if ( ! isset( $catalogo[ $cual ] ) ) {
	$cual = (string) array_key_first( $catalogo );
}

$ficha  = $catalogo[ $cual ];
$actual = SWPK_Plantillas::obtener( $cual );
$tocada = SWPK_Plantillas::esta_tocada( $cual );

$url_de = static fn( string $c ) => add_query_arg(
	[ 'page' => 'panel-kinsta-correos', 'seccion' => 'plantillas', 'plantilla' => $c ],
	admin_url( 'admin.php' )
);
?>

<p class="swpk-aviso-corto">
	Aquí se cambia cómo está escrito cada aviso, no a quién le llega ni cuándo sale: eso está en
	<a href="<?php echo esc_url( add_query_arg( [ 'page' => 'panel-kinsta-correos', 'seccion' => 'envios' ], admin_url( 'admin.php' ) ) ); ?>">la otra pestaña</a>.
	<?php echo wp_kses_post( SWPK_Vista::ayuda(
		'Se escribe en texto normal, con una línea en blanco entre párrafos; el panel le pone el '
		. 'formato, la cabecera y el pie al enviarlo. Lo que el panel arma con los datos de cada día '
		. '—la lista de sitios con sus pendientes, la tabla de lo que cambió, el recuadro de cuántas '
		. 'veces se actualizó— no se escribe a mano: entra por una marca, y donde pongas esa marca, '
		. 'ahí va el bloque entero.'
	) ); ?>
</p>

<?php
// ── La lista de plantillas, con su botón ────────────────────────────────────
//
// Eran tres enlaces en una línea separados por puntos, y no se veían como
// algo que se pueda abrir: «aquí debería haber un botón que diga ver, editar,
// no sé, entiendes». Ahora es una fila por plantilla, con a quién le llega y
// un botón.
SWPK_Vista::caja_abrir( 'Las plantillas de los avisos', esc_html( SWPK_Vista::plural( count( $catalogo ), 'plantilla', 'plantillas' ) ), '', true );
?>
<table class="widefat striped swpk-tabla">
	<tbody>
	<?php foreach ( $catalogo as $clave => $c ) : ?>
		<tr<?php echo $cual === $clave ? ' style="background:#f0f6fc"' : ''; ?>>
			<td>
				<strong><?php echo esc_html( (string) $c['nombre'] ); ?></strong>
				<?php if ( SWPK_Plantillas::esta_tocada( (string) $clave ) ) : ?>
					<span class="swpk-mudo">(editado)</span>
				<?php endif; ?>
				<br><span class="swpk-gris"><?php echo esc_html( (string) $c['quien'] ); ?></span>
			</td>
			<td style="width:160px;text-align:right">
				<a class="button<?php echo $cual === $clave ? '' : ' button-primary'; ?>" href="<?php echo esc_url( $url_de( (string) $clave ) ); ?>">
					<?php echo $cual === $clave ? 'Se está viendo' : 'Ver y editar'; ?>
				</a>
			</td>
		</tr>
	<?php endforeach; ?>
	</tbody>
</table>
<?php SWPK_Vista::caja_cerrar(); ?>

<?php
// El editor y las marcas, uno al lado del otro. Estaban uno debajo del otro:
// «los campos que podemos ingresar dentro del correo no están lado a lado, la
// parte donde estamos viendo la previsualización y los accesos directos, que
// aparezca a la par, ya que no lo tenemos tan grande».
SWPK_Vista::rejilla_abrir();
SWPK_Vista::caja_abrir( 'Cómo está redactado', '', 'swpk-c8' );
?>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<input type="hidden" name="action" value="swpk_guardar_plantilla">
	<input type="hidden" name="plantilla" value="<?php echo esc_attr( $cual ); ?>">
	<?php wp_nonce_field( 'swpk_guardar_plantilla' ); ?>

	<h2 style="margin-top:22px"><?php echo esc_html( (string) $ficha['nombre'] ); ?></h2>
	<p class="swpk-mudo" style="margin:-6px 0 14px"><?php echo esc_html( (string) $ficha['quien'] ); ?></p>

	<?php if ( ! $tocada ) : ?>
		<p class="swpk-mudo" style="margin:-6px 0 14px">
			Esta plantilla está tal como vino de fábrica. Lo que escribas aquí reemplaza ese texto.
		</p>
	<?php endif; ?>

	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="swpk-asunto">El asunto</label></th>
			<td>
				<input type="text" id="swpk-asunto" name="asunto" class="large-text"
					value="<?php echo esc_attr( $actual['asunto'] ); ?>">
				<p class="description">Lo que se ve en la bandeja de entrada, antes de abrirlo.</p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="swpk-cuerpo">El texto</label></th>
			<td>
				<textarea id="swpk-cuerpo" name="cuerpo" rows="14" class="large-text code"
					style="font-family:inherit;font-size:13.5px;line-height:1.6"><?php
					echo esc_textarea( $actual['cuerpo'] );
				?></textarea>
				<p class="description">
					Una línea en blanco separa un párrafo del siguiente. Una marca de bloque va sola
					en su propio párrafo.
				</p>
			</td>
		</tr>
	</table>

	<p>
		<?php submit_button( 'Guardar la plantilla', 'primary', 'submit', false ); ?>

		<button type="button" class="button" id="swpk-ver-previa" style="margin-left:6px"
			data-url="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
			data-plantilla="<?php echo esc_attr( $cual ); ?>"
			data-nonce="<?php echo esc_attr( wp_create_nonce( 'swpk_vista_previa' ) ); ?>">
			Vista previa
		</button>

		<?php if ( $tocada ) : ?>
			<a class="button" style="margin-left:6px"
				href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=swpk_plantilla_fabrica&plantilla=' . $cual ), 'swpk_plantilla_fabrica_' . $cual ) ); ?>"
				onclick="return confirm('Se pierde lo que hayas escrito en esta plantilla y vuelve el texto de fábrica. ¿Seguro?');">
				Volver a la de fábrica
			</a>
		<?php endif; ?>
	</p>

	<p class="swpk-aviso-corto">
		La vista previa no manda nada.
		<?php echo wp_kses_post( SWPK_Vista::ayuda(
			'Dibuja el correo con el texto que tengas escrito ahora, esté guardado o no, y con datos de '
			. 'ejemplo. Los bloques que arma el panel con los datos del día salen marcados con un '
			. 'recuadro que dice qué va ahí: enseñar una tabla inventada que luego no coincide sería '
			. 'peor que no enseñarla. Para leer el correo de verdad, con los datos de hoy, está '
			. '«Mandar una prueba» en la otra pestaña.'
		) ); ?>
	</p>
</form>

<?php SWPK_Vista::caja_cerrar(); ?>

<?php
// ── Las marcas que se pueden meter ──────────────────────────────────────────
//
// Estaban al final del formulario, dentro de una fila de la tabla, debajo de
// dos cajas de texto largas. O sea: donde no las ve nadie. «Aquí debería
// salir un panel con los widgets que se pueden usar; esto está mal, es al
// alguien que venga y los administre, no lo entiende y no se lo explica.»
//
// Ahora tienen su propio recuadro, con el mismo peso que el editor.
SWPK_Vista::caja_abrir(
	'Lo que se puede meter' . SWPK_Vista::ayuda(
		'Se escribe la marca tal cual, con sus llaves, donde quieras que salga lo que nombra. Una '
		. 'marca mal escrita no rompe nada: sale tal cual en el correo, así que se nota enseguida.'
	),
	esc_html( SWPK_Vista::plural( count( (array) $ficha['marcas'] ), 'marca', 'marcas' ) ),
	'swpk-c4',
	true
);
?>
<table class="widefat striped swpk-tabla swpk-marcas">
	<tbody>
	<?php foreach ( (array) $ficha['marcas'] as $marca => $explica ) : ?>
		<tr>
			<td><code><?php echo esc_html( (string) $marca ); ?></code>
				<span class="swpk-marca-explica"><?php echo esc_html( (string) $explica ); ?></span>
			</td>
		</tr>
	<?php endforeach; ?>
	</tbody>
</table>
<?php SWPK_Vista::caja_cerrar(); ?>
<?php SWPK_Vista::rejilla_cerrar(); ?>

<?php
// La ventana de la vista previa. Vacía hasta que se pulsa el botón: el marco
// se carga entonces, no al abrir la pantalla.
?>
<dialog id="swpk-previa" class="swpk-dialogo">
	<div class="swpk-dialogo-cab">
		<strong>Vista previa — <?php echo esc_html( (string) $ficha['nombre'] ); ?></strong>
		<form method="dialog"><button class="button">Cerrar</button></form>
	</div>
	<iframe name="swpk-previa-marco" title="Vista previa del correo"></iframe>
</dialog>
