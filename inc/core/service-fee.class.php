<?php (__FILE__ == $_SERVER['SCRIPT_FILENAME']) ? die(header('Location: /')) : null;

// controls all aspects of service fee charging and manipulation, except those that are covered in js/admin/order/service-fee.js
class qsot_service_fee {
	protected static $o = array();
	protected static $options = array();

	public static function pre_init() {
		$settings_class_name = apply_filters('qsot-settings-class-name', '');
		if (!empty($settings_class_name)) {
			self::$o =& $settings_class_name::instance();

			// load all the options, and share them with all other parts of the plugin
			$options_class_name = apply_filters('qsot-options-class-name', '');
			if (!empty($options_class_name)) {
				self::$options =& $options_class_name::instance();
				self::_setup_admin_options();
			}

			add_action('init', array(__CLASS__, 'register_assets'), 10000);
			add_action('qsot-admin-load-assets-shop_order', array(__CLASS__, 'load_assets'), 10000, 2);

			add_filter('qsot-needs-service-fee', array(__CLASS__, 'needs_service_fee'), 10, 2);
			add_filter('qsot-calculate-service-fee', array(__CLASS__, 'calculate_service_fee'), 10, 3);

			add_action('woocommerce_calculate_totals', array(__CLASS__, 'calculate_service_fees_in_cart'), 10, 1);
			add_action('woocommerce_cart_totals_before_order_total', array(__CLASS__, 'add_service_fee_cart_totals_rows'), 10);
			add_action('woocommerce_review_order_before_order_total', array(__CLASS__, 'add_service_fee_cart_totals_rows'), 10);
			add_action('woocommerce_get_order_item_totals', array(__CLASS__, 'get_order_item_totals'), 10, 2);

			add_action('woocommerce_add_order_item_meta', array(__CLASS__, 'add_order_item_meta'), 10, 2);
			add_filter('qsot-cart-item-ticket-data', array(__CLASS__, 'add_order_item_meta_data'), 10, 2);
			add_filter('woocommerce_ajax_before_add_order_item', array(__CLASS__, 'ajax_before_add_order_item'), 10, 3);
			add_action('woocommerce_ajax_add_order_item_meta', array(__CLASS__, 'ajax_add_order_item_meta'), 10, 2);

			add_action('woocommerce_admin_after_order_item_headers', array(__CLASS__, 'add_service_fee_columns_in_admin'), 10);
			add_action('woocommerce_admin_after_order_item_values', array(__CLASS__, 'add_service_fee_values_in_admin'), 10, 3);
			add_filter('woocommerce_hidden_order_itemmeta', array(__CLASS__, 'hide_service_fee_meta'), 10, 1);
			add_filter('qsot-enforce-calc-totals-item', array(__CLASS__, 'enforce_calc_totals_item'), 10, 3);
			add_action('save_post', array(__CLASS__, 'save_order_update_service_fee_meta'), 90000, 2);

			add_filter('qswoo-reset-line-item-totals', array(__CLASS__, 'reset_service_fee_fields'), 10, 4);
			add_action('qswoo-admin-coupons-update-order-item-meta', array(__CLASS__, 'admin_coupons_update_order_item_meta'), 10, 2);
			add_filter('qswoo-admin-coupons-needs-modification-keys', array(__CLASS__, 'needs_modification_keys'), 10, 2);
			add_action('qswoo-admin-coupons-restore-save-request', array(__CLASS__, 'after_coupon_reapplication'), 10, 2);

			add_filter('qsot-micro-manage-discounts-discount-types', array(__CLASS__, 'add_service_fee_discount_type'), 10, 2);

			// all sales report
			add_filter('qsot-all-sales-report-summary-defaults', array(__CLASS__, 'all_sales_summary_defaults'), 10, 2);
			add_filter('qsot-all-sales-report-summary-columns', array(__CLASS__, 'all_sales_summary_columns'), 10, 3);
			add_filter('qsot-all-sales-report-detail-columns', array(__CLASS__, 'all_sales_detail_columns'), 10, 2);
			add_filter('qsot-all-sales-report-tally-order-items', array(__CLASS__, 'all_sales_tally_order_items'), 10, 4);
			add_filter('qsot-all-sales-report-payment-type-row-totals', array(__CLASS__, 'all_sales_payment_type_row_totals'), 10, 4);
			add_filter('qsot-all-sales-report-grand-total-payment-method-tally', array(__CLASS__, 'all_sales_tally_totals'), 10, 6);
			add_filter('qsot-all-sales-report-grand-total-grand-total-tally', array(__CLASS__, 'all_sales_tally_totals'), 10, 6);
			add_filter('qsot-all-sales-report-before-order-total-for-values', array(__CLASS__, 'all_sales_order_total_for_values'), 10, 2);
			add_filter('qsot-all-sales-report-totals-format', array(__CLASS__, 'all_sales_totals_format'), 10, 6);

			// ticket sales report
			add_filter('qsot-ticket-sales-report-summary-defaults', array(__CLASS__, 'ticket_sales_summary_defaults'), 10, 2);
			add_filter('qsot-ticket-sales-report-summary-columns', array(__CLASS__, 'ticket_sales_summary_columns'), 10, 3);
			add_filter('qsot-ticket-sales-report-detail-columns', array(__CLASS__, 'ticket_sales_detail_columns'), 10, 2);
			add_filter('qsot-ticket-sales-report-detail-row', array(__CLASS__, 'ticket_sales_detail_row'), 10, 7);
			add_filter('qsot-ticket-sales-report-event-payment-method-tally', array(__CLASS__, 'ticket_sales_tally_totals'), 10, 6);
			add_filter('qsot-ticket-sales-report-event-grand-total-tally', array(__CLASS__, 'ticket_sales_tally_totals'), 10, 6);
			add_filter('qsot-ticket-sales-report-grand-total-payment-method-tally', array(__CLASS__, 'ticket_sales_tally_totals'), 10, 6);
			add_filter('qsot-ticket-sales-report-grand-total-grand-total-tally', array(__CLASS__, 'ticket_sales_tally_totals'), 10, 6);
			add_filter('qsot-ticket-sales-report-payment-type-row-totals', array(__CLASS__, 'ticket_sales_payment_type_row_totals'), 10, 4);
			add_filter('qsot-ticket-sales-report-totals-format', array(__CLASS__, 'ticket_sales_format_totals'), 10, 6);

			// show sales report
			add_filter('qsot-show-sales-report-summary-defaults', array(__CLASS__, 'show_sales_summary_defaults'), 10, 2);
			add_filter('qsot-show-sales-report-summary-columns', array(__CLASS__, 'show_sales_summary_columns'), 10, 3);
			add_filter('qsot-show-sales-report-detail-columns', array(__CLASS__, 'show_sales_detail_columns'), 10, 2);
			add_filter('qsot-show-sales-report-detail-row', array(__CLASS__, 'show_sales_detail_row'), 10, 7);
			add_filter('qsot-show-sales-report-event-payment-method-tally', array(__CLASS__, 'show_sales_tally_totals'), 10, 6);
			add_filter('qsot-show-sales-report-event-grand-total-tally', array(__CLASS__, 'show_sales_tally_totals'), 10, 6);
			add_filter('qsot-show-sales-report-payment-type-row-totals', array(__CLASS__, 'show_sales_payment_type_row_totals'), 10, 4);
			add_filter('qsot-show-sales-report-grand-total-payment-method-tally', array(__CLASS__, 'show_sales_tally_totals'), 10, 6);
			add_filter('qsot-show-sales-report-grand-total-grand-total-tally', array(__CLASS__, 'show_sales_tally_totals'), 10, 6);
			add_filter('qsot-show-sales-report-totals-format', array(__CLASS__, 'show_sales_format_totals'), 10, 6);

			// product sales report
			add_filter('qsot-product-sales-report-detail-row', array(__CLASS__, 'product_sales_detail_row'), 10, 5);
			add_filter('qsot-product-sales-report-event-payment-method-tally', array(__CLASS__, 'product_sales_tally_totals'), 10, 6);
			add_filter('qsot-product-sales-report-event-grand-total-tally', array(__CLASS__, 'product_sales_tally_totals'), 10, 6);
			add_filter('qsot-product-sales-report-grand-total-payment-method-tally', array(__CLASS__, 'product_sales_tally_totals'), 10, 6);
			add_filter('qsot-product-sales-report-grand-total-grand-total-tally', array(__CLASS__, 'product_sales_tally_totals'), 10, 6);
			add_filter('qsot-product-sales-report-payment-type-row-totals', array(__CLASS__, 'product_sales_payment_type_row_totals'), 10, 4);
			add_filter('qsot-product-sales-report-totals-format', array(__CLASS__, 'product_sales_format_totals'), 10, 6);
			add_filter('qsot-product-sales-report-summary-defaults', array(__CLASS__, 'product_sales_summary_defaults'), 100, 2);
			add_filter('qsot-product-sales-report-summary-columns', array(__CLASS__, 'product_sales_summary_columns'), 100, 3);
			add_filter('qsot-product-sales-report-detail-columns', array(__CLASS__, 'product_sales_detail_columns'), 100, 2);

			// hide for non-special-owners
			add_action('admin_head', array(__CLASS__, 'visibility'), PHP_INT_MAX);
		}
	}

