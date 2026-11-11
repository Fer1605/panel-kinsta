<?php
/**
 * El correo del sitio.
 *
 * ─── Por qué esto va ANTES de activar la verificación en dos pasos ──────────
 *
 * Si el código llega por correo y el sitio no puede enviar correo, el
 * administrador pide su código, nunca le llega, y queda encerrado afuera de su
 * propio sitio. Nosotros también.
 *
 * Y en WordPress el envío de correo está roto más seguido de lo que uno
 * quisiera: wp_mail sin configurar, SMTP muerto, dominio sin SPF. Por eso el
 * agente prueba el envío y se lo reporta al panel, y el panel puede decir de
 * antemano en qué sitios es seguro activar los dos pasos y en cuáles hay que
 * arreglar el correo primero.
 */

defined( 'ABSPATH' ) || exit;

class SWA_Correo {

	public const OPCION_ESTADO = 'swa_estado_correo';

	/**
	 * Manda un correo de prueba y guarda el resultado.
	 *
	 * Ojo con lo que esto comprueba y lo que no: comprueba que WordPress logró
	 * entregárselo a su transporte sin error. No comprueba que haya llegado a
	 * la bandeja. Es la misma distinción entre "enviado" y "entregado" que
	 * aplica a la bitácora del panel, y conviene no confundirla.
	 */
	public static function probar( string $destino = '' ): array {
		$destino = '' !== $destino ? $destino : (string) get_option( 'admin_email' );

		$error = '';
		$captura = static function ( WP_Error $e ) use ( &$error ): void {
			$error = $e->get_error_message();
		};
		add_action( 'wp_mail_failed', $captura );

		$ok = wp_mail(
			$destino,
			'Prueba de envío — Agente Solución Web',
			'<p>Este correo confirma que ' . esc_html( get_bloginfo( 'name' ) ) . ' puede enviar correo.</p>'
			. '<p>Se mandó automáticamente antes de activar la verificación en dos pasos. Si no se puede enviar correo, esa verificación dejaría a los administradores sin poder entrar.</p>',
			[ 'Content-Type: text/html; charset=UTF-8' ]
		);

		remove_action( 'wp_mail_failed', $captura );

		$estado = [
			'ok'       => (bool) $ok,
			'error'    => $error,
			'destino'  => $destino,
			'probado'  => current_time( 'mysql' ),
		];

		update_option( self::OPCION_ESTADO, $estado, false );

		return $estado;
	}

	public static function estado(): array {
		$e = get_option( self::OPCION_ESTADO, [] );
		return is_array( $e ) ? $e : [];
	}

	public static function puede_enviar(): bool {
		return (bool) ( self::estado()['ok'] ?? false );
	}
}
