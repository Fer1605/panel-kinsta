<?php
/**
 * Las llaves de consulta: crearlas, revocarlas y mantenerlas lejos de las personas.
 *
 * ─── Por qué este archivo existe aparte ─────────────────────────────────────
 *
 * Una llave es, por dentro, una cuenta de WordPress. Tiene que serlo: la
 * autenticación básica necesita un usuario, y todo el recorte del panel —que
 * una llave de ventas vea solo sus sitios— cuelga de que haya una identidad.
 * Montarlo sobre otra cosa habría significado escribir un segundo sistema de
 * permisos, y entonces el panel y la API podrían contradecirse.
 *
 * Pero una cuenta de WordPress sale en Usuarios, y ahí empieza el problema que
 * Fer señaló y que tenía razón en señalar:
 *
 *   "si yo entro a editarlos ahí, les puedo cambiar la contraseña. Y entonces
 *    alguien me puede quitar los accesos (...) cualquiera puede tener mi
 *    usuario y la contraseña la cambia y ya puede entrar"
 *
 * Una llave tirada en la lista de usuarios se puede editar, se le puede poner
 * una contraseña conocida, se le puede subir el rol a administrador, o se puede
 * borrar por limpieza sin saber qué se está rompiendo. Nada de eso debería
 * poder pasar desde una pantalla que existe para dar de alta personas.
 *
 * ─── Qué hace este archivo, entonces ────────────────────────────────────────
 *
 * Las saca de ahí y les pone una reja de cuatro barrotes:
 *
 *   1. No salen en Usuarios, ni en los filtros de arriba. Su lista es la
 *      pantalla "Conectarse", que es donde significan algo.
 *   2. Nadie las edita, las borra ni las asciende: ni un administrador. Las
 *      únicas dos cosas que se les pueden hacer son crearlas y revocarlas, y
 *      las dos están en el panel.
 *   3. No entran por el formulario de WordPress. Una llave consulta la API; no
 *      se sienta en el escritorio.
 *   4. No se les puede pedir "recuperar contraseña".
 *
 * ─── Hasta dónde llega esto, dicho sin adornos ──────────────────────────────
 *
 * Quien pueda instalar un plugin o entrar a la base de datos puede deshacer
 * cualquiera de los cuatro barrotes. Código que corre dentro de WordPress no
 * puede defenderse de alguien que también puede correr código dentro de
 * WordPress; eso no es un descuido de esta reja, es el techo de lo que una
 * reja así alcanza. Lo que cierra es el camino fácil: la pantalla de Usuarios.
 * Lo otro ya exige entrar al servidor, y el día que alguien pueda hacer eso la
 * llave de la API es el menor de los problemas.
 */

defined( 'ABSPATH' ) || exit;

class SWPK_Llaves {

	/** Cuánto dura en pantalla la llave recién creada, en segundos. */
	public const ASOMO = 300;

	/**
	 * Dónde vive la huella del token de MCP.
	 *
	 * La huella y no el token: en la base de datos no queda nada con lo que
	 * alguien se pueda conectar. Es la misma regla que la contraseña, y por el
	 * mismo motivo — con la misma consecuencia, que la dirección se enseña una
	 * sola vez y si se pierde se revoca la llave y se hace otra.
	 */
	public const META_TOKEN = 'swpk_mcp_token';

	public static function init(): void {
		// Solo la lista de Usuarios, no cualquier consulta de usuarios: la
		// pantalla "Conectarse" también pregunta por el rol y necesita que le
		// contesten.
		add_filter( 'users_list_table_query_args', [ __CLASS__, 'fuera_de_la_lista' ] );
		add_filter( 'views_users', [ __CLASS__, 'fuera_de_los_filtros' ] );
		add_filter( 'map_meta_cap', [ __CLASS__, 'nadie_las_toca' ], 10, 4 );
		add_filter( 'wp_authenticate_user', [ __CLASS__, 'no_entran_por_el_formulario' ] );
		add_filter( 'allow_password_reset', [ __CLASS__, 'sin_recuperar_contrasena' ], 10, 2 );
	}

	/** ¿Esta cuenta es una llave y no una persona? */
	public static function es_llave( WP_User|int|null $usuario ): bool {
		$u = $usuario instanceof WP_User ? $usuario : get_user_by( 'id', (int) $usuario );

		return $u instanceof WP_User && in_array( SWPK_Perfiles::API, (array) $u->roles, true );
	}

