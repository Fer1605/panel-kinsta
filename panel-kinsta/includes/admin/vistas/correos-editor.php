<?php
/**
 * El editor de una plantilla de correo, en una ventana.
 *
 * ─── Por qué esto existe ────────────────────────────────────────────────────
 *
 * Porque el jefe lo pidió con todas sus letras, y dos veces:
 *
 *   «ver y editar el texto… ¿por qué lo levantas así? LEVANTALO EN UN MODAL.
 *    NO ME MANDES A PANTALLITAS DE PANTALLITAS.»                       [17:11]
 *
 * Antes, «Ver y editar el texto» era un enlace: dejabas la pantalla donde
 * estabas, aterrizabas en otra pestaña con el editor, cambiabas una frase, y
 * volvías a buscar dónde estabas. Para cambiar una palabra del asunto eso son
 * tres pantallas y dos viajes.
 *
 * Ahora el editor se abre encima. No se cambia de pantalla, y al cerrar
 * seguís donde estabas.
 *
 * ─── Por qué van todas dibujadas de una vez y no por AJAX ───────────────────
 *
 * Son ocho plantillas: ocho asuntos y ocho textos de unas pocas líneas. Todo
 * junto no llega a 20 KB, y a cambio no hace falta un endpoint nuevo, ni un
 * estado de «cargando», ni pensar qué pasa si la petición falla con el
 * trabajo a medio escribir. La ventana abre al instante porque ya está ahí.
 *
 * Se incluye desde las dos pestañas de Correos. La variable `$editor_puesto`
 * evita dibujarlas dos veces si algún día se incluye de nuevo en la misma
 * carga: dos <dialog> con el mismo id serían dos formularios con los mismos
 * campos, y el navegador abriría siempre el primero.
 */

defined( 'ABSPATH' ) || exit;

if ( ! empty( $GLOBALS['swpk_editor_puesto'] ) ) {
	return;
}

$GLOBALS['swpk_editor_puesto'] = true;

$puede_tocar = SWPK_Perfiles::puede( SWPK_Perfiles::ADMINISTRAR );

