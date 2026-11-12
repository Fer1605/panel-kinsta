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
		add_action( 'admin_post_swpk_asignar', [ __CLASS__, 'accion_asignar' ] );
		add_action( 'admin_post_swpk_cifrar', [ __CLASS__, 'accion_cifrar' ] );
		add_action( 'admin_post_swpk_crear_acceso', [ __CLASS__, 'accion_crear_acceso' ] );
		add_action( 'admin_post_swpk_modo_envio', [ __CLASS__, 'accion_modo_envio' ] );
		add_action( 'admin_post_swpk_envios', [ __CLASS__, 'accion_envios' ] );
		add_action( 'admin_post_swpk_correo_semanal', [ __CLASS__, 'accion_correo_semanal' ] );
		add_action( 'admin_post_swpk_correo_diario', [ __CLASS__, 'accion_correo_diario' ] );
		add_action( 'admin_post_swpk_correo_mensual', [ __CLASS__, 'accion_correo_mensual' ] );
		add_action( 'admin_post_swpk_quitar_acceso', [ __CLASS__, 'accion_quitar_acceso' ] );
		add_action( 'admin_head', [ __CLASS__, 'estilos' ] );
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
		add_submenu_page( self::SLUG, 'Caídas', 'Caídas', $tecnico, self::SLUG . '-caidas', [ __CLASS__, 'pantalla_caidas' ] );

		// La ficha de un sitio no va en el menú: se llega desde las listas.
		add_submenu_page( null, 'Sitio', 'Sitio', $ver, self::SLUG . '-sitio', [ __CLASS__, 'pantalla_sitio' ] );
		add_submenu_page( null, 'Reporte', 'Reporte', $ver, self::SLUG . '-reporte', [ __CLASS__, 'pantalla_reporte' ] );
		add_submenu_page( self::SLUG, 'Correos', 'Correos', $admin, self::SLUG . '-correos', [ __CLASS__, 'pantalla_correos' ] );
		add_submenu_page( self::SLUG, 'Bitácora', 'Bitácora', $ver, self::SLUG . '-bitacora', [ __CLASS__, 'pantalla_bitacora' ] );
		add_submenu_page( self::SLUG, 'Equipo', 'Equipo', $admin, self::SLUG . '-equipo', [ __CLASS__, 'pantalla_equipo' ] );
		add_submenu_page( self::SLUG, 'Accesos', 'Accesos', $admin, self::SLUG . '-accesos', [ __CLASS__, 'pantalla_accesos' ] );
		add_submenu_page( self::SLUG, 'Ajustes', 'Ajustes', $admin, self::SLUG . '-ajustes', [ __CLASS__, 'pantalla_ajustes' ] );
	}

	/** El botón de bajar a Excel, con los filtros que estén puestos. */
	public static function boton_exportar( string $tipo ): void {
		$filtros = [];
		foreach ( [ 'tag', 'nivel', 'q' ] as $k ) {
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

		return in_array( $de, [ self::SLUG . '-sitios', self::SLUG . '-vulnerabilidades' ], true )
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
		// guardar el PDF, así que en el reporte conviene que diga de qué es.
		return self::SLUG . '-reporte' === $pagina
			? sprintf( 'Reporte %s %s', $nombre, $admin_titulo )
			: $nombre . ' ' . $admin_titulo;
	}

	/** El nombre del proveedor, para escribirlo en las pantallas. */
	public static function proveedor(): string {
		return swpk_fuente()->proveedor();
	}

	private static function pagina_actual(): string {
		return isset( $_GET['page'] ) ? sanitize_key( (string) $_GET['page'] ) : '';
	}

	public static function estilos(): void {
		$pagina = isset( $_GET['page'] ) ? sanitize_key( (string) $_GET['page'] ) : '';
		if ( ! str_starts_with( $pagina, self::SLUG ) ) {
			return;
		}
		?>
		<style>
			.swpk-tarjetas{display:flex;gap:14px;flex-wrap:wrap;margin:18px 0}
			.swpk-tarjeta{background:#fff;border:1px solid #c3c4c7;border-radius:6px;padding:16px 18px;min-width:170px;flex:1}
			.swpk-tarjeta .swpk-n{font-size:30px;font-weight:600;line-height:1.1}
			.swpk-tarjeta .swpk-r{color:#50575e;font-size:13px;margin-top:4px}
			.swpk-nivel{display:inline-block;padding:2px 9px;border-radius:11px;color:#fff;font-size:11px;font-weight:600;white-space:nowrap}
			.swpk-aviso-demo{border-left:4px solid #dba617;background:#fcf9e8;padding:12px 14px;margin:16px 0}
			.swpk-atribucion{color:#646970;font-size:12px;margin-top:18px;padding-top:12px;border-top:1px solid #dcdcde}
			.swpk-tabla td,.swpk-tabla th{vertical-align:middle}
			.swpk-mono{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:12px}
			.swpk-copiable{width:100%;min-height:150px;font-family:ui-monospace,Menlo,monospace;font-size:12px}

			/* Elegir grupo */
			.swpk-grupos{display:flex;gap:12px;flex-wrap:wrap;margin:18px 0}
			.swpk-grupo{display:block;flex:1;min-width:190px;background:#fff;border:1px solid #c3c4c7;
				border-radius:8px;padding:16px 18px;text-decoration:none;color:#1d2327;transition:.12s}
			.swpk-grupo:hover{border-color:#2271b1;box-shadow:0 1px 5px rgba(0,0,0,.07);color:#1d2327}
			.swpk-grupo-activo{border-color:#2271b1;border-width:2px;background:#f0f6fc;padding:15px 17px}
			.swpk-grupo-n{display:block;font-size:30px;font-weight:600;line-height:1.1}
			.swpk-grupo-de{font-size:15px;font-weight:400;color:#646970;margin-left:5px}
			.swpk-grupo-t{display:block;font-weight:600;margin-top:3px}
			.swpk-grupo-s{display:block;color:#646970;font-size:12px;margin-top:2px}

			/* Filtros */
			.swpk-filtros{display:flex;gap:7px;flex-wrap:wrap;align-items:center;margin:14px 0}
			.swpk-filtros-t{color:#646970;font-size:13px;margin-right:3px}
			.swpk-filtro{display:inline-flex;align-items:center;gap:6px;padding:4px 12px;border-radius:14px;
				border:1px solid #c3c4c7;background:#fff;text-decoration:none;color:#1d2327;font-size:13px}
			.swpk-filtro:hover{border-color:#2271b1;color:#1d2327}
			.swpk-filtro-activo{background:#2271b1;border-color:#2271b1;color:#fff}
			.swpk-filtro-activo:hover{color:#fff}
			.swpk-filtro-n{background:rgba(0,0,0,.08);border-radius:9px;padding:0 6px;font-size:11px}
			.swpk-filtro-activo .swpk-filtro-n{background:rgba(255,255,255,.25)}

			/* Barra de filtros + buscador, en una sola fila */
			.swpk-barra{display:flex;justify-content:space-between;align-items:center;
				gap:16px;flex-wrap:wrap;margin:14px 0}
			.swpk-barra .swpk-filtros{margin:0}
			.swpk-barra .swpk-buscador{margin:0}

			/* Volver */
			.swpk-volver{margin:4px 0 14px}
			.swpk-volver a{display:inline-block;padding:6px 14px;border:1px solid #c3c4c7;
				border-radius:4px;background:#fff;text-decoration:none;font-weight:600;color:#2271b1}
			.swpk-volver a:hover{border-color:#2271b1;background:#f0f6fc}

			/* Fichas de sitio en la lista */
			.swpk-ficha{display:flex;justify-content:space-between;align-items:center;gap:16px;
				background:#fff;border:1px solid #c3c4c7;border-radius:8px;padding:16px 20px;margin-bottom:10px}
			.swpk-ficha:hover{border-color:#2271b1}
			.swpk-ficha-nom{font-size:15px;font-weight:600;margin:0}
			.swpk-ficha-dom{color:#646970;font-size:12px;font-family:ui-monospace,Menlo,monospace}
			.swpk-ficha-cifras{display:flex;gap:26px;align-items:center;flex-wrap:wrap}
			.swpk-cifra{text-align:center;min-width:72px}
			.swpk-cifra b{display:block;font-size:22px;line-height:1.1}
			.swpk-cifra span{color:#646970;font-size:11px}

			/* Buscador y paginación */
			.swpk-buscador{display:inline-flex;gap:6px;align-items:center;margin:0 0 14px}
			.swpk-buscador input[type=search]{min-width:230px}
			.swpk-paginador{display:flex;justify-content:space-between;align-items:center;
				margin:14px 0;gap:12px;flex-wrap:wrap}
			.swpk-cuenta{color:#646970;font-size:13px}
			.swpk-pag{margin:0 10px;color:#646970;font-size:13px}

			/* Texto para la asesora */
			.swpk-mensaje{background:#fff;border:1px solid #c3c4c7;border-left:4px solid #2271b1;
				border-radius:6px;padding:20px 24px;margin:14px 0;max-width:780px}
			.swpk-mensaje h4{margin:0 0 4px;font-size:15px}
			.swpk-mensaje-intro{margin:0 0 16px;color:#1d2327}
			.swpk-mod{border-top:1px solid #f0f0f1;padding:14px 0 0;margin:14px 0 0}
			.swpk-mod:first-of-type{border-top:0;padding-top:0;margin-top:0}
			.swpk-mod-nombre{font-weight:600;font-size:14px}
			.swpk-mod-ver{color:#646970;font-size:13px;margin:3px 0 7px}
			.swpk-mod-ver b{color:#008a20}
			.swpk-mod-que{margin:0;color:#1d2327}
			.swpk-mod-ojo{color:#b32d2e;margin:7px 0 0;font-size:13px}
			.swpk-cierre{margin:18px 0 0;padding-top:14px;border-top:1px solid #f0f0f1;color:#1d2327}
			.swpk-copiar{margin-top:14px}
			.swpk-plano{white-space:pre-wrap;font-family:inherit;font-size:14px;line-height:1.6;margin:0}
			.swpk-plano-largo{max-height:380px;overflow-y:auto;padding-right:14px;
				border-bottom:1px solid #f0f0f1}
			.swpk-sello{background:#fff;border:1px solid #dcdcde;border-left:4px solid #72aee6;
				padding:12px 16px;margin:16px 0;max-width:840px;font-size:13px;line-height:1.5}
			.swpk-sello-mal{border-left-color:#b32d2e;background:#fcf0f1}
			.swpk-sello-t{font-weight:600;color:#1d2327;margin-bottom:8px}
			.swpk-sello ul{margin:0;padding:0;list-style:none}
			.swpk-sello li{display:flex;flex-wrap:wrap;gap:4px 12px;padding:3px 0}
			.swpk-sello-q{color:#50575e;flex:1 1 320px}
			.swpk-sello-c{color:#1d2327;font-variant-numeric:tabular-nums;white-space:nowrap}
			.swpk-sello li.swpk-viejo .swpk-sello-c{color:#b32d2e;font-weight:600}
			.swpk-sello-p{flex:1 1 100%;color:#b32d2e}
			.swpk-sello-ahora{margin-top:9px;padding-top:9px;border-top:1px solid #f0f0f1;color:#50575e}

			/* El Resumen es una tabla. Lo único que necesita: una línea arriba,
			   gris para lo que acompaña y rojo para una sola cosa. */
			.swpk-linea{max-width:900px;font-size:14px;line-height:1.6;margin:14px 0 16px}
			.swpk-gris{color:#8c8f94}
			.swpk-rojo{color:#b32d2e;font-weight:600}
			.swpk-nota{max-width:900px;color:#50575e;font-size:13px;margin-top:10px}
			.swpk-dia td{background:#f6f7f7 !important;font-weight:600;color:#1d2327;
				border-top:1px solid #dcdcde}
			.swpk-rango{display:flex;gap:14px;align-items:flex-end;flex-wrap:wrap;
				margin:16px 0 4px;padding:14px 16px;background:#fff;border:1px solid #dcdcde;
				border-radius:4px;max-width:900px}
			.swpk-rango label{font-size:12px;color:#50575e}
		</style>
		<?php
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

	public static function nivel_badge( string $nivel ): string {
		$colores = SWPK_Riesgo::colores();
		$nombres = SWPK_Riesgo::nombres();
		return sprintf(
			'<span class="swpk-nivel" style="background:%s">%s</span>',
			esc_attr( $colores[ $nivel ] ?? '#50575e' ),
			esc_html( $nombres[ $nivel ] ?? $nivel )
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

	public static function pantalla_accesos(): void {
		if ( ! SWPK_Perfiles::puede( SWPK_Perfiles::VER_ACCESOS ) ) {
			wp_die( 'Sin permiso.' );
		}
		require SWPK_RUTA . 'includes/admin/vistas/accesos.php';
	}

	public static function pantalla_correos(): void {
		if ( ! self::puede_administrar() ) {
			wp_die( 'Sin permiso.' );
		}
		require SWPK_RUTA . 'includes/admin/vistas/correos.php';
	}

	public static function pantalla_bitacora(): void {
		if ( ! self::puede_ver() ) {
			wp_die( 'Sin permiso.' );
		}
		require SWPK_RUTA . 'includes/admin/vistas/bitacora.php';
	}

	public static function pantalla_ajustes(): void {
		if ( ! self::puede_administrar() ) {
			wp_die( 'Sin permiso.' );
		}
		require SWPK_RUTA . 'includes/admin/vistas/ajustes.php';
	}

	// ── Acciones ────────────────────────────────────────────────────────────

	public static function accion_correr(): void {
		if ( ! self::puede_administrar() ) {
			wp_die( 'Sin permiso.' );
		}
		check_admin_referer( 'swpk_correr' );

		$resumen = SWPK_Sincronizador::correr();

		$mensaje = $resumen['errores']
			? 'error:' . rawurlencode( implode( ' · ', $resumen['errores'] ) )
			: 'ok:' . rawurlencode(
				sprintf(
					'%d sitios, %d filas de inventario, %d vulnerabilidades, %d eventos nuevos.',
					$resumen['sitios'],
					$resumen['filas_foto'],
					$resumen['vulnerabilidades'],
					$resumen['eventos']
				)
			);

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG . '&swpk_msg=' . $mensaje ) );
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

	private static function volver_a_correos( string $mensaje, bool $error = false ): void {
		wp_safe_redirect(
			admin_url( 'admin.php?page=' . self::SLUG . '-correos&swpk_msg=' . ( $error ? 'error:' : 'ok:' ) . rawurlencode( $mensaje ) )
		);
		exit;
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
