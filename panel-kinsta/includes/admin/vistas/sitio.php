<?php
/**
 * La ficha de un sitio: todo lo suyo en un solo lugar, y el mensaje para la
 * asesora listo para copiar.
 *
 * Se entra desde la lista de Sitios o desde la de Vulnerabilidades, y arriba
 * siempre hay un botón para volver a donde se venía, con el grupo y el filtro
 * que la persona había elegido.
 */
defined( 'ABSPATH' ) || exit;

$sitio_id = isset( $_GET['sitio'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['sitio'] ) ) : '';
$sitio    = $sitio_id ? SWPK_Inventario::obtener( $sitio_id ) : null;
$regreso  = SWPK_Vista::url_de_regreso();

if ( ! $sitio || ! SWPK_Perfiles::puede_ver_sitio( $sitio_id ) ) {
	echo '<div class="wrap"><h1>Sitio</h1>';
	SWPK_Vista::volver( $regreso['url'], $regreso['texto'] );
	echo '<div class="notice notice-error"><p>Ese sitio no existe o no está entre los tuyos.</p></div></div>';
	return;
}

$hallazgos = SWPK_Riesgo::hallazgos( $sitio_id );
$nivel     = SWPK_Riesgo::nivel_del_sitio( $sitio_id );

$con_vuln = array_values( array_filter( $hallazgos, static fn( $h ) => 'actualizacion_pendiente' !== $h['motivo'] ) );
$solo_act = array_values( array_filter( $hallazgos, static fn( $h ) => 'actualizacion_pendiente' === $h['motivo'] ) );

