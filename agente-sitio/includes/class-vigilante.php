<?php
/**
 * El aviso en el momento, cuando cambia quién tiene control del sitio.
 *
 * ─── Por qué no basta el reporte diario ─────────────────────────────────────
 *
 * El reporte diario sigue existiendo y sirve, pero para esto llega tarde. El
 * caso que hay que atrapar es el que describió el jefe: un malware entra y se
 * crea un administrador. Si el panel se entera a las cinco de la mañana del día
 * siguiente, el intruso ya pasó una noche entera dentro con todos los permisos
 * — tiempo de sobra para dejar otra puerta, mandar correo desde el dominio del
 * cliente o tocar la base.
 *
 * Enterarse en el momento es lo que permite interferir. Enterarse mañana es
 * enterarse de lo que ya pasó.
 *
 * ─── Cómo avisa ─────────────────────────────────────────────────────────────
 *
 * Se engancha a los avisos que WordPress ya da cuando alguien se registra,
 * cambia de rol o se borra, y manda el reporte completo en ese instante.
 *
 * Manda la lista ENTERA, no solo lo que cambió. Suena a desperdicio y no lo es:
 * el panel ya sabe comparar lo que recibe contra lo que tenía guardado, así que
 * mandar todo hace que el aviso sea repetible sin daño. Si uno se pierde — se
 * cayó la red, el panel estaba reiniciando — el siguiente corrige todo solo, sin
 * que nadie tenga que reconstruir qué avisos faltaron.
 *
 * ─── Tres detalles que evitan que esto se vuelva ruido ──────────────────────
 *
 * 1. Se manda al cerrar la petición, no dentro del gancho. Si un plugin crea
 *    cincuenta usuarios en un mismo golpe, eso es UN aviso, no cincuenta.
 *
 * 2. Antes de mandar se compara una huella de la lista de usuarios vigilados.
 *    Si lo que cambió no es nadie con control —alguien editó su biografía, o se
 *    registró un suscriptor en un blog abierto— no se manda nada. Un aviso que
 *    salta por cosas que no importan deja de leerse en una semana.
 *
 * 3. Si el envío falla, se reintenta a los cinco minutos, y de todas formas el
 *    reporte diario vuelve a cuadrarlo todo. El aviso instantáneo es la vía
 *    rápida; el reporte diario es la red por debajo.
 */

defined( 'ABSPATH' ) || exit;

class SWA_Vigilante {

	public const HOOK_REINTENTO = 'swa_reintento_aviso';

	/** Guarda la huella de la última lista que el panel ya conoce. */
	private const OPCION_HUELLA = 'swa_huella_usuarios';

	private static bool $pendiente = false;

	public static function init(): void {
		$ganchos = [
			'user_register',   // alguien nuevo
			'profile_update',  // le cambiaron el correo o los datos
			'set_user_role',   // le cambiaron el rol
			'add_user_role',
			'remove_user_role',
			'deleted_user',    // ya borrado: la lista de abajo sale correcta
		];

		foreach ( $ganchos as $gancho ) {
			add_action( $gancho, [ __CLASS__, 'marcar' ], 10, 0 );
		}

		add_action( 'shutdown', [ __CLASS__, 'al_cerrar' ] );
		add_action( self::HOOK_REINTENTO, [ __CLASS__, 'reintentar' ] );
	}

	/** Algo tocó a los usuarios; se decide al cerrar si vale la pena avisar. */
	public static function marcar(): void {
		self::$pendiente = true;
	}

	/**
	 * La huella de quién tiene control ahora mismo.
	 *
	 * Entra lo que importa vigilar —quién es, con qué correo y con qué rol— y
	 * nada más. Así un cambio de contraseña o de apodo no dispara un aviso, y un
	 * administrador nuevo sí.
	 */
	public static function huella(): string {
		$filas = [];

		foreach ( SWA_Reporte::usuarios_vigilados() as $u ) {
			$filas[] = sprintf(
				'%d|%s|%s|%s',
				$u['id'],
				$u['login'],
				$u['correo'],
				implode( ',', $u['roles'] )
			);
		}

		sort( $filas );

		return sha1( implode( "\n", $filas ) );
	}

	public static function recordar_huella(): void {
		update_option( self::OPCION_HUELLA, self::huella(), false );
	}

	public static function al_cerrar(): void {
		if ( ! self::$pendiente ) {
			return;
		}

		self::$pendiente = false;

		if ( ! SWA_Ajustes::registrado() ) {
			return;
		}

		if ( self::huella() === (string) get_option( self::OPCION_HUELLA, '' ) ) {
			// Cambió algo, pero no de lo que vigilamos.
			return;
		}

		self::avisar();
	}

	private static function avisar(): void {
		$r = SWA_Reporte::enviar();

		if ( is_wp_error( $r ) ) {
			if ( ! wp_next_scheduled( self::HOOK_REINTENTO ) ) {
				wp_schedule_single_event( time() + 5 * MINUTE_IN_SECONDS, self::HOOK_REINTENTO );
			}
			return;
		}

		self::recordar_huella();
	}

	public static function reintentar(): void {
		if ( ! SWA_Ajustes::registrado() ) {
			return;
		}

		if ( self::huella() === (string) get_option( self::OPCION_HUELLA, '' ) ) {
			return;
		}

		self::avisar();
	}
}
