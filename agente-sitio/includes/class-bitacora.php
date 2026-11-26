<?php
/**
 * El registro de quién entró al sitio y qué tocó.
 *
 * ─── Por qué lo lleva el agente y no un plugin de los de siempre ────────────
 *
 * La norma «el sitio guarda quién entró y qué tocó» la incumplen todos los
 * sitios, y hasta hoy la única forma de cumplirla era instalar un plugin de
 * registro en 108 sitios. Eso es un plugin más que mantener, una superficie
 * más que vigilar y 108 instalaciones que hacer. Lo dijo Fer y tiene razón:
 * «no le estamos instalando nada más, cualquier seguridad corre por nuestra
 * cuenta, y no estamos cargando los sitios de más plugins».
 *
 * ─── Dónde vive lo que se guarda ────────────────────────────────────────────
 *
 * En una tabla propia DENTRO DE CADA SITIO: `wp_swa_bitacora`. No en opciones,
 * que es donde está todo lo demás del agente, porque una opción es una fila
 * que se reescribe entera cada vez y eso no sirve para algo que crece.
 *
 * No se manda todo al panel. Al panel sube un resumen de dos o tres kilobytes
 * al día, pegado al reporte que ya viaja. El detalle se queda en el sitio y
 * solo se pide cuando alguien lo pregunta — y entonces viaja por el mismo
 * camino firmado de siempre, sin abrirle ninguna puerta nueva al sitio.
 *
 * ─── Cuánto ocupa, y hasta cuándo ───────────────────────────────────────────
 *
 * Una fila son unos 250 bytes. Se guardan DOCE MESES, con un tope de 120.000
 * filas: unos 30 MB en el peor caso, que al lado de lo que pesa un WordPress
 * con su galería de imágenes no es nada. Doce y no tres meses porque la
 * pregunta que se le hace a un registro casi siempre es vieja: «¿quién tocó
 * esto?» se pregunta cuando algo ya se rompió, no el mismo día.
 *
 * La limpieza corre una vez al día, de noche, y borra por fecha primero y por
 * tope después.
 *
 * ─── Qué NO se guarda, y es a propósito ─────────────────────────────────────
 *
 *  - Contraseñas. Ni las que se escriben mal al entrar.
 *  - La IP entera: se le quita el último trozo. Sirve igual para ver que
 *    alguien entró desde otro sitio, y deja de ser un dato personal completo.
 *  - Lo que hacen los visitantes: páginas vistas, formularios, pedidos. Eso es
 *    información del cliente y de SUS clientes, y el panel no tiene por qué
 *    tenerla.
 *
 * ─── Lo que esto NO promete ─────────────────────────────────────────────────
 *
 * Si alguien desactiva el agente, el registro deja de escribir. Por eso en el
 * panel esta norma dice «lo lleva el agente» y no «el sitio lo guarda»: son
 * cosas distintas y conviene no confundirlas el día que haga falta.
 * Y de antes de instalarlo no hay nada: no se puede inventar.
 */

defined( 'ABSPATH' ) || exit;

class SWA_Bitacora {

	/** La versión de la tabla. Subirla hace que se cree o se actualice sola. */
	private const VERSION = 1;

	private const OPCION_VERSION = 'swa_bitacora_version';
	private const HOOK_LIMPIEZA  = 'swa_bitacora_limpiar';

	private const DIAS_QUE_SE_GUARDAN = 365;
	private const TOPE_DE_FILAS       = 120000;

	public static function tabla(): string {
		global $wpdb;
		return $wpdb->prefix . 'swa_bitacora';
	}

	/**
	 * ─── Por qué la tabla se crea por VERSIÓN y no al activar ───────────────
	 *
	 * Un plugin tiene un momento de activación donde crear sus tablas. Un tema
	 * NO lo tiene, y este agente va a vivir dentro del tema hijo del jefe. Si
	 * esto se escribiera como el resto del agente, al pasarlo al tema la tabla
	 * no se crearía nunca y el registro no guardaría nada — en silencio, que es
	 * la peor manera de fallar.
	 *
	 * Así que se comprueba un número guardado en cada carga. Comparar dos
	 * enteros en memoria no cuesta nada; solo cuando no coinciden se toca la
	 * base de datos.
	 */
	public static function init(): void {
		self::revisar_tabla();

		add_action( self::HOOK_LIMPIEZA, [ __CLASS__, 'limpiar' ] );

		if ( ! wp_next_scheduled( self::HOOK_LIMPIEZA ) ) {
			wp_schedule_event( strtotime( 'tomorrow 03:30' ), 'daily', self::HOOK_LIMPIEZA );
		}

		self::escuchar();
	}

