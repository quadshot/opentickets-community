var QS = QS || { Tools:{} };
( function( $, qt ) { 
	// i18n string please
	QS._str = function( str, S ) { var S = $.extend( true, { str:{} }, S ); return qt.is( S.str[ str ] ) ? S.str[ str ] : str; };

  // add select2 to a given element
  QS.add_select2 = function( eles, settings ) { 
		var S = $.extend( { nonce:'' }, settings );
    $( eles ).each( function() {
      var me = $( this ), sa = me.data( 'sa' ) || 'find-posts', action = me.data( 'action' ) || 'qsot-ajax', array_data = me.data( 'array' ) || false, minlen = parseInt( me.data( 'minchar' ) || 2 );

			if ( array_data ) {
				var data_func = qt.isF( S.data_func_func ) ? S.data_func_func( me, array_data ) : function() { return { results:array_data }; },
						args = {
							data: data_func,
							initSelection: function( ele, callback ) {
								var val = $( ele ).data( 'init-value' );
								$( ele ).select2( 'data', qt.isO( val ) ? val : { id:0, text:$( ele ).data( 'init-placeholder' ) || QS._str( 'Select One' ) } );
							},  
							minimumInputLength: 0
						};
				// if the matcher was set, the use that too
				if ( S.matcher_func && qt.isF( S.matcher_func ) )
					args.matcher = S.matcher_func( me );
				me.select2( args );
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
						var val = $( ele ).data( 'init-value' );
						$( ele ).select2( 'data', qt.isO( val ) ? val : { id:0, text:$( ele ).data( 'init-placeholder' ) || QS._str( 'Select One' ) } );
					},  
					minimumInputLength: minlen
				} );
			}
		} );
  }
} )( jQuery, QS.Tools );
