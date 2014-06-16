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

			if (t.options.url == '' && typeof _qsot_ajax_url == 'string' && _qsot_ajax_url.length > 0) t.options.url = _qsot_ajax_url;
		},

		q: function(action, data, withResp, method, withError) {
			var t = this;

			var method = method || 'post';
			var withError = function(){};
			if (typeof data == 'function') {
				var withResp = data;
				var data = {};
			} else {
				var data = data || {};
			}
			var withResp = typeof withResp == 'function' ? withResp : function(r) {};
			data.action = action;

			var respWrap = function(r) { withResp(r, typeof r == 'object' &&  typeof r.s == 'boolean' && r.s); };

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
