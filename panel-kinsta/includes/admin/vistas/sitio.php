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

// ── Las normas se cuentan arriba, porque arriba es donde se preguntan ───────
//
//   «nos falta la información de cuántas normas de seguridad tiene pendientes.
//    Eso no lo estamos viendo en estos cuadros de resumen […] y yo clicarlas,
//    en vez de tener que ir a normas de seguridad y tener que ver todas»
//
// Las cuentas se hacían dentro de la pestaña de normas, así que desde la
// cabecera no había número que enseñar. Ahora se hacen aquí y la pestaña las
// reutiliza: un solo recuento para las dos cosas.
$normas_activas = SWPK_Normas::activas();
$cumple_sitio   = SWPK_Normas::del_sitio( $sitio_id );

$aplicables = [];
foreach ( $normas_activas as $n ) {
	if ( SWPK_Normas::aplica( $n, (string) $sitio->tag ) ) {
		$aplicables[] = $n;
	}
}

$estado_de = static function ( $n ) use ( $cumple_sitio ): string {
	$fila = $cumple_sitio[ (string) $n->clave ] ?? null;
	return $fila ? (string) $fila->estado : SWPK_Normas::SIN_DATO;
};

// Un sitio del cliente se mide, pero no se le toca nada sin pedirlo. Se
// decide una vez arriba porque lo usan las dos pestañas.
$es_cliente = SWPK_Etiquetas::NO_UPDATE === (string) $sitio->tag;

