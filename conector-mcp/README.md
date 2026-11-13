# Conector MCP del Panel Kinsta — la vía local

> **Lo normal ya no es esto.** El panel sirve MCP por su cuenta: en Claude
> Desktop se agrega como conector personalizado pegando una dirección, sin bajar
> nada y sin Node. La dirección sale al crear una llave, en *Conectarse (API)*.
>
> Esto de aquí es la vía secundaria, y sirve para una cosa que la otra no puede:
> **hablar con un panel que corre en tu propia máquina.** El conector remoto no
> llega a `localhost`, porque quien abre la conexión es la nube de Anthropic y
> no tu computadora. Para probar en local, esto; para todo lo demás, la URL.

Esto es lo que hace que **Claude Desktop pueda preguntarle a un panel local**.

El panel ya contesta por API. Pero Claude Desktop no consume una API de ese
tipo: no hay dónde pegarle una dirección con usuario y contraseña. Habla un
protocolo distinto, MCP. Este archivo es el traductor en medio — de un lado
habla MCP con Claude, del otro habla HTTP con el panel.

Una vez puesto, se le puede preguntar en español:

> ¿Qué sitios necesitan atención hoy?
> ¿Cuántas veces se actualizó tal proyecto este mes?
> ¿Quién tiene acceso de administrador a este sitio?
> Dame los sitios con vulnerabilidades críticas

Y contesta con los datos del panel, no con suposiciones.

---

## Lo que hace falta

- **Node 18 o más nuevo.** Comprobarlo con `node -v`. Si no está, se baja de
  [nodejs.org](https://nodejs.org).
- **Una llave del panel.** Se saca del panel mismo: *Panel Kinsta →
  Conectarse (API) → Crear la llave*. Sale una ventana con la llave y un botón
  para copiarla. **Se enseña una sola vez**; si se pierde, se revoca y se crea
  otra.
- **Que el panel sea alcanzable desde la computadora donde corre Claude
  Desktop.** Esto es lo que hoy no se cumple: el panel vive en una máquina de
  desarrollo y desde fuera no existe. Mientras siga así, el conector solo
  funciona en esa misma máquina.

## 1 · Comprobar la conexión antes de tocar Claude Desktop

Vale la pena hacerlo primero: si algo está mal, aquí se ve el motivo en una
línea, en vez de un "falló" sin explicación dentro de la aplicación.

```bash
PANEL_URL=https://panel.ejemplo.com PANEL_LLAVE='llave-claude:AbCd 1234 EfGh' node index.mjs --probar
```

Si sirve, contesta algo así:

```
Contestó. El panel dice:
  20 sitios necesitan atención hoy
  dato obtenido: 2026-11-13 07:20:44
  corrida al día: sí

Herramientas disponibles: 7
La conexión sirve. Ya se le puede dar a Claude Desktop.
```

## 2 · Ponerlo en Claude Desktop

El archivo de configuración está en:

- **macOS:** `~/Library/Application Support/Claude/claude_desktop_config.json`
- **Windows:** `%APPDATA%\Claude\claude_desktop_config.json`

Si no existe, se crea. Si ya existe y tiene otros conectores, se agrega
`panel-kinsta` dentro de `mcpServers`, sin borrar los demás:

```json
{
  "mcpServers": {
    "panel-kinsta": {
      "command": "node",
      "args": ["/ruta/completa/a/conector-mcp/index.mjs"],
      "env": {
        "PANEL_URL": "https://panel.ejemplo.com",
        "PANEL_LLAVE": "llave-claude:AbCd 1234 EfGh"
      }
    }
  }
}
```

Tres cosas que se equivocan siempre:

- **`args` lleva la ruta completa**, no `./index.mjs`. Claude Desktop no arranca
  desde esta carpeta.
- **`PANEL_LLAVE` va completa, con los espacios** si los trae. Entre comillas.
- **Hay que cerrar y volver a abrir Claude Desktop.** Los conectores se leen al
  arrancar.

Después, en Claude Desktop, el conector aparece en el menú de herramientas y ya
se le puede preguntar.

## 3 · Qué puede y qué no

Siete herramientas, todas de lectura:

| Herramienta | Contesta |
|---|---|
| `panel_estado` | El veredicto de hoy: qué necesita atención, qué está caído, de qué no se sabe |
| `panel_sitios` | La cartera, con filtros por grupo, nivel de riesgo o nombre |
| `panel_sitio` | La ficha completa de un sitio |
| `panel_vulnerabilidades` | Los hallazgos de toda la cartera, uno por fila |
| `panel_usuarios` | Quién tiene control de cada sitio nuestro |
| `panel_eventos` | Qué cambió y cuándo, por rango de fechas |
| `panel_bitacora` | Qué se le notificó a quién, y si el correo salió |

**No escribe nada, y no dispara procesos.** La corrida completa y el análisis de
un sitio tienen su botón en el panel, y se quedan ahí a propósito: una
conversación no debería poder lanzar más de 200 peticiones al hosting porque a
alguien le pareció buena idea a media pregunta. Y la llave tampoco podría — el
panel le contestaría 403.

Toda respuesta trae **de cuándo es el dato**: cuándo se le preguntó al hosting,
si la corrida del día pasó, y si algún sitio no contestó. Un dato sin fecha es
una afirmación sin fecha.

## 4 · Si algo no funciona

Los mensajes de error dicen la causa, no "error". Los tres que se ven:

| Dice | Es |
|---|---|
| *No se pudo llegar al panel en…* | El panel está apagado, o la dirección es `localhost` y Claude Desktop corre en otra máquina |
| *La llave no sirve…* | Mal copiada (le faltan los espacios), o la revocaron desde el panel |
| *Esa llave puede consultar, pero no esto* | Se pidió algo que una llave de solo lectura no alcanza |

Si Claude Desktop no muestra el conector, casi siempre es el JSON: una coma de
más, una ruta relativa, o que no se reinició la aplicación.

## 5 · La llave, por si se la pasan a alguien

Una llave es una cuenta de solo lectura y **no se puede ascender**: no se edita,
no se borra y no se le cambia la contraseña desde la pantalla de usuarios de
WordPress, ni siendo administrador. Las dos únicas cosas que se le hacen son
crearla y revocarla, y las dos están en el panel.

Cada consulta que pasa por aquí queda escrita en el panel, en *Conectarse
(API)*, con la hora y lo que se pidió. Hace falta porque una contraseña de
aplicación se salta la verificación en dos pasos: si no se anotara, una llave
filtrada podría leer la cartera entera sin dejar rastro.
