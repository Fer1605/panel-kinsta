<?php
/**
 * Lo que este sitio puede decir de sí mismo sobre las normas de seguridad.
 *
 * El panel no puede comprobar casi nada de esto desde fuera: si el editor de
 * código está abierto, si el registro de usuarios está abierto, si PHP se
 * ejecuta en la carpeta de subidas. Son cosas que solo se saben estando dentro.
 * Por eso están aquí, y por eso viajan pegadas al reporte diario en vez de
 * tener su propia ruta: una ruta más sería una puerta más.
 *
 * ─── La prueba de /uploads es una prueba de verdad ──────────────────────────
 *
 * El jefe pidió «que tenga htaccess bien configurado». En Kinsta no existe
 * `.htaccess` —corre Nginx— así que mirar el archivo daría «no cumple» en los
 * 107 sitios y no significaría nada.
 *
 * Lo que de verdad importa es el efecto: si alguien logra subir un archivo PHP
 * por un formulario, ¿el servidor lo ejecuta o lo devuelve como texto? Eso se
 * comprueba haciéndolo: se escribe un archivo inofensivo en la carpeta de
 * subidas, se pide por HTTP, se mira qué contestó y se borra. Da el mismo
 * resultado en Nginx, en Apache y en Pantheon, porque mide la consecuencia y
 * no la receta.
 *
 * El archivo de prueba no hace nada: imprime una palabra. Vive segundos, tiene
 * nombre al azar y se borra incluso si la petición falla.
 *
 * ─── Lo que se corrigió el 18/11/2026 ──────────────────────────────────────
 *
 * La prueba estaba bien hecha pero mal interpretada. Cualquier respuesta que no
 * fuera 2xx se reportaba como «no se pudo comprobar», y en Kinsta lo que pasa
 * es un 403: tiene una regla de Nginx que no sirve archivos .php de la carpeta
 * de subidas. Se midió desde fuera contra 14 sitios de la cartera y 13
 * devolvieron 403 para un .php inexistente mientras devolvían 404 o 302 para un
 * .txt con el mismo nombre — o sea, la regla existe y mira la extensión.
 *
 * Un 403 ahí es la MEJOR noticia posible: el servidor no entrega el archivo ni
 * para leerlo, que protege más que devolverlo como texto. Mientras contara como
 * «no sé», el panel dejaba esa norma en «sin dato» para siempre en los 108
 * sitios. Ahora son cuatro respuestas y no tres, y la nueva es «rechazado».
 */

defined( 'ABSPATH' ) || exit;

class SWA_Normas {

	/** La palabra que delata que el archivo se ejecutó. */
	private const SENAL = 'SWA-UPLOADS-EJECUTA';

	/**
	 * La prueba de subidas se guarda doce horas.
	 *
	 * El agente pregunta por órdenes cada cinco minutos. Escribir un archivo y
	 * pedirlo por HTTP cada cinco minutos serían 288 peticiones al día que el
	 * sitio se hace a sí mismo para contestar algo que no cambia nunca, salvo
	 * que alguien toque la configuración del servidor.
	 */
	private const CACHE_UPLOADS = 'swa_php_en_uploads';

	/**
	 * Lo que se le manda al panel.
	 *
	 * Los nombres son los que esperan los comprobadores de SWPK_Normas. Un dato
	 * que falte llega como ausente, no como `false`: el panel distingue «no
	 * cumple» de «no lo sé», y mandar `false` por no saber convertiría cada
	 * hueco en una alarma falsa.
	 *
	 * @return array<string,mixed>
	 */
	public static function medir( bool $incluir_uploads = true ): array {
		$normas = [
			'editor_codigo'    => self::editor_cerrado(),
			'registro_abierto' => (bool) get_option( 'users_can_register' ),
		];

		// En el reporte liviano de cada cinco minutos no va: se manda en el
		// diario, o cuando ya está en caché y no cuesta nada.
		$uploads = get_transient( self::CACHE_UPLOADS );

		if ( false !== $uploads ) {
			$normas['php_en_uploads'] = (string) $uploads;
		} elseif ( $incluir_uploads ) {
			$normas['php_en_uploads'] = self::php_en_uploads();
		}

		return $normas;
	}

