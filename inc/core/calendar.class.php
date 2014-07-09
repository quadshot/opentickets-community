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
			self::$options =& $options_class_name::instance();
			//self::_setup_admin_options();
		}

		$settings_class_name = apply_filters('qsot-settings-class-name', '');
		if (!empty($settings_class_name)) {
			self::$o =& $settings_class_name::instance();

			add_filter('woocommerce_page_settings', array(__CLASS__, 'add_pages'), 10, 1);
			add_filter('init', array(__CLASS__, 'register_assets'), 10);

			add_action('init', array(__CLASS__, 'add_sidebar'), 11);
			add_filter('init', array(__CLASS__, 'intercept_cal_ajax'), 1000, 1);
			add_filter('wp', array(__CLASS__, 'add_assets'), 10000);
			add_action('qsot-calendar-settings', array(__CLASS__, 'calendar_settings'), 10, 3);
			add_filter('qsot-calendar-event', array(__CLASS__, 'get_calendar_event'), 10, 2);
			add_shortcode(self::$shortcode, array(__CLASS__, 'shortcode'));

			add_filter('qsot-templates-page-templates', array(__CLASS__, 'add_calendar_template'));
		}
	}

	public static function add_calendar_template($list) {
		$list['qsot-calendar.php'] = 'OpenTickets Calendar';
		return $list;
	}

	public static function register_assets() {
		$suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';
		wp_register_script('qsot-frontend-calendar', self::$o->core_url.'assets/js/features/calendar/calendar.js', array('fullcalendar'), '0.1.0-beta');
		wp_register_style('qsot-frontend-calendar-style', self::$o->core_url.'assets/css/features/calendar/calendar.css', array('fullcalendar'), '0.1.0-beta');
	}

	public static function add_sidebar() {
		if (is_dynamic_sidebar()) {
			$slug = sanitize_title('qsot-calendar');
			global $wp_registered_sidebars;
			if (!isset($wp_registered_sidebars[$slug])) {
				$a = array(
					'id' => $slug,
					'name' => 'Non-JS Calendar Page',
					'description' => 'Widget area on calendar template that shows when a user does not have javascript enabled.',
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
		$post = get_post();
		$needs_calendar = ($post->post_type == 'page' && $post->ID == get_option('qsot_calendar_page_id', ''));

		if (!$needs_calendar) $needs_calendar = (bool)preg_match('#\['.self::$shortcode.'[^\[\]]*\]#', $post->post_content);

		if ($needs_calendar) {
			wp_enqueue_script('qsot-frontend-calendar');
			wp_enqueue_style('qsot-frontend-calendar-style');

			do_action('qsot-calendar-settings', $post, $needs_calendar, self::$shortcode);
		}
	}

	public static function calendar_settings($post, $needs_calendar=true, $shortcode='') {
		$time = microtime(true);
		wp_localize_script('qsot-frontend-calendar', '_qsot_event_calendar_ui_settings', apply_filters('qsot-event-calendar-ui-settings', array(
			'ajaxurl' => add_query_arg(array(
				'qscal' => 'events',
				't' => strrev($time),
				'v' => md5($time.NONCE_KEY),
			), is_admin() ? admin_url('/admin-ajax.php') : site_url('/wp-load.php')),
			'event_template' => self::_get_event_template(),
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
			'post_status' => 'publish',
			'posts_per_page' => -1,
			'post_parent__not' => 0,
			'suppress_filters' => false,
		);
		if (isset($_REQUEST['start'])) $args['start_date_after'] = date('Y-m-d H:i:s', $_REQUEST['start']);
		if (isset($_REQUEST['end'])) $args['start_date_before'] = date('Y-m-d H:i:s', $_REQUEST['end']);
		if (isset($_REQUEST['priced_like'])) $args['priced_like'] = (int)$_REQUEST['priced_like'];
		if (isset($_REQUEST['has_price'])) $args['has_price'] = $_REQUEST['has_price'];
		if (current_user_can('see_hidden_events')) $args['post_status'] = array('hidden', 'publish');

		//add_action('pre_get_posts', function(&$q) { die(__log('aldkjfalj', $q)); }, PHP_INT_MAX);
		//add_filter('posts_request', function($R) { die(__log($R)); }, 10);
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
			'passed' => false,
		);
		$e['_start'] = strtotime($e['start']);
		if (!apply_filters('qsot-can-sell-tickets-to-event', false, $event->ID)) { //$e['_start'] < current_time('timestamp')) {
			$e['avail-words'] = 'Ended';
			$e['passed'] = true;
		}
		if (is_admin()) {
			$e['id'] = $event->ID;
		}

		return $e;
	}

	public static function create_calendar_page() {
		$page_id = get_option('qsot_calendar_page_id', 0);
		if (empty($page_id)) {
			$data = array(
				'post_title' => 'Event Calendar',
				'post_name' => 'calendar',
				'post_content' => '',
				'post_status' => 'publish',
				'post_author' => 1,
				'post_type' => 'page',
			);
			$page_id = wp_insert_post($data);
			if (is_numeric($page_id) && !empty($page_id)) {
				update_post_meta($page_id, '_wp_page_template', 'qsot-calendar.php');
				update_option('qsot_calendar_page_id', $page_id);
			}
		}
	}

	public static function add_pages($list) {
		$list[] = array(
			'title' => __('Event Pages', 'qsot'),
			'type' => 'title',
			'desc' => __('These pages are used to display the upcoming events.', 'qsot'),
			'id' => 'qsot-event-pages',
		);
		$list[] = array(
			'title' => __('Event Calendar', 'qsot'),
			'desc' => __('Page to display the calendar of upcoming events.', 'qsot'),
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
