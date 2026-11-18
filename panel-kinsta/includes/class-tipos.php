<?php
/**
 * Decidir si un sitio es una tienda, un cotizador o solo informativo.
 *
 * ─── De dónde sale esto ─────────────────────────────────────────────────────
 *
 * El jefe pidió normas distintas según el tipo de sitio:
 *
 *   «hay normas web. Cuando tú haces un e-commerce, en el e-commerce te piden
 *    que para cobrar se cumplan ciertas normas. Esas normas hay que revisarlas:
 *    si es un e-commerce, qué normas deberíamos tener; si es un sitio
 *    informativo, qué normas deberíamos tener»                       [01:24]
 *
 * Y la regla para distinguirlos la propuso Fer: tiene WooCommerce activo, es
 * tienda; no lo tiene, es informativo; y si lo tiene apagado o sin método de
 * pago, se quedó siendo informativo.
 *
 * ─── Los dos huecos de esa regla ────────────────────────────────────────────
 *
 * El fondo es correcto, pero medida contra la cartera real la regla tiene dos
 * agujeros, y los dos importan:
 *
 * 1. WooCommerce trae TRES pasarelas dentro del núcleo que no son plugins:
 *    transferencia bancaria, cheque y contra entrega. El panel no las ve. Una
 *    tienda puede estar cobrando por depósito con cero plugins de pago
 *    instalados. Y una tienda que solo cobra contra entrega SÍ es un comercio
 *    electrónico: «no tiene pasarela en línea» no es «no vende».
 *
 * 2. Un plugin de pasarela activo no es una pasarela encendida. En la cartera
 *    hay un sitio con el plugin de Cybersource instalado y activo en cero.
 *
 * Por eso la decisión va en tres capas, de la más barata a la más certera, y
 * cada sitio guarda POR QUÉ quedó donde quedó.
 *
 * ─── Las tres capas ─────────────────────────────────────────────────────────
 *
 * Capa 1 — el panel, hoy, sin código nuevo. La foto diaria de plugins ya trae
 * el `slug` y si está activo. Da una primera clasificación de los 96 sitios con
 * inventario, al instante.
 *
 * Capa 2 — el agente lee los ajustes de WooCommerce. Cierra los dos huecos:
 * qué pasarelas están ENCENDIDAS (no instaladas), cuáles de ellas cobran
 * dinero aunque no sea en línea, y si el catálogo tiene productos comprables.
 *
 * Capa 3 — la que no admite discusión: ¿alguna vez cobró? Un pedido pagado con
 * total mayor que cero significa que esa tienda está en alcance de PCI, digan
 * lo que digan los ajustes.
 */

defined( 'ABSPATH' ) || exit;

class SWPK_Tipos {

	public const TIENDA      = 'tienda';
	public const COTIZADOR   = 'cotizador';
	public const INFORMATIVO = 'informativo';
	public const SIN_DATO    = 'sin_dato';

	/**
	 * Trozos de nombre que delatan un plugin de pasarela de pago.
	 *
	 * Incluye las de Guatemala, que no aparecen en ninguna lista de fuera:
	 * QPayPro, Serfinsa, Recurrente, N1co, PowerTranz y Visanet. Salieron de
	 * mirar qué hay instalado de verdad en la cartera.
	 */
	private const PASARELAS = [
		'stripe', 'paypal', 'cybersource', 'powertranz', 'qpaypro', 'serfinsa',
		'recurrente', 'n1co', 'applepay', 'googlepay', 'mercadopago', 'visanet',
		'neonet', 'conekta', 'redsys', 'payu', 'woocommerce-payments', 'square',
		'authorize', 'braintree', 'klarna', 'mollie', 'bac-', 'gateway',
	];

	/** @return array<string,string> */
	public static function nombres(): array {
		return [
			self::TIENDA      => 'Tienda',
			self::COTIZADOR   => 'Cotizador',
			self::INFORMATIVO => 'Informativo',
			self::SIN_DATO    => 'Sin clasificar',
		];
	}

	public static function nombre( string $tipo ): string {
		return self::nombres()[ $tipo ] ?? 'Sin clasificar';
	}

	/**
	 * Clasificar toda la cartera con lo que el panel ya tiene.
	 *
	 * @return array<string,int> cuántos quedaron en cada tipo
	 */
	public static function clasificar_todos(): array {
		$cuenta = [ self::TIENDA => 0, self::COTIZADOR => 0, self::INFORMATIVO => 0, self::SIN_DATO => 0 ];

		foreach ( SWPK_Inventario::todos() as $sitio ) {
			$r = self::clasificar( (string) $sitio->sitio_id );
			++$cuenta[ $r['tipo'] ];
		}

		return $cuenta;
	}

	/**
	 * Decidir el tipo de un sitio y guardarlo.
	 *
	 * @return array{tipo:string,motivo:string}
	 */
	public static function clasificar( string $sitio_id ): array {
		$r = self::decidir( $sitio_id );

		global $wpdb;

		// El motivo se recorta a la medida de la columna.
		//
		// Esto no es prudencia de más: con MySQL en modo estricto, un valor más
		// largo que el VARCHAR no recorta nada — rechaza el UPDATE ENTERO, en
		// silencio. Pasó: el motivo del cotizador medía 210 caracteres, la
		// columna son 191, y veintiún sitios se quedaban sin clasificar sin que
		// nada lo dijera. La función devolvía «cotizador» y la base guardaba
		// «sin dato».
		$wpdb->update(
			SWPK_Tablas::tabla( 'sitios' ),
			[
				'tipo_sitio'  => $r['tipo'],
				'tipo_motivo' => mb_substr( $r['motivo'], 0, 188 ),
			],
			[ 'sitio_id' => $sitio_id ]
		);

		return $r;
	}

