<?php
/**
 * El motor de riesgo: convierte datos en una frase que se puede copiar.
 *
 * Cruza lo que hay instalado (inventario del día) con el feed de Wordfence y decide
 * qué tan urgente es cada cosa. La severidad NO se inventa: se usa el rating
 * que publica Wordfence (None, Low, Medium, High, Critical), porque inventar
 * cortes propios es la forma más rápida de perder credibilidad cuando el
 * cliente pregunta de dónde salió el número.
 */

defined( 'ABSPATH' ) || exit;

class SWPK_Riesgo {

	public const CRITICO = 'critico';
	public const ALTO    = 'alto';
	public const MEDIO   = 'medio';
	public const BAJO    = 'bajo';
	public const AL_DIA  = 'al_dia';
	public const SIN_DATOS = 'sin_datos';

	public static function nombres(): array {
		return [
			self::CRITICO => 'Crítico',
			self::ALTO    => 'Alto',
			self::MEDIO   => 'Medio',
			self::BAJO    => 'Bajo',
			self::AL_DIA  => 'Al día',
			self::SIN_DATOS => 'Sin datos',
		];
	}

	/**
	 * Los colores del riesgo, los de la maqueta que aprobó el jefe.
	 *
	 * Tres cambiaron al construir la dona del Resumen, y por un motivo que
	 * solo se ve cuando los colores quedan pegados en un mismo gráfico:
	 *
	 *   · CRÍTICO y ALTO eran #b32d2e y #d63638, dos rojos. En una insignia
	 *     suelta se distinguen; en la dona, uno al lado del otro, son el mismo
	 *     color y la cartera parecía tener 26 sitios críticos.
	 *     Ahora ALTO es ámbar oscuro, como en la maqueta.
	 *   · BAJO era #2271b1, el azul de WordPress, que es también el color de
	 *     marca del panel. Un nivel de riesgo pintado del color de la marca se
	 *     lee como un acento de diseño, no como un dato. Ahora es gris.
	 *
	 * Los otros dos se quedan como estaban y NO son los de la maqueta: ahí
	 * eran #00a32a y #c3c4d0, y estos colores se usan también de fondo con
	 * texto blanco encima —en las insignias y en los correos—, donde esos dos
	 * dejan el texto ilegible.
	 *
	 * Y el color nunca va solo: al lado siempre está la palabra. Es regla del
	 * jefe, porque esto se imprime y se manda por correo, y porque el color
	 * solo no le sirve a quien no distingue rojo de verde.
	 */
	/**
	 * Los colores de los seis niveles.
	 *
	 * ─── Por qué «al día» es AZUL y no verde ────────────────────────────────
	 *
	 * El jefe es daltónico, y el panel es para él. Lo dijo mirando la pantalla
	 * de Caídas, con el verde y el rojo uno al lado del otro:
	 *
	 *     «Tus colores, este y este, yo lo veo igual. [...] Pues yo lo veo
	 *      verde los dos.» (15:52)
	 *
	 * Y en Cumplimiento, con tres niveles distintos: «para mí es el mismo
	 * color [...] verde oscuro y verde claro». La petición fue literal:
	 * «ponerme amarillo, rojo, azul, colores totalmente contrarios».
	 *
	 * ─── Y por qué esta paleta no se eligió a ojo ───────────────────────────
	 *
	 * El primer intento cambió el verde por azul y se quedó tan contento. Al
	 * medirlo salió que había arreglado el par del que se quejaba —al día
	 * contra crítico pasó de 17 a 145 de separación— y había creado otro:
	 * `alto` (#bc5b00) y `medio` (#8a6a00) quedaban a 6,8. Naranja y mostaza
	 * son el mismo color para quien no distingue el rojo del verde. O sea,
	 * exactamente la queja de Cumplimiento, movida de sitio.
	 *
	 * Así que estos seis valores salen de una búsqueda con tres condiciones a
	 * la vez, y las tres hay que mantenerlas si alguien los cambia:
	 *
	 *  1. **Los seis se distinguen entre sí en los TRES daltonismos** —
	 *     deuteranopía, protanopía y tritanopía—, simulados con las matrices
	 *     de Machado. El par más parecido está a 103 de distancia; por debajo
	 *     de 45 se confunden.
	 *  2. **Los seis se distinguen en escala de grises**, así que valen
	 *     también para el daltonismo que no se simuló, para una pantalla mal
	 *     calibrada y para una impresión en blanco y negro.
	 *  3. **Cada uno pasa de 4,5:1 con SU color de texto**, que no siempre es
	 *     el blanco. Ahí estaba la trampa del intento anterior: dar por hecho
	 *     el texto blanco obliga a que todo sea oscuro, y si todo es oscuro no
	 *     queda sitio donde separar seis niveles. El amarillo que pidió el
	 *     jefe existe —`medio` es un ámbar claro de verdad— porque lleva
	 *     texto oscuro. Ver texto_sobre().
	 *
	 * Lo que de verdad hace accesible esto no es el color: es que al lado del
	 * color siempre va la palabra. Un color que hay que interpretar es un
	 * color que alguien va a interpretar mal.
	 *
	 * Y eso vale el doble para las insignias pálidas de las tablas, cuyos
	 * fondos son casi blancos y entre ellos no se separan ni pueden. Llevaron
	 * un rato una franja gruesa de color a la izquierda, como apoyo. Se quitó:
	 * en una píldora redondeada el borde grueso se recorta contra el radio y
	 * deja una media luna oscura pegada al costado. No hacía falta — la
	 * palabra ya estaba ahí — y se veía mal.
	 */
	public static function colores(): array {
		return [
			self::CRITICO   => '#7d0f12', // rojo muy oscuro · texto blanco 10,8:1
			self::ALTO      => '#bc5b00', // naranja oscuro   · texto blanco  4,5:1
			self::MEDIO     => '#e8b33d', // ámbar claro      · texto oscuro  8,3:1
			self::BAJO      => '#5c6670', // pizarra          · texto blanco  5,9:1
			self::AL_DIA    => '#2a96d8', // azul             · texto oscuro  4,9:1
			self::SIN_DATOS => '#d2d6da', // gris claro       · texto oscuro 10,9:1
		];
	}

