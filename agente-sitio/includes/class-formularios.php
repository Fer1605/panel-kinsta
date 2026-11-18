<?php
/**
 * Los archivos que la gente sube por un formulario, y quién puede bajarlos.
 *
 * ─── De dónde sale esto ─────────────────────────────────────────────────────
 *
 * Del audio del 18/11/2026. Es el ejemplo que el jefe dio, y es textual:
 *
 *   «que si se suben CVs —ese archivo se llama CV, currículum— hay datos de
 *    formularios públicos. Creeme que ese error es más común de lo que crees:
 *    hacen un formulario, dicen "suba su CV", y el CV queda público a todo el
 *    mundo, se filtran datos. ¿Por qué? Porque se suben los archivos a
 *    uploads, no hay tema de seguridad de carpeta»                   [00:39]
 *
 * ─── Por qué esto no lo puede hacer el panel ────────────────────────────────
 *
 * Para probar que un CV se baja hace falta saber cómo se llama el archivo, y
 * adivinarlo desde fuera sería andar pescando el currículum de alguien. El
 * agente no tiene que adivinar nada: está dentro, ve la carpeta, agarra el
 * archivo más reciente, lo traduce a dirección pública y la pide.
 *
 * Y se queda con el sí o el no. **El nombre del archivo no sale de aquí**: no
 * viaja al panel, no se escribe en la bitácora, no se guarda en ningún lado.
 * Eso es de la persona que lo subió, no nuestro.
 *
 * ─── El hallazgo que esto destapa en Kinsta ─────────────────────────────────
 *
 * Contact Form 7, Gravity Forms y WPForms escriben un `.htaccess` dentro de su
 * carpeta de subidas para protegerla. **Kinsta corre Nginx y no lee
 * `.htaccess`: ese archivo es texto inerte.** Se comprobó desde fuera el
 * 18/11/2026 que la carpeta se sirve —`uploads/gravity_forms/index.html`
 * devuelve 200— así que lo único que salva un archivo es lo difícil que sea
 * adivinar su ruta. Los tres plugins creen que están protegidos y no lo están.
 *
 * Por eso hay dos comprobaciones y no una: si el archivo se baja (grave), y si
 * hay un `.htaccess` ahí dando una falsa sensación de protección.
 */

defined( 'ABSPATH' ) || exit;

class SWA_Formularios {

	/**
	 * La prueba se guarda doce horas.
	 *
	 * Recorrer carpetas y hacer una petición HTTP cada cinco minutos, para
	 * contestar algo que solo cambia cuando alguien toca el servidor o sube un
	 * archivo nuevo, no tiene sentido.
	 */
	private const CACHE = 'swa_formularios';

	/**
	 * Dónde deja sus archivos cada plugin, relativo a la carpeta de subidas.
	 *
	 * Las rutas salen del código de cada plugin, no de suponer. La columna
	 * `protege` dice si el plugin escribe un archivo de protección ahí dentro
	 * — el que en Nginx no sirve de nada.
	 *
	 * @return array<string,array{archivo:string,carpeta:string,protege:bool}>
	 */
	private static function familias(): array {
		return [
			'contact-form-7/wp-contact-form-7.php' => [ 'archivo' => 'Contact Form 7', 'carpeta' => 'wpcf7_uploads', 'protege' => true ],
			'gravityforms/gravityforms.php'        => [ 'archivo' => 'Gravity Forms', 'carpeta' => 'gravity_forms', 'protege' => true ],
			'wpforms-lite/wpforms.php'             => [ 'archivo' => 'WPForms', 'carpeta' => 'wpforms', 'protege' => true ],
			'wpforms/wpforms.php'                  => [ 'archivo' => 'WPForms', 'carpeta' => 'wpforms', 'protege' => true ],
			'ninja-forms/ninja-forms.php'          => [ 'archivo' => 'Ninja Forms', 'carpeta' => 'ninja-forms', 'protege' => false ],
			'fluentform/fluentform.php'            => [ 'archivo' => 'Fluent Forms', 'carpeta' => 'fluentform', 'protege' => false ],
			'forminator/forminator.php'            => [ 'archivo' => 'Forminator', 'carpeta' => 'forminator', 'protege' => false ],
			// Elementor Pro viene con dos nombres de carpeta según de dónde se
			// instaló. Patchstack llama a la suya, textualmente, «una carpeta
			// pública y accesible por web».
			'elementor-pro/elementor-pro.php'      => [ 'archivo' => 'Elementor Forms', 'carpeta' => 'elementor/forms', 'protege' => false ],
			'pro-elements/pro-elements.php'        => [ 'archivo' => 'Elementor Forms', 'carpeta' => 'elementor/forms', 'protege' => false ],
			// Un añadido de CF7 que usa su propia carpeta, y que tuvo una falla
			// de subida publicada.
			'drag-and-drop-multiple-file-upload-contact-form-7/drag-n-drop-upload-cf7.php' => [ 'archivo' => 'Drag & Drop para CF7', 'carpeta' => 'wp_dndcf7_uploads', 'protege' => false ],
		];
	}

