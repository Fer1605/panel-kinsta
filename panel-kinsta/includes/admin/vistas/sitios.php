<?php
/**
 * Sitios: primero se elige el grupo, después se filtra, y el resultado va
 * paginado. Con 107 sitios, una sola lista con todo encima no se lee.
 */
defined( 'ABSPATH' ) || exit;

$todos    = SWPK_Perfiles::sitios_visibles();
$puede_asignar = SWPK_Perfiles::puede( SWPK_Perfiles::ADMINISTRAR );

// ── Cinco columnas, y las de asignar solo cuando se van a usar ─────────────
//
// «Siento que hay demasiada data, demasiado texto, y lo que quiero son
// resúmenes» (02:52). Eran nueve columnas —once para un administrador, que
// es lo que somos los dos que la usamos— y de esas, las de Asesora y
// Contacto del cliente son un FORMULARIO: campos de texto y casillas en
// todas las filas, siempre, aunque nadie vaya a asignar nada ese día.
//
// Así que la pantalla por defecto son cinco columnas de solo lectura, y
// asignar pasa a ser un modo al que se entra con un botón. Mismo formulario,
// misma acción, mismos permisos: lo único que cambia es que ya no está
// puesto encima de la mesa a todas horas.
$asignar  = $puede_asignar && 'asesoras' === ( isset( $_GET['editar'] ) ? sanitize_key( wp_unslash( $_GET['editar'] ) ) : '' );
$asesoras = $asignar ? SWPK_Perfiles::asesoras() : [];

$tag      = SWPK_Vista::tag_pedido();
$nivel    = SWPK_Vista::nivel_pedido();
$busqueda = SWPK_Vista::busqueda_pedida();

$por_tag   = SWPK_Vista::conteo_por_tag( $todos );
$del_grupo = SWPK_Vista::filtrar( $todos, $tag, '', '' );

// Las versiones que ofrece el desplegable salen del grupo elegido, no de los
// 108: en «los administra el cliente» no tiene sentido ofrecer una versión de
// PHP que solo existe en los nuestros, porque elegirla deja la tabla vacía.
//
// Y salen del grupo SIN filtrar por versión, a propósito: si se armaran sobre
// lo ya filtrado, al elegir PHP 8.2 el desplegable se quedaría con 8.2 como
// única opción y no habría forma de cambiar de idea sin quitar el filtro.
$versiones = SWPK_Vista::filtros_de_version( $del_grupo );

// Las cifras de las pastillas de riesgo sí cuentan sobre lo filtrado por
// versión. Si no, con PHP 8.2 puesto la tabla enseñaba 11 sitios y las
// pastillas sumaban 13: al pulsar «Crítico» salían menos de los que la
// pastilla prometía, y un número que no cuadra con lo que pasa al pulsarlo es
// peor que no poner número.
$por_nivel = SWPK_Vista::conteo_por_nivel(
	SWPK_Vista::filtrar( $todos, $tag, '', '', $versiones['wp'], $versiones['php'] )
);

$filtrados = SWPK_Vista::filtrar( $todos, $tag, $nivel, $busqueda, $versiones['wp'], $versiones['php'] );

// ── Tres filtros más, que son los que faltaban ──────────────────────────────
//
// «Aquí, como él dijo, esto no se puede filtrar.» Había cuatro —grupo, cómo
// está, y las dos versiones— y ninguno contestaba las preguntas con las que
// de verdad se abre esta pantalla:
//
//   «enseñame mis sitios»            → por asesora
//   «cuáles tengo que ir a actualizar a mano» → por cómo se actualiza
//   «cuáles no tienen agente»        → por agente
//
// Los tres se calculan sobre la lista YA filtrada por lo demás, así se pueden
// combinar: los sitios de Tiffany que además están en ajuste manual.
$f_asesora = isset( $_GET['asesora_f'] ) ? (int) $_GET['asesora_f'] : 0;
$f_actualiza = isset( $_GET['actualiza'] ) ? sanitize_key( wp_unslash( $_GET['actualiza'] ) ) : '';
$f_agente  = isset( $_GET['agente'] ) ? sanitize_key( wp_unslash( $_GET['agente'] ) ) : '';

