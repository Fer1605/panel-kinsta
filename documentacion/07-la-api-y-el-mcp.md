# 07 · La API y el MCP

Dos formas de consultar el panel desde fuera. Son la misma lógica por dentro:
el MCP **no tiene código propio** — arma la misma petición que haría cualquiera
y la manda por la puerta de siempre.

## Por qué existe

El jefe lo pidió en dos líneas, después de aprobar la lógica del panel:

> «recuerda que tendra fases asi que las consultas y procesos mejor si tienen
> api de consumo»
>
> «debe poder consultarse o correrse manualmente los procesos o consultar un
> sitio por si alguien pregunta puedo hacerlo manual y no esperar»

Y más tarde pidió conectarlo a Claude Desktop, como el conector de Novamira:
Conectores → Agregar conector personalizado → pegar la URL → Conectar.

---

## La API

Espacio `swpk/v1`, el mismo que usan los agentes. **Es una capa delgada sobre
las funciones que alimentan las pantallas**, para que panel y API no puedan
contradecirse.

### Lectura

| Ruta | Devuelve |
|---|---|
| `GET /estado` | El veredicto: cuántos necesitan atención, cuáles no pueden esperar |
| `GET /sitios` | Listado con filtros `tag`, `nivel`, `busqueda`, paginado |
| `GET /sitios/{id}` | La ficha completa de un sitio |
| `GET /vulnerabilidades` | Los hallazgos de toda la cartera |
| `GET /normas` | La lista de normas, con el porqué de cada una |
| `GET /cumplimiento` | Quién cumple qué, una fila por cruce, con su fecha |
| `GET /ordenes` | Qué se le pidió a qué sitio y cómo acabó |
| `GET /usuarios` | Quién tiene control de cada sitio nuestro |
| `GET /eventos` | El historial, filtrable por fecha, tipo y sitio |
| `GET /bitacora` | Qué se notificó a quién y cuándo |

### Procesos

| Ruta | Hace |
|---|---|
| `POST /sitios/{id}/analizar` | Vuelve a leer un sitio, ahora |
| `POST /analizar` | La corrida completa |
| `POST /sondear` | El sondeo de caídas de los 108 |

**Van por POST aunque no escriban nada afuera, porque cuestan**: la corrida
completa son más de 200 peticiones al hosting. Un GET se repite solo —un
navegador, una caché, un enlace abierto dos veces— y eso no debe poder
disparar 200 peticiones sin querer.

### Lo que la API NO puede hacer

**No crea órdenes.** No es un olvido: una orden cambia cosas en el sitio de un
cliente, y eso se pide desde la pantalla que dice en cuántos sitios va a pasar
y pide confirmación. Abrirlo a la API sería dejar que un script cambie 94
sitios sin que nadie lo vea.

Tampoco asigna asesoras ni marca nada como atendido. El día que haga falta
escribir desde fuera, será una decisión aparte.

### La frescura va en todas las respuestas

Toda respuesta lleva `frescura`, `generado_en` y `zona`. Por lo mismo que las
llevan las pantallas, los Excel y los PDF: quien consuma esto meses después
tiene que saber de cuándo es el dato.

---

## Autenticación: contraseñas de aplicación

Es lo que ya trae WordPress. Tres cuidados, porque esto abre una puerta nueva:

**1. Un perfil propio.** Las llaves tienen el rol `swpk_api`, con permiso de
lectura y nada más. Disparar procesos pide además `swpk_administrar_panel`.

**2. Todo queda anotado.** Las contraseñas de aplicación **se saltan la
verificación en dos pasos**, porque no pasan por el formulario de entrada. Es
el precio de que un programa pueda consultar sin una persona delante. Lo que lo
hace aceptable es que quede escrito quién llamó, a qué y desde dónde —igual que
una entrada al panel— y que la llave se pueda revocar.

**3. Nada de `__return_true`.** El `permission_callback` comprueba el mismo
permiso que comprueba la pantalla, con `SWPK_Perfiles::puede()`.

### El doble registro: un error que vale la pena conocer

Una sola petición HTTP dejaba **dos filas idénticas** en el registro de
accesos, con un segundo de diferencia.

La causa: `rest_send_allow_header()` corre enganchada a `rest_post_dispatch`
—o sea, después de haber contestado— y vuelve a llamar al `permission_callback`
de cada manejador para decidir qué métodos anunciar en la cabecera `Allow`.

