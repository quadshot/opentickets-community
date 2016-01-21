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
		var T = '';
		$( '.clrpick' ).iris({
			change: function( event, ui ) {
				$( this ).parent().find( '.clrpick' ).css( {
					backgroundColor: ui.color.toString(),
					color: T !== ui.color.toString() ? ( ui.color.toHsl().l > 50 ? '#000' : '#fff' ) : '#bbb',
					fontStyle: T !== ui.color.toString() ? 'normal' : 'italic'
				} );
			},
			hide: true,
			border: true
		}).click( function() {
			$( '.iris-picker' ).hide();
			$( this ).closest( '.color_box' ).find( '.iris-picker' ).show();
		}).each( function() {
			var color = $( this ).iris( 'color', true );
			$( this ).parent().find( '.clrpick' ).css( {
				backgroundColor: color.toString(),
				color: T !== color.toString() ? ( color.toHsl().l > 50 ? '#000' : '#fff' ) : '#bbb',
				fontStyle: T !== color.toString() ? 'normal' : 'italic'
			} );
		} );

		$( 'body' ).click( function() {
			$( '.iris-picker' ).hide();
		});

		$( '.clrpick' ).click( function( event ) {
			event.stopPropagation();
		});
	} );

	$( document ).on( 'click', '[rel="reset-colors"]', function( e ) {
		e.preventDefault();
		var td = $( this ).closest( '.color-selection' );
		$( '.clrpick', td ).each( function() {
			var def = $( this ).data( 'default' );
			if ( def ) {
				$( this ).val( def ).trigger( 'change' );
				$( this ).closest( '.color_box' ).find( '[rel="transparent"]' )[ 'transparent' == def ? 'prop' : 'removeProp' ]( 'checked', 'checked' );
			}
		} );
	} );
} )( jQuery, QS.Tools );
