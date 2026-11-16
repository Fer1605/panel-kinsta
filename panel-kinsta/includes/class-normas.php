<?php
/**
 * Las normas de seguridad: la lista, y el veredicto de cada sitio contra ella.
 *
 * ─── De dónde sale esto ─────────────────────────────────────────────────────
 *
 * Del audio del 13/11/2026. Lo pidió así:
 *
 *   «me gustaría que pusiéramos como un checklist o un listado (...) de doble
 *    autenticación, que no se pueda escribir código, que no tenga usuarios
 *    administradores sin doble autenticación, que no tenga spam, que todos los
 *    formularios tengan honeypot (...) que tenga htaccess bien configurado, que
 *    no se puedan leer archivos en el upload de PHP o archivos maliciosos»
 *
 * Y que la lista se pueda cambiar: «ya podamos agregar más, podamos quitar».
 *
 * ─── Por qué una tabla y no una constante en el código ──────────────────────
 *
 * Porque pidió editarla. Una lista en el código la cambia quien programa; una
 * lista en la base de datos la cambia él. Las diez que vienen de fábrica se
 * pueden apagar pero no borrar, para que nadie deje el panel sin normas por
 * accidente y después no sepa cómo volver atrás.
 *
 * ─── Por qué el veredicto se guarda con fecha ───────────────────────────────
 *
 * El panel no puede comprobar estas normas por su cuenta: casi ninguna se ve
 * desde fuera. Las trae el agente en su reporte, y el reporte llega una vez al
 * día. Entonces lo que la pantalla muestra es siempre «así estaba cuando se
 * midió», y esa fecha tiene que ir pegada al dato o el panel estaría afirmando
 * cosas del presente con información de ayer. Es la misma regla que ya rige el
 * inventario (ver SWPK_Frescura).
 *
 * ─── Las dos que hubo que reescribir ────────────────────────────────────────
 *
 * 1. El `.htaccess` **no existe en Kinsta**, que corre Nginx. Su propia
 *    documentación lo dice. Así que la norma no pregunta si el archivo tiene
 *    la regla, pregunta por el efecto: ¿se ejecuta PHP en /uploads o no? Eso
 *    se mide igual en Nginx, en Apache y en Pantheon, y es lo que de verdad
 *    importa. Medir el efecto en vez de la causa es más trabajo y más honesto.
 *
 * 2. El honeypot no tiene forma genérica de comprobarse: depende del plugin de
 *    formularios de cada sitio. Queda como norma declarativa — alguien marca
 *    «revisado» y queda con su nombre y su fecha. Es una lista de verificación
 *    en papel, pero con rastro. Lo de inyectar el honeypot a la fuerza se
 *    descartó: rompe formularios ajenos.
 */

defined( 'ABSPATH' ) || exit;

class SWPK_Normas {

	/** Los cuatro veredictos posibles. No hay un quinto. */
	public const CUMPLE    = 'cumple';
	public const FALLA     = 'falla';
	public const SIN_DATO  = 'sin_dato';
	public const NO_APLICA = 'no_aplica';

	public const OPCION_SEMBRADO = 'swpk_normas_sembradas';

	/** Cada vez que cambie el catálogo de fábrica, este número sube. */
	public const VERSION_CATALOGO = 1;

	public static function init(): void {
		add_action( 'plugins_loaded', [ __CLASS__, 'sembrar' ], 20 );
	}

