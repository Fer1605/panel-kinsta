<?php
/**
 * El inventario consolidado: la tabla de sitios y sus consultas.
 *
 * "Individual, mucha información, pero no en paquete" (00:00). Esto es el
 * paquete.
 */

defined( 'ABSPATH' ) || exit;

class SWPK_Inventario {

	/** Lee los sitios de la fuente y los guarda. Devuelve cuántos. */
	public static function sincronizar(): int|WP_Error {
		global $wpdb;

		$sitios = swpk_fuente()->sitios();
		if ( is_wp_error( $sitios ) ) {
			return $sitios;
		}

		$tabla  = SWPK_Tablas::tabla( 'sitios' );
		$ahora  = current_time( 'mysql' );
		$n      = 0;
		$vistos = [];

		foreach ( $sitios as $sitio ) {
			if ( '' === $sitio['sitio_id'] ) {
				continue;
			}

			$datos = [
				'sitio_id'       => $sitio['sitio_id'],
				'entorno_id'     => $sitio['entorno_id'],
				'nombre'         => $sitio['nombre'],
				'nombre_visible' => $sitio['nombre_visible'],
				'estado'         => $sitio['estado'],
				'dominio'        => $sitio['dominio'],
				'etiquetas'      => wp_json_encode( $sitio['etiquetas'] ),
				'tag'            => SWPK_Etiquetas::clasificar( $sitio['etiquetas'] ),
				'version_wp'     => $sitio['version_wp'],
				'version_php'    => $sitio['version_php'],
				'visto_en'       => $ahora,
				// Volvió a aparecer: se le borra la cuenta de ausencias y, si
				// estaba dado de baja, se le quita la baja. Un sitio que
				// reaparece es un sitio vivo, y los hay: un dominio que se
				// movió, una cuenta que se pasó de empresa.
				'ausencias'      => 0,
				'de_baja'        => 0,
				'de_baja_en'     => null,
			];

			$existe = $wpdb->get_row(
				$wpdb->prepare( "SELECT id, de_baja, grupo_a_mano FROM {$tabla} WHERE sitio_id = %s", $sitio['sitio_id'] )
			);

			if ( $existe ) {
				// La corrección a mano del grupo NO se pisa con lo que diga la
				// etiqueta del hosting. Ver grupo_de() y el comentario de
				// SWPK_Etiquetas.
				if ( '' !== (string) $existe->grupo_a_mano ) {
					unset( $datos['tag'] );
				}

				if ( (int) $existe->de_baja === 1 ) {
					SWPK_Eventos::registrar(
						SWPK_Eventos::SITIO_REPUESTO,
						$sitio['sitio_id'],
						sprintf( '%s volvió a aparecer en el hosting; se le quitó la baja.', $sitio['nombre_visible'] ),
						'ok'
					);
				}

				$wpdb->update( $tabla, $datos, [ 'id' => (int) $existe->id ] );
			} else {
				$wpdb->insert( $tabla, $datos );
			}
			++$n;
			$vistos[] = $sitio['sitio_id'];
		}

		self::dar_de_baja_a_los_que_ya_no_estan( $vistos, $ahora );

		update_option( 'swpk_ultima_sincronizacion', $ahora, false );

		return $n;
	}

	/**
	 * Cuántas sincronizaciones seguidas puede faltar un sitio antes de darlo
	 * de baja.
	 *
	 * El número lo puso el jefe: «si después de cinco intentos de estar
	 * verificando que el dominio no existe, no responde, decir: hemos dado de
	 * baja, se queda en el historial» (02:19). La sincronización corre una vez
	 * al día, así que son cinco días — tiempo de sobra para que un fallo de la
	 * API o una cuenta que se movió no borre un sitio vivo.
	 */
	public const AUSENCIAS_PARA_BAJA = 5;

