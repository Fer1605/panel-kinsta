<?php
/**
 * Bitácora · Movimientos: todo lo que cambió, buscable y por rango de fechas.
 *
 * ─── Por qué esta pantalla ──────────────────────────────────────────────────
 *
 * La otra mitad de la bitácora guarda lo que se AVISÓ. Esto guarda lo que
 * PASÓ, que no es lo mismo y hasta ahora solo se veía en Inicio, y solo de los
 * últimos días.
 *
 * El jefe lo pidió en la reunión con estas palabras:
 *
 *   "Quiero un reporte de me diga, mira, este mes actualizamos seis veces tal
 *    proyecto. Los más actualizados, qué plugins fueron los que más se
 *    actualizaron, qué plugins fueron los que no."              (01:18)
 *
 *   "Quiero decir, oye, ¿sabes qué? Mira, de tal fecha a tal fecha."  (01:37)
 *
 * Y queda registrado aunque no se le haya avisado a nadie: los correos están
 * en modo de prueba y aun así todo lo que cambia se guarda aquí desde el
 * primer día. El día que se enciendan, lo de antes ya está escrito.
 */
defined( 'ABSPATH' ) || exit;

$desde = isset( $_GET['desde'] ) && $_GET['desde']
	? sanitize_text_field( wp_unslash( (string) $_GET['desde'] ) )
	: current_time( 'Y-m-01' );

$hasta = isset( $_GET['hasta'] ) && $_GET['hasta']
	? sanitize_text_field( wp_unslash( (string) $_GET['hasta'] ) )
	: current_time( 'Y-m-d' );

$tipo     = isset( $_GET['tipo'] ) ? sanitize_key( (string) $_GET['tipo'] ) : '';
$busqueda = SWPK_Vista::busqueda_pedida();

$sitios = [];
foreach ( SWPK_Perfiles::sitios_visibles() as $s ) {
	$sitios[ (string) $s->sitio_id ] = $s;
}

$puede_todo = SWPK_Perfiles::puede( SWPK_Perfiles::VER_TODO );

$movimientos = array_filter(
	SWPK_Eventos::entre( $desde, $hasta, '', $tipo ),
	static function ( $e ) use ( $sitios, $puede_todo, $busqueda ) {
		if ( ! $puede_todo && ! isset( $sitios[ (string) $e->sitio_id ] ) ) {
			return false;
		}
		if ( '' === $busqueda ) {
			return true;
		}
		$nombre = (string) ( $sitios[ (string) $e->sitio_id ]->nombre_visible ?? '' );
		return false !== stripos( $nombre . ' ' . $e->titulo, $busqueda );
	}
);

$movimientos = array_values( $movimientos );
[ $en_pantalla, $pagina, $paginas ] = SWPK_Vista::paginar( $movimientos, SWPK_Vista::pagina_pedida(), 50 );

$etq_evt = SWPK_Eventos::etiquetas();

// Cuántos de cada tipo, para la línea de arriba y para el desplegable.
$por_tipo = [];
foreach ( $movimientos as $e ) {
	$por_tipo[ $e->tipo ] = ( $por_tipo[ $e->tipo ] ?? 0 ) + 1;
}
arsort( $por_tipo );
?>

<p style="max-width:820px;color:#3c434a">
	Todo lo que cambió en los sitios, con su fecha. Esto se guarda <strong>pase lo que pase con
	los correos</strong>: aunque hoy no se le esté avisando a nadie, el movimiento queda escrito.
</p>

