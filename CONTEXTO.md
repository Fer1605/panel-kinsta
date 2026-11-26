# Contexto del Panel Kinsta — para arrancar otro chat

Este archivo existe para una sola cosa: **que puedas abrir un chat nuevo,
pasarle esto, y que arranque sabiendo lo mismo que sabe el chat de hoy.**

Fecha de corte: **26 de noviembre de 2026.** Lo que diga de versiones,
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

- `analisis-reuniones/` — **el análisis de la grabación del 26 de noviembre**,
  el más reciente: `Panel Kinsta - lo que pidio el 26 de noviembre.pdf` (44
  páginas) y su fuente en HTML, con las capturas. Trae, por cada pedido, la
  cita textual con el minuto, qué significa, qué se propone y la maqueta. Más
  las cuatro contradicciones de datos comprobadas contra la base, el panel que
  el jefe enseñó como modelo (su «Panel DMM») y un plan de 18 cambios en orden.
  Al lado va `Panel Kinsta - respuesta a tus correcciones.pdf` (34 páginas), que
  contesta las ocho correcciones de Fer a ese plan: el plugin de auto-update que
  recordaba —es **PRO Elements**, en 81 de los 83 sitios de auto-update, con su
  propio actualizador contra GitHub—, por qué el verde es el peor color que
  podemos elegir, las asesoras por sitio, el correo de prueba, y el diseño
  completo del registro de actividad dentro del agente (tabla, retención, cómo
  se lee desde el panel sin abrirle una puerta al sitio, y la pega del child
  theme: un tema no tiene activación, así que la tabla se crea por versión).
  **No va al repositorio**: las capturas llevan nombres y correos de clientes.
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
| Panel Kinsta | **0.9.5** | Desplegado en producción y verificado ahí mismo. |
| Agente (plugin) | **0.6.4** | En 106 de los 107 sitios que lo tienen. |
| Agente (dentro del tema) | **0.6.4-tema** | En el PR, esperando al jefe. |
| Tema hijo | **2.6.1** | En el PR. |

### Lo del 25 y 26 (0.6.3 → 0.9.5)

Empezó por un ajuste del correo que pidió el jefe y acabó destapando cinco
cosas que no funcionaban y que no se veían desde ninguna pantalla.

- **0.7.0 · el correo y el editor.** La tabla del diario iba sin encabezados y
  ordenada por hora, que es el único orden que no le sirve a nadie: el mismo
  sitio salía en la fila 3, en la 40 y en la 112. Ahora va por sitio, con lo
  que no es rutina primero, y con un recuento por tipo arriba. Logo de la casa
  en banda blanca —es azul, sobre la franja azul desaparecía— y en PNG, porque
  Gmail y Outlook no dibujan SVG. Botón al panel en los seis avisos internos.
  Y el editor de plantillas se abre en una ventana: «levantalo en un modal, no
  me mandes a pantallitas de pantallitas» (17:11).

  En el camino salieron **cuatro avisos que no salían**: el mensual a clientes
  **nunca estuvo programado** —`monthly` no es de WordPress, la mete un filtro
  que al activar el plugin aún no está puesto, y `wp_schedule_event()` devuelve
  `false` callado, el mismo fallo del sondeo de cinco minutos—; el aviso de
  caída salía una sola vez y nunca más; un agente callado no avisaba a nadie; y
  la revisión diaria se moría a medio camino sin dejar rastro, porque era la
  única tarea larga que no volvía a pedir tiempo.

- **0.8.0 · el cortafuegos, bien medido.** Ver «Lo que dejó abierto la reunión
  del 23», más abajo. Y dos cosas más: un sitio que el hosting ya no devuelve
  deja de contarse como caída —el panel se lo apuntaba en `ausencias` y acto
  seguido iba a tocarle la puerta al dominio borrado—, y las normas pueden
  quedar **en pausa**: se siguen midiendo, pero lo que no las cumple no cuenta
  como incumplimiento. Es para la verificación en dos pasos, que está apagada
  a propósito; contándola como falla, el 100 % era inalcanzable por definición
  y 83 filas rojas tapaban las que sí hay que arreglar.

  El correo diario pasó a decir **cómo está la cartera hoy** y no solo qué
  cambió: un sitio puede llevar doce días caído sin generar un movimiento
  nuevo, y entonces el correo de hoy no lo nombraba.

- **0.9.0 · un interruptor por aviso.** Lo forzó que el mensual a clientes no
  está autorizado: el interruptor general apaga todo, y quitarle el horario no
  sirve porque el panel lo vuelve a poner solo. Es además el H6 del 19.

- **0.9.1 · la sección F, cerrada.** Las siete de maquetado del 23.

- **0.9.2 · la primera falsa alarma de caída.** Un sitio que responde en 90 ms
  salió como caído y, una hora después, como «estuvo caído 1h 1min». Dos
  fallos a la vez: el aviso salía con dos intentos separados por ocho
  segundos —que no distinguen una caída de un tropiezo— y ahora son cuatro
  repartidos en minuto y medio; y la duración era una resta de relojes
  disfrazada de medición, porque el monitor mira UNA VEZ POR HORA y lo único
  que sabe son las dos revisiones. Ahora dice las dos horas y que lo de en
  medio no es lo que estuvo caído.

