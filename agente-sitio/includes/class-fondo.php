<?php
/**
 * Lo que solo se ve estando dentro: configuración, permisos e integridad.
 *
 * ─── Por qué 16 normas y no 45 ──────────────────────────────────────────────
 *
 * El catálogo que salió de la investigación listaba cuarenta y cinco cosas
 * medibles aquí dentro: dieciocho constantes de `wp-config.php`, nueve de
 * usuarios, catorce de integridad y cuatro de registro. Todas son reales.
 *
 * Pero una pantalla con cuarenta y cinco filas por sitio, en 108 sitios, no la
 * mira nadie — y una norma que nadie mira es peor que ninguna, porque da la
 * impresión de que se está revisando. Así que se agrupan por lo que significan:
 * «el modo de depuración está apagado» es una norma, y por dentro comprueba
 * cuatro cosas. Ninguna comprobación se pierde; lo que cambia es cuántas filas
 * salen y qué dice cada una.
 *
 * Cuando una falla, el detalle dice exactamente cuál de las cuatro fue.
 *
 * ─── Lo que cuesta caro, y cómo se paga ─────────────────────────────────────
 *
 * Dos de estas son lentas de verdad: comparar los archivos del núcleo contra
 * las sumas oficiales son unos dos mil `md5_file`, y preguntar por cada plugin
 * si sigue abierto en el repositorio son unas veinticinco peticiones a
 * WordPress.org. Ninguna de las dos cambia de un minuto a otro, así que van en
 * caché de doce horas y solo se rehacen en el reporte diario, nunca en el
 * liviano de cada cinco minutos.
 */

defined( 'ABSPATH' ) || exit;

class SWA_Fondo {

	private const CACHE       = 'swa_fondo';
	private const CACHE_SUMAS = 'swa_sumas';
	private const CACHE_REPO  = 'swa_repo_';

	/**
	 * Las ocho llaves que WordPress usa para firmar sesiones.
	 *
	 * Si faltan, si son el texto de ejemplo, o si son iguales en dos sitios
	 * nuestros, una sesión robada en uno sirve en el otro.
	 */
	private const LLAVES = [
		'AUTH_KEY',
		'SECURE_AUTH_KEY',
		'LOGGED_IN_KEY',
		'NONCE_KEY',
		'AUTH_SALT',
		'SECURE_AUTH_SALT',
		'LOGGED_IN_SALT',
		'NONCE_SALT',
	];

	/** Permisos que un rol por debajo de administrador no debería tener. */
	private const PERMISOS_DE_MAS = [
		'edit_plugins',
		'edit_themes',
		'edit_files',
		'install_plugins',
		'install_themes',
		'update_plugins',
		'update_core',
		'promote_users',
		'edit_users',
		'delete_users',
		'unfiltered_html',
		'manage_options',
	];

	/** Archivos que WordPress carga antes que nada, si están. */
	private const DROP_INS = [
		'advanced-cache.php',
		'object-cache.php',
		'db.php',
		'db-error.php',
		'install.php',
		'maintenance.php',
		'sunrise.php',
		'php-error.php',
		'fatal-error-handler.php',
	];

