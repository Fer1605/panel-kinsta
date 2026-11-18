# 06 · Normas de seguridad

Esto salió del audio del 13 de noviembre de 2026. Son tres pedidos:

1. **Una lista de normas, editable**, y un análisis de cada sitio contra ella.
   *«que ya podamos agregar más, podamos quitar»*
2. **Que además de medir, se pueda arreglar, con registro.**
   *«a este le faltaba doble autenticación, ¿hago, procedo, no procedo?»*
3. **Reseteo masivo de contraseñas**, para cuando alguien se va del equipo.

Y una aclaración suya que define el alcance: las notificaciones de
actualizaciones **no** son esto. *«eso no es tema de seguridad.»*

---

## Las piezas

```
   PANEL                                        SITIO DEL CLIENTE

   SWPK_Normas          ── mide con ──┐
   catálogo + veredicto               │
        │                             │
        ▼                             │         SWA_Normas
   swpk_cumplimiento   ◄───────── el reporte ──  editor, registro, uploads
   sitio × norma                      ▲
                                      │
   SWPK_Ordenes        ── la cola ─────┘         SWA_Ordenes
   lo que se pidió      la respuesta             ejecuta y contesta
        │               del reporte
        ▼
   swpk_ordenes
```

---

## Las 73 normas

Arrancaron siendo diez, todas medidas por el agente. Hoy son **73**, y lo que
cambió no es solo el número: cambió **quién las mide**, y eso es lo que decide
cuáles funcionan sin instalar nada.

| Quién mide | Normas | ¿Necesita el agente? |
|---|---|---|
| El agente, dentro del sitio | 36 | sí |
| La sonda externa del panel | 24 | no |
| La API de la plataforma | 10 | no |
| El inventario ya guardado | 3 | no |

**37 de las 73 funcionan sin instalar nada en ningún sitio.** Las otras 36 son
las que solo se ven desde dentro — y son también las más graves.

Por gravedad: 40 altas, 26 medias, 7 bajas. Ocho se miden solo en tiendas.

### Las cuatro fuentes, y por qué cada norma está donde está

**El inventario** (`inv_*`). Tres normas que salen de la foto diaria de plugins
y de la tabla de sitios, que la corrida ya guarda. Volver a pedirlas al hosting
serían 108 peticiones para traer lo que está en la base. Son los plugins en
versión con falla conocida, los instalados pero apagados, y el estado del sitio.

**La sonda externa** (`SWPK_Sonda`). Veinticuatro normas que preguntan,
literalmente, qué ve un desconocido desde internet: archivos que no deberían
estar donde se pueden bajar, y cómo viaja y cómo se enmarca la página. Son unas
cuarenta peticiones sin credenciales por sitio, una vez al día a las 5:30.

**La plataforma** (`SWPK_Revision`, `rev_*`). Diez normas que hay que pedirle al
hosting: protección de bots, respaldos, HTTPS forzado, tráfico, errores,
registros. Trece lecturas por sitio, a las 4:30, y unos 25 minutos para los 108
porque el límite de peticiones por minuto no se puede apurar.

**El agente** (`SWA_Formularios`, `SWA_Fondo`, `SWA_Tienda`). Treinta y seis
normas que no se pueden medir de ninguna otra forma: los archivos que sube la
gente por un formulario, las constantes de `wp-config.php`, los permisos de cada
rol, los archivos del núcleo contra las sumas oficiales, los plugins que
WordPress.org cerró, y todo lo de las tiendas.

### Las tres reglas que hacen que esto sirva y no sea ruido

Están en el código de la sonda y conviene no deshacerlas:

**1. Nunca concluir por el código HTTP solo.** Muchos sitios contestan 200 con
la página de error del tema. Cada ruta trae el patrón que su contenido tiene que
calzar, y si no calza es «no está» aunque el código sea 200.

**2. «Bloqueado» no es «aprobado».** Un 403 del cortafuegos y un «ese archivo no
existe» se parecen desde fuera. El `.git` puede estar ahí detrás de una regla
que alguien apague mañana. Por eso hay cuatro estados y no dos.

**3. Una sonda de control por sitio.** Se pide una dirección que con certeza no
existe. Si el sitio contesta a esa como si existiera, sus comprobaciones de
archivos no valen y se marcan así en bloque, en vez de producir veinte hallazgos
falsos. En la cartera hay un sitio que contesta 200 con 15 KB de HTML a
cualquier dirección.

