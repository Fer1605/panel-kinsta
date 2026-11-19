<?php
/**
 * Qué se pidió: la cola de órdenes y el reseteo masivo de contraseñas.
 *
 * Esta pantalla tiene un trabajo incómodo que es su razón de ser: decir que
 * las órdenes **no son inmediatas**, y decirlo antes de que alguien pulse el
 * botón, no después de que se quede mirando la pantalla preguntándose si
 * funcionó.
 *
 * El agente pregunta cada cinco minutos, y ese reloj en WordPress no es un
 * reloj: corre cuando alguien visita el sitio. Un sitio con tráfico contesta en
 * minutos; un sitio que nadie visita de noche contesta por la mañana.
 */

defined( 'ABSPATH' ) || exit;

$conteos = SWPK_Ordenes::conteos();
$filtro  = isset( $_GET['estado'] ) ? sanitize_key( (string) $_GET['estado'] ) : '';
$ordenes = SWPK_Ordenes::listar( '' !== $filtro ? [ 'estado' => $filtro ] : [] );

// Filtrar además por qué se pidió y por sitio.
//
// Va aquí y no en la consulta a propósito: `listar()` es la misma función que
// usa el agente para recoger su cola, y meterle un filtro que solo sirve para
// dibujar una pantalla es ensuciar la pieza que de verdad entrega trabajo. Son
// doscientas filas como mucho; filtrarlas aquí no se nota.
$tipos_ordenes = SWPK_Ordenes::tipos();
$tipo_pedido   = SWPK_Vista::opcion_pedida( 'tipo', $tipos_ordenes );
$busca_orden   = SWPK_Vista::busqueda_pedida();

if ( '' !== $tipo_pedido || '' !== $busca_orden ) {
	$aguja   = strtolower( remove_accents( $busca_orden ) );
	$ordenes = array_values(
		array_filter(
			$ordenes,
			static function ( $o ) use ( $tipo_pedido, $aguja ) {
				if ( '' !== $tipo_pedido && (string) $o->tipo !== $tipo_pedido ) {
					return false;
				}
				if ( '' === $aguja ) {
					return true;
				}
				$sitio = SWPK_Inventario::obtener( (string) $o->sitio_id );
				$pajar = strtolower( remove_accents( (string) ( $sitio->nombre_visible ?? $o->sitio_id ) ) );
				return str_contains( $pajar, $aguja );
			}
		)
	);
}

// Las órdenes se acumulan: cada arreglo que se pide deja una. Sin paginar,
// esta pantalla crece sin tope según se use el panel.
[ $ordenes_pagina, $pag_ord, $pags_ord ] = SWPK_Vista::paginar(
	$ordenes,
	SWPK_Vista::pagina_pedida()
);

// Para el reseteo: los sitios nuestros que tienen agente. En los demás no se
// puede, y es mejor que el formulario lo diga antes que después.
$nuestros_con_agente = [];
$sin_agente          = 0;

// Los logins de administrador que el panel conoce, para no escribirlo a ciegas.
// Se recogen en el mismo recorrido: la lista de usuarios se busca por la
// dirección del sitio, que es con la que se registró el agente, no por el id
// con el que lo conoce el hosting.
$logins = [];

foreach ( SWPK_Perfiles::sitios_visibles() as $s ) {
	if ( SWPK_Etiquetas::NO_UPDATE === (string) $s->tag ) {
		continue;
	}

	$agente = SWPK_Ordenes::agente_de( (string) $s->sitio_id );

	if ( ! $agente ) {
		++$sin_agente;
		continue;
	}

	$nuestros_con_agente[] = $s;

	foreach ( SWPK_Usuarios::del_sitio( (string) $agente->sitio_url ) as $u ) {
		if ( ! str_contains( (string) $u->roles, 'administrator' ) ) {
			continue;
		}

		$logins[ (string) $u->login ] = ( $logins[ (string) $u->login ] ?? 0 ) + 1;
	}
}

arsort( $logins );

$url_estado = static fn( string $e ) => add_query_arg(
	array_filter(
		[ 'page' => 'panel-kinsta-seguridad', 'seccion' => 'ordenes', 'estado' => $e ]
	),
	admin_url( 'admin.php' )
);
?>

<?php SWPK_Vista::caja_abrir(
	'Lo que pedimos' . SWPK_Vista::ayuda(
		'Cada vez que le das a «Arreglarlo» en «Qué le falta a cada sitio», el encargo aparece aquí y se '
		. 'queda hasta que el sitio confirma que lo hizo. Tarda entre unos minutos y unas horas: el plugin '
		. 'de cada sitio recoge lo pendiente cuando se comunica con el panel, y en un sitio con poco '
		. 'tráfico eso puede tardar más. Por eso aquí se ve el estado y no un «listo» de inmediato.'
	),
	'',
	'',
	true
); ?>

