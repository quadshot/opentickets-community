( function( $, qt ) { 
	var S = $.extend( { str:{} }, _qsot_system_status );

	// get a translated string if it exists
	function _str( str ) { return qt.is( S.str[ str ] ) ? S.str[ str ] : str; }

  // add select2 to a given element
  QS.add_select2 = function( eles, settings ) { 
		var S = $.extend( { nonce:'' }, settings );
    $( eles ).each( function() {
      var me = $( this ), sa = me.data( 'sa' ) || 'find-posts', action = me.data( 'action' ) || 'qsot-system-status', array_data = me.data( 'array' ) || false, minlen = parseInt( me.data( 'minchar' ) || 2 );

			if ( array_data ) {
				me.select2( {
					data: array_data,
					initSelection: function( ele, callback ) { 
						callback( $( ele ).data( 'init-value' ) );
					},  
					minimumInputLength: 0
				} );
			} else {
				me.select2( {
					ajax: {
						url: ajaxurl,
						data: function( term, page ) { 
							var data = { q:term, page:page, sa:sa, action:action, _n:me.data( 'nonce' ) || S.nonce };
							if ( me.data( 'add' ) ) {
								var ele = $( me.data( 'add' ) ).eq( 0 );
								if ( ele.length )
									data[ ele.attr( 'name' ) ] = ele.val();
							}
							return data;
						},  
						method: 'post',
						type: 'post',
						dataType: 'json',
						delay:300,
						processResults: function( data, page ) { return { results:data.r }; },
						cache: true
					},  
					initSelection: function( ele, callback ) { 
						callback( $( ele ).data( 'init-value' ) );
					},  
					minimumInputLength: minlen
				} );
			}
		} );
  }

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
				if ( r.success && r.r ) {
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

console.log( 'release', data, target, me.data( 'target' ) || me.next( '.results' ), me.data( 'target' ), me );
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
				if ( r.success && r.r ) {
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
