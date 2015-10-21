<?php (__FILE__ == $_SERVER['SCRIPT_FILENAME']) ? die(header('Location: /')) : null;

if (!class_exists('qsot_event_area')):

class qsot_event_area {
	// holder for event plugin options
	protected static $o = null;
	// holder for event plugin settings
	protected static $options = null;

	// holder for non-js-version errors
	public static $nojs_submission_errors = array();

	public static function pre_init() {
		// first thing, load all the options, and share them with all other parts of the plugin
		$settings_class_name = apply_filters('qsot-settings-class-name', '');
		if (!class_exists($settings_class_name)) return false;
		self::$o = call_user_func_array(array($settings_class_name, "instance"), array());

		// load all the options, and share them with all other parts of the plugin
		$options_class_name = apply_filters('qsot-options-class-name', '');
		if (!empty($options_class_name)) {
			self::$options = call_user_func_array(array($options_class_name, "instance"), array());
			//self::_setup_admin_options();
		}

		self::$o->event_area = apply_filters('qsot-event-area-options', array(
			'post_type' => 'qsot-event-area',
			'rewrite_slug' => false,
			'mk' => array(
				'cap' => '_capacity',
				'po' => '_pricing_options',
				'img' => '_thumbnail_id',
			),
		));

		$mk = self::$o->meta_key;
		self::$o->meta_key = array_merge(is_array($mk) ? $mk : array(), array(
			'event_area' => '_event_area_id',
			'ea_purchased' => '_purchased_ea',
		));

		// register this post type
		add_filter('qsot-events-core-post-types', array(__CLASS__, 'register_post_type'), 4, 1);
		// fetch list of the available event areas based on the venue
		add_filter('qsot-get-venue-event-areas', array(__CLASS__, 'get_venue_event_areas'), 4, 3);
		// allow advanced filtering, based on 'prices like X event' (primarily used in admin when 'changing' a reservation)
		add_action('pre_get_posts', array(__CLASS__, 'post_priced_like'), 10, 1);
		add_action('pre_get_posts', array(__CLASS__, 'post_has_price'), 10, 1);

		// different methods to actually attain the event area information
		add_filter('qsot-get-event-event-area', array(__CLASS__, 'get_event_ea'), 10, 2);
		add_filter('qsot-get-event-area', array(__CLASS__, 'get_event_area_by_id'), 10, 2);
		// draw the event area in the post content
		add_filter('qsot-event-the-content', array(__CLASS__, 'draw_event_area'), 1000, 2);
		// draw the image, if there is one, of the event area
		add_action('qsot-draw-event-area-image', array(__CLASS__, 'draw_event_area_image'), 100, 3);

		// register all js/css
		add_action('init', array(__CLASS__, 'register_assets'), 10);
		// load frontend js/css
		add_action('qsot-frontend-event-assets', array(__CLASS__, 'load_frontend_assets'), 10);
		add_filter('qsot-event-frontend-templates', array(__CLASS__, 'frontend_templates'), 10, 2);

		// update the purchase counts for a given event
		add_action('qsot-confirmed-ticket', array(__CLASS__, 'update_purchase_count'), 10, 3);
		add_action('qsot-unconfirmed-ticket', array(__CLASS__, 'update_purchase_count'), 10, 3);
		add_action('qsot-ticket-selection-update-ticket-after-meta-update', array(__CLASS__, 'maybe_update_purchases'), 10, 4);
		// obtain the appropriate counts for an event. purchased ticket count or available ticket count
		add_filter('qsot-get-event-purchased-tickets', array(__CLASS__, 'get_purchased_tickets'), 100, 3);
		add_filter('qsot-get-event-available-tickets', array(__CLASS__, 'get_available_tickets'), 100, 3);
		// add important meta to events
		add_filter('qsot-event-meta', array(__CLASS__, 'add_event_meta'), 100, 3);
		// get the ticket type id based on the event id
		add_filter('qsot-ea-non-js-ticket-type-id', array(__CLASS__, 'get_ticket_type_id'), 100, 3);

		// intercept non-js form submissions
		add_filter('template_include', array(__CLASS__, 'intercept_no_js_form_submission'), 10, 1);

		// frontend ajax
		add_action('wp_ajax_qsot-frontend-ticket-selection', array(__CLASS__, 'handle_frontend_ajax'), 10);
		add_action('wp_ajax_nopriv_qsot-frontend-ticket-selection', array(__CLASS__, 'handle_frontend_ajax'), 10);
		add_action('qsot-ticket-selection-frontend-ajax-r', array(__CLASS__, 'faj_reserve'), 10, 2);
		add_action('qsot-ticket-selection-frontend-ajax-d', array(__CLASS__, 'faj_delete'), 10, 2);
		add_action( 'woocommerce_update_cart_action_cart_updated', array( __CLASS__, 'update_reservations_from_cart' ), 0, 1 );

		// allow external access to errors on non-js submissions
		add_filter('qsot-zoner-non-js-error-messages', array(__CLASS__, 'get_no_js_errors'), 10, 1);

		// add event area to ticket information
		add_filter('qsot-compile-ticket-info', array(__CLASS__, 'add_event_area_data'), 2000, 3);

		if (is_admin()) {
			// add the metabox to control this post type
			add_action('add_meta_boxes', array(__CLASS__, 'setup_meta_boxes'), 4);

			// load admin assets for the event edit page
			add_action('qsot-events-edit-page-assets', array(__CLASS__, 'load_event_settings_assets'), 10, 2);
			// laod assets for venue edit page
			add_action('qsot-admin-load-assets-qsot-venue', array(__CLASS__, 'load_admin_assets'), 10, 2);
			// js templates for the admin
			add_filter('qsot-event-area-admin-templates', array(__CLASS__, 'admin_templates'), 10, 2);

			// handle admin ajax stuff
			add_action('wp_ajax_qsot-event-area', array(__CLASS__, 'handle_ajax_admin'), 10);
			// load event area list, admin ajax
			add_action('qsot-event-area-admin-ajax-load', array(__CLASS__, 'aaj_load'), 10, 2);
			// save an event area, admin ajax
			add_action('qsot-event-area-admin-ajax-save-item', array(__CLASS__, 'aaj_save_item'), 10, 2);
			// delete an event area, admin ajax
			add_action('qsot-event-area-admin-ajax-delete-item', array(__CLASS__, 'aaj_delete_item'), 10, 2);

			// external admin ajax handlers
			add_filter('qsot-ticket-selection-admin-ajax-load-event', array(__CLASS__, 'aaj_ts_load_event'), 10, 2);
			add_filter('qsot-ticket-selection-admin-ajax-add-tickets', array(__CLASS__, 'aaj_ts_add_tickets'), 10, 2);

			// sub event bulk edit stuff
			add_action('qsot-events-bulk-edit-settings', array(__CLASS__, 'event_area_bulk_edit_settings'), 30, 2);
			add_filter('qsot-events-save-sub-event-settings', array(__CLASS__, 'save_sub_event_settings'), 10, 3);
			add_filter('qsot-load-child-event-settings', array(__CLASS__, 'load_child_event_settings'), 10, 3);

			// update order item info. synced with ajax in pricing class
			add_filter('qsot-ticket-selection-admin-update-ticket-meta', array(__CLASS__, 'admin_update_ticket'), 10, 5);
		}
	}

	public static function register_assets() {
		wp_register_style('qsot-event-frontend', self::$o->core_url.'assets/css/frontend/event.css', array(), '0.1.0');
		wp_register_script('qsot-event-frontend', self::$o->core_url.'assets/js/features/event-area/ui.js', array('qsot-tools'), '0.1.0');
		wp_register_script('event-area-admin', self::$o->core_url.'assets/js/admin/event-area/ui.js', array('qsot-tools'), '0.1.0-beta', true);
		wp_register_script('qsot-event-event-area-settings', self::$o->core_url.'assets/js/admin/event-area/event-settings.js', array('qsot-event-ui'), self::$o->version);
	}

	public static function load_admin_assets($exists, $post_id) {
		wp_enqueue_script('event-area-admin');
		wp_localize_script('event-area-admin', '_qsot_event_area_settings', array(
			'nonce' => wp_create_nonce('event-areas-for-'.$post_id),
			'venue_id' => $post_id,
			'templates' => apply_filters('qsot-event-area-admin-templates', array(), $post_id),
			'tickets' => apply_filters('qsot-get-all-ticket-products', array()),
			'ajaxurl' => admin_url('admin-ajax.php'),
		));
	}