	/**
	 * Lo que se le manda al panel.
	 *
	 * Nunca nombres de archivo ni direcciones. Solo cuentas y sí/no.
	 *
	 * @return array<string,mixed>
	 */
	public static function medir( bool $completo = true ): array {
		$guardado = get_transient( self::CACHE );

		if ( is_array( $guardado ) ) {
			return $guardado;
		}

		if ( ! $completo ) {
			return [];
		}

		$subidas = wp_get_upload_dir();

		if ( ! empty( $subidas['error'] ) || empty( $subidas['basedir'] ) ) {
			return [ 'error' => 'no_se_pudo' ];
		}

		$base = trailingslashit( (string) $subidas['basedir'] );
		$url  = trailingslashit( (string) $subidas['baseurl'] );

		$activos    = [];
		$inertes    = [];
		$publicos   = [];
		$revisables = 0;

		foreach ( self::familias() as $plugin => $f ) {
			if ( ! self::activo( $plugin ) ) {
				continue;
			}

			$activos[] = $f['archivo'];

			$carpeta = $base . $f['carpeta'];

			if ( ! is_dir( $carpeta ) ) {
				continue;
			}

			// 1. ¿Hay un .htaccess ahí? En Nginx no hace nada, y su presencia
			//    es justo lo que hace creer que la carpeta está protegida.
			if ( $f['protege'] && file_exists( trailingslashit( $carpeta ) . '.htaccess' ) && ! self::hay_apache() ) {
				$inertes[] = $f['archivo'];
			}

			// 2. ¿Se baja un archivo de verdad?
			$muestra = self::muestra( $carpeta );

			if ( null === $muestra ) {
				continue;
			}

			++$revisables;

			$publico = self::se_baja(
				str_replace( $base, $url, $muestra ),
				(int) filesize( $muestra )
			);

			if ( true === $publico ) {
				$publicos[] = $f['archivo'];
			}
		}

		$resultado = [
			'plugins'     => array_values( array_unique( $activos ) ),
			'htaccess_inerte' => array_values( array_unique( $inertes ) ),
			'publicos'    => array_values( array_unique( $publicos ) ),
			'revisados'   => $revisables,
			'php_en_subidas' => self::php_sueltos( $base ),
			'antispam'    => self::antispam(),
		];

		set_transient( self::CACHE, $resultado, 12 * HOUR_IN_SECONDS );

		return $resultado;
	}

	public static function olvidar(): void {
		delete_transient( self::CACHE );
	}

	private static function activo( string $plugin ): bool {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		return is_plugin_active( $plugin );
	}

	/** ¿El servidor es Apache? Solo ahí un .htaccess hace algo. */
	private static function hay_apache(): bool {
		$s = strtolower( (string) ( $_SERVER['SERVER_SOFTWARE'] ?? '' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

		return str_contains( $s, 'apache' ) || str_contains( $s, 'litespeed' );
	}

	/**
	 * Un archivo real de los que subió la gente, para probar con él.
	 *
	 * Se busca el más reciente que no sea de relleno. Se devuelve la ruta para
	 * usarla aquí dentro y nada más: no sale de esta clase.
	 */
	private static function muestra( string $carpeta ): ?string {
		$saltar = [ 'index.php', 'index.html', 'index.htm', '.htaccess', 'web.config', '.DS_Store' ];

		$it = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $carpeta, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::LEAVES_ONLY
		);

		$mejor = null;
		$fecha = 0;
		$vistos = 0;

		foreach ( $it as $f ) {
			// Una carpeta con miles de archivos no debe hacer que el reporte
			// del agente tarde un minuto. Con doscientos alcanza de sobra.
			if ( ++$vistos > 200 ) {
				break;
			}

			if ( ! $f->isFile() || in_array( $f->getFilename(), $saltar, true ) ) {
				continue;
			}

			if ( $f->getMTime() > $fecha ) {
				$fecha = $f->getMTime();
				$mejor = $f->getPathname();
			}
		}

		return $mejor;
	}

	/**
	 * ¿Esta dirección entrega el archivo a alguien sin sesión?
	 *
	 * @return bool|null null si no se pudo comprobar
	 */
	private static function se_baja( string $url, int $tamano ): ?bool {
		if ( '' === $url || $tamano <= 0 ) {
			return null;
		}

		$r = wp_remote_get(
			$url,
			[
				'timeout'     => 10,
				'redirection' => 0,
				'sslverify'   => false, // un certificado local no debe arruinar la prueba
				'cookies'     => [],    // sin sesión: así lo vería un desconocido
				'headers'     => [ 'Cache-Control' => 'no-cache' ],
			]
		);

		if ( is_wp_error( $r ) ) {
			return null;
		}

		$codigo = (int) wp_remote_retrieve_response_code( $r );

		if ( 200 !== $codigo ) {
			return false;
		}

		// Un 200 con la página de error del tema no es el archivo. Que el
		// tamaño calce es lo que lo distingue.
		$largo = (int) wp_remote_retrieve_header( $r, 'content-length' );

		if ( $largo > 0 ) {
			return $largo === $tamano;
		}

		return strlen( (string) wp_remote_retrieve_body( $r ) ) === $tamano;
	}

	/**
	 * Archivos PHP sueltos bajo la carpeta de subidas.
	 *
	 * Wordfence lo dice sin rodeos en su aviso del fallo de Elementor Pro: esa
	 * carpeta no debería tener nunca un .php, así que encontrarlo es señal de
	 * que algo pasó. Los `index.php` de relleno que ponen varios plugins no
	 * cuentan: son archivos vacíos para que la carpeta no liste su contenido.
	 */
	private static function php_sueltos( string $base ): int {
		if ( ! is_dir( $base ) ) {
			return 0;
		}

		$n      = 0;
		$vistos = 0;

		$it = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $base, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::LEAVES_ONLY
		);

		foreach ( $it as $f ) {
			if ( ++$vistos > 5000 ) {
				break;
			}

			if ( ! $f->isFile() ) {
				continue;
			}

			$nombre = strtolower( $f->getFilename() );

			if ( 'index.php' === $nombre ) {
				continue;
			}

			if ( preg_match( '/\.(php|phtml|phar|php\d)$/', $nombre ) ) {
				++$n;
			}
		}

		return $n;
	}

