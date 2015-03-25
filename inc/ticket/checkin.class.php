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

		// add rewrite rules handling
		add_action('wp', array(__CLASS__, 'intercept_checkin_request'), 15);
		add_filter('query_vars', array(__CLASS__, 'query_vars'), 1000);
		add_filter('qsot-tickets-rewrite-rules', array(__CLASS__, 'checkin_rewrite_rules'), 1000);
		add_action('qsot-event-checkin-intercepted', array(__CLASS__, 'event_checkin'), 1000, 2);

		// add qr to ticket
		add_filter('qsot-compile-ticket-info', array(__CLASS__, 'add_qr_code'), 3000, 3);
	}

	public static function intercept_checkin_request(&$wp) {
		if (isset($wp->query_vars['qsot-event-checkin'], $wp->query_vars['qsot-checkin-packet']) && $wp->query_vars['qsot-event-checkin']) {
			$packet = $wp->query_vars['qsot-checkin-packet'];
			do_action('qsot-event-checkin-intercepted', self::_parse_checkin_packet($packet), $packet);
		}
	}

	public static function event_checkin($data, $packet) {
		if (!is_user_logged_in() || !current_user_can('edit_users')) {
			self::_no_access();
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

	protected static function _no_access($msg='', $heading='') {
		$template = apply_filters('qsot-locate-template', '', array('checkin/no-access.php'), false, false);
		$stylesheet = apply_filters('qsot-locate-template', '', array('checkin/style.css'), false, false);
		$stylesheet = str_replace(DIRECTORY_SEPARATOR, '/', str_replace(ABSPATH, '/', $stylesheet));
		$stylesheet = site_url($stylesheet);
		include_once $template;
	}

	public static function query_vars($vars) {
		$new_items = array(
			'qsot-event-checkin',
			'qsot-checkin-packet',
		);

		return array_unique(array_merge($vars, $new_items));
	}

	public static function checkin_rewrite_rules($rules) {
		$rules['qsot-event-checkin'] = array('event-checkin/(.*)?', 'qsot-event-checkin=1&qsot-checkin-packet=');
		return $rules;
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

			$url = site_url('/event-checkin/'.self::_create_checkin_packet($info).'/');

			$data = array( 'd' => $url, 'p' => site_url() );
			ksort( $data );
			$data['sig'] = sha1( NONCE_KEY . @json_encode( $data ) . NONCE_SALT );
			$data = @json_encode( $data );

			$ticket->qr_code = sprintf(
				'<img src="%s%s" alt="%s" />',
				self::$o->core_url.'libs/phpqrcode/index.php?d=',
				base64_encode(strrev($data)),
				$ticket->product->get_title().' ('.$ticket->product->get_price().')'
			);
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
				$url = site_url('/event-checkin/'.self::_create_checkin_packet($info).'/');

				$data = array( 'd' => $url, 'p' => site_url() );
				ksort( $data );
				$data['sig'] = sha1( NONCE_KEY . @json_encode( $data ) . NONCE_SALT );
				$data = @json_encode( $data );

				$ticket->qr_codes[ $i ] = sprintf(
					'<img src="%s%s" alt="%s" />',
					self::$o->core_url.'libs/phpqrcode/index.php?d=',
					base64_encode(strrev($data)),
					$ticket->product->get_title().' ('.$ticket->product->get_price().')'
				);
				if ( null == $ticket->qr_code ) $ticket->qr_code = $ticket->qr_codes[ $i ];
			}
		}

		return $ticket;
	}

	protected static function _create_checkin_packet($data) {
		$pack = sprintf(
			'%s;%s;%s.%s;%s:%s;%s',
			$data['order_id'],
			$data['order_item_id'],
			$data['event_id'],
			$data['price'],
			$data['title'],
			$data['uniq'],
			$data['ticket_num']
		);
		$pack .= '|'.sha1($pack.AUTH_SALT);
		return @base64_encode(strrev($pack));
	}

	protected static function _parse_checkin_packet($raw) {
		$data = array();
		$packet = strrev(@base64_decode($raw));

		// ticket security
		$pack = explode('|', $packet);
		$hash = array_pop($pack);
		$pack = implode('|', $pack);
		if (!$pack || !$hash || sha1($pack.AUTH_SALT) != $hash) return $data;

		$parts = explode(';', $packet);
		$data['order_id'] = array_shift($parts);
		$data['order_item_id'] = array_shift($parts);
		list($data['event_id'], $data['price']) = explode('.', array_shift($parts));
		list($data['title'], $data['uniq']) = explode(':', array_shift($parts));
		return $data;
	}
}

if (defined('ABSPATH') && function_exists('add_action')) QSOT_checkin::pre_init();

endif;
