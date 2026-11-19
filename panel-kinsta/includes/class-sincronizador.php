<?php
/**
 * La corrida diaria.
 *
 * Orden, y cada paso depende del anterior:
 *   1. Inventario de sitios (etiquetas, versiones de WordPress y PHP)
 *   2. Inventario del día: qué plugin y qué versión hay en cada sitio
 *   3. Feed de Wordfence, filtrado a lo que tenemos instalado
 *   4. Comparación con el inventario anterior → eventos
 *
 * ─── Esto no corre de un tirón, y no puede ───────────────────────────────────
 *
 * Son dos peticiones a Kinsta por sitio. Con 107 sitios, 214, y el cliente HTTP
 * frena solo para no pasarse del límite por minuto: la corrida tarda minutos.
 * Una petición web no dura minutos. Por eso el trabajo lo lleva SWPK_Trabajo,
 * que lo parte en tramos cortos y anota dónde se quedó; aquí solo viven los
 * pasos, no el motor que los empuja.
 *
 * Ahí está contado por qué, y qué pasó el día que se descubrió.
 *
 * Nota sobre WP-Cron: se dispara con las visitas al sitio. En un panel que casi
 * nadie visita, eso significa que la corrida de las 6:30 puede no ocurrir. En
 * producción hay que apagarlo y poner una tarea de verdad en el servidor:
 *     define('DISABLE_WP_CRON', true);
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
		SWPK_Trabajo::empezar();

		// Un empujón largo, pero con reloj.
		//
		// La tentación es dejarlo correr hasta terminar, ya que aquí no hay
		// navegador esperando. Es un error: también la línea de comandos y el
		// reloj de WordPress corren bajo un límite de tiempo, y si se pasa, el
		// proceso muere a media corrida. Se comprobó en local el 18 de
		// noviembre de 2026: un límite de 120 segundos tumbó la corrida entera.
		//
		// Cuatro minutos caben de sobra bajo el límite de cualquier servidor
		// razonable. Lo que no quepa aquí no se pierde: el trabajo queda
		// anotado y SWPK_Trabajo::empujar() lo sigue hasta el final.
		SWPK_Trabajo::avanzar( 240 );

		return self::ultima_corrida();
	}

	/**
	 * Vuelve a leer UN sitio, ahora mismo, sin tocar a los demás.
	 *
	 * Lo pidió el jefe: "debe poder consultarse o correrse manualmente los
	 * procesos o consultar un sitio por si alguien pregunta puedo hacerlo
	 * manual y no esperar". Sirve para los 107, porque todo esto sale de la API
	 * del hosting: también para los 13 que administra el cliente.
	 *
	 * Lee lo mismo que lee la corrida para cada sitio —de hecho usa el mismo
	 * método— y además compara contra el día anterior y lo revisa por fuera, que
	 * es lo que hace falta para que la ficha quede entera y no a medias.
	 *
	 * Esta sí cabe en una petición web: son dos llamadas a Kinsta, no 214.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	public static function analizar_sitio( string $sitio_id ): array|WP_Error {
		global $wpdb;

		$sitio = SWPK_Inventario::obtener( $sitio_id );
		if ( ! $sitio ) {
			return new WP_Error( 'swpk_sin_sitio', 'Ese sitio no está en el inventario.' );
		}

		$r = self::foto_de_un_sitio( $sitio_id );

		if ( $r['fallo'] ) {
			return new WP_Error(
				'swpk_sin_respuesta',
				sprintf( '%s no contestó por este sitio. No se cambió nada.', SWPK_Admin::proveedor() )
			);
		}

		// Una lista vacía NO es "no tiene nada instalado": es "Kinsta no nos
		// dijo". Pasa de verdad —Kinsta contesta con su fecha de escaneo y sin
		// módulos— y si se escribiera, la comparación contra el día anterior
		// levantaría un "se desinstaló" por cada módulo que el sitio sí tiene.
		//
		// Se descubrió probando este mismo botón: once eventos falsos de golpe,
		// en un sitio al que nadie había tocado.
		if ( 0 === (int) $r['filas'] ) {
			return [
				'modulos'   => 0,
				'eventos'   => 0,
				'sondeo'    => self::revisar_por_fuera( $sitio ),
				'sin_datos' => true,
			];
		}

		// La fecha de este sitio, y la de nadie más.
		$wpdb->update(
			SWPK_Tablas::tabla( 'sitios' ),
			[ 'inventario_en' => $r['frescura'][ $sitio_id ] ?? null ],
			[ 'sitio_id' => $sitio_id ]
		);

		$eventos = SWPK_Eventos::comparar_fotos( current_time( 'Y-m-d' ), $sitio_id );
		$sondeo  = self::revisar_por_fuera( $sitio );

		SWPK_Estado::olvidar();

		return [
			'modulos'   => (int) $r['filas'],
			'eventos'   => $eventos,
			'sondeo'    => $sondeo,
			'sin_datos' => empty( $r['frescura'][ $sitio_id ] ),
		];
	}

	/**
	 * Lo que se puede saber de un sitio sin que el hosting nos cuente nada:
	 * si contesta, y cómo está configurado por fuera.
	 *
	 * Corre aunque Kinsta no haya escaneado el sitio, porque no depende de él.
	 * Es la mitad del dato que la ficha puede mostrar igual.
	 *
	 * @return string El estado del sondeo, para enseñarlo.
	 */
	private static function revisar_por_fuera( object $sitio ): string {
		$sitio_id = (string) $sitio->sitio_id;
		$dominio  = (string) $sitio->dominio;

		$estado = (string) ( SWPK_Monitor::sondear_uno( $sitio_id, $dominio )['estado'] ?? '' );

		SWPK_Sonda::sondear_uno( $sitio_id, $dominio );

		$cuales = SWPK_Normas::COMPROBADORES_DE_SONDA;
		if ( ! (int) $sitio->cobertura_agente ) {
			$cuales[] = SWPK_Normas::COMPROBADOR_DE_AMBOS;
		}

		SWPK_Normas::medir( $sitio_id, [], $cuales );

		return $estado;
	}

	/**
	 * La foto de UN sitio: sus plugins y sus temas, escritos ya.
	 *
	 * Es el ladrillo con el que el motor de trabajos arma la corrida completa,
	 * de a poco. Lo que antes era un solo viaje de 214 peticiones ahora son 107
	 * viajes de dos, y entre uno y otro el servidor puede respirar.
	 *
	 * No compara contra nada ni levanta eventos: eso se hace al final, cuando
	 * ya están los 107. Aquí solo se lee y se escribe.
	 *
	 * @return array{filas:int,frescura:array<string,string>,fallo:bool}
	 */
	public static function foto_de_un_sitio( string $sitio_id ): array {
		global $wpdb;

		$fuente  = swpk_fuente();
		$plugins = $fuente->plugins( $sitio_id );
		$temas   = $fuente->temas( $sitio_id );

		if ( is_wp_error( $plugins ) || is_wp_error( $temas ) ) {
			// Un sitio que falle no tumba la corrida: se anota y se sigue. Peor
			// que un dato incompleto es no tener ninguno.
			return [ 'filas' => 0, 'frescura' => [], 'fallo' => true ];
		}

		$modulos  = array_merge( $plugins, $temas );
		$frescura = $fuente->frescura();

		// Una lista vacía NO es "no tiene nada instalado": es "Kinsta no nos
		// dijo". Si se escribiera, la comparación contra el día anterior
		// levantaría un "se desinstaló" por cada módulo que el sitio sí tiene.
		if ( ! $modulos ) {
			return [ 'filas' => 0, 'frescura' => $frescura, 'fallo' => false ];
		}

		$tabla = SWPK_Tablas::tabla( 'fotos' );
		$fecha = current_time( 'Y-m-d' );

		// Fuera lo de hoy de este sitio antes de escribir: si un módulo se
		// desinstaló desde la corrida anterior, un replace lo dejaría ahí.
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
					'escaneado_en'       => $frescura[ $sitio_id ] ?? null,
				]
			);
			++$n;
		}

		return [ 'filas' => $n, 'frescura' => $frescura, 'fallo' => false ];
	}

	/**
	 * Cierra la foto: marca de qué sitios NO se supo nada.
	 *
	 * Esto es lo que antes se hacía con un borrado a todos antes de empezar, y
	 * era una trampa: si la corrida moría a la mitad —que es exactamente lo que
	 * pasaba en producción— dejaba medio inventario marcado como "nunca
	 * escaneado", que es una mentira distinta de "no sabemos todavía".
	 *
	 * Ahora el borrado ocurre aquí, cuando ya se leyeron todos: los que no
	 * aparecieron en ninguna respuesta son, de verdad, los que no sabemos.
	 *
	 * @param array<string,string> $vistos sitio_id => cuándo lo miró Kinsta.
	 */
	public static function cerrar_foto( array $vistos ): void {
		global $wpdb;

		$tabla_sitios = SWPK_Tablas::tabla( 'sitios' );

		$wpdb->query( "UPDATE {$tabla_sitios} SET inventario_en = NULL" );

		foreach ( $vistos as $sitio_id => $cuando ) {
			$wpdb->update( $tabla_sitios, [ 'inventario_en' => $cuando ], [ 'sitio_id' => (string) $sitio_id ] );
		}

		// Detectar qué sitios ya tienen un plugin de 2FA activo sale gratis de
		// este mismo inventario: no cuesta ni una llamada extra.
		self::marcar_cobertura_2fa( current_time( 'Y-m-d' ) );
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
