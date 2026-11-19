<?php
/**
 * El panel: menú, pantallas y acciones.
 *
 * Por ahora vive dentro del escritorio de WordPress. El jefe lo pidió "a través
 * de un plugin, porque promos va a ser para otras cosas" (08:50), y pidió
 * perfiles distintos para ventas y soporte (08:17). Los perfiles se resuelven
 * con capacidades propias, no con los roles de WordPress, para que una asesora
 * no necesite ser administradora del sitio para ver su panel.
 */

defined( 'ABSPATH' ) || exit;

class SWPK_Admin {

	public const SLUG = 'panel-kinsta';

	public static function init(): void {
		add_action( 'admin_menu', [ __CLASS__, 'menu' ] );
		add_action( 'admin_post_swpk_correr', [ __CLASS__, 'accion_correr' ] );
		add_action( 'admin_post_swpk_analizar_sitio', [ __CLASS__, 'accion_analizar_sitio' ] );
		add_action( 'admin_post_swpk_guardar_ajustes', [ __CLASS__, 'accion_guardar_ajustes' ] );
		add_action( 'admin_post_swpk_probar', [ __CLASS__, 'accion_probar' ] );
		add_action( 'admin_post_swpk_sondear', [ __CLASS__, 'accion_sondear' ] );
		add_action( 'admin_post_swpk_revisar', [ __CLASS__, 'accion_revisar' ] );
		add_action( 'admin_post_swpk_plataforma', [ __CLASS__, 'accion_plataforma' ] );
		add_action( 'admin_post_swpk_asignar', [ __CLASS__, 'accion_asignar' ] );
		add_action( 'admin_post_swpk_cifrar', [ __CLASS__, 'accion_cifrar' ] );
		add_action( 'admin_post_swpk_crear_acceso', [ __CLASS__, 'accion_crear_acceso' ] );
		add_action( 'admin_post_swpk_modo_envio', [ __CLASS__, 'accion_modo_envio' ] );
		add_action( 'admin_post_swpk_envios', [ __CLASS__, 'accion_envios' ] );
		add_action( 'admin_post_swpk_guardar_plantilla', [ __CLASS__, 'accion_guardar_plantilla' ] );
		add_action( 'admin_post_swpk_plantilla_fabrica', [ __CLASS__, 'accion_plantilla_fabrica' ] );
		add_action( 'admin_post_swpk_vista_previa', [ __CLASS__, 'accion_vista_previa' ] );
		add_action( 'admin_post_swpk_correo_semanal', [ __CLASS__, 'accion_correo_semanal' ] );
		add_action( 'admin_post_swpk_correo_diario', [ __CLASS__, 'accion_correo_diario' ] );
		add_action( 'admin_post_swpk_correo_mensual', [ __CLASS__, 'accion_correo_mensual' ] );
		add_action( 'admin_post_swpk_correo_resumen', [ __CLASS__, 'accion_correo_resumen' ] );
		add_action( 'admin_post_swpk_quitar_acceso', [ __CLASS__, 'accion_quitar_acceso' ] );
		add_action( 'admin_post_swpk_crear_llave', [ __CLASS__, 'accion_crear_llave' ] );
		add_action( 'admin_post_swpk_revocar_llave', [ __CLASS__, 'accion_revocar_llave' ] );
		add_action( 'admin_post_swpk_guardar_norma', [ __CLASS__, 'accion_guardar_norma' ] );
		add_action( 'admin_post_swpk_encender_norma', [ __CLASS__, 'accion_encender_norma' ] );
		add_action( 'admin_post_swpk_borrar_norma', [ __CLASS__, 'accion_borrar_norma' ] );
		add_action( 'admin_post_swpk_marcar_norma', [ __CLASS__, 'accion_marcar_norma' ] );
		add_action( 'admin_post_swpk_arreglar', [ __CLASS__, 'accion_arreglar' ] );
		add_action( 'admin_post_swpk_reseteo_masivo', [ __CLASS__, 'accion_reseteo_masivo' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'estilos' ] );
		add_filter( 'parent_file', [ __CLASS__, 'menu_abierto' ] );
		add_filter( 'submenu_file', [ __CLASS__, 'submenu_marcado' ] );
		add_filter( 'admin_title', [ __CLASS__, 'titulo_de_la_ficha' ], 10, 2 );
	}

	private static function puede_ver(): bool {
		return SWPK_Perfiles::puede( SWPK_Perfiles::VER );
	}

	private static function puede_administrar(): bool {
		return SWPK_Perfiles::puede( SWPK_Perfiles::ADMINISTRAR );
	}

	/**
	 * El menú se arma con los permisos de cada perfil.
	 *
	 * Lo que una asesora no debe ver, no aparece. Esconder la opción no es
	 * seguridad por sí sola —cada pantalla vuelve a comprobar el permiso al
	 * abrirse— pero un menú con cosas que no puede abrir es un menú confuso.
	 */
	public static function menu(): void {
		if ( ! self::puede_ver() ) {
			return;
		}

		$ver     = 'read';
		$tecnico = SWPK_Perfiles::puede( SWPK_Perfiles::VER_TECNICO ) ? 'read' : 'do_not_allow';
		$admin   = self::puede_administrar() ? 'read' : 'do_not_allow';

		add_menu_page(
			'Panel Kinsta',
			'Panel Kinsta',
			$ver,
			self::SLUG,
			[ __CLASS__, 'pantalla_resumen' ],
			'dashicons-shield-alt',
			3
		);

		add_submenu_page( self::SLUG, 'Resumen', 'Resumen', $ver, self::SLUG, [ __CLASS__, 'pantalla_resumen' ] );
		add_submenu_page( self::SLUG, 'Sitios', 'Sitios', $ver, self::SLUG . '-sitios', [ __CLASS__, 'pantalla_sitios' ] );
		add_submenu_page( self::SLUG, 'Vulnerabilidades', 'Vulnerabilidades', $ver, self::SLUG . '-vulnerabilidades', [ __CLASS__, 'pantalla_vulnerabilidades' ] );
		add_submenu_page( self::SLUG, 'Seguridad', 'Seguridad', $ver, self::SLUG . '-seguridad', [ __CLASS__, 'pantalla_seguridad' ] );
		add_submenu_page( self::SLUG, 'Caídas', 'Caídas', $tecnico, self::SLUG . '-caidas', [ __CLASS__, 'pantalla_caidas' ] );

		// ─── Por qué el menú tiene ocho entradas y no trece ──────────────
		//
		// Tenía trece, y el jefe lo dijo sin rodeos mirándolo: «panel es
		// simple, o sea no tener tanto», «necesito simpleza». Tres de esas
		// entradas eran la misma pregunta que otra, vista de otro lado, así
		// que se volvieron pestañas de quien ya la contestaba:
		//
		//   Accesos           → Historial, pestaña «En el panel»
		//   Bitácora          → Correos,   pestaña «Qué se mandó ya»
		//   Conectarse (API)  → Ajustes,   pestaña «Conectarse (API)»
		//
		// Y «Movimientos» pasó a llamarse «Historial». Ni «Movimientos» ni
		// «Registros» se entendían al leerlos en el menú; esta pantalla
		// contesta «¿qué pasó, y cuándo?», y eso es un historial.
		add_submenu_page( self::SLUG, 'Historial', 'Historial', $ver, self::SLUG . '-movimientos', [ __CLASS__, 'pantalla_movimientos' ] );
		add_submenu_page( self::SLUG, 'Correos', 'Correos', $admin, self::SLUG . '-correos', [ __CLASS__, 'pantalla_correos' ] );
		add_submenu_page( self::SLUG, 'Equipo', 'Equipo', $admin, self::SLUG . '-equipo', [ __CLASS__, 'pantalla_equipo' ] );
		add_submenu_page( self::SLUG, 'Ajustes', 'Ajustes', $admin, self::SLUG . '-ajustes', [ __CLASS__, 'pantalla_ajustes' ] );

		// ─── La ficha y el reporte: registradas, pero escondidas ──────────
		//
		// Estaban colgando de `null`, que es la forma habitual de registrar
		// una pantalla a la que solo se llega desde otra. El precio era que
		// WordPress no reconocía el menú como el actual y **lo cerraba**: al
		// entrar a un sitio desaparecían las nueve entradas del panel y había
		// que volver atrás para ir a otra parte. «Si entro en algún sitio se
		// me cierra todo el panel donde puedo ver todas las opciones.»
		//
		// Así que cuelgan del panel como las demás, y se esconden del menú
		// con una regla de estilo. El menú se queda abierto y marca de dónde
		// se vino.
		add_submenu_page( self::SLUG, 'Sitio', 'Sitio', $ver, self::SLUG . '-sitio', [ __CLASS__, 'pantalla_sitio' ] );
		add_submenu_page( self::SLUG, 'Reporte', 'Reporte', $ver, self::SLUG . '-reporte', [ __CLASS__, 'pantalla_reporte' ] );

		// Las tres direcciones viejas siguen existiendo, escondidas, y llevan
		// a su pestaña nueva. Quien tenga una guardada en favoritos, o un
		// enlace en un correo que ya salió, no se encuentra una pared.
		add_submenu_page( null, 'Accesos', 'Accesos', $admin, self::SLUG . '-accesos', [ __CLASS__, 'pantalla_accesos' ] );
		add_submenu_page( null, 'Bitácora', 'Bitácora', $ver, self::SLUG . '-bitacora', [ __CLASS__, 'pantalla_bitacora' ] );
		add_submenu_page( null, 'Conectarse (API)', 'Conectarse (API)', $admin, self::SLUG . '-api', [ __CLASS__, 'pantalla_api' ] );
	}

