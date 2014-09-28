<?php (__FILE__ == $_SERVER['SCRIPT_FILENAME']) ? die(header('Location: /')) : null;

class qsot_order_admin {
	// holder for event plugin options
	protected static $o = null;
	protected static $options = null;

	public static function pre_init() {
		$settings_class_name = apply_filters('qsot-settings-class-name', '');
		if (!empty($settings_class_name)) {
			self::$o = call_user_func_array(array($settings_class_name, "instance"), array());

			// load all the options, and share them with all other parts of the plugin
			$options_class_name = apply_filters('qsot-options-class-name', '');
			if (!empty($options_class_name)) {
				self::$options = call_user_func_array(array($options_class_name, "instance"), array());
				self::_setup_admin_options();
			}

			add_action('init', array(__CLASS__, 'register_assets'), 5000);
			add_action('qsot-admin-load-assets-shop_order', array(__CLASS__, 'load_assets'), 5000, 2);
			add_filter('woocommerce_found_customer_details', array(__CLASS__, 'add_default_country_state'), 10, 1);

			add_action('save_post', array(__CLASS__, 'enforce_non_guest_orders'), PHP_INT_MAX - 1, 2);
			add_action('admin_notices', array(__CLASS__, 'cannot_use_guest'), 10);

			add_action('plugins_loaded', array(__CLASS__, 'plugins_loaded'), 10000);

			add_action('admin_notices', array(__CLASS__, 'generic_errors'), 10);
			add_filter('qsot-order-can-accept-payments', array(__CLASS__, 'block_payments_for_generic_errors'), 10, 2);
			add_filter('qsot-admin-payments-error', array(__CLASS__, 'block_payments_generic_error_message'), 10, 2);

			add_action('save_post', array(__CLASS__, 'require_billing_information'), 9999, 2);
			add_action('save_post', array(__CLASS__, 'reset_generic_errors'), 0, 2);

			add_action('woocommerce_completed_order_email_after_address', array(__CLASS__, 'print_custom_email_message'), 1);
			add_filter('qsot-order-has-tickets', array(__CLASS__, 'has_tickets'), 10, 2);
		}
	}

	public static function plugins_loaded() {
		if (current_user_can('create_users')) {
			add_action('woocommerce_after_customer_user', array(__CLASS__, 'add_new_user_button'), 10, 3);
			add_action('woocommerce_admin_order_data_after_order_details', array(__CLASS__, 'new_user_btn'), 10, 1);
			add_action('wp_ajax_qsot-new-user', array(__CLASS__, 'admin_new_user_handle_ajax'), 10);
		}
	}

	public static function register_assets() {
		if (QSOT::is_wc_latest()) {
			wp_register_script('qsot-new-user', self::$o->core_url.'assets/js/admin/order/new-user.js', array('jquery-ui-dialog', 'qsot-tools', 'wc-admin-meta-boxes'), self::$o->version);
		} else {
			wp_register_script('qsot-new-user', self::$o->core_url.'assets/js/admin/order/new-user.js', array('jquery-ui-dialog', 'qsot-tools', 'woocommerce_admin_meta_boxes'), self::$o->version);
		}
	}

	public static function load_assets($exists, $post_id) {
		// load the eit page js, which also loads all it's dependencies
		wp_enqueue_script('qsot-new-user');
		wp_localize_script('qsot-new-user', '_qsot_new_user', apply_filters('qsot-new-user-settings', array(
			'order_id' => $post_id,
			'templates' => self::_new_user_ui_templates($post_id), // all templates used by the ui js
		), $post_id));
	}

	// draw the new user button as soon as possible on the order data metabox
	public static function new_user_btn($order) {
		?><script language="javascript" type="text/javascript">
			if (typeof jQuery == 'object' || typeof jQuery == 'function')
				(function($) { $('<a href="#" class="new-user-btn" rel="new-user-btn">new</a>').appendTo('.order_data_column .form-field label[for="customer_user"]'); })(jQuery);
		</script><?php
	}

	public static function add_default_country_state($data) {
		list($country, $state) = explode(':', get_option('woocommerce_default_country', '').':');

		foreach ($data as $k => $v) {
			if (preg_match('#_country$#', $k) && isset($country) && !empty($country) && empty($v)) $data[$k] = $country;
			elseif (preg_match('#_state$#', $k) && isset($state) && !empty($state) && empty($v)) $data[$k] = $state;
		}

		return $data;
	}

