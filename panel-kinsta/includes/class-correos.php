<?php
/**
 * Los correos automáticos.
 *
 * Esto es lo que convierte el panel en lo que se pidió: no un tablero que
 * alguien mira si se acuerda, sino avisos que salen solos y quedan registrados.
 *
 *   "Yo cuando suceda algo vengo y digo: oye, no me puedes decir nada, porque
 *    mira, según el sistema, desde tal fecha hasta esta fecha, aquí está, se
 *    hicieron 10 notificaciones." — 02:48
 *
 * ─── El interruptor, que no es un adorno ────────────────────────────────────
 *
 * Arranca en MODO DE PRUEBA. Los correos se arman completos, se guardan en la
 * bitácora y se pueden leer en el panel, pero NO SE ENVÍAN. El envío real se
 * habilita a mano cuando alguien haya leído unos cuantos y esté conforme.
 *
 * La razón es simple: el primer lote sale a clientes reales. Un error de
 * redacción, un destinatario mal calculado o un sitio clasificado en el grupo
 * equivocado se convierte en 100 correos equivocados que no se pueden recoger.
 * Mejor leerlos antes.
 *
 * Ese mismo interruptor apaga todos los envíos en cualquier momento.
 */

defined( 'ABSPATH' ) || exit;

class SWPK_Correos {

	public const HOOK_SEMANAL = 'swpk_correo_semanal';
	public const HOOK_DIARIO  = 'swpk_correo_diario';
	public const HOOK_MENSUAL = 'swpk_correo_mensual';

	public const MODO_PRUEBA = 'prueba';
	public const MODO_REAL   = 'real';

	public static function init(): void {
		add_filter( 'cron_schedules', [ __CLASS__, 'horarios' ] );
		add_action( self::HOOK_SEMANAL, [ __CLASS__, 'semanal_asesoras' ] );
		add_action( self::HOOK_DIARIO, [ __CLASS__, 'diario_soporte' ] );
		add_action( self::HOOK_MENSUAL, [ __CLASS__, 'mensual_clientes' ] );
	}

	public static function programar(): void {
		// Lunes 7:00 am: "le mandamos un correo los lunes a las 7 de la mañana" (02:34).
		if ( ! wp_next_scheduled( self::HOOK_SEMANAL ) ) {
			wp_schedule_event( strtotime( 'next monday 07:00' ), 'weekly', self::HOOK_SEMANAL );
		}

		// Después de la corrida de las 6:30, para que el resumen tenga datos de hoy.
		if ( ! wp_next_scheduled( self::HOOK_DIARIO ) ) {
			wp_schedule_event( strtotime( 'tomorrow 07:30' ), 'daily', self::HOOK_DIARIO );
		}

		if ( ! wp_next_scheduled( self::HOOK_MENSUAL ) ) {
			wp_schedule_event( strtotime( 'first day of next month 08:00' ), 'monthly', self::HOOK_MENSUAL );
		}
	}

	public static function desprogramar(): void {
		foreach ( [ self::HOOK_SEMANAL, self::HOOK_DIARIO, self::HOOK_MENSUAL ] as $hook ) {
			$ts = wp_next_scheduled( $hook );
			if ( $ts ) {
				wp_unschedule_event( $ts, $hook );
			}
		}
	}

	public static function horarios( array $h ): array {
		$h['weekly']  = [ 'interval' => WEEK_IN_SECONDS, 'display' => 'Cada semana' ];
		$h['monthly'] = [ 'interval' => 30 * DAY_IN_SECONDS, 'display' => 'Cada mes' ];
		return $h;
	}

	// ── El interruptor ──────────────────────────────────────────────────────

	public static function modo(): string {
		return self::MODO_REAL === get_option( 'swpk_modo_envio', self::MODO_PRUEBA )
			? self::MODO_REAL
			: self::MODO_PRUEBA;
	}

	public static function en_prueba(): bool {
		return self::MODO_PRUEBA === self::modo();
	}

	public static function activos(): bool {
		return (bool) get_option( 'swpk_envios_activos', true );
	}

