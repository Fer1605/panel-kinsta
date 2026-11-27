<?php
/**
 * Vulnerabilidades y pendientes: un renglón por hallazgo, y el renglón se abre.
 *
 * ─── Por qué cambió, la primera vez ─────────────────────────────────────────
 *
 * Esta pantalla enseñaba una ficha por sitio con dos cifras —«4 con
 * vulnerabilidad, 9 por actualizar»— y un botón para entrar. Servía para saber
 * a qué sitio ir, pero no contestaba la pregunta que de verdad se hace:
 * «¿quién tiene este plugin vulnerable?». Para eso había que entrar a los 38
 * sitios uno por uno. La maqueta que el jefe aprobó lo resolvió con una tabla
 * plana, un renglón por hallazgo y un filtro por módulo.
 *
 * ─── Por qué cambió otra vez (27-11-2026) ───────────────────────────────────
 *
 *   «sigue saturado de información y esto mi jefe no va a decir que hicimos
 *    nada»
 *
 * La tabla plana resolvió el «quién tiene Elementor vulnerable» y creó el
 * problema de al lado: la columna del medio llevaba cuatro párrafos —qué pasa,
 * qué permite, quién puede aprovecharlo, qué hay que hacer— y veinticinco
 * renglones de cuatro párrafos son cien párrafos en una pantalla de 13
 * pulgadas. Es el mismo pozo en el que cayó «Lo que tiene pendiente» de la
 * ficha de un sitio, y se sale igual: la línea cerrada dice solo aquello con lo
 * que se decide, y el porqué, que es lo largo, vive dentro.
 *
 * Tres cosas más, todas de `documentacion/16-estilo-de-las-pantallas.md`:
 *
 *  - **Las pastillas de gravedad sobraban.** Eran una fila de cuatro pastillas
 *    que filtran, y el desplegable de Gravedad ya hace eso con la cuenta
 *    dentro. Dos mandos para lo mismo, diez píxeles aparte (regla §3.2).
 *  - **Faltaba QUÉ HACER.** La ficha de un sitio lo dice desde el 27 por la
 *    mañana con `SWPK_QueHacer` y sus ocho situaciones; la pantalla de toda la
 *    cartera seguía diciendo «hay que actualizarlo a la 3.2» incluso de los
 *    módulos que nadie puede actualizar. Ahora las dos pantallas dicen lo
 *    mismo y se ven igual (regla §8).
 *  - **Las cifras de arriba se pueden pulsar.** Cuatro tarjetas que contestan
 *    «¿y qué hago yo con esto?» antes de leer un solo renglón, y cada una
 *    lleva a su lista ya filtrada (regla §8).
 *
 * ─── Lo que cuesta ──────────────────────────────────────────────────────────
 *
 * Armar la lista obliga a abrir los hallazgos de cada sitio, que es lo que ya
 * hacía la versión anterior para pintar sus cifras — el costo es el mismo. Se
 * guarda cinco minutos en caché porque cada cambio de filtro recarga la
 * pantalla, y pagar el recorrido entero por mover un desplegable no tiene
 * sentido. El «qué hacer» se calcula al armar la caché y no al pintar: así se
 * puede filtrar y contar por él sin recorrer los hallazgos dos veces.
 */
defined( 'ABSPATH' ) || exit;

$todos    = SWPK_Perfiles::sitios_visibles();
$tag      = SWPK_Vista::tag_pedido();
$nivel    = SWPK_Vista::nivel_pedido();
$busqueda = SWPK_Vista::busqueda_pedida();

$por_tag = SWPK_Vista::conteo_por_tag( $todos );

/**
 * Todos los hallazgos de los sitios que esta persona puede ver, aplanados.
 *
 * La caché es por persona porque una asesora ve su cartera y no la de todos,
 * y la corrida diaria la olvida al terminar para que nadie vea datos viejos
 * sin que se le avise. El sufijo del nombre sube cuando cambia la FORMA de lo
 * guardado: una caché de la versión anterior volvería sin las claves nuevas y
 * la pantalla se caería con un aviso de índice que no existe.
 */
