<?php
/**
 * Qué hay que hacer con un módulo que tiene algo pendiente.
 *
 * ─── De dónde sale esto ─────────────────────────────────────────────────────
 *
 * 27-11-2026, Heraldos Costa Rica. La pantalla «Lo que tiene pendiente» decía
 * de Modern Events Calendar Lite: Crítico · Hay parche · quién puede
 * aprovecharlo · qué permite. Y no decía la única cosa por la que alguien abre
 * esa pantalla:
 *
 *   «dice que hay un parche, pero no tiene una actualización, ¿o sí? Entonces,
 *    ¿qué es lo que podemos hacer? Aquí no me dice qué podemos hacer […] y fue
 *    lo que habíamos platicado, que mi jefe quiere saber qué puede hacer.»
 *
 * Tenía razón hasta en el detalle: ese plugin NO tiene actualización, y el
 * panel igual sugería actualizar. El reporte para el cliente sí decía «tiene la
 * versión X, hay que pasarlo a Y» desde hacía semanas. La pantalla no.
 *
 * ─── Las ocho situaciones ───────────────────────────────────────────────────
 *
 * No hay una respuesta: hay ocho, y cada una lleva a un trabajo distinto. Lo
 * que estaba mal no era que faltara una columna, era que las ocho se enseñaban
 * como si fueran la misma.
 *
 *   1. Lo actualiza el hosting      → no es nuestro. Esperar, o abrir ticket.
 *   2. Cerrado por WordPress        → reemplazarlo. Nunca va a llegar arreglo.
 *   3. Hay versión y corrige        → actualizar. Se acabó hoy.
 *   4. Hay versión pero no alcanza  → actualizar NO basta. Decir hasta dónde.
 *   5. Hay parche y el sitio no lo ve → revisar licencia o instalarlo a mano.
 *   6. Sin parche todavía           → esperar, o desactivarlo mientras tanto.
 *   7. Está apagado                 → borrarlo. Apagado sigue siendo código.
 *   8. Solo actualización           → actualizar.
 *
 * El caso 2 era el de Heraldos y el panel lo contaba como el 3. El caso 1 sale
 * hoy en 100 de los 109 sitios y se contaba como el 8, con la frase «hay que
 * entrar al sitio a actualizarlo» debajo de un módulo al que nadie puede
 * entrar a actualizar.
 */

defined( 'ABSPATH' ) || exit;

class SWPK_QueHacer {

	/** Quién tiene que moverse. Decide el color y el tono de la frase. */
	public const NOSOTROS = 'nosotros';
	public const HOSTING  = 'hosting';
	public const CLIENTE  = 'cliente';
	public const ESPERAR  = 'esperar';

	/**
	 * El caso, con una clave estable.
	 *
	 * ─── Por qué hace falta una clave y no basta el titular ─────────────────
	 *
	 * El titular lleva la versión dentro —«Actualizar a 3.2.1»— así que no hay
	 * dos iguales y no se puede agrupar por él. Para que la pantalla de
	 * Vulnerabilidades pueda ofrecer «enseñame solo lo que se arregla
	 * actualizando», hace falta una clave que no cambie de módulo en módulo.
	 *
	 * Los casos 3 y 8 comparten clave a propósito: en los dos el trabajo es el
	 * mismo —entrar y actualizar—, y lo que los distingue —si hay una falla
	 * conocida o solo una versión vieja— ya se filtra por su lado.
	 */
	public const ACTUALIZAR = 'actualizar';
	public const NO_BASTA   = 'no_basta';
	public const A_MANO     = 'a_mano';
	public const CERRADO    = 'cerrado';
	public const APAGADO    = 'apagado';
	public const AGUANTAR   = 'aguantar';
	public const DEL_HOST   = 'del_hosting';

	/**
	 * Los rótulos de los casos, en el orden en que se trabajan: primero lo que
	 * se puede cerrar hoy, al final lo que no depende de nosotros.
	 *
	 * @return array<string,string>
	 */
	public static function casos(): array {
		return [
			self::ACTUALIZAR => 'Actualizarlo',
			self::NO_BASTA   => 'Actualizar no basta',
			self::A_MANO     => 'Instalarlo a mano',
			self::CERRADO    => 'Reemplazarlo: cerrado por WordPress',
			self::APAGADO    => 'Borrarlo: está apagado',
			self::AGUANTAR   => 'Esperar el arreglo',
			self::DEL_HOST   => 'Lo actualiza el hosting',
		];
	}

