<?php
/**
 * Cómo un trabajo largo se pide a sí mismo la siguiente tanda.
 *
 * ─── Por qué esto es una clase y no tres líneas copiadas ────────────────────
 *
 * Porque las tres líneas estaban mal en el único sitio donde existían, y la
 * copia habría heredado el fallo.
 *
 * `SWPK_Trabajo::encadenar()` manda la petición con `'blocking' => false` y
 * `'timeout' => 0.01`. Funciona, porque a ese trabajo lo arranca siempre
 * alguien pulsando un botón, o sea una petición web. Medido en producción el
 * 27-11-2026: **desde la línea de comandos y desde el reloj, con esos valores
 * la petición no llega a salir.** La corrida de la sonda se quedaba parada en
 * 108 sitios pendientes sin que nadie se enterara, y la de la revisión moría
 * a los 23 sitios de 109 todos los días desde hacía semanas.
 *
 * Lo que sí funciona desde cualquier sitio es esperar un poco. Medio segundo:
 * suficiente para que la petición salga de verdad, y nada comparado con los
 * veinticinco segundos que va a trabajar el del otro lado. Cuando la espera
 * se corta, el trabajo sigue igual, porque quien atiende pone
 * `ignore_user_abort()`.
 *
 * Y siempre, además, se deja programado el reloj de respaldo: si el hosting
 * no permite que un sitio se llame a sí mismo, el trabajo avanza más lento
 * pero avanza.
 */

defined( 'ABSPATH' ) || exit;

final class SWPK_Relevo {

	/** Lo que espera quien manda el aviso. No es esperar a que trabaje. */
	private const ESPERA = 0.5;

	/** Cuánto vale un candado antes de darse por muerto. */
	private const CANDADO = 180;

	/**
	 * Pide la siguiente tanda al propio panel.
	 *
	 * @param string $accion  El `action` de admin-ajax que la atiende.
	 * @param string $llave   La llave de esta corrida, que es lo que autoriza.
	 * @param string $respaldo El hook del reloj por si la petición no sale.
	 */
	public static function pedir( string $accion, string $llave, string $respaldo ): void {
		if ( '' === $llave ) {
			return;
		}

		if ( '' !== $respaldo && ! wp_next_scheduled( $respaldo ) ) {
			wp_schedule_single_event( time() + 120, $respaldo );
		}

		wp_remote_post(
			admin_url( 'admin-ajax.php' ),
			[
				'blocking'  => true,
				'timeout'   => self::ESPERA,
				'sslverify' => false,
				'body'      => [
					'action' => $accion,
					'llave'  => $llave,
				],
			]
		);
	}

	/** La llave que vino en la petición, limpia. */
	public static function llave_pedida(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- lo que autoriza es la llave del trabajo, no una sesión: quien manda esto es el panel, no una persona.
		return isset( $_POST['llave'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['llave'] ) ) : '';
	}

	/** Para que dos no avancen el mismo trabajo a la vez. */
	public static function tomar( string $tarea ): bool {
		if ( get_transient( 'swpk_candado_' . $tarea ) ) {
			return false;
		}

		set_transient( 'swpk_candado_' . $tarea, 1, self::CANDADO );

		return true;
	}

	public static function soltar( string $tarea ): void {
		delete_transient( 'swpk_candado_' . $tarea );
	}
}
