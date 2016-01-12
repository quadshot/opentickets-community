var QS = jQuery.extend( true, { Tools:{} }, QS );

// event calendar control js
QS.EventCalendar = ( function( $, W, D, qt, undefined ) {
	// get the settings sent to us from PHP
	var S = $.extend( { show_count:true }, _qsot_calendar_settings ),
			DEFS = {
				on_selection: false,
				calendar_container: '.event-calendar'
			};

	// return the function that will be stored in QS.EventCalendar
	return function( options ) {
		var T = $.extend( this, {
					initialized: false,
					fix: {},
					elements: {},
					options: $.extend( {}, DEFS, options, { author:'Loushou', version:'0.2.0-beta' } ),
					url_params: {},
					goto_form: undefined
				} );

		// call the plugin init logic
		_init();

		// add the public methods
		T.refresh = refresh;
		T.setUrlParams = set_url_params;

		// the initialization function
		function _init() {
			// if we alerady initialized, then dont do it again
			if ( T.initialized )
				return;

			// setup the base elements
			T.elements.m = $( T.options.calendar_container );

			// if the primary calendar container was not found, then bail now
			if ( ! T.elements.m.length )
				return;
			T.initialized = true;

			var inside = false;
			// setup the fullcalendar plugin object
			T.cal = T.elements.m.fullCalendar( {
				// draws the event
				eventRender: render_event,
				eventAfterAllRender: function() {
					if ( ! inside ) {
						inside = true;
						refresh( true );
						inside = false;
					}
				},
				// where to get the event data
				eventSources: [
					{
						url: T.options.ajaxurl,
						data: get_url_params,
						xhrFields: { withCredneitals:true }
					}
				],
				// when an event is clicked
				eventClick: on_click,
				// when rendering the calendar header
				viewRender: trigger_header_render_event,
				headerRender: add_goto_form_to_header,
				// what to do when the events are loading from ajax
				loading: _loading
			} );

			// if the default start date was defined, then go to it now
			T.cal.fullCalendar( 'gotoDate', get_goto_date() )
		}

		// get the date to goto
		function get_goto_date() { return moment( qt.isO( T.options.gotoDate ) || qt.isS( T.options.gotoDate ) ? T.options.gotoDate : undefined ); }

		// get the url params currently stored internal to this object
		function get_url_params() { return T.url_params; }
		// set the url params stored internal to this object
		function set_url_params( data ) { T.url_params = $.extend( true, {}, data ); }

		// when rendering the header section of the calendar, we need to add the 'goto' form
		function add_goto_form_to_header( header_element, view ) { setup_goto_form( this ).insertBefore( header_element ); }

		// when rendering the new view, we need trigger a header render event, used elsewhere, to force refresh the header
		function trigger_header_render_event( view, view_element ) { view.calendar.trigger( 'headerRender', view.calendar, $( view.element ).closest( '.fc' ).find( '.fc-header' ), view ); }

		// setup and fetch the gotoForm
		function setup_goto_form( calendar, parent_element ) {
			// if the gotoform was not setup yet, then do it now
			if ( ! qt.is( T.goto_form ) ) {
				T.goto_form = $( '<div class="goto-form"></div>' );
				var goto_date = get_goto_date(),
						gy = goto_date.year(),
						gm = goto_date.month(),
						year_select = $( '<select rel="year" style="width:auto;"></select>' ).appendTo( T.goto_form ),
						month_select = $( '<select rel="month" style="width:auto;"></select>' ).appendTo( T.goto_form ),
						btn_classes = 'fc-button fc-button-today fc-state-default fc-corner-left fc-corner-right',
						goto_btn = $( '<span rel="goto-btn" unselectable="on" style="-moz-user-select: none;" class="' + btn_classes + '">Goto Month</span>' ).appendTo( T.goto_form ),
						i;

				// setup the options on both the year and month select boxes
				for ( i = gy - 10; i <= gy + 15; i++ )
					$( '<option value="' + i + '"' + ( gy == i ? ' selected="selected"' : '' ) + '>' + i + '</option>' ).appendTo( year_select );
				for ( i = 0; i < 12; i++ )
					$( '<option value="' + i + '"' + ( gm == i ? ' selected="selected"' : '' ) + '>' + moment( { y:gy, M:i } ).format( 'MMMM' ) + '</option>' ).appendTo( month_select );

				// setup the events for the goto button
				goto_btn
					.on( 'click.goto-form', function( e ) {
						e.preventDefault();
						calendar.gotoDate( { y:year_select.val(), M:month_select.val() } );
					} )
					.hover(
						function() { $( this ).addClass( 'fc-state-hover' ); },
						function() { $( this ).removeClass( 'fc-state-hover' ); }
					);
			}

			return T.goto_form;
		}

		// when clicking an event, we need to 'select' the event
		function on_click( evt, e, view ) { if ( qt.isF( T.options.on_selection ) ) T.options.on_selction( e, evt, view ); }

		// as a transition between triggered actions (like a click) and rendered results (like the events being rendered in the calendar frame), we need a visual 'loading' cue. this function handles that
		function _loading( show, view ) {
			// if the loading container is not yet created, create it now
			if ( ! qt.isO( T.elements.loading ) || T.elements.loading.length ) {
				// setup the parts of the loading overlay
				T.elements.loading = $( '<div class="loading-overlay-wrap"></div>' ).css( { position:'absolute', top:0, bottom:0, left:0, right:0, width:'auto', height:'auto' } ).appendTo( T.elements.m );
				T.elements._loading_overlay = $( '<div class="loading-overlay"></div>' ).css( { position:'absolute', top:0, left:0, left:0, right:0, width:'auto', height:'auto' } ).appendTo( T.elements.loading );
				T.elements._loading_msg = $( '<div class="loading-message">' + qt.str( 'Loading...', S ) + '</div>' ).css( { position:'absolute', top:0, left:0 } ).appendTo( T.elements.loading );
				
				// when the window resizes, then the calendar also resizes. when that happens, our loading overlay needs to be resized also, because of how we 
				// skip this for now
				/*
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
				*/
			}

			// either show or hide the loading container
			T.elements.loading[ show ? 'show' : 'hide' ]();
		}

		// trigger a refresh, maybe even a full refresh, of the calendar
		function refresh( full_refresh ) {
			// if this is a full refresh request, trigger the full render action
			if ( full_refresh )
				T.cal.fullCalendar( 'render' );

			// refresh the current events being displayed
			T.cal.fullCalendar( 'rerenderEvents' );
		}

		// render a single event
		function render_event( evt, element, view ) {
			// get the template to use, based on the current view
			var tmpl = view.name + '-view',
					element = $( element ),
					inner = $( qt.tmpl( tmpl, T.options ) ).appendTo( element.empty() ),
					section;

			// add some classes for the look and feel, based on the status
			element.addClass( 'status-' + evt.status );
			if ( evt.protected )
				element.addClass( 'is-protected' );
			if ( evt.passed )
				element.addClass( 'in-the-past' );

			// if there is an image block in the display, then add the image, and bump it onto the special 'image load trick' list.
			// we need the ILT crap because once the image loads, we need to rerender the calendar so everything lines up
			if ( '' != evt.img && ( section = inner.find( '.fc-img' ) ) && section.length ) {
				var img = $( evt.img ).appendTo( section );
				qt.ilt( img.attr( 'src' ), refresh, 'event-images' );
			}

			// if the title area exists, add the title
			if ( ( section = inner.find( '.fc-title' ) ) && section.length )
				section.html( evt.title );

			// if the time block exists, add the time. format like: 9p or 5:07a
			if ( ( section = inner.find( '.fc-time' ) ) && section.length ) {
				var mo = moment( evt.start ), format = section.data( 'format' ) || 'h:mma', time = { m:mo.get( 'minute' ) };
				section.html( moment( evt.start ).format( time.m > 0 ? format : format.replace( /:mm/g, '' ) ) );
			}

			// if the availability is in the output, fill that in
			if ( qt.is( evt['avail-words'] ) && ( section = inner.find( '.fc-availability' ) ) && section.length ) {
				section.find( '.words' ).html( evt['avail-words'] );
				if ( qt.is( evt.available ) )
					section.find( '.num' ).html( '[' + evt.available + ']' );
			}

			// if the short description block is present, add that too
			if ( qt.is( evt['short-description'] ) && ( section = inner.find( '.fc-short-description' ) ) && section.length )
				section.html( evt['short-description'] );
		}

		function _setup_goto_form() {}
	};
} )( jQuery, window, document, QS.Tools );

