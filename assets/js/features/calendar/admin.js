// qsot-calendar.php
(function($) {
	$(document).on('change', '#page_template', function(e) {
		var me = $(this), par = me.closest('.postbox'), mb = par.siblings('#qsot-calendar-settings-box');
		console.log('changed page template', me, me.val(), par, mb);
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
		$('.use-datepicker').datepicker({dateFormat:'yy-mm-dd', onSelect:function(){$(this).trigger('change');}});
	});
})(jQuery);
