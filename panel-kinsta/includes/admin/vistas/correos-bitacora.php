<?php
/**
 * La bitácora: qué se le notificó a quién, y cuándo.
 *
 * Contesta la pregunta del minuto 02:48: «desde tal fecha hasta esta fecha,
 * aquí está, se hicieron 10 notificaciones».
 *
 * Era una entrada propia del menú y ahora es la tercera pestaña de Correos,
 * porque el jefe lo dijo igual de claro: «la bitácora no son movimientos, es
 * la bitácora de los correos que se enviaron; debería estar ahí dentro».
 *
 * Este archivo es solo el cuerpo: el recuadro y las pestañas los pone
 * correos.php.
 */
defined( 'ABSPATH' ) || exit;

/** La dirección de esta misma pestaña, con lo que se le quiera añadir. */
$swpk_url_bitacora = static fn( array $extra = [] ) => add_query_arg(
	array_merge( [ 'page' => 'panel-kinsta-correos', 'seccion' => 'bitacora' ], $extra ),
	admin_url( 'admin.php' )
);

$ver = isset( $_GET['ver'] ) ? (int) $_GET['ver'] : 0;

if ( $ver ) {
	global $wpdb;
	$uno = $wpdb->get_row(
		$wpdb->prepare( 'SELECT * FROM ' . SWPK_Tablas::tabla( 'bitacora' ) . ' WHERE id = %d', $ver )
	);

	if ( $uno && ( SWPK_Perfiles::puede( SWPK_Perfiles::VER_TODO ) || SWPK_Perfiles::puede_ver_sitio( (string) $uno->sitio_id ) ) ) {
		SWPK_Vista::volver( esc_url( remove_query_arg( 'ver' ) ), 'Volver a la bitácora' );
		printf( '<h1>%s</h1>', esc_html( (string) $uno->asunto ) );
		printf(
			'<p style="color:#50575e">%s · a %s · <strong>%s</strong></p>',
			esc_html( SWPK_Frescura::fmt( 'j \d\e F \d\e Y, H:i', $uno->enviado_en ) ),
			esc_html( (string) $uno->destinatarios ?: 'nadie' ),
			esc_html( (string) $uno->resultado )
		);

		if ( $uno->error ) {
			printf( '<div class="notice notice-warning inline"><p>%s</p></div>', esc_html( (string) $uno->error ) );
		}

		echo '<div style="background:#fff;border:1px solid #c3c4c7;border-radius:6px;margin-top:16px;overflow:hidden">';
		// El cuerpo es HTML que generó este mismo plugin, no entrada de nadie.
		echo wp_kses_post( (string) $uno->cuerpo );
		echo '</div>';

		SWPK_Vista::volver( esc_url( remove_query_arg( 'ver' ) ), 'Volver a la bitácora' );
		return;
	}
}

$tipo_filtro = isset( $_GET['tipo'] ) ? sanitize_key( (string) $_GET['tipo'] ) : '';
$tipos       = SWPK_Bitacora::tipos();

// El rango, igual que en Movimientos: del día 1 del mes a hoy. Sin esto la
// pestaña solo sabía contestar "los últimos 400 avisos", que no es la pregunta
// que hace nadie.
$desde = isset( $_GET['desde'] ) && $_GET['desde']
	? sanitize_text_field( wp_unslash( (string) $_GET['desde'] ) )
	: current_time( 'Y-m-01' );

$hasta = isset( $_GET['hasta'] ) && $_GET['hasta']
	? sanitize_text_field( wp_unslash( (string) $_GET['hasta'] ) )
	: current_time( 'Y-m-d' );

$registros = SWPK_Bitacora::entre( $desde, $hasta, $tipo_filtro );

// Una asesora ve la constancia de SUS sitios. La bitácora es evidencia, y
// la evidencia que le sirve es la de los clientes que ella atiende. Los avisos
// de toda la cartera, que no son de ningún sitio, son de Dirección.
if ( ! SWPK_Perfiles::puede( SWPK_Perfiles::VER_TODO ) ) {
	$mios      = array_map( static fn( $s ) => (string) $s->sitio_id, SWPK_Perfiles::sitios_visibles() );
	$registros = array_filter( $registros, static fn( $r ) => in_array( (string) $r->sitio_id, $mios, true ) );
}

