(function($) {
	/**
	 * Add order items loading block
	 */
	function addOrderItemsLoading() {
		$( '#woocommerce-order-items' ).block({
			message: null,
			overlayCSS: {
				background: '#fff url(' + woocommerce_admin_meta_boxes.plugin_url + '/assets/images/ajax-loader.gif) no-repeat center',
				opacity: 0.6
			}
		});
	}

	$(function() {
		$('#woocommerce-order-items').off('click', 'button.calculate-action')
			//@@@@LOUSHOU - added calcs for service fees
			// exact copy from plugins/woocommerce/assets/js/admin/meta-boxes-order.js, with the addition of service fees
			.on( 'click', 'button.calculate-action', function(ev) {
				ev.stopImmediatePropagation();
				if ( window.confirm( woocommerce_admin_meta_boxes.calc_totals ) ) {

					addOrderItemsLoading();

					// Get row totals
					var line_totals    = 0;
					var tax            = 0;
					var shipping       = 0;
					var order_discount = $( '#_order_discount' ).val() || '0';
					var service_fees   = 0;

					order_discount = accounting.unformat( order_discount.replace( ',', '.' ) );

					$( '.woocommerce_order_items tr.shipping input.line_total' ).each(function() {
						var cost  = $( this ).val() || '0';
						cost      = accounting.unformat( cost, woocommerce_admin.mon_decimal_point );
						shipping  = shipping + parseFloat( cost );
					});

					$( '.woocommerce_order_items input.line_tax' ).each(function() {
						var cost = $( this ).val() || '0';
						cost     = accounting.unformat( cost, woocommerce_admin.mon_decimal_point );
						tax      = tax + parseFloat( cost );
					});

					$( '.woocommerce_order_items tr.item, .woocommerce_order_items tr.fee' ).each(function() {
						var line_total     = $( this ).find( 'input.line_total' ).val() || '0';
						line_totals        = line_totals + accounting.unformat( line_total.replace( ',', '.' ) );
					});

					// Tax
					if ( 'yes' === woocommerce_admin_meta_boxes.round_at_subtotal ) {
						tax = parseFloat( accounting.toFixed( tax, woocommerce_admin_meta_boxes.rounding_precision ) );
					}

					//@@@@LOUSHOU - totals hook for plugins
					var all_totals = {
						line_totals: line_totals,
						tax: tax,
						shipping: shipping,
						order_discount: order_discount,
						order_total: line_totals + tax + shipping + - order_discount
					};
					QS.cbs.trigger( 'calculate-action', [ all_totals ] );
					//console.log('calculate-action', all_totals);

					// Set Total
					$( '#_order_total' )
						.val( accounting.formatNumber( all_totals.order_total, woocommerce_admin_meta_boxes.currency_format_num_decimals, '', woocommerce_admin.mon_decimal_point ) )
						.change();

					$( 'button.save-action' ).click();
				}

				return false;
			})
			//@@@@LOUSOU - added calcs for service fees
			// When the qty is changed, increase or decrease costs
			.on( 'change', '.woocommerce_order_items input.quantity', function() {
				var $row          = $( this ).closest( 'tr.item' );
				var qty           = $( this ).val();
				var o_qty         = $( this ).attr( 'data-qty' );
				var line_total    = $( 'input.line_total', $row );
				var line_subtotal = $( 'input.line_subtotal', $row );

				// Totals
				var unit_total = accounting.unformat( line_total.attr( 'data-total' ), woocommerce_admin.mon_decimal_point ) / o_qty;
				line_total.val(
					parseFloat( accounting.formatNumber( unit_total * qty, woocommerce_admin_meta_boxes.rounding_precision, '' ) )
						.toString()
						.replace( '.', woocommerce_admin.mon_decimal_point )
				);

				var unit_subtotal = accounting.unformat( line_subtotal.attr( 'data-subtotal' ), woocommerce_admin.mon_decimal_point ) / o_qty;
				line_subtotal.val(
					parseFloat( accounting.formatNumber( unit_subtotal * qty, woocommerce_admin_meta_boxes.rounding_precision, '' ) )
						.toString()
						.replace( '.', woocommerce_admin.mon_decimal_point )
				);

				// Taxes
				$( 'td.line_tax', $row ).each(function() {
					var line_total_tax = $( 'input.line_tax', $( this ) );
					var unit_total_tax = accounting.unformat( line_total_tax.attr( 'data-total_tax' ), woocommerce_admin.mon_decimal_point ) / o_qty;
					if ( 0 < unit_total_tax ) {
						line_total_tax.val(
							parseFloat( accounting.formatNumber( unit_total_tax * qty, woocommerce_admin_meta_boxes.rounding_precision, '' ) )
								.toString()
								.replace( '.', woocommerce_admin.mon_decimal_point )
						);
					}

					var line_subtotal_tax = $( 'input.line_subtotal_tax', $( this ) );
					var unit_subtotal_tax = accounting.unformat( line_subtotal_tax.attr( 'data-subtotal_tax' ), woocommerce_admin.mon_decimal_point ) / o_qty;
					if ( 0 < unit_subtotal_tax ) {
						line_subtotal_tax.val(
							parseFloat( accounting.formatNumber( unit_subtotal_tax * qty, woocommerce_admin_meta_boxes.rounding_precision, '' ) )
								.toString()
								.replace( '.', woocommerce_admin.mon_decimal_point )
						);
					}
				});

				$( this ).trigger( 'quantity_changed' );
			})
			//@@@@LOUSOU - added calcs for service fees ***************************************************************************************************
			// When the refund qty is changed, increase or decrease costs
			.on( 'change', '.woocommerce_order_items input.refund_order_item_qty', function() {
				var $row              = $( this ).closest( 'tr.item' );
				var qty               = $row.find( 'input.quantity' ).val();
				var refund_qty        = $( this ).val();
				var line_total        = $( 'input.line_total', $row );
				var refund_line_total = $( 'input.refund_line_total', $row );

				// Totals
				var unit_total = accounting.unformat( line_total.attr( 'data-total' ), woocommerce_admin.mon_decimal_point ) / qty;

				refund_line_total.val(
					parseFloat( accounting.formatNumber( unit_total * refund_qty, woocommerce_admin_meta_boxes.rounding_precision, '' ) )
						.toString()
						.replace( '.', woocommerce_admin.mon_decimal_point )
				);

				// Taxes
				$( 'td.line_tax', $row ).each( function() {
					var line_total_tax        = $( 'input.line_tax', $( this ) );
					var refund_line_total_tax = $( 'input.refund_line_tax', $( this ) );
					var unit_total_tax = accounting.unformat( line_total_tax.attr( 'data-total_tax' ), woocommerce_admin.mon_decimal_point ) / qty;

					if ( 0 < unit_total_tax ) {
						refund_line_total_tax.val(
							parseFloat( accounting.formatNumber( unit_total_tax * refund_qty, woocommerce_admin_meta_boxes.rounding_precision, '' ) )
								.toString()
								.replace( '.', woocommerce_admin.mon_decimal_point )
						);
					}
				});

				$( this ).trigger( 'refund_quantity_changed' );
			})
	});
})(jQuery);
