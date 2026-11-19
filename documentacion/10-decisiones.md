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

**Qué.** La tabla es `swpk_fotos`, pero en pantalla y en los comentarios se
habla de «inventario».

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
5. **El agente no está instalado en todos los sitios.** Desde el 19 de
   noviembre de 2026 está en 95 —los *live* de Auto Update y Desarrollo— y los
   13 del cliente siguen fuera, que es donde el panel ve menos.

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

---

## 24. La corrida no cabe en una petición web, y por eso va a tramos

**Qué.** «Correr ahora» no corre nada. Apunta que hay trabajo y devuelve la
pantalla. El trabajo avanza después en tramos de doce segundos, empujados por
la pantalla mientras esté abierta y por el reloj del servidor cuando no lo
esté. La pantalla enseña una barra.

**Por qué.** Porque la corrida son dos consultas al hosting por cada sitio
—214 con 107 sitios— y el cliente HTTP frena solo para no pasarse del límite
por minuto que impone Kinsta. O sea que tarda minutos **por diseño**. Una
petición web no dura minutos: el servidor la corta y el trabajo queda a medias.

**Cómo se supo.** El 18 de noviembre de 2026 el jefe entró al panel recién
instalado en producción y pulsó el botón. Le salió un 504 del servidor.

**Por qué no se había visto antes, que es la parte que enseña algo.** No fue
por probar contra datos inventados: el panel de desarrollo tiene la misma llave
de Kinsta y la misma cartera de 108 sitios, y la corrida tarda ahí los mismos
minutos. Se escapó porque se probaba **siempre desde la línea de comandos**,
donde no hay servidor web que corte la petición. Nadie había pulsado el botón
en un navegador con la cartera completa delante.

Es la segunda vez en dos días que muerde lo mismo. La otra fue la clave de
registro del agente: el agente decía «está en el panel, en Ajustes», no estaba
en ninguna pantalla, y no se notó porque en las pruebas la clave se sacaba con
un comando contra la base de datos. La regla, entonces:

> **Lo que va a usar una persona se prueba por donde lo va a usar esa persona.**
> Si el camino de quien prueba no es el camino de quien usa, el hueco no se ve.

**Lo que arregla de paso.** La corrida vieja borraba la fecha de escaneo de los
107 sitios al empezar y la volvía a llenar al final. Si moría a la mitad
—justo lo que pasaba— dejaba medio inventario marcado como «nunca escaneado»,
que es una mentira distinta de «todavía no se sabe». Ahora la fecha se anota
sitio por sitio conforme se lee, y lo que se borra al cerrar es solo lo de los
sitios que de verdad no contestaron.

**Un tramo se mide con reloj, no con sitios.** Si se midiera en «veinte sitios»
y un día el hosting contestara lento, el tramo duraría el triple y volveríamos
al mismo 504. Doce segundos duran doce segundos conteste como conteste: lo que
cambia es cuánto avanza, no si sobrevive.

**El endpoint de empresa no es la salida.** Devuelve lo mismo en pocas llamadas,
pero pagina de 30 en 30 y los parámetros que controlan eso van en el cuerpo de
un GET, que es lo que WordPress no puede mandar. Comprobado contra la API real
el 11 de noviembre de 2026; está contado en `class-fuente-api.php`.

## 25. Un reloj que falla en silencio es peor que un reloj parado

**Qué pasó.** El 19 de noviembre de 2026 el panel decía, en «De cuándo es lo
que estás viendo»: *el panel revisó si los sitios responden → nunca*, y debajo,
en rojo, *el sondeo de cada cinco minutos no está corriendo*. Tenía razón: no
había corrido una sola vez, ni ahí ni en ninguna otra instalación.

**Por qué.** `wp_schedule_event()` comprueba que la recurrencia que le pasas
exista en `wp_get_schedules()`. Si no existe **devuelve `false` y no dice
nada**: ni excepción, ni aviso, ni entrada en el registro. El intervalo de
cinco minutos es nuestro, lo registra un filtro que se engancha en `init()`,
y `init()` cuelga de `plugins_loaded`.

Al activar un plugin, `plugins_loaded` ya pasó. WordPress incluye el archivo
del plugin y dispara el gancho de activación dentro de una petición en la que
ese plugin todavía no estaba en la lista de activos. Así que el gancho de
activación llamaba a `programar()` con un intervalo que, en esa petición
concreta, no existía. El evento no se creaba. Nadie se enteraba.