	// register the assets used by service fees
	public static function register_assets() {
		wp_register_script('qsot-order-service-fee', self::$o->core_url.'js/admin/order/service-fees.js', array('qsot-line-item-discounts'), self::$o->version);
	}

	// load the needed assets on the edit order screen
	public static function load_assets($exists, $post_id) {
		wp_enqueue_script('qsot-order-service-fee');
		
		wp_localize_script('qsot-order-service-fee', '_qsot_order_service_fee_settings', apply_filters('qsot-order-service-fee-settings', array(
			'calc' => self::$options->{'qsot-service-fee-calculation'},
			'amt' => self::$options->{'qsot-service-fee-amount'},
			'unit' => self::$options->{'qsot-service-fee-unit'},
		), $post_id));
	}

	public static function visibility() {
		if (!self::$o->owns_service_fees) {
			?><style>th.line_service_fee, td.line_service_fee { display:none; }</style><?php
		}
	}

	public static function reset_service_fee_fields($item, $oiid, $order, $groups) {
		$item['line_service_fee_subtotal'] = $item['line_service_fee_total'] = 0;
		$item['line_service_fee_reason'] = $item['line_service_fee_reason_extra'] = '';
		return $item;
	}

	public static function admin_coupons_update_order_item_meta($oiid, $values) {
		foreach (array('line_service_fee_subtotal', 'line_service_fee_total', 'line_service_fee_reason', 'line_service_fee_reason_extra') as $k) {
			if (isset($values[$k])) woocommerce_update_order_item_meta($oiid, '_'.$k, $values[$k]);
		}
	}

	// add service fee to the list of discountable items in the micro manage discounts lightbox
	public static function add_service_fee_discount_type($list, $order_id) {
		$list['service-fee'] = __('Service Fees', 'qsot');
		return $list;
	}

	public static function product_sales_detail_columns($columns, $req) {
		$list = array();
		foreach ($columns as $col => $label) {
			$list[$col] = $label;
			if ($col == 'subtotal') {
				$list['service-fee'] = __('Service Fee', 'qsot');
			}
		}
		return $list;
	}

	public static function product_sales_summary_columns($columns, $templ, $req) {
		$list = array();
		foreach ($columns as $col => $label) {
			$list[$col] = $label;
			if ($col == 'sold-units') {
				$list['service-fee'] = __('Service Fees', 'qsot');
			}
		}
		return $list;
	}

	public static function product_sales_summary_defaults($defs, $req) {
		$list = array();
		foreach ($defs as $k => $v) {
			$list[$k] = $v;
			if ($k == 'sold-units') {
				$list['service-fee'] = 0;
			}
		}
		return $list;
	}

	// format our new fields for display
	public static function product_sales_format_totals($values, $group, $method, $totals, $req, $subkey='') {
		$values['service-fee'] = money_format('%.2n', (double)$values['service-fee']);

		return $values;
	}

	// tally up the totals to include service fees
	public static function product_sales_tally_totals($method_group, $values, $oi, $oiid, $order, $totals) {
		if (isset($values['service-fee'])) {
			$method_group['service-fee'] += $values['service-fee'];
		}

		return $method_group;
	}

	// apply only a portion of the total order values because this is for on of potentially multiple payments on an order
	public static function product_sales_payment_type_row_totals($values, $ratio, $order_id, $total_values) {
		$values['service-fee'] = round($values['service-fee'] * $ratio, 2);

		return $values;
	}

	public static function product_sales_detail_row($values, $oiid, $oi, $product, $order) {
		if (isset($oi['_line_service_fee_subtotal'], $oi['_line_service_fee_total'])) {
			$values['service-fee'] = number_format($values['service-fee'] + $oi['_line_service_fee_subtotal'], 2, '.', '');
			$values['total'] = number_format($values['total'] + $oi['_line_service_fee_total'], 2, '.', '');
			$values['discount'] = number_format(($values['subtotal'] + $values['service-fee']) - $values['total'], 2, '.', '');
		}

		return $values;
	}

