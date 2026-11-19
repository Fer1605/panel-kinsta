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

	<p class="swpk-aviso-corto">
		Otro sistema puede preguntarle al panel lo mismo que enseñan estas pantallas.
		<?php echo wp_kses_post( SWPK_Vista::ayuda( sprintf(
			'Pregunta por la misma puerta que usan las pantallas y recibe lo mismo que se ve aquí, '
			. 'incluido lo que vino de %s y ya está guardado. No hay que ir al hosting por separado, y '
			. 'la llave del hosting no sale de este plugin: si hace falta el dato fresco, se dispara un '
			. 'proceso y el panel pregunta con su propia llave.',
			SWPK_Admin::proveedor()
		) ) ); ?>
	</p>

	<?php if ( ! $alcanzable ) : ?>
		
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

	<?php SWPK_Vista::rejilla_abrir(); ?>

	<?php SWPK_Vista::caja_abrir( 'La dirección del panel', '', 'swpk-c12' ); ?>

	<p>Todas las rutas salen de aquí:</p>

	<p>
		<code id="swpk-base" style="display:inline-block;padding:8px 12px;background:#f6f7f7;border:1px solid #dcdcde;font-size:14px"><?php echo esc_html( $base ); ?></code>
		<?php // El botón de la maqueta. Si el navegador no deja copiar —pasa fuera de https— lo dice en vez de fingir que copió. ?>
		<button type="button" class="button" style="margin-left:6px" onclick="
			navigator.clipboard &amp;&amp; navigator.clipboard.writeText(document.getElementById('swpk-base').textContent.trim())
				.then(function(){ this.textContent = 'Copiada'; }.bind(this))
				.catch(function(){ this.textContent = 'Copiala a mano'; }.bind(this));
		">Copiar</button>
	</p>

	<?php SWPK_Vista::caja_cerrar(); ?>

	<?php SWPK_Vista::caja_abrir( 'Las llaves que hay', '<a href="#swpk-crear-llave">Crear una llave</a>', 'swpk-c12' ); ?>

	<p class="swpk-aviso-corto">
		Una llave solo puede leer, y esta tabla es su única lista: aquí se crea y aquí se revoca.
		<?php echo wp_kses_post( SWPK_Vista::ayuda(
			'Consulta los sitios, las vulnerabilidades, los usuarios, los movimientos y el historial de '
			. 'envíos, y no puede disparar procesos ni tocar ajustes. Es a propósito: una llave que vive '
			. 'dentro de otro sistema se puede filtrar, y lo que se filtre no debe poder cambiar nada. '
			. 'Tampoco salen en Usuarios de WordPress ni se pueden editar desde ahí — si se pudiera, una '
			. 'llave de solo lectura se volvería administradora con un desplegable, o alguien la '
			. 'borraría por limpieza sin saber qué estaba rompiendo.'
		) ); ?>
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
			background: #fcf0f1; border: 1px solid #ecc7c9;
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

	<form id="swpk-crear-llave" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:14px 0 22px">
		<?php wp_nonce_field( 'swpk_crear_llave' ); ?>
		<input type="hidden" name="action" value="swpk_crear_llave">
		<label>
			Para qué sistema es<br>
			<input type="text" name="nombre" class="regular-text" placeholder="Fase 2 · Claude" required>
		</label>
		<button class="button button-primary">Crear la llave</button>
	</form>

	<?php if ( $llaves ) : ?>
		<table class="widefat striped swpk-tabla">
			<thead>
				<tr>
					<th>Nombre</th>
					<th style="width:200px">Creada</th>
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
					<td>
						<strong><?php echo esc_html( $u->display_name ); ?></strong><br>
						<code class="swpk-gris"><?php echo esc_html( $u->user_login ); ?></code>
					</td>
					<?php
					// La maqueta llama «Creada» a esta columna, así que tiene que
					// decir cuándo se creó la llave y no quién es. La fecha de alta
					// del usuario ES esa fecha: la llave y el usuario nacen juntos.
					?>
					<td class="swpk-gris">
						<?php echo esc_html( SWPK_Frescura::fmt( 'j M Y', (string) $u->user_registered ) ); ?>
					</td>
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

	<?php SWPK_Vista::caja_cerrar(); ?>

	<?php SWPK_Vista::caja_abrir( 'Qué se puede consultar', esc_html( SWPK_Vista::plural( count( $catalogo ), 'ruta', 'rutas' ) ), 'swpk-c12' ); ?>

	<p class="swpk-aviso-corto">
		Toda respuesta trae encima de cuándo es el dato.
		<?php echo wp_kses_post( SWPK_Vista::ayuda(
			'Cada ruta envuelve la misma función que alimenta su pantalla: no hay una sola consulta '
			. 'escrita dos veces, y por eso el panel y la API no se pueden contradecir. Y cada '
			. 'respuesta dice cuándo se le preguntó al hosting, cuándo se bajó la lista de '
			. 'vulnerabilidades y si la corrida está al día, por lo mismo que lo traen los Excel y los '
			. 'PDF: un dato sin fecha es una afirmación sin fecha.'
		) ); ?>
	</p>

	<?php
	// Los dos desplegables de la maqueta. Van por GET como el resto del panel
	// y no en el navegador: diez filas no justifican el único filtro del panel
	// que trabajaría de otra forma que todos los demás.
	$metodos = [];
	$quienes_ruta = [];
	foreach ( $catalogo as $r ) {
		$metodos[ $r['metodo'] ]      = $r['metodo'];
		$quienes_ruta[ $r['quien'] ]  = $r['quien'];
	}

	$metodo_pedido = SWPK_Vista::opcion_pedida( 'metodo', $metodos );
	$quien_ruta    = SWPK_Vista::opcion_pedida( 'perfil', $quienes_ruta );

	SWPK_Vista::barra_de_filtros(
		'panel-kinsta-ajustes',
		[
			'metodo' => [
				'etiqueta' => 'Cómo',
				'opciones' => $metodos,
				'actual'   => $metodo_pedido,
				'todas'    => 'GET y POST',
			],
			'perfil' => [
				'etiqueta' => 'Quién puede',
				'opciones' => $quienes_ruta,
				'actual'   => $quien_ruta,
				'todas'    => 'Cualquier perfil',
			],
		],
		[],
		null
	);

	$rutas_vis = array_filter(
		$catalogo,
		static fn( $r ) =>
			( '' === $metodo_pedido || $r['metodo'] === $metodo_pedido )
			&& ( '' === $quien_ruta || $r['quien'] === $quien_ruta )
	);
	?>

	<table class="widefat striped swpk-tabla">
		<thead>
			<tr>
				<th style="width:60px">Cómo</th>
				<th style="width:230px">La ruta</th>
				<th>Qué contesta</th>
				<th style="width:110px">Quién puede</th>
				<th style="width:190px">Se le puede pedir</th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ( $rutas_vis as $r ) : ?>
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

	<?php SWPK_Vista::caja_cerrar(); ?>

	<?php SWPK_Vista::caja_abrir( 'Conectarlo a Claude', esc_html( SWPK_Vista::plural( count( SWPK_Mcp::catalogo() ), 'consulta disponible', 'consultas disponibles' ) ), 'swpk-c12' ); ?>

	<div class="notice notice-warning">
			<p>
				<strong>La API ya funciona, pero hoy solo desde esta máquina.</strong>
				El panel vive en <code><?php echo esc_html( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ); ?></code>,
				que no existe para nadie más. Todo lo de abajo es real y se puede probar aquí mismo;
				para que lo consuma otro sistema hace falta que el panel esté en un servidor al que
				se pueda llegar. Eso no es trabajo de código, es dónde se pone.
			</p>
		</div>

	<p class="swpk-aviso-corto">
		<strong>No hay nada que bajar ni que instalar.</strong> Se agrega como conector personalizado
		pegando una dirección, la que sale al crear la llave.
	</p>

	<ol style="max-width:860px;color:#3c434a;line-height:1.7">
		<li><strong>Crear una llave</strong> aquí arriba. La ventana entrega la dirección, con su botón
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

	<p class="swpk-aviso-corto">
		Conectado, se le pregunta en español: <em>¿qué sitios necesitan atención hoy?</em>,
		<em>¿cuántas veces se actualizó tal proyecto este mes?</em>
	</p>

	<table class="widefat striped swpk-tabla">
		<thead>
			<tr>
				<th style="width:210px">Lo que puede consultar</th>
				<th>Qué devuelve</th>
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

	<p class="swpk-aviso-corto">
		<strong>La dirección es la llave: quien la tenga, consulta.</strong> Y el conector solo lee.
		<?php echo wp_kses_post( SWPK_Vista::ayuda(
			'La dirección vale lo mismo que una contraseña: no se manda por chat ni se deja escrita '
			. 'donde la vea cualquiera, y se revoca desde «Las llaves que hay» en cuanto sobre. En la '
			. 'base de datos no queda guardada —vive su huella, igual que una contraseña—, y por eso se '
			. 'enseña una sola vez. El conector tiene el alcance de su llave: una de ventas contesta '
			. 'sobre sus sitios y nada más, y las rutas que disparan procesos no se le entregan, porque '
			. 'una conversación no debería poder lanzar doscientas peticiones al hosting a media '
			. 'pregunta. Cada consulta queda anotada abajo, marcada como MCP.'
		) ); ?>
	</p>

		<?php SWPK_Vista::caja_cerrar(); ?>

	<?php SWPK_Vista::caja_abrir( 'Probarlo desde aquí', '', 'swpk-c12' ); ?>

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
		<button class="button" id="swpk-probar">Probar</button>
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

	<?php SWPK_Vista::caja_cerrar(); ?>


		<?php SWPK_Vista::caja_abrir( 'Lo que ya se consultó', '', 'swpk-c12' ); ?>

	<p class="swpk-aviso-corto">
		Cada llamada queda escrita, la que pasa y la que se rechaza. Las entradas de personas están
		en <a href="<?php echo esc_url( admin_url( 'admin.php?page=panel-kinsta-movimientos&seccion=accesos' ) ); ?>">Historial</a>.
		<?php echo wp_kses_post( SWPK_Vista::ayuda(
			'Hace falta porque una contraseña de aplicación se salta la verificación en dos pasos —no '
			. 'pasa por el formulario de entrada— así que lo único que queda de esa llamada es esta '
			. 'fila.'
		) ); ?>
	</p>

	<?php if ( ! $llamadas ) : ?>
		<p class="swpk-gris">Nadie ha consultado la API todavía.</p>
	<?php else : ?>
		<table class="widefat striped swpk-tabla">
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
	<?php SWPK_Vista::caja_cerrar(); ?>

	<?php SWPK_Vista::rejilla_cerrar(); ?>
