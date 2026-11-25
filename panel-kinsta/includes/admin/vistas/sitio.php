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

// El filtro al que llevan los dos números de arriba. `$hallazgos` se recorta
// solo para la tabla; los contadores siguen contando el total, porque si
// cambiaran al pulsarlos dejarían de servir para saltar de uno a otro.
$solo = isset( $_GET['solo'] ) ? sanitize_key( (string) $_GET['solo'] ) : '';

if ( 'vuln' === $solo ) {
	$hallazgos = $con_vuln;
} elseif ( 'act' === $solo ) {
	$hallazgos = $solo_act;
}

$plano = SWPK_Textos::para_asesora( $sitio, $hallazgos );

// ── Las pestañas de la ficha ────────────────────────────────────────────────
//
// La ficha era una sola página larga y el jefe se perdió en ella: «aquí lo que
// yo hago es navegar todo donde mismo», «cero experiencia de usuario». Pidió
// pestañas y hasta las nombró: «normas de seguridad, resumen del proyecto,
// actualizaciones pendientes críticas, características del proyecto».
//
// Se entra por lo que hay que hacer, que es a lo que se viene.
$seccion = isset( $_GET['seccion'] ) ? sanitize_key( (string) $_GET['seccion'] ) : 'pendiente';

if ( ! in_array( $seccion, [ 'pendiente', 'normas', 'datos' ], true ) ) {
	$seccion = 'pendiente';
}

