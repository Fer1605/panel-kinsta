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

	public const DIRECCION = 'swpk_direccion';
	public const VENTAS    = 'swpk_ventas';
	public const SOPORTE   = 'swpk_soporte';

	// Permisos
	public const VER          = 'swpk_ver_panel';
	public const VER_TODO     = 'swpk_ver_todos_los_sitios';
	public const VER_TECNICO  = 'swpk_ver_tecnico';
	public const VER_BITACORA = 'swpk_ver_bitacora';
	public const VER_ACCESOS  = 'swpk_ver_accesos';
	public const ADMINISTRAR  = 'swpk_administrar_panel';

	public static function roles(): array {
		return [
			self::DIRECCION => 'Panel · Dirección',
			self::VENTAS    => 'Panel · Ventas',
			self::SOPORTE   => 'Panel · Soporte',
		];
	}

	/** @return array<string,array<int,string>> */
	private static function permisos(): array {
		return [
			self::DIRECCION => [ self::VER, self::VER_TODO, self::VER_TECNICO, self::VER_BITACORA, self::VER_ACCESOS, self::ADMINISTRAR ],
			self::VENTAS    => [ self::VER, self::VER_BITACORA ],
			self::SOPORTE   => [ self::VER, self::VER_TODO, self::VER_TECNICO, self::VER_BITACORA ],
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

	public static function puede( string $permiso ): bool {
		return current_user_can( $permiso ) || current_user_can( 'manage_options' );
	}

	/** El perfil del usuario actual, para mostrarlo y para decidir qué enseñar. */
	public static function perfil_actual(): string {
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

		$yo = get_current_user_id();

		return array_values(
			array_filter( $todos, static fn( $s ) => (int) $s->asesora_id === $yo )
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

	/** Las asesoras a las que se les puede asignar un sitio. */
	public static function asesoras(): array {
		return get_users(
			[
				'role__in' => [ self::VENTAS, self::DIRECCION, 'administrator' ],
				'orderby'  => 'display_name',
			]
		);
	}

	public static function asignar( string $sitio_id, int $usuario_id, string $contacto = '' ): void {
		global $wpdb;

		$wpdb->update(
			SWPK_Tablas::tabla( 'sitios' ),
			[ 'asesora_id' => $usuario_id, 'contacto_cliente' => $contacto ],
			[ 'sitio_id' => $sitio_id ]
		);
	}
}
