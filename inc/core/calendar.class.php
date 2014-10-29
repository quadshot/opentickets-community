<?php (__FILE__ == $_SERVER['SCRIPT_FILENAME']) ? die(header('Location: /')) : null;

class qsot_frontend_calendar {
	protected static $o = null;
	protected static $options = null;
	protected static $shortcode = 'qsot-event-calendar';

	public static function pre_init() {
		add_action('qsot-activate', array(__CLASS__, 'create_calendar_page'), 10);
		// load all the options, and share them with all other parts of the plugin
		$options_class_name = apply_filters('qsot-options-class-name', '');
		if (!empty($options_class_name)) {
			self::$options = call_user_func_array(array($options_class_name, "instance"), array());
			//self::_setup_admin_options();
		}

		$settings_class_name = apply_filters('qsot-settings-class-name', '');
		if (!empty($settings_class_name)) {
			self::$o = call_user_func_array(array($settings_class_name, "instance"), array());

			add_filter('woocommerce_page_settings', array(__CLASS__, 'add_pages'), 10, 1);
			add_filter('init', array(__CLASS__, 'register_assets'), 10);

			add_action('init', array(__CLASS__, 'add_sidebar'), 11);
			add_filter('init', array(__CLASS__, 'intercept_cal_ajax'), 1000, 1);
			add_filter('wp_enqueue_scripts', array(__CLASS__, 'add_assets'), 10000);
			add_action('qsot-calendar-settings', array(__CLASS__, 'calendar_settings'), 10, 3);
			add_filter('qsot-calendar-event', array(__CLASS__, 'get_calendar_event'), 10, 2);
			add_shortcode(self::$shortcode, array(__CLASS__, 'shortcode'));

			add_filter('qsot-templates-page-templates', array(__CLASS__, 'add_calendar_template'));

			// add the admin metabox to control the calendar settings
			add_action('add_meta_boxes', array(__CLASS__, 'add_meta_boxes'), 1000);
			add_action('postbox_classes_page_qsot-calendar-settings-box', array(__CLASS__, 'mb_calendar_settings_classes'), 1000, 1);
			add_action('save_post', array(__CLASS__, 'save_page_calendar_settings'), 1000, 2);

			// load admin js and css
			add_action('qsot-admin-load-assets-page', array(__CLASS__, 'load_admin_assets'), 1000, 2);
		}
	}

	public static function add_calendar_template($list) {
		$list['qsot-calendar.php'] = __('OpenTickets Calendar','opentickets-community-edition');
		return $list;
	}

	public static function register_assets() {
		$suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';
		wp_register_script('qsot-frontend-calendar', self::$o->core_url.'assets/js/features/calendar/calendar.js', array('fullcalendar'), '0.1.0-beta');
		wp_register_style('qsot-frontend-calendar-style', self::$o->core_url.'assets/css/features/calendar/calendar.css', array('fullcalendar'), '0.1.0-beta');
		wp_register_script('qsot-admin-calendar', self::$o->core_url.'assets/js/features/calendar/admin.js', array('jquery-ui-datepicker'), '0.1.0-beta');
	}

	// load the admin js and css
	public static function load_admin_assets($exists, $post_id) {
		wp_enqueue_script('qsot-admin-calendar');
		wp_enqueue_style('qsot-admin-styles');
	}

	public static function add_sidebar() {
		if (is_dynamic_sidebar()) {
			$slug = sanitize_title('qsot-calendar');
			global $wp_registered_sidebars;
			if (!isset($wp_registered_sidebars[$slug])) {
				$a = array(
					'id' => $slug,
					'name' => __('Non-JS Calendar Page','opentickets-community-edition'),
					'description' => __('Widget area on calendar template that shows when a user does not have javascript enabled.','opentickets-community-edition'),
					'before_widget' => '<div id="%1$s" class="widget %SPAN% %2$s"><div class="widget-inner">',
					'after_widget' => '</div><div class="clear"></div></div>',
					'before_title' => '<h3 class="widgettitle">',
					'after_title' => '</h3>',
				);
				register_sidebar($a);
			}

			/* @@@ NICE TO HAVE - get widget auto assignment to work
			$default_widget_class = 'qsot_upcoming_shows_widget';
			global $wp_registered_widgets, $sidebars_widgets;
			if (true || !isset($sidebars_widgets[$slug])) {
				$pos = array();
				foreach (array_keys($wp_registered_widgets) as $key) {
					if (($name = preg_replace('#^'.$default_widget_class.'(.*)$#', '\1', $key)) != $key) {
						$pos[] = empty($name) ? '-1' : $name;
					}
				}
				$pos = array_filter($pos);
				$wslug = $default_widget_class;
				if (!empty($pos)) {
					$wslug .= (min($pos)-1);
				}
				die(__log($sidebars_widgets[$slug], $wp_registered_widgets[$sidebars_widgets[$slug][0]]));
			}
			*/
		}
	}