- **0.9.3 a 0.9.5 · el repaso de contraste.** La píldora de «Sale» salía con
  el texto del mismo azul que su fondo —1,0 de contraste, invisible— por la
  regla de `panel.css` que pinta los enlaces y que YA tenía un comentario
  avisando de esto. Tercera vez. Midiendo las dos hojas enteras salieron
  cuatro más que nadie había visto, y la peor eran **los encabezados de todas
  las tablas**, a 2,77:1.

  Y dos errores por el camino, que es lo que hay que recordar: puse la regla
  blanca encima de la gris —pesan igual, gana la de abajo— y medí el azul de
  la marca solo contra blanco, cuando ese azul también es texto y casi nunca
  cae sobre blanco. Las dos se vieron midiendo el color calculado EN EL
  NAVEGADOR, con la hoja ya desplegada. **Un cambio de contraste no está hecho
  hasta que se mide donde se ve, no donde se escribe.**

Tablas a la versión 16: `entornos` en sitios, `aplazada` y `aplazada_porque`
en normas.

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

### La zona horaria — urgente, y es decisión de Fer

**El WordPress del panel está en UTC y Guatemala es UTC−6, así que todos los
avisos programados salen seis horas antes de la hora que dicen.** El diario de
las 7:30 sale a la 1:30 de la mañana, y **el semanal a las asesoras del lunes
30 les llega a la 1:00 a.m.**

El panel está escrito dando por hecho lo contrario —`SWPK_Frescura::cuando()`
usa `wp_timezone()`, y su comentario dice «pasó justo al poner la zona de
Guatemala»—, así que la zona se puso alguna vez y hoy no está.

No se tocó porque la zona horaria es de **todo el WordPress de
solucionweb.com**, no solo del panel: entradas, formularios, Elementor. Es el
sitio de la empresa.

Cuando se decida son dos pasos, y el segundo no se puede saltar:

1. Poner `America/Guatemala` en Ajustes → Generales.
2. **Desprogramar y volver a programar las tareas.** Guardan un instante
   absoluto y no se recalculan solas.

El detalle, con la tabla de a qué hora sale hoy cada aviso, está en
`INFORME-2026-11-25b-lo-que-encontre.md`.

### Esperando una decisión de Fer (son funcionalidad, no maquetado)

Están en `REUNION-2026-11-19-lo-que-pidio.md`, sección K:

- El flujo completo que pidió el jefe: analizar un sitio → mandarle correo a la
  asesora → PDF imprimible → decir «hazlo» → reporte de cómo estaba y cómo
  quedó.
- Poder agregar más correos de notificación por sitio.
- «Arreglar sólo esa parte», con más granularidad que la norma.
- **Archivos modificados o maliciosos** (reunión del 18, 2.9): «aquí dice que
  hay tres archivos maliciosos». El agente no mira integridad de archivos.

El **interruptor por aviso** salió de esta lista el 26: se hizo porque el
mensual a clientes no está autorizado y no había forma de parar solo a ese.

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

- **El cortafuegos: esto estaba mal contado, y el 26 se midió bien.** No es
  que Cloudflare bloquee a Pantheon. Contra los MISMOS catorce sitios, con
  minutos de diferencia: saliendo por `34.46.111.53`, **403 en los catorce**;
  saliendo por `35.239.102.121`, **200 en los catorce**, repetido dos veces.
  **Una de las dos IP de salida está en lista negra y la otra no**, y Pantheon
  documenta que las suyas son dinámicas y que no publica rangos. Contadas ocho
  veces, salimos por la buena **siete de cada ocho**.

  Lo caro no era el bloqueo: **la corrida ciega pisaba la buena.** La sonda
  ponía 24 normas en «sin dato» en los 107 sitios — unas 2.500 mediciones
  tiradas por una moneda al aire. Arreglado en 0.8.0 y sin depender de nadie:
  si más del 60 % vuelve bloqueado, la corrida habla de nosotros, no se
  escribe nada encima, se guarda por qué IP salió y se reintenta a la vuelta
  siguiente.

  El mensaje para soporte de Kinsta, la prueba de dos minutos que se puede
  hacer antes en MyKinsta (Bot protection → Exceptions → Allow por user agent
  `PanelKinsta`) y las tres salidas si dicen que no están en
  `KINSTA-pedido-de-desbloqueo.md`, que no va al repositorio.
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
| `0a99e0d`, `9430cc8`, `6fa3ee7` | La misma trampa por tercera vez, y los dos errores al arreglarla: dos reglas del mismo peso (gana la de abajo) y un color de marca medido contra un solo fondo. |
| `504bf0e` | Por qué un tropiezo de veinte segundos no es una caída, y por qué «estuvo caído 1h 1min» era una resta de relojes. |
| `da6a2aa` | La mudanza del agente al tema, y las dos minas que destapó. |
| `50206dd` | «La causa que dejé escrita era falsa». |
