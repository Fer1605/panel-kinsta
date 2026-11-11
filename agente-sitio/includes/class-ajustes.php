<?php
/** Ajustes del agente. */

defined( 'ABSPATH' ) || exit;

class SWA_Ajustes {

	public const OPCION = 'swa_ajustes';

	public static function predeterminados(): array {
		return [
			'panel_url'        => '',
			'clave_registro'   => '',
			'token'            => '',   // lo entrega el panel al registrarse
			'secreto'          => '',   // lo entrega el panel al registrarse
			'2fa_activo'       => 0,
			'2fa_roles'        => [ 'administrator', 'editor' ],
			'2fa_gracia_hasta' => 0,
			'cerrar_xmlrpc'    => 0,
		];
	}

	public static function todos(): array {
		$g = get_option( self::OPCION, [] );
		return wp_parse_args( is_array( $g ) ? $g : [], self::predeterminados() );
	}

	public static function obtener( string $clave, mixed $defecto = '' ): mixed {
		return self::todos()[ $clave ] ?? $defecto;
	}

	public static function guardar( array $nuevos ): void {
		$a = get_option( self::OPCION, [] );
		update_option( self::OPCION, array_merge( is_array( $a ) ? $a : [], $nuevos ), false );
	}

	public static function registrado(): bool {
		$a = self::todos();
		return '' !== $a['token'] && '' !== $a['secreto'] && '' !== $a['panel_url'];
	}
}
