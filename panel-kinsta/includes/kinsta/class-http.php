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
 * En todo el plugin solo debe aparecer wp_remote_get. Si alguna vez sale un
 * wp_remote_post, un wp_remote_request o un 'method' => 'PUT', es que alguien
 * agregó escritura.
 *
 * Y de las tres direcciones a las que el panel habla —api.kinsta.com,
 * wordfence.com y api.wordpress.org— la de Kinsta solo se arma en este archivo.
 * ────────────────────────────────────────────────────────────────────────────
 */

defined( 'ABSPATH' ) || exit;

class SWPK_Http {

	private const BASE    = 'https://api.kinsta.com/v2';
	private const TIMEOUT = 25;

	/** @var array<int,float> Momento de cada petición, para respetar el límite. */
	private static array $historial = [];

	private const POR_MINUTO = 100; // el límite real es 120; se deja holgura

	public function __construct( private string $api_key ) {}

	/**
	 * Frena si vamos a pasarnos del límite de Kinsta.
	 *
	 * Son 120 peticiones por minuto por empresa, y leer el inventario completo
	 * son dos peticiones por sitio: con 107 sitios, 214. Sin freno, a mitad de
	 * la corrida Kinsta empieza a devolver 429 y el inventario queda a medias
	 * sin que nadie se entere. Mejor que la corrida tarde dos minutos.
	 */
	private function esperar_si_hace_falta(): void {
		$ahora = microtime( true );

		self::$historial = array_values(
			array_filter( self::$historial, static fn( $t ) => $ahora - $t < 60 )
		);

		if ( count( self::$historial ) >= self::POR_MINUTO ) {
			$espera = 61 - ( $ahora - self::$historial[0] );
			if ( $espera > 0 ) {
				usleep( (int) ( $espera * 1000000 ) );
			}
			self::$historial = [];
		}

		self::$historial[] = microtime( true );
	}

	/**
	 * Una petición GET a la API de Kinsta.
	 *
	 * ─── Sobre los filtros que Kinsta documenta en el cuerpo de un GET ──────
	 *
	 * Los endpoints de plugins y temas documentan sus filtros (status, column,
	 * order_by) dentro del CUERPO de una petición GET. Eso no se puede hacer
	 * desde WordPress: su capa HTTP, al ver un GET, intenta convertir el cuerpo
	 * en parámetros de URL con http_build_query(), y si el cuerpo es una cadena
	 * JSON revienta con un error fatal. No lo ignora en silencio: tumba el
	 * proceso. Comprobado contra la API real el 11 de noviembre de 2026.
	 *
	 * Así que aquí no se manda cuerpo. El filtrado y el orden los hace el panel
	 * con los datos ya recibidos, que de todas formas era lo que había que hacer:
	 * pedir "solo los vulnerables" y recibir todo sin darse cuenta sería peor que
	 * no filtrar nunca.
	 *
	 * @param string              $ruta  Ruta relativa, por ejemplo "/sites".
	 * @param array<string,mixed> $query Parámetros de la URL.
	 */
	public function get( string $ruta, array $query = [] ): array|WP_Error {
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

		$this->esperar_si_hace_falta();

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
