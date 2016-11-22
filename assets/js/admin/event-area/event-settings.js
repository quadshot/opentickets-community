(function($, EventUI, EditSetting, qt, undefined) {
	EventUI.callbacks.add('add_event', function(args, orig) {
		args['event_area'] = 0;
	});

	EventUI.callbacks.add('before_submit_event_item', function(ev, evdata) {
		ev['event_area'] = evdata['event-area'];
	});

	// when data on the event settings is selected, make sure to udate the ui appropriately
	EditSetting.callbacks.add('update', function(data, adjust) {
		// if the selected data was a venue
		if (this.tag == 'venue' && ($.inArray(typeof data.venue, ['string', 'number']) != -1 || (typeof data.venue == 'object' && typeof data.venue.toString == 'function'))) {
			// store the venue id to test for
			var test = $.inArray(typeof data.venue, ['string', 'number']) != -1 ? data.venue : data.venue.toString(),
					// find the element that holds the current setting for event area
					ea = this.elements.main_form.find('[tag="event-area"]');
			// if we found the element
			if (ea.length) {
				// get the ea object from the element
				ea = ea.qsEditSetting('get');

				// if we got an ea object..
				if (typeof ea == 'object' && ea.initialized) {
					// find the event area pool
					var pool = ea.elements.form.find('[name="event-area-pool"]'),
							// find the element to display the event area name
							display = ea.elements.form.find('[name="event-area"]'),
							// get the current event area id
							current = data['event-area'] || display.val();
					// clear out the display
					display.empty();

					// copy all non-associated event areas to the display container
					pool.find('option').not('[venue-id]').clone().appendTo( display );

					// copy all eas that belong to the venue, to the display container
					pool.find('option[venue-id="'+test+'"]').clone().appendTo( display );

					// select the current value from the display box
					if ( ! display.find( 'option[value="' + current + '"]' ).length )
						pool.find( 'option[value="' + current + '"]' ).clone().appendTo( display );
					display.find( 'option[value="' + current + '"]' ).prop( 'selected', 'selected' );
				}
			}
		} else if ( this.tag == 'event-area' && ( $.inArray( typeof data['event-area'], ['string', 'number'] ) != -1 || ( typeof data['event-area'] == 'object' && typeof data['event-area'].toString == 'function' ) ) ) {
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
