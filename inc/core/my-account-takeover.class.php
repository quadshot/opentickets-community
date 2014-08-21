<?php (__FILE__ == $_SERVER['SCRIPT_FILENAME']) ? die(header('Location: /')) : null;

class qsot_my_account_takeover {
	protected static $options = array();
	protected static $o = array();

	public static function pre_init() {
		$settings_class_name = apply_filters('qsot-settings-class-name', '');
		if (!empty($settings_class_name)) {
			self::$o =& call_user_func_array(array($settings_class_name, "instance"), array());
			// load all the options, and share them with all other parts of the plugin
			$options_class_name = apply_filters('qsot-options-class-name', '');
			if (!empty($options_class_name)) {
				self::$options =& call_user_func_array(array($options_class_name, "instance"), array());
				self::_setup_admin_options();
			}

			add_action('woocommerce_before_my_account', array(__CLASS__, 'draw_upcoming_event_tickets_list'), 10);

			add_action('edit_user_profile', array(__CLASS__, 'add_my_account_to_user_profile'), 4, 1);
			add_action('show_user_profile', array(__CLASS__, 'add_my_account_to_user_profile'), 4, 1);

			add_action('woocommerce_init', array(__CLASS__, 'override_shortcodes'), 10001);

			add_action('woocommerce_my_account_my_orders_values', array(__CLASS__, 'my_orders_values'), 10, 2);
			add_action('woocommerce_my_account_my_orders_headers', array(__CLASS__, 'my_orders_headers'), 10, 2);

			// allow users to be logged in indefinitely, more or less
			if (self::$options->{'qsot-infinite-login'} == 'yes') {
				//add_action('login_init', array(__CLASS__, 'long_test_cookie'), PHP_INT_MAX);
				add_filter('auth_cookie_expiration', array(__CLASS__, 'long_login_expire'), PHP_INT_MAX, 3);
				add_filter('wc_session_expiring', array(__CLASS__, 'long_login_expiring'), PHP_INT_MAX, 3);
				add_filter('wc_session_expiration', array(__CLASS__, 'long_login_expire'), PHP_INT_MAX, 3);
				add_filter('plugins_loaded', array(__CLASS__, 'extend_login_expiration'), 1);
			}
		}
	}

	public static function debug($name) { die(__log($name)); }

	public static function my_orders_headers($user, $orders) {
		if (!is_admin()) return;

		?><th>Shows</th><?php
	}

	public static function my_orders_values($user, $order) {
		if (!is_admin()) return;

		$shows = array();

		foreach ($order->get_items() as $item) {
			unset($item['item_meta']);
			if (is_array($item) && isset($item['event_id'])) {
				$event = apply_filters('qsot-get-event', false, $item['event_id']);
				if (is_object($event)) {
					$shows[] = $event->post_title;
				}
			}
		}

		$shows = array_unique($shows);
		?>
			<td>
				<?php if (count($shows)): ?>
					<?php echo implode('<br/>', $shows) ?>
				<?php else: ?>
					&nbsp;(none)
				<?php endif; ?>
			</td>
		<?php
	}

	public static function long_login_expire($length, $user_id=0, $remember='') {
		return 31536000;
	}

	public static function long_login_expiring($length, $user_id=0, $remember='') {
		return 31449600;
	}

	public static function long_test_cookie() {
		setcookie(TEST_COOKIE, 'WP Cookie check', apply_filters('auth_cookie_expiration', 0), COOKIEPATH, COOKIE_DOMAIN);
		if ( SITECOOKIEPATH != COOKIEPATH )
			setcookie(TEST_COOKIE, 'WP Cookie check', apply_filters('auth_cookie_expiration', 0), SITECOOKIEPATH, COOKIE_DOMAIN);
	}

	public static function extend_login_expiration() {
		$user = wp_get_current_user();
		if (!empty($user->ID)) {
			wp_set_auth_cookie($user->ID);
			self::long_test_cookie();
		}
	}

