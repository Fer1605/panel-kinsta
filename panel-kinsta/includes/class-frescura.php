<?php
/**
 * De cuándo es cada dato.
 *
 * ─── El problema que resuelve ───────────────────────────────────────────────
 *
 * El panel no vigila nada en vivo: toma fotos. Le pregunta a Kinsta una vez al
 * día, se baja la lista de vulnerabilidades una vez al día, y revisa si los
 * sitios responden cada cinco minutos. Lo que se ve en pantalla siempre es una
 * foto, nunca el momento.
 *
 * Eso está bien mientras la foto sea de hoy. El peligro es el día en que la
 * corrida deja de pasar —el cron se rompe, la llave vence, Kinsta cambia la
 * API— y el panel sigue mostrando la última foto buena, en verde, sin decir que
 * es de hace cinco días. Nadie se daría cuenta. Y un panel que da falsa
 * tranquilidad es peor que no tener panel, porque se le cree.
 *
 * Es el mismo error que ya corregimos un nivel más abajo: cuando Kinsta no ha
 * escaneado un sitio, el panel dice "no sabemos" en vez de pintarlo de verde.
 * Esto es lo mismo, pero para el panel entero.
 *
 * ─── La regla ───────────────────────────────────────────────────────────────
 *
 * Ningún dato se muestra sin su hora. Siempre dos horas, nunca una:
 *
 *   · cuándo se obtuvo el dato   (la foto)
 *   · cuándo lo estás viendo     (ahora)
 *
 * Con las dos juntas no hay nada que adivinar. Y cuando la distancia entre las
 * dos se pasa de lo razonable, el panel lo dice fuerte, arriba y en rojo.
 *
 * ─── Los cuatro relojes ─────────────────────────────────────────────────────
 *
 * No hay un solo "cuándo". Hay cuatro, y confundirlos es parte del problema:
 *
 *   1. Kinsta escaneó el sitio       → por sitio, lo pone Kinsta a su ritmo
 *   2. El panel le preguntó a Kinsta → la corrida diaria, 6:30 am
 *   3. Se bajó la lista de Wordfence → una vez al día, con freno de 20 horas
 *   4. Se revisó si el sitio responde → cada cinco minutos
 *
 * El 1 vive en la columna `inventario_en` de cada sitio y se enseña en su
 * ficha. Los otros tres son globales y los enseña esta clase.
 *
 * ─── Intentó y logró no son lo mismo ────────────────────────────────────────
 *
 * `swpk_ultima_corrida` guarda cuándo arrancó el último intento.
 * `swpk_ultima_sincronizacion` guarda cuándo entró el último dato bueno.
 *
 * Si el intento es más nuevo que el dato, la corrida arrancó y no logró traer
 * nada: hay un fallo silencioso. Esa comparación es la que delata el día malo.
 */

defined( 'ABSPATH' ) || exit;

class SWPK_Frescura {

	/**
	 * Cuánto puede pasar antes de que un dato deje de ser "de hoy".
	 *
	 * La corrida es diaria a las 6:30. A las 26 horas ya se saltó una entera,
	 * y eso no es un retraso: es que no corrió.
	 */
	public const CORRIDA_AL_DIA = 26 * HOUR_IN_SECONDS;

	/**
	 * El feed de Wordfence tiene un freno propio de 20 horas para no quemar la
	 * llave, así que se le da más cuerda: tres días sin bajarlo sí es un fallo.
	 */
	public const FEED_AL_DIA = 3 * DAY_IN_SECONDS;

	// ── Las horas, una por una ──────────────────────────────────────────────

	/** Cuándo entró el último dato bueno de Kinsta. */
	public static function corrida(): string {
		return SWPK_Inventario::ultima_sincronizacion();
	}

	/** Cuándo arrancó el último intento de corrida, haya servido o no. */
	public static function intento(): string {
		$u = SWPK_Sincronizador::ultima_corrida();
		return (string) ( $u['inicio'] ?? '' );
	}

	/** Cuándo se bajó la lista de vulnerabilidades. */
	public static function feed(): string {
		return SWPK_Wordfence::ultima_actualizacion();
	}

	/** Cuándo se revisó por última vez si los sitios responden. */
	public static function sondeo(): string {
		$u = SWPK_Monitor::ultimo_sondeo();
		return (string) ( $u['cuando'] ?? '' );
	}

	/** Ahora. El otro extremo de la comparación. */
	public static function ahora(): string {
		return current_time( 'mysql' );
	}

