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
	qt.isB = function(v) { return typeof v == 'boolean'; };
	qt.isS = function(v) { return typeof v == 'string'; };
	qt.isN = function(v) { return typeof v == 'number'; };
	qt.isC = function(v, c) { return qt.isO( v ) && v.constructor == c ; };
	qt.dist = function(x1, y1, x2, y2) { var dx = x1 - x2, dy = y1 - y2; return Math.sqrt( ( dx * dx ) + ( dy * dy ) ); };
	qt.ucw = function(v) { return v.toLowerCase().replace( /^[\u00C0-\u1FFF\u2C00-\uD7FF\w]|\b[\u00C0-\u1FFF\u2C00-\uD7FF\w]/g, function( l ) { return l.toUpperCase(); } ); };
	qt.esc = function(v) {
			var div, res;
			( div = document.createElement( 'div' ) ).appendChild( document.createTextNode( v ) );
			res = div.innerHTML;
			delete div;
			return res;
		};

	// i18n string please
	QS._str = qt.str = function( str, S ) { var S = $.extend( true, { str:{} }, S ); return qt.is( S.str[ str ] ) ? S.str[ str ] : str; };

	// template fetcher
	qt.tmpl = function( name, S ) { var S = $.extend( true, { templates:{} }, S ); return qt.is( S.templates[ name ] ) ? S.templates[ name ] : ''; }

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
		if (typeof QS.CB == 'function') cls.callbacks = new QS.CB(cls);
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

/* focus checker. when a specific element receives focus, check an adjacent checkbox or radio button */
( function( $, qt ) {
	$( document ).on( 'focus', '.focus-check', function() {
		var me = $( this ),
				scope = me.data( 'scope' ) || false,
				target = me.data( 'target' ) || false, ele;

		// if we do not have the needed data, then bail
		if ( ! scope || ! target )
			return;

		// if the supplied data does not point to an actual element, bail
		ele = $( scope ).find( target );
		if ( ! ele.length )
			return;

		ele.prop( 'checked', true );
	} );
} )( jQuery, QS.Tools );

QS.popMediaBox = (function($, qt) {
	var custom = {};

  function show_mediabox(e, args) {
    e.preventDefault();
		// before doing anything, make sure we have the media library box object. should prevent non-admin access
		if ( ! qt.isO( wp ) || ! qt.is( wp.media ) || ! qt.is( wp.media.controller ) )
			return;
		var self = $(this),
				args = $.extend( { type:'image' }, args ),
				// find the parent container of the button that triggered this lightbox. this is used to find the other elements of this tool, like the id and preview fields
				par = qt.is( args.par )
						? ( qt.isO( args.par ) ? args.par : self.closest( args.par ) )
						: ( ( par = self.attr( 'scope' ) ) ? self.closest( par ) : self.closest( 'div' ) ),
				// find the id storage field
				id_field = qt.is( args.id_field )
						? ( qt.isO( args.id_field ) ? args.id_field : par.find( args.id_field ) )
						: ( ( id_field = par.find( '[rel="img-id"]' ) ) ? id_field : $() ),
				// find the preview container
				preview_cont = qt.is( args.pc )
						? ( qt.isO( args.pc ) ? args.pc : par.find( args.pc ) )
						: ( ( preview_cont = par.find( '[rel="image-preview"]' ) ) ? preview_cont : $() ),
				// what extra steps to take once an attachment has been selected
				with_selection = qt.isF( args.with_selection ) ? args.with_selection : function( attachment ) {},
				// allow to completely over take the function that uses the selection. the default funciton fills the preview container and id fields
				on_select = qt.isF( args.on_select ) ? args.on_select : function() {
					// get the information about the selected image
					var attachment = custom[ args.type ].state().get( 'selection' ).first().attributes;
					// update the id field
					if ( id_field.length )
						id_field.val( attachment.id ).change();
					// update the preview container
					if ( preview_cont.length ) {
						preview_cont.each( function() {
							var t = $( this ),
									url = qt.is( attachment.sizes.full ) ? attachment.sizes.full.url : '',
									size = qt.is( args.size ) ? args.size : ( ( size = t.attr( 'size' ) ) ? size : 'thumb' )
									size = size == 'thumb' ? 'thumbnail' : size;
							// find the appropriate image url
							if ( qt.is( attachment.sizes[ size ] ) && qt.is( attachment.sizes[ size ].url ) )
								url = attachment.sizes[ size ].url;
							// if there is no image then empty the preview
							if ( '' == url )
								t.empty();
							// otherwise create the new preview
							else
								$( '<img src="' + url + '" class="preview-image" />' ).appendTo( t.empty() );
						} );
					}
					// run our additional logic
					with_selection( attachment );
				};

		// if the lightbox already exists, then use it
    if ( custom[ args.type ] ) {
      custom[ args.type ].state( 'select-image' ).off( 'select' ).on( 'select', on_select );
      custom[ args.type ].open();
      return;
		// otherwise create a new instance of the lightbox
    } else {
			// initialize the base lightbox
      custom[ args.type ] = wp.media( {
        frame: 'select',
        state: 'select-image',
        library: { type:args.types || 'image' },
        multiple: false
      } );

			// register teh lightbox with the lightbox controller library
      custom[ args.type ].states.add( [
        new wp.media.controller.Library( {
          id: 'select-image',
          title: 'Select an Image',
          priority: 20,
          toolbar: 'select',
          filterable: 'uploaded',
          library: wp.media.query( custom[ args.type ].options.library ),
          multiple: custom[ args.type ].options.multiple ? 'reset' : false,
          editable: true,
          displayUserSettings: false,
          displaySettings: true,
          allowLocalEdits: true
        } ),
      ] );

			// finalize the envents of the lightbox, and pop it
      custom[ args.type ].state( 'select-image' ).off( 'select' ).on( 'select', on_select );
      custom[ args.type ].open();
    }
  }

	return show_mediabox;
})(jQuery, QS.Tools);

