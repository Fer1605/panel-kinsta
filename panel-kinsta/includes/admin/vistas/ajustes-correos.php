<?php
/**
 * Ajustes · Correos: a qué direcciones nuestras sale cada cosa.
 *
 * Estaba metido al final de «El panel», entre la llave del hosting y la tabla
 * de estado, en una tarjeta llamada «A quién le llega» que no decía nada:
 * «ni siquiera entiendo eso de a quién le llega, ¿qué significa?». Son tres
 * direcciones nuestras, cada una para un correo distinto, y ahora viven en su
 * propia pestaña con una burbuja que dice exactamente qué llega a cada una.
 *
 * Ojo con qué NO está aquí: a quién se le escribe de cada sitio no se
 * configura, se calcula — son los administradores que reporta el agente,
 * menos los de nuestros dominios, y se ven en la ficha de cada sitio.
 */

defined( 'ABSPATH' ) || exit;

$a = SWPK_Ajustes::todos();
?>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<input type="hidden" name="action" value="swpk_guardar_ajustes">
	<input type="hidden" name="volver_a" value="correos">
	<?php wp_nonce_field( 'swpk_ajustes' ); ?>

	<?php SWPK_Vista::rejilla_abrir(); ?>

	<?php SWPK_Vista::caja_abrir( 'Direcciones nuestras', '', 'swpk-c7' ); ?>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="correo_soporte">Correo de soporte</label></th>
			<td>
				<input type="text" class="regular-text" name="correo_soporte" id="correo_soporte" value="<?php echo esc_attr( $a['correo_soporte'] ); ?>">
				<p class="description">
					Le llega el resumen diario con todo lo técnico.
					<?php echo wp_kses_post( SWPK_Vista::ayuda(
						'Dos correos van a esta dirección: el resumen diario de las 7:30, con todo lo que '
						. 'cambió en la cartera en 24 horas, y el resumen de los domingos, con todo lo que '
						. 'el panel notificó durante la semana. Pueden ser varias direcciones separadas '
						. 'por coma, o una de grupo.'
					) ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="correo_ventas">Copia a ventas</label></th>
			<td>
				<input type="text" class="regular-text" name="correo_ventas" id="correo_ventas" value="<?php echo esc_attr( $a['correo_ventas'] ); ?>">
				<p class="description">
					Recibe copia del correo semanal de las asesoras.
					<?php echo wp_kses_post( SWPK_Vista::ayuda(
						'Cada lunes a las 7:00 cada asesora recibe el suyo, con sus sitios pendientes; a '
						. 'esta dirección le llega una copia de todos. No reemplaza a las asesoras del '
						. 'Equipo, que siguen recibiendo cada una lo suyo: es para que dirección o ventas '
						. 'vean lo mismo sin tener que pedirlo. Déjala en blanco si no hace falta.'
					) ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="dominios_propios">Dominios de la casa</label></th>
			<td>
				<input type="text" class="regular-text" name="dominios_propios" id="dominios_propios" value="<?php echo esc_attr( $a['dominios_propios'] ); ?>">
				<p class="description">
					Los correos de estos dominios no cuentan como contacto del cliente.
					<?php echo wp_kses_post( SWPK_Vista::ayuda(
						'Separados por coma. Somos administradores de los sitios que llevamos, así que sin '
						. 'esto el reporte que va dirigido al cliente nos llegaría también a nosotros. '
						. 'Cualquier dirección de estos dominios queda fuera de los correos a clientes.'
					) ); ?>
				</p>
			</td>
		</tr>
	</table>
	<?php submit_button( 'Guardar' ); ?>
	<?php SWPK_Vista::caja_cerrar(); ?>

	<?php
	// Qué sale a cada una, en una tabla. Es la pregunta que se hace al llegar
	// aquí, y antes había que deducirla leyendo las descripciones de abajo.
	SWPK_Vista::caja_abrir( 'Qué llega a cada una', '', 'swpk-c5', true );
	?>
	<table class="widefat striped swpk-tabla swpk-tabla-fija">
		<thead>
			<tr>
				<th>El correo</th>
				<th>A dónde</th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td>Resumen diario, 7:30</td>
				<td>Soporte</td>
			</tr>
			<tr>
				<td>Resumen de los domingos, 18:00</td>
				<td>Soporte</td>
			</tr>
			<tr>
				<td>Semanal de cada asesora, lunes 7:00</td>
				<td>Cada asesora, con copia a ventas</td>
			</tr>
			<tr>
				<td>Reporte mensual, día 1</td>
				<td>Los administradores de cada sitio, menos los de la casa</td>
			</tr>
			<tr>
				<td>Sitio caído, y sitio repuesto</td>
				<td>Soporte, en el momento</td>
			</tr>
		</tbody>
	</table>
	<?php SWPK_Vista::caja_cerrar(); ?>

	<?php SWPK_Vista::rejilla_cerrar(); ?>
</form>

<p class="swpk-aviso-corto">
	Cuándo sale cada uno y cómo está redactado se cambia en
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=panel-kinsta-correos' ) ); ?>">Correos</a>.
</p>
