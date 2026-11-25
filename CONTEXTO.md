# Contexto del Panel Kinsta — para arrancar otro chat

Este archivo existe para una sola cosa: **que puedas abrir un chat nuevo,
pasarle esto, y que arranque sabiendo lo mismo que sabe el chat de hoy.**

Fecha de corte: **23 de noviembre de 2026, por la noche.** Lo que diga de versiones,
cantidades y pendientes era cierto ese día; lo que diga de reglas y de cómo
está armado el panel no cambia.

## Cómo usar este archivo

- **Si abrís el chat sobre esta misma carpeta** (`/Users/fer/PROYECTOS/PANEL KINSTA`),
  basta con decir: «leé `CONTEXTO.md` y seguimos». Desde ahí tiene los enlaces
  a todo lo demás.
- **Si el chat no tiene la carpeta**, pegá este archivo completo. No trae
  nombres de clientes ni contraseñas, así que se puede pegar en cualquier lado.
- **Lo que este archivo no es:** el manual. El manual son los 14 archivos de
  `documentacion/`, y este archivo dice cuál abrir para qué.

---

## 1. Qué es el panel y dónde vive

El Panel Kinsta es un plugin de WordPress que vigila la cartera de sitios y
**deja evidencia** de lo que vigiló: versiones, vulnerabilidades, caídas,
normas de seguridad, quién tiene acceso a cada sitio y qué se le notificó a
quién. No administra los sitios: los mide.

Son **dos plugins que se hablan**:

| | Qué es | Dónde corre |
|---|---|---|
| `panel-kinsta/` | El panel. Lee la API de Kinsta y el feed de Wordfence, guarda fotos diarias, compara y avisa. | Un solo WordPress: **www.solucionweb.com**, alojado en **Pantheon**. |
| `agente-sitio/` | El agente. Un plugin chiquito en cada sitio del cliente que le reporta al panel lo que la API de Kinsta no ve (usuarios admin, ajustes de seguridad, pedidos). | En cada sitio vigilado. |

Hay una **tercera copia del agente**, metida dentro del tema hijo
`hello-elementor-child` del jefe, para los sitios donde no se quiere instalar
un plugin aparte. Vive en otro repositorio (`soydiazweb/hello-elementor-child`)
y cuatro de sus archivos están a propósito distintos de los del plugin —
`inc/README.md` de ese repositorio dice cuáles y por qué. Donde están los dos,
**gana el plugin**: el tema se calla solo (`if ( defined( 'SWA_VERSION' ) ) return;`).

### Los entornos

- **Local:** panel en `localhost:8882`, sitio de cliente de prueba en
  `localhost:8883`. Las credenciales están en `PRUEBAS.md`, que **no** va al
  repositorio.
- **Producción:** `terminus` contra `solucionweb.live`. Se despliega haciendo
  push a la rama `master` del git de Pantheon y luego
  `terminus env:deploy solucionweb.live --cc`.
- **Los sitios vigilados:** la API de Kinsta los cubre todos. Hoy son **109**,
  de los cuales **13 son del cliente** (etiqueta `no_update`: se miden, no se
  tocan) y **95 tienen el agente**. El número sube cuando entra un sitio nuevo,
  así que no lo tomes como fijo: sale de `wp_swpk_sitios`.

---

## 2. Dónde está escrito cada cosa

### Va al repositorio (es público)

| Archivo | Para qué abrirlo |
|---|---|
| `README.md` | Qué hace el panel, para alguien que llega de fuera. |
| `documentacion/README.md` | El índice del manual, y **las cuatro reglas que no se tocan**. |
| `documentacion/01-como-esta-armado.md` | Las piezas y cómo encajan. El primero que hay que leer. |
| `documentacion/10-decisiones.md` | **El más importante.** Lo que se decidió a propósito y qué se rompe si se cambia. 33 secciones. |
| `documentacion/11-como-trabajar-en-esto.md` | Convenciones de la casa: cómo se nombra, cómo se comenta, cómo se prueba. |
| `documentacion/02` a `09`, `12` a `14` | Base de datos, de dónde salen los datos, el agente, las pantallas, las 73 normas, la API y el MCP, permisos y llaves, avisos y bitácora, Pantheon, qué es cada sitio, cadencia y costos. |
| `CONTEXTO.md` | Este archivo. |

