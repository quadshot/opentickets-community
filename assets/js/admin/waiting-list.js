QS.AdminWaitingListUserSelector = (function($, undefined) {
	var qt = QS.Tools;
	var cb = QS.CB;

	function awlus(s) {
		var p = this;
		var t = p.add_user = {};

		t.initialized = false;
		t.o = {};
		t.e = {};
		t.actions = {
			cancel: function() {},
			add: function() {}
		};
		t.ran = false;
		t.init = _init;
		t.setSettings = _set_settings;

		t.init(s);

		function _trigger_dialog(e, actions) {
			e.preventDefault();
			t.ran = false;
			if (typeof actions == 'object') t.actions = $.extend(t.actions, actions);
			t.e.dia.dialog('open');
		};

		function _init(settings) {
			if (!t.initialized) {
				t.setSettings(settings);
				_setup_elements();
				_setup_events();
				t.initialized = true;
				console.log(t, p);
			}
		};

		function _set_settings(settings) {
			t.o = $.extend({ title:'Add User' }, t.o, settings, {author:'loushou', version:'0.1-lou'});
		};

		function _setup_elements() {
			t.e.dia = $(t.o.templates['add-user-dialog']).hide().appendTo('body').dialog({
				autoOpen: false,
				appendTo: 'body',
				modal: true,
				title: t.o.title,
				width: 400,
				height: 500,
				position: {
					my: 'top',
					at: 'top+50',
					of: window
				},
				close: function(e, ui) { if (!t.ran) t.actions.cancel(e, $(this), ui); },
				buttons: {
					'Cancel': function(e, ui) { t.e.dia.dialog('close'); },
					'Add': function(e, ui) { t.ran = true; t.actions.add(e, $(this), ui); t.e.dia.dialog('close'); }
				}
			});
		};

		function _setup_events() {
			p.e.main.bind('add-user', _trigger_dialog);
			t.e.dia.find('select.ajax_chosen_select_customer').ajaxChosen({
				method:'GET',
				url:ajaxurl,
				dataType:'json',
				afterTypeDelay:100,
				minTermLength:2,
				data: {
					action:'woocommerce_json_search_customers',
					'default':'[no user]',
					security:t.o.security
				}
			}, function(data) {
				var terms = {};
				$.each(data, function(i, val) { terms[i] = val });
				return terms;
			});
		};
	};

	qt.start(awlus, 'qsawlus');

	return awlus;
})(jQuery);

