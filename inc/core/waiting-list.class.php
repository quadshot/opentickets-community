<?php (__FILE__ == $_SERVER['SCRIPT_FILENAME']) ? die(header('Location: /')) : null;

class qsot_waiting_list {
	// holder for event plugin options
	protected static $o = null;
	protected static $options = null;

	public static function pre_init() {
		// load all the options, and share them with all other parts of the plugin
		$options_class_name = apply_filters('qsot-options-class-name', '');
		if (!empty($options_class_name)) {
			self::$options =& $options_class_name::instance();
			//self::_setup_admin_options();
		}

		$settings_class_name = apply_filters('qsot-settings-class-name', '');
		if (!empty($settings_class_name)) {
			self::$o =& $settings_class_name::instance();

			add_filter('qsot-waiting-list-templates', array(__CLASS__, 'templates_waiting_list'), 10, 2);

			add_action('init', array(__CLASS__, 'register_assets'), 1000);
			add_action('wp', array(__CLASS__, 'load_frontend_assets'), 1000, 1);
			add_action('load-post.php', array(__CLASS__, 'load_edit_page_assets'), 999);
			add_action('add_meta_boxes', array(__CLASS__, 'waiting_list_metaboxes'), 10, 2);

			add_filter('qsot-upcoming-events-buy-link', array(__CLASS__, 'upcoming_events_buy_link'), 100, 2);

			add_action('qsot-ajax-waiting-list', array(__CLASS__, 'handle_ajax'), 100);

			add_action('wp_ajax_qsot-waiting-list', array(__CLASS__, 'handle_admin_ajax'), 100);

			add_action('qsot-load-seating-report-assets', array(__CLASS__, 'load_seating_report_assets'), 100);
			add_filter('qsot-admin-waiting-list-add-user-templates', array(__CLASS__, 'admin_templates_waiting_list'), 10, 2);
			add_filter('qsot-get-waiting-list', array(__CLASS__, 'get_waiting_list'), 100, 2);
			add_action('qsot-below-seating-report', array(__CLASS__, 'add_to_seating_report'), 100, 3);
		}
	}

	public static function register_assets() {
		wp_register_script('qsot-waiting-list', self::$o->core_url.'assets/js/features/waiting-list/waiting-list.js', array('qsot-frontend-ajax'), self::$o->version);
		wp_register_style('qsot-waiting-list', self::$o->core_url.'assets/css/features/waiting-list/waiting-list.css', array(), self::$o->version);

		wp_register_script('qsot-admin-waiting-list', self::$o->core_url.'assets/js/admin/waiting-list.js', array('qsot-tools', 'jquery-blockui', 'jquery-ui-dialog', 'ajax-chosen'), self::$o->version);
	}

	public static function load_seating_report_assets() {
		$settings = array();
		$settings['adduser'] = apply_filters(
			'qsot-admin-waiting-list-add-user-settings',
			array(
				'templates' => apply_filters('qsot-admin-waiting-list-add-user-templates', array(), $post->ID),
				'security' => wp_create_nonce("search-customers"),
			),
			$post->ID
		);

		global $woocommerce;
		wp_enqueue_style( 'woocommerce_admin_styles', $woocommerce->plugin_url() . '/assets/css/admin.css' );
		wp_enqueue_style( 'woocommerce_chosen_styles', $woocommerce->plugin_url() . '/assets/css/chosen.css' );
		wp_enqueue_script('qsot-admin-waiting-list');
		wp_localize_script('qsot-admin-waiting-list', '_qsot_sr_waiting_list_settings', apply_filters('qsot-sr-waiting-list-settings', $settings, $post->ID));
	}

