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
| `documentacion/16-estilo-de-las-pantallas.md` | **Para seguir optimizando pantallas.** Cómo se ve y cómo se lee el panel, con el motivo de cada regla, y la lista de lo que falta: Vulnerabilidades primero, luego Caídas. |
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

## 5. Estado al 27 de noviembre de 2026

| | Versión | Dónde está |
|---|---|---|
| Panel Kinsta | **0.19.5** | En el repositorio y comprobado en local a 1280 px. **La 0.18.1 es la que está en producción**: la 0.19.x todavía no se ha desplegado. |
| Agente (plugin) | **0.7.0** | 0.6.4 está en 106 de 107. La 0.7.0 trae el registro de actividad y **no está repartida**. |
| Agente (dentro del tema) | **0.7.0-tema** | La rama unida del jefe sigue en 0.6.4-tema con código de 0.6.3. La corrección + el registro están en una rama lista, **sin PR abierto**. |
| Tema hijo | **2.6.1** | En el PR. |

### Lo del 27 por la tarde: Vulnerabilidades dejó de ser un muro (0.18.1 → 0.19.0)

**La queja, textual:** «sigue saturado de información y esto mi jefe no va a
decir que hicimos nada».

**Qué estaba mal.** La pantalla de Vulnerabilidades era una tabla de cinco
columnas y la del medio llevaba **cuatro párrafos** por renglón: qué pasa, qué
permite la falla, quién puede aprovecharla y qué hay que hacer. Con
veinticinco renglones por página son cien párrafos en un portátil de 13
pulgadas. Y encima no decía lo único por lo que se abre: **qué hacer**. Eso ya
lo sabía decir `SWPK_QueHacer` desde esa misma mañana, pero solo lo usaba la
ficha de un sitio.

**Qué se hizo.** Lo mismo que se hizo en la ficha, con las mismas clases: cada
hallazgo es ahora una **línea que se despliega**. La línea cerrada dice sitio,
módulo y versiones, gravedad y parche, y qué hacer con su franja de color de
quién se mueve. El porqué vive dentro, en dos pasos numerados. Nada abierto al
entrar.

Además: cuatro tarjetas pulsables arriba (con falla conocida · solo
actualización · se arreglan actualizando · sin arreglo todavía) en lugar de las
cuatro pastillas de gravedad, que repetían el desplegable de Gravedad; dos
filtros nuevos —«Qué hay pendiente» y «Qué hacer»— y uno retirado; y los
**pendientes de un sitio** con filtros y páginas, de quince en quince, que
antes eran los 23 módulos seguidos.

**Lo que cambió fuera de esas dos pantallas:**

- `SWPK_QueHacer::para()` devuelve ahora `caso`, una clave estable por cada una
  de las siete situaciones, y `SWPK_QueHacer::casos()` da sus rótulos. Hacía
  falta porque el titular lleva la versión dentro y no se puede agrupar por él.
- El transient de la pantalla pasa a `swpk_hallazgos3_` (cambió la forma de lo
  guardado), y el `DELETE` de `SWPK_Estado::olvidar()` ya no depende de que el
  nombre acabe en guion bajo.
- La barra de filtros de **todo el panel** se alinea por abajo, y por debajo de
  1500 px cada desplegable mide 155 px como mínimo y la barra se parte en dos
  renglones. Con seis filtros a 1280 px se quedaban en 124 px y «Todos los
  sitios» y «Todos los módulos» se recortaban los dos a «Todos los …». De paso
  arregla lo mismo en Sitios, que tiene ocho.

**Qué se comprobó.** Que no reviente: las dos pantallas y sus filtros pedidos
con parámetros reales, sin `Fatal`, `Warning`, `Notice` ni `Deprecated`, con
los valores inventados cayendo al «sin filtro». Que se vea: en el navegador con
`window.innerWidth` en 1280, sin desborde horizontal, el renglón bajó de 82 px
a 69 px y los filtros se leen enteros.

**Segunda pasada, la misma tarde: «mejor, pero todavía sigue horrible».**

- El aviso de «lo que estás viendo no es de hoy» pasa de cinco párrafos, una
  lista con viñetas y dos bloques de código a **una línea** con su burbuja «i»
  y la acción al final. La receta de WP-Cron y Plesk se borró: es una
  instrucción de instalación y salía todos los días en cuatro pantallas. Toca
  también Resumen, Sitios y la ficha de un sitio.
- Las cuatro tarjetas de Vulnerabilidades quedan en **una filita**, con el
  número y el rótulo a la par. Las de la ficha de un sitio se quedan como
  están: ahí funcionan.
- Las dos últimas columnas de cada fila pasan a **ancho fijo**, para que el
  hueco caiga entre el nombre del sitio y las etiquetas y no en la esquina
  derecha.

Con eso, la lista empieza en el píxel 574 en vez de pasado el 1.100: la
primera pantalla del portátil ya enseña hallazgos.

**Tercera pasada, y una lección que costó una corrida.** El aviso de una línea
salió con un enlace al final —«Revisar los sitios ahora»— y Fer lo pulsó
creyendo que llevaba a la pantalla de Sitios. Arrancó una corrida de 108
sitios contra la API de Kinsta desde el panel local; se paró a mano con
`SWPK_Trabajo::olvidar()` cuando iba por 93. **El enlace se quitó**: dónde
está el botón se dice ahora dentro de la burbuja. La regla quedó escrita en
§2 del estilo de las pantallas: nada que tarde minutos o le pegue al hosting
se dispara desde un aviso.

