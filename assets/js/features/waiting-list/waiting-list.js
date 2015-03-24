var QS = QS || {};
QS.WaitingList = (function($, w, d, undefined) {
	var qt = QS.Tools;
	var initialized = false;
	var _defs = {
		event_id: 0,
		templates: {},
		ui: {}
	};
	var aj = new QS.Ajax(), qt = QS.Tools;

	function wlist(o) {
		var t = this;

		t.o = $.extend({}, _defs, o, {author:'loushou', version:'0.1-beta'});
		t.e = {};
		t.init = _init;
		t.setup_elements = _setup_elements;
		t.setup_events = _setup_events;

		t.init();

		function _init() {
			if (!initialized && t.o.show) {
				initialized = true;
				t.setup_elements();
				t.setup_events();
			}
		};

		function _click_remove_me(e) {
			e.preventDefault();
			var data = {
				sa: 'remove-me',
				e: t.o.event_id
			};

			t.e.list.block();
			t.e.msgs.empty();

			aj.q('waiting-list', data, function(r) {
				if (r.s) {
					_update_list_view('', '');
				} else {
					_show_messages(r.m);
				}
				t.e.list.unblock();
			}, 'post', function(r) {
				t.e.list.unblock();
			});
		};

		function _click_add_me(e) {
			e.preventDefault();
			var data = {
				sa: 'add-me',
				e: t.o.event_id,
				qty: t.e.addform.find('[rel="quantity"]').val()
			};

			t.e.list.block();
			t.e.msgs.empty();

			aj.q('waiting-list', data, function(r) {
				if (r.s) {
					_update_list_view(r.pos, r.quantity);
				} else {
					_show_messages(r.m);
				}
				t.e.list.unblock();
			}, 'post', function(r) {
				t.e.list.unblock();
			});
		};

		function _show_messages(m) {
			t.e.msgs.empty();
			if (typeof m == 'object' && typeof m.length == 'number')
				for (var i=0; i<m.length; i++)
					$('<div class="message">'+m[i]+'</div>').appendTo(t.e.msgs);
		};

		function _setup_elements() {
			if (typeof t.o.templates['waiting-list'] == 'string') {
				t.e.list = $(t.o.templates['waiting-list']).appendTo(t.o.ui.e.main);
				t.e.msgs = t.e.list.find('[rel="msgs"]');
				t.e.addform = t.e.list.find('[rel="add-me-form"]');
				t.e.addbtn = t.e.list.find('[rel="add-me"]');
				t.e.removeform = t.e.list.find('[rel="remove-me-form"]');
				t.e.curpos = t.e.list.find('[rel="curpos"]');
				t.e.curqty = t.e.list.find('[rel="curqty"]');
				t.e.removebtn = t.e.list.find('[rel="remove-me"]');
				_update_list_view(t.e.list.attr('pos'), t.e.list.attr('quantity'));
			}

			t.setup_events();
		};

		function _setup_events() {
			t.e.addbtn.unbind('click.waiting-list').bind('click.waiting-list', _click_add_me);
			t.e.removebtn.unbind('click.waiting-list').bind('click.waiting-list', _click_remove_me);
		};

		function _update_list_view(pos, quantity) {
			t.e.list.attr({pos:pos, quantity:quantity});
			t.e.curpos.text(pos+'');
			t.e.curqty.text(quantity+'');

			var pos = qt.toInt(pos);
			var quantity = qt.toInt(quantity);

			if (pos <= 0 || quantity <= 0) {
				t.e.addform.show();
				t.e.removeform.hide();
			} else {
				t.e.addform.hide();
				t.e.removeform.show();
			}
		};
	};

	wlist.start = function() {
		$(w).bind('qsot-sc-ui-loaded', function(ev, ui) {
			var exists = $(w).data('qsot-waiting-list');
			if (typeof exists != 'object' || exists == null) {
				var settings = $.extend({}, _qsot_waiting_list, {ui:ui});
				console.log('settings', settings);
				$(w).data('qsot-waiting-list', new wlist(settings));
			}
		});
	};

	return wlist;
})(jQuery, window, document);

QS.WaitingList.start();