( function( $, qt ) {
	// get all attributes of a given element
	QS.AllAttrs = QS.AllAttrs || function( ele ) {
		var output = {}, atts = ele[0].attributes, n = atts.length;
		for ( var att, i = 0; i < n; i++ ){
			att = atts[i];
			if ( 'string' === typeof att.nodeName )
				output[ att.nodeName ] = att.nodeValue;
		}
		return output;
	}

	// add the i18n datepicker to an element. i18n datepicker differs from a normal datepicker, because a normal datepicker will not adjust the display date format based on locale.
	// display format is passed to this function via element data, produced by the php code
	QS.DatepickerI18n = QS.DatepickerI18n || function( context, selector ) {
		var selector = selector || '.use-i18n-datepicker', // selector to identify elements that need the datepicker
				context = qt.is( context ) && context.length ? $( context ) : $( 'body' ); // context to search for selector within

		// for every element that needs a datepicker that does not already have one, attempt to apply one
		$( selector, context ).not( '.has-datepicker' ).each( function() {
			var me = $( this ),
					display_format = me.data( 'display-format' ) || 'mm-dd-yy',
					scope = me.closest( me.attr( 'scope' ) || 'body' ),
					role = me.attr( 'role' ) || 'standard',
					mode = me.data( 'mode' ) || 'normal',
					mode = mode.split( /\s*:\s*/ ),
					allow_blank = me.data( 'allow-blank' ) || false,
					initial_date = me.data( 'init-date' ) || me.val(),
					d = initial_date ? new Date( initial_date ) : ( allow_blank ? '' : new Date() ),
					d = new Date( initial_date ),
					min = me.data( 'min-date' ) || '',
					max = me.data( 'max-date' ) || '',
					def = me.data( 'default' ) || '',
					def = '' === initial_date ? ( qt.is( def ) ? def : d ) : d;

			// update the initial element to show it has a datapicker
			me.addClass( 'has-datepicker' );

			// if the selected element is not a hidden element, then make it so
			if ( 'hidden' !== me.attr( 'type' ).toLowerCase() ) {
				var atts = QS.AllAttrs( me );
				delete atts.type;
				var tmp = $( '<input type="hidden" />' ).attr( atts ).insertBefore( me );
				me.remove();
				me = tmp;
			}

			// create a display version above the hidden on
			var display = $( '<input type="text" />' ).insertBefore( me ).attr( { id:( me.attr( 'id' ) || me.attr( 'name' ) ) + '-display', role:role + '-display' } )
						.addClass( me.attr( 'class' ).replace( new RegExp( selector.replace( /^\.#/, '' ), 'g' ), '' ) );
			me.data( 'display', display );

			// setup the event that clears the hidden field when the display field is cleared
			display.on( 'change', function( e ) {
				if ( '' == $( this ).val() )
					me.val( '' );
			} );

			// setup some basic settings for the datepicker
			var args = {
				altField: me,
				altFormat: 'yy-mm-dd',
				dateFormat: display_format
			};

			// if there is a min-date, then set it
			if ( min )
				args.minDate = new Date( min );

			// if there is a max-date, then set it
			if ( max )
				args.maxDate = new Date( max );

			// depending on the 'mode' we may need to add elements and modify the args
			switch ( mode[0] ) {
				case 'icon':
					args.showOn = 'button';
					args.buttonText = '<span class="dashicons ' + ( qt.is( mode[1] ) ? mode[1] : 'dashicons-calendar-alt' ) + '"></span>';
				break;
			}

			// depending on the role, we may need to add some extra logic
			switch ( role ) {
				case 'from':
					args.onSelect = function( str, obj ) {
						var d;
						scope.find( '[role="to"]' ).each( function( ind ) {
							var other = $( this ).data( 'display' ),
									other_d = other.length ? other.datepicker( 'getDate' ) : d

							d = display.datepicker( 'getDate' );

							if ( other.length && d && other_d && d.getTime() > other_d.getTime() ) {
								other.datepicker( 'setDate', d );
							}
						} );

						var link_with = me.data( 'link-with' ) || false;
						link_with = link_with ? scope.find( link_with ).add( link_with ) : false;
						// update all 'link with' datepickers
						if ( link_with )
							link_with.each( function() {
								if ( ! $( this ).hasClass( 'has-datepicker' ) )
									return;
								var display = $( this ).data( 'display' );
								if ( qt.isO( display ) && display.length ) {
									display.datepicker( 'setDate', d );
									var on_sel = display.datepicker( 'option', 'onSelect' );
									if ( qt.isF( on_sel ) )
										on_sel( d );
								}
							} );
					};
				break;

				case 'to':
					args.onSelect = function( str, obj ) {
						var d;
						scope.find( '[role="from"]' ).each( function() {
							var other = $( this ).data( 'display' ),
									other_d = other.length ? other.datepicker( 'getDate' ) : d

							d = display.datepicker( 'getDate' );

							if ( other.length && d && other_d && d.getTime() < other_d.getTime() ) {
								other.datepicker( 'setDate', d );
							}
						} );

						var link_with = me.data( 'link-with' ) || false;
						link_with = link_with ? scope.find( link_with ).add( link_with ) : false;
						// update all 'link with' datepickers
						if ( link_with )
							link_with.each( function() {
								if ( ! $( this ).hasClass( 'has-datepicker' ) )
									return;
								var display = $( this ).data( 'display' );
								if ( qt.isO( display ) && display.length ) {
									display.datepicker( 'setDate', d );
									display.datepicker( 'option', 'onSelect' )( d );
								}
							} );
					};
				break;
			}

			// initialize the datepicker now
			display.datepicker( args );
			display.datepicker( 'setDate', def );
		} );
	}

	$( function() { QS.DatepickerI18n( 'body' ); } );
} )( jQuery, QS.Tools );

// on page load, if there are any mediabox
jQuery( function( $ ) {
	$( document ).on( 'click', '[rel="remove-img"]', function( e ) {
		e.preventDefault();
		var self = $( this ), par = self.closest( self.attr( 'scope' ) || 'div' );
		par.find( self.attr( 'preview' ) || '[rel="image-preview"]' ).empty();
		par.find( self.attr( 'id-field' ) || '[rel="img-id"]' ).val( '0' );
	} );

	$( document ).on( 'click', '.qsot-popmedia, [rel="qsot-popmedia"]', function( e ) {
		var self = $( this );
		QS.popMediaBox.apply( this, [ e, {
			with_selection: function( attachment ) {
				self.closest( self.attr( 'scope' ) ).removeClass( 'no-img' );
			}
		} ] );
	} );
} );

(function($) {
  $.fn.qsBlock = function(settings) {
    return this.each(function() {
      var element = $(this), position = element.css( 'position' ),
          sets = $.extend(true, { msg:'<h1>Loading...</h1>', css:{ backgroundColor:'#000000', opacity:0.5 }, msgcss:{ color:'#ffffff' } }, settings),
          bd = $('<div class="block-backdrop"></div>').appendTo( element ), msg = $('<div class="block-msg"></div>').appendTo( element ),
					dims = { width:element.outerWidth(), height:element.outerHeight() };
			if ( position == 'static' )
				element.css( { position:'relative' } );
			$(sets.msg).css({ color:'inherit' }).appendTo(msg);
      var mhei = msg.height();
      bd.css($.extend({
        position: 'absolute',
        width: 'auto',
        height: 'auto',
        top: 0, bottom:0,
        left: 0, right:0
      }, sets.css));
      msg.css($.extend({
        textAlign: 'center',
        position: 'absolute',
        width: dims.width,
        top: '50%',
        left: 0,
				marginTop: -mhei
      }, sets.msgcss));
			msg.find('h1').css({ fontSize: dims.height > 30 ? 28 : '100%' });

      var ublock = function() { element.css( { position:position } ); bd.remove(); msg.remove(); element.off('unblock', ublock); }
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
				var f = cascade[i];
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

QS.CB = (function($, undefined) {
	function CBs(cls, fname, sname) {
		var t = this,
				idx = 0,
				_callbacks = {},
				fname = typeof fname == 'string' && fname.length > 0 ? fname : 'callback',
				sname = typeof sname == 'string' && sname.length > 0 ? sname : 'callbacks';

		function cb_add(name, func) {
			var res = function() {};

			if (typeof func == 'function') {
				if (!(_callbacks[name] instanceof Array)) _callbacks[name] = [];
				var obj = { p:idx++, f:func };
				_callbacks[name].push( obj );
				res = function( priority ) { obj.p = priority; };
			}

			return res;
		};

		function cb_has( name ) {
			return ( 'undefined' != typeof _callbacks[ name ] && _callbacks[ name ] instanceof Array );
		};

		function cb_remove(name, func) {
			if (typeof func == 'function' && _callbacks[name] instanceof Array) {
				_callbacks[name] = _callbacks[name].filter(function(f) { return f.f.toString() != func.toString(); });
			} else if ( ( 'undefined' == typeof func || null === func ) && _callbacks[ name ] instanceof Array ) {
				delete _callbacks[ name ];
			}
		};

		function _ordered( a, b ) { return ( a.p < b.p ) ? -1 : ( ( a.p > b.p ) ? 1 : 0 ); };

		function cb_get(name) {
			if (!(_callbacks[name] instanceof Array)) return [];
			return _callbacks[name].filter(function(f) { return true; }).sort( _ordered ); //send a copy of callback list
		};

		function cb_trigger(name, params) {
			var params = params || [],
					cbs = cb_get(name), res;
			if (cbs instanceof Array) {
				for (var i=0; i<cbs.length; i++) {
					res = cbs[i].f.apply(this, params);
					if ( false === res )
						break;
				}
			}

			return res;
		};

		function _debug_handlers() { console.log('debug_'+sname, $.extend({}, _callbacks)); };

		t.add = cb_add;
		t.has = cb_has;
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

	return CBs;
})(jQuery);
QS.cbs = new QS.CB();

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

	// if the button has not been initialized yet, then do so
	function _maybe_init( me ) {
		var is_init = me.data( 'togvis-init' );
		if ( is_init != 1 ) {
			me.trigger( 'init' );
			return true;
		}
		return false;
	}

	// almost everything follows the simply rule of on or off, based on the target container's current state
	$( document ).on( 'click.togvis', '.togvis', function( e ) {
		if ( _maybe_init( $( this ) ) ) return;
		// do not do this for special case scenarios. checkboxes, radio buttons, and select boxes
		if (this.tagName.toLowerCase() == 'select' || (this.tagName.toLowerCase() == 'input' && $.inArray($(this).attr('type').toLowerCase(), ['checkbox','radio']) != -1)) return;
		e.preventDefault();
		_everything.call(this);
	});
	// checkboxes and radio buttons show toggle the visibility of the target container, based on the state of the checkbox
	$( document ).off( 'change.togvis', 'input[type=checkbox].togvis, input[type=radio].togvis' )
			.on('change.togvis', 'input[type=checkbox].togvis, input[type=radio].togvis', function(e) { _cb_radio.call(this); });
	// select boxes should hide all containers linked to non-selected options from the select box, and show all containers linked to the selected option
	$( document ).on( 'change.togvis', 'select.togvis', function(e) { _select_box.call(this); } );

	// need a separate initialization function. the reason is because on things like checkboxes, if you call .change() on page load, the state of the 
	// checkbox will change. for instance if you set the state to unchecked, if you call .change() on page load, the state will now be checked. this is
	// undesired functionality in the case of page load, because on page load we want to simply switch everything to the starting state. in order to do
	// this, we need to only read the state and use it to determine the state of the affected containers.
	$( document ).on( 'init.togvis', '.togvis', function(e) {
		$( this ).data( 'togvis-init', 1 )
		if (this.tagName.toLowerCase() == 'input' && $.inArray($(this).attr('type').toLowerCase(), ['checkbox', 'radio']) != -1) {
			_cb_radio.call(this);
		} else if (this.tagName.toLowerCase() == 'select') {
			_select_box.call(this);
		} else {
			_everything.call(this);
		}
	} );

	$(function() {
		// once the page loads, initialize all the togvis events that are marked 'auto'. this will put the switching in the initial state for that item
		$('.togvis[auto=auto]').trigger('init');
	});
})(jQuery);

QS.EditSetting = (function($, undefined) {
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
						m.find( 'option' ).removeProp( 'selected' ).filter( '[value=' + ( d.getMonth() + 1 ) + ']' ).prop( 'selected', 'selected' );
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
								var ele = this.elements.form.find('[name="'+i+'"]').removeProp('checked').filter('[value="'+escape(val)+'"]').prop('checked', 'checked');
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
						$('option', field).removeProp('selected').filter('[value="'+escape(val)+'"]').filter(function() { return $(this).css('display').toLowerCase() != 'none'; }).prop('selected', 'selected');
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

	EditSetting.callbacks = new QS.CB(EditSetting);

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
})(jQuery);

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
				var me = $( this );
				if (me.attr('type') == 'checkbox' || me.attr('type') == 'radio')
					if (me.filter(':checked').length == 0) return;
				if (typeof me.attr('name') == 'string' && me.attr('name').length != 0) {
					var res = me.attr('name').match(/^([^\[\]]+)(\[.*\])?$/), name = res[1], val = ! me.hasClass( 'wp-editor-area' ) || ! me.attr( 'id' ) ? me.val() : tinymce.editors[ me.attr( 'id' ) ].getContent();
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

	function _enc_special_chars( str ) {

	}

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
				case 'string': t = t+'='+encodeURIComponent(v); break;
				case 'boolean': t = t+'='+parseInt(v).toString(); break;
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
			//case 'standard':
			default: return $.paramStandard(a, tr); break;
			//default: return $.paramAll(a, tr); break;
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

( function( $, undefined ) {
	// allow for checkboxes to be specified that control enabling and disabling other form elements
	$( document ).on( 'change', '[data-toggle-disabled]', function( e ) {
		console.log( 'changed', me, 'scope', scope, 'tar', tar );
		var me = $( this ), scope = me.closest( me.attr( 'scope' ) || '.field' ), tar = scope.find( me.data( 'toggle-disabled' ) || me.attr( 'data-toggle-disabled' ) );
		// if there are actually elements to toggle the enabled status on, then
		if ( tar.length ) {
			// if the box is checked, disable them.
			if ( me.is( ':checked' ) )
				tar.prop( 'disabled', 'disabled' );
			// if un checked, enabled them
			else
				tar.removeProp( 'disabled' );
		}
	} );

	// allow for checkboxes to be specified that control enabling and disabling other form elements
	$( document ).on( 'change', '[data-toggle-enabled]', function( e ) {
		var me = $( this ), scope = me.closest( me.attr( 'scope' ) || '.field' ), tar = scope.find( me.data( 'toggle-enabled' ) || me.attr( 'data-toggle-enabled' ) );
		// if there are actually elements to toggle the enabled status on, then
		if ( tar.length ) {
			// if the box is checked, enable them.
			if ( me.is( ':checked' ) )
				tar.removeProp( 'disabled' );
			// if un checked, enabled them
			else
				tar.prop( 'disabled', 'disabled' );
		}
	} );
} )( jQuery );

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
