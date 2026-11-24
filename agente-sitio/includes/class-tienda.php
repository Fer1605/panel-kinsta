<?php
/**
 * Si este sitio vende de verdad, y cómo está montado el cobro.
 *
 * ─── Las tres preguntas, que no son la misma ────────────────────────────────
 *
 * «¿Tiene WooCommerce?» no es «¿está activo?» y no es «¿cobra?». Mezclarlas es
 * de donde salen las clasificaciones mal hechas:
 *
 *   - Instalado y apagado: hay sitios así en la cartera. No vende.
 *   - Activo con catálogo y sin pasarela encendida: es un cotizador. Se eligen
 *     productos, se pide presupuesto, nunca se paga.
 *   - Activo con transferencia bancaria o contra entrega: **sí vende**, aunque
 *     no haya ningún plugin de pasarela instalado, porque esas tres vienen
 *     dentro de WooCommerce. Desde el panel esto no se ve, y es la mitad de la
 *     razón por la que este archivo existe.
 *
 * ─── Y la pregunta que zanja ────────────────────────────────────────────────
 *
 * ¿Alguna vez cobró? Un pedido pagado con total mayor que cero y un método de
 * pago real significa que esta tienda está en alcance de PCI, digan lo que
 * digan los ajustes. Es la evidencia más fuerte y es una consulta barata.
 *
 * ─── Lo que NO se manda ─────────────────────────────────────────────────────
 *
 * Ningún dato de ningún cliente de la tienda. Se mandan cuentas, identificadores
 * de pasarela y sí/no. Y de la búsqueda de números de tarjeta se manda cuántos
 * se encontraron y dónde —tabla y columna—, nunca el número ni la fila.
 */

defined( 'ABSPATH' ) || exit;

class SWA_Tienda {

	private const CACHE = 'swa_tienda';

	/**
	 * Las tres pasarelas que vienen DENTRO de WooCommerce.
	 *
	 * No son plugins, así que el panel no las puede ver desde fuera. Y cobran
	 * dinero: una tienda que solo acepta contra entrega es un comercio
	 * electrónico igual.
	 */
	private const DEL_NUCLEO = [ 'bacs', 'cheque', 'cod' ];

	/**
	 * @return array<string,mixed>
	 */
	public static function medir( bool $completo = true ): array {
		$g = get_transient( self::CACHE );

		if ( is_array( $g ) ) {
			return $g;
		}

		if ( ! $completo ) {
			return [];
		}

		if ( ! self::hay_woo() ) {
			$r = [ 'woo_instalado' => self::instalado(), 'woo_activo' => false ];
			set_transient( self::CACHE, $r, 12 * HOUR_IN_SECONDS );

			return $r;
		}

		$r = [
			'woo_instalado'        => true,
			'woo_activo'           => true,
			'woo_version'          => defined( 'WC_VERSION' ) ? WC_VERSION : '',
			'pasarelas_encendidas' => [],
			'pasarelas_con_dinero' => [],
			'en_prueba'            => [],
			'catalogo_comprable'   => self::catalogo_comprable(),
			'checkout_publicado'   => self::checkout_publicado(),
			'cobro'                => self::pedidos_pagados(),
			'descargas'            => self::descargas(),
			'invitados'            => self::invitados(),
			'limpieza'             => self::limpieza(),
			'webhooks_sin_cifrar'  => self::webhooks(),
			'tarjetas'             => self::tarjetas(),
		];

		foreach ( self::pasarelas() as $id => $p ) {
			$r['pasarelas_encendidas'][] = $p['titulo'];

			if ( ! $p['solo_manual'] ) {
				$r['pasarelas_con_dinero'][] = $p['titulo'];
			} elseif ( in_array( $id, self::DEL_NUCLEO, true ) ) {
				// Transferencia, cheque y contra entrega cobran, aunque no sea
				// en línea. No ponerlas aquí sería clasificar como cotizador a
				// una tienda que sí vende.
				$r['pasarelas_con_dinero'][] = $p['titulo'];
			}

			if ( $p['prueba'] ) {
				$r['en_prueba'][] = $p['titulo'];
			}
		}

		set_transient( self::CACHE, $r, 12 * HOUR_IN_SECONDS );

		return $r;
	}

	public static function olvidar(): void {
		delete_transient( self::CACHE );
	}