	// ─────────────────────────────────────────────────────────────────────────
	// El catálogo de fábrica
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * Las normas con las que arranca el panel.
	 *
	 * `arreglable` significa que el agente sabe cómo cumplirla si se le ordena.
	 * Las que no lo son no es por pereza: cambiarle el nombre al usuario
	 * «admin» rompe lo que esté escrito a su nombre, y que PHP no se ejecute en
	 * /uploads es una regla del servidor, no del sitio — en Kinsta se pide a
	 * soporte. Esas se miden y se avisan; arreglarlas es trabajo de alguien.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function catalogo(): array {
		return [
			'dos_pasos_activa' => [
				'nombre'      => 'La verificación en dos pasos está activa en el sitio',
				'porque'      => 'Sin ella, una contraseña filtrada es la entrada completa. Es la única norma '
					. 'de esta lista que sigue protegiendo el sitio después de que la contraseña ya se perdió.',
				'comprobador' => 'dos_pasos_activa',
				'severidad'   => 'alta',
				'arreglable'  => 1,
				'orden'       => 10,
			],
			'admins_dos_pasos' => [
				'nombre'      => 'Ningún administrador sin verificación en dos pasos',
				'porque'      => 'Que esté activa en el sitio no sirve si hay administradores que nunca la '
					. 'configuraron: esos siguen entrando con contraseña a secas, y son los que tienen control total.',
				'comprobador' => 'admins_dos_pasos',
				'severidad'   => 'alta',
				'arreglable'  => 1,
				'orden'       => 20,
			],
			'editor_codigo' => [
				'nombre'      => 'No se puede escribir código desde el escritorio',
				'porque'      => 'Con el editor de temas y plugins abierto, cualquiera que entre como '
					. 'administrador puede dejar código suyo corriendo en el sitio sin tocar el servidor. '
					. 'Es el paso que convierte una cuenta robada en un sitio robado.',
				'comprobador' => 'editor_codigo',
				'severidad'   => 'alta',
				'arreglable'  => 1,
				'orden'       => 30,
			],
			'php_en_uploads' => [
				'nombre'      => 'En la carpeta de subidas no se ejecuta PHP',
				'porque'      => 'Si se ejecuta, un archivo subido por un formulario deja de ser un archivo y '
					. 'pasa a ser un programa. Es la vía clásica de entrada por un formulario mal protegido.',
				'comprobador' => 'php_en_uploads',
				'severidad'   => 'alta',
				'arreglable'  => 0,
				'orden'       => 40,
			],
			'xmlrpc_cerrado' => [
				'nombre'      => 'XML-RPC está cerrado',
				'porque'      => 'Es una puerta vieja de WordPress que casi ningún sitio usa y que permite '
					. 'probar cientos de contraseñas en una sola petición, sin que el sitio lo note como cientos de intentos.',
				'comprobador' => 'xmlrpc_cerrado',
				'severidad'   => 'media',
				'arreglable'  => 1,
				'orden'       => 50,
			],
			'registro_cerrado' => [
				'nombre'      => 'El registro de usuarios está cerrado',
				'porque'      => 'Un sitio informativo con el registro abierto termina lleno de cuentas que '
					. 'nadie creó a propósito, y es por donde entra el spam.',
				'comprobador' => 'registro_cerrado',
				'severidad'   => 'media',
				'arreglable'  => 1,
				'orden'       => 60,
			],
			'sin_usuario_admin' => [
				'nombre'      => 'No existe un usuario llamado «admin»',
				'porque'      => 'Es el primer nombre que prueba cualquier ataque automático. Con el nombre '
					. 'acertado, solo les falta la contraseña.',
				'comprobador' => 'sin_usuario_admin',
				'severidad'   => 'media',
				'arreglable'  => 0,
				'orden'       => 70,
			],
			'formularios_honeypot' => [
				'nombre'      => 'Los formularios tienen honeypot o captcha',
				'porque'      => 'Sin eso, los formularios se llenan de spam automático y el correo del cliente '
					. 'se vuelve inservible. Esta se marca a mano: no hay forma de comprobarla igual en todos los '
					. 'plugins de formularios.',
				'comprobador' => 'declarativa',
				'severidad'   => 'media',
				'arreglable'  => 0,
				'orden'       => 80,
			],
			'wp_al_dia' => [
				'nombre'      => 'WordPress está en una versión con soporte',
				'porque'      => 'Una versión vieja acumula fallas conocidas y publicadas. No es que pueda '
					. 'pasar algo: es que ya está escrito dónde y cómo.',
				'comprobador' => 'wp_al_dia',
				'severidad'   => 'media',
				'arreglable'  => 0,
				'orden'       => 90,
			],
			'php_soportado' => [
				'nombre'      => 'PHP está en una versión con soporte',
				'porque'      => 'Una versión de PHP sin soporte ya no recibe parches de seguridad, así que las '
					. 'fallas que le encuentren de ahora en adelante se quedan sin arreglar.',
				'comprobador' => 'php_soportado',
				'severidad'   => 'media',
				'arreglable'  => 0,
				'orden'       => 100,
			],
		];
	}