foreach ( SWPK_Plantillas::catalogo() as $clave => $ficha ) :
	$clave   = (string) $clave;
	$guardada = SWPK_Plantillas::obtener( $clave );
	$tocada  = SWPK_Plantillas::esta_tocada( $clave );
	?>
	<dialog id="swpk-editor-<?php echo esc_attr( $clave ); ?>" class="swpk-dialogo swpk-editor" data-plantilla="<?php echo esc_attr( $clave ); ?>">
		<div class="swpk-dialogo-cab">
			<strong><?php echo esc_html( (string) $ficha['nombre'] ); ?></strong>
			<form method="dialog"><button class="button">Cerrar</button></form>
		</div>

		<?php
		// «Cuándo sale» y «Para», arriba del todo, como en su panel: para no
		// tener que acordarse de a quién le va esto mientras se escribe.
		?>
		<div class="swpk-editor-ficha">
			<div>
				<span class="swpk-editor-ficha-rot">Cuándo sale y para quién</span>
				<span><?php echo esc_html( (string) $ficha['quien'] ); ?></span>
			</div>
			<div>
				<span class="swpk-editor-ficha-rot">Ahora mismo</span>
				<span><?php
					echo SWPK_Correos::encendido( $clave )
						? '<strong>Encendido</strong>'
						: 'Apagado';
				?></span>
			</div>
		</div>

		<div class="swpk-editor-cuerpo swpk-editor-dos">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="swpk-editor-form">
				<input type="hidden" name="action" value="swpk_guardar_plantilla">
				<input type="hidden" name="plantilla" value="<?php echo esc_attr( $clave ); ?>">
				<?php
				// De vuelta a la pestaña desde la que se abrió la ventana, y
				// con la ventana abierta otra vez: guardar no es salir.
				?>
				<input type="hidden" name="volver_a" value="<?php echo esc_attr( isset( $seccion ) ? (string) $seccion : 'envios' ); ?>">
				<?php wp_nonce_field( 'swpk_guardar_plantilla' ); ?>

				<?php
				// El «a quién le llega» ya está en la ficha de arriba. Dejarlo
				// también aquí lo decía dos veces en la misma pantalla.
				?>
				<?php if ( ! $tocada ) : ?>
					<p class="swpk-mudo swpk-editor-quien">
						Está tal como vino de fábrica. Lo que escribas aquí reemplaza ese texto.
					</p>
				<?php endif; ?>

				<p class="swpk-editor-campo">
					<label for="swpk-asunto-<?php echo esc_attr( $clave ); ?>"><strong>El asunto</strong></label>
					<input type="text" class="large-text" id="swpk-asunto-<?php echo esc_attr( $clave ); ?>"
						name="asunto" data-papel="asunto"
						value="<?php echo esc_attr( $guardada['asunto'] ); ?>"
						<?php disabled( ! $puede_tocar ); ?>>
					<span class="description">Lo que se ve en la bandeja de entrada, antes de abrirlo.</span>
				</p>

				<p class="swpk-editor-campo">
					<label for="swpk-cuerpo-<?php echo esc_attr( $clave ); ?>"><strong>El texto</strong></label>
					<textarea id="swpk-cuerpo-<?php echo esc_attr( $clave ); ?>" name="cuerpo" rows="12"
						class="large-text code" data-papel="cuerpo"
						<?php disabled( ! $puede_tocar ); ?>><?php echo esc_textarea( $guardada['cuerpo'] ); ?></textarea>
					<span class="description">
						Una línea en blanco separa un párrafo del siguiente. Una marca de bloque va sola
						en su propio párrafo.
					</span>
				</p>

				<?php if ( $puede_tocar ) : ?>
					<?php
					// Una sola acción azul por bloque, que es la regla 6 de su
					// panel: «"Limpiar" parece un botón más». Guardar es azul;
					// lo demás va en texto, que es lo que son.
					?>
					<p class="swpk-editor-botones">
						<button type="submit" class="button button-primary">Guardar</button>

						<button type="button" class="swpk-enlace-pie" data-swpk-previa
							data-url="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
							data-plantilla="<?php echo esc_attr( $clave ); ?>"
							data-nonce="<?php echo esc_attr( wp_create_nonce( 'swpk_vista_previa' ) ); ?>">
							Verlo armado de verdad
						</button>

						<?php if ( $tocada ) : ?>
							<a class="swpk-enlace-pie"
								href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=swpk_plantilla_fabrica&plantilla=' . $clave ), 'swpk_plantilla_fabrica_' . $clave ) ); ?>"
								onclick="return confirm('Se pierde lo que hayas escrito en esta plantilla y vuelve el texto de fábrica. ¿Seguro?');">
								Restaurar el original
							</a>
						<?php endif; ?>
					</p>
				<?php endif; ?>
			</form>

			<?php
			// ── La vista previa «del otro lado» ─────────────────────────────
			//
			// «esa vista previa debería estar del otro lado» (09:22). Estaba
			// detrás de un botón que abría OTRA ventana encima: servía para
			// comprobar, no para escribir. Ahora va al lado y se actualiza
			// mientras se teclea, con datos de ejemplo.
			//
			// Los ejemplos viajan en un data- y la sustitución la hace el
			// navegador: pedirle al servidor el correo armado en cada tecla
			// sería una petición por pulsación.
			//
			// Y sí, el asunto también se sustituye. Era una pregunta suya
			// concreta —«hay que ver si el parámetro también funciona en el
			// subject»— y la respuesta es sí: asunto() pasa por el mismo
			// reemplazo que el cuerpo. Aquí se ve.
			?>
			<div class="swpk-editor-previa" data-ejemplos="<?php
				echo esc_attr( (string) wp_json_encode( SWPK_Plantillas::ejemplos() ) );
			?>">
				<p class="swpk-editor-marcas-tit"><strong>Vista previa</strong> <span class="swpk-mudo">con datos de ejemplo</span></p>
				<div class="swpk-previa-sobre">
					<p class="swpk-previa-rot">Asunto</p>
					<p class="swpk-previa-asunto" data-papel="previa-asunto"></p>
					<p class="swpk-previa-rot">Mensaje</p>
					<div class="swpk-previa-cuerpo" data-papel="previa-cuerpo"></div>
				</div>
			</div>

			<div class="swpk-editor-marcas">
				<p class="swpk-editor-marcas-tit">
					<strong>Lo que se puede meter</strong>
					<?php echo wp_kses_post( SWPK_Vista::ayuda(
						'Se escribe la marca tal cual, con sus llaves, donde quieras que salga lo que '
						. 'nombra. Una marca mal escrita no rompe nada: sale tal cual en el correo, así '
						. 'que se nota enseguida. Pulsá una para copiarla.'
					) ); ?>
				</p>
				<table class="widefat striped swpk-tabla swpk-marcas">
					<tbody>
					<?php foreach ( (array) $ficha['marcas'] as $marca => $explica ) : ?>
						<tr>
							<td>
								<button type="button" class="swpk-marca-copiar" data-marca="<?php echo esc_attr( (string) $marca ); ?>">
									<code><?php echo esc_html( (string) $marca ); ?></code>
								</button>
								<span class="swpk-marca-explica"><?php echo esc_html( (string) $explica ); ?></span>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
	</dialog>
<?php endforeach; ?>

<?php
// La ventana de la vista previa, una sola y compartida por las ocho.
//
// Se abre ENCIMA de la del editor, que sigue abierta debajo: al cerrarla
// volvés al texto que estabas escribiendo, sin haberlo perdido.
?>
<dialog id="swpk-previa" class="swpk-dialogo">
	<div class="swpk-dialogo-cab">
		<strong>Vista previa — <span id="swpk-previa-nombre">el correo</span></strong>
		<form method="dialog"><button class="button">Cerrar</button></form>
	</div>
	<iframe name="swpk-previa-marco" title="Vista previa del correo"></iframe>
</dialog>
