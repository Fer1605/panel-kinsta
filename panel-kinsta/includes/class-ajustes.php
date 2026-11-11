<?php
/**
 * Ajustes del panel.
 *
 * ─── Dónde viven las llaves ─────────────────────────────────────────────────
 *
 * Se pueden guardar desde el panel, sin tocar archivos. El problema de guardar
 * una llave en la base de datos es que la base viaja entera dentro de cada
 * respaldo, y los respaldos se mandan por correo y se dejan en carpetas: quien
 * consiga un respaldo se lleva las llaves.
 *
 * Por eso aquí no se guardan en claro: se cifran con AES-256-GCM usando una
 * clave derivada de las claves de seguridad de este WordPress, que viven en
 * wp-config.php y NO están en la base de datos. Resultado: un respaldo de base
 * de datos, por sí solo, no sirve para nada. Hacen falta las dos cosas.
 *
 * Quien prefiera no guardarlas ni cifradas puede definirlas como constantes en
 * wp-config.php (SWPK_KINSTA_API_KEY, SWPK_KINSTA_COMPANY_ID,
 * SWPK_WORDFENCE_KEY) y esas mandan sobre lo que haya en la base.
 */

defined( 'ABSPATH' ) || exit;

class SWPK_Ajustes {

	public const OPCION = 'swpk_ajustes';

	public static function predeterminados(): array {
		return [
			'modo'              => 'demo',   // demo | api
			'company_id'        => '',
			'api_key'           => '',
			'wordfence_key'     => '',
			'correo_soporte'    => '',
			'correo_ventas'     => '',
			'dominios_propios'  => 'solucionweb.com',
			'etiqueta_desarrollo' => '',
			'etiqueta_auto'       => '',
			'etiqueta_no_update'  => '',
		];
	}

	public static function todos(): array {
		$guardados = get_option( self::OPCION, [] );
		$guardados = is_array( $guardados ) ? $guardados : [];

		foreach ( [ 'api_key', 'wordfence_key' ] as $secreto ) {
			if ( ! empty( $guardados[ $secreto ] ) ) {
				$guardados[ $secreto ] = self::descifrar( (string) $guardados[ $secreto ] );
			}
		}

		$ajustes = wp_parse_args( $guardados, self::predeterminados() );

		// La constante siempre manda sobre la base de datos.
		if ( defined( 'SWPK_KINSTA_API_KEY' ) && '' !== (string) SWPK_KINSTA_API_KEY ) {
			$ajustes['api_key'] = (string) SWPK_KINSTA_API_KEY;
		}
		if ( defined( 'SWPK_KINSTA_COMPANY_ID' ) && '' !== (string) SWPK_KINSTA_COMPANY_ID ) {
			$ajustes['company_id'] = (string) SWPK_KINSTA_COMPANY_ID;
		}
		if ( defined( 'SWPK_WORDFENCE_KEY' ) && '' !== (string) SWPK_WORDFENCE_KEY ) {
			$ajustes['wordfence_key'] = (string) SWPK_WORDFENCE_KEY;
		}

		return $ajustes;
	}

	private const MARCA = 'swpk1:';

	/**
	 * La clave con la que se cifra, derivada de las claves de seguridad de este
	 * WordPress. Están en wp-config.php, nunca en la base de datos: ese es el
	 * punto de todo esto.
	 */
	private static function clave_de_cifrado(): string {
		$material = '';
		foreach ( [ 'AUTH_KEY', 'SECURE_AUTH_KEY', 'AUTH_SALT', 'SECURE_AUTH_SALT' ] as $c ) {
			if ( defined( $c ) ) {
				$material .= (string) constant( $c );
			}
		}

		// Sin salts no hay cifrado posible. Pasa en instalaciones mal hechas.
		return '' === $material ? '' : hash( 'sha256', 'swpk|' . $material, true );
	}

	public static function cifrar( string $texto ): string {
		$clave = self::clave_de_cifrado();

		if ( '' === $texto || '' === $clave || ! function_exists( 'openssl_encrypt' ) ) {
			return $texto;
		}

		$iv  = random_bytes( 12 );
		$tag = '';

		$cifrado = openssl_encrypt( $texto, 'aes-256-gcm', $clave, OPENSSL_RAW_DATA, $iv, $tag );
		if ( false === $cifrado ) {
			return $texto;
		}

		return self::MARCA . base64_encode( $iv . $tag . $cifrado );
	}

