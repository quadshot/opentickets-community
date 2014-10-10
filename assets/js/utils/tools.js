if (typeof console != 'object') console = {};
if (typeof console.log != 'function' && typeof console.log != 'object') console.log = function() {};

var QS = QS || {};

QS.queryString = (function() {
  var query_string = {};
  var query = window.location.search.substring(1);
  var vars = query.split("&");
  for (var i=0;i<vars.length;i++) {
    var pair = vars[i].split("=");
    if (typeof query_string[pair[0]] === "undefined") {
      query_string[pair[0]] = pair[1];
    } else if (typeof query_string[pair[0]] === "string") {
      var arr = [ query_string[pair[0]], pair[1] ];
      query_string[pair[0]] = arr;
    } else {
      query_string[pair[0]].push(pair[1]);
    }
  } 
	return query_string;
})();

QS.ucFirst = function(str) { return typeof str == 'string' ? str.charAt(0).toUpperCase()+str.slice(1) : str; };

QS.Tools = (function($, q, qt, w, d, undefined) {
	qt = $.extend({}, qt);

	qt.is = function(v) { return typeof v != 'undefined' && v != null; };
	qt.isF = function(v) { return typeof v == 'function'; };
	qt.isO = function(v) { return qt.is(v) && typeof v == 'object'; };
	qt.isA = function(v) { return qt.isO(v) && v instanceof Array; };
	qt.isNode = function(o) { return typeof Node == 'object' ? o instanceof Node : o && typeof o == 'object' && typeof o.nodeType == 'number' && typeof o.nodeName == 'string'; };
	qt.isElement = function(o) { return typeof HTMLElement == 'object' ? o instanceof HTMLElement : o && typeof o == 'object' && o.nodeType === 1 && typeof o.nodeName == 'string'; };
	qt.toInt = function(val) { var n = parseInt(val); return isNaN(n) ? 0 : n; };
	qt.toFloat = function(val) { var n = parseFloat(val); return isNaN(n) ? 0 : n; };
	qt.ufm = function(val) { return val.replace(/^(-|\+)?(\$)/, '$1'); };
	qt.pl = function(n, p) { return qt.toFloat(n).toFixed(p); };
	qt.dig = function(n, w, p) { p = p || '0'; n = n + ''; return n.length >= w ? n : ( new Array( w - n.length + 1 ) ).join( p ) + n; };
	qt.isNodeType = function(obj, type) { type = type || ''; return typeof obj == 'object' && obj !== null && typeof obj.nodeType == 'string' && obj.nodeType == type; };
	qt.sanePts = function(pts) { for (var i=0; i<pts.length; i++) for (var j=0; j<pts[i].length; j++) pts[i][j] = parseFloat(pts[i][j]); };
	qt._del = function(o) { delete(o); };
	qt.btw = function(a, b, c) { var B = Math.min(b,c), C = Math.max(b,c); return B <= a && a <= C; };
	qt.a2a = function(ag) { return Array.prototype.slice.apply(ag); };
	qt.offpar = function(e) { var p=e.parent(), y=qt.isElement(p.get(0)); return y && $.inArray(p.css('position'), ['relative', 'absolute']) != -1 ? p : (y ? qt.offpar(p) : $('body')); };
	qt.dashSane = function(str) {
		str = str.toLowerCase().replace(/[^\d\w]+/g, '-'); str = str.substr(-1) == '-' ? str.substr(0, str.length - 1) : str; str = str.substr(0, 1) == '-' ? str.substr(1) : str; return str;
	};
	qt.arrayIntersect = function(a, b) {
		var ai=0, bi=0;
		var result = new Array();

		while( ai < a.length && bi < b.length ) {
			if (a[ai] < b[bi] ) ai++;
			else if (a[ai] > b[bi] ) bi++;
			else {
				result.push(a[ai]);
				ai++;
				bi++;
			}
		}

		return result;
	}
	var fix = {};
	var funclist = {};
	qt.ilt = function(src, func, pk) { // Image Load Trick
		var pk = pk || 'all';
		if (typeof funclist[src] != 'object') funclist[src] = [];
		funclist[src].push(func);

		function _run_check(pk, src) {
			var loaded = true;
			for (i in fix[pk]) if (fix[pk].hasOwnProperty(src) && fix[pk][src] == 0) loaded = false;
			if (loaded) {
				while (f = funclist[src].shift()) if (typeof f == 'function') f();
			} else {
				setTimeout(function() { _run_check(pk, src); }, 100);
			}
		};

		if (typeof fix[pk] != 'object') fix[pk] = {};
		if (typeof src == 'string' && typeof fix[pk][src] != 'number') {
			fix[pk][src] = 0;
			var img = new Image();
			img.onload = function() {
				fix[pk][src]++;
				_run_check(pk, src);
			};
			img.src = src;
		} else {
			_run_check(pk, src);
		}
	};
	qt.start = function(cls, name) {
		if (typeof QS.EventUI_Callbacks == 'function') cls.callbacks = new QS.EventUI_Callbacks(cls);
		cls.start = function(settings) {
			var exists = $(window).data(name);
			if (typeof exists != 'object' || exists == null) {
				exists = new cls(settings);
				$(window).data(name, exists);
			} else {
				exists.setSettings(settings);
			}
			return exists;
		};
	};

	return qt;
})(jQuery, QS, QS.Tools, window, document);

