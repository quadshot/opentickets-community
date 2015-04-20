<?php (__FILE__ == $_SERVER['SCRIPT_FILENAME']) ? die(header('Location: /')) : null;

/* Handles the various parts of the checkin procedure, from checkin code creation, code validation, injecting the code in the ticket, etc...
 */

if (!class_exists('QSOT_checkin')):

class QSOT_checkin {
	// holder for event plugin options
	protected static $o = null;

	public static function pre_init() {
		// load the plugin settings
		$settings_class_name = apply_filters('qsot-settings-class-name', '');
		if (empty($settings_class_name) || !class_exists($settings_class_name)) return;
		self::$o = call_user_func_array(array($settings_class_name, 'instance'), array());

		// add qr to ticket
		add_filter('qsot-compile-ticket-info', array(__CLASS__, 'add_qr_code'), 3000, 3);

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
	}

	public static function intercept_checkins( $value, $qvar, $all_data, $query_vars ) {
		$packet = urldecode( $all_data['qsot-checkin-packet'] );
		self::event_checkin( self::_parse_checkin_packet( $packet ), $packet );
	}

	public static function event_checkin($data, $packet) {
		if (!is_user_logged_in() || !current_user_can('edit_users')) {
			self::_no_access('', '', $data, $packet);
			exit;
		}

		$template = '';

		if (apply_filters('qsot-is-already-occupied', false, $data['order_id'], $data['event_id'], $data['order_item_id'])) {
			$template = 'checkin/already-occupied.php';
		} else {
			$res = apply_filters('qsot-occupy-sold', false, $data['order_id'], $data['event_id'], $data['order_item_id'], 1);
			if ($res) $template = 'checkin/occupy-success.php';
			else $template = 'checkin/occupy-failure.php';
		}

		$ticket = apply_filters('qsot-compile-ticket-info', false, $data['order_item_id'], $data['order_id']);
		$ticket->owns = apply_filters('qsot-zoner-owns', array(), $ticket->event, $ticket->order_item['product_id'], '*', false, $data['order_id'], $data['order_item_id']);
		$stylesheet = apply_filters('qsot-locate-template', '', array('checkin/style.css'), false, false);
		$stylesheet = str_replace(DIRECTORY_SEPARATOR, '/', str_replace(ABSPATH, '/', $stylesheet));
		$stylesheet = site_url($stylesheet);

		$template = apply_filters('qsot-locate-template', '', array($template), false, false);
		include_once $template;

		exit;
	}

	protected static function _no_access($msg='', $heading='', $data=array(), $packet='') {
		if ( ! is_user_logged_in() ) {
			$url = wp_login_url( self::create_checkin_url( str_replace( array( '+', '=', '/' ), array( '-', '_', '~' ), $packet ) ) );
			wp_safe_redirect( $url );
			exit;
		} else {
			$template = apply_filters('qsot-locate-template', '', array('checkin/no-access.php'), false, false);
			$stylesheet = apply_filters('qsot-locate-template', '', array('checkin/style.css'), false, false);
			$stylesheet = str_replace(DIRECTORY_SEPARATOR, '/', str_replace(ABSPATH, '/', $stylesheet));
			$stylesheet = site_url($stylesheet);
			include_once $template;
		}
	}

	public static function create_checkin_url( $info ) {
		global $wp_rewrite;
		$post_link = $wp_rewrite->get_extra_permastruct( 'post' );

		$packet = self::_create_checkin_packet( $info );

		if ( ! empty( $post_link ) ) {
			$post_link = site_url( '/event-checkin/' . $packet . '/' );
		} else {
			$post_link = add_query_arg( array(
				'qsot-event-checkin' => 1,
				'qsot-checkin-packet' => $packet,
			), site_url() );
		}

		return $post_link;
	}

