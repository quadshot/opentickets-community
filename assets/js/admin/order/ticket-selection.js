var QS = QS || {},
		_qsot_admin_ticket_selection = _qsot_admin_ticket_selection || {};

QS.adminTicketSelection = (function($, qs, qt) {
	var S = $.extend({}, _qsot_admin_ticket_selection), defs = {};
	$(function() { S.order_id = $('#post_ID').val(); });

	function aj(sa, data, func, efunc) {
		var data = $.extend({}, data, { action:'qsot-admin-ticket-selection', sa:sa, nonce:S.nonce, order_id:S.order_id, customer_user:$('#customer_user').val() }),
				func = func || function(){},
				efunc = efunc || function(){};

		$.ajax({
			url: ajaxurl,
			data: data,
			type: 'POST',
			dataType: 'json',
			success: function(r) {
				if (typeof r == 'object') {
					if (typeof r.e != 'undefined') console.log('ajax error: ', r.e);
					func(r);
				} else { efunc(); }
			},
			error: efunc
		});
	}

	function ui(o, e) {
		var t = this;
		
		function _init() {
			if (t.initialized) return;
			t.initialized = true;

			t.current_action = false;
			t.oi = false;
			t.ev = {};
			t.oiid = 0;
			t.need_qty = 0;
			t.priced_like = 0;

			t.o = $.extend({}, defs, o, { author:'loushou', version:'0.1.0' });

			t.e = { scope: $(e) };
			t.e.oi = $('#order_items_list', t.e.scope);
			t.e.oi = t.e.oi.length ? t.e.oi : $('#order_line_items', t.e.scope);

			_setup_elements();
			_setup_events();
		}

		function _dia_error(errs, hide_all) {
			var hide_all = hide_all || false;
			t.e.err.empty();
			for (var i=0; i<errs.length; i++) $('<div class="error"></div>').html(errs[i]).appendTo(t.e.err);
			if (hide_all) t.e.all.hide();
			t.e.err.fadeIn(200);
		}

		function _dia_msgs(msgs, hide_all) {
			var hide_all = hide_all || false;
			t.e.err.empty();
			for (var i=0; i<msgs.length; i++) $('<div class="error"></div>').html(msgs[i]).appendTo(t.e.err);
			if (hide_all) t.e.all.hide();
			t.e.err.fadeIn(200);
		}

		function _start_change_event(e) {
			e.preventDefault();
			var dt = new XDate();
			if (typeof t.ev == 'object' && typeof t.ev.dt == 'string') dt = new XDate(t.ev.dt);
			_load_calendar(dt);
		}

		function _start_change_ui(e) {
			e.preventDefault();
			t.current_action = 'change';
			var me = $(this), event_id = qt.toInt(me.attr('event-id'));
			t.qty = qt.toInt(me.attr('qty'));
			t.oiid = qt.toInt(me.attr('item-id'));
			t.oi = me.closest('.item');

			if (event_id <= 0) event_id = typeof t.ev == 'object' && typeof t.ev.id != 'undfined' ? qt.toInt(t.ev.id) : 0;
			if (event_id > 0) t.priced_like = event_id;

			if (event_id > 0) {
				_load_event(event_id);
			} else {
				_load_calendar(new XDate());
			}
		}

		function _start_add_tickets_ui(e) {
			e.preventDefault();
			t.current_action = 'add';
			var dt = new XDate();
			if (typeof t.ev == 'object' && typeof t.ev.dt == 'string') dt = new XDate(t.ev.dt);
			_load_calendar(dt);
		}

		function _add_tickets(e) {
			e.preventDefault();
			var me = $(this), form = me.closest('.ticket-form'), args = form.louSerialize({
				eid: t.ev.id
			});
			
			if (args.qty < 1) {
				alert('You must specify a quantity greater than 1.');
				return;
			}

			var dia = t.e.dia.closest('.ui-dialog');
			dia.qsBlock({ msg:'<h1>Processing...</h1>', css:{ zIndex:999999 }, msgcss:{ zIndex:1000000 } })

			aj('add-tickets', args, function(r) {
				if (typeof r != 'object') {
					_dia_error(['Invalid response.']);
					dia.qsUnblock();
					return;
				}

				if (!r.s) {
					if (typeof r.e == 'object') _dia_error(r.e);
					else _dia_error(['There was a problem adding those tickets.']);
					dia.qsUnblock();
					return;
				}

				_update_order_items(function(r) {
					if (!qt.isO(r) || !qt.isO(r.i) || r.i.length == 0) {
						dia.qsUnblock();
						_dia_error(['Tickets were added, but you must refresh the page to see them in the order items list.']);
						return;
					}

					t.e.oi.empty();
					for (var i=0; i<r.i.length; i++) $(r.i[i]).appendTo(t.e.oi);
					
					dia.qsUnblock();
					_dia_msgs(['Tickets have been added.']);
				}, function() { dia.qsUnblock(); });
			});
		}

		function _update_order_items(success, failure) {
			var s = function(r) { t.e.oi.qsUnblock(); if (typeof success == 'function') success(r); },
					e = function() { t.e.oi.qsUnblock(); if (typeof failure == 'function') failure(); };
			t.e.oi.qsBlock({ css:{ zIndex:9999 }, msgcss:{ zIndex:10000 } });
			aj('update-order-items', {}, s, e);
		}

		function _display_type(name) {
			var aname = 'actions_'+name, iname = 'inner_'+name;
			t.e.ev.find('[rel="image-wrap"]').empty();
			t.e.ev.find('>').appendTo(t.e.holder);
			t.e.actions.find('>').appendTo(t.e.holder);
			t.e[aname].appendTo(t.e.actions);
			t.e[iname].appendTo(t.e.ev);
		}

		function _load_event(event_id) {
			action = action || 'change';
			t.e.all.hide();
			t.e.trans.fadeIn(200);
			if (!t.e.dia.dialog('isOpen')) t.e.dia.dialog('open');

			t.ev = {};

			aj('load-event', { eid:event_id, oiid:t.oiid }, function(r) {
				if (typeof r != 'object') {
					_dia_error(['There was a problem loading the requested information. Please close this modal and try again.'], true);
					return;
				}

				if (typeof r.data != 'object') {
					_load_calendar(new XDate(), ['There was a problem loading the requested Event. Switching to calendar view.']);
					return;
				}
				//console.log('event', r);

				t.ev = r.data;

				t.e.info[t.qty <= r.data._available ? 'removeClass' : 'addClass']('no-enough');
				$(r.data._link).appendTo(t.e.info.find('[rel="name"]').empty());
				$(r.data._html_date).appendTo(t.e.info.find('[rel="date"]').empty());
				t.e.info.find('[rel="capacity"] [rel="total"]').text(r.data._capacity);
				t.e.info.find('[rel="capacity"] [rel="available"]').text(r.data._available);

				_display_type(t.current_action);

				t.e.ev.find('[rel="ticket-count"]').val(r.data._owns);
				if (r.data._owns > 0) t.e.ev.find('[rel="add-btn"]').val('Change Ticket Count');
				else t.e.ev.find('[rel="add-btn"]').val('Add Tickets');
				t.e.ev.find('[rel="ttname"]').html('"'+r.data._raw.meta._event_area_obj.ticket._display_title+' ('+r.data._raw.meta._event_area_obj.ticket._display_price+')'+'"');
				if (qt.isO(r.data._imgs) && qt.isO(r.data._imgs.full) && typeof r.data._imgs.full.url)
					$('<div class="event-area-image"><img src="'+r.data._imgs.full.url+'" title="'+r.data.name+'" /></div>').appendTo(t.e.ev.find('[rel="image-wrap"]').empty());

				ui.callbacks.trigger( 'load-event', [ r, t.e ] );

				t.e.all.hide();
				$(t.e.info).add(t.e.actions).add(t.e.ev).fadeIn(200);
			});
		}

		function _load_calendar(dt, msgs) {
			msgs = msgs || false;
			t.e.all.hide();
			if (msgs && msgs.length) _dia_error(msgs, true);
			t.e.cal.fadeIn(200);
			if (!t.e.dia.dialog('isOpen')) t.e.dia.dialog('open');

			t.cal.setUrlParams({ priced_like:t.priced_like });

			var dt = new XDate(dt);
			t.cal.cal.fullCalendar('gotoDate', dt.getFullYear(), dt.getMonth());

			ui.callbacks.trigger( 'load-calendar', [ dt, t.e ] );
		}

		function _select_event(e, calEvent) {
			e.preventDefault();
			_load_event(calEvent.id);
		}

		function _update_ticket(e) {
			e.preventDefault();

			var data = {
				eid: t.ev.id,
				order_id: $('#post_ID').val(),
				oiid: t.oiid
			};

			// update the ticket based on the current event selected
			aj('update-ticket', data, function(r) {
				console.log('update-ticket', r, !qt.isO(r), !qt.isO(r.data), !qt.isO(r.event));
				if (!qt.isO(r) || !qt.isO(r.data) || !qt.isO(r.event)) {
					console.log('ajax error: ', 'Invalid response.', r);
					_dia_error(['There was a problem changing the reservation.']);
					return;
				}
				console.log('updating now');
				t.oi.find('.change-ticket').attr('event-id', r.event.ID);
				var lk = t.oi.find('.ticket-info [rel="edit-event"]');
				$('<a rel="edit-event" href="'+r.event._edit_url+'" target="_blank" title="edit event">'+r.event.post_title+'</a>').insertBefore(lk);
				lk.remove();
				t.oi.find('.ticket-info').addClass('updated');
				t.e.dia.dialog('close');
			});
		}

		function _setup_elements() {
			var windims = { w:$(window).outerWidth(true), h:$(window).outerHeight(true) };

			t.e.holder = $('<div></div>').hide().appendTo('body');

			t.e.dia = $(S.templates['dialog-shell']).appendTo('#wpwrap').dialog({
				autoOpen: false,
				width: windims.w >= 1000 ? 1000 : (windims.w >= 600 ? 600 : windims - 10),
				height: 'auto',
				modal: true,
				position: { my:'center', at:'center', of:window },
				appendTo: '#wpwrap',
				close: function() { t.qty = 0; t.oiid = 0; t.priced_like = 0; t.oi = false; t.current_action = false; }
			});

			t.e.err = t.e.dia.find('[rel="errors"]');
			t.e.info = t.e.dia.find('[rel="info"]');
			t.e.actions = t.e.dia.find('[rel="actions"]');
			t.e.trans = t.e.dia.find('[rel="transition"]');
			t.e.ev = t.e.dia.find('[rel="event-wrap"]');
			t.e.cal = t.e.dia.find('[rel="calendar-wrap"]');

			t.e.all = t.e.err.add(t.e.info).add(t.e.actions).add(t.e.ev).add(t.e.cal).add(t.e.trans);

			$(S.templates['transition']).appendTo(t.e.trans);
			$(S.templates['info']).appendTo(t.e.info);
			t.e.actions_change = $(S.templates['actions:change']).appendTo(t.e.holder);
			t.e.actions_add = $(S.templates['actions:add']).appendTo(t.e.holder);
			t.e.inner_change = $(S.templates['inner:change']).appendTo(t.e.holder);
			t.e.inner_add = $(S.templates['inner:add']).appendTo(t.e.holder);

			var today = XDate();
			var args = $.extend({}, _qsot_event_calendar_ui_settings, {
				calendarContainer: t.e.cal,
				onSelection: _select_event
			});
			t.cal = new QSEventsEventCalendar(args);
			t.cal.cal.fullCalendar('gotoDate', today.getFullYear(), today.getMonth());

			ui.callbacks.trigger( 'setup-elements', [ t.e ] );
		}

		function _setup_events() {
			t.e.scope.on('click', '[rel="add-tickets-btn"]', _start_add_tickets_ui);
			t.e.scope.on('click', '.change-ticket', _start_change_ui);
			t.e.actions.on('click', '[rel="change-btn"]', _start_change_event);
			t.e.actions.on('click', '[rel="use-btn"]', _update_ticket);
			t.e.ev.on('click', '[rel="add-btn"]', _add_tickets);
		}

		_init();
	}

	ui.aj = aj;
	ui.callbacks = new QS.EventUI_Callbacks();

	return ui;
})(jQuery, QS, QS.Tools);

jQuery(function($) {
	var ts = new QS.adminTicketSelection({
	}, 'body');
});
