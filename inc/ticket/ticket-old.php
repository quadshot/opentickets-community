<?php (__FILE__ == $_SERVER['SCRIPT_FILENAME']) ? die(header('Location: /')) : null;

class qsot_event_tickets {
	// holder for event plugin options
	protected static $o = null;

	protected static $templates = array();
	protected static $stylesheets = array();

	public static function pre_init() {
		// first thing, load all the options, and share them with all other parts of the plugin
		$settings_class_name = apply_filters('qsot-settings-class-name', '');
		self::$o = $settings_class_name::instance();

		self::$o->tk = array(
			'mk' => array(
				'tmpl' => '__ticket_template',
				'style' => '__ticket_template',
			),
		);

		add_action('qsot-ticket-intercepted', array(__CLASS__, 'display_ticket'), 10, 1);
		add_action('qsot-get-event-ticket-template', array(__CLASS__, 'get_event_ticket_template'), 10, 2);
		add_action('qsot-get-event-ticket-stylesheet', array(__CLASS__, 'get_event_ticket_stylesheet'), 10, 2);

		add_filter('qsot-event-zone-item-data-keys', array(__CLASS__, 'maintain_ticket_link'), 11, 1);
		add_filter('qsot-cart-item-ticket-data', array(__CLASS__, 'add_ticket_link'), 11, 2);
		add_filter('qsot-generate-ticket-code', array(__CLASS__, 'generate_ticket_code'), 10, 4);
		add_filter('qsot-get-ticket-info-from-ticket-code', array(__CLASS__, 'get_ticket_info_from_ticket_code'), 10, 2);
		add_action('woocommerce_checkout_update_order_meta', array(__CLASS__, 'post_order_creation_ticket_link'), 10, 2);

		add_filter('woocommerce_cart_item_name', array(__CLASS__, 'change_order_page_link'), 10, 2);
		add_filter('qsot-item-meta', array(__CLASS__, 'special_item_meta'), 100, 8);
		add_filter('woocommerce_hidden_order_itemmeta', array(__CLASS__, 'hide_product_meta'), 10, 1);
		add_filter('qsot-cancelled-order-item-meta-to-remove', array(__CLASS__, 'mark_item_meta_for_deletion'), 10, 6);
		add_action('plugins_loaded', array(__CLASS__, 'plugins_loaded'), 11);
		add_action('wp', array(__CLASS__, 'intercept_ticket_request'), 11);
		add_filter('query_vars', array(__CLASS__, 'query_vars'), 10);
		add_filter('rewrite_rules_array', array(__CLASS__, 'rewrite_rules_array'), PHP_INT_MAX);

		add_filter('qsot-ticket-verification-form-check', array(__CLASS__, 'basic_ticket_verification'), 10, 2);
	}

	public static function plugins_loaded() {
		self::_load_templates();
	}

	public static function change_order_page_link($current, $item) {
		if (isset($item['ticket_link'], $item['name'])) {
			$current = sprintf(
				'<a href="%s" title="%s" target="_blank">%s</a>',
				site_url($item['ticket_link']),
				'View/Download/Print your ticket',
				$item['name']
			);
		}

		return $current;
	}

	public static function mark_item_meta_for_deletion($list, $item_id, $item, $order_id, $old_status, $new_status) {
		$list[] = '_ticket_link';
		$list[] = '_ticket_code';
		$list[] = '__saved';
		return array_unique($list);
	}

	public static function hide_product_meta($list) {
		$list[] = '_ticket_link';
		$list[] = '_ticket_code';
		$list[] = '__saved';
		return array_unique($list);
	}

	public static function maintain_ticket_link($current) {
		$current = is_array($current) ? $current : array();
		$current[] = 'ticket_link';
		$current[] = 'ticket_code';
		return array_unique($current);
	}

	public static function generate_ticket_code($current, $args='') {
		//$event_id, $sc_id, $zone_id) {
		$args = wp_parse_args($args, array(
			'event_id' => 0,
			'sc_id' => 0,
			'order_id' => 0,
			'order_item_id' => 0,
		));
		extract($args);
		$key = $event_id.'.'.$sc_id.'.'.$order_id.'.'.$order_item_id;
		$key .= '~'.sha1($key.AUTH_KEY);
		$key = str_pad('', 3 - (strlen($key) % 3), '|').$key;
		$ekey = str_replace(array('/', '+'), array('-', '_'), base64_encode($key));

		return $ekey;
	}