$plano = SWPK_Textos::para_asesora( $sitio, $hallazgos );
?>
<div class="wrap">
	<?php SWPK_Vista::volver( $regreso['url'], $regreso['texto'] ); ?>

	<h1 style="margin-bottom:4px">
		<?php echo wp_kses_post( SWPK_Admin::nivel_badge( $nivel ) ); ?>
		<?php echo esc_html( $sitio->nombre_visible ); ?>
	</h1>
	<p style="margin:0 0 16px">
		<a href="<?php echo esc_url( 'https://' . ltrim( $sitio->dominio, 'htps:/' ) ); ?>" target="_blank" rel="noopener"
			class="swpk-mono" style="color:#50575e"><?php echo esc_html( $sitio->dominio ); ?></a>
		<span style="color:#50575e"> · <?php echo esc_html( SWPK_Etiquetas::nombres()[ $sitio->tag ] ); ?></span>
	</p>

	<?php SWPK_Admin::mostrar_mensaje(); ?>

	<div class="swpk-tarjetas">
		<div class="swpk-tarjeta">
			<div class="swpk-n" style="color:<?php echo count( $con_vuln ) ? '#b32d2e' : '#008a20'; ?>"><?php echo count( $con_vuln ); ?></div>
			<div class="swpk-r">Con vulnerabilidad conocida</div>
		</div>
		<div class="swpk-tarjeta">
			<div class="swpk-n"><?php echo count( $solo_act ); ?></div>
			<div class="swpk-r">Solo actualización pendiente</div>
		</div>
		<div class="swpk-tarjeta">
			<div class="swpk-n"><?php echo esc_html( $sitio->version_wp ?: '—' ); ?></div>
			<div class="swpk-r">WordPress</div>
		</div>
		<div class="swpk-tarjeta">
			<div class="swpk-n"><?php echo esc_html( $sitio->version_php ?: '—' ); ?></div>
			<div class="swpk-r">PHP</div>
		</div>
		<?php if ( SWPK_Etiquetas::es_nuestro( $sitio->tag ) ) : ?>
			<div class="swpk-tarjeta">
				<div class="swpk-n" style="font-size:19px;padding-top:6px;color:<?php echo $sitio->tiene_2fa ? '#008a20' : '#d63638'; ?>">
					<?php echo $sitio->tiene_2fa ? '✓ Activa' : 'Pendiente'; ?>
				</div>
				<div class="swpk-r">Doble autenticación</div>
			</div>
		<?php endif; ?>
	</div>

	<?php $dias = SWPK_Inventario::dias_sin_escanear( $sitio ); ?>

	<?php if ( null === $dias ) : ?>
		<div class="notice notice-warning">
			<p>
				<strong>Este sitio no ha sido escaneado por <?php echo esc_html( SWPK_Admin::proveedor() ); ?></strong>,
				así que su lista de módulos llega vacía. Una lista vacía no significa que el sitio esté
				limpio: significa que no sabemos qué tiene.
			</p>
			<p>
				Esa lista la arma <?php echo esc_html( SWPK_Admin::proveedor() ); ?> por su cuenta, sitio por
				sitio y cada uno a su ritmo. Normalmente aparece sola en unos días. Si pasa una semana
				así, vale la pena abrirles un ticket.
			</p>
		</div>
	<?php elseif ( SWPK_Inventario::dato_viejo( $sitio ) ) : ?>
		<div class="notice notice-warning">
			<p>
				<strong>Este dato tiene <?php echo (int) $dias; ?> días.</strong>
				No ha vuelto a ser revisado por <?php echo esc_html( SWPK_Admin::proveedor() ); ?> desde el
				<?php echo esc_html( mysql2date( 'j \d\e F', (string) $sitio->inventario_en ) ); ?>, así que
				lo de abajo puede estar desactualizado.
			</p>
		</div>
	<?php else : ?>
		<p style="color:#50575e;margin:-6px 0 16px">
			Lo que hay instalado en este sitio fue revisado por <?php echo esc_html( SWPK_Admin::proveedor() ); ?> el
			<?php echo esc_html( mysql2date( 'j \d\e F \a \l\a\s H:i', (string) $sitio->inventario_en ) ); ?>
			<?php echo 0 === $dias ? '(hoy)' : sprintf( '(hace %d día%s)', (int) $dias, 1 === $dias ? '' : 's' ); ?>.
		</p>
	<?php endif; ?>

	<?php if ( $hallazgos ) : ?>
		<h2>El mensaje para la asesora</h2>
		<p style="color:#50575e;margin-top:-6px">
			Escrito para que el cliente entienda qué se juega, no solo que hay que actualizar.
		</p>

		<div class="swpk-mensaje">
			<pre style="white-space:pre-wrap;font-family:inherit;font-size:14px;line-height:1.6;margin:0"><?php echo esc_html( $plano ); ?></pre>
			<textarea id="swpk-plano" style="position:absolute;left:-9999px" readonly><?php echo esc_textarea( $plano ); ?></textarea>
			<p class="swpk-copiar">
				<button type="button" class="button button-primary"
					onclick="var t=document.getElementById('swpk-plano');t.select();document.execCommand('copy');this.textContent='✓ Copiado';">
					Copiar el texto
				</button>
				<span style="color:#50575e;margin-left:8px">listo para pegar en una cotización o un correo</span>
			</p>
		</div>

		<h2>Todo lo que tiene pendiente</h2>
		<table class="widefat striped swpk-tabla">
			<thead>
				<tr>
					<th>Módulo</th>
					<th style="width:160px">Versión</th>
					<th style="width:95px">Nivel</th>
					<th style="width:230px">Quién puede aprovecharlo</th>
					<th>Qué permite</th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $hallazgos as $h ) : ?>
				<?php $v = $h['vulnerabilidades'][0] ?? null; ?>
				<tr>
					<td>
						<strong><?php echo esc_html( SWPK_Textos::limpiar_titulo( (string) $h['titulo'] ) ); ?></strong><br>
						<span class="swpk-mono" style="color:#50575e"><?php echo esc_html( $h['slug'] ); ?></span>
						<?php if ( '' === $h['auto_update'] ) : ?>
							<br><span style="color:#dba617;font-size:12px">sin actualización automática</span>
						<?php endif; ?>
					</td>
					<td>
						<span class="swpk-mono"><?php echo esc_html( $h['version'] ); ?></span>
						<?php if ( '' !== $h['version_disponible'] ) : ?>
							→ <span class="swpk-mono" style="color:#008a20"><?php echo esc_html( $h['version_disponible'] ); ?></span>
						<?php endif; ?>
					</td>
					<td><?php echo wp_kses_post( SWPK_Admin::nivel_badge( $h['nivel'] ) ); ?></td>
					<td>
						<?php if ( $v ) : ?>
							<?php $quien = SWPK_Textos::quien_puede( (string) ( $v->cvss_vector ?? '' ), (string) $v->descripcion ); ?>
							<span style="color:<?php echo $quien['sin_cuenta'] ? '#b32d2e' : '#1d2327'; ?>">
								<?php echo esc_html( $quien['frase'] ); ?>
							</span>
						<?php else : ?>
							<span style="color:#50575e">—</span>
						<?php endif; ?>
					</td>
					<td>
						<?php if ( $v ) : ?>
							<?php echo esc_html( SWPK_Textos::explicar( (string) $v->titulo, (string) $v->descripcion ) ); ?>
							<?php if ( '' !== (string) $v->cve ) : ?>
								<br><span class="swpk-mono" style="color:#50575e"><?php echo esc_html( (string) $v->cve ); ?></span>
							<?php endif; ?>
						<?php elseif ( 'vulnerable_sin_detalle' === $h['motivo'] ) : ?>
							<span style="color:#d63638">El hosting la marca como vulnerable</span>,
							<span style="color:#50575e">a la espera del detalle público.</span>
						<?php else : ?>
							<span style="color:#50575e">Solo actualización pendiente, sin vulnerabilidad conocida.</span>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	<?php elseif ( SWPK_Riesgo::SIN_DATOS === $nivel ) : ?>
		<div class="notice notice-warning inline">
			<p>
				<strong>Aquí no hay nada que mostrar, y eso no es una buena noticia.</strong>
				No es que este sitio esté al día: es que no tenemos su lista de módulos, así que no
				hay nada contra qué comparar.
			</p>
		</div>
	<?php else : ?>
		<div class="notice notice-success"><p>
			Este sitio no tiene nada pendiente: todos sus módulos están al día y ninguno tiene una
			vulnerabilidad conocida.
		</p></div>
	<?php endif; ?>

	<?php SWPK_Vista::volver( $regreso['url'], $regreso['texto'] ); ?>

	<p class="swpk-atribucion"><?php echo wp_kses_post( SWPK_Wordfence::atribucion() ); ?></p>
</div>