	/**
	 * La llave dueña de una dirección de MCP, o null.
	 *
	 * Se busca por la huella, nunca por el token en claro, así que una consulta
	 * a mano contra la base de datos no sirve para entrar.
	 */
	public static function por_token( string $token ): ?WP_User {
		if ( 32 !== strlen( $token ) ) {
			return null;
		}

		$encontradas = get_users(
			[
				'role'       => SWPK_Perfiles::API,
				'meta_key'   => self::META_TOKEN,
				'meta_value' => hash( 'sha256', $token ),
				'number'     => 1,
			]
		);

		return $encontradas ? $encontradas[0] : null;
	}

	/** @return array<int,WP_User> */
	public static function listar(): array {
		return get_users( [ 'role' => SWPK_Perfiles::API, 'orderby' => 'display_name' ] );
	}

	public static function cuantas(): int {
		$conteo = count_users();

		return (int) ( $conteo['avail_roles'][ SWPK_Perfiles::API ] ?? 0 );
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Los cuatro barrotes
	// ─────────────────────────────────────────────────────────────────────────

	/** @param array<string,mixed> $args */
	public static function fuera_de_la_lista( array $args ): array {
		$excluidos   = (array) ( $args['role__not_in'] ?? [] );
		$excluidos[] = SWPK_Perfiles::API;

		$args['role__not_in'] = array_values( array_unique( $excluidos ) );

		return $args;
	}

	/** @param array<string,string> $vistas */
	public static function fuera_de_los_filtros( array $vistas ): array {
		unset( $vistas[ SWPK_Perfiles::API ] );

		$cuantas = self::cuantas();

		// El enlace "Todos" lleva su conteo dentro. Si no se le resta, queda
		// prometiendo más cuentas de las que la lista enseña, y eso se ve como
		// un error del panel en vez de como una decisión.
		if ( $cuantas > 0 && isset( $vistas['all'] ) ) {
			$vistas['all'] = (string) preg_replace_callback(
				'/\((\d[\d,.\s]*)\)/',
				static function ( array $m ) use ( $cuantas ): string {
					$n = (int) preg_replace( '/\D/', '', $m[1] );

					return '(' . number_format_i18n( max( 0, $n - $cuantas ) ) . ')';
				},
				$vistas['all'],
				1
			);
		}

		return $vistas;
	}

	/**
	 * Ni editarlas, ni borrarlas, ni ascenderlas — tampoco un administrador.
	 *
	 * Importa sobre todo `promote_user`: sin esto, una llave de solo lectura se
	 * podría volver administradora con un desplegable, y una llave filtrada
	 * dejaría de ser de solo lectura sin que nadie tocara el plugin.
	 *
	 * Esto no estorba al panel: crear y revocar usan las funciones de bajo
	 * nivel de WordPress, que no preguntan por permisos porque ya se preguntó
	 * antes, en la acción, con su nonce.
	 *
	 * @param array<int,string> $permisos
	 * @param array<int,mixed>  $args
	 * @return array<int,string>
	 */
	public static function nadie_las_toca( array $permisos, string $permiso, int $usuario_id, array $args ): array {
		$vigilados = [ 'edit_user', 'delete_user', 'promote_user', 'remove_user', 'add_user_meta', 'edit_user_meta', 'delete_user_meta' ];

		if ( ! in_array( $permiso, $vigilados, true ) || ! isset( $args[0] ) ) {
			return $permisos;
		}

		return self::es_llave( (int) $args[0] ) ? [ 'do_not_allow' ] : $permisos;
	}

	/**
	 * Este filtro corre en el formulario de entrada y NO en la autenticación por
	 * contraseña de aplicación — comprobado en `wp-includes/user.php`, donde
	 * `wp_authenticate_application_password()` no pasa por aquí. Es justo lo que
	 * hace falta: cerrarle la puerta de las personas sin cerrarle la suya.
	 */
	public static function no_entran_por_el_formulario( WP_User|WP_Error|null $usuario ): WP_User|WP_Error|null {
		if ( $usuario instanceof WP_User && self::es_llave( $usuario ) ) {
			return new WP_Error(
				'swpk_llave_no_entra',
				'<strong>Error:</strong> esa cuenta es una llave de consulta del panel, no una persona. Por aquí no entra.'
			);
		}

		return $usuario;
	}

	/**
	 * `allow_password_reset` no siempre trae un booleano: otro plugin puede
	 * haber puesto ya un WP_Error con su propio motivo. Se deja pasar tal cual.
	 */
	public static function sin_recuperar_contrasena( bool|WP_Error $permitido, int $usuario_id ): bool|WP_Error {
		return self::es_llave( $usuario_id ) ? false : $permitido;
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Crear y revocar: las dos únicas cosas que se le hacen a una llave
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * Crea una llave y devuelve su contraseña en claro, una sola vez.
	 *
	 * @return array{nombre:string,login:string,clave:string,token:string,error:string}|WP_Error
	 */
	public static function crear( string $nombre ): array|WP_Error {
		$nombre = sanitize_text_field( $nombre );

		if ( '' === $nombre ) {
			return new WP_Error( 'swpk_sin_nombre', 'Ponle un nombre: sirve para saber a qué sistema revocarle el acceso.' );
		}

		// El prefijo no es decoración: es lo que hace que una llave se
		// distinga de una persona de un vistazo, en el registro de accesos y
		// en cualquier consulta hecha a mano contra la base de datos.
		$login = 'llave-' . sanitize_user( sanitize_title( $nombre ), true );
		$base  = $login;
		$i     = 2;

		while ( username_exists( $login ) ) {
			$login = $base . '-' . $i;
			++$i;
		}

		// Una llave no es una persona y no tiene correo, pero WordPress exige
		// uno y único. Se le pone uno del propio panel; si el panel vive en una
		// dirección sin punto (localhost), uno reservado que no existe para
		// nadie, para no mandarle correo por error a un dominio ajeno.
		$anfitrion = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$dominio   = str_contains( $anfitrion, '.' ) ? $anfitrion : 'panel.invalid';

		$id = wp_insert_user(
			[
				'user_login'   => $login,
				'user_email'   => $login . '@' . $dominio,
				'user_pass'    => wp_generate_password( 48, true, true ),
				'display_name' => $nombre,
				'role'         => SWPK_Perfiles::API,
			]
		);

		if ( is_wp_error( $id ) ) {
			return $id;
		}

		// La dirección de MCP se genera siempre, con contraseñas de aplicación o
		// sin ellas: es la vía que no exige consola, y no tiene sentido que
		// dependa de un mecanismo de WordPress que este servidor podría no tener.
		$token = bin2hex( random_bytes( 16 ) );
		update_user_meta( (int) $id, self::META_TOKEN, hash( 'sha256', $token ) );

		$clave = '';
		$error = '';

		if ( wp_is_application_passwords_available() ) {
			$creada = WP_Application_Passwords::create_new_application_password(
				(int) $id,
				[ 'name' => 'Panel Kinsta · ' . $nombre ]
			);

			if ( is_wp_error( $creada ) ) {
				$error = $creada->get_error_message();
			} else {
				$clave = (string) $creada[0];
			}
		} else {
			$error = 'WordPress las exige por HTTPS y este servidor no lo tiene';
		}

		return [ 'nombre' => $nombre, 'login' => $login, 'clave' => $clave, 'token' => $token, 'error' => $error ];
	}

	/**
	 * Revoca una llave: le corta las contraseñas y borra la cuenta.
	 *
	 * Se borra, no se deja sin rol, por lo mismo que no sale en Usuarios: una
	 * cuenta `llave-algo` sin rol tirada en la lista es basura que el día que
	 * alguien la vea no va a saber qué era ni si se puede tocar.
	 *
	 * Lo que consultó NO se pierde: `swpk_accesos` guarda el login como texto,
	 * así que el rastro de una llave revocada se sigue leyendo entero.
	 *
	 * @return string|WP_Error El nombre de la llave revocada.
	 */
	public static function revocar( int $id ): string|WP_Error {
		$u = get_user_by( 'id', $id );

		// Este botón no puede servir para dejar fuera a una persona del equipo
		// por un id cambiado a mano en la dirección.
		if ( ! $u || ! self::es_llave( $u ) ) {
			return new WP_Error( 'swpk_no_es_llave', 'Esa no es una llave de consulta.' );
		}

		$nombre = (string) $u->display_name;

		// Primero las contraseñas: corta en seco lo que esté consultando ahora
		// mismo, incluso si el borrado de la cuenta fallara.
		if ( class_exists( 'WP_Application_Passwords' ) ) {
			WP_Application_Passwords::delete_all_application_passwords( $id );
		}

		if ( ! function_exists( 'wp_delete_user' ) ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
		}

		wp_delete_user( $id );

		return $nombre;
	}
}
