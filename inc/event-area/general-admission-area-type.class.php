<?php if ( __FILE__ == $_SERVER['SCRIPT_FILENAME'] ) die( header( 'Location: /') );

// class to handle the basic general admission event area type
class QSOT_General_Admission_Area_Type extends QSOT_Base_Event_Area_Type {
	// container for the singleton instance
	protected static $instance = array();

	// get the singleton instance
	public static function instance() {
		// if the instance already exists, use it
		if ( isset( self::$instance ) && self::$instance instanceof QSOT_General_Admission_Area_Type )
			return self::$instance;

		// otherwise, start a new instance
		return self::$instance = new QSOT_General_Admission_Area_Type();
	}

	// constructor. handles instance setup, and multi instance prevention
	public function __construct() {
		// if there is already an instance of this object, then bail now
		if ( isset( self::$instance ) && self::$instance instanceof QSOT_General_Admission_Area_Type )
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


	// setup the object
	public function initialize( $ajax=true ) {
		// defaults from parent
		parent::initialize();

		// setup the object description
		$this->priority = 1;
		$this->find_priority = PHP_INT_MAX;
		$this->slug = 'general-admission';
		$this->name = __( 'General Admission', 'opentickets-community-edition' );

		// after all the plugins have loaded, register this type
		add_action( 'plugins_loaded', array( &$this, 'plugins_loaded' ), 10 );

		// actions to help sync the cart with the actions we take in the event ticket UI
		add_filter( 'qsot-gaea-zoner-reserve-results', array( &$this, 'add_tickets_to_cart' ), 10, 2 );
		add_action( 'woocommerce_before_cart_item_quantity_zero', array( &$this, 'delete_ticket_from_cart' ), 10, 1 );
		add_action( 'woocommerce_cart_item_removed', array( &$this, 'delete_ticket_from_cart' ), 10, 1 );
		add_action( 'woocommerce_after_cart_item_quantity_update', array( &$this, 'update_reservations_on_cart_update' ), 10, 3 );

		// load the zoner when on the settings pages
		add_action( 'load-opentickets_page_opentickets-settings', array( &$this, 'get_zoner' ), -1 );

		// certain filters should only exist in the admin
		if ( is_admin() ) {
			// add the list of valid state types to the list that the seating chart will use to pull records
			add_filter( 'qsot-seating-report-state-map', array( &$this, 'add_state_types_to_report' ), 10, 2 );
		}

		if ( $ajax ) {
			// add the gaea ajax handlers
			$aj = QSOT_Ajax::instance();
			$aj->register( 'gaea-reserve', array( &$this, 'aj_reserve' ), array(), null, 'qsot-frontend-ajax' );
			$aj->register( 'gaea-remove', array( &$this, 'aj_remove' ), array(), null, 'qsot-frontend-ajax' );
			$aj->register( 'gaea-update', array( &$this, 'aj_update' ), array(), null, 'qsot-frontend-ajax' );

			// register our admin ajax functions
			$aj->register( 'gaea-add-tickets', array( &$this, 'admin_ajax_add_tickets' ), array( 'edit_shop_orders' ), null, 'qsot-admin-ajax' );
			$aj->register( 'gaea-update-ticket', array( &$this, 'admin_ajax_update_ticket' ), array( 'edit_shop_orders' ), null, 'qsot-admin-ajax' );
		}
	}

	// destroy the object
	public function deinitialize() {
		remove_action( 'switch_blog', array( &$this, 'setup_table_names' ), PHP_INT_MAX );
		remove_action( 'plugins_loaded', array( &$this, 'plugins_loaded' ), 10 );
		remove_filter( 'qsot-gaea-zoner-reserve-results', array( &$this, 'add_tickets_to_cart' ), 10 );
		remove_filter( 'qsot-ticket-item-meta-keys', array( &$this, 'meta_keys_maintained' ), 10 );
		remove_filter( 'qsot-ticket-item-hidden-meta-keys', array( &$this, 'meta_keys_hidden' ), 10 );
	}

	// register this area type after all plugins have loaded
	public function plugins_loaded() {
		// register this as an event area type
		do_action_ref_array( 'qsot-register-event-area-type', array( &$this ) );
	}

	// register the assets we may need in either the admin or the frontend, for this area_type
	public function register_assets() {
		// reusable data
		$url = QSOT::plugin_url();
		$version = QSOT::version();

		// register styles and scripts
		wp_register_style( 'qsot-gaea-event-frontend', $url . 'assets/css/frontend/event.css', array(), $version );
		wp_register_script( 'qsot-gaea-event-frontend', $url . 'assets/js/features/event-area/ui.js', array( 'qsot-tools' ), $version );
	}

	// enqueue the appropriate assets for the frontend
	public function enqueue_assets( $event ) {
		// if we do not have the required info, then bail
		if ( ! is_object( $event ) || ! isset( $event->event_area ) || ! is_object( $event->event_area ) )
			return;

		// if this event is not using an event area of this type, then bail now
		if ( ! isset( $event->event_area->area_type ) || ! is_object( $event->event_area->area_type ) || $this->slug !== $event->event_area->area_type->get_slug() )
			return;

		// include the base styling
		wp_enqueue_style('qsot-gaea-event-frontend');

		// get the ticket type for this event
		$ticket = $this->get_ticket_type( array( 'event_area' => $event->event_area ) );

		// if there is not a valid ticket for this event, then bail
		if ( ! is_object( $ticket ) || is_wp_error( $ticket ) || ! isset( $ticket->id ) )
			return;

		// get the zoner for this area type
		$zoner = $this->get_zoner();

		// get the valid stati for that zoner
		$stati = $zoner->get_stati();

		// enqueue the frontend event ui scrit
		wp_enqueue_script( 'qsot-gaea-event-frontend' );

		// are we allowed to show the available quantity?
		$show_qty = 'yes' == apply_filters( 'qsot-get-option-value', 'yes', 'qsot-show-available-quantity' );

		// setup the settings we need for that script to run
		wp_localize_script( 'qsot-gaea-event-frontend', '_qsot_gaea_tickets', apply_filters( 'qsot-event-frontend-settings', array(
			'nonce' => wp_create_nonce( 'do-qsot-frontend-ajax' ),
			'edata' => $this->_get_frontend_event_data( $event ),
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'templates' => $this->get_templates( $event ),
			'messages' => array(
				'available' => array(
					'msg' => ( $show_qty )
							? __( 'There are currently <span class="available"></span> <span rel="tt"></span> available.', 'opentickets-community-edition' )
							: str_replace( '<span class="available"></span> ', '', __( 'There are currently <span class="available"></span> <span rel="tt"></span> available.', 'opentickets-community-edition' ) ),
					'type' => 'msg'
				),
				'more-available' => array(
					'msg' => ( $show_qty )
							? __( 'There are currently <span class="available"></span> more <span rel="tt"></span> available.', 'opentickets-community-edition' )
							: str_replace( '<span class="available"></span> ', '', __( 'There are currently <span class="available"></span> <span rel="tt"></span> available.', 'opentickets-community-edition' ) ),
					'type' => 'msg'
				),
				'not-available' => array( 'msg' => __( 'We\'re sorry. There are currently no tickets available.', 'opentickets-community-edition' ), 'type' => 'error' ),
				'sold-out' => array( 'msg' => __( 'We are sorry. This event is sold out!', 'opentickets-community-edition' ), 'type' => 'error' ),
				'one-moment' => array( 'msg' => __( '<h1>One Moment Please...</h1>', 'opentickets-community-edition' ), 'type' => 'msg' ),
			),
			'owns' => $zoner->find( array( 'fields' => 'total', 'state' => $stati['r'][0], 'event_id' => $event->ID, 'ticket_type_id' => $ticket->id, 'customer_id' => $zoner->current_user() ) ),
		), $event ) );
	}

	// get the frontend template to use in the event selection ui
	public function get_templates( $event ) {
		// make sure we have an event area
		$event->event_area = isset( $event->event_area ) && is_object( $event->event_area ) ? $event->event_area : apply_filters( 'qsot-event-area-for-event', false, $GLOBALS['post'] );

		// if there is no event area, then bail
		if ( ! isset( $event->event_area ) || ! is_object( $event->event_area ) )
			return apply_filters( 'qsot-gaea-event-frontend-templates', apply_filters( 'qsot-event-frontend-templates', array(), $event ) );

		// get a list of all the templates we need
		$needed_templates = apply_filters( 'qsot-gaea-frontend-templates', array( 'ticket-selection', 'owns', 'msgs', 'msg', 'error', 'ticket-type' ), $event, $this );

		// aggregate the data needed for the templates
		$args = array(
			'limit' => apply_filters( 'qsot-event-ticket-purchase-limit', 0, $event->ID ),
			'max' => 1000000,
			'cart_url' => '#',
		);

		$cart = WC()->cart;
		// if there is a cart, then try to update the cart url
		if ( is_object( $cart ) )
			$args['cart_url'] = $cart->get_cart_url();

		// figure out the true max, based on available info
		$zoner = $this->get_zoner();
		$stati = $zoner->get_stati();
		$taken = $zoner->find( array( 'fields' => 'total', 'event_id' => $event->ID, 'state' => array( $stati['r'][0], $stati['c'][0] ) ) );
		$capacity = $event->event_area->meta['_capacity'];
		$capacity = $capacity > 0 ? $capacity : PHP_INT_MAX;
		$args['max'] = $args['limit'] > 0 ? min( $args['limit'], min( $args['max'], $capacity - $taken ) ) : min( $args['max'], $capacity - $taken );

		// allow modification of the args
		$args = apply_filters( 'qsot-gaea-frontend-templates-data', $args, $event, $this );

		$templates = array();
		// load each template in the list
		foreach ( $needed_templates as $template )
			$templates[ $template ] = QSOT_Templates::maybe_include_template( 'event-area/general-admission/' . $template . '.php', $args );

		return apply_filters( 'qsot-gaea-event-frontend-templates', $templates, $event );
	}

	// get the admin templates that are needed based on type and args
	public function get_admin_templates( $list, $type, $args='' ) {
		switch ( $type ) {
			case 'ticket-selection':
				$list['general-admission'] = array();

				// create a list of the templates we need
				$needed_templates = array( 'info', 'actions-change', 'actions-add', 'inner-change', 'inner-add' );

				// add the needed templates to the output list
				foreach ( $needed_templates as $template )
					$list['general-admission'][ $template ] = QSOT_Templates::maybe_include_template( 'admin/ticket-selection/general-admission/' . $template . '.php', $args );
			break;
		}

		return $list;
	}

	// construct the data array that holds all the info we send to the frontend UI for selecting tickets
	protected function _get_frontend_event_data( $event ) {
		// get our zoner for this event
		$zoner = $this->get_zoner();
		$stati = $zoner->get_stati();

		// get the ticket price for this event area
		$ticket = $this->get_ticket_type( array( 'event_area' => $event->event_area ) );

		// determine the total number of sold or reserved seats, thus far
		$reserved_or_confirmed = $zoner->find( array( 'fields' => 'total', 'state' => array( $stati['r'][0], $stati['c'][0] ), 'event_id' => $event->ID ) );

		// figure out how many that leaves for the picking
		//$cap = isset( $event->event_area->meta, $event->event_area->meta['_capacity'] ) ? $event->event_area->meta['_capacity'] : 0;
		$cap = apply_filters( 'qsot-get-event-capacity', 0, $event );
		$left = $cap > 0 ? max( 0, $cap - $reserved_or_confirmed ) : 1000000;

		// start putting together the results
		$out = array(
			'id' => $event->ID,
			'name' => apply_filters( 'the_title', $event->post_title, $event->ID ),
			'link' => get_permalink( $event->ID ),
			'parent_link' => get_permalink( $event->post_parent ),
			'capacity' => $cap,
			'available' => $left,
			'ticket' => null,
		);

		// if the ticket type is valid, then add it's information to the output
		if ( ! is_wp_error( $ticket ) && is_object( $ticket ) && isset( $ticket->id ) )
			$out['ticket'] = array(
				'name' => $ticket->get_title(),
				'price' => apply_filters( 'qsot-price-formatted', $ticket->get_price() ),
			);

		return apply_filters( 'qsot-frontend-event-data', $out, $event );
	}

	// when running the seating report, we need the report to know about our valid reservation states. add then here
	public function add_state_types_to_report( $list, $event_id ) {
		// get a list of the valid states from our zoner
		$zoner = $this->get_zoner();
		$stati = $zoner->get_stati();

		// add each one to the list we are returning
		foreach ( $stati as $status )
			$list[ $status[0] ] = $status;

		return $list;
	}

	// upon successful reservation of tickets, add those tickets to the cart
	public function add_tickets_to_cart( $success, $args ) {
		// if the reservation was not successful, then bail now
		if ( ! $success || is_wp_error( $success ) )
			return $success;

		// if there was an order number specified, then we dont need to add this item to a cart
		if ( isset( $args['order_id'] ) && $args['order_id'] > 0 )
			return $success;

		// make sure that the event is in an area that is of this area type
		$area_type = apply_filters( 'qsot-event-area-type-for-event', null, $args['event_id'] );
		if ( ! is_object( $area_type ) || $area_type->get_slug() !== $this->get_slug() )
			return $success;

		// otherwise, add a GA ticket to the cart, for this event, with this quantity
		// start by making sure we have a cart
		$cart = WC()->cart;
		if ( ! is_object( $cart ) ) {
			$this->get_zoner()->remove( $args );
			return new WP_Error( 'no_cart', __( 'Could not add those items to your cart.', 'opentickets-community-edition' ) );
		}

		// add the item to the cart. the WC_Cart class now handles situations where the item already exists, by simply updating the quantity
		$cart->add_to_cart( $args['ticket_type_id'], $args['final_qty'], '', array(), array( 'event_id' => $args['event_id'] ) );

		return $success;
	}

	// when updating the quantity of tickets in the cart page, we need to perform the same update on our reservations, if allowed
	public function update_reservations_on_cart_update( $cart_item_key, $quantity, $old_quantity ) {
		// if this is not an update cart scenario, then bail now
		if ( ! isset( $_POST['update_cart'] ) )
			return;

		// fetch the zoner
		$zoner = $this->get_zoner();
		$stati = $zoner->get_stati();

		// get the cart item
		$items = WC()->cart->get_cart();
		$item = isset( $items[ $cart_item_key ] ) ? $items[ $cart_item_key ] : false;
		if ( empty( $item ) || ! isset( $item['event_id'] ) )
			return;

		// load the event and check that it is for this type of event area before doing anything else
		$area_type = apply_filters( 'qsot-event-area-type-for-event', false, $item['event_id'] );
		if ( ! is_object( $area_type ) || is_wp_error( $area_type ) || $area_type->get_slug() !== $this->get_slug() )
			return;

		// remove recursive filter
		remove_action( 'woocommerce_after_cart_item_quantity_update', array( &$this, 'update_reservations_on_cart_update' ), 10 );

		// update the reservations
		$result = $zoner->reserve( false, array(
			'event_id' => $item['event_id'],
			'ticket_type_id' => $item['product_id'],
			'quantity' => $quantity,
		) );

		// if the update failed, then revert the quantity
		if ( ! is_wp_error( $result ) && is_scalar( $result ) && $result > 0 ) {
			// if the final quantity does not equal the requested quantity, then pop a message indicating that the reason is because there are not enough tickets
			if ( $result != $quantity )
				wc_add_notice( sprintf( __( 'There were not %d tickets available. We reserved %d for you instead, which is all that is available.', 'opentickets-community-edition' ), $quantity, $result ), 'error' );

			WC()->cart->set_quantity( $cart_item_key, $result, true );
		} else if ( ! $result || is_wp_error( $result ) ) {
			// reset the quantity and pop an error as to why
			WC()->cart->set_quantity( $cart_item_key, $old_quantity, true );
			if ( is_wp_error( $result ) )
				wc_add_notice( implode( '', $result->get_error_messages() ), 'error' );
			else
				wc_add_notice( __( 'Could not update the quantity of that item.', 'opentickets-community-edition' ), 'error' );
		}

		// readd this filter for later checks
		add_action( 'woocommerce_after_cart_item_quantity_update', array( &$this, 'update_reservations_on_cart_update' ), 10, 3 );
	}

	// during cart item removal, we need to sync the ticket table as well
	public function delete_ticket_from_cart( $item_key ) {
		$WC = WC();
		// if we dont have a cart or woocommcer object, then bail
		if ( ! is_object( $WC ) || ! is_object( $WC->cart ) )
			return;

		$item = null;
		// figure out which item we are syncing
		// check the removed items first
		if ( isset( $WC->cart->removed_cart_contents[ $item_key ] ) ) {
			// grab the item from the removed contents table
			$item = $WC->cart->removed_cart_contents[ $item_key ];

			// remove the item from the remove contents table, so that it cannot be 'restored'. we do this because restoring could happen after the available ticket has been purchased elsewhere
			unset( $WC->cart->removed_cart_contents[ $item_key ] );
		// if it is not in the removed items, checked the current items
		} else if ( isset( $WC->cart->cart_contents[ $item_key ] ) ) {
			$item = $WC->cart->cart_contents[ $item_key ];
		}

		// if we did not find the item, then bail
		if ( empty( $item ) )
			return;

		// if the item is not linked to an event, then bail
		if ( ! isset( $item['event_id'] ) )
			return;

		// load the event and area_type
		$event = get_post( $item['event_id'] );
		$area_type = apply_filters( 'qsot-event-area-type-for-event', false, $event );

		// if the event's area type is not this type, then bail
		if ( ! is_object( $area_type ) || is_wp_error( $area_type ) || $area_type->get_slug() !== $this->get_slug() )
			return;

		// get the zoner
		$zoner = $this->get_zoner();
		$stati = $zoner->get_stati();

		// remove the reservation
		$zoner->remove( false, array(
			'event_id' => $item['event_id'],
			'ticket_type_id' => $item['product_id'],
			'customer_id' => $zoner->current_user(),
			'quantity' => $item['quantity'],
			'state' => $stati['r'][0],
		) );
	}

	// determine if the supplied post could be of this area type
	public function post_is_this_type( $post ) {
		// if this is not an event area, then it cannot be
		if ( 'qsot-event-area' != $post->post_type )
			return false;

		$type = get_post_meta( $post->ID, '_qsot-event-area-type', true );
		// if the area_type is set, and it is not equal to this type, then bail
		if ( ! empty( $type ) && $type !== $this->slug )
			return false;

		// otherwise, it is
		return true;
	}

	// get the list of metaboxes relevant for this event type
	// postbox_classes_qsot-event-area_qsot-event-area-type
	public function get_meta_boxes() {
		// if we already generated this list, then use the cached version
		if ( is_array( $this->meta_boxes ) )
			return $this->meta_boxes;

		// create a container for the metabox list
		$meta_boxes = array();

		// create a list of all the metaboxes we should add for this area type
		foreach ( apply_filters( 'qsot-gaea-attributes-screens', array( 'qsot-event-area' ) ) as $screen ) {
			$meta_boxes[] = array(
				'qsot-gaea-attributes',
				__( 'General Admission - Attributes', 'opentickets-community-edition' ),
				array( &$this, 'mb_attributes' ),
				$screen,
				'normal',
				'high'
			);
		}

		return $this->meta_boxes = apply_filters( 'qsot-gaea-meta-boxes', $meta_boxes );
	}

	// draw the contents of the attributes metabox
	public function mb_attributes( $post ) {
		// list of settings to fetch
		$fields = array(
			'image' => '_thumbnail_id',
			'capacity' => '_capacity',
			'ticket' => '_pricing_options',
		);

		$options = array();
		// load the options from the list above
		foreach ( $fields as $key => $meta_key )
			$options[ $key ] = get_post_meta( $post->ID, $meta_key, true );

		// get the name of the ticket price that is selected
		$ticket_name = $options['ticket'] > 0 ? get_the_title( $options['ticket'] ) : '';
		?>
			<div class="qsot-mb">
				<input type="hidden" name="qsot-gaea-n" value="<?php echo wp_create_nonce( 'save-qsot-gaea-now' ); ?>" />

				<div class="field edit-field area-ticket-wrap" rel="field">
					<label for="gaea-ticket"><?php _e( 'Ticket Price', 'opentickets-community-edition' ) ?></label>
					<input type="hidden" class="widefat ticket use-select2" rel="ticket" name="gaea-ticket" id="gaea-ticket" value=""
							data-sa="find-product" data-init-value="<?php echo esc_attr( @json_encode( array( 'id' => $options['ticket'], 'text' => $ticket_name ) ) ) ?>" data-minchar="2" />
				</div>

				<div class="field edit-field area-capacity-wrap" rel="field">
					<label for="gaea-capacity"><?php _e( 'Capacity', 'opentickets-community-edition' ) ?></label>
					<input autocomplete="off" type="number" min="0" step="1" class="widefat capacity" rel="capacity" name="gaea-capacity" id="gaea-capacity" value="<?php echo esc_attr( $options['capacity'] ) ?>" />
				</div>

				<div class="field edit-field image-select-wrap" rel="field">
					<label for="gaea-img-id"><?php _e( 'Event Area Image', 'opentickets-community-edition' ) ?></label>

					<div>
						<div class="clear"></div>
						<button class="button use-popmedia" rel="change-img" scope="[rel='field']"><?php _e( 'Select Image', 'opentickets-community-edition' ) ?></button>
						<a href="#remove-img" rel="remove-img" class="remove-img-btn" scope="[rel='field']" preview="[rel='img-wrap']"><?php _e( 'remove', 'opentickets-community-edition' ) ?></a>
					</div>

					<div>
						<div class="image-preview" size="large" rel="img-wrap"><?php echo wp_get_attachment_image( $options['image'], 'large' ) ?></div>
						<input type="hidden" name="gaea-img-id" id="gaea-img-id" value="<?php echo esc_attr( $options['image'] ) ?>" rel="img-id" />
						<div class="clear"></div>
					</div>
				</div>
			</div>
		<?php
	}

	// handle the saving of event areas of this type
	// registered during area_type registration. then called in inc/event-area/post-type.class.php save_post()
	public function save_post( $post_id, $post, $updated ) {
		// check the nonce for our settings. if not there or invalid, then bail
		if ( ! isset( $_POST['qsot-gaea-n'] ) || ! wp_verify_nonce( $_POST['qsot-gaea-n'], 'save-qsot-gaea-now' ) )
			return;

		// save all the data for this type
		update_post_meta( $post_id, '_pricing_options', isset( $_POST['gaea-ticket'] ) ? $_POST['gaea-ticket'] : '' );
		update_post_meta( $post_id, '_capacity', isset( $_POST['gaea-capacity'] ) ? $_POST['gaea-capacity'] : '' );
		update_post_meta( $post_id, '_thumbnail_id', isset( $_POST['gaea-img-id'] ) ? $_POST['gaea-img-id'] : '' );
	}

	// fetch the object that is handling the registrations for this event_area type
	public function get_zoner() {
		return QSOT_General_Admission_Zoner::instance();
	}

	// render the frontend ui
	public function render_ui( $event, $event_area ) {
		// get the zoner for this event_area
		$zoner = $event_area->area_type->get_zoner();

		// get the zoner stati
		$stati = $zoner->get_stati();

		// figure out how many tickets we have reserved for this event currently
		$reserved = $zoner->find( array( 'fields' => 'total-by-ticket-type', 'event_id' => $event->ID, 'customer_id' => $zoner->current_user(), 'order_id' => 0, 'state' => $stati['r'][0] ) );

		// default template
		$template_file = 'post-content/event-area-closed.php';

		// if the event can have ticket sold, or if it is sold out but this user has active reservations, then show the event ticket selection UI
		if ( apply_filters( 'qsot-can-sell-tickets-to-event', false, $event->ID ) || array_sum( array_values( $reserved ) ) > 0 )
			$template_file = 'post-content/event-area.php';

		$out = '';
		// if we have the event area, then go ahead and render the appropriate interface
		if ( is_object( $event_area ) ) {
			$event_area->ticket = $this->get_ticket_type( array( 'event_area' => $event_area ) );
			$template = apply_filters( 'qsot-locate-template', '', array( $template_file, 'post-content/event-area.php' ), false, false );
			ob_start();
			if ( ! empty( $template ) )
				QSOT_Templates::include_template( $template, apply_filters( 'qsot-draw-event-area-args', array(
					'event' => $event,
					'reserved' => $reserved,
					'area' => $event_area,
				), $event, $event_area ), true, false );
			$out = ob_get_contents();
			ob_end_clean();
		}

		// allow modification if needed
		return apply_filters( 'qsot-no-js-seat-selection-form', $out, $event_area, $event, 0, $reserved );
	}

	// get the event area display name, based on the event area and its meta
	public function get_event_area_display_name( $event_area ) {
		// get the capacity of the event_area
		$capacity = (int) get_post_meta( $event_area->ID, '_capacity', true );

		// get the ticket product that represents the price for this event
		$ticket_product_id = get_post_meta( $event_area->ID, '_pricing_options', true );
		$product = is_numeric( $ticket_product_id ) && $ticket_product_id > 0 ? wc_get_product( $ticket_product_id ) : null;
		if ( ! is_object( $product ) || is_wp_error( $product ) )
			return sprintf( '%s [x%s]', apply_filters( 'the_title', $event_area->post_title, $event_area->ID ), $capacity );

		// construct the final name for the event area to be displayed
		return sprintf(
			'%s [x%s] / %s (%s)',
			apply_filters( 'the_title', $event_area->post_title, $event_area->ID ),
			$capacity,
			$product->get_title(),
			apply_filters( 'qsot-price-formatted', $product->get_price() )
		);
	}

	// determine the ticket_type for the supplied data for this area_type
	public function get_ticket_type( $data='' ) {
		// normalize the supplied data
		$data = wp_parse_args( $data, array(
			'event' => false,
			'event_area' => false,
		) );

		// if the event is supplied, use the event_area from that event
		if ( false !== $data['event'] ) {
			if ( is_object( $data['event'] ) && isset( $data['event']->ID ) )
				$data['event_area'] = get_post_meta( $data['event']->ID, '_event_area_id', true );
			else if ( is_numeric( $data['event'] ) )
				$data['event_area'] = get_post_meta( $data['event'], '_event_area_id', true );
		}

		// if there is no event_area in the supplied, data, then bail
		if ( false == $data['event_area'] )
			return new WP_Error( 'invalid_event_area', __( 'Could not find that event.', 'opentickets-community-edition' ) );

		// if the event_area supplied is an id, try to load the area
		if ( is_numeric( $data['event_area'] ) )
			$data['event_area'] = apply_filters( 'qsot-get-event-area', false, $data['event_area'] );

		// if there is still no event area object, then bail
		if ( ! is_object( $data['event_area'] ) || ! isset( $data['event_area']->ID ) )
			return new WP_Error( 'invalid_event_area', __( 'Could not find that event.', 'opentickets-community-edition' ) );

		// if the pricing opiton is not set for this area, then bail
		if ( ! isset( $data['event_area']->meta, $data['event_area']->meta['_pricing_options'] ) )
			return new WP_Error( 'invalid_event_area', __( 'Could not find that event.', 'opentickets-community-edition' ) );

		// get the resulting ticket
		$result = wc_get_product( $data['event_area']->meta['_pricing_options'] );
		if ( is_wp_error( $result ) )
			return $result;
		if ( ! is_object( $result ) || ! isset( $result->id ) )
			return new WP_Error( 'invalid_ticket_type', __( 'Could not find the price for this event.', 'opentickets-community-edition' ) );

		// if the current user canread this ticket, return it, otherwise fail
		if ( 'private' == $result->post->post_status && ! current_user_can( 'read', $result->id ) )
			return new WP_Error( 'access_denied', __( 'Cannot find the price for this event.', 'opentickets-community-edition' ) );

		return $result;
	}

	// add the 'data' to each response passed to this function
	protected function _add_data( $resp, $event, $event_area=null, $ticket_type=null ) {
		$resp['data'] = array( 'owns' => 0, 'available' => 0, 'available_more' => 0 );
		// get the needex objects for data construction
		$zoner = $this->get_zoner();
		$event_area = is_object( $event_area ) ? $event_area : ( isset( $event->event_area ) && is_object( $event->event_area ) ? $event->event_area : apply_filters( 'qsot-event-area-for-event', false, $event ) );
		$ticket_type = is_object( $ticket_type ) ? $ticket_type : $this->get_ticket_type( array( 'event_area' => $event_area ) );

		// if any of the data is missing or errors, then bail
		if ( ! is_object( $zoner ) || is_wp_error( $zoner ) )
			return $resp;
		if ( ! is_object( $event_area ) || is_wp_error( $event_area ) )
			return $resp;
		if ( ! is_object( $ticket_type ) || is_wp_error( $ticket_type ) )
			return $resp;

		$stati = $zoner->get_stati();
		// add the extra data used to update the ui
		$resp['data'] = array(
			'owns' => $zoner->find( array( 'fields' => 'total', 'event_id' => $event->ID, 'customer_id' => $zoner->current_user(), 'ticket_type_id' => $ticket_type->id, 'order_id' => 0, 'state' => $stati['r'][0] ) ),
			'available' => 0,
			'available_more' => 0,
		);

		// only show the remaining availability if we are allowed by settings
		if ( 'yes' == apply_filters( 'qsot-get-option-value', 'yes', 'qsot-show-available-quantity' ) ) {
			// determine how many tickets have been sold or reserved for this event so far
			$reserved_or_confirmed = $zoner->find( array( 'fields' => 'total', 'event_id' => $event->ID ) );

			// calculate how many are left
			$event->event_area = $event_area;
			$capacity = apply_filters( 'qsot-get-event-capacity', 0, $event );
			$left = max( 0, $capacity - $reserved_or_confirmed );

			// update the response
			$resp['data']['available'] = $resp['data']['available_more'] = $capacity > 0 ? $left : 1000000;
		}

		return $resp;
	}

	// handle the reserve ticket ajax requests
	public function aj_reserve( $resp, $event ) {
		// determine the quantity that is being requested
		$qty = intval( $_POST['quantity'] );

		// if the quantity is not a positive number, then bail
		if ( $qty <= 0 ) {
			$resp['e'][] = __( 'The quantity must be greater than zero.', 'opentickets-community-edition' );
			return $this->_add_data( apply_filters( 'qsot-seating-ajax-response-reserve', $resp, $event, null, $ticket_type ), $event );
		}

		// get the event_area based on the event
		$event_area = apply_filters( 'qsot-event-area-for-event', false, $event );
		if ( ! is_object( $event_area ) ) {
			$resp['e'][] = __( 'Could not find that event.', 'opentickets-community-edition' );
			return $this->_add_data( apply_filters( 'qsot-seating-ajax-response-reserve', $resp, $event, null, $ticket_type ), $event );
		}

		// determine the ticket type to use for the 
		$ticket_type = $this->get_ticket_type( array( 'event_area' => $event_area ) );

		// get the zoner that will handle this request
		$zoner = $this->get_zoner();

		// process the reservation request
		$res = $zoner->reserve( false, array(
			'event_id'=> $event->ID,
			'ticket_type_id' => $ticket_type->id,
			'customer_id' => $zoner->current_user(),
			'quantity' => $qty,
			'order_id' => 0,
		) );

		// if the result was successful
		if ( $res && ! is_wp_error( $res ) ) {
			// construct an affirmative response, with the remainder data if applicable
			$resp['s'] = true;
			$resp['m'] = array( __( 'Updated your reservations successfully.', 'opentickets-community-edition' ) );

			// force the cart to send the cookie, because sometimes it doesnt. stupid bug
			WC()->cart->maybe_set_cart_cookies();
		// if the request failed for a known reason, then add that reason to the response
		} else if ( is_wp_error( $res ) ) {
			$resp['e'] = array_merge( $resp['e'], $res->get_error_messages() );
		// otherwise it failed for an unknown reason. add an error to the response
		} else {
			$resp['e'][] = __( 'Could not update your reservations.', 'opentickets-community-edition' );
		}

		return $this->_add_data( apply_filters( 'qsot-seating-ajax-response-reserve', $resp, $event, $event_area, $ticket_type ), $event, $event_area, $ticket_type );
	}

	// handle the remove reservation ajax requests
	public function aj_remove( $resp, $event ) {
		// get the event_area based on the event
		$event_area = apply_filters( 'qsot-event-area-for-event', false, $event );
		if ( ! is_object( $event_area ) ) {
			$resp['e'][] = __( 'Could not find that event.', 'opentickets-community-edition' );
			return $this->_add_data( apply_filters( 'qsot-seating-ajax-response-remove', $resp, $event, null, $ticket_type ), $event );
		}

		// determine the ticket type to use for the 
		$ticket_type = $this->get_ticket_type( array( 'event_area' => $event_area ) );

		// get the zoner that will handle this request
		$zoner = $this->get_zoner();

		// get the list of zoner stati
		$stati = $zoner->get_stati();

		// get information about the current user, so we can identify them
		$current_user = $zoner->current_user();
		$current_user_id = get_current_user_id();

		// aggregate the args used for the remote function
		$rargs = array(
			'event_id'=> $event->ID,
			'ticket_type_id' => $ticket_type,
			// after an order is created, the user's user_id becomes the session_customer_id. adding this logic for thos wishywashy ppl
			'customer_id' => array_filter( array( $current_user, $current_user_id ) ),
			'order_id' => 0,
			'state' => array( $stati['r'][0], $stati['c'][0] ),
		);

		// include any order ids for orders that still require payment
		$rargs['order_id'] = is_array( $rargs['order_id'] ) ? $rargs['order_id'] : array( absint( $rargs['order_id'] ) );
		$rargs['order_id'][] = isset( WC()->session->order_awaiting_payment ) ? absint( WC()->session->order_awaiting_payment ) : 0;
		$rargs['order_id'] = array_unique( $rargs['order_id'] );

		// attempt to remove the reservation
		$res = $zoner->remove( false, $rargs );

		// if the result was successful
		if ( $res && ! is_wp_error( $res ) ) {
			// construct an affirmative response, with the remainder data if applicable
			$resp['s'] = true;
			$resp['m'] = array( __( 'Updated your reservations successfully.', 'opentickets-community-edition' ) );

			// force the cart to send the cookie, because sometimes it doesnt. stupid bug
			WC()->cart->maybe_set_cart_cookies();
		// if the request failed for a known reason, then add that reason to the response
		} else if ( is_wp_error( $res ) ) {
			$resp['e'] = array_merge( $resp['e'], $res->get_error_messages() );
		// otherwise it failed for an unknown reason. add an error to the response
		} else {
			$resp['e'][] = __( 'Could not update your reservations.', 'opentickets-community-edition' );
		}

		return $this->_add_data( apply_filters( 'qsot-seating-ajax-response-remove', $resp, $event, $event_area, $ticket_type ), $event, $event_area, $ticket_type );
	}

	// handle the update reservation ajax requests
	public function aj_update( $resp, $event ) {
		// determine the quantity that is being requested
		$qty = intval( $_POST['quantity'] );

		// if the quantity is not a positive number, then bail
		if ( $qty <= 0 ) {
			$resp['e'][] = __( 'The quantity must be greater than zero.', 'opentickets-community-edition' );
			return $this->_add_data( apply_filters( 'qsot-seating-ajax-response-update', $resp, $event, null, $ticket_type ), $event );
		}

		// get the event_area based on the event
		$event_area = apply_filters( 'qsot-event-area-for-event', false, $event );
		if ( ! is_object( $event_area ) ) {
			$resp['e'][] = __( 'Could not find that event.', 'opentickets-community-edition' );
			return $this->_add_data( apply_filters( 'qsot-seating-ajax-response-update', $resp, $event, null, $ticket_type ), $event );
		}

		// determine the ticket type to use for the 
		$ticket_type = $this->get_ticket_type( array( 'event_area' => $event_area ) );

		// get the zoner that will handle this request
		$zoner = $this->get_zoner();

		// get the list of zoner stati
		$stati = $zoner->get_stati();

		// process the reservation request
		$res = $zoner->update( false, array(
			'event_id'=> $event->ID,
			'ticket_type_id' => $ticket_type->id,
			'customer_id' => $zoner->current_user(),
			'order_id' => 0,
			'status' => $stati['r'][0],
		), array( 'quantity' => $qty ) );

		// if the result was successful
		if ( $res && ! is_wp_error( $res ) ) {
			// construct an affirmative response, with the remainder data if applicable
			$resp['s'] = true;
			$resp['m'] = array( __( 'Updated your reservations successfully.', 'opentickets-community-edition' ) );

			// force the cart to send the cookie, because sometimes it doesnt. stupid bug
			WC()->cart->maybe_set_cart_cookies();
		// if the request failed for a known reason, then add that reason to the response
		} else if ( is_wp_error( $res ) ) {
			$resp['e'] = array_merge( $resp['e'], $res->get_error_messages() );
		// otherwise it failed for an unknown reason. add an error to the response
		} else {
			$resp['e'][] = __( 'Could not update your reservations.', 'opentickets-community-edition' );
		}

		return $this->_add_data( apply_filters( 'qsot-seating-ajax-response-update', $resp, $event, $event_area, $ticket_type ), $event, $event_area, $ticket_type );
	}

	// confirm the tickets defined by an order item
	public function confirm_tickets( $item, $item_id, $order, $event, $event_area ) {
		$cuids = array();

		// figure out the list of session ids to use for the lookup
		if ( ( $ocuid = get_post_meta( $order->id, '_customer_user', true ) ) )
			$cuids[] = $ocuid;
		$cuids[] = QSOT::current_user();
		$cuids[] = md5( $order->id . ':' . site_url() );
		$cuids = array_filter( $cuids );

		// get the zoner and stati that are valid
		$zoner = $event_area->area_type->get_zoner();
		$stati = $zoner->get_stati();

		global $wpdb;
		// perform the update
		return $zoner->update( false, array(
			'event_id' => $item['event_id'],
			'quantity' => $item['qty'],
			'state' => array( $stati['r'][0], $stati['c'][0] ),
			'order_id' => array( 0, $order->id ),
			'order_item_id' => array( 0, $item_id ),
			'ticket_type_id' => $item['product_id'],
			'where__extra' => array(
				$wpdb->prepare( 'and ( order_item_id = %d or ( order_item_id = 0 and session_customer_id in(\'' . implode( "','", array_map( 'esc_sql', $cuids ) ) . '\') ) )', $item_id )
			),
		), array(
			'state' => $stati['c'][0],
			'order_id' => $order->id,
			'order_item_id' => $item_id,
			'session_customer_id' => current( $cuids ),
		) );
	}

	// unconfirm the tickets defined by an order item
	public function unconfirm_tickets( $item, $item_id, $order, $event, $event_area ) {
		$cuids = array();

		// figure out the list of session ids to use for the lookup
		if ( ( $ocuid = get_post_meta( $order->id, '_customer_user', true ) ) )
			$cuids[] = $ocuid;
		$cuids[] = QSOT::current_user();
		$cuids[] = md5( $order->id . ':' . site_url() );
		$cuids = array_filter( $cuids );

		// get the zoner and stati that are valid
		$zoner = $event_area->area_type->get_zoner();
		$stati = $zoner->get_stati();

		global $wpdb;
		// perform the update
		return $zoner->update( false, array(
			'event_id' => $item['event_id'],
			'quantity' => $item['qty'],
			'state' => array( $stati['r'][0], $stati['c'][0] ),
			'order_id' => array( 0, $order->id ),
			'order_item_id' => array( 0, $item_id ),
			'ticket_type_id' => $item['product_id'],
			'where__extra' => array(
				$wpdb->prepare( 'and ( order_item_id = %d or ( order_item_id = 0 and session_customer_id in(\'' . implode( "','", array_map( 'esc_sql', $cuids ) ) . '\') ) )', $item_id )
			),
		), array(
			'state' => $stati['r'][0],
			'order_id' => $order->id,
			'order_item_id' => $item_id,
			'session_customer_id' => current( $cuids ),
			'since' => current_time( 'mysql' ),
		) );
	}

	// cancel the tickets defined by an order item
	public function cancel_tickets( $item, $item_id, $order, $event, $event_area ) {
		$cuids = array();

		// figure out the list of session ids to use for the lookup
		if ( ( $ocuid = get_post_meta( $order->id, '_customer_user', true ) ) )
			$cuids[] = $ocuid;
		$cuids[] = QSOT::current_user();
		$cuids[] = md5( $order->id . ':' . site_url() );
		$cuids = array_filter( $cuids );

		// get the zoner and stati that are valid
		$zoner = $event_area->area_type->get_zoner();
		$stati = $zoner->get_stati();

		global $wpdb;
		// perform the update
		return $zoner->remove( false, array(
			'event_id' => $item['event_id'],
			'quantity' => $item['qty'],
			'state' => array( $stati['r'][0], $stati['c'][0] ),
			'order_id' => array( 0, $order->id ),
			'order_item_id' => array( 0, $item_id ),
			'ticket_type_id' => $item['product_id'],
			'where__extra' => array(
				$wpdb->prepare( 'and ( order_item_id = %d or ( order_item_id = 0 and session_customer_id in(\'' . implode( "','", array_map( 'esc_sql', $cuids ) ) . '\') ) )', $item_id )
			),
		) );
	}

	// add the gaea event data to the admin ajax load event response
	public function admin_ajax_load_event( $data, $event, $event_area, $order ) {
		// add the html versions of the start and end date
		$frmt = __( 'D, F jS, Y h:ia', 'opentickets-community-edition' );
		$data['_html_date'] = sprintf( '<span class="from">%s</span> - <span class="to">%s</span>', date_i18n( $frmt, QSOT_Utils::local_timestamp( $event->meta->start ) ), date_i18n( $frmt, QSOT_Utils::local_timestamp( $event->meta->end ) ) );

		// add the capacity
		$data['_capacity'] = intval( isset( $event_area->meta['_capacity'] ) ? $event_area->meta['_capacity'] : 0 );

		// get the available amount of tickets left on the event
		$zoner = $this->get_zoner();
		$data['_available'] = $zoner->get_availability( $event, $event_area );
		$stati = $zoner->get_stati();

		// add the raw event data, in case we want it, and the edit event link
		$event->event_area = $event_area;
		$data['_raw'] = $event;
		$data['_link'] = sprintf( '<a href="%s" target="_blank">%s</a>', get_edit_post_link( $event->ID ), $data['name'] );

		// load all the image sizes for the featured image of the event area
		$data['_imgs'] = array();
		// if the event area has a featured image, load that image's details for use in the ui
		if ( isset( $event_area->meta['_thumbnail_id'] ) ) {
			// get the image data, and store it in the result, so the ui can do with it what it wants
			$img_info = get_post_meta( $event_area->meta['_thumbnail_id'], '_wp_attachment_metadata', true );
			$data['_image_info_raw'] = $img_info;

			// then for each image size, aggregate some information for displaying the image, which is used to create the image tags
			if ( isset( $img_info['file'] ) && is_array( $img_info ) && isset( $img_info['sizes'] ) && is_array( $img_info['sizes'] ) ) {
				$u = wp_upload_dir();
				$base_file = $img_info['file'];
				$file_path = trailingslashit( trailingslashit( $u['baseurl'] ) . str_replace( basename( $base_file ), '', $base_file ) );
				// for each image size, add a record with the image path and size details
				foreach ( $img_info['sizes'] as $k => $info ) {
					$data['_imgs'][$k] = array(
						'url' => $file_path . $info['file'],
						'width' => $info['width'],
						'height' => $info['height'],
					);
				}
				// also add an entry for the fullsize version
				$data['_imgs']['full'] = array(
					'url' => trailingslashit( $u['baseurl'] ) . $base_file,
					'width' => $img_info['width'],
					'height' => $img_info['height'],
				);
			}
		}

		// default number of tickets owned by this order and the default ticket data
		$data['_owns'] = 0;
		$data['_ticket'] = array( 'id' => 0, 'title' => '', 'price' => '' );

		// get the ticket type for this event
		$ticket_type = $this->get_ticket_type( array( 'event_area' => $event_area ) );

		// if we know the ticket type, then try to figure out how many tickets for this event that this order owns currently
		if ( is_object( $ticket_type ) && ! is_wp_error( $ticket_type ) ) {
			// update the ticket information
			$data['_ticket'] = array(
				'id' => $ticket_type->id,
				'title' => $ticket_type->get_title(),
				'price' => $ticket_type->get_price(),
			);

			// determine how many tickets are owned by this order, and update our data accordingly
			$owns = is_object( $zoner ) ? $zoner->find( array( 'fields' => 'total', 'order_id' => $order->id, 'event_id' => $event->ID ) ) : 0;
			$owns_int = is_object( $zoner ) ? $zoner->find( array( 'state' => $stati['i'][0], 'fields' => 'total', 'order_id' => $order->id, 'event_id' => $event->ID ) ) : 0;

			// if the order owns tickets, then update values
			if ( $owns > 0 ) {
				// update the count of the number owned
				$data['_owns'] = $owns;

				// adjust the available tickets so that they account for those already owned
				$data['_available'] = max( 0, $data['_available'] - $owns_int );
			}
		}

		return apply_filters( 'qsot-gaea-admin-ajax-load-event', $data, $event, $event_area );
	}

	// handle the admin ajax request to add a ticket to an order
	public function admin_ajax_add_tickets( $resp, $event ) {
		// if the event does not exist, then bail
		if ( ! is_object( $event ) ) {
			$resp['e'][] = __( 'Could not find the new event.', 'opentickets-community-edition' );
			return $resp;
		}
		do_action( 'qsot-clear-zone-locks', array( 'event_id' => $event->ID ) );
		
		// attempt to load the event_area for that event, and if not loaded, then bail
		$event_area = apply_filters( 'qsot-event-area-for-event', false, $event );
		if ( ! is_object( $event_area ) || ! isset( $event_area->area_type ) || ! is_object( $event_area->area_type ) || ! ( $zoner = $event_area->area_type->get_zoner() ) ) {
			$resp['e'][] = __( 'Could not find the new event\'s event area.', 'opentickets-community-edition' );
			return $resp;
		}
		$stati = $zoner->get_stati();

		// load the order and if it does not exist, bail
		$order = wc_get_order( isset( $_POST['order_id'] ) ? (int) $_POST['order_id'] : false );
		if ( ! is_object( $order ) || is_wp_error( $order ) ) {
			$resp['e'][] = __( 'Could not find that order item on the order.', 'opentickets-community-edition' );
			return $resp;
		}

		// if the quantity is not valid, then bail
		if ( ( $quantity = isset( $_POST['qty'] ) ? (int) $_POST['qty'] : 0 ) <= 0 ) {
			$resp['e'][] = __( 'The quantity must be greater than zero.', 'opentickets-community-edition' );
			return $resp;
		}

		// figure out the ticket product to use
		$product = $this->get_ticket_type( array( 'event_area' => $event_area ) );
		if ( ! is_object( $product ) ) {
			$resp['e'][] = __( 'Could not add those tickets, because the ticket product was invalid.', 'opentickets-community-edition' );
			return $resp;
		} else if ( is_wp_error( $product ) ) {
			$resp['e'] = $product->get_error_messages();
			return $resp;
		}
		$ticket_type_id = $product->id;

		// figure out the appropriate customer id
		$customer_id = 'order:' . $order->id;
		if ( isset( $_POST['customer_user'] ) && ! empty( $_POST['customer_user'] ) )
			$customer_id = $_POST['customer_user'];
		elseif ( ( $order_customer_id = get_post_meta( $order->id, '_customer_user', true ) ) )
			$customer_id = $order_customer_id;

		// actually add the ticket
		$res = $zoner->reserve( false, array(
			'event_id' => $event->ID,
			'order_id' => $order->id,
			'quantity' => $quantity,
			'customer_id' => $customer_id,
			'ticket_type_id' => $ticket_type_id,
		) );

		// if the response was successful, then...
		if ( ! is_wp_error( $res ) && is_scalar( $res ) && $res > 0 ) {
			// update the response status
			$resp['s'] = true;

			// add the item to the order
			$item_id = $this->_add_or_update_order_item( $order, $product, $res, array( 'event_id' => $event->ID ) );

			// update the reservation entry with the order_item_id
			$new_state = $stati['c'][0];
			$zoner->update( false, array(
				'event_id' => $event->ID,
				'order_id' => $order->id,
				'quantity' => $res,
				'customer_id' => $customer_id,
				'ticket_type_id' => $ticket_type_id,
				'state' => $stati['r'][0],
			), array( 'order_item_id' => $item_id, 'state' => $new_state ) );

			// notifiy externals of the change
			$event->event_area = $event_area;
			do_action( 'qsot-order-admin-gaea-added-tickets', $order, $event, $quantity, $customer_id, $item_id );
		}

		// add the data for the event area to the response too, so we can update the area data on the display
		$resp['data'] = array(
			'id' => $event->ID,
			'name' => apply_filters( 'the_title', $event->post_title, $event->ID ),
			'area_type' => $event_area->area_type->get_slug(),
		);
		$resp['data'] = $event_area->area_type->admin_ajax_load_event( $resp['data'], $event, $event_area, $order );

		return $resp;
	}

	// add a new item or update an existing item for this reservation request
	protected function _add_or_update_order_item( $order, $product, $qty, $args ) {
		$found = 0;
		// cycle through the order items and find the first matching order item for this event and product combo
		foreach ( $order->get_items( 'line_item' ) as $oiid => $item ) {
			// if there is no product_id on this item, skip it
			if ( ! isset( $item['product_id'] ) || $item['product_id'] != $product->id )
				continue;

			$matched = true;
			// figure out if all the args match
			foreach ( $args as $k => $v ) {
				if ( ! isset( $item[ $k ] ) || $item[ $k ] != $v ) {
					$matched = false;
					break;
				}
			}

			// if all the fields match, then use this order item
			if ( $matched ) {
				$found = $oiid;
				break;
			}
		}

		$item_id = 0;
		// if the product-event combo was found in an existing order item, then simply update the quantity of that order item
		if ( $found > 0 ) {
			$order->update_product( $found, $product, array( 'qty' => $qty ) );
			$item_id = $found;
		// otherwise add a new order item for this seleciton
		} else {
			$item_id = $order->add_product( $product, $qty );
			foreach ( $args as $k => $v )
				wc_add_order_item_meta( $item_id, '_' . $k, $v );
		}

		return $item_id;
	}

	// handle the admin ajax request to update an existing ticket
	public function admin_ajax_update_ticket( $resp, $event ) {
		// if the event does not exist, then bail
		if ( ! is_object( $event ) ) {
			$resp['e'][] = __( 'Could not find the new event.', 'opentickets-community-edition' );
			return $resp;
		}
		
		// attempt to load the event_area for that event, and if not loaded, then bail
		$event_area = apply_filters( 'qsot-event-area-for-event', false, $event );
		if ( ! is_object( $event_area ) || ! isset( $event_area->area_type ) || ! is_object( $event_area->area_type ) || ! ( $zoner = $event_area->area_type->get_zoner() ) ) {
			$resp['e'][] = __( 'Could not find the new event\'s event area.', 'opentickets-community-edition' );
			return $resp;
		}
		$stati = $zoner->get_stati();

		// load the order and if it does not exist, bail
		$order = wc_get_order( isset( $_POST['order_id'] ) ? (int) $_POST['order_id'] : false );
		if ( ! is_object( $order ) || is_wp_error( $order ) ) {
			$resp['e'][] = __( 'Could not find that order item on the order.', 'opentickets-community-edition' );
			return $resp;
		}

		// get the order items, and if the requested one does not exist, then bail
		$items = $order->get_items();
		if ( ! isset( $_POST['oiid'] ) || ! is_numeric( $_POST['oiid'] ) || ! isset( $items[ (int) $_POST['oiid'] ] ) ) {
			$resp['e'][] = __( 'The order item does not appear to be valid.', 'opentickets-community-edition' );
			return $resp;
		}
		$oiid = (int) $_POST['oiid'];
		$item = $items[ $oiid ];

		// perform the update to the ticket
		$res = $zoner->update( false, array(
			'ticket_type_id' => $item['product_id'],
			'quantity' => $item['qty'],
			'order_id' => $order->id,
			'order_item_id' => $oiid,
			'event_id' => $item['event_id'],
			'state' => array( $stati['r'][0], $stati['c'][0] ),
		), array(
			'event_id' => $event->ID,
		) );

		// construct the response
		$resp['s'] = true;
		$resp['updated'] = array();
		$resp['data'] = $item;
		$resp['data']['__order_item_id'] = $oiid;
		$event->post_title = apply_filters( 'the_title', $event->post_title, $event->ID );
		$event->_edit_url = get_edit_post_link( $event->ID );
		$event->_edit_link = sprintf( '<a rel="edit-event" href="%s" target="_blank" title="edit event">%s</a>', $event->_edit_url, $event->post_title );
		$resp['event'] = $event;

		// if the change was successful, then update the order item meta
		if ( $res ) {
			$resp['updated']['event_id'] = $event->ID;
			wc_update_order_item_meta( $oiid, '_event_id', $event->ID );
		}

		return $resp;
	}

	// when loading the ticket info, we need to add the owns information to the ticket object. mostly used in checkin process
	public function compile_ticket( $ticket ) {
		$oiid = isset( $ticket->order_item_id ) ? $ticket->order_item_id : false;

		// validate that we have all the data we need to do this step
		if ( ! is_object( $ticket ) || ! is_numeric( $oiid ) || ! isset( $ticket->order ) || ! is_object( $ticket->order ) )
			return $ticket;

		// fetch the order item based on the order_item_id and order_id
		$ois = $ticket->order->get_items();
		$oi = isset( $ois[ $oiid ] ) ? $ois[ $oiid ] : null;
		if ( empty( $oi ) )
			return $ticket;

		// update the order item to be what we just found
		$ticket->order_item = $oi;

		// add the owns info
		$query = QSOT_Zoner_Query::instance();
		$owns = $query->find( array(
			'event_id' => $ticket->event->ID,
			'ticket_type_id' => $ticket->order_item['product_id'],
			'order_id' => $ticket->order->id,
			'order_item_id' => $ticket->order_item_id,
		) );

		// construct a totals list, indexed by state
		$ticket->owns = array();
		if ( is_array( $owns ) )
			foreach ( $owns as $entry )
				$ticket->owns[ $entry->state ] = isset( $ticket->owns[ $entry->state ] ) ? $ticket->owns[ $entry->state ] + $entry->quantity : $entry->quantity;

		// overlay the result on top the defaults
		return $ticket;
	}
}

// security
if ( defined( 'ABSPATH' ) && function_exists( 'add_action' ) )
	QSOT_General_Admission_Area_Type::instance();
