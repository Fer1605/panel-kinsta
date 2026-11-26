# El agente dentro del tema — qué cambió en 2.6.1

**Para:** quien revisa antes de unir esto a su rama.
**Fecha:** 26 de noviembre de 2026.
**Versión del tema:** 2.6.1 → **2.6.2**.
**Versión del agente que declara el tema:** `0.6.4-tema` (el número no cambia:
ya decía eso, y ahí está el problema).

Este archivo existe para que no haya que leer el diff entero: dice qué se
tocó, por qué, y qué se probó.

---

## Lo que había que resolver

El agente ya está dentro del tema desde el PR #1, unido el 19 de noviembre.
Pero **todavía no se ha instalado en ningún sitio**, así que nadie lo había
visto correr desde aquí. Se activó el tema en un sitio de prueba, se apagó el
plugin suelto y se miró qué pasaba. Salieron dos cosas.

Y una advertencia antes de empezar, porque cambia cómo leer lo de abajo: la
rama de hoy **declara `0.6.4-tema`, pero el código del agente que lleva dentro
es el de 0.6.3**. El número se subió sin traer los archivos. Por eso el fallo
de las tiendas, que el plugin arregló el 23 de noviembre, sigue vivo aquí
aunque la versión diga que no. Esto es justamente lo que esta entrega corrige:
ahora el número y el código coinciden.

---

## 1. La medición de la tienda reventaba

### Qué pasaba

Al armar el reporte, el agente cuenta los pedidos pagados. Para eso mira la
tabla `wc_orders` de WooCommerce. El tema comprobaba que **la tabla**
existiera, y daba por hecho que dentro estaba la columna `date_paid_gmt`.

No siempre está. WooCommerce renombró esa columna por el camino, así que una
tienda que encendió HPOS hace tiempo puede tener la tabla con un esquema
viejo, sin esa columna. Entonces la consulta muere con «Unknown column», y el
error **sale impreso en medio del reporte**.

Esto no es una hipótesis: el 23 de noviembre pasó en dos tiendas al instalar
el agente, y por eso el plugin ya lo tenía arreglado. La copia del tema se
quedó con la versión de antes.

Reproducido tal cual en el sitio de prueba antes de arreglarlo:

```
SQLSTATE[HY000]: no such column: date_paid_gmt
  class-tienda.php(220): SWA_Tienda::pedidos_pagados()
  class-reporte.php(161): SWA_Tienda::medir(true)
```

### Qué se hizo

Se comprueba la **columna**, no solo la tabla. Sin la columna se cae al conteo
por entradas, que funciona en cualquier versión de WooCommerce.

### De dónde salió

Es el archivo `class-tienda.php` del plugin, copiado tal cual. No se escribió
código nuevo: el arreglo ya existía, solo no había llegado hasta aquí.

Al revisar esto se vio que **nueve de los trece archivos del agente estaban
atrasados respecto del plugin**, no solo ese. Los nueve se pusieron al día de
una vez, aunque el que rompía era este.

---

## 2. El interruptor de «no reportar al panel» no apagaba de verdad

### Qué pasaba

El interruptor que se pidió —para los sitios que no están en el servidor del
panel— estaba puesto en los **relojes**: apagado, se quitaban el reporte
diario y la pregunta de cada cinco minutos.

Pero los relojes no son la única puerta. El agente también llama al panel
**en el momento en que cambia quién tiene control del sitio** (eso es lo que
hace `SWA_Vigilante`, y es justo lo que hay que detectar cuando un malware se
crea un administrador), y además hay un botón «Reportar ahora» en la pantalla.

Esas dos puertas no miraban el interruptor. Resultado: un sitio con el panel
apagado **seguía llamando al panel** en cuanto alguien creaba un usuario.
Medido en el sitio de prueba:

```
interruptor: APAGADO
enviar() => true        ← mandó igual
último reporte: cambió  ← se registró en el panel
```

Lo contrario de lo que promete el interruptor.

### Qué se hizo

El candado se movió **dentro de `SWA_Reporte::enviar()`**, que es por donde
pasan todas las puertas. Ahora da igual quién llame: con el interruptor
apagado devuelve `WP_Error( 'swa_panel_apagado' )` y no sale nada del sitio.

Los relojes se siguen quitando, para no dejar trabajo programado que no sirve.

### Qué sigue funcionando con el panel apagado