	/**
	 * El color de texto que va encima de cada nivel.
	 *
	 * No es un detalle: es lo que permite que la paleta use claros y oscuros
	 * en vez de solo oscuros, y sin eso no caben seis niveles separados. Dar
	 * por hecho el blanco fue el error del primer intento.
	 */
	public static function texto_sobre( string $nivel ): string {
		return in_array( $nivel, [ self::MEDIO, self::AL_DIA, self::SIN_DATOS ], true )
			? '#1d2327'
			: '#ffffff';
	}

	private static function de_rating( string $rating ): string {
		return match ( strtolower( $rating ) ) {
			'critical' => self::CRITICO,
			'high'     => self::ALTO,
			'medium'   => self::MEDIO,
			'low'      => self::BAJO,
			default    => self::BAJO,
		};
	}

	/**
	 * El peso de un nivel, para ordenar.
	 *
	 * Es pública porque SWPK_Estado ordena la lista de lo que hay que atender
	 * por gravedad, y replicar esta tabla en otro archivo es la forma segura de
	 * que un día dejen de coincidir.
	 */
	public static function peso( string $nivel ): int {
		return match ( $nivel ) {
			self::CRITICO => 4,
			self::ALTO    => 3,
			self::MEDIO   => 2,
			self::BAJO    => 1,
			default       => 0,
		};
	}

	/**
	 * Hallazgos de un sitio: qué tiene instalado que esté vulnerable o atrasado,
	 * con el por qué y la versión que lo arregla.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	/**
	 * La última fecha con datos de CADA sitio, en una sola consulta.
	 *
	 * Sigue siendo el máximo por sitio y no el de la cartera: eso es lo que
	 * evita que un sitio del que hoy no vino nada se mire contra la fecha de
	 * hoy y salga «Al día» con sus módulos intactos. Lo único que cambia es
	 * que se pregunta una vez para los 108 en vez de una vez por sitio.
	 *
	 * @return array<string,string> sitio_id => fecha
	 */
	private static function ultimas_fotos(): array {
		static $mapa = null;

		if ( null !== $mapa ) {
			return $mapa;
		}

		global $wpdb;
		$tabla = SWPK_Tablas::tabla( 'fotos' );
		$mapa  = [];

		foreach ( (array) $wpdb->get_results( "SELECT sitio_id, MAX(fecha) AS fecha FROM {$tabla} GROUP BY sitio_id" ) as $f ) {
			$mapa[ (string) $f->sitio_id ] = (string) $f->fecha;
		}

		return $mapa;
	}

