<?php
/**
 * La corrida diaria.
 *
 * Orden, y cada paso depende del anterior:
 *   1. Inventario de sitios (etiquetas, versiones de WordPress y PHP)
 *   2. Foto del día: qué plugin y qué versión hay en cada sitio
 *   3. Feed de Wordfence, filtrado a lo que tenemos instalado
 *   4. Comparación con la foto anterior → eventos
 *
 * Nota sobre WP-Cron: se dispara con las visitas al sitio. En el panel —que
 * casi nadie visita— eso significa que la corrida puede no ocurrir. Cuando esté
 * en promos hay que apagar WP-Cron y poner una tarea programada real en Plesk:
 *     define('DISABLE_WP_CRON', true);
 *     wget -q -O - https://promos.../wp-cron.php?doing_wp_cron
 * Mientras se desarrolla en local, el botón "Correr ahora" hace lo mismo a mano.
 */

defined( 'ABSPATH' ) || exit;

class SWPK_Sincronizador {

	public const HOOK = 'swpk_corrida_diaria';

	public static function init(): void {
		add_action( self::HOOK, [ __CLASS__, 'correr' ] );
	}

	public static function programar(): void {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			// 6:30 am hora local: la corrida tiene que estar lista antes del
			// correo de las asesoras de los lunes a las 7:00 (02:34).
			wp_schedule_event( SWPK_Frescura::cuando( 'tomorrow 06:30' ), 'daily', self::HOOK );
		}
	}

	public static function desprogramar(): void {
		$ts = wp_next_scheduled( self::HOOK );
		if ( $ts ) {
			wp_unschedule_event( $ts, self::HOOK );
		}
	}

	/**
	 * @return array<string,mixed> Resumen de lo que hizo, para mostrarlo.
	 */
	public static function correr(): array {
		$resumen = [
			'inicio'           => current_time( 'mysql' ),
			'sitios'           => 0,
			'filas_foto'       => 0,
			'vulnerabilidades' => 0,
			'eventos'          => 0,
			'errores'          => [],
			'notas'            => [],
		];

		$sitios = SWPK_Inventario::sincronizar();
		if ( is_wp_error( $sitios ) ) {
			$resumen['errores'][] = $sitios->get_error_message();
			return $resumen;
		}
		$resumen['sitios'] = (int) $sitios;

		$foto = self::tomar_foto();
		if ( is_wp_error( $foto ) ) {
			$resumen['errores'][] = $foto->get_error_message();
		} else {
			$resumen['filas_foto'] = (int) $foto;
		}

		$wf = SWPK_Wordfence::actualizar();
		if ( is_wp_error( $wf ) ) {
			// Que el feed no haga falta todavía no es un fallo: es el freno
			// haciendo su trabajo. Mezclarlo con los errores de verdad haría que
			// nadie mire los errores de verdad.
			$saltos = [ 'swpk_wf_reciente', 'swpk_wf_en_espera' ];

			if ( in_array( $wf->get_error_code(), $saltos, true ) ) {
				$resumen['notas'][] = $wf->get_error_message();
			} else {
				$resumen['errores'][] = $wf->get_error_message();
			}
		} else {
			$resumen['vulnerabilidades'] = (int) $wf;
		}

		$resumen['eventos'] = SWPK_Eventos::comparar_fotos( current_time( 'Y-m-d' ) );
		$resumen['fin']     = current_time( 'mysql' );

		update_option( 'swpk_ultima_corrida', $resumen, false );

		// Lo que la pantalla de Resumen tenga guardado ya no vale: acaba de
		// cambiar justo lo que resume.
		SWPK_Estado::olvidar();

		return $resumen;
	}

	/**
	 * Vuelve a leer UN sitio, ahora mismo, sin tocar a los demás.
	 *
	 * Lo pidió el jefe: "debe poder consultarse o correrse manualmente los
	 * procesos o consultar un sitio por si alguien pregunta puedo hacerlo
	 * manual y no esperar". Sirve para los 107, porque todo esto sale de la API
	 * del hosting: también para los 13 que administra el cliente.
	 *
	 * ─── Por qué no se puede reusar tomar_foto() ────────────────────────────
	 *
	 * Porque tomar_foto() es destructiva a propósito: borra `inventario_en` de
	 * TODOS los sitios y lo vuelve a llenar con lo que Kinsta conteste en esa
	 * corrida, para que un sitio que Kinsta dejó de reportar aparezca como "sin
	 * datos" y no se quede con la fecha vieja. Correrla para un sitio dejaría a
	 * los otros 106 marcados como si nunca los hubieran escaneado.
	 *
	 * Así que esta hace lo mismo pero acotado: reemplaza las filas de hoy de
	 * ese sitio, actualiza su fecha y la de nadie más, compara contra su propia
	 * foto anterior, y lo sondea de paso.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	public static function analizar_sitio( string $sitio_id ): array|WP_Error {
		global $wpdb;

		$sitio = SWPK_Inventario::obtener( $sitio_id );
		if ( ! $sitio ) {
			return new WP_Error( 'swpk_sin_sitio', 'Ese sitio no está en el inventario.' );
		}

		$fuente  = swpk_fuente();
		$plugins = $fuente->plugins( $sitio_id );
		if ( is_wp_error( $plugins ) ) {
			return $plugins;
		}

		$temas = $fuente->temas( $sitio_id );
		if ( is_wp_error( $temas ) ) {
			return $temas;
		}

		$modulos = array_merge( $plugins, $temas );

		// Una lista vacía NO es "no tiene nada instalado": es "Kinsta no nos
		// dijo". Pasa de verdad — Kinsta contesta con su fecha de escaneo y sin
		// módulos — y si se escribiera, la comparación contra la foto anterior
		// levantaría un "se desinstaló" por cada módulo que el sitio sí tiene.
		//
		// Se descubrió probando este mismo botón: once eventos falsos de golpe,
		// en un sitio al que nadie había tocado. Es el mismo error que el panel
		// ya evita en la corrida diaria, y aquí lo había reintroducido.
		if ( ! $modulos ) {
			return [
				'modulos'   => 0,
				'eventos'   => 0,
				'sondeo'    => (string) ( SWPK_Monitor::sondear_uno( $sitio_id, (string) $sitio->dominio )['estado'] ?? '' ),
				'sin_datos' => true,
			];
		}

		$tabla = SWPK_Tablas::tabla( 'fotos' );
		$fecha = current_time( 'Y-m-d' );

		// Fuera lo de hoy de este sitio antes de escribir: si un módulo se
		// desinstaló desde la corrida de la madrugada, un replace lo dejaría
		// ahí para siempre.
		$wpdb->delete( $tabla, [ 'fecha' => $fecha, 'sitio_id' => $sitio_id ] );

		$n = 0;
		foreach ( $modulos as $fila ) {
			if ( '' === (string) ( $fila['slug'] ?? '' ) ) {
				continue;
			}

			$wpdb->replace(
				$tabla,
				[
					'fecha'              => $fecha,
					'sitio_id'           => $sitio_id,
					'tipo'               => $fila['tipo'],
					'slug'               => $fila['slug'],
					'titulo'             => $fila['titulo'],
					'version'            => $fila['version'],
					'version_disponible' => $fila['version_disponible'],
					'hay_actualizacion'  => $fila['hay_actualizacion'] ? 1 : 0,
					'es_vulnerable'      => $fila['es_vulnerable'] ? 1 : 0,
					'auto_update'        => $fila['auto_update'],
					'activo'             => $fila['activo'] ? 1 : 0,
				]
			);
			++$n;
		}

		// La fecha de este sitio, y la de nadie más.
		$frescura = $fuente->frescura();
		$wpdb->update(
			SWPK_Tablas::tabla( 'sitios' ),
			[ 'inventario_en' => $frescura[ $sitio_id ] ?? null ],
			[ 'sitio_id' => $sitio_id ]
		);

		$eventos = SWPK_Eventos::comparar_fotos( $fecha, $sitio_id );

		// Y de paso, si responde. Es una petición más y es la otra mitad de
		// "cómo está este sitio".
		$r      = SWPK_Monitor::sondear_uno( $sitio_id, (string) $sitio->dominio );
		$sondeo = (string) ( $r['estado'] ?? '' );

		SWPK_Estado::olvidar();

		return [
			'modulos' => $n,
			'eventos' => $eventos,
			'sondeo'  => $sondeo,
			'sin_datos' => empty( $frescura[ $sitio_id ] ),
		];
	}

	/** Guarda la foto del día: una fila por sitio y por plugin o tema. */
	public static function tomar_foto(): int|WP_Error {
		global $wpdb;

		$fuente  = swpk_fuente();
		$plugins = $fuente->plugins();
		if ( is_wp_error( $plugins ) ) {
			return $plugins;
		}

		$temas = $fuente->temas();
		if ( is_wp_error( $temas ) ) {
			return $temas;
		}

		$filas = array_merge( $plugins, $temas );
		$mapa  = self::mapa_de_nombres();
		$tabla = SWPK_Tablas::tabla( 'fotos' );
		$fecha = current_time( 'Y-m-d' );
		$n     = 0;

		foreach ( $filas as $fila ) {
			// La API de empresa identifica el sitio por su nombre visible, no
			// por id. Hay que resolverlo contra el inventario.
			$sitio_id = (string) ( $fila['sitio_id'] ?? '' );
			if ( '' === $sitio_id ) {
				$clave    = self::normalizar_nombre( (string) ( $fila['sitio_nombre'] ?? '' ) );
				$sitio_id = $mapa[ $clave ] ?? '';
			}

			if ( '' === $sitio_id || '' === $fila['slug'] ) {
				continue;
			}

			$wpdb->replace(
				$tabla,
				[
					'fecha'              => $fecha,
					'sitio_id'           => $sitio_id,
					'tipo'               => $fila['tipo'],
					'slug'               => $fila['slug'],
					'titulo'             => $fila['titulo'],
					'version'            => $fila['version'],
					'version_disponible' => $fila['version_disponible'],
					'hay_actualizacion'  => $fila['hay_actualizacion'] ? 1 : 0,
					'es_vulnerable'      => $fila['es_vulnerable'] ? 1 : 0,
					'auto_update'        => $fila['auto_update'],
					'activo'             => $fila['activo'] ? 1 : 0,
				]
			);
			++$n;
		}

		// Guardar cuándo miró Kinsta cada sitio. Un sitio que no aparezca aquí
		// es un sitio del que no tenemos datos, y eso tiene que verse: una lista
		// de plugins vacía no es "está limpio", es "no sabemos".
		$tabla_sitios = SWPK_Tablas::tabla( 'sitios' );
		$wpdb->query( "UPDATE {$tabla_sitios} SET inventario_en = NULL" );

		foreach ( $fuente->frescura() as $sitio_id => $cuando ) {
			$wpdb->update( $tabla_sitios, [ 'inventario_en' => $cuando ], [ 'sitio_id' => $sitio_id ] );
		}

		// Detectar qué sitios ya tienen un plugin de 2FA activo sale gratis de
		// esta misma foto: no cuesta ni una llamada extra (§4.8 del plan).
		self::marcar_cobertura_2fa( $fecha );

		return $n;
	}

	/** @return array<string,string> nombre normalizado => sitio_id */
	private static function mapa_de_nombres(): array {
		$mapa = [];
		foreach ( SWPK_Inventario::todos() as $sitio ) {
			$mapa[ self::normalizar_nombre( $sitio->nombre_visible ) ] = $sitio->sitio_id;
			$mapa[ self::normalizar_nombre( $sitio->nombre ) ]         = $sitio->sitio_id;
		}
		return $mapa;
	}

	private static function normalizar_nombre( string $nombre ): string {
		return strtolower( trim( remove_accents( $nombre ) ) );
	}

	/**
	 * Plugins de 2FA conocidos. Si alguno está activo en un sitio, ese sitio ya
	 * tiene cobertura y no hace falta volver a tocarlo.
	 */
	public static function plugins_2fa(): array {
		return [
			'two-factor'                   => 'Two-Factor',
			'wp-2fa'                       => 'WP 2FA',
			'wordfence-login-security'     => 'Wordfence Login Security',
			'miniorange-2-factor-authentication' => 'miniOrange 2FA',
			'google-authenticator'         => 'Google Authenticator',
			'agente-sitio'                 => 'Agente Solución Web',
		];
	}

	private static function marcar_cobertura_2fa( string $fecha ): void {
		global $wpdb;

		$fotos  = SWPK_Tablas::tabla( 'fotos' );
		$sitios = SWPK_Tablas::tabla( 'sitios' );
		$slugs  = array_keys( self::plugins_2fa() );

		$marcadores = implode( ',', array_fill( 0, count( $slugs ), '%s' ) );

		$encontrados = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT sitio_id, slug FROM {$fotos} WHERE fecha = %s AND activo = 1 AND slug IN ({$marcadores})",
				array_merge( [ $fecha ], $slugs )
			)
		) ?: [];

		$wpdb->query( "UPDATE {$sitios} SET tiene_2fa = 0, plugin_2fa = '', estado_2fa = 'sin_2fa'" );

		foreach ( $encontrados as $f ) {
			$wpdb->update(
				$sitios,
				[
					'tiene_2fa'  => 1,
					'plugin_2fa' => self::plugins_2fa()[ $f->slug ] ?? $f->slug,
					'estado_2fa' => 'activa',
				],
				[ 'sitio_id' => $f->sitio_id ]
			);
		}
	}

	public static function ultima_corrida(): array {
		$v = get_option( 'swpk_ultima_corrida', [] );
		return is_array( $v ) ? $v : [];
	}
}