	// when on the edit single event page in the admin, we need to queue up certain aseets (previously registered) so that the page actually works properly
	public static function load_edit_page_assets() {
		// is this a new event or an existing one? we can check this by determining the post_id, if there is one (since WP does not tell us)
		$post_id = 0;
		// if there is a post_id in the admin url, and the post it represents is of our event post type, then this is an existing post we are just editing
		if (isset($_REQUEST['post']) && get_post_type($_REQUEST['post']) == self::$o->core_post_type) {
			$post_id = $_REQUEST['post'];
			$existing = true;
		// if this is not an edit page of our post type, then we need none of these assets loaded
		} else return;

		$post = get_post($post_id);

		$settings = array();
		$settings['parent'] = $post->post_parent;
		$settings['users'] = $user_ids = array();
		
		if ($post->post_parent == 0) {
			$children = get_posts(array(
				'post_type' => self::$o->core_post_type,
				'posts_per_page' => -1,
				'post_parent' => $post->ID,
				'orderby' => 'title',
				'order' => 'asc',
				'suppress_filters' => false,
			));
			$settings['events'] = array();
			$settings['lists'] = array();
			foreach ($children as $child) {
				$settings['events'][] = array(
					'name' => $child->post_title,
					'id' => $child->ID,
				);
				$list = get_post_meta($child->ID, '_waiting_list', true);
				$list = is_array($list) ? $list : array();
				$settings['lists'][$child->ID.''] = $list;
				foreach ($list as $item) if (isset($item['u'])) $user_ids[] = $item['u'];
			}
		} else {
			$settings['list'] = get_post_meta($post->ID, '_waiting_list', true);
			$settings['list'] = is_array($settings['list']) ? $settings['list'] : array();
			foreach ($settings['list'] as $item) if (isset($item['u'])) $user_ids[] = $item['u'];
		}

		foreach ($user_ids as $id) {
			$u = get_user_by('id', $id);
			$email = $u->user_email;
			$name = array();
			if (isset($u->billing_first_name)) $name[] = $u->billing_first_name;
			if (isset($u->billing_last_name)) $name[] = $u->billing_last_name;
			if (empty($name)) {
				if (isset($u->first_name)) $name[] = $u->first_name;
				if (isset($u->last_name)) $name[] = $u->last_name;
			}
			if (empty($name)) $name[] = $u->user_login;
			$display = implode(' ', $name).' ('.$email.')';
			$link = get_edit_user_link($u->ID);
			$settings['users'][$u->ID.''] = array(
				'id' => $u->ID,
				'name' => $display,
				'link' => esc_attr($link),
			);
		}

		$settings['adduser'] = apply_filters(
			'qsot-admin-waiting-list-add-user-settings',
			array(
				'templates' => apply_filters('qsot-admin-waiting-list-add-user-templates', array(), $post->ID),
				'security' => wp_create_nonce("search-customers"),
			),
			$post->ID
		);

		global $woocommerce;
		wp_enqueue_style( 'woocommerce_admin_styles', $woocommerce->plugin_url() . '/assets/css/admin.css' );
		wp_enqueue_style( 'woocommerce_chosen_styles', $woocommerce->plugin_url() . '/assets/css/chosen.css' );
		wp_enqueue_script('qsot-admin-waiting-list');
		wp_localize_script('qsot-admin-waiting-list', '_qsot_waiting_list_settings', apply_filters('qsot-waiting-list-settings', $settings, $post->ID));
	}

	public static function load_frontend_assets(&$wp) {
		if (is_singular(self::$o->core_post_type) && ($post = get_post()) && $post->post_parent != 0) {
			wp_enqueue_script('qsot-waiting-list');
			wp_enqueue_style('qsot-waiting-list');

			$event = apply_filters('qsot-get-event', false, $post->ID);

			wp_localize_script('qsot-waiting-list', '_qsot_waiting_list', apply_filters('qsot-waiting-list-settings', array(
				'templates' => apply_filters('qsot-waiting-list-templates', array(), $post->ID), // all templates used by the ui js
				'event_id' => $post->ID,
				'show' => in_array($event->meta->availability, array('sold-out', 'low')),
			), $post->ID));
		}
	}

	public static function admin_templates_waiting_list($list, $post_id) {
		$list['add-user-dialog'] = '<div class="add-user-dialog">'
				.'<p>Select the user to add to the list, and provide a quantity of seats that the user needs, then click "Add user".</p>'
				.'<p class="form-field form-field-wide">'
					.'<label for="uid">User</label>'
					.'<select name="uid" id="uid" class="ajax_chosen_select_customer widefat">'
						.'<option value="">[no user]</option>'
					.'</select>'
				.'</p>'
				.'<p class="form-field form-field-wide">'
					.'<label for="q">Quantity</label>'
					.'<input type="number" value="1" name="q" id="q" class="widefat"/>'
				.'</p>'
			.'</div>';

		return $list;
	}