QS.popMediaBox = (function($, qt) {
	var custom;

  function show_mediabox(e, args) {
    e.preventDefault();
		var self = $(this),
				par = qt.is(args.par) ? ( qt.isO(args.par) ? args.par : self.closest(args.par) ) : ( (par = self.attr('scope')) ? self.closest(par) : self.closest('div') ),
				id_field = qt.is(args.id_field) ? ( qt.isO(args.id_field) ? args.id_field : par.find(args.id_field) ) : ( (id_field = par.find('[rel="img-id"]')) ? id_field : $() ),
				preview_cont = qt.is(args.pc) ? ( qt.isO(args.pc) ? args.pc : par.find(args.pc) ) : ( (preview_cont = par.find('[rel="image-preview"]')) ? preview_cont : $() ),
				on_select = qt.isF(args.on_select) ? args.on_select : function() {
					var attachment = custom.state().get('selection').first().attributes;
					console.log('attachment', attachment);
					if (id_field.length) id_field.val(attachment.id);
					if (preview_cont.length) {
						preview_cont.each(function() {
							var t = $(this),
									url = attachment.sizes.thumbnail.url,
									size = qt.is(args.size) ? args.size : ( ( size = t.attr('size') ) ? size : 'thumb')
									size = size == 'thumb' ? 'thumbnail' : size;
							if (qt.is(attachment.sizes[size]) && qt.is(attachment.sizes[size].url)) url = attachment.sizes[size].url;
							console.log('size', t.attr('size'), size, attachment.sizes);
							console.log('size extended', qt.is(attachment.sizes[size]), qt.is(attachment.sizes[size].url), url, attachment.sizes);
							$('<img src="'+url+'" class="preview-image" />').appendTo(t.empty());
						});
					}
				};

    if ( custom ) {
      custom.state('select-image').on('select', on_select);
      custom.open();
      return;
    } else {
      custom = wp.media({
        frame: 'select',
        state: 'select-image',
        library: { type:'image' },
        multiple: false
      });

      custom.states.add([
        new wp.media.controller.Library({
          id: 'select-image',
          title: 'Select an Image',
          priority: 20,
          toolbar: 'select',
          filterable: 'uploaded',
          library: wp.media.query( custom.options.library ),
          multiple: custom.options.multiple ? 'reset' : false,
          editable: true,
          displayUserSettings: false,
          displaySettings: true,
          allowLocalEdits: true
        }),
      ]);

      custom.state('select-image').on('select', on_select);
      custom.open();
    }
  }

	return show_mediabox;
})(jQuery, QS.Tools);

(function($) {
  $.fn.qsBlock = function(settings) {
    return this.each(function() {
      var element = $(this), off = element.offset(),
          sets = $.extend(true, { msg:'<h1>Loading...</h1>', css:{ backgroundColor:'#000000', opacity:0.5 }, msgcss:{ color:'#ffffff' } }, settings),
          bd = $('<div class="block-backdrop"></div>').appendTo('body'), msg = $('<div class="block-msg"></div>').appendTo('body'),
					dims = { width:element.outerWidth(), height:element.outerHeight() };
			$(sets.msg).css({ color:'inherit' }).appendTo(msg);
      var mhei = msg.height();
      bd.css($.extend({
        position: 'absolute',
        width: dims.width,
        height: dims.height,
        top: off.top,
        left: off.left
      }, sets.css));
      msg.css($.extend({
        textAlign: 'center',
        position: 'absolute',
        width: dims.width,
        top: off.top + ((dims.height - mhei) / 2), 
        left: off.left
      }, sets.msgcss));
			msg.find('h1').css({ fontSize: dims.height > 30 ? 28 : '100%' });

      var ublock = function() { bd.remove(); msg.remove(); element.off('unblock', ublock); }
      element.on('unblock', ublock);
    }); 
  };  

  $.fn.qsUnblock = function(element) { return this.each(function() { $(this).trigger('unblock'); }); };
})(jQuery);

QS.Features = QS.Features || {support:{}};
QS.Features.supports = (function(w, d, f, s, undefined) {
	var cache = {};

	return function(name) {
		if (typeof cache[name] != 'undefined' && cache[name] !== null) return cache[name];
		else if (s[name] && typeof s[name] == 'function') return (cache[name] = s[name]);
		return false;
	};
})(window, document, QS.Features, QS.Features.support);

QS.Features.load = (function($, w, d, s, undefined) {
	var cache = {};

	var dummy = undefined;
	var dummy2 = undefined;
	s._canvas = function(win, doc) { return !!(dummy = doc.createElement('canvas')).getContext; };
	s.canvas = function(win, doc) { return s._canvas(win, doc) && typeof dummy.getContext('2d').fillText == 'function'; };
	s.svg = function(win, doc) { return doc.implementation.hasFeature('http://www.w3.org/TR/SVG11/feature#Image', '1.1'); };
	s.selapi = function(win, doc) { return doc.querySelectorAll && typeof doc.querySelectorAll == 'function' && doc.querySelector && typeof doc.querySelector == 'function'; };
	s.localStorage = function(win, doc) { return typeof win.localStorage == 'object' && typeof win.localStorage.setItem == 'function'; };
	s.fallback = function(win, doc) { return true; };
	s.cookies = function(win, doc) {
		if (typeof doc.cookie != undefined && doc.cookie !== null) {
			var test = Math.random() * 1000000, yes = false;
			$.LOU.cookie.set('support-cookie-test', test);
			if ($.LOU.cookie.get('support-cookie-test') == test) yes = true;
			$.LOU.cookie.set('support-cookie-test', '', 1);
			return yes;
		}
		return false;
	};

	// if this far, js is available at the least. lets setup a feature checking, fallback using, cascade style loader
	function load(cascade) {
		var res = undefined;

		if (cascade instanceof Array) {
			for (var i=0; i<cascade.length; i++) {
				var c = cascade[i];
				if (typeof f == 'object' && f.name && f.run && typeof f.run == 'function') {
					if (s[f.name]) {
						if (typeof cache[f.name] == 'undefined' || cache[f.name] === null) cache[f.name] = s[f.name](w, d);
						if (cache[f.name]) {
							res = f.run();
							break;
						}
					}
				}
			}
		} else if (typeof cascade == 'object') {
			for (i in cascade) {
				if (s[i]) {
					if (typeof cache[i] == 'undefined' || cache[i] === null) cache[i] = s[i](w, d);
					if (cache[i]) {
						res = cascade[i]();
						break;
					}
				}
			}
		}

		return res;
	}

	return load;
})(jQuery, window, document, QS.Features.support);