### No va al repositorio (`.gitignore`)

Trae nombres de clientes, transcripciones de reuniones o contraseñas. Está en
la máquina y nada más:

- `REUNION-2026-11-23-lo-que-pidio.md` — **lo último que pidió el jefe**, punto
  por punto, con la cita textual y el minuto de la grabación, agrupado en ocho
  secciones (A a H). Si alguien pregunta «¿qué pidió exactamente?», es este.
- `INFORME-2026-11-25-que-estaba-roto.md` — **la explicación de qué estaba roto
  y cómo quedó**, en nueve capítulos, escrita para leerse de corrido. Es el
  mismo texto del PDF «Panel Kinsta - que estaba roto y como quedo.pdf». Si
  alguien pregunta «¿por qué no funcionaba?», es este. Trae al principio un
  bloque «para arrancar un chat nuevo» y al final la lista de lo que falta.
- `REUNION-2026-11-19-lo-que-pidio.md` — **los 49 pedidos del jefe** de la
  reunión anterior, uno por uno, textuales, cada uno con su estado.
- `REUNION-2026-11-18-panel.md`, `REUNION-2026-11-18-gustavo.md` — las dos
  reuniones anteriores.
- `NORMAS-catalogo.md` — las 73 normas con mediciones reales, por dominio.
- `PLAN.md`, `FASE-1.md` — el alcance comercial.
- `PRUEBAS.md` — guía de pruebas, con las contraseñas de los sitios locales.

---

## 3. Las cuatro reglas que no se tocan

Están explicadas en `documentacion/README.md`, y tres de ellas están además
**por escrito en el documento que se le entregó al jefe**. Si un cambio choca
con una de estas, se para y se pregunta:

1. **El panel nunca escribe en Kinsta.** `SWPK_Http` implementa un solo método,
   `get()`. Buscar `wp_remote_` en todo el repositorio devuelve solo GET, y eso
   es la garantía — la llave de Kinsta no se puede limitar desde Kinsta.
2. **El sitio del cliente no abre ninguna puerta a internet.** El sitio llama
   al panel, nunca al revés. Cuando hizo falta que el panel le pidiera algo a
   un sitio, se resolvió con una cola que el sitio consulta.
3. **No se dice «está bien» cuando lo que pasa es «no lo sé».** Un sitio sin
   datos no se pinta como sano. Es la razón de ser de medio panel y la primera
   cosa que se rompe al «simplificar».
4. **A los sitios del cliente se les mide, no se les toca.** Los `no_update`
   los administra el cliente.

---

## 4. Cómo se trabaja en esto

Reglas de Fer, que valen igual en un chat nuevo:

- **El código y los comentarios, en español**, y los comentarios dicen **por
  qué**, no qué. Un comentario que explica la línea de abajo sobra; uno que
  explica por qué no se hizo de la forma obvia, vale.
- **Subir sin preguntar.** Cuando el trabajo está listo, commit y push directo;
  no se pide permiso de trámite. **Desplegar a producción y tocar sitios en
  vivo sí necesita un sí explícito.**
- **Nada de scroll infinito.** Listas paginadas y secciones con título.
- **La verificación en dos pasos del agente sigue apagada** en todos los
  sitios hasta que Fer lo indique. No encenderla, ni en pruebas, ni volver a
  ofrecerlo.
- **No mentir.** Si no se sabe, se dice «no lo sé». No decir «ya está» sin
  haberlo visto.
- **Revisar el `.gitignore` antes de cada commit.** El repositorio es público y
  dos veces se escaparon nombres de clientes dentro de un texto.
- **La pantalla del jefe es de 13 pulgadas.** Todo lo de maquetado se comprueba
  a 1280 px de ancho, no en la pantalla grande.
- **Al jefe se le habla de tú**, no de vos.

---

## 5. Estado al 25 de noviembre de 2026