	public static function get_ticket_info_from_ticket_code($current, $code) {
		$key = trim(base64_decode(str_replace(array('-', '_'), array('/', '+'), $code)), '|');

		$key = explode('~', $key);
		$hash = array_pop($key);
		$key = implode('~', $key);
		if (!$key || !$hash || $hash != sha1($key.AUTH_KEY)) return array(
			'event_id' => 0,
			'sc_id' => 0,
			'order_item_id' => 0,
			'product_id' => 0,
			'qty' => 0,
			'order_id' => 0,
		);

		@list($event_id, $sc_id, $order_id, $order_item_id) = explode('.', $key);

		$product_id = self::_get_product_id_from_order_item_id($order_item_id);
		$qty = self::_get_quantity_from_order_item_id($order_item_id);

		return array(
			'event_id' => $event_id,
			'sc_id' => $sc_id,
			'order_item_id' => $order_item_id,
			'product_id' => $product_id,
			'qty' => $qty,
			'order_id' => $order_id,
		);
	}

	protected static function _get_quantity_from_order_item_id($order_item_id) {
		if (empty($order_item_id)) return 0;

		global $wpdb;

		$q = $wpdb->prepare('select meta_value from '.$wpdb->prefix.'woocommerce_order_itemmeta where meta_key = %s and order_item_id = %d', '_qty', $order_item_id);
		$result = $wpdb->get_var($q);

		return $result;
	}

	protected static function _get_product_id_from_order_item_id($order_item_id) {
		if (empty($order_item_id)) return 0;

		global $wpdb;

		$q = $wpdb->prepare('select meta_value from '.$wpdb->prefix.'woocommerce_order_itemmeta where meta_key = %s and order_item_id = %d', '_product_id', $order_item_id);
		$result = $wpdb->get_var($q);

		return $result;
	}

	public static function post_order_creation_ticket_link($order_id, $posted) {
		$order = new WC_Order($order_id);
		if (!is_object($order) || $order->id != $order_id) return;

		foreach ($order->get_items('line_item') as $oiid => $item) {
			$_product = $order->get_product_from_item($item);
			if ($_product->ticket == 'yes') {
				$data = $item;
				$data['order_id'] = $order_id;
				$data['product'] = $_product;
				$data['quantity'] = $data['qty'];
				$data['order_item_id'] = $oiid;
				$code = apply_filters('qsot-cart-item-ticket-data', array(), $data);
				if (is_array($code) && isset($code['ticket_code'], $code['ticket_link'])) {
					woocommerce_update_order_item_meta($oiid, '_ticket_code', $code['ticket_code']);
					woocommerce_update_order_item_meta($oiid, '_ticket_link', $code['ticket_link']);
				}
			}
		}
	}

	public static function add_ticket_link($current, $args) {
		//$zone_id, $event_id, $product) {
		$args = wp_parse_args($args, array(
			'event_id' => 0,
			'sc_id' => 0,
			'order_id' => 0,
			'order_item_id' => 0,
		));
		if ($args['order_id'] > 0 && (!isset($current['ticket_code']) || empty($current['ticket_code']))) {
			$args['sc_id'] = absint(get_post_meta($args['event_id'], self::$o->{'meta_key.sc'}, true));
			$ekey = apply_filters('qsot-generate-ticket-code', '', $args);
			$current['ticket_code'] = $ekey;
			$current['ticket_link'] = '/ticket/'.$current['ticket_code'].'/';
		}

		return $current;
	}

  protected static function _token($order_id) {
    $token = '';
    if (is_numeric($order_id))
      $token = @strrev(@md5(@strrev(get_post_meta('_customer_user', $order_id, true).':'.get_post_meta('_billing_email', $order_id, true))));
    return $token;
  }

	public static function special_item_meta($meta_list, $meta_key, $meta_value, $flat, $return, $hideprefix, $all_meta, $oi_obj) {
		$data = array();

		switch ($meta_key) {
			case '_ticket_link':
				$text = 'View your ticket';
				if (isset($all_meta['_product_id'], $all_meta['_line_subtotal'])) {
					$p = get_product($all_meta['_product_id'][0]);
					$p->price = $all_meta['_line_subtotal'][0];
					$p->price_display = money_format('%.2n', $p->price);
					$text = $p->post->post_title.' @ '.$p->price_display;
				}
        $token = is_object($oi_obj) && is_object($oi_obj->order) ? self::_token($oi_obj->order->ID) : '';
        $url = !empty($token) ? add_query_arg(array('n' => $token), site_url($meta_value[0])) : site_url($meta_value[0]);
				$data = array(
					'name' => 'Ticket',
					'value' => $meta_value[0],
          'display' => '<a href="'.esc_attr($url).'" target="_blank" title="View your ticket">'.$text.'</a>',
				);
			break;
		}

		if (!empty($data)) {
			if ($flat) $meta_list[] = $data['name'].': '.$data['display'];
			else $meta_list[] = '<dt>'.$data['name'].':</dt><dd>'.$data['display'].'</dd>';
		}

		return $meta_list;
	}

