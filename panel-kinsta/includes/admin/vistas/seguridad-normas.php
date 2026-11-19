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

$normas    = SWPK_Normas::todas();
$etiquetas = SWPK_Etiquetas::nombres();

$editar = isset( $_GET['editar'] ) ? sanitize_key( (string) $_GET['editar'] ) : '';
$norma  = '' !== $editar ? SWPK_Normas::una( $editar ) : null;

// ── Dividir por tema ────────────────────────────────────────────────────────
//
// Setenta y tres normas de corrido, cada una con su párrafo, es una pantalla
// por la que nunca se termina de bajar: «tengo que seguir haciendo demasiado
// scroll y no veo todas las reglas porque no está bien dividido». Los temas ya
// existían dentro del código, solo que como comentarios. Ahora son un dato, y
// esta pantalla los usa para dos cosas: enseñar de un vistazo en cuántas
// partes se divide esto, y no sacar más de una página de golpe.
$grupo_pedido = isset( $_GET['grupo'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['grupo'] ) ) : '';

$por_grupo = [];
foreach ( $normas as $n ) {
	$g = (string) ( $n->grupo ?? '' );
	$por_grupo[ '' !== $g ? $g : 'Sin clasificar' ][] = $n;
}

// El orden del catálogo manda; lo que no esté en él va al final, pero va.
$orden = [];
foreach ( SWPK_Normas::grupos() as $g ) {
	if ( isset( $por_grupo[ $g ] ) ) {
		$orden[] = $g;
	}
}
foreach ( array_keys( $por_grupo ) as $g ) {
	if ( ! in_array( $g, $orden, true ) ) {
		$orden[] = $g;
	}
}

$hay_grupo = '' !== $grupo_pedido && isset( $por_grupo[ $grupo_pedido ] );
$lista     = $hay_grupo ? $por_grupo[ $grupo_pedido ] : $normas;

$url_grupo = static fn( string $g ) => add_query_arg(
	array_filter( [
		'page'    => 'panel-kinsta-seguridad',
		'seccion' => 'normas',
		'grupo'   => $g,
	] ),
	admin_url( 'admin.php' )
);

$pastillas = [ [
	'texto'  => 'Todas',
	'n'      => count( $normas ),
	'url'    => $url_grupo( '' ),
	'activa' => ! $hay_grupo,
] ];

foreach ( $orden as $g ) {
	$pastillas[] = [
		'texto'  => $g,
		'n'      => count( $por_grupo[ $g ] ),
		'url'    => $url_grupo( $g ),
		'activa' => $hay_grupo && $grupo_pedido === $g,
	];
}

[ $en_pantalla, $pagina, $paginas ] = SWPK_Vista::paginar( $lista, SWPK_Vista::pagina_pedida(), 12 );
?>


<div class="swpk-pastillas" style="margin:0 0 14px"><?php echo SWPK_Vista::pastillas( $pastillas ); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>

