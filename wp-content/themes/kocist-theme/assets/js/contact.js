/*
 * Iletisim sayfasi: haritadaki isletme sekmeleri.
 *
 * Sekmeye basinca harita, karttaki isletme adi ve yol tarifi o isletmeye
 * gecer. Gomme adresi sunucuda kurulup dugmeye yazilir; betik adres uretmez.
 * Oklar odagi sekmeler arasinda gezdirir, Enter / Bosluk secer: her secim
 * haritayi yeniden yukluyor, okla gezerken her adimda yuklenmesin. Kendiliginden
 * donmez. Betik yoksa sekmeler gizli kalir, kartta uc isletme de listelenir.
 */
( function () {
	var root = document.querySelector( '[data-k-map]' );

	if ( ! root ) {
		return;
	}

	var list   = root.querySelector( '[data-k-map-tabs]' );
	var tabs   = Array.prototype.slice.call( root.querySelectorAll( '[data-k-map-tab]' ) );
	var places = Array.prototype.slice.call( root.querySelectorAll( '[data-k-map-place]' ) );
	var frame  = root.querySelector( '[data-k-map-frame]' );
	var panel  = root.querySelector( '#k-map-panel' );

	if ( ! list || tabs.length < 2 || tabs.length !== places.length || ! frame || ! panel ) {
		return;
	}

	function select( index ) {
		var tab = tabs[ index ];
		var src = tab.getAttribute( 'data-k-map-src' );

		tabs.forEach( function ( item, i ) {
			item.setAttribute( 'aria-selected', i === index ? 'true' : 'false' );
			item.setAttribute( 'tabindex', i === index ? '0' : '-1' );
		} );

		places.forEach( function ( place, i ) {
			place.hidden = i !== index;
		} );

		panel.setAttribute( 'aria-labelledby', tab.id );
		frame.title = tab.getAttribute( 'data-k-map-name' ) || frame.title;

		if ( src && frame.getAttribute( 'src' ) !== src ) {
			frame.setAttribute( 'src', src );
		}
	}

	tabs.forEach( function ( tab, index ) {
		tab.addEventListener( 'click', function () {
			select( index );
		} );

		tab.addEventListener( 'keydown', function ( event ) {
			var next = -1;

			if ( 'ArrowRight' === event.key ) {
				next = ( index + 1 ) % tabs.length;
			} else if ( 'ArrowLeft' === event.key ) {
				next = ( index - 1 + tabs.length ) % tabs.length;
			} else if ( 'Home' === event.key ) {
				next = 0;
			} else if ( 'End' === event.key ) {
				next = tabs.length - 1;
			}

			if ( next > -1 ) {
				event.preventDefault();
				tabs[ next ].focus();
			}
		} );
	} );

	panel.setAttribute( 'role', 'tabpanel' );
	list.hidden = false;
	select( 0 );
}() );
