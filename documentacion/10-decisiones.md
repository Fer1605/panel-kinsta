# 10 · Las decisiones

Este archivo existe para una cosa: que nadie —persona o inteligencia
artificial— «mejore» algo que está así a propósito.

Cada entrada dice **qué se decidió**, **por qué**, y **qué se rompe si se
cambia**. Si vas a tocar algo de aquí, está bien hacerlo: pero hazlo sabiendo
lo que estás deshaciendo.

---

## 1. El panel nunca escribe en Kinsta

**Qué.** `SWPK_Http` tiene un solo método, `get`. No existe en el repositorio
una llamada que cree, borre, reinicie o actualice nada en la cuenta de hosting.

**Por qué.** Kinsta no vende llaves de solo lectura: al crear una llave solo se
elige nombre y caducidad, no hay alcances, y la llave hereda los permisos de
quien la creó. Tampoco hay un rol de MyKinsta que vea sin poder tocar. La
garantía no puede estar en Kinsta, así que está en el código — y es auditable:
buscar `wp_remote_` devuelve solo GET.

**Si se cambia.** Se rompe la promesa principal del proyecto, la que está
escrita en la cabecera del plugin y en el documento que recibió el jefe. Esto
no es un refactor, es un cambio de alcance.

---

## 2. El sitio del cliente no abre ninguna puerta a internet

**Qué.** El sitio llama al panel, nunca al revés. No queda un endpoint
esperando peticiones.

**Por qué.** Un endpoint abierto en 94 sitios de cliente es el tipo de cosa que
se vuelve un problema dos años después, cuando nadie recuerda que existe.

**Cómo se respetó incluso cuando estorbó.** Cuando hizo falta que el panel le
ordenara algo a un sitio, la salida fácil era abrir una ruta en el agente. En
vez de eso se puso una **cola que el sitio consulta**. El precio es que una
orden no es inmediata, y ese precio se dice en pantalla en vez de esconderlo.

**Si se cambia.** No solo se rompe una promesa escrita: se multiplica por 94 la
superficie de ataque de la agencia.

---

## 3. No se dice «está bien» cuando lo que pasa es «no lo sé»

**Qué.** Un dato ausente nunca se muestra como un dato bueno.

**Dónde vive.** `SWPK_Riesgo::SIN_DATOS`, `SWPK_Normas::SIN_DATO`, el
`'no_se_pudo'` de la prueba de subidas, el aviso de frescura, el mensaje del
botón «analizar ahora», la ficha de un sitio sin inventario.

**Por qué.** Es la razón de ser de medio panel. Un panel que muestra un sitio
sin datos como sano hace que alguien tome una decisión creyendo que sabe algo
que no sabe.

**Si se cambia.** Es lo primero que se rompe al «simplificar»: un `?? false` o
un `?:` que convierte un dato ausente en un `false` parece limpieza y es una
mentira.

---

## 4. A los sitios del cliente se les mide, no se les toca

**Qué.** Los que llevan la etiqueta `no_update` los administra el cliente. El
panel los vigila y avisa, pero `SWPK_Ordenes::encolar()` se niega a mandarles
nada.

**Por qué.** Es la regla que ya estaba escrita: «no se le toca nada sin
pedirlo».

---

## 5. El Resumen no cuenta: ordena

**Qué.** La pantalla principal no abre con contadores. Abre con la lista de lo
que hay que atender, con el sitio nombrado.

**Por qué.** El jefe lo pidió dos veces, con meses de diferencia, y las dos
veces no se recogió. *«no pequeños resumenes de numeros»*, *«Individual, mucha
información, pero no en paquete.»*

**Si se cambia.** Se vuelve a la versión que él ya rechazó. Si hace falta un
contador, que sea después de la lista, no antes.

Lo mismo aplica al tablero de Seguridad: abre por norma con los sitios
nombrados, y la cuadrícula va plegada al final.

---

## 6. La severidad no se inventa

**Qué.** Se usa el rating que publica Wordfence (None, Low, Medium, High,
Critical) tal cual.

**Por qué.** Inventar cortes propios es la forma más rápida de perder
credibilidad cuando el cliente pregunta de dónde salió el número.

---

## 7. Lo que decide la urgencia no es solo la gravedad

**Qué.** Lo que mueve una falla al primer lugar del Resumen es que **se pueda
aprovechar desde internet, sin tener cuenta en el sitio**.

**Por qué.** Eso es lo que buscan los ataques automáticos, los que recorren
internet probando fallas sin elegir a quién. Una falla «crítica» que exige ser
administrador para explotarla es otra conversación, y ponerla arriba hace que
lo que de verdad urge se pierda entre ruido.

---

## 8. Dos plugins, no uno

**Qué.** El panel y el agente son plugins separados que no comparten código.

**Por qué.** La API de Kinsta no puede listar los usuarios de WordPress, y dar
SSH a cada proyecto se descartó. Si de todas formas hay que entrar una vez a
cada sitio, esa visita resuelve cuatro cosas.

