<?php
/**
 * Conectarse: cómo le pregunta otro sistema al panel.
 *
 * Esta pantalla existe para contestar una cosa: "¿y esto cómo lo consumo?".
 * Por eso no es documentación — es la llave, la dirección, las rutas y un botón
 * para probarlas aquí mismo, sin salir a buscar nada.
 *
 * Lo que sí dice, y en grande, es cuándo NO se puede llegar desde fuera.
 * Mientras el panel corra en una máquina de la casa, la API funciona pero solo
 * para esa máquina, y eso no se arregla con código: hace falta un servidor.
 * Callarlo sería dejar que alguien pruebe la llave desde su casa, no le
 * funcione, y crea que está mal hecha.
 */
defined( 'ABSPATH' ) || exit;

$base       = SWPK_Api::base();
$alcanzable = SWPK_Api::alcanzable();
$catalogo   = SWPK_Api::catalogo();
$nueva      = get_transient( 'swpk_llave_nueva' );
$llaves     = get_users( [ 'role' => SWPK_Perfiles::API, 'orderby' => 'display_name' ] );
$hay_app_pw = wp_is_application_passwords_available();
$llamadas   = array_merge( SWPK_Accesos::listar( 25, SWPK_Accesos::API ), SWPK_Accesos::listar( 25, SWPK_Accesos::API_NEGADO ) );