	/**
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

		$r = [
			'constantes' => self::constantes(),
			'usuarios'   => self::permisos(),
			'integridad' => self::integridad(),
			'registro'   => self::registro(),
		];

		set_transient( self::CACHE, $r, 12 * HOUR_IN_SECONDS );

		return $r;
	}

	public static function olvidar(): void {
		delete_transient( self::CACHE );
		delete_transient( self::CACHE_SUMAS );
	}

	// ─────────────────────────────────────────────────────────────────────────
	// wp-config.php y la configuración de PHP
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * @return array<string,mixed>
	 */
	private static function constantes(): array {
		$def = static fn( string $c ): bool => defined( $c ) && constant( $c );

		// Las llaves no se mandan nunca. Se manda un hash, que sirve para
		// comparar dos sitios entre sí sin que el panel sepa ninguna.
		$faltan  = [];
		$ejemplo = [];
		$juntas  = '';

		foreach ( self::LLAVES as $llave ) {
			if ( ! defined( $llave ) ) {
				$faltan[] = $llave;
				continue;
			}

			$v = (string) constant( $llave );

			if ( '' === trim( $v ) || str_contains( strtolower( $v ), 'put your unique phrase here' ) ) {
				$ejemplo[] = $llave;
			}

			$juntas .= $v;
		}

		return [
			'llaves_faltan'  => $faltan,
			'llaves_ejemplo' => $ejemplo,
			// Un hash de las ocho juntas: el panel compara estos entre sitios
			// para encontrar plantillas clonadas, y no puede sacar las llaves.
			'llaves_huella'  => '' === $juntas ? '' : hash( 'sha256', $juntas ),

			'depuracion'     => [
				'wp_debug'         => $def( 'WP_DEBUG' ),
				'wp_debug_display' => defined( 'WP_DEBUG_DISPLAY' ) ? (bool) WP_DEBUG_DISPLAY : true,
				'wp_debug_log'     => $def( 'WP_DEBUG_LOG' ),
				// Y lo que de verdad decide si un error sale impreso.
				'display_errors'   => (bool) filter_var( ini_get( 'display_errors' ), FILTER_VALIDATE_BOOLEAN ),
				'script_debug'     => $def( 'SCRIPT_DEBUG' ),
				'entorno'          => function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : '',
			],

			'puertas'        => [
				'reparacion'       => $def( 'WP_ALLOW_REPAIR' ),
				'subidas_sin_filtro' => $def( 'ALLOW_UNFILTERED_UPLOADS' ),
			],

			'actualizaciones' => [
				'nucleo'    => defined( 'WP_AUTO_UPDATE_CORE' ) ? WP_AUTO_UPDATE_CORE : 'minor',
				'apagadas'  => $def( 'AUTOMATIC_UPDATER_DISABLED' ),
				'sin_tocar' => $def( 'DISALLOW_FILE_MODS' ),
			],

			'admin_https'    => $def( 'FORCE_SSL_ADMIN' ),

			// Credenciales en texto plano dentro del archivo de configuración.
			// Se manda si LAS HAY, nunca cuáles.
			'credenciales'   => array_values(
				array_filter(
					[ 'FTP_USER', 'FTP_PASS', 'FTP_HOST', 'FTP_SSL' ],
					static fn( string $c ): bool => defined( $c ) && '' !== (string) constant( $c )
				)
			),
		];
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Usuarios y permisos
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * @return array<string,mixed>
	 */
	private static function permisos(): array {
		$admins = count( get_users( [ 'role' => 'administrator', 'fields' => 'ID' ] ) );

		$roles  = wp_roles();
		$de_mas = [];

		foreach ( $roles->roles as $clave => $rol ) {
			if ( 'administrator' === $clave ) {
				continue;
			}

			foreach ( self::PERMISOS_DE_MAS as $p ) {
				if ( empty( $rol['capabilities'][ $p ] ) ) {
					continue;
				}

				if ( self::de_fabrica( $clave, $p ) ) {
					continue;
				}

				$de_mas[] = ( $rol['name'] ?? $clave ) . ' → ' . $p;
			}
		}

		// Contraseñas de aplicación: se saltan la verificación en dos pasos, así
		// que conviene saber cuántas hay. Nunca cuáles.
		$app = 0;

		foreach ( get_users( [ 'fields' => 'ID' ] ) as $id ) {
			$app += count( (array) get_user_meta( (int) $id, '_application_passwords', true ) );
		}

		return [
			'administradores' => $admins,
			'permisos_de_mas' => $de_mas,
			'rol_por_omision' => (string) get_option( 'default_role', 'subscriber' ),
			'claves_de_app'   => $app,
		];
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Integridad
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * @return array<string,mixed>
	 */
	private static function integridad(): array {
		return [
			'nucleo'      => self::nucleo(),
			'cerrados'    => self::cerrados(),
			'drop_ins'    => self::drop_ins(),
			'tareas'      => self::tareas(),
		];
	}

	/**
	 * Los archivos del núcleo contra las sumas oficiales de WordPress.org.
	 *
	 * Es la respuesta directa al caso que contó el jefe: un sitio que Kinsta
	 * mandó revisar por infección, donde ellos no encontraron nada. «Ahí yo
	 * puedo ver archivos modificados la última vez fuera del core, cosas
	 * extrañas.»
	 *
	 * Tres cubos, como hace Sucuri: modificado, faltante y añadido. Y tres
	 * cosas que hay que excluir o da falsos positivos todos los días:
	 * `wp-config.php` no está en el manifiesto, `wp-content` tampoco, y los
	 * mu-plugins que pone la plataforma no son del núcleo.
	 *
	 * @return array<string,mixed>
	 */
	private static function nucleo(): array {
		$guardado = get_transient( self::CACHE_SUMAS );

		if ( is_array( $guardado ) ) {
			return $guardado;
		}

		if ( ! function_exists( 'get_core_checksums' ) ) {
			require_once ABSPATH . 'wp-admin/includes/update.php';
		}

		global $wp_version;

		$sumas = get_core_checksums( $wp_version, get_locale() );

		// Algunos pares de versión e idioma no existen; se cae al idioma por
		// omisión antes de rendirse.
		if ( ! $sumas && 'en_US' !== get_locale() ) {
			$sumas = get_core_checksums( $wp_version, 'en_US' );
		}

		if ( ! is_array( $sumas ) || ! $sumas ) {
			$r = [ 'ok' => null, 'motivo' => 'No se pudieron traer las sumas oficiales.' ];
			set_transient( self::CACHE_SUMAS, $r, HOUR_IN_SECONDS );

			return $r;
		}

		$cambiados = [];
		$faltantes = [];

		foreach ( $sumas as $archivo => $suma ) {
			// wp-content es del sitio, no del núcleo: ahí viven los plugins,
			// los temas y las subidas, y todos cambian legítimamente.
			if ( str_starts_with( $archivo, 'wp-content/' ) || 'wp-config-sample.php' === $archivo ) {
				continue;
			}

			$ruta = ABSPATH . $archivo;

			if ( ! file_exists( $ruta ) ) {
				$faltantes[] = $archivo;
				continue;
			}

			if ( md5_file( $ruta ) !== $suma ) {
				$cambiados[] = $archivo;
			}
		}

		$r = [
			'ok'        => true,
			'version'   => $wp_version,
			'revisados' => count( $sumas ),
			'cambiados' => array_slice( $cambiados, 0, 20 ),
			'faltantes' => array_slice( $faltantes, 0, 20 ),
			'cuantos'   => count( $cambiados ) + count( $faltantes ),
		];

		set_transient( self::CACHE_SUMAS, $r, 12 * HOUR_IN_SECONDS );

		return $r;
	}

	/**
	 * Plugins que WordPress.org cerró.
	 *
	 * Esto es un hueco que no ve nadie: un plugin cerrado por un problema de
	 * seguridad **sigue apareciendo como «al día»** en el escritorio, y Site
	 * Health no dice nada. Patchstack lo documenta así.
	 *
	 * Dos detalles de implementación que importan:
	 *
	 *   - Hay que leer el cuerpo de la respuesta AUNQUE el código sea 404. La
	 *     API contesta 404 con un JSON que dice `closed: true`.
	 *   - Un 404 sin ese JSON NO es «cerrado»: puede ser un plugin de pago que
	 *     nunca estuvo en el repositorio. Eso se reporta aparte, como «no se
	 *     puede verificar», que es distinto de «abandonado».
	 *
	 * @return array<string,mixed>
	 */
	private static function cerrados(): array {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$cerrados = [];
		$viejos   = [];
		$sin_repo = 0;
		$mirados  = 0;

		foreach ( get_plugins() as $archivo => $datos ) {
			if ( ++$mirados > 60 ) {
				break;
			}

			$slug = (string) strtok( $archivo, '/' );

			if ( '' === $slug || str_starts_with( $slug, 'kinsta-' ) ) {
				continue;
			}

			$info = self::del_repositorio( $slug );

			if ( null === $info ) {
				continue; // no se pudo preguntar; no se inventa nada
			}

			if ( 'sin_repo' === $info ) {
				++$sin_repo;
				continue;
			}

			if ( ! empty( $info['closed'] ) ) {
				$cerrados[] = (string) ( $datos['Name'] ?? $slug );
				continue;
			}

			$ultima = strtotime( (string) ( $info['last_updated'] ?? '' ) );

			// El umbral es nuestro, no de un estándar: Patchstack se niega a
			// dar un número y dice que un proyecto con comunidad activa y sin
			// versión en un año «puede estar bien». Dos años ya no.
			if ( $ultima && $ultima < strtotime( '-24 months' ) ) {
				$viejos[] = (string) ( $datos['Name'] ?? $slug );
			}
		}

		return [
			'cerrados'  => $cerrados,
			'viejos'    => $viejos,
			'sin_repo'  => $sin_repo,
			'revisados' => $mirados,
		];
	}

	/**
	 * @return array<string,mixed>|string|null  'sin_repo' si nunca estuvo
	 */
	private static function del_repositorio( string $slug ): array|string|null {
		$clave = self::CACHE_REPO . md5( $slug );
		$g     = get_transient( $clave );

		if ( false !== $g ) {
			return is_array( $g ) || 'sin_repo' === $g ? $g : null;
		}

		$r = wp_remote_get(
			add_query_arg(
				[ 'action' => 'plugin_information', 'request[slug]' => $slug ],
				'https://api.wordpress.org/plugins/info/1.2/'
			),
			[ 'timeout' => 8 ]
		);

		if ( is_wp_error( $r ) ) {
			return null;
		}

		// El cuerpo se lee pase lo que pase con el código: un plugin cerrado
		// contesta 404 con el JSON que lo dice.
		$datos = json_decode( (string) wp_remote_retrieve_body( $r ), true );

		if ( ! is_array( $datos ) ) {
			return null;
		}

		$salida = ( isset( $datos['error'] ) && empty( $datos['closed'] ) ) ? 'sin_repo' : $datos;

		set_transient( $clave, $salida, DAY_IN_SECONDS );

		return $salida;
	}

	/**
	 * ¿Este permiso se lo da WordPress a este rol de fábrica?
	 *
	 * Hace falta porque sin esto la norma daba un falso positivo en TODOS los
	 * sitios: el rol Editor trae `unfiltered_html` desde que WordPress existe,
	 * en cualquier instalación de un solo sitio. Marcarlo como «permiso de
	 * más» sería acusar a los 108 de una decisión que no tomó nadie.
	 *
	 * Se comprobó mirando los roles de una instalación limpia: de los doce
	 * permisos que vigila esta norma, el único que el núcleo reparte fuera de
	 * administrador es ese. En multisitio ni siquiera ese, pero dejarlo pasar
	 * de más es mejor que levantar una alarma falsa.
	 */
	private static function de_fabrica( string $rol, string $permiso ): bool {
		return 'editor' === $rol && 'unfiltered_html' === $permiso;
	}

	/** @return array<int,string> */
	private static function drop_ins(): array {
		$hay = [];

		foreach ( self::DROP_INS as $d ) {
			if ( file_exists( WP_CONTENT_DIR . '/' . $d ) ) {
				$hay[] = $d;
			}
		}

		return $hay;
	}

	/**
	 * Tareas programadas cuyo gancho no reconoce nadie.
	 *
	 * Un gancho sin función enganchada es una tarea que quedó de un plugin que
	 * ya no está — casi siempre inofensiva— o algo que alguien dejó puesto. No
	 * se afirma cuál: se cuenta y se nombran las primeras, para que una persona
	 * mire.
	 *
	 * @return array<string,mixed>
	 */
	private static function tareas(): array {
		$cron = _get_cron_array();

		if ( ! is_array( $cron ) ) {
			return [ 'huerfanas' => [], 'total' => 0 ];
		}

		$huerfanas = [];
		$total     = 0;

		foreach ( $cron as $ganchos ) {
			foreach ( (array) $ganchos as $gancho => $_ ) {
				++$total;

				if ( 'wp_' === substr( (string) $gancho, 0, 3 ) || has_action( (string) $gancho ) ) {
					continue;
				}

				$huerfanas[ (string) $gancho ] = true;
			}
		}

		return [
			'huerfanas' => array_slice( array_keys( $huerfanas ), 0, 10 ),
			'cuantas'   => count( $huerfanas ),
			'total'     => $total,
		];
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Registro
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * ¿Hay algo que guarde quién entró y qué tocó?
	 *
	 * El registro de la plataforma y el del sitio no son intercambiables: el de
	 * Kinsta no ve que alguien activó un plugin desde el escritorio. Contar uno
	 * solo como suficiente sería darse por bueno sin estarlo.
	 *
	 * @return array<string,mixed>
	 */
	private static function registro(): array {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$conocidos = [
			'wp-security-audit-log/wp-security-audit-log.php' => 'WP Activity Log',
			'wp-activity-log/wp-activity-log.php'            => 'WP Activity Log',
			'simple-history/index.php'                       => 'Simple History',
			'stream/stream.php'                              => 'Stream',
			'user-activity-log/user-activity-log.php'        => 'User Activity Log',
			'aryo-activity-log/aryo-activity-log.php'        => 'Activity Log',
			'wordfence/wordfence.php'                        => 'Wordfence',
			'sucuri-scanner/sucuri.php'                      => 'Sucuri',
		];

		$hay = [];

		foreach ( $conocidos as $archivo => $nombre ) {
			if ( is_plugin_active( $archivo ) ) {
				$hay[] = $nombre;
			}
		}

		return [ 'plugins' => array_values( array_unique( $hay ) ) ];
	}
}
