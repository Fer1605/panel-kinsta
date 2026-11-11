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
			$manana = strtotime( 'tomorrow 06:30', current_time( 'timestamp' ) );
			wp_schedule_event( $manana, 'daily', self::HOOK );
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

		return $resumen;
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