$clave_cache = 'swpk_hallazgos3_' . get_current_user_id();
$todas       = get_transient( $clave_cache );

if ( ! is_array( $todas ) ) {
	$todas = [];

	foreach ( $todos as $s ) {
		foreach ( SWPK_Riesgo::hallazgos( (string) $s->sitio_id ) as $h ) {
			$v = $h['vulnerabilidades'][0] ?? null;

			// ── Lo que faltaba la primera vez: QUÉ es la falla ──────────
			//
			// Esta pantalla decía cuántas hay y de qué módulo, y nunca qué
			// hace la falla. «No estoy entendiendo qué vulnerabilidad es.»
			// Tenía razón, y lo peor es que el dato ya estaba guardado: el
			// feed de Wordfence trae el título, la descripción y el CVE, y
			// SWPK_Textos ya sabe traducirlos a una frase que se entiende.
			$quien = $v
				? SWPK_Textos::quien_puede(
					(string) ( $v->cvss_vector ?? '' ),
					(string) ( $v->descripcion ?? '' )
				)
				: [ 'sin_cuenta' => false, 'frase' => '' ];

			// ── Y lo que faltaba la segunda: QUÉ HACER ──────────────────
			//
			// Se calcula aquí, dentro de la caché, y no al pintar cada fila.
			// Dos razones: los filtros y las tarjetas de arriba cuentan por
			// él —y contar obliga a tenerlo de las 2.700 filas, no de las 25
			// que se ven—, y `SWPK_QueHacer::para()` necesita el objeto
			// entero de la vulnerabilidad, que a propósito no se guarda.
			$hacer = SWPK_QueHacer::para( $h );

			$todas[] = [
				'sitio_id'     => (string) $s->sitio_id,
				'nombre'       => (string) $s->nombre_visible,
				'dominio'      => (string) $s->dominio,
				'tag'          => (string) $s->tag,
				'revisado'     => (string) ( $s->inventario_en ?? '' ),
				'nivel'        => (string) $h['nivel'],
				'motivo'       => (string) $h['motivo'],
				'modulo'       => SWPK_Textos::limpiar_titulo( (string) $h['titulo'] ),
				'version'      => (string) $h['version'],
				'disponible'   => (string) $h['version_disponible'],
				'sin_cuenta'   => (bool) $quien['sin_cuenta'],
				'quien'        => (string) ( $quien['frase'] ?? '' ),
				'que_permite'  => $v
					? SWPK_Textos::explicar( (string) ( $v->titulo ?? '' ), (string) ( $v->descripcion ?? '' ) )
					: '',
				'titulo_vuln'  => $v ? (string) ( $v->titulo ?? '' ) : '',
				'cve'          => $v ? (string) ( $v->cve ?? '' ) : '',
				'cuantas'      => count( $h['vulnerabilidades'] ),
				// El slug y el tipo, para la píldora de parche y para los
				// enlaces al historial del módulo. Del objeto de la
				// vulnerabilidad no se guarda nada más: meterle la
				// descripción completa de 2.700 hallazgos engorda el
				// transient sin que nadie la lea desde aquí.
				'slug'         => (string) $h['slug'],
				'tipo'         => (string) ( $h['tipo'] ?? 'plugin' ),
				'parcheada'    => $v ? (bool) (int) ( $v->parcheada ?? 0 ) : null,
				'titular'      => (string) $hacer['titular'],
				'detalle'      => (string) $hacer['detalle'],
				'de_quien'     => (string) $hacer['quien'],
				'caso'         => (string) $hacer['caso'],
				'urgente'      => (bool) $hacer['urgente'],
			];
		}
	}

	set_transient( $clave_cache, $todas, 5 * MINUTE_IN_SECONDS );
}

// Lo del grupo elegido. De aquí salen las cuentas de las tarjetas y las
// opciones de los desplegables: si salieran de lo ya filtrado, elegir un
// módulo dejaría ese módulo como única opción y no habría forma de cambiar de
// idea sin quitar el filtro.
$del_grupo = array_values( array_filter(
	$todas,
	static fn( $f ) => '' === $tag || $f['tag'] === $tag
) );

