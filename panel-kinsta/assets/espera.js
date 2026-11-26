/**
 * Que se vea que algo está pasando mientras se espera.
 *
 * ─── Por qué existe ─────────────────────────────────────────────────────────
 *
 * «Analizar este sitio ahora… no pasa nada, o sea, no hay como cargando,
 *  verificando.» (23/11, 07:59)
 *
 * Tenía razón, y el problema no era que fuera lento: era que no decía nada.
 * El botón es un enlace normal, el servidor se va a preguntarle al hosting por
 * ese sitio, y eso tarda entre tres y diez segundos. Durante esos segundos la
 * pantalla se queda exactamente igual que antes de pulsar. Quien no sabe que
 * tarda, vuelve a pulsar; quien sabe, se queda mirando sin saber si se pulsó.
 *
 * No hace falta nada del otro mundo: el botón dice lo que está haciendo y se
 * apaga para que no se pueda pulsar dos veces. La página se recarga sola
 * cuando el servidor contesta, y ahí llega el mensaje con el resultado.
 *
 * Se marca con `data-espera="…"`, así que cualquier botón lento del panel
 * puede usarlo sin tocar este archivo.
 */
( function () {
	'use strict';

	document.addEventListener( 'click', function ( e ) {
		var enlace = e.target.closest ? e.target.closest( '[data-espera]' ) : null;

		if ( ! enlace || enlace.dataset.swpkEsperando ) {
			return;
		}

		// Clic con ctrl/cmd o rueda del ratón: abre en otra pestaña y ESTA
		// no se va a ninguna parte. Apagar el botón ahí sería dejarlo muerto.
		if ( e.metaKey || e.ctrlKey || e.shiftKey || 1 === e.button ) {
			return;
		}

		enlace.dataset.swpkEsperando = '1';
		enlace.classList.add( 'swpk-esperando' );
		enlace.setAttribute( 'aria-busy', 'true' );

		// El ancho se congela antes de cambiar el texto: si no, el botón se
		// encoge o se estira y los de al lado dan un salto.
		enlace.style.minWidth = enlace.offsetWidth + 'px';
		enlace.textContent = enlace.dataset.espera;

		// Y no se puede volver a pulsar. `pointer-events` no basta: con el
		// tabulador y Enter se llega igual.
		enlace.addEventListener( 'click', function ( otro ) {
			otro.preventDefault();
			otro.stopImmediatePropagation();
		}, true );
	} );
}() );
