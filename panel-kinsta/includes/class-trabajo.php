<?php
/**
 * Los trabajos largos, partidos en tramos.
 *
 * ─── Por qué existe esto ────────────────────────────────────────────────────
 *
 * El 18 de noviembre de 2026 el jefe entró al panel recién instalado en
 * producción, pulsó «Correr ahora», y a los pocos minutos le salió un 504 del
 * servidor. No fue mala suerte: era imposible que funcionara.
 *
 * La corrida pide a Kinsta los plugins y los temas de cada sitio, dos
 * peticiones por sitio. Con 107 sitios son 214, y el cliente HTTP frena solo
 * para no pasarse de las 120 por minuto que permite Kinsta. O sea que la
 * corrida tarda MINUTOS por diseño. Una petición web no dura minutos: el
 * servidor la corta mucho antes, y el trabajo se queda a medias.
 *
 * Por qué no se vio antes: NO fue por correr contra datos de prueba. El panel
 * de desarrollo tiene la misma llave y la misma cartera, y la corrida tarda
 * ahí los mismos minutos. Se escapó porque la corrida se probaba siempre desde
 * la línea de comandos, donde no hay servidor web que corte. Nadie había
 * pulsado el botón en un navegador con los 108 sitios delante.
 *
 * Es la segunda vez en dos días que muerde lo mismo —la otra fue la clave de
 * registro, que se sacaba con un comando en vez de leerla en la pantalla donde
 * el agente decía que estaba—. La regla, entonces, escrita donde se vea:
 * **lo que va a usar una persona se prueba por donde lo va a usar esa
 * persona.** Si el camino de quien prueba no es el camino de quien usa, el
 * hueco no se ve.
 *
 * ─── Cómo se arregla ────────────────────────────────────────────────────────
 *
 * El botón deja de hacer el trabajo. Lo único que hace es APUNTAR que hay
 * trabajo pendiente, y devolver la pantalla enseguida. A partir de ahí el
 * trabajo avanza a tramos cortos, y cada tramo deja escrito dónde se quedó.
 *
 * Un tramo se mide con reloj, no con cantidad de sitios. Eso importa: si un día
 * Kinsta contesta lento, un tramo de «veinte sitios» duraría el triple y
 * volveríamos al mismo 504. Un tramo de «doce segundos» dura doce segundos
 * conteste Kinsta como conteste; simplemente avanza menos.
 *
 * Quién empuja los tramos, por orden:
 *
 *   1. La pantalla, mientras esté abierta. Es lo que hace que se vea una barra
 *      que avanza en vez de un navegador congelado.
 *   2. El reloj de WordPress, si la pantalla se cerró. Por eso cerrar la
 *      pestaña no cancela nada: el trabajo sigue y se termina solo.
 *
 * ─── Lo que esto arregla de paso ────────────────────────────────────────────
 *
 * La corrida vieja borraba la fecha de escaneo de los 107 sitios y la volvía a
 * llenar al final. Si moría a la mitad —que es justo lo que pasaba— dejaba
 * medio inventario marcado como «nunca escaneado». Aquí la fecha de cada sitio
 * se va anotando conforme se lee, y el borrado de los que no aparecieron ocurre
 * al cerrar, cuando ya se leyeron todos. Un trabajo interrumpido ya no miente.
 */

defined( 'ABSPATH' ) || exit;

class SWPK_Trabajo {

	public const OPCION = 'swpk_trabajo';
	public const HOOK   = 'swpk_empujar_trabajo';

	/** Para que dos no avancen el mismo trabajo a la vez. Ver candado(). */
	private const CANDADO = 'swpk_trabajo_candado';

	/**
	 * Cuánto trabaja un tramo antes de devolver el control.
	 *
	 * Doce segundos. Da margen de sobra contra cualquier límite de servidor
	 * —el más apretado que se ve en la práctica son treinta— y es poco
	 * suficiente para que la barra se vea moverse y no parezca colgada.
	 */
	private const SEGUNDOS = 12;

	/**
	 * Sin latido en este tiempo, el trabajo se da por muerto.
	 *
	 * Pasa si el servidor mata el proceso a mitad de un tramo. Sin esto, un
	 * trabajo muerto bloquearía el botón para siempre y nadie sabría por qué.
	 */
	private const MUERTO = 180;

