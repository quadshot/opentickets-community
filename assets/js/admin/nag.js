var _qsot_nag_settings = _qsot_nag_settings || {};
(function($) {
	var qt = QS.Tools;

	if (!qt.isO(_qsot_nag_settings) || !qt.is(_qsot_nag_settings.title) || !qt.is(_qsot_nag_settings.layout)) return;
	var o = _qsot_nag_settings, par = undefined, otitem = undefined, nag = undefined;

	function aj(sa, data, func, efunc) {
		var data = data || {}, func = func || function(r) { console.log('success', r); }, efunc = efunc || function(x, s, e) { console.log('fail', x, s, e) };
		data.action = 'qsot-nag';
		data.sa = sa;
		$.ajax({
			url: ajaxurl,
			data: data,
			dataType: 'json',
			error: efunc,
			success: func,
			type: 'POST'
		});
	}

	$(document).on('click', '.qsot-nag-box .action', function(e) {
		var sa = $(this).attr('act');
		if (!sa) return;

		aj(sa, {}, function(r) {
			nag.find('[rel="title"]').html(r.msg);
			nag.find('[rel="content"]').html('');
			nag.find('[rel="answers"]').hide().find('[rel="right"], [rel="left"]').empty();
			nag.trigger('close', [ 1500 ]);
		}, function() { nag.trigger('close'); });
	});

	function close_nag(e, spd) {
		var spd = spd || 500;
		nag.fadeOut(spd);
		otitem.removeClass('qsot-special-arrow');
	}
	
	function setup_nag() {
		if (!par) par = $('#wpwrap').length ? $('#wpwrap') : $('body');
		otitem = otitem || $('#toplevel_page_opentickets');
		nag = nag || $(o.layout);

		otitem.addClass('qsot-special-arrow');
		nag.appendTo(par).css({ left:otitem.width(), top:otitem.position()['top'] - 10 });
		nag.off('close.qsot').on('close.qsot', close_nag);
		nag.find('[rel="title"]').html(o.title);
		nag.find('[rel="content"]').html(o.question);
		nag.find('[rel="answers"]').show().find('[rel="right"], [rel="left"]').empty();

		for (var i=0; i<o.answers.length; i++) {
			var ans = o.answers[i];
			var core = ans.type == 'link' ? $('<a href="#">'+ans.label+'</a>') : $('<input type="button" value="'+ans.label+'"/>');
			core.addClass('action');
			if (qt.is(ans.class)) core.addClass(ans.class);
			if (qt.is(ans.action)) core.attr('act' , ans.action);
			core.appendTo(nag.find('[rel="answers"]').find('[rel="'+ans.location+'"]'))
		}
	}
	$(setup_nag);
})(jQuery);