Y la otra, que ya estaba escrita en la hoja de estilos y se volvió a romper:
**ningún aviso lleva franja de color en la orilla izquierda.** El aviso nuevo
salió con una. Se quitó, y de paso las tres que quedaban sueltas —el reparto
del trabajo de un sitio, la nota de «no se pudo medir» y la celda de consejo
de los apagados—. Y la de «quién se mueve» en cada fila tampoco se queda como
franja: pasa a ser un **punto de color** delante de la frase —«cambiémoslas a
no líneas, sino a un círculo o algo que los identifique con ese color»—. El
color sigue diciendo de quién es el trabajo, pero sin rayar la fila, y al lado
va siempre la palabra que dice lo mismo. **Ya no queda ni una franja de color
en todo el panel**: `grep -n "border-left" assets/*.css` solo devuelve tres
líneas grises de 1 px, que son separadores de columna.

### Lo del 27 al cierre: Caídas, de 4.809 px a 2.672 (0.19.3 → 0.19.4)

El punto 2 de la lista de §9. El diagnóstico que estaba escrito —«la pantalla
está bien, lo que satura es lo que enseña de golpe»— era exacto: medida antes
de tocar nada, la página tenía **4.809 px de alto y la tabla empezaba en el
893**, así que la primera pantalla del portátil no enseñaba ni un sitio.

Mismo método que en Vulnerabilidades: los dos avisos de tres párrafos pasan a
una línea con burbuja; el veredicto sube al subtítulo de la cabecera y su
mecánica a la burbuja; fuera la dona y en su lugar la filita de tarjetas
pulsables; y la tabla pasa a filas que se despliegan, de 140 px a 69.

Lo propio de esta pantalla: **a un sitio que responde bien no se le explica
nada**. La celda «Ahora» llevaba un «Qué significa» plegable en todas las
filas, y el de un 200 decía «el panel no tiene una traducción para esta
respuesta». Ahora el análisis sale solo donde hay algo que analizar, así que
la columna de la derecha está vacía casi siempre y las filas con problema
—que van primero— son las únicas que saltan. El día que no hay ninguna, la
rejilla pasa a dos columnas, decidido por página entera y nunca fila por fila.

El historial se pagina de diez en diez con su propio parámetro `ph`.

Queda: **2.672 px, y la lista empieza en el 477.**

### Lo del 27, cerrando la lista: Seguridad (0.19.4 → 0.19.5)

El punto 4 y último. Era la que menos urgía —«creo que está más decente»— y
tenía dos cosas:

- **La cuarta dona del panel.** Otra caja de 278 px para tres números de los
  que dos suelen ser cero, y sin poder pulsarlos. La misma filita de tarjetas,
  y ahora cada cifra lleva a su lista: «Cumplen todo» y «Les falta algo» abren
  la vista por sitio con el estado puesto, «Sin revisar» abre la de «Sin
  información».
- **La misma frase, cinco veces.** Cada bloque de norma listaba cinco sitios y
  repetía en los cinco, palabra por palabra, el mismo detalle: «El sitio no
  manda la cabecera. La primera visita de cada persona sigue saliendo sin
  cifrar.» ×5. Ahora, cuando los detalles del bloque son todos iguales, se
  dice una vez bajo el título y la columna desaparece; cuando de verdad
  cambian de sitio en sitio —«3 módulos en una versión con falla conocida:
  …»— se quedan. Y el porqué de la norma deja de ser un párrafo recortado con
  puntos suspensivos y pasa a la burbuja del título.

Queda en **2.432 px** (eran 2.883) y el primer bloque empieza en el 577.

**La lista de §9 está cerrada.** Lo que sigue pendiente de maquetado es la
pieza concreta del final de esa sección: el botón «arreglarlo en Kinsta» para
las normas que se tocan en MyKinsta y no en el sitio —el HTTPS forzado, las
redirecciones, la protección de bots—, comprobando cada dirección a mano antes
de escribirla.

### Lo del 27 a mediodía: el panel decía que 100 sitios tenían 2FA (0.14.1 → 0.15.1)

Fer pidió apagar la verificación en dos pasos de Heraldos Costa Rica: «no sé
por qué está activa». **No estaba activa.** El agente de ese sitio reporta
`dos_pasos = 0`, y la norma `dos_pasos_activa` lo decía bien: «aplazada».

El fallo estaba en `marcar_cobertura_2fa()`: contaba `agente-sitio` como un
plugin de 2FA cualquiera, así que bastaba con que estuviera instalado y activo
para pintar «Doble autenticación ✓ Activa». Y el agente trae esa verificación
**apagada a propósito** desde el 19 de noviembre. Resultado: la tarjeta mentía
en **100 sitios**. Es la queja de fondo del jefe —«no es correcto lo que dice
aquí, entonces yo no puedo confiar en este panel»— en una tarjeta que nadie
había vuelto a mirar.

Ahora se lee de `wp_swpk_agentes.dos_pasos`, que es el valor que el agente
manda en cada reporte. **La tiene encendida 1 sitio de 107: Estilo Urbano.**
Se lee de la tabla de agentes y no de la foto de plugins porque en algunos
sitios el agente va dentro del tema hijo y ahí no hay fila `agente-sitio` que
mirar — gatearlo por la foto dejaba fuera justo al único que la tiene.

#### Por qué 27 normas se quedan en «falta medirlo»

«¿Por qué no las hemos podido medir? ¿Porque esas no se pueden medir
automáticamente?» Sí se pueden. En Heraldos son 27, y son **las mismas 27 en
los 107 sitios**: todas las de `COMPROBADORES_DE_SONDA`, las que se miden
pidiéndole la página al sitio desde fuera. Lo que falla es el cortafuegos —el
mismo 403 de Cloudflare que deja ciego al sondeo de caídas—, no el agente ni
la norma.

`SWPK_Normas::por_que_no_se_midio()` lo dice en la propia norma, distinguiendo
tres casos: la mide la sonda desde fuera y no la dejan llegar; la mide el
agente y este sitio no lo tiene; la mide el agente y todavía no la ha mandado.

#### El botón que la pantalla prometía y no estaba