	protected static function _update_errors($errors, $order_id) {
		$errors = is_scalar($errors) ? array($errors) : $errors;
		$errors = !is_array($errors) ? array() : $errors;
		$current = get_post_meta($order_id, '_generic_errors', true);
		if (!empty($current)) array_unshift($errors, $current);
		update_post_meta($order_id, '_generic_errors', implode('<br/>', $errors));
	}

	public static function reset_generic_errors($post_id, $post) {
		if ($post->post_type != 'shop_order') return;
		update_post_meta($post_id, '_generic_errors', '');
	}

	public static function generic_errors() {
		$post = get_post();

		// must be shop order
		if (!is_object($post) || !isset($post->post_type) || $post->post_type != 'shop_order') return;

		if ($errors = get_post_meta($post->ID, '_generic_errors', true)) {
			?>
				<div class="error"><p><?php echo $errors ?></p></div>
			<?php
		}
	}

	public static function block_payments_for_generic_errors($pass, $post) {
		// must be shop order
		if ($post->post_type != 'shop_order') return $pass;
		
		$this_pass = !((bool)get_post_meta($post->ID, '_generic_errors', true));

		return !(!$pass || !$this_pass);
	}

	public static function block_payments_generic_error_message($msg, $post) {
		// must be shop order
		if ($post->post_type != 'shop_order') return $msg;

		// if the payment is not being blocked by error messages, then dont change the existing message
		if (!get_post_meta($post->ID, '_generic_errors', true)) return $msg;

		return $msg.' '.get_post_meta($post->ID, '_generic_errors', true);
	}

	public static function cannot_use_guest() {
		$post = get_post();

		// must be shop order
		if (!is_object($post) || !isset($post->post_type) || $post->post_type != 'shop_order') return;

		// restrict for everyone except those who can manage woocommerce settings (ie: administrators)
		if (current_user_can('manage_woocommerce')) return;

		if (get_post_meta($post->ID, '_use_guest_attempted', true)) {
			?>
				<div class="error">
					<p>
						The current settings disallow using '<strong>Guest</strong>' as the customer for the order.
						You have attempted to use '<strong>Guest</strong>' as the customer.
						You will not be able to process payments or complete an order until a user has been selected as the customer.
					</p>
				</div>
			<?php
		}
	}

	public static function block_payments_for_guest_orders($pass, $post) {
		// must be shop order
		if ($post->post_type != 'shop_order') return $pass;

		// if guest checkout is active, this does not apply
		if (get_option('woocommerce_enable_guest_checkout', 'no') == 'yes') return $pass;

		// restrict for everyone except those who can manage woocommerce settings (ie: administrators)
		if (current_user_can('manage_woocommerce')) return $pass;
		
		$this_pass = !((bool)get_post_meta($post->ID, '_use_guest_attempted', true));

		return !(!$pass || !$this_pass);
	}

	public static function block_payments_error_message($msg, $post) {
		// must be shop order
		if ($post->post_type != 'shop_order') return $msg;

		// if guest checkout is active, this does not apply
		if (get_option('woocommerce_enable_guest_checkout', 'no') == 'yes') return $msg;

		// restrict for everyone except those who can manage woocommerce settings (ie: administrators)
		if (current_user_can('manage_woocommerce')) return $msg;

		// if the payment is not being blocked by the guest setting, then dont change the existing message
		if (!get_post_meta($post->ID, '_use_guest_attempted', true)) return $msg;

		return $msg.' Additionally, because of the current Woocommerce settings, "<strong>Guest</strong>" is not allowed as the customer user. Please select a user first.';
	}

	public static function enforce_non_guest_orders($post_id, $post) {
		// must be shop order
		if ($post->post_type != 'shop_order') return;

		// if guest checkout is active, this does not apply
		if (get_option('woocommerce_enable_guest_checkout', 'no') == 'yes') return;

		// restrict for everyone except those who can manage woocommerce settings (ie: administrators)
		if (current_user_can('manage_woocommerce')) return;

		// if the guest checkout is disabled and the admin is attempting to use a guest user, then flag the order, which is later used to limit payment and pop an error
		if (isset($_POST['customer_user'])) {
			$current = get_post_meta($post_id, '_customer_user', true);
			if (empty($current)) {
				update_post_meta($post_id, '_use_guest_attempted', 1);

				remove_action('woocommerce_process_shop_order_meta', 'woocommerce_process_shop_order_meta', 10);
				remove_action('save_post', array(__CLASS__, 'enforce_non_guest_orders'), PHP_INT_MAX);
				remove_action('save_post', array(__CLASS__, 'require_billing_information'), 9999);
				do_action('qsot-before-guest-check-update-order-status', $post);
				$order = new WC_Order($post_id);
				$order->update_status('pending', 'You cannot use "Guest" as the owner of the order, due to current Woocommerce settings.');
				add_action('woocommerce_process_shop_order_meta', 'woocommerce_process_shop_order_meta', 10, 2);
				add_action('save_post', array(__CLASS__, 'enforce_non_guest_orders'), PHP_INT_MAX, 2);
				add_action('save_post', array(__CLASS__, 'require_billing_information'), 9999, 2);
			} else {
				update_post_meta($post_id, '_use_guest_attempted', 0);
			}
		}
	}