	// format our new fields for display
	public static function show_sales_format_totals($values, $group, $method, $totals, $req, $subkey='') {
		$values['service-fee-subtotal'] = money_format('%.2n', (double)$values['service-fee-subtotal']);

		return $values;
	}

	// tally up the totals to include service fees
	public static function show_sales_tally_totals($method_group, $values, $oi, $oiid, $order, $totals) {
		if (isset($values['service-fee-subtotal'], $values['service-fee-units'], $oi['_line_service_fee_total'])) {
			$method_group['service-fee-subtotal'] += $values['service-fee-subtotal'];
			$method_group['service-fee-units'] += $values['service-fee-units'];

			$disc = ($values['service-fee-subtotal'] - $oi['_line_service_fee_total']);
			// discount was already added. need to adjust the discounted count to accurately show that part of this item was discounted, if the service fee has been discounted.
			if ($disc > 0) $method_group['discounted-units'][$oiid.''] = $values['quantity'];
		}

		return $method_group;
	}

	// apply only a portion of the total order values because this is for on of potentially multiple payments on an order
	public static function show_sales_payment_type_row_totals($values, $ratio, $order_id, $total_values) {
		$values['service-fee-subtotal'] = round($values['service-fee-subtotal'] * $ratio, 2);
		$values['service-fee-units'] = $values['quantity'];

		return $values;
	}

	// add the values for service fees to each detail row
	public static function show_sales_detail_row($values, $oiid, $product, $oi, $order, $event, $zone) {
		if (isset($oi['_line_service_fee_subtotal'], $oi['_line_service_fee_total']) && !empty($oi['_line_service_fee_subtotal'])) {
			$values['service-fee-subtotal'] += $oi['_line_service_fee_subtotal'];
			$values['discount'] += ($oi['_line_service_fee_subtotal'] - $oi['_line_service_fee_total']);
			$values['total'] += $oi['_line_service_fee_total'];
		} elseif (!isset($values['service-fee-subtotal'])) {
			$values['service-fee-subtotal'] = 0;
		}

		return $values;
	}

	// add a service fee column to the detail print out
	public static function show_sales_detail_columns($list, $req) {
		$final = array();

		// add the service fee column after the item-subtotal column
		foreach ($list as $k => $v) {
			$final[$k] = $v;
			if ($k == 'subtotal') $final['service-fee-subtotal'] = __('Service Fee Subtotal', 'qsot');
		}

		return $final;
	}

	// add our service fee column headers to the summary report
	public static function show_sales_summary_columns($list, $templ, $req) {
		$final = array();

		// add the headers after the item-units header, so that they line up with our defaults
		foreach ($list as $k => $v) {
			$final[$k] = $v;
			if ($k == 'sold-percent') {
				$final['service-fee-subtotal'] = __('Service Fee Total', 'qsot');
				$final['service-fee-units'] = __('# Service Fee', 'qsot');
			}
		}

		return $final;
	}

	// add the defaults for the fees to the list of defaults used in the show sales report
	public static function show_sales_summary_defaults($defs, $req) {
		$final = array();

		// add our fields after the item tallies
		foreach ($defs as $k => $v) {
			$final[$k] = $v;
			if ($k == 'sold-percent') {
				$final['service-fee-subtotal'] = 0;
				$final['service-fee-units'] = 0;
				$final['service-fee-units'] = 0;
			}
		}

		return $final;
	}

	// format our new fields for display
	public static function ticket_sales_format_totals($values, $group, $method, $totals, $req, $subkey='') {
		$values['service-fee-subtotal'] = money_format('%.2n', (double)$values['service-fee-subtotal']);

		return $values;
	}

	// tally up the totals to include service fees
	public static function ticket_sales_tally_totals($method_group, $values, $oi, $oiid, $order, $totals) {
		if (isset($values['service-fee-subtotal'], $values['service-fee-units'], $oi['_line_service_fee_total'])) {
			$method_group['service-fee-subtotal'] += $values['service-fee-subtotal'];
			$method_group['service-fee-units'] += $values['service-fee-units'];

			$disc = ($values['service-fee-subtotal'] - $oi['_line_service_fee_total']);
			// discount was already added. need to adjust the discounted count to accurately show that part of this item was discounted, if the service fee has been discounted.
			if ($disc > 0) $method_group['discounted-units'][$oiid.''] = $values['quantity'];
		}

		return $method_group;
	}

	// apply only a portion of the total order values because this is for on of potentially multiple payments on an order
	public static function ticket_sales_payment_type_row_totals($values, $ratio, $order_id, $total_values) {
		$values['service-fee-subtotal'] = round($values['service-fee-subtotal'] * $ratio, 2);
		$values['service-fee-units'] = $values['quantity'];

		return $values;
	}

	// add the values for service fees to each detail row
	public static function ticket_sales_detail_row($values, $oiid, $product, $oi, $order, $event, $zone) {
		if (isset($oi['_line_service_fee_subtotal'], $oi['_line_service_fee_total']) && !empty($oi['_line_service_fee_subtotal'])) {
			$values['service-fee-subtotal'] += $oi['_line_service_fee_subtotal'];
			$values['discount'] += ($oi['_line_service_fee_subtotal'] - $oi['_line_service_fee_total']);
			$values['total'] += $oi['_line_service_fee_total'];
		}

		return $values;
	}

	// add a service fee column to the detail print out
	public static function ticket_sales_detail_columns($list, $req) {
		$final = array();

		// add the service fee column after the item-subtotal column
		foreach ($list as $k => $v) {
			$final[$k] = $v;
			if ($k == 'item-subtotal') $final['service-fee-subtotal'] = __('Service Fee Subtotal', 'qsot');
		}

		return $final;
	}

	// add our service fee column headers to the summary report
	public static function ticket_sales_summary_columns($list, $templ, $req) {
		$final = array();

		// add the headers after the item-units header, so that they line up with our defaults
		foreach ($list as $k => $v) {
			$final[$k] = $v;
			if ($k == 'item-units') {
				$final['service-fee-subtotal'] = __('Service Fee Total', 'qsot');
				$final['service-fee-units'] = __('# Service Fee', 'qsot');
			}
		}

		return $final;
	}

	// add the defaults for the fees to the list of defaults used in the ticket sales report
	public static function ticket_sales_summary_defaults($defs, $req) {
		$final = array();

		// add our fields after the item tallies
		foreach ($defs as $k => $v) {
			$final[$k] = $v;
			if ($k == 'item-units') {
				$final['service-fee-subtotal'] = 0;
				$final['service-fee-units'] = 0;
			}
		}

		return $final;
	}

	// for the summary on the all sales report, we need to format the fee-total
	public static function all_sales_totals_format($method_totals, $total_group, $method, $all_totals, $req, $pre_key='') {
		// format it like all the other columns
		$method_totals['service-fee-total'] = money_format('%.2n', (double)$method_totals['service-fee-total']);

		return $method_totals;
	}

