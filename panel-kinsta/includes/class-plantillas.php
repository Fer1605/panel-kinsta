<?php
/**
 * El contenido de los correos.
 *
 * Tres audiencias, tres idiomas distintos para los mismos hechos:
 *
 *   Asesora  argumento de venta: qué pasa si no se hace, listo para reenviar
 *   Cliente  tranquilidad y constancia, sin una sola palabra técnica
 *   Soporte  el dato crudo, sin adornos
 *
 * Se escriben con estilos en línea porque los clientes de correo descartan las
 * hojas de estilo. Es feo de escribir y es la única forma de que se vea igual
 * en Gmail, en Outlook y en el teléfono.
 */

defined( 'ABSPATH' ) || exit;

class SWPK_Plantillas {

	private const AZUL  = '#2271b1';
	private const ROJO  = '#9e0b10';
	private const GRIS  = '#646970';
	/** El mostaza de SWPK_Riesgo: el recuadro de «ya te avisamos N veces». */
	private const MOSTAZA = '#8a6a00';

	/**
	 * El logo que va arriba de todos los correos.
	 *
	 * Es un PNG y no el SVG del tema a propósito: Gmail, Outlook y la app de
	 * correo del teléfono NO dibujan SVG — lo dejan como un hueco o como el
	 * icono de imagen rota. El logo del tema de solucionweb.com es
	 * `logo-azul-sw.svg`, así que no sirve aquí por mucho que sea el oficial.
	 *
	 * Se puede cambiar en Ajustes → Correos sin tocar código. Si alguien pone
	 * ahí un SVG, se ignora y se usa este: ver `logo()`.
	 */
	public const LOGO_DE_FABRICA = 'https://www.solucionweb.com/wp-content/uploads/2026/11/logo-solucion-web-azul-.png';

	public const OPCION_LOGO = 'swpk_logo_correo';

	/** Donde se guarda lo que se edite. Lo que no esté aquí, sale de fábrica. */
	public const OPCION = 'swpk_plantillas';

	/**
	 * Las plantillas editables.
	 *
	 * ─── Qué se puede cambiar y qué no ──────────────────────────────────────
	 *
	 * Se edita lo que es prosa: el asunto y el texto que acompaña. NO se edita
	 * el bloque de datos — la lista de sitios con sus pendientes, la tabla de
	 * lo que cambió — porque eso lo arma el panel con la información de cada
	 * día, y un campo de texto no puede producirlo.
	 *
	 * Ese bloque entra por una marca: donde se escriba `{{sitios}}`, ahí va. Se
	 * puede mover de sitio, poner texto antes o después, o quitarlo. Lo que no
	 * se puede es escribirlo a mano, y está bien que así sea.
	 *
	 * Son seis: los cuatro avisos programados —semanal, diario, mensual y el
	 * resumen de los domingos— y los dos que salen en el momento, cuando un
	 * sitio se cae y cuando vuelve. Cada uno de los cuatro programados se abre
	 * desde su renglón en «Qué sale, y a quién», que es donde se le busca.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function catalogo(): array {
		return [
			'semanal' => [
				'nombre' => 'Semanal a las asesoras',
				'quien'  => 'A cada asesora, cada lunes: sus sitios con algo pendiente.',
				'titulo' => 'Tus sitios esta semana',
				'asunto' => '{{cuantos}} de tus sitios necesitan atención esta semana',
				'cuerpo' => "Hola {{asesora}},\n\n"
					. "De los sitios que atendés, {{cuantos}} tienen algo pendiente esta semana{{criticos}}.\n\n"
					. "{{sitios}}\n\n"
					. 'Si querés que nos encarguemos nosotros de las actualizaciones, avisanos y lo coordinamos.',
				'marcas' => [
					'{{asesora}}'  => 'El nombre de la asesora',
					'{{cuantos}}'  => 'Cuántos de sus sitios tienen algo pendiente',
					'{{criticos}}' => 'La coletilla «, y N en estado alto o crítico». Si no hay ninguno, se queda en blanco sola.',
					'{{sitios}}'   => 'EL BLOQUE: un recuadro por sitio, con el texto listo para copiarle al cliente. Dentro de cada recuadro, el panel añade solo la frase de insistencia cuando toca: «es la tercera vez seguida que avisamos y sigue igual», o qué se movió desde el aviso anterior.',
				],
			],
			'diario'  => [
				'nombre' => 'Diario a soporte',
				'quien'  => 'A la dirección de soporte, todos los días: lo que cambió en 24 horas.',
				'titulo' => 'Resumen del día',
				'asunto' => 'Resumen del día: {{cuantos}} cambios en la cartera',
				'cuerpo' => "{{estado}}\n\n"
					. "Esto cambió en las últimas 24 horas ({{cuantos}} en total):\n\n"
					. '{{cambios}}',
				'marcas' => [
					'{{cuantos}}' => 'Cuántos cambios hubo',
					'{{estado}}'  => 'EL BLOQUE: cómo está la cartera HOY — qué sitios no responden, cuáles están en rojo y por qué, y cuántos no se pueden medir. Es lo que pasa ahora, no lo que cambió.',
					'{{cambios}}' => 'EL BLOQUE: la tabla de qué cambió, en qué sitio y cuándo.',
				],
			],
			'resumen' => [
				'nombre' => 'Resumen semanal de lo enviado',
				'quien'  => 'A soporte, cada domingo: todo lo que el panel notificó durante la semana.',
				'titulo' => 'La semana en el panel',
				'asunto' => 'Resumen de la semana: {{movimientos}} movimientos y {{cuantos}} avisos',
				'cuerpo' => "Esta es la semana del {{desde}} al {{hasta}}.\n\n"
					. "{{movimiento}}\n\n"
					. "{{tabla}}\n\n"
					. 'Todo esto queda guardado en el panel: los movimientos en Movimientos y los avisos en '
					. 'el historial de envíos, con el contenido completo de cada correo y la fecha exacta en que salió.',
				'marcas' => [
					'{{cuantos}}'    => 'Cuántos avisos salieron en la semana',
					'{{movimientos}}' => 'Cuántos movimientos hubo en los sitios',
					'{{desde}}'      => 'El primer día de la semana',
					'{{hasta}}'      => 'El último día de la semana',
					'{{movimiento}}' => 'EL BLOQUE: qué pasó en los sitios durante la semana.',
					'{{tabla}}'      => 'EL BLOQUE: el desglose de lo que se avisó, y cada aviso uno por uno.',
				],
			],
			'caida' => [
				'nombre' => 'Aviso de caída',
				'quien'  => 'A soporte, en el momento: un sitio dejó de responder.',
				'titulo' => 'Un sitio no está respondiendo',
				'asunto' => '[Caído] {{sitio}}',
				'cuerpo' => "{{sitio}} no está respondiendo.\n\n"
					. "Dirección: {{url}}\n"
					. "Detectado: {{cuando}}\n"
					. "Qué pasó: {{motivo}}\n"
					. "Intentos: {{intentos}}\n\n"
					. 'El monitor vuelve a revisar dentro de una hora. Si para entonces ya responde, '
					. 'les llega otro correo.',
				'marcas' => [
					'{{sitio}}'    => 'El nombre del sitio',
					'{{url}}'      => 'La dirección que se sondeó',
					'{{cuando}}'   => 'Cuándo se detectó, en hora de Guatemala',
					'{{motivo}}'   => 'Qué contestó el servidor, en palabras: «el dominio no resuelve», «rechazó la conexión»…',
					'{{intentos}}' => 'Cuántas veces seguidas se le preguntó antes de avisar, y en cuánto tiempo',
				],
			],
			'sigue_caido' => [
				'nombre' => 'Sigue caído',
				'quien'  => 'A soporte y a avisos de caída, una vez al día mientras el sitio no vuelva.',
				'titulo' => 'Un sitio sigue sin responder',
				'asunto' => '[Sigue caído] {{sitio}} — {{cuanto}}',
				'cuerpo' => "{{sitio}} sigue sin responder.\n\n"
					. "Dirección: {{url}}\n"
					. "Lleva caído: {{cuanto}}\n"
					. "Dejó de responder: {{desde}}\n"
					. "Última revisión: {{revisado}}\n"
					. "Qué contesta: {{motivo}}\n\n"
					. 'Este aviso se repite una vez al día hasta que el sitio vuelva, o hasta que se dé '
					. 'de baja del panel.',
				'marcas' => [
					'{{sitio}}'    => 'El nombre del sitio',
					'{{url}}'      => 'La dirección que se sondeó',
					'{{cuanto}}'   => 'Cuánto lleva caído, en palabras: «2 días», «14 horas»',
					'{{desde}}'    => 'Cuándo dejó de responder',
					'{{revisado}}' => 'Cuándo fue la última vez que el panel lo comprobó',
					'{{motivo}}'   => 'Qué contestó el servidor, en palabras',
				],
			],
			'agente_callado' => [
				'nombre' => 'El agente de un sitio se calló',
				'quien'  => 'A soporte y a avisos de caída: un sitio lleva más de 48 horas sin reportar.',
				'titulo' => 'Un sitio dejó de reportar',
				'asunto' => '[Sin reportar] {{sitio}}',
				'cuerpo' => "El agente de {{sitio}} lleva {{cuanto}} sin mandar su reporte.\n\n"
					. "Dirección: {{url}}\n"
					. "Último reporte: {{ultimo}}\n\n"
					. "Esto no quiere decir que el sitio esté caído. Quiere decir que de ese sitio el "
					. "panel ya no sabe nada nuevo: ni sus administradores, ni su verificación en dos "
					. "pasos, ni sus normas de seguridad. Lo que se vea de él en el panel es de hace "
					. "{{cuanto}}.\n\n"
					. 'Lo normal es que alguien haya desactivado el plugin, que el sitio esté en '
					. 'mantenimiento, o que el reloj del sitio dejara de correr.',
				'marcas' => [
					'{{sitio}}'  => 'El nombre del sitio',
					'{{url}}'    => 'La dirección del sitio',
					'{{cuanto}}' => 'Cuánto lleva sin reportar, en palabras',
					'{{ultimo}}' => 'Cuándo fue su último reporte',
				],
			],
			'repuesto' => [
				'nombre' => 'Aviso de recuperación',
				'quien'  => 'A soporte, en el momento: un sitio que estaba caído volvió.',
				'titulo' => 'Un sitio volvió a responder',
				'asunto' => '[Repuesto] {{sitio}}',
				// ─── Por qué ya no dice «estuvo caído 1h 1min» ──────────────
				//
				// Porque el panel no lo sabe y lo estaba diciendo igual.
				// Revisa UNA VEZ POR HORA: lo único que midió es que a las
				// 8:33 no contestó y a las 9:33 sí. Entre esas dos horas pudo
				// estar caído un segundo o los sesenta minutos enteros.
				//
				// Decir «1h 1min» es coger la distancia entre dos revisiones
				// y presentarla como una medición. El 26 de noviembre de 2026
				// eso mandó un correo diciendo que un sitio había estado una
				// hora caído cuando se podía entrar sin problema. Es la regla
				// 3 otra vez: no afirmar lo que no se midió.
				'cuerpo' => "{{sitio}} volvió a responder.\n\n"
					. "No respondió en la revisión de las {{fallo}}.\n"
					. "En la siguiente, a las {{vuelta}}, ya respondía.\n\n"
					. "Entre las dos revisiones pasaron {{cuanto}}, pero eso NO es lo que estuvo "
					. "caído: el monitor mira una vez por hora, así que pudo ser un segundo o pudo "
					. "ser todo ese rato. Lo que sí se midió está en Caídas.",
				'marcas' => [
					'{{sitio}}'  => 'El nombre del sitio',
					'{{fallo}}'  => 'La hora de la revisión en la que no contestó',
					'{{vuelta}}' => 'La hora de la revisión en la que ya contestaba',
					'{{cuanto}}' => 'Lo que pasó ENTRE las dos revisiones: «1h 1min». No es lo que estuvo caído, es el máximo que pudo estarlo.',
				],
			],
			'de_baja' => [
				'nombre' => 'Sitio dado de baja',
				'quien'  => 'A soporte, cuando el hosting deja de devolver un sitio cinco veces seguidas.',
				'titulo' => 'Un sitio se dio de baja',
				'asunto' => '[De baja] {{sitio}}',
				'cuerpo' => "{{sitio}} se dio de baja del panel.\n\n"
					. "El hosting dejó de devolverlo en la lista de sitios, y ya van {{veces}} comprobaciones "
					. "seguidas sin que apareciera. Lo normal es que lo hayan borrado de la cuenta.\n\n"
					. "Lo que se midió de este sitio NO se borra: sus datos, sus avisos y su historial se "
					. "quedan en el panel. Lo único que cambia es que deja de contar en los totales y de "
					. "generar alarmas.\n\n"
					. 'Si vuelve a aparecer en el hosting, el panel le quita la baja solo.',
				'marcas' => [
					'{{sitio}}' => 'El nombre del sitio',
					'{{veces}}' => 'Cuántas comprobaciones seguidas pasó sin aparecer',
				],
			],
			'mensual' => [
				'nombre' => 'Mensual a los clientes',
				'quien'  => 'A los administradores de cada sitio nuestro, el día 1. Sin una palabra técnica.',
				'titulo' => 'Reporte de mantenimiento',
				'asunto' => 'Reporte de mantenimiento de {{sitio}}',
				'cuerpo' => "Hola,\n\n"
					. "Le escribimos con el reporte de mantenimiento de {{sitio}} correspondiente a {{mes}}.\n\n"
					. "{{veces}}\n\n"
					. "{{actualizado}}\n\n"
					. "Las actualizaciones corrigen fallas de seguridad que se descubren y se publican "
					. "continuamente. Mantenerlas al día es lo que evita que alguien las aproveche: es la "
					. "diferencia entre un sitio que se mantiene y uno que un día amanece con problemas.\n\n"
					. 'No hay nada que usted tenga que hacer. Le avisamos para que sepa que su sitio está siendo atendido.',
				'marcas' => [
					'{{sitio}}'       => 'El nombre del sitio',
					'{{mes}}'         => 'El mes del que habla el reporte',
					'{{veces}}'       => 'EL BLOQUE: el recuadro azul con «su sitio se actualizó N veces».',
					'{{actualizado}}' => 'EL BLOQUE: la lista de qué se mantuvo al día. Si no hubo nada, se queda en blanco.',
				],
			],
		];
	}

	/**
	 * La dirección del logo que se pinta arriba del correo.
	 *
	 * Un SVG guardado a mano se descarta en vez de usarse: no se ve en ningún
	 * cliente de correo serio, y un logo que no se ve es peor que el de
	 * fábrica. Vacío también cae en el de fábrica.
	 */
	public static function logo(): string {
		$url = trim( (string) get_option( self::OPCION_LOGO, '' ) );

		if ( '' === $url || str_ends_with( strtolower( $url ), '.svg' ) ) {
			return self::LOGO_DE_FABRICA;
		}

		return $url;
	}

