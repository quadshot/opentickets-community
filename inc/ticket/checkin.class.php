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
		self::$o =& call_user_func_array(array($settings_class_name, 'instance'), array());

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

		$ticket = apply_filters('qsot-compile-ticket-info', false, $args['order_item_id'], $args['order_id']);
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

	public static function add_qr_code($ticket, $order_item_id, $order_id) {
		$info = array(
			'order_id' => $ticket->order->id,
			'event_id' => $ticket->event->ID,
			'order_item_id' => $order_item_id,
			'title' => $ticket->product->get_title().' ('.$ticket->product->get_price_html().')',
			'price' => $ticket->product->get_price(),
			'uniq' => md5(sha1(microtime(true).rand(0, PHP_INT_MAX))),
		);
		$data = site_url('/event-checkin/'.self::_create_checkin_packet($info).'/');

		$ticket->qr_code = sprintf(
			'<img src="%s%s" alt="%s" />',
			self::$o->core_url.'libs/phpqrcode/?d=',
			//base64_encode(@json_encode($data)),
			base64_encode(strrev($data)),
			$ticket->product->get_title().' ('.$ticket->product->get_price().')'
		);

		return $ticket;
	}

	protected static function _create_checkin_packet($data) {
		$pack = sprintf(
			'%s;%s;%s.%s;%s:%s',
			$data['order_id'],
			$data['order_item_id'],
			$data['event_id'],
			$data['price'],
			$data['title'],
			$data['uniq']
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