	public static function load_frontend_assets($post) {
		wp_enqueue_style('qsot-event-frontend');
		if (is_object($post)) {
			$event = apply_filters('qsot-get-event', $post, $post);
			$ticket_id = is_object($event->meta) && is_object($event->meta->_event_area_obj) && is_object($event->meta->_event_area_obj->ticket) ? $event->meta->_event_area_obj->ticket->id : 0;
			wp_enqueue_script('qsot-event-frontend');
			wp_localize_script('qsot-event-frontend', '_qsot_ea_tickets', apply_filters( 'qsot-event-frontend-settings', array(
				'nonce' => wp_create_nonce('frontend-events-ticket-selection-'.$event->ID),
				'edata' => self::_get_frontend_event_data($event),
				'ajaxurl' => admin_url('admin-ajax.php'),
				'templates' => apply_filters('qsot-event-frontend-templates', array(), $event),
				'messages' => array(
					'available' => array(
						'msg' => ( 'yes' == self::$options->{'qsot-show-available-quantity'} )
								? __( 'There are currently <span class="available"></span> <span rel="tt"></span> available.', 'opentickets-community-edition' )
								: str_replace( '<span class="available"></span> ', '', __( 'There are currently <span class="available"></span> <span rel="tt"></span> available.', 'opentickets-community-edition' ) ),
						'type' => 'msg'
					),
					'more-available' => array(
						'msg' => ( 'yes' == self::$options->{'qsot-show-available-quantity'} ) 
								? __( 'There are currently <span class="available"></span> more <span rel="tt"></span> available.', 'opentickets-community-edition' )
								: str_replace( '<span class="available"></span> ', '', __( 'There are currently <span class="available"></span> <span rel="tt"></span> available.', 'opentickets-community-edition' ) ),
						'type' => 'msg'
					),
					'not-available' => array('msg' => __('We\'re sorry. There are currently no tickets available.','opentickets-community-edition'), 'type' => 'error'),
					'sold-out' => array('msg' => __('We are sorry. This event is sold out!','opentickets-community-edition'), 'type' => 'error'),
					'one-moment' => array('msg' => __('<h1>One Moment Please...</h1>','opentickets-community-edition'), 'type' => 'msg'),
				),
				'owns' => $ticket_id ? apply_filters('qsot-zoner-owns-current-user', 0, $event, $ticket_id, self::$o->{'z.states.r'}) : 0,
			), $event));
		}
	}

	public static function load_event_settings_assets($exists, $post_id) {
		wp_enqueue_script('qsot-event-event-area-settings');
	}

	// load the event area information and attach it to the ticket information. used when rendering the ticket
	public static function add_event_area_data( $current, $oiid, $order_id ) {
		// skip this function if the ticket has not already been loaded, or if it is a wp error
		if ( ! is_object( $current ) || is_wp_error( $current ) )
			return $current;

		// also skip this function if the event info has not been loaded, or the event area core object has not been loaded
		if ( ! isset( $current->event, $current->event->meta, $current->event->meta->_event_area_obj ) )
			return $current;

		// move the event area object to top level scope so we dont have to dig for it
		$current->event_area = $current->event->meta->_event_area_obj;
		unset( $current->event->meta->_event_area_obj );

		return $current;
	}

	// construct the data array that holds all the info we send to the frontend UI for selecting tickets
	protected static function _get_frontend_event_data( $event ) {
		// determine the total number of sold or reserved seats, thus far
		$reserved_or_confirmed = apply_filters( 'qsot-event-reserved-or-confirmed-since', 0, $event->ID );

		// figure out how many that leaves for the picking
		$cap = isset( $event->meta->_event_area_obj->meta, $event->meta->_event_area_obj->meta[ self::$o->{'event_area.mk.cap'} ] ) ? $event->meta->_event_area_obj->meta[ self::$o->{'event_area.mk.cap'} ] : 0;
		$left = max( 0, $cap - $reserved_or_confirmed );

		// start putting together the results
		$out = array(
			'id' => $event->ID,
			'name' => apply_filters( 'the_title', $event->post_title ),
			'ticket' => false,
			'link' => get_permalink( $event->ID ),
			'parent_link' => get_permalink( $event->post_parent ),
			'capacity' => $event->meta->capacity,
			'available' => $left,
		);

		// if there is a ticket associated to the event, then include the basic display info about the ticket
		if ( is_object( $event->meta ) && is_object( $event->meta->_event_area_obj ) && is_object( $event->meta->_event_area_obj->ticket ) ) {
			$out['ticket'] = array(
				'name' => $event->meta->_event_area_obj->ticket->get_title(),
				'price' => apply_filters( 'qsot-price-formatted', $event->meta->_event_area_obj->ticket->get_price() ),
			);
		}

		return apply_filters( 'qsot-frontend-event-data', $out, $event );
	}

	public static function frontend_templates($list, $event) {
		$woocommerce = WC();
		$cart_url = '#';
		// get the cart url
		if ( is_object( $woocommerce ) && is_object( $woocommerce->cart ) )
			$cart_url = $woocommerce->cart->get_cart_url();

		$max = 1000000;
		// figure out the proper max value for the number box
		if ( is_object( $event->meta ) ) {
			// if there is only a certain number available, then use that
			if ( isset( $event->meta->available ) && is_numeric( $event->meta->available ) && $event->meta->available > 0 )
				$max = min( $max, $event->meta->available );
			// if we have a purchase limit, figure that into our max
			if ( isset( $event->meta->purchase_limit ) && is_numeric( $event->meta->purchase_limit ) && $event->meta->purchase_limit > 0 )
				$max = min( $max, $event->meta->purchase_limit );
		}

		// figure out the purchase limit for the event
		$limit = apply_filters( 'qsot-event-ticket-purchase-limit', 0, $event->ID );

		$list['ticket-selection'] = '<div class="ticket-form ticket-selection-section">'
				.'<div class="form-inner reserve">'
					.'<div class="title-wrap">'
						.'<h3>'.__('Step 1: How Many?','opentickets-community-edition').'</h3>'
					.'</div>'
					.'<div class="field">'
						.'<label class="section-heading">'.__('Reserve some tickets:','opentickets-community-edition').'</label>'
						.'<div class="availability-message helper"></div>'
						.'<span rel="tt"></span>'
						. ( 1 !== intval( $limit )
								? '<input type="number" step="1" min="0" max="' . $max . '" rel="qty" name="quantity" value="1" class="very-short" />'
								: '<input type="hidden" rel="qty" name="quantity" value="1" /> ' . __( 'x', 'opentickets-community-edition' ) . ' 1'
						)
						.'<input type="button" value="'.__('Reserve','opentickets-community-edition').'" rel="reserve-btn" class="button" />'
					.'</div>'
				.'</div>'
			.'</div>';

		if ( 'yes' == apply_filters( 'qsot-get-option-value', 'no', 'qsot-locked-reservations' ) ) {
			$list['owns'] = '<div class="ticket-form ticket-selection-section">'
					.'<div class="form-inner update">'
						.'<div class="title-wrap">'
							.'<h3>'.__('Step 2: Review','opentickets-community-edition').'</h3>'
						.'</div>'
						.'<div class="field">'
							.'<label class="section-heading">'.__('You currently have:','opentickets-community-edition').'</label>'
							.'<div class="availability-message helper"></div>'
							.'<a href="#" class="remove-link" rel="remove-btn">X</a>'
							.'<span rel="tt"></span>'
							.' ' . __( 'x', 'opentickets-community-edition' ) . ' <span rel="qty"></span>'
						.'</div>'
					.'</div>'
					.'<div class="actions" rel="actions">'
						.'<a href="'.esc_attr($cart_url).'" class="button" rel="cart-btn">'.__('Proceed to Cart','opentickets-community-edition').'</a>'
					.'</div>'
				.'</div>';
		} else {
			$list['owns'] = '<div class="ticket-form ticket-selection-section">'
					.'<div class="form-inner update">'
						.'<div class="title-wrap">'
							.'<h3>'.__('Step 2: Review','opentickets-community-edition').'</h3>'
						.'</div>'
						.'<div class="field">'
							.'<label class="section-heading">'.__('You currently have:','opentickets-community-edition').'</label>'
							.'<div class="availability-message helper"></div>'
							.'<a href="#" class="remove-link" rel="remove-btn">X</a>'
							.'<span rel="tt"></span>'
							. ( 1 !== intval( $limit )
									? '<input type="number" step="1" min="0" max="' . $max . '" rel="qty" name="quantity" value="1" class="very-short" />'
										. '<input type="button" value="' . __( 'Update', 'opentickets-community-edition' ) . '" rel="update-btn" class="button" />'
									: '<input type="hidden" rel="qty" name="quantity" value="1" /> ' . __( 'x', 'opentickets-community-edition' ) . ' 1'
							)
						.'</div>'
					.'</div>'
					.'<div class="actions" rel="actions">'
						.'<a href="'.esc_attr($cart_url).'" class="button" rel="cart-btn">'.__('Proceed to Cart','opentickets-community-edition').'</a>'
					.'</div>'
				.'</div>';
		}

		$list['msgs'] = '<div class="messages"></div>';
		$list['msg'] = '<div class="message"></div>';
		$list['error'] = '<div class="error"></div>';

		$list['tt'] = '<span class="ticket-description">'
				.'<span class="name" rel="ttname"></span>'
				.'<span class="price" rel="ttprice"></span>'
			.'</span>';

		return $list;
	}

