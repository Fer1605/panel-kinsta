/**
 * Las burbujitas de ayuda.
 *
 * ─── Por qué hace falta JavaScript para esto ────────────────────────────────
 *
 * El globo se dibujaba con CSS, colgado del ⓘ. El problema es que el ⓘ vive
 * dentro de una tarjeta que recorta lo que se sale (para poder desplazar las
 * tablas anchas en pantalla chica), así que el globo salía cortado: «si estoy
 * viendo la primera burbujita, está cortada y solo leo "falta que nadie se
 * equivoque para que entren"».
 *
 * Un elemento `position: fixed` no lo recorta nadie, pero entonces hay que
 * decirle dónde ponerse. Eso es lo único que hace este archivo: al pasar el
 * mouse o al llegar con el teclado, calcula el hueco y coloca el globo — arriba
 * si cabe, abajo si no, y sin salirse por los lados.
 */
( function () {
	'use strict';

	var MARGEN = 10;

	function colocar( globo, icono ) {
		var i = icono.getBoundingClientRect();

		// Se mide con el globo ya visible pero sin posición, o mediría cero.
		globo.style.visibility = 'hidden';
		globo.style.display = 'block';
		globo.style.left = '0px';
		globo.style.top = '0px';

		var g = globo.getBoundingClientRect();

		// Arriba del ⓘ si cabe; si no, abajo.
		var arriba = i.top - g.height - 8;
		var top = arriba >= MARGEN ? arriba : i.bottom + 8;

		// Centrado, sin salirse por ningún lado.
		var left = i.left + i.width / 2 - g.width / 2;
		var tope = document.documentElement.clientWidth - g.width - MARGEN;

		if ( left < MARGEN ) {
			left = MARGEN;
		}
		if ( left > tope ) {
			left = Math.max( MARGEN, tope );
		}

		globo.style.left = Math.round( left ) + 'px';
		globo.style.top = Math.round( top ) + 'px';
		globo.style.visibility = '';
	}

	function esconder( globo ) {
		globo.style.display = '';
		globo.style.left = '';
		globo.style.top = '';
	}

	function enganchar( icono ) {
		var globo = icono.querySelector( '.swpk-ayuda-globo' );

		if ( ! globo ) {
			return;
		}

		[ 'mouseenter', 'focus' ].forEach( function ( evento ) {
			icono.addEventListener( evento, function () {
				colocar( globo, icono );
			} );
		} );

		[ 'mouseleave', 'blur' ].forEach( function ( evento ) {
			icono.addEventListener( evento, function () {
				esconder( globo );
			} );
		} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		Array.prototype.forEach.call(
			document.querySelectorAll( '.swpk-ayuda' ),
			enganchar
		);
	} );
}() );
