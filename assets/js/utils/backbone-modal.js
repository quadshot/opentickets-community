/*global jQuery, Backbone, _ */
( function( $, Backbone, _ ) {
	'use strict';

	/**
	 * QSOT Backbone Modal plugin
	 *
	 * @param {object} options
	 */
	$.fn.QSOTBackboneModal = function( options ) {
		if ( 'get' == options )
			return this.data( 'qsot-dialog' );
		return this.each( function() {
			( new $.QSOTBackboneModal( $( this ), options ) );
		});
	};

	/**
	 * Initialize the Backbone Modal
	 *
	 * @param {object} element [description]
	 * @param {object} options [description]
	 */
	$.QSOTBackboneModal = function( element, options ) {
		// Set settings
		var settings = $.extend( {}, $.QSOTBackboneModal.defaultOptions, options );

		if ( settings.template || settings.template_element ) {
			element.data( 'qsot-dialog', 
				new $.QSOTBackboneModal.View({
					target: settings.template,
					target_element: $( settings.template_element )
				})
			);
		}
	};

	/**
	 * Set default options
	 *
	 * @type {object}
	 */
	$.QSOTBackboneModal.defaultOptions = {
		template: '',
		template_element: $()
	};

	/**
	 * Create the Backbone Modal
	 *
	 * @return {null}
	 */
	$.QSOTBackboneModal.View = Backbone.View.extend({
		tagName: 'div',
		id: 'wc-backbone-modal-dialog',
		_target: undefined,
		events: {
			'click .modal-close': 'closeButton',
			'keydown':            'keyboardActions'
		},

		initialize: function( data ) {
			this._target = data.target;
			this._target_element = data.target_element;
			_.bindAll( this, 'render' );
			this.render();
			this.open();
		},

		render: function() {
			this.$el.hide().attr( 'tabindex' , '0' ).append( this._target_element.length > 0 ? $( this._target_element ) : $( this._target ).html() );

			$( 'body' ).css({
				'overflow': 'hidden'
			}).append( this.$el );

			this.adjust_size();

			$( 'body' ).trigger( 'qsot_backbone_modal_loaded', this._target );

			return this;
		},

		adjust_size: function() {
			var $content = $( '.wc-backbone-modal-content' ).find( 'article' ),
					cur_h = ( 0 === $content.height() ) ? 90 : $content.outerHeight();
			$content.css( { height:'auto' } );
			var content_h = ( 0 === $content.outerHeight() ) ? 90 : $content.outerHeight(),
					max_h = $( window ).height() - 250;
			$content.css( { height:cur_h } );

			if ( content_h > max_h ) {
				$content.css({
					'overflow': 'auto',
					height: max_h + 'px'
				});
			} else {
				$content.css({
					'overflow': 'visible',
					height: content_h
				});
			}

			/*
			$( '.wc-backbone-modal-content' ).css({
				'margin-top': '-' + ( $( '.wc-backbone-modal-content' ).height() / 2 ) + 'px',
				'margin-left': '-' + ( $( '.wc-backbone-modal-content' ).width() / 2 ) + 'px'
			});
			*/

			return this;
		},

		set_content: function( settings ) {
			this._target = settings.target;
			this._target_element = settings.target_element;

			this.$el.empty().attr( 'tabindex' , '0' ).append( this._target_element.length > 0 ? $( this._target_element ) : $( this._target ).html() );
			this.adjust_size();

			return this;
		},

		open: function( e ) {
			this.delegateEvents();
			this.$el.show();

			return this;
		},

		close: function( e ) {
			// fix scroll... sigh
			this.undelegateEvents();
			$( document ).off( 'focusin' );
			$( 'body' ).css({ 'overflow': 'auto' });

			this.$el.hide();

			return this;
		},

		closeButton: function( e ) {
			e.preventDefault();
			this.close();
		},

		addButton: function( e ) {
			$( 'body' ).trigger( 'qsot_backbone_modal_response', [ this._target, this.getFormData() ] );
			this.closeButton( e );
		},

		getFormData: function() {
			var data = {};

			$.each( $( 'form', this.$el ).serializeArray(), function( index, item ) {
				if ( data.hasOwnProperty( item.name ) ) {
					data[ item.name ] = $.makeArray( data[ item.name ] );
					data[ item.name ].push( item.value );
				}
				else {
					data[ item.name ] = item.value;
				}
			});

			return data;
		},

		keyboardActions: function( e ) {
			var button = e.keyCode || e.which;

			// ESC key
			if ( 27 === button ) {
				this.closeButton( e );
			}
		}
	});

}( jQuery, Backbone, _ ));
