#!/usr/bin/env node
/**
 * Conector MCP del Panel Kinsta.
 *
 * ─── Qué es esto y por qué hace falta ───────────────────────────────────────
 *
 * El panel ya contesta por API: diez rutas, autenticación básica, probadas. Pero
 * Claude Desktop no consume una API así — no hay dónde pegarle una dirección con
 * usuario y contraseña. Habla MCP, que es otro protocolo. Esto es el traductor:
 * de un lado habla MCP con Claude, del otro habla HTTP con el panel.
 *
 * ─── Por qué sin dependencias ───────────────────────────────────────────────
 *
 * Porque esto se lo tiene que poder instalar alguien que no programa, y "npm
 * install" en la máquina de otra persona es donde mueren estas cosas. Un archivo
 * y un `node`, nada más. Node trae `fetch` desde la 18.
 *
 * ─── Por qué solo lee ───────────────────────────────────────────────────────
 *
 * La API tiene tres rutas que disparan procesos y las tres exigen permiso de
 * Dirección; una llave de consulta recibe 403. Aquí no se exponen a propósito:
 * una conversación no debería poder lanzar 200 peticiones al hosting porque a
 * alguien le pareció buena idea en medio de una pregunta. Eso tiene su botón en
 * el panel, donde se ve quién lo apretó.
 *
 * Uso:
 *   PANEL_URL=https://panel.ejemplo.com PANEL_LLAVE='llave-x:abcd EFGH' node index.mjs
 *   node index.mjs --probar      (comprueba la conexión y sale, sin MCP)
 */

const VERSION = '1.0.0';
const PROTOCOLO = '2024-11-05';
const ESPERA_MS = 120000;
const TOPE_TEXTO = 120000;

// ─────────────────────────────────────────────────────────────────────────────
// Configuración
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Acepta las dos formas de escribir la dirección, porque las dos se van a
 * escribir: la del sitio a secas y la que el panel enseña en pantalla, que ya
 * trae /wp-json/swpk/v1. Pedir exactamente una sería pedir un error de dedo.
 */
function base() {
	const crudo = String( process.env.PANEL_URL || '' ).trim().replace( /\/+$/, '' );
	if ( ! crudo ) {
		return null;
	}

	return crudo.includes( '/wp-json/' ) ? crudo : `${ crudo }/wp-json/swpk/v1`;
}

function autorizacion() {
	const llave = String( process.env.PANEL_LLAVE || '' ).trim();
	if ( ! llave.includes( ':' ) ) {
		return null;
	}

	return 'Basic ' + Buffer.from( llave, 'utf8' ).toString( 'base64' );
}

function queFalta() {
	if ( ! base() ) {
		return 'Falta PANEL_URL: la dirección del panel, por ejemplo https://panel.ejemplo.com';
	}
	if ( ! autorizacion() ) {
		return 'Falta PANEL_LLAVE, y va en formato usuario:contraseña. Se saca del panel, en "Conectarse (API)" → Crear la llave.';
	}

	return null;
}

// ─────────────────────────────────────────────────────────────────────────────
// Las herramientas: una por ruta de lectura, con los mismos nombres de
// parámetro que la API. Si aquí se llamaran distinto, habría dos vocabularios
// que mantener de acuerdo.
// ─────────────────────────────────────────────────────────────────────────────

const GRUPOS = [ 'desarrollo', 'auto_update', 'no_update', 'sin_etiqueta' ];
const NIVELES = [ 'critico', 'alto', 'medio', 'bajo' ];
const TIPOS = [
	'plugin_actualizado',
	'actualizacion_pendiente',
	'vulnerabilidad_detectada',
	'vulnerabilidad_resuelta',
	'plugin_instalado',
	'plugin_desinstalado',
	'administrador_nuevo',
	'administrador_eliminado',
	'usuario_subio_de_rol',
	'correo_de_dominio_extrano',
	'sitio_caido',
	'sitio_repuesto',
	'agente_registrado',
];

const FECHA = { type: 'string', description: 'Fecha en formato AAAA-MM-DD.' };

