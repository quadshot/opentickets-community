var QS = $.extend( { Tools:{} }, QS );

( function( $, qt ) {
	var S = $.extend( {}, _qsot_event_area_admin );

	$( function() {
		QS.add_select2( $( '.use-select2' ), S );

		$( '.use-popmedia' ).on( 'click', function( e ) {
			e.preventDefault();

			console.log( 'fucking fuck', this, QS.popMediaBox );

			QS.popMediaBox.apply(this, [e, {
				par: $( this ).attr( 'scope' ) || '[rel="field"]',
				id_field: '[rel="img-id"]',
				pc: '[rel="img-wrap"]'
			}]);
		} );
	} )
} )( jQuery, QS.Tools );