	// ── Los veredictos ──────────────────────────────────────────────────────

	/** Segundos desde una hora guardada. null = nunca pasó. */
	public static function edad( string $cuando ): ?int {
		if ( '' === $cuando ) {
			return null;
		}
		$ts = strtotime( $cuando );
		return $ts ? max( 0, time() - $ts ) : null;
	}

	public static function corrida_al_dia(): bool {
		$edad = self::edad( self::corrida() );
		return null !== $edad && $edad < self::CORRIDA_AL_DIA;
	}

	/**
	 * ¿La última corrida arrancó y no trajo nada?
	 *
	 * Se compara el intento con el dato. Se dan diez minutos de holgura porque
	 * la corrida tarda un par de minutos en completarse y no tiene sentido
	 * gritar mientras está trabajando.
	 */
	public static function corrida_fallo(): bool {
		$intento = self::edad( self::intento() );
		$dato    = self::edad( self::corrida() );

		if ( null === $intento ) {
			return false;
		}
		if ( null === $dato ) {
			return true;
		}

		return ( $dato - $intento ) > 10 * MINUTE_IN_SECONDS;
	}

	/** Lo que la última corrida dejó anotado como error, si dejó algo. */
	public static function errores(): array {
		$u = SWPK_Sincronizador::ultima_corrida();
		$e = $u['errores'] ?? [];
		return is_array( $e ) ? $e : [];
	}

	public static function feed_al_dia(): bool {
		$edad = self::edad( self::feed() );
		return null !== $edad && $edad < self::FEED_AL_DIA;
	}

	/** ¿Hay algo que amerite gritar arriba de la pantalla? */
	public static function hay_problema(): bool {
		return ! self::corrida_al_dia() || self::corrida_fallo() || ! self::feed_al_dia();
	}

	// ── Cómo se dice ────────────────────────────────────────────────────────

	/** "hace 4 horas", "hace 2 días", "nunca". */
	public static function hace( string $cuando ): string {
		$edad = self::edad( $cuando );
		if ( null === $edad ) {
			return 'nunca';
		}
		if ( $edad < 2 * MINUTE_IN_SECONDS ) {
			return 'hace un momento';
		}
		if ( $edad < HOUR_IN_SECONDS ) {
			return sprintf( 'hace %d minutos', (int) floor( $edad / MINUTE_IN_SECONDS ) );
		}
		if ( $edad < DAY_IN_SECONDS ) {
			$h = (int) floor( $edad / HOUR_IN_SECONDS );
			return 1 === $h ? 'hace una hora' : sprintf( 'hace %d horas', $h );
		}
		$d = (int) floor( $edad / DAY_IN_SECONDS );
		return 1 === $d ? 'hace un día' : sprintf( 'hace %d días', $d );
	}

	/**
	 * Los nombres de los meses y los días, en español.
	 *
	 * WordPress los traduce según el idioma del sitio. Este panel está escrito
	 * entero en español, así que un panel instalado sobre un WordPress en inglés
	 * saldría diciendo "11 de November" — que se ve descuidado justo en la
	 * pantalla cuyo trabajo es que se le crea. Se traduce aquí y deja de
	 * depender de cómo esté configurado el WordPress donde caiga.
	 */
	private const EN_ES = [
		'January' => 'enero',   'February' => 'febrero', 'March'     => 'marzo',
		'April'   => 'abril',   'May'      => 'mayo',    'June'      => 'junio',
		'July'    => 'julio',   'August'   => 'agosto',  'September' => 'septiembre',
		'October' => 'octubre', 'November' => 'noviembre', 'December' => 'diciembre',
		'Jan' => 'ene', 'Feb' => 'feb', 'Mar' => 'mar', 'Apr' => 'abr',
		'Jun' => 'jun', 'Jul' => 'jul', 'Aug' => 'ago', 'Sep' => 'sep',
		'Oct' => 'oct', 'Nov' => 'nov', 'Dec' => 'dic',
		'Monday' => 'lunes',   'Tuesday' => 'martes', 'Wednesday' => 'miércoles',
		'Thursday' => 'jueves', 'Friday' => 'viernes', 'Saturday' => 'sábado',
		'Sunday' => 'domingo',
		'Mon' => 'lun', 'Tue' => 'mar', 'Wed' => 'mié', 'Thu' => 'jue',
		'Fri' => 'vie', 'Sat' => 'sáb', 'Sun' => 'dom',
	];