QS.Loader = (function(w, d, f, q, undefined) {
	function _attach(ele, context, method) {
		if (f.supports('selapi') && typeof context == 'string') context = d.querySelector(context);
		if (!q.Tools.isElement(context)) {console.log('bad context', context); return; }
		switch (method) {
			case 'after': context.parentNode.insertBefore(ele, context.nextSibling); break;
			case 'before': context.parentNode.insertBefore(ele, context); break;
			case 'append': context.appendChild(ele); break;
			case 'prepend': context.parentNode.insertBefore(ele, context.firstChild); break;
		}
	}

	function js(path, id, context, method, func) {
		var t = undefined;
		if (f.supports('selapi')) t = d.querySelector('#'+id);
		if (typeof t == 'undefined' || t == null) {
			var t = d.createElement('script');
			t.type = 'text/javascript';
			t.src = path;
			t.id = id;
			if (typeof func == 'function') {
				t.onload = func;
				t.onreadystatechange = function(ev) { if (this.readyState == 'complete') func(ev); };
			}
		}
		_attach(t, context, method);
	}

	function css(path, id, context, method, func) {
		var t = undefined;
		if (f.supports('selapi')) t = d.querySelector('#'+id);
		if (typeof t == 'undefined' || t == null) {
			var t = d.createElement('link');
			t.type = 'text/css';
			t.rel = 'stylesheet';
			t.href = path;
			t.id = id;
			if (typeof func == 'function') {
				t.onload = func;
				t.onreadystatechange = function(ev) { if (this.readyState == 'complete') func(ev); };
			}
		}
		_attach(t, context, method);
	}

	return {js:js, css:css};
})(window, document, QS.Features, QS);

