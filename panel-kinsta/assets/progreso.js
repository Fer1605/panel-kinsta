/**
 * La barra de «Correr ahora».
 *
 * La corrida no cabe en una petición web —son dos consultas al hosting por
 * cada uno de los 107 sitios—, así que el panel la parte en tramos. Este guion
 * es el que va pidiendo el siguiente tramo mientras la pantalla esté abierta,
 * y el único motivo por el que existe es que se vea avanzar algo en vez de un
 * navegador congelado que acaba en un 504.
 *
 * No es imprescindible. El trabajo queda anotado en el panel desde que se
 * pulsa el botón, y el reloj de WordPress lo termina solo. Si este guion no
 * corre, o si se cierra la pestaña, la corrida llega igual al final: solo que
 * sin barra.
 */
(function () {
	'use strict';

	var caja = document.querySelector('.swpk-corrida');

	if (!caja || typeof ajaxurl === 'undefined') {
		return;
	}

	var pista = caja.querySelector('.swpk-corrida-pista span');
	var texto = caja.querySelector('.swpk-corrida-texto');
	var nonce = caja.getAttribute('data-nonce');
	var fallos = 0;

	function pedirTramo() {
		var cuerpo = new FormData();
		cuerpo.append('action', 'swpk_avanzar');
		cuerpo.append('_wpnonce', nonce);

		fetch(ajaxurl, {
			method: 'POST',
			body: cuerpo,
			credentials: 'same-origin'
		})
			.then(function (r) {
				return r.json();
			})
			.then(function (r) {
				if (!r || !r.success || !r.data) {
					throw new Error('respuesta inesperada');
				}

				var p = r.data;

				fallos = 0;

				if (pista) {
					pista.style.width = (p.porcentaje || 0) + '%';
				}

				if (texto) {
					texto.textContent = p.texto || '';
				}

				if (p.estado === 'corriendo') {
					// `ocupado` quiere decir que el tramo lo está haciendo
					// otro —el reloj del servidor, normalmente—. No es un
					// error y no hay nada que hacer salvo esperar; pedir otro
					// tramo al instante sería machacar al servidor con
					// preguntas que ya sabemos que va a rechazar.
					window.setTimeout(pedirTramo, p.ocupado ? 2000 : 0);
					return;
				}

				// Terminó: la pantalla se vuelve a cargar para enseñar los
				// datos nuevos, que es lo que la persona vino a ver.
				window.location.reload();
			})
			.catch(function () {
				// Un tramo que no contesta no cancela nada. El trabajo sigue
				// anotado, así que se reintenta un rato; si tampoco, se deja
				// en manos del reloj y se dice, en vez de fingir que avanza.
				fallos += 1;

				if (fallos > 5) {
					if (texto) {
						texto.textContent = 'Se perdió el contacto con el panel. La corrida sigue por su cuenta; esta pantalla se puede recargar en un rato.';
					}
					return;
				}

				window.setTimeout(pedirTramo, 3000);
			});
	}

	pedirTramo();
})();
