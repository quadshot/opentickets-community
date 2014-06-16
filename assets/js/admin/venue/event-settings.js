(function($, EventUI, EditSetting, undefined) {
	EventUI.callbacks.add('add_event', function(args, orig) {
		args['venue'] = 0;
	});

	EventUI.callbacks.add('before_submit_event_item', function(ev, evdata) {
		ev['venue'] = evdata.venue;
	});

	EventUI.callbacks.add('render_event', function(ev, element, view, that) {
		// figure out how to make a look up for the actual name
		element.find('.'+this.fctm+'-venue').html('(ID:'+ev.venue+')');
	});
})(jQuery, QS.EventUI, QS.EditSetting);