### Un 403 son tres cosas distintas

Esto decidió si el bloque de exposición servía o no. Al principio cualquier 403
se contaba como «no se sabe», y en la plataforma eso dejaba las veintidós normas
en «sin dato» en todos los sitios. Midiendo se vio que hay tres cosas detrás:

```
/wp-config.php.bak     403     /loquesea.php.bak           403   regla por extensión
/composer.json         403     /carpeta-inventada/…json    403   regla por nombre
/phpinfo.php           403     «Just a moment…»                  reto al sondeo
```

Las dos primeras son la plataforma negándose a servir **ese tipo de archivo**,
exista o no: nadie lo puede bajar, y eso sí es protección. La tercera es un reto
que cualquier navegador de verdad pasa — o sea que el archivo **no** está
protegido de una persona, solo del sondeo. Esa queda en «no se sabe».

Se distinguen con un control por extensión en la primera pasada y, solo para lo
que queda sin explicar, un segundo control con el mismo nombre bajo una carpeta
inventada.

### Por qué 16 normas y no 45 en el bloque del agente

La investigación sacó cuarenta y cinco comprobaciones medibles dentro del sitio:
dieciocho constantes de `wp-config.php`, nueve de usuarios, catorce de
integridad, cuatro de registro. Están todas, pero agrupadas en dieciséis normas
que significan algo.

Una pantalla con cuarenta y cinco filas por sitio, en 108 sitios, no la mira
nadie — y **una norma que nadie mira es peor que ninguna**, porque da la
impresión de que se está revisando. Cuando una falla, el detalle dice cuál de
las comprobaciones de dentro fue.

### Lo que deliberadamente NO se marca como incumplimiento

Marcar todo es la forma más rápida de que nadie mire el tablero. Estas se miden
y no se marcan, cada una con su motivo escrito en el código:

| Qué | Por qué no |
|---|---|
| Rastreadores de IA desbloqueados | Vienen así de fábrica y no se distingue una decisión de un descuido. Serían 108 filas rojas por algo que no es seguridad |
| `unfiltered_html` en el rol Editor | Lo trae WordPress de fábrica. Acusaría a los 108 de una decisión que no tomó nadie |
| Retención de datos sin configurar en tiendas | Es una decisión legítima: hay deberes contables que obligan a guardar. Lo que sí falla es tenerla puesta con la tarea muerta |
| Pago sin cuenta en WooCommerce | Es el valor por omisión, etiquetado «recomendado» por el propio WooCommerce |
| `WP_DEBUG` encendido a secas | Con la pantalla apagada y el registro fuera de la raíz es defendible. Lo que se marca es que un error salga a la vista |
| Un rol por omisión alto con el registro cerrado | Nadie se puede registrar, así que no hay por dónde entrar |

Y veintiuna comprobaciones más se descartaron con el motivo escrito en
`NORMAS-catalogo.md`: el prefijo de tabla distinto de `wp_` (no está en ningún
estándar), la cabecera `X-XSS-Protection` (obsoleta: si se manda, en cero), los
permisos de archivo (los gestiona el hosting), el prefijo `__Host-` en las
cookies (WordPress no lo hace, fallaría igual en los 108).

### Dos ejes, y no hay que revolverlos

Una norma puede limitarse por dos cosas distintas:

- **`aplica_a`** — las etiquetas del contrato de mantenimiento: desarrollo, auto
  update, no auto update.
- **`aplica_tipo`** — qué hace el sitio: tienda, cotizador, informativo.

Son independientes. Un sitio puede ser `auto_update` **y** tienda a la vez. Ver
[13 · Qué es cada sitio](13-que-es-cada-sitio.md).

## Dos textos por norma, y hacen falta los dos

Una norma lleva `nombre` y `problema`, y confundirlos tenía un coste real.

La primera versión solo tenía el nombre, en afirmativo, que es lo correcto para
una regla. El tablero lo usaba como título de los bloques de fallos, así que la
pantalla decía:

> **No existe un usuario llamado «admin»**
> Colegio los Andes — Existe un usuario llamado «admin».

El panel se contradecía solo. Quien lo leía tenía que deducir que el título era
la regla y la fila el incumplimiento, y nadie debería tener que deducir eso.

