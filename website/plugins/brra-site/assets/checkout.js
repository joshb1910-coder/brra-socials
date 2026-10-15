/* Preview the new-member price as the email is typed. The server applies the real price at payment. */
( function () {
	var box = document.getElementById( 'brra-price-box' );
	var email = document.getElementById( 'bemail' );
	if ( ! box || ! email ) {
		return;
	}
	var note = box.querySelector( '[data-role="note"]' );
	var was = box.querySelector( '[data-role="was"]' );
	var price = box.querySelector( '[data-role="price"]' );
	var submit = document.querySelector( '#pmpro_btn-submit, .pmpro_btn-submit-checkout' );
	var defaultNote = note.textContent.trim();
	var timer = null;
	var last = '';

	function show( state ) {
		if ( 'new' === state ) {
			price.textContent = box.dataset.new;
			was.hidden = false;
			note.textContent = 'New member price applied: your first season is ' + box.dataset.new + '.';
			note.classList.add( 'is-new' );
		} else if ( 'returning' === state ) {
			price.textContent = box.dataset.full;
			was.hidden = true;
			note.textContent = 'Welcome back. Thanks for renewing.';
			note.classList.remove( 'is-new' );
		} else {
			price.textContent = box.dataset.full;
			was.hidden = true;
			note.textContent = defaultNote;
			note.classList.remove( 'is-new' );
		}
		if ( submit && submit.tagName === 'INPUT' ) {
			submit.value = 'Join and pay ' + price.textContent;
		}
	}

	function check() {
		var value = email.value.trim().toLowerCase();
		if ( value === last ) {
			return;
		}
		last = value;
		if ( ! /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test( value ) ) {
			show( 'unknown' );
			return;
		}
		var body = new URLSearchParams( { action: 'brra_price_check', email: value, level: box.dataset.level } );
		fetch( box.dataset.ajax, { method: 'POST', body: body, credentials: 'same-origin' } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( data ) { if ( value === last ) { show( data.state ); } } )
			.catch( function () { show( 'unknown' ); } );
	}

	email.addEventListener( 'input', function () {
		clearTimeout( timer );
		timer = setTimeout( check, 400 );
	} );
	email.addEventListener( 'change', check );
	if ( email.value ) {
		check();
	}
} )();
