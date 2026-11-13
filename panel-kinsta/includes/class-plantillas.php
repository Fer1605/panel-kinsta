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

		$c  = sprintf( '<p>Hola %s,</p>', esc_html( $persona->display_name ) );
		$c .= sprintf(
			'<p>De los sitios que atendés, <strong>%d</strong> tienen algo pendiente esta semana%s.</p>',
			count( $sitios ),
			$criticos ? sprintf( ', y <strong style="color:%s">%d en estado alto o crítico</strong>', self::ROJO, $criticos ) : ''
		);

		foreach ( $sitios as $sitio ) {
			$hallazgos = SWPK_Riesgo::hallazgos( (string) $sitio->sitio_id );
			$nivel     = SWPK_Riesgo::nivel_del_sitio( (string) $sitio->sitio_id );
			$color     = SWPK_Riesgo::colores()[ $nivel ] ?? self::GRIS;
			$texto     = SWPK_Textos::para_asesora( $sitio, $hallazgos );

			$vulns = count( array_filter( $hallazgos, static fn( $h ) => 'actualizacion_pendiente' !== $h['motivo'] ) );

			$c .= sprintf(
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

		$c .= sprintf(
			'<p style="color:%s">Si querés que nos encarguemos nosotros de las actualizaciones, avisanos y lo coordinamos.</p>',
			self::GRIS
		);

		return self::envolver( 'Tus sitios esta semana', $c, true );
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

		$c .= sprintf( '<p>Esto cambió en las últimas 24 horas (%d en total):</p>', count( $eventos ) );
		$c .= '<table style="width:100%;border-collapse:collapse">' . $filas . '</table>';

		if ( count( $eventos ) > 60 ) {
			$c .= sprintf( '<p style="color:%s">Y %d más, en el panel.</p>', self::GRIS, count( $eventos ) - 60 );
		}

		return self::envolver( 'Resumen del día', $c );
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

		$mes = SWPK_Frescura::fmt( 'F \d\e Y', strtotime( $desde ) );

		$c  = '<p>Hola,</p>';
		$c .= sprintf(
			'<p>Le escribimos con el reporte de mantenimiento de <strong>%s</strong> correspondiente a %s.</p>',
			esc_html( $sitio->nombre_visible ),
			esc_html( $mes )
		);
		$c .= sprintf(
			'<p style="background:#f0f6fc;border-left:4px solid %s;padding:14px 16px;margin:18px 0">'
			. 'Durante el mes su sitio se actualizó <strong>%d %s</strong>.</p>',
			self::AZUL,
			$veces,
			1 === $veces ? 'vez' : 'veces'
		);

		if ( $lista ) {
			$c .= '<p>Estos son los componentes que se mantuvieron al día:</p>';
			$c .= '<ul style="margin:0 0 18px;padding-left:20px">' . $lista . '</ul>';
		}

		$c .= '<p>Las actualizaciones corrigen fallas de seguridad que se descubren y se publican '
			. 'continuamente. Mantenerlas al día es lo que evita que alguien las aproveche: es la '
			. 'diferencia entre un sitio que se mantiene y uno que un día amanece con problemas.</p>';

		$c .= sprintf(
			'<p style="color:%s">No hay nada que usted tenga que hacer. Le avisamos para que sepa que '
			. 'su sitio está siendo atendido.</p>',
			self::GRIS
		);

		return self::envolver( 'Reporte de mantenimiento', $c );
	}
}