	/**
	 * @return array{tipo:string,motivo:string}
	 */
	private static function decidir( string $sitio_id ): array {
		// ── Capa 3 primero: si cobró, no hay nada que discutir ──────────
		$tienda = SWPK_Tienda::de( $sitio_id );

		if ( ! empty( $tienda['cobro'] ) ) {
			return [
				'tipo'   => self::TIENDA,
				'motivo' => sprintf( 'Tiene %d pedido(s) pagado(s) con cobro real.', (int) $tienda['cobro'] ),
			];
		}

		// ── Capa 2: los ajustes, que el agente leyó desde dentro ────────
		if ( isset( $tienda['pasarelas_encendidas'] ) ) {
			$con_dinero = (array) ( $tienda['pasarelas_con_dinero'] ?? [] );

			if ( $con_dinero ) {
				return [
					'tipo'   => self::TIENDA,
					'motivo' => sprintf( 'Tiene pasarela(s) encendida(s) que cobran: %s.', implode( ', ', $con_dinero ) ),
				];
			}

			if ( ! empty( $tienda['woo_activo'] ) ) {
				return [
					'tipo'   => self::COTIZADOR,
					'motivo' => 'WooCommerce está activo pero no hay ninguna pasarela encendida que cobre, así que no se puede pagar.',
				];
			}

			return [ 'tipo' => self::INFORMATIVO, 'motivo' => 'El agente no encontró WooCommerce activo.' ];
		}

		// ── Capa 1: la foto de plugins. Lo que hay sin agente ───────────
		$foto = self::foto( $sitio_id );

		if ( ! $foto ) {
			return [ 'tipo' => self::SIN_DATO, 'motivo' => sprintf( '%s todavía no ha escaneado este sitio.', SWPK_Admin::proveedor() ) ];
		}

		$woo = null;

		foreach ( $foto as $f ) {
			if ( 'woocommerce' === $f->slug && 'plugin' === $f->tipo ) {
				$woo = $f;
				break;
			}
		}

		if ( ! $woo ) {
			return [ 'tipo' => self::INFORMATIVO, 'motivo' => 'No tiene WooCommerce instalado.' ];
		}

		if ( ! (int) $woo->activo ) {
			return [ 'tipo' => self::INFORMATIVO, 'motivo' => 'Tiene WooCommerce instalado pero apagado.' ];
		}

		$pasarelas = [];

		foreach ( $foto as $f ) {
			if ( ! (int) $f->activo ) {
				continue;
			}

			foreach ( self::PASARELAS as $p ) {
				if ( str_contains( strtolower( (string) $f->slug ), $p ) ) {
					$pasarelas[] = (string) ( $f->titulo ?: $f->slug );
					break;
				}
			}
		}

		if ( $pasarelas ) {
			return [
				'tipo'   => self::TIENDA,
				'motivo' => sprintf( 'WooCommerce activo y plugin(s) de pasarela activo(s): %s.', implode( ', ', array_slice( $pasarelas, 0, 3 ) ) ),
			];
		}

		// Sin agente no se puede saber si cobra por transferencia o contra
		// entrega, que son del núcleo de WooCommerce y no plugins. Decir
		// «cotizador» sería afirmar algo que no se comprobó.
		return [
			'tipo'   => self::COTIZADOR,
			'motivo' => 'WooCommerce activo y ningún plugin de pasarela. Puede cobrar por transferencia o contra entrega, que vienen dentro de WooCommerce: hace falta el agente para saberlo.',
		];
	}

	/** @return array<int,object> */
	private static function foto( string $sitio_id ): array {
		global $wpdb;
		$tabla = SWPK_Tablas::tabla( 'fotos' );

		$fecha = $wpdb->get_var( $wpdb->prepare( "SELECT MAX(fecha) FROM {$tabla} WHERE sitio_id = %s", $sitio_id ) );

		if ( ! $fecha ) {
			return [];
		}

		return $wpdb->get_results(
			$wpdb->prepare( "SELECT slug, titulo, tipo, activo FROM {$tabla} WHERE sitio_id = %s AND fecha = %s", $sitio_id, $fecha )
		) ?: [];
	}

	/**
	 * ¿Esta norma aplica a un sitio de este tipo?
	 *
	 * Vacío quiere decir todos, igual que `aplica_a`. Y ojo: `aplica_a` y
	 * `aplica_tipo` son dos ejes distintos y no hay que revolverlos. El
	 * primero son las etiquetas del contrato de mantenimiento —desarrollo,
	 * auto update— y el segundo es qué hace el sitio. Un sitio puede ser
	 * `auto_update` Y tienda a la vez.
	 */
	public static function aplica( object $norma, string $tipo ): bool {
		$a = trim( (string) ( $norma->aplica_tipo ?? '' ) );

		if ( '' === $a ) {
			return true;
		}

		// Un sitio sin clasificar no se libra de una norma de tienda por no
		// saber qué es: queda «sin dato», que es lo honesto.
		if ( self::SIN_DATO === $tipo ) {
			return true;
		}

		return in_array( $tipo, array_map( 'trim', explode( ',', $a ) ), true );
	}
}
