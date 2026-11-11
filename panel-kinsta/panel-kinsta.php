<?php
/**
 * Plugin Name:       Panel Kinsta — Solución Web
 * Plugin URI:        https://github.com/Fer1605/panel-kinsta
 * Description:       Consolida el estado de los sitios alojados en Kinsta (etiquetas, plugins, versiones, vulnerabilidades y usuarios administradores), avisa a clientes, asesoras y soporte, y deja constancia con fecha de todo lo que se notificó.
 * Version:           0.1.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            Solución Web
 * License:           GPL-2.0-or-later
 * Text Domain:       panel-kinsta
 *
 * ────────────────────────────────────────────────────────────────────────────
 * SOLO LECTURA, POR DISEÑO
 *
 * Este plugin nunca escribe en Kinsta. La clase SWPK_Http implementa un único
 * método —GET— y no existe en todo el código una llamada que actualice,
 * reinicie, cree o borre nada en la cuenta de hosting. La llave de la API de
 * Kinsta no se puede limitar desde Kinsta (no hay roles de solo lectura ni
 * alcances por llave), así que la garantía está aquí, en el código, y es
 * auditable: buscar "wp_remote_" en este repositorio devuelve únicamente
 * peticiones GET, y las de Kinsta salen todas de un solo archivo.
 * ────────────────────────────────────────────────────────────────────────────
 */

defined( 'ABSPATH' ) || exit;

define( 'SWPK_VERSION', '0.1.0' );
define( 'SWPK_RUTA', plugin_dir_path( __FILE__ ) );
define( 'SWPK_URL', plugin_dir_url( __FILE__ ) );
define( 'SWPK_ARCHIVO', __FILE__ );

require_once SWPK_RUTA . 'includes/class-tablas.php';
require_once SWPK_RUTA . 'includes/class-perfiles.php';
require_once SWPK_RUTA . 'includes/class-accesos.php';
require_once SWPK_RUTA . 'includes/class-ajustes.php';
require_once SWPK_RUTA . 'includes/class-etiquetas.php';
require_once SWPK_RUTA . 'includes/class-versiones.php';
require_once SWPK_RUTA . 'includes/kinsta/interface-fuente.php';
require_once SWPK_RUTA . 'includes/kinsta/class-http.php';
require_once SWPK_RUTA . 'includes/kinsta/class-fuente-api.php';
require_once SWPK_RUTA . 'includes/kinsta/class-fuente-demo.php';
require_once SWPK_RUTA . 'includes/class-inventario.php';
require_once SWPK_RUTA . 'includes/class-wordfence.php';
require_once SWPK_RUTA . 'includes/class-riesgo.php';
require_once SWPK_RUTA . 'includes/class-textos.php';
require_once SWPK_RUTA . 'includes/class-eventos.php';
require_once SWPK_RUTA . 'includes/class-bitacora.php';
require_once SWPK_RUTA . 'includes/class-plantillas.php';
require_once SWPK_RUTA . 'includes/class-correos.php';
require_once SWPK_RUTA . 'includes/class-usuarios.php';
require_once SWPK_RUTA . 'includes/class-receptor.php';
require_once SWPK_RUTA . 'includes/class-monitor.php';
require_once SWPK_RUTA . 'includes/class-sincronizador.php';
require_once SWPK_RUTA . 'includes/class-exportar.php';
require_once SWPK_RUTA . 'includes/admin/class-vista.php';
require_once SWPK_RUTA . 'includes/admin/class-admin.php';

/**
 * Devuelve la fuente de datos activa.
 *
 * El panel no habla con Kinsta directamente: habla con una fuente. Hoy hay dos
 * implementaciones —la API real y los datos de prueba— y mañana puede haber una
 * para Pantheon sin tocar nada de lo que está encima. El jefe lo pidió en la
 * reunión (minuto 19:05) y es la diferencia entre agregar una clase y rehacer
 * el plugin.
 */
function swpk_fuente(): SWPK_Fuente {
	static $fuente = null;

	if ( null !== $fuente ) {
		return $fuente;
	}

	$ajustes = SWPK_Ajustes::todos();

	if ( 'demo' === $ajustes['modo'] || '' === $ajustes['api_key'] || '' === $ajustes['company_id'] ) {
		$fuente = new SWPK_Fuente_Demo();
	} else {
		$fuente = new SWPK_Fuente_Api( $ajustes['api_key'], $ajustes['company_id'] );
	}

	/**
	 * Permite sustituir la fuente de datos (Pantheon, pruebas automatizadas).
	 */
	$fuente = apply_filters( 'swpk_fuente', $fuente );

	return $fuente;
}

register_activation_hook( __FILE__, [ 'SWPK_Tablas', 'crear' ] );
register_activation_hook( __FILE__, [ 'SWPK_Perfiles', 'crear_roles' ] );
register_activation_hook( __FILE__, [ 'SWPK_Sincronizador', 'programar' ] );
register_activation_hook( __FILE__, [ 'SWPK_Monitor', 'programar' ] );
register_activation_hook( __FILE__, [ 'SWPK_Correos', 'programar' ] );
register_deactivation_hook( __FILE__, [ 'SWPK_Sincronizador', 'desprogramar' ] );
register_deactivation_hook( __FILE__, [ 'SWPK_Monitor', 'desprogramar' ] );
register_deactivation_hook( __FILE__, [ 'SWPK_Correos', 'desprogramar' ] );

add_action(
	'plugins_loaded',
	static function (): void {
		SWPK_Tablas::revisar_version();
		SWPK_Sincronizador::init();
		SWPK_Receptor::init();
		SWPK_Monitor::init();
		SWPK_Correos::init();
		SWPK_Exportar::init();
		SWPK_Accesos::init();
		SWPK_Perfiles::init();
		if ( is_admin() ) {
			SWPK_Admin::init();
		}
	}
);
