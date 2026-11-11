<?php
/**
 * El contrato entre el panel y de dónde salen los datos.
 *
 * Todo lo que el panel sabe de los sitios entra por aquí. Kinsta queda detrás
 * de esta interfaz, no escrito por todo el código, porque el jefe ya avisó:
 * "eso después lo vamos a replicar en Pantheon... y después lo replicamos a
 * otros servidores" (19:05). Agregar Pantheon mañana es escribir una clase que
 * implemente esta interfaz, no rehacer el panel.
 */

defined( 'ABSPATH' ) || exit;

interface SWPK_Fuente {

	/** Nombre legible de la fuente, para mostrarlo en el panel. */
	public function nombre(): string;

	/** ¿Esta fuente trae datos reales o de prueba? */
	public function es_demo(): bool;

	/**
	 * Sitios con sus etiquetas y entornos.
	 *
	 * @return array<int,array<string,mixed>>|WP_Error
	 */
	public function sitios(): array|WP_Error;

	/**
	 * Plugins de toda la empresa, en una sola consulta.
	 *
	 * @return array<int,array<string,mixed>>|WP_Error
	 */
	public function plugins(): array|WP_Error;

	/**
	 * Temas de toda la empresa.
	 *
	 * @return array<int,array<string,mixed>>|WP_Error
	 */
	public function temas(): array|WP_Error;

	/**
	 * Cuándo fue la última vez que el proveedor miró lo instalado en cada sitio.
	 *
	 * No es un detalle: si el proveedor nunca escaneó un sitio, su lista de
	 * plugins llega vacía. Un panel que muestre eso como "sin pendientes" está
	 * diciendo que el sitio está sano cuando en realidad no sabe nada de él, y
	 * eso es exactamente lo contrario de para lo que existe este panel.
	 *
	 * Llamar después de plugins() o temas().
	 *
	 * @return array<string,string> sitio_id => fecha, o cadena vacía si nunca.
	 */
	public function frescura(): array;

	/** Comprueba que la fuente responde. */
	public function probar(): true|WP_Error;
}