// on page load, start rendering the calendar
jQuery( function( $ ) { var cal = new QS.EventCalendar( _qsot_event_calendar_ui_settings ); } );

/*
var QSEventsEventCalendar = (function($, w, d, undefined) {


		
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
			ele.addClass( 'status-' + evt.status );
			var e = $(t.o.event_template), extra = '';

			if ( $.inArray( evt.status, [ 'private', 'hidden' ] ) != -1 ) {
				extra = ' [' + evt.status + ']';
				title = ele.attr( 'title' ) || '';
				ele.attr( 'title', title + 'This event is hidden from the public. ' );
			}

			if ( evt.protected ) {
				ele.addClass( 'is-protected' );
				extra += ' *';
				title = ele.attr( 'title' ) || '';
				ele.attr( 'title', title + 'This event requires a password.' );
			}

			$('<span class="event-name">' + evt.title + extra + '</span>').appendTo(e.find('.heading'));
			if ( S.show_count ) {
				$('<span class="event-availability"><span class="lbl">Availability: </span><span class="words">'+evt['avail-words']+' </span><span class="num">('+evt.available+')</span></span>').appendTo(e.find('.meta'));
			} else {
				$('<span class="event-availability"><span class="lbl">Availability: </span><span class="words">'+evt['avail-words']+'</span>').appendTo(e.find('.meta'));
			}
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
*/