	/**
	 * La dirección de una pantalla del panel, para el botón del correo.
	 *
	 * Se fuerza a https: `admin_url()` devuelve lo que diga la opción `home`
	 * de WordPress, y en este panel esa opción está en http. Un botón que
	 * manda a http desde un correo lo marcan como inseguro los clientes de
	 * correo y lo reescribe medio mundo por el camino.
	 *
	 * @param array<string,string> $args
	 */
	public static function url_panel( string $pagina = 'panel-kinsta-movimientos', array $args = [] ): string {
		return set_url_scheme(
			add_query_arg(
				array_merge( [ 'page' => $pagina ], $args ),
				admin_url( 'admin.php' )
			),
			'https'
		);
	}

	/**
	 * El botón que le toca a cada aviso.
	 *
	 * Vive aquí y no repartido por las seis funciones para que la respuesta a
	 * «¿a dónde lleva este correo?» esté en una sola tabla que se pueda leer
	 * entera. El mensual al cliente NO lleva botón, y es el único: el cliente
	 * no tiene usuario en el panel, así que un botón ahí es un enlace a una
	 * pantalla de login que no le va a dejar entrar.
	 *
	 * @return array{texto:string,url:string}|array{}
	 */
	public static function boton_de( string $clave ): array {
		$destinos = [
			'diario'   => [ 'Ver todos los cambios en el panel', 'panel-kinsta-movimientos' ],
			'semanal'  => [ 'Ver estos sitios en el panel', 'panel-kinsta-sitios' ],
			'resumen'  => [ 'Ver el historial completo en el panel', 'panel-kinsta-correos' ],
			'caida'       => [ 'Ver el estado de los sitios', 'panel-kinsta-caidas' ],
			'sigue_caido' => [ 'Ver el estado de los sitios', 'panel-kinsta-caidas' ],
			'agente_callado' => [ 'Ver el sitio en el panel', 'panel-kinsta-sitios' ],
			'repuesto' => [ 'Ver el estado de los sitios', 'panel-kinsta-caidas' ],
			'de_baja'  => [ 'Ver el sitio en el panel', 'panel-kinsta-sitios' ],
		];

		if ( ! isset( $destinos[ $clave ] ) ) {
			return [];
		}

		[ $texto, $pagina ] = $destinos[ $clave ];

		return [
			'texto' => $texto,
			'url'   => self::url_panel(
				$pagina,
				'resumen' === $clave ? [ 'seccion' => 'bitacora' ] : []
			),
		];
	}

