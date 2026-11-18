# 14 · Cada cuánto pasa cada cosa, y qué de esto cuesta dinero

Este archivo contesta dos preguntas que hizo el jefe y que conviene tener
escritas, porque la respuesta corta («no se puede») es incompleta y la larga
tiene una condición de dinero.

## La pregunta

> **(Fer)** «tiene una revisión que hace una vez al día, pero como el hosting
> hace las revisiones en los sitios cuando se le da la gana, no tiene como un
> orden.»
>
> **(el jefe)** «Bueno, había que ver si **se puede ordenar** o si no, no se
> puede ordenar, para que nosotros le digamos: oye, **todos los días a
> medianoche**.»

## La respuesta, en tres partes

### 1. Nuestras revisiones: sí, y ya está hecho

Todo lo que corre el panel tiene hora fija:

| Qué | Cuándo |
|---|---|
| Lectura de la plataforma | cada día, 4:30 |
| Revisión de configuración (la sonda) | cada día, 5:30 |
| Inventario de los sitios | cada día, 6:30 |
| Sondeo de caídas | cada 5 minutos |
| Diario a soporte | cada día, 7:30 |
| Semanal a las asesoras | lunes, 7:00 |
| Resumen de la semana | domingos, 18:00 |
| Mensual a los clientes | día 1, 8:00 |

**Con una condición que el panel avisa solo en Ajustes:** el reloj de WordPress
se dispara con la primera visita *después* de cada hora, no a la hora exacta. En
un panel al que entran dos personas, una corrida de las 6:30 puede acabar
pasando a media mañana. Para que las horas se cumplan de verdad, el servidor
tiene que llamar a `wp-cron.php` él mismo y hay que poner `DISABLE_WP_CRON` en
`wp-config.php`. Es un ajuste del hosting, no del panel.

### 2. Las actualizaciones del hosting: sí, pero se paga

La documentación del hosting describe un complemento de actualizaciones
automáticas con dos ajustes que son literalmente lo que el jefe pidió:

| Ajuste | Qué hace, en palabras de su documentación |
|---|---|
| **Testing frequency** | *«Select the days you want the updates to occur on your site»* |
| **Testing time window** | *«This is when you want the updates to occur on your site»* |

Días y franja horaria, a elección. Actualiza *«all plugins and themes, including
inactive ones»*.

**Cuesta 3 USD por entorno al mes**, gratis el primer mes. En los 108 entornos
serían unos 324 USD mensuales. Se contrata por sitio, así que se podría poner
solo en algunos.

Comprobado contra el inventario real: **ningún sitio de la cartera lo tiene
contratado**. Ochenta usan la actualización automática de WordPress, que es
gratis pero no se puede programar; el resto no tiene ninguna.

**Un detalle que importa para elegir la hora:** el hosting tiene su propio
periodo de mantenimiento de 2 a 5 de la madrugada, hora del centro de datos, y
su documentación avisa que durante esa franja *«the updates may fail and attempt
to retry at a later time»*. O sea que «a medianoche» —lo que pidió el jefe— cae
justo al lado de la peor franja posible.

### 3. El escaneo de vulnerabilidades: no, y no hay forma

Esta es la parte que más importaba y es la que no tiene solución.

El hosting escanea los plugins y temas de cada sitio contra su base de
vulnerabilidades **todos los días, a la hora que él decida**. Su documentación
no menciona ningún ajuste de hora, día, franja ni zona horaria, y **tampoco hay
ningún endpoint en toda su API** para leerlo ni para cambiarlo. Se revisó el
catálogo completo: lo único que aparece con la palabra «interval» es la
caducidad de contraseñas SSH.

No se puede ordenar ni pagando.

## La decisión tomada

**No se contrata nada de pago.** Por lo tanto:

- Las actualizaciones del hosting siguen pasando cuando el hosting quiera.
- El escaneo de vulnerabilidades también, y eso no cambiaría aunque se pagara.
- Lo nuestro sí tiene hora fija, y es lo único que podemos prometer.

La forma honesta de decírselo a alguien de fuera: *«fijar la hora es posible
solo para las actualizaciones, y solo pagando un complemento por sitio. El
escaneo de vulnerabilidades no se puede programar de ninguna manera. Lo que sí
tiene hora fija es nuestra propia revisión, que es la que alimenta el panel.»*

## Por qué una revisión al día alcanza

Vale dejarlo escrito, porque es la pregunta que viene después.

Lo que el panel mide son estados, no sucesos: qué versión tiene cada plugin, qué
normas cumple cada sitio, qué hay expuesto. Eso cambia cuando alguien actualiza
o toca algo, no de minuto a minuto. Mirarlo cada hora daría el mismo resultado
veinticuatro veces.

Lo que sí es un suceso —que un sitio se caiga— **no** espera a la revisión
diaria: el sondeo de caídas corre cada cinco minutos, y el agente, en los sitios
donde esté instalado, avisa en segundos cuando alguien se da de alta, cambia de
rol o se borra.

Así que la cadencia no es una sola: es diaria para lo que cambia despacio y de
minutos o segundos para lo que no puede esperar.

## De qué reloj van estas horas

**Del WordPress donde esté instalado el panel**, no del hosting. Todo el panel
usa `current_time()`, `wp_timezone()` y `wp_date()`, así que funciona igual esté
alojado donde esté — y eso importa, porque el panel va a vivir en un WordPress
que no está en el mismo sitio que los sitios que vigila.

La consecuencia hay que tenerla clara: **si la zona horaria de ese WordPress
está mal, todas las horas de este archivo están mal con ella**, y nada lo
delataría. Por eso la pantalla de Ajustes enseña la zona y la hora que el panel
cree que es ahora mismo. Si esa hora no coincide con el reloj de quien la mira,
el problema está ahí y no en las tareas.

Se ajusta en Ajustes → Generales de WordPress, no en el panel.

Hay una trampa relacionada ya resuelta y documentada en `SWPK_Frescura::cuando()`:
`strtotime('tomorrow 06:30')` calcula en UTC, porque WordPress le fija UTC a
PHP. Programar con eso dejaba la corrida de las 6:30 agendada a las 12:30 de la
noche, y los correos con ella. Por eso las horas se calculan con
`DateTimeImmutable` y `wp_timezone()`, y no con `strtotime` a secas.
