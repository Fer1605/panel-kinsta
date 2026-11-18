# 13 · Qué es cada sitio: tienda, cotizador o informativo

El jefe pidió normas distintas según el tipo de sitio:

> «hay normas web. Cuando tú haces un e-commerce, en el e-commerce te piden que
> **para cobrar** se cumplan ciertas normas. Esas normas hay que revisarlas: si
> es un e-commerce, qué normas deberíamos tener; si es un sitio informativo, qué
> normas deberíamos tener.»

Eso obliga a que el panel sepa qué es cada sitio. Lo decide `SWPK_Tipos`.

## Los cuatro tipos

| Tipo | Qué significa |
|---|---|
| `tienda` | Cobra dinero, en línea o no |
| `cotizador` | Tiene catálogo pero no se puede pagar: se pide presupuesto |
| `informativo` | No vende |
| `sin_dato` | Todavía no se sabe |

## La regla propuesta, y sus dos agujeros

La regla era: tiene WooCommerce activo, es tienda; no lo tiene, es informativo.
El fondo es correcto y el panel ya lo puede hacer solo. Pero medida contra la
cartera real tiene dos agujeros, y los dos importan.

**Uno.** WooCommerce trae **tres pasarelas dentro del núcleo** que no son
plugins: transferencia bancaria (`bacs`), cheque (`cheque`) y contra entrega
(`cod`). El panel no las ve desde fuera. Una tienda puede estar cobrando por
depósito con cero plugins de pago instalados.

Y hay que decirlo claro: **una tienda que solo cobra contra entrega SÍ es un
comercio electrónico.** «No tiene pasarela en línea» no es «no vende».

**Dos.** Un plugin de pasarela activo no es una pasarela encendida. En la
cartera hay un sitio con el plugin de Cybersource instalado y activo en cero.

## Las tres capas

De la más barata a la más certera. Cada sitio guarda también **por qué** quedó
donde quedó, en `tipo_motivo`, que es lo que hace la clasificación discutible
sin tener que rehacerla.

**Capa 1 — el panel, sin agente.** La foto diaria de plugins ya trae el `slug` y
si está activo. Reconoce las pasarelas por el nombre del plugin, incluidas las
de Guatemala: QPayPro, Serfinsa, Recurrente, N1co, PowerTranz, Visanet. Da una
primera clasificación al instante.

Lo que esta capa **no** puede decir es si el sitio cobra por transferencia o
contra entrega. Por eso, cuando encuentra WooCommerce activo y ningún plugin de
pasarela, el motivo lo dice: *«puede cobrar por transferencia o contra entrega,
que vienen dentro de WooCommerce: hace falta el agente para saberlo»*.

**Capa 2 — el agente lee los ajustes.** Cierra los dos agujeros:

- Qué pasarelas están **encendidas** (`enabled === 'yes'`), no instaladas.
- Cuáles de ellas cobran dinero, contando las tres del núcleo.
- Si el catálogo tiene productos comprables. Un producto publicado con el precio
  vacío **no es comprable** —así lo resuelve el propio WooCommerce— y es la
  huella de un catálogo montado para cotizar.
- Si el checkout está publicado.

**Capa 3 — la que zanja.** ¿Alguna vez cobró? Un pedido pagado con total mayor
que cero y un método de pago real significa que **esa tienda está en alcance de
PCI, digan lo que digan los ajustes**. Es la evidencia más fuerte y es una
consulta barata.

## Tres detalles que se pasan por alto

1. **Multisitio.** Un plugin activado en toda la red **no aparece** en el
   `active_plugins` del sitio. Hay que mirar también `active_sitewide_plugins`.
   Sin eso, todo cliente en multisitio sale como «no activo».
2. **La carpeta se puede renombrar.** Es más robusto buscar por
   `TextDomain === 'woocommerce'` que por la ruta del archivo.
3. **Cargado contra configurado.** `defined('WC_VERSION')` es lo único que
   detecta un WooCommerce cargado por un mu-plugin o un cargador propio. Y para
   comparar versiones vulnerables hay que usar la del **archivo**, no la de la
   base: si no coinciden, hay una actualización de base pendiente.

## Una trampa que costó encontrar

El motivo del cotizador medía 210 caracteres y la columna `tipo_motivo` son 191.
Con MySQL en modo estricto eso **no recorta: rechaza el UPDATE entero, en
silencio**. Veintiún sitios se quedaban sin clasificar mientras la función
devolvía «cotizador» correctamente.

Por eso `SWPK_Tipos::clasificar()` recorta el motivo antes de escribir. Si
alguien alarga esos textos, que sepa por qué está ese `mb_substr`.

## Cómo se usa

Una norma con `aplica_tipo` vacío se mide en todos. Con `tienda,cotizador` solo
en esos. Y **un sitio sin clasificar recibe todas las normas**: librarlo por no
saber qué es sería esconder el hueco en vez de verlo.

`aplica_tipo` es un eje distinto de `aplica_a`. El primero es qué hace el sitio;
el segundo son las etiquetas del contrato de mantenimiento. Un sitio puede ser
`auto_update` y tienda a la vez, así que se miran por separado.