«Aquí dice "el panel lo puede hacer solo", pero no me tiene el botón.» El
botón solo salía con la norma en FALLA, y XML-RPC está en «falta medilo».
Cerrar XML-RPC no necesita haberlo medido: la orden es la misma y no hace daño
si ya estaba cerrado. Ahora sale también en SIN_DATO. Primero decía
«Mandarlo igual», y eso duró media hora: «ni se entiende qué significa eso».
El botón dice **«Arreglar»** en los dos casos, y la salvedad va escrita debajo,
dentro: «Aunque no se pudo medir, la orden se puede mandar: si ya estaba bien,
no cambia nada».

#### Los plugins apagados

Pedido del jefe: «si hay algún plugin que está desactivado, que nos lo diga
cuando entramos al sitio… que te aconseje que lo elimines». Salían solo si
además tenían una actualización pendiente, porque los hallazgos se arman de
vulnerabilidades y actualizaciones. Ahora hay una caja propia,
`SWPK_Inventario::apagados()`, con el consejo según el caso: borrarlo cuanto
antes si además es vulnerable, borrarlo si ya no se usa, o preguntarle al
cliente si el sitio es suyo. En Heraldos son 4.

---

### Lo del 27 por la tarde: el must-use no estaba apagado (0.15.1 → 0.15.5)

**«Kinsta Must-use Plugins» salía entre los apagados.** Fer:

> «ese es de Kinsta, no debería de estar entre los apagados, porque ese ni
> siquiera aparece dentro de los sitios»

Y nos lo dejó definido, que es lo que faltaba: *apagado* quiere decir **que
alguien lo desactivó en el escritorio del sitio**, no que lleve tiempo sin
uso. Un must-use plugin se carga siempre y no se puede desactivar ahí.
WordPress no lo apunta en `active_plugins` porque no hace falta, la API de
Kinsta lo devuelve con `status: inactive` por eso, y el panel se lo creyó.

Lo decidía en dos sitios distintos y uno estaba mal: la norma
`inv_inactivos` lo saltaba con un `str_starts_with( $slug, 'kinsta-' )`
escrito a mano, y la caja nueva no lo saltaba. Ahora lo decide
**`SWPK_QueHacer::se_puede_apagar()`**, una sola vez, y lo usan los tres
sitios donde se dice «apagado»: la norma, la caja y la pastilla de la fila de
pendientes.

**Filtro de gravedad en las normas.** Estaban «Tipo de norma» y «Estado», y la
pastilla Alta/Media/Baja se enseñaba en cada fila sin poder filtrar por ella:
«no podemos filtrar por eso». Ahora son tres desplegables con su cuenta
dentro, y «las altas que no cumplen» se pide en dos clics.

### Lo del 27 por la tarde: la sonda ya termina sola (0.15.5 → 0.16.2)

`SWPK_Sonda::correr()` hacía los 108 sitios de una pasada —unas 2.700
peticiones, diez minutos— y guardaba solo al final. Ni el reloj de Pantheon ni
una sesión de consola aguantan tanto, así que moría siempre antes de guardar.
Nunca había terminado. Ahora va por tandas, igual que `SWPK_Trabajo`:

- `empezar()` arma la cola, **hace la primera tanda ahí mismo** y pide la
  siguiente. Lo llama el reloj diario.
- `avanzar()` hace diez sitios o veinticinco segundos, lo que llegue antes,
  guarda dónde quedó y encadena. Con candado, para que no avancen dos a la vez.
- Los sitios salen de la cola **antes** de sondearlos: si el servidor mata una
  tanda, la siguiente sigue con los que faltan en vez de reintentar los mismos.
- La protección de «una corrida ciega no borra lo bueno» ahora es por sitio:
  el que vuelve sin rutas fiables no se mide y conserva lo de ayer.

**Dos cosas que costaron encontrar, las dos medidas en producción:**

1. `'blocking' => false` con `'timeout' => 0.01` —copiado de
   `SWPK_Trabajo::encadenar()`— **no manda la petición cuando quien la manda
   es la línea de comandos o el reloj**, que es justo de donde arranca esta
   corrida. Desde una pantalla sí sale. La corrida se quedaba en 108
   pendientes sin moverse. Ahora se manda esperando medio segundo
   (`'blocking' => true`, `'timeout' => 0.5`): sale siempre, y la espera no es
   por el trabajo —la tanda del otro lado tarda veinticinco segundos—, porque
   `relevo()` pone `ignore_user_abort()`.
2. Aun así hace falta la red del reloj `swpk_sonda_tanda`, que se vuelve a
   programar en cada encadenado.

**Comprobado dos veces de punta a punta**, arrancando con
`wp cron event run swpk_sonda_diaria` y sin tocar nada más:
`{"sondeados":108,"rutas_no_fiables":1,"expuestos":10,"medidos":107,
"corrida_mal":false}`, en 8 min 20 s y 8 min 43 s.

#### La clasificación de rutas, que es lo que dejaba el «falta medirlo»

`bloqueado` no es un hallazgo: es «no pude averiguarlo», y en la pantalla sale
como «falta medirlo». Para decir `protegido` la sonda pide la misma ruta con
un nombre inventado: si el servidor también la niega, es regla para todos; si
contesta, el bloqueo es solo contra la sonda y no se aprendió nada.

Eso no salía igual en todas las corridas. El mismo sitio, el mismo día, la
misma versión: sondeado aparte daba `protegido`, `ausente` y un `expuesto`;
dentro de la corrida completa, las nueve rutas en `bloqueado`.

**Tres hipótesis descartadas, todas midiendo, no razonando:**

| Se pensó | Se midió | Resultado |
|---|---|---|
| Es el tamaño de la tanda | El mismo sitio en grupos de 1, 10 y 30 | Idéntico y bueno las tres veces |
| Es la compañía de la tanda | Su tanda real de 10, los mismos vecinos | Bueno |
| Son peticiones que se caen | Contador propio en la corrida | 4 caídas de 6.264 |

Así que la causa sigue sin estar clara. Lo que sí se arregló:

1. **Un 429 o un 5xx ya no es «ese archivo no existe».** Era un error de
   verdad y con dos caras: en la ruta real convertía un «no sé» en un
   «cumple» —la mentira que prohíbe la regla 3—, y en la dirección de control
   hacía que el 403 del archivo real pasara de `protegido` a `bloqueado`.
2. **Lo que no llega se vuelve a pedir**, con tres segundos de respiro, y una
   segunda respuesta mala no pisa la primera.
3. **Un «no sé» de hoy no borra un «sí» de ayer.** Es la regla que ya
   gobernaba la corrida entera, aplicada a cada ruta: si hoy una ruta sale
   `bloqueado` y ayer salió `protegido`, `ausente` o `expuesto`, se conserva
   la de ayer y se guarda de cuándo es. A los tres días deja de valer y la
   norma vuelve a «falta medirlo», que entonces sí será verdad. Está en
   `conservar_lo_medido()`.

**Medido después, en producción:** las 24 normas que se miden desde fuera son
2.592 mediciones posibles en los 108 sitios. Esta mañana estaban las 2.592 sin
medir, porque la sonda no había terminado nunca. Ahora hay **1.778 medidas y
814 sin medir** — del 0 % al 69 %. Heraldos pasó de 27 a 5, y sus fallas
reales de 11 a 15. Y la corrida encontró **20 sitios con algún archivo
alcanzable desde fuera**; en Heraldos, `readme.html`, que dice qué versión de
WordPress corre.

#### Y las otras 1.003: la revisión diaria tampoco terminaba

Dije que eran «las que contesta el agente, en sitios que no lo tienen». **Era
falso**, y Fer lo cazó en una línea: «entiendo que solo era uno el que no
tenía el agente instalado». Son dos —Cito Color y Trazo—, no ochenta.

Lo que había detrás era la misma enfermedad por tercera vez.
`swpk_ultima_revision` decía, todos los días:

    {"estado":"corriendo","arranco_en":23,"total":109}

«Corriendo» desde las 4:35 de la mañana, con 23 sitios de 109. Son trece
llamadas a la API de Kinsta por sitio —mil cuatrocientas—, y el cliente frena
solo para no pasarse del límite de Kinsta: catorce minutos. No cabe en una
petición.

El 25 de noviembre esto ya se había arreglado **a medias**: se le puso un
cursor para que cada día siguiera donde se quedó y diera la vuelta a la
cartera en varios días. El cursor avanzaba, sí. Pero la revisión no terminaba
nunca, y el dato de un sitio tenía cinco días cuando le tocaba. Un arreglo
que esconde el síntoma es peor que ninguno: desde entonces la opción decía
«corriendo» y nadie volvió a mirarla.

Ahora va por tandas y se encadena sola, igual que la sonda. **Comprobado
desde el reloj:** `{"estado":"terminada","total":108,"revisados":108,
"fallos":0,"medidos":108}` en 13 minutos. Primera vez que termina.

Y como ya iban dos copias del mismo mecanismo, la parte que costó encontrar
vive en un solo sitio: **`SWPK_Relevo`**. Ahí está escrito por qué el aviso
se manda esperando medio segundo y no sin esperar, que es lo que
`SWPK_Trabajo::encadenar()` hace y lo que a esas dos no les funcionaba.

**Resultado del día, medido:** las mediciones sin dato pasaron de 27 por
sitio a **9,8**. Heraldos, de 27 a **5**.

---

### Lo del 27, al cierre: el goteo, y un interruptor para la corrida

**El goteo de XML-RPC** (`SWPK_Sonda::empezar_goteo()`): un sitio por minuto,
empezando a las 9:00 UTC, que son las 3 de la mañana en Guatemala. Los 108
quedan medidos en menos de dos horas. Las cuentas, que son lo que manda:

| | Peticiones por hora |
|---|---|
| Lo que nos bloqueó | ~47.000 |
| El goteo | 60 |

Lo que NO hace, a propósito: acelerar cuando va bien. Así es como se vuelve a
trece por segundo.

**Un interruptor, `swpk_sonda_pausada`.** Al parar la corrida en caliente
descubrí que no se podía: `init()` llama a `programar()`, así que la
siguiente visita al escritorio la volvía a poner. El «lo paré» duraba hasta
que alguien abriera una pantalla. Ahora hay una opción que `programar()`
respeta. **Está puesta: la corrida completa no corre hasta que se quite.** El
goteo sí, que para eso se hizo.

**Y un falso «cumple» que habría salido en los 108 sitios.** `leer_xmlrpc()`
devolvía `bloqueado` —o sea, norma cumplida— ante cualquier 403, incluido el
de Cloudflare bloqueándonos a nosotros. Con la IP bloqueada, la cartera
entera habría dicho «XML-RPC cerrado» sin que nadie hubiera comprobado nada.
Ahora el bloqueo del borde se lee como `no_se_pudo`. Comprobado en
producción: antes de arreglarlo, Heraldos decía «cumple»; después, «falta
medirlo», que es la verdad mientras no nos dejen mirar.

---

### Lo del 27 por la noche, confirmado por Kinsta: fueron nuestras peticiones a xmlrpc.php

Soporte de Kinsta, textual, mientras yo seguía buscándolo:

> «La razón por la que fuiste bloqueado es que hay muchas solicitudes XML-RPC
> a múltiples sitios alojados con nosotros en un corto período de tiempo. Más
> de 50 sitios.»

Eran nuestras. Una línea de `SWPK_Sonda::plan()` mandaba un POST a
`xmlrpc.php` por sitio: **108 POST a xmlrpc.php en ocho minutos, repartidos
por toda la cartera**. Visto desde un cortafuegos eso no se distingue de un
ataque de fuerza bruta distribuido, porque es literalmente la misma forma.
Nos bloquearon la IP de salida y con ella se cegó todo: las normas y el
monitor de caídas.

Es la causa de todo lo de hoy, y también la del 23 de noviembre, cuando 106
sitios figuraban caídos sin estarlo.

