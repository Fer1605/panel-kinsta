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
 * Y el reporte llega en el momento, no al día siguiente: el plugin del sitio
 * avisa en cuanto alguien se da de alta, cambia de rol o se borra. El reporte
 * diario quedó como red por debajo, para cuadrar lo que un aviso perdido se
 * haya saltado. Enterarse mañana de que anoche apareció un administrador es
 * enterarse de lo que ya pasó.
 *
 * Qué dispara alerta:
 *   1. Apareció un administrador que no estaba.
 *   2. Alguien que ya existía subió a administrador.
 *   3. Desapareció un administrador.
 *
 * Y de cada alta se dice el salto —"pasó de 3 a 5 administradores"— porque es
 * lo primero que se mira: una sola se revisa con calma, tres de golpe no.
 *
 * Las altas se avisan TODAS, no solo las sospechosas. Lo que cambia es el tono:
 * si el correo está en un dominio que no es del cliente ni nuestro —el caso
 * "@juanitoperezspam.com" que puso de ejemplo— sale como crítico; si no tiene
 * nada raro, sale igual pero diciendo que parece un alta normal. Un alta
 * legítima que nadie recuerda haber hecho es tan interesante como una
 * sospechosa, y esa diferencia la pone quien lee, no el panel.
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

		// Cuántos administradores había y cuántos hay. Es el dato que el jefe
		// pidió textualmente —"empezó con tres usuarios, de la nada subió a
		// cinco"— y es lo primero que se mira al leer la alerta: un alta sola
		// se revisa con calma, tres de golpe no.
		$admins_antes = 0;
		foreach ( $previos as $f ) {
			if ( self::tiene_control( array_filter( explode( ',', (string) $f->roles ) ) ) ) {
				++$admins_antes;
			}
		}

		$admins_ahora = 0;
		foreach ( $reportados as $u ) {
			if ( self::tiene_control( array_map( 'strval', (array) ( $u['roles'] ?? [] ) ) ) ) {
				++$admins_ahora;
			}
		}

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
							'%s (%s) subió a administrador. Antes era: %s. El sitio pasó de %d a %d administradores.',
							$login,
							$correo,
							implode( ', ', $roles_antes ) ?: 'sin rol',
							$admins_antes,
							$admins_ahora
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

				// Se avisa SIEMPRE que aparece un administrador, se vea
				// sospechoso o no. Un alta legítima que nadie recuerda haber
				// hecho es exactamente igual de interesante que una sospechosa:
				// la diferencia la pone quien lee, no el panel. Lo que sí
				// cambia es el tono, para que lo grave siga saltando a la vista
				// y no se pierda entre las altas normales.
				SWPK_Eventos::registrar(
					SWPK_Eventos::ADMIN_NUEVO,
					self::sitio_id( $sitio_url ),
					sprintf(
						'Administrador nuevo: %s (%s). El sitio pasó de %d a %d administradores. %s',
						$login,
						$correo,
						$admins_antes,
						$admins_ahora,
						self::veredicto( $correo, $sitio_url )
					),
					$extrano ? 'critico' : 'alto',
					'',
					[
						'sitio'        => $sitio_url,
						'login'        => $login,
						'correo'       => $correo,
						'roles'        => $roles,
						'admins_antes' => $admins_antes,
						'admins_ahora' => $admins_ahora,
						'sospechoso'   => $extrano,
					]
				);
				++$cambios;

				if ( $extrano ) {
					SWPK_Eventos::registrar(
						SWPK_Eventos::CORREO_EXTRANO,
						self::sitio_id( $sitio_url ),
						sprintf(
							'%s usa %s, que no es dominio del sitio, ni nuestro, ni de un proveedor de correo conocido',
							$login,
							self::dominio( $correo )
						),
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
					sprintf(
						'Se eliminó al administrador %s (%s). El sitio pasó de %d a %d administradores.',
						$previo->login,
						$previo->correo,
						$admins_antes,
						$admins_ahora
					),
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
	 * Los proveedores de correo de toda la vida.
	 *
	 * Media Guatemala administra su sitio desde un Gmail. Si un correo de Gmail
	 * contara como sospechoso, la mitad de las altas legítimas saldrían en rojo,
	 * y una alerta que se equivoca la mitad de las veces deja de leerse en una
	 * semana — que es la única forma real de que este módulo fracase.
	 *
	 * El caso que describió el jefe no era un Gmail: era "@juanitoperezspam.com",
	 * un dominio inventado que nadie había visto nunca. Eso es lo que se marca.
	 */
	private const PROVEEDORES_CONOCIDOS = [
		'gmail.com', 'googlemail.com', 'hotmail.com', 'hotmail.es', 'outlook.com',
		'outlook.es', 'live.com', 'msn.com', 'yahoo.com', 'yahoo.es', 'icloud.com',
		'me.com', 'proton.me', 'protonmail.com', 'aol.com', 'gmx.com', 'zoho.com',
	];

	public static function proveedor_conocido( string $correo ): bool {
		return in_array( self::dominio( $correo ), self::PROVEEDORES_CONOCIDOS, true );
	}

	/** ¿El correo está en un dominio del sitio o nuestro? */
	public static function dominio_de_casa( string $correo, string $sitio_url ): bool {
		$dominio = self::dominio( $correo );
		if ( '' === $dominio ) {
			return false;
		}

		$propios = SWPK_Ajustes::dominios_propios();

		$anfitrion = strtolower( (string) wp_parse_url( $sitio_url, PHP_URL_HOST ) );
		$anfitrion = preg_replace( '/^www\./', '', $anfitrion ) ?? $anfitrion;

		if ( '' !== $anfitrion ) {
			$propios[] = $anfitrion;
		}

		foreach ( $propios as $p ) {
			if ( '' !== $p && ( $dominio === $p || str_ends_with( $dominio, '.' . $p ) ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * ¿El correo está en un dominio que no se le reconoce a nadie?
	 *
	 * No es prueba de nada por sí solo, pero combinado con "administrador nuevo
	 * aparecido de la nada" es exactamente el patrón que describió el jefe.
	 */
	private static function dominio_extrano( string $correo, string $sitio_url ): bool {
		if ( '' === self::dominio( $correo ) ) {
			return true;
		}

		return ! self::dominio_de_casa( $correo, $sitio_url ) && ! self::proveedor_conocido( $correo );
	}

	/** Qué se revisó del correo de este usuario, dicho en una frase. */
	private static function veredicto( string $correo, string $sitio_url ): string {
		if ( self::dominio_de_casa( $correo, $sitio_url ) ) {
			return 'Su correo es del dominio del cliente o nuestro y no tiene nada raro, así que parece un alta normal. Vale confirmar que alguien lo dio de alta a propósito.';
		}

		if ( self::proveedor_conocido( $correo ) ) {
			return sprintf(
				'Su correo es un %s personal, que es normal en los clientes y no prueba nada por sí solo. Vale confirmar que alguien lo dio de alta a propósito.',
				self::dominio( $correo )
			);
		}

		return 'Su correo está en un dominio que no es del cliente, ni nuestro, ni de un proveedor conocido: ese es el patrón de los administradores que deja un malware. Hay que revisarlo ya.';
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