| | Versión | Dónde está |
|---|---|---|
| Panel Kinsta | **0.6.3** | Desplegado en producción y verificado ahí mismo. |
| Agente (plugin) | **0.6.4** | En 106 de los 107 sitios que lo tienen. |
| Agente (dentro del tema) | **0.6.4-tema** | En el PR, esperando al jefe. |
| Tema hijo | **2.6.1** | En el PR. |

### Lo del 24 y 25: los colores y la letra (0.6.1 → 0.6.3)

El jefe es daltónico y lo dijo tres veces en la grabación: «ponerme amarillo,
rojo, azul, **colores totalmente contrarios**». Costó tres intentos.

- **0.6.1** — primera paleta. Arreglaba el verde/rojo del que se quejaba y
  creaba un par nuevo: naranja y mostaza a 6,8 de distancia. Fallida.
- **0.6.2** — tonos pálidos con la palabra dentro de la insignia, y una franja
  de color a la izquierda. Los fondos quedaban a 1,7 de distancia:
  prácticamente idénticos. Fallida, y las franjas se quitaron porque Fer las
  rechazó de plano. **Un pastel es lo contrario de un color contrario.**
- **0.6.3** — la definitiva. Seis colores sacados de una búsqueda con tres
  condiciones a la vez (separados en los tres daltonismos, separados en escala
  de grises, y cada uno legible con su propio color de texto). El par más
  parecido quedó a 100; por debajo de 45 se confunden. Y de paso las insignias
  pasaron a 12 px / peso 700 y se arreglaron dos grises de texto que llevaban
  **desde siempre** por debajo del mínimo legible: 3,24:1 y 4,20:1, cuando el
  mínimo es 4,5:1.

La paleta, con hex y contrastes, está en
`panel-kinsta/includes/class-riesgo.php` y explicada en
`INFORME-2026-11-25-que-estaba-roto.md`, capítulo 4.

**Lección que se repitió dos veces:** el panel numera su hoja de estilos con
`SWPK_VERSION`, así que **un cambio de CSS sin subir la versión del plugin es
un cambio que nadie ve**. Se perdió un rato buscando un fallo que no existía.

### Lo de la reunión del 23 por la noche (0.6.0)

Seis cosas, y las dos primeras son las que hacían que el panel no sirviera
para lo que se construyó. El razonamiento completo está en el commit; aquí
solo qué cambió y qué quedó pendiente.

1. **El panel no había mandado un solo correo. Nunca.** Dos causas a la vez:
   `swpk_modo_envio` no existía como opción, así que caía en `MODO_PRUEBA` de
   fábrica; y `correo_soporte` estaba vacío. La bitácora entera eran siete
   filas, ninguna enviada. Y una tercera que además mentía: el aviso de caída
   se iba callado si no había destinatario, sin dejar rastro — por eso el
   Historial enseñaba la caída y ningún aviso. **Ya está encendido y
   comprobado: llegó un correo de verdad a una bandeja de verdad.**
2. **«No puedo confiar en este panel.»** Trataba la etiqueta del hosting y la
   actualización automática como el mismo dato. El dato correcto estaba
   guardado desde el primer día en cada foto y no se enseñaba. Ahora se mide
   y se enseña aparte, en Sitios y en la ficha, y el grupo se puede corregir
   a mano cuando la etiqueta del hosting está mal.
3. **El monitor no estaba midiendo nada.** 106 de 109 sitios en 403 porque
   Cloudflare bloquea las IP de salida de Pantheon. Estado nuevo `ciego`: no
   cuenta como caída ni avisa. **Esto no arregla la medición, solo deja de
   mentir sobre ella** — ver el punto 6.
4. **Paleta para daltónicos.** «Al día» pasa a azul. Los seis colores salen
   de una búsqueda medida, no de elegirlos a ojo: el primer intento arregló
   el par del que se quejaba y rompió otro sin que nadie lo notara hasta
   medirlo. Las tres condiciones están en el comentario de
   `SWPK_Riesgo::colores()`.