	/**
	 * El botón azul del final del correo.
	 *
	 * «Y abajo un botón con url hacia dónde ver esto.» Va armado con una tabla
	 * y no con un `<a>` suelto porque Outlook de escritorio ignora el relleno
	 * de un enlace y lo deja del alto de la letra: con la tabla el botón tiene
	 * el tamaño de un botón en todas partes.
	 */
	public static function boton( string $texto, string $url ): string {
		if ( '' === trim( $texto ) || '' === trim( $url ) ) {
			return '';
		}

		return sprintf(
			'<table role="presentation" cellpadding="0" cellspacing="0" border="0" '
			. 'style="margin:26px auto 6px"><tr>'
			. '<td align="center" bgcolor="%1$s" style="border-radius:6px">'
			. '<a href="%2$s" style="display:inline-block;padding:13px 30px;'
			. 'font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;'
			. 'font-size:14px;font-weight:600;line-height:1;color:#ffffff;'
			. 'text-decoration:none;border-radius:6px">%3$s</a>'
			. '</td></tr></table>',
			self::AZUL,
			esc_url( $url ),
			esc_html( $texto )
		);
	}

	/** Lo guardado, o lo de fábrica si nadie lo ha tocado. */
	public static function obtener( string $clave ): array {
		$de_fabrica = self::catalogo()[ $clave ] ?? null;

		if ( ! $de_fabrica ) {
			return [ 'asunto' => '', 'cuerpo' => '' ];
		}

		$guardadas = get_option( self::OPCION, [] );
		$mia       = is_array( $guardadas ) ? ( $guardadas[ $clave ] ?? [] ) : [];

		return [
			'asunto' => '' !== trim( (string) ( $mia['asunto'] ?? '' ) ) ? (string) $mia['asunto'] : (string) $de_fabrica['asunto'],
			'cuerpo' => '' !== trim( (string) ( $mia['cuerpo'] ?? '' ) ) ? (string) $mia['cuerpo'] : (string) $de_fabrica['cuerpo'],
		];
	}

	/** ¿Esta plantilla está tocada, o es la de fábrica? */
	public static function esta_tocada( string $clave ): bool {
		$guardadas = get_option( self::OPCION, [] );
		return is_array( $guardadas ) && ! empty( $guardadas[ $clave ] );
	}

	public static function guardar( string $clave, string $asunto, string $cuerpo ): void {
		if ( ! isset( self::catalogo()[ $clave ] ) ) {
			return;
		}

		$guardadas = get_option( self::OPCION, [] );
		$guardadas = is_array( $guardadas ) ? $guardadas : [];

		$guardadas[ $clave ] = [
			'asunto' => sanitize_text_field( $asunto ),
			// El cuerpo NO pasa por sanitize_text_field: eso le comería los
			// saltos de línea, que es justo lo que separa los párrafos.
			'cuerpo' => wp_check_invalid_utf8( $cuerpo ),
		];

		update_option( self::OPCION, $guardadas, false );
	}

	public static function restaurar( string $clave ): void {
		$guardadas = get_option( self::OPCION, [] );

		if ( is_array( $guardadas ) && isset( $guardadas[ $clave ] ) ) {
			unset( $guardadas[ $clave ] );
			update_option( self::OPCION, $guardadas, false );
		}
	}

	/**
	 * El asunto, con sus marcas sustituidas. Texto plano.
	 *
	 * @param array<string,string> $valores
	 */
	public static function asunto( string $clave, array $valores ): string {
		$asunto = self::obtener( $clave )['asunto'];

		return trim( str_replace( array_keys( $valores ), array_values( $valores ), $asunto ) );
	}

	/**
	 * El cuerpo de la plantilla, convertido a HTML de correo.
	 *
	 * Se escribe en texto normal, con una línea en blanco entre párrafos. Cada
	 * párrafo sale envuelto en <p>; un párrafo que sea SOLO una marca de bloque
	 * se reemplaza por el bloque entero, sin envolverlo.
	 *
	 * Lo escrito se escapa antes de sustituir nada: quien edita una plantilla
	 * tiene permiso de administrar el panel, pero esto acaba en el buzón de un
	 * cliente y una etiqueta suelta ahí no la quiere nadie. Los bloques entran
	 * después, y esos los arma el panel.
	 *
	 * @param array<string,string> $enlinea Marcas de texto: ya escapadas.
	 * @param array<string,string> $bloques Marcas de bloque: HTML que arma el panel.
	 */
	public static function cuerpo( string $clave, array $enlinea = [], array $bloques = [] ): string {
		return self::armar( self::obtener( $clave )['cuerpo'], $enlinea, $bloques );
	}

	/**
	 * Lo mismo, pero sobre un texto que se le pasa en vez del guardado.
	 *
	 * Existe por la vista previa: él quiere ver cómo queda ANTES de guardar,
	 * y sin que salga ningún correo. Con `cuerpo()` eso no se podía, porque
	 * siempre leía de la base.
	 *
	 * @param array<string,string> $enlinea Marcas de texto: ya escapadas.
	 * @param array<string,string> $bloques Marcas de bloque: HTML que arma el panel.
	 */
	public static function armar( string $plantilla, array $enlinea = [], array $bloques = [] ): string {
		$parrafos = preg_split( '/\R\s*\R/u', trim( $plantilla ) ) ?: [];
		$html     = '';

		foreach ( $parrafos as $parrafo ) {
			$parrafo = trim( (string) $parrafo );

			if ( '' === $parrafo ) {
				continue;
			}

			// ¿Es un párrafo que no dice más que «pon el bloque aquí»?
			if ( array_key_exists( $parrafo, $bloques ) ) {
				$html .= $bloques[ $parrafo ];
				continue;
			}

			$linea = esc_html( $parrafo );

			foreach ( $enlinea as $marca => $valor ) {
				$linea = str_replace( $marca, $valor, $linea );
			}

			// Una marca de bloque metida a media frase no puede salir en bruto:
			// ahí no cabe una tabla. Se quita, y el párrafo sigue leyéndose.
			foreach ( array_keys( $bloques ) as $marca ) {
				$linea = str_replace( $marca, '', $linea );
			}

			$html .= '<p>' . nl2br( $linea ) . '</p>';
		}

		return $html;
	}

	/**
	 * La vista previa de una plantilla, con datos de ejemplo y sin mandar nada.
	 *
	 * «Que no te lo mande, sino la previsualización.» El botón de antes decía
	 * «Ver cómo queda» y llevaba a generar el correo de verdad; esto dibuja el
	 * correo entero —marco, cabecera y pie incluidos— con valores de muestra,
	 * y sobre el texto que esté escrito en el formulario, esté guardado o no.
	 *
	 * Los bloques que arma el panel con los datos del día no se pueden
	 * inventar: en la vista previa salen como un recuadro que dice qué va ahí.
	 * Es preferible a enseñar una tabla falsa que luego no coincide.
	 *
	 * @return array{asunto:string,html:string}
	 */
	public static function ejemplo( string $clave, string $asunto = '', string $cuerpo = '' ): array {
		$catalogo = self::catalogo();

		if ( ! isset( $catalogo[ $clave ] ) ) {
			return [ 'asunto' => '', 'html' => '' ];
		}

		$ficha   = $catalogo[ $clave ];
		$guardada = self::obtener( $clave );
		$asunto  = '' !== trim( $asunto ) ? $asunto : (string) $guardada['asunto'];
		$cuerpo  = '' !== trim( $cuerpo ) ? $cuerpo : (string) $guardada['cuerpo'];

		// Un valor de muestra por marca de texto. Son los que se leen bien en
		// una frase: un nombre, un número, una fecha.
		$muestra = [
			'{{asesora}}'     => 'María',
			'{{cuantos}}'     => '3',
			'{{criticos}}'    => ', y 1 en estado alto o crítico',
			'{{movimientos}}' => '12',
			'{{desde}}'       => '9 de noviembre',
			'{{hasta}}'       => '15 de noviembre',
			'{{sitio}}'       => 'Sitio de ejemplo',
			'{{mes}}'         => 'octubre',
			'{{url}}'         => 'https://ejemplo.com',
			'{{cuando}}'      => 'hoy a las 14:05',
			'{{cuanto}}'      => '12 minutos',
			'{{motivo}}'      => 'rechazó la conexión',
		];

		$enlinea = [];
		$bloques = [];

		foreach ( (array) $ficha['marcas'] as $marca => $explica ) {
			$marca = (string) $marca;

			if ( str_starts_with( (string) $explica, 'EL BLOQUE' ) ) {
				$bloques[ $marca ] = sprintf(
					'<div style="border:1px dashed #c3c4c7;border-radius:8px;padding:14px 16px;'
					. 'margin:14px 0;color:#646970;font-size:13px;line-height:1.5">'
					. '<strong>%s</strong><br>%s</div>',
					esc_html( $marca ),
					esc_html( trim( substr( (string) $explica, strlen( 'EL BLOQUE:' ) ) ) )
				);
				continue;
			}

			$enlinea[ $marca ] = esc_html( $muestra[ $marca ] ?? trim( $marca, '{}' ) );
		}

		return [
			'asunto' => trim( str_replace( array_keys( $muestra ), array_values( $muestra ), $asunto ) ),
			// Con su logo y su botón: la vista previa que no enseña el botón
			// es una vista previa de otro correo.
			'html'   => self::envolver(
				(string) $ficha['titulo'],
				self::armar( $cuerpo, $enlinea, $bloques ),
				false,
				self::boton_de( $clave )
			),
		];
	}