$por_nivel = [];
$por_caso  = [];
$modulos   = [];

// Las dos cuentas de la cabecera: una falla conocida no es lo mismo que una
// versión vieja, y mezcladas en un solo «2.755 hallazgos» el número asusta sin
// decir nada. La cifra que importa es la primera.
$n_fallas = 0;
$n_act    = 0;

// Quién se mueve, para las otras dos tarjetas.
$por_quien = [];

// Las opciones de sitio salen de los hallazgos que hay, no del inventario:
// ofrecer un sitio sin nada pendiente es ofrecer una lista vacía. Con el
// número de hallazgos al lado, que es lo que se viene a saber.
$cuenta_sitio = [];
$nombre_sitio = [];

foreach ( $del_grupo as $f ) {
	$por_nivel[ $f['nivel'] ] = ( $por_nivel[ $f['nivel'] ] ?? 0 ) + 1;
	$por_caso[ $f['caso'] ]   = ( $por_caso[ $f['caso'] ] ?? 0 ) + 1;
	$por_quien[ $f['de_quien'] ] = ( $por_quien[ $f['de_quien'] ] ?? 0 ) + 1;
	$modulos[ $f['modulo'] ]  = $f['modulo'];

	if ( 'actualizacion_pendiente' === $f['motivo'] ) {
		++$n_act;
	} else {
		++$n_fallas;
	}

	$cuenta_sitio[ $f['sitio_id'] ] = ( $cuenta_sitio[ $f['sitio_id'] ] ?? 0 ) + 1;
	$nombre_sitio[ $f['sitio_id'] ] = (string) $f['nombre'];
}

ksort( $modulos, SORT_NATURAL | SORT_FLAG_CASE );
$modulo = SWPK_Vista::opcion_pedida( 'modulo', $modulos );

$sitios_opciones = [];
foreach ( $nombre_sitio as $id_s => $nom_s ) {
	$sitios_opciones[ $id_s ] = sprintf( '%s (%d)', $nom_s, (int) $cuenta_sitio[ $id_s ] );
}
asort( $sitios_opciones, SORT_NATURAL | SORT_FLAG_CASE );

$sitio_filtro = SWPK_Vista::opcion_pedida( 'sitio_f', $sitios_opciones );

// ── Qué hay que hacer con ella ──────────────────────────────────────────────
//
// Es la pregunta con la que se abre esta pantalla un lunes: «¿qué puedo
// arreglar hoy?». Antes el desplegable ofrecía tres respuestas sacadas de si
// Wordfence había publicado parche —hay, no hay, cerrado—, y esas tres no son
// el trabajo: un módulo con parche publicado que el sitio no ve se arregla a
// mano, y uno que actualiza el hosting no se arregla, se espera. Las siete
// respuestas de SWPK_QueHacer sí son el trabajo, y son las mismas que lee en
// la ficha de un sitio.
$casos_todos    = SWPK_QueHacer::casos();
$casos_opciones = [];
foreach ( $casos_todos as $clave_caso => $rotulo ) {
	if ( ! empty( $por_caso[ $clave_caso ] ) ) {
		$casos_opciones[ $clave_caso ] = sprintf( '%s (%d)', $rotulo, (int) $por_caso[ $clave_caso ] );
	}
}
$caso_filtro = SWPK_Vista::opcion_pedida( 'caso', $casos_opciones );

// ── Falla conocida o solo versión vieja ─────────────────────────────────────
//
// La ficha de un sitio separa las dos cosas desde el principio —son sus dos
// primeras tarjetas— y esta pantalla, que las mezclaba en una lista de 2.755,
// no tenía forma de separarlas. Es la primera cosa que hay que poder decir:
// «enseñame solo las fallas».
$pendiente_filtro = SWPK_Vista::opcion_pedida(
	'pendiente',
	[ 'vuln' => 'Fallas conocidas', 'act' => 'Solo actualizaciones' ]
);

