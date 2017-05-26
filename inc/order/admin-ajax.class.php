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

		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			wp_die( -1 );
		}

		try {
			$order_id     = absint( $_POST['order_id'] );
			$order        = wc_get_order( $order_id );
			$items_to_add = wp_parse_id_list( is_array( $_POST['item_to_add'] ) ? $_POST['item_to_add'] : array( $_POST['item_to_add'] ) );

			if ( ! $order ) {
				throw new Exception( __( 'Invalid order', 'woocommerce' ) );
			}

			ob_start();

			foreach ( $items_to_add as $item_to_add ) {
				if ( ! in_array( get_post_type( $item_to_add ), array( 'product', 'product_variation' ) ) ) {
					continue;
				}
				$item_id     = $order->add_product( wc_get_product( $item_to_add ) );
				$item        = apply_filters( 'woocommerce_ajax_order_item', $order->get_item( $item_id ), $item_id );
				$order_taxes = $order->get_taxes();
				$class       = 'new_row';

				do_action( 'woocommerce_ajax_add_order_item_meta', $item_id, $item );
				//include( 'admin/meta-boxes/views/html-order-item.php' );
				//@@@@LOUSHOU - allow overtake of template
				if ( $template = QSOT_Templates::locate_woo_template( 'meta-boxes/views/html-order-item.php', 'admin' ) )
					include( $template );
			}

			wp_send_json_success( array(
				'html' => ob_get_clean(),
			) );
		} catch ( Exception $e ) {
			wp_send_json_error( array( 'error' => $e->getMessage() ) );
		}

	}

	/**
	 * Save order items via ajax
	 * exact copy from /wp-content/plugins/woocommerce/includes/class-wc-ajax.php, with change to template selection
	 */
	public static function save_order_items() {
		check_ajax_referer( 'order-item', 'security' );

		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			wp_die( -1 );
		}

		if ( isset( $_POST['order_id'], $_POST['items'] ) ) {
			$order_id = absint( $_POST['order_id'] );

			// Parse the jQuery serialized items
			$items = array();
			parse_str( $_POST['items'], $items );

			// Save order items
			wc_save_order_items( $order_id, $items );

			// Return HTML items
			$order = wc_get_order( $order_id );
			//include( 'admin/meta-boxes/views/html-order-items.php' );
			//@@@@LOUSHOU - allow overtake of template
			if ( $template = QSOT_Templates::locate_woo_template( 'meta-boxes/views/html-order-items.php', 'admin' ) )
				include( $template );
		}
		wp_die();
	}

	/**
	 * Load order items via ajax
	 * exact copy from /wp-content/plugins/woocommerce/includes/class-wc-ajax.php, with change to template selection
	 */
	public static function load_order_items() {
		check_ajax_referer( 'order-item', 'security' );

		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			wp_die( -1 );
		}

		// Return HTML items
		$order_id = absint( $_POST['order_id'] );
		$order    = wc_get_order( $order_id );
		//include( 'admin/meta-boxes/views/html-order-items.php' );
		//@@@@LOUSHOU - allow overtake of template
		if ( $template = QSOT_Templates::locate_woo_template( 'meta-boxes/views/html-order-items.php', 'admin' ) )
			include( $template );
		wp_die();
	}

	/**
	 * Add order fee via ajax
	 * exact copy from /wp-content/plugins/woocommerce/includes/class-wc-ajax.php, with change to template selection
	 */
	public static function add_order_fee() {
		check_ajax_referer( 'order-item', 'security' );

		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			wp_die( -1 );
		}

		try {
			$order_id    = absint( $_POST['order_id'] );
			$order       = wc_get_order( $order_id );
			$order_taxes = $order->get_taxes();
			$item        = new WC_Order_Item_Fee();
			$item->set_order_id( $order_id );
			$item_id     = $item->save();

			ob_start();
			//include( 'admin/meta-boxes/views/html-order-fee.php' );
			//@@@@LOUSHOU - allow overtake of template
			if ( $template = QSOT_Templates::locate_woo_template( 'meta-boxes/views/html-order-fee.php', 'admin' ) )
				include( $template );

			wp_send_json_success( array(
				'html' => ob_get_clean(),
			) );
		} catch ( Exception $e ) {
			wp_send_json_error( array( 'error' => $e->getMessage() ) );
		}
	}

	/**
	 * Add order shipping cost via ajax
	 * exact copy from /wp-content/plugins/woocommerce/includes/class-wc-ajax.php, with change to template selection
	 */
	public static function add_order_shipping() {
		check_ajax_referer( 'order-item', 'security' );

		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			wp_die( -1 );
		}

		try {
			$order_id         = absint( $_POST['order_id'] );
			$order            = wc_get_order( $order_id );
			$order_taxes      = $order->get_taxes();
			$shipping_methods = WC()->shipping() ? WC()->shipping->load_shipping_methods() : array();

			// Add new shipping
			$item = new WC_Order_Item_Shipping();
			$item->set_shipping_rate( new WC_Shipping_Rate() );
			$item->set_order_id( $order_id );
			$item_id = $item->save();

			ob_start();
			//include( 'admin/meta-boxes/views/html-order-shipping.php' );
			//@@@@LOUSHOU - allow overtake of template
			if ( $template = QSOT_Templates::locate_woo_template( 'meta-boxes/views/html-order-shipping.php', 'admin' ) )
				include( $template );

			wp_send_json_success( array(
				'html' => ob_get_clean(),
			) );
		} catch ( Exception $e ) {
			wp_send_json_error( array( 'error' => $e->getMessage() ) );
		}
	}
}

if (defined('ABSPATH') && function_exists('add_action')) QSOT_order_admin_ajax::pre_init();
