<?php
/**
 * Fuente de datos de prueba.
 *
 * Existe por una razón práctica: todavía no hay llave de la API de Kinsta, y
 * esperarla para empezar a construir habría costado semanas. Con esta fuente el
 * panel funciona completo —inventario, vulnerabilidades, avisos, bitácora— sin
 * tocar Kinsta ni un sitio de cliente. Sirve para tres cosas:
 *
 *   1. Desarrollar y probar hoy, sin accesos.
 *   2. Enseñarle al jefe un modelo que se ve y se usa, no un documento.
 *   3. Probar los casos feos a propósito: un sitio con cinco vulnerabilidades
 *      críticas, un plugin abandonado, un administrador nuevo con correo raro.
 *      Esos casos no se pueden "esperar a que pasen" en producción.
 *
 * Los nombres de sitio son inventados y los datos de vulnerabilidad llevan el
 * prefijo DEMO- para que nadie los confunda con un CVE real. Este repositorio
 * es público: aquí no entra ni un nombre de cliente ni un dominio real.
 */

defined( 'ABSPATH' ) || exit;

class SWPK_Fuente_Demo implements SWPK_Fuente {

	public function nombre(): string {
		return 'Datos de prueba';
	}

	public function proveedor(): string {
		return 'el proveedor';
	}

	public function es_demo(): bool {
		return true;
	}

	public function probar(): true|WP_Error {
		return true;
	}

	public function frescura(): array {
		$mapa = [];
		foreach ( (array) $this->sitios() as $s ) {
			$mapa[ (string) $s['sitio_id'] ] = current_time( 'mysql' );
		}
		return $mapa;
	}

	public function sitios(): array|WP_Error {
		$definicion = [
			// nombre, etiqueta de MyKinsta, versión de WP, PHP, estado
			[ 'cafetalera-el-zapote',   'Cafetalera El Zapote',    'Desarrollo',     '6.9',   '8.3', 'live' ],
			[ 'transportes-milenio',    'Transportes Milenio',     'Desarrollo',     '6.9',   '8.3', 'live' ],
			[ 'clinica-san-rafael',     'Clínica San Rafael',      'Auto update',    '6.9',   '8.2', 'live' ],
			[ 'repuestos-del-valle',    'Repuestos del Valle',     'Auto update',    '6.9',   '8.2', 'live' ],
			[ 'colegio-monte-verde',    'Colegio Monte Verde',     'Auto update',    '6.8.2', '8.2', 'live' ],
			[ 'ferreteria-esperanza',   'Ferretería La Esperanza', 'Auto update',    '6.9',   '8.1', 'live' ],
			[ 'hotel-casa-antigua',     'Hotel Casa Antigua',      'Auto update',    '6.9',   '8.2', 'live' ],
			[ 'distribuidora-maya',     'Distribuidora Maya',      'Auto update',    '6.8.2', '8.1', 'live' ],
			[ 'inmobiliaria-pacifico',  'Inmobiliaria Pacífico',   'No auto update', '6.4.3', '8.0', 'live' ],
			[ 'restaurante-la-ceiba',   'Restaurante La Ceiba',    'No auto update', '6.2.6', '7.4', 'live' ],
			[ 'gimnasio-fuerza-total',  'Gimnasio Fuerza Total',   'No auto update', '6.5.5', '8.1', 'live' ],
			[ 'libreria-academica',     'Librería Académica',      'No auto update', '5.9.9', '7.4', 'live' ],
		];

		$sitios = [];
		foreach ( $definicion as $i => $d ) {
			[ $slug, $visible, $etiqueta, $wp, $php, $estado ] = $d;

			$sitios[] = [
				'sitio_id'       => 'demo-' . str_pad( (string) ( $i + 1 ), 3, '0', STR_PAD_LEFT ),
				'entorno_id'     => 'env-demo-' . str_pad( (string) ( $i + 1 ), 3, '0', STR_PAD_LEFT ),
				'nombre'         => $slug,
				'nombre_visible' => $visible,
				'estado'         => $estado,
				'dominio'        => $slug . '.ejemplo.gt',
				'etiquetas'      => [ [ 'id' => 'lbl-' . sanitize_title( $etiqueta ), 'name' => $etiqueta ] ],
				'version_wp'     => $wp,
				'version_php'    => $php,
			];
		}

		return $sitios;
	}

	public function plugins( ?string $solo = null ): array|WP_Error {
		return $this->software( 'plugin', $solo );
	}

