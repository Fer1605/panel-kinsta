<?php
/**
 * Caídas: cómo están ahora mismo, y el historial de lo que se cayó.
 *
 * ─── Por qué cambió (27-11-2026) ────────────────────────────────────────────
 *
 *   «Caídas tiene mucha información al entrar.»
 *
 * Y el diagnóstico de `documentacion/16-estilo-de-las-pantallas.md` §9 era:
 * «sí y no: la pantalla está bien, lo que satura es lo que enseña de golpe».
 * Medido antes de tocar nada: la página medía 4.809 px de alto y la tabla
 * empezaba en el píxel 893. En el portátil de 13" del jefe, la primera
 * pantalla entera no enseñaba ni un sitio.
 *
 * Lo que la llenaba, en orden:
 *
 *  1. **Dos avisos de tres párrafos cada uno** —el monitor parado y la vuelta
 *     que salió por la IP bloqueada—. Ahora son una línea con su burbuja «i»,
 *     igual que el aviso de fecha del resto del panel.
 *  2. **El veredicto, en una caja aparte**, repetía lo que ya podía decir el
 *     subtítulo de la cabecera y añadía dos párrafos de mecánica. El veredicto
 *     sube al subtítulo, la mecánica baja a la burbuja, y la caja desaparece.
 *  3. **La dona**, una caja de ancho completo para enseñar cuatro números, de
 *     los que tres suelen ser cero. La sustituyen las mismas tarjetas en fila
 *     de Vulnerabilidades, que además se pueden pulsar y filtran la lista.
 *  4. **La tabla**, con la celda «Ahora» a cuatro renglones —pastilla, código,
 *     «Qué significa» plegable y «desde»— en TODAS las filas, incluidas las de
 *     los sitios que contestan 200 perfectamente. Cada renglón medía 140 px.
 *     Ahora es la misma línea que se despliega de Vulnerabilidades y de la
 *     ficha de un sitio: lo que se decide en la línea cerrada, y el análisis
 *     dentro, para la fila que a uno le importa.
 *  5. **El historial**, cuarenta filas seguidas. Paginado de diez en diez, con
 *     su propio parámetro para no pelearse con el paginador de arriba.
 */
defined( 'ABSPATH' ) || exit;

$estados = SWPK_Monitor::estados();
$ultimo  = SWPK_Monitor::ultimo_sondeo();
$al_dia  = SWPK_Monitor::esta_al_dia();

// Las cifras de arriba cuentan SIEMPRE los 108, con filtro o sin él. Son el
// estado de la cartera, no el de la tabla: si al filtrar «sin responder» la
// cifra de «sin responder» se recalculara sobre lo filtrado, diría el total de
// lo filtrado y dejaría de ser una alarma.
$abajo = $raro = $lentos = $ciegos = $ausentes = 0;
foreach ( $estados as $e ) {
	if ( 'abajo' === $e->estado ) { ++$abajo; }
	if ( 'raro' === $e->estado ) { ++$raro; }
	if ( 'ciego' === $e->estado ) { ++$ciegos; }
	if ( 'ausente' === $e->estado ) { ++$ausentes; }
	if ( 'arriba' === $e->estado && $e->ms > 5000 ) { ++$lentos; }
}

$responden = count( $estados ) - (int) $abajo - (int) $ciegos - (int) $ausentes;

// «Lento» no es un estado guardado: es responder bien pero tardando más de
// cinco segundos. Se filtra igual porque es justo lo que uno quiere mirar
// cuando un cliente dice que su sitio va pesado.
// La maqueta ofrece tres cosas y no cinco: responde, no responde, o todo.
// «Respuesta rara» y «lento» eran matices del primero, y de los cinco solo se
// usaban dos. Lo que se perdía al simplificar —el 4xx, el motivo— no se
// pierde: está dentro de la fila, que ahora se abre.
$estados_posibles = [
	'arriba' => 'Responden',
	'abajo'  => 'No responden',
	// Tercera opción, y no es un matiz: son los sitios cuyo hosting no deja
	// pasar al monitor. Del resto se sabe algo; de estos no se sabe nada, y
	// mezclarlos con «responden» sería decir que están bien.
	'ciego'  => 'No se pueden medir',
	// Cuarta, y no es un matiz tampoco: el hosting dejó de devolver este
	// sitio. No es que no conteste — es que ya no está. Mezclarlo con «no
	// responden» manda a alguien a revisar un sitio borrado.
	'ausente' => 'Ya no están en el hosting',
];