	// create the QR Codes that are added to the ticket display, based on the existing ticket information, order_item_id, and order_id
	public static function add_qr_code($ticket, $order_item_id, $order_id) {
		// if the $ticket has not been loaded, or could not be loaded, and thus is not an object, then gracefully skip this function
		if ( ! is_object( $ticket ) ) return $ticket;

		// validate that the $order_id supplied is a valid order
		$order = wc_get_order( $order_id );
		if ( ! is_object( $order ) ) return $ticket;

		// validate that the $order_item_id supplied is present on the supplied order
		$items = $order->get_items();
		if ( ! is_array( $items ) || ! isset( $items[ $order_item_id . '' ] ) ) return $ticket;
		$item = $items[ $order_item_id . '' ];
		unset( $order, $items );

		// determine the quantity of the tickets that were purchased for this item
		$qty = isset( $item['qty'] ) ? $item['qty'] : 1;

		// is PDF the format we are generating?
		$is_pdf = isset( $_GET['frmt'] ) && 'pdf' == $_GET['frmt'];

		// if we only have one ticket, then only generate a single QR Code
		if ( 1 == $qty ) {
			// aggregate the ticket information to compile into the QR Code
			$info = array(
				'order_id' => $ticket->order->id,
				'event_id' => $ticket->event->ID,
				'order_item_id' => $order_item_id,
				'title' => $ticket->product->get_title().' ('.$ticket->product->get_price_html().')',
				'price' => $ticket->product->get_price(),
				'uniq' => md5(sha1(microtime(true).rand(0, PHP_INT_MAX))),
				'ticket_num' => 0,
			);

			$url = self::create_checkin_url( $info );

			if ( ! $is_pdf ) {
				$img_url = self::_qr_img( $url );
				$data = array( 'd' => $url, 'p' => site_url() );
				ksort( $data );
				$data['sig'] = sha1( NONCE_KEY . @json_encode( $data ) . NONCE_SALT );
				$data = @json_encode( $data );

				$ticket->qr_code = sprintf(
					'<img src="%s%s" alt="%s" />',
					//$img_url,
					self::$o->core_url.'libs/phpqrcode/index.php?d=',
					str_replace( array( '+', '=', '/' ), array( '-', '_', '~' ), base64_encode( strrev( $data ) ) ),
					$ticket->product->get_title().' ('.$ticket->product->get_price().')'
				);
			} else {
				$img_url = self::_qr_img( $url );

				$ticket->qr_code = sprintf(
					'<img src="%s" alt="%s" />',
					$img_url,
					$ticket->product->get_title().' ('.$ticket->product->get_price().')'
				);
			}
		} else if ( $qty > 1 ) {
			$ticket->qr_code = null;
			$ticket->qr_codes = array();

			$info = array(
				'order_id' => $ticket->order->id,
				'event_id' => $ticket->event->ID,
				'order_item_id' => $order_item_id,
				'title' => $ticket->product->get_title().' ('.$ticket->product->get_price_html().')',
				'price' => $ticket->product->get_price(),
				'uniq' => md5(sha1(microtime(true).rand(0, PHP_INT_MAX))),
			);

			for ( $i = 0; $i < $qty; $i++ ) {
				$info['ticket_num'] = $i;
				$url = self::create_checkin_url( $info );

				if ( ! $is_pdf ) {
					$data = array( 'd' => $url, 'p' => site_url() );
					ksort( $data );
					$data['sig'] = sha1( NONCE_KEY . @json_encode( $data ) . NONCE_SALT );
					$data = @json_encode( $data );

					$ticket->qr_codes[ $i ] = sprintf(
						'<img src="%s%s" alt="%s" />',
						self::$o->core_url.'libs/phpqrcode/index.php?d=',
						str_replace( array( '+', '=', '/' ), array( '-', '_', '~' ), base64_encode( strrev( $data ) ) ),
						$ticket->product->get_title().' ('.$ticket->product->get_price().')'
					);
				} else {
					$img_url = self::_qr_img( $url );

					$ticket->qr_codes[ $i ] = sprintf(
						'<img src="%s" alt="%s" />',
						$img_url,
						$ticket->product->get_title().' ('.$ticket->product->get_price().')'
					);
				}
				if ( null == $ticket->qr_code ) $ticket->qr_code = $ticket->qr_codes[ $i ];
			}
		}

		return $ticket;
	}

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
			$img_url = QSOT_QRimage::jpg_base64( $tab, min( max( 1, $enc->size ), $maxSize ), $enc->margin, 100 );
		} catch (Exception $e) {
			$img_url = 'data:image/jpeg;base64,';
			// log any exceptions
			QRtools::log($outfile, $e->getMessage());
		}

		return $img_url;
	}

	protected static function _create_checkin_packet($data) {
		if ( ! is_array( $data ) ) return $data;
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
		$pack .= '|'.sha1($pack.AUTH_SALT);
		// need string replace to accommodate login screen redirect
		return str_replace( array( '+', '=', '/' ), array( '-', '_', '~' ), @base64_encode( strrev( $pack ) ) );
	}

	protected static function _parse_checkin_packet($raw) {
		$data = array();
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
		if (!$pack || !$hash || sha1($pack.AUTH_SALT) != $hash) return $data;

		$parts = explode(';', $packet, 4);
		$data['order_id'] = array_shift($parts);
		$data['order_item_id'] = array_shift($parts);
		list($data['event_id'], $data['price']) = explode('.', array_shift($parts));
		list($data['title'], $data['uniq']) = explode(':', array_shift($parts));
		return $data;
	}
}

if (defined('ABSPATH') && function_exists('add_action')) QSOT_checkin::pre_init();

endif;
