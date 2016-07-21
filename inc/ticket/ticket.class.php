<?php if ( __FILE__ == $_SERVER['SCRIPT_FILENAME'] ) die( header( 'Location: /') );

/* Handles the assingment, display and templating of printable tickets.
 */

if (!class_exists('QSOT_tickets')):

class QSOT_tickets {
	// holder for event plugin options
	protected static $o = null;
	protected static $options = null;

	// container for templates caches
	protected static $templates = array();
	protected static $stylesheets = array();

	// containers for current settings when protecting pdf output from errors
	protected static $ERROR_REPORTING = null;
	protected static $DISPLAY_ERRORS = null;

	// order tracking
	protected static $order_id = 0;

	public static function pre_init() {
		// load the plugin settings
		$settings_class_name = apply_filters('qsot-settings-class-name', '');
		if (empty($settings_class_name) || !class_exists($settings_class_name)) return;
		self::$o = call_user_func_array(array($settings_class_name, 'instance'), array());

		// load all the options, and share them with all other parts of the plugin
		$options_class_name = apply_filters('qsot-options-class-name', '');
		if (!empty($options_class_name)) {
			self::$options = call_user_func_array(array($options_class_name, "instance"), array());
			self::_setup_admin_options();
		}

		// setup the db tables for the ticket code lookup
		// we offload this to a different table so that we can index the ticket codes for lookup speed
		self::setup_table_names();
		add_action( 'switch_blog', array( __CLASS__, 'setup_table_names' ), PHP_INT_MAX, 2 );
		add_filter('qsot-upgrader-table-descriptions', array(__CLASS__, 'setup_tables'), 10);

		// ticket codes
		add_filter('qsot-generate-ticket-code', array(__CLASS__, 'generate_ticket_code'), 10, 2);
		add_filter('qsot-decode-ticket-code', array(__CLASS__, 'decode_ticket_code'), 10, 2);

		// cart actions
		add_action('woocommerce_resume_order', array(__CLASS__, 'sniff_order_id'), 1000, 1);
		add_action('woocommerce_new_order', array(__CLASS__, 'sniff_order_id'), 1000, 1);
		add_action('qsot-ajax-before-add-order-item', array(__CLASS__, 'sniff_order_id'), 1000, 1);
		add_action('woocommerce_add_order_item_meta', array(__CLASS__, 'add_ticket_code_for_order_item'), 1000, 3);
		add_action('woocommerce_ajax_add_order_item_meta', array(__CLASS__, 'add_ticket_code_for_order_item'), 1000, 2);
		add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'on_complete_update_tickets' ), 9, 1 );

		// order item display
		add_action('qsot-ticket-item-meta', array(__CLASS__, 'order_item_ticket_link'), 1000, 3);

		// ticket links
		add_filter( 'qsot-get-ticket-link', array( __CLASS__, 'get_ticket_link' ), 1000, 2 );
		add_filter( 'qsot-get-ticket-link-from-code', array( __CLASS__, 'get_ticket_link_from_code' ), 1000, 2 );
		add_filter( 'qsot-get-order-tickets-link', array( __CLASS__, 'get_order_tickets_link' ), 1000, 2 );
		add_action( 'woocommerce_admin_order_data_after_order_details', array( __CLASS__, 'maybe_order_tickets_link' ), 10, 1 );
		add_action( 'woocommerce_email_before_order_table', array( __CLASS__, 'maybe_order_tickets_link_email' ), 10, 3 );
		add_action( 'woocommerce_email_after_order_table', array( __CLASS__, 'maybe_order_tickets_link_email' ), 10, 3 );

		// display ticket
		//add_action('qsot-ticket-intercepted', array(__CLASS__, 'display_ticket'), 1000, 1);
		add_action('qsot-rewriter-intercepted-qsot-ticket-id', array(__CLASS__, 'display_ticket'), 1000, 1);
		add_action('qsot-rewriter-intercepted-qsot-order-ticket-id', array(__CLASS__, 'display_order_ticket'), 1000, 1);
		add_filter('qsot-compile-ticket-info', array(__CLASS__, 'compile_ticket_info'), 1000, 3);
		add_filter('qsot-compile-ticket-info', array(__CLASS__, 'compile_ticket_info_images'), PHP_INT_MAX, 3);
		// one-click-email link auth
		add_filter('qsot-email-link-auth', array(__CLASS__, 'email_link_auth'), 1000, 2);
		add_filter('qsot-verify-email-link-auth', array(__CLASS__, 'validate_email_link_auth'), 1000, 3);
		// guest checkout verification
		add_filter('qsot-ticket-verification-form-check', array(__CLASS__, 'validate_guest_verification'), 1000, 2);

		// email - add ticket download links
		add_action( 'woocommerce_order_item_meta_start', array( __CLASS__, 'add_view_ticket_link_to_emails' ), 2000, 3 );

		// any special logic that needs to be run when activating our plugin
		add_action( 'qsot-activate', array( __CLASS__, 'on_activate' ), 1000 );

		if (is_admin()) {
			add_action('admin_footer-options-permalink.php', array(__CLASS__, 'debug_rewrite_rules'));
		}

		// add the rewrite rules for the ticket urls
		do_action(
			'qsot-rewriter-add',
			'qsot-ticket',
			array(
				'name' => 'qsot-ticket',
				'query_vars' => array( 'qsot-ticket', 'qsot-ticket-id' ),
				'rules' => array( 'ticket/(.*)?' => 'qsot-ticket=1&qsot-ticket-id=' ),
			)
		);
		do_action(
			'qsot-rewriter-add',
			'qsot-order-tickets',
			array(
				'name' => 'qsot-order-tickets',
				'query_vars' => array( 'qsot-order-tickets', 'qsot-order-ticket-id' ),
				'rules' => array( 'order-tickets/(.*)?' => 'qsot-ticket=1&qsot-order-ticket-id=' ),
			)
		);
	}

	public static function debug_rewrite_rules() {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			?><pre style="font-size:11px; padding-left:160px; color:#000000; background-color:#ffffff;"><?php print_r($GLOBALS['wp_rewrite']->rules) ?></pre><?php
		}
	}

	public static function add_view_ticket_link_to_emails($item_id, $item, $order) {
		$status = is_callable(array(&$order, 'get_status')) ? $order->get_status() : $order->status;
		if (!in_array($status, apply_filters('qsot-ticket-link-allow-by-order-status', array('completed')))) return;

		$auth = apply_filters('qsot-email-link-auth', '', $order->id);
		$link = apply_filters('qsot-get-ticket-link', '', $item_id);
		$link = $link ? add_query_arg(array('n' => $auth), $link) : $link;
		if (empty($link)) return;

		$label = __( 'Ticket', 'opentickets-community-edition' );
		$title = __( 'View your ticket', 'opentickets-community-edition' );
		$display = __( 'View this ticket', 'opentickets-community-edition' );
		if ($item['qty'] > 1) {
			$label = __( 'Tickets', 'opentickets-community-edition' );
			$title = __( 'View your tickets', 'opentickets-community-edition' );
			$display = __( 'View these tickets', 'opentickets-community-edition' );
		}

		echo sprintf(
			'<br/><small><strong>%s:</strong> <a class="ticket-link" href="%s" target="_blank" title="%s">%s</a></small>',
			$label,
			$link,
			$title,
			$display
		);
	}

	public static function order_item_ticket_link($item_id, $item, $product) {
		if (!apply_filters('qsot-item-is-ticket', false, $item)) return;

		$url = apply_filters('qsot-get-ticket-link', '', $item_id);
		if (empty($url)) return;

		$title = __( 'View this ticket', 'opentickets-community-edition' );
		$display = __( 'View ticket', 'opentickets-community-edition' );
		if ($item['qty'] > 1) {
			$title = __( 'View these tickets', 'opentickets-community-edition' );
			$display = __( 'View tickets', 'opentickets-community-edition' );
		}

		?><a target="_blank" href="<?php echo esc_attr($url) ?>" title="<?php echo esc_attr(__($title)) ?>"><?php echo __($display) ?></a><?php
	}

	protected static function _order_item_order_status( $item_id ) {
		global $wpdb;

		$q = $wpdb->prepare( 'select order_id from ' . $wpdb->prefix . 'woocommerce_order_items where order_item_id = %d', $item_id );
		$order_id = (int) $wpdb->get_var($q);
		if ( $order_id <= 0 ) return 'does-not-exist';

		if ( QSOT::is_wc_latest() ) {
			$status = preg_replace( '#^wc-#', '', get_post_status( $order_id ) );
		} else {
			$status = wp_get_object_terms( array( $order_id ), array( 'shop_order_status' ), 'slugs' );
			$status = is_array( $status ) ? ( in_array( 'completed', $status ) ? 'completed' : current( $status ) ) : 'does-no-exist';
		}

		return $status;
	}

	// get the appropriate ticket link, based on the order_item_id
	public static function get_ticket_link( $current, $item_id ) {
		global $wpdb;

		// figure out the order status for this item, and only allow completed orders to pass this check
		$order_status = self::_order_item_order_status( $item_id );
		if ( ! in_array( $order_status, array( 'completed' ) ) ) return '';

		// get the ticket code from this order item
		$q = $wpdb->prepare( 'select ticket_code from ' . $wpdb->qsot_ticket_codes . ' where order_item_id = %d', $item_id );
		$code = $wpdb->get_var( $q );

		// if there is no code, then bail
		if ( empty( $code ) )
			return $current;

		// otherwise, return the appropriate ticket link
		return apply_filters( 'qsot-get-ticket-link-from-code', $current, $code );
	}

	// get the ticket link, based on the ticket code
	public static function get_ticket_link_from_code( $current, $code ) {
		global $wp_rewrite;

		$final = '';
		// if we ARE using a permalink struct, then return a pretty permalink
		if ( ! empty( $wp_rewrite->permalink_structure ) ) {
			$final = site_url( '/ticket/' . $code . '/' );
		// otherwise, return an ugly permalink
		} else {
			$final = add_query_arg( array(
				'qsot-ticket' => 1,
				'qsot-ticket-id' => $code,
			), site_url() );
		}

		return $final;
	}

	// get the order tickets link, based on the order
	public static function get_order_tickets_link( $current, $order ) {
		$order = wc_get_order( $order );
		// if the order does not exist, then vail
		if ( ! is_object( $order ) || is_wp_error( $order ) )
			return $current;

		global $wp_rewrite;

		$final = '';
		// if we ARE using a permalink struct, then return a pretty permalink
		if ( ! empty( $wp_rewrite->permalink_structure ) ) {
			$final = site_url( '/order-tickets/' . $order->order_key . '/' );
		// otherwise, return an ugly permalink
		} else {
			$final = add_query_arg( array(
				'qsot-order-tickets' => 1,
				'qsot-order-ticket-id' => $order->order_key,
			), site_url() );
		}

		return $final;
	}

	// maybe add the order tickets link to the edit order admin page
	public static function maybe_order_tickets_link( $order ) {
		// if the order is not completed, then bail
		if ( 'completed' !== $order->get_status() )
			return;

		$at_least_one = false;
		// if there are no tickets on the order then bail
		foreach ( $order->get_items() as $item_id => $item ) {
			if ( apply_filters( 'qsot-item-is-ticket', false, $item ) ) {
				$at_least_one = true;
				break;
			}
		}
		if ( ! $at_least_one )
			return;

		// draw the link
		echo sprintf(
			'<a class="button-primary all-tickets-button" href="%s" title="%s" target="_blank">%s</a>',
			apply_filters( 'qsot-get-order-tickets-link', '', $order ),
			__( 'View all of the tickets on a single page, for this order', 'opentickets-community-edition' ),
			__( 'View ALL Order Tickets', 'opentickets-community-edition' )
		);
	}

	// maybe add the order tickets link to the edit order admin page
	public static function maybe_order_tickets_link_email( $order, $sent_to_admin=false, $plain_text=false ) {
		// if the order is not completed, then bail
		if ( 'completed' !== $order->get_status() )
			return;

		$at_least_one = false;
		// if there are no tickets on the order then bail
		foreach ( $order->get_items() as $item_id => $item ) {
			if ( apply_filters( 'qsot-item-is-ticket', false, $item ) ) {
				$at_least_one = true;
				break;
			}
		}
		if ( ! $at_least_one )
			return;

		// create the link
		$link = add_query_arg(
			array( 'n' => apply_filters( 'qsot-email-link-auth', '', $order->id ) ),
			apply_filters( 'qsot-get-order-tickets-link', '', $order )
		);

		if ( ! $plain_text )
			// draw the link
			echo sprintf(
				'<a class="button-primary all-tickets-button" href="%s" title="%s" target="_blank">%s</a>',
				$link,
				__( 'View all of the tickets on a single page, for this order', 'opentickets-community-edition' ),
				__( 'View all of your tickets on a single page &raquo;', 'opentickets-community-edition' )
			);
		else
			echo $link;
	}

	public static function sniff_order_id($order_id) {
		self::$order_id = $order_id;
	}

	public static function add_ticket_code_for_order_item( $item_id, $values, $key='', $order_id=0 ) {
		if ( empty( $order_id ) && isset( self::$order_id ) ) $order_id = self::$order_id;
		if ( empty( $order_id ) && isset( WC()->session ) && ( $cur_order_id = WC()->session->order_awaiting_payment ) ) $order_id = $cur_order_id;

		global $wpdb;

		$code_args = array_merge(
			$values,
			array(
				'order_id' => $order_id,
				'order_item_id' => $item_id,
			)
		);
		$code = apply_filters('qsot-generate-ticket-code', '', $code_args);
		
		$q = $wpdb->prepare(
			'insert into '.$wpdb->qsot_ticket_codes.' (order_item_id, ticket_code) values (%d, %s) on duplicate key update ticket_code = values(ticket_code)',
			$item_id,
			$code
		);
		$wpdb->query($q);
	}

	public static function on_complete_update_tickets( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( is_object( $order ) ) {
			foreach ( $order->get_items() as $oiid => $item ) {
				$values = $item;
				unset( $item['item_meta'] );
				self::add_ticket_code_for_order_item( $oiid, $values, '', $order_id );
			}
		}
	}

	public static function generate_ticket_code($current, $args='') {
		$args = wp_parse_args($args, array(
			'event_id' => 0,
			'order_id' => 0,
			'order_item_id' => 0,
		));
		$args = apply_filters('qsot-generate-ticket-code-args', $args);
		if (empty($args['order_id']) || empty($args['order_item_id']) || empty($args['event_id'])) return $current;

		$format = '%s.%s.%s';
		$key = apply_filters('qsot-generate-ticket-code-code', sprintf($format, $args['event_id'], $args['order_id'], $args['order_item_id']), $format, $args);
		$key .= '~'.sha1($key.AUTH_KEY);
		$key = str_pad('', 3 - (strlen($key) % 3), '|').$key;
		$ekey = str_replace(array('/', '+'), array('-', '_'), base64_encode($key));

		return $ekey;
	}

	public static function decode_ticket_code($current, $code) {
    global $wpdb;

    $code = trim( $code );
    // if the ticket code is empty, bail
    if ( empty( $code ) ) 
      return array();

    // lookup the ticket code
    $q = $wpdb->prepare( 'select order_item_id from ' . $wpdb->qsot_ticket_codes . ' where ticket_code = %s', $code );
    $order_item_id = $wpdb->get_var( $q );

    // if there is no order item id, then bail
    if ( empty( $order_item_id ) ) 
      return array();

    // lookup the order_id fro the order_item_id
    $order_id = $wpdb->get_var( $wpdb->prepare( 'select order_id from ' . $wpdb->prefix . 'woocommerce_order_items where order_item_id = %s', $order_item_id ) );

    // if there is no order_id, bail
    if ( empty( $order_id ) ) 
      return array();

    // look up the event id for the order_item
    $event_id = $wpdb->get_var( $wpdb->prepare( 'select meta_value from ' . $wpdb->prefix . 'woocommerce_order_itemmeta where order_item_id = %s and meta_key = %s', $order_item_id, '_event_id' ) );

    // if there is no event_id then bail
    if ( empty( $event_id ) ) 
      return array();

    // otherwise, return the data we collected
    return array( 'event_id' => $event_id, 'order_id' => $order_id, 'order_item_id' => $order_item_id );
	}

	// create a verification code that will allow a non-logged in user to by-pass the need to login to view their tickets, if they click a link in their email
	public static function email_link_auth( $current, $order_id ) {
		// get some basic information about the order itself
		$user_id = get_post_meta( $order_id, '_customer_user', true );
		$email = get_post_meta( $order_id, '_billing_email', true );

		// create the code
		$str = sprintf( '%s.%s.%s.%s.%s', AUTH_KEY, $user_id, $email, $order_id, NONCE_SALT );

		// sign the code
		$str .= '~' . sha1( $str );

		// hash and encode it
		return @strrev( @md5( @strrev( $str ) ) );
	}

	// validate an email bypass-login code
	public static function validate_email_link_auth( $pass, $auth, $order_id ) {
		// fetch what the code should be
		$check = apply_filters( 'qsot-email-link-auth', '', $order_id );

		// determine if the supplied code is valid
		return $check === $auth;
	}

	// fetch the html for the branding images
	protected static function _get_branding_images( $branding_image_ids ) {
		$brand_imgs = array();
		// cycle through the indexes of the branding images, and if an image belongs, then load the html for it
		for ( $i = 0; $i < 5; $i++ ) {
			// store the image id
			$bid = isset( $branding_image_ids[ $i ] ) ? $branding_image_ids[ $i ] : null;

			// default the image html to nothing
			$brand_imgs[ $i ] = '';

			// if there is an image id, or there should be an image here, then
			if ( isset( $bid ) && 'noimg' !== $bid ) {
				// load the branding image
				$brand_imgs[ $i ] = apply_filters( 'qsot-ticket-branding-image', wp_get_attachment_image( $bid, array( 90, 99999 ), false, array( 'class' => 'branding-img' ) ), $bid, $i );

				// default the image to the opentickets image with a link to our site
				$brand_imgs[ $i ] = ! empty( $brand_imgs[ $i ] )
					? $brand_imgs[ $i ]
					: '<a href="' . esc_attr( QSOT::product_url() ) . '" title="' . __( 'Who is OpenTickets?', 'opentickets-community-edition' ) . '">'
							.'<img src="' . esc_attr( QSOT::plugin_url() . 'assets/imgs/opentickets-tiny.jpg' ) . '" class="ot-tiny-logo branding-img" />'
						. '</a>';
			}

			// if there is no image, then add a spacer
			$brand_imgs[ $i ] = empty( $brand_imgs[ $i ] ) ? '<div class="fake-branding-img">&nbsp;</div>' : $brand_imgs[ $i ];
		}

		return apply_filters( 'qsot-ticket-branding-image-html', $brand_imgs, $branding_image_ids );
	}

	// hide errors
	protected static function _hide_errors() {
		self::$ERROR_REPORTING = error_reporting( 0 );
		self::$DISPLAY_ERRORS = ini_get( 'display_errors' );
		ini_set( 'display_errors', 0 );
	}

	// restore errors
	protected static function _restore_errors() {
		if ( null !== self::$ERROR_REPORTING )
			error_reporting( self::$ERROR_REPORTING );
		if ( null !== self::$DISPLAY_ERRORS )
			ini_set( 'display_errors', self::$DISPLAY_ERRORS );

		self::$ERROR_REPORTING = self::$DISPLAY_ERRORS = null;
	}

	// display the order tickets, or an error, depending on if we can load the tickets or not
	public static function display_order_ticket( $order_key ) {
		// get the order from the order_key
		$order_id = wc_get_order_id_by_order_key( $order_key );
		$order = wc_get_order( $order_id );
		if ( ! is_object( $order ) && ! is_wp_error( $order ) ) {
			self::_restore_errors();
			return false;
		}

		// verify that the user can view these tickets
		if ( ! self::_can_user_view_ticket( array( 'order_id' => $order_id ) ) ) {
			self::_no_access( __( 'You do not have permission to view this ticket.', 'opentickets-community-edition' ) );
			exit;
		}

		// do not display the ticket unless the order is complete
		if ( 'completed' != $order->get_status() ) {
			self::_no_access( __( 'The ticket cannot be displayed, because the order is not complete.', 'opentickets-community-edition' ) );
			exit;
		}

		$errors = array();
		$tickets = array();
		// cycle through the order items. find any tickets in the order. foreach ticket, add it to our ticket list
		foreach ( $order->get_items() as $item_id => $item ) {
			// if the item is not a ticket, then bail
			if ( ! apply_filters( 'qsot-item-is-ticket', false, $item ) )
				continue;

			// get the ticket data for display on the output ticket
			$ticket = apply_filters( 'qsot-compile-ticket-info', false, $item_id, $order_id );

			// if the ticket was not loaded, then skip it
			if ( ! is_object( $ticket ) ) {
				$errors[] = __( 'There was a problem loading one of your tickets.', 'opentickets-community-edition' );
				continue;
			}

			if ( is_wp_error( $ticket ) ) {
				$errors[] = __( 'There was a problem loading one of your tickets.', 'opentickets-community-edition' ) . '<br/><em>' . implode( '</br>', $ticket->get_error_messages() ) . '</em>';
				continue;
			}

			$eparts = array();
			// find out if any of the needed data is missing, or if any of it is in a format that is un expected, and generate a list of errors to report
			if ( ! isset( $ticket->order, $ticket->order->id ) )
				$eparts[] = __( 'the order', 'opentickets-community-edition' );
			if ( ! isset( $ticket->product, $ticket->product->id ) )
				$eparts[] = __( 'the ticket product information', 'opentickets-community-edition' );
			if ( ! isset( $ticket->event, $ticket->event->ID ) )
				$eparts[] = __( 'the event information', 'opentickets-community-edition' );
			if ( ! isset( $ticket->venue, $ticket->venue->ID ) )
				$eparts[] = __( 'the venue information', 'opentickets-community-edition' );
			if ( ! isset( $ticket->order_item ) || ! is_array( $ticket->order_item ) )
				$eparts[] = __( 'the order item information', 'opentickets-community-edition' );
			if ( ! isset( $ticket->event_area, $ticket->event_area->ID ) )
				$eparts[] = __( 'the event area information', 'opentickets-community-edition' );

			// if there was any needed data missing, then construct an error about it, and skip this ticket
			if ( ! empty( $eparts ) ) {
				$errors[] = sprintf( __( 'The following ticket data could not be loaded: %s', 'opentickets-community-edition' ), '<br/>' . implode( ',<br/>', $eparts ) );
				continue;
			}

			// at this point we have what we need for this ticket, so add it to the list
			$tickets[] = $ticket;
		}

		// if there were not tickets successfully loaded, then error out
		if ( empty( $tickets ) ) {
			self::_no_access( __( 'None of the tickets for this order could be found.', 'opentickets-community-edition' ) );
			exit;
		}

		// determine the file location for the template and it's stylesheet
		$template = 'tickets/basic-order-tickets.php';
		$stylesheet = apply_filters( 'qsot-locate-template', '', array( 'tickets/basic-style.css' ), false, false );
		// account for messed up Windows paths
		$stylesheet = str_replace( DIRECTORY_SEPARATOR, '/', $stylesheet );
		$abspath = str_replace( DIRECTORY_SEPARATOR, '/', ABSPATH );
		$stylesheet = str_replace( $abspath, '/', $stylesheet );
		$stylesheet = site_url( $stylesheet );

		// figure out the page title for the output
		$page_title = sprintf(
			__( '%s - %s', 'opentickets-community-edition' ),
			sprintf( __( 'Order #%d', 'opentickets-community-edition' ), $order_id ),
			__( 'All Tickets', 'opentickets-community-edition' )
		);

		// get the html for the ticket itself
		$out = self::_get_ticket_html( self::_display_ticket_args( array(
			'tickets' => $tickets,
			'template' => $template,
			'stylesheet' => $stylesheet,
			'page_title' => $page_title,
		) ) );

		$_GET = wp_parse_args( $_GET, array( 'frmt' => 'html' ) );
		// do something different depending on the requested format
		switch ( $_GET['frmt'] ) {
			default: echo apply_filters( 'qsot-display-order-tickets-output-' . $_GET['frmt'] . '-format', $out, $order_key, array(
				'tickets' => $tickets,
				'template' => $template,
				'stylesheet' => $stylesheet,
				'page_title' => $page_title,
			), $order ); break;
		}

		self::_restore_errors();

		exit;
	}

	// display the ticket, or an error, depending on if we can load the ticket or not
	public static function display_ticket( $code ) {
		// parse the args from the ticket code
		$args = apply_filters( 'qsot-decode-ticket-code', array(), $code );

		// make sure we have the basic required data for loading the ticket
		if ( ! is_array( $args ) || ! isset( $args['order_id'], $args['order_item_id'] ) || empty( $args['order_id'] ) || empty( $args['order_item_id'] ) ) {
			self::_restore_errors();
			return false;
		}

		// make sure that the current user can view this ticket
		if ( ! self::_can_user_view_ticket( $args ) ) {
			self::_restore_errors();
			return false;
		}

		// load all the data needed to render the ticket
		$ticket = apply_filters( 'qsot-compile-ticket-info', false, $args['order_item_id'], $args['order_id'] );

		// if ticket was not loaded, then fail
		if ( ! is_object( $ticket ) ) {
			self::_no_access( __( 'There was a problem loading your ticket.', 'opentickets-community-edition' ) );
			exit;
		}

		// if the resulting ticket we a wp_error, print the message from the error, to be more specific about the problem
		if ( is_wp_error( $ticket ) ) {
			$message = __( 'There was a problem loading your ticket.', 'opentickets-community-edition' ) . '<br/><em>' . implode( '</br>', $ticket->get_error_messages() ) . '</em>';
			self::_no_access( $message );
			exit;
		}

		$errors = array();
		// find out if any of the needed data is missing, or if any of it is in a format that is un expected, and generate a list of errors to report
		if ( ! isset( $ticket->order, $ticket->order->id ) )
			$errors[] = __( 'the order', 'opentickets-community-edition' );
		if ( ! isset( $ticket->product, $ticket->product->id ) )
			$errors[] = __( 'the ticket product information', 'opentickets-community-edition' );
		if ( ! isset( $ticket->event, $ticket->event->ID ) )
			$errors[] = __( 'the event information', 'opentickets-community-edition' );
		if ( ! isset( $ticket->venue, $ticket->venue->ID ) )
			$errors[] = __( 'the venue information', 'opentickets-community-edition' );
		if ( ! isset( $ticket->order_item ) || ! is_array( $ticket->order_item ) )
			$errors[] = __( 'the order item information', 'opentickets-community-edition' );
		if ( ! isset( $ticket->event_area, $ticket->event_area->ID ) )
			$errors[] = __( 'the event area information', 'opentickets-community-edition' );

		// if there area any errors from above to report, then display an error message showing those problems
		if ( ! empty( $errors ) ) {
			self::_no_access( sprintf( __( 'The following ticket data could not be loaded: %s', 'opentickets-community-edition' ), '<br/>' . implode( ',<br/>', $errors ) ) );
			exit;
		}

		// do not display the ticket unless the order is complete
		if ( 'completed' != $ticket->order->get_status() ) {
			self::_no_access( __( 'The ticket cannot be displayed, because the order is not complete.', 'opentickets-community-edition' ) );
			exit;
		}

		// determine the file location for the template and it's stylesheet
		$template = 'tickets/basic-ticket.php';
		$stylesheet = apply_filters( 'qsot-locate-template', '', array( 'tickets/basic-style.css' ), false, false );
		// account for messed up Windows paths
		$stylesheet = str_replace( DIRECTORY_SEPARATOR, '/', $stylesheet );
		$abspath = str_replace( DIRECTORY_SEPARATOR, '/', ABSPATH );
		$stylesheet = str_replace( $abspath, '/', $stylesheet );
		$stylesheet = site_url( $stylesheet );

		// figure out the page title for the output
		$page_title = sprintf(
			__( '%s - %s - %s - %s', 'opentickets-community-edition' ),
			__( 'Ticket', 'opentickets-community-edition' ),
			$ticket->event->post_title,
			$ticket->product->get_title(),
			$ticket->product->get_price()
		);

		// get the html for the ticket itself
		$out = self::_get_ticket_html( self::_display_ticket_args( array(
			'ticket' => $ticket,
			'template' => $template,
			'stylesheet' => $stylesheet,
			'page_title' => $page_title,
		) ) );
		//die($out);

		$_GET = wp_parse_args( $_GET, array( 'frmt' => 'html' ) );
		// do something different depending on the requested format
		switch ( $_GET['frmt'] ) {
			default: echo apply_filters( 'qsot-display-ticket-output-' . $_GET['frmt'] . '-format', $out, $code, array(
				'ticket' => $ticket,
				'template' => $template,
				'stylesheet' => $stylesheet,
				'page_title' => $page_title,
			) ); break;
		}

		self::_restore_errors();

		exit;
	}

	// compile a list of data to be used to generate the ticket output
	protected static function _display_ticket_args( $base='' ) {
		// load the branding image ids from our settings page
		$branding_image_ids = self::$options->{'qsot-ticket-branding-img-ids'};
		$branding_image_ids = is_scalar( $branding_image_ids ) ? explode( ',', $branding_image_ids ) : $branding_image_ids;

		// determine the branding images before hand since they are reused
		$brand_imgs = self::_get_branding_images( $branding_image_ids );

		// normalize the args
		return wp_parse_args( $base, array(
			'branding_image_ids' => $branding_image_ids,
			'brand_imgs' => $brand_imgs,
			'pdf' => apply_filters( 'qsot-is-pdf-ticket', false ), // maintaining for backwards compatibility
		) );
	}

	// generate the HTML for the ticket, and return it. do this because it may be output directly
	protected static function _get_ticket_html( $args ) {
		// extract the template name and stylesheet
		$template = $args['template'];
		$stylesheet = $args['stylesheet'];
		unset( $args['template'], $args['stylesheet'] );

		// enqueue our ticket styling
		wp_enqueue_style('qsot-ticket-style', $stylesheet, array(), self::$o->version);

		// start the capture buffer
		ob_start();

		// render the ticket
		QSOT_Templates::include_template( $template, $args );

		// store the capture buffer contents, and close the buffer
		$out = ob_get_contents();
		ob_end_clean();

		return $out;
	}

	// configure the images used on the ticket display
	public static function compile_ticket_info_images( $current, $oiid, $order_id ) {
		// do not process this unless the ticket information has been loaded
		if ( ! is_object( $current ) || is_wp_error( $current ) )
			return $current;

		// create the list of pairs to calculate
		$pairs = array(
			'image_id_left' => self::$options->{'qsot-ticket-image-shown'},
			'image_id_right' => self::$options->{'qsot-ticket-image-shown-right'},
		);

		// calculate each pair
		foreach ( $pairs as $key => $setting ) {
			switch ( $setting ) {
				default:
				case 'event':
					if ( isset( $current->event, $current->event->image_id ) )
						$current->{$key} = $current->event->image_id;
				break;

				case 'product':
					$product = wc_get_product( wc_get_order_item_meta( $oiid, '_product_id', true ) );
					if ( is_object( $product ) )
						$current->{$key} = get_post_thumbnail_id( $product->id );
				break;

				case 'venue':
					if ( isset( $current->venue, $current->venue->image_id ) )
						$current->{$key} = $current->venue->image_id;
				break;

				case 'none':
					$current->{$key} = 0;
				break;
			}
		}

		return $current;
	}

	// aggregate all the basic ticket information
	public static function compile_ticket_info( $current, $oiid, $order_id ) {
		// load the order
		$order = wc_get_order($order_id);
		if ( ! isset( $order, $order->id ) )
			return new WP_Error( 'missing_data', __( 'Could not laod the order that this ticket belongs to.', 'opentickets-community-edition' ), array( 'order_id' => $order_id ) );

		// load the order item that was specified by oiid
		$order_items = $order->get_items();
		$order_item = isset( $order_items[ $oiid ] ) ? $order_items[ $oiid ] : false;
		// if the order item could not be loaded, then fail
		if ( empty( $order_item ) || ! isset( $order_item['product_id'], $order_item['event_id'] ) )
			return new WP_Error( 'missing_data', __( 'Could not load the order item associated with this ticket.', 'opentickets-community-edition' ), array( 'oiid' => $oiid, 'items' => $order_items ) );

		// load the product specified by the order item
		$product = wc_get_product( isset( $order_item['variation_id'] ) && $order_item['variation_id'] ? $order_item['variation_id'] : $order_item['product_id'] );
		// if the product cannot be loaded, then fail
		if ( ! is_object( $product ) || is_wp_error( $product ) )
			return new WP_Error(
				'missing_data',
				__( 'The ticket product associated with the ticket order item, could not be found.', 'opentickets-community-edition' ),
				array( 'product_id' => $order_item['product_id'], 'variation_id' => $order_item['variation_id'], 'order_item' => $order_item )
			);

		// load the event specified by the order item
		$event = apply_filters('qsot-get-event', false, $order_item['event_id']);
		// fail if the event cannot be loaded
		if ( ! is_object( $event ) || ! isset( $event->ID ) )
			return new WP_Error(
				'missing_data',
				__( 'The event information for the ticket could not be found. Perhaps the event has been removed.', 'opentickets-community-edition' ),
				array( 'event_id' => $order_item['event_id'], 'order_item' => $order_item )
			);

		// populate the data we need in the ticket display
		$current = is_object($current) ? $current : new stdClass();
		$current->order_item_id = $oiid;
		$current->order = $order;
		$current->show_order_number = 'yes' == self::$options->{'qsot-ticket-show-order-id'};
		$current->order_item = $order_item;
		$current->product = $product;
		$current->event = $event;
		$current->names = array();
		$current->image_id_left = 0;
		$current->image_id_right = 0;

		// populate the event image id
		$current->event->image_id = get_post_thumbnail_id( $current->event->ID );
		$current->event->image_id = empty( $current->event->image_id ) ? get_post_thumbnail_id( $current->event->post_parent ) : $current->event->image_id;

		// if the options say use the shipping name, then attempt to use it
		if ( self::$options->{'qsot-ticket-purchaser-info'} == 'shipping' ) {
			$k = 'shipping';
			foreach ( array( 'first_name', 'last_name' ) as $_k ) {
				$key = $k . '_' . $_k;
				if ( $name = $order->$key )
					$current->names[] = $name;
			}
		}
		
		// always fallback to billing name, since it is usually required, if we still have no names
		if ( empty( $current->names ) ) {
			$k = 'billing';
			foreach ( array( 'first_name', 'last_name' ) as $_k ) {
				$key = $k . '_' . $_k;
				if ( $name = $order->$key )
					$current->names[] = $name;
			}
		}

		// if the names are still empty, try to pull any information from the user the order is assigned to, if it exists
		if ( empty( $current->names ) && ( $uid = $order->customer_user ) ) {
			$user = get_user_by( 'id', $uid );
			if ( is_object( $user ) && isset( $user->user_login ) ) {
				if ( $user->display_name )
					$current->names[] = $user->display_name;
				else
					$current->names[] = $user->user_login;
			} else {
				$current->names[] = 'unknown';
			}
		}

		return $current;
	}

	// determine if the current user can view this ticket
	protected static function _can_user_view_ticket($args) {
		$can = false;
		// load the order
		$order = wc_get_order( isset( $args['order_id'] ) ? $args['order_id'] : 0 );
		if ( ! is_object( $order ) || is_wp_error( $order ) )
			return $can;

		// figure out if guest checkout is enabled, because special logic is used for this scenario
		$guest_checkout = strtolower( get_option( 'woocommerce_enable_guest_checkout', 'no' ) ) == 'yes';

		// figure out the owner of the order, if that is stored
		$customer_user_id = get_post_meta( $order->id, '_customer_user', true );

		// determine the current logged in user, so we can compare it to the known required data
		$u = wp_get_current_user();

		// if the user is logged in, or a form was submitted, then
		if ( is_user_logged_in() || ! empty( $_POST ) ) {
			if (
					( current_user_can( 'edit_shop_orders' ) ) || // if the current user is an admin of some sort
					( $customer_user_id && current_user_can( 'edit_user', $customer_user_id ) ) || // or they can edit the profile of the user who the order is for
					( $u->ID && $customer_user_id == $u->ID ) || // or they are the user that the order is for (not the same as above)
					( $guest_checkout && apply_filters( 'qsot-ticket-verification-form-check', false, $order->id ) ) // or they passed the guest checkout ticket verification form
			) {
				$can = true; // then they can view the ticket
			// otherwise, if guest checkout is enabled, and the form was not submitted, pop the guest checkout verification form
			} else if ( $guest_checkout && ! isset( $_POST['verification_form'] ) ) {
				self::_guest_verification_form();
			// if guest checkout is enabled, and the user submitted the guect verification form, but that submission did not pass, then hard fail
			} else if ( $guest_checkout && ! apply_filters( 'qsot-ticket-verification-form-check', false, $order->id ) ) {
				self::_no_access(__('The information you supplied does not match our record.','opentickets-community-edition'));
			// if guest checkout is not enabled, then pop the login form
			} else if ( ! $guest_checkout ) {
				self::_login_form();
			// otherwise completely deny
			} else {
				self::_no_access();
			}
		// if the user is not logged in, then
		} else {
			// if the link contains a unique email auth code, then verify that code is for this order
			if ( isset( $_GET['n'] ) && apply_filters( 'qsot-verify-email-link-auth', false, $_GET['n'], $args['order_id'] ) ) {
				$can = true;
			// if guest checkout is enabled, and the verification form was not just submitted, then pop the form now
			} else if ( $guest_checkout && ! isset( $_POST['verification_form'] ) ) {
				self::_guest_verification_form();
			// otherwise pop the login form
			} else {
				self::_login_form();
			}
		}

		return $can;
	}

	public static function validate_guest_verification($pass, $order_id) {
		$email = get_post_meta($order_id, '_billing_email', true);
		return $email && $email == $_POST['email'];
	}

	protected static function _login_form() {
		$template = apply_filters('qsot-locate-template', '', array('tickets/form-login.php'), false, false);
		include_once $template;
		exit;
	}

	protected static function _no_access($msg='That is not a valid ticket.') {
		$template = apply_filters('qsot-locate-template', '', array('tickets/error-msg.php'), false, false);
		include_once $template;
		exit;
	}

	protected static function _guest_verification_form() {
		$template = apply_filters('qsot-locate-template', '', array('tickets/verification-form.php'), false, false);
		include_once $template;
		exit;
	}

	public static function setup_table_names() {
		global $wpdb;
		$wpdb->qsot_ticket_codes = $wpdb->prefix.'qsot_ticket_codes';
	}

	public static function setup_tables($tables) {
    global $wpdb;
    $tables[$wpdb->qsot_ticket_codes] = array(
      'version' => '0.1.0',
      'fields' => array(
				'order_item_id' => array('type' => 'bigint(20) unsigned'), // if of order_item that this code is for
				'ticket_code' => array('type' => 'varchar(250)'),
      ),   
      'keys' => array(
        'PRIMARY KEY  (order_item_id)',
				'INDEX tc (ticket_code(250))',
      ),
			'pre-update' => array(
				'when' => array(
					'exists' => array(
						'alter ignore table ' . $wpdb->qsot_ticket_codes . ' drop primary key',
						'alter ignore table ' . $wpdb->qsot_ticket_codes . ' drop index `tc`',
					),
				),
			),
    );   

    return $tables;
	}

	// do stuff upon activation of our plugin
	public static function on_activate() {
	}

	// setup the options that are available to control tickets. reachable at WPAdmin -> OpenTickets (menu) -> Settings (menu) -> Frontend (tab) -> Tickets (heading)
	protected static function _setup_admin_options() {
		// setup the default values
		self::$options->def( 'qsot-ticket-image-shown', 'event' );
		self::$options->def( 'qsot-ticket-image-shown-right', 'venue' );
		self::$options->def( 'qsot-ticket-purchaser-info', 'event' );
		self::$options->def( 'qsot-ticket-show-order-id', 'no' );
		self::$options->def( 'qsot-ticket-branding-img-ids', '' );

		// the 'Tickets' heading on the Frontend tab
		self::$options->add( array(
			'order' => 500,
			'type' => 'title',
			'title' => __( 'Tickets', 'opentickets-community-edition' ),
			'id' => 'heading-frontend-tickets-1',
			'page' => 'frontend',
			'section' => 'tickets',
		) );

		// which image is shown on the left side of the ticket. either no image, the Event image, the Venue image, the Ticket Product image
		self::$options->add(array(
			'order' => 505,
			'id' => 'qsot-ticket-image-shown',
			'type' => 'radio',
			'title' => __( 'Left Ticket Image', 'opentickets-community-edition' ),
			'desc_tip' => __( 'The image to show in the bottom left corner of the ticket.', 'opentickets-community-edition' ),
			'options' => array(
				'none' => __( 'no image', 'opentickets-community-edition' ),
				'event' => __( 'the Event Featured Image', 'opentickets-community-edition' ),
				'venue' => __( 'the Venue Image', 'opentickets-community-edition' ),
				'product' => __( 'the Ticket Product Image', 'opentickets-community-edition' ),
			),
			'default' => 'event',
			'page' => 'frontend',
			'section' => 'tickets',
		));

		// which image is shown on the right side of the ticket. either no image, the Event image, the Venue image, the Ticket Product image
		self::$options->add( array(
			'order' => 505,
			'id' => 'qsot-ticket-image-shown-right',
			'type' => 'radio',
			'title' => __( 'Right Ticket Image', 'opentickets-community-edition' ),
			'desc_tip' => __( 'The image to show in the bottom right corner of the ticket.', 'opentickets-community-edition' ),
			'options' => array(
				'none' => __( 'no image', 'opentickets-community-edition' ),
				'event' => __( 'the Event Featured Image', 'opentickets-community-edition' ),
				'venue' => __( 'the Venue Image', 'opentickets-community-edition' ),
				'product' => __( 'the Ticket Product Image', 'opentickets-community-edition' ),
			),
			'default' => 'venue',
			'page' => 'frontend',
			'section' => 'tickets',
		) );

		// ticket branding line images.
		self::$options->add( array(
			'order' => 509,
			'id' => 'qsot-ticket-branding-img-ids',
			'type' => 'qsot-image-ids',
			'title' => __( 'Ticket Branding Images', 'opentickets-community-edition' ),
			'desc_tip' => __( 'Images shown in the small line of branding images below each ticket.', 'opentickets-community-edition' ),
			'desc' => __( 'The recommended size of these images is 90px wide by 15px tall. Images will be constrained to 90px wide, no matter the size. Choosing "no image" will make a blank spot on the ticket branding row.', 'opentickets-community-edition' ),
			'count' => 5,
			'preview-size' => array( 90, 15 ),
			'page' => 'frontend',
			'section' => 'tickets',
		) );

		// the information about the purchaser to display. either the billing information, or the shipping information
		self::$options->add( array(
			'order' => 522,
			'id' => 'qsot-ticket-purchaser-info',
			'type' => 'radio',
			'title' => __( 'Purchaser Info', 'opentickets-community-edition' ),
			'desc_tip' => __( 'Which information to user for the purchaser display information. Either Billing or Shipping.', 'opentickets-community-edition' ),
			'options' => array(
				'billing' => __( 'the Billing Information', 'opentickets-community-edition' ),
				'shipping' => __( 'the Shipping Information', 'opentickets-community-edition' ),
			),
			'default' => 'billing',
			'page' => 'frontend',
			'section' => 'tickets',
		) );

		// whether or not to show the order # on the ticket.
		self::$options->add( array(
			'order' => 529,
			'id' => 'qsot-ticket-show-order-id',
			'type' => 'checkbox',
			'title' => __( 'Show Order #', 'opentickets-community-edition' ),
			'desc' => __( 'Show the order number of the ticket, on the ticket.', 'opentickets-community-edition' ),
			'default' => 'no',
			'page' => 'frontend',
			'section' => 'tickets',
		) );

		// end the 'Tickets' section on the page
		self::$options->add(array(
			'order' => 599,
			'type' => 'sectionend',
			'id' => 'heading-frontend-tickets-1',
			'page' => 'frontend',
			'section' => 'tickets',
		));
	}
}

if (defined('ABSPATH') && function_exists('add_action')) QSOT_tickets::pre_init();

endif;