	public static function templates_waiting_list($list, $post_id) {
		if (is_user_logged_in()) {
			list($pos, $quantity) = self::_get_current_position($post_id);
			$list['waiting-list'] = '<div class="qsot-waiting-list list-container w-list" rel="waiting-list-container" pos="'.$pos.'" quantity="'.$quantity.'">'
					.'<div class="list-wrapper">'
						.'<label>Waiting list: </label>'
						.'<div class="extra-msgs" rel="msgs"></div>'
						.'<div class="add-me-form" rel="add-me-form">'
							.'<span>Quantity:</span> '
							.'<input type="number" step="1" min="0" class="quantity" rel="quantity" />'
							.'<input type="button" class="add-me-btn" rel="add-me" value="Add me" />'
						.'</div>'
						.'<div class="remove-me-form" rel="remove-me-form">'
							.'<span>Your current position:</span> '
							.'<span class="curpos" rel="curpos">'.$pos.'</span>, '
							.'<span>for a quantity of</span> '
							.'<span class="curqty" rel="curqty">'.$quantity.'</span> '
							.'<input type="button" class="remove-me-btn" rel="remove-me" value="Remove me" />'
						.'</div>'
					.'</div>'
				.'</div>';
		} else {
			$list['waiting-list'] = '<div class="qsot-waiting-list list-container w-list" rel="waiting-list-container">'
					.'<div class="list-wrapper">'
						.'<label>Waiting list: </label>'
						.'<p>'.self::_logged_in_msg().'</p>'
					.'</div>'
				.'</div>';
		}

		return $list;
	}

	public static function upcoming_events_buy_link($link, $event) {
		if ($event->meta->available < $event->meta->capacity * .02) {
			$link = sprintf(
				'<a href="%s" class="%s" title="%s">%s</a>',
				esc_attr(get_permalink($event->ID)),
				'buy buy-link',
				'View Event',
				'Wait List'
			);
		}

		return $link;
	}

	public static function waiting_list_metaboxes($post_type, $post) {
		if ($post_type != self::$o->core_post_type) return;

		add_meta_box(
			'waiting-list-div',
			'Waiting List',
			array(__CLASS__, 'mb_waiting_list'),
			self::$o->core_post_type,
			'side',
			'default'
		);
	}

	public static function mb_waiting_list($post) {
		?>
			<?php if ($post->post_parent == 0): ?>
				<div class="field">
					<label>Event</label>
					<select class="widefat event-list"></select>
				</div>
			<?php endif; ?>

			<div class="waiting-list-msgs"></div>

			<div class="waiting-list-actions" rel="actions">
				<input type="button" class="button add-user" value="Add user" rel="add-user" />
			</div>

			<div class="waiting-list-wrap item-list-wrap"></div>
		<?php
	}

	public static function get_waiting_list($current, $event_id) {
		$list = get_post_meta($event_id, '_waiting_list', true);
		$current = is_array($current) ? $current : array();
		if (is_array($list)) foreach ($list as $ind => $item) {
			$user = get_user_by('id', $item['u']);
			$user->fullname = self::_get_user_fullname($user);
			$current[] = array(
				'quantity' => $item['q'],
				'user' => $user,
			);
		}
		return $current;
	}

	protected static function _get_user_fullname($user) {
		$names = array();

		$first = trim(get_user_meta($user->ID, 'billing_first_name', true));
		if (!empty($first)) $names[] = $first;

		$last = trim(get_user_meta($user->ID, 'billing_last_name', true));
		if (!empty($last)) $names[] = $last;

		if (empty($names)) $names = array($user->display_name);

		return implode(' ', $names);
	}

	protected static function _get_current_position($event_id) {
		$current = get_post_meta($event_id, '_waiting_list', true);
		$current = is_array($current) ? $current : array();
		$user = wp_get_current_user();
		$pos = -1;
		$quantity = 0;
		foreach ($current as $ind => $item) if ($item['u'] == $user->ID) {
			$pos = $ind;
			$quantity = $item['q'];
			break;
		}
		$pos++;
		return array($pos, $quantity);
	}

	protected static function _logged_in_msg() {
		$url = wp_login_url(remove_query_arg(array('nothing'), $_SERVER['HTTP_REFERER']));
		$msg = sprintf(
			'You must be <a href="%s" title="%s">logged in</a> before you can use the waiting list feature.',
			$url,
			'Login Now'
		);
		return $msg;
	}

	public static function handle_admin_ajax() {
		switch ($_POST['sa']) {
			case 'add': self::_admin_ajax_add_user(); break;
			case 'remove': self::_admin_ajax_remove_user(); break;
			case 'sradd': self::_admin_sr_ajax_add_user(); break;
			case 'srremove': self::_admin_sr_ajax_remove_user(); break;
		}

		exit;
	}

	public static function _admin_sr_ajax_add_user() {
		$res = array(
			's' => false,
			'e' => array(),
			'm' => array(),
		);

		$event_id = $_POST['eid'];
		$quantity = $_POST['q'];
		$user = get_user_by('id', $_POST['uid']);
		
		if (is_object($user) && !empty($user->ID)) {
			$res = self::_add_a_user($res, $event_id, $user, $quantity);
		} else {
			$res['e'][] = 'Could not find the specified user. Thus, we could not add them to the waiting list.';
		}

		$event = apply_filters('qsot-get-event', false, $event_id);
		self::_draw_waiting_list_for_seating_report($event, array(
			'ajax' => true,
			'errors' => $res['e'],
			'messages' => $res['m'],
		));
	}

