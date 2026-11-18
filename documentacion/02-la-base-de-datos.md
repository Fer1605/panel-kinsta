# 02 · La base de datos

Once tablas, todas con el prefijo de WordPress más `swpk_`. Se crean y se
migran en `class-tablas.php` con `dbDelta`.

## Cómo funciona la migración

`SWPK_Tablas::VERSION` es un número. En cada carga, `revisar_version()` lo
compara con la opción `swpk_version_tablas` y, si no coinciden, vuelve a correr
`crear()` entero.

**Para cambiar el esquema:** edita el `CREATE TABLE` que toque y **sube
`VERSION` en uno**. Si no lo subes, tu columna nueva no existe en ninguna
instalación que ya esté corriendo, y el código que la usa falla en silencio.

`dbDelta` agrega columnas e índices que falten, pero **no borra ni renombra**
nada. Quitar una columna hay que hacerlo a mano con una migración aparte; en la
práctica, hasta hoy nunca ha hecho falta.

---

## `swpk_sitios` — el inventario consolidado

Una fila por sitio. Es la tabla de la que cuelga todo lo demás: el `sitio_id`
que usa aquí es la llave con la que el resto del panel identifica un sitio.

| Columna | Qué guarda |
|---|---|
| `sitio_id` | El identificador del hosting. **Único.** Es la llave de todo el panel |
| `entorno_id` | El entorno de producción dentro de ese sitio |
| `nombre`, `nombre_visible` | Como lo llama el hosting, y como lo llamamos nosotros |
| `estado` | Lo que reporta el hosting |
| `dominio` | El dominio principal. Se usa para emparejar al agente por su URL |
| `etiquetas` | Las etiquetas crudas del hosting, tal cual llegan |
| `tag` | El grupo ya traducido: `desarrollo`, `auto_update`, `no_update`, `sin_etiqueta` |
| `version_wp`, `version_php` | Lo que reporta el hosting |
| `inventario_en` | **Cuándo miró el hosting este sitio.** No cuándo corrió el panel |
| `asesora_id` | Qué asesora lo lleva (recorta lo que ve el perfil de ventas) |
| `contacto_cliente` | A quién se le avisa |
| `cobertura_agente` | Si tiene el agente instalado |
| `tiene_2fa`, `plugin_2fa`, `estado_2fa` | Qué se sabe de la verificación en dos pasos |
| `visto_en` | La última vez que el hosting lo listó |

**`inventario_en` merece su párrafo.** No es la fecha de la corrida: es la
fecha en que el hosting escaneó *ese* sitio. Kinsta no vuelve a mirar los 108
el mismo día, así que un hallazgo puede venir de un dato de hace una semana. El
panel lo dice fila por fila en vez de poner una sola fecha arriba, que sería
fechar datos viejos como si fueran de hoy.

---

## `swpk_fotos` — el inventario diario

Una fila por **sitio × módulo × día**. Esto es lo que permite decir «este mes
se actualizó seis veces»: el hosting no guarda historial consultable, así que
la historia la construye el panel guardando una foto diaria y comparándolas.

| Columna | Qué guarda |
|---|---|
| `fecha`, `sitio_id`, `tipo`, `slug` | **Clave única.** `tipo` es `plugin` o `theme` |
| `titulo`, `version`, `version_disponible` | El módulo y sus versiones |
| `hay_actualizacion`, `es_vulnerable` | Lo que dice el hosting |
| `auto_update` | Si se actualiza solo |
| `activo` | Si está activo en el sitio |
| `escaneado_en` | Cuándo lo miró el hosting |

> El nombre de la tabla es `fotos` y el del método es `tomar_foto()`, pero en
> las pantallas y en los comentarios se habla de «inventario». Es una deuda de
> nombres conocida y pendiente: el concepto es el mismo.

---

## `swpk_eventos` — qué cambió

| Columna | Qué guarda |
|---|---|
| `ocurrio_en`, `sitio_id`, `tipo`, `severidad` | Cuándo, dónde, qué y qué tan grave |
| `slug` | El módulo, cuando lo hay. **También sirve de antirrepetido** |
| `titulo`, `detalle` | El texto y los datos |
| `notificado` | Si ya salió en un aviso |

**Un mismo cambio no se anota dos veces el mismo día.** Se compara por tipo,
sitio, módulo y día. Hizo falta porque el botón de «analizar este sitio ahora»
vuelve a comparar contra la foto anterior: pulsarlo dos veces registraba otra
vez los mismos cambios, y con 23 módulos eran 30 eventos donde había 10.