	/**
	 * ¿Está cerrado el editor de temas y plugins?
	 *
	 * Son dos constantes y hacen falta las dos. `DISALLOW_FILE_MODS` lo cierra
	 * todo —editor, instalar, actualizar— y `DISALLOW_FILE_EDIT` cierra solo el
	 * editor. Con cualquiera de las dos puestas, nadie escribe código desde el
	 * escritorio, que es lo que la norma pregunta.
	 */
	public static function editor_cerrado(): bool {
		if ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS ) {
			return true;
		}

		return defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT;
	}

	/**
	 * ¿Se ejecuta PHP en la carpeta de subidas?
	 *
	 * Cuatro respuestas, y la tercera es la que faltaba:
	 *
	 *   'ejecuta'    el servidor lo ejecutó. Mal.
	 *   'texto'      el servidor lo devolvió como texto. Bien.
	 *   'rechazado'  el servidor se niega a entregarlo. Mejor todavía: no se
	 *                puede ni leer.
	 *   'no_se_pudo' la prueba no se pudo hacer.
	 *
	 * Antes un 403 caía en 'no_se_pudo', y en Kinsta un 403 es justo lo que
	 * pasa: tiene una regla de Nginx que no sirve archivos .php de la carpeta
	 * de subidas. Comprobado el 18/11/2026 contra 14 sitios de la cartera, 403
	 * en 13. Así que la mejor noticia posible se estaba reportando como «no
	 * sé», y la norma se quedaba sin veredicto para siempre.
	 */
	public static function php_en_uploads(): string {
		$subidas = wp_get_upload_dir();

		if ( ! empty( $subidas['error'] ) || empty( $subidas['basedir'] ) || empty( $subidas['baseurl'] ) ) {
			return self::recordar( 'no_se_pudo' );
		}

		$nombre = 'swa-prueba-' . wp_generate_password( 12, false, false ) . '.php';
		$ruta   = trailingslashit( $subidas['basedir'] ) . $nombre;
		$url    = trailingslashit( $subidas['baseurl'] ) . $nombre;

		// Lo más inofensivo que sigue siendo PHP: imprime una palabra. No lee
		// nada, no escribe nada, no llama a nada.
		$escrito = @file_put_contents( $ruta, "<?php echo '" . self::SENAL . "';\n" );

		if ( false === $escrito ) {
			return self::recordar( 'no_se_pudo' );
		}

		$respuesta = wp_remote_get(
			$url,
			[
				'timeout'     => 10,
				'redirection' => 0,
				'sslverify'   => false, // un certificado local no debe arruinar la prueba
				'headers'     => [ 'Cache-Control' => 'no-cache' ],
			]
		);

		// El borrado va antes de interpretar nada, para que ninguna rama de
		// salida deje el archivo puesto.
		@unlink( $ruta );

		if ( is_wp_error( $respuesta ) ) {
			return self::recordar( 'no_se_pudo' );
		}

		$codigo = (int) wp_remote_retrieve_response_code( $respuesta );
		$cuerpo = (string) wp_remote_retrieve_body( $respuesta );

		// El archivo existe: lo acabamos de escribir. Así que un 403 o un 404
		// no es «no se pudo comprobar», es el servidor negándose a entregarlo,
		// que es exactamente lo que queremos que pase.
		if ( in_array( $codigo, [ 401, 403, 404, 405, 410, 451 ], true ) ) {
			return self::recordar( 'rechazado' );
		}

		if ( $codigo < 200 || $codigo >= 300 ) {
			return self::recordar( 'no_se_pudo' );
		}

		if ( str_contains( $cuerpo, '<?php' ) ) {
			// Vino el código fuente: no se ejecutó.
			return self::recordar( 'texto' );
		}

		if ( str_contains( $cuerpo, self::SENAL ) ) {
			return self::recordar( 'ejecuta' );
		}

		// 200 pero el cuerpo no es ni la señal ni el código. Puede ser una
		// página de bloqueo, una redirección servida como 200 o una caché
		// intermedia. No se sabe, y no se inventa.
		return self::recordar( 'no_se_pudo' );
	}

	private static function recordar( string $estado ): string {
		set_transient(
			self::CACHE_UPLOADS,
			$estado,
			'no_se_pudo' === $estado ? HOUR_IN_SECONDS : 12 * HOUR_IN_SECONDS
		);

		return $estado;
	}

	/** Para el botón de «probar ahora» del escritorio del agente. */
	public static function olvidar_uploads(): void {
		delete_transient( self::CACHE_UPLOADS );
	}
}
