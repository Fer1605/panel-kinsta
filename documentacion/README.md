# Documentación del Panel Kinsta

Esto es el manual de cómo está hecho el panel y por qué. No es el `README.md`
de la raíz —ese cuenta qué hace el panel para alguien que llega de fuera— sino
lo que hace falta saber para **abrirlo y cambiarlo** sin romper nada.

Está escrito para dos lectores a la vez:

- **Una persona** que entra a tocar el código por primera vez.
- **Una inteligencia artificial** a la que se le entrega el repositorio y se le
  pide que mejore algo. Por eso cada archivo dice no solo qué hace el código,
  sino **qué reglas no se pueden romper** y **por qué se decidió así**. Un
  cambio que parece una mejora obvia puede estar rompiendo una promesa que se
  le hizo por escrito a un cliente.

## Por dónde empezar

Si solo vas a leer tres, que sean estos, en este orden:

1. **[01 · Cómo está armado](01-como-esta-armado.md)** — las piezas y cómo
   encajan. Sin esto, todo lo demás son archivos sueltos.
2. **[10 · Las decisiones](10-decisiones.md)** — lo que se decidió a propósito
   y qué pasa si se cambia. Es el archivo que evita que una mejora deshaga algo
   que costó entender.
3. **[11 · Cómo trabajar en esto](11-como-trabajar-en-esto.md)** — las
   convenciones de la casa: cómo se nombra, cómo se comenta, cómo se prueba, y
   cómo se agrega cada tipo de cosa.

## Todo lo que hay

| | Archivo | De qué trata |
|---|---|---|
| 01 | [Cómo está armado](01-como-esta-armado.md) | Los dos plugins, las capas, por dónde entra y sale cada dato |
| 02 | [La base de datos](02-la-base-de-datos.md) | Las once tablas, columna por columna, y por qué existe cada una |
| 03 | [De dónde salen los datos](03-de-donde-salen-los-datos.md) | Kinsta, Wordfence, el agente, el modo de prueba, y la frescura |
| 04 | [El agente](04-el-agente.md) | El plugin que vive en cada sitio: qué reporta, qué hace y qué no |
| 05 | [Las pantallas](05-las-pantallas.md) | Cada pantalla, qué pregunta contesta y qué deliberadamente no hace |
| 06 | [Normas de seguridad](06-normas-de-seguridad.md) | **Las 73 normas**, quién mide cada una, y lo que deliberadamente no se marca |
| 07 | [La API y el MCP](07-la-api-y-el-mcp.md) | Las rutas, cómo se autentica y cómo se conecta Claude Desktop |
| 08 | [Permisos y llaves](08-permisos-y-llaves.md) | Perfiles, recorte por sitio, llaves de consulta, dos pasos |
| 09 | [Avisos y bitácora](09-avisos-y-bitacora.md) | Los correos automáticos y la evidencia de lo que se notificó |
| 10 | [Las decisiones](10-decisiones.md) | Lo que no es obvio, y por qué está así |
| 11 | [Cómo trabajar en esto](11-como-trabajar-en-esto.md) | Convenciones, cómo agregar cosas, cómo probar |
| 12 | [Pantheon](12-pantheon.md) | El plan para que el panel lea sitios que no están en Kinsta |
| 13 | [Qué es cada sitio](13-que-es-cada-sitio.md) | Tienda, cotizador o informativo: las tres capas y los dos agujeros de la regla simple |
| 14 | [Cadencia y lo que cuesta](14-cadencia-y-lo-que-cuesta.md) | Cada cuánto pasa cada cosa, qué se puede programar y qué exige pagar |

## Las cuatro reglas que no se tocan

Si vas a cambiar algo y tu cambio choca con una de estas, **para y pregunta**.
No son preferencias de estilo: son promesas hechas por escrito, y tres de ellas
están además escritas en el documento que se le entregó al jefe.

1. **El panel nunca escribe en Kinsta.** `SWPK_Http` implementa un solo método,
   `get`, y no hay en todo el repositorio una llamada que cree, borre, reinicie
   o actualice nada en la cuenta de hosting. La llave de Kinsta no se puede
   limitar desde Kinsta —no hay llaves de solo lectura— así que la garantía
   está en el código y es auditable: buscar `wp_remote_` devuelve solo GET.

2. **El sitio del cliente no abre ninguna puerta a internet.** El sitio llama
   al panel, nunca al revés. No queda un endpoint esperando peticiones. Cuando
   hizo falta que el panel le pidiera algo a un sitio, se resolvió con una cola
   que el sitio consulta —ver [06](06-normas-de-seguridad.md)—, no abriendo una
   puerta.

3. **No se dice «está bien» cuando lo que pasa es «no lo sé».** Un sitio del
   que no hay datos no se muestra como sano. Esto aparece por todas partes: en
   `SWPK_Riesgo::SIN_DATOS`, en el estado `sin_dato` de las normas, en el aviso
   de frescura, en `'no_se_pudo'` de la prueba de subidas. Es la razón de ser de
   medio panel y la primera cosa que se rompe al «simplificar».

4. **A los sitios del cliente se les mide, no se les toca.** Los que llevan la
   etiqueta `no_update` los administra el cliente. El panel los vigila y avisa,
   pero no les cambia nada sin que lo pidan.

## Cómo está escrito el código

En español, y los comentarios dicen **por qué**, no qué. Hay mucho comentario y
es a propósito: casi todas las decisiones de este panel vienen de una reunión
concreta o de un problema que se descubrió probando, y esa razón no se deduce
leyendo el código. Ver [11 · Cómo trabajar en esto](11-como-trabajar-en-esto.md).

Si vas a agregar código, agrégalo en el mismo idioma y con el mismo criterio:
un comentario que explique qué hace la línea de abajo sobra; uno que explique
por qué no se hizo de la forma obvia, vale oro.