QS.AdminWaitingList = (function($, undefined) {
	var qt = QS.Tools;
	var cb = QS.CB;

	function awl(s) {
		var t = this;

		t.na = false;
		t.initialized = false;
		t.currentEid = 0;
		t.currentList = [];
		t.o = {};
		t.e = {};
		t.init = _init;
		t.setSettings = _set_settings;

		t.init(s);
		QS.AdminWaitingListUserSelector.apply(t, [s.adduser || {}]);

		function _change_waiting_list(e) {
			console.log(t.e);
			t.e.main.block();

			t.e.lwrap.empty();
			var eid = t.e.elist.val();
			t.currentEid = eid;
			var ename = $(this).find('option[value="'+eid+'"]').text();
			t.currentList = typeof t.o.lists[eid+''] == 'object' ? t.o.lists[eid+''] : [];
			t.o.nomsg = '<p>There are no partons on the waiting list for event ['+ename+'].</p>';
			_populate_list(t.currentList, t.o.nomsg);

			t.e.main.unblock();
		};

		function _add_waiting_list_item(e) {
			t.e.main.trigger('add-user', [{add:_add_user_success, cancel:_add_user_cancel}]);
		};

		function _ajax(data, success, failure) {
			var data = data || {};
			data.action = 'qsot-waiting-list';
			data.eid = t.currentEid;

			$.ajax({
				url: ajaxurl,
				dataType: 'json',
				success: success,
				error: failure,
				data: data,
				type: 'post'
			});
		};

		function _add_user_success(e, dia, ui) {
			e.preventDefault();

			data = $(dia).louSerialize();
			data.sa = 'add';

			t.e.main.block({ message:'One moment please...', overlayCSS:{ backgroundColor:'#ffffff' } });

			_ajax(data, function(r) {
				if (typeof r == 'object' && r.s) {
					t.currentList.push({u:data.uid, q:data.q});
					t.o.users[r.uid] = r.u;
					t.e.elist.change();
					console.log(data, t);
				}
				t.e.main.unblock();
			}, function() {
				t.e.main.unblock();
			});
		};

		function _add_user_cancel(e, dia, ui) {
			e.preventDefault();
		}

		function _remove_waiting_list_item(e) {
			e.preventDefault();
			_clear_errors();
			var self = $(this);
			var li = self.closest('li');
			var item = li.data('qsawl-item');

			var res = confirm('Are you sure you wish to remove '+t.o.users[item['u']].name+' from the list?');

			if (res) {
				t.e.main.block({ message:'One moment please...', overlayCSS:{ backgroundColor:'#ffffff' } });

				function _error() { t.e.main.unblock(); }
				function _success(r) {
					if (typeof r == 'object' && r.s) {
						_remove_from_stored_list(item);
						t.e.main.unblock();
						_populate_list(t.currentList, t.o.nomsg);
					}
					if (typeof r.e == 'object' && r.e.length) {
						_draw_errors(r.e);
					}
				};

				var data = {
					action: 'qsot-waiting-list',
					sa: 'remove',
					eid: t.currentEid,
					u: item['u'],
					q: item['q']
				};

				$.ajax({
					url: ajaxurl,
					data: data,
					dataType: 'json',
					error: _error,
					success: _success,
					type: 'POST'
				});
			}
		};

		function _remove_from_stored_list(item) {
			var shift = 0;
			for (var i=0; i<t.currentList.length; i++) {
				if (t.currentList[i]['u'] == item['u']) {
					shift++;
				} else {
					t.currentList[i-shift] = t.currentList[i];
				}
			}
			for (var i=0; i<shift; i++) t.currentList.pop();
		};

		function _clear_errors() {
			t.e.msgs.empty();
		};

		function _draw_errors(list) {
			for (var i=0; i<list.length; i++) {
				$('<div class="err-msg">'+list[i]+'</div>').css({ border:'1px solid #880000', backgroundColor:'#ffeeee', color:'#880000' });
			}
		};

		function _populate_list(list, nonemsg) {
			t.e.lwrap.empty();
			var ul = $('<ul class="item-list" rel="item-list"></ul>');
			var cnt = 0;
			for (i in list) if (list.hasOwnProperty(i)) {
				var item = list[i];
				if (typeof item['u'] != 'undefined' && item['u'] != null) {
					var user = t.o.users[item['u']];
					var qty = item['q'];
					var link = '<a href="'+user.link+'" target="_blank">'+user.name+'</a>';
					var rem = '<a href="#" class="remove" rel="remove">X</a>';
					$('<li class="item" rel="item">['+qty+'] - '+link+' '+rem+'</li>').appendTo(ul).data('qsawl-item', item);
					cnt++;
				}
			}
			if (cnt) ul.appendTo(t.e.lwrap);
			else $(nonemsg).appendTo(t.e.lwrap);
		};

		function _init(settings) {
			if (!t.na && !t.initialized) {
				_set_settings(settings);
				_setup_elements();
				if (t.e.main.length) {
					console.log(t.e, t.o);
					_setup_events();
					t.initialized = true;
				} else t.na = true;
			}
		};

		function _set_settings(settings) {
			t.o = $.extend({nomsg:'<p>There are no partons on the waiting list.</p>'}, t.o, settings, {author:'loushou', version:'0.1-beta'});
		};

		function _setup_elements() {
			t.e.main = $('#waiting-list-div');
			t.e.inside = t.e.main.find('.inside:eq(0)');
			t.e.lwrap = t.e.inside.find('.waiting-list-wrap');
			t.e.elist = t.e.inside.find('.event-list');
			t.e.msgs = t.e.inside.find('.waiting-list-msgs');

			if (t.e.elist.length && t.o.events) {
				for (var i=0; i<t.o.events.length; i++) {
					$('<option value="'+t.o.events[i].id+'">'+t.o.events[i].name+'</option>').appendTo(t.e.elist);
				}
			}
		};

		function _setup_events() {
			if (t.e.elist.length) {
				t.e.elist.change(_change_waiting_list);
				if (t.e.elist.length && qt.toInt(t.o['parent']) == 0 && typeof t.o.lists == 'object') {
					t.e.elist.change();
				}
			} else {
				t.currentEid = $('#post_ID').val();
				t.currentList = t.o.list;
			}

			if (qt.toInt(t.o['parent']) != 0) {
				_populate_list(t.currentList, t.o.nomsg);
			}

			t.e.main.on('click', '.item-list .item .remove', _remove_waiting_list_item);
			t.e.main.on('click', '[rel="actions"] [rel="add-user"]', _add_waiting_list_item);
		};
	}

	awl.callbacks = new cb(awl);

	awl.start = function(settings) {
		var exists = $(window).data('qsawl');
		if (typeof exists != 'object' || exists == null) {
			exists = new awl(settings);
			$(window).data('qsawl', exists);
		} else {
			exists.setSettings(settings);
		}
		return exists;
	};

	return awl;
})(jQuery);

