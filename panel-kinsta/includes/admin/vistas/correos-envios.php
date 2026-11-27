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
	'Resumen de los domingos' => wp_next_scheduled( SWPK_Correos::HOOK_RESUMEN ),
];

$accion = static fn( $a ) => wp_nonce_url( admin_url( 'admin-post.php?action=' . $a ), $a );

// ── «Mandar una prueba. ¿A quién?» ──────────────────────────────────────────
//
// Los cuatro botones que decían eso llamaban al envío de verdad. El del
// mensual le escribe al contacto de cada uno de los 109 sitios.
//
// Ahora son dos botones que dicen la verdad, y el de la prueba lleva la
// dirección escrita dentro —como en el panel que nos enseñó— para que no haya
// que preguntarse a dónde fue.
$mi_correo = SWPK_Admin::correo_de_quien_entro();

$prueba_url = static fn( string $aviso ) => wp_nonce_url(
	admin_url( 'admin-post.php?action=swpk_correo_prueba&aviso=' . rawurlencode( $aviso ) ),
	'swpk_correo_prueba'
);

/**
 * Los dos botones de una fila. El de verdad va en rojo y pregunta antes,
 * diciendo a quién le va a escribir: una confirmación que no dice el alcance
 * no es una confirmación, es un trámite.
 */
$botones_de = static function ( string $aviso, string $accion_real, string $a_quien ) use ( $accion, $prueba_url, $mi_correo ): string {
	$html = '';

	if ( '' !== $mi_correo ) {
		$html .= sprintf(
			'<a class="button" href="%s" title="%s">Enviármelo a mí (%s)</a> ',
			esc_url( $prueba_url( $aviso ) ),
			esc_attr( 'Arma el correo de verdad, con los datos de verdad, y te lo manda solo a vos. No le llega a nadie más, ni siquiera con el envío real encendido.' ),
			esc_html( $mi_correo )
		);
	}

	$html .= sprintf(
		'<a class="button swpk-boton-peligro" href="%s" onclick="return confirm(%s);">Enviarlo de verdad ahora</a>',
		esc_url( $accion( $accion_real ) ),
		esc_attr( wp_json_encode( sprintf( 'Esto manda el correo de verdad, ahora mismo, a %s. No se puede recoger. ¿Seguro?', $a_quien ) ) )
	);

	return $html;
};

// «Aquí debería haber un botón que diga ver, editar»: cada aviso abre su
// plantilla. Y la abre ENCIMA, no en otra pestaña — «levantalo en un modal,
// no me mandes a pantallitas de pantallitas» (17:11). Las ventanas las dibuja
// correos-editor.php, al final de esta pantalla.
// ── El interruptor de cada aviso ───────────────────────────────────────────
//
// «Esto es un switch, deberían de ser switch nada más» (19/11). Hasta hoy el
// único interruptor era el general, y eso obliga a elegir entre que salga todo
// o que no salga nada — cuando lo normal es querer parar UNO. El caso real:
// el reporte mensual sale a clientes y todavía no hay autorización.
$interruptor_de = static function ( string $aviso ): string {
	$encendido = SWPK_Correos::encendido( $aviso );

	return sprintf(
		'<a class="swpk-sale%1$s" href="%2$s">%3$s</a>',
		$encendido ? ' swpk-sale-si' : '',
		esc_url(
			wp_nonce_url(
				add_query_arg(
					[ 'action' => 'swpk_aviso', 'aviso' => $aviso, 'encender' => $encendido ? '0' : '1' ],
					admin_url( 'admin-post.php' )
				),
				'swpk_aviso_' . $aviso
			)
		),
		$encendido ? 'Sale' : 'Apagado'
	);
};

