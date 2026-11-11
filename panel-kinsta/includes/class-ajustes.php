<?php
/**
 * Ajustes del panel.
 *
 * La llave de la API se lee, en este orden:
 *   1. La constante SWPK_KINSTA_API_KEY de wp-config.php  ← lo correcto
 *   2. Una opción de la base de datos                      ← solo para pruebas
 *
 * Por qué importa el orden: una llave en la base de datos viaja en cada
 * respaldo, y los respaldos se mandan por correo y se dejan en carpetas. En
 * wp-config.php queda fuera del alcance de cualquier plugin que lea opciones y
 * fuera de los respaldos de base de datos. El panel avisa en pantalla si la
 * encuentra en la base.
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
		$ajustes   = wp_parse_args( is_array( $guardados ) ? $guardados : [], self::predeterminados() );

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

	public static function obtener( string $clave, mixed $defecto = '' ): mixed {
		$todos = self::todos();
		return $todos[ $clave ] ?? $defecto;
	}

	public static function guardar( array $nuevos ): void {
		$actuales = get_option( self::OPCION, [] );
		$actuales = is_array( $actuales ) ? $actuales : [];
		update_option( self::OPCION, array_merge( $actuales, $nuevos ), false );
	}

	/** ¿La llave está guardada en la base de datos en vez de en wp-config.php? */
	public static function llave_en_base_de_datos(): bool {
		if ( defined( 'SWPK_KINSTA_API_KEY' ) && '' !== (string) SWPK_KINSTA_API_KEY ) {
			return false;
		}
		$guardados = get_option( self::OPCION, [] );
		return is_array( $guardados ) && ! empty( $guardados['api_key'] );
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