Las caídas quedan fuera de esa regla a propósito: no traen módulo, y un sitio
puede caerse y reponerse varias veces en un día, y cada vez es noticia.

---

## `swpk_vulnerabilidades` — la copia del feed de Wordfence

Lo que aporta el **porqué**. El hosting solo da un sí/no; esta tabla trae la
descripción, el CVSS, el CVE y qué versión lo arregla.

| Columna | Qué guarda |
|---|---|
| `wf_id`, `slug` | **Clave única.** El id de Wordfence y el módulo |
| `cve`, `cve_enlace` | La referencia pública |
| `cvss_score`, `cvss_rating`, `cvss_vector` | La gravedad, **tal como la publica Wordfence** |
| `informativa` | Las que Wordfence marca como no explotables |
| `descripcion`, `remediacion` | Qué es y cómo se arregla |
| `rangos`, `versiones_parcheadas` | Qué versiones están afectadas |

**La severidad no se inventa.** Se usa el rating de Wordfence tal cual, porque
inventar cortes propios es la forma más rápida de perder credibilidad cuando el
cliente pregunta de dónde salió el número.

---

## `swpk_bitacora` — la evidencia

Esto es el producto, no un accesorio. Contesta: *«desde tal fecha hasta esta
fecha, aquí está, se hicieron 10 notificaciones»*.

| Columna | Qué guarda |
|---|---|
| `enviado_en`, `tipo`, `sitio_id` | Cuándo, de qué y de qué sitio |
| `destinatarios`, `asunto`, `cuerpo` | A quién, con qué título y con qué texto exacto |
| `resultado`, `error` | Si salió, y si no, por qué |
| `disparado_por` | Quién lo mandó, si fue una persona |

Se guarda el cuerpo entero a propósito: una bitácora que dice «se mandó un
aviso» sin decir qué decía no sirve para defenderse de un reclamo.

---

## `swpk_accesos` — quién entró al panel

Entradas, salidas, intentos fallidos y llamadas a la API. El tercero no lo
pidió nadie y es el que de verdad sirve: una ráfaga de intentos fallidos es la
señal de que alguien está probando contraseñas contra el sitio desde donde se
consulta la información de **todos** los clientes.

---

## `swpk_monitor` — el sondeo de caídas

Una fila por sitio, con su estado, los milisegundos que tardó, cuántos fallos
seguidos lleva, desde cuándo está así, y las caídas y segundos caídos del mes.

**No se avisa al primer fallo.** Hacen falta dos sondeos fallidos seguidos: un
fallo suelto casi siempre es la red, no el sitio.

---

## `swpk_agentes` — los sitios que tienen el plugin

| Columna | Qué guarda |
|---|---|
| `sitio_url` | **Único.** La dirección con la que el plugin se presenta |
| `sitio_id` | El id del hosting, resuelto al registrarse |
| `token`, `secreto` | Suyos y solo suyos. Ver abajo |
| `version`, `registrado_en`, `ultimo_reporte` | Trazabilidad |
| `puede_enviar_correo`, `error_correo` | Si el sitio puede mandar correo |
| `dos_pasos`, `xmlrpc_abierto` | Estado de seguridad básico |

**Un secreto por sitio, no uno compartido.** Con uno compartido, un sitio
comprometido podría inventar reportes a nombre de cualquier otro —decir, por
ejemplo, que el sitio de otro cliente no tiene administradores nuevos—. Con uno
por sitio, lo que se filtre en un sitio solo sirve para ese sitio.

**La deuda conocida:** esta tabla y `swpk_usuarios` se llavean por `sitio_url`,
no por `sitio_id`, y el `sitio_id` se resuelve emparejando dominios. Si un
agente reporta desde una dirección que no coincide con ningún dominio del
inventario, no se puede cruzar. Desde noviembre eso ya no desaparece en
silencio: queda como evento `agente_sin_sitio`.

---

## `swpk_usuarios` — quién tiene control de cada sitio

Una fila por **sitio × usuario**, solo de los roles vigilados (administrator,
editor, shop_manager). Un sitio con 3.000 clientes de tienda no manda 3.000
filas: lo que se vigila son los que tienen control.

Lleva `dos_pasos` por usuario, que es lo que alimenta la norma de «ningún
administrador sin verificación en dos pasos».

---

## `swpk_normas` — la lista de reglas