5. **Sitios que ya no existen.** A las cinco sincronizaciones sin aparecer se
   dan de baja, dejan de contar y se avisa. No se borra la fila: la evidencia
   sobrevive al sitio.
6. **El correo que sabe que ya lo mandó antes.** `SWPK_Insistencia`. La firma
   de un estado lleva el slug Y las dos versiones, porque «el mismo plugin en
   otra versión» sí es un cambio y contar pendientes no lo detecta.

Tablas a la versión 14: `grupo_a_mano`, `ausencias`, `de_baja`, `de_baja_en`.

### Lo de antes (0.5.1 y anteriores)

1. **Los 49 pedidos del jefe de la reunión del 19.** Hechos todos los de cómo
   se ve; los de funcionalidad quedaron separados porque son decisiones de Fer
   (ver el punto 6).
2. **El contacto del cliente se llena solo** con los administradores que
   reporta el agente, quitando los dominios de la casa, y **siempre se puede
   escribir encima**. Si lo que se guarda es la misma lista que trae el agente,
   se guarda vacío para que el sitio siga al agente.
3. **Sitios tardaba porque hacía 5.000 consultas.** Dos patrones N+1
   (`SWPK_Wordfence::para_slug()` y la fecha de la última foto en
   `SWPK_Riesgo`) resueltos con mapas estáticos por petición — no cachés, para
   que no haya nada que invalidar. **En producción: de 1.957 consultas y
   1.580 ms a 208 y 401 ms.** La distribución de niveles no cambió.
4. **El agente manda su primer reporte al registrarse**, para no esperar al
   cron del día siguiente.
5. **El ajuste del agente dentro del tema hijo**: pestañas independientes y un
   interruptor para apagar la parte del panel. Está en el PR #1 de
   `soydiazweb/hello-elementor-child`, con el README y el `readme.txt`
   actualizados y la versión alineada.

---

## 6. Lo que queda pendiente

### Esperando a otra persona

- **El PR del tema hijo** espera la aprobación del jefe.

### Esperando una decisión de Fer (son funcionalidad, no maquetado)

Están en `REUNION-2026-11-19-lo-que-pidio.md`, sección K:

- Un interruptor por aviso en Correos, para encender o apagar cada correo.
- El flujo completo que pidió el jefe: analizar un sitio → mandarle correo a la
  asesora → PDF imprimible → decir «hazlo» → reporte de cómo estaba y cómo
  quedó.
- Poder agregar más correos de notificación por sitio.
- «Arreglar sólo esa parte», con más granularidad que la norma.

### El agente en los sitios del cliente (23 de noviembre, de noche)

El jefe lo pidió en la reunión —«¿te vas a instalarle el plugin? ¿Tienes el
SSH?» (09:09)— y Fer confirmó que era una orden. **12 de los 13 `no_update`
quedaron con el agente**, registrados y reportando. El panel ya calcula solo
el contacto del cliente en 10 de ellos, que antes había que escribir a mano.

Cómo se hizo, por si hay que repetirlo: **los datos de SSH salen de la propia
API de Kinsta**, en el `ssh_connection` de cada entorno, y el usuario es el
nombre del sitio — el `web_root` lo dice. La llave de la Mac de Fer ya está
autorizada en la cuenta, así que no hace falta contraseña ni entrar a MyKinsta
a copiar nada sitio por sitio.

Y dos cosas que destapó hacerlo:

- **Uno de los trece se quedó fuera, y por una razón que importa.** Corre
  **PHP 7.4.33** y el agente pide 8.1, así que no se puede instalar. Se le
  quitó la carpeta y el sitio quedó como estaba. Pero al diagnosticarlo salió
  algo peor: su `wp-config.php` termina en basura de una edición mal hecha, un
  fragmento suelto después del `require` de `wp-settings.php`. Hoy solo suelta
  un *warning* en cada carga porque PHP 7.4 lo tolera. **En PHP 8 eso es error
  fatal.** O sea que si alguien le sube el PHP para poder instalarle el
  agente, le tira el sitio. Hay que arreglarlo en ese orden: primero el
  `wp-config.php`, después el PHP, y con permiso del cliente. **Cuál es está
  en `REUNION-2026-11-23-lo-que-pidio.md`**, que no va al repositorio.