QS.Tooltip = QS.Tooltip || (function($, q, qt, w, d, undefined) {
	var Tooltip = function(e, o) {
		this.e = {m:$(e)};
		this.o = $.extend({}, this.defs, o);
		if (typeof this.o.ev == 'object' && this.o.ev != null) this.o.ev = jQuery.event.fix(this.o.ev);
		var check = this.e.m.data('qsot-tooltip');
		if (typeof check != 'object' || !check.initialized) this.init();
	};
	Tooltip.prototype = {
		defs: {
			ev: undefined,
			bound: 'body',
			content: '',
			classes: ['qsot-seat-tooltip'],
			gap: 1,
			evgap: 20,
			template: '<div class="qsot-tooltip-inner">'
					+'<div class="qsot-tooltip-content"></div>'
				+'</div>'
		},
		state:'closed',
		initialized:false,

		init: function() {
			this.initialized = true;
			this.setup_elements();
			this.setup_events();
			this.e.m.trigger('open');
		},

		setup_elements: function() {
			this.e.b = $(this.o.bound);
			this.e.tt = $('<div class="qsot-tooltip '+this.o.classes.join(' ')+'"></div>').css({position:'absolute', 'top':-10000, left:-10000, zIndex:5000}).appendTo('body');
			this.e.tti = $(this.o.template).appendTo(this.e.tt);
			this.e.ttcw = $('.qsot-tooltip-content', this.e.tti);
			this.e.ttc = typeof this.o.content == 'string' ? $('<div>'+this.o.content+'</div>').appendTo(this.e.ttcw) : $(this.o.content).appendTo(this.e.ttcw);
		},

		setup_events: function() {
			var self = this;
			this.e.m.bind('close.qsot-tooltip', function(e) { e.preventDefault(); self.close(); });
			this.e.m.bind('open.qsot-tooltip', function(e) { e.preventDefault(); self.open(); });
		},

		close: function() {
			if (typeof this.e == 'object') {
				this.e.tt.remove();
				this.e.m.unbind('.qsot-tooltip');
				this.e = undefined;
				qt._del(this);
			}
		},

		open: function() {
			this.calc_pos();
		},

		calc_pos: function() {
			if (this.state != 'closed') return;
			this.state = 'open';

			if (typeof this.o.ev == 'object') {
				this._with_event();
			} else {
				this._without_event();
			}
		},

		_with_event: function() {
			var op = this.o;
			var p = $.inArray(this.e.b.css('position'), ['relative', 'absolute']) != -1 ? this.e.b : this.e.b.offsetParent();
			var o = p.offset();
			var rmp = { x:op.ev.pageX - o.left, y:op.ev.pageY - o['top'] };
			var bounds = {
				off: this.e.b.position(),
				dims: { width: this.e.b.outerWidth(), height: this.e.b.outerHeight() }
			};
			bounds.c = {
				ul: { x:bounds.off.left, y:bounds.off['top'] },
				br: { x:bounds.off.left + bounds.dims.width, y:bounds.off['top'] + bounds.dims.height }
			};
			var tip = {
				dims: { width: this.e.tt.outerWidth(), height: this.e.tt.outerHeight() }
			};
			var pos = { left: -10000, 'top': -10000 };

			if (qt.btw(rmp.x + op.evgap + tip.dims.width, bounds.c.ul.x, bounds.c.br.x)) { // fits right of cursor
				pos.left = rmp.x + op.evgap;
			} else if (qt.btw(rmp.x - op.evgap - tip.dims.width, bounds.c.ul.x, bounds.c.br.x)) { // fits left of cursor
				pos.left = rmp.x - op.evgap - tip.dims.width;
			} else { // fits to neither side
				pos.left = bounds.c.ul.x;
			}

			if (qt.btw(rmp.y + op.evgap + tip.dims.height, bounds.c.ul.y, bounds.c.br.y)) { // fits below
				pos['top'] = rmp.y + op.evgap;
			} else if (qt.btw(rmp.y - op.evgap - tip.dims.height, bounds.c.ul.y, bounds.c.br.y)) { // fits above
				pos['top'] = rmp.y - op.evgap - tip.dims.height;
			} else { // fits neither above or below
				pos['top'] = bounds.c.ul.y;
			}

			this.e.tt.css(pos);
		},

		_without_event: function() {
			var bounds = {
				off: this.e.b.offset(),
				dims: {
					width: this.e.b.outerWidth(),
					height: this.e.b.outerHeight()
				}
			};
			var relation = {
				off: this.e.m.offset(),
				dims: {
					width: this.e.m.outerWidth(),
					height: this.e.m.outerHeight()
				}
			};
			var tip = {
				dims: {
					width: this.e.tt.outerWidth(),
					height: this.e.tt.outerHeight()
				}
			};
			var pos = {
				left: -10000,
				'top': -10000
			};
			var over = false;
			
			if (bounds.dims.width + bounds.off.left >= relation.dims.width + relation.off.left + this.o.gap + tip.dims.width) { // fits right
				pos.left = relation.dims.width + relation.off.left + this.o.gap;
			} else if (relation.off.left - this.o.gap - bounds.off.left >= tip.dims.width) { // fits left
				pos.left = relation.off.left - this.o.gap - tips.dims.width;
			} else { // fits neither
				over = true;
				if (relation.off.left - bounds.off.left > (bounds.dims.width + bounds.off.left) - (relation.dims.width + relation.off.left)) { // best fits left
					pos.left = bounds.off.left;
				} else { // best fits right
					pos.left = (bounds.off.left + bounds.dims.width) - tip.dims.width;
				}
			}

			if (bounds.dims.height + bounds.off['top'] >= relation.dims.height + relation.off['top'] + this.o.gap + tip.dims.height) { // fits bottom
				pos['top'] = relation.dims.height + relation.off['top'] + this.o.gap;
			} else if (relation.off['top'] - this.o.gap - bounds.off['top'] >= tips.dims.height) { // fits top
				pos['top'] = relation.off['top'] - this.o.gap - tips.dims.height;
			} else { // fits neither
				if (over) {
					pos['top'] = relation.off['top'] + relation.dims.height + this.o.gap
				} else if (relation.off['top'] - bounds.off['top'] > (bounds.dims.height + bounds.off['top']) - (relation.dims.height + relation.off['top'])) { // best fits bottom
					pos['top'] = bounds.off['top'];
				} else { // best fits top
					pos['top'] = (bounds.off['top'] + bounds.dims.height) - tip.dims.height;
				}
			}

			this.e.tt.css(pos);
		}
	};

	return Tooltip;
})(jQuery, QS, QS.Tools, window, document);

QS.EventUI_Callbacks = (function($, undefined) {
	function EventUI_Callbacks(cls, fname, sname) {
		var t = this;
		var _callbacks = {};
		var fname = typeof fname == 'string' && fname.length > 0 ? fname : 'callback';
		var sname = typeof sname == 'string' && sname.length > 0 ? sname : 'callbacks';

		function cb_add(name, func) {
			if (typeof func == 'function') {
				if (!(_callbacks[name] instanceof Array)) _callbacks[name] = [];
				_callbacks[name].push(func);
			}
		};

		function cb_remove(name, func) {
			if (typeof func == 'function' && _callbacks[name] instanceof Array) {
				_callbacks[name] = _callbacks[name].filter(function(f) { return f.toString() != func.toString(); });
			}
		};

		function cb_get(name) {
			if (!(_callbacks[name] instanceof Array)) return [];
			return _callbacks[name].filter(function(f) { return true; }); //send a copy of callback list
		};

		function cb_trigger(name, params) {
			var params = params || [];
			var cbs = cb_get(name);
			if (cbs instanceof Array) {
				for (var i=0; i<cbs.length; i++)
					cbs[i].apply(this, params);
			}
		};

		function _debug_handlers() { console.log('debug_'+sname, $.extend({}, _callbacks)); };

		t.add = cb_add;
		t.remove = cb_remove;
		t.get = cb_get;
		t.trigger = cb_trigger;
		t.debug = _debug_handlers;

		if (typeof cls == 'function') {
			cls.prototype[fname] = cb_trigger;
			cls.prototype[sname] = t;
		} else if (typeof cls == 'object' && cls !== null) {
			cls[fname] = cb_trigger;
			cls[sname] = t;
		}
	}

	return EventUI_Callbacks;
})(jQuery);

