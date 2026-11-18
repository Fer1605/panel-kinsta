# 04 · El agente

`agente-sitio/` es el plugin que vive en cada sitio de cliente. Prefijo `SWA_`.

## Qué sale de aquí, y qué no

**Sale:** id, usuario, correo, roles y fecha de alta de cada usuario con
control; si la verificación en dos pasos está activa; si el sitio puede mandar
correo; las versiones de WordPress y PHP; si XML-RPC está abierto; y las normas
de seguridad que solo se ven desde dentro.

**No sale:** contraseñas, ni cifradas. Ni contenido, ni pedidos, ni clientes,
ni nada más de la base de datos.

Esto no es una aspiración: `SWA_Reporte::armar()` es la lista completa de lo
que se manda. Si algo no está ahí, no viaja.

## La regla que define el diseño

**El sitio llama al panel, no al revés.**

No queda un endpoint esperando peticiones de internet. Es el tipo de cosa que
se vuelve un problema dos años después, cuando nadie se acuerda de que existe.

Cuando hizo falta que el panel le pidiera algo a un sitio —las órdenes de
seguridad— no se abrió una puerta: se puso una cola que el sitio consulta. Ver
[06](06-normas-de-seguridad.md).

---

## El registro: una vez por sitio

```
POST  {panel}/wp-json/swpk/v1/registro
      { clave, sitio, nombre }
   →  { token, secreto }
```

La clave de registro es la misma para todos y se genera sola en el panel
(`SWPK_Receptor::clave_registro()`). A cambio, el panel le entrega a **ese
sitio** su token y su secreto, que no sirven para ningún otro.

Un re-registro renueva el secreto. Es lo que se quiere: para revocar un sitio,
se borra su fila en `swpk_agentes` y se vuelve a registrar.

---

## El reporte: firmado, y con marca de tiempo

```
POST  {panel}/wp-json/swpk/v1/reporte
      X-SWA-Token:  el token de este sitio
      X-SWA-Marca:  time()
      X-SWA-Firma:  HMAC-SHA256( marca + "\n" + cuerpo, secreto )
```

El panel comprueba tres cosas, en este orden: que el token exista, que la marca
esté dentro de una ventana de 300 segundos, y que la firma cuadre.

**La marca entra en la firma y se rechaza lo viejo.** Sin eso, alguien podría
guardar un reporte de hace un mes y volver a mandarlo para tapar que hoy
apareció un administrador nuevo.

### Cuándo se manda

| Cuándo | Qué lleva |
|---|---|
| **Diario, a las 05:00** | El reporte completo |
| **En el momento**, al cambiar quién tiene control | El reporte completo |
| **Cada cinco minutos** | El liviano: solo pregunta si hay órdenes |

El diario va escalonado por sitio: `crc32( home_url() ) % 3600`. Si 94 agentes
llaman a la misma hora, el panel recibe 94 peticiones en el mismo minuto. El
desfase sale del propio dominio, así que es estable entre corridas.

### El reporte liviano, y el peligro que trae

El liviano solo manda `agente`, `sitio`, `liviano: true` y los resultados de
órdenes pendientes. **No manda usuarios ni normas**: con 94 sitios, mandar la
lista entera cada cinco minutos serían 27.000 reportes completos al día para
preguntar algo que casi siempre se contesta con «nada».

Eso obliga a una regla en el receptor, y es importante:

> **Nada se escribe por ausencia.** Si un dato no viene en el reporte, el dato
> no se toca.

Sin esa regla, un reporte liviano apagaría en el panel la verificación en dos
pasos de un sitio que la tiene puesta, y `SWPK_Usuarios::procesar()` con la
lista vacía daría de baja a **todos** los usuarios del sitio y anotaría un
evento por cada uno. Se descubrió probándolo, antes de que llegara a ningún
sitio real.

Si tocas `SWPK_Receptor::reporte()`, no uses `?? ''` ni `! empty()` para
rellenar un campo ausente: comprueba `isset()` y, si no está, no lo escribas.

---

## El vigilante: el aviso en el momento

`SWA_Vigilante` existe porque el reporte diario llega tarde para un caso
concreto: un malware entra y se crea un administrador. Si el panel se entera a
las cinco de la mañana del día siguiente, el daño ya está hecho.

Guarda una huella de quién tiene control. Cuando cambia, manda el reporte en el
acto. Si el envío falla, reintenta.

