<?php
/**
 * El estado del sistema: qué está bien y qué está mal, dicho de una vez.
 *
 * ─── Por qué existe esta clase ──────────────────────────────────────────────
 *
 * La pantalla de Resumen abría con ocho números —12, 82, 13, 26, 50, 94, 0, 3—
 * y el jefe los rechazó con todas sus letras:
 *
 *   "el panel debe ser a nivel sistema darme la info rapidamente y no pequeños
 *    resumenes de numeros, piensalo que entro rapido a ver y rapido tendria que
 *    captar que esta bien y que esta mal"   (WhatsApp, 11/11/2026)
 *
 * Y no era la primera vez. En la reunión ya lo había dicho:
 *
 *   "Individual, mucha información, pero no en paquete."   (audio corto, 00:00)
 *
 * Dos veces, con meses de diferencia, pidió lo mismo y no lo recogimos. Un
 * contador obliga a dos pasos: interpretarlo y después ir a buscar a qué sitio
 * se refiere. Lo que él quiere es leer el problema con el nombre del sitio
 * puesto, sin buscar nada.
 *
 * Así que esta clase no cuenta: ordena. Devuelve la lista de lo que hay que
 * atender, de lo más grave a lo menos, con el sitio nombrado y la falla
 * explicada en palabras.
 *
 * ─── Qué decide que algo sea urgente ────────────────────────────────────────
 *
 * El nivel de riesgo no alcanza por sí solo. Lo que mueve una falla al primer
 * lugar es que se pueda aprovechar **desde internet, sin tener cuenta en el
 * sitio**: eso es lo que buscan los ataques automáticos, los que recorren
 * internet probando fallas una por una sin elegir a quién. Una falla grave que
 * exige ser administrador para explotarla es otra conversación.
 *
 * ─── Decir también lo que está bien ─────────────────────────────────────────
 *
 * Si no hay nada que atender, la pantalla lo dice con una frase, no se queda
 * vacía. Una pantalla vacía se lee como "no cargó", no como "todo en orden".
 */

defined( 'ABSPATH' ) || exit;

class SWPK_Estado {

	/**
	 * El recorrido es caro: nivel_del_sitio() consulta el inventario y cruza el feed
	 * de Wordfence módulo por módulo, 107 veces. Cinco minutos de caché hacen
	 * que recargar la pantalla no vuelva a pagarlo, y la corrida diaria lo
	 * olvida al terminar para que nunca se vea algo viejo sin avisar.
	 */
	private const VIGENCIA = 5 * MINUTE_IN_SECONDS;

	/** La caché es por persona: una asesora ve su cartera, no la de todos. */
	private static function clave(): string {
		return 'swpk_estado_' . get_current_user_id();
	}

