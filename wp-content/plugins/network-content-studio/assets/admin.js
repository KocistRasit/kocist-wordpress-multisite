/**
 * Icerik Studyosu panel davranislari.
 *
 * - Onizlemeden gelen tiklamayi karsilar, ilgili bilesenin formunu yukler
 * - Formu AJAX ile kaydeder, onizlemeyi tazeler
 * - Tekrarli satir ekle/sil/tasi, bolum sirasi (yukari/asagi)
 * - Kaydedilmemis degisiklik takibi ve "Vazgec"
 *
 * Sunucuya giden her istek panel nonce'u ile imzalanir. JavaScript kapaliyken
 * ayni islevler klasik form gonderimiyle calisir.
 */
( function () {
	'use strict';

	var cfg = window.nwcsPanel || {};
	var T = cfg.text || {};

	var wrap = document.querySelector( '.nwcs-wrap' );
	if ( ! wrap ) {
		return;
	}

	var siteId = wrap.getAttribute( 'data-nwcs-site' );
	var pageKey = wrap.getAttribute( 'data-nwcs-page' );
	var editorBox = wrap.querySelector( '[data-nwcs-editor]' );
	var previewWrap = wrap.querySelector( '[data-nwcs-preview-wrap]' );
	var frame = wrap.querySelector( '[data-nwcs-preview]' );
	var toast = wrap.querySelector( '[data-nwcs-toast]' );
	var screens = {
		list: wrap.querySelector( '[data-nwcs-screen="list"]' ),
		editor: wrap.querySelector( '[data-nwcs-screen="editor"]' )
	};

	var dirty = false;
	var rowCounter = 0;
	var pendingFocus = null;
	// Duzenleyicide acik olan sayfa; onizlemeden gelen tiklama ile degisebilir.
	var activePage = pageKey;

	/* ---------------- yardimcilar ---------------- */

	function showToast( message, kind ) {
		if ( ! toast ) {
			return;
		}
		toast.textContent = message;
		toast.className = 'nwcs-toast' + ( kind ? ' is-' + kind : '' );
		toast.hidden = false;

		clearTimeout( showToast.timer );
		// Hata metni ne yapilacagini anlatir; okunacak kadar uzun kalsin.
		if ( 'busy' !== kind ) {
			showToast.timer = setTimeout( function () {
				toast.hidden = true;
			}, 'error' === kind ? 9000 : 3200 );
		}
	}

	// Sayfada birden cok form olabilir (SEO sekmesi); gosterge ilgili formunki.
	function setDirty( value, form ) {
		dirty = value;
		var scope = form || currentForm() || wrap;
		var badge = scope.querySelector( '[data-nwcs-dirty]' );
		if ( badge ) {
			badge.hidden = ! value;
		}
	}

	function showScreen( name ) {
		Object.keys( screens ).forEach( function ( key ) {
			if ( screens[ key ] ) {
				screens[ key ].classList.toggle( 'is-active', key === name );
			}
		} );

		if ( 'editor' === name ) {
			revealSide();
		}
	}

	/*
	 * Sol sutun ekran boyunda ve yapiskan; ustunde site seridi oldugu icin sayfa
	 * en ustteyken sutunun alti (Kaydet dugmesi) ekranin disinda kaliyordu.
	 * Duzenleyici acilinca sayfa, sutun yapistigi yere gelecek kadar kaydirilir.
	 */
	function revealSide() {
		var side = wrap.querySelector( '.nwcs-side' );

		if ( ! side || window.matchMedia( '(max-width: 1100px)' ).matches ) {
			return;
		}

		var stickTop = parseFloat( window.getComputedStyle( side ).top ) || 0;
		var offset = side.getBoundingClientRect().top - stickTop;

		if ( offset > 1 ) {
			window.scrollBy( 0, offset );
		}
	}

	// Baglantiyla dogrudan bir bolume gelindiyse (…&component=hero) duzenleyici zaten acik.
	if ( screens.editor && screens.editor.classList.contains( 'is-active' ) ) {
		window.addEventListener( 'load', revealSide );
	}

	function post( action, data ) {
		var body = data instanceof FormData ? data : new FormData();

		if ( ! ( data instanceof FormData ) ) {
			Object.keys( data || {} ).forEach( function ( key ) {
				if ( Array.isArray( data[ key ] ) ) {
					data[ key ].forEach( function ( item ) {
						body.append( key + '[]', item );
					} );
				} else {
					body.append( key, data[ key ] );
				}
			} );
		}

		body.set( 'action', action );
		body.set( 'nonce', cfg.nonce );

		// Islem ve nonce adreste de gider: sunucunun alan sayisi siniri
		// (max_input_vars) govdenin sonunu keserse istek yine taninir.
		var url = cfg.ajaxUrl + ( cfg.ajaxUrl.indexOf( '?' ) === -1 ? '?' : '&' ) +
			'action=' + encodeURIComponent( action ) + '&nonce=' + encodeURIComponent( cfg.nonce );

		return fetch( url, { method: 'POST', body: body, credentials: 'same-origin' } )
			.then( function ( response ) {
				return response.json();
			} );
	}

	/*
	 * Uzun urun listeleri yuzlerce alan uretir; sunucu 1000 alandan sonrasini
	 * sessizce atar (max_input_vars) ve secimler yarim kaydedilir. fields[...]
	 * ve products[...] alanlari bu yuzden tek bir JSON alanina toplanir;
	 * PHP'nin ad[a][b][] cozumlemesiyle ayni yapiyi kurar. Dosyalar yerinde kalir.
	 */
	function packForm( data ) {
		var groups = { fields: null, products: null };
		var keep = [];

		data.forEach( function ( value, name ) {
			var match = /^(fields|products)\[/.exec( name );

			if ( ! match || 'string' !== typeof value ) {
				keep.push( [ name, value ] );
				return;
			}

			var parts = name.slice( match[1].length ).match( /\[[^\]]*\]/g ) || [];
			var node = groups[ match[1] ] || ( groups[ match[1] ] = {} );

			parts.forEach( function ( part, i ) {
				var key = part.slice( 1, -1 );
				var last = i === parts.length - 1;

				if ( '' === key ) {
					key = Object.keys( node ).length;
				}

				if ( last ) {
					node[ key ] = value;
				} else {
					if ( 'object' !== typeof node[ key ] || null === node[ key ] ) {
						node[ key ] = {};
					}
					node = node[ key ];
				}
			} );
		} );

		var packed = new FormData();

		keep.forEach( function ( pair ) {
			packed.append( pair[0], pair[1] );
		} );

		Object.keys( groups ).forEach( function ( key ) {
			if ( groups[ key ] ) {
				packed.append( key + '_json', JSON.stringify( groups[ key ] ) );
			}
		} );

		return packed;
	}

	/*
	 * Urun listesi suzgeci: arama (Turkce harf ve buyuk/kucuk harf farki
	 * gozetilmez; ad, kod, kategori) ve "yalnizca secilenler". Kayda etkisi
	 * yok; gizlenen satirlar formda kalir.
	 */
	function foldText( text ) {
		return String( text )
			.replace( /İ/g, 'i' ).replace( /I/g, 'ı' )
			.toLowerCase()
			.replace( /ı/g, 'i' ).replace( /ğ/g, 'g' ).replace( /ü/g, 'u' )
			.replace( /ş/g, 's' ).replace( /ö/g, 'o' ).replace( /ç/g, 'c' )
			.replace( /â/g, 'a' ).replace( /î/g, 'i' ).replace( /û/g, 'u' );
	}

	function filterProducts( field ) {
		if ( ! field ) {
			return;
		}

		var search = field.querySelector( '[data-nwcs-products-search]' );
		var only = field.querySelector( '[data-nwcs-products-only]' );
		var count = field.querySelector( '[data-nwcs-products-count]' );
		var words = foldText( search ? search.value : '' ).split( /\s+/ ).filter( Boolean );
		var items = field.querySelectorAll( '[data-nwcs-product]' );
		var shown = 0;
		var picked = 0;

		items.forEach( function ( item ) {
			var box = item.querySelector( '[data-nwcs-product-pick]' );
			var haystack = item.getAttribute( 'data-search' ) || '';
			var match = words.every( function ( word ) {
				return haystack.indexOf( word ) !== -1;
			} );

			if ( box && box.checked ) {
				picked++;
			}

			if ( only && only.checked && box && ! box.checked ) {
				match = false;
			}

			item.hidden = ! match;
			shown += match ? 1 : 0;
		} );

		if ( count ) {
			count.textContent = picked + ' / ' + items.length + ' seçili' + ( shown < items.length ? ' · ' + shown + ' gösteriliyor' : '' );
		}
	}

	wrap.addEventListener( 'input', function ( event ) {
		if ( event.target.matches && event.target.matches( '[data-nwcs-products-search]' ) ) {
			filterProducts( event.target.closest( '.nwcs-field--products' ) );
		}
	} );

	// Urun gorsel secimi: secenekler ilk dokunusta ortak sablondan doldurulur.
	function fillMediaSelect( event ) {
		var select = event.target;

		if ( ! select.matches || ! select.matches( 'select[data-nwcs-lazy-media]' ) ) {
			return;
		}

		var source = select.closest( '.nwcs-field--products' ).querySelector( 'template[data-nwcs-media-options]' );
		var value = select.value;

		select.removeAttribute( 'data-nwcs-lazy-media' );

		if ( ! source ) {
			return;
		}

		select.innerHTML = source.innerHTML;
		select.value = value;
	}

	wrap.addEventListener( 'mousedown', fillMediaSelect );
	wrap.addEventListener( 'focusin', fillMediaSelect );

	// Arama kutusunda Enter formu gondermesin.
	wrap.addEventListener( 'keydown', function ( event ) {
		if ( 'Enter' === event.key && event.target.matches && event.target.matches( '[data-nwcs-products-search]' ) ) {
			event.preventDefault();
		}
	} );

	wrap.querySelectorAll( '.nwcs-field--products' ).forEach( filterProducts );

	/**
	 * Galeriye bir gorsel dugumu ekler (kitaplik aramasi ve eski secim kutusu).
	 * Galeride zaten varsa eklemez; eklediyse true.
	 */
	function appendGalleryItem( gallery, id, thumb ) {
		id = String( parseInt( id, 10 ) || 0 );

		if ( ! gallery || '0' === id || gallery.querySelector( 'input[name="gallery[]"][value="' + id + '"]' ) ) {
			return false;
		}

		var node = document.createElement( 'div' );
		node.className = 'nwcs-gallery__item';
		node.setAttribute( 'data-nwcs-gallery-item', '' );
		node.innerHTML =
			'<input type="hidden" name="gallery[]" value="' + id + '" />' +
			'<img alt="" />' +
			'<div class="nwcs-gallery__tools">' +
			'<button type="button" class="nwcs-move" data-nwcs-gallery-move="up" aria-label="Öne al">↑</button>' +
			'<button type="button" class="nwcs-move" data-nwcs-gallery-move="down" aria-label="Geri al">↓</button>' +
			'<button type="button" class="nwcs-move nwcs-row__delete" data-nwcs-gallery-remove aria-label="Çıkar">×</button>' +
			'</div>';
		node.querySelector( 'img' ).src = thumb || '';
		gallery.appendChild( node );

		return true;
	}

	function reloadPreview() {
		if ( ! frame ) {
			return;
		}
		previewWrap.classList.add( 'is-loading' );
		// Kendi alan adindaki sitede cerceve baska kokenden: location.reload()
		// orada engellenir, adresi yeniden vermek her durumda calisir.
		frame.src = frame.src;
	}

	/* ---------------- duzenleyici yukleme ---------------- */

	/**
	 * Bilesen duzenleyicisini yukler.
	 *
	 * page: bilesenin ait oldugu sayfa. Onizlemede "Tum Sayfalar" bileseni
	 * (ust menu, footer) tiklandiginda panel baska bir sayfada olabilir; o yuzden
	 * sayfa anahtari her zaman acikca tasinir.
	 */
	function openComponent( componentKey, focus, page ) {
		if ( dirty && ! window.confirm( T.confirmDiscard ) ) {
			return;
		}

		activePage = page || activePage;

		pendingFocus = focus || null;
		setDirty( false );
		showScreen( 'editor' );
		markActivePage( activePage );
		editorBox.innerHTML = '<p class="nwcs-empty">' + ( T.loading || '' ) + '</p>';

		post( 'nwcs_editor', { site: siteId, content_page: activePage, component: componentKey } )
			.then( function ( result ) {
				if ( ! result || ! result.success ) {
					editorBox.innerHTML = '<p class="nwcs-empty">Bölüm açılamadı. Paneli yenileyip bölüme yeniden tıklayın.</p>';
					return;
				}

				editorBox.innerHTML = result.data.html;
				editorBox.querySelectorAll( '.nwcs-field--products' ).forEach( filterProducts );
				markActiveSection( componentKey );
				applyFocus();
				updateHistory( componentKey );
			} )
			.catch( function () {
				editorBox.innerHTML = '<p class="nwcs-empty">Bölüm açılamadı. Bağlantınızı kontrol edip paneli yenileyin.</p>';
			} );
	}

	function markActiveSection( componentKey ) {
		wrap.querySelectorAll( '[data-nwcs-open-component]' ).forEach( function ( link ) {
			link.classList.toggle( 'is-active', link.getAttribute( 'data-nwcs-open-component' ) === componentKey );
		} );
	}

	/**
	 * Sayfa sekmesini isaretler. Sunucu tarafindaki bolum listesi hala eski
	 * sayfaya ait oldugundan, "Bolumler"e donuste o sayfaya gidilir.
	 */
	function markActivePage( page ) {
		wrap.querySelectorAll( '.nwcs-pages__tab' ).forEach( function ( tab ) {
			var tabPage = new URL( tab.href, window.location.origin ).searchParams.get( 'content_page' );
			tab.classList.toggle( 'is-active', tabPage === page );
		} );
	}

	function updateHistory( componentKey ) {
		var url = new URL( window.location.href );
		url.searchParams.set( 'content_page', activePage );
		url.searchParams.set( 'component', componentKey );
		window.history.replaceState( {}, '', url.toString() );
	}

	function panelUrl( page, component ) {
		var url = new URL( window.location.href );
		url.searchParams.set( 'content_page', page );

		if ( component ) {
			url.searchParams.set( 'component', component );
		} else {
			url.searchParams.delete( 'component' );
		}

		return url.toString();
	}

	/**
	 * Onizlemeden gelen alan yolunu forma yansitir.
	 * focus = { field: 'title' } veya { field: 'items.title', row: 2 }
	 */
	function applyFocus() {
		if ( ! pendingFocus ) {
			return;
		}

		var focus = pendingFocus;
		pendingFocus = null;

		var node = null;

		if ( null !== focus.row && undefined !== focus.row ) {
			var repeaterName = focus.field.split( '.' )[0];
			var repeater = editorBox.querySelector( '[data-nwcs-repeater][data-nwcs-field="' + repeaterName + '"]' );
			var row = repeater && repeater.querySelectorAll( '[data-nwcs-row]' )[ parseInt( focus.row, 10 ) ];

			if ( row ) {
				row.classList.add( 'is-nwcs-focus' );
				node = row.querySelector( '[data-nwcs-field="' + focus.field + '"]' ) || row;
			}
		} else {
			node = editorBox.querySelector( '[data-nwcs-field="' + focus.field + '"]' );
		}

		if ( ! node ) {
			return;
		}

		node.classList.add( 'is-nwcs-focus' );
		node.scrollIntoView( { block: 'center' } );

		var input = node.querySelector( 'input[type="text"], textarea, select' );
		if ( input ) {
			input.focus();
			if ( input.select ) {
				input.select();
			}
		}

		setTimeout( function () {
			node.classList.remove( 'is-nwcs-focus' );
		}, 2200 );
	}

	/* ---------------- onizleme kopru mesajlari ---------------- */

	window.addEventListener( 'message', function ( event ) {
		if ( ! event.data || 'object' !== typeof event.data ) {
			return;
		}

		// Mesaj yalnizca onizleme cercevesinden kabul edilir (site kendi alan
		// adindaysa koken panelinkinden farklidir).
		if ( ! frame || event.source !== frame.contentWindow || event.origin !== new URL( frame.src ).origin ) {
			return;
		}

		if ( 'nwcs:ready' === event.data.type ) {
			previewWrap.classList.remove( 'is-loading' );
			return;
		}

		if ( 'nwcs:edit' !== event.data.type ) {
			return;
		}

		var parts = String( event.data.target || '' ).split( '.' );
		if ( parts.length < 2 ) {
			return;
		}

		// target: "<sayfa>.<bilesen>[.<alan>[.<altalan>]]"
		var targetPage = parts[0];
		var componentKey = parts[1];
		var fieldPath = parts.slice( 2 ).join( '.' );

		openComponent(
			componentKey,
			{
				field: fieldPath,
				row: null === event.data.row ? null : event.data.row
			},
			targetPage
		);
	} );

	/* ---------------- tiklamalar ---------------- */

	wrap.addEventListener( 'click', function ( event ) {
		var target = event.target;

		// Yonetim menusunu ac/kapat (ust seritteki uc cizgili marka dugmesi).
		// Tercih tarayicida hatirlanir; sunucu varsayilan olarak menuyu katlar.
		if ( target.closest && target.closest( '[data-nwcs-menu]' ) ) {
			var folded = document.body.classList.toggle( 'folded' );
			try {
				window.localStorage.setItem( 'nwcsMenuOpen', folded ? '0' : '1' );
			} catch ( e ) {}
			return;
		}

		// Bolum ac (soldaki liste her zaman sunucudan gelen sayfaya aittir)
		var opener = target.closest ? target.closest( '[data-nwcs-open-component]' ) : null;
		if ( opener ) {
			event.preventDefault();
			openComponent( opener.getAttribute( 'data-nwcs-open-component' ), null, pageKey );
			return;
		}

		// Bolumler ekranina don
		var back = target.closest ? target.closest( '[data-nwcs-back]' ) : null;
		if ( back ) {
			event.preventDefault();

			// Baska bir sayfanin bileseni acildiysa o sayfanin listesine gidilir.
			if ( activePage !== pageKey ) {
				if ( dirty && ! window.confirm( T.confirmDiscard ) ) {
					return;
				}
				dirty = false;
				window.location.href = panelUrl( activePage );
				return;
			}

			if ( dirty && ! window.confirm( T.confirmDiscard ) ) {
				return;
			}
			setDirty( false );
			showScreen( 'list' );
			return;
		}

		// Onizleme genisligi
		var device = target.closest ? target.closest( '[data-nwcs-device]' ) : null;
		if ( device ) {
			wrap.querySelectorAll( '[data-nwcs-device]' ).forEach( function ( button ) {
				button.classList.toggle( 'is-active', button === device );
			} );
			previewWrap.classList.toggle( 'is-mobile', 'mobile' === device.getAttribute( 'data-nwcs-device' ) );
			return;
		}

		// Onizlemeyi yenile
		if ( target.closest && target.closest( '[data-nwcs-reload]' ) ) {
			reloadPreview();
			return;
		}

		// Galeri: sirayi degistir ya da gorseli cikar
		var galleryMove = target.closest ? target.closest( '[data-nwcs-gallery-move]' ) : null;
		if ( galleryMove ) {
			var gItem = galleryMove.closest( '[data-nwcs-gallery-item]' );
			var gNeighbour = 'up' === galleryMove.getAttribute( 'data-nwcs-gallery-move' )
				? gItem.previousElementSibling
				: gItem.nextElementSibling;

			if ( gNeighbour ) {
				if ( 'up' === galleryMove.getAttribute( 'data-nwcs-gallery-move' ) ) {
					gItem.parentNode.insertBefore( gItem, gNeighbour );
				} else {
					gItem.parentNode.insertBefore( gNeighbour, gItem );
				}
			}
			return;
		}

		if ( target.closest && target.closest( '[data-nwcs-gallery-remove]' ) ) {
			target.closest( '[data-nwcs-gallery-item]' ).remove();
			return;
		}

		// Urun istisnalarini ac/kapat
		var productToggle = target.closest ? target.closest( '[data-nwcs-product-toggle]' ) : null;
		if ( productToggle ) {
			var card = productToggle.closest( '[data-nwcs-product]' );
			var isOpen = card.classList.toggle( 'is-open' );
			productToggle.setAttribute( 'aria-expanded', isOpen ? 'true' : 'false' );
			productToggle.textContent = isOpen ? 'Kapat' : 'Özelleştirilmiş';
			return;
		}

		// Urun sirasi
		var productMove = target.closest ? target.closest( '[data-nwcs-product-move]' ) : null;
		if ( productMove ) {
			var item = productMove.closest( '[data-nwcs-product]' );
			var direction = productMove.getAttribute( 'data-nwcs-product-move' );

			if ( 'top' === direction ) {
				if ( item.previousElementSibling ) {
					item.parentNode.insertBefore( item, item.parentNode.firstElementChild );
					setDirty( true );
				}
				return;
			}

			// Suzgec acikken gizli satirlarin arkasina gecmek bir sey degistirmez; gorunen komsuya atlanir.
			var neighbour = 'up' === direction ? item.previousElementSibling : item.nextElementSibling;
			while ( neighbour && neighbour.hidden ) {
				neighbour = 'up' === direction ? neighbour.previousElementSibling : neighbour.nextElementSibling;
			}

			if ( neighbour ) {
				if ( 'up' === productMove.getAttribute( 'data-nwcs-product-move' ) ) {
					item.parentNode.insertBefore( item, neighbour );
				} else {
					item.parentNode.insertBefore( neighbour, item );
				}
				setDirty( true );
			}
			return;
		}

		// Ikon listesini ac/kapat
		var iconToggle = target.closest ? target.closest( '[data-nwcs-icon-toggle]' ) : null;
		if ( iconToggle ) {
			var grid = iconToggle.closest( '.nwcs-field' ).querySelector( '.nwcs-icons' );
			var opened = grid.classList.toggle( 'is-open' );
			iconToggle.setAttribute( 'aria-expanded', opened ? 'true' : 'false' );
			iconToggle.textContent = opened ? 'Kapat' : 'Değiştir';
			return;
		}

		// Bolum sirasi
		var move = target.closest ? target.closest( '.nwcs-section__move [data-nwcs-move]' ) : null;
		if ( move ) {
			moveSection( move );
			return;
		}

		// Form ici dugmeler
		handleFormButtons( event );
	} );

	/* ---------------- bolum sirasi ---------------- */

	function moveSection( button ) {
		var item = button.closest( '.nwcs-section' );
		var list = item.parentNode;
		var direction = button.getAttribute( 'data-nwcs-move' );
		var sortableItems = Array.prototype.filter.call( list.children, function ( node ) {
			return node.classList.contains( 'is-sortable' );
		} );
		var index = sortableItems.indexOf( item );

		if ( 'up' === direction && index > 0 ) {
			list.insertBefore( item, sortableItems[ index - 1 ] );
		} else if ( 'down' === direction && index < sortableItems.length - 1 ) {
			list.insertBefore( sortableItems[ index + 1 ], item );
		} else {
			return;
		}

		item.classList.add( 'is-moving' );
		setTimeout( function () {
			item.classList.remove( 'is-moving' );
		}, 600 );

		saveOrder( list );
	}

	function saveOrder( list ) {
		var order = Array.prototype.filter
			.call( list.children, function ( node ) {
				return node.classList.contains( 'is-sortable' );
			} )
			.map( function ( node ) {
				return node.getAttribute( 'data-nwcs-section-key' );
			} );

		showToast( T.saving || '', 'busy' );

		post( 'nwcs_order', { site: siteId, content_page: pageKey, order: order } )
			.then( function ( result ) {
				if ( result && result.success ) {
					showToast( result.data.message, null );
					reloadPreview();
				} else {
					showToast( T.saveError || '', 'error' );
				}
			} )
			.catch( function () {
				showToast( T.saveError || '', 'error' );
			} );
	}

	/* ---------------- form davranislari ---------------- */

	function currentForm() {
		return editorBox ? editorBox.querySelector( '[data-nwcs-form]' ) : null;
	}

	function renumber( repeater ) {
		repeater.querySelectorAll( '[data-nwcs-rows] > [data-nwcs-row]' ).forEach( function ( row, index ) {
			var sort = row.querySelector( '[data-nwcs-sort]' );
			if ( sort ) {
				sort.value = String( index );
			}
			var number = row.querySelector( '[data-nwcs-row-number]' );
			if ( number ) {
				number.textContent = String( index + 1 );
			}
		} );
	}

	function handleFormButtons( event ) {
		var button = event.target.closest ? event.target.closest( 'button' ) : null;
		var form = currentForm();

		if ( ! button || ! form || ! form.contains( button ) ) {
			return;
		}

		// Vazgec
		if ( button.hasAttribute( 'data-nwcs-discard' ) ) {
			if ( dirty && ! window.confirm( T.confirmDiscard ) ) {
				return;
			}
			setDirty( false );
			openComponent( form.querySelector( '[name="component"]' ).value, null );
			return;
		}

		// Satir ekle
		if ( button.hasAttribute( 'data-nwcs-add-row' ) ) {
			event.preventDefault();
			var repeater = button.closest( '[data-nwcs-repeater]' );
			var list = repeater.querySelector( '[data-nwcs-rows]' );
			var max = parseInt( repeater.getAttribute( 'data-max' ), 10 ) || 20;

			if ( list.children.length >= max ) {
				showToast( 'Bu bölümde en fazla ' + max + ' satır olabilir. Yeni satır eklemek için önce bir satırı silin.', 'error' );
				return;
			}

			rowCounter += 1;
			var markup = repeater
				.querySelector( '[data-nwcs-row-template]' )
				.innerHTML.split( '__ROW__' )
				.join( 'n' + rowCounter );
			var holder = document.createElement( 'div' );
			holder.innerHTML = markup;
			var newRow = holder.querySelector( '[data-nwcs-row]' );
			list.appendChild( newRow );
			renumber( repeater );
			setDirty( true );

			var first = newRow.querySelector( 'input[type="text"], textarea' );
			if ( first ) {
				first.focus();
			}
			return;
		}

		// Satir sil
		if ( button.hasAttribute( 'data-nwcs-remove-row' ) ) {
			event.preventDefault();
			if ( ! window.confirm( T.confirmRow ) ) {
				return;
			}
			var repeaterOfRow = button.closest( '[data-nwcs-repeater]' );
			button.closest( '[data-nwcs-row]' ).remove();
			renumber( repeaterOfRow );
			setDirty( true );
			return;
		}

		// Satir tasi
		if ( button.hasAttribute( 'data-nwcs-move' ) ) {
			event.preventDefault();
			var direction = button.getAttribute( 'data-nwcs-move' );
			var row = button.closest( '[data-nwcs-row]' );
			var sibling = 'up' === direction ? row.previousElementSibling : row.nextElementSibling;

			if ( ! sibling ) {
				return;
			}

			if ( 'up' === direction ) {
				row.parentNode.insertBefore( row, sibling );
			} else {
				row.parentNode.insertBefore( sibling, row );
			}

			renumber( button.closest( '[data-nwcs-repeater]' ) );
			setDirty( true );
		}
	}

	// Degisiklik takibi
	wrap.addEventListener( 'input', function ( event ) {
		var form = event.target.closest( '[data-nwcs-form]' );
		if ( form ) {
			setDirty( true, form );
		}
	} );
	// Havuz/medya sayfalarindaki kontroller (duzenleyici formuna bagli degil)
	wrap.addEventListener( 'change', function ( event ) {
		// Formdaki her degisiklik kaydedilmemis sayilir.
		var changedForm = event.target.closest( '[data-nwcs-form]' );
		if ( changedForm ) {
			setDirty( true, changedForm );
		}

		// Galeriye kitapliktan gorsel ekle (eski secim kutusu kullanan sayfa kalirsa)
		if ( event.target.matches( '[data-nwcs-gallery-add]' ) && event.target.value ) {
			var picker = event.target;
			appendGalleryItem(
				picker.closest( '.nwcs-field' ).querySelector( '[data-nwcs-gallery]' ),
				picker.value,
				picker.selectedOptions[0].getAttribute( 'data-thumb' )
			);
			picker.value = '';
			return;
		}

		// Kategori etiketi
		if ( event.target.matches( '[data-nwcs-tag]' ) ) {
			event.target.closest( '.nwcs-tag' ).classList.toggle( 'is-active', event.target.checked );
		}

		// Toplu islem: tumunu sec
		if ( event.target.matches( '[data-nwcs-check-all]' ) ) {
			var checked = event.target.checked;
			wrap.querySelectorAll( '[data-nwcs-check]' ).forEach( function ( box ) {
				box.checked = checked;
			} );
		}

		// Urun secim kutusu
		if ( event.target.matches( '[data-nwcs-product-pick]' ) ) {
			event.target.closest( '[data-nwcs-product]' ).classList.toggle( 'is-selected', event.target.checked );
			filterProducts( event.target.closest( '.nwcs-field--products' ) );
		}

		if ( event.target.matches( '[data-nwcs-products-only]' ) ) {
			filterProducts( event.target.closest( '.nwcs-field--products' ) );
		}

		// Urun secim kipi
		if ( event.target.matches( '[data-nwcs-mode]' ) ) {
			wrap.querySelectorAll( '.nwcs-mode' ).forEach( function ( label ) {
				label.classList.toggle( 'is-active', label.contains( event.target ) );
			} );

			// "Hepsi" kipinde butun urunler gosterilir; kutular da isaretlenir.
			if ( 'all' === event.target.value ) {
				wrap.querySelectorAll( '[data-nwcs-product-pick]' ).forEach( function ( box ) {
					box.checked = true;
					box.closest( '[data-nwcs-product]' ).classList.add( 'is-selected' );
				} );
			}
		}

		// Ikon secimi
		if ( event.target.matches( '.nwcs-icons input[type="radio"]' ) ) {
			var group = event.target.closest( '.nwcs-icons' );
			var chosen = null;

			group.querySelectorAll( '.nwcs-icon' ).forEach( function ( label ) {
				var active = label.contains( event.target );
				label.classList.toggle( 'is-active', active );
				if ( active ) {
					chosen = label;
				}
			} );

			// Kapali gorunumdeki ozeti guncelle.
			var summary = event.target.closest( '.nwcs-field' ).querySelector( '[data-nwcs-icon-current]' );
			if ( summary && chosen ) {
				summary.innerHTML = '';
				var svg = chosen.querySelector( 'svg' );
				if ( svg ) {
					summary.appendChild( svg.cloneNode( true ) );
				}
				var name = chosen.querySelector( '.nwcs-icon__label' );
				summary.appendChild( document.createTextNode( name ? name.textContent : 'İkon yok' ) );
			}
		}

		// Yeni gorsel onizlemesi
		if ( event.target.matches( '.nwcs-image input[type="file"]' ) && event.target.files && event.target.files[0] ) {
			var box = event.target.closest( '.nwcs-image' ).querySelector( '.nwcs-image__preview' );
			box.innerHTML = '';
			var img = document.createElement( 'img' );
			img.src = URL.createObjectURL( event.target.files[0] );
			img.alt = '';
			box.appendChild( img );
		}
	} );

	// Kaydet
	wrap.addEventListener( 'submit', function ( event ) {
		var form = event.target.closest ? event.target.closest( '[data-nwcs-form]' ) : null;

		if ( ! form ) {
			return;
		}

		event.preventDefault();

		var data = packForm( new FormData( form ) );
		showToast( T.saving || '', 'busy' );

		post( 'nwcs_save_ajax', data )
			.then( function ( result ) {
				if ( ! result || ! result.success ) {
					showToast( ( result && result.data && result.data.message ) || T.saveError, 'error' );
					return;
				}

				setDirty( false, form );
				showToast( result.data.message, result.data.hasError ? 'error' : null );
				reloadPreview();

				// Yuklenen dosya girdileri temizlensin ki tekrar gonderilmesin.
				form.querySelectorAll( 'input[type="file"]' ).forEach( function ( input ) {
					input.value = '';
				} );
			} )
			.catch( function () {
				showToast( T.saveError || '', 'error' );
			} );
	} );

	window.addEventListener( 'beforeunload', function ( event ) {
		if ( ! dirty ) {
			return;
		}
		event.preventDefault();
		event.returnValue = T.confirmLeave || '';
	} );

	// Menu tercihi: sunucu katlanmis gonderir, kullanici actiysa geri acilir.
	try {
		if ( '1' === window.localStorage.getItem( 'nwcsMenuOpen' ) ) {
			document.body.classList.remove( 'folded' );
		}
	} catch ( e ) {}

	// Onizleme yuklendiginde gostergeyi kapat
	if ( frame ) {
		frame.addEventListener( 'load', function () {
			previewWrap.classList.remove( 'is-loading' );
		} );
	}
	/* ---------------- kitaplik aramasi (urun galerisi) ---------------- */

	( function () {
		var input = wrap.querySelector( '[data-nwcs-media-find]' );
		var finder = document.getElementById( 'nwcs-find-form' );

		if ( ! input || ! finder ) {
			return;
		}

		var box = input.closest( '[data-nwcs-media-find-box]' );
		var list = box.querySelector( '[data-nwcs-media-find-list]' );
		var gallery = box.closest( '.nwcs-field' ).querySelector( '[data-nwcs-gallery]' );
		var timer = null;
		var ticket = 0;

		function inGallery() {
			return Array.prototype.map.call( gallery.querySelectorAll( 'input[name="gallery[]"]' ), function ( field ) {
				return field.value;
			} );
		}

		function render( items ) {
			list.innerHTML = '';

			if ( ! items.length ) {
				var empty = document.createElement( 'li' );
				empty.className = 'nwcs-find__empty';
				empty.textContent = 'Bu aramaya uyan görsel yok.';
				list.appendChild( empty );
				return;
			}

			items.forEach( function ( item ) {
				var row = document.createElement( 'li' );
				row.className = 'nwcs-find__item';
				row.innerHTML =
					'<span class="nwcs-find__pick"><img alt="" loading="lazy" />' +
					'<span class="nwcs-find__name"><strong></strong> <small></small></span></span>' +
					'<button type="button" class="button button-small">Ekle</button>';
				row.querySelector( 'img' ).src = item.thumb || '';
				row.querySelector( 'strong' ).textContent = item.title;
				row.querySelector( 'small' ).textContent = item.name;
				row.querySelector( 'button' ).setAttribute( 'aria-label', 'Galeriye ekle: ' + item.title );
				row.querySelector( 'button' ).addEventListener( 'click', function () {
					if ( appendGalleryItem( gallery, item.id, item.thumb ) ) {
						setDirty( true );
					}
					row.remove();
				} );
				list.appendChild( row );
			} );
		}

		function search() {
			var mine = ++ticket;

			post( 'nwcs_media_find', { q: input.value, haric: inGallery() } )
				.then( function ( json ) {
					if ( mine === ticket ) {
						render( json && json.success ? json.data.items : [] );
					}
				} )
				.catch( function () {} );
		}

		// JS'li durumda GET formu gonderilmez; sonuc yerinde gelir.
		finder.addEventListener( 'submit', function ( event ) {
			event.preventDefault();
			search();
		} );

		input.addEventListener( 'input', function () {
			clearTimeout( timer );
			timer = setTimeout( search, 250 );
		} );
	}() );

	/* ---------------- sayfa bulucu ---------------- */

	( function () {
		var finder = wrap.querySelector( '[data-nwcs-finder]' );
		if ( ! finder ) {
			return;
		}

		var input = finder.querySelector( '[data-nwcs-finder-input]' );
		var list = finder.querySelector( '[data-nwcs-finder-list]' );
		var status = finder.querySelector( '[data-nwcs-finder-status]' );
		var items = [];

		try {
			items = JSON.parse( finder.querySelector( '[data-nwcs-finder-data]' ).textContent || '[]' );
		} catch ( e ) {
			return;
		}

		// Turkce harfler ve buyuk/kucuk harf farki aramayi bozmasin: "civi" = "Çivi".
		var fold = function ( text ) {
			return String( text )
				.replace( /İ/g, 'i' ).replace( /I/g, 'ı' )
				.toLowerCase()
				.replace( /ı/g, 'i' ).replace( /ğ/g, 'g' ).replace( /ü/g, 'u' )
				.replace( /ş/g, 's' ).replace( /ö/g, 'o' ).replace( /ç/g, 'c' )
				.replace( /[âà]/g, 'a' ).replace( /[îì]/g, 'i' ).replace( /[ûù]/g, 'u' );
		};

		items.forEach( function ( item ) {
			item.f = fold( item.t + ' ' + item.k );
			item.ft = fold( item.t );
		} );

		var LIMIT = 60;
		var shown = [];
		var active = -1;

		function search( query ) {
			var words = fold( query ).split( /\s+/ ).filter( Boolean );

			// Bos arama: sayfalar ve kategori sayfalari; urunler yazinca gelir.
			if ( ! words.length ) {
				return items.filter( function ( item ) {
					return 'Ürünler' !== item.g;
				} );
			}

			var found = items.filter( function ( item ) {
				return words.every( function ( word ) {
					return -1 !== item.f.indexOf( word );
				} );
			} );

			// Adi aranan kelimeyle baslayanlar once.
			var first = words[ 0 ];
			return found.sort( function ( a, b ) {
				var sa = 0 === a.ft.indexOf( first ) ? 0 : 1;
				var sb = 0 === b.ft.indexOf( first ) ? 0 : 1;
				return sa - sb;
			} );
		}

		function option( item, index ) {
			var li = document.createElement( 'li' );
			li.className = 'nwcs-finder__item';
			li.id = 'nwcs-finder-opt-' + index;
			li.setAttribute( 'role', 'option' );
			li.setAttribute( 'data-index', String( index ) );

			var title = document.createElement( 'span' );
			title.className = 'nwcs-finder__title';
			title.textContent = item.t;

			var kind = document.createElement( 'span' );
			kind.className = 'nwcs-finder__kind';
			kind.textContent = item.k;

			li.appendChild( title );
			li.appendChild( kind );
			return li;
		}

		function render() {
			var query = input.value;
			var results = search( query );
			var total = results.length;

			shown = results.slice( 0, LIMIT );
			active = shown.length ? 0 : -1;
			list.innerHTML = '';

			var group = '';
			shown.forEach( function ( item, index ) {
				if ( item.g !== group ) {
					group = item.g;
					var head = document.createElement( 'li' );
					head.className = 'nwcs-finder__group';
					head.setAttribute( 'role', 'presentation' );
					head.textContent = group;
					list.appendChild( head );
				}
				list.appendChild( option( item, index ) );
			} );

			var note = document.createElement( 'li' );
			note.className = 'nwcs-finder__note';
			note.setAttribute( 'role', 'presentation' );

			if ( ! total ) {
				note.textContent = '“' + query.trim() + '” ile eşleşen sayfa ya da ürün yok. Ürün adının bir kısmını ya da kategorisini yazın.';
				list.appendChild( note );
			} else if ( total > shown.length ) {
				note.textContent = ( total - shown.length ) + ' sonuç daha var; aramayı biraz daha yazın.';
				list.appendChild( note );
			} else if ( ! query.trim() && items.some( function ( item ) { return 'Ürünler' === item.g; } ) ) {
				note.textContent = 'Bir ürüne gitmek için adını yazın.';
				list.appendChild( note );
			}

			status.textContent = total ? total + ' sonuç' : 'Sonuç yok';
			open( true );
			highlight();
		}

		function highlight() {
			var nodes = list.querySelectorAll( '[role="option"]' );

			nodes.forEach( function ( node ) {
				var on = String( active ) === node.getAttribute( 'data-index' );
				node.classList.toggle( 'is-active', on );
				node.setAttribute( 'aria-selected', on ? 'true' : 'false' );

				if ( on ) {
					input.setAttribute( 'aria-activedescendant', node.id );
					node.scrollIntoView( { block: 'nearest' } );
				}
			} );

			if ( active < 0 ) {
				input.removeAttribute( 'aria-activedescendant' );
			}
		}

		function open( state ) {
			list.hidden = ! state;
			input.setAttribute( 'aria-expanded', state ? 'true' : 'false' );
			finder.classList.toggle( 'is-open', state );
		}

		function go( index ) {
			var item = shown[ index ];
			if ( ! item ) {
				return;
			}
			if ( dirty && ! window.confirm( T.confirmDiscard ) ) {
				return;
			}
			dirty = false;
			window.location.href = item.u;
		}

		input.addEventListener( 'input', render );
		input.addEventListener( 'focus', render );

		input.addEventListener( 'keydown', function ( event ) {
			if ( 'ArrowDown' === event.key || 'ArrowUp' === event.key ) {
				event.preventDefault();
				if ( list.hidden ) {
					render();
					return;
				}
				if ( shown.length ) {
					active = ( active + ( 'ArrowDown' === event.key ? 1 : -1 ) + shown.length ) % shown.length;
					highlight();
				}
			} else if ( 'Enter' === event.key ) {
				event.preventDefault();
				go( active );
			} else if ( 'Escape' === event.key ) {
				if ( input.value ) {
					input.value = '';
					render();
				} else {
					open( false );
					input.blur();
				}
			}
		} );

		list.addEventListener( 'mousedown', function ( event ) {
			// Tiklama sirasinda kutu odagi kaybedip liste kapanmasin.
			event.preventDefault();
		} );

		list.addEventListener( 'click', function ( event ) {
			var node = event.target.closest ? event.target.closest( '[role="option"]' ) : null;
			if ( node ) {
				go( parseInt( node.getAttribute( 'data-index' ), 10 ) );
			}
		} );

		list.addEventListener( 'mousemove', function ( event ) {
			var node = event.target.closest ? event.target.closest( '[role="option"]' ) : null;
			if ( node && String( active ) !== node.getAttribute( 'data-index' ) ) {
				active = parseInt( node.getAttribute( 'data-index' ), 10 );
				highlight();
			}
		} );

		input.addEventListener( 'blur', function () {
			window.setTimeout( function () {
				open( false );
			}, 120 );
		} );

		// "/" ile her yerden kutuya: yazi alaninda degilken.
		document.addEventListener( 'keydown', function ( event ) {
			var tag = ( document.activeElement && document.activeElement.tagName ) || '';
			var typing = /^(INPUT|TEXTAREA|SELECT)$/.test( tag ) || ( document.activeElement && document.activeElement.isContentEditable );

			if ( '/' === event.key && ! typing && ! event.ctrlKey && ! event.metaKey && ! event.altKey ) {
				event.preventDefault();
				input.focus();
				input.select();
			}
		} );
	}() );
}() );
