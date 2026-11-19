# 03 · De dónde salen los datos

Cuatro fuentes, y cada una sabe cosas que las otras no:

| Fuente | Qué aporta | Qué **no** puede saber |
|---|---|---|
| **El hosting** (Kinsta) | Sitios, etiquetas, plugins, temas, versiones, un sí/no de vulnerable | Los usuarios de WordPress. Por qué una falla es grave |
| **Wordfence** | Qué es cada vulnerabilidad, qué tan grave, qué versión la arregla | Qué hay instalado en tus sitios |
| **El agente** | Usuarios, dos pasos, correo, y las normas que solo se ven desde dentro | Nada del hosting |
| **El monitor** | Si el sitio responde ahora mismo | Por qué no responde |

Ninguna sirve sola. El valor del panel está en el cruce.

---

## El hosting, detrás de una interfaz

Todo lo que el panel sabe de los sitios entra por `SWPK_Fuente`
(`kinsta/interface-fuente.php`). Ocho métodos:

```php
nombre()      // "Kinsta", para las pantallas
proveedor()   // "Kinsta", a secas
es_demo()     // ¿datos reales o inventados?
sitios()      // sitios con etiquetas y entornos
plugins()     // plugins de TODA la empresa, en una consulta
temas()       // lo mismo con temas
frescura()    // cuándo miró el proveedor cada sitio
probar()      // ¿responde?
```

La fuente activa la devuelve `swpk_fuente()`. Hay dos implementaciones y el
filtro `swpk_fuente` para meter una tercera.

### `SWPK_Fuente_Api` — Kinsta de verdad

Diseñada alrededor del **límite de 120 peticiones por minuto por empresa**. Por
eso usa los endpoints de **nivel empresa**, no los de sitio: el inventario
completo son tres llamadas al día en total, en vez de dos por sitio — que con
108 sitios serían 216 y chocarían con el límite.

Si vas a agregar una consulta, piénsalo en esos términos. Una llamada por sitio
dentro de un bucle de 108 es la forma más rápida de que Kinsta empiece a
rechazar peticiones y el panel muestre datos a medias.

### `SWPK_Fuente_Demo` — datos inventados

Existe porque todavía no hay llave de Kinsta, y esperarla para empezar habría
costado semanas. Con esta fuente el panel funciona completo —inventario,
vulnerabilidades, avisos, bitácora— sin tocar Kinsta ni un sitio de cliente.

Los identificadores de vulnerabilidad llevan `DEMO-` a propósito: no son
reales, y el panel pinta una tira amarilla arriba para que nadie confunda una
demostración con la realidad.

El panel entra en modo de prueba solo —`SWPK_Ajustes::en_modo_demo()`— si el
modo está en `demo` o si falta la llave o el `company_id`.

### `SWPK_Http` — solo GET

Una clase, un método: `get`. No hay en todo el repositorio una llamada que
cree, borre, reinicie o actualice nada en Kinsta.

Esto es **la** garantía de solo lectura, y está en el código porque no puede
estar en otro sitio: Kinsta no vende llaves de solo lectura. Al crear una llave
solo se elige nombre y caducidad —no hay alcances— y la llave hereda los
permisos de quien la creó. Tampoco existe un rol de MyKinsta que vea los sitios
sin poder tocarlos.

**Si agregas un POST aquí, rompes la promesa principal del proyecto.** Si
alguna vez hace falta escribir en el hosting, eso es una conversación con el
jefe y un cambio de alcance, no un método nuevo.

---

## Wordfence: el porqué

Kinsta dice, por cada plugin, `is_version_vulnerable: true`. Nada más. No dice
cuál es la falla, ni qué tan grave, ni qué versión la arregla, ni qué le puede
pasar al cliente.

El feed de Wordfence Intelligence aporta exactamente eso, y es lo que permite
que el panel entregue una frase que una asesora puede mandar en vez de una
tabla de CVE.

- **Se descarga una vez al día.** El límite está escrito en el código. Pedirlo
  dos veces seguidas devuelve un 429 y el panel se echa atrás solo, duplicando
  la espera; insistir puede hacer que suspendan la llave.
- **Se guardan tres fechas, no una**, y es lo que permite no mentir:
  `comprobado` (la última vez que preguntamos y contestó), `ultimo_cambio` (la
  última vez que lo que contestó era distinto) y `mas_nueva` (la vulnerabilidad
  más reciente de la lista). Con una sola fecha, «Wordfence no ha publicado
  nada en tres días» —una buena noticia— se veía igual que «llevamos tres días
  sin comprobar», que es un fallo. `SWPK_Wordfence::como_va()` las distingue en
  una frase.
- Se filtra a los slugs que de verdad tenemos instalados: el feed completo es
  enorme y el 99% no nos aplica.
- `SWPK_Wordfence::atribucion()` pinta el crédito. **No lo quites**: el feed se
  usa bajo esa condición.

---

## El agente: lo que solo se ve desde dentro

Ver [04 · El agente](04-el-agente.md) para el detalle. En resumen: cada sitio
manda un reporte firmado una vez al día y en el momento en que cambia quién
tiene control, y desde noviembre pregunta cada cinco minutos si le dejaron
alguna orden.

**Que el reporte DEJE de llegar también es información.** Un sitio que lleva 48
horas sin reportar aparece marcado: un sitio caído, o uno donde alguien
desactivó el agente, se delata solo. Eso vive en `SWPK_Receptor::callados()`.

---

## La sonda: lo que ve un desconocido desde internet

