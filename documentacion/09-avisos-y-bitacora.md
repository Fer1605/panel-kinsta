# 09 · Avisos y bitácora

Esto es lo que convierte el panel en lo que se pidió: no un tablero que alguien
mira si se acuerda, sino avisos que salen solos y quedan registrados.

> «Yo cuando suceda algo vengo y digo: oye, no me puedes decir nada, porque
> mira, según el sistema, desde tal fecha hasta esta fecha, aquí está, se
> hicieron 10 notificaciones.» — 02:48

**La bitácora es el producto, no un accesorio.** Si hubiera que tirar una parte
del panel y quedarse con otra, se queda esta.

---

## Los tres correos

`class-correos.php` decide cuándo sale cada uno y a quién.

| Correo | Cada | Para quién | Qué dice |
|---|---|---|---|
| **Semanal** | semana | Las asesoras | Lo de sus sitios, en argumento de venta |
| **Diario** | día | Soporte | El dato crudo, sin adornos |
| **Mensual** | mes | Los clientes | Tranquilidad y constancia |

### Tres audiencias, tres idiomas para los mismos hechos

`class-plantillas.php`:

- **Asesora** — argumento de venta: qué pasa si no se hace, listo para
  reenviar.
- **Cliente** — tranquilidad y constancia, **sin una sola palabra técnica**.
- **Soporte** — el dato crudo.

Los correos se escriben con **estilos en línea** porque los clientes de correo
descartan las hojas de estilo. No es descuido: es la única forma de que se vean
igual en Gmail, en Outlook y en el teléfono.

### El texto no se arma en la plantilla

`class-textos.php` es el que convierte un CVE en una frase que alguien puede
mandar. Esto es más importante de lo que parece. El jefe no pidió un reporte
técnico: pidió *«algo que ellas puedan copiar y pegar en su cotización»*. Si el
panel entrega una tabla de CVE y versiones, la asesora tiene que traducirla, y
si tiene que traducirla no la usa.

Los métodos que importan:

```php
SWPK_Textos::quien_puede( $h )      // "cualquiera desde internet, sin cuenta"
SWPK_Textos::que_hay_en_juego( $h ) // qué le pasa al cliente
SWPK_Textos::explicar( $h )         // la frase completa
SWPK_Textos::para_asesora( ... )    // el mensaje entero, listo para copiar
SWPK_Textos::para_cliente( ... )    // lo mismo, sin tecnicismos
```

Si agregas un tipo de hallazgo, su texto va aquí, no en la plantilla ni en la
vista.

---

## El interruptor: modo de prueba

`SWPK_Correos::modo()` arranca en **prueba**. En prueba, los correos no salen a
los clientes: se capturan.

Esto no es una comodidad de desarrollo, es una red de seguridad. Un panel que
manda correos a 108 clientes la primera vez que alguien pulsa algo por
curiosidad es un panel que se desinstala el mismo día.

Para pasar a real hay que cambiarlo a propósito en la pantalla de Correos. Y
hay un segundo interruptor, `swpk_envios_activos`, para apagarlo todo sin
perder la configuración.

---

## La bitácora

`class-bitacora.php`. Cada envío deja una fila con:

- **Cuándo** se mandó.
- **De qué tipo**: cliente, asesora, soporte, doble autenticación, caída.
- **A quién** — los destinatarios completos.
- **Con qué asunto y con qué cuerpo exacto.**
- **Si salió**, y si no, el error.
- **Quién lo disparó**, si fue una persona.

### Dos decisiones que importan

**Se guarda el cuerpo entero.** Una bitácora que dice «se mandó un aviso» sin
decir qué decía no sirve para defenderse de un reclamo. Ocupa más; vale la pena.

**Un envío fallido también se registra.** `SWPK_Bitacora::enviar()` anota el
fallo con su motivo. Un fallo silencioso en la evidencia es peor que no tener
evidencia: deja creyendo que se avisó cuando no se avisó.

### No guarda el correo, guarda la notificación

Fue literal en la reunión: *«No se guarde el correo, sino necesito que se
guarde qué notifiqué, a quién notifiqué y cuándo lo notifiqué.»* La bitácora no
es una copia del buzón: es el registro de la notificación.

---

## Dos pestañas, dos preguntas

La pantalla de Bitácora abre en **Movimientos**, no en Notificaciones, y es a
propósito:

- **Movimientos** contesta *«¿qué pasó?»* — es con lo que uno entra.
- **Notificaciones** contesta *«¿a quién se le avisó?»* — se consulta cuando
  alguien reclama, no todos los días.

Las dos van con rango de fechas, del día 1 del mes a hoy por defecto. Sin
rango, la pantalla solo sabía contestar «los últimos 400 avisos», que no es la
pregunta que hace nadie.

---

## Una trampa real con los eventos

Se alargó el texto de «administrador nuevo» por encima de los 191 caracteres
de su columna, el evento **dejó de guardarse**, y el panel siguió como si nada.

Por eso `SWPK_Eventos::registrar()` comprueba que la escritura funcionó y deja
el fallo anotado, y los fallos salen en Ajustes. Si agregas un tipo de evento
con texto largo, mira el ancho de la columna.

---

## Exportar

`class-exportar.php` baja a CSV lo que esté en pantalla, con los filtros
puestos. Dos detalles que parecen menores y no lo son:

1. **El BOM.** Excel abre un CSV en UTF-8 sin BOM interpretando los acentos
   como basura. Con BOM, abre bien. Son tres bytes y la diferencia entre un
   archivo que alguien usa y uno que reenvía preguntando qué pasó.
2. **La frescura va dentro del archivo.** Un Excel que alguien abre en marzo
   tiene que decir de cuándo son sus datos.