$filas = array_values( array_filter(
	$del_grupo,
	static function ( $f ) use ( $nivel, $modulo, $busqueda, $sitio_filtro, $caso_filtro, $pendiente_filtro ) {
		if ( '' !== $sitio_filtro && $f['sitio_id'] !== $sitio_filtro ) {
			return false;
		}
		if ( '' !== $caso_filtro && $f['caso'] !== $caso_filtro ) {
			return false;
		}
		if ( 'vuln' === $pendiente_filtro && 'actualizacion_pendiente' === $f['motivo'] ) {
			return false;
		}
		if ( 'act' === $pendiente_filtro && 'actualizacion_pendiente' !== $f['motivo'] ) {
			return false;
		}
		if ( '' !== $nivel && $f['nivel'] !== $nivel ) {
			return false;
		}
		if ( '' !== $modulo && $f['modulo'] !== $modulo ) {
			return false;
		}
		if ( '' !== $busqueda ) {
			$aguja = strtolower( remove_accents( $busqueda ) );
			$pajar = strtolower( remove_accents( $f['nombre'] . ' ' . $f['dominio'] . ' ' . $f['modulo'] ) );
			if ( ! str_contains( $pajar, $aguja ) ) {
				return false;
			}
		}
		return true;
	}
) );

// Lo más grave primero y, a igualdad de gravedad, lo que se aprovecha sin
// tener cuenta en el sitio: eso es lo que buscan los ataques automáticos.
usort(
	$filas,
	static fn( $a, $b ) =>
		[ SWPK_Riesgo::peso( $b['nivel'] ), (int) $b['sin_cuenta'], $a['nombre'] ]
		<=> [ SWPK_Riesgo::peso( $a['nivel'] ), (int) $a['sin_cuenta'], $b['nombre'] ]
);

$sitios_tocados = count( array_unique( array_column( $filas, 'sitio_id' ) ) );

[ $en_pantalla, $pagina, $paginas ] = SWPK_Vista::paginar( $filas, SWPK_Vista::pagina_pedida(), 25 );

