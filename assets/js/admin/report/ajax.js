var QS = QS || { Tools:{} };
( function( $, qt ) {
	var S = $.extend( {}, _qsot_report_ajax );

	// add the select2 thing to use-select2 within context
	function _add_select2( context ) {
		// store the default matcher function for later use
		var default_matcher = $.fn.select2.defaults.matcher, context = context || 'body';

		// add the select2 to elements that need it
		QS.add_select2( $( context ).find( '.use-select2' ), {
			// change the matcher function so that it only matches events from the selected year
			matcher_func: function( ele ) {
				// is this select2 supposed to be filtered by another select box?
				var filter_by = $( ele.data( 'filter-by' ) );

				// if it is not supposed to be filtered, then just return a regular data function
				if ( ! filter_by.length )
					return default_matcher;
				// but if it is supposed to be filtered, return a data function that will do the filtering
				else
					return function( term, text, option ) {
						var key = filter_by.attr( 'id' ), val = filter_by.val();
						return ( ! qt.is( option[ key ] ) || parseInt( val ) == parseInt( option[ key ] ) ) && default_matcher.apply( this, [].slice.call( arguments ) );
					};
			}
		} );
	}

	// add table sorter
	function _add_tablesorter( context ) {
		var context = context || 'body';
		$( context ).find( '.use-tablesorter' ).tablesorter();
	}

	// handle the form actions
	$( document ).on( 'submit.qsot-reporting', '.qsot-ajax-form', function( e, extra_data, target, clear_other ) {
		e.preventDefault();
		var extra_data = extra_data || {}, target = target || $( '#report-results' ), data = $.extend( true, { action:'qsot-admin-report-ajax', _n:S._n }, $( this ).louSerialize(), extra_data );
				msg = $( '<h4></h4>' ), span = $( '<span>' + QS._str( 'Loading...', S ) + '</span>' ).appendTo( msg ),
				clear_other = clear_other || false;

		// if the new parent does not equal the old parent, then we are just refreshing the form
		if ( data.last_parent_id && data.parent_event_id != data.last_parent_id && ! target.is( '#report-form' ) ) {
			clear_other = target;
			target = $( '#report-form' );
		}

		// pop relevant loading messages
		msg.appendTo( target.empty() );
		_loading( msg, { width:span.outerWidth() } );
		if ( qt.isO( clear_other ) && clear_other.length )
			clear_other.empty();

		$.ajax( {
			url: ajaxurl,
			method: 'post',
			data: data,
			cache: false,
			dataType: 'html',
			xhrFields: { withCredentials: true },
			error: function() { console.log( 'Error:', [].slice.call( arguments ) ); },
			success: function( r ) {
				var result = $( $.trim( r ) ).appendTo( target.empty() );
				_add_select2( target );
				_add_tablesorter( target );
				target.find( '.use-tablesorter' ).each( function() {
					var col = $( this ).find( '.col-order_id' ), pos = col.prevAll( 'th' ).length - 1;
					if ( ! col.length )
						return;
					$( this ).trigger( 'sorton', [ [[pos,0]] ] );
				} );
				QS.cbs.trigger( 'report-loaded', [ result, target ] );
			}
		} );
	} );

	// when clicking a button that is marked as a button to refresh the form, then submit the form with an extra param saying that it should refresh the form, and make the target the form container
	$( document ).on( 'click', '.refresh-form', function( e ) {
		e.preventDefault();
		$( this ).closest( 'form' ).trigger( 'submit', [ { 'reload-form':1 }, $( '#report-form' ), $( '#report-results' ) ] );
	} );

  // on page load, add the select2 ui to any element that requires
  $( function() {
		_add_select2( 'body' );
		_add_tablesorter( 'body' );

		$( 'body' ).find( '.use-tablesorter' ).each( function() {
			var col = $( this ).find( '.col-order_id' ), pos = col.prevAll( 'th' ).length - 1;
			if ( ! col.length )
				return;
			$( this ).trigger( 'sorton', [ [[pos,0]] ] );
		} );
	} );

	function add_date_pickers(sel) {
		var dates = jQuery(sel).each( function() {
			var me = $( this ), real = me.attr( 'real' ), scope = me.attr( 'scope' ), frmt = me.attr( 'frmt' ), args = {
						defaultDate: "",
						dateFormat: "yy-mm-dd",
						numberOfMonths: 1,
						maxDate: "+5y",
						minDate: "-5y",
						showButtonPanel: true,
						showOn: "focus",
						buttonImageOnly: true
					};
			if ( 'undefined' != typeof real && null !== real ) {
				var alt = $( real, me.closest( scope || 'body' ) );
				if ( alt.length ) {
					args.altField = alt;
					args.altFormat = args.dateFormat;
					args.dateFormat = frmt || args.dateFormat;
				}
			}
			me.datepicker( args );
		} ).filter('.from').focus();
	}

	function _rajax(data, target) {
		var target = target || '#report_result';
		target = $(target);

		data.action = 'report_ajax';
		$.post(ajaxurl, data, function(r) {
			target.empty();
			$(r).appendTo(target);
			add_date_pickers($(".qsot-range-datepicker", target));
		}, 'html');
	};

	function _loading(on, settings) {
		var on = $(on);
		var settings = $.extend({
			height:10,
			width:'auto',
			blockWidth:25,
			color:'#000000',
			border:'1px solid #000000',
			speed:50 // pixels per second
		}, settings);
		settings.width = settings.width == 'auto' ? on.outerWidth() : settings.width;

		settings._bar = $('<div></div>').insertAfter(on).css({
			height:settings.height,
			width:settings.width,
			border:settings.border,
			position:'relative'
		});
		settings._block = $('<div></div>').css({
			backgroundColor:settings.color,
			width:settings.blockWidth,
			height:settings.height,
			position:'absolute',
			'top':0,
			left:0
		}).appendTo(settings._bar);

		settings._interval = (1/settings.speed) * 1000;
		settings._direction = 1;

		function _doit() {
			if ($(on).length) {
				var current = parseInt(settings._block.css('left'));

				if (current <= 0) {
					current = 0;
					settings._direction = 1;
				} else if (current >= settings.width - settings.blockWidth) {
					current = settings.width - settings.blockWidth;
					settings._direction = -1;
				}

				settings._block.css('left', current + settings._direction);
				setTimeout(_doit, settings._interval);
			}
		};

		setTimeout(_doit, 1);
	};

	$( document ).on( 'change', '.form-container form select[change-action]', function() {
		var f = $(this).closest('form');
		var data = f.louSerialize();
		data.raction = 'refresh-form';

		$('#report_result').empty();
		var target = $('#form_extended').empty();
		var msg = $('<h4></h4>').appendTo(target);
		var span = $('<span>Loading...</span>').appendTo(msg);
		_loading(msg, {width:span.outerWidth()});
		_rajax(data, target);
	});

	$( document ).on( 'submit', '.form-container form', function(e) {
		e.preventDefault();
		var f = $(this);

		var data = f.louSerialize();
		data.raction = data.action;

		if (data.raction == 'extended-form') {
			$('#report_result').empty();
			var target = $('#form_extended').empty();
			var msg = $('<h4></h4>').appendTo(target);
			var span = $('<span>Loading...</span>').appendTo(msg);
			_loading(msg, {width:span.outerWidth()});
			_rajax(data, target);
		} else {
			var target = $('#report_result').empty();
			var msg = $('<h4></h4>').appendTo(target);
			var span = $('<span>Loading (this could take a minute)...</span>').appendTo(msg);
			_loading(msg, {width:span.outerWidth()});
			_rajax(data, target);
		}

		return false;
	});

	$( document ).on( 'click', 'table th .sorter', function(e) {
		e.preventDefault();
		$('input[name="sort"]').val($(this).attr('sort')).closest('form').submit();
	});

	$(document).on('change', 'form .filter-list', function() {
		var fl = $(this);
		var f = fl.closest('form');
		var l = $(fl.attr('limit'), f);
		if (l.length <= 0) return;

		var p = $(l.attr('pool'));
		if (p.length <= 0) return;

		l.empty();
		p.find('option').filter('[lvalue="'+fl.val()+'"]').each(function() {
			$(this).clone().appendTo(l);
		});
	});
	$(function() { $('form .filter-list').change(); });

	$(function() { add_date_pickers(".qsot-range-datepicker"); });
} )( jQuery, QS.Tools );