	/** Mete el catálogo en la tabla la primera vez, y al subir de versión. */
	public static function sembrar(): void {
		if ( (int) get_option( self::OPCION_SEMBRADO, 0 ) === self::VERSION_CATALOGO ) {
			return;
		}

		global $wpdb;
		$tabla = SWPK_Tablas::tabla( 'normas' );

		foreach ( self::catalogo() as $clave => $n ) {
			$existe = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$tabla} WHERE clave = %s", $clave ) );

			$fila = [
				'clave'       => $clave,
				'nombre'      => $n['nombre'],
				'porque'      => $n['porque'],
				'comprobador' => $n['comprobador'],
				'severidad'   => $n['severidad'],
				'arreglable'  => (int) $n['arreglable'],
				'orden'       => (int) $n['orden'],
				'de_fabrica'  => 1,
			];

			if ( $existe ) {
				// Se refresca el texto, no el estado: si él la apagó, sigue
				// apagada. Lo que él decidió manda sobre lo que trae el código.
				unset( $fila['clave'] );
				$wpdb->update( $tabla, $fila, [ 'id' => (int) $existe ] );
			} else {
				$fila['activa']    = 1;
				$fila['creada_en'] = current_time( 'mysql' );
				$wpdb->insert( $tabla, $fila );
			}
		}

		update_option( self::OPCION_SEMBRADO, self::VERSION_CATALOGO, false );
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Leer y escribir la lista
	// ─────────────────────────────────────────────────────────────────────────

	/** @return array<int,object> */
	public static function todas(): array {
		global $wpdb;
		$tabla = SWPK_Tablas::tabla( 'normas' );

		return $wpdb->get_results( "SELECT * FROM {$tabla} ORDER BY orden ASC, id ASC" ) ?: [];
	}

	/** @return array<int,object> */
	public static function activas(): array {
		return array_values( array_filter( self::todas(), static fn( $n ) => (int) $n->activa === 1 ) );
	}

