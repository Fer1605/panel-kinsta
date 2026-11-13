<?php
/**
 * Los eventos: lo que cambió de un día para otro.
 *
 * "Mira, en esta corrida del día pasó esto" (04:34). Kinsta no guarda historial
 * consultable, así que la historia la construye el panel comparando el inventario de
 * hoy con la de ayer. Antes de el primer inventario no hay nada, y eso hay que
 * decirlo en pantalla en vez de mostrar un cero que parece un dato.
 */

defined( 'ABSPATH' ) || exit;

class SWPK_Eventos {

	public const ACTUALIZADO     = 'plugin_actualizado';
	public const PENDIENTE       = 'actualizacion_pendiente';
	public const VULNERABLE      = 'vulnerabilidad_detectada';
	public const RESUELTA        = 'vulnerabilidad_resuelta';
	public const NUEVO_PLUGIN    = 'plugin_instalado';
	public const PLUGIN_QUITADO  = 'plugin_desinstalado';
	public const ADMIN_NUEVO     = 'administrador_nuevo';
	public const ROL_SUBIO       = 'usuario_subio_de_rol';
	public const CORREO_EXTRANO  = 'correo_de_dominio_extrano';
	public const SITIO_CAIDO     = 'sitio_caido';
	public const SITIO_REPUESTO  = 'sitio_repuesto';

	/**
	 * Lo que NO hace falta mirar: son buenas noticias.
	 *
	 * Un plugin que se actualizó solo es el sistema funcionando. Si cada uno se
	 * lleva su renglón, un día normal son 128 renglones de cosas que salieron
	 * bien, y lo que sí hay que atender queda enterrado entre ellas.
	 *
	 * Estas se cuentan y se resumen en una línea; el resto se enseña una por
	 * sitio.
	 */
	public static function rutinarios(): array {
		return [ self::ACTUALIZADO, self::RESUELTA, self::SITIO_REPUESTO ];
	}

	public static function es_rutinario( string $tipo ): bool {
		return in_array( $tipo, self::rutinarios(), true );
	}

	public static function etiquetas(): array {
		return [
			self::ACTUALIZADO    => 'Se actualizó',
			self::PENDIENTE      => 'Actualización pendiente',
			self::VULNERABLE     => 'Vulnerabilidad detectada',
			self::RESUELTA       => 'Vulnerabilidad resuelta',
			self::NUEVO_PLUGIN   => 'Plugin instalado',
			self::PLUGIN_QUITADO => 'Plugin desinstalado',
			self::ADMIN_NUEVO    => 'Administrador nuevo',
			self::ROL_SUBIO      => 'Subió de rol',
			self::CORREO_EXTRANO => 'Correo de dominio extraño',
			self::SITIO_CAIDO    => 'Sitio caído',
			self::SITIO_REPUESTO => 'Sitio repuesto',
			'agente_registrado'  => 'Plugin instalado en el sitio',
			'administrador_eliminado' => 'Administrador eliminado',
		];
	}

	/**
	 * Anota un evento. Y si no puede, lo dice.
	 *
	 * El insert puede fallar —un texto más largo que su columna, la base llena,
	 * la tabla sin migrar— y durante un tiempo esto lo dejó pasar callado: el
	 * evento simplemente no existía, y nadie tenía forma de saber que faltaba.
	 * Un panel cuyo trabajo es avisar no puede perder avisos en silencio, así
	 * que un fallo aquí queda anotado y sale en Ajustes.
	 *
	 * Se descubrió así: se alargó el texto de "administrador nuevo" por encima
	 * de los 191 caracteres de su columna, el evento dejó de guardarse, y el
	 * panel siguió como si nada.
	 */
	public static function registrar(
		string $tipo,
		string $sitio_id,
		string $titulo,
		string $severidad = 'info',
		string $slug = '',
		array $detalle = []
	): bool {
		global $wpdb;

		// Un mismo cambio no se anota dos veces el mismo día.
		//
		// El botón de "analizar este sitio ahora" vuelve a comparar contra la
		// inventario anterior, así que pulsarlo dos veces registraba otra vez los
		// mismos cambios. Con 23 módulos eran 30 eventos donde había 10.
		//
		// Se compara por tipo, sitio, módulo y día. Las caídas no traen módulo
		// y quedan fuera de la regla a propósito: un sitio puede caerse y
		// reponerse varias veces en el mismo día, y cada vez es noticia.
		if ( '' !== $slug && self::ya_anotado( $tipo, $sitio_id, $slug ) ) {
			return true;
		}

		$ok = $wpdb->insert(
			SWPK_Tablas::tabla( 'eventos' ),
			[
				'ocurrio_en' => current_time( 'mysql' ),
				'sitio_id'   => $sitio_id,
				'tipo'       => $tipo,
				'severidad'  => $severidad,
				'slug'       => $slug,
				'titulo'     => $titulo,
				'detalle'    => $detalle ? wp_json_encode( $detalle ) : null,
			]
		);

		if ( false === $ok ) {
			self::anotar_fallo( $tipo, $titulo, (string) $wpdb->last_error );
			return false;
		}

		return true;
	}