	public static function admin_templates($list, $venue_id) {
		$list['area-ui'] = '<div class="area-ui">'
				.'<div class="actions top">'
					.'<button class="add-btn button" rel="add-btn">'.__('add','opentickets-community-edition').'</button>'
				.'</div>'
				.'<div class="area-list" rel="area-list"></div>'
				.'<div class="actions bottom">'
					.'<button class="add-btn button" rel="add-btn">'.__('add','opentickets-community-edition').'</button>'
				.'</div>'
			.'</div>';
		$list['no-areas'] = '<div class="view-area view none-area" rel="view-area">'
				.'<div class="inside">'
					.'<span class="none">'.__('There are currently no areas configured. Please add one to continue.','opentickets-community-edition').'</span>'
				.'</div>'
			.'</div>';
		$list['view-area'] = '<div class="view-area view" rel="view-area">'
				.'<div class="inside">'
					.'<div class="image-preview" size="thumb" rel="img-wrap"></div>'
					.'<div class="area-info" rel="area-info">'
						. implode( '', array_values( apply_filters( 'qsot-event-area-ui-area-info', array(
							'name' => '<div class="area-name" rel="area-name"></div>',
							'capacity' => '<div class="info" rel="info">'
									.'<span class="ticket-name" rel="ttname"></span> @ '
									.'<span class="ticket-price" rel="ttprice"></span> '
									.'(x<span class="capacity" rel="capacity"></span>)'
								.'</div>',
							'actions' => '<div class="actions" rel="actions">'
									. implode( '<span class="divider"> | </span>', array_values( apply_filters( 'qsot-event-area-ui-actions', array(
										'<a href="#" rel="edit-btn">'.__('edit','opentickets-community-edition').'</a>',
										'<a href="#" rel="del-btn">'.__('delete','opentickets-community-edition').'</a>',
									), $venue_id ) ) )
								.'</div>',
						), $venue_id ) ) )
					.'</div>'
				.'</div>'
				.'<div class="clear"></div>'
			.'</div>';
		$list['edit-area'] = '<div class="edit-area edit" rel="edit-area">'
				.'<div class="errors" rel="error-list"></div>'
				.'<input type="hidden" name="area-id[{{id}}]" rel="area-id" value="{{id}}"/>'
				. implode( '', array_values( apply_filters( 'qsot-event-area-ui-parts', array(
					'image-selector' => '<div class="edit-field image-select-wrap" rel="field">'
							.'<label for="img-id[{{id}}]"><strong>'.__('Event Area Image','opentickets-community-edition').'</strong></label>'
							.'<div>'
								.'<div class="image-preview" size="full" rel="img-wrap"></div>'
								.'<input type="hidden" name="img-id[{{id}}]" value="0" rel="img-id" />'
								.'<div class="clear"></div>'
							.'</div>'
							.'<button class="button" rel="change-img">'.__('Select Image','opentickets-community-edition').'</button>'
							. '<a href="#remove-img" rel="remove-img" class="remove-img-btn" scope="[rel=\'field\']" preview="[rel=\'img-wrap\']">remove</a>'
						.'</div>',
					'area-name' => '<div class="edit-field area-name-wrap" rel="field">'
							.'<label for="area-name[{{id}}]"><strong>'.__('Area Name','opentickets-community-edition').'</strong></label>'
							.'<input autocomplete="off" type="text" class="widefat area-name" rel="area-name" name="area-name[{{id}}]" value="" />'
						.'</div>',
					'capacity' => '<div class="edit-field area-name-wrap" rel="field">'
							.'<label for="capacity[{{id}}]"><strong>'.__('Capacity','opentickets-community-edition').'</strong></label>'
							.'<input autocomplete="off" type="number" min="0" max="100000" step="1" class="widefat capacity" rel="capacity" name="capacity[{{id}}]" value="" />'
						.'</div>',
					'pricing' => '<div class="edit-field area-ticket-type" rel="field">'
							.'<label for="area-ticket-type"><strong>'.__('Available Pricing','opentickets-community-edition').'</strong></label>'
							.'<select class="widefat price-list" rel="ttid" name="price-option-tt-id[{{id}}]"></select>'
						.'</div>',
				), $venue_id ) ) )
				.'<div class="actions" rel="actions">'
					.'<button class="button-primary save-btn" rel="save-btn">'.__('save','opentickets-community-edition').'</button>'
					.'<button class="button cancel-btn" rel="cancel-btn">'.__('cancel','opentickets-community-edition').'</button>'
				.'</div>'
			.'</div>';
		$list['price-option'] = '<div class="price-option-wrap" rel="price-option">'
				.'<table class="price-option-table"><tbody><tr>'
					.'<td class="price-option-name-wrap" width="75%">'
						.'<input type="text" class="widefat price-option-name" name="price-option-name[{{id}}][]" rel="name" value="no-name" />'
					.'</td>'
					.'<td class="price-option-name-wrap" width="25%">'
						.'<select class="widefat" name="price-option-tt-id[{{id}}][]" rel="ttid">'
							.'<option value="0">'.__('None','opentickets-community-edition').'</option>'
						.'</select>'
					.'</td>'
				.'</tr></tbody></table>'
			.'</div>';

		return $list;
	}

	public static function handle_frontend_ajax() {
		$post = wp_parse_args($_POST, array('sa' => '', 'event_id' => 0, 'nonce' => ''));
		$post['event_id'] = (int)$post['event_id'];
		$resp = array();
		if ($post['event_id'] > 0 && wp_verify_nonce($post['nonce'], 'frontend-events-ticket-selection-'.$post['event_id'])) {
			$event = get_post( $post['event_id'] );
			if ( post_password_required( $event ) ) {
				$resp['s'] = false;
				$resp['e'] = array( __('This event is password protected.','opentickets-community-edition') );
			} else if ( ! empty( $post['sa'] ) ) {
				$resp = apply_filters('qsot-ticket-selection-frontend-ajax-'.$post['sa'], $resp, $post);
			}
		} else {
			$resp['s'] = false;
			$resp['e'] = array( __('Invalid request. Please refresh the page and try again.','opentickets-community-edition') );
		}
		do_action( 'qsot-sync-cart' );
		header('Content-Type: text/json');
		echo @json_encode($resp);
		exit;
	}