`SWPK_Sonda`, cada día a las 5:30. Son unas cuarenta peticiones sin credenciales
por sitio —GET en su mayoría, un POST a `xmlrpc.php` pidiendo la lista de
métodos y un TRACE, ninguno de los cuales cambia nada— y de ahí salen 24 normas.

No es un escáner de vulnerabilidades ni una prueba de penetración: es lo mismo
que haría cualquiera desde un navegador. No se intenta entrar, no se prueban
contraseñas, no se envía ningún formulario.

**Las peticiones van intercaladas entre todos los sitios, no sitio por sitio.**
Esto no es un detalle de rendimiento: lanzadas en bloque, la gestión de bots del
hosting empezaría a cortar a media corrida, y lo peligroso es que todo lo que
viniera después se vería como «bloqueado» sin que nada lo avisara. Intercaladas,
cada sitio recibe una petición cada varios segundos.

Medido: 4,4 segundos por sitio con quince en paralelo, unos ocho minutos para
los 108.

Las tres reglas que la hacen utilizable —no concluir por el código HTTP, no
confundir «bloqueado» con «aprobado», y la sonda de control por sitio— están
explicadas en [06 · Normas de seguridad](06-normas-de-seguridad.md).

## La revisión de la plataforma: lo que el hosting sabe

`SWPK_Revision`, cada día a las 4:30. Trece lecturas por sitio de cosas que solo
el hosting sabe: protección contra bots, respaldos, HTTPS forzado, cuentas SFTP
extra, reglas de redirección, IP denegadas, visitas, códigos de respuesta y el
registro de accesos.

Vive aparte de `SWPK_Fuente` a propósito. La fuente es la abstracción que
permite que mañana haya otro proveedor sin tocar nada de lo que está encima;
estos trece endpoints son de un proveedor concreto, y meterlos en la interfaz
obligaría al siguiente a implementar trece métodos que no tiene. Se apaga sola
cuando la fuente no es la que corresponde.

Medido contra la cuenta real: 13,9 segundos por sitio, unos 25 minutos para los
108. No se puede apurar: el límite del proveedor son 120 peticiones por minuto y
`SWPK_Http` se guarda en 100.

**Por qué cada una tiene su propio horario y no van dentro de la corrida
diaria:** dos tareas largas pegadas es una tarea que el límite de tiempo de PHP
mata a la mitad, y entonces se pierden las dos.

## El monitor: ¿responde o no?

`SWPK_Monitor` sondea una vez por hora. El panel vigila por su cuenta en vez
de esperar a Kinsta por una razón simple: **un aviso que depende de Kinsta no
sirve para enterarse de que Kinsta se cayó.**

No avisa al primer fallo. Al sitio que no contesta se le pregunta otra vez ocho
segundos después, dentro de la misma corrida, y solo cuenta si falla las dos
veces. Antes la segunda opinión llegaba en el sondeo siguiente; con el sondeo
cada hora eso habría significado avisar a las dos horas.

Por qué una hora y no cinco minutos: el cron de WordPress no es un reloj, solo
corre cuando alguien visita el sitio, y en Pantheon —donde vive el panel— ni
eso: viene con `DISABLE_WP_CRON` puesto y la plataforma ejecuta el cron una vez
por hora. Se podía montar algo aparte para ir más rápido y se decidió que no.
Lo que no se podía era seguir prometiendo cinco minutos.

---

## La frescura: de cuándo es cada dato

`SWPK_Frescura` existe porque **el panel no vigila nada en vivo**. Le pregunta
al hosting una vez al día, se baja el feed una vez al día, y sondea cada cinco
minutos. Lo que se ve en pantalla es siempre una foto.

Un panel que muestra una foto de hace tres días sin decirlo está mintiendo por
omisión, y es peor que no tener panel: alguien toma una decisión creyendo que
mira el presente.

Por eso:

- **Toda pantalla lleva su sello** — `SWPK_Frescura::sello()`.
- **Toda respuesta de la API lleva `frescura`, `generado_en` y `zona`.** Quien
  consuma la API meses después tiene que poder saber de cuándo es el dato.
- **Cada fila lleva su propia fecha** cuando las fechas difieren: el hosting no
  escanea los 108 sitios el mismo día, y cada norma se midió cuando ese sitio
  reportó.
- **Si el dato está viejo, se avisa arriba** — `SWPK_Frescura::aviso()`.

Las horas son hora de Guatemala, no UTC. `SWPK_Frescura::fmt()` y `fecha()` ya
lo resuelven: úsalas en vez de `date()` o `gmdate()`.

---

## Y la regla que gobierna todo esto

**No se dice «está bien» cuando lo que pasa es «no lo sé».**

Aparece en todas partes y conviene reconocerla para no borrarla sin querer:

| Dónde | Cómo se ve |
|---|---|
| `SWPK_Riesgo::SIN_DATOS` | Un sitio sin inventario no sale como sano |
| `SWPK_Normas::SIN_DATO` | Un sitio sin agente sale «sin medir», no «cumple» |
| `'no_se_pudo'` en la prueba de subidas | Si el archivo no se pudo escribir, no se inventa un resultado |
| El aviso del botón «analizar ahora» | «El hosting no devolvió la lista — no es que el sitio esté limpio» |
| La ficha de un sitio sin datos | «Aquí no hay nada que mostrar, y eso no es una buena noticia» |

Si estás simplificando algo y te sale un `?:` que convierte un dato ausente en
`false`, acabas de romper esto.
