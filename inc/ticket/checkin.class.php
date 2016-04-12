<?php if ( __FILE__ == $_SERVER['SCRIPT_FILENAME'] ) die( header( 'Location: /') );

/* Handles the various parts of the checkin procedure, from checkin code creation, code validation, injecting the code in the ticket, etc...
 */

if (!class_exists('QSOT_checkin')):

class QSOT_checkin {
	// holder for event plugin options
	protected static $o = null;
	protected static $options = null;

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

		// add qr to ticket
		add_filter('qsot-compile-ticket-info', array(__CLASS__, 'add_qr_code'), 3000, 3);

		// compile ticket qr data
		add_filter( 'qsot-get-ticket-qr-data', array( __CLASS__, 'get_ticket_qr_data' ), 1000, 2 );

		// add rewrite rules to intercept the QR Code scans
		do_action(
			'qsot-rewriter-add',
			'qsot-event-checkin',
			array(
				'name' => 'qsot-event-checkin',
				'query_vars' => array( 'qsot-event-checkin', 'qsot-checkin-packet' ),
				'rules' => array( 'event-checkin/(.*)?' => 'qsot-event-checkin=1&qsot-checkin-packet=' ),
				'func' => array( __CLASS__, 'intercept_checkins' ),
			)
		);

		// check if the 'checkin' role and cap needs to be created
		if ( is_admin() )
			self::_check_roles_and_caps();
	}

	// handler for the checkin urls
	public static function intercept_checkins( $value, $qvar, $all_data, $query_vars ) {
		$packet = urldecode( $all_data['qsot-checkin-packet'] );
		self::event_checkin( self::_parse_checkin_packet( $packet ), $packet );
	}

	// interprets the request, and formulates an appropriate response
	public static function event_checkin( $data, $packet ) {
		// if the user is not logged in, or if they don't have access to check ppl in, then have them login or error out
		if ( ! is_user_logged_in() || ! current_user_can( 'checkin' ) ) {
			self::_no_access( '', '', $data, $packet );
			exit;
		}

		$template = '';

		// load the event, event area, and area type objects
		$event = get_post( $data['event_id'] );
		$event_area = apply_filters( 'qsot-event-area-for-event', false, $event );
		$area_type = is_object( $event_area ) && isset( $event_area->area_type ) ? $event_area->area_type : null;
		$zoner = is_object( $area_type ) ? $area_type->get_zoner() : null;
		$stati = is_object( $zoner ) ? $zoner->get_stati() : array();

		// if the zoner was not loaded, then this is a hard failure
		if ( ! is_object( $zoner ) ) {
			$template = 'checkin/occupy-failure.php';
			$extra_msg = __( 'Could not find that event.', 'opentickets-community-edition' );
		} else {
			// load the order item
			$order = wc_get_order( $data['order_id'] );
			$order_items = $order->get_items();
			$order_item = isset( $order_items[ $data['order_item_id'] ] ) ? $order_items[ $data['order_item_id'] ] : false;

			// if there is no order item then bail
			if ( ! $order_item ) {
				$template = 'checkin/occupy-failure.php';
				$extra_msg = __( 'Could not find that order.', 'opentickets-community-edition' );
			} else {
				// check if the seat is already occupied
				$qargs = array(
					'event_id' => $data['event_id'],
					'order_id' => $data['order_id'],
					'order_item_id' => $data['order_item_id'],
					'state' => $stati['o'][0],
				);
				$res = $zoner->find( $qargs );
				$res = current( $res );

				// if the seat is already checked in, load a template saying so
				if ( is_object( $res ) && $res->quantity >= $order_item['qty'] ) {
				//if ( apply_filters( 'qsot-is-already-occupied', false, $data['order_id'], $data['event_id'], $data['order_item_id'] ) ) {
					$template = 'checkin/already-occupied.php';
				// otherwise
				} else {
					// try to check the seat in
					$res = $zoner->occupy( false, array(
						'order_id' => $data['order_id'],
						'event_id' => $data['event_id'],
						'order_item_id' => $data['order_item_id'],
						'__raw' => $data,
					) );
					// if it was successful, have a message saying that
					if ( $res && ! is_wp_error( $res ) ) $template = 'checkin/occupy-success.php';
					// otherwise, have a message saying it failed
					else {
						$template = 'checkin/occupy-failure.php';
						if ( is_wp_error( $res ) )
							$extra_msg = implode( ' ', $res->get_error_messages() );
					}
				}
			}
		}

		// load the information used by the checkin template
		$ticket = apply_filters( 'qsot-compile-ticket-info', false, $data['order_item_id'], $data['order_id'] );
		//$ticket->owns = apply_filters( 'qsot-zoner-owns', array(), $ticket->event, $ticket->order_item['product_id'], '*', false, $data['order_id'], $data['order_item_id'] );
		$stylesheet = apply_filters( 'qsot-locate-template', '', array('checkin/style.css'), false, false );
		$stylesheet = str_replace( DIRECTORY_SEPARATOR, '/', str_replace( ABSPATH, '/', $stylesheet ) );
		$stylesheet = site_url( $stylesheet );

		// find the template, ensuring to allow theme overrides and such
		$template = apply_filters( 'qsot-locate-template', '', array( $template ), false, false );
		// render the results
		include_once $template;

		exit;
	}

	// when a user does not have access to check a ticket in, either they are logged out, or they do not have permission. respond to either situation
	protected static function _no_access($msg='', $heading='', $data=array(), $packet='') {
		// if they are not logged in, then pop a login form
		if ( ! is_user_logged_in() ) {
			$url = wp_login_url( self::create_checkin_url( str_replace( array( '+', '=', '/' ), array( '-', '_', '~' ), $packet ) ) );
			wp_safe_redirect( $url );
			exit;
		// if they are logged in, but do not have permission, then fail
		} else {
			$template = apply_filters( 'qsot-locate-template', '', array( 'checkin/no-access.php' ), false, false );
			$stylesheet = apply_filters( 'qsot-locate-template', '', array( 'checkin/style.css' ), false, false );
			$stylesheet = str_replace( DIRECTORY_SEPARATOR, '/', str_replace( ABSPATH, '/', $stylesheet ) );
			$stylesheet = site_url( $stylesheet );
			include_once $template;
		}
	}

	// create the url that will be used for the checkin process, based on the current permalink structure
	public static function create_checkin_url( $info ) {
		global $wp_rewrite;
		$post_link = $wp_rewrite->get_extra_permastruct( 'post' );

		$packet = self::_create_checkin_packet( $info );

		// if we are using pretty permalinks, then make a pretty url
		if ( ! empty( $post_link ) ) {
			$post_link = site_url( '/event-checkin/' . $packet . '/' );
		// otherwise use the default url struct, and have query params instead
		} else {
			$post_link = add_query_arg( array(
				'qsot-event-checkin' => 1,
				'qsot-checkin-packet' => $packet,
			), site_url() );
		}

		return $post_link;
	}

	// create the QR Codes that are added to the ticket display, based on the existing ticket information, order_item_id, and order_id
	public static function add_qr_code( $ticket, $order_item_id, $order_id ) {
		// if the $ticket has not been loaded, or could not be loaded, and thus is not an object or is a wp_error, then gracefully skip this function
		if ( ! is_object( $ticket ) || is_wp_error( $ticket ) ) return $ticket;

		// verify that the order was loaded
		if ( ! isset( $ticket->order, $ticket->order->id ) )
			return new WP_Error( 'missing_data', __( 'Could not laod the order that this ticket belongs to.', 'opentickets-community-edition' ), array( 'order_id' => $order_id ) );
		$order = $ticket->order;

		// verify that the order item was loaded
		if ( ! isset( $ticket->order_item ) || empty( $ticket->order_item ) || ! isset( $ticket->order_item['product_id'], $ticket->order_item['event_id'] ) )
			return new WP_Error( 'missing_data', __( 'Could not load the order item associated with this ticket.', 'opentickets-community-edition' ), array( 'oiid' => $order_item_id ) );
		$item = $ticket->order_item;

		// determine the quantity of the tickets that were purchased for this item
		$qty = isset( $item['qty'] ) ? $item['qty'] : 1;

		// find all the codes that are to be encoded in the qr codes
		$codes = apply_filters( 'qsot-get-ticket-qr-data', array(), array(
			'order_id' => $ticket->order->id,
			'event_id' => $ticket->event->ID,
			'order_item_id' => $order_item_id,
			'product' => $ticket->product,
			'qty' => $qty,
		) );

		$ticket->qr_code = null;

		for ( $i = 0; $i < count( $codes ); $i++ ) {
			// get the url, width and height to use for the image tag
			@list( $url, $width, $height ) = self::qr_img_url( $codes[ $i ] );

			// create the image url
			$atts = array( 'src' => $url, 'alt' => $ticket->product->get_title() . ' (' . $ticket->product->get_price() . ')' );
			if ( null !== $width )
				$atts['width'] = $width;
			if ( null !== $height )
				$atts['height'] = $height;
			// compile the img atts
			$atts_arr = array();
			foreach ( $atts as $k => $v )
				$atts_arr[] = sprintf( '%s="%s"', $k, esc_attr( $v ) );
			$ticket->qr_codes[ $i ] = '<img ' . implode( ' ', $atts_arr ) . ' />';

			// make sure that the first code is added as the primary code. eventually this will be deprecated
			if ( null == $ticket->qr_code )
				$ticket->qr_code = $ticket->qr_codes[ $i ];
		}

		if ( ! WP_DEBUG )
			unset( $ticket->qr_data_debugs, $ticket->qr_data_debug );
		else if ( isset( $ticket->qr_data_debugs ) && defined( 'WP_DEBUG_TICKETS' ) && WP_DEBUG_TICKETS )
			var_dump( $ticket->qr_data_debugs );

		return $ticket;
	}

	// get the qr image url
	public static function qr_img_url( $code ) {
		static $su = false;
		// cache the site url, used for qr code validation in phpqrcode lib
		if ( false === $su )
			$su = site_url();

		$url = '';
		$width = null;
		$height = null;
		$using_phpqrcode = defined( 'QSOT_USE_PHPQRCODE' ) && QSOT_USE_PHPQRCODE;

		// PHPQRCODE lib section. obsolete in favor of google charts. still configurable for use with constant.... for now
		if ( $using_phpqrcode ) {
			// pack the data into something we can pass to the lib
			$data = array( 'd' => $code, 'p' => $su );
			ksort( $data );
			$data['sig'] = sha1( NONCE_KEY . @json_encode( $data ) . NONCE_SALT );
			$data = @json_encode( $data );

			// create the url
			$url = add_query_arg( array( 'd' => str_replace( array( '+', '=', '/' ), array( '-', '_', '~' ), base64_encode( strrev( $data ) ) ) ), self::$o->core_url . 'libs/phpqrcode/index.php' );
		// default is to use google apis
		} else {
			$width = $height = 185;
			$data = array(
				'cht' => 'qr',
				'chld' => 'L|1',
				'choe' => 'UTF-8',
				'chs' => $width . 'x' . $height,
				'chl' => rawurlencode( $code ),
			);
			$url = add_query_arg( $data, 'https://chart.googleapis.com/chart' );
		}

		// add a filter for modification of url (like base64 encodeing or external domain or something
		$url = apply_filters( 'qsot-qr-img-url', $url, $code, $data, $using_phpqrcode );

		return array( $url, $width, $height );
	}

	// create all the codes that are encoded inside the QR Codes
	public static function get_ticket_qr_data( $code, $args ) {
		static $is_url = null;
		// figure out the global settings of how these codes should be created: as urls or just codes
		if ( null === $is_url )
			$is_url = 'checkin-url' == self::$options->{'qsot-ticket-qr-mode'};

		// normalize the input data
		$args = wp_parse_args( $args, array(
			'order_id' => 0,
			'event_id' => 0,
			'order_item_id' => 0,
			'product' => 0,
			'qty' => 0,
			'index' => 0,
		) );

		// load the product if it was not sent as a product object, and bail if there is not a product
		if ( is_numeric( $args['product'] ) && ! empty( $args['product'] ) )
			$args['product'] = wc_get_product( $args['product'] );
		if ( ! is_object( $args['product'] ) || is_wp_error( $args['product'] ) )
			return $code;

		// create the base data that is encoded in the packets
		$base = array(
			'order_id' => $args['order_id'],
			'order_item_id' => $args['order_item_id'],
			'event_id' => $args['event_id'],
			'title' => $args['product']->get_title() . ' (' . $args['product']->get_price_html() . ')',
			'price' => $args['product']->get_price(),
			'uniq' => md5( sha1( 0 . ':' . $args['order_id'] . ':' . $args['order_item_id'] ) ),
			'ticket_num' => 0,
		);

		$code = array();
		// if a specific index was sent in our input, then we only want to return the code for that one index
		if ( isset( $args['index'] ) && $args['index'] ) {
			// comiple the data for the code
			$info = $base;
			$info['uniq'] = md5( sha1( $args['index'] . ':' . $args['order_id'] . ':' . $args['order_item_id'] ) );
			$info['ticket_num'] = $args['index'];

			// add the code to the return list
			$code[] = $is_url ? self::create_checkin_url( $info ) : self::_create_checkin_packet( $info );
		// otherwise, just add one code per ticket in the quantity
		} else {
			for ( $i = 0; $i < $args['qty']; $i++ ) {
				// aggregate the data for this code
				$info = $base;
				$info['uniq'] = md5( sha1( ( $i + 1 ) . ':' . $args['order_id'] . ':' . $args['order_item_id'] ) );
				$info['ticket_num'] = $i + 1;

				// add the code to the return list
				$code[] = $is_url ? self::create_checkin_url( $info ) : self::_create_checkin_packet( $info );
			}
		}

		return $code;
	}

	// create a base64 encoded image that can be embeded in the pdf, instead of externally loaded, since that can cause problems
	protected static function _qr_img( $data ) {
		require_once self::$o->core_dir . 'libs/phpqrcode/qrlib.php';
		require_once self::$o->core_dir . 'libs/phpqrcode/qsot-qrimage.php';

		ob_start();

		// create the encoder
		$enc = QRencode::factory('L', 3, 1);

		$outfile = false;
		try {
			// attempt to encode the data
			ob_start();
			$tab = $enc->encode( $data );
			$err = ob_get_contents();
			ob_end_clean();

			// log any errors produced
			if ( $err != '' )
				QRtools::log( $outfile, $err );

			// calculate the dimensions of the image
			$maxSize = (int)( QR_PNG_MAXIMUM_SIZE / ( count( $tab ) + 2 * $enc->margin ) );

			// render the image
			$img_data = QSOT_QRimage::jpg_base64( $tab, 2.5/*min( max( 1, $enc->size ), $maxSize )*/, $enc->margin, 100 );
		} catch ( Exception $e ) {
			$img_data = array( 'data:image/jpeg;base64,', 0, 0 );
			// log any exceptions
			QRtools::log( $outfile, $e->getMessage() );
		}

		return $img_data;
	}

	// create the packed that is used in the checkin process. this is a stringified version of all the information needed to check a user in
	protected static function _create_checkin_packet( $data ) {
		// if there is no data, then return nothing
		if ( ! is_array( $data ) ) return $data;

		$pack = null;
		// allow other plugins to create their own checkin packet if they like. NOTE: they may also need to hook into 'qsot-parse-checkin-packet' below if they want to do this
		$pack = apply_filters( 'qsot-create-checkin-packet', $pack, $data );

		// if there is not a plugin override on this, then create a specifically formatted string containing the data we need
		if ( null === $pack )
			$pack = sprintf(
				'%s;%s;%s.%s;%s:%s:%s',
				$data['order_id'],
				$data['order_item_id'],
				$data['event_id'],
				$data['price'],
				$data['title'],
				$data['uniq'],
				$data['ticket_num']
			);

		// sign it for security
		$pack .= '|' . sha1( $pack . AUTH_SALT );

		// need string replace because some characters are not urlencode/decode friendly or query param friendly
		return str_replace( array( '+', '=', '/' ), array( '-', '_', '~' ), @base64_encode( strrev( $pack ) ) );
	}

	// unpack the data stored in the checkin url packet, and put it in array format again, so that it can be used to perform the checkin
	protected static function _parse_checkin_packet($raw) {
		$data = array();
		// make the reverse string replacements from above, otherwise the base64 won't decode
		$raw = str_replace( array( '-', '_', '~' ), array( '+', '=', '/' ), $raw );
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG )
			$packet = strrev( base64_decode( $raw ) );
		else
			$packet = strrev( @base64_decode( $raw ) );

		// ticket security
		// strrev to prevent 'title' tampering, if that is even a thing
		$pack = explode( '|', strrev( $packet ), 2 );
		$hash = strrev( array_shift( $pack ) );
		$pack = strrev( implode( '|', $pack ) );
		if ( ! $pack || ! $hash || sha1( $pack . AUTH_SALT ) != $hash ) return $data;

		$data = null;
		// allow other plugins to interpret the packet on their own; for instance, if they have custom packet logic above at filter 'qsot-create-checkin-packet'
		$data = apply_filters( 'qsot-parse-checkin-packet', $data, $pack );

		// if there is no plugin override, then assume we are dealing with the default packet, and parse that
		if ( null === $data ) {
			$data = array();
			$parts = explode( ';', $packet, 4 );
			$data['order_id'] = array_shift( $parts );
			$data['order_item_id'] = array_shift( $parts );
			list( $data['event_id'], $data['price'] ) = explode( '.', array_shift( $parts ) );
			list( $data['title'], $data['uniq'] ) = explode( ':', array_shift( $parts ) );
		}

		return $data;
	}

  // check if the 'checkin' cap and accompanying role need to be created
	protected static function _check_roles_and_caps() {
		// fetch the last version that this check was performed on
		$last_check = get_option( '_qsot_checkin_role_check', '0.0.0' );

		// if the last check was done at a version lower than the current version, then do it now
		if ( version_compare( $last_check, QSOT::version() ) < 0 ) {
			self::_update_roles_and_caps();
			update_option( '_qsot_checkin_role_check', QSOT::version() );
		}
	}

	// add our checkin role if it does not exist, and update all applicable roles with the capability for checkin
	protected static function _update_roles_and_caps() {
		if ( function_exists( 'wp_roles' ) ) {
			// get the wp_roles object
			$wp_roles = wp_roles();
		} else {
			global $wp_roles;
		}

		// get the names of all roles
		$names = $wp_roles->get_names();

		// if the checkin role does not exist, then create it
		if ( ! isset( $names['qsot-checkin'] ) )
			add_role( 'qsot-checkin', __( 'Check-in Only', 'opentickets-community-edition' ), array( 'read' => 1, 'level_0' => 1, 'checkin' => 1 ) );

		// cycle through the roles, and add the capability to the ones that need it
		foreach ( $names as $slug => $display ) {
			// if this is the subscriber or customer role, just skip it, becuase .... well they dont need it
			if ( in_array( $slug, array( 'subscriber', 'customer' ) ) )
				continue;

			// get the role
			$role = $wp_roles->get_role( $slug );

			// if this is the checkin role, then add it straight away
			if ( 'qsot-checkin' == $slug ) {
				$role->add_cap( 'checkin', '1' );
				continue;
			}

			$enough = false;
			// otherwise, make sure the role has higher than the subscriber role. if it does, then add this as a cap
			foreach ( $role->capabilities as $cap => $has ) {
				if ( $has && ! in_array( $cap, array( 'read', 'level_0' ) ) ) {
					$role->add_cap( 'checkin', '1' );
					break;
				}
			}
		}
	}

	// setup the options that are available to control tickets. reachable at WPAdmin -> OpenTickets (menu) -> Settings (menu) -> Frontend (tab) -> Tickets (heading)
	protected static function _setup_admin_options() {
		// setup the defaults, so that queries to the options object give the correct value, if none has been set by the admins
		self::$options->def( 'qsot-ticket-qr-mode', 'checkin-url' );

		// the 'Tickets' heading on the Frontend tab
		self::$options->add( array(
			'order' => 600,
			'type' => 'title',
			'title' => __( 'Checkin & QR Codes', 'opentickets-community-edition' ),
			'id' => 'heading-checkin-1',
			'page' => 'frontend',
			'section' => 'tickets',
		) );

		// setup the settings section
		self::$options->add( array(
			'order' => 605,
			'id' => 'qsot-ticket-qr-mode',
			'type' => 'radio',
			'title' => __( 'QR Code Mode', 'qsot' ),
			'desc_tip' => __( 'How should the QR codes be created? Should they just contain the ticket code? or should be they point to a check-in url?', 'qsot' ),
			'options' => array(
				'checkin-url' => __( 'Check-in URL', 'qsot' ),
				'code-only' => __( 'Ticket Code Only', 'qsot' ),
			),
			'default' => self::$options->{'qsot-ticket-qr-mode'},
			'page' => 'frontend',
			'section' => 'tickets',
		) );

		// end the 'Checkin & QR Codes' section on the page
		self::$options->add( array(
			'order' => 699,
			'type' => 'sectionend',
			'id' => 'heading-checkin-1',
			'page' => 'frontend',
			'section' => 'tickets',
		) );
	}
}

if (defined('ABSPATH') && function_exists('add_action')) QSOT_checkin::pre_init();

endif;
