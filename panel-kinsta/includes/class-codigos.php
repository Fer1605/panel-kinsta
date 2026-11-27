<?php
/**
 * Qué significa cada código de error, a quién le toca arreglarlo y qué se
 * hace primero.
 *
 * ─── De dónde sale esto ──────────────────────────────────────────────────────
 *
 * El jefe nos enseñó el correo que Kinsta le manda cuando un sitio se cae y
 * dijo, con esas palabras, «esto es lo que yo quiero»:
 *
 *   «se cayó, ¿por qué se cayó? "Sitio caído, se acabó el tiempo de espera."
 *    ¿De quién? ¿Cómo veo eso? Es que voy al punto: eso es lo que yo quiero
 *    saber acá. O sea, qué pasó, porque para eso son las caídas»      (19:16)
 *
 *   «se cayó, ¿por cuánto tiempo?, y qué, ¿por qué se cayó? ¿Qué hubo de
 *    análisis? No hay análisis»                                       (19:45)
 *
 *   «mira, Kinsta viene y él ya tiene… ya Kinsta me avisa. Eso debería de
 *    tenerlo yo aquí»                                                 (20:11)
 *
 * El correo de Kinsta tiene cuatro bloques: qué pasó, qué significa en
 * castellano, cómo se arregla, y qué va a pasar ahora. Nosotros teníamos uno
 * y medio.
 *
 * ─── Lo que esto NO es ───────────────────────────────────────────────────────
 *
 * No es un diagnóstico. El panel no sabe por qué se cayó un sitio concreto, y
 * fingir que sí sería la misma mentira que decir «estuvo caído 1h 1min»
 * cuando lo único medido son dos relojes.
 *
 * Es una traducción: el código HTTP ya lo tenemos en la mano al sondear, y lo
 * que hacíamos era tirarlo y escribir una frase genérica. Diez entradas, cada
 * una con qué significa, a quién le toca y qué se mira primero. Nada más.
 */

defined( 'ABSPATH' ) || exit;

class SWPK_Codigos {

	/** A quién le toca. Decide el tono del correo y si hay que llamar al cliente. */
	public const HOSTING  = 'hosting';
	public const NOSOTROS = 'nosotros';
	public const CLIENTE  = 'cliente';
	public const NO_SE_SABE = 'no_se_sabe';

	public static function de_quien(): array {
		return [
			self::HOSTING    => 'Lo arregla Kinsta',
			self::NOSOTROS   => 'Lo arreglamos nosotros',
			self::CLIENTE    => 'Hay que hablarlo con el cliente',
			self::NO_SE_SABE => 'Todavía no se sabe de quién es',
		];
	}

	/**
	 * La tabla. Las claves son el código HTTP, o una palabra para los casos
	 * que no tienen código porque la conexión no llegó a hacerse.
	 *
	 * @return array<string,array{titulo:string,significa:string,quien:string,primero:string}>
	 */
	public static function tabla(): array {
		return [
			'504' => [
				'titulo'    => 'Tiempo de espera agotado',
				'significa' => 'El servidor web esperó demasiado la respuesta de otro servicio y se rindió. '
					. 'Casi siempre es PHP tardando de más: una consulta pesada, un plugin trabado o un pico de visitas.',
				'quien'     => self::HOSTING,
				'primero'   => 'Mirar los registros de PHP de esa hora en MyKinsta. Si se repite, pedirle a Kinsta que suba el tiempo de espera.',
			],
			'502' => [
				'titulo'    => 'El servidor contestó algo que no se entiende',
				'significa' => 'El servidor de delante recibió una respuesta rota del que corre PHP. '
					. 'Suele ser PHP que se murió a mitad: falta de memoria, o un error fatal.',
				'quien'     => self::HOSTING,
				'primero'   => 'Registros → error.log de esa hora. Si nombra un plugin, desactivarlo y probar. Si no, pedirle a Kinsta más memoria para PHP.',
			],
			'503' => [
				'titulo'    => 'El servicio no está disponible',
				'significa' => 'El servidor está vivo pero dice que ahora no puede atender. '
					. 'Es lo que contesta un sitio en mantenimiento, o uno que el hosting frenó por consumo.',
				'quien'     => self::HOSTING,
				'primero'   => 'Mirar si quedó un archivo .maintenance de una actualización a medias. Si no, abrir ticket con Kinsta.',
			],
			'500' => [
				'titulo'    => 'Error interno del sitio',
				'significa' => 'PHP se rompió. Es el error de un plugin, un tema o el propio WordPress, no del hosting.',
				'quien'     => self::NOSOTROS,
				'primero'   => 'Registros → error.log. Casi siempre nombra el archivo y la línea. Si fue justo después de una actualización, volver atrás desde el respaldo.',
			],
			'403' => [
				'titulo'    => 'El servidor nos cerró la puerta',
				'significa' => 'Algo entre internet y el sitio decidió no dejarnos pasar: casi siempre Cloudflare o '
					. 'el cortafuegos del hosting bloqueando la IP desde la que mira el panel. '
					. 'El sitio puede estar perfectamente bien para todos los demás.',
				'quien'     => self::NOSOTROS,
				'primero'   => 'Abrir el sitio en el navegador. Si carga, no es una caída: es que no nos dejan medirlo. Hay que pedir que dejen pasar la IP del panel.',
			],
			'429' => [
				'titulo'    => 'Demasiadas peticiones',
				'significa' => 'El sitio está limitando la cantidad de peticiones. O hay un ataque, o hay algo '
					. 'nuestro preguntando de más.',
				'quien'     => self::NOSOTROS,
				'primero'   => 'Mirar en Analíticas de Kinsta de dónde viene el tráfico. Si viene del panel, bajar la frecuencia del sondeo.',
			],
			'404' => [
				'titulo'    => 'La dirección no existe',
				'significa' => 'El servidor contesta, pero en esa dirección no hay nada. '
					. 'Normalmente el dominio ya no apunta a este sitio, o el sitio se movió.',
				'quien'     => self::NOSOTROS,
				'primero'   => 'Comprobar en MyKinsta que el dominio siga asignado a este sitio, y que el DNS apunte a Kinsta.',
			],
			'dns' => [
				'titulo'    => 'El dominio no resuelve',
				'significa' => 'No se pudo averiguar a qué servidor va este dominio. O se venció, o le cambiaron '
					. 'el DNS, o el proveedor del dominio está caído.',
				'quien'     => self::CLIENTE,
				'primero'   => 'Mirar la fecha de vencimiento del dominio. Si está vigente, revisar los servidores de nombres con quien lo administra.',
			],
			'certificado' => [
				'titulo'    => 'El certificado no sirve',
				'significa' => 'El candado del navegador está vencido o no cubre este dominio. '
					. 'Quien entre ve una pantalla de advertencia antes del sitio.',
				'quien'     => self::HOSTING,
				'primero'   => 'MyKinsta → Dominios → regenerar el certificado. Si no deja, abrir ticket.',
			],
			'conexion' => [
				'titulo'    => 'No se pudo conectar',
				'significa' => 'Ni siquiera se llegó a hablar con el servidor: rechazó la conexión o no contestó. '
					. 'Es lo que se ve cuando el servidor está apagado o la red está cortada.',
				'quien'     => self::HOSTING,
				'primero'   => 'MyKinsta → ver si el sitio está suspendido o en mantenimiento. Si no, abrir ticket: el servidor no responde.',
			],
			'bloqueo' => [
				'titulo'    => 'Página de bloqueo del hosting',
				'significa' => 'El servidor contesta, pero lo que devuelve es la página del cortafuegos, no el sitio. '
					. 'Es un bloqueo con nombre: alguien decidió no dejar pasar a quien pregunta.',
				'quien'     => self::NOSOTROS,
				'primero'   => 'Abrir el sitio desde otra red. Si carga, hay que pedir que dejen pasar la IP del panel.',
			],
		];
	}

