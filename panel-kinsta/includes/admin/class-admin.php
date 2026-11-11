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

	public const CAP_VER       = 'swpk_ver_panel';
	public const CAP_AJUSTES   = 'swpk_administrar_panel';
	public const SLUG          = 'panel-kinsta';

	public static function init(): void {
		add_action( 'admin_menu', [ __CLASS__, 'menu' ] );
		add_action( 'admin_post_swpk_correr', [ __CLASS__, 'accion_correr' ] );
		add_action( 'admin_post_swpk_guardar_ajustes', [ __CLASS__, 'accion_guardar_ajustes' ] );
		add_action( 'admin_post_swpk_probar', [ __CLASS__, 'accion_probar' ] );
		add_action( 'admin_head', [ __CLASS__, 'estilos' ] );
	}

	private static function puede_ver(): bool {
		return current_user_can( self::CAP_VER ) || current_user_can( 'manage_options' );
	}

	private static function puede_administrar(): bool {
		return current_user_can( self::CAP_AJUSTES ) || current_user_can( 'manage_options' );
	}

	public static function menu(): void {
		$cap = self::puede_ver() ? 'read' : 'do_not_allow';

		add_menu_page(
			'Panel Kinsta',
			'Panel Kinsta',
			$cap,
			self::SLUG,
			[ __CLASS__, 'pantalla_resumen' ],
			'dashicons-shield-alt',
			3
		);

		add_submenu_page( self::SLUG, 'Resumen', 'Resumen', $cap, self::SLUG, [ __CLASS__, 'pantalla_resumen' ] );
		add_submenu_page( self::SLUG, 'Sitios', 'Sitios', $cap, self::SLUG . '-sitios', [ __CLASS__, 'pantalla_sitios' ] );
		add_submenu_page( self::SLUG, 'Vulnerabilidades', 'Vulnerabilidades', $cap, self::SLUG . '-vulnerabilidades', [ __CLASS__, 'pantalla_vulnerabilidades' ] );
		add_submenu_page( self::SLUG, 'Bitácora', 'Bitácora', $cap, self::SLUG . '-bitacora', [ __CLASS__, 'pantalla_bitacora' ] );
		add_submenu_page(
			self::SLUG,
			'Ajustes',
			'Ajustes',
			self::puede_administrar() ? 'read' : 'do_not_allow',
			self::SLUG . '-ajustes',
			[ __CLASS__, 'pantalla_ajustes' ]
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