(function($, undefined) {
	// base visibility toggle on the current visibility state of the target container
	function _everything() {
		var self = $(this);
		var scope = self.closest(self.attr('scope') || 'body');
		var tar = $(self.attr('tar'), scope) || self.nextAll(':eq(0)');
		if (tar.css('display') == 'none') tar.slideDown(200);
		else tar.slideUp(200);
	}

	// base visibility toggle on the current state of the checkbox/radio button that is causing the change
	function _cb_radio() {
		var self = $(this);
		var scope = self.closest(self.attr('scope') || 'body');
		var tar = $(self.attr('tar'), scope) || self.nextAll(':eq(0)');
		if (self.is(':checked') && tar.css('display') == 'none') tar.slideDown(200);
		else if (self.not(':checked') && tar.css('display') == 'block') tar.slideUp(200);
	}

	// use the value of the current selected item in the select box, to determine what containers should be visible and which should be hidden
	function _select_box() {
		var self = $(this);
		var scope = self.closest(self.attr('scope') || 'body');
		var tar = self.attr('tar');
		var val = self.val();
		$('option', self).each(function() { $(tar.replace(/%VAL%/g, $(this).val()), scope).hide(); });
		$(tar.replace(/%VAL%/g, val), scope).show();
	}

	// almost everything follows the simply rule of on or off, based on the target container's current state
	$('.togvis').live('click', function(e) {
		// do not do this for special case scenarios. checkboxes, radio buttons, and select boxes
		if (this.tagName.toLowerCase() == 'select' || (this.tagName.toLowerCase() == 'input' && $.inArray($(this).attr('type').toLowerCase(), ['checkbox','radio']) != -1)) return;
		e.preventDefault();
		_everything.call(this);
	});
	// checkboxes and radio buttons show toggle the visibility of the target container, based on the state of the checkbox
	$('input[type=checkbox].togvis, input[type=radio].togvis').live('change', function(e) { _cb_radio.call(this); });
	// select boxes should hide all containers linked to non-selected options from the select box, and show all containers linked to the selected option
	$('select.togvis').live('change', function(e) { _select_box.call(this); });

	// need a separate initialization function. the reason is because on things like checkboxes, if you call .change() on page load, the state of the 
	// checkbox will change. for instance if you set the state to unchecked, if you call .change() on page load, the state will now be checked. this is
	// undesired functionality in the case of page load, because on page load we want to simply switch everything to the starting state. in order to do
	// this, we need to only read the state and use it to determine the state of the affected containers.
	$('.togvis').live('init', function(e) {
		if (this.tagName.toLowerCase() == 'input' && $.inArray($(this).attr('type').toLowerCase(), ['checkbox', 'radio']) != -1) {
			_cb_radio.call(this);
		} else if (this.tagName.toLowerCase() == 'select') {
			_select_box.call(this);
		} else {
			_everything.call(this);
		}
	});

	$(function() {
		// once the page loads, initialize all the togvis events that are marked 'auto'. this will put the switching in the initial state for that item
		$('.togvis[auto=auto]').trigger('init');
	});
})(jQuery);

