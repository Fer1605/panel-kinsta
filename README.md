# Panel Kinsta

Panel de control y evidencia para una cartera de sitios WordPress alojados en
[Kinsta](https://kinsta.com). Consolida en un solo lugar qué tiene instalado
cada sitio, qué le falta actualizar, qué tiene una vulnerabilidad conocida y
qué se le notificó al cliente — con fecha.

Está hecho como plugin de WordPress: se instala en un WordPress propio, aparte
de los sitios que vigila, para que siga funcionando aunque el hosting vigilado
se caiga.

## Qué resuelve

Una agencia con muchos sitios en Kinsta puede ver cada sitio por separado en
MyKinsta, pero no el conjunto. Y cuando un sitio desactualizado se infecta, no
hay forma rápida de demostrar que al cliente se le avisó.

Este panel resuelve las dos cosas:

- **El conjunto.** Todos los sitios agrupados por etiqueta, con su versión de
  WordPress, sus plugins, qué está pendiente de actualizar y qué está
  vulnerable.
- **La evidencia.** Cada aviso que manda queda registrado con qué se notificó,
  a quién y cuándo.

## Solo lectura, por diseño

Kinsta **no ofrece llaves de API de solo lectura**: al crear una llave solo se
elige nombre y caducidad, no hay alcances, y la llave hereda los permisos de
quien la crea. Tampoco existe un rol de MyKinsta que pueda ver los sitios sin
poder administrarlos.

Por eso la restricción vive en el código, donde se puede comprobar:

```bash
grep -rn "wp_remote_" panel-kinsta/
```

En todo el plugin solo debe aparecer `wp_remote_get`. El panel habla con tres
direcciones —`api.kinsta.com`, `wordfence.com` y `api.wordpress.org`— y la de
Kinsta se arma únicamente en `includes/kinsta/class-http.php`, cuya clase
`SWPK_Http` expone un solo método de red y no acepta el verbo HTTP como
parámetro. Para que el panel pudiera actualizar un plugin, reiniciar PHP o
crear un usuario en la cuenta de hosting, alguien tendría que escribir código
nuevo, y eso queda en el historial del repositorio.

La llave se configura como constante en `wp-config.php`, nunca en la base de
datos:

```php
define( 'SWPK_KINSTA_API_KEY',    'la-llave' );
define( 'SWPK_KINSTA_COMPANY_ID', 'el-company-id' );
define( 'SWPK_WORDFENCE_KEY',     'la-llave-de-wordfence' );
```

**Y una línea que no es opcional:** el WordPress donde viva el panel tiene que
estar en la zona horaria de verdad —`America/Guatemala`— antes de la primera
corrida. El panel guarda casi todas sus fechas en hora local y compara contra
ese mismo reloj; si se deja en UTC y se cambia después, las fechas escritas
antes del cambio quedan corridas seis horas, y son justo las fechas que el panel
enseña para que nadie dude de cuándo es un dato.

Si la encuentra en la base de datos, el panel lo advierte en pantalla: una
llave ahí viaja dentro de cada respaldo.

## Fuentes de datos

| Fuente | Qué aporta | Costo |
|---|---|---|
| **API de Kinsta** (v2) | Sitios, etiquetas, entornos, versión de WordPress y PHP, plugins y temas con su versión, si hay actualización, si la versión es vulnerable y si tiene actualización automática | incluido en el hosting |
| **Wordfence Intelligence** | El *por qué*: CVE, severidad CVSS, descripción y qué versión corrige cada vulnerabilidad | gratis, uso comercial permitido |
| **api.wordpress.org** | Si un plugin fue retirado del repositorio (no va a recibir parches nunca más) | gratis |

Kinsta responde un sí/no por plugin. Wordfence es lo que convierte ese sí/no en
una frase que se le puede explicar a un cliente.

### El feed se descarga una vez al día, y el límite está en el código

El feed de vulnerabilidades se publica una vez al día: pedirlo más seguido no
trae nada nuevo y cuenta contra el límite del proveedor, que avisa por correo y
puede llegar a **suspender la llave**.

Por eso el freno no depende de que alguien se acuerde:

- No se vuelve a descargar si hace menos de 20 horas que se bajó bien. No es un
  error, simplemente no hace falta.
- Ante un **429**, se anota hasta cuándo no insistir y la espera se va
  duplicando (6 h → 12 h → … hasta 48 h). Insistir contra un 429 es justo lo que
  lleva a la suspensión.
- Las dos comprobaciones ocurren **antes** de tocar la red.

La pantalla de Ajustes muestra cuándo fue la última descarga y a partir de
cuándo se permite la siguiente.

## Modo de prueba

El panel funciona completo **sin llave de Kinsta**. En modo de prueba genera una
cartera de sitios inventados —con vulnerabilidades, plugins atrasados y sitios
al día— para poder desarrollarlo, probarlo y enseñarlo antes de tener accesos.

Los identificadores de vulnerabilidad de ese modo llevan el prefijo `DEMO-` a
propósito, y el panel muestra un aviso en todas las pantallas.

Se cambia en **Panel Kinsta → Ajustes → Modo**.

## Instalación para desarrollo

Requiere PHP 8.1 o superior y WordPress 6.4 o superior.

Con [Studio](https://developer.wordpress.com/studio/) (gratis, macOS y Windows):

```bash
studio create --path ~/Studio/panel --name "Panel" --php 8.3
ln -s /ruta/a/este/repo/panel-kinsta ~/Studio/panel/wp-content/plugins/panel-kinsta
cd ~/Studio/panel && studio wp plugin activate panel-kinsta
```

Y la primera corrida:

```bash
studio wp eval 'print_r( SWPK_Sincronizador::correr() );'
```

Con cualquier otro WordPress local, basta con copiar o enlazar la carpeta
`panel-kinsta/` dentro de `wp-content/plugins/` y activarla.

## Dos plugins

| | Dónde se instala | Qué hace |
|---|---|---|
| **`panel-kinsta/`** | En un WordPress propio, uno solo | Lee el hosting, cruza vulnerabilidades, avisa y deja constancia |
| **`agente-sitio/`** | En cada sitio vigilado | Reporta quién tiene acceso de administrador y activa la verificación en dos pasos |

El agente existe porque la API de Kinsta **no tiene endpoint para listar los
usuarios de WordPress**: tiene para crear un administrador, para preguntar si un
correo ya existe y para generar un enlace de acceso, pero no para descubrir
usuarios que no sabíamos que existían — que es justo lo que hay que detectar
cuando un malware crea una cuenta.

El sitio llama al panel, no al revés: **el sitio vigilado no abre ningún endpoint
nuevo.** Cada reporte va firmado con HMAC-SHA256 y una marca de tiempo, con un
secreto propio de ese sitio que el panel le entregó al registrarlo. Que el
reporte deje de llegar también es información: un sitio callado 48 horas aparece
marcado.

Lo que sale del sitio: id, usuario, correo, roles y fecha de alta de cada usuario
con rol de control, si la verificación en dos pasos está activa y si el sitio
logra enviar correo. **No salen contraseñas, ni contenido, ni nada más de la base
de datos.**

### La verificación en dos pasos

Está escrita en el agente en lugar de depender de un plugin de terceros, por dos
razones: un solo plugin por sitio en vez de dos, y que el panel pueda llevar el
marcador de cobertura sin hurgar en las tablas de otro fabricante.

El flujo es el de siempre: WordPress valida la contraseña, el agente le quita la
sesión recién creada, manda un código de seis dígitos por correo y solo entrega
la sesión cuando el código es correcto. Las reglas que lo sostienen:

- Código generado con `random_int`, **guardado cifrado**, de un solo uso y con 10
  minutos de vigencia.
- Cinco intentos y bloqueo de 15 minutos.
- Un testigo de un solo uso impide probar códigos contra un usuario sin haber
  pasado antes su contraseña.
- Códigos de respaldo, también cifrados.
- Solo a los roles con control. **Nunca a suscriptores o clientes de tienda:**
  pedirle un código por correo a cada comprador tumba la venta.
- Salida de emergencia: `define( 'SWA_2FA_APAGADO', true );` en `wp-config.php`.

**Dos límites que conviene tener claros:**

1. **Esto protege el formulario de login y nada más.** No protege XML-RPC ni la
   API REST con contraseñas de aplicación, porque esas vías no pasan por el
   formulario. El agente ofrece cerrar XML-RPC en la misma visita.
2. **El sitio tiene que poder enviar correo.** Si `wp_mail` está roto, el código
   no llega y los administradores quedan fuera de su propio sitio. Por eso el
   agente prueba el envío antes y **se niega a activar la verificación si el
   envío falla**.

## Perfiles de acceso

Tres roles propios de WordPress para personas, más uno para programas. Si una asesora necesitara
ser *administradora* para ver su panel, tendría acceso a instalar plugins y
editar código en el sitio desde el cual se consulta la información de todos los
clientes.

| Perfil | Qué ve | Qué no |
|---|---|---|
| **Dirección** | Todo, más los ajustes y el registro de accesos | — |
| **Ventas** | Solo los sitios que tiene asignados: qué actualizar, por qué importa, y la constancia de lo que se le avisó a ese cliente | Caídas, accesos, ajustes, llaves |
| **Soporte** | Todos los sitios, del lado técnico: caídas, vulnerabilidades, estado de los agentes | Accesos, ajustes, llaves |
| **Llave de consulta** | Lo mismo que soporte, pero por la API y solo de lectura | Accesos, ajustes, llaves, y **disparar cualquier proceso** |

Que ventas vea solo lo suyo no es desconfianza: un panel con 107 sitios donde
solo 12 son los tuyos no se usa. El recorte es lo que lo hace útil.

Cada pantalla vuelve a comprobar el permiso al abrirse, así que esconder la
opción del menú no es lo único que la protege: entrar por la URL directa
devuelve un 403.

### El registro de accesos

Entradas, salidas e intentos fallidos. El tercero no se pidió, pero es el que
más sirve: una ráfaga de intentos contra el panel es la señal de que alguien
está probando contraseñas contra el sitio desde el que se consulta a todos los
clientes.

**No se guarda ninguna contraseña, ni siquiera las de los intentos fallidos.**
Un registro de contraseñas intentadas es una lista de contraseñas reales de
gente que se equivocó de campo.

### La verificación en dos pasos del panel

El panel es un WordPress, así que usa **el mismo agente** que se instala en los
sitios vigilados, con el reporte apagado. Una sola implementación de código
sensible en vez de dos — mantener dos versiones de lo mismo es cómo se arreglan
los fallos en una sola y se olvidan en la otra.

Hay una interacción entre las dos funciones que vale conocer, porque costó
encontrarla: la verificación en dos pasos corta el login para mostrar su
pantalla, así que el gancho normal de WordPress donde se anotaba la entrada
**nunca llegaba a correr**. La entrada se anota ahora cuando el segundo paso se
supera, que es cuando la persona entró de verdad. Anotarla antes habría
registrado como entrada a alguien que solo acertó la contraseña.

## El monitor de caídas

El panel revisa cada sitio cada cinco minutos desde su propio servidor, en
paralelo con `curl_multi`. No depende de que el hosting avise: **un aviso que
depende del hosting no sirve para enterarse de que el hosting se cayó.** Esa es
la razón de que el panel viva fuera del hosting que vigila.

Qué cuenta como caído:

| Estado | Cuándo |
|---|---|
| **Responde** | 2xx o 3xx y el contenido no delata un error |
| **Sin responder** | No contesta, tarda demasiado, devuelve 5xx, o contesta 200 **mostrando un error de WordPress** |
| **Respuesta rara** | 4xx. Puede ser un cortafuegos bloqueando al monitor y no una caída real: se marca, no se grita |

El tercer caso del segundo renglón es el que más se escapa: un sitio con
"Error establishing a database connection" devuelve 200 y cualquier monitor
ingenuo lo da por sano. Para el visitante ese sitio está caído.

**No avisa al primer fallo.** Hacen falta dos sondeos fallidos seguidos —diez
minutos— antes de declarar la caída. Un monitor que grita por cada parpadeo de
red deja de leerse en una semana, y entonces no sirve para nada.

Los errores de red se traducen antes de mandarlos: *el dominio no resuelve*,
*el servidor rechazó la conexión*, *el certificado está vencido*. Para quien
está en soporte no es lo mismo un problema de DNS que un hosting caído, y "no
respondió" obliga a ir a averiguarlo a mano.

### WP-Cron no sirve para esto

WP-Cron se dispara con las visitas al sitio. **Un panel que casi nadie visita no
ejecuta nada**, y un monitor que no corre es peor que no tener monitor: da falsa
tranquilidad. El panel detecta esto y lo avisa en pantalla.

En producción hay que apagarlo y poner una tarea programada de verdad. En
`wp-config.php`:

```php
define( 'DISABLE_WP_CRON', true );
```

Y en el panel del servidor (Plesk → Tareas programadas), cada 5 minutos:

```
/usr/bin/php /ruta/al/sitio/wp-cron.php >/dev/null 2>&1
```

## Exportar

Cualquier lista se baja a Excel con los filtros que estén puestos — exportar
siempre todo obligaría a filtrar otra vez en Excel, que es lo que se quería
evitar. Dos detalles que parecen menores:

- El CSV lleva **BOM**, porque Excel abre UTF-8 sin BOM convirtiendo
  "Librería" en "LibrerÃ­a".
- Las columnas van separadas por **punto y coma**, que es lo que espera Excel
  en español. Con coma, todo cae en una sola columna.

Y cada sitio tiene un **reporte imprimible** con lo que se le notificó al
cliente en un periodo, lo que cambió en el sitio y su estado actual. El PDF lo
genera el navegador desde el diálogo de impresión: sin librerías de PDF, sin
problemas de memoria y sin pelearse con fuentes y acentos.

## Cómo está armado

```
panel-kinsta/
├── panel-kinsta.php              arranque y selección de la fuente de datos
└── includes/
    ├── class-tablas.php          esquema (6 tablas)
    ├── class-ajustes.php         configuración; la constante manda sobre la BD
    ├── class-etiquetas.php       traduce las etiquetas del hosting a 3 grupos
    ├── class-versiones.php       comparación de versiones tolerante
    ├── class-inventario.php      el inventario consolidado
    ├── class-sincronizador.php   la corrida diaria
    ├── class-wordfence.php       feed de vulnerabilidades (lectura en trozos)
    ├── class-riesgo.php          cruce de versiones instaladas contra el feed
    ├── class-eventos.php         comparación entre fotos: qué cambió
    ├── class-perfiles.php        roles, permisos y alcance por perfil
    ├── class-llaves.php          las llaves de la API, lejos de las personas
    ├── class-accesos.php         entradas, salidas e intentos fallidos
    ├── class-monitor.php         sondeo de disponibilidad cada 5 minutos
    ├── class-receptor.php        recibe los reportes firmados de los agentes
    ├── class-api.php             la puerta de consulta: 10 rutas, sin lógica propia
    ├── class-usuarios.php        línea base de usuarios y alerta de admin nuevo
    ├── class-bitacora.php        registro de notificaciones
    ├── class-textos.php          los avisos, redactados por audiencia
    ├── kinsta/
    │   ├── interface-fuente.php  el contrato
    │   ├── class-http.php        cliente HTTP — SOLO GET
    │   ├── class-fuente-api.php  la API real
    │   └── class-fuente-demo.php datos de prueba
    └── admin/                    el panel

conector-mcp/                 el traductor para Claude Desktop (Node, sin dependencias)
```

El hosting está detrás de `SWPK_Fuente`, no escrito por todo el código: agregar
otro proveedor es escribir una clase que implemente esa interfaz.

### Dos decisiones que no son obvias

**La historia se construye con fotos diarias.** Kinsta no guarda un historial de
actualizaciones consultable. El panel toma una foto del estado cada día y la
compara con la anterior; de esa comparación salen los eventos ("este plugin pasó
de 3.1 a 3.2"). Consecuencia honesta: el conteo empieza el día que se enciende el
panel y no se puede reconstruir hacia atrás.

**El feed de vulnerabilidades se lee en trozos.** Los endpoints de Wordfence
devuelven el feed completo y no aceptan parámetros — no se puede consultar por
plugin. Son decenas de MB, así que `json_decode` del archivo entero agota la
memoria de PHP. `SWPK_Wordfence::recorrer_feed()` va entregando un registro a la
vez contando llaves y comillas, y solo se guardan las vulnerabilidades del
software que realmente está instalado.

## La API de consulta del panel

Diez rutas en `swpk/v1`, siete de lectura y tres que disparan los mismos
procesos que los botones. **Ninguna trae lógica propia:** cada una envuelve la
función que ya alimenta su pantalla, que es lo único que impide que el panel y
la API se contradigan. La pantalla *Conectarse (API)* tiene la dirección, el
botón para crear una llave, el catálogo de rutas y un probador en vivo.

- **Identificación:** contraseñas de aplicación de WordPress, con autenticación
  básica. Exigen HTTPS, salvo en entornos de desarrollo.
- **Alcance:** el del perfil de la llave. Una llave de ventas recibe sus sitios
  y nada más, en todas las rutas. Un sitio que no es suyo contesta 404, no 403:
  a quien no tiene por qué verlo no se le confirma que exista.
- **Rastro:** cada llamada queda en `swpk_accesos`, la que pasa y la que se
  rechaza, con la ruta que pidió. Hace falta porque una contraseña de aplicación
  se salta la verificación en dos pasos, así que esa fila es lo único que queda
  de ella.
- **Fecha:** toda respuesta lleva `obtenido_en`, `generado_en` y los relojes
  completos. Un JSON sin fecha es una afirmación sin fecha.
- **No escribe.** Lee y dispara procesos. Nada más.

La llave de Kinsta no sale del plugin: si hace falta el dato fresco, se dispara
un proceso y el panel pregunta con la suya.

### Una llave no vive con las personas

Por dentro una llave es una cuenta de WordPress, y tiene que serlo: la
autenticación básica necesita un usuario, y el recorte por perfil cuelga de que
haya una identidad. Pero una cuenta suelta en *Usuarios* se puede editar, y ahí
`swpk_api` deja de ser de solo lectura — basta con cambiarle la contraseña o
subirle el rol desde un desplegable.

`SWPK_Llaves` las saca de ahí y les pone cuatro barrotes:

1. No salen en *Usuarios* ni en sus filtros. Su lista es *Conectarse (API)*.
2. No se editan, no se borran y no se ascienden: tampoco un administrador.
   `map_meta_cap` niega `edit_user`, `delete_user`, `promote_user` y sus
   parientes cuando el destino es una llave.
3. No entran por el formulario de WordPress (`wp_authenticate_user`), lo cual
   **no** afecta a las contraseñas de aplicación: `wp_authenticate_application_password()`
   no pasa por ese filtro.
4. No se les puede pedir "recuperar contraseña".

Revocar borra la cuenta, no le quita el rol. Dejarla sin rol llenaba la lista de
cuentas `llave-algo` que nadie sabía qué eran — pasó, y por eso cambió. Lo que
la llave consultó no se pierde: `swpk_accesos` guarda el login como texto.

**Hasta dónde llega:** quien pueda instalar un plugin o entrar a la base de
datos deshace cualquiera de los cuatro. Esto cierra el camino fácil, que es la
pantalla de Usuarios; lo otro ya exige el servidor, y ese día la llave de la API
es el menor de los problemas.

### El conector para Claude Desktop

Claude Desktop no consume una API REST: habla MCP. `conector-mcp/` es el
traductor — un archivo de Node sin dependencias, porque esto se lo tiene que
poder instalar alguien que no programa y `npm install` en la máquina de otro es
donde mueren estas cosas. Expone las siete rutas de lectura como herramientas;
las tres de proceso quedan fuera a propósito, para que una conversación no pueda
lanzar más de 200 peticiones a Kinsta a media pregunta. Su README tiene la
configuración y `node index.mjs --probar` comprueba la conexión antes de tocar
nada.

## Límites de la API de Kinsta

- **120 peticiones por minuto** por empresa. Por eso se usan los endpoints de
  nivel empresa: el inventario completo son 3 llamadas al día, no 2 por sitio.
- `siteLabels` viene en camelCase mientras el resto de la respuesta es
  snake_case.
- Los endpoints de plugins documentan sus filtros **en el cuerpo de un GET**.
  Varias librerías HTTP descartan ese cuerpo sin avisar, así que el panel manda
  los filtros pero no confía en ellos y vuelve a filtrar de su lado.
- El inventario de plugins no es en tiempo real: es una foto que Kinsta refresca
  por su cuenta, y viene con su `last_updated_at`.
- La API está marcada como beta pública.

## Estado

En desarrollo. Funciona el inventario, el cruce de vulnerabilidades, la
comparación diaria, los textos de aviso, la bitácora, el agente con su reporte
firmado y su verificación en dos pasos, el monitor de caídas, los perfiles de
acceso, el registro de entradas, los correos automáticos, la exportación, la
API de consulta y el conector MCP.

Falta el despliegue del agente en los sitios, que no es programar sino entrar a
cada uno. Y para que la API y el conector sirvan de algo desde fuera, falta que
el panel viva en un servidor al que se pueda llegar: hoy corre en una máquina de
la casa, y desde ahí no lo alcanza nadie más.

## Atribución

Los datos de vulnerabilidades provienen de
[Wordfence Intelligence](https://www.wordfence.com/threat-intel/) (Defiant,
Inc.), usados bajo su
[licencia](https://www.wordfence.com/wti-community-edition-terms-and-conditions/),
que exige enlazar el registro de cada vulnerabilidad y reproducir su aviso de
copyright donde se muestren. Los identificadores CVE son de MITRE.

## Licencia

GPL-2.0-or-later, como WordPress.