	// alter the total calculation for this order, because we now have extra values to include, the fees
	public static function all_sales_order_total_for_values($values, $opost) {
		// alter the subtotal to include the fees
		$values['subtotal'] += $values['service-fees'];

		return $values;
	}

	// tally up the $values (order values already calculated) by payment_method. during this step we need to separately tally the fees
	public static function all_sales_tally_totals($payment_method, $values, $order_item, $oiid, $order, $totals) {
		// tally the fees
		$payment_method['service-fee-total'] += $values['service-fees'];

		return $payment_method;
	}

	// while tallying all the other order values, we need to also tally the fees
	public static function all_sales_tally_order_items($values, $oiid, $item, $opost) {
		// tally the fees up per item
		$values['service-fees'] += $item['_line_service_fee_subtotal'];

		// add the fee discount to the discount total
		$values['discount'] += ($item['_line_service_fee_subtotal'] - $item['_line_service_fee_total']);
		$values['fee-discount'] += ($item['_line_service_fee_subtotal'] - $item['_line_service_fee_total']);

		return $values;
	}

	// apply only a portion of the total order values because this is for on of potentially multiple payments on an order
	public static function all_sales_payment_type_row_totals($values, $ratio, $order_id, $total_values) {
		$values['service-fees'] = round($values['service-fees'] * $ratio, 2);

		return $values;
	}

	// inject a fee column into the detailed breakdown report
	public static function all_sales_detail_columns($columns, $req) {
		$final = array();

		// inject our column after the items total
		foreach ($columns as $k => $v) {
			$final[$k] = $v;
			if ($k == 'items') $final['service-fees'] = __('Service Fees Total', 'qsot');
		}

		return $final;
	}

	// inject the column on the summary of the all sales report
	public static function all_sales_summary_columns($columns, $template, $req) {
		$final = array();

		// insert the fees column directly after the item total column
		foreach ($columns as $k => $v) {
			$final[$k] = $v;
			if ($k == 'item-total') $final['service-fee-total'] = __('Total Service Fees', 'qsot');
		}

		return $final;
	}

	// inject a default value for the fees total on the all sales report
	public static function all_sales_summary_defaults($defs, $req) {
		$final = array();

		// find the position directly after item-total, and inject fee-total there
		foreach ($defs as $k => $v) {
			$final[$k] = $v;
			if ($k == 'item-total') $final['service-fee-total'] = 0;
		}
		
		return $final;
	}

	public static function needs_modification_keys($keys, $order) {
		return array_unique(array_merge($keys, array('line_service_fee_total', 'line_service_fee_subtotal')));
	}

	public static function after_coupon_reapplication($post_id, $post) {
		// only do this in the admin, to posts that are of 'shop_order' type. otherwise these calculations are irrelevant
		if (!is_admin()) return;
		if ($post->post_type != 'shop_order') return;

		$order = new WC_Order($post_id);

		// setup container for validation errors that would stop the payment capability
		$errors = array();
		$sflabel = self::$options->{'qsot-service-fee-label'}; // service fee label
		// determine whether we require a reason for discounting the service fee
		$reqrea = apply_filters('qsot-get-option-value', 'no', 'qsot-require-discount-reason') == 'yes';

		// if the order loaded, and we have submission of both service fee subtotal and total, then do our calculation
		if ($order->id == $post_id) {
			// foreach service fee sub total submitted
			foreach ($order->get_items(array('line_item')) as $oiid => $item) {
				$product_id = $item['product_id'];
				$product = get_product($product_id);
				if (!is_object($product) || $product->id != $product_id) continue;

				$needs_service_fee = (self::$options->{'qsot-service-fee-apply-to-tickets'} == 'yes' && $product->ticket == 'yes')
						|| (self::$options->{'qsot-service-fee-apply-to-downloadable'} == 'yes' && $product->downloadable == 'yes')
						|| (self::$options->{'qsot-service-fee-apply-to-all-products'} == 'yes');

				if (!$needs_service_fee) {
					foreach (array('_line_service_fee_subtotal', '_line_service_fee_total', '_line_service_fee_reason', '_line_service_fee_reason_extra') as $k)
						woocommerce_update_order_item_meta($id, $k, '');
					continue;
				}

				$amt = self::$options->{'qsot-service-fee-amount'};
				$unit = self::$options->{'qsot-service-fee-unit'};

				switch (self::$options->{'qsot-service-fee-calculation'}) {
					case 'discounted': $on = woocommerce_get_order_item_meta($oiid, '_line_total', true); break;
					case 'full': $on = woocommerce_get_order_item_meta($oiid, '_line_subtotal', true); break;
				}

				switch ($unit) {
					case 'percentage': $subtotal = round($on * ($amt / 100), 2); break;
					case 'flat-fee':
					default: $subtotal = $amt; break;
				}

				// load the matching total, rea(son), and rea(son_)ex(tra) from the submitted results. also calculation the dis(count)
				$total = $subtotal;
				$rea = '';
				$reaex = '';
				$dis = 0;

				// keep track of the current values of all relevant fields, for comparison later
				$current = array(
					'subtotal' => woocommerce_get_order_item_meta($oiid, '_line_service_fee_subtotal', true),
					'total' => woocommerce_get_order_item_meta($oiid, '_line_service_fee_total', true),
					'reason' => woocommerce_get_order_item_meta($oiid, '_line_service_fee_reason', true),
					'reason_extra' => woocommerce_get_order_item_meta($oiid, '_line_service_fee_reason_extra', true),
				);
				$current['discount'] = $current['subtotal'] - $current['total'];

				// update the relevant meta
				woocommerce_update_order_item_meta($oiid, '_line_service_fee_subtotal', number_format($subtotal, 2, '.', ''));
				woocommerce_update_order_item_meta($oiid, '_line_service_fee_total', number_format($total, 2, '.', ''));
				woocommerce_update_order_item_meta($oiid, '_line_service_fee_reason', $rea);
				woocommerce_update_order_item_meta($oiid, '_line_service_fee_reason_extra', $reaex);

				// if there is a discount and we require a reason for discounting
				if ($dis > 0 && $reqrea) {
					// compile a relevant name, that uniquely identifies the affected item, for the audit trail
					$name = $item['name'];
					if (isset($item['event_id'], $item['zone_id'])) {
						$event = get_post($item['event_id']);
						$zone = apply_filters('qsot-get-seating-zone', false, $item['zone_id']);
						if (is_object($event) && is_object($zone)) $name .= ' -> '.$zone->fullname.' @ '.$event->post_title;
					}

					// starting error count for this iteration of the item loop. we need this since we are likely processing more than one item here. the reason is because $errors holds ALL errors
					// not just the ones for this item. plus when we add the audit trail record, we do not want to add the record until it is valid.
					// @@@ values after a failed attempt are not correct in the audit trail. fix that
					$ecnt = count($errors);
					$errors = self::_add_reason_error(
						$errors,
						'You MUST supply a "reason" for your '.$sflabel.' Discount for '.$name.'.',
						$rea,
						$reaex,
						$post_id
					);

					// if the reason check above passes without any new errors, and if discount amount has changed
					if (count($errors) == $ecnt && $cur['discount'] != $dis) {
						$qty = $_POST['order_item_qty'][$oiid];
						$reason = $rea.(empty($reaex) ? '' : '('.$reaex.')');
						$msg = sprintf(
							'The '.$sflabel.' was discounted (%d) x [%s], for a total of |$%01.2f| discount, because "%s". '
									.'This brings the line service fee total to |$%01.2f|.',
							isset($item['qty']) ? $item['qty'] : 1,
							$name,
							$dis,
							$reason,
							$total
						);
						// add the service fee audit trail item
						$args = array(
							'order_id' => $post_id,
							'record_type' => 'add-service-fee-discount',
							'note' => $msg,
							'meta' => array(
								'order_item_id' => $oiid,
								'discount' => $dis,
								'reason' => $rea,
								'reason_extra' => $reaex,
							),
						);
						do_action('qsot-add-audit-item', $args);
					}
				}
				
				// allow other plugins to add errors
				$errors = apply_filters('qsot-service-fee-discount-and-reason-errors', $errors, $oiid, $post, $post_id);
			}
		}

		// update the errors and potentially block payment
		self::_update_errors($errors, $post->ID);
	}