	protected static function _user_id_from_ticket_code($code) {
		global $wpdb;

		$q = $wpdb->prepare('select order_item_id from '.$wpdb->prefix.'woocommerce_order_itemmeta where meta_key = %s and meta_value = %s', '_ticket_code', $code);
		$order_item_id = $wpdb->get_var($q);

		$order_id = 0;
		if (!empty($order_item_id)) {
			$q = $wpdb->prepare('select order_id from '.$wpdb->prefix.'woocommerce_order_items where order_item_id = %d', $order_item_id);
			$order_id = $wpdb->get_var($q);
		}

		return (int)get_post_meta($order_id, '_customer_user', true);
	}

	public static function display_ticket($code) {
		/*
		if (!is_user_logged_in()) {
			$url = site_url($_SERVER['REQUEST_URI']).(!empty($_SERVER['QUERY_STRING']) ? '?'.$_SERVER['QUERY_STRING'] : '');
			wp_safe_redirect(wp_login_url($url));
			exit;
		}

		if (empty($user_id)) $user = wp_get_current_user();
		else $user = new WP_User($user_id);

		$owner = $user;
		$owner->meta = array();
		$meta = get_user_meta($owner->ID);
		foreach ($meta as $k => $v) $meta[$k] = array_shift($v);
		$owner->meta = $meta;
		*/

		$info = apply_filters('qsot-get-ticket-info-from-ticket-code', array(), $code);
		$event_id = $info['event_id'];
		$sc_id = $info['sc_id'];
		$ticket_id = $info['zone_id'];
		$order_id = $info['order_id'];
    $N = self::_token($order_id);
		//$user_id = self::_user_id_from_ticket_code($code);

		//$u = wp_get_current_user();
		//if ($u->ID != $user_id && !current_user_can('edit_users')) return;

		$ticket = apply_filters('qsot-get-event-zone-ticket-info', false, array(
			'event_id' => $event_id,
			'sc_id' => $sc_id,
			'zone_id' => $ticket_id,
			'order_id' => $order_id,
			'product_id' => $info['product_id'],
		)); //, $user_id);
		$ticket->quantity = $info['qty'];
		if (!is_object($ticket) || !isset($ticket->event)) {
			self::_display_ticket_error(array('msg' => 'This ticket link is invalid.'));
			exit();
		}

		$guest_checkout = strtolower(get_option('woocommerce_enable_guest_checkout', 'no')) == 'yes';

    if (!isset($_GET['n']) || $_GET['n'] != $N) {
			$u = wp_get_current_user();
			if (!empty($u->ID)) {
				if (isset($ticket->owner) && is_object($ticket->owner) && $ticket->owner->ID != $u->ID && !current_user_can('edit_user', $ticket->owner->ID)) {
					self::_display_ticket_error(array('msg' => 'You do not have permission to view this ticket.'));
					exit();
				}
			} else if (!$guest_checkout) {
				self::_display_login_form();
				exit();
			} else if (!isset($_POST['verification_form'])) {
				self::_display_ticket_verification_form();
				exit();
			} else if (!apply_filters('qsot-ticket-verification-form-check', false, $ticket)) {
				self::_display_ticket_error(array('msg' => 'The information you provided does not match the information we have on record; therefore, the ticket will not be displayed.'));
				exit();
			}
		}

		$template = apply_filters('qsot-get-event-ticket-template', false, $event_id);
		$stylesheet = apply_filters('qsot-get-event-ticket-stylesheet', false, $event_id);

		if (is_object($ticket) && !empty($template) && !empty($stylesheet)) {
			$out = self::_get_ticket_html(array('ticket' => $ticket, 'template' => $template, 'stylesheet' => $stylesheet));

			$_GET = wp_parse_args($_GET, array('frmt' => 'html'));
			switch ($_GET['frmt']) {
				case 'pdf':
					$title = $ticket->own->price['name'].' ('.$ticket->own->price['price_display'].')';
					self::_print_pdf($out, $title);
				break;
				default: echo $out; break;
			}

			exit();
		} else {
			self::_display_ticket_error(array('msg' => 'This ticket link is invalid.'));
			exit();
		}
	}

	public static function basic_ticket_verification($current, $ticket) {
		return isset($_POST['email'], $ticket->order) && $_POST['email'] == $ticket->order->billing_email;
	}

	protected static function _display_ticket_error($data) {
		$template = apply_filters('qsot-locate-template', '', array('tickets/error-msg.php'));
		if (!empty($template)) {
			extract($data);
			include $template;
		}
	}

	protected static function _display_ticket_verification_form() {
		$template = apply_filters('qsot-locate-template', '', array('tickets/verification-form.php'));
		if (!empty($template)) {
			include $template;
		}
	}