// Cuánto tarda, en tres tramos. Contesta algo que el estado solo no contesta:
// un sitio puede responder bien y aun así tardar ocho segundos, que es de lo
// que se queja el cliente.
$tramos = [
	'rapido' => 'Menos de 1 s',
	'medio'  => 'Entre 1 y 3 s',
	'tardon' => 'Más de 3 s',
];

$estado_pedido = SWPK_Vista::opcion_pedida( 'estado', $estados_posibles );
$grupo_pedido  = SWPK_Vista::opcion_pedida( 'tag', SWPK_Etiquetas::nombres() );
$tramo_pedido  = SWPK_Vista::opcion_pedida( 'tarda', $tramos );
$busca_caida   = SWPK_Vista::busqueda_pedida();

// El grupo de cada sitio vigilado, para el desplegable y para filtrar. Se
// arma una vez: preguntarlo dentro del filtro son 108 consultas por carga.
$grupo_de = [];
$por_tag  = [];

foreach ( $estados as $e ) {
	$sitio                             = SWPK_Inventario::obtener( (string) $e->sitio_id );
	$t                                 = (string) ( $sitio->tag ?? '' );
	$grupo_de[ (string) $e->sitio_id ] = $t;
	if ( '' !== $t ) {
		$por_tag[ $t ] = ( $por_tag[ $t ] ?? 0 ) + 1;
	}
}

$filtrados = array_values(
	array_filter(
		$estados,
		static function ( $e ) use ( $estado_pedido, $grupo_pedido, $tramo_pedido, $busca_caida, $grupo_de ) {
			// «Responden» incluye la respuesta rara: el sitio contesta, aunque
			// conteste mal. «No responden» es el que no contesta nada.
			if ( 'arriba' === $estado_pedido && in_array( (string) $e->estado, [ 'abajo', 'ciego', 'ausente' ], true ) ) {
				return false;
			}
			if ( 'ciego' === $estado_pedido && 'ciego' !== (string) $e->estado ) {
				return false;
			}
			if ( 'ausente' === $estado_pedido && 'ausente' !== (string) $e->estado ) {
				return false;
			}
			if ( 'abajo' === $estado_pedido && 'abajo' !== (string) $e->estado ) {
				return false;
			}

			if ( '' !== $grupo_pedido && ( $grupo_de[ (string) $e->sitio_id ] ?? '' ) !== $grupo_pedido ) {
				return false;
			}

			if ( '' !== $tramo_pedido ) {
				$ms = (int) $e->ms;
				if ( 'rapido' === $tramo_pedido && $ms >= 1000 ) {
					return false;
				}
				if ( 'medio' === $tramo_pedido && ( $ms < 1000 || $ms > 3000 ) ) {
					return false;
				}
				if ( 'tardon' === $tramo_pedido && $ms <= 3000 ) {
					return false;
				}
			}

			if ( '' === $busca_caida ) {
				return true;
			}

			$aguja = strtolower( remove_accents( $busca_caida ) );
			$sitio = SWPK_Inventario::obtener( (string) $e->sitio_id );
			$pajar = strtolower( remove_accents( (string) ( $sitio->nombre_visible ?? '' ) . ' ' . (string) $e->url ) );
			return str_contains( $pajar, $aguja );
		}
	)
);

// Los que más tardan, primero: es el orden con el que uno abre esta pantalla
// —«¿cuál va pesado?»—. Los caídos van antes que todos: no tardan,
// directamente no están. Y como son los únicos que llevan algo escrito en la
// columna de la derecha, lo accionable queda arriba y a la vista.
usort(
	$filtrados,
	static fn( $a, $b ) =>
		[ 'abajo' === (string) $b->estado, (int) $b->ms ]
		<=> [ 'abajo' === (string) $a->estado, (int) $a->ms ]
);

