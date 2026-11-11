<?php
/**
 * Vigilancia de usuarios administradores.
 *
 * El módulo que el jefe pidió con más detalle:
 *
 *   "Esta cuenta empezó con tres usuarios, perfecto. Esta cuenta, de la nada,
 *    subió a cinco usuarios. Que haya un panel que nos notifique: esta cuenta
 *    ahora tiene cinco usuarios, y nos diga qué cuenta fue y cuáles son los
 *    usuarios nuevos con su correo." — 03:37
 *
 *   "Yo lo que quiero son administradores, pero son los que tienen control.
 *    Porque los últimos malwares que nos han ingresado generan usuarios
 *    administradores normalmente." — 04:16
 *
 * Cómo funciona: la primera vez que un sitio reporta, lo que manda queda como
 * línea base y no se avisa de nada — avisar de 40 usuarios que ya existían no
 * es una alerta, es ruido. De ahí en adelante, cada reporte se compara con lo
 * guardado y lo que cambió se convierte en evento.
 *
 * Tres cosas disparan alerta:
 *   1. Apareció un administrador que ayer no estaba.
 *   2. Alguien que ya existía subió a administrador.
 *   3. El correo de un usuario nuevo está en un dominio que no es del cliente
 *      ni nuestro — el caso "@juanitoperezspam.com" que puso de ejemplo.
 */

defined( 'ABSPATH' ) || exit;

class SWPK_Usuarios {

	private const ROLES_CON_CONTROL = [ 'administrator', 'super_admin' ];

