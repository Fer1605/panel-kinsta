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

	public function proveedor(): string {
		return 'Kinsta';
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
			// Viene como "php8.5"; en pantalla estorba el prefijo.
			'version_php'    => ltrim( (string) ( $entorno['container_info']['php_engine_version'] ?? '' ), 'phpPHP' ),
		];
	}

	public function plugins(): array|WP_Error {
		return $this->software( 'wp-plugins', 'plugins', 'plugin' );
	}

	public function temas(): array|WP_Error {
		return $this->software( 'wp-themes', 'themes', 'tema' );
	}

	/** @var array<int,array<string,mixed>>|null */
	private ?array $cache_sitios = null;

	/** @var array<string,string> sitio_id => última vez que Kinsta miró lo instalado */
	private array $frescura = [];

	public function frescura(): array {
		return $this->frescura;
	}

	private function sitios_cacheados(): array|WP_Error {
		if ( null === $this->cache_sitios ) {
			$s = $this->sitios();
			if ( is_wp_error( $s ) ) {
				return $s;
			}
			$this->cache_sitios = $s;
		}

		return $this->cache_sitios;
	}

	/**
	 * Lee los plugins o temas instalados, sitio por sitio.
	 *
	 * ─── Por qué sitio por sitio y no con el endpoint de empresa ────────────
	 *
	 * El endpoint de empresa parecía la opción obvia: una sola llamada para
	 * todo. Pero contra la API real resultó que **pagina de 30 en 30** —declara
	 * 338 y entrega 30— y los parámetros que controlan eso (limit, offset)
	 * Kinsta los espera tipados, es decir en el cuerpo de un GET, que es
	 * justamente lo que no se puede mandar desde WordPress.
	 *
	 * El endpoint por entorno no pagina: entrega la lista completa del sitio.
	 * Cuesta dos peticiones por sitio —214 con 107 sitios— y por eso el cliente
	 * HTTP va frenando solo para no pasarse del límite por minuto. La corrida
	 * tarda un par de minutos, pero corre una vez al día de madrugada y el dato
	 * queda completo, que es lo que importa.
	 *
	 * Comprobado contra la API real el 11 de noviembre de 2026.
	 */
	private function software( string $ruta, string $clave, string $tipo ): array|WP_Error {
		$sitios = $this->sitios_cacheados();
		if ( is_wp_error( $sitios ) ) {
			return $sitios;
		}

		$filas  = [];
		$fallos = 0;

		foreach ( $sitios as $sitio ) {
			$entorno = (string) $sitio['entorno_id'];
			if ( '' === $entorno ) {
				continue;
			}

			$r = $this->http->get( '/sites/environments/' . rawurlencode( $entorno ) . '/' . $ruta );

			if ( is_wp_error( $r ) ) {
				// Un sitio que falle no debe tumbar la corrida entera: se anota
				// y se sigue. Peor que un dato incompleto es no tener ninguno.
				++$fallos;
				continue;
			}

			// Kinsta devuelve cuándo miró este sitio por última vez. Si viene en
			// 1970 es que nunca lo miró: la lista vacía no significa "sin
			// plugins", significa "sin datos". Hay que poder distinguirlo.
			$mirado = (string) ( $r['environment'][ $clave ]['last_updated_at'] ?? '' );
			$ts     = $mirado ? strtotime( $mirado ) : 0;

			if ( $ts > 86400 ) {
				$actual = $this->frescura[ (string) $sitio['sitio_id'] ] ?? '';
				$nuevo  = gmdate( 'Y-m-d H:i:s', $ts );
				if ( '' === $actual || $nuevo > $actual ) {
					$this->frescura[ (string) $sitio['sitio_id'] ] = $nuevo;
				}
			}

			foreach ( (array) ( $r['environment'][ $clave ]['items'] ?? [] ) as $item ) {
				$version    = (string) ( $item['version'] ?? '' );
				$disponible = (string) ( $item['update_version'] ?? '' );
				$estado     = strtolower( (string) ( $item['update'] ?? 'none' ) );

				// "Hay actualización" no viene como booleano: se deduce de que
				// update no sea "none" y de que exista una versión superior.
				$hay = 'none' !== $estado
					&& '' !== $disponible
					&& SWPK_Versiones::es_menor( $version, $disponible );

				$filas[] = [
					'sitio_id'           => (string) $sitio['sitio_id'],
					'sitio_nombre'       => (string) $sitio['nombre_visible'],
					'entorno_nombre'     => 'Live',
					'tipo'               => $tipo,
					'slug'               => (string) ( $item['name'] ?? '' ),
					'titulo'             => (string) ( $item['title'] ?? $item['name'] ?? '' ),
					'version'            => $version,
					'version_disponible' => $hay ? $disponible : '',
					'hay_actualizacion'  => $hay,
					'es_vulnerable'      => (bool) ( $item['is_version_vulnerable'] ?? false ),
					'auto_update'        => (string) ( $item['auto_update_type'] ?? '' ),
					'activo'             => 'active' === strtolower( (string) ( $item['status'] ?? '' ) ),
				];
			}
		}

		if ( $fallos && ! $filas ) {
			return new WP_Error(
				'swpk_sin_inventario',
				sprintf( 'No se pudo leer el inventario de ninguno de los %d sitios.', count( $sitios ) )
			);
		}

		if ( $fallos ) {
			SWPK_Eventos::registrar(
				'lectura_incompleta',
				'',
				sprintf( 'No se pudo leer %s de %d sitio(s). El inventario de esos quedó sin actualizar.', $tipo, $fallos ),
				'alto'
			);
		}

		return $filas;
	}
}