$texto_de = static fn( string $plantilla ) => sprintf(
	'<button type="button" class="button" data-swpk-editar="%s">Ver y editar el texto</button>',
	esc_attr( $plantilla )
);
?>

	<?php
	// ── Los dos interruptores, arriba y a lo ancho ──────────────────────────
	//
	// Estaban en una columna estrecha a la izquierda, y al lado la tabla de
	// qué sale quedaba apretada y con scroll propio. «Los interruptores no
	// deben de estar así: eso debe de estar arriba, a la par de las pestañas,
	// y lo de qué sale y a quién en todo horizontal, para no meterle scrolls
	// internos a los paneles.»
	//
	// Cada palanca es un enlace que dispara su acción con su nonce, no un
	// <input>: el estado no se guarda escribiendo en un formulario, se cambia
	// pulsando. Y la de salir de modo de prueba pregunta antes, porque al otro
	// lado hay clientes de verdad.
	?>
	<div class="swpk-interruptores">
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
					historial de envíos. Hay que cambiarlo a propósito.
					<strong>Pulsa para activar el envío real.</strong>
				<?php else : ?>
					Los correos están saliendo a clientes de verdad.
					<strong>Pulsa para volver a modo de prueba.</strong>
				<?php endif; ?>
			</span>
		</span>
	</a>
	</div>

		<?php
		$apagados_ahora = SWPK_Correos::apagados();
		if ( $apagados_ahora ) :
			$nombres_apagados = [];
			foreach ( $apagados_ahora as $a ) {
				$nombres_apagados[] = (string) ( SWPK_Plantillas::catalogo()[ $a ]['nombre'] ?? $a );
			}
			?>
		<div class="notice notice-warning">
			<p>
				<strong><?php echo esc_html( SWPK_Vista::plural( count( $nombres_apagados ), 'aviso apagado', 'avisos apagados' ) ); ?>:</strong>
				<?php echo esc_html( implode( ', ', $nombres_apagados ) ); ?>.
				Cuando les toque no salen, y queda anotado en el historial que tocaba.
				<?php echo wp_kses_post( SWPK_Vista::ayuda(
					'Apagar un aviso no lo esconde: su fila sigue apareciendo en el historial de envíos '
					. 'con resultado «apagado», para poder demostrar dentro de tres meses que ese día '
					. 'tocaba y no salió a propósito. El reloj sigue corriendo; lo que no sale, no sale.'
				) ); ?>
			</p>
		</div>
		<?php endif; ?>

		<?php if ( ! $activos ) : ?>
		<div class="notice notice-error">
			<p><strong>Los envíos están apagados.</strong> No sale ningún correo, ni de prueba ni real.</p>
		</div>
		<?php elseif ( $en_prueba ) : ?>
		<div class="notice notice-warning">
			<p>
				<strong>Modo de prueba:</strong> los correos se arman enteros y quedan en el historial
				para leerlos, pero no salen a nadie.
				<?php echo wp_kses_post( SWPK_Vista::ayuda(
					'Es a propósito. El primer lote sale a clientes reales, y un error de redacción o un '
					. 'destinatario mal calculado se vuelve cien correos que no se pueden recoger. '
					. 'Conviene leer unos cuantos antes de abrir la llave.'
				) ); ?>
			</p>
		</div>
		<?php else : ?>
		<div class="notice notice-success">
			<p><strong>Envío real activo.</strong> Los correos salen de verdad a asesoras, clientes y soporte.</p>
		</div>
		<?php endif; ?>

	<?php SWPK_Vista::rejilla_abrir(); ?>

	<?php SWPK_Vista::caja_abrir( 'Los avisos automáticos', esc_html( SWPK_Vista::plural( count( $proximos ), 'aviso', 'avisos' ) ), 'swpk-c12', true ); ?>
	<table class="widefat striped swpk-tabla">
		<thead>
			<tr>
				<th style="width:190px">El aviso</th>
				<th style="width:90px">¿Sale?</th>
				<th style="width:150px">Cuándo</th>
				<th>Qué dice</th>
				<th style="width:300px"></th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td><strong>Semanal a las asesoras</strong></td>
				<td><?php echo wp_kses_post( $interruptor_de( 'semanal' ) ); ?></td>
				<td>
					Cada lunes, 7:00<br>
					<span class="swpk-gris" style="font-size:12px">próximo: <?php echo $proximos['Semanal a las asesoras'] ? esc_html( SWPK_Frescura::fmt( 'j M, H:i', $proximos['Semanal a las asesoras'] ) ) : '<span style="color:#9e0b10">sin programar</span>'; ?></span>
				</td>
				<td>
					A cada asesora, solo sus sitios con algo pendiente, con el texto listo para reenviarle
					al cliente. Copia a la dirección de ventas si está configurada.
				</td>
				<td class="swpk-acciones-fila">
					<?php echo wp_kses_post( $texto_de( 'semanal' ) ); ?>
					<?php echo wp_kses_post( $botones_de( 'semanal', 'swpk_correo_semanal', 'cada asesora con sitios pendientes, con copia a ventas' ) ); ?>
				</td>
			</tr>
			<tr>
				<td><strong>Diario a soporte</strong></td>
				<td><?php echo wp_kses_post( $interruptor_de( 'diario' ) ); ?></td>
				<td>
					Cada día, 7:30<br>
					<span class="swpk-gris" style="font-size:12px">próximo: <?php echo $proximos['Diario a soporte'] ? esc_html( SWPK_Frescura::fmt( 'j M, H:i', $proximos['Diario a soporte'] ) ) : '<span style="color:#9e0b10">sin programar</span>'; ?></span>
				</td>
				<td>Lo que cambió en las últimas 24 horas en toda la cartera.</td>
				<td class="swpk-acciones-fila">
					<?php echo wp_kses_post( $texto_de( 'diario' ) ); ?>
					<?php echo wp_kses_post( $botones_de( 'diario', 'swpk_correo_diario', 'soporte' ) ); ?>
				</td>
			</tr>
			<tr>
				<td><strong>Mensual a los clientes</strong></td>
				<td><?php echo wp_kses_post( $interruptor_de( 'mensual' ) ); ?></td>
				<td>
					El día 1, 8:00<br>
					<span class="swpk-gris" style="font-size:12px">próximo: <?php echo $proximos['Mensual a los clientes'] ? esc_html( SWPK_Frescura::fmt( 'j M, H:i', $proximos['Mensual a los clientes'] ) ) : '<span style="color:#9e0b10">sin programar</span>'; ?></span>
				</td>
				<td>
					A los administradores de cada sitio que administramos nosotros, excluyendo las
					direcciones propias. Cuántas veces se actualizó su sitio y qué se mantuvo al día.
				</td>
				<td class="swpk-acciones-fila">
					<?php echo wp_kses_post( $texto_de( 'mensual' ) ); ?>
					<?php echo wp_kses_post( $botones_de( 'mensual', 'swpk_correo_mensual', 'LOS CLIENTES de todos los sitios que llevamos nosotros' ) ); ?>
				</td>
			</tr>
			<tr>
				<td><strong>Resumen de los domingos</strong></td>
				<td><?php echo wp_kses_post( $interruptor_de( 'resumen' ) ); ?></td>
				<td>
					Cada domingo, 18:00<br>
					<span class="swpk-gris" style="font-size:12px">próximo: <?php echo $proximos['Resumen de los domingos'] ? esc_html( SWPK_Frescura::fmt( 'j M, H:i', $proximos['Resumen de los domingos'] ) ) : '<span style="color:#9e0b10">sin programar</span>'; ?></span>
				</td>
				<td>
					A soporte: todo lo que el panel notificó durante la semana, con el resultado de
					cada envío. <strong>Sale también cuando no hubo nada</strong> — un domingo sin
					correo no querría decir «no pasó nada» sino «no sé si el panel corrió».
				</td>
				<td class="swpk-acciones-fila">
					<?php echo wp_kses_post( $texto_de( 'resumen' ) ); ?>
					<?php echo wp_kses_post( $botones_de( 'resumen', 'swpk_correo_resumen', 'soporte' ) ); ?>
				</td>
			</tr>
		</tbody>
	</table>
	<?php SWPK_Vista::caja_cerrar(); ?>

	<?php
	// ── Los avisos que no esperan a un horario ──────────────────────────────
	//
	// Faltaban en esta pantalla, y eran justo los que el jefe echaba en falta
	// —«sitio caído, pero nunca me avisó»—. Si la tabla de arriba era «los
	// avisos», no estar en ella se lee como no existir.
	//
	// El de «sigue caído» es nuevo (25/11/2026) y tapa un agujero que se vio
	// en producción: el aviso de caída sale una vez, en el momento del
	// cambio, y nunca más. Un sitio lleva dos días sin responder y del correo
	// no se vuelve a saber.
	SWPK_Vista::caja_abrir( 'Los avisos del momento', 'no esperan a un horario', 'swpk-c12', true );

	$del_momento = [
		[
			'nombre'   => 'Sitio caído',
			'cuando'   => 'En cuanto deja de responder',
			'que'      => 'Un sitio dejó de responder dos veces seguidas. Va a soporte y a la lista de avisos de caída.',
			'plantilla' => 'caida',
		],
		[
			'nombre'   => 'Sigue caído',
			'cuando'   => 'Una vez al día, mientras no vuelva',
			'que'      => 'El recordatorio de que un sitio caído <strong>sigue</strong> caído, con cuánto lleva y cuándo se comprobó por última vez. No sale el mismo día de la caída: ese día ya salió el aviso de arriba.',
			'plantilla' => 'sigue_caido',
		],
		[
			'nombre'   => 'Sitio repuesto',
			'cuando'   => 'En cuanto vuelve',
			'que'      => 'El sitio volvió a responder, y cuánto estuvo caído.',
			'plantilla' => 'repuesto',
		],
		[
			'nombre'   => 'El agente se calló',
			'cuando'   => 'A las 48 horas sin reporte',
			'que'      => 'El sitio puede estar perfecto, pero de él ya no entra nada nuevo: ni administradores, ni dos pasos, ni normas. Lo que el panel enseñe de ese sitio es una foto vieja.',
			'plantilla' => 'agente_callado',
		],
		[
			'nombre'   => 'Sitio dado de baja',
			'cuando'   => 'A las 5 sincronizaciones sin aparecer',
			'que'      => 'El hosting dejó de devolverlo. Deja de contar en totales y alarmas; sus datos y sus avisos se quedan.',
			'plantilla' => 'de_baja',
		],
	];
	?>
	<table class="widefat striped swpk-tabla">
		<thead>
			<tr>
				<th style="width:190px">El aviso</th>
				<th style="width:90px">¿Sale?</th>
				<th style="width:150px">Cuándo</th>
				<th>Qué dice</th>
				<th style="width:300px"></th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ( $del_momento as $m ) : ?>
			<tr>
				<td><strong><?php echo esc_html( $m['nombre'] ); ?></strong></td>
				<td><?php echo wp_kses_post( $interruptor_de( (string) $m['plantilla'] ) ); ?></td>
				<td><?php echo esc_html( $m['cuando'] ); ?></td>
				<td><?php echo wp_kses_post( $m['que'] ); ?></td>
				<td class="swpk-acciones-fila">
					<?php echo wp_kses_post( $texto_de( $m['plantilla'] ) ); ?>
				</td>
			</tr>
		<?php endforeach; ?>
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
		'swpk-c6'
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
			<th scope="row">Avisos de caída</th>
			<td>
				<code><?php echo esc_html( $aj['correo_caidas'] ?: '— sin poner —' ); ?></code>
				<p class="description">
					Se <strong>suma</strong> a soporte cuando un sitio se cae, cuando vuelve, cuando
					se da de baja y cuando el agente de un sitio se calla.
				</p>
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

	<?php SWPK_Vista::caja_abrir(
		'Lo que falta para que un aviso llegue' . SWPK_Vista::ayuda(
			'Un aviso solo sale si tiene a quién. Esta lista dice cuántos sitios no tienen a nadie a '
			. 'quien escribirle y por qué, para que no se descubra el día que se abra la llave y no '
			. 'salga nada.'
		),
		'',
		'swpk-c6',
		true
	); ?>
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
	<table class="widefat striped swpk-tabla swpk-tabla-fija">
		<tbody>
		<?php foreach ( $revisiones as $r ) : ?>
			<tr>
				<td style="width:40px;font-size:18px"><?php echo $r['ok'] ? '<span style="color:#0b5ed7">✓</span>' : '<span style="color:#9e0b10">!</span>'; ?></td>
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
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=panel-kinsta-correos&seccion=bitacora' ) ); ?>">el historial de envíos</a>,
	donde se puede abrir y leer completo. En modo de prueba no sale a nadie.
</p>

<?php require SWPK_RUTA . 'includes/admin/vistas/correos-editor.php'; ?>
