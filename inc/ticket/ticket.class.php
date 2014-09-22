<?php (__FILE__ == $_SERVER['SCRIPT_FILENAME']) ? die(header('Location: /')) : null;

/* Handles the assingment, display and templating of printable tickets.
 */

if (!class_exists('QSOT_tickets')):

class QSOT_tickets {
	// holder for event plugin options
	protected static $o = null;

	// container for templates caches
	protected static $templates = array();
	protected static $stylesheets = array();

	// order tracking
	protected static $order_id = 0;

	public static function pre_init() {
		// load the plugin settings
		$settings_class_name = apply_filters('qsot-settings-class-name', '');
		if (empty($settings_class_name) || !class_exists($settings_class_name)) return;
		self::$o = call_user_func_array(array($settings_class_name, 'instance'), array());

		// setup the db tables for the ticket code lookup
		// we offload this to a different table so that we can index the ticket codes for lookup speed
		global $wpdb;
		$wpdb->qsot_ticket_codes = $wpdb->prefix.'qsot_ticket_codes';
		add_filter('qsot-upgrader-table-descriptions', array(__CLASS__, 'setup_tables'), 10);

		// handle incoming urls that are for ticket functions
		add_filter('query_vars', array(__CLASS__, 'query_vars'), 10);
		add_action('wp', array(__CLASS__, 'intercept_ticket_request'), 11);
		add_filter('rewrite_rules_array', array(__CLASS__, 'rewrite_rules_array'), PHP_INT_MAX);

		// ticket codes
		add_filter('qsot-generate-ticket-code', array(__CLASS__, 'generate_ticket_code'), 10, 2);
		add_filter('qsot-decode-ticket-code', array(__CLASS__, 'decode_ticket_code'), 10, 2);

		// cart actions
		add_action('woocommerce_resume_order', array(__CLASS__, 'sniff_order_id'), 1000, 1);
		add_action('woocommerce_new_order', array(__CLASS__, 'sniff_order_id'), 1000, 1);
		add_action('qsot-ajax-before-add-order-item', array(__CLASS__, 'sniff_order_id'), 1000, 1);
		add_action('woocommerce_add_order_item_meta', array(__CLASS__, 'add_ticket_code_for_order_item'), 1000, 3);
		add_action('woocommerce_ajax_add_order_item_meta', array(__CLASS__, 'add_ticket_code_for_order_item'), 1000, 2);

		// order item display
		add_action('qsot-ticket-item-meta', array(__CLASS__, 'order_item_ticket_link'), 1000, 3);
		add_filter('qsot-get-ticket-link', array(__CLASS__, 'get_ticket_link'), 1000, 2);

		// display ticket
		add_action('qsot-ticket-intercepted', array(__CLASS__, 'display_ticket'), 1000, 1);
		add_filter('qsot-compile-ticket-info', array(__CLASS__, 'compile_ticket_info'), 1000, 3);
		// one-click-email link auth
		add_filter('qsot-email-link-auth', array(__CLASS__, 'email_link_auth'), 1000, 2);
		add_filter('qsot-verify-email-link-auth', array(__CLASS__, 'verify_email_link_auth'), 1000, 3);
		// guest checkout verification
		add_filter('qsot-ticket-verification-form-check', array(__CLASS__, 'validate_guest_verification'), 1000, 2);

		// email - add ticket download links
		add_action('qsot-order-item-list-ticket-info', array(__CLASS__, 'add_view_ticket_link_to_emails'), 2000, 3);

		// ticket flush rewrite rules
		add_action('qsot-activate', array(__CLASS__, 'on_activate'), 1000);

		if (is_admin()) {
			add_action('admin_footer-options-permalink.php', array(__CLASS__, 'debug_rewrite_rules'));
		}
	}

	public static function debug_rewrite_rules() {
		/*
		?><pre style="font-size:11px; color:#000000; background-color:#ffffff;"><?php print_r($GLOBALS['wp_rewrite']->rules) ?></pre><?php
		*/
	}

	public static function add_view_ticket_link_to_emails($item_id, $item, $order) {
		$status = is_callable(array(&$order, 'get_status')) ? $order->get_status() : $order->status;
		if (!in_array($status, apply_filters('qsot-ticket-link-allow-by-order-status', array('completed')))) return;

		$auth = apply_filters('qsot-email-link-auth', '', $order->id);
		$link = apply_filters('qsot-get-ticket-link', '', $item_id);
		$link = $link ? add_query_arg(array('n' => $auth), $link) : $link;
		if (empty($link)) return;

		$title = 'View your ticket';
		$display = 'View this ticket';
		if ($item['qty'] > 1) {
			$title .= 's';
			$display = 'View these tickets';
		}

		echo sprintf(
			'<br/><small> - <a class="ticket-link" href="%s" target="_blank" title="%s">%s</a></small>',
			$link,
			__($title),
			__($display)
		);
	}

