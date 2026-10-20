/**
 * Sık sorulan sorular (Ürün Havuzu -> Kategoriler -> kategori).
 *
 * JavaScript yoksa sekmeler baglantidir (?sss_site=), ↑ ↓ ve "Sil" formu o
 * haliyle kaydeder, formun sonunda bos bir "Yeni soru" satiri durur. JS ile:
 * - sekmeler sayfa yenilenmeden degisir (ok tuslari, Home, End); adres
 *   cubugundaki ?sss_site= de guncellenir, yenileyince ayni sekme acilir;
 * - ↑ ↓ ve "Sil" satiri yerinde tasir ya da kaldirir; kayit "Soruları
 *   kaydet" ile yapilir (degisiklik notu gorunur);
 * - "Soru ekle" yeni satir ekler (site basina en fazla data-nwcs-faq-max);
 * - cevabi olup sorusu bos satirla form gonderilmez (sunucu da denetler).
 */
( function () {
	'use strict';

	var section = document.querySelector( '[data-nwcs-faq]' );

	if ( ! section ) {
		return;
	}

	var counter = 0;

	/* ---------------- Sekmeler ---------------- */

	var tabList = section.querySelector( '[data-nwcs-faq-tabs]' );
	var tabs = tabList ? Array.prototype.slice.call( tabList.querySelectorAll( '[data-nwcs-faq-tab]' ) ) : [];

	function panelFor( tab ) {
		return section.querySelector( '[data-nwcs-faq-panel="' + tab.getAttribute( 'data-nwcs-faq-tab' ) + '"]' );
	}

	function select( tab, focus ) {
		tabs.forEach( function ( other ) {
			var on = other === tab;
			var panel = panelFor( other );

			other.classList.toggle( 'is-active', on );
			other.setAttribute( 'aria-selected', on ? 'true' : 'false' );
			other.setAttribute( 'tabindex', on ? '0' : '-1' );

			if ( panel ) {
				panel.hidden = ! on;
			}
		} );

		if ( focus ) {
			tab.focus();
		}

		// Yenileyince ayni sekme acilsin; islem sonucu (?sss=) bir kez gorunur.
		if ( window.history && window.history.replaceState ) {
			try {
				var url = new URL( window.location.href );
				url.searchParams.set( 'sss_site', tab.getAttribute( 'data-nwcs-faq-tab' ) );
				url.searchParams.delete( 'sss' );
				window.history.replaceState( null, '', url.toString() );
			} catch ( error ) {
				// Eski tarayici: adres degismez, sekme yine degisir.
			}
		}
	}

	if ( tabs.length ) {
		tabList.setAttribute( 'role', 'tablist' );
		tabList.setAttribute( 'aria-label', 'Sitelerin soruları' );

		tabs.forEach( function ( tab ) {
			var panel = panelFor( tab );

			tab.setAttribute( 'role', 'tab' );
			tab.removeAttribute( 'aria-current' );
			tab.setAttribute( 'aria-selected', tab.classList.contains( 'is-active' ) ? 'true' : 'false' );
			tab.setAttribute( 'tabindex', tab.classList.contains( 'is-active' ) ? '0' : '-1' );

			if ( panel ) {
				tab.setAttribute( 'aria-controls', panel.id );
				panel.setAttribute( 'role', 'tabpanel' );
				panel.setAttribute( 'aria-labelledby', tab.id );
				panel.setAttribute( 'tabindex', '0' );
			}

			tab.addEventListener( 'click', function ( event ) {
				event.preventDefault();
				select( tab, false );
			} );
		} );

		tabList.addEventListener( 'keydown', function ( event ) {
			var index = tabs.indexOf( document.activeElement );
			var next = -1;

			if ( -1 === index ) {
				return;
			}

			if ( 'ArrowRight' === event.key ) {
				next = ( index + 1 ) % tabs.length;
			} else if ( 'ArrowLeft' === event.key ) {
				next = ( index - 1 + tabs.length ) % tabs.length;
			} else if ( 'Home' === event.key ) {
				next = 0;
			} else if ( 'End' === event.key ) {
				next = tabs.length - 1;
			}

			if ( -1 !== next ) {
				event.preventDefault();
				select( tabs[ next ], true );
			}
		} );
	}

	/* ---------------- Satirlar ---------------- */

	function rowsOf( form ) {
		return Array.prototype.slice.call( form.querySelectorAll( '[data-nwcs-faq-rows] > [data-nwcs-faq-row]' ) );
	}

	function maxOf( form ) {
		return parseInt( form.getAttribute( 'data-nwcs-faq-max' ), 10 ) || 10;
	}

	/* Sablondan yeni satir: anahtar "n1", "n2"... (sunucu sirayi formdaki yerden alir). */
	function newRow( form ) {
		var template = form.querySelector( '[data-nwcs-faq-template]' );

		if ( ! template ) {
			return null;
		}

		counter += 1;

		var holder = document.createElement( 'template' );
		holder.innerHTML = template.innerHTML.replace( /__KEY__/g, 'n' + counter ).trim();

		return holder.content.firstElementChild;
	}

	function say( form, text ) {
		var status = form.querySelector( '[data-nwcs-faq-status]' );

		if ( status ) {
			status.textContent = text;
		}
	}

	function dirty( form ) {
		var status = form.querySelector( '[data-nwcs-faq-status]' );

		if ( status && ! status.textContent ) {
			status.textContent = 'Kaydedilmemiş değişiklik var. Yazmak için “Soruları kaydet”e basın.';
		}
	}

	/* Numaralar, etiketler, ↑ ↓ durumlari, sayac ve "Soru ekle". */
	function refresh( form ) {
		var rows = rowsOf( form );
		var max = maxOf( form );
		var filled = 0;

		rows.forEach( function ( row, index ) {
			var name = ( index + 1 ) + '. soru';
			var number = row.querySelector( '[data-nwcs-faq-n]' );
			var label = row.querySelector( '[data-nwcs-faq-label]' );
			var labelAnswer = row.querySelector( '[data-nwcs-faq-label-a]' );
			var sr = row.querySelector( '[data-nwcs-faq-sr]' );
			var up = row.querySelector( '[data-nwcs-faq-move="up"]' );
			var down = row.querySelector( '[data-nwcs-faq-move="down"]' );
			var question = row.querySelector( '[data-nwcs-faq-q]' );

			if ( number ) {
				number.textContent = String( index + 1 );
			}

			// Etiketteki numara yalnizca ekran okuyucu icin ("2. Soru", "2. Cevap").
			if ( label ) {
				label.textContent = ( index + 1 ) + '. ';
			}

			if ( labelAnswer ) {
				labelAnswer.textContent = ( index + 1 ) + '. ';
			}

			if ( sr ) {
				sr.textContent = ': ' + name;
			}

			if ( up ) {
				up.disabled = 0 === index;
				up.setAttribute( 'aria-label', 'Yukarı taşı: ' + name );
			}

			if ( down ) {
				down.disabled = rows.length - 1 === index;
				down.setAttribute( 'aria-label', 'Aşağı taşı: ' + name );
			}

			if ( question && '' !== question.value.trim() ) {
				filled += 1;
			}
		} );

		var count = form.querySelector( '[data-nwcs-faq-count]' );

		if ( count ) {
			count.textContent = filled + ' / ' + max + ' soru';
		}

		var add = form.querySelector( '[data-nwcs-faq-add]' );

		if ( add ) {
			add.disabled = rows.length >= max;
			add.title = rows.length >= max ? 'Bir sitede en fazla ' + max + ' soru olabilir.' : '';
		}
	}

	section.querySelectorAll( '[data-nwcs-faq-form]' ).forEach( function ( form ) {
		var list = form.querySelector( '[data-nwcs-faq-rows]' );
		var add = form.querySelector( '[data-nwcs-faq-add]' );

		if ( ! list ) {
			return;
		}

		// Formun sonundaki bos "Yeni soru" satiri JS'de numarali, tasinabilir bir satir olur.
		list.querySelectorAll( '[data-nwcs-faq-new]' ).forEach( function ( blank ) {
			var question = blank.querySelector( '[data-nwcs-faq-q]' );
			var answer = blank.querySelector( '[data-nwcs-faq-a]' );
			var fresh = newRow( form );

			if ( fresh && question && answer && '' === question.value && '' === answer.value ) {
				list.replaceChild( fresh, blank );
			}
		} );

		if ( add ) {
			add.hidden = false;
			add.addEventListener( 'click', function () {
				if ( rowsOf( form ).length >= maxOf( form ) ) {
					return;
				}

				var row = newRow( form );

				if ( ! row ) {
					return;
				}

				list.appendChild( row );
				refresh( form );
				dirty( form );

				var question = row.querySelector( '[data-nwcs-faq-q]' );

				if ( question ) {
					question.focus();
				}
			} );
		}

		form.addEventListener( 'input', function () {
			dirty( form );
			refresh( form );
		} );

		form.addEventListener( 'click', function ( event ) {
			var move = event.target.closest( '[data-nwcs-faq-move]' );
			var remove = event.target.closest( '[data-nwcs-faq-remove]' );
			var row = event.target.closest( '[data-nwcs-faq-row]' );

			if ( ! row || ( ! move && ! remove ) ) {
				return;
			}

			event.preventDefault();

			if ( remove ) {
				var rows = rowsOf( form );
				var at = rows.indexOf( row );
				var question = row.querySelector( '[data-nwcs-faq-q]' );
				var text = question && question.value.trim() ? '“' + question.value.trim() + '”' : ( at + 1 ) + '. soru';

				row.parentNode.removeChild( row );

				// Liste bos kalmasin: tek bos satir.
				if ( ! rowsOf( form ).length ) {
					var blank = newRow( form );

					if ( blank ) {
						list.appendChild( blank );
					}
				}

				refresh( form );
				say( form, text + ' kaldırıldı. Yazmak için “Soruları kaydet”e basın.' );

				var left = rowsOf( form );
				var target = left[ Math.min( at, left.length - 1 ) ];
				var field = target ? target.querySelector( '[data-nwcs-faq-q]' ) : null;

				if ( field ) {
					field.focus();
				}

				return;
			}

			var up = 'up' === move.getAttribute( 'data-nwcs-faq-move' );
			var other = up ? row.previousElementSibling : row.nextElementSibling;

			if ( ! other ) {
				return;
			}

			list.insertBefore( row, up ? other : other.nextElementSibling );
			refresh( form );

			// Odak ayni dugmede kalsin; ucta devre disi kaldiysa karsi yondeki dugmeye.
			var focus = move.disabled ? row.querySelector( '[data-nwcs-faq-move="' + ( up ? 'down' : 'up' ) + '"]' ) : move;

			if ( focus ) {
				focus.focus();
			}

			row.classList.add( 'is-moved' );
			window.setTimeout( function () {
				row.classList.remove( 'is-moved' );
			}, 700 );

			say( form, 'Soru ' + ( rowsOf( form ).indexOf( row ) + 1 ) + '. sırada. Yazmak için “Soruları kaydet”e basın.' );
		} );

		/*
		 * Cevabi olup sorusu bos satir: form gonderilmez; sunucunun mesajiyla
		 * ayni metin, ilk bos soruya odak. Sunucu da ayni kurali uygular.
		 */
		form.addEventListener( 'submit', function ( event ) {
			var error = form.querySelector( '[data-nwcs-faq-error]' );
			var empty = [];

			rowsOf( form ).forEach( function ( row, index ) {
				var question = row.querySelector( '[data-nwcs-faq-q]' );
				var answer = row.querySelector( '[data-nwcs-faq-a]' );
				var bad = question && answer && '' === question.value.trim() && '' !== answer.value.trim();

				if ( question ) {
					question.setAttribute( 'aria-invalid', bad ? 'true' : 'false' );
				}

				if ( bad ) {
					empty.push( { input: question, number: index + 1 } );
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
				error.textContent = 'Kaydedilmedi: ' + empty.map( function ( item ) {
					return item.number + '.';
				} ).join( ', ' ) + ' satırda cevap var ama soru boş. Soruyu yazın ya da satırı silin.';
				error.hidden = false;
			}

			empty[ 0 ].input.focus();
		} );

		refresh( form );
	} );
}() );