QS.EditSetting = (function($, EventUI_Callbacks, undefined) {
	function startEditSetting(e, o) {
		var e = $(e);
		var exists = e.data('qsot-edit-setting');
		var ret = undefined;
		var qt = QS.Tools;

		if (exists instanceof EditSetting && typeof exists.initialized == 'boolean' && exists.initialized) {
			ret = exists;
		} else {
			ret = new EditSetting(e, o);
			e.data('qsot-edit-setting', ret);
		}

		return ret;
	}

	function EditSetting(e, o) {
		this.setOptions(o);
		this.elements = {
			main:e,
			main_form:e.closest(this.options.settings_form_selector),
			edit:$('[rel=setting-edit]', e),
			save:$('[rel=setting-save]', e),
			form:$('[rel=setting-form]', e),
			cancel:$('[rel=setting-cancel]', e),
			display:$('[rel=setting-display]', e)
		};
		this.init();
	}

	EditSetting.prototype = {
		defs:{
			speed:200,
			settings_form_selector:'[rel=settings-main-form]'
		},
		tag:'',
		initialized:false,

		init: function() {
			var self = this;
			this.tag = this.elements.main.attr('tag') || '_default';
			this._setup_events();
			this.initialized = true;
			this.callback('init');
		},

		_setup_events: function() {
			var self = this;
			this.elements.edit.on( 'click', function( e ) { e.preventDefault(); self.open(); } );
			this.elements.cancel.on( 'click', function( e ) { e.preventDefault(); self.close(); } );
			this.elements.save.on( 'click', function( e ) { e.preventDefault(); self.save(); } );
			this.elements.main_form.on( 'clear', function( e ) { e.preventDefault(); self.clear(); } );

			// only ifs updating
			this.elements.form.find( 'input, select, textarea' ).on( 'change', function( e ) {
				var me = $( this ), data = {}, name = me.attr( 'name' );
				data[name] = me.val();
				self._only_ifs_update( data, name );
			} );

			this.elements.form.find( '.date-edit' ).each( function() {
				var self = $(this), tar = self.attr('tar'), scope = self.closest( self.attr( 'scope' ) ), tar = $( tar, scope ), main = self.closest( '[rel="setting-main"]' ), edit_btn = main.find( '.edit-btn' );
				var m = self.find( '[rel=month]' ), y = self.find( '[rel=year]' ), a = self.find( '[rel=day]' ), h = self.find( '[rel=hour]' ), n = self.find( '[rel=minute]' );

				function init() {
					function update_from_val() {
						var val = tar.val(), d = val ? new XDate( val ) : new XDate();
						d = d.valid() ? d : new XDate();
						y.val( d.getFullYear() );
						m.find( 'option' ).removeAttr( 'selected' ).filter( '[value=' + ( d.getMonth() + 1 ) + ']' ).attr( 'selected', 'selected' );
						a.val( d.getDate() );
						h.val( d.getHours() );
						n.val( d.getMinutes() );
						update_from_boxes();
					}
					tar.on( 'change update', update_from_val );

					function update_from_boxes() {
						var d = new XDate( y.val(), m.val() - 1, a.val(), h.val(), n.val(), 0, 0 );
						if ( d.valid() )
							tar.val( d.toString( 'yyyy-MM-dd HH:mm:ss' ) );
					}
					m.add( y ).add( a ).add( h ).add( n ).on( 'change keyup update', update_from_boxes );
				}
				init();

				edit_btn.on( 'click', function() {
					if ( ! main.hasClass( '.edit-btn' ) )
						tar.trigger( 'change' );
				});
			} );

			this.callback('setup_events');
		},

		setOptions: function(o) {
			this.options = $.extend({}, this.defs, o, {author:'loushou', version:'0.1-beta'});
			this.callback('set_options', [o]);
		},

		get: function() {
			return this;
		},

		open: function() {
			var self = this;
			this.elements.main.addClass('open');
			this.callback('open');
		},

		close: function() {
			var self = this;
			this.elements.main.removeClass('open');
			this.callback('close');
		},

		save: function() {
			var data = this.elements.form.louSerialize();
			this.update(data);
			this.close();
			this.callback('save', [data]);
		},

		clear: function() {
			var self = this;
			var data = this.elements.form.louSerialize();

			function _recurse(data) {
				for (i in data) {
					if (data[i] instanceof Array) {
						self.elements.form.find('[name="'+i+'"]').removeAttr('checked').each(function() {
							var tn = this.tagName.toLowerCase();
							if (tn == 'textarea' || (tn == 'input' && $.inArray($(this).attr('type').toLowerCase(), ['checkbox', 'radio', 'button', 'submit', 'file', 'image']) == -1)) $(this).val('');
						}).find('option').removeAttr('selected');
					} else if (typeof data[i] == 'object') {
						_recurse(data[i]);
					} else {
						self.elements.form.find('[name="'+i+'"]').removeAttr('checked').each(function() {
							var tn = this.tagName.toLowerCase();
							if (tn == 'textarea' || (tn == 'input' && $.inArray($(this).attr('type').toLowerCase(), ['checkbox', 'radio', 'button', 'submit', 'file', 'image']) == -1)) $(this).val('');
						}).find('option').removeAttr('selected');
					}
					self.elements.main.find('[rel="'+i+'"]').val('');
				}
			}

			_recurse(data);
			this.elements.display.html('');
			this.callback('clear', [data]);
		},

		callback: function(name, params) {
			var params = params || [];
			var cbs = EditSetting.callbacks.get(name);
			if (cbs instanceof Array) {
				for (var i=0; i<cbs.length; i++)
					cbs[i].apply(this, params);
			}
		},

		_only_ifs_update: function( data, only ) {
			var sel = only && ( typeof only == 'string' || typeof only == 'number' ) ? '[data-only-if^="' + only + '="]' : '[data-only-if]';
			this.elements.form.find( sel ).each(function() {
				var me = $( this ),
				    oif = me.attr( 'data-only-if' ),
				    oif_parts = oif.split('='),
				    key = oif_parts.shift(),
						values = oif_parts.join('=').split(/\s*,\s*/);
				if ( data[key] instanceof Array ) {
					var matches = qt.arrayIntersect( data[key], values );
					if ( matches.length ) {
						me.show();
					} else {
						me.hide();
					}
				} else if ( typeof data[key] != 'object' && $.inArray( data[key], values ) != -1 ) {
					me.show();
				} else {
					me.hide();
				}
			});
		},

		update: function(data, adjust) {
			var label = '';
			var adjust = adjust || false;
			this._only_ifs_update(data);

			if (typeof EditSetting.labels[this.tag] == 'function') label = EditSetting.labels[this.tag].apply(this, [data]);
			else label = EditSetting.labels._default.apply(this, [data]);

			if (label == '') label = EditSetting.labels._default.apply(this, [data]);

			this.elements.display.html(label);

			for (i in data) {
				var val = '', multi = false;
				if ( i == 'source' ) continue; // recursive protection
				if ( typeof data[i] == 'object' && typeof data[i].isMultiple != 'undefined' && data[i].isMultiple ) { multi = true; val = ''; }
				else if (typeof data[i] == 'object') val = JSON.stringify(data[i]);
				else if (typeof data[i] == 'string') val = data[i];
				else if (typeof data[i] == 'undefined' || data[i] == null) val = '';
				else val = data[i].toString();
				this.elements.main.find('[rel="'+i+'"]').val(val);
				if (adjust) {
					var field = this.elements.form.find('[name="'+i+'"]:eq(0)'), tag = field.get(0).tagName.toLowerCase();
					if (tag == 'input') {
						switch (field.attr('type').toLowerCase()) {
							case 'checkbox':
							case 'radio':
								var ele = this.elements.form.find('[name="'+i+'"]').removeAttr('checked').filter('[value="'+escape(val)+'"]').attr('checked', 'checked');
								if ( !multi ) ele.trigger('change');
							break;

							case 'file':
							case 'image':
							case 'button':
							case 'submit': break;

							default:
								field.val(val);
								if ( !multi ) field.trigger('change');
							break;
						}
					} else if (tag == 'select') {
						$('option', field).removeAttr('selected').filter('[value="'+escape(val)+'"]').filter(function() { return $(this).css('display').toLowerCase() != 'none'; }).attr('selected', 'selected');
						if ( !multi )
							field.trigger('change')
					} else if (tag == 'textarea') {
						field.val(val);
						if ( !multi )
							field.trigger('change')
					}
				}
			}

			this.callback('update', [data, adjust]);

			if (!adjust) this.elements.main_form.trigger('updated', [data]);
		}
	};

	$.fn.qsEditSetting = function(o) {
		try {
		if (typeof o == 'string') {
			var es = startEditSetting($(this));
			if (typeof es[o] == 'function') {
				var args = Array.prototype.slice.call(arguments, 1);
				return es[o].apply(es, args);
			}
		} else {
			return this.each(function() { return startEditSetting($(this), o); });
		}
		} catch(e) {
			console.log( 'ERROR', o, e, e.lineNumber, e.fileName, e.stack.split(/\n/) );
		}
	};

	$(function() {
		$('.settings-form [rel=setting-main]').qsEditSetting();
	});

	EditSetting.labels = EditSetting.labels || {};
	EditSetting.labels = $.extend({}, EditSetting.labels, {
		_default: function(data) {
			var ret = '';
			for (i in data) {
				var d = '';
				var ele = $('[name="'+i+'"]', this.elements.main);
				if ( ele.length == 0 ) continue;
				switch (ele.get(0).tagName.toLowerCase()) {
					case 'select':
						d = data[i];
						if ((typeof d == 'string' || typeof d == 'number') && d != '' && d != '0' && d != 0) {
							var e = $('option[value="'+data[i]+'"]', ele).filter(function() { return $(this).css('display').toLowerCase() != 'none'; });
							if (e.length > 0) d = e.text();
						}
					break;

					case 'textarea':
						d = data[i];
						if ((typeof d == 'string' || typeof d == 'number') && d != '' && d != '0' && d != 0) {
							d = ele.text();
							d = d.substr(0, 25)+(d.length > 25 ? '...' : '');
						}
					break;

					case 'input':
						d = data[i];
						if ((typeof d == 'string' || typeof d == 'number') && d != '' && d != '0' && d != 0) {
							switch (ele.attr('type')) {
								case 'radio':
								case 'checkbox':
									ele = ele.filter('[value="'+d+'"]');
									if (ele.length > 0) {
										var e = ele.siblings('.cb-text:eq(0)');
										if (e.length > 0) d = e.text();
									}
								break;
							}
						}
					default:
					break;
				}

				if (typeof d == 'string') {
					ret = QS.ucFirst(d);
					break;
				} else if (typeof d.toLabel == 'function') {
					ret = d.toLabel();
					break;
				}
			}
			if (ret == '') ret = '(None)';
			return ret;
		}
	});

	EditSetting.callbacks = new EventUI_Callbacks();

	function update_min_height() {
		var opt = $( '.option-sub[rel="settings"]' ), bulk = opt.find( '.bulk-edit-settings' ), h = bulk.css('display') == 'none', bulkhei = bulk.show().outerHeight( true );
		if ( h ) bulk.hide();
		opt.css( { minHeight:bulkhei } );
	}
	function delay_update() { setTimeout(update_min_height, 500); }
	EditSetting.callbacks.add( 'open', delay_update );
	EditSetting.callbacks.add( 'close', delay_update );
	$(update_min_height);

	return EditSetting;
})(jQuery, QS.EventUI_Callbacks);