	/** ¿Ya está anotado hoy este mismo cambio, en este mismo módulo? */
	private static function ya_anotado( string $tipo, string $sitio_id, string $slug ): bool {
		global $wpdb;
		$tabla = SWPK_Tablas::tabla( 'eventos' );

		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$tabla}
				 WHERE tipo = %s AND sitio_id = %s AND slug = %s AND DATE( ocurrio_en ) = %s
				 LIMIT 1",
				$tipo,
				$sitio_id,
				$slug,
				current_time( 'Y-m-d' )
			)
		);
	}

	private const OPCION_FALLOS = 'swpk_eventos_perdidos';

	/** Deja constancia de un evento que no se pudo guardar. */
	private static function anotar_fallo( string $tipo, string $titulo, string $error ): void {
		$fallos = self::fallos();

		array_unshift(
			$fallos,
			[
				'cuando' => current_time( 'mysql' ),
				'tipo'   => $tipo,
				'titulo' => mb_substr( $titulo, 0, 300 ),
				'error'  => mb_substr( $error, 0, 300 ),
			]
		);

		update_option( self::OPCION_FALLOS, array_slice( $fallos, 0, 20 ), false );
		error_log( sprintf( '[Panel Kinsta] No se pudo guardar el evento "%s": %s', $tipo, $error ) );
	}

	/** @return array<int,array<string,string>> */
	public static function fallos(): array {
		$v = get_option( self::OPCION_FALLOS, [] );
		return is_array( $v ) ? $v : [];
	}

	public static function olvidar_fallos(): void {
		delete_option( self::OPCION_FALLOS );
	}

	/**
	 * Compara el inventario de hoy con la anterior y registra los cambios.
	 *
	 * Esto es lo que convierte un inventario en una historia: sin la
	 * comparación, el panel solo sabría decir cómo están las cosas hoy, y lo
	 * que el jefe pidió es "este mes actualizamos seis veces" (01:18).
	 */
	/** @var array<string,bool> sitios cuyo cambio se ignoró por no tener escaneo nuevo */
	private static array $ultimo_salto = [];

	/**
	 * Los sitios que la última comparación se saltó porque Kinsta no los había
	 * vuelto a mirar. Se enseñan en la corrida: saltarlos callado sería cambiar
	 * un ruido por un silencio.
	 *
	 * @return array<int,string>
	 */
	public static function saltados(): array {
		return array_keys( self::$ultimo_salto );
	}

	public static function comparar_fotos( string $fecha_hoy, string $solo = '' ): int {
		global $wpdb;
		$tabla = SWPK_Tablas::tabla( 'fotos' );

		// Con $solo la comparación se acota a un sitio. Lo usa el botón de
		// "analizar este sitio ahora": si comparara la cartera entera, un
		// análisis suelto a media tarde levantaría eventos de los otros 106
		// como si hubieran cambiado en ese momento.
		$fecha_previa = '' !== $solo
			? $wpdb->get_var( $wpdb->prepare( "SELECT MAX(fecha) FROM {$tabla} WHERE fecha < %s AND sitio_id = %s", $fecha_hoy, $solo ) )
			: $wpdb->get_var( $wpdb->prepare( "SELECT MAX(fecha) FROM {$tabla} WHERE fecha < %s", $fecha_hoy ) );

		if ( ! $fecha_previa ) {
			return 0; // Primera corrida: no hay con qué comparar.
		}

		$leer = static function ( string $fecha ) use ( $wpdb, $tabla, $solo ): array {
			return '' !== $solo
				? ( $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$tabla} WHERE fecha = %s AND sitio_id = %s", $fecha, $solo ) ) ?: [] )
				: ( $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$tabla} WHERE fecha = %s", $fecha ) ) ?: [] );
		};

		$hoy    = self::indexar( $leer( $fecha_hoy ) );
		$previo = self::indexar( $leer( (string) $fecha_previa ) );

		$n = 0;

		// De qué sitios teníamos datos antes y de cuáles tenemos ahora. Hace
		// falta en los dos sentidos, porque Kinsta no contesta de los 108 todos
		// los días y su silencio no es una noticia sobre el sitio:
		//
		//   sin datos hoy, con datos antes  -> NO es "se desinstaló todo"
		//   con datos hoy, sin datos antes  -> NO es "se instaló todo"
		//
		// Las dos pasaron de verdad el 12 de noviembre de 2026, en la misma
		// corrida: 402 desinstalados falsos en 25 sitios y 13 instalados falsos
		// en 2. Ninguno de esos módulos se tocó.
		$con_datos_hoy   = [];
		$con_datos_antes = [];
		$escaneo_hoy     = [];
		$escaneo_antes   = [];

		foreach ( $hoy as $fila ) {
			$con_datos_hoy[ $fila->sitio_id ] = true;
			$escaneo_hoy[ $fila->sitio_id ]   = (string) ( $fila->escaneado_en ?? '' );
		}
		foreach ( $previo as $fila ) {
			$con_datos_antes[ $fila->sitio_id ] = true;
			$escaneo_antes[ $fila->sitio_id ]   = (string) ( $fila->escaneado_en ?? '' );
		}

		// Y aquí el juez de verdad: ¿Kinsta volvió a mirar este sitio?
		//
		// Kinsta escanea cada sitio cuando le toca, muy desparejo: medido sobre
		// los 108, el mismo día había 67 escaneados hoy, 27 de hace tres días y
		// uno sin tocar desde abril. Si entre dos inventarios su fecha de escaneo NO
		// avanzó, la lista tendría que ser idéntica. Si cambió igual, el cambio
		// no es del sitio: es de la respuesta.
		//
		// Sin esto había que entrar a comprobarlo a mano cada vez que algo
		// parecía raro, y eso no escala: nadie va a estar mirando.
		$sin_escaneo_nuevo = [];
		foreach ( $con_datos_hoy as $sitio_id => $_ ) {
			if ( ! isset( $con_datos_antes[ $sitio_id ] ) ) {
				continue;
			}

			$antes = $escaneo_antes[ $sitio_id ] ?? '';
			$ahora = $escaneo_hoy[ $sitio_id ] ?? '';

			// Sin fechas no se puede juzgar: se deja pasar, que es como se
			// comportaba antes de guardarlas.
			if ( '' === $antes || '' === $ahora ) {
				continue;
			}

			if ( $ahora <= $antes ) {
				$sin_escaneo_nuevo[ $sitio_id ] = true;
			}
		}

		// Ojo con la diferencia entre estas dos listas, que al principio las
		// confundí y la nota de la corrida salió mintiendo:
		//
		//   $sin_escaneo_nuevo -> sitios que Kinsta no volvió a mirar. Son
		//     muchos y es NORMAL: midiendo los 108, unos 40 al día. No tiene
		//     nada de raro y no hay que contarlo.
		//
		//   self::$ultimo_salto -> sitios donde ADEMÁS la lista venía distinta
		//     y hubo que callar un evento. Eso sí es raro y hay que decirlo.
		//
		// La primera corrida con esto puesto reportó "la lista cambió" de un
		// sitio cuyas dos inventarios eran idénticas. Una nota que se equivoca deja de
		// leerse, y entonces el día que diga algo cierto nadie la va a mirar.
		self::$ultimo_salto = [];

		foreach ( $hoy as $clave => $fila ) {
			if ( ! isset( $previo[ $clave ] ) && isset( $sin_escaneo_nuevo[ $fila->sitio_id ] ) ) {
				// Aparece algo que antes no estaba, sin escaneo nuevo detrás.
				self::$ultimo_salto[ $fila->sitio_id ] = true;
				continue;
			}

			if ( ! isset( $previo[ $clave ] ) ) {
				// Primera vez que tenemos datos de este sitio: lo que hay es lo
				// que hay, no algo que alguien acabara de instalar.
				if ( ! isset( $con_datos_antes[ $fila->sitio_id ] ) ) {
					continue;
				}

				self::registrar(
					self::NUEVO_PLUGIN,
					$fila->sitio_id,
					sprintf( '%s %s se instaló', $fila->titulo, $fila->version ),
					'info',
					$fila->slug
				);
				++$n;
				continue;
			}

			$antes = $previo[ $clave ];

			// Sin escaneo nuevo, ni la versión ni la marca de vulnerable
			// deberían haberse movido. Si se movieron, se movió la respuesta,
			// no el sitio.
			if ( isset( $sin_escaneo_nuevo[ $fila->sitio_id ] )
				&& ( $antes->version !== $fila->version || $antes->es_vulnerable !== $fila->es_vulnerable ) ) {
				self::$ultimo_salto[ $fila->sitio_id ] = true;
				continue;
			}

			if ( $antes->version !== $fila->version && SWPK_Versiones::es_menor( $antes->version, $fila->version ) ) {
				self::registrar(
					self::ACTUALIZADO,
					$fila->sitio_id,
					sprintf( '%s pasó de %s a %s', $fila->titulo, $antes->version, $fila->version ),
					'ok',
					$fila->slug,
					[ 'de' => $antes->version, 'a' => $fila->version ]
				);
				++$n;
			}

			if ( ! $antes->es_vulnerable && $fila->es_vulnerable ) {
				self::registrar(
					self::VULNERABLE,
					$fila->sitio_id,
					sprintf( '%s %s tiene una vulnerabilidad conocida', $fila->titulo, $fila->version ),
					'critico',
					$fila->slug
				);
				++$n;
			}

			if ( $antes->es_vulnerable && ! $fila->es_vulnerable ) {
				self::registrar(
					self::RESUELTA,
					$fila->sitio_id,
					sprintf( '%s ya no está vulnerable (%s)', $fila->titulo, $fila->version ),
					'ok',
					$fila->slug
				);
				++$n;
			}
		}

		foreach ( $previo as $clave => $fila ) {
			// Silencio de Kinsta, no desinstalación. Ver el comentario de arriba.
			if ( ! isset( $con_datos_hoy[ $fila->sitio_id ] ) ) {
				continue;
			}

			if ( ! isset( $hoy[ $clave ] ) && isset( $sin_escaneo_nuevo[ $fila->sitio_id ] ) ) {
				// Falta algo que antes estaba, sin escaneo nuevo detrás.
				self::$ultimo_salto[ $fila->sitio_id ] = true;
				continue;
			}

			if ( ! isset( $hoy[ $clave ] ) ) {
				self::registrar(
					self::PLUGIN_QUITADO,
					$fila->sitio_id,
					sprintf( '%s se desinstaló', $fila->titulo ),
					'info',
					$fila->slug
				);
				++$n;
			}
		}

		return $n;
	}

	private static function indexar( array $filas ): array {
		$mapa = [];
		foreach ( $filas as $fila ) {
			$mapa[ $fila->sitio_id . '|' . $fila->tipo . '|' . $fila->slug ] = $fila;
		}
		return $mapa;
	}

	/** @return array<int,object> */
	public static function recientes( int $limite = 50, string $sitio_id = '' ): array {
		global $wpdb;
		$tabla = SWPK_Tablas::tabla( 'eventos' );

		if ( '' !== $sitio_id ) {
			return $wpdb->get_results(
				$wpdb->prepare( "SELECT * FROM {$tabla} WHERE sitio_id = %s ORDER BY ocurrio_en DESC, id DESC LIMIT %d", $sitio_id, $limite )
			) ?: [];
		}

		return $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$tabla} ORDER BY ocurrio_en DESC, id DESC LIMIT %d", $limite )
		) ?: [];
	}

	/**
	 * Los movimientos de un rango de fechas, con filtros.
	 *
	 * El jefe lo pidió así en la reunión: "quiero saber cuántas veces se le
	 * notificó al cliente en el mes o en la semana. O en rango de fechas"
	 * (01:29) y "este mes actualizamos seis veces tal proyecto" (01:18).
	 *
	 * Inicio enseña los últimos días y agrupado, que es para enterarse. Esto es
	 * para buscar: un sitio, un tipo, un periodo.
	 *
	 * @return array<int,object>
	 */
	public static function entre( string $desde, string $hasta, string $sitio_id = '', string $tipo = '', int $limite = 1000 ): array {
		global $wpdb;
		$tabla = SWPK_Tablas::tabla( 'eventos' );

		$donde  = [ 'ocurrio_en BETWEEN %s AND %s' ];
		$params = [ $desde . ' 00:00:00', $hasta . ' 23:59:59' ];

		if ( '' !== $sitio_id ) {
			$donde[]  = 'sitio_id = %s';
			$params[] = $sitio_id;
		}

		if ( '' !== $tipo ) {
			$donde[]  = 'tipo = %s';
			$params[] = $tipo;
		}

		$params[] = $limite;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$tabla} WHERE " . implode( ' AND ', $donde ) . ' ORDER BY ocurrio_en DESC, id DESC LIMIT %d',
				...$params
			)
		) ?: [];
	}

	/** Cuántas veces se actualizó algo en un sitio dentro de un rango. */
	public static function conteo_actualizaciones( string $sitio_id, string $desde, string $hasta ): int {
		global $wpdb;
		$tabla = SWPK_Tablas::tabla( 'eventos' );
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$tabla} WHERE sitio_id = %s AND tipo = %s AND ocurrio_en BETWEEN %s AND %s",
				$sitio_id,
				self::ACTUALIZADO,
				$desde,
				$hasta
			)
		);
	}
}
