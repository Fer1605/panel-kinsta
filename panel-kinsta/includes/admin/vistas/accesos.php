<?php
/** Accesos al panel: entradas, salidas e intentos fallidos. */
defined( 'ABSPATH' ) || exit;

$etiquetas = [
	SWPK_Accesos::ENTRADA => [ 'Entró', '#008a20' ],
	SWPK_Accesos::SALIDA  => [ 'Salió', '#50575e' ],
	SWPK_Accesos::FALLIDO => [ 'Intento fallido', '#d63638' ],
	SWPK_Accesos::BLOQUEO => [ 'Bloqueado por intentos', '#b32d2e' ],
	SWPK_Accesos::API     => [ 'Consulta por API', '#2271b1' ],
	SWPK_Accesos::API_NEGADO => [ 'API rechazada', '#d63638' ],
];

// Lo que ofrece el desplegable es el nombre de cada cosa, no su clave interna.
$acciones  = array_map( static fn( $e ) => $e[0], $etiquetas );
$filtro    = SWPK_Vista::opcion_pedida( 'accion', $acciones );
$registros = SWPK_Accesos::listar( 500, $filtro );

// Buscar por usuario o por dirección.
//
// Es el filtro que de verdad se usa aquí: cuando saltan cuarenta intentos
// fallidos, lo primero que uno quiere es ver si vienen todos de la misma
// dirección. La consulta ya trae como mucho 500 filas, así que se filtra aquí.
$busca_acceso = SWPK_Vista::busqueda_pedida();

if ( '' !== $busca_acceso ) {
	$aguja     = strtolower( remove_accents( $busca_acceso ) );
	$registros = array_values(
		array_filter(
			$registros,
			static function ( $r ) use ( $aguja ) {
				$pajar = strtolower( remove_accents( (string) $r->login . ' ' . (string) $r->ip ) );
				return str_contains( $pajar, $aguja );
			}
		)
	);
}

[ $en_pantalla, $pagina, $paginas ] = SWPK_Vista::paginar( $registros, SWPK_Vista::pagina_pedida(), 25 );
$fallidos  = SWPK_Accesos::fallidos_recientes( 24 );
?>
<div class="wrap">
	<h1>Accesos al panel</h1>
	<?php SWPK_Admin::mostrar_mensaje(); ?>

	<p style="max-width:840px;color:#3c434a">
		Quién entró al panel, cuándo entró y cuándo salió. También quedan los intentos fallidos:
		este es el sitio desde el que se consulta la información de todos los clientes, así que una
		ráfaga de intentos contra él es algo que conviene ver.
	</p>

	<?php if ( $fallidos >= 10 ) : ?>
		<div class="notice notice-error">
			<p>
				<strong>Hubo <?php echo (int) $fallidos; ?> intentos fallidos en las últimas 24 horas.</strong>
				Puede ser alguien que no se acuerda de su contraseña, o puede ser que estén probando.
				Vale la pena mirar de qué direcciones vienen.
			</p>
		</div>
	<?php endif; ?>

	<div class="swpk-tarjetas">
		<div class="swpk-tarjeta">
			<div class="swpk-n" style="color:<?php echo $fallidos ? '#d63638' : '#008a20'; ?>"><?php echo (int) $fallidos; ?></div>
			<div class="swpk-r">Intentos fallidos (24 h)</div>
		</div>
		<div class="swpk-tarjeta">
			<div class="swpk-n"><?php echo count( SWPK_Perfiles::asesoras() ); ?></div>
			<div class="swpk-r">Personas con acceso</div>
		</div>
	</div>

	<?php
	// Antes esto eran siete botones en fila, uno por cada cosa que puede pasar.
	// Ocupaban dos renglones, no se veía cuál estaba puesto y no había forma de
	// buscar una dirección. Ahora es la misma barra que el resto del panel.
	SWPK_Vista::barra_de_filtros(
		'panel-kinsta-accesos',
		[
			'accion' => [
				'etiqueta' => 'Qué pasó',
				'opciones' => $acciones,
				'actual'   => $filtro,
				'todas'    => 'Todo',
			],
		],
		[],
		$busca_acceso,
		'usuario o dirección IP…'
	);
	?>

	<?php SWPK_Vista::paginador( $pagina, $paginas, count( $registros ), 'registros', 'registro' ); ?>

	<?php if ( ! $registros ) : ?>
		<div class="notice notice-info"><p>
			<?php
			echo ( '' !== $filtro || '' !== $busca_acceso )
				? 'Ningún registro coincide con ese filtro.'
				: 'Todavía no hay registros. Se van a llenar solos en cuanto alguien entre o salga del panel.';
			?>
		</p></div>
	<?php else : ?>
		<table class="widefat striped swpk-tabla">
			<thead>
				<tr>
					<th style="width:160px">Cuándo</th>
					<th style="width:150px">Qué pasó</th>
					<th>Usuario</th>
					<th style="width:160px">Desde</th>
					<th>Navegador</th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $en_pantalla as $r ) : ?>
				<?php [ $texto, $color ] = $etiquetas[ $r->accion ] ?? [ $r->accion, '#50575e' ]; ?>
				<tr>
					<td><?php echo esc_html( SWPK_Frescura::fmt( 'j M Y, H:i:s', $r->ocurrio_en ) ); ?></td>
					<td><span style="color:<?php echo esc_attr( $color ); ?>;font-weight:600"><?php echo esc_html( $texto ); ?></span></td>
					<td>
						<?php echo esc_html( (string) $r->login ); ?>
						<?php if ( ! $r->usuario_id && SWPK_Accesos::FALLIDO === $r->accion ) : ?>
							<br><span style="color:#50575e;font-size:12px">ese usuario no existe</span>
						<?php endif; ?>
						<?php if ( ! empty( $r->detalle ) ) : ?>
							<br><code style="font-size:11px"><?php echo esc_html( (string) $r->detalle ); ?></code>
						<?php endif; ?>
					</td>
					<td class="swpk-mono"><?php echo esc_html( (string) $r->ip ?: '—' ); ?></td>
					<td style="color:#50575e;font-size:12px"><?php echo esc_html( mb_substr( (string) $r->agente, 0, 90 ) ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<p class="swpk-atribucion">
		No se guarda ninguna contraseña, ni siquiera las de los intentos fallidos: un registro de
		contraseñas intentadas es una lista de contraseñas reales de gente que se equivocó de campo.
	</p>
</div>
