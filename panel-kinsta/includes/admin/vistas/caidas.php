<?php
/** Caídas: estado ahora mismo y el historial de lo que se cayó. */
defined( 'ABSPATH' ) || exit;

$estados = SWPK_Monitor::estados();
$ultimo  = SWPK_Monitor::ultimo_sondeo();
$al_dia  = SWPK_Monitor::esta_al_dia();

// Las cuatro cifras de arriba cuentan SIEMPRE los 108, con filtro o sin él.
// Son el estado de la cartera, no el de la tabla: si al filtrar «sin
// responder» la tarjeta de «sin responder» se recalculara sobre lo filtrado,
// diría el total de lo filtrado y dejaría de ser una alarma.
$abajo = $raro = $lentos = $ciegos = 0;
foreach ( $estados as $e ) {
	if ( 'abajo' === $e->estado ) { ++$abajo; }
	if ( 'raro' === $e->estado ) { ++$raro; }
	if ( 'ciego' === $e->estado ) { ++$ciegos; }
	if ( 'arriba' === $e->estado && $e->ms > 5000 ) { ++$lentos; }
}

// «Lento» no es un estado guardado: es responder bien pero tardando más de
// cinco segundos. Se filtra igual porque es justo lo que uno quiere mirar
// cuando un cliente dice que su sitio va pesado.
// La maqueta ofrece tres cosas y no cinco: responde, no responde, o todo.
// «Respuesta rara» y «lento» eran matices del primero, y de los cinco solo se
// usaban dos. Lo que se perdía al simplificar —el 4xx, el motivo— no se
// pierde: está en la celda de «Ahora», que ahora los dice debajo.
$estados_posibles = [
	'arriba' => 'Responden',
	'abajo'  => 'No responden',
	// Tercera opción, y no es un matiz: son los sitios cuyo hosting no deja
	// pasar al monitor. Del resto se sabe algo; de estos no se sabe nada, y
	// mezclarlos con «responden» sería decir que están bien.
	'ciego'  => 'No se pueden medir',
];