	public static function revisar_tabla(): void {
		if ( (int) get_option( self::OPCION_VERSION, 0 ) === self::VERSION ) {
			return;
		}

		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$tabla   = self::tabla();
		$collate = $wpdb->get_charset_collate();

		// `ocurrio_en` y `ocurrio_local` son las DOS horas, y eso no es un
		// capricho: el 10 de noviembre un comprador pagó de más porque el
		// precio lo decidía el reloj de su computadora, y lo que permitió
		// probar de quién era la culpa en minutos fue tener las dos marcas de
		// tiempo que comparar. Un registro con una sola hora obliga a adivinar.
		dbDelta(
			"CREATE TABLE {$tabla} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				ocurrio_en DATETIME NOT NULL,
				ocurrio_local DATETIME NOT NULL,
				usuario_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
				usuario_login VARCHAR(80) NOT NULL DEFAULT '',
				rol VARCHAR(60) NOT NULL DEFAULT '',
				accion VARCHAR(40) NOT NULL,
				objeto VARCHAR(40) NOT NULL DEFAULT '',
				objeto_id VARCHAR(191) NOT NULL DEFAULT '',
				ip VARCHAR(45) NOT NULL DEFAULT '',
				detalle TEXT NULL,
				PRIMARY KEY (id),
				KEY cuando (ocurrio_en),
				KEY quien (usuario_id),
				KEY que (accion)
			) {$collate};"
		);

		update_option( self::OPCION_VERSION, self::VERSION, false );
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Escribir
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * El nombre del usuario se guarda ADEMÁS del id, y no es repetirse: el día
	 * que haya que mirar quién borró algo, el usuario puede estar borrado y un
	 * id suelto no dice nada. El rol, igual.
	 *
	 * @param array<string,mixed> $detalle
	 */
	public static function anotar( string $accion, string $objeto = '', string $objeto_id = '', array $detalle = [], ?int $usuario_id = null ): void {
		global $wpdb;

		$uid = null === $usuario_id ? get_current_user_id() : $usuario_id;
		$u   = $uid ? get_userdata( $uid ) : null;

		$wpdb->insert(
			self::tabla(),
			[
				'ocurrio_en'    => gmdate( 'Y-m-d H:i:s' ),
				'ocurrio_local' => current_time( 'mysql' ),
				'usuario_id'    => (int) $uid,
				'usuario_login' => $u ? (string) $u->user_login : '',
				'rol'           => $u && $u->roles ? (string) reset( $u->roles ) : '',
				'accion'        => substr( $accion, 0, 40 ),
				'objeto'        => substr( $objeto, 0, 40 ),
				'objeto_id'     => substr( $objeto_id, 0, 191 ),
				'ip'            => self::ip(),
				'detalle'       => $detalle ? wp_json_encode( $detalle ) : null,
			]
		);
	}

