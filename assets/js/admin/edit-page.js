(function($, undefined) {
	$(function() {
		$('<style>.ui-datepicker { z-index:999 !important; }</style>').appendTo('head');
		$('.use-datepicker').datepicker({dateFormat:'yy-mm-dd', onSelect:function(){$(this).trigger('change');}});
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
