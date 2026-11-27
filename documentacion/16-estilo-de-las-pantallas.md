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
- Las franjas de color de «quién se mueve»: nosotros `#1c6dc7`, hosting
  `#9aa4b2`, cliente `#7a5cd6`, esperar `#b4690e`.

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

1. **Vulnerabilidades** — «todavía tiene como demasiada info». Es la primera.
2. **Caídas** — «tiene mucha información al entrar». Sí y no: la pantalla
   está bien, lo que satura es lo que enseña de golpe.
3. **Pendientes de un sitio** — mejoró mucho (filas que se despliegan), pero
   todavía acumula.
4. **Seguridad** — «creo que está más decente». La última.

Y una pieza concreta pendiente, de las normas de un sitio: **el botón
«arreglarlo en Kinsta»** para las que se tocan en MyKinsta y no en el sitio
—el HTTPS forzado, las redirecciones, la protección de bots—, que abra la
pantalla exacta. Hay que **comprobar cada dirección a mano** antes de
escribirla: así se arregló «Abrir en Kinsta», que llevaba a la lista de los
109 porque la ruta inventada no existía.

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
