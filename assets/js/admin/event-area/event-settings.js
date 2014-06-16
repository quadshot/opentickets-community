(function($, EventUI, EditSetting, qt, undefined) {
	EventUI.callbacks.add('add_event', function(args, orig) {
		args['event_area'] = 0;
	});

	EventUI.callbacks.add('before_submit_event_item', function(ev, evdata) {
		ev['event_area'] = evdata['event-area'];
	});

	EditSetting.callbacks.add('update', function(data, adjust) {
		if (this.tag == 'venue' && ($.inArray(typeof data.venue, ['string', 'number']) != -1 || (typeof data.venue == 'object' && typeof data.venue.toString == 'function'))) {
			var test = $.inArray(typeof data.venue, ['string', 'number']) != -1 ? data.venue : data.venue.toString();
			var ea = this.elements.main_form.find('[tag="event-area"]');
			if (ea.length) {
				ea = ea.qsEditSetting('get');
				if (typeof ea == 'object' && ea.initialized) {
					ea.elements.form.find('[name="event-area"] option').hide();
					ea.elements.form.find('[name="event-area"] option').not('[venue-id]').show();
					ea.elements.form.find('[name="event-area"] option[venue-id="'+test+'"]').show();
				}
			}
		} else if (this.tag == 'event-area' && (
				$.inArray(typeof data['event-area'], ['string', 'number']) != -1 || (typeof data['event-area'] == 'object' && typeof data['event-area'].toString == 'function')
		)) {
			var test = $.inArray(typeof data['event-area'], ['string', 'number']) != -1 ? data['event-area'] : data['event-area'].toString(),
					ea = this.elements.main_form.find('[tag="event-area"]');
			if (ea.length) {
				ea = ea.qsEditSetting('get');
				if (typeof ea == 'object' && ea.initialized) {
					data.capacity = qt.toInt(ea.elements.form.find('[name="event-area"] option[value="'+data['event-area']+'"]').attr('capacity'));
				}
			}
		}
	});

/*
	EventUI.callbacks.add('render_event', function(ev, element, view, that) {
		// figure out how to make a look up for the actual name
		element.find('.'+this.fctm+'-venue').html('(ID:'+ev.venue+')');
	});
*/
})(jQuery, QS.EventUI, QS.EditSetting, QS.Tools);
