var QS = QS || {},
		_qsot_ea_tickets = _qsot_ea_tickets || { ajaxurl:'/wp-admin/admin-ajax.php' };

QS.EATicketSelection = (function($, q, qt) {
	var S = $.extend({}, _qsot_ea_tickets),
			defs = {};

	function aj(sa, data, func, efunc) {
		var data = $.extend({}, data, { action:'qsot-frontend-ticket-selection', sa:sa, nonce:S.nonce, event_id:S.edata.id }),
				func = func || function(){},
				efunc = efunc || function(){};

		$.ajax({
			// patch for 'force ssl admin' issues
			xhrFields: { withCredentials: true },
			url: S.ajaxurl,
			data: data,
			type: 'POST',
			dataType: 'json',
			success: function(r) {
				if (qt.isO(r)) {
					if (typeof r.e != 'undefined') console.log('ajax error: ', r.e);
					func(r);
				} else { efunc( r ); }
			},
			error: efunc
		});
	}
	
	function ui(e, o) {
		var t = this;

		function _init() {
			t.initialized = t.initialized || false;
			if (t.initialized) return;
			t.initialized = true;
			if (_setup_elements()) {
				_setup_events();
				_load_ui();
			}
		}

		function _setup_elements() {
			t.e = { m:$(e) };
			t.e.msgs = $(S.templates['msgs']).appendTo(t.e.m).hide();
			t.e.ts = $(S.templates['ticket-selection']).appendTo(t.e.m).hide();
			t.e.o = $(S.templates['owns']).appendTo(t.e.m).hide();

			t.tt = $(S.templates['tt']);
			if (qt.isO(S.edata) && qt.isO(S.edata.ticket) && qt.is(S.edata.available)) {
				t.tt.find('[rel="ttname"]').html('"'+S.edata.ticket.name+'"');
				t.tt.find('[rel="ttprice"]').html('('+S.edata.ticket.price+')');
			} else {
				_show_msg('not-available');
				return false;
			}
			if (S.edata.available <= 0) {
				_show_msg('sold-out');
				return false;
			}
			var msg = _get_msg('available');
			t.e.m.find('.availability-message').html(_replacements(msg.msg));
			var msg = _get_msg('more-available');
			t.e.m.find('.availability-more-message').html(_replacements(msg.msg));
			t.e.m.find('[rel="tt"]').each(function() {
				t.tt.clone().appendTo($(this).empty());
			});
			_update_availables({ available:S.edata.available, owns:S.owns, available_more:qt.toInt(S.edata.available) - qt.toInt(S.owns) });

			t.eall = t.e.ts.add(t.e.o).add(t.e.msgs);

			return true;
		}

		function _setup_events() {
			t.e.m.on('click', '[rel="reserve-btn"]', _ticket_reservations);
			t.e.m.on('click', '[rel="update-btn"]', _update_reservations);
			t.e.m.on('click', '[rel="remove-btn"]', _remove_reservations);
		}

		function _replacements(msg) {
			var msg = $('<span>'+msg+'</span>'), avail = qt.isO(S.edata) && qt.is(S.edata.available) ? S.edata.available : 0;
			msg.find('[rel="tt"]').each(function() { t.tt.clone().appendTo($(this).empty()); });
			msg.find('.available').html(S.edata.available);
			return msg;
		}

		function _clear_msgs() { t.e.msgs.hide().empty(); }
		function _get_msg(msg_name) { return qt.isO(S.messages[msg_name]) ? S.messages[msg_name] : { msg:'An error has occurred.', type:'error' }; }
		function _show_msg(msg_name, cmsg, ctype) {
			var msg = _get_msg(msg_name), tmpl = $(S.templates[msg.type]);
			_clear_msgs();
			if (msg_name == '_custom' && qt.isA(cmsg) && cmsg.length) {
				var ctype = ctype || 'error';
				tmpl = qt.is(S.templates[ctype]) ? $(S.templates[ctype]) : $(S.templates['error']);
				for (var i=0; i<cmsg.length; i++)
					tmpl.clone().html(_replacements(cmsg[i])).appendTo(t.e.msgs);
			} else {
				tmpl.html(_replacements(msg.msg)).appendTo(t.e.msgs);
			}
			t.e.msgs.show();
		}

		function _load_ui() {
			if (S.owns > 0) {
				t.e.m.find('[rel="qty"]').val(S.owns);
				_display_form('o');
			} else {
				_display_form('ts');
			}
		}

		function _display_form(which) {
			t.eall.hide();
			t.e[which].fadeIn(300);
		}

		function _update_availables(data) {
			t.e.m.find('.availability-message .available').html(qt.is(data.available) ? data.available : '0');
			t.e.m.find('.availability-more-message .available').html(qt.is(data.available_more) ? data.available_more : '0');
		}

		function _is_valid_response(r, form) {
			if ( qt.is( r.data ) )
				_update_availables( r.data );

			if (!qt.isO(r)) {
				_show_msg('unexpected');
				form.qsUnblock();
				return false;
			}
			if (!r.s) {
				if (qt.isA(r.e) && r.e.length) _show_msg('_custom', r.e, 'error');
				else _show_msg('unsuccessful');
				form.qsUnblock();
				return false;
			}
			if (!qt.isO(r.data)) {
				_show_msg('invalid-response');
				form.qsUnblock();
				return false;
			}

			return true;
		}

		function _ticket_reservations(e) {
			e.preventDefault();
			_clear_msgs();

			var waiting = _get_msg('one-moment');
			t.e.m.qsBlock({ msg:_replacements(waiting.msg) });

			var data = t.e.ts.louSerialize();

			aj('r', data, function(r) {
				if (!_is_valid_response(r, t.e.ts)) return;
				t.e.o.find('[rel="qty"]').val(r.data.owns);
				_update_availables(r.data);
				_display_form('o')
				if (qt.isA(r.m) && r.m.length) _show_msg('_custom', r.m, 'msg');
				t.e.ts.qsUnblock();
			}, function( r ) { _show_msg('unexpected'); t.e.ts.qsUnblock(); if ( qt.is( r.data ) ) _update_availables( r.data ); });
		}

		function _update_reservations(e) {
			e.preventDefault();
			_clear_msgs();

			var waiting = _get_msg('one-moment');
			t.e.m.qsBlock({ msg:_replacements(waiting.msg) });

			var data = t.e.o.louSerialize();

			aj('d', data, function(r) {
				if (!_is_valid_response(r, t.e.o)) return;
				t.e.o.find('[rel="qty"]').val(r.data.owns);
				_update_availables(r.data);
				_display_form('o')
				if (qt.isA(r.m) && r.m.length) _show_msg('_custom', r.m, 'msg');
				t.e.ts.qsUnblock();
			}, function( r ) { _show_msg('unexpected'); t.e.ts.qsUnblock(); if ( qt.is( r.data ) ) _update_availables( r.data ); });
		}

		function _remove_reservations(e) {
			e.preventDefault();
			_clear_msgs();

			var waiting = _get_msg('one-moment');
			t.e.m.qsBlock({ msg:_replacements(waiting.msg) });

			var data = {};

			aj('d', data, function(r) {
				if (!_is_valid_response(r, t.e.o)) return;
				t.e.o.find('[rel="qty"]').val(r.data.owns);
				_update_availables(r.data);
				_display_form('ts')
				if (qt.isA(r.m) && r.m.length) _show_msg('_custom', r.m, 'msg');
				t.e.ts.qsUnblock();
			}, function( r ) { _show_msg('unexpected'); t.e.ts.qsUnblock(); if ( qt.is( r.data ) ) _update_availables( r.data ); });
		}

		_init();
	}

	return ui;
})(jQuery, QS, QS.Tools);

jQuery(function() {
	var ui = new QS.EATicketSelection('[rel="ticket-selection"]', {});
});
