<?php
/**
 * Cómo se arregla cada norma, y qué quiere decir en castellano.
 *
 * ─── Por qué existe este archivo ─────────────────────────────────────────────
 *
 * El jefe, mirando las tres primeras normas de las 73:
 *
 *   «esta es crítica… “permite usar el servidor del sitio para alcanzar otros
 *    servicios internos”. Ajá. ¿Pero qué tengo que hacer? No lo tengo claro,
 *    ¿me entiendes?»                                                  (06:35)
 *
 *   «“no se puede escribir código desde el escritorio”… no entiendo qué es
 *    esto. No sé si esto quiere decir que ya no voy a poder escribir código
 *    en los widgets»                                                  (12:32)
 *
 *   «hay que hacerlo a mano. Ok, ¿qué debo de hacer aquí? ¿Qué debo de hacer
 *    yo a mano? Si no, no se entiende qué debo de hacer»              (17:58)
 *
 *   «si tú mañana no estás porque te enfermaste, y yo pongo a Miguelito a
 *    hacerlo, ¿va a entender qué tiene que hacer?»                    (18:23)
 *
 * Esa última frase es el criterio de todo lo que hay aquí abajo. Un texto
 * está bien escrito si Miguelito, que no sabe qué es una cabecera HTTP, puede
 * cerrar la norma leyéndolo.
 *
 * ─── Las reglas con las que están escritos ───────────────────────────────────
 *
 *  1. Pasos numerados, y cada paso es una cosa que se hace, no una que se
 *     sabe. «Entrar a Ajustes → Lectura» sí; «revisar la configuración» no.
 *  2. Se nombra la pantalla tal cual se llama en WordPress, con sus flechas,
 *     para poder seguirla sin adivinar.
 *  3. Sin estimaciones de tiempo. El panel no sabe cuánto tarda cada quien, y
 *     decirlo sería inventarlo.
 *  4. Cuando el arreglo lo tiene que hacer otro —Kinsta, el cliente, el autor
 *     de un plugin— se dice quién, en la primera línea. Media hora buscando
 *     un botón que no existe es peor que la norma sin cumplir.
 *  5. Cuando no hay arreglo, se dice que no lo hay. «Asumir el riesgo por
 *     escrito» es una salida legítima y es la única honesta en algunos casos.
 *
 * La `aclaracion` es la línea de debajo del nombre: una frase que contesta
 * «¿qué quiere decir esto?» antes de que nadie la pregunte. Nació de la de
 * `editor_codigo`, que él leyó en voz alta y entendió al revés.
 *
 * @return array<string,array{aclaracion:string,arreglo:string}>
 */

defined( 'ABSPATH' ) || exit;

