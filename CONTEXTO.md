# Contexto del Panel Kinsta — para arrancar otro chat

Este archivo existe para una sola cosa: **que puedas abrir un chat nuevo,
pasarle esto, y que arranque sabiendo lo mismo que sabe el chat de hoy.**

Fecha de corte: **23 de noviembre de 2026.** Lo que diga de versiones,
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

- `REUNION-2026-11-19-lo-que-pidio.md` — **los 49 pedidos del jefe**, uno por
  uno, textuales, cada uno con su estado (hecho / pendiente / es
  funcionalidad). Si alguien pregunta «¿qué pidió exactamente?», es este.
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

## 5. Estado al 23 de noviembre de 2026

| | Versión | Dónde está |
|---|---|---|
| Panel Kinsta | **0.5.1** | Desplegado en producción y verificado ahí mismo. |
| Agente (plugin) | **0.6.3** | En los 95 sitios. |
| Agente (dentro del tema) | **0.6.4-tema** | En el PR, esperando al jefe. |
| Tema hijo | **2.6.1** | En el PR. |

Lo último que se hizo, en orden:

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

### Deudas técnicas, sin urgencia

- El agente 0.6.3 no se ha vuelto a repartir a los 95 sitios. Lo único que
  cambió es el primer reporte al registrarse, y los 95 ya están registrados,
  así que no corre prisa. **Ojo:** ese cambio entró sin subirle el número de
  versión al plugin, mientras la copia del tema ya va en `0.6.4-tema`.
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
| `7a65097` | Las 5.000 consultas, y por qué la solución es un mapa por petición y no una caché. |
| `a08c10a` | Por qué el contacto escrito a mano le gana al que reporta el agente. |
| `bdbfa34` | «No tiene agente» y «el agente no ha mandado la lista» no son lo mismo: cuatro motivos distintos. |
| `fa58624` | Una medida de maquetado sin el ancho al lado no vale nada. |
| `b86262f` | Leer el HTML no es ver la pantalla. |
| `3b2f382` | El botón de arreglar: la vez que insistí tres veces en que estaba y la causa era mía. |
| `9e3fda6` | El botón azul que no se leía, por especificidad de CSS. Contraste 1,60:1. |
| `da6a2aa` | La mudanza del agente al tema, y las dos minas que destapó. |
| `50206dd` | «La causa que dejé escrita era falsa». |