	public static function descifrar( string $guardado ): string {
		if ( ! str_starts_with( $guardado, self::MARCA ) ) {
			return $guardado; // guardado antes de que esto existiera
		}

		$clave = self::clave_de_cifrado();
		$bruto = base64_decode( substr( $guardado, strlen( self::MARCA ) ), true );

		if ( '' === $clave || false === $bruto || strlen( $bruto ) < 29 ) {
			return '';
		}

		$claro = openssl_decrypt(
			substr( $bruto, 28 ),
			'aes-256-gcm',
			$clave,
			OPENSSL_RAW_DATA,
			substr( $bruto, 0, 12 ),
			substr( $bruto, 12, 16 )
		);

		return false === $claro ? '' : $claro;
	}

	/** ¿Están las llaves protegidas, o hay alguna en claro de antes? */
	public static function llaves_en_claro(): array {
		$g = get_option( self::OPCION, [] );
		$g = is_array( $g ) ? $g : [];

		$sueltas = [];
		foreach ( [ 'api_key' => 'la de Kinsta', 'wordfence_key' => 'la de Wordfence' ] as $campo => $nombre ) {
			$v = (string) ( $g[ $campo ] ?? '' );
			if ( '' !== $v && ! str_starts_with( $v, self::MARCA ) ) {
				$sueltas[] = $nombre;
			}
		}

		return $sueltas;
	}

	public static function cifrado_disponible(): bool {
		return '' !== self::clave_de_cifrado() && function_exists( 'openssl_encrypt' );
	}

	public static function obtener( string $clave, mixed $defecto = '' ): mixed {
		$todos = self::todos();
		return $todos[ $clave ] ?? $defecto;
	}

	public static function guardar( array $nuevos ): void {
		$actuales = get_option( self::OPCION, [] );
		$actuales = is_array( $actuales ) ? $actuales : [];

		foreach ( [ 'api_key', 'wordfence_key' ] as $secreto ) {
			if ( isset( $nuevos[ $secreto ] ) && '' !== $nuevos[ $secreto ] ) {
				$nuevos[ $secreto ] = self::cifrar( (string) $nuevos[ $secreto ] );
			}
		}

		update_option( self::OPCION, array_merge( $actuales, $nuevos ), false );
	}

	/** Cifra las llaves que quedaron guardadas en claro de antes. */
	public static function cifrar_lo_viejo(): int {
		if ( ! self::cifrado_disponible() ) {
			return 0;
		}

		$g = get_option( self::OPCION, [] );
		$g = is_array( $g ) ? $g : [];
		$n = 0;

		foreach ( [ 'api_key', 'wordfence_key' ] as $secreto ) {
			$v = (string) ( $g[ $secreto ] ?? '' );
			if ( '' !== $v && ! str_starts_with( $v, self::MARCA ) ) {
				$g[ $secreto ] = self::cifrar( $v );
				++$n;
			}
		}

		if ( $n ) {
			update_option( self::OPCION, $g, false );
		}

		return $n;
	}

	/**
	 * ¿Hay alguna llave guardada en la base de datos en vez de en wp-config.php?
	 *
	 * Vale para las dos, no solo la de Kinsta: los términos de Wordfence piden
	 * expresamente mantener su llave confidencial, y una llave en la base de
	 * datos viaja dentro de cada respaldo.
	 *
	 * @return array<int,string> Las que están mal guardadas, con nombre legible.
	 */
	public static function llaves_en_base_de_datos(): array {
		$guardados = get_option( self::OPCION, [] );
		$guardados = is_array( $guardados ) ? $guardados : [];

		$sueltas = [];

		if ( ! empty( $guardados['api_key'] ) && ! ( defined( 'SWPK_KINSTA_API_KEY' ) && '' !== (string) SWPK_KINSTA_API_KEY ) ) {
			$sueltas[] = 'la de Kinsta';
		}

		if ( ! empty( $guardados['wordfence_key'] ) && ! ( defined( 'SWPK_WORDFENCE_KEY' ) && '' !== (string) SWPK_WORDFENCE_KEY ) ) {
			$sueltas[] = 'la de Wordfence';
		}

		return $sueltas;
	}

	public static function llave_en_base_de_datos(): bool {
		return [] !== self::llaves_en_base_de_datos();
	}

	public static function en_modo_demo(): bool {
		$a = self::todos();
		return 'demo' === $a['modo'] || '' === $a['api_key'] || '' === $a['company_id'];
	}

	public static function dominios_propios(): array {
		$crudo = (string) self::obtener( 'dominios_propios', 'solucionweb.com' );
		$lista = array_filter( array_map( 'trim', explode( ',', $crudo ) ) );
		return array_map( 'strtolower', $lista );
	}
}
