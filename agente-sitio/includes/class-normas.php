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
			$normas['php_en_uploads'] = 'no_se_pudo' === $uploads ? 'no_se_pudo' : (bool) $uploads;
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
	 * @return bool|string true si se ejecuta (mal), false si no (bien),
	 *                     'no_se_pudo' si la prueba no se pudo hacer.
	 */
	public static function php_en_uploads(): bool|string {
		$subidas = wp_get_upload_dir();

		if ( ! empty( $subidas['error'] ) || empty( $subidas['basedir'] ) || empty( $subidas['baseurl'] ) ) {
			set_transient( self::CACHE_UPLOADS, 'no_se_pudo', HOUR_IN_SECONDS );

			return 'no_se_pudo';
		}

		$nombre = 'swa-prueba-' . wp_generate_password( 12, false, false ) . '.php';
		$ruta   = trailingslashit( $subidas['basedir'] ) . $nombre;
		$url    = trailingslashit( $subidas['baseurl'] ) . $nombre;

		// Lo más inofensivo que sigue siendo PHP: imprime una palabra. No lee
		// nada, no escribe nada, no llama a nada.
		$escrito = @file_put_contents( $ruta, "<?php echo '" . self::SENAL . "';\n" );

		if ( false === $escrito ) {
			set_transient( self::CACHE_UPLOADS, 'no_se_pudo', HOUR_IN_SECONDS );

			return 'no_se_pudo';
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
			set_transient( self::CACHE_UPLOADS, 'no_se_pudo', HOUR_IN_SECONDS );

			return 'no_se_pudo';
		}

		$codigo = (int) wp_remote_retrieve_response_code( $respuesta );
		$cuerpo = (string) wp_remote_retrieve_body( $respuesta );

		// Un 403 o un 404 tampoco es «no se ejecuta»: es que no se pudo
		// comprobar, porque el servidor ni sirvió el archivo. Decirlo como
		// bueno sería inventarse una buena noticia.
		if ( $codigo < 200 || $codigo >= 300 ) {
			set_transient( self::CACHE_UPLOADS, 'no_se_pudo', 12 * HOUR_IN_SECONDS );

			return 'no_se_pudo';
		}

		$ejecuta = str_contains( $cuerpo, self::SENAL ) && ! str_contains( $cuerpo, '<?php' );

		set_transient( self::CACHE_UPLOADS, $ejecuta ? 1 : 0, 12 * HOUR_IN_SECONDS );

		return $ejecuta;
	}

	/** Para el botón de «probar ahora» del escritorio del agente. */
	public static function olvidar_uploads(): void {
		delete_transient( self::CACHE_UPLOADS );
	}
}
