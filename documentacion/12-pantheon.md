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

**Pantheon no tiene un endpoint que liste los plugins de un sitio.**

Kinsta sí: con tres llamadas de nivel empresa, el panel se trae los plugins y
los temas de los 108 sitios, con sus versiones, si hay actualización y si la
versión es vulnerable. Todo el diseño del panel cuelga de eso.

Lo que Pantheon ofrece es **Terminus**, una herramienta de línea de comandos:

| Lo que hace | Cómo |
|---|---|
| Listar los sitios de la cuenta | `terminus site:list` |
| Correr WP-CLI **dentro** de un sitio | `terminus wp <sitio>.<entorno> -- plugin list --format=json` |
| Autenticarse | `terminus auth:login --machine-token …` |

O sea: para saber qué plugins tiene un sitio, hay que **entrar al sitio y
preguntárselo a WordPress**. No hay atajo por API.

Y hay un detalle de seguridad que importa más que el resto: **un machine token
de Pantheon no se puede limitar a un proyecto.** Da acceso a todo lo que
alcanza esa cuenta, y a diferencia de la llave de Kinsta —que al menos está
encerrada detrás de un cliente que solo sabe hacer GET— Terminus ejecuta
comandos. Un token guardado en el panel sería una llave con poder de escritura
sobre toda la cuenta de Pantheon.

---

## Las tres salidas, y cuál recomiendo

### A · Terminus desde el servidor del panel

El panel instala Terminus y lo llama con `shell_exec` por cada sitio.

**En contra, y es bastante:**
- Hay que instalar Terminus y Composer en el servidor del panel.
- Hace falta `shell_exec` habilitado. **En Kinsta suele estar deshabilitado**,
  que es donde vive el panel.
- Una llamada por sitio, y cada una levanta un WP-CLI remoto: segundos, no
  milisegundos. Con 100 sitios, la corrida pasa de minutos a horas.
- El token no se puede limitar. Rompe el espíritu de «solo lectura».

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

### C · La API interna de Pantheon

La que usa Terminus por dentro. **Descartada:** no está documentada
públicamente, así que puede cambiar sin aviso y dejar el panel ciego sin que
nadie se entere hasta que un cliente pregunte.

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

### Fase 6 · Opcional: descubrir sitios con Terminus

Solo para la lista de sitios, no para el inventario. Resolvería el punto flojo
de la B —que no descubre sitios— sin meter Terminus en la corrida diaria.

Lo dejo fuera de la primera versión a propósito: trae de vuelta el token sin
límites, y es lo único que la B evita por completo. Si hace falta, que sea una
decisión aparte y consciente.

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

**Fuentes consultadas sobre lo que ofrece Pantheon:**
[Command Directory de Terminus](https://docs.pantheon.io/terminus/commands) ·
[`site:list`](https://docs.pantheon.io/terminus/commands/site-list) ·
[`machine-token:list`](https://docs.pantheon.io/terminus/commands/machine-token-list) ·
[Pantheon en la documentación de Tugboat](https://docs.tugboatqa.com/starter-configs/tutorials/pantheon/), sobre que el token no se puede limitar a un proyecto ·
[Comandos de Terminus para WordPress](https://www.knihter.com/insights/wordpress/pantheon-terminus-commands-essential-wp-cli-shortcuts-for-wordpress-developers/), sobre `terminus wp … -- plugin list`