**Por qué no se vio antes.** Los demás relojes del panel usan `daily` y
`weekly`, que WordPress trae de fábrica, y esos sí quedaban puestos. En la
pantalla de cron se veían cinco eventos `swpk_` y todo parecía normal. Faltaba
el sexto.

**Lo mismo en el agente.** `swa_preguntar_ordenes` —la pregunta cada cinco
minutos con la que el sitio recoge las órdenes del panel— tenía el fallo
idéntico, y por la misma razón: intervalo propio. Comprobado por SSH en los 95
sitios. El reporte diario sí estaba puesto, porque usa `daily`, y por eso el
agente parecía sano: reportaba. Lo que no hacía era obedecer. Una orden
enviada desde el panel esperaba al reporte del día siguiente en vez de cinco
minutos.

**El arreglo.** `programar()` ya no da por supuesto el intervalo: si no está
registrado lo registra antes de usarlo. Y se vuelve a llamar desde `init()` en
cada carga, como ya hacían la sonda y la revisión diaria, para que las
instalaciones que ya existen se curen solas sin reactivar nada.

**La regla.** Una función que devuelve `false` en vez de quejarse necesita que
alguien compruebe el resultado. Si programar un reloj puede fallar sin ruido,
el que lo programa comprueba que quedó puesto — o el panel acaba prometiendo
una vigilancia que no existe.

**Lo que esto destapó: el reloj del hosting.** Programar el evento cada cinco
minutos no significa que ocurra cada cinco minutos. WP-Cron no es un reloj, es
«lo que se ejecuta cuando alguien pide una página». En Pantheon —donde vive el
panel— ni eso: el upstream de WordPress trae `DISABLE_WP_CRON` puesto y la
plataforma ejecuta el cron **una vez por hora**. Un sondeo programado cada
cinco minutos iba a correr cada hora igual, y el panel iba a seguir avisando en
rojo con razón.

## 26. El sondeo es cada hora, y el panel lo dice

**La decisión.** El 19 de noviembre de 2026, puestas las tres salidas delante
—aceptar la hora; disparar el sondeo con las llamadas que los 95 agentes ya
hacen al panel cada cinco minutos; o reencender WP-Cron en solucionweb.com—, se
eligió **la hora**. Nada que montar, nada que depender de los agentes, nada que
tocar en la configuración de Pantheon.

**Lo que cambió, entonces.** El evento usa `hourly`, que es de WordPress, así
que de paso desaparece el intervalo propio que causó el fallo de arriba. Y
cambió el texto en todas partes donde el panel prometía cinco minutos: la
pantalla de Caídas, la de Ajustes, el sello de frescura, el README, y —la que
importa de verdad— **la plantilla del correo de caída que le llega al
cliente**, que decía «el monitor revisa cada cinco minutos».

**Lo que no se podía dejar como estaba.** La regla era «no se avisa hasta dos
sondeos fallidos seguidos», y la segunda opinión llegaba en la corrida
siguiente. Con cinco minutos eso costaba cinco minutos; con una hora habría
costado **dos horas**, y el panel estaría diciendo «reviso cada hora» mientras
avisa a las dos. Así que la segunda opinión se pide ahora dentro de la misma
corrida, ocho segundos después del primer fallo y solo a los que fallaron. Un
corte de red de tres segundos sigue sin contar como caída, que es para lo que
estaba la regla; lo que no se hereda es el retraso.

**El precio, dicho claro.** Una caída se ve hasta una hora tarde. Eso es lo que
hay con el reloj de este hosting y sin montar nada aparte, y está escrito aquí
para que el día que no alcance, se sepa qué hay que cambiar y qué costaba.

## 27. El agente se muda al tema, y la mudanza destapó dos minas

**Qué.** El agente deja de ser solo un plugin suelto: va también dentro del
tema hijo `hello-elementor-child`, que ya se instala en todos los sitios. Un
sitio nuevo nace conectado al panel sin que nadie se acuerde de instalar nada.
Lo pidió el jefe el 19 de noviembre de 2026, y es el mismo camino que ya había
seguido el plugin `no_url_field`, que hoy es `inc/form-security.php` de ese
tema.

