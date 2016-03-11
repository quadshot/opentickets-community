<?php if ( __FILE__ == $_SERVER['SCRIPT_FILENAME'] ) die( header( 'Location: /') );

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

			add_action('admin_notices', array(__CLASS__, 'generic_errors'), 10);
			add_filter('qsot-order-can-accept-payments', array(__CLASS__, 'block_payments_for_generic_errors'), 10, 2);
			add_filter('qsot-admin-payments-error', array(__CLASS__, 'block_payments_generic_error_message'), 10, 2);

			add_action('save_post', array(__CLASS__, 'reset_generic_errors'), 0, 2);
			add_action('save_post', array(__CLASS__, 'require_billing_information'), 999999, 2);
			add_action('save_post', array(__CLASS__, 'enforce_non_guest_orders'), PHP_INT_MAX - 1, 2);
			add_action('admin_notices', array(__CLASS__, 'cannot_use_guest'), 10);

			// add messages to the completed order email only
			add_action( 'woocommerce_email_subject_customer_completed_order', array( __CLASS__, 'add_completed_order_email_messages' ), 1, 1 );
			add_filter('qsot-order-has-tickets', array(__CLASS__, 'has_tickets'), 10, 2);

			// add the new user button to the interface
			add_action( 'wp_ajax_qsot-new-user', array( __CLASS__, 'admin_new_user_handle_ajax' ), 10 );
			add_action( 'woocommerce_admin_order_data_after_order_details', array( __CLASS__, 'new_user_btn' ), 10, 1 );
		}
	}

	public static function register_assets() {
		if (QSOT::is_wc_latest()) {
			wp_register_script('qsot-new-user', self::$o->core_url.'assets/js/admin/order/new-user.js', array('jquery-ui-dialog', 'qsot-tools', 'wc-admin-meta-boxes'), self::$o->version);
			wp_register_script( 'qsot-order-metaboxes', self::$o->core_url.'assets/js/admin/order/metaboxes.js', array( 'qsot-tools', 'wc-admin-meta-boxes' ), self::$o->version );
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

		// take over some of the metabox javascript actions
		wp_enqueue_script( 'qsot-order-metaboxes' );
	}

	// draw the new user button as soon as possible on the order data metabox
	public static function new_user_btn($order) {
		?><script language="javascript" type="text/javascript">
			if (typeof jQuery == 'object' || typeof jQuery == 'function')
				(function($) {
					var w = $( '<span class="new-user-btn-wrap"></span>' ).appendTo( '.order_data_column .form-field label[for="customer_user"]' );
					$( '<a href="#" class="new-user-btn" rel="new-user-btn">new</a>' ).appendTo( w );
					$( '<span class="divider"> | </span>' ).appendTo( w );
				})(jQuery);
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
		static $called_for = array();
		if ( isset( $called_for[$post_id.''] ) ) return;
		$called_for[$post_id.''] = 1;
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
						<?php _e('The current settings disallow using "<strong>Guest</strong>" as the customer for the order. You have attempted to use "<strong>Guest</strong>" as the customer. You will not be able to process payments or complete an order until a user has been selected as the customer.','opentickets-community-edition') ?>
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

		return $msg.' '.__('Additionally, because of the current Woocommerce settings, "<strong>Guest</strong>" is not allowed as the customer user. Please select a user first.','opentickets-community-edition');
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

				self::_remove_recursive_filters();
				self::_disable_emails();

				do_action('qsot-before-guest-check-update-order-status', $post);
				if ( QSOT::is_wc_latest() ) {
					$order = wc_get_order( $post_id );
					$ostatus = $order->get_status();
				} else {
					$order = new WC_Order($post_id);
					$stati = wp_get_object_terms( array( $post_id ), array( 'shop_order_status' ), 'slugs' );
					$ostatus = substr( $ostatus = current( $stati ), 0, 3 ) == 'wc-' ? substr( $ostatus, 3 ) : $ostatus;
				}
				if ( $ostatus != 'pending' ) {
					$order->update_status('pending', __('You cannot use "Guest" as the owner of the order, due to current Woocommerce settings.','opentickets-community-edition'));
				} else {
					$order->add_order_note(__('You cannot use "Guest" as the owner of the order, due to current Woocommerce settings.','opentickets-community-edition'));
				}

				self::_enable_emails();
			} else {
				update_post_meta($post_id, '_use_guest_attempted', 0);
			}
		}
	}

	// determine the value of a piece of meta, based on the POST values and the original values of the order
	protected static function _get_value( $key, $order ) {
		$k = '_' == $key{0} ? substr( $key, 1 ) : $key;

		// if there is a value that was submitted in the POST date
		if ( ! empty( $_POST[ $key ] ) ) {
			return wc_clean( $_POST[ $key ] );
		// otherwise try to find it based on the other available information
		} else {
			// allow any plugins to modify the default value if needed, like on checkout
			$value = apply_filters( 'woocommerce_checkout_get_value', null, $k );
			if ( $value !== null )
				return $value;

			// Get the billing_ and shipping_ address fields
			$address_fields = array_merge( WC()->countries->get_address_fields(), WC()->countries->get_address_fields( '', 'shipping_' ) );

			// see if the order already has values for this field in it's meta. if so, use that
			if ( $meta = get_post_meta( $order->id, $key, true ) )
				return $meta;

			// if the user for the order is set, then try to use their personal data if it exists
			if ( $user_id = get_post_meta( $order->id, '_customer_user', true ) ) {
				if ( $meta = get_user_meta( $user_id, $k, true ) )
					return $meta;

				if ( 'billing_email' == $k ) {
					$u = get_user_by( 'id', $user_id );
					if ( is_object( $u ) )
						return $u->user_email;
				}
			}

			// finally, fallback on defaults if available
			switch ( $k ) {
				case 'billing_country' :
					return apply_filters( 'default_checkout_country', WC()->countries->get_base_country(), 'billing' );
				case 'billing_state' :
					return apply_filters( 'default_checkout_state', '', 'billing' );
				case 'billing_postcode' :
					return apply_filters( 'default_checkout_postcode', '', 'billing' );
				case 'shipping_country' :
					return apply_filters( 'default_checkout_country', WC()->countries->get_base_country(), 'shipping' );
				case 'shipping_state' :
					return apply_filters( 'default_checkout_state', '', 'shipping' );
				case 'shipping_postcode' :
					return apply_filters( 'default_checkout_postcode', '', 'shipping' );
				default :
					return apply_filters( 'default_checkout_' . $k, null, $k );
			}
		}
	}

	public static function require_billing_information($post_id, $post) {
		return; // disable for now until we add this setting back in
		// must be shop order
		if ($post->post_type != 'shop_order') return;

		// only perform this check if the associated option is on
		if (self::$options->{'qsot-require-billing-information'} != 'yes') return;

		// only when the past is being saved in the admin
		if (!isset($_POST['action']) || $_POST['action'] != 'editpost') return;

		// load the order
		$order = wc_get_order( $post_id );
		if ( ! is_object( $order ) || ! isset( $order->id ) ) return;

		// do not perform this check on cancelled orders, because they are irrelevant checks at that point
		if ( 'cancelled' == $order->get_status() ) return;

		// ****** most of this is adapted from the checkout logic from WC2.3.x
		// get all the fields that we should be validating. derived from checkout process
		$fields = WC()->countries->get_address_fields( self::_get_value( '_billing_country', $order ), '_billing_' );
		$errors = array();

		// cycle through each field, and validate the input
		foreach ( $fields as $key => $field ) {
			// make sure we have a field type
			if ( ! isset( $field['type'] ) )
				$field['type'] = 'text';

			// find the submitted value of the field
			switch ( $field['type'] ) {
				// checkboxes are on or off
				case 'checkbox':
					$value = isset( $_POST[ $key ] ) ? 1 : 0;
				break;

				// multiselect boxes have multiple values that need cleaning
				case 'multiselect':
					$value = isset( $_POST[ $key ] ) ? implode( ', ', array_map( 'wc_clean', $_POST[ $key ] ) ) : '';
				break;

				// textareas allow for lots of text, so clean that up
				case 'textarea':
					$value = isset( $_POST[ $key ] ) ? wp_strip_all_tags( wp_check_invalid_utf8( stripslashes( $_POST[ $key ] ) ) ) : '';
				break;

				// all other fields should be cleaned as well
				default:
					$value = isset( $_POST[ $key ] ) ? ( is_array( $_POST[ $key ] ) ? array_map( 'wc_clean', $_POST[ $key ] ) : wc_clean( $_POST[ $key ] ) ) : '';
				break;
			}

			// allow modification of resulting value
			$value = apply_filters( 'woocommerce_process_checkout_' . sanitize_title( $field['type'] ) . '_field', $value );
			$value = apply_filters( 'woocommerce_process_checkout_field_' . $key, $value );

			// check required fields
			if ( isset( $field['required'] ) && $field['required'] && empty( $value ) )
				$error[] = '<strong>' . $field['label'] . '</strong> ' . __( 'is a required field.', 'woocommerce' );

			// some non-empty fields need addtiional validation. handle that here
			if ( ! empty( $value ) ) {
				// cycle through the rules
				if ( isset( $field['validate'] ) ) foreach ( $field['validate'] as $rule ) {
					// process each rule if it is in the list
					switch ( $rule ) {
						// postcodes vary from country to country
						case 'postcode':
							$value = strtoupper( str_replace( ' ', '', $value ) );
							if ( ! WC_Validation::is_postcode( $value, $_POST[ $key ] ) )
								$errors[] = __( 'Please enter a valid postcode/ZIP.', 'woocommerce' );
						break;

						// phone digit count and format varies from country to country
						case 'phone':
							$value = wc_format_phone_number( $value );
							if ( ! WC_Validation::is_phone( $value ) )
								$errors[] = '<strong>' . $field['label'] . '</strong> ' . __( 'is not a valid phone number.', 'woocommerce' );
						break;

						// validate email addresses
						case 'email':
							$value = strtolower( $value );
							if ( ! is_email( $value ) )
								$errors[] = '<strong>' . $field['label'] . '</strong> ' . __( 'is not a valid email address.', 'woocommerce' );
						break;

						// states cound be in different formats or have different values based on the country
						case 'state':
							$states = WC()->countries->get_states( self::_get_value( '_billing_country', $order ) );

							if ( ! empty( $states ) && is_array( $states ) ) {
								$states = array_flip( array_map( 'strtolower', $states ) );
								// look up correct value if key exists
								if ( isset( $states[ strtolower( $value ) ] ) )
									$value = $states[ strtolower( $value ) ];
							}

							if ( ! empty( $states ) && is_array( $states ) && count( $states ) > 0 ) {
								if ( ! in_array( $value, $states ) ) {
									$errors[] = '<strong>' . $field['label'] . '</strong> ' . strtolower( $value ) . ' ' . __( 'is not valid. Please enter one of the following:', 'woocommerce' ) . ' ' . implode( ', ', $states ) . '<pre>' . var_export( $states, true) . '</pre>';
								}
							}
						break;
					}
				}
			}
		}

		if (!empty($errors)) {
			self::_update_errors($errors, $post_id);

			self::_remove_recursive_filters();
			self::_disable_emails();

			do_action('qsot-before-guest-check-update-order-status', $post);

			// if the order is not pending, cancelled or failed, then update the state to pending, so that the admin knows that there is a problem
			if ( ! in_array( $order->get_status(), array( 'pending', 'cancelled', 'failed' ) ) ) {
				$order->update_status( 'pending', __( 'Your current settings require you to provide most billing information for each order.', 'opentickets-community-edition' ) );
			// otherwise, just log a message saying that it is still messed up
			} else {
				$order->add_order_note( __( 'Your current settings require you to provide most billing information for each order.', 'opentickets-community-edition' ), false );
			}

			self::_enable_emails();
			add_action('save_post', array(__CLASS__, 'enforce_non_guest_orders'), PHP_INT_MAX, 2);
		}
	}

	protected static function _remove_recursive_filters() {
		remove_action('woocommerce_process_shop_order_meta', 'woocommerce_process_shop_order_meta', 10);
		remove_action('save_post', array(__CLASS__, 'enforce_non_guest_orders'), PHP_INT_MAX);
		remove_action('save_post', array(__CLASS__, 'require_billing_information'), 999999);
		foreach ( $GLOBALS['wp_filter']['save_post'] as $priority => $func_group )
			foreach ( $func_group as $name => $func_settings )
				if ( is_array( $func = $func_settings['function'] ) && $func[0] instanceof WC_Admin_Meta_Boxes )
					remove_action( 'save_post', $func, $priority );
	}

	// needed for CLI jobs so we can reset the filters that can cause infinite loops, when running batch jobs
	public static function reset_filters() {
		remove_action('woocommerce_process_shop_order_meta', 'woocommerce_process_shop_order_meta', 10);
		remove_action('save_post', array(__CLASS__, 'enforce_non_guest_orders'), PHP_INT_MAX);
		remove_action('save_post', array(__CLASS__, 'require_billing_information'), 999999);
		add_action('woocommerce_process_shop_order_meta', 'woocommerce_process_shop_order_meta', 10, 2);
		add_action('save_post', array(__CLASS__, 'enforce_non_guest_orders'), PHP_INT_MAX, 2);
		add_action('save_post', array(__CLASS__, 'require_billing_information'), 999999, 2);
	}

	public static function is_postcode($code) {
		$compare = preg_replace('#[^0-9a-zA-Z]+#', '', $code);
		return !!preg_match('#^([\w\d][\w\d\-]{3,}[\w\d])$#', $compare);
	}

	public static function is_phone($number) {
		$compare = preg_replace('#[\(\)\-\.]#', '', $number);
		return strlen($compare) >= 7 && preg_match('#^\d+$#', $compare);
	}

	// handle the ajax request that creates new users
	public static function admin_new_user_handle_ajax() {
		// if the current user cannot create users, then bail
		if ( ! current_user_can( 'create_users' ) )
			exit();

		// handle the ajax request depending on the defined SubAction
		switch ( $_POST['sa'] ) {
			// handle the create user action
			case 'create': self::_aj_new_user_create(); break;
			// handle any custom actions defined elsewhere
			default: do_action( 'qsot-new-user-ajax-' .$_POST['sa'] ); break;
		}

		exit();
	}

	// create a new user, using ajax request info
	protected static function _aj_new_user_create() {
		$res = array(
			's' => false,
			'e' => array(),
			'm' => array(),
			'c' => array(),
		);

		// first verify that the current user has permissions to add user
		if ( ! current_user_can( 'create_users' ) ) {
			$res['e'][] = __( 'You do not have permission to create users. Sorry.', 'opentickets-community-edition' );
			wp_send_json( $res );
		}

		// begin sanitizing data
		$username = sanitize_user( trim( $_POST['new_user_login'] ) );
		$email = trim( urldecode( $_POST['new_user_email'] ) );
		$first_name = trim( $_POST['new_user_first_name'] );
		$last_name = trim( $_POST['new_user_last_name'] );

		// if we are not using the email as the username, then
		if ( get_option( 'woocommerce_registration_email_for_username', 'no' ) == 'no' ) {
			// if there is no specified username, then error out to that effect
			if ( empty( $username ) ) {
				$res['e'][] = __( 'The username is a required field.', 'opentickets-community-edition' );
			// if the username is not valid, error out
			} else if ( ! validate_username( $username ) ) {
				$res['e'][] = __( 'That user name contains illegal characters.', 'opentickets-community-edition' );
			// if that username already exists, error out
			} else if ( username_exists( $username ) ) {
				$res['e'][] = $username . ' ' . __( 'is already being used by another user. Please enter a different username.', 'opentickets-community-edition' );
			}
		}

		// if there is no email, error out, because it is required
		if ( empty( $email ) ) {
			$res['e'][] = __( 'The email address is required.', 'opentickets-community-edition' );
		// if the supplied value is NOT a valid email, error out
		} else if ( ! is_email( $email ) ) {
			$res['e'][] = __( 'That is not a valid email address.', 'opentickets-community-edition' );
		// if that email address is already registered, the error out
		} else if ( email_exists( $email ) ) {
			$res['e'][] = $email . ' ' . __( 'is already in use by another user. Please use a different email address.', 'opentickets-community-edition' );
		}

		// validate that there is a first name present
		if ( empty( $first_name ) ) {
			$res['e'][] = __( 'The first name is a required field.', 'opentickets-community-edition' );
		}

		// validate that there is a last naem present
		if ( empty( $last_name ) ) {
			$res['e'][] = __( 'The last name is a required field.', 'opentickets-community-edition' );
		}

		// if there are no validation errors, then try to create the user
		if ( empty( $res['e'] ) ) {
			// add a message confirming validation
			$res['m'][] = __( 'The information you supplied passed validation.', 'opentickets-community-edition' );

			// compile the information we will use to create the user
			$user_info = array(
				'user_login' => ( get_option( 'woocommerce_registration_email_for_username', 'no' ) == 'yes' ) ? $email : $username,
				'user_email' => $email,
				'user_pass' => version_compare( $GLOBALS['wp_version'], '4.3.1' ) >= 0 ? null : self::_random_pass( 8 ),
				'first_name' => $first_name,
				'last_name' => $last_name,
				'display_name' => $first_name . ' ' . $last_name,
				'role' => 'customer',
			);

			// attempt to add the user
			$user_id = wp_insert_user( $user_info );

			// if it was a hard fail for some reason, report it
			if ( is_wp_error( $user_id ) ) {
				$res['e'][] = __( 'User creation failed:', 'opentickets-community-edition' ) . ' ' . $user_id->get_error_message();
			// otherwise, assume we passed, and the user was created, so finish up creation and formulate an appropriate response
			} else {
				$res['s'] = true;
				$res['m'][] = __( 'The user was created successfully.', 'opentickets-community-edition' );
				$user = new WP_User( $user_id );
				$res['c']['id'] = $user_id;
				$res['c']['text'] = sprintf( '%s %s (#%d - %s)', $first_name, $last_name, $user_id, $email );

				// notify the user of the password
				wp_new_user_notification( $user_id, $user_info['user_pass'], true );

				// add the user name and email for woo, so we dont have to fill it out
				update_user_meta( $user_id, 'billing_first_name', $first_name );
				update_user_meta( $user_id, 'billing_last_name', $last_name );
				update_user_meta( $user_id, 'billing_email', $email );
			}
		}

		// respond
		wp_send_json( $res );
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

		$list['new-user-form'] = '<div class="new-user-form-wrapper" title="'.__('New User Form','opentickets-community-edition').'">'
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
							.'<label for="new_user_login">'.__('Username','opentickets-community-edition').'</label>'
							.'<input class="widefat" type="text" name="new_user_login" id="new_user_login" rel="new-user-login" value="" />'
						.'</div>')
				.'<div class="field">'
					.'<label for="new_user_email">'.__('Email','opentickets-community-edition').'</label>'
					.'<input class="widefat" type="email" name="new_user_email" id="new_user_email" rel="new-user-email" value="" />'
				.'</div>'
				.'<div class="field">'
					.'<label for="new_user_first_name">'.__('First Name','opentickets-community-edition').'</label>'
					.'<input class="widefat" type="text" name="new_user_first_name" id="new_user_first_name" rel="new-user-first-name" value="" />'
				.'</div>'
				.'<div class="field">'
					.'<label for="new_user_last_name">'.__('Last Name','opentickets-community-edition').'</label>'
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

	// only add the extra custom completed order email messages, to the complete order emails
	public static function add_completed_order_email_messages( $subject ) {
		add_action('woocommerce_email_customer_details', array(__CLASS__, 'print_custom_email_message'), 1000);
		add_action('woocommerce_email_before_order_table', array(__CLASS__, 'print_custom_email_message_top'), 1000);
		return $subject;
	}

	public static function print_custom_email_message_top($order, $html=true) {
		$print = apply_filters('qsot-order-has-tickets', false, $order);
		if ($print) {
			if ($html) {
				$msg = apply_filters( 'the_content', self::$options->{'qsot-completed-order-email-message-top'} );
				if (!empty($msg)) echo '<div class="custom-email-message">'.$msg.'</div>';
			} else {
				$msg = apply_filters( 'the_title', self::$options->{'qsot-completed-order-email-message-text-top'} );
				if (!empty($msg)) echo "\n****************************************************\n\n".$msg;
			}
		}
	}

	public static function print_custom_email_message($order, $html=true) {
		$print = apply_filters('qsot-order-has-tickets', false, $order);
		if ($print) {
			if ($html) {
				$msg = apply_filters( 'the_content', self::$options->{'qsot-completed-order-email-message'} );
				if (!empty($msg)) echo '<div class="custom-email-message">'.$msg.'</div>';
			} else {
				$msg = apply_filters( 'the_title', self::$options->{'qsot-completed-order-email-message-text'} );
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

	protected static function _disable_emails() {
		$emails = WC_Emails::instance();

		remove_action( 'woocommerce_order_status_pending_to_processing_notification', array( $emails->emails['WC_Email_Customer_Processing_Order'], 'trigger' ), 10 );
		remove_action( 'woocommerce_order_status_pending_to_on-hold_notification', array( $emails->emails['WC_Email_Customer_Processing_Order'], 'trigger' ), 10 );
		remove_action( 'woocommerce_order_status_completed_notification', array( $emails->emails['WC_Email_Customer_Completed_Order'], 'trigger' ), 10 );
		remove_action( 'woocommerce_order_status_pending_to_processing_notification', array( $emails->emails['WC_Email_New_Order'], 'trigger' ), 10 );
		remove_action( 'woocommerce_order_status_pending_to_completed_notification', array( $emails->emails['WC_Email_New_Order'], 'trigger' ), 10 );
		remove_action( 'woocommerce_order_status_pending_to_on-hold_notification', array( $emails->emails['WC_Email_New_Order'], 'trigger' ), 10 );
		remove_action( 'woocommerce_order_status_failed_to_processing_notification', array( $emails->emails['WC_Email_New_Order'], 'trigger' ), 10 );
		remove_action( 'woocommerce_order_status_failed_to_completed_notification', array( $emails->emails['WC_Email_New_Order'], 'trigger' ), 10 );
		remove_action( 'woocommerce_order_status_failed_to_on-hold_notification', array( $emails->emails['WC_Email_New_Order'], 'trigger' ), 10 );
	}

	protected static function _enable_emails() {
		self::_disable_emails();
		$emails = WC_Emails::instance();

		add_action( 'woocommerce_order_status_pending_to_processing_notification', array( $emails->emails['WC_Email_Customer_Processing_Order'], 'trigger' ), 10 );
		add_action( 'woocommerce_order_status_pending_to_on-hold_notification', array( $emails->emails['WC_Email_Customer_Processing_Order'], 'trigger' ), 10 );
		add_action( 'woocommerce_order_status_completed_notification', array( $emails->emails['WC_Email_Customer_Completed_Order'], 'trigger' ), 10 );
		add_action( 'woocommerce_order_status_pending_to_processing_notification', array( $emails->emails['WC_Email_New_Order'], 'trigger' ), 10 );
		add_action( 'woocommerce_order_status_pending_to_completed_notification', array( $emails->emails['WC_Email_New_Order'], 'trigger' ), 10 );
		add_action( 'woocommerce_order_status_pending_to_on-hold_notification', array( $emails->emails['WC_Email_New_Order'], 'trigger' ), 10 );
		add_action( 'woocommerce_order_status_failed_to_processing_notification', array( $emails->emails['WC_Email_New_Order'], 'trigger' ), 10 );
		add_action( 'woocommerce_order_status_failed_to_completed_notification', array( $emails->emails['WC_Email_New_Order'], 'trigger' ), 10 );
		add_action( 'woocommerce_order_status_failed_to_on-hold_notification', array( $emails->emails['WC_Email_New_Order'], 'trigger' ), 10 );
	}

	protected static function _setup_admin_options() {
		self::$options->def( 'qsot-completed-order-email-message-top', '' );
		self::$options->def( 'qsot-completed-order-email-message-text-top', '' );
		self::$options->def( 'qsot-completed-order-email-message', '' );
		self::$options->def( 'qsot-completed-order-email-message-text', '' );

		self::$options->add( array(
			'order' => 2100,
			'type' => 'title',
			'title' => __( 'Additional Email Settings', 'opentickets-community-edition' ),
			'id' => 'heading-add-email-sets-1',
			'section' => 'wc-emails',
		) );

		self::$options->add( array(
			'order' => 2131,
			'id' => 'qsot-completed-order-email-message-top',
			'type' => 'wysiwyg',
			'class' => 'widefat reason-list i18n-multilingual',
			'title' => __( 'Custom Completed Order Message (Above Order Items)', 'opentickets-community-edition' ),
			'desc' => __( 'This html appears near the top of the default Completed Order email, sent to the customer upon completion of their order, above the order items.', 'opentickets-community-edition' ),
			'default' => '',
			'section' => 'wc-emails',
		) );

		self::$options->add( array(
			'order' => 2132,
			'id' => 'qsot-completed-order-email-message-text-top',
			'type' => 'textarea',
			'class' => 'widefat reason-list i18n-multilingual',
			'title' => __( 'Custom Completed Order Message - Text only version (Above Order Items)', 'opentickets-community-edition' ),
			'desc' => __( 'This html appears near the top of the default Completed Order email, sent to the customer upon completion of their order, above the order items.', 'opentickets-community-edition' ),
			'default' => '',
			'section' => 'wc-emails',
		) );

		self::$options->add( array(
			'order' => 2141,
			'id' => 'qsot-completed-order-email-message',
			'type' => 'wysiwyg',
			'class' => 'widefat reason-listdt i18n-multilingual',
			'title' => __( 'Custom Completed Order Message (Below Address)', 'opentickets-community-edition' ),
			'desc' => __( 'This html appears at the bottom of the default Completed Order email, sent to the customer upon completion of their order, below their address information.', 'opentickets-community-edition' ),
			'default' => '',
			'section' => 'wc-emails',
		) );

		self::$options->add( array(
			'order' => 2142,
			'id' => 'qsot-completed-order-email-message-text',
			'type' => 'textarea',
			'class' => 'widefat reason-list i18n-multilingual',
			'title' => __( 'Custom Completed Order Message - Text only version (Below Address)', 'opentickets-community-edition' ),
			'desc' => __( 'This text appears at the bottom of the default Completed Order email, sent to the customer upon completion of their order, below their address information.', 'opentickets-community-edition' ),
			'default' => '',
			'section' => 'wc-emails',
		) );

		self::$options->add( array(
			'order' => 2199,
			'type' => 'sectionend',
			'id' => 'heading-add-email-sets-1',
			'section' => 'wc-emails',
		) );
	}
}

if (defined('ABSPATH') && function_exists('add_action')) {
	qsot_order_admin::pre_init();
}
