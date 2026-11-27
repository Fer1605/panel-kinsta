# 16 · El estilo de las pantallas

Las reglas de cómo se ve y cómo se lee el panel. No son gustos: cada una sale
de algo que el jefe o Fer dijeron delante de la pantalla, y el motivo está
escrito al lado para que no se deshaga por descuido.

**Este archivo es el punto de partida para seguir optimizando pantallas.**
Lo que falta está al final, en §9.

---

## 1. Quién lee esto, y en qué pantalla

- **El jefe** es el lector final. Ve el panel en un portátil de 13 pulgadas.
  **Comprobar siempre a 1280 px de ancho**, no a 1700.
- Lo que él pregunta es siempre lo mismo: **«¿y qué hago con esto?»**
- Fer es quien da la cara delante de él. Si una pantalla no se entiende en
  diez segundos, el problema es de la pantalla.
- Al jefe se le habla de **tú**, no de vos.

---

## 2. La regla madre: una pantalla que enseña un problema dice qué hacer

Describir no basta. Nivel, gravedad, quién puede aprovecharlo y qué permite
son contexto; **falta la acción**.

Cada hallazgo lleva:

- **Quién se mueve**: nosotros · el hosting · el cliente · hay que esperar.
- **Qué se aprieta**: la orden concreta, no «revisar la configuración».

Cuando hay varias situaciones posibles, se enumeran todas y cada una tiene su
frase. Nada de una frase genérica que valga para todo. Las ocho de
`includes/class-que-hacer.php` salieron de ahí.

**Y la acción tiene que ser verdad.** «El resto hay que ir a cambiarlo al
sitio» era falso: el HTTPS forzado se activa en MyKinsta sin entrar al sitio.
Si no se sabe dónde se arregla, se dice «a mano», no se inventa el lugar.

**Pero la acción no se pone donde se pueda apretar sin querer.** Un enlace
dentro de un aviso se lee como «llévame ahí», no como «haz esto ahora». El
aviso de la fecha llevó veinte minutos un enlace que decía «Revisar los sitios
ahora» y arrancaba una corrida de 108 sitios contra la API del hosting:

  «lo apaché sin querer porque no sabía qué significaba […] decía ver sitios o
   revisar sitios, y yo entiendo que es verlos, entrar al panel de sitios. Lo
   apaché y resulta que está jalando información»

**Nada que tarde minutos, que le pegue al hosting o que no se pueda deshacer
se dispara desde un aviso, una burbuja o un enlace suelto.** Va en un botón,
en su pantalla, con su burbuja que dice lo que va a pasar y su aviso de
espera. En el aviso se dice DÓNDE está ese botón, que también es decir qué
hacer.

---

## 3. No saturar. Lo que eso quiere decir, en concreto

Frase textual del jefe: **«no quiero saturar pantallas de información»**.

1. **Nada abierto al entrar.** Ninguna fila desplegada por omisión, ni
   siquiera la primera «para ayudar». Quien quiera el detalle, lo abre.
2. **Un mensaje, no dos.** Si una frase dice lo mismo que otra que está diez
   píxeles más abajo, sobra una. Pasó con el reparto en azul de las normas:
   «tenemos repetida la información, ¿te das cuenta? […] O sea, fatal».
3. **Los filtros van en desplegables, con la cuenta dentro** —«Alta (6)»—, no
   en filas de pastillas que ocupan cuatro renglones.
4. **Listas paginadas.** Nunca scroll infinito. Secciones con título.
5. **Lo accionable arriba.** Y ojo con el orden: un `usort` bien intencionado
   mandó los cinco botones de arreglar a la página tres, y la queja fue
   «sigo sin ver dónde puedo arreglar». No era impresión.

---

## 4. Las palabras

- **Los rótulos nombran el dato, no preguntan por él.** «Riesgo», «Estado».
  Nunca «Cómo está» — «como que no se entiende; entre que tiene que ser
  básico, pero no tan básico que se oye ya más corriente».
- **Los botones dicen el verbo de lo que pasa al apretarlos.** «Arreglar».
  Nada ingenioso: «Mandarlo igual» duró media hora — «ni se entiende qué
  significa eso».
- Si hay un matiz, va **en una línea gris debajo**, no dentro del botón.
- Castellano llano. Una idea por párrafo. El qué-significa antes del porqué.
- Nada de «no se pudo» a secas: decir **qué** no se pudo y **por qué**.

