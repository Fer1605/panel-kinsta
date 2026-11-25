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

	/**
	 * La norma se mide, no se cumple, y eso ESTÁ DECIDIDO ASÍ.
	 *
	 * ─── Por qué hace falta un quinto estado ────────────────────────────────
	 *
	 * «Esa norma debería estar pendiente, no fallando.»
	 *
	 * El caso concreto es la verificación en dos pasos. Está apagada en los
	 * 83 sitios de auto-update a propósito y por una razón que no es pereza:
	 * aquí se entra a todos los sitios con un mismo usuario, `SW-admin`, cuyo
	 * correo es el del jefe. Encenderla significaría que cada vez que
	 * cualquiera entra a cualquier sitio, a cualquier hora, le llega el
	 * código a él y hay que molestarlo para que lo pase. Queda apagada hasta
	 * que exista la excepción que él mismo dijo que iba a dar.
	 *
	 * Mientras tanto no es un incumplimiento: es un pendiente con dueño y con
	 * motivo. Contarlo como `falla` tiene dos costes y los dos son caros:
	 * el 100 % pasa a ser inalcanzable por definición, y 83 filas rojas tapan
	 * las que sí hay que arreglar.
	 *
	 * Y `no_aplica` tampoco sirve: no aplica es «esta norma no es para este
	 * sitio». Aquí sí es, y algún día se va a cumplir.
	 *
	 * La norma SE SIGUE MIDIENDO. El día que se encienda en un sitio, ese
	 * sitio pasa a `cumple` solo, sin que nadie toque nada. Lo único que
	 * cambia es cómo se cuenta el «todavía no».
	 */
	public const APLAZADA = 'aplazada';

	public const OPCION_SEMBRADO = 'swpk_normas_sembradas';

	/** Cada vez que cambie el catálogo de fábrica, este número sube. */
	public const VERSION_CATALOGO = 11;

	/**
	 * Los comprobadores que contesta la sonda del panel, no el agente.
	 *
	 * Estos dos se miden siempre en la corrida diaria, porque el agente no los
	 * puede contestar bien: XML-RPC porque desde dentro no se ve si la petición
	 * llega, y los nombres de usuario porque la pregunta es justamente qué se
	 * ve desde fuera.
	 */
	public const COMPROBADORES_DE_SONDA = [
		// Lo que el agente no puede contestar bien desde dentro.
		'xmlrpc_cerrado',
		'usuarios_enumerables',
		'autor_enumerable',

		// Archivos que no deberían estar donde se pueden bajar.
		'ruta_config_respaldo',
		'ruta_debug_log',
		'ruta_volcados',
		'ruta_phpinfo',
		'ruta_restos_instalador',
		'ruta_instalador',
		'ruta_delatores',
		'ruta_listado',
		'ruta_readme',

		// Lo que el servidor le dice al navegador.
		'certificado',
		'cors',
		'hsts',
		'marco',
		'mixto',
		'canonico',
		'csp',
		'referrer',
		'permissions',
		'xss_obsoleta',
		'trace',
		'indexable',
	];

	/**
	 * El que tiene dos fuentes y hay que decidir cuál manda.
	 *
	 * La prueba del agente es la buena —escribe un archivo y mira qué pasa— y
	 * la sonda solo puede decir si el servidor tiene una regla por extensión.
	 * Así que esta se mide desde la sonda ÚNICAMENTE en los sitios sin agente;
	 * si no, la corrida diaria pisaría el veredicto bueno con el parcial.
	 */
	public const COMPROBADOR_DE_AMBOS = 'php_en_uploads';

	/**
	 * Lo que sale del inventario que la corrida diaria ya guardó.
	 *
	 * Cero peticiones: todo esto está en `swpk_fotos` y en `swpk_sitios`.
	 */
	public const COMPROBADORES_DE_INVENTARIO = [
		'inv_vulnerables',
		'inv_inactivos',
		'inv_estado',
		'wp_al_dia',
		'php_soportado',
	];

	/** Lo que hay que pedirle a la plataforma. Ver SWPK_Revision. */
	public const COMPROBADORES_DE_REVISION = [
		'rev_https',
		'rev_respaldo',
		'rev_trafico',
		'rev_errores',
		'rev_logins',
		'rev_bots',
		'rev_excepciones',
		'rev_sftp',
		'rev_redirecciones',
		'rev_ia',
	];

	public static function init(): void {
		add_action( 'plugins_loaded', [ __CLASS__, 'sembrar' ], 20 );
	}

	// ─────────────────────────────────────────────────────────────────────────
	// El catálogo de fábrica
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * Las normas con las que arranca el panel.
	 *
	 * Cada norma lleva DOS textos y hacen falta los dos:
	 *
	 *   `nombre`    la regla, en afirmativo: «No existe un usuario llamado
	 *               admin». Es lo que la norma exige, y es lo que va en la
	 *               lista de normas.
	 *   `problema`  lo que pasa cuando no se cumple: «Existe un usuario
	 *               llamado admin». Es lo que va en la lista de fallos.
	 *
	 * Tener solo el primero hacía que el tablero se contradijera solo: el
	 * título decía «No existe un usuario llamado admin» y justo debajo la fila
	 * decía que sí existe. Quien lo leía tenía que deducir que el título era la
	 * regla y la fila el incumplimiento, y nadie debería tener que deducir eso.
	 *
	 * `arreglable` significa que el agente sabe cómo cumplirla si se le ordena.
	 * Las que no lo son no es por pereza: cambiarle el nombre al usuario
	 * «admin» rompe lo que esté escrito a su nombre, y que PHP no se ejecute en
	 * /uploads es una regla del servidor, no del sitio — en Kinsta se pide a
	 * soporte. Esas se miden y se avisan; arreglarlas es trabajo de alguien.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	/**
	 * Los temas en los que se divide el catálogo, en orden de lectura.
	 *
	 * Estaban desde el principio, pero solo como comentarios dentro del
	 * código: la pantalla no tenía de dónde agarrarse y sacaba las 73 normas
	 * de corrido, una detrás de otra, sin que nadie pudiera decir dónde
	 * empezaba un tema y terminaba otro.
	 *
	 * El orden importa y no es alfabético: va de lo que cualquiera entiende
	 * —quién entra al sitio— a lo que solo le dice algo a quien lo administra.
	 *
	 * @return array<int,string>
	 */
	public static function grupos(): array {
		return [
			'Cuentas y accesos',
			'Archivos a la vista',
			'Lo que el navegador recibe',
			'Versiones',
			'El hosting',
			'Formularios y subidas',
			'Dentro del sitio',
			'Tienda',
		];
	}

	public static function catalogo(): array {
		return [
			'dos_pasos_activa' => [
				'nombre'      => 'La verificación en dos pasos está activa en el sitio',
				'problema'    => 'La verificación en dos pasos está apagada',
				'porque'      => 'Es pedir un código al correo además de la contraseña. Sin eso, a quien se le '
					. 'filtre la contraseña entra y ya — no hay un segundo candado. Es lo único de esta lista '
					. 'que sigue protegiendo el sitio cuando la contraseña ya se perdió.',
				'comprobador' => 'dos_pasos_activa',
				'severidad'   => 'alta',
				'arreglable'  => 1,
				'orden'       => 10,
				'grupo'       => 'Cuentas y accesos',
			],
			'admins_dos_pasos' => [
				'nombre'      => 'Ningún administrador sin verificación en dos pasos',
				'problema'    => 'Hay administradores sin verificación en dos pasos',
				'porque'      => 'Encenderla en el sitio no basta: cada administrador tiene que configurarla una '
					. 'vez. El que no lo haya hecho sigue entrando solo con su contraseña, y los administradores '
					. 'son los que pueden cambiarlo todo.',
				'comprobador' => 'admins_dos_pasos',
				'severidad'   => 'alta',
				'arreglable'  => 1,
				'orden'       => 20,
				'grupo'       => 'Cuentas y accesos',
			],
			'editor_codigo' => [
				'nombre'      => 'No se puede escribir código desde el escritorio',
				'problema'    => 'Se puede escribir código desde el escritorio',
				'porque'      => 'WordPress trae un editor que deja cambiar el código del sitio desde el '
					. 'escritorio, sin entrar al servidor. Si alguien se roba una cuenta de administrador y eso '
					. 'está abierto, puede dejar su propio código corriendo ahí mismo. Es el paso que convierte '
					. 'una cuenta robada en un sitio robado.',
				'comprobador' => 'editor_codigo',
				'severidad'   => 'alta',
				'arreglable'  => 1,
				'orden'       => 30,
				'grupo'       => 'Cuentas y accesos',
			],
			'php_en_uploads' => [
				'nombre'      => 'En la carpeta de subidas no se ejecuta PHP',
				'problema'    => 'En la carpeta de subidas SÍ se ejecuta PHP',
				'porque'      => 'Ahí es donde caen los archivos que sube la gente por un formulario. Si el '
					. 'servidor los ejecuta en vez de solo guardarlos, alguien puede subir un programa disfrazado '
					. 'de archivo y hacerlo correr. Es la entrada clásica por un formulario mal protegido.',
				'comprobador' => 'php_en_uploads',
				'severidad'   => 'alta',
				'arreglable'  => 0,
				'orden'       => 40,
				'grupo'       => 'Cuentas y accesos',
			],
			'xmlrpc_cerrado' => [
				'nombre'      => 'XML-RPC está cerrado',
				'problema'    => 'XML-RPC está abierto',
				'porque'      => 'Es una puerta vieja de WordPress que casi ningún sitio usa hoy. Por ahí se '
					. 'pueden probar cientos de contraseñas de un solo golpe, y el sitio lo ve como un intento, '
					. 'no como cientos — así que ni se entera de que lo están atacando. Se mide desde fuera, '
					. 'porque lo que importa no es si WordPress lo tiene habilitado sino si la petición le llega: '
					. 'en Kinsta el servidor la corta antes.',
				'comprobador' => 'xmlrpc_cerrado',
				'severidad'   => 'media',
				'arreglable'  => 1,
				'orden'       => 50,
				'grupo'       => 'Cuentas y accesos',
			],
			'registro_cerrado' => [
				'nombre'      => 'El registro de usuarios está cerrado',
				'problema'    => 'Cualquiera puede crearse una cuenta',
				'porque'      => 'Un sitio informativo con el registro abierto termina lleno de cuentas que '
					. 'nadie creó a propósito, y es por donde entra el spam.',
				'comprobador' => 'registro_cerrado',
				'severidad'   => 'media',
				'arreglable'  => 1,
				'orden'       => 60,
				'grupo'       => 'Cuentas y accesos',
			],
			'sin_usuario_admin' => [
				'nombre'      => 'Ninguna cuenta tiene un nombre adivinable',
				'problema'    => 'Hay una cuenta con un nombre de los que se prueban primero',
				'porque'      => 'Para entrar a WordPress hacen falta dos datos: el nombre de usuario y la '
					. 'contraseña. Un ataque automático tiene que adivinar los dos — pero «admin», '
					. '«administrator» y «webmaster» son los primeros que prueba siempre. Si una cuenta se llama '
					. 'así, ya no adivina nada: solo le queda la contraseña. Es media cerradura regalada.',
				'comprobador' => 'sin_usuario_admin',
				'severidad'   => 'media',
				'arreglable'  => 0,
				'orden'       => 70,
				'grupo'       => 'Cuentas y accesos',
			],
			'usuarios_enumerables' => [
				'nombre'      => 'El sitio no publica sus nombres de usuario',
				'problema'    => 'El sitio publica sus nombres de usuario',
				'porque'      => 'WordPress trae una dirección —/wp-json/wp/v2/users— que entrega la lista de '
					. 'nombres de usuario sin pedir contraseña. No es una falla del sitio: WordPress no considera '
					. 'privados los nombres de usuario, y así viene de fábrica. Pero mientras esté abierta, la '
					. 'norma de arriba no sirve de mucho: da igual cómo se llame la cuenta si el sitio publica el '
					. 'nombre. Se cierra con una línea de código en el tema.',
				'comprobador' => 'usuarios_enumerables',
				'severidad'   => 'media',
				'arreglable'  => 0,
				'orden'       => 75,
				'grupo'       => 'Cuentas y accesos',
			],
			// ── Lo que se ve desde fuera: archivos que no deberían estar ──
			//
			// Todas las mide SWPK_Sonda con una petición sin credenciales, y
			// todas exigen que el CONTENIDO calce, no solo el código HTTP.
			// Un 200 con la página de error del tema no es un hallazgo.
			'config_respaldo' => [
				'nombre'      => 'No hay copias de wp-config.php accesibles',
				'problema'    => 'Hay una copia de wp-config.php que se puede descargar',
				'porque'      => 'Ese archivo tiene la contraseña de la base de datos y las llaves de las '
					. 'sesiones. El servidor sabe no servir el original, pero una copia llamada '
					. '«wp-config.php.bak» ya no es un .php: es un archivo de texto, y lo entrega entero a '
					. 'quien lo pida. Suelen quedar de una edición apurada.',
				'comprobador' => 'ruta_config_respaldo',
				'severidad'   => 'alta',
				'arreglable'  => 0,
				'orden'       => 110,
				'grupo'       => 'Archivos a la vista',
			],
			'debug_log' => [
				'nombre'      => 'El registro de errores no es accesible',
				'problema'    => 'El registro de errores se puede leer desde internet',
				'porque'      => 'Cuando se deja el modo de depuración encendido, WordPress escribe los '
					. 'errores en wp-content/debug.log. Ahí salen rutas del servidor, consultas a la base de '
					. 'datos y a veces llaves de API. Es un archivo de texto en una carpeta pública.',
				'comprobador' => 'ruta_debug_log',
				'severidad'   => 'alta',
				'arreglable'  => 0,
				'orden'       => 115,
				'grupo'       => 'Archivos a la vista',
			],
			'volcados' => [
				'nombre'      => 'No hay respaldos descargables en la raíz',
				'problema'    => 'Hay un respaldo del sitio o de la base que se puede descargar',
				'porque'      => 'Un backup.zip o un database.sql en la raíz entrega el sitio entero —y con '
					. 'él todos los datos de los clientes— a quien adivine el nombre. Y los nombres son '
					. 'siempre los mismos.',
				'comprobador' => 'ruta_volcados',
				'severidad'   => 'alta',
				'arreglable'  => 0,
				'orden'       => 120,
				'grupo'       => 'Archivos a la vista',
			],
			'phpinfo' => [
				'nombre'      => 'No hay páginas de diagnóstico expuestas',
				'problema'    => 'Hay una página de diagnóstico accesible',
				'porque'      => 'Un phpinfo.php o un adminer.php dejados de una revisión publican la '
					. 'configuración entera del servidor: rutas, versiones, extensiones y variables de '
					. 'entorno. Adminer además es una puerta directa a la base de datos.',
				'comprobador' => 'ruta_phpinfo',
				'severidad'   => 'alta',
				'arreglable'  => 0,
				'orden'       => 125,
				'grupo'       => 'Archivos a la vista',
			],
			'restos_instalador' => [
				'nombre'      => 'No quedaron restos de una migración',
				'problema'    => 'Quedaron archivos de una migración',
				'porque'      => 'Los plugins que mudan sitios dejan un installer.php y su registro. Si se '
					. 'olvidan ahí, cualquiera puede volver a correr la instalación sobre el sitio que ya '
					. 'está puesto. Es una de las formas más viejas de quedarse con un WordPress.',
				'comprobador' => 'ruta_restos_instalador',
				'severidad'   => 'alta',
				'arreglable'  => 0,
				'orden'       => 130,
				'grupo'       => 'Archivos a la vista',
			],
			'instalador_abierto' => [
				'nombre'      => 'Las páginas de instalación y reparación no se pueden usar',
				'problema'    => 'La página de instalación o la de reparación responde',
				'porque'      => 'install.php y repair.php son las dos puertas de mantenimiento de '
					. 'WordPress. La de reparación funciona SIN iniciar sesión si está habilitada en la '
					. 'configuración, y da acceso a la base de datos. El panel solo mira si pintan el '
					. 'formulario; nunca les envía nada.',
				'comprobador' => 'ruta_instalador',
				'severidad'   => 'alta',
				'arreglable'  => 0,
				'orden'       => 135,
				'grupo'       => 'Archivos a la vista',
			],
			'delatores' => [
				'nombre'      => 'No hay archivos de desarrollo publicados',
				'problema'    => 'Hay archivos de desarrollo accesibles',
				'porque'      => 'Un .git deja bajar el historial entero del código. Un .env trae las '
					. 'contraseñas. Un composer.json o un package.json dicen la versión exacta de cada '
					. 'dependencia, que es lo que alguien necesita para buscar cuál tiene una falla '
					. 'publicada.',
				'comprobador' => 'ruta_delatores',
				'severidad'   => 'alta',
				'arreglable'  => 0,
				'orden'       => 140,
				'grupo'       => 'Archivos a la vista',
			],
			'listado_de_carpetas' => [
				'nombre'      => 'Las carpetas no listan su contenido',
				'problema'    => 'Una carpeta lista su contenido',
				'porque'      => 'Si al pedir una carpeta el servidor muestra la lista de archivos, no hace '
					. 'falta adivinar ningún nombre: ahí está todo lo que subió la gente por los '
					. 'formularios. No es control de acceso, es comodidad para buscar.',
				'comprobador' => 'ruta_listado',
				'severidad'   => 'media',
				'arreglable'  => 0,
				'orden'       => 145,
				'grupo'       => 'Archivos a la vista',
			],
			'autor_enumerable' => [
				'nombre'      => 'No se puede averiguar un nombre de usuario por la dirección',
				'problema'    => 'La dirección /?author=1 revela un nombre de usuario',
				'porque'      => 'Pidiendo /?author=1 el sitio redirige a /author/nombre-real/ y ahí queda el '
					. 'nombre de la cuenta. Es la otra mitad de la norma de arriba: cerrar solo la lista de '
					. 'usuarios y dejar esto abierto no cierra nada.',
				'comprobador' => 'autor_enumerable',
				'severidad'   => 'media',
				'arreglable'  => 0,
				'orden'       => 76,
				'grupo'       => 'Archivos a la vista',
			],
			'readme_expuesto' => [
				'nombre'      => 'El readme de WordPress no está accesible',
				'problema'    => 'El readme de WordPress se puede leer',
				'porque'      => 'Dice la versión de WordPress sin tener que deducirla. WordPress lo vuelve '
					. 'a crear en cada actualización, así que borrarlo no es definitivo — por eso está como '
					. 'baja: molesta, no abre nada.',
				'comprobador' => 'ruta_readme',
				'severidad'   => 'baja',
				'arreglable'  => 0,
				'orden'       => 150,
				'grupo'       => 'Archivos a la vista',
			],
			'trace_habilitado' => [
				'nombre'      => 'El método TRACE está deshabilitado',
				'problema'    => 'El método TRACE responde',
				'porque'      => 'TRACE hace que el servidor repita la petición tal cual, cabeceras '
					. 'incluidas. Ningún sitio lo necesita y es una ayuda para robar cookies.',
				'comprobador' => 'trace',
				'severidad'   => 'baja',
				'arreglable'  => 0,
				'orden'       => 155,
				'grupo'       => 'Archivos a la vista',
			],
			'sitio_indexable' => [
				'nombre'      => 'El sitio deja que los buscadores lo indexen',
				'problema'    => 'El sitio le pide a los buscadores que no lo indexen',
				'porque'      => 'Un sitio vivo que pide no ser indexado casi siempre es un descuido: se '
					. 'quedó el ajuste de cuando estaba en construcción, y el cliente no aparece en Google '
					. 'sin saber por qué.',
				'comprobador' => 'indexable',
				'severidad'   => 'baja',
				'arreglable'  => 0,
				'orden'       => 160,
				'grupo'       => 'Archivos a la vista',
			],

			// ── Lo que se ve desde fuera: lo que el navegador recibe ──────
			'certificado' => [
				'nombre'      => 'El certificado es válido y no está por vencer',
				'problema'    => 'El certificado tiene un problema',
				'porque'      => 'Si el certificado no es válido para el nombre que se pidió, el navegador '
					. 'planta una pantalla roja antes de dejar entrar. Kinsta los renueva solo, así que un '
					. 'fallo aquí casi siempre es un dominio que se agregó sin su nombre.',
				'comprobador' => 'certificado',
				'severidad'   => 'alta',
				'arreglable'  => 0,
				'orden'       => 200,
				'grupo'       => 'Lo que el navegador recibe',
			],
			'cors_abierto' => [
				'nombre'      => 'El sitio no se abre a cualquier origen con credenciales',
				'problema'    => 'El sitio acepta peticiones con credenciales desde cualquier origen',
				'porque'      => 'Con esa combinación, cualquier página que visite alguien con la sesión '
					. 'iniciada en el sitio puede leer sus datos. Solo cuenta cuando van las dos cosas '
					. 'juntas: permitir cualquier origen Y permitir credenciales. Un permiso abierto a '
					. 'secas, en contenido público, no es un problema.',
				'comprobador' => 'cors',
				'severidad'   => 'alta',
				'arreglable'  => 0,
				'orden'       => 205,
				'grupo'       => 'Lo que el navegador recibe',
			],
			'hsts' => [
				'nombre'      => 'El sitio exige HTTPS en las siguientes visitas',
				'problema'    => 'El sitio no exige HTTPS en las siguientes visitas',
				'porque'      => 'Redirigir a HTTPS no alcanza: la primera petición ya salió en claro. Esta '
					. 'cabecera le dice al navegador que la próxima vez vaya directo a HTTPS sin preguntar. '
					. 'Varía de verdad entre sitios, no es algo que ponga la plataforma.',
				'comprobador' => 'hsts',
				'severidad'   => 'alta',
				'arreglable'  => 0,
				'orden'       => 210,
				'grupo'       => 'Lo que el navegador recibe',
			],
			'bloqueo_de_marco' => [
				'nombre'      => 'El sitio no se puede meter dentro de otra página',
				'problema'    => 'El sitio se puede meter dentro de otra página',
				'porque'      => 'Sin esto, alguien puede cargar el sitio invisible dentro del suyo y hacer '
					. 'que la gente haga clic en botones que no ve. Se cierra con una cabecera y no rompe '
					. 'nada mientras el sitio no se embeba a sí mismo.',
				'comprobador' => 'marco',
				'severidad'   => 'alta',
				'arreglable'  => 0,
				'orden'       => 215,
				'grupo'       => 'Lo que el navegador recibe',
			],
			'contenido_mixto' => [
				'nombre'      => 'La portada no carga recursos sin cifrar',
				'problema'    => 'La portada carga recursos por http://',
				'porque'      => 'Una página segura que carga una imagen o un script por http deja de ser '
					. 'segura para esa parte: se puede interceptar y cambiar por el camino. Solo se cuentan '
					. 'los recursos, no los enlaces a otros sitios.',
				'comprobador' => 'mixto',
				'severidad'   => 'media',
				'arreglable'  => 0,
				'orden'       => 220,
				'grupo'       => 'Lo que el navegador recibe',
			],
			'dominio_canonico' => [
				'nombre'      => 'El sitio responde por un solo nombre',
				'problema'    => 'El sitio responde igual con www y sin www',
				'porque'      => 'Si los dos nombres contestan 200, Google ve dos sitios con el mismo '
					. 'contenido, las cookies quedan repartidas y la exigencia de HTTPS se aplica a un '
					. 'nombre y no al otro. Uno tiene que redirigir al otro.',
				'comprobador' => 'canonico',
				'severidad'   => 'media',
				'arreglable'  => 0,
				'orden'       => 225,
				'grupo'       => 'Lo que el navegador recibe',
			],
			'csp' => [
				'nombre'      => 'Hay una política de contenido con lo mínimo',
				'problema'    => 'No hay política de contenido, o no trae lo mínimo',
				'porque'      => 'Es lo que limita de dónde puede cargar código la página. En un sitio hecho '
					. 'con Elementor no se puede exigir la versión estricta, así que esta norma pide solo '
					. 'las dos piezas que no rompen nada: que no se puedan cargar objetos y que nadie pueda '
					. 'cambiar la dirección base de la página. Medir solo que la cabecera «exista» sería '
					. 'engañarnos: una política con todo permitido no protege de nada.',
				'comprobador' => 'csp',
				'severidad'   => 'media',
				'arreglable'  => 0,
				'orden'       => 230,
				'grupo'       => 'Lo que el navegador recibe',
			],
			'referrer_policy' => [
				'nombre'      => 'El sitio no filtra a dónde venía la gente',
				'problema'    => 'El sitio manda la dirección completa a otros sitios',
				'porque'      => 'Sin esta cabecera, cada vez que alguien hace clic hacia otro sitio, el '
					. 'otro sitio recibe la dirección exacta de donde venía — con lo que lleve en la '
					. 'dirección.',
				'comprobador' => 'referrer',
				'severidad'   => 'baja',
				'arreglable'  => 0,
				'orden'       => 235,
				'grupo'       => 'Lo que el navegador recibe',
			],
			'permissions_policy' => [
				'nombre'      => 'El sitio declara qué funciones del navegador no usa',
				'problema'    => 'El sitio no declara qué funciones del navegador no usa',
				'porque'      => 'Apaga de antemano cámara, micrófono y ubicación para todo lo que cargue la '
					. 'página, incluidos los scripts de terceros. Va como baja a propósito: no hay un valor '
					. 'acordado y una lista demasiado estricta rompe los videos y los mapas embebidos.',
				'comprobador' => 'permissions',
				'severidad'   => 'baja',
				'arreglable'  => 0,
				'orden'       => 240,
				'grupo'       => 'Lo que el navegador recibe',
			],
			'xss_obsoleta' => [
				'nombre'      => 'No se manda la cabecera X-XSS-Protection',
				'problema'    => 'Se manda la cabecera X-XSS-Protection',
				'porque'      => 'Es una cabecera retirada. Los navegadores la ignoran o, peor, el filtro '
					. 'que encendía llegó a abrir fallas propias. Muchas guías viejas todavía la piden; hoy '
					. 'lo correcto es no mandarla. Si se manda, que sea en cero.',
				'comprobador' => 'xss_obsoleta',
				'severidad'   => 'baja',
				'arreglable'  => 0,
				'orden'       => 245,
				'grupo'       => 'Lo que el navegador recibe',
			],
			// ── Lo que ya está en el inventario, sin pedir nada ───────────
			//
			// Estas tres salen de la foto diaria de plugins y del inventario de
			// sitios, que la corrida ya guarda. Volver a pedirlas a Kinsta
			// serían 108 peticiones para traer lo que está en la base.
			'sin_vulnerables' => [
				'nombre'      => 'Ningún plugin o tema en versión con falla conocida',
				'problema'    => 'Hay plugins o temas en una versión con falla conocida',
				'porque'      => 'No es que pueda pasar algo: es que ya está publicado qué falla, dónde y cómo. '
					. 'Kinsta marca cada plugin contra la base de vulnerabilidades, así que esto no hay que '
					. 'deducirlo. Es la categoría con más incidencia del OWASP Top 10 de 2025.',
				'comprobador' => 'inv_vulnerables',
				'severidad'   => 'alta',
				'arreglable'  => 0,
				'orden'       => 12,
				'grupo'       => 'Versiones',
			],
			'sin_inactivos' => [
				'nombre'      => 'No hay plugins instalados pero apagados',
				'problema'    => 'Hay plugins instalados pero apagados',
				'porque'      => 'Un plugin apagado sigue con sus archivos en el servidor, y esos archivos se '
					. 'pueden pedir desde internet igual que los de uno encendido. Apagarlo no lo quita de en '
					. 'medio: hay que borrarlo. Y como está apagado, nadie se acuerda de actualizarlo.',
				'comprobador' => 'inv_inactivos',
				'severidad'   => 'media',
				'arreglable'  => 0,
				'orden'       => 14,
				'grupo'       => 'Versiones',
			],
			'sitio_sano' => [
				'nombre'      => 'El sitio no está bloqueado en la plataforma',
				'problema'    => 'El sitio está bloqueado o en un estado raro',
				'porque'      => 'Kinsta puede bloquear un sitio por falta de pago, por abuso de recursos o '
					. 'porque detectó algo. Si pasa, el panel tiene que decirlo antes de que lo diga el cliente.',
				'comprobador' => 'inv_estado',
				'severidad'   => 'alta',
				'arreglable'  => 0,
				'orden'       => 16,
				'grupo'       => 'Versiones',
			],

			// ── Lo que hay que pedirle a la plataforma ────────────────────
			'https_forzado' => [
				'nombre'      => 'La plataforma fuerza HTTPS',
				'problema'    => 'La plataforma no fuerza HTTPS',
				'porque'      => 'Que el sitio redirija a HTTPS por su cuenta no es lo mismo que el servidor lo '
					. 'exija. Lo segundo no se puede desactivar desde dentro de WordPress ni romper con un '
					. 'plugin.',
				'comprobador' => 'rev_https',
				'severidad'   => 'alta',
				'arreglable'  => 0,
				'orden'       => 250,
				'grupo'       => 'El hosting',
			],
			'respaldo_reciente' => [
				'nombre'      => 'Hay un respaldo de las últimas 48 horas',
				'problema'    => 'No hay un respaldo reciente',
				'porque'      => 'Es lo único que convierte un desastre en un mal rato. Kinsta respalda a diario '
					. 'y guarda dos semanas; si el último es de hace más de dos días, algo se rompió en ese '
					. 'proceso y nadie se enteró.',
				'comprobador' => 'rev_respaldo',
				'severidad'   => 'alta',
				'arreglable'  => 0,
				'orden'       => 255,
				'grupo'       => 'El hosting',
			],
			'trafico_normal' => [
				'nombre'      => 'El tráfico está dentro de lo normal de este sitio',
				'problema'    => 'El tráfico se salió de lo normal de este sitio',
				'porque'      => 'Un pico puede ser una pauta publicitaria o puede ser un ataque, y de las dos '
					. 'maneras hay que verlo. La comparación no es contra un número fijo sino contra lo que ESE '
					. 'sitio hace normalmente: la mediana de los treinta días anteriores. Se usa la mediana y no '
					. 'el promedio porque un día de pico arrastra el promedio y entonces el siguiente ya no se '
					. 'nota.',
				'comprobador' => 'rev_trafico',
				'severidad'   => 'media',
				'arreglable'  => 0,
				'orden'       => 260,
				'grupo'       => 'El hosting',
			],
			'errores_servidor' => [
				'nombre'      => 'El sitio no está devolviendo errores de servidor',
				'problema'    => 'El sitio está devolviendo errores de servidor',
				'porque'      => 'Un goteo de errores 500 es un sitio que a ratos no funciona, y el cliente lo '
					. 'nota antes que nosotros si nadie lo mira.',
				'comprobador' => 'rev_errores',
				'severidad'   => 'media',
				'arreglable'  => 0,
				'orden'       => 265,
				'grupo'       => 'El hosting',
			],
			'oleada_de_login' => [
				'nombre'      => 'No hay una oleada de intentos de entrada',
				'problema'    => 'Hay una oleada de intentos de entrada',
				'porque'      => 'Alguien probando contraseñas en serie deja rastro en el registro de accesos. '
					. 'Que aparezca no significa que hayan entrado; significa que lo están intentando y que vale '
					. 'la pena mirar las cuentas de ese sitio.',
				'comprobador' => 'rev_logins',
				'severidad'   => 'media',
				'arreglable'  => 0,
				'orden'       => 270,
				'grupo'       => 'El hosting',
			],
			'proteccion_bots' => [
				'nombre'      => 'La protección contra bots está encendida',
				'problema'    => 'La protección contra bots está apagada',
				'porque'      => 'Kinsta filtra tráfico automático en su borde, antes de que llegue al sitio. '
					. 'Viene encendida en el nivel básico; si está apagada, alguien la apagó.',
				'comprobador' => 'rev_bots',
				'severidad'   => 'media',
				'arreglable'  => 0,
				'orden'       => 275,
				'grupo'       => 'El hosting',
			],
			'excepciones_bots' => [
				'nombre'      => 'No hay excepciones abiertas en la protección contra bots',
				'problema'    => 'Hay excepciones en la protección contra bots',
				'porque'      => 'Una excepción apaga la protección para lo que diga la regla. Suelen ponerse '
					. 'para arreglar algo un día y quedarse ahí para siempre. No es que estén mal: es que hay '
					. 'que saber que están y revisarlas.',
				'comprobador' => 'rev_excepciones',
				'severidad'   => 'media',
				'arreglable'  => 0,
				'orden'       => 280,
				'grupo'       => 'El hosting',
			],
			'sftp_adicional' => [
				'nombre'      => 'No hay cuentas SFTP extra sin revisar',
				'problema'    => 'Hay cuentas SFTP extra',
				'porque'      => 'Cada cuenta extra es alguien más que puede escribir archivos en el sitio. '
					. 'Suelen crearse para un proveedor externo y nadie las borra cuando el trabajo termina.',
				'comprobador' => 'rev_sftp',
				'severidad'   => 'media',
				'arreglable'  => 0,
				'orden'       => 285,
				'grupo'       => 'El hosting',
			],
			'reglas_redireccion' => [
				'nombre'      => 'Las redirecciones de la plataforma están revisadas',
				'problema'    => 'Hay redirecciones en la plataforma sin revisar',
				'porque'      => 'Las redirecciones del servidor no se ven desde WordPress: alguien las pone en '
					. 'el panel de Kinsta y ahí quedan. Una mal hecha manda visitas a otro sitio sin que nada en '
					. 'el WordPress lo delate.',
				'comprobador' => 'rev_redirecciones',
				'severidad'   => 'media',
				'arreglable'  => 0,
				'orden'       => 290,
				'grupo'       => 'El hosting',
			],
			'rastreadores_ia' => [
				'nombre'      => 'La política de rastreadores de IA está decidida',
				'problema'    => 'No se ha decidido qué hacer con los rastreadores de IA',
				'porque'      => 'Kinsta puede bloquear los robots que recogen contenido para entrenar modelos. '
					. 'No es seguridad, es una decisión del cliente sobre su contenido — por eso va como baja. '
													. 'Pero conviene que esté tomada a propósito y no por omisión.',
				'comprobador' => 'rev_ia',
				'severidad'   => 'baja',
				'arreglable'  => 0,
				'orden'       => 295,
				'grupo'       => 'El hosting',
			],
			// ── Los archivos que sube la gente por un formulario ─────────
			//
			// Es lo que el jefe pidió con el ejemplo del CV, y lo mide el
			// agente desde dentro: para probar que un archivo se baja hay que
			// saber cómo se llama, y adivinarlo desde fuera sería andar
			// pescando el currículum de alguien. El agente lo prueba adentro y
			// manda solo el sí o el no — el nombre del archivo no sale del
			// sitio.
			'subidas_publicas' => [
				'nombre'      => 'Los archivos subidos por formulario no se bajan sin permiso',
				'problema'    => 'Un archivo subido por formulario se baja sin permiso',
				'porque'      => 'Es el error más caro y el más común: se hace un formulario que pide subir un '
					. 'CV, el archivo cae en una carpeta pública, y queda a la vista de cualquiera que dé con la '
					. 'dirección. Ahí van nombres, teléfonos, direcciones y a veces números de documento.',
				'comprobador' => 'form_publicos',
				'severidad'   => 'alta',
				'arreglable'  => 0,
				'orden'       => 20,
				'grupo'       => 'Formularios y subidas',
			],
			'htaccess_inerte' => [
				'nombre'      => 'Ningún plugin confía en una protección que aquí no funciona',
				'problema'    => 'Un plugin dejó una protección que en este servidor no hace nada',
				'porque'      => 'Contact Form 7, Gravity Forms y WPForms escriben un archivo .htaccess en su '
					. 'carpeta de subidas para cerrarla. Funciona en Apache. Este servidor corre Nginx y ni '
					. 'siquiera lee ese archivo, así que la carpeta queda abierta mientras el plugin cree que la '
					. 'cerró. Lo único que protege los archivos es lo difícil que sea adivinar su dirección.',
				'comprobador' => 'form_htaccess',
				'severidad'   => 'alta',
				'arreglable'  => 0,
				'orden'       => 22,
				'grupo'       => 'Formularios y subidas',
			],
			'php_en_subidas_archivos' => [
				'nombre'      => 'No hay archivos PHP en la carpeta de subidas',
				'problema'    => 'Hay archivos PHP en la carpeta de subidas',
				'porque'      => 'Ahí solo deberían caer imágenes y documentos. Un archivo PHP en esa carpeta no '
					. 'llegó subiendo una foto: o alguien lo puso, o entró por una falla de subida. Es la señal '
					. 'más directa de que algo pasó.',
				'comprobador' => 'form_php_sueltos',
				'severidad'   => 'alta',
				'arreglable'  => 0,
				'orden'       => 24,
				'grupo'       => 'Formularios y subidas',
			],
			// ── Lo que solo se ve estando dentro ─────────────────────────
			//
			// La investigación sacó cuarenta y cinco comprobaciones posibles
			// aquí: dieciocho constantes de wp-config, nueve de usuarios,
			// catorce de integridad y cuatro de registro. Están todas, pero
			// agrupadas en dieciséis normas que significan algo. Cuarenta y
			// cinco filas por sitio, en 108 sitios, no las mira nadie — y una
			// norma que nadie mira es peor que ninguna, porque da la impresión
			// de que se está revisando. Cuando una falla, el detalle dice cuál
			// de las comprobaciones de dentro fue.
			'llaves_unicas' => [
				'nombre'      => 'Las llaves de sesión son propias de este sitio',
				'problema'    => 'Las llaves de sesión faltan, son las de ejemplo o se repiten en otro sitio',
				'porque'      => 'Son las ocho frases con las que WordPress firma las sesiones. Si son las que '
					. 'vienen de ejemplo, cualquiera que sepa eso puede fabricarse una sesión de administrador. '
					. 'Y si dos sitios nuestros tienen las mismas, una sesión robada en uno sirve en el otro — '
					. 'que es lo que pasa cuando un sitio se clona de otro para arrancar rápido.',
				'comprobador' => 'fondo_llaves',
				'severidad'   => 'alta',
				'arreglable'  => 0,
				'orden'       => 30,
				'grupo'       => 'Dentro del sitio',
			],
			'modo_depuracion' => [
				'nombre'      => 'El modo de depuración está apagado',
				'problema'    => 'El modo de depuración está encendido',
				'porque'      => 'Con esto encendido, los errores de PHP salen impresos en la página o se '
					. 'escriben en un archivo dentro de la carpeta pública. En los dos casos se publican rutas '
					. 'del servidor, consultas a la base de datos y a veces llaves. Se enciende para arreglar '
					. 'algo un día y se olvida.',
				'comprobador' => 'fondo_depuracion',
				'severidad'   => 'alta',
				'arreglable'  => 0,
				'orden'       => 32,
				'grupo'       => 'Dentro del sitio',
			],
			'puertas_de_mantenimiento' => [
				'nombre'      => 'Las puertas de mantenimiento están cerradas',
				'problema'    => 'Hay una puerta de mantenimiento abierta',
				'porque'      => 'Dos constantes de wp-config.php abren cosas que no deberían estar abiertas '
					. 'nunca en un sitio vivo: una deja usar la página de reparación de la base de datos SIN '
					. 'iniciar sesión, y la otra apaga el filtro que impide subir cualquier tipo de archivo.',
				'comprobador' => 'fondo_puertas',
				'severidad'   => 'alta',
				'arreglable'  => 0,
				'orden'       => 34,
				'grupo'       => 'Dentro del sitio',
			],
			'actualizaciones_automaticas' => [
				'nombre'      => 'Las actualizaciones automáticas del núcleo están encendidas',
				'problema'    => 'Las actualizaciones automáticas del núcleo están apagadas',
				'porque'      => 'Las de seguridad de WordPress llegan solas si nadie las apaga. Apagarlas deja '
					. 'el sitio esperando a que una persona se acuerde, y es el camino más corto a una versión '
					. 'con una falla publicada.',
				'comprobador' => 'fondo_actualizaciones',
				'severidad'   => 'alta',
				'arreglable'  => 0,
				'orden'       => 36,
				'grupo'       => 'Dentro del sitio',
			],
			'admin_por_https' => [
				'nombre'      => 'El escritorio se sirve solo por HTTPS',
				'problema'    => 'El escritorio puede servirse sin cifrar',
				'porque'      => 'Es una línea en wp-config.php que obliga a que la administración vaya cifrada '
					. 'siempre. Sin ella, en una red cualquiera la contraseña de un administrador puede viajar '
					. 'en claro.',
				'comprobador' => 'fondo_admin_https',
				'severidad'   => 'alta',
				'arreglable'  => 0,
				'orden'       => 38,
				'grupo'       => 'Dentro del sitio',
			],
			'credenciales_en_config' => [
				'nombre'      => 'No hay contraseñas guardadas en wp-config.php',
				'problema'    => 'Hay contraseñas guardadas en wp-config.php',
				'porque'      => 'Las credenciales de FTP que WordPress deja guardar ahí quedan en texto plano '
					. 'en un archivo que, si alguna vez se copia mal o se respalda a la carpeta pública, las '
					. 'entrega enteras.',
				'comprobador' => 'fondo_credenciales',
				'severidad'   => 'alta',
				'arreglable'  => 0,
				'orden'       => 40,
				'grupo'       => 'Dentro del sitio',
			],
			'roles_sin_permisos_de_mas' => [
				'nombre'      => 'Ningún rol tiene permisos que no le tocan',
				'problema'    => 'Hay un rol con permisos que no le tocan',
				'porque'      => 'Un rol de editor o de autor al que alguien le agregó «instalar plugins» o '
					. '«editar usuarios» es un administrador con otro nombre. Suele pasarse para resolver algo '
					. 'puntual y quedarse puesto para siempre.',
				'comprobador' => 'fondo_permisos',
				'severidad'   => 'alta',
				'arreglable'  => 0,
				'orden'       => 42,
				'grupo'       => 'Dentro del sitio',
			],
			'rol_por_omision' => [
				'nombre'      => 'Quien se registre entra con el rol más bajo',
				'problema'    => 'Quien se registre entra con un rol con privilegios',
				'porque'      => 'Si el registro está abierto y el rol por omisión no es el más bajo, cualquiera '
					. 'que se cree una cuenta entra con permisos. Las dos cosas por separado se pueden defender; '
					. 'juntas, no.',
				'comprobador' => 'fondo_rol_omision',
				'severidad'   => 'alta',
				'arreglable'  => 0,
				'orden'       => 44,
				'grupo'       => 'Dentro del sitio',
			],
			'administradores_contados' => [
				'nombre'      => 'La cantidad de administradores está controlada',
				'problema'    => 'Hay más administradores de los esperados',
				'porque'      => 'Cada administrador puede cambiarlo todo y apagar lo que sea. No es que estén '
					. 'mal: es que hay que saber cuántos son y que cada uno siga haciendo falta.',
				'comprobador' => 'fondo_administradores',
				'severidad'   => 'media',
				'arreglable'  => 0,
				'orden'       => 46,
				'grupo'       => 'Dentro del sitio',
			],
			'claves_de_aplicacion' => [
				'nombre'      => 'Las contraseñas de aplicación están contadas',
				'problema'    => 'Hay contraseñas de aplicación sin revisar',
				'porque'      => 'Son llaves que entran al sitio por la API SIN pasar por la verificación en dos '
					. 'pasos. No son malas —el propio panel usa una— pero una que quedó de un trabajo viejo es '
					. 'una puerta abierta que nadie vigila.',
				'comprobador' => 'fondo_claves_app',
				'severidad'   => 'media',
				'arreglable'  => 0,
				'orden'       => 48,
				'grupo'       => 'Dentro del sitio',
			],
			'nucleo_sin_tocar' => [
				'nombre'      => 'Los archivos de WordPress no están modificados',
				'problema'    => 'Hay archivos de WordPress modificados o faltantes',
				'porque'      => 'Se comparan los archivos del núcleo contra las sumas oficiales de WordPress.org, '
					. 'una por una. Un archivo del núcleo cambiado no lo cambió una actualización: lo cambió '
					. 'alguien. Es la respuesta al caso de un sitio que el hosting mandó revisar por infección y '
					. 'donde ellos no encontraron nada.',
				'comprobador' => 'fondo_nucleo',
				'severidad'   => 'alta',
				'arreglable'  => 0,
				'orden'       => 50,
				'grupo'       => 'Dentro del sitio',
			],
			'plugins_cerrados' => [
				'nombre'      => 'Ningún plugin fue cerrado por WordPress.org',
				'problema'    => 'Hay un plugin que WordPress.org cerró',
				'porque'      => 'Cuando WordPress.org cierra un plugin por un problema de seguridad, el sitio '
					. 'NO se entera: en el escritorio sigue apareciendo «al día» y Site Health no dice nada. Es '
					. 'un hueco que no ve nadie salvo que se pregunte expresamente, y eso es lo que hace esta '
					. 'norma.',
				'comprobador' => 'fondo_cerrados',
				'severidad'   => 'alta',
				'arreglable'  => 0,
				'orden'       => 52,
				'grupo'       => 'Dentro del sitio',
			],
			'plugins_abandonados' => [
				'nombre'      => 'Ningún plugin lleva años sin actualizarse',
				'problema'    => 'Hay plugins que llevan años sin actualizarse',
				'porque'      => 'Un plugin sin una versión en dos años no es necesariamente inseguro, pero sí es '
					. 'uno al que nadie le va a arreglar la próxima falla. El umbral de dos años es criterio '
					. 'nuestro: Patchstack se niega a dar un número y dice que un proyecto con comunidad activa '
					. 'y sin versión en un año puede estar bien.',
				'comprobador' => 'fondo_abandonados',
				'severidad'   => 'alta',
				'arreglable'  => 0,
				'orden'       => 54,
				'grupo'       => 'Dentro del sitio',
			],
			'drop_ins_esperados' => [
				'nombre'      => 'No hay archivos que se cargan antes que WordPress',
				'problema'    => 'Hay archivos que se cargan antes que WordPress',
				'porque'      => 'WordPress carga un puñado de archivos especiales antes que nada, si están. '
					. 'Algunos los pone la caché y son normales; otros no los puso nadie que debiera. Como '
					. 'corren antes que todo lo demás, nada de lo que venga después los puede frenar.',
				'comprobador' => 'fondo_dropins',
				'severidad'   => 'alta',
				'arreglable'  => 0,
				'orden'       => 56,
				'grupo'       => 'Dentro del sitio',
			],
			'tareas_programadas' => [
				'nombre'      => 'No hay tareas programadas sin dueño',
				'problema'    => 'Hay tareas programadas sin dueño',
				'porque'      => 'Una tarea cuyo gancho no reconoce ningún plugin instalado casi siempre quedó '
					. 'de uno que se borró, y es inofensiva. Pero es también la forma más discreta de dejar algo '
					. 'corriendo en un sitio, así que conviene que una persona las mire.',
				'comprobador' => 'fondo_tareas',
				'severidad'   => 'media',
				'arreglable'  => 0,
				'orden'       => 58,
				'grupo'       => 'Dentro del sitio',
			],
			'registro_de_accesos' => [
				'nombre'      => 'El sitio guarda quién entró y qué tocó',
				'problema'    => 'El sitio no guarda quién entró y qué tocó',
				'porque'      => 'El registro de la plataforma y el del sitio no son lo mismo: el de Kinsta no '
					. 've que alguien activó un plugin desde el escritorio. Sin un registro dentro del sitio, '
					. 'cuando pase algo no va a haber forma de saber quién ni cuándo.',
				'comprobador' => 'fondo_registro',
				'severidad'   => 'media',
				'arreglable'  => 0,
				'orden'       => 60,
				'grupo'       => 'Dentro del sitio',
			],
			// ── Solo para las tiendas ────────────────────────────────────
			//
			// `aplica_tipo` es el eje nuevo: estas no se miden en un sitio
			// informativo ni en un cotizador. Un sitio sin clasificar SÍ las
			// recibe, porque librarlo por no saber qué es sería esconder el
			// hueco en vez de verlo.
			'tienda_api_cerrada' => [
				'nombre'      => 'Los pedidos y los clientes no se leen sin permiso',
				'problema'    => 'Los pedidos o los clientes se leen sin permiso',
				'porque'      => 'WooCommerce publica direcciones que devuelven pedidos y clientes, y tienen que '
					. 'pedir credenciales. Si alguna contesta sin pedirlas, se está entregando el nombre, el '
					. 'correo, el teléfono y la dirección de cada persona que compró.',
				'comprobador' => 'tienda_api',
				'severidad'   => 'alta',
				'arreglable'  => 0,
				'aplica_tipo' => 'tienda,cotizador',
				'orden'       => 300,
				'grupo'       => 'Tienda',
			],
			'tienda_logs_cerrados' => [
				'nombre'      => 'Los registros de la tienda no se pueden descargar',
				'problema'    => 'Los registros de la tienda se pueden descargar',
				'porque'      => 'WooCommerce guarda ahí lo que le contestan las pasarelas, y cuando alguien '
					. 'enciende la depuración de una pasarela eso incluye el contenido de las peticiones. El '
					. 'nombre del archivo lleva un hash — pero ese hash se calcula con una de las llaves de '
					. 'wp-config.php, y si esa llave no está puesta, WooCommerce usa una palabra que está '
					. 'escrita en su código público. Entonces el nombre se puede deducir.',
				'comprobador' => 'tienda_logs',
				'severidad'   => 'alta',
				'arreglable'  => 0,
				'aplica_tipo' => 'tienda,cotizador',
				'orden'       => 302,
				'grupo'       => 'Tienda',
			],
			'tienda_descargas_protegidas' => [
				'nombre'      => 'Los archivos que se venden no se bajan sin comprar',
				'problema'    => 'Los archivos que se venden se pueden bajar sin comprar',
				'porque'      => 'WooCommerce tiene tres formas de entregar un archivo comprado y una de ellas '
					. 'es redirigir a la dirección real. Su propia documentación lo dice: cualquiera con esa '
					. 'dirección accede al archivo, haya comprado o no.',
				'comprobador' => 'tienda_descargas',
				'severidad'   => 'alta',
				'arreglable'  => 0,
				'aplica_tipo' => 'tienda',
				'orden'       => 304,
				'grupo'       => 'Tienda',
			],
			'tienda_pedido_invitado' => [
				'nombre'      => 'Un pedido de invitado pide el correo antes de mostrarse',
				'problema'    => 'Un pedido de invitado se muestra sin pedir el correo',
				'porque'      => 'Quien compra sin cuenta recibe un enlace a su pedido. WooCommerce pide el '
					. 'correo antes de enseñarlo, salvo que algo lo haya desactivado — y entonces cualquiera con '
					. 'el enlace ve los datos de esa persona.',
				'comprobador' => 'tienda_invitados',
				'severidad'   => 'alta',
				'arreglable'  => 0,
				'aplica_tipo' => 'tienda',
				'orden'       => 306,
				'grupo'       => 'Tienda',
			],
			'tienda_sin_datos_de_tarjeta' => [
				'nombre'      => 'No se encontraron números de tarjeta guardados',
				'problema'    => 'Hay números de tarjeta guardados en texto plano',
				'porque'      => 'WooCommerce no guarda tarjetas por diseño, así que si aparece una es un '
					. 'accidente: una pasarela que volcó su depuración en el pedido, o alguien del equipo que '
					. 'pegó el número en una nota al tomar un pedido por teléfono. Tenerlas guardadas cambia por '
					. 'completo las obligaciones del cliente con su banco.',
				'comprobador' => 'tienda_tarjetas',
				'severidad'   => 'alta',
				'arreglable'  => 0,
				'aplica_tipo' => 'tienda',
				'orden'       => 308,
				'grupo'       => 'Tienda',
			],
			'tienda_webhooks_cifrados' => [
				'nombre'      => 'Los avisos automáticos de la tienda van cifrados',
				'problema'    => 'Hay avisos automáticos de la tienda que van sin cifrar',
				'porque'      => 'Los webhooks mandan el pedido entero —con los datos de quien compró— cada vez '
					. 'que pasa algo. Si la dirección es http y no https, eso viaja en claro.',
				'comprobador' => 'tienda_webhooks',
				'severidad'   => 'alta',
				'arreglable'  => 0,
				'aplica_tipo' => 'tienda,cotizador',
				'orden'       => 310,
				'grupo'       => 'Tienda',
			],
			'tienda_limpieza_viva' => [
				'nombre'      => 'La limpieza de datos personales funciona',
				'problema'    => 'La limpieza de datos personales está configurada pero no corre',
				'porque'      => 'WooCommerce puede borrar o anonimizar pedidos viejos solo. Dejarlo sin '
					. 'configurar es una decisión legítima —hay deberes contables— pero configurarlo y que la '
					. 'tarea esté muerta es peor que no tenerlo: nadie va a volver a mirarlo porque en la '
					. 'pantalla dice que está puesto.',
				'comprobador' => 'tienda_limpieza',
				'severidad'   => 'media',
				'arreglable'  => 0,
				'aplica_tipo' => 'tienda',
				'orden'       => 312,
				'grupo'       => 'Tienda',
			],
			'tienda_pasarela_en_vivo' => [
				'nombre'      => 'Las pasarelas de pago están en modo real',
				'problema'    => 'Hay una pasarela de pago en modo de pruebas',
				'porque'      => 'Una pasarela que se quedó en pruebas acepta pedidos que nunca cobran. No es un '
					. 'problema de seguridad: es dinero que no entró y que nadie notó hasta el cierre del mes.',
				'comprobador' => 'tienda_modo',
				'severidad'   => 'media',
				'arreglable'  => 0,
				'aplica_tipo' => 'tienda',
				'orden'       => 314,
				'grupo'       => 'Tienda',
			],
			'formularios_honeypot' => [
				'nombre'      => 'Los formularios tienen honeypot o captcha',
				'problema'    => 'Los formularios no tienen honeypot ni captcha',
				'porque'      => 'Sin eso, los formularios se llenan de spam automático y el correo del cliente '
					. 'se vuelve inservible. La mide el agente leyendo los ajustes globales de cada plugin de '
					. 'formularios, que es donde viven el captcha y el honeypot en casi todos. No se mira '
					. 'formulario por formulario a propósito: en Fluent Forms y en Contact Form 7 el ajuste es '
					. 'global, y mirar cada formulario diría «este no tiene» cuando sí.',
				'comprobador' => 'form_antispam',
				'severidad'   => 'media',
				'arreglable'  => 0,
				'orden'       => 80,
				'grupo'       => 'Tienda',
			],
			'wp_al_dia' => [
				'nombre'      => 'WordPress está en una versión con soporte',
				'problema'    => 'WordPress está en una versión sin soporte',
				'porque'      => 'Una versión vieja acumula fallas conocidas y publicadas. No es que pueda '
					. 'pasar algo: es que ya está escrito dónde y cómo.',
				'comprobador' => 'wp_al_dia',
				'severidad'   => 'media',
				'arreglable'  => 0,
				'orden'       => 90,
				'grupo'       => 'Tienda',
			],
			'php_soportado' => [
				'nombre'      => 'PHP está en una versión con soporte',
				'problema'    => 'PHP está en una versión sin soporte',
				'porque'      => 'Una versión de PHP sin soporte ya no recibe parches de seguridad, así que las '
					. 'fallas que le encuentren de ahora en adelante se quedan sin arreglar.',
				'comprobador' => 'php_soportado',
				'severidad'   => 'media',
				'arreglable'  => 0,
				'orden'       => 100,
				'grupo'       => 'Tienda',
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
				'problema'    => $n['problema'],
				'porque'      => $n['porque'],
				'comprobador' => $n['comprobador'],
				'severidad'   => $n['severidad'],
				'arreglable'  => (int) $n['arreglable'],
				'orden'       => (int) $n['orden'],
				'aplica_tipo' => (string) ( $n['aplica_tipo'] ?? '' ),
				'grupo'       => (string) ( $n['grupo'] ?? '' ),
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
			'problema'  => sanitize_text_field( (string) ( $datos['problema'] ?? '' ) ),
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
				unset( $fila['nombre'], $fila['problema'], $fila['porque'] );
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

	/**
	 * Pone o quita la pausa de una norma. Ver APLAZADA.
	 *
	 * El motivo es obligatorio al pausar, y no por formalismo: una norma en
	 * pausa sin motivo escrito es una norma que dentro de seis meses nadie
	 * sabe por qué no se cuenta, y acaba quedándose así para siempre. El
	 * motivo sale escrito en cada sitio donde esa norma aparece.
	 */
	public static function aplazar( string $clave, bool $aplazada, string $porque = '' ): void {
		global $wpdb;

		$wpdb->update(
			SWPK_Tablas::tabla( 'normas' ),
			[
				'aplazada'        => $aplazada ? 1 : 0,
				'aplazada_porque' => $aplazada ? mb_substr( sanitize_text_field( $porque ), 0, 190 ) : '',
			],
			[ 'clave' => $clave ]
		);

		// Y se re-escribe lo ya medido, sin esperar al reporte de mañana.
		// Si no, la pantalla seguiría enseñando 83 filas rojas hasta que
		// cada sitio volviera a reportar, y el cambio parecería no haber
		// hecho nada.
		$tabla = SWPK_Tablas::tabla( 'cumplimiento' );

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$tabla} SET estado = %s WHERE norma = %s AND estado = %s",
				$aplazada ? self::APLAZADA : self::FALLA,
				$clave,
				$aplazada ? self::FALLA : self::APLAZADA
			)
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
	 * Se llama desde el receptor, con el reporte en la mano. No sale a internet:
	 * lo que no viene en el reporte lo busca en lo que la sonda del panel ya
	 * dejó guardado (ver SWPK_Sonda). Un sitio que no reporta no se mide — y
	 * eso se ve, porque su fila del tablero queda en «sin dato» con la fecha de
	 * la última vez.
	 *
	 * `$solo` limita la medición a ciertos comprobadores. Hace falta porque hay
	 * normas que no las contesta el agente sino la sonda, y esas se miden en la
	 * corrida diaria, sin reporte en la mano. Sin este filtro, medir con el
	 * reporte vacío escribiría «sin dato» encima de lo que el agente había
	 * contestado bien ayer.
	 *
	 * @param array<string,mixed> $reporte
	 * @param array<int,string>   $solo    comprobadores a medir; vacío = todos
	 * @return array<string,string> clave de norma => veredicto
	 */
	public static function medir( string $sitio_id, array $reporte, array $solo = [] ): array {
		$sitio = '' !== $sitio_id ? SWPK_Inventario::obtener( $sitio_id ) : null;
		$tag   = $sitio ? (string) $sitio->tag : SWPK_Etiquetas::SIN;
		$tipo  = $sitio ? (string) ( $sitio->tipo_sitio ?: SWPK_Tipos::SIN_DATO ) : SWPK_Tipos::SIN_DATO;

		$veredictos = [];

		foreach ( self::activas() as $norma ) {
			if ( $solo && ! in_array( (string) $norma->comprobador, $solo, true ) ) {
				continue;
			}

			// Dos ejes distintos: la etiqueta es el contrato de mantenimiento
			// y el tipo es qué hace el sitio. Un sitio puede ser auto_update Y
			// tienda a la vez, así que se miran por separado.
			if ( ! SWPK_Tipos::aplica( $norma, $tipo ) ) {
				self::anotar( $sitio_id, (string) $norma->clave, self::NO_APLICA, 'Esta norma solo aplica a las tiendas, y este sitio no lo es.' );
				$veredictos[ (string) $norma->clave ] = self::NO_APLICA;
				continue;
			}

			if ( ! self::aplica( $norma, $tag ) ) {
				self::anotar( $sitio_id, (string) $norma->clave, self::NO_APLICA, 'Esta norma no aplica a este grupo de sitios.' );
				$veredictos[ (string) $norma->clave ] = self::NO_APLICA;
				continue;
			}

			$r = self::comprobar( (string) $norma->comprobador, $reporte, $sitio );

			// Una norma en pausa que NO se cumple no es un incumplimiento:
			// es un pendiente decidido. Ver APLAZADA. Si se cumple, se
			// cuenta como cumplida y en paz — la pausa no esconde lo bueno.
			if ( self::FALLA === $r['estado'] && 1 === (int) ( $norma->aplazada ?? 0 ) ) {
				$porque = trim( (string) ( $norma->aplazada_porque ?? '' ) );

				$r = [
					'estado'  => self::APLAZADA,
					'detalle' => '' !== $porque
						? 'En pausa por decisión: ' . $porque
						: 'En pausa por decisión. Se sigue midiendo; cuando se cumpla, cambia solo.',
				];
			}

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

		// Si la verificación en dos pasos está apagada en el sitio, que no haya
		// ningún administrador con ella puesta no es un segundo problema: es el
		// mismo, contado otra vez.
		//
		// Lo dijo quien lo leyó en pantalla: «aquí dice la verificación en dos
		// pasos, está apagada, y luego viene hay administradores sin verificar
		// en dos pasos, que no sé por qué también, vamos a hacer regla
		// repetida». Tenía razón. Dos avisos para una sola causa hacen que se
		// dejen de leer los dos.
		//
		// La norma no desaparece: queda como «no aplica», que es la verdad —
		// vuelve a medirse sola en cuanto alguien encienda la verificación.
		if ( isset( $r['dos_pasos']['activo'] ) && empty( $r['dos_pasos']['activo'] ) ) {
			return [
				'estado'  => self::NO_APLICA,
				'detalle' => 'La verificación en dos pasos está apagada en el sitio, así que esto se mide cuando se encienda.',
			];
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

	/**
	 * ¿Se ejecuta PHP en la carpeta de subidas?
	 *
	 * Hay dos fuentes y se usan en este orden:
	 *
	 *   1. El agente, que hace la prueba de verdad: escribe un .php, lo pide y
	 *      lo borra. Es la única que distingue «el servidor lo devolvió como
	 *      texto» de «el servidor lo ejecutó».
	 *   2. La sonda del panel, que pide un .php que no existe y lo compara con
	 *      un .txt que tampoco existe. Si el .php da 403 y el .txt no, hay una
	 *      regla por extensión y el archivo no se entrega ni para leerlo. Sirve
	 *      cuando no hay agente, que hoy es en todos los sitios.
	 *
	 * El arreglo que había que hacer está en el estado «rechazado». El agente
	 * trataba cualquier respuesta que no fuera 2xx como «no se pudo comprobar»,
	 * y en Kinsta la regla de Nginx devuelve 403: medido el 18/11/2026, 13 de 14
	 * sitios de la cartera. Un 403 ahí es el MEJOR resultado posible —el
	 * servidor se niega incluso a entregar el archivo— y mientras contara como
	 * «no sé», esta norma se quedaba en «sin dato» para siempre en los 108.
	 */
	private static function comprobar_php_en_uploads( array $r, ?object $sitio ): array {
		$v = $r['normas']['php_en_uploads'] ?? null;

		// Los agentes viejos mandan true/false. Se traducen para no perder su
		// dato cuando todavía no se han actualizado.
		if ( true === $v ) {
			$v = 'ejecuta';
		} elseif ( false === $v ) {
			$v = 'texto';
		}

		if ( 'ejecuta' === $v ) {
			return [
				'estado'  => self::FALLA,
				'detalle' => 'Probado con un archivo real: un PHP subido a la carpeta de subidas SÍ se ejecuta. En Kinsta esto se cierra pidiéndoselo a soporte; no es algo que el sitio pueda arreglar solo.',
			];
		}

		if ( 'texto' === $v ) {
			return [ 'estado' => self::CUMPLE, 'detalle' => 'Probado con un archivo real: el servidor lo devolvió como texto, no lo ejecutó.' ];
		}

		if ( 'rechazado' === $v ) {
			return [
				'estado'  => self::CUMPLE,
				'detalle' => 'Probado con un archivo real: el servidor se niega a entregarlo. Protege más que devolverlo como texto, porque no se puede ni leer.',
			];
		}

		// Sin dato del agente —o con «no se pudo»— queda la sonda.
		$regla = (string) ( SWPK_Sonda::de( (string) ( $sitio->sitio_id ?? '' ) )['uploads_regla'] ?? '' );

		if ( 'bloquea' === $regla ) {
			return [
				'estado'  => self::CUMPLE,
				'detalle' => 'El servidor tiene una regla que no entrega archivos .php de la carpeta de subidas. Comprobado desde fuera, sin agente.',
			];
		}

		if ( 'sin_regla' === $regla ) {
			return [
				'estado'  => self::SIN_DATO,
				'detalle' => 'El servidor no tiene una regla por extensión en la carpeta de subidas. Puede seguir estando bien —que PHP no esté configurado para ejecutar ahí— pero eso solo lo prueba el agente, escribiendo un archivo de verdad.',
			];
		}

		if ( 'no_se_pudo' === $v ) {
			return [ 'estado' => self::SIN_DATO, 'detalle' => 'El agente intentó la prueba y no pudo completarla, y la sonda tampoco pudo averiguarlo desde fuera.' ];
		}

		return [ 'estado' => self::SIN_DATO, 'detalle' => 'Hace falta el agente para probarlo, o un sondeo desde fuera.' ];
	}

	/**
	 * ¿Está cerrado XML-RPC?
	 *
	 * Esto lo contesta la sonda desde fuera, no el agente desde dentro, y el
	 * motivo es un falso positivo que estuvo en producción: el agente leía el
	 * filtro `xmlrpc_enabled` de WordPress, que dice si WordPress *serviría*
	 * XML-RPC si le llegara la petición. En Kinsta no le llega — Nginx devuelve
	 * 403 antes de que PHP arranque — así que el panel podía estar marcando
	 * «abierto» una puerta por la que nadie puede pasar.
	 *
	 * Lo que importa es si se puede entrar, y eso solo se ve desde fuera. El
	 * dato del agente se sigue usando, pero para explicar, no para decidir.
	 */
	private static function comprobar_xmlrpc_cerrado( array $r, ?object $sitio ): array {
		$sonda  = SWPK_Sonda::de( (string) ( $sitio->sitio_id ?? '' ) );
		$fuera  = (string) ( $sonda['xmlrpc'] ?? '' );
		$dentro = isset( $r['xmlrpc_abierto'] ) ? (bool) $r['xmlrpc_abierto'] : null;

		if ( 'bloqueado' === $fuera ) {
			return [
				'estado'  => self::CUMPLE,
				'detalle' => null === $dentro || ! $dentro
					? 'El servidor no deja llegar la petición a WordPress.'
					: 'El servidor no deja llegar la petición a WordPress, aunque WordPress lo tenga habilitado por dentro. Nadie puede entrar por ahí.',
			];
		}

		if ( 'rechaza' === $fuera ) {
			return [ 'estado' => self::CUMPLE, 'detalle' => 'La petición llega a WordPress y WordPress la rechaza.' ];
		}

		if ( 'responde' === $fuera ) {
			return [
				'estado'  => self::FALLA,
				'detalle' => 'XML-RPC contesta desde internet: se puede probar cientos de contraseñas de un golpe y el sitio lo ve como un solo intento.',
			];
		}

		// Sin sonda no se afirma nada. Antes aquí se contestaba con el dato del
		// agente, y es justo lo que daba el resultado falso.
		if ( null === $dentro ) {
			return [ 'estado' => self::SIN_DATO, 'detalle' => 'Todavía no se ha podido sondear este sitio desde fuera.' ];
		}

		return [
			'estado'  => self::SIN_DATO,
			'detalle' => $dentro
				? 'WordPress lo tiene habilitado por dentro, pero no se pudo comprobar desde fuera si el servidor deja pasar la petición. Sin eso no se puede decir si está abierto.'
				: 'WordPress lo tiene deshabilitado por dentro, pero falta comprobarlo desde fuera.',
		];
	}

	/**
	 * ¿El sitio publica los nombres de usuario?
	 *
	 * WordPress no considera esto una vulnerabilidad: su política dice que los
	 * nombres de usuario no son información privada y que su enumeración está
	 * fuera de alcance. Así que esto se reporta por lo que es —divulgación que
	 * hace más eficiente un ataque de contraseñas— y no como una falla grave.
	 *
	 * Importa por una razón concreta: mientras esté abierto, la norma de «no
	 * existe un usuario admin» vale poco. Da igual cómo se llame la cuenta si
	 * el sitio publica el nombre.
	 */
	private static function comprobar_usuarios_enumerables( array $r, ?object $sitio ): array {
		$sonda = SWPK_Sonda::de( (string) ( $sitio->sitio_id ?? '' ) );

		if ( ! array_key_exists( 'usuarios_rest', $sonda ) || null === $sonda['usuarios_rest'] ) {
			return [ 'estado' => self::SIN_DATO, 'detalle' => 'Todavía no se ha podido sondear este sitio desde fuera.' ];
		}

		$n = (int) $sonda['usuarios_rest'];

		if ( 0 === $n ) {
			return [ 'estado' => self::CUMPLE, 'detalle' => 'La lista de usuarios no se puede leer sin iniciar sesión.' ];
		}

		return [
			'estado'  => self::FALLA,
			'detalle' => sprintf(
				'El sitio publica %s en /wp-json/wp/v2/users, sin pedir contraseña. Para entrar hacen falta dos datos —el nombre y la contraseña— y esto regala el primero.',
				1 === $n ? 'un nombre de usuario' : sprintf( '%d nombres de usuario', $n )
			),
		];
	}

	private static function comprobar_registro_cerrado( array $r, ?object $sitio ): array {
		if ( ! isset( $r['normas']['registro_abierto'] ) ) {
			return [ 'estado' => self::SIN_DATO, 'detalle' => 'Hace falta una versión del agente que reporte este dato.' ];
		}

		return empty( $r['normas']['registro_abierto'] )
			? [ 'estado' => self::CUMPLE, 'detalle' => 'Nadie puede crearse una cuenta por su cuenta.' ]
			: [ 'estado' => self::FALLA, 'detalle' => 'Cualquiera puede registrarse en el sitio.' ];
	}

	/**
	 * Los nombres que un ataque automático prueba antes que cualquier otro.
	 *
	 * Antes esto miraba solo «admin», que es demasiado estrecho: «administrator»
	 * y «webmaster» se adivinan igual de rápido. En la cartera hay cuentas
	 * llamadas «webmaster» hoy.
	 */
	private const NOMBRES_ADIVINABLES = [
		'admin',
		'administrator',
		'administrador',
		'root',
		'webmaster',
		'wpadmin',
		'wp-admin',
		'usuario',
		'user',
		'test',
		'demo',
	];

	/**
	 * ¿Hay un usuario con un nombre adivinable?
	 *
	 * Y una advertencia honesta en el detalle: esta norma se apoya en que el
	 * nombre de usuario sea un dato que el atacante no tiene. Si el sitio lo
	 * publica —ver `usuarios_enumerables`— cumplir esta norma no sirve de gran
	 * cosa, y el panel tiene que decirlo en vez de dar una tranquilidad falsa.
	 */
	private static function comprobar_sin_usuario_admin( array $r, ?object $sitio ): array {
		$usuarios = (array) ( $r['usuarios'] ?? [] );

		if ( ! $usuarios ) {
			return [ 'estado' => self::SIN_DATO, 'detalle' => 'El agente no reportó usuarios.' ];
		}

		$malos = [];

		foreach ( $usuarios as $u ) {
			$login = strtolower( trim( (string) ( $u['login'] ?? '' ) ) );

			if ( '' !== $login && in_array( $login, self::NOMBRES_ADIVINABLES, true ) ) {
				$malos[] = $login;
			}
		}

		if ( $malos ) {
			return [
				'estado'  => self::FALLA,
				'detalle' => sprintf(
					'Existe %s con un nombre de los que se prueban primero: %s. Cambiarlo hay que hacerlo a mano: lo que esté escrito a su nombre se queda apuntando a él.',
					1 === count( $malos ) ? 'una cuenta' : 'más de una cuenta',
					implode( ', ', array_map( static fn( $m ) => '«' . $m . '»', array_unique( $malos ) ) )
				),
			];
		}

		$expuestos = SWPK_Sonda::de( (string) ( $sitio->sitio_id ?? '' ) )['usuarios_rest'] ?? null;

		if ( null !== $expuestos && (int) $expuestos > 0 ) {
			return [
				'estado'  => self::CUMPLE,
				'detalle' => 'Ninguna cuenta tiene un nombre adivinable — pero el sitio publica sus nombres de usuario, así que no hay nada que adivinar. Vale poco mientras eso siga abierto.',
			];
		}

		return [ 'estado' => self::CUMPLE, 'detalle' => 'Ninguna cuenta tiene un nombre de los que se prueban primero.' ];
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Los comprobadores que contesta la sonda
	//
	// Todos empiezan por lo mismo: ¿hay sondeo, y es fiable? Si el sitio
	// contestó a una dirección inventada como si existiera, sus respuestas no
	// valen y se dice así, en vez de producir veinte hallazgos falsos.
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * Lo que la sonda dejó de este sitio, o un motivo por el que no sirve.
	 *
	 * @return array{0:array<string,mixed>,1:?array{estado:string,detalle:string}}
	 */
	private static function sonda( ?object $sitio ): array {
		$s = SWPK_Sonda::de( (string) ( $sitio->sitio_id ?? '' ) );

		if ( ! $s ) {
			return [ [], [ 'estado' => self::SIN_DATO, 'detalle' => 'Todavía no se ha podido sondear este sitio desde fuera.' ] ];
		}

		return [ $s, null ];
	}

	/**
	 * El veredicto de un grupo de rutas.
	 *
	 * «Bloqueado» no es «aprobado»: puede haber una regla del borde tapando un
	 * archivo que sigue ahí, y esa regla alguien la puede quitar mañana.
	 *
	 * @return array{estado:string,detalle:string}
	 */
	private static function de_ruta( ?object $sitio, string $grupo, string $mal, string $bien ): array {
		[ $s, $pero ] = self::sonda( $sitio );

		if ( $pero ) {
			return $pero;
		}

		// La sonda de control frena ESTAS y solo estas: en un sitio que
		// contesta 200 a cualquier dirección, «el archivo está ahí» no se
		// puede afirmar. Las cabeceras y el certificado se siguen midiendo.
		if ( empty( $s['rutas_fiables'] ) ) {
			return [
				'estado'  => self::SIN_DATO,
				'detalle' => 'El sitio contesta a direcciones que no existen como si existieran, así que no se puede '
					. 'distinguir un archivo que está de uno que no. Mientras siga así, buscar archivos desde fuera '
					. 'no sirve en este sitio.',
			];
		}

		$estado = (string) ( $s['rutas'][ $grupo ] ?? '' );

		if ( SWPK_Sonda::EXPUESTO === $estado ) {
			return [ 'estado' => self::FALLA, 'detalle' => $mal ];
		}

		if ( SWPK_Sonda::AUSENTE === $estado ) {
			return [ 'estado' => self::CUMPLE, 'detalle' => $bien ];
		}

		// La plataforma no sirve ese tipo de archivo, exista o no: se comprobó
		// pidiendo una dirección inventada con la misma extensión y también
		// vino bloqueada. Entonces da igual si el archivo está — nadie lo
		// puede bajar — y eso sí es protección.
		if ( SWPK_Sonda::PROTEGIDO === $estado ) {
			return [
				'estado'  => self::CUMPLE,
				'detalle' => 'El servidor no entrega archivos de ese tipo a nadie, exista el archivo o no. Comprobado '
					. 'pidiendo una dirección inventada con la misma extensión: también la rechaza.',
			];
		}

		if ( SWPK_Sonda::BLOQUEADO === $estado ) {
			return [
				'estado'  => self::SIN_DATO,
				'detalle' => 'El cortafuegos cortó o retó nuestra petición, y no es una regla que aplique a todos: una '
					. 'dirección inventada parecida sí contesta. Así que no se puede saber si el archivo está ahí — y '
					. 'un reto no protege de una persona con un navegador, solo de un sondeo como este.',
			];
		}

		return [ 'estado' => self::SIN_DATO, 'detalle' => 'No se pudo comprobar desde fuera.' ];
	}

	private static function comprobar_ruta_config_respaldo( array $r, ?object $sitio ): array {
		return self::de_ruta(
			$sitio,
			'config_respaldo',
			'Se puede descargar una copia de wp-config.php. Ahí va la contraseña de la base de datos.',
			'No se encontró ninguna copia de wp-config.php accesible.'
		);
	}

	private static function comprobar_ruta_debug_log( array $r, ?object $sitio ): array {
		return self::de_ruta(
			$sitio,
			'debug_log',
			'El registro de errores se puede leer desde internet. Ahí salen rutas del servidor y consultas a la base.',
			'El registro de errores no es accesible.'
		);
	}

	private static function comprobar_ruta_volcados( array $r, ?object $sitio ): array {
		return self::de_ruta(
			$sitio,
			'volcados',
			'Hay un respaldo del sitio o de la base que se puede descargar entero.',
			'No se encontró ningún respaldo descargable en la raíz.'
		);
	}

	private static function comprobar_ruta_phpinfo( array $r, ?object $sitio ): array {
		return self::de_ruta(
			$sitio,
			'phpinfo',
			'Hay una página de diagnóstico accesible: publica la configuración del servidor.',
			'No se encontró ninguna página de diagnóstico.'
		);
	}

	private static function comprobar_ruta_restos_instalador( array $r, ?object $sitio ): array {
		return self::de_ruta(
			$sitio,
			'restos_instalador',
			'Quedaron archivos de una migración. Con ellos se puede volver a instalar encima del sitio.',
			'No quedaron restos de ninguna migración.'
		);
	}

	private static function comprobar_ruta_instalador( array $r, ?object $sitio ): array {
		return self::de_ruta(
			$sitio,
			'instalador',
			'La página de instalación o la de reparación contesta con su formulario.',
			'Ni la instalación ni la reparación responden.'
		);
	}

	private static function comprobar_ruta_delatores( array $r, ?object $sitio ): array {
		return self::de_ruta(
			$sitio,
			'delatores',
			'Hay archivos de desarrollo publicados (.git, .env o las listas de dependencias).',
			'No hay archivos de desarrollo publicados.'
		);
	}

	private static function comprobar_ruta_listado( array $r, ?object $sitio ): array {
		return self::de_ruta(
			$sitio,
			'listado',
			'Una carpeta muestra la lista de los archivos que tiene dentro.',
			'Las carpetas no listan su contenido.'
		);
	}

	private static function comprobar_ruta_readme( array $r, ?object $sitio ): array {
		return self::de_ruta(
			$sitio,
			'readme',
			'El readme de WordPress se puede leer: dice la versión exacta. WordPress lo vuelve a crear en cada actualización.',
			'El readme de WordPress no está accesible.'
		);
	}

	private static function comprobar_autor_enumerable( array $r, ?object $sitio ): array {
		[ $s, $pero ] = self::sonda( $sitio );

		if ( $pero ) {
			return $pero;
		}

		$v = $s['autor'] ?? null;

		if ( null === $v ) {
			return [ 'estado' => self::SIN_DATO, 'detalle' => 'No se pudo comprobar desde fuera.' ];
		}

		return $v
			? [ 'estado' => self::FALLA, 'detalle' => 'Pidiendo /?author=1 el sitio redirige a la página del autor y deja ver el nombre de la cuenta.' ]
			: [ 'estado' => self::CUMPLE, 'detalle' => 'La dirección /?author=1 no revela ningún nombre de cuenta.' ];
	}

	private static function comprobar_trace( array $r, ?object $sitio ): array {
		[ $s, $pero ] = self::sonda( $sitio );

		if ( $pero ) {
			return $pero;
		}

		$v = $s['trace'] ?? null;

		if ( null === $v ) {
			return [ 'estado' => self::SIN_DATO, 'detalle' => 'No se pudo comprobar desde fuera.' ];
		}

		return $v
			? [ 'estado' => self::FALLA, 'detalle' => 'El servidor atiende TRACE y repite la petición entera, cabeceras incluidas.' ]
			: [ 'estado' => self::CUMPLE, 'detalle' => 'El servidor no atiende TRACE.' ];
	}

	private static function comprobar_indexable( array $r, ?object $sitio ): array {
		[ $s, $pero ] = self::sonda( $sitio );

		if ( $pero ) {
			return $pero;
		}

		$v = $s['robots'] ?? null;

		if ( null === $v ) {
			return [ 'estado' => self::SIN_DATO, 'detalle' => 'No se pudo leer robots.txt.' ];
		}

		return $v
			? [ 'estado' => self::FALLA, 'detalle' => 'El robots.txt le pide a los buscadores que no indexen nada. En un sitio vivo casi siempre es un ajuste que se quedó de cuando estaba en construcción.' ]
			: [ 'estado' => self::CUMPLE, 'detalle' => 'El sitio deja que los buscadores lo indexen.' ];
	}

	private static function comprobar_certificado( array $r, ?object $sitio ): array {
		[ $s, $pero ] = self::sonda( $sitio );

		if ( $pero ) {
			return $pero;
		}

		$c  = (array) ( $s['certificado'] ?? [] );
		$ok = $c['ok'] ?? null;

		if ( null === $ok ) {
			return [ 'estado' => self::SIN_DATO, 'detalle' => 'No se pudo revisar el certificado.' ];
		}

		if ( ! $ok ) {
			return [ 'estado' => self::FALLA, 'detalle' => (string) ( $c['motivo'] ?? 'El certificado no es válido para este sitio.' ) ];
		}

		$dias = $c['dias'] ?? null;

		if ( null !== $dias && $dias < 15 ) {
			return [
				'estado'  => self::FALLA,
				'detalle' => $dias < 0
					? 'El certificado está vencido.'
					: sprintf( 'El certificado vence en %d día(s). Kinsta los renueva solo, así que vale la pena mirarlo.', $dias ),
			];
		}

		return [
			'estado'  => self::CUMPLE,
			'detalle' => null === $dias
				? 'El certificado es válido para este sitio.'
				: sprintf( 'Válido, y le quedan %d días.', $dias ),
		];
	}

	private static function comprobar_cors( array $r, ?object $sitio ): array {
		[ $s, $pero ] = self::sonda( $sitio );

		if ( $pero ) {
			return $pero;
		}

		$v = $s['cors'] ?? null;

		if ( null === $v ) {
			return [ 'estado' => self::SIN_DATO, 'detalle' => 'No se pudo comprobar desde fuera.' ];
		}

		return $v
			? [ 'estado' => self::FALLA, 'detalle' => 'El sitio acepta peticiones con credenciales desde cualquier origen. Cualquier página que visite alguien con la sesión abierta puede leer sus datos.' ]
			: [ 'estado' => self::CUMPLE, 'detalle' => 'No se abre a orígenes ajenos con credenciales.' ];
	}

	private static function comprobar_hsts( array $r, ?object $sitio ): array {
		[ $s, $pero ] = self::sonda( $sitio );

		if ( $pero ) {
			return $pero;
		}

		if ( ! isset( $s['cabeceras']['hsts_max_age'] ) ) {
			return [ 'estado' => self::SIN_DATO, 'detalle' => 'No se pudieron leer las cabeceras de la portada.' ];
		}

		$edad = (int) $s['cabeceras']['hsts_max_age'];

		if ( 0 === $edad ) {
			return [ 'estado' => self::FALLA, 'detalle' => 'El sitio no manda la cabecera. La primera visita de cada persona sigue saliendo sin cifrar.' ];
		}

		if ( $edad < YEAR_IN_SECONDS ) {
			return [
				'estado'  => self::FALLA,
				'detalle' => sprintf( 'La manda, pero solo por %d día(s). Lo recomendado es un año o más.', (int) floor( $edad / DAY_IN_SECONDS ) ),
			];
		}

		return [
			'estado'  => self::CUMPLE,
			'detalle' => sprintf( 'Exige HTTPS durante %d día(s).', (int) floor( $edad / DAY_IN_SECONDS ) ),
		];
	}

	private static function comprobar_marco( array $r, ?object $sitio ): array {
		[ $s, $pero ] = self::sonda( $sitio );

		if ( $pero ) {
			return $pero;
		}

		if ( ! isset( $s['cabeceras']['marco'] ) ) {
			return [ 'estado' => self::SIN_DATO, 'detalle' => 'No se pudieron leer las cabeceras de la portada.' ];
		}

		return $s['cabeceras']['marco']
			? [ 'estado' => self::CUMPLE, 'detalle' => 'El sitio no se deja meter dentro de otra página.' ]
			: [ 'estado' => self::FALLA, 'detalle' => 'Cualquiera puede cargar el sitio dentro del suyo, invisible, y hacer que la gente haga clic en lo que no ve.' ];
	}

	private static function comprobar_mixto( array $r, ?object $sitio ): array {
		[ $s, $pero ] = self::sonda( $sitio );

		if ( $pero ) {
			return $pero;
		}

		$n = $s['mixto'] ?? null;

		if ( null === $n ) {
			return [ 'estado' => self::SIN_DATO, 'detalle' => 'No se pudo leer la portada.' ];
		}

		return $n > 0
			? [ 'estado' => self::FALLA, 'detalle' => sprintf( 'La portada carga %d recurso(s) por http://. Esa parte se puede interceptar y cambiar por el camino.', (int) $n ) ]
			: [ 'estado' => self::CUMPLE, 'detalle' => 'Todo lo que carga la portada va cifrado.' ];
	}

	private static function comprobar_canonico( array $r, ?object $sitio ): array {
		[ $s, $pero ] = self::sonda( $sitio );

		if ( $pero ) {
			return $pero;
		}

		return match ( (string) ( $s['canonico'] ?? '' ) ) {
			'canonico'  => [ 'estado' => self::CUMPLE, 'detalle' => 'Un nombre sirve el sitio y el otro redirige hacia él.' ],
			'solo_uno'  => [ 'estado' => self::CUMPLE, 'detalle' => 'El sitio responde por un solo nombre.' ],
			'duplicado' => [ 'estado' => self::FALLA, 'detalle' => 'Con www y sin www contestan los dos. Google ve dos sitios iguales, y la exigencia de HTTPS se aplica a un nombre y no al otro.' ],
			default     => [ 'estado' => self::SIN_DATO, 'detalle' => 'No se pudo comprobar desde fuera.' ],
		};
	}

	/**
	 * La política de contenido, midiendo lo que importa y no que exista.
	 *
	 * Una política que existe pero lo permite todo pasaría una comprobación de
	 * presencia sin proteger de nada. Y exigir la versión estricta en un sitio
	 * de Elementor sería pedir algo imposible. Así que se piden las dos piezas
	 * que no rompen nada.
	 */
	private static function comprobar_csp( array $r, ?object $sitio ): array {
		[ $s, $pero ] = self::sonda( $sitio );

		if ( $pero ) {
			return $pero;
		}

		if ( ! isset( $s['cabeceras']['csp'] ) ) {
			return [ 'estado' => self::SIN_DATO, 'detalle' => 'No se pudieron leer las cabeceras de la portada.' ];
		}

		$csp = (string) $s['cabeceras']['csp'];

		if ( '' === trim( $csp ) ) {
			return [ 'estado' => self::FALLA, 'detalle' => 'No hay política de contenido.' ];
		}

		$faltan = [];

		if ( ! preg_match( "/object-src\s+'none'/", $csp ) ) {
			$faltan[] = 'no permitir objetos';
		}

		if ( ! preg_match( "/base-uri\s+'(none|self)'/", $csp ) ) {
			$faltan[] = 'fijar la dirección base';
		}

		if ( $faltan ) {
			return [
				'estado'  => self::FALLA,
				'detalle' => sprintf( 'Hay política, pero le falta %s. Una política que lo permite todo no protege de nada.', implode( ' y ', $faltan ) ),
			];
		}

		return [ 'estado' => self::CUMPLE, 'detalle' => 'Hay política de contenido con lo mínimo que se le pide.' ];
	}

	private static function comprobar_referrer( array $r, ?object $sitio ): array {
		[ $s, $pero ] = self::sonda( $sitio );

		if ( $pero ) {
			return $pero;
		}

		if ( ! isset( $s['cabeceras']['referrer'] ) ) {
			return [ 'estado' => self::SIN_DATO, 'detalle' => 'No se pudieron leer las cabeceras de la portada.' ];
		}

		$v = (string) $s['cabeceras']['referrer'];

		$buenos = [ 'no-referrer', 'same-origin', 'strict-origin', 'strict-origin-when-cross-origin' ];

		foreach ( $buenos as $b ) {
			if ( str_contains( $v, $b ) ) {
				return [ 'estado' => self::CUMPLE, 'detalle' => sprintf( 'Filtra a dónde venía la gente (%s).', $v ) ];
			}
		}

		return [
			'estado'  => self::FALLA,
			'detalle' => '' === $v
				? 'No manda la cabecera: cada sitio al que alguien haga clic recibe la dirección completa de donde venía.'
				: sprintf( 'La manda con un valor que no protege (%s).', $v ),
		];
	}

	private static function comprobar_permissions( array $r, ?object $sitio ): array {
		[ $s, $pero ] = self::sonda( $sitio );

		if ( $pero ) {
			return $pero;
		}

		if ( ! isset( $s['cabeceras']['permissions'] ) ) {
			return [ 'estado' => self::SIN_DATO, 'detalle' => 'No se pudieron leer las cabeceras de la portada.' ];
		}

		return $s['cabeceras']['permissions']
			? [ 'estado' => self::CUMPLE, 'detalle' => 'Declara qué funciones del navegador no usa.' ]
			: [ 'estado' => self::FALLA, 'detalle' => 'No declara nada, así que cámara, micrófono y ubicación quedan disponibles para todo lo que cargue la página.' ];
	}

	private static function comprobar_xss_obsoleta( array $r, ?object $sitio ): array {
		[ $s, $pero ] = self::sonda( $sitio );

		if ( $pero ) {
			return $pero;
		}

		if ( ! isset( $s['cabeceras']['xss_obsoleta'] ) ) {
			return [ 'estado' => self::SIN_DATO, 'detalle' => 'No se pudieron leer las cabeceras de la portada.' ];
		}

		return $s['cabeceras']['xss_obsoleta']
			? [ 'estado' => self::FALLA, 'detalle' => 'Manda una cabecera retirada. Los navegadores la ignoran, y el filtro que encendía llegó a abrir fallas propias.' ]
			: [ 'estado' => self::CUMPLE, 'detalle' => 'No manda la cabecera retirada.' ];
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Solo para las tiendas
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * @return array{0:array<string,mixed>,1:?array{estado:string,detalle:string}}
	 */
	private static function tienda( array $r, ?object $sitio ): array {
		$t = (array) ( $r['tienda'] ?? [] );

		if ( ! $t ) {
			$t = SWPK_Tienda::de( (string) ( $sitio->sitio_id ?? '' ) );
		}

		if ( ! $t ) {
			return [ [], [ 'estado' => self::SIN_DATO, 'detalle' => 'Hace falta el agente instalado para poder revisar la tienda desde dentro.' ] ];
		}

		if ( empty( $t['woo_activo'] ) ) {
			return [ $t, [ 'estado' => self::NO_APLICA, 'detalle' => 'Este sitio no tiene WooCommerce activo.' ] ];
		}

		return [ $t, null ];
	}

	/** Las dos rutas de WooCommerce que no deben contestar sin credenciales. */
	private static function comprobar_tienda_api( array $r, ?object $sitio ): array {
		$s = SWPK_Sonda::de( (string) ( $sitio->sitio_id ?? '' ) );

		if ( ! isset( $s['tienda_api'] ) ) {
			return [ 'estado' => self::SIN_DATO, 'detalle' => 'Todavía no se ha sondeado este sitio desde fuera.' ];
		}

		$abiertas = (array) $s['tienda_api'];

		return ! $abiertas
			? [ 'estado' => self::CUMPLE, 'detalle' => 'Ni los pedidos ni los clientes se leen sin credenciales.' ]
			: [
				'estado'  => self::FALLA,
				'detalle' => sprintf( 'Contesta sin pedir credenciales: %s. Ahí van los datos de quien compró.', implode( ', ', $abiertas ) ),
			];
	}

	private static function comprobar_tienda_logs( array $r, ?object $sitio ): array {
		$s        = SWPK_Sonda::de( (string) ( $sitio->sitio_id ?? '' ) );
		$abierto  = $s['tienda_logs'] ?? null;

		[ $t, $pero ] = self::tienda( $r, $sitio );

		if ( true === $abierto ) {
			return [ 'estado' => self::FALLA, 'detalle' => 'La carpeta de registros de la tienda se puede listar desde internet.' ];
		}

		if ( $pero ) {
			return null === $abierto ? $pero : [ 'estado' => self::CUMPLE, 'detalle' => 'La carpeta de registros no se puede listar.' ];
		}

		// La otra mitad: sin la llave, el nombre de cada archivo de registro se
		// puede deducir, y entonces da igual que la carpeta no se liste.
		$c = (array) ( $r['fondo']['constantes'] ?? [] );

		if ( isset( $c['llaves_faltan'] ) ) {
			$sin_sal = in_array( 'AUTH_SALT', (array) $c['llaves_faltan'], true )
				|| in_array( 'AUTH_SALT', (array) ( $c['llaves_ejemplo'] ?? [] ), true );

			if ( $sin_sal ) {
				return [
					'estado'  => self::FALLA,
					'detalle' => 'La llave con la que WooCommerce disimula el nombre de sus archivos de registro no está puesta, así que usa la palabra que trae su código público: los nombres se pueden deducir y los archivos bajar.',
				];
			}
		}

		return [ 'estado' => self::CUMPLE, 'detalle' => 'La carpeta no se lista y los nombres de los archivos no se pueden deducir.' ];
	}

	private static function comprobar_tienda_descargas( array $r, ?object $sitio ): array {
		[ $t, $pero ] = self::tienda( $r, $sitio );
		if ( $pero ) {
			return $pero;
		}

		$d = (array) ( $t['descargas'] ?? [] );

		if ( ! $d ) {
			return [ 'estado' => self::SIN_DATO, 'detalle' => 'El agente no reportó cómo se entregan las descargas.' ];
		}

		$malos = [];

		if ( 'redirect' === ( $d['metodo'] ?? '' ) ) {
			$malos[] = 'el método de entrega es «redirección», que según la propia documentación de WooCommerce deja el archivo a la vista de cualquiera con la dirección';
		}

		if ( ! empty( $d['respaldo'] ) ) {
			$malos[] = 'está activo el respaldo por redirección, que WooCommerce etiqueta como inseguro';
		}

		return $malos
			? [ 'estado' => self::FALLA, 'detalle' => ucfirst( implode( '; ', $malos ) ) . '.' ]
			: [ 'estado' => self::CUMPLE, 'detalle' => sprintf( 'Los archivos se entregan por «%s», que no expone la dirección real.', (string) ( $d['metodo'] ?? '' ) ) ];
	}

	private static function comprobar_tienda_invitados( array $r, ?object $sitio ): array {
		[ $t, $pero ] = self::tienda( $r, $sitio );
		if ( $pero ) {
			return $pero;
		}

		$i = (array) ( $t['invitados'] ?? [] );

		if ( ! $i ) {
			return [ 'estado' => self::SIN_DATO, 'detalle' => 'El agente no reportó este dato.' ];
		}

		if ( empty( $i['sin_cuenta'] ) ) {
			return [ 'estado' => self::CUMPLE, 'detalle' => 'No se puede comprar sin cuenta, así que no hay pedidos de invitado.' ];
		}

		if ( empty( $i['exige_correo'] ) ) {
			return [ 'estado' => self::FALLA, 'detalle' => 'Algo desactivó la verificación por correo: cualquiera con el enlace de un pedido ve los datos de quien compró.' ];
		}

		$gracia = (int) ( $i['gracia'] ?? 0 );

		if ( $gracia > DAY_IN_SECONDS ) {
			return [ 'estado' => self::FALLA, 'detalle' => sprintf( 'Pide el correo, pero con un margen de %d horas en las que no lo pide. Lo normal son diez minutos.', (int) round( $gracia / HOUR_IN_SECONDS ) ) ];
		}

		return [ 'estado' => self::CUMPLE, 'detalle' => 'Pide el correo antes de enseñar un pedido de invitado.' ];
	}

	/**
	 * Números de tarjeta guardados.
	 *
	 * El detalle dice el límite SIEMPRE, también cuando sale limpio. Un
	 * resultado limpio significa «no se encontró ninguno donde se buscó, el día
	 * que se buscó», y nunca «no se guardan datos de tarjeta». La diferencia
	 * importa si alguien enseña esto como garantía.
	 */
	private static function comprobar_tienda_tarjetas( array $r, ?object $sitio ): array {
		[ $t, $pero ] = self::tienda( $r, $sitio );
		if ( $pero ) {
			return $pero;
		}

		$c = (array) ( $t['tarjetas'] ?? [] );

		if ( ! isset( $c['encontrados'] ) ) {
			return [ 'estado' => self::SIN_DATO, 'detalle' => 'El agente no hizo esta búsqueda.' ];
		}

		$n = (int) $c['encontrados'];
		$p = (int) ( $c['de_prueba'] ?? 0 );

		if ( $n > 0 ) {
			return [
				'estado'  => self::FALLA,
				'detalle' => sprintf(
					'Se encontraron %d número(s) de tarjeta en texto plano, en: %s. No se guardó ninguno; solo la cuenta.',
					$n,
					implode( ', ', (array) ( $c['donde'] ?? [] ) )
				),
			];
		}

		$nota = $p > 0 ? sprintf( ' Sí hay %d número(s) de prueba, que no son de nadie pero conviene limpiar.', $p ) : '';

		return [
			'estado'  => self::CUMPLE,
			'detalle' => 'No se encontró ninguno en las notas de pedido ni en el meta de pedido, hoy. Eso no es lo mismo que «no se guardan datos de tarjeta»: solo que no había donde se buscó.' . $nota,
		];
	}

	private static function comprobar_tienda_webhooks( array $r, ?object $sitio ): array {
		[ $t, $pero ] = self::tienda( $r, $sitio );
		if ( $pero ) {
			return $pero;
		}

		$n = $t['webhooks_sin_cifrar'] ?? null;

		if ( null === $n ) {
			return [ 'estado' => self::SIN_DATO, 'detalle' => 'El agente no reportó los webhooks.' ];
		}

		return 0 === (int) $n
			? [ 'estado' => self::CUMPLE, 'detalle' => 'Ningún aviso automático sale sin cifrar.' ]
			: [ 'estado' => self::FALLA, 'detalle' => sprintf( '%d aviso(s) automático(s) mandan el pedido entero por http, sin cifrar.', (int) $n ) ];
	}

	private static function comprobar_tienda_limpieza( array $r, ?object $sitio ): array {
		[ $t, $pero ] = self::tienda( $r, $sitio );
		if ( $pero ) {
			return $pero;
		}

		$l = (array) ( $t['limpieza'] ?? [] );

		if ( ! $l ) {
			return [ 'estado' => self::SIN_DATO, 'detalle' => 'El agente no reportó este dato.' ];
		}

		// No configurarla es una decisión legítima: hay deberes contables que
		// obligan a guardar. Lo que no se sostiene es tenerla puesta y muerta.
		if ( empty( $l['configurada'] ) ) {
			return [ 'estado' => self::CUMPLE, 'detalle' => 'No hay limpieza automática configurada. Es una decisión válida: hay deberes contables que obligan a guardar los pedidos.' ];
		}

		return empty( $l['cron_vivo'] )
			? [ 'estado' => self::FALLA, 'detalle' => 'La limpieza está configurada pero su tarea programada no existe: no está corriendo, y en la pantalla de la tienda parece que sí.' ]
			: [ 'estado' => self::CUMPLE, 'detalle' => 'La limpieza está configurada y su tarea está viva.' ];
	}

	private static function comprobar_tienda_modo( array $r, ?object $sitio ): array {
		[ $t, $pero ] = self::tienda( $r, $sitio );
		if ( $pero ) {
			return $pero;
		}

		$prueba = (array) ( $t['en_prueba'] ?? [] );

		if ( ! isset( $t['pasarelas_encendidas'] ) ) {
			return [ 'estado' => self::SIN_DATO, 'detalle' => 'El agente no reportó las pasarelas.' ];
		}

		return ! $prueba
			? [ 'estado' => self::CUMPLE, 'detalle' => sprintf( '%s en modo real.', ucfirst( SWPK_Vista::plural( count( (array) $t['pasarelas_encendidas'] ), 'pasarela encendida', 'pasarelas encendidas' ) ) ) ]
			: [ 'estado' => self::FALLA, 'detalle' => sprintf( 'En modo de pruebas: %s. Acepta pedidos que no cobran.', implode( ', ', $prueba ) ) ];
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Configuración, permisos e integridad: lo que solo se ve desde dentro
	// ─────────────────────────────────────────────────────────────────────────

	/** Dónde el panel recuerda la huella de las llaves de cada sitio. */
	private const OPCION_HUELLAS = 'swpk_huellas_de_llaves';

	/**
	 * @return array{0:array<string,mixed>,1:?array{estado:string,detalle:string}}
	 */
	private static function fondo( array $r, string $parte ): array {
		$f = (array) ( $r['fondo'] ?? [] );

		if ( ! $f ) {
			return [ [], [ 'estado' => self::SIN_DATO, 'detalle' => 'Hace falta el agente instalado en el sitio para poder revisar esto desde dentro.' ] ];
		}

		if ( ! isset( $f[ $parte ] ) ) {
			return [ $f, [ 'estado' => self::SIN_DATO, 'detalle' => 'Hace falta una versión del agente que reporte este dato.' ] ];
		}

		return [ (array) $f[ $parte ], null ];
	}

	/**
	 * Las ocho llaves de sesión.
	 *
	 * Tres cosas distintas, y la tercera es la que no se puede ver mirando un
	 * solo sitio: que dos sitios nuestros tengan las mismas. El agente manda un
	 * hash de las ocho juntas —nunca las llaves— y el panel los compara entre
	 * sí. Es como se descubre una plantilla clonada.
	 */
	private static function comprobar_fondo_llaves( array $r, ?object $sitio ): array {
		[ $c, $pero ] = self::fondo( $r, 'constantes' );
		if ( $pero ) {
			return $pero;
		}

		$faltan  = (array) ( $c['llaves_faltan'] ?? [] );
		$ejemplo = (array) ( $c['llaves_ejemplo'] ?? [] );
		$huella  = (string) ( $c['llaves_huella'] ?? '' );
		$yo      = (string) ( $sitio->sitio_id ?? '' );

		if ( $faltan ) {
			return [ 'estado' => self::FALLA, 'detalle' => sprintf( 'Faltan %d de las 8 llaves de sesión.', count( $faltan ) ) ];
		}

		if ( $ejemplo ) {
			return [
				'estado'  => self::FALLA,
				'detalle' => sprintf( '%d de las 8 llaves siguen siendo las de ejemplo que trae WordPress. Están publicadas: cualquiera que lo sepa puede fabricarse una sesión.', count( $ejemplo ) ),
			];
		}

		// Guardar la huella y mirar si alguien más tiene la misma.
		$repetido = '';

		if ( '' !== $huella && '' !== $yo ) {
			$todas = (array) get_option( self::OPCION_HUELLAS, [] );

			foreach ( $todas as $otro_id => $otra ) {
				if ( (string) $otro_id !== $yo && (string) $otra === $huella ) {
					$otro     = SWPK_Inventario::obtener( (string) $otro_id );
					$repetido = (string) ( $otro->nombre_visible ?? $otro_id );
					break;
				}
			}

			$todas[ $yo ] = $huella;
			update_option( self::OPCION_HUELLAS, $todas, false );
		}

		if ( '' !== $repetido ) {
			return [
				'estado'  => self::FALLA,
				'detalle' => sprintf( 'Las ocho llaves son idénticas a las de «%s». Una sesión robada en uno de los dos sirve en el otro. Pasa cuando un sitio se clona de otro para arrancar.', $repetido ),
			];
		}

		return [ 'estado' => self::CUMPLE, 'detalle' => 'Las ocho llaves están puestas, no son las de ejemplo y no se repiten en ningún otro sitio de la cartera.' ];
	}

	private static function comprobar_fondo_depuracion( array $r, ?object $sitio ): array {
		[ $c, $pero ] = self::fondo( $r, 'constantes' );
		if ( $pero ) {
			return $pero;
		}

		$d     = (array) ( $c['depuracion'] ?? [] );
		$malos = [];

		// Lo que de verdad importa es si un error sale a la vista o queda
		// escrito en la carpeta pública. WP_DEBUG encendido con la pantalla
		// apagada y el registro fuera de la raíz es defendible, así que no se
		// marca por sí solo.
		if ( ! empty( $d['wp_debug'] ) && ! empty( $d['wp_debug_display'] ) ) {
			$malos[] = 'los errores se imprimen en la página';
		}

		if ( ! empty( $d['display_errors'] ) ) {
			$malos[] = 'PHP tiene activado mostrar errores';
		}

		if ( ! empty( $d['wp_debug'] ) && ! empty( $d['wp_debug_log'] ) ) {
			$malos[] = 'los errores se escriben en un archivo dentro de la carpeta pública';
		}

		if ( ! empty( $d['entorno'] ) && 'production' !== $d['entorno'] && 'local' !== $d['entorno'] ) {
			$malos[] = sprintf( 'el sitio se declara de tipo «%s»', $d['entorno'] );
		}

		return $malos
			? [ 'estado' => self::FALLA, 'detalle' => ucfirst( implode( '; ', $malos ) ) . '.' ]
			: [ 'estado' => self::CUMPLE, 'detalle' => 'Ningún error de PHP sale a la vista ni queda escrito donde se pueda leer.' ];
	}

	private static function comprobar_fondo_puertas( array $r, ?object $sitio ): array {
		[ $c, $pero ] = self::fondo( $r, 'constantes' );
		if ( $pero ) {
			return $pero;
		}

		$p     = (array) ( $c['puertas'] ?? [] );
		$malos = [];

		if ( ! empty( $p['reparacion'] ) ) {
			$malos[] = 'la página de reparación de la base de datos se puede usar SIN iniciar sesión';
		}

		if ( ! empty( $p['subidas_sin_filtro'] ) ) {
			$malos[] = 'está apagado el filtro que impide subir cualquier tipo de archivo';
		}

		return $malos
			? [ 'estado' => self::FALLA, 'detalle' => ucfirst( implode( '; ', $malos ) ) . '.' ]
			: [ 'estado' => self::CUMPLE, 'detalle' => 'Las dos puertas de mantenimiento están cerradas.' ];
	}

	private static function comprobar_fondo_actualizaciones( array $r, ?object $sitio ): array {
		[ $c, $pero ] = self::fondo( $r, 'constantes' );
		if ( $pero ) {
			return $pero;
		}

		$a = (array) ( $c['actualizaciones'] ?? [] );

		if ( ! empty( $a['apagadas'] ) ) {
			return [ 'estado' => self::FALLA, 'detalle' => 'Las actualizaciones automáticas están apagadas del todo en wp-config.php.' ];
		}

		if ( false === ( $a['nucleo'] ?? 'minor' ) ) {
			return [ 'estado' => self::FALLA, 'detalle' => 'Las actualizaciones automáticas del núcleo están apagadas.' ];
		}

		// Esta constante no es un fallo por sí misma, pero pelea con lo que
		// acabamos de aprobar: apaga instalar y actualizar desde el escritorio.
		if ( ! empty( $a['sin_tocar'] ) ) {
			return [
				'estado'  => self::CUMPLE,
				'detalle' => 'Las del núcleo están encendidas. Ojo: también está puesta la constante que bloquea instalar y actualizar desde el escritorio, así que los plugins hay que actualizarlos por otra vía.',
			];
		}

		return [
			'estado'  => self::CUMPLE,
			'detalle' => sprintf( 'Encendidas (%s).', is_bool( $a['nucleo'] ?? null ) ? 'todas' : (string) ( $a['nucleo'] ?? 'minor' ) ),
		];
	}

	private static function comprobar_fondo_admin_https( array $r, ?object $sitio ): array {
		[ $c, $pero ] = self::fondo( $r, 'constantes' );
		if ( $pero ) {
			return $pero;
		}

		return ! empty( $c['admin_https'] )
			? [ 'estado' => self::CUMPLE, 'detalle' => 'El escritorio está obligado a ir por HTTPS.' ]
			: [ 'estado' => self::FALLA, 'detalle' => 'Falta la línea de wp-config.php que obliga al escritorio a ir cifrado.' ];
	}

	private static function comprobar_fondo_credenciales( array $r, ?object $sitio ): array {
		[ $c, $pero ] = self::fondo( $r, 'constantes' );
		if ( $pero ) {
			return $pero;
		}

		$cr = (array) ( $c['credenciales'] ?? [] );

		return ! $cr
			? [ 'estado' => self::CUMPLE, 'detalle' => 'No hay credenciales guardadas en wp-config.php.' ]
			: [ 'estado' => self::FALLA, 'detalle' => sprintf( 'Hay %d credencial(es) de FTP guardadas en texto plano en wp-config.php.', count( $cr ) ) ];
	}

	private static function comprobar_fondo_permisos( array $r, ?object $sitio ): array {
		[ $u, $pero ] = self::fondo( $r, 'usuarios' );
		if ( $pero ) {
			return $pero;
		}

		$de_mas = (array) ( $u['permisos_de_mas'] ?? [] );

		return ! $de_mas
			? [ 'estado' => self::CUMPLE, 'detalle' => 'Ningún rol por debajo de administrador tiene permisos de más.' ]
			: [
				'estado'  => self::FALLA,
				'detalle' => sprintf( 'Permisos que no le tocan a ese rol: %s.', implode( ', ', array_slice( $de_mas, 0, 6 ) ) ),
			];
	}

	private static function comprobar_fondo_rol_omision( array $r, ?object $sitio ): array {
		[ $u, $pero ] = self::fondo( $r, 'usuarios' );
		if ( $pero ) {
			return $pero;
		}

		$rol    = (string) ( $u['rol_por_omision'] ?? 'subscriber' );
		$abierto = ! empty( $r['normas']['registro_abierto'] );

		if ( 'subscriber' === $rol ) {
			return [ 'estado' => self::CUMPLE, 'detalle' => 'Quien se registre entra como suscriptor, que es el rol más bajo.' ];
		}

		// Un rol alto con el registro cerrado no es un agujero: nadie se puede
		// registrar. Se dice, pero no se marca como incumplimiento.
		if ( ! $abierto ) {
			return [
				'estado'  => self::CUMPLE,
				'detalle' => sprintf( 'El rol por omisión es «%s», que no es el más bajo — pero el registro está cerrado, así que nadie entra por ahí.', $rol ),
			];
		}

		return [
			'estado'  => self::FALLA,
			'detalle' => sprintf( 'El registro está abierto Y quien se registre entra como «%s». Las dos cosas juntas dejan que cualquiera se cree una cuenta con permisos.', $rol ),
		];
	}

	private static function comprobar_fondo_administradores( array $r, ?object $sitio ): array {
		[ $u, $pero ] = self::fondo( $r, 'usuarios' );
		if ( $pero ) {
			return $pero;
		}

		$n    = (int) ( $u['administradores'] ?? 0 );
		$tope = (int) apply_filters( 'swpk_tope_administradores', 5 );

		return $n <= $tope
			? [ 'estado' => self::CUMPLE, 'detalle' => sprintf( '%d administrador(es).', $n ) ]
			: [ 'estado' => self::FALLA, 'detalle' => sprintf( '%d administradores, y lo acordado son %d o menos. Cada uno puede cambiarlo todo.', $n, $tope ) ];
	}

	private static function comprobar_fondo_claves_app( array $r, ?object $sitio ): array {
		[ $u, $pero ] = self::fondo( $r, 'usuarios' );
		if ( $pero ) {
			return $pero;
		}

		$n = (int) ( $u['claves_de_app'] ?? 0 );

		return 0 === $n
			? [ 'estado' => self::CUMPLE, 'detalle' => 'No hay ninguna contraseña de aplicación.' ]
			: [
				'estado'  => self::FALLA,
				'detalle' => sprintf( 'Hay %d contraseña(s) de aplicación. Entran por la API sin pasar por la verificación en dos pasos; hay que confirmar que todas siguen haciendo falta.', $n ),
			];
	}

	private static function comprobar_fondo_nucleo( array $r, ?object $sitio ): array {
		[ $i, $pero ] = self::fondo( $r, 'integridad' );
		if ( $pero ) {
			return $pero;
		}

		$n = (array) ( $i['nucleo'] ?? [] );

		if ( null === ( $n['ok'] ?? null ) ) {
			return [ 'estado' => self::SIN_DATO, 'detalle' => (string) ( $n['motivo'] ?? 'No se pudieron comparar los archivos del núcleo.' ) ];
		}

		$cuantos = (int) ( $n['cuantos'] ?? 0 );

		if ( 0 === $cuantos ) {
			return [ 'estado' => self::CUMPLE, 'detalle' => sprintf( 'Los %d archivos del núcleo coinciden con los oficiales de WordPress %s.', (int) ( $n['revisados'] ?? 0 ), (string) ( $n['version'] ?? '' ) ) ];
		}

		$lista = array_merge( (array) ( $n['cambiados'] ?? [] ), (array) ( $n['faltantes'] ?? [] ) );

		return [
			'estado'  => self::FALLA,
			'detalle' => sprintf(
				'%d archivo(s) del núcleo no coinciden con los oficiales: %s%s. Una actualización no los cambia; los cambió alguien.',
				$cuantos,
				implode( ', ', array_slice( $lista, 0, 5 ) ),
				count( $lista ) > 5 ? '…' : ''
			),
		];
	}

	private static function comprobar_fondo_cerrados( array $r, ?object $sitio ): array {
		[ $i, $pero ] = self::fondo( $r, 'integridad' );
		if ( $pero ) {
			return $pero;
		}

		$c      = (array) ( $i['cerrados'] ?? [] );
		$malos  = (array) ( $c['cerrados'] ?? [] );

		return ! $malos
			? [ 'estado' => self::CUMPLE, 'detalle' => sprintf( 'Ninguno de los %d plugins revisados está cerrado en el repositorio.', (int) ( $c['revisados'] ?? 0 ) ) ]
			: [
				'estado'  => self::FALLA,
				'detalle' => sprintf( 'WordPress.org cerró %s. En el escritorio del sitio siguen apareciendo «al día».', implode( ', ', $malos ) ),
			];
	}

	private static function comprobar_fondo_abandonados( array $r, ?object $sitio ): array {
		[ $i, $pero ] = self::fondo( $r, 'integridad' );
		if ( $pero ) {
			return $pero;
		}

		$c      = (array) ( $i['cerrados'] ?? [] );
		$viejos = (array) ( $c['viejos'] ?? [] );
		$sin    = (int) ( $c['sin_repo'] ?? 0 );

		$nota = $sin > 0
			? sprintf( ' (%d de pago o a medida, que no están en el repositorio y no se pueden revisar así.)', $sin )
			: '';

		return ! $viejos
			? [ 'estado' => self::CUMPLE, 'detalle' => 'Ningún plugin lleva más de dos años sin una versión nueva.' . $nota ]
			: [
				'estado'  => self::FALLA,
				'detalle' => sprintf( 'Más de dos años sin actualizarse: %s.%s', implode( ', ', array_slice( $viejos, 0, 6 ) ), $nota ),
			];
	}

	private static function comprobar_fondo_dropins( array $r, ?object $sitio ): array {
		[ $i, $pero ] = self::fondo( $r, 'integridad' );
		if ( $pero ) {
			return $pero;
		}

		$hay = (array) ( $i['drop_ins'] ?? [] );

		// Los de caché los pone el hosting o un plugin de caché y son
		// normales. Marcarlos daría una alarma en casi todos los sitios.
		$normales = [ 'advanced-cache.php', 'object-cache.php', 'db.php' ];
		$raros    = array_values( array_diff( $hay, $normales ) );

		if ( ! $raros ) {
			return [
				'estado'  => self::CUMPLE,
				'detalle' => $hay
					? sprintf( 'Solo los de caché, que son normales: %s.', implode( ', ', $hay ) )
					: 'No hay ninguno.',
			];
		}

		return [
			'estado'  => self::FALLA,
			'detalle' => sprintf( 'Hay archivos que se cargan antes que WordPress y no son de caché: %s.', implode( ', ', $raros ) ),
		];
	}

	private static function comprobar_fondo_tareas( array $r, ?object $sitio ): array {
		[ $i, $pero ] = self::fondo( $r, 'integridad' );
		if ( $pero ) {
			return $pero;
		}

		$t = (array) ( $i['tareas'] ?? [] );
		$n = (int) ( $t['cuantas'] ?? 0 );

		return 0 === $n
			? [ 'estado' => self::CUMPLE, 'detalle' => sprintf( 'Las %d tareas programadas tienen dueño.', (int) ( $t['total'] ?? 0 ) ) ]
			: [
				'estado'  => self::FALLA,
				'detalle' => sprintf(
					'%d tarea(s) programada(s) cuyo gancho no reconoce ningún plugin instalado: %s. Casi siempre quedaron de un plugin que se borró, pero conviene mirarlas.',
					$n,
					implode( ', ', (array) ( $t['huerfanas'] ?? [] ) )
				),
			];
	}

	private static function comprobar_fondo_registro( array $r, ?object $sitio ): array {
		[ $g, $pero ] = self::fondo( $r, 'registro' );
		if ( $pero ) {
			return $pero;
		}

		$hay = (array) ( $g['plugins'] ?? [] );

		return $hay
			? [ 'estado' => self::CUMPLE, 'detalle' => sprintf( 'Guarda el rastro con %s.', implode( ', ', $hay ) ) ]
			: [ 'estado' => self::FALLA, 'detalle' => 'No hay nada instalado que guarde quién entró y qué tocó. El registro de la plataforma no ve lo que pasa dentro de WordPress.' ];
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Los archivos que sube la gente por un formulario
	//
	// Todo esto lo mide el agente dentro del sitio y llega en su reporte. El
	// panel nunca ve el nombre de un archivo subido, solo las cuentas.
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * @return array{0:array<string,mixed>,1:?array{estado:string,detalle:string}}
	 */
	private static function formularios( array $r ): array {
		$f = (array) ( $r['formularios'] ?? [] );

		if ( ! $f ) {
			return [ [], [ 'estado' => self::SIN_DATO, 'detalle' => 'Hace falta el agente instalado en el sitio para poder revisar esto desde dentro.' ] ];
		}

		if ( ! empty( $f['error'] ) ) {
			return [ $f, [ 'estado' => self::SIN_DATO, 'detalle' => 'El agente no pudo leer la carpeta de subidas.' ] ];
		}

		if ( ! isset( $f['plugins'] ) ) {
			return [ $f, [ 'estado' => self::SIN_DATO, 'detalle' => 'Hace falta una versión del agente que revise los formularios.' ] ];
		}

		return [ $f, null ];
	}

	private static function comprobar_form_publicos( array $r, ?object $sitio ): array {
		[ $f, $pero ] = self::formularios( $r );
		if ( $pero ) {
			return $pero;
		}

		if ( ! $f['plugins'] ) {
			return [ 'estado' => self::NO_APLICA, 'detalle' => 'Este sitio no tiene ningún plugin de formularios con subida de archivos.' ];
		}

		$malos = (array) ( $f['publicos'] ?? [] );

		if ( $malos ) {
			return [
				'estado'  => self::FALLA,
				'detalle' => sprintf(
					'Se probó con un archivo de verdad y se bajó sin pedir nada: %s. Lo que esté ahí lo puede ver cualquiera que dé con la dirección.',
					implode( ', ', $malos )
				),
			];
		}

		if ( 0 === (int) ( $f['revisados'] ?? 0 ) ) {
			return [
				'estado'  => self::SIN_DATO,
				'detalle' => 'Hay plugins de formularios pero todavía nadie ha subido nada, así que no hay con qué probar.',
			];
		}

		return [
			'estado'  => self::CUMPLE,
			'detalle' => sprintf( 'Se probó con un archivo real de %s y el servidor no lo entregó.', SWPK_Vista::plural( (int) $f['revisados'], 'carpeta', 'carpetas' ) ),
		];
	}

	private static function comprobar_form_htaccess( array $r, ?object $sitio ): array {
		[ $f, $pero ] = self::formularios( $r );
		if ( $pero ) {
			return $pero;
		}

		$malos = (array) ( $f['htaccess_inerte'] ?? [] );

		if ( ! $malos ) {
			return [ 'estado' => self::CUMPLE, 'detalle' => 'Ningún plugin está confiando en una protección que este servidor no lee.' ];
		}

		return [
			'estado'  => self::FALLA,
			'detalle' => sprintf(
				'%s dejó un .htaccess en su carpeta de subidas para cerrarla, y este servidor no lee .htaccess. El plugin cree que la cerró; está abierta.',
				implode( ', ', $malos )
			),
		];
	}

	private static function comprobar_form_php_sueltos( array $r, ?object $sitio ): array {
		[ $f, $pero ] = self::formularios( $r );
		if ( $pero ) {
			return $pero;
		}

		$n = (int) ( $f['php_en_subidas'] ?? 0 );

		return 0 === $n
			? [ 'estado' => self::CUMPLE, 'detalle' => 'No hay ningún archivo PHP en la carpeta de subidas.' ]
			: [
				'estado'  => self::FALLA,
				'detalle' => sprintf(
					'Hay %d archivo(s) PHP en la carpeta de subidas. Ahí solo deberían caer imágenes y documentos: eso no llegó subiendo una foto.',
					$n
				),
			];
	}

	private static function comprobar_form_antispam( array $r, ?object $sitio ): array {
		[ $f, $pero ] = self::formularios( $r );
		if ( $pero ) {
			return $pero;
		}

		$a   = (array) ( $f['antispam'] ?? [] );
		$con = (array) ( $a['con'] ?? [] );
		$sin = (array) ( $a['sin'] ?? [] );

		if ( ! $con && ! $sin ) {
			return [ 'estado' => self::NO_APLICA, 'detalle' => 'Este sitio no tiene ningún plugin de formularios de los que el panel sabe leer.' ];
		}

		if ( ! $sin ) {
			return [ 'estado' => self::CUMPLE, 'detalle' => sprintf( 'Con captcha u honeypot: %s.', implode( ', ', $con ) ) ];
		}

		return [
			'estado'  => self::FALLA,
			'detalle' => sprintf(
				'Sin captcha ni honeypot: %s.%s',
				implode( ', ', $sin ),
				$con ? sprintf( ' (Sí lo tienen: %s.)', implode( ', ', $con ) ) : ''
			),
		];
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Los comprobadores que leen el inventario que ya está guardado
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * La foto de plugins más reciente de un sitio.
	 *
	 * @return array<int,object>
	 */
	private static function foto( string $sitio_id ): array {
		if ( '' === $sitio_id ) {
			return [];
		}

		global $wpdb;
		$tabla = SWPK_Tablas::tabla( 'fotos' );

		$fecha = $wpdb->get_var( $wpdb->prepare( "SELECT MAX(fecha) FROM {$tabla} WHERE sitio_id = %s", $sitio_id ) );

		if ( ! $fecha ) {
			return [];
		}

		return $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$tabla} WHERE sitio_id = %s AND fecha = %s", $sitio_id, $fecha )
		) ?: [];
	}

	private static function comprobar_inv_vulnerables( array $r, ?object $sitio ): array {
		$filas = self::foto( (string) ( $sitio->sitio_id ?? '' ) );

		if ( ! $filas ) {
			return [ 'estado' => self::SIN_DATO, 'detalle' => sprintf( '%s todavía no ha escaneado este sitio.', SWPK_Admin::proveedor() ) ];
		}

		$malos = [];

		foreach ( $filas as $f ) {
			if ( (int) $f->es_vulnerable ) {
				$malos[] = (string) ( $f->titulo ?: $f->slug );
			}
		}

		if ( ! $malos ) {
			return [ 'estado' => self::CUMPLE, 'detalle' => sprintf( 'Ninguno de los %d módulos está en una versión marcada.', count( $filas ) ) ];
		}

		return [
			'estado'  => self::FALLA,
			'detalle' => sprintf(
				'%s en una versión con falla conocida: %s%s.',
				SWPK_Vista::plural( count( $malos ), 'módulo', 'módulos' ),
				implode( ', ', array_slice( $malos, 0, 6 ) ),
				count( $malos ) > 6 ? '…' : ''
			),
		];
	}

	private static function comprobar_inv_inactivos( array $r, ?object $sitio ): array {
		$filas = self::foto( (string) ( $sitio->sitio_id ?? '' ) );

		if ( ! $filas ) {
			return [ 'estado' => self::SIN_DATO, 'detalle' => sprintf( '%s todavía no ha escaneado este sitio.', SWPK_Admin::proveedor() ) ];
		}

		$apagados = [];

		foreach ( $filas as $f ) {
			// Los mu-plugins de la plataforma salen siempre como inactivos y no
			// son nuestros ni se pueden borrar. Contarlos sería ruido fijo.
			if ( (int) $f->activo || str_starts_with( (string) $f->slug, 'kinsta-' ) ) {
				continue;
			}

			$apagados[] = (string) ( $f->titulo ?: $f->slug );
		}

		if ( ! $apagados ) {
			return [ 'estado' => self::CUMPLE, 'detalle' => 'No hay nada instalado que esté apagado.' ];
		}

		return [
			'estado'  => self::FALLA,
			'detalle' => sprintf(
				'%s instalado(s) pero apagado(s): %s%s. Los archivos siguen ahí y se pueden pedir desde internet.',
				SWPK_Vista::plural( count( $apagados ), 'módulo', 'módulos' ),
				implode( ', ', array_slice( $apagados, 0, 6 ) ),
				count( $apagados ) > 6 ? '…' : ''
			),
		];
	}

	private static function comprobar_inv_estado( array $r, ?object $sitio ): array {
		$estado = strtolower( trim( (string) ( $sitio->estado ?? '' ) ) );

		if ( '' === $estado ) {
			return [ 'estado' => self::SIN_DATO, 'detalle' => 'El inventario no trae el estado de este sitio.' ];
		}

		$buenos = [ 'live', 'active', 'ok', 'running' ];

		return in_array( $estado, $buenos, true )
			? [ 'estado' => self::CUMPLE, 'detalle' => sprintf( 'El sitio está en estado «%s».', $estado ) ]
			: [ 'estado' => self::FALLA, 'detalle' => sprintf( 'El sitio está en estado «%s» en la plataforma.', $estado ) ];
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Los comprobadores que leen la revisión de la plataforma
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * @return array{0:array<string,mixed>,1:?array{estado:string,detalle:string}}
	 */
	private static function revision( ?object $sitio ): array {
		if ( ! class_exists( 'SWPK_Revision' ) || ! SWPK_Revision::disponible() ) {
			return [ [], [ 'estado' => self::SIN_DATO, 'detalle' => 'Esto solo se puede leer con la llave de la plataforma, y ahora mismo el panel está con los datos de prueba.' ] ];
		}

		$v = SWPK_Revision::de( (string) ( $sitio->sitio_id ?? '' ) );

		if ( ! $v ) {
			return [ [], [ 'estado' => self::SIN_DATO, 'detalle' => 'Todavía no se ha revisado este sitio contra la plataforma.' ] ];
		}

		return [ $v, null ];
	}

	private static function comprobar_rev_https( array $r, ?object $sitio ): array {
		[ $v, $pero ] = self::revision( $sitio );
		if ( $pero ) {
			return $pero;
		}

		$x = $v['https'] ?? null;

		if ( null === $x || '' === $x ) {
			return [ 'estado' => self::SIN_DATO, 'detalle' => 'La plataforma no contestó este dato.' ];
		}

		return str_starts_with( (string) $x, 'ENABLED' )
			? [ 'estado' => self::CUMPLE, 'detalle' => 'La plataforma fuerza HTTPS.' ]
			: [ 'estado' => self::FALLA, 'detalle' => sprintf( 'La plataforma no lo fuerza (está en «%s»).', $x ) ];
	}

	private static function comprobar_rev_respaldo( array $r, ?object $sitio ): array {
		[ $v, $pero ] = self::revision( $sitio );
		if ( $pero ) {
			return $pero;
		}

		$horas = $v['respaldo']['horas'] ?? null;

		if ( null === $horas ) {
			return [ 'estado' => self::SIN_DATO, 'detalle' => 'No se pudo leer la lista de respaldos.' ];
		}

		if ( $horas > 48 ) {
			return [ 'estado' => self::FALLA, 'detalle' => sprintf( 'El respaldo más reciente es de hace %d horas.', (int) $horas ) ];
		}

		return [
			'estado'  => self::CUMPLE,
			'detalle' => sprintf( 'El último respaldo es de hace %d hora(s); hay %d guardados.', (int) $horas, (int) ( $v['respaldo']['cuantos'] ?? 0 ) ),
		];
	}

	/**
	 * El pico de tráfico, contra la línea base del propio sitio.
	 *
	 * El detalle dice las dos cifras a propósito. Un pico no es ni bueno ni
	 * malo: puede ser una pauta publicitaria o puede ser un ataque, y lo que el
	 * panel tiene que hacer es que alguien lo mire, no decidir cuál es.
	 */
	private static function comprobar_rev_trafico( array $r, ?object $sitio ): array {
		[ $v, $pero ] = self::revision( $sitio );
		if ( $pero ) {
			return $pero;
		}

		$t = (array) ( $v['trafico'] ?? [] );

		if ( ! empty( $t['pocos_datos'] ) ) {
			return [ 'estado' => self::SIN_DATO, 'detalle' => 'Todavía no hay suficientes días de historial para saber qué es normal en este sitio.' ];
		}

		if ( null === ( $t['veces'] ?? null ) ) {
			return [ 'estado' => self::SIN_DATO, 'detalle' => 'No se pudo leer la analítica de visitas.' ];
		}

		$veces = (float) $t['veces'];
		$tope  = (float) ( $t['tope'] ?? 3 );

		if ( $veces >= $tope ) {
			return [
				'estado'  => self::FALLA,
				'detalle' => sprintf(
					'Ayer tuvo %d visitas y lo normal en este sitio son %d: %sx lo de siempre. Puede ser una pauta o puede ser tráfico dirigido — hay que mirarlo.',
					(int) $t['hoy'],
					(int) $t['base'],
					$veces
				),
			];
		}

		return [
			'estado'  => self::CUMPLE,
			'detalle' => sprintf( '%d visitas ayer, contra %d que es lo normal en este sitio.', (int) $t['hoy'], (int) $t['base'] ),
		];
	}

	private static function comprobar_rev_errores( array $r, ?object $sitio ): array {
		[ $v, $pero ] = self::revision( $sitio );
		if ( $pero ) {
			return $pero;
		}

		$e = (array) ( $v['errores'] ?? [] );

		if ( null === ( $e['por_hora'] ?? null ) ) {
			return [ 'estado' => self::SIN_DATO, 'detalle' => 'No se pudo leer el desglose de respuestas.' ];
		}

		$por_hora = (int) $e['por_hora'];

		return $por_hora >= (int) ( $e['tope'] ?? 20 )
			? [ 'estado' => self::FALLA, 'detalle' => sprintf( '%d errores de servidor por hora en las últimas 24 (%d en total).', $por_hora, (int) ( $e['total'] ?? 0 ) ) ]
			: [
				'estado'  => self::CUMPLE,
				'detalle' => 0 === $por_hora
					? 'Ningún error de servidor en las últimas 24 horas.'
					// El total va aunque esté por debajo del tope: «unos 14 por
					// hora» suena a nada y son 358 al día. Que se vea el número.
					: sprintf( 'Unos %d por hora, %d en 24 horas. Por debajo del tope, pero no es cero.', $por_hora, (int) ( $e['total'] ?? 0 ) ),
			];
	}

	/**
	 * La oleada de intentos de entrada.
	 *
	 * El detalle dice explícitamente que un número bajo no prueba nada. El
	 * borde de Kinsta absorbe parte del ataque antes de que el origen lo vea, y
	 * solo se leen las últimas 2000 líneas. Si no se dijera, alguien va a leer
	 * «0» como «estamos tranquilos», que no es lo mismo.
	 */
	private static function comprobar_rev_logins( array $r, ?object $sitio ): array {
		[ $v, $pero ] = self::revision( $sitio );
		if ( $pero ) {
			return $pero;
		}

		$l = (array) ( $v['logins'] ?? [] );

		if ( null === ( $l['cuantos'] ?? null ) ) {
			return [ 'estado' => self::SIN_DATO, 'detalle' => 'No se pudo leer el registro de accesos.' ];
		}

		$n = (int) $l['cuantos'];

		if ( $n >= (int) ( $l['tope'] ?? 50 ) ) {
			return [
				'estado'  => self::FALLA,
				'detalle' => sprintf( '%d intentos de entrada en las últimas %d líneas del registro. No significa que hayan entrado; significa que lo están intentando.', $n, (int) ( $l['lineas'] ?? 0 ) ),
			];
		}

		return [
			'estado'  => self::CUMPLE,
			'detalle' => sprintf( '%d intentos en las últimas %d líneas. Ojo: el borde corta parte del ataque antes de que el registro lo vea, así que un número bajo no prueba que no haya.', $n, (int) ( $l['lineas'] ?? 0 ) ),
		];
	}

	private static function comprobar_rev_bots( array $r, ?object $sitio ): array {
		[ $v, $pero ] = self::revision( $sitio );
		if ( $pero ) {
			return $pero;
		}

		$n = $v['bots_nivel'] ?? null;

		if ( null === $n || '' === $n ) {
			return [ 'estado' => self::SIN_DATO, 'detalle' => 'La plataforma no contestó el nivel de protección.' ];
		}

		$nombres = [
			'block-malicious-traffic' => 'bloquea el tráfico malicioso',
			'block-automations'       => 'bloquea las automatizaciones',
			'challenge-bots'          => 'reta a los bots',
			'challenge-everyone'      => 'reta a todo el mundo',
		];

		return isset( $nombres[ $n ] )
			? [ 'estado' => self::CUMPLE, 'detalle' => sprintf( 'Encendida: %s.', $nombres[ $n ] ) ]
			: [ 'estado' => self::FALLA, 'detalle' => sprintf( 'En un nivel que el panel no reconoce («%s»). Hay que mirarlo en la plataforma.', $n ) ];
	}

	private static function comprobar_rev_excepciones( array $r, ?object $sitio ): array {
		[ $v, $pero ] = self::revision( $sitio );
		if ( $pero ) {
			return $pero;
		}

		$n = $v['bots_excep'] ?? null;

		if ( null === $n ) {
			return [ 'estado' => self::SIN_DATO, 'detalle' => 'La plataforma no contestó las excepciones.' ];
		}

		return 0 === (int) $n
			? [ 'estado' => self::CUMPLE, 'detalle' => 'No hay ninguna excepción puesta.' ]
			: [ 'estado' => self::FALLA, 'detalle' => sprintf( 'Hay %d excepción(es). Cada una apaga la protección para lo que diga su regla; conviene revisar si todavía hacen falta.', (int) $n ) ];
	}

	private static function comprobar_rev_sftp( array $r, ?object $sitio ): array {
		[ $v, $pero ] = self::revision( $sitio );
		if ( $pero ) {
			return $pero;
		}

		$n = $v['sftp_extra'] ?? null;

		if ( null === $n ) {
			return [ 'estado' => self::SIN_DATO, 'detalle' => 'La plataforma no contestó las cuentas SFTP.' ];
		}

		return 0 === (int) $n
			? [ 'estado' => self::CUMPLE, 'detalle' => 'No hay cuentas SFTP extra.' ]
			: [ 'estado' => self::FALLA, 'detalle' => sprintf( 'Hay %d cuenta(s) SFTP extra. Cada una puede escribir archivos en el sitio.', (int) $n ) ];
	}

	private static function comprobar_rev_redirecciones( array $r, ?object $sitio ): array {
		[ $v, $pero ] = self::revision( $sitio );
		if ( $pero ) {
			return $pero;
		}

		$n = $v['redirecciones'] ?? null;

		if ( null === $n ) {
			return [ 'estado' => self::SIN_DATO, 'detalle' => 'La plataforma no contestó las reglas de redirección.' ];
		}

		return 0 === (int) $n
			? [ 'estado' => self::CUMPLE, 'detalle' => 'No hay redirecciones puestas en la plataforma.' ]
			: [ 'estado' => self::FALLA, 'detalle' => sprintf( 'Hay %d redirección(es) en la plataforma. No se ven desde WordPress, así que hay que revisarlas en el panel de la plataforma.', (int) $n ) ];
	}

	private static function comprobar_rev_ia( array $r, ?object $sitio ): array {
		[ $v, $pero ] = self::revision( $sitio );
		if ( $pero ) {
			return $pero;
		}

		$x = $v['bots_ia'] ?? null;

		if ( null === $x || '' === $x ) {
			return [ 'estado' => self::SIN_DATO, 'detalle' => 'La plataforma no contestó este dato.' ];
		}

		// Apagado es el valor de fábrica, y desde fuera no se puede distinguir
		// «alguien decidió dejarlo abierto» de «nadie lo ha mirado nunca».
		// Marcarlo como incumplimiento daría 108 filas rojas por una decisión
		// que no es de seguridad — justo el ruido que hace que nadie mire el
		// tablero. Así que se dice que está sin decidir, que es la verdad.
		return 'enabled' === $x
			? [ 'estado' => self::CUMPLE, 'detalle' => 'Los rastreadores de IA están bloqueados.' ]
			: [ 'estado' => self::SIN_DATO, 'detalle' => 'Los rastreadores de IA pueden recoger el contenido del sitio. Viene así de fábrica, y desde aquí no se puede saber si alguien lo decidió o si nadie lo ha mirado. Es una decisión del cliente.' ];
	}

	private static function comprobar_wp_al_dia( array $r, ?object $sitio ): array {
		// Primero el agente, que es el dato de dentro. Si no hay —y hoy no hay
		// en ningún sitio— sirve el del inventario, que Kinsta da para 107 de
		// los 108. Antes esta norma se quedaba en «sin dato» en toda la cartera
		// esperando un agente que no está instalado.
		$aqui = (string) ( $r['wp'] ?? '' );
		$de   = 'el agente';

		if ( '' === $aqui ) {
			$aqui = (string) ( $sitio->version_wp ?? '' );
			$de   = SWPK_Admin::proveedor();
		}

		if ( '' === $aqui ) {
			return [ 'estado' => self::SIN_DATO, 'detalle' => 'No hay versión de WordPress, ni del agente ni del inventario.' ];
		}

		$minima = (string) apply_filters( 'swpk_wp_minima', '6.4' );

		return version_compare( $aqui, $minima, '>=' )
			? [ 'estado' => self::CUMPLE, 'detalle' => sprintf( 'WordPress %s, según %s.', $aqui, $de ) ]
			: [
				'estado'  => self::FALLA,
				'detalle' => sprintf( 'WordPress %s, y lo mínimo con soporte es %s. Según %s.', $aqui, $minima, $de ),
			];
	}

	private static function comprobar_php_soportado( array $r, ?object $sitio ): array {
		$aqui = (string) ( $r['php'] ?? '' );
		$de   = 'el agente';

		if ( '' === $aqui ) {
			// El inventario la guarda como «php8.3»; aquí hace falta el número.
			$aqui = ltrim( (string) ( $sitio->version_php ?? '' ), 'phpPHP' );
			$de   = SWPK_Admin::proveedor();
		}

		if ( '' === $aqui ) {
			return [ 'estado' => self::SIN_DATO, 'detalle' => 'No hay versión de PHP, ni del agente ni del inventario.' ];
		}

		// Se queda en el código y en un filtro, no en la base de datos: cuando
		// PHP retire una versión hay que cambiar esto a mano, y es mejor que
		// eso se vea en un commit que escondido en una fila de una tabla.
		$minima = (string) apply_filters( 'swpk_php_minima', '8.1' );

		return version_compare( $aqui, $minima, '>=' )
			? [ 'estado' => self::CUMPLE, 'detalle' => sprintf( 'PHP %s, según %s.', $aqui, $de ) ]
			: [
				'estado'  => self::FALLA,
				'detalle' => sprintf( 'PHP %s, que ya no recibe parches. Lo mínimo con soporte es %s. Según %s.', $aqui, $minima, $de ),
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

	/**
	 * Cómo se dice que esta norma NO se cumple.
	 *
	 * Si nadie escribió el texto —una norma que puso una persona a mano— se
	 * arma uno que al menos no se contradice. Mejor «No se cumple: los
	 * formularios tienen captcha» que repetir la regla en afirmativo debajo de
	 * un título que dice que algo está mal.
	 */
	public static function problema( object $norma ): string {
		$propio = trim( (string) ( $norma->problema ?? '' ) );

		return '' !== $propio ? $propio : 'No se cumple: ' . $norma->nombre;
	}

	/** Cómo se dice cada veredicto en pantalla. */
	public static function etiqueta( string $estado ): string {
		return match ( $estado ) {
			self::CUMPLE    => 'Cumple',
			self::FALLA     => 'No cumple',
			self::NO_APLICA => 'No aplica',
			self::APLAZADA  => 'En pausa',
			default         => 'Sin medir',
		};
	}
}
