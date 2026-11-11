<?php
/**
 * La bitácora: la pantalla que contesta la pregunta del minuto 02:48.
 *
 * "Desde tal fecha hasta esta fecha, aquí está, se hicieron 10 notificaciones."
 */
defined( 'ABSPATH' ) || exit;

$registros = SWPK_Bitacora::listar( 200 );
$tipos     = SWPK_Bitacora::tipos();
?>
<div class="wrap">
	<h1>Bitácora de notificaciones</h1>
	<?php SWPK_Admin::mostrar_mensaje(); ?>

	<p style="max-width:820px;color:#3c434a">
		Todo lo que el panel notifica queda aquí con su fecha, su destinatario y su contenido. Esto
		es el respaldo: sirve para demostrar qué se avisó, a quién y cuándo.
	</p>

	<div class="notice notice-warning inline" style="max-width:820px">
		<p>
			<strong>Una precisión que importa el día que alguien reclame:</strong> esta bitácora prueba
			que el panel <em>envió</em> el correo. Probar que <em>llegó</em> depende de que el SMTP del
			sitio dé acuse de entrega, y puede que no lo dé. Por eso la columna dice "enviado" y no
			"entregado".
		</p>
	</div>

	<?php if ( ! $registros ) : ?>
		<div class="notice notice-info"><p>
			Todavía no se ha enviado ningún aviso. La bitácora empieza a llenarse cuando se activen
			los correos automáticos.
		</p></div>
	<?php else : ?>
		<table class="widefat striped swpk-tabla">
			<thead>
				<tr>
					<th style="width:140px">Cuándo</th>
					<th style="width:110px">A quién</th>
					<th>Sitio</th>
					<th>Destinatarios</th>
					<th>Asunto</th>
					<th style="width:90px">Resultado</th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $registros as $r ) : ?>
				<?php $sitio = SWPK_Inventario::obtener( $r->sitio_id ); ?>
				<tr>
					<td><?php echo esc_html( mysql2date( 'j M Y, H:i', $r->enviado_en ) ); ?></td>
					<td><?php echo esc_html( $tipos[ $r->tipo ] ?? $r->tipo ); ?></td>
					<td><?php echo esc_html( $sitio->nombre_visible ?? '—' ); ?></td>
					<td class="swpk-mono"><?php echo esc_html( (string) $r->destinatarios ); ?></td>
					<td><?php echo esc_html( (string) $r->asunto ); ?></td>
					<td>
						<?php if ( 'enviado' === $r->resultado ) : ?>
							<span style="color:#008a20">Enviado</span>
						<?php else : ?>
							<span style="color:#d63638" title="<?php echo esc_attr( (string) $r->error ); ?>">
								<?php echo esc_html( $r->resultado ); ?>
							</span>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
