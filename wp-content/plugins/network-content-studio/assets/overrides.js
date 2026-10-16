/**
 * Urun Havuzu -> Ozellestirmeler bolumu.
 * Site basina istisna degerlerini sayfadan ayrilmadan kaydeder/kaldirir.
 */
( function () {
	'use strict';

	var wrap = document.querySelector( '[data-nwcs-overrides]' );

	if ( ! wrap ) {
		return;
	}

	// Icerik Studyosu'ndaki "değiştir ↗" baglantisi (#nwcs-ovr-<site>) o sitenin
	// kutusunu acar ve adres alanina gider.
	if ( /^#nwcs-ovr-\d+$/.test( window.location.hash ) ) {
		var target = document.getElementById( window.location.hash.slice( 1 ) );

		if ( target && wrap.contains( target ) ) {
			target.open = true;
			target.scrollIntoView( { block: 'start' } );

			var slugInput = target.querySelector( '[data-ovr="slug"]' );

			if ( slugInput ) {
				slugInput.focus( { preventScroll: true } );
			}
		}
	}

	var cfg       = window.nwcsPanel || {};
	var productId = wrap.getAttribute( 'data-product' );

	var post = function ( action, fields ) {
		var body = new FormData();

		body.append( 'action', action );
		body.append( 'nonce', cfg.nonce );
		body.append( 'product', productId );

		Object.keys( fields ).forEach( function ( key ) {
			body.append( key, fields[ key ] );
		} );

		return fetch( cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
			.then( function ( r ) { return r.json(); } )
			// Baglanti koptu ya da yanit okunamadi: tarayicinin Ingilizce hatasi gorunmesin.
			.catch( function () {
				throw new Error( 'Kaydedilemedi: siteye ulaşılamadı. İnternet bağlantınızı kontrol edin, sayfayı yenileyip yeniden deneyin.' );
			} )
			.then( function ( json ) {
				if ( ! json || ! json.success ) {
					var error = new Error( ( json && json.data && json.data.message ) || 'Kaydedilemedi. Sayfayı yenileyip yeniden deneyin; sorun sürerse geliştiriciye haber verin.' );
					error.field = json && json.data ? json.data.field : '';
					throw error;
				}
				return json.data;
			} );
	};

	var readBlock = function ( block ) {
		var values = { blog: block.getAttribute( 'data-blog' ) };

		block.querySelectorAll( '[data-ovr]' ).forEach( function ( field ) {
			var key = field.getAttribute( 'data-ovr' );
			values[ key ] = 'checkbox' === field.type ? ( field.checked ? '1' : '' ) : field.value;
		} );

		return values;
	};

	var flash = function ( block, text, isError ) {
		var status = block.querySelector( '[data-ovr-status]' );

		window.clearTimeout( status.nwcsTimer );
		status.textContent = text;
		status.classList.toggle( 'is-error', !! isError );

		// Hata okunabilsin diye daha uzun kalir.
		status.nwcsTimer = window.setTimeout( function () {
			status.textContent = '';
			status.classList.remove( 'is-error' );
		}, isError ? 9000 : 2500 );
	};

	// Sayfa adresi: alan, "Otomatiğe dön", eski adres notu ve durum seridindeki
	// "Sitede gör" baglantisi sunucunun dondurdugu adrese gore guncellenir.
	var showAddress = function ( block, address ) {
		var box = block.querySelector( '[data-ovr-slug-box]' );

		if ( ! box || ! address ) {
			return;
		}

		var input  = box.querySelector( '[data-ovr="slug"]' );
		var custom = address.slug !== address.auto;

		input.value = custom ? address.slug : '';
		input.removeAttribute( 'aria-invalid' );
		box.querySelector( '[data-ovr-slug-reset]' ).disabled = ! custom;

		var note = box.querySelector( '[data-ovr-slug-note]' );
		var text = 'Otomatik: ' + address.auto + '. Boş bırakırsanız otomatik adres kullanılır. Türkçe harfler sadeleşir, boşluklar tire olur.';

		note.textContent = text;

		if ( address.old && address.old.length ) {
			note.appendChild( document.createElement( 'br' ) );
			note.appendChild( document.createTextNode(
				( 1 === address.old.length ? 'Eski adres (' : 'Eski adresler (' ) + address.old.join( ', ' ) + ') güncel adrese yönlenir.'
			) );
		}

		var name = block.querySelector( '.nwcs-ovr__name' );

		document.querySelectorAll( '.nwcs-status__row' ).forEach( function ( row ) {
			var site = row.querySelector( '.nwcs-status__site' );

			if ( ! site || ! name || site.textContent.trim() !== name.textContent.trim() ) {
				return;
			}

			row.querySelectorAll( '.nwcs-status__act a[target="_blank"]' ).forEach( function ( link ) {
				if ( 0 === link.textContent.indexOf( 'Sitede gör' ) ) {
					link.href = address.url;
				}
			} );
		} );
	};

	var slugError = function ( block, error ) {
		var input = block.querySelector( '[data-ovr="slug"]' );

		if ( input && 'slug' === error.field ) {
			input.setAttribute( 'aria-invalid', 'true' );
			input.focus();
		}

		flash( block, error.message, true );
	};

	var setTag = function ( block, count ) {
		var tag = block.querySelector( '.nwcs-ovr__tag' );

		if ( ! tag || tag.classList.contains( 'nwcs-ovr__tag--off' ) ) {
			return;
		}

		tag.classList.toggle( 'nwcs-ovr__tag--on', count > 0 );
		tag.textContent = count > 0 ? count + ' özelleştirme' : 'Havuzdaki gibi';
	};

	// Fiyat alani yalnizca kutucuk isaretliyken yazilabilir.
	wrap.addEventListener( 'change', function ( event ) {
		var box = event.target.closest( '[data-ovr="price_override"]' );

		if ( ! box ) {
			return;
		}

		var price = box.closest( '.nwcs-ovr__body' ).querySelector( '[data-ovr="price"]' );
		price.disabled = ! box.checked;

		if ( box.checked ) {
			price.focus();
		}
	} );

	wrap.addEventListener( 'click', function ( event ) {
		var block = event.target.closest( '.nwcs-ovr' );

		if ( ! block ) {
			return;
		}

		if ( event.target.closest( '[data-ovr-save]' ) ) {
			var button = event.target.closest( '[data-ovr-save]' );
			button.disabled = true;

			post( 'nwcs_override_save', readBlock( block ) )
				.then( function ( data ) {
					flash( block, data.message );
					setTag( block, data.count );
					showAddress( block, data.address );
					block.querySelector( '[data-ovr-clear]' ).disabled = ! data.count;
				} )
				.catch( function ( error ) { slugError( block, error ); } )
				.finally( function () { button.disabled = false; } );
		}

		if ( event.target.closest( '[data-ovr-slug-reset]' ) ) {
			var reset = event.target.closest( '[data-ovr-slug-reset]' );
			reset.disabled = true;

			post( 'nwcs_override_slug_reset', { blog: block.getAttribute( 'data-blog' ) } )
				.then( function ( data ) {
					flash( block, data.message );
					setTag( block, data.count );
					showAddress( block, data.address );
					block.querySelector( '[data-ovr-clear]' ).disabled = ! data.count;
				} )
				.catch( function ( error ) {
					reset.disabled = false;
					slugError( block, error );
				} );
		}

		if ( event.target.closest( '[data-ovr-clear]' ) ) {
			if ( ! window.confirm( 'Bu sitedeki özelleştirmeler (sayfa adresi dahil) silinecek; ürün bu sitede de Ürün Havuzu’ndaki hâliyle görünecek. Eski adreslerden gelen ziyaretçiler yine ürün sayfasına ulaşır. Devam edilsin mi?' ) ) {
				return;
			}

			post( 'nwcs_override_clear', { blog: block.getAttribute( 'data-blog' ) } )
				.then( function ( data ) {
					block.querySelectorAll( '[data-ovr]' ).forEach( function ( field ) {
						if ( 'checkbox' === field.type ) {
							field.checked = false;
						} else if ( 'SELECT' === field.tagName ) {
							field.value = '0';
						} else {
							field.value = '';
						}
					} );

					block.querySelector( '[data-ovr="price"]' ).disabled = true;
					block.querySelector( '[data-ovr-clear]' ).disabled = true;
					setTag( block, 0 );
					showAddress( block, data.address );
					flash( block, data.message );
				} )
				.catch( function ( error ) { slugError( block, error ); } );
		}
	} );
}() );