if ( $f_asesora ) {
	$suyos     = SWPK_Perfiles::sitios_de_asesora( $f_asesora );
	$filtrados = array_values( array_filter(
		$filtrados,
		static fn( $s ) => in_array( (string) $s->sitio_id, $suyos, true )
	) );
}

if ( '' !== $f_actualiza ) {
	// Se pregunta de una vez por todos los sitios que quedan, no uno por uno
	// dentro del filtro: la cuenta de auto-update hace consultas.
	$auto_filtro = SWPK_Inventario::auto_update_de_varios(
		array_map( static fn( $s ) => (string) $s->sitio_id, $filtrados )
	);

	$filtrados = array_values( array_filter(
		$filtrados,
		static function ( $s ) use ( $auto_filtro, $f_actualiza ) {
			$a = $auto_filtro[ (string) $s->sitio_id ] ?? [ 'con' => 0, 'sin' => 0, 'total' => 0 ];

			if ( 0 === (int) $a['total'] ) {
				return 'sin_medir' === $f_actualiza;
			}
			if ( 0 === (int) $a['sin'] ) {
				return 'todo' === $f_actualiza;
			}
			if ( 0 === (int) $a['con'] ) {
				return 'manual' === $f_actualiza;
			}
			return 'mixto' === $f_actualiza;
		}
	) );
}

if ( '' !== $f_agente ) {
	$filtrados = array_values( array_filter(
		$filtrados,
		static fn( $s ) => ( 'si' === $f_agente ) === (bool) (int) $s->cobertura_agente
	) );
}

// Lo que más urge, primero. Es lo que dice el pie de la tarjeta en la maqueta,
// y sin el orden ese pie sería mentira: la lista venía como la devolvía la
// base, que es por nombre. A igualdad de nivel, por nombre, para que dos
// recargas seguidas no barajen la tabla.
//
// ─── El peso se calcula ANTES de ordenar, y no dentro de la comparación ───
//
// Parece un detalle y era la pantalla entera. nivel_del_sitio() hace consultas
// y mira el feed de vulnerabilidades; metido dentro del comparador, PHP lo
// llamaba una vez por cada comparación: con 108 sitios, unas setecientas veces
// en vez de 108. Medido en el panel de desarrollo con la cartera real, esta
// pantalla tardaba 4,9 segundos mientras las demás tardaban medio segundo.
// Ahora se calcula una vez por sitio y se ordena sobre el resultado.
$peso_de = [];
foreach ( $filtrados as $s ) {
	$peso_de[ (string) $s->sitio_id ] = SWPK_Riesgo::peso( SWPK_Riesgo::nivel_del_sitio( (string) $s->sitio_id ) );
}

usort(
	$filtrados,
	static fn( $a, $b ) =>
		[ $peso_de[ (string) $b->sitio_id ], $a->nombre_visible ]
		<=> [ $peso_de[ (string) $a->sitio_id ], $b->nombre_visible ]
);
[ $pagina_de_sitios, $pagina, $paginas ] = SWPK_Vista::paginar( $filtrados, SWPK_Vista::pagina_pedida() );

// A quién le va a llegar el aviso de cada sitio de esta página. Sale de los
// administradores que reporta el agente, menos los de nuestros dominios, que
// es la misma cuenta que hace el envío. En dos consultas para las veinte
// filas, no dos por fila.
$destinos_pagina = SWPK_Correos::destinatarios_de_varios( $pagina_de_sitios );