	public static function override_shortcodes() {
		remove_shortcode('woocommerce_view_order');
		add_shortcode( 'woocommerce_view_order', array( __CLASS__, 'view_order_shortcode' ) );
	}

	public static function view_order_shortcode($atts) {
		global $woocommerce;
		return $woocommerce->shortcode_wrapper( array( __CLASS__, 'view_order_shortcode_output' ), $atts );
	}

	public static function view_order_shortcode_output($atts) {
		global $woocommerce;

		if ( ! is_user_logged_in() ) return;

		extract( shortcode_atts( array(
	    	'order_count' => 10
		), $atts ) );

		$user_id      	= get_current_user_id();
		$order_id		= ( isset( $_GET['order'] ) ) ? $_GET['order'] : 0;
		$order 			= new WC_Order( $order_id );

		if ( $order_id == 0 ) {
			woocommerce_get_template( 'myaccount/my-orders.php', array( 'order_count' => 'all' == $order_count ? -1 : $order_count ) );
			return;
		}

		if ( !current_user_can('delete_users') && $order->user_id != $user_id ) {
			echo '<div class="woocommerce-error">' . __( 'Invalid order.', 'woocommerce' ) . ' <a href="'.get_permalink( woocommerce_get_page_id('myaccount') ).'">'. __( 'My Account &rarr;', 'woocommerce' ) .'</a>' . '</div>';
			return;
		}

		$status = get_term_by('slug', $order->status, 'shop_order_status');

		echo '<p class="order-info">'
		. sprintf( __( 'Order <mark class="order-number">%s</mark> made on <mark class="order-date">%s</mark>', 'woocommerce'), $order->get_order_number(), date_i18n( get_option( 'date_format' ), strtotime( $order->order_date ) ) )
		. '. ' . sprintf( __( 'Order status: <mark class="order-status">%s</mark>', 'woocommerce' ), __( $status->name, 'woocommerce' ) )
		. '.</p>';

		$notes = $order->get_customer_order_notes();
		if ($notes) :
			?>
			<h2><?php _e( 'Order Updates', 'woocommerce' ); ?></h2>
			<ol class="commentlist notes">
				<?php foreach ($notes as $note) : ?>
				<li class="comment note">
					<div class="comment_container">
						<div class="comment-text">
							<p class="meta"><?php echo date_i18n(__( 'l jS \of F Y, h:ia', 'woocommerce' ), strtotime($note->comment_date)); ?></p>
							<div class="description">
								<?php echo wpautop(wptexturize($note->comment_content)); ?>
							</div>
			  				<div class="clear"></div>
			  			</div>
						<div class="clear"></div>
					</div>
				</li>
				<?php endforeach; ?>
			</ol>
			<?php
		endif;

		do_action( 'woocommerce_view_order', $order_id );
	}

	public static function add_my_account_to_user_profile($userprofile) {
		global $woocommerce;

		if ( ! is_user_logged_in() ) {
			woocommerce_get_template( 'myaccount/form-login.php' );
		} else {
			?><div class="my-account"><?php
				woocommerce_get_template( 'myaccount/my-account.php', array(
					'current_user' 	=> $userprofile,
					'order_count' 	=> -1,
				) );
			?></div><?php
		}
	}

