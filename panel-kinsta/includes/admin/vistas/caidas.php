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

$estado_pedido = SWPK_Vista::opcion_pedida( 'estado', $estados_posibles );
$busca_caida   = SWPK_Vista::busqueda_pedida();

$filtrados = $estados;

if ( '' !== $estado_pedido || '' !== $busca_caida ) {
	$aguja     = strtolower( remove_accents( $busca_caida ) );
	$filtrados = array_values(
		array_filter(
			$estados,
			static function ( $e ) use ( $estado_pedido, $aguja ) {
				if ( 'lento' === $estado_pedido ) {
					if ( 'arriba' !== $e->estado || $e->ms <= 5000 ) {
						return false;
					}
				} elseif ( '' !== $estado_pedido && (string) $e->estado !== $estado_pedido ) {
					return false;
				}

				if ( '' === $aguja ) {
					return true;
				}

				$sitio = SWPK_Inventario::obtener( (string) $e->sitio_id );
				$pajar = strtolower( remove_accents( (string) ( $sitio->nombre_visible ?? '' ) . ' ' . (string) $e->url ) );
				return str_contains( $pajar, $aguja );
			}
		)
	);
}

[ $en_pantalla, $pagina, $paginas ] = SWPK_Vista::paginar( $filtrados, SWPK_Vista::pagina_pedida(), 25 );

$historial = array_filter(
	SWPK_Eventos::recientes( 40 ),
	static fn( $e ) => in_array( $e->tipo, [ SWPK_Eventos::SITIO_CAIDO, SWPK_Eventos::SITIO_REPUESTO ], true )
);
?>
<div class="wrap">
	<h1>Caídas</h1>
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

	<?php SWPK_Frescura::sello(); ?>

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

	<?php if ( $estados ) : ?>
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

		<h2>Estado ahora</h2>

		<?php
		SWPK_Vista::barra_de_filtros(
			'panel-kinsta-caidas',
			[
				'estado' => [
					'etiqueta' => 'Cómo responde',
					'opciones' => $estados_posibles,
					'actual'   => $estado_pedido,
					'todas'    => 'Como sea',
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
					<th style="width:110px">Estado</th>
					<th style="width:80px">Respuesta</th>
					<th style="width:90px">Tiempo</th>
					<th>Detalle</th>
					<th style="width:150px">Desde</th>
					<th style="width:120px">Caídas del mes</th>
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
					<td><span class="swpk-nivel" style="background:<?php echo esc_attr( $color ); ?>"><?php echo esc_html( $texto ); ?></span></td>
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
	<?php else : ?>
		<div class="notice notice-info"><p>
			Todavía no hay sitios vigilados. El monitor usa los dominios del inventario: primero hay
			que correr la sincronización desde el Resumen.
		</p></div>
	<?php endif; ?>

	<h2>Historial</h2>
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
</div>