**Cómo conviven.** Los 95 sitios que ya tienen el plugin no cambian. Los
plugins cargan antes que el tema, así que cuando se llega al módulo del tema el
plugin ya definió `SWA_VERSION` y el módulo se aparta. Nunca corren los dos. Y
el día que se quite el plugin de un sitio, el tema toma el relevo solo: los
ajustes viven en la base de datos, no en el plugin. En el panel se distinguen
por la versión: `0.6.3` es plugin, `0.6.3-tema` es tema.

**Doce de trece archivos son copia exacta.** A propósito. El único que cambia
es la pantalla, que se dibuja con los recuadros y los interruptores del tema en
vez del aspecto suelto de WordPress. Así una corrección del agente se trae sin
pensar.

### Y ahora lo que enseña de verdad

Probar la entrada **por donde la hace una persona** —el login escondido que ese
tema pone en `/panel/admin/`— destapó dos fallos del agente. Los dos le pasaban
igual al plugin desde siempre; no son de la mudanza.

**Uno: error fatal al entrar.** La pantalla del segundo paso llamaba a
`login_header()`, que vive DENTRO de `wp-login.php` y solo existe si la
petición pasó por ese archivo. Con un login propio, quien valida la contraseña
es una plantilla del front: `wp-login.php` no se carga y la llamada revienta a
mitad de la entrada. Dicho sin rodeos: **encender la verificación en dos pasos
en un sitio con el tema hijo dejaba fuera a todos los administradores.**

**Dos: el código no tenía dónde llegar.** El formulario mandaba el código a
`wp-login.php`, y ese mismo tema rebota a `/contacto/` a quien pida
`wp-login.php` sin haber entrado — que es exactamente la situación de alguien a
medio segundo paso. Ahora el código va a `admin-post.php`, que el tema sí deja
pasar porque lo usan los formularios del sitio.

**Por qué no lo había visto nadie.** Porque la verificación en dos pasos está
apagada en los 95 sitios, a propósito, hasta probarla. Habría saltado en el
primero donde se encendiera, y el primero iba a ser una prueba a mano.

Es la tercera vez que muerde lo mismo —antes fueron la clave de registro y el
botón de «Correr ahora»—, así que la regla ya no es una anécdota:

> **Lo que va a usar una persona se prueba por donde lo va a usar esa persona.**
> Y para una pantalla de entrada, eso significa entrar: escribir la contraseña,
> esperar el código, escribirlo. No basta con que el archivo no tenga errores
> de sintaxis.

---

## 28. Un nombre que nadie entiende no es un nombre

Viendo el panel, el jefe se paró en una de las ocho familias de normas y dijo,
sin rodeos: **«no sé qué significa cómo viaja».** Se llamaba «Cómo viaja el
sitio» y agrupaba diez normas —HTTPS, HSTS, política de contenido, bloqueo de
marco, referidos, permisos del navegador—. Para quien conoce el tema es una
metáfora cómoda. Para quien no, no dice nada, y además suena a transporte.

Ahora se llama **«Lo que el navegador recibe»**, que es literalmente lo que
miden esas diez normas: lo que el servidor le dice al navegador de cada
visitante. El nombre viaja en la base de datos, así que cambiarlo obliga a
subir `SWPK_Normas::VERSION_CATALOGO` —de 10 a 11— para que `sembrar()` repase
las filas que ya existen. Sin ese número, el código habría dicho una cosa y la
pantalla habría seguido diciendo la otra.

**La regla que queda:** un nombre de pantalla, de pestaña o de grupo se escribe
para quien no construyó esto. Si hay que explicarlo, no es un nombre: es una
adivinanza con etiqueta.

## 29. Seguridad y Vulnerabilidades no son lo mismo, y había que decirlo

La misma grabación, un minuto después: *«aquí veo lo vulnerable y aquí veo lo
seguro — **no es lo mismo**»*. Tenía razón en las dos mitades: son cosas
distintas, y ninguna de las dos pantallas lo decía.

- **Seguridad** son **las normas**: lo que todo sitio debería cumplir, tenga lo
  que tenga instalado. Se incumplen por configuración y se arreglan cambiando
  esa configuración.
- **Vulnerabilidades** son **las fallas conocidas** de los plugins y temas que
  ese sitio tiene puestos. Se arreglan actualizando.