[ $en_pantalla, $pagina, $paginas ] = SWPK_Vista::paginar( $filtrados, SWPK_Vista::pagina_pedida(), 25 );

$historial = array_values( array_filter(
	SWPK_Eventos::recientes( 40 ),
	static fn( $e ) => in_array( $e->tipo, [ SWPK_Eventos::SITIO_CAIDO, SWPK_Eventos::SITIO_REPUESTO ], true )
) );

// El historial tiene su propio parámetro de página: con `p` para las dos
// listas, pasar de página en una saltaba también la otra.
[ $hist_pagina, $pag_hist, $pags_hist ] = SWPK_Vista::paginar( $historial, SWPK_Vista::pagina_pedida( 'ph' ), 10 );

/** Esta misma pantalla con un estado puesto, para las tarjetas de arriba. */
$url_estado = static fn( string $cual ) => add_query_arg(
	array_filter( [ 'page' => 'panel-kinsta-caidas', 'estado' => $cual ] ),
	admin_url( 'admin.php' )
);
?>
<div class="wrap">
	<?php
	// ── El veredicto sube al subtítulo ──────────────────────────────────────
	//
	// Era una caja propia con un <h2> y dos párrafos, justo encima de otra
	// caja que decía lo mismo en una dona. El subtítulo de la cabecera existe
	// exactamente para esto: es donde Vulnerabilidades pone «330 hallazgos en
	// 52 sitios». La mecánica de los dos intentos, que es lo que ocupaba los
	// párrafos, baja a la burbuja de la caja de abajo, que es donde se mira
	// cuando se duda de un número.
	$veredicto = $abajo
		? sprintf(
			'%s sin responder ahora mismo.',
			SWPK_Vista::plural( (int) $abajo, 'sitio está', 'sitios están' )
		)
		: sprintf( 'Ninguno de los %d sitios está caído ahora mismo.', count( $estados ) );

	SWPK_Vista::cabecera(
		'Caídas',
		esc_html( $veredicto ),
		SWPK_Vista::pastillas( [
			[
				'texto'  => $al_dia ? 'Sondeo automático' : 'El sondeo no está pasando',
				'url'    => admin_url( 'admin.php?page=panel-kinsta-ajustes' ),
				'activa' => ! $al_dia,
			],
		] )
		. ( SWPK_Perfiles::puede( SWPK_Perfiles::ADMINISTRAR )
			? sprintf(
				'<a class="button" data-espera="Tocando la puerta de los %d sitios…" href="%s">Sondear ahora</a>',
				count( $estados ),
				esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=swpk_sondear' ), 'swpk_sondear' ) )
			)
			: '' )
	);
	?>
	<?php SWPK_Admin::mostrar_mensaje(); ?>

	<?php if ( ! $al_dia ) : ?>
		<?php
		// ── El monitor no está corriendo ────────────────────────────────────
		//
		// Eran tres párrafos: qué pasa, por qué importa y dónde se mira. Lo
		// que hay que leer de un vistazo es el hecho y desde cuándo; el resto
		// se abre quien va a arreglarlo. Un monitor parado es grave, y por eso
		// la línea sigue siendo roja — pero grave no quiere decir largo.
		?>
		<p class="swpk-aviso-fecha swpk-aviso-fecha-mal">
			<strong>El monitor no está corriendo.</strong>
			<?php
			echo empty( $ultimo['cuando'] )
				? 'Todavía no ha hecho ningún sondeo.'
				: 'El último sondeo fue el ' . esc_html( SWPK_Frescura::fmt( 'j M, H:i', (string) $ultimo['cuando'] ) ) . ', hace demasiado.';
			?>
			<?php echo wp_kses_post( SWPK_Vista::ayuda(
				'El sondeo va cada hora y lo dispara el reloj del servidor. Si han pasado más de dos '
				. 'horas, ese reloj no está pasando por aquí. Un monitor que no corre es peor que no '
				. 'tener monitor, porque da falsa tranquilidad: lo de abajo es la última medición '
				. 'buena de cada sitio, no cómo están ahora. En Pantheon, que es donde vive este '
				. 'panel, el cron lo ejecuta la plataforma una vez por hora y no depende de las '
				. 'visitas. Para hacer la vuelta completa a mano está el botón «Sondear ahora», '
				. 'arriba a la derecha.'
			) ); ?>
		</p>
	<?php endif; ?>

	<?php if ( ! empty( $ultimo['corrida_mal'] ) ) : ?>
		<?php
		// ── La vuelta salió por una IP bloqueada ────────────────────────────
		//
		// No es un problema de los sitios y es importante no leerlo así. El
		// hosting de los clientes tiene una de las IP de salida de este
		// servidor en su lista negra y otra limpia, y no se puede elegir por
		// cuál sale cada vuelta. La que sale por la mala no mide nada.
		//
		// Lo que se ve abajo es la ÚLTIMA MEDICIÓN BUENA de cada sitio: desde
		// esa vuelta no se escribió nada encima, y cada fila dice de cuándo
		// es. Eran tres párrafos para decir eso; ahora es la línea y su
		// burbuja.
		?>
		<p class="swpk-aviso-fecha">
			<strong>La última vuelta no pudo medir casi nada, y no es culpa de los sitios.</strong>
			<?php echo esc_html( sprintf(
				'%d de %d respondieron con la página de bloqueo del hosting.',
				(int) ( $ultimo['ciegos'] ?? 0 ),
				(int) ( $ultimo['sondeados'] ?? 0 )
			) ); ?>
			<?php echo wp_kses_post( SWPK_Vista::ayuda(
				'Este panel sale a internet por una dirección que cambia sola, y el hosting de los '
				. 'clientes tiene una de esas direcciones en su lista de bloqueo y otra limpia. '
				. 'Cuando la vuelta sale por la bloqueada, no ve nada.'
				. ( ! empty( $ultimo['ip_de_salida'] )
					? ' Esta salió por ' . (string) $ultimo['ip_de_salida'] . '.'
					: '' )
				. ' Lo que estás viendo abajo no se borró: es la última medición buena de cada '
				. 'sitio, y cada fila dice de cuándo es la suya. La vuelta siguiente lo intenta '
				. 'otra vez.'
			) ); ?>
		</p>
	<?php endif; ?>

	<?php if ( $estados ) : ?>
		<?php
		// ── Las cifras, en una filita, y cada una lleva a su lista ──────────
		//
		// Aquí había una dona dentro de una caja de ancho completo: 230 px de
		// alto para enseñar cuatro números, de los que tres suelen ser cero, y
		// sin poder pulsarlos. Son las mismas tarjetas de Vulnerabilidades, y
		// por la misma razón: «que sea solo una filita, el número y el texto a
		// la par». Cada una lleva a la lista ya filtrada.
		//
		// Las dos primeras salen siempre, aunque sean cero —un cero en «no
		// responden» es la buena noticia que se viene a ver—. Las otras dos
		// solo si hay algo: un cero en «ya no están en el hosting» no es
		// noticia, es lo normal.
		?>
		<div class="swpk-tarjetas">
			<a class="swpk-tarjeta swpk-tarjeta-enlace" href="<?php echo esc_url( $url_estado( 'arriba' ) ); ?>">
				<div class="swpk-n" style="color:#1c6dc7"><?php echo (int) $responden; ?></div>
				<div class="swpk-r">Responden</div>
			</a>
			<a class="swpk-tarjeta swpk-tarjeta-enlace" href="<?php echo esc_url( $url_estado( 'abajo' ) ); ?>">
				<div class="swpk-n" style="color:<?php echo $abajo ? '#9e0b10' : '#1c6dc7'; ?>"><?php echo (int) $abajo; ?></div>
				<div class="swpk-r">No responden</div>
			</a>
			<?php if ( $ciegos ) : ?>
				<a class="swpk-tarjeta swpk-tarjeta-enlace" href="<?php echo esc_url( $url_estado( 'ciego' ) ); ?>">
					<div class="swpk-n"><?php echo (int) $ciegos; ?></div>
					<div class="swpk-r">No se pueden medir</div>
				</a>
			<?php endif; ?>
			<?php if ( $ausentes ) : ?>
				<a class="swpk-tarjeta swpk-tarjeta-enlace" href="<?php echo esc_url( $url_estado( 'ausente' ) ); ?>">
					<div class="swpk-n"><?php echo (int) $ausentes; ?></div>
					<div class="swpk-r">Ya no están en el hosting</div>
				</a>
			<?php endif; ?>
		</div>

		<?php
		// La línea de cuándo se miró, con la mecánica dentro. Es el dato que
		// convierte «ninguno está caído» en una afirmación comprobable: sin
		// saber de cuándo es la medición, la frase no vale nada.
		?>
		<p class="swpk-comoasi-linea">
			<?php if ( ! empty( $ultimo['cuando'] ) ) : ?>
				<strong>Última revisión:</strong>
				<?php echo esc_html( SWPK_Frescura::fmt( 'j \d\e F, H:i', (string) $ultimo['cuando'] ) ); ?>
				(<?php echo esc_html( SWPK_Frescura::hace( (string) $ultimo['cuando'] ) ); ?>),
				<?php echo esc_html( SWPK_Vista::plural( (int) ( $ultimo['sondeados'] ?? 0 ), 'sitio comprobado', 'sitios comprobados' ) ); ?>.
			<?php else : ?>
				<strong>Todavía no se ha hecho ninguna revisión.</strong>
			<?php endif; ?>
			<?php if ( $ausentes ) : ?>
				<?php echo esc_html( SWPK_Vista::plural( (int) $ausentes, 'sitio ya no está', 'sitios ya no están' ) ); ?>
				en el hosting y no cuentan como caída.
			<?php endif; ?>
			<?php echo wp_kses_post( SWPK_Vista::ayuda( sprintf(
				'El panel mira todos los sitios una vez por hora. En cuanto uno deja de responder, '
				. 'pasa a mirar SOLO los caídos cada cinco minutos, hasta que vuelvan: por eso la '
				. 'duración de una caída se puede afirmar con cinco minutos de margen y no con '
				. 'sesenta. No se avisa al primer fallo: al que no contesta se le pregunta otra vez '
				. 'ocho segundos después, y solo cuenta si falla las dos veces, porque un fallo '
				. 'suelto casi siempre es la red y no el sitio. Mientras un sitio siga sin '
				. 'responder sale un recordatorio por correo una vez al día. Un sitio que figura '
				. '«no se puede medir» no es un sitio caído: es uno al que el cortafuegos de su '
				. 'hosting no nos deja preguntarle. Y uno que «ya no está» es uno que el hosting '
				. 'dejó de devolver; a las %d comprobaciones seguidas sin aparecer se da de baja '
				. 'solo y sale el correo.',
				(int) SWPK_Inventario::AUSENCIAS_PARA_BAJA
			) ) ); ?>
		</p>

		<?php SWPK_Vista::rejilla_abrir(); ?>

		<?php
		SWPK_Vista::caja_abrir(
			'Sitio por sitio',
			'los que más tardan, primero',
			'swpk-c12',
			true
		);

		SWPK_Vista::barra_de_filtros(
			'panel-kinsta-caidas',
			[
				'estado' => [
					'etiqueta' => 'Cómo responde',
					'opciones' => $estados_posibles,
					'actual'   => $estado_pedido,
					'todas'    => 'Todos',
				],
				'tag' => [
					'etiqueta' => 'Grupo',
					'opciones' => array_intersect_key( SWPK_Etiquetas::nombres(), $por_tag ),
					'actual'   => $grupo_pedido,
					'todas'    => 'Todos los grupos',
				],
				'tarda' => [
					'etiqueta' => 'Lo que tarda',
					'opciones' => $tramos,
					'actual'   => $tramo_pedido,
					'todas'    => 'Lo que tarda',
				],
			],
			[],
			$busca_caida
		);
		?>

		<?php SWPK_Vista::paginador( $pagina, $paginas, count( $filtrados ) ); ?>

		<?php if ( ! $en_pantalla ) : ?>
			<div class="notice notice-info inline"><p>Ningún sitio coincide con ese filtro.</p></div>
		<?php else : ?>
			<?php
			// ── Por qué esto ya no es una tabla ─────────────────────────────
			//
			// Era una tabla de seis columnas y la de «Ahora» llevaba cuatro
			// renglones —la pastilla, el código traducido, un «Qué significa»
			// plegable y «desde cuándo»— en TODAS las filas, incluidas las de
			// los 106 sitios que contestan 200 sin problema. A un sitio que
			// responde bien no hay que explicarle nada: el «Qué significa» de
			// un 200 decía «el panel no tiene una traducción para esta
			// respuesta», que es ruido con forma de dato.
			//
			// Cada renglón medía 140 px. Ahora es la misma línea que se
			// despliega de Vulnerabilidades y de la ficha de un sitio, con las
			// mismas clases: sitio, estado, y qué hacer. Y la columna de la
			// derecha solo lleva texto cuando hay algo que hacer, así que las
			// filas con problema —que van primero— son las únicas que saltan.
			?>
			<?php
			// ── Si en esta página no hay nada que hacer, sobran tres columnas ──
			//
			// La tercera columna solo lleva texto en las filas con problema. El
			// día que todos responden —que es la mayoría de los días— esa
			// columna está vacía en las 25 filas, y la pastilla de estado se
			// queda flotando en medio con 400 px de aire a la derecha: el mismo
			// «tenemos mucho espacio de sobra al lado derecho» de
			// Vulnerabilidades. Si ninguna fila de ESTA página tiene nada que
			// hacer, la rejilla pasa a dos columnas y el estado se pega a la
			// derecha. Se decide por página entera, nunca por fila: una columna
			// que cambia de sitio de fila en fila es justo lo que no puede
			// pasar.
			$hay_accion = false;
			foreach ( $en_pantalla as $e_a ) {
				if ( 'arriba' !== (string) $e_a->estado ) {
					$hay_accion = true;
					break;
				}
			}
			?>
			<div class="swpk-normas swpk-pendientes swpk-caidas-lista<?php echo $hay_accion ? '' : ' swpk-caidas-sin-accion'; ?>">
			<?php foreach ( $en_pantalla as $e ) : ?>
				<?php
				$sitio = SWPK_Inventario::obtener( $e->sitio_id );

				[ $nivel_e, $texto ] = match ( $e->estado ) {
					'abajo'   => [ SWPK_Riesgo::CRITICO, 'Sin responder' ],
					'ausente' => [ SWPK_Riesgo::BAJO, 'Ya no está' ],
					'raro'    => [ SWPK_Riesgo::MEDIO, 'Respuesta rara' ],
					'ciego'   => [ SWPK_Riesgo::SIN_DATOS, 'No se puede medir' ],
					'arriba'  => [ SWPK_Riesgo::AL_DIA, 'Responde' ],
					default   => [ SWPK_Riesgo::SIN_DATOS, 'Sin datos' ],
				};

				// El texto NO siempre es blanco: la paleta usa claros y
				// oscuros para que los seis niveles se separen. Ver
				// SWPK_Riesgo::texto_sobre().
				$color = SWPK_Riesgo::colores()[ $nivel_e ];
				$sobre = SWPK_Riesgo::texto_sobre( $nivel_e );

				// Hay algo que explicar solo cuando el sitio NO está bien. Un
				// 200 no se traduce: se da por bueno y se calla.
				$hay_que_mirar = 'arriba' !== (string) $e->estado;
				$t             = $hay_que_mirar ? SWPK_Codigos::traducir( (int) $e->codigo, (string) $e->motivo ) : null;
				$logs          = $sitio ? SWPK_Codigos::url_registros( $sitio ) : '';

				// El punto de color de «quién se mueve» es el mismo de las
				// otras dos pantallas, y las claves de SWPK_Codigos coinciden
				// con las de SWPK_QueHacer salvo «no se sabe», que se pinta
				// como «hay que esperar»: en los dos casos el trabajo todavía
				// no tiene dueño.
				$clase_quien = $t
					? ( SWPK_Codigos::NO_SE_SABE === $t['quien'] ? 'esperar' : (string) $t['quien'] )
					: '';

				// El dominio sin el protocolo ni la barra final. La url entera
				// de un subdominio largo se partía en dos renglones y empujaba
				// la fila entera.
				$corto = preg_replace( '#^https?://|/$#', '', (string) $e->url );
				?>
				<details class="swpk-norma swpk-pendiente swpk-caida">
					<summary>
						<span class="swpk-norma-nombre">
							<strong><?php echo esc_html( $sitio->nombre_visible ?? $e->sitio_id ); ?></strong>
							<span class="swpk-norma-aclara">
								<?php echo esc_html( (string) $corto ); ?>
								<?php if ( $e->ms ) : ?>
									· <span class="swpk-mono"><?php echo esc_html( number_format( $e->ms / 1000, 1 ) ); ?> s</span>
								<?php endif; ?>
								<?php if ( $e->ultimo_sondeo ) : ?>
									· <?php echo esc_html( SWPK_Frescura::hace( (string) $e->ultimo_sondeo ) ); ?>
								<?php else : ?>
									· nunca revisado
								<?php endif; ?>
							</span>
						</span>
						<span class="swpk-norma-estado">
							<span class="swpk-nivel" style="background:<?php echo esc_attr( $color ); ?>;color:<?php echo esc_attr( $sobre ); ?>;border-color:<?php echo esc_attr( $color ); ?>"><?php echo esc_html( $texto ); ?></span>
							<?php if ( $hay_que_mirar && ( $e->codigo || $e->motivo ) ) : ?>
								<span class="swpk-pendiente-pildora swpk-mudo">
									<?php echo esc_html( SWPK_Codigos::titulo_corto( (int) $e->codigo, (string) $e->motivo ) ); ?>
								</span>
							<?php endif; ?>
						</span>
						<?php
						// Vacía cuando el sitio responde bien, y es a
						// propósito: no hay nada que hacer, y una columna
						// repitiendo «nada que hacer» cien veces es la clase
						// de relleno que llenó esta pantalla la primera vez.
						?>
						<?php if ( $t ) : ?>
							<span class="swpk-pendiente-hacer swpk-hacer-<?php echo esc_attr( $clase_quien ); ?>">
								<?php echo esc_html( SWPK_Codigos::de_quien()[ $t['quien'] ] ?? '' ); ?>
							</span>
						<?php else : ?>
							<?php
							// La celda vacía existe igual: la rejilla tiene tres
							// columnas fijas y sin ella la pastilla de estado se
							// iría al hueco de la derecha en estas filas y no en
							// las otras. Sin el punto, que sin frase al lado sería
							// un color suelto que no dice nada.
							?>
							<span></span>
						<?php endif; ?>
					</summary>

					<div class="swpk-norma-dentro">
						<ol class="swpk-norma-pasos">
							<li>
								<h4>Qué pasa</h4>
								<?php if ( $t ) : ?>
									<p style="margin:0 0 6px"><?php echo esc_html( $t['significa'] ); ?></p>
									<p class="swpk-mudo" style="margin:0">
										Lo que midió el panel: <?php echo esc_html( (string) $e->motivo ); ?>
									</p>
								<?php else : ?>
									<p style="margin:0">
										Responde bien.
										<?php if ( $e->ms ) : ?>
											Tarda <?php echo esc_html( number_format( $e->ms / 1000, 1 ) ); ?> segundos en
											contestar<?php echo $e->ms > 5000 ? ', que es mucho: por encima de cinco segundos el visitante lo nota' : ''; ?>.
										<?php endif; ?>
									</p>
								<?php endif; ?>

								<p class="swpk-mudo" style="margin:6px 0 0">
									<?php if ( $e->desde ) : ?>
										Así desde el <?php echo esc_html( SWPK_Frescura::fmt( 'j M, H:i', $e->desde ) ); ?>.
									<?php endif; ?>
									<?php
									// «Como mucho» y no «en total»: el sondeo es cada hora,
									// así que lo que se suma es la distancia entre la
									// revisión que falló y la que ya no, no el tiempo que el
									// sitio estuvo caído. Decirlo como un total es presentar
									// una resta de relojes como una medición.
									?>
									<?php echo esc_html( SWPK_Vista::plural( (int) $e->caidas_mes, 'caída', 'caídas' ) ); ?> este mes<?php
									if ( $e->segundos_caido ) :
										?>, como mucho <?php echo esc_html( SWPK_Monitor::duracion( (int) $e->segundos_caido ) ); ?> en total<?php
									endif;
									?>.
								</p>
							</li>

							<li>
								<h4>Qué hacer</h4>
								<?php if ( $t ) : ?>
									<p><strong><?php echo esc_html( SWPK_Codigos::de_quien()[ $t['quien'] ] ?? '' ); ?>.</strong>
										<?php echo esc_html( $t['primero'] ); ?></p>
								<?php else : ?>
									<p>Nada. Este sitio contesta, y contesta en tiempo.</p>
								<?php endif; ?>
								<p class="swpk-norma-pie">
									<a class="button button-small" target="_blank" rel="noopener"
										href="<?php echo esc_url( $e->url ); ?>">Abrir el sitio ↗</a>
									<a class="button button-small"
										href="<?php echo esc_url( add_query_arg(
											[ 'page' => 'panel-kinsta-movimientos', 'sitio' => $e->sitio_id ],
											admin_url( 'admin.php' )
										) ); ?>">Ver su historial</a>
									<?php if ( '' !== $logs ) : ?>
										<a class="button button-small" target="_blank" rel="noopener"
											href="<?php echo esc_url( $logs ); ?>">Ver los registros en Kinsta ↗</a>
									<?php endif; ?>
									<span class="swpk-mudo"><?php echo SWPK_Admin::grupo_badge( $grupo_de[ (string) $e->sitio_id ] ?? '' ); ?></span>
								</p>
							</li>
						</ol>
					</div>
				</details>
			<?php endforeach; ?>
			</div>

			<?php SWPK_Vista::paginador( $pagina, $paginas, count( $filtrados ) ); ?>
		<?php endif; ?>
		<?php SWPK_Vista::caja_cerrar(); ?>

		<?php
		// El historial guarda CAMBIOS de estado, no revisiones. Un sitio que
		// lleva dos días caído aparece una sola vez, el día que se cayó, y eso
		// se presta a leerlo como «hace dos días que no se mira». Por eso lo
		// dice el subtítulo, y por eso cada fila de arriba dice cuándo se
		// revisó.
		SWPK_Vista::caja_abrir(
			'El historial',
			'se apunta cuando un sitio cambia de estado, no en cada revisión',
			'swpk-c12',
			! empty( $historial )
		);
		?>
		<?php if ( ! $historial ) : ?>
			<p style="color:#50575e;margin:0">
				No se ha caído nada desde que el monitor está encendido. Cada caída y cada
				recuperación queda aquí con su hora, y el aviso a soporte queda en el
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=panel-kinsta-correos&seccion=bitacora' ) ); ?>">historial de envíos</a>.
			</p>
		<?php else : ?>
			<table class="widefat striped swpk-tabla">
				<thead>
					<tr>
						<th style="width:150px">Cuándo</th>
						<th style="width:120px">Qué</th>
						<th>Detalle</th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $hist_pagina as $h ) : ?>
					<tr>
						<td><?php echo esc_html( SWPK_Frescura::fmt( 'j M Y, H:i', $h->ocurrio_en ) ); ?></td>
						<td>
							<?php if ( SWPK_Eventos::SITIO_CAIDO === $h->tipo ) : ?>
								<span style="color:#9e0b10">Se cayó</span>
							<?php else : ?>
								<span style="color:#0b5ed7">Volvió</span>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( $h->titulo ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<?php SWPK_Vista::paginador( $pag_hist, $pags_hist, count( $historial ), 'movimientos', 'movimiento', 'ph' ); ?>
		<?php endif; ?>
		<?php SWPK_Vista::caja_cerrar(); ?>

		<?php SWPK_Vista::rejilla_cerrar(); ?>
	<?php else : ?>
		<div class="notice notice-info"><p>
			Todavía no hay sitios vigilados. El monitor usa los dominios del inventario: primero hay
			que correr la sincronización desde el Resumen.
		</p></div>
	<?php endif; ?>
</div>