$url_pestana = static fn( string $s ) => add_query_arg(
	[ 'page' => 'panel-kinsta-sitio', 'sitio' => $sitio_id, 'seccion' => $s ] + SWPK_Vista::rastro(),
	admin_url( 'admin.php' )
);
?>
<div class="wrap">
	<?php SWPK_Vista::volver( $regreso['url'], $regreso['texto'] ); ?>

	<?php
	// La ficha de un sitio no sale en la maqueta —ahí no hay por dónde entrar
	// a una— pero es a donde lleva cada nombre de cada tabla. Dejarla con la
	// pinta vieja hacía que el panel cambiara de cara al primer clic.
	// ── Que se note en qué pestaña estás ────────────────────────────────
	//
	// Las pestañas cambiaban el contenido y nada más: ni el título ni la
	// bajada se movían. «Cambia el panel, pero no me doy cuenta porque no hay
	// nada que me lo indique; debería cambiar el nombre de la página.» Así
	// que el título dice el sitio Y la pestaña, y la bajada dice qué se está
	// mirando.
	$que_miro = match ( $seccion ) {
		'normas' => 'Las normas de seguridad de este sitio: cuáles cumple, cuáles no y cuáles no se pueden medir.',
		'datos'  => 'Los datos del sitio: versiones, quién lo atiende y a quién se le escribe.',
		default  => 'Lo que este sitio tiene pendiente: fallas conocidas y actualizaciones.',
	};

	$cual_pestana = match ( $seccion ) {
		'normas' => 'Normas de seguridad',
		'datos'  => 'Datos del sitio',
		default  => 'Lo que tiene pendiente',
	};

	SWPK_Vista::cabecera(
		$sitio->nombre_visible . ' · ' . $cual_pestana,
		sprintf(
			'%s<br><a href="%s" target="_blank" rel="noopener" class="swpk-mono">%s</a>',
			esc_html( $que_miro ),
			esc_url( 'https://' . ltrim( $sitio->dominio, 'htps:/' ) ),
			esc_html( $sitio->dominio )
		),
		SWPK_Admin::grupo_badge( SWPK_Inventario::grupo_de( $sitio ) )
		. ' ' . SWPK_Admin::nivel_badge( $nivel )
		. ' ' . SWPK_Vista::pastillas( [
			[
				'texto'  => 'Lo que tiene pendiente',
				'n'      => $hallazgos ? count( $hallazgos ) : '',
				'url'    => $url_pestana( 'pendiente' ),
				'activa' => 'pendiente' === $seccion,
			],
			[
				'texto'  => 'Normas de seguridad',
				'url'    => $url_pestana( 'normas' ),
				'activa' => 'normas' === $seccion,
			],
			[
				'texto'  => 'Datos del sitio',
				'url'    => $url_pestana( 'datos' ),
				'activa' => 'datos' === $seccion,
			],
		] )
	);
	?>

	<?php SWPK_Admin::mostrar_mensaje(); ?>
	<?php SWPK_Frescura::aviso(); ?>

	<?php
	// Los dos botones en una fila, cada uno con su burbuja. Antes cada uno iba
	// en su renglón con un párrafo al lado explicando qué hacía, y entre los
	// dos se comían el alto de la pantalla antes de enseñar un solo dato.
	?>
	<p class="swpk-acciones-ficha">
		<a class="button button-primary"
			href="<?php echo esc_url( add_query_arg( [ 'page' => 'panel-kinsta-reporte', 'sitio' => $sitio_id ] + SWPK_Vista::rastro(), admin_url( 'admin.php' ) ) ); ?>">
			Reporte para el cliente
		</a>
		<?php echo wp_kses_post( SWPK_Vista::ayuda(
			'Qué se le notificó a este cliente y cuándo, en una hoja lista para imprimir o guardar en PDF.'
		) ); ?>

		<?php if ( SWPK_Perfiles::puede( SWPK_Perfiles::ADMINISTRAR ) ) : ?>
			<a class="button" href="<?php echo esc_url( SWPK_Admin::url_analizar( $sitio_id ) ); ?>">
				Analizar este sitio ahora
			</a>
			<?php echo wp_kses_post( SWPK_Vista::ayuda( sprintf(
				'Vuelve a preguntarle a %s qué tiene instalado este sitio y revisa si responde, sin '
				. 'esperar a la corrida de mañana. Tarda unos segundos y no toca los demás sitios.',
				SWPK_Admin::proveedor()
			) ) ); ?>
		<?php endif; ?>

		<?php if ( $hallazgos && 'pendiente' === $seccion ) : ?>
			<button type="button" class="button" onclick="document.getElementById('swpk-mensaje-dlg').showModal()">
				Ver el mensaje para la asesora
			</button>
			<?php echo wp_kses_post( SWPK_Vista::ayuda(
				'El texto escrito para que el cliente entienda qué se juega con lo que tiene pendiente, '
				. 'listo para copiar y pegar en un correo o una cotización.'
			) ); ?>
		<?php endif; ?>
	</p>

	<?php
	// ── Los dos primeros números llevan a su lista ──────────────────────────
	//
	// «Quiero ver las 19 críticas, quiero ver las 14 pendientes, no puedo,
	// ¿dónde puedo ver eso?» No podía: eran dos cifras pintadas. Ahora cada
	// una es un enlace a la pestaña de pendientes de este sitio, con su
	// filtro puesto. Un número que no lleva a los renglones que lo forman es
	// un número que hay que creer.
	?>
	<div class="swpk-tarjetas">
		<a class="swpk-tarjeta swpk-tarjeta-enlace" href="<?php echo esc_url( add_query_arg( 'solo', 'vuln', $url_pestana( 'pendiente' ) ) ); ?>">
			<div class="swpk-n" style="color:<?php echo count( $con_vuln ) ? '#9e0b10' : '#0b5ed7'; ?>"><?php echo count( $con_vuln ); ?></div>
			<div class="swpk-r">Con vulnerabilidad conocida</div>
		</a>
		<a class="swpk-tarjeta swpk-tarjeta-enlace" href="<?php echo esc_url( add_query_arg( 'solo', 'act', $url_pestana( 'pendiente' ) ) ); ?>">
			<div class="swpk-n"><?php echo count( $solo_act ); ?></div>
			<div class="swpk-r">Solo actualización pendiente</div>
		</a>
		<?php
		// En «Datos del sitio» estas tres no se pintan: la tabla de esa
		// pestaña las dice enteras, y el mismo dato dos veces en la misma
		// pantalla es ruido, no refuerzo.
		if ( 'datos' !== $seccion ) :
		?>
		<div class="swpk-tarjeta">
			<div class="swpk-n"><?php echo esc_html( $sitio->version_wp ?: '—' ); ?></div>
			<div class="swpk-r">WordPress</div>
		</div>
		<div class="swpk-tarjeta">
			<div class="swpk-n"><?php echo esc_html( $sitio->version_php ?: '—' ); ?></div>
			<div class="swpk-r">PHP</div>
		</div>
		<?php endif; ?>
		<?php if ( 'datos' !== $seccion && SWPK_Etiquetas::es_nuestro( $sitio->tag ) ) : ?>
			<div class="swpk-tarjeta">
				<div class="swpk-n" style="font-size:19px;padding-top:6px;color:<?php echo $sitio->tiene_2fa ? '#0b5ed7' : '#9e0b10'; ?>">
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


	<?php SWPK_Vista::rejilla_abrir(); ?>

	<?php if ( $hallazgos && 'pendiente' === $seccion ) : ?>
		<?php
		// Solo la ventana: el botón que la abre vive arriba, con los otros
		// dos. Aquí, entre los números y la tabla, partía en dos justo lo que
		// se viene a mirar.
		?>
		<dialog id="swpk-mensaje-dlg" class="swpk-dialogo swpk-dialogo-texto">
			<div class="swpk-dialogo-cab">
				<strong>El mensaje para la asesora</strong>
				<form method="dialog"><button class="button">Cerrar</button></form>
			</div>

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

		</dialog>

		<?php
		// El título dice QUÉ se está mirando. Al pulsar «19 con vulnerabilidad
		// conocida» la tabla quedaba filtrada pero seguía titulándose «todo lo
		// que tiene pendiente», que es justo lo que ya no es.
		$titulo_tabla = match ( $solo ) {
			'vuln' => 'Vulnerabilidades conocidas',
			'act'  => 'Actualizaciones pendientes',
			default => 'Todo lo que tiene pendiente',
		};

		SWPK_Vista::caja_abrir(
			$titulo_tabla,
			'' !== $solo
				? sprintf(
					'%s · <a href="%s">ver todo</a>',
					esc_html( SWPK_Vista::plural( count( $hallazgos ), 'módulo', 'módulos' ) ),
					esc_url( $url_pestana( 'pendiente' ) )
				)
				: esc_html( SWPK_Vista::plural( count( $hallazgos ), 'módulo', 'módulos' ) ),
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
							<br><span style="color:#8a6a00;font-size:12px">sin actualización automática</span>
						<?php endif; ?>
					</td>
					<td>
						<span class="swpk-mono"><?php echo esc_html( $h['version'] ); ?></span>
						<?php if ( '' !== $h['version_disponible'] ) : ?>
							→ <span class="swpk-mono" style="color:#0b5ed7"><?php echo esc_html( $h['version_disponible'] ); ?></span>
						<?php endif; ?>
					</td>
					<td><?php echo wp_kses_post( SWPK_Admin::nivel_badge( $h['nivel'] ) ); ?></td>
					<td>
						<?php if ( $v ) : ?>
							<?php $quien = SWPK_Textos::quien_puede( (string) ( $v->cvss_vector ?? '' ), (string) $v->descripcion ); ?>
							<span style="color:<?php echo $quien['sin_cuenta'] ? '#9e0b10' : '#1d2327'; ?>">
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
							<span style="color:#9e0b10">El hosting la marca como vulnerable</span>,
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
	<?php elseif ( 'pendiente' === $seccion && SWPK_Riesgo::SIN_DATOS === $nivel ) : ?>
		<?php SWPK_Vista::caja_abrir( 'Todo lo que tiene pendiente', 'sin datos', 'swpk-c12' ); ?>
		<p style="margin:0">
			<strong>Aquí no hay nada que mostrar, y eso no es una buena noticia.</strong>
			No es que este sitio esté al día: es que no tenemos su lista de módulos, así que no
			hay nada contra qué comparar.
		</p>
		<?php SWPK_Vista::caja_cerrar(); ?>
	<?php elseif ( 'pendiente' === $seccion ) : ?>
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

	<?php if ( $normas_activas && 'normas' === $seccion ) : ?>
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

		// Las cinco que el panel sabe arreglar, de las 73. Con el resto
		// paginado de doce en doce, buscarlas a mano es rendirse a la tercera
		// página: «no hay ningún sitio en donde me salga un botón. No hay
		// ninguno». Son una pastilla más, al final.
		$ARREGLABLES = '_arreglables';

		$las_arreglables = array_values( array_filter(
			$aplicables,
			static fn( $n ) => '' !== SWPK_Ordenes::orden_de_la_norma( (string) $n->clave )
				&& 1 === (int) $n->arreglable
		) );

		$tema_pedido = isset( $_GET['tema'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['tema'] ) ) : '';
		$pide_arreglables = $ARREGLABLES === $tema_pedido && $las_arreglables;
		$hay_tema    = '' !== $tema_pedido && isset( $por_tema[ $tema_pedido ] );
		$para_ver    = $pide_arreglables ? $las_arreglables : ( $hay_tema ? $por_tema[ $tema_pedido ] : $aplicables );

		// ── Primero lo que falta ────────────────────────────────────────
		//
		// Venían en el orden del catálogo, así que al entrar se veía una
		// norma cumplida, una sin medir y una que falla, mezcladas. Se entra
		// aquí a ver qué hay que arreglar: eso va arriba. Después lo que no se
		// pudo medir, y al final lo que ya está bien.
		// ── Primero lo que se puede hacer, después lo que falla ─────────
		//
		// La primera versión de este orden ponía delante todo lo que falla. Y
		// como de las 73 el panel solo sabe arreglar cinco —que en un sitio
		// que todavía no reporta salen «sin medir»—, esas cinco se iban a la
		// página dos o tres, detrás de un muro de normas que dicen «hay que
		// hacerlo a mano». Antes no había orden ninguno y salían en el del
		// catálogo, donde esas cinco son las primeras: por eso el botón de
		// arreglar estaba a la vista al entrar, y después de mi cambio dejó
		// de estarlo. «Sigo sin ver dónde puedo arreglar» no era una
		// impresión: era esto.
		//
		// Ahora manda lo accionable: lo que falla Y el panel sabe arreglar,
		// después lo que falla, después lo que el panel sabría arreglar en
		// cuanto lo mida, y al final lo que ya está bien.
		$peso_estado = static function ( $n ) use ( $cumple_sitio ): int {
			$fila = $cumple_sitio[ (string) $n->clave ] ?? null;
			$e    = $fila ? (string) $fila->estado : SWPK_Normas::SIN_DATO;
			$puede = '' !== SWPK_Ordenes::orden_de_la_norma( (string) $n->clave )
				&& 1 === (int) $n->arreglable;

			if ( SWPK_Normas::FALLA === $e ) {
				return $puede ? 0 : 1;
			}
			if ( SWPK_Normas::SIN_DATO === $e ) {
				return $puede ? 2 : 3;
			}
			if ( SWPK_Normas::NO_APLICA === $e ) {
				return 5;
			}
			return 4;
		};

		usort(
			$para_ver,
			static fn( $a, $b ) => [ $peso_estado( $a ), (string) $a->nombre ]
				<=> [ $peso_estado( $b ), (string) $b->nombre ]
		);

		// El tema conserva la pestaña y de dónde se venía; si no, elegir un
		// tema devolvía a la pestaña de pendientes y se perdía el regreso.
		$url_tema = static fn( string $g ) => add_query_arg(
			array_filter( [
				'page'    => 'panel-kinsta-sitio',
				'sitio'   => (string) $sitio->sitio_id,
				'seccion' => 'normas',
				'tema'    => $g,
			] ) + SWPK_Vista::rastro(),
			admin_url( 'admin.php' )
		);

		$pastillas_tema = [ [
			'texto'  => 'Todas',
			'n'      => count( $aplicables ),
			'url'    => $url_tema( '' ),
			'activa' => ! $hay_tema && ! $pide_arreglables,
		] ];

		foreach ( $temas as $g ) {
			$pastillas_tema[] = [
				'texto'  => $g,
				'n'      => count( $por_tema[ $g ] ),
				'url'    => $url_tema( $g ),
				'activa' => $hay_tema && $tema_pedido === $g,
			];
		}

		if ( $las_arreglables ) {
			$pastillas_tema[] = [
				'texto'  => 'Las que el panel sabe arreglar',
				'n'      => count( $las_arreglables ),
				'url'    => $url_tema( $ARREGLABLES ),
				'activa' => (bool) $pide_arreglables,
			];
		}

		[ $normas_pagina, $pag_nor, $pags_nor ] = SWPK_Vista::paginar( $para_ver, SWPK_Vista::pagina_pedida(), 12 );
		?>
		<div class="swpk-pastillas" style="margin:0 0 12px"><?php echo SWPK_Vista::pastillas( $pastillas_tema ); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>

		<?php
		// Lo mismo que se dice en Seguridad, dicho también aquí: esta es la
		// otra pantalla donde se entra a arreglar y donde la columna de la
		// derecha sale vacía casi siempre.
		$arreglables_aqui = 0;
		foreach ( $para_ver as $n_a ) {
			$fila_a = $cumple_sitio[ (string) $n_a->clave ] ?? null;
			if ( SWPK_Normas::FALLA === ( $fila_a ? (string) $fila_a->estado : '' )
				&& '' !== SWPK_Ordenes::orden_de_la_norma( (string) $n_a->clave )
				&& 1 === (int) $n_a->arreglable
				&& ! $es_cliente
			) {
				++$arreglables_aqui;
			}
		}
		?>
		<p class="swpk-aviso-corto">
			<?php
			if ( $es_cliente ) {
				echo 'Este sitio lo lleva el cliente: el panel lo mide, pero no le cambia nada.';
			} elseif ( 0 === $arreglables_aqui && $las_arreglables ) {
				echo esc_html( sprintf(
					'Ahora mismo no hay nada que pedirle al panel: de las %s que sabe arreglar, ninguna está fallando aquí.',
					SWPK_Vista::plural( count( $las_arreglables ), 'norma', 'normas' )
				) );
			} elseif ( 0 === $arreglables_aqui ) {
				echo 'De lo que le falta a este sitio, el panel no puede arreglar nada solo: hay que entrar al sitio a cambiarlo.';
			} else {
				echo esc_html( sprintf(
					'%s desde aquí; el resto hay que ir a cambiarlo al sitio.',
					SWPK_Vista::plural( $arreglables_aqui, 'norma se arregla', 'normas se arreglan' )
				) );
			}

			echo wp_kses_post( SWPK_Vista::ayuda(
				'El agente sabe cambiar estas normas, y ninguna más: '
				. implode( '; ', SWPK_Ordenes::normas_que_sabe_arreglar() ) . '. Las demás —una cabecera '
				. 'del servidor, una política de contenido, una versión de PHP— no están a su alcance: el '
				. 'panel las mide y avisa, y el arreglo lo hace una persona entrando al sitio.'
			) );
			?>
		</p>

		<table class="widefat striped swpk-tabla">
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

				// La celda de la derecha dice SIEMPRE qué se puede hacer con esta
				// norma, y no solo cuando falla. Antes, una que el panel sabe
				// arreglar pero que todavía no se ha medido salía con la celda en
				// blanco: ni botón ni explicación. Desde fuera eso se lee como
				// «aquí no se puede arreglar nada», que es lo contrario de lo que
				// pasa, y es lo que hacía parecer que el botón había desaparecido.
				$puede_el_panel = '' !== $orden && 1 === (int) $n->arreglable;
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
					<?php elseif ( $es_cliente && $puede_el_panel ) : ?>
						<span class="swpk-mudo" title="Sitio del cliente: se mide, no se cambia sin que lo pida.">Lo lleva el cliente</span>
					<?php elseif ( SWPK_Normas::FALLA === $estado && $puede_el_panel ) : ?>
						<a class="button button-small button-primary" href="<?php
							echo esc_url( SWPK_Admin::url_arreglar( $clave, $sitio_id ) );
						?>">Arreglar</a>
					<?php elseif ( $puede_el_panel && SWPK_Normas::SIN_DATO === $estado ) : ?>
						<span class="swpk-puede" title="El panel sabe arreglar esta norma, pero todavía no sabe si hace falta: el sitio no ha mandado la medición.">
							Se puede arreglar
						</span>
					<?php elseif ( $puede_el_panel ) : ?>
						<span class="swpk-mudo" title="El panel sabe arreglarla, y aquí ya está bien.">El panel la sabe arreglar</span>
					<?php elseif ( SWPK_Normas::FALLA === $estado ) : ?>
						<span class="swpk-mudo" title="Esto no lo puede cambiar el panel: hay que entrar al sitio a hacerlo.">Hay que hacerlo a mano</span>
					<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php SWPK_Vista::paginador( $pag_nor, $pags_nor, count( $para_ver ), 'normas', 'norma' ); ?>
		<?php SWPK_Vista::caja_cerrar(); ?>
	<?php endif; ?>

	<?php
	// ── Datos del sitio ─────────────────────────────────────────────────────
	//
	// «Características del proyecto», en sus palabras. Lo que antes estaba
	// repartido entre la cabecera, los avisos y el sello, junto y en una
	// tabla: qué versión tiene, de cuándo es el dato, quién lo atiende y a
	// quién se le escribe.
	//
	// El contacto del cliente está aquí porque él lo echó en falta —«¿no hay
	// un correo del cliente?»— y porque es el dato que hace falta para mandar
	// el reporte. Se pone al asignar el sitio, en la pantalla de Sitios.
	if ( 'datos' === $seccion ) :
		$asesora  = (int) $sitio->asesora_id ? get_userdata( (int) $sitio->asesora_id ) : null;
		$contacto = trim( (string) $sitio->contacto_cliente );

		$datos = [
			'Dirección'            => sprintf(
				'<a href="%s" target="_blank" rel="noopener" class="swpk-mono">%s</a>',
				esc_url( 'https://' . ltrim( (string) $sitio->dominio, 'htps:/' ) ),
				esc_html( (string) $sitio->dominio )
			),
			'Grupo'                => SWPK_Admin::grupo_badge( SWPK_Inventario::grupo_de( $sitio ) )
				. ( '' !== (string) ( $sitio->grupo_a_mano ?? '' )
					? ' ' . wp_kses_post( SWPK_Vista::ayuda(
						'Este grupo está corregido a mano en el panel. La etiqueta que trae el hosting '
						. 'es «' . esc_html( SWPK_Etiquetas::nombres()[ (string) $sitio->tag ] ?? (string) $sitio->tag ) . '».'
					) )
					: '' ),
			// Lo medido, al lado de la etiqueta y con el mismo peso.
			//
			// La etiqueta y la actualización automática son dos cosas, y
			// enseñar solo la primera fue lo que hizo que el panel dijera algo
			// que Kinsta desmentía. Ver SWPK_Admin::auto_update_badge().
			'Actualización automática' => ( static function () use ( $sitio ) {
				$a = SWPK_Inventario::auto_update( (string) $sitio->sitio_id );

				return SWPK_Admin::auto_update_badge( $a )
					. ( $a['total']
						? ' <span class="swpk-gris" style="font-size:12px">'
							. sprintf(
								'%d de %d módulos activos',
								(int) $a['con'],
								(int) $a['total']
							)
							. '</span>'
						: '' )
					. wp_kses_post( SWPK_Vista::ayuda(
						'Lo que el hosting reporta módulo por módulo, no lo que sugiere la etiqueta. Un '
						. 'sitio etiquetado como Desarrollo puede tener todo en automático, y uno '
						. 'etiquetado para automático puede tener módulos a mano.'
					) );
			} )(),
			'WordPress'            => esc_html( $sitio->version_wp ?: '—' ),
			'PHP'                  => esc_html( $sitio->version_php ?: '—' ),
			'Última revisión'      => $sitio->inventario_en
				? esc_html( SWPK_Frescura::fmt( 'j \d\e F \d\e Y, H:i', (string) $sitio->inventario_en ) )
					. wp_kses_post( SWPK_Vista::ayuda(
						'Cuándo ' . SWPK_Admin::proveedor() . ' revisó por última vez qué tiene instalado este sitio. No lo decide el panel: lo hace el hosting a su ritmo.'
					) )
				: '<span class="swpk-gris">nunca</span>',
			'Quién lo atiende'     => $asesora
				? esc_html( $asesora->display_name )
				: '<span class="swpk-gris">sin asignar</span>',
			'A quién le llega el correo' => ( static function () use ( $sitio ) {
				// «Dice: lo da el agente. ¿Y por qué no lo estoy viendo?»
				// Porque el panel lo calculaba solo en el momento de enviar y
				// no lo enseñaba en ningún sitio. Es la misma cuenta que hace
				// el envío, así que lo que sale aquí es exactamente a quién le
				// va a llegar.
				$destinos = SWPK_Correos::destinatarios_cliente( $sitio );

				if ( ! $destinos ) {
					return '<span class="swpk-gris">a nadie todavía</span>'
						. wp_kses_post( SWPK_Vista::ayuda(
							'El panel saca los destinatarios de los administradores que reporta el '
							. 'agente, quitando los de nuestros propios dominios. Si no sale ninguno es '
							. 'porque el agente aún no ha mandado su lista de usuarios, porque todos los '
							. 'administradores son nuestros, o porque el sitio no tiene agente. En ese '
							. 'último caso se usa el contacto escrito a mano, aquí abajo.'
						) );
				}

				return '<span class="swpk-mono">' . esc_html( implode( ', ', $destinos ) ) . '</span>'
					. wp_kses_post( SWPK_Vista::ayuda(
						'Lo único que les llega es el reporte mensual de mantenimiento: qué se actualizó '
						. 'en su sitio y qué se mantuvo al día. Ningún otro aviso del panel sale hacia '
						. 'ellos. Son los administradores que reporta el agente, menos los de nuestros '
						. 'dominios.'
					) );
			} )(),
			'Contacto del cliente' => '' !== $contacto
				? esc_html( $contacto )
				: '<span class="swpk-gris">sin contacto</span>'
					. wp_kses_post( SWPK_Vista::ayuda(
						'A quién se le manda el reporte de lo que se hizo. Se pone al asignar el sitio, en la pantalla de Sitios. A las asesoras y a soporte se les avisa de lo que falta; al cliente se le cuenta lo que ya se hizo.'
					) ),
			'Verificación en dos pasos' => SWPK_Etiquetas::es_nuestro( SWPK_Inventario::grupo_de( $sitio ) )
				? ( $sitio->tiene_2fa
					? '<span style="color:var(--swpk-bien);font-weight:600">activa</span>'
					: '<span style="color:var(--swpk-critico);font-weight:600">apagada</span>' )
				: '<span class="swpk-gris">no se mide: el sitio es del cliente</span>',
			'Agente instalado'     => (int) $sitio->cobertura_agente
				? '<span style="color:var(--swpk-bien);font-weight:600">sí</span>'
				: '<span class="swpk-gris">no</span>'
					. wp_kses_post( SWPK_Vista::ayuda(
						'Sin el agente dentro del sitio, el panel no puede ver quién tiene acceso de administrador ni medir las normas que solo se saben desde dentro.'
					) ),
		];

		SWPK_Vista::caja_abrir( 'Datos del sitio', '', 'swpk-c12', true );
		?>
		<table class="widefat striped swpk-tabla">
			<tbody>
			<?php foreach ( $datos as $que => $valor ) : ?>
				<tr>
					<th scope="row" style="width:220px"><?php echo esc_html( $que ); ?></th>
					<td><?php echo wp_kses_post( $valor ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
		SWPK_Vista::caja_cerrar();
	endif;
	?>

	<?php SWPK_Vista::rejilla_cerrar(); ?>

	<?php SWPK_Vista::volver( $regreso['url'], $regreso['texto'] ); ?>

	<p class="swpk-atribucion"><?php echo wp_kses_post( SWPK_Wordfence::atribucion() ); ?></p>
</div>
