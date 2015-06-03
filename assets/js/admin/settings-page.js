var QS = QS || { popMediaBox:function(){} };
( function( $, qt ) {
	$( document ).on( 'click', '[rel="no-img"]', function( e ) {
		e.preventDefault();
		var self = $( this ), par = self.closest( self.attr( 'scope' ) ).addClass( 'no-img' );
		par.find( '[rel="image-preview"]' ).empty();
		par.find( '[rel="img-id"]' ).val( 'noimg' );
	} );
} )( jQuery, QS.Tools );