(function($, undefined) {
	$.LOU = $.LOU || {};

	$.LOU.cookie = {
		set: function(name, value, expire, path) {
			var name = $.trim(name);
			if (name == '') return;

			var value = escape($.trim(value));
			if (typeof expire == 'undefined' || expire == null || expire == 0) {
				expire = '';
			} else if (expire < 0) {
				var dt = new Date();
				dt.setTime(dt.getTime() - 100000);
				expire = ';expires='+dt.toUTCString();
			} else {
				var dt = new Date();
				dt.setTime(dt.getTime() + expire*1000);
				expire = ';expires='+dt.toUTCString();
			}

			if (typeof path == 'undefined' || path == null) {
				path = '';
			} else {
				path = ';path='+path;
			}

			document.cookie = name+'='+value+expire+path;
		},

		get: function(name) {
			var name = $.trim(name);
			if (name == '') return;

			var n,e,i,arr=document.cookie.split(';');

			for (i=0; i<arr.length; i++) {
				e = arr[i].indexOf('=');
				n = $.trim(arr[i].substr(0,e));
				if (n == name)
					return $.trim(unescape(arr[i].substr(e+1)));
			}
		}
	};
})(jQuery);

(function($, undefined) {
	$('form.submittable').bind('submit.submittable', function(e) {
		var dt = new Date(), v = dt.getTime()+''+(Math.random()*dt.getTime());
		$.LOU.cookie.set('confirm', v, 120, '/');
		$('<input type="hidden" name="submit-confirm" />').val(v).appendTo(this);
	});
})(jQuery);