const HERRAMIENTAS = [
	{
		name: 'panel_estado',
		description:
			'El veredicto del panel hoy: cuántos sitios necesitan atención, cuáles no pueden esperar, ' +
			'cuáles están caídos y de cuáles no se sabe nada. Empieza por aquí para cualquier pregunta ' +
			'del tipo "¿cómo estamos?" o "¿hay algo urgente?". Trae además de cuándo es el dato.',
		inputSchema: { type: 'object', properties: {}, additionalProperties: false },
		ruta: () => '/estado',
		consulta: [],
	},
	{
		name: 'panel_sitios',
		description:
			'La cartera de sitios con su nivel de riesgo, su grupo y de cuándo es el dato de cada uno. ' +
			'Úsala para listar, contar o filtrar sitios. Con 108 sitios conviene filtrar: sin filtros ' +
			'devuelve una respuesta larga.',
		inputSchema: {
			type: 'object',
			properties: {
				grupo: { type: 'string', enum: GRUPOS, description: 'desarrollo y auto_update son sitios que administramos nosotros; no_update son externos.' },
				nivel: { type: 'string', enum: NIVELES },
				q: { type: 'string', description: 'Busca en el nombre del sitio.' },
				pagina: { type: 'integer', minimum: 1 },
				por_pagina: { type: 'integer', minimum: 1, maximum: 200 },
			},
			additionalProperties: false,
		},
		ruta: () => '/sitios',
		consulta: [ 'grupo', 'nivel', 'q', 'pagina', 'por_pagina' ],
	},
	{
		name: 'panel_sitio',
		description:
			'La ficha completa de un sitio: sus hallazgos de seguridad, quién tiene acceso, qué le pasó ' +
			'últimamente y qué se le avisó al cliente. El id sale de panel_sitios, en el campo sitio_id.',
		inputSchema: {
			type: 'object',
			properties: { id: { type: 'string', description: 'El sitio_id que devuelve panel_sitios.' } },
			required: [ 'id' ],
			additionalProperties: false,
		},
		requeridos: [ 'id' ],
		ruta: ( args ) => '/sitios/' + encodeURIComponent( String( args.id ) ),
		consulta: [],
	},
	{
		name: 'panel_vulnerabilidades',
		description:
			'Los hallazgos de seguridad de toda la cartera, uno por fila, con el sitio al que pertenecen, ' +
			'el módulo, qué permite hacer la falla y desde cuándo. Para "¿qué hay que parchar?".',
		inputSchema: {
			type: 'object',
			properties: {
				nivel: { type: 'string', enum: NIVELES },
				sitio: { type: 'string', description: 'Un sitio_id, para ver solo los suyos.' },
				pagina: { type: 'integer', minimum: 1 },
				por_pagina: { type: 'integer', minimum: 1, maximum: 200 },
			},
			additionalProperties: false,
		},
		ruta: () => '/vulnerabilidades',
		consulta: [ 'nivel', 'sitio', 'pagina', 'por_pagina' ],
	},
	{
		name: 'panel_usuarios',
		description:
			'Quién tiene control de cada sitio nuestro, con su rol y su correo. Esto no lo da el hosting: ' +
			'lo reporta el plugin instalado en cada sitio, así que solo existe para los sitios que ' +
			'administramos nosotros.',
		inputSchema: {
			type: 'object',
			properties: { sitio: { type: 'string', description: 'Un sitio_id, para ver solo ese.' } },
			additionalProperties: false,
		},
		ruta: () => '/usuarios',
		consulta: [ 'sitio' ],
	},
	{
		name: 'panel_eventos',
		description:
			'Qué cambió y cuándo: actualizaciones, vulnerabilidades que aparecieron o se resolvieron, ' +
			'caídas, administradores nuevos. Para preguntas con fechas, como "qué pasó esta semana" o ' +
			'"cuántas veces se actualizó este sitio este mes".',
		inputSchema: {
			type: 'object',
			properties: {
				desde: FECHA,
				hasta: FECHA,
				sitio: { type: 'string', description: 'Un sitio_id.' },
				tipo: { type: 'string', enum: TIPOS },
				por_pagina: { type: 'integer', minimum: 1, maximum: 200 },
			},
			additionalProperties: false,
		},
		ruta: () => '/eventos',
		consulta: [ 'desde', 'hasta', 'sitio', 'tipo', 'por_pagina' ],
	},
	{
		name: 'panel_bitacora',
		description:
			'Qué se le notificó a quién y cuándo, con el resultado del envío. Es el registro de lo que se ' +
			'le avisó al cliente, no de lo que pasó en el sitio — para eso está panel_eventos.',
		inputSchema: {
			type: 'object',
			properties: {
				desde: FECHA,
				hasta: FECHA,
				tipo: { type: 'string' },
				sitio: { type: 'string', description: 'Un sitio_id.' },
				cuerpo: { type: 'boolean', description: 'true para traer también el texto que se mandó.' },
				por_pagina: { type: 'integer', minimum: 1, maximum: 200 },
			},
			additionalProperties: false,
		},
		ruta: () => '/bitacora',
		consulta: [ 'desde', 'hasta', 'tipo', 'sitio', 'cuerpo', 'por_pagina' ],
	},
];

