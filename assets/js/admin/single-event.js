( function( $ ) {
	var S = $.extend( true, { evt:{} }, _qsot_single_event ),
			H = 'hasOwnProperty';

	// update the interface with the settings from the event
	function update_settings( data ) {
		var main = $( '.single-event-settings' ), i;

		// cycle through all the event data, and update the form accordingly
		for ( i in data ) if ( data[ H ]( i ) ) {
			var field = main.find( '[name="settings[' + i + ']"]' );
			// if there is not relevant field, then skip this data key
			if ( ! field.length )
				continue;

			// otherwise try to update it
			var setting_main = field.closest( field.attr( 'scope' ) || 'body' );
			if ( setting_main.length > 0 ) {
				var updateArgs = {},
						val = -1 === $.inArray( i, ['start', 'end'] ) ? data[ i ] : ( function( d ) { return {
							toLabel: function() { return moment( d ).format( 'YYYY-MM-DD HH:mm:ss' ); },
							toString: function() { return d; }
						}; } )( data[ i ] );
				updateArgs[ i ] = val;
				setting_main.qsEditSetting( 'update', data, false );
				setting_main.qsEditSetting( 'update', updateArgs, true );
			}
		}
	}

	$( document ).on( 'submit', 'form', function() {
		var main = $( '.single-event-settings' ),
				data = main.louSerialize();

		$( '<input type="hidden" name="qsot-event-settings"/>' ).appendTo( $( this ) ).val( JSON.stringify( data ) );
	} );

	$( function() {
		update_settings( S.evt );
	} );
} )( jQuery );