	/**
	 * El marco común: logo, cabecera, contenido, botón y pie.
	 *
	 * El logo va en una banda BLANCA encima de la franja azul, y no dentro de
	 * ella, por una razón de bulto: el logo de la casa es azul. Puesto sobre
	 * la franja azul del título desaparece. La banda blanca es además lo que
	 * hace que el correo se reconozca de un vistazo en la bandeja.
	 *
	 * @param array{texto?:string,url?:string} $boton El botón del final, si lo lleva.
	 */
	public static function envolver( string $titulo, string $contenido, bool $con_atribucion = false, array $boton = [] ): string {
		$pie = $con_atribucion
			? '<p style="color:#6b7280;font-size:11px;line-height:1.5;margin:16px 0 0">'
				. 'Los datos de vulnerabilidades provienen de Wordfence Intelligence (Defiant, Inc.), '
				. 'usados bajo su licencia. Los identificadores CVE son de MITRE.</p>'
			: '';

		return sprintf(
			'<div style="background:#f6f7f7;padding:24px 12px;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif">'
			. '<div style="max-width:640px;margin:0 auto;background:#fff;border-radius:8px;overflow:hidden;border:1px solid #dcdcde">'
			. '<div style="padding:20px 26px 16px;background:#fff;text-align:left">'
			. '<img src="%s" alt="Solución Web" width="150" height="41" '
			. 'style="display:block;width:150px;height:auto;border:0;outline:none;text-decoration:none">'
			. '</div>'
			. '<div style="background:%s;padding:18px 26px">'
			. '<p style="margin:0;color:#fff;font-size:16px;font-weight:600">%s</p>'
			. '</div>'
			. '<div style="padding:26px;color:#1d2327;font-size:14px;line-height:1.6">%s%s</div>'
			. '<div style="padding:16px 26px;border-top:1px solid #f0f0f1;background:#fafafa">'
			. '<p style="margin:0;color:%s;font-size:12px">Este mensaje lo generó automáticamente el panel de mantenimiento. '
			. 'Queda registrado con su fecha y su destinatario.<br>%s</p>%s'
			. '</div></div></div>',
			esc_url( self::logo() ),
			self::AZUL,
			esc_html( $titulo ),
			$contenido,
			self::boton( (string) ( $boton['texto'] ?? '' ), (string) ( $boton['url'] ?? '' ) ),
			self::GRIS,
			esc_html( self::de_cuando() ),
			$pie
		);
	}

	/**
	 * De cuándo son los datos del correo.
	 *
	 * Un correo se lee a los tres días, o se reenvía a los dos meses. Si el
	 * cliente contesta "esto ya lo arreglamos", hay que poder decir de qué día
	 * era el inventario sin tener que entrar al panel a adivinarlo.
	 *
	 * No se nombra al hosting ni a Wordfence: este pie también lo lee el
	 * cliente, y ahí el proveedor no le dice nada.
	 */
	private static function de_cuando(): string {
		$cuando = SWPK_Frescura::corrida();

		return '' === $cuando
			? 'Los datos de este mensaje no tienen fecha de origen registrada.'
			: sprintf(
				'Los datos de este mensaje se obtuvieron el %s.',
				SWPK_Frescura::fmt( 'j \d\e F \d\e Y, g:i a', $cuando )
			);
	}

	/** Lunes a las 7, a cada asesora: sus sitios y el texto listo para reenviar. */
	public static function semanal_asesora( WP_User $persona, array $sitios ): string {
		$orden = [ SWPK_Riesgo::CRITICO => 0, SWPK_Riesgo::ALTO => 1, SWPK_Riesgo::MEDIO => 2, SWPK_Riesgo::BAJO => 3 ];
		usort(
			$sitios,
			static fn( $a, $b ) => ( $orden[ SWPK_Riesgo::nivel_del_sitio( $a->sitio_id ) ] ?? 9 )
				<=> ( $orden[ SWPK_Riesgo::nivel_del_sitio( $b->sitio_id ) ] ?? 9 )
		);

		$criticos = count(
			array_filter(
				$sitios,
				static fn( $s ) => in_array(
					SWPK_Riesgo::nivel_del_sitio( $s->sitio_id ),
					[ SWPK_Riesgo::CRITICO, SWPK_Riesgo::ALTO ],
					true
				)
			)
		);

		$bloque = '';

		foreach ( $sitios as $sitio ) {
			$hallazgos = SWPK_Riesgo::hallazgos( (string) $sitio->sitio_id );
			$nivel     = SWPK_Riesgo::nivel_del_sitio( (string) $sitio->sitio_id );
			$color     = SWPK_Riesgo::colores()[ $nivel ] ?? self::GRIS;
			$texto     = SWPK_Textos::para_asesora( $sitio, $hallazgos );

			$vulns = count( array_filter( $hallazgos, static fn( $h ) => 'actualizacion_pendiente' !== $h['motivo'] ) );

			// La insistencia: cuántas veces seguidas se avisa de este sitio y
			// qué se movió desde la vez anterior. Apuntar tiene efecto —sube el
			// contador—, así que va aquí, una sola vez por sitio y por correo.
			// Ver SWPK_Insistencia.
			$insiste = SWPK_Insistencia::frase( SWPK_Insistencia::apuntar( (string) $sitio->sitio_id, $hallazgos ) );

			$bloque .= sprintf(
				'<div style="border:1px solid #dcdcde;border-radius:6px;padding:16px 18px;margin:18px 0">'
				. '<p style="margin:0 0 4px;font-size:15px;font-weight:600">%s</p>'
				. '<p style="margin:0 0 12px;color:%s;font-size:13px"><strong>%s</strong> · %d con vulnerabilidad · %d por actualizar</p>'
				. '%s'
				. '<p style="margin:0 0 6px;font-size:13px;color:%s">Para copiar y mandarle al cliente:</p>'
				. '<div style="background:#f6f7f7;border-radius:4px;padding:14px;white-space:pre-wrap;font-size:13px;line-height:1.55">%s</div>'
				. '</div>',
				esc_html( $sitio->nombre_visible ),
				$color,
				esc_html( SWPK_Riesgo::nombres()[ $nivel ] ?? '' ),
				$vulns,
				count( $hallazgos ) - $vulns,
				'' !== $insiste
					? sprintf(
						'<p style="margin:0 0 12px;padding:10px 12px;background:#faf4df;border:1px solid #e3d3a0;border-radius:4px;color:%s;font-size:13px;line-height:1.5">%s</p>',
						self::MOSTAZA,
						esc_html( $insiste )
					)
					: '',
				self::GRIS,
				esc_html( $texto )
			);
		}

		return self::envolver(
			self::catalogo()['semanal']['titulo'],
			self::cuerpo(
				'semanal',
				[
					'{{asesora}}'  => esc_html( $persona->display_name ),
					'{{cuantos}}'  => '<strong>' . count( $sitios ) . '</strong>',
					'{{criticos}}' => $criticos
						? sprintf( ', y <strong style="color:%s">%d en estado alto o crítico</strong>', self::ROJO, $criticos )
						: '',
				],
				[ '{{sitios}}' => $bloque ]
			),
			true,
			self::boton_de( 'semanal' )
		);
	}