	public static function require_billing_information($post_id, $post) {
		// must be shop order
		if ($post->post_type != 'shop_order') return;

		// only perform this check if the associated option is on
		if (self::$options->{'qsot-require-billing-information'} != 'yes') return;

		// only when the past is being saved in the admin
		if (!isset($_POST['action']) || $_POST['action'] != 'editpost') return;

		$errors = array();
		$fields = array( // all fields are pre-filtered with trim and a regex that replaces multiple spaces with 1 space (same with dashes)
			'_billing_first_name' => array(
				'#^([\w\d][\-\w\d\s\&\.\']+)$#' => 'must be at least 2 letters or numbers', // at least 2 letters or numbers
			),
			'_billing_last_name' => array(
				'#^([\w\d][\-\w\d\s\&\.\']+)$#' => 'must be at least 2 letters or numbers', // at least 2 letters or numbers
			),
			'_billing_address_1' => array(
				'#^(.{5,})$#' => 'must be at least 7 letters, numbers, or spaces', // at least 7 letters numbers and spaces, beginning and ending with letter or number
			),
			'_billing_city' => array(
				'#^([\w\d][\w\d\s]{2,}[\w\d])$#' => 'must be at least 4 letters, numbers, or spaces', // at least 4 letters numbers and spaces, beginning and ending with letter or number
			),
			'_billing_postcode' => array(
				'#^([\w\d][\w\d\-]{5,}[\w\d])$#' => 'must be at least 5 letters, numbers, or spaces', // at least 5 letters numbers and dashes, beginning and ending with letter or number
			),
			'_billing_country' => array(
				'#^([\w\d][\w\d\s]*?[\w\d])$#' => 'must be at least 2 letters, numbers, or spaces', // at least 3 letters numbers and spaces, beginning and ending with letter or number
			),
			/* not valid for international
			'_billing_state' => array(
				'#^([\w\d][\w\d\s]*?[\w\d])$#' => 'must be at least 2 letters, numbers, or spaces', // at least 2 letters numbers and spaces, beginning and ending with letter or number
			),
			*/
			'_billing_email' => array(
				'#^([\w\d].+[\w])$#' => 'must be at least 3 letters, numbers, or spaces', // at least 3 characters long, beginning with letter or number and ending with letter
				'functions' => array(
					array(
						'func' => 'is_email',
						'msg' => 'must be a valid email',
					),
				),
			),
			'_billing_phone' => array(
				'functions' => array(
					array(
						'func' => array(__CLASS__, 'is_phone'),
						'msg' => 'must be a valid phone number',
					),
				),
			),
		);

		foreach ($fields as $k => $rules) {
			$name = ucwords(trim(preg_replace('#_+#', ' ', $k)));
			$value = isset($_POST[$k]) ? $_POST[$k] : '';
			$value = trim(preg_replace('#\s+#', ' ', preg_replace('#\-+#', '-', $value)));
			$msgs = array();

			foreach ($rules as $rule_key => $msg) {
				if ($rule_key == 'functions') {
					foreach ($msg as $rule) {
						$func = $rule['func'];
						$m = $rule['msg'];
						if (is_callable($func) && !call_user_func($func, $value)) $msgs[] = $m.' '.$value;
					}
				} else {
					if (!preg_match($rule_key, $value)) $msgs[] = $msg.' '.$value;
				}
			}

			if ($msgs) {
				$errors[] = '- '.$name.' '.implode(', ', $msgs);
			}
		}

		if (!empty($errors)) {
			self::_update_errors($errors, $post_id);

			remove_action('woocommerce_process_shop_order_meta', 'woocommerce_process_shop_order_meta', 10);
			remove_action('save_post', array(__CLASS__, 'enforce_non_guest_orders'), PHP_INT_MAX);
			remove_action('save_post', array(__CLASS__, 'require_billing_information'), 9999);
			do_action('qsot-before-guest-check-update-order-status', $post);
			$order = new WC_Order($post_id);
			$order->update_status('pending', 'Your current settings require you to provide most billing information for each order.');
			add_action('woocommerce_process_shop_order_meta', 'woocommerce_process_shop_order_meta', 10, 2);
			add_action('save_post', array(__CLASS__, 'enforce_non_guest_orders'), PHP_INT_MAX, 2);
			add_action('save_post', array(__CLASS__, 'require_billing_information'), 9999, 2);
		}
	}

