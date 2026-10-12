/**
 * Urun formu -> "Ürün videosu".
 *
 * Buyuk dosya tarayicida yakalanir: sunucuya gitmeden uyari cikar (PHP
 * post_max_size asilirsa form bos gider ve "baglanti suresi doldu" sayfasi
 * gelir). "Videoyu üründen kaldır" secimi "Video yok"a ceker. JavaScript
 * yoksa ayni isler secim kutusu ve sunucu denetimiyle yapilir.
 * Sunucu tarafi: includes/admin/pool-video.php
 */
( function () {
	'use strict';

	document.querySelectorAll( '[data-nwcs-video]' ).forEach( function ( box ) {
		var select = box.querySelector( '[data-nwcs-video-select]' );
		var upload = box.querySelector( '[data-nwcs-video-upload]' );
		var current = box.querySelector( '[data-nwcs-video-current]' );
		var remove = box.querySelector( '[data-nwcs-video-remove]' );
		var error = box.querySelector( '[data-nwcs-video-error]' );
		var initial = select ? select.value : '0';

		function showError( text ) {
			error.textContent = text;
			error.hidden = '' === text;
			box.classList.toggle( 'is-invalid', '' !== text );
		}

		// Onizleme yalnizca kayitli video secili kaldikca gorunur.
		function syncPreview() {
			if ( ! current || ! select ) {
				return;
			}

			var keep = select.value === initial && '0' !== initial && ! ( upload && upload.files.length );
			current.hidden = ! keep;

			if ( ! keep ) {
				var player = current.querySelector( 'video' );
				if ( player ) {
					player.pause();
				}
			}
		}

		if ( upload && error ) {
			upload.addEventListener( 'change', function () {
				var file = upload.files[0];
				var max = parseInt( upload.getAttribute( 'data-max' ), 10 ) || 0;

				showError( '' );

				if ( ! file ) {
					syncPreview();
					return;
				}

				if ( ! /\.mp4$/i.test( file.name ) ) {
					upload.value = '';
					showError( 'Video MP4 olmalı. Telefon videosunu MP4 olarak dışa aktarıp yeniden seçin.' );
				} else if ( max && file.size > max ) {
					upload.value = '';
					showError( 'Bu video ' + ( file.size / 1048576 ).toFixed( 1 ).replace( '.', ',' ) + ' MB; en fazla ' + upload.getAttribute( 'data-max-text' ) + ' olabilir. Videoyu sıkıştırıp yeniden seçin.' );
				}

				syncPreview();
			} );
		}

		if ( select ) {
			select.addEventListener( 'change', syncPreview );
		}

		if ( remove && select ) {
			remove.addEventListener( 'click', function () {
				select.value = '0';
				select.dispatchEvent( new Event( 'change', { bubbles: true } ) );
				select.focus();
			} );
		}
	} );
}() );
