<?php
/**
 * La lista de normas: agregar, quitar y apagar.
 *
 * Lo pidió así: «ya podamos agregar más, podamos quitar». Entonces esto no es
 * una pantalla de solo lectura con la lista que trae el código: es la lista de
 * verdad, y lo que se decide aquí es lo que el panel mide.
 *
 * Dos cosas que la pantalla dice en voz alta, porque si no se descubren tarde:
 *
 *  1. Una norma nueva no se comprueba sola. Nadie puede programar un
 *     comprobador desde un formulario. Una norma que escribe una persona nace
 *     declarativa: alguien la marca sitio por sitio y queda con su nombre y su
 *     fecha. Es una lista de verificación con rastro, y sirve; pero no es lo
 *     mismo que una medición, y la pantalla no finge que lo sea.
 *
 *  2. Las diez de fábrica se apagan, no se borran. Si se borraran, la
 *     siguiente actualización del plugin las devolvería y parecería que el
 *     panel no obedece.
 */

defined( 'ABSPATH' ) || exit;

$normas = SWPK_Normas::todas();
$grupos = SWPK_Etiquetas::nombres();

$editar = isset( $_GET['editar'] ) ? sanitize_key( (string) $_GET['editar'] ) : '';
$norma  = '' !== $editar ? SWPK_Normas::una( $editar ) : null;
?>

<p class="swpk-intro">
	<strong>Esta es la lista de lo que revisamos en cada sitio.</strong> Es la que decide qué sale en la
	pestaña anterior: cada norma encendida se comprueba en todos los sitios que tengan el plugin instalado.
	Se le pueden agregar normas y quitar, sin tocar el programa.
</p>
<p class="swpk-mudo" style="max-width:880px;margin:-6px 0 14px">
	La <strong>gravedad</strong> solo decide el orden en que salen los problemas: las altas primero.
	<strong>Apagar</strong> una norma hace que deje de revisarse y desaparezca de la pestaña anterior;
	encenderla la vuelve a meter, pero no al instante — se mide con el siguiente reporte de cada sitio.
</p>

<?php SWPK_Vista::caja_abrir( 'Qué revisamos en cada sitio', '', '', true ); ?>
<table class="widefat striped" style="margin-top:10px">
	<thead>
		<tr>
			<th style="width:34%">Norma</th>
			<th style="width:90px">Gravedad</th>
			<th style="width:170px">¿Quién la revisa?</th>
			<th>Aplica a</th>
			<th style="width:150px"></th>
		</tr>
	</thead>
	<tbody>
	<?php foreach ( $normas as $n ) : ?>
		<?php
		$apagada     = (int) $n->activa !== 1;
		$declarativa = 'declarativa' === (string) $n->comprobador;
		$aplica      = trim( (string) $n->aplica_a );
		?>
		<tr<?php echo $apagada ? ' style="opacity:.5"' : ''; ?>>
			<td>
				<strong><?php echo esc_html( (string) $n->nombre ); ?></strong>
				<?php if ( (int) $n->de_fabrica === 1 ) : ?>
					<span class="swpk-mudo" title="Viene con el panel. Se puede apagar, no borrar.">de fábrica</span>
				<?php endif; ?>
				<?php if ( $apagada ) : ?>
					<span class="swpk-etq swpk-sev-baja">Apagada</span>
				<?php endif; ?>
				<?php if ( (string) $n->porque !== '' ) : ?>
					<p class="swpk-mudo" style="margin:4px 0 0"><?php echo esc_html( (string) $n->porque ); ?></p>
				<?php endif; ?>
			</td>
			<td>
				<span class="swpk-etq swpk-sev-<?php echo esc_attr( (string) $n->severidad ); ?>">
					<?php echo esc_html( ucfirst( (string) $n->severidad ) ); ?>
				</span>
			</td>
			<td>
				<?php if ( $declarativa ) : ?>
					<strong>Una persona</strong>
					<br><span class="swpk-mudo">entrando a la ficha del sitio y marcándola</span>
				<?php else : ?>
					<strong>El plugin, solo</strong>
					<br><span class="swpk-mudo">la mira en el sitio y la reporta cada día</span>
				<?php endif; ?>
				<?php if ( (int) $n->arreglable === 1 ) : ?>
					<br><span class="swpk-mudo">y se puede arreglar</span>
				<?php endif; ?>
			</td>
			<td>
				<?php
				if ( '' === $aplica ) {
					echo 'Todos los sitios';
				} else {
					$nombres = [];
					foreach ( explode( ',', $aplica ) as $t ) {
						$nombres[] = $grupos[ trim( $t ) ] ?? trim( $t );
					}
					echo esc_html( implode( ', ', $nombres ) );
				}
				?>
			</td>
			<td style="text-align:right">
				<a class="button button-small" href="<?php
					echo esc_url(
						add_query_arg(
							[ 'page' => 'panel-kinsta-seguridad', 'seccion' => 'normas', 'editar' => (string) $n->clave ],
							admin_url( 'admin.php' )
						)
					);
				?>">Cambiar</a>

				<a class="button button-small" href="<?php echo esc_url( SWPK_Admin::url_encender_norma( (string) $n->clave, $apagada ) ); ?>">
					<?php echo $apagada ? 'Encender' : 'Apagar'; ?>
				</a>

				<?php if ( (int) $n->de_fabrica !== 1 ) : ?>
					<a class="button button-small" style="color:#b32d2e"
						href="<?php echo esc_url( SWPK_Admin::url_borrar_norma( (string) $n->clave ) ); ?>"
						onclick="return confirm('Se borra la norma «<?php echo esc_js( (string) $n->nombre ); ?>» y todo lo que se midió de ella. Esto no se puede deshacer. ¿Seguimos?')">Borrar</a>
				<?php endif; ?>
			</td>
		</tr>
	<?php endforeach; ?>
	</tbody>
