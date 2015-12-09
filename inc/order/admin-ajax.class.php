<?php if ( __FILE__ == $_SERVER['SCRIPT_FILENAME'] ) die( header( 'Location: /') );

/**
 * Overtake some of the core WC ajax functions.
 */
class QSOT_order_admin_ajax {
	// setup this class
	public static function pre_init() {
		// if we are processing an ajax request
		if (defined('DOING_AJAX')) {
			// if woocommerce plugin has already initialized, then just directly call our setup function
			if (did_action('woocommerce_loaded') > 0) self::setup_ajax_overrides();
			// otherwise wait until it is initialized before we call it
			else add_action('woocommerce_loaded', array(__CLASS__, 'setup_ajax_overrides'), 1000);
		}
	}

	// setup the override ajax functions
	// largely copied from /wp-content/plugins/woocommerce/includes/class-wc-ajax.php
	public static function setup_ajax_overrides() {
		$ajax_events = array(
			'add_order_item' => false, //@@@@LOUSHOU - only needed because of the lack of WC admin template functions
			'save_order_items' => false, //@@@@LOUSHOU - only needed because of the lack of WC admin template functions
			'load_order_items' => false, //@@@@LOUSHOU - only needed because of the lack of WC admin template functions
			'add_order_fee' => false, //@@@@LOUSHOU - only needed because of the lack of WC admin template functions
			'add_order_shipping' => false, //@@@@LOUSHOU - only needed because of the lack of WC admin template functions
		);

		// cycle through the list of relevant events
		foreach ( $ajax_events as $ajax_event => $nopriv ) {
			// remove any core WC ajax event handler, becuase it is duplicated here
			remove_action( 'wp_ajax_woocommerce_' . $ajax_event, array( 'WC_AJAX', $ajax_event ) );

			// setup our ajax handler
			add_action( 'wp_ajax_woocommerce_' . $ajax_event, array( __CLASS__, $ajax_event ) );

			if ( $nopriv ) {
				// remove any core WC ajax event handler, becuase it is duplicated here
				remove_action( 'wp_ajax_nopriv_woocommerce_' . $ajax_event, array( 'WC_AJAX', $ajax_event ) );

				// setup our ajax handler
				add_action( 'wp_ajax_nopriv_woocommerce_' . $ajax_event, array( __CLASS__, $ajax_event ) );
			}
		}
	}

	/**
	 * Add order item via ajax
	 * exact copy from /wp-content/plugins/woocommerce/includes/class-wc-ajax.php, with change to template selection
	 */
	public static function add_order_item() {
		check_ajax_referer( 'order-item', 'security' );

		$item_to_add = sanitize_text_field( $_POST['item_to_add'] );
		$order_id    = absint( $_POST['order_id'] );

		// Find the item
		if ( ! is_numeric( $item_to_add ) ) {
			die();
		}

		$post = get_post( $item_to_add );

		if ( ! $post || ( 'product' !== $post->post_type && 'product_variation' !== $post->post_type ) ) {
			die();
		}

		$_product    = wc_get_product( $post->ID );
		$order       = wc_get_order( $order_id );
		$order_taxes = $order->get_taxes();
		$class       = 'new_row';

		// Set values
		$item = array();

		$item['product_id']        = $_product->id;
		$item['variation_id']      = isset( $_product->variation_id ) ? $_product->variation_id : '';
		$item['variation_data']    = isset( $_product->variation_data ) ? $_product->variation_data : '';
		$item['name']              = $_product->get_title();
		$item['tax_class']         = $_product->get_tax_class();
		$item['qty']               = 1;
		$item['line_subtotal']     = wc_format_decimal( $_product->get_price_excluding_tax() );
		$item['line_subtotal_tax'] = '';
		$item['line_total']        = wc_format_decimal( $_product->get_price_excluding_tax() );
		$item['line_tax']          = '';

		// Add line item
		$item_id = wc_add_order_item( $order_id, array(
			'order_item_name' 		=> $item['name'],
			'order_item_type' 		=> 'line_item'
		) );

		// Add line item meta
		if ( $item_id ) {
			wc_add_order_item_meta( $item_id, '_qty', $item['qty'] );
			wc_add_order_item_meta( $item_id, '_tax_class', $item['tax_class'] );
			wc_add_order_item_meta( $item_id, '_product_id', $item['product_id'] );
			wc_add_order_item_meta( $item_id, '_variation_id', $item['variation_id'] );
			wc_add_order_item_meta( $item_id, '_line_subtotal', $item['line_subtotal'] );
			wc_add_order_item_meta( $item_id, '_line_subtotal_tax', $item['line_subtotal_tax'] );
			wc_add_order_item_meta( $item_id, '_line_total', $item['line_total'] );
			wc_add_order_item_meta( $item_id, '_line_tax', $item['line_tax'] );

			// Since 2.2
			wc_add_order_item_meta( $item_id, '_line_tax_data', array( 'total' => array(), 'subtotal' => array() ) );

			// Store variation data in meta
			if ( $item['variation_data'] && is_array( $item['variation_data'] ) ) {
				foreach ( $item['variation_data'] as $key => $value ) {
					wc_add_order_item_meta( $item_id, str_replace( 'attribute_', '', $key ), $value );
				}
			}

			do_action( 'woocommerce_ajax_add_order_item_meta', $item_id, $item );
		}

		$item          = apply_filters( 'woocommerce_ajax_order_item', $item, $item_id );

		//include( 'admin/meta-boxes/views/html-order-item.php' );
		//@@@@LOUSHOU - allow overtake of template
		if ( $template = QSOT_Templates::locate_woo_template( 'meta-boxes/views/html-order-item.php', 'admin' ) )
			include( $template );

		// Quit out
		die();
	}

