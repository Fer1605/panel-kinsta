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

		// Y se programa aquí además de en la activación, igual que el monitor
		// y la revisión diaria. No es por gusto: EL MENSUAL A LOS CLIENTES NO
		// ESTABA PROGRAMADO, y lo estuvo así desde que existe el panel.
		//
		// Es el mismo fallo que ya se había visto en el sondeo, calcado.
		// `wp_schedule_event()` comprueba que la recurrencia exista en
		// `wp_get_schedules()`, y si no existe devuelve `false` sin decir
		// nada: ni excepción, ni aviso, ni registro. `monthly` no es de
		// WordPress, la mete el filtro `horarios()` de aquí arriba — y ese
		// filtro cuelga de `init()`, que corre en `plugins_loaded`. Al
		// ACTIVAR el plugin, `plugins_loaded` ya pasó. Así que el único de
		// los cuatro correos con recurrencia propia es el único que no quedó
		// puesto. Los otros tres usan `daily` y `weekly`, que sí trae
		// WordPress.
		//
		// Comprobado en producción el 25/11/2026: `wp cron event list`
		// devolvía los otros tres y no este. Ese correo iba a salir el 1 de
		// diciembre a clientes reales y no iba a salir nunca.
		self::programar();
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

		// Lo escrito a mano manda. El campo de Sitios se puede escribir
		// siempre, y un campo que se puede escribir y no hace nada es una
		// trampa: si alguien puso ahí una dirección, es porque quiere que el
		// aviso vaya a esa y no a la que deduce el panel. Vacío, se usa la
		// lista del agente, que es lo normal.
		$a_mano = array_values( array_filter( array_map(
			'trim',
			explode( ',', (string) ( $sitio->contacto_cliente ?? '' ) )
		) ) );

		if ( $a_mano ) {
			return array_values( array_unique( $a_mano ) );
		}

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

		return array_values( array_unique( $correos ) );
	}

	/**
	 * Lo mismo que `destinatarios_cliente()`, pero para muchos sitios de una
	 * vez y en dos consultas.
	 *
	 * La versión de uno en uno hace dos consultas por sitio. Eso está bien al
	 * enviar —se envía poco y de tarde en tarde— y está mal en una tabla de
	 * veinte filas, que es donde hace falta enseñar a quién le va a llegar el
	 * aviso sin tener que entrar a cada ficha.
	 *
	 * Devuelve una entrada por sitio con sus correos y, cuando no hay ninguno,
	 * POR QUÉ no lo hay. Esa distinción importa: «el agente todavía no ha
	 * mandado su lista de usuarios» no es lo mismo que «este sitio no tiene
	 * agente», y decir lo segundo cuando pasa lo primero es decirle a quien
	 * mira que le falta instalar algo que ya está instalado.
	 *
	 * El motivo es uno de: `ok`, `sin_agente`, `sin_reporte`, `todos_de_casa`.
	 *
	 * @param array<int,object> $sitios
	 * @return array<string,array{correos:array<int,string>,motivo:string}>
	 */
	public static function destinatarios_de_varios( array $sitios ): array {
		global $wpdb;

		if ( ! $sitios ) {
			return [];
		}

		$propios = SWPK_Ajustes::dominios_propios();

		// Los agentes, una vez. La relación con el sitio es por sitio_id
		// cuando lo trae, y por dominio cuando no: el receptor viejo no lo
		// rellenaba, así que hay filas de las dos clases.
		$agentes = $wpdb->get_results(
			'SELECT sitio_id, sitio_url FROM ' . SWPK_Tablas::tabla( 'agentes' )
		) ?: [];

		$por_id      = [];
		$por_dominio = [];

		foreach ( $agentes as $a ) {
			$url = (string) $a->sitio_url;

			if ( '' !== (string) $a->sitio_id ) {
				$por_id[ (string) $a->sitio_id ] = $url;
			}

			$host = (string) wp_parse_url( $url, PHP_URL_HOST );
			if ( '' !== $host ) {
				$por_dominio[ strtolower( preg_replace( '/^www\./', '', $host ) ) ] = $url;
			}
		}

		// Y los administradores, una vez.
		$usuarios = $wpdb->get_results(
			"SELECT sitio_url, correo FROM " . SWPK_Tablas::tabla( 'usuarios' )
			. " WHERE roles LIKE '%administrator%'"
		) ?: [];

		$correos_por_url = [];
		$reporto_alguno  = []; // url => true, aunque todos fueran de casa

		foreach ( $usuarios as $u ) {
			$url_u  = (string) $u->sitio_url;
			$correo = (string) $u->correo;

			$reporto_alguno[ $url_u ] = true;

			$dominio = strtolower( (string) substr( strrchr( $correo, '@' ) ?: '', 1 ) );

			foreach ( $propios as $propio ) {
				if ( $dominio === $propio || str_ends_with( $dominio, '.' . $propio ) ) {
					continue 2; // es de los nuestros
				}
			}

			$correos_por_url[ $url_u ][] = $correo;
		}

		$salida = [];

		foreach ( $sitios as $s ) {
			$id   = (string) $s->sitio_id;
			$host = strtolower( preg_replace( '/^www\./', '', (string) $s->dominio ) );
			$url  = $por_id[ $id ] ?? ( $por_dominio[ $host ] ?? '' );

			if ( ! empty( $correos_por_url[ $url ] ) ) {
				$salida[ $id ] = [
					'correos' => array_values( array_unique( $correos_por_url[ $url ] ) ),
					'motivo'  => 'ok',
				];
				continue;
			}

			// Sin correos. El porqué sale de lo que el panel ya sabe: si el
			// sitio tiene agente, si ese agente ha mandado alguna vez su
			// lista, y si lo que mandó se quedó todo fuera por ser de casa.
			$motivo = 'sin_agente';

			if ( 1 === (int) ( $s->cobertura_agente ?? 0 ) || '' !== $url ) {
				$motivo = ! empty( $reporto_alguno[ $url ] ) ? 'todos_de_casa' : 'sin_reporte';
			}

			$salida[ $id ] = [ 'correos' => [], 'motivo' => $motivo ];
		}

		return $salida;
	}

	/**
	 * Avisa de que un sitio se dio de baja porque el hosting ya no lo devuelve.
	 *
	 * Lo pidió con las dos mitades juntas, y la segunda es la que importa:
	 * «decir: hemos dado de baja, se queda en el historial [...] y se manda un
	 * correo que se dio de baja ese sitio porque ya no existe» (02:23).
	 *
	 * Lo llama SWPK_Inventario al sincronizar. Ver AUSENCIAS_PARA_BAJA.
	 */
	public static function avisar_de_baja( string $sitio_id, string $nombre, int $veces ): string {
		// Y la cuenta de insistencia de ese sitio se borra: no tiene sentido
		// seguir contando «es la cuarta vez que avisamos» de un sitio que ya
		// no existe.
		SWPK_Insistencia::olvidar( $sitio_id );

		return self::despachar(
			SWPK_Bitacora::CAIDA,
			self::destinatarios_soporte(),
			SWPK_Plantillas::asunto( 'de_baja', [ '{{sitio}}' => $nombre ] ),
			SWPK_Plantillas::de_baja(
				[
					'{{sitio}}' => esc_html( $nombre ),
					'{{veces}}' => (string) $veces,
				]
			),
			$sitio_id
		);
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

		// ── Un día sin cambios tampoco se va callado, y ahora SE ENVÍA ──────
		//
		// Antes, un día sin movimientos solo dejaba una línea en la bitácora y
		// no salía ningún correo. La intención era buena —que quedara
		// constancia— pero el efecto no: el jefe pidió enterarse DEL DÍA A DÍA
		// («yo no voy a ver este panel… lo que necesito es que me notifique el
		// día a día», 18:04) y un día tranquilo era un día en el que no recibía
		// nada. Peor: podía haber un sitio sin responder y doce en rojo, y como
		// ninguno de esos hechos era NUEVO, no había correo.
		//
		// El correo lleva ahora un bloque de cómo está la cartera HOY, que
		// tiene sentido se haya movido algo o no. Así que sale igual, con la
		// lista de cambios o con «ningún cambio en 24 horas». Ver
		// SWPK_Plantillas::estado_de_hoy().
		$cuerpo = SWPK_Plantillas::diario_soporte( array_values( $eventos ) );

		$r = self::despachar(
			SWPK_Bitacora::SOPORTE,
			self::destinatarios_soporte(),
			$eventos
				? SWPK_Plantillas::asunto( 'diario', [ '{{cuantos}}' => (string) count( $eventos ) ] )
				: 'Resumen del día: sin cambios en la cartera',
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

			// Y copia para nosotros, que es la mitad que faltaba del pedido.
			//
			// «Hoy se actualizó esto, en la semana actualizamos esto, esto y
			// esto de este sitio. ESTO TANTO AL CLIENTE COMO A NOSOTROS»
			// (18:27). El mensual solo salía al cliente, así que de lo único
			// que se le cuenta al cliente no quedaba copia en ninguna bandeja
			// nuestra — solo en la bitácora, que hay que entrar a mirar.
			//
			// Van en el mismo correo y no en uno aparte a propósito: lo que
			// interesa es leer EXACTAMENTE lo que le llegó al cliente, no un
			// resumen de que se le escribió. Que la dirección de soporte se
			// vea en la copia no es un problema: es la nuestra.
			if ( $destinatarios ) {
				$destinatarios = array_values(
					array_unique( array_merge( $destinatarios, self::destinatarios_soporte() ) )
				);
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
