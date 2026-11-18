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
	private const ROJO  = '#b32d2e';
	private const GRIS  = '#646970';

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
	 * Son tres porque tres son los correos que el panel manda hoy. El ejemplo
	 * que vio el jefe enseñaba cinco —también uno del código de verificación en
	 * dos pasos y otro de sitio caído—, pero esos dos correos no existen: el
	 * panel no los envía. Poner su plantilla aquí sería dejar dos cajas de
	 * texto que no cambian nada.
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
					'{{sitios}}'   => 'EL BLOQUE: un recuadro por sitio, con el texto listo para copiarle al cliente.',
				],
			],
			'diario'  => [
				'nombre' => 'Diario a soporte',
				'quien'  => 'A la dirección de soporte, todos los días: lo que cambió en 24 horas.',
				'titulo' => 'Resumen del día',
				'asunto' => 'Resumen del día: {{cuantos}} cambios en la cartera',
				'cuerpo' => "Esto cambió en las últimas 24 horas ({{cuantos}} en total):\n\n"
					. '{{cambios}}',
				'marcas' => [
					'{{cuantos}}' => 'Cuántos cambios hubo',
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
					. 'la Bitácora, con el contenido completo de cada correo y la fecha exacta en que salió.',
				'marcas' => [
					'{{cuantos}}'    => 'Cuántos avisos salieron en la semana',
					'{{movimientos}}' => 'Cuántos movimientos hubo en los sitios',
					'{{desde}}'      => 'El primer día de la semana',
					'{{hasta}}'      => 'El último día de la semana',
					'{{movimiento}}' => 'EL BLOQUE: qué pasó en los sitios durante la semana.',
					'{{tabla}}'      => 'EL BLOQUE: el desglose de lo que se avisó, y cada aviso uno por uno.',
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
		$plantilla = self::obtener( $clave )['cuerpo'];
		$parrafos  = preg_split( '/\R\s*\R/u', trim( $plantilla ) ) ?: [];
		$html      = '';

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

	/** El marco común: cabecera, contenido y pie. */
	public static function envolver( string $titulo, string $contenido, bool $con_atribucion = false ): string {
		$pie = $con_atribucion
			? '<p style="color:#8c8f94;font-size:11px;line-height:1.5;margin:16px 0 0">'
				. 'Los datos de vulnerabilidades provienen de Wordfence Intelligence (Defiant, Inc.), '
				. 'usados bajo su licencia. Los identificadores CVE son de MITRE.</p>'
			: '';

		return sprintf(
			'<div style="background:#f6f7f7;padding:24px 12px;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif">'
			. '<div style="max-width:640px;margin:0 auto;background:#fff;border-radius:8px;overflow:hidden;border:1px solid #dcdcde">'
			. '<div style="background:%s;padding:18px 26px">'
			. '<p style="margin:0;color:#fff;font-size:16px;font-weight:600">%s</p>'
			. '</div>'
			. '<div style="padding:26px;color:#1d2327;font-size:14px;line-height:1.6">%s</div>'
			. '<div style="padding:16px 26px;border-top:1px solid #f0f0f1;background:#fafafa">'
			. '<p style="margin:0;color:%s;font-size:12px">Este mensaje lo generó automáticamente el panel de mantenimiento. '
			. 'Queda registrado con su fecha y su destinatario.<br>%s</p>%s'
			. '</div></div></div>',
			self::AZUL,
			esc_html( $titulo ),
			$contenido,
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

			$bloque .= sprintf(
				'<div style="border:1px solid #dcdcde;border-left:4px solid %s;border-radius:6px;padding:16px 18px;margin:18px 0">'
				. '<p style="margin:0 0 4px;font-size:15px;font-weight:600">%s</p>'
				. '<p style="margin:0 0 12px;color:%s;font-size:13px">%s · %d con vulnerabilidad · %d por actualizar</p>'
				. '<p style="margin:0 0 6px;font-size:13px;color:%s">Para copiar y mandarle al cliente:</p>'
				. '<div style="background:#f6f7f7;border-radius:4px;padding:14px;white-space:pre-wrap;font-size:13px;line-height:1.55">%s</div>'
				. '</div>',
				$color,
				esc_html( $sitio->nombre_visible ),
				self::GRIS,
				esc_html( SWPK_Riesgo::nombres()[ $nivel ] ?? '' ),
				$vulns,
				count( $hallazgos ) - $vulns,
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
			true
		);
	}

	/** Resumen diario a soporte: lo que cambió, sin adornos. */
	public static function diario_soporte( array $eventos ): string {
		$etiquetas = SWPK_Eventos::etiquetas();

		$filas = '';
		foreach ( array_slice( $eventos, 0, 60 ) as $e ) {
			$sitio = SWPK_Inventario::obtener( (string) $e->sitio_id );
			$color = match ( $e->severidad ) {
				'critico' => self::ROJO,
				'alto'    => '#d63638',
				'ok'      => '#008a20',
				default   => self::GRIS,
			};

			$filas .= sprintf(
				'<tr>'
				. '<td style="padding:7px 10px;border-bottom:1px solid #f0f0f1;color:%s;font-size:12px;white-space:nowrap">%s</td>'
				. '<td style="padding:7px 10px;border-bottom:1px solid #f0f0f1;font-size:12px">%s</td>'
				. '<td style="padding:7px 10px;border-bottom:1px solid #f0f0f1;font-size:13px">%s</td>'
				. '</tr>',
				$color,
				esc_html( $etiquetas[ $e->tipo ] ?? $e->tipo ),
				esc_html( $sitio->nombre_visible ?? '—' ),
				esc_html( $e->titulo )
			);
		}

		// "Cero cambios" es el mensaje más peligroso que este correo puede dar.
		// Leído rápido significa "todo tranquilo", y puede significar "no
		// miramos". Si la corrida no trajo datos, eso va arriba y en rojo, antes
		// que cualquier número, porque el número es lo que engaña.
		$c = '';
		if ( ! SWPK_Frescura::corrida_al_dia() || SWPK_Frescura::corrida_fallo() ) {
			$c .= sprintf(
				'<p style="background:#fcf0f1;border-left:4px solid %s;padding:12px 14px;margin:0 0 18px">'
				. '<strong>Ojo: este resumen no es de hoy.</strong><br>'
				. 'El último dato bueno entró el %s. Lo de abajo es ese inventario, no el de ahora, '
				. 'así que "cero cambios" aquí no quiere decir que no haya pasado nada: quiere '
				. 'decir que el panel no pudo mirar. Hay que revisar la tarea programada.</p>',
				self::ROJO,
				'' === SWPK_Frescura::corrida()
					? 'nunca'
					: esc_html( SWPK_Frescura::fmt( 'j \d\e F, g:i a', SWPK_Frescura::corrida() ) . ', ' . SWPK_Frescura::hace( SWPK_Frescura::corrida() ) )
			);
		}

		$tabla = '<table style="width:100%;border-collapse:collapse">' . $filas . '</table>';

		if ( count( $eventos ) > 60 ) {
			$tabla .= sprintf( '<p style="color:%s">Y %d más, en el panel.</p>', self::GRIS, count( $eventos ) - 60 );
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
				[ '{{cambios}}' => $tabla ]
			)
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
				. '<div style="background:#f0f6fc;border-left:4px solid #2271b1;padding:14px 16px;margin:0 0 18px">'
				. '<strong>Esta semana no salió ningún aviso.</strong><br>'
				. 'No es que el panel no haya corrido: corrió, revisó la cartera y no encontró nada que ameritara '
				. 'avisarle a nadie. Este correo sale igual para que quede constancia de eso.'
				. '</div>';

			return self::envolver( self::catalogo()['resumen']['titulo'], $html );
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
				'enviado' === $f->resultado ? '#008a20' : '#d63638',
				esc_html( (string) $f->resultado )
			);
		}

		$html .= '</table>';

		if ( count( $filas ) > 60 ) {
			$html .= sprintf( '<p style="color:#50575e;font-size:13px">Y %d más, en la Bitácora.</p>', count( $filas ) - 60 );
		}

		return self::envolver( self::catalogo()['resumen']['titulo'], $html );
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
				. '<div style="background:#f0f6fc;border-left:4px solid #2271b1;padding:14px 16px;margin:0 0 22px">'
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
			$html .= '<p style="margin:0 0 8px;font-weight:600;color:#d63638">Lo que no es rutina</p>';
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
			'<p style="background:#f0f6fc;border-left:4px solid %s;padding:14px 16px;margin:18px 0">'
			. 'Durante el mes su sitio se actualizó <strong>%d %s</strong>.</p>',
			self::AZUL,
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