| Columna | Qué guarda |
|---|---|
| `clave` | **Única.** El identificador que usan los comprobadores y la API |
| `nombre`, `porque` | Qué dice la norma y por qué importa, en palabras |
| `comprobador` | El método que la mide, o `declarativa` si la marca una persona |
| `aplica_a` | Grupos separados por coma. **Vacío quiere decir todos** |
| `severidad` | `alta`, `media`, `baja` |
| `arreglable` | Si el agente sabe cumplirla cuando se le ordena |
| `activa` | Si se mide |
| `de_fabrica` | Las que trae el panel. Se apagan, no se borran |
| `orden` | Para ordenarlas en pantalla |

El catálogo de fábrica vive en `SWPK_Normas::catalogo()`. Al subir
`VERSION_CATALOGO` se vuelven a sembrar: **se refresca el texto, nunca el
estado**. Si alguien apagó una norma, sigue apagada.

---

## `swpk_cumplimiento` — el veredicto de cada cruce

Una fila por **sitio × norma**. No es un cálculo al vuelo: el dato no está en
el panel, lo trae el agente una vez al día, así que hay que guardarlo **con la
fecha en que se midió**.

| Columna | Qué guarda |
|---|---|
| `sitio_id`, `norma` | **Clave única** |
| `estado` | `cumple`, `falla`, `sin_dato`, `no_aplica` |
| `detalle` | La explicación en palabras, ya redactada |
| `medido_en` | Cuándo. Va pegado al dato, no arriba de la pantalla |
| `revisado_por`, `revisado_en` | Quién lo marcó a mano, para las declarativas |

`sin_dato` **no** es `falla`. Un sitio sin agente aparece «sin medir» en las
diez normas, y la pantalla lo dice con esas palabras.

---

## `swpk_ordenes` — la cola hacia los agentes

| Columna | Qué guarda |
|---|---|
| `sitio_id`, `sitio_url` | A quién va |
| `tipo` | Uno de los seis verbos de `SWPK_Ordenes::tipos()` |
| `norma` | Qué norma viene a arreglar, si viene de una |
| `carga` | Los parámetros, en JSON. **Nunca una contraseña** |
| `estado` | `pendiente`, `entregada`, `hecha`, `fallo`, `vencida`, `cancelada` |
| `resultado` | Lo que contestó el sitio, en palabras |
| `lote` | Agrupa un reseteo masivo |
| `pedida_por`, `pedida_en`, `entregada_en`, `resuelta_en` | El rastro completo |

**En `carga` no va ninguna contraseña.** La del reseteo masivo vive en un
transient con dos horas de vida y se inyecta en el momento de entregar. Si se
guardara aquí, el panel sería el único lugar del mundo con las contraseñas de
administrador de todos los sitios en claro — que es justo el problema que el
reseteo masivo viene a resolver.

---

## Consultar la base a mano

Las tablas se nombran siempre con `SWPK_Tablas::tabla( 'sitios' )`, nunca
escribiendo el prefijo. En un sitio de Studio:

```bash
studio wp eval 'global $wpdb; print_r( $wpdb->get_results( "SELECT * FROM " . SWPK_Tablas::tabla( "normas" ) ) );'
```

`studio wp db query` **no funciona** en estas instalaciones: no encuentra el
binario de mysql. Usa `wp eval` con `$wpdb`.

## Lo que se agregó en la versión 12

**`sitios.tipo_sitio`** y **`sitios.tipo_motivo`** — qué es cada sitio (tienda,
cotizador, informativo o sin datos) y **por qué** quedó ahí. El motivo es lo que
hace la clasificación discutible sin tener que rehacerla.

Ojo con `tipo_motivo`: son 191 caracteres y `SWPK_Tipos::clasificar()` recorta
antes de escribir. No es prudencia de más — con MySQL en modo estricto un valor
más largo **rechaza el UPDATE entero, en silencio**, y ya pasó: veintiún sitios
se quedaron sin clasificar mientras la función devolvía el valor correcto.

**`normas.aplica_tipo`** — a qué tipos de sitio aplica una norma. Vacío quiere
decir todos. Es un eje **independiente** de `aplica_a`, que son las etiquetas
del contrato de mantenimiento: un sitio puede ser `auto_update` y tienda a la
vez.

### Lo que NO está en una tabla, y por qué

Tres lecturas diarias viven en transients de 25 horas y no en tablas:
`swpk_sonda_*`, `swpk_revision_*` y `swpk_tienda_*`.

El motivo es que son **fotos del día**, no historial. Si la sonda deja de correr
o el agente deja de reportar, el dato caduca solo en vez de quedarse en una
tabla mintiendo con una fecha vieja. Lo que sí es historial —los eventos, la
bitácora, el cumplimiento— sí tiene tabla.