---

## 9. Un secreto por sitio, no uno compartido

**Qué.** Cada agente tiene su propio token y su propio secreto.

**Por qué.** Con uno compartido, un sitio comprometido podría inventar reportes
a nombre de cualquier otro — decir que el sitio de otro cliente no tiene
administradores nuevos, por ejemplo. Con uno por sitio, lo que se filtre en un
sitio solo sirve para ese.

---

## 10. Nada se escribe por ausencia

**Qué.** En `SWPK_Receptor::reporte()`, si un dato no viene en el reporte, el
dato **no se toca**.

**Por qué.** El agente manda un reporte liviano cada cinco minutos, sin
usuarios ni normas. Sin esta regla, ese reporte apagaría en el panel la
verificación en dos pasos de un sitio que la tiene, y `procesar()` con la lista
vacía daría de baja a **todos** los usuarios del sitio y anotaría un evento por
cada uno.

**Se descubrió probándolo**, antes de que llegara a ningún sitio real.

**Si se cambia.** Un `?? 0` de aspecto inocente borra la vigilancia de usuarios
de los 94 sitios en cinco minutos.

---

## 11. Un cambio de contraseña nunca es un duplicado

**Qué.** `SWPK_Ordenes::encolar()` deduplica comparando tipo **y carga**, y las
órdenes `resetear_clave` ni siquiera pasan por ahí: pasan por sustitución.

**Por qué.** Comparar solo por tipo hacía que pedir el cambio de «pedro»
cuando ya había uno pendiente de «ana» devolviera la orden de ana y el panel
dijera «listo». Se pedía una cosa y pasaba otra. Y como la contraseña no viaja
en la carga, dos peticiones del mismo usuario se ven idénticas en la tabla
siendo dos contraseñas distintas.

**Si se cambia.** Vuelve un fallo que aplica la contraseña equivocada en
silencio y dice que todo salió bien.

---

## 12. La contraseña del reseteo masivo no se guarda

**Qué.** Vive en un transient con dos horas de vida, nunca en una tabla.

**Por qué.** Si se guardara, el panel sería el único lugar del mundo donde
están en claro las contraseñas de administrador de todos los sitios — que es
justo el problema que el reseteo viene a resolver.

**El precio, aceptado a propósito:** un sitio que no pregunta en dos horas
queda sin cambiar, y hay que volver a pedirlo.

---

## 13. El `.htaccess` se cambió por su efecto

**Qué.** La norma no pregunta si el archivo tiene la regla. Pregunta si el
servidor **ejecuta** un PHP subido a la carpeta de subidas, y lo comprueba
haciéndolo.

**Por qué.** En Kinsta no existe `.htaccess` — corre Nginx. Mirar el archivo
daría «no cumple» en los 108 sitios sin significar nada. Medir el efecto es más
trabajo y funciona igual en Nginx, Apache y Pantheon.

---

## 14. El honeypot es declarativo

**Qué.** Nadie lo comprueba automáticamente: alguien lo marca, y queda con su
nombre y su fecha.

**Por qué.** No hay forma genérica: depende del plugin de formularios de cada
sitio. Se descartó que el agente inyectara el honeypot él mismo — es lo más
completo y lo más invasivo, y puede romper formularios ajenos.

**Lo que sigue pendiente:** comprobar los dos o tres plugins de formularios que
de verdad usamos, que cubrirían la mayoría.

---

## 15. El editor de código lo cierra el agente, y se dice

**Qué.** El agente define `DISALLOW_FILE_EDIT` él mismo en cada carga, en vez
de escribir en `wp-config.php`. El reporte dice **quién** lo cerró.

**Por qué.** Un plugin que escribe en `wp-config.php` puede dejar el sitio del
cliente en blanco. El precio es que el cierre depende de que el agente siga
activo, y eso **se dice en pantalla** en vez de prometer algo que no es verdad.

---

## 16. Los procesos de la API van por POST

**Qué.** `POST /analizar`, no `GET /analizar`.

**Por qué.** La corrida completa son más de 200 peticiones al hosting. Un GET
se repite solo —un navegador, una caché, un enlace abierto dos veces— y eso no
debe poder disparar 200 peticiones sin querer.

---

## 17. La API no crea órdenes

**Qué.** `/ordenes` es solo lectura.

**Por qué.** Una orden cambia cosas en el sitio de un cliente. Eso se pide
desde la pantalla que dice en cuántos sitios va a pasar y pide confirmación.
Por la API, un script podría cambiar 94 sitios sin que nadie lo vea.

---

## 18. Las llaves no son personas

**Qué.** Cuatro filtros las sacan de la lista de usuarios, impiden que nadie
las edite (ni un administrador), les cierran el formulario de entrada y les
quitan la recuperación de contraseña.

**Por qué.** Lo encontró Fer: una llave que aparece como usuario es una cuenta
que cualquier administrador puede secuestrar.