	public static function hallazgos( string $sitio_id, string $fecha = '' ): array {
		global $wpdb;

		$tabla = SWPK_Tablas::tabla( 'fotos' );

		// La última inventario DE ESTE SITIO, no la de la cartera.
		//
		// No es lo mismo: Kinsta no contesta de los 108 todos los días. Con el
		// MAX global, un sitio del que hoy no vino nada se mira contra la fecha
		// de hoy, encuentra cero módulos y sale "Al día" — con sus 35 módulos
		// intactos y una vulnerabilidad dentro. Es la mentira más cara que este
		// panel puede contar, y la contó: el 12 de noviembre de 2026, de 25
		// sitios a la vez.
		//
		// Con el MAX por sitio, ese sitio se mira contra su último dato bueno.
		// Viejo, sí — y para eso está la columna "Revisado" y la tira de
		// frescura, que dicen de cuándo es.
		$fecha = '' !== $fecha ? $fecha : ( self::ultimas_fotos()[ $sitio_id ] ?? '' );

		if ( '' === $fecha ) {
			return [];
		}

		$filas = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$tabla} WHERE sitio_id = %s AND fecha = %s", $sitio_id, $fecha )
		) ?: [];

		$hallazgos = [];

		foreach ( $filas as $fila ) {
			$vulns = self::vulnerabilidades_que_aplican( $fila->slug, $fila->version );

			if ( $vulns ) {
				$peor = self::BAJO;
				foreach ( $vulns as $v ) {
									$nivel = self::de_rating( (string) $v->cvss_rating );
					if ( self::peso( $nivel ) > self::peso( $peor ) ) {
						$peor = $nivel;
					}
				}

				$hallazgos[] = [
					'nivel'              => $peor,
					'motivo'             => 'vulnerabilidad',
					'slug'               => $fila->slug,
					'titulo'             => $fila->titulo,
					'version'            => $fila->version,
					'version_disponible' => $fila->version_disponible,
					'vulnerabilidades'   => $vulns,
					'auto_update'        => $fila->auto_update,
				];
				continue;
			}

			// Kinsta dice que la versión instalada es vulnerable pero el feed no
			// nos dio el detalle. Se reporta igual: el dato de Kinsta vale, solo
			// que sin el "por qué".
			if ( $fila->es_vulnerable ) {
				$hallazgos[] = [
					'nivel'              => self::ALTO,
					'motivo'             => 'vulnerable_sin_detalle',
					'slug'               => $fila->slug,
					'titulo'             => $fila->titulo,
					'version'            => $fila->version,
					'version_disponible' => $fila->version_disponible,
					'vulnerabilidades'   => [],
					'auto_update'        => $fila->auto_update,
				];
				continue;
			}

			if ( $fila->hay_actualizacion ) {
				$hallazgos[] = [
					'nivel'              => self::BAJO,
					'motivo'             => 'actualizacion_pendiente',
					'slug'               => $fila->slug,
					'titulo'             => $fila->titulo,
					'version'            => $fila->version,
					'version_disponible' => $fila->version_disponible,
					'vulnerabilidades'   => [],
					'auto_update'        => $fila->auto_update,
				];
			}
		}

		usort(
			$hallazgos,
			static fn( $a, $b ) => self::peso( $b['nivel'] ) <=> self::peso( $a['nivel'] )
		);

		return $hallazgos;
	}

	/** @return array<int,object> */
	public static function vulnerabilidades_que_aplican( string $slug, string $version ): array {
		$aplican = [];

		foreach ( SWPK_Wordfence::para_slug( $slug ) as $v ) {
			$rangos = json_decode( (string) $v->rangos, true );
			$rangos = is_array( $rangos ) ? $rangos : [];

			foreach ( $rangos as $rango ) {
				if ( is_array( $rango ) && SWPK_Versiones::en_rango( $version, $rango ) ) {
					$aplican[] = $v;
					break;
				}
			}
		}

		return $aplican;
	}

	/**
	 * El nivel de riesgo de un sitio: el peor de sus hallazgos.
	 *
	 * Con una excepción que importa: si el proveedor nunca escaneó ese sitio, no
	 * hay hallazgos — pero eso NO es "al día". Decir que un sitio está sano
	 * cuando no se sabe nada de él es el error que este panel existe para evitar.
	 */
	public static function nivel_del_sitio( string $sitio_id ): string {
		$sitio = SWPK_Inventario::obtener( $sitio_id );
		if ( $sitio && empty( $sitio->inventario_en ) ) {
			return self::SIN_DATOS;
		}

		$peor = self::AL_DIA;

		foreach ( self::hallazgos( $sitio_id ) as $h ) {
			if ( self::peso( $h['nivel'] ) > self::peso( $peor ) ) {
				$peor = $h['nivel'];
			}
		}

		return $peor;
	}

	/**
	 * ¿El plugin fue retirado del repositorio de WordPress?
	 *
	 * Un plugin retirado no va a recibir parches nunca más, así que una
	 * vulnerabilidad en él no se arregla actualizando: hay que cambiarlo. Es
	 * una conversación distinta con el cliente y vale distinguirla.
	 * Se cachea 12 horas para no castigar a api.wordpress.org.
	 */
	public static function esta_abandonado( string $slug ): bool {
		$cache = get_transient( 'swpk_cerrado_' . $slug );
		if ( false !== $cache ) {
			return '1' === $cache;
		}

		$r = wp_remote_get(
			add_query_arg(
				[ 'action' => 'plugin_information', 'request[slug]' => $slug ],
				'https://api.wordpress.org/plugins/info/1.2/'
			),
			[ 'timeout' => 10 ]
		);

		$cerrado = false;
		if ( ! is_wp_error( $r ) ) {
			$datos   = json_decode( (string) wp_remote_retrieve_body( $r ), true );
			$cerrado = is_array( $datos ) && 'closed' === ( $datos['error'] ?? '' );
		}

		set_transient( 'swpk_cerrado_' . $slug, $cerrado ? '1' : '0', 12 * HOUR_IN_SECONDS );

		return $cerrado;
	}
}
