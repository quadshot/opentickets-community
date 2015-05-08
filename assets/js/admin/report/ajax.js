(function($) {
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

	$('form select[change-action]').live('change', function() {
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

	$('form').live('submit', function(e) {
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

	$('table th .sorter').live('click', function(e) {
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
})(jQuery);
