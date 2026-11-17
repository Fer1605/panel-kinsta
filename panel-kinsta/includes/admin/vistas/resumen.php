<?php
/**
 * Inicio: el diario de lo que pasó, agrupado por sitio.
 *
 * ─── Por qué agrupado, y no una fila por cambio ─────────────────────────────
 *
 * La primera versión del diario ponía una fila por evento. Con los datos
 * reales eso son 128 filas en un día normal, casi todas del mismo puñado de
 * sitios, y la pantalla se volvía un scroll interminable donde no se encuentra
 * nada:
 *
 *   "tengo como 20.000 cosas de un solo sitio y aparte se vuelve súper largo
 *    el scroll (...) imagínate que se actualicen 80 y tantos sitios con 5
 *    plugins cada uno, a qué hora se encuentra qué está pasando"
 *
 * Tenía razón, y el cálculo también: 80 sitios por 5 módulos son 400 filas.
 * Ahora es una fila por SITIO y por día, con el resumen de lo que le pasó. Los
 * mismos 128 cambios caben en unas 40 filas, y cada una dice de qué sitio es.
 *
 * Y lleva buscador, que es lo otro que faltaba: con 108 sitios, buscar uno a
 * ojo por el scroll no es buscar.
 */
defined( 'ABSPATH' ) || exit;

$estado   = SWPK_Estado::calcular();
$sin_leer = count( $estado['urgentes'] );
$busqueda = SWPK_Vista::busqueda_pedida();

$sitios = [];
foreach ( SWPK_Perfiles::sitios_visibles() as $s ) {
	$sitios[ (string) $s->sitio_id ] = $s;
}

$puede_todo = SWPK_Perfiles::puede( SWPK_Perfiles::VER_TODO );
$eventos    = array_filter(
	SWPK_Eventos::recientes( 600 ),
	static fn( $e ) => $puede_todo || isset( $sitios[ (string) $e->sitio_id ] )
);

// Agrupado por día y, dentro del día, por sitio. El día de hoy aparece
// siempre, tenga algo o no: es el que contesta "¿pasó algo?".
$hoy     = current_time( 'Y-m-d' );
$por_dia = [ $hoy => [] ];

foreach ( $eventos as $e ) {
	$sitio  = $sitios[ (string) $e->sitio_id ] ?? null;
	$nombre = (string) ( $sitio->nombre_visible ?? '' );

	if ( '' !== $busqueda && false === stripos( $nombre, $busqueda ) ) {
		continue;
	}

	$dia   = substr( (string) $e->ocurrio_en, 0, 10 );
	$clave = (string) $e->sitio_id;

	if ( ! isset( $por_dia[ $dia ][ $clave ] ) ) {
		$por_dia[ $dia ][ $clave ] = [
			'sitio'    => $sitio,
			'nombre'   => '' !== $nombre ? $nombre : (string) $e->sitio_id,
			'hora'     => (string) $e->ocurrio_en,
			'grave'    => false,
			'mirar'    => false,
			'por_tipo' => [],
		];
	}

	$fila = &$por_dia[ $dia ][ $clave ];
	$fila['por_tipo'][ $e->tipo ][] = $e;
	if ( 'critico' === $e->severidad ) {
		$fila['grave'] = true;
	}
	if ( ! SWPK_Eventos::es_rutinario( (string) $e->tipo ) ) {
		$fila['mirar'] = true;
	}
	unset( $fila );
}

// Un día se parte en dos montones, pero los dos se ENSEÑAN: arriba lo que
// necesita atención, abajo lo que salió bien solo.
//
// Antes el segundo montón solo se contaba, y eso dejaba días que decían
// "nada que mirar" justo encima de una línea admitiendo que sí hubo
// movimiento:
//
//   "no puede ser que hoy no se haya actualizado ninguno (...) si tiene
//    registro de que un sitio hizo movimientos, aquí debería de aparecer"
//
// Tenía razón: un contador no es el dato, es la promesa de que el dato está
// en otra parte. Siguen separados —mezclarlos entierra lo urgente entre 128
// actualizaciones que salieron bien— pero ya no se esconde nada.
$solos_dia   = [];
$resumen_dia = [];