	// make sure that our order item meta fields for the service fee are updated when the order is saved in the admin
	public static function save_order_update_service_fee_meta($post_id, $post) {
		// only do this in the admin, to posts that are of 'shop_order' type. otherwise these calculations are irrelevant
		if (!is_admin()) return;
		if ($post->post_type != 'shop_order') return;

		$order = new WC_Order($post_id);

		// setup container for validation errors that would stop the payment capability
		$errors = array();
		$sflabel = self::$options->{'qsot-service-fee-label'}; // service fee label
		// determine whether we require a reason for discounting the service fee
		$reqrea = apply_filters('qsot-get-option-value', 'no', 'qsot-require-discount-reason') == 'yes';

		// if the order loaded, and we have submission of both service fee subtotal and total, then do our calculation
		if ($order->id == $post_id && isset($_POST['line_service_fee_subtotal'], $_POST['line_service_fee_total'])) {
			// foreach service fee sub total submitted
			foreach ($_POST['line_service_fee_subtotal'] as $id => $subtotal) {
				$product_id = woocommerce_get_order_item_meta($id, '_product_id', true);
				$product = get_product($product_id);
				if (!is_object($product) || $product->id != $product_id) continue;

				$needs_service_fee = (self::$options->{'qsot-service-fee-apply-to-tickets'} == 'yes' && $product->ticket == 'yes')
						|| (self::$options->{'qsot-service-fee-apply-to-downloadable'} == 'yes' && $product->downloadable == 'yes')
						|| (self::$options->{'qsot-service-fee-apply-to-all-products'} == 'yes');

				if (!$needs_service_fee) {
					foreach (array('_line_service_fee_subtotal', '_line_service_fee_total', '_line_service_fee_reason', '_line_service_fee_reason_extra') as $k)
						woocommerce_update_order_item_meta($id, $k, '');
					continue;
				}

				$amt = self::$options->{'qsot-service-fee-amount'};
				$unit = self::$options->{'qsot-service-fee-unit'};

				switch (self::$options->{'qsot-service-fee-calculation'}) {
					case 'discounted': $on = $_POST['line_total'][$id]; break;
					case 'full': $on = woocommerce_get_order_item_meta($id, '_line_subtotal', true); break;
				}

				switch ($unit) {
					case 'percentage': $subtotal = round($on * ($amt / 100), 2); break;
					case 'flat-fee':
					default: $subtotal = $amt; break;
				}

				// load the matching total, rea(son), and rea(son_)ex(tra) from the submitted results. also calculation the dis(count)
				$total = $_POST['line_service_fee_total'][$id];
				$rea = isset($_POST['line_service_fee_reason'][$id]) ? trim($_POST['line_service_fee_reason'][$id]) : '';
				$reaex = isset($_POST['line_service_fee_reason_extra'][$id]) ? trim($_POST['line_service_fee_reason_extra'][$id]) : '';
				$dis = $subtotal - $total;

				// keep track of the current values of all relevant fields, for comparison later
				$current = array(
					'subtotal' => woocommerce_get_order_item_meta($id, '_line_service_fee_subtotal', true),
					'total' => woocommerce_get_order_item_meta($id, '_line_service_fee_total', true),
					'reason' => woocommerce_get_order_item_meta($id, '_line_service_fee_reason', true),
					'reason_extra' => woocommerce_get_order_item_meta($id, '_line_service_fee_reason_extra', true),
				);
				$current['discount'] = $current['subtotal'] - $current['total'];

				// update the relevant meta
				woocommerce_update_order_item_meta($id, '_line_service_fee_subtotal', number_format((float)$subtotal, 2, '.', ''));
				woocommerce_update_order_item_meta($id, '_line_service_fee_total', number_format((float)$total, 2, '.', ''));
				woocommerce_update_order_item_meta($id, '_line_service_fee_reason', $rea);
				woocommerce_update_order_item_meta($id, '_line_service_fee_reason_extra', $reaex);

				// if there is a discount and we require a reason for discounting
				if ($dis > 0 && $reqrea) {
					// load the current item from the order, because we need some data from it for the audit trail record
					$item = self::_get_order_item($id);

					// compile a relevant name, that uniquely identifies the affected item, for the audit trail
					$name = $item['name'];
					if (isset($item['event_id'], $item['zone_id'])) {
						$event = get_post($item['event_id']);
						$zone = apply_filters('qsot-get-seating-zone', false, $item['zone_id']);
						if (is_object($event) && is_object($zone)) $name .= ' -> '.$zone->fullname.' @ '.$event->post_title;
					}

					// starting error count for this iteration of the item loop. we need this since we are likely processing more than one item here. the reason is because $errors holds ALL errors
					// not just the ones for this item. plus when we add the audit trail record, we do not want to add the record until it is valid.
					// @@@ values after a failed attempt are not correct in the audit trail. fix that
					$ecnt = count($errors);
					$errors = self::_add_reason_error(
						$errors,
						'You MUST supply a "reason" for your '.$sflabel.' Discount for '.$name.'.',
						$rea,
						$reaex,
						$post_id
					);

					// if the reason check above passes without any new errors, and if discount amount has changed
					if (count($errors) == $ecnt && $cur['discount'] != $dis) {
						$qty = $_POST['order_item_qty'][$id];
						$reason = $rea.(empty($reaex) ? '' : '('.$reaex.')');
						$msg = sprintf(
							'The '.$sflabel.' was discounted (%d) x [%s], for a total of |$%01.2f| discount, because "%s". '
									.'This brings the line service fee total to |$%01.2f|.',
							isset($item['qty']) ? $item['qty'] : 1,
							$name,
							$dis,
							$reason,
							$total
						);
						// add the service fee audit trail item
						$args = array(
							'order_id' => $post_id,
							'record_type' => 'add-service-fee-discount',
							'note' => $msg,
							'meta' => array(
								'order_item_id' => $id,
								'discount' => $dis,
								'reason' => $rea,
								'reason_extra' => $reaex,
							),
						);
						do_action('qsot-add-audit-item', $args);
					}
				}
				
				// allow other plugins to add errors
				$errors = apply_filters('qsot-service-fee-discount-and-reason-errors', $errors, $id, $post, $post_id);
			}
		}

		// update the errors and potentially block payment
		self::_update_errors($errors, $post->ID);
	}

