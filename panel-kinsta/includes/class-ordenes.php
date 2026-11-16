<?php
/**
 * La cola de lo que el panel le pide a un agente.
 *
 * ─── Por qué una cola y no una llamada ─────────────────────────────────────
 *
 * Lo natural sería que el panel llamara al sitio y le dijera «apagá el editor
 * de código». Eso obligaría a que cada sitio de cliente dejara un endpoint
 * esperando peticiones de internet, y es exactamente lo que este proyecto
 * prometió por escrito que no iba a hacer: «el sitio llama al panel, no al
 * revés» (ver SWA_Reporte y la sección «Solo lectura, por diseño» del README).
 *
 * Así que la dirección del tráfico no cambia. El panel deja la orden anotada
 * aquí; el agente, cuando pasa a reportar, pregunta si le dejaron algo. Nadie
 * llama al sitio. La única puerta sigue siendo la del panel, y sigue estando
 * firmada con el secreto de ese sitio.
 *
 * ─── El precio, dicho de frente ────────────────────────────────────────────
 *
 * Una orden no es instantánea. El agente pregunta cada cinco minutos, y ese
 * reloj en un sitio de WordPress depende de que alguien visite el sitio. En un
 * sitio con poco tráfico una orden puede tardar horas. **No se puede prometer
 * masivo e instantáneo**, y la pantalla lo dice en vez de dejar a nadie
 * mirando una rueda girando.
 *
 * ─── La contraseña del reseteo masivo no se guarda ─────────────────────────
 *
 * Si se guardara en esta tabla, el panel pasaría a ser el único lugar del
 * mundo donde están en claro las contraseñas de administrador de todos los
 * sitios. Que es, literalmente, el problema que el reseteo masivo viene a
 * resolver: «mañana se va alguien y resulta que tiene las contraseñas y todo
 * conectado».
 *
 * Entonces la contraseña no entra en la columna `carga`. Vive aparte, en un
 * transient con fecha de caducidad, y se le entrega al agente en el momento en
 * que pregunta. Cuando el transient vence, las órdenes que no se alcanzaron a
 * entregar se marcan vencidas y hay que volver a pedirlas. Es menos cómodo que
 * guardarla, y es la diferencia entre un panel y un llavero.
 */

defined( 'ABSPATH' ) || exit;

class SWPK_Ordenes {

	public const PENDIENTE = 'pendiente';
	public const ENTREGADA = 'entregada';
	public const HECHA     = 'hecha';
	public const FALLO     = 'fallo';
	public const VENCIDA   = 'vencida';
	public const CANCELADA = 'cancelada';

	/**
	 * Cuánto vive una contraseña de reseteo esperando que el agente pregunte.
	 *
	 * Dos horas es el equilibrio: suficiente para que casi todos los sitios
	 * hayan preguntado al menos una vez, y poco para que no quede ahí el fin
	 * de semana. Un sitio sin visitas en dos horas no alcanza, y eso la
	 * pantalla lo dice antes de que alguien lo descubra.
	 */
	public const VIDA_CLAVE = 2 * HOUR_IN_SECONDS;

	/** Lo que el agente sabe hacer. Pedirle otra cosa se rechaza aquí, no allá. */
	public static function tipos(): array {
		return [
			'activar_dos_pasos'  => 'Activar la verificación en dos pasos',
			'exigir_dos_pasos'   => 'Exigirla a los administradores',
			'apagar_editor'      => 'Apagar el editor de código',
			'cerrar_xmlrpc'      => 'Cerrar XML-RPC',
			'cerrar_registro'    => 'Cerrar el registro de usuarios',
			'resetear_clave'     => 'Cambiar la contraseña de un usuario nuestro',
		];
	}