</table>
<?php SWPK_Vista::caja_cerrar(); ?>

<?php SWPK_Vista::caja_abrir( $norma ? 'Cambiar una norma' : 'Agregar una norma' ); ?>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="max-width:720px">
	<input type="hidden" name="action" value="swpk_guardar_norma">
	<?php wp_nonce_field( 'swpk_guardar_norma' ); ?>
	<input type="hidden" name="clave" value="<?php echo esc_attr( $norma ? (string) $norma->clave : '' ); ?>">

	<table class="form-table">
		<tr>
			<th scope="row"><label for="swpk-norma-nombre">Qué dice la norma</label></th>
			<td>
				<input type="text" id="swpk-norma-nombre" name="nombre" class="regular-text" style="width:100%"
					value="<?php echo esc_attr( $norma ? (string) $norma->nombre : '' ); ?>"
					<?php echo $norma && (int) $norma->de_fabrica === 1 ? 'readonly' : 'required'; ?>>
				<?php if ( $norma && (int) $norma->de_fabrica === 1 ) : ?>
					<p class="description">
						El nombre y el porqué de una norma de fábrica los pone el código, para que una
						actualización del plugin no borre en silencio lo que alguien escribió aquí. Lo que sí se
						puede cambiar es a qué sitios aplica, qué tan grave es y si está encendida.
					</p>
				<?php else : ?>
					<p class="description">
						En afirmativo, como se quiere que esté: «Los formularios tienen captcha», no «revisar captcha».
						Así el tablero se lee igual que la norma.
					</p>
				<?php endif; ?>
			</td>
		</tr>

		<?php if ( ! $norma || (int) $norma->de_fabrica !== 1 ) : ?>
			<tr>
				<th scope="row"><label for="swpk-norma-problema">Y si no se cumple, ¿qué se dice?</label></th>
				<td>
					<input type="text" id="swpk-norma-problema" name="problema" class="regular-text" style="width:100%"
						value="<?php echo esc_attr( $norma ? (string) $norma->problema : '' ); ?>">
					<p class="description">
						La misma idea, pero al revés: si arriba pusiste «Los formularios tienen captcha», aquí va
						«Los formularios no tienen captcha». Es el título que sale en la lista de problemas, y
						hacen falta los dos textos: sin este, el panel pondría la regla en afirmativo encima de
						una lista de sitios que no la cumplen, y se contradiría solo.
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row"><label for="swpk-norma-porque">Por qué importa</label></th>
				<td>
					<textarea id="swpk-norma-porque" name="porque" rows="3" style="width:100%"><?php
						echo esc_textarea( $norma ? (string) $norma->porque : '' );
					?></textarea>
					<p class="description">
						Qué puede pasar si no se cumple, en palabras que se le puedan decir a un cliente. Esto es lo
						que sale debajo de la norma en el tablero, y es lo que convierte una lista de reglas en algo
						que alguien entiende por qué tiene que arreglar.
					</p>
				</td>
			</tr>
		<?php endif; ?>

		<tr>
			<th scope="row">Gravedad</th>
			<td>
				<?php foreach ( [ 'alta' => 'Alta', 'media' => 'Media', 'baja' => 'Baja' ] as $v => $etq ) : ?>
					<label style="margin-right:14px">
						<input type="radio" name="severidad" value="<?php echo esc_attr( $v ); ?>"
							<?php checked( $norma ? (string) $norma->severidad : 'media', $v ); ?>>
						<?php echo esc_html( $etq ); ?>
					</label>
				<?php endforeach; ?>
				<p class="description">Decide el orden en el tablero: lo grave arriba.</p>
			</td>
		</tr>

		<tr>
			<th scope="row">Aplica a</th>
			<td>
				<?php
				$actuales = $norma && '' !== trim( (string) $norma->aplica_a )
					? array_map( 'trim', explode( ',', (string) $norma->aplica_a ) )
					: array_keys( $grupos );
				?>
				<?php foreach ( $grupos as $tag => $etq ) : ?>
					<label style="display:block;margin-bottom:4px">
						<input type="checkbox" name="aplica_a[]" value="<?php echo esc_attr( $tag ); ?>"
							<?php checked( in_array( $tag, $actuales, true ) ); ?>>
						<?php echo esc_html( $etq ); ?>
					</label>
				<?php endforeach; ?>
				<p class="description">
					Los cuatro marcados quiere decir «todos los sitios», que es lo normal para una norma de
					seguridad. Ojo: los sitios del cliente se <strong>miden</strong> igual, pero el panel no les
					cambia nada — eso es aparte y no se decide aquí.
				</p>
			</td>
		</tr>

		<tr>
			<th scope="row">Encendida</th>
			<td>
				<label>
					<input type="checkbox" name="activa" value="1" <?php checked( $norma ? (int) $norma->activa === 1 : true ); ?>>
					Medir esta norma
				</label>
			</td>
		</tr>
	</table>

	<?php if ( ! $norma ) : ?>
		<div class="swpk-comoasi" style="margin:0 0 14px">
			<p>
				<strong>¿Y cómo se revisa una norma que escribo yo?</strong> La revisa una persona, no el
				programa. Eso es lo que hay que saber antes de escribirla.
			</p>
			<p>
				Las diez que trae el panel las mira el plugin del sitio porque alguien programó <em>cómo</em>
				mirarlas: dónde está el dato, qué cuenta como cumplir. Una norma escrita aquí no trae eso, y no
				hay forma de que el panel lo adivine a partir de una frase. Así que va a salir siempre como
				<strong>«sin revisar»</strong> hasta que alguien entre a la ficha de un sitio y la marque.
			</p>
			<p>
				Sirve igual, y bastante: queda con el nombre de quien la revisó y la fecha, que es exactamente
				lo que vale cuando hay que demostrar algo. Es una lista de verificación con rastro — pero no es
				una medición automática, y el panel no va a fingir que lo sea. Si quieres que una norma nueva se
				revise sola, eso es programarla.
			</p>
		</div>
	<?php endif; ?>

	<p>
		<button type="submit" class="button button-primary"><?php echo $norma ? 'Guardar' : 'Agregar la norma'; ?></button>
		<?php if ( $norma ) : ?>
			<a class="button" href="<?php
				echo esc_url( add_query_arg( [ 'page' => 'panel-kinsta-seguridad', 'seccion' => 'normas' ], admin_url( 'admin.php' ) ) );
			?>">Cancelar</a>
		<?php endif; ?>
	</p>
</form>
<?php SWPK_Vista::caja_cerrar(); ?>
