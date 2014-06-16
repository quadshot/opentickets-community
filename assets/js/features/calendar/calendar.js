var QSEventsEventCalendar = (function($, w, d, undefined) {
	var _defs = {
		onSelection: false,
		calendarContainer: '.event-calendar'
	};

	function calendar(o) {
		var t = this;
		t.initialized = false;
		t.fix = {};
		t.o = $.extend({}, _defs, o, { author:'loushou', version:'0.1.0-beta' });
		_init();
		t.refresh = refresh;
		t.setUrlParams = _set_url_params;
		t._url_params = {};

		var gotoForm;

		function _init() {
			if (!t.initialized) {
				t.initialized = true;
				t.e = {
					m: $(t.o.calendarContainer)
				};
				if (t.e.m.length) {
					t.cal = t.e.m.fullCalendar({
						eventRender: _draw_event,
						eventSources: [
							{
								url: t.o.ajaxurl,
								data: _current_url_params
							}
						],
						eventClick: _click,
						viewDisplay: _header_render_hook,
						headerRender: _header_render,
						//eventAfterAllRender: _image_render_fix,
						loading: _loading
					});
					if (typeof t.o.gotoDate == 'object' && t.o.gotoDate != null) t.cal.fullCalendar('gotoDate', t.o.gotoDate);
				}
			}
		};

		function _current_url_params() {
			return t._url_params;
		};

		function _set_url_params(data) {
			t._url_params = data || {};
		};

		function _header_render(header, view) {
			var curDate = new XDate(this.getDate);
			_setup_goto_form(this, header.find('.fc-header-center'));
		};

		function _header_render_hook(view) {
			var header = $(view.element).closest('.fc').find('.fc-header');
			console.log('header hook', $.extend({}, $(view.element).closest('.fc')), header, $.extend({}, view));
			view.calendar.trigger('headerRender', view.calendar, header, view);
		};

		function _setup_goto_form(calendar, appendTo) {
			if (typeof gotoForm != 'object') {
				gotoForm = $('<div class="goto-form"></div>');
				var curDate = new XDate();
				var curY = curDate.getFullYear();
				var curM = curDate.getMonth();
				var yearSelect = $('<select rel="year" style="width:auto;"></select>').appendTo(gotoForm);
				for (var i=curDate.getFullYear()-5; i<=curDate.getFullYear()+15; i++) $('<option value="'+i+'"'+(curY == i ? ' selected="selected"' : '')+'>'+i+'</option>').appendTo(yearSelect);
				var monthSelect = $('<select rel="month" style="width:auto;"></select>').appendTo(gotoForm);
				for (var i=0; i<calendar.options.monthNames.length; i++) $('<option value="'+i+'"'+(curM == i ? ' selected="selected"' : '')+'>'+calendar.options.monthNames[i]+'</option>').appendTo(monthSelect);
				var btnClses = 'fc-button fc-button-today fc-state-default fc-corner-left fc-corner-right';
				var gotoBtn = $('<span rel="goto-btn" unselectable="on" style="-moz-user-select: none;" class="'+btnClses+'">Goto Month</span>').appendTo(gotoForm);
				gotoBtn.bind('click.goto-form', function(e) {
					e.preventDefault();
					var year = yearSelect.val();
					var month = monthSelect.val();
					calendar.gotoDate(year, month);
				}).hover(
					function() { $(this).addClass('fc-state-hover'); },
					function() { $(this).removeClass('fc-state-hover'); }
				);
			}
			gotoForm.appendTo(appendTo);
		};

		function _click(calEvent, e, view) {
			if (typeof t.o.onSelection == 'function') {
				t.o.onSelection(e, calEvent);
			}
		};

		function _loading(loading, view) {
			if (loading) {
				if (typeof t.e.loading != 'object') {
					t.e.loading = $('<div class="loading-overlay-wrap">').css({position:'absolute'}).appendTo('body');
					t.e._lol = $('<div class="loading-overlay"></div>').css({position:'absolute', 'top':0, left:0}).appendTo(t.e.loading);
					t.e._lmsg = $('<div class="loading-message">Loading...</div>').css({position:'absolute'}).appendTo(t.e.loading);

					var check = 0;
					var curVD = t.e.m.data('fullCalendar').options.viewDisplay;
					function _on_resize(ch, view) {
						if (ch == check) {
							var off = t.e.m.offset();
							var dims = {
								width: t.e.m.outerWidth(true),
								height: t.e.m.outerHeight(true)
							};
							t.e.loading.css($.extend({}, off, dims));
							t.e._lol.css(dims);
							var pos = {
								'top': parseInt((dims.height - t.e._lmsg.outerHeight())/2),
								left: parseInt((dims.width - t.e._lmsg.outerWidth())/2),
							};
							t.e._lmsg.css(pos);
							if (typeof view == 'object' && view != null) curVD(view);
						}
					};
					_on_resize(0);
					
					t.e.m.data('fullCalendar').options.viewDisplay = function(view) {
						check = Math.random()*100000;
						_on_resize(check, view);
					};
					$(window).bind('resize', function() { check = Math.random()*100000; _on_resize(check); });
				}
				t.e.loading.show();
			} else {
				t.e.loading.hide();
			}
		};

		function refresh(fullRender) {
			if (fullRender) t.e.m.fullCalendar('render');
			t.e.m.fullCalendar('rerenderEvents');
		};
		
		function _image_render_fix(view) {
			var key = view.name+'-'+view.title;
			if (!t.rerendered[key]) {
				t.rerendered[key] = true;
				setTimeout(function() {
					refresh();
				}, 500);
			}
		};

		function _draw_event(evt, ele, view) {
			var e = $(t.o.event_template);
			$('<span class="event-name">'+evt.title+'</span>').appendTo(e.find('.heading'));
			$('<span class="event-availability">Availability: '+evt['avail-words']+' ('+evt.available+')</span>').appendTo(e.find('.meta'));
			var img = $(evt.img).appendTo(e.find('.img'));
			var key = view.name+'-'+view.title;
			_image_load_trick(img.attr('src'), key, function() {
				t.e.m.fullCalendar('rerenderEvents');
			});
			e.appendTo(ele.find('.fc-event-inner').empty());
			if (evt.passed) ele.addClass('in-the-past');
		};

		function _image_load_trick(imgsrc, primary_key, func) {
			if (typeof t.fix[primary_key] != 'object') t.fix[primary_key] = {};
			if (typeof imgsrc == 'string' && typeof t.fix[primary_key][imgsrc] != 'number') {
				t.fix[primary_key][imgsrc] = 0;
				var img = new Image();
				img.onload = function() {
					var loaded = true;
					t.fix[primary_key][imgsrc] = 1;
					for (i in t.fix[primary_key]) if (t.fix[primary_key].hasOwnProperty(i)) {
						if (t.fix[primary_key][i] != 1) {
							loaded = false;
						}
					}
					if (loaded && typeof func == 'function') {
						func();
					}
				};
				img.src = imgsrc;
			}
		};
	};

	return calendar;
})(jQuery, window, document);

jQuery(function($) {
	var cal = new QSEventsEventCalendar(_qsot_event_calendar_ui_settings);
});
