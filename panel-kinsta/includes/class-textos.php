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

	/**
	 * Quién puede aprovechar esta falla.
	 *
	 * ─── Por qué esto es lo primero que hay que decir ───────────────────────
	 *
	 * No es lo mismo "cualquiera desde internet, sin tener cuenta" que "alguien
	 * que ya es administrador del sitio". Lo primero es una emergencia; lo
	 * segundo casi nunca lo es. Un mensaje que no distingue las dos cosas trata
	 * todo por igual, y entonces el cliente no hace nada — que es exactamente lo
	 * que pasa con los avisos genéricos.
	 *
	 * El dato sale de dos lugares, en este orden:
	 *   1. El vector CVSS, que lo dice en clave: PR:N = sin privilegios,
	 *      PR:L = cuenta baja, PR:H = cuenta alta, UI:R = hace falta un clic.
	 *   2. La descripción de Wordfence, que lo escribe en palabras.
	 *
	 * @return array{frase:string,sin_cuenta:bool,con_clic:bool}
	 */
	public static function quien_puede( string $vector, string $descripcion ): array {
		$v = strtoupper( $vector );
		$d = strtolower( $descripcion );

		$con_clic = str_contains( $v, 'UI:R' )
			|| str_contains( $d, 'trick a user' )
			|| str_contains( $d, 'tricking an administrator' );

		// El vector manda cuando está.
		if ( str_contains( $v, 'PR:N' ) ) {
			$nivel = 'ninguna';
		} elseif ( str_contains( $v, 'PR:H' ) ) {
			$nivel = 'alta';
		} elseif ( str_contains( $v, 'PR:L' ) ) {
			$nivel = 'baja';
		} elseif ( str_contains( $d, 'unauthenticated' ) ) {
			$nivel = 'ninguna';
		} elseif ( preg_match( '/(administrator|admin)-level/', $d ) ) {
			$nivel = 'alta';
		} elseif ( preg_match( '/(subscriber|contributor|author|editor|customer|shop manager)-level/', $d, $m ) ) {
			$nivel = 'baja';
			$rol   = $m[1];
		} elseif ( str_contains( $d, 'authenticated' ) ) {
			$nivel = 'baja';
		} else {
			$nivel = 'desconocida';
		}

		$roles = [
			'subscriber'   => 'suscriptor',
			'customer'     => 'cliente de la tienda',
			'contributor'  => 'colaborador',
			'author'       => 'autor',
			'editor'       => 'editor',
			'shop manager' => 'gestor de la tienda',
		];

		$frase = match ( $nivel ) {
			'ninguna' => 'Cualquiera desde internet, sin tener cuenta en el sitio.',
			'baja'    => isset( $rol )
				? sprintf( 'Cualquiera que tenga una cuenta en el sitio, aunque sea de %s.', $roles[ $rol ] ?? $rol )
				: 'Cualquiera que tenga una cuenta en el sitio, aunque sea de las más básicas.',
			'alta'    => 'Solo alguien que ya tiene acceso de administrador.',
			default   => 'No está publicado qué nivel de acceso hace falta.',
		};

		if ( $con_clic ) {
			$frase .= ' Hace falta, eso sí, que alguien del sitio abra un enlace preparado.';
		}

		return [
			'frase'      => $frase,
			'sin_cuenta' => 'ninguna' === $nivel,
			'con_clic'   => $con_clic,
		];
	}

	/**
	 * Qué hay en juego en ESTE módulo.
	 *
	 * La misma falla no pesa igual en un plugin de traducciones que en el que
	 * guarda los pedidos. Aquí se dice qué información o qué función concreta
	 * está detrás del módulo afectado, que es lo que convierte un aviso técnico
	 * en algo que al cliente le importa.
	 *
	 * Solo se dice cuando se sabe. Inventarlo sería peor que callarlo.
	 */
	public static function que_hay_en_juego( string $slug, string $titulo ): string {
		$s = strtolower( $slug . ' ' . $titulo );

		$familias = [
			'tienda'    => [ [ 'woocommerce', 'wc-', 'easy-digital', 'cart', 'checkout' ], 'Este módulo es parte de la tienda: detrás de él están los pedidos, las direcciones y los datos de pago de sus clientes.' ],
			'respaldo'  => [ [ 'updraft', 'backwpup', 'duplicator', 'wp-migration', 'backup', 'migrate' ], 'Este módulo maneja las copias de seguridad. Una copia contiene la base de datos completa del sitio: todos sus usuarios, sus clientes y su contenido.' ],
			'formulario'=> [ [ 'contact-form', 'wpforms', 'gravityforms', 'ninja-forms', 'forminator', 'formidable' ], 'Este módulo maneja los formularios: detrás de él están los datos de todas las personas que han escrito al sitio.' ],
			'archivos'  => [ [ 'file-manager', 'filemanager', 'wp-file' ], 'Este módulo da acceso directo a los archivos del servidor, que es lo más sensible que hay en un sitio.' ],
			'usuarios'  => [ [ 'user-registration', 'memberpress', 'members', 'learndash', 'ultimate-member', 'profile' ], 'Este módulo maneja las cuentas de usuario del sitio.' ],
			'correo'    => [ [ 'smtp', 'mailer', 'newsletter', 'mailchimp' ], 'Este módulo guarda la configuración del correo saliente, incluidas sus credenciales de envío.' ],
			'paginas'   => [ [ 'elementor', 'divi', 'wpbakery', 'beaver', 'bricks', 'oxygen' ], 'Este módulo es el constructor con el que están hechas las páginas: quien lo controle puede modificar cualquier página del sitio, incluida la de pago.' ],
			'seguridad' => [ [ 'wordfence', 'sucuri', 'ithemes-security', 'all-in-one-wp-security', 'limit-login' ], 'Este módulo es, precisamente, el que protege el sitio.' ],
		];

		foreach ( $familias as $datos ) {
			foreach ( $datos[0] as $pista ) {
				if ( str_contains( $s, $pista ) ) {
					return $datos[1];
				}
			}
		}

		return '';
	}

	/**
	 * El texto que la asesora copia y pega en una cotización o en un correo.
	 *
	 * ─── Qué tiene que lograr este texto ────────────────────────────────────
	 *
	 * Que el cliente entienda qué le puede pasar a ÉL. "Hay que actualizar estos
	 * módulos o te pueden hackear" no logra nada: el cliente asiente y no hace
	 * nada, porque no le dijimos qué se juega.
	 *
	 * Por eso cada módulo se explica en tres líneas que responden tres preguntas
	 * distintas, y las tres salen de datos reales, no de frases hechas:
	 *
	 *   Quién puede      ← del vector CVSS o de la descripción de Wordfence
	 *   Qué puede hacer  ← del tipo de vulnerabilidad
	 *   Qué hay en juego ← de qué módulo es y qué información maneja
	 *
	 * Y arriba, lo primero que se lee: cuántas de estas se pueden aprovechar sin
	 * tener cuenta en el sitio. Esa cifra es la que hace que alguien conteste el
	 * correo.
	 */
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
				"El sitio %s tiene %d módulo(s) con una actualización pendiente.\n\n"
				. "Ninguno tiene hoy una vulnerabilidad conocida públicamente, así que no hay urgencia. "
				. "Pero las fallas se publican todas las semanas, y un módulo desactualizado es el que "
				. "las recibe: mantenerlos al día es lo que evita llegar al punto en que sí haya prisa.",
				$sitio->nombre_visible,
				$pendientes
			);
		}

		// Primero los que SÍ tienen detalle público. Son los únicos que
		// convencen: de los otros solo se puede decir "el hosting dice que es
		// vulnerable", y un mensaje que empieza con ocho de esos no mueve a
		// nadie. A igualdad de detalle, manda la gravedad.
		usort(
			$graves,
			static function ( $a, $b ) {
				$da = empty( $a['vulnerabilidades'] ) ? 1 : 0;
				$db = empty( $b['vulnerabilidades'] ) ? 1 : 0;
				if ( $da !== $db ) {
					return $da <=> $db;
				}
				$pa = (float) ( $a['vulnerabilidades'][0]->cvss_score ?? 0 );
				$pb = (float) ( $b['vulnerabilidades'][0]->cvss_score ?? 0 );
				return $pb <=> $pa;
			}
		);

		$total  = count( $graves );
		$sobran = max( 0, $total - self::MAXIMO_EN_MENSAJE );
		$graves = array_slice( $graves, 0, self::MAXIMO_EN_MENSAJE );

		// Cuántas se pueden aprovechar sin cuenta: el dato que mueve a actuar.
		$sin_cuenta = 0;
		foreach ( $graves as $h ) {
			$v = $h['vulnerabilidades'][0] ?? null;
			if ( $v && self::quien_puede( (string) ( $v->cvss_vector ?? '' ), (string) $v->descripcion )['sin_cuenta'] ) {
				++$sin_cuenta;
			}
		}

		$lineas   = [];
		$lineas[] = sprintf(
			'Revisamos el estado de seguridad de %s y encontramos %d módulo(s) con una vulnerabilidad conocida públicamente.',
			$sitio->nombre_visible,
			$total
		);

		if ( $sin_cuenta ) {
			$lineas[] = '';
			$lineas[] = sprintf(
				$sin_cuenta > 1
					? 'De esas, %d se pueden aprovechar desde internet sin tener cuenta en el sitio. Esto es lo que buscan los ataques automáticos: programas que recorren internet probando estas fallas una por una, sin elegir a quién.'
					: 'De esas, %d se puede aprovechar desde internet sin tener cuenta en el sitio. Esto es lo que buscan los ataques automáticos: programas que recorren internet probando estas fallas una por una, sin elegir a quién.',
				$sin_cuenta
			);
		}

		$lineas[] = '';
		$lineas[] = $sobran
			? sprintf( 'Los %d más graves, y qué significa cada uno:', count( $graves ) )
			: 'Qué significa cada uno:';
		$lineas[] = '';

		$n = 0;
		foreach ( $graves as $h ) {
			++$n;

			$v       = $h['vulnerabilidades'][0] ?? null;
			$destino = '' !== $h['version_disponible'] ? $h['version_disponible'] : 'la última versión disponible';

			$lineas[] = sprintf( '%d. %s', $n, self::limpiar_titulo( (string) $h['titulo'] ) );
			$lineas[] = sprintf( '   Tiene la versión %s. Hay que pasarlo a %s.', $h['version'], $destino );

			if ( ! $v ) {
				$lineas[] = '   El proveedor de hosting marca esta versión como vulnerable. Estamos a la espera del detalle público de la falla.';
				$lineas[] = '';
				continue;
			}

			$quien = self::quien_puede( (string) ( $v->cvss_vector ?? '' ), (string) $v->descripcion );

			if ( '' !== (string) $v->cvss_rating ) {
				$lineas[] = sprintf(
					'   Gravedad: %s, %s sobre 10.',
					self::severidad_en_palabras( (string) $v->cvss_rating ),
					(string) $v->cvss_score
				);
			}

			$lineas[] = '   Quién puede aprovecharlo: ' . $quien['frase'];
			$lineas[] = '   Qué permite hacer: ' . self::explicar( (string) $v->titulo, (string) $v->descripcion );

			$juego = self::que_hay_en_juego( (string) $h['slug'], (string) $h['titulo'] );
			if ( '' !== $juego ) {
				$lineas[] = '   Qué hay en juego: ' . $juego;
			}

			$corrige = self::version_que_corrige( $v, (string) $h['version_disponible'] );
			if ( '' !== $corrige ) {
				$lineas[] = sprintf(
					'   Atención: la versión %s todavía está afectada. Hay que llegar a la %s o posterior.',
					$h['version_disponible'],
					$corrige
				);
			}

			if ( SWPK_Riesgo::esta_abandonado( (string) $h['slug'] ) ) {
				$lineas[] = '   Atención: este módulo ya no se mantiene. Actualizar no lo arregla; hay que reemplazarlo.';
			}

			if ( '' !== (string) $v->cve ) {
				$lineas[] = '   Referencia pública: ' . $v->cve;
			}

			$lineas[] = '';
		}

		if ( $sobran ) {
			$lineas[] = sprintf( 'Hay %d módulo(s) más en la misma situación, con el detalle en nuestro reporte completo.', $sobran );
			$lineas[] = '';
		}

		// El cierre se arma con el peor hallazgo concreto, no con una frase hecha.
		$lineas[] = self::cierre( $graves[0] ?? null, $sin_cuenta );

		return implode( "\n", $lineas );
	}

	/** El párrafo final, construido sobre el peor hallazgo de verdad. */
	private static function cierre( ?array $peor, int $sin_cuenta ): string {
		if ( ! $peor ) {
			return 'Recomendamos programar estas actualizaciones cuanto antes.';
		}

		$v = $peor['vulnerabilidades'][0] ?? null;

		if ( ! $v ) {
			return 'Recomendamos programar estas actualizaciones cuanto antes. Podemos encargarnos nosotros y dejar constancia de lo que se actualizó.';
		}

		$quien = self::quien_puede( (string) ( $v->cvss_vector ?? '' ), (string) $v->descripcion );
		$que   = self::explicar( (string) $v->titulo, (string) $v->descripcion );

		$base = sprintf(
			'La más grave es la de %s: %s %s',
			self::limpiar_titulo( (string) $peor['titulo'] ),
			lcfirst( rtrim( $que, '.' ) ) . '.',
			$quien['sin_cuenta']
				? 'Y no hace falta tener cuenta en el sitio para aprovecharla.'
				: ''
		);

		$base = trim( $base );

        $base .= $sin_cuenta
			? ' Estas fallas están publicadas con nombre y número, lo que significa que cualquiera puede consultarlas — y los programas que atacan sitios las consultan. El riesgo no es que alguien decida atacarlos: es que un programa pase por su sitio y encuentre la puerta abierta.'
			: ' Estas fallas están publicadas con nombre y número, así que son conocidas por cualquiera que quiera buscarlas.';

		return $base . "\n\nPodemos encargarnos nosotros de estas actualizaciones y dejarle constancia por escrito de qué se actualizó y cuándo.";
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

	/** Los nombres vienen del feed con entidades HTML (&nbsp;, &amp;) que en un
	 * correo de texto plano se ven como basura. */
	public static function limpiar_titulo( string $titulo ): string {
		$t = html_entity_decode( $titulo, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$t = str_replace( "\xC2\xA0", ' ', $t );
		return trim( preg_replace( '/\s+/u', ' ', $t ) ?? $t );
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