Un sitio puede cumplir las 73 normas y tener diez plugins con falla, y al
revés. Ahora cada pantalla abre con una línea que dice qué es y enlaza a la
otra, y las dos llevan la misma burbuja con la explicación larga — la misma a
propósito: es la misma explicación, y quien la lea en cualquiera de las dos ya
no vuelve a preguntarlo.

## 30. Una pantalla puede estar rota y parecer entera

Repasando el panel entero contra la grabación apareció algo que no venía de
esta ronda: la pestaña **«Qué revisamos»** de Seguridad moría con un error
fatal al abrir el formulario de una norma. Una variable renombrada en un
commit anterior (`$grupos`, que ahora se llama `$etiquetas`) que nadie volvió a
tocar.

Lo importante no es el fallo, que son dos palabras: es **por qué no se había
visto**. El primer barrido de esta sesión revisó desbordes, scrolls internos y
cajas vacías en las quince pantallas, y dio limpio — incluida esa. El error
ocurre a media página, después de que el `<div class="wrap">` ya se abrió, así
que la pantalla tiene su cabecera, sus pestañas y su tabla, y por arriba se ve
igual que las demás.

**La regla que queda:** revisar la maqueta no es revisar la pantalla. Un
barrido de pantallas busca además `Fatal error`, `Warning`, `Notice` y
`Deprecated` en el HTML servido, y lo hace con los parámetros de verdad
—filtros puestos, página tres, `editar=` con una norma dentro—, porque el
camino que se rompe casi nunca es la portada de la pantalla.

## 31. Lo que explica una pantalla no se escribe encima de la pantalla

Él lo dijo mirando cuatro pantallas seguidas: *«toda esa información no le
sirve, hay mucho ruido visual»*, *«es un montón de info que no voy a leer»*,
*«necesito simpleza»*. Y tenía razón en algo más fino que «sobra texto»:
el panel estaba contándose a sí mismo en voz alta, cada vez, a alguien que
entra a mirar un dato.

Eran párrafos de tres y cuatro líneas encima de cada tablero, explicando qué
decide esa lista, qué pasa al apagar una norma, cuánto tarda un arreglo, qué
significa cada columna. Todo cierto y todo útil — **una vez**. A partir de la
segunda, es lo que hay que saltarse para llegar a la tabla.

La regla que queda: **una línea a la vista, el resto en la burbuja.** El
título de una caja puede llevar su ⓘ pegada (`caja_abrir()` pasa el título por
`wp_kses_post`, no por `esc_html`, justo para eso), y ahí dentro cabe el
párrafo entero sin ocupar pantalla. Lo que se pierde es la lectura obligatoria;
no se pierde la explicación.

De paso salió una cita de la grabación metida en la interfaz —*«Todo lo que sea
arroba solución se excluye» (01:57)*, con su minuto y todo, en el campo de
dominios propios—. Las notas de una reunión documentan el código; no se
publican dentro de él.

## 32. Una barra de filtros que no cabe no es culpa del ancho

«El buscar y el filtrar abajo, cuando debería continuar con la fila; eso cabe
perfectamente en una.» Cabía: 800 px de campos en 908 px de barra. Se partía de
todas formas.

La causa es cómo decide envolver un contenedor flexible: mira el ancho del
**contenido** de cada campo, no el que van a tener después de repartirse el
espacio. Un desplegable con nombres de sitio mide lo que mida el nombre más
largo —210 px por un nombre de tres palabras—, cinco campos así suman más de lo
que hay, y el sexto y el botón se caen al renglón de abajo. Después, ya
envuelto, cada uno se encoge y queda la fila de arriba con huecos.

Se arregla dándoles una **base corta** (`flex: 1 1 120px`) y `min-width: 0`:
la cuenta del envolvimiento se hace con 120 px por campo, cabe, y el reparto
del ancho sobrante ocurre después. El texto largo del desplegable se recorta
con puntos suspensivos y se ve entero al desplegarlo.

**Cómo se comprueba, y por qué no a ojo.** Contando: altura de la barra contra
altura del campo más alto. Si la barra mide mucho más que un campo, envolvió.
Y se mide a **1280 px**, que es la pantalla de trece pulgadas desde la que se
revisa esto, no a la del que lo escribe.