**Lo hecho, en caliente:**

1. **La corrida diaria ya no pide `xmlrpc.php`.** Se queda solo para el botón
   de «analizar este sitio ahora»: un sitio, cuando alguien lo pide. Un sitio
   no es un patrón. Tiene un coste y hay que decirlo: esa norma solo se puede
   afirmar desde fuera —el dato del agente da falsos positivos, está en
   `comprobar_xmlrpc_cerrado()`—, así que donde nadie pulse el botón se
   quedará en «falta medirlo». Es el precio de no parecer un atacante.
2. **Freno de dos peticiones por segundo** en toda la corrida
   (`SWPK_Sonda::POR_SEGUNDO`). Pasa de ocho minutos a una hora larga, de
   madrugada. El botón de un sitio no lleva freno: 58 peticiones sueltas no
   mueven ninguna aguja y hay una persona esperando.
3. **La sonda queda desprogramada** hasta que Fer diga. No corre esta noche.
4. El monitor de caídas sigue: son 108 peticiones a la portada por hora,
   0,03 por segundo, y es lo único que avisa si un sitio se cae.

**Lo que hay que pedirle a Kinsta:** que desbloqueen `34.46.111.53` y
`35.239.102.121`, las dos IP de salida de Pantheon, diciendo qué se cambió.

**La lección, para que no se repita:** el panel sondea sitios de clientes
desde una IP compartida. Cualquier comprobación nueva que se repita por toda
la cartera hay que mirarla dos veces, y preguntarse cómo se ve **desde el
cortafuegos del otro lado**, no solo si el dato es correcto. Un patrón que
parece un ataque es un ataque, a efectos de quien lo recibe.

---

### Lo del 27 por la noche: cómo se encontró (el bloqueo es POR IP)

Al mirar los 20 sitios que la corrida marcó con un archivo alcanzable desde
fuera saltó el detalle: **son los 20 primeros por orden alfabético**, o sea
las dos primeras tandas. Los demás no es que estén limpios: es que no se
pudieron medir.

Y la razón, comprobada a mano desde producción: `readme.html` y **la portada**
de los sitios 30, 60 y 95 contestan 403 con el cuerpo de Cloudflare:

    Attention Required! | Cloudflare — Sorry, you have been blocked

La prueba que lo cierra: **cambiando el agente de usuario a uno de Chrome,
también 403.** No es el agente. **Es la IP.** Cloudflare tiene bloqueada
`34.46.111.53`, que es por donde sale el panel, y la bloquea para todo.

Eso explica el día entero:

- Por qué la excepción por agente de usuario no cambió nada: Cloudflare corta
  por IP antes de mirar el agente.
- Por qué un sitio sondeado aparte sale bien y dentro de la corrida sale
  «bloqueado»: al principio de la corrida la IP todavía no está bloqueada.
- Por qué los 20 son los 20 primeros.
- Por qué el 23 de noviembre 106 sitios figuraban caídos sin estarlo.
- Y, muy probablemente, por qué no salió el correo de Cito Color.

**Lo que lo dispara es el propio panel.** Una corrida son 6.264 peticiones en
ocho minutos: unas 13 por segundo contra sitios del mismo hosting. Cualquier
cortafuegos con límite por IP corta eso. Nos estamos bloqueando solos.

**Lo que hay que decidir, y es decisión de Fer:**

| | Qué es | Qué cuesta |
|---|---|---|
| Bajarle el ritmo al sondeo | Repartir las 6.264 peticiones en una hora en vez de ocho minutos. Es nuestro, no toca la seguridad de nadie | La corrida diaria tarda una hora, de madrugada |
| Excepción por IP en Kinsta | `{"type":"ip","action":"skip"}` con las dos IP de salida de Pantheon, en los 109 | **Las IP de salida de Pantheon son compartidas**: dejaríamos pasar también el tráfico de otros clientes de Pantheon por encima de la protección de bots de NUESTROS clientes |
| Preguntarle a Kinsta | Por qué la excepción por agente no evita el bloqueo | Depende de ellos |

La recomendación es la primera, y la segunda solo si la primera no basta.

**Mientras tanto el panel está ciego**: ahora mismo no puede ver si un sitio
se cae, porque le contestan 403 a todo.

---

#### La excepción de bots: puesta en los 109, y lo que resultó NO ser la causa

**La excepción está puesta en los 109 sitios** (27 de noviembre, 16:40 UTC),
por la API, verificada después en cinco al azar:
`{"type":"user_agent","action":"skip","value":"PanelKinsta"}`. Kinsta la
guarda en minúsculas, con el id `user_agent:skip:panelkinsta`.

Pero hay que decir lo que no se pudo probar: **no hay antes y después**. Ese
día el bloqueo de Cloudflare no se reprodujo ni una vez. La portada de un
sitio con excepción y la de uno sin ella contestaron 200 las veinte veces, y
el 403 que sí sale —en `xmlrpc.php`, `wp-config.php.bak`, `phpinfo.php`— no es
de Cloudflare: el cuerpo es la página de nginx y la cabecera dice
`ki-cf-cache-status: BYPASS`, o sea que pasó Cloudflare y lo paró Kinsta.
Ese 403 es la regla del servidor, igual para todos, y la sonda ya lo sabe:
compara cada ruta con una de control.

**Entonces las 27 normas no eran Cloudflare.** Era que
`SWPK_Sonda::correr()` **nunca había terminado una corrida en producción**:
`swpk_ultima_sonda` estaba sin escribir. Son 108 sitios y tarda unos diez
minutos, más de lo que aguanta una corrida de cron en Pantheon, así que moría
siempre antes de llegar al `update_option` del final. Se forzó a mano y
terminó: `{"sondeados":108,"rutas_no_fiables":1,"corrida_mal":false,
"medidos":108}`. Heraldos pasó de **27 normas sin medir a 4**, y sus fallas
reales de 11 a 19 — estaban escondidas detrás del «falta medirlo».