	public static function add_assets() {
		if (is_admin()) return;

		$post = get_post();
		if (!is_object($post)) return;

		$needs_calendar = ($post->post_type == 'page' && $post->ID == get_option('qsot_calendar_page_id', ''));

		if (!$needs_calendar) $needs_calendar = (bool)preg_match('#qsot-calendar\.php$#', get_post_meta($post->ID, '_wp_page_template', true));

		if (!$needs_calendar) $needs_calendar = (bool)preg_match('#\['.self::$shortcode.'[^\[\]]*\]#', $post->post_content);

		if ($needs_calendar) {
			wp_enqueue_script('qsot-frontend-calendar');
			wp_enqueue_style('qsot-frontend-calendar-style');

			do_action('qsot-calendar-settings', $post, $needs_calendar, self::$shortcode);
		}
	}

	// create a metabox that holds the extended settings for the calendar page
	public static function add_meta_boxes() {
		$screens = array('page');
		foreach ($screens as $screen) {
			add_meta_box(
				'qsot-calendar-settings-box',
				__('Calendar Settings','opentickets-community-edition'),
				array(__CLASS__, 'mb_calendar_settings'),
				$screen,
				'side',
				'core'
			);
		}
	}

	public static function calendar_settings($post, $needs_calendar=true, $shortcode='') {
		$time = microtime(true);
		wp_localize_script('qsot-frontend-calendar', '_qsot_event_calendar_ui_settings', apply_filters('qsot-event-calendar-ui-settings', array(
			'ajaxurl' => add_query_arg(array(
				'qscal' => 'events',
				't' => strrev($time),
				'v' => md5($time.NONCE_KEY),
			), admin_url('/admin-ajax.php')),
			'event_template' => self::_get_event_template(),
			'gotoDate' => self::_get_calendar_start_date($post),
		), $post));
	}

	public static function intercept_cal_ajax() {
		if (isset($_GET['qscal'], $_GET['t'], $_GET['v'])) {
			$t = strrev($_GET['t']);
			if (md5($t.NONCE_KEY) == $_GET['v']) {
				self::_handle_ajax();
				die();
			}
		}
	}

	public static function shortcode($atts) {
		return '<div class="calendar event-calendar"></div>';
	}

	protected static function _get_event_template() {
		return '<div class="event-item">'
				.'<div class="heading"></div>'
				.'<div class="meta"></div>'
				.'<div class="img"></div>'
			.'</div>';
	}

	protected static function _handle_ajax() {
		$parents = $final = array();

		$args = array(
			'post_type' => self::$o->core_post_type,
			'post_status' => array( 'publish' ),
			'posts_per_page' => -1,
			'post_parent__not' => 0,
			'suppress_filters' => false,
		);
		if (isset($_REQUEST['start'])) $args['start_date_after'] = date('Y-m-d H:i:s', $_REQUEST['start']);
		if (isset($_REQUEST['end'])) $args['start_date_before'] = date('Y-m-d H:i:s', $_REQUEST['end']);
		if (isset($_REQUEST['priced_like'])) $args['priced_like'] = (int)$_REQUEST['priced_like'];
		if (isset($_REQUEST['has_price'])) $args['has_price'] = $_REQUEST['has_price'];
		if ( apply_filters( 'qsot-show-hidden-events', current_user_can( 'edit_posts' ) ) ) $args['post_status'][] = 'hidden';
		if ( current_user_can( 'read_private_posts' ) ) $args['post_status'][] = 'private';

		$events = get_posts($args);
		foreach ($events as $event) {
			$tmp = apply_filters('qsot-calendar-event', false, $event);
			if ($tmp !== false)
				$final[] = $tmp;
		}
		
		header('Content-Type: text/json');
		echo @json_encode($final);
	}

