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
		add_action( 'admin_post_swpk_guardar_ajustes', [ __CLASS__, 'accion_guardar_ajustes' ] );
		add_action( 'admin_post_swpk_probar', [ __CLASS__, 'accion_probar' ] );
		add_action( 'admin_post_swpk_sondear', [ __CLASS__, 'accion_sondear' ] );
		add_action( 'admin_post_swpk_asignar', [ __CLASS__, 'accion_asignar' ] );
		add_action( 'admin_head', [ __CLASS__, 'estilos' ] );
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
		add_submenu_page( self::SLUG, 'Bitácora', 'Bitácora', $ver, self::SLUG . '-bitacora', [ __CLASS__, 'pantalla_bitacora' ] );
		add_submenu_page( self::SLUG, 'Accesos', 'Accesos', $admin, self::SLUG . '-accesos', [ __CLASS__, 'pantalla_accesos' ] );
		add_submenu_page( self::SLUG, 'Ajustes', 'Ajustes', $admin, self::SLUG . '-ajustes', [ __CLASS__, 'pantalla_ajustes' ] );
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
		$ult = SWPK_Inventario::ultima_sincronizacion();
		?>
		<p>
			<a href="<?php echo esc_url( $url ); ?>" class="button button-primary">Correr ahora</a>
			<span style="margin-left:10px;color:#50575e">
				<?php
				echo '' !== $ult
					? 'Última corrida: ' . esc_html( mysql2date( 'j \d\e F, H:i', $ult ) )
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

	public static function pantalla_accesos(): void {
		if ( ! SWPK_Perfiles::puede( SWPK_Perfiles::VER_ACCESOS ) ) {
			wp_die( 'Sin permiso.' );
		}
		require SWPK_RUTA . 'includes/admin/vistas/accesos.php';
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

	public static function accion_guardar_ajustes(): void {
		if ( ! self::puede_administrar() ) {
			wp_die( 'Sin permiso.' );
		}
		check_admin_referer( 'swpk_ajustes' );

		$campos = [
			'modo', 'company_id', 'api_key', 'wordfence_key', 'correo_soporte',
			'correo_ventas', 'dominios_propios', 'etiqueta_desarrollo',
			'etiqueta_auto', 'etiqueta_no_update',
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