	/**
	 * Los sitios que el hosting ya no devuelve.
	 *
	 * ─── Por qué esto hacía falta ───────────────────────────────────────────
	 *
	 * El panel nunca quitaba nada. Si un sitio desaparecía de Kinsta, su fila
	 * se quedaba ahí para siempre con su última foto, sus vulnerabilidades y
	 * su nivel de riesgo, como si siguiera existiendo.
	 *
	 * El 23 de noviembre de 2026 eso se vio en vivo: la API devolvía 108
	 * sitios y la tabla tenía 109. El de más llevaba días borrado del
	 * hosting. El jefe le dio clic y el panel contestó «no existe»:
	 *
	 *     «Ya, porque está jalando sitios que no existen. Y eso va a pasar
	 *      porque estamos quitando, agregando.» (01:55)
	 *
	 * No se borra la fila, y eso es a propósito: la evidencia de lo que se
	 * midió y de lo que se notificó tiene que sobrevivir al sitio. Se marca
	 * `de_baja`, se deja de contar en los totales y en las alarmas, y queda
	 * en el historial — que es exactamente lo que pidió.
	 *
	 * @param array<int,string> $vistos Los sitio_id que la fuente SÍ devolvió.
	 */
	private static function dar_de_baja_a_los_que_ya_no_estan( array $vistos, string $ahora ): void {
		global $wpdb;

		// Sin ninguno visto no se da de baja a nadie: eso no es que la cartera
		// se haya vaciado, es que la llamada falló y devolvió una lista vacía.
		if ( ! $vistos ) {
			return;
		}

		$tabla = SWPK_Tablas::tabla( 'sitios' );
		$en    = implode( ',', array_fill( 0, count( $vistos ), '%s' ) );

		$ausentes = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, sitio_id, nombre_visible, ausencias, de_baja FROM {$tabla} WHERE sitio_id NOT IN ({$en})",
				...$vistos
			)
		) ?: [];

		foreach ( $ausentes as $a ) {
			if ( (int) $a->de_baja === 1 ) {
				continue; // ya estaba de baja: no se vuelve a avisar
			}

			$cuantas = (int) $a->ausencias + 1;

			if ( $cuantas < self::AUSENCIAS_PARA_BAJA ) {
				$wpdb->update( $tabla, [ 'ausencias' => $cuantas ], [ 'id' => (int) $a->id ] );
				continue;
			}

			$wpdb->update(
				$tabla,
				[ 'ausencias' => $cuantas, 'de_baja' => 1, 'de_baja_en' => $ahora ],
				[ 'id' => (int) $a->id ]
			);

			SWPK_Eventos::registrar(
				SWPK_Eventos::SITIO_DE_BAJA,
				(string) $a->sitio_id,
				sprintf(
					'%s se dio de baja: el hosting no lo devuelve desde hace %d sincronizaciones. Sus datos se quedan en el historial.',
					(string) $a->nombre_visible,
					$cuantas
				),
				'atencion'
			);

			// Y se avisa, que es la otra mitad del pedido: «y se manda un
			// correo que se dio de baja ese sitio porque ya no existe» (02:25).
			SWPK_Correos::avisar_de_baja( (string) $a->sitio_id, (string) $a->nombre_visible, $cuantas );
		}
	}

	/**
	 * Cuántos módulos de cada sitio tienen actualización automática, según el
	 * hosting.
	 *
	 * ─── Esto es lo que costó la confianza en el panel ──────────────────────
	 *
	 * La etiqueta del hosting y la actualización automática son dos cosas
	 * distintas, y el panel las estaba tratando como una. Hay sitios con la
	 * etiqueta «Desarrollo», así que el panel los ponía en el grupo
	 * Desarrollo, cuyo globito decía «lo administramos y lo actualizamos
	 * nosotros» — o sea, a mano. El jefe fue al hosting a comprobarlo y
	 * encontró todos sus módulos en auto-update:
	 *
	 *     «No es correcto lo que dice aquí. Entonces yo no puedo confiar en
	 *      este panel.» (20:07)
	 *
	 * Y tenía razón dos veces, porque además dijo lo de fondo: «puede estar en
	 * desarrollo y con actualización automática. Pero yo no sé eso, en ningún
	 * lado lo dice y tampoco puedo saberlo» (09:23).
	 *
	 * El dato estaba guardado desde el primer día —`auto_update` en cada foto,
	 * que Kinsta llena con «WordPress Auto Update»— y no se enseñaba en
	 * ninguna pantalla. Así que no hay que ir a buscar nada nuevo: hay que
	 * dejar de esconder lo que ya se tenía.
	 *
	 * La etiqueta queda como lo que es, una etiqueta administrativa. Esto es
	 * lo medido.
	 *
	 * @param array<int,string> $sitio_ids
	 * @return array<string,array{con:int,sin:int,total:int}>
	 */
	public static function auto_update_de_varios( array $sitio_ids = [] ): array {
		global $wpdb;

		$fotos = SWPK_Tablas::tabla( 'fotos' );

		$donde = '';
		$args  = [];

		if ( $sitio_ids ) {
			$en    = implode( ',', array_fill( 0, count( $sitio_ids ), '%s' ) );
			// Con el prefijo `f.`: sin él, `sitio_id` es ambiguo porque la
			// subconsulta de la derecha también tiene una columna así, y MySQL
			// rechaza la consulta entera.
			$donde = "AND f.sitio_id IN ({$en})";
			$args  = $sitio_ids;
		}

		// Solo la foto más reciente de cada sitio, y solo lo que está activo:
		// un plugin desactivado no se actualiza ni hace falta que lo haga.
		$sql = "SELECT f.sitio_id,
					SUM( CASE WHEN f.auto_update <> '' THEN 1 ELSE 0 END ) AS con_auto,
					COUNT(*) AS total
				FROM {$fotos} f
				INNER JOIN (
					SELECT sitio_id, MAX(fecha) AS ultima
					FROM {$fotos}
					GROUP BY sitio_id
				) u ON u.sitio_id = f.sitio_id AND u.ultima = f.fecha
				WHERE f.activo = 1 {$donde}
				GROUP BY f.sitio_id";

		$filas = $args
			? $wpdb->get_results( $wpdb->prepare( $sql, ...$args ) )
			: $wpdb->get_results( $sql );

		$salida = [];

		foreach ( (array) $filas as $f ) {
			$total = (int) $f->total;
			$con   = (int) $f->con_auto;

			$salida[ (string) $f->sitio_id ] = [
				'con'   => $con,
				'sin'   => $total - $con,
				'total' => $total,
			];
		}

		return $salida;
	}

	/**
	 * Lo mismo, para un solo sitio.
	 *
	 * @return array{con:int,sin:int,total:int}
	 */
	public static function auto_update( string $sitio_id ): array {
		return self::auto_update_de_varios( [ $sitio_id ] )[ $sitio_id ]
			?? [ 'con' => 0, 'sin' => 0, 'total' => 0 ];
	}

	/**
	 * El grupo de un sitio: la corrección a mano si la hay, y si no la
	 * etiqueta del hosting.
	 *
	 * Hace falta porque la etiqueta del hosting puede estar simplemente mal.
	 * Un sitio puede tener dos entornos —un `live` con el dominio de verdad y
	 * un `staging`— y la etiqueta «Desarrollo» está puesta en el SITIO, que en
	 * el hosting cubre los dos. El panel mide el entorno vivo, así que acababa
	 * enseñando el dominio de producción con el cartel de desarrollo:
	 *
	 *     «Y aquí dice que es [el dominio real], pero están en desarrollo.
	 *      Eso es imposible.» (03:35)
	 *
	 * No es imposible: es que la etiqueta del hosting describe el sitio entero
	 * y el panel mira una parte. Arreglarlo en Kinsta rompería lo que la
	 * etiqueta significa allá, así que la corrección vive aquí.
	 */
	public static function grupo_de( object $sitio ): string {
		$a_mano = (string) ( $sitio->grupo_a_mano ?? '' );

		return '' !== $a_mano && isset( SWPK_Etiquetas::nombres()[ $a_mano ] )
			? $a_mano
			: (string) ( $sitio->tag ?? SWPK_Etiquetas::SIN );
	}

	/**
	 * Los sitios vivos. Los dados de baja NO salen aquí.
	 *
	 * Esta función alimenta los totales, las alarmas y los correos, y un sitio
	 * que ya no existe no puede seguir contando como «un sitio con 10
	 * vulnerabilidades». Para verlos —que es lo que pidió, que queden en el
	 * historial— está `todos_con_los_de_baja()`.
	 *
	 * @return array<int,object>
	 */
	public static function todos(): array {
		global $wpdb;
		$tabla = SWPK_Tablas::tabla( 'sitios' );
		return $wpdb->get_results( "SELECT * FROM {$tabla} WHERE de_baja = 0 ORDER BY tag ASC, nombre_visible ASC" ) ?: [];
	}

	/** @return array<int,object> */
	public static function todos_con_los_de_baja(): array {
		global $wpdb;
		$tabla = SWPK_Tablas::tabla( 'sitios' );
		return $wpdb->get_results( "SELECT * FROM {$tabla} ORDER BY de_baja ASC, tag ASC, nombre_visible ASC" ) ?: [];
	}

	/** @return array<int,object> */
	public static function de_baja(): array {
		global $wpdb;
		$tabla = SWPK_Tablas::tabla( 'sitios' );
		return $wpdb->get_results( "SELECT * FROM {$tabla} WHERE de_baja = 1 ORDER BY de_baja_en DESC" ) ?: [];
	}

	/** Corrige a mano el grupo de un sitio. Cadena vacía = volver a la etiqueta del hosting. */
	public static function guardar_grupo_a_mano( string $sitio_id, string $grupo ): bool {
		global $wpdb;

		if ( '' !== $grupo && ! isset( SWPK_Etiquetas::nombres()[ $grupo ] ) ) {
			return false;
		}

		$datos = [ 'grupo_a_mano' => $grupo ];

		// Y se aplica ya al grupo efectivo, para que la pantalla no tenga que
		// esperar a la sincronización de mañana para reflejar la corrección.
		if ( '' !== $grupo ) {
			$datos['tag'] = $grupo;
		}

		return false !== $wpdb->update(
			SWPK_Tablas::tabla( 'sitios' ),
			$datos,
			[ 'sitio_id' => $sitio_id ]
		);
	}

	public static function obtener( string $sitio_id ): ?object {
		global $wpdb;
		$tabla = SWPK_Tablas::tabla( 'sitios' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tabla} WHERE sitio_id = %s", $sitio_id ) );
	}

	/** @return array<string,int> */
	public static function conteo_por_tag(): array {
		global $wpdb;
		$tabla = SWPK_Tablas::tabla( 'sitios' );
		$filas = $wpdb->get_results( "SELECT tag, COUNT(*) AS n FROM {$tabla} GROUP BY tag" ) ?: [];

		$conteo = array_fill_keys( array_keys( SWPK_Etiquetas::nombres() ), 0 );
		foreach ( $filas as $fila ) {
			$conteo[ $fila->tag ] = (int) $fila->n;
		}

		return $conteo;
	}

	/**
	 * A partir de cuántos días el inventario de un sitio deja de ser confiable.
	 *
	 * El proveedor no escanea todos los sitios a la vez: cada uno lleva su
	 * propio ritmo. Un dato de hace tres días está bien; uno de hace tres
	 * semanas puede estar escondiendo una actualización pendiente que salió
	 * después, y eso es casi tan engañoso como no tener dato.
	 */
	public const DIAS_PARA_DUDAR = 14;

	/** Días desde que el proveedor miró este sitio. null = nunca lo miró. */
	public static function dias_sin_escanear( object $sitio ): ?int {
		if ( empty( $sitio->inventario_en ) ) {
			return null;
		}

		$cuando = strtotime( (string) $sitio->inventario_en );
		if ( ! $cuando ) {
			return null;
		}

		// Aquí SÍ va time() y no SWPK_Frescura::ahora_ts(), al revés que en el
		// resto del panel: `inventario_en` no lo escribe current_time(), lo
		// escribe Kinsta, y Kinsta lo da en UTC. Comparar un instante en UTC
		// contra el reloj local lo correría seis horas.
		//
		// Si algún día esta columna pasa a guardarse en hora local, esta línea
		// hay que cambiarla con ella.
		return (int) floor( ( time() - $cuando ) / DAY_IN_SECONDS );
	}

	public static function dato_viejo( object $sitio ): bool {
		$dias = self::dias_sin_escanear( $sitio );
		return null !== $dias && $dias >= self::DIAS_PARA_DUDAR;
	}

	public static function ultima_sincronizacion(): string {
		return (string) get_option( 'swpk_ultima_sincronizacion', '' );
	}
}