<?php
SWPK_Vista::caja_abrir(
	( $hay_grupo ? $grupo_pedido : 'Qué revisamos en cada sitio' ) . SWPK_Vista::ayuda(
		'Esta lista decide qué se mide. Cada norma encendida se comprueba en todos los sitios que '
		. 'tengan el plugin instalado; apagarla la saca de «Qué le falta a cada sitio», y encenderla '
		. 'la devuelve con el siguiente reporte de cada sitio, no al instante. La gravedad solo decide '
		. 'el orden: las altas primero. Donde dice «El plugin», la mira el plugin solo; donde dice '
		. '«Una persona», alguien la marca entrando a la ficha del sitio. «Se arregla» quiere decir '
		. 'que el panel le puede pedir al plugin que la corrija.'
	),
	esc_html( SWPK_Vista::plural( count( $lista ), 'norma', 'normas' ) ),
	'',
	true
);
?>
<?php SWPK_Vista::paginador( $pagina, $paginas, count( $lista ), 'normas', 'norma' ); ?>
<table class="widefat striped" style="margin-top:10px">
	<thead>
		<tr>
			<th style="width:38%">La norma</th>
			<th style="width:11%">Gravedad</th>
			<th style="width:13%">Quién la revisa</th>
			<th style="width:20%">A qué sitios aplica</th>
			<th style="width:18%"></th>
		</tr>
	</thead>
	<tbody>
	<?php foreach ( $en_pantalla as $n ) : ?>
		<?php
		$apagada     = (int) $n->activa !== 1;
		$declarativa = 'declarativa' === (string) $n->comprobador;
		$aplica      = trim( (string) $n->aplica_a );
		$su_grupo    = (string) ( $n->grupo ?? '' );
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
				<?php if ( ! $hay_grupo && '' !== $su_grupo ) : ?>
					<br><a class="swpk-mudo" href="<?php echo esc_url( $url_grupo( $su_grupo ) ); ?>"><?php echo esc_html( $su_grupo ); ?></a>
				<?php endif; ?>
				<?php
				// El «porqué» es un párrafo entero y es bueno que lo sea: explica
				// qué permite hacer la falla. Pero sacarlo completo en todas las
				// filas daba filas de 300 píxeles y una pantalla de ocho
				// pantallazos. Aquí va resumido, y entero al pasar por encima o
				// al abrir «Cambiar».
				$porque = trim( (string) $n->porque );
				if ( '' !== $porque ) :
					$corto = SWPK_Vista::recortar( $porque, 70 );
					?>
					<p class="swpk-mudo" style="margin:4px 0 0" title="<?php echo esc_attr( $porque ); ?>">
						<?php echo esc_html( $corto ); ?>
					</p>
				<?php endif; ?>
			</td>
			<td>
				<span class="swpk-etq swpk-sev-<?php echo esc_attr( (string) $n->severidad ); ?>">
					<?php echo esc_html( ucfirst( (string) $n->severidad ) ); ?>
				</span>
			</td>
			<td>
				<?php
				// Antes esta celda explicaba, en tres renglones, lo mismo en las
				// 73 filas. Repetir la explicación 73 veces no explica mejor:
				// solo hace la fila tres veces más alta. La explicación vive
				// ahora una sola vez, arriba de la tabla.
				?>
				<?php if ( $declarativa ) : ?>
					<span title="Alguien la marca entrando a la ficha del sitio.">Una persona</span>
				<?php else : ?>
					<span title="El plugin la mira en el sitio y la reporta cada día.">El plugin</span>
				<?php endif; ?>
				<?php if ( (int) $n->arreglable === 1 ) : ?>
					<span class="swpk-mudo" title="El panel le puede pedir al plugin que la arregle.">· se arregla</span>
				<?php endif; ?>
			</td>
			<td>
				<?php
				if ( '' === $aplica ) {
					echo 'Todos los sitios';
				} else {
					$nombres = [];
					foreach ( explode( ',', $aplica ) as $t ) {
						$nombres[] = $etiquetas[ trim( $t ) ] ?? trim( $t );
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
<?php SWPK_Vista::paginador( $pagina, $paginas, count( $lista ), 'normas', 'norma' ); ?>
<?php SWPK_Vista::caja_cerrar(); ?>

<?php
// El formulario, plegado mientras no haga falta.
//
// Estaba siempre abierto y son mil píxeles de campos debajo de la lista. Se
// agrega o se cambia una norma cada varios meses; se consulta la lista todos
// los días. Lo que se usa poco no puede estorbar a lo que se usa siempre.
?>
<details class="swpk-caja" style="padding:0" <?php echo $norma ? 'open' : ''; ?>>
	<summary style="cursor:pointer;padding:14px 18px;font-weight:600">
		<?php echo $norma ? 'Cambiar «' . esc_html( (string) $norma->nombre ) . '»' : 'Agregar una norma'; ?>
	</summary>
	<div class="swpk-caja-cuerpo">


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
					: array_keys( $etiquetas );
				?>
				<?php foreach ( $etiquetas as $tag => $etq ) : ?>
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
		<p class="swpk-aviso-corto">
			<strong>Una norma que escribas tú la revisa una persona, no el programa.</strong>
			<?php echo wp_kses_post( SWPK_Vista::ayuda(
				'Las que trae el panel las mira el plugin del sitio porque alguien programó cómo mirarlas: '
				. 'dónde está el dato y qué cuenta como cumplir. Una norma escrita aquí no trae eso, y el panel '
				. 'no puede adivinarlo a partir de una frase, así que va a salir como «sin revisar» hasta que '
				. 'alguien entre a la ficha de un sitio y la marque. Queda con el nombre de quien la revisó y la '
				. 'fecha, que es lo que vale cuando hay que demostrar algo.'
			) ); ?>
		</p>
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
	</div>
</details>