// ─────────────────────────────────────────────────────────────────────────────
// Hablar con el panel
// ─────────────────────────────────────────────────────────────────────────────

async function preguntar( herramienta, args ) {
	const url = new URL( base() + herramienta.ruta( args ) );

	for ( const nombre of herramienta.consulta ) {
		const valor = args[ nombre ];
		if ( undefined === valor || null === valor || '' === valor ) {
			continue;
		}
		url.searchParams.set( nombre, String( valor ) );
	}

	let respuesta;

	try {
		respuesta = await fetch( url, {
			headers: { Authorization: autorizacion(), Accept: 'application/json' },
			signal: AbortSignal.timeout( ESPERA_MS ),
		} );
	} catch ( e ) {
		// Lo más probable, y de lejos: el panel vive en una máquina que desde
		// aquí no existe. Decirlo así ahorra media hora de buscar la causa en
		// el lugar equivocado.
		throw new Error(
			`No se pudo llegar al panel en ${ url.origin }. ` +
			`Comprueba que esté encendido y que esta computadora lo alcance — si la dirección es ` +
			`localhost, solo funciona en la misma máquina donde corre el panel. (${ e.message })`
		);
	}

	const crudo = await respuesta.text();

	if ( ! respuesta.ok ) {
		throw new Error( explicarFallo( respuesta.status, crudo ) );
	}

	try {
		return JSON.stringify( JSON.parse( crudo ), null, 2 );
	} catch {
		throw new Error(
			`El panel contestó algo que no es JSON. Suele querer decir que la dirección apunta a ` +
			`otra cosa, no a la API. Esto fue lo que llegó: ${ crudo.slice( 0, 300 ) }`
		);
	}
}

function explicarFallo( estado, crudo ) {
	let detalle = '';

	try {
		const cuerpo = JSON.parse( crudo );
		detalle = String( cuerpo.message || '' );
	} catch {
		detalle = crudo.slice( 0, 300 );
	}

	if ( 401 === estado ) {
		return `La llave no sirve. O está mal copiada —va completa, con los espacios— o la revocaron desde el panel. ${ detalle }`;
	}
	if ( 403 === estado ) {
		return `Esa llave puede consultar, pero no esto. ${ detalle }`;
	}
	if ( 404 === estado ) {
		return `El panel contestó que eso no está. Si preguntabas por un sitio: puede que no exista, o que esta llave no lo tenga asignado. ${ detalle }`;
	}

	return `El panel contestó ${ estado }. ${ detalle }`;
}

// ─────────────────────────────────────────────────────────────────────────────
// MCP sobre stdin/stdout: JSON por líneas. Lo único que sale por stdout son
// respuestas del protocolo; cualquier otra cosa lo rompe, así que los avisos
// van por stderr.
// ─────────────────────────────────────────────────────────────────────────────

function escribir( objeto ) {
	process.stdout.write( JSON.stringify( objeto ) + '\n' );
}

function responder( id, resultado ) {
	escribir( { jsonrpc: '2.0', id, result: resultado } );
}

