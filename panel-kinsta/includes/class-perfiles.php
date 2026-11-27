<?php
/**
 * Perfiles de acceso al panel.
 *
 *   "Darles un panel al área de ventas y un panel al área de soporte que tenga
 *    esa misma información. Que registre cuándo se loguearon, cuándo se
 *    deslogearon. Que tenga doble seguridad, pida el código por correo, todo
 *    eso." — 08:17
 *
 * ─── Por qué roles propios y no los de WordPress ────────────────────────────
 *
 * Porque si una asesora necesitara ser "administradora" de WordPress para ver
 * su panel, tendría acceso a instalar plugins, editar código y tocar el sitio
 * donde vive el panel — que es justo el sitio desde el cual se consulta la
 * información de todos los clientes. Un rol propio le da exactamente lo que
 * necesita y nada más.
 *
 * ─── Los tres perfiles ──────────────────────────────────────────────────────
 *
 *   Dirección  Todo. Ajustes, llaves, registro de accesos.
 *   Ventas     Solo sus sitios asignados. Lo que necesita para vender: qué
 *              actualizar, por qué importa y qué se le ha avisado al cliente.
 *              No ve ajustes ni llaves.
 *   Soporte    Todos los sitios, pero del lado técnico: caídas, vulnerabilidades,
 *              estado de los agentes. No ve ajustes ni llaves.
 *
 * Que ventas vea solo lo suyo no es desconfianza: es que un panel con 107
 * sitios donde solo 12 son los tuyos no se usa. El recorte es lo que lo hace
 * útil.
 */

defined( 'ABSPATH' ) || exit;

class SWPK_Perfiles {

	/** Marca de usuario: esta persona entra en el reparto automático de la cartera. */
	public const META_REPARTO = 'swpk_asesora_cartera';

	public const DIRECCION = 'swpk_direccion';
	public const VENTAS    = 'swpk_ventas';
	public const SOPORTE   = 'swpk_soporte';

	/**
	 * El cuarto perfil no es para una persona: es para un programa.
	 *
	 * Lee todo y no puede hacer nada más. No dispara procesos, no toca ajustes,
	 * no ve el registro de accesos. El jefe pidió que las consultas tuvieran
	 * API; esto es la llave con la que se consulta, y su límite es el punto:
	 * una llave que vive dentro de otro sistema se puede filtrar, y lo que se
	 * filtre no debe poder cambiar nada.
	 */
	public const API = 'swpk_api';

	/** La marca que distingue una llave de una persona en pantalla. */
	public const ES_LLAVE = 'swpk_es_llave';

	// Permisos
	public const VER          = 'swpk_ver_panel';
	public const VER_TODO     = 'swpk_ver_todos_los_sitios';
	public const VER_TECNICO  = 'swpk_ver_tecnico';
	public const VER_BITACORA = 'swpk_ver_bitacora';
	public const VER_ACCESOS  = 'swpk_ver_accesos';
	public const ADMINISTRAR  = 'swpk_administrar_panel';

	/**
	 * Los permisos cambian al salir una versión nueva del plugin, pero
	 * crear_roles() solo corre al activarlo. Sin esto, un panel que ya estaba
	 * instalado se quedaría sin el perfil nuevo hasta que alguien desactive y
	 * vuelva a activar el plugin — y nadie va a hacer eso.
	 */
	public const VERSION_ROLES = 2;

	public static function roles(): array {
		return [
			self::DIRECCION => 'Panel · Dirección',
			self::VENTAS    => 'Panel · Ventas',
			self::SOPORTE   => 'Panel · Soporte',
			self::API       => 'Panel · Llave de consulta (API)',
		];
	}

	public static function revisar_roles(): void {
		if ( (int) get_option( 'swpk_version_roles', 0 ) === self::VERSION_ROLES ) {
			return;
		}

		self::crear_roles();
		update_option( 'swpk_version_roles', self::VERSION_ROLES, false );
	}

