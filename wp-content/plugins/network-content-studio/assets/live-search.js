/**
 * Canli arama (includes/live-search.php). data-nwcs-live nitelikli arama
 * kutusuna yazarken formun altinda sonuc listesi acar.
 *
 * - 200 ms bekler, eski istegi iptal eder; gec gelen cevapta "Aranıyor…".
 * - Eslesen kisim kalin. Katlama sunucudakiyle ayni (nwcs_search_fold):
 *   Turkce harf, buyuk/kucuk harf, bosluk ve tire farki yok. Liste DOM ile
 *   kurulur (textContent); sunucudan gelen metin HTML olarak islenmez.
 * - ARIA combobox + listbox: ↑ ↓ etkin urun (aria-activedescendant), Enter
 *   etkin urunu acar, yoksa form sonuc sayfasina gider; Esc listeyi kapatir;
 *   disari tiklayinca kapanir; odak kutuda kalir; sonuc sayisi duyurulur.
 * - Genis ekranda (>= 1024 px) yanda etkin (ya da uzerine gelinen) urunun
 *   onizlemesi: gorsel, ad, kod, fiyat, kisa metin, adet ve ekle dugmesi.
 *   Ekleme temanin betiginde: 'nwcs-live-add' olayi, sonuc detail.result
 *   ({ status: 'added' | 'full' | 'error', qty, notified }).
 * Ayarlar ve metinler window.nwcsLiveSearch (tema, nwcs_live_search_enqueue).
 * Betik yoksa ya da veri ucu cevap vermezse form eskisi gibi calisir.
 */