function fallar( id, codigo, mensaje ) {
	escribir( { jsonrpc: '2.0', id, error: { code: codigo, message: mensaje } } );
}

function contenido( texto, esFallo = false ) {
	const t = texto.length > TOPE_TEXTO
		? texto.slice( 0, TOPE_TEXTO ) + `\n\n[...cortado. Pide menos filas con por_pagina, o filtra.]`
		: texto;

	return { content: [ { type: 'text', text: t } ], isError: esFallo };
}

async function llamar( id, params ) {
	const herramienta = HERRAMIENTAS.find( ( h ) => h.name === params?.name );

	if ( ! herramienta ) {
		return fallar( id, -32602, `Este conector no tiene una herramienta "${ params?.name }".` );
	}

	const falta = queFalta();
	if ( falta ) {
		return responder( id, contenido( falta, true ) );
	}

	const args = params.arguments || {};

	for ( const obligatorio of herramienta.requeridos || [] ) {
		if ( ! args[ obligatorio ] ) {
			return responder( id, contenido( `Hace falta "${ obligatorio }".`, true ) );
		}
	}

	try {
		return responder( id, contenido( await preguntar( herramienta, args ) ) );
	} catch ( e ) {
		return responder( id, contenido( e.message, true ) );
	}
}

async function atender( linea ) {
	let mensaje;

	try {
		mensaje = JSON.parse( linea );
	} catch {
		return;
	}

	const { id, method, params } = mensaje;

	// Sin id es una notificación: no se contesta.
	if ( undefined === id || null === id ) {
		return;
	}

	if ( 'initialize' === method ) {
		return responder( id, {
			protocolVersion: params?.protocolVersion || PROTOCOLO,
			capabilities: { tools: {} },
			serverInfo: { name: 'panel-kinsta', version: VERSION },
		} );
	}

	if ( 'ping' === method ) {
		return responder( id, {} );
	}

	if ( 'tools/list' === method ) {
		return responder( id, {
			tools: HERRAMIENTAS.map( ( { name, description, inputSchema } ) => ( { name, description, inputSchema } ) ),
		} );
	}

	if ( 'tools/call' === method ) {
		return llamar( id, params );
	}

	return fallar( id, -32601, `Este conector no hace "${ method }".` );
}

// ─────────────────────────────────────────────────────────────────────────────
// Arranque
// ─────────────────────────────────────────────────────────────────────────────

async function probar() {
	const falta = queFalta();

	if ( falta ) {
		console.error( 'No está configurado:\n  ' + falta );
		process.exit( 1 );
	}

	console.error( `Preguntándole a ${ base() }/estado …` );

	try {
		const datos = JSON.parse( await preguntar( HERRAMIENTAS[ 0 ], {} ) );

		console.error( '\nContestó. El panel dice:' );
		console.error( '  ' + ( datos.veredicto?.titulo || '(sin veredicto)' ) );
		console.error( `  dato obtenido: ${ datos.frescura?.obtenido_en || '?' }` );
		console.error( `  corrida al día: ${ datos.frescura?.corrida_al_dia ? 'sí' : 'no' }` );
		console.error( `\nHerramientas disponibles: ${ HERRAMIENTAS.length }` );
		console.error( 'La conexión sirve. Ya se le puede dar a Claude Desktop.' );
	} catch ( e ) {
		console.error( '\nNo sirvió:\n  ' + e.message );
		process.exit( 1 );
	}
}

if ( process.argv.includes( '--probar' ) ) {
	probar();
} else {
	let pendiente = '';

	process.stdin.setEncoding( 'utf8' );

	process.stdin.on( 'data', ( trozo ) => {
		pendiente += trozo;

		let corte;
		while ( ( corte = pendiente.indexOf( '\n' ) ) >= 0 ) {
			const linea = pendiente.slice( 0, corte ).trim();
			pendiente = pendiente.slice( corte + 1 );

			if ( linea ) {
				atender( linea );
			}
		}
	} );

	process.stdin.on( 'end', () => process.exit( 0 ) );

	console.error( `Conector del Panel Kinsta ${ VERSION } — escuchando.` );
}