	public static function _admin_sr_ajax_remove_user() {
		$res = array(
			's' => false,
			'e' => array(),
			'm' => array(),
		);

		$event_id = $_POST['eid'];
		$chk = $_POST['chk'];
		$user_id = $_POST['uid'];
		
		$res = self::_remove_a_user($res, $event_id, $user_id, $chk);

		$event = apply_filters('qsot-get-event', false, $event_id);
		self::_draw_waiting_list_for_seating_report($event, array(
			'ajax' => true,
			'errors' => $res['e'],
			'messages' => $res['m'],
		));
	}

	public static function _admin_ajax_remove_user() {
		$res = array(
			's' => false,
			'e' => array(),
			'm' => array(),
		);

		$event_id = isset($_POST['eid']) ? $_POST['eid'] : 0;

		if (!empty($event_id)) {
			$user = isset($_POST['u']) ? get_user_by('id', $_POST['u']) : null;
			if (is_object($user) && $user->ID == $_POST['u']) {
				$current = get_post_meta($event_id, '_waiting_list', true);
					$current = is_array($current) ? $current : array();
				$new = array();
				foreach ($current as $item) if ($item['u'] != $user->ID) $new[] = $item;
				update_post_meta($event_id, '_waiting_list', $new);
				$res['s'] = true;
			} else {
				$res['e'][] = 'Could not find that user. Invalid users cannot be removed from the list.';
			}
		} else {
			$res['e'][] = 'Could not determine which event needs to have you on the waiting list.';
		}

		header('Content-Type: text/json');
		echo @json_encode($res);
	}

	public static function _admin_ajax_add_user() {
		$res = array(
			's' => false,
			'e' => array(),
			'm' => array(),
		);

		$event_id = isset($_POST['eid']) ? $_POST['eid'] : 0;

		if (!empty($event_id)) {
			$user = isset($_POST['uid']) ? get_user_by('id', $_POST['uid']) : null;
			if (is_object($user) && $user->ID == $_POST['uid']) {
				$qty = isset($_POST['q']) ? (int)$_POST['q'] : 0;
				if (!empty($qty)) {
					$current = get_post_meta($event_id, '_waiting_list', true);
					$current = is_array($current) ? $current : array();
					$found = -1;
					foreach ($current as $ind => $item) {
						if ($item['u'] == $user->ID) {
							$found = $ind;
						}
					}
					if ($found == -1) $current[] = array('u' => $user->ID, 'q' => $qty);
					else $current[$found] = array('u' => $user->ID, 'q' => $qty);
					update_post_meta($event_id, '_waiting_list', $current);

					$u = $user;
					$email = $u->user_email;
					$name = array();
					if (isset($u->billing_first_name)) $name[] = $u->billing_first_name;
					if (isset($u->billing_last_name)) $name[] = $u->billing_last_name;
					if (empty($name)) {
						if (isset($u->first_name)) $name[] = $u->first_name;
						if (isset($u->last_name)) $name[] = $u->last_name;
					}
					if (empty($name)) $name[] = $u->user_login;
					$display = implode(' ', $name).' ('.$email.')';
					$link = get_edit_user_link($u->ID);

					$res['u'] = array(
						'id' => $u->ID,
						'name' => $display,
						'link' => esc_attr($link),
					);

					$res['s'] = true;
					$res['uid'] = $user->ID;
				} else {
					$res['e'][] = 'The quantity must be greater than zero.';
				}
			} else {
				$res['e'][] = 'Could not find that user. Invalid users cannot be removed from the list.';
			}
		} else {
			$res['e'][] = 'Could not determine which event needs to have you on the waiting list.';
		}

		header('Content-Type: text/json');
		echo @json_encode($res);
	}

	public static function handle_ajax() {
		switch ($_POST['sa']) {
			case 'add-me': self::_add_current_user(); break;
			case 'remove-me': self::_remove_current_user(); break;
		}

		exit;
	}

