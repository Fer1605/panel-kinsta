<?php
/**
 * Cuántas veces se avisó de lo mismo, y qué cambió desde la vez anterior.
 *
 * ─── El pedido, con sus palabras ────────────────────────────────────────────
 *
 * Un aviso que se repite idéntico cada lunes se vuelve ruido, y la asesora
 * deja de abrirlo. Lo que el jefe pidió es que el correo sepa que ya lo
 * mandó antes:
 *
 *     «Y en el correo debería decir: oye, ya te notificamos primera vez. En la
 *      otra semana, oye, segunda vez que te notificamos y no notamos que haya
 *      pasado nada. Luego, oye, tercera vez que notificamos y no ha pasado
 *      nada. [...] Hasta que note una diferencia.» (19:20)
 *
 * Y cuando le preguntaron cómo se nota esa diferencia, la contestó él mismo, y
 * la respuesta es la parte fina de todo esto:
 *
 *     «—¿Y qué estoy mandando ahorita? Según el análisis, sigue la misma
 *       cantidad de plugins sin actualizar.
 *      —Sí, en los mismos plugins, sí. ¿Por qué sí puede ser diferente? Porque
 *       la semana pasada había la versión 1.3 y ahorita es la versión 1.4.
 *       Entonces hay una diferencia, pero es el mismo plugin al final del
 *       día.» (19:35)
 *
 * O sea: contar cuántos módulos están pendientes NO sirve para saber si algo
 * cambió. Tres pendientes la semana pasada y tres esta semana pueden ser tres
 * situaciones distintas. Lo que identifica el estado es **qué módulo y en qué
 * versión**, y por eso la firma de abajo lleva el slug y las dos versiones.
 *
 * ─── Por qué una tabla y no la bitácora ────────────────────────────────────
 *
 * La bitácora guarda el cuerpo del correo, así que en teoría se podría
 * reconstruir de ahí lo que se avisó la vez pasada. En la práctica sería
 * adivinar un dato a partir de un HTML redactado, y el día que alguien cambie
 * la plantilla —que se puede cambiar desde el panel, a propósito— la cuenta
 * se rompería sin avisar. Un dato que importa se guarda como dato.
 */

defined( 'ABSPATH' ) || exit;

class SWPK_Insistencia {

	public const OPCION = 'swpk_insistencia';

	/**
	 * La firma de lo que le falta a un sitio, ahora mismo.
	 *
	 * Lleva el slug y las dos versiones —la instalada y la disponible— porque
	 * eso es lo que distingue «el mismo problema» de «el mismo plugin, otra
	 * versión». Va ordenada para que el orden en que vengan los hallazgos no
	 * cambie la firma.
	 *
	 * @param array<int,array<string,mixed>> $hallazgos
	 */
	public static function firma( array $hallazgos ): string {
		$partes = [];

		foreach ( $hallazgos as $h ) {
			$partes[] = sprintf(
				'%s@%s>%s',
				(string) ( $h['slug'] ?? '' ),
				(string) ( $h['version'] ?? '' ),
				(string) ( $h['version_disponible'] ?? '' )
			);
		}

		sort( $partes );

		return implode( '|', $partes );
	}

	/** @return array<string,array{veces:int,firma:string,desde:string,ultima:string}> */
	private static function todo(): array {
		$v = get_option( self::OPCION, [] );
		return is_array( $v ) ? $v : [];
	}

	/**
	 * Apunta que se está avisando de este sitio y devuelve cómo va la cuenta.
	 *
	 * Llamarla tiene efecto: sube el contador. Se llama una vez por sitio y por
	 * correo enviado, justo al armar el cuerpo.
	 *
	 * @param array<int,array<string,mixed>> $hallazgos
	 * @return array{veces:int,igual:bool,cambio:string,desde:string}
	 *   veces  — cuántas veces seguidas se ha avisado de este sitio.
	 *   igual  — si lo pendiente es exactamente lo mismo que la vez anterior.
	 *   cambio — en palabras, qué se movió desde la vez anterior.
	 *   desde  — la fecha del primer aviso de esta racha.
	 */
	public static function apuntar( string $sitio_id, array $hallazgos ): array {
		$todo  = self::todo();
		$firma = self::firma( $hallazgos );
		$hoy   = current_time( 'mysql' );
		$antes = $todo[ $sitio_id ] ?? null;

		if ( ! $antes ) {
			$todo[ $sitio_id ] = [ 'veces' => 1, 'firma' => $firma, 'desde' => $hoy, 'ultima' => $hoy ];
			update_option( self::OPCION, $todo, false );

			return [ 'veces' => 1, 'igual' => false, 'cambio' => '', 'desde' => $hoy ];
		}

		$igual  = (string) $antes['firma'] === $firma;
		$veces  = (int) $antes['veces'] + 1;
		$cambio = $igual ? '' : self::describir_el_cambio( (string) $antes['firma'], $firma );

		$todo[ $sitio_id ] = [
			'veces'  => $veces,
			'firma'  => $firma,
			// La racha arranca de nuevo cuando algo se movió: si cambió, ya no
			// es «la cuarta vez que te decimos lo mismo».
			'desde'  => $igual ? (string) $antes['desde'] : $hoy,
			'ultima' => $hoy,
		];

		update_option( self::OPCION, $todo, false );

		return [
			'veces'  => $igual ? $veces : 1,
			'igual'  => $igual,
			'cambio' => $cambio,
			'desde'  => $igual ? (string) $antes['desde'] : $hoy,
		];
	}

