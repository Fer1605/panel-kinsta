<?php
/**
 * Correos · Qué sale: el interruptor, cuándo sale cada uno, y generarlos para leerlos.
 *
 * Es la pestaña con la que se entra. La otra —las plantillas— se abre cuando
 * hay que cambiar cómo está redactado un aviso, que es cada varios meses.
 */
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

	<?php SWPK_Vista::rejilla_abrir(); ?>

	<?php SWPK_Vista::caja_abrir( 'El interruptor', '', 'swpk-c5' ); ?>

	<?php
	// Las dos palancas de la maqueta. Cada una es un enlace que dispara su
	// acción con su nonce, no un <input>: el estado no se guarda escribiendo
	// en un formulario, se cambia pulsando. Y la de salir de modo de prueba
	// pregunta antes, porque al otro lado hay clientes de verdad.
	?>
	<a class="swpk-interruptor<?php echo $activos ? ' swpk-encendido' : ''; ?>"
		href="<?php echo esc_url( $accion( 'swpk_envios' ) ); ?>">
		<span class="swpk-palanca"></span>
		<span>
			<span class="swpk-que">
				<?php echo $activos ? 'Los avisos están encendidos' : 'Los avisos están apagados'; ?>
			</span>
			<span class="swpk-como">
				El interruptor general. Apagarlo no borra nada: solo deja de mandar.
				<strong><?php echo $activos ? 'Pulsa para apagarlos.' : 'Pulsa para encenderlos.'; ?></strong>
			</span>
		</span>
	</a>

	<a class="swpk-interruptor<?php echo $en_prueba ? '' : ' swpk-encendido swpk-ojo'; ?>"
		href="<?php echo esc_url( $accion( 'swpk_modo_envio' ) ); ?>"
		<?php if ( $en_prueba ) : ?>
			onclick="return confirm('A partir de ahora los correos van a salir de verdad, a clientes reales. ¿Seguro?');"
		<?php endif; ?>>
		<span class="swpk-palanca"></span>
		<span>
			<span class="swpk-que">
				Modo de envío: <?php echo $en_prueba ? 'prueba' : 'real'; ?>
			</span>
			<span class="swpk-como">
				<?php if ( $en_prueba ) : ?>
					En prueba los correos no salen a los clientes: se arman y se guardan en la
					bitácora. Hay que cambiarlo a propósito.
					<strong>Pulsa para activar el envío real.</strong>
				<?php else : ?>
					Los correos están saliendo a clientes de verdad.
					<strong>Pulsa para volver a modo de prueba.</strong>
				<?php endif; ?>
			</span>
		</span>
	</a>

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
	<?php SWPK_Vista::caja_cerrar(); ?>

	<?php SWPK_Vista::caja_abrir( 'Qué sale, y a quién', '', 'swpk-c7', true ); ?>
	<table class="widefat striped swpk-tabla" style="max-width:980px">
		<thead>
			<tr>
				<th style="width:200px">El aviso</th>
				<th>Qué dice</th>
				<th style="width:180px">Cuándo</th>
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
				<td><?php echo $proximos['Semanal a las asesoras'] ? esc_html( SWPK_Frescura::fmt( 'j M, H:i', $proximos['Semanal a las asesoras'] ) ) : '<span style="color:#d63638">sin programar</span>'; ?></td>
				<td><a class="button" href="<?php echo esc_url( $accion( 'swpk_correo_semanal' ) ); ?>">Mandar una prueba</a></td>
			</tr>
			<tr>
				<td><strong>Diario a soporte</strong><br><span style="color:#50575e;font-size:12px">todos los días 7:30</span></td>
				<td>Lo que cambió en las últimas 24 horas en toda la cartera.</td>
				<td><?php echo $proximos['Diario a soporte'] ? esc_html( SWPK_Frescura::fmt( 'j M, H:i', $proximos['Diario a soporte'] ) ) : '<span style="color:#d63638">sin programar</span>'; ?></td>
				<td><a class="button" href="<?php echo esc_url( $accion( 'swpk_correo_diario' ) ); ?>">Generarlo ahora</a></td>
			</tr>
			<tr>
				<td><strong>Mensual a los clientes</strong><br><span style="color:#50575e;font-size:12px">día 1 a las 8:00</span></td>
				<td>
					A los administradores de cada sitio que administramos nosotros, excluyendo las
					direcciones propias. Cuántas veces se actualizó su sitio y qué se mantuvo al día.
				</td>
				<td><?php echo $proximos['Mensual a los clientes'] ? esc_html( SWPK_Frescura::fmt( 'j M, H:i', $proximos['Mensual a los clientes'] ) ) : '<span style="color:#d63638">sin programar</span>'; ?></td>
				<td><a class="button" href="<?php echo esc_url( $accion( 'swpk_correo_mensual' ) ); ?>">Generarlo ahora</a></td>
			</tr>
		</tbody>
	</table>
	<?php SWPK_Vista::caja_cerrar(); ?>

	<?php
	// La tarjeta de la maqueta. Aquí solo ENSEÑA: los campos viven en Ajustes,
	// que es donde está el formulario que los guarda. Repetir el formulario en
	// dos pantallas es la forma segura de que un día guarden cosas distintas.
	$aj = SWPK_Ajustes::todos();
	SWPK_Vista::caja_abrir(
		'A quién le llega',
		sprintf(
			'<a href="%s">Cambiarlo en Ajustes</a>',
			esc_url( admin_url( 'admin.php?page=panel-kinsta-ajustes' ) )
		),
		'swpk-c12'
	);
	?>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row">Correo de soporte</th>
			<td>
				<code><?php echo esc_html( $aj['correo_soporte'] ?: '— sin poner —' ); ?></code>
				<p class="description">Le llega el diario con todo lo técnico.</p>
			</td>
		</tr>
		<tr>
			<th scope="row">Copia a ventas</th>
			<td>
				<code><?php echo esc_html( $aj['correo_ventas'] ?: '— sin poner —' ); ?></code>
				<p class="description">Recibe copia de lo que se le manda a cada cliente.</p>
			</td>
		</tr>
		<tr>
			<th scope="row">Dominios nuestros</th>
			<td>
				<code><?php echo esc_html( $aj['dominios_propios'] ?: '— sin poner —' ); ?></code>
				<p class="description">Los correos de estos dominios no cuentan como contacto del cliente.</p>
			</td>
		</tr>
	</table>
	<?php SWPK_Vista::caja_cerrar(); ?>

	<?php SWPK_Vista::caja_abrir( 'Cosas que conviene revisar antes de abrir la llave', '', 'swpk-c12', true ); ?>
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

<?php SWPK_Vista::caja_cerrar(); ?>

<?php SWPK_Vista::rejilla_cerrar(); ?>

<p class="swpk-nota">
	"Generarlo ahora" arma el correo con los datos de hoy y lo deja en la
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=panel-kinsta-bitacora' ) ); ?>">bitácora</a>,
	donde se puede abrir y leer completo. En modo de prueba no sale a nadie.
</p>
