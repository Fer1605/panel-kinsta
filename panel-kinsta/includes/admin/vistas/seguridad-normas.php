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

<p style="max-width:880px;color:#3c434a;margin-top:16px">
	Esta es la lista contra la que se mide cada sitio. Lo que se apague aquí deja de medirse y desaparece del
	tablero; lo que se encienda se empieza a medir en el siguiente reporte de cada sitio, no al instante.
</p>

<table class="widefat striped" style="margin-top:10px">
	<thead>
		<tr>
			<th style="width:34%">Norma</th>
			<th style="width:90px">Gravedad</th>
			<th style="width:150px">Cómo se comprueba</th>
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
					<span title="Alguien la marca a mano, sitio por sitio. Queda con su nombre y su fecha.">A mano</span>
				<?php else : ?>
					<span title="La mide el agente instalado en el sitio y la manda en su reporte.">El agente</span>
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

<h2 style="margin-top:32px"><?php echo $norma ? 'Cambiar una norma' : 'Agregar una norma'; ?></h2>

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
		<div class="notice notice-warning inline" style="margin:0 0 14px">
			<p>
				Una norma nueva <strong>no se comprueba sola</strong>: nadie puede programar un comprobador desde
				este formulario. Va a aparecer en el tablero como «sin medir» hasta que alguien la marque, sitio
				por sitio, desde la ficha de cada sitio. Queda con su nombre y su fecha, que es lo que vale en una
				auditoría — pero no es una medición, y el panel no va a decir que lo sea.
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
