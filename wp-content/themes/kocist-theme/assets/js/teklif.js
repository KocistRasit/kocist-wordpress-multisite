/**
 * Arama kutusu ve teklif sepeti (inc/search.php, inc/basket.php).
 *
 * - Ust menudeki buyutec: betik yoksa arama sayfasina giden baglanti; betikle
 *   menunun altindaki arama kutusunu acip kapatan dugme olur.
 * - Sepet cerezi "kimlik:adet|..." (inc/basket.php ile ayni bicim ve yol).
 *   Ust menudeki sayi buradan yazilir: sayfa onbellekte kalsa da dogru.
 * - Urun sayfasi: "Teklif sepetine ekle" sayfayi yenilemeden cerezi yazar ve
 *   sonucu canli bolgede duyurur; cerez yazilamadiysa hata yazar. Ust menudeki
 *   canli aramanin onizlemesi de ayni yolla ekler ('nwcs-live-add' olayi).
 * - Sepet sayfasi: adet degisince form kendisi gonderilir (sunucu listeyi,
 *   WhatsApp mesajini yeniden cizer); "Sepeti temizle" onay sorar.
 * JavaScript yoksa her sey duz baglanti ve form olarak calisir.
 */
( function () {
	'use strict';

	var doc       = document;
	var COOKIE    = 'kc_teklif';
	var link      = doc.querySelector( '[data-k-basket-link]' );
	var status    = doc.querySelector( '[data-k-basket-status]' );
	var path      = link ? ( link.getAttribute( 'data-path' ) || '/' ) : '/';
	var maxLines  = status ? parseInt( status.getAttribute( 'data-max-lines' ), 10 ) || 30 : 30;
	var maxQty    = status ? parseInt( status.getAttribute( 'data-max-qty' ), 10 ) || 9999 : 9999;

	function clamp( value, min, max ) {
		return Math.max( min, Math.min( max, value ) );
	}

	/* ---------------------------------------------------------------- */
	/* Arama kutusu                                                      */
	/* ---------------------------------------------------------------- */

	var toggle = doc.querySelector( '[data-k-search-toggle]' );
	var panel  = doc.querySelector( '[data-k-search]' );

	if ( toggle && panel ) {
		var setSearch = function ( open ) {
			panel.hidden = ! open;
			toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
			toggle.classList.toggle( 'is-open', open );

			if ( open ) {
				var input = panel.querySelector( 'input[type="search"]' );

				if ( input ) {
					input.focus();
				}
			}
		};

		// Baglanti artik bir dugme: ekran okuyucu durumunu da okur.
		toggle.setAttribute( 'role', 'button' );
		toggle.setAttribute( 'aria-expanded', 'false' );

		toggle.addEventListener( 'click', function ( event ) {
			event.preventDefault();
			setSearch( panel.hidden );
		} );

		// Dugme gibi: bosluk tusu da acar.
		toggle.addEventListener( 'keydown', function ( event ) {
			if ( ' ' === event.key ) {
				event.preventDefault();
				setSearch( panel.hidden );
			}
		} );

		doc.addEventListener( 'keydown', function ( event ) {
			if ( 'Escape' === event.key && ! panel.hidden ) {
				setSearch( false );
				toggle.focus();
			}
		} );
	}

	/* ---------------------------------------------------------------- */
	/* Sepet cerezi                                                      */
	/* ---------------------------------------------------------------- */

	function readBasket() {
		var match = doc.cookie.match( new RegExp( '(?:^|; )' + COOKIE + '=([^;]*)' ) );
		var items = {};
		var order = [];
		var raw   = '';

		if ( match ) {
			try {
				raw = decodeURIComponent( match[ 1 ].replace( /\+/g, ' ' ) );
			} catch ( error ) {
				raw = '';
			}

			raw.split( '|' ).forEach( function ( pair ) {
				var m = /^(\d{1,10}):(\d{1,4})$/.exec( pair );
				var qty = m ? Math.min( maxQty, parseInt( m[ 2 ], 10 ) ) : 0;

				if ( qty > 0 && ( items[ m[ 1 ] ] || order.length < maxLines ) ) {
					if ( ! items[ m[ 1 ] ] ) {
						order.push( m[ 1 ] );
					}
					items[ m[ 1 ] ] = qty;
				}
			} );
		}

		return { items: items, order: order };
	}

	function writeBasket( basket ) {
		var value = basket.order
			.filter( function ( id ) {
				return basket.items[ id ] > 0;
			} )
			.slice( 0, maxLines )
			.map( function ( id ) {
				return id + ':' + basket.items[ id ];
			} )
			.join( '|' );
		var secure = 'https:' === window.location.protocol ? '; secure' : '';

		doc.cookie = COOKIE + '=' + encodeURIComponent( value ) + '; path=' + path + '; max-age=' + ( value ? 30 * 24 * 3600 : 0 ) + '; samesite=lax' + secure;
	}

	/* Ust menudeki sayi: sepetteki urun cesidi (adet toplami degil). */
	function renderCount() {
		var count = readBasket().order.length;

		doc.querySelectorAll( '[data-k-basket-count]' ).forEach( function ( el ) {
			el.textContent = String( count );
			el.hidden = 0 === count;
		} );

		doc.querySelectorAll( '[data-k-basket-sr]' ).forEach( function ( el ) {
			el.textContent = count ? ( el.getAttribute( 'data-count' ) || '' ).replace( '{sayi}', String( count ) ) : el.getAttribute( 'data-empty' );
		} );
	}

	/* Gonderim tamamlandi: sunucu sepeti bosaltti; cerez burada da silinir. */
	if ( doc.querySelector( '[data-k-basket-done]' ) ) {
		writeBasket( { items: {}, order: [] } );
	}

	renderCount();

	/* ---------------------------------------------------------------- */
	/* Urun sayfasi: "Teklif sepetine ekle"                             */
	/* ---------------------------------------------------------------- */

	/**
	 * Sonucu canli bolgeye yazar. Once bosaltilir: ayni metin ikinci kez de
	 * duyurulsun.
	 */
	function say( text, ok ) {
		if ( ! status ) {
			return;
		}

		status.textContent = '';
		status.classList.remove( 'is-ok', 'is-err' );

		window.setTimeout( function () {
			var message = doc.createElement( 'span' );

			message.className = 'k-badd__msg';
			message.textContent = text;
			status.appendChild( message );

			if ( ok && link ) {
				var go = doc.createElement( 'a' );

				go.className = 'k-badd__go';
				go.href = link.href;
				go.textContent = status.getAttribute( 'data-go' ) || '';
				status.appendChild( doc.createTextNode( ' ' ) );
				status.appendChild( go );
			}

			status.classList.add( ok ? 'is-ok' : 'is-err' );
		}, 60 );
	}

	/**
	 * Sepete ekler ve ust menudeki sayiyi yazar. Sonuc: { status: 'added',
	 * qty } (bu urunun sepetteki adedi), { status: 'full' } ya da cerez
	 * yazilamadiysa { status: 'error' }. Urun sayfasindaki form ve ust
	 * menudeki canli arama ayni yolu kullanir.
	 */
	function addToBasket( id, qty ) {
		var basket = readBasket();

		if ( ! basket.items[ id ] ) {
			if ( basket.order.length >= maxLines ) {
				return { status: 'full' };
			}

			basket.order.push( id );
			basket.items[ id ] = 0;
		}

		basket.items[ id ] = Math.min( maxQty, basket.items[ id ] + qty );
		writeBasket( basket );

		// Tarayici cerezi kabul etmediyse "eklendi" denmez.
		var saved = readBasket().items[ id ];

		if ( ! saved ) {
			return { status: 'error' };
		}

		renderCount();

		return { status: 'added', qty: saved };
	}

	doc.querySelectorAll( '[data-k-basket-add]' ).forEach( function ( form ) {
		form.addEventListener( 'submit', function ( event ) {
			var input  = form.querySelector( '[name="kc_qty"]' );
			var result = addToBasket( form.getAttribute( 'data-id' ), clamp( parseInt( input ? input.value : '1', 10 ) || 1, 1, maxQty ) );

			event.preventDefault();

			if ( 'added' !== result.status ) {
				say( status ? status.getAttribute( 'full' === result.status ? 'data-full' : 'data-error' ) : '', false );
				return;
			}

			if ( input ) {
				input.value = '1';
			}

			say( ( status ? status.getAttribute( 'data-added' ) || '' : '' ).replace( '{adet}', String( result.qty ) ), true );
		} );
	} );

	/*
	 * Ust menudeki canli arama (eklenti, assets/live-search.js): onizlemedeki
	 * "Teklif sepetine ekle" bu olayi gonderir; sonuc olaya yazilir, metni
	 * liste kendisi gosterir. Sepet sayfasi acik degilse (baglanti yok) cevap
	 * verilmez: liste urun sayfasina gider.
	 */
	doc.addEventListener( 'nwcs-live-add', function ( event ) {
		var detail = event.detail || {};
		var id     = parseInt( detail.id, 10 );

		if ( id > 0 && link ) {
			detail.result = addToBasket( String( id ), clamp( parseInt( detail.qty, 10 ) || 1, 1, maxQty ) );
		}
	} );

	/* ---------------------------------------------------------------- */
	/* Adet secicileri (− +)                                            */
	/* ---------------------------------------------------------------- */

	doc.querySelectorAll( '[data-k-qty]' ).forEach( function ( box ) {
		var input  = box.querySelector( 'input' );
		var basket = box.closest( '[data-k-basket-form]' );
		var less   = box.querySelector( '[data-k-step="-1"]' );
		// Sepet satirinda − en fazla 1'e iner (satiri "Çıkar" siler); urun sayfasinda min.
		var floor  = Math.max( 1, parseInt( input.getAttribute( 'min' ), 10 ) || 0 );
		var timer;

		var sync = function () {
			if ( less ) {
				less.disabled = ( parseInt( input.value, 10 ) || 0 ) <= floor;
			}
		};

		box.querySelectorAll( '[data-k-step]' ).forEach( function ( button ) {
			button.hidden = false;
			button.addEventListener( 'click', function ( event ) {
				event.preventDefault();
				input.value = clamp( ( parseInt( input.value, 10 ) || 0 ) + parseInt( button.getAttribute( 'data-k-step' ), 10 ), floor, maxQty );
				sync();
				input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			} );
		} );

		input.addEventListener( 'input', sync );
		sync();

		if ( ! basket ) {
			return;
		}

		// Sepet: art arda tiklamalar tek gonderimde toplanir.
		input.addEventListener( 'change', function () {
			window.clearTimeout( timer );
			timer = window.setTimeout( function () {
				basket.submit();
			}, 600 );
		} );

		// Enter formun ilk dugmesine (bir satirin −'sine) basmasin: yalnizca adetler kaydedilir.
		input.addEventListener( 'keydown', function ( event ) {
			if ( 'Enter' === event.key ) {
				event.preventDefault();
				window.clearTimeout( timer );
				basket.submit();
			}
		} );
	} );

	// Adet degisince kendiliginden kaydedildigi icin "Adetleri güncelle" gerekmez.
	doc.querySelectorAll( '[data-k-basket-update]' ).forEach( function ( button ) {
		button.hidden = true;
	} );

	/* "Sepeti temizle" onay sorar. */
	doc.querySelectorAll( 'form[data-k-confirm]' ).forEach( function ( form ) {
		form.addEventListener( 'submit', function ( event ) {
			var question = form.getAttribute( 'data-k-confirm' );

			if ( question && ! window.confirm( question ) ) {
				event.preventDefault();
			}
		} );
	} );

	/* ---------------------------------------------------------------- */
	/* Gonderim donusu: ilk hatali alan, yoksa sonuc bandi odaklanir.    */
	/* ---------------------------------------------------------------- */

	var send   = doc.getElementById( 'teklif-gonder' );
	var target = send ? ( send.querySelector( '[aria-invalid="true"]' ) || doc.querySelector( '[data-k-focus]' ) ) : doc.querySelector( '[data-k-focus]' );

	if ( target && /[?&]kc=[a-f0-9]{20}(?:&|$)/.test( window.location.search ) ) {
		// Sayfa yuklenip #teklif-gonder capasina gidildikten sonra: capa odagi ezmesin.
		var focusTarget = function () {
			window.setTimeout( function () {
				target.focus( { preventScroll: true } );
				target.scrollIntoView( { block: 'center' } );
			}, 0 );
		};

		if ( 'complete' === doc.readyState ) {
			focusTarget();
		} else {
			window.addEventListener( 'load', focusTarget );
		}
	}

	/* Hatali alan duzeltilince isaret ve hata metni kalkar. */
	doc.addEventListener( 'input', function ( event ) {
		var field = event.target;

		if ( ! field.matches || ! field.matches( '[aria-invalid="true"]' ) ) {
			return;
		}

		var filled = 'checkbox' === field.type ? field.checked : '' !== field.value.trim();

		if ( filled ) {
			field.removeAttribute( 'aria-invalid' );

			var message = doc.getElementById( field.id + '-error' );

			if ( message ) {
				message.remove();
			}
		}
	} );
}() );
