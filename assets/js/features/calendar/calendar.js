var QS = jQuery.extend( true, { Tools:{} }, QS );

// event calendar control js
QS.EventCalendar = ( function( $, W, D, qt, undefined ) {
	// get the settings sent to us from PHP
	var S = $.extend( { show_count:true }, _qsot_calendar_settings ),
			H = 'hasOwnProperty',
			DEFS = {
				on_selection: false,
				calendar_container: '.event-calendar'
			};

	// js equivalent to php ucwords func
	function ucwords( str ) { return ( str + '' ).replace( /^([a-z\u00E0-\u00FC])|\s+([a-z\u00E0-\u00FC])/g, function( $1 ) { return $1.toUpperCase(); } ); }

	// return the function that will be stored in QS.EventCalendar
	return function( options ) {
		var T = $.extend( this, {
					initialized: false,
					fix: {},
					elements: {},
					options: $.extend( {}, DEFS, options, { author:'Loushou', version:'0.2.0-beta' } ),
					url_params: {},
					goto_form: undefined
				} ),
				imgs = {};

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
				eventAfterAllRender: function() { _loading( false ); },
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
				contentHeight: 'auto',
				viewRender: trigger_header_render_event,
				headerRender: add_header_elements,
				// change up the header format
				header: { left:'', center:'title', right:'today prev,next' },
				// what to do when the events are loading from ajax
				loading: _loading
			} );
			T.fcal = T.cal.data( 'fullCalendar' );

			// if the default start date was defined, then go to it now
			console.log( 'goto date', get_goto_date() );
			T.cal.fullCalendar( 'gotoDate', get_goto_date() );
		}

		// get the date to goto
		function get_goto_date() { return moment( qt.isO( T.options.gotoDate ) || qt.isS( T.options.gotoDate ) ? T.options.gotoDate : moment() ); }

		// get the url params currently stored internal to this object
		function get_url_params() { return T.url_params; }
		// set the url params stored internal to this object
		function set_url_params( data ) { T.url_params = $.extend( true, {}, data ); }

		// when rendering the header section of the calendar, we need to add the 'goto' form
		function add_header_elements( header_element, view ) {
			// add the goto form, which allows choosing a month and year, and navigating to it
			header_element.siblings( '.goto-form' ).remove();
			setup_goto_form( this ).insertBefore( header_element );

			// add the view selector, which allows us to switch views on the fly
			var left = header_element.find( '.fc-left' );
			setup_view_selector( this ).appendTo( left.empty() );
		}

		// when rendering the new view, we need trigger a header render event, used elsewhere, to force refresh the header
		function trigger_header_render_event( view, view_element ) {
			_loading( true, view );
			view.calendar.trigger( 'headerRender', view.calendar, $( view.el ).closest( '.fc' ).find( '.fc-toolbar' ), view );
		}

		// setup the form that allows us to switch views easily
		function setup_view_selector( calendar ) {
			// if the selector has not yet been created, then create it
			if ( ! qt.is( T.view_selector ) ) {
				var i;
				T.view_selector = $( '<select rel="view" class="fc-state-default"></style>' );

				// create an entry for each view that is available
				for ( i in $.fullCalendar.views ) if ( $.fullCalendar.views[ H ]( i ) ) {
					var name = ucwords( i.replace( /([A-Z])/, function( match ) { return ' ' + match.toLowerCase(); } ) );
					$( '<option>' + name + '</option>' ).attr( 'value', i ).appendTo( T.view_selector );
				}

				// setup the switcher event
				T.view_selector.off( 'change.qscal' ).on( 'change.qscal', function( e ) { T.fcal.changeView( $( this ).val() ); } );
			}

			var res = T.view_selector.clone( true );
			res.find( 'option[value="' + calendar.view.name + '"]' ).prop( 'selected', 'selected' );
			return res;
		}

		// setup and fetch the gotoForm
		function setup_goto_form( calendar ) {
			// if the gotoform was not setup yet, then do it now
			if ( ! qt.is( T.goto_form ) ) {
				T.goto_form = $( '<div class="goto-form"></div>' );
				var goto_date = get_goto_date(),
						gy = goto_date.year(),
						gm = goto_date.month(),
						year_select = $( '<select rel="year" class="fc-state-default"></select>' ).appendTo( T.goto_form ),
						month_select = $( '<select rel="month" class="fc-state-default"></select>' ).appendTo( T.goto_form ),
						btn_classes = 'fc-button fc-button-today fc-state-default fc-corner-left fc-corner-right',
						goto_btn = $( '<button rel="goto-btn" unselectable="on" style="-moz-user-select: none;" class="' + btn_classes + '">' + qt.str( 'Goto Month', S ) + '</button>' ).appendTo( T.goto_form ),
						i;

				// setup the options on both the year and month select boxes
				for ( i = gy - 10; i <= gy + 15; i++ )
					$( '<option value="' + i + '"' + ( gy == i ? ' selected="selected"' : '' ) + '>' + i + '</option>' ).appendTo( year_select );
				for ( i = 0; i < 12; i++ )
					$( '<option value="' + i + '"' + ( gm == i ? ' selected="selected"' : '' ) + '>' + moment( { y:gy, M:i } ).format( 'MMMM' ) + '</option>' ).appendTo( month_select );
			}

			var ret = T.goto_form.clone( true );

			// setup the events for the goto button
			ret.find( '[rel="goto-btn"]' )
				.on( 'click.goto-form', function( e ) {
					e.preventDefault();
					console.log( 'click', ret.find( '[rel="year"]' ), ret.find( '[rel="month"]' ), ret.find( '[rel="year"]' ).val(), ret.find( '[rel="month"]' ).val() );
					T.cal.fullCalendar( 'gotoDate', moment( { y:ret.find( '[rel="year"]' ).val(), M:ret.find( '[rel="month"]' ).val() } ) );
				} )
				.hover(
					function() { $( this ).addClass( 'fc-state-hover' ); },
					function() { $( this ).removeClass( 'fc-state-hover' ); }
				);

			return ret;
		}

		// when clicking an event, we need to 'select' the event
		function on_click( evt, e, view ) { if ( qt.isF( T.options.on_selection ) ) T.options.on_selection( e, evt, view ); }

		// as a transition between triggered actions (like a click) and rendered results (like the events being rendered in the calendar frame), we need a visual 'loading' cue. this function handles that
		function _loading( show, view ) {
			// if the loading container is not yet created, create it now
			if ( ! qt.isO( T.elements.loading ) || ! T.elements.loading.length ) {
				// setup the parts of the loading overlay
				T.elements.loading = $( '<div class="loading-overlay-wrap"></div>' ).appendTo( T.elements.m );
				T.elements._loading_overlay = $( '<div class="loading-overlay"></div>' ).appendTo( T.elements.loading );
				T.elements._loading_msg = $( '<div class="loading-message">' + qt.str( 'Loading...', S ) + '</div>' ).appendTo( T.elements.loading );
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

		// get the template name from the view name
		function _template_name( view_name ) {
			return view_name.replace( /([A-Z])/, function( match ) { return '-' + match.toLowerCase(); } ) + '-view';
		}

		// add a load check for images. once all rendered images are loaded, we will rerender
		function _add_image_loaded_check( src ) {
			var image = new Image();
			image.onload = function() {
				imgs[ src ] = true;
			};
			image.src = src;
		}

		// render a single event
		function render_event( evt, element, view ) {
			// get the template to use, based on the current view
			var tmpl = _template_name( view.name ),
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
				var img = $( evt.img ).appendTo( section ),
						src = img.attr( 'src' );

				// if the browser did not previously load the image, then make it do so now
				if ( ! imgs[ src ] ) {
					imgs[ src ] = false;
					_add_image_loaded_check( src );
				}
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
