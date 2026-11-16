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