**Lo que queda por arreglar:** la sonda tiene que correr por tandas, como
`SWPK_Trabajo::avanzar()`, en vez de una pasada de diez minutos que el
servidor corta. Mientras no se haga, mañana vuelve a quedarse a medias.

La excepción se deja puesta igual: el bloqueo de Cloudflare del 23 de
noviembre —106 sitios con la página «Attention Required!»— fue real, y es al
monitor de caídas al que le pega. Ver `SWPK_Monitor::SENALES_DE_BLOQUEO`.

#### Cómo se pone a mano, con los clics

Comprobado en MyKinsta el 27 de noviembre, entrando a uno de los sitios:

1. MyKinsta → **Sitios** → el sitio → **Protección contra bots** (en el menú
   de la izquierda; sale marcado como *Beta*).
2. Abajo del todo, **Excepciones** → **Añadir excepción**.
3. Pestaña **Agente de usuario**. El propio cuadro dice: «la regla se aplicará
   siempre que parte de la cadena especificada coincida», así que basta con
   escribir `PanelKinsta`.
4. Dejar marcado **Permitir** (las otras dos son *Desafiar* y *Bloquear*) y
   **Añadir excepción**.

`PanelKinsta` es el agente de usuario de las dos cosas que hoy se quedan
fuera: `SWPK_Monitor` (`PanelKinsta/<versión> (monitor de disponibilidad)`) y
`SWPK_Sonda` (`PanelKinsta/<versión> (revision de configuracion)`).

**Es por sitio.** En el que miré, la protección iba bloqueando cerca de una
cuarta parte de las peticiones de las últimas 24 horas. Hacerlo en los 109 a mano son 109 vueltas,
así que lo sensato es probarlo en uno, comprobar que la sonda pasa, y recién
entonces pedirle a Kinsta por ticket que lo aplique a toda la empresa.

---

### Lo del 27 a media mañana: la segunda pasada (0.14.0 → 0.14.1)

La columna «Qué hacer» contestaba la pregunta, pero cuatro columnas de
párrafos acumulaban: «esta visualización ahora no me gusta, siento que se ve
más desordenado y se acumula toda la información». Cada módulo ocupaba media
pantalla. Ahora **la lista de pendientes se lee como las normas**: cada módulo
es una línea que se despliega, con módulo, nivel y qué hacer en la línea
cerrada, y el porqué dentro, en dos bloques numerados.

**Los filtros de normas** se habían escrito a mano en vez de usar
`SWPK_Vista::barra_de_filtros()`, la misma de Sitios, Vulnerabilidades e
Historial: «estos filtros quedan horribles, nada que ver con los demás del
sitio». Ya usan la compartida.

**«Abrir en Kinsta» no abría el sitio.** Construía
`/sites/info/<sitio>/<entorno>`, que MyKinsta no reconoce y redirige a la lista
de los 109. Las rutas buenas, comprobadas copiándolas de la barra del
navegador: `/sites/details/<sitio>/<entorno>` para la ficha y
`/sites/errorLogs/<sitio>/<entorno>` para los registros. Los dos identificadores
que guardamos siempre fueron los buenos. Y la burbuja del reporte para el
cliente iba después de ese botón, así que parecía explicarlo: «la burbuja no
dice nada relacionado a Kinsta ni se entiende qué onda».

#### Y por qué no llegó el correo de Cito Color

Kinsta avisó a las 7:47 de un 504 en Cito Color y el panel no mandó nada. Hay
dos motivos, y el segundo es el de fondo:

1. **El panel mira una vez por hora.** Kinsta mira sin parar. La caída empezó y
   terminó entre dos vueltas: a las 13:57 UTC el sitio contestaba 200 en 0,37 s.
   El sondeo de cinco minutos de la 0.13.0 **no ayuda aquí**: solo se enciende
   cuando el panel YA pilló un sitio caído, y sirve para medir cuándo vuelve.
2. **El panel está ciego en la mayoría de las vueltas.** La corrida manual de
   hoy: `{"sondeados":108,"abajo":0,"ciegos":106,"corrida_mal":true,
   "ip_de_salida":"34.46.111.53"}`. Es el bloqueo de Cloudflare documentado en
   `KINSTA-pedido-de-desbloqueo.md`: de las dos IP de salida de Pantheon, una
   está en lista negra y la otra limpia, y Pantheon no deja elegir. Cuando la
   vuelta sale por la mala, el panel no escribe nada —y hace bien, para no
   borrar lo bueno— pero tampoco puede detectar una caída.

O sea: aunque la caída hubiera durado una hora entera, había una moneda al aire
de que el panel no la viera. **Sigue pendiente la excepción de Bot protection en
MyKinsta**, que son dos minutos en un sitio de prueba.

El sondeo en sí funciona: 108 sitios en 5,8 segundos, y los avisos salen cuando
hay algo que decir (ayer: `[Caído] Cito Color` 14:33 y `[Repuesto] Cito Color`
15:33, a jdiaz, lalvarez y mvalle).

---

### Lo del 27 por la mañana: «¿y qué hago yo con esto?» (0.13.2 → 0.14.0)

Fer abrió Heraldos Costa Rica y se encontró con Modern Events Calendar Lite
5.15.5 marcado **Crítico · Hay parche** y sin ninguna actualización ofrecida:

> «dice que hay un parche, pero no tiene una actualización, ¿o sí? Entonces,
> ¿qué es lo que podemos hacer? Aquí no me dice qué podemos hacer, solo dice
> quién puede aprovechar esta vulnerabilidad, qué es lo que permite hacer, pero
> no me está diciendo qué hacer.»

**Lo que había debajo.** Ese plugin está **cerrado en wordpress.org desde el 11
de mayo de 2022** por incumplir las normas. La falla se corrige en la 7.37.0 y
el sitio tiene la 5.15.5: esa versión no va a llegar nunca por WordPress. Lo
que había que hacer no era actualizar, era reemplazarlo.

