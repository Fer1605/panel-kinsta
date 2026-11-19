# 01 · Cómo está armado

## Son dos plugins, y tienen que ser dos

| | Dónde vive | Para qué |
|---|---|---|
| `panel-kinsta/` | En **un** WordPress nuestro, el panel | Consolida, cruza, avisa y guarda la evidencia |
| `agente-sitio/` | En **cada** sitio de cliente | Reporta lo que solo se ve desde dentro, y ejecuta lo que se le pida |

No es una decisión de arquitectura por gusto: es que **la API de Kinsta no
tiene forma de listar los usuarios de un sitio**. Tiene endpoints para crear un
administrador, para preguntar si un correo ya existe y para generar un enlace
de acceso — nada de eso sirve para descubrir usuarios que no sabíamos que
existían, que es exactamente lo que hay que detectar cuando un malware se crea
un administrador.

La alternativa era dar acceso SSH a cada proyecto, y se descartó con razón. Si
de todas formas hay que entrar una vez a cada sitio, esa visita resuelve cuatro
cosas de un viaje: los usuarios, la verificación en dos pasos, la prueba de que
el sitio manda correo, y las normas de seguridad.

**Hoy el agente no está instalado en ningún sitio.** Solo corre en el sitio de
prueba local. Eso no es «falta terminar»: es que no ha empezado, y es la
dependencia que bloquea la mitad de lo que el panel sabe hacer.

## El panel, por capas

El orden importa: cada capa solo conoce la de abajo.

```
   PANTALLAS           includes/admin/vistas/*.php
   (no deciden nada, solo preguntan y pintan)
        │
   LÓGICA              class-estado, class-riesgo, class-normas,
   (decide qué         class-textos, class-eventos, class-ordenes…
    significa el dato)
        │
   ALMACÉN             class-inventario, class-usuarios, class-bitacora,
   (lee y escribe       class-accesos… todos sobre class-tablas
    las once tablas)
        │
   FUENTES             interface-fuente.php ← class-fuente-api (Kinsta)
   (de dónde viene                         ← class-fuente-demo (prueba)
    el dato crudo)     class-wordfence (vulnerabilidades)
                       class-receptor  (lo que mandan los agentes)
```

### La regla de las vistas

Una vista **no decide**. Si en un archivo de `vistas/` aparece una regla de
negocio —qué cuenta como grave, qué sitio es nuestro, cuándo un dato está
viejo— está en el lugar equivocado. La prueba de fuego: si la API tuviera que
contestar lo mismo que esa pantalla, ¿podría? Si no, la lógica está atrapada en
la vista.

Esto no es purismo. La API (`class-api.php`) existe precisamente porque el jefe
pidió consultar el panel desde fuera, y está escrita como una **capa delgada
sobre las mismas funciones que alimentan las pantallas**, para que panel y API
no puedan contradecirse. Esa garantía se cae en el momento en que una vista
calcula algo por su cuenta.

### La regla de las fuentes

Todo lo que el panel sabe de los sitios entra por `SWPK_Fuente`. Kinsta está
detrás de esa interfaz, no esparcido por el código, porque desde la primera
reunión se avisó que esto iba a replicarse en Pantheon y en otros servidores.
Agregar un proveedor es escribir una clase, no rehacer el panel — ver
[12 · Pantheon](12-pantheon.md).

La fuente activa la devuelve `swpk_fuente()`, en `panel-kinsta.php`, y se puede
sustituir con el filtro `swpk_fuente`.

## El recorrido completo de un dato

Así se mueve todo, de punta a punta:

```
  ① LA CORRIDA DIARIA            SWPK_Sincronizador::correr()   05:00
     ├─ inventario de sitios     fuente()->sitios()
     ├─ inventario del día       fuente()->plugins() + temas()   → swpk_fotos
     ├─ feed de vulnerabilidades SWPK_Wordfence::actualizar()    → swpk_vulnerabilidades
     ├─ comparar con ayer        SWPK_Eventos::comparar_fotos()  → swpk_eventos
     └─ caducar órdenes muertas  SWPK_Ordenes::caducar()

  ② EL AGENTE, DESDE CADA SITIO  SWA_Reporte::enviar()           05:00 + al cambiar algo
     POST /swpk/v1/reporte, firmado
     ├─ usuarios con control     → swpk_usuarios
     ├─ estado de dos pasos,
     │  xmlrpc, correo, versiones→ swpk_agentes
     ├─ normas de seguridad      SWPK_Normas::medir()  → swpk_cumplimiento
     └─ ◄ la respuesta trae las órdenes pendientes

  ③ EL MONITOR DE CAÍDAS         SWPK_Monitor::sondear_todos()   cada hora
     → swpk_monitor, y swpk_eventos cuando algo cambia

  ④ LOS AVISOS                   SWPK_Correos::despachar()
     → correo enviado, y swpk_bitacora con la evidencia

  ⑤ LO QUE SE CONSULTA
     pantallas        includes/admin/vistas/
     API              /wp-json/swpk/v1/…
     MCP              /wp-json/swpk/v1/mcp/{token}   (Claude Desktop)
     Excel            SWPK_Exportar
```