	/**
	 * Una fecha con formato, siempre en español.
	 *
	 * Acepta tanto lo que sale de la base ("2026-11-11 06:32:00") como un
	 * timestamp. strtr elige siempre la coincidencia más larga, así que tener
	 * "Jan" y "January" en la misma tabla no se estorban.
	 */
	public static function fmt( string $formato, string|int $cuando ): string {
		$texto = is_int( $cuando ) ? wp_date( $formato, $cuando ) : mysql2date( $formato, $cuando );
		$texto = (string) $texto;

		return str_starts_with( get_locale(), 'es' ) ? $texto : strtr( $texto, self::EN_ES );
	}

	/** "11 de noviembre, 6:32 am" — o "nunca" si no hay hora. */
	public static function fecha( string $cuando ): string {
		return '' === $cuando ? 'nunca' : self::fmt( 'j \d\e F, g:i a', $cuando );
	}

	/** La fecha con su antigüedad al lado: "11 de noviembre, 6:32 am (hace 4 horas)". */
	public static function fecha_y_edad( string $cuando ): string {
		if ( '' === $cuando ) {
			return 'nunca';
		}
		return sprintf( '%s (%s)', self::fecha( $cuando ), self::hace( $cuando ) );
	}

	// ── Para pantalla ───────────────────────────────────────────────────────

	/**
	 * Los relojes que se enseñan, con su veredicto.
	 *
	 * Si se pasa un sitio, se le agrega adelante su propio reloj. Son dos cosas
	 * distintas y la diferencia entre las dos importa: Kinsta escanea cada sitio
	 * cuando le toca, y después el panel le pregunta el resultado. Un dato puede
	 * haber entrado al panel esta mañana y ser, de origen, de hace dos semanas.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function relojes( ?object $sitio = null ): array {
		$proveedor = SWPK_Admin::proveedor();

		$relojes = [];

		if ( $sitio ) {
			$dias = SWPK_Inventario::dias_sin_escanear( $sitio );

			$relojes[] = [
				'que'    => sprintf( '%s revisó qué hay instalado en este sitio', $proveedor ),
				'cuando' => (string) ( $sitio->inventario_en ?? '' ),
				'al_dia' => null !== $dias && ! SWPK_Inventario::dato_viejo( $sitio ),
				'si_no'  => null === $dias
					? sprintf( '%s todavía no ha escaneado este sitio: su lista de módulos llega vacía, y vacía no quiere decir limpia.', $proveedor )
					: sprintf( 'Pasaron más de %d días sin que lo revisen. Lo de abajo puede estar desactualizado.', SWPK_Inventario::DIAS_PARA_DUDAR ),
			];
		}

		$relojes[] = [
			'que'    => sprintf( 'El panel le preguntó a %s qué hay en cada sitio', $proveedor ),
			'cuando' => self::corrida(),
			'al_dia' => self::corrida_al_dia(),
			'si_no'  => 'La corrida diaria no ha pasado. Lo de abajo es la última foto buena.',
		];

		$resto = [
			[
				'que'    => 'Se bajó la lista de vulnerabilidades conocidas (Wordfence)',
				'cuando' => self::feed(),
				'al_dia' => self::feed_al_dia(),
				'si_no'  => 'Sin lista nueva no aparecen las vulnerabilidades publicadas estos días.',
			],
		];

		if ( SWPK_Perfiles::puede( SWPK_Perfiles::VER_TECNICO ) ) {
			$resto[] = [
				'que'    => 'El panel revisó si los sitios responden',
				'cuando' => self::sondeo(),
				'al_dia' => SWPK_Monitor::esta_al_dia(),
				'si_no'  => 'El sondeo de cada cinco minutos no está corriendo.',
			];
		}

		return array_merge( $relojes, $resto );
	}

	/**
	 * La tira de "de cuándo es esto", que va en todas las pantallas.
	 *
	 * Va arriba, debajo del título, no abajo escondida: el punto es que no haya
	 * que buscarla para saber si lo que se está leyendo es de hoy.
	 */
	public static function sello( ?object $sitio = null ): void {
		$relojes = self::relojes( $sitio );
		$mal     = (bool) array_filter( $relojes, static fn( $r ) => ! $r['al_dia'] );
		?>
		<div class="swpk-sello <?php echo $mal ? 'swpk-sello-mal' : ''; ?>">
			<div class="swpk-sello-t">De cuándo es lo que estás viendo</div>
			<ul>
				<?php foreach ( $relojes as $r ) : ?>
					<li class="<?php echo $r['al_dia'] ? '' : 'swpk-viejo'; ?>">
						<span class="swpk-sello-q"><?php echo esc_html( $r['que'] ); ?></span>
						<span class="swpk-sello-c"><?php echo esc_html( self::fecha_y_edad( (string) $r['cuando'] ) ); ?></span>
						<?php if ( ! $r['al_dia'] ) : ?>
							<span class="swpk-sello-p"><?php echo esc_html( $r['si_no'] ); ?></span>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
			<div class="swpk-sello-ahora">
				Esta pantalla se armó el <?php echo esc_html( self::fmt( 'j \d\e F \a \l\a\s g:i a', self::ahora() ) ); ?>.
				Si la dejas abierta, los datos no se refrescan solos: hay que recargarla.
			</div>
		</div>
		<?php
	}