---

## 5. Cómo se arma un bloque que explica

Tres partes o más → **numeradas y en una sola columna**.

En dos columnas el orden de lectura sale 1, 3, 2 y se ve desordenado aunque
cada pieza esté bien. Los `<ol class="swpk-norma-pasos">` con círculo
numerado son el patrón.

Las filas que se despliegan son `<details>` + `<summary>` con CSS grid:
funcionan con el teclado, no necesitan JavaScript y se imprimen abiertas. Las
columnas del `summary` llevan **ancho fijo**, o la columna de acción baila de
fila en fila.

---

## 6. Las burbujas de ayuda

- El icono es una **«i»**, no un «?». Lo pidió el jefe el 27-11-2026.
- Va en serif y cursiva (`Georgia, italic`): una i de palo en un círculo de
  16 px pierde el punto. Ese era el motivo por el que antes era «?», y sigue
  siendo cierto; por eso la i lleva ese tratamiento.
- Gris discreta, se enciende al pasar por encima. **Nunca ámbar**: treinta
  puntos amarillos pesan más que el dato que acompañan.
- La burbuja va **pegada a lo que explica**. La del reporte para el cliente
  estaba detrás del botón de Kinsta y parecía explicar ese otro botón.

---

## 7. Color

- La paleta vive en **dos hojas**: `assets/heredado.css` (lo viejo) y
  `assets/panel.css` encima. Un cambio hecho solo en una deja colores vivos
  en la otra.
- Al cambiar un color se busca **el hex en las dos**, no el nombre de la
  clase:
  ```bash
  grep -rhoiE '#[0-9a-f]{6}' panel-kinsta/assets/*.css | sort | uniq -c
  ```
- Comprobar que dos cosas distintas no compartan clase: `swpk-nivel-al_dia`
  lo usaban el nivel de riesgo y la insignia de actualización, y cambiarle el
  color a una se lo cambiaba a la otra.
- **Verificar el color calculado en el navegador**, no en el archivo. Un azul
  que se leía 1,60:1 pasó tres revisiones del código.
- **Un color que dice un dato se enseña con un PUNTO, no con una franja.**
  Los de «quién se mueve» —nosotros `#1c6dc7`, hosting `#9aa4b2`, cliente
  `#7a5cd6`, esperar `#b4690e`— eran una franja en la orilla izquierda de
  cada fila y ahora son un círculo de 8 px delante de la frase: «cambiémoslas
  a no líneas, sino a un círculo o algo que los identifique con ese color».
  El color sigue diciendo lo mismo, la fila deja de estar rayada, y el punto
  es lo que el panel ya usa en otras partes para «esto es de este grupo».
- Y el punto **nunca va solo**: al lado va siempre la frase que dice lo mismo
  con palabras —«Lo actualiza Kinsta», «Desactivarlo mientras tanto»—. Un
  color no puede ser la única manera de saber algo.
- **Ningún aviso, nota o recuadro lleva franja de color en la orilla
  izquierda.** «Evita usar las líneas de color en la orilla, quítalas todas
  las que encuentres […] esas líneas son mucha inteligencia, te dije que las
  quitaras de todo el sitio, eso incluye hasta las notificaciones.» El estado
  se dice con el FONDO: `#fdf5f5` con borde `#ecc7c9` para lo que va mal,
  `#fdfbf2` con `#ebd9a4` para lo que avisa, `#f4faf5` con `#cbe5d1` para lo
  que está bien. Es la regla que más veces se ha vuelto a romper por
  descuido: se escribe una franja sin pensarlo porque «queda bien».

---

## 8. Lo mismo se ve igual en todas partes

- Los filtros de cualquier listado usan **`SWPK_Vista::barra_de_filtros()`**,
  la misma de Sitios, Vulnerabilidades e Historial. Escribir unos a mano
  salió caro: «estos filtros quedan horribles, nada que ver con los demás del
  sitio, ¿qué onda?».
- Su parámetro de búsqueda es `q`, y «Limpiar» borra todos los campos más
  `q` y `p`.
- Tarjetas de cabecera: las que importan grandes, las de contexto con
  `.swpk-tarjeta-menor`. Y **si una tarjeta enseña un número, se puede
  pulsar** y lleva a la lista ya filtrada.

---

## 9. Lo que falta por optimizar