- **El agente tenía un fallo que solo se ve en tiendas viejas** (0.6.4 lo
  arregla): comprobaba que la tabla `wc_orders` existiera y daba por hecho el
  esquema de dentro. Dos tiendas la tenían sin la columna `date_paid_gmt`, y
  la consulta moría con «Unknown column» en medio del reporte.

### Lo que dejó abierto la reunión del 23

- **El monitor sigue sin poder medir 106 sitios.** Cloudflare bloquea las IP
  de salida de Pantheon; comprobado desde el servidor (403 con la página de
  bloqueo) y desde fuera (200). Las salidas son dos: que el hosting deje
  pasar a Pantheon, o un servicio externo de monitoreo. **Es decisión de
  Fer**, y hasta que se decida el panel dice honestamente «no se puede
  medir» en vez de inventarse una caída.
- **El semanal a las asesoras ya sale**, con el primer envío el lunes 30 de
  noviembre a las 07:00. El Equipo son cuatro: dos asesoras en Ventas, y
  Luisa y Fer en Dirección, que reciben copia de todo.
- **Las asesoras están asignadas SOLO a los sitios `no_update`, y eso es
  deliberado.** Es la parte del pedido que entendí al revés la primera vez. El
  jefe lo dijo claro en el minuto 11:14: «no auto-update [...] **esto es lo
  que debemos de notificar a las asesoras**». La razón, que da él mismo: los
  `auto_update` son nuestros, así que un pendiente ahí es culpa nuestra y no
  hay cliente al que perseguir — «yo no puedo venir al cliente y decir: lo
  que pasa que usted no actualizó». Los `no_update` son del cliente, y ahí sí
  hace falta que la asesora lo llame. **Los otros 96 sitios quedan sin asesora
  a propósito**: si alguien les asigna una, empezarán a generar correo semanal
  que nadie pidió.
- **El reparto de los 13 entre las dos asesoras es arbitrario**, porque el
  hosting no guarda quién atiende cada cuenta. Se corrige en Sitios, columna
  Asesora.
- **Dos cosas que aparecieron al crear el equipo y conviene no olvidar:**
  - `tramirez@` ya tenía cuenta en el WordPress con el rol `ws_advisor` del
    sitio. Se le **añadió** el rol del panel con `add_role()` en vez de
    reemplazarlo: `wp_insert_user` con `role` le habría quitado el que tenía.
    Cualquier alta futura de alguien que ya exista tiene el mismo cuidado.
  - **`jdiaz@` es el correo de la cuenta `swadmin`**, el administrador
    compartido de WordPress, no una cuenta personal del jefe. Por eso no se
    le pudo crear cuenta propia (el correo ya está tomado) y por eso **no se
    le tocó**: meterle un rol del panel lo volvería «un miembro del equipo
    llamado swadmin». No hace falta: ya recibe todo por `correo_soporte` y
    `correo_ventas`. Si algún día quiere cuenta personal, primero hay que
    cambiarle el correo a `swadmin`, y eso es decisión suya.
- **El mensual a clientes está encendido y sale el 1 de diciembre**, a los
  administradores de cada sitio nuestro con movimientos en noviembre. Fer
  decidió encenderlo entero sabiendo que el primer lote va a clientes
  reales; conviene leer unos cuantos en Correos → Bitácora antes de esa
  fecha.
- Del video quedaron sin tocar, por ser maquetado menor: la «i» de
  información que no parece una «i», la dirección del reporte que va debajo
  en vez de al lado, las fechas del reporte al cliente que salen todas
  iguales, el «Analizar este sitio ahora» sin señal de que está trabajando, y
  el «ver y editar» de Correos que manda a otra pantalla en vez de abrir una
  ventana.

### Deudas técnicas, sin urgencia

