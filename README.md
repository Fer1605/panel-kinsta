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
    ├── class-monitor.php         sondeo de disponibilidad cada 5 minutos
    ├── class-receptor.php        recibe los reportes firmados de los agentes
    ├── class-usuarios.php        línea base de usuarios y alerta de admin nuevo
    ├── class-bitacora.php        registro de notificaciones
    ├── class-textos.php          los avisos, redactados por audiencia
    ├── kinsta/
    │   ├── interface-fuente.php  el contrato
    │   ├── class-http.php        cliente HTTP — SOLO GET
    │   ├── class-fuente-api.php  la API real
    │   └── class-fuente-demo.php datos de prueba
    └── admin/                    el panel
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
firmado y su verificación en dos pasos, y el monitor de caídas. Falta el envío
automático de los correos periódicos, los perfiles de acceso al panel y la
exportación.

## Atribución

Los datos de vulnerabilidades provienen de
[Wordfence Intelligence](https://www.wordfence.com/threat-intel/) (Defiant,
Inc.), usados bajo su
[licencia](https://www.wordfence.com/wti-community-edition-terms-and-conditions/),
que exige enlazar el registro de cada vulnerabilidad y reproducir su aviso de
copyright donde se muestren. Los identificadores CVE son de MITRE.

## Licencia

GPL-2.0-or-later, como WordPress.