Ahora la lista de normas usa `nombre` y la lista de fallos usa `problema`. Una
norma escrita a mano sin `problema` cae en «No se cumple: {nombre}», que al
menos no se contradice. `SWPK_Normas::problema()` lo resuelve.

**Y la regla no se repite debajo del problema.** El primer arreglo ponía las
dos —«Existe un usuario llamado admin» como título y «La norma dice: No existe
un usuario llamado admin» debajo— y seguía sin entenderse. Dos frases opuestas
pegadas obligan a quien lee a decidir cuál es cuál, que es exactamente el
trabajo que no debería tener que hacer. La regla vive en «Qué revisamos», que
es donde se va a buscarla.

### El `porque` es parte del producto, no un adorno

El texto que explica por qué importa una norma se leyó en voz alta y no se
entendió. «Es el primer nombre que prueba cualquier ataque automático. Con el
nombre acertado, solo les falta la contraseña» es correcto y es ilegible para
quien no sabe ya la respuesta.

El criterio para reescribirlos: **explicar el mecanismo, no resumirlo.** La
versión que sí se entiende dice que para entrar hacen falta dos datos, que el
ataque tiene que adivinar los dos, y que «admin» le regala uno. Son tres
frases en vez de una, y es la diferencia entre que alguien lo entienda y que
asienta sin entenderlo.

## Los cuatro veredictos

| Estado | Qué quiere decir |
|---|---|
| `cumple` | Se midió y está bien |
| `falla` | Se midió y está mal |
| `sin_dato` | **No se sabe.** Casi siempre: ese sitio no tiene el agente |
| `no_aplica` | La norma no aplica a ese grupo de sitios |

**`sin_dato` no es `falla` y tampoco es `cumple`.** Un sitio sin agente
aparece «sin medir» en las diez, y el tablero lo dice con esas palabras y en su
propio apartado: *«No es una lista de problemas: es la lista de lo que el panel
no sabe.»*

---

## Cómo se mide

`SWPK_Normas::medir( $sitio_id, $reporte )` se llama **desde el receptor, con
el reporte en la mano**. No va a buscar nada afuera: todo lo que necesita
acaba de llegar.

Cada norma tiene un `comprobador`, que es un método `comprobar_<clave>` en
`SWPK_Normas`. Recibe el reporte y la fila del sitio, y devuelve
`[ 'estado', 'detalle' ]`. El detalle es texto ya redactado para la pantalla.

Las declarativas se saltan: si se midieran aquí, cada reporte diario borraría
el «revisado» que alguien puso ayer.

El veredicto se guarda en `swpk_cumplimiento` con `medido_en`. Un sitio que no
reporta no se mide — y eso se ve, porque su fila queda en `sin_dato` con la
fecha de la última vez.

### Agregar un comprobador

1. Mete la norma en `SWPK_Normas::catalogo()` con su `comprobador`.
2. Escribe `private static function comprobar_<comprobador>( array $r, ?object $sitio ): array`.
3. Si el dato viene del sitio, agrégalo en `SWA_Normas::medir()` y léelo de
   `$r['normas']['tu_clave']`.
4. Sube `VERSION_CATALOGO`.

**La regla de oro del comprobador:** si el dato no está, devuelve `SIN_DATO`,
no `FALLA`. Confundir «no lo sé» con «está mal» haría inservible todo el
tablero — con 107 sitios sin agente, serían 1.070 alarmas falsas.

---

## Las normas que escribe una persona

Alguien puede agregar una norma desde la pantalla. Nace **declarativa**, porque
nadie puede programar un comprobador desde un formulario.

La pantalla lo dice antes de guardar, no después: *«Una norma nueva no se
comprueba sola. Va a aparecer en el tablero como "sin medir" hasta que alguien
la marque, sitio por sitio.»*

Marcarla a mano (`SWPK_Normas::marcar()`) deja el nombre de quien la marcó y la
fecha. Es lo más parecido a una firma que tiene el panel: no prueba que el
honeypot esté puesto, prueba que alguien dijo que lo revisó, cuándo y quién.
Que es exactamente lo que vale en una auditoría.

De una norma de fábrica solo se deja cambiar lo que no rompe nada: a qué sitios
aplica, qué tan grave es, y si está encendida. El nombre y el porqué los pone
el código y se reescriben en cada siembra — dejar que se editen sería prometer
un cambio que la siguiente actualización borra sin avisar.

