<?php
/**
 * La ficha de un sitio: todo lo suyo en un solo lugar, y el mensaje para la
 * asesora listo para copiar.
 *
 * Se entra desde la lista de Sitios o desde la de Vulnerabilidades, y arriba
 * siempre hay un botón para volver a donde se venía, con el grupo y el filtro
 * que la persona había elegido.
 */
defined( 'ABSPATH' ) || exit;

$sitio_id = isset( $_GET['sitio'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['sitio'] ) ) : '';
$sitio    = $sitio_id ? SWPK_Inventario::obtener( $sitio_id ) : null;
$regreso  = SWPK_Vista::url_de_regreso();

if ( ! $sitio || ! SWPK_Perfiles::puede_ver_sitio( $sitio_id ) ) {
	echo '<div class="wrap"><h1>Sitio</h1>';
	SWPK_Vista::volver( $regreso['url'], $regreso['texto'] );
	echo '<div class="notice notice-error"><p>Ese sitio no existe o no está entre los tuyos.</p></div></div>';
	return;
}

$hallazgos = SWPK_Riesgo::hallazgos( $sitio_id );
$nivel     = SWPK_Riesgo::nivel_del_sitio( $sitio_id );

$con_vuln = array_values( array_filter( $hallazgos, static fn( $h ) => 'actualizacion_pendiente' !== $h['motivo'] ) );
$solo_act = array_values( array_filter( $hallazgos, static fn( $h ) => 'actualizacion_pendiente' === $h['motivo'] ) );