	/**
	 * El grito, cuando el dato ya no es de hoy.
	 *
	 * Deliberadamente arriba de todo y en rojo. El día que esto pase, tiene que
	 * ser imposible seguir leyendo el panel sin haberlo visto.
	 */
	public static function aviso(): void {
		if ( ! self::hay_problema() ) {
			return;
		}

		$fallo   = self::corrida_fallo();
		$vieja   = ! self::corrida_al_dia();
		$errores = self::errores();
		?>
		<div class="notice notice-error">
			<?php if ( $vieja || $fallo ) : ?>
				<p><strong>Lo que estás viendo no es de hoy.</strong>
					<?php if ( '' === self::corrida() ) : ?>
						El panel nunca ha logrado traer datos de <?php echo esc_html( SWPK_Admin::proveedor() ); ?>.
					<?php else : ?>
						El último dato bueno entró el
						<?php echo esc_html( self::fecha_y_edad( self::corrida() ) ); ?>.
					<?php endif; ?>
					<?php if ( $fallo && '' !== self::intento() ) : ?>
						Después de eso hubo un intento, el <?php echo esc_html( self::fecha( self::intento() ) ); ?>,
						que arrancó y no trajo nada.
					<?php endif; ?>
				</p>

				<?php if ( $errores ) : ?>
					<p>Lo que falló:</p>
					<ul style="list-style:disc;margin-left:24px">
						<?php foreach ( array_slice( $errores, 0, 5 ) as $e ) : ?>
							<li><?php echo esc_html( (string) $e ); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>

				<p>
					Mientras esto no se arregle, los números de abajo siguen siendo los de la última
					foto buena. No están mal: están viejos. Una actualización que salió después no
					aparece, y un sitio que ya se arregló sigue saliendo pendiente.
				</p>
				<p>
					La causa más común es WP-Cron, que se dispara con las visitas al sitio y a este
					panel casi nadie entra. En producción hay que apagarlo y poner una tarea
					programada de verdad:<br>
					<code>define( 'DISABLE_WP_CRON', true );</code> en wp-config.php, y en Plesk, cada hora:<br>
					<code>/usr/bin/php /ruta/al/panel/wp-cron.php &gt;/dev/null 2&gt;&amp;1</code>
				</p>
				<?php if ( SWPK_Perfiles::puede( SWPK_Perfiles::ADMINISTRAR ) ) : ?>
					<p>Para traer los datos ahora mismo, a mano, está el botón <strong>Correr ahora</strong>.</p>
				<?php endif; ?>
			<?php endif; ?>

			<?php if ( ! self::feed_al_dia() && ! $vieja && ! $fallo ) : ?>
				<p><strong>La lista de vulnerabilidades está vieja.</strong>
					La última se bajó <?php echo esc_html( self::fecha_y_edad( self::feed() ) ); ?>.
					Los sitios y sus versiones sí están al día, pero una vulnerabilidad publicada
					estos días todavía no está en la lista, así que no aparece marcada.
				</p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * La misma información en una línea de texto, para lo que sale del panel:
	 * los CSV, el reporte imprimible y los correos.
	 *
	 * Fuera del panel es donde más importa. Un Excel o un PDF se guarda, se
	 * reenvía y se abre meses después, cuando ya nadie se acuerda de qué día se
	 * sacó. Si no lleva la fecha encima, no sirve como respaldo.
	 */
	public static function linea_de_texto(): string {
		return sprintf(
			'Datos de %s obtenidos el %s. Lista de vulnerabilidades del %s. Documento generado el %s.',
			SWPK_Admin::proveedor(),
			self::fecha( self::corrida() ),
			self::fecha( self::feed() ),
			self::fmt( 'j \d\e F \d\e Y \a \l\a\s g:i a', self::ahora() )
		);
	}
}