// A dónde vuelve el botón de analizar: a esta misma página, con sus filtros.
// Analizar un sitio desde la página 3 del grupo y aterrizar en el Resumen
// obliga a volver a buscarlo.
$volver = add_query_arg(
	array_filter(
		[
			'page'  => 'panel-kinsta-sitios',
			'tag'   => $tag,
			'nivel' => $nivel,
			'q'     => $busqueda,
			'wp'    => $versiones['wp'],
			'php'   => $versiones['php'],
			'p'     => $pagina > 1 ? $pagina : '',
		]
	),
	'admin.php'
);
?>
<div class="wrap">
	<?php
	// Las pastillas de grupo, como en la maqueta: «Todos» primero y después
	// los tres grupos, cada una con su cuenta y la puesta marcada.
	//
	// Antes esto era un selector de tarjetas grandes y la pantalla NO enseñaba
	// nada hasta elegir un grupo. La maqueta no tiene esa puerta: abre con los
	// 108 y el grupo es un filtro más. Se quitó la puerta.
	$pastillas_tag = [
		[
			'texto'  => 'Todos',
			'n'      => count( $todos ),
			'url'    => admin_url( 'admin.php?page=panel-kinsta-sitios' ),
			'activa' => '' === $tag,
		],
	];

	foreach ( SWPK_Etiquetas::nombres() as $t => $nombre_t ) {
		if ( empty( $por_tag[ $t ] ) ) {
			continue;
		}

		$pastillas_tag[] = [
			'texto'  => $nombre_t,
			'n'      => $por_tag[ $t ],
			'url'    => add_query_arg(
				[ 'page' => 'panel-kinsta-sitios', 'tag' => $t ],
				admin_url( 'admin.php' )
			),
			'activa' => $t === $tag,
		];
	}

	SWPK_Vista::cabecera(
		'Sitios',
		esc_html( SWPK_Vista::plural( count( $filtrados ), 'sitio', 'sitios' ) . '.' ),
		SWPK_Vista::pastillas( $pastillas_tag )
	);
	?>
	<?php SWPK_Admin::mostrar_mensaje(); ?>
	<?php SWPK_Frescura::aviso(); ?>

	<?php if ( ! $todos ) : ?>
		<div class="notice notice-info"><p>
			<?php
			echo SWPK_Perfiles::puede( SWPK_Perfiles::VER_TODO )
				? 'No hay inventario todavía. Corré la sincronización desde el Resumen.'
				: 'Todavía no tenés sitios asignados. Pedile a dirección que te los asigne.';
			?>
		</p></div>
		</div>
		<?php return; ?>
	<?php endif; ?>

	<?php if ( '' !== $tag && ! SWPK_Etiquetas::es_nuestro( $tag ) ) : ?>
		<p style="color:#50575e;max-width:800px;margin:0">
			Estos sitios los administra el cliente. Quedan fuera de la doble autenticación y de la
			lectura de usuarios: no podemos instalarles nada mientras no se les venda. Lo que sí se
			vigila es todo lo que viene de <?php echo esc_html( SWPK_Admin::proveedor() ); ?>, que es
			justo lo que hay que avisarles.
		</p>
	<?php endif; ?>

	<?php
	// Los botones de la pantalla van en el encabezado de la tarjeta, no en una
	// barra suelta encima: la maqueta no pone nada entre la cabecera y la
	// primera tarjeta, y una fila de botones flotando ahí era justo lo que
	// hacía que esta pantalla no se pareciera.
	//
	// Aquí había también un «Revisar todos», que era «Correr ahora» con otro
	// nombre. Se quitó: el mismo botón en tres pantallas y con tres nombres
	// distintos es un botón que nadie sabe cuál apretó. Vive en el Resumen.
	ob_start();
	SWPK_Admin::boton_exportar( 'sitios' );
	$botones_cartera = ob_get_clean();
	?>

	<?php
	// El buscador se mudó aquí dentro: antes era un formulario aparte, y con
	// dos formularios en pantalla buscar borraba la versión elegida y elegir
	// una versión borraba lo buscado. Ahora los tres viajan juntos.
	// La tarjeta se abre aquí y no antes: lo de arriba —el selector de grupo,
	// la nota del cliente— es contexto de la pantalla, no contenido de la
	// tabla. Dentro de la tarjeta va solo lo que la tabla filtra y pagina.
	SWPK_Vista::caja_abrir(
		'La cartera',
		'<span style="margin-right:12px">Ordenados por lo que más urge</span>' . $botones_cartera,
		'',
		true
	);

	// Los cuatro selectores de la maqueta: grupo, cómo está, y las dos
	// versiones. El grupo también está arriba en pastillas; se repite porque
	// las pastillas son para saltar de un grupo a otro y el selector es para
	// combinarlo con lo demás sin perder lo que ya estaba puesto.
	//
	// «Cómo está» era una fila de pastillas aparte. Pasa a selector, como en
	// la maqueta, y lleva la cuenta en el texto: elegir un nivel que dejaría
	// la tabla vacía es un clic perdido.
	$opciones_nivel = [];
	foreach ( SWPK_Riesgo::nombres() as $clave_n => $nombre_n ) {
		if ( empty( $por_nivel[ $clave_n ] ) ) {
			continue;
		}
		$opciones_nivel[ $clave_n ] = sprintf( '%s (%d)', $nombre_n, (int) $por_nivel[ $clave_n ] );
	}

	// Las asesoras que de verdad llevan algún sitio, con cuántos. Una lista con
	// gente a cero obliga a probar para descubrir que no hay nada detrás.
	$opciones_asesora = [];
	foreach ( SWPK_Perfiles::asesoras() as $a_opt ) {
		$cuantos = count( SWPK_Perfiles::sitios_de_asesora( (int) $a_opt->ID ) );
		if ( $cuantos ) {
			$opciones_asesora[ (string) $a_opt->ID ] = sprintf( '%s (%d)', $a_opt->display_name, $cuantos );
		}
	}

	SWPK_Vista::barra_de_filtros(
		'panel-kinsta-sitios',
		[
			'tag' => [
				'etiqueta' => 'Grupo',
				'opciones' => array_intersect_key(
					SWPK_Etiquetas::nombres(),
					array_filter( $por_tag )
				),
				'actual'   => $tag,
				'todas'    => 'Todos los grupos',
			],
			'nivel' => [
				// ── «Cómo está» no dice qué se está eligiendo ───────────────
				//
				//   «no "cómo está", porque "cómo está" como que no se
				//    entiende. Entre que tiene que ser básico, pero no tan
				//    básico que se oye ya más corriente.»
				//
				// Lo que hay dentro son Crítico, Alto, Medio y Al día: eso es
				// el riesgo del sitio, y llamarlo por su nombre no lo hace más
				// técnico, lo hace más corto.
				'etiqueta' => 'Riesgo',
				'opciones' => $opciones_nivel,
				'actual'   => $nivel,
				'todas'    => 'Cualquier riesgo',
			],
		] + $versiones['campos'] + [
			'actualiza' => [
				'etiqueta' => 'Se actualiza',
				'opciones' => [
					'todo'      => 'Todo automático',
					'mixto'     => 'Alguno en manual',
					'manual'    => 'Todo en ajuste manual',
					'sin_medir' => 'Sin medir',
				],
				'actual'   => $f_actualiza,
				'todas'    => 'Se actualiza',
			],
			'asesora_f' => [
				'etiqueta' => 'Asesora',
				'opciones' => $opciones_asesora,
				'actual'   => $f_asesora ? (string) $f_asesora : '',
				'todas'    => 'Cualquier asesora',
			],
			'agente' => [
				'etiqueta' => 'Agente',
				'opciones' => [ 'si' => 'Con agente', 'no' => 'Sin agente' ],
				'actual'   => $f_agente,
				'todas'    => 'Con y sin agente',
			],
		],
		[],
		$busqueda
	);
	?>

	<?php if ( ! $pagina_de_sitios ) : ?>
		<div class="notice notice-info inline"><p>Ningún sitio coincide con ese filtro.</p></div>
	<?php else : ?>

		<?php if ( $puede_asignar ) : ?>
			<?php
			// La puerta del modo de asignación. Conserva los filtros puestos:
			// se entra a asignar sobre lo que uno está mirando, no sobre los
			// 109 otra vez.
			$url_editar = $asignar
				? remove_query_arg( 'editar' )
				: add_query_arg( 'editar', 'asesoras' );
			?>
			<p class="swpk-barra-modo">
				<?php if ( $asignar ) : ?>
					<strong>Asignando asesoras y contactos.</strong>
					<a href="<?php echo esc_url( $url_editar ); ?>">Salir sin guardar</a>
				<?php else : ?>
					<a class="button button-small" href="<?php echo esc_url( $url_editar ); ?>">Asignar asesoras y contactos</a>
					<?php echo wp_kses_post( SWPK_Vista::ayuda(
						'Abre las dos columnas de edición sobre los sitios que estás viendo ahora. '
						. 'Fuera de ese modo la tabla es de solo lectura, que es como se mira a diario.'
					) ); ?>

					<?php
					// El reparto en lote, para no ir sitio por sitio. La misma
					// regla corre sola en cada sincronización; esto es para no
					// esperar a mañana.
					?>
					<a class="button button-small"
						href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=swpk_repartir_asesoras' ), 'swpk_repartir_asesoras' ) ); ?>"
						onclick="return confirm('Pone a todas las asesoras del Equipo en cada sitio nuestro que no tenga ninguna, y se las quita a los del cliente. Lo que ya esté asignado a mano no se toca. ¿Seguimos?');">
						Poner las asesoras en todos los nuestros
					</a>
					<?php echo wp_kses_post( SWPK_Vista::ayuda(
						'Auto update y Desarrollo llevan a las dos asesoras. Los del cliente —No auto '
						. 'update— se quedan sin ninguna: a esos el panel no les manda el semanal. Un '
						. 'sitio nuestro que ya tenga a alguien puesto a mano se queda como está.'
					) ); ?>
				<?php endif; ?>
			</p>
		<?php endif; ?>

		<?php if ( $asignar ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="swpk_asignar">
			<?php wp_nonce_field( 'swpk_asignar' ); ?>
		<?php endif; ?>

		<?php
		$auto_de = SWPK_Inventario::auto_update_de_varios(
			array_map( static fn( $s ) => (string) $s->sitio_id, $pagina_de_sitios )
		);
		?>
		<?php SWPK_Vista::paginador( $pagina, $paginas, count( $filtrados ) ); ?>
		<?php
		// ── Cuántas normas no cumple cada sitio de esta página ──────────────
		//
		// Se lee una vez para toda la página y no una por fila: la tabla de
		// cumplimiento son 109 sitios por 73 normas, y preguntar sitio a sitio
		// dentro del bucle son veinte consultas por pantalla.
		$cumple_mapa = SWPK_Normas::cumplimiento();
		?>
		<table class="widefat striped swpk-tabla swpk-tabla-sitios">
			<thead>
				<tr>
					<th>Sitio</th>
					<th style="width:86px">Riesgo</th>
					<th style="width:230px">Qué le falta</th>
					<th style="width:120px">
						Se actualiza
						<?php echo wp_kses_post( SWPK_Vista::ayuda(
							'Cuántos módulos hay que entrar a actualizar a mano, según el hosting. Es un '
							. 'dato medido, no la etiqueta: un sitio puede estar etiquetado como Desarrollo '
							. 'y tener todo en automático, y al revés.'
						) ); ?>
					</th>
					<?php if ( $asignar ) : ?>
						<th style="width:170px">Asesora</th>
						<th style="width:240px">
							Contacto del cliente
							<?php echo wp_kses_post( SWPK_Vista::ayuda(
								'A quién se le manda el reporte de lo que se hizo. En los sitios que '
								. 'llevamos nosotros no hace falta escribirlo: los destinatarios los da el '
								. 'agente instalado, y se ven en la ficha de cada sitio. En los que lleva '
								. 'el cliente no hay agente, así que ahí sí hay que escribirlo a mano — y '
								. 'son justo los que más hay que avisar.'
							) ); ?>
						</th>
					<?php endif; ?>
					<th style="width:110px"></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $pagina_de_sitios as $s ) : ?>
				<?php
				$nivel_s   = SWPK_Riesgo::nivel_del_sitio( $s->sitio_id );
				$hallazgos = SWPK_Riesgo::hallazgos( $s->sitio_id );
				$vulns     = 0;
				foreach ( $hallazgos as $h ) {
					if ( 'actualizacion_pendiente' !== $h['motivo'] ) {
						++$vulns;
					}
				}
				$updates = count( $hallazgos ) - $vulns;

				$sin_cumplir = 0;
				foreach ( ( $cumple_mapa[ (string) $s->sitio_id ] ?? [] ) as $c ) {
					if ( SWPK_Normas::FALLA === ( $c->estado ?? '' ) ) {
						++$sin_cumplir;
					}
				}

				$grupo_s  = SWPK_Inventario::grupo_de( $s );
				$suyas    = SWPK_Perfiles::asesoras_de( (string) $s->sitio_id );
				$nuestro  = SWPK_Etiquetas::es_nuestro( (string) $s->tag );
				$nombres  = [];
				foreach ( $suyas as $id_a ) {
					$u = get_user_by( 'id', $id_a );
					if ( $u ) {
						$nombres[] = $u->display_name;
					}
				}
				?>
				<tr>
					<?php
					// ── La franja de grupo, que él pidió con esas palabras ──
					//
					// «Le podríamos colocar como grupo… un colorcito, como una
					// línea, una burbuja, entonces yo ya sé» (04:14). Una
					// franja de 4 px al inicio de la fila, en frío —azul, lila,
					// gris—, que no se confunde con el rojo del riesgo porque
					// no es cálida. Así el color cálido de la fila queda para
					// una sola cosa: cómo está el sitio.
					?>
					<td class="swpk-col-sitio swpk-franja-<?php echo esc_attr( $grupo_s ); ?>">
						<a href="<?php echo esc_url( SWPK_Vista::url_del_sitio( (string) $s->sitio_id, 'panel-kinsta-sitios' ) ); ?>">
							<strong><?php echo esc_html( $s->nombre_visible ); ?></strong>
						</a>
						<div class="swpk-sitio-pie">
							<span class="swpk-mono"><?php echo esc_html( $s->dominio ); ?></span>
							<span class="swpk-gris">·</span>
							<?php echo esc_html( SWPK_Etiquetas::nombres()[ $grupo_s ] ?? 'Sin etiqueta' ); ?>
						</div>
						<?php
						// ── «Ver todo»: lo que no se mira a diario ──────────
						//
						// Versiones, última revisión, doble paso y asesora
						// salen de la tabla y entran aquí plegados. Es la
						// regla 7 de su panel: «el detalle se despliega, no se
						// muestra». Sigue a un clic, no a dos pantallas.
						?>
						<details class="swpk-vertodo">
							<summary>Ver todo</summary>
							<dl>
								<dt>WordPress</dt><dd class="swpk-mono"><?php echo esc_html( $s->version_wp ?: '—' ); ?></dd>
								<dt>PHP</dt><dd class="swpk-mono"><?php echo esc_html( $s->version_php ?: '—' ); ?></dd>
								<dt>Revisado</dt>
								<dd><?php echo esc_html(
									! empty( $s->inventario_en )
										? SWPK_Frescura::fmt( 'j M, H:i', (string) $s->inventario_en )
										: '—'
								); ?></dd>
								<?php if ( $nuestro ) : ?>
									<dt>Doble paso</dt>
									<dd><?php echo $s->tiene_2fa
										? esc_html( '✓ ' . $s->plugin_2fa )
										: '<span class="swpk-rojo">Pendiente</span>'; ?></dd>
								<?php endif; ?>
								<dt>Asesora</dt>
								<dd><?php echo $nombres ? esc_html( implode( ', ', $nombres ) ) : '—'; ?></dd>
							</dl>
						</details>
					</td>

					<td><?php echo wp_kses_post( SWPK_Admin::nivel_badge( $nivel_s ) ); ?></td>

					<?php
					// ── «Qué le falta»: frases, no cifras sueltas ───────────
					//
					// Eran dos números pegados en una columna de 124 px —«1 con
					// vulnerabilidad / 2 por actualizar»— que había que
					// interpretar. Ahora cada línea se lee sola y dice de qué
					// es el número, con la vulnerabilidad arriba y en negrita
					// porque es la que manda.
					?>
					<td class="swpk-falta">
						<?php if ( SWPK_Riesgo::SIN_DATOS === $nivel_s ) : ?>
							<span class="swpk-gris">Sin escanear por <?php echo esc_html( SWPK_Admin::proveedor() ); ?></span>
						<?php elseif ( ! $vulns && ! $updates && ! $sin_cumplir ) : ?>
							<span class="swpk-gris">Nada pendiente.</span>
						<?php else : ?>
							<?php if ( $vulns ) : ?>
								<div><strong class="swpk-rojo"><?php
									printf(
										'%d %s conocida%s',
										(int) $vulns,
										1 === $vulns ? 'vulnerabilidad' : 'vulnerabilidades',
										1 === $vulns ? '' : 's'
									);
								?></strong></div>
							<?php endif; ?>
							<?php if ( $updates ) : ?>
								<div><?php
									printf(
										'%d actualizaci%s pendiente%s',
										(int) $updates,
										1 === $updates ? 'ón' : 'ones',
										1 === $updates ? '' : 's'
									);
								?></div>
							<?php endif; ?>
							<?php if ( $sin_cumplir ) : ?>
								<div><a href="<?php echo esc_url( SWPK_Vista::url_del_sitio( (string) $s->sitio_id, 'panel-kinsta-sitios' ) . '&seccion=normas&estado=' . SWPK_Normas::FALLA ); ?>"><?php
									printf( '%d norma%s sin cumplir', (int) $sin_cumplir, 1 === $sin_cumplir ? '' : 's' );
								?></a></div>
							<?php endif; ?>
						<?php endif; ?>
						<?php if ( SWPK_Inventario::dato_viejo( $s ) ) : ?>
							<div class="swpk-mudo" style="font-size:12px">dato de hace <?php echo (int) SWPK_Inventario::dias_sin_escanear( $s ); ?> días</div>
						<?php endif; ?>
					</td>

					<td><?php echo wp_kses_post( SWPK_Admin::auto_update_badge( $auto_de[ (string) $s->sitio_id ] ?? [ 'con' => 0, 'sin' => 0, 'total' => 0 ] ) ); ?></td>

					<?php if ( $asignar ) : ?>
						<td>
							<?php
							// ── Varias asesoras, no una ────────────────────
							//
							// «Que tienen que ser las 2 asesoras (...) que no
							// sea solo un select de una persona.» Son casillas
							// y no un <select multiple>: en un multiple hay que
							// saber que se escoge con Ctrl, no se ve de un
							// vistazo quién está marcada, y con dos nombres
							// ocupa más que las casillas.
							//
							// A los sitios del cliente —no_update— no se les
							// asigna nadie, y no se dibuja el control: así
							// nadie marca dos nombres y se queda esperando que
							// el panel los guarde. Ver asignar_asesoras().
							?>
							<?php if ( ! $asesoras ) : ?>
								<?php printf(
									'<span class="swpk-gris" style="font-size:12px">Nadie en el <a href="%s">Equipo</a> todavía</span>',
									esc_url( admin_url( 'admin.php?page=panel-kinsta-equipo' ) )
								); ?>
							<?php elseif ( ! $nuestro ) : ?>
								<span class="swpk-gris" style="font-size:12px"
									title="A los sitios del cliente no se les asigna asesora desde aquí.">
									<?php echo $nombres ? esc_html( implode( ', ', $nombres ) ) : '—'; ?>
								</span>
							<?php else : ?>
								<div class="swpk-asesoras">
									<?php foreach ( $asesoras as $a ) : ?>
										<?php
										// Solo el nombre de pila: «Luisa Fernanda
										// Álvarez» parte en tres renglones y con
										// cuatro personas la fila crece doce.
										$partes = explode( ' ', (string) $a->display_name );
										?>
										<label title="<?php echo esc_attr( $a->display_name ); ?>">
											<input type="checkbox"
												name="asesora[<?php echo esc_attr( $s->sitio_id ); ?>][]"
												value="<?php echo (int) $a->ID; ?>"
												<?php checked( in_array( (int) $a->ID, $suyas, true ) ); ?>>
											<?php echo esc_html( $partes[0] ); ?>
										</label>
									<?php endforeach; ?>
									<input type="hidden" name="asesora_vista[<?php echo esc_attr( $s->sitio_id ); ?>]" value="1">
								</div>
							<?php endif; ?>
						</td>
						<td>
							<?php
							// El campo trae la lista del agente como VALOR, no como
							// marcador. De marcador no se podía leer entera, no se podía
							// corregir una sola dirección sin volver a escribirlas todas, y
							// en gris parecía un campo apagado: «deberían aparecer como que
							// alguien los escribió».
							//
							// Y sigue sin congelarse una copia vieja: al guardar, si lo que
							// hay en el campo es exactamente lo que dice el agente, se
							// guarda vacío —o sea, «seguí al agente»—. Solo se guarda lo
							// que alguien haya cambiado de verdad. Ver accion_asignar().
							$info       = $destinos_pagina[ (string) $s->sitio_id ] ?? [ 'correos' => [], 'motivo' => 'sin_agente' ];
							$del_agente = $info['correos'];

							// Qué se pone de marcador, y qué se explica, según POR QUÉ no
							// hay correos. Decir «no tiene el agente instalado» cuando lo
							// que pasa es que el agente aún no ha mandado su lista manda a
							// instalar algo que ya está puesto.
							[ $marcador, $explica ] = $del_agente
								? [
									implode( ', ', $del_agente ),
									'Estas direcciones las reporta el agente del sitio: sus administradores, '
									. 'menos los de los dominios de la casa que haya en Ajustes → Correos. Si no '
									. 'las tocás, la lista se mantiene sola y no se guarda ninguna copia. En '
									. 'cuanto cambies algo, manda lo que dejes escrito y deja de seguir al '
									. 'agente; para volver a seguirlo, vaciá el campo y guardá. Varias '
									. 'direcciones, separadas por coma.',
								]
								: match ( $info['motivo'] ) {
									'sin_reporte' => [
										'el agente aún no ha mandado la lista',
										'Este sitio sí tiene el agente instalado, pero todavía no ha mandado su '
										. 'lista de administradores: llega con su próximo reporte y entonces el '
										. 'campo se llena solo. Mientras tanto se puede escribir a mano, y lo '
										. 'escrito manda sobre lo que mande el agente.',
									],
									'todos_de_casa' => [
										'todos sus administradores son de la casa',
										'El agente reportó sus administradores, pero todos son de los dominios '
										. 'de la casa puestos en Ajustes → Correos, así que ninguno cuenta como '
										. 'contacto del cliente. Si hay que avisarle a alguien de fuera, se '
										. 'escribe aquí.',
									],
									default => [
										'correo del cliente, separados por coma',
										'Este sitio no tiene el agente instalado, así que el panel no puede '
										. 'saber quiénes son sus administradores: aquí hay que escribirlo a '
										. 'mano. Varias direcciones, separadas por coma. Si se queda vacío, su '
										. 'aviso no sale.',
									],
								};

							$auto    = $del_agente ? implode( ', ', $del_agente ) : '';
							$escrito = trim( (string) $s->contacto_cliente );
							$valor   = '' !== $escrito ? $escrito : $auto;
							?>
							<input type="hidden"
								name="contacto_auto[<?php echo esc_attr( $s->sitio_id ); ?>]"
								value="<?php echo esc_attr( $auto ); ?>">
							<span class="swpk-campo-con-ayuda">
								<input type="text"
									title="<?php echo esc_attr( $valor ); ?>"
									name="contacto[<?php echo esc_attr( $s->sitio_id ); ?>]"
									value="<?php echo esc_attr( $valor ); ?>"
									placeholder="<?php echo esc_attr( $marcador ); ?>">
								<?php echo wp_kses_post( SWPK_Vista::ayuda( $explica ) ); ?>
							</span>
						</td>
					<?php endif; ?>

					<td class="swpk-col-ir">
						<a class="button button-small"
							href="<?php echo esc_url( SWPK_Vista::url_del_sitio( (string) $s->sitio_id, 'panel-kinsta-sitios' ) ); ?>">Ver sitio</a>
						<?php if ( $puede_asignar ) : ?>
							<a class="swpk-enlace-pie"
								href="<?php echo esc_url( SWPK_Admin::url_analizar( (string) $s->sitio_id, $volver ) ); ?>"
								data-espera="Revisando…"
								title="Vuelve a preguntarle a <?php echo esc_attr( SWPK_Admin::proveedor() ); ?> qué tiene este sitio, ahora mismo">
								Analizar
							</a>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<?php if ( $asignar ) : ?>
			<?php submit_button( 'Guardar esta página' ); ?>
			</form>
		<?php endif; ?>

		<?php SWPK_Vista::paginador( $pagina, $paginas, count( $filtrados ) ); ?>
	<?php endif; ?>
	<?php SWPK_Vista::caja_cerrar(); ?>

	<?php
	// El sello, la tira de perfil y los botones van AL PIE, como en la maqueta:
	// entre la cabecera y la primera tarjeta ella no pone nada. Arriba, el
	// sello solo —que son cuatro renglones de fechas— empujaba la tabla fuera
	// de la pantalla.
	?>
	<?php SWPK_Admin::aviso_demo(); ?>
</div>
