(function($, undefined) {
	$(function() {
		$('<style>.ui-datepicker { z-index:999 !important; }</style>').appendTo('head');
		$('.use-datepicker').each( function() {
			var me = $( this ), args = { dateFormat:'yy-mm-dd', onSelect:function() { $( this ).trigger( 'change' ); } }, real = me.attr( 'real' ), scope = me.attr( 'scope' ), frmt = me.attr( 'frmt' );
			if ( 'undefined' != typeof real && null !== real ) {
				var alt = $( real, me.closest( scope || 'body' ) );
				if ( alt.length ) {
					args.altField = alt;
					args.altFormat = args.dateFormat;
					args.dateFormat = frmt || args.dateFormat;
				}
			}
			me.datepicker( args );
		} );
	});
})(jQuery);

(function($, EventUI, undefined) {
	EventUI.callbacks.add('before_submit_event_item', function(ev, data) {
		ev.touched = 'yes';
	});
	$(function() {
		$('.events-ui').qsEventUI();
	});
})(jQuery, QS.EventUI);
