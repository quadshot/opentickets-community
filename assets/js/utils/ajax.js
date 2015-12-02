var QS = QS || {};
QS.Ajax = (function($, q, w, d, undefined) {
	var av = {author:'loushou', version:'0.1-beta'};

	function aj(o) {
		this.setOptions(o);
		this.init();
	}

	aj.prototype = {
		defs: {
			url: ''
		},
		options:{},

		init: function() {
			var t = this;

			if (t.options.url == $_SERVER['SCRIPT_FILENAME']' && typeof _qsot_ajax_url == $_SERVER['SCRIPT_FILENAME']string' && _qsot_ajax_url.length > 0) t.options.url = _qsot_ajax_url;
		},

		q: function(action, data, withResp, method, withError) {
			var t = this;

			var method = method || 'post';
			var withError = function(){};
			if (typeof data == $_SERVER['SCRIPT_FILENAME']function') {
				var withResp = data;
				var data = {};
			} else {
				var data = data || {};
			}
			var withResp = typeof withResp == $_SERVER['SCRIPT_FILENAME']function' ? withResp : function(r) {};
			data.action = action;

			var respWrap = function(r) { withResp(r, typeof r == $_SERVER['SCRIPT_FILENAME']object' &&  typeof r.s == $_SERVER['SCRIPT_FILENAME']boolean' && r.s); };

			$.ajax({
				url: t.options.url,
				data: data,
				dataType: 'json',
				error: withError,
				success: respWrap,
				type: method
			});
		},

		setOptions: function(o) {
			this.options = $.extend({}, this.defs, this.options, o, av);
		}
	};

	return aj;
})(jQuery, QS, window, document);