**El detalle técnico que no se puede cambiar a la ligera:** el bloqueo de
entrada va en `wp_authenticate_user`, **no** en `authenticate`. Moverlo a
`authenticate` bloquearía también las contraseñas de aplicación y dejaría la
llave inservible.

---

## 19. Los correos arrancan en modo de prueba

**Qué.** `SWPK_Correos::modo()` devuelve prueba por defecto.

**Por qué.** Un panel que manda correos a 108 clientes la primera vez que
alguien pulsa algo por curiosidad es un panel que se desinstala el mismo día.

---

## 20. El registro de accesos no cuenta doble

**Qué.** `SWPK_Api::anotar_llamada()` ignora la pasada que viene de
`rest_post_dispatch`.

**Por qué.** `rest_send_allow_header()` vuelve a llamar al
`permission_callback` después de contestar, para armar la cabecera `Allow`.
Resultado medido: una petición HTTP, dos filas idénticas. En un registro que
existe para probar quién consultó qué, eso convierte «esta llave consultó 400
veces» en una cifra indefendible.

**Por qué no se veía:** `rest_do_request` no pasa por `rest_post_dispatch`, así
que las pruebas de adentro salían limpias. Solo aparecía por HTTP, que es por
donde entran las llaves de verdad.

---

## 21. Las horas son hora de Guatemala

**Qué.** `SWPK_Frescura::fmt()` y `fecha()`, no `date()` ni `gmdate()`. Los
crons se programan en hora local.

**Por qué.** Hubo un periodo en que todo salía seis horas más tarde. Un panel
de evidencia con las horas mal es un panel de evidencia inservible.

---

## 22. El inventario diario se llama `fotos` por dentro

**Qué.** La tabla es `swpk_fotos` y el método `tomar_foto()`, pero en pantalla
y en los comentarios se habla de «inventario».

**Por qué.** Es una deuda de nombres, no una decisión. Está pendiente
renombrarlo. Si lo haces, acuérdate de que es una migración de base de datos,
no solo un *buscar y reemplazar*.

---

## Lo que sigue abierto

No son decisiones tomadas: son preguntas esperando respuesta. No las resuelvas
por tu cuenta.

1. **¿El panel puede cambiar cosas en los sitios?** Está construido, con
   aprobación y registro, pero la decisión de fondo es del jefe.
2. **¿Las llaves vuelven a la pantalla de Usuarios?** En pausa, esperándolo.
3. **¿Se acepta que una orden tarde?** Por el cron de WordPress.
4. **Pantheon.** Ver [12](12-pantheon.md).
5. **El agente no está instalado en ningún sitio.** Es la dependencia que
   bloquea la mitad de lo que el panel sabe hacer.

## 12. Una norma que nadie mira es peor que ninguna

La investigación sacó cuarenta y cinco comprobaciones medibles dentro del sitio.
Están todas, agrupadas en dieciséis normas que significan algo.

El motivo no es pereza: una pantalla con cuarenta y cinco filas por sitio, en
108 sitios, no la mira nadie — y entonces el panel da la impresión de que se
está revisando algo que en la práctica nadie revisa. Cuando una norma falla, el
detalle dice cuál de las comprobaciones de dentro fue.

La misma idea gobierna qué se marca como incumplimiento y qué no. Seis cosas se
miden y deliberadamente **no** se marcan, cada una con su motivo escrito en el
código, porque marcarlas daría filas rojas en los 108 sitios por decisiones que
no tomó nadie o que son legítimas. Están listadas en
[06 · Normas de seguridad](06-normas-de-seguridad.md).

**Si alguien quiere agregar una norma, la pregunta no es «¿se puede medir?» sino
«¿alguien va a hacer algo distinto por verla?».**

## 13. «Bloqueado» no es «aprobado»

Cuando la sonda pide un archivo y el cortafuegos corta la petición, no se
aprendió nada: el archivo puede estar ahí detrás de una regla que alguien apague
mañana. Eso se reporta como «no se sabe», nunca como que está bien.

La excepción, y está medida: si una dirección inventada con la misma extensión
—o con el mismo nombre bajo una carpeta que no existe— también viene bloqueada,
entonces la plataforma no sirve ese tipo de archivo a nadie, y eso sí protege.

Es la misma regla que la número 3 de esta lista, aplicada a lo que se ve desde
fuera. Y es la que decidió si el bloque de exposición servía: sin distinguir los
tres tipos de 403, las veintidós normas habrían quedado en «sin dato» en todos
los sitios.

## 14. Lo que cuesta dinero se dice, no se promete

Fijar la hora de las actualizaciones del hosting es posible, pero con un
complemento de pago por sitio. El escaneo de vulnerabilidades no es posible de
ninguna manera.

Se decidió no contratar nada, así que la respuesta honesta es «se puede ordenar
solo esto, solo pagando, y esto otro no se puede ni pagando». Lo escrito en
[14 · Cadencia y lo que cuesta](14-cadencia-y-lo-que-cuesta.md) está para que
nadie tenga que volver a averiguarlo, y para que nadie prometa una hora fija que
el panel no puede cumplir.