	/**
	 * ¿Los formularios tienen honeypot o captcha?
	 *
	 * Esto era una norma declarativa —alguien la marcaba a mano— porque «no
	 * hay forma de comprobarla igual en todos los plugins». Sigue siendo
	 * cierto que cada uno la guarda a su manera, pero dos cosas se pueden leer
	 * sin adivinar: los ajustes globales de cada plugin, que es donde viven
	 * reCAPTCHA y los honeypot en la mayoría.
	 *
	 * Dos trampas que harían dar falsos negativos, y por eso se miran los
	 * ajustes globales y no cada formulario:
	 *
	 *   - El honeypot de Fluent Forms y el reCAPTCHA v3 de Contact Form 7 son
	 *     GLOBALES. Mirar formulario por formulario diría «este no tiene».
	 *   - El antispam moderno de WPForms depende de JavaScript, así que leer el
	 *     HTML de la página tampoco lo ve.
	 *
	 * @return array<string,mixed>
	 */
	private static function antispam(): array {
		$con = [];
		$sin = [];

		$mira = static function ( string $plugin, string $nombre, callable $tiene ) use ( &$con, &$sin ): void {
			if ( ! self::activo( $plugin ) ) {
				return;
			}

			if ( $tiene() ) {
				$con[] = $nombre;
			} else {
				$sin[] = $nombre;
			}
		};

		$mira(
			'contact-form-7/wp-contact-form-7.php',
			'Contact Form 7',
			static function (): bool {
				// CF7 guarda la llave de reCAPTCHA como un arreglo sitio=>secreto.
				$r = class_exists( 'WPCF7' ) ? WPCF7::get_option( 'recaptcha' ) : null;

				if ( ! empty( $r ) ) {
					return true;
				}

				// O el añadido de honeypot, que es un plugin aparte.
				return self::activo( 'contact-form-7-honeypot/honeypot.php' );
			}
		);

		$mira(
			'wpforms-lite/wpforms.php',
			'WPForms',
			static function (): bool {
				$a = (array) get_option( 'wpforms_settings', [] );

				return ! empty( $a['captcha-provider'] ) || ! empty( $a['recaptcha-site-key'] )
					|| ! empty( $a['hcaptcha-site-key'] ) || ! empty( $a['turnstile-site-key'] );
			}
		);

		$mira(
			'fluentform/fluentform.php',
			'Fluent Forms',
			static function (): bool {
				foreach ( [ '_fluentform_reCaptcha_details', '_fluentform_hCaptcha_details', '_fluentform_turnstile_details' ] as $o ) {
					if ( ! empty( get_option( $o ) ) ) {
						return true;
					}
				}

				$g = (array) get_option( '_fluentform_global_form_settings', [] );

				return ! empty( $g['misc']['honeypot'] ) || ! empty( $g['misc']['_honeypot'] );
			}
		);

		$mira(
			'elementor-pro/elementor-pro.php',
			'Elementor Forms',
			static function (): bool {
				return '' !== (string) get_option( 'elementor_pro_recaptcha_site_key', '' )
					|| '' !== (string) get_option( 'elementor_pro_recaptcha_v3_site_key', '' );
			}
		);

		$mira(
			'gravityforms/gravityforms.php',
			'Gravity Forms',
			static function (): bool {
				return '' !== (string) get_option( 'rg_gforms_captcha_public_key', '' );
			}
		);

		return [ 'con' => $con, 'sin' => $sin ];
	}
}
