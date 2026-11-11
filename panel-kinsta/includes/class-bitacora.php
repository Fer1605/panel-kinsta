<?php
/**
 * La bitácora. Esto es el producto, no un accesorio.
 *
 * "No se guarde el correo, sino necesito que se guarde qué notifiqué, a quién
 * notifiqué y cuándo lo notifiqué" (02:58). Y el para qué, que es lo que manda
 * sobre todo el diseño: "yo cuando suceda algo vengo y digo: oye, no me puedes
 * decir nada, porque mira, según el sistema, desde tal fecha hasta esta fecha,
 * aquí está, se hicieron 10 notificaciones" (02:48).
 *
 * Guardamos también el cuerpo del correo, aunque no lo pidió: es la diferencia
 * entre decir "le avisamos" y poder mostrar qué decía el aviso. Se puede apagar
 * en Ajustes.
 *
 * Una honestidad que tiene que estar en pantalla y no solo aquí: esto prueba
 * que el panel ENVIÓ. Probar que LLEGÓ depende de que el SMTP del sitio dé
 * acuse, y puede que no lo dé. La columna dice "enviado", no "entregado".
 */

defined( 'ABSPATH' ) || exit;

class SWPK_Bitacora {

	public const CLIENTE  = 'aviso_cliente';
	public const ASESORA  = 'aviso_asesora';
	public const SOPORTE  = 'aviso_soporte';
	public const DOS_FA   = 'aviso_2fa';
	public const CAIDA    = 'aviso_caida';

	public static function tipos(): array {
		return [
			self::CLIENTE => 'Cliente',
			self::ASESORA => 'Asesora',
			self::SOPORTE => 'Soporte',
			self::DOS_FA  => 'Doble autenticación',
			self::CAIDA   => 'Caída',
		];
	}

	/**
	 * Manda un correo y lo registra. Si el envío falla, también queda registrado:
	 * un fallo silencioso en la evidencia es peor que no tener evidencia.
	 *
	 * @param array<int,string> $destinatarios
	 */
	public static function enviar(
		string $tipo,
		array $destinatarios,
		string $asunto,
		string $cuerpo_html,
		string $sitio_id = ''
	): bool {
		$destinatarios = array_values( array_unique( array_filter( array_map( 'sanitize_email', $destinatarios ) ) ) );

		if ( ! $destinatarios ) {
			self::registrar( $tipo, [], $asunto, $cuerpo_html, $sitio_id, 'sin_destinatario', 'No había a quién enviarle.' );
			return false;
		}

		$cabeceras = [ 'Content-Type: text/html; charset=UTF-8' ];

		$error = '';
		add_action(
			'wp_mail_failed',
			static function ( WP_Error $e ) use ( &$error ): void {
				$error = $e->get_error_message();
			}
		);

		$ok = wp_mail( $destinatarios, $asunto, $cuerpo_html, $cabeceras );

		self::registrar(
			$tipo,
			$destinatarios,
			$asunto,
			$cuerpo_html,
			$sitio_id,
			$ok ? 'enviado' : 'fallo',
			$error
		);

		return (bool) $ok;
	}

	/** @param array<int,string> $destinatarios */
	public static function registrar(
		string $tipo,
		array $destinatarios,
		string $asunto,
		string $cuerpo,
		string $sitio_id = '',
		string $resultado = 'enviado',
		string $error = ''
	): void {
		global $wpdb;

		$guardar_cuerpo = (bool) apply_filters( 'swpk_guardar_cuerpo_del_correo', true );

		$wpdb->insert(
			SWPK_Tablas::tabla( 'bitacora' ),
			[
				'enviado_en'     => current_time( 'mysql' ),
				'tipo'           => $tipo,
				'sitio_id'       => $sitio_id,
				'destinatarios'  => implode( ', ', $destinatarios ),
				'asunto'         => $asunto,
				'cuerpo'         => $guardar_cuerpo ? $cuerpo : null,
				'resultado'      => $resultado,
				'error'          => '' !== $error ? $error : null,
				'disparado_por'  => get_current_user_id(),
			]
		);
	}

	/** @return array<int,object> */
	public static function listar( int $limite = 100, string $sitio_id = '' ): array {
		global $wpdb;
		$tabla = SWPK_Tablas::tabla( 'bitacora' );

		if ( '' !== $sitio_id ) {
			return $wpdb->get_results(
				$wpdb->prepare( "SELECT * FROM {$tabla} WHERE sitio_id = %s ORDER BY enviado_en DESC, id DESC LIMIT %d", $sitio_id, $limite )
			) ?: [];
		}

		return $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$tabla} ORDER BY enviado_en DESC, id DESC LIMIT %d", $limite )
		) ?: [];
	}

	/** Cuántos avisos se mandaron a un sitio entre dos fechas. La pregunta del minuto 02:48. */
	public static function conteo( string $sitio_id, string $desde, string $hasta, string $tipo = '' ): int {
		global $wpdb;
		$tabla = SWPK_Tablas::tabla( 'bitacora' );

		if ( '' !== $tipo ) {
			return (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$tabla} WHERE sitio_id = %s AND tipo = %s AND enviado_en BETWEEN %s AND %s",
					$sitio_id, $tipo, $desde, $hasta
				)
			);
		}

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$tabla} WHERE sitio_id = %s AND enviado_en BETWEEN %s AND %s",
				$sitio_id, $desde, $hasta
			)
		);
	}
}