	/**
	 * Módulos que pone y actualiza el hosting, no nosotros.
	 *
	 * Kinsta instala sus must-use plugins en cada sitio y los va soltando por
	 * tandas. No aparecen en la lista de plugins del escritorio, no tienen
	 * actualización automática de WordPress y nadie puede actualizarlos desde
	 * wp-admin. El panel los enseñaba igual que a Elementor Pro, con la frase
	 * «hay que entrar al sitio a actualizarlo» debajo:
	 *
	 *   «esos deberían ser automáticos, ¿no? Porque esos no es que lo
	 *    instalemos nosotros, sino de parte de Kinsta […] ese lo actualiza
	 *    Kinsta, ¿no?»
	 *
	 * Sí. Y como se reparten por tandas, el panel puede contestar también la
	 * pregunta siguiente —por qué este sitio todavía no lo tiene— con un dato
	 * nuestro: en cuántos sitios de la cartera ya llegó.
	 */
	private const DEL_HOSTING = [
		'kinsta-mu-plugins' => 'Kinsta',
	];

	/**
	 * Lo que el hosting mete como must-use lleva su prefijo y puede salir con
	 * nombres que no conocemos de antemano. El prefijo cubre los que vengan.
	 */
	private const PREFIJOS_DEL_HOSTING = [ 'kinsta-' ];

	public static function es_del_hosting( string $slug ): bool {
		if ( isset( self::DEL_HOSTING[ $slug ] ) ) {
			return true;
		}

		foreach ( self::PREFIJOS_DEL_HOSTING as $prefijo ) {
			if ( str_starts_with( $slug, $prefijo ) ) {
				return true;
			}
		}

		return false;
	}

	/** Quién lo pone, para nombrarlo en la frase. */
	public static function quien_del_hosting( string $slug ): string {
		return self::DEL_HOSTING[ $slug ] ?? 'Kinsta';
	}

	/**
	 * Si se puede apagar desde el escritorio del sitio.
	 *
	 * ─── Por qué esto existe ────────────────────────────────────────────────
	 *
	 * 27-11-2026, Heraldos Costa Rica: «Kinsta Must-use Plugins» salía en la
	 * lista «instalados pero apagados». Fer:
	 *
	 *   «ese es de Kinsta, no debería de estar entre los apagados, porque ese
	 *    ni siquiera aparece dentro de los sitios»
	 *
	 * Tiene razón, y el dato de Kinsta nos engaña: un must-use plugin se
	 * carga siempre, antes que cualquier otro, y WordPress no lo apunta en la
	 * lista de plugins activos porque no hace falta —no se puede desactivar—.
	 * La API lo devuelve con status «inactive» por eso, no porque esté
	 * apagado. «Apagado» quiere decir una cosa concreta: que alguien lo
	 * desactivó en el escritorio del sitio. Esto no se puede desactivar ahí,
	 * así que nunca está apagado.
	 */
	public static function se_puede_apagar( string $slug ): bool {
		return ! self::es_del_hosting( $slug );
	}