El panel no lo decía por un fallo de una línea: `SWPK_Repositorio::preguntar()`
miraba el **código HTTP** y el repositorio pone el motivo del cierre en el
**cuerpo del 404**. Así que «404 → no se sabe», y los cinco módulos cerrados de
la cartera —`modern-events-calendar-lite`, `admin-block-country`,
`appointment-calendar`, `ultimate-addons-for-elementor` y un `twentytwenty`,
**cuatro de ellos cerrados por un problema de seguridad**— salían etiquetados
«Hay parche: actualizar».

Había además **dos implementaciones** de la misma pregunta:
`SWPK_Riesgo::esta_abandonado()`, que leía el cuerpo y acertaba, y
`SWPK_Repositorio::estado()`, que no. La que pintaba la etiqueta de la pantalla
era la mala. Ahora hay una sola, y distingue plugin de tema: existe un
**plugin** `twentytwenty` cerrado en 2024 y el **tema** Twenty Twenty vivo en su
3.3, y preguntando siempre por la API de plugins el tema por defecto de
WordPress salía marcado como cerrado por seguridad.

**`SWPK_QueHacer`** (`includes/class-que-hacer.php`) contesta la pregunta. No
hay una respuesta, hay ocho, y cada una lleva a un trabajo distinto: lo
actualiza el hosting · cerrado por WordPress · hay versión y corrige · hay
versión pero no alcanza · hay parche y el sitio no lo ve · sin parche todavía ·
está apagado · solo actualización.

**Los must-use de Kinsta.** Fer también vio `kinsta-mu-plugins 3.7.0 → 3.8.0`
con la frase «hay que entrar al sitio a actualizarlo» debajo:

> «esos deberían ser automáticos, ¿no? Porque esos no es que lo instalemos
> nosotros, sino de parte de Kinsta […] ¿podemos nosotros saber por qué no lo
> ha hecho?»

Tenía razón: nadie puede actualizarlos desde wp-admin, y sale pendiente en
**100 de los 109 sitios**. Ahora dice «Lo actualiza Kinsta» y contesta la
segunda pregunta con un dato nuestro: en cuántos sitios de la cartera ya cayó
la versión nueva (hoy, 1 de 103). Cuando pase del 80 % y este sitio siga sin
ella, el panel sugiere preguntarle a Kinsta.

**La tabla de pendientes** pasa de cinco columnas a cuatro: módulo (con la
versión dentro), nivel, **qué hacer** y por qué importa. «Quién puede
aprovecharlo» y «qué permite» eran la misma pregunta partida en dos.

**En la ficha del sitio**, tres cosas más que pidió en el mismo rato:

- **Tarjeta de «Normas sin cumplir»** en la cabecera, al lado de las otras dos
  cifras, y lleva a la pestaña de normas ya filtrada por «No cumple». Las
  versiones de WordPress y PHP siguen ahí pero en pequeño: «¿para qué quiero
  saber el tipo de WordPress que tiene, el PHP? Lo cual no está mal. Pero aquí
  realmente la información que me importa…».
- **Los filtros de normas**, de quince pastillas en cuatro renglones a **dos
  desplegables y un buscador** en una línea. Cada opción lleva su cuenta
  dentro. Y el rótulo «Cómo está» pasa a «Estado» aquí y a «Riesgo» en Sitios:
  «"cómo está" como que no se entiende; entre que tiene que ser básico, pero no
  tan básico que se oye ya más corriente».
- **La norma abierta**, en **tres pasos numerados** en vez de dos columnas.
  Estaba «cómo lo sabemos» arriba a la izquierda, «cómo se arregla» debajo y
  «qué pasa si no» a la derecha: leído de izquierda a derecha salía 1, 3, 2. No
  era impresión suya, el orden estaba roto. Ahora: qué medimos y qué salió →
  qué pasa si se deja así → cómo se arregla, con el botón cerrando el tres.

---

### Lo del 26 de madrugada: los 18 del PDF, cerrados (0.11.0 → 0.13.2)

De los 18 que pidió, quedaban los puntos 6 al 18. Están todos.

- **9 · El botón de «prueba» que no lo era.** Los cuatro llamaban al envío de
  verdad; el del mensual recorre los 109 sitios y le escribe al contacto de
  cada cliente. No pasaba nada porque ese aviso está apagado desde la 0.9.0,
  pero el día que se encendiera mandaba correo a todos a quien le diera clic.
  El arreglo va en `despachar()`, la única puerta por la que sale un correo:
  con el desvío puesto, los cuatro se vuelven pruebas sin tocar su código.

- **6 y 7 · Los 73 «cómo se arregla».** El campo no existía: la tabla guardaba
  nombre, problema, porque, comprobador y severidad, y ninguno decía cómo
  cerrar la norma. Dos columnas nuevas (tablas v18) y los 73 textos escritos,
  en `includes/normas-arreglos.php`. Cada norma se despliega con las cuatro
  respuestas de la maqueta.

- **8 · La columna que bailaba** salió gratis con el 6: podía tener siete
  contenidos distintos alineados a la derecha. Ahora es de ancho fijo, a la
  izquierda, y solo lleva botón.

- **10, 11 y 12 · Las caídas.** `SWPK_Codigos` traduce los diez códigos: qué
  significa, de quién es el arreglo, qué se mira primero. Sale en el correo y
  en el panel, con enlace a los registros de Kinsta. Y el sondeo pasa a cinco
  minutos **solo mientras hay algo caído** —no los 109 cada cinco minutos—,
  con el evento puesto al caer el primero y quitado al volver el último.

- **13 · El slug en las cinco pantallas** y los enlaces cruzados ficha ↔
  historial. Es lo que cierra la contradicción de Elementor.

- **14 · El editor a dos columnas**, con la previa al lado actualizándose al
  teclear. Y sí, la marca también se sustituye en el asunto: era una pregunta
  suya concreta.

- **15 · El Resumen lista en vez de contar**, con el camino a cada cosa.