usort( $llamadas, static fn( $a, $b ) => strcmp( (string) $b->ocurrio_en, (string) $a->ocurrio_en ) );
$llamadas = array_slice( $llamadas, 0, 25 );
?>
<div class="wrap">
	<h1>Conectarse al panel</h1>
	<?php SWPK_Admin::mostrar_mensaje(); ?>

	<p style="max-width:860px;color:#3c434a">
		El panel contesta preguntas. Cualquier otro sistema —o Claude, en la Fase 2— le pregunta
		por la misma puerta que usan estas pantallas y recibe lo mismo que se ve aquí,
		<strong>incluido lo que vino de <?php echo esc_html( SWPK_Admin::proveedor() ); ?> y ya está
		guardado</strong>. No hay que ir al hosting por separado, y la llave del hosting no sale de
		este plugin: si hace falta el dato fresco, se dispara un proceso y el panel pregunta con su
		propia llave.
	</p>

	<?php if ( ! $alcanzable ) : ?>
		<div class="notice notice-warning">
			<p>
				<strong>La API ya funciona, pero hoy solo desde esta máquina.</strong>
				El panel vive en <code><?php echo esc_html( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ); ?></code>,
				que no existe para nadie más. Todo lo de abajo es real y se puede probar aquí mismo;
				para que lo consuma otro sistema hace falta que el panel esté en un servidor al que
				se pueda llegar. Eso no es trabajo de código, es dónde se pone.
			</p>
		</div>
	<?php endif; ?>

	<?php if ( ! $hay_app_pw ) : ?>
		<div class="notice notice-error">
			<p>
				<strong>Este servidor tiene desactivadas las contraseñas de aplicación de WordPress.</strong>
				Son el mecanismo con el que un programa se identifica sin usar la contraseña de una
				persona. WordPress las exige por HTTPS: sin certificado, no las deja crear. La llave
				se puede crear igual, pero su contraseña habrá que generarla en el servidor definitivo.
			</p>
		</div>
	<?php endif; ?>

	<h2>1 · La dirección</h2>

	<p>Todas las rutas salen de aquí:</p>

	<p><code style="display:inline-block;padding:8px 12px;background:#f6f7f7;border:1px solid #dcdcde;font-size:14px"><?php echo esc_html( $base ); ?></code></p>

	<h2>2 · La llave</h2>

	<p style="max-width:860px;color:#3c434a">
		Una llave es una cuenta que <strong>solo puede leer</strong>: consulta los sitios, las
		vulnerabilidades, los usuarios, los movimientos y la bitácora, y no puede disparar procesos
		ni tocar ajustes. Es a propósito — una llave que vive dentro de otro sistema se puede
		filtrar, y lo que se filtre no debe poder cambiar nada.
	</p>

	<?php if ( $nueva ) : ?>
		<div class="notice notice-success">
			<p><strong>Llave creada: <?php echo esc_html( (string) $nueva['nombre'] ); ?></strong></p>
			<?php if ( '' !== (string) $nueva['clave'] ) : ?>
				<p>
					Usuario <code><?php echo esc_html( (string) $nueva['login'] ); ?></code> ·
					contraseña <code style="font-size:14px"><?php echo esc_html( (string) $nueva['clave'] ); ?></code>
				</p>
				<p>
					<strong>Cópiala ahora.</strong> WordPress no la vuelve a mostrar: guarda solo su huella,
					igual que con cualquier contraseña. Si se pierde, se revoca esta llave y se crea otra.
				</p>
			<?php else : ?>
				<p>
					La cuenta quedó creada, pero este servidor no deja generar su contraseña
					(<?php echo esc_html( (string) $nueva['error'] ); ?>). Habrá que generarla en el
					servidor definitivo, desde el perfil de ese usuario.
				</p>
			<?php endif; ?>
		</div>
		<?php delete_transient( 'swpk_llave_nueva' ); ?>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:14px 0 22px">
		<?php wp_nonce_field( 'swpk_crear_llave' ); ?>
		<input type="hidden" name="action" value="swpk_crear_llave">
		<label>
			Para qué sistema es<br>
			<input type="text" name="nombre" class="regular-text" placeholder="Fase 2 · Claude" required>
		</label>
		<button class="button button-primary">Crear la llave</button>
	</form>

	<?php if ( $llaves ) : ?>
		<table class="widefat striped swpk-tabla" style="max-width:860px">
			<thead>
				<tr>
					<th>Llave</th>
					<th style="width:200px">Usuario</th>
					<th style="width:160px">Última consulta</th>
					<th style="width:110px"></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $llaves as $u ) : ?>
				<?php
				global $wpdb;
				$ultima = (string) $wpdb->get_var(
					$wpdb->prepare(
						'SELECT MAX(ocurrio_en) FROM ' . SWPK_Tablas::tabla( 'accesos' ) . ' WHERE usuario_id = %d AND accion = %s',
						$u->ID,
						SWPK_Accesos::API
					)
				);
				?>
				<tr>
					<td><strong><?php echo esc_html( $u->display_name ); ?></strong></td>
					<td><code><?php echo esc_html( $u->user_login ); ?></code></td>
					<td class="swpk-gris">
						<?php echo esc_html( '' !== $ultima ? SWPK_Frescura::hace( $ultima ) : 'nunca' ); ?>
					</td>
					<td>
						<a class="button button-small"
							href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=swpk_revocar_llave&usuario=' . $u->ID ), 'swpk_revocar_llave_' . $u->ID ) ); ?>"
							onclick="return confirm('¿Revocar esta llave? El sistema que la use dejará de poder consultar.')">
							Revocar
						</a>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	<?php else : ?>
		<p class="swpk-gris">Todavía no hay ninguna llave creada.</p>
	<?php endif; ?>

	<h2>3 · Las rutas</h2>

	<p style="max-width:860px;color:#3c434a">
		Cada una envuelve la misma función que alimenta su pantalla. No hay una sola consulta
		escrita dos veces: es lo que hace imposible que el panel y la API se contradigan.
		<strong>Toda respuesta trae encima de cuándo es el dato</strong> —cuándo se le preguntó al
		hosting, cuándo se bajó la lista de vulnerabilidades, si la corrida está al día— por lo mismo
		que lo traen los Excel y los PDF: un JSON sin fecha es una afirmación sin fecha.
	</p>

	<table class="widefat striped swpk-tabla">
		<thead>
			<tr>
				<th style="width:60px">Cómo</th>
				<th style="width:230px">Ruta</th>
				<th>Qué contesta</th>
				<th style="width:110px">Quién puede</th>
				<th style="width:190px">Se le puede pedir</th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ( $catalogo as $r ) : ?>
			<tr>
				<td><strong style="color:<?php echo 'GET' === $r['metodo'] ? '#2271b1' : '#b32d2e'; ?>"><?php echo esc_html( $r['metodo'] ); ?></strong></td>
				<td><code><?php echo esc_html( $r['ruta'] ); ?></code></td>
				<td><?php echo esc_html( $r['que'] ); ?></td>
				<td class="swpk-gris"><?php echo esc_html( $r['quien'] ); ?></td>
				<td class="swpk-gris"><?php echo '' !== $r['params'] ? '<code>' . esc_html( $r['params'] ) . '</code>' : '—'; ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>

	<p class="swpk-nota">
		Los tres procesos van por <code>POST</code> aunque no escriban nada afuera, porque cuestan: la
		corrida completa son más de 200 peticiones al hosting. Un <code>GET</code> se repite solo —un
		navegador, una caché, un enlace abierto dos veces— y eso no debe poder disparar 200 peticiones
		sin que nadie lo haya pedido.
	</p>

	<h2>4 · Probarlo</h2>

	<p style="max-width:860px;color:#3c434a">
		Desde fuera, con la llave de arriba y autenticación básica:
	</p>

	<p><code style="display:block;padding:10px 12px;background:#f6f7f7;border:1px solid #dcdcde;overflow:auto">curl -u "USUARIO:CONTRASEÑA" <?php echo esc_html( $base . '/estado' ); ?></code></p>

	<p style="max-width:860px;color:#3c434a">
		Y aquí mismo, con tu propia sesión, para ver qué contesta de verdad:
	</p>

	<p>
		<select id="swpk-ruta">
			<?php foreach ( $catalogo as $r ) : ?>
				<?php if ( 'GET' !== $r['metodo'] || str_contains( $r['ruta'], '{id}' ) ) { continue; } ?>
				<option value="<?php echo esc_attr( $r['ruta'] ); ?>"><?php echo esc_html( $r['ruta'] ); ?></option>
			<?php endforeach; ?>
		</select>
		<button class="button" id="swpk-probar">Preguntar</button>
		<span class="swpk-gris" id="swpk-estado"></span>
	</p>

	<pre id="swpk-salida" style="display:none;max-height:420px;overflow:auto;background:#1d2327;color:#c3c4c7;padding:14px;border-radius:4px;font-size:12px"></pre>

	<script>
	( function () {
		var boton  = document.getElementById( 'swpk-probar' );
		var salida = document.getElementById( 'swpk-salida' );
		var aviso  = document.getElementById( 'swpk-estado' );
		var base   = <?php echo wp_json_encode( $base ); ?>;
		var nonce  = <?php echo wp_json_encode( wp_create_nonce( 'wp_rest' ) ); ?>;

		boton.addEventListener( 'click', function () {
			var ruta = document.getElementById( 'swpk-ruta' ).value;
			aviso.textContent = 'preguntando…';
			salida.style.display = 'none';

			fetch( base + ruta, { headers: { 'X-WP-Nonce': nonce } } )
				.then( function ( r ) {
					aviso.textContent = 'contestó ' + r.status;
					return r.json();
				} )
				.then( function ( d ) {
					salida.textContent = JSON.stringify( d, null, 2 );
					salida.style.display = 'block';
				} )
				.catch( function ( e ) {
					aviso.textContent = 'no contestó: ' + e.message;
				} );
		} );
	}() );
	</script>

	<h2>5 · Lo que ya se consultó</h2>

	<p style="max-width:860px;color:#3c434a">
		Cada llamada queda escrita, la que pasa y la que se rechaza. Hace falta porque una contraseña
		de aplicación <strong>se salta la verificación en dos pasos</strong> —no pasa por el
		formulario de entrada— así que lo único que queda de ella es esta fila. Las entradas de
		personas están en <a href="<?php echo esc_url( admin_url( 'admin.php?page=panel-kinsta-accesos' ) ); ?>">Accesos</a>.
	</p>

	<?php if ( ! $llamadas ) : ?>
		<p class="swpk-gris">Nadie ha consultado la API todavía.</p>
	<?php else : ?>
		<table class="widefat striped swpk-tabla" style="max-width:860px">
			<thead>
				<tr>
					<th style="width:150px">Cuándo</th>
					<th style="width:170px">Quién</th>
					<th>Qué pidió</th>
					<th style="width:120px">Resultado</th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $llamadas as $l ) : ?>
				<tr>
					<td class="swpk-gris"><?php echo esc_html( SWPK_Frescura::fmt( 'j M Y, H:i', (string) $l->ocurrio_en ) ); ?></td>
					<td><code><?php echo esc_html( '' !== (string) $l->login ? (string) $l->login : '—' ); ?></code></td>
					<td><code><?php echo esc_html( (string) $l->detalle ); ?></code></td>
					<td<?php echo SWPK_Accesos::API_NEGADO === $l->accion ? ' class="swpk-rojo"' : ''; ?>>
						<?php echo esc_html( SWPK_Accesos::API_NEGADO === $l->accion ? 'Rechazada' : 'Contestada' ); ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<p class="swpk-nota">
		Lo que esta API <strong>no</strong> hace es escribir: no asigna asesoras, no marca nada como
		atendido, no toca ningún sitio. Lee, y dispara los tres procesos que el panel ya dispara con
		un botón. El día que haga falta escribir desde fuera será una decisión aparte, no un
		descuido.
	</p>
</div>
