<?php
/**
 * Esquema de base de datos del panel.
 *
 * Son cinco tablas y cada una existe por una razón concreta de la reunión:
 *
 *  - sitios            el inventario consolidado por etiqueta (00:26)
 *  - inventarios       el inventario diario. Es lo que permite decir "este mes se
 *                      actualizó seis veces" (01:18). Sin inventario no hay historia:
 *                      Kinsta no guarda historial consultable.
 *  - eventos           lo que cambió de un día para otro. "Mira, en esta
 *                      corrida del día pasó esto" (04:34)
 *  - vulnerabilidades  la copia local del feed de Wordfence, que es lo que
 *                      aporta el POR QUÉ es grave. Kinsta solo da un sí/no.
 *  - bitacora          qué se notificó, a quién y cuándo (02:58). El corazón
 *                      del proyecto: es la evidencia.
 *  - accesos           entradas, salidas e intentos fallidos al panel (08:17)
 *
 * Y tres que salieron del audio de normas de seguridad (13/11/2026):
 *
 *  - normas            la lista de reglas de seguridad, editable. "que ya
 *                      podamos agregar más, podamos quitar"
 *  - cumplimiento      el cruce sitio × norma con su veredicto. Es una tabla
 *                      aparte y no un cálculo al vuelo porque el dato no está
 *                      en el panel: lo trae el agente una vez al día, así que
 *                      hay que guardarlo con la fecha en que se midió.
 *  - ordenes           la cola de lo que el panel le pide al agente. Existe
 *                      para que el sitio siga sin abrir ninguna puerta: el
 *                      agente pregunta si le dejaron algo, nadie lo llama.
 */

defined( 'ABSPATH' ) || exit;

class SWPK_Tablas {

	public const VERSION = 19;

	public static function tabla( string $nombre ): string {
		global $wpdb;
		return $wpdb->prefix . 'swpk_' . $nombre;
	}

	public static function crear(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$collate = $wpdb->get_charset_collate();

		$sitios = self::tabla( 'sitios' );
		$fotos  = self::tabla( 'fotos' );
		$evt    = self::tabla( 'eventos' );
		$vuln   = self::tabla( 'vulnerabilidades' );
		$bit    = self::tabla( 'bitacora' );
		$acc    = self::tabla( 'accesos' );
		$sas    = self::tabla( 'sitio_asesoras' );

		dbDelta(
			"CREATE TABLE {$sitios} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				sitio_id VARCHAR(64) NOT NULL,
				entorno_id VARCHAR(64) DEFAULT '',
				entornos TEXT NULL,
				nombre VARCHAR(191) NOT NULL DEFAULT '',
				nombre_visible VARCHAR(191) NOT NULL DEFAULT '',
				estado VARCHAR(32) NOT NULL DEFAULT '',
				dominio VARCHAR(191) NOT NULL DEFAULT '',
				etiquetas TEXT NULL,
				tag VARCHAR(32) NOT NULL DEFAULT 'sin_etiqueta',
				version_wp VARCHAR(32) NOT NULL DEFAULT '',
				version_php VARCHAR(32) NOT NULL DEFAULT '',
				inventario_en DATETIME NULL,
				asesora_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
				contacto_cliente VARCHAR(191) NOT NULL DEFAULT '',
				cobertura_agente TINYINT(1) NOT NULL DEFAULT 0,
				tipo_sitio VARCHAR(16) NOT NULL DEFAULT 'sin_dato',
				tipo_motivo VARCHAR(191) NOT NULL DEFAULT '',
				tiene_2fa TINYINT(1) NOT NULL DEFAULT 0,
				plugin_2fa VARCHAR(191) NOT NULL DEFAULT '',
				estado_2fa VARCHAR(32) NOT NULL DEFAULT 'sin_2fa',
				visto_en DATETIME NULL,
				grupo_a_mano VARCHAR(32) NOT NULL DEFAULT '',
				ausencias SMALLINT UNSIGNED NOT NULL DEFAULT 0,
				de_baja TINYINT(1) NOT NULL DEFAULT 0,
				de_baja_en DATETIME NULL,
				suspendido TINYINT(1) NOT NULL DEFAULT 0,
				suspendido_en DATETIME NULL,
				PRIMARY KEY (id),
				UNIQUE KEY sitio_id (sitio_id),
				KEY tag (tag)
			) {$collate};"
		);