- **El agente 0.6.4 está repartido: 106 de 107 sitios.** Falta uno solo, y no
  por el agente: su SSH acepta la conexión TCP y luego la corta en el saludo
  (`kex_exchange_identification: Connection reset by peer`), con el puerto
  abierto y el sitio sin bloquear según la API. Probado cuatro veces con
  pausas. Hay que mirarlo en el panel del hosting —puede que tenga el acceso
  SSH apagado— y volver a correr el reparto solo para él. **Cuál es está en
  `REUNION-2026-11-23-lo-que-pidio.md`.**
- **El reparto en masa, por si hace falta otra vez:** se saca la lista de
  sitios con su `ssh_connection` de la API de Kinsta, se sube el `.tgz` del
  plugin a `/tmp` de cada sitio, se extrae en `wp-content/plugins` y se
  comprueba leyendo `SWA_VERSION`. Dos cosas que importan: una pausa corta
  entre sitios, porque sin ella el hosting corta conexiones por ráfaga; y un
  `ConnectTimeout` de 25 s y no de 8, porque con 8 fallaron seis sitios que
  estaban perfectamente bien.
- Las 8 normas de seguridad nuevas del jefe siguen mostrándose como «Por
  construir».
- Las 5 decisiones del PDF «Normas de seguridad - el plan».
- Unas 131 filas de eventos duplicadas del 12 de noviembre.
- `swpk_fotos` merece otro nombre.
- En producción, `home` y `siteurl` siguen en `http://`, no en `https://`.

---

## 7. Qué significa «verificado»

Esto está en `documentacion/10-decisiones.md` §33 y es la lección más cara de
las últimas semanas. **«Verificado» quiere decir dos cosas distintas y no son
intercambiables:**

- **«Que no reviente»** — pedir el HTML de la pantalla y buscar `Fatal`,
  `Warning`, `Notice`, `Deprecated`, con parámetros reales. Barato, y se puede
  hacer sobre todas las pantallas.
- **«Que se vea»** — medirlo en el navegador. Y **leer `window.innerWidth`
  antes de creerle a cualquier medida**: dos hallazgos de desborde resultaron
  ser el navegador colapsado a 253 px.

Las dos cosas se dicen por separado. Un error fatal pasó tres barridos porque
ocurría **después** de que `<div class="wrap">` ya se había abierto: el HTML
parecía entero.

Y la otra lección, de tres veces en que Fer tenía razón y yo dije «ya está»:
**cuando ella dice que algo no aparece, aparece en pantalla es la prueba, no el
código.** Las tres veces la causa era mía. Una de ellas fue un `usort` que
empujó los botones a la página 3 del paginador.

---

## 8. Lo que solo está en los mensajes de commit

Hay que decirlo: **una buena parte del razonamiento de estas semanas no está en
ningún `.md`, está en los mensajes de commit.** Están escritos para leerse —
`git log` es parte de la documentación de este proyecto. Si hace falta entender
por qué algo está como está y los archivos de `documentacion/` no lo dicen:

```bash
git log --format='%h %ad%n%B' --date=short
```

Los que más vale leer:

| Commit | De qué habla |
|---|---|
| `1606a47` | Las dos causas de que el panel no mandara correos, y la tercera que además mentía. Y por qué la etiqueta del hosting no dice si un sitio se actualiza solo. |
| el de la paleta | Cómo una paleta «arreglada» rompió otro par de colores, y por qué ahora se elige midiendo. |
| `7a65097` | Las 5.000 consultas, y por qué la solución es un mapa por petición y no una caché. |
| `a08c10a` | Por qué el contacto escrito a mano le gana al que reporta el agente. |
| `bdbfa34` | «No tiene agente» y «el agente no ha mandado la lista» no son lo mismo: cuatro motivos distintos. |
| `fa58624` | Una medida de maquetado sin el ancho al lado no vale nada. |
| `b86262f` | Leer el HTML no es ver la pantalla. |
| `3b2f382` | El botón de arreglar: la vez que insistí tres veces en que estaba y la causa era mía. |
| `9e3fda6` | El botón azul que no se leía, por especificidad de CSS. Contraste 1,60:1. |
| `da6a2aa` | La mudanza del agente al tema, y las dos minas que destapó. |
| `50206dd` | «La causa que dejé escrita era falsa». |