El código por correo al entrar, el silencio de los avisos de WordPress, y
todo lo demás del tema. El interruptor apaga **solo** la conversación con el
panel.

---

## Los archivos que se tocaron

| Archivo | Qué |
|---|---|
| `inc/agente.php` | Sin cambios de comportamiento. Ya declaraba `0.6.4-tema`; ahora es verdad. |
| `inc/agente/class-tienda.php` | Copia exacta del plugin. Trae el arreglo del 23 de noviembre. |
| `inc/agente/class-reporte.php` | Copia del plugin + el candado dentro de `enviar()`. |
| `inc/agente/class-ordenes.php` | Copia del plugin + quitar el reloj con el panel apagado. |
| `inc/agente/class-ajustes.php` | Copia del plugin + el ajuste `panel_activo`. |
| `inc/agente/class-correo.php` `class-dos-factores.php` `class-firma.php` `class-fondo.php` `class-formularios.php` `class-normas.php` `class-silencio.php` `class-vigilante.php` | Copia exacta del plugin, puestas al día. |
| `inc/agente/class-admin.php` | **Sin tocar.** Es la pantalla con el diseño de este tema. |
| `inc/README.md` | La regla de mantenimiento, corregida: ahora son cuatro archivos distintos, no uno. |
| `style.css` `functions.php` `readme.txt` | Versión 2.6.2. |

**Nada fuera de `inc/agente/` cambió de comportamiento.** Ni el login, ni las
reglas de acceso, ni los botones flotantes, ni los shortcodes, ni la
seguridad de formularios.

---

## Qué se probó, y con qué resultado

En el sitio de prueba local, con el plugin suelto **desactivado** y el tema
activo:

| Prueba | Resultado |
|---|---|
| El tema carga su agente y no el del plugin | `SWA_VERSION = 0.6.4-tema` |
| Los dos relojes quedan puestos sin gancho de activación | reporte diario y pregunta de 5 min, puestos |
| Reporte al panel | `enviar() => true`, **sin un solo error impreso** |
| El panel lo recibió | el panel muestra el sitio con versión `0.6.4-tema` y la hora del reporte |
| Pregunta de órdenes | `preguntar() => true` |
| Medición de la tienda | devuelve los 13 datos, sin error |
| Medición de normas | 3 normas medidas |
| La pantalla se registra | «Conexión al panel», dentro de Apariencia |
| Los seis botones de la pantalla | los seis conectados |
| La pantalla se dibuja | 9 971 bytes, con el interruptor visible |
| Interruptor apagado: reloj, vigilante y botón | los tres frenados |
| Interruptor encendido otra vez | los tres vuelven a funcionar |
| Portada del sitio | HTTP 200, sin avisos ni errores |
| `/wp-login.php` | redirige, como debe |
| `/panel/admin/` | HTTP 200 |
| Sintaxis de los 13 archivos del agente | compilan |

---

## Lo que NO se probó, y hay que mirar al instalarlo en el primer sitio

1. **Una tienda de verdad con el esquema viejo de HPOS.** El arreglo se probó
   contra una base sin la columna, que es el mismo caso, pero no contra una de
   las dos tiendas donde reventó el 23.
2. **El cambio de login.** Al activar este tema, `/wp-login.php` deja de
   servir y el acceso pasa a `/panel/admin/`. En un sitio que hoy entra por
   `/wp-login.php` eso se nota de inmediato. Hay que avisar antes, y guardar
   los enlaces permanentes después de subir los archivos.
3. **Un sitio que tenga el plugin suelto todavía.** El tema se aparta solo
   —mira la constante `SWA_VERSION`, no la lista de plugins— pero conviene
   verlo una vez de verdad antes de confiar en los 95 sitios.

---

## Dos cosas que el tema hace distinto del plugin, a propósito

1. **No hay gancho de activación.** Un plugin tiene un momento de activación
   donde programar los relojes; un tema no. Aquí se programan en dos sitios:
   al cambiar de tema, y en cada carga dentro del `init()` de cada clase, que
   ya comprueba si el reloj está puesto. Eso es lo que recupera un sitio donde
   el tema se instaló copiando archivos, sin pasar por el cambio de tema.
2. **El interruptor `panel_activo`.** No existe en el plugin, porque el plugin
   solo se instala donde se quiere que reporte. El tema va en sitios que no
   están en este servidor, y ahí no tiene a quién reportarle.