	/**
	 * Cómo está la cartera HOY. Va arriba del resumen diario.
	 *
	 * ─── Por qué un correo de «lo que cambió» no era suficiente ─────────────
	 *
	 * Porque son dos preguntas y el correo solo contestaba una.
	 *
	 *   «A él le llega ese correo y viene como que todo, pero no puede ver
	 *    exactamente: mira, este sitio dice que tiene esta vulnerabilidad,
	 *    este dice que se cayó por tal cosa.»
	 *
	 * El diario listaba los MOVIMIENTOS de las últimas 24 horas. Eso no es el
	 * estado: un sitio puede llevar doce días caído y tres semanas con una
	 * vulnerabilidad crítica sin generar un solo movimiento nuevo, y entonces
	 * el correo de hoy no lo nombra. Peor todavía, un día tranquilo salía
	 * «sin cambios en la cartera» con un sitio sin responder y doce en rojo.
	 *
	 * Eso es la regla 3 otra vez, en su forma más cara: no decir «está bien»
	 * cuando lo que se midió es otra cosa.
	 *
	 * Lo que cambió sigue estando, debajo. Primero qué pasa, después qué se
	 * movió.
	 *
	 * ─── Por qué no usa SWPK_Estado::calcular() ─────────────────────────────
	 *
	 * Porque esa función filtra por lo que puede ver el usuario de la sesión,
	 * y en el cron no hay usuario: devolvería los sitios sin asesora y cero
	 * caídos. Este correo va a soporte y tiene que ver la cartera entera.
	 */
	private static function estado_de_hoy(): string {
		$caidos   = [];
		$ausentes = 0;
		$ciegos   = 0;

		foreach ( SWPK_Monitor::estados() as $e ) {
			if ( 'ciego' === $e->estado ) {
				++$ciegos;
				continue;
			}

			if ( 'ausente' === $e->estado ) {
				++$ausentes;
				continue;
			}

			if ( 'abajo' !== $e->estado ) {
				continue;
			}

			$sitio    = SWPK_Inventario::obtener( (string) $e->sitio_id );
			$caidos[] = [
				'nombre' => (string) ( $sitio->nombre_visible ?? $e->sitio_id ),
				'url'    => (string) $e->url,
				'desde'  => (string) $e->desde,
				'motivo' => (string) $e->motivo,
			];
		}

		// Los sitios en rojo, con SU peor hallazgo nombrado. «12 sitios en
		// crítico» no sirve para nada; «este plugin, esta versión» sí.
		$rojos = [];
		$por_nivel = [];

		foreach ( SWPK_Inventario::todos() as $sitio ) {
			$id    = (string) $sitio->sitio_id;
			$nivel = SWPK_Riesgo::nivel_del_sitio( $id );

			$por_nivel[ $nivel ] = ( $por_nivel[ $nivel ] ?? 0 ) + 1;

			if ( ! in_array( $nivel, [ SWPK_Riesgo::CRITICO, SWPK_Riesgo::ALTO ], true ) ) {
				continue;
			}

			$hallazgos = SWPK_Riesgo::hallazgos( $id );
			$peor      = null;

			foreach ( $hallazgos as $h ) {
				if ( null === $peor || SWPK_Riesgo::peso( $h['nivel'] ) > SWPK_Riesgo::peso( $peor['nivel'] ) ) {
					$peor = $h;
				}
			}

			$rojos[] = [
				'nombre'  => (string) $sitio->nombre_visible,
				'nivel'   => $nivel,
				'cuantos' => count( $hallazgos ),
				'peor'    => $peor ? SWPK_Textos::limpiar_titulo( (string) $peor['titulo'] ) : '',
			];
		}

		usort(
			$rojos,
			static fn( $a, $b ) => [ SWPK_Riesgo::peso( $b['nivel'] ), $b['cuantos'] ]
				<=> [ SWPK_Riesgo::peso( $a['nivel'] ), $a['cuantos'] ]
		);

		$html = '<p style="margin:0 0 10px;font-weight:600;font-size:14px">Cómo está la cartera hoy</p>';

		// ── Lo que no responde, arriba del todo y en rojo ───────────────────
		if ( $caidos ) {
			$html .= sprintf(
				'<div style="background:#fcf0f1;border:1px solid #ecc7c9;border-radius:6px;padding:14px 16px;margin:0 0 16px">'
				. '<p style="margin:0 0 8px;font-weight:700;color:%s">%s sin responder ahora mismo</p>',
				self::ROJO,
				esc_html( SWPK_Vista::plural( count( $caidos ), 'sitio', 'sitios' ) )
			);

			foreach ( $caidos as $c ) {
				$html .= sprintf(
					'<p style="margin:0 0 6px;font-size:13px;line-height:1.5">'
					. '<strong>%s</strong> — %s<br>'
					. '<span style="color:%s">Desde el %s · %s</span></p>',
					esc_html( $c['nombre'] ),
					esc_html( $c['url'] ),
					self::GRIS,
					esc_html( SWPK_Frescura::fmt( 'j \d\e F, H:i', $c['desde'] ) ),
					esc_html( $c['motivo'] ?: 'No contesta' )
				);
			}

			$html .= '</div>';
		} else {
			$html .= sprintf(
				'<p style="background:#f0f6fc;border:1px solid #c9ddf2;border-radius:6px;padding:12px 14px;margin:0 0 16px;font-size:13px">'
				. '<strong>Ningún sitio sin responder ahora mismo.</strong>%s</p>',
				$ciegos
					? sprintf(
						' De %d el hosting no deja medirlo desde aquí, así que de esos no se sabe.',
						(int) $ciegos
					)
					: ''
			);
		}

		// ── Lo que está en rojo, con el porqué ──────────────────────────────
		if ( $rojos ) {
			$html .= sprintf(
				'<p style="margin:0 0 8px;font-weight:600;font-size:13px">%s en estado alto o crítico</p>',
				esc_html( SWPK_Vista::plural( count( $rojos ), 'sitio', 'sitios' ) )
			);

			$html .= '<table role="presentation" style="width:100%;border-collapse:collapse;margin:0 0 16px">';

			foreach ( array_slice( $rojos, 0, 15 ) as $r ) {
				$html .= sprintf(
					'<tr>'
					. '<td style="padding:7px 10px 7px 0;border-bottom:1px solid #f0f0f1;font-size:13px;'
					. 'font-weight:600;vertical-align:top">%s</td>'
					. '<td style="padding:7px 10px;border-bottom:1px solid #f0f0f1;font-size:12px;'
					. 'color:%s;white-space:nowrap;vertical-align:top">%s</td>'
					. '<td style="padding:7px 0;border-bottom:1px solid #f0f0f1;font-size:13px;vertical-align:top">%s</td>'
					. '</tr>',
					esc_html( $r['nombre'] ),
					SWPK_Riesgo::CRITICO === $r['nivel'] ? self::ROJO : '#b35309',
					esc_html( SWPK_Riesgo::nombres()[ $r['nivel'] ] ?? $r['nivel'] ),
					esc_html(
						'' !== $r['peor']
							? $r['peor'] . ( $r['cuantos'] > 1 ? sprintf( ' (y %d más)', $r['cuantos'] - 1 ) : '' )
							: sprintf( '%d pendientes', $r['cuantos'] )
					)
				);
			}

			$html .= '</table>';

			if ( count( $rojos ) > 15 ) {
				$html .= sprintf(
					'<p style="color:%s;font-size:13px;margin:-8px 0 16px">Y %d más en el panel.</p>',
					self::GRIS,
					count( $rojos ) - 15
				);
			}
		}

		// ── Y lo que el panel NO sabe, dicho como lo que es ─────────────────
		$avisos = [];

		if ( $ciegos ) {
			$avisos[] = sprintf(
				'De <strong>%d</strong> el hosting no deja que el panel los mire desde fuera, así que de esos no se sabe si responden ni se pueden revisar sus normas de seguridad.',
				(int) $ciegos
			);
		}

		if ( $ausentes ) {
			$avisos[] = sprintf(
				'<strong>%d</strong> ya no aparecen en el hosting. Si siguen sin aparecer, se dan de baja solos y sale su correo.',
				(int) $ausentes
			);
		}

		if ( ! empty( $por_nivel[ SWPK_Riesgo::SIN_DATOS ] ) ) {
			$avisos[] = sprintf(
				'De <strong>%d</strong> no hay inventario todavía, así que no se pueden clasificar.',
				(int) $por_nivel[ SWPK_Riesgo::SIN_DATOS ]
			);
		}

		if ( $avisos ) {
			$html .= sprintf(
				'<p style="background:#f6f7f7;border:1px solid #e6e7eb;border-radius:6px;padding:12px 14px;'
				. 'margin:0 0 6px;font-size:13px;line-height:1.5;color:%s">Lo que el panel no puede afirmar: %s</p>',
				self::GRIS,
				implode( ' ', $avisos )
			);
		}

		return $html;
	}

