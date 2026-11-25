/**
 * El editor de plantillas de correo, en una ventana encima de la pantalla.
 *
 * «Levantalo en un modal. No me mandes a pantallitas de pantallitas.» Antes
 * cada "Ver y editar el texto" era un viaje a otra pestaña del panel.
 *
 * Esto hace tres cosas, y nada más:
 *
 *   1. Abre la ventana del editor que pide el botón.
 *   2. Dibuja la vista previa del correo que se esté escribiendo, en otra
 *      ventana encima, sin mandar nada.
 *   3. Copia una marca al pulsarla.
 *
 * Sustituye a previa.js, que sabía hacer solo la segunda y solo cuando el
 * editor era una pantalla entera con ids fijos.
 */
( function () {
	'use strict';

	function abrir( dlg ) {
		if ( ! dlg ) {
			return;
		}
		if ( typeof dlg.showModal === 'function' ) {
			dlg.showModal();
		} else {
			dlg.setAttribute( 'open', 'open' );
		}
	}

	// ── 1. Abrir el editor ──────────────────────────────────────────────────
	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest ? e.target.closest( '[data-swpk-editar]' ) : null;

		if ( ! btn ) {
			return;
		}

		e.preventDefault();
		abrir( document.getElementById( 'swpk-editor-' + btn.dataset.swpkEditar ) );
	} );

	// ── 2. La vista previa ──────────────────────────────────────────────────
	//
	// Va en un <iframe> y no en un <div> a propósito: un correo trae sus
	// propios estilos, y metidos en la página del panel ni se ven como se
	// verán en el buzón ni dejan la página en paz.
	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest ? e.target.closest( '[data-swpk-previa]' ) : null;

		if ( ! btn ) {
			return;
		}

		e.preventDefault();

		var caja   = btn.closest( '.swpk-editor' );
		var asunto = caja ? caja.querySelector( '[data-papel="asunto"]' ) : null;
		var cuerpo = caja ? caja.querySelector( '[data-papel="cuerpo"]' ) : null;
		var previa = document.getElementById( 'swpk-previa' );

		if ( ! previa ) {
			return;
		}

		var nombre = document.getElementById( 'swpk-previa-nombre' );
		var titulo = caja ? caja.querySelector( '.swpk-dialogo-cab strong' ) : null;

		if ( nombre && titulo ) {
			nombre.textContent = titulo.textContent;
		}

		var form = document.createElement( 'form' );
		form.method = 'post';
		form.action = btn.dataset.url;
		form.target = 'swpk-previa-marco';

		[
			[ 'action', 'swpk_vista_previa' ],
			[ 'plantilla', btn.dataset.plantilla ],
			[ '_wpnonce', btn.dataset.nonce ],
			[ 'asunto', asunto ? asunto.value : '' ],
			[ 'cuerpo', cuerpo ? cuerpo.value : '' ]
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

		abrir( previa );
	} );

	// ── 3. Copiar una marca ─────────────────────────────────────────────────
	//
	// Si el navegador no deja copiar —pasa sin https, y el panel vive en
	// http— se mete en el texto donde estaba el cursor, que es para lo que
	// uno la iba a copiar de todas formas.
	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest ? e.target.closest( '.swpk-marca-copiar' ) : null;

		if ( ! btn ) {
			return;
		}

		e.preventDefault();

		var marca  = btn.dataset.marca || '';
		var caja   = btn.closest( '.swpk-editor' );
		var cuerpo = caja ? caja.querySelector( '[data-papel="cuerpo"]' ) : null;

		if ( cuerpo && ! cuerpo.disabled ) {
			var i = cuerpo.selectionStart;
			var f = cuerpo.selectionEnd;

			if ( typeof i === 'number' ) {
				cuerpo.value = cuerpo.value.slice( 0, i ) + marca + cuerpo.value.slice( f );
				cuerpo.focus();
				cuerpo.selectionStart = cuerpo.selectionEnd = i + marca.length;
			}
		}

		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			navigator.clipboard.writeText( marca ).catch( function () {} );
		}
	} );

	// ── Pulsar fuera cierra, que es lo que espera cualquiera ────────────────
	Array.prototype.forEach.call( document.querySelectorAll( 'dialog.swpk-dialogo' ), function ( dlg ) {
		dlg.addEventListener( 'click', function ( e ) {
			if ( e.target === dlg ) {
				dlg.close();
			}
		} );
	} );

	// ── Volver de guardar con la ventana abierta donde estaba ───────────────
	//
	// Guardar manda el formulario y la pantalla se recarga. Sin esto, el
	// editor se cierra solo y el mensaje de "guardado" sale detrás: parece
	// que te echó. El redirect trae ?plantilla=<clave> y eso la vuelve a
	// abrir, en la misma pestaña desde la que se abrió.
	( function () {
		var cual = new URLSearchParams( window.location.search ).get( 'plantilla' );

		if ( cual ) {
			abrir( document.getElementById( 'swpk-editor-' + cual.replace( /[^a-z0-9_-]/gi, '' ) ) );
		}
	}() );
}() );