	private static function instalado(): bool {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		foreach ( get_plugins() as $datos ) {
			if ( 'woocommerce' === ( $datos['TextDomain'] ?? '' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * ¿Está cargado AHORA?
	 *
	 * Es lo único que ve un WooCommerce cargado por un mu-plugin o por un
	 * cargador propio, que `active_plugins` no registra. Y en multisitio un
	 * plugin activado en toda la red tampoco aparece ahí.
	 */
	private static function hay_woo(): bool {
		return class_exists( 'WooCommerce' ) && function_exists( 'WC' );
	}

	/**
	 * Las pasarelas encendidas, con su identificador.
	 *
	 * `enabled === 'yes'` es la señal, no que el plugin esté activo: en la
	 * cartera hay un sitio con el plugin de una pasarela instalado y activo y
	 * la pasarela apagada en los ajustes.
	 *
	 * @return array<string,array{titulo:string,solo_manual:bool,prueba:bool}>
	 */
	private static function pasarelas(): array {
		$salida = [];

		foreach ( WC()->payment_gateways()->payment_gateways() as $g ) {
			if ( 'yes' !== $g->enabled ) {
				continue;
			}

			$id = (string) $g->id;

			// Cada pasarela nombra su propio ajuste de pruebas: no hay una API
			// para esto. Si no se reconoce, se dice que no se sabe en vez de
			// afirmar que está en vivo.
			$prueba = false;

			foreach ( [ 'testmode', 'sandbox', 'test_mode', 'environment' ] as $clave ) {
				$v = $g->settings[ $clave ] ?? null;

				if ( 'yes' === $v || 'sandbox' === $v || 'test' === $v || true === $v ) {
					$prueba = true;
					break;
				}
			}

			$salida[ $id ] = [
				'titulo'      => (string) ( $g->get_title() ?: $id ),
				'solo_manual' => in_array( $id, self::DEL_NUCLEO, true ),
				'prueba'      => $prueba,
			];
		}

		return $salida;
	}

	/**
	 * ¿Hay algún producto que se pueda comprar?
	 *
	 * Un producto publicado con el precio vacío NO es comprable —así lo
	 * resuelve el propio WooCommerce— y es la huella de un catálogo montado
	 * para cotizar.
	 */
	private static function catalogo_comprable(): int {
		global $wpdb;

		$tabla = $wpdb->prefix . 'wc_product_meta_lookup';

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $tabla ) ) !== $tabla ) {
			return -1; // no se sabe
		}

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tabla} WHERE min_price IS NOT NULL AND min_price > 0" );
	}

	private static function checkout_publicado(): bool {
		if ( ! function_exists( 'wc_get_page_id' ) ) {
			return false;
		}

		$id = (int) wc_get_page_id( 'checkout' );

		return $id > 0 && 'publish' === get_post_status( $id );
	}

	/**
	 * ¿Cuántos pedidos pagados de verdad?
	 *
	 * Se mira primero dónde viven los pedidos: desde hace unas versiones
	 * WooCommerce los guarda en su propia tabla y no en las entradas.
	 */
	private static function pedidos_pagados(): int {
		global $wpdb;

		if ( 'yes' === get_option( 'woocommerce_custom_orders_table_enabled' ) ) {
			$t = $wpdb->prefix . 'wc_orders';

			// Que la tabla exista no basta: hay que comprobar la COLUMNA.
			//
			// El 23 de noviembre de 2026 esto reventó en dos tiendas al
			// instalar el agente. Las dos tenían la tabla `wc_orders` —la
			// opción de HPOS encendida en algún momento— pero con un esquema
			// viejo, sin `date_paid_gmt`. La consulta moría con «Unknown
			// column», el error salía impreso en medio del reporte, y lo
			// único que salvó el registro fue que el fallo venía después de
			// lo importante.
			//
			// WooCommerce renombró esa columna por el camino, así que la
			// presencia de la tabla no dice nada del esquema que hay dentro.
			// Sin la columna se cae al conteo por entradas, que funciona en
			// cualquier versión.
			$tabla_ok = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) ) === $t;