	public static function draw_upcoming_event_tickets_list($current_user) {
		global $wpdb;

		$orders = get_posts(array(
			'posts_per_page' => -1,
			'meta_key' => '_customer_user',
			'meta_value' => is_object($current_user) && isset($current_user->ID) ? $current_user->ID : get_current_user_id(),
			'post_type' => 'shop_order',
			'post_status' => 'publish',
			'fields' => 'ids',
		));
		if (!is_array($orders) || empty($orders)) return;
		$orders = array_map('absint', $orders);

		$q = $wpdb->prepare('select distinct order_item_id from '.$wpdb->base_prefix.'woocommerce_order_items where order_id in ('.implode(',', $orders).')', true);
		$order_item_ids = $wpdb->get_col($q);
		if (!is_array($order_item_ids) || empty($order_item_ids)) return;
		$order_item_ids = array_map('absint', $order_item_ids);

		$q = $wpdb->prepare(
			'select order_item_id, meta_value from '.$wpdb->base_prefix.'woocommerce_order_itemmeta where order_item_id in ('.implode(',', $order_item_ids).') and meta_key = %s',
			'_event_id'
		);
		$pairs = $wpdb->get_results($q);
		if (!is_array($pairs) || empty($pairs)) return;

		$groups = array();
		foreach ($pairs as $pair) {
			$event_id = $pair->meta_value;
			$oiid = $pair->order_item_id;
			if (!isset($groups["{$event_id}"]) || !is_array($groups["{$event_id}"])) $groups["{$event_id}"] = array();
			$groups["{$event_id}"][] = $oiid;
		}

		$events = get_posts(array(
			'posts_per_page' => -1,
			'fields' => 'ids',
			'suppress_filters' => false,
			'post_status' => 'publish',
			'post_type' => self::$o->core_post_type,
			'start_date_after' => date('Y-m-d H:i:s'),
			'post__in' => array_keys($groups),
		));
		if (!is_array($events) || empty($events)) return;
		$events = array_map('absint', $events);

		$ticket_ids = array();
		foreach ($events as $eid)
			if (isset($groups["{$eid}"]))
				$ticket_ids = array_merge($ticket_ids, $groups["{$eid}"]);
		$ticket_ids = array_unique($ticket_ids);

		$q = $wpdb->prepare('select * from '.$wpdb->base_prefix.'woocommerce_order_itemmeta where order_item_id in ('.implode(',', $ticket_ids).')', true);
		$raw_data = $wpdb->get_results($q);

		$q = $wpdb->prepare('select order_id, order_item_id from '.$wpdb->base_prefix.'woocommerce_order_items where order_item_id in ('.implode(',', $ticket_ids).')', true);
		$raw_pairs = $wpdb->get_results($q);
		$pairs = array();
		foreach ($raw_pairs as $raw_row) $pairs[$raw_row->order_item_id.''] = $raw_row->order_id;

		$event_data = $ticket_data = array();
		foreach ($raw_data as $row) {
			if (!isset($ticket_data["{$row->order_item_id}"]) || !is_array($ticket_data["{$row->order_item_id}"]))
				$ticket_data["{$row->order_item_id}"] = array('__order_item_id' => $row->order_item_id, '__order_id' => isset($pairs[$row->order_item_id]) ? $pairs[$row->order_item_id] : 0);
			$ticket_data["{$row->order_item_id}"][$row->meta_key] = $row->meta_value;
		}

		foreach ($ticket_data as $ind => $ticket) {
			$ticket = (object)wp_parse_args($ticket, array(
				'_ticket_code' => '',
				'_ticket_link' => '',
				'_product_id' => 0,
				'_event_id' => 0,
				'_zone_id' => 0,
				'__order_id' => 0,
			));
			$ticket->product = get_product($ticket->_product_id);
			$ticket->event = apply_filters('qsot-event-add-meta', get_post($ticket->_event_id));
			$ticket->zone = apply_filters('qsot-get-seating-zone', null, $ticket->_zone_id);
			$ticket_data[$ind] = $ticket;

			if (is_object($ticket->event) && (!isset($event_data["{$ticket->_event_id}"]) || !is_object($event_data["{$ticket->_event_id}"])))
				$event_data["{$ticket->_event_id}"] = $ticket->event;
			if (!isset($event_data["{$ticket->_event_id}"]->tickets) || !is_array($event_data["{$ticket->_event_id}"]->tickets))
				$event_data["{$ticket->_event_id}"]->tickets = array();
			$event_data["{$ticket->_event_id}"]->tickets[] = $ticket;
		}

		woocommerce_get_template('myaccount/my-upcoming-tickets.php', array(
			'user' => $current_user,
			'tickets' => $ticket_data,
			'by_event' => $event_data,
			'display_format' => self::$options->{'qsot-my-account-display-upcoming-tickets'},
		));
	}

