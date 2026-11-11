<?php
/**
 * Registro de accesos al panel.
 *
 *   "Que registre cuándo se loguearon, cuándo se desloguearon." — 08:17
 *
 * Tres cosas se anotan: entradas, salidas e intentos fallidos. El tercero no
 * lo pidió, pero es el que de verdad sirve: una ráfaga de intentos fallidos
 * contra el panel es la señal de que alguien está probando contraseñas, y ese
 * es el sitio desde donde se consulta la información de todos los clientes.
 *
 * Qué NO se guarda: la contraseña que se intentó. Ni siquiera la fallida. Un
 * registro de contraseñas intentadas es una lista de contraseñas reales de
 * gente que se equivocó de campo, y eso no se guarda nunca.
 */

defined( 'ABSPATH' ) || exit;

class SWPK_Accesos {

	public const ENTRADA = 'entrada';
	public const SALIDA  = 'salida';
	public const FALLIDO = 'fallido';
	public const BLOQUEO = 'bloqueo';

	public static function init(): void {
		// Prioridad 20 a propósito: la verificación en dos pasos se engancha en
		// wp_login con prioridad 10 y corta la ejecución para mostrar su
		// pantalla. Si esto corriera antes, quedaría anotada una entrada de
		// alguien que solo acertó la contraseña y todavía no ha entrado — que es
		// precisamente lo contrario de lo que este registro debe probar.
		add_action( 'wp_login', [ __CLASS__, 'al_entrar' ], 20, 2 );

		// Y por eso mismo hace falta este otro: cuando hay segundo paso, el
		// gancho de arriba nunca llega a correr. La entrada se anota cuando el
		// segundo paso se supera, que es cuando la persona entró de verdad.
		add_action( 'swa_segundo_paso_superado', [ __CLASS__, 'al_pasar_segundo_paso' ] );

		// Un bloqueo por intentos fallidos contra el segundo paso también se
		// anota: es la señal de que alguien tiene una contraseña que no debería.
		add_action( 'swa_bloqueo_por_intentos', [ __CLASS__, 'al_bloquearse' ] );

		add_action( 'wp_logout', [ __CLASS__, 'al_salir' ] );
		add_action( 'wp_login_failed', [ __CLASS__, 'al_fallar' ] );
	}

	public static function al_pasar_segundo_paso( WP_User $usuario ): void {
		self::anotar( self::ENTRADA, $usuario->ID, $usuario->user_login );
	}

	public static function al_bloquearse( WP_User $usuario ): void {
		self::anotar( self::BLOQUEO, $usuario->ID, $usuario->user_login );
	}

	public static function al_entrar( string $login, WP_User $usuario ): void {
		self::anotar( self::ENTRADA, $usuario->ID, $login );
	}

	public static function al_salir( int $usuario_id = 0 ): void {
		$usuario = $usuario_id ? get_user_by( 'id', $usuario_id ) : wp_get_current_user();
		self::anotar( self::SALIDA, (int) ( $usuario->ID ?? 0 ), (string) ( $usuario->user_login ?? '' ) );
	}

	public static function al_fallar( string $login ): void {
		$usuario = get_user_by( 'login', $login );
		self::anotar( self::FALLIDO, (int) ( $usuario->ID ?? 0 ), $login );
	}

	private static function anotar( string $accion, int $usuario_id, string $login ): void {
		global $wpdb;

		$wpdb->insert(
			SWPK_Tablas::tabla( 'accesos' ),
			[
				'ocurrio_en' => current_time( 'mysql' ),
				'usuario_id' => $usuario_id,
				'login'      => sanitize_text_field( $login ),
				'accion'     => $accion,
				'ip'         => self::ip(),
				'agente'     => isset( $_SERVER['HTTP_USER_AGENT'] )
					? mb_substr( sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_USER_AGENT'] ) ), 0, 255 )
					: '',
			]
		);
	}

	/**
	 * La IP de quien está entrando.
	 *
	 * Detrás de un proxy o un CDN, REMOTE_ADDR es la del proxy y la real viene
	 * en una cabecera. Pero esas cabeceras las puede inventar cualquiera, así
	 * que solo se hacen caso si el servidor está marcado como que está detrás
	 * de un proxy de confianza. Si no, se guarda la que ve el servidor, que
	 * será menos útil pero no es mentira.
	 */
	private static function ip(): string {
		if ( defined( 'SWPK_DETRAS_DE_PROXY' ) && SWPK_DETRAS_DE_PROXY && ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$cadena = sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
			$primera = trim( explode( ',', $cadena )[0] );
			if ( filter_var( $primera, FILTER_VALIDATE_IP ) ) {
				return $primera;
			}
		}

		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) ) : '';

		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	}

	/** @return array<int,object> */
	public static function listar( int $limite = 200, string $accion = '' ): array {
		global $wpdb;
		$tabla = SWPK_Tablas::tabla( 'accesos' );

		if ( '' !== $accion ) {
			return $wpdb->get_results(
				$wpdb->prepare( "SELECT * FROM {$tabla} WHERE accion = %s ORDER BY ocurrio_en DESC, id DESC LIMIT %d", $accion, $limite )
			) ?: [];
		}

		return $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$tabla} ORDER BY ocurrio_en DESC, id DESC LIMIT %d", $limite )
		) ?: [];
	}

	/** Intentos fallidos en las últimas horas: la señal de que alguien está probando. */
	public static function fallidos_recientes( int $horas = 24 ): int {
		global $wpdb;
		$tabla = SWPK_Tablas::tabla( 'accesos' );

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$tabla} WHERE accion = %s AND ocurrio_en > %s",
				self::FALLIDO,
				gmdate( 'Y-m-d H:i:s', time() - $horas * HOUR_IN_SECONDS )
			)
		);
	}
}