	/**
	 * Resumen diario a soporte: lo que cambió, sin adornos.
	 *
	 * ─── Por qué la tabla está ordenada por sitio y no por hora ─────────────
	 *
	 * «Esto que sea una tabla ordenada» (jefe, 25/11/2026, sobre el correo de
	 * las 158 filas). La tabla salía sin encabezados y ordenada por hora, que
	 * es el orden en que el panel las apunta y el único que no le sirve a
	 * nadie: el mismo sitio aparecía en la fila 3, en la 40 y en la 112, así
	 * que para saber qué le pasó a un sitio había que recorrer las 158.
	 *
	 * Ahora va por sitio en orden alfabético, y dentro de cada sitio lo grave
	 * antes que la rutina. Con eso, las cuatro líneas de un sitio quedan
	 * juntas y se lee de un vistazo.
	 *
	 * Y encima va el recuento por tipo, que contesta la pregunta con la que
	 * uno abre este correo —«¿esto son 158 actualizaciones de plugin o pasó
	 * algo?»— sin leer una sola fila.
	 *
	 * @param array<int,object> $eventos
	 */
	public static function diario_soporte( array $eventos ): string {
		$etiquetas = SWPK_Eventos::etiquetas();

		// El nombre del sitio de cada evento, una sola vez por sitio: dentro
		// del bucle de ordenación serían cientos de consultas repetidas.
		$nombre_de = [];
		foreach ( $eventos as $e ) {
			$id = (string) $e->sitio_id;
			if ( ! isset( $nombre_de[ $id ] ) ) {
				$sitio             = '' !== $id ? SWPK_Inventario::obtener( $id ) : null;
				$nombre_de[ $id ] = (string) ( $sitio->nombre_visible ?? '—' );
			}
		}

		// Lo que no es rutina, primero dentro de cada sitio. Una vulnerabilidad
		// detectada y una actualización de plugin no pesan lo mismo, y en una
		// tabla larga lo que va abajo no se lee.
		$peso = static fn( $e ) => SWPK_Eventos::es_rutinario( (string) $e->tipo ) ? 1 : 0;

		$ordenados = $eventos;
		usort(
			$ordenados,
			static function ( $a, $b ) use ( $nombre_de, $peso ) {
				$na = $nombre_de[ (string) $a->sitio_id ] ?? '';
				$nb = $nombre_de[ (string) $b->sitio_id ] ?? '';

				$cmp = strcasecmp( $na, $nb );
				if ( 0 !== $cmp ) {
					return $cmp;
				}

				$cmp = $peso( $a ) <=> $peso( $b );
				if ( 0 !== $cmp ) {
					return $cmp;
				}

				return strcmp( (string) $b->ocurrio_en, (string) $a->ocurrio_en );
			}
		);

		// "Cero cambios" es el mensaje más peligroso que este correo puede dar.
		// Leído rápido significa "todo tranquilo", y puede significar "no
		// miramos". Si la corrida no trajo datos, eso va arriba y en rojo, antes
		// que cualquier número, porque el número es lo que engaña.
		$c = '';
		if ( ! SWPK_Frescura::corrida_al_dia() || SWPK_Frescura::corrida_fallo() ) {
			$c .= sprintf(
				'<p style="background:#fcf0f1;border:1px solid #ecc7c9;border-radius:6px;padding:12px 14px;margin:0 0 18px">'
				. '<strong>Ojo: este resumen no es de hoy.</strong><br>'
				. 'El último dato bueno entró el %s. Lo de abajo es ese inventario, no el de ahora, '
				. 'así que "cero cambios" aquí no quiere decir que no haya pasado nada: quiere '
				. 'decir que el panel no pudo mirar. Hay que revisar la tarea programada.</p>',
				'' === SWPK_Frescura::corrida()
					? 'nunca'
					: esc_html( SWPK_Frescura::fmt( 'j \d\e F, g:i a', SWPK_Frescura::corrida() ) . ', ' . SWPK_Frescura::hace( SWPK_Frescura::corrida() ) )
			);
		}

		// ── El recuento por tipo ────────────────────────────────────────────
		$por_tipo = [];
		foreach ( $ordenados as $e ) {
			$t              = (string) $e->tipo;
			$por_tipo[ $t ] = ( $por_tipo[ $t ] ?? 0 ) + 1;
		}
		// Lo que no es rutina, arriba del recuento, aunque sea uno y las
		// actualizaciones sean ciento cincuenta. La pregunta que contesta
		// este bloque es «¿pasó algo?», y una vulnerabilidad detectada
		// escondida debajo de «se actualizó: 152» no la contesta.
		uksort(
			$por_tipo,
			static function ( $a, $b ) use ( $por_tipo ) {
				$ra = SWPK_Eventos::es_rutinario( (string) $a ) ? 1 : 0;
				$rb = SWPK_Eventos::es_rutinario( (string) $b ) ? 1 : 0;

				return $ra === $rb
					? $por_tipo[ $b ] <=> $por_tipo[ $a ]
					: $ra <=> $rb;
			}
		);

		$resumen = '<p style="margin:0 0 8px;font-weight:600;font-size:14px">En resumen</p>'
			. '<table role="presentation" style="width:100%;border-collapse:collapse;margin:0 0 24px">';

		foreach ( $por_tipo as $tipo => $cuantos ) {
			$resumen .= sprintf(
				'<tr>'
				. '<td style="padding:7px 0;border-bottom:1px solid #e6e7eb;font-size:13px">%s</td>'
				. '<td style="padding:7px 0;border-bottom:1px solid #e6e7eb;text-align:right;'
				. 'font-size:13px;font-weight:700;white-space:nowrap">%d</td>'
				. '</tr>',
				esc_html( $etiquetas[ (string) $tipo ] ?? (string) $tipo ),
				(int) $cuantos
			);
		}

		$resumen .= '</table>';

		// ── El detalle, sitio por sitio ─────────────────────────────────────
		//
		// El nombre del sitio se repite en todas sus filas a propósito, en vez
		// de escribirlo solo en la primera. Escribirlo una vez queda más
		// limpio en pantalla y se rompe en el correo: basta con que alguien
		// reenvíe un recorte, o que el cliente de correo parta la tabla, para
		// que media lista quede sin dueño.
		$filas  = '';
		$cebra  = false;
		$previo = null;

		foreach ( array_slice( $ordenados, 0, 60 ) as $e ) {
			$id = (string) $e->sitio_id;

			// La cebra cambia por SITIO y no por fila: así se ve dónde empieza
			// y dónde acaba cada sitio, que es justo lo que se pedía.
			if ( null !== $previo && $previo !== $id ) {
				$cebra = ! $cebra;
			}
			$previo = $id;

			$color = match ( $e->severidad ) {
				'critico' => self::ROJO,
				'alto'    => self::ROJO,
				'ok'      => '#0b5ed7',
				default   => self::GRIS,
			};

			$fondo = $cebra ? '#fafafa' : '#ffffff';
			$borde = '1px solid #e6e7eb';

			$filas .= sprintf(
				'<tr style="background:%s">'
				. '<td style="padding:9px 10px 9px 0;border-bottom:%s;font-size:13px;'
				. 'font-weight:600;vertical-align:top">%s</td>'
				. '<td style="padding:9px 10px;border-bottom:%s;color:%s;font-size:12px;'
				. 'white-space:nowrap;vertical-align:top">%s</td>'
				. '<td style="padding:9px 10px;border-bottom:%s;font-size:13px;vertical-align:top">%s</td>'
				. '<td style="padding:9px 0 9px 10px;border-bottom:%s;color:%s;font-size:12px;'
				. 'text-align:right;white-space:nowrap;vertical-align:top">%s</td>'
				. '</tr>',
				$fondo,
				$borde,
				esc_html( $nombre_de[ $id ] ?? '—' ),
				$borde,
				$color,
				esc_html( $etiquetas[ $e->tipo ] ?? $e->tipo ),
				$borde,
				esc_html( $e->titulo ),
				$borde,
				self::GRIS,
				esc_html( SWPK_Frescura::fmt( 'j/n H:i', (string) $e->ocurrio_en ) )
			);
		}

		if ( ! $ordenados ) {
			// Un día sin movimientos no es un día sin correo: el bloque de
			// arriba sigue diciendo cómo está la cartera, que es lo que de
			// verdad se quiere saber. Lo que no puede pasar es que el correo
			// no salga, porque entonces no hay forma de distinguir «no pasó
			// nada» de «el panel no corrió».
			$resumen = '';
			$tabla   = '<div style="background:#f0f6fc;border:1px solid #c9ddf2;border-radius:6px;'
				. 'padding:14px 16px;margin:0"><strong>Ningún cambio en las últimas 24 horas.</strong><br>'
				. 'Nada se actualizó, nada se instaló y nadie cambió de rol en ningún sitio de la '
				. 'cartera. Lo de arriba sí es de ahora mismo.</div>';

			return self::envolver(
				self::catalogo()['diario']['titulo'],
				$c . self::cuerpo(
					'diario',
					[ '{{cuantos}}' => '<strong>0</strong>' ],
					[ '{{estado}}' => self::estado_de_hoy(), '{{cambios}}' => $tabla ]
				),
				false,
				self::boton_de( 'diario' )
			);
		}

		$tabla = '<p style="margin:0 0 8px;font-weight:600;font-size:14px">Sitio por sitio</p>'
			. '<table role="presentation" style="width:100%;border-collapse:collapse">'
			. '<thead><tr>'
			. sprintf(
				'<th style="padding:0 10px 7px 0;border-bottom:2px solid #c3c4c7;text-align:left;'
				. 'color:%1$s;font-size:11px;font-weight:700;text-transform:uppercase;'
				. 'letter-spacing:.04em">Sitio</th>'
				. '<th style="padding:0 10px 7px;border-bottom:2px solid #c3c4c7;text-align:left;'
				. 'color:%1$s;font-size:11px;font-weight:700;text-transform:uppercase;'
				. 'letter-spacing:.04em">Qué pasó</th>'
				. '<th style="padding:0 10px 7px;border-bottom:2px solid #c3c4c7;text-align:left;'
				. 'color:%1$s;font-size:11px;font-weight:700;text-transform:uppercase;'
				. 'letter-spacing:.04em">Detalle</th>'
				. '<th style="padding:0 0 7px 10px;border-bottom:2px solid #c3c4c7;text-align:right;'
				. 'color:%1$s;font-size:11px;font-weight:700;text-transform:uppercase;'
				. 'letter-spacing:.04em">Cuándo</th>',
				self::GRIS
			)
			. '</tr></thead><tbody>' . $filas . '</tbody></table>';

		if ( count( $ordenados ) > 60 ) {
			$tabla .= sprintf(
				'<p style="color:%s;font-size:13px;margin:14px 0 0">Y %d más. Están todos en el panel, '
				. 'con el botón de abajo.</p>',
				self::GRIS,
				count( $ordenados ) - 60
			);
		}

		// El aviso de «este resumen no es de hoy» va antes de la plantilla y
		// fuera de ella: no es prosa que se pueda reescribir ni quitar desde el
		// panel. Es la advertencia que impide leer «cero cambios» como «todo
		// tranquilo» cuando en realidad el panel no pudo mirar.
		return self::envolver(
			self::catalogo()['diario']['titulo'],
			$c . self::cuerpo(
				'diario',
				[ '{{cuantos}}' => '<strong>' . count( $eventos ) . '</strong>' ],
				[
					'{{estado}}'  => self::estado_de_hoy(),
					'{{cambios}}' => $resumen . $tabla,
				]
			),
			false,
			self::boton_de( 'diario' )
		);
	}