	/**
	 * Lo que se movió entre dos firmas, en castellano.
	 *
	 * Distingue las tres cosas que pueden pasar, y la tercera es la que pidió
	 * el jefe: el mismo plugin con otra versión.
	 */
	private static function describir_el_cambio( string $antes, string $ahora ): string {
		$a = self::por_slug( $antes );
		$b = self::por_slug( $ahora );

		$resueltos = array_diff_key( $a, $b );
		$nuevos    = array_diff_key( $b, $a );

		$movidos = [];
		foreach ( array_intersect_key( $a, $b ) as $slug => $v ) {
			if ( $v !== $b[ $slug ] ) {
				$movidos[] = $slug;
			}
		}

		$frases = [];

		if ( $resueltos ) {
			$frases[] = sprintf(
				'%d se resolvió%s (%s)',
				count( $resueltos ),
				count( $resueltos ) > 1 ? 'n' : '',
				implode( ', ', array_keys( $resueltos ) )
			);
		}

		if ( $nuevos ) {
			$frases[] = sprintf(
				'%d nuev%s (%s)',
				count( $nuevos ),
				count( $nuevos ) > 1 ? 'os' : 'o',
				implode( ', ', array_keys( $nuevos ) )
			);
		}

		if ( $movidos ) {
			$frases[] = sprintf(
				'%s sigue pendiente pero cambió de versión',
				implode( ', ', $movidos )
			);
		}

		return implode( '; ', $frases );
	}

	/** @return array<string,string> slug => versiones */
	private static function por_slug( string $firma ): array {
		$salida = [];

		foreach ( array_filter( explode( '|', $firma ) ) as $parte ) {
			[ $slug, $versiones ] = array_pad( explode( '@', $parte, 2 ), 2, '' );
			if ( '' !== $slug ) {
				$salida[ $slug ] = $versiones;
			}
		}

		return $salida;
	}

	/**
	 * La frase que va en el correo.
	 *
	 * La primera vez no dice nada de insistencia —sería raro estrenar un aviso
	 * diciendo «es la primera vez»— y de la segunda en adelante va subiendo el
	 * tono sin perder la educación.
	 *
	 * @param array{veces:int,igual:bool,cambio:string,desde:string} $r
	 */
	public static function frase( array $r ): string {
		if ( '' !== $r['cambio'] ) {
			return sprintf( 'Desde el aviso anterior sí hubo movimiento: %s.', $r['cambio'] );
		}

		if ( $r['veces'] <= 1 ) {
			return '';
		}

		$desde = '' !== $r['desde']
			? SWPK_Frescura::fmt( 'j \d\e F', $r['desde'] )
			: '';

		return sprintf(
			'Es la %s vez seguida que avisamos de este sitio%s, y sigue exactamente igual que la primera.',
			self::ordinal( (int) $r['veces'] ),
			'' !== $desde ? ' desde el ' . $desde : ''
		);
	}

	private static function ordinal( int $n ): string {
		$nombres = [
			2 => 'segunda',
			3 => 'tercera',
			4 => 'cuarta',
			5 => 'quinta',
			6 => 'sexta',
			7 => 'séptima',
			8 => 'octava',
		];

		return $nombres[ $n ] ?? $n . '.ª';
	}

	/** Borra la cuenta de un sitio. Se usa cuando un sitio se da de baja. */
	public static function olvidar( string $sitio_id ): void {
		$todo = self::todo();
		unset( $todo[ $sitio_id ] );
		update_option( self::OPCION, $todo, false );
	}
}