---

## Lo que la pantalla NO hace, y por qué

Hubo una **cuadrícula completa**: 108 filas de sitios por 10 columnas de
normas, plegada al final. Se quitó. Con un solo sitio medido eran 1.070 celdas
con un signo de interrogación, y la reacción de quien la vio fue exacta:
«demasiado ruido visual, todo aparece como incógnito».

En su lugar va la lista de los sitios que **sí** se pueden revisar, con su
cuenta. Hoy es una fila; cuando el plugin esté instalado será la pantalla útil.
Si alguna vez hace falta la matriz para una auditoría, que vuelva — pero
mostrando solo los sitios medidos, no 107 columnas de interrogaciones.

## Qué hace «Arreglarlo», dicho en la pantalla

La pregunta que salió al probarlo fue literal: *«si yo le doy a arreglar,
¿cómo se arregla? ¿el panel envía y se modifica algo, o es solo lo que
hacemos a mano?»*.

Es la pregunta justa, y la pantalla no la contestaba. «Arreglar» en un panel
puede querer decir cuatro cosas distintas, de «lo anoto para que alguien lo
haga» a «lo acabo de cambiar». Si no se dice cuál, se pulsa a ciegas — y esto
cambia configuración en el sitio de un cliente.

Ahora lo dice antes del primer botón: el plugin del sitio ejecuta el cambio de
verdad, sin que nadie entre; tarda entre minutos y horas; y lo que el plugin
no sabe hacer sale marcado como «Hay que hacerlo a mano».

## El canal de órdenes

### Por qué una cola y no una llamada

Lo natural sería que el panel llamara al sitio. Eso obligaría a que cada sitio
de cliente dejara un endpoint esperando peticiones de internet, y es
exactamente lo que este proyecto prometió por escrito que no iba a hacer.

Así que la dirección del tráfico no cambia:

1. El panel deja la orden anotada en `swpk_ordenes`.
2. El agente, cuando pasa a reportar, se la lleva en la respuesta.
3. La ejecuta y contesta en el siguiente viaje.

Nadie llama al sitio. La única puerta sigue siendo la del panel, y sigue
estando firmada con el secreto de ese sitio.

### El precio, dicho de frente

**Una orden no es inmediata.** El agente pregunta cada cinco minutos, y ese
reloj en WordPress depende de que alguien visite el sitio. Un sitio con tráfico
contesta en minutos; uno que nadie visita de noche contesta por la mañana.

**No se puede prometer masivo e instantáneo**, y la pantalla lo dice antes de
que nadie pulse el botón, no después.

### Los estados

| Estado | En pantalla | Qué quiere decir |
|---|---|---|
| `pendiente` | En cola | El panel lo apuntó; el sitio no ha venido a recogerlo |
| `entregada` | Aplicándose | El sitio se lo llevó y lo está haciendo |
| `hecha` | Listo | El sitio lo hizo y lo confirmó |
| `fallo` | No se pudo | Lo intentó y no pudo, o se lo llevó y nunca contestó |
| `vencida` | Caducó | La contraseña del lote venció antes de entregarse |
| `cancelada` | Reemplazada | Se pidió otra vez lo mismo antes de recogerlo |

Las etiquetas de pantalla son de dos palabras a propósito. Las primeras
—«Esperando que el sitio pregunte», «El sitio ya se la llevó»— describían bien
el mecanismo y no las entendía nadie: hablan de cómo viaja una orden por
dentro, cuando lo que hace falta saber es en qué va el encargo. La explicación
larga vive en `SWPK_Ordenes::explicacion()` y sale debajo de la etiqueta.

### Y una regla de estilo que salió de aquí

Las pantallas **no explican nuestras decisiones**. Había párrafos que decían
«así ningún sitio de cliente tiene que dejar una puerta abierta a internet,
que es la promesa de fondo de todo este proyecto». Eso es cierto, está bien
razonado, y no le toca a quien usa el panel: a esa persona le toca saber que
un arreglo tarda entre minutos y horas.

El porqué de las decisiones va en esta carpeta y en los comentarios del
código. En pantalla van los hechos del producto.

Una orden entregada que nunca se resuelve se cierra como `fallo` a las 24
horas, en la corrida diaria. Dejarla en `entregada` para siempre haría que el
panel mostrara como «en camino» algo que ya no va a llegar.