	// process the ajax request from the frontend, that is used to reserve tickets
	public static function faj_reserve( $resp, $data ) {
		// start with an empty response
		$resp['s'] = false;
		$resp['e'] = array();

		// load the event
		$event = apply_filters( 'qsot-get-event', false, $data['event_id'] );

		// detemrine the quantity being requested
		$qty = $data['quantity'];

		// if they are qctually requesting a quantity, the event exists, and the event data about the event_area we need is present, then
		if ( $qty > 0 && is_object( $event ) && is_object( $event->meta ) && is_object( $event->meta->_event_area_obj ) && is_object( $event->meta->_event_area_obj->ticket ) ) {
			// process the reservation
			$res = apply_filters( 'qsot-zoner-reserve-current-user', false, $event, $event->meta->_event_area_obj->ticket->id, $qty );

			// if the reservation was a success, then
			if ( $res && ! is_wp_error( $res ) ) {
				// construct an affirmative response, with the remainder data if applicable
				$resp['s'] = true;
				$resp['m'] = array( __( 'Updated your reservations successfully.', 'opentickets-community-edition' ) );

				// force the cart to send the cookie, because sometimes it doesnt. stupid bug
				WC()->cart->maybe_set_cart_cookies();
			// if there were reported errors, then report them as is
			} else if ( is_wp_error( $res ) ) {
				$resp['e'] = array_merge( $resp['e'], $res->get_error_messages() );
			// if it failed but no error was reported, just give a generic message
			} else {
				$resp['e'][] = __( 'Could not update your reservations.', 'opentickets-community-edition' );
			}
		// otherwise try to report an error that will be helpful in some way to figuring out the problem
		} else {
			if ( $qty <= 0 )
				$resp['e'][] = __( 'The quantity must be greater than zero.', 'opentickets-community-edition' );
			if ( ! is_object( $event ) )
				$resp['e'][] = __( 'Could not load that event.', 'opentickets-community-edition' );
			if ( ! is_object( $event->meta ) )
				$resp['e'][] = __( 'A problem occurred when loading that event.', 'opentickets-community-edition' );
			if ( ! is_object( $event->meta->_event_area_obj ) )
				$resp['e'][] = __( 'That event does not currently have any tickets.', 'opentickets-community-edition' );
			if ( ! is_object( $event->meta->_event_area_obj->ticket ) )
				$resp['e'][] = __( 'The event does not have any tickets.', 'opentickets-community-edition' );
		}

		// determine the total number of sold or reserved seats, thus far
		$reserved_or_confirmed = apply_filters( 'qsot-event-reserved-or-confirmed-since', 0, $event->ID );

		// figure out how many that leaves for the picking
		$cap = isset( $event->meta->_event_area_obj->meta, $event->meta->_event_area_obj->meta[ self::$o->{'event_area.mk.cap'} ] ) ? $event->meta->_event_area_obj->meta[ self::$o->{'event_area.mk.cap'} ] : 0;
		$left = max( 0, $cap - $reserved_or_confirmed );

		// add the extra data used to update the ui
		$resp['data'] = array(
			'owns' => apply_filters( 'qsot-zoner-owns-current-user', 0, $event, $event->meta->_event_area_obj->ticket->id, self::$o->{'z.states.r'} ),
			'available' => 0,
			'available_more' => 0,
		);

		// only show the remaining availability if we are allowed by settings
		if ( 'yes' == apply_filters( 'qsot-get-option-value', 'yes', 'qsot-show-available-quantity' ) ) {
			$resp['data']['available'] = $left;
			$resp['data']['available_more'] = $left;
		}

		return $resp;
	}

	public static function faj_delete($resp, $data) {
		$resp['s'] = false;
		$resp['e'] = array();
		$data = wp_parse_args($data, array('quantity' => 0));

		$event = apply_filters('qsot-get-event', false, $data['event_id']);
		if (is_object($event) && is_object($event->meta) && is_object($event->meta->_event_area_obj) && is_object($event->meta->_event_area_obj->ticket)) {
			$customer_id = apply_filters('qsot-zoner-current-user', md5(isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : time()));

			// get the available occupancy of the event
			$available = apply_filters('qsot-get-event-available-tickets', 0, $event);
			// determine how many this person already has reserved
			$owns = apply_filters( 'qsot-zoner-owns', 0, $event, 0, self::$o->{'z.states.r'}, $customer_id );
			$owns_all = array_sum( array_values( $owns ) );

			$quantity = $data['quantity'];

			if ( $quantity <= $available ) {
				$where = array(
					'customer_id' => $customer_id,
					'event_id' => $event->ID,
					'ticket_type_id' => $event->meta->_event_area_obj->ticket->id,
					'state' => self::$o->{'z.states.r'},
				);
				if ($data['quantity'] <= 0) {
					$set = array(
						'qty' => 0,
						'_delete' => true,
					);
				} else {
					$set = array(
						'qty' => $data['quantity'],
					);
				}
				$res = apply_filters('qsot-zoner-update-reservation', false, $where, $set);
				$owns = apply_filters('qsot-zoner-owns-current-user', 0, $event, $event->meta->_event_area_obj->ticket->id, self::$o->{'z.states.r'});

				if ($data['quantity'] <= 0) {
					if (!$owns && $res) {
						$resp['s'] = true;
						$resp['m'] = array( __('Updated your reservations successfully.','opentickets-community-edition') );
					} else {
						if ($owns) $resp['e'][] = __('A problem occurred when trying to remove your reservations.','opentickets-community-edition');
						else $resp['e'][] = __('Could not update your reservations.','opentickets-community-edition');
					}
				} else {
					if ( is_wp_error( $res ) ) {
						$resp['e'][] = $res->get_error_message();
					} else if (!$res || !$owns) {
						if ($owns) $resp['e'][] = __('A problem occurred when trying to update your reservations.','opentickets-community-edition');
						else $resp['e'][] = __('Could not update your reservations.','opentickets-community-edition');
					} else {
						$resp['s'] = true;
						$resp['m'] = array( __('Updated your reservations successfully.','opentickets-community-edition') );
					}
				}
			} else {
				$show_available_qty = apply_filters( 'qsot-get-option-value', 'yes', 'qsot-show-available-quantity' );

				$resp['e'][] = ( 'yes' == $show_available_qty )
						? sprintf(
							__( 'There are not enough available tickets to increase the quantity of %s to %d. There are only %d available.', 'opentickets-community-edition' ),
							$event->meta->_event_area_obj->ticket->get_title(),
							$quantity,
							$available
						)
						: sprintf(
							__( 'There are not enough available tickets to increase the quantity of %s to %d.', 'opentickets-community-edition' ),
							$event->meta->_event_area_obj->ticket->get_title(),
							$quantity
						);
			}
		} else {
			if (!is_object($event)) $resp['e'][] = __('Could not load that event.','opentickets-community-edition');
			if (!is_object($event->meta)) $resp['e'][] = __('A problem occurred when loading that event.','opentickets-community-edition');
			if (!is_object($event->meta->_event_area_obj)) $resp['e'][] = __('That event does not have currently have any tickets.','opentickets-community-edition');
			if (!is_object($event->meta->_event_area_obj->ticket)) $resp['e'][] = __('The event does not have any tickets.','opentickets-community-edition');
		}

		// determine the total number of sold or reserved seats, thus far
		$reserved_or_confirmed = apply_filters( 'qsot-event-reserved-or-confirmed-since', 0, $event->ID );

		// figure out how many that leaves for the picking
		$cap = isset( $event->meta->_event_area_obj->meta, $event->meta->_event_area_obj->meta[ self::$o->{'event_area.mk.cap'} ] ) ? $event->meta->_event_area_obj->meta[ self::$o->{'event_area.mk.cap'} ] : 0;
		$left = max( 0, $cap - $reserved_or_confirmed );

		// add the extra data used to update the ui
		$resp['data'] = array(
			'owns' => apply_filters( 'qsot-zoner-owns-current-user', 0, $event, $event->meta->_event_area_obj->ticket->id, self::$o->{'z.states.r'} ),
			'available' => 0,
			'available_more' => 0,
		);

		// only show the remaining availability if we are allowed by settings
		if ( 'yes' == apply_filters( 'qsot-get-option-value', 'yes', 'qsot-show-available-quantity' ) ) {
			$resp['data']['available'] = $left;
			$resp['data']['available_more'] = $left;
		}

		return $resp;
	}

	public static function update_reservations_from_cart( $cart_updated ) {
		if ( $cart_updated ) {
			$customer_id = apply_filters('qsot-zoner-current-user', md5(isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : time()));

			foreach ( WC()->cart->get_cart() as $key => $item ) {
				if ( ! apply_filters( 'qsot-item-is-ticket', false, $item ) ) continue;

				$where = array(
					'customer_id' => $customer_id,
					'event_id' => $item['event_id'],
					'ticket_type_id' => $item['product_id'],
					'state' => self::$o->{'z.states.r'},
				);
				if ( $item['quantity'] <= 0 ) {
					$set = array(
						'qty' => 0,
						'_delete' => true,
					);
				} else {
					$set = array(
						'qty' => $item['quantity'],
					);
				}
				$res = apply_filters('qsot-zoner-update-reservation', false, $where, $set);
			}
		}

		return $cart_updated;
	}