( function () {
	'use strict';

	var cfg = window.nwcsLiveSearch;
	var doc = document;
	var LETTER;

	if ( ! cfg || ! cfg.endpoint || ! window.fetch || ! window.CustomEvent || ! Array.from ) {
		return;
	}

	try {
		LETTER = new RegExp( '[\\p{L}\\p{N}]', 'u' );
	} catch ( error ) {
		return; // Unicode harf sinifi yok (cok eski tarayici): duz form.
	}

	var P     = cfg.prefix || 'nwcs-ls';
	var T     = cfg.texts || {};
	var C     = cfg.classes || {};
	var MIN   = cfg.min || 2;
	var MAX   = cfg.max || 60;
	var wide  = window.matchMedia( '(min-width: ' + ( cfg.wide || 1024 ) + 'px)' );
	var cache = {};
	var uid   = 0;

	/* ---------------------------------------------------------------- */
	/* Katlama ve vurgu (includes/search.php ile ayni kurallar)          */
	/* ---------------------------------------------------------------- */

	var MAP = { 'ı': 'i', 'ğ': 'g', 'ü': 'u', 'ş': 's', 'ö': 'o', 'ç': 'c', 'â': 'a', 'î': 'i', 'û': 'u' };

	function fold( text ) {
		return String( text ).replace( /İ/g, 'i' ).replace( /I/g, 'ı' ).toLowerCase().replace( /[ığüşöçâîû]/g, function ( c ) {
			return MAP[ c ];
		} );
	}

	function compact( text ) {
		return Array.from( fold( text ) ).filter( function ( c ) {
			return LETTER.test( c );
		} ).join( '' );
	}

	/** Terimin kelimeleri (2 harften kisasi vurgulanmaz). */
	function words( term ) {
		return fold( term ).split( /\s+/ ).map( compact ).filter( function ( word ) {
			return word.length >= 2;
		} );
	}

	/**
	 * Metinde terimin gectigi yerler: [bas, son) asil metindeki konumlar.
	 * Terim butunuyle geciyorsa o, gecmiyorsa kelimeleri ayri ayri.
	 */
	function hits( text, term ) {
		var hay = '';
		var at  = [];
		var pos = 0;
		var out = [];

		Array.from( text ).forEach( function ( ch ) {
			Array.from( fold( ch ) ).forEach( function ( f ) {
				if ( LETTER.test( f ) ) {
					hay += f;
					for ( var k = 0; k < f.length; k++ ) {
						at.push( [ pos, pos + ch.length ] );
					}
				}
			} );
			pos += ch.length;
		} );

		var whole = compact( term );

		( whole && hay.indexOf( whole ) >= 0 ? [ whole ] : words( term ) ).forEach( function ( needle ) {
			var from = 0;
			var i;

			while ( ( i = hay.indexOf( needle, from ) ) >= 0 ) {
				out.push( [ at[ i ][ 0 ], at[ i + needle.length - 1 ][ 1 ] ] );
				from = i + needle.length;
			}
		} );

		out.sort( function ( a, b ) {
			return a[ 0 ] - b[ 0 ];
		} );

		return out.reduce( function ( list, range ) {
			var last = list[ list.length - 1 ];

			if ( last && range[ 0 ] <= last[ 1 ] ) {
				last[ 1 ] = Math.max( last[ 1 ], range[ 1 ] );
			} else {
				list.push( range.slice() );
			}

			return list;
		}, [] );
	}

	/* ---------------------------------------------------------------- */
	/* Yardimcilar                                                       */
	/* ---------------------------------------------------------------- */

	function el( tag, cls, text ) {
		var node = doc.createElement( tag );

		if ( cls ) {
			node.className = cls;
		}

		if ( null !== text && undefined !== text ) {
			node.textContent = String( text );
		}

		return node;
	}

	function fill( template, key, value ) {
		return String( template || '' ).split( '{' + key + '}' ).join( String( value ) );
	}

	function str( value ) {
		return 'string' === typeof value ? value : ( 'number' === typeof value ? String( value ) : '' );
	}

	/* Yalnizca http(s) ya da site ici adres. */
	function safeUrl( value ) {
		value = str( value );

		return /^(https?:)?\/\//i.test( value ) || '/' === value.charAt( 0 ) ? value : '';
	}

	/** Metni yazar; eslesen kisimlar <strong>. */
	function marked( node, text, term ) {
		var last = 0;

		hits( text, term ).forEach( function ( range ) {
			if ( range[ 0 ] > last ) {
				node.appendChild( doc.createTextNode( text.slice( last, range[ 0 ] ) ) );
			}

			node.appendChild( el( 'strong', P + '__hit', text.slice( range[ 0 ], range[ 1 ] ) ) );
			last = range[ 1 ];
		} );

		if ( last < text.length ) {
			node.appendChild( doc.createTextNode( text.slice( last ) ) );
		}

		return node;
	}

	function normalize( data ) {
		data = data && 'object' === typeof data ? data : {};

		return {
			total: Math.max( 0, parseInt( data.total, 10 ) || 0 ),
			url: safeUrl( data.url ),
			items: ( Array.isArray( data.items ) ? data.items : [] ).filter( function ( item ) {
				return item && 'object' === typeof item && parseInt( item.id, 10 ) > 0;
			} ).map( function ( item ) {
				var image = item.image && 'object' === typeof item.image && safeUrl( item.image.url ) ? item.image : null;

				return {
					id: parseInt( item.id, 10 ),
					url: safeUrl( item.url ),
					title: str( item.title ),
					code: str( item.code ),
					price: str( item.price ),
					note: str( item.price_note ),
					hasPrice: !! item.has_price,
					canAdd: !! item.can_add,
					thumb: safeUrl( item.thumb ),
					image: image ? { url: safeUrl( image.url ), width: parseInt( image.width, 10 ) || 0, height: parseInt( image.height, 10 ) || 0 } : null,
					text: str( item.text )
				};
			} )
		};
	}

	/* ---------------------------------------------------------------- */
	/* Her arama kutusu                                                  */
	/* ---------------------------------------------------------------- */

	doc.querySelectorAll( 'input[data-nwcs-live]' ).forEach( function ( input ) {
		if ( input.form ) {
			setup( input, input.form );
		}
	} );

	function setup( input, form ) {
		var id      = P + '-' + ( ++uid );
		var root    = el( 'div', P );
		var panel   = el( 'div', P + '__panel' );
		var col     = el( 'div', P + '__col' );
		var state   = el( 'div', P + '__state' );
		var list    = el( 'div', P + '__list' );
		var all     = el( 'a', P + '__all' );
		var preview = el( 'div', P + '__preview' );
		var live    = el( 'p', ( cfg.sr ? cfg.sr + ' ' : '' ) + P + '__live' );
		var items   = [];
		var active  = -1;
		var shown   = null;
		var term    = '';
		var seq     = 0;
		var timer;
		var wait;
		var sayTimer;
		var ctrl;

		root.id     = id;
		root.hidden = true;
		list.id     = id + '-list';
		list.setAttribute( 'role', 'listbox' );
		list.setAttribute( 'aria-label', str( T.list ) );
		live.setAttribute( 'role', 'status' );
		preview.hidden = true;

		col.appendChild( state );
		col.appendChild( list );
		col.appendChild( all );
		panel.appendChild( col );
		panel.appendChild( preview );
		root.appendChild( panel );
		form.appendChild( root );
		form.appendChild( live );

		input.setAttribute( 'role', 'combobox' );
		input.setAttribute( 'aria-autocomplete', 'list' );
		input.setAttribute( 'aria-expanded', 'false' );
		input.setAttribute( 'aria-controls', list.id );
		input.setAttribute( 'autocomplete', 'off' );

		function open() {
			if ( root.hidden ) {
				root.hidden = false;
				input.setAttribute( 'aria-expanded', 'true' );
				form.classList.add( 'is-live-open' );
			}

			fit();
		}

		function close() {
			seq++;
			window.clearTimeout( timer );
			window.clearTimeout( wait );
			window.clearTimeout( sayTimer );

			if ( ctrl ) {
				ctrl.abort();
				ctrl = null;
			}

			root.classList.remove( 'is-loading' );

			if ( ! root.hidden ) {
				root.hidden = true;
				input.setAttribute( 'aria-expanded', 'false' );
				form.classList.remove( 'is-live-open' );
				live.textContent = '';
			}

			setActive( -1 );
		}

		/*
		 * Liste pencerenin altindan tasmasin. Altta sabit bir serit varsa tema
		 * kokun margin-bottom'uyla yer ayirir (mutlak konumda yerlesimi etkilemez).
		 */
		function fit() {
			var gap = parseFloat( window.getComputedStyle( root ).marginBottom ) || 0;

			panel.style.maxHeight = Math.max( 240, window.innerHeight - root.getBoundingClientRect().top - 16 - gap ) + 'px';
		}

		function say( text ) {
			window.clearTimeout( sayTimer );
			sayTimer = window.setTimeout( function () {
				live.textContent = text;
			}, 450 );
		}

		/* -------------------------- Istek ----------------------------- */

		function run( q ) {
			var mine = ++seq;

			window.clearTimeout( wait );

			if ( ctrl ) {
				ctrl.abort();
				ctrl = null;
			}

			if ( cache[ q ] ) {
				render( cache[ q ] );
				return;
			}

			ctrl = window.AbortController ? new window.AbortController() : null;
			wait = window.setTimeout( loading, 150 );

			window.fetch( cfg.endpoint + ( cfg.endpoint.indexOf( '?' ) < 0 ? '?' : '&' ) + 'q=' + encodeURIComponent( q ), {
				credentials: 'omit',
				headers: { Accept: 'application/json' },
				signal: ctrl ? ctrl.signal : undefined
			} ).then( function ( response ) {
				if ( ! response.ok ) {
					throw new Error( String( response.status ) );
				}

				return response.json();
			} ).then( function ( data ) {
				if ( mine !== seq ) {
					return;
				}

				cache[ q ] = normalize( data );
				render( cache[ q ] );
			} ).catch( function () {
				// Iptal edilen istek sessiz; veri ucu yoksa liste acilmaz, form eskisi gibi.
				if ( mine === seq ) {
					close();
				}
			} );
		}

		function loading() {
			root.classList.add( 'is-loading' );

			if ( root.hidden || ! items.length ) {
				state.textContent = str( T.loading );
				state.hidden      = false;
				list.hidden       = true;
				all.hidden        = true;
				preview.hidden    = true;
				open();
			}
		}

		/* -------------------------- Cizim ----------------------------- */

		function render( data ) {
			window.clearTimeout( wait );
			root.classList.remove( 'is-loading' );

			items       = data.items;
			active      = -1;
			shown       = null;
			list.textContent = '';
			input.removeAttribute( 'aria-activedescendant' );

			if ( data.total && items.length ) {
				state.hidden      = true;
				state.textContent = '';
				list.hidden       = false;
				items.forEach( function ( item, index ) {
					list.appendChild( option( item, index ) );
				} );
				all.hidden      = ! data.url;
				all.href        = data.url || '#';
				all.textContent = fill( T.all, 'sayi', data.total );
				say( fill( T.count, 'sayi', data.total ) );
			} else {
				empty();
				say( fill( T.emptyTitle, 'terim', term ) );
			}

			open();
			show( items[ 0 ] || null );
		}

		function empty() {
			var links = ( Array.isArray( cfg.links ) ? cfg.links : [] ).slice();

			state.textContent = '';
			state.hidden      = false;
			list.hidden       = true;
			all.hidden        = true;
			state.appendChild( el( 'strong', P + '__empty', fill( T.emptyTitle, 'terim', term ) ) );

			if ( T.emptyText ) {
				state.appendChild( el( 'span', P + '__emptytext', T.emptyText ) );
			}

			if ( cfg.wa && safeUrl( cfg.wa.base ) && cfg.wa.label ) {
				links.push( { label: cfg.wa.label, url: cfg.wa.base + ( cfg.wa.msg ? '?text=' + encodeURIComponent( fill( cfg.wa.msg, 'terim', term ) ) : '' ), blank: true } );
			}

			if ( links.length ) {
				var row = el( 'span', P + '__links' );

				links.forEach( function ( link ) {
					var a = el( 'a', ( C.link ? C.link + ' ' : '' ) + P + '__link', link.label );

					a.href = safeUrl( link.url ) || '#';

					if ( link.blank ) {
						a.target = '_blank';
						a.rel    = 'noopener';
					}

					row.appendChild( a );
				} );

				state.appendChild( row );
			}
		}

		function picture( item, cls, big ) {
			var box = el( 'span', cls );
			var src = big ? ( item.image ? item.image.url : '' ) : item.thumb;

			if ( src ) {
				var img = el( 'img' );

				img.alt      = '';
				img.loading  = 'lazy';
				img.decoding = 'async';
				img.width    = big ? ( item.image.width || 768 ) : 56;
				img.height   = big ? ( item.image.height || 576 ) : 56;
				img.src      = src;
				box.appendChild( img );
			} else {
				box.appendChild( el( 'span', P + '__ph' + ( C[ big ? 'phLarge' : 'ph' ] ? ' ' + C[ big ? 'phLarge' : 'ph' ] : '' ), cfg.plate ? item.code : ( big ? T.noImage : '' ) ) );
			}

			return box;
		}

		function codeLine( code, cls, highlight ) {
			var line  = el( 'span', cls );
			var parts = String( T.code || '{kod}' ).split( '{kod}' );
			var value = el( 'span', P + '__codev' );

			line.appendChild( doc.createTextNode( parts[ 0 ] ) );

			if ( highlight ) {
				marked( value, code, term );
			} else {
				value.textContent = code;
			}

			line.appendChild( value );

			if ( parts.length > 1 ) {
				line.appendChild( doc.createTextNode( parts.slice( 1 ).join( code ) ) );
			}

			return line;
		}

		function price( item, cls ) {
			var box = el( 'span', cls + ( item.hasPrice ? '' : ' is-quote' ) );

			box.appendChild( el( 'span', P + '__amount', item.price ) );

			if ( item.note ) {
				box.appendChild( doc.createTextNode( ' ' ) );
				box.appendChild( el( 'small', P + '__note', item.note ) );
			}

			return box;
		}

		function option( item, index ) {
			var a    = el( 'a', P + '__opt' );
			var info = el( 'span', P + '__info' );

			a.id = id + '-o' + index;
			a.setAttribute( 'role', 'option' );
			a.setAttribute( 'aria-selected', 'false' );
			a.tabIndex = -1;

			if ( item.url ) {
				a.href = item.url;
			}

			info.appendChild( marked( el( 'span', P + '__title' ), item.title, term ) );

			if ( item.code ) {
				info.appendChild( codeLine( item.code, P + '__code', true ) );
			}

			a.appendChild( picture( item, P + '__thumb', false ) );
			a.appendChild( info );
			a.appendChild( price( item, P + '__price' ) );

			// mousemove (mouseenter degil): liste farenin altinda yenilenince etkin oge kaymasin.
			a.addEventListener( 'mousemove', function () {
				if ( active !== index ) {
					setActive( index, true );
				}
			} );

			return a;
		}

		function setActive( index, mouse ) {
			var options = list.children;

			if ( options[ active ] ) {
				options[ active ].classList.remove( 'is-active' );
				options[ active ].setAttribute( 'aria-selected', 'false' );
			}

			active = options[ index ] ? index : -1;

			if ( options[ active ] ) {
				options[ active ].classList.add( 'is-active' );
				options[ active ].setAttribute( 'aria-selected', 'true' );
				input.setAttribute( 'aria-activedescendant', options[ active ].id );

				if ( ! mouse && options[ active ].scrollIntoView ) {
					options[ active ].scrollIntoView( { block: 'nearest' } );
				}
			} else {
				input.removeAttribute( 'aria-activedescendant' );
			}

			if ( ! root.hidden ) {
				show( items[ active ] || items[ 0 ] || null );
			}
		}

		/* ------------------------ Onizleme ---------------------------- */

		function show( item ) {
			if ( ! item || ! wide.matches ) {
				preview.hidden = true;
				shown          = null;
				return;
			}

			preview.hidden = false;

			if ( shown === item ) {
				return;
			}

			shown = item;
			preview.textContent = '';

			var body  = el( 'div', P + '__pbody' );
			var title = el( item.url ? 'a' : 'span', P + '__ptitle', item.title );

			if ( item.url ) {
				title.href = item.url;
			}

			preview.appendChild( picture( item, P + '__media', true ) );
			body.appendChild( title );

			if ( item.code ) {
				body.appendChild( codeLine( item.code, P + '__pcode', false ) );
			}

			body.appendChild( price( item, P + '__pprice' ) );

			if ( item.text ) {
				body.appendChild( el( 'p', P + '__ptext', item.text ) );
			}

			if ( item.canAdd ) {
				adder( item, body );
			} else if ( item.url && T.open ) {
				var go = el( 'a', ( C.open ? C.open + ' ' : '' ) + P + '__go', T.open );

				go.href = item.url;
				body.appendChild( go );
			}

			preview.appendChild( body );
		}

		function adder( item, body ) {
			var row    = el( 'div', P + '__add' );
			var qty    = el( 'div', C.qty || '' );
			var less   = el( 'button', C.qtyBtn || '' );
			var more   = el( 'button', C.qtyBtn || '' );
			var box    = el( 'input', C.qtyInput || '' );
			var button = el( 'button', ( C.add ? C.add + ' ' : '' ) + P + '__addbtn', T.add );
			var note   = el( 'p', P + '__status' );
			var top    = Math.max( 1, parseInt( cfg.maxQty, 10 ) || 99 );
			var flash;

			var step = function ( node, sign, label ) {
				node.type = 'button';
				node.appendChild( el( 'span', '', sign ) ).setAttribute( 'aria-hidden', 'true' );
				node.appendChild( el( 'span', cfg.sr || '', label ) );
			};

			var value = function () {
				return Math.max( 1, Math.min( top, parseInt( box.value, 10 ) || 1 ) );
			};

			var sync = function () {
				less.disabled = value() <= 1;
			};

			var message = function ( text, ok ) {
				note.textContent = '';
				note.className   = P + '__status';

				// Bir an bos: ayni metin ikinci kez de duyurulsun.
				window.setTimeout( function () {
					note.appendChild( doc.createTextNode( text ) );

					if ( ok && cfg.cart && T.go ) {
						var go = el( 'a', P + '__cart', T.go );

						go.href = safeUrl( cfg.cart ) || '#';
						note.appendChild( doc.createTextNode( ' ' ) );
						note.appendChild( go );
					}

					note.classList.add( ok ? 'is-ok' : 'is-err' );
				}, 60 );
			};

			var add = function () {
				var detail = { id: item.id, qty: value(), title: item.title, code: item.code, url: item.url, result: null };

				doc.dispatchEvent( new window.CustomEvent( 'nwcs-live-add', { detail: detail } ) );

				var result = detail.result;

				// Temanin betigi yoksa urun sayfasi (orada ekleme formu var).
				if ( ! result ) {
					if ( item.url ) {
						window.location.href = item.url;
					}
					return;
				}

				if ( 'added' === result.status ) {
					box.value = '1';
					sync();
					button.classList.add( 'is-added' );
					window.clearTimeout( flash );
					flash = window.setTimeout( function () {
						button.classList.remove( 'is-added' );
					}, 1200 );

					if ( ! result.notified ) {
						message( fill( T.added, 'adet', result.qty || detail.qty ), true );
					}
				} else {
					message( 'full' === result.status ? str( T.full ) : str( T.error ), false );
				}
			};

			step( less, '−', str( T.less ) );
			step( more, '+', str( T.more ) );
			box.type      = 'number';
			box.min       = '1';
			box.max       = String( top );
			box.step      = '1';
			box.value     = '1';
			box.inputMode = 'numeric';
			box.setAttribute( 'aria-label', str( T.qty ) );
			button.type = 'button';
			note.setAttribute( 'role', 'status' );

			less.addEventListener( 'click', function () {
				box.value = String( Math.max( 1, value() - 1 ) );
				sync();
			} );

			more.addEventListener( 'click', function () {
				box.value = String( Math.min( top, value() + 1 ) );
				sync();
			} );

			box.addEventListener( 'input', sync );

			// Adet kutusunda Enter arama formunu gondermesin: ekler.
			box.addEventListener( 'keydown', function ( event ) {
				if ( 'Enter' === event.key ) {
					event.preventDefault();
					add();
				}
			} );

			button.addEventListener( 'click', add );
			sync();

			qty.appendChild( less );
			qty.appendChild( box );
			qty.appendChild( more );
			row.appendChild( qty );
			row.appendChild( button );
			body.appendChild( row );
			body.appendChild( note );
		}

		/* ------------------------- Olaylar ---------------------------- */

		input.addEventListener( 'input', function () {
			window.clearTimeout( timer );
			term = input.value.trim().slice( 0, MAX );

			if ( compact( term ).length < MIN ) {
				items = [];
				close();
				return;
			}

			if ( cache[ term ] ) {
				run( term );
				return;
			}

			timer = window.setTimeout( function () {
				run( term );
			}, 200 );
		} );

		// Kutuya geri donulunce ayni terimin sonucu yeniden gorunur (yeni istek yok).
		input.addEventListener( 'focus', function () {
			var q = input.value.trim().slice( 0, MAX );

			if ( root.hidden && cache[ q ] ) {
				term = q;
				run( q );
			}
		} );

		input.addEventListener( 'keydown', function ( event ) {
			var count = list.hidden ? 0 : list.children.length;

			if ( event.isComposing || event.altKey || event.ctrlKey || event.metaKey ) {
				return;
			}

			if ( 'ArrowDown' === event.key || 'ArrowUp' === event.key ) {
				if ( root.hidden ) {
					if ( cache[ term ] && compact( term ).length >= MIN ) {
						event.preventDefault();
						run( term );
					}
					return;
				}

				if ( ! count ) {
					return;
				}

				event.preventDefault();

				var next = active + ( 'ArrowDown' === event.key ? 1 : -1 );

				// Sonuncudan sonra ve ilkten once kutunun kendisi (etkin urun yok).
				if ( next >= count ) {
					next = -1;
				} else if ( next < -1 ) {
					next = count - 1;
				}

				setActive( next );
			} else if ( 'Enter' === event.key ) {
				var current = root.hidden ? null : list.children[ active ];

				if ( current && current.href ) {
					event.preventDefault();
					window.location.href = current.href;
				}
			} else if ( 'Escape' === event.key && ! root.hidden ) {
				// Yalnizca liste kapanir: kutu, yazilan ve ust menudeki arama cubugu kalir.
				event.preventDefault();
				event.stopPropagation();
				close();
			}
		} );

		// Liste ve onizlemeye basinca odak kutuda kalsin (adet kutusu haric).
		root.addEventListener( 'mousedown', function ( event ) {
			if ( ! event.target.closest( 'input' ) ) {
				event.preventDefault();
			}
		} );

		doc.addEventListener( 'pointerdown', function ( event ) {
			if ( ! root.hidden && ! form.contains( event.target ) ) {
				close();
			}
		} );

		// Sekmeyle formdan cikilinca kapanir.
		form.addEventListener( 'focusout', function ( event ) {
			if ( ! root.hidden && event.relatedTarget && ! form.contains( event.relatedTarget ) ) {
				close();
			}
		} );

		window.addEventListener( 'resize', function () {
			if ( ! root.hidden ) {
				fit();
			}
		} );

		var onWide = function () {
			if ( ! root.hidden ) {
				shown = null;
				show( items[ active ] || items[ 0 ] || null );
			}
		};

		if ( wide.addEventListener ) {
			wide.addEventListener( 'change', onWide );
		} else if ( wide.addListener ) {
			wide.addListener( onWide );
		}
	}
}() );