return [

	// ── Cuentas y accesos ───────────────────────────────────────────────────

	'dos_pasos_activa' => [
		'aclaracion' => 'Pide un código además de la contraseña al entrar al escritorio. No afecta a quien solo visita el sitio.',
		'arreglo'    => "El panel lo puede hacer solo: le manda la orden al agente y la próxima medición lo confirma.\n"
			. "Si se prefiere a mano:\n"
			. "1. Entrar al escritorio del sitio, a Plugins → Añadir nuevo.\n"
			. "2. Instalar y activar «Two Factor» o «WP 2FA».\n"
			. "3. Volver aquí y darle a «Analizar este sitio ahora».",
	],

	'admins_dos_pasos' => [
		'aclaracion' => 'Encenderla en el sitio no basta: cada administrador tiene que configurarla una vez en su propio perfil.',
		'arreglo'    => "No se puede hacer por nadie: cada persona configura la suya.\n"
			. "1. En la ficha del sitio, mirar qué administradores faltan.\n"
			. "2. Escribirle a cada uno pidiéndole que entre a Usuarios → Tu perfil y active la verificación en dos pasos.\n"
			. "3. Si alguno es una cuenta que ya nadie usa, darla de baja en vez de perseguirla.\n"
			. "4. Volver aquí y darle a «Analizar este sitio ahora».",
	],

	'editor_codigo' => [
		'aclaracion' => 'No afecta a Elementor ni a los widgets: solo cierra la pantalla Apariencia → Editor de archivos.',
		'arreglo'    => "El panel lo puede hacer solo: le manda la orden al agente, el agente la aplica y la próxima medición lo confirma. No hay que entrar al sitio.\n"
			. "Si se prefiere a mano:\n"
			. "1. Abrir el archivo wp-config.php del sitio.\n"
			. "2. Añadir la línea: define( 'DISALLOW_FILE_EDIT', true );\n"
			. "3. Ponerla antes de la línea que dice «That's all, stop editing».",
	],

	'php_en_uploads' => [
		'aclaracion' => 'La carpeta donde caen los archivos que sube la gente debe guardarlos, no ejecutarlos.',
		'arreglo'    => "Lo arregla el hosting, no el sitio: en Kinsta esta regla se pide a soporte.\n"
			. "1. Abrir un ticket en MyKinsta pidiendo que se bloquee la ejecución de PHP en /wp-content/uploads/.\n"
			. "2. Cuando contesten, volver aquí y darle a «Analizar este sitio ahora».\n"
			. "No sirve poner un .htaccess: este servidor es Nginx y no lo lee. Ver la norma «Un plugin dejó una protección que en este servidor no hace nada».",
	],

	'xmlrpc_cerrado' => [
		'aclaracion' => 'Una puerta vieja de WordPress para publicar desde fuera. Jetpack y la app móvil la usan; casi nada más.',
		'arreglo'    => "El panel lo puede hacer solo.\n"
			. "Antes de cerrarla, comprobar que el sitio no use Jetpack ni la app móvil de WordPress. Si las usa, no se cierra: se deja anotado y se pasa a «aplazada».\n"
			. "Si se prefiere a mano: pedirle a Kinsta que bloquee /xmlrpc.php desde su panel.",
	],

	'registro_cerrado' => [
		'aclaracion' => 'Si está abierto, cualquiera que entre al sitio puede crearse una cuenta sin que nadie lo apruebe.',
		'arreglo'    => "El panel lo puede hacer solo.\n"
			. "Antes de cerrarlo, comprobar que el sitio no sea una tienda ni tenga área de clientes: ahí el registro hace falta. Si lo necesita, revisar en cambio la norma «Quien se registre entra con un rol con privilegios».\n"
			. "Si se prefiere a mano: Ajustes → Generales, y desmarcar «Cualquiera puede registrarse».",
	],

	'sin_usuario_admin' => [
		'aclaracion' => 'Nombres como admin, administrator o el nombre del dominio son los primeros que prueba quien ataca.',
		'arreglo'    => "No se le cambia el nombre a un usuario de WordPress: se crea otro y se borra el viejo.\n"
			. "1. Usuarios → Añadir nuevo, con un nombre que no se adivine y rol de administrador.\n"
			. "2. Entrar con esa cuenta nueva.\n"
			. "3. Usuarios → borrar la cuenta vieja, y cuando pregunte, «Atribuir todo el contenido a» la nueva.\n"
			. "4. Volver aquí y darle a «Analizar este sitio ahora».",
	],

	'usuarios_enumerables' => [
		'aclaracion' => 'La lista de usuarios se puede pedir por /wp-json/wp/v2/users sin contraseña. Da la mitad de lo que hace falta para entrar.',
		'arreglo'    => "1. Instalar «Disable WP REST API» o, si ya está, revisar sus ajustes.\n"
			. "2. Cerrar solo el listado de usuarios, no la API entera: Elementor y la tienda la usan.\n"
			. "3. Comprobar abriendo https://eldominio/wp-json/wp/v2/users en una ventana privada: tiene que pedir permiso.\n"
			. "4. Volver aquí y darle a «Analizar este sitio ahora».",
	],

	'autor_enumerable' => [
		'aclaracion' => 'Al abrir /?author=1 el sitio redirige a la página del autor, y en la dirección queda su nombre de usuario.',
		'arreglo'    => "1. En el plugin de seguridad del sitio, buscar la opción «bloquear enumeración de autores» o «author scan».\n"
			. "2. Si no hay plugin de seguridad, pedirle a Kinsta una regla que devuelva 403 a las peticiones con ?author= numérico.\n"
			. "3. Comprobar abriendo https://eldominio/?author=1 en una ventana privada.",
	],

	'administradores_contados' => [
		'aclaracion' => 'Cada administrador puede cambiarlo todo, incluida la cuenta de los demás. Lo acordado son cinco o menos.',
		'arreglo'    => "1. Usuarios → Todos, filtrar por Administrador.\n"
			. "2. Por cada uno, decidir: ¿sigue trabajando en esto?\n"
			. "3. Al que ya no, bajarlo a Editor o darlo de baja. No hace falta borrarlo: basta con quitarle el rol.\n"
			. "4. Si de verdad hacen falta más de cinco, anotarlo y pasar la norma a «aplazada» con el motivo.",
	],

	'claves_de_aplicacion' => [
		'aclaracion' => 'Son contraseñas sueltas que entran por la API sin pasar por la verificación en dos pasos. Las crea una integración, y se quedan.',
		'arreglo'    => "1. Usuarios → Todos, y en cada usuario bajar hasta «Contraseñas de aplicación».\n"
			. "2. Por cada una, ver el nombre: dice para qué se creó.\n"
			. "3. Revocar las que no se reconozcan o cuya integración ya no exista.\n"
			. "4. Las que sí hacen falta, dejarlas y anotar en el panel para qué son.",
	],

	'roles_sin_permisos_de_mas' => [
		'aclaracion' => 'Un plugin o un ajuste viejo le dio a un rol un permiso que no le toca: por ejemplo, que un Suscriptor pueda subir archivos.',
		'arreglo'    => "1. Instalar «User Role Editor» si no está.\n"
			. "2. Abrir el rol que el panel señala y comparar con lo que trae WordPress de fábrica.\n"
			. "3. Quitar el permiso de más. Si no se sabe cuál es, el botón «Reset» del plugin devuelve el rol a su estado original.\n"
			. "4. Probar con una cuenta de ese rol que el sitio siga funcionando.",
	],

	'rol_por_omision' => [
		'aclaracion' => 'El rol que recibe quien se registra. De fábrica es Suscriptor, que no puede hacer nada; cualquier otro sí.',
		'arreglo'    => "1. Ajustes → Generales → «Perfil predeterminado para nuevos usuarios».\n"
			. "2. Ponerlo en Suscriptor.\n"
			. "3. Si la tienda necesita otro rol, que sea Cliente, nunca Editor ni Autor.\n"
			. "4. Revisar si alguien se registró mientras estaba mal: Usuarios → ordenar por fecha de registro.",
	],

	'registro_de_accesos' => [
		'aclaracion' => 'Un registro, dentro de WordPress, de cada entrada y cada cambio. WordPress de fábrica no guarda nada de eso.',
		'arreglo'    => "Lo lleva el agente del panel: en los sitios con el agente al día esta norma se cumple sola, sin instalar nada.\n"
			. "Si el sitio no tiene agente:\n"
			. "1. Plugins → Añadir nuevo.\n"
			. "2. Instalar y activar «Simple History» (gratis) o «WP Activity Log».\n"
			. "3. En sus ajustes, poner que guarde 90 días como mínimo.\n"
			. "4. Volver aquí y darle a «Analizar este sitio ahora».",
	],

	// ── Archivos a la vista ─────────────────────────────────────────────────

	'config_respaldo' => [
		'aclaracion' => 'Una copia de wp-config.php con otra extensión (.bak, .old, .txt) que el servidor sirve como texto: dentro están el usuario y la contraseña de la base de datos.',
		'arreglo'    => "Esto es urgente: con ese archivo alguien entra a la base de datos.\n"
			. "1. Entrar por SFTP o por el administrador de archivos de MyKinsta.\n"
			. "2. Borrar el archivo que el panel nombra. No moverlo a otra carpeta del sitio: borrarlo.\n"
			. "3. Cambiar la contraseña de la base de datos en MyKinsta y ponerla en wp-config.php.\n"
			. "4. Cambiar las llaves de sesión (ver la norma de las llaves únicas): eso echa a quien esté dentro.",
	],

	'debug_log' => [
		'aclaracion' => 'El archivo donde WordPress escribe los errores. Trae rutas del servidor y, a veces, datos de la base.',
		'arreglo'    => "1. Borrar /wp-content/debug.log por SFTP.\n"
			. "2. En wp-config.php, poner: define( 'WP_DEBUG', false );\n"
			. "3. Si hace falta dejar el registro encendido, añadir también define( 'WP_DEBUG_DISPLAY', false ); y define( 'WP_DEBUG_LOG', '/ruta/fuera/del/public/debug.log' );",
	],

	'volcados' => [
		'aclaracion' => 'Un .zip, .sql o .tar.gz dentro del sitio que cualquiera puede descargar. Es el sitio entero, o la base entera.',
		'arreglo'    => "Esto es urgente.\n"
			. "1. Bajar el archivo a la computadora, por si hace falta.\n"
			. "2. Borrarlo del servidor.\n"
			. "3. Si era un volcado de la base, cambiar la contraseña de la base y las llaves de sesión.\n"
			. "4. Para la próxima: los respaldos van a los de Kinsta, no a una carpeta del sitio.",
	],

	'phpinfo' => [
		'aclaracion' => 'Una página que lista toda la configuración del servidor: versiones, rutas, extensiones y a veces variables de entorno.',
		'arreglo'    => "1. Borrar el archivo que el panel nombra.\n"
			. "2. Comprobar abriendo esa dirección en una ventana privada: tiene que dar 404.",
	],

	'restos_instalador' => [
		'aclaracion' => 'Archivos que deja un plugin de migración (Duplicator, All-in-One WP Migration). Traen la base de datos o permiten reinstalar el sitio encima.',
		'arreglo'    => "1. Borrar los archivos que el panel nombra: installer.php, installer-backup.php, *.wpress y la carpeta dup-installer.\n"
			. "2. Desactivar y borrar el plugin de migración si ya se usó.\n"
			. "3. Comprobar que el sitio sigue cargando.",
	],

	'instalador_abierto' => [
		'aclaracion' => '/wp-admin/install.php o /wp-admin/maint/repair.php contestan. El primero puede reinstalar WordPress encima; el segundo da acceso a la base.',
		'arreglo'    => "1. Si el sitio ya está instalado, install.php debería redirigir solo. Si no lo hace, pedirle a Kinsta una regla que lo bloquee.\n"
			. "2. Para repair.php: en wp-config.php, quitar o poner en false la línea WP_ALLOW_REPAIR.\n"
			. "3. Comprobar las dos direcciones en una ventana privada.",
	],

	'delatores' => [
		'aclaracion' => 'Archivos de trabajo que se subieron sin querer: .env, .git, composer.json, package.json, copias de seguridad del editor.',
		'arreglo'    => "1. Borrar los archivos que el panel nombra.\n"
			. "2. Si hay una carpeta .git, borrarla entera: tiene el historial del código y a veces contraseñas de commits viejos.\n"
			. "3. Si había un .env, dar por quemado todo lo que tuviera dentro y cambiarlo.",
	],

	'listado_de_carpetas' => [
		'aclaracion' => 'Al abrir una carpeta sin index.html, el servidor enseña la lista de archivos que hay dentro.',
		'arreglo'    => "Lo arregla el hosting.\n"
			. "1. Abrir un ticket en MyKinsta pidiendo que se desactive el autoindex para ese sitio.\n"
			. "2. Mientras tanto, poner un index.html vacío en la carpeta que el panel nombra.",
	],

	'readme_expuesto' => [
		'aclaracion' => 'El archivo readme.html de WordPress dice la versión exacta. WordPress lo vuelve a crear en cada actualización.',
		'arreglo'    => "1. Borrar /readme.html por SFTP.\n"
			. "2. Vuelve a aparecer en cada actualización de WordPress, así que no se gana mucho borrándolo a mano cada vez.\n"
			. "3. Lo que de verdad cierra esto es pedirle a Kinsta una regla que devuelva 404 para /readme.html y /license.txt.",
	],

	'subidas_publicas' => [
		'aclaracion' => 'Un archivo que alguien subió por un formulario (una cédula, un currículum, una factura) se descarga con solo saber la dirección.',
		'arreglo'    => "1. Mirar qué formulario lo subió y si de verdad hace falta guardar el archivo en el sitio.\n"
			. "2. Si el formulario es de Elementor o WPForms, configurarlo para que mande el archivo por correo y no lo guarde.\n"
			. "3. Si hay que guardarlo, pedirle a Kinsta que la carpeta de ese formulario pida autenticación.\n"
			. "4. Borrar lo que ya esté subido y no haga falta.",
	],

	'php_en_subidas_archivos' => [
		'aclaracion' => 'Hay archivos .php dentro de la carpeta de subidas. Ahí nunca debería haber código: solo imágenes y documentos.',
		'arreglo'    => "Tratarlo como un posible sitio comprometido hasta demostrar lo contrario.\n"
			. "1. Mirar los archivos que el panel nombra, sin ejecutarlos.\n"
			. "2. Si no se reconocen, bajarlos como evidencia y borrarlos del servidor.\n"
			. "3. Pedir un escaneo de malware a Kinsta.\n"
			. "4. Cambiar las contraseñas de los administradores y las llaves de sesión.",
	],

	'htaccess_inerte' => [
		'aclaracion' => 'Un plugin de seguridad escribió sus reglas en un archivo .htaccess. Este servidor es Nginx y no lee ese archivo: la protección parece puesta y no existe.',
		'arreglo'    => "Es de las peores de la lista, porque el panel del plugin dice «protegido».\n"
			. "1. Mirar qué protege ese .htaccess.\n"
			. "2. Pedirle a Kinsta la misma regla en Nginx.\n"
			. "3. Hasta que Kinsta conteste, dar esa protección por inexistente.\n"
			. "4. No borrar el .htaccess: no hace daño, y si el sitio se mueve a Apache vuelve a servir.",
	],

	'drop_ins_esperados' => [
		'aclaracion' => 'Archivos como object-cache.php o advanced-cache.php que WordPress carga antes que nada. Los pone el hosting o un plugin de caché — y también los pone quien entra sin permiso.',
		'arreglo'    => "1. Plugins → Drop-ins, y mirar la lista.\n"
			. "2. En Kinsta son normales object-cache.php (Redis) y, a veces, advanced-cache.php.\n"
			. "3. Cualquier otro, o uno que nadie reconozca, se abre y se mira antes de borrarlo.\n"
			. "4. Si tiene código raro, tratarlo como sitio comprometido: escaneo y cambio de contraseñas.",
	],

	// ── Lo que el navegador recibe ──────────────────────────────────────────

	'certificado' => [
		'aclaracion' => 'El candado del navegador. Si está vencido o no cubre el dominio, el visitante ve una pantalla de advertencia antes de entrar.',
		'arreglo'    => "Lo arregla Kinsta.\n"
			. "1. MyKinsta → el sitio → Dominios → ver el estado del certificado.\n"
			. "2. Si se puede, pulsar «Generar certificado» para el dominio que falta.\n"
			. "3. Si el dominio apunta a otro sitio o no resuelve, eso se arregla primero en el DNS.\n"
			. "4. Si nada de lo anterior, abrir ticket con Kinsta.",
	],

	'hsts' => [
		'aclaracion' => 'Una cabecera que le dice al navegador «de aquí en adelante, a este sitio entrá siempre cifrado». La primera visita de cada persona sigue saliendo sin cifrar hasta que se pone.',
		'arreglo'    => "Lo arregla el hosting.\n"
			. "1. Abrir ticket en MyKinsta pidiendo la cabecera Strict-Transport-Security con max-age de un año.\n"
			. "2. Antes de pedirlo, comprobar que TODO el sitio funciona por https, subdominios incluidos: con HSTS puesto, lo que no esté cifrado deja de cargar.\n"
			. "3. No pedir «preload» la primera vez: eso es difícil de deshacer.",
	],

	'bloqueo_de_marco' => [
		'aclaracion' => 'Sin esto, cualquiera puede cargar el sitio invisible dentro del suyo y hacer que la gente haga clic en lo que no ve.',
		'arreglo'    => "Lo arregla el hosting.\n"
			. "1. Abrir ticket en MyKinsta pidiendo la cabecera Content-Security-Policy con frame-ancestors 'self'.\n"
			. "2. Si el sitio se incrusta a propósito en otro (un formulario, un mapa), decir en el ticket desde qué dominios sí.",
	],

	'cors_abierto' => [
		'aclaracion' => 'El sitio deja que páginas de otros dominios le pidan datos con la sesión de quien está dentro. Es una puerta abierta a los datos de quien visita.',
		'arreglo'    => "1. Buscar qué plugin lo puso: casi siempre uno de API o de integración.\n"
			. "2. En sus ajustes, cambiar el «*» por la lista de dominios que de verdad hacen falta.\n"
			. "3. Si no aparece el plugin, pedirle a Kinsta que quite la cabecera Access-Control-Allow-Origin de ese sitio.",
	],

	'contenido_mixto' => [
		'aclaracion' => 'La página va cifrada pero carga una imagen o un script por http://. El navegador avisa, y en algunos casos no lo carga.',
		'arreglo'    => "1. Abrir la portada, F12 → Consola, y mirar qué recurso sale en rojo.\n"
			. "2. Si es un archivo del propio sitio, cambiar la dirección a https en el contenido.\n"
			. "3. Si son muchos, instalar «Better Search Replace» y cambiar http://eldominio por https://eldominio en toda la base.\n"
			. "4. Hacer respaldo antes del cambio masivo.",
	],

	'dominio_canonico' => [
		'aclaracion' => 'El sitio contesta igual con www y sin www. Para los buscadores son dos sitios con el mismo contenido, y las sesiones no se comparten entre los dos.',
		'arreglo'    => "1. Decidir cuál es el bueno: el que esté en Ajustes → Generales.\n"
			. "2. MyKinsta → Dominios → marcar ese como primario.\n"
			. "3. Kinsta redirige el otro solo.\n"
			. "4. Comprobar abriendo las dos direcciones.",
	],

	'csp' => [
		'aclaracion' => 'Una lista de desde dónde puede el sitio cargar scripts e imágenes. Sin ella, un script metido por un plugin viejo carga lo que quiera.',
		'arreglo'    => "Es la más delicada de poner: mal hecha, deja el sitio en blanco.\n"
			. "1. Pedirle a Kinsta la cabecera en modo «Report-Only» primero.\n"
			. "2. Navegar el sitio entero una semana y mirar qué se quejaría.\n"
			. "3. Con esa lista, pedir la cabecera de verdad.\n"
			. "4. Si no hay tiempo para eso, pasar la norma a «aplazada» con el motivo escrito. Es mejor que una política a medias.",
	],

	'referrer_policy' => [
		'aclaracion' => 'Cuando alguien hace clic en un enlace que sale del sitio, el otro sitio recibe la dirección completa de donde venía — incluida la del escritorio.',
		'arreglo'    => "Lo arregla el hosting.\n"
			. "1. Abrir ticket en MyKinsta pidiendo la cabecera Referrer-Policy con el valor strict-origin-when-cross-origin.\n"
			. "2. No rompe nada: es el valor que ya traen los navegadores modernos por defecto.",
	],

	'permissions_policy' => [
		'aclaracion' => 'Sin ella, cámara, micrófono y ubicación quedan disponibles para todo lo que cargue la página, plugins de terceros incluidos.',
		'arreglo'    => "Lo arregla el hosting.\n"
			. "1. Abrir ticket en MyKinsta pidiendo la cabecera Permissions-Policy apagando lo que el sitio no use: camera=(), microphone=(), geolocation=().\n"
			. "2. Si el sitio tiene un mapa con «usar mi ubicación», dejar geolocation=(self).",
	],

	'xss_obsoleta' => [
		'aclaracion' => 'Una cabecera vieja que los navegadores de hoy ignoran, y que en los de ayer causaba fallos. Sobra.',
		'arreglo'    => "1. Buscar qué plugin de seguridad la pone y quitar esa opción.\n"
			. "2. Si la pone el servidor, pedirle a Kinsta que la quite.\n"
			. "3. Lo que sí protege es la política de contenido: ver esa norma.",
	],

	'trace_habilitado' => [
		'aclaracion' => 'Un método de HTTP para depurar que devuelve lo que se le manda. Hoy no sirve para nada y se usó para robar cookies.',
		'arreglo'    => "Lo arregla el hosting.\n"
			. "1. Abrir ticket en MyKinsta pidiendo que se bloquee el método TRACE.\n"
			. "2. No afecta a nada del sitio.",
	],

	'sitio_indexable' => [
		'aclaracion' => 'El sitio le está pidiendo a Google que no lo muestre. En un sitio de desarrollo está bien; en uno publicado es un problema de negocio.',
		'arreglo'    => "1. Ajustes → Lectura, y desmarcar «Disuade a los motores de búsqueda».\n"
			. "2. Revisar también el plugin de SEO: Yoast tiene su propio interruptor.\n"
			. "3. Comprobar que https://eldominio/robots.txt ya no diga Disallow: /\n"
			. "4. En Google Search Console, pedir la indexación de la portada.",
	],

	'https_forzado' => [
		'aclaracion' => 'Que quien entre por http:// sea mandado a https:// por el servidor, antes de que WordPress cargue.',
		'arreglo'    => "1. MyKinsta → el sitio → Herramientas → «Forzar HTTPS», encenderlo.\n"
			. "2. Comprobar abriendo http://eldominio: tiene que saltar solo a https.",
	],

	'admin_por_https' => [
		'aclaracion' => 'El escritorio se puede servir sin cifrar, así que la contraseña de un administrador puede viajar en claro.',
		'arreglo'    => "1. En wp-config.php, añadir: define( 'FORCE_SSL_ADMIN', true );\n"
			. "2. Ponerla antes de la línea «That's all, stop editing».\n"
			. "3. Encender también «Forzar HTTPS» en MyKinsta.",
	],

	// ── Versiones ───────────────────────────────────────────────────────────

	'wp_al_dia' => [
		'aclaracion' => 'La versión de WordPress ya no recibe parches de seguridad.',
		'arreglo'    => "1. Hacer un respaldo desde MyKinsta.\n"
			. "2. Si el sitio es nuestro, actualizarlo desde Escritorio → Actualizaciones.\n"
			. "3. Si es del cliente, escribirle primero: no se le toca nada sin pedirlo.\n"
			. "4. Después de actualizar, abrir la portada y una página interna para comprobar que no se rompió nada.",
	],

	'php_soportado' => [
		'aclaracion' => 'La versión de PHP ya no recibe parches. Subirla es cosa del hosting, y puede romper plugins viejos.',
		'arreglo'    => "1. Mirar si el sitio tiene plugins que no soporten la versión nueva.\n"
			. "2. Hacer respaldo.\n"
			. "3. Subir la versión en el entorno de pruebas de Kinsta primero, nunca directo en vivo.\n"
			. "4. Si el sitio aguanta, subirla en vivo desde MyKinsta → Herramientas → Motor PHP.\n"
			. "Ojo: un wp-config.php con un error de sintaxis que PHP 7 tolera, PHP 8 no. Revisarlo antes.",
	],

	'nucleo_sin_tocar' => [
		'aclaracion' => 'Hay archivos de WordPress cambiados o que faltan. O alguien editó el núcleo, o el sitio está comprometido.',
		'arreglo'    => "1. Mirar qué archivos señala el panel.\n"
			. "2. Si son cambios que hizo alguien del equipo, sacarlos del núcleo y ponerlos en el tema hijo.\n"
			. "3. Si nadie los reconoce, tratarlo como sitio comprometido: escaneo de malware con Kinsta y cambio de contraseñas.\n"
			. "4. Reinstalar WordPress desde Escritorio → Actualizaciones → «Reinstalar ahora»: eso devuelve el núcleo original sin tocar el contenido.",
	],

	// ── El hosting ──────────────────────────────────────────────────────────

	'sitio_sano' => [
		'aclaracion' => 'Kinsta tiene el sitio marcado como bloqueado o en un estado que no es el normal.',
		'arreglo'    => "1. MyKinsta → el sitio → mirar el aviso que salga arriba.\n"
			. "2. Casi siempre es facturación o una suspensión por abuso.\n"
			. "3. Abrir ticket con Kinsta y avisar a quien lleva la cuenta.",
	],

	'respaldo_reciente' => [
		'aclaracion' => 'Kinsta guarda respaldos diarios. Si el último es de hace días, no hay a dónde volver si algo sale mal.',
		'arreglo'    => "1. MyKinsta → el sitio → Respaldos → ver la fecha del último.\n"
			. "2. Crear uno manual ahora mismo.\n"
			. "3. Si los automáticos están apagados, encenderlos.\n"
			. "4. Si fallan solos, abrir ticket: suele ser que el sitio pasó el tamaño del plan.",
	],

	'trafico_normal' => [
		'aclaracion' => 'El tráfico de este sitio se salió de lo que es normal en él. Puede ser una campaña, o puede ser un ataque.',
		'arreglo'    => "1. MyKinsta → Analíticas → mirar de dónde viene y a qué direcciones.\n"
			. "2. Si son muchas peticiones a /wp-login.php o /xmlrpc.php, es un ataque: ver la norma de la oleada de intentos.\n"
			. "3. Si es tráfico de verdad, no hay nada que arreglar: anotarlo y pasar la norma a «aplazada».",
	],

	'errores_servidor' => [
		'aclaracion' => 'El sitio está devolviendo errores 500 o 502 a parte de las visitas. No está caído del todo, pero falla.',
		'arreglo'    => "1. MyKinsta → Registros → error.log, de la hora en que empezó.\n"
			. "2. Si el error nombra un plugin, desactivarlo y volver a probar.\n"
			. "3. Si no nombra nada, suele ser memoria o tiempo de espera de PHP: pedirle a Kinsta que los suba.\n"
			. "4. Ver también la ficha de la caída en la pantalla de Caídas: ahí está el código y qué significa.",
	],

	'oleada_de_login' => [
		'aclaracion' => 'Alguien está probando contraseñas en la puerta del escritorio, muchas veces por minuto.',
		'arreglo'    => "1. MyKinsta → Herramientas → encender la protección contra fuerza bruta.\n"
			. "2. Comprobar que todos los administradores tengan verificación en dos pasos: es lo único que sirve si una contraseña ya se filtró.\n"
			. "3. Si viene de pocas IP, pedirle a Kinsta que las bloquee.\n"
			. "4. No cambiar la dirección del login: estorba más de lo que protege.",
	],

	'proteccion_bots' => [
		'aclaracion' => 'La protección de Kinsta contra bots está apagada para este sitio.',
		'arreglo'    => "1. MyKinsta → el sitio → Herramientas.\n"
			. "2. Encender la protección.\n"
			. "3. Si se apagó a propósito —porque bloqueaba una integración—, anotarlo y pasar la norma a «aplazada» con el motivo.",
	],

	'excepciones_bots' => [
		'aclaracion' => 'Hay direcciones a las que la protección contra bots no se aplica. Cada excepción es un agujero con nombre.',
		'arreglo'    => "1. MyKinsta → Herramientas → mirar la lista de excepciones.\n"
			. "2. Por cada una, preguntar para qué se puso y si sigue haciendo falta.\n"
			. "3. Quitar las que ya no.\n"
			. "4. Las que sí, anotarlas en el panel para que la próxima revisión no vuelva a empezar de cero.",
	],

	'sftp_adicional' => [
		'aclaracion' => 'Hay cuentas de SFTP además de la principal. Cada una puede escribir en el sitio entero.',
		'arreglo'    => "1. MyKinsta → el sitio → Información → usuarios SFTP.\n"
			. "2. Por cada una, ver de quién es y si sigue trabajando en esto.\n"
			. "3. Borrar las que sobren.\n"
			. "4. A las que se quedan, cambiarles la contraseña si lleva más de un año igual.",
	],

	'reglas_redireccion' => [
		'aclaracion' => 'Hay redirecciones puestas en el panel del hosting, por encima de WordPress. Una mal hecha manda el tráfico a otro sitio.',
		'arreglo'    => "1. MyKinsta → el sitio → Redirecciones.\n"
			. "2. Leer cada una y comprobar a dónde lleva.\n"
			. "3. Borrar las que apunten a dominios que no son del cliente.\n"
			. "4. Anotar las legítimas para no volver a revisarlas desde cero.",
	],

	'rastreadores_ia' => [
		'aclaracion' => 'Los robots que leen el sitio para entrenar modelos. No es un riesgo de seguridad: es una decisión del cliente.',
		'arreglo'    => "1. Preguntarle al cliente si quiere que su contenido se use para entrenar modelos.\n"
			. "2. Si no: añadir al robots.txt las reglas para GPTBot, CCBot, Google-Extended y ClaudeBot.\n"
			. "3. Si sí, o si da igual: anotar la decisión y pasar la norma a «aplazada».\n"
			. "No se cumple sola: hace falta que alguien decida.",
	],

	// ── Dentro del sitio ────────────────────────────────────────────────────

	'sin_vulnerables' => [
		'aclaracion' => 'Hay un plugin o un tema en una versión con una falla publicada. Es la única norma donde alguien de fuera ya sabe cómo entrar.',
		'arreglo'    => "1. Mirar en la pestaña «Lo que tiene pendiente» cuál es y si hay parche.\n"
			. "2. Si hay parche: actualizar. Respaldo antes.\n"
			. "3. Si no hay parche todavía: desactivar el plugin si el sitio puede vivir sin él.\n"
			. "4. Si WordPress.org lo cerró: no va a haber arreglo. Hay que cambiarlo por otro, y eso se habla con el cliente.",
	],

	'sin_inactivos' => [
		'aclaracion' => 'Un plugin desactivado sigue en el servidor, y su código se puede alcanzar desde fuera aunque esté apagado.',
		'arreglo'    => "1. Plugins → Inactivos.\n"
			. "2. Por cada uno: ¿se va a volver a usar? Si no, borrarlo.\n"
			. "3. Desactivar no basta: hay que borrar.\n"
			. "4. Respaldo antes, por si alguno guardaba configuración que haga falta.",
	],

	'plugins_cerrados' => [
		'aclaracion' => 'WordPress.org retiró el plugin de su directorio, casi siempre por un problema de seguridad sin arreglar.',
		'arreglo'    => "No hay actualización que esperar: el plugin ya no existe para WordPress.\n"
			. "1. Ver si el sitio lo usa de verdad o quedó de antes.\n"
			. "2. Si no lo usa: desactivarlo y borrarlo.\n"
			. "3. Si lo usa: buscar el reemplazo y cotizar el cambio. Esto es trabajo que se le factura al cliente.\n"
			. "4. Mientras tanto, dejar por escrito que se asume el riesgo.",
	],

	'plugins_abandonados' => [
		'aclaracion' => 'El autor no publica nada desde hace años. No está roto hoy, pero el día que aparezca una falla no la va a arreglar nadie.',
		'arreglo'    => "No es urgente, pero sí es una conversación que hay que tener.\n"
			. "1. Mirar cuáles son y qué hacen en el sitio.\n"
			. "2. Buscar reemplazo mantenido para los que hagan algo importante.\n"
			. "3. Planificar el cambio con el cliente, sin prisa.\n"
			. "4. Anotar la decisión para que la próxima revisión no vuelva a levantarlo.",
	],

	'tareas_programadas' => [
		'aclaracion' => 'Tareas que WordPress ejecuta solo, cada tanto, y que dejó un plugin que ya no está.',
		'arreglo'    => "1. Instalar «WP Crontrol».\n"
			. "2. Herramientas → Cron Events, y mirar la columna del «hook».\n"
			. "3. Los que empiecen por el prefijo de un plugin que ya no está, borrarlos.\n"
			. "4. No tocar los que empiezan por wp_: son de WordPress.",
	],

	'llaves_unicas' => [
		'aclaracion' => 'Ocho frases largas en wp-config.php con las que WordPress firma las sesiones. Si faltan, son las de ejemplo o se repiten en otro sitio, una sesión robada vale en los dos.',
		'arreglo'    => "1. Abrir https://api.wordpress.org/secret-key/1.1/salt/ en el navegador: genera ocho líneas nuevas.\n"
			. "2. En wp-config.php, reemplazar las ocho líneas de AUTH_KEY a NONCE_SALT por las nuevas.\n"
			. "3. Guardar. Esto echa a todo el mundo del escritorio, incluido uno mismo: avisar antes.\n"
			. "4. Volver a entrar y comprobar que el sitio funciona.",
	],

	'modo_depuracion' => [
		'aclaracion' => 'WP_DEBUG encendido hace que los errores de PHP salgan en la propia página, con rutas del servidor dentro.',
		'arreglo'    => "1. En wp-config.php, poner define( 'WP_DEBUG', false );\n"
			. "2. Si hace falta dejarlo encendido para diagnosticar, añadir define( 'WP_DEBUG_DISPLAY', false ); para que al menos no salga en pantalla.\n"
			. "3. Borrar el debug.log que haya quedado.",
	],

	'puertas_de_mantenimiento' => [
		'aclaracion' => 'Un plugin de «próximamente» o de mantenimiento con una forma de saltárselo: una clave en la dirección, o una cookie.',
		'arreglo'    => "1. Ver qué plugin es y si el modo mantenimiento sigue haciendo falta.\n"
			. "2. Si no: desactivarlo y borrarlo.\n"
			. "3. Si sí: quitar la puerta trasera y dejar que el acceso sea entrando al escritorio normalmente.",
	],

	'actualizaciones_automaticas' => [
		'aclaracion' => 'Las actualizaciones de seguridad del núcleo de WordPress están apagadas. Son las que salen sin avisar y arreglan fallas publicadas.',
		'arreglo'    => "1. En wp-config.php, quitar la línea AUTOMATIC_UPDATER_DISABLED o ponerla en false.\n"
			. "2. Comprobar que no haya un filtro en el tema o en un plugin que las apague.\n"
			. "3. Esto es distinto del auto-update de Kinsta, que es por módulo: las dos cosas tienen que estar.",
	],

	'credenciales_en_config' => [
		'aclaracion' => 'Hay contraseñas de servicios —SMTP, una API, una pasarela— escritas en wp-config.php en texto plano.',
		'arreglo'    => "1. Mirar cuáles son y de qué servicio.\n"
			. "2. Pasarlas a variables de entorno de Kinsta y leerlas con getenv() en vez de escribirlas.\n"
			. "3. Cambiar cada contraseña después de moverla: la vieja ya estuvo en un archivo que se puede copiar.\n"
			. "4. Si el servicio no permite variables de entorno, anotarlo y pasar la norma a «aplazada».",
	],

	'formularios_honeypot' => [
		'aclaracion' => 'Un campo invisible que los robots rellenan y las personas no. Sin eso ni captcha, los formularios reciben spam y se usan para mandar correo.',
		'arreglo'    => "1. En Elementor: abrir el formulario, Opciones adicionales → activar «Honeypot».\n"
			. "2. Si el sitio usa otro plugin de formularios, buscar su opción equivalente.\n"
			. "3. Si el spam sigue, añadir reCAPTCHA v3, que no le pide nada al visitante.\n"
			. "4. Comprobar enviando el formulario una vez.",
	],

	// ── La tienda ───────────────────────────────────────────────────────────

	'tienda_api_cerrada' => [
		'aclaracion' => 'Los pedidos o los clientes de la tienda se pueden leer desde fuera sin contraseña. Son nombres, direcciones y teléfonos.',
		'arreglo'    => "Esto es urgente: son datos personales de clientes.\n"
			. "1. WooCommerce → Ajustes → Avanzado → API REST, y revocar las llaves que no se reconozcan.\n"
			. "2. Comprobar que /wp-json/wc/v3/orders pida autenticación en una ventana privada.\n"
			. "3. Si responde sin pedir nada, hay un plugin abriéndolo: buscarlo y quitarlo.\n"
			. "4. Avisar a quien lleva la cuenta: puede haber que notificar a los clientes.",
	],

	'tienda_logs_cerrados' => [
		'aclaracion' => 'Los registros de WooCommerce se descargan sabiendo la dirección. Traen datos de pedidos y respuestas de la pasarela de pago.',
		'arreglo'    => "1. Comprobar que /wp-content/uploads/wc-logs/ no se liste ni se descargue.\n"
			. "2. Si se descarga, pedirle a Kinsta que bloquee esa carpeta.\n"
			. "3. WooCommerce → Estado → Registros, y borrar los que ya no hagan falta.\n"
			. "4. Bajar el nivel de registro si está en «debug».",
	],

	'tienda_descargas_protegidas' => [
		'aclaracion' => 'Los archivos que la tienda vende se bajan con solo saber la dirección, sin haber comprado.',
		'arreglo'    => "1. WooCommerce → Ajustes → Productos → Descargables.\n"
			. "2. Poner «Método de descarga» en «Redirección forzada» o «X-Accel-Redirect».\n"
			. "3. Mover los archivos a una carpeta fuera de /uploads/ si se puede.\n"
			. "4. Probar la dirección directa en una ventana privada: no debe bajar nada.",
	],

	'tienda_pedido_invitado' => [
		'aclaracion' => 'La página de «pedido recibido» se abre con solo el número de pedido, sin pedir el correo. Cambiando el número se ven los pedidos de otros.',
		'arreglo'    => "1. WooCommerce → Ajustes → Cuentas y privacidad.\n"
			. "2. Desmarcar «Permitir a los clientes hacer pedidos sin una cuenta» si el negocio lo permite.\n"
			. "3. Si hace falta el pedido de invitado, comprobar que la página pida el correo para confirmar.\n"
			. "4. Probar con el número de un pedido real desde una ventana privada.",
	],

	'tienda_sin_datos_de_tarjeta' => [
		'aclaracion' => 'Hay números de tarjeta guardados en la base de datos del sitio. Eso no se puede tener, ni cifrado ni sin cifrar.',
		'arreglo'    => "Esto es lo más grave de la lista. Parar y avisar antes de tocar nada.\n"
			. "1. Avisar a quien lleva la cuenta y al cliente, hoy.\n"
			. "2. Mirar qué plugin los está guardando y desactivarlo.\n"
			. "3. Borrar los datos de la base, con respaldo previo guardado fuera del servidor.\n"
			. "4. La tienda no debe tocar el número nunca: eso lo hace la pasarela, en su propia página o en su iframe.",
	],

	'tienda_webhooks_cifrados' => [
		'aclaracion' => 'Avisos automáticos que la tienda manda a otro sistema por http:// en vez de https://. Van en claro por internet.',
		'arreglo'    => "1. WooCommerce → Ajustes → Avanzado → Webhooks.\n"
			. "2. Cambiar cada dirección http:// por https://.\n"
			. "3. Si el destino no tiene https, el problema es del otro sistema: hay que escribirle a quien lo lleva.\n"
			. "4. Borrar los webhooks que ya no apunten a ningún sitio.",
	],

	'tienda_limpieza_viva' => [
		'aclaracion' => 'WooCommerce puede borrar solo los datos personales viejos, pero la tarea está configurada y no corre.',
		'arreglo'    => "1. WooCommerce → Ajustes → Cuentas y privacidad → ver los plazos de retención.\n"
			. "2. Instalar «WP Crontrol» y comprobar que la tarea de limpieza esté programada.\n"
			. "3. Si WP-Cron está apagado en wp-config.php, programar una tarea de verdad en MyKinsta → Cron jobs.\n"
			. "4. Comprobar a los pocos días que los datos viejos ya no están.",
	],

	'tienda_pasarela_en_vivo' => [
		'aclaracion' => 'Hay una pasarela de pago en modo de pruebas en un sitio publicado. Los cobros no llegan.',
		'arreglo'    => "1. WooCommerce → Ajustes → Pagos, y abrir la pasarela que el panel nombra.\n"
			. "2. Quitar el modo de pruebas y poner las llaves de producción.\n"
			. "3. Hacer una compra de verdad por el monto mínimo y comprobar que llega.\n"
			. "4. Si la tienda todavía no abre, esto no es un fallo: anotarlo y pasar la norma a «aplazada».",
	],
];
