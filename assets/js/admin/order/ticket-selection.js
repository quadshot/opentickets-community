var QS = QS || {},
		_qsot_admin_ticket_selection = _qsot_admin_ticket_selection || {};

// container to hold all the special logic for each event area type
QS.eventAreaTypes = {};

// special logic for the general admission event area type
QS.eventAreaTypes['general-admission'] = ( function( $, qs, qt) {
	// constructor for the general admission event area type handler
	function gaea_ui() {
		var me = this;
		me.initialized = false;

		/* private methods */

		// setup the elements used by this area type
		function _setup_elements() {
			// get the template list for this area type
			var templates = me.S.templates['general-admission'] || {};

			// setup the main containers for the ticket selection UI on this event area type
			me.e = {
				main: $( '<div class="general-admission-event"></div>' ).hide().appendTo( me.ui.e.info ),
				actions: $( '<div class="general-admission-event"></div>' ).hide().appendTo( me.ui.e.actions ),
				event_wrap: $( '<div class="general-admission-event"></div>' ).hide().appendTo( me.ui.e.event_wrap ),
				holder:$( '<div></div>' )
			};

			// add the various parts used by the event area type UI
			me.e.info = $( templates['info'] ).appendTo( me.e.main );
			me.e.actions_change = $( templates['actions-change'] ).appendTo( me.e.holder );
			me.e.actions_add = $( templates['actions-add'] ).appendTo( me.e.holder );
			me.e.inner_change = $( templates['inner-change'] ).appendTo( me.e.holder );
			me.e.inner_add = $( templates['inner-add'] ).appendTo( me.e.holder );
		}

		// setup the events that this area type makes use of
		function _setup_events() {
			me.e.actions.on( 'click', '[rel="change-btn"]', me.ui.switch_events );
			me.e.actions.on( 'click', '[rel="use-btn"]', me.update_ticket );
			me.e.event_wrap.on( 'click', '[rel="add-btn"]', me.add_tickets );
		}

		// setup state UI, depending on the current ui state
		function _setup_state_ui() {
			// clear out the event image
			me.e.event_wrap.find( '[rel="image-wrap"]' ).empty();

			// move the existing actions and ui int the holding area
			me.e.event_wrap.find( '>' ).appendTo( me.e.holder );
			me.e.actions.find( '>' ).appendTo( me.e.holder );

			// move the appropriate ui elements in the actions and event_wrap container
			me.e[ 'actions_' + me.ui.state ].appendTo( me.e.actions );
			me.e[ 'inner_' + me.ui.state ].appendTo( me.e.event_wrap );

			// update the text on the button, based on state
			switch ( me.ui.state ) {
				case 'add': me.e.event_wrap.find( '[rel="add-btn"]' ).val( qs._str( 'Add Tickets', me.S ) ); break;
				case 'change': me.e.event_wrap.find( '[rel="add-btn"]' ).val( qs._str( 'Change Ticket Count', me.S ) ); break;
				default: me.e.event_wrap.find( '[rel="add-btn"]' ).val( qs._str( 'Save', me.S ) ); break;
			}
		}

		// initialize this object inside the modal
		me.initialize = function( ui, S ) {
			// only do this once
			if ( me.initialized )
				return;
			me.initialized = true;

			// set the internal reference to the ui and settings
			me.ui = ui;
			me.S = S;

			// setup the elements and events
			_setup_elements();
			_setup_events();
		}

		// handle the load event logic for this area type
		me.load_event = function( event_obj ) {
			// setup the state buttons and interface
			_setup_state_ui();

			// show whether there are enough seats or not
			me.e.info[ me.ui.quantity <= event_obj._available ? 'removeClass' : 'addClass' ]( 'no-enough' );

			// add the event name, with the edit link on it
			$( event_obj._link ).appendTo( me.e.info.find( '[rel="name"]' ).empty() );

			// and the date range for the event
			$( event_obj._html_date ).appendTo( me.e.info.find( '[rel="date"]' ).empty() );

			// fill out the capacity and availability indicators
			me.e.info.find( '[rel="capacity"] [rel="total"]' ).text( event_obj._capacity );
			me.e.info.find( '[rel="capacity"] [rel="available"]' ).text( event_obj._available );

			// fill out the form with the current number of owned tickets
			me.e.event_wrap.find( '[rel="ticket-count"]' ).val( event_obj._owns );

			// ticket display information
			me.e.event_wrap.find( '[rel="ttname"]' ).html( '"' + event_obj._ticket.title + ' (' + event_obj._ticket.price + ')"' );

			// update the event image
			if ( qt.isO( event_obj._imgs ) && qt.isO( event_obj._imgs.full ) && qt.is( event_obj._imgs.full.url ) )
				$( '<div class="event-area-image"><img src="' + qt.esc( event_obj._imgs.full.url ) + '" title="' + qt.esc( event_obj.name ) + '" /></div>' ).appendTo( me.e.event_wrap.find( '[rel="image-wrap"]' ).empty() );

			// hide all actions, event_wrap, and info data for all area_types
			me.ui.e.info.find( '>' ).hide();
			me.ui.e.actions.find( '>' ).hide();
			me.ui.e.event_wrap.find( '>' ).hide();

			// now show all the ui elements for this event area type
			me.e.main.show();
			me.e.actions.show();
			me.e.event_wrap.show();
		};

		// handle the clicking of the 'add-btn'
		me.add_tickets = function( e ) {
			e.preventDefault();
			// obtain the form data
			var btn = $( this ), form = btn.closest( '.ticket-form' ), data = form.louSerialize( { event_id:me.ui.event_obj.id } );

			// if there quantity is not present or too low, then bail
			if ( ! qt.is( data.qty ) || data.qty < 1 ) {
				alert( qs._str( 'You must specify a quantity greater than 1.', me.S ) );
				return;
			}

			// block the dialog
			var true_dialog = me.ui.e.dialog.closest( '.ui-dialog' );
			true_dialog.qsBlock( { msg:'<h1>' + qs._str( 'Processing...', me.S ) + '</h1>', css:{ zIndex:999999 }, msgcss:{ zIndex:1000000 } } );

			// process the add ticket request
			me.ui.aj( 'gaea-add-tickets', data, function( r ) {
				// if the response is not valid, then bail
				if ( ! qt.isO( r ) ) {
					true_dialog.qsUnblock();
					me.ui.dialog_msgs( [ qs._str( 'Invalid response.', me.S ) ], 'error' );
					return;
				}

				// if the response was not successful, then bail
				if ( ! qt.is( r.s ) || ! r.s ) {
					true_dialog.qsUnblock();
					// show any passed errors, or a default error
					me.ui.dialog_msgs( qt.isA( r.e ) ? r.e : [ qs._str( 'There was a problem adding those tickets.', me.S ) ], 'error' );
					return;
				}

				me.load_event( r.data );
				// otherwise, update the order items list
				me.ui.update_order_items( function() { true_dialog.qsUnblock(); } );
			}, function() { true_dialog.qsUnblock(); })
		};

		me.update_ticket = function( e ) {
			e.preventDefault();

			// aggregate a list of the data needed for the ajax request
			var data = { event_id:me.ui.event_obj.id, oiid:me.ui.order_item.data( 'order_item_id' ) };

			// block the dialog
			var true_dialog = me.ui.e.dialog.closest( '.ui-dialog' );
			true_dialog.qsBlock( { msg:'<h1>' + qs._str( 'Processing...', me.S ) + '</h1>', css:{ zIndex:999999 }, msgcss:{ zIndex:1000000 } } );

			// process the update ticket request
			me.ui.aj( 'gaea-update-ticket', data, function( r ) {
				// if the response is not valid, then bail
				if ( ! qt.isO( r ) ) {
					true_dialog.qsUnblock();
					me.ui.dialog_msgs( [ qs._str( 'Invalid response.', me.S ) ], 'error' );
					return;
				}

				// if the response was not successful, then bail
				if ( ! qt.is( r.s ) || ! r.s || ! qt.isO( r.data ) || ! qt.isO( r.event ) ) {
					true_dialog.qsUnblock();
					// show any passed errors, or a default error
					me.ui.dialog_msgs( qt.isA( r.e ) ? r.e : [ qs._str( 'There was a problem adding those tickets.', me.S ) ], 'error' );
					return;
				}

				// update the event display in the order item
				// first update the event id on the button, so on the next click to change the event, the whole process works as expected
				me.ui.order_item.find( '.change-ticket' ).attr( 'event-id', r.event.ID );

				// add a new link to the new event in the order item meta, and remove the old one
				var event_link = me.ui.order_item.find( '.ticket-info [rel="edit-event"]' );
				$( r.event._edit_link ).insertBefore( event_link );
				event_link.remove();

				// mark the item as having been updated, which highlights it for confirmation
				me.ui.order_item.find( '.ticket-info' ).addClass( 'updated' );

				// clearup and close the dialog
				true_dialog.qsUnblock();
				me.ui.e.dialog.dialog( 'close' );
			}, function() {
				me.ui.dialog_msgs( [ qs._str( 'Could not update those tickets.', me.S ) ], 'error' );
				true_dialog.qsUnblock();
			} )
		};
	}

	return new gaea_ui();
} )( jQuery, QS, QS.Tools );

