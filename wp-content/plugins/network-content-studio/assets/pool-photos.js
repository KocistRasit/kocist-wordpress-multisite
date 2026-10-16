/**
 * Fotograf Kutusu (Ürün Havuzu -> Kategoriler).
 *
 * - Birakma alani: dosyalar tek kuyrukta, sirayla yuklenir (admin-ajax,
 *   nwcs_photo_upload); ilerleme ve dosya basina sorun satiri gosterilir.
 * - Kuyruk bitince onizleme formu gonderilir (tarayicinin reddettigi dosyalar
 *   da listede yer alsin diye red[] alanlariyla).
 * - Onizlemede "Çıkar" isaretlenince sayac guncellenir.
 *
 * JavaScript yoksa ayni kart duz bir coklu dosya formudur; onizlemedeki
 * "Çıkar" kutulari da formla gider.
 */
( function () {
	'use strict';

	var cfg = window.nwcsPanel || {};
	var allowed = /\.(jpe?g|jpe|png|webp|heic|heif)$/i;

	/* ---------------- onizleme: Cikar ---------------- */
	var preview = document.querySelector( '[data-nwcs-photos-preview]' );

	if ( preview ) {
		var counter = preview.querySelector( '[data-nwcs-photos-count]' );
		var base = counter ? parseInt( counter.textContent, 10 ) || 0 : 0;

		preview.addEventListener( 'change', function ( event ) {
			if ( ! event.target.matches( '[data-nwcs-photo-out]' ) ) {
				return;
			}

			event.target.closest( '.nwcs-photos__shot' ).classList.toggle( 'is-out', event.target.checked );

			if ( counter ) {
				counter.textContent = String( base - preview.querySelectorAll( '[data-nwcs-photo-out]:checked' ).length );
			}
		} );
	}

	/* ---------------- birakma alani ---------------- */
	var form = document.querySelector( '[data-nwcs-drop]' );

	if ( ! form || ! window.fetch || ! window.FormData ) {
		return;
	}

	var zone = form.querySelector( '[data-nwcs-drop-zone]' );
	var input = form.querySelector( '.nwcs-drop__input' );
	var pick = form.querySelector( '[data-nwcs-drop-pick]' );
	var box = form.querySelector( '[data-nwcs-drop-progress]' );
	var meter = form.querySelector( '[data-nwcs-drop-meter]' );
	var count = form.querySelector( '[data-nwcs-drop-count]' );
	var list = form.querySelector( '[data-nwcs-drop-errors]' );
	var next = document.querySelector( '[data-nwcs-drop-preview]' );
	var busy = false;

	form.classList.add( 'is-js' );
	form.querySelectorAll( '[data-nwcs-drop-js]' ).forEach( function ( node ) {
		node.hidden = false;
	} );
	pick.hidden = false;
	form.querySelector( '[data-nwcs-drop-nojs]' ).hidden = true;

	pick.addEventListener( 'click', function () {
		input.click();
	} );

	input.addEventListener( 'change', function () {
		start( input.files );
	} );

	[ 'dragenter', 'dragover' ].forEach( function ( type ) {
		zone.addEventListener( type, function ( event ) {
			event.preventDefault();
			if ( ! busy ) {
				zone.classList.add( 'is-over' );
			}
		} );
	} );

	[ 'dragleave', 'drop' ].forEach( function ( type ) {
		zone.addEventListener( type, function ( event ) {
			event.preventDefault();
			zone.classList.remove( 'is-over' );
		} );
	} );

	zone.addEventListener( 'drop', function ( event ) {
		if ( event.dataTransfer && event.dataTransfer.files ) {
			start( event.dataTransfer.files );
		}
	} );

	// Form JS'li durumda kendiliginden gonderilmez (Enter vb.).
	form.addEventListener( 'submit', function ( event ) {
		event.preventDefault();
	} );

	window.addEventListener( 'beforeunload', function ( event ) {
		if ( busy ) {
			event.preventDefault();
			event.returnValue = '';
		}
	} );

	function line( name, reason ) {
		var item = document.createElement( 'li' );
		var strong = document.createElement( 'strong' );
		strong.textContent = name;
		item.appendChild( strong );
		item.appendChild( document.createTextNode( ': ' + reason ) );
		list.appendChild( item );
	}

	function start( fileList ) {
		var files = Array.prototype.slice.call( fileList || [] );

		if ( busy || ! files.length ) {
			return;
		}

		var queue = [];
		var rejected = [];
		var total = files.length;
		var done = 0;
		var batch = '';

		busy = true;
		form.classList.add( 'is-busy' );
		pick.disabled = true;
		box.hidden = false;
		list.innerHTML = '';
		meter.max = total;

		files.forEach( function ( file ) {
			if ( ! allowed.test( file.name ) ) {
				rejected.push( [ file.name, 'görsel türü uygun değil (JPG, PNG, WebP ya da HEIC olmalı)' ] );
			} else if ( cfg.uploadMax && file.size > cfg.uploadMax ) {
				rejected.push( [ file.name, 'çok büyük (sınır ' + cfg.uploadMaxText + ')' ] );
			} else {
				queue.push( file );
			}
		} );

		rejected.forEach( function ( row ) {
			line( row[0], row[1] );
			done++;
		} );

		function show() {
			meter.value = done;
			count.textContent = 'Yükleniyor ' + done + ' / ' + total;
		}

		function stop( message ) {
			busy = false;
			form.classList.remove( 'is-busy' );
			pick.disabled = false;
			input.value = '';
			count.textContent = message;
		}

		function finish() {
			if ( ! batch ) {
				stop( 'Hiçbir dosya yüklenmedi; nedenleri aşağıda yazıyor.' );
				return;
			}

			count.textContent = 'Yüklendi ' + done + ' / ' + total + '. Önizleme açılıyor…';
			next.querySelector( '[name="parti"]' ).value = batch;

			rejected.forEach( function ( row ) {
				var field = document.createElement( 'input' );
				field.type = 'hidden';
				field.name = 'red[]';
				field.value = JSON.stringify( row );
				next.appendChild( field );
			} );

			busy = false;
			next.submit();
		}

		function send( index ) {
			show();

			if ( index >= queue.length ) {
				finish();
				return;
			}

			var file = queue[ index ];
			var data = new FormData();
			data.append( 'action', 'nwcs_photo_upload' );
			data.append( '_ajax_nonce', cfg.nonce || '' );
			data.append( 'kategori', form.getAttribute( 'data-kategori' ) );
			data.append( 'parti', batch );
			data.append( 'dosya', file, file.name );

			fetch( cfg.ajaxUrl, { method: 'POST', body: data, credentials: 'same-origin' } )
				.then( function ( response ) {
					return response.json().catch( function () {
						return { success: false, file: true, data: { message: 'site yanıt vermedi; yeniden deneyin (Teknik: HTTP ' + response.status + ')' } };
					} );
				} )
				.then( function ( json ) {
					done++;

					if ( json && json.success ) {
						batch = json.data.parti;
						if ( json.data.reason ) {
							line( json.data.name, json.data.reason );
						}
						send( index + 1 );
						return;
					}

					var message = ( json && json.data && json.data.message ) || ( -1 === json || 0 === json ? 'oturumun süresi dolmuş olabilir; sayfayı yenileyip yeniden deneyin' : 'yüklenemedi; yeniden deneyin' );

					// Dosyaya ozgu sunucu hatasi (ornegin boyut): kuyruk surer.
					if ( json && json.file ) {
						line( file.name, message );
						rejected.push( [ file.name, message ] );
						send( index + 1 );
						return;
					}

					// Yetki, oturum ya da baska sekmede yeni yukleme: durulur.
					line( file.name, message );
					stop( 'Yükleme durdu; nedeni aşağıda yazıyor.' );
				} )
				.catch( function () {
					done++;
					line( file.name, 'bağlantı koptu; yeniden deneyin' );
					rejected.push( [ file.name, 'bağlantı koptu; yeniden deneyin' ] );
					send( index + 1 );
				} );
		}

		send( 0 );
	}
}() );
