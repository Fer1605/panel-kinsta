<?php
/**
 * Firma de los envíos al panel.
 *
 * Cada reporte va firmado con HMAC-SHA256 sobre el cuerpo más la marca de
 * tiempo, con un secreto propio de este sitio que el panel entregó al
 * registrarlo. Dos cosas que esto evita:
 *
 *  - Que alguien invente un reporte diciendo ser este sitio.
 *  - Que alguien guarde un reporte viejo y lo vuelva a mandar más tarde para
 *    tapar un cambio. Por eso la marca de tiempo entra en la firma y el panel
 *    rechaza lo que venga con más de cinco minutos de diferencia.
 *
 * La comparación se hace con hash_equals, que tarda lo mismo acierte o falle.
 * Comparar con == filtra información por el tiempo que tarda en responder.
 */

defined( 'ABSPATH' ) || exit;

class SWA_Firma {

	public const VENTANA = 300; // cinco minutos

	public static function firmar( string $cuerpo, int $marca, string $secreto ): string {
		return hash_hmac( 'sha256', $marca . "\n" . $cuerpo, $secreto );
	}

	public static function verificar( string $cuerpo, int $marca, string $firma, string $secreto ): bool {
		if ( abs( time() - $marca ) > self::VENTANA ) {
			return false;
		}
		return hash_equals( self::firmar( $cuerpo, $marca, $secreto ), $firma );
	}
}
