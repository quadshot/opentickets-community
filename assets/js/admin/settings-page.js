var QS = QS || { popMediaBox:function(){} };
( function( $, qt ) {
	$( document ).on( 'click', '[rel="no-img"]', function( e ) {
		e.preventDefault();
		var self = $( this ), par = self.closest( self.attr( 'scope' ) ).addClass( 'no-img' );
		par.find( '[rel="image-preview"]' ).empty();
		par.find( '[rel="img-id"]' ).val( 'noimg' );
	} );

	// color picker
	$( function() {
		$( '.clrpick' ).iris({
			change: function( event, ui ) {
				$( this ).parent().find( '.clrpick' ).css({ backgroundColor: ui.color.toString(), color:ui.color.toHsl().l > 50 ? '#000' : '#fff' });
			},
			hide: true,
			border: true
		}).click( function() {
			$( '.iris-picker' ).hide();
			$( this ).closest( '.color_box' ).find( '.iris-picker' ).show();
		}).each( function() {
			var color = $( this ).iris( 'color', true );
			$( this ).css( { backgroundColor:color.toString(), color:color.toHsl().l > 50 ? '#000' : '#fff' } );
		} );

		$( 'body' ).click( function() {
			$( '.iris-picker' ).hide();
		});

		$( '.clrpick' ).click( function( event ) {
			event.stopPropagation();
		});
	} );
} )( jQuery, QS.Tools );
