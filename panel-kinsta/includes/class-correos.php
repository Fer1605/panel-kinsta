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

	/**
	 * El resumen de los domingos.
	 *
	 * Es un correo distinto del semanal a las asesoras, aunque los dos sean
	 * semanales: aquel va a cada asesora con SUS sitios pendientes, y este va a
	 * soporte con TODO lo que el panel notificó. El jefe pidió el segundo
	 * expresamente y con día: «al final de semana, un domingo».
	 */
	public const HOOK_RESUMEN = 'swpk_correo_resumen';

	public const MODO_PRUEBA = 'prueba';
	public const MODO_REAL   = 'real';

	public static function init(): void {
		add_filter( 'cron_schedules', [ __CLASS__, 'horarios' ] );
		add_action( self::HOOK_SEMANAL, [ __CLASS__, 'semanal_asesoras' ] );
		add_action( self::HOOK_DIARIO, [ __CLASS__, 'diario_soporte' ] );
		add_action( self::HOOK_MENSUAL, [ __CLASS__, 'mensual_clientes' ] );
		add_action( self::HOOK_RESUMEN, [ __CLASS__, 'resumen_semanal' ] );
	}

	public static function programar(): void {
		// Lunes 7:00 am: "le mandamos un correo los lunes a las 7 de la mañana" (02:34).
		if ( ! wp_next_scheduled( self::HOOK_SEMANAL ) ) {
			wp_schedule_event( SWPK_Frescura::cuando( 'next monday 07:00' ), 'weekly', self::HOOK_SEMANAL );
		}

		// Después de la corrida de las 6:30, para que el resumen tenga datos de hoy.
		if ( ! wp_next_scheduled( self::HOOK_DIARIO ) ) {
			wp_schedule_event( SWPK_Frescura::cuando( 'tomorrow 07:30' ), 'daily', self::HOOK_DIARIO );
		}

		if ( ! wp_next_scheduled( self::HOOK_RESUMEN ) ) {
			// Domingo a las 18:00: la semana ya terminó y todavía es hora de
			// que alguien lo lea antes del lunes.
			wp_schedule_event( SWPK_Frescura::cuando( 'next sunday 18:00' ), 'weekly', self::HOOK_RESUMEN );
		}

		if ( ! wp_next_scheduled( self::HOOK_MENSUAL ) ) {
			wp_schedule_event( SWPK_Frescura::cuando( 'first day of next month 08:00' ), 'monthly', self::HOOK_MENSUAL );
		}
	}

	public static function desprogramar(): void {
		foreach ( [ self::HOOK_SEMANAL, self::HOOK_DIARIO, self::HOOK_MENSUAL, self::HOOK_RESUMEN ] as $hook ) {
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
				SWPK_Plantillas::asunto( 'semanal', [ '{{cuantos}}' => (string) count( $con_algo ) ] ),
				$cuerpo
			);

			++$resumen['asesoras'];
			$resumen['sitios']      += count( $con_algo );
			$resumen['resultados'][] = $persona->display_name . ': ' . $r;
		}

		// Lo mismo que arriba: una semana en que ninguna asesora tenía nada
		// pendiente deja su constancia, en vez de no dejar nada.
		if ( ! $resumen['asesoras'] ) {
			SWPK_Bitacora::registrar(
				SWPK_Bitacora::ASESORA,
				[],
				'Resumen semanal: ninguna asesora con sitios pendientes',
				'',
				'',
				'sin_novedad',
				'El panel revisó los sitios de cada asesora y ninguno tenía algo pendiente.'
			);
		}

		update_option( 'swpk_ultimo_semanal', current_time( 'mysql' ), false );

		return $resumen;
	}

	/**
	 * El resumen de los domingos: todo lo que se notificó en la semana.
	 *
	 * Sale IGUAL cuando no se envió nada, y eso no es un descuido: es el pedido.
	 * «En ese resumen se le puede indicar: de esto no se obtuvo ningún
	 * movimiento, no ha pasado nada.» Un domingo sin correo no significa «no
	 * pasó nada», significa «no sé si el panel corrió» — y poder distinguir las
	 * dos cosas es justo para lo que sirve el panel.
	 *
	 * @return array<string,mixed>
	 */
	public static function resumen_semanal(): array {
		$hasta = current_time( 'Y-m-d' );
		$desde = wp_date( 'Y-m-d', strtotime( '-6 days', (int) current_time( 'timestamp' ) ) );

		$filas = SWPK_Bitacora::entre( $desde, $hasta );

		// El propio resumen no se cuenta a sí mismo.
		$filas = array_values(
			array_filter( $filas, static fn( $f ) => SWPK_Bitacora::RESUMEN !== $f->tipo )
		);

		// La otra mitad: lo que PASÓ, que no es lo mismo que lo que avisamos.
		// Un sitio puede haber cambiado de versión cinco veces sin generar un
		// solo correo.
		$eventos = SWPK_Eventos::sin_repetidos( SWPK_Eventos::entre( $desde, $hasta ) );

		$cuerpo = SWPK_Plantillas::resumen_semanal( $filas, $desde, $hasta, $eventos );

		$r = self::despachar(
			SWPK_Bitacora::RESUMEN,
			self::destinatarios_soporte(),
			SWPK_Plantillas::asunto(
				'resumen',
				[
					'{{cuantos}}'     => (string) count( $filas ),
					'{{movimientos}}' => (string) count( $eventos ),
					'{{desde}}'       => $desde,
					'{{hasta}}'       => $hasta,
				]
			),
			$cuerpo
		);

		update_option( 'swpk_ultimo_resumen', current_time( 'mysql' ), false );

		return [
			'avisos'     => count( $filas ),
			'movimientos' => count( $eventos ),
			'desde'      => $desde,
			'hasta'      => $hasta,
			'resultado'  => $r,
		];
	}

	/** Resumen diario a soporte: qué cambió ayer. */
	public static function diario_soporte(): array {
		$desde   = SWPK_Frescura::limite( DAY_IN_SECONDS );
		$eventos = array_filter(
			SWPK_Eventos::recientes( 200 ),
			static fn( $e ) => $e->ocurrio_en >= $desde
		);

		// Un día sin cambios NO se va callado. Antes esta función se salía por
		// aquí sin escribir nada, y en la bitácora ese día simplemente no
		// existía: imposible distinguir "el panel miró y no había nada" de "el
		// panel no corrió". En una pantalla que es evidencia, eso es lo peor
		// que puede pasar. El 12 de noviembre de 2026 no quedó constancia del
		// correo diario por esta razón.
		if ( ! $eventos ) {
			SWPK_Bitacora::registrar(
				SWPK_Bitacora::SOPORTE,
				self::destinatarios_soporte(),
				'Resumen del día: sin cambios en la cartera',
				'',
				'',
				'sin_novedad',
				'El panel revisó y no hubo cambios que contar en las últimas 24 horas.'
			);

			return [ 'eventos' => 0, 'resultado' => 'sin_novedad' ];
		}

		$cuerpo = SWPK_Plantillas::diario_soporte( $eventos );

		$r = self::despachar(
			SWPK_Bitacora::SOPORTE,
			self::destinatarios_soporte(),
			SWPK_Plantillas::asunto( 'diario', [ '{{cuantos}}' => (string) count( $eventos ) ] ),
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
				SWPK_Plantillas::asunto(
					'mensual',
					[
						'{{sitio}}' => (string) $sitio->nombre_visible,
						'{{mes}}'   => SWPK_Frescura::fmt( 'F \d\e Y', $desde ),
					]
				),
				$cuerpo,
				(string) $sitio->sitio_id
			);

			if ( in_array( $r, [ 'enviado', 'simulado' ], true ) ) {
				++$resumen['enviados'];
			}
		}

		// Y lo mismo. Este es el caso que de verdad pasó: el 11 de noviembre de
		// 2026 el mensual recorrió la cartera, no encontró actualizaciones del
		// mes anterior en ningún sitio —el panel llevaba un día de vida— y se
		// fue sin escribir una sola línea.
		if ( ! $resumen['enviados'] ) {
			SWPK_Bitacora::registrar(
				SWPK_Bitacora::CLIENTE,
				[],
				'Reportes mensuales: ningún sitio con movimientos el mes pasado',
				'',
				'',
				'sin_novedad',
				sprintf(
					'El panel revisó %d sitio(s) nuestros y ninguno tuvo actualizaciones entre el %s y el %s.',
					(int) $resumen['sin_novedad'],
					$desde,
					$hasta
				)
			);
		}

		update_option( 'swpk_ultimo_mensual', current_time( 'mysql' ), false );

		return $resumen;
	}
}