### Las barreras

`SWPK_Ordenes::encolar()` se niega en tres casos:

1. **Un sitio del cliente** (`no_update`). La regla escrita es «no se le toca
   nada sin pedirlo». Se mide, no se cambia.
2. **Un sitio sin agente.** No hay a quién dejarle la orden.
3. **Un tipo que no está en la lista.** Seis verbos y nada más.

Y una cuarta, la deduplicación: pulsar «Arreglar» dos veces no encola dos.

### La deduplicación tiene una trampa, y costó encontrarla

Comparar **solo por tipo** tenía un agujero grave: pedir el cambio de
contraseña de «pedro» cuando ya había uno pendiente de «ana» devolvía la orden
de ana y el panel decía «listo». Se pedía una cosa y pasaba otra.

La corrección tiene dos partes:

- Se compara **también la carga**. Dos órdenes del mismo tipo con distinta
  carga son dos órdenes distintas.
- **Un cambio de contraseña nunca es un duplicado**, aunque sea del mismo
  usuario. La contraseña no viaja en la carga —vive aparte, en el lote— así que
  dos peticiones de «ana» se ven idénticas en la tabla y son dos contraseñas
  distintas. Así que esas no pasan por la deduplicación: pasan por la
  sustitución. Manda la última, y la anterior queda anotada como `cancelada`.

Si tocas `encolar()`, ten esto presente: **lo que no está en `carga` no se
puede comparar**.

---

## El reseteo masivo de contraseñas

Para cuando alguien se va del equipo. *«mañana se va alguien y resulta que
tiene las contraseñas y todo conectado; resetea la contraseña masiva de todos y
se cambian todas por una que yo quiera.»*

**Alcance:** el usuario administrador nuestro, no los usuarios del cliente. La
pantalla ofrece los logins que el panel ya conoce, con en cuántos sitios
aparece cada uno.

### La contraseña no se guarda

Si se guardara en `swpk_ordenes`, el panel pasaría a ser el único lugar del
mundo donde están en claro las contraseñas de administrador de todos los
sitios. Que es, literalmente, el problema que esto viene a resolver.

Entonces:

- La contraseña **no entra en la columna `carga`**.
- Vive en un transient (`swpk_clave_lote_<lote>`) con **dos horas** de vida.
- Se le inyecta al agente en el momento en que pregunta.
- Cuando el transient vence, las órdenes que no se alcanzaron a entregar se
  marcan `vencida` y hay que volver a pedirlas.

Es menos cómodo que guardarla, y es la diferencia entre un panel y un llavero.

Dos horas es el equilibrio: suficiente para que casi todos los sitios hayan
preguntado al menos una vez, y poco para que no quede ahí el fin de semana.

**Comprobado, no supuesto:** se buscó la contraseña columna por columna en las
seis tablas del panel después de un reseteo. No aparece en ninguna. El único
lugar donde está es el transient, con su caducidad.

### El orden importa

En `reseteo_masivo()`, la contraseña se guarda **antes** de encolar. Si se
encolaran primero y fallara el guardado, quedarían órdenes esperando una clave
que no existe. Y si no se encola ninguna, el transient se borra.

### Lo que no se sanitiza

La contraseña **no** pasa por `sanitize_text_field`: eso le quitaría caracteres
y dejaría en los sitios una contraseña distinta de la que se escribió. Se usa
tal cual llegó, que es lo que hace WordPress con cualquier contraseña.

Mínimo 12 caracteres. Una contraseña que se va a poner en varios sitios a la
vez es el peor lugar para ahorrar.

---

## Lo que queda pendiente de decidir

Del plan original, esto sigue sin respuesta del jefe y conviene no darlo por
resuelto:

1. **¿El panel pasa de observar a poder cambiar cosas en los sitios?** Está
   construido, con aprobación y registro en cada paso, pero la decisión de
   fondo sigue siendo suya.
2. **¿Se acepta que una orden tarde?** Por lo del cron de WordPress.
3. **¿Cuál es la lista de la primera versión?** Están las diez; se pueden
   apagar las que sobren.

Y la dependencia que bloquea todo: **el agente no está instalado en ningún
sitio**. Sin agente no hay norma que comprobar ni orden que ejecutar.
