/**
 * Medya Havuzu -> Videolar: secilen videolari tek tek yukler.
 *
 * Sunucunun istek siniri (post_max_size) birkac videoyu tek istekte almaz;
 * burada her video ayri istekle (nwcs_media_video_upload) sirayla gider.
 * Buyuk ya da MP4 olmayan dosya hic gonderilmez. Bitince sayfa sonuc
 * bildirimiyle yeniden acilir. JavaScript yoksa form tek istekte gider.
 * Sunucu tarafi: includes/admin/media-video.php
 */
( function () {
	'use strict';

	var form = document.querySelector( '[data-nwcs-video-bulk]' );

	if ( ! form || ! window.fetch || ! window.FormData || 'undefined' === typeof window.ajaxurl ) {
		return;
	}

	var input = form.querySelector( 'input[type="file"]' );
	var progress = form.querySelector( '[data-nwcs-video-progress]' );
	var button = form.querySelector( 'button[type="submit"]' );
	var max = parseInt( form.getAttribute( 'data-max' ), 10 ) || 0;
	var busy = false;

	form.addEventListener( 'submit', function ( event ) {
		event.preventDefault();

		if ( busy || ! input.files.length ) {
			return;
		}

		var files = Array.prototype.slice.call( input.files );
		var counts = { adet: 0, buyuk: 0, tursuz: 0, hatali: 0 };
		var keys = { video_size: 'buyuk', video_type: 'tursuz', video_upload: 'hatali' };
		var index = 0;

		busy = true;
		button.disabled = true;
		progress.hidden = false;

		// Yukleme surerken sayfadan cikilirsa kalanlar yuklenmez; uyar.
		var leave = function ( leaveEvent ) {
			leaveEvent.preventDefault();
			leaveEvent.returnValue = '';
		};
		window.addEventListener( 'beforeunload', leave );

		var finish = function () {
			var url = new window.URL( form.getAttribute( 'data-done' ), window.location.href );

			url.searchParams.set( 'nwcs_media', counts.adet ? 'video_uploaded' : 'video_failed' );
			Object.keys( counts ).forEach( function ( key ) {
				if ( counts[ key ] ) {
					url.searchParams.set( key, String( counts[ key ] ) );
				}
			} );

			window.removeEventListener( 'beforeunload', leave );
			window.location.assign( url.toString() );
		};

		var next = function () {
			if ( index >= files.length ) {
				finish();
				return;
			}

			var file = files[ index++ ];

			progress.textContent = index + ' / ' + files.length + ' yükleniyor: ' + file.name;

			if ( ! /\.mp4$/i.test( file.name ) ) {
				counts.tursuz++;
				next();
				return;
			}

			if ( max && file.size > max ) {
				counts.buyuk++;
				next();
				return;
			}

			var data = new window.FormData();
			data.append( 'action', 'nwcs_media_video_upload' );
			data.append( '_wpnonce', form.querySelector( 'input[name="_wpnonce"]' ).value );
			data.append( 'file', file );

			window.fetch( window.ajaxurl, { method: 'POST', body: data, credentials: 'same-origin' } )
				.then( function ( response ) {
					return response.json();
				} )
				.then( function ( json ) {
					if ( json && json.success ) {
						counts.adet++;
					} else {
						counts[ keys[ json && json.data && json.data.error ] || 'hatali' ]++;
					}
				} )
				.catch( function () {
					counts.hatali++;
				} )
				.then( next );
		};

		next();
	} );
}() );