<?php
// Solo se dibujan los estados que tienen algo. Seis filtros en cero son seis
// cosas que leer para nada, y hacen que la pantalla parezca más complicada de
// lo que es.
$con_algo = array_filter(
	[ SWPK_Ordenes::PENDIENTE, SWPK_Ordenes::ENTREGADA, SWPK_Ordenes::HECHA,
	  SWPK_Ordenes::FALLO, SWPK_Ordenes::VENCIDA, SWPK_Ordenes::CANCELADA ],
	static fn( $e ) => ( $conteos[ $e ] ?? 0 ) > 0
);
?>
<?php if ( count( $con_algo ) > 1 ) : ?>
	<p class="swpk-filtros-estado">
		<a href="<?php echo esc_url( $url_estado( '' ) ); ?>" class="<?php echo '' === $filtro ? 'current' : ''; ?>">Ver todo</a>
		<?php foreach ( $con_algo as $e ) : ?>
			· <a href="<?php echo esc_url( $url_estado( $e ) ); ?>"
				title="<?php echo esc_attr( SWPK_Ordenes::explicacion( $e ) ); ?>"
				class="<?php echo $filtro === $e ? 'current' : ''; ?>">
				<?php echo esc_html( SWPK_Ordenes::etiqueta( $e ) ); ?> (<?php echo (int) $conteos[ $e ]; ?>)
			</a>
		<?php endforeach; ?>
	</p>
<?php endif; ?>

<?php
SWPK_Vista::barra_de_filtros(
	'panel-kinsta-seguridad',
	[
		'tipo' => [
			'etiqueta' => 'Qué le pedimos',
			'opciones' => $tipos_ordenes,
			'actual'   => $tipo_pedido,
			'todas'    => 'Cualquier cosa',
		],
	],
	[ 'seccion' => 'ordenes', 'estado' => $filtro ],
	$busca_orden
);
?>

<?php if ( ! $ordenes ) : ?>
	<p style="color:#50575e;max-width:860px">
		<?php
		if ( '' !== $tipo_pedido || '' !== $busca_orden ) {
			echo 'Ningún arreglo coincide con ese filtro.';
		} elseif ( '' !== $filtro ) {
			echo 'Nada en ese estado ahora mismo.';
		} else {
			echo 'Todavía no le hemos pedido nada a ningún sitio. Cuando le des a «Arreglarlo» en la pestaña anterior, el encargo aparece aquí y se puede seguir hasta que el sitio confirme que lo hizo.';
		}
		?>
	</p>
<?php else : ?>
	<?php SWPK_Vista::paginador( $pag_ord, $pags_ord, count( $ordenes ), 'arreglos pedidos', 'arreglo pedido' ); ?>
	<table class="widefat striped">
		<thead>
			<tr>
				<th style="width:22%">En qué sitio</th>
				<th style="width:24%">Qué le pedimos</th>
				<th style="width:14%">Cómo va</th>
				<th>Qué contestó el sitio</th>
				<th style="width:15%">Cuándo se pidió</th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ( $ordenes_pagina as $o ) : ?>
			<?php $sitio = SWPK_Inventario::obtener( (string) $o->sitio_id ); ?>
			<tr>
				<td>
					<?php if ( $sitio ) : ?>
						<a href="<?php
							echo esc_url(
								add_query_arg(
									[ 'page' => 'panel-kinsta-sitio', 'sitio' => (string) $o->sitio_id, 'de' => 'panel-kinsta-seguridad' ],
									admin_url( 'admin.php' )
								)
							);
						?>"><?php echo esc_html( (string) ( $sitio->nombre_visible ?: $sitio->nombre ) ); ?></a>
					<?php else : ?>
						<?php echo esc_html( (string) $o->sitio_id ?: '—' ); ?>
					<?php endif; ?>
				</td>
				<td><?php echo esc_html( SWPK_Ordenes::tipos()[ (string) $o->tipo ] ?? (string) $o->tipo ); ?></td>
				<td>
					<span class="swpk-etq swpk-orden-<?php echo esc_attr( (string) $o->estado ); ?>"
						title="<?php echo esc_attr( SWPK_Ordenes::explicacion( (string) $o->estado ) ); ?>">
						<?php echo esc_html( SWPK_Ordenes::etiqueta( (string) $o->estado ) ); ?>
					</span>
					<br><span class="swpk-mudo"><?php echo esc_html( SWPK_Ordenes::explicacion( (string) $o->estado ) ); ?></span>
				</td>
				<td><?php echo esc_html( (string) $o->resultado ?: '—' ); ?></td>
				<td>
					<?php echo esc_html( SWPK_Frescura::fmt( 'j/n/Y H:i', (string) $o->pedida_en ) ); ?>
					<?php if ( $o->resuelta_en ) : ?>
						<br><span class="swpk-mudo">cerrada <?php echo esc_html( SWPK_Frescura::fmt( 'j/n H:i', (string) $o->resuelta_en ) ); ?></span>
					<?php endif; ?>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>

	<?php SWPK_Vista::paginador( $pag_ord, $pags_ord, count( $ordenes ), 'arreglos pedidos', 'arreglo pedido' ); ?>
<?php endif; ?>

<hr style="margin:34px 0">

<?php SWPK_Vista::caja_cerrar(); ?>

<?php SWPK_Vista::caja_abrir( 'Cambiar una contraseña en varios sitios de golpe' ); ?>

