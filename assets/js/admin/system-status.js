( function( $, qt ) { 
	var S = $.extend( { str:{} }, _qsot_system_status );

	// get a translated string if it exists
	function _str( str ) { return qt.is( S.str[ str ] ) ? S.str[ str ] : str; }

  // on page load, add the select2 ui to any element that requires
  $( function() { QS.add_select2( $( '.use-select2' ), S || {} ); } );

	// when submitting the ajax forms, do so via ajax :)
	$( document ).on( 'submit', '.qsot-ajax-form', function( e ) {
		e.preventDefault();

		// get all the form data
		var me = $( this ), data = me.louSerialize(), action = me.data( 'action' ) || 'qsot-system-status', sa = me.data( 'sa' ) || 'load-post', target = $( me.data( 'target' ) || me.next( '.results' ) );
		data = $.extend( { action:action, sa:sa, _n:me.data( 'nonce' ) || S.nonce }, data );

		// pop loading message
		$( '<h3>' + _str( 'Loading...' ) + '</h3>' ).appendTo( target.empty() );

		// load the data
		$.ajax( {
			dataType: 'json',
			method: 'post',
			url: ajaxurl,
			cache: false,
			data: data,
			success: function( r ) {
				if ( r.s && r.r ) {
					var out = $( r.r ).appendTo( target.empty() );
					QS.add_select2( $( '.use-select2', out ), S || {} );
				} else {
					$( '<p>' + _str( 'No results found.' ) + '</p>' ).appendTo( target.empty() );
				}
			},
			error: function() {
				$( '<p>' + _str( 'No results found.' ) + '</p>' ).appendTo( target.empty() );
			}
		} );
	} );

	// handle adv tools table row actions
	$( document ).on( 'click', '[role="release-btn"]', function( e ) {
		e.preventDefault();

		var me = $( this ), entry = me.closest( '[role="entry"]' ), id = entry.data( 'row' ), action = me.data( 'action' ) || 'qsot-system-status', sa = me.data( 'sa' ) || 'release',
				evnt = me.closest( '[role="event"]' ), event_id = evnt.data( 'id' ), target = $( me.data( 'target' ) || me.next( '.results' ) ), data = { id:id, event_id:event_id, action:action, sa:sa, _n:me.data( 'nonce' ) || S.nonce };

		// pop loading message
		$( '<h3>' + _str( 'Loading...' ) + '</h3>' ).appendTo( target.empty() );

		// load the data
		$.ajax( {
			dataType: 'json',
			method: 'post',
			url: ajaxurl,
			cache: false,
			data: data,
			success: function( r ) {
				if ( r.s && r.r ) {
					var out = $( r.r ).appendTo( target.empty() );
					QS.add_select2( $( '.use-select2', out ), S || {} );
				} else {
					$( '<p>' + _str( 'No results found.' ) + '</p>' ).appendTo( target.empty() );
				}
			},
			error: function() {
				$( '<p>' + _str( 'No results found.' ) + '</p>' ).appendTo( target.empty() );
			}
		} );
	} );
} )( jQuery, QS.Tools );