	/**
	 * Arma y registra un correo. Lo envía de verdad solo si toca.
	 *
	 * Siempre queda en la bitácora, se mande o no: un correo que se generó y no
	 * salió también es información, y en modo de prueba es justamente lo que hay
	 * que poder leer.
	 */
	public static function despachar( string $tipo, array $destinatarios, string $asunto, string $cuerpo, string $sitio_id = '' ): string {
		$destinatarios = array_values( array_unique( array_filter( array_map( 'sanitize_email', $destinatarios ) ) ) );

		if ( ! $destinatarios ) {
			SWPK_Bitacora::registrar( $tipo, [], $asunto, $cuerpo, $sitio_id, 'sin_destinatario', 'No había a quién enviarle.' );
			return 'sin_destinatario';
		}

		if ( ! self::activos() ) {
			SWPK_Bitacora::registrar( $tipo, $destinatarios, $asunto, $cuerpo, $sitio_id, 'apagado', 'Los envíos están apagados.' );
			return 'apagado';
		}

		if ( self::en_prueba() ) {
			SWPK_Bitacora::registrar( $tipo, $destinatarios, $asunto, $cuerpo, $sitio_id, 'simulado', 'Modo de prueba: se armó pero no se envió.' );
			return 'simulado';
		}

		return SWPK_Bitacora::enviar( $tipo, $destinatarios, $asunto, $cuerpo, $sitio_id ) ? 'enviado' : 'fallo';
	}

	// ── A quién le llega cada cosa ──────────────────────────────────────────