	/**
	 * La IP, recortada.
	 *
	 * Se le quita el último trozo —el último número en IPv4, los últimos
	 * cuatro grupos en IPv6—. Con eso todavía se ve que alguien entró desde
	 * una red distinta de la de siempre, que es para lo que sirve mirar la IP
	 * en un registro de accesos, y deja de ser un dato que identifica a una
	 * persona concreta. Guardar la entera sería recoger más de lo que hace
	 * falta.
	 */
	private static function ip(): string {
		$cruda = '';

		foreach ( [ 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' ] as $clave ) {
			if ( ! empty( $_SERVER[ $clave ] ) ) {
				$cruda = explode( ',', (string) wp_unslash( $_SERVER[ $clave ] ) )[0];
				break;
			}
		}

		$cruda = trim( $cruda );

		if ( '' === $cruda ) {
			return '';
		}

		if ( filter_var( $cruda, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			$p = explode( '.', $cruda );
			return $p[0] . '.' . $p[1] . '.' . $p[2] . '.0';
		}

		if ( filter_var( $cruda, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
			$p = explode( ':', $cruda );
			return implode( ':', array_slice( $p, 0, 4 ) ) . '::';
		}

		return '';
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Qué se escucha
	// ─────────────────────────────────────────────────────────────────────────

	private static function escuchar(): void {
		// Quién entra y quién no consigue entrar. El intento fallido importa
		// tanto como el bueno: cincuenta seguidos son alguien probando claves.
		add_action( 'wp_login', static function ( $login, $usuario ) {
			self::anotar( 'entro', 'usuario', (string) $usuario->ID, [], (int) $usuario->ID );
		}, 10, 2 );

		add_action( 'wp_login_failed', static function ( $login ) {
			// El login se guarda, la contraseña NO. Ni cifrada, ni un trozo.
			self::anotar( 'intento_fallido', 'usuario', '', [ 'login' => substr( (string) $login, 0, 60 ) ], 0 );
		} );

		add_action( 'wp_logout', static function ( $uid ) {
			self::anotar( 'salio', 'usuario', (string) $uid, [], (int) $uid );
		} );

		// Usuarios: es lo que hay que ver cuando un malware se crea un
		// administrador, y es la razón por la que el agente existe.
		add_action( 'user_register', static function ( $uid ) {
			$u = get_userdata( $uid );
			self::anotar( 'usuario_creado', 'usuario', (string) $uid, [
				'login' => $u ? $u->user_login : '',
				'rol'   => $u && $u->roles ? reset( $u->roles ) : '',
			] );
		} );

		add_action( 'delete_user', static function ( $uid ) {
			$u = get_userdata( $uid );
			self::anotar( 'usuario_borrado', 'usuario', (string) $uid, [ 'login' => $u ? $u->user_login : '' ] );
		} );

		add_action( 'set_user_role', static function ( $uid, $rol, $anteriores ) {
			self::anotar( 'rol_cambiado', 'usuario', (string) $uid, [
				'de' => $anteriores ? reset( $anteriores ) : '',
				'a'  => $rol,
			] );
		}, 10, 3 );

		// Plugins y temas: instalar, activar, desactivar, actualizar, borrar.
		add_action( 'activated_plugin', static fn( $p ) => self::anotar( 'plugin_activado', 'plugin', (string) $p ) );
		add_action( 'deactivated_plugin', static fn( $p ) => self::anotar( 'plugin_desactivado', 'plugin', (string) $p ) );
		add_action( 'switch_theme', static fn( $nuevo ) => self::anotar( 'tema_cambiado', 'tema', (string) $nuevo ) );

		add_action( 'upgrader_process_complete', static function ( $upgrader, $extra ) {
			$tipo = (string) ( $extra['type'] ?? '' );

			if ( ! in_array( $tipo, [ 'plugin', 'theme', 'core' ], true ) ) {
				return;
			}

			$cuales = (array) ( $extra['plugins'] ?? $extra['themes'] ?? [] );

			self::anotar(
				'core' === $tipo ? 'wordpress_actualizado' : $tipo . '_actualizado',
				$tipo,
				implode( ', ', array_map( 'strval', array_slice( $cuales, 0, 5 ) ) ),
				[ 'accion' => (string) ( $extra['action'] ?? '' ) ]
			);
		}, 10, 2 );

		add_action( 'deleted_plugin', static fn( $p ) => self::anotar( 'plugin_borrado', 'plugin', (string) $p ), 10, 1 );

		// Contenido: publicar, editar y mandar a la papelera. No se guarda el
		// contenido, solo qué entrada y quién.
		add_action( 'transition_post_status', static function ( $nuevo, $viejo, $post ) {
			if ( $nuevo === $viejo || 'auto-draft' === $nuevo || 'inherit' === $post->post_type ) {
				return;
			}

			if ( in_array( $post->post_type, [ 'revision', 'nav_menu_item', 'customize_changeset', 'oembed_cache' ], true ) ) {
				return;
			}

			// Los pedidos de la tienda NO entran: son datos de los clientes del
			// cliente, y el panel no tiene por qué tenerlos.
			if ( str_starts_with( (string) $post->post_type, 'shop_order' ) ) {
				return;
			}

			self::anotar( 'contenido_' . $nuevo, (string) $post->post_type, (string) $post->ID, [
				'titulo' => substr( (string) $post->post_title, 0, 120 ),
				'de'     => (string) $viejo,
			] );
		}, 10, 3 );

		// Ajustes que importan: que alguien pueda registrarse solo, y el rol
		// que le toca al registrarse. Las dos son normas de seguridad.
		foreach ( [ 'users_can_register', 'default_role', 'siteurl', 'home', 'admin_email' ] as $opcion ) {
			add_action( "update_option_{$opcion}", static function ( $viejo, $nuevo ) use ( $opcion ) {
				self::anotar( 'ajuste_cambiado', 'opcion', $opcion, [
					'de' => substr( (string) ( is_scalar( $viejo ) ? $viejo : '' ), 0, 120 ),
					'a'  => substr( (string) ( is_scalar( $nuevo ) ? $nuevo : '' ), 0, 120 ),
				] );
			}, 10, 2 );
		}
	}

	/**
	 * Lo que hace el propio agente, anotado como lo de cualquiera.
	 *
	 * Si el panel manda apagar el editor de archivos, eso tiene que quedar
	 * escrito igual que si lo hubiera hecho una persona: un registro donde las
	 * acciones automáticas no aparecen miente por omisión.
	 */
	public static function anotar_orden( string $tipo, bool $salio_bien, string $porque = '' ): void {
		self::anotar(
			'orden_del_panel',
			'orden',
			$tipo,
			[ 'resultado' => $salio_bien ? 'hecha' : 'falló', 'porque' => substr( $porque, 0, 160 ) ],
			0
		);
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Leer
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * El resumen que viaja con el reporte diario. Dos o tres kilobytes.
	 *
	 * No son las filas: son las cuentas. Cuántas entradas, cuántos intentos
	 * fallidos, quién entró, qué se tocó. Con eso el panel puede avisar de algo
	 * raro sin tener que guardarse el historial de 108 sitios.
	 *
	 * @return array<string,mixed>
	 */
	public static function resumen( int $horas = 24 ): array {
		global $wpdb;

		$tabla = self::tabla();
		$desde = gmdate( 'Y-m-d H:i:s', time() - $horas * HOUR_IN_SECONDS );

		if ( ! self::hay_tabla() ) {
			return [ 'hay_tabla' => false ];
		}

		$por_accion = [];
		foreach ( (array) $wpdb->get_results(
			$wpdb->prepare( "SELECT accion, COUNT(*) n FROM {$tabla} WHERE ocurrio_en >= %s GROUP BY accion", $desde )
		) as $f ) {
			$por_accion[ (string) $f->accion ] = (int) $f->n;
		}

		$quienes = [];
		foreach ( (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT usuario_login, COUNT(*) n FROM {$tabla}
				  WHERE ocurrio_en >= %s AND usuario_login <> '' GROUP BY usuario_login ORDER BY n DESC LIMIT 10",
				$desde
			)
		) as $f ) {
			$quienes[ (string) $f->usuario_login ] = (int) $f->n;
		}

		return [
			'hay_tabla'   => true,
			'desde'       => $desde,
			'filas'       => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tabla} WHERE ocurrio_en >= %s", $desde ) ),
			'por_accion'  => $por_accion,
			'quienes'     => $quienes,
			'total'       => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tabla}" ),
			'mas_vieja'   => (string) $wpdb->get_var( "SELECT MIN(ocurrio_en) FROM {$tabla}" ),
			'dias_que_guarda' => self::DIAS_QUE_SE_GUARDAN,
		];
	}

	/**
	 * El detalle, cuando el panel lo pide con el verbo `traer_bitacora`.
	 *
	 * Con tope, porque una petición sin tope de un sitio con mucho movimiento
	 * puede ser un envío de varios megas por una respuesta HTTP.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function detalle( string $desde = '', string $hasta = '', int $tope = 500 ): array {
		global $wpdb;

		if ( ! self::hay_tabla() ) {
			return [];
		}

		$tabla = self::tabla();
		$tope  = max( 1, min( 2000, $tope ) );

		$desde = $desde ?: gmdate( 'Y-m-d H:i:s', time() - 7 * DAY_IN_SECONDS );
		$hasta = $hasta ?: gmdate( 'Y-m-d H:i:s' );

		$filas = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ocurrio_en, ocurrio_local, usuario_login, rol, accion, objeto, objeto_id, ip, detalle
				   FROM {$tabla}
				  WHERE ocurrio_en BETWEEN %s AND %s
			   ORDER BY ocurrio_en DESC
				  LIMIT %d",
				$desde,
				$hasta,
				$tope
			),
			ARRAY_A
		);

		return (array) $filas;
	}

	public static function hay_tabla(): bool {
		global $wpdb;
		$t = self::tabla();
		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) ) === $t;
	}

	/**
	 * La limpieza: por fecha primero, por tope después.
	 *
	 * Las dos y no una: la fecha es la regla que importa, pero un sitio con un
	 * ataque de fuerza bruta puede escribir cien mil filas en un día y ahí la
	 * fecha no frena nada.
	 */
	public static function limpiar(): void {
		global $wpdb;

		if ( ! self::hay_tabla() ) {
			return;
		}

		$tabla = self::tabla();

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$tabla} WHERE ocurrio_en < %s",
				gmdate( 'Y-m-d H:i:s', time() - self::DIAS_QUE_SE_GUARDAN * DAY_IN_SECONDS )
			)
		);

		$cuantas = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tabla}" );

		if ( $cuantas > self::TOPE_DE_FILAS ) {
			$corte = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$tabla} ORDER BY id DESC LIMIT 1 OFFSET %d",
					self::TOPE_DE_FILAS
				)
			);

			if ( $corte ) {
				$wpdb->query( $wpdb->prepare( "DELETE FROM {$tabla} WHERE id <= %d", $corte ) );
			}
		}
	}

	public static function desprogramar(): void {
		$t = wp_next_scheduled( self::HOOK_LIMPIEZA );

		if ( $t ) {
			wp_unschedule_event( $t, self::HOOK_LIMPIEZA );
		}
	}
}