$plano = SWPK_Textos::para_asesora( $sitio, $hallazgos );
?>
<div class="wrap">
	<?php SWPK_Vista::volver( $regreso['url'], $regreso['texto'] ); ?>

	<?php
	// La ficha de un sitio no sale en la maqueta —ahí no hay por dónde entrar
	// a una— pero es a donde lleva cada nombre de cada tabla. Dejarla con la
	// pinta vieja hacía que el panel cambiara de cara al primer clic.
	SWPK_Vista::cabecera(
		(string) $sitio->nombre_visible,
		sprintf(
			'<a href="%s" target="_blank" rel="noopener" class="swpk-mono">%s</a>',
			esc_url( 'https://' . ltrim( $sitio->dominio, 'htps:/' ) ),
			esc_html( $sitio->dominio )
		),
		SWPK_Admin::grupo_badge( (string) $sitio->tag )
		. ' ' . SWPK_Admin::nivel_badge( $nivel )
	);
	?>

	<?php SWPK_Admin::mostrar_mensaje(); ?>
	<?php SWPK_Frescura::aviso(); ?>

	<p>
		<a class="button button-primary"
			href="<?php echo esc_url( add_query_arg( [ 'page' => 'panel-kinsta-reporte', 'sitio' => $sitio_id ] + SWPK_Vista::rastro(), admin_url( 'admin.php' ) ) ); ?>">
			Reporte para el cliente
		</a>
		<span style="color:#50575e;margin-left:8px">qué se le notificó y cuándo, listo para imprimir o guardar en PDF</span>
	</p>

	<?php if ( SWPK_Perfiles::puede( SWPK_Perfiles::ADMINISTRAR ) ) : ?>
		<p>
			<a class="button" href="<?php echo esc_url( SWPK_Admin::url_analizar( $sitio_id ) ); ?>">
				Analizar este sitio ahora
			</a>
			<span style="color:#50575e;margin-left:8px">
				vuelve a preguntarle a <?php echo esc_html( SWPK_Admin::proveedor() ); ?> qué tiene instalado
				y revisa si responde, sin esperar a la corrida de mañana
			</span>
		</p>
	<?php endif; ?>

	<div class="swpk-tarjetas">
		<div class="swpk-tarjeta">
			<div class="swpk-n" style="color:<?php echo count( $con_vuln ) ? '#b32d2e' : '#008a20'; ?>"><?php echo count( $con_vuln ); ?></div>
			<div class="swpk-r">Con vulnerabilidad conocida</div>
		</div>
		<div class="swpk-tarjeta">
			<div class="swpk-n"><?php echo count( $solo_act ); ?></div>
			<div class="swpk-r">Solo actualización pendiente</div>
		</div>
		<div class="swpk-tarjeta">
			<div class="swpk-n"><?php echo esc_html( $sitio->version_wp ?: '—' ); ?></div>
			<div class="swpk-r">WordPress</div>
		</div>
		<div class="swpk-tarjeta">
			<div class="swpk-n"><?php echo esc_html( $sitio->version_php ?: '—' ); ?></div>
			<div class="swpk-r">PHP</div>
		</div>
		<?php if ( SWPK_Etiquetas::es_nuestro( $sitio->tag ) ) : ?>
			<div class="swpk-tarjeta">
				<div class="swpk-n" style="font-size:19px;padding-top:6px;color:<?php echo $sitio->tiene_2fa ? '#008a20' : '#d63638'; ?>">
					<?php echo $sitio->tiene_2fa ? '✓ Activa' : 'Pendiente'; ?>
				</div>
				<div class="swpk-r">Doble autenticación</div>
			</div>
		<?php endif; ?>
	</div>

	<?php $dias = SWPK_Inventario::dias_sin_escanear( $sitio ); ?>

	<?php if ( null === $dias ) : ?>
		<div class="notice notice-warning">
			<p>
				<strong>Este sitio no ha sido escaneado por <?php echo esc_html( SWPK_Admin::proveedor() ); ?></strong>,
				así que su lista de módulos llega vacía. Una lista vacía no significa que el sitio esté
				limpio: significa que no sabemos qué tiene.
			</p>
			<p>
				Esa lista la arma <?php echo esc_html( SWPK_Admin::proveedor() ); ?> por su cuenta, sitio por
				sitio y cada uno a su ritmo. Normalmente aparece sola en unos días. Si pasa una semana
				así, vale la pena abrirles un ticket.
			</p>
		</div>
	<?php elseif ( SWPK_Inventario::dato_viejo( $sitio ) ) : ?>
		<div class="notice notice-warning">
			<p>
				<strong>Este dato tiene <?php echo (int) $dias; ?> días.</strong>
				No ha vuelto a ser revisado por <?php echo esc_html( SWPK_Admin::proveedor() ); ?> desde el
				<?php echo esc_html( SWPK_Frescura::fmt( 'j \d\e F', (string) $sitio->inventario_en ) ); ?>, así que
				lo de abajo puede estar desactualizado.
			</p>
		</div>
	<?php endif; ?>

	<?php SWPK_Frescura::sello( $sitio ); ?>

	<?php SWPK_Vista::rejilla_abrir(); ?>

	<?php if ( $hallazgos ) : ?>
		<?php SWPK_Vista::caja_abrir( 'El mensaje para la asesora', 'listo para pegar', 'swpk-c12' ); ?>
		<p style="color:#50575e;margin-top:0">
			Escrito para que el cliente entienda qué se juega, no solo que hay que actualizar.
		</p>

		<div class="swpk-mensaje">
			<?php
			// Con 23 módulos el mensaje mide dos pantallas y empuja todo lo demás
			// fuera de la vista. Se deja en una caja con su propio scroll: se lee
			// completo si se quiere, pero no se come la pantalla.
			$lineas_largo = substr_count( $plano, "\n" ) > 28;
			?>
			<pre class="swpk-plano <?php echo $lineas_largo ? 'swpk-plano-largo' : ''; ?>"><?php echo esc_html( $plano ); ?></pre>
			<?php if ( $lineas_largo ) : ?>
				<p style="color:#50575e;font-size:12px;margin:8px 0 0">
					El mensaje sigue dentro de la caja. El botón copia el texto completo, no solo lo que se ve.
				</p>
			<?php endif; ?>
			<textarea id="swpk-plano" style="position:absolute;left:-9999px" readonly><?php echo esc_textarea( $plano ); ?></textarea>
			<p class="swpk-copiar">
				<button type="button" class="button button-primary"
					onclick="var t=document.getElementById('swpk-plano');t.select();document.execCommand('copy');this.textContent='✓ Copiado';">
					Copiar el texto
				</button>
				<span style="color:#50575e;margin-left:8px">listo para pegar en una cotización o un correo</span>
			</p>
		</div>

		<?php SWPK_Vista::caja_cerrar(); ?>

		<?php
		SWPK_Vista::caja_abrir(
			'Todo lo que tiene pendiente',
			esc_html( SWPK_Vista::plural( count( $hallazgos ), 'módulo', 'módulos' ) ),
			'swpk-c12',
			true
		);
		?>
		<table class="widefat striped swpk-tabla">
			<thead>
				<tr>
					<th>Módulo</th>
					<th style="width:160px">Versión</th>
					<th style="width:95px">Nivel</th>
					<th style="width:230px">Quién puede aprovecharlo</th>
					<th>Qué permite</th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $hallazgos as $h ) : ?>
				<?php $v = $h['vulnerabilidades'][0] ?? null; ?>
				<tr>
					<td>
						<strong><?php echo esc_html( SWPK_Textos::limpiar_titulo( (string) $h['titulo'] ) ); ?></strong><br>
						<span class="swpk-mono" style="color:#50575e"><?php echo esc_html( $h['slug'] ); ?></span>
						<?php if ( '' === $h['auto_update'] ) : ?>
							<br><span style="color:#dba617;font-size:12px">sin actualización automática</span>
						<?php endif; ?>
					</td>
					<td>
						<span class="swpk-mono"><?php echo esc_html( $h['version'] ); ?></span>
						<?php if ( '' !== $h['version_disponible'] ) : ?>
							→ <span class="swpk-mono" style="color:#008a20"><?php echo esc_html( $h['version_disponible'] ); ?></span>
						<?php endif; ?>
					</td>
					<td><?php echo wp_kses_post( SWPK_Admin::nivel_badge( $h['nivel'] ) ); ?></td>
					<td>
						<?php if ( $v ) : ?>
							<?php $quien = SWPK_Textos::quien_puede( (string) ( $v->cvss_vector ?? '' ), (string) $v->descripcion ); ?>
							<span style="color:<?php echo $quien['sin_cuenta'] ? '#b32d2e' : '#1d2327'; ?>">
								<?php echo esc_html( $quien['frase'] ); ?>
							</span>
						<?php else : ?>
							<span style="color:#50575e">—</span>
						<?php endif; ?>
					</td>
					<td>
						<?php if ( $v ) : ?>
							<?php echo esc_html( SWPK_Textos::explicar( (string) $v->titulo, (string) $v->descripcion ) ); ?>
							<?php if ( '' !== (string) $v->cve ) : ?>
								<br><span class="swpk-mono" style="color:#50575e"><?php echo esc_html( (string) $v->cve ); ?></span>
							<?php endif; ?>
						<?php elseif ( 'vulnerable_sin_detalle' === $h['motivo'] ) : ?>
							<span style="color:#d63638">El hosting la marca como vulnerable</span>,
							<span style="color:#50575e">a la espera del detalle público.</span>
						<?php else : ?>
							<span style="color:#50575e">Solo actualización pendiente, sin vulnerabilidad conocida.</span>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php SWPK_Vista::caja_cerrar(); ?>
	<?php elseif ( SWPK_Riesgo::SIN_DATOS === $nivel ) : ?>
		<?php SWPK_Vista::caja_abrir( 'Todo lo que tiene pendiente', 'sin datos', 'swpk-c12' ); ?>
		<p style="margin:0">
			<strong>Aquí no hay nada que mostrar, y eso no es una buena noticia.</strong>
			No es que este sitio esté al día: es que no tenemos su lista de módulos, así que no
			hay nada contra qué comparar.
		</p>
		<?php SWPK_Vista::caja_cerrar(); ?>
	<?php else : ?>
		<?php SWPK_Vista::caja_abrir( 'Todo lo que tiene pendiente', 'nada', 'swpk-c12' ); ?>
		<p style="margin:0;color:var(--swpk-bien);font-weight:600">
			Este sitio no tiene nada pendiente: todos sus módulos están al día y ninguno tiene una
			vulnerabilidad conocida.
		</p>
		<?php SWPK_Vista::caja_cerrar(); ?>
	<?php endif; ?>

	<?php
	// ── Las normas de seguridad de este sitio ───────────────────────────────
	//
	// Va en la ficha y no solo en el tablero porque es aquí donde se marcan a
	// mano las declarativas: el honeypot de ESTE sitio lo revisa alguien que
	// está mirando ESTE sitio, no una casilla en una pantalla de 107 filas.
	$normas_activas = SWPK_Normas::activas();
	$cumple_sitio   = SWPK_Normas::del_sitio( $sitio_id );
	?>

	<?php if ( $normas_activas ) : ?>
		<?php SWPK_Vista::caja_abrir( 'Las normas de seguridad', '', 'swpk-c12', true ); ?>

		<?php
		$agente_sitio = SWPK_Ordenes::agente_de( $sitio_id );
		$es_cliente   = SWPK_Etiquetas::NO_UPDATE === (string) $sitio->tag;
		?>

		<?php if ( ! $agente_sitio ) : ?>
			<div class="notice notice-warning inline">
				<p>
					<strong>Este sitio no tiene el agente instalado</strong>, así que casi ninguna de estas normas
					se puede comprobar: son cosas que solo se ven desde dentro del sitio. Lo que sigue dice «sin
					medir», que no es lo mismo que «está bien».
				</p>
			</div>
		<?php endif; ?>

		<?php
		// Las 73 normas de corrido en la ficha de cada sitio son el mismo pozo
		// que había en «Qué revisamos». Se divide por tema y se pagina, y con
		// las mismas pastillas, para que las dos pantallas se lean igual.
		$aplicables = [];
		foreach ( $normas_activas as $n ) {
			if ( SWPK_Normas::aplica( $n, (string) $sitio->tag ) ) {
				$aplicables[] = $n;
			}
		}

		$por_tema = [];
		foreach ( $aplicables as $n ) {
			$g = (string) ( $n->grupo ?? '' );
			$por_tema[ '' !== $g ? $g : 'Sin clasificar' ][] = $n;
		}

		$temas = [];
		foreach ( SWPK_Normas::grupos() as $g ) {
			if ( isset( $por_tema[ $g ] ) ) {
				$temas[] = $g;
			}
		}
		foreach ( array_keys( $por_tema ) as $g ) {
			if ( ! in_array( $g, $temas, true ) ) {
				$temas[] = $g;
			}
		}

		$tema_pedido = isset( $_GET['tema'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['tema'] ) ) : '';
		$hay_tema    = '' !== $tema_pedido && isset( $por_tema[ $tema_pedido ] );
		$para_ver    = $hay_tema ? $por_tema[ $tema_pedido ] : $aplicables;

		$url_tema = static fn( string $g ) => add_query_arg(
			array_filter( [
				'page'  => 'panel-kinsta-sitio',
				'sitio' => (string) $sitio->sitio_id,
				'tema'  => $g,
			] ),
			admin_url( 'admin.php' )
		);

		$pastillas_tema = [ [
			'texto'  => 'Todas',
			'n'      => count( $aplicables ),
			'url'    => $url_tema( '' ),
			'activa' => ! $hay_tema,
		] ];

		foreach ( $temas as $g ) {
			$pastillas_tema[] = [
				'texto'  => $g,
				'n'      => count( $por_tema[ $g ] ),
				'url'    => $url_tema( $g ),
				'activa' => $hay_tema && $tema_pedido === $g,
			];
		}

		[ $normas_pagina, $pag_nor, $pags_nor ] = SWPK_Vista::paginar( $para_ver, SWPK_Vista::pagina_pedida(), 12 );
		?>
		<div class="swpk-pastillas" style="margin:0 0 12px"><?php echo SWPK_Vista::pastillas( $pastillas_tema ); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>

		<table class="widefat striped" style="max-width:1000px">
			<thead>
				<tr>
					<th style="width:34%">Norma</th>
					<th style="width:12%">Cómo está</th>
					<th style="width:36%">Detalle</th>
					<th style="width:18%"></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $normas_pagina as $n ) : ?>
				<?php
				$clave = (string) $n->clave;

				$fila   = $cumple_sitio[ $clave ] ?? null;
				$estado = $fila ? (string) $fila->estado : SWPK_Normas::SIN_DATO;
				$orden  = SWPK_Ordenes::orden_de_la_norma( $clave );
				?>
				<tr>
					<td>
						<strong><?php echo esc_html( (string) $n->nombre ); ?></strong>
						<span class="swpk-etq swpk-sev-<?php echo esc_attr( (string) $n->severidad ); ?>"><?php
							echo esc_html( ucfirst( (string) $n->severidad ) );
						?></span>
					</td>
					<td>
						<span class="swpk-etq swpk-celda-<?php echo esc_attr( $estado ); ?>"><?php
							echo esc_html( SWPK_Normas::etiqueta( $estado ) );
						?></span>
					</td>
					<td>
						<?php echo esc_html( $fila ? (string) $fila->detalle : 'Todavía no se ha medido.' ); ?>
						<?php if ( $fila && $fila->medido_en ) : ?>
							<br><span class="swpk-mudo">
								<?php
								if ( (int) $fila->revisado_por > 0 ) {
									$quien = get_userdata( (int) $fila->revisado_por );
									printf(
										'Marcado por %s el %s',
										esc_html( $quien ? $quien->display_name : 'alguien' ),
										esc_html( SWPK_Frescura::fmt( 'j/n/Y H:i', (string) $fila->medido_en ) )
									);
								} else {
									printf(
										'Medido el %s',
										esc_html( SWPK_Frescura::fmt( 'j/n/Y H:i', (string) $fila->medido_en ) )
									);
								}
								?>
							</span>
						<?php endif; ?>
					</td>
					<td style="text-align:right">
						<?php if ( ! SWPK_Perfiles::puede( SWPK_Perfiles::ADMINISTRAR ) ) : ?>
							&mdash;
						<?php elseif ( 'declarativa' === (string) $n->comprobador ) : ?>
							<a class="button button-small" href="<?php
								echo esc_url( SWPK_Admin::url_marcar_norma( $clave, $sitio_id, SWPK_Normas::CUMPLE ) );
							?>">Cumple</a>
							<a class="button button-small" href="<?php
								echo esc_url( SWPK_Admin::url_marcar_norma( $clave, $sitio_id, SWPK_Normas::FALLA ) );
							?>">No</a>
						<?php elseif ( SWPK_Normas::FALLA === $estado && '' !== $orden && (int) $n->arreglable === 1 && ! $es_cliente ) : ?>
							<a class="button button-small" href="<?php
								echo esc_url( SWPK_Admin::url_arreglar( $clave, $sitio_id ) );
							?>">Arreglar</a>
						<?php elseif ( SWPK_Normas::FALLA === $estado && $es_cliente ) : ?>
							<span class="swpk-mudo" title="Sitio del cliente: se mide, no se cambia.">Es del cliente</span>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php SWPK_Vista::paginador( $pag_nor, $pags_nor, count( $para_ver ), 'normas', 'norma' ); ?>
		<?php SWPK_Vista::caja_cerrar(); ?>
	<?php endif; ?>

	<?php SWPK_Vista::rejilla_cerrar(); ?>

	<?php SWPK_Vista::volver( $regreso['url'], $regreso['texto'] ); ?>

	<p class="swpk-atribucion"><?php echo wp_kses_post( SWPK_Wordfence::atribucion() ); ?></p>
</div>