// main admin ticket selection UI
QS.adminTicketSelection = ( function( $, qs, qt ) {
	// normalize the settings for this UI
	var S = $.extend( true, { nonce:'', templates:{} }, _qsot_admin_ticket_selection );

	// constructor for the UI
	function UI( options, element ) {
		var me = this;
		// only run the constructor once
		if ( me.initialized )
			return me;
		me.initialized = true

		/* private functions */

		// init the object
		function __init() {
			// setup the options
			me.opts = $.extend( true, {}, options, { author:'loushou', version:'0.1.0' } );

			// setup the initial internal state. state determines what type of action is happening. defaults are 'add' (adding a ticket) and 'change' (changing and existing ticket)
			me.state = '';

			// setup the param that contains the price that the existing ticket uses. this will be used to determine what events show in the calendar to choose from
			me.priced_like = '';

			// setup param that contains the event id of the existing order item
			me.event_id = 0;

			// param that contains the original quantity of the order item
			me.quantity = 0;

			// setup the elements
			me.e = {
				scope:$( element ),
				order_items: $( element ).find( '#order_line_items' )
			};
			me.e.order_items = me.e.order_items.length ? me.e.order_items : $( '#order_line_items' );

			// setup our basic elements and events
			_setup_basic_elements();
			_setup_basic_events();
		}

		// setup the basic elements used by the UI
		function _setup_basic_elements() {
			// figure out the dimensions of the window, including margins, padding and borders
			var windims = { w:$( window ).outerWidth( true ), h:$( window ).outerHeight( true ) };

			// create the dialog element
			me.e.dialog = $( S.templates['dialog-shell'] ).appendTo( '#wpwrap' ).dialog( {
				autoOpen: false,
				dialogClass: 'qsot-dialog',
				width: windims.w >= 1000 ? 1000 : (windims.w >= 600 ? 600 : windims - 10),
				height: 'auto',
				modal: true,
				position: { my:'center', at:'center', of:window },
				appendTo: '#wpwrap',
				close: function() { _reset(); }
			} );

			// and assign all the dialog parts
			me.e.errors = me.e.dialog.find( '[rel="errors"]' );
			me.e.info = me.e.dialog.find( '[rel="info"]' );
			me.e.actions = me.e.dialog.find( '[rel="actions"]' );
			me.e.transition = me.e.dialog.find( '[rel="trans"]' );
			me.e.event_wrap = me.e.dialog.find( '[rel="event-wrap"]' );
			me.e.calendar = me.e.dialog.find( '[rel="calendar-wrap"]' );
			me.e.all = me.e.errors.add( me.e.info ).add( me.e.actions ).add( me.e.event_wrap ).add( me.e.calendar ).add( me.e.transition );

			// add the transitioner
			$( S.templates['transition'] ).appendTo( me.e.transition );

			// setup the calendar
			var today = moment(),
					args = $.extend( {}, _qsot_event_calendar_ui_settings, { calendar_container:me.e.calendar, on_selection:_select_event } );
			me.calendar = new QS.EventCalendar( args );
			me.calendar.cal.fullCalendar( 'gotoDate', today );

			// allow more external setup
			qs.cbs.trigger( 'setup-elements', [ me, S ] );
		}

		// setup the basic events
		function _setup_basic_events() {
			me.e.scope.on( 'click', '[rel="add-tickets-btn"]', me.add_ticket_ui );
			me.e.scope.on( 'click', '.change-ticket', me.change_ticket_ui );

			qs.cbs.trigger( 'setup-events', [ me, S ] );
		}

		// load the calendar
		function _load_calendar( current_date, msgs ) {
			// normalize the input
			msgs = msgs || false;
			if ( ! current_date ) {
				// if there is a 'current event', then try to load the calendar for around that event date
				if ( qt.isO( me.event_obj ) && qt.is( me.event_obj.dt ) )
					current_date = moment( me.event_obj.dt.replace( / /, 'T' ) )
				else
					current_date = moment();
			}

			// hide all dialog element so that we can load the calendar only
			me.e.all.hide();

			// add our messages, if they are present
			if ( msgs && msgs.length )
				me.dialog_msgs( msgs, 'error' );

			// show the calendar
			me.e.calendar.fadeIn( 200, _adjust_dialog );

			// set the param that tells the calendar to only show events that can use the same price as the existing event, if it exists
			me.calendar.setUrlParams( { priced_like:me.priced_like } );

			// set the date only the calendar, forcing a reload of the events
			me.calendar.cal.fullCalendar( 'gotoDate', current_date );

			// allow external modification
			qs.cbs.trigger( 'load-calendar', [ current_date, me.e, me, S ] );
		}

		// handle the clicking of an event in the calendar
		function _select_event( e, calendar_event ) {
			e.preventDefault();
			// load the event
			_load_event( calendar_event.id );
		}

		// load a specific event chosen from the calendar
		function _load_event( event_id ) {
			// hide all ui elements, and show a loading screen
			me.e.all.hide();
			me.e.transition.fadeIn( 200, _adjust_dialog );

			// reset the loaded event
			me.event_obj = {};

			// load the event
			me.aj( 'load-event', { event_id:event_id }, function( r ) {
				// if the response is invalid, error out
				if ( ! qt.isO( r ) ) {
					me.switch_events();
					me.dialog_msgs( [ qs._str( 'There was a problem loading the requested information. Please close this modal and try again.', S ) ], 'error' );
					return;
				}

				// if the event is not part of the response, then error out
				if ( ! qt.isO( r.data ) ) {
					me.switch_events();
					me.dialog_msgs( [ qs._str( 'There was a problem loading the requested Event. Switching to calendar view.', S ) ], 'error' );
					return;
				}

				// if the area_type of this event is valid, then
				if ( qt.is( r.data.area_type ) && qt.isO( QS.eventAreaTypes[ r.data.area_type ] ) ) {
					// set the global event holder with the event data
					me.event_obj = r.data;
					//console.log( 'Loaded Event:', me.event_obj );

					// make sure that event area type is initialized, and tell it to load the event
					QS.eventAreaTypes[ r.data.area_type ].initialize( me, S );
					QS.eventAreaTypes[ r.data.area_type ].load_event( me.event_obj );

					// notify externals
					qs.cbs.trigger( 'load-event', [ r, me.e, me, S ] )

					// show the interface
					me.e.all.hide();
					me.e.info.add( me.e.actions ).add( me.e.event_wrap ).fadeIn( 200, _adjust_dialog );
				// otherwise, return to calendar with an error
				} else {
					me.switch_events();
					me.dialog_msgs( [ qs._str( 'Could not load that event, because it has an invalid event area type.', S ) ], 'error' );
				}
			}, function() {
				me.switch_events();
				me.dialog_msgs( [ qs._str( 'Could not load that event.', S ) ], 'error' );
			} );
		}

		// adjust the dialog position, after the content has changed (a manual call, not automagically)
		function _adjust_dialog() {
			me.e.dialog.dialog( 'option', 'position', { my:'center top', at:'center top+10', of:window, collision:'fit flip', within:window } );
		}

		// reset all internal markers
		function _reset() {
			me.priced_like = me.state = '';
			me.event_id = me.quantity = 0;
			me.event_obj = undefined;
		}

		/* public functions */

		// handle all ajax calls
		me.aj = function( sa, data, func, error_func ) {
			// normalize the data
			var data = $.extend( {}, data, { action:'qsot-admin-ajax', sa:sa, _n:S.nonce, order_id:$( '#post_ID' ).val(), customer_user:$( '#customer_user' ).val() } ),
					// normalize the success and error functions
					func = func || function(){},
					error_func = error_func || function(){};

			// perform the ajax
			$.ajax( {
				url: ajaxurl,
				data: data,
				type: 'POST',
				dataType: 'json',
				success: function( r ) {
					if ( typeof r == 'object' ) {
						if ( typeof r.e != 'undefined' )
							console.log( 'ajax error: ', r.e );
						func( r );
					} else error_func();
				},
				error: error_func
			});
		}

		// open the dialog box
		me.open_dialog = function() {
			// only try to open the dialog, if it is not already open, otherwise an exception is thrown
			if ( ! me.e.dialog.dialog( 'isOpen' ) )
				me.e.dialog.dialog( 'open' );
		};

		// close the dialog box
		me.close_dialog = function() {
			// only try to close the dialog if it is open
			if ( me.e.dialog.dialog( 'isOpen' ) )
				me.e.dialog.dialog( 'close' );
		};

		// set the errors/messages to be displayed in the dialog
		me.dialog_msgs = function( errors, type, hide_all ) {
			type = type || 'error';
			// if we are being asked to hide all dialog elements, then do so
			if ( hide_all )
				me.e.all.hide();

			// clear out the error message container
			me.e.errors.empty();

			// add each error the list
			for ( var i=0; i < errors.length; i++ )
				$( '<div class="' + type + '"></div>' ).html( errors[ i ] ).appendTo( me.e.errors );

			// show the errors
			me.e.errors.fadeIn( 200 );
		};

		// start the UI with the mindset of 'adding' a new reservation
		me.add_ticket_ui = function( e ) {
			e.preventDefault();

			// flag the internal state of the UI
			me.state = 'add';

			// load the calendar
			me.open_dialog();
			_load_calendar();
		};

		// start the UI with the mindset of 'changing' an existing reservation
		me.change_ticket_ui = function( e ) {
			e.preventDefault();

			// flag the internal state of the UI
			me.state = 'change';

			// setup the internal tracker of the clicked order item
			var btn = $( this );
			me.order_item = btn.closest( '.item' );
			me.event_id = btn.data( 'event-id' ) || btn.attr( 'event-id' );
			me.quantity = btn.data( 'qty' ) || btn.attr( 'qty' );

			// if the event id is not set or invalid, then try to use any currently selected event
			if ( me.event_id <= 0 && qt.isO( me.event_obj ) && qt.is( me.event_obj.id ) )
				me.event_id = qt.toInt( me.event_obj.id );

			// if there is a valid event id, then make sure we: 1) load the event directly, and 2) only show events in the calendar that have compatible pricing
			if ( me.event_id > 0 ) {
				me.priced_like = me.event_id;
				me.open_dialog();
				_load_event( me.event_id );
			} else {
				me.open_dialog();
				_load_calendar();
			}
		};

		// handle the switching from the current event to another
		me.switch_events = function( e ) {
			if ( qt.isO( e ) && qt.isF( e.preventDefault ) )
				e.preventDefault();
			_load_calendar();
		};

		// handle updating the order items list
		me.update_order_items = function( on_complete ) {
			// normalize the completion function
			var on_complete = on_complete || function() {};

			// pop a loading overlay on top of the order items
			me.e.order_items.qsBlock( { css:{ zIndex:9999 }, msgcss:{ zIndex:10000 } } );

			// run the ajax that updates the list
			me.aj( 'update-order-items', {}, function( r ) {
				// on success, update the list

				// if the response is not valid, then error
				if ( ! qt.isO( r ) || ! qt.isA( r.i ) || ! r.i.length ) {
					me.dialog_msgs( [ qs._str( 'Tickets were added, but you must refresh the page to see them in the order items list.', S ) ] );
				// otherwise, update the list
				} else {
					me.e.order_items.empty();
					for ( var i=0; i < r.i.length; i++ )
						$( r.i[ i ] ).appendTo( me.e.order_items );
					me.dialog_msgs( [ qs._str( 'Tickets have been added.' ) ], 'msg' );
				}

				me.e.order_items.qsUnblock();
				on_complete( true, r );
			}, function() {
				me.e.order_items.qsUnblock();
				on_complete( false );
			} );
		};

		// initialize the object
		__init();
	}

	// makeshift singleton
	var instance = undefined;
	// instance function
	UI.start = function( o, e ) {
		// if there is already an instance, use it
		if ( instance instanceof UI )
			return instance;

		// otherwise create a new instance
		return ( instance = new QS.adminTicketSelection( o, e ) );
	};

	return UI;
} )( jQuery, QS, QS.Tools );

jQuery(function($) {
	var ts = QS.AdminTS = QS.adminTicketSelection.start( {}, 'body' );
});