<form method="get" class="swpk-rango">
	<input type="hidden" name="page" value="panel-kinsta-bitacora">
	<input type="hidden" name="seccion" value="movimientos">
	<label>Desde<br><input type="date" name="desde" value="<?php echo esc_attr( $desde ); ?>"></label>
	<label>Hasta<br><input type="date" name="hasta" value="<?php echo esc_attr( $hasta ); ?>"></label>
	<label>Qué pasó<br>
		<select name="tipo">
			<option value="">Todo</option>
			<?php foreach ( $etq_evt as $clave => $nombre ) : ?>
				<option value="<?php echo esc_attr( $clave ); ?>" <?php selected( $tipo, $clave ); ?>>
					<?php echo esc_html( $nombre ); ?>
				</option>
			<?php endforeach; ?>
		</select>
	</label>
	<label>Sitio o módulo<br>
		<input type="search" name="q" value="<?php echo esc_attr( $busqueda ); ?>" placeholder="buscar…">
	</label>
	<button class="button">Filtrar</button>
	<?php if ( $tipo || '' !== $busqueda ) : ?>
		<a class="button" href="<?php echo esc_url( add_query_arg( [ 'page' => 'panel-kinsta-bitacora', 'seccion' => 'movimientos', 'desde' => $desde, 'hasta' => $hasta ], admin_url( 'admin.php' ) ) ); ?>">Quitar filtros</a>
	<?php endif; ?>
</form>

<p class="swpk-linea">
	<strong><?php echo esc_html( SWPK_Vista::plural( count( $movimientos ), 'movimiento', 'movimientos' ) ); ?></strong>
	del <?php echo esc_html( SWPK_Frescura::fmt( 'j \d\e F', $desde . ' 12:00:00' ) ); ?>
	al <?php echo esc_html( SWPK_Frescura::fmt( 'j \d\e F', $hasta . ' 12:00:00' ) ); ?>.
	<?php if ( $por_tipo ) : ?>
		<span class="swpk-gris">
			<?php
			$trozos = [];
			foreach ( array_slice( $por_tipo, 0, 5, true ) as $t => $n ) {
				$trozos[] = sprintf( '%d %s', $n, strtolower( $etq_evt[ $t ] ?? $t ) );
			}
			echo esc_html( implode( ' · ', $trozos ) );
			?>
		</span>
	<?php endif; ?>
</p>

<?php SWPK_Vista::paginador( $pagina, $paginas, count( $movimientos ), 'movimientos' ); ?>

<?php if ( ! $en_pantalla ) : ?>
	<div class="notice notice-info inline"><p>
		Ningún movimiento en ese periodo con esos filtros.
		<?php if ( ! SWPK_Frescura::corrida_al_dia() ) : ?>
			<strong class="swpk-rojo">Y ojo: la corrida no está pasando, así que puede que sí haya habido y no nos hayamos enterado.</strong>
		<?php endif; ?>
	</p></div>
<?php else : ?>
	<table class="widefat striped swpk-tabla">
		<thead>
			<tr>
				<th style="width:150px">Cuándo</th>
				<th style="width:200px">Sitio</th>
				<th style="width:130px">Grupo</th>
				<th style="width:170px">Qué pasó</th>
				<th>Detalle</th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ( $en_pantalla as $e ) : ?>
			<?php $s = $sitios[ (string) $e->sitio_id ] ?? null; ?>
			<tr>
				<td class="swpk-gris"><?php echo esc_html( SWPK_Frescura::fmt( 'j M Y, H:i', $e->ocurrio_en ) ); ?></td>
				<td>
					<?php if ( $s ) : ?>
						<a href="<?php echo esc_url( SWPK_Vista::url_del_sitio( (string) $e->sitio_id, 'panel-kinsta-bitacora' ) ); ?>">
							<?php echo esc_html( $s->nombre_visible ); ?>
						</a>
					<?php else : ?>
						<span class="swpk-gris">—</span>
					<?php endif; ?>
				</td>
				<td><?php echo $s ? SWPK_Admin::grupo_badge( (string) $s->tag ) : ''; ?></td>
				<td<?php echo 'critico' === $e->severidad ? ' class="swpk-rojo"' : ''; ?>>
					<?php echo esc_html( $etq_evt[ $e->tipo ] ?? $e->tipo ); ?>
				</td>
				<td><?php echo esc_html( $e->titulo ); ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>

	<?php SWPK_Vista::paginador( $pagina, $paginas, count( $movimientos ), 'movimientos' ); ?>
<?php endif; ?>

<p class="swpk-nota">
	Los movimientos empiezan el día que el panel entró a producción. Kinsta no guarda un
	historial que se pueda consultar hacia atrás, así que lo anterior a esa fecha no existe
	en ninguna parte — no es que falte aquí.
</p>
