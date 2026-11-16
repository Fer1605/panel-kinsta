# 08 · Permisos y llaves

Lo pidió en la reunión:

> «Darles un panel al área de ventas y un panel al área de soporte que tenga
> esa misma información. Que registre cuándo se loguearon, cuándo se
> deslogearon. Que tenga doble seguridad, pida el código por correo, todo
> eso.» — 08:17

## Roles propios, no los de WordPress

El panel define sus propios roles (`SWPK_Perfiles::roles()`) y sus propias
capacidades. **Una asesora no tiene que ser administradora del sitio para ver
su panel.** Montarlo sobre los roles de WordPress habría significado darle a
cada asesora un rol que también sirve para tocar el WordPress donde vive el
panel.

### Los cuatro perfiles

| Perfil | Ve | No ve |
|---|---|---|
| **Dirección** | Todo | — |
| **Ventas** | Solo sus sitios asignados, qué actualizar y por qué, el texto para cotizar, los avisos a esos clientes | Los sitios de otras asesoras, caídas, accesos, ajustes |
| **Soporte** | Todos los sitios, vulnerabilidades, caídas, bitácora | Registro de accesos, ajustes y llaves |
| **API** | Todos los sitios por consulta, vulnerabilidades, usuarios, movimientos | Accesos y ajustes. **Y no hace nada**: no dispara procesos, no escribe |

El cuarto no es para una persona: es para un programa. Su límite es el punto —
una llave que vive dentro de otro sistema se puede filtrar, y lo que se filtre
no debe poder cambiar nada.

### Las capacidades

```php
SWPK_Perfiles::VER           // entrar al panel
SWPK_Perfiles::VER_TODO      // todos los sitios, no solo los asignados
SWPK_Perfiles::VER_TECNICO   // caídas y cosas de servidor
SWPK_Perfiles::VER_BITACORA  // la bitácora de avisos
SWPK_Perfiles::VER_ACCESOS   // quién entró al panel
SWPK_Perfiles::ADMINISTRAR   // ajustes, llaves, disparar procesos, órdenes
```

Se comprueban siempre con `SWPK_Perfiles::puede( $permiso )`.

### El recorte por sitio

`SWPK_Perfiles::sitios_visibles()` devuelve los sitios que el perfil actual
puede ver — todos, o solo los asignados a esa asesora.

**Úsala siempre en vez de `SWPK_Inventario::todos()`** en cualquier pantalla,
ruta o cálculo que una persona vaya a ver. `todos()` es para procesos internos
que de verdad necesitan los 108.

Una llave de ventas ve lo de ventas también por la API y por el MCP, por la
misma función. No hay un segundo sistema de recorte.

### Los roles cambian con las versiones

`crear_roles()` solo corre al activar el plugin. `VERSION_ROLES` y
`revisar_roles()` existen para que un panel ya instalado reciba un permiso
nuevo sin que alguien tenga que desactivar y reactivar — porque nadie va a
hacer eso.

**Si agregas una capacidad, sube `VERSION_ROLES`.**

---

## Las llaves de consulta

`class-llaves.php`. Una llave es, por dentro, una **cuenta de WordPress**.
Tiene que serlo: la autenticación básica necesita un usuario, y todo el
recorte del panel cuelga de que haya una identidad. Montarlo sobre otra cosa
habría significado escribir un segundo sistema de permisos.

Pero una llave no es una persona, y eso trae un problema que Fer encontró
mirando la pantalla de usuarios:

> «si yo entro a editarlos ahí, les puedo cambiar la contraseña. Y entonces
> alguien me puede quitar los accesos (...) cualquiera puede tener mi usuario y
> la contraseña la cambia y ya puede entrar»

Tenía razón. Una llave que aparece en la lista de usuarios es una cuenta que
cualquier administrador del sitio puede secuestrar: le cambia la contraseña, la
asciende a administradora, y entra.

### Los cuatro barrotes

| Barrote | Cómo |
|---|---|
| **No salen en la lista de usuarios** | `users_list_table_query_args` y `views_users` |
| **Nadie las edita, ni un administrador** | `map_meta_cap` niega `edit_user`, `delete_user`, `promote_user`, `remove_user` y los `*_user_meta` |
| **No entran por el formulario** | `wp_authenticate_user` las rechaza |
| **No se puede recuperar su contraseña** | `allow_password_reset` |

El tercero merece una nota: se engancha en `wp_authenticate_user` y **no** en
`authenticate`. Está comprobado en `wp-includes/user.php` que
`wp_authenticate_application_password()` no pasa por `wp_authenticate_user`, así
que esto bloquea la entrada por formulario **sin** bloquear las contraseñas de
aplicación, que es justo lo que la llave necesita para funcionar.

Se verificó con pruebas que incluyen controles: una **persona** sigue siendo
editable, para que no se esté comprobando que el filtro bloquea todo.

### Crear y revocar

`SWPK_Llaves::crear()` genera la cuenta, una contraseña de aplicación y un
token de 32 hexadecimales para el MCP. **En la base se guarda solo la huella
del token** (`sha256`), igual que una contraseña.

La contraseña y la dirección del MCP se muestran **una sola vez**, en un
pop-up con botón de copiar. Si se pierden, se revoca la llave y se hace otra.

`SWPK_Llaves::revocar()` borra las contraseñas de aplicación **y después borra
la cuenta**. Se niega a tocar nada que no sea una llave.

### Lo que queda por decidir

Fer dejó esto en pausa esperando al jefe: si las llaves vuelven a aparecer en
la pantalla de Usuarios de WordPress. Hoy no aparecen. *«Por el momento lo
vamos a manejar así, ya que mi jefe nos diga qué onda.»*

---

## El registro de accesos

`class-accesos.php`. Se anotan cuatro cosas:

- **Entradas** y **salidas**, que es lo que pidió.
- **Intentos fallidos**, que no pidió nadie y es el que de verdad sirve: una
  ráfaga de intentos fallidos es la señal de que alguien está probando
  contraseñas contra el sitio desde donde se consulta la información de
  **todos** los clientes.
- **Llamadas a la API**, con la ruta que se pidió y si se aceptó o se rechazó.

Lo de la API hace falta porque una contraseña de aplicación se salta la
verificación en dos pasos. Ver [07](07-la-api-y-el-mcp.md), incluido el error
del doble registro y por qué nunca hay que escribir en esta tabla a mano.

---

## La verificación en dos pasos del panel

El panel exige segundo paso para entrar, con código por correo. Es el mismo
mecanismo que el agente pone en los sitios de cliente, por las mismas razones,
y con el mismo cuidado: **si el panel no puede mandar correo, nadie entra**.
Hay códigos de respaldo.

`SWPK_Accesos::al_pasar_segundo_paso()` y `al_bloquearse()` dejan el rastro.