	/** @return array<string,array<int,string>> */
	private static function permisos(): array {
		return [
			self::DIRECCION => [ self::VER, self::VER_TODO, self::VER_TECNICO, self::VER_BITACORA, self::VER_ACCESOS, self::ADMINISTRAR ],
			self::VENTAS    => [ self::VER, self::VER_BITACORA ],
			self::SOPORTE   => [ self::VER, self::VER_TODO, self::VER_TECNICO, self::VER_BITACORA ],
			// Todo lo que es leer, nada de lo que es obrar. Sin VER_ACCESOS:
			// quién entra al panel no es asunto de un sistema de fuera.
			self::API       => [ self::VER, self::VER_TODO, self::VER_TECNICO, self::VER_BITACORA, self::ES_LLAVE ],
		];
	}

	public static function crear_roles(): void {
		foreach ( self::permisos() as $rol => $permisos ) {
			$caps = [ 'read' => true ];
			foreach ( $permisos as $p ) {
				$caps[ $p ] = true;
			}

			// remove_role antes de add_role para que un cambio de permisos en
			// una versión nueva del plugin sí se aplique. add_role no hace nada
			// si el rol ya existe, y eso deja permisos viejos pegados.
			remove_role( $rol );
			add_role( $rol, self::roles()[ $rol ], $caps );
		}

		// El administrador de WordPress ve todo, o quien instala el plugin se
		// quedaría afuera de su propio panel.
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			foreach ( [ self::VER, self::VER_TODO, self::VER_TECNICO, self::VER_BITACORA, self::VER_ACCESOS, self::ADMINISTRAR ] as $p ) {
				$admin->add_cap( $p );
			}
		}
	}

	public static function quitar_roles(): void {
		foreach ( array_keys( self::roles() ) as $rol ) {
			remove_role( $rol );
		}
	}

	/** Qué ve y qué no ve cada perfil, en palabras, para enseñarlo en pantalla. */
	public static function descripciones(): array {
		return [
			self::DIRECCION => [
				've'    => [ 'Todos los sitios', 'Vulnerabilidades y pendientes', 'Caídas', 'Historial de envíos', 'Registro de accesos', 'Ajustes y llaves' ],
				'no_ve' => [],
			],
			self::VENTAS => [
				've'    => [ 'Solo los sitios que tiene asignados', 'Qué actualizar en ellos y por qué importa', 'El texto listo para cotizar', 'Los avisos que se le mandaron a esos clientes' ],
				'no_ve' => [ 'Los sitios de otras asesoras', 'Caídas', 'Registro de accesos', 'Ajustes y llaves' ],
			],
			self::SOPORTE => [
				've'    => [ 'Todos los sitios', 'Vulnerabilidades y pendientes', 'Caídas y recuperaciones', 'Historial de envíos' ],
				'no_ve' => [ 'Registro de accesos', 'Ajustes y llaves' ],
			],
			self::API => [
				've'    => [ 'Todos los sitios, por consulta', 'Vulnerabilidades y pendientes', 'Quién tiene acceso a cada sitio', 'Movimientos y bitácora' ],
				'no_ve' => [ 'Registro de accesos', 'Ajustes y llaves' ],
				'no_hace' => [ 'Disparar la corrida', 'Analizar un sitio', 'Sondear caídas', 'Escribir cualquier cosa' ],
			],
		];
	}

	/**
	 * Deja el escritorio de WordPress lo más desnudo posible para quien solo
	 * entra a ver el panel.
	 *
	 * Una asesora no tiene por qué toparse con Entradas, Medios ni Herramientas:
	 * no puede usarlos y solo estorban. Y al entrar, que caiga directo en el
	 * panel en vez de en un escritorio de WordPress que no le dice nada.
	 */
	public static function init(): void {
		add_action( 'admin_menu', [ __CLASS__, 'limpiar_menu' ], 999 );
		add_filter( 'login_redirect', [ __CLASS__, 'a_donde_entra' ], 10, 3 );
		add_action( 'admin_bar_menu', [ __CLASS__, 'limpiar_barra' ], 999 );
	}

	/** ¿Esta persona solo viene al panel? */
	public static function es_solo_panel(): bool {
		return current_user_can( self::VER ) && ! current_user_can( 'manage_options' );
	}

	public static function limpiar_menu(): void {
		if ( ! self::es_solo_panel() ) {
			return;
		}

		foreach ( [ 'index.php', 'edit.php', 'upload.php', 'edit.php?post_type=page', 'edit-comments.php', 'tools.php', 'profile.php' ] as $pagina ) {
			remove_menu_page( $pagina );
		}
	}

	public static function limpiar_barra( WP_Admin_Bar $barra ): void {
		if ( ! self::es_solo_panel() ) {
			return;
		}

		foreach ( [ 'wp-logo', 'comments', 'new-content', 'dashboard' ] as $nodo ) {
			$barra->remove_node( $nodo );
		}
	}

	public static function a_donde_entra( string $destino, string $pedido, $usuario ): string {
		if ( $usuario instanceof WP_User && user_can( $usuario, self::VER ) && ! user_can( $usuario, 'manage_options' ) ) {
			return admin_url( 'admin.php?page=' . SWPK_Admin::SLUG );
		}

		return $destino;
	}

	/**
	 * Crea un acceso al panel.
	 *
	 * No se inventa una contraseña ni se manda por correo en texto: se crea la
	 * cuenta y se genera el enlace de WordPress para que la persona ponga la
	 * suya. Así la contraseña no pasa por ningún lado donde quede escrita.
	 *
	 * @return array{usuario:WP_User,enlace:string}|WP_Error
	 */
	public static function crear_acceso( string $nombre, string $correo, string $perfil ): array|WP_Error {
		$correo = sanitize_email( $correo );
		$nombre = sanitize_text_field( $nombre );

		if ( ! is_email( $correo ) ) {
			return new WP_Error( 'swpk_correo', 'Ese correo no es válido.' );
		}

		if ( email_exists( $correo ) ) {
			return new WP_Error( 'swpk_existe', 'Ya hay una cuenta con ese correo.' );
		}

		if ( ! isset( self::roles()[ $perfil ] ) ) {
			return new WP_Error( 'swpk_perfil', 'Ese perfil no existe.' );
		}

		$login = sanitize_user( current( explode( '@', $correo ) ), true );
		$base  = $login;
		$i     = 2;
		while ( username_exists( $login ) ) {
			$login = $base . $i;
			++$i;
		}

		$id = wp_insert_user(
			[
				'user_login'   => $login,
				'user_email'   => $correo,
				'user_pass'    => wp_generate_password( 32, true, true ),
				'display_name' => '' !== $nombre ? $nombre : $login,
				'role'         => $perfil,
			]
		);

		if ( is_wp_error( $id ) ) {
			return $id;
		}

		$usuario = get_user_by( 'id', $id );
		$clave   = get_password_reset_key( $usuario );

		$enlace = is_wp_error( $clave )
			? ''
			: network_site_url( 'wp-login.php?action=rp&key=' . $clave . '&login=' . rawurlencode( $usuario->user_login ), 'login' );

		return [ 'usuario' => $usuario, 'enlace' => $enlace ];
	}

	/** @return array<int,WP_User> */
	public static function del_equipo(): array {
		return get_users( [ 'role__in' => array_keys( self::roles() ), 'orderby' => 'display_name' ] );
	}

	public static function puede( string $permiso ): bool {
		return current_user_can( $permiso ) || current_user_can( 'manage_options' );
	}

	/** El perfil del usuario actual, para mostrarlo y para decidir qué enseñar. */
	public static function perfil_actual(): string {
		// Primero la llave: tiene los permisos de soporte y se vería como
		// soporte, que no es lo que es.
		if ( current_user_can( self::ES_LLAVE ) ) {
			return self::API;
		}
		if ( self::puede( self::ADMINISTRAR ) ) {
			return self::DIRECCION;
		}
		if ( current_user_can( self::VER_TECNICO ) ) {
			return self::SOPORTE;
		}
		if ( current_user_can( self::VER ) ) {
			return self::VENTAS;
		}
		return '';
	}

	public static function nombre_perfil(): string {
		return self::roles()[ self::perfil_actual() ] ?? 'Sin perfil';
	}

	/**
	 * Los sitios que el usuario actual puede ver.
	 *
	 * @return array<int,object>
	 */
	public static function sitios_visibles(): array {
		$todos = SWPK_Inventario::todos();

		if ( self::puede( self::VER_TODO ) ) {
			return $todos;
		}

		// Por la tabla de asesoras: si un sitio lo llevan dos, las dos lo
		// ven. Con la columna `asesora_id` solo lo veía la primera.
		$mios = self::sitios_de_asesora( get_current_user_id() );

		return array_values(
			array_filter( $todos, static fn( $s ) => in_array( (string) $s->sitio_id, $mios, true ) )
		);
	}

	public static function puede_ver_sitio( string $sitio_id ): bool {
		if ( self::puede( self::VER_TODO ) ) {
			return true;
		}

		foreach ( self::sitios_visibles() as $s ) {
			if ( (string) $s->sitio_id === $sitio_id ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Las asesoras a las que se les puede asignar un sitio.
	 *
	 * Solo la gente de nuestro Equipo. Antes entraban también los
	 * `administrator` del WordPress donde vive el panel, y eso llenaba el
	 * desplegable con cuentas que no son del equipo: en solucionweb.com, los
	 * administradores del sitio de la empresa. Quien asigna un sitio tiene que
	 * ver a sus compañeras, no la lista de usuarios de otro sitio.
	 *
	 * La llave de la API queda fuera a propósito: es una llave, no una persona,
	 * y no se le puede asignar un cliente.
	 */
	public static function asesoras(): array {
		return get_users(
			[
				'role__in' => [ self::DIRECCION, self::VENTAS, self::SOPORTE ],
				'orderby'  => 'display_name',
			]
		);
	}

	public static function asignar( string $sitio_id, int $usuario_id, string $contacto = '' ): void {
		self::asignar_asesoras( $sitio_id, $usuario_id ? [ $usuario_id ] : [], $contacto );
	}

	/**
	 * Las asesoras de un sitio. Varias, no una.
	 *
	 * ─── Por qué varias ─────────────────────────────────────────────────────
	 *
	 * «Que tienen que ser las 2 asesoras (...) que no sea solo un select de
	 * una persona.» Un sitio lo llevan dos, y con una sola columna había que
	 * elegir a cuál avisarle.
	 *
	 * @return array<int,int> Los ids, en el orden en que se asignaron.
	 */
	public static function asesoras_de( string $sitio_id ): array {
		global $wpdb;

		return array_map(
			'intval',
			$wpdb->get_col(
				$wpdb->prepare(
					'SELECT usuario_id FROM ' . SWPK_Tablas::tabla( 'sitio_asesoras' )
					. ' WHERE sitio_id = %s ORDER BY asignada_en, usuario_id',
					$sitio_id
				)
			)
		);
	}

	/**
	 * Los sitios de una asesora.
	 *
	 * @return array<int,string> Los sitio_id.
	 */
	public static function sitios_de_asesora( int $usuario_id ): array {
		global $wpdb;

		return array_map(
			'strval',
			$wpdb->get_col(
				$wpdb->prepare(
					'SELECT sitio_id FROM ' . SWPK_Tablas::tabla( 'sitio_asesoras' ) . ' WHERE usuario_id = %d',
					$usuario_id
				)
			)
		);
	}

	/**
	 * Cambia la lista completa de asesoras de un sitio.
	 *
	 * ─── La regla de a qué sitios se les puede asignar ──────────────────────
	 *
	 * Solo a los nuestros: auto_update y desarrollo. A los 13 del cliente
	 * —etiqueta no_update— no se les asigna nadie, y eso es a propósito: «en
	 * los sitios no auto update (...) todavía no les debemos de enviar nada».
	 *
	 * Y la forma de que no les llegue nada NO es apagar la lista de correos,
	 * que es lo que se había entendido: «no hay que dejar apagada la lista,
	 * hay que dejarla encendida, simplemente hay que dejar deseleccionada a
	 * las personas y no les debe de llegar». El aviso semanal sigue
	 * encendido; lo que pasa es que esos sitios no tienen a nadie asignado,
	 * así que no entran en el correo de nadie.
	 *
	 * Esto vive en el código y no en la memoria de quien asigna: si mañana
	 * alguien abre un sitio no_update y escoge dos nombres, el panel no los
	 * guarda y lo dice.
	 *
	 * @param array<int,int|string> $usuarios Los ids que quedan asignados.
	 * @return array{guardadas:int,rechazado:string}
	 */
	/**
	 * ─── Quién entra en el reparto automático ───────────────────────────────
	 *
	 * No todos los que tienen perfil de asesora llevan cartera. En producción
	 * son cuatro —Tiffany, Alejandra, Luisa y Fer— y el jefe nombró a dos:
	 * «también van a Ale, deberían ser las dos, ellas dos como asesoras»
	 * (03:45). Luisa está para las caídas y Fer lleva el panel.
	 *
	 * Repartir «todas las que tengan el perfil» les habría puesto 96 sitios a
	 * las cuatro, y con eso el correo semanal a dos personas que no lo
	 * esperan. Así que es una marca por persona, que se pone en Equipo, y no
	 * una deducción del perfil.
	 *
	 * Sin nadie marcado no se reparte nada: es más seguro no hacer nada que
	 * adivinar a quién le toca la cartera.
	 *
	 * @return array<int,int>
	 */
	public static function las_del_reparto(): array {
		$ids = [];

		foreach ( self::asesoras() as $u ) {
			if ( get_user_meta( (int) $u->ID, self::META_REPARTO, true ) ) {
				$ids[] = (int) $u->ID;
			}
		}

		return $ids;
	}

	public static function entra_en_el_reparto( int $usuario_id ): bool {
		return (bool) get_user_meta( $usuario_id, self::META_REPARTO, true );
	}

	public static function marcar_para_el_reparto( int $usuario_id, bool $si ): void {
		if ( $si ) {
			update_user_meta( $usuario_id, self::META_REPARTO, 1 );
		} else {
			delete_user_meta( $usuario_id, self::META_REPARTO );
		}
	}

	/**
	 * ─── La regla de reparto, aplicada a toda la cartera ────────────────────
	 *
	 * «Poné a las 2 asesoras en todos los sitios de auto update, y a los NO
	 * auto update, quitalas.»
	 *
	 * Lo mismo que dijo el jefe en el 03:45: «ahorita todos van a Tiffany,
	 * incluso los auto update y desarrollos, todos van a Tiffany… y también
	 * van a Ale, deberían ser las dos, ellas dos como asesoras».
	 *
	 * Cubre los dos grupos NUESTROS —auto update y desarrollo—, que es lo que
	 * decide es_nuestro() y lo que él nombró. Los del cliente se quedan sin
	 * nadie: asignar_asesoras() ya los rechaza, así que tenerlos asignados era
	 * un estado que la propia pantalla no deja crear.
	 *
	 * No pisa lo que alguien haya puesto a mano: un sitio nuestro que YA tiene
	 * asesoras se queda como está. Solo rellena los vacíos y vacía los del
	 * cliente. Así se puede correr en cada sincronización sin deshacer el
	 * trabajo de nadie.
	 *
	 * @return array{puestas:int,quitadas:int,ya_tenian:int}
	 */
	public static function repartir_asesoras_por_defecto(): array {
		global $wpdb;

		$tabla = SWPK_Tablas::tabla( 'sitio_asesoras' );
		$todas = self::las_del_reparto();

		$cuenta = [ 'puestas' => 0, 'quitadas' => 0, 'ya_tenian' => 0 ];

		if ( ! $todas ) {
			return $cuenta;
		}

		$sitios = $wpdb->get_results(
			'SELECT sitio_id, tag, grupo_a_mano FROM ' . SWPK_Tablas::tabla( 'sitios' ) . ' WHERE de_baja = 0'
		) ?: [];

		$ahora = current_time( 'mysql' );
		$quien = get_current_user_id();

		foreach ( $sitios as $s ) {
			// El grupo corregido a mano manda sobre la etiqueta del hosting,
			// igual que en todas las demás pantallas.
			$grupo = '' !== (string) $s->grupo_a_mano ? (string) $s->grupo_a_mano : (string) $s->tag;
			$sid   = (string) $s->sitio_id;

			$tiene = (int) $wpdb->get_var(
				$wpdb->prepare( "SELECT COUNT(*) FROM {$tabla} WHERE sitio_id = %s", $sid )
			);

			if ( ! SWPK_Etiquetas::es_nuestro( $grupo ) ) {
				if ( $tiene ) {
					$wpdb->delete( $tabla, [ 'sitio_id' => $sid ] );
					$wpdb->update(
						SWPK_Tablas::tabla( 'sitios' ),
						[ 'asesora_id' => 0 ],
						[ 'sitio_id' => $sid ]
					);
					++$cuenta['quitadas'];
				}
				continue;
			}

			if ( $tiene ) {
				++$cuenta['ya_tenian'];
				continue;
			}

			foreach ( $todas as $id ) {
				$wpdb->insert(
					$tabla,
					[
						'sitio_id'     => $sid,
						'usuario_id'   => $id,
						'asignada_en'  => $ahora,
						'asignada_por' => $quien,
					]
				);
			}

			$wpdb->update(
				SWPK_Tablas::tabla( 'sitios' ),
				[ 'asesora_id' => $todas[0] ],
				[ 'sitio_id' => $sid ]
			);
			++$cuenta['puestas'];
		}

		return $cuenta;
	}

	public static function asignar_asesoras( string $sitio_id, array $usuarios, ?string $contacto = null ): array {
		global $wpdb;

		$tabla = SWPK_Tablas::tabla( 'sitio_asesoras' );

		$sitio = SWPK_Inventario::obtener( $sitio_id );
		$tag   = $sitio ? (string) $sitio->tag : '';

		if ( $sitio && ! SWPK_Etiquetas::es_nuestro( $tag ) && $usuarios ) {
			return [
				'guardadas' => 0,
				'rechazado' => 'A este sitio no se le asignan asesoras: está etiquetado como '
					. ( SWPK_Etiquetas::nombres()[ $tag ] ?? $tag )
					. ', y a esos todavía no se les avisa nada.',
			];
		}

		// Solo gente del equipo. Un id que no está en la lista de asesoras se
		// descarta en silencio: puede venir de un formulario viejo o de una
		// cuenta que se borró desde que se dibujó la pantalla.
		$validas = array_map( static fn( $u ) => (int) $u->ID, self::asesoras() );
		$ids     = array_values( array_unique( array_intersect( array_map( 'intval', $usuarios ), $validas ) ) );

		$wpdb->delete( $tabla, [ 'sitio_id' => $sitio_id ] );

		$ahora = current_time( 'mysql' );
		$quien = get_current_user_id();

		foreach ( $ids as $id ) {
			$wpdb->insert(
				$tabla,
				[
					'sitio_id'     => $sitio_id,
					'usuario_id'   => $id,
					'asignada_en'  => $ahora,
					'asignada_por' => $quien,
				]
			);
		}

		// `asesora_id` se sigue escribiendo con la primera, para las pantallas
		// y la caché que todavía la leen. Ver el comentario de la tabla.
		$cambios = [ 'asesora_id' => $ids[0] ?? 0 ];

		if ( null !== $contacto ) {
			$cambios['contacto_cliente'] = $contacto;
		}

		$wpdb->update( SWPK_Tablas::tabla( 'sitios' ), $cambios, [ 'sitio_id' => $sitio_id ] );

		return [ 'guardadas' => count( $ids ), 'rechazado' => '' ];
	}
}
