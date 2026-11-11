<?php
/**
 * Los eventos: lo que cambió de un día para otro.
 *
 * "Mira, en esta corrida del día pasó esto" (04:34). Kinsta no guarda historial
 * consultable, así que la historia la construye el panel comparando la foto de
 * hoy con la de ayer. Antes de la primera foto no hay nada, y eso hay que
 * decirlo en pantalla en vez de mostrar un cero que parece un dato.
 */

defined( 'ABSPATH' ) || exit;

class SWPK_Eventos {

	public const ACTUALIZADO     = 'plugin_actualizado';
	public const PENDIENTE       = 'actualizacion_pendiente';
	public const VULNERABLE      = 'vulnerabilidad_detectada';
	public const RESUELTA        = 'vulnerabilidad_resuelta';
	public const NUEVO_PLUGIN    = 'plugin_instalado';
	public const PLUGIN_QUITADO  = 'plugin_desinstalado';
	public const ADMIN_NUEVO     = 'administrador_nuevo';
	public const ROL_SUBIO       = 'usuario_subio_de_rol';
	public const CORREO_EXTRANO  = 'correo_de_dominio_extrano';
	public const SITIO_CAIDO     = 'sitio_caido';
	public const SITIO_REPUESTO  = 'sitio_repuesto';

	public static function etiquetas(): array {
		return [
			self::ACTUALIZADO    => 'Se actualizó',
			self::PENDIENTE      => 'Actualización pendiente',
			self::VULNERABLE     => 'Vulnerabilidad detectada',
			self::RESUELTA       => 'Vulnerabilidad resuelta',
			self::NUEVO_PLUGIN   => 'Plugin instalado',
			self::PLUGIN_QUITADO => 'Plugin desinstalado',
			self::ADMIN_NUEVO    => 'Administrador nuevo',
			self::ROL_SUBIO      => 'Subió de rol',
			self::CORREO_EXTRANO => 'Correo de dominio extraño',
			self::SITIO_CAIDO    => 'Sitio caído',
			self::SITIO_REPUESTO => 'Sitio repuesto',
		];
	}

	public static function registrar(
		string $tipo,
		string $sitio_id,
		string $titulo,
		string $severidad = 'info',
		string $slug = '',
		array $detalle = []
	): void {
		global $wpdb;

		$wpdb->insert(
			SWPK_Tablas::tabla( 'eventos' ),
			[
				'ocurrio_en' => current_time( 'mysql' ),
				'sitio_id'   => $sitio_id,
				'tipo'       => $tipo,
				'severidad'  => $severidad,
				'slug'       => $slug,
				'titulo'     => $titulo,
				'detalle'    => $detalle ? wp_json_encode( $detalle ) : null,
			]
		);
	}

	/**
	 * Compara la foto de hoy con la anterior y registra los cambios.
	 *
	 * Esto es lo que convierte un inventario en una historia: sin la
	 * comparación, el panel solo sabría decir cómo están las cosas hoy, y lo
	 * que el jefe pidió es "este mes actualizamos seis veces" (01:18).
	 */
	public static function comparar_fotos( string $fecha_hoy ): int {
		global $wpdb;
		$tabla = SWPK_Tablas::tabla( 'fotos' );

		$fecha_previa = $wpdb->get_var(
			$wpdb->prepare( "SELECT MAX(fecha) FROM {$tabla} WHERE fecha < %s", $fecha_hoy )
		);

		if ( ! $fecha_previa ) {
			return 0; // Primera corrida: no hay con qué comparar.
		}

		$hoy    = self::indexar( $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$tabla} WHERE fecha = %s", $fecha_hoy ) ) ?: [] );
		$previo = self::indexar( $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$tabla} WHERE fecha = %s", $fecha_previa ) ) ?: [] );

		$n = 0;

		foreach ( $hoy as $clave => $fila ) {
			if ( ! isset( $previo[ $clave ] ) ) {
				self::registrar(
					self::NUEVO_PLUGIN,
					$fila->sitio_id,
					sprintf( '%s %s se instaló', $fila->titulo, $fila->version ),
					'info',
					$fila->slug
				);
				++$n;
				continue;
			}

			$antes = $previo[ $clave ];

			if ( $antes->version !== $fila->version && SWPK_Versiones::es_menor( $antes->version, $fila->version ) ) {
				self::registrar(
					self::ACTUALIZADO,
					$fila->sitio_id,
					sprintf( '%s pasó de %s a %s', $fila->titulo, $antes->version, $fila->version ),
					'ok',
					$fila->slug,
					[ 'de' => $antes->version, 'a' => $fila->version ]
				);
				++$n;
			}

			if ( ! $antes->es_vulnerable && $fila->es_vulnerable ) {
				self::registrar(
					self::VULNERABLE,
					$fila->sitio_id,
					sprintf( '%s %s tiene una vulnerabilidad conocida', $fila->titulo, $fila->version ),
					'critico',
					$fila->slug
				);
				++$n;
			}

			if ( $antes->es_vulnerable && ! $fila->es_vulnerable ) {
				self::registrar(
					self::RESUELTA,
					$fila->sitio_id,
					sprintf( '%s ya no está vulnerable (%s)', $fila->titulo, $fila->version ),
					'ok',
					$fila->slug
				);
				++$n;
			}
		}

		foreach ( $previo as $clave => $fila ) {
			if ( ! isset( $hoy[ $clave ] ) ) {
				self::registrar(
					self::PLUGIN_QUITADO,
					$fila->sitio_id,
					sprintf( '%s se desinstaló', $fila->titulo ),
					'info',
					$fila->slug
				);
				++$n;
			}
		}

		return $n;
	}

	private static function indexar( array $filas ): array {
		$mapa = [];
		foreach ( $filas as $fila ) {
			$mapa[ $fila->sitio_id . '|' . $fila->tipo . '|' . $fila->slug ] = $fila;
		}
		return $mapa;
	}

	/** @return array<int,object> */
	public static function recientes( int $limite = 50, string $sitio_id = '' ): array {
		global $wpdb;
		$tabla = SWPK_Tablas::tabla( 'eventos' );

		if ( '' !== $sitio_id ) {
			return $wpdb->get_results(
				$wpdb->prepare( "SELECT * FROM {$tabla} WHERE sitio_id = %s ORDER BY ocurrio_en DESC, id DESC LIMIT %d", $sitio_id, $limite )
			) ?: [];
		}

		return $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$tabla} ORDER BY ocurrio_en DESC, id DESC LIMIT %d", $limite )
		) ?: [];
	}

	/** Cuántas veces se actualizó algo en un sitio dentro de un rango. */
	public static function conteo_actualizaciones( string $sitio_id, string $desde, string $hasta ): int {
		global $wpdb;
		$tabla = SWPK_Tablas::tabla( 'eventos' );
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$tabla} WHERE sitio_id = %s AND tipo = %s AND ocurrio_en BETWEEN %s AND %s",
				$sitio_id,
				self::ACTUALIZADO,
				$desde,
				$hasta
			)
		);
	}
}