	/**
	 * Los administradores del sitio, que son los destinatarios del aviso al
	 * cliente: "¿cómo le voy a notificar al cliente? Pues a los administradores
	 * del sitio web" (01:43), y "todo lo que sea arroba solución se excluye"
	 * (01:57).
	 *
	 * En los sitios sin agente —los que administra el cliente— esa lista no
	 * existe, así que se usa el contacto que se haya escrito a mano en Sitios.
	 * Si tampoco hay, el correo no sale y queda anotado por qué.
	 *
	 * @return array<int,string>
	 */
	public static function destinatarios_cliente( object $sitio ): array {
		global $wpdb;

		$propios = SWPK_Ajustes::dominios_propios();
		$correos = [];

		$agente = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT sitio_url FROM ' . SWPK_Tablas::tabla( 'agentes' ) . ' WHERE sitio_id = %s OR sitio_url LIKE %s',
				$sitio->sitio_id,
				'%' . $wpdb->esc_like( (string) $sitio->dominio ) . '%'
			)
		);

		if ( $agente ) {
			$usuarios = SWPK_Usuarios::del_sitio( (string) $agente->sitio_url );

			foreach ( $usuarios as $u ) {
				if ( ! str_contains( (string) $u->roles, 'administrator' ) ) {
					continue;
				}

				$dominio = strtolower( (string) substr( strrchr( (string) $u->correo, '@' ) ?: '', 1 ) );

				foreach ( $propios as $p ) {
					if ( $dominio === $p || str_ends_with( $dominio, '.' . $p ) ) {
						continue 2; // es de los nuestros: se excluye
					}
				}

				$correos[] = (string) $u->correo;
			}
		}

		if ( ! $correos && ! empty( $sitio->contacto_cliente ) ) {
			$correos = array_filter( array_map( 'trim', explode( ',', (string) $sitio->contacto_cliente ) ) );
		}

		return array_values( array_unique( $correos ) );
	}

	/** @return array<int,string> */
	public static function destinatarios_soporte(): array {
		$crudo = (string) SWPK_Ajustes::obtener( 'correo_soporte', '' );
		return array_values( array_filter( array_map( 'trim', explode( ',', $crudo ) ) ) );
	}

	/** @return array<int,string> */
	public static function copia_ventas(): array {
		$crudo = (string) SWPK_Ajustes::obtener( 'correo_ventas', '' );
		return array_values( array_filter( array_map( 'trim', explode( ',', $crudo ) ) ) );
	}

	// ── Los tres correos ────────────────────────────────────────────────────

	/**
	 * Lunes a las 7: a cada asesora, sus sitios con algo pendiente.
	 *
	 * @return array<string,mixed>
	 */
	public static function semanal_asesoras(): array {
		$resumen = [ 'asesoras' => 0, 'sitios' => 0, 'resultados' => [] ];

		foreach ( SWPK_Perfiles::del_equipo() as $persona ) {
			$sus_sitios = array_values(
				array_filter(
					SWPK_Inventario::todos(),
					static fn( $s ) => (int) $s->asesora_id === (int) $persona->ID
				)
			);

			$con_algo = array_values(
				array_filter(
					$sus_sitios,
					static fn( $s ) => ! in_array(
						SWPK_Riesgo::nivel_del_sitio( $s->sitio_id ),
						[ SWPK_Riesgo::AL_DIA, SWPK_Riesgo::SIN_DATOS ],
						true
					)
				)
			);

			if ( ! $con_algo ) {
				continue;
			}

			$cuerpo = SWPK_Plantillas::semanal_asesora( $persona, $con_algo );

			$r = self::despachar(
				SWPK_Bitacora::ASESORA,
				array_merge( [ $persona->user_email ], self::copia_ventas() ),
				sprintf( '%d de tus sitios necesitan atención esta semana', count( $con_algo ) ),
				$cuerpo
			);

			++$resumen['asesoras'];
			$resumen['sitios']      += count( $con_algo );
			$resumen['resultados'][] = $persona->display_name . ': ' . $r;
		}

		update_option( 'swpk_ultimo_semanal', current_time( 'mysql' ), false );

		return $resumen;
	}

	/** Resumen diario a soporte: qué cambió ayer. */
	public static function diario_soporte(): array {
		$desde   = SWPK_Frescura::limite( DAY_IN_SECONDS );
		$eventos = array_filter(
			SWPK_Eventos::recientes( 200 ),
			static fn( $e ) => $e->ocurrio_en >= $desde
		);

		if ( ! $eventos ) {
			return [ 'eventos' => 0, 'resultado' => 'nada que contar' ];
		}

		$cuerpo = SWPK_Plantillas::diario_soporte( $eventos );

		$r = self::despachar(
			SWPK_Bitacora::SOPORTE,
			self::destinatarios_soporte(),
			sprintf( 'Resumen del día: %d cambios en la cartera', count( $eventos ) ),
			$cuerpo
		);

		return [ 'eventos' => count( $eventos ), 'resultado' => $r ];
	}

	/** Resumen mensual a los clientes de los sitios que sí administramos. */
	public static function mensual_clientes(): array {
		// El mes pasado según el reloj de aquí, no según UTC: si no, el 1 a las
		// 00:30 de Guatemala el panel creería que todavía es el mes anterior.
		$ahora = SWPK_Frescura::ahora_ts();
		$desde = gmdate( 'Y-m-01 00:00:00', strtotime( 'first day of last month', $ahora ) );
		$hasta = gmdate( 'Y-m-t 23:59:59', strtotime( 'last day of last month', $ahora ) );

		$resumen = [ 'enviados' => 0, 'sin_destinatario' => 0, 'sin_novedad' => 0 ];

		foreach ( SWPK_Inventario::todos() as $sitio ) {
			if ( ! SWPK_Etiquetas::es_nuestro( $sitio->tag ) ) {
				continue; // los que administra el cliente no reciben este resumen
			}

			$veces = SWPK_Eventos::conteo_actualizaciones( (string) $sitio->sitio_id, $desde, $hasta );
			if ( ! $veces ) {
				++$resumen['sin_novedad'];
				continue;
			}

			$destinatarios = self::destinatarios_cliente( $sitio );
			if ( ! $destinatarios ) {
				++$resumen['sin_destinatario'];
			}

			$cuerpo = SWPK_Plantillas::mensual_cliente( $sitio, $veces, $desde, $hasta );

			$r = self::despachar(
				SWPK_Bitacora::CLIENTE,
				$destinatarios,
				sprintf( 'Reporte de mantenimiento de %s', $sitio->nombre_visible ),
				$cuerpo,
				(string) $sitio->sitio_id
			);

			if ( in_array( $r, [ 'enviado', 'simulado' ], true ) ) {
				++$resumen['enviados'];
			}
		}

		update_option( 'swpk_ultimo_mensual', current_time( 'mysql' ), false );

		return $resumen;
	}
}