	/** El botón de bajar a Excel, con los filtros que estén puestos. */
	public static function boton_exportar( string $tipo ): void {
		$filtros = [];
		// 'modulo' entró con la tabla plana de Vulnerabilidades. Si no viajara,
		// el Excel traería los 294 hallazgos cuando en pantalla se ven 17.
		foreach ( [ 'tag', 'nivel', 'q', 'wp', 'php', 'modulo' ] as $k ) {
			if ( ! empty( $_GET[ $k ] ) ) {
				$filtros[ $k ] = sanitize_text_field( wp_unslash( (string) $_GET[ $k ] ) );
			}
		}

		printf(
			'<a class="button" href="%s" style="margin-left:6px">Bajar a Excel</a>',
			esc_url( SWPK_Exportar::url( $tipo, $filtros ) )
		);
	}

	/** La tira que dice con qué perfil se está viendo el panel. */
	public static function tira_de_perfil(): void {
		$perfil = SWPK_Perfiles::nombre_perfil();
		$cuantos = count( SWPK_Perfiles::sitios_visibles() );

		printf(
			'<p style="color:#50575e;margin:-4px 0 14px">Estás viendo el panel como <strong>%s</strong>%s</p>',
			esc_html( $perfil ),
			SWPK_Perfiles::puede( SWPK_Perfiles::VER_TODO )
				? ', con los ' . (int) $cuantos . ' sitios.'
				: sprintf( ', con %d sitio(s) asignado(s).', (int) $cuantos )
		);
	}

	/**
	 * La ficha de un sitio no está en el menú, así que WordPress cerraría el
	 * menú del panel al entrar en ella y la persona se quedaría sin saber dónde
	 * está. Esto lo deja abierto y marca de dónde viene.
	 */
	public static function menu_abierto( $padre ) {
		return in_array( self::pagina_actual(), [ self::SLUG . '-sitio', self::SLUG . '-reporte' ], true ) ? self::SLUG : $padre;
	}

	public static function submenu_marcado( $submenu ) {
		if ( self::SLUG . '-sitio' !== self::pagina_actual() ) {
			return $submenu;
		}

		$de = isset( $_GET['de'] ) ? sanitize_key( (string) $_GET['de'] ) : '';

		return in_array( $de, [ self::SLUG . '-sitios', self::SLUG . '-vulnerabilidades', self::SLUG . '-seguridad' ], true )
			? $de
			: self::SLUG . '-sitios';
	}

	/** Y que la pestaña del navegador diga de qué sitio se trata. */
	public static function titulo_de_la_ficha( $titulo, $admin_titulo ) {
		$pagina = self::pagina_actual();

		if ( ! in_array( $pagina, [ self::SLUG . '-sitio', self::SLUG . '-reporte' ], true ) ) {
			return $titulo;
		}

		$id    = isset( $_GET['sitio'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['sitio'] ) ) : '';
		$sitio = $id ? SWPK_Inventario::obtener( $id ) : null;
		$nombre = $sitio->nombre_visible ?? 'Sitio';

		// El título de la pestaña es el nombre que propone el navegador al
		// guardar el PDF, y es también lo que el navegador imprime en la
		// esquina de cada hoja. Por eso se escribe entero aquí y no se le pega
		// el título del menú, que dejaba «Reporte Tal Sitio Reporte» en la
		// cabecera de un documento que va a ver un cliente.
		return self::SLUG . '-reporte' === $pagina
			? sprintf( 'Reporte de mantenimiento — %s', $nombre )
			: sprintf( '%s — Panel Kinsta', $nombre );
	}

	/** El nombre del proveedor, para escribirlo en las pantallas. */
	public static function proveedor(): string {
		return swpk_fuente()->proveedor();
	}

	private static function pagina_actual(): string {
		return isset( $_GET['page'] ) ? sanitize_key( (string) $_GET['page'] ) : '';
	}

	/**
	 * Las hojas de estilo del panel.
	 *
	 * Antes esto imprimía doscientas líneas de CSS en cada carga con
	 * `admin_head`. Ahora son dos archivos encolados como manda WordPress, lo
	 * que además deja que el navegador los guarde en caché:
	 *
	 *   heredado.css  lo que ya había, movido tal cual
	 *   panel.css     el diseño nuevo, que va encima
	 *   filtros.js    que los desplegables de filtro se apliquen al elegir
	 *   ayuda.js      que la burbujita de ⓘ no salga cortada
	 *   previa.js     la ventana con la vista previa de un correo
	 *
	 * Solo se cargan en las pantallas del panel. Una hoja que reescribe tablas
	 * y botones no tiene por qué andar suelta por el escritorio de alguien.
	 */
	public static function estilos(): void {
		$pagina = isset( $_GET['page'] ) ? sanitize_key( (string) $_GET['page'] ) : '';

		if ( ! str_starts_with( $pagina, self::SLUG ) ) {
			return;
		}

		$base = SWPK_URL . 'assets/';

		wp_enqueue_style( 'swpk-heredado', $base . 'heredado.css', [], SWPK_VERSION );
		wp_enqueue_style( 'swpk-panel', $base . 'panel.css', [ 'swpk-heredado' ], SWPK_VERSION );
		wp_enqueue_script( 'swpk-filtros', $base . 'filtros.js', [], SWPK_VERSION, true );
		wp_enqueue_script( 'swpk-progreso', $base . 'progreso.js', [], SWPK_VERSION, true );
		wp_enqueue_script( 'swpk-ayuda', $base . 'ayuda.js', [], SWPK_VERSION, true );
		wp_enqueue_script( 'swpk-previa', $base . 'previa.js', [], SWPK_VERSION, true );
	}

