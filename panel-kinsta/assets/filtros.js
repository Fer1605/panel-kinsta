/* ──────────────────────────────────────────────────────────────────────────
   Que los filtros se apliquen solos al elegir.

   El filtro de verdad está en el servidor: cada desplegable es un campo de un
   formulario GET que recarga la pantalla con la lista ya filtrada y vuelta a
   paginar. Eso funciona sin esto.

   Lo único que hace este archivo es ahorrar el clic en «Filtrar»: al cambiar
   un desplegable, manda el formulario. Y como entonces el botón sobra, lo
   esconde — pero solo aquí, con JavaScript, para que en un navegador que lo
   tenga apagado el botón siga estando y la barra siga sirviendo.
   ────────────────────────────────────────────────────────────────────────── */

(function () {
	'use strict';

	document.addEventListener('DOMContentLoaded', function () {
		var barras = document.querySelectorAll('form.swpk-filtra');

		for (var i = 0; i < barras.length; i++) {
			(function (barra) {
				var ir = barra.querySelector('.swpk-filtra-ir');

				// El buscador se queda con su botón: escribiendo no se puede
				// recargar a cada tecla, haría una consulta por letra.
				if (ir && ! barra.querySelector('input[type="search"]')) {
					ir.hidden = true;
				}

				var selects = barra.querySelectorAll('select');
				for (var j = 0; j < selects.length; j++) {
					selects[j].addEventListener('change', function () {
						barra.submit();
					});
				}
			})(barras[i]);
		}
	});
})();