En orden de urgencia, dicho por Fer el 27-11-2026:

1. ~~**Vulnerabilidades**~~ — hecho el 27 por la tarde, en 0.19.0. Ver §9 bis.
2. ~~**Caídas**~~ — hecho el 27 por la tarde, en 0.19.4. Ver §9 ter.
3. ~~**Pendientes de un sitio**~~ — hecho a la vez, en 0.19.0: filtros y
   páginas. Ver §9 bis.
4. **Seguridad** — «creo que está más decente». **Es la que queda.**

---

## 9 bis. Lo que se hizo con Vulnerabilidades y con los pendientes (0.19.0)

Queda escrito porque es el patrón con el que hay que hacer las otras dos.

**El muro de párrafos se convirtió en líneas que se despliegan.** La tabla
tenía cinco columnas y la del medio llevaba cuatro párrafos: qué pasa, qué
permite la falla, quién puede aprovecharla y qué hay que hacer. Veinticinco
renglones por página son cien párrafos. Ahora cada hallazgo es una línea que se
abre —las mismas clases `.swpk-norma .swpk-pendiente` de la ficha de un sitio—
con cuatro cosas en la línea cerrada: **sitio, módulo y versiones, gravedad y
parche, y qué hacer**. Lo demás vive dentro. Nada abierto al entrar.

**La pantalla de la cartera ahora dice QUÉ HACER, igual que la de un sitio.**
`SWPK_QueHacer` ya existía y solo lo usaba la ficha. Aquí se calcula al armar
la caché, no al pintar, porque los filtros y las tarjetas cuentan por él.

**`SWPK_QueHacer::para()` devuelve `caso`**, una clave estable por cada una de
las siete situaciones (`SWPK_QueHacer::casos()`). Hacía falta porque el titular
lleva la versión dentro —«Actualizar a 3.2.1»— y por eso no se puede agrupar ni
filtrar por él.

**Cuatro tarjetas pulsables arriba**, en vez de las cuatro pastillas de
gravedad que repetían el desplegable de Gravedad: con falla conocida, solo
actualización, se arreglan actualizando, y sin arreglo todavía.

**Dos filtros nuevos y uno retirado.** Entran «Qué hay pendiente» (falla
conocida / solo actualización) y «Qué hacer» (los siete casos). Sale «Qué se
puede hacer», que ofrecía tres respuestas sacadas de si Wordfence había
publicado parche, y esas tres no son el trabajo.

**Los pendientes de un sitio se filtran y se paginan**, con los mismos tres
desplegables y de quince en quince. Antes eran los 23 módulos seguidos.

**Segunda pasada, la misma tarde.** «Mejor, pero todavía sigue horrible.»
Cuatro cosas más, y las tres primeras valen para cualquier pantalla:

- **Un aviso es una notita de una línea, no un comunicado.** El de «lo que
  estás viendo no es de hoy» eran cinco párrafos, una lista con viñetas y dos
  bloques de código dentro de un recuadro rojo a toda pantalla, antes de
  enseñar un solo dato. Ahora es una línea: el hecho, la fecha, la burbuja
  «i» con el porqué, y la acción al final. Y **la receta de WP-Cron y Plesk se
  borró entera**: «eso creo que a nadie le interesa, la verdad». Era una
  instrucción de instalación que se hace una vez en la vida del panel, y
  estaba saliendo todos los días en cuatro pantallas. Sigue escrita donde se
  necesita: en Ajustes y en la documentación.
- **Las tarjetas de cabecera no siempre tienen que ser cajas grandes.** En la
  ficha de un sitio funcionan. Encima de una barra de seis filtros, no: ahí el
  número y el rótulo van **a la par, en una filita** y más pequeños
  (`.swpk-tarjetas-fila`). Dicen lo mismo en un tercio del alto.
- **El hueco de una fila va en el medio, no a la derecha.** Las dos últimas
  columnas de una fila que se despliega llevan **ancho fijo**, no `fr`. Con
  `minmax(190px, 0.9fr)` la última crecía con la pantalla: el texto empezaba
  en el borde izquierdo de una columna muy ancha, quedaba un hueco grande en
  la esquina derecha, y a la vez un sitio de nombre largo llegaba a tocar la
  etiqueta de gravedad. Con las dos fijas, el aire cae entre el nombre y las
  etiquetas, que es donde se necesita.