	/**
	 * El aviso de que un sitio se cayó.
	 *
	 * Lo arma aquí y no en el monitor por lo mismo que los otros cinco: el
	 * monitor sabe sondear, no sabe redactar. Y porque `obtener()` devuelve
	 * solo el par editable —asunto y cuerpo—, no el título del marco; pedirle
	 * el título desde fuera devuelve null y revienta justo cuando un sitio se
	 * cae, que es el peor momento posible para un error.
	 *
	 * @param array<string,string> $marcas ya escapadas
	 */
	public static function caida( array $marcas ): string {
		return self::envolver(
			self::catalogo()['caida']['titulo'],
			self::cuerpo( 'caida', $marcas ),
			false,
			self::boton_de( 'caida' )
		);
	}

	/** El aviso de que un sitio volvió. */
	public static function de_baja( array $marcas ): string {
		return self::envolver(
			self::catalogo()['de_baja']['titulo'],
			self::cuerpo( 'de_baja', $marcas ),
			false,
			self::boton_de( 'de_baja' )
		);
	}

	/** El recordatorio diario de que un sitio sigue sin responder. */
	public static function sigue_caido( array $marcas ): string {
		return self::envolver(
			self::catalogo()['sigue_caido']['titulo'],
			self::cuerpo( 'sigue_caido', $marcas ),
			false,
			self::boton_de( 'sigue_caido' )
		);
	}

	/** El aviso de que un sitio lleva días sin mandar su reporte. */
	public static function agente_callado( array $marcas ): string {
		return self::envolver(
			self::catalogo()['agente_callado']['titulo'],
			self::cuerpo( 'agente_callado', $marcas ),
			false,
			self::boton_de( 'agente_callado' )
		);
	}

	public static function repuesto( array $marcas ): string {
		return self::envolver(
			self::catalogo()['repuesto']['titulo'],
			self::cuerpo( 'repuesto', $marcas ),
			false,
			self::boton_de( 'repuesto' )
		);
	}

	/**
	 * El resumen semanal de lo que se envió.
	 *
	 * ─── De dónde sale ──────────────────────────────────────────────────
	 *
	 * Del audio del 18/11/2026, y es textual:
	 *
	 *   «me gustaría que se mande UN RESUMEN DE LO QUE SE ENVIÓ. Si algo se
	 *    envía a diario, al final de semana, UN DOMINGO, se mande el resumen de
	 *    lo que se envió: "te damos tu resumen semanal, se enviaron tres
	 *    correos notificando"»                                        [05:12]
	 *
	 *   «en ese resumen se le puede indicar: DE ESTO NO SE OBTUVO NINGÚN
	 *    MOVIMIENTO, NO HA PASADO NADA, que se le ha notificado a todos»
	 *                                                                 [05:35]
	 *
	 * Esa segunda frase es la que decide el diseño: el correo sale IGUAL
	 * cuando no se envió nada. Un domingo sin correo no significa «no pasó
	 * nada», significa «no sé si el panel corrió». La diferencia es justo lo
	 * que él quiere poder demostrar.
	 *
	 * @param array<int,object> $filas lo que salió en la semana
	 */
	public static function resumen_semanal( array $filas, string $desde, string $hasta, array $eventos = [] ): string {
		$nombres = SWPK_Bitacora::tipos();

		// Agrupar por tipo para el encabezado, y listar el detalle debajo.
		$por_tipo = [];

		foreach ( $filas as $f ) {
			$t = (string) $f->tipo;
			$por_tipo[ $t ] = ( $por_tipo[ $t ] ?? 0 ) + 1;
		}

		$html = self::movimiento_de_la_semana( $eventos );

		if ( ! $filas ) {
			$html .= '<p style="margin:0 0 10px;font-weight:600">Lo que avisamos</p>'
				. '<div style="background:#f0f6fc;border:1px solid #c9ddf2;border-radius:6px;padding:14px 16px;margin:0 0 18px">'
				. '<strong>Esta semana no salió ningún aviso.</strong><br>'
				. 'No es que el panel no haya corrido: corrió, revisó la cartera y no encontró nada que ameritara '
				. 'avisarle a nadie. Este correo sale igual para que quede constancia de eso.'
				. '</div>';

			return self::envolver(
				self::catalogo()['resumen']['titulo'],
				$html,
				false,
				self::boton_de( 'resumen' )
			);
		}

		$html .= '<p style="margin:0 0 10px;font-weight:600">Lo que avisamos</p>';
		$html .= '<table role="presentation" style="width:100%;border-collapse:collapse;margin:0 0 20px">';

		foreach ( $por_tipo as $tipo => $cuantos ) {
			$html .= sprintf(
				'<tr><td style="padding:7px 0;border-bottom:1px solid #e6e7eb">%s</td>'
				. '<td style="padding:7px 0;border-bottom:1px solid #e6e7eb;text-align:right;font-weight:600">%d</td></tr>',
				esc_html( $nombres[ $tipo ] ?? $tipo ),
				(int) $cuantos
			);
		}

		$html .= '</table>';

		$html .= '<p style="margin:0 0 10px;font-weight:600">Uno por uno</p>';
		$html .= '<table role="presentation" style="width:100%;border-collapse:collapse;font-size:13px">';

		foreach ( array_slice( $filas, 0, 60 ) as $f ) {
			$html .= sprintf(
				'<tr><td style="padding:6px 10px 6px 0;border-bottom:1px solid #f0f0f1;white-space:nowrap;color:#50575e">%s</td>'
				. '<td style="padding:6px 0;border-bottom:1px solid #f0f0f1">%s</td>'
				. '<td style="padding:6px 0 6px 10px;border-bottom:1px solid #f0f0f1;text-align:right;color:%s">%s</td></tr>',
				esc_html( wp_date( 'j/n H:i', strtotime( (string) $f->enviado_en ) ) ),
				esc_html( (string) $f->asunto ),
				'enviado' === $f->resultado ? '#0b5ed7' : '#9e0b10',
				esc_html( (string) $f->resultado )
			);
		}

		$html .= '</table>';

		if ( count( $filas ) > 60 ) {
			$html .= sprintf( '<p style="color:#50575e;font-size:13px">Y %d más, en el historial de envíos del panel.</p>', count( $filas ) - 60 );
		}

		return self::envolver(
			self::catalogo()['resumen']['titulo'],
			$html,
			false,
			self::boton_de( 'resumen' )
		);
	}

