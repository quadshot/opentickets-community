var QS = QS || { Tools:{} };

( function( $, qt ) {
	var S = $.extend( {}, _qsot_event_area_admin );

	$( function() {
		// add select to to things that need it, on page load
		QS.add_select2( $( '.use-select2' ), S );

		// add the media box logic to any button that needs it on page load
		$( '.use-popmedia' ).on( 'click', function( e ) {
			e.preventDefault();

			QS.popMediaBox.apply(this, [e, {
				par: $( this ).attr( 'scope' ) || '[rel="field"]',
				id_field: '[rel="img-id"]',
				pc: '[rel="img-wrap"]'
			}]);
		} );
	} )

	// handle the switching of the event_area_type
	$( document ).on( 'change', '[name="qsot-event-area-type"]', function( e ) {
		var current = $( this ).val();
		// hide any postboxes that are not for this type, but for other types instead
		$( '.postbox.not-for-' + current ).hide();

		// show any postboxes that ARE for this type
		$( '.postbox.for-' + current ).fadeIn( 300 );

		// trigger global callbacks for box selection
		QS.cbs.trigger( 'postbox-' + current, [] );
	} );

	$( function() { $( '[name="qsot-event-area-type"]:checked:eq(0)' ).trigger( 'change' ); } );
} )( jQuery, QS.Tools );