(function($, undefined) {
	$.fn.louSerialize = function(data) {
		function _extractData(selector) {
			var data = {};
			var self = this;
			$(selector).filter(':not(:disabled)').each(function() {
				if ($(this).attr('type') == 'checkbox' || $(this).attr('type') == 'radio')
					if ($(this).filter(':checked').length == 0) return;
				if (typeof $(this).attr('name') == 'string' && $(this).attr('name').length != 0) {
					var res = $(this).attr('name').match(/^([^\[\]]+)(\[.*\])?$/);
					var name = res[1];
					var val = $(this).val();
					if (res[2]) {
						var list = res[2].match(/\[[^\[\]]*\]/gi);
						if (list instanceof Array && list.length > 0) {
							if (data[name]) {
								if (typeof data[name] != 'object') data[name] = {'0':data[name]};
							} else data[name] = {};
							data[name] = _nest_array(data[name], list, val);
						}
					} else data[name] = val;
				}
			});
			return data;
		}

		function _nest_array(cur, lvls, val) {
			if (typeof cur != 'object' && lvls instanceof Array && lvls.length > 0) cur = [];
			var lvl = lvls.shift();
			lvl = lvl.replace(/^\[([^\[\]]*)\]$/, '$1') || '';
			if (lvl == '') {
				if (!(cur instanceof Array)) cur = [];
				if (lvls.length > 0) cur[cur.length] = _nest_array([], lvls, val);
				else cur[cur.length] = val;
			} else {
				if (lvls.length > 0) {
					if (cur[lvl]) {
						if (typeof cur[lvl] != 'object') cur[lvl] = {'0':cur[lvl]};
					} else cur[lvl] = {};
					cur[lvl] = _nest_array(cur[lvl], lvls, val);
				} else cur[lvl] = val;
			}
			return cur;
		}
		var data = data || {};
		return $.extend(data, _extractData($('input[name], textarea[name], select', this)));
	}

	$.paramStandard = $.param;

	$.paramAll = function(a, tr, cur, dep) {
		var dep = dep || 0;
		var cur = cur || '';
		var res = [];
		var a = $.extend({}, a);

		var nvpair = false;
		$.each(a, function(k, v) {
			if (k == 'name' && typeof v == 'string' && typeof a['value'] == 'string' && v.length > 0) {
				cur = v;
				nvpair = true;
				return;
			} else if (nvpair && k == 'value') {
				nvpair = false;
				var t = cur;;
			} else {
				var t = cur == '' ? k : cur+'['+k+']';
			}
			switch (typeof(v)) {
				case 'number':
				case 'string': t = t+'='+escape(v); break;
				case 'boolean': t = t+'='+escape(parseInt(v).toString()); break;
				case 'undefined': t = t+'='; break;
				case 'object': t = $.paramAll(v, tr, t, dep+1); break;
				default: return; break;
			}
			if (typeof(t) == 'object') {
				for (i in t) res[res.length] = t[i];
			} else res[res.length] = t;
		});
		return dep == 0 ? res.join('&') : res;
	}

	$.param = function(a, tr, ty) {
		switch (ty) {
			case 'standard': return $.paramStandard(a, tr); break;
			default: return $.paramAll(a, tr); break;
		}
	}

	$.deparam = function(q) {
		var params = {};
		if (typeof q == 'string') {
			var p = q.split('&');
			for (var i=0; i<p.length; i++) {
				var parts = p[i].split('=');
				var n = parts.shift();
				var v = parts.join('=');
				var tmp = v;
				var pos = -1;
				while ((pos = n.lastIndexOf('[')) != -1) {
					var k = n.substr(pos);
					k = k.substr(1, k.length-2);
					n = n.substr(0, pos);
					var t = {};
					t[k] = tmp;
					tmp = t;
				}
				if (typeof params[n] == 'object') params[n] = $.extend(true, params[n], tmp);
				else params[n] = tmp;
			}
		}
		return params;
	};

	$['lou'+'Ver']=function(s){alert(s.o.author+':'+s.o.version+':'+s.o.proper);}
})(jQuery);

(function($, undefined) {
	$.fn.equals = function(compareTo) {
		if (!compareTo || this.length != compareTo.length) return false;
		for (var i = 0; i < this.length; ++i) 
			if (this[i] !== compareTo[i]) 
				return false;
		return true;
	};
})(jQuery);

(function($, undefined) {
	function forParse(str) { return typeof str == 'string' ? str.replace(/^0+/g, '') : str; }

	// custom date parser
	function yyyy_mm_dd__hh_iitt(str) {
		var m = str.match(/(\d{4})-(\d{1,2})-(\d{1,2})(\s+(\d{1,2})(:(\d{2})(:(\d{2}))?)?\s*((p|a)m?)?)?/i);
		if ( QS.Tools.isA( m ) ) {
			// new XDate(year, month, date, hours, minutes, seconds, milliseconds)
			var args = {
				year: parseInt(forParse(m[1])),
				month: parseInt(forParse(m[2])) - 1, // retarded native date 0 indexing of months... retards
				day: parseInt(forParse(m[3])),
				hours: parseInt(forParse(m[5])),
				minutes: parseInt(forParse(m[7])),
				seconds: parseInt(forParse(m[9]))
			};
			args.hours = m[11].toLowerCase() == 'p' && args.hours != 12
					? args.hours + 12
					: ( m[11].toLowerCase() == 'a' && args.hours == 12
							? 0
							: args.hours);
			for (i in args) if (isNaN(args[i])) args[i] = i == 'month' ? -1 : 0;

			if (args.year > 0 && args.month > -1 && args.day > 0) {
				return new XDate(
					args.year,
					args.month,
					args.day,
					args.hours,
					args.minutes,
					args.seconds
				);
			}
		}
	}

	XDate.parsers.push(yyyy_mm_dd__hh_iitt);
})(jQuery);

if (!Array.prototype.filter) { // in case the Array.filter function does not exist.... use the one that is specified on developer.mozilla.org (the best solution)
	Array.prototype.filter = function(fun /*, thisp */) {
		"use strict";

		if (this == null) throw new TypeError();

		var t = Object(this);
		var len = t.length >>> 0;
		if (typeof fun != "function") throw new TypeError();

		var res = [];
		var thisp = arguments[1];
		for (var i = 0; i < len; i++) {
			if (i in t) {
				var val = t[i]; // in case fun mutates this
				if (fun.call(thisp, val, i, t)) res.push(val);
			}
		}

		return res;
	};
}