foreach ( $por_dia as $dia => $del_dia ) {
	$mirar = [];
	$solos = [];
	$bien  = [ 'sitios' => 0, 'modulos' => 0 ];

	foreach ( $del_dia as $clave => $f ) {
		if ( ! empty( $f['mirar'] ) ) {
			$mirar[ $clave ] = $f;
			continue;
		}

		$solos[ $clave ] = $f;
		++$bien['sitios'];

		foreach ( $f['por_tipo'] as $lista ) {
			$bien['modulos'] += count( $lista );
		}
	}

	$por_dia[ $dia ]     = $mirar;
	$solos_dia[ $dia ]   = $solos;
	$resumen_dia[ $dia ] = $bien;
}

// Solo hacen falta dos cifras del día para la frase de arriba: cuántos sitios
// necesitan atención y cuántos se movieron sin que nadie entrara. El resto de
// lo que se calculaba aquí —el detalle renglón por renglón— era para la tabla
// del diario, que ya no está: vive en Movimientos, con sus filtros.
$por_dia  = array_slice( $por_dia, 0, 8, true );
$etq_evt  = SWPK_Eventos::etiquetas();
$de_hoy   = count( $por_dia[ $hoy ] ?? [] );
$bien_hoy = $resumen_dia[ $hoy ] ?? [ 'sitios' => 0, 'modulos' => 0 ];

