# 05 · Las pantallas

Viven en `panel-kinsta/includes/admin/vistas/`. El menú se arma en
`SWPK_Admin::menu()` y cada pantalla vuelve a comprobar el permiso al abrirse:
esconder una opción del menú no es seguridad por sí sola, pero un menú con
cosas que no se pueden abrir es un menú confuso.

| Pantalla | Archivo | Qué pregunta contesta |
|---|---|---|
| **Resumen** | `resumen.php` | ¿Está todo bien o no? |
| **Sitios** | `sitios.php` | ¿Cómo está cada sitio? |
| **Vulnerabilidades** | `vulnerabilidades.php` | ¿Qué hay que parchar? |
| **Seguridad** | `seguridad*.php` | ¿Cumplimos nuestras propias normas? |
| **Caídas** | `caidas.php` | ¿Qué sitio no responde? |
| **Correos** | `correos.php` | ¿Qué avisos salen y a quién? |
| **Bitácora** | `bitacora*.php` | ¿Qué pasó, y qué le avisamos a quién? |
| **Equipo** | `equipo.php` | ¿Quién entra al panel y con qué perfil? |
| **Accesos** | `accesos.php` | ¿Quién entró, cuándo, y quién lo intentó? |
| **Conectarse (API)** | `api.php` | ¿Cómo lo consulto desde fuera? |
| **Ajustes** | `ajustes.php` | La llave, el modo, las etiquetas, los dominios |
| *Ficha de sitio* | `sitio.php` | Todo lo de un sitio, y el mensaje listo para copiar |
| *Reporte* | `reporte.php` | El reporte imprimible |

Las dos últimas no están en el menú: se llega a ellas desde las listas, y
`SWPK_Admin::menu_abierto()` deja el menú del panel abierto para que nadie se
pierda.

---

## Resumen: la pantalla que se rehízo dos veces

Esta es la que más vale la pena entender, porque su historia es una lección.

Abría con ocho números —12, 82, 13, 26, 50, 94, 0, 3— y el jefe los rechazó
con todas sus letras:

> «el panel debe ser a nivel sistema darme la info rapidamente y no pequeños
> resumenes de numeros, piensalo que entro rapido a ver y rapido tendria que
> captar que esta bien y que esta mal»

Y no era la primera vez. En la reunión ya lo había dicho: *«Individual, mucha
información, pero no en paquete.»* **Dos veces, con meses de diferencia, pidió
lo mismo y no se recogió.**

Un contador obliga a dos pasos: interpretarlo, y después ir a buscar a qué
sitio se refiere. Lo que él quiere es leer el problema **con el nombre del
sitio puesto**, sin buscar nada.

Así que `SWPK_Estado` no cuenta: **ordena**. Devuelve la lista de lo que hay
que atender, de lo más grave a lo menos, con el sitio nombrado y la falla
explicada en palabras.

### Qué decide que algo sea urgente

El nivel de riesgo no alcanza solo. Lo que mueve una falla al primer lugar es
que **se pueda aprovechar desde internet, sin tener cuenta en el sitio**: eso
es lo que buscan los ataques automáticos, los que recorren internet probando
fallas una por una sin elegir a quién. Una falla grave que exige ser
administrador para explotarla es otra conversación.

### Decir también lo que está bien

Si no hay nada que atender, la pantalla lo dice con una frase. No se queda
vacía: una pantalla vacía se lee como «no cargó», no como «todo en orden».

### El rendimiento

`SWPK_Riesgo::nivel_del_sitio()` consulta el inventario y cruza el feed de
Wordfence módulo por módulo, 108 veces. `SWPK_Estado` guarda el recorrido en
caché cinco minutos y lo olvida al terminar la corrida diaria, para que
recargar la pantalla no vuelva a pagarlo y nunca se vea algo viejo sin aviso.

**Si cambias algo que afecte al veredicto, llama a `SWPK_Estado::olvidar()`.**
Las acciones de normas y órdenes ya lo hacen.

### El tope de diez por día

El Resumen lista por día. Con 72 filas la tabla medía 5.600 píxeles y había que
hacer scroll eterno. Ahora muestra hasta diez sitios tranquilos por día y
enlaza al resto con «Ver los N sitios restantes de este día →», que lleva a
Bitácora → Movimientos **ya filtrado por ese día**. Los sitios que hay que
mirar no entran en el tope: esos salen todos.

---

## Seguridad: tres pestañas, una entrada de menú

Son tres caras de lo mismo —la regla, quién la cumple, y qué se hizo— así que
van juntas y no ocupan tres líneas del menú.

- **Cómo estamos** (`seguridad-cumplimiento.php`) — el tablero. Abre **por
  norma**, con los sitios nombrados debajo de cada una, no con una cuadrícula
  de palomitas. La cuadrícula completa está al final, plegada, para una
  auditoría o una captura.
- **Las normas** (`seguridad-normas.php`) — agregar, cambiar, apagar, borrar.
- **Qué se pidió** (`seguridad-ordenes.php`) — la cola de órdenes y el reseteo
  masivo de contraseñas.

El detalle completo, en [06 · Normas de seguridad](06-normas-de-seguridad.md).

---

## Ficha de un sitio

Todo lo de un sitio en un lugar: sus hallazgos, sus usuarios, sus normas, sus
eventos, y **el mensaje para la asesora listo para copiar**.

Ese mensaje es más importante de lo que parece. El jefe no pidió un reporte
técnico: pidió *«algo que ellas puedan copiar y pegar en su cotización»*. Si el
panel entrega una tabla de CVE y versiones, la asesora tiene que traducirla, y
si tiene que traducirla no la usa. `SWPK_Textos` entrega el texto ya escrito.

Arriba siempre hay un botón para volver a donde se venía, **con el grupo y el
filtro que la persona había elegido** (`SWPK_Vista::url_de_regreso()`). Mandar
siempre al Resumen obligaría a volver a buscar el sitio que se acaba de mirar.

También está el botón **«Analizar este sitio ahora»**, que vuelve a leer un
solo sitio sin tocar a los otros 107. La corrida completa es destructiva por
diseño —resetea el inventario de todos— así que `analizar_sitio()` es una
versión acotada aparte, no la misma función con un parámetro.

---

## Lo que las pantallas deliberadamente NO hacen

- **No deciden.** Toda regla de negocio vive en una clase de lógica, para que
  la API pueda contestar lo mismo. Ver [01](01-como-esta-armado.md).
- **No escriben en Kinsta.** Nada en el panel lo hace.
- **No esconden la falta de datos.** Un sitio sin inventario dice «aquí no hay
  nada que mostrar, y eso no es una buena noticia», no una tabla vacía.
- **No muestran una fecha sola arriba** cuando las filas tienen fechas
  distintas. Cada fila lleva la suya.

---

## Estilos

Van todos en `SWPK_Admin::estilos()`, enganchado a `admin_head`. No hay hoja de
estilos aparte: el panel vive dentro del escritorio de WordPress y hereda su
diseño, y una hoja propia tendría que pelearse con él.

Dos criterios que conviene respetar:

- **La gravedad se dice con color Y con palabra.** El color solo no sirve a
  quien no distingue rojo de verde, y esto se imprime.
- **Las clases llevan prefijo `swpk-`.** Sin prefijo, un tema o un plugin del
  escritorio puede pisarlas.

Un ejemplo de por qué vale la pena mirar la especificidad: `.swpk-campo > span`
(0,1,1) le ganaba a `.swpk-fila` (0,1,0), así que la fila nunca era flex y el
campo salía cortado. Se resolvió con una clase propia, no subiendo la
especificidad de la otra.