	/**
	 * Save order items via ajax
	 * exact copy from /wp-content/plugins/woocommerce/includes/class-wc-ajax.php, with change to template selection
	 */
	public static function save_order_items() {
		check_ajax_referer( 'order-item', 'security' );

		if ( isset( $_POST['order_id'] ) && isset( $_POST['items'] ) ) {
			$order_id = absint( $_POST['order_id'] );

			// Parse the jQuery serialized items
			$items = array();
			parse_str( $_POST['items'], $items );

			// Save order items
			wc_save_order_items( $order_id, $items );

			// Return HTML items
			$order = wc_get_order( $order_id );
			$data  = get_post_meta( $order_id );

			//include( 'admin/meta-boxes/views/html-order-items.php' );
			//@@@@LOUSHOU - allow overtake of template
			if ( $template = QSOT_Templates::locate_woo_template( 'meta-boxes/views/html-order-items.php', 'admin' ) )
				include( $template );
		}

		die();
	}

	/**
	 * Load order items via ajax
	 * exact copy from /wp-content/plugins/woocommerce/includes/class-wc-ajax.php, with change to template selection
	 */
	public static function load_order_items() {
		check_ajax_referer( 'order-item', 'security' );

		// Return HTML items
		$order_id = absint( $_POST['order_id'] );
		$order    = new WC_Order( $order_id );
		$data     = get_post_meta( $order_id );

		//include( 'admin/meta-boxes/views/html-order-items.php' );
		//@@@@LOUSHOU - allow overtake of template
		if ( $template = QSOT_Templates::locate_woo_template( 'meta-boxes/views/html-order-items.php', 'admin' ) )
			include( $template );

		die();
	}

	/**
	 * Add order fee via ajax
	 * exact copy from /wp-content/plugins/woocommerce/includes/class-wc-ajax.php, with change to template selection
	 */
	public static function add_order_fee() {

		check_ajax_referer( 'order-item', 'security' );

		$order_id      = absint( $_POST['order_id'] );
		$order         = wc_get_order( $order_id );
		$order_taxes   = $order->get_taxes();
		$item          = array();

		// Add new fee
		$fee            = new stdClass();
		$fee->name      = '';
		$fee->tax_class = '';
		$fee->taxable   = $fee->tax_class !== '0';
		$fee->amount    = '';
		$fee->tax       = '';
		$fee->tax_data  = array();
		$item_id        = $order->add_fee( $fee );

		//include( 'admin/meta-boxes/views/html-order-fee.php' );
		//@@@@LOUSHOU - allow overtake of template
		if ( $template = QSOT_Templates::locate_woo_template( 'meta-boxes/views/html-order-fee.php', 'admin' ) )
			include( $template );

		// Quit out
		die();
	}

	/**
	 * Add order shipping cost via ajax
	 * exact copy from /wp-content/plugins/woocommerce/includes/class-wc-ajax.php, with change to template selection
	 */
	public static function add_order_shipping() {

		check_ajax_referer( 'order-item', 'security' );

		$order_id         = absint( $_POST['order_id'] );
		$order            = wc_get_order( $order_id );
		$order_taxes      = $order->get_taxes();
		$shipping_methods = WC()->shipping() ? WC()->shipping->load_shipping_methods() : array();
		$item             = array();

		// Add new shipping
		$shipping        = new stdClass();
		$shipping->label = '';
		$shipping->id    = '';
		$shipping->cost  = '';
		$shipping->taxes = array();
		$item_id         = $order->add_shipping( $shipping );

		//include( 'admin/meta-boxes/views/html-order-shipping.php' );
		//@@@@LOUSHOU - allow overtake of template
		if ( $template = QSOT_Templates::locate_woo_template( 'meta-boxes/views/html-order-shipping.php', 'admin' ) )
			include( $template );

		// Quit out
		die();
	}
}

if (defined('ABSPATH') && function_exists('add_action')) QSOT_order_admin_ajax::pre_init();
