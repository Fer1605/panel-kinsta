<?php
/**
 * Cliente HTTP de Kinsta. SOLO GET.
 *
 * ─── Esto es la garantía de "solo lectura" ───────────────────────────────────
 *
 * Kinsta no vende llaves de solo lectura: al crear una llave solo se elige
 * nombre y caducidad, no hay alcances, y la llave hereda los permisos de quien
 * la creó. Tampoco existe un rol de MyKinsta que vea los sitios sin poder
 * administrarlos. Verificado en la documentación de Kinsta el 10/11/2026.
 *
 * Entonces la restricción vive aquí. Esta clase expone un único método público
 * de red —get()— y por dentro llama a wp_remote_get, que fija el método GET.
 * No hay forma de pasarle otro verbo: no es un parámetro. Para que el panel
 * pudiera actualizar un plugin, reiniciar PHP o crear un usuario en Kinsta,
 * alguien tendría que agregar código nuevo a este archivo, y eso se ve en el
 * historial del repositorio.
 *
 * Auditoría de un minuto:
 *     grep -rn "wp_remote_" panel-kinsta/
 * Solo debe aparecer wp_remote_get, y solo en este archivo.
 * ────────────────────────────────────────────────────────────────────────────
 */

defined( 'ABSPATH' ) || exit;

class SWPK_Http {

	private const BASE    = 'https://api.kinsta.com/v2';
	private const TIMEOUT = 25;

	public function __construct( private string $api_key ) {}

	/**
	 * Una petición GET a la API de Kinsta.
	 *
	 * @param string              $ruta    Ruta relativa, por ejemplo "/sites".
	 * @param array<string,mixed> $query   Parámetros de la URL.
	 * @param array<string,mixed> $filtros Filtros que Kinsta documenta en el
	 *                                     cuerpo de un GET (sí, en el cuerpo).
	 *                                     Varias librerías HTTP descartan el
	 *                                     cuerpo en un GET sin avisar, así que
	 *                                     quien llame a esto debe poder filtrar
	 *                                     también del lado del panel.
	 */
	public function get( string $ruta, array $query = [], array $filtros = [] ): array|WP_Error {
		if ( '' === trim( $this->api_key ) ) {
			return new WP_Error( 'swpk_sin_llave', 'No hay llave de la API de Kinsta configurada.' );
		}

		$url = self::BASE . '/' . ltrim( $ruta, '/' );
		if ( $query ) {
			$url = add_query_arg( array_map( 'rawurlencode', array_map( 'strval', $query ) ), $url );
		}

		$args = [
			'timeout'     => self::TIMEOUT,
			'redirection' => 2,
			'headers'     => [
				'Authorization' => 'Bearer ' . $this->api_key,
				'Accept'        => 'application/json',
			],
			'user-agent'  => 'PanelKinsta/' . SWPK_VERSION . '; +https://github.com/Fer1605/panel-kinsta',
		];

		if ( $filtros ) {
			$args['headers']['Content-Type'] = 'application/json';
			$args['body']                    = wp_json_encode( $filtros );
		}

		$respuesta = wp_remote_get( $url, $args );

		if ( is_wp_error( $respuesta ) ) {
			return $respuesta;
		}

		$codigo = (int) wp_remote_retrieve_response_code( $respuesta );
		$cuerpo = (string) wp_remote_retrieve_body( $respuesta );

		if ( 429 === $codigo ) {
			return new WP_Error(
				'swpk_limite',
				'Kinsta devolvió 429: se pasó el límite de 120 peticiones por minuto. Hay que espaciar las consultas.'
			);
		}

		if ( 401 === $codigo || 403 === $codigo ) {
			return new WP_Error(
				'swpk_sin_permiso',
				sprintf(
					'Kinsta devolvió %d en %s. La llave no alcanza ese endpoint: tiene que generarla un usuario con rol suficiente (owner, administrator o developer de la empresa).',
					$codigo,
					$ruta
				)
			);
		}

		if ( $codigo < 200 || $codigo >= 300 ) {
			return new WP_Error(
				'swpk_http_' . $codigo,
				sprintf( 'Kinsta devolvió %d en %s: %s', $codigo, $ruta, wp_strip_all_tags( substr( $cuerpo, 0, 500 ) ) )
			);
		}

		$datos = json_decode( $cuerpo, true );
		if ( ! is_array( $datos ) ) {
			return new WP_Error( 'swpk_json', 'La respuesta de Kinsta no es JSON válido.' );
		}

		return $datos;
	}
}