	public static function get_calendar_event($current, $event) {
		if (!is_object($event) || !isset($event->post_parent, $event->post_title, $event->ID, $event->meta)) return $current;

		if (isset($parents["{$event->post_parent}"])) $par = $parents["{$event->post_parent}"];
		else $par = $parents["{$event->post_parent}"] = get_post($event->post_parent);

		$e = array(
			'title' => apply_filters('the_title', is_object($par) && isset($par->post_title) ? $par->post_title : $event->post_title),
			'start' => get_post_meta($event->ID, self::$o->{'meta_key.start'}, true),
			'url' => get_permalink($event->ID),
			'img' => get_the_post_thumbnail($event->ID),
			'available' => $event->meta->available,
			'capacity' => $event->meta->capacity,
			'avail-words' => $event->meta->availability,
			'status' => $event->post_status,
			'protected' => $event->post_password ? 1 : 0,
			'passed' => false,
		);
		$e['_start'] = strtotime($e['start']);
		if (!apply_filters('qsot-can-sell-tickets-to-event', false, $event->ID)) { //$e['_start'] < current_time('timestamp')) {
			$e['avail-words'] = __('Ended','opentickets-community-edition');
			$e['passed'] = true;
		}
		if (is_admin()) {
			$e['id'] = $event->ID;
		}

		return $e;
	}

	// actually save the calendar settings
	public static function save_page_calendar_settings($post_id, $post) {
		if (isset($_POST['_calendar_start_method'])) {
			update_post_meta($post_id, '_calendar_start_method', $_POST['_calendar_start_method']);
		}

		if (isset($_POST['_calendar_start_manual'])) {
			update_post_meta($post_id, '_calendar_start_manual', $_POST['_calendar_start_manual']);
		}
	}

	// hide the metabox by default if the qsot-calendar.php template is not the selected template
	public static function mb_calendar_settings_classes($classes) {
		$template = get_page_template_slug( get_queried_object_id() );
		if ($template == 'qsot-calendar.php') $classes[] = 'hide-if-js';
		return $classes;
	}

	// actually draw the settings metabox for the calendar page
	public static function mb_calendar_settings($post, $mb) {
		// generate a list of valid options for the calendar starting date modes
		$valid = apply_filters('qsot-calendar-modes', array(
			'today' => __('Today','opentickets-community-edition'), // starts the calendar at today, when the calendar page is loaded
			'first' => __('Date of next Event','opentickets-community-edition'), // starts the calendar at the date of the first event, when calendar is loaded
			'manual' => __('Manually entered date','opentickets-community-edition'), // uses a manually entered date as the start date of the calendar when it is loaded
		));

		// get the current settings
		$method = get_post_meta($post->ID, '_calendar_start_method', true);
		$date = get_post_meta($post->ID, '_calendar_start_manual', true);

		// default the settings to 'today' (above)
		$method = isset($valid[$method]) ? $method : 'today';

		// draw the form
		?>
			<p><strong><?php _e('Starting Date Method','opentickets-community-edition') ?></strong></p>

			<p>
				<?php foreach ($valid as $meth => $label): ?>
					<input type="radio" name="_calendar_start_method" class="qsot-cal-meth" id="qsot-cal-meth-<?php echo esc_attr($meth) ?>"
						value="<?php echo esc_attr($meth) ?>" <?php checked($method, $meth) ?> />
					<label class="screen-reader-text" for="qsot-cal-meth-<?php echo esc_attr($meth) ?>"><?php echo force_balance_tags($label) ?></label>
					<span class="cb-display"><?php echo force_balance_tags($label) ?></span></br>
				<?php endforeach; ?>
			</p>

			<div class="hide-if-js extra-box" rel="extra-manual">
				<p><strong><?php _e('Manually entered date','opentickets-community-edition') ?></strong></p>
				<label class="screen-reader-text" for="qsot-cal-start-manual"><?php _e('Manually entered date','opentickets-community-edition') ?></label>
				<input size="11" type="text" class="use-datepicker" id="qsot-cal-start-manual" name="_calendar_start_manual" value="<?php echo esc_attr($date) ?>" />
			</div>

			<?php
				// allow plugins to add to this
				do_action('qsot-calendar-settings-metabox-extra', $post, $mb);
			?>
		<?php
	}