La huella solo se renueva cuando de verdad se mandó la lista: un reporte
liviano no la lleva, así que decir «el panel ya la conoce» taparía un alta de
administrador hasta el día siguiente.

---

## La verificación en dos pasos

`SWA_Dos_Factores` intercepta la entrada: el usuario escribe su contraseña,
WordPress la valida como siempre, y antes de dejarlo entrar se le quita la
sesión recién creada y se le pide un código que llega por correo.

**Por eso `SWA_Correo` va antes.** Si el código llega por correo y el sitio no
puede enviar correo, el administrador pide su código, nunca le llega, y queda
encerrado fuera de su propio sitio. Nosotros también. El agente comprueba que
el sitio puede mandar correo **antes** de dejar activar esto.

Hay códigos de respaldo (`generar_respaldo()`) y un apagado automático, por si
algo sale mal.

Nota para la API: **las contraseñas de aplicación de WordPress se saltan la
verificación en dos pasos.** Por eso cada llamada a la API del panel queda
registrada en `swpk_accesos`. Ver [08](08-permisos-y-llaves.md).

---

## Las normas que mide el agente

`SWA_Normas::medir()` devuelve lo que solo se ve desde dentro:

| Clave | Qué comprueba |
|---|---|
| `editor_codigo` | `DISALLOW_FILE_MODS` o `DISALLOW_FILE_EDIT` |
| `registro_abierto` | La opción `users_can_register` |
| `php_en_uploads` | Si el servidor ejecuta un PHP subido a la carpeta de subidas |

### La prueba de subidas es una prueba de verdad

El jefe pidió «que tenga htaccess bien configurado». En Kinsta **no existe
`.htaccess`** —corre Nginx, y su propia documentación lo dice— así que mirar el
archivo daría «no cumple» en los 108 sitios sin significar nada.

Lo que importa es el efecto: si alguien logra subir un archivo PHP por un
formulario, ¿el servidor lo ejecuta o lo devuelve como texto? Eso se comprueba
haciéndolo: se escribe un archivo inofensivo con nombre al azar, se pide por
HTTP, se mira qué contestó y se borra. Da el mismo resultado en Nginx, en
Apache y en Pantheon, porque mide la consecuencia y no la receta.

Detalles que importan:

- El archivo imprime una palabra y nada más. No lee, no escribe, no llama a nada.
- Se borra **antes** de interpretar la respuesta, para que ninguna rama de
  salida lo deje puesto.
- El resultado se guarda doce horas. Hacerlo cada cinco minutos serían 288
  peticiones diarias que el sitio se hace a sí mismo para contestar algo que no
  cambia nunca.
- Un 403 o un 404 **no** es «no se ejecuta»: es `'no_se_pudo'`. Decirlo como
  bueno sería inventarse una buena noticia.

---

## Lo que el agente mide y nadie más puede

Cuatro clases, todas con caché de doce horas y todas fuera del reporte liviano
de cada cinco minutos. De aquí salen 36 de las 73 normas.

**`SWA_Formularios` — los archivos que sube la gente.** Es el caso que el jefe
contó con el ejemplo del CV. Para probar que un archivo se baja hay que saber
cómo se llama, y adivinarlo desde fuera sería andar pescando el currículum de
alguien. El agente está dentro: ve la carpeta, agarra el archivo más reciente,
lo traduce a dirección pública y la pide sin sesión.

**El nombre del archivo no sale del sitio.** No viaja al panel, no se escribe en
la bitácora, no se guarda. Se manda el sí o el no.

De aquí sale también el hallazgo del `.htaccess` inerte: Contact Form 7, Gravity
Forms y WPForms escriben uno en su carpeta de subidas para cerrarla, y en un
servidor Nginx ese archivo no se lee. Los tres plugins creen que están
protegidos y no lo están.

**`SWA_Fondo` — configuración, permisos e integridad.** Las constantes de
`wp-config.php`, los permisos de cada rol, los archivos del núcleo contra las
sumas oficiales de WordPress.org, los plugins que el repositorio cerró, los
drop-ins y las tareas programadas huérfanas.

Dos cosas que solo se ven aquí:

- Un plugin **cerrado** por WordPress.org por un problema de seguridad sigue
  apareciendo como «al día» en el escritorio del sitio, y Site Health no dice
  nada. Hay que preguntarlo expresamente.
