// qsot-calendar.php
(function($) {
	$(document).on('change', '#page_template', function(e) {
		var me = $(this), par = me.closest('.postbox'), mb = par.siblings('#qsot-calendar-settings-box');
		if (me.val() == 'qsot-calendar.php') {
			mb.addClass('show');
		} else {
			mb.removeClass('show');
		}
	});

	$(document).on('change', '.qsot-cal-meth:checked', function(e) {
		var me = $(this), par = me.closest('.postbox');
		par.find('.extra-box').hide();
		par.find('[rel="extra-'+me.val()+'"]').show();
	});

	$(function() {
		$('#page_template').trigger('change');
		$('.qsot-cal-meth:checked').trigger('change');
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
