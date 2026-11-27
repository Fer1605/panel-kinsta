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

	// ── La vista previa de al lado, viva ────────────────────────────────────
	//
	// «"ver y editar texto". Esto no es un ver. Vista previa… esa vista previa
	// debería estar del otro lado» (09:22).
	//
	// La de antes era un botón que armaba el correo DE VERDAD en el servidor y
	// lo abría en otra ventana encima. Eso sigue existiendo —«Verlo armado de
	// verdad»—, porque es lo único que comprueba el correo entero con su
	// plantilla y su pie. Pero para escribir hace falta ver el cambio al
	// teclearlo, y eso no puede ser una petición por pulsación.
	//
	// Así que esta sustituye las marcas con valores de ejemplo aquí mismo. Es
	// una aproximación a propósito: enseña el texto, no el diseño del correo.
	function escapar( t ) {
		var d = document.createElement( 'div' );
		d.textContent = t;
		return d.innerHTML;
	}

	function rellenar( texto, ejemplos ) {
		return String( texto ).replace( /\{\{[a-z_]+\}\}/gi, function ( m ) {
			// Una marca que no existe se deja tal cual, que es justo lo que
			// hace el panel al mandar el correo: así un error de escritura se
			// ve aquí igual que se vería en la bandeja.
			return Object.prototype.hasOwnProperty.call( ejemplos, m ) ? ejemplos[ m ] : m;
		} );
	}

	function pintarPrevia( caja ) {
		var panel = caja.querySelector( '.swpk-editor-previa' );

		if ( ! panel ) {
			return;
		}

		var ejemplos = {};
		try {
			ejemplos = JSON.parse( panel.getAttribute( 'data-ejemplos' ) || '{}' );
		} catch ( e ) {
			ejemplos = {};
		}

		var asunto = caja.querySelector( '[data-papel="asunto"]' );
		var cuerpo = caja.querySelector( '[data-papel="cuerpo"]' );
		var vA     = panel.querySelector( '[data-papel="previa-asunto"]' );
		var vC     = panel.querySelector( '[data-papel="previa-cuerpo"]' );

		if ( vA && asunto ) {
			vA.textContent = rellenar( asunto.value, ejemplos );
		}

		if ( vC && cuerpo ) {
			// Una línea en blanco separa párrafos, que es la misma regla con
			// la que el panel arma el correo.
			vC.innerHTML = rellenar( cuerpo.value, ejemplos )
				.split( /\n\s*\n/ )
				.map( function ( p ) {
					return '<p>' + escapar( p.trim() ).replace( /\n/g, '<br>' ) + '</p>';
				} )
				.join( '' );
		}
	}

	document.addEventListener( 'input', function ( e ) {
		var papel = e.target.getAttribute ? e.target.getAttribute( 'data-papel' ) : null;

		if ( 'asunto' !== papel && 'cuerpo' !== papel ) {
			return;
		}

		var caja = e.target.closest( '.swpk-editor' );
		if ( caja ) {
			pintarPrevia( caja );
		}
	} );

	// Y la primera vez, al abrir: si no, la previa arranca en blanco al lado
	// de un texto que sí está escrito.
	document.querySelectorAll( '.swpk-editor' ).forEach( function ( caja ) {
		pintarPrevia( caja );
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
