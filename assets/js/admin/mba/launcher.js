(function($){
	$.MBAMsgHandler = $.MBAMsgHandler || function(o) {
		this.o = $.extend({}, this._defs, {author:'loushou', version:'1.0-beta'}, o);
		this.init();
	}

	$.MBAMsgHandler.prototype = {
		_defs: { callback:function(){}, obj:this, once:true },

		init: function() {
			var self = this;
			var ts = (new Date()).getTime();
			$(window).bind('transmitMBAMsg.mbaMsgHandler'+ts, function(e, msg) {
				self.o.callback.apply(self.o.obj, [msg]);
				if (self.o.once) $(window).unbind('transmitMBAMsg.mbaMsgHandler'+ts);
			});
		}
	}
})(jQuery);

(function($) {
	function tb_click(){
		var t = this.title || this.name || null;
		var a = this.href || this.alt;
		var g = this.rel || false;
		tb_show(t,a,g);
		this.blur();
		//return false;
	}

	$(document).off('click.loumba', '.lou-mba-image-selector').on('click.loumba', '.lou-mba-image-selector', function(e) {
		e.preventDefault();
		tb_click.apply(this, [e]);
		var btn = $(this);
		btn.next('.error').remove();
		(new $.MBAMsgHandler({
			obj:btn,
			callback:function(msg) {
				if (typeof msg == 'object' && msg != null) {
					var rel = $(btn).parents((btn.attr('relative') == 'parent' ? btn.attr('parent') : btn.attr('relative')) || 'body').eq(0);
					$(btn.attr('alt-field'), rel).val(msg.alt).change();
					$('<img '
						+'src="'+msg.src+'" '
						+'title="'+escape(msg.title)+'" '
						+'alt="'+escape(msg.alt)+'" '
						+(msg.w ? 'width="'+msg.w+'" ' : '')
						+(msg.h ? 'height="'+msg.h+'" ' : '')
					+'/>').appendTo($(btn.attr('preview'), rel).empty());
					$(btn.attr('src-field'), rel).val(msg.src).change();
					$(btn.attr('title-field'), rel).val(msg.title).change();
					$(btn.attr('id-field'), rel).val(msg.id).change();
				} else {
					$('<span class="error">A problem occured receiving the message from the mediabox.</span>').insertAfter(btn);
				}
				tb_remove();
			}
		}));
	});
	$(document).off('click.loumba', '.lou-mba-image-deselector').on('click.loumba', '.lou-mba-image-deselector', function(e) {
		e.preventDefault();
		var btn = $(this);
		var rel = $(btn).closest((btn.attr('relative') == 'parent' ? btn.attr('parent') : btn.attr('relative')) || 'body');
		var keys = ['id-field', 'src-field', 'title-field', 'alt-field'];
		for (i in keys) $(btn.attr(keys[i]), rel).val('');
		$(btn.attr('preview'), rel).empty()
	});
})(jQuery);