	public static function init(): void {
		add_action( self::HOOK, [ __CLASS__, 'empujar' ] );
		add_action( 'wp_ajax_swpk_avanzar', [ __CLASS__, 'ajax' ] );

		// El relevo entre tramos. Va sin sesión a propósito: lo llama el propio
		// panel contra sí mismo, no una persona. Lo que lo protege es la llave
		// del trabajo, no una cookie. Ver encadenar().
		add_action( 'wp_ajax_nopriv_swpk_relevo', [ __CLASS__, 'relevo' ] );
		add_action( 'wp_ajax_swpk_relevo', [ __CLASS__, 'relevo' ] );
	}

	// ── Estado ──────────────────────────────────────────────────────────────

	/** @return array<string,mixed>|null */
	public static function estado(): ?array {
		$t = get_option( self::OPCION, null );

		return is_array( $t ) && isset( $t['paso'] ) ? $t : null;
	}

	public static function corriendo(): bool {
		$t = self::estado();

		return $t && 'corriendo' === $t['estado'];
	}

	/** Un trabajo que dejó de latir hace rato ya no va a volver solo. */
	public static function colgado(): bool {
		$t = self::estado();

		return $t && 'corriendo' === $t['estado'] && ( time() - (int) $t['latido'] ) > self::MUERTO;
	}

	public static function olvidar(): void {
		delete_option( self::OPCION );
		self::soltar_candado();
	}

	private static function guardar( array $t ): void {
		// Sin autoload: esto cambia cada pocos segundos mientras corre y no
		// tiene por qué viajar en cada petición del escritorio.
		update_option( self::OPCION, $t, false );
	}

	// ── Empezar ─────────────────────────────────────────────────────────────

	/**
	 * Apunta que hay trabajo. No hace nada pesado: vuelve enseguida.
	 *
	 * Si ya hay uno corriendo y vivo, devuelve ese. Dos corridas a la vez
	 * escribirían la misma foto del mismo día desde dos sitios.
	 *
	 * @return array<string,mixed>
	 */
	public static function empezar(): array {
		if ( self::corriendo() && ! self::colgado() ) {
			return self::publico( self::estado() );
		}

		$t = [
			'paso'     => 'sitios',
			'estado'   => 'corriendo',
			'cola'     => [],
			'vistos'   => [],
			'hechos'   => 0,
			'total'    => 0,
			'filas'    => 0,
			'fallos'   => 0,
			'resumen'  => [
				'inicio'           => current_time( 'mysql' ),
				'sitios'           => 0,
				'filas_foto'       => 0,
				'vulnerabilidades' => 0,
				'eventos'          => 0,
				'errores'          => [],
				'notas'            => [],
			],
			'latido'   => time(),
			// Para que el panel pueda pedirse a sí mismo el siguiente tramo
			// sin sesión de nadie. Nace y muere con el trabajo.
			'llave'    => wp_generate_password( 32, false ),
		];

		self::guardar( $t );

		// Que arranque solo, sin esperar a que nadie lo empuje. Así el botón
		// funciona aunque el navegador no ejecute el guion de la barra.
		self::encadenar( (string) $t['llave'] );

		// Y la red de seguridad, por si el sitio no puede llamarse a sí mismo.
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_single_event( time() + 90, self::HOOK );
		}

