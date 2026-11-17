<?php
/** Ajustes. */
defined( 'ABSPATH' ) || exit;

$a = SWPK_Ajustes::todos();
?>
<div class="wrap">
	<?php
	SWPK_Vista::cabecera(
		'Ajustes del panel',
		'La llave del hosting, los grupos y cómo corre.'
	);
	?>
	<?php SWPK_Admin::mostrar_mensaje(); ?>

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
						En modo de prueba el panel funciona completo con sitios inventados. Es lo que
						permite desarrollarlo y enseñarlo antes de tener accesos.
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
						El campo se muestra en blanco para no reimprimir el secreto. Escribir algo la reemplaza.<br>
						<strong>Kinsta no ofrece llaves de solo lectura:</strong> al crearla solo se elige
						nombre y caducidad, y hereda los permisos de quien la crea. La garantía de solo
						lectura es de este plugin, que únicamente hace peticiones de consulta.
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="wordfence_key">Llave de Wordfence Intelligence</label></th>
				<td>
					<input type="password" class="regular-text" name="wordfence_key" id="wordfence_key"
						value="" autocomplete="off" placeholder="<?php echo '' !== $a['wordfence_key'] ? '•••••••• (ya configurada)' : ''; ?>">
					<p class="description">
						Cuenta gratuita en wordfence.com → Integrations. Es la fuente del "por qué es
						grave": severidad, CVE y qué versión lo corrige. Kinsta solo da un sí/no.
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
	// «Cómo corre» es, en la maqueta, la lista de horarios: cada cuánto pasa
	// cada cosa. Aquí tenía la tabla de estado del panel, que es otra cosa y
	// se bajó a su propia tarjeta.
	SWPK_Vista::caja_abrir( 'Cómo corre', '', 'swpk-c6' );

	$horarios = [
		'Inventario de los sitios'        => 'cada día, 6:30',
		'Lista de vulnerabilidades'       => 'cada día, una vez',
		'Sondeo de caídas'                => 'cada 5 minutos',
		'Reporte de los sitios con plugin' => 'cada día, 5:00',
	];

	foreach ( $horarios as $que => $cuando ) {
		printf(
			'<div class="swpk-par"><span class="swpk-q">%s</span><span class="swpk-v">%s</span></div>',
			esc_html( $que ),
			esc_html( $cuando )
		);
	}
	?>

	<?php if ( SWPK_Perfiles::puede( SWPK_Perfiles::ADMINISTRAR ) ) : ?>
		<p style="margin:16px 0 0">
			<a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=swpk_correr' ), 'swpk_correr' ) ); ?>">Correr ahora</a>
			<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=swpk_sondear' ), 'swpk_sondear' ) ); ?>" style="margin-left:6px">Sondear ahora</a>
		</p>
	<?php endif; ?>
	<?php SWPK_Vista::caja_cerrar(); ?>

	<form method="post" class="swpk-pasa" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="swpk_guardar_ajustes">
		<?php wp_nonce_field( 'swpk_ajustes' ); ?>

		<?php SWPK_Vista::caja_abrir( 'Los grupos de sitios', 'se descubren solos', 'swpk-c7' ); ?>
		<?php $etiquetas = SWPK_Etiquetas::descubrir(); ?>

		<?php if ( ! $etiquetas ) : ?>
			<p style="max-width:780px;color:#50575e">
				Todavía no hay sitios leídos, así que no se sabe con qué etiquetas los agrupa
				<?php echo esc_html( SWPK_Admin::proveedor() ); ?>. Aparecerán solas después de la
				primera sincronización.
			</p>
		<?php else : ?>
			<p style="max-width:820px;color:#3c434a">
				Estas son las etiquetas que <?php echo esc_html( SWPK_Admin::proveedor() ); ?> devuelve,
				tal como están escritas allá. El panel las detectó solo y adivinó a qué grupo
				corresponde cada una. <strong>Solo hay que tocar esto si alguna quedó en el grupo
				equivocado.</strong>
			</p>

			<table class="widefat striped swpk-tabla" style="max-width:760px">
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
							<select name="etiqueta[<?php echo esc_attr( $clave ); ?>]" style="width:100%">
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

		<?php SWPK_Vista::caja_abrir( 'Nuestros dominios', '', 'swpk-c5' ); ?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="dominios_propios">Dominios de la casa</label></th>
				<td>
					<input type="text" class="regular-text" name="dominios_propios" id="dominios_propios" value="<?php echo esc_attr( $a['dominios_propios'] ); ?>">
					<p class="description">
						Los correos de estos dominios se excluyen de los avisos al cliente.
						"Todo lo que sea arroba solución se excluye" (01:57).
					</p>
				</td>
			</tr>
		</table>

		<?php SWPK_Vista::caja_cerrar(); ?>

		<?php SWPK_Vista::caja_abrir( 'A quién le llega', '', 'swpk-c12' ); ?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="correo_ventas">Copia a ventas</label></th>
				<td><input type="text" class="regular-text" name="correo_ventas" id="correo_ventas" value="<?php echo esc_attr( $a['correo_ventas'] ); ?>">
				<p class="description">Recibe copia de lo que se le manda a cada cliente. Una dirección de grupo, o varias separadas por coma.</p></td>
			</tr>
			<tr>
				<th scope="row"><label for="correo_soporte">Correo de soporte</label></th>
				<td><input type="text" class="regular-text" name="correo_soporte" id="correo_soporte" value="<?php echo esc_attr( $a['correo_soporte'] ); ?>">
				<p class="description">Le llega el diario con todo lo técnico.</p></td>
			</tr>
		</table>
		<?php submit_button( 'Guardar' ); ?>
		<?php SWPK_Vista::caja_cerrar(); ?>

	</form>

	<?php SWPK_Vista::caja_abrir( 'Cómo está el panel', esc_html( 'versión ' . SWPK_VERSION ), 'swpk-c12', true ); ?>
	<?php // swpk-pares: nombre a la izquierda en gris, dato a la derecha en negrita, como la maqueta. ?>
	<table class="widefat swpk-pares">
		<tbody>
			<tr><td>Fuente de datos</td><td><strong><?php echo esc_html( swpk_fuente()->nombre() ); ?></strong></td></tr>
			<?php $perdidos = SWPK_Eventos::fallos(); ?>
			<?php if ( $perdidos ) : ?>
				<tr>
					<td>Avisos perdidos</td>
					<td>
						<strong style="color:#b32d2e"><?php echo esc_html( SWPK_Vista::plural( count( $perdidos ), 'evento no se pudo guardar', 'eventos no se pudieron guardar' ) ); ?>.</strong>
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
						<br><strong style="color:#b32d2e">La corrida diaria no está pasando.</strong>
					<?php endif; ?>
					<?php if ( SWPK_Frescura::corrida_fallo() ) : ?>
						<br><span style="color:#b32d2e;font-size:12px">
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
						<br><strong style="color:#b32d2e">La lista está vieja.</strong>
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
						<span style="color:#dba617">Hay alguna sin cifrar</span>
					<?php else : ?>
						<span style="color:#008a20">✓ Cifradas en la base de datos</span>
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
	<?php SWPK_Vista::caja_cerrar(); ?>

	<?php SWPK_Vista::rejilla_cerrar(); ?>
</div>