- Las ocho llaves de sesión se mandan como **un hash de las ocho juntas**, nunca
  las llaves. El panel los compara entre sitios, y así se descubre que un sitio
  se clonó de otro — que es cuando una sesión robada en uno sirve en el otro.

**`SWA_Tienda` — si el sitio vende de verdad.** Ver
[13 · Qué es cada sitio](13-que-es-cada-sitio.md).

**`SWA_Silencio` — que WordPress se calle.** Pedido literal del jefe: «no debe
notificar doble correo (...) deben llegar los del sistema, solo los del panel».
Calla los cuatro avisos de rutina —plugin, tema y núcleo actualizados, y «hay
una versión nueva»— y **no calla ninguno que avise de un problema**: el del
núcleo distingue éxito de fallo y solo se calla el éxito. Callar un fallo
convertiría «no duplicamos correos» en «nadie se entera de nada».

Es un ajuste encendido de fábrica, no una constante, porque el panel tiene que
poder decir en el reporte si está puesto y porque un cliente que sí quiera los
avisos de WordPress debe poder quedarse como está.

### Dos guardas contra hacer lento el reporte

`SWA_Formularios` mira como mucho 200 archivos buscando una muestra y 5000
buscando PHP sueltos. Una carpeta de subidas con años encima no debe hacer que
el reporte tarde un minuto.

## Las órdenes: seis verbos y nada más

`SWA_Ordenes` recoge lo que el panel dejó pendiente y lo ejecuta. La lista de
lo que sabe hacer está en el código y es corta:

| Orden | Qué hace |
|---|---|
| `activar_dos_pasos` | Enciende la verificación en dos pasos |
| `exigir_dos_pasos` | La exige a los administradores, con una semana de gracia |
| `apagar_editor` | Cierra el editor de código |
| `cerrar_xmlrpc` | Cierra XML-RPC |
| `cerrar_registro` | Cierra el registro de usuarios |
| `resetear_clave` | Cambia la contraseña de un administrador nuestro |

Una orden que no esté en esa lista se rechaza y se le dice al panel. **No hay
«ejecutá esto», no hay «instalá aquello», no viaja código en la orden.** Ni el
panel ni nadie que logre hablar como el panel puede pedirle a un sitio algo que
no esté escrito aquí.

### Tres cosas que `resetear_clave` no hace

- **No crea el usuario si no existe.** Una orden de cambiar contraseña que de
  pronto crea un administrador es una puerta, no un arreglo.
- **No toca a nadie que no sea administrador.** El reseteo masivo es para
  nuestras cuentas; los usuarios del cliente no son asunto nuestro.
- **No manda correo al usuario.** Lo manda el panel, a nosotros, cuando lo
  decidamos — no 94 correos automáticos a 94 buzones.

### El editor de código y una promesa que no se puede hacer

`DISALLOW_FILE_EDIT` es una constante de `wp-config.php`, y un plugin no
debería escribir en ese archivo: un error de escritura deja el sitio del
cliente en blanco.

Así que el agente hace lo que sí puede sin riesgo: define la constante él
mismo, temprano, en cada carga. El efecto es real —el editor desaparece del
escritorio— pero depende de que el agente siga activo.

Por eso el reporte dice **quién** lo cerró (`editor_codigo_por`), y el panel
muestra «cerrado por el agente» en vez de «cerrado», que sería prometer algo
que no es verdad si alguien desactiva el plugin.

### Los resultados no se pierden

Si el panel no contesta —se cayó, cambió de dirección— el resultado de una
orden ya ejecutada se guarda en una cola local y viaja en el siguiente intento.
Sin eso, una orden ejecutada con éxito podría quedar para siempre como
«entregada» en el panel solo porque falló la petición de vuelta.

La cola tiene tope de 100, para que un panel caído una semana no llene la tabla
de opciones.

---

## Instalarlo en un sitio

1. Copiar o enlazar `agente-sitio/` en `wp-content/plugins/` y activarlo.
2. En **Ajustes → Agente Solución Web**, poner la dirección del panel y la
   clave de registro (sale de Ajustes, en el panel).
3. Pulsar **Registrar**. El panel le entrega token y secreto.
4. Comprobar que el sitio manda correo **antes** de encender la verificación en
   dos pasos.

El reporte diario y el sondeo de órdenes quedan programados al activar.