	public static function aviso_demo(): void {
		if ( ! SWPK_Ajustes::en_modo_demo() ) {
			return;
		}
		?>
		<div class="swpk-aviso-demo">
			<strong>Datos de prueba.</strong> El panel está corriendo sin llave de Kinsta, con sitios y
			vulnerabilidades inventados, para poder desarrollarlo y enseñarlo sin accesos. Los
			identificadores de vulnerabilidad llevan <code>DEMO-</code> a propósito: no son reales.
			Cuando haya llave, se cambia el modo en <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::SLUG . '-ajustes' ) ); ?>">Ajustes</a>.
		</div>
		<?php
	}

	public static function barra_acciones(): void {
		$t = SWPK_Trabajo::estado();

		if ( $t && 'corriendo' === $t['estado'] ) {
			self::avance( SWPK_Trabajo::publico( $t ) );
			return;
		}

		// Una corrida que acaba de terminar deja su resultado a la vista una vez
		// y se olvida. Si no se olvidara, el mismo "listo" saldría mañana.
		if ( $t && in_array( $t['estado'], [ 'hecho', 'error' ], true ) ) {
			self::resultado( SWPK_Trabajo::publico( $t ) );
			SWPK_Trabajo::olvidar();
		}

		$url = wp_nonce_url( admin_url( 'admin-post.php?action=swpk_correr' ), 'swpk_correr' );
		$ult = SWPK_Frescura::corrida();
		?>
		<p>
			<a href="<?php echo esc_url( $url ); ?>" class="button button-primary">Correr ahora</a>
			<span style="margin-left:10px;color:#50575e">
				<?php
				echo '' !== $ult
					? 'Último dato bueno: ' . esc_html( SWPK_Frescura::fecha_y_edad( $ult ) )
					: 'Todavía no se ha corrido ninguna vez.';
				?>
			</span>
		</p>
		<?php
	}

	/**
	 * La barra mientras corre.
	 *
	 * El `data-nonce` es lo que la pantalla usa para ir pidiendo el siguiente
	 * tramo. Si el navegador no puede con ello —JavaScript apagado, pestaña
	 * cerrada— no se pierde nada: el trabajo está anotado y el reloj lo termina.
	 * Por eso el texto lo dice, en vez de dejar a nadie esperando.
	 *
	 * @param array<string,mixed> $p
	 */
	private static function avance( array $p ): void {
		?>
		<div class="swpk-corrida" data-nonce="<?php echo esc_attr( wp_create_nonce( 'swpk_avanzar' ) ); ?>">
			<div class="swpk-corrida-pista">
				<span style="width:<?php echo (int) $p['porcentaje']; ?>%"></span>
			</div>
			<p class="swpk-corrida-texto"><?php echo esc_html( (string) $p['texto'] ); ?></p>
			<p class="swpk-corrida-nota">
				Esto tarda unos minutos: son dos consultas al hosting por cada sitio.
				Se puede cerrar esta pantalla —la corrida sigue sola y termina igual.
			</p>
		</div>
		<?php
	}

	/**
	 * Lo que quedó de la corrida que acaba de terminar.
	 *
	 * @param array<string,mixed> $p
	 */
	private static function resultado( array $p ): void {
		$r       = (array) $p['resumen'];
		$errores = (array) ( $r['errores'] ?? [] );
		$notas   = (array) ( $r['notas'] ?? [] );

		if ( $errores ) {
			printf(
				'<div class="notice notice-error"><p><strong>La corrida se detuvo.</strong> %s</p></div>',
				esc_html( implode( ' · ', $errores ) )
			);

			return;
		}

		printf(
			'<div class="notice notice-success"><p><strong>Corrida terminada.</strong> %s</p>%s</div>',
			esc_html(
				sprintf(
					'%d sitios, %d filas de inventario, %d vulnerabilidades, %d eventos nuevos.',
					(int) ( $r['sitios'] ?? 0 ),
					(int) ( $r['filas_foto'] ?? 0 ),
					(int) ( $r['vulnerabilidades'] ?? 0 ),
					(int) ( $r['eventos'] ?? 0 )
				)
			),
			$notas ? '<p>' . esc_html( implode( ' · ', $notas ) ) . '</p>' : ''
		);
	}

	public static function nivel_badge( string $nivel ): string {
		$nombres = SWPK_Riesgo::nombres();

		// El color lo pone la clase, no un `style` en línea. Antes era el color
		// pleno de SWPK_Riesgo::colores() con el texto en blanco; la maqueta las
		// quiere en fondo suave, y eso no se puede decir desde aquí sin repetir
		// seis parejas de colores que ya viven en la hoja de estilos.
		return sprintf(
			'<span class="swpk-nivel swpk-nivel-%s">%s</span>',
			esc_attr( $nivel ),
			esc_html( $nombres[ $nivel ] ?? $nivel )
		);
	}

	/**
	 * El grupo de un sitio, como etiqueta y no como texto gris.
	 *
	 * El nombre a secas no dice nada. "No auto update", leído de pasada en una
	 * columna gris, no distingue un sitio nuestro de uno del cliente — y es
	 * justo la distinción que decide quién puede actualizarlo y quién tiene que
	 * pedir permiso antes. La etiqueta lo dice con color para quien pasa rápido,
	 * el `title` lo dice con palabras para quien se detiene, y la leyenda de
	 * arriba de la tabla lo dice para quien lo ve por primera vez.
	 */
	public static function grupo_badge( string $tag ): string {
		if ( '' === $tag ) {
			return '';
		}

		$motivos = [
			SWPK_Etiquetas::DESARROLLO => 'Sitio nuestro: lo administramos y lo actualizamos nosotros.',
			SWPK_Etiquetas::AUTO       => 'Sitio nuestro: se actualiza solo.',
			SWPK_Etiquetas::NO_UPDATE  => 'Sitio del cliente: no se le actualiza nada sin pedirlo.',
			SWPK_Etiquetas::SIN        => 'Sin etiqueta en el hosting: no sabemos en qué grupo va.',
		];

		return sprintf(
			'<span class="swpk-etq swpk-etq-%s" title="%s">%s</span>',
			esc_attr( $tag ),
			esc_attr( $motivos[ $tag ] ?? '' ),
			esc_html( SWPK_Etiquetas::nombres()[ $tag ] ?? $tag )
		);
	}

	// ── Pantallas ───────────────────────────────────────────────────────────

	public static function pantalla_resumen(): void {
		if ( ! self::puede_ver() ) {
			wp_die( 'Sin permiso.' );
		}
		require SWPK_RUTA . 'includes/admin/vistas/resumen.php';
	}

	public static function pantalla_sitios(): void {
		if ( ! self::puede_ver() ) {
			wp_die( 'Sin permiso.' );
		}
		require SWPK_RUTA . 'includes/admin/vistas/sitios.php';
	}

	public static function pantalla_vulnerabilidades(): void {
		if ( ! self::puede_ver() ) {
			wp_die( 'Sin permiso.' );
		}
		require SWPK_RUTA . 'includes/admin/vistas/vulnerabilidades.php';
	}

	public static function pantalla_caidas(): void {
		if ( ! SWPK_Perfiles::puede( SWPK_Perfiles::VER_TECNICO ) ) {
			wp_die( 'Sin permiso.' );
		}
		require SWPK_RUTA . 'includes/admin/vistas/caidas.php';
	}

	public static function pantalla_sitio(): void {
		if ( ! self::puede_ver() ) {
			wp_die( 'Sin permiso.' );
		}
		require SWPK_RUTA . 'includes/admin/vistas/sitio.php';
	}

	public static function pantalla_reporte(): void {
		if ( ! self::puede_ver() ) {
			wp_die( 'Sin permiso.' );
		}
		require SWPK_RUTA . 'includes/admin/vistas/reporte.php';
	}

	public static function pantalla_equipo(): void {
		if ( ! self::puede_administrar() ) {
			wp_die( 'Sin permiso.' );
		}
		require SWPK_RUTA . 'includes/admin/vistas/equipo.php';
	}

	/**
	 * Las tres direcciones viejas, que ahora solo llevan a su pestaña.
	 *
	 * No borran nada ni enseñan nada: redirigen. Un enlace guardado hace tres
	 * semanas tiene que seguir llegando a donde llegaba, aunque la pantalla se
	 * haya mudado dentro de otra.
	 */
	private static function mudado( string $pagina, string $seccion ): void {
		wp_safe_redirect(
			add_query_arg( [ 'page' => $pagina, 'seccion' => $seccion ], admin_url( 'admin.php' ) ),
			301
		);
		exit;
	}

	public static function pantalla_accesos(): void {
		if ( ! SWPK_Perfiles::puede( SWPK_Perfiles::VER_ACCESOS ) ) {
			wp_die( 'Sin permiso.' );
		}
		self::mudado( self::SLUG . '-movimientos', 'accesos' );
	}

	public static function pantalla_correos(): void {
		if ( ! self::puede_administrar() ) {
			wp_die( 'Sin permiso.' );
		}
		require SWPK_RUTA . 'includes/admin/vistas/correos.php';
	}

	public static function pantalla_movimientos(): void {
		if ( ! self::puede_ver() ) {
			wp_die( 'Sin permiso.' );
		}
		require SWPK_RUTA . 'includes/admin/vistas/movimientos.php';
	}

	public static function pantalla_bitacora(): void {
		if ( ! self::puede_ver() ) {
			wp_die( 'Sin permiso.' );
		}
		self::mudado( self::SLUG . '-correos', 'bitacora' );
	}

	public static function pantalla_seguridad(): void {
		if ( ! self::puede_ver() ) {
			wp_die( 'Sin permiso.' );
		}
		require SWPK_RUTA . 'includes/admin/vistas/seguridad.php';
	}

	public static function pantalla_ajustes(): void {
		if ( ! self::puede_administrar() ) {
			wp_die( 'Sin permiso.' );
		}
		require SWPK_RUTA . 'includes/admin/vistas/ajustes.php';
	}

	public static function pantalla_api(): void {
		if ( ! self::puede_administrar() ) {
			wp_die( 'Sin permiso.' );
		}
		self::mudado( self::SLUG . '-ajustes', 'api' );
	}

	// ── Acciones ────────────────────────────────────────────────────────────

	/**
	 * «Correr ahora».
	 *
	 * No corre nada aquí: apunta que hay trabajo y devuelve la pantalla. Lo que
	 * sigue lo lleva SWPK_Trabajo, a tramos, y la pantalla lo enseña con una
	 * barra. Antes esta acción hacía la corrida entera dentro de la petición, y
	 * contra la cuenta real eso termina siempre igual: el servidor corta a los
	 * pocos minutos y el usuario ve un 504.
	 */
	public static function accion_correr(): void {
		if ( ! self::puede_administrar() ) {
			wp_die( 'Sin permiso.' );
		}
		check_admin_referer( 'swpk_correr' );

		SWPK_Trabajo::empezar();

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG ) );
		exit;
	}

	/**
	 * "Analizar este sitio ahora", el botón que pidió el jefe.
	 *
	 * Vuelve al mismo sitio desde donde se pulsó, porque se usa desde la lista
	 * y desde la ficha: mandar siempre al Resumen obligaría a volver a buscar
	 * el sitio que se acaba de revisar.
	 */
	public static function accion_analizar_sitio(): void {
		if ( ! self::puede_administrar() ) {
			wp_die( 'Sin permiso.' );
		}

		$sitio_id = isset( $_GET['sitio'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['sitio'] ) ) : '';
		check_admin_referer( 'swpk_analizar_' . $sitio_id );

		$r = SWPK_Sincronizador::analizar_sitio( $sitio_id );

		if ( is_wp_error( $r ) ) {
			$mensaje = 'error:' . rawurlencode( $r->get_error_message() );
		} elseif ( $r['sin_datos'] ) {
			// No se dice "no tiene nada": se dice que no contestó. La
			// diferencia es la razón de ser de medio panel.
			$mensaje = 'error:' . rawurlencode(
				sprintf(
					'%s no devolvió la lista de módulos de este sitio, así que no hay nada nuevo que guardar. Lo que se ve sigue siendo el dato de la última corrida — no es que el sitio esté limpio.',
					self::proveedor()
				)
			);
		} else {
			$mensaje = 'ok:' . rawurlencode(
				sprintf(
					'Revisado ahora mismo: %d módulos%s%s.',
					$r['modulos'],
					$r['eventos'] ? sprintf( ', %d cambio(s) desde la última vez', $r['eventos'] ) : ', sin cambios desde la última vez',
					'abajo' === $r['sondeo'] ? ' — y el sitio NO está respondiendo' : ''
				)
			);
		}

		$volver = isset( $_GET['volver'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['volver'] ) ) : '';
		$destino = '' !== $volver
			? add_query_arg( 'swpk_msg', $mensaje, admin_url( $volver ) )
			: admin_url( 'admin.php?page=' . self::SLUG . '-sitio&sitio=' . rawurlencode( $sitio_id ) . '&swpk_msg=' . $mensaje );

		wp_safe_redirect( $destino );
		exit;
	}

	/** La dirección del botón de analizar, para la ficha y para la lista. */
	public static function url_analizar( string $sitio_id, string $volver = '' ): string {
		$args = [ 'action' => 'swpk_analizar_sitio', 'sitio' => $sitio_id ];
		if ( '' !== $volver ) {
			$args['volver'] = $volver;
		}

		return wp_nonce_url(
			add_query_arg( $args, admin_url( 'admin-post.php' ) ),
			'swpk_analizar_' . $sitio_id
		);
	}

	public static function accion_probar(): void {
		if ( ! self::puede_administrar() ) {
			wp_die( 'Sin permiso.' );
		}
		check_admin_referer( 'swpk_probar' );

		$r       = swpk_fuente()->probar();
		$mensaje = is_wp_error( $r )
			? 'error:' . rawurlencode( $r->get_error_message() )
			: 'ok:' . rawurlencode( 'La conexión con ' . swpk_fuente()->nombre() . ' funciona.' );

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG . '-ajustes&swpk_msg=' . $mensaje ) );
		exit;
	}

	public static function accion_sondear(): void {
		if ( ! self::puede_administrar() ) {
			wp_die( 'Sin permiso.' );
		}
		check_admin_referer( 'swpk_sondear' );

		$r = SWPK_Monitor::sondear_todos();

		wp_safe_redirect(
			admin_url(
				'admin.php?page=' . self::SLUG . '-caidas&swpk_msg=ok:' . rawurlencode(
					sprintf( '%d sitios sondeados, %d sin responder, %d cambios de estado.', $r['sondeados'], $r['abajo'], $r['cambios'] )
				)
			)
		);
		exit;
	}

	/**
	 * Lanzar la revisión de configuración a mano.
	 *
	 * No se corre aquí mismo: son unos ocho minutos para los 108 sitios y la
	 * petición del navegador se cortaría mucho antes, dejando la revisión a
	 * medias y sin que nadie se entere. Se encola para dentro de medio minuto y
	 * se dice que va en camino, que es la verdad.
	 */
	public static function accion_revisar(): void {
		if ( ! self::puede_administrar() ) {
			wp_die( 'Sin permiso.' );
		}
		check_admin_referer( 'swpk_revisar' );

		wp_schedule_single_event( time() + 30, SWPK_Sonda::HOOK );

		wp_safe_redirect(
			admin_url(
				'admin.php?page=' . self::SLUG . '-ajustes&swpk_msg=ok:' . rawurlencode(
					'La revisión de configuración quedó en cola. Tarda unos minutos en pasar por los 108 sitios; '
					. 'los resultados van apareciendo en Seguridad conforme termina.'
				)
			)
		);
		exit;
	}

	/**
	 * Leer la plataforma a mano.
	 *
	 * Igual que la revisión de configuración, se encola: son unos 25 minutos
	 * para los 108 sitios y el límite de peticiones por minuto de Kinsta no se
	 * puede apurar.
	 */
	public static function accion_plataforma(): void {
		if ( ! self::puede_administrar() ) {
			wp_die( 'Sin permiso.' );
		}
		check_admin_referer( 'swpk_plataforma' );

		if ( ! SWPK_Revision::disponible() ) {
			wp_safe_redirect(
				admin_url( 'admin.php?page=' . self::SLUG . '-ajustes&swpk_msg=error:' . rawurlencode( 'Esto necesita la llave de la plataforma; el panel está con los datos de prueba.' ) )
			);
			exit;
		}

		wp_schedule_single_event( time() + 30, SWPK_Revision::HOOK );

		wp_safe_redirect(
			admin_url(
				'admin.php?page=' . self::SLUG . '-ajustes&swpk_msg=ok:' . rawurlencode(
					'La lectura de la plataforma quedó en cola. Tarda unos 25 minutos en pasar por los 108 sitios, '
					. 'porque el límite de peticiones por minuto no se puede apurar.'
				)
			)
		);
		exit;
	}

	public static function accion_asignar(): void {
		if ( ! self::puede_administrar() ) {
			wp_die( 'Sin permiso.' );
		}
		check_admin_referer( 'swpk_asignar' );

		$asignaciones = isset( $_POST['asesora'] ) ? (array) wp_unslash( $_POST['asesora'] ) : [];
		$contactos    = isset( $_POST['contacto'] ) ? (array) wp_unslash( $_POST['contacto'] ) : [];

		foreach ( $asignaciones as $sitio_id => $usuario_id ) {
			SWPK_Perfiles::asignar(
				sanitize_text_field( (string) $sitio_id ),
				(int) $usuario_id,
				sanitize_text_field( (string) ( $contactos[ $sitio_id ] ?? '' ) )
			);
		}

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG . '-sitios&swpk_msg=ok:' . rawurlencode( 'Asignaciones guardadas.' ) ) );
		exit;
	}

	public static function accion_crear_acceso(): void {
		if ( ! self::puede_administrar() ) {
			wp_die( 'Sin permiso.' );
		}
		check_admin_referer( 'swpk_crear_acceso' );

		$r = SWPK_Perfiles::crear_acceso(
			sanitize_text_field( wp_unslash( (string) ( $_POST['nombre'] ?? '' ) ) ),
			sanitize_email( wp_unslash( (string) ( $_POST['correo'] ?? '' ) ) ),
			sanitize_key( wp_unslash( (string) ( $_POST['perfil'] ?? '' ) ) )
		);

		if ( is_wp_error( $r ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG . '-equipo&swpk_msg=error:' . rawurlencode( $r->get_error_message() ) ) );
			exit;
		}

		set_transient(
			'swpk_acceso_nuevo',
			[ 'nombre' => $r['usuario']->display_name, 'enlace' => $r['enlace'] ],
			300
		);

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG . '-equipo' ) );
		exit;
	}

	public static function accion_quitar_acceso(): void {
		if ( ! self::puede_administrar() ) {
			wp_die( 'Sin permiso.' );
		}

		$id = isset( $_GET['usuario'] ) ? (int) $_GET['usuario'] : 0;
		check_admin_referer( 'swpk_quitar_acceso_' . $id );

		$u = get_user_by( 'id', $id );
		if ( $u ) {
			// Se le quita el perfil del panel, no se borra la cuenta: borrar
			// usuarios de WordPress arrastra lo que hayan creado.
			foreach ( array_keys( SWPK_Perfiles::roles() ) as $rol ) {
				$u->remove_role( $rol );
			}
			global $wpdb;
			$wpdb->update( SWPK_Tablas::tabla( 'sitios' ), [ 'asesora_id' => 0 ], [ 'asesora_id' => $id ] );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG . '-equipo&swpk_msg=ok:' . rawurlencode( 'Acceso retirado.' ) ) );
		exit;
	}

	/**
	 * Crea una llave de consulta: la cuenta y su contraseña de aplicación, de
	 * una vez.
	 *
	 * La contraseña se enseña una sola vez y no se guarda en ninguna parte
	 * nuestra: WordPress solo conserva su huella. Es lo correcto y además es lo
	 * que ya hace el panel con los accesos de personas — nunca se inventa una
	 * contraseña y se manda por correo en texto.
	 */
	public static function accion_crear_llave(): void {
		if ( ! self::puede_administrar() ) {
			wp_die( 'Sin permiso.' );
		}
		check_admin_referer( 'swpk_crear_llave' );

		$llave = SWPK_Llaves::crear( wp_unslash( (string) ( $_POST['nombre'] ?? '' ) ) );

		if ( is_wp_error( $llave ) ) {
			self::volver_a_api( $llave->get_error_message(), true );
		}

		// La contraseña va en un transient y no en la dirección: lo que viaja en
		// la URL queda escrito en el historial del navegador y en el registro del
		// servidor, y esta se enseña una sola vez justamente para que no quede
		// escrita en ningún lado.
		set_transient( 'swpk_llave_nueva', $llave, SWPK_Llaves::ASOMO );

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG . '-api' ) );
		exit;
	}

	public static function accion_revocar_llave(): void {
		if ( ! self::puede_administrar() ) {
			wp_die( 'Sin permiso.' );
		}

		$id = isset( $_GET['usuario'] ) ? (int) $_GET['usuario'] : 0;
		check_admin_referer( 'swpk_revocar_llave_' . $id );

		$nombre = SWPK_Llaves::revocar( $id );

		if ( is_wp_error( $nombre ) ) {
			self::volver_a_api( $nombre->get_error_message(), true );
		}

		self::volver_a_api( sprintf( 'Llave revocada: %s. La cuenta se borró y lo que la usara deja de poder consultar.', $nombre ) );
	}

	private static function volver_a_api( string $mensaje, bool $error = false ): void {
		wp_safe_redirect(
			admin_url( 'admin.php?page=' . self::SLUG . '-api&swpk_msg=' . ( $error ? 'error:' : 'ok:' ) . rawurlencode( $mensaje ) )
		);
		exit;
	}

	private static function volver_a_correos( string $mensaje, bool $error = false ): void {
		wp_safe_redirect(
			admin_url( 'admin.php?page=' . self::SLUG . '-correos&swpk_msg=' . ( $error ? 'error:' : 'ok:' ) . rawurlencode( $mensaje ) )
		);
		exit;
	}

	/** Vuelve a la plantilla que se estaba editando, no al principio. */
	private static function volver_a_plantilla( string $cual, string $mensaje, bool $error = false ): void {
		wp_safe_redirect(
			add_query_arg(
				[
					'page'      => self::SLUG . '-correos',
					'seccion'   => 'plantillas',
					'plantilla' => $cual,
					'swpk_msg'  => ( $error ? 'error:' : 'ok:' ) . $mensaje,
				],
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Guardar la redacción de un aviso.
	 *
	 * El cuerpo NO pasa por sanitize_text_field: eso le comería los saltos de
	 * línea, que es lo único que separa un párrafo del siguiente. Se guarda tal
	 * cual y se escapa al armar el correo, que es donde importa.
	 */
	/**
	 * La vista previa de una plantilla: dibuja el correo y no manda nada.
	 *
	 * Sale en un marco dentro de la misma pantalla, así que escribe el correo
	 * entero y corta —no hay cabecera de administrador alrededor, que es justo
	 * lo que falsearía cómo se ve—. Trabaja sobre lo que esté escrito en el
	 * formulario, guardado o no: la gracia es ver antes de guardar.
	 */
	public static function accion_vista_previa(): void {
		if ( ! self::puede_administrar() ) {
			wp_die( 'Sin permiso.' );
		}
		check_admin_referer( 'swpk_vista_previa' );

		$cual = isset( $_POST['plantilla'] ) ? sanitize_key( (string) $_POST['plantilla'] ) : '';

		if ( ! isset( SWPK_Plantillas::catalogo()[ $cual ] ) ) {
			wp_die( 'Esa plantilla no existe.' );
		}

		$previa = SWPK_Plantillas::ejemplo(
			$cual,
			isset( $_POST['asunto'] ) ? wp_unslash( (string) $_POST['asunto'] ) : '',
			isset( $_POST['cuerpo'] ) ? wp_unslash( (string) $_POST['cuerpo'] ) : ''
		);

		header( 'Content-Type: text/html; charset=utf-8' );
		header( 'X-Frame-Options: SAMEORIGIN' );

		printf(
			'<!doctype html><html lang="es"><head><meta charset="utf-8">'
			. '<meta name="viewport" content="width=device-width, initial-scale=1">'
			. '<title>Vista previa</title></head>'
			. '<body style="margin:0;background:#f0f0f1">'
			. '<div style="background:#fff;border-bottom:1px solid #dcdcde;padding:14px 18px;'
			. 'font:13px -apple-system,system-ui,sans-serif;color:#50575e">'
			. '<span style="color:#8c8f94">Asunto</span><br>'
			. '<strong style="font-size:15px;color:#1d2327">%s</strong></div>%s</body></html>',
			esc_html( $previa['asunto'] ),
			wp_kses_post( $previa['html'] )
		);

		exit;
	}

	public static function accion_guardar_plantilla(): void {
		if ( ! self::puede_administrar() ) {
			wp_die( 'Sin permiso.' );
		}
		check_admin_referer( 'swpk_guardar_plantilla' );

		$cual = isset( $_POST['plantilla'] ) ? sanitize_key( (string) $_POST['plantilla'] ) : '';

		if ( ! isset( SWPK_Plantillas::catalogo()[ $cual ] ) ) {
			self::volver_a_correos( 'Esa plantilla no existe.', true );
		}

		$asunto = isset( $_POST['asunto'] ) ? wp_unslash( (string) $_POST['asunto'] ) : '';
		$cuerpo = isset( $_POST['cuerpo'] ) ? wp_unslash( (string) $_POST['cuerpo'] ) : '';

		// Un asunto vacío deja un correo sin asunto, que es lo que más parece
		// correo basura. Y un cuerpo vacío manda un correo en blanco a cien
		// clientes. Las dos cosas se paran aquí.
		if ( '' === trim( $asunto ) || '' === trim( $cuerpo ) ) {
			self::volver_a_plantilla(
				$cual,
				'No se guardó: el asunto y el texto no pueden quedar vacíos. Si querés el texto original, usá «Volver a la de fábrica».',
				true
			);
		}

		SWPK_Plantillas::guardar( $cual, $asunto, $cuerpo );

		self::volver_a_plantilla(
			$cual,
			'Redacción guardada. Desde el próximo envío, este aviso sale con este texto.'
		);
	}

	public static function accion_plantilla_fabrica(): void {
		if ( ! self::puede_administrar() ) {
			wp_die( 'Sin permiso.' );
		}

		$cual = isset( $_GET['plantilla'] ) ? sanitize_key( (string) $_GET['plantilla'] ) : '';
		check_admin_referer( 'swpk_plantilla_fabrica_' . $cual );

		if ( ! isset( SWPK_Plantillas::catalogo()[ $cual ] ) ) {
			self::volver_a_correos( 'Esa plantilla no existe.', true );
		}

		SWPK_Plantillas::restaurar( $cual );

		self::volver_a_plantilla( $cual, 'Listo: vuelve a estar como venía de fábrica.' );
	}

	public static function accion_modo_envio(): void {
		if ( ! self::puede_administrar() ) {
			wp_die( 'Sin permiso.' );
		}
		check_admin_referer( 'swpk_modo_envio' );

		$nuevo = SWPK_Correos::en_prueba() ? SWPK_Correos::MODO_REAL : SWPK_Correos::MODO_PRUEBA;
		update_option( 'swpk_modo_envio', $nuevo, false );

		self::volver_a_correos(
			SWPK_Correos::MODO_REAL === $nuevo
				? 'Envío real activado. Los correos ahora salen de verdad.'
				: 'De vuelta en modo de prueba. No sale ningún correo.'
		);
	}

	public static function accion_envios(): void {
		if ( ! self::puede_administrar() ) {
			wp_die( 'Sin permiso.' );
		}
		check_admin_referer( 'swpk_envios' );

		$nuevo = ! SWPK_Correos::activos();
		update_option( 'swpk_envios_activos', $nuevo, false );

		self::volver_a_correos( $nuevo ? 'Envíos encendidos.' : 'Envíos apagados: no sale nada.' );
	}

	public static function accion_correo_semanal(): void {
		if ( ! self::puede_administrar() ) {
			wp_die( 'Sin permiso.' );
		}
		check_admin_referer( 'swpk_correo_semanal' );

		$r = SWPK_Correos::semanal_asesoras();

		self::volver_a_correos(
			$r['asesoras']
				? sprintf( '%d correo(s) generados, cubriendo %d sitios. Están en la bitácora.', $r['asesoras'], $r['sitios'] )
				: 'No se generó ninguno: no hay asesoras con sitios asignados que tengan algo pendiente.'
		);
	}

	public static function accion_correo_diario(): void {
		if ( ! self::puede_administrar() ) {
			wp_die( 'Sin permiso.' );
		}
		check_admin_referer( 'swpk_correo_diario' );

		$r = SWPK_Correos::diario_soporte();

		self::volver_a_correos(
			$r['eventos']
				? sprintf( 'Resumen generado con %d cambios. Está en la bitácora.', $r['eventos'] )
				: 'No hubo cambios en las últimas 24 horas, así que no hay nada que contar.'
		);
	}

	public static function accion_correo_resumen(): void {
		if ( ! self::puede_administrar() ) {
			wp_die( 'Sin permiso.' );
		}
		check_admin_referer( 'swpk_correo_resumen' );

		$r = SWPK_Correos::resumen_semanal();

		self::volver_a_correos(
			sprintf(
				'Resumen de la semana del %s al %s: %d movimiento(s) en los sitios y %d aviso(s) enviados. Resultado del envío: %s.',
				$r['desde'],
				$r['hasta'],
				$r['movimientos'],
				$r['avisos'],
				$r['resultado']
			)
		);
	}

	public static function accion_correo_mensual(): void {
		if ( ! self::puede_administrar() ) {
			wp_die( 'Sin permiso.' );
		}
		check_admin_referer( 'swpk_correo_mensual' );

		$r = SWPK_Correos::mensual_clientes();

		self::volver_a_correos(
			sprintf(
				'%d correo(s) generados. %d sitios sin novedad en el mes y %d sin destinatario.',
				$r['enviados'],
				$r['sin_novedad'],
				$r['sin_destinatario']
			)
		);
	}

	public static function accion_cifrar(): void {
		if ( ! self::puede_administrar() ) {
			wp_die( 'Sin permiso.' );
		}
		check_admin_referer( 'swpk_cifrar' );

		$n = SWPK_Ajustes::cifrar_lo_viejo();

		wp_safe_redirect(
			admin_url( 'admin.php?page=' . self::SLUG . '-ajustes&swpk_msg=ok:' . rawurlencode( sprintf( '%d llave(s) cifradas.', $n ) ) )
		);
		exit;
	}

	public static function accion_guardar_ajustes(): void {
		if ( ! self::puede_administrar() ) {
			wp_die( 'Sin permiso.' );
		}
		check_admin_referer( 'swpk_ajustes' );

		// Las etiquetas ya no se escriben: se eligen de las que el proveedor
		// devuelve, y solo si la detección automática se equivocó.
		if ( isset( $_POST['etiqueta'] ) && is_array( $_POST['etiqueta'] ) ) {
			SWPK_Etiquetas::guardar_mapa( (array) wp_unslash( $_POST['etiqueta'] ) );
		}

		$campos = [
			'modo', 'company_id', 'api_key', 'wordfence_key', 'correo_soporte',
			'correo_ventas', 'dominios_propios',
		];

		$nuevos = [];
		foreach ( $campos as $campo ) {
			if ( ! isset( $_POST[ $campo ] ) ) {
				continue;
			}
			$valor = sanitize_text_field( wp_unslash( (string) $_POST[ $campo ] ) );

			// La llave vacía no borra la que ya estaba: el campo se muestra en
			// blanco a propósito para no reimprimir un secreto en el HTML.
			if ( in_array( $campo, [ 'api_key', 'wordfence_key' ], true ) && '' === $valor ) {
				continue;
			}

			$nuevos[ $campo ] = $valor;
		}

		SWPK_Ajustes::guardar( $nuevos );

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG . '-ajustes&swpk_msg=ok:' . rawurlencode( 'Ajustes guardados.' ) ) );
		exit;
	}

	// ── Seguridad: normas y órdenes ─────────────────────────────────────────

	/** A dónde volver después de una acción de Seguridad. */
	private static function url_seguridad( string $seccion, string $mensaje = '' ): string {
		$args = [ 'page' => self::SLUG . '-seguridad', 'seccion' => $seccion ];

		if ( '' !== $mensaje ) {
			$args['swpk_msg'] = $mensaje;
		}

		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	public static function url_encender_norma( string $clave, bool $encender ): string {
		return wp_nonce_url(
			add_query_arg(
				[ 'action' => 'swpk_encender_norma', 'norma' => $clave, 'encender' => $encender ? '1' : '0' ],
				admin_url( 'admin-post.php' )
			),
			'swpk_norma_' . $clave
		);
	}

	public static function url_borrar_norma( string $clave ): string {
		return wp_nonce_url(
			add_query_arg(
				[ 'action' => 'swpk_borrar_norma', 'norma' => $clave ],
				admin_url( 'admin-post.php' )
			),
			'swpk_norma_' . $clave
		);
	}

	/**
	 * La dirección de «arreglar».
	 *
	 * Con `$sitio_id` vacío quiere decir «en todos los que fallan». Es el mismo
	 * verbo y el mismo permiso: la diferencia la pone el alcance, y la pantalla
	 * de confirmación dice en cuántos sitios va a pasar antes de pulsar.
	 */
	public static function url_arreglar( string $norma, string $sitio_id = '' ): string {
		$args = [ 'action' => 'swpk_arreglar', 'norma' => $norma ];

		if ( '' !== $sitio_id ) {
			$args['sitio'] = $sitio_id;
		}

		return wp_nonce_url(
			add_query_arg( $args, admin_url( 'admin-post.php' ) ),
			'swpk_arreglar_' . $norma
		);
	}

	public static function url_marcar_norma( string $norma, string $sitio_id, string $estado ): string {
		return wp_nonce_url(
			add_query_arg(
				[ 'action' => 'swpk_marcar_norma', 'norma' => $norma, 'sitio' => $sitio_id, 'estado' => $estado ],
				admin_url( 'admin-post.php' )
			),
			'swpk_marcar_' . $norma . '_' . $sitio_id
		);
	}

	public static function accion_guardar_norma(): void {
		if ( ! self::puede_administrar() ) {
			wp_die( 'Sin permiso.' );
		}
		check_admin_referer( 'swpk_guardar_norma' );

		$datos = [
			'clave'     => isset( $_POST['clave'] ) ? sanitize_key( wp_unslash( (string) $_POST['clave'] ) ) : '',
			'nombre'    => isset( $_POST['nombre'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['nombre'] ) ) : '',
			'problema'  => isset( $_POST['problema'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['problema'] ) ) : '',
			'porque'    => isset( $_POST['porque'] ) ? sanitize_textarea_field( wp_unslash( (string) $_POST['porque'] ) ) : '',
			'severidad' => isset( $_POST['severidad'] ) ? sanitize_key( wp_unslash( (string) $_POST['severidad'] ) ) : 'media',
			'aplica_a'  => isset( $_POST['aplica_a'] ) ? implode( ',', array_map( 'sanitize_key', (array) wp_unslash( $_POST['aplica_a'] ) ) ) : '',
			'activa'    => isset( $_POST['activa'] ) ? 1 : 0,
		];

		$r = SWPK_Normas::guardar( $datos );

		$mensaje = is_wp_error( $r )
			? 'error:' . rawurlencode( $r->get_error_message() )
			: 'ok:' . rawurlencode( '' !== $datos['clave'] ? 'Norma guardada.' : 'Norma agregada. Aparecerá en el tablero como «sin medir» hasta que alguien la marque sitio por sitio.' );

		wp_safe_redirect( self::url_seguridad( 'normas', $mensaje ) );
		exit;
	}

	public static function accion_encender_norma(): void {
		if ( ! self::puede_administrar() ) {
			wp_die( 'Sin permiso.' );
		}

		$clave = isset( $_GET['norma'] ) ? sanitize_key( (string) $_GET['norma'] ) : '';
		check_admin_referer( 'swpk_norma_' . $clave );

		$encender = ! empty( $_GET['encender'] );
		SWPK_Normas::encender( $clave, $encender );
		SWPK_Estado::olvidar();

		wp_safe_redirect(
			self::url_seguridad(
				'normas',
				'ok:' . rawurlencode( $encender ? 'Norma encendida. Se empieza a medir en el siguiente reporte de cada sitio.' : 'Norma apagada. Deja de medirse y sale del tablero.' )
			)
		);
		exit;
	}

	public static function accion_borrar_norma(): void {
		if ( ! self::puede_administrar() ) {
			wp_die( 'Sin permiso.' );
		}

		$clave = isset( $_GET['norma'] ) ? sanitize_key( (string) $_GET['norma'] ) : '';
		check_admin_referer( 'swpk_norma_' . $clave );

		$r = SWPK_Normas::borrar( $clave );
		SWPK_Estado::olvidar();

		$mensaje = is_wp_error( $r )
			? 'error:' . rawurlencode( $r->get_error_message() )
			: 'ok:' . rawurlencode( 'Norma borrada, con todo lo que se había medido de ella.' );

		wp_safe_redirect( self::url_seguridad( 'normas', $mensaje ) );
		exit;
	}

	/** Marcar a mano una norma declarativa en un sitio. */
	public static function accion_marcar_norma(): void {
		if ( ! self::puede_administrar() ) {
			wp_die( 'Sin permiso.' );
		}

		$norma    = isset( $_GET['norma'] ) ? sanitize_key( (string) $_GET['norma'] ) : '';
		$sitio_id = isset( $_GET['sitio'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['sitio'] ) ) : '';
		check_admin_referer( 'swpk_marcar_' . $norma . '_' . $sitio_id );

		$estado = isset( $_GET['estado'] ) ? sanitize_key( (string) $_GET['estado'] ) : '';
		$r      = SWPK_Normas::marcar( $sitio_id, $norma, $estado );
		SWPK_Estado::olvidar();

		$mensaje = is_wp_error( $r )
			? 'error:' . rawurlencode( $r->get_error_message() )
			: 'ok:' . rawurlencode( 'Anotado, con tu nombre y la fecha.' );

		wp_safe_redirect(
			add_query_arg(
				[ 'page' => self::SLUG . '-sitio', 'sitio' => $sitio_id, 'de' => self::SLUG . '-seguridad', 'swpk_msg' => $mensaje ],
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Pide el arreglo de una norma: en un sitio, o en todos los que la fallan.
	 *
	 * No arregla nada aquí: encola. Y cuenta con precisión qué se encoló y qué
	 * no, porque «listo» sería mentira cuando tres de los catorce sitios son
	 * del cliente y uno no tiene agente.
	 */
	public static function accion_arreglar(): void {
		if ( ! self::puede_administrar() ) {
			wp_die( 'Sin permiso.' );
		}

		$norma = isset( $_GET['norma'] ) ? sanitize_key( (string) $_GET['norma'] ) : '';
		check_admin_referer( 'swpk_arreglar_' . $norma );

		$tipo = SWPK_Ordenes::orden_de_la_norma( $norma );

		if ( '' === $tipo ) {
			wp_safe_redirect(
				self::url_seguridad(
					'cumplimiento',
					'error:' . rawurlencode( 'Esa norma no se puede arreglar desde el panel: hay que hacerlo a mano.' )
				)
			);
			exit;
		}

		$uno = isset( $_GET['sitio'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['sitio'] ) ) : '';

		if ( '' !== $uno ) {
			$objetivos = [ $uno ];
		} else {
			// Todos los que hoy la fallan. Se recalcula aquí en vez de confiar
			// en lo que la pantalla tenía: entre que se cargó y se pulsó pudo
			// llegar un reporte y cambiar quién la cumple.
			$objetivos = [];

			foreach ( SWPK_Perfiles::sitios_visibles() as $s ) {
				$del = SWPK_Normas::del_sitio( (string) $s->sitio_id );

				if ( isset( $del[ $norma ] ) && SWPK_Normas::FALLA === (string) $del[ $norma ]->estado ) {
					$objetivos[] = (string) $s->sitio_id;
				}
			}
		}

		$encoladas = 0;
		$rechazos  = [];

		foreach ( $objetivos as $sitio_id ) {
			$r = SWPK_Ordenes::encolar( $tipo, $sitio_id, [], $norma );

			if ( is_wp_error( $r ) ) {
				$rechazos[] = $r->get_error_message();
				continue;
			}

			++$encoladas;
		}

		if ( 0 === $encoladas ) {
			wp_safe_redirect(
				self::url_seguridad(
					'cumplimiento',
					'error:' . rawurlencode( 'No se pudo pedir en ningún sitio. ' . implode( ' ', array_slice( $rechazos, 0, 2 ) ) )
				)
			);
			exit;
		}

		$texto = sprintf(
			'Pedido en %s. No es inmediato: cada sitio lo aplica cuando pregunte al panel, lo que puede tardar desde minutos hasta horas según su tráfico.',
			SWPK_Vista::plural( $encoladas, 'sitio', 'sitios' )
		);

		if ( $rechazos ) {
			$texto .= sprintf( ' %s quedaron fuera: %s', count( $rechazos ), $rechazos[0] );
		}

		wp_safe_redirect( self::url_seguridad( 'ordenes', 'ok:' . rawurlencode( $texto ) ) );
		exit;
	}

	public static function accion_reseteo_masivo(): void {
		if ( ! self::puede_administrar() ) {
			wp_die( 'Sin permiso.' );
		}
		check_admin_referer( 'swpk_reseteo_masivo' );

		$login  = isset( $_POST['login'] ) ? sanitize_user( wp_unslash( (string) $_POST['login'] ), true ) : '';
		$grupos = isset( $_POST['grupos'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['grupos'] ) ) : [];

		// La contraseña NO se sanitiza con sanitize_text_field: eso le quitaría
		// caracteres y dejaría puesta en los sitios una contraseña distinta de
		// la que se escribió aquí. Se usa tal cual llegó, que es lo que hace
		// WordPress con cualquier contraseña.
		$clave = isset( $_POST['clave'] ) ? (string) wp_unslash( $_POST['clave'] ) : '';

		if ( ! $grupos ) {
			wp_safe_redirect( self::url_seguridad( 'ordenes', 'error:' . rawurlencode( 'No se eligió ningún grupo de sitios.' ) ) );
			exit;
		}

		$objetivos = [];

		foreach ( SWPK_Perfiles::sitios_visibles() as $s ) {
			if ( in_array( (string) $s->tag, $grupos, true ) ) {
				$objetivos[] = (string) $s->sitio_id;
			}
		}

		$r = SWPK_Ordenes::reseteo_masivo( $objetivos, $login, $clave );

		if ( is_wp_error( $r ) ) {
			wp_safe_redirect( self::url_seguridad( 'ordenes', 'error:' . rawurlencode( $r->get_error_message() ) ) );
			exit;
		}

		$texto = sprintf(
			'Pedido en %s. La contraseña vive dos horas en memoria del panel y después se borra: los sitios que no pregunten en ese rato quedan sin cambiar y van a decirlo aquí.',
			SWPK_Vista::plural( (int) $r['encoladas'], 'sitio', 'sitios' )
		);

		if ( ! empty( $r['rechazos'] ) ) {
			$texto .= sprintf( ' %d quedaron fuera.', count( $r['rechazos'] ) );
		}

		wp_safe_redirect( self::url_seguridad( 'ordenes', 'ok:' . rawurlencode( $texto ) ) );
		exit;
	}

	public static function mostrar_mensaje(): void {
		if ( empty( $_GET['swpk_msg'] ) ) {
			return;
		}

		$crudo = sanitize_text_field( wp_unslash( (string) $_GET['swpk_msg'] ) );
		[ $tipo, $texto ] = array_pad( explode( ':', $crudo, 2 ), 2, '' );

		printf(
			'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
			'error' === $tipo ? 'error' : 'success',
			esc_html( urldecode( $texto ) )
		);
	}
}