	// add service fee meta to the 'fees' total, so that the calc totals calculation is correct
	public static function enforce_calc_totals_item($totals, $item, $order) {
		$totals['fees'] += $item['line_service_fee_total'];
		return $totals;
	}

	// add values for the columns on the edit order page in the order items meta box in the admin
	public static function add_service_fee_values_in_admin($product, $item, $item_id) {
		// meta for service fees, mixed with default values, so that we dont have to test for isset
		$item = wp_parse_args($item, array(
			'_line_service_fee_subtotal' => '',
			'_line_service_fee_total' => '',
			'_line_service_fee_reason' => '',
			'_line_service_fee_reason_extra' => '',
		));
		//!!!! needs own styles
		?>
			<td class="line_service_fee line_cost" width="1%">
				<div class="view">
					<?php echo wc_price($item['line_service_fee_total']) ?>
				</div>

				<div class="edit" style="display:none;">
					<div class="subtotal">
						<label>
							<?php echo __('Subtotal', 'woocommerce') ?>
							<input type="hidden" name="line_service_fee_subtotal[<?php echo $item_id ?>]"
								value="<?php echo $item['line_service_fee_subtotal'] ?>" class="line_service_fee_subtotal" />
							<input type="number" step="any" min="0" name="line_service_fee_subtotal_display[<?php echo $item_id ?>]" placeholder="0.00" disabled="disabled"
								value="<?php echo $item['line_service_fee_subtotal'] ?>" class="line_service_fee_subtotal" />
						</label>
					</div>

					<div class="discounts">
						<label>
							<?php _e('Discount', 'woocommerce'); ?>:
							<?php $discount = isset($item['line_service_fee_subtotal'], $item['line_service_fee_total']) ? $item['line_service_fee_subtotal'] - $item['line_service_fee_total'] : 0; ?>
							<input type="number" step="any" min="0" name="line_service_fee_discount[<?php echo absint($item_id); ?>]" placeholder="0.00"
									value="<?php echo esc_attr($discount) ?>" class="line_service_fee_discount" />
						</label>
					</div>

					<div class="total">
						<label>
							<?php echo __('Total', 'woocommerce') ?>
							<input type="number" step="any" min="0" name="line_service_fee_total[<?php echo $item_id ?>]" placeholder="0.00"
								value="<?php echo $item['line_service_fee_total'] ?>" class="line_service_fee_total" />
						</label>
					</div>

					<?php if (apply_filters('qsot-get-option-value', 'no', 'qsot-require-discount-reason') == 'yes'): ?>
						<?php $reasons = apply_filters('qsot-discount-reason-types', array(), $item_id, $item); ?>
						<?php $show = $discount <= 0 ? 'none' : 'block'; ?>
						<div class="reason" style="display:<?php echo $show ?>;">
							<?php $style = !isset($item['line_service_fee_reason']) || empty($item['line_service_fee_reason']) ? 'border-color:red;' : ''; ?>
							<label>
								<?php _e('Reason', 'woocommerce'); ?>:
								<select name="line_service_fee_reason[<?php echo absint($item_id); ?>]" class="line_service_fee_reason" style="<?php echo esc_attr($style) ?>">
									<option value="">- Select -</option>
									<?php foreach ($reasons as $value => $label): ?>
										<?php if ((($matched = preg_match('#^\-\-#', $value)) && $value == $item['line_service_fee_reason']) || !$matched): ?>
											<option value="<?php echo esc_attr($value) ?>" <?php selected($value, $item['line_service_fee_reason']) ?>><?php echo $label ?></option>
										<?php endif; ?>
									<?php endforeach; ?>
								</select>
							</label>

							<?php $style = 'display:'.(preg_match('#\+\+$#', $item['line_service_fee_reason']) ? 'block' : 'none').'; '; ?>
							<?php $field_style = !isset($item['line_service_fee_reason_extra']) || strlen($item['line_service_fee_reason_extra']) < 3 ? 'border-color:red;' : ''; ?>
							<?php $field_value = isset($item['line_service_fee_reason_extra']) ? force_balance_tags($item['line_service_fee_reason_extra']) : ''; ?>
							<label class="extra" style="<?php echo esc_attr($style) ?>">
								<div><?php _e('Explain', 'woocommerce') ?>:</div>
								<textarea name="line_service_fee_reason_extra[<?php echo absint($item_id); ?>]" class="line_service_fee_reason_extra extra-field" style="width:100%"
										style="<?php echo esc_attr($field_style) ?>"><?php echo $field_value ?></textarea>
							</label>
						</div>
					<?php endif; ?>
				</div>
			</td>
		<?php
	}

	// add columns to the order items list on the edit order screen in the admin, that hold our service fees
	public static function add_service_fee_columns_in_admin() {
		//!!!! needs own styles
		?>
			<th class="line_service_fee line_cost"><?php echo __(self::$options->{'qsot-service-fee-label'}, 'qsot') ?></th>
		<?php
	}

	// hide our service fee meta from being printed below the title of the item in the order items metabox in the admin on the edit order page
	public static function hide_service_fee_meta($keys) {
		// our keys
		$new = array(
			'_line_service_fee_subtotal',
			'_line_service_fee_total',
			'_line_service_fee_reason',
			'_line_service_fee_reason_extra',
			'__service-fee', '___service-fee', /// both are legacy
		);

		return array_unique(array_merge($keys, $new));
	}