- **17 · El reparto en una frase** en la ficha: cuántas arregla el panel,
  cuántas obligan a entrar y cuántas hay que hablar con el cliente.

- **18 · Las burbujas** en las cinco vistas que no tenían ninguna.

#### Las asesoras, y la trampa que casi se come el reparto

Fer pidió las dos asesoras en todos los sitios de auto update y ninguna en los
no update. En producción hay **cuatro** personas con perfil de asesora
—Tiffany, Alejandra, Luisa y Fer—, así que repartir «todas las que tengan el
perfil» les habría puesto 96 sitios a las cuatro, y con eso el correo semanal
a dos que no lo esperan.

Por eso el reparto va por una **marca por persona** —«Lleva cartera», en
Equipo— y no por deducción del perfil. Marcadas Tiffany y Alejandra, que son
las dos que nombró el jefe. Resultado: **96 sitios nuestros con las dos, 13 del
cliente sin ninguna**. El semanal sigue apagado, así que todavía no sale nada.

Y un fallo que salió al mirarlo: «Qué alcanza» en Equipo contaba sobre
`sitios.asesora_id`, que solo guarda a la primera. Alejandra salía con 96 y
Tiffany con «ninguno», teniendo las dos exactamente los mismos.

### Lo del 26 por la noche: los cinco primeros del PDF (0.10.0 → 0.11.0)

El PDF del 26 lista **18 cambios**, y dice cuáles van primero: «del 1 al 5 son
los que cambian lo que se ve al abrir». Esa noche se hicieron píldoras,
filtros, el registro de actividad (el 16) y una pantalla llamada «Lo que
toca»… **que no estaba en los 18**. Fer abrió el panel y no vio nada
cambiado, porque de los cinco primeros solo había pedazos.

**La lección, que es la que importa:** una lista priorizada por el cliente no
se reordena por cuenta propia. Si se hace el 16 antes que el 2, el cliente ve
la pantalla igual que ayer y con razón pregunta en qué se fue la tarde.

- **El amarillo que quedó.** La paleta se había aplicado a `.swpk-nivel-*` y
  nadie miró `.swpk-sev-*`, que es la que pinta Alta/Media/Baja en las tablas
  de normas. Vive en `heredado.css`, que casi nunca se abre. **Buscar la clase
  por su nombre no basta: hay que buscar el COLOR por su hex en las dos
  hojas.** De paso salieron la mostaza de la celda «en pausa» y tres fondos
  crema.

- **`swpk-nivel-al_dia` lo compartían dos cosas.** El nivel de riesgo «Al día»
  y la insignia «Automático» de cómo se actualiza. Ponerle gris a una se lo
  ponía a la otra. La insignia tiene ahora su propia clase, `swpk-autoupd`.

- **Sitios, de nueve columnas a cinco.** Las de Asesora y Contacto eran un
  formulario abierto en las 109 filas a todas horas, para cualquiera con
  permiso de administrar — o sea, siempre. Pasan a un modo con botón que
  conserva los filtros. Versiones, revisado, doble paso y asesora se pliegan
  en «Ver todo».

- **La franja de grupo.** 4 px al inicio de la fila: azul, lila, gris. Fríos a
  propósito — con un naranja ahí la fila vuelve a tener cinco manchas cálidas,
  que es justo de lo que se quejó.

- **«N normas sin cumplir»** en la fila, enlazando a la ficha ya filtrada. Se
  escribió primero leyendo `$c->veredicto`, y la columna se llama `estado`:
  habría salido 0 en las 109 filas sin dar error. Se vio contando filas en el
  render, no mirando el código.

- **La ficha:** «Ver su historial» (abre Historial ya filtrado por ese sitio) y
  «Abrir en Kinsta» (necesita el id del entorno además del del sitio; sin él no
  se dibuja, porque un enlace a un 404 es peor que ninguno). Y una segunda fila
  de pastillas, «Cómo está»: Todas · No cumple · Sin medir · Cumple. Las
  cuentas se calculan ANTES de filtrar, o al elegir «No cumple» las otras
  dirían 0 y no habría vuelta.

- **«Lo que toca», quitada entera.** Menú, manejador y vista.

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

- **El agente dentro del tema hijo: probado el 26, y hay que mandarle una
  corrección.** El PR #1 de `soydiazweb/hello-elementor-child` se unió el 19
  de noviembre, así que el agente ya vive en el tema del jefe — pero **nunca
  se ha instalado en un sitio**. Se activó el tema en `cliente-demo` con el
  plugin suelto apagado y funciona de punta a punta: los dos relojes se
  programan sin gancho de activación, el reporte llega y el panel lo registra
  como `0.6.4-tema`, la pantalla se dibuja dentro de Apariencia con sus seis
  botones, la portada no suelta un aviso. Pero salieron **dos fallos**, los
  dos en la rama que el jefe ya tiene unida:
  1. **La rama declara `0.6.4-tema` y lleva el código de 0.6.3.** El número se
     subió sin traer los archivos: nueve de los trece estaban atrasados. El que
     importa es `class-tienda.php`, que se quedó sin el arreglo del 23 de
     noviembre y revienta con «Unknown column `date_paid_gmt`» en las tiendas
     con el esquema viejo de HPOS. Reproducido tal cual.
  2. **El interruptor `panel_activo` no apagaba de verdad.** El candado estaba
     solo en los relojes, y el vigilante llama a `SWA_Reporte::enviar()`
     directo cuando cambia quién tiene control del sitio — así que un sitio
     con el panel apagado seguía llamando al panel. El candado se movió dentro
     de `enviar()`, que es la puerta por donde pasan todas.

  La corrección está probada y empaquetada en `hello-elementor-child-2.6.2.zip`
  (no va al repositorio: es el tema del jefe), con el detalle en
  `documentacion/15-el-agente-en-el-child-theme.md`. **Falta mandársela**: no
  se abrió PR todavía.

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