	public static function event_area_bulk_edit_settings($post, $mb) {
		$eaargs = array(
			'post_type' => self::$o->{'event_area.post_type'},
			'post_status' => array('publish', 'inherit'),
			'posts_per_page' => -1,
		);
		$areas = get_posts($eaargs);
		?>
			<div class="setting-group">
				<div class="setting" rel="setting-main" tag="event-area">
					<div class="setting-current">
						<span class="setting-name"><?php _e('Event Area:','opentickets-community-edition') ?></span>
						<span class="setting-current-value" rel="setting-display"></span>
						<a class="edit-btn" href="#" rel="setting-edit" scope="[rel=setting]" tar="[rel=form]"><?php _e('Edit','opentickets-community-edition') ?></a>
						<input type="hidden" name="settings[event-area]" value="" scope="[rel=setting-main]" rel="event-area" />
					</div>
					<div class="setting-edit-form" rel="setting-form">
						<select name="event-area">
							<option value="0"><?php _e('-None-','opentickets-community-edition') ?></option>
							<?php foreach ($areas as $area): ?>
								<?php
									$ticket = get_product(get_post_meta($area->ID, self::$o->{'event_area.mk.po'}, true));
									$capacity = get_post_meta($area->ID, self::$o->{'event_area.mk.cap'}, true);
									if ( is_object( $ticket ) ) {
										$disp_cap = $capacity;
										$name = esc_attr( $area->post_title ) . ' / ' . $ticket->get_title() . ' (' . apply_filters( 'qsot-price-formatted', $ticket->get_price() ) . ')';
									} else {
										$disp_cap = 0;
										$name = esc_attr( $area->post_title ) . ' / ' . apply_filters( 'the_title', '(no ticket selected)' ) . ' (' . apply_filters( 'qsot-price-formatted', 0 ) . ')';
									}
									$name = apply_filters( 'qsot-event-settings-event-area-name', $name, $area, $ticket, $capacity );
								?>
								<option value="<?php echo esc_attr($area->ID) ?>" venue-id="<?php echo $area->post_parent ?>" capacity="<?php echo $disp_cap ?>"><?php echo $name; ?></option>
							<?php endforeach; ?>
						</select>
						<div class="edit-setting-actions">
							<input type="button" class="button" rel="setting-save" value="<?php _e('OK','opentickets-community-edition') ?>" />
							<a href="#" rel="setting-cancel"><?php _e('Cancel','opentickets-community-edition') ?></a>
						</div>
					</div>
				</div>
			</div>
		<?php
	}

	// when saving a sub event, we need to make sure to save what event area it belongs to
	public static function save_sub_event_settings( $settings, $parent_id, $parent ) {
		// cache the product price lookup becasue it can get heavy
		static $ea_price = array();

		// if the ea_id was in the submitted data (from the saving of an edit-event screen in the admin), then
		if ( isset( $settings['submitted'], $settings['submitted']->event_area ) ) {
			// add the event_area_id to the meta to save for the individual child event
			$settings['meta'][ self::$o->{'meta_key.event_area'} ] = $settings['submitted']->event_area;

			// also record the price_option product _price, because it will be used by the display options plugin
			if ( isset( $ea_price[ $settings['submitted']->event_area ] ) ) {
				$settings['meta']['_price'] = $ea_price[ $settings['submitted']->event_area ];
			// if that price has not been cached yet, then look it up
			} else {
				$price = 0;
				$product_id = get_post_meta( $settings['submitted']->event_area, self::$o->{'event_area.mk.po'}, true );
				if ( $product_id > 0 )
					$price = get_post_meta( $product_id, '_price', true );
				$ea_price[ $settings['submitted']->event_area ] = $settings['meta']['_price'] = $price;
			}
		}

		return $settings;
	}

	public static function load_child_event_settings($settings, $defs, $event) {
		if (is_object($event) && isset($event->ID)) {
			$ea_id = get_post_meta($event->ID, self::$o->{'meta_key.event_area'}, true);
			$settings['event-area'] = (int)$ea_id;
			if ($ea_id) $settings['capacity'] = get_post_meta($ea_id, self::$o->{'event_area.mk.cap'}, true);
		}

		return $settings;
	}

	public static function handle_ajax_admin() {
		$post = wp_parse_args($_POST, array('sa' => '', 'venue_id' => 0, 'check_venue_id' => 0, 'nonce' => ''));
		$resp = array();
		if (wp_verify_nonce($post['nonce'], 'event-areas-for-'.$post['check_venue_id'])) {
			if (!empty($post['sa'])) $resp = apply_filters('qsot-event-area-admin-ajax-'.$post['sa'], $resp, $post);
		} else {
			$resp['s'] = false;
			$resp['e'] = array( __('Invalid request. Please refresh the page and try again.','opentickets-community-edition') );
		}
		header('Content-Type: text/json');
		echo @json_encode($resp);
		exit;
	}

	public static function aaj_load($resp, $data) {
		$resp['s'] = false;
		$resp['e'] = array();

		$vid = isset($data['venue_id']) ? $data['venue_id'] : 0;
		if (empty($vid)) {
			$resp['e'][] = sprintf( __('Could not find the venue you specified [%s].','opentickets-community-edition'), $vid);
			return $resp;
		}

		$resp['list'] = array_values(apply_filters('qsot-get-venue-event-areas', array(), $vid));

		return $resp;
	}

	public static function aaj_delete_item($resp, $data) {
		$resp['s'] = false;
		$resp['e'] = array();

		$ea_ids = isset($data['area-id']) ? $data['area-id'] : array();
		if (count($ea_ids)) {
			foreach ($ea_ids as $ea_id) {
				wp_delete_post($ea_id);
				$resp['s'] = true;
			}
		}

		return $resp;
	}

	public static function aaj_save_item($resp, $data) {
		$resp['s'] = false;
		$resp['e'] = array();

		$venue_id = isset($data['venue_id']) ? $data['venue_id'] : 0;
		if (!$venue_id) {
			$resp['e'][] = __('Could not find that venue.','opentickets-community-edition');
			return $resp;
		}

		$items = array();

		// organize the submitted data into a format that we can understand for updates
		foreach ( $data['area-id'] as $id => $ids ) {
			if ( isset( $data['area-name'], $data['area-name'][ $id ] ) && ! empty( $data['area-name'][ $id ] ) ) {
				$items[ $id ] = array(
					'id' => $id,
					'image_id' => isset( $data['img-id'], $data['img-id'][ $id ] ) ? $data['img-id'][ $id ] : 0,
					'name' => $data['area-name'][ $id ],
					'capacity' => isset( $data['capacity'], $data['capacity'][ $id ] ) ? $data['capacity'][ $id ] : 0,
					'ttid' => isset( $data['price-option-tt-id'], $data['price-option-tt-id'][ $id ] ) ? $data['price-option-tt-id'][ $id ] : 0,
				);
			}
		}

		// if the data could not be formatted, then bail
		if ( empty( $items ) ) {
			$resp['e'][] = __( 'Could not save the item(s) because not enough information was provided.', 'opentickets-community-edition' );
			return $resp;
		}

		$resp['items'] = array();

		foreach ($items as $item) {
			$args = array(
				'post_parent' => $venue_id,
				'post_title' => $item['name'],
				'post_type' => self::$o->{'event_area.post_type'},
				'post_status' => 'inherit',
			);
			if ($item['id'] > 0) $args['ID'] = $item['id'];

			$old_id = $item['id'];
			$id = wp_insert_post($args);

			if ($id) {
				$resp['s'] = true;
				update_post_meta($id, self::$o->{'event_area.mk.cap'}, $item['capacity']);
				update_post_meta($id, self::$o->{'event_area.mk.img'}, $item['image_id']);
				update_post_meta($id, self::$o->{'event_area.mk.po'}, $item['ttid']);
				do_action( 'qsot-save-event-area', $old_id, $id, $item );
				$resp['items'][$old_id] = apply_filters('qsot-get-venue-event-areas', array(), $venue_id, $id);
			} else {
				$resp['e'][] = sprintf( __( 'There was a problem saving the area [%s].', 'opentickets-community-edition' ), isset( $item['area-name'] ) ? $item['area-name'] : '' );
			}
		}

		return $resp;
	}

