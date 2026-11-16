# 11 · Cómo trabajar en esto

## Montarlo en local

Hace falta PHP 8.1 o superior y WordPress 6.4 o superior. Con
[Studio](https://developer.wordpress.com/studio/):

```bash
studio create --path ~/Studio/panel --name "Panel" --php 8.3
ln -s /ruta/a/este/repo/panel-kinsta ~/Studio/panel/wp-content/plugins/panel-kinsta
cd ~/Studio/panel && studio wp plugin activate panel-kinsta
```

Y la primera corrida:

```bash
studio wp eval 'print_r( SWPK_Sincronizador::correr() );'
```

Arranca en **modo de prueba**: sin llave de Kinsta, con sitios y
vulnerabilidades inventados. Se puede desarrollar el panel entero sin tocar
Kinsta ni un sitio de cliente.

Para probar el agente hace falta un segundo WordPress con `agente-sitio/`
enlazado, registrado contra el primero.

### Dos tropiezos del entorno local que cuestan una hora

**1. `studio wp db query` no funciona.** No encuentra el binario de mysql. Usa
`wp eval` con `$wpdb`:

```bash
studio wp eval 'global $wpdb; print_r( $wpdb->get_results( "SELECT * FROM " . SWPK_Tablas::tabla( "normas" ) ) );'
```

**2. Los sitios de Studio escuchan en IPv6.** Si el agente apunta a
`http://localhost:8882`, PHP resuelve primero `127.0.0.1`, se cuelga unos
18 segundos y después cae a `::1`. Parece que el panel está lentísimo y no lo
está. Para probar, apunta el agente a `http://[::1]:8882`:

```bash
studio wp eval 'SWA_Ajustes::guardar( [ "panel_url" => "http://[::1]:8882" ] );'
```

---

## Cómo se escribe el código aquí

### En español, y los comentarios dicen por qué

Nombres de clases, métodos, variables, tablas y columnas: en español. Y los
comentarios explican **por qué**, no qué.

```php
// Mal — repite lo que ya dice la línea
// Recorre los sitios
foreach ( $sitios as $s ) {

// Bien — dice lo que el código no puede decir
// Se recalcula aquí en vez de confiar en lo que la pantalla tenía: entre que
// se cargó y se pulsó pudo llegar un reporte y cambiar quién la cumple.
foreach ( SWPK_Perfiles::sitios_visibles() as $s ) {
```

Hay mucho comentario y es a propósito. Casi todas las decisiones de este panel
vienen de una reunión concreta o de un problema que se descubrió probando, y
esa razón no se deduce leyendo el código. Cuando una decisión viene de algo que
dijo el jefe, **se cita con el minuto o la fecha**.

### Las palabras que se usan en pantalla

Todo lo que se ve tiene que poder decirse en voz alta delante de un cliente.
Nada de jerga interna, nada de nombres de tablas, nada de siglas sin explicar.

Y nada de frases que no dicen nada. Hubo una versión del Resumen que decía
«nada que mirar», «60 sin nada que hacer», «5 sitios que mirar». Fer lo cortó:
*«Eso se oye horrible. ¿Qué significa eso?»* Tenía razón: eran etiquetas
internas asomándose a la pantalla.

**Y las pantallas no explican nuestras decisiones.** Hubo párrafos que decían
«así ningún sitio de cliente tiene que dejar una puerta abierta a internet,
que es la promesa de fondo de todo este proyecto». Es cierto y está bien
razonado, y no le toca a quien usa el panel. A esa persona le toca saber que
un arreglo tarda entre minutos y horas. El porqué va en `documentacion/` y en
los comentarios del código; en pantalla van los hechos del producto.

**Una explicación no es una advertencia.** Si la pantalla provoca una pregunta
—«¿y esto qué hace?»— se contesta en una caja azul (`.swpk-comoasi`), no en
una amarilla. Una advertencia que no advierte de nada enseña a ignorar las que
sí.

### El tamaño de los archivos

Una clase, una responsabilidad. Si un archivo pasa de unas 400 líneas, casi
siempre está haciendo dos cosas. `class-admin.php` es la excepción —menú,
pantallas, acciones y estilos— y es deuda conocida, no un modelo a seguir.

---

## Cómo agregar cada cosa

### Una columna o una tabla

1. Edita el `CREATE TABLE` en `class-tablas.php`.
2. **Sube `SWPK_Tablas::VERSION` en uno.** Si no, tu columna no existe en
   ninguna instalación que ya esté corriendo.
3. `dbDelta` agrega lo que falte, pero **no borra ni renombra**.

### Una norma de seguridad

1. Mete la norma en `SWPK_Normas::catalogo()`.
2. Escribe `comprobar_<clave>( array $r, ?object $sitio ): array`.
3. Si el dato viene del sitio, agrégalo en `SWA_Normas::medir()`.
4. Si se puede arreglar, agrega el verbo en `SWPK_Ordenes::tipos()`, el
   mapeo en `orden_de_la_norma()` y el ejecutor `hacer_<verbo>()` en
   `SWA_Ordenes`.
5. **Sube `VERSION_CATALOGO`.**

**La regla de oro:** si el dato no está, devuelve `SIN_DATO`, no `FALLA`.

### Una ruta de la API

1. Agrégala en `SWPK_Api::rutas()`, con `puede_leer` o `puede_obrar`.
2. El manejador **no trae lógica propia**: llama a la misma función que usa la
   pantalla.
3. Usa `self::responder()`, que pone la frescura.
4. Si tiene sentido para un modelo, agrégala también a
   `SWPK_Mcp::herramientas()`.
5. **No escribas en `swpk_accesos` a mano.** Lo hace `anotar_llamada()`.

### Una pantalla

1. Un archivo en `includes/admin/vistas/`.
2. Registra la página y el `pantalla_*()` en `class-admin.php`, con su permiso.
3. **La vista no decide nada.** La prueba: ¿podría la API contestar lo mismo?
4. Recorta con `SWPK_Perfiles::sitios_visibles()`, no con
   `SWPK_Inventario::todos()`.
5. Pon el sello de frescura.

### Una acción (un botón que hace algo)

El patrón, idéntico en las veinte que ya hay:

```php
add_action( 'admin_post_swpk_loquesea', [ __CLASS__, 'accion_loquesea' ] );

public static function accion_loquesea(): void {
    if ( ! self::puede_administrar() ) { wp_die( 'Sin permiso.' ); }
    check_admin_referer( 'swpk_loquesea' );
    // …
    wp_safe_redirect( admin_url( '…&swpk_msg=ok:' . rawurlencode( 'Lo que pasó.' ) ) );
    exit;
}
```

Permiso, nonce, trabajo, redirección con mensaje. **Siempre los cuatro.** Y el
mensaje dice qué pasó de verdad, no «Listo»: si tres de catorce sitios
quedaron fuera, el mensaje lo dice.

### Un proveedor de hosting

Ver [12 · Pantheon](12-pantheon.md).

---

## Cómo se prueba

No hay pruebas automáticas todavía. Se prueba a mano, y con un criterio:
**probar también el caso en que falla**, no solo el feliz.

Los dos fallos más caros de este proyecto se encontraron así:

- El reporte liviano que habría borrado los usuarios se encontró preguntando
  *«¿y qué pasa si el reporte no trae usuarios?»*.
- El reseteo que aplicaba la contraseña equivocada se encontró pidiendo dos
  reseteos seguidos de usuarios distintos.

Ninguno de los dos aparece si solo se prueba el camino bueno.

### Lo mínimo antes de subir

```bash
# Sintaxis de todo
for f in $(find panel-kinsta agente-sitio -name "*.php"); do php -l "$f" > /dev/null || echo "FALLA: $f"; done

# Que las pantallas no tiren avisos de PHP
studio wp eval '
error_reporting(E_ALL); ini_set("display_errors","1");
wp_set_current_user(1);
require_once ABSPATH . "wp-admin/includes/screen.php";
require_once ABSPATH . "wp-admin/includes/template.php";
$_GET = ["page" => "panel-kinsta-seguridad"];
ob_start(); require SWPK_RUTA . "includes/admin/vistas/seguridad.php"; $h = ob_get_clean();
printf("%d bytes, %d avisos\n", strlen($h), preg_match_all("/Warning:|Notice:|Deprecated:|Fatal/i", $h, $m));
'
```

Y la corrida completa contra los datos de prueba, para confirmar que no se
rompió nada ni se volvió lenta:

```bash
studio wp eval 'print_r( SWPK_Sincronizador::correr() );'
```

### Probar algo que afecte a varios sitios

Con la fuente de prueba hay 108 sitios inventados. Para probar el agente hace
falta emparejarlo a mano con uno, porque su dirección local no coincide con
ningún dominio del inventario de prueba:

```bash
studio wp eval '
global $wpdb;
$s = $wpdb->get_row( "SELECT sitio_id FROM {$wpdb->prefix}swpk_sitios WHERE tag = \"desarrollo\" LIMIT 1" );
$wpdb->update( "{$wpdb->prefix}swpk_agentes", [ "sitio_id" => $s->sitio_id ], [ "sitio_url" => "http://localhost:8883" ] );
'
```

---

## Qué NO subir al repositorio

El repositorio es **público**. El `.gitignore` cubre lo que no puede salir, y
conviene mirarlo antes de cada commit:

- `docs/`, `FASE-1.md`, `PLAN.md`, `PRUEBAS.md` — material interno: la
  transcripción de la reunión, nombres de clientes, alcance comercial,
  contraseñas de los sitios locales.
- Audio y video de las reuniones: `*.ogg *.mp3 *.wav *.m4a *.mp4 *.webm`…
- `*.pdf`, `*.zip` — lo que se le manda al jefe.
- `*.key`, `*.pem`, `.env`, `credenciales*`, `wp-config.php`.

**`documentacion/` sí va al repositorio.** Es esta carpeta, y está escrita para
que se pueda leer desde fuera: no tiene nombres de clientes ni credenciales.

Antes de cada commit:

```bash
git status --short --untracked-files=all | awk '{print $2}' | grep -iE "\.pdf|\.zip|\.ogg|docs/|credencial|\.key|\.env"
```

Si eso devuelve algo, para.

---

## Los mensajes de commit

En español, y cuentan **qué cambió para quien usa el panel**, no qué archivos
se tocaron. El título es una frase, no un sustantivo.

```
Que el panel distinga solo un cambio real de una mala respuesta
Que las horas sean horas, y no seis horas más tarde
Medir las normas de seguridad, y poder arreglarlas sin abrir una puerta
```

En el cuerpo, lo que importa: por qué se hizo, qué se descartó, y **qué salió
de probarlo**. Si encontraste un fallo mientras lo hacías, cuéntalo — es la
parte que más vale dentro de seis meses.