	// add item meta during multi select, before we save the meta to the db
	public static function add_order_item_meta_data($data, $args) {
		//$zone_id, $event_id, $product) {
		$args = wp_parse_args($args, array(
			'zone_id' => 0,
			'event_id' => 0,
			'product' => null,
			'order_id' => 0,
		));
		extract($args);

		if (is_object($product)) {
			$needs_service_fee = (self::$options->{'qsot-service-fee-apply-to-tickets'} == 'yes' && $product->ticket == 'yes')
					|| (self::$options->{'qsot-service-fee-apply-to-downloadable'} == 'yes' && $product->downloadable == 'yes')
					|| (self::$options->{'qsot-service-fee-apply-to-all-products'} == 'yes');

			if (!$needs_service_fee) {
				foreach (array('_line_service_fee_subtotal', '_line_service_fee_total', '_line_service_fee_reason', '_line_service_fee_reason_extra') as $k)
					$data[$k] = '';
			} else {
				$data['line_service_fee_subtotal'] = $data['line_service_fee_total'] = apply_filters('qsot-calculate-service-fee', 0, $data['line_subtotal'], $data['line_total']);
			}
		}

		return $data;
	}

	// when creating a new order from a customer on the frontend, upon adding an item to the order, we need to make sure that our service fee fields are updated
	public static function add_order_item_meta($item_id, $values) {
		// for each of our service fee keys, add the meta to this item
		foreach (array('line_service_fee_subtotal', 'line_service_fee_total', 'line_service_fee_reason', 'line_service_fee_reason_extra') as $k)
			if (isset($values[$k]))
				woocommerce_update_order_item_meta($item_id, '_'.$k, woocommerce_format_decimal($values[$k], 2));
	}

	// add meta to the item array before we save this meta, otherwise it will not be available to the template that is used to make the tr for the item table
	public static function ajax_before_add_order_item($item, $product, $order) {
		$needs_service_fee = (self::$options->{'qsot-service-fee-apply-to-tickets'} == 'yes' && $product->ticket == 'yes')
				|| (self::$options->{'qsot-service-fee-apply-to-downloadable'} == 'yes' && $product->downloadable == 'yes')
				|| (self::$options->{'qsot-service-fee-apply-to-all-products'} == 'yes');

		if (!$needs_service_fee) {
			foreach (array('_line_service_fee_subtotal', '_line_service_fee_total', '_line_service_fee_reason', '_line_service_fee_reason_extra') as $k)
				$item[$k] = '';
		} else {
			$item['line_service_fee_subtotal'] = $item['line_service_fee_total'] = apply_filters('qsot-calculate-service-fee', 0, $item['line_subtotal'], $item['line_total']);
		}

		return $item;
	}

	// save the meta when creating a new item using core woocommerce 'add item' button
	public static function ajax_add_order_item_meta($item_id, $item) {
		woocommerce_update_order_item_meta($item_id, '_line_service_fee_subtotal', $item['line_service_fee_subtotal']);
		woocommerce_update_order_item_meta($item_id, '_line_service_fee_total', $item['line_service_fee_total']);
		woocommerce_update_order_item_meta($item_id, '_line_service_fee_reason', '');
		woocommerce_update_order_item_meta($item_id, '_line_service_fee_reason_extra', '');
	}

	// add totals to the print out of totals in the pay form. this is used in the admin payments metabox
	public static function get_order_item_totals($rows, $order) {
		$total = 0;

		// tally up the total service fees
		foreach ($order->get_items() as $oiid => $item) {
			$total += $item['line_service_fee_total'];
		}

		$final = array();
		if ($total > 0) {
			// place the service fee above the total, as to not confuse the on looker by having it after the total, which includes service fees
			foreach ($rows as $key => $row) {
				if ($key == 'order_total') {
					$final['service_fees'] = array(
						'label' => __(self::$options->{'qsot-service-fee-label'}, 'qsot-event'),
						'value' => woocommerce_price($total),
					);
				}
				$final[$key] = $row;
			}
		} else {
			$final = $rows;
		}

		return $final;
	}

	// add a print out of the service fee on the customer facing areas that show the totals
	public static function add_service_fee_cart_totals_rows() {
		global $woocommerce;

		// tally the service fee
		$fee = 0;
		foreach ($woocommerce->cart->cart_contents as $key => $values) $fee += $values['line_service_fee_total'];

		if ($fee > 0) {
			// display ther service fee
			?>
				<tr class="fee fee-service-fee">
					<th><?php _e(self::$options->{'qsot-service-fee-label'}, 'woocommerce'); ?></th>
					<td><?php echo woocommerce_price($fee); ?></td>
				</tr>
			<?php
		}
	}

	// when we calculate the cart totals, we need to independently calculate these service fee totals
	public static function calculate_service_fees_in_cart($cart) {
		// look at every item in the cart
		foreach ($cart->cart_contents as $cart_item_key => $values) {
			$service_fee_data = array(
				'line_service_fee_subtotal' => 0, // service fee subtotal, before service fee specific discounts
				'line_service_fee_total' => 0, // service fee final total, after service fee specific discounts
				'line_service_fee_reason' => '', // discount reason
				'line_service_fee_reason_extra' => '', // discount reason explanation if required
			);

			$product = get_product($values['product_id']);
			// if this product needs a service fee, then calculate it
			if (apply_filters('qsot-needs-service-fee', false, $product)) {
				$service_fee_data['line_service_fee_subtotal'] =
				$service_fee_data['line_service_fee_total'] = apply_filters('qsot-calculate-service-fee', 0, $values['line_subtotal'], $values['line_total']);
			}

			// add any fee we come up with to the fee_total, so that it gets added into the cart total appropriately
			$cart->fee_total += $service_fee_data['line_service_fee_total'];

			// update the cart item with is complete meta
			$cart->cart_contents[$cart_item_key] = array_merge($values, $service_fee_data);
		}
	}

	// use the subtotal (before discounts) and the total (after discounts) in conjunction with the current settings, to determine the final service fee price
	public static function calculate_service_fee($fee, $subtotal, $total) {
		// normalize the fee to a number, because words are not a valid value
		$fee = is_numeric($fee) ? $fee : 0;
		$on = 'no';

		// if we actually have a service fee to apply
		if (self::$options->{'qsot-service-fee-amount'} >= 0) {
			$on = 0;

			// determine which value to base our service fee off of, determined by our current settings
			switch (self::$options->{'qsot-service-fee-calculation'}) {
				case 'full': $on = $subtotal; break;
				case 'discounted': $on = $total; break;
			}

			// use that value with our settings to determine the final service fee amount
			switch (self::$options->{'qsot-service-fee-unit'}) {
				case 'flat-fee': $fee += self::$options->{'qsot-service-fee-amount'}; break;
				case 'percentage': $fee += $on * (self::$options->{'qsot-service-fee-amount'} / 100); break;
			}
		}

		return $fee;
	}