	// load the event details for the admin ticket selection interface
	public static function aaj_ts_load_event( $resp, $data ) {
		$resp['s'] = false;
		$resp['e'] = array();

		// fetch the relevant ids from the data
		$event_id = $data['eid'];
		$oiid = $data['oiid'];
		$oid = $data['order_id'];

		// load the event and order information
		$event = apply_filters( 'qsot-get-event', false, $event_id );
		$order = new WC_Order( $oid );

		// if both event and order exist, then
		if ( is_object( $event ) && is_object( $order ) ) {
			// determine an appropriate 'customer id' to use when editing the ticket reservations
			$customer_id = $data['customer_user'];
			if ( empty( $customer_id ) )
				$customer_id = get_post_meta( $order->id, '_customer_id', true );
			if ( empty( $customer_id ) )
				$customer_id = md5( $order->id );

			$resp['s'] = true;
			// aggregate the basic event data
			$resp['data'] = array(
				'id' => $event->ID,
				'name' => apply_filters( 'the_title', $event->post_title ),
			);
			$resp['data']['_html_date'] = sprintf(// display dates
				'<span class="from">%s</span> - <span class="to">%s</span>',
				date_i18n( 'D, F jS, Y h:ia', strtotime( $event->meta->start ) ),
				date_i18n( 'D, F jS, Y h:ia', strtotime( $event->meta->end ) )
			);
			$resp['data']['_capacity'] = $event->meta->capacity;
			$resp['data']['_available'] = $event->meta->available;
			$resp['data']['_imgs'] = array();
			$resp['data']['_raw'] = $event;
			// create an edit link for the event that opens in a new tab
			$resp['data']['_link'] = sprintf( '<a href="%s" target="_blank">%s</a>', get_edit_post_link( $event->ID ), $resp['data']['name'] );

			// if the event area has a featured image, load that image's details for use in the ui
			if ( is_object( $event->meta->_event_area_obj ) && isset( $event->meta->_event_area_obj->meta, $event->meta->_event_area_obj->meta['_thumbnail_id'] ) ) {
				// get the image data, and store it in the result, so the ui can do with it what it wants
				$img_info = get_post_meta( $event->meta->_event_area_obj->meta['_thumbnail_id'], '_wp_attachment_metadata', true );
				$resp['data']['_image_info_raw'] = $img_info;

				// then for each image size, aggregate some information for displaying the image, which is used to create the image tags
				if ( isset( $img_info['file'] ) && is_array( $img_info ) && isset( $img_info['sizes'] ) && is_array( $img_info['sizes'] ) ) {
					$u = wp_upload_dir();
					$base_file = $img_info['file'];
					$file_path = trailingslashit( trailingslashit( $u['baseurl'] ) . str_replace( basename( $base_file ), '', $base_file ) );
					// for each image size, add a record with the image path and size details
					foreach ( $img_info['sizes'] as $k => $info ) {
						$resp['data']['_imgs'][$k] = array(
							'url' => $file_path . $info['file'],
							'width' => $info['width'],
							'height' => $info['height'],
						);
					}
					// also add an entry for the fullsize version
					$resp['data']['_imgs']['full'] = array(
						'url' => trailingslashit( $u['baseurl'] ) . $base_file,
						'width' => $img_info['width'],
						'height' => $img_info['height'],
					);
				}
			}

			// fetch the information about currently owned tickets
			$resp['data']['_owns'] = 0;
			if ( isset( $event->meta, $event->meta->_event_area_obj, $event->meta->_event_area_obj->ticket ) && $event->meta->_event_area_obj->ticket->id > 0 ) {
				$owns = apply_filters( 'qsot-zoner-owns', 0, $event, $event->meta->_event_area_obj->ticket->id, false, false, $order->id );
				if ( is_array( $owns ) )
					foreach ( $owns as $state => $cnt )
						if ( is_numeric( $cnt ) )
							$resp['data']['_owns'] += $cnt;
			}
		}

		return $resp;
	}

	public static function aaj_ts_add_tickets($resp, $data) {
		$resp['s'] = false;
		$resp['e'] = array();

		$oid = $data['order_id'];
		$eid = $data['eid'];
		$qty = $data['qty'];
		$event = apply_filters('qsot-get-event', false, $eid);
		$order = new WC_Order($oid);

		if ($qty > 0 && is_object($event) && is_object($order)) {
			$customer_id = $data['customer_user'];
			if (empty($customer_id)) $customer_id = get_post_meta($order->id, '_customer_id', true);
			if (empty($customer_id)) $customer_id = md5($order->id);
			$res = apply_filters('qsot-zoner-reserve', false, $event, $event->meta->_event_area_obj->ticket->id, $qty, $customer_id, $oid);
			if ( $res && ! is_wp_error( $res ) ) {
				do_action('qsot-order-admin-added-tickets', $order, $event, $event->meta->_event_area_obj->ticket->id, $qty, $customer_id);
				$resp['s'] = true;
			}
		} else {
			if ($qty <= 0) $resp['e'][] = __('The quantity must be greater than zero.','opentickets-community-edition');
			if (!is_object($event)) $resp['e'][] = __('Could not find that event.','opentickets-community-edition');
			if (!is_object($order)) $resp['e'][] = __('That is not a valid order.','opentickets-community-edition');
		}

		return $resp;
	}

	public static function admin_update_ticket($meta, $oiid, $item, $order, $data) {
		$event_id = $data['eid'];

		$event = apply_filters('qsot-get-event', false, $event_id);
		if (is_object($event) && is_array($item)) {
			$customer_user = get_post_meta($oid, '_customer_user', true);
			$where = array(
				'ticket_type_id' => $item['product_id'],
				'qty' => $item['qty'],
				'order_id' => $oid,
				'customer_id' => $customer_user,
				'event_id' => $item['event_id'],
				'state' => array(self::$o->{'z.states.c'}, self::$o->{'z.states.o'}),
			);
			$change_to = array(
				'event_id' => $event_id,
			);
			$res = apply_filters('qsot-zoner-update-reservation', false, $where, $change_to);
			if ($res) {
				$meta['event_id'] = $event_id;
			}
		}

		return $meta;
	}

	public static function get_venue_event_areas($list, $venue_id, $area_id=false) {
		$eas_args = array(
			'post_type' => self::$o->{'event_area.post_type'},
			'post_parent' => $venue_id,
			'post_status' => 'any',
			'orderby' => 'date',
			'order' => 'asc',
			'posts_per_page' => -1,
		);
		if ($area_id)
			$eas_args['include'] = is_array($area_id) ? implode(',', array_map('absint', $area_id)) : $area_id;
		$eas = get_posts($eas_args);

		$final = array();
		foreach ($eas as $ea) {
			$meta = get_post_custom($ea->ID);
			foreach ($meta as $k => $v) $meta[$k] = array_shift($v);

			$ea->meta = wp_parse_args($meta, array(
				self::$o->{'event_area.mk.cap'} => 0,
				self::$o->{'event_area.mk.po'} => 0,
				self::$o->{'event_area.mk.img'} => 0,
			));
			//$ea->meta[self::$o->{'event_area.mk.po'}] = is_array($ea->meta[self::$o->{'event_area.mk.po'}]) ? $ea->meta[self::$o->{'event_area.mk.po'}] : array();

			$ea->imgs = array(
				'full' => wp_get_attachment_image_src($ea->meta[self::$o->{'event_area.mk.img'}], 'full'),
				'thumb' => wp_get_attachment_image_src($ea->meta[self::$o->{'event_area.mk.img'}], array(150, 150)),
			);

			if ( has_action( 'get-venue-event-area' ) ) {
				_deprecated_function( 'filter; "get-venue-event-area"', '1.6.5', 'filter; "qsot-get-venue-event-area"' );
				$final[$ea->ID] = apply_filters( 'get-venue-event-area', $ea, $ea );
			}
			$final[$ea->ID] = apply_filters( 'qsot-get-venue-event-area', $ea, $ea );
		}

		return $area_id ? $final[$area_id.''] : $final;
	}

	public static function mb_venue_event_areas() {
		?>
			<style>
				#available-event-areas { border-color:#cccccc; }
				#available-event-areas.closed { border-color:#e5e5e5; }
				#available-event-areas .inside { padding:0; margin:0; }
				#available-event-areas .image-preview { background-color:#cccccc; border:1px solid #888888; float:left; min-width:75px; min-height:75px; }
				#available-event-areas .item { padding:0.5em 0; border-top:1px dotted #888888; }
				#available-event-areas .item:first-child { padding-top:0; border-top:0; }
				#available-event-areas .view .image-preview { width:75px; height:75px; overflow:hidden; }
				#available-event-areas .view .image-preview img { width:75px; height:auto; }
				#available-event-areas .edit .image-preview { margin-bottom:3px; max-width:100%; }
				#available-event-areas .edit .image-preview img { max-width:100%; }
				#available-event-areas .view .area-name,
				#available-event-areas .view .info,
				#available-event-areas .view .actions { margin-left:83px; }
				#available-event-areas .viewing .view { display:block; }
				#available-event-areas .viewing .edit { display:none; }
				#available-event-areas .adding .view,
				#available-event-areas .editing .view { display:none; }
				#available-event-areas .adding .edit,
				#available-event-areas .editing .edit { display:block; }
				#available-event-areas .edit .errors .error { background-color:#ffeeee; margin:0 0 5px; }
				#available-event-areas .edit .edit-field { margin-bottom:5px; }
				#available-event-areas .area-ui > .actions { background-color:#f4f4f4; padding:0.3em 0.5em; }
				#available-event-areas .area-ui > .actions.top { border-bottom:1px solid #cccccc; }
				#available-event-areas .area-ui > .actions.bottom { border-top:1px solid #cccccc; }
				#available-event-areas .area-ui > .area-list { padding:0.5em; }
				#available-event-areas button { margin-right:5px; }
			</style>

			<div class="venue-event-area-admin" rel="event-area-admin">
			</div>
		<?php
	}

	public static function setup_meta_boxes() {
		$screens = array('qsot-venue');
		foreach ($screens as $screen) {
			add_meta_box(
				'available-event-areas',
				__('Event Areas','opentickets-community-edition'),
				array(__CLASS__, 'mb_venue_event_areas'),
				$screen,
				'normal',
				'high'
			);
		}
	}