	public static function is_phone($number) {
		$compare = preg_replace('#[\(\)\-\.]#', '', $number);
		return strlen($compare) >= 7 && preg_match('#^\d+$#', $compare);
	}

	public static function add_new_user_button($customer_user, $post, $post_id) {
		?>
			<a href="#" class="new-user" rel="new-user-btn">New User</a>
		<?php
	}

	public static function admin_new_user_handle_ajax() {
		switch ($_POST['sa']) {
			case 'create': self::_aj_new_user_create(); break;
			default: do_action('qsot-new-user-ajax-'.$_POST['sa']); break;
		}

		exit();
	}

	protected static function _aj_new_user_create() {
		$res = array(
			's' => false,
			'e' => array(),
			'm' => array(),
			'c' => array(),
		);

		$username = trim($_POST['new_user_login']);
		$email = trim(urldecode($_POST['new_user_email']));
		$first_name = trim($_POST['new_user_first_name']);
		$last_name = trim($_POST['new_user_last_name']);

		if (get_option('woocommerce_registration_email_for_username', 'no') == 'no') {
			if (empty($username)) {
				$res['e'][] = 'The username is a required field.';
			} else if (!validate_username($username)) {
				$res['e'][] = 'That user name contains illegal characters.';
			} else if (username_exists($username)) {
				$res['e'][] = $username.' is already being used by another user. Please enter a different username.';
			}
		}

		if (empty($email)) {
			$res['e'][] = 'The email address is required.';
		} else if (!is_email($email)) {
			$res['e'][] = 'That is not a valid email address.';
		} else if (email_exists($email)) {
			$res['e'][] = $email.' is already in use by another user. Please use a different email address.';
		}

		if (empty($first_name)) {
			$res['e'][] = 'The first name is a required field.';
		}

		if (empty($last_name)) {
			$res['e'][] = 'The last name is a required field.';
		}

		if (empty($res['e'])) {
			$res['m'][] = 'The information you supplied passed validation.';
			$user_info = array(
				'user_login' => get_option('woocommerce_registration_email_for_username', 'no') == 'yes' ? $email : $username,
				'user_email' => $email,
				'user_pass' => self::_random_pass(8),
				'first_name' => $first_name,
				'last_name' => $last_name,
				'display_name' => $first_name.' '.$last_name,
				'role' => 'customer',
			);
			$user_id = wp_insert_user($user_info);
			if (is_wp_error($user_id)) {
				$res['e'][] = 'User creation failed: '.$user_id->get_error_message();
			} else {
				$res['s'] = true;
				$res['m'][] = 'The user was created successfully.';
				$user = new WP_User($user_id);
				$res['c']['id'] = $user_id;
				$res['c']['displayed_value'] = sprintf('%s %s (#%d - %s)', $first_name, $last_name, $user_id, $email);
				wp_new_user_notification($user_id, $user_info['user_pass']);
				update_user_meta($user_id, 'billing_first_name', $first_name);
				update_user_meta($user_id, 'billing_last_name', $last_name);
				update_user_meta($user_id, 'billing_email', $email);
			}
		}

		header('Content-Type: text/json');
		echo @json_encode($res);
	}

	protected static function _random_pass($length) {
		$pool = array(
			'lets' => 'abcdefghijklmnopqrstuvwxyz',
			'ulets' => 'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
			'nums' => '0123456789',
			'symbols' => '-_$',
		);
		$pool = implode('', array_values($pool));
		$poollen = strlen($pool);

		$pswd = '';
		for ($i=0; $i<absint($length); $i++) $pswd .= substr($pool, rand(0, $poollen-1), 1);

		return $pswd;
	}