	public function temas( ?string $solo = null ): array|WP_Error {
		return $this->software( 'tema', $solo );
	}

	private function software( string $tipo, ?string $solo = null ): array {
		$sitios = $this->sitios();

		if ( 'tema' === $tipo ) {
			$catalogo = [
				[ 'astra', 'Astra', '4.11.2', '4.11.2', false ],
				[ 'hello-elementor', 'Hello Elementor', '3.4.4', '3.4.4', false ],
			];
		} else {
			// slug, título, versión al día, versión vieja (para los no update), vulnerable en la vieja
			$catalogo = [
				[ 'elementor',        'Elementor',            '3.34.1', '3.18.3', true  ],
				[ 'woocommerce',      'WooCommerce',          '9.9.5',  '8.2.1',  true  ],
				[ 'contact-form-7',   'Contact Form 7',       '6.1.1',  '5.7.2',  true  ],
				[ 'wordpress-seo',    'Yoast SEO',            '26.2',   '20.6',   false ],
				[ 'wp-rocket',        'WP Rocket',            '3.21',   '3.12',   false ],
				[ 'updraftplus',      'UpdraftPlus',          '1.25.8', '1.22.9', true  ],
				[ 'wp-mail-smtp',     'WP Mail SMTP',         '4.6.0',  '3.7.0',  false ],
				[ 'akismet',          'Akismet',              '5.6',    '5.0',    false ],
				[ 'wp-file-manager',  'WP File Manager',      '7.2.9',  '6.9',    true  ],
				[ 'loco-translate',   'Loco Translate',       '2.7.4',  '2.6.1',  false ],
			];
		}

		$filas = [];

		foreach ( $sitios as $sitio ) {
			if ( null !== $solo && (string) $sitio['sitio_id'] !== $solo ) {
				continue;
			}

			$tag      = SWPK_Etiquetas::clasificar( $sitio['etiquetas'] );
			$atrasado = SWPK_Etiquetas::NO_UPDATE === $tag;

			// Cuántos plugins tiene cada sitio: no todos tienen todo.
			$cuantos = 'tema' === $tipo ? 1 : ( $atrasado ? count( $catalogo ) : 6 );
			$semilla = crc32( $sitio['sitio_id'] );

			for ( $i = 0; $i < $cuantos; $i++ ) {
				$indice = ( $semilla + $i * 3 ) % count( $catalogo );
				[ $slug, $titulo, $al_dia, $vieja, $vulnerable_en_vieja ] = $catalogo[ $indice ];

				if ( isset( $filas[ $sitio['sitio_id'] . $slug ] ) ) {
					continue;
				}

				$version = $atrasado ? $vieja : $al_dia;

				// Un sitio de auto update puede estar un paso atrás: la
				// actualización automática corre, pero no es instantánea.
				$un_paso_atras = ! $atrasado && 0 === $i % 4;
				if ( $un_paso_atras ) {
					$version = $vieja;
				}

				$hay_actualizacion = $version !== $al_dia;

				$filas[ $sitio['sitio_id'] . $slug ] = [
					'sitio_nombre'       => $sitio['nombre_visible'],
					'sitio_id'           => $sitio['sitio_id'],
					'entorno_nombre'     => 'Live',
					'tipo'               => $tipo,
					'slug'               => $slug,
					'titulo'             => $titulo,
					'version'            => $version,
					'version_disponible' => $hay_actualizacion ? $al_dia : '',
					'hay_actualizacion'  => $hay_actualizacion,
					'es_vulnerable'      => $hay_actualizacion && $vulnerable_en_vieja,
					'auto_update'        => $atrasado ? '' : 'Kinsta Automatic Update',
					'activo'             => true,
				];
			}
		}

		return array_values( $filas );
	}