			if ( $tabla_ok && $wpdb->get_results( "SHOW COLUMNS FROM {$t} LIKE 'date_paid_gmt'" ) ) {
				return (int) $wpdb->get_var(
					"SELECT COUNT(*) FROM {$t}
					  WHERE status IN ('wc-processing','wc-completed')
					    AND date_paid_gmt IS NOT NULL
					    AND total_amount > 0"
				);
			}
		}

		return (int) $wpdb->get_var(
			"SELECT COUNT(DISTINCT p.ID)
			   FROM {$wpdb->posts} p
			   JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_date_paid'
			  WHERE p.post_type = 'shop_order'
			    AND p.post_status IN ('wc-processing','wc-completed')
			    AND m.meta_value <> ''"
		);
	}

	/**
	 * Cómo se entregan los archivos que se compran.
	 *
	 * El método «redirección» lo dice la propia documentación de WooCommerce:
	 * cualquiera con la dirección accede al archivo, haya comprado o no.
	 *
	 * @return array<string,mixed>
	 */
	private static function descargas(): array {
		return [
			'metodo'        => (string) get_option( 'woocommerce_file_download_method', 'force' ),
			'respaldo'      => 'yes' === get_option( 'woocommerce_downloads_redirect_fallback_allowed' ),
			'pide_sesion'   => 'yes' === get_option( 'woocommerce_downloads_require_login' ),
			'nombre_con_hash' => 'yes' === get_option( 'woocommerce_downloads_add_hash_to_filename' ),
		];
	}

	/**
	 * ¿Se le pide el correo a quien compró sin cuenta antes de enseñarle su pedido?
	 *
	 * No tiene opción: es un filtro. Así que se evalúa en vez de buscarlo, que
	 * es lo único que dice la verdad — un plugin puede engancharse para forzar
	 * la verificación igual que para quitarla.
	 *
	 * @return array<string,mixed>
	 */
	private static function invitados(): array {
		$exige = (bool) apply_filters( 'woocommerce_order_email_verification_required', true, null, 'order-received' );

		return [
			'exige_correo' => $exige,
			'gracia'       => (int) apply_filters( 'woocommerce_order_email_verification_grace_period', 10 * MINUTE_IN_SECONDS ),
			'sin_cuenta'   => 'yes' === get_option( 'woocommerce_enable_guest_checkout', 'yes' ),
		];
	}

	/**
	 * La limpieza de datos personales.
	 *
	 * Lo que importa no es solo que esté configurada: es que la tarea siga
	 * viva. Una retención puesta con el cron muerto es peor que no tenerla,
	 * porque nadie va a volver a mirarla.
	 *
	 * @return array<string,mixed>
	 */
	private static function limpieza(): array {
		$puesta = false;

		foreach ( [ 'woocommerce_trash_pending_orders', 'woocommerce_trash_failed_orders', 'woocommerce_trash_cancelled_orders', 'woocommerce_anonymize_completed_orders', 'woocommerce_anonymize_refunded_orders', 'woocommerce_delete_inactive_accounts' ] as $o ) {
			$v = get_option( $o );

			if ( function_exists( 'wc_parse_relative_date_option' ) ) {
				$v = wc_parse_relative_date_option( $v );
			}

			if ( ! empty( $v['number'] ) ) {
				$puesta = true;
				break;
			}
		}

		return [
			'configurada' => $puesta,
			'cron_vivo'   => (bool) wp_next_scheduled( 'woocommerce_cleanup_personal_data' ),
			'borra_pedido' => 'yes' === get_option( 'woocommerce_erasure_request_removes_order_data' ),
		];
	}

	/** Webhooks activos que mandan datos sin cifrar. */
	private static function webhooks(): int {
		global $wpdb;

		$t = $wpdb->prefix . 'wc_webhooks';

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) ) !== $t ) {
			return 0;
		}

		return (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$t}
			  WHERE status = 'active'
			    AND delivery_url LIKE 'http://%'
			    AND delivery_url NOT LIKE 'http://localhost%'
			    AND delivery_url NOT LIKE 'http://127.%'"
		);
	}

	/**
	 * Buscar números de tarjeta guardados en texto plano.
	 *
	 * ─── Las reglas que no se negocian ──────────────────────────────────
	 *
	 * Esta comprobación puede CREAR el problema que busca si se hace mal. Así
	 * que:
	 *
	 *   - Nunca se guarda la coincidencia. Ni en la base, ni en un archivo, ni
	 *     en un correo. Se cuenta y se suelta. Guardarla sería crear un
	 *     almacén de datos de tarjeta nuevo, que es exactamente lo que estamos
	 *     buscando.
	 *   - No se usa REGEXP dentro del SELECT: el registro de consultas lentas
	 *     capturaría el patrón y, peor, la fila. Se pagina y se compara en PHP.
	 *   - Se valida con Luhn. Sin eso la señal es inservible: se llena de
	 *     números de pedido, de teléfono y de guía.
	 *   - Los números de prueba publicados se restan y se cuentan aparte.
	 *
	 * Y el límite, que hay que decir siempre: un resultado limpio significa
	 * «no se encontró ninguno donde se buscó, el día que se buscó». Nunca
	 * «no se guardan datos de tarjeta».
	 *
	 * @return array<string,mixed>
	 */
	private static function tarjetas(): array {
		global $wpdb;

		$de_prueba = [
			'4111111111111111', '4242424242424242', '5555555555554444',
			'378282246310005', '4000000000000002', '5105105105105100',
			'371449635398431', '6011111111111117',
		];

		// Las formas que tiene cada marca, ya sin separadores.
		$marcas = '/^(?:4\d{12}(?:\d{3})?|5[1-5]\d{14}|2(?:2[2-9]|[3-6]\d|7[01])\d{12}|3[47]\d{13}|6(?:011|5\d{2})\d{12})$/';

		// Y cómo se encuentra un candidato DENTRO del texto, con sus espacios o
		// guiones puestos.
		//
		// Aquí hubo un error que habría hecho inservible toda la comprobación:
		// el primer intento quitaba los espacios de TODO el texto y luego
		// buscaba la marca con límites de palabra. Pero quitar los espacios
		// pega el número a lo que tiene alrededor —«tarjeta: 4539 5781 0017
		// 6875 por» se vuelve «tarjeta:4539578100176875por»— y entonces no hay
		// límite de palabra que calce. El escáner contestaba «no se encontró
		// ninguna» con una tarjeta válida delante.
		//
		// Así que los separadores se quitan DESPUÉS, candidato por candidato.
		$candidatos = '/(?<![\d])(?:\d[ \-]?){12,19}(?![\d])/';

		$reales  = 0;
		$pruebas = 0;
		$donde   = [];

		$revisar = static function ( string $texto, string $sitio ) use ( &$reales, &$pruebas, &$donde, $marcas, $candidatos, $de_prueba ): void {
			if ( ! preg_match_all( $candidatos, $texto, $m ) ) {
				return;
			}

			foreach ( $m[0] as $crudo ) {
				$cand = preg_replace( '/[\s\-]/', '', $crudo );

				if ( ! preg_match( $marcas, (string) $cand ) || ! self::luhn( (string) $cand ) ) {
					continue;
				}

				if ( in_array( $cand, $de_prueba, true ) ) {
					++$pruebas;
					continue;
				}

				++$reales;
				$donde[ $sitio ] = true;
			}
		};

		// Las notas de pedido y el meta de pedido, que es donde caen de verdad.
		$lugares = [
			[ $wpdb->comments, 'comment_content', "comment_type = 'order_note'", 'notas de pedido' ],
			[ $wpdb->postmeta, 'meta_value', "meta_key LIKE '_%' AND meta_value <> ''", 'meta de pedido' ],
		];

		foreach ( $lugares as [ $tabla, $columna, $donde_sql, $etiqueta ] ) {
			$filas = $wpdb->get_col( "SELECT {$columna} FROM {$tabla} WHERE {$donde_sql} LIMIT 3000" );

			foreach ( (array) $filas as $f ) {
				$revisar( (string) $f, $etiqueta );
			}
		}

		return [
			'encontrados' => $reales,
			'de_prueba'   => $pruebas,
			'donde'       => array_keys( $donde ),
		];
	}

	/** El dígito de control que tiene toda tarjeta de verdad. */
	private static function luhn( string $numero ): bool {
		$suma = 0;
		$alt  = false;

		for ( $i = strlen( $numero ) - 1; $i >= 0; $i-- ) {
			$d = (int) $numero[ $i ];

			if ( $alt ) {
				$d *= 2;
				if ( $d > 9 ) {
					$d -= 9;
				}
			}

			$suma += $d;
			$alt   = ! $alt;
		}

		return 0 === $suma % 10;
	}
}
