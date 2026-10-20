/**
 * Renk listesi (Ürün Havuzu -> Renkler).
 *
 * JavaScript yoksa ↑ ↓, "Sil" ve "+ Renk ekle" formu o haliyle kaydeder;
 * formun sonunda bos bir "Yeni renk" satiri durur. JS ile:
 * - ↑ ↓ ve "Sil" satiri yerinde tasir ya da kaldirir (kayitli rengin silinmesi
 *   formda silinen[] olarak gider); kayit "Kaydet" ile yapilir;
 * - "+ Renk ekle" yeni satir ekler (en fazla data-nwcs-palette-max);
 * - ton secilince yuvarlak ve renk kodu canli degisir;
 * - adi bos satir, ayni ad (Turkce harf ve buyuk/kucuk harf farki yok) ve
 *   fazla renk varken form gonderilmez (sunucu da denetler).
 */
( function () {
	'use strict';

	var form = document.querySelector( '[data-nwcs-palette]' );

	if ( ! form ) {
		return;
	}

	var list = form.querySelector( '[data-nwcs-palette-rows]' );
	var add = form.querySelector( '[data-nwcs-palette-add]' );
	var max = parseInt( form.getAttribute( 'data-nwcs-palette-max' ), 10 ) || 60;
	var counter = 0;

	if ( ! list ) {
		return;
	}

	form.classList.add( 'is-js' );

	function fold( text ) {
		return String( text )
			.replace( /İ/g, 'i' ).replace( /I/g, 'ı' )
			.toLowerCase()
			.replace( /ı/g, 'i' ).replace( /ğ/g, 'g' ).replace( /ü/g, 'u' )
			.replace( /ş/g, 's' ).replace( /ö/g, 'o' ).replace( /ç/g, 'c' )
			.replace( /â/g, 'a' ).replace( /î/g, 'i' ).replace( /û/g, 'u' )
			.trim();
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

	function rows() {
		return Array.prototype.slice.call( list.querySelectorAll( '[data-nwcs-palette-row]' ) );
	}

	function nameOf( row, index ) {
		var input = row.querySelector( '[data-nwcs-palette-name]' );
		var value = input ? input.value.trim() : '';

		return value ? '“' + value + '”' : ( index + 1 ) + '. renk';
	}

	function say( text ) {
		var status = form.querySelector( '[data-nwcs-palette-status]' );

		if ( status ) {
			status.textContent = text;
		}
	}

	function dirty() {
		var status = form.querySelector( '[data-nwcs-palette-status]' );

		if ( status && ! status.textContent ) {
			status.textContent = 'Kaydedilmemiş değişiklik var. Yazmak için “Kaydet”e basın.';
		}
	}

	function paint( row ) {
		var input = row.querySelector( '[data-nwcs-palette-hex]' );
		var dot = row.querySelector( '[data-nwcs-palette-dot]' );
		var code = row.querySelector( '[data-nwcs-palette-code]' );
		var hex = input ? String( input.value ).toLowerCase() : '';

		if ( dot ) {
			dot.style.setProperty( '--nwcs-dot', hex || '#ffffff' );
			dot.classList.toggle( 'is-empty', ! hex );
			dot.classList.toggle( 'is-light', isLight( hex ) );
		}

		if ( code ) {
			code.textContent = hex;
		}
	}

	/* Numaralar, ↑ ↓ durumlari ve adlari, sayac, "+ Renk ekle". */
	function refresh() {
		var all = rows();

		all.forEach( function ( row, index ) {
			var number = row.querySelector( '[data-nwcs-palette-n]' );
			var up = row.querySelector( '[data-nwcs-palette-move="up"]' );
			var down = row.querySelector( '[data-nwcs-palette-move="down"]' );
			var sr = row.querySelector( '[data-nwcs-palette-sr]' );
			var name = nameOf( row, index ).replace( /[“”]/g, '' );

			if ( number ) {
				number.textContent = String( index + 1 );
			}

			if ( up ) {
				up.disabled = 0 === index;
				up.setAttribute( 'aria-label', 'Yukarı taşı: ' + name );
			}

			if ( down ) {
				down.disabled = all.length - 1 === index;
				down.setAttribute( 'aria-label', 'Aşağı taşı: ' + name );
			}

			if ( sr ) {
				sr.textContent = ': ' + name;
			}
		} );

		var count = form.querySelector( '[data-nwcs-palette-count]' );

		if ( count ) {
			count.textContent = all.length + ' / ' + max + ' renk';
		}

		if ( add ) {
			add.setAttribute( 'aria-disabled', all.length >= max ? 'true' : 'false' );
			add.title = all.length >= max ? 'Listede en fazla ' + max + ' renk olabilir.' : '';
		}
	}

	/* Sablondan yeni satir: anahtar "j1", "j2"... (sunucu kimligi kayitta verir). */
	function newRow() {
		var template = form.querySelector( '[data-nwcs-palette-template]' );

		if ( ! template ) {
			return null;
		}

		counter += 1;

		var holder = document.createElement( 'template' );
		holder.innerHTML = template.innerHTML.replace( /__KEY__/g, 'j' + counter ).trim();

		return holder.content.firstElementChild;
	}

	// Formun sonundaki bos "Yeni renk" satiri JS'de gereksiz: "+ Renk ekle" satir ekler.
	list.querySelectorAll( '[data-nwcs-palette-new]' ).forEach( function ( blank ) {
		var input = blank.querySelector( '[data-nwcs-palette-name]' );

		if ( input && '' === input.value.trim() ) {
			blank.parentNode.removeChild( blank );
		}
	} );

	rows().forEach( paint );

	if ( add ) {
		add.addEventListener( 'click', function ( event ) {
			event.preventDefault();

			var error = form.querySelector( '[data-nwcs-palette-error]' );

			if ( rows().length >= max ) {
				if ( error ) {
					error.textContent = 'Listede en fazla ' + max + ' renk olabilir. Yeni renk için kullanmadığınız bir rengi silin.';
					error.hidden = false;
				}

				return;
			}

			var row = newRow();

			if ( ! row ) {
				return;
			}

			list.appendChild( row );
			paint( row );
			refresh();
			say( 'Yeni renk satırı eklendi. Adını yazıp tonunu seçin, sonra “Kaydet”e basın.' );

			var name = row.querySelector( '[data-nwcs-palette-name]' );

			if ( name ) {
				name.focus();
			}
		} );
	}

	/* Duzeltilen alanin isareti ve eski hata metni kalkar (kayitta yeniden denetlenir). */
	function clearError( input ) {
		var error = form.querySelector( '[data-nwcs-palette-error]' );

		if ( input && 'true' === input.getAttribute( 'aria-invalid' ) ) {
			input.setAttribute( 'aria-invalid', 'false' );
		}

		if ( error && ! error.hidden && ! form.querySelector( '[data-nwcs-palette-name][aria-invalid="true"]' ) ) {
			error.hidden = true;
		}
	}

	form.addEventListener( 'input', function ( event ) {
		var row = event.target.closest( '[data-nwcs-palette-row]' );

		if ( row && event.target.matches( '[data-nwcs-palette-hex]' ) ) {
			paint( row );
		}

		if ( event.target.matches( '[data-nwcs-palette-name]' ) ) {
			clearError( event.target );
		}

		dirty();
		refresh();
	} );

	form.addEventListener( 'click', function ( event ) {
		var move = event.target.closest( '[data-nwcs-palette-move]' );
		var remove = event.target.closest( '[data-nwcs-palette-remove]' );
		var row = event.target.closest( '[data-nwcs-palette-row]' );

		if ( ! row || ( ! move && ! remove ) ) {
			return;
		}

		event.preventDefault();

		var all = rows();
		var at = all.indexOf( row );

		if ( remove ) {
			var text = nameOf( row, at );
			var id = row.getAttribute( 'data-nwcs-palette-id' );

			// Kayitli renk: sunucu formda olmayan rengi silinmis saymaz (baska sekmede eklenmis olabilir).
			if ( id ) {
				var gone = document.createElement( 'input' );
				gone.type = 'hidden';
				gone.name = 'silinen[]';
				gone.value = id;
				form.appendChild( gone );
			}

			row.parentNode.removeChild( row );
			clearError( null );
			refresh();
			say( text + ' listeden kaldırıldı. Yazmak için “Kaydet”e basın; bu rengi kullanan ürünler rengini korur.' );

			var left = rows();
			var target = left[ Math.min( at, left.length - 1 ) ];
			var field = target ? target.querySelector( '[data-nwcs-palette-name]' ) : add;

			if ( field ) {
				field.focus();
			}

			return;
		}

		var up = 'up' === move.getAttribute( 'data-nwcs-palette-move' );
		var other = up ? row.previousElementSibling : row.nextElementSibling;

		if ( ! other ) {
			return;
		}

		list.insertBefore( row, up ? other : other.nextElementSibling );
		refresh();

		// Odak ayni dugmede kalsin; ucta devre disi kaldiysa karsi yondeki dugmeye.
		var focus = move.disabled ? row.querySelector( '[data-nwcs-palette-move="' + ( up ? 'down' : 'up' ) + '"]' ) : move;

		if ( focus ) {
			focus.focus();
		}

		row.classList.add( 'is-moved' );
		window.setTimeout( function () {
			row.classList.remove( 'is-moved' );
		}, 700 );

		say( nameOf( row, rows().indexOf( row ) ) + ' ' + ( rows().indexOf( row ) + 1 ) + '. sırada. Yazmak için “Kaydet”e basın.' );
	} );

	/*
	 * Adi bos satir, ayni ad ve fazla renk: form gonderilmez, ilk hataya odak.
	 * Sunucu da ayni kurallari uygular (hata yazilanlari korur).
	 */
	function joinList( items ) {
		return items.length > 1 ? items.slice( 0, -1 ).join( ', ' ) + ' ve ' + items[ items.length - 1 ] : items[ 0 ];
	}

	form.addEventListener( 'submit', function ( event ) {
		var error = form.querySelector( '[data-nwcs-palette-error]' );
		var all = rows();
		var empty = [];
		var seen = {};
		var twins = [];
		var first = null;

		all.forEach( function ( row, index ) {
			var input = row.querySelector( '[data-nwcs-palette-name]' );
			var key = input ? fold( input.value ) : '';
			var bad = ! key;

			if ( ! input ) {
				return;
			}

			if ( bad ) {
				empty.push( ( index + 1 ) + '.' );
			} else if ( Object.prototype.hasOwnProperty.call( seen, key ) ) {
				twins.push( '“' + input.value.trim() + '” (' + ( seen[ key ] + 1 ) + '. ve ' + ( index + 1 ) + '. satır)' );
				bad = true;
			} else {
				seen[ key ] = index;
			}

			input.setAttribute( 'aria-invalid', bad ? 'true' : 'false' );

			if ( bad && ! first ) {
				first = input;
			}
		} );

		var lines = [];

		if ( empty.length ) {
			lines.push( joinList( empty ) + ' satırda renk adı boş. Adı yazın ya da satırı silin.' );
		}

		if ( twins.length ) {
			lines.push( 'Aynı ad iki kez var: ' + joinList( twins ) + '. Büyük/küçük harf ve Türkçe harf farkı sayılmaz; adlardan birini değiştirin.' );
		}

		if ( all.length > max ) {
			lines.push( 'Listede en fazla ' + max + ' renk olabilir; şu an ' + all.length + ' renk var. Kullanmadığınız renkleri silin.' );
		}

		if ( ! lines.length ) {
			if ( error ) {
				error.hidden = true;
			}

			return;
		}

		event.preventDefault();

		if ( error ) {
			error.textContent = 'Kaydedilmedi: ' + lines.join( ' ' );
			error.hidden = false;
		}

		if ( first ) {
			first.focus();
		}
	} );

	refresh();
}() );
