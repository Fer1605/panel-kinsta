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
					href="<?php echo esc_url( $es ? remove_query_arg( [ 'tag', 'nivel', 'p', 'q' ], $base ) : add_query_arg( 'tag', $tag, $base ) ); ?>">

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
	public static function paginar( array $todo, int $pagina_actual, int $por_pagina = self::POR_PAGINA ): array {
		$total   = count( $todo );
		$paginas = max( 1, (int) ceil( $total / $por_pagina ) );
		$pagina  = max( 1, min( $pagina_actual, $paginas ) );

		return [ array_slice( $todo, ( $pagina - 1 ) * $por_pagina, $por_pagina ), $pagina, $paginas ];
	}

	public static function paginador( int $pagina, int $paginas, int $total, string $que = 'sitios' ): void {
		if ( $paginas < 2 ) {
			printf(
				'<p class="swpk-cuenta">%d %s</p>',
				(int) $total,
				esc_html( $que )
			);
			return;
		}
		?>
		<div class="swpk-paginador">
			<span class="swpk-cuenta"><?php echo (int) $total; ?> <?php echo esc_html( $que ); ?></span>
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

	/** La dirección de la lista de donde se vino, con su grupo y su filtro. */
	public static function url_de_regreso(): array {
		$origen = isset( $_GET['de'] ) ? sanitize_key( (string) $_GET['de'] ) : 'panel-kinsta-vulnerabilidades';
		$origen = in_array( $origen, [ 'panel-kinsta-vulnerabilidades', 'panel-kinsta-sitios' ], true )
			? $origen
			: 'panel-kinsta-vulnerabilidades';

		$args = [ 'page' => $origen ];
		foreach ( [ 'tag', 'nivel', 'p', 'q' ] as $k ) {
			if ( ! empty( $_GET[ $k ] ) ) {
				$args[ $k ] = sanitize_text_field( wp_unslash( (string) $_GET[ $k ] ) );
			}
		}

		return [
			'url'   => add_query_arg( $args, admin_url( 'admin.php' ) ),
			'texto' => 'panel-kinsta-sitios' === $origen ? 'Volver a Sitios' : 'Volver a Vulnerabilidades',
		];
	}

	/** El enlace a la ficha de un sitio, conservando de dónde se viene. */
	public static function url_del_sitio( string $sitio_id, string $origen ): string {
		$args = [
			'page'  => 'panel-kinsta-sitio',
			'sitio' => $sitio_id,
			'de'    => $origen,
		];

		foreach ( [ 'tag', 'nivel', 'p', 'q' ] as $k ) {
			if ( ! empty( $_GET[ $k ] ) ) {
				$args[ $k ] = sanitize_text_field( wp_unslash( (string) $_GET[ $k ] ) );
			}
		}

		return add_query_arg( $args, admin_url( 'admin.php' ) );
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
	 * Filtra una lista de sitios por grupo, nivel y búsqueda.
	 *
	 * @param array<int,object> $sitios
	 * @return array<int,object>
	 */
	public static function filtrar( array $sitios, string $tag, string $nivel, string $busqueda ): array {
		return array_values(
			array_filter(
				$sitios,
				static function ( $s ) use ( $tag, $nivel, $busqueda ) {
					if ( '' !== $tag && $s->tag !== $tag ) {
						return false;
					}
					if ( '' !== $nivel && SWPK_Riesgo::nivel_del_sitio( $s->sitio_id ) !== $nivel ) {
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