	public static function add_event_meta($m, $event, $raw_meta) {
		$m['_event_area_obj'] = apply_filters('qsot-get-event-event-area', false, $event->ID);
		$m['capacity'] = $m['purchases'] = $m['available'] = 0;
		$m['availability'] = __('sold-out','opentickets-community-edition');
		if (is_object($m['_event_area_obj'])) {
			$m['capacity'] = $m['_event_area_obj']->meta['purchased'] + $m['_event_area_obj']->meta['available'];
			$m['purchases'] = $m['_event_area_obj']->meta['purchased'];
			$m['available'] = $m['_event_area_obj']->meta['available'];
			switch (true) {
				case $m['available'] >= ($m['capacity'] - self::$o->always_reserve) * 0.65: $m['availability'] = __('high','opentickets-community-edition'); break;
				case $m['available'] >= ($m['capacity'] - self::$o->always_reserve) * 0.30: $m['availability'] = __('medium','opentickets-community-edition'); break;
				case $m['available'] <= self::$o->always_reserve: $m['availability'] = __('sold-out','opentickets-community-edition'); break;
				default: $m['availability'] = __('low','opentickets-community-edition'); break;
			}
		}
		return $m;
	}

	public static function get_event_ea($current, $event_id) {
		$ea_id = get_post_meta($event_id, self::$o->{'meta_key.event_area'}, true);
		if (!empty($ea_id)) {
			$current = apply_filters('qsot-get-event-area', $current, $ea_id);
			if (is_object($current)) {
				$ttid = get_post_meta($ea_id, self::$o->{'event_area.mk.po'}, true);
				$current->meta['purchased'] = apply_filters('qsot-get-event-purchased-tickets', 0, $event_id);
				$current->meta['available'] = apply_filters('qsot-get-event-available-tickets', 0, $event_id);
				$current->is_soldout = ($current->meta['available'] <= 0);
			}
		}

		return $current;
	}

	public static function maybe_update_purchases($oiid, $from, $to, $order) {
		//qsot-confirmed-ticket
		if (isset($to['event_id'], $from['event_id'])) {
			do_action('qsot-unconfirmed-ticket', $order, $from, $oiid);
			$item = wp_parse_args($to, $from);
			do_action('qsot-confirmed-ticket', $order, $item, $oiid);
		}
	}

	public static function update_purchase_count($order, $item, $item_id) {
		if (!isset($item['event_id'])) return;
		$total = apply_filters( 'qsot-count-tickets', 0, array( 'state' => self::$o->{'z.states.c'}, 'event_id' => $item['event_id'] ) );
		update_post_meta( $item['event_id'], self::$o->{'meta_key.ea_purchased'}, $total );
	}

	public static function get_purchased_tickets($current, $event_id) {
		static $cache = array();

		$event_id = is_object($event_id) ? $event_id->ID : $event_id;
		if (!isset($cache[$event_id.''])) {
			$cache[$event_id.''] = get_post_meta($event_id, self::$o->{'meta_key.ea_purchased'}, true);
		}

		return $cache[$event_id.''];
	}

	public static function get_available_tickets($current, $event_id) {
		static $cache = array();

		$event_id = is_object($event_id) ? $event_id->ID : $event_id;
		if (!isset($cache[$event_id.''])) {
			$purchased = (int)apply_filters('qsot-get-event-purchased-tickets', 0, $event_id);
			$cache[$event_id.''] = (int)get_post_meta(
				(int)get_post_meta($event_id, self::$o->{'meta_key.event_area'}, true),
				self::$o->{'event_area.mk.cap'},
				true
			) - $purchased;
		}

		return $cache[$event_id.''];
	}

	public static function get_event_area_by_id($current, $ea_id) {
		$area = !empty($ea_id) ? get_post($ea_id) : false;

		if (is_object($area)) {
			$current = $area;
			$current->meta = array();
			$m = get_post_custom($current->ID);
			foreach (self::$o->{'event_area.mk'} as $k => $v) {
				$current->meta[$v] = isset($m[$v]) ? array_shift($m[$v]) : '';
			}
			$current->ticket = false;
			if (!empty($current->meta[self::$o->{'event_area.mk.po'}])) {
				$current->ticket = wc_get_product($current->meta[self::$o->{'event_area.mk.po'}]);
				if ( is_object( $current->ticket ) ) {
					$current->ticket->_display_title = $current->ticket->get_title();
					$current->ticket->_display_price = apply_filters('qsot-price-formatted', $current->ticket->get_price());
				} else {
					$current->ticket = false;
				}
			}
		}

		return $current;
	}

	public static function draw_event_area_image($event, $area, $reserved) {
		$ea_id = (int)get_post_meta($event->ID, self::$o->{'meta_key.event_area'}, true);
		if ($ea_id <= 0) return;
		$ea = get_post($ea_id);

		$thumb_id = get_post_meta($ea_id, self::$o->{'event_area.mk.img'}, true);
		if ($thumb_id > 0) {
			list($thumb_url, $w, $h, $rs) = wp_get_attachment_image_src($thumb_id, 'full');
			if ($thumb_url) {
				?>
				<div class="event-area-image-wrap">
					<img src="<?php echo esc_attr($thumb_url) ?>" class="event-area-image" alt="Image of the <?php echo esc_attr(apply_filters('the_title', $ea->post_title)) ?>" />
				</div>
				<?php
			}
		}
	}

	// add the ticket selection UI to the output of the individual event pages
	public static function draw_event_area($content, $event) {
		$out = '';

		$reserved = $interests = array();
		// load the event area
		$area = apply_filters( 'qsot-get-event-event-area', false, $event->ID );
		// container for the name of the template to use
		$template_file = 'post-content/event-area-closed.php';

		// figure out if the user has any tickets to this event. this will serve twp purposes.
		// 1) if they have some, they will become available inside the template, in a moment
		// 2) if they have some, then even events that register as 'closed' that they have reservations for, will allow them to edit their reservations
		$has_reserved = apply_filters( 'qsot-zoner-owns-current-user', 0, $event->ID, $area->ticket->post->ID, self::$o->{'z.states.r'} );

		// check to make sure that we can sell tickets to this event. usually this is only false if we are too close to the start of the event.
		// if we can then change the template to the ticket selection UI enabled template, and load the list of reservations
		if ( apply_filters( 'qsot-can-sell-tickets-to-event', false, $event->ID ) || $has_reserved > 0 ) {
			$template_file = 'post-content/event-area.php';
		}

		// if we have the event area, then go ahead and render the appropriate interface
		if ( is_object( $area ) ) {
			$template = apply_filters( 'qsot-locate-template', '', array( $template_file, 'post-content/event-area.php' ), false, false );
			ob_start();
			if ( ! empty( $template ) )
				self::_include_template( $template, apply_filters( 'qsot-draw-event-area-args', array(
					'event' => $event,
					'reserved' => $reserved,
					'area' => $area,
				), $event, $area ) );
			$out = ob_get_contents();
			ob_end_clean();
		}

		// allow modification if needed
		$out = apply_filters( 'qsot-no-js-seat-selection-form', $out, $area, $event, $interests, $reserved );

		// put the UI in the appropriate location, depending on our settings
		if ( self::$options->{'qsot-synopsis-position'} == 'above' )
			return $content . $out;
		else
			return $out . $content;
	}

	// include a template, and make specific $args local vars
	protected static function _include_template( $template, $args ) {
		// remove any 'template' overridding
		unset( $args['template'] );

		// extract args to local vars
		extract( $args );

		include $template;
	}

	public static function get_ticket_type_id($current, $event, $post_data=array()) {
		$ea_id = (int)get_post_meta($event->ID, self::$o->{'meta_key.event_area'}, true);
		if ($ea_id <= 0) return $current;

		$ea = get_post($ea_id);
		if (!is_object($ea) || $ea->post_type != self::$o->{'event_area.post_type'}) return $current;

		$ttid = (int)get_post_meta($ea_id, self::$o->{'event_area.mk.po'}, true);
		return $ttid;
	}

