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

<p style="max-width:860px;color:#3c434a">
	Aquí se cambia <strong>cómo está escrito</strong> cada aviso, sin tocar a quién le llega ni
	cuándo sale. Eso está en <a href="<?php echo esc_url( add_query_arg( [ 'page' => 'panel-kinsta-correos', 'seccion' => 'envios' ], admin_url( 'admin.php' ) ) ); ?>">la otra pestaña</a>.
</p>

<div class="swpk-comoasi">
	<p>
		<strong>Lo que el panel arma, el panel lo sigue armando.</strong> La lista de sitios con sus
		pendientes, la tabla de lo que cambió, el recuadro de cuántas veces se actualizó: eso sale de
		los datos de cada día, y por eso no se escribe a mano. Entra por una <strong>marca</strong> —
		donde pongas <code>{{sitios}}</code>, ahí va el bloque.
	</p>
	<p>
		Se escribe en texto normal, con <strong>una línea en blanco entre párrafos</strong>. El panel
		le pone el formato, la cabecera y el pie al enviarlo.
	</p>
</div>

<?php SWPK_Vista::caja_abrir( 'Las plantillas de los avisos', esc_html( SWPK_Vista::plural( count( $catalogo ), 'plantilla', 'plantillas' ) ) ); ?>

<p class="swpk-filtros-estado">
	<?php foreach ( $catalogo as $clave => $c ) : ?>
		<?php if ( $clave !== array_key_first( $catalogo ) ) : ?> · <?php endif; ?>
		<a href="<?php echo esc_url( $url_de( (string) $clave ) ); ?>"
			class="<?php echo $cual === $clave ? 'current' : ''; ?>">
			<?php echo esc_html( (string) $c['nombre'] ); ?>
			<?php if ( SWPK_Plantillas::esta_tocada( (string) $clave ) ) : ?>
				<span class="swpk-mudo">(editado)</span>
			<?php endif; ?>
		</a>
	<?php endforeach; ?>
</p>

<?php SWPK_Vista::caja_cerrar(); ?>

<?php SWPK_Vista::caja_abrir( 'Cómo está redactado' ); ?>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="max-width:980px">
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
		<tr>
			<th scope="row">Lo que se puede meter</th>
			<td>
				<table class="widefat striped" style="max-width:720px">
					<tbody>
					<?php foreach ( (array) $ficha['marcas'] as $marca => $explica ) : ?>
						<tr>
							<td style="width:170px"><code><?php echo esc_html( (string) $marca ); ?></code></td>
							<td><?php echo esc_html( (string) $explica ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<p class="description">
					Una marca mal escrita no rompe nada: sale tal cual en el correo, así que se nota
					enseguida al generarlo.
				</p>
			</td>
		</tr>
	</table>

	<p>
		<?php submit_button( 'Guardar la plantilla', 'primary', 'submit', false ); ?>

		<a class="button" style="margin-left:6px"
			href="<?php echo esc_url( add_query_arg( [ 'page' => 'panel-kinsta-correos', 'seccion' => 'envios' ], admin_url( 'admin.php' ) ) ); ?>">
			Ver cómo queda
		</a>

		<?php if ( $tocada ) : ?>
			<a class="button" style="margin-left:6px"
				href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=swpk_plantilla_fabrica&plantilla=' . $cual ), 'swpk_plantilla_fabrica_' . $cual ) ); ?>"
				onclick="return confirm('Se pierde lo que hayas escrito en esta plantilla y vuelve el texto de fábrica. ¿Seguro?');">
				Volver a la de fábrica
			</a>
		<?php endif; ?>
	</p>

	<p class="swpk-mudo" style="max-width:860px">
		<strong>«Ver cómo queda» lo genera de verdad</strong>, con los datos de hoy, y lo deja en la
		bitácora para abrirlo y leerlo completo. Mientras los correos estén en modo de prueba no sale
		a nadie. Se hace desde la otra pestaña, con el botón «Generarlo ahora» del aviso que
		corresponda — así lo que se lee es el correo real y no una simulación que podría no coincidir.
	</p>
</form>

<?php SWPK_Vista::caja_cerrar(); ?>
