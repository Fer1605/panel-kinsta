<?php
/**
 * Que WordPress deje de mandar sus propios correos de actualización.
 *
 * ─── De dónde sale esto ─────────────────────────────────────────────────────
 *
 * Es el pedido más concreto del audio del 18/11/2026, y es textual:
 *
 *   «acordate que NO DEBE NOTIFICAR DOBLE CORREO, porque hoy por hoy a los
 *    administradores o al autor del sitio, que soy yo, me llegan "se ha
 *    actualizado, se ha actualizado, se ha actualizado". ESO YA NO DEBE
 *    LLEGAR. Deben llegar los del sistema, SOLO LOS DEL PANEL»       [02:18]
 *
 * ─── Qué se calla y qué no ──────────────────────────────────────────────────
 *
 * Se callan los avisos de rutina: «se actualizó un plugin», «se actualizó el
 * núcleo», «hay una versión nueva». Esos son los que llegan tres veces por
 * semana y los que él describe.
 *
 * **NO se calla nada que avise de un problema.** Si una actualización falla, si
 * el sitio entra en modo de recuperación o si WordPress manda un correo de
 * depuración porque algo salió mal, eso sigue saliendo. Callar un fallo sería
 * convertir «no duplicamos correos» en «nadie se entera de nada», que es un
 * problema mucho peor que el que se vino a resolver.
 *
 * ─── Por qué es un ajuste y no una constante ────────────────────────────────
 *
 * Porque el panel tiene que poder decir en el reporte si está encendido, y
 * porque un sitio donde el cliente SÍ quiere recibir los avisos de WordPress
 * tiene que poder quedarse como está sin tocar código.
 */

defined( 'ABSPATH' ) || exit;

class SWA_Silencio {

	public static function init(): void {
		if ( ! SWA_Ajustes::obtener( 'callar_correos', 1 ) ) {
			return;
		}

		// Los tres avisos de «se actualizó algo».
		add_filter( 'auto_core_update_send_email', [ __CLASS__, 'solo_si_hay_problema' ], 10, 4 );
		add_filter( 'auto_plugin_update_send_email', '__return_false' );
		add_filter( 'auto_theme_update_send_email', '__return_false' );

		// Y el de «hay una versión nueva disponible», que WordPress manda al
		// correo del sitio cuando no puede actualizar solo.
		add_filter( 'send_core_update_notification', '__return_false' );
	}

	/**
	 * El del núcleo sí distingue por qué está escribiendo.
	 *
	 * WordPress pasa `$type`: 'success', 'fail' o 'critical'. Se calla solo el
	 * primero. Los otros dos son justo los que hay que seguir recibiendo.
	 *
	 * @param bool   $enviar
	 * @param string $tipo
	 */
	public static function solo_si_hay_problema( $enviar, $tipo = '', $core_update = null, $result = null ): bool {
		return 'success' !== $tipo;
	}

	/** Para que el reporte pueda decir si está puesto. */
	public static function activo(): bool {
		return (bool) SWA_Ajustes::obtener( 'callar_correos', 1 );
	}
}