	/**
	 * @param array<int,array<string,mixed>> $reportados
	 * @return int Cuántos cambios se registraron.
	 */
	public static function procesar( string $sitio_url, array $reportados ): int {
		global $wpdb;

		$tabla = SWPK_Tablas::tabla( 'usuarios' );
		$ahora = current_time( 'mysql' );

		$previos = [];
		$filas   = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$tabla} WHERE sitio_url = %s", $sitio_url )
		) ?: [];
		foreach ( $filas as $f ) {
			$previos[ (int) $f->usuario_id ] = $f;
		}

		$primera_vez = ! $previos;
		$cambios     = 0;
		$vistos      = [];

		foreach ( $reportados as $u ) {
			$id = (int) ( $u['id'] ?? 0 );
			if ( ! $id ) {
				continue;
			}

			$vistos[] = $id;
			$roles    = array_map( 'strval', (array) ( $u['roles'] ?? [] ) );
			$correo   = sanitize_email( (string) ( $u['correo'] ?? '' ) );
			$login    = sanitize_text_field( (string) ( $u['login'] ?? '' ) );

			$fila = [
				'sitio_url'  => $sitio_url,
				'usuario_id' => $id,
				'login'      => $login,
				'correo'     => $correo,
				'nombre'     => sanitize_text_field( (string) ( $u['nombre'] ?? '' ) ),
				'roles'      => implode( ',', $roles ),
				'alta'       => self::fecha( (string) ( $u['alta'] ?? '' ) ),
				'dos_pasos'  => ! empty( $u['dos_pasos'] ) ? 1 : 0,
				'visto_en'   => $ahora,
			];

			$previo = $previos[ $id ] ?? null;

			if ( $previo ) {
				$wpdb->update( $tabla, $fila, [ 'id' => (int) $previo->id ] );

				$roles_antes = array_filter( explode( ',', (string) $previo->roles ) );

				if ( ! self::tiene_control( $roles_antes ) && self::tiene_control( $roles ) ) {
					SWPK_Eventos::registrar(
						SWPK_Eventos::ROL_SUBIO,
						self::sitio_id( $sitio_url ),
						sprintf(
							'%s (%s) subió a administrador. Antes era: %s',
							$login,
							$correo,
							implode( ', ', $roles_antes ) ?: 'sin rol'
						),
						'critico',
						'',
						[ 'sitio' => $sitio_url, 'antes' => $roles_antes, 'ahora' => $roles ]
					);
					++$cambios;
				}

				continue;
			}

			$wpdb->insert( $tabla, $fila );

			// En la primera corrida no se avisa: eso es la línea base.
			if ( $primera_vez ) {
				continue;
			}

			if ( self::tiene_control( $roles ) ) {
				$extrano = self::dominio_extrano( $correo, $sitio_url );

				SWPK_Eventos::registrar(
					SWPK_Eventos::ADMIN_NUEVO,
					self::sitio_id( $sitio_url ),
					sprintf(
						'Administrador nuevo en %s: %s (%s)%s',
						$sitio_url,
						$login,
						$correo,
						$extrano ? ' — correo de un dominio que no es del cliente ni nuestro' : ''
					),
					'critico',
					'',
					[ 'sitio' => $sitio_url, 'login' => $login, 'correo' => $correo, 'roles' => $roles ]
				);
				++$cambios;

				if ( $extrano ) {
					SWPK_Eventos::registrar(
						SWPK_Eventos::CORREO_EXTRANO,
						self::sitio_id( $sitio_url ),
						sprintf( '%s usa %s, que no es dominio del sitio ni nuestro', $login, self::dominio( $correo ) ),
						'critico',
						'',
						[ 'sitio' => $sitio_url, 'correo' => $correo ]
					);
					++$cambios;
				}
			}
		}

		// Usuarios que desaparecieron: también vale saberlo.
		foreach ( $previos as $id => $previo ) {
			if ( in_array( $id, $vistos, true ) ) {
				continue;
			}

			$wpdb->delete( $tabla, [ 'id' => (int) $previo->id ] );

			if ( ! $primera_vez && self::tiene_control( array_filter( explode( ',', (string) $previo->roles ) ) ) ) {
				SWPK_Eventos::registrar(
					'administrador_eliminado',
					self::sitio_id( $sitio_url ),
					sprintf( 'Se eliminó al administrador %s (%s) de %s', $previo->login, $previo->correo, $sitio_url ),
					'alto',
					'',
					[ 'sitio' => $sitio_url ]
				);
				++$cambios;
			}
		}

		return $cambios;
	}

	private static function tiene_control( array $roles ): bool {
		return (bool) array_intersect( self::ROLES_CON_CONTROL, array_map( 'strtolower', $roles ) );
	}

	private static function dominio( string $correo ): string {
		$partes = explode( '@', strtolower( trim( $correo ) ) );
		return 2 === count( $partes ) ? $partes[1] : '';
	}

	/**
	 * ¿El correo está en un dominio que no es del sitio ni nuestro?
	 *
	 * No es prueba de nada por sí solo —hay clientes que usan Gmail— pero
	 * combinado con "administrador nuevo aparecido de la nada" es exactamente
	 * el patrón que describió el jefe.
	 */
	private static function dominio_extrano( string $correo, string $sitio_url ): bool {
		$dominio = self::dominio( $correo );
		if ( '' === $dominio ) {
			return true;
		}

		$propios = SWPK_Ajustes::dominios_propios();

		$anfitrion = strtolower( (string) wp_parse_url( $sitio_url, PHP_URL_HOST ) );
		$anfitrion = preg_replace( '/^www\./', '', $anfitrion ) ?? $anfitrion;

		if ( '' !== $anfitrion ) {
			$propios[] = $anfitrion;
		}

		foreach ( $propios as $p ) {
			if ( '' !== $p && ( $dominio === $p || str_ends_with( $dominio, '.' . $p ) ) ) {
				return false;
			}
		}

		return true;
	}

	private static function fecha( string $crudo ): ?string {
		$crudo = trim( $crudo );
		if ( '' === $crudo ) {
			return null;
		}
		$ts = strtotime( $crudo );
		return $ts ? gmdate( 'Y-m-d H:i:s', $ts ) : null;
	}

	/** Enlaza el sitio reportado con el inventario de Kinsta, por dominio. */
	private static function sitio_id( string $sitio_url ): string {
		$anfitrion = strtolower( (string) wp_parse_url( $sitio_url, PHP_URL_HOST ) );
		$anfitrion = preg_replace( '/^www\./', '', $anfitrion ) ?? $anfitrion;

		foreach ( SWPK_Inventario::todos() as $s ) {
			$d = preg_replace( '/^www\./', '', strtolower( (string) $s->dominio ) ) ?? '';
			if ( '' !== $d && $d === $anfitrion ) {
				return (string) $s->sitio_id;
			}
		}

		return '';
	}

	/** @return array<int,object> */
	public static function del_sitio( string $sitio_url ): array {
		global $wpdb;
		$tabla = SWPK_Tablas::tabla( 'usuarios' );
		return $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$tabla} WHERE sitio_url = %s ORDER BY roles ASC, login ASC", $sitio_url )
		) ?: [];
	}

	public static function total_administradores(): int {
		global $wpdb;
		$tabla = SWPK_Tablas::tabla( 'usuarios' );
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tabla} WHERE roles LIKE '%administrator%'" );
	}
}
