var _qsot_event_area_settings = _qsot_event_area_settings || {};
var QS = QS || {};
QS.EventAreaUICB = new QS.CB();

(function($, qt) {
	var S = $.extend({ ajaxurl:'/wp-admin/admin-ajax.php', nonce:false, venue_id:0 }, _qsot_event_area_settings),
			els = {}, next_id = -1;

	function aj(sa, data, func, efunc) {
		var data = $.extend({}, data, { action:'qsot-event-area', sa:sa, nonce:S.nonce, venue_id:$( '#post_ID' ).val(), check_venue_id:S.venue_id }),
				func = func || function(){},
				efunc = efunc || function(){};

		$.ajax({
			url: S.ajaxurl,
			data: data,
			type: 'POST',
			dataType: 'json',
			contentType: 'application/x-www-form-urlencoded',
			success: function(r) {
				if (typeof r == 'object') {
					if (typeof r.e != 'undefined') console.log('ajax error: ', r.e);
					func(r);
				} else { efunc(); }
			},
			error: efunc
		});
	}

	function refresh_event_areas() {
		els.mb.qsBlock();
		QS.EventAreaUICB.trigger( 'before-load', [] );
		aj(
			'load',
			{},
			function(r) {
				if (typeof r.list != 'object' || r.list.length == 0) {
					$(S.templates['no-areas']).appendTo(els.al.empty());
				} else {
					var i = 0;
					for (i; i<r.list.length; i++) {
						var item = $('<div class="item viewing" rel="item"></div>').appendTo(els.al),
								data = r.list[i], panels = {};
						panels.edit = $(_make_replacements(S.templates['edit-area'], { id:data.ID })).appendTo(item);
						panels.view = $(S.templates['view-area']).appendTo(item);
						item.data({ item:data, panels:panels });
						update_display(item);
					}
				}
				QS.EventAreaUICB.trigger( 'after-load', [ update_field, r, els.al ] );
				els.mb.qsUnblock();
			},
			function() { els.eaa.qsUnblock(); }
		);
	}

	function _make_replacements(str, data) {
		var i = '', v = '', res = str;
		for (i in data) {
			v = data[i];
			res = res.replace(new RegExp('\{\{'+i+'\}\}', 'g'), v);
		}
		return res;
	}

	function update_field(sel, item, val) {
		$(sel, item).each(function() {
			switch (this.tagName.toLowerCase()) {
				case 'input':
					switch ($(this).attr('type')) {
						case 'radio':
						case 'checkbox':
							if ($(this).val() == val) $(this).attr('checked', 'checked');
							else $(this).removeAttr('checked');
						break;

						default:
							$(this).val(val);
						break;
					}
				break;

				case 'textarea':
					$(this).val(val);
				break;

				case 'select':
					$(this).find('option').each(function() {
						if ($(this).val() == val) $(this).prop('selected', 'selected');
						else $(this).removeProp('selected');
					});
				break;

				default:
					$(this).html(val);
				break;
			}
		});
	}

	function update_display(item) {
		var data = item.data('item'),
				ticket = qt.is(S.tickets[data.meta._pricing_options])
					? S.tickets[data.meta._pricing_options].post
					: { ID:0, post_title:'(none)', meta:{ price:'<span class="amount">0</span>' } };
		update_field('[rel="area-id"]', item, data.ID);
		update_field('[rel="area-name"]', item, data.post_title);
		update_field('[rel="img-id"]', item, data.meta._thumbnail_id);
		update_field('[rel="capacity"]', item, data.meta._capacity);
		update_field('[rel="ttname"]', item, ticket.post_title);
		update_field('[rel="ttprice"]', item, ticket.meta.price);
		update_field('[rel="ttid"]', item, ticket.ID);
		$('[rel="img-wrap"]', item).each(function() {
			$(this).empty();
			var size = $(this).attr('size');
			if (!qt.is(size) || !qt.is(data.imgs[size])) size = 'thumb';
			if (data.imgs[size][0])
				$('<img src="'+data.imgs[size][0]+'" />').appendTo($(this).empty());
		});

		QS.EventAreaUICB.trigger( 'updated-display', [ update_field, item, data, ticket ] );
	}

	function toggle_edit_item(e) {
		e.preventDefault();
		var btn = $(this), item = btn.closest('[rel="item"]');
		if (item.hasClass('viewing')) {
			item.removeClass('viewing').addClass('editing');
			update_ticket_list(item, false);
		} else if (item.hasClass('adding')) {
			item.closest('[rel="item"]').remove();
		} else {
			item.removeClass('editing').addClass('viewing');
			update_ticket_list(item, true);
		}
		update_display(item);
		maybe_none_msg();
	}

	function save_item(e) {
		var t = this, btn = $(t), item = btn.closest('[rel="item"]'), data = item.louSerialize();
		item.qsBlock({ msg:'<h1>Saving...</h1>' });
		QS.EventAreaUICB.trigger( 'before-save', [ update_field, item, data ] );
		aj(
			'save-item',
			data,
			function (r) {
				if (qt.isO(r) && qt.is(r.items)) {
					var id = item.find('[rel="area-id"]').val();
					if (id && qt.isO(r.items[id+''])) {
						if (id != r.items[id+''].ID) {
							item.find('[name$="['+id+']"]').each(function() { $(this).attr('name', $(this).attr('name').replace(new RegExp('\\['+id+'\\]', 'g'), '['+r.items[id+''].ID+']')); });
							item.find('[for$="['+id+']"]').each(function() { $(this).attr('for', $(this).attr('for').replace(new RegExp('\\['+id+'\\]', 'g'), '['+r.items[id+''].ID+']')); });
						}
						item.data('item', r.items[id+'']);
						item.removeClass('adding').addClass('editing');
						toggle_edit_item.apply(t, [e]);
					}
				} else if (qt.isO(r) && qt.isO(r.e) && r.e.length) {
					var el = item.find('.edit [rel="error-list"]').empty();
					for (var i=0; i<r.e.length; i++) $('<div class="error">'+r.e[i]+'</div>').appendTo(el);
				} else {
					var el = item.find('.edit [rel="error-list"]').empty();
					$('<div class="error">An unknown error occured.</div>').appendTo(el);
				}
				QS.EventAreaUICB.trigger( 'after-save', [ update_field, r, item, data ] );
				item.qsUnblock();
			},
			function() {
				var el = item.find('.edit [rel="error-list"]').empty();
				$('<div class="error">An unknown error occured.</div>').appendTo(el);
				item.qsUnblock();
			}
		);
	}

	function del_item(e) {
		e.preventDefault();
		var item = $(this).closest('[rel="item"]'), iobj = item.data('item'), data = item.louSerialize();

		if (!qt.isO(iobj)) {
			alert('That event area is having issues. Try refreshing the page, and attempting to delete it again.');
			return;
		}

		if (confirm('Are you sure you want to delete the Event Area ['+iobj.post_title+'] ?')) {
			QS.EventAreaUICB.trigger( 'before-delete', [ update_field, item, data ] );
			aj('delete-item', data, function(r) {
				if (r.s) {
					alert('Successfully removed the event area ['+iobj.post_title+'].');
					item.remove();
				} else {
					alert('ERROR: There was a problem removing that event area.');
				}
				QS.EventAreaUICB.trigger( 'after-delete', [ update_field, item, data ] );
			});
		}
	}

	function add_item(e) {
		remove_none_msg();
		var item = $('<div class="item adding" rel="item"></div>').appendTo(els.al), panels = {}, data = { ID:next_id--, meta:{}, imgs:{ thumb:['', 0, 0] }, ticket:{ meta:{} } };
		panels.edit = $(_make_replacements(S.templates['edit-area'], { id:data.ID })).appendTo(item);
		panels.view = $(S.templates['view-area']).appendTo(item);
		panels.edit.find('input, select, textarea').removeAttr('disabled');
		item.data({ item:data, panels:panels });
		update_ticket_list(item, false);
		update_display(item);
	}

	function maybe_none_msg() {
		if (els.al.children('[rel="item"]').length == 0) $(S.templates['no-areas']).appendTo(els.al.empty());
	}

	function remove_none_msg() {
		els.al.find('.none-area').remove();
	}

	function setup_interface(uisel) {
		els.eaa = $(uisel);
		if (els.eaa.length) {
			els.mb = els.eaa.closest('.postbox');
			els.aui = $(S.templates['area-ui']).appendTo(els.eaa);
			els.al = els.aui.find('[rel="area-list"]');
			setup_events();
			refresh_event_areas();
		}
	}

	function enter_submit(e) {
		if (e.which == 13) {
			e.preventDefault();
			$(this).closest('[rel="item"]').find('.save-btn').trigger('click');
		}
	}

	function update_ticket_list(item, empty_list) {
		var empty_list = empty_list || false, list = $('[rel="ttid"]', item);
		if (list.length == 0) return;

		list.empty();
		if (!empty_list) {
			for (i in S.tickets) {
				$('<option value="'+S.tickets[i].post.ID+'">'+S.tickets[i].post.post_title+' ('+S.tickets[i].post.meta.price+')</options>').appendTo(list);
			}
		}
	}

	function stop_saving_post(e) {
		e.preventDefault();
	}

	function setup_events() {
		els.eaa.on('click', 'button', stop_saving_post);
		els.eaa.on('click', '[rel="add-btn"]', add_item);
		els.eaa.on('click', '[rel="edit-btn"]', toggle_edit_item);
		els.eaa.on('click', '[rel="del-btn"]', del_item);
		els.eaa.on('click', '[rel="save-btn"]', save_item);
		els.eaa.on('click', '[rel="cancel-btn"]', toggle_edit_item);
		els.eaa.on('keydown', '[rel="item"] input, [rel="item"] select, [rel="item"] textarea', enter_submit);
		els.eaa.on('click', '[rel="change-img"]', function(e) {
			QS.popMediaBox.apply(this, [e, {
				par: '[rel="item"]',
				id_field: '[rel="img-id"]',
				pc: '[rel="img-wrap"]'
			}]);
		});
	}

	$(function() {
		if (S.venue_id === false)
			S.venue_id = $('#post_ID').val();
		setup_interface('[rel="event-area-admin"]');
	});
})(jQuery, QS.Tools);
