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

	// copied from fullCalendar, and needed for the Footer class
	function htmlEscape(s) {
		return (s + '').replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/'/g, '&#039;')
			.replace(/"/g, '&quot;')
			.replace(/\n/g, '<br />');
	}

	// exact copy of Header class inside fullcalendar, retermed as footer
	function Footer(calendar, options) {
		var t = this;
		
		// exports
		t.render = render;
		t.removeElement = removeElement;
		t.updateTitle = updateTitle;
		t.activateButton = activateButton;
		t.deactivateButton = deactivateButton;
		t.disableButton = disableButton;
		t.enableButton = enableButton;
		t.getViewsWithButtons = getViewsWithButtons;
		
		// locals
		var el = $();
		var viewsWithButtons = [];
		var tm;


		function render() {
			var sections = options.header;

			tm = options.theme ? 'ui' : 'fc';

			if (sections) {
				el = $("<div class='fc-toolbar fc-bottom'/>")
					.append(renderSection('left'))
					.append(renderSection('right'))
					.append(renderSection('center'))
					.append('<div class="fc-clear"/>');

				return el;
			}
		}
		
		
		function removeElement() {
			el.remove();
			el = $();
		}
		
		
		function renderSection(position) {
			var sectionEl = $('<div class="fc-' + position + '"/>');
			var buttonStr = options.header[position];

			if (buttonStr) {
				$.each(buttonStr.split(' '), function(i) {
					var groupChildren = $();
					var isOnlyButtons = true;
					var groupEl;

					$.each(this.split(','), function(j, buttonName) {
						var customButtonProps;
						var viewSpec;
						var buttonClick;
						var overrideText; // text explicitly set by calendar's constructor options. overcomes icons
						var defaultText;
						var themeIcon;
						var normalIcon;
						var innerHtml;
						var classes;
						var button; // the element

						if (buttonName == 'title') {
							groupChildren = groupChildren.add($('<h2>&nbsp;</h2>')); // we always want it to take up height
							isOnlyButtons = false;
						}
						else {
							if ((customButtonProps = (calendar.options.customButtons || {})[buttonName])) {
								buttonClick = function(ev) {
									if (customButtonProps.click) {
										customButtonProps.click.call(button[0], ev);
									}
								};
								overrideText = ''; // icons will override text
								defaultText = customButtonProps.text;
							}
							else if ((viewSpec = calendar.getViewSpec(buttonName))) {
								buttonClick = function() {
									calendar.changeView(buttonName);
								};
								viewsWithButtons.push(buttonName);
								overrideText = viewSpec.buttonTextOverride;
								defaultText = viewSpec.buttonTextDefault;
							}
							else if (calendar[buttonName]) { // a calendar method
								buttonClick = function() {
									calendar[buttonName]();
								};
								overrideText = (calendar.overrides.buttonText || {})[buttonName];
								defaultText = options.buttonText[buttonName]; // everything else is considered default
							}

							if (buttonClick) {

								themeIcon =
									customButtonProps ?
										customButtonProps.themeIcon :
										options.themeButtonIcons[buttonName];

								normalIcon =
									customButtonProps ?
										customButtonProps.icon :
										options.buttonIcons[buttonName];

								if (overrideText) {
									innerHtml = htmlEscape(overrideText);
								}
								else if (themeIcon && options.theme) {
									innerHtml = "<span class='ui-icon ui-icon-" + themeIcon + "'></span>";
								}
								else if (normalIcon && !options.theme) {
									innerHtml = "<span class='fc-icon fc-icon-" + normalIcon + "'></span>";
								}
								else {
									innerHtml = htmlEscape(defaultText);
								}

								classes = [
									'fc-' + buttonName + '-button',
									tm + '-button',
									tm + '-state-default'
								];

								button = $( // type="button" so that it doesn't submit a form
									'<button type="button" class="' + classes.join(' ') + '">' +
										innerHtml +
									'</button>'
									)
									.click(function(ev) {
										// don't process clicks for disabled buttons
										if (!button.hasClass(tm + '-state-disabled')) {

											buttonClick(ev);

											// after the click action, if the button becomes the "active" tab, or disabled,
											// it should never have a hover class, so remove it now.
											if (
												button.hasClass(tm + '-state-active') ||
												button.hasClass(tm + '-state-disabled')
											) {
												button.removeClass(tm + '-state-hover');
											}
										}
									})
									.mousedown(function() {
										// the *down* effect (mouse pressed in).
										// only on buttons that are not the "active" tab, or disabled
										button
											.not('.' + tm + '-state-active')
											.not('.' + tm + '-state-disabled')
											.addClass(tm + '-state-down');
									})
									.mouseup(function() {
										// undo the *down* effect
										button.removeClass(tm + '-state-down');
									})
									.hover(
										function() {
											// the *hover* effect.
											// only on buttons that are not the "active" tab, or disabled
											button
												.not('.' + tm + '-state-active')
												.not('.' + tm + '-state-disabled')
												.addClass(tm + '-state-hover');
										},
										function() {
											// undo the *hover* effect
											button
												.removeClass(tm + '-state-hover')
												.removeClass(tm + '-state-down'); // if mouseleave happens before mouseup
										}
									);

								groupChildren = groupChildren.add(button);
							}
						}
					});

					if (isOnlyButtons) {
						groupChildren
							.first().addClass(tm + '-corner-left').end()
							.last().addClass(tm + '-corner-right').end();
					}

					if (groupChildren.length > 1) {
						groupEl = $('<div/>');
						if (isOnlyButtons) {
							groupEl.addClass('fc-button-group');
						}
						groupEl.append(groupChildren);
						sectionEl.append(groupEl);
					}
					else {
						sectionEl.append(groupChildren); // 1 or 0 children
					}
				});
			}

			return sectionEl;
		}
		
		
		function updateTitle(text) {
			el.find('h2').text(text);
		}
		
		
		function activateButton(buttonName) {
			el.find('.fc-' + buttonName + '-button')
				.addClass(tm + '-state-active');
		}
		
		
		function deactivateButton(buttonName) {
			el.find('.fc-' + buttonName + '-button')
				.removeClass(tm + '-state-active');
		}
		
		
		function disableButton(buttonName) {
			el.find('.fc-' + buttonName + '-button')
				.attr('disabled', 'disabled')
				.addClass(tm + '-state-disabled');
		}
		
		
		function enableButton(buttonName) {
			el.find('.fc-' + buttonName + '-button')
				.removeAttr('disabled')
				.removeClass(tm + '-state-disabled');
		}


		function getViewsWithButtons() {
			return viewsWithButtons;
		}

	}

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
				imgs = {},
				last_form_value = {
					month: moment().month(),
					year: moment().year()
				};

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
						xhrFields: { withCredentials:true }
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
			T.cal.fullCalendar( 'gotoDate', get_goto_date() );
		}

		// get the date to goto
		function get_goto_date() { return moment( qt.isO( T.options.gotoDate ) || qt.isS( T.options.gotoDate ) ? T.options.gotoDate : moment() ); }

		// get the url params currently stored internal to this object
		function get_url_params() { return T.url_params; }
		// set the url params stored internal to this object
		function set_url_params( data ) { T.url_params = $.extend( true, {}, data ); }

		var footer, footer_element;
		// when rendering the header section of the calendar, we need to add the 'goto' form
		function add_header_elements( header_element, view ) {
			// add a footer
			if ( ! qt.is( this.footer ) ) {
				footer = this.footer = new Footer( this, this.options );
				footer_element = footer.render();
				footer_element.insertAfter( header_element.nextAll( '.fc-view-container:eq(0)' ) );
				footer_element.find( '.fc-center h2' ).remove();
				header_element = header_element.add( footer_element );
			}

			// add the goto form (which allows choosing a month and year, and navigating to it) and the view selector (allowing the switching of the calendar render view)
			header_element.each( function() {
				var he = $( this ),
						f = setup_goto_form( view.calendar ),
						v = setup_view_selector( view.calendar );

				// clear out the old goto form and add the one
				he.find( '.goto-form' ).remove();
				f.appendTo( he.find( '.fc-center' ) );

				// add the vie selector to the appropriate location
				v.appendTo( he.find( '.fc-left' ).empty() );
			} );
		}

		// when rendering the new view, we need trigger a header render event, used elsewhere, to force refresh the header
		function trigger_header_render_event( view, view_element ) {
			// update the date to show on the select boxes
			var new_date = view.calendar.getDate();
			last_form_value.month = new_date.month();
			last_form_value.year = new_date.year();

			// pop the loading overlay
			_loading( true, view );

			// render the header and footer
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
					if ( -1 !== $.inArray( name.toLowerCase(), [ 'basic', 'agenda' ] ) )
						continue;
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
			ret.find( '[rel="year"] option[value="' + last_form_value.year + '"]' ).prop( 'selected', 'selected' ).siblings( 'option' ).removeProp( 'selected' );
			ret.find( '[rel="month"] option[value="' + last_form_value.month + '"]' ).prop( 'selected', 'selected' ).siblings( 'option' ).removeProp( 'selected' );

			// setup the events for the goto button
			ret.find( '[rel="goto-btn"]' )
				.on( 'click.goto-form', function( e ) {
					e.preventDefault();
					var form = $( this ).closest( '.goto-form' );
					last_form_value = { month:form.find( '[rel="month"]' ).val(), year:form.find( '[rel="year"]' ).val() };
					T.cal.fullCalendar( 'gotoDate', moment( last_form_value ) );
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
			if ( qt.is( evt['short_description'] ) && ( section = inner.find( '.fc-short-description' ) ) && section.length )
				section.html( evt['short_description'] );
		}

		function _setup_goto_form() {}
	};
} )( jQuery, window, document, QS.Tools );

// on page load, start rendering the calendar
jQuery( function( $ ) { var cal = new QS.EventCalendar( _qsot_event_calendar_ui_settings ); } );
