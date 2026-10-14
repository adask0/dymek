/**
 * Chat button and window. The button is drawn when the browser is idle and
 * the answers are fetched only when the chat is opened for the first time.
 */
( function () {
	'use strict';

	const config = window.dymekConfig;
	if ( ! config ) {
		return;
	}

	const icon = '<svg viewBox="0 0 24 24" width="28" height="28" aria-hidden="true" focusable="false"><path fill="currentColor" d="M4 3h16a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H9l-5 4v-4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"/></svg>';

	let tree = null;
	let request = null;
	let launcher, panel, closeButton, log, choices;

	function el( tag, className, text ) {
		const node = document.createElement( tag );
		node.className = className;
		if ( text ) {
			node.textContent = text;
		}
		return node;
	}

	function addBubble( from, content, isHtml ) {
		const bubble = el( 'div', 'dymek__bubble dymek__bubble--' + from );
		if ( isHtml ) {
			bubble.innerHTML = content;
		} else {
			bubble.textContent = content;
		}
		log.appendChild( bubble );
		log.scrollTop = log.scrollHeight;
	}

	function addChoice( label, onClick ) {
		const button = el( 'button', 'dymek__choice', label );
		button.type = 'button';
		button.addEventListener( 'click', onClick );
		choices.appendChild( button );
	}

	function focusFirst() {
		( choices.firstElementChild || closeButton ).focus();
	}

	function show( slug, fromUser ) {
		const node = tree.nodes[ slug ];

		if ( fromUser ) {
			addBubble( 'user', node.label, false );
		}
		if ( node.content ) {
			addBubble( 'bot', node.content, true );
		}

		choices.textContent = '';
		if ( 'link' === node.type && node.url ) {
			const link = el( 'a', 'dymek__choice dymek__choice--link', config.i18n.openLink );
			link.href = node.url;
			link.target = '_blank';
			link.rel = 'noopener noreferrer';
			choices.appendChild( link );
		}
		node.children.forEach( function ( child ) {
			addChoice( tree.nodes[ child ].label, function () {
				show( child, true );
			} );
		} );
		if ( slug !== tree.start ) {
			addChoice( config.i18n.restart, function () {
				show( tree.start, false );
			} );
		}

		focusFirst();
	}

	function load() {
		return fetch( config.endpoint )
			.then( function ( response ) {
				if ( ! response.ok ) {
					throw new Error( response.statusText );
				}
				return response.json();
			} )
			.then( function ( data ) {
				tree = data;
				show( tree.start, false );
			} )
			.catch( function () {
				request = null;
				addBubble( 'bot', config.i18n.error, false );
				focusFirst();
			} );
	}

	function openPanel() {
		panel.hidden = false;
		launcher.setAttribute( 'aria-expanded', 'true' );

		if ( ! request ) {
			request = load();
		} else {
			focusFirst();
		}
	}

	function closePanel() {
		panel.hidden = true;
		launcher.setAttribute( 'aria-expanded', 'false' );
		launcher.focus();
	}

	function onKeydown( event ) {
		if ( 'Escape' === event.key ) {
			closePanel();
			return;
		}
		if ( 'Tab' !== event.key ) {
			return;
		}

		const items = panel.querySelectorAll( 'button, a[href]' );
		const first = items[ 0 ];
		const last = items[ items.length - 1 ];

		if ( event.shiftKey && document.activeElement === first ) {
			last.focus();
			event.preventDefault();
		} else if ( ! event.shiftKey && document.activeElement === last ) {
			first.focus();
			event.preventDefault();
		}
	}

	function build() {
		const root = el( 'div', 'dymek dymek--' + config.position );

		panel = el( 'div', 'dymek__panel' );
		panel.id = 'dymek-panel';
		panel.hidden = true;
		panel.setAttribute( 'role', 'dialog' );
		panel.setAttribute( 'aria-modal', 'true' );
		panel.setAttribute( 'aria-labelledby', 'dymek-title' );

		const header = el( 'div', 'dymek__header' );
		const title = el( 'h2', 'dymek__title', config.title );
		title.id = 'dymek-title';
		closeButton = el( 'button', 'dymek__close', '×' );
		closeButton.type = 'button';
		closeButton.setAttribute( 'aria-label', config.i18n.close );
		header.append( title, closeButton );

		log = el( 'div', 'dymek__log' );
		log.setAttribute( 'role', 'log' );
		log.setAttribute( 'aria-live', 'polite' );
		choices = el( 'div', 'dymek__choices' );
		panel.append( header, log, choices );

		launcher = el( 'button', 'dymek__launcher' );
		launcher.type = 'button';
		launcher.innerHTML = icon;
		launcher.setAttribute( 'aria-label', config.i18n.open );
		launcher.setAttribute( 'aria-expanded', 'false' );
		launcher.setAttribute( 'aria-controls', panel.id );

		root.append( panel, launcher );
		document.body.appendChild( root );

		launcher.addEventListener( 'click', function () {
			if ( panel.hidden ) {
				openPanel();
			} else {
				closePanel();
			}
		} );
		closeButton.addEventListener( 'click', closePanel );
		panel.addEventListener( 'keydown', onKeydown );
	}

	if ( 'requestIdleCallback' in window ) {
		window.requestIdleCallback( build );
	} else {
		window.setTimeout( build, 1 );
	}
} )();