?>
<div class="wrap">
	<?php
	// Las pastillas de grupo, a la derecha del título. Cada una lleva a Sitios
	// ya filtrado por esa etiqueta: el número dice a cuántos te lleva, no es
	// un contador de adorno.
	$conteo_tags = SWPK_Vista::conteo_por_tag( SWPK_Perfiles::sitios_visibles() );
	$pastillas   = [];

	foreach ( SWPK_Etiquetas::nombres() as $tag => $nombre ) {
		if ( empty( $conteo_tags[ $tag ] ) ) {
			continue;
		}

		$pastillas[] = [
			'texto' => $nombre,
			'n'     => $conteo_tags[ $tag ],
			'url'   => add_query_arg(
				[ 'page' => 'panel-kinsta-sitios', 'tag' => $tag ],
				admin_url( 'admin.php' )
			),
		];
	}

	SWPK_Vista::cabecera(
		'Panel Kinsta',
		// «revisados hoy» solo si de verdad se revisaron hoy. La maqueta lo
		// daba por hecho; aquí la corrida puede no haber pasado, y decirlo
		// igual sería la clase de mentira tranquilizadora que este panel no
		// cuenta. Si no pasó, se calla — el aviso de frescura de abajo ya lo
		// dice con todas sus letras.
		$estado['total']
			? esc_html( sprintf(
				SWPK_Frescura::corrida_al_dia()
					? 'Los %d sitios de la cuenta, revisados hoy.'
					: 'Los %d sitios de la cuenta.',
				(int) $estado['total']
			) )
			: '',
		SWPK_Vista::pastillas( $pastillas )
	);
	?>
	<?php SWPK_Admin::mostrar_mensaje(); ?>
	<?php SWPK_Frescura::aviso(); ?>

	<?php
	// ── Las normas de seguridad, en una línea ───────────────────────────────
	//
	// Un sitio con el editor de código abierto está mal aunque tenga todos sus
	// plugins al día, y el Resumen no lo sabría: su riesgo se calcula con
	// vulnerabilidades y actualizaciones. Así que la cuenta de normas
	// incumplidas se dice aquí, con el enlace puesto, en vez de esperar a que
	// alguien se acuerde de abrir otra pestaña.
	//
	// Una línea y no un bloque: lo que manda el Resumen son las
	// vulnerabilidades, que es lo que se aprovecha desde internet sin tener
	// cuenta en el sitio.
	$normas_fallando = 0;
	$sitios_fallando = 0;

	foreach ( SWPK_Normas::cumplimiento() as $sitio_id => $suyas ) {
		$cuantas = 0;

		foreach ( $suyas as $c ) {
			if ( SWPK_Normas::FALLA === (string) $c->estado ) {
				++$cuantas;
			}
		}

		if ( $cuantas > 0 ) {
			++$sitios_fallando;
			$normas_fallando += $cuantas;
		}
	}
	?>

	<?php // La línea de normas baja con el resto: arriba empujaba las tarjetas. ?>
	<?php if ( false ) : ?>
		<p class="swpk-linea">
			<strong><?php echo esc_html( SWPK_Vista::plural( $sitios_fallando, 'sitio incumple', 'sitios incumplen' ) ); ?></strong>
			alguna norma de seguridad
			(<?php echo esc_html( SWPK_Vista::plural( $normas_fallando, 'incumplimiento', 'incumplimientos' ) ); ?> en total).
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=panel-kinsta-seguridad' ) ); ?>">Ver cuáles &rarr;</a>
		</p>
	<?php endif; ?>

	<?php if ( ! $estado['total'] ) : ?>

		<div class="notice notice-info"><p>
			Todavía no hay inventario. Dale a <strong>Correr ahora</strong> para traer los sitios
			y tomar el primer inventario.
		</p></div>

	<?php else : ?>

		<?php
		// ── La rejilla ──────────────────────────────────────────────────────
		//
		// Esta es la maqueta que el jefe aprobó, construida con los datos de
		// verdad. Son cinco tarjetas, y el orden es el suyo: primero lo que
		// hay que atender con el nombre del sitio puesto, después cómo está la
		// cartera entera, y al final las dos cifras de volumen.
		//
		// Lo que NO es: ocho contadores en fila, que es con lo que abría antes
		// y lo que él rechazó dos veces —"no pequeños resumenes de numeros"—.
		// Un número aquí siempre viene acompañado del enlace a los renglones
		// que lo forman.
		$colores = SWPK_Riesgo::colores();
		$nombres = SWPK_Riesgo::nombres();
		$urg     = $estado['caidos'] ? array_merge( $estado['caidos'], $estado['urgentes'] ) : $estado['urgentes'];
		?>

		<?php SWPK_Vista::rejilla_abrir(); ?>

			<?php
			// ── Lo que no puede esperar ─────────────────────────────────────
			SWPK_Vista::caja_abrir(
				'Lo que no puede esperar',
				$urg
					? sprintf(
						'<a href="%s">%s</a>',
						esc_url( admin_url( 'admin.php?page=panel-kinsta-vulnerabilidades' ) ),
						1 === count( $urg ) ? 'Ver el sitio' : esc_html( sprintf( 'Ver los %d', count( $urg ) ) )
					)
					: '',
				'swpk-c4',
				true
			);

			$filas_urg = [];

			foreach ( array_slice( $urg, 0, 6 ) as $u ) {
				$sub = trim( (string) $u['modulo'] );
				if ( '' !== (string) $u['explicacion'] ) {
					$sub = ( '' !== $sub ? $sub . ' · ' : '' ) . $u['explicacion'];
				}

				$filas_urg[] = [
					'nom'  => $u['nombre'],
					'sub'  => SWPK_Vista::recortar( $sub, 95 ),
					'url'  => SWPK_Vista::url_del_sitio( (string) $u['sitio_id'], 'panel-kinsta' ),
					'dato' => 'caido' === $u['nivel']
						? '<span class="swpk-nivel swpk-nivel-caido">Caído</span>'
						: SWPK_Admin::nivel_badge( (string) $u['nivel'] ),
				];
			}

			SWPK_Vista::lista( $filas_urg, 'Nada urgente. Ningún sitio tiene una falla que se aproveche desde internet sin cuenta.' );
			SWPK_Vista::caja_cerrar();
			?>

			<?php
			// ── Cómo está la cartera ────────────────────────────────────────
			SWPK_Vista::caja_abrir(
				'Cómo está la cartera',
				esc_html( SWPK_Frescura::fmt( 'j M', $estado['armado_en'] ) ),
				'swpk-c4'
			);

			$trozos = [];
			foreach ( $estado['por_nivel'] as $nivel => $cuantos ) {
				$trozos[] = [
					'n'      => (int) $cuantos,
					'color'  => $colores[ $nivel ] ?? '#8c8f94',
					'texto'  => $nombres[ $nivel ] ?? $nivel,
				];
			}

			SWPK_Vista::dona( $trozos, (int) $estado['total'], 'sitios' );
			SWPK_Vista::caja_cerrar();
			?>

			<?php
			// ── Novedades ───────────────────────────────────────────────────
			SWPK_Vista::caja_abrir(
				'Novedades',
				sprintf(
					'<a href="%s">Ver todo</a>',
					esc_url( admin_url( 'admin.php?page=panel-kinsta-bitacora&seccion=movimientos' ) )
				),
				'swpk-c4',
				true
			);

			$ultimos = [];

			foreach ( array_slice( array_values( $eventos ), 0, 6 ) as $e ) {
				$ultimos[] = [
					'nom'  => SWPK_Vista::recortar( (string) $e->titulo, 60 ),
					'sub'  => $etq_evt[ $e->tipo ] ?? $e->tipo,
					'url'  => isset( $sitios[ (string) $e->sitio_id ] )
						? SWPK_Vista::url_del_sitio( (string) $e->sitio_id, 'panel-kinsta' )
						: '',
					'dato' => esc_html( SWPK_Frescura::fmt( 'j M', (string) $e->ocurrio_en ) ),
				];
			}

			SWPK_Vista::lista( $ultimos, 'Todavía no hay nada anotado.' );
			SWPK_Vista::caja_cerrar();
			?>

			<?php
			// ── Se actualizaron ─────────────────────────────────────────────
			//
			// El pie dice desde cuándo mide el panel, y no «este mes», porque
			// el panel lleva pocos días midiendo y un «este mes» con tres días
			// de datos es un número que se lee como lo que no es.
			$por_dia_act = SWPK_Eventos::conteo_por_dia( SWPK_Eventos::ACTUALIZADO, 14 );
			$primer_dia  = SWPK_Eventos::primer_dia();
			$dias_mide   = $primer_dia
				? max( 1, 1 + (int) floor( ( strtotime( current_time( 'Y-m-d' ) ) - strtotime( $primer_dia ) ) / DAY_IN_SECONDS ) )
				: 0;

			SWPK_Vista::caja_abrir(
				'Se actualizaron',
				$primer_dia
					? esc_html( sprintf(
						'%s – %s',
						SWPK_Frescura::fmt( 'j M', $primer_dia . ' 12:00:00' ),
						SWPK_Frescura::fmt( 'j M', current_time( 'Y-m-d' ) . ' 12:00:00' )
					) )
					: '',
				'swpk-c6'
			);
			?>
				<div class="swpk-cifron"><?php echo (int) array_sum( $por_dia_act ); ?></div>
				<div class="swpk-cifron-pie">
					<?php
					$d = min( $dias_mide, 14 );
					echo esc_html( sprintf(
						'módulos actualizados en %s',
						0 === $d ? 'lo que lleva midiendo el panel'
							: ( 1 === $d ? 'el día que lleva midiendo el panel'
								: sprintf( 'los %d días que lleva midiendo el panel', $d ) )
					) );
					?>
				</div>
				<?php SWPK_Vista::grafica( array_map(
					static fn( $dia, $n ) => [ 'etq' => SWPK_Frescura::fmt( 'j M', $dia . ' 12:00:00' ), 'n' => $n ],
					array_keys( $por_dia_act ),
					array_values( $por_dia_act )
				) ); ?>
			<?php SWPK_Vista::caja_cerrar(); ?>

			<?php
			// ── Vulnerabilidades y pendientes ───────────────────────────────
			SWPK_Vista::caja_abrir(
				'Vulnerabilidades y pendientes',
				sprintf(
					'<a href="%s">Ver todo</a>',
					esc_url( admin_url( 'admin.php?page=panel-kinsta-vulnerabilidades' ) )
				),
				'swpk-c6'
			);

			$sev = [];
			foreach ( [ SWPK_Riesgo::CRITICO, SWPK_Riesgo::ALTO, SWPK_Riesgo::MEDIO, SWPK_Riesgo::BAJO ] as $nivel ) {
				$sev[] = [
					'n'     => (int) ( $estado['por_sev'][ $nivel ] ?? 0 ),
					'color' => $colores[ $nivel ],
					'texto' => $nombres[ $nivel ],
				];
			}
			?>
				<div class="swpk-cifron"><?php echo (int) $estado['hallazgos']; ?></div>
				<div class="swpk-cifron-pie">
					<?php
					echo esc_html( sprintf(
						'%s en %s',
						SWPK_Vista::plural( (int) $estado['hallazgos'], 'hallazgo abierto', 'hallazgos abiertos' ),
						SWPK_Vista::plural( (int) $estado['con_hallazgos'], 'sitio', 'sitios' )
					) );
					?>
				</div>
				<?php
				SWPK_Vista::barra_apilada( $sev );
				SWPK_Vista::leyenda( $sev );
				?>
			<?php SWPK_Vista::caja_cerrar(); ?>

		<?php SWPK_Vista::rejilla_cerrar(); ?>

		<p class="swpk-linea">
			<strong>
				<?php
				if ( 0 === $de_hoy && 0 === $bien_hoy['sitios'] ) {
					echo esc_html( sprintf( 'Hoy no ha cambiado nada en los %d sitios.', (int) $estado['total'] ) );
				} elseif ( 0 === $de_hoy ) {
					echo esc_html( sprintf(
						'Hoy ningún sitio necesita atención. Hubo movimiento en %s, y %s.',
						SWPK_Vista::plural( (int) $bien_hoy['sitios'], 'sitio', 'sitios' ),
						1 === (int) $bien_hoy['sitios'] ? 'salió bien solo' : 'salieron bien solos'
					) );
				} else {
					echo esc_html( sprintf( 'Hoy %s atención.', SWPK_Vista::plural( $de_hoy, 'sitio necesita', 'sitios necesitan' ) ) );
				}
				?>
			</strong>
			<?php if ( $sin_leer ) : ?>
				Y <?php echo esc_html( SWPK_Vista::plural( $sin_leer, 'sitio sigue', 'sitios siguen' ) ); ?> con algo sin atender desde antes:
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=panel-kinsta-vulnerabilidades' ) ); ?>">verlos</a>.
			<?php else : ?>
				Y no hay nada sin atender de antes.
			<?php endif; ?>
		</p>

		<?php
		// ── Dónde quedó el diario ───────────────────────────────────────────
		//
		// Aquí iba una tabla con un renglón por sitio y por día, de los últimos
		// ocho. La maqueta aprobada no la trae: ahí los movimientos del día son
		// la tarjeta «Novedades», seis renglones y un enlace.
		//
		// No se perdió nada. Todo lo que enseñaba está en Movimientos, que
		// además filtra por fecha, por tipo y por sitio, cosa que esta tabla no
		// hacía. Si alguna vez se quiere de vuelta aquí, está en el historial
		// de git, en el commit anterior a este.
		?>

		<?php
		// Lo que antes separaba la cabecera de las tarjetas, ahora al pie.
		SWPK_Admin::tira_de_perfil();
		SWPK_Admin::aviso_demo();

		if ( SWPK_Perfiles::puede( SWPK_Perfiles::ADMINISTRAR ) ) {
			SWPK_Admin::barra_acciones();
		}
		?>

		<?php if ( $normas_fallando > 0 ) : ?>
			<p class="swpk-linea">
				<strong><?php echo esc_html( SWPK_Vista::plural( $sitios_fallando, 'sitio incumple', 'sitios incumplen' ) ); ?></strong>
				alguna norma de seguridad
				(<?php echo esc_html( SWPK_Vista::plural( $normas_fallando, 'incumplimiento', 'incumplimientos' ) ); ?> en total).
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=panel-kinsta-seguridad' ) ); ?>">Ver cuáles &rarr;</a>
			</p>
		<?php endif; ?>

		<?php SWPK_Frescura::sello(); ?>

	<?php endif; ?>

	<p class="swpk-atribucion"><?php echo wp_kses_post( SWPK_Wordfence::atribucion() ); ?></p>
</div>