// Cuánto tarda, en tres tramos. Es el tercer desplegable de la maqueta y
// contesta algo que el estado solo no contesta: un sitio puede responder bien
// y aun así tardar ocho segundos, que es de lo que se queja el cliente.
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
	$sitio                            = SWPK_Inventario::obtener( (string) $e->sitio_id );
	$t                                = (string) ( $sitio->tag ?? '' );
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
			if ( 'arriba' === $estado_pedido && in_array( (string) $e->estado, [ 'abajo', 'ciego' ], true ) ) {
				return false;
			}
			if ( 'ciego' === $estado_pedido && 'ciego' !== (string) $e->estado ) {
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

// Los que más tardan, primero: es lo que dice el pie de la tarjeta en la
// maqueta, y es el orden con el que uno abre esta pantalla —«¿cuál va
// pesado?»—. Los caídos van antes que todos: no tardan, directamente no están.
usort(
	$filtrados,
	static fn( $a, $b ) =>
		[ 'abajo' === (string) $b->estado, (int) $b->ms ]
		<=> [ 'abajo' === (string) $a->estado, (int) $a->ms ]
);

[ $en_pantalla, $pagina, $paginas ] = SWPK_Vista::paginar( $filtrados, SWPK_Vista::pagina_pedida(), 25 );

$historial = array_filter(
	SWPK_Eventos::recientes( 40 ),
	static fn( $e ) => in_array( $e->tipo, [ SWPK_Eventos::SITIO_CAIDO, SWPK_Eventos::SITIO_REPUESTO ], true )
);
?>
<div class="wrap">
	<?php
	SWPK_Vista::cabecera(
		'Caídas',
		esc_html( sprintf( 'El panel sondea los %d sitios una vez por hora.', count( $estados ) ) ),
		SWPK_Vista::pastillas( [
			[
				'texto'  => $al_dia ? 'Sondeo automático' : 'El sondeo no está pasando',
				'url'    => admin_url( 'admin.php?page=panel-kinsta-ajustes' ),
				'activa' => ! $al_dia,
			],
		] )
		. ( SWPK_Perfiles::puede( SWPK_Perfiles::ADMINISTRAR )
			? sprintf(
				'<a class="button" href="%s">Sondear ahora</a>',
				esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=swpk_sondear' ), 'swpk_sondear' ) )
			)
			: '' )
	);
	?>
	<?php SWPK_Admin::mostrar_mensaje(); ?>

	<?php
	// El veredicto de la maqueta: la frase que contesta la pantalla entera.
	// Es lo único que ella deja entre la cabecera y las tarjetas.
	?>
	<div class="swpk-veredicto<?php echo $abajo ? ' swpk-veredicto-mal' : ''; ?>">
		<h2>
			<?php
			echo esc_html(
				$abajo
					? sprintf(
						'%s sin responder ahora mismo.',
						SWPK_Vista::plural( (int) $abajo, 'sitio está', 'sitios están' )
					)
					: sprintf( 'Ninguno de los %d sitios está caído ahora mismo.', count( $estados ) )
			);
			?>
		</h2>
		<p>
			No se avisa al primer fallo: al que no contesta se le pregunta otra vez ocho segundos
			después, y solo cuenta si falla las dos veces. Un fallo suelto casi siempre es la red
			y no el sitio.
		</p>
	</div>

	<?php if ( ! $al_dia ) : ?>
		<div class="notice notice-error">
			<p><strong>El monitor no está corriendo.</strong>
				<?php
				echo empty( $ultimo['cuando'] )
					? 'Todavía no ha hecho ningún sondeo.'
					: 'El último sondeo fue ' . esc_html( SWPK_Frescura::fmt( 'j M, H:i', (string) $ultimo['cuando'] ) ) . ', hace demasiado.';
				?>
			</p>
			<p>
				El sondeo va cada hora, y lo dispara el reloj del hosting. Si han pasado más de dos
				horas, ese reloj no está pasando por aquí. Un monitor que no corre es peor que no
				tener monitor, porque da falsa tranquilidad.
			</p>
			<p>
				Lo primero es mirar el reloj del servidor: en Pantheon, que es donde vive este panel,
				el cron lo ejecuta la plataforma una vez por hora y no depende de las visitas.
				Mientras tanto, «Sondear ahora» hace la vuelta completa a mano.
			</p>
		</div>
	<?php endif; ?>


	<?php SWPK_Vista::rejilla_abrir(); ?>

	<?php if ( $estados ) : ?>
		<?php
		// ── La dona, estrecha; la tabla, entera ─────────────────────────
		//
		// Iban a cinco y siete columnas, y la tabla de al lado quedaba tan
		// apretada que le salía desplazamiento propio: «le generaste un scroll
		// interno bien feo a lo de sitio por sitio, mejor que esté horizontal».
		// La dona necesita lo que necesita —es un círculo de 140 px— y lo que
		// sobraba era el hueco en blanco a su derecha. Así que la dona ocupa
		// un tercio y la tabla, abajo, todo el ancho.
		SWPK_Vista::caja_abrir( 'Cómo están ahora', 'sondeo cada hora', 'swpk-c12' );

		// Tres porciones, no dos. Antes «Responden» era todo-menos-los-caídos,
		// así que los 106 sitios que el cortafuegos no deja medir se contaban
		// como que respondían. La dona decía 107 de 108 arriba mientras el
		// panel no había conseguido ver ni uno.
		SWPK_Vista::dona(
			[
				[
					'n'     => count( $estados ) - (int) $abajo - (int) $ciegos,
					'color' => SWPK_Riesgo::colores()[ SWPK_Riesgo::AL_DIA ],
					'texto' => 'Responden',
				],
				[
					'n'     => (int) $abajo,
					'color' => SWPK_Riesgo::colores()[ SWPK_Riesgo::CRITICO ],
					'texto' => 'No responden',
				],
				[
					'n'     => (int) $ciegos,
					'color' => SWPK_Riesgo::colores()[ SWPK_Riesgo::SIN_DATOS ],
					'texto' => 'No se pueden medir',
				],
			],
			count( $estados ),
			'sitios'
		);
		?>
		<?php SWPK_Vista::caja_cerrar(); ?>

		<?php
		// Las cuatro cifras de arriba cuentan SIEMPRE los 108, pase lo que pase
		// con el filtro de abajo. Es a propósito: son el estado de la cartera,
		// no el de la página que estás mirando.
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
		<table class="widefat striped swpk-tabla">
			<thead>
				<tr>
					<th>Sitio</th>
					<th style="width:120px">Grupo</th>
					<th style="width:190px">Ahora</th>
					<th style="width:80px">Tarda</th>
					<th style="width:110px">Caídas este mes</th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $en_pantalla as $e ) : ?>
				<?php
				$sitio = SWPK_Inventario::obtener( $e->sitio_id );
				[ $nivel_e, $texto ] = match ( $e->estado ) {
					'abajo'  => [ SWPK_Riesgo::CRITICO, 'Sin responder' ],
					'raro'   => [ SWPK_Riesgo::MEDIO, 'Respuesta rara' ],
					'ciego'  => [ SWPK_Riesgo::SIN_DATOS, 'No se puede medir' ],
					'arriba' => [ SWPK_Riesgo::AL_DIA, 'Responde' ],
					default  => [ SWPK_Riesgo::SIN_DATOS, 'Sin datos' ],
				};
				// El texto NO siempre es blanco: la paleta usa claros y
				// oscuros para que los seis niveles se separen. Ver
				// SWPK_Riesgo::texto_sobre().
				$color    = SWPK_Riesgo::colores()[ $nivel_e ];
				$sobre    = SWPK_Riesgo::texto_sobre( $nivel_e );
				?>
				<tr>
					<td>
						<strong><?php echo esc_html( $sitio->nombre_visible ?? $e->sitio_id ); ?></strong><br>
						<a class="swpk-mono" style="color:#50575e" href="<?php echo esc_url( $e->url ); ?>" target="_blank" rel="noopener">
							<?php echo esc_html( $e->url ); ?>
						</a>
					</td>
					<td><?php echo SWPK_Admin::grupo_badge( $grupo_de[ (string) $e->sitio_id ] ?? '' ); ?></td>
					<td>
						<span class="swpk-nivel" style="background:<?php echo esc_attr( $color ); ?>;color:<?php echo esc_attr( $sobre ); ?>;border-color:<?php echo esc_attr( $color ); ?>"><?php echo esc_html( $texto ); ?></span>
						<?php if ( $e->codigo || $e->motivo ) : ?>
							<br>
							<span class="swpk-gris" style="font-size:12px">
								<?php
								$detalle = array_filter( [
									$e->codigo ? (string) (int) $e->codigo : '',
									(string) $e->motivo,
								] );
								echo esc_html( implode( ' · ', $detalle ) );
								?>
							</span>
						<?php endif; ?>
						<?php if ( $e->desde ) : ?>
							<br><span class="swpk-gris" style="font-size:12px">desde <?php echo esc_html( SWPK_Frescura::fmt( 'j M, H:i', $e->desde ) ); ?></span>
						<?php endif; ?>
					</td>
					<td class="swpk-mono"><?php echo $e->ms ? esc_html( number_format( $e->ms / 1000, 1 ) ) . ' s' : '—'; ?></td>
					<td>
						<?php echo (int) $e->caidas_mes; ?>
						<?php if ( $e->segundos_caido ) : ?>
							<br><span style="color:#50575e;font-size:12px"><?php echo esc_html( SWPK_Monitor::duracion( (int) $e->segundos_caido ) ); ?> en total</span>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php SWPK_Vista::paginador( $pagina, $paginas, count( $filtrados ) ); ?>

		<?php if ( ! $en_pantalla ) : ?>
			<div class="notice notice-info inline"><p>Ningún sitio coincide con ese filtro.</p></div>
		<?php endif; ?>
		<?php SWPK_Vista::caja_cerrar(); ?>
	<?php else : ?>
		<div class="notice notice-info"><p>
			Todavía no hay sitios vigilados. El monitor usa los dominios del inventario: primero hay
			que correr la sincronización desde el Resumen.
		</p></div>
	<?php endif; ?>

	<?php SWPK_Vista::caja_abrir( 'El historial', '', 'swpk-c12', ! empty( $historial ) ); ?>
	<?php if ( ! $historial ) : ?>
		<p style="color:#50575e">
			No se ha caído nada desde que el monitor está encendido. Cada caída y cada recuperación
			queda aquí con su hora, y el aviso a soporte queda en la
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=panel-kinsta-correos&seccion=bitacora' ) ); ?>">el historial de envíos</a>.
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
			<?php foreach ( $historial as $h ) : ?>
				<tr>
					<td><?php echo esc_html( SWPK_Frescura::fmt( 'j M Y, H:i', $h->ocurrio_en ) ); ?></td>
					<td>
						<?php if ( SWPK_Eventos::SITIO_CAIDO === $h->tipo ) : ?>
							<span style="color:#a4161a">Se cayó</span>
						<?php else : ?>
							<span style="color:#0b6ba8">Volvió</span>
						<?php endif; ?>
					</td>
					<td><?php echo esc_html( $h->titulo ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
	<?php SWPK_Vista::caja_cerrar(); ?>

	<?php SWPK_Vista::rejilla_cerrar(); ?>

	<?php
	// El sello, la tira de perfil y los botones van AL PIE, como en la maqueta:
	// entre la cabecera y la primera tarjeta ella no pone nada. Arriba, el
	// sello solo —que son cuatro renglones de fechas— empujaba la tabla fuera
	// de la pantalla.
	?>
</div>
