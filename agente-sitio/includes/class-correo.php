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
 * quisiera: wp_mail sin configurar, SMTP muerto, dominio sin SPF.
 *
 * ─── El hueco que tiene una prueba automática, y cómo se tapa ───────────────
 *
 * wp_mail() devuelve true cuando el transporte ACEPTÓ el mensaje. Eso no es lo
 * mismo que entregarlo. Pasa seguido: el SMTP está mal configurado o caído, el
 * correo sale del servidor igual, y nunca llega — o llega dos horas tarde, o
 * cae en spam. Una prueba automática diría "sí funciona" y estaríamos a punto
 * de encerrar a alguien fuera de su sitio.
 *
 * Por eso la prueba no basta por sí sola. Hacen falta las dos cosas:
 *
 *   1. Que el envío no dé error (esto, automático).
 *   2. Que una persona confirme que el correo LLEGÓ (a mano, una vez).
 *
 * Sin la segunda, la verificación en dos pasos no se activa. Es un clic más y
 * es la diferencia entre creer que el correo funciona y saberlo.
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
			'ok'         => (bool) $ok,
			'error'      => $error,
			'destino'    => $destino,
			'probado'    => current_time( 'mysql' ),
			// Una prueba nueva invalida la confirmación anterior: si se volvió
			// a probar es porque algo cambió.
			'confirmado' => false,
			'confirmado_en' => '',
		];

		update_option( self::OPCION_ESTADO, $estado, false );

		return $estado;
	}

	/** Alguien vio el correo de prueba en su bandeja y lo confirma. */
	public static function confirmar_recibido(): void {
		$estado = self::estado();

		if ( empty( $estado['ok'] ) ) {
			return;
		}

		$estado['confirmado']    = true;
		$estado['confirmado_en'] = current_time( 'mysql' );
		$estado['confirmado_por'] = wp_get_current_user()->user_login ?? '';

		update_option( self::OPCION_ESTADO, $estado, false );
	}

	public static function confirmado(): bool {
		return (bool) ( self::estado()['confirmado'] ?? false );
	}

	public static function estado(): array {
		$e = get_option( self::OPCION_ESTADO, [] );
		return is_array( $e ) ? $e : [];
	}

	/**
	 * ¿Se puede activar la verificación en dos pasos en este sitio?
	 *
	 * Hacen falta las dos cosas: que el envío no falle Y que alguien haya
	 * confirmado que el correo llegó de verdad. Solo lo primero es la trampa
	 * que describe el comentario de arriba.
	 */
	public static function puede_enviar(): bool {
		$e = self::estado();
		return ! empty( $e['ok'] ) && ! empty( $e['confirmado'] );
	}

	/** El envío técnico funcionó, aunque nadie haya confirmado todavía. */
	public static function envio_aceptado(): bool {
		return (bool) ( self::estado()['ok'] ?? false );
	}
}
