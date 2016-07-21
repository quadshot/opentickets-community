var QS = QS || {};
QS.EventUI = (function($, undefined) {
	var qt = QS.Tools,
			S = $.extend( true, { frmts:{}, tz:moment.tz.guess() }, _qsot_event_ui_settings ),
			new_post_id = -1;

	console.log( 'timezone', _qsot_event_ui_settings, S );
	// set the default timezone for momentjs
	moment.tz.setDefault( S.tz );

	function frmt( str ) {
		return ( 'string' == typeof str && qt.is( S.frmts[ str ] ) ) ? S.frmts[ str ] : str;
	}

	function NewEventDateTimeForm() {
		var t = this;

		if (!t.calendar) return;

		t.form = {};
		t.callback('add_repeat_functions');
		t.elements = t.elements || {};
		t.elements.form = t.elements.form || {};
		t.elements.form.main_form = $('.option-sub[rel=add]', t.elements.main || 'body');
		t.elements.form.add_btn = $('[rel=add-btn]', t.elements.form.main_form);
		t.elements.form.messages = $('[rel=messages]', t.elements.form.main_from);
		t.elements.form.start_date = $('input[name="start-date"]', t.elements.form.main_form);
		t.elements.form.end_date = $('input[name="end-date"]', t.elements.form.main_form);
		t.elements.form.starts_on = $('input[name="repeat-starts"]', t.elements.form.main_form);
		t.elements.form.ends_on = $('input[name="repeat-ends-on"]', t.elements.form.main_form);
		t.elements.form.start_date_display = $('input[name="start-date-display"]', t.elements.form.main_form);
		t.elements.form.end_date_display = $('input[name="end-date-display"]', t.elements.form.main_form);
		t.elements.form.starts_on_display = $('input[name="repeat-starts-display"]', t.elements.form.main_form);
		t.elements.form.ends_on_display = $('input[name="repeat-ends-on-display"]', t.elements.form.main_form);

		var current = undefined;
		$(window).on( 'scroll', function(e) {
			var last = (new Date()).getTime() + ' ' + (Math.random() * 10000);
			current = last;
			setTimeout( function() {
				if ( last != current ) return;
				var wintop = $( window ).scrollTop(), opt = $( '.option-sub[rel="settings"]', t.elements.main || 'body' ), opttop = opt.offset().top, opthei = opt.outerHeight(),
				    bulk = opt.find( '.bulk-edit-settings' ), bulkhei = bulk.outerHeight(), bump = 100, off = 10;
				if ( wintop > opttop - bump && wintop < opttop + opthei - bulkhei - bump )
					bulk.finish().animate( { top:wintop - opttop + bump + off }, { duration:500 } );
				else if ( wintop < opttop - bump )
					bulk.finish().animate( { top:off }, { duration:500 } );
				else if ( wintop > opttop + opthei - bulkhei - bump )
					bulk.finish().animate( { top:opthei - bulkhei }, { duration:500 } );
			}, 100 );
		} );

		// when the start date selection changes, we need to update the end date, date range start on date, and date range end on date selection to be at least that date
		t.elements.form.start_date_display.bind( 'change', function() {
			var val = t.elements.form.start_date.val(), // actual date value
					disp_val = t.elements.form.start_date_display.val(), // displayed daet value
					cur = new moment( val ), // moment object of selected date
					end = new moment( t.elements.form.end_date.val() ), // moment object of the current selected end date
					starton = new moment( t.elements.form.starts_on.val() ), // start on date range
					endon = new moment( t.elements.form.ends_on.val() );// end on date range

			// update the end date if it is earlier than the new start date
			if ( cur.diff( end, 's' ) > 0 ) {
				t.elements.form.end_date_display.val( disp_val )
				t.elements.form.end_date.val( val );
			}

			// update the date range start on date, if before new start date
			if ( cur.diff( starton, 's' ) > 0 ) {
				t.elements.form.starts_on_display.val( disp_val )
				t.elements.form.starts_on.val( val );
			}

			// update the date range end on date, if before the new start date
			if ( cur.diff( endon ) > 0 ) {
				t.elements.form.ends_on_display.val( disp_val );
				t.elements.form.ends_on.val( val );
			}
		} );

		if (typeof t.callback != 'function') {
			t.callback = function(name, params) {
				var params = params || [];
				var cbs = EventUI.callbacks.get(name);
				if (cbs instanceof Array) {
					for (var i=0; i<cbs.length; i++)
						cbs[i].apply(t, params);
				}
			};
		}

		t.elements.form.add_btn.click(function(e) {
			e.preventDefault();
			var data = t.elements.form.main_form.louSerialize();
			data['title'] = $('[name=post_title]').val();
			t.callback('form_add_btn_data', [data, t.elements.form.main_form, t.elements.form.add_btn]);
			t.form.processAddDateTimes(data);
		});

		function pl( n, p ) { return qt.toFloat( n ).toFixed( p ); };
		function dig( n, w, p ) { p = p || '0'; n = n + ''; return n.length >= w ? n : ( new Array( w - n.length + 1 ) ).join( p ) + n; };

		function normalize_time( str ) {
			var matches = str.toLowerCase().match( /^(\d{1,2})(:(\d{1,2})(:(\d{1,2}))?)?([pa]m?)?$/ ),
					res = matches
						? {
							hour: qt.toInt( matches[1] ),
							min: qt.toInt( matches[3] ),
							sec: qt.toInt( matches[5] ),
							mer: qt.is( matches[6] ) ? matches[6].toLowerCase().substr( 0, 1 ) : ''
						}
						: {
							hour: 0,
							min: 0,
							sec: 0,
							mer: ''
						};
			// adjust the hour to be 24 hour time
			if ( 'p' == res.mer && res.hour < 12 )
				res.hour += 12;
			else if ( 'a' == res.mer && 12 == res.hour )
				res.hour = 0;
			return qt.dig( res.hour, 2 ) + ':' + qt.dig( res.min, 2 ) + ':' + qt.dig( res.sec, 2 );
		}

		t.form.processAddDateTimes = function(data) {
			if (typeof t.addEvents != 'function') return;

			var current_dt = new moment();
			var data = $.extend({
				'start-time': '00:00:00',
				'start-date': current_dt.format( frmt( 'MM-DD-YYYY' ) ),
				'end-time': '23:59:59',
				'end-date': current_dt.format( frmt( 'MM-DD-YYYY' ) ),
			}, data);

			data['start-time'] = normalize_time( data['start-time'] );
			data['end-time'] = normalize_time( data['end-time'] );

			var base = {
				start: new moment( data['start-date'] + ' ' + data['start-time'] ).toDate(),
				end: new moment( data['end-date'] + ' ' + data['end-time'] ).toDate(),
				title: data['title'],
				allDay: false,
				editable: true
			};
			var events = [];

			if ( qt.is( data.repeat ) ) {
				var funcName = 'repeat' + QS.ucFirst( data.repeats );
				if ( qt.isF( t.form[ funcName ] ) )
					t.form[ funcName ]( events, base, data );
				else
					t.callback( funcName, [ events, base, data ] );
			} else {
				events.push( $.extend( true, {}, base, {
					single: 'yes',
					start: ( moment( data['start-date'] + ' ' + data['start-time'] ) ).format( moment.defaultFormat ),
					end: ( moment( data['end-date'] + ' ' + data['end-time'] ) ).format( moment.defaultFormat )
				} ) );
			}

			t.callback( 'process_add_date_time', [ events, base, data ] );
			var cnt = events.length;
			t.addEvents( events );

			var msg = $( '<li><strong>Added</strong> [<strong>' + cnt + '</strong>] events to the calendar below.</li>' )
					.appendTo( t.elements.form.messages );
			t.callback( 'process_add_date_time_msgs', [ msg ] );
			msg.show().fadeOut( {
				duration: 3000,
				complete: function() { msg.remove(); }
			} );
		};

		function evenDays(from, to) {
			var f = moment( { year:from.year(), month:from.month(), day:from.date() } ),
					o = moment( { year:to.year(), month:to.month(), day:to.date() } );
			return o.diff( f, 'd' );
		};

		// calculate all the repeats, for a weekly repeat
		t.form.repeatWeekly = function( events, base, data ) {
			var d = moment( data['repeat-starts'] ),
					st = moment( base['start'] ),
					en = moment( base['end'] ),
					st_en_diff = en.diff( st, 's' ),
					cnt = 0,
					inRange = function() { return false; };

			// figure out the function that determins if a given day should have an even on it
			switch ( data['repeat-ends-type'] ) {
				case 'on':
					inRange = ( function() {
						var e = moment( data['repeat-ends-on'] );
						return function() { return d.unix() <= e.unix(); };
					} )(); break;
				case 'after':
					inRange = ( function() {
						var e = data['repeat-ends-after'];
						return function() { return cnt < e; };
					} )(); break;
				default:
					// pass params as an object, so that the function inRange can be modified by callbacks, and then returned by reference. that way we can actually accept the changed function
					var pkg = {
						inRange:inRange,
						data:data
					};
					t.callback('repeat_ends_type', [pkg]);
					inRange = pkg.inRange;
				break;
			}

			function incWeeks() { d.add( -d.day(), 'd' ).add( data['repeat-every'], 'w' ); }
			function nextDay( day ) {
				var c = d.day();
				if ( day < c ) return -1;
				d.add( day - c, 'd' );
				return 1;
			}

			if ( qt.isO( data['repeat-on'] ) && Object.keys( data['repeat-on'] ).length ) {
				// while we are still in range of the maximal event date
				while ( inRange() ) {
					// for each day of the week we need to repeat on
					for ( i in data['repeat-on'] ) {
						// initial run, in case first day is in middle of list, skip ahead to the first day. (ex: list 'm', 'tu', 'th', 'sa' and first day is 'th')
						if ( nextDay( data['repeat-on'][ i ] ) < 0 )
							continue;

						// if we are outside the desired range, then end our loop
						if ( ! inRange() )
							break;

						// create a new copy of the base event data
						var args = $.extend( true, {}, base ),
								// copy the start date, and add a number of days to it equal to the distance to the current day of this loop
								st = st.clone().add( evenDays( st, d ), 'd' );

						// create the start and end dates for this event
						var sigh = st.clone();
						args['start'] = sigh.clone().toDate();
						sigh = sigh.add( st_en_diff, 's' );
						args['end'] = sigh.clone().toDate();

						// push the event to the event list
						events.push( args );

						// keep track of how many events we have created, in case this is a quantity limited loop
						cnt++;
					}
					incWeeks();
				}
			}

			return events;
		};

		t.form.repeatDaily = function(events, base, data) {
		};
	}

	function EventList() {
		var t = this;

		if (!t.calendar) return;
		if (!t.elements.event_list || t.elements.event_list.length == 0) return;

		t.elements.bulk_edit = {
			settings_form: $('.bulk-edit-settings', t.elements.main)
		};
		t.event_list = {};
		t.last_clicked = undefined;
		t.selection = $();

		if (typeof t.callback != 'function') {
			t.callback = function(name, params) {
				var params = params || [];
				var cbs = EventUI.callbacks.get(name);
				if (cbs instanceof Array) {
					for (var i=0; i<cbs.length; i++)
						cbs[i].apply(t, params);
				}
			};
		}

		t.event_list.updateSettingsForm = function() {
			// SAVE EVENT SETTINGS FROM FORM
			t.elements.bulk_edit.settings_form.unbind('updated.update-settings').bind('updated.update-settings', function(e, data) {
				var selected = t.event_list.getSelection();

				selected.each(function() {
					var ev = $(this).data('event');
					for (i in data) {
						if ( !( typeof data[i].isMultiple && data[i].isMultiple ) && data[i] !== '' ) {
							ev[i] = data[i];
						}
					}
				});

				t.calendar.fullCalendar('refetchEvents');
			}).trigger('clear');

			var selected = t.event_list.getSelection();

			function Multiple() {
				this.toString = function() { return ''; };
				this.toLabel = function() { return '(Multiple)'; };
				this.isMultiple = true;
			};

			if (selected.length) {
				var settings = {};

				selected.each(function() {
					var ev = $(this).data('event');
					if (typeof ev == 'object') {
						for (i in ev) {
							if ( i == 'source' ) continue;
							var val = ev[i];
							if (typeof settings[i] != 'undefined') {
								if (settings[i] != val) settings[i] = new Multiple();
							} else {
								settings[i] = typeof val != 'undefined' ? val : '';
							}
						}
					}
				});

				var i = undefined;
				for (i in settings) {
					var field = t.elements.bulk_edit.settings_form.find('[name="settings['+i+']"]');
					if (field.length > 0) {
						var setting_main = field.closest(field.attr('scope') || 'body');
						if (setting_main.length > 0) {
							var updateArgs = {};
							updateArgs[i] = settings[i];
							setting_main.qsEditSetting('update', settings, false);
							setting_main.qsEditSetting('update', updateArgs, true);
						}
					}
				}

				t.elements.bulk_edit.settings_form.show();
				t.callback('update_settings_form_show');
			} else {
				t.callback('update_settings_form_hide');
				$('[rel=value]', t.elements.bulk_edit.settings_form).val('');
				$('[rel=display]', t.elements.bulk_edit.settings_form).html('');
				t.elements.bulk_edit.settings_form.hide();
				$('[rel=form]', t.elements.bulk_edit.settings_form).hide();
				$('[rel=edit]', t.elements.bulk_edit.settings_form).show();
			}
		}

		t.event_list.getSelection = function() {
			return t.selection; //$('.event-date.selected', t.elements.event_list);
		};

		function highlight(e) {
			var self = $(this);

			if (e.shiftKey && t.last_clicked && t.last_clicked.length > 0 && !t.last_clicked.equals(self)) {
				var p = self.prevAll('.event-date');
				var pl = t.last_clicked.prevAll('.event-date');
				var list = $(self).add(t.last_clicked);
				if (p.length < pl.length) list = self.parent().find('.event-date').slice(p.length, pl.length+1);
				else list = self.parent().find('.event-date').slice(pl.length, p.length+1);
				t.selection = list;
			} else if (e.metaKey || e.ctrlKey) {
				if (t.selection.filter(function() { return $(this).equals(self); }).length > 0) {
					t.selection = t.selection.filter(function() { return !$(this).equals(self); });
				} else {
					t.selection = t.selection.add(self);
				}
			} else {
				if (t.last_clicked && t.last_clicked.length > 0 && t.last_clicked.equals(self) && t.selection.length == 1) {
					if (self.hasClass('selected')) { 
						t.selection = t.selection.filter(function() { return !$(this).equals(self); });
					} else {
						t.selection.add(self);
					}
				} else {
					t.selection = self;
					t.last_clicked = self;
				}
			}

			self.siblings('.event-date').addBack().removeClass('selected');
			t.selection.addClass('selected');

			t.event_list.updateSettingsForm();
		};

		// remove an item from the calendar, based on the clicking of the red X
		function remove(e) {
			e.preventDefault();
			var self = $( this ),
					scope = self.closest( '[rel=item]' ),
					ev = scope.data( 'event' );
			scope.remove();
			t.removeEvents(ev);
		};

		t.event_list.add_item = function(ev) {
			var d = moment(ev.start);
			var extra = [];
			if (typeof ev.edit_link == 'string' && ev.edit_link.length) 
				extra.push('<div class="edit action"><a href="'+ev.edit_link+'" target="_blank" rel="edit" title="Edit Event">E</a></div>');
			if (typeof ev.view_link == 'string' && ev.view_link.length) 
				extra.push('<div class="view action"><a href="'+ev.view_link+'" target="_blank" rel="edit" title="View Event">V</a></div>');
			var ele = $('<div class="event-date" rel="item">'
					+'<div class="event-title">'
						+'<span>'+d.format( frmt( 'hh:mma' ) )+' on '+d.format( frmt( 'ddd MM-DD-YYYY' ) )+' ('+ev.title+')</span>'
						+'<div class="actions">'
							+extra.join('')
							+'<div class="remove action" rel="remove">X</div>'
						+'</div>'
					+'</div>'
				+'</div>').data('event', ev).click(highlight);
			ele.find( '[rel=remove]' ).click( remove );
			t.callback('event_list_item', [ele]);
			if (ele.length)
				ele.appendTo(t.elements.event_list);
		};
	}

	function startEventUI(e, o) {
		var e = $(e);
		var exists = e.data('qsot-event-ui');
		var ret = undefined;

		if (exists instanceof EventUI && typeof exists.initialized == 'boolean' && exists.initialized) {
			exists.setOptions(o);
			ret = exists;
		} else {
			ret = new EventUI(e, o);
			e.data('qsot-event-ui', ret);
		}

		return ret;
	}

	function EventUI(e, o) {
		this.first = moment();
		this.setOptions(o);
		this.loadSettings();
		this.elements = {
			main:e,
			calendar:e.find('[rel=calendar]'),
			event_list:e.find('[rel=event-list]'),
			buttons:{}
		};
    this.elements.postbox = this.elements.calendar.closest( '.postbox' );

    // fix for 'locked event settings box' people are reporting
    if ( this.elements.postbox.hasClass( 'closed' ) ) { 
      this.elements.postbox.removeClass( 'closed' );
      this.init();
      this.elements.postbox.addClass( 'closed' );
    } else {
      this.init();
    }   
	};

	EventUI.prototype = {
		defs: {
			evBgColor:'#000000',
			evFgColor:'#ffffff'
		},
		fctm:'fc',
		calendar:undefined,
		events:[],
		first:false,
		initialized:false,

		init: function() {
			var self = this;

			this.calendar = this.elements.calendar.fullCalendar({
				header: {
					left: 'title agendaWeek,month',
					center: '',
					right: 'today prev,next'
				},
				eventAfterRender: function(ev, element, view) { return self.calendarAfterRender(ev, element, view) },
				eventRender: function(ev, element, view) { var args = Array.prototype.slice.call(arguments); args.push(this); return self.eventRender.apply(self, args); },
				eventDrop: function(ev, day, min, allDay, revertFunc, jsEv, ui, view) { var args = Array.prototype.slice.call(arguments); args.push(this); return self.eventDrop.apply(self, args); },
				viewRender: function(  view, view_element ) { return self.addButtons( view ); }
			});
			this.calendar.fullCalendar('gotoDate', this.first.toDate());

			// setup the event source
			this.event_source = {
				events: this.events,
				color: this.options.evBgColor,
				textColor: this.options.evFgColor
			};

			// update the calendar with the events
			this.updateSources();

			// import
			NewEventDateTimeForm.call(this);
			EventList.call(this);

			this.calendar.closest('form').on('submit', function(e) {
				return self.beforeFormSubmit($(this));
			});

			this.updateEventList();

			this.callback('init');
			this.initialized = true;
		},

		// update the event sources for the calendar
		updateSources: function() {
			this.calendar.fullCalendar( 'removeEventSource', this.event_source );
			this.calendar.fullCalendar( 'addEventSource', this.event_source );
		},

		eventRender: function(ev, element, view, that) {
			var self = this;
			function _toNum(data) { var d = parseInt(data); return isNaN(d) ? 0 : d; };

			var tmpl = this.template(['render_event_'+view.name, 'render_event']);

			if (tmpl) {
				if (typeof tmpl == 'function') tmpl = tmpl();
				else tmpl = $(tmpl);
				var item = tmpl.clone();
				item.find('.'+self.fctm+'-event-time').html(element.find('.'+self.fctm+'-event-time').html());
				item.find('.'+self.fctm+'-event-title').html(element.find('.'+self.fctm+'-event-title').html());
				item.find('.'+self.fctm+'-capacity').html('('+_toNum(ev.capacity)+')');
				item.find('.'+self.fctm+'-visibility').html('['+QS.ucFirst(ev.visibility)+']');
				item.find( '[rel=remove]' ).data( 'event', ev ).click( function() { self.removeEvents( [ $( this ).data( 'event' ) ] ); } );
				element.empty();
				item.appendTo(element);
			} else {
				$('<span class="'+self.fctm+'-capacity"> ('+_toNum(ev.capacity)+') </span>')
					.insertBefore(element.find('.'+self.fctm+'-event-title'));
			}

			this.callback('render_event', [ev, element, view, that]);
		},

		eventDrop: function(ev, day, min, allDay, revertFunc, jsEv, ui, view, that) {
			this.updateEventList();
			this.callback('drop_event', [ev, day, min, allDay, revertFunc, jsEv, ui, view, that]);
		},

		// add a list of events to the global list of events used by the calendar and event list ui
		addEvents: function( events ) {
			// if there are events to add
			if ( qt.isA( events ) ) {
				// add each event
				for ( var i = 0; i < events.length; i++ )
					this.addEvent(events[i]);
			// otherwise if the passed event list is a single event, add that one event
			} else if ( qt.isO( events ) && qt.isS( events._id ) ) {
				// add the event to the event list used by the calendar
				this.addEvent( events );
			}

			this.updateSources();

			// update the event list ui below the calendar
			this.updateEventList();

			// notify that we added events
			this.callback( 'add_events', [ events ] );
		},

		// add an event the list of events used by the calendar
		addEvent: function( title, start, extra ) {
			var args = {},
					obj = {};

			// if the title param is actually the entire event object, then
			if ( qt.isO( title ) ) {
				// normalize the event, and make a copy
				obj = $.extend( {}, title );
			// otherwise combine the given params to create the event object
			} else {
				var extra = extra || { allDay:false };
				// check the title that was passed
				if ( ! qt.isS( title ) ) return;

				// create the base event
				obj = $.extend( {}, {
					title: title,
					start: start
				}, extra );
			}


			// aggregate the args we will use for the event render on the calendar
			args = $.extend( {
				status: 'pending',
				visibility: 'public',
				capacity: 0,
				post_id: new_post_id--
			}, obj );

			// notify of the new event
			this.callback( 'add_event', [ args, obj ] );

			// add the event to the calendar list
			this.events.push( args );
		},

		removeEvents: function(events) {
			this.callback('delete_events', [events]); // be smart... call before we delete the events from the list
			if (events instanceof Array) {
				for (var i=0; i<events.length; i++) this.removeEvent(events[i]);
				this.updateEventList();
			} else if (typeof events == 'object' && typeof events._id == 'string') {
				this.removeEvent(events);
				this.updateEventList();
			}
		},

		removeEvent: function(ev) {
			var to_remove = ev.length;

			// this may seem dumb, and convoluted, but trust me, it is not. we have to use the EXACT same array here, otherwise the list used by the calendar does not update.
			// this means that you cannot use methods that create a NEW array, such as Array.filter or Array.slice/splice or some function that creates a new array outright.
			// the alternative to this is to manually update the array used by the 'event source' that the calendar uses, and that is far less future proof.

			// track the number of items that have been removed
			var removed = 0;

			// cycle through all array items in the events array
			for (var i=0; i<this.events.length; i++) {
				// if the current array item (event) matches the event we are trying to remove
				if (this.events[i].post_id == ev.post_id) {
					// remove this item
					delete this.events[i];
					// add to the count of removed items
					removed++;
				// if we are not removing the current item, we need to shift this item up the array by the number of items we have removed thus far
				} else {
					// move the item up
					this.events[i-removed] = this.events[i];
				}
			}

			// now we know how many items we have removed total. now we need to trim the end of the array by that many items. this will remove any duplicates that this
			// shifting process has created as well as any undefined values
			for (var i=0; i<removed; i++)
				this.events.pop();

			var exists = $('[rel="items-removed"]', this.elements.main);
			if (exists.length == 0) $('<input type="hidden" rel="items-removed" name="events-removed" value="1" />').appendTo(this.elements.main);

			this.updateSources();
			this.updateEventList();
		},

		template: function(names) {
			var template = '';

			if (typeof names == 'string') names = [names];
			if (!(names instanceof Array)) return template;

			for (var i=0; i<names.length; i++) {
				if ($.inArray(typeof this.templates[names[i]], ['string', 'object', 'function']) != -1) {
					template = this.templates[names[i]];
					break;
				}
			}

			return template;
		},

		setOptions: function(o) {
			this.options = $.extend({}, this.defs, o, {author:'loushou', version:'0.1-beta'});
			this.callback('set_options', [o]);
		},

		loadSettings: function() {
			if (qt.isO(_qsot_settings)) {
				if (qt.isO(_qsot_settings.events)) {
					this.events = _qsot_settings.events;
				}
				if (qt.isO(_qsot_settings.templates)) {
					this.templates = _qsot_settings.templates;
				}
				this.first = typeof _qsot_settings.first == 'string' && _qsot_settings.first != '' ? moment(_qsot_settings.first) : moment();
			}
			this.callback('load_settings');
		},

		updateEventList: function() {
			this.calendar.fullCalendar('refetchEvents');
			var events = this.calendar.fullCalendar('clientEvents');

			if (this.elements.event_list.length) {
				this.elements.event_list.empty();
				events.sort(function(a, b) {
					var at = moment( a.start ).unix();
					var bt = moment( b.start ).unix();
					return at < bt ? -1 : ( at == bt ? 0 : 1 );
				});
				for (i in events) {
					if (typeof this.event_list == 'object' && typeof this.event_list.add_item == 'function') this.event_list.add_item(events[i]);
				}
				this.last_clicked = undefined;
				if (typeof this.event_list == 'object' && typeof this.event_list.updateSettingsForm == 'function') this.event_list.updateSettingsForm();
				this.callback('update_event_list');
			}
		},

		calendarAfterRender: function(ev, element, view) {
			element = $(element);
		},

		// add the buttons we need in the admin interface
		addButtons: function( view ) {
			var tm = this.fctm;

			// find the header container that we are adding the buttons to
			this.elements.header = $( '.qsot-action-toolbar' );
			if ( ! this.elements.header.length ) {
				this.elements.orig_header = view.el.closest( '.' + tm ).find( '.' + tm + '-toolbar' );
				this.elements.header = $( '<div class="qsot-action-toolbar ' + tm + '-toolbar"></div>' ).insertBefore( this.elements.orig_header );
			}
			this.elements.header_center = $( '<div class="' + tm + '-center"></div>' ).appendTo( this.elements.header );

			// add the new evetn date button, which when clicked, opens the new event date form
			this.addButton( 'new_event_btn', 'New Event Date', [ 'togvis', 'button', 'button-primary' ], { tar:'.option-sub[rel=add]', scope:'.events-ui' } ).appendTo( this.elements.header_center );

			// allow others to add buttons here as well
			this.callback( 'add_buttons', [ view ] );
		},

		// create a button and return it
		addButton: function( name, label, classes, attr ) {
			// if the button does not already exist, create it
			if ( ! qt.is( this.elements.buttons[ name ] ) ) {
				// normalize the input
				var tm = this.fctm,
						attr = attr || {},
						classes = classes || '';
				classes = qt.isA( classes ) ? classes : ( qt.isO( classes ) ? '' : classes.split( /\s+/ ) );

				// add some default classes
				classes.concat( [
					tm + '-button',
					tm + '-button-' + name,
					tm + '-state-default',
					tm + '-corner-left',
					tm + '-corner-right'
				] );
				classes = classes.join( ' ' );

				// create the button
				this.elements.buttons[ name ] = $( '<span>'
						+ '<span class="' + tm + '-button-inner">'
							+ '<span class="' + tm + '-button-content">' + qt.str( label, S ) + '</span>'
							+ '<span class="' + tm + '-button-effect"><span></span></span>'
						+ '</span>'
					+ '</span>').addClass( classes ).attr( attr )
					// handle the visual change, using the fc state css, when hovered
					.hover(
						function() { $( this ).not( '.' + tm + '-state-active' ).not( '.' + tm + '-state-disabled' ).addClass( tm + '-state-hover' ); },
						function() { $( this ).removeClass( tm + '-state-hover' ).removeClass( tm + '-state-down' ); }
					)
					// handle the visual changes upon click of the button
					.click( function() {
						var self = $( this );
						if ( self.hasClass( tm + '-state-active' ) )
							self.removeClass( tm + '-state-active' );
						else
							self.addClass( tm + '-state-active' );
					} );
			}

			// returned the created or cached element
			return this.elements.buttons[ name ];
		},

		beforeFormSubmit: function(form) {
			var events = this.calendar.fullCalendar('clientEvents'),
					post_id_dec = -1,
					defaults = {
						status:'pending',
						visibility:'public',
						password:'',
						pub_date:'',
						purchase_limit: 0,
						capacity:0
					};
			this.callback( 'before-submit-defaults', [ defaults ] );

			for (var i = 0; i < events.length; i++) {
				var ev = {
					_id: events[i]._id,
					start: (moment(events[i].start)).format(),
					end: qt.isO( events[i].end ) && ( events[i].end instanceof Date || events[i].end._isAMomentObject ) ? (moment(events[i].end)).format() : events[i].end,
					title: events[i].title,
					post_id: events[i].post_id,
					status: events[i].status,
					visibility: events[i].visibility,
					password: events[i].password,
					pub_date: events[i].pub_date,
					purchase_limit: events[i].purchase_limit || 0,
					capacity: events[i].capacity
				};
				ev = $.extend( { post_id:post_id_dec-- }, defaults, ev );
				this.callback('before_submit_event_item', [ ev, events[i], defaults ]);
				var txt = JSON.stringify(ev);
				$('<input type="hidden" name="_qsot_event_settings['+i+']" value=""/>').val(txt).appendTo(form);
			}

			//return false;
		}
	};

	$.fn.qsEventUI = function(o) { return this.each(function() { return startEventUI($(this), o); }); };

	EventUI.callbacks = new QS.CB( EventUI );

	return EventUI;
})(jQuery);
