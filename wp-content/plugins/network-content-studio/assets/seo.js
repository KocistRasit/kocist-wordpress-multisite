/**
 * SEO alanlari: karakter sayaci ve canli Google onizlemesi.
 *
 * - data-nwcs-count tasiyan alanin altinda "42 / 60" sayaci. Alan bossa
 *   otomatik degerin (gri yer tutucu) uzunlugu gosterilir.
 * - SEO sekmesindeki her sayfa kartinda, baslik ve aciklama yazildikca
 *   ustteki Google onizlemesi guncellenir; alan bosalinca otomatik degere doner.
 *
 * Icerik Studyosu'nda duzenleyici AJAX ile yuklendigi icin olay dinleme
 * belge duzeyindedir; sonradan gelen alanlar da calisir.
 */
( function () {
	'use strict';

	var counterFor = function ( input ) {
		var counter = input.nextElementSibling;

		if ( ! counter || ! counter.hasAttribute( 'data-nwcs-counter' ) ) {
			counter = document.createElement( 'span' );
			counter.setAttribute( 'data-nwcs-counter', '' );
			counter.className = 'nwcs-counter';
			input.insertAdjacentElement( 'afterend', counter );
		}

		return counter;
	};

	var updateCounter = function ( input ) {
		var limit = parseInt( input.getAttribute( 'data-nwcs-count' ), 10 );

		if ( ! limit ) {
			return;
		}

		var own = input.value.trim();
		var length = ( own || input.getAttribute( 'placeholder' ) || '' ).length;
		var counter = counterFor( input );

		counter.textContent = ( own ? '' : 'otomatik · ' ) + length + ' / ' + limit;
		counter.classList.toggle( 'is-over', length > limit );
	};

	var updatePreview = function ( input ) {
		var card = input.closest( '[data-nwcs-seo-page]' );

		if ( ! card ) {
			return;
		}

		var name = input.getAttribute( 'name' );
		var target = null;

		if ( 'fields[title]' === name ) {
			target = card.querySelector( '[data-nwcs-serp-title]' );
		} else if ( 'fields[description]' === name ) {
			target = card.querySelector( '[data-nwcs-serp-desc]' );
		}

		if ( target ) {
			target.textContent = input.value.trim() || input.getAttribute( 'placeholder' ) || '';
		}
	};

	var handle = function ( event ) {
		var input = event.target;

		if ( ! input.matches || ! input.matches( '[data-nwcs-count]' ) ) {
			return;
		}

		updateCounter( input );
		updatePreview( input );
	};

	document.addEventListener( 'input', handle );
	document.addEventListener( 'focusin', handle );

	document.querySelectorAll( '[data-nwcs-count]' ).forEach( updateCounter );
}() );

/**
 * SEO ve GEO > Paylasim gorselleri: kartlari suzme (elle secilen / otomatik)
 * ve ad ya da adrese gore arama. Yalnizca gorunurluk degisir.
 */
( function () {
	'use strict';

	var tools = document.querySelector( '[data-nwcs-share-tools]' );

	if ( ! tools ) {
		return;
	}

	var filter = '';
	var query = '';

	var fold = function ( text ) {
		return String( text || '' )
			.replace( /İ/g, 'i' ).replace( /I/g, 'ı' )
			.toLowerCase()
			.replace( /ı/g, 'i' ).replace( /ğ/g, 'g' ).replace( /ü/g, 'u' )
			.replace( /ş/g, 's' ).replace( /ö/g, 'o' ).replace( /ç/g, 'c' );
	};

	var apply = function () {
		document.querySelectorAll( '[data-nwcs-share-section]' ).forEach( function ( section ) {
			var shown = 0;

			section.querySelectorAll( '[data-nwcs-share]' ).forEach( function ( card ) {
				var ok = ( ! filter || card.getAttribute( 'data-share-filter' ) === filter )
					&& ( ! query || fold( card.getAttribute( 'data-share-text' ) ).indexOf( query ) !== -1 );

				card.hidden = ! ok;
				shown += ok ? 1 : 0;
			} );

			var count = section.querySelector( '[data-nwcs-share-count]' );
			var empty = section.querySelector( '[data-nwcs-share-empty]' );

			if ( count ) {
				count.textContent = shown;
			}
			if ( empty ) {
				empty.hidden = shown > 0;
			}
		} );
	};

	tools.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( '[data-nwcs-share-filter]' );

		if ( ! button ) {
			return;
		}

		filter = button.getAttribute( 'data-nwcs-share-filter' ) || '';
		tools.querySelectorAll( '[data-nwcs-share-filter]' ).forEach( function ( other ) {
			other.classList.toggle( 'is-active', other === button );
		} );
		apply();
	} );

	var search = tools.querySelector( '[data-nwcs-share-search]' );

	if ( search ) {
		search.addEventListener( 'input', function () {
			query = fold( search.value.trim() );
			apply();
		} );
	}

	// Yer isaretiyle (#share-kat-x) gelindiyse kartin duzenleyicisi acik olsun.
	if ( window.location.hash && /^#share-/.test( window.location.hash ) ) {
		var target = document.querySelector( window.location.hash );
		var details = target ? target.querySelector( 'details' ) : null;

		if ( details ) {
			details.open = true;
		}
	}
}() );
