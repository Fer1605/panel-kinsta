<?php
/** Ajustes. */
defined( 'ABSPATH' ) || exit;

$a = SWPK_Ajustes::todos();
?>
<div class="wrap">
	<h1>Ajustes del panel</h1>
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

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="swpk_guardar_ajustes">
		<?php wp_nonce_field( 'swpk_ajustes' ); ?>

		<h2>Conexión</h2>
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

		<h2>Las tres etiquetas de MyKinsta</h2>
		<p class="description" style="max-width:760px">
			El panel adivina a qué grupo pertenece cada sitio por el nombre de su etiqueta. Si los
			nombres reales no se parecen a lo esperado, se escriben aquí tal como están en MyKinsta.
		</p>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="etiqueta_desarrollo">Desarrollo</label></th>
				<td><input type="text" class="regular-text" name="etiqueta_desarrollo" id="etiqueta_desarrollo" value="<?php echo esc_attr( $a['etiqueta_desarrollo'] ); ?>"></td>
			</tr>
			<tr>
				<th scope="row"><label for="etiqueta_auto">Auto update</label></th>
				<td><input type="text" class="regular-text" name="etiqueta_auto" id="etiqueta_auto" value="<?php echo esc_attr( $a['etiqueta_auto'] ); ?>"></td>
			</tr>
			<tr>
				<th scope="row"><label for="etiqueta_no_update">No auto update</label></th>
				<td><input type="text" class="regular-text" name="etiqueta_no_update" id="etiqueta_no_update" value="<?php echo esc_attr( $a['etiqueta_no_update'] ); ?>"></td>
			</tr>
		</table>

		<h2>Correos</h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="correo_ventas">Ventas</label></th>
				<td><input type="text" class="regular-text" name="correo_ventas" id="correo_ventas" value="<?php echo esc_attr( $a['correo_ventas'] ); ?>">
				<p class="description">Dirección de grupo o varias separadas por coma.</p></td>
			</tr>
			<tr>
				<th scope="row"><label for="correo_soporte">Soporte</label></th>
				<td><input type="text" class="regular-text" name="correo_soporte" id="correo_soporte" value="<?php echo esc_attr( $a['correo_soporte'] ); ?>"></td>
			</tr>
			<tr>
				<th scope="row"><label for="dominios_propios">Dominios nuestros</label></th>
				<td>
					<input type="text" class="regular-text" name="dominios_propios" id="dominios_propios" value="<?php echo esc_attr( $a['dominios_propios'] ); ?>">
					<p class="description">
						Los correos de estos dominios se excluyen de los avisos al cliente.
						"Todo lo que sea arroba solución se excluye" (01:57).
					</p>
				</td>
			</tr>
		</table>

		<?php submit_button( 'Guardar ajustes' ); ?>
	</form>

	<hr>
	<h2>Probar la conexión</h2>
	<p>
		<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=swpk_probar' ), 'swpk_probar' ) ); ?>">
			Probar <?php echo esc_html( swpk_fuente()->nombre() ); ?>
		</a>
	</p>

	<h2>Estado</h2>
	<table class="widefat striped" style="max-width:720px">
		<tbody>
			<tr><td>Fuente de datos</td><td><strong><?php echo esc_html( swpk_fuente()->nombre() ); ?></strong></td></tr>
			<tr><td>Última sincronización</td><td><?php echo esc_html( SWPK_Inventario::ultima_sincronizacion() ?: 'nunca' ); ?></td></tr>
			<tr><td>Feed de vulnerabilidades</td><td><?php echo esc_html( SWPK_Wordfence::ultima_actualizacion() ?: 'nunca' ); ?></td></tr>
			<tr><td>Próxima corrida automática</td><td>
				<?php
				$ts = wp_next_scheduled( SWPK_Sincronizador::HOOK );
				echo $ts ? esc_html( mysql2date( 'j M Y, H:i', gmdate( 'Y-m-d H:i:s', $ts + ( (int) get_option( 'gmt_offset' ) * 3600 ) ) ) ) : 'no programada';
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
</div>