	/**
	 * En cuántos sitios de la cartera ya llegó esta versión del módulo del
	 * hosting, y en cuántos falta. Se guarda una hora: es un dato de la última
	 * foto, no cambia dentro de la misma mañana.
	 *
	 * @return array{con:int,total:int,ultima:string}
	 */
	public static function reparto_del_hosting( string $slug ): array {
		global $wpdb;

		$cache = get_transient( 'swpk_reparto_hosting_' . $slug );
		if ( is_array( $cache ) ) {
			return $cache;
		}

		$tabla = SWPK_Tablas::tabla( 'fotos' );
		$fecha = (string) $wpdb->get_var( $wpdb->prepare( "SELECT MAX(fecha) FROM {$tabla} WHERE slug = %s", $slug ) );

		$vacio = [ 'con' => 0, 'total' => 0, 'ultima' => '' ];

		if ( '' === $fecha ) {
			return $vacio;
		}

		$filas = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT version, version_disponible, hay_actualizacion FROM {$tabla} WHERE slug = %s AND fecha = %s",
				$slug,
				$fecha
			)
		) ?: [];

		if ( ! $filas ) {
			return $vacio;
		}

		// La última versión que se ha visto en la cartera: la mayor entre las
		// instaladas y las ofrecidas. Así el número dice «ya llegó a N» sin
		// que haya que saber de antemano cuál es la nueva.
		$ultima = '';
		foreach ( $filas as $f ) {
			foreach ( [ (string) $f->version, (string) $f->version_disponible ] as $v ) {
				if ( '' !== $v && ( '' === $ultima || version_compare( $v, $ultima, '>' ) ) ) {
					$ultima = $v;
				}
			}
		}

		$con = 0;
		foreach ( $filas as $f ) {
			if ( '' !== $ultima && '' !== (string) $f->version && version_compare( (string) $f->version, $ultima, '>=' ) ) {
				++$con;
			}
		}

		$r = [ 'con' => $con, 'total' => count( $filas ), 'ultima' => $ultima ];

		set_transient( 'swpk_reparto_hosting_' . $slug, $r, HOUR_IN_SECONDS );

		return $r;
	}

	/**
	 * La respuesta, para un hallazgo de SWPK_Riesgo::hallazgos().
	 *
	 * @param array<string,mixed> $h
	 * @return array{titular:string,detalle:string,quien:string,caso:string,urgente:bool}
	 */
	public static function para( array $h ): array {
		$slug        = (string) ( $h['slug'] ?? '' );
		$tipo        = (string) ( $h['tipo'] ?? 'plugin' );
		$version     = (string) ( $h['version'] ?? '' );
		$disponible  = (string) ( $h['version_disponible'] ?? '' );
		$motivo      = (string) ( $h['motivo'] ?? '' );
		$activo      = ! isset( $h['activo'] ) || (int) $h['activo'] > 0;
		$vulns       = is_array( $h['vulnerabilidades'] ?? null ) ? $h['vulnerabilidades'] : [];
		$hay_falla   = 'actualizacion_pendiente' !== $motivo;

		// ── 1. Lo pone y lo actualiza el hosting ───────────────────────────
		if ( self::es_del_hosting( $slug ) ) {
			$quien_hosting = self::quien_del_hosting( $slug );
			$r             = self::reparto_del_hosting( $slug );

			$detalle = sprintf(
				'No se puede actualizar desde el escritorio del sitio ni con una orden del panel: lo instala %s y lo va soltando por tandas.',
				$quien_hosting
			);

			if ( $r['total'] > 0 && '' !== $r['ultima'] ) {
				$detalle .= sprintf(
					' La %s ya llegó a %d de los %d sitios de la cartera.',
					$r['ultima'],
					$r['con'],
					$r['total']
				);

				// Cuando ya lleva semanas repartida y este sitio sigue sin
				// ella, deja de ser una tanda y pasa a ser algo que preguntar.
				if ( $r['total'] > 0 && $r['con'] >= (int) ceil( $r['total'] * 0.8 ) ) {
					$detalle .= ' Como ya está en casi todos, vale la pena preguntarle a ' . $quien_hosting . ' por este.';
				}
			}

			return [
				'titular' => 'Lo actualiza ' . $quien_hosting,
				'caso'    => self::DEL_HOST,
				'detalle' => $detalle,
				'quien'   => self::HOSTING,
				'urgente' => false,
			];
		}

		// ── 2. WordPress lo cerró: no va a haber arreglo ───────────────────
		if ( SWPK_Repositorio::CERRADO === SWPK_Repositorio::estado( $slug, $tipo ) ) {
			$detalle = SWPK_Repositorio::frase_cierre( $slug, $tipo );

			$corrige = self::version_objetivo( $vulns );
			if ( '' !== $corrige ) {
				$detalle .= sprintf(
					' La falla está corregida desde la %s, pero esa versión no va a llegar por WordPress: el sitio tiene la %s y ahí se queda.',
					$corrige,
					$version
				);
			} else {
				$detalle .= ' Lo que esté instalado es lo último que va a tener.';
			}

			if ( SWPK_Repositorio::cerrado_por_seguridad( $slug, $tipo ) ) {
				$detalle .= ' Lo cerraron por seguridad, así que no es cosa de esperar.';
			}

			return [
				'titular' => $activo ? 'Reemplazarlo por otro' : 'Borrarlo del sitio',
				'caso'    => self::CERRADO,
				'detalle' => $detalle,
				'quien'   => self::NOSOTROS,
				'urgente' => $activo && $hay_falla,
			];
		}

		// ── 7. Instalado pero apagado ──────────────────────────────────────
		//
		// Va antes que los casos de actualizar porque la respuesta correcta
		// para un módulo apagado no es actualizarlo: es sacarlo. El código de
		// un plugin desactivado sigue en el servidor y se puede alcanzar.
		if ( ! $activo ) {
			return [
				'titular' => 'Borrarlo: está apagado',
				'caso'    => self::APAGADO,
				'detalle' => $hay_falla
					? 'Está desactivado, pero sus archivos siguen en el servidor y una falla en ellos se puede aprovechar igual. Lo limpio es borrarlo; si se va a volver a usar, actualizarlo primero.'
					: 'Está desactivado. Si ya no se usa, lo limpio es borrarlo en vez de arrastrar una actualización pendiente cada mes.',
				'quien'   => self::NOSOTROS,
				'urgente' => false,
			];
		}

		// ── 8. Solo una actualización pendiente ────────────────────────────
		if ( ! $hay_falla ) {
			return [
				'titular' => '' !== $disponible ? 'Actualizar a ' . $disponible : 'Actualizar',
				'caso'    => self::ACTUALIZAR,
				'detalle' => self::como_se_actualiza( $h ),
				'quien'   => self::NOSOTROS,
				'urgente' => false,
			];
		}

		// A partir de aquí hay una falla conocida.
		$corrige = self::version_objetivo( $vulns );

		// ── 5. Hay parche publicado, pero el sitio no ve la actualización ──
		if ( '' === $disponible ) {
			if ( '' !== $corrige ) {
				return [
					'titular' => 'Llegar a la ' . $corrige,
				'caso'    => self::A_MANO,
					'detalle' => sprintf(
						'El arreglo existe desde la %s y el sitio tiene la %s, pero WordPress no le está ofreciendo nada. Suele ser una licencia vencida o un módulo que no se instaló desde el repositorio: hay que revisar la licencia, o bajarlo del sitio del autor e instalarlo a mano.',
						$corrige,
						$version
					),
					'quien'   => self::NOSOTROS,
					'urgente' => true,
				];
			}

			// ── 6. No hay arreglo todavía ──────────────────────────────────
			return [
				'titular' => 'Desactivarlo mientras tanto',
				'caso'    => self::AGUANTAR,
				'detalle' => 'El autor todavía no ha publicado el arreglo, así que actualizar no sirve de nada. Lo único que baja el riesgo hoy es apagarlo, o bloquear por delante la parte del sitio que usa.',
				'quien'   => self::ESPERAR,
				'urgente' => true,
			];
		}

		// ── 4. Hay actualización, pero no llega hasta donde hace falta ─────
		if ( '' !== $corrige && version_compare( $disponible, $corrige, '<' ) ) {
			return [
				'titular' => 'Actualizar no basta',
				'caso'    => self::NO_BASTA,
				'detalle' => sprintf(
					'Lo que ofrece WordPress es la %s, y la falla sigue ahí: se corrige en la %s o posterior. Hay que actualizar igual, y después revisar si el autor ya publicó esa versión.',
					$disponible,
					$corrige
				),
				'quien'   => self::NOSOTROS,
				'urgente' => true,
			];
		}

		// ── 3. Hay actualización y corrige la falla ────────────────────────
		return [
			'titular' => 'Actualizar a ' . $disponible,
				'caso'    => self::ACTUALIZAR,
			'detalle' => '' !== $corrige
				? sprintf( 'Esa versión corrige la falla, que está arreglada desde la %s. %s', $corrige, self::como_se_actualiza( $h ) )
				: 'Esa versión corrige la falla. ' . self::como_se_actualiza( $h ),
			'quien'   => self::NOSOTROS,
			'urgente' => true,
		];
	}

	/**
	 * La versión más baja que corrige la falla, según Wordfence.
	 *
	 * Se queda con la menor: es la que hay que alcanzar. Las otras son
	 * parches de ramas posteriores.
	 */
	public static function version_objetivo( array $vulns ): string {
		// La versión a la que hay que llegar para que no quede ninguna falla
		// abierta: la MAYOR de las versiones que corrige cada una. Mirar solo
		// la primera vulnerabilidad de la lista deja fuera las demás, y en un
		// módulo muy atrasado las demás son casi todas.
		$objetivo = '';

		foreach ( $vulns as $v ) {
			if ( ! is_object( $v ) ) {
				continue;
			}
			$p = self::primera_version_parcheada( $v );
			if ( '' !== $p && ( '' === $objetivo || version_compare( $p, $objetivo, '>' ) ) ) {
				$objetivo = $p;
			}
		}

		return $objetivo;
	}

	private static function primera_version_parcheada( object $v ): string {
		$lista = json_decode( (string) ( $v->versiones_parcheadas ?? '' ), true );
		$lista = is_array( $lista ) ? array_values( array_filter( array_map( 'strval', $lista ) ) ) : [];

		if ( ! $lista ) {
			return '';
		}

		usort( $lista, 'version_compare' );

		return $lista[0];
	}

	/** Quién puede apretar el botón: el sitio solo, o una persona entrando. */
	private static function como_se_actualiza( array $h ): string {
		return '' !== (string) ( $h['auto_update'] ?? '' )
			? 'Está puesto para actualizarse solo, así que debería caer en la próxima vuelta de WordPress. Si lleva días igual, hay que entrar a hacerlo.'
			: 'Este módulo no se actualiza solo: hay que entrar al sitio y actualizarlo.';
	}
}
