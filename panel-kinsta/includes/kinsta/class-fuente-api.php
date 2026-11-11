<?php
/**
 * La fuente real: la API de Kinsta.
 *
 * Diseñada alrededor del límite de 120 peticiones por minuto por empresa: se
 * usan los endpoints de NIVEL EMPRESA, no los de sitio. Así el inventario
 * completo son tres llamadas al día en total, en vez de dos por sitio (que con
 * 100 sitios serían 200 y chocarían con el límite).
 */

defined( 'ABSPATH' ) || exit;

class SWPK_Fuente_Api implements SWPK_Fuente {

	private SWPK_Http $http;

	public function __construct( string $api_key, private string $company_id ) {
		$this->http = new SWPK_Http( $api_key );
	}

	public function nombre(): string {
		return 'API de Kinsta';
	}

	public function es_demo(): bool {
		return false;
	}

	public function probar(): true|WP_Error {
		$r = $this->http->get( '/validate' );
		return is_wp_error( $r ) ? $r : true;
	}

	public function sitios(): array|WP_Error {
		$r = $this->http->get(
			'/sites',
			[
				'company'              => $this->company_id,
				'include_environments' => 'true',
			]
		);

		if ( is_wp_error( $r ) ) {
			return $r;
		}

		$sitios = $r['company']['sites'] ?? [];
		if ( ! is_array( $sitios ) ) {
			return new WP_Error( 'swpk_forma', 'La respuesta de /sites no trae company.sites.' );
		}

		return array_values( array_map( [ $this, 'normalizar_sitio' ], $sitios ) );
	}

	/**
	 * Normaliza un sitio de Kinsta a la forma que usa el panel.
	 *
	 * Dos trampas verificadas que se arreglan aquí y en ningún otro lugar:
	 *  - siteLabels viene en camelCase mientras casi todo lo demás es
	 *    snake_case, y hay ejemplos en el blog de Kinsta que muestran
	 *    site_labels. Se aceptan los dos.
	 *  - No existe filtro por etiqueta en el query string: /sites solo acepta
	 *    company e include_environments. El filtrado por tag es nuestro.
	 */
	private function normalizar_sitio( array $sitio ): array {
		$etiquetas = $sitio['siteLabels'] ?? $sitio['site_labels'] ?? [];
		$etiquetas = is_array( $etiquetas ) ? $etiquetas : [];

		$entorno = null;
		foreach ( (array) ( $sitio['environments'] ?? [] ) as $e ) {
			// El entorno vivo es el que interesa; si no viene marcado, el primero.
			if ( null === $entorno || in_array( strtolower( (string) ( $e['name'] ?? '' ) ), [ 'live', 'production' ], true ) ) {
				$entorno = $e;
			}
		}

		$dominio = '';
		if ( is_array( $entorno ) ) {
			$dominio = (string) ( $entorno['primaryDomain']['name'] ?? $entorno['primary_domain']['name'] ?? '' );
			if ( '' === $dominio && ! empty( $entorno['domains'][0]['name'] ) ) {
				$dominio = (string) $entorno['domains'][0]['name'];
			}
		}

		return [
			'sitio_id'       => (string) ( $sitio['id'] ?? '' ),
			'entorno_id'     => (string) ( $entorno['id'] ?? '' ),
			'nombre'         => (string) ( $sitio['name'] ?? '' ),
			'nombre_visible' => (string) ( $sitio['display_name'] ?? $sitio['name'] ?? '' ),
			'estado'         => (string) ( $sitio['status'] ?? '' ),
			'dominio'        => $dominio,
			'etiquetas'      => array_values(
				array_filter(
					array_map(
						static fn( $e ) => [
							'id'   => (string) ( $e['id'] ?? '' ),
							'name' => (string) ( $e['name'] ?? '' ),
						],
						$etiquetas
					),
					static fn( $e ) => '' !== $e['name']
				)
			),
			'version_wp'     => (string) ( $entorno['wordpress_version'] ?? '' ),
			'version_php'    => (string) ( $entorno['container_info']['php_engine_version'] ?? '' ),
		];
	}

	public function plugins(): array|WP_Error {
		return $this->software( 'wp-plugins', 'plugins', 'plugin' );
	}

	public function temas(): array|WP_Error {
		return $this->software( 'wp-themes', 'themes', 'tema' );
	}

	/**
	 * Lee plugins o temas de toda la empresa.
	 *
	 * Kinsta documenta los filtros (status, column, order_by) en el CUERPO de un
	 * GET. Se mandan, pero no se confía en ellos: si el servidor los ignora
	 * —cosa que pasa— llega el listado completo y el panel filtra por su cuenta.
	 * Pedir "column: vulnerable" y recibir todo sin filtrar, sin darse cuenta,
	 * sería peor que no filtrar nunca.
	 */
	private function software( string $ruta, string $clave, string $tipo ): array|WP_Error {
		$r = $this->http->get(
			'/company/' . rawurlencode( $this->company_id ) . '/' . $ruta,
			[],
			[ 'order_by' => [ 'field' => 'name', 'order' => 'ascend' ] ]
		);

		if ( is_wp_error( $r ) ) {
			return $r;
		}

		$items = $r['company'][ $clave ]['items'] ?? [];
		if ( ! is_array( $items ) ) {
			return new WP_Error( 'swpk_forma', sprintf( 'La respuesta de /%s no trae company.%s.items.', $ruta, $clave ) );
		}

		// Kinsta agrupa por software y lista dentro los entornos donde está
		// instalado. El panel lo quiere al revés: una fila por sitio y software.
		$filas = [];
		foreach ( $items as $item ) {
			$slug   = (string) ( $item['name'] ?? '' );
			$titulo = (string) ( $item['title'] ?? $slug );

			foreach ( (array) ( $item['environments'] ?? [] ) as $env ) {
				$version    = (string) ( $env['plugin_version'] ?? $env['theme_version'] ?? $env['version'] ?? '' );
				$disponible = (string) ( $env['plugin_update'] ?? $env['theme_update'] ?? $env['update_version'] ?? '' );
				$estado     = (string) ( $env['plugin_status'] ?? $env['theme_status'] ?? $env['status'] ?? '' );

				// "hay actualización" no viene como booleano en ningún campo:
				// se deduce de que exista una versión superior a la instalada.
				$hay_actualizacion = '' !== $disponible
					&& 'available' !== strtolower( $disponible )
					&& SWPK_Versiones::es_menor( $version, $disponible );

				if ( ! $hay_actualizacion && in_array( strtolower( $disponible ), [ 'available', 'yes' ], true ) ) {
					$hay_actualizacion = true;
					$disponible        = (string) ( $item['latest_version'] ?? '' );
				}

				$filas[] = [
					'sitio_nombre'       => (string) ( $env['site_display_name'] ?? '' ),
					'entorno_nombre'     => (string) ( $env['display_name'] ?? '' ),
					'tipo'               => $tipo,
					'slug'               => $slug,
					'titulo'             => $titulo,
					'version'            => $version,
					'version_disponible' => $hay_actualizacion ? $disponible : '',
					'hay_actualizacion'  => $hay_actualizacion,
					'es_vulnerable'      => (bool) ( $env['is_version_vulnerable'] ?? $item['is_latest_version_vulnerable'] ?? false ),
					'auto_update'        => (string) ( $env['auto_update_type'] ?? '' ),
					'activo'             => 'active' === strtolower( $estado ),
				];
			}
		}

		return $filas;
	}
}
