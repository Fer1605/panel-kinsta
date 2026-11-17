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
$abajo = $raro = $lentos = 0;
foreach ( $estados as $e ) {
	if ( 'abajo' === $e->estado ) { ++$abajo; }
	if ( 'raro' === $e->estado ) { ++$raro; }
	if ( 'arriba' === $e->estado && $e->ms > 5000 ) { ++$lentos; }
}

// «Lento» no es un estado guardado: es responder bien pero tardando más de
// cinco segundos. Se filtra igual porque es justo lo que uno quiere mirar
// cuando un cliente dice que su sitio va pesado.
$estados_posibles = [
	'abajo'  => 'Sin responder',
	'raro'   => 'Respuesta rara (4xx)',
	'lento'  => 'Responde, pero lento',
	'arriba' => 'Responde bien',
];

// Cuánto tarda, en tres tramos. Es el tercer desplegable de la maqueta y
// contesta algo que el estado solo no contesta: un sitio puede responder bien
// y aun así tardar ocho segundos, que es de lo que se queja el cliente.
$tramos = [
	'rapido' => 'Menos de 1 s',
	'medio'  => 'Entre 1 y 5 s',
	'tardon' => 'Más de 5 s',
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
			if ( 'lento' === $estado_pedido ) {
				if ( 'arriba' !== $e->estado || $e->ms <= 5000 ) {
					return false;
				}
			} elseif ( '' !== $estado_pedido && (string) $e->estado !== $estado_pedido ) {
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
				if ( 'medio' === $tramo_pedido && ( $ms < 1000 || $ms > 5000 ) ) {
					return false;
				}
				if ( 'tardon' === $tramo_pedido && $ms <= 5000 ) {
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
		esc_html( sprintf( 'El panel sondea los %d sitios cada cinco minutos.', count( $estados ) ) ),
		SWPK_Vista::pastillas( [
			[
				'texto'  => $al_dia ? 'Sondeo automático' : 'El sondeo no está pasando',
				'url'    => admin_url( 'admin.php?page=panel-kinsta-ajustes' ),
				'activa' => ! $al_dia,
			],
		] )
	);
	?>
	<?php SWPK_Admin::mostrar_mensaje(); ?>

	<p style="max-width:840px;color:#3c434a">
		El panel revisa cada sitio cada cinco minutos desde este servidor, por su cuenta. No depende
		de que el hosting avise: un aviso que depende de Kinsta no sirve para enterarse de que Kinsta
		se cayó. Por eso el panel vive fuera.
	</p>

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
				WP-Cron se dispara con las visitas al sitio, y a este panel casi nadie entra. Un monitor
				que no corre es peor que no tener monitor, porque da falsa tranquilidad. En producción
				hay que apagar WP-Cron y poner una tarea programada de verdad en Plesk:
			</p>
			<p><code>define( 'DISABLE_WP_CRON', true );</code> en wp-config.php, y en Plesk, cada 5 minutos:<br>
				<code>/usr/bin/php /ruta/al/sitio/wp-cron.php &gt;/dev/null 2&gt;&amp;1</code></p>
		</div>
	<?php endif; ?>

	<p>
		<?php if ( SWPK_Perfiles::puede( SWPK_Perfiles::ADMINISTRAR ) ) : ?>
			<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=swpk_sondear' ), 'swpk_sondear' ) ); ?>"
				class="button button-primary">Sondear ahora</a>
		<?php endif; ?>
		<?php SWPK_Admin::boton_exportar( 'caidas' ); ?>
		<span style="margin-left:10px;color:#50575e">
			<?php
			echo ! empty( $ultimo['cuando'] )
				? sprintf(
					'Último sondeo: %s · %d sitios · %d sin responder',
					esc_html( SWPK_Frescura::fmt( 'j M, H:i', (string) $ultimo['cuando'] ) ),
					(int) ( $ultimo['sondeados'] ?? 0 ),
					(int) ( $ultimo['abajo'] ?? 0 )
				)
				: 'Todavía no se ha sondeado.';
			?>
		</span>
	</p>

	<?php SWPK_Vista::rejilla_abrir(); ?>

	<?php if ( $estados ) : ?>
		<?php SWPK_Vista::caja_abrir( 'Cómo están ahora', esc_html( SWPK_Frescura::fmt( 'j M, H:i', current_time( 'mysql' ) ) ), 'swpk-c5' ); ?>
		<div class="swpk-tarjetas">
			<div class="swpk-tarjeta">
				<div class="swpk-n" style="color:<?php echo $abajo ? '#b32d2e' : '#008a20'; ?>"><?php echo (int) $abajo; ?></div>
				<div class="swpk-r">Sin responder ahora</div>
			</div>
			<div class="swpk-tarjeta">
				<div class="swpk-n"><?php echo (int) $raro; ?></div>
				<div class="swpk-r">Respuesta rara (4xx)</div>
			</div>
			<div class="swpk-tarjeta">
				<div class="swpk-n"><?php echo (int) $lentos; ?></div>
				<div class="swpk-r">Lentos (más de 5 s)</div>
			</div>
			<div class="swpk-tarjeta">
				<div class="swpk-n"><?php echo count( $estados ); ?></div>
				<div class="swpk-r">Sitios vigilados</div>
			</div>
		</div>
		<?php SWPK_Vista::caja_cerrar(); ?>

		<?php
		// Las cuatro cifras de arriba cuentan SIEMPRE los 108, pase lo que pase
		// con el filtro de abajo. Es a propósito: son el estado de la cartera,
		// no el de la página que estás mirando.
		SWPK_Vista::caja_abrir(
			'Sitio por sitio',
			esc_html( SWPK_Vista::plural( count( $filtrados ), 'sitio', 'sitios' ) ),
			'swpk-c7',
			true
		);

		SWPK_Vista::barra_de_filtros(
			'panel-kinsta-caidas',
			[
				'estado' => [
					'etiqueta' => 'Cómo responde',
					'opciones' => $estados_posibles,
					'actual'   => $estado_pedido,
					'todas'    => 'Como sea',
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
					'todas'    => 'Tarde lo que tarde',
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
					<th style="width:110px">Ahora</th>
					<th style="width:80px">Respuesta</th>
					<th style="width:90px">Tarda</th>
					<th>Detalle</th>
					<th style="width:150px">Desde</th>
					<th style="width:120px">Caídas este mes</th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $en_pantalla as $e ) : ?>
				<?php
				$sitio = SWPK_Inventario::obtener( $e->sitio_id );
				[ $color, $texto ] = match ( $e->estado ) {
					'abajo'  => [ '#b32d2e', 'Sin responder' ],
					'raro'   => [ '#dba617', 'Respuesta rara' ],
					'arriba' => [ '#008a20', 'Responde' ],
					default  => [ '#50575e', 'Sin datos' ],
				};
				?>
				<tr>
					<td>
						<strong><?php echo esc_html( $sitio->nombre_visible ?? $e->sitio_id ); ?></strong><br>
						<a class="swpk-mono" style="color:#50575e" href="<?php echo esc_url( $e->url ); ?>" target="_blank" rel="noopener">
							<?php echo esc_html( $e->url ); ?>
						</a>
					</td>
					<td><?php echo SWPK_Admin::grupo_badge( $grupo_de[ (string) $e->sitio_id ] ?? '' ); ?></td>
					<td><span class="swpk-nivel" style="background:<?php echo esc_attr( $color ); ?>;color:#fff;border-color:<?php echo esc_attr( $color ); ?>"><?php echo esc_html( $texto ); ?></span></td>
					<td class="swpk-mono"><?php echo $e->codigo ? (int) $e->codigo : '—'; ?></td>
					<td class="swpk-mono"><?php echo $e->ms ? esc_html( number_format( $e->ms / 1000, 2 ) ) . ' s' : '—'; ?></td>
					<td style="color:<?php echo 'abajo' === $e->estado ? '#b32d2e' : '#50575e'; ?>">
						<?php echo esc_html( (string) $e->motivo ); ?>
					</td>
					<td><?php echo $e->desde ? esc_html( SWPK_Frescura::fmt( 'j M, H:i', $e->desde ) ) : '—'; ?></td>
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
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=panel-kinsta-bitacora' ) ); ?>">bitácora</a>.
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
							<span style="color:#b32d2e">Se cayó</span>
						<?php else : ?>
							<span style="color:#008a20">Volvió</span>
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
	<?php SWPK_Frescura::sello(); ?>
</div>