QS.AdminWaitingListSeatingReport = (function($, undefined) {
	var qt = QS.Tools;
	var cb = QS.CB;

	function awlsr(s) {
		var t = this;
		var s = s || {};

		t.initialized = t.na = false;
		t.o = {};
		t.e = {};
		t.init = _init;
		t.setSettings = _set_settings;

		t.init(s);
		QS.AdminWaitingListUserSelector.apply(t, [s.adduser || {}]);

		function _ajax(data, success, failure) {
			var data = data || {};
			data.action = 'qsot-waiting-list';
			data.eid = t.e.main.find('#waiting-list-wrap').attr('event-id');

			$.ajax({
				url: ajaxurl,
				dataType: 'html',
				success: success,
				error: failure,
				data: data,
				type: 'post'
			});
		};

		function _add_item(e) {
			e.preventDefault();

			function _add_user_success(e, dia, ui) {
				data = $(dia).louSerialize();
				data.sa = 'sradd';

				t.e.main.find('#waiting-list-wrap').block({ overlayCSS:{ background:'#ffffff' }, message:'<h1>Updating...</h1>' });

				function success(r) {
					t.e.main.find('#waiting-list-wrap').empty().unblock();
					$(r).appendTo(t.e.main.find('#waiting-list-wrap'));
				};

				function failure() {
					t.e.main.find('#waiting-list-wrap').unblock();
					t.e.main.trigger('clear-wait-list-msg').trigger('add-wait-list-msg', ['Problem occurred when trying to update the list.']);
				};

				_ajax(data, success, failure);
			};

			function _add_user_cancel(e, dia, ui) { }; 
			t.e.main.trigger('add-user', [{add:_add_user_success, cancel:_add_user_cancel}]);
		};

		function _remove_item(e) {
			e.preventDefault();

			var btn = $(this);
			var scope = btn.closest(btn.attr('scope'));
			var uid = scope.attr('user');
			var chk = scope.attr('chk');
			var fullname = scope.attr('username');

			var sure = confirm('Are you sure you want to remove ['+fullname+'] from the waiting list?');
			if (!sure) return;
			
			var data = {
				sa: 'srremove',
				uid: uid,
				chk: chk
			};

			t.e.main.find('#waiting-list-wrap').block({ overlayCSS:{ background:'#ffffff' }, message:'<h1>Updating...</h1>' });

			function success(r) {
				t.e.main.find('#waiting-list-wrap').empty().unblock();
				$(r).appendTo(t.e.main.find('#waiting-list-wrap'));
			};

			function failure() {
				t.e.main.find('#waiting-list-wrap').unblock();
				t.e.main.trigger('add-wait-list-msg', ['Problem occurred when trying to update the list.']);
			};

			_ajax(data, success, failure);
		};

		function _clear_msg(e) {
			e.preventDefault();
			t.e.main.find('#waiting-list-wrap .messages').empty();
		};

		function _add_msg(e, m) {
			e.preventDefault();
			if (typeof m == 'string') {
				$('<div class="errmsg">- '+m+'</div>').appendTo(t.e.main.find('#waiting-list-wrap .messages'));
			} else if (typeof m == 'object' && m.length) {
				for (var i=0; i<m.length; i++)
					$('<div class="errmsg">- '+m[i]+'</div>').appendTo(t.e.main.find('#waiting-list-wrap .messages'));
			}
		};

		function _init(settings) {
			if (!t.na && !t.initialized) {
				_set_settings(settings);
				_setup_elements();
				if (t.e.main.length) {
					_setup_events();
					t.initialized = true;
				} else t.na = true;
			}
		};

		function _set_settings(settings) {
			t.o = $.extend({}, t.o, settings, {author:'loushou', version:'0.1-beta'});
		};

		function _setup_elements() {
			t.e.main = $('#report_result');
		};

		function _setup_events() {
			t.e.main.on('click', '#waiting-list-wrap [rel="actions"] [rel="add-item"]', _add_item);
			t.e.main.on('click', '#waiting-list-wrap [rel="list"] .action[rel="remove"]', _remove_item);
			t.e.main.on('clear-wait-list-msg', _clear_msg);
			t.e.main.on('add-wait-list-msg', _add_msg);
		};
	}

	awlsr.callbacks = new cb(awlsr);

	awlsr.start = function(settings) {
		var exists = $(window).data('qsawlsr');
		if (typeof exists != 'object' || exists == null) {
			exists = new awlsr(settings);
			$(window).data('qsawlsr', exists);
		} else {
			exists.setSettings(settings);
		}
		return exists;
	};

	return awlsr;
})(jQuery);

jQuery(function($) {
	if (typeof _qsot_waiting_list_settings == 'object') QS.AdminWaitingList.start(_qsot_waiting_list_settings);
	if (typeof _qsot_sr_waiting_list_settings == 'object') QS.AdminWaitingListSeatingReport.start(_qsot_sr_waiting_list_settings);
});
