var QS = QS || { popMediaBox:function(){} };
( function( $, qt ) {
	$( document ).on( 'click', '.qsot-popmedia', function( e ) {
		var self = $( this );
		QS.popMediaBox.apply( this, [ e, {
			with_selection: function( attachment ) {
				self.closest( self.attr( 'scope' ) ).removeClass( 'no-img' );
			}
		} ] );
	} );

	$( document ).on( 'click', '[rel="remove-img"]', function( e ) {
		e.preventDefault();
		var self = $( this ), par = self.closest( self.attr( 'scope' ) );
		par.find( '[rel="image-preview"]' ).empty();
		par.find( '[rel="img-id"]' ).val( '0' );
	} );

	$( document ).on( 'click', '[rel="no-img"]', function( e ) {
		e.preventDefault();
		var self = $( this ), par = self.closest( self.attr( 'scope' ) ).addClass( 'no-img' );
		par.find( '[rel="image-preview"]' ).empty();
		par.find( '[rel="img-id"]' ).val( 'noimg' );
	} );
} )( jQuery, QS.Tools );
