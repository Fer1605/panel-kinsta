<?php
/**
 * Piezas que repiten todas las pantallas: elegir categoría, filtrar y paginar.
 *
 * ─── Por qué existe ─────────────────────────────────────────────────────────
 *
 * Con 107 sitios y 1.700 filas de inventario, una sola página con todo encima
 * no se lee: se abre, se ve una pared de texto y se cierra. Un panel que no se
 * usa no sirve de nada, por bien que estén los datos.
 *
 * La regla es: primero se elige de qué grupo hablamos —los tres tags— y hasta
 * entonces aparece la información. Después se afina con filtros, y lo que queda
 * se parte en páginas.
 */

defined( 'ABSPATH' ) || exit;

class SWPK_Vista {

	public const POR_PAGINA = 20;

	/**
	 * Los tres grupos, como tarjetas grandes para elegir.
	 *
	 * En la pantalla de Sitios el número es la cantidad de sitios del grupo. En
	 * la de Vulnerabilidades no puede serlo: poner 82 ahí se lee como "82 sitios
	 * con vulnerabilidades", cuando en realidad son 31. Por eso la tarjeta
	 * acepta un "31 de 82" y una línea con el desglose de los que quedan fuera.
	 *
	 * @param array<string,array{n:int,de?:int,sub?:string}> $detalles Por tag.
	 */
	public static function selector_de_grupo( string $pagina, string $actual, array $conteos, array $extra = [], array $detalles = [] ): void {
		$base = add_query_arg( array_merge( [ 'page' => $pagina ], $extra ), admin_url( 'admin.php' ) );
		?>
		<div class="swpk-grupos">
			<?php foreach ( SWPK_Etiquetas::nombres() as $tag => $nombre ) : ?>
				<?php
				if ( empty( $conteos[ $tag ] ) && SWPK_Etiquetas::SIN === $tag ) {
					continue;
				}

				$es  = $actual === $tag;
				$det = $detalles[ $tag ] ?? null;
				?>
				<a class="swpk-grupo <?php echo $es ? 'swpk-grupo-activo' : ''; ?>"
					href="<?php echo esc_url( $es ? remove_query_arg( [ 'tag', 'nivel', 'p', 'q', 'wp', 'php' ], $base ) : add_query_arg( 'tag', $tag, $base ) ); ?>">

					<?php if ( $det ) : ?>
						<span class="swpk-grupo-n" style="color:<?php echo $det['n'] ? '#b32d2e' : '#008a20'; ?>">
							<?php echo (int) $det['n']; ?><span class="swpk-grupo-de">de <?php echo (int) ( $det['de'] ?? 0 ); ?></span>
						</span>
					<?php else : ?>
						<span class="swpk-grupo-n"><?php echo (int) ( $conteos[ $tag ] ?? 0 ); ?></span>
					<?php endif; ?>

					<span class="swpk-grupo-t"><?php echo esc_html( $nombre ); ?></span>

					<?php if ( $det && ! empty( $det['sub'] ) ) : ?>
						<span class="swpk-grupo-s"><?php echo esc_html( $det['sub'] ); ?></span>
					<?php elseif ( ! SWPK_Etiquetas::es_nuestro( $tag ) && SWPK_Etiquetas::SIN !== $tag ) : ?>
						<span class="swpk-grupo-s">los administra el cliente</span>
					<?php else : ?>
						<span class="swpk-grupo-s">los administramos nosotros</span>
					<?php endif; ?>
				</a>
			<?php endforeach; ?>
		</div>

		<?php if ( '' === $actual ) : ?>
			<p style="color:#50575e;margin-top:-4px">
				<?php
				echo $detalles
					? 'Elegí un grupo para ver qué tiene pendiente cada sitio.'
					: 'Elegí un grupo para ver sus sitios. Así no se mezclan los que administramos nosotros con los que administra el cliente, que se tratan distinto.';
				?>
			</p>
		<?php endif; ?>
		<?php
	}

	/** Abre la fila que lleva los filtros a la izquierda y el buscador a la derecha. */
	public static function abrir_barra(): void {
		echo '<div class="swpk-barra">';
	}

	public static function cerrar_barra(): void {
		echo '</div>';
	}

