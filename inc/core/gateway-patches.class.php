<?php (__FILE__ == $_SERVER['SCRIPT_FILENAME']) ? die(header('Location: /')) : null;

class qsot_gateway_patches {
	protected static $order_id = 0;

	public static function pre_init() {
		add_filter('woocommerce_paypal_args', array(__CLASS__, 'patch_paypal_data'), 1000000);
	}

	public static function patch_paypal_pro_admin_return_url($url, $order) {
		return get_edit_post_link($order->id, 'raw');
	}

	public static function patch_paypal_data($args) {
		global $woocommerce;

		//@list($customer_id) = is_object($woocommerce->session) ? $woocommerce->session->get_session_cookie() : array('');
		$customer_id = apply_filters( 'woocommerce_checkout_customer_id', get_current_user_id() );
		if (!empty($customer_id)) {
			$un = maybe_unserialize($args['custom']);
			$un['cust_id'] = $customer_id;
			$args['custom'] = maybe_serialize($un);
		}

		$available_gateways = WC()->payment_gateways->get_available_payment_gateways();
		$gateway = isset($available_gateways['paypal']) ? $available_gateways['paypal'] : false;
		if (empty($gateway)) return $args;

		$order_id = preg_replace('#^'.preg_quote($gateway->invoice_prefix, '#').'(.*)$#', '\1', $args['invoice']);
		if (!is_numeric($order_id)) return $args;
		$order = new WC_Order($order_id);

		$max = 0;
		foreach ($args as $k => $v) {
			if ( ($nk = preg_replace('#^item_name_(\d+)$#', '\1', $k)) && $nk == $k) continue;
			$max = max($max, $nk);
			$number = isset($args['item_number_'.$nk]) ? $args['item_number_'.$nk] : false;
			if ($number) {
				$product = self::_product_from_sku($number); 
				if (is_object($product) && isset($product->post)) {
					$args[$k] = self::_paypal_item_name($product->get_title());
				}
			}
		}

		return apply_filters('qsot-paypal-args', $args, $max, $order);
	}

	protected static function _paypal_item_name( $item_name ) {
		$item_name = strip_tags($item_name);
		if ( strlen( $item_name ) > 127 ) {
			$item_name = substr( $item_name, 0, 124 ) . '...';
		}
		return html_entity_decode( $item_name, ENT_NOQUOTES, 'UTF-8' );
	}

	protected static function _product_from_sku($sku) {
		global $wpdb;

		$product_id = $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key='_sku' AND meta_value='%s' LIMIT 1", $sku ) );

		return get_product($product_id);
	}
}

if (defined('ABSPATH') && function_exists('add_action')) {
	qsot_gateway_patches::pre_init();
}