<p style="max-width:880px;color:#3c434a">
	Para cuando alguien se va del equipo. Lo pidió así: <em>«mañana se va alguien y resulta que tiene las
	contraseñas y todo conectado; resetea la contraseña masiva de todos y se cambian todas por una que yo
	quiera»</em>.
</p>

<div class="notice notice-warning inline" style="max-width:880px">
	<p>
		<strong>La contraseña no se guarda en el panel.</strong> Vive dos horas, el rato que tardan los
		sitios en recogerla, y después se borra. No queda escrita en ninguna parte, así que aquí no se puede
		volver a consultar: cópiala a tu gestor de contraseñas antes de darle.
		<strong>Un sitio que no la recoja en esas dos horas queda sin cambiar</strong>, y hay que volver a
		pedirlo.
	</p>
	<p>
		Y solo toca <strong>al usuario administrador que se indique</strong>. Los usuarios del cliente no se
		tocan: si en un sitio ese usuario no existe o no es administrador, ese sitio queda sin cambios y lo
		dice.
	</p>
</div>

<?php if ( ! $nuestros_con_agente ) : ?>
	<div class="notice notice-error inline" style="max-width:880px">
		<p>
			<strong>Ahora mismo esto no se puede usar.</strong>
			No hay ningún sitio nuestro con el agente instalado, y sin agente no hay a quién dejarle la orden.
			<?php if ( $sin_agente > 0 ) : ?>
				Hay <?php echo esc_html( SWPK_Vista::plural( $sin_agente, 'sitio nuestro', 'sitios nuestros' ) ); ?>
				esperando que se les instale.
			<?php endif; ?>
		</p>
	</div>
	<?php return; ?>
<?php endif; ?>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="max-width:760px">
	<input type="hidden" name="action" value="swpk_reseteo_masivo">
	<?php wp_nonce_field( 'swpk_reseteo_masivo' ); ?>

	<table class="form-table">
		<tr>
			<th scope="row"><label for="swpk-reseteo-login">De qué usuario</label></th>
			<td>
				<input type="text" id="swpk-reseteo-login" name="login" class="regular-text" required
					list="swpk-logins" autocomplete="off"
					value="<?php echo esc_attr( (string) array_key_first( $logins ) ); ?>">
				<datalist id="swpk-logins">
					<?php foreach ( $logins as $login => $cuantos ) : ?>
						<option value="<?php echo esc_attr( (string) $login ); ?>"><?php
							printf( '%s — en %d sitio(s)', esc_html( (string) $login ), (int) $cuantos );
						?></option>
					<?php endforeach; ?>
				</datalist>
				<p class="description">
					El nombre de usuario exacto, el mismo en todos los sitios. La lista de arriba son los
					administradores que el panel ya conoce, con en cuántos sitios aparece cada uno.
				</p>
			</td>
		</tr>

		<tr>
			<th scope="row">En qué sitios</th>
			<td>
				<?php
				$por_grupo = [];

				foreach ( $nuestros_con_agente as $s ) {
					$por_grupo[ (string) $s->tag ][] = $s;
				}
				?>
				<?php foreach ( $por_grupo as $tag => $lista ) : ?>
					<label style="display:block;margin-bottom:6px">
						<input type="checkbox" name="grupos[]" value="<?php echo esc_attr( $tag ); ?>" checked>
						<?php echo SWPK_Admin::grupo_badge( $tag ); ?>
						<?php echo esc_html( SWPK_Vista::plural( count( $lista ), 'sitio', 'sitios' ) ); ?>
					</label>
				<?php endforeach; ?>
				<p class="description">
					Solo salen los sitios nuestros que tienen el plugin instalado. Los que administra el cliente
					no están en la lista: a esos no se les cambia nada sin que lo pidan.
					<?php if ( $sin_agente > 0 ) : ?>
						<br><?php echo esc_html( SWPK_Vista::plural( $sin_agente, 'sitio nuestro queda', 'sitios nuestros quedan' ) ); ?>
						fuera por no tener agente.
					<?php endif; ?>
				</p>
			</td>
		</tr>

		<tr>
			<th scope="row"><label for="swpk-reseteo-clave">La contraseña nueva</label></th>
			<td>
				<input type="text" id="swpk-reseteo-clave" name="clave" class="regular-text" required
					minlength="12" autocomplete="off" spellcheck="false">
				<p class="description">
					Mínimo 12 caracteres. Se ve mientras la escribes, porque hay que copiarla a un gestor antes
					de darle — después ya no se puede consultar.
				</p>
			</td>
		</tr>
	</table>

	<p>
		<button type="submit" class="button button-primary"
			onclick="return confirm('Se va a cambiar la contraseña de ese usuario en los sitios marcados, y se van a cerrar todas sus sesiones abiertas. No es inmediato: cada sitio lo aplica cuando pregunte al panel. ¿Seguimos?')">
			Pedir el cambio
		</button>
		<span class="swpk-mudo" style="margin-left:10px">
			Queda anotado quién lo pidió y cuándo, sitio por sitio, en esta misma pantalla.
		</span>
	</p>
</form>

<?php SWPK_Vista::caja_cerrar(); ?>
