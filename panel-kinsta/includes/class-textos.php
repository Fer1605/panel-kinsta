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

	/** Cuántos módulos entran en el mensaje antes de resumir. */
	public const MAXIMO_EN_MENSAJE = 8;

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

		// Un mensaje con 23 módulos no se pega en ninguna cotización: se cierra y
		// no se usa. Van los más graves y se dice cuántos quedan, que para
		// decidir es suficiente — el detalle completo está en el panel.
		$total  = count( $graves );
		$sobran = max( 0, $total - self::MAXIMO_EN_MENSAJE );
		$graves = array_slice( $graves, 0, self::MAXIMO_EN_MENSAJE );

		$lineas   = [];
		$lineas[] = $sobran
			? sprintf(
				'El sitio %s tiene %d módulos con una vulnerabilidad de seguridad conocida públicamente. Estos son los %d más graves; los otros %d están en el reporte completo:',
				$sitio->nombre_visible,
				$total,
				count( $graves ),
				$sobran
			)
			: sprintf(
				'El sitio %s tiene %d módulo(s) con una vulnerabilidad de seguridad conocida públicamente. Recomendamos actualizarlos:',
				$sitio->nombre_visible,
				$total
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
				$lineas[] = '   Qué permite: ' . self::explicar( (string) $v->titulo, (string) $v->descripcion );

				// Si la versión disponible sigue estando afectada, decirlo. Mandar
				// a actualizar a una versión que tampoco lo arregla es peor que no
				// decir nada.
				$de_verdad = self::version_que_corrige( $v, (string) $h['version_disponible'] );
				if ( '' !== $de_verdad ) {
					$lineas[] = sprintf(
						'   Atención: la versión %s todavía está afectada. Hay que llegar a la %s o posterior.',
						$h['version_disponible'],
						$de_verdad
					);
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

	/**
	 * Traduce el tipo de vulnerabilidad a una frase que un cliente entienda.
	 *
	 * ─── Por qué esto y no traducir la descripción de Wordfence ─────────────
	 *
	 * El feed viene en inglés y en lenguaje técnico: "insufficient input
	 * sanitization and output escaping of user-supplied data". Aunque se
	 * tradujera palabra por palabra, seguiría sin decirle nada a un cliente —
	 * y lo que el jefe pidió es "algo que ellas puedan copiar y pegar en su
	 * cotización" (02:22). Una cotización con esa frase adentro no se manda.
	 *
	 * Las vulnerabilidades de WordPress caen casi todas en una docena de tipos,
	 * y el tipo viene escrito en el título del aviso. Así que en vez de traducir
	 * se reconoce el tipo y se usa una explicación escrita de antemano, en
	 * términos de qué le puede pasar al negocio del cliente.
	 *
	 * Sale mejor que una traducción, no cuesta nada y no depende de nadie.
	 */
	public static function explicar( string $titulo, string $respaldo = '' ): string {
		$t = strtolower( $titulo . ' ' . $respaldo );

		$tipos = [
			'sql injection'              => 'Permite leer o modificar la base de datos del sitio: los datos de los clientes, los pedidos y las contraseñas guardadas.',
			'remote code execution'      => 'Permite ejecutar programas dentro del servidor. Es la falla más grave que existe: con ella se instala un malware y se crean usuarios administradores.',
			'arbitrary file upload'      => 'Permite subir archivos al servidor y ejecutarlos. Es por donde entran la mayoría de los malwares que después crean usuarios administradores.',
			'arbitrary file deletion'    => 'Permite borrar archivos del sitio. Borrando el archivo correcto, el sitio se cae o queda abierto para que alguien lo tome.',
			'arbitrary file read'        => 'Permite leer archivos del servidor que no deberían verse, incluido el que guarda las contraseñas de la base de datos.',
			'local file inclusion'       => 'Permite leer archivos del servidor que no deberían verse, incluido el que guarda las contraseñas de la base de datos.',
			'path traversal'             => 'Permite salirse de las carpetas permitidas y llegar a archivos del servidor que no deberían verse.',
			'directory traversal'        => 'Permite salirse de las carpetas permitidas y llegar a archivos del servidor que no deberían verse.',
			'privilege escalation'       => 'Permite que una cuenta común se convierta en administradora del sitio.',
			'authentication bypass'      => 'Permite entrar al sitio sin usuario ni contraseña.',
			'arbitrary user password'    => 'Permite cambiarle la contraseña a otro usuario, incluido un administrador.',
			'account takeover'           => 'Permite apoderarse de la cuenta de otro usuario, incluida la de un administrador.',
			'cross-site scripting'       => 'Permite dejar código escondido en el sitio que se ejecuta en el navegador de quien lo visita. Se usa para robar sesiones y para redirigir a los visitantes a otras páginas.',
			'cross-site request forgery' => 'Permite engañar a un administrador para que, sin darse cuenta, ejecute un cambio en el sitio con solo abrir un enlace.',
			'server-side request forgery'=> 'Permite usar el servidor del sitio para alcanzar otros servicios internos que no deberían ser accesibles desde fuera.',
			'object injection'           => 'Permite manipular el funcionamiento interno del sitio, y según qué más esté instalado puede terminar en ejecución de programas.',
			'missing authorization'      => 'Permite que alguien sin permiso ejecute acciones reservadas a administradores.',
			'sensitive information'      => 'Expone información que debería ser privada: datos de clientes, copias de seguridad o configuración interna.',
			'information disclosure'     => 'Expone información que debería ser privada: datos de clientes, copias de seguridad o configuración interna.',
			'open redirect'              => 'Permite usar el dominio del sitio para mandar visitantes a páginas fraudulentas, lo que daña la reputación del dominio.',
			'denial of service'          => 'Permite tumbar el sitio y dejarlo sin responder.',
			'arbitrary option update'    => 'Permite cambiar la configuración del sitio, por ejemplo para abrir el registro de usuarios nuevos como administradores.',
		];

		foreach ( $tipos as $clave => $explicacion ) {
			if ( str_contains( $t, $clave ) ) {
				return $explicacion;
			}
		}

		// Si el tipo no se reconoce, mejor decir poco y verdadero que mucho e
		// inventado. El detalle técnico queda en el panel para quien lo quiera.
		return 'Es una falla de seguridad conocida y publicada. El detalle técnico está en el panel.';
	}

	/**
	 * ¿La versión a la que vamos a recomendar actualizar sigue siendo vulnerable?
	 *
	 * Pasa más de lo que uno quisiera: el sitio tiene la 8.2, hay una 9.9
	 * disponible, y la vulnerabilidad afecta hasta la 10.4. Recomendar la 9.9
	 * sería mandar a un cliente a hacer un trabajo que no lo deja seguro — y
	 * quedar mal cuando se entere.
	 *
	 * @return string La versión que de verdad lo corrige, o '' si no se sabe.
	 */
	public static function version_que_corrige( object $vulnerabilidad, string $disponible ): string {
		$parcheadas = json_decode( (string) $vulnerabilidad->versiones_parcheadas, true );
		$parcheadas = is_array( $parcheadas ) ? array_filter( array_map( 'strval', $parcheadas ) ) : [];

		if ( ! $parcheadas ) {
			return '';
		}

		// La menor de las versiones parcheadas que sea igual o mayor a la que
		// hay disponible. Si la disponible ya alcanza, se queda la disponible.
		usort( $parcheadas, static fn( $a, $b ) => version_compare( $a, $b ) );

		foreach ( $parcheadas as $p ) {
			if ( '' === $disponible || ! SWPK_Versiones::es_menor( $disponible, $p ) ) {
				return '';
			}
			return $p;
		}

		return '';
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