	// use the current settings to determine whether a given product requires a service fee
	public static function needs_service_fee($current, $product) {
		// if service fees are turned off or 0, then we obviously dont need a service fee
		if (self::$options->{'qsot-service-fee-amount'} <= 0) return $current;

		// normalize the product param into an object of WC_Product type, such that we can continue our calculations
		if (is_scalar($product) && is_numeric($product)) $product = get_product();

		// if we do not have a product object, then we can't continue to determine if it needs a service fee
		if (!is_object($product) || !is_a($product, 'WC_Product')) return $current;

		// adjust our answer based on the current settings and on the flags on the product
		if (self::$options->{'qsot-service-fee-apply-to-tickets'} == 'yes' && $product->ticket == 'yes') $current = true;
		if (self::$options->{'qsot-service-fee-apply-to-downloadable'} == 'yes' && $product->downloadable == 'yes') $current = true;
		if (self::$options->{'qsot-service-fee-apply-to-all-products'} == 'yes') $current = true;

		return $current;
	}

	// load an order item as if we got it from the $order->get_items() function
	protected static function _get_order_item($id, $order_id=0) {
		global $wpdb;
		$res = array();

		// if we dont know the order_id, figure it out based on the order_item_id
		if (empty($order_id)) {
			$t = $wpdb->prefix.'woocommerce_order_items';
			$q = $wpdb->prepare('select order_id from '.$t.' where order_item_id = %d', $id);
			$order_id = $wpdb->get_var($q);
		}

		// if we have an order_id
		if (is_numeric($order_id) && $order_id > 0) {
			// load the order
			$order = new WC_Order($order_id);
			// actually call get_items
			$items = $order->get_items(array('line_item', 'fee'));
			// if our requested item exists, pull it out and add some meta to it for tracking purposes later
			if (isset($items[$id])) {
				$res = $items[$id];
				$res['__order_id'] = $order_id;
				$res['__order_item_id'] = $id;
			}
		}

		return $res;
	}

	// method to throw an error if the reason is empty or does not have all required information
	protected static function _add_reason_error($errors, $msg, $reason, $reason_extra, $order_id) {
		$reason = trim($reason);
		$reason_extra = trim($reason_extra);
		if (empty($reason) || (preg_match('#\+\+$#', $reason) && empty($reason_extra)))
			$errors[] = $msg;
		return $errors;
	}

	// save the list of errors, effectively blocking the payment metabox from being able to be used, if any errors exist
	protected static function _update_errors($errors, $order_id) {
		$errors = is_scalar($errors) ? array($errors) : $errors;
		$errors = !is_array($errors) ? array() : $errors;
		$current = get_post_meta($order_id, '_generic_errors', true);
		if (!empty($current)) array_unshift($errors, $current);
		update_post_meta($order_id, '_generic_errors', implode('<br/>', $errors));
	}

	// setup the available settings that show on the events woocommerce settings tab.
	protected static function _setup_admin_options() {
		// setup the defaults, so that queries to the options object give the correct value, if none has been set by the admins
		self::$options->def('qsot-service-fee-label', 'Service Fee');
		self::$options->def('qsot-service-fee-amount', '5');
		self::$options->def('qsot-service-fee-unit', 'percentage');
		self::$options->def('qsot-service-fee-calculation', 'discounted');
		self::$options->def('qsot-service-fee-apply-to-tickets', 'yes');
		self::$options->def('qsot-service-fee-apply-to-downloadable', 'no');
		self::$options->def('qsot-service-fee-apply-to-all-products', 'no');

		// setup the settings section
		/** @@@@!OpenTickets - removing serivce fee settings from UI. For Product Launch */
		if (self::$o->owns_service_fees) {
			//if (qsot_lkey::activated()) {
				self::$options->add(array(
					'order' => 500,
					'type' => 'title',
					'title' => __('Service Fees', 'qsot'),
					'id' => 'heading-service-fees-1',
				));

				self::$options->add(array(
					'order' => 510,
					'id' => 'qsot-service-fee-label',
					'type' => 'text',
					'title' => __('Service Fee Verbiage', 'qsot'),
					'desc_tip' => __('Label for the Service Fee on the cart, checkout, and admin pages.', 'qsot'),
					'default' => 'Service Fee',
				));

				self::$options->add(array(
					'order' => 511,
					'id' => 'qsot-service-fee-amount',
					'type' => 'number',
					'title' => __('Service Fee Amount', 'qsot'),
					'desc_tip' => __('Numeric magnitude of the service fee.', 'qsot'),
					'default' => '0',
				));

				self::$options->add(array(
					'order' => 512,
					'id' => 'qsot-service-fee-unit',
					'type' => 'radio',
					'title' => __('Service Fee Type', 'qsot'),
					'desc_tip' => __('Method of applying the service fee.', 'qsot'),
					'options' => array(
						'percentage' => __('Percentage', 'qsot'),
						'flat-fee' => __('Flat Fee', 'qsot'),
					),
					'default' => 'percentage',
				));

				self::$options->add(array(
					'order' => 513,
					'id' => 'qsot-service-fee-calculation',
					'type' => 'radio',
					'title' => __('Service Fee Calculation', 'qsot'),
					'desc_tip' => __('Should the service fee be applied on the full item price, or the price after any applicable discounts.', 'qsot'),
					'options' => array(
						'full' => __('Full Price', 'qsot'),
						'discounted' => __('Discounted Price', 'qsot'),
					),
					'default' => 'discounted',
				));

				self::$options->add(array(
					'order' => 515,
					'id' => 'qsot-service-fee-apply-to-tickets',
					'type' => 'checkbox',
					'title' => __('Apply to', 'qsot'),
					'desc' => __('Tickets', 'qsot'),
					'default' => 'yes',
					'checkboxgroup' => 'start',
				));

				self::$options->add(array(
					'order' => 520,
					'id' => 'qsot-service-fee-apply-to-downloadable',
					'type' => 'checkbox',
					'desc' => __('Downloadable', 'qsot'),
					'default' => 'no',
					'checkboxgroup' => '',
				));

				self::$options->add(array(
					'order' => 525,
					'id' => 'qsot-service-fee-apply-to-all-products',
					'type' => 'checkbox',
					'desc' => __('All Products', 'qsot'),
					'default' => 'no',
					'checkboxgroup' => 'end',
				));

				self::$options->add(array(
					'order' => 600,
					'type' => 'sectionend',
					'id' => 'heading-service-fees-1',
				));
			// }
		}
	}
}

if (defined('ABSPATH') && function_exists('add_action')) {
	qsot_service_fee::pre_init();
}
