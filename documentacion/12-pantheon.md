# 12 · Pantheon

> **Esto es un plan, no código escrito.** Nada de lo que está aquí está
> implementado. Se escribió para decidirlo antes de construirlo.

## Por qué, y por qué ahora

Desde la primera reunión estaba dicho: *«eso después lo vamos a replicar en
Pantheon... y después lo replicamos a otros servidores»* (19:05). Por eso el
panel habla con una interfaz (`SWPK_Fuente`) y no con Kinsta directamente.

Lo que cambió es el **cuándo**: el jefe quiere probar el panel ahora, y el
sitio donde lo quiere probar está en Pantheon.

---

## Lo primero, porque cambia todo el plan

**Pantheon sí tiene API. Lo que no tiene es la respuesta que necesitamos.**

Conviene decirlo en ese orden, porque lo contrario —«Pantheon no tiene
API»— es falso y además hace que la conversación empiece por donde no es. El
problema no es la puerta: es que detrás de la puerta no está el dato.

### Qué hay, exactamente

Pantheon tiene **dos formas** de hablar con él desde fuera:

| | Qué es | Cómo se autentica |
|---|---|---|
| **La API pública** | Una API REST, nueva. Gestiona cuenta, sitios y entornos | Un *Personal Access Token*, con `Authorization: Bearer`. **No acepta los machine tokens antiguos** |
| **Terminus** | Un programa de línea de comandos, no una API. Es lo que usaba todo el mundo antes | Un *machine token*, con `terminus auth:login --machine-token …` |

Y lo que importa de verdad:

> **Ninguna de las dos sabe qué plugins tiene instalado un sitio.**

No es un endpoint que falte y vayan a agregar: es que **Pantheon no mira
dentro de WordPress**. Su API administra el sitio —entornos, despliegues,
copias, dominios— igual que administraría un sitio de Drupal o uno hecho a
mano. Qué plugins tiene y en qué versión es asunto de WordPress, no de la
plataforma.

Kinsta sí lo sabe porque **escanea la instalación de WordPress** y guarda ese
inventario. Por eso con tres llamadas de nivel empresa el panel se trae los
plugins y los temas de los 108 sitios, con sus versiones y si son
vulnerables. Todo el diseño del panel cuelga de eso, y es lo que Pantheon no
ofrece de ninguna manera.

### Entonces, ¿cómo se sabría qué hay dentro de un sitio de Pantheon?

Entrando al sitio y preguntándoselo a WordPress. Terminus sabe hacerlo, y es
la única vía:

```
terminus wp <sitio>.<entorno> -- plugin list --format=json
```

Lo que hace ese comando por debajo es **abrir una conexión al contenedor del
sitio y correr WP-CLI ahí dentro**. Uno por uno, con su login y su conexión
cada vez.

### Y Terminus es un programa, no una dirección web

Esto se explicó mal la primera vez y conviene dejarlo claro, porque cambia lo
que cuesta.

Una API se usa escribiendo una dirección: el panel manda una petición HTTPS y
le contestan. No hace falta instalar nada.

Terminus no es eso. Es **un programa que se instala**, como WP-CLI o como
Composer, y se ejecuta desde la línea de comandos. Para que el panel lo use,
hacen falta dos cosas que no son gratis:

1. **Instalar Terminus en el servidor donde vive el panel.** Con PHP y
   Composer, y manteniéndolo al día como cualquier otra dependencia.
2. **Poder ejecutarlo desde donde el panel lo necesite.**

### Y sobre si Kinsta lo permite: no lo sabemos

Este documento decía, en tres sitios distintos, que «Kinsta no lo permite».
**Eso se escribió sin comprobarlo** y es una impresión general, no un hecho.
Al ir a buscarlo en la documentación de Kinsta, lo que hay es esto:

| Lo que sí está documentado | Dónde |
|---|---|
| Cada sitio corre en su propio contenedor **con su propio crontab**, y se pueden añadir *cron jobs de servidor de verdad* | [Cron jobs](https://kinsta.com/docs/wordpress-hosting/site-management/cron-jobs/) |
| Hay acceso **SSH**, y se nombra a **Composer** y **WP-CLI** como herramientas usables | [Connect to SSH](https://kinsta.com/docs/wordpress-hosting/connect-to-ssh/) |
| La única función de PHP que aparece nombrada como deshabilitada es **`proc_open`** | [Banned & incompatible plugins](https://kinsta.com/docs/wordpress-hosting/wordpress-plugins-themes/wordpress-banned-incompatible-plugins/) |

Y esto **no** está documentado: si `shell_exec`, `exec` o `system` están
bloqueadas; si se puede instalar una herramienta de línea de comandos de un
tercero en el contenedor; y si hay restricción de salida SSH hacia otros
servidores.

Así que la lectura honesta cambia bastante:

- **Lo que casi seguro NO se puede** es que el panel llame a Terminus en
  mitad de una petición web, desde PHP. Aunque la función estuviera
  habilitada, cada llamada tarda segundos y una petición web no es el lugar.
- **Lo que probablemente SÍ se puede** es un *cron job* diario en el
  contenedor que ejecute Terminus y deje el resultado donde el panel lo lea.
  Kinsta documenta que eso existe y que el intervalo mínimo es de cinco
  minutos.

**Para cerrarlo hay que preguntarle a soporte de Kinsta** tres cosas: la lista
de funciones deshabilitadas, si admiten instalar una CLI de terceros en el
contenedor, y si hay restricción de tráfico saliente. Mientras tanto, esto es
una incógnita y no una imposibilidad.

### Lo del token, con el matiz correcto

Ninguno de los dos tokens se puede limitar: **no hay alcances, ni modo de solo
lectura, ni restricción por sitio**. La documentación de la API pública lo
dice con todas sus letras — el token actúa como el usuario, y puede ver y
hacer lo que esa cuenta pueda.

Eso es peor que la llave de Kinsta, que tampoco tiene alcances pero al menos
está encerrada detrás de un cliente que solo sabe hacer GET (`SWPK_Http`). Un
token de Pantheon guardado en el panel sería una llave con poder de escritura
sobre toda la cuenta.

Dos matices más, que son operativos y no de seguridad:

- El **Personal Access Token caduca a los 90 días**. Habría que renovarlo
  cuatro veces al año o el panel se queda ciego sin avisar.
- El **machine token de Terminus no caduca**, y se puede generar uno nuevo
  desde cualquier cuenta con acceso. Así que lo de «si alguien se va de la
  empresa» no es el problema: el problema es lo que el token permite hacer.

### Y un aviso sobre madurez

La API pública es reciente y su propia documentación tiene una página de
**limitaciones conocidas**: algunas llamadas devuelven error 500 con token
personal, y hay fallos de autenticación intermitentes. Tampoco hay un endpoint
documentado para listar los sitios de una cuenta — se llega dando un rodeo por
los *workspaces*. No es algo sobre lo que construir hoy.

---

## Las tres salidas, y cuál recomiendo

### A · Terminus desde el servidor del panel

El panel instala Terminus y lo llama por cada sitio.

**A favor, y es real:** es lo único que puede **descubrir sitios**. Con
`terminus site:list` el panel sabría qué sitios existen en la cuenta aunque no
tengan nada instalado — que es justo lo que se pierde con la opción B.

**En contra, y es bastante:**
- Hay que instalar Terminus y Composer en el servidor del panel, y mantenerlos.
- Hay que poder ejecutarlo desde ahí. En Kinsta **no está documentado** si se
  puede; lo que sí está documentado es que hay cron de contenedor, así que la
  vía realista sería un cron diario y no una llamada desde PHP. Hay que
  preguntárselo a soporte.
- Una llamada por sitio, y cada una abre una conexión y levanta un WP-CLI
  remoto: segundos, no milisegundos. Con 100 sitios, la corrida pasa de
  minutos a horas.
- El token no se puede limitar y permite escribir. Rompe el espíritu de «solo
  lectura» que el panel mantiene con Kinsta.

### B · Que lo reporte el agente ← **la que recomiendo**

El agente ya está instalado en el sitio, ya manda un reporte firmado, y ya
reporta las versiones de WordPress y PHP, los usuarios y las normas. **Que
reporte también los plugins y los temas.**

Con eso, Pantheon no hace falta para nada: el panel aprende todo del sitio
mismo.

**A favor:**
- **No hace falta ninguna credencial nueva**, ni un token con poder sobre toda
  una cuenta.
- **Funciona en cualquier hosting.** No resuelve Pantheon: resuelve Pantheon,
  cPanel, un VPS, SiteGround y lo que venga. Es la respuesta al *«y después lo
  replicamos a otros servidores»* de una sola vez, en lugar de escribir una
  clase por proveedor para siempre.
- Reutiliza el canal firmado que ya existe y ya está probado.
- El dato es **más fresco** que el de Kinsta: Kinsta escanea cuando le toca y
  puede tener una semana; el agente mira el sitio en el momento de reportar.
- No cuesta trabajo extra en el sitio: el agente tiene que estar instalado de
  todas formas, por los usuarios, la verificación en dos pasos y las normas.

**En contra, y hay que decirlo:**
- **No descubre sitios.** Kinsta sabe de un sitio aunque nadie haya instalado
  nada; aquí, un sitio sin agente es un sitio que el panel no sabe que existe.
- **No hay etiquetas.** El grupo (`desarrollo` / `auto_update` / `no_update`)
  en Kinsta sale de las etiquetas del hosting. Aquí habría que ponerlo a mano,
  o que el agente lo traiga de su configuración.
- **Sin agente, no hay nada.** Si alguien lo desactiva, ese sitio se apaga en
  el panel. Eso ya pasa hoy con los usuarios y las normas, y el panel ya lo
  detecta: un agente callado 48 horas sale marcado.

### C · Las dos juntas: Terminus solo para la lista, el agente para el detalle

Vale la pena nombrarla porque resuelve el único punto flojo de la B.

Terminus se usaría **una vez al día y para una sola cosa**: `site:list`, para
saber qué sitios existen en la cuenta. El inventario de cada sitio lo seguiría
reportando el agente. Así el panel sabría que un sitio existe aunque nadie le
haya instalado nada, y lo mostraría como «sin plugin, no sabemos nada de él» —
que es exactamente lo que hoy hace con los sitios de Kinsta sin agente.

**Pero hay que ser claros con lo que esta opción NO ahorra**, porque la
primera versión de este documento la vendía como un añadido ligero y no lo es.
Fer lo vio enseguida: *«si Terminus igual lo tengo que instalar en un
servidor, tampoco es como que sea una alternativa»*. Tiene razón.

Lo que baja es el **coste de funcionamiento**, no el de montaje:

| | Opción A | Opción C |
|---|---|---|
| Instalar Terminus y mantenerlo | Sí | **Sí, igual** |
| Depender de poder ejecutarlo en el servidor | Sí | **Sí, igual** |
| Token sin límites, con permiso de escritura | Sí | **Sí, igual** |
| Llamadas al día | ~100 | 1 |
| Si falla, ¿se cae el panel? | Sí, se queda sin inventario | No, solo no descubre sitios nuevos |

O sea: **el obstáculo de entrada es el mismo**. Si Terminus no se puede
ejecutar donde viva el panel, la C tampoco existe. Lo único que la C evita es
la lentitud y el riesgo de que todo el inventario dependa de ello.

**Mi recomendación sigue siendo dejarla para después**, pero no porque sea
barata de agregar: porque hasta que no esté decidido dónde vive el panel, no
se puede saber si es posible siquiera.

---

## La decisión que está debajo de todo esto

Y aquí está lo que de verdad hay que resolver antes que nada, y no es una
pregunta sobre Pantheon:

> **¿Dónde va a vivir el panel?**

- Si vive en **un servidor nuestro** —un VPS, una máquina que controlemos—
  instalar Terminus y correrlo por cron es trivial, y las opciones A y C se
  abren solas.
- Si vive en **Kinsta**, está la incógnita de arriba y hay que preguntar.
- Si vive donde vive hoy —**la máquina de la casa**— no hay nada que decidir,
  porque de todas formas no se puede llegar a él desde fuera.

La opción B es la única que funciona igual en los tres casos. Esa es su virtud
real, más que ninguna otra: **no depende de una decisión que todavía no está
tomada.**

---

## El plan, si se aprueba la B

### Fase 1 · El agente reporta su inventario

En `SWA_Reporte::armar()`, un bloque nuevo con lo que ya sabe WordPress:

```php
'modulos' => [
    // get_plugins() + get_site_transient('update_plugins')
    [ 'tipo' => 'plugin', 'slug' => …, 'titulo' => …, 'version' => …,
      'version_disponible' => …, 'activo' => …, 'auto_update' => … ],
    …
],
```

Solo en el reporte completo, nunca en el liviano: son decenas de filas.

**Lo que NO trae, y es una buena noticia:** no trae `es_vulnerable`. Kinsta da
un sí/no y el panel ya lo ignora en la práctica, porque quien decide qué es
vulnerable es el cruce con Wordfence, que el panel ya hace por su cuenta. Así
que aquí no se pierde nada.

### Fase 2 · El receptor lo guarda

En `swpk_fotos`, con la misma forma que hoy. Hay un punto a cuidar: hoy el
inventario lo escribe el sincronizador en la corrida y el receptor no toca esa
tabla. Habrá que decidir quién manda cuando llegan los dos — para un sitio de
Pantheon solo llega uno, así que no choca, pero conviene dejarlo explícito.

### Fase 3 · `SWPK_Fuente_Agentes`

Una clase que implementa `SWPK_Fuente` leyendo de lo que reportaron los
agentes, en vez de de una API:

| Método | De dónde sale |
|---|---|
| `nombre()`, `proveedor()` | «Los propios sitios» |
| `sitios()` | `swpk_agentes`, una fila por agente registrado |
| `plugins()`, `temas()` | Lo que reportaron, de `swpk_fotos` |
| `frescura()` | `ultimo_reporte` de cada agente |
| `probar()` | ¿Hay agentes reportando? |

### Fase 4 · La fuente compuesta — y esto es lo que el jefe pidió de verdad

Él no quiere mover los 108 sitios de Kinsta: quiere **probar uno de Pantheon**
mientras lo demás sigue igual. Hoy `swpk_fuente()` devuelve **una** fuente, así
que eso no se puede.

Hace falta `SWPK_Fuente_Compuesta`: tiene varias fuentes dentro y junta sus
resultados. Kinsta trae sus 108, los agentes traen los suyos, y el panel ve
una sola cartera.

Es, además, lo que hace que el panel deje de ser «el panel de Kinsta» y pase a
ser el panel de la agencia.

### Fase 5 · El grupo, y los sitios de otro hosting

Dos cosas pequeñas que sin ellas no funciona:

- Un campo de **grupo** en los ajustes del agente, que viaje en el reporte, y
  que el panel pueda sobrescribir desde la ficha del sitio.
- Que la pantalla diga **de dónde sale cada sitio**. Un sitio que el panel
  conoce por su agente y uno que conoce por Kinsta no saben lo mismo de sí
  mismos, y mezclarlos sin decirlo sería volver a confundir «no lo sé» con
  «está bien».

### Fase 6 · Opcional, y solo si hace falta: la opción C

Terminus una vez al día, solo para `site:list`. Es la salida C de arriba.
Queda fuera de la primera versión a propósito — trae de vuelta el token sin
límites y la dependencia de poder ejecutar programas en el servidor, que es lo
único que la B evita por completo. Si al usarlo resulta que hace falta ver los
sitios sin agente, se agrega encima sin rehacer nada.

---

## Cuánto es

| Fase | Trabajo |
|---|---|
| 1 · El agente reporta módulos | Pequeño. Es `get_plugins()` y poco más |
| 2 · El receptor lo guarda | Pequeño, con cuidado en quién manda |
| 3 · `SWPK_Fuente_Agentes` | Mediano. Es la clase nueva |
| 4 · Fuente compuesta | Mediano, y es el que hay que pensar bien |
| 5 · Grupo y origen en pantalla | Pequeño, repartido en varias vistas |
| 6 · Terminus (opcional) | Mediano, y trae su propio problema |

Las fases 1 a 3 ya dejan un sitio de Pantheon visible en el panel. La 4 es la
que hace falta para que conviva con los 108 de Kinsta.

---

## Lo que hace falta decidir antes de escribir una línea

1. **¿Vamos por la B?** Es la recomendación: no pide credenciales nuevas, y
   resuelve de paso cualquier otro hosting.
2. **¿El sitio de Pantheon del jefe ya puede llevar el agente instalado?** Sin
   eso, la B no arranca. Y es un sitio real, así que la instalación se hace
   como se hace en un sitio en vivo: con su visto bueno.
3. **¿El grupo lo pone el agente o el panel?** Mi propuesta: el agente lo trae
   como sugerencia, el panel manda.
4. **¿Hace falta descubrir sitios sin agente?** Si la respuesta es no, nos
   ahorramos el token de Pantheon entero.

---

## Qué NO hay que hacer

- **No meter Terminus en la corrida diaria.** Una llamada por sitio con WP-CLI
  remoto convierte minutos en horas.
- **No guardar un machine token de Pantheon** mientras haya otra salida: no se
  puede limitar, y a diferencia de la llave de Kinsta, con esa se puede
  escribir.
- **No tratar un sitio sin agente como un sitio sano.** Es la regla de siempre
  y aquí pesa más, porque en Pantheon el agente es la única fuente.

---

**Fuentes, y qué salió de cada una.** Conviene dejarlas: esto se escribió a
partir de documentación que es reciente y cambia, así que dentro de unos meses
habrá que volver a mirarla antes de dar nada por cierto.

| Página | Qué confirma |
|---|---|
| [Guía de la API pública](https://docs.pantheon.io/guides/public-api) | Que existe una API REST pública (Public API v1) |
| [Autenticación](https://docs.pantheon.io/guides/public-api/authentication) | Personal Access Token con `Bearer`; **no** acepta los machine tokens antiguos |
| [Sitios en la API](https://docs.pantheon.io/guides/public-api/sites) | Solo `GET /sites/{siteId}` y búsqueda por nombre. **No hay listado de sitios documentado** |
| [Limitaciones conocidas](https://docs.pantheon.io/guides/public-api/known-limitations) | Errores 500 con token personal y fallos de autenticación intermitentes |
| [Personal Access Tokens](https://docs.pantheon.io/personal-access-tokens) | Caducan a los 90 días; se revocan desde los ajustes personales |
| [Comandos de Terminus](https://docs.pantheon.io/terminus/commands) y [`site:list`](https://docs.pantheon.io/terminus/commands/site-list) | Que Terminus sí puede listar los sitios de la cuenta |
| [Pantheon en la documentación de Tugboat](https://docs.tugboatqa.com/starter-configs/tutorials/pantheon/) | Que el token no se puede limitar a un proyecto |
| [Comandos de Terminus para WordPress](https://www.knihter.com/insights/wordpress/pantheon-terminus-commands-essential-wp-cli-shortcuts-for-wordpress-developers/) | La forma `terminus wp … -- plugin list` |

**Lo que NO se pudo confirmar:** la especificación OpenAPI de la API pública
pesa 224 KB y se leyó en parte. La conclusión de que no hay inventario de
plugins es firme para todo lo leído —guía, limitaciones y la mayor parte de la
especificación— pero no se recorrió endpoint por endpoint. Si alguien quiere
cerrarlo del todo, el sitio es
[`api.pantheon.io/v1/openapi.json`](https://api.pantheon.io/v1/openapi.json).