	/** Qué orden arregla cada norma. Una norma sin orden aquí no es arreglable. */
	public static function orden_de_la_norma( string $norma ): string {
		return match ( $norma ) {
			'dos_pasos_activa' => 'activar_dos_pasos',
			'admins_dos_pasos' => 'exigir_dos_pasos',
			'editor_codigo'    => 'apagar_editor',
			'xmlrpc_cerrado'   => 'cerrar_xmlrpc',
			'registro_cerrado' => 'cerrar_registro',
			default            => '',
		};
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Encolar
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * Deja una orden anotada para un sitio.
	 *
	 * Se niega en tres casos, y los tres importan:
	 *
	 *  1. Un sitio del cliente (`no_update`). La regla escrita es «no se le
	 *     toca nada sin pedirlo». Se mide, no se cambia.
	 *  2. Un sitio sin agente. No hay a quién dejarle la orden.
	 *  3. Una orden igual ya pendiente. Pulsar dos veces no debe encolar dos.
	 *
	 * @param array<string,mixed> $carga
	 */
	public static function encolar(
		string $tipo,
		string $sitio_id,
		array $carga = [],
		string $norma = '',
		string $lote = ''
	): int|WP_Error {
		if ( ! isset( self::tipos()[ $tipo ] ) ) {
			return new WP_Error( 'swpk_orden_tipo', sprintf( 'El agente no sabe hacer «%s».', $tipo ) );
		}

		$sitio = SWPK_Inventario::obtener( $sitio_id );

		if ( ! $sitio ) {
			return new WP_Error( 'swpk_orden_sitio', 'Ese sitio no está en el inventario.' );
		}

		if ( SWPK_Etiquetas::NO_UPDATE === (string) $sitio->tag ) {
			return new WP_Error(
				'swpk_orden_cliente',
				sprintf(
					'%s lo administra el cliente. El panel lo mide, pero no le cambia nada sin que él lo pida — es la regla que ya está escrita.',
					$sitio->nombre_visible ?: $sitio->nombre
				)
			);
		}

		$agente = self::agente_de( $sitio_id );

		if ( ! $agente ) {
			return new WP_Error(
				'swpk_orden_sin_agente',
				sprintf(
					'%s no tiene el agente instalado, así que no hay quién reciba la orden.',
					$sitio->nombre_visible ?: $sitio->nombre
				)
			);
		}

		global $wpdb;
		$tabla = SWPK_Tablas::tabla( 'ordenes' );

		// ── No duplicar, pero sin confundir dos órdenes distintas ───────────
		//
		// La deduplicación existe para que pulsar «Arreglar» dos veces no
		// encole dos. Pero comparar SOLO por tipo tiene un agujero grave, y lo
		// encontramos probándolo: pedir el cambio de contraseña de «pedro»
		// cuando ya había uno pendiente de «ana» devolvía la orden de ana y el
		// panel decía «listo». Se pedía una cosa y pasaba otra.
		//
		// Así que se compara también la carga. Dos órdenes del mismo tipo con
		// distinta carga son dos órdenes distintas, y punto.
		$carga_json = $carga ? wp_json_encode( $carga ) : null;

		// Un cambio de contraseña NUNCA es un duplicado, aunque sea del mismo
		// usuario. La contraseña no viaja en la carga —vive aparte, en el
		// lote— así que dos peticiones de «ana» se ven idénticas aquí y son
		// dos contraseñas distintas. Tratarlas como la misma hacía que valiera
		// la primera y la que la persona acababa de escribir se perdiera en
		// silencio. Así que estas no pasan por la deduplicación: pasan por la
		// sustitución, más abajo, que es lo correcto —manda la última.
		$ya = 'resetear_clave' === $tipo
			? null
			: ( null === $carga_json
			? $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$tabla} WHERE sitio_url = %s AND tipo = %s AND carga IS NULL AND estado IN (%s, %s)",
					$agente->sitio_url,
					$tipo,
					self::PENDIENTE,
					self::ENTREGADA
				)
			)
			: $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$tabla} WHERE sitio_url = %s AND tipo = %s AND carga = %s AND estado IN (%s, %s)",
					$agente->sitio_url,
					$tipo,
					$carga_json,
					self::PENDIENTE,
					self::ENTREGADA
				)
			) );

		if ( $ya ) {
			return (int) $ya;
		}

		// Y un cambio de contraseña del MISMO usuario que todavía no salió se
		// sustituye por el nuevo: la contraseña que vale es la que se acaba de
		// escribir, no la de hace media hora. La vieja queda anotada como
		// sustituida, para que el rastro diga qué pasó en vez de desaparecer.
		if ( 'resetear_clave' === $tipo && ! empty( $carga['login'] ) ) {
			$previas = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT id FROM {$tabla} WHERE sitio_url = %s AND tipo = %s AND estado = %s AND carga LIKE %s",
					$agente->sitio_url,
					$tipo,
					self::PENDIENTE,
					'%' . $wpdb->esc_like( '"login":"' . $carga['login'] . '"' ) . '%'
				)
			) ?: [];

			foreach ( $previas as $vieja ) {
				self::marcar(
					(int) $vieja,
					self::CANCELADA,
					'Se pidió otra vez el cambio de este mismo usuario antes de que el sitio recogiera esta orden. Vale la contraseña nueva.'
				);
			}
		}

		$wpdb->insert(
			$tabla,
			[
				'sitio_id'   => $sitio_id,
				'sitio_url'  => (string) $agente->sitio_url,
				'tipo'       => $tipo,
				'norma'      => $norma,
				'carga'      => $carga_json,
				'estado'     => self::PENDIENTE,
				'lote'       => $lote,
				'pedida_por' => get_current_user_id(),
				'pedida_en'  => current_time( 'mysql' ),
			]
		);

		$id = (int) $wpdb->insert_id;

		SWPK_Eventos::registrar(
			'orden_pedida',
			$sitio_id,
			sprintf( '%s pidió: %s', self::quien( get_current_user_id() ), self::tipos()[ $tipo ] ),
			'info',
			'orden-' . $id
		);

		return $id;
	}

	/**
	 * El reseteo masivo de contraseñas.
	 *
	 * Encola una orden por sitio y guarda la contraseña aparte, una sola vez
	 * para todo el lote. Devuelve el lote y lo que pasó con cada sitio, porque
	 * lo que hay que decirle a quien pulsó el botón es exactamente en cuántos
	 * sitios se va a aplicar y en cuáles no se pudo — no un «listo».
	 *
	 * @param array<int,string> $sitios_id
	 * @return array{lote:string,encoladas:int,rechazos:array<int,string>}|WP_Error
	 */
	public static function reseteo_masivo( array $sitios_id, string $login, string $clave ): array|WP_Error {
		$login = sanitize_user( $login, true );

		if ( '' === $login ) {
			return new WP_Error( 'swpk_reseteo_login', 'Falta decir de qué usuario es la contraseña.' );
		}

		if ( strlen( $clave ) < 12 ) {
			return new WP_Error(
				'swpk_reseteo_clave',
				'La contraseña tiene que tener al menos 12 caracteres. Una contraseña que se va a poner en varios sitios a la vez es el peor lugar para ahorrar.'
			);
		}

		$lote = 'r' . wp_generate_password( 11, false, false );

		// Primero la contraseña, después las órdenes: si se encolaran antes y
		// fallara el guardado, quedarían órdenes esperando una clave que no
		// existe. Así, si esto falla, no se encoló nada.
		set_transient( self::clave_del_lote( $lote ), $clave, self::VIDA_CLAVE );

		$encoladas = 0;
		$rechazos  = [];

		foreach ( $sitios_id as $sitio_id ) {
			$r = self::encolar( 'resetear_clave', (string) $sitio_id, [ 'login' => $login ], '', $lote );

			if ( is_wp_error( $r ) ) {
				$rechazos[] = $r->get_error_message();
				continue;
			}

			++$encoladas;
		}

		if ( 0 === $encoladas ) {
			delete_transient( self::clave_del_lote( $lote ) );

			return new WP_Error(
				'swpk_reseteo_vacio',
				'No se pudo encolar en ningún sitio, así que la contraseña no se guardó. ' . implode( ' ', array_slice( $rechazos, 0, 3 ) )
			);
		}

		SWPK_Eventos::registrar(
			'reseteo_pedido',
			'',
			sprintf(
				'%s pidió cambiar la contraseña de «%s» en %s',
				self::quien( get_current_user_id() ),
				$login,
				SWPK_Vista::plural( $encoladas, 'sitio', 'sitios' )
			),
			'aviso',
			$lote
		);

		return [ 'lote' => $lote, 'encoladas' => $encoladas, 'rechazos' => $rechazos ];
	}

	private static function clave_del_lote( string $lote ): string {
		return 'swpk_clave_lote_' . $lote;
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Entregar y resolver
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * Lo que este sitio tiene pendiente. Lo llama el receptor, ya con la firma
	 * comprobada: aquí no se vuelve a verificar nada porque aquí no se puede.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function entregar( string $sitio_url ): array {
		global $wpdb;
		$tabla = SWPK_Tablas::tabla( 'ordenes' );

		$filas = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$tabla} WHERE sitio_url = %s AND estado = %s ORDER BY id ASC LIMIT 25",
				$sitio_url,
				self::PENDIENTE
			)
		) ?: [];

		$salida = [];
		$ahora  = current_time( 'mysql' );

		foreach ( $filas as $f ) {
			$carga = $f->carga ? (array) json_decode( (string) $f->carga, true ) : [];

			// La contraseña se añade aquí, en el último momento, y nunca estuvo
			// en la tabla. Si ya venció, la orden no se entrega a medias: se
			// marca vencida y se dice por qué.
			if ( 'resetear_clave' === $f->tipo ) {
				$clave = get_transient( self::clave_del_lote( (string) $f->lote ) );

				if ( ! is_string( $clave ) || '' === $clave ) {
					self::marcar(
						(int) $f->id,
						self::VENCIDA,
						'La contraseña de este lote venció antes de que el sitio preguntara. Hay que volver a pedir el cambio.'
					);
					continue;
				}

				$carga['clave'] = $clave;
			}

			$wpdb->update(
				$tabla,
				[ 'estado' => self::ENTREGADA, 'entregada_en' => $ahora ],
				[ 'id' => (int) $f->id ]
			);

			$salida[] = [
				'id'    => (int) $f->id,
				'tipo'  => (string) $f->tipo,
				'norma' => (string) $f->norma,
				'carga' => $carga,
			];
		}

		return $salida;
	}

	/**
	 * Lo que el agente contesta sobre las órdenes que ya ejecutó.
	 *
	 * Se comprueba que cada orden sea de ESE sitio antes de tocarla. Sin eso,
	 * un sitio comprometido podría cerrar como «hecha» una orden de otro — y
	 * el panel diría que un sitio quedó arreglado sin que nadie lo tocara.
	 *
	 * @param array<int,array<string,mixed>> $resultados
	 */
	public static function recibir_resultados( string $sitio_url, array $resultados ): int {
		global $wpdb;
		$tabla   = SWPK_Tablas::tabla( 'ordenes' );
		$cerradas = 0;

		foreach ( $resultados as $r ) {
			$id = (int) ( $r['id'] ?? 0 );

			if ( $id <= 0 ) {
				continue;
			}

			$orden = $wpdb->get_row(
				$wpdb->prepare( "SELECT * FROM {$tabla} WHERE id = %d AND sitio_url = %s", $id, $sitio_url )
			);

			if ( ! $orden || self::ENTREGADA !== $orden->estado ) {
				continue;
			}

			$ok    = ! empty( $r['ok'] );
			$texto = sanitize_text_field( (string) ( $r['resultado'] ?? '' ) );

			self::marcar( $id, $ok ? self::HECHA : self::FALLO, $texto );

			SWPK_Eventos::registrar(
				$ok ? 'orden_hecha' : 'orden_fallo',
				(string) $orden->sitio_id,
				sprintf(
					'%s: %s%s',
					self::tipos()[ (string) $orden->tipo ] ?? $orden->tipo,
					$ok ? 'hecho' : 'falló',
					'' !== $texto ? ' — ' . $texto : ''
				),
				$ok ? 'info' : 'aviso',
				'orden-' . $id
			);

			++$cerradas;
		}

		return $cerradas;
	}

	public static function marcar( int $id, string $estado, string $resultado = '' ): void {
		global $wpdb;

		$wpdb->update(
			SWPK_Tablas::tabla( 'ordenes' ),
			[
				'estado'      => $estado,
				'resultado'   => $resultado,
				'resuelta_en' => current_time( 'mysql' ),
			],
			[ 'id' => $id ]
		);
	}

	/**
	 * Cierra las órdenes que llevan demasiado tiempo entregadas sin respuesta.
	 *
	 * Una orden entregada que nunca se resuelve es un sitio que preguntó, se
	 * llevó la orden y se calló. Dejarla en «entregada» para siempre haría que
	 * el panel mostrara como «en camino» algo que nunca va a llegar.
	 */
	public static function caducar( int $horas = 24 ): int {
		global $wpdb;
		$tabla  = SWPK_Tablas::tabla( 'ordenes' );
		$limite = SWPK_Frescura::limite( $horas * HOUR_IN_SECONDS );

		$viejas = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id FROM {$tabla} WHERE estado = %s AND entregada_en < %s",
				self::ENTREGADA,
				$limite
			)
		) ?: [];

		foreach ( $viejas as $v ) {
			self::marcar(
				(int) $v->id,
				self::FALLO,
				sprintf( 'El sitio se llevó la orden y no contestó en %d horas.', $horas )
			);
		}

		return count( $viejas );
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Leer
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * @param array<string,mixed> $filtros
	 * @return array<int,object>
	 */
	public static function listar( array $filtros = [], int $limite = 200 ): array {
		global $wpdb;
		$tabla = SWPK_Tablas::tabla( 'ordenes' );

		$donde = [ '1=1' ];
		$args  = [];

		if ( ! empty( $filtros['estado'] ) ) {
			$donde[] = 'estado = %s';
			$args[]  = (string) $filtros['estado'];
		}

		if ( ! empty( $filtros['sitio_id'] ) ) {
			$donde[] = 'sitio_id = %s';
			$args[]  = (string) $filtros['sitio_id'];
		}

		if ( ! empty( $filtros['lote'] ) ) {
			$donde[] = 'lote = %s';
			$args[]  = (string) $filtros['lote'];
		}

		$sql = "SELECT * FROM {$tabla} WHERE " . implode( ' AND ', $donde ) . ' ORDER BY id DESC LIMIT %d';
		$args[] = $limite;

		return $wpdb->get_results( $wpdb->prepare( $sql, ...$args ) ) ?: [];
	}

	/** @return array<string,int> */
	public static function conteos(): array {
		global $wpdb;
		$tabla = SWPK_Tablas::tabla( 'ordenes' );
		$filas = $wpdb->get_results( "SELECT estado, COUNT(*) AS n FROM {$tabla} GROUP BY estado" ) ?: [];

		$conteos = [
			self::PENDIENTE => 0,
			self::ENTREGADA => 0,
			self::HECHA     => 0,
			self::FALLO     => 0,
			self::VENCIDA   => 0,
			self::CANCELADA => 0,
		];

		foreach ( $filas as $f ) {
			$conteos[ (string) $f->estado ] = (int) $f->n;
		}

		return $conteos;
	}

	public static function agente_de( string $sitio_id ): ?object {
		global $wpdb;
		$tabla = SWPK_Tablas::tabla( 'agentes' );

		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$tabla} WHERE sitio_id = %s", $sitio_id )
		) ?: null;
	}

	public static function etiqueta( string $estado ): string {
		return match ( $estado ) {
			self::PENDIENTE => 'Esperando que el sitio pregunte',
			self::ENTREGADA => 'El sitio ya se la llevó',
			self::HECHA     => 'Hecho',
			self::FALLO     => 'Falló',
			self::VENCIDA   => 'Venció sin entregarse',
			self::CANCELADA => 'Sustituida por otra',
			default         => $estado,
		};
	}

	private static function quien( int $id ): string {
		$u = get_userdata( $id );

		return $u ? $u->display_name : 'El panel';
	}
}