Fíjate en una cosa: **nada de esto es en vivo**. El panel toma inventario. Lo
que se ve en pantalla es siempre una foto, nunca el momento, y por eso existe
`SWPK_Frescura` — ver [03](03-de-donde-salen-los-datos.md).

## Los archivos, uno por uno

### El panel

| Archivo | Qué resuelve |
|---|---|
| `panel-kinsta.php` | Carga todo, define `swpk_fuente()`, engancha activación y crons |
| `class-tablas.php` | El esquema: las once tablas y la migración por versión |
| `class-perfiles.php` | Quién ve qué: roles propios, recorte por sitio |
| `class-llaves.php` | Las llaves de consulta, y el cerco para que no sean personas |
| `class-accesos.php` | Entradas, salidas, intentos fallidos y llamadas a la API |
| `class-ajustes.php` | Opciones, y el cifrado de las llaves de Kinsta |
| `class-etiquetas.php` | Traduce las etiquetas del hosting a los cuatro grupos |
| `class-versiones.php` | Comparar versiones sin equivocarse con `1.9` y `1.10` |
| `kinsta/interface-fuente.php` | El contrato con el proveedor de hosting |
| `kinsta/class-fuente-api.php` | Kinsta de verdad, con su límite de peticiones |
| `kinsta/class-fuente-demo.php` | Datos inventados, para desarrollar sin llave |
| `kinsta/class-http.php` | **Solo GET.** La garantía de solo lectura |
| `class-inventario.php` | La tabla de sitios y sus consultas |
| `class-wordfence.php` | El feed de vulnerabilidades y su atribución |
| `class-riesgo.php` | Cruza lo instalado con el feed y decide qué tan grave es |
| `class-textos.php` | Convierte un CVE en una frase que una asesora puede mandar |
| `class-eventos.php` | Qué cambió de un día para otro |
| `class-bitacora.php` | Qué se notificó, a quién y cuándo. **Esto es el producto** |
| `class-plantillas.php` | El HTML de los correos, tres audiencias |
| `class-correos.php` | Cuándo sale cada aviso y a quién |
| `class-usuarios.php` | Vigilancia de quién tiene control de cada sitio |
| `class-normas.php` | Las normas de seguridad y el veredicto de cada sitio |
| `class-ordenes.php` | La cola de lo que el panel le pide a un agente |
| `class-receptor.php` | Las dos rutas por donde entran los agentes |
| `class-api.php` | La API de consulta |
| `class-mcp.php` | El panel hablando MCP, para Claude Desktop |
| `class-monitor.php` | Sondeo de caídas |
| `class-sincronizador.php` | Los pasos de la corrida, y analizar un sitio suelto |
| `class-trabajo.php` | El motor que parte la corrida en tramos y la reanuda |
| `class-frescura.php` | De cuándo es cada dato, y cuándo hay que avisarlo |
| `class-estado.php` | El veredicto del Resumen: qué está mal, con nombre |
| `class-exportar.php` | Excel |
| `admin/class-admin.php` | Menú, pantallas, acciones y estilos |
| `admin/class-vista.php` | Filtros, paginación, migas, plurales |
| `admin/vistas/*.php` | Una pantalla cada uno |

### El agente

| Archivo | Qué resuelve |
|---|---|
| `agente-sitio.php` | Carga, activación, crons |
| `class-ajustes.php` | La dirección del panel, el token y el secreto |
| `class-firma.php` | HMAC-SHA256 sobre cuerpo + marca de tiempo |
| `class-reporte.php` | Arma y manda el reporte, diario y liviano |
| `class-vigilante.php` | El aviso en el momento en que cambia quién tiene control |
| `class-dos-factores.php` | Verificación en dos pasos por código al correo |
| `class-correo.php` | Comprueba que el sitio puede mandar correo |
| `class-normas.php` | Lo que solo se ve desde dentro del sitio |
| `class-ordenes.php` | Recoge y ejecuta lo que el panel dejó pendiente |
| `class-admin.php` | La pantalla de ajustes del agente |

## Nombres: los prefijos

- **`SWPK_`** y `swpk_` — todo lo del panel (Solución Web · Panel Kinsta).
  Tablas: `wp_swpk_sitios`, `wp_swpk_normas`…
- **`SWA_`** y `swa_` — todo lo del agente (Solución Web · Agente).

Nunca se mezclan. Si ves `SWA_` dentro de `panel-kinsta/`, es un error: los dos
plugins no comparten código, solo el formato del mensaje que se mandan.

## Dónde engancharse

Los filtros y acciones que el código ya expone, por si hace falta cambiar algo
sin tocar el núcleo:

| Gancho | Para qué |
|---|---|
| `swpk_fuente` | Sustituir el proveedor de hosting (Pantheon, pruebas) |
| `swpk_wp_minima` | La versión mínima de WordPress que cuenta como «con soporte» |
| `swpk_php_minima` | Lo mismo para PHP |
| `swa_roles_vigilados` | Qué roles reporta el agente (por defecto: administrator, editor, shop_manager) |