	protected static function _remove_current_user() {
		$res = array(
			's' => false,
			'e' => array(),
			'm' => array(),
		);

		$event_id = isset($_POST['e']) ? $_POST['e'] : 0;

		if (!empty($event_id)) {
			$user = wp_get_current_user();
			if (is_object($user) && !empty($user->ID)) {
				$current = get_post_meta($event_id, '_waiting_list', true);
				$current = is_array($current) ? $current : array();
				$new = array();
				foreach ($current as $item) if ($item['u'] != $user->ID) $new[] = $item;
				update_post_meta($event_id, '_waiting_list', $new);
				$res['pos'] = '';
				$res['quantity'] = '';
				$res['s'] = true;
			} else {
				$url = wp_login_url(remove_query_arg(array('nothing'), $_SERVER['HTTP_REFERER']));
				$res['m'][] = self::_logged_in_msg();
			}
		} else {
			$res['m'][] = 'Could not determine which event needs to have you on the waiting list.';
		}

		header('Content-Type: text/json');
		echo @json_encode($res);
	}

	protected static function _remove_a_user($res, $event_id, $user_id, $chk) {
		$res = wp_parse_args(array(
			's' => false,
			'e' => array(),
			'm' => array(),
		));

		if (!empty($event_id)) {
			$current = get_post_meta($event_id, '_waiting_list', true);
			$current = is_array($current) ? $current : array();
			$new_list = array();
			foreach ($current as $ind => $item) if ($item['u'] != $user_id) {
				if (md5($ind.'|'.$user_id) != $chk) {
					$new_list[] = $item;
				} else {
					$res['s'] = true;
					$res['m'][] = 'Removed the user from the wait list position ['.($ind+1).'] successfully.';
				}
			}
			update_post_meta($event_id, '_waiting_list', $new_list);
		} else {
			$res['m'][] = 'Could not determine which event needs to have you on the waiting list.';
			$res['e'][] = 'Could not determine which event needs to have you on the waiting list.';
		}
	}

	protected static function _add_a_user($res, $event_id, $user, $quantity) {
		$res = wp_parse_args(array(
			's' => false,
			'e' => array(),
			'm' => array(),
		));

		if (!empty($event_id)) {
			if (is_numeric($quantity) && $quantity > 0) {
				$current = get_post_meta($event_id, '_waiting_list', true);
				$current = is_array($current) ? $current : array();
				$pos = -1;
				foreach ($current as $ind => $item) if ($item['u'] == $user->ID) {
					$pos = $ind;
					break;
				}
				if ($pos >= 0) {
					$current[$pos]['q'] = $quantity;
					$pos++;
				} else {
					$current[] = array('u' => $user->ID, 'q' => $quantity);
					$pos = count($current);
				}
				update_post_meta($event_id, '_waiting_list', $current);
				$res['pos'] = $pos;
				$res['quantity'] = $quantity;
				$res['s'] = true;
			} else {
				$res['m'][] = 'The quantity must be a positive number.';
				$res['e'][] = 'The quantity must be a positive number.';
			}
		} else {
			$res['m'][] = 'Could not determine which event needs to have you on the waiting list.';
			$res['e'][] = 'Could not determine which event needs to have you on the waiting list.';
		}
	}

	protected static function _add_current_user() {
		$res = array(
			's' => false,
			'e' => array(),
			'm' => array(),
		);

		$event_id = isset($_POST['e']) ? $_POST['e'] : 0;
		$qty = isset($_POST['qty']) ? $_POST['qty'] : 0;
		$user = wp_get_current_user();

		if (is_object($user) && !empty($user->ID)) {
			$res = self::_add_a_user($res, $event_id, $user, $qty);
		} else {
			$url = wp_login_url(remove_query_arg(array('nothing'), $_SERVER['HTTP_REFERER']));
			$res['m'][] = self::_logged_in_msg();
		}

		header('Content-Type: text/json');
		echo @json_encode($res);
	}

	public static function add_to_seating_report($event, $req, $tickets) {
		self::_draw_waiting_list_for_seating_report($event, array(), $req, false);
	}

	protected static function _draw_waiting_list_for_seating_report($event, $args='', $req=array(), $ajax=false) {
		$req = wp_parse_args($req, array());
		$list = apply_filters('qsot-get-waiting-list', array(), $event->ID);

		$args = wp_parse_args($args, array(
			'ajax' => $ajax,
			'req' => $req,
			'event' => $event,
			'list' => $list,
			'errors' => array(),
			'messages' => array(),
		));

		self::_inc_template(array('admin/reports/waiting-list-subreport.php'), $args);
	}

	protected static function _inc_template($template, $_args) {
		extract($_args);
		$template = apply_filters('qsot-locate-template', '', $template, false, false);
		if (!empty($template)) include $template;
	}
}

if (defined('ABSPATH') && function_exists('add_action')) {
	qsot_waiting_list::pre_init();
}
