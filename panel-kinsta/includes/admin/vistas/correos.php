<?php
/** Correos: el interruptor, cuándo sale cada uno, y generarlos para leerlos. */
defined( 'ABSPATH' ) || exit;

$en_prueba = SWPK_Correos::en_prueba();
$activos   = SWPK_Correos::activos();

$proximos = [
	'Semanal a las asesoras' => wp_next_scheduled( SWPK_Correos::HOOK_SEMANAL ),
	'Diario a soporte'       => wp_next_scheduled( SWPK_Correos::HOOK_DIARIO ),
	'Mensual a los clientes' => wp_next_scheduled( SWPK_Correos::HOOK_MENSUAL ),
];

$accion = static fn( $a ) => wp_nonce_url( admin_url( 'admin-post.php?action=' . $a ), $a );
?>
<div class="wrap">
	<h1>Correos</h1>
	<?php SWPK_Admin::mostrar_mensaje(); ?>

	<?php if ( ! $activos ) : ?>
		<div class="notice notice-error">
			<p><strong>Los envíos están apagados.</strong> No sale ningún correo, ni de prueba ni real.</p>
		</div>
	<?php elseif ( $en_prueba ) : ?>
		<div class="notice notice-warning">
			<p>
				<strong>Modo de prueba.</strong> Los correos se arman completos y quedan en la bitácora
				para leerlos, pero <strong>no se envían a nadie</strong>.
			</p>
			<p>
				Es a propósito: el primer lote sale a clientes reales, y un error de redacción o un
				destinatario mal calculado se vuelve cien correos que no se pueden recoger. Conviene
				leer unos cuantos antes de abrir la llave.
			</p>
		</div>
	<?php else : ?>
		<div class="notice notice-success">
			<p><strong>Envío real activo.</strong> Los correos salen de verdad a asesoras, clientes y soporte.</p>
		</div>
	<?php endif; ?>

	<h2>El interruptor</h2>
	<table class="widefat striped" style="max-width:760px">
		<tbody>
			<tr>
				<td style="width:230px"><strong>Modo</strong></td>
				<td>
					<?php echo $en_prueba ? 'Prueba: se arman y se guardan, no se envían' : 'Real: los correos salen'; ?>
				</td>
				<td style="width:200px;text-align:right">
					<a class="button <?php echo $en_prueba ? 'button-primary' : ''; ?>"
						href="<?php echo esc_url( $accion( 'swpk_modo_envio' ) ); ?>"
						<?php if ( $en_prueba ) : ?>
							onclick="return confirm('A partir de ahora los correos van a salir de verdad, a clientes reales. ¿Seguro?');"
						<?php endif; ?>>
						<?php echo $en_prueba ? 'Activar el envío real' : 'Volver a modo de prueba'; ?>
					</a>
				</td>
			</tr>
			<tr>
				<td><strong>Envíos</strong></td>
				<td><?php echo $activos ? 'Encendidos' : 'Apagados: no sale nada'; ?></td>
				<td style="text-align:right">
					<a class="button" href="<?php echo esc_url( $accion( 'swpk_envios' ) ); ?>">
						<?php echo $activos ? 'Apagar todo' : 'Encender'; ?>
					</a>
				</td>
			</tr>
		</tbody>
	</table>

	<h2>Qué sale, cuándo y a quién</h2>
	<table class="widefat striped swpk-tabla" style="max-width:980px">
		<thead>
			<tr>
				<th style="width:200px">Correo</th>
				<th>A quién</th>
				<th style="width:180px">Próximo</th>
				<th style="width:160px"></th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td><strong>Semanal a las asesoras</strong><br><span style="color:#50575e;font-size:12px">lunes 7:00</span></td>
				<td>
					A cada asesora, solo sus sitios con algo pendiente, con el texto listo para reenviarle
					al cliente. Copia a la dirección de ventas si está configurada.
				</td>
				<td><?php echo $proximos['Semanal a las asesoras'] ? esc_html( wp_date( 'j M, H:i', $proximos['Semanal a las asesoras'] ) ) : '<span style="color:#d63638">sin programar</span>'; ?></td>
				<td><a class="button" href="<?php echo esc_url( $accion( 'swpk_correo_semanal' ) ); ?>">Generarlo ahora</a></td>
			</tr>
			<tr>
				<td><strong>Diario a soporte</strong><br><span style="color:#50575e;font-size:12px">todos los días 7:30</span></td>
				<td>Lo que cambió en las últimas 24 horas en toda la cartera.</td>
				<td><?php echo $proximos['Diario a soporte'] ? esc_html( wp_date( 'j M, H:i', $proximos['Diario a soporte'] ) ) : '<span style="color:#d63638">sin programar</span>'; ?></td>
				<td><a class="button" href="<?php echo esc_url( $accion( 'swpk_correo_diario' ) ); ?>">Generarlo ahora</a></td>
			</tr>
			<tr>
				<td><strong>Mensual a los clientes</strong><br><span style="color:#50575e;font-size:12px">día 1 a las 8:00</span></td>
				<td>
					A los administradores de cada sitio que administramos nosotros, excluyendo las
					direcciones propias. Cuántas veces se actualizó su sitio y qué se mantuvo al día.
				</td>
				<td><?php echo $proximos['Mensual a los clientes'] ? esc_html( wp_date( 'j M, H:i', $proximos['Mensual a los clientes'] ) ) : '<span style="color:#d63638">sin programar</span>'; ?></td>
				<td><a class="button" href="<?php echo esc_url( $accion( 'swpk_correo_mensual' ) ); ?>">Generarlo ahora</a></td>
			</tr>
		</tbody>
	</table>

	<p style="color:#50575e;max-width:900px">
		"Generarlo ahora" arma el correo con los datos de hoy y lo deja en la
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=panel-kinsta-bitacora' ) ); ?>">bitácora</a>,
		donde se puede abrir y leer completo. En modo de prueba no sale a nadie.
	</p>

	<h2>Cosas que conviene revisar antes de abrir la llave</h2>
	<?php
	$sin_asesora  = 0;
	$sin_contacto = 0;

	foreach ( SWPK_Inventario::todos() as $s ) {
		if ( ! $s->asesora_id ) {
			++$sin_asesora;
		}
		if ( ! SWPK_Etiquetas::es_nuestro( $s->tag ) && '' === trim( (string) $s->contacto_cliente ) ) {
			++$sin_contacto;
		}
	}

	$revisiones = [
		[
			'ok'   => ! $sin_asesora,
			'si'   => 'Todos los sitios tienen asesora asignada.',
			'no'   => sprintf( '%d sitios no tienen asesora asignada: nadie va a recibir su aviso semanal.', $sin_asesora ),
			'link' => admin_url( 'admin.php?page=panel-kinsta-sitios' ),
		],
		[
			'ok'   => ! $sin_contacto,
			'si'   => 'Los sitios que administra el cliente tienen contacto escrito.',
			'no'   => sprintf( '%d sitios que administra el cliente no tienen contacto: su aviso no tiene a dónde salir.', $sin_contacto ),
			'link' => admin_url( 'admin.php?page=panel-kinsta-sitios&tag=no_update' ),
		],
		[
			'ok'   => '' !== trim( (string) SWPK_Ajustes::obtener( 'correo_soporte', '' ) ),
			'si'   => 'Hay dirección de soporte configurada.',
			'no'   => 'Falta la dirección de soporte: el resumen diario no tiene a dónde salir.',
			'link' => admin_url( 'admin.php?page=panel-kinsta-ajustes' ),
		],
		[
			'ok'   => (bool) SWPK_Perfiles::del_equipo(),
			'si'   => 'Hay asesoras con acceso al panel.',
			'no'   => 'Todavía no hay nadie con perfil de ventas: el correo semanal no tiene destinatarios.',
			'link' => admin_url( 'admin.php?page=panel-kinsta-equipo' ),
		],
	];
	?>
	<table class="widefat striped" style="max-width:900px">
		<tbody>
		<?php foreach ( $revisiones as $r ) : ?>
			<tr>
				<td style="width:40px;font-size:18px"><?php echo $r['ok'] ? '<span style="color:#008a20">✓</span>' : '<span style="color:#dba617">!</span>'; ?></td>
				<td><?php echo esc_html( $r['ok'] ? $r['si'] : $r['no'] ); ?></td>
				<td style="width:120px;text-align:right">
					<?php if ( ! $r['ok'] ) : ?>
						<a class="button button-small" href="<?php echo esc_url( $r['link'] ); ?>">Arreglarlo</a>
					<?php endif; ?>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
</div>