		dbDelta(
			"CREATE TABLE {$fotos} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				fecha DATE NOT NULL,
				sitio_id VARCHAR(64) NOT NULL,
				tipo VARCHAR(16) NOT NULL DEFAULT 'plugin',
				slug VARCHAR(191) NOT NULL,
				titulo VARCHAR(191) NOT NULL DEFAULT '',
				version VARCHAR(64) NOT NULL DEFAULT '',
				version_disponible VARCHAR(64) NOT NULL DEFAULT '',
				hay_actualizacion TINYINT(1) NOT NULL DEFAULT 0,
				es_vulnerable TINYINT(1) NOT NULL DEFAULT 0,
				auto_update VARCHAR(64) NOT NULL DEFAULT '',
				activo TINYINT(1) NOT NULL DEFAULT 1,
				escaneado_en DATETIME NULL,
				PRIMARY KEY (id),
				UNIQUE KEY foto (fecha, sitio_id, tipo, slug),
				KEY sitio_fecha (sitio_id, fecha),
				KEY slug (slug)
			) {$collate};"
		);

		dbDelta(
			"CREATE TABLE {$evt} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				ocurrio_en DATETIME NOT NULL,
				sitio_id VARCHAR(64) NOT NULL DEFAULT '',
				tipo VARCHAR(48) NOT NULL,
				severidad VARCHAR(16) NOT NULL DEFAULT 'info',
				slug VARCHAR(191) NOT NULL DEFAULT '',
				titulo TEXT NULL,
				detalle LONGTEXT NULL,
				notificado TINYINT(1) NOT NULL DEFAULT 0,
				PRIMARY KEY (id),
				KEY sitio_fecha (sitio_id, ocurrio_en),
				KEY tipo (tipo),
				KEY notificado (notificado)
			) {$collate};"
		);

		dbDelta(
			"CREATE TABLE {$vuln} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				wf_id VARCHAR(64) NOT NULL,
				titulo TEXT NULL,
				tipo_software VARCHAR(16) NOT NULL DEFAULT 'plugin',
				slug VARCHAR(191) NOT NULL DEFAULT '',
				cve VARCHAR(64) NOT NULL DEFAULT '',
				cve_enlace TEXT NULL,
				cvss_score DECIMAL(3,1) NULL,
				cvss_rating VARCHAR(16) NOT NULL DEFAULT '',
				cvss_vector VARCHAR(120) NOT NULL DEFAULT '',
				informativa TINYINT(1) NOT NULL DEFAULT 0,
				descripcion LONGTEXT NULL,
				remediacion TEXT NULL,
				parcheada TINYINT(1) NOT NULL DEFAULT 0,
				rangos LONGTEXT NULL,
				versiones_parcheadas TEXT NULL,
				publicada DATETIME NULL,
				PRIMARY KEY (id),
				UNIQUE KEY wf_slug (wf_id, slug),
				KEY slug (slug)
			) {$collate};"
		);

		dbDelta(
			"CREATE TABLE {$bit} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				enviado_en DATETIME NOT NULL,
				tipo VARCHAR(48) NOT NULL,
				sitio_id VARCHAR(64) NOT NULL DEFAULT '',
				destinatarios TEXT NULL,
				asunto TEXT NULL,
				cuerpo LONGTEXT NULL,
				resultado VARCHAR(32) NOT NULL DEFAULT 'enviado',
				error TEXT NULL,
				disparado_por BIGINT UNSIGNED NOT NULL DEFAULT 0,
				PRIMARY KEY (id),
				KEY sitio_fecha (sitio_id, enviado_en),
				KEY tipo (tipo)
			) {$collate};"
		);

		$mon = self::tabla( 'monitor' );

		dbDelta(
			"CREATE TABLE {$mon} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				sitio_id VARCHAR(64) NOT NULL,
				url VARCHAR(191) NOT NULL DEFAULT '',
				estado VARCHAR(16) NOT NULL DEFAULT 'desconocido',
				codigo SMALLINT UNSIGNED NOT NULL DEFAULT 0,
				ms INT UNSIGNED NOT NULL DEFAULT 0,
				motivo VARCHAR(191) NOT NULL DEFAULT '',
				fallos_seguidos SMALLINT UNSIGNED NOT NULL DEFAULT 0,
				desde DATETIME NULL,
				ultimo_sondeo DATETIME NULL,
				caidas_mes SMALLINT UNSIGNED NOT NULL DEFAULT 0,
				segundos_caido INT UNSIGNED NOT NULL DEFAULT 0,
				PRIMARY KEY (id),
				UNIQUE KEY sitio_id (sitio_id),
				KEY estado (estado)
			) {$collate};"
		);

		$age = self::tabla( 'agentes' );
		$usr = self::tabla( 'usuarios' );

		dbDelta(
			"CREATE TABLE {$age} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				sitio_url VARCHAR(191) NOT NULL,
				sitio_id VARCHAR(64) NOT NULL DEFAULT '',
				nombre VARCHAR(191) NOT NULL DEFAULT '',
				token VARCHAR(64) NOT NULL,
				secreto VARCHAR(128) NOT NULL,
				version VARCHAR(16) NOT NULL DEFAULT '',
				registrado_en DATETIME NOT NULL,
				ultimo_reporte DATETIME NULL,
				puede_enviar_correo TINYINT(1) NOT NULL DEFAULT 0,
				error_correo TEXT NULL,
				dos_pasos TINYINT(1) NOT NULL DEFAULT 0,
				xmlrpc_abierto TINYINT(1) NOT NULL DEFAULT 1,
				PRIMARY KEY (id),
				UNIQUE KEY sitio_url (sitio_url),
				UNIQUE KEY token (token),
				KEY sitio_id (sitio_id)
			) {$collate};"
		);

		dbDelta(
			"CREATE TABLE {$usr} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				sitio_url VARCHAR(191) NOT NULL,
				sitio_id VARCHAR(64) NOT NULL DEFAULT '',
				usuario_id BIGINT UNSIGNED NOT NULL,
				login VARCHAR(191) NOT NULL DEFAULT '',
				correo VARCHAR(191) NOT NULL DEFAULT '',
				nombre VARCHAR(191) NOT NULL DEFAULT '',
				roles VARCHAR(191) NOT NULL DEFAULT '',
				alta DATETIME NULL,
				dos_pasos TINYINT(1) NOT NULL DEFAULT 0,
				visto_en DATETIME NOT NULL,
				PRIMARY KEY (id),
				UNIQUE KEY sitio_usuario (sitio_url, usuario_id),
				KEY sitio_id (sitio_id),
				KEY correo (correo)
			) {$collate};"
		);

		dbDelta(
			"CREATE TABLE {$acc} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				ocurrio_en DATETIME NOT NULL,
				usuario_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
				login VARCHAR(191) NOT NULL DEFAULT '',
				accion VARCHAR(32) NOT NULL DEFAULT 'entrada',
				detalle VARCHAR(191) NOT NULL DEFAULT '',
				ip VARCHAR(64) NOT NULL DEFAULT '',
				agente TEXT NULL,
				PRIMARY KEY (id),
				KEY usuario (usuario_id),
				KEY fecha (ocurrio_en)
			) {$collate};"
		);

		// ── Lo del audio de normas de seguridad ─────────────────────────────

		$nor = self::tabla( 'normas' );
		$cum = self::tabla( 'cumplimiento' );
		$ord = self::tabla( 'ordenes' );

		// `aclaracion` y `como_se_arregla` son las dos que faltaban para que una
		// norma se pueda cerrar sin preguntarle a nadie: «hay que hacerlo a
		// mano. Ok, ¿qué debo de hacer yo a mano?» (17:58). Los 73 textos
		// están en includes/normas-arreglos.php.
		//
		// El comentario va AQUÍ y no dentro del CREATE TABLE: dbDelta parte la
		// consulta línea a línea y un /* */ dentro la deja sin parsear.
		dbDelta(
			"CREATE TABLE {$nor} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				clave VARCHAR(64) NOT NULL,
				nombre VARCHAR(191) NOT NULL DEFAULT '',
				problema VARCHAR(191) NOT NULL DEFAULT '',
				porque TEXT NULL,
				aclaracion VARCHAR(255) NOT NULL DEFAULT '',
				como_se_arregla TEXT NULL,
				comprobador VARCHAR(64) NOT NULL DEFAULT '',
				aplica_a VARCHAR(191) NOT NULL DEFAULT '',
				aplica_tipo VARCHAR(64) NOT NULL DEFAULT '',
				grupo VARCHAR(48) NOT NULL DEFAULT '',
				severidad VARCHAR(16) NOT NULL DEFAULT 'media',
				arreglable TINYINT(1) NOT NULL DEFAULT 0,
				activa TINYINT(1) NOT NULL DEFAULT 1,
				aplazada TINYINT(1) NOT NULL DEFAULT 0,
				aplazada_porque VARCHAR(191) NOT NULL DEFAULT '',
				de_fabrica TINYINT(1) NOT NULL DEFAULT 0,
				orden SMALLINT UNSIGNED NOT NULL DEFAULT 50,
				creada_en DATETIME NULL,
				PRIMARY KEY (id),
				UNIQUE KEY clave (clave),
				KEY activa (activa)
			) {$collate};"
		);

		dbDelta(
			"CREATE TABLE {$cum} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				sitio_id VARCHAR(64) NOT NULL,
				norma VARCHAR(64) NOT NULL,
				estado VARCHAR(16) NOT NULL DEFAULT 'sin_dato',
				detalle TEXT NULL,
				medido_en DATETIME NULL,
				revisado_por BIGINT UNSIGNED NOT NULL DEFAULT 0,
				revisado_en DATETIME NULL,
				PRIMARY KEY (id),
				UNIQUE KEY sitio_norma (sitio_id, norma),
				KEY norma_estado (norma, estado)
			) {$collate};"
		);

		dbDelta(
			"CREATE TABLE {$ord} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				sitio_id VARCHAR(64) NOT NULL DEFAULT '',
				sitio_url VARCHAR(191) NOT NULL DEFAULT '',
				tipo VARCHAR(48) NOT NULL,
				norma VARCHAR(64) NOT NULL DEFAULT '',
				carga LONGTEXT NULL,
				estado VARCHAR(16) NOT NULL DEFAULT 'pendiente',
				resultado TEXT NULL,
				lote VARCHAR(32) NOT NULL DEFAULT '',
				pedida_por BIGINT UNSIGNED NOT NULL DEFAULT 0,
				pedida_en DATETIME NOT NULL,
				entregada_en DATETIME NULL,
				resuelta_en DATETIME NULL,
				PRIMARY KEY (id),
				KEY sitio_estado (sitio_url, estado),
				KEY estado (estado),
				KEY lote (lote)
			) {$collate};"
		);

		// ── Las asesoras de cada sitio ──────────────────────────────────────
		//
		// Antes era una sola columna en `sitios`: `asesora_id`. Un sitio, una
		// persona. Y no alcanza: «que tienen que ser las 2 asesoras (...) que no
		// sea solo un select de una persona».
		//
		// Esto es una tabla aparte y no tres columnas más en `sitios`, porque
		// mañana pueden ser tres y no hay que volver a tocar el esquema.
		//
		// `asesora_id` se queda donde está y se sigue escribiendo con la
		// primera de la lista: hay pantallas y una caché que la leen, y
		// cambiarlas todas de un tirón es cómo se rompen cosas que ya
		// funcionaban. Lo que manda para los correos y para quién ve qué es
		// esta tabla.
		dbDelta(
			"CREATE TABLE {$sas} (
				sitio_id VARCHAR(64) NOT NULL,
				usuario_id BIGINT UNSIGNED NOT NULL,
				asignada_en DATETIME NOT NULL,
				asignada_por BIGINT UNSIGNED NOT NULL DEFAULT 0,
				PRIMARY KEY (sitio_id, usuario_id),
				KEY usuario (usuario_id)
			) {$collate};"
		);

		self::pasar_asesoras_a_su_tabla();

		update_option( 'swpk_version_tablas', self::VERSION, false );
	}

	public static function revisar_version(): void {
		if ( (int) get_option( 'swpk_version_tablas', 0 ) !== self::VERSION ) {
			self::crear();
		}
	}

	/**
	 * Las asignaciones que ya existían, a la tabla nueva.
	 *
	 * Sin esto, el día que se sube esta versión todos los sitios aparecen sin
	 * asesora: la columna `asesora_id` sigue con su valor, pero el panel ya
	 * está leyendo la tabla. Y nadie se acuerda de a quién tenía asignado qué.
	 *
	 * Corre una sola vez. Se marca con su propia opción y no con la versión de
	 * tablas, porque `crear()` se llama también cuando se añade cualquier otra
	 * tabla más adelante, y entonces esto volvería a correr y resucitaría
	 * asignaciones que alguien quitó a propósito.
	 */
	private static function pasar_asesoras_a_su_tabla(): void {
		if ( get_option( 'swpk_asesoras_pasadas' ) ) {
			return;
		}

		global $wpdb;

		$sitios = self::tabla( 'sitios' );
		$tabla  = self::tabla( 'sitio_asesoras' );

		$filas = $wpdb->get_results( "SELECT sitio_id, asesora_id FROM {$sitios} WHERE asesora_id > 0" );
		$ahora = current_time( 'mysql' );

		foreach ( (array) $filas as $f ) {
			$wpdb->query(
				$wpdb->prepare(
					"INSERT IGNORE INTO {$tabla} (sitio_id, usuario_id, asignada_en, asignada_por) VALUES (%s, %d, %s, 0)",
					(string) $f->sitio_id,
					(int) $f->asesora_id,
					$ahora
				)
			);
		}

		update_option( 'swpk_asesoras_pasadas', 1, false );
	}
}
