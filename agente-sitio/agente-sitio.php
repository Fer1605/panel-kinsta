<?php
/**
 * Plugin Name:       Agente Solución Web
 * Plugin URI:        https://github.com/Fer1605/panel-kinsta
 * Description:       Reporta al Panel Kinsta quién tiene acceso de administrador a este sitio y activa la verificación en dos pasos por correo. No envía contenido ni datos de la base.
 * Version:           0.1.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            Solución Web
 * License:           GPL-2.0-or-later
 *
 * ────────────────────────────────────────────────────────────────────────────
 * POR QUÉ EXISTE ESTE PLUGIN
 *
 * La API de Kinsta no tiene endpoint para listar los usuarios de WordPress.
 * Tiene para crear un administrador, para preguntar si un correo ya existe y
 * para generar un enlace de acceso — nada de eso sirve para descubrir usuarios
 * que no sabíamos que existían, que es justo lo que hay que detectar cuando un
 * malware crea un administrador.
 *
 * Y se descartó dar acceso SSH a cada proyecto, con razón.
 *
 * Entonces la única salida es dejar algo instalado en cada sitio. Si de todas
 * formas hay que entrar una vez, esa visita resuelve cuatro cosas de un viaje:
 * los usuarios, la verificación en dos pasos, la prueba de que el sitio manda
 * correo, y más adelante el resumen por proyecto de la Fase 2.
 *
 * ─── QUÉ SALE DE AQUÍ, Y QUÉ NO ─────────────────────────────────────────────
 *
 * Sale: id, usuario, correo, roles y fecha de alta de cada usuario; si la
 * verificación en dos pasos está activa; y si el sitio logra enviar correo.
 *
 * NO sale: contraseñas (ni cifradas), contenido, pedidos, clientes, ni nada
 * más de la base de datos.
 *
 * Y el sitio NO abre ninguna puerta nueva: es él quien llama al panel una vez
 * al día. No queda un endpoint esperando peticiones de internet.
 * ────────────────────────────────────────────────────────────────────────────
 */

defined( 'ABSPATH' ) || exit;

define( 'SWA_VERSION', '0.1.0' );
define( 'SWA_RUTA', plugin_dir_path( __FILE__ ) );
define( 'SWA_ARCHIVO', __FILE__ );

require_once SWA_RUTA . 'includes/class-ajustes.php';
require_once SWA_RUTA . 'includes/class-firma.php';
require_once SWA_RUTA . 'includes/class-correo.php';
require_once SWA_RUTA . 'includes/class-reporte.php';
require_once SWA_RUTA . 'includes/class-dos-factores.php';
require_once SWA_RUTA . 'includes/class-admin.php';

register_activation_hook( __FILE__, [ 'SWA_Reporte', 'programar' ] );
register_deactivation_hook( __FILE__, [ 'SWA_Reporte', 'desprogramar' ] );

add_action(
	'plugins_loaded',
	static function (): void {
		SWA_Reporte::init();
		SWA_Dos_Factores::init();
		if ( is_admin() ) {
			SWA_Admin::init();
		}
	}
);
