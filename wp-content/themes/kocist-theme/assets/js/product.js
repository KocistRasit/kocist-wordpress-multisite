/**
 * Urun sayfasi: galeri degistirici, urun akordeonu (Teknik Detaylar, Urun
 * Aciklamasi, Lojistik ve Teslimat), telefondaki "forma git" baglantisi ve
 * sikca sorulan sorular akordiyonu.
 */
( function () {
	'use strict';

	/* ---------- galeri ---------- */

	var gallery = document.querySelector( '[data-k-gallery]' );

	/*
	 * Fareyle buyuk gorselin uzerine gelince imlecin oldugu yer buyur
	 * (yalnizca fare; dokunmatikte tiklama dogrudan buyutme penceresini acar).
	 * Yer tutucuda (gorsel yok) calismaz.
	 */
	var lensStage = gallery ? gallery.querySelector( '.k-product__stage' ) : null;

	if ( lensStage && lensStage.querySelector( 'img.k-product__photo' ) ) {
		var lens = function ( event ) {
			// Video oynarken buyutme yok.
			if ( 'mouse' !== event.pointerType || lensStage.classList.contains( 'is-video' ) ) {
				lensStage.classList.remove( 'is-lens' );
				return;
			}

			// Oklar, buyutec rozeti ve sayac uzerinde buyutme yok: dugme gorunur kalsin.
			if ( event.target.closest && event.target.closest( '.k-product__arrow, .k-zoom__badge, .k-product__counter' ) ) {
				lensStage.classList.remove( 'is-lens' );
				return;
			}

			// Konum gorsele gore: sahnede gorselin etrafinda bosluk olabilir.
			var box = lensStage.querySelector( '.k-product__photo' ).getBoundingClientRect();
			var at  = function ( value ) {
				return Math.min( 100, Math.max( 0, value * 100 ) ) + '%';
			};

			lensStage.style.setProperty( '--zx', at( ( event.clientX - box.left ) / box.width ) );
			lensStage.style.setProperty( '--zy', at( ( event.clientY - box.top ) / box.height ) );
			lensStage.classList.add( 'is-lens' );
		};

		lensStage.addEventListener( 'pointerenter', lens );
		lensStage.addEventListener( 'pointermove', lens );
		lensStage.addEventListener( 'pointerleave', function () {
			lensStage.classList.remove( 'is-lens' );
		} );
	}

	if ( gallery ) {
		var stage   = gallery.querySelector( '.k-product__stage' );
		var photo   = gallery.querySelector( '.k-product__photo' );
		var thumbs  = Array.prototype.slice.call( gallery.querySelectorAll( '[data-k-thumb]' ) );
		var prev    = gallery.querySelector( '[data-k-gallery-prev]' );
		var next    = gallery.querySelector( '[data-k-gallery-next]' );
		var counter = gallery.querySelector( '[data-k-gallery-counter]' );

		/*
		 * Urun videosu: <template> icinde gelir; ilk secilisinde sahneye
		 * eklenir (o ana kadar hicbir sey indirilmez). Baska kareye gecince durur.
		 */
		var videoTpl = gallery.querySelector( '[data-k-video-tpl]' );
		var video    = null;

		var startVideo = function () {
			if ( ! stage || ! videoTpl || ! videoTpl.content.firstElementChild ) {
				return;
			}

			if ( ! video ) {
				video = videoTpl.content.firstElementChild.cloneNode( true );
				stage.appendChild( video );
			}

			stage.classList.add( 'is-video' );
			stage.classList.remove( 'is-lens' );

			var playing = video.play();

			// Tarayici sesli oynatmayi reddederse denetimler gorunur kalir; ziyaretci baslatir.
			if ( playing && playing.catch ) {
				playing.catch( function () {} );
			}
		};

		var stopVideo = function () {
			if ( video ) {
				video.pause();
			}

			if ( stage ) {
				stage.classList.remove( 'is-video' );
			}
		};

		// Fotografsiz urun: sahne bir oynat dugmesi.
		var videoStart = gallery.querySelector( '[data-k-video-start]' );

		if ( videoStart ) {
			videoStart.addEventListener( 'click', function () {
				startVideo();
				videoStart.hidden = true;

				if ( video ) {
					video.focus();
				}
			} );
		}

		// Yer tutucu basiliysa degistirilecek bir <img> yoktur.
		if ( stage && photo && thumbs.length ) {
			var current = 0;

			/**
			 * @param {number} index Gosterilecek gorselin sirasi.
			 */
			function show( index ) {
				// Bas ve son arasinda dolanir: oklar hicbir zaman cikmaza girmez.
				var target  = ( index + thumbs.length ) % thumbs.length;
				var thumb   = thumbs[ target ];
				var full    = thumb.getAttribute( 'data-full' );
				var isVideo = thumb.hasAttribute( 'data-k-video-thumb' );

				if ( ! full && ! isVideo ) {
					return;
				}

				thumbs.forEach( function ( other, otherIndex ) {
					other.classList.toggle( 'is-active', otherIndex === target );
				} );

				if ( counter ) {
					counter.textContent = ( target + 1 ) + ' / ' + thumbs.length;
				}

				current = target;

				if ( isVideo ) {
					startVideo();
					return;
				}

				stopVideo();

				if ( full === photo.getAttribute( 'src' ) ) {
					return;
				}

				// Once soluklastir, gorsel yuklenince geri getir: yarim
				// yuklenmis gorsel ekranda gorunmesin.
				stage.classList.add( 'is-swapping' );

				var loader = new window.Image();

				loader.onload = function () {
					// Ilk gorselin srcset'i kalirsa tarayici onu gostermeye devam eder.
					photo.removeAttribute( 'srcset' );
					photo.removeAttribute( 'sizes' );
					photo.src = full;
					photo.alt = thumb.getAttribute( 'data-alt' ) || '';
					stage.classList.remove( 'is-swapping' );
				};

				loader.onerror = function () {
					stage.classList.remove( 'is-swapping' );
				};

				loader.src = full;
			}

			thumbs.forEach( function ( thumb, index ) {
				thumb.addEventListener( 'click', function () {
					show( index );
				} );
			} );

			if ( prev ) {
				prev.addEventListener( 'click', function () {
					show( current - 1 );
				} );
			}

			if ( next ) {
				next.addEventListener( 'click', function () {
					show( current + 1 );
				} );
			}

			// Klavye: galeri odaktayken sag/sol oklar da calisir.
			gallery.addEventListener( 'keydown', function ( event ) {
				// Video odaktayken oklar videoyu ileri/geri sarar.
				if ( 'VIDEO' === event.target.tagName ) {
					return;
				}

				if ( 'ArrowLeft' === event.key ) {
					event.preventDefault();
					show( current - 1 );
				} else if ( 'ArrowRight' === event.key ) {
					event.preventDefault();
					show( current + 1 );
				}
			} );

			/* ---------- dokunmatik kaydirma ---------- */

			var startX = null;

			stage.addEventListener( 'touchstart', function ( event ) {
				// Videonun ilerleme cubugunu surukleme kare degistirmesin.
				startX = 'VIDEO' === event.target.tagName ? null : event.changedTouches[ 0 ].clientX;
			}, { passive: true } );

			stage.addEventListener( 'touchend', function ( event ) {
				if ( null === startX ) {
					return;
				}

				var delta = event.changedTouches[ 0 ].clientX - startX;

				// Kazara dokunuslari gorsel degisimi saymamak icin esik.
				if ( Math.abs( delta ) > 44 ) {
					show( current + ( delta < 0 ? 1 : -1 ) );
				}

				startX = null;
			}, { passive: true } );
		}
	}

	/*
	 * ---------- urun akordeonu ----------
	 * Ayni anda en fazla bir panel acik; acik basliga basinca o da kapanir.
	 * Baslangic durumu sunucudan (aria-expanded): on yuzde tek panel, panel
	 * onizlemesinde hepsi acik. Betik yoksa hidden hic konmaz: paneller acik.
	 */
	document.querySelectorAll( '[data-k-acc]' ).forEach( function ( group ) {
		var buttons = Array.prototype.slice.call( group.querySelectorAll( '[data-k-acc-btn]' ) );

		var set = function ( button, open ) {
			var panel = document.getElementById( button.getAttribute( 'aria-controls' ) );

			button.setAttribute( 'aria-expanded', open ? 'true' : 'false' );

			if ( panel ) {
				panel.hidden = ! open;
				panel.classList.toggle( 'is-open', open );
			}
		};

		buttons.forEach( function ( button ) {
			set( button, 'true' === button.getAttribute( 'aria-expanded' ) );

			button.addEventListener( 'click', function () {
				var open = 'true' !== button.getAttribute( 'aria-expanded' );

				buttons.forEach( function ( other ) {
					set( other, other === button && open );
				} );
			} );
		} );
	} );

	/*
	 * ---------- telefonda "Teklif formuna git" ----------
	 * Forma kaydirir ve ilk alani odaklar; betik yoksa capa ayni yere gider.
	 */
	document.querySelectorAll( '[data-k-form-jump]' ).forEach( function ( link ) {
		link.addEventListener( 'click', function ( event ) {
			var target = document.getElementById( ( link.getAttribute( 'href' ) || '' ).replace( '#', '' ) );
			var field  = target ? target.querySelector( 'input:not([type="hidden"]):not([tabindex="-1"]), textarea' ) : null;

			if ( ! target ) {
				return;
			}

			event.preventDefault();

			var still = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

			target.scrollIntoView( { behavior: still ? 'auto' : 'smooth', block: 'start' } );

			if ( field ) {
				field.focus( { preventScroll: true } );
			}

			if ( window.history && window.history.replaceState ) {
				window.history.replaceState( null, '', '#' + target.id );
			}
		} );
	} );

	/*
	 * ---------- form donusu ----------
	 * Gonderimden sonra sayfa ?kc=...#teklif-formu ile acilir: ilk hatali
	 * alan, yoksa sonuc bandi odaklanir (ekran okuyucu sonucu hemen okur).
	 */
	if ( /[?&]kc=[a-f0-9]{20}(?:&|$)/.test( window.location.search ) ) {
		var form   = document.getElementById( 'teklif-formu' );
		var target = form ? ( form.querySelector( '[aria-invalid="true"]' ) || form.querySelector( '[data-k-form-notice]' ) ) : null;

		if ( target ) {
			// Sayfa yuklenip #teklif-formu capasina gidildikten sonra: capa odagi ezmesin.
			var focusTarget = function () {
				target.focus( { preventScroll: true } );
				form.scrollIntoView( { block: 'start' } );
			};

			if ( 'complete' === document.readyState ) {
				window.setTimeout( focusTarget, 0 );
			} else {
				window.addEventListener( 'load', function () {
					window.setTimeout( focusTarget, 0 );
				} );
			}
		}
	}
}() );