/** La dirección de esta misma pantalla con un filtro puesto y los demás en blanco. */
$url_filtro = static fn( array $args ) => add_query_arg(
	array_filter( [ 'page' => 'panel-kinsta-vulnerabilidades', 'tag' => $tag ] + $args ),
	admin_url( 'admin.php' )
);
?>
<div class="wrap">
	<?php
	// La cabecera va sin pastillas. Las cuatro de gravedad que había aquí eran
	// un filtro más —el mismo que el desplegable «Gravedad», que además lleva
	// la cuenta dentro—, y la regla §3.2 del estilo de las pantallas dice lo
	// que pasa con eso: «si una frase dice lo mismo que otra que está diez
	// píxeles más abajo, sobra una».
	SWPK_Vista::cabecera(
		'Vulnerabilidades y pendientes',
		esc_html( sprintf(
			'%s en %s.',
			SWPK_Vista::plural( count( $filas ), 'hallazgo', 'hallazgos' ),
			SWPK_Vista::plural( $sitios_tocados, 'sitio', 'sitios' )
		) )
	);
	?>
	<?php SWPK_Admin::mostrar_mensaje(); ?>

	<?php
	// El aviso de fecha va ANTES de las cifras, no después: si el inventario
	// es viejo, esos cuatro números son viejos, y hay que saberlo antes de
	// leerlos. Sin la parte de la lista de Wordfence: esta pantalla la dice
	// abajo, con sus propias palabras.
	SWPK_Frescura::aviso( false );
	?>

	<?php
	// ── Las cuatro cifras con las que se decide el día ──────────────────────
	//
	// Y cada una lleva a su lista. Un número que no lleva a los renglones que
	// lo forman es un número que hay que creer: «quiero ver las 19 críticas,
	// quiero ver las 14 pendientes, no puedo, ¿dónde puedo ver eso?».
	//
	// Las dos primeras separan lo que no es lo mismo: una falla conocida de
	// una versión vieja. Las dos siguientes contestan de quién es el trabajo,
	// que es la pregunta de después.
	$n_nosotros = (int) ( $por_quien[ SWPK_QueHacer::NOSOTROS ] ?? 0 );
	$n_esperar  = (int) ( $por_quien[ SWPK_QueHacer::ESPERAR ] ?? 0 );
	$n_hosting  = (int) ( $por_quien[ SWPK_QueHacer::HOSTING ] ?? 0 );
	?>
	<div class="swpk-tarjetas">
		<a class="swpk-tarjeta swpk-tarjeta-enlace" href="<?php echo esc_url( $url_filtro( [ 'pendiente' => 'vuln' ] ) ); ?>">
			<div class="swpk-n" style="color:<?php echo $n_fallas ? '#9e0b10' : '#0b5ed7'; ?>"><?php echo (int) $n_fallas; ?></div>
			<div class="swpk-r">Con falla conocida</div>
		</a>
		<a class="swpk-tarjeta swpk-tarjeta-enlace" href="<?php echo esc_url( $url_filtro( [ 'pendiente' => 'act' ] ) ); ?>">
			<div class="swpk-n"><?php echo (int) $n_act; ?></div>
			<div class="swpk-r">Solo actualización pendiente</div>
		</a>
		<a class="swpk-tarjeta swpk-tarjeta-enlace" href="<?php echo esc_url( $url_filtro( [ 'caso' => SWPK_QueHacer::ACTUALIZAR ] ) ); ?>">
			<div class="swpk-n" style="color:#1c6dc7"><?php echo (int) ( $por_caso[ SWPK_QueHacer::ACTUALIZAR ] ?? 0 ); ?></div>
			<div class="swpk-r">Se arreglan actualizando</div>
		</a>
		<?php
		// La cuarta tarjeta cambia según lo que haya. Si hay módulos esperando
		// arreglo, eso es lo que hay que saber; si no hay ninguno, enseñar un
		// cero no aporta y en su lugar va lo que lleva el hosting.
		if ( $n_esperar ) :
		?>
		<a class="swpk-tarjeta swpk-tarjeta-enlace" href="<?php echo esc_url( $url_filtro( [ 'caso' => SWPK_QueHacer::AGUANTAR ] ) ); ?>">
			<div class="swpk-n" style="color:#b4690e"><?php echo (int) $n_esperar; ?></div>
			<div class="swpk-r">Sin arreglo todavía</div>
		</a>
		<?php elseif ( $n_hosting ) : ?>
		<a class="swpk-tarjeta swpk-tarjeta-enlace" href="<?php echo esc_url( $url_filtro( [ 'caso' => SWPK_QueHacer::DEL_HOST ] ) ); ?>">
			<div class="swpk-n"><?php echo (int) $n_hosting; ?></div>
			<div class="swpk-r">Los actualiza el hosting</div>
		</a>
		<?php endif; ?>
	</div>

	<?php
	// La otra mitad de la aclaración que pidió al no distinguir esta pantalla
	// de Seguridad. La burbuja es la misma en las dos, a propósito: es la misma
	// explicación, y quien la lea en cualquiera de las dos ya no vuelve a
	// preguntarlo.
	?>
	<p class="swpk-comoasi-linea">
		Aquí están <strong>las fallas conocidas</strong> de los plugins y temas instalados, y lo que
		está pendiente de actualizar. Lo que todo sitio debería cumplir, tenga lo que tenga
		instalado, está en
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=panel-kinsta-seguridad' ) ); ?>">Seguridad</a>.
		<?php echo wp_kses_post( SWPK_Vista::ayuda(
			'No son lo mismo y no se arreglan igual. Una norma la incumple el sitio por cómo está '
			. 'configurado —no exige HTTPS, publica sus nombres de usuario— y se arregla cambiando '
			. 'esa configuración. Una vulnerabilidad la trae un plugin o un tema en una versión con '
			. 'una falla ya descubierta, y se arregla actualizándolo. Un sitio puede cumplir las '
			. 'normas y tener diez plugins con falla, y al revés.'
		) ); ?>
	</p>

	<?php
	// La frase de la lista de Wordfence, antes de nada.
	//
	// Esta pantalla vive de esa lista, así que lo primero que hay que saber es
	// si está al día — y sobre todo, distinguir «Wordfence no ha publicado
	// nada» de «no hemos comprobado». El sello de abajo da las fechas; esto da
	// la frase, que es lo que se lee de verdad.
	$wf = SWPK_Wordfence::como_va();
	?>
	<?php
	ob_start();
	SWPK_Admin::boton_exportar( 'vulnerabilidades' );
	$boton_excel = ob_get_clean();
	?>

	<?php
	// El título de la caja lleva la burbuja de dónde sale la gravedad. Antes
	// era un párrafo al pie de la pantalla, a cuatro pantallazos de la columna
	// que explicaba: nadie lee una aclaración que está lejos de lo aclarado.
	SWPK_Vista::caja_abrir(
		'Todo lo que hay que parchar ' . SWPK_Vista::ayuda(
			'La gravedad no la inventa el panel: es la que publica Wordfence Intelligence para cada '
			. 'vulnerabilidad. Las que solo dicen «Bajo» sin falla conocida son actualizaciones '
			. 'pendientes, y ahí el nivel lo pone el panel para que no se lean como una urgencia.'
		),
		'<span style="margin-right:12px">Lo más grave primero</span>' . $boton_excel,
		'',
		true
	);

	$opciones_sev = [];
	foreach ( [ SWPK_Riesgo::CRITICO, SWPK_Riesgo::ALTO, SWPK_Riesgo::MEDIO, SWPK_Riesgo::BAJO ] as $sev ) {
		if ( ! empty( $por_nivel[ $sev ] ) ) {
			$opciones_sev[ $sev ] = sprintf( '%s (%d)', SWPK_Riesgo::nombres()[ $sev ], (int) $por_nivel[ $sev ] );
		}
	}

	SWPK_Vista::barra_de_filtros(
		'panel-kinsta-vulnerabilidades',
		[
			'pendiente' => [
				'etiqueta' => 'Qué hay pendiente',
				'opciones' => [
					'vuln' => sprintf( 'Fallas conocidas (%d)', (int) $n_fallas ),
					'act'  => sprintf( 'Solo actualizaciones (%d)', (int) $n_act ),
				],
				'actual'   => $pendiente_filtro,
				'todas'    => 'Fallas y actualizaciones',
			],
			'caso' => [
				'etiqueta' => 'Qué hacer',
				'opciones' => $casos_opciones,
				'actual'   => $caso_filtro,
				'todas'    => 'Cualquier arreglo',
			],
			'nivel' => [
				'etiqueta' => 'Gravedad',
				'opciones' => $opciones_sev,
				'actual'   => $nivel,
				'todas'    => 'Toda gravedad',
			],
			'tag' => [
				'etiqueta' => 'Grupo',
				'opciones' => array_intersect_key( SWPK_Etiquetas::nombres(), array_filter( $por_tag ) ),
				'actual'   => $tag,
				'todas'    => 'Todos los grupos',
			],
			'sitio_f' => [
				'etiqueta' => 'Sitio',
				'opciones' => $sitios_opciones,
				'actual'   => $sitio_filtro,
				'todas'    => 'Todos los sitios',
			],
			'modulo' => [
				'etiqueta' => 'Módulo',
				'opciones' => $modulos,
				'actual'   => $modulo,
				'todas'    => 'Todos los módulos',
			],
		],
		[],
		$busqueda,
		'sitio o módulo…'
	);
	?>

	<?php SWPK_Vista::paginador( $pagina, $paginas, count( $filas ), 'hallazgos', 'hallazgo' ); ?>

	<?php if ( ! $en_pantalla ) : ?>
		<div class="notice notice-success inline"><p>
			Nada pendiente con ese filtro.
		</p></div>
	<?php else : ?>
		<?php
		// ── Por qué esto ya no es una tabla ─────────────────────────────────
		//
		// Era una tabla de cinco columnas y la del medio llevaba cuatro
		// párrafos: qué pasa, qué permite la falla, quién puede aprovecharla y
		// qué hay que hacer. Con veinticinco renglones por página eso son cien
		// párrafos compitiendo, y la pantalla se lee como un muro. Es
		// literalmente el mismo problema que tuvo «Lo que tiene pendiente» de
		// la ficha de un sitio —«se ve más desordenado y se acumula toda la
		// información»— y se arregla igual, con las mismas clases: la línea
		// cerrada dice las cuatro cosas con las que se decide —qué sitio, qué
		// módulo, cómo de grave y qué hay que hacer— y todo lo demás vive
		// dentro, para quien lo abra.
		//
		// Ninguna fila viene abierta. Ni la primera «para ayudar» (regla §3.1).
		?>
		<div class="swpk-normas swpk-pendientes swpk-vulns">
		<?php foreach ( $en_pantalla as $f ) : ?>
			<?php
			$hay_version = '' !== $f['disponible'] && $f['disponible'] !== $f['version'];
			$url_sitio   = SWPK_Vista::url_del_sitio( $f['sitio_id'], 'panel-kinsta-vulnerabilidades' );

			// Lo que va en la burbujita de la línea cerrada: el nombre técnico
			// con el que Wordfence la publicó, para poder buscarla, y su CVE.
			$detalle_vuln = '';
			if ( '' !== (string) $f['titulo_vuln'] ) {
				$detalle_vuln = 'Wordfence la publicó como: ' . $f['titulo_vuln'] . '.';
			}
			if ( '' !== (string) $f['cve'] ) {
				$detalle_vuln .= ( '' !== $detalle_vuln ? ' ' : '' ) . 'Identificador ' . $f['cve'] . '.';
			}
			?>
			<details class="swpk-norma swpk-pendiente swpk-vuln">
				<summary>
					<span class="swpk-norma-nombre">
						<strong><?php echo esc_html( $f['nombre'] ); ?></strong>
						<span class="swpk-norma-aclara">
							<span class="swpk-vuln-modulo"><?php echo esc_html( $f['modulo'] ); ?></span>
							· <span class="swpk-mono"><?php echo esc_html( $f['version'] ); ?></span><?php
							if ( $hay_version ) : ?> → <span class="swpk-mono" style="color:#0b5ed7"><?php
								echo esc_html( $f['disponible'] );
							?></span><?php endif; ?>
							<?php if ( 1 < (int) $f['cuantas'] ) : ?>
								· <?php echo (int) $f['cuantas']; ?> fallas
							<?php endif; ?>
						</span>
					</span>
					<span class="swpk-norma-estado">
						<?php echo wp_kses_post( SWPK_Admin::nivel_badge( (string) $f['nivel'] ) ); ?>
						<?php if ( 'actualizacion_pendiente' === $f['motivo'] ) : ?>
							<span class="swpk-pendiente-pildora"><span class="swpk-mudo">solo actualización</span></span>
						<?php else : ?>
							<?php
							$pildora = SWPK_Admin::parche_badge(
								$f['parcheada'] ?? null,
								(string) ( $f['slug'] ?? '' ),
								(string) ( $f['tipo'] ?? 'plugin' )
							);
							if ( '' !== $pildora ) :
								?>
								<span class="swpk-pendiente-pildora"><?php echo wp_kses_post( $pildora ); ?></span>
							<?php endif; ?>
						<?php endif; ?>
					</span>
					<span class="swpk-pendiente-hacer swpk-hacer-<?php echo esc_attr( $f['de_quien'] ); ?>">
						<?php echo esc_html( $f['titular'] ); ?>
					</span>
				</summary>

				<div class="swpk-norma-dentro">
					<ol class="swpk-norma-pasos">
						<li>
							<h4>Por qué importa</h4>
							<?php if ( 'vulnerable_sin_detalle' === $f['motivo'] ) : ?>
								<p style="margin:0">
									<span style="color:#9e0b10"><?php echo esc_html( SWPK_Admin::proveedor() ); ?>
									marca esta versión como vulnerable</span>, y el feed de Wordfence todavía no
									publicó el detalle de qué falla es. El dato del hosting vale igual: lo que
									falta es el «por qué».
								</p>
							<?php elseif ( 'actualizacion_pendiente' === $f['motivo'] ) : ?>
								<p style="margin:0">
									No tiene ninguna falla conocida publicada. Es una actualización pendiente y
									nada más: importa porque las versiones viejas acumulan fallas que se publican
									después.
								</p>
							<?php else : ?>
								<?php if ( $f['sin_cuenta'] ) : ?>
									<p style="margin:0 0 6px">
										<span class="swpk-rotulin">Quién puede aprovecharlo</span>
										<span class="swpk-rojo">Cualquiera desde internet, sin necesidad de tener
										una cuenta en el sitio.</span>
									</p>
								<?php elseif ( '' !== (string) $f['quien'] ) : ?>
									<p style="margin:0 0 6px">
										<span class="swpk-rotulin">Quién puede aprovecharlo</span>
										<?php echo esc_html( $f['quien'] ); ?>
									</p>
								<?php endif; ?>
								<?php if ( '' !== (string) $f['que_permite'] ) : ?>
									<p style="margin:0">
										<span class="swpk-rotulin">Qué permite hacer</span>
										<?php echo esc_html( $f['que_permite'] ); ?>
										<?php if ( '' !== (string) $f['cve'] ) : ?>
											<br><span class="swpk-mono swpk-mudo"><?php echo esc_html( (string) $f['cve'] ); ?></span>
										<?php endif; ?>
									</p>
								<?php endif; ?>
								<?php if ( '' !== (string) $f['titulo_vuln'] ) : ?>
									<p class="swpk-mudo" style="margin:6px 0 0;font-size:12px">
										Wordfence la publicó como «<?php echo esc_html( $f['titulo_vuln'] ); ?>».
									</p>
								<?php endif; ?>
							<?php endif; ?>
						</li>

						<li>
							<h4>Qué hacer</h4>
							<p><strong><?php echo esc_html( $f['titular'] ); ?>.</strong>
								<?php echo esc_html( $f['detalle'] ); ?></p>
							<p class="swpk-norma-pie">
								<a class="button button-small" href="<?php echo esc_url( add_query_arg( 'solo', 'vuln', $url_sitio ) ); ?>">Ver el sitio</a>
								<a class="button button-small" href="<?php echo esc_url( add_query_arg(
									[ 'page' => 'panel-kinsta-movimientos', 'sitio' => $f['sitio_id'], 'q' => $f['slug'] ],
									admin_url( 'admin.php' )
								) ); ?>">Ver su historial</a>
								<span class="swpk-mudo">
									<?php echo SWPK_Admin::grupo_badge( $f['tag'] ); ?>
									<?php
									// La fecha de revisión era una columna de la tabla y aquí
									// pierde el sitio: nadie decide nada con ella, pero hace
									// falta para saber si el renglón es de hoy o de hace una
									// semana. Va donde se mira cuando se duda.
									echo esc_html(
										'' !== $f['revisado']
											? ' Revisado el ' . SWPK_Frescura::fmt( 'j M, H:i', $f['revisado'] )
											: ' Nunca revisado'
									);
									?>
								</span>
							</p>
						</li>
					</ol>
				</div>
			</details>
		<?php endforeach; ?>
		</div>

		<?php SWPK_Vista::paginador( $pagina, $paginas, count( $filas ), 'hallazgos', 'hallazgo' ); ?>
	<?php endif; ?>
	<?php SWPK_Vista::caja_cerrar(); ?>

	<?php
	// El sello, la tira de perfil y los botones van AL PIE, como en la maqueta:
	// entre la cabecera y la primera tarjeta ella no pone nada. Arriba, el
	// sello solo —que son cuatro renglones de fechas— empujaba la tabla fuera
	// de la pantalla.
	?>
	<p class="swpk-lista <?php echo $wf['al_dia'] ? '' : 'swpk-lista-mal'; ?>">
		<?php echo esc_html( $wf['frase'] ); ?>
	</p>

	<?php SWPK_Admin::aviso_demo(); ?>

	<p class="swpk-atribucion"><?php echo wp_kses_post( SWPK_Wordfence::atribucion() ); ?></p>
</div>