	protected static function _get_calendar_start_date($post) {
		// only process this for posts we can find
		if (!is_object($post)) $post = get_post();
		if (!is_object($post)) return;

		// generate a list of valid options for the calendar starting date modes
		$valid = apply_filters('qsot-calendar-modes', array(
			'today' => __('Today','opentickets-community-edition'), // starts the calendar at today, when the calendar page is loaded
			'first' => __('Date of next Event','opentickets-community-edition'), // starts the calendar at the date of the first event, when calendar is loaded
			'manual' => __('Manually entered date','opentickets-community-edition'), // uses a manually entered date as the start date of the calendar when it is loaded
		));

		// get the current settings
		$method = get_post_meta($post->ID, '_calendar_start_method', true);
		$date = get_post_meta($post->ID, '_calendar_start_manual', true);

		// default the settings to 'today' (above)
		$method = isset($valid[$method]) ? $method : 'today';

		switch ($method) {
			case 'first':
				$out = self::_next_event_date();
				break;
			case 'manual':
				$out = strtotime($date) < strtotime('today') ? current_time('mysql') : $date;
				break;
			default:
			case 'today':
				$out = date('Y-m-d');
				break;
		}

		return $out;
	}

	// determine the date of the next event, after today
	protected static function _next_event_date() {
		// get the next event id 
		$posts = get_posts(array(
			'post_type' => self::$o->core_post_type,
			'post_status' => 'publish',
			'post_parent__not' => 0,
			'posts_per_page' => 1,
			'meta_query' => array(
				array(
					'key' => self::$o->{'meta_key.start'},
					'value' => current_time('mysql'),
					'type' => 'DATETIME',
					'compare' => '>=',
				),
			),
			'meta_key' => self::$o->{'meta_key.start'},
			'orderby' => 'meta_value_date',
			'order' => 'asc',
			'fields' => 'ids',
			'suppress_filters' => false,
		));

		// get the id if it exists
		$post_id = !empty($posts) ? current($posts) : 0;

		// fetch the start date of the found event, and default it to today if it does not exist or has no start date
		$start = get_post_meta($post_id, self::$o->{'meta_key.start'}, true);
		$start = empty($start) || $start == '0000-00-00 00:00:00' ? current_time('mysql') : $start;

		return $start;
	}

	public static function create_calendar_page() {
		$page_id = get_option('qsot_calendar_page_id', 0);
		if (empty($page_id)) {
			$data = array(
				'post_title' => __('Event Calendar','opentickets-community-edition'),
				'post_name' => 'calendar',
				'post_content' => '',
				'post_status' => 'publish',
				'post_author' => 1,
				'post_type' => 'page',
			);
			$page_id = wp_insert_post($data);
			if (is_numeric($page_id) && !empty($page_id)) {
				update_post_meta($page_id, '_wp_page_template', 'qsot-calendar.php');
				update_post_meta($page_id, '_calendar_start_method', 'today');
				update_option('qsot_calendar_page_id', $page_id);
			}
		}
	}

	public static function add_pages($list) {
		$list[] = array(
			'title' => __('Event Pages','opentickets-community-edition'),
			'type' => 'title',
			'desc' => __('These pages are used to display the upcoming events.','opentickets-community-edition'),
			'id' => 'qsot-event-pages',
		);
		$list[] = array(
			'title' => __('Event Calendar','opentickets-community-edition'),
			'desc' => __('Page to display the calendar of upcoming events.','opentickets-community-edition'),
			'id' => 'qsot_calendar_page_id',
			'type' => 'single_select_page',
			'default' => '',
			'class' => 'chosen_select_nostd',
			'css' => 'min-width:300px;',
			'desc_tip' => true,
		);
		$list[] = array(
			'type' => 'sectionend',
			'id' => 'qsot-event-pages',
		);

		return $list;
	}
}

if (defined('ABSPATH') && function_exists('add_action')) {
	qsot_frontend_calendar::pre_init();
}