	/** Filtro por nivel de riesgo. */
	public static function filtro_de_riesgo( string $actual, array $conteos = [] ): void {
		$niveles = [ '' => 'Todos' ] + SWPK_Riesgo::nombres();
		?>
		<div class="swpk-filtros">
			<span class="swpk-filtros-t">Mostrar:</span>
			<?php foreach ( $niveles as $nivel => $nombre ) : ?>
				<?php
				if ( '' !== $nivel && isset( $conteos[ $nivel ] ) && 0 === $conteos[ $nivel ] ) {
					continue;
				}
				$url = '' === $nivel ? remove_query_arg( [ 'nivel', 'p' ] ) : add_query_arg( [ 'nivel' => $nivel, 'p' => 1 ] );
				?>
				<a class="swpk-filtro <?php echo $actual === $nivel ? 'swpk-filtro-activo' : ''; ?>"
					href="<?php echo esc_url( $url ); ?>">
					<?php echo esc_html( $nombre ); ?>
					<?php if ( '' !== $nivel && isset( $conteos[ $nivel ] ) ) : ?>
						<span class="swpk-filtro-n"><?php echo (int) $conteos[ $nivel ]; ?></span>
					<?php endif; ?>
				</a>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/** Buscador por nombre de sitio. */
	public static function buscador( string $pagina, string $actual, array $conservar = [] ): void {
		?>
		<form method="get" class="swpk-buscador">
			<input type="hidden" name="page" value="<?php echo esc_attr( $pagina ); ?>">
			<?php foreach ( $conservar as $k => $v ) : ?>
				<?php if ( '' !== $v ) : ?>
					<input type="hidden" name="<?php echo esc_attr( $k ); ?>" value="<?php echo esc_attr( $v ); ?>">
				<?php endif; ?>
			<?php endforeach; ?>
			<input type="search" name="q" value="<?php echo esc_attr( $actual ); ?>" placeholder="Buscar un sitio…">
			<button class="button">Buscar</button>
			<?php if ( '' !== $actual ) : ?>
				<a class="button-link" href="<?php echo esc_url( remove_query_arg( [ 'q', 'p' ] ) ); ?>">limpiar</a>
			<?php endif; ?>
		</form>
		<?php
	}

	/** @return array{0:array,1:int,2:int} lo que toca mostrar, página actual, total de páginas */
	/**
	 * "1 sitio" / "12 sitios", en vez de "1 sitio(s)".
	 *
	 * El paréntesis le ahorra el trabajo a quien escribe y se lo pasa a quien
	 * lee, y estas son las líneas que más se leen del panel: son las que
	 * contestan "¿pasó algo hoy?".
	 */
	/**
	 * Recorta un texto sin partir la última palabra.
	 *
	 * Los renglones de las tarjetas son de una línea y las explicaciones de
	 * las vulnerabilidades son de tres. Cortar por carácter dejaba cosas como
	 * «Permite dejar código escondido en el si…», que se lee peor que no
	 * decir nada; cortando por palabra queda «…en el…» y se entiende.
	 */
	public static function recortar( string $texto, int $tope ): string {
		$texto = trim( preg_replace( '/\s+/u', ' ', $texto ) ?? '' );

		if ( mb_strlen( $texto ) <= $tope ) {
			return $texto;
		}

		$corte = mb_substr( $texto, 0, $tope );
		$hueco = mb_strrpos( $corte, ' ' );

		if ( false !== $hueco && $hueco > $tope * 0.6 ) {
			$corte = mb_substr( $corte, 0, $hueco );
		}

		return rtrim( $corte, " .,;:·-" ) . '…';
	}

	public static function plural( int $n, string $uno, string $varios ): string {
		return 1 === $n ? '1 ' . $uno : number_format_i18n( $n ) . ' ' . $varios;
	}

	public static function paginar( array $todo, int $pagina_actual, int $por_pagina = self::POR_PAGINA ): array {
		$total   = count( $todo );
		$paginas = max( 1, (int) ceil( $total / $por_pagina ) );
		$pagina  = max( 1, min( $pagina_actual, $paginas ) );

		return [ array_slice( $todo, ( $pagina - 1 ) * $por_pagina, $por_pagina ), $pagina, $paginas ];
	}

	/**
	 * El pie con la cuenta y, si hace falta, el paso de página.
	 *
	 * Lleva el singular aparte porque ahora se filtra de verdad: filtrar hasta
	 * dejar un solo sitio es lo normal, y el pie decía «1 sitios».
	 */
	public static function paginador( int $pagina, int $paginas, int $total, string $que = 'sitios', string $uno = 'sitio' ): void {
		$cuenta = self::plural( $total, $uno, $que );

		if ( $paginas < 2 ) {
			printf( '<p class="swpk-cuenta">%s</p>', esc_html( $cuenta ) );
			return;
		}
		?>
		<div class="swpk-paginador">
			<span class="swpk-cuenta"><?php echo esc_html( $cuenta ); ?></span>
			<span>
				<?php if ( $pagina > 1 ) : ?>
					<a class="button" href="<?php echo esc_url( add_query_arg( 'p', $pagina - 1 ) ); ?>">‹ Anterior</a>
				<?php endif; ?>
				<span class="swpk-pag">Página <?php echo (int) $pagina; ?> de <?php echo (int) $paginas; ?></span>
				<?php if ( $pagina < $paginas ) : ?>
					<a class="button" href="<?php echo esc_url( add_query_arg( 'p', $pagina + 1 ) ); ?>">Siguiente ›</a>
				<?php endif; ?>
			</span>
		</div>
		<?php
	}

	/**
	 * El botón de regresar.
	 *
	 * Sin esto, al entrar a la ficha de un sitio no hay forma obvia de volver a
	 * la lista de donde se venía: queda el menú de la izquierda, que lleva al
	 * principio y pierde el grupo y el filtro que la persona había elegido.
	 */
	public static function volver( string $url, string $texto ): void {
		printf(
			'<p class="swpk-volver"><a href="%s">&larr; %s</a></p>',
			esc_url( $url ),
			esc_html( $texto )
		);
	}

	/**
	 * Las pantallas desde las que se puede entrar a la ficha de un sitio, y
	 * cómo se llama el camino de vuelta a cada una.
	 *
	 * ─── Por qué una lista y no dos ─────────────────────────────────────────
	 *
	 * Antes aquí solo valían Sitios y Vulnerabilidades, y cualquier otro
	 * origen caía en Vulnerabilidades. Resultado: se entraba a un sitio desde
	 * la Bitácora y el botón decía "Volver a Vulnerabilidades" y llevaba ahí.
	 *
	 *   "Yo estaba en bitácora y estaba viendo qué estaba pasando. ¿Yo para
	 *    qué quiero volver a vulnerabilidades? El botón me tiene que regresar
	 *    de donde yo vengo."
	 *
	 * Tiene razón, y es una lista: el día que haya otra pantalla que enlace a
	 * un sitio, se agrega aquí y el botón ya sabe volver.
	 *
	 * @var array<string,string>
	 */
	private const ORIGENES = [
		'panel-kinsta'                  => 'Volver a Inicio',
		'panel-kinsta-sitios'           => 'Volver a Sitios',
		'panel-kinsta-vulnerabilidades' => 'Volver a Vulnerabilidades',
		'panel-kinsta-bitacora'         => 'Volver a Bitácora',
	];

	/**
	 * Lo que hay que recordar de la lista de origen para devolver a la persona
	 * al mismo sitio donde estaba: su grupo, su filtro, su página, su búsqueda,
	 * su pestaña y su rango de fechas.
	 *
	 * Viaja con el prefijo `de_` porque `desde`, `hasta` y `tipo` también son
	 * parámetros de la ficha y del reporte: sin prefijo, cambiar el periodo de
	 * un reporte le cambiaría el rango a la Bitácora a la que se vuelve.
	 *
	 * @var array<int,string>
	 */
	private const ESTADO = [ 'tag', 'nivel', 'p', 'q', 'wp', 'php', 'seccion', 'desde', 'hasta', 'tipo' ];

	/** La dirección de la lista de donde se vino, tal como la dejó la persona. */
	public static function url_de_regreso(): array {
		$origen = isset( $_GET['de'] ) ? sanitize_key( (string) $_GET['de'] ) : '';
		if ( ! isset( self::ORIGENES[ $origen ] ) ) {
			$origen = 'panel-kinsta-vulnerabilidades';
		}

		$args = [ 'page' => $origen ];
		foreach ( self::ESTADO as $k ) {
			if ( ! empty( $_GET[ 'de_' . $k ] ) ) {
				$args[ $k ] = sanitize_text_field( wp_unslash( (string) $_GET[ 'de_' . $k ] ) );
			}
		}

		$texto = self::ORIGENES[ $origen ];

		// La Bitácora son dos pestañas y no es lo mismo volver a una que a la
		// otra: el botón dice a cuál.
		if ( 'panel-kinsta-bitacora' === $origen ) {
			$texto = 'notificaciones' === ( $args['seccion'] ?? 'movimientos' )
				? 'Volver a Notificaciones'
				: 'Volver a Movimientos';
		}

		return [
			'url'   => add_query_arg( $args, admin_url( 'admin.php' ) ),
			'texto' => $texto,
		];
	}

	/** El enlace a la ficha de un sitio, conservando de dónde se viene. */
	public static function url_del_sitio( string $sitio_id, string $origen ): string {
		$args = [
			'page'  => 'panel-kinsta-sitio',
			'sitio' => $sitio_id,
			'de'    => $origen,
		];

		foreach ( self::ESTADO as $k ) {
			if ( ! empty( $_GET[ $k ] ) ) {
				$args[ 'de_' . $k ] = sanitize_text_field( wp_unslash( (string) $_GET[ $k ] ) );
			}
		}

		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	/**
	 * El rastro de dónde se viene, para arrastrarlo un salto más.
	 *
	 * Hace falta porque de la ficha de un sitio se salta al reporte, y de ahí
	 * hay que poder volver dos pasos atrás, a la lista original.
	 *
	 * @return array<string,string>
	 */
	public static function rastro(): array {
		$args = [];

		if ( ! empty( $_GET['de'] ) ) {
			$args['de'] = sanitize_key( (string) $_GET['de'] );
		}

		foreach ( self::ESTADO as $k ) {
			if ( ! empty( $_GET[ 'de_' . $k ] ) ) {
				$args[ 'de_' . $k ] = sanitize_text_field( wp_unslash( (string) $_GET[ 'de_' . $k ] ) );
			}
		}

		return $args;
	}

	/** El mismo rastro, como campos ocultos de un formulario. */
	public static function rastro_oculto(): void {
		foreach ( self::rastro() as $k => $v ) {
			printf( '<input type="hidden" name="%s" value="%s">', esc_attr( $k ), esc_attr( $v ) );
		}
	}

	/**
	 * Una barra de filtros: unos cuantos desplegables y, si hace falta, un
	 * buscador, todo en un solo formulario.
	 *
	 * ─── Por qué en el servidor y no en el navegador ────────────────────────
	 *
	 * El demo que vio el jefe filtraba con JavaScript, sobre las filas ya
	 * dibujadas. Aquí no sirve: estas pantallas ya paginan en el servidor, así
	 * que en la página solo hay veinte filas de las ciento ocho. Un filtro de
	 * navegador habría filtrado esas veinte y habría dicho «ningún resultado»
	 * cuando lo buscado estaba en la página cuatro.
	 *
	 * Filtrando aquí, el filtro se aplica a todo y la paginación se recalcula
	 * sobre lo filtrado. De paso el filtro viaja en la dirección: se puede
	 * enlazar, guardar en favoritos y mandar por chat, y funciona igual aunque
	 * el navegador tenga el JavaScript apagado.
	 *
	 * El `p` de la página NO se arrastra a propósito: cambiar un filtro vuelve
	 * a la página uno. Quedarse en la siete de una lista que ahora tiene dos
	 * deja la tabla en blanco sin explicar por qué.
	 *
	 * @param string                                                                                         $pagina    Slug de la pantalla.
	 * @param array<string,array{etiqueta:string,opciones:array<string,string>,actual:string,todas?:string}>  $campos
	 * @param array<string,string>                                                                           $conservar Lo que viaja oculto.
	 * @param string|null                                                                                    $busqueda  null = sin buscador.
	 */
	public static function barra_de_filtros( string $pagina, array $campos, array $conservar = [], ?string $busqueda = null, string $marcador = 'Buscar un sitio…' ): void {
		$hay_algo = ( null !== $busqueda && '' !== $busqueda );
		foreach ( $campos as $c ) {
			if ( '' !== ( $c['actual'] ?? '' ) ) {
				$hay_algo = true;
			}
		}
		?>
		<form method="get" class="swpk-filtra">
			<input type="hidden" name="page" value="<?php echo esc_attr( $pagina ); ?>">
			<?php foreach ( $conservar as $k => $v ) : ?>
				<?php if ( '' !== (string) $v ) : ?>
					<input type="hidden" name="<?php echo esc_attr( $k ); ?>" value="<?php echo esc_attr( (string) $v ); ?>">
				<?php endif; ?>
			<?php endforeach; ?>

			<?php foreach ( $campos as $clave => $campo ) : ?>
				<label class="swpk-campo">
					<span><?php echo esc_html( $campo['etiqueta'] ); ?></span>
					<select name="<?php echo esc_attr( $clave ); ?>">
						<option value=""><?php echo esc_html( $campo['todas'] ?? 'Todas' ); ?></option>
						<?php foreach ( $campo['opciones'] as $valor => $texto ) : ?>
							<option value="<?php echo esc_attr( (string) $valor ); ?>" <?php selected( (string) $valor, $campo['actual'] ?? '' ); ?>>
								<?php echo esc_html( $texto ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</label>
			<?php endforeach; ?>

			<?php if ( null !== $busqueda ) : ?>
				<label class="swpk-campo swpk-campo-ancho">
					<span>Buscar</span>
					<input type="search" name="q" value="<?php echo esc_attr( $busqueda ); ?>"
						placeholder="<?php echo esc_attr( $marcador ); ?>">
				</label>
			<?php endif; ?>

			<button class="button swpk-filtra-ir">Filtrar</button>

			<?php if ( $hay_algo ) : ?>
				<a class="swpk-filtra-limpiar"
					href="<?php echo esc_url( remove_query_arg( array_merge( array_keys( $campos ), [ 'q', 'p' ] ) ) ); ?>">
					quitar los filtros
				</a>
			<?php endif; ?>
		</form>
		<?php
	}

	/**
	 * Lee un filtro de la dirección y lo valida contra lo que de verdad existe.
	 *
	 * Validar contra los valores presentes, y no contra una lista escrita a
	 * mano, evita dos cosas: que alguien invente un `?php=9.9` en la dirección
	 * y le salga una tabla vacía sin explicación, y que la lista de opciones se
	 * quede vieja el día que entre una versión nueva.
	 *
	 * @param array<string,string> $validas
	 */
	public static function opcion_pedida( string $clave, array $validas ): string {
		$v = isset( $_GET[ $clave ] ) ? sanitize_text_field( wp_unslash( (string) $_GET[ $clave ] ) ) : '';
		return isset( $validas[ $v ] ) ? $v : '';
	}

	/**
	 * Los valores distintos que hay en una lista, para armar un desplegable.
	 *
	 * @param array<int,mixed>       $filas
	 * @param callable(mixed):string $saca
	 * @return array<string,string>
	 */
	public static function valores_unicos( array $filas, callable $saca, bool $como_version = false ): array {
		$vistos = [];
		foreach ( $filas as $fila ) {
			$v = trim( (string) $saca( $fila ) );
			if ( '' !== $v ) {
				$vistos[ $v ] = $v;
			}
		}

		if ( $como_version ) {
			uksort( $vistos, static fn( $a, $b ) => version_compare( (string) $b, (string) $a ) );
		} else {
			ksort( $vistos, SORT_NATURAL | SORT_FLAG_CASE );
		}

		return $vistos;
	}

	/* ──────────────────────────────────────────────────────────────────────
	   La maqueta aprobada: cabecera, tarjetas y las piezas que van dentro.

	   Esto se construyó después de que el jefe aprobara una maqueta estática y
	   nosotros subiéramos solo la pintura. Aprobar la maqueta no la convierte
	   en el plugin: cada pieza de aquí es una de las suyas, vuelta a hacer en
	   PHP para que se alimente de los datos de verdad.

	   Todo imprime en vez de devolver, como el resto de esta clase, porque las
	   vistas son plantillas y no constructores de cadenas.
	   ────────────────────────────────────────────────────────────────────── */

	/**
	 * La cabecera de la pantalla: título, una línea de contexto y, a la
	 * derecha, lo que la pantalla quiera poner ahí (las pastillas de grupo,
	 * normalmente).
	 *
	 * El h1 va aquí y no suelto porque WordPress le pone su propio margen y
	 * entonces el título y las pastillas dejan de estar a la misma altura.
	 */
	public static function cabecera( string $titulo, string $sub = '', string $derecha = '' ): void {
		?>
		<div class="swpk-cabecera">
			<div>
				<h1><?php echo esc_html( $titulo ); ?></h1>
				<?php if ( '' !== $sub ) : ?>
					<div class="swpk-cabecera-sub"><?php echo wp_kses_post( $sub ); ?></div>
				<?php endif; ?>
			</div>
			<?php if ( '' !== $derecha ) : ?>
				<div class="swpk-pastillas"><?php echo wp_kses_post( $derecha ); ?></div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Las pastillas de la cabecera. Devuelve en vez de imprimir porque entran
	 * como argumento de cabecera().
	 *
	 * Cada una es un enlace de verdad: el número no decora, dice a cuántos
	 * sitios te lleva. Una pastilla que no lleva a ninguna parte es un
	 * contador, que es justo lo que se pidió quitar.
	 *
	 * @param array<int,array<string,mixed>> $items texto, n, url y activa.
	 */
	public static function pastillas( array $items ): string {
		$html = '';

		foreach ( $items as $it ) {
			// Sin número también vale: las pastillas que hacen de pestañas
			// —Movimientos, Notificaciones— no cuentan nada, solo llevan.
			$n = (string) ( $it['n'] ?? '' );

			$html .= sprintf(
				'<a class="swpk-pastilla%s" href="%s">%s%s</a>',
				! empty( $it['activa'] ) ? ' swpk-pastilla-activa' : '',
				esc_url( (string) $it['url'] ),
				esc_html( (string) $it['texto'] ),
				'' !== $n ? '<span class="swpk-pastilla-n">' . esc_html( $n ) . '</span>' : ''
			);
		}

		return $html;
	}

	public static function rejilla_abrir(): void {
		echo '<div class="swpk-rejilla">';
	}

	public static function rejilla_cerrar(): void {
		echo '</div>';
	}

	/**
	 * Abrir una tarjeta.
	 *
	 * @param string $titulo   El de la cabecera, a la izquierda.
	 * @param string $pie      Lo de la derecha: una fecha, o un enlace a la
	 *                         pantalla donde está todo. Admite HTML.
	 * @param string $ancho    Columnas de la rejilla: swpk-c4, swpk-c6, …
	 *                         Vacío si la tarjeta no va dentro de una rejilla.
	 * @param bool   $sin_aire Para lo que trae su propio relleno: tablas y
	 *                         listas, que ya llevan el suyo y se vería doble.
	 */
	public static function caja_abrir( string $titulo, string $pie = '', string $ancho = '', bool $sin_aire = false ): void {
		printf( '<section class="swpk-caja %s">', esc_attr( $ancho ) );

		if ( '' !== $titulo ) {
			printf(
				'<div class="swpk-caja-cab"><h2>%s</h2>%s</div>',
				esc_html( $titulo ),
				'' !== $pie ? '<span class="swpk-pie-dato">' . wp_kses_post( $pie ) . '</span>' : ''
			);
		}

		printf( '<div class="swpk-caja-cuerpo%s">', $sin_aire ? ' swpk-sin-aire' : '' );
	}

	public static function caja_cerrar(): void {
		echo '</div></section>';
	}

	/**
	 * Una lista dentro de una tarjeta: nombre, explicación debajo y un dato a
	 * la derecha. Es el formato de «Lo que no puede esperar» y de «Novedades».
	 *
	 * @param array<int,array<string,mixed>> $filas nom, sub, dato y url.
	 * @param string                         $vacio Qué decir si no hay nada.
	 *                                              Una lista vacía sin frase se
	 *                                              lee como «no cargó».
	 */
	public static function lista( array $filas, string $vacio = 'Nada por aquí.' ): void {
		if ( ! $filas ) {
			printf( '<p class="swpk-caja-vacia" style="padding:18px 20px;color:var(--swpk-apagado)">%s</p>', esc_html( $vacio ) );
			return;
		}

		echo '<ul class="swpk-renglones">';

		foreach ( $filas as $f ) {
			$nom = esc_html( (string) $f['nom'] );
			if ( ! empty( $f['url'] ) ) {
				$nom = sprintf( '<a href="%s">%s</a>', esc_url( (string) $f['url'] ), $nom );
			}

			printf(
				'<li><div><div class="swpk-renglon-nom">%s</div>%s</div><div class="swpk-renglon-dato">%s</div></li>',
				$nom,
				'' !== (string) ( $f['sub'] ?? '' )
					? '<div class="swpk-renglon-sub">' . esc_html( (string) $f['sub'] ) . '</div>'
					: '',
				wp_kses_post( (string) ( $f['dato'] ?? '' ) )
			);
		}

		echo '</ul>';
	}

	/**
	 * La dona de la cartera, en SVG y sin una sola línea de JavaScript.
	 *
	 * Se dibuja con un círculo por trozo y el truco de siempre: la raya del
	 * trazo mide lo que vale el trozo y el hueco mide el resto, y cada uno
	 * empieza donde terminó el anterior. Sin librería de gráficas: son seis
	 * círculos, y una librería para esto son 90 KB que cargan en cada pantalla.
	 *
	 * @param array<int,array<string,mixed>> $trozos n, color y texto.
	 */
	public static function dona( array $trozos, int $total, string $pie = 'sitios' ): void {
		$radio  = 52.0;
		$vuelta = 2 * M_PI * $radio;
		$suma   = 0;

		foreach ( $trozos as $t ) {
			$suma += (int) $t['n'];
		}

		echo '<div class="swpk-dona">';
		echo '<svg viewBox="0 0 140 140" width="140" height="140" role="img" aria-label="' .
			esc_attr( sprintf( '%d %s', $total, $pie ) ) . '">';

		printf(
			'<circle cx="70" cy="70" r="%s" fill="none" stroke="#f0f1f6" stroke-width="16"/>',
			esc_attr( (string) $radio )
		);

		$acumulado = 0.0;

		foreach ( $trozos as $t ) {
			$n = (int) $t['n'];
			if ( $n <= 0 || $suma <= 0 ) {
				continue;
			}

			$largo = $vuelta * $n / $suma;

			printf(
				'<circle cx="70" cy="70" r="%s" fill="none" stroke="%s" stroke-width="16"'
				. ' stroke-dasharray="%s %s" stroke-dashoffset="%s" transform="rotate(-90 70 70)"/>',
				esc_attr( (string) $radio ),
				esc_attr( (string) $t['color'] ),
				esc_attr( number_format( $largo, 2, '.', '' ) ),
				esc_attr( number_format( $vuelta - $largo, 2, '.', '' ) ),
				esc_attr( number_format( -$acumulado, 2, '.', '' ) )
			);

			$acumulado += $largo;
		}

		printf(
			'<text x="70" y="66" text-anchor="middle" font-size="27" font-weight="700" fill="#1b1b2f">%d</text>'
			. '<text x="70" y="85" text-anchor="middle" font-size="11" fill="#6b6f80">%s</text>',
			$total,
			esc_html( $pie )
		);

		echo '</svg>';
		self::leyenda( $trozos );
		echo '</div>';
	}

	/**
	 * La leyenda: punto de color, qué es, y cuántos.
	 *
	 * Lleva la palabra además del color a propósito. Es una regla que viene del
	 * jefe y no se toca: el color solo no le sirve a quien no distingue rojo de
	 * verde, y esto se imprime y se manda por correo.
	 *
	 * @param array<int,array<string,mixed>> $trozos n, color y texto.
	 */
	public static function leyenda( array $trozos ): void {
		echo '<ul class="swpk-claves">';

		foreach ( $trozos as $t ) {
			printf(
				'<li><span class="swpk-punto" style="background:%s"></span>'
				. '<span class="swpk-clave-t">%s</span><span class="swpk-clave-n">%d</span></li>',
				esc_attr( (string) $t['color'] ),
				esc_html( (string) $t['texto'] ),
				(int) $t['n']
			);
		}

		echo '</ul>';
	}

	/**
	 * La barra segmentada: la misma información que la dona, en una línea.
	 *
	 * @param array<int,array<string,mixed>> $trozos n y color.
	 */
	public static function barra_apilada( array $trozos ): void {
		$suma = 0;
		foreach ( $trozos as $t ) {
			$suma += (int) $t['n'];
		}

		if ( $suma <= 0 ) {
			return;
		}

		echo '<div class="swpk-barra-apilada">';

		foreach ( $trozos as $t ) {
			if ( (int) $t['n'] <= 0 ) {
				continue;
			}
			printf(
				'<span style="background:%s;width:%s%%"></span>',
				esc_attr( (string) $t['color'] ),
				esc_attr( number_format( 100 * (int) $t['n'] / $suma, 2, '.', '' ) )
			);
		}

		echo '</div>';
	}

	/**
	 * La gráfica de línea, también en SVG y a mano.
	 *
	 * Con dos puntos o menos no se dibuja nada: una línea entre dos días no es
	 * una tendencia, es una raya, y enseñarla como si lo fuera es presumir de
	 * un dato que el panel todavía no tiene.
	 *
	 * @param array<int,array<string,mixed>> $puntos etq y n.
	 */
	public static function grafica( array $puntos ): void {
		$cuantos = count( $puntos );

		if ( $cuantos < 3 ) {
			return;
		}

		$ancho  = 660.0;
		$alto   = 124.0;
		$margen = 26.0;
		$techo  = 0;

		foreach ( $puntos as $p ) {
			$techo = max( $techo, (int) $p['n'] );
		}

		$techo = max( 1, $techo );
		$paso  = ( $ancho - 2 * $margen ) / ( $cuantos - 1 );
		$xy    = [];

		foreach ( array_values( $puntos ) as $i => $p ) {
			$xy[] = [
				$margen + $paso * $i,
				$alto - ( $alto - 14.0 ) * ( (int) $p['n'] ) / $techo,
			];
		}

		// Curva suave: cada tramo es una cúbica con los tiradores a media
		// distancia, que es lo que redondea la esquina sin inventarse valores
		// entre un día y el siguiente.
		$linea = sprintf( 'M%s,%s', number_format( $xy[0][0], 1, '.', '' ), number_format( $xy[0][1], 1, '.', '' ) );

		for ( $i = 1; $i < count( $xy ); $i++ ) {
			$medio  = ( $xy[ $i - 1 ][0] + $xy[ $i ][0] ) / 2;
			$linea .= sprintf(
				' C%s,%s %s,%s %s,%s',
				number_format( $medio, 1, '.', '' ),
				number_format( $xy[ $i - 1 ][1], 1, '.', '' ),
				number_format( $medio, 1, '.', '' ),
				number_format( $xy[ $i ][1], 1, '.', '' ),
				number_format( $xy[ $i ][0], 1, '.', '' ),
				number_format( $xy[ $i ][1], 1, '.', '' )
			);
		}

		$relleno = $linea . sprintf(
			' L%s,%s L%s,%s Z',
			number_format( end( $xy )[0], 1, '.', '' ),
			number_format( $alto, 1, '.', '' ),
			number_format( $xy[0][0], 1, '.', '' ),
			number_format( $alto, 1, '.', '' )
		);

		$id = 'swpk-deg-' . wp_rand( 1000, 9999 );

		echo '<svg class="swpk-grafica" viewBox="0 0 660 150" preserveAspectRatio="none" style="height:150px" aria-hidden="true">';
		printf(
			'<defs><linearGradient id="%1$s" x1="0" y1="0" x2="0" y2="1">'
			. '<stop offset="0%%" stop-color="var(--swpk-marca)" stop-opacity=".16"/>'
			. '<stop offset="100%%" stop-color="var(--swpk-marca)" stop-opacity="0"/></linearGradient></defs>'
			. '<path d="%2$s" fill="url(#%1$s)"/>'
			. '<path d="%3$s" fill="none" stroke="var(--swpk-marca)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>',
			esc_attr( $id ),
			esc_attr( $relleno ),
			esc_attr( $linea )
		);

		foreach ( array_values( $puntos ) as $i => $p ) {
			printf(
				'<circle cx="%s" cy="%s" r="3" fill="var(--swpk-marca)"><title>%s</title></circle>',
				number_format( $xy[ $i ][0], 1, '.', '' ),
				number_format( $xy[ $i ][1], 1, '.', '' ),
				esc_html( sprintf( '%s: %d', (string) $p['etq'], (int) $p['n'] ) )
			);
		}

		// Solo el primero, el de en medio y el último: con quince días, una
		// etiqueta por punto se convierte en una mancha ilegible.
		foreach ( [ 0, intdiv( $cuantos - 1, 2 ), $cuantos - 1 ] as $i ) {
			printf(
				'<text x="%s" y="144" text-anchor="middle" font-size="10" fill="#9598a8">%s</text>',
				number_format( $xy[ $i ][0], 1, '.', '' ),
				esc_html( (string) array_values( $puntos )[ $i ]['etq'] )
			);
		}

		echo '</svg>';
	}

	public static function pagina_pedida(): int {
		return isset( $_GET['p'] ) ? max( 1, (int) $_GET['p'] ) : 1;
	}

	public static function tag_pedido(): string {
		$t = isset( $_GET['tag'] ) ? sanitize_key( (string) $_GET['tag'] ) : '';
		return isset( SWPK_Etiquetas::nombres()[ $t ] ) ? $t : '';
	}

	public static function nivel_pedido(): string {
		$n = isset( $_GET['nivel'] ) ? sanitize_key( (string) $_GET['nivel'] ) : '';
		return isset( SWPK_Riesgo::nombres()[ $n ] ) ? $n : '';
	}

	public static function busqueda_pedida(): string {
		return isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['q'] ) ) : '';
	}

	/**
	 * Filtra una lista de sitios por grupo, nivel, búsqueda y versiones.
	 *
	 * Las versiones de WordPress y de PHP las pidió el jefe: «filtrar por
	 * versión de WordPress y por versión de PHP». Van aquí dentro, y no como un
	 * filtro aparte en la pantalla, para que el Excel salga igual que lo que se
	 * está viendo — el botón de bajar a Excel recorre esta misma función. Un
	 * Excel con más filas que la pantalla de la que salió no se puede enseñar.
	 *
	 * @param array<int,object> $sitios
	 * @return array<int,object>
	 */
	public static function filtrar( array $sitios, string $tag, string $nivel, string $busqueda, string $wp = '', string $php = '' ): array {
		return array_values(
			array_filter(
				$sitios,
				static function ( $s ) use ( $tag, $nivel, $busqueda, $wp, $php ) {
					if ( '' !== $tag && $s->tag !== $tag ) {
						return false;
					}
					if ( '' !== $nivel && SWPK_Riesgo::nivel_del_sitio( $s->sitio_id ) !== $nivel ) {
						return false;
					}
					if ( '' !== $wp && (string) $s->version_wp !== $wp ) {
						return false;
					}
					if ( '' !== $php && (string) $s->version_php !== $php ) {
						return false;
					}
					if ( '' !== $busqueda ) {
						$aguja = strtolower( remove_accents( $busqueda ) );
						$pajar = strtolower( remove_accents( $s->nombre_visible . ' ' . $s->dominio ) );
						if ( ! str_contains( $pajar, $aguja ) ) {
							return false;
						}
					}
					return true;
				}
			)
		);
	}

	/**
	 * Las versiones de WordPress y de PHP que de verdad hay, ya listas para
	 * armar los dos desplegables, con lo pedido ya leído y validado.
	 *
	 * @param array<int,object> $sitios Sobre los que se arman las opciones.
	 * @return array{campos:array<string,array<string,mixed>>,wp:string,php:string}
	 */
	public static function filtros_de_version( array $sitios ): array {
		$wps  = self::valores_unicos( $sitios, static fn( $s ) => (string) $s->version_wp, true );
		$phps = self::valores_unicos( $sitios, static fn( $s ) => (string) $s->version_php, true );

		return [
			'campos' => [
				'wp'  => [
					'etiqueta' => 'WordPress',
					'opciones' => $wps,
					'actual'   => self::opcion_pedida( 'wp', $wps ),
					'todas'    => 'Cualquiera',
				],
				'php' => [
					'etiqueta' => 'PHP',
					'opciones' => $phps,
					'actual'   => self::opcion_pedida( 'php', $phps ),
					'todas'    => 'Cualquiera',
				],
			],
			'wp'     => self::opcion_pedida( 'wp', $wps ),
			'php'    => self::opcion_pedida( 'php', $phps ),
		];
	}

	/** @param array<int,object> $sitios */
	public static function conteo_por_nivel( array $sitios ): array {
		$conteo = array_fill_keys( array_keys( SWPK_Riesgo::nombres() ), 0 );

		foreach ( $sitios as $s ) {
			$n            = SWPK_Riesgo::nivel_del_sitio( $s->sitio_id );
			$conteo[ $n ] = ( $conteo[ $n ] ?? 0 ) + 1;
		}

		return $conteo;
	}

	/** @param array<int,object> $sitios */
	public static function conteo_por_tag( array $sitios ): array {
		$conteo = array_fill_keys( array_keys( SWPK_Etiquetas::nombres() ), 0 );

		foreach ( $sitios as $s ) {
			$conteo[ $s->tag ] = ( $conteo[ $s->tag ] ?? 0 ) + 1;
		}

		return $conteo;
	}
}