	/**
	 * Qué pasó en los sitios durante la semana.
	 *
	 * ─── Por qué esto va aparte de «lo que avisamos» ────────────────────
	 *
	 * Porque son dos preguntas distintas y el jefe pidió las dos, contándolas:
	 *
	 *   «me gustaría que se mande un resumen de LO QUE SE ENVIÓ (...) Eso sería
	 *    UNA. DOS: necesitaría de que aparte de ese resumen semanal de todo eso,
	 *    DE LO QUE SE VIO EN LA SEMANA, ver que se le mande un resumen; en ese
	 *    resumen se le puede indicar: de esto no se obtuvo ningún movimiento,
	 *    no ha pasado nada»                                        [05:12-05:50]
	 *
	 * «Lo que se envió» contesta ¿avisamos?, y sirve para cubrirse. «Lo que se
	 * vio» contesta ¿qué pasó?, y sirve para que alguien se dé cuenta de algo
	 * antes de que llame el cliente. Un sitio puede haber cambiado de versión
	 * cinco veces sin que eso generara un solo correo.
	 *
	 * Van en el MISMO correo y no en dos: dos correos el mismo domingo a la
	 * misma hora se leen como ruido, y él lo contó como una sola cosa de dos
	 * mitades.
	 *
	 * @param array<int,object> $eventos
	 */
	private static function movimiento_de_la_semana( array $eventos ): string {
		$html = '<p style="margin:0 0 10px;font-weight:600">Lo que pasó en los sitios</p>';

		if ( ! $eventos ) {
			return $html
				. '<div style="background:#f0f6fc;border:1px solid #c9ddf2;border-radius:6px;padding:14px 16px;margin:0 0 22px">'
				. '<strong>Ningún movimiento esta semana.</strong><br>'
				. 'Nada se actualizó, nada se instaló, nada se cayó y nadie cambió de rol en ningún sitio '
				. 'de la cartera.'
				. '</div>';
		}

		$etiquetas = SWPK_Eventos::etiquetas();

		$por_tipo = [];
		$sitios   = [];
		$graves   = [];

		// Llegan ya sin repetidos: lo hace SWPK_Eventos::sin_repetidos() antes
		// de entrar, para que el asunto del correo cuente lo mismo que el
		// cuerpo. Si se hiciera aquí, el asunto diría 302 y la lista 171.
		foreach ( $eventos as $e ) {
			$tipo = (string) $e->tipo;

			$por_tipo[ $tipo ] = ( $por_tipo[ $tipo ] ?? 0 ) + 1;

			if ( '' !== (string) $e->sitio_id ) {
				$sitios[ (string) $e->sitio_id ] = true;
			}

			// Lo que no es rutina se nombra uno por uno: ahí está lo que hay
			// que mirar, y perderlo entre doscientas actualizaciones de plugin
			// sería exactamente lo contrario de lo que se pidió.
			if ( ! SWPK_Eventos::es_rutinario( $tipo ) ) {
				$graves[] = $e;
			}
		}

		arsort( $por_tipo );

		$html .= sprintf(
			'<p style="margin:0 0 12px;color:#50575e">%s en %s.</p>',
			esc_html( SWPK_Vista::plural( (int) array_sum( $por_tipo ), 'movimiento', 'movimientos' ) ),
			esc_html( SWPK_Vista::plural( count( $sitios ), 'sitio', 'sitios' ) )
		);

		$html .= '<table role="presentation" style="width:100%;border-collapse:collapse;margin:0 0 18px">';

		foreach ( $por_tipo as $tipo => $cuantos ) {
			$html .= sprintf(
				'<tr><td style="padding:7px 0;border-bottom:1px solid #e6e7eb">%s</td>'
				. '<td style="padding:7px 0;border-bottom:1px solid #e6e7eb;text-align:right;font-weight:600">%d</td></tr>',
				esc_html( $etiquetas[ $tipo ] ?? $tipo ),
				(int) $cuantos
			);
		}

		$html .= '</table>';

		if ( $graves ) {
			$html .= '<p style="margin:0 0 8px;font-weight:600;color:#9e0b10">Lo que no es rutina</p>';
			$html .= '<table role="presentation" style="width:100%;border-collapse:collapse;font-size:13px;margin:0 0 22px">';

			foreach ( array_slice( $graves, 0, 25 ) as $e ) {
				$sitio = '' !== (string) $e->sitio_id ? SWPK_Inventario::obtener( (string) $e->sitio_id ) : null;

				$html .= sprintf(
					'<tr><td style="padding:6px 10px 6px 0;border-bottom:1px solid #f0f0f1;white-space:nowrap;color:#50575e">%s</td>'
					. '<td style="padding:6px 10px 6px 0;border-bottom:1px solid #f0f0f1">%s</td>'
					. '<td style="padding:6px 0;border-bottom:1px solid #f0f0f1">%s</td></tr>',
					esc_html( wp_date( 'j/n', strtotime( (string) $e->ocurrio_en ) ) ),
					esc_html( (string) ( $sitio->nombre_visible ?? '—' ) ),
					esc_html( (string) $e->titulo )
				);
			}

			$html .= '</table>';

			if ( count( $graves ) > 25 ) {
				$html .= sprintf( '<p style="color:#50575e;font-size:13px;margin:-14px 0 22px">Y %d más, en Movimientos.</p>', count( $graves ) - 25 );
			}
		}

		return $html;
	}

	/**
	 * El resumen mensual al cliente.
	 *
	 *   "Este mes tu sitio se actualizó siete veces, en estos plugins. Tu sitio
	 *    se mantiene seguro." — 05:37
	 *
	 * Ni una palabra técnica. El cliente no tiene por qué saber qué es un CVE.
	 */
	public static function mensual_cliente( object $sitio, int $veces, string $desde, string $hasta ): string {
		global $wpdb;

		$modulos = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT DISTINCT titulo FROM ' . SWPK_Tablas::tabla( 'eventos' )
				. ' WHERE sitio_id = %s AND tipo = %s AND ocurrio_en BETWEEN %s AND %s LIMIT 25',
				$sitio->sitio_id,
				SWPK_Eventos::ACTUALIZADO,
				$desde,
				$hasta
			)
		) ?: [];

		$lista = '';
		foreach ( $modulos as $m ) {
			// Los títulos de evento vienen como "X pasó de 1.0 a 1.1"; al
			// cliente le basta el nombre.
			$nombre = preg_replace( '/ pas[oó] de .*/u', '', (string) $m ) ?? (string) $m;
			$lista .= '<li style="margin-bottom:4px">' . esc_html( SWPK_Textos::limpiar_titulo( $nombre ) ) . '</li>';
		}

		// El mes, con la fecha tal cual y SIN pasarla por strtotime().
		//
		// `$desde` es «2026-10-01 00:00:00» en el reloj de Guatemala. strtotime()
		// lo leía como UTC —WordPress le fija UTC a PHP— y al escribirlo de
		// vuelta en hora local le quitaba seis horas: el reporte de octubre
		// salía titulado «septiembre». Un reporte mensual con el mes equivocado
		// no hay forma de defenderlo delante del cliente.
		$mes = SWPK_Frescura::fmt( 'F \d\e Y', $desde );

		$recuadro = sprintf(
			'<p style="background:#f0f6fc;border:1px solid #c9ddf2;border-radius:6px;padding:14px 16px;margin:18px 0">'
			. 'Durante el mes su sitio se actualizó <strong>%d %s</strong>.</p>',
			$veces,
			1 === $veces ? 'vez' : 'veces'
		);

		$al_dia = $lista
			? '<p>Estos son los componentes que se mantuvieron al día:</p>'
				. '<ul style="margin:0 0 18px;padding-left:20px">' . $lista . '</ul>'
			: '';

		return self::envolver(
			self::catalogo()['mensual']['titulo'],
			self::cuerpo(
				'mensual',
				[
					'{{sitio}}' => '<strong>' . esc_html( $sitio->nombre_visible ) . '</strong>',
					'{{mes}}'   => esc_html( $mes ),
				],
				[
					'{{veces}}'       => $recuadro,
					'{{actualizado}}' => $al_dia,
				]
			)
		);
	}
}