	protected static function _new_user_ui_templates($post_id) {
		$list = array();

		$list['new-user-form'] = '<div class="new-user-form-wrapper" title="New User Form">'
				.'<style>'
					.'.new-user-form-wrapper .messages { font-size:10px; font-weight:700; font-style:italic; } '
					.'.new-user-form-wrapper .messages > div { padding:2px 5px; margin-bottom:3px; border:1px solid #880000; border-radius:5px; } '
					.'.new-user-form-wrapper .messages .err { color:#880000; background-color:#ffeeee; border-color:#880000; } '
					.'.new-user-form-wrapper .messages .msg { color:#000088; background-color:#eeeeff; border-color:#000088; } '
				.'</style>'
				.'<div class="messages" rel="messages"></div>'
				.(get_option('woocommerce_registration_email_for_username', 'no') == 'yes'
					? ''
					: '<div class="field">'
							.'<label for="new_user_login">Username</label>'
							.'<input class="widefat" type="test" name="new_user_login" id="new_user_login" rel="new-user-login" value="" />'
						.'</div>')
				.'<div class="field">'
					.'<label for="new_user_email">Email</label>'
					.'<input class="widefat" type="email" name="new_user_email" id="new_user_email" rel="new-user-email" value="" />'
				.'</div>'
				.'<div class="field">'
					.'<label for="new_user_first_name">First Name</label>'
					.'<input class="widefat" type="text" name="new_user_first_name" id="new_user_first_name" rel="new-user-first-name" value="" />'
				.'</div>'
				.'<div class="field">'
					.'<label for="new_user_last_name">Last Name</label>'
					.'<input class="widefat" type="text" name="new_user_last_name" id="new_user_last_name" rel="new-user-last-name" value="" />'
				.'</div>'
			.'</div>';

		return apply_filters('qsot-new-user-templates', $list, $post_id);
	}

	public static function has_tickets($current, $order) {
		if (!is_object($order)) return $current;
		
		$has = false;

		foreach ($order->get_items() as $item) {
			$product = $order->get_product_from_item($item);
			if ($product->ticket == 'yes') {
				$has = true;
				break;
			}
		}

		return $has;
	}

	public static function print_custom_email_message($order, $html=true) {
		$print = apply_filters('qsot-order-has-tickets', false, $order);
		if ($print) {
			if ($html) {
				$msg = self::$options->{'qsot-completed-order-email-message'};
				if (!empty($msg)) echo '<div class="custom-email-message">'.$msg.'</div>';
			} else {
				$msg = self::$options->{'qsot-completed-order-email-message-text'};
				if (!empty($msg)) echo "\n****************************************************\n\n".$msg;
			}
		}
	}

	protected static function _get_order_item($id, $order_id=0) {
		global $wpdb;
		$res = array();

		if (empty($order_id)) {
			$t = $wpdb->prefix.'woocommerce_order_items';
			$q = $wpdb->prepare('select order_id from '.$t.' where order_item_id = %d', $id);
			$order_id = $wpdb->get_var($q);
		}
		if (is_numeric($order_id) && $order_id > 0) {
			$order = new WC_Order($order_id);
			$items = $order->get_items(array('line_item', 'fee'));
			if (isset($items[$id])) {
				$res = $items[$id];
				$res['__order_id'] = $order_id;
				$res['__order_item_id'] = $id;
			}
		}

		return $res;
	}

	protected static function _setup_admin_options() {
		self::$options->def('qsot-require-billing-information', 'yes');
		self::$options->def('qsot-completed-order-email-message', '');
		self::$options->def('qsot-completed-order-email-message-text', '');

		self::$options->add(array(
			'order' => 2100,
			'type' => 'title',
			'title' => __('Additional Admin Based Order Validation', 'qsot'),
			'id' => 'heading-admin-orders-1',
		));

		self::$options->add(array(
			'order' => 2131,
			'id' => 'qsot-completed-order-email-message',
			'type' => 'textarea',
			'class' => 'widefat reason-list',
			'title' => __('Custom Completed Order Message', 'qsot'),
			'desc' => __('This html appears at the bottom of the default Completed Order email, sent to the customer upon completion of their order, below their address information.', 'qsot'),
			'default' => '',
		));

		self::$options->add(array(
			'order' => 2132,
			'id' => 'qsot-completed-order-email-message-text',
			'type' => 'textarea',
			'class' => 'widefat reason-list',
			'title' => __('Custom Completed Order Message - Text only version', 'qsot'),
			'desc' => __('This text appears at the bottom of the default Completed Order email, sent to the customer upon completion of their order, below their address information.', 'qsot'),
			'default' => '',
		));

		self::$options->add(array(
			'order' => 2199,
			'type' => 'sectionend',
			'id' => 'heading-admin-orders-1',
		));
	}
}

if (defined('ABSPATH') && function_exists('add_action')) {
	qsot_order_admin::pre_init();
}
