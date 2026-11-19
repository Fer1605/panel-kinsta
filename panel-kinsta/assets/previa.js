/**
 * La vista previa de una plantilla de correo, en una ventana y sin mandar nada.
 *
 * «En vez de ver cómo queda debe ser una previsualización, y que nos abra un
 * pop-up con cómo se ve la previsualización del correo.» El botón de antes
 * llevaba a otra pestaña a generar el correo de verdad.
 *
 * Va en un <iframe> y no en un <div> a propósito: un correo trae sus propios
 * estilos, y metidos en la página del panel ni se ven como se verán en el
 * buzón ni dejan la página en paz. Dentro del marco, el correo está solo.
 */
( function () {
	'use strict';

	var dlg = document.getElementById( 'swpk-previa' );
	var btn = document.getElementById( 'swpk-ver-previa' );

	if ( ! dlg || ! btn ) {
		return;
	}

	btn.addEventListener( 'click', function ( e ) {
		e.preventDefault();

		var form = document.createElement( 'form' );
		form.method = 'post';
		form.action = btn.dataset.url;
		form.target = 'swpk-previa-marco';

		[
			[ 'action', 'swpk_vista_previa' ],
			[ 'plantilla', btn.dataset.plantilla ],
			[ '_wpnonce', btn.dataset.nonce ],
			[ 'asunto', ( document.getElementById( 'swpk-asunto' ) || {} ).value || '' ],
			[ 'cuerpo', ( document.getElementById( 'swpk-cuerpo' ) || {} ).value || '' ]
		].forEach( function ( par ) {
			var campo = document.createElement( 'input' );
			campo.type = 'hidden';
			campo.name = par[ 0 ];
			campo.value = par[ 1 ];
			form.appendChild( campo );
		} );

		document.body.appendChild( form );
		form.submit();
		document.body.removeChild( form );

		if ( typeof dlg.showModal === 'function' ) {
			dlg.showModal();
		} else {
			dlg.setAttribute( 'open', 'open' );
		}
	} );

	dlg.addEventListener( 'click', function ( e ) {
		// Pulsar fuera del recuadro la cierra, que es lo que espera cualquiera.
		if ( e.target === dlg ) {
			dlg.close();
		}
	} );
}() );
