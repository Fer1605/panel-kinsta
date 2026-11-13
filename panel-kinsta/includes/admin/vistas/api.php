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
$llaves     = SWPK_Llaves::listar();
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

	<p style="max-width:860px;color:#3c434a">
		<strong>Las llaves no salen en Usuarios de WordPress, y no se pueden editar desde ahí.</strong>
		Esta tabla es su única lista. Nadie —tampoco un administrador— les puede cambiar la
		contraseña, subirles el perfil ni borrarlas desde la pantalla de usuarios: si se pudiera,
		una llave de solo lectura se volvería administradora con un desplegable, o alguien la
		borraría por limpieza sin saber qué estaba rompiendo. Las dos únicas cosas que se le hacen
		a una llave son crearla y revocarla, y las dos están aquí.
	</p>

	<?php if ( $nueva ) : ?>
		<?php
		// Se consume al mostrarse. La contraseña se enseña una vez y una sola, así
		// que recargar la pantalla no la puede volver a sacar.
		delete_transient( 'swpk_llave_nueva' );
		?>
		<style>
		.swpk-telon {
			position: fixed; inset: 0; z-index: 160000;
			background: rgba( 0, 0, 0, .65 );
			display: flex; align-items: center; justify-content: center;
			padding: 20px;
		}
		.swpk-modal {
			background: #fff; border-radius: 6px; max-width: 580px; width: 100%;
			padding: 28px 30px 24px; box-shadow: 0 18px 50px rgba( 0, 0, 0, .35 );
			max-height: 90vh; overflow: auto;
		}
		.swpk-modal h2 { margin: 0 0 2px; font-size: 21px; }
		.swpk-modal-nombre { margin: 0 0 18px; color: #50575e; }
		.swpk-modal-aviso {
			background: #fcf0f1; border-left: 4px solid #d63638;
			padding: 11px 14px; margin: 0 0 20px; color: #3c434a;
		}
		.swpk-campo { display: block; margin: 0 0 16px; }
		.swpk-campo-t { display: block; font-weight: 600; margin-bottom: 5px; }
		.swpk-campo .swpk-pista { font-weight: 400; color: #646970; margin: 0 0 6px; }
		.swpk-fila { display: flex; gap: 8px; align-items: flex-start; }
		.swpk-fila textarea {
			flex: 1 1 auto; min-width: 0; resize: vertical;
			font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
			font-size: 12px; line-height: 1.45; background: #f6f7f7;
		}
		.swpk-fila input {
			flex: 1 1 auto; min-width: 0;
			font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
			font-size: 13px; background: #f6f7f7;
		}
		.swpk-fila .button { flex: 0 0 auto; }
		.swpk-copiado { background: #008a20 !important; border-color: #008a20 !important; color: #fff !important; }
		.swpk-dos { display: flex; gap: 14px; flex-wrap: wrap; }
		.swpk-dos > .swpk-campo { flex: 1 1 220px; margin-bottom: 0; }
		.swpk-modal-pie { margin: 22px 0 0; text-align: right; }
		@media ( max-width: 600px ) {
			.swpk-modal { padding: 20px; }
			.swpk-fila { flex-wrap: wrap; }
			.swpk-fila .button { width: 100%; }
		}
		</style>

		<div class="swpk-telon" id="swpk-telon">
			<div class="swpk-modal" role="dialog" aria-modal="true" aria-labelledby="swpk-modal-titulo">
				<h2 id="swpk-modal-titulo">Tu llave está lista</h2>
				<p class="swpk-modal-nombre"><?php echo esc_html( (string) $nueva['nombre'] ); ?></p>

				<p class="swpk-modal-aviso">
						<strong>Cópiala ahora: no se vuelve a mostrar.</strong>
						En la base de datos queda solo su huella, igual que con cualquier contraseña.
						Si se pierde no se recupera — se revoca esta llave y se crea otra, que son dos clics.
					</p>

					<div class="swpk-campo">
						<span class="swpk-campo-t">Para Claude Desktop</span>
						<p class="swpk-pista">
							Esto se pega en <strong>Ajustes &rarr; Conectores &rarr; Agregar conector
							personalizado</strong>, y no hace falta nada más: ni usuario, ni contraseña, ni
							bajar nada. La dirección misma es la llave.
						</p>
						<span class="swpk-fila">
							<input type="text" readonly value="<?php echo esc_attr( SWPK_Mcp::url( (string) $nueva['token'] ) ); ?>" onclick="this.select()">
							<button type="button" class="button button-primary swpk-copiar" id="swpk-primero">Copiar</button>
						</span>
					</div>

					<?php if ( '' !== (string) $nueva['clave'] ) : ?>

						<p class="swpk-pista" style="margin:18px 0 8px">
							Y si la vas a consultar desde Postman, <code>curl</code> u otro sistema, eso va
							con usuario y contraseña:
						</p>

						<div class="swpk-dos">
							<div class="swpk-campo">
								<span class="swpk-campo-t">Usuario</span>
								<span class="swpk-fila">
									<input type="text" readonly value="<?php echo esc_attr( (string) $nueva['login'] ); ?>" onclick="this.select()">
									<button type="button" class="button swpk-copiar">Copiar</button>
								</span>
							</div>
							<div class="swpk-campo">
								<span class="swpk-campo-t">Contraseña</span>
								<span class="swpk-fila">
									<input type="text" readonly value="<?php echo esc_attr( (string) $nueva['clave'] ); ?>" onclick="this.select()">
									<button type="button" class="button swpk-copiar">Copiar</button>
								</span>
							</div>
						</div>

					<?php else : ?>

						<p class="swpk-pista" style="margin-top:16px">
							Este servidor no deja generar contraseñas de aplicación
							(<?php echo esc_html( (string) $nueva['error'] ); ?>), así que esta llave no sirve
							por ahora para Postman ni <code>curl</code>. <strong>La dirección de arriba sí
							funciona</strong>: no depende de ese mecanismo. El usuario es
							<code><?php echo esc_html( (string) $nueva['login'] ); ?></code>.
						</p>

					<?php endif; ?>

				<p class="swpk-modal-pie">
					<button type="button" class="button" id="swpk-cerrar">Ya la copié</button>
				</p>
			</div>
		</div>

		<script>
		( function () {
			var telon = document.getElementById( 'swpk-telon' );
			if ( ! telon ) { return; }

			function avisar( boton ) {
				var antes = boton.textContent;
				boton.textContent = 'Copiado';
				boton.classList.add( 'swpk-copiado' );
				setTimeout( function () {
					boton.textContent = antes;
					boton.classList.remove( 'swpk-copiado' );
				}, 1800 );
			}

			// A mano con execCommand por si no hay portapapeles: el navegador solo
			// lo da en HTTPS y en localhost, y este panel va a vivir en los dos
			// lados. Sin esto, en un servidor sin certificado el botón no haría
			// nada y no se sabría por qué.
			function aMano( campo, boton ) {
				campo.focus();
				campo.select();
				try {
					if ( document.execCommand( 'copy' ) ) { avisar( boton ); return; }
				} catch ( e ) {}
				boton.textContent = 'Cópiala a mano';
			}

			telon.addEventListener( 'click', function ( ev ) {
				var boton = ev.target.closest( '.swpk-copiar' );
				if ( ! boton ) { return; }

				var campo = boton.parentNode.querySelector( 'input, textarea' );
				if ( ! campo ) { return; }

				if ( navigator.clipboard && navigator.clipboard.writeText ) {
					navigator.clipboard.writeText( campo.value ).then(
						function () { avisar( boton ); },
						function () { aMano( campo, boton ); }
					);
					return;
				}

				aMano( campo, boton );
			} );

			// Se cierra con el botón y nada más. Ni con Escape ni tocando afuera:
			// lo que hay dentro no se vuelve a ver, y un clic de más no debería
			// costar una llave.
			document.getElementById( 'swpk-cerrar' ).addEventListener( 'click', function () {
				telon.remove();
			} );

			var primero = document.getElementById( 'swpk-primero' );
			if ( primero ) { primero.focus(); }
		}() );
		</script>
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
							onclick="return confirm('¿Revocar esta llave? La cuenta se borra y el sistema que la use deja de poder consultar. Lo que ya consultó queda en el registro.')">
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

	<h2>5 · Conectar Claude Desktop</h2>

	<p style="max-width:860px;color:#3c434a">
		<strong>No hay nada que bajar ni que instalar.</strong> El panel habla MCP por su cuenta, así
		que en Claude Desktop se agrega como conector personalizado: se pega una dirección y se le da
		conectar. Esa dirección sale al crear la llave, aquí arriba, y es lo único que hace falta.
	</p>

	<ol style="max-width:860px;color:#3c434a;line-height:1.7">
		<li><strong>Crear la llave</strong> aquí arriba. La ventana entrega la dirección, con su botón
			de copiar. <strong>Se enseña una sola vez.</strong></li>
		<li>En Claude Desktop: <strong>Ajustes → Conectores → Agregar conector personalizado</strong>.</li>
		<li>Ponerle un <strong>nombre</strong> —por ejemplo <em>Panel Kinsta</em>— y pegar la
			<strong>dirección</strong>.</li>
		<li><strong>Conectar.</strong> Ya queda; no pide usuario ni contraseña, porque la dirección
			misma es la llave.</li>
	</ol>

	<div class="notice notice-warning inline" style="max-width:860px">
		<p>
			<strong>Esto exige que el panel esté en internet, con HTTPS. No es opcional y no se puede
			rodear con código.</strong>
			Claude Desktop no se conecta desde la computadora de quien lo usa: le manda la dirección a
			la nube de Anthropic, y es la nube la que abre la conexión al servidor. Una dirección
			<code>localhost</code> o de red interna la rechaza de entrada.
		</p>
		<p>
			<?php if ( ! $alcanzable ) : ?>
				Hoy el panel vive en
				<code><?php echo esc_html( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ); ?></code>,
				así que <strong>esto todavía no se puede probar</strong> — ni desde esta misma máquina.
				El día que el panel tenga su dominio, funciona sin tocar una línea.
			<?php else : ?>
				Este panel ya está en una dirección pública. Si el cortafuegos del servidor filtra por
				origen, hay que dejar entrar al rango de salida de Anthropic.
			<?php endif; ?>
		</p>
	</div>

	<p style="max-width:860px;color:#3c434a">
		Una vez conectado se le pregunta en español y contesta con lo que hay en el panel:
		<em>¿qué sitios necesitan atención hoy?</em>, <em>¿cuántas veces se actualizó tal proyecto este
		mes?</em>, <em>¿quién tiene acceso de administrador a este sitio?</em>
	</p>

	<table class="widefat striped swpk-tabla" style="max-width:860px">
		<thead>
			<tr>
				<th style="width:210px">Lo que puede consultar</th>
				<th>Qué contesta</th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ( SWPK_Mcp::catalogo() as $h ) : ?>
			<tr>
				<td><code><?php echo esc_html( $h['nombre'] ); ?></code></td>
				<td class="swpk-gris"><?php echo esc_html( $h['que'] ); ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>

	<p class="swpk-nota">
		<strong>La dirección es la llave: quien la tenga, consulta.</strong> Por eso vale lo mismo que
		una contraseña — no se manda por chat ni se deja escrita donde la vea cualquiera, y se revoca
		desde la tabla de arriba en cuanto sobre. En la base de datos no queda guardada: vive su huella,
		igual que una contraseña, y por eso se enseña una sola vez.
	</p>

	<p class="swpk-nota">
		El conector <strong>solo lee</strong>, y tiene el alcance de su llave: una llave de ventas
		contesta sobre sus sitios y nada más. Las tres rutas que disparan procesos no se le entregan,
		porque una conversación no debería poder lanzar más de 200 peticiones al hosting a media
		pregunta. Cada consulta queda escrita abajo, marcada como MCP.
	</p>

		<h2>6 · Lo que ya se consultó</h2>

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
