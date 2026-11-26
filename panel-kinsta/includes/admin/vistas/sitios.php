<?php
/**
 * Sitios: primero se elige el grupo, después se filtra, y el resultado va
 * paginado. Con 107 sitios, una sola lista con todo encima no se lee.
 */
defined( 'ABSPATH' ) || exit;

$todos    = SWPK_Perfiles::sitios_visibles();
$asignar  = SWPK_Perfiles::puede( SWPK_Perfiles::ADMINISTRAR );
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
				'etiqueta' => 'Cómo está',
				'opciones' => $opciones_nivel,
				'actual'   => $nivel,
				'todas'    => 'Cómo está',
			],
		] + $versiones['campos'],
		[],
		$busqueda
	);
	?>

	<?php if ( ! $pagina_de_sitios ) : ?>
		<div class="notice notice-info inline"><p>Ningún sitio coincide con ese filtro.</p></div>
	<?php else : ?>

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
		<table class="widefat striped swpk-tabla">
			<thead>
				<tr>
					<th>Sitio</th>
					<th style="width:96px">Grupo</th>
					<th style="width:112px">
						Se actualiza
						<?php echo wp_kses_post( SWPK_Vista::ayuda(
							'Si los módulos del sitio tienen puesta la actualización automática, según el '
							. 'hosting. Es un dato medido, no la etiqueta: un sitio puede estar etiquetado '
							. 'como Desarrollo y tener todo en automático, y al revés.'
						) ); ?>
					</th>
					<th style="width:82px">Cómo está</th>
					<th style="width:124px">Pendientes</th>
					<th style="width:104px">
						Versiones
						<?php echo wp_kses_post( SWPK_Vista::ayuda( 'WordPress y PHP, en ese orden.' ) ); ?>
					</th>
					<?php if ( SWPK_Etiquetas::es_nuestro( $tag ) ) : ?>
						<th style="width:96px">Doble paso</th>
					<?php endif; ?>
					<th style="width:96px">Revisado</th>
					<th style="width:165px">Asesora</th>
					<?php if ( $asignar ) : ?>
						<th style="width:260px">
							Contacto del cliente
							<?php echo wp_kses_post( SWPK_Vista::ayuda(
								'A quién se le manda el reporte de lo que se hizo. En los sitios que '
								. 'llevamos nosotros no hace falta escribirlo: los destinatarios los da el '
								. 'agente instalado, y se ven en la ficha de cada sitio. En los que lleva '
								. 'el cliente no hay agente, así que ahí sí hay que escribirlo a mano — y '
								. 'son justo los que más hay que avisar.'
							) ); ?>
						</th>
						<th style="width:84px"></th>
					<?php endif; ?>
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
				?>
				<tr>
					<td>
						<a href="<?php echo esc_url( SWPK_Vista::url_del_sitio( (string) $s->sitio_id, 'panel-kinsta-sitios' ) ); ?>">
							<strong><?php echo esc_html( $s->nombre_visible ); ?></strong>
						</a><br>
						<span class="swpk-mono" style="color:#50575e"><?php echo esc_html( $s->dominio ); ?></span>
					</td>
					<td><?php echo SWPK_Admin::grupo_badge( SWPK_Inventario::grupo_de( $s ) ); ?></td>
					<td><?php echo wp_kses_post( SWPK_Admin::auto_update_badge( $auto_de[ (string) $s->sitio_id ] ?? [ 'con' => 0, 'sin' => 0, 'total' => 0 ] ) ); ?></td>
					<td><?php echo wp_kses_post( SWPK_Admin::nivel_badge( $nivel_s ) ); ?></td>
					<td>
						<?php if ( SWPK_Riesgo::SIN_DATOS === $nivel_s ) : ?>
							<span style="color:#6b7280">sin escanear por <?php echo esc_html( SWPK_Admin::proveedor() ); ?></span>
						<?php else : ?>
							<?php if ( $vulns ) : ?>
								<strong style="color:#9e0b10"><?php echo (int) $vulns; ?> con vulnerabilidad</strong><br>
							<?php endif; ?>
							<?php echo count( $hallazgos ) - $vulns; ?> por actualizar
							<?php if ( SWPK_Inventario::dato_viejo( $s ) ) : ?>
								<br><span style="color:#8a6a00;font-size:12px" title="<?php echo esc_attr( SWPK_Admin::proveedor() . ' no lo revisa desde hace ' . (int) SWPK_Inventario::dias_sin_escanear( $s ) . ' días' ); ?>">
									dato de hace <?php echo (int) SWPK_Inventario::dias_sin_escanear( $s ); ?> días
								</span>
							<?php endif; ?>
						<?php endif; ?>
					</td>
					<td class="swpk-mono"><?php
						printf(
							'%s <span class="swpk-gris">·</span> %s',
							esc_html( $s->version_wp ?: '—' ),
							esc_html( $s->version_php ?: '—' )
						);
					?></td>
					<?php if ( SWPK_Etiquetas::es_nuestro( $tag ) ) : ?>
						<td>
							<?php if ( $s->tiene_2fa ) : ?>
								<span style="color:#0b5ed7">✓ <?php echo esc_html( $s->plugin_2fa ); ?></span>
							<?php else : ?>
								<span style="color:#9e0b10">Pendiente</span>
							<?php endif; ?>
						</td>
					<?php endif; ?>
					<td class="swpk-gris">
						<?php
						echo esc_html(
							! empty( $s->inventario_en )
								? SWPK_Frescura::fmt( 'j M, H:i', (string) $s->inventario_en )
								: '—'
						);
						?>
					</td>
					<td>
						<?php
						// ── Varias asesoras, no una ────────────────────────
						//
						// «Que tienen que ser las 2 asesoras (...) que no sea
						// solo un select de una persona.» Son casillas y no un
						// <select multiple>: en un multiple hay que saber que
						// se escoge con Ctrl, no se ve de un vistazo quién
						// está marcada, y con dos nombres ocupa más que las
						// casillas.
						//
						// A los sitios del cliente —no_update— no se les
						// asigna nadie, y no se dibuja el control: así nadie
						// marca dos nombres y se queda esperando que el panel
						// los guarde. Ver SWPK_Perfiles::asignar_asesoras().
						//
						// Las dos variables se calculan ANTES del if: dentro
						// de una rama solo existirían cuando esa rama corre.
						$suyas   = SWPK_Perfiles::asesoras_de( (string) $s->sitio_id );
						$nuestro = SWPK_Etiquetas::es_nuestro( (string) $s->tag );
						?>
						<?php if ( $asignar && ! $asesoras ) : ?>
							<?php
							// Sin nadie en el Equipo no hay a quién asignar, y un
							// desplegable vacío no lo explica. Se dice y se enseña
							// dónde se arregla.
							printf(
								'<span class="swpk-gris" style="font-size:12px">Nadie en el <a href="%s">Equipo</a> todavía</span>',
								esc_url( admin_url( 'admin.php?page=panel-kinsta-equipo' ) )
							);
							?>
						<?php elseif ( $asignar && ! $nuestro ) : ?>
							<?php
							// A estos no se les asigna, pero algunos YA tienen a
							// alguien de antes. Si se enseñara «no lleva asesora»
							// el panel estaría mintiendo. Se enseña lo que hay y
							// se dice que no se cambia desde aquí.
							//
							// No se borran solos: esa asignación es también lo que
							// decide qué sitios ve cada persona al entrar, y
							// quitarla de golpe le cerraría la puerta a alguien sin
							// avisar. Lo que no sale es el correo, y eso ya lo
							// frena el interruptor del aviso semanal.
							$nombres = [];
							foreach ( $suyas as $id_a ) {
								$u = get_user_by( 'id', $id_a );
								if ( $u ) {
									$nombres[] = $u->display_name;
								}
							}
							?>
							<span class="swpk-gris" style="font-size:12px">
								<?php if ( $nombres ) : ?>
									<?php echo esc_html( implode( ', ', $nombres ) ); ?><br>
									<em>de antes; no se cambia desde aquí porque es
									<?php echo esc_html( SWPK_Etiquetas::nombres()[ $s->tag ] ?? $s->tag ); ?></em>
								<?php else : ?>
									No lleva asesora: es <?php echo esc_html( SWPK_Etiquetas::nombres()[ $s->tag ] ?? $s->tag ); ?>
								<?php endif; ?>
							</span>
						<?php elseif ( $asignar ) : ?>
							<div class="swpk-asesoras">
								<?php foreach ( $asesoras as $a ) : ?>
									<label style="display:block;font-size:12px;line-height:1.7">
										<input type="checkbox"
											name="asesora[<?php echo esc_attr( $s->sitio_id ); ?>][]"
											value="<?php echo (int) $a->ID; ?>"
											<?php checked( in_array( (int) $a->ID, $suyas, true ) ); ?>>
										<?php echo esc_html( $a->display_name ); ?>
									</label>
								<?php endforeach; ?>
								<?php
								// Sin esto, desmarcar a todas no manda nada para este
								// sitio y el panel no se enteraría de que hay que
								// dejarlo sin nadie.
								?>
								<input type="hidden" name="asesora_vista[<?php echo esc_attr( $s->sitio_id ); ?>]" value="1">
							</div>
						<?php else : ?>
							<?php
							if ( ! $suyas ) {
								echo '—';
							} else {
								$nombres = [];
								foreach ( $suyas as $id ) {
									$u = get_user_by( 'id', $id );
									if ( $u ) {
										$nombres[] = $u->display_name;
									}
								}
								echo esc_html( implode( ', ', $nombres ) );
							}
							?>
						<?php endif; ?>
					</td>
					<?php if ( $asignar ) : ?>
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
							?>
							<?php
							$auto   = $del_agente ? implode( ', ', $del_agente ) : '';
							$escrito = trim( (string) $s->contacto_cliente );
							$valor  = '' !== $escrito ? $escrito : $auto;
							?>
							<input type="hidden"
								name="contacto_auto[<?php echo esc_attr( $s->sitio_id ); ?>]"
								value="<?php echo esc_attr( $auto ); ?>">
							<?php
							// La burbuja va A LA PAR del campo, no debajo.
							//
							// El campo era `width:100%`, así que ocupaba la
							// celda entera y la «i» caía en el renglón
							// siguiente, suelta y sin nada que la explicara:
							// «no entiendo dónde está esto, la i al lado de
							// los correos». La caja flexible le deja a la
							// burbuja sus 16 px y el campo se queda con el
							// resto — o sea, el campo se angosta lo justo
							// para que quepan los dos en la misma línea.
							?>
							<span class="swpk-campo-con-ayuda">
								<input type="text"
									title="<?php echo esc_attr( $valor ); ?>"
									name="contacto[<?php echo esc_attr( $s->sitio_id ); ?>]"
									value="<?php echo esc_attr( $valor ); ?>"
									placeholder="<?php echo esc_attr( $marcador ); ?>">
								<?php echo wp_kses_post( SWPK_Vista::ayuda( $explica ) ); ?>
							</span>
						</td>
						<td>
							<a class="button button-small"
								href="<?php echo esc_url( SWPK_Admin::url_analizar( (string) $s->sitio_id, $volver ) ); ?>"
								data-espera="Revisando…"
								title="Vuelve a preguntarle a <?php echo esc_attr( SWPK_Admin::proveedor() ); ?> qué tiene este sitio, ahora mismo">
								Analizar
							</a>
						</td>
					<?php endif; ?>
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