		return self::publico( $t );
	}

	// ── Avanzar ─────────────────────────────────────────────────────────────

	/**
	 * Hace un tramo y devuelve dónde quedó.
	 *
	 * @param int $segundos Cuánto puede durar el tramo. Con 0 no para hasta
	 *                      terminar: es lo que usan el reloj y la línea de
	 *                      comandos, donde no hay navegador esperando.
	 *
	 * @return array<string,mixed>
	 */
	public static function avanzar( int $segundos = self::SEGUNDOS ): array {
		$t = self::estado();

		if ( ! $t || 'corriendo' !== $t['estado'] ) {
			return $t ? self::publico( $t ) : [];
		}

		// Si ya hay alguien avanzando este trabajo, este se aparta. Pasa
		// cuando el reloj entra mientras la pantalla está abierta: sin esto,
		// los dos sacarían sitios de la misma cola y cada uno guardaría su
		// versión, así que parte del avance del otro se perdería.
		if ( ! self::candado() ) {
			// Ojo con el orden: al sumar arreglos gana la clave de la
			// izquierda, así que 'ocupado' tiene que ir delante del publico()
			// que ya trae su propio 'ocupado' => false.
			return [ 'ocupado' => true ] + self::publico( $t );
		}

		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 120 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}

		$hasta = $segundos > 0 ? microtime( true ) + $segundos : INF;

		do {
			switch ( $t['paso'] ) {
				case 'sitios':
					self::paso_sitios( $t );
					break;
				case 'modulos':
					self::paso_modulos( $t, $hasta );
					break;
				case 'cierre':
					self::paso_cierre( $t );
					break;
				case 'wordfence':
					self::paso_wordfence( $t );
					break;
				case 'eventos':
					self::paso_eventos( $t );
					break;
				default:
					self::terminar( $t );
					break;
			}

			self::latir( $t );
		} while ( 'corriendo' === $t['estado'] && microtime( true ) < $hasta );

		self::soltar_candado();

		// Si queda trabajo, que el siguiente tramo no dependa de nadie.
		if ( 'corriendo' === $t['estado'] ) {
			self::encadenar( (string) ( $t['llave'] ?? '' ) );
		}

		return self::publico( $t );
	}

	/**
	 * Pide el siguiente tramo al propio panel, sin esperar la respuesta.
	 *
	 * ─── Por qué hace falta ─────────────────────────────────────────────────
	 *
	 * Sin esto, los tramos dependen de que haya una pantalla abierta o de que
	 * el reloj del servidor pase. Lo primero falla en cuanto alguien cambia de
	 * pestaña: el navegador congela los temporizadores de las pestañas que no
	 * se ven, y la corrida se arrastra. Lo segundo depende de cada hosting —en
	 * Kinsta el reloj real pasa cada quince minutos—, y a ese ritmo «se puede
	 * cerrar la pantalla» sería mentira.
	 *
	 * Así, cada tramo que termina deja pedido el siguiente. La corrida avanza
	 * sola, con pantalla o sin ella, y nadie espera.
	 *
	 * Si el hosting no permite que un sitio se llame a sí mismo, esto no hace
	 * nada y no rompe nada: queda el reloj, que es más lento pero llega.
	 */
	private static function encadenar( string $llave ): void {
		if ( '' === $llave ) {
			return;
		}

		wp_remote_post(
			admin_url( 'admin-ajax.php' ),
			[
				// Lo que importa de esta petición es que salga, no lo que
				// conteste: quien la manda ya terminó su parte.
				'blocking'  => false,
				'timeout'   => 0.01,
				'sslverify' => false,
				'body'      => [
					'action' => 'swpk_relevo',
					'llave'  => $llave,
				],
			]
		);
	}

	/**
	 * El relevo: hace un tramo y, al terminar, pide el siguiente.
	 *
	 * No mira permisos de usuario porque no hay usuario: la manda el panel.
	 * Lo que comprueba es la llave del trabajo en curso, que es aleatoria,
	 * vive solo mientras dura ese trabajo, y no sirve para nada más que para
	 * empujar lo que el panel ya decidió hacer.
	 */
	public static function relevo(): void {
		$t = self::estado();

		if ( ! $t || 'corriendo' !== $t['estado'] ) {
			wp_die( '', '', [ 'response' => 204 ] );
		}

		$dada = isset( $_POST['llave'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['llave'] ) ) : '';

		if ( '' === $dada || ! hash_equals( (string) ( $t['llave'] ?? '' ), $dada ) ) {
			wp_die( '', '', [ 'response' => 403 ] );
		}

		self::avanzar();

		wp_die( '', '', [ 'response' => 204 ] );
	}

	/**
	 * Pide el turno para avanzar. Devuelve false si lo tiene otro.
	 *
	 * Un candado que no se suelta —porque el servidor mató el proceso a mitad
	 * de un tramo— dejaría la corrida parada para siempre, así que caduca solo
	 * al minuto y medio. Es más de lo que dura un tramo y menos de lo que
	 * tarda nadie en darse cuenta.
	 */
	private static function candado(): bool {
		$puesto = add_option( self::CANDADO, time(), '', false );

		if ( $puesto ) {
			return true;
		}

		if ( ( time() - (int) get_option( self::CANDADO, 0 ) ) < 90 ) {
			return false;
		}

		update_option( self::CANDADO, time(), false );

		return true;
	}

	private static function soltar_candado(): void {
		delete_option( self::CANDADO );
	}

	/**
	 * «Sigo vivo»: la hora, lo avanzado y el candado, de una sola vez.
	 *
	 * El candado hay que refrescarlo aquí y no solo al tomarlo. Si no, un
	 * tramo largo lo dejaría caducar mientras sigue trabajando, otro entraría
	 * creyéndolo libre, y volveríamos a tener dos avanzando la misma cola.
	 */
	private static function latir( array &$t ): void {
		$t['latido'] = time();

		self::guardar( $t );
		update_option( self::CANDADO, time(), false );
	}

	/**
	 * El empujón del reloj, para cuando ya no hay pantalla abierta.
	 *
	 * Solo entra si hace rato que nadie lo empuja: si la pantalla está abierta
	 * y avanzando, este se aparta y no duplica trabajo.
	 */
	public static function empujar(): void {
		if ( ! self::corriendo() ) {
			return;
		}

		$t = self::estado();

		if ( ( time() - (int) $t['latido'] ) > 90 ) {
			self::avanzar( 60 );
		}

		if ( self::corriendo() && ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_single_event( time() + 90, self::HOOK );
		}
	}

	// ── Los pasos ───────────────────────────────────────────────────────────

	private static function paso_sitios( array &$t ): void {
		$n = SWPK_Inventario::sincronizar();

		if ( is_wp_error( $n ) ) {
			$t['resumen']['errores'][] = $n->get_error_message();
			$t['estado']               = 'error';
			return;
		}

		$t['resumen']['sitios'] = (int) $n;

		foreach ( SWPK_Inventario::todos() as $sitio ) {
			$t['cola'][] = (string) $sitio->sitio_id;
		}

		$t['total'] = count( $t['cola'] );
		$t['paso']  = 'modulos';
	}

	/**
	 * El paso largo: los plugins y los temas, sitio por sitio.
	 *
	 * Guarda después de CADA sitio, no al final del tramo. Si el servidor corta
	 * el proceso justo aquí, lo leído hasta ese sitio ya está escrito y el
	 * siguiente tramo sigue desde el que faltaba, sin repetir llamadas.
	 */
	private static function paso_modulos( array &$t, float $hasta ): void {
		// Ojo con la condición: se comprueba el reloj DESPUÉS de hacer un
		// sitio, no antes. Si se comprobara antes, un tramo que llega con el
		// tiempo ya gastado no haría ni un solo sitio, la cola no bajaría, y
		// la corrida se quedaría quieta para siempre sin dar ningún error.
		// Así, un tramo siempre adelanta al menos un sitio.
		while ( $t['cola'] ) {
			$sitio_id = (string) array_shift( $t['cola'] );
			$r        = SWPK_Sincronizador::foto_de_un_sitio( $sitio_id );

			$t['filas']  += (int) $r['filas'];
			$t['vistos']  = array_merge( $t['vistos'], $r['frescura'] );
			$t['fallos'] += $r['fallo'] ? 1 : 0;
			++$t['hechos'];

			self::latir( $t );

			if ( microtime( true ) >= $hasta ) {
				break;
			}
		}

		if ( ! $t['cola'] ) {
			$t['paso'] = 'cierre';
		}
	}

	private static function paso_cierre( array &$t ): void {
		SWPK_Sincronizador::cerrar_foto( $t['vistos'] );

		$t['resumen']['filas_foto'] = (int) $t['filas'];

		if ( $t['fallos'] ) {
			$t['resumen']['notas'][] = sprintf(
				'%d sitio(s) no contestaron y se saltaron; su dato queda como estaba.',
				(int) $t['fallos']
			);
		}

		$t['paso'] = 'wordfence';
	}

	private static function paso_wordfence( array &$t ): void {
		$wf = SWPK_Wordfence::actualizar();

		if ( is_wp_error( $wf ) ) {
			// Que el feed no haga falta todavía no es un fallo: es el freno
			// haciendo su trabajo. Mezclarlo con los errores de verdad haría
			// que nadie mire los errores de verdad.
			$saltos = [ 'swpk_wf_reciente', 'swpk_wf_en_espera' ];

			if ( in_array( $wf->get_error_code(), $saltos, true ) ) {
				$t['resumen']['notas'][] = $wf->get_error_message();
			} else {
				$t['resumen']['errores'][] = $wf->get_error_message();
			}
		} else {
			$t['resumen']['vulnerabilidades'] = (int) $wf;
		}

		$t['paso'] = 'eventos';
	}

	private static function paso_eventos( array &$t ): void {
		$t['resumen']['eventos'] = SWPK_Eventos::comparar_fotos( current_time( 'Y-m-d' ) );

		// Los sitios cuyo cambio se ignoró por no tener escaneo nuevo salen en
		// las notas. Saltarlos sin decirlo sería cambiar un ruido por un
		// silencio, y el silencio es peor: nadie lo nota.
		$saltados = SWPK_Eventos::saltados();

		if ( $saltados ) {
			$nombres = [];
			foreach ( array_slice( $saltados, 0, 8 ) as $sitio_id ) {
				$s         = SWPK_Inventario::obtener( (string) $sitio_id );
				$nombres[] = (string) ( $s->nombre_visible ?? $sitio_id );
			}

			$t['resumen']['notas'][] = sprintf(
				'De %d sitio(s) la lista cambió pero %s no los había vuelto a escanear, así que no se tomó por bueno: %s%s.',
				count( $saltados ),
				SWPK_Admin::proveedor(),
				implode( ', ', $nombres ),
				count( $saltados ) > 8 ? '…' : ''
			);
		}

		// Las órdenes que un sitio se llevó y nunca contestó se cierran aquí.
		$t['resumen']['ordenes_caducadas'] = SWPK_Ordenes::caducar();

		// Con la foto recién guardada se puede decir qué es cada sitio.
		$t['resumen']['tipos'] = SWPK_Tipos::clasificar_todos();

		$t['paso'] = 'fin';
	}

	private static function terminar( array &$t ): void {
		$t['resumen']['fin'] = current_time( 'mysql' );
		$t['estado']         = 'hecho';

		update_option( 'swpk_ultima_corrida', $t['resumen'], false );

		// Lo que la pantalla de Resumen tenga guardado ya no vale: acaba de
		// cambiar justo lo que resume.
		SWPK_Estado::olvidar();
	}

	// ── Lo que ve la pantalla ───────────────────────────────────────────────

	/** @return array<string,mixed> */
	public static function publico( ?array $t ): array {
		if ( ! $t ) {
			return [ 'estado' => 'ninguno' ];
		}

		return [
			'ocupado'    => false,
			'estado'     => (string) $t['estado'],
			'paso'       => (string) $t['paso'],
			'hechos'     => (int) $t['hechos'],
			'total'      => (int) $t['total'],
			'porcentaje' => self::porcentaje( $t ),
			'texto'      => self::texto( $t ),
			'resumen'    => $t['resumen'],
		];
	}

	private static function porcentaje( array $t ): int {
		if ( 'hecho' === $t['estado'] ) {
			return 100;
		}

		if ( 'error' === $t['estado'] ) {
			return 100;
		}

		// El peso está repartido como tarda de verdad: leer los sitios uno por
		// uno es casi toda la corrida, y lo demás son segundos.
		return match ( $t['paso'] ) {
			'sitios'    => 2,
			'modulos'   => $t['total'] > 0 ? (int) ( 5 + 83 * $t['hechos'] / $t['total'] ) : 5,
			'cierre'    => 89,
			'wordfence' => 93,
			'eventos'   => 97,
			default     => 99,
		};
	}

	private static function texto( array $t ): string {
		if ( 'hecho' === $t['estado'] ) {
			return 'Listo.';
		}

		if ( 'error' === $t['estado'] ) {
			return 'Se detuvo: ' . implode( ' · ', (array) $t['resumen']['errores'] );
		}

		return match ( $t['paso'] ) {
			'sitios'    => 'Leyendo la lista de sitios…',
			'modulos'   => sprintf( 'Revisando sitios: %d de %d', (int) $t['hechos'], (int) $t['total'] ),
			'cierre'    => 'Ordenando lo que se leyó…',
			'wordfence' => 'Bajando las vulnerabilidades conocidas…',
			'eventos'   => 'Comparando contra el día anterior…',
			default     => 'Terminando…',
		};
	}

	// ── La puerta que usa la pantalla ───────────────────────────────────────

	public static function ajax(): void {
		if ( ! SWPK_Perfiles::puede( SWPK_Perfiles::ADMINISTRAR ) ) {
			wp_send_json_error( [ 'texto' => 'Sin permiso.' ], 403 );
		}

		check_ajax_referer( 'swpk_avanzar' );

		wp_send_json_success( self::avanzar() );
	}
}
