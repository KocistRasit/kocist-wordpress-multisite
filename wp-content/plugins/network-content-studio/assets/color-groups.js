/**
 * Renk grupları (Ürün Havuzu -> Kategoriler -> kategori).
 *
 * JavaScript yoksa her dugme formu gonderir (↑ ↓ ve "Gruptan çıkar" formu o
 * haliyle kaydeder). JS ile:
 * - ↑ ↓ satiri yerinde tasir; "Öne çıkan" etiketi ilk satira gecer; kayit
 *   "Grubu kaydet" ile yapilir (degisiklik notu gorunur);
 * - hazir renk secilince yuvarlak boyanir, renk adi bossa hazir rengin adi
 *   yazilir; ozel renk secici yalnizca "Özel renk"te gorunur;
 * - urun secim listesi yazdikca suzulur (kod ve ad; Turkce harf farki yok);
 * - "Grubu oluştur" ikiden az urunle gonderilmez (sunucu da denetler);
 * - grup formu renk adi bos satirla gonderilmez (sunucu da denetler).
 */
( function () {
	'use strict';

	var section = document.querySelector( '[data-nwcs-colors]' );

	if ( ! section ) {
		return;
	}

	section.classList.add( 'is-js' );

	var presets = {};

	try {
		presets = JSON.parse( section.getAttribute( 'data-nwcs-colors' ) || '{}' ) || {};
	} catch ( error ) {
		presets = {};
	}

	function fold( text ) {
		return String( text )
			.replace( /İ/g, 'i' ).replace( /I/g, 'ı' )
			.toLowerCase()
			.replace( /ı/g, 'i' ).replace( /ğ/g, 'g' ).replace( /ü/g, 'u' )
			.replace( /ş/g, 's' ).replace( /ö/g, 'o' ).replace( /ç/g, 'c' )
			.replace( /â/g, 'a' ).replace( /î/g, 'i' ).replace( /û/g, 'u' );
	}

	/* Acik renk (WCAG bagil parlaklik > 0,7): yuvarlagin cevresinde cizgi. */
	function isLight( hex ) {
		var match = /^#([0-9a-f]{6})$/i.exec( hex || '' );

		if ( ! match ) {
			return true;
		}

		var channel = function ( index ) {
			var value = parseInt( match[ 1 ].substr( index, 2 ), 16 ) / 255;

			return value <= 0.03928 ? value / 12.92 : Math.pow( ( value + 0.055 ) / 1.055, 2.4 );
		};

		return 0.2126 * channel( 0 ) + 0.7152 * channel( 2 ) + 0.0722 * channel( 4 ) > 0.7;
	}

	/* ---------------- Uye satiri: renk ---------------- */

	function paint( row ) {
		var select = row.querySelector( '[data-nwcs-color-preset]' );
		var custom = row.querySelector( '[data-nwcs-color-custom]' );
		var dot = row.querySelector( '[data-nwcs-color-dot]' );

		if ( ! select || ! dot ) {
			return;
		}

		var value = select.value;
		var hex = '';

		if ( 'ozel' === value && custom ) {
			hex = custom.value;
		} else if ( presets[ value ] ) {
			hex = presets[ value ];
		}

		if ( custom ) {
			custom.toggleAttribute( 'data-off', 'ozel' !== value );
		}

		dot.style.setProperty( '--nwcs-dot', hex || '#ffffff' );
		dot.classList.toggle( 'is-empty', ! hex );
		dot.classList.toggle( 'is-light', isLight( hex ) );
	}

	section.querySelectorAll( '[data-nwcs-color-row]' ).forEach( function ( row ) {
		var select = row.querySelector( '[data-nwcs-color-preset]' );

		if ( select ) {
			select.setAttribute( 'data-was', select.value );
		}

		paint( row );
	} );

	section.addEventListener( 'change', function ( event ) {
		var target = event.target;
		var row = target.closest( '[data-nwcs-color-row]' );
		var form = target.closest( '[data-nwcs-color-group]' );

		if ( row && target.matches( '[data-nwcs-color-preset]' ) ) {
			var name = row.querySelector( '[data-nwcs-color-name]' );
			var was = target.getAttribute( 'data-was' ) || '';

			// Renk adi bossa ya da onceki hazir rengin adiysa yeni hazir rengin adi yazilir.
			if ( name && presets[ target.value ] && ( '' === name.value.trim() || name.value === was ) ) {
				name.value = target.value;
			}

			target.setAttribute( 'data-was', target.value );
			paint( row );
		}

		if ( form ) {
			dirty( form );
		}

		if ( target.matches( '[data-nwcs-color-item] input[type="checkbox"]' ) ) {
			count( target.closest( '[data-nwcs-color-picker]' ) );
		}
	} );

	section.addEventListener( 'input', function ( event ) {
		var target = event.target;
		var row = target.closest( '[data-nwcs-color-row]' );

		if ( row && target.matches( '[data-nwcs-color-custom]' ) ) {
			var select = row.querySelector( '[data-nwcs-color-preset]' );

			if ( select ) {
				select.value = 'ozel';
				select.setAttribute( 'data-was', 'ozel' );
			}

			paint( row );
		}

		if ( target.matches( '[data-nwcs-color-filter]' ) ) {
			filter( target.closest( '[data-nwcs-color-picker]' ) );
		}

		var form = target.closest( '[data-nwcs-color-group]' );

		if ( form && ! target.matches( '[data-nwcs-color-filter]' ) ) {
			dirty( form );
		}
	} );

	/* ---------------- Sira: ↑ ↓ ---------------- */

	function dirty( form ) {
		var status = form.querySelector( '[data-nwcs-color-status]' );

		if ( status && ! status.textContent ) {
			status.textContent = 'Kaydedilmemiş değişiklik var. Yazmak için “Grubu kaydet”e basın.';
		}
	}

	function refresh( body ) {
		var rows = Array.prototype.slice.call( body.querySelectorAll( '[data-nwcs-color-row]' ) );

		rows.forEach( function ( row, index ) {
			var lead = row.querySelector( '[data-nwcs-color-lead]' );
			var up = row.querySelector( '[data-nwcs-color-move="up"]' );
			var down = row.querySelector( '[data-nwcs-color-move="down"]' );

			if ( lead ) {
				lead.hidden = 0 !== index;
			}

			if ( up ) {
				up.disabled = 0 === index;
			}

			if ( down ) {
				down.disabled = rows.length - 1 === index;
			}
		} );
	}

	section.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( '[data-nwcs-color-move]' );

		if ( ! button ) {
			return;
		}

		event.preventDefault();

		var row = button.closest( '[data-nwcs-color-row]' );
		var body = row.parentNode;
		var form = button.closest( '[data-nwcs-color-group]' );
		var up = 'up' === button.getAttribute( 'data-nwcs-color-move' );
		var other = up ? row.previousElementSibling : row.nextElementSibling;

		if ( ! other ) {
			return;
		}

		body.insertBefore( row, up ? other : other.nextElementSibling );
		refresh( body );

		// Odak ayni dugmede kalsin; ucta devre disi kaldiysa karsi yondeki dugmeye.
		var focus = button.disabled ? row.querySelector( '[data-nwcs-color-move="' + ( up ? 'down' : 'up' ) + '"]' ) : button;

		if ( focus ) {
			focus.focus();
		}

		row.classList.add( 'is-moved' );
		window.setTimeout( function () {
			row.classList.remove( 'is-moved' );
		}, 700 );

		var rows = Array.prototype.slice.call( body.querySelectorAll( '[data-nwcs-color-row]' ) );
		var name = button.getAttribute( 'aria-label' ).replace( /^[^:]*:\s*/, '' );
		var status = form && form.querySelector( '[data-nwcs-color-status]' );

		if ( status ) {
			status.textContent = name + ' ' + ( rows.indexOf( row ) + 1 ) + '. sırada' + ( 0 === rows.indexOf( row ) ? ' (öne çıkan)' : '' ) + '. Yazmak için “Grubu kaydet”e basın.';
		}
	} );

	/* ---------------- Urun secim listesi ---------------- */

	function count( picker ) {
		if ( ! picker ) {
			return;
		}

		var label = picker.querySelector( '[data-nwcs-color-count]' );
		var items = picker.querySelectorAll( '[data-nwcs-color-item]' );
		var free = 0;
		var shown = 0;
		var picked = 0;

		items.forEach( function ( item ) {
			var box = item.querySelector( 'input[type="checkbox"]' );

			free += box && ! box.disabled ? 1 : 0;
			shown += item.hidden ? 0 : 1;
			picked += box && box.checked ? 1 : 0;
		} );

		if ( label ) {
			label.textContent = picked + ' seçili · ' + free + ' ürün seçilebilir' + ( shown < items.length ? ' · ' + shown + ' gösteriliyor' : '' ) + '.';
		}

		var none = picker.querySelector( '[data-nwcs-color-none]' );

		if ( none ) {
			none.hidden = 0 !== shown;
		}
	}

	function filter( picker ) {
		if ( ! picker ) {
			return;
		}

		var input = picker.querySelector( '[data-nwcs-color-filter]' );
		var words = fold( input ? input.value : '' ).split( /\s+/ ).filter( Boolean );

		picker.querySelectorAll( '[data-nwcs-color-item]' ).forEach( function ( item ) {
			var haystack = item.getAttribute( 'data-nwcs-color-item' ) || '';
			var box = item.querySelector( 'input[type="checkbox"]' );

			// Isaretli urun aramada kaybolmasin.
			item.hidden = ! ( box && box.checked ) && ! words.every( function ( word ) {
				return -1 !== haystack.indexOf( word );
			} );
		} );

		count( picker );
	}

	section.querySelectorAll( '[data-nwcs-color-picker]' ).forEach( function ( picker ) {
		var search = picker.querySelector( '[data-nwcs-color-search]' );

		// Az urunde arama kutusu gereksiz.
		if ( search && picker.querySelectorAll( '[data-nwcs-color-item]' ).length > 6 ) {
			search.hidden = false;
		}

		count( picker );
	} );

	/*
	 * Renk adi zorunlu: bos ad varken form gonderilmez; sunucunun mesajiyla ayni
	 * metin ("W-ADR-TEK için renk adı yazın."), ilk bos alana odak. Gruptan
	 * cikarilan satir sayilmaz. Sunucu da ayni kurali uygular.
	 */
	function joinNames( names ) {
		return names.length > 1 ? names.slice( 0, -1 ).join( ', ' ) + ' ve ' + names[ names.length - 1 ] : names[ 0 ];
	}

	section.querySelectorAll( '[data-nwcs-color-group]' ).forEach( function ( form ) {
		form.addEventListener( 'submit', function ( event ) {
			var submitter = event.submitter || null;
			var out = submitter && 'cikar' === submitter.name ? submitter.closest( '[data-nwcs-color-row]' ) : null;
			var error = form.querySelector( '[data-nwcs-color-missing]' );
			var empty = [];

			form.querySelectorAll( '[data-nwcs-color-row]' ).forEach( function ( row ) {
				var input = row.querySelector( '[data-nwcs-color-name]' );

				if ( ! input || row === out ) {
					return;
				}

				var blank = '' === input.value.trim();

				input.setAttribute( 'aria-invalid', blank ? 'true' : 'false' );

				if ( blank ) {
					empty.push( { input: input, code: row.getAttribute( 'data-nwcs-color-code' ) || '' } );
				}
			} );

			if ( ! empty.length ) {
				if ( error ) {
					error.hidden = true;
				}

				return;
			}

			event.preventDefault();

			if ( error ) {
				error.textContent = 'Kaydedilmedi: ' + joinNames( empty.map( function ( item ) {
					return item.code;
				} ) ) + ' için renk adı yazın.';
				error.hidden = false;
			}

			empty[ 0 ].input.focus();
		} );
	} );

	section.querySelectorAll( '[data-nwcs-color-create]' ).forEach( function ( form ) {
		form.addEventListener( 'submit', function ( event ) {
			var picked = form.querySelectorAll( '[data-nwcs-color-item] input[type="checkbox"]:checked' ).length;
			var error = form.querySelector( '[data-nwcs-color-error]' );

			if ( picked < 2 ) {
				event.preventDefault();

				if ( error ) {
					error.hidden = false;
				}

				var first = form.querySelector( '[data-nwcs-color-item] input[type="checkbox"]:not(:disabled)' );

				if ( first ) {
					first.focus();
				}
			} else if ( error ) {
				error.hidden = true;
			}
		} );
	} );
}() );