	public static function post_has_price(&$q) {
		if (!isset($q->query_vars['post_type']) || !in_array(self::$o->core_post_type, (array)$q->query_vars['post_type'])) return;
		if (!isset($q->query_vars['has_price'])) return;
		$q->query_vars['has_price'] = array_filter(array_map('absint', (array)$q->query_vars['has_price']));
		if (empty($q->query_vars['has_price'])) return;

		$ea_ids = get_posts(array(
			'post_type' => self::$o->{'event_area.post_type'},
			'post_status' => array('publish', 'inherit'),
			'posts_per_page' => -1,
			'fields' => 'ids',
			'suppress_filters' => false,
			'meta_query' => array(
				array(
					'key' => self::$o->{'event_area.mk.po'},
					'value' => $ttid,
					'compare' => '=',
					'type' => 'UNSIGNED',
				),
			),
		));
		if (empty($ea_ids)) return;

		$args = array(
			'post_type' => self::$o->core_post_type,
			'post_status' => 'publish',
			'posts_per_page' => -1,
			'fields' => 'ids',
			'meta_query' => array(
				array(
					'key' => self::$o->{'meta_key.event_area'},
					'value' => $ea_ids,
					'compare' => 'IN',
					'type' => 'UNSIGNED',
				),
			),
		);
		if (current_user_can('see_hidden_events')) $args['post_status'] = array('hidden', 'publish');

		$eids = get_posts($args);
		$q->query_vars['post__in'] = isset($q->query_vars['post__in']) ? array_merge($q->query_vars['post__in'], $eids) : $eids;
	}

	public static function post_priced_like(&$q) {
		if (!isset($q->query_vars['post_type']) || !in_array(self::$o->core_post_type, (array)$q->query_vars['post_type'])) return;
		if (!isset($q->query_vars['priced_like']) || (int)$q->query_vars['priced_like'] <= 0) return;

		$event = get_post((int)$q->query_vars['priced_like']);
		if (!is_object($event) || $event->post_type != self::$o->core_post_type) return;
		$ttid = apply_filters('qsot-ea-non-js-ticket-type-id', 0, $event);
		if (empty($ttid)) return;

		$ea_ids = get_posts(array(
			'post_type' => self::$o->{'event_area.post_type'},
			'post_status' => array('publish', 'inherit'),
			'posts_per_page' => -1,
			'fields' => 'ids',
			'suppress_filters' => false,
			'meta_query' => array(
				array(
					'key' => self::$o->{'event_area.mk.po'},
					'value' => $ttid,
					'compare' => '=',
					'type' => 'UNSIGNED',
				),
			),
		));
		if (empty($ea_ids)) return;

		$args = array(
			'post_type' => self::$o->core_post_type,
			'post_status' => 'publish',
			'posts_per_page' => -1,
			'fields' => 'ids',
			'meta_query' => array(
				array(
					'key' => self::$o->{'meta_key.event_area'},
					'value' => $ea_ids,
					'compare' => 'IN',
					'type' => 'UNSIGNED',
				),
			),
		);
		if (current_user_can('see_hidden_events')) $args['post_status'] = array('hidden', 'publish');

		$eids = get_posts($args);
		$q->query_vars['post__in'] = isset($q->query_vars['post__in']) ? array_merge($q->query_vars['post__in'], $eids) : $eids;
	}

	public static function get_no_js_errors($current) {
		return count(self::$nojs_submission_errors) ? self::$nojs_submission_errors : array();
	}

	public static function intercept_no_js_form_submission($template) {
		$event = $GLOBALS['post'];
		if (!is_object($event) || $event->post_type != self::$o->core_post_type) return $template;
		do_action('qsot-zoner-clear-locks', $event->ID);

		if (isset($_GET['remove_reservations']) && isset($_GET['submission']) && wp_verify_nonce($_GET['submission'], 'ticket-selection-step-two')) {

			$ticket_type_id = apply_filters('qsot-ea-non-js-ticket-type-id', 0, $event, $_POST);
			apply_filters('qsot-zoner-reserve-current-user', false, $event, $ticket_type_id, 0);

			wp_safe_redirect(add_query_arg(array('rmvd' => 1), remove_query_arg(array('remove_reservations', 'submission'))));
			exit;
		}

		if (!isset($_POST['submission'], $_POST['qsot-step'])) return $template;

		$ticket_type_id = apply_filters('qsot-ea-non-js-ticket-type-id', 0, $event, $_POST);

		$show_available_qty = apply_filters( 'qsot-get-option-value', true, 'qsot-show-available-quantity' );

		switch ($_POST['qsot-step']) {
			case 1:
				if ( post_password_required( $event ) )
					return get_the_password_form( $event );
				if (!wp_verify_nonce($_POST['submission'], 'ticket-selection-step-one')) break;
				$requested_count = $_POST['ticket-count'];
				if ($requested_count > 0) {
					$success = apply_filters('qsot-zoner-reserve-current-user', false, $event, $ticket_type_id, $requested_count);
					if ( ! $success || is_wp_error( $success ) ) {
						$available = apply_filters('qsot-get-event-available-tickets', 0, $event, $ticket_type_id);
						$ticket = get_product($ticket_type_id);
						$ticket_name = sprintf(
							'"<span class="ticket-name">%s</span>" (<span class="ticket-price">%s</span>)',
							is_object($ticket) ? $ticket->get_title() : __('(Unknown Ticket Type)','opentickets-community-edition'),
							is_object($ticket) ? wc_price($ticket->get_price()) : wc_price(0)
						);
						self::$nojs_submission_errors[] = ( 'yes' == $show_available_qty )
								? sprintf(
									__( 'There are only <span class="available">%s</span> %s available currently. Could not temporarily reserve %d %s.', 'opentickets-community-edition' ),
									$available,
									$ticket_name,
									$requested_count,
									$ticket_name
								)
								: sprintf(
									__( 'There are not enough %s tickets available currently. Could not temporarily reserve %d %s.', 'opentickets-community-edition' ),
									$ticket_name,
									$requested_count,
									$ticket_name
								);
					} else {
						wp_safe_redirect( add_query_arg( array() ) );
						exit;
					}
				} else {
					self::$nojs_submission_errors[] = __( 'The number of tickets must be greater than 0.', 'opentickets-community-edition' );
				}
			break;

			case 2:
				if ( post_password_required( $event ) )
					return get_the_password_form( $event );
				if (!wp_verify_nonce($_POST['submission'], 'ticket-selection-step-two')) break;
				$requested_count = $_POST['ticket-count'];
				if ($requested_count > 0) {
					$owns = apply_filters('qsot-zoner-owns-current-user', 0, $event, $ticket_type_id, self::$o->{'z.states.r'});
					$success = apply_filters('qsot-zoner-reserve-current-user', false, $event, $ticket_type_id, $requested_count);
					if ( ! $success | is_wp_error( $success ) ) {
						$available = apply_filters('qsot-get-event-available-tickets', 0, $event, $ticket_type_id);
						$ticket = get_product($ticket_type_id);
						$ticket_name = sprintf(
							'"<span class="ticket-name">%s</span>" (<span class="ticket-price">%s</span>)',
							is_object($ticket) ? $ticket->get_title() : '(Unknown Ticket Type)',
							is_object($ticket) ? wc_price($ticket->get_price()) : wc_price(0)
						);
						self::$nojs_submission_errors[] = ( 'yes' == $show_available_qty )
								? sprintf(
									__('There are only <span class="available">%s</span> more %s available currently. Could not temporarily reserve %d more %s. You still have %d %s.','opentickets-community-edition'),
									$available - $owns,
									$ticket_name,
									$requested_count,
									$ticket_name,
									$owns,
									$ticket_name
								)
								: sprintf(
									__('There are not enough %s tickets available currently. Could not temporarily reserve %d more %s. You still have %d %s.','opentickets-community-edition'),
									$ticket_name,
									$requested_count,
									$ticket_name,
									$owns,
									$ticket_name
								);
					} else {
						wp_safe_redirect(add_query_arg(array()));
						exit;
					}
				} else {
					self::$nojs_submission_errors[] = __('The number of tickets must be greater than 0.','opentickets-community-edition');
				}
			break;
		}

		return $template;
	}

	public static function register_post_type($list) {
		$list[self::$o->{'event_area.post_type'}] = array(
			'label_replacements' => array(
				'plural' => __('Event Areas','opentickets-community-edition'), // plural version of the proper name
				'singular' => __('Area','opentickets-community-edition'), // singular version of the proper name
			),
			'args' => array( // almost all of these are passed through to the core regsiter_post_type function, and follow the same guidelines defined on wordpress.org
				'public' => false,
				'menu_position' => 21.35,
				'supports' => array(),
				//'hierarchical' => true,
				'rewrite' => array('slug' => self::$o->{'event_area.rewrite_slug'}),
				//'register_meta_box_cb' => array(__CLASS__, 'setup_meta_boxes'),
				//'capability_type' => 'event',
				'show_ui' => false,
				'permalink_epmask' => EP_PAGES,
			),
		);

		return $list;
	}
}

if (defined('ABSPATH') && function_exists('add_action')) {
	qsot_event_area::pre_init();
}

endif;
