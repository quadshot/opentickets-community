<?php if ( __FILE__ == $_SERVER['SCRIPT_FILENAME'] ) die( header( 'Location: /') );

// controls the core functionality of the evet area post type
class QSOT_Ticket_Product {
	// container for the singleton instance
	protected static $instance = null;

	// get the singleton instance
	public static function instance() {
		// if the instance already exists, use it
		if ( isset( self::$instance ) && self::$instance instanceof QSOT_Ticket_Product )
			return self::$instance;

		// otherwise, start a new instance
		return self::$instance = new QSOT_Ticket_Product();
	}

	// constructor. handles instance setup, and multi instance prevention
	public function __construct() {
		// if there is already an instance of this object, then bail now
		if ( isset( self::$instance ) && self::$instance instanceof QSOT_Ticket_Product )
			throw new Exception( sprintf( __( 'There can only be one instance of the %s object at a time.', 'opentickets-community-edition' ), __CLASS__ ), 12000 );

		// otherwise, set this as the known instance
		self::$instance = $this;

		// and call the intialization function
		$this->initialize();
	}

	// destructor. handles instance destruction
	public function __destruct() {
		$this->deinitialize();
	}


	// initialize the object. maybe add actions and filters
	public function initialize() {
		// register the assets that may be used by this class
		add_action( 'init', array( &$this, 'register_assets' ), 100 );

		// add our filters that modify the cart and order item metas for the basic ticket information
		add_filter( 'qsot-ticket-item-meta-keys', array( &$this, 'meta_keys_maintained' ), 10, 1 );
		add_filter( 'qsot-ticket-item-hidden-meta-keys', array( &$this, 'meta_keys_hidden' ), 10, 1 );

		// fetch a complete list of all products that are tickets
		add_filter( 'qsot-get-all-ticket-products', array( &$this, 'get_all_ticket_products' ), 100, 2 );

		// run 
		add_filter( 'qsot-price-formatted', array( &$this, 'formatted_price' ), 10, 1 );

		// add the 'ticket' production option (next to 'virtual' and 'downloadable'), and handle the saving of that value
		add_filter( 'product_type_options', array( &$this, 'add_ticket_product_type_option' ), 999 );
		add_action( 'woocommerce_process_product_meta', array( &$this, 'save_product_meta' ), 999, 2 );
		add_filter( 'qsot-item-is-ticket', array( &$this, 'item_is_ticket' ), 10, 2 );

		// ticket products do not need processing, so they can skip straight to completed
		add_action( 'woocommerce_order_item_needs_processing', array( &$this, 'tickets_dont_need_processing' ), 10, 3 );

		// add the admin ajax handler
		$aj = QSOT_Ajax::instance();
		$aj->register( 'update-order-items', array( &$this, 'admin_ajax_update_order_items' ), array( 'edit_shop_orders' ), null, 'qsot-admin-ajax' );
	}

	// deinitialize the object. remove actions and filter
	public function deinitialize() {
		remove_filter( 'qsot-ticket-item-meta-keys', array( &$this, 'meta_keys_maintained' ), 10 );
		remove_filter( 'qsot-ticket-item-hidden-meta-keys', array( &$this, 'meta_keys_hidden' ), 10 );
		remove_filter( 'qsot-get-all-ticket-products', array( &$this, 'get_all_ticket_products' ), 100 );
		remove_filter( 'qsot-price-formatted', array( &$this, 'formatted_price' ), 10 );
		remove_filter( 'product_type_options', array( &$this, 'remove_ticket_product_type_option' ), 999 );
		remove_action( 'woocommerce_process_product_meta', array( &$this, 'save_product_meta' ), 999 );
		remove_filter( 'qsot-item-is-ticket', array( &$this, 'item_is_ticket' ), 10 );
		remove_action( 'woocommerce_order_item_needs_processing', array( &$this, 'tickets_dont_need_processing' ), 10 );
	}

	// register the assets we might use
	public function register_assets() {
		// initialize the reusabel data
		$url = QSOT::plugin_url();
		$version = QSOT::version();
	}

	// add meta keys that should be maintained in the cart and saved into order items
	public function meta_keys_maintained( $list ) {
		$list[] = 'event_id';
		return $list;
	}

	// add meta keys that should be hidden on order item display
	public function meta_keys_hidden( $list ) {
		$list[] = '_event_id';
		return $list;
	}