	/**
	 * Vulnerabilidades de prueba, con la misma forma que el feed de Wordfence.
	 *
	 * Los identificadores llevan DEMO- a propósito: son inventados. Sirven para
	 * ver cómo se ve el panel y cómo se lee el correo a la asesora, no para
	 * informar de un riesgo real.
	 */
	public function vulnerabilidades_demo(): array {
		return [
			[
				'wf_id'                => 'DEMO-0001',
				'titulo'               => 'Elementor <= 3.19.0 — Subida de archivos sin restricción por colaborador',
				'tipo_software'        => 'plugin',
				'slug'                 => 'elementor',
				'cve'                  => 'DEMO-CVE-0001',
				'cvss_score'           => 8.8,
				'cvss_rating'          => 'High',
				'informativa'          => 0,
				'descripcion'          => 'Una cuenta con rol de colaborador puede subir archivos arbitrarios y ejecutarlos en el servidor. Dato de prueba.',
				'remediacion'          => 'Actualizar a la versión 3.19.2 o posterior.',
				'parcheada'            => 1,
				'rangos'               => [ [ 'from_version' => '*', 'from_inclusive' => true, 'to_version' => '3.19.0', 'to_inclusive' => true ] ],
				'versiones_parcheadas' => [ '3.19.2' ],
			],
			[
				'wf_id'                => 'DEMO-0002',
				'titulo'               => 'WooCommerce <= 8.4.0 — Inyección SQL en el buscador de pedidos',
				'tipo_software'        => 'plugin',
				'slug'                 => 'woocommerce',
				'cve'                  => 'DEMO-CVE-0002',
				'cvss_score'           => 9.1,
				'cvss_rating'          => 'Critical',
				'informativa'          => 0,
				'descripcion'          => 'Permite leer la base de datos completa, incluidos los datos de los clientes. Dato de prueba.',
				'remediacion'          => 'Actualizar a la versión 8.4.1 o posterior.',
				'parcheada'            => 1,
				'rangos'               => [ [ 'from_version' => '*', 'from_inclusive' => true, 'to_version' => '8.4.0', 'to_inclusive' => true ] ],
				'versiones_parcheadas' => [ '8.4.1' ],
			],
			[
				'wf_id'                => 'DEMO-0003',
				'titulo'               => 'WP File Manager <= 7.0 — Ejecución remota de código sin autenticación',
				'tipo_software'        => 'plugin',
				'slug'                 => 'wp-file-manager',
				'cve'                  => 'DEMO-CVE-0003',
				'cvss_score'           => 9.8,
				'cvss_rating'          => 'Critical',
				'informativa'          => 0,
				'descripcion'          => 'Cualquiera en internet puede subir y ejecutar código sin iniciar sesión. Es la familia de fallas por la que entran los malwares que crean usuarios administradores. Dato de prueba.',
				'remediacion'          => 'Actualizar a la versión 7.2.1 o posterior.',
				'parcheada'            => 1,
				'rangos'               => [ [ 'from_version' => '*', 'from_inclusive' => true, 'to_version' => '7.0', 'to_inclusive' => true ] ],
				'versiones_parcheadas' => [ '7.2.1' ],
			],
			[
				'wf_id'                => 'DEMO-0004',
				'titulo'               => 'Contact Form 7 <= 5.9.7 — XSS almacenado por autor',
				'tipo_software'        => 'plugin',
				'slug'                 => 'contact-form-7',
				'cve'                  => 'DEMO-CVE-0004',
				'cvss_score'           => 6.4,
				'cvss_rating'          => 'Medium',
				'informativa'          => 0,
				'descripcion'          => 'Permite guardar scripts que se ejecutan en el navegador de otros usuarios del sitio. Dato de prueba.',
				'remediacion'          => 'Actualizar a la versión 5.9.8 o posterior.',
				'parcheada'            => 1,
				'rangos'               => [ [ 'from_version' => '*', 'from_inclusive' => true, 'to_version' => '5.9.7', 'to_inclusive' => true ] ],
				'versiones_parcheadas' => [ '5.9.8' ],
			],
			[
				'wf_id'                => 'DEMO-0005',
				'titulo'               => 'UpdraftPlus <= 1.23.3 — Descarga de respaldos por usuario con sesión',
				'tipo_software'        => 'plugin',
				'slug'                 => 'updraftplus',
				'cve'                  => 'DEMO-CVE-0005',
				'cvss_score'           => 7.5,
				'cvss_rating'          => 'High',
				'informativa'          => 0,
				'descripcion'          => 'Un suscriptor puede descargar el respaldo completo del sitio, que incluye la base de datos. Dato de prueba.',
				'remediacion'          => 'Actualizar a la versión 1.23.4 o posterior.',
				'parcheada'            => 1,
				'rangos'               => [ [ 'from_version' => '*', 'from_inclusive' => true, 'to_version' => '1.23.3', 'to_inclusive' => true ] ],
				'versiones_parcheadas' => [ '1.23.4' ],
			],
		];
	}
}