	protected static function _display_login_form() {
		$redirect = site_url($_SERVER['REQUEST_URI']).(!empty($_SERVER['QUERY_STRING']) ? '?'.$_SERVER['QUERY_STRING'] : '');
		$template = apply_filters('qsot-locate-template', '', array('tickets/form-login.php'));
		if (!empty($template)) {
			include $template;
		}
	}

	protected static function _get_ticket_html($args) {
		extract($args);
		ob_start();
		wp_enqueue_style('qsot-ticket-style', $stylesheet, array(), self::$o->version);
		include_once $template;
		$out = ob_get_contents();
		ob_end_clean();
		return $out;
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

	public static function get_event_ticket_template($current, $event_id) {
		$name = get_post_meta($event_id, self::$o->{'tk.mk.tmpl'}, true);
		return isset(self::$templates[$name]) ? self::$templates[$name] : self::$templates['_Default'];
	}

	public static function get_event_ticket_stylesheet($current, $event_id) {
		$name = get_post_meta($event_id, self::$o->{'tk.mk.style'}, true);
		return isset(self::$stylesheets[$name]) ? self::$stylesheets[$name] : self::$stylesheets['_Default'];
	}

	protected static function _load_templates() {
		self::$templates = self::_load_templates_label_by('template name', '#^.+\.php$#i');
		self::$stylesheets = self::_load_templates_label_by('style name', '#^.+\.css$#i', true);
	}

	protected static function _load_templates_label_by($label, $file_regex, $url_form=false, $extra_path='') {
		$final_list = array();

		$dirs = apply_filters('qsot-ticket-template-dirs', array(
			self::$o->core_dir.'templates/tickets/'.$extra_path,
			get_template_directory().'/templates/tickets/'.$extra_path,
			get_stylesheet_directory().'/templates/tickets/'.$extra_path,
		), $extra_path);
		
		foreach ($dirs as $dir) {
			if (file_exists($dir)) {
				$list = new RegexIterator(
					new RecursiveIteratorIterator(
						new RecursiveDirectoryIterator($dir),
						RecursiveIteratorIterator::SELF_FIRST
					),
					$file_regex,
					RecursiveRegexIterator::GET_MATCH
				);
				foreach ($list as $file) {
					$file = array_shift($file);
					$header = file_get_contents($file, false, null, 0, 2048);
					preg_match_all('#\/\*(.*)\*\/#is', $header, $comments);

					foreach ($comments[1] as $header) {
						preg_match_all('#^([^:]*?)(:(.*?))?$#', trim($header), $matches, PREG_SET_ORDER);

						$headers = array();
						$last_header = '';

						foreach ($matches as $match) {
							if (isset($match[1], $match[3])) {
								$k = str_replace('-', ' ', sanitize_title_with_dashes(trim($match[1], "* \t\n\r\b\0")));
								$v = trim($match[3], "* \t\r\n\b\0");
								$headers[$k] = $v;
								$last_header = $k;
							} else if (isset($match[1])) {
								$headers[$last_header] .= ' '.trim($match[1], "* \t\n\r\b\0");
							}
						}

						if (isset($headers[$label])) {
							$final_list[$headers[$label]] = $url_form ? str_replace(trailingslashit(ABSPATH), trailingslashit(site_url()), $file) : $file;
						}
					}
				}
			}
		}

		return apply_filters('qsot-load-templates-label-by', $final_list, $label, $file_regex, $extra_path);
	}

	public static function intercept_ticket_request(&$wp) {
		if (isset($wp->query_vars['qsot-ticket'], $wp->query_vars['qsot-ticket-id']) && $wp->query_vars['qsot-ticket'] == 1) {
			$code = $wp->query_vars['qsot-ticket-id'];
			do_action('qsot-ticket-intercepted', $code);
		}

		if (isset($wp->query_vars['qsot-event-checkin'], $wp->query_vars['qsot-checkin-packet']) && $wp->query_vars['qsot-event-checkin'] == 1) {
			$packet = $wp->query_vars['qsot-checkin-packet'];
			do_action('qsot-event-checkin-intercepted', self::_parse_checkin_packet($packet), $packet);
		}
	}

	public static function query_vars($vars) {
		$new_items = array(
			'qsot-ticket',
			'qsot-ticket-id',
		);

		return array_unique(array_merge($vars, $new_items));
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

		if (isset($_COOKIE['rwdebug']) && $_COOKIE['rwdebug'] == 1) {
			add_action('admin_footer', array(__CLASS__, 'footer_debug_rewrite'));
		}

		return $extra + $current;
	}

	public static function footer_debug_rewrite() {
		if (isset($_COOKIE['rwdebug']) && $_COOKIE['rwdebug'] == '1') {
			global $wp_rewrite;
			echo '<pre>';
			print_r($wp_rewrite->rules);
			echo '</pre>';
		}
	}
}

if (defined('ABSPATH') && function_exists('add_action')) {
	qsot_event_tickets::pre_init();
}
