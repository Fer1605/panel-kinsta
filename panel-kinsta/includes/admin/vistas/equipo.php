<?php
/** Equipo: quién entra al panel y qué ve cada quien. */
defined( 'ABSPATH' ) || exit;

$equipo  = SWPK_Perfiles::del_equipo();
$roles   = SWPK_Perfiles::roles();
$descrip = SWPK_Perfiles::descripciones();
$recien  = get_transient( 'swpk_acceso_nuevo' );
?>
<div class="wrap">
	<h1>Equipo</h1>
	<?php SWPK_Admin::mostrar_mensaje(); ?>

	<?php if ( is_array( $recien ) ) : ?>
		<?php delete_transient( 'swpk_acceso_nuevo' ); ?>
		<div class="notice notice-success">
			<p><strong>Acceso creado para <?php echo esc_html( $recien['nombre'] ); ?>.</strong></p>
			<p>
				Pasale este enlace para que ponga su contraseña. <strong>Vence en un día</strong> y sirve
				una sola vez:
			</p>
			<p><input type="text" readonly onclick="this.select()" class="large-text swpk-mono"
				value="<?php echo esc_attr( $recien['enlace'] ); ?>"></p>
			<p style="color:#50575e">
				No le mandamos una contraseña por correo a propósito: una contraseña escrita en un
				correo se queda escrita para siempre. Con este enlace, la elige la persona y no pasa
				por ningún lado.
			</p>
		</div>
	<?php endif; ?>

	<p style="max-width:860px;color:#3c434a">
		Las personas que entran al panel no son usuarios normales de WordPress: tienen un perfil
		propio que les da acceso al panel y a nada más. No ven entradas, ni medios, ni herramientas,
		y al entrar caen directo aquí.
	</p>

	<h2>Dar acceso a alguien</h2>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="swpk_crear_acceso">
		<?php wp_nonce_field( 'swpk_crear_acceso' ); ?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="nombre">Nombre</label></th>
				<td><input type="text" class="regular-text" name="nombre" id="nombre" required placeholder="Nombre y apellido"></td>
			</tr>
			<tr>
				<th scope="row"><label for="correo">Correo</label></th>
				<td><input type="email" class="regular-text" name="correo" id="correo" required placeholder="nombre@solucionweb.com"></td>
			</tr>
			<tr>
				<th scope="row"><label for="perfil">Perfil</label></th>
				<td>
					<select name="perfil" id="perfil">
						<?php foreach ( $roles as $slug => $nombre ) : ?>
							<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $slug, SWPK_Perfiles::VENTAS ); ?>>
								<?php echo esc_html( $nombre ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
		</table>
		<?php submit_button( 'Crear acceso' ); ?>
	</form>

	<h2>Qué ve cada perfil</h2>
	<div class="swpk-tarjetas">
		<?php foreach ( $roles as $slug => $nombre ) : ?>
			<div class="swpk-tarjeta" style="min-width:250px">
				<p style="font-weight:600;margin:0 0 10px"><?php echo esc_html( $nombre ); ?></p>
				<ul style="margin:0;color:#1d2327">
					<?php foreach ( $descrip[ $slug ]['ve'] as $x ) : ?>
						<li style="margin-bottom:3px">✓ <?php echo esc_html( $x ); ?></li>
					<?php endforeach; ?>
				</ul>
				<?php if ( $descrip[ $slug ]['no_ve'] ) : ?>
					<ul style="margin:10px 0 0;color:#8c8f94">
						<?php foreach ( $descrip[ $slug ]['no_ve'] as $x ) : ?>
							<li style="margin-bottom:3px">✕ <?php echo esc_html( $x ); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
	</div>

	<h2>Quién tiene acceso hoy</h2>
	<?php if ( ! $equipo ) : ?>
		<div class="notice notice-info inline"><p>
			Todavía nadie, aparte de los administradores de este WordPress.
		</p></div>
	<?php else : ?>
		<table class="widefat striped swpk-tabla">
			<thead>
				<tr>
					<th>Persona</th>
					<th style="width:180px">Perfil</th>
					<th style="width:160px">Sitios asignados</th>
					<th style="width:170px">Última entrada</th>
					<th style="width:110px"></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $equipo as $u ) : ?>
				<?php
				global $wpdb;
				$ultima = $wpdb->get_var(
					$wpdb->prepare(
						'SELECT ocurrio_en FROM ' . SWPK_Tablas::tabla( 'accesos' ) . ' WHERE usuario_id = %d AND accion = %s ORDER BY ocurrio_en DESC LIMIT 1',
						$u->ID,
						SWPK_Accesos::ENTRADA
					)
				);
				$asignados = (int) $wpdb->get_var(
					$wpdb->prepare( 'SELECT COUNT(*) FROM ' . SWPK_Tablas::tabla( 'sitios' ) . ' WHERE asesora_id = %d', $u->ID )
				);
				$perfil = array_values( array_intersect( array_keys( $roles ), (array) $u->roles ) )[0] ?? '';
				?>
				<tr>
					<td>
						<strong><?php echo esc_html( $u->display_name ); ?></strong><br>
						<span class="swpk-mono" style="color:#50575e"><?php echo esc_html( $u->user_email ); ?></span>
					</td>
					<td><?php echo esc_html( $roles[ $perfil ] ?? '—' ); ?></td>
					<td>
						<?php if ( SWPK_Perfiles::VENTAS === $perfil ) : ?>
							<?php echo $asignados ? (int) $asignados . ' sitio(s)' : '<span style="color:#d63638">ninguno todavía</span>'; ?>
						<?php else : ?>
							<span style="color:#50575e">todos</span>
						<?php endif; ?>
					</td>
					<td><?php echo $ultima ? esc_html( mysql2date( 'j M Y, H:i', (string) $ultima ) ) : '<span style="color:#50575e">nunca</span>'; ?></td>
					<td>
						<a class="button button-small"
							href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=swpk_quitar_acceso&usuario=' . $u->ID ), 'swpk_quitar_acceso_' . $u->ID ) ); ?>"
							onclick="return confirm('¿Quitarle el acceso al panel a <?php echo esc_js( $u->display_name ); ?>? La cuenta se queda, solo pierde el acceso.');">
							Quitar acceso
						</a>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<p style="color:#50575e">
			Una asesora sin sitios asignados entra al panel y no ve nada. Los sitios se asignan en
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=panel-kinsta-sitios' ) ); ?>">Sitios</a>.
		</p>
	<?php endif; ?>
</div>