	public static function order_item_ticket_link($item_id, $item, $product) {
		if (!apply_filters('qsot-item-is-ticket', false, $item)) return;

		$url = apply_filters('qsot-get-ticket-link', '', $item_id);
		if (empty($url)) return;

		$title = 'View this ticket';
		$display = 'View ticket';
		if ($item['qty'] > 1) {
			$title = 'View these tickets';
			$display = 'View tickets';
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

	public static function get_ticket_link($current, $item_id) {
		global $wpdb, $wp_rewrite;

		$order_status = self::_order_item_order_status( $item_id );
		if ( ! in_array( $order_status, array( 'completed' ) ) ) return '';

		$q = $wpdb->prepare('select ticket_code from '.$wpdb->qsot_ticket_codes.' where order_item_id = %d', $item_id);
		$code = $wpdb->get_var($q);

		if ( empty($code) ) return $current;

		$post_link = $wp_rewrite->get_extra_permastruct('post');

		if ( !empty($post_link) ) {
			$post_link = site_url('/ticket/'.$code.'/');
		} else {
			$post_link = add_query_arg(array(
				'qsot-ticket' => 1,
				'qsot-ticket-id' => $code,
			), site_url());
		}

		return $post_link;
	}

	public static function sniff_order_id($order_id) {
		self::$order_id = $order_id;
	}

	public static function add_ticket_code_for_order_item($item_id, $values, $key='') {
		if (empty(self::$order_id)) return;

		global $wpdb;

		$code_args = array_merge(
			$values,
			array(
				'order_id' => self::$order_id,
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
		$code = trim(base64_decode(str_replace(array('-', '_'), array('/', '+'), $code)), '|');
		@list($raw, $hash) = explode('~', $code);
		if (!$raw || !$hash || $hash != sha1($raw.AUTH_KEY)) return $current;

		$args = array();
		list($args['event_id'], $args['order_id'], $args['order_item_id']) = explode('.', $raw);
		$args = apply_filters('qsot-decode-ticket-code-args', $args, $raw);

		return $args;
	}

	public static function query_vars($vars) {
		$new_items = array(
			'qsot-ticket',
			'qsot-ticket-id',
		);

		return array_unique(array_merge($vars, $new_items));
	}

	public static function email_link_auth($current, $order_id) {
		$user_id = get_post_meta($order_id, '_customer_user', true);
		$email = get_post_meta($order_id, '_billing_email', true);
		$str = sprintf('%s.%s.%s.%s.%s', AUTH_KEY, $user_id, $email, $order_id, NONCE_SALT);
		$str .= '~'.sha1($str);
		return @strrev(@md5(@strrev($str)));
	}

	public static function validate_email_link_auth($pass, $auth, $order_id) {
		$check = apply_filters('qsot-email-link-auth', '', $order_id);
		return $check === $auth;
	}

	public static function display_ticket($code) {
		$args = apply_filters('qsot-decode-ticket-code', array(), $code);
		if (empty($args['order_id']) || empty($args['order_item_id'])) return false;
		if (!self::_can_user_view_ticket($args)) return false;

		$ticket = apply_filters('qsot-compile-ticket-info', false, $args['order_item_id'], $args['order_id']);
		$template = apply_filters('qsot-locate-template', '', array('tickets/basic-ticket.php'), false, false);
		$stylesheet = apply_filters('qsot-locate-template', '', array('tickets/basic-style.css'), false, false);
		$stylesheet = str_replace(DIRECTORY_SEPARATOR, '/', str_replace(ABSPATH, '/', $stylesheet));
		$stylesheet = site_url($stylesheet);

		$out = self::_get_ticket_html(array('ticket' => $ticket, 'template' => $template, 'stylesheet' => $stylesheet));

		$_GET = wp_parse_args($_GET, array('frmt' => 'html'));
		switch ($_GET['frmt']) {
			case 'pdf':
				$title = $ticket->product->get_title().' ('.$ticket->product->get_price().')';
				self::_print_pdf($out, $title);
			break;
			default: echo $out; break;
		}

		exit;
	}

	protected static function _get_ticket_html($args) {
		ob_start();
		extract($args);
		wp_enqueue_style('qsot-ticket-style', $stylesheet, array(), self::$o->version);

		include_once $template;
		$out = ob_get_contents();
		ob_end_clean();

		return $out;
	}

	public static function compile_ticket_info($current, $oiid, $order_id) {
		$order = new WC_Order($order_id);

		$order_items = $order->get_items();
		$order_item = isset($order_items[$oiid]) ? $order_items[$oiid] : false;
		if (empty($order_item) || !isset($order_item['product_id'], $order_item['event_id'])) return $current;

		$product = get_product($order_item['product_id']);
		$event = apply_filters('qsot-get-event', false, $order_item['event_id']);
		if (empty($event) || empty($product) || is_wp_error($product)) return $current;

		$current = is_object($current) ? $current : new stdClass();
		$current->order = $order;
		$current->order_item = $order_item;
		$current->product = $product;
		$current->event = $event;

		return $current;
	}

	protected static function _print_pdf($html, $title) {
		$u = wp_upload_dir();
		$pth = $u['basedir'];
		if (empty($pth)) return;
		$pth = trailingslashit($pth).'tcpdf-cache/';
		$url = trailingslashit($u['baseurl']).'tcpdf-cache/';

		if (!file_exists($pth) && !mkdir($pth)) return;

		require_once self::$o->core_dir.'libs/dompdf/dompdf_config.inc.php';

		$pdf = new DOMPDF();
		$pdf->load_html($html);
		$pdf->render();
		$pdf->stream(sanitize_title_with_dashes('ticket-'.$title).'.pdf');
	}

	protected static function _can_user_view_ticket($args) {
		$can = false;

		$order = get_post($args['order_id']);
		if (!is_object($order) || !isset($order->ID)) return $can;

		$guest_checkout = strtolower(get_option('woocommerce_enable_guest_checkout', 'no')) == 'yes';
		$customer_user_id = get_post_meta($order->ID, '_customer_user', true);
		$u = wp_get_current_user();

		if (is_user_logged_in()) {
			if (
					(current_user_can('manage_woocommerce_orders')) ||
					($customer_user_id && current_user_can('edit_user', $customer_user_id)) ||
					($u->ID && $customer_user_id == $u->ID)
			) {
				$can = true;
			} else if ($guest_checkout && !isset($_POST['verification_form'])) {
				self::_guest_verification_form();
			} else if ($cuest_checkout && !apply_filters('qsot-ticket-verification-form-check', false, $order->ID)) {
				self::_no_access('The information you supplied does not match our record.');
			} else {
				self::_no_access();
			}
		} else {
			if (isset($_GET['n']) && apply_filters('qsot-verify-email-link-auth', false, $args['order_id'])) {
				$can = true;
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
	}

	protected static function _no_access($msg='That is not a valid ticket.') {
		$template = apply_filters('qsot-locate-template', '', array('tickets/error-msg.php'), false, false);
		include_once $template;
	}

	protected static function _guest_verification_form() {
		$template = apply_filters('qsot-locate-template', '', array('tickets/verification-form.php'), false, false);
		include_once $template;
	}

	public static function intercept_ticket_request(&$wp) {
		if (isset($wp->query_vars['qsot-ticket'], $wp->query_vars['qsot-ticket-id']) && $wp->query_vars['qsot-ticket']) {
			$code = $wp->query_vars['qsot-ticket-id'];
			do_action('qsot-ticket-intercepted', $code);
		}
	}

	public static function rewrite_rules_array($current) {
		global $wp_rewrite;
		$rules = apply_filters('qsot-tickets-rewrite-rules', array(
			'qsot-ticket' => array('ticket/(.*)?', 'qsot-ticket=1&qsot-ticket-id='),
		));
		$extra = array();

		foreach ($rules as $k => $v) {
			list($find, $replace) = $v;
			$wp_rewrite->add_permastruct($k, '%'.$k.'%', false, EP_PAGES);
			$wp_rewrite->add_rewrite_tag('%'.$k.'%', $find, $replace);
			$uri_rules = $wp_rewrite->generate_rewrite_rules('%'.$k.'%', EP_PAGES);
			$extra = array_merge($extra, $uri_rules);
		}

		return $extra + $current;
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
        'PRIMARY KEY  oiid (order_item_id)',
				'INDEX tc (ticket_code(250))',
      )    
    );   

    return $tables;
	}

	public static function on_activate() {
		flush_rewrite_rules();
	}
}

if (defined('ABSPATH') && function_exists('add_action')) QSOT_tickets::pre_init();

endif;