	// fetch a list of all ticket products, and add their various pricing metas to the result
	public static function get_all_ticket_products( $list, $format='objects' ) {
		// args used in the get_post() method to find all ticket products
		$args = array(
			'post_type' => 'product',
			'post_status' => array( 'publish' ),
			'perm' => 'readable',
			'posts_per_page' => -1,
			'orderby' => 'title',
			'order' => 'asc',
			'fields' => 'ids',
			'meta_query' => array(
				array(
					'key' => '_ticket',
					'value' => 'yes',
					'compare' => '=',
				),
			),
		);

		// if the current user can read private posts, then add private post_status
		if ( current_user_can( 'read_private_posts' ) )
			$args['post_status'][] = 'private';

		// get the ids of all the ticket products
		$ids = get_posts( $args );

		// if we were asked for the ids only, then return them now
		if ( 'ids' == $format )
			return $ids;

		$tickets = array();
		// otherwise load all the ticket products, and meta, into an array we will return
		foreach ( $ids as $id ) {
			// load the product
			$ticket = wc_get_product( $id );

			// if the product is not loaded, or is an error, then bail
			if ( ! is_object( $ticket ) || is_wp_error( $ticket ) )
				continue;

			// add some of the basic meta for pricing
			$ticket->post->meta = array();
			$ticket->post->meta['price_raw'] = $ticket->price;
			$ticket->post->meta['price_html'] = wc_price($ticket->post->meta['price_raw']);
			$ticket->post->meta['price'] = apply_filters('qsot-price-formatted', $ticket->post->meta['price_raw']);

			// shorthand the propername of the ticket, so we dont have to keep doing it
			$ticket->post->proper_name = apply_filters( 'the_title', $ticket->get_title(), $ticket->id );

			// add the ticket to the indexed return list
			$tickets[ '' . $ticket->post->ID ] = $ticket;
		}

		return $tickets;
	}

	// non-html-element version of the formatted price. will still contain entities
	public static function formatted_price($price) {
		return strip_tags( wc_price( $price ) );
	}

	// add the product option that allows the admin to define a product as a ticket
	public static function add_ticket_product_type_option( $list ) {
		$list['ticket'] = array(
			'id' => '_ticket',
			'wrapper_class' => 'show_if_simple',
			'label' => __( 'Ticket', 'opentickets-community-edition' ),
			'description' => __( 'Allows this product to be assigned as a ticket, when configuring pricing for an event.', 'opentickets-community-edition' ),
		);
	
		return $list;
	}

	// save the meta that designates a product as a ticket
	public static function save_product_meta( $post_id, $post ) {
		// figure out the appropriate value to save
		$is_ticket = isset( $_POST['_ticket'] ) ? 'yes' : 'no';

		// update the value in the database
		update_post_meta( $post_id, '_ticket', $is_ticket );

		// all ticket products should be hidden from the frontend shop. they should only be purchaseable via the ticket selection UI, because otherwise no event will be associated with it
		if ( $is_ticket == 'yes' )
			update_post_meta( $post_id, '_visibility', 'hidden' );
	}

	// determine if the item is a ticket product or not
	public static function item_is_ticket( $is, $item ) {
		// determine if the needed data is set or not
		if ( ! isset( $item['product_id'] ) || ( ! isset( $item['qty'] ) && ! isset( $item['quantity'] ) ) )
			return false;

		// determine if the product is a ticket or not
		$ticket = get_post_meta( $item['product_id'], '_ticket', true );
		return $ticket == 'yes';
	}

	// when tickets are purchased, they do not need to be 'processing'. thus, if the order is for only tickets, the order should go straight to completed after payment is received
	public static function tickets_dont_need_processing( $needs, $product, $order_id ) {
		if ( get_post_meta( $product->id, '_ticket', true ) == 'yes' )
			$needs = false;
		return $needs;
	}

	// handle the admin ajax request to refresh the order items list
	public static function admin_ajax_update_order_items( $resp, $event ) {
		// load the order
		$order = wc_get_order( isset( $_POST['order_id'] ) ? (int) $_POST['order_id'] : 0 );

		// if there is no order, then bail
		if ( ! is_object( $order ) || is_wp_error( $order ) ) {
			$resp['s'] = false;
			$resp['e'][] = __( 'Invalid order number.', 'opentickets-community-edition' );
			return $resp;
		}

		// setup our response
		$resp['s'] = true;
		$resp['i'] = array();

		// start capturing the output for this item
		ob_start();

		// cycle through the items that should be displayed, and display them
		foreach ( $order->get_items( array( 'line_item', 'fee', 'shipping', 'tax' ) ) as $item_id => $item ) {
			// determine the classes to add to the line item tr element
			$class = apply_filters( 'woocommerce_admin_order_items_class', 'new_row', $item, $order );

			// do something different for each item type
			switch ($item['type']) {
				// products
				case 'line_item' :
					$_product = $order->get_product_from_item($item);
					$template = QSOT_Templates::locate_woo_template( 'meta-boxes/views/html-order-item.php', 'admin' );
					if ( $template )
						include( $template );
				break;

				// fees
				case 'fee' :
					$template = QSOT_Templates::locate_woo_template( 'meta-boxes/views/html-order-fee.php', 'admin' );
					if ( $template )
						include( $template );
				break;

				// shipping charges
				case 'shipping' :
					$template = QSOT_Templates::locate_woo_template( 'meta-boxes/views/html-order-shipping.php', 'admin' );
					if ( $template )
						include( $template );
				break;

				// taxes
				case 'tax' :
					$template = QSOT_Templates::locate_woo_template( 'meta-boxes/views/html-order-tax.php', 'admin' );
					if ( $template )
						include( $template );
				break;
			}

			// add the filter that the core WC plugin would normally add after rendering this item
			do_action( 'woocommerce_order_item_' . $item['type'] . '_html');

			// grab the output of this item and store it in our response
			$resp['i'][] = ob_get_contents();
			ob_clean();
		}

		// end capturing
		ob_end_clean();

		return $resp;
	}
}

if ( defined( 'ABSPATH' ) && function_exists( 'add_action' ) )
	QSOT_Ticket_Product::instance();