	protected static function _setup_admin_options() {
		self::$options->def('qsot-my-account-display-upcoming-tickets', 'by_event');
		self::$options->def('qsot-infinite-login', 'yes');

		self::$options->add(array(
			'order' => 1000,
			'type' => 'title',
			'title' => __('My Account Page', 'qsot'),
			'id' => 'heading-my-account-1',
		));

		self::$options->add(array(
			'order' => 1010,
			'id' => 'qsot-my-account-display-upcoming-tickets',
			'type' => 'radio',
			'title' => __('Display Upcoming Tickets', 'qsot'),
			'desc_tip' => __('Format to display the upcoming tickets list in. The list appears on the end user\'s "My Account" page.', 'qsot'),
			'options' => array(
				'by_event' => __('By Event', 'qsot'),
				'as_list' => __('As Line Item List', 'qsot'),
			),
			'default' => 'by_event',
		));

		self::$options->add(array(
			'order' => 1020,
			'id' => 'qsot-infinite-login',
			'type' => 'checkbox',
			'title' => __('Infinite Login', 'qsot'),
			'desc' => __('Once a user logs in, they stay logged in, forever.', 'qsot'),
			'default' => 'yes',
		));

		self::$options->add(array(
			'order' => 1030,
			'type' => 'sectionend',
			'id' => 'heading-my-account-1',
		));
	}
}

if (defined('ABSPATH') && function_exists('add_action')) {
	if ( !function_exists('wp_set_auth_cookie') ) :
	/** @@@@@COPIED FROM pluggable.php and modified to work with our infinite login
	 * Sets the authentication cookies based User ID.
	 *
	 * The $remember parameter increases the time that the cookie will be kept. The
	 * default the cookie is kept without remembering is two days. When $remember is
	 * set, the cookies will be kept for 14 days or two weeks.
	 *
	 * @since 2.5
	 *
	 * @param int $user_id User ID
	 * @param bool $remember Whether to remember the user
	 */
	function wp_set_auth_cookie($user_id, $remember = false, $secure = '') {
		//if ( $remember ) {
			$expiration = $expire = time() + apply_filters('auth_cookie_expiration', 1209600, $user_id, $remember);
		/*
		} else {
			$expiration = time() + apply_filters('auth_cookie_expiration', 172800, $user_id, $remember);
			$expire = 0;
		}
		*/

		if ( '' === $secure )
			$secure = is_ssl();

		$secure = apply_filters('secure_auth_cookie', $secure, $user_id);
		$secure_logged_in_cookie = apply_filters('secure_logged_in_cookie', false, $user_id, $secure);

		if ( $secure ) {
			$auth_cookie_name = SECURE_AUTH_COOKIE;
			$scheme = 'secure_auth';
		} else {
			$auth_cookie_name = AUTH_COOKIE;
			$scheme = 'auth';
		}

		$auth_cookie = wp_generate_auth_cookie($user_id, $expiration, $scheme);
		$logged_in_cookie = wp_generate_auth_cookie($user_id, $expiration, 'logged_in');

		do_action('set_auth_cookie', $auth_cookie, $expire, $expiration, $user_id, $scheme);
		do_action('set_logged_in_cookie', $logged_in_cookie, $expire, $expiration, $user_id, 'logged_in');

		setcookie($auth_cookie_name, $auth_cookie, $expire, PLUGINS_COOKIE_PATH, COOKIE_DOMAIN, $secure, true);
		setcookie($auth_cookie_name, $auth_cookie, $expire, ADMIN_COOKIE_PATH, COOKIE_DOMAIN, $secure, true);
		setcookie(LOGGED_IN_COOKIE, $logged_in_cookie, $expire, COOKIEPATH, COOKIE_DOMAIN, $secure_logged_in_cookie, true);
		if ( COOKIEPATH != SITECOOKIEPATH )
			setcookie(LOGGED_IN_COOKIE, $logged_in_cookie, $expire, SITECOOKIEPATH, COOKIE_DOMAIN, $secure_logged_in_cookie, true);
	}
	endif;
	
	qsot_my_account_takeover::pre_init();
}