- Y el nombre del botón en los textos tiene que ser el de verdad: decían
  «Correr ahora» y se llama «Revisar los sitios ahora» desde hace semanas.

**Y dos cosas de la hoja de estilos que valen para todo el panel:**

- La barra de filtros se alinea **por abajo** (`align-items: flex-end`). Con
  seis filtros alguna etiqueta se parte en dos renglones, y alineando por
  arriba su desplegable se descolgaba del resto de la fila.
- Por debajo de 1500 px cada desplegable tiene **155 px como mínimo** y la
  barra se parte en dos renglones. Con seis filtros a 1280 px cada uno se
  quedaba en 124 px, y «Todos los sitios» y «Todos los módulos» se recortaban
  los dos a «Todos los …»: un desplegable que no se puede leer no filtra nada,
  y la cuenta que va dentro —que es la razón de que sean desplegables— no se
  veía. Dos renglones de desplegables no son lo que se pidió quitar; lo que se
  pidió quitar eran cuatro renglones de pastillas.

Y una pieza concreta pendiente, de las normas de un sitio: **el botón
«arreglarlo en Kinsta»** para las que se tocan en MyKinsta y no en el sitio
—el HTTPS forzado, las redirecciones, la protección de bots—, que abra la
pantalla exacta. Hay que **comprobar cada dirección a mano** antes de
escribirla: así se arregló «Abrir en Kinsta», que llevaba a la lista de los
109 porque la ruta inventada no existía.

---

## 9 ter. Lo que se hizo con Caídas (0.19.4)

El diagnóstico de arriba —«la pantalla está bien, lo que satura es lo que
enseña de golpe»— era exacto. Medido antes de tocar nada: **4.809 px de alto y
la tabla empezando en el píxel 893**. La primera pantalla del portátil no
enseñaba ni un sitio. Después: **2.672 px y la lista en el 477**.

Nada nuevo en el método; es el mismo de §9 bis aplicado a lo que había:

- **Dos avisos de tres párrafos** —el monitor parado y la vuelta que salió por
  la IP bloqueada— pasan a una línea con su burbuja.
- **El veredicto sube al subtítulo de la cabecera.** Era una caja propia con
  un `<h2>` y dos párrafos, encima de otra caja que decía lo mismo con una
  dona. El subtítulo existe para esto. La mecánica —los dos intentos, el
  sondeo cada cinco minutos mientras algo esté caído— baja a la burbuja.
- **Fuera la dona**: 230 px de caja para cuatro números de los que tres suelen
  ser cero, y sin poder pulsarlos. En su lugar, la filita de tarjetas, y cada
  una filtra la lista. Las dos primeras salen siempre aunque sean cero —un
  cero en «no responden» es la buena noticia que se viene a ver—; las otras
  dos solo si hay algo.
- **La tabla pasa a filas que se despliegan**, con las mismas clases. El
  renglón baja de 140 px a 69.
- **A un sitio que responde bien no se le explica nada.** La celda «Ahora»
  llevaba un «Qué significa» plegable en TODAS las filas, y el de un 200 decía
  «el panel no tiene una traducción para esta respuesta»: ruido con forma de
  dato. Ahora el análisis solo aparece donde hay algo que analizar.
- **Y por eso la columna de la derecha queda vacía casi siempre**, que es lo
  que se busca: las filas con problema —que van primero— son las únicas con
  texto a la derecha y saltan solas. El día que no hay ninguna, la rejilla
  pasa a dos columnas y el estado se pega a la derecha, **decidido por página
  entera y nunca fila por fila**.
- **El historial se pagina**, de diez en diez, con su propio parámetro `ph`:
  con `p` para las dos listas, pasar de página en una saltaba también la otra.

---

## 10. Cómo se da por buena una pantalla

Dos cosas distintas, y se dicen por separado:

- **«Que no reviente»**: pedir el HTML con parámetros reales y buscar
  `Fatal`, `Warning`, `Notice`, `Deprecated`.
- **«Que se vea»**: medirlo en el navegador. Y **leer `window.innerWidth`
  antes de creerle a cualquier medida** — dos hallazgos de desborde
  resultaron ser el navegador colapsado a 253 px.

Y la regla que viene de tres veces en que Fer tenía razón y yo había dicho
«ya está»: **cuando ella dice que algo no aparece, lo que manda es la
pantalla, no el código.** Las tres veces la causa era mía.