	public static function una( string $clave ): ?object {
		global $wpdb;
		$tabla = SWPK_Tablas::tabla( 'normas' );

		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tabla} WHERE clave = %s", $clave ) ) ?: null;
	}

	/**
	 * Crea o cambia una norma.
	 *
	 * De una norma de fábrica solo se deja cambiar lo que no rompe nada: a qué
	 * sitios aplica, qué tan grave es, y si está encendida. El comprobador no,
	 * porque es código: apuntarlo a otro sitio solo serviría para que la norma
	 * midiera algo que no es lo que dice su nombre.
	 *
	 * @param array<string,mixed> $datos
	 */
	public static function guardar( array $datos ): string|WP_Error {
		global $wpdb;
		$tabla = SWPK_Tablas::tabla( 'normas' );

		$nombre = sanitize_text_field( (string) ( $datos['nombre'] ?? '' ) );
		if ( '' === $nombre ) {
			return new WP_Error( 'swpk_norma_sin_nombre', 'Una norma necesita un nombre.' );
		}

		$clave   = sanitize_key( (string) ( $datos['clave'] ?? '' ) );
		$existe  = '' !== $clave ? self::una( $clave ) : null;
		$fabrica = $existe && (int) $existe->de_fabrica === 1;

		$fila = [
			'nombre'    => $nombre,
			'porque'    => wp_kses_post( (string) ( $datos['porque'] ?? '' ) ),
			'aplica_a'  => self::limpiar_aplica_a( (string) ( $datos['aplica_a'] ?? '' ) ),
			'severidad' => in_array( $datos['severidad'] ?? '', [ 'alta', 'media', 'baja' ], true )
				? (string) $datos['severidad']
				: 'media',
			'activa'    => empty( $datos['activa'] ) ? 0 : 1,
		];

		if ( $existe ) {
			if ( $fabrica ) {
				// El nombre y el porqué de una norma de fábrica los pone el
				// código, y se vuelven a escribir en cada siembra. Dejar que se
				// editen aquí sería prometer un cambio que la siguiente
				// actualización borra sin avisar.
				unset( $fila['nombre'], $fila['porque'] );
			}

			$wpdb->update( $tabla, $fila, [ 'id' => (int) $existe->id ] );

			return (string) $existe->clave;
		}

		// Una norma nueva la escribe una persona, así que no tiene comprobador:
		// nadie puede programar uno desde una pantalla. Nace declarativa, que
		// es justo lo que hace falta para «esto lo revisamos a mano».
		$clave = self::clave_libre( $nombre );

		$wpdb->insert(
			$tabla,
			$fila + [
				'clave'       => $clave,
				'comprobador' => 'declarativa',
				'arreglable'  => 0,
				'de_fabrica'  => 0,
				'orden'       => 500,
				'creada_en'   => current_time( 'mysql' ),
			]
		);

		return $clave;
	}

	/**
	 * Borra una norma.
	 *
	 * Las de fábrica no se borran, se apagan. Si se borraran, la siguiente
	 * siembra las devolvería y daría la impresión de que el panel no obedece.
	 */
	public static function borrar( string $clave ): true|WP_Error {
		$norma = self::una( $clave );

		if ( ! $norma ) {
			return new WP_Error( 'swpk_norma', 'Esa norma no existe.' );
		}

		if ( (int) $norma->de_fabrica === 1 ) {
			return new WP_Error(
				'swpk_norma_fabrica',
				'Las normas que vienen con el panel no se borran, se apagan. Así queda claro que se decidió no medirla, en vez de parecer que nunca existió.'
			);
		}

		global $wpdb;
		$wpdb->delete( SWPK_Tablas::tabla( 'normas' ), [ 'clave' => $clave ] );
		$wpdb->delete( SWPK_Tablas::tabla( 'cumplimiento' ), [ 'norma' => $clave ] );

		return true;
	}

	public static function encender( string $clave, bool $encendida ): void {
		global $wpdb;
		$wpdb->update(
			SWPK_Tablas::tabla( 'normas' ),
			[ 'activa' => $encendida ? 1 : 0 ],
			[ 'clave' => $clave ]
		);
	}

	private static function limpiar_aplica_a( string $crudo ): string {
		$validos = array_keys( SWPK_Etiquetas::nombres() );
		$pedidos = array_filter( array_map( 'sanitize_key', explode( ',', $crudo ) ) );
		$buenos  = array_values( array_intersect( $pedidos, $validos ) );

		// Vacío quiere decir «todos». Es lo que se quiere por defecto: una
		// norma de seguridad que solo aplica a algunos sitios es la excepción.
		return count( $buenos ) === count( $validos ) ? '' : implode( ',', $buenos );
	}

	private static function clave_libre( string $nombre ): string {
		$base  = sanitize_key( remove_accents( $nombre ) );
		$base  = '' !== $base ? substr( $base, 0, 48 ) : 'norma';
		$clave = $base;
		$n     = 2;

		while ( self::una( $clave ) ) {
			$clave = $base . '_' . $n;
			++$n;
		}

		return $clave;
	}

	/** ¿Esta norma aplica a un sitio con esta etiqueta? */
	public static function aplica( object $norma, string $tag ): bool {
		$aplica = trim( (string) $norma->aplica_a );

		return '' === $aplica || in_array( $tag, array_map( 'trim', explode( ',', $aplica ) ), true );
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Medir
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * Mide todas las normas activas contra un reporte que acaba de llegar.
	 *
	 * Se llama desde el receptor, con el reporte en la mano. No va a buscar
	 * nada afuera: todo lo que necesita viene en el reporte o ya está en el
	 * panel. Un sitio que no reporta no se mide — y eso se ve, porque su fila
	 * del tablero queda en «sin dato» con la fecha de la última vez.
	 *
	 * @param array<string,mixed> $reporte
	 * @return array<string,string> clave de norma => veredicto
	 */
	public static function medir( string $sitio_id, array $reporte ): array {
		$sitio = '' !== $sitio_id ? SWPK_Inventario::obtener( $sitio_id ) : null;
		$tag   = $sitio ? (string) $sitio->tag : SWPK_Etiquetas::SIN;

		$veredictos = [];

		foreach ( self::activas() as $norma ) {
			if ( ! self::aplica( $norma, $tag ) ) {
				self::anotar( $sitio_id, (string) $norma->clave, self::NO_APLICA, 'Esta norma no aplica a este grupo de sitios.' );
				$veredictos[ (string) $norma->clave ] = self::NO_APLICA;
				continue;
			}

			$r = self::comprobar( (string) $norma->comprobador, $reporte, $sitio );

			// Una norma declarativa la contesta una persona, no el agente. Si
			// se midiera aquí, cada reporte diario borraría el «revisado» que
			// alguien puso ayer.
			if ( 'declarativa' === $norma->comprobador ) {
				$veredictos[ (string) $norma->clave ] = self::SIN_DATO;
				continue;
			}

			self::anotar( $sitio_id, (string) $norma->clave, $r['estado'], $r['detalle'] );
			$veredictos[ (string) $norma->clave ] = $r['estado'];
		}

		return $veredictos;
	}

	/**
	 * @param array<string,mixed> $reporte
	 * @return array{estado:string,detalle:string}
	 */
	public static function comprobar( string $comprobador, array $reporte, ?object $sitio ): array {
		$metodo = 'comprobar_' . $comprobador;

		if ( ! method_exists( __CLASS__, $metodo ) ) {
			return [
				'estado'  => self::SIN_DATO,
				'detalle' => 'Esta norma no tiene una forma automática de comprobarse.',
			];
		}

		return self::$metodo( $reporte, $sitio );
	}

	/** Guarda el veredicto de un cruce sitio × norma, con la hora en que se midió. */
	public static function anotar( string $sitio_id, string $norma, string $estado, string $detalle = '' ): void {
		if ( '' === $sitio_id ) {
			return;
		}

		global $wpdb;
		$tabla = SWPK_Tablas::tabla( 'cumplimiento' );

		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$tabla} (sitio_id, norma, estado, detalle, medido_en)
				 VALUES (%s, %s, %s, %s, %s)
				 ON DUPLICATE KEY UPDATE estado = VALUES(estado), detalle = VALUES(detalle), medido_en = VALUES(medido_en)",
				$sitio_id,
				$norma,
				$estado,
				$detalle,
				current_time( 'mysql' )
			)
		);
	}

	/**
	 * Marca a mano una norma declarativa. Queda con nombre y fecha.
	 *
	 * Esto es lo más parecido a una firma que tiene el panel: no prueba que el
	 * honeypot esté puesto, prueba que alguien dijo que lo revisó, cuándo y
	 * quién. Que es exactamente lo que vale en una auditoría.
	 */
	public static function marcar( string $sitio_id, string $norma, string $estado, int $quien = 0 ): true|WP_Error {
		if ( ! in_array( $estado, [ self::CUMPLE, self::FALLA, self::SIN_DATO ], true ) ) {
			return new WP_Error( 'swpk_estado', 'Veredicto desconocido.' );
		}

		$n = self::una( $norma );
		if ( ! $n ) {
			return new WP_Error( 'swpk_norma', 'Esa norma no existe.' );
		}

		global $wpdb;
		$tabla = SWPK_Tablas::tabla( 'cumplimiento' );
		$quien = $quien ?: get_current_user_id();
		$ahora = current_time( 'mysql' );

		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$tabla} (sitio_id, norma, estado, detalle, medido_en, revisado_por, revisado_en)
				 VALUES (%s, %s, %s, %s, %s, %d, %s)
				 ON DUPLICATE KEY UPDATE estado = VALUES(estado), detalle = VALUES(detalle),
				   medido_en = VALUES(medido_en), revisado_por = VALUES(revisado_por), revisado_en = VALUES(revisado_en)",
				$sitio_id,
				$norma,
				$estado,
				'Revisado a mano.',
				$ahora,
				$quien,
				$ahora
			)
		);

		SWPK_Eventos::registrar(
			'norma_revisada',
			$sitio_id,
			sprintf(
				'%s marcó «%s» como %s',
				self::quien( $quien ),
				$n->nombre,
				self::CUMPLE === $estado ? 'cumplida' : ( self::FALLA === $estado ? 'incumplida' : 'sin revisar' )
			),
			'info'
		);

		return true;
	}

	private static function quien( int $id ): string {
		$u = get_userdata( $id );

		return $u ? $u->display_name : 'Alguien';
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Los comprobadores
	//
	// Cada uno recibe el reporte del agente y la fila del sitio, y contesta una
	// de cuatro cosas. Ninguno sale a internet ni toca el sitio: leen lo que ya
	// llegó. Y ninguno confunde «no lo sé» con «está mal», que es la
	// equivocación que haría inservible todo el tablero.
	// ─────────────────────────────────────────────────────────────────────────

	private static function comprobar_dos_pasos_activa( array $r, ?object $sitio ): array {
		if ( ! isset( $r['dos_pasos']['activo'] ) ) {
			return [ 'estado' => self::SIN_DATO, 'detalle' => 'El agente no reportó este dato.' ];
		}

		if ( empty( $r['dos_pasos']['activo'] ) ) {
			return [ 'estado' => self::FALLA, 'detalle' => 'La verificación en dos pasos está apagada en este sitio.' ];
		}

		$roles  = (array) ( $r['dos_pasos']['roles'] ?? [] );
		$gracia = (int) ( $r['dos_pasos']['gracia_hasta'] ?? 0 );

		if ( ! in_array( 'administrator', $roles, true ) ) {
			return [
				'estado'  => self::FALLA,
				'detalle' => 'Está activa, pero no para los administradores — que son justo los que tienen control total.',
			];
		}

		if ( $gracia > time() ) {
			return [
				'estado'  => self::CUMPLE,
				'detalle' => sprintf(
					'Activa para administradores, con periodo de gracia hasta el %s.',
					wp_date( 'j/n/Y', $gracia )
				),
			];
		}

		return [ 'estado' => self::CUMPLE, 'detalle' => 'Activa y exigida para los administradores.' ];
	}

	private static function comprobar_admins_dos_pasos( array $r, ?object $sitio ): array {
		$usuarios = (array) ( $r['usuarios'] ?? [] );

		if ( ! $usuarios ) {
			return [ 'estado' => self::SIN_DATO, 'detalle' => 'El agente no reportó usuarios.' ];
		}

		$sin = [];

		foreach ( $usuarios as $u ) {
			$roles = (array) ( $u['roles'] ?? [] );

			if ( ! in_array( 'administrator', $roles, true ) ) {
				continue;
			}

			if ( empty( $u['dos_pasos'] ) ) {
				$sin[] = (string) ( $u['login'] ?? '?' );
			}
		}

		if ( ! $sin ) {
			return [ 'estado' => self::CUMPLE, 'detalle' => 'Todos los administradores la tienen configurada.' ];
		}

		return [
			'estado'  => self::FALLA,
			'detalle' => sprintf(
				'%s sin configurarla: %s.',
				SWPK_Vista::plural( count( $sin ), 'administrador', 'administradores' ),
				implode( ', ', array_slice( $sin, 0, 8 ) ) . ( count( $sin ) > 8 ? '…' : '' )
			),
		];
	}

	private static function comprobar_editor_codigo( array $r, ?object $sitio ): array {
		if ( ! isset( $r['normas']['editor_codigo'] ) ) {
			return [ 'estado' => self::SIN_DATO, 'detalle' => 'Hace falta una versión del agente que reporte este dato.' ];
		}

		return empty( $r['normas']['editor_codigo'] )
			? [ 'estado' => self::FALLA, 'detalle' => 'El editor de temas y plugins está abierto: se puede escribir código desde el escritorio.' ]
			: [ 'estado' => self::CUMPLE, 'detalle' => 'El editor de código está deshabilitado.' ];
	}

	private static function comprobar_php_en_uploads( array $r, ?object $sitio ): array {
		$v = $r['normas']['php_en_uploads'] ?? null;

		if ( null === $v ) {
			return [ 'estado' => self::SIN_DATO, 'detalle' => 'Hace falta una versión del agente que haga esta prueba.' ];
		}

		if ( 'no_se_pudo' === $v ) {
			// Decirlo y no esconderlo: no poder escribir el archivo de prueba
			// no es una buena noticia ni una mala, es no saber.
			return [ 'estado' => self::SIN_DATO, 'detalle' => 'No se pudo hacer la prueba: el agente no logró escribir el archivo temporal.' ];
		}

		return $v
			? [ 'estado' => self::FALLA, 'detalle' => 'Un archivo PHP subido a la carpeta de subidas SÍ se ejecuta. En Kinsta esto se cierra pidiéndoselo a soporte; no es algo que el sitio pueda arreglar solo.' ]
			: [ 'estado' => self::CUMPLE, 'detalle' => 'Probado con un archivo real: el servidor lo devolvió como texto, no lo ejecutó.' ];
	}

	private static function comprobar_xmlrpc_cerrado( array $r, ?object $sitio ): array {
		if ( ! isset( $r['xmlrpc_abierto'] ) ) {
			return [ 'estado' => self::SIN_DATO, 'detalle' => 'El agente no reportó este dato.' ];
		}

		return empty( $r['xmlrpc_abierto'] )
			? [ 'estado' => self::CUMPLE, 'detalle' => 'XML-RPC está cerrado.' ]
			: [ 'estado' => self::FALLA, 'detalle' => 'XML-RPC está abierto.' ];
	}

	private static function comprobar_registro_cerrado( array $r, ?object $sitio ): array {
		if ( ! isset( $r['normas']['registro_abierto'] ) ) {
			return [ 'estado' => self::SIN_DATO, 'detalle' => 'Hace falta una versión del agente que reporte este dato.' ];
		}

		return empty( $r['normas']['registro_abierto'] )
			? [ 'estado' => self::CUMPLE, 'detalle' => 'Nadie puede crearse una cuenta por su cuenta.' ]
			: [ 'estado' => self::FALLA, 'detalle' => 'Cualquiera puede registrarse en el sitio.' ];
	}

	private static function comprobar_sin_usuario_admin( array $r, ?object $sitio ): array {
		$usuarios = (array) ( $r['usuarios'] ?? [] );

		if ( ! $usuarios ) {
			return [ 'estado' => self::SIN_DATO, 'detalle' => 'El agente no reportó usuarios.' ];
		}

		foreach ( $usuarios as $u ) {
			if ( 'admin' === strtolower( (string) ( $u['login'] ?? '' ) ) ) {
				return [
					'estado'  => self::FALLA,
					'detalle' => 'Existe un usuario llamado «admin». Cambiarle el nombre hay que hacerlo a mano: lo que esté escrito a su nombre se queda apuntando a él.',
				];
			}
		}

		return [ 'estado' => self::CUMPLE, 'detalle' => 'No hay ningún usuario con ese nombre.' ];
	}

	private static function comprobar_wp_al_dia( array $r, ?object $sitio ): array {
		$aqui = (string) ( $r['wp'] ?? '' );

		if ( '' === $aqui ) {
			return [ 'estado' => self::SIN_DATO, 'detalle' => 'El agente no reportó la versión de WordPress.' ];
		}

		$minima = (string) apply_filters( 'swpk_wp_minima', '6.4' );

		return version_compare( $aqui, $minima, '>=' )
			? [ 'estado' => self::CUMPLE, 'detalle' => sprintf( 'WordPress %s.', $aqui ) ]
			: [
				'estado'  => self::FALLA,
				'detalle' => sprintf( 'WordPress %s, y lo mínimo con soporte es %s.', $aqui, $minima ),
			];
	}

	private static function comprobar_php_soportado( array $r, ?object $sitio ): array {
		$aqui = (string) ( $r['php'] ?? '' );

		if ( '' === $aqui ) {
			return [ 'estado' => self::SIN_DATO, 'detalle' => 'El agente no reportó la versión de PHP.' ];
		}

		// Se queda en el código y en un filtro, no en la base de datos: cuando
		// PHP retire una versión hay que cambiar esto a mano, y es mejor que
		// eso se vea en un commit que escondido en una fila de una tabla.
		$minima = (string) apply_filters( 'swpk_php_minima', '8.1' );

		return version_compare( $aqui, $minima, '>=' )
			? [ 'estado' => self::CUMPLE, 'detalle' => sprintf( 'PHP %s.', $aqui ) ]
			: [
				'estado'  => self::FALLA,
				'detalle' => sprintf( 'PHP %s, que ya no recibe parches. Lo mínimo con soporte es %s.', $aqui, $minima ),
			];
	}

	/** Las declarativas no se comprueban solas, por definición. */
	private static function comprobar_declarativa( array $r, ?object $sitio ): array {
		return [ 'estado' => self::SIN_DATO, 'detalle' => 'Esta norma se marca a mano.' ];
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Leer el tablero
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * Todo el cumplimiento guardado, indexado por sitio y norma.
	 *
	 * @return array<string,array<string,object>>
	 */
	public static function cumplimiento(): array {
		global $wpdb;
		$tabla = SWPK_Tablas::tabla( 'cumplimiento' );
		$filas = $wpdb->get_results( "SELECT * FROM {$tabla}" ) ?: [];

		$mapa = [];

		foreach ( $filas as $f ) {
			$mapa[ (string) $f->sitio_id ][ (string) $f->norma ] = $f;
		}

		return $mapa;
	}

	/** @return array<string,object> */
	public static function del_sitio( string $sitio_id ): array {
		global $wpdb;
		$tabla = SWPK_Tablas::tabla( 'cumplimiento' );
		$filas = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$tabla} WHERE sitio_id = %s", $sitio_id )
		) ?: [];

		$mapa = [];

		foreach ( $filas as $f ) {
			$mapa[ (string) $f->norma ] = $f;
		}

		return $mapa;
	}

	/**
	 * El tablero: una fila por sitio, con su veredicto en cada norma.
	 *
	 * @param array<int,object> $sitios
	 * @return array<string,mixed>
	 */
	public static function tablero( array $sitios ): array {
		$normas = self::activas();
		$mapa   = self::cumplimiento();

		$filas    = [];
		$al_dia   = 0;
		$fallando = 0;
		$sin_dato = 0;
		$por_norma = [];

		foreach ( $normas as $n ) {
			$por_norma[ (string) $n->clave ] = [
				'norma'    => $n,
				'cumple'   => 0,
				'falla'    => 0,
				'sin_dato' => 0,
			];
		}

		foreach ( $sitios as $s ) {
			$sitio_id = (string) $s->sitio_id;
			$tag      = (string) $s->tag;
			$del      = $mapa[ $sitio_id ] ?? [];

			$celdas  = [];
			$fallas  = 0;
			$huecos  = 0;
			$cumples = 0;

			foreach ( $normas as $n ) {
				$clave = (string) $n->clave;

				if ( ! self::aplica( $n, $tag ) ) {
					$celdas[ $clave ] = [ 'estado' => self::NO_APLICA, 'detalle' => '', 'medido_en' => '' ];
					continue;
				}

				$fila   = $del[ $clave ] ?? null;
				$estado = $fila ? (string) $fila->estado : self::SIN_DATO;

				$celdas[ $clave ] = [
					'estado'    => $estado,
					'detalle'   => $fila ? (string) $fila->detalle : 'Todavía no se ha medido en este sitio.',
					'medido_en' => $fila ? (string) $fila->medido_en : '',
					'revisado_por' => $fila ? (int) $fila->revisado_por : 0,
				];

				if ( self::FALLA === $estado ) {
					++$fallas;
					++$por_norma[ $clave ]['falla'];
				} elseif ( self::CUMPLE === $estado ) {
					++$cumples;
					++$por_norma[ $clave ]['cumple'];
				} else {
					++$huecos;
					++$por_norma[ $clave ]['sin_dato'];
				}
			}

			if ( $fallas > 0 ) {
				++$fallando;
			} elseif ( $huecos > 0 ) {
				++$sin_dato;
			} else {
				++$al_dia;
			}

			$filas[] = [
				'sitio'   => $s,
				'celdas'  => $celdas,
				'fallas'  => $fallas,
				'huecos'  => $huecos,
				'cumples' => $cumples,
			];
		}

		// Lo peor arriba. Es la misma regla del Resumen: si hay que buscar el
		// problema, la pantalla no sirve.
		usort(
			$filas,
			static function ( $a, $b ) {
				if ( $a['fallas'] !== $b['fallas'] ) {
					return $b['fallas'] <=> $a['fallas'];
				}
				if ( $a['huecos'] !== $b['huecos'] ) {
					return $b['huecos'] <=> $a['huecos'];
				}

				return strcasecmp( (string) $a['sitio']->nombre_visible, (string) $b['sitio']->nombre_visible );
			}
		);

		return [
			'normas'    => $normas,
			'filas'     => $filas,
			'por_norma' => $por_norma,
			'al_dia'    => $al_dia,
			'fallando'  => $fallando,
			'sin_dato'  => $sin_dato,
			'total'     => count( $filas ),
		];
	}

	/** Cómo se dice cada veredicto en pantalla. */
	public static function etiqueta( string $estado ): string {
		return match ( $estado ) {
			self::CUMPLE    => 'Cumple',
			self::FALLA     => 'No cumple',
			self::NO_APLICA => 'No aplica',
			default         => 'Sin medir',
		};
	}
}