	/**
	 * Traduce lo que devolvió el sondeo.
	 *
	 * Se le pasa el código y el motivo porque hay casos que no tienen código
	 * —el DNS que no resuelve devuelve 0— y la única pista está en el texto
	 * del error de curl. Si no se reconoce nada, se dice que no se sabe en vez
	 * de inventar una explicación: es la regla 3 del proyecto.
	 *
	 * @return array{clave:string,titulo:string,significa:string,quien:string,primero:string,conocido:bool}
	 */
	public static function traducir( int $codigo, string $motivo = '' ): array {
		$tabla = self::tabla();
		$pista = mb_strtolower( $motivo );

		$clave = '';

		if ( isset( $tabla[ (string) $codigo ] ) ) {
			$clave = (string) $codigo;
		} elseif ( str_contains( $pista, 'certificado' ) || str_contains( $pista, 'ssl' ) || str_contains( $pista, 'tls' ) ) {
			$clave = 'certificado';
		} elseif ( str_contains( $pista, 'dns' ) || str_contains( $pista, 'resolve' ) || str_contains( $pista, 'resolver' ) ) {
			$clave = 'dns';
		} elseif ( str_contains( $pista, 'cortafuego' ) || str_contains( $pista, 'bloque' ) ) {
			$clave = 'bloqueo';
		} elseif ( 0 === $codigo ) {
			$clave = 'conexion';
		}

		if ( '' === $clave ) {
			return [
				'clave'     => (string) $codigo,
				'titulo'    => $codigo > 0 ? 'Respondió ' . $codigo : 'No respondió',
				'significa' => 'El panel no tiene una traducción para esta respuesta. Lo que midió está arriba, tal cual.',
				'quien'     => self::NO_SE_SABE,
				'primero'   => 'Abrir el sitio en el navegador y mirar los registros de esa hora en MyKinsta.',
				'conocido'  => false,
			];
		}

		return $tabla[ $clave ] + [ 'clave' => $clave, 'conocido' => true ];
	}

	/** El título corto, para una fila de tabla: «504 · Tiempo de espera agotado». */
	public static function titulo_corto( int $codigo, string $motivo = '' ): string {
		$t = self::traducir( $codigo, $motivo );

		return $codigo > 0 && $t['conocido']
			? $codigo . ' · ' . $t['titulo']
			: $t['titulo'];
	}

	/**
	 * El enlace a los registros de ese sitio en MyKinsta, que es el botón
	 * «View logs» del correo de Kinsta.
	 *
	 * Hace falta el id del entorno además del del sitio. Sin él no se dibuja:
	 * un enlace que lleva a un 404 es peor que ninguno.
	 */
	public static function url_registros( object $sitio ): string {
		if ( empty( $sitio->entorno_id ) ) {
			return '';
		}

		return sprintf(
			'https://my.kinsta.com/sites/info/%s/%s/logs',
			rawurlencode( (string) $sitio->sitio_id ),
			rawurlencode( (string) $sitio->entorno_id )
		);
	}
}