	public static function olvidar(): void {
		global $wpdb;

		// Se borran las de todo el mundo: una corrida cambia lo que ve cualquiera.
		$wpdb->query(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_swpk_estado_%'
			 OR option_name LIKE '_transient_timeout_swpk_estado_%'"
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function calcular( bool $forzar = false ): array {
		$sitios = SWPK_Perfiles::sitios_visibles();

		$guardado = $forzar ? false : get_transient( self::clave() );
		if ( is_array( $guardado ) ) {
			// Las caídas nunca salen de la caché: son lo más urgente que puede
			// decir esta pantalla, y enseñar un sitio como "arriba" cinco
			// minutos después de que se cayó es exactamente el tipo de mentira
			// tranquilizadora que este panel no debe contar.
			$guardado['caidos'] = self::caidos( $sitios );
			return $guardado;
		}

		$urgentes     = [];
		$resto        = [];
		$sin_datos    = [];
		$al_dia_filas = [];
		$al_dia       = 0;
		$sin_2fa   = 0;
		$nuestros  = 0;

		foreach ( $sitios as $s ) {
			$sitio_id = (string) $s->sitio_id;
			$nivel    = SWPK_Riesgo::nivel_del_sitio( $sitio_id );

			if ( SWPK_Etiquetas::es_nuestro( $s->tag ) ) {
				++$nuestros;
				if ( ! $s->tiene_2fa ) {
					++$sin_2fa;
				}
			}

			$basico = [
				'sitio_id'    => $sitio_id,
				'nombre'      => (string) $s->nombre_visible,
				'dominio'     => (string) $s->dominio,
				'tag'         => (string) $s->tag,
				'nivel'       => $nivel,
				'revisado_en' => (string) ( $s->inventario_en ?? '' ),
				'modulo'      => '',
				'version'     => '',
				'disponible'  => '',
				'explicacion' => '',
				'cuantos'     => 0,
				'publicas'    => 0,
			];

			if ( SWPK_Riesgo::SIN_DATOS === $nivel ) {
				$sin_datos[] = $basico + [ 'dias' => SWPK_Inventario::dias_sin_escanear( $s ) ];
				continue;
			}

			if ( SWPK_Riesgo::AL_DIA === $nivel ) {
				++$al_dia;
				$al_dia_filas[] = $basico;
				continue;
			}

			$fila = self::resumir_sitio( $s, $nivel );

			// Qué va en "no puede esperar". El nivel solo no alcanza: casi todo
			// lo que Kinsta marca como vulnerable sin publicar el detalle cae en
			// ALTO, y si eso entra a la lista de hoy, la lista de hoy se vuelve
			// de 26 sitios y deja de ser la lista de hoy.
			//
			// Lo que sí la define es poder aprovecharse desde internet sin tener
			// cuenta en el sitio: eso es lo que buscan los ataques automáticos,
			// los que recorren internet probando fallas sin elegir a quién. Eso,
			// y lo crítico, que es grave aunque pida credenciales.
			if ( $fila['publicas'] > 0 || SWPK_Riesgo::CRITICO === $nivel ) {
				$urgentes[] = $fila;
			} else {
				$resto[] = $fila;
			}
		}

		// Primero lo que se aprovecha sin cuenta, después por gravedad, y a
		// igualdad de todo, el que más módulos tiene comprometidos.
		$orden = static function ( array $a, array $b ): int {
			return [ $b['publicas'], SWPK_Riesgo::peso( $b['nivel'] ), $b['cuantos'] ]
				<=> [ $a['publicas'], SWPK_Riesgo::peso( $a['nivel'] ), $a['cuantos'] ];
		};

		usort( $urgentes, $orden );
		usort( $resto, $orden );

		$estado = [
			'urgentes'  => $urgentes,
			'resto'     => $resto,
			'sin_datos' => $sin_datos,
			'al_dia_filas' => $al_dia_filas,
			'al_dia'    => $al_dia,
			'sin_2fa'   => $sin_2fa,
			'nuestros'  => $nuestros,
			'total'     => count( $sitios ),
			'armado_en' => current_time( 'mysql' ),
		];

		set_transient( self::clave(), $estado, self::VIGENCIA );

		$estado['caidos'] = self::caidos( $sitios );

		return $estado;
	}

	/**
	 * Un sitio con problemas, reducido a lo que la pantalla necesita decir.
	 *
	 * Se arma aquí y no en la vista para que lo que se guarda en caché sea
	 * texto ya resuelto y no el árbol entero de vulnerabilidades, que pesa.
	 *
	 * @return array<string,mixed>
	 */
	private static function resumir_sitio( object $sitio, string $nivel ): array {
		$hallazgos = SWPK_Riesgo::hallazgos( (string) $sitio->sitio_id );
		$publicas  = 0;
		$abierto   = null;

		foreach ( $hallazgos as $h ) {
			$v = $h['vulnerabilidades'][0] ?? null;
			if ( ! $v ) {
				continue;
			}
			$quien = SWPK_Textos::quien_puede(
				(string) ( $v->cvss_vector ?? '' ),
				(string) ( $v->descripcion ?? '' )
			);
			if ( $quien['sin_cuenta'] ) {
				++$publicas;
				$abierto ??= $h;
			}
		}

		// Qué módulo encabeza la fila. Si hay uno que se aprovecha desde
		// internet sin cuenta, ese, aunque su nivel no sea el más alto: es el
		// que puso al sitio en esta lista, y enseñar otro deja la fila
		// contradiciéndose consigo misma — "2 se aprovechan sin cuenta" al lado
		// de un módulo del que ni siquiera hay detalle publicado.
		$peor = $abierto ?? ( $hallazgos[0] ?? null );
		$vuln = $peor['vulnerabilidades'][0] ?? null;

		if ( $vuln ) {
			$explicacion = SWPK_Textos::explicar( (string) $vuln->titulo, (string) $vuln->descripcion );
		} elseif ( $peor && 'vulnerable_sin_detalle' === $peor['motivo'] ) {
			$explicacion = sprintf(
				'%s marca esta versión como vulnerable, pero no está publicado el detalle.',
				SWPK_Admin::proveedor()
			);
		} else {
			$explicacion = 'Tiene una actualización pendiente.';
		}

		return [
			'sitio_id'    => (string) $sitio->sitio_id,
			'nombre'      => (string) $sitio->nombre_visible,
			'dominio'     => (string) $sitio->dominio,
			'tag'         => (string) $sitio->tag,
			'nivel'       => $nivel,
			'revisado_en' => (string) ( $sitio->inventario_en ?? '' ),
			'modulo'      => $peor ? SWPK_Textos::limpiar_titulo( (string) $peor['titulo'] ) : '',
			'version'     => $peor ? (string) $peor['version'] : '',
			'disponible'  => $peor ? (string) $peor['version_disponible'] : '',
			'explicacion' => $explicacion,
			'cuantos'     => count( $hallazgos ),
			'publicas'    => $publicas,
		];
	}

	/** Los sitios que ahora mismo no responden, de los que esta persona puede ver. */
	private static function caidos( array $sitios ): array {
		if ( ! SWPK_Perfiles::puede( SWPK_Perfiles::VER_TECNICO ) ) {
			return [];
		}

		$mios   = array_map( static fn( $s ) => (string) $s->sitio_id, $sitios );
		$caidos = [];

		foreach ( SWPK_Monitor::estados() as $e ) {
			if ( 'abajo' !== $e->estado || ! in_array( (string) $e->sitio_id, $mios, true ) ) {
				continue;
			}
			$sitio    = SWPK_Inventario::obtener( (string) $e->sitio_id );
			$caidos[] = [
				'sitio_id'    => (string) $e->sitio_id,
				'nombre'      => (string) ( $sitio->nombre_visible ?? $e->sitio_id ),
				'dominio'     => (string) ( $sitio->dominio ?? '' ),
				'tag'         => (string) ( $sitio->tag ?? '' ),
				'nivel'       => 'caido',
				'revisado_en' => (string) ( $sitio->inventario_en ?? '' ),
				'modulo'      => '',
				'version'     => '',
				'disponible'  => '',
				'explicacion' => (string) ( $e->motivo ?: 'No contesta.' ),
				'cuantos'     => 0,
				'publicas'    => 0,
				'desde'       => (string) $e->desde,
			];
		}

		return $caidos;
	}

	/**
	 * Los sitios en una sola lista, del peor al que no tiene nada.
	 *
	 * Sin secciones y sin cortes: la pantalla es una tabla y el orden hace el
	 * trabajo que antes hacían los títulos. Lo que hay que atender queda arriba
	 * por ser lo peor, no por estar en un apartado aparte.
	 *
	 * @param array<string,mixed> $estado
	 * @return array<int,array<string,mixed>>
	 */
	public static function tabla( array $estado ): array {
		return array_merge(
			$estado['caidos'],
			$estado['urgentes'],
			$estado['resto'],
			$estado['sin_datos'],
			$estado['al_dia_filas']
		);
	}

	/**
	 * La frase de arriba. Es lo primero y, muchos días, lo único que se lee.
	 *
	 * @param array<string,mixed> $estado
	 * @return array<string,string>
	 */
	public static function veredicto( array $estado ): array {
		$caidos   = count( $estado['caidos'] );
		$urgentes = count( $estado['urgentes'] );
		$resto    = count( $estado['resto'] );

		if ( $caidos ) {
			return [
				'tono'    => 'mal',
				'titulo'  => 1 === $caidos
					? 'Hay un sitio caído ahora mismo'
					: sprintf( 'Hay %d sitios caídos ahora mismo', $caidos ),
				'detalle' => 'Eso va antes que cualquier otra cosa de esta pantalla.',
			];
		}

		if ( $urgentes ) {
			$publicas = 0;
			foreach ( $estado['urgentes'] as $u ) {
				$publicas += $u['publicas'];
			}

			return [
				'tono'    => 'mal',
				'titulo'  => 1 === $urgentes
					? 'Un sitio necesita atención hoy'
					: sprintf( '%d sitios necesitan atención hoy', $urgentes ),
				'detalle' => $publicas
					? sprintf(
						'%s de esas fallas se pueden aprovechar desde internet sin tener cuenta en el sitio.',
						1 === $publicas ? 'Una' : $publicas
					)
					: 'Ninguna se puede aprovechar sin tener antes una cuenta en el sitio.',
			];
		}

		if ( $resto ) {
			return [
				'tono'    => 'ojo',
				'titulo'  => sprintf( '%d sitio(s) con cosas pendientes, ninguna urgente', $resto ),
				'detalle' => 'Nada que atender hoy mismo, pero no conviene dejarlo correr.',
			];
		}

		if ( $estado['sin_datos'] ) {
			return [
				'tono'    => 'ojo',
				'titulo'  => 'Todo lo que sabemos está al día',
				'detalle' => sprintf(
					'Pero de %d sitio(s) no sabemos qué tienen instalado, así que no se puede afirmar más.',
					count( $estado['sin_datos'] )
				),
			];
		}

		return [
			'tono'    => 'bien',
			'titulo'  => 'Todo en orden',
			'detalle' => sprintf(
				'Los %d sitios están al día y ninguno está caído.',
				(int) $estado['total']
			),
		];
	}
}
