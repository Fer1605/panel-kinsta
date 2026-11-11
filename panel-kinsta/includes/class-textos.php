<?php
/**
 * Los textos de los avisos.
 *
 * Esto es más importante de lo que parece. El jefe no pidió un reporte técnico:
 * pidió "algo que ellas puedan copiar y pegar en su cotización" (02:22). Si el
 * panel entrega una tabla de CVE y versiones, la asesora tiene que traducirla,
 * y si tiene que traducirla no la usa. Entonces el panel entrega el texto ya
 * escrito, en el idioma del cliente, y la asesora solo lo copia.
 *
 * Tres audiencias, tres idiomas distintos para el mismo hecho:
 *   - cliente:  tranquilidad y constancia, sin tecnicismos
 *   - asesora:  argumento de venta, con el riesgo en términos de negocio
 *   - soporte:  el dato crudo, versiones y slugs
 */

defined( 'ABSPATH' ) || exit;

class SWPK_Textos {

	/** El texto que la asesora copia y pega en una cotización. */
	public static function para_asesora( object $sitio, array $hallazgos ): string {
		$graves = array_values(
			array_filter(
				$hallazgos,
				static fn( $h ) => in_array( $h['nivel'], [ SWPK_Riesgo::CRITICO, SWPK_Riesgo::ALTO ], true )
			)
		);

		if ( ! $graves ) {
			$pendientes = count( $hallazgos );
			if ( 0 === $pendientes ) {
				return sprintf(
					"El sitio %s está al día: no tiene módulos pendientes de actualizar ni vulnerabilidades conocidas.",
					$sitio->nombre_visible
				);
			}

			return sprintf(
				"El sitio %s tiene %d módulo(s) con actualización pendiente. Ninguno tiene una vulnerabilidad conocida hoy, pero mantenerlos al día es lo que evita llegar a ese punto.",
				$sitio->nombre_visible,
				$pendientes
			);
		}

		$lineas   = [];
		$lineas[] = sprintf(
			'El sitio %s tiene %d módulo(s) con una vulnerabilidad de seguridad conocida públicamente. Recomendamos actualizarlos:',
			$sitio->nombre_visible,
			count( $graves )
		);
		$lineas[] = '';

		$n = 0;
		foreach ( $graves as $h ) {
			++$n;

			$destino = '' !== $h['version_disponible'] ? $h['version_disponible'] : 'la última versión disponible';
			$lineas[] = sprintf( '%d. %s', $n, $h['titulo'] );
			$lineas[] = sprintf( '   Versión instalada: %s  →  actualizar a: %s', $h['version'], $destino );

			if ( ! empty( $h['vulnerabilidades'] ) ) {
				$v        = $h['vulnerabilidades'][0];
				$severidad = '' !== (string) $v->cvss_rating
					? sprintf( '%s (%s sobre 10)', self::severidad_en_palabras( (string) $v->cvss_rating ), (string) $v->cvss_score )
					: 'severidad no publicada';

				$lineas[] = sprintf( '   Riesgo: %s', $severidad );

				$descripcion = trim( (string) $v->descripcion );
				if ( '' !== $descripcion ) {
					$lineas[] = '   Qué permite: ' . self::recortar( $descripcion, 220 );
				}
				if ( '' !== (string) $v->cve ) {
					$lineas[] = '   Referencia: ' . $v->cve;
				}
			} else {
				$lineas[] = '   Riesgo: el proveedor de hosting marca esta versión como vulnerable.';
			}

			if ( SWPK_Riesgo::esta_abandonado( $h['slug'] ) ) {
				$lineas[] = '   Atención: este módulo ya no se mantiene. Actualizar no lo arregla; hay que reemplazarlo.';
			}

			$lineas[] = '';
		}

		$lineas[] = 'Mientras el sitio siga con estas versiones, queda expuesto a un ataque automatizado: estas fallas se publican y los robots las buscan por internet sin elegir a quién.';

		return implode( "\n", $lineas );
	}

	/** El correo al cliente. Tranquilidad, no tecnicismos (05:37). */
	public static function para_cliente( object $sitio, int $actualizaciones, array $plugins ): string {
		if ( 0 === $actualizaciones ) {
			return sprintf(
				'<p>Hola,</p><p>Le escribimos del equipo de Solución Web con el reporte de mantenimiento de <strong>%s</strong>.</p><p>Este mes su sitio no requirió actualizaciones. Lo seguimos revisando todos los días.</p>',
				esc_html( $sitio->nombre_visible )
			);
		}

		$lista = '';
		foreach ( array_slice( $plugins, 0, 12 ) as $nombre ) {
			$lista .= '<li>' . esc_html( $nombre ) . '</li>';
		}

		return sprintf(
			'<p>Hola,</p><p>Le escribimos del equipo de Solución Web con el reporte de mantenimiento de <strong>%s</strong>.</p>'
			. '<p>Este mes su sitio se actualizó <strong>%d %s</strong>. Estos son los módulos que se mantuvieron al día:</p>'
			. '<ul>%s</ul>'
			. '<p>Las actualizaciones corrigen fallas de seguridad que se publican todos los días. Mantenerlas al día es lo que mantiene su sitio seguro.</p>',
			esc_html( $sitio->nombre_visible ),
			$actualizaciones,
			1 === $actualizaciones ? 'vez' : 'veces',
			$lista
		);
	}

	private static function severidad_en_palabras( string $rating ): string {
		return match ( strtolower( $rating ) ) {
			'critical' => 'crítico',
			'high'     => 'alto',
			'medium'   => 'medio',
			'low'      => 'bajo',
			default    => 'no clasificado',
		};
	}

	private static function recortar( string $texto, int $largo ): string {
		$texto = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $texto ) ) ?? '' );
		if ( mb_strlen( $texto ) <= $largo ) {
			return $texto;
		}
		return mb_substr( $texto, 0, $largo - 1 ) . '…';
	}
}