$normas_falla = 0;
foreach ( $aplicables as $n ) {
	if ( SWPK_Normas::FALLA === $estado_de( $n ) ) {
		++$normas_falla;
	}
}

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
	<?php
	// ── La barra de acciones, siempre los mismos enlaces ────────────────────
	//
	//   «ahora vengo yo aquí, quiero ver todo lo que ha pasado con este sitio.
	//    ¿Dónde lo veo? Ah, me tengo que ir a historial, y en historial buscar
	//    el sitio… y tengo un sitio en blanco» (13:24)
	//   «le podemos poner un botoncito: ir a ver sitio, o ir al reporte de
	//    sitio, y te manda para acá» (14:43)
	//
	// Faltaban los dos puentes de salida: al Historial de ESTE sitio, ya
	// filtrado, y al hosting. Estaban a tres pantallas y un desplegable de 109
	// nombres. Ahora son dos enlaces, siempre en el mismo orden y en el mismo
	// sitio de la ficha — que es la regla 5 de su panel: «unas cosas se pasan
	// para arriba, unas para abajo» es justo lo que no puede pasar.
	?>
	<p class="swpk-acciones-ficha">
		<a class="button"
			href="<?php echo esc_url( add_query_arg( [ 'page' => 'panel-kinsta-movimientos', 'sitio' => $sitio_id ] , admin_url( 'admin.php' ) ) ); ?>">
			Ver su historial
		</a>
		<a class="button button-primary"
			href="<?php echo esc_url( add_query_arg( [ 'page' => 'panel-kinsta-reporte', 'sitio' => $sitio_id ] + SWPK_Vista::rastro(), admin_url( 'admin.php' ) ) ); ?>">
			Reporte para el cliente
		</a>
		<?php
		// La burbuja del reporte iba DESPUÉS del botón de Kinsta, así que
		// parecía explicar ese: «la burbuja no dice nada relacionado a Kinsta
		// ni se entiende qué onda». Cada burbuja pegada a lo suyo.
		echo wp_kses_post( SWPK_Vista::ayuda(
			'Qué se le notificó a este cliente y cuándo, en una hoja lista para imprimir o guardar en PDF.'
		) );
		?>

		<?php
		// El cuarto enlace de la barra. Va al hosting, que es a donde hay que
		// ir para la mitad de los arreglos: encender la actualización
		// automática, mirar los entornos, los respaldos.
		//
		// Hace falta el id del entorno además del del sitio: MyKinsta no abre
		// la ficha sin los dos. Si el sitio no tiene entorno guardado —pasa
		// con los que la API aún no ha devuelto entero— no se dibuja el
		// enlace, porque un enlace que lleva a un 404 es peor que ninguno.
		?>
		<?php if ( ! empty( $sitio->entorno_id ) ) : ?>
			<a class="button" target="_blank" rel="noopener"
				href="<?php echo esc_url( SWPK_Codigos::url_sitio( $sitio ) ); ?>">
				Abrir en Kinsta ↗
			</a>
			<?php echo wp_kses_post( SWPK_Vista::ayuda(
				'Abre la ficha de este sitio en MyKinsta, en otra pestaña: los entornos, los respaldos, '
				. 'la caché, los registros y el interruptor de las actualizaciones automáticas del hosting. '
				. 'Hay que tener la sesión de Kinsta iniciada.'
			) ); ?>
		<?php endif; ?>

		<?php if ( SWPK_Perfiles::puede( SWPK_Perfiles::ADMINISTRAR ) ) : ?>
			<?php
			// ── F4 y F5, que son la misma queja en dos mitades ─────────────
			//
			//   «analizar este sitio ahora… no pasa nada, o sea, no hay como
			//    cargando, verificando» (07:59)
			//   «ya lo hizo, pero esto cuándo lo hizo… no sé cuándo lo hizo»
			//    (08:11)
			//
			// Las dos salían del mismo sitio: el botón no decía nada ni antes
			// ni después. Antes, porque es un enlace normal y preguntarle al
			// hosting por un sitio tarda entre tres y diez segundos en los que
			// la pantalla no se mueve — `data-espera` lo arregla, ver
			// espera.js. Y después, porque la última revisión estaba en la
			// pestaña de Datos y aquí no: hay que leerla donde está el botón
			// que la cambia, no a dos clics de distancia.
			?>
			<a class="button" href="<?php echo esc_url( SWPK_Admin::url_analizar( $sitio_id ) ); ?>"
				data-espera="Revisando este sitio…">
				Analizar este sitio ahora
			</a>
			<?php echo wp_kses_post( SWPK_Vista::ayuda( sprintf(
				'Vuelve a preguntarle a %s qué tiene instalado este sitio y revisa si responde, sin '
				. 'esperar a la corrida de mañana. Tarda unos segundos y no toca los demás sitios.',
				SWPK_Admin::proveedor()
			) ) ); ?>
			<span class="swpk-mudo" style="margin-left:2px">
				<?php
				echo ! empty( $sitio->inventario_en )
					? esc_html( 'Última vez: ' . SWPK_Frescura::fecha_y_edad( (string) $sitio->inventario_en ) )
					: 'Nunca se ha analizado';
				?>
			</span>
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
		// ── La tercera cifra que faltaba ────────────────────────────────────
		//
		//   «nos falta la información de cuántas normas de seguridad tiene
		//    pendientes. Eso no lo estamos viendo en estos cuadros de resumen.
		//    Ahí deberíamos tener cuántas normas pendientes. Y yo clicarlas, en
		//    vez de tener que ir a normas de seguridad y ver todas»
		//
		// Lleva a la pestaña de normas con el estado ya puesto en «No cumple»:
		// el número y la lista que lo forma son la misma cosa, y hasta ahora
		// había que reconstruirla a mano.
		if ( 'datos' !== $seccion && $aplicables ) :
		?>
		<a class="swpk-tarjeta swpk-tarjeta-enlace" href="<?php echo esc_url( add_query_arg(
			'estado',
			SWPK_Normas::FALLA,
			$url_pestana( 'normas' )
		) ); ?>">
			<div class="swpk-n" style="color:<?php echo $normas_falla ? '#9e0b10' : '#0b5ed7'; ?>"><?php echo (int) $normas_falla; ?></div>
			<div class="swpk-r">Normas sin cumplir</div>
		</a>
		<?php endif; ?>
		<?php if ( 'datos' !== $seccion && SWPK_Etiquetas::es_nuestro( $sitio->tag ) ) : ?>
			<div class="swpk-tarjeta">
				<div class="swpk-n" style="font-size:19px;padding-top:6px;color:<?php echo $sitio->tiene_2fa ? '#0b5ed7' : '#9e0b10'; ?>">
					<?php echo $sitio->tiene_2fa ? '✓ Activa' : 'Pendiente'; ?>
				</div>
				<div class="swpk-r">Doble autenticación</div>
			</div>
		<?php endif; ?>
		<?php
		// Las versiones van al final y en pequeño. No están de más —son el dato
		// que contesta «¿por qué no actualiza?»— pero no son a lo que se viene:
		// «¿para qué quiero saber el tipo de WordPress que tiene, el PHP? Lo
		// cual no está mal. Pero aquí realmente la información que me importa…»
		// Siguen ahí, sin ocupar el mismo tamaño que lo que hay que hacer.
		if ( 'datos' !== $seccion ) :
		?>
		<div class="swpk-tarjeta swpk-tarjeta-menor">
			<div class="swpk-n"><?php echo esc_html( $sitio->version_wp ?: '—' ); ?></div>
			<div class="swpk-r">WordPress</div>
		</div>
		<div class="swpk-tarjeta swpk-tarjeta-menor">
			<div class="swpk-n"><?php echo esc_html( $sitio->version_php ?: '—' ); ?></div>
			<div class="swpk-r">PHP</div>
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
		<?php
		// ── «Aquí no me dice qué podemos hacer» ─────────────────────────────
		//
		//   «tiene un nivel crítico y dice que hay un parche, pero no tiene una
		//    actualización, ¿o sí? Entonces, ¿qué es lo que podemos hacer? […]
		//    mi jefe quiere saber qué puede hacer»
		//
		// La tabla tenía cinco columnas —módulo, versión, nivel, quién puede
		// aprovecharlo, qué permite— y ninguna contestaba eso. Se añadió una
		// sexta con la respuesta y el remedio salió peor que la enfermedad:
		//
		//   «esta visualización ahora no me gusta, siento que se ve más
		//    desordenado y se acumula toda la información»
		//
		// Tenía razón: cuatro columnas de párrafos son cuatro bloques de texto
		// compitiendo, y cada módulo ocupaba media pantalla. El mismo pozo que
		// había en las normas, y que allí se resolvió desplegando cada línea.
		//
		// Así que esto se lee ahora igual que las normas: la línea cerrada dice
		// las tres cosas con las que se decide —qué módulo, cómo de grave, y
		// qué hay que hacer— y el porqué, que es lo largo, vive dentro.
		?>
		<div class="swpk-normas swpk-pendientes">
		<?php foreach ( $hallazgos as $h ) : ?>
			<?php
			$v     = $h['vulnerabilidades'][0] ?? null;
			$hacer = SWPK_QueHacer::para( $h );
			$quien = $v
				? SWPK_Textos::quien_puede( (string) ( $v->cvss_vector ?? '' ), (string) $v->descripcion )
				: null;
			?>
			<details class="swpk-norma swpk-pendiente">
				<summary>
					<span class="swpk-norma-nombre">
						<strong><?php echo esc_html( SWPK_Textos::limpiar_titulo( (string) $h['titulo'] ) ); ?></strong>
						<span class="swpk-norma-aclara">
							<span class="swpk-mono"><?php echo esc_html( $h['version'] ); ?></span><?php
							if ( '' !== $h['version_disponible'] ) : ?> → <span class="swpk-mono" style="color:#0b5ed7"><?php
								echo esc_html( $h['version_disponible'] );
							?></span><?php endif; ?>
							· <?php echo esc_html( $h['slug'] ); ?>
							<?php if ( isset( $h['activo'] ) && 0 === (int) $h['activo'] ) : ?>
								· apagado
							<?php endif; ?>
						</span>
					</span>
					<span class="swpk-norma-estado">
						<?php echo wp_kses_post( SWPK_Admin::nivel_badge( $h['nivel'] ) ); ?>
						<?php
						$pildora = SWPK_Admin::parche_badge(
							$v ? (bool) (int) ( $v->parcheada ?? 0 ) : null,
							(string) $h['slug'],
							(string) ( $h['tipo'] ?? 'plugin' )
						);
						if ( '' !== $pildora ) :
							?>
							<span class="swpk-pendiente-pildora"><?php echo wp_kses_post( $pildora ); ?></span>
						<?php endif; ?>
					</span>
					<span class="swpk-pendiente-hacer swpk-hacer-<?php echo esc_attr( $hacer['quien'] ); ?>">
						<?php echo esc_html( $hacer['titular'] ); ?>
					</span>
				</summary>

				<div class="swpk-norma-dentro">
					<ol class="swpk-norma-pasos">
						<li>
							<h4>Por qué importa</h4>
							<?php if ( $v ) : ?>
								<p style="margin:0 0 6px">
									<span class="swpk-rotulin">Quién puede aprovecharlo</span>
									<span style="color:<?php echo $quien['sin_cuenta'] ? '#9e0b10' : '#1d2327'; ?>">
										<?php echo esc_html( $quien['frase'] ); ?>
									</span>
								</p>
								<p style="margin:0">
									<span class="swpk-rotulin">Qué permite hacer</span>
									<?php echo esc_html( SWPK_Textos::explicar( (string) $v->titulo, (string) $v->descripcion ) ); ?>
									<?php if ( '' !== (string) $v->cve ) : ?>
										<br><span class="swpk-mono swpk-mudo"><?php echo esc_html( (string) $v->cve ); ?></span>
									<?php endif; ?>
								</p>
							<?php elseif ( 'vulnerable_sin_detalle' === $h['motivo'] ) : ?>
								<p style="margin:0">
									<span style="color:#9e0b10">El hosting marca esta versión como vulnerable</span>,
									y todavía no hay detalle público de la falla. El dato del hosting vale igual:
									lo que falta es el «por qué».
								</p>
							<?php else : ?>
								<p style="margin:0">
									No tiene ninguna falla conocida publicada. Es una actualización pendiente y nada más:
									importa porque las versiones viejas acumulan fallas que se publican después.
								</p>
							<?php endif; ?>
						</li>

						<li>
							<h4>Qué hacer</h4>
							<p><strong><?php echo esc_html( $hacer['titular'] ); ?>.</strong>
								<?php echo esc_html( $hacer['detalle'] ); ?></p>
							<p class="swpk-norma-pie">
								<a class="button button-small" href="<?php echo esc_url( add_query_arg(
									[ 'page' => 'panel-kinsta-movimientos', 'sitio' => $sitio_id, 'q' => $h['slug'] ],
									admin_url( 'admin.php' )
								) ); ?>">Ver su historial</a>
								<span class="swpk-mudo">Cada vez que este módulo cambió de versión en este sitio.</span>
							</p>
						</li>
					</ol>
				</div>
			</details>
		<?php endforeach; ?>
		</div>
		<?php SWPK_Vista::caja_cerrar(); ?>

		<?php
		// ── Los que están instalados pero apagados ──────────────────────────
		//
		//   «si hay algún plugin que está desactivado, eso se ve dentro del
		//    panel de Kinsta, que nos lo diga cuando entramos al sitio […] si
		//    no es utilizable dentro del sitio, que te aconseje que lo
		//    elimines»
		//
		// Hasta ahora solo salían los apagados que ADEMÁS tenían actualización
		// pendiente, porque la lista de arriba se arma de vulnerabilidades y
		// actualizaciones. Un plugin apagado y al día no aparecía en ninguna
		// parte, y es exactamente el que lleva meses ahí sin que nadie se
		// acuerde.
		$apagados = SWPK_Inventario::apagados( $sitio_id );
		?>
		<?php if ( $apagados ) : ?>
			<?php
			SWPK_Vista::caja_abrir(
				'Instalados pero apagados',
				esc_html( SWPK_Vista::plural( count( $apagados ), 'plugin', 'plugins' ) ),
				'swpk-c12',
				true
			);
			?>
			<p class="swpk-aviso-corto" style="margin-top:0">
				Un plugin apagado sigue con sus archivos en el servidor, y esos archivos se pueden alcanzar desde
				fuera aunque el plugin no esté corriendo. Y como está apagado, nadie se acuerda de actualizarlo.
				<strong>Si ya no se usa, lo limpio es borrarlo</strong>, no dejarlo desactivado.
				<?php echo wp_kses_post( SWPK_Vista::ayuda(
					'Desactivar un plugin solo le dice a WordPress que no lo cargue. Los archivos siguen en '
					. 'su carpeta y una falla en ellos se puede aprovechar igual, porque muchos se pueden '
					. 'pedir directamente por su dirección sin pasar por WordPress.'
				) ); ?>
			</p>
			<table class="widefat striped swpk-tabla">
				<thead>
					<tr>
						<th>Plugin</th>
						<th style="width:150px">Versión</th>
						<th style="width:40%">Qué hacer</th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $apagados as $a ) : ?>
					<tr>
						<td>
							<strong><?php echo esc_html( SWPK_Textos::limpiar_titulo( (string) $a->titulo ) ); ?></strong><br>
							<span class="swpk-mono" style="color:#50575e"><?php echo esc_html( (string) $a->slug ); ?></span>
						</td>
						<td>
							<span class="swpk-mono"><?php echo esc_html( (string) $a->version ); ?></span>
							<?php if ( '' !== (string) $a->version_disponible ) : ?>
								→ <span class="swpk-mono" style="color:#0b5ed7"><?php echo esc_html( (string) $a->version_disponible ); ?></span>
							<?php endif; ?>
						</td>
						<td class="swpk-hacer-celda">
							<?php if ( $es_cliente ) : ?>
								<strong>Preguntarle al cliente si lo usa.</strong>
								Este sitio lo lleva él: si dice que no lo necesita, que lo borre o que nos deje borrarlo.
							<?php elseif ( (int) $a->es_vulnerable ) : ?>
								<strong>Borrarlo cuanto antes.</strong>
								Está apagado y además tiene una falla conocida: los archivos siguen ahí y la falla con ellos.
							<?php else : ?>
								<strong>Borrarlo si ya no se usa.</strong>
								Si hace falta más adelante, se vuelve a instalar en un minuto y entra con la versión del día.
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<?php SWPK_Vista::caja_cerrar(); ?>
		<?php endif; ?>
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
	// $normas_activas, $cumple_sitio, $aplicables y $estado_de se calculan
	// arriba del todo, porque la tarjeta de la cabecera también los necesita.
	?>

	<?php if ( $normas_activas && 'normas' === $seccion ) : ?>
		<?php SWPK_Vista::caja_abrir( 'Las normas de seguridad', '', 'swpk-c12', true ); ?>

		<?php $agente_sitio = SWPK_Ordenes::agente_de( $sitio_id ); ?>

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

		// ── Buscar por palabra ──────────────────────────────────────────────
		//
		//   «y siempre que tengamos como un campo para poder buscar»
		//
		// Con 73 normas repartidas en siete páginas, saber que existe una que
		// habla de XML-RPC no sirve de nada si hay que ir pasando páginas hasta
		// dar con ella. Busca en el nombre, en la aclaración y en el porqué,
		// que es por donde alguien la reconocería.
		$buscar = isset( $_GET['q'] ) ? trim( sanitize_text_field( wp_unslash( (string) $_GET['q'] ) ) ) : '';

		if ( '' !== $buscar ) {
			$aguja    = mb_strtolower( $buscar );
			$para_ver = array_values( array_filter(
				$para_ver,
				static function ( $n ) use ( $aguja ): bool {
					$pajar = mb_strtolower(
						(string) $n->nombre . ' '
						. (string) ( $n->aclaracion ?? '' ) . ' '
						. (string) ( $n->problema ?? '' ) . ' '
						. (string) ( $n->porque ?? '' ) . ' '
						. (string) $n->clave
					);
					return str_contains( $pajar, $aguja );
				}
			) );
		}

		// ── Filtrar por cómo está la norma ──────────────────────────────
		//
		//   «quiero filtrar por las que no cumplen, ¿cómo filtro?»
		//
		// Había pastillas por TEMA y ninguna por ESTADO, que es la pregunta
		// con la que se entra aquí. Con 73 normas y doce por página, ver las
		// que fallan era ir pasando páginas.
		//
		// Se combina con el tema: las de «Cuentas y accesos» que no cumplen.
		$estados_posibles = [
			SWPK_Normas::FALLA    => 'No cumple',
			SWPK_Normas::SIN_DATO => 'Falta medirlo',
			SWPK_Normas::CUMPLE   => 'Cumple',
		];

		$estado_pedido = isset( $_GET['estado'] ) ? sanitize_key( wp_unslash( (string) $_GET['estado'] ) ) : '';
		if ( ! isset( $estados_posibles[ $estado_pedido ] ) ) {
			$estado_pedido = '';
		}

		// Las cuentas se sacan de lo que hay DESPUÉS del tema y ANTES del
		// estado: si se contaran sobre lo ya filtrado, al elegir «No cumple»
		// las otras dos pastillas dirían 0 y no habría forma de volver.
		$cuenta_estado = array_fill_keys( array_keys( $estados_posibles ), 0 );
		foreach ( $para_ver as $n ) {
			$e = $estado_de( $n );
			if ( isset( $cuenta_estado[ $e ] ) ) {
				++$cuenta_estado[ $e ];
			}
		}
		$total_antes_de_estado = count( $para_ver );

		if ( '' !== $estado_pedido ) {
			$para_ver = array_values( array_filter(
				$para_ver,
				static fn( $n ) => $estado_de( $n ) === $estado_pedido
			) );
		}

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
		[ $normas_pagina, $pag_nor, $pags_nor ] = SWPK_Vista::paginar( $para_ver, SWPK_Vista::pagina_pedida(), 12 );

		// ── Dos desplegables y un buscador, en vez de quince pastillas ──────
		//
		//   «aquí en tipo de norma yo no quiero que estén todas así en bloques.
		//    Mejor que sea un filtro […] y poner esos dos filtros ahí y que
		//    sean dropdowns, para no estar saturando las pantallas, que es lo
		//    que él dijo: que no quiere saturar pantallas de información»
		//
		// Eran dos filas de pastillas —diez temas y cuatro estados— que
		// ocupaban cuatro renglones antes de que empezara la lista. Dicen lo
		// mismo en una sola línea, y cada opción sigue llevando su cuenta
		// dentro, que era lo único que las pastillas hacían bien.
		//
		// Y el rótulo deja de ser «Cómo está»:
		//
		//   «no "cómo está", porque "cómo está" como que no se entiende. Entre
		//    que tiene que ser básico, pero no tan básico que se oye ya más
		//    corriente.»
		$temas_opciones = [];
		foreach ( $temas as $g ) {
			$temas_opciones[ $g ] = sprintf( '%s (%d)', $g, count( $por_tema[ $g ] ) );
		}
		if ( $las_arreglables ) {
			$temas_opciones[ $ARREGLABLES ] = sprintf( 'Las que el panel sabe arreglar (%d)', count( $las_arreglables ) );
		}

		$opciones_estado = [];
		foreach ( $estados_posibles as $clave_e => $nombre_e ) {
			$opciones_estado[ $clave_e ] = sprintf( '%s (%d)', $nombre_e, $cuenta_estado[ $clave_e ] );
		}

		// ── La misma barra que el resto del panel ───────────────────────────
		//
		//   «y estos filtros quedan horribles, nada que ver con los demás del
		//    sitio, ¿qué onda?»
		//
		// Los había escrito a mano en vez de usar SWPK_Vista::barra_de_filtros(),
		// que es la que dibuja la de Sitios, la de Vulnerabilidades y la de
		// Historial. Salían con otra caja, otro alto y otra tipografía en la
		// misma pantalla. Es la regla 5 otra vez: lo mismo se ve igual en todas
		// partes, o deja de reconocerse.
		SWPK_Vista::barra_de_filtros(
			'panel-kinsta-sitio',
			[
				'tema' => [
					'etiqueta' => 'Tipo de norma',
					'opciones' => $temas_opciones,
					'actual'   => $tema_pedido,
					'todas'    => sprintf( 'Todas (%d)', count( $aplicables ) ),
				],
				'estado' => [
					'etiqueta' => 'Estado',
					'opciones' => $opciones_estado,
					'actual'   => $estado_pedido,
					'todas'    => sprintf( 'Todas (%d)', $total_antes_de_estado ),
				],
			],
			[ 'sitio' => $sitio_id, 'seccion' => 'normas' ] + SWPK_Vista::rastro(),
			$buscar,
			'una palabra de la norma…'
		);
		?>

		<?php if ( '' !== $estado_pedido && ! $para_ver ) : ?>
			<div class="notice notice-info inline"><p>
				Ninguna norma de este sitio está en «<?php echo esc_html( $estados_posibles[ $estado_pedido ] ); ?>»
				<?php if ( $hay_tema || $pide_arreglables ) : ?>dentro de este tema<?php endif; ?>.
				<a href="<?php echo esc_url( $url_pestana( 'normas' ) ); ?>">Ver todas</a>
			</p></div>
		<?php endif; ?>

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
		<?php
		// ── «Eso es lo que convierte 21 filas rojas en un plan de trabajo» ──
		//
		// «mira, tienes de 30 normas que revisamos solo tienes 15 aplicadas al
		// sitio web — eso sí me interesa» (12:58).
		//
		// El reparto por QUIÉN tiene que moverse, no por tema ni por
		// gravedad: lo que el panel arregla con un clic, lo que obliga a
		// entrar al sitio, y lo que hay que hablar con el cliente antes de
		// tocar nada. Son tres trabajos distintos y hasta ahora salían
		// mezclados en una lista de filas rojas.
		$reparto = [ 'panel' => 0, 'entrar' => 0, 'hablar' => 0 ];

		foreach ( $aplicables as $n_r ) {
			$f_r = $cumple_sitio[ (string) $n_r->clave ] ?? null;
			if ( SWPK_Normas::FALLA !== ( $f_r ? (string) $f_r->estado : '' ) ) {
				continue;
			}

			if ( $es_cliente ) {
				// En un sitio del cliente no se entra a cambiar nada sin
				// pedirlo, así que TODO lo que falle es una conversación.
				++$reparto['hablar'];
			} elseif ( '' !== SWPK_Ordenes::orden_de_la_norma( (string) $n_r->clave ) && 1 === (int) $n_r->arreglable ) {
				++$reparto['panel'];
			} else {
				++$reparto['entrar'];
			}
		}

		$total_falla = array_sum( $reparto );
		?>
		<?php if ( $total_falla > 0 ) : ?>
			<p class="swpk-reparto">
				<?php
				$trozos = [];
				if ( $reparto['panel'] ) {
					$trozos[] = sprintf(
						'<strong>%d</strong> %s el panel con un clic',
						$reparto['panel'],
						1 === $reparto['panel'] ? 'la arregla' : 'las arregla'
					);
				}
				if ( $reparto['entrar'] ) {
					$trozos[] = sprintf(
						'<strong>%d</strong> se %s entrando al sitio',
						$reparto['entrar'],
						1 === $reparto['entrar'] ? 'arregla' : 'arreglan'
					);
				}
				if ( $reparto['hablar'] ) {
					$trozos[] = sprintf(
						'<strong>%d</strong> hay que %s con el cliente',
						$reparto['hablar'],
						1 === $reparto['hablar'] ? 'hablarla' : 'hablarlas'
					);
				}

				printf(
					'De %s sin cumplir de %d: %s.',
					esc_html( SWPK_Vista::plural( $total_falla, 'norma', 'normas' ) ),
					count( $aplicables ),
					wp_kses_post( implode( ', ', $trozos ) )
				);
				?>
			</p>
		<?php endif; ?>

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

		<?php
		// ── Cada norma se despliega, en vez de llenar la tabla ──────────────
		//
		// Era una tabla de cuatro columnas donde la tercera —«Detalle»— metía
		// tres renglones de texto en cada fila, y la cuarta cambiaba de
		// contenido según la fila: un botón azul, dos botones, «Hay que
		// hacerlo a mano», «Se puede arreglar», «El panel la sabe arreglar»,
		// «Lo lleva el cliente», o nada. Anchos distintos, alineados a la
		// derecha, uno debajo de otro:
		//
		//   «y ahora me cambió el orden, ahora me pasó el orden para acá.
		//    Date cuenta: aquí lo tengo y luego me lo pasa para acá» (07:34)
		//
		// Eso no era una impresión suya: la columna bailaba de verdad.
		//
		// Ahora la línea cerrada enseña tres cosas y siempre las mismas
		// —nombre, cómo está, gravedad— y el botón «Arreglar» va en una
		// columna de ancho fijo. Todo lo demás —qué quiere decir, cómo lo
		// sabemos, qué pasa si no, cómo se arregla— vive dentro, al abrirla.
		//
		// No es una <table> porque una fila de tabla no se puede desplegar sin
		// JavaScript, y <details> lo hace solo, funciona con el teclado y se
		// imprime abierto.
		?>
		<div class="swpk-normas">
		<?php foreach ( $normas_pagina as $n ) : ?>
			<?php
			$clave = (string) $n->clave;

			$fila   = $cumple_sitio[ $clave ] ?? null;
			$estado = $fila ? (string) $fila->estado : SWPK_Normas::SIN_DATO;
			$orden  = SWPK_Ordenes::orden_de_la_norma( $clave );

			// La acción se decide una vez y se usa en los dos sitios: el botón
			// de la línea cerrada y el bloque «cómo se arregla» de dentro.
			$puede_el_panel = '' !== $orden && 1 === (int) $n->arreglable;
			$es_declarativa = 'declarativa' === (string) $n->comprobador;
			$puedo_tocar    = SWPK_Perfiles::puede( SWPK_Perfiles::ADMINISTRAR );

			// ── El botón también cuando no se pudo medir ────────────────
			//
			//   «aquí dice "el panel lo puede hacer solo", pero aquí no me
			//    tiene una opción de arreglar, no me tiene el botón activo.
			//    Entonces, si aquí dice que yo lo puedo hacer desde el panel,
			//    ¿por qué aquí no tengo un botón?»
			//
			// Porque el botón solo salía cuando la norma FALLA, y XML-RPC está
			// en «falta medirlo»: se mide desde fuera y el cortafuegos no deja.
			// Pero cerrar XML-RPC no necesita haberlo medido — la orden es la
			// misma y no hace daño si ya estaba cerrado. Dejar el texto
			// prometiendo algo que no tiene botón es lo peor de los dos mundos.
			$se_puede_pedir = $puede_el_panel && ! $es_cliente
				&& in_array( $estado, [ SWPK_Normas::FALLA, SWPK_Normas::SIN_DATO ], true );

			// Se abre sola la que falla Y el panel puede arreglar: es la que
			// uno vino a ver. Las demás, cerradas.
			$abierta = SWPK_Normas::FALLA === $estado && $puede_el_panel && ! $es_cliente;
			?>
			<details class="swpk-norma" <?php echo $abierta ? 'open' : ''; ?>>
				<summary>
					<span class="swpk-norma-nombre">
						<strong><?php echo esc_html( (string) $n->nombre ); ?></strong>
						<?php if ( '' !== (string) ( $n->aclaracion ?? '' ) ) : ?>
							<span class="swpk-norma-aclara"><?php echo esc_html( (string) $n->aclaracion ); ?></span>
						<?php endif; ?>
					</span>
					<span class="swpk-norma-estado">
						<span class="swpk-etq swpk-celda-<?php echo esc_attr( $estado ); ?>"><?php
							echo esc_html( SWPK_Normas::etiqueta( $estado ) );
						?></span>
					</span>
					<span class="swpk-norma-grav">
						<span class="swpk-etq swpk-sev-<?php echo esc_attr( (string) $n->severidad ); ?>"><?php
							echo esc_html( ucfirst( (string) $n->severidad ) );
						?></span>
					</span>
					<?php
					// La columna de ancho fijo. Solo lleva botón: cuando el
					// panel no puede hacer nada, se queda vacía, porque el
					// «cómo se arregla» está dentro, que es su sitio.
					?>
					<span class="swpk-norma-accion">
						<?php if ( ! $puedo_tocar ) : ?>
						<?php elseif ( $es_declarativa ) : ?>
							<a class="button button-small" href="<?php
								echo esc_url( SWPK_Admin::url_marcar_norma( $clave, $sitio_id, SWPK_Normas::CUMPLE ) );
							?>">Cumple</a>
							<a class="button button-small" href="<?php
								echo esc_url( SWPK_Admin::url_marcar_norma( $clave, $sitio_id, SWPK_Normas::FALLA ) );
							?>">No</a>
						<?php elseif ( $se_puede_pedir ) : ?>
							<a class="button button-small <?php echo SWPK_Normas::FALLA === $estado ? 'button-primary' : ''; ?>" href="<?php
								echo esc_url( SWPK_Admin::url_arreglar( $clave, $sitio_id ) );
							?>"><?php echo SWPK_Normas::FALLA === $estado ? 'Arreglar' : 'Mandarlo igual'; ?></a>
						<?php endif; ?>
					</span>
				</summary>

				<div class="swpk-norma-dentro">
					<?php
					// ── Tres bloques numerados, en un orden que se sigue ────
					//
					//   «no cumple, alta… ¿en qué nos afecta?, ¿cómo lo
					//    sabemos?, el editor no sé qué, cómo se arregla. Esa
					//    información hay que ordenarla de mejor forma, porque
					//    yo así no la entiendo. Sí se entiende, pero se ve
					//    desordenado […] o los numeras o los pones dentro de
					//    bloquecitos»
					//
					// Estaban en dos columnas: a la izquierda «cómo lo
					// sabemos» y debajo «cómo se arregla», y a la derecha
					// «qué pasa si no se arregla». Leído de izquierda a
					// derecha salía 1, 3, 2 — y leído de arriba abajo, el
					// bloque de la derecha quedaba colgando sin sitio. No era
					// una impresión: el orden estaba roto de verdad.
					//
					// Ahora es una sola columna y van numerados, en el único
					// orden que cuenta algo: qué se midió, qué pasa si se
					// deja así, y qué hay que hacer. El botón cierra el tres,
					// que es donde toca.
					?>
					<ol class="swpk-norma-pasos">
						<li>
							<h4>Qué medimos, y qué salió</h4>
							<p>
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
							</p>
							<?php
							// ── Por qué se quedó sin medir ──────────────────
							//
							//   «todas nuestras normas que no se pueden medir,
							//    ¿por qué no las podemos medir?»
							//
							// Antes decía «el agente todavía no ha mandado esta
							// medición», que en la mayoría de los casos es
							// falso: las 27 que faltan medir en cada sitio se
							// miden desde FUERA, y lo que falla es el
							// cortafuegos, no el agente.
							if ( SWPK_Normas::SIN_DATO === $estado ) :
								?>
								<p class="swpk-no-medida">
									<?php echo esc_html( SWPK_Normas::por_que_no_se_midio( $n, $sitio, (bool) $agente_sitio ) ); ?>
								</p>
							<?php endif; ?>
						</li>

						<li>
							<h4>Qué pasa si se deja así</h4>
							<p><?php echo esc_html( (string) ( $n->porque ?? '' ) ); ?></p>
						</li>

						<li>
							<h4>Cómo se arregla</h4>
							<?php
							// ── «Ok, ¿qué debo de hacer yo a mano?» (17:58) ──
							//
							// Es el campo que no existía. La tabla de normas
							// guardaba nombre, problema, porque, comprobador y
							// severidad, y ninguno decía cómo cerrarla. Están
							// los 73 en includes/normas-arreglos.php.
							$pasos = trim( (string) ( $n->como_se_arregla ?? '' ) );
							?>
							<?php if ( $es_cliente ) : ?>
								<p class="swpk-mudo">
									Este sitio lo lleva el cliente: el panel lo mide, pero no le cambia nada.
									Lo de abajo es para decírselo a quien sí lo administra.
								</p>
							<?php endif; ?>
							<?php if ( '' !== $pasos ) : ?>
								<?php
								// Las líneas que empiezan por «1.» van en lista
								// numerada; las demás son párrafos. Así el texto
								// se escribe de corrido en el archivo y se lee
								// como pasos aquí, sin marcar nada a mano.
								$en_lista = false;
								foreach ( explode( "\n", $pasos ) as $linea ) {
									$linea = trim( $linea );
									if ( '' === $linea ) {
										continue;
									}
									$es_paso = (bool) preg_match( '/^\d+\.\s/', $linea );

									if ( $es_paso && ! $en_lista ) {
										echo '<ol class="swpk-pasos">';
										$en_lista = true;
									} elseif ( ! $es_paso && $en_lista ) {
										echo '</ol>';
										$en_lista = false;
									}

									if ( $es_paso ) {
										echo '<li>' . esc_html( preg_replace( '/^\d+\.\s*/', '', $linea ) ) . '</li>';
									} else {
										echo '<p>' . esc_html( $linea ) . '</p>';
									}
								}
								if ( $en_lista ) {
									echo '</ol>';
								}
								?>
							<?php else : ?>
								<p class="swpk-mudo">Esta norma todavía no tiene los pasos escritos.</p>
							<?php endif; ?>

							<?php if ( $se_puede_pedir && $puedo_tocar ) : ?>
								<p class="swpk-norma-pie">
									<a class="button button-primary" href="<?php
										echo esc_url( SWPK_Admin::url_arreglar( $clave, $sitio_id ) );
									?>">Arreglar ahora</a>
									<span class="swpk-mudo">
										<?php
										echo SWPK_Normas::FALLA === $estado
											? 'El panel le manda la orden al agente y la próxima medición lo confirma.'
											: 'No se pudo medir, pero la orden es la misma y no hace daño si ya estaba bien.';
										?>
									</span>
								</p>
							<?php endif; ?>
						</li>
					</ol>
				</div>
			</details>
		<?php endforeach; ?>
		</div>

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
		// Las asesoras del sitio, que pueden ser varias. Se lee la tabla y no
		// la columna `asesora_id`, que solo guarda a la primera.
		$sus_asesoras = [];
		foreach ( SWPK_Perfiles::asesoras_de( (string) $sitio->sitio_id ) as $id_a ) {
			$u = get_userdata( $id_a );
			if ( $u ) {
				$sus_asesoras[] = $u->display_name;
			}
		}
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
			// ── Los entornos, y por qué esto importa ────────────────────
			//
			// «Y aquí dice que es [el dominio real] pero están en desarrollo.
			// Eso es imposible» (03:35). No era imposible y tampoco era un
			// sitio repetido: en el hosting ese sitio tiene DOS entornos, uno
			// vivo y uno de pruebas, la etiqueta está puesta en el sitio —que
			// cubre los dos— y el panel mide el vivo. Enseñaba, con razón, el
			// dominio de producción con el cartel de desarrollo.
			//
			// La etiqueta no se puede arreglar en el hosting sin romper lo
			// que significa allá, así que lo que se arregla es el panel: aquí
			// se dice cuántos entornos hay y cuál se está midiendo. El que
			// manda en todas las pantallas sigue siendo el vivo.
			'Entornos en el hosting' => ( static function () use ( $sitio ) {
				$todos = SWPK_Inventario::entornos( $sitio );

				if ( ! $todos ) {
					return '<span class="swpk-gris">sin medir</span>'
						. wp_kses_post( SWPK_Vista::ayuda(
							'El hosting todavía no ha dicho qué entornos tiene este sitio. Llega con la '
							. 'próxima sincronización.'
						) );
				}

				$lineas = [];
				foreach ( $todos as $e ) {
					$lineas[] = sprintf(
						'<strong>%s</strong>%s%s',
						esc_html( (string) $e['nombre'] ),
						'' !== (string) $e['dominio']
							? ' <span class="swpk-mono swpk-gris">' . esc_html( (string) $e['dominio'] ) . '</span>'
							: '',
						! empty( $e['vivo'] )
							? ' <span class="swpk-gris">— es el que mide el panel</span>'
							: ' <span class="swpk-gris">— no se mide</span>'
					);
				}

				return implode( '<br>', $lineas )
					. ( count( $todos ) > 1
						? wp_kses_post( SWPK_Vista::ayuda(
							'Este sitio tiene más de un entorno en el hosting, y la etiqueta está puesta '
							. 'en el SITIO: cubre a los dos. Por eso un sitio ya en producción puede '
							. 'seguir saliendo con la etiqueta «Desarrollo» — la dejó puesta el entorno '
							. 'de pruebas. Todo lo que el panel mide, sondea y cuenta es el entorno '
							. 'vivo; los demás solo se nombran aquí. Si la etiqueta no corresponde, se '
							. 'corrige el grupo a mano desde Sitios y la corrección aguanta la '
							. 'sincronización.'
						) )
						: '' );
			} )(),
			'WordPress'            => esc_html( $sitio->version_wp ?: '—' ),
			'PHP'                  => esc_html( $sitio->version_php ?: '—' ),
			'Última revisión'      => $sitio->inventario_en
				? esc_html( SWPK_Frescura::fmt( 'j \d\e F \d\e Y, H:i', (string) $sitio->inventario_en ) )
					. wp_kses_post( SWPK_Vista::ayuda(
						'Cuándo ' . SWPK_Admin::proveedor() . ' revisó por última vez qué tiene instalado este sitio. No lo decide el panel: lo hace el hosting a su ritmo.'
					) )
				: '<span class="swpk-gris">nunca</span>',
			'Quién lo atiende'     => $sus_asesoras
				? esc_html( implode( ', ', $sus_asesoras ) )
				: '<span class="swpk-gris">'
					. ( SWPK_Etiquetas::es_nuestro( (string) $sitio->tag )
						? 'sin asignar'
						: 'no lleva asesora: es ' . esc_html( SWPK_Etiquetas::nombres()[ $sitio->tag ] ?? $sitio->tag ) )
					. '</span>',
			// ── UN solo renglón de correo, no dos que se contradicen ────────
			//
			// Había dos: «A quién le llega el correo», con direcciones puestas,
			// y justo debajo «Contacto del cliente: sin contacto». Las dos
			// hablan de lo mismo y dicen cosas opuestas: «¿estos correos de
			// quién son? Mauro Hernández… ¿esos quiénes son? No son clientes,
			// entonces. ¿Por qué dice cliente sin contacto?».
			//
			// Son los administradores del sitio que reporta el agente, quitando
			// los de nuestros dominios: o sea, la gente del cliente. Eso es el
			// contacto del cliente. El otro campo solo existe para corregirlo a
			// mano cuando el agente no está o se equivoca.
			//
			// Ahora es un renglón: a quién le llega, y de dónde salió.
			'Correo del cliente'   => ( static function () use ( $sitio, $contacto ) {
				$del_agente = SWPK_Correos::destinatarios_cliente( $sitio );
				$escrito    = '' !== $contacto;
				$ayuda      = 'Lo único que sale hacia el cliente es el reporte mensual de '
					. 'mantenimiento, y hoy está apagado. Ningún otro aviso del panel llega aquí.';

				if ( $escrito ) {
					return '<span class="swpk-mono">' . esc_html( $contacto ) . '</span>'
						. '<br><span class="swpk-gris" style="font-size:12px">escrito a mano en la pantalla de Sitios</span>'
						. wp_kses_post( SWPK_Vista::ayuda( $ayuda ) );
				}

				if ( $del_agente ) {
					return '<span class="swpk-mono">' . esc_html( implode( ', ', $del_agente ) ) . '</span>'
						. '<br><span class="swpk-gris" style="font-size:12px">'
						. 'los administradores del sitio, según el agente, quitando los nuestros'
						. '</span>'
						. wp_kses_post( SWPK_Vista::ayuda( $ayuda ) );
				}

				return '<span class="swpk-gris">no hay a quién</span>'
					. wp_kses_post( SWPK_Vista::ayuda(
						'Ni el agente ha mandado administradores que no sean nuestros, ni hay un '
						. 'contacto escrito a mano. Se escribe en la pantalla de Sitios.'
					) );
			} )(),
			'Verificación en dos pasos' => SWPK_Etiquetas::es_nuestro( SWPK_Inventario::grupo_de( $sitio ) )
				? ( $sitio->tiene_2fa
					? '<span style="color:var(--swpk-bien);font-weight:600">activa</span>'
					: '<span style="color:var(--swpk-critico);font-weight:600">apagada</span>' )
				: '<span class="swpk-gris">no se mide: el sitio es del cliente</span>',
			// ── El registro de actividad, con lo que de verdad se sabe ──────
			'Registro de actividad' => ( static function () use ( $sitio ) {
				$g = get_option( 'swpk_bitacora_' . md5( (string) $sitio->dominio ), [] );
				$g = is_array( $g ) ? $g : [];
				$r = (array) ( $g['resumen'] ?? [] );

				if ( empty( $r['hay_tabla'] ) ) {
					return '<span class="swpk-gris">no lo lleva</span>'
						. wp_kses_post( SWPK_Vista::ayuda(
							'Hace falta una versión del agente que lleve el registro. Sin él, lo único que '
							. 'queda es el registro del hosting, que no ve lo que pasa dentro de WordPress.'
						) );
				}

				$hoy = (int) ( $r['filas'] ?? 0 );

				// Se dice «lo lleva el agente» y no «el sitio lo guarda»,
				// porque son cosas distintas: si alguien desactiva el agente,
				// el registro deja de anotar. El día que haga falta el
				// registro, esa diferencia es justo la que importa.
				return sprintf(
					'<span style="color:var(--swpk-bien);font-weight:600">lo lleva el agente</span> '
					. '<span class="swpk-gris">· %d anotacion%s en 24 h · %d guardadas · %d días de historial</span>',
					$hoy,
					1 === $hoy ? '' : 'es',
					(int) ( $r['total'] ?? 0 ),
					(int) ( $r['dias_que_guarda'] ?? 0 )
				)
				. wp_kses_post( SWPK_Vista::ayuda(
					'El detalle se queda DENTRO del sitio; al panel solo sube este resumen cada día. '
					. 'Para ver quién entró y qué tocó, se le pide el detalle al sitio y llega en los '
					. 'siguientes cinco minutos. Lo que no se guarda: contraseñas, la IP entera —se le '
					. 'quita el último trozo— ni lo que hacen los visitantes del sitio.'
				) );
			} )(),
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
