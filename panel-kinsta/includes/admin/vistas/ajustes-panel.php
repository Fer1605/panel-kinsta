<?php
/**
 * Ajustes del panel: la llave del hosting, los grupos y cómo corre.
 *
 * Solo el cuerpo. El recuadro y las pestañas los pone ajustes.php.
 */
defined( 'ABSPATH' ) || exit;

$a = SWPK_Ajustes::todos();
?>

	<?php if ( SWPK_Ajustes::llaves_en_claro() ) : ?>
		<div class="notice notice-warning">
			<p>
				Hay llaves guardadas sin cifrar de una versión anterior
				(<?php echo esc_html( implode( ' y ', SWPK_Ajustes::llaves_en_claro() ) ); ?>).
				Se arregla solo al guardar de nuevo, o
				<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=swpk_cifrar' ), 'swpk_cifrar' ) ); ?>">cifrarlas ahora</a>.
			</p>
		</div>
	<?php elseif ( ! SWPK_Ajustes::cifrado_disponible() ) : ?>
		<div class="notice notice-error">
			<p>
				<strong>Este WordPress no tiene claves de seguridad definidas</strong>, así que las
				llaves no se pueden cifrar. Hay que generarlas en <code>wp-config.php</code> antes de
				guardar nada aquí.
			</p>
		</div>
	<?php endif; ?>

	<?php SWPK_Vista::rejilla_abrir(); ?>

	<form method="post" class="swpk-pasa" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="swpk_guardar_ajustes">
		<?php wp_nonce_field( 'swpk_ajustes' ); ?>

		<?php SWPK_Vista::caja_abrir( 'La conexión con el hosting', esc_html( 'modo: ' . ( 'api' === $a['modo'] ? 'datos reales' : 'datos de prueba' ) ), 'swpk-c6' ); ?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="modo">Modo</label></th>
				<td>
					<select name="modo" id="modo">
						<option value="demo" <?php selected( $a['modo'], 'demo' ); ?>>Datos de prueba (sin llave)</option>
						<option value="api" <?php selected( $a['modo'], 'api' ); ?>>API de Kinsta</option>
					</select>
					<p class="description">
						En modo de prueba el panel funciona completo con sitios inventados.
						<?php echo wp_kses_post( SWPK_Vista::ayuda(
							'Es lo que permite desarrollarlo y enseñarlo antes de tener accesos.'
						) ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="company_id">Company ID de Kinsta</label></th>
				<td>
					<input type="text" class="regular-text" name="company_id" id="company_id"
						value="<?php echo esc_attr( $a['company_id'] ); ?>">
					<p class="description">Está en MyKinsta, en la URL de la empresa.</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="api_key">Llave de la API de Kinsta</label></th>
				<td>
					<input type="password" class="regular-text" name="api_key" id="api_key"
						value="" autocomplete="off" placeholder="<?php echo '' !== $a['api_key'] ? '•••••••• (ya configurada)' : ''; ?>">
					<p class="description">
						En blanco para no reimprimir el secreto. Escribir algo la reemplaza.
						<?php echo wp_kses_post( SWPK_Vista::ayuda(
							'Kinsta no ofrece llaves de solo lectura: al crearla solo se elige nombre y '
							. 'caducidad, y hereda los permisos de quien la crea. La garantía de solo lectura '
							. 'es de este plugin, que únicamente hace peticiones de consulta.'
						) ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="wordfence_key">Llave de Wordfence Intelligence</label></th>
				<td>
					<input type="password" class="regular-text" name="wordfence_key" id="wordfence_key"
						value="" autocomplete="off" placeholder="<?php echo '' !== $a['wordfence_key'] ? '•••••••• (ya configurada)' : ''; ?>">
					<p class="description">
						Cuenta gratuita en wordfence.com → Integrations.
						<?php echo wp_kses_post( SWPK_Vista::ayuda(
							'Es la fuente del «por qué es grave»: severidad, CVE y qué versión lo corrige. '
							. 'Kinsta solo dice sí o no.'
						) ); ?>
					</p>
				</td>
			</tr>
		</table>

		<p>
			<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=swpk_probar' ), 'swpk_probar' ) ); ?>">
				Probar la conexión
			</a>
			<span class="swpk-gris">no guarda nada: solo pregunta si la llave sirve.</span>
		</p>
		<?php submit_button( 'Guardar la conexión' ); ?>
		<?php SWPK_Vista::caja_cerrar(); ?>
	</form>

	<?php
	// ── Conectar los sitios ────────────────────────────────────────────────
	//
	// Esto estaba roto de la peor manera: el agente, dentro del sitio del
	// cliente, dice «la clave está en el panel, en Ajustes» — y no estaba en
	// ninguna pantalla. El panel la generaba y se la quedaba. La única forma
	// de sacarla era un comando contra la base de datos, que es justo lo que
	// no puede hacer quien va a instalar el agente.
	//
	// O sea: la instrucción mandaba a un lugar vacío, y sin esa clave no se
	// puede conectar ni un sitio. Se descubrió porque alguien preguntó qué
	// había que pegar en esos dos campos.
	//
	// Las dos cosas que hay que pegar van juntas aquí, en el orden en que se
	// piden allá, para que sea copiar y pegar y no una búsqueda.
	if ( SWPK_Perfiles::puede( SWPK_Perfiles::ADMINISTRAR ) ) :
		SWPK_Vista::caja_abrir( 'Conectar los sitios', '', 'swpk-c6' );
		?>
		<p style="margin:0 0 14px">
			En cada sitio: <strong>Ajustes → Agente Solución Web</strong>, se pegan estos dos datos
			y se le da a <strong>Registrar este sitio</strong>.
		</p>

		<div class="swpk-par">
			<span class="swpk-q">Dirección del panel</span>
			<span class="swpk-v"><code class="swpk-mono"><?php echo esc_html( home_url() ); ?></code></span>
		</div>

		<div class="swpk-par">
			<span class="swpk-q">Clave de registro</span>
			<span class="swpk-v"><code class="swpk-mono"><?php echo esc_html( SWPK_Receptor::clave_registro() ); ?></code></span>
		</div>

		<p class="swpk-gris" style="font-size:12px;margin:10px 0 0">
			Trátala como una contraseña.
			<?php echo wp_kses_post( SWPK_Vista::ayuda(
				'Con ella, cualquiera que sepa la dirección del panel puede dar de alta un sitio y '
				. 'mandarle datos. Es la misma para todos los sitios y solo se usa al registrarse: al '
				. 'registrarse, el panel le entrega a ese sitio un secreto propio, distinto en cada uno, '
				. 'y de ahí en adelante el sitio firma sus reportes con el suyo. Por eso esta clave se '
				. 'puede cambiar sin desconectar los sitios que ya estén andando, y por eso el secreto '
				. 'de un sitio no sirve para hablar por otro.'
			) ); ?>
		</p>
		<?php
		SWPK_Vista::caja_cerrar();
	endif;

	// «Cómo corre» es, en la maqueta, la lista de horarios: cada cuánto pasa
	// cada cosa. Aquí tenía la tabla de estado del panel, que es otra cosa y
	// se bajó a su propia tarjeta.
	SWPK_Vista::caja_abrir(
		'Tiempos de verificación' . SWPK_Vista::ayuda(
			'Son las horas a las que el panel sale a buscar cada tipo de dato. No se cambian desde '
			. 'aquí: las fija el programa. Si el servidor no llama al reloj de WordPress por su '
			. 'cuenta, se cumplen con retraso, y eso lo dice el aviso de abajo.'
		),
		'',
		'swpk-c6'
	);

	$horarios = [
		'Inventario de los sitios'        => 'cada día, 6:30',
		'Lista de vulnerabilidades'       => 'cada día, una vez',
		'Sondeo de caídas'                => 'cada hora',
		'Revisión de configuración'       => 'cada día, 5:30',
		'Lectura de la plataforma'        => 'cada día, 4:30',
		'Reporte de los sitios con plugin' => 'cada día, 5:00',
	];

	foreach ( $horarios as $que => $cuando ) {
		printf(
			'<div class="swpk-par"><span class="swpk-q">%s</span><span class="swpk-v">%s</span></div>',
			esc_html( $que ),
			esc_html( $cuando )
		);
	}

	// El jefe pidió hora fija: «para que nosotros le digamos, oye, todos los
	// días a medianoche». Las horas de arriba son las que están puestas, pero
	// solo se cumplen de verdad si el servidor llama al reloj de WordPress por
	// su cuenta. Si no, el reloj se dispara con la primera visita DESPUÉS de la
	// hora — y en un panel al que entran dos personas, eso puede ser horas más
	// tarde. Decirlo aquí es la diferencia entre una hora y una intención.
	// De qué reloj van estas horas.
	//
	// El panel saca la hora de WordPress —`current_time()`, `wp_timezone()`—
	// y no del hosting, a propósito: así funciona igual esté donde esté
	// instalado. Pero eso significa que si la zona horaria de ESTE WordPress
	// está mal, todas las horas de arriba están mal con ella, y nada lo
	// delata. Por eso se enseña, con la hora que el panel cree que es ahora
	// mismo: si no coincide con el reloj de quien lo mira, ahí está el
	// problema y no en el panel.
	$zona = wp_timezone_string();

	printf(
		'<div class="swpk-par" style="margin-top:14px"><span class="swpk-q">Reloj que usa el panel</span>'
		. '<span class="swpk-v">%s — ahora son las %s</span></div>',
		esc_html( $zona ?: 'sin configurar' ),
		esc_html( wp_date( 'H:i' ) )
	);

	printf(
		'<p class="swpk-gris" style="font-size:12px;margin:6px 0 0">La de este WordPress, no la del hosting. %s <a href="%s">Cambiarla</a>.</p>',
		wp_kses_post( SWPK_Vista::ayuda(
			'Está en Ajustes → Generales. Si esa hora no es la correcta, todo lo de arriba corre a '
			. 'destiempo: los avisos salen antes o después de lo que dicen.'
		) ),
		esc_url( admin_url( 'options-general.php#timezone_string' ) )
	);

	$cron_real = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;

	printf(
		'<div class="notice notice-%s inline" style="margin:16px 0 0"><p style="margin:.5em 0">%s</p></div>',
		$cron_real ? 'success' : 'warning',
		$cron_real
			? 'El servidor llama al reloj por su cuenta, así que estas horas se cumplen.'
			: 'Ojo: estas horas son aproximadas. ' . SWPK_Vista::ayuda(
				'El reloj de WordPress se dispara con la primera visita DESPUÉS de cada hora, no a la '
				. 'hora exacta. En un panel al que entran pocas personas, una corrida de las 6:30 puede '
				. 'acabar pasando a media mañana. Para que se cumplan de verdad hace falta que el '
				. 'servidor llame a wp-cron.php él mismo, y que aquí se ponga DISABLE_WP_CRON. Es un '
				. 'ajuste del hosting, no del panel.'
			)
	);
	?>

	<?php
	// ── «Correr ahora» vive en un solo sitio, y es el Resumen ───────────────
	//
	// Estaba aquí, en el Resumen y en Sitios, con tres nombres distintos. Él
	// lo pidió claro: «que aparezca en un solo lugar». Un botón que hace lo
	// mismo en tres pantallas es un botón del que nadie sabe cuál apretó.
	//
	// Queda en el Resumen porque es donde se entra y donde se ve la barra de
	// avance. Lo que sigue aquí son las otras tres corridas, que no son la
	// misma cosa y no están en ninguna otra parte.
	?>
	<?php if ( SWPK_Perfiles::puede( SWPK_Perfiles::ADMINISTRAR ) ) : ?>
		<?php
		// Cada botón dispara una corrida distinta contra los 108 sitios, y por
		// el nombre no se sabe cuál: «le di a leer la plataforma y ni entiendo
		// qué hice». Ahora cada uno dice qué hace y cuánto tarda antes de que
		// nadie lo pulse.
		?>
		<p class="swpk-acciones-ficha" style="margin:16px 0 0">
			<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=swpk_sondear' ), 'swpk_sondear' ) ); ?>">Sondear ahora</a>
			<?php echo wp_kses_post( SWPK_Vista::ayuda(
				'Llama a los 108 sitios por su dirección y anota cuáles no contestan. Es lo que alimenta '
				. 'la pantalla de Caídas. Tarda menos de un minuto y no cambia nada en ningún sitio.'
			) ); ?>

			<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=swpk_revisar' ), 'swpk_revisar' ) ); ?>">Revisar configuración</a>
			<?php echo wp_kses_post( SWPK_Vista::ayuda(
				'Mide desde fuera las normas que se ven sin entrar al sitio: si exige HTTPS, si publica '
				. 'sus nombres de usuario, si deja leer el readme. Alimenta Seguridad. Tarda unos '
				. 'minutos y no cambia nada en ningún sitio.'
			) ); ?>

			<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=swpk_plataforma' ), 'swpk_plataforma' ) ); ?>">Leer la plataforma</a>
			<?php echo wp_kses_post( SWPK_Vista::ayuda( sprintf(
				'Le pregunta a %s por la configuración de cada sitio: si fuerza HTTPS, cómo tiene la '
				. 'protección contra bots, qué respaldos hay. Es la más lenta —unos 25 minutos para los '
				. '108— porque su API solo deja hacer unas cuantas preguntas por minuto y no se puede '
				. 'apurar. Queda en cola y sigue sola aunque se cierre la pestaña. No cambia nada en '
				. 'ningún sitio.',
				SWPK_Admin::proveedor()
			) ) ); ?>
		</p>
		<p class="swpk-gris" style="margin:10px 0 0">
			La corrida que trae el inventario se dispara desde el
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=panel-kinsta' ) ); ?>">Resumen</a>,
			que es donde se ve cómo va.
		</p>
	<?php endif; ?>
	<?php SWPK_Vista::caja_cerrar(); ?>

	<form method="post" class="swpk-pasa" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="swpk_guardar_ajustes">
		<?php wp_nonce_field( 'swpk_ajustes' ); ?>

		<?php SWPK_Vista::caja_abrir( 'Los grupos de sitios', 'se descubren solos', 'swpk-c6' ); ?>
		<?php $etiquetas = SWPK_Etiquetas::descubrir(); ?>

		<?php if ( ! $etiquetas ) : ?>
			<p style="max-width:780px;color:#50575e">
				Todavía no hay sitios leídos, así que no se sabe con qué etiquetas los agrupa
				<?php echo esc_html( SWPK_Admin::proveedor() ); ?>. Aparecerán solas después de la
				primera sincronización.
			</p>
		<?php else : ?>
			<p class="swpk-aviso-corto">
				Solo hay que tocar esto si alguna quedó en el grupo equivocado.
				<?php echo wp_kses_post( SWPK_Vista::ayuda( sprintf(
					'Son las etiquetas que %s devuelve, tal como están escritas allá. El panel las '
					. 'detectó solo y adivinó a qué grupo corresponde cada una.',
					SWPK_Admin::proveedor()
				) ) ); ?>
			</p>

			<table class="widefat striped swpk-tabla swpk-tabla-fija">
				<thead>
					<tr>
						<th>Grupo</th>
						<th style="width:90px">Sitios</th>
						<th style="width:210px">Qué quiere decir</th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $etiquetas as $clave => $e ) : ?>
					<tr>
						<td>
							<span class="swpk-mono"><?php echo esc_html( $e['nombre'] ); ?></span>
							<?php if ( ! $e['manual'] ) : ?>
								<br><span style="color:#50575e;font-size:12px">detectada sola</span>
							<?php else : ?>
								<br><span style="color:#2271b1;font-size:12px">corregida a mano</span>
							<?php endif; ?>
						</td>
						<td><?php echo (int) $e['sitios']; ?></td>
						<td>
							<select name="etiqueta[<?php echo esc_attr( $clave ); ?>]" class="swpk-ancho-celda">
								<?php foreach ( SWPK_Etiquetas::nombres() as $tag => $nombre_tag ) : ?>
									<option value="<?php echo esc_attr( $tag ); ?>" <?php selected( $e['tag'], $tag ); ?>>
										<?php echo esc_html( $nombre_tag ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<p style="max-width:820px;color:#50575e;margin-top:10px">
				Al cambiar un grupo hay que volver a correr la sincronización para que los sitios se
				reacomoden.
			</p>
		<?php endif; ?>

		<?php SWPK_Vista::caja_cerrar(); ?>

	</form>

	<?php SWPK_Vista::caja_abrir(
		'Cómo está el panel' . SWPK_Vista::ayuda(
			'De cuándo es cada cosa de las que enseña el panel, y cada cuánto se refresca sola. '
			. 'Ninguna pantalla se actualiza mientras la tenés abierta: hay que recargarla.'
		),
		esc_html( 'versión ' . SWPK_VERSION ),
		'swpk-c12',
		true
	); ?>
	<?php // swpk-pares: nombre a la izquierda en gris, dato a la derecha en negrita, como la maqueta. ?>
	<table class="widefat swpk-pares">
		<tbody>
			<tr><td>Fuente de datos</td><td><strong><?php echo esc_html( swpk_fuente()->nombre() ); ?></strong></td></tr>
			<?php $perdidos = SWPK_Eventos::fallos(); ?>
			<?php if ( $perdidos ) : ?>
				<tr>
					<td>Avisos perdidos</td>
					<td>
						<strong style="color:#a4161a"><?php echo esc_html( SWPK_Vista::plural( count( $perdidos ), 'evento no se pudo guardar', 'eventos no se pudieron guardar' ) ); ?>.</strong>
						<br><span style="font-size:12px">
							Un panel que avisa no puede perder avisos callado. El último fue
							<?php echo esc_html( SWPK_Frescura::fecha( (string) $perdidos[0]['cuando'] ) ); ?>:
							<code><?php echo esc_html( (string) $perdidos[0]['error'] ); ?></code>
						</span>
					</td>
				</tr>
			<?php endif; ?>
			<tr>
				<td>Último dato bueno de <?php echo esc_html( swpk_fuente()->proveedor() ); ?></td>
				<td>
					<?php echo esc_html( SWPK_Frescura::fecha_y_edad( SWPK_Frescura::corrida() ) ); ?>
					<?php if ( ! SWPK_Frescura::corrida_al_dia() ) : ?>
						<br><strong style="color:#a4161a">La corrida diaria no está pasando.</strong>
					<?php endif; ?>
					<?php if ( SWPK_Frescura::corrida_fallo() ) : ?>
						<br><span style="color:#a4161a;font-size:12px">
							Hubo un intento posterior, el <?php echo esc_html( SWPK_Frescura::fecha( SWPK_Frescura::intento() ) ); ?>,
							que arrancó y no trajo nada.
						</span>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<td>Último intento de corrida</td>
				<td><?php echo esc_html( SWPK_Frescura::fecha_y_edad( SWPK_Frescura::intento() ) ); ?></td>
			</tr>
			<tr>
				<td>Feed de vulnerabilidades</td>
				<td>
					<?php
					$wf = SWPK_Wordfence::ultima_actualizacion();
					echo '' !== $wf
						? esc_html( SWPK_Frescura::fecha_y_edad( $wf ) )
						: 'nunca se ha descargado';
					?>
					<?php if ( '' !== $wf && ! SWPK_Frescura::feed_al_dia() ) : ?>
						<br><strong style="color:#a4161a">La lista está vieja.</strong>
						<span style="font-size:12px">Una vulnerabilidad publicada estos días todavía no aparece marcada.</span>
					<?php endif; ?>
					<?php $proxima = SWPK_Wordfence::proxima_descarga(); ?>
					<?php if ( $proxima > time() ) : ?>
						<br><span style="color:#50575e;font-size:12px">
							Próxima descarga a partir del <?php echo esc_html( SWPK_Frescura::fmt( 'j \d\e F, H:i', $proxima ) ); ?>.
							<?php if ( SWPK_Wordfence::esperar_hasta() > time() ) : ?>
								<strong>Wordfence pidió esperar</strong>, así que el panel no va a insistir:
								insistir contra su límite puede terminar en que suspendan la llave.
							<?php else : ?>
								El feed se publica una vez al día; pedirlo más seguido no trae nada nuevo.
							<?php endif; ?>
						</span>
					<?php endif; ?>
				</td>
			</tr>
			<tr><td>Próxima corrida automática</td><td>
				<?php
				$ts = wp_next_scheduled( SWPK_Sincronizador::HOOK );
				echo $ts ? esc_html( SWPK_Frescura::fmt( 'j M Y, H:i', gmdate( 'Y-m-d H:i:s', $ts + ( (int) get_option( 'gmt_offset' ) * 3600 ) ) ) ) : 'no programada';
				?>
			</td></tr>
			<tr>
				<td>Llaves</td>
				<td>
					<?php if ( SWPK_Ajustes::llaves_en_claro() ) : ?>
						<span style="color:#8a6a00">Hay alguna sin cifrar</span>
					<?php else : ?>
						<span style="color:#0b6ba8">✓ Cifradas en la base de datos</span>
						<br><span style="color:#50575e;font-size:12px">
							Se cifran con las claves de seguridad de este WordPress, que están en
							<code>wp-config.php</code> y no en la base. Un respaldo de la base de datos,
							por sí solo, no sirve para sacarlas.
						</span>
					<?php endif; ?>
				</td>
			</tr>
			<tr><td>Versión del plugin</td><td><?php echo esc_html( SWPK_VERSION ); ?></td></tr>
		</tbody>
	</table>
	<?php SWPK_Frescura::sello(); ?>
	<?php SWPK_Vista::caja_cerrar(); ?>

	<?php SWPK_Vista::rejilla_cerrar(); ?>