$registros = array_values( $registros );
[ $en_pantalla, $pagina, $paginas ] = SWPK_Vista::paginar( $registros, SWPK_Vista::pagina_pedida(), 25 );

// Para no mentir en la pantalla vacía. Ver abajo.
$hubo_alguna_vez = SWPK_Bitacora::hay_alguno();
?>

	<p style="max-width:820px;color:#3c434a">
		Todo lo que el panel notifica queda aquí con su fecha, su destinatario y su contenido. Esto
		es el respaldo: sirve para demostrar qué se avisó, a quién y cuándo.
	</p>

	<p style="max-width:820px;color:#3c434a">
		<strong>Ojo con qué es y qué no:</strong> esta pestaña guarda <em>lo que se avisó</em>, no
		<em>lo que pasó</em>. Si un sitio se actualizó y todavía nadie le escribió al cliente, el
		cambio está en <a href="<?php echo esc_url( admin_url( 'admin.php?page=panel-kinsta-movimientos' ) ); ?>">Historial</a>
		y aquí no aparece. Y cada aviso queda tal como salió: si después se corrige el dato que lo
		originó, el aviso se queda escrito igual — una constancia que se reescribe sola no sirve
		como constancia.
	</p>

	<div class="notice notice-warning inline" style="max-width:820px">
		<p>
			<strong>Una precisión que importa el día que alguien reclame:</strong> esta bitácora prueba
			que el panel <em>envió</em> el correo. Probar que <em>llegó</em> depende de que el SMTP del
			sitio dé acuse de entrega, y puede que no lo dé. Por eso la columna dice "enviado" y no
			"entregado".
		</p>
		<?php if ( SWPK_Correos::en_prueba() ) : ?>
			<p>
				Y ahora mismo el panel está en <strong>modo de prueba</strong>: lo que dice "De prueba"
				se armó pero no salió a nadie. Se puede abrir y leer con el botón de la derecha.
			</p>
		<?php endif; ?>
	</div>

	<?php // Los mismos campos que las demás barras del panel: «el buscar aquí es
	// diferente a todos los demás botones». ?>
	<form method="get" class="swpk-rango swpk-filtra">
		<input type="hidden" name="page" value="panel-kinsta-correos">
		<input type="hidden" name="seccion" value="bitacora">
		<input type="hidden" name="tipo" value="<?php echo esc_attr( $tipo_filtro ); ?>">

		<label class="swpk-campo">
			<span>Desde</span>
			<input type="date" name="desde" value="<?php echo esc_attr( $desde ); ?>">
		</label>

		<label class="swpk-campo">
			<span>Hasta</span>
			<input type="date" name="hasta" value="<?php echo esc_attr( $hasta ); ?>">
		</label>

		<button class="button swpk-filtra-ir">Filtrar</button>
	</form>

	<div class="swpk-filtros">
		<span class="swpk-filtros-t">Mostrar:</span>
		<?php foreach ( [ '' => 'Todo' ] + $tipos as $clave => $nombre ) : ?>
			<a class="swpk-filtro <?php echo $tipo_filtro === $clave ? 'swpk-filtro-activo' : ''; ?>"
				href="<?php echo esc_url( '' === $clave ? remove_query_arg( [ 'tipo', 'p' ] ) : add_query_arg( [ 'tipo' => $clave, 'p' => 1 ] ) ); ?>">
				<?php echo esc_html( $nombre ); ?>
			</a>
		<?php endforeach; ?>
	</div>

	<p class="swpk-linea">
		<strong><?php echo esc_html( SWPK_Vista::plural( count( $registros ), 'aviso', 'avisos' ) ); ?></strong>
		del <?php echo esc_html( SWPK_Frescura::fmt( 'j \d\e F', $desde . ' 12:00:00' ) ); ?>
		al <?php echo esc_html( SWPK_Frescura::fmt( 'j \d\e F', $hasta . ' 12:00:00' ) ); ?>
		<?php if ( '' !== $tipo_filtro ) : ?>
			<span class="swpk-gris">· solo <?php echo esc_html( strtolower( $tipos[ $tipo_filtro ] ?? $tipo_filtro ) ); ?></span>
		<?php endif; ?>
	</p>

	<p><?php SWPK_Admin::boton_exportar( 'bitacora' ); ?></p>

	<?php
	SWPK_Vista::caja_abrir(
		'Lo que se ha notificado',
		esc_html( SWPK_Vista::plural( count( $registros ), 'aviso', 'avisos' ) ),
		'',
		true
	);
	?>

	<?php SWPK_Vista::paginador( $pagina, $paginas, count( $registros ), 'avisos', 'aviso' ); ?>

	<?php if ( ! $registros ) : ?>
		<?php /* "Nunca ha salido nada" y "no hay nada en este rango" son dos cosas
		         distintas, y esta pantalla decía la primera en los dos casos. Para una
		         pantalla que es evidencia, decir de más es tan malo como decir de menos. */ ?>
		<div class="notice notice-info"><p>
			<?php if ( ! $hubo_alguna_vez ) : ?>
				Todavía no se ha enviado ningún aviso, de ninguna fecha. La bitácora empieza a
				llenarse cuando se activen los correos automáticos.
			<?php else : ?>
				<strong>Ningún aviso en este rango de fechas<?php echo '' !== $tipo_filtro ? ' con ese filtro' : ''; ?>.</strong>
				Sí hay avisos guardados de otras fechas: amplía el rango para verlos.
				<a href="<?php echo esc_url( $swpk_url_bitacora( [ 'desde' => '2020-01-01', 'hasta' => current_time( 'Y-m-d' ) ] ) ); ?>">Ver todos</a>.
			<?php endif; ?>
		</p></div>
	<?php else : ?>
		<table class="widefat striped swpk-tabla">
			<thead>
				<tr>
					<th style="width:140px">Cuándo</th>
					<th style="width:110px">Tipo</th>
					<th>Sitio</th>
					<th>A quién</th>
					<th>Asunto</th>
					<th style="width:120px">Resultado</th>
					<th style="width:90px"></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $en_pantalla as $r ) : ?>
				<?php $sitio = SWPK_Inventario::obtener( $r->sitio_id ); ?>
				<tr>
					<td><?php echo esc_html( SWPK_Frescura::fmt( 'j M Y, H:i', $r->enviado_en ) ); ?></td>
					<td><?php echo esc_html( $tipos[ $r->tipo ] ?? $r->tipo ); ?></td>
					<td><?php echo esc_html( $sitio->nombre_visible ?? '—' ); ?></td>
					<td class="swpk-mono"><?php echo esc_html( (string) $r->destinatarios ); ?></td>
					<td><?php echo esc_html( (string) $r->asunto ); ?></td>
					<td>
						<?php
						[ $texto_r, $color_r ] = match ( $r->resultado ) {
							'enviado'          => [ 'Enviado', '#008a20' ],
							'simulado'         => [ 'De prueba', '#2271b1' ],
							'apagado'          => [ 'No salió', '#8c8f94' ],
							'sin_destinatario' => [ 'Sin destinatario', '#dba617' ],
							'sin_novedad'      => [ 'Sin novedad', '#8c8f94' ],
							default            => [ 'Falló', '#d63638' ],
						};
						?>
						<span style="color:<?php echo esc_attr( $color_r ); ?>" title="<?php echo esc_attr( (string) $r->error ); ?>">
							<?php echo esc_html( $texto_r ); ?>
						</span>
					</td>
					<td>
						<?php if ( $r->cuerpo ) : ?>
							<a class="button button-small" href="<?php echo esc_url( add_query_arg( [ 'seccion' => 'bitacora', 'ver' => (int) $r->id ] ) ); ?>">Leerlo</a>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php SWPK_Vista::paginador( $pagina, $paginas, count( $registros ), 'avisos', 'aviso' ); ?>
	<?php endif; ?>
	<?php SWPK_Vista::caja_cerrar(); ?>