En un registro que existe para poder probar quién consultó qué, contar doble no
es un detalle de limpieza: convierte «esta llave consultó 400 veces» en una
cifra que no se puede defender delante de nadie.

Y no se veía en las pruebas de adentro, porque `rest_do_request` **no pasa por
`rest_post_dispatch`** — solo salía por HTTP, que es por donde entran las
llaves de verdad.

La guarda está en `SWPK_Api::anotar_llamada()`, con `doing_filter()`. Es
pública porque el MCP anota por ahí también: su `permission_callback` sufre
exactamente el mismo doble disparo, y tener dos formas de escribir en el
registro sería tener una sin guardia.

**Si agregas una ruta, no escribas en `swpk_accesos` por tu cuenta.** Usa
`SWPK_Api::anotar_llamada()`.

---

## El MCP

`class-mcp.php`. Una ruta:

```
POST  {panel}/wp-json/swpk/v1/mcp/{token}
```

El token son 32 caracteres hexadecimales, propios de cada llave. En la base no
se guarda el token sino su huella (`sha256`), igual que una contraseña.

Protocolo JSON-RPC 2.0 sobre HTTP, versión `2024-11-05`. Atiende `initialize`,
`notifications/initialized`, `tools/list`, `tools/call` y `ping`, y contesta
`resources/list` y `prompts/list` con listas vacías —como manda el protocolo—
en vez de con un error genérico que el cliente registraría como caída.

### Las diez herramientas

`panel_estado`, `panel_sitios`, `panel_sitio`, `panel_vulnerabilidades`,
`panel_normas`, `panel_cumplimiento`, `panel_ordenes`, `panel_usuarios`,
`panel_eventos`, `panel_bitacora`.

Son las mismas rutas de lectura de la API, con los mismos nombres de
parámetro. **Las tres que disparan procesos no se entregan**, y tampoco ninguna
que cambie algo en un sitio de cliente.

`tools/list` devuelve solo `name`, `description` e `inputSchema`: las claves
internas (`_ruta`, `_en_la_ruta`, `_requiere`) se quitan antes de contestar.

### Cómo llama

`tools/call` no hace nada por su cuenta: arma un `WP_REST_Request` igual al que
haría cualquiera desde fuera y lo manda con `rest_do_request`. Así el alcance,
el recorte por perfil y el registro son los mismos, sin una sola línea
repetida. Las llamadas que vienen por MCP se marcan con `· MCP` en el registro.

### Para agregar una herramienta

1. Que exista la ruta en `SWPK_Api`.
2. Agrégala a `SWPK_Mcp::herramientas()` con `_ruta` y su `inputSchema`.
3. Si lleva algo en la URL, `_en_la_ruta` dice qué argumento va ahí.

Escribe la `description` pensando en que la lee un modelo, no una persona: dile
para qué sirve, con qué otra empezar, y **qué malinterpretaría si no se lo
dices**. Por ejemplo, `panel_cumplimiento` avisa que `sin_dato` quiere decir
que el panel no lo sabe, no que el sitio cumpla.

---

## Conectar Claude Desktop

1. En el panel: **Conectarse (API) → Crear una llave**. Sale un pop-up con la
   dirección del MCP y un botón de copiar. **Se muestra una sola vez.**
2. En Claude Desktop: **Conectores → Agregar conector personalizado**, poner un
   nombre y pegar la URL.

### El límite que no se puede saltar

**La URL tiene que ser pública y con HTTPS.** Claude Desktop no abre la
conexión desde la computadora: la manda a la nube de Anthropic, que es quien
se conecta. Por eso `localhost` y las direcciones privadas se rechazan.

Hoy el panel corre en una máquina de la casa, así que **esto no se puede
probar de verdad todavía**. No es un fallo del código: es que el panel tiene
que vivir en un servidor al que se pueda llegar.

`SWPK_Api::alcanzable()` lo comprueba y la pantalla lo dice en vez de dejar que
alguien lo descubra como un error de Claude Desktop.

---

## `conector-mcp/`: la vía local

Hay un servidor MCP en Node, sin dependencias, en `conector-mcp/`. Funciona por
stdio y es lo único que puede alcanzar un panel en `localhost`.

Quedó como alternativa, no como el camino principal. Fer lo cortó en seco y
tenía razón: *«Nadie tiene acceso al repositorio, amigo. Y no vamos a estar
haciendo eso.»* Pedir bajar una carpeta, tener Node y editar un archivo de
configuración a mano no es «pegar una URL».
