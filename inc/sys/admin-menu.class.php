<?php if ( __FILE__ == $_SERVER['SCRIPT_FILENAME'] ) die( header( 'Location: /') );

class qsot_admin_menu {
	protected static $o = array();
	protected static $options = array();
	protected static $menu_slugs = array(
		'main' => 'opentickets',
		'settings' => 'opentickets-settings',
		'documentation' => 'opentickets-documentation',
		'videos' => 'opentickets-documentation',
	);
	protected static $menu_page_hooks = array(
		'main' => 'toplevel_page_opentickets',
		'settings' => 'opentickets_page_opentickets-settings',
	);
	protected static $menu_page_uri = '';

	// container for the reports page object
	protected static $reports = null;

	public static function pre_init() {
		$settings_class_name = apply_filters('qsot-settings-class-name', '');
		if (!empty($settings_class_name)) {
			self::$o = call_user_func_array(array($settings_class_name, "instance"), array());

			$options_class_name = apply_filters('qsot-options-class-name', '');
			if (!empty($options_class_name)) {
				self::$options = call_user_func_array(array($options_class_name, "instance"), array());
				self::_setup_admin_options();
			}

			self::$menu_page_uri = add_query_arg(array('page' => self::$menu_slugs['main']), 'admin.php');

			add_action('init', array(__CLASS__, 'register_assets'), 0);
			add_action('init', array(__CLASS__, 'register_post_types'), 1);

			add_action('qsot-activate', array(__CLASS__, 'on_activation'), 10);

			// allow some core woocommerce assets to be loaded on our pages
			add_filter( 'woocommerce_screen_ids', array( __CLASS__, 'load_woocommerce_admin_assets' ), 10 );
			add_filter( 'woocommerce_reports_screen_ids', array( __CLASS__, 'load_woocommerce_admin_assets' ), 10 );
			// get the uri/hook/slug of our settings pages for use in asset enqueuing and such
			add_filter( 'qsot-get-menu-page-uri', array( __CLASS__, 'menu_page_uri' ), 10, 3 );
			add_filter( 'qsot-get-menu-slug', array( __CLASS__, 'menu_page_slug' ), 10, 2 );

			add_action('admin_menu', array(__CLASS__, 'create_menu_items'), 11);
			add_action('admin_menu', array(__CLASS__, 'repair_menu_order'), PHP_INT_MAX);
			add_action( 'admin_menu', array( __CLASS__, 'external_links' ), PHP_INT_MAX );
			add_action('qsot_daily_stats', array(__CLASS__, 'daily_stats'), 1000);
			add_action('activate_plugin', array(__CLASS__, 'incremental_stats'), 1000, 2);
			add_action('deactivate_plugin', array(__CLASS__, 'incremental_stats'), 1000, 2);
			add_action('switch_theme', array(__CLASS__, 'incremental_stats'), 1000, 2);

			// when saving settings, we could have updated the /qsot-event/ url slug... so we need to updating the permalinks on page refresh
			add_action( 'qsot-settings-save-redirect', array( __CLASS__, 'refresh_permalinks_on_save_uri' ), 10, 2 );
			add_action( 'admin_init', array( __CLASS__, 'refresh_permalinks_on_save_page_refresh' ), 1 );

			if (is_admin()) {
				add_action('admin_enqueue_scripts', array(__CLASS__, 'nag_stats'), 1000);
				add_action('wp_ajax_qsot-nag', array(__CLASS__, 'handle_nag_ajax'), 1000);
				self::_check_cron();
			}
		}
	}

	// register the assets needed by our plugin in the admin
	public static function register_assets() {
		// the js to handle the analytics nag
		wp_register_script( 'qsot-nag', self::$o->core_url . 'assets/js/admin/nag.js', array( 'qsot-tools' ), self::$o->version );

		// used on the various settings pages
		wp_register_script( 'qsot-admin-settings', self::$o->core_url . 'assets/js/admin/settings-page.js', array( 'qsot-tools', 'iris' ), self::$o->version );
		wp_register_style( 'qsot-admin-settings', self::$o->core_url . 'assets/css/admin/settings-page.css', array(), self::$o->version );
	}

	public static function load_woocommerce_admin_assets($list) {
		return array_unique(array_merge($list, array_values(self::$menu_page_hooks)));
	}

	// fetch the page slug for a given settings page
	public static function menu_page_slug( $current, $which='main' ) {
		return ( ! empty( $which ) && is_scalar( $which ) && isset( self::$menu_slugs[ $which ] ) ) ? self::$menu_slugs[ $which ] : self::$menu_slugs['main'];
	}

	// fetch the page uri for a settings page in our plugin
	public static function menu_page_uri( $current, $which='main', $omit_hook=false ) {
		$page_slug = isset( self::$menu_slugs['main'] ) ? self::$menu_slugs['main'] : '';
		// figure out the slug for the page
		if ( ! empty( $which ) && is_scalar( $which ) && isset( self::$menu_slugs[ $which ] ) )
			$page_slug = self::$menu_slugs[ $which ];

		// if we are just looking for the page uri, and not the uri and hook, then just return the uri now
		if ( $omit_hook )
			return add_query_arg( array( 'page' => $page_slug ), 'admin.php' );

		// otherwise return both
		return array(
			add_query_arg( array( 'page' => $page_slug ), 'admin.php' ),
			isset( self::$menu_page_hooks[ $which ] ) ? self::$menu_page_hooks[ $which ] : ''
		);
	}

	public static function register_post_types() {
		// generate a list of post types and post type settings to create. allow external plugins to modify this. why? because of multiple reasons. 1) this process calls a syntaxically different
		// method of defining post types, that has a slightly different set of defaults than the normal method, which may be preferred over the core method of doing so. 2) external plugins may
		// want to brand the name of the post differently. 3) external plugins may want to tweak the settings of the pos type for some other purpose. 4) sub plugins/external plugins may have
		// additional post types that need to be declared at the same time as the core post types. 5) make up your own reasons
		$core = apply_filters('qsot-events-core-post-types', array());

		// if there are post types to create, then create them
		if (is_array($core) && !empty($core))
			foreach ($core as $slug => $args) self::_register_post_type($slug, $args);
	}

	public static function refresh_permalinks_on_save_uri( $uri, $page ) {
		if ( 'general' == $page ) {
			$uri = add_query_arg( array( 'refresh-permalinks' => wp_create_nonce( 'refresh-now/qsot' ) ) );
		}

		return $uri;
	}

	public static function refresh_permalinks_on_save_page_refresh() {
		if ( isset( $_GET['refresh-permalinks'] ) ) {
			if ( wp_verify_nonce( $_GET['refresh-permalinks'], 'refresh-now/qsot' ) ) {
				global $wp_rewrite;
				flush_rewrite_rules();
				$wp_rewrite->rewrite_rules();
			}
			wp_safe_redirect( remove_query_arg( array( 'refresh-permalinks' ) ) );
			exit;
		}
	}

	public static function repair_menu_order() {
		global $menu;

		$core = apply_filters('qsot-events-core-post-types', array());
		foreach ($core as $k => $v) {
			$core[$k]['__name'] = is_array($v['label_replacements']) && isset($v['label_replacements'], $v['label_replacements']['plural'])
				? $v['label_replacements']['plural']
				: ucwords(preg_replace('#[-_]+#', ' ', $k));
		}

		foreach ($menu as $ind => $m) {
			foreach ($core as $k => $v) {
				if (strpos($m[2], 'post_type='.$k) !== false && $m[0] === $v['__name']) {
					$pos = isset($v['args'], $v['args']['menu_position']) ? $v['args']['menu_position'] : false;
					if (!empty($pos) && $pos != $ind) {
						$menu["$pos"] = $m;
						unset($menu["$ind"]);
						break;
					}
				}
			}
		}
	}

	// create the external links on our menu, which currently can open in a new window
	// done this way, because currently there is no mechanism to make admin menu items open a new tab!!! wth
	public static function external_links() {
		global $menu, $submenu;

		// if out opentickets menu exists
		if ( isset( $submenu['opentickets'] ) ) {
			// add a documentation link
			$submenu['opentickets'][] = array(
				sprintf( __( 'Documentation %s', 'opentickets-community-edition' ), '<span class="dashicons dashicons-external"></span>' ),
				'manage_options',
				"http://opentickets.com/documentation/' target='_blank",
				sprintf( __( 'Documentation %s', 'opentickets-community-edition' ), '' ),
				'otce-external-link otce-documentation'
			);

			// add a videos link
			$submenu['opentickets'][] = array(
				sprintf( __( 'Videos %s', 'opentickets-community-edition' ), '<span class="dashicons dashicons-external"></span>' ),
				'manage_options',
				"http://opentickets.com/videos/' target='_blank",
				sprintf( __( 'Videos %s', 'opentickets-community-edition' ), '' ),
				'otce-external-link otce-videos'
			);
		}
	}

	// register our custom menu items for our settings pages
	public static function create_menu_items() {
		// make the main menu item
		self::$menu_page_hooks['main'] = add_menu_page(
			self::$o->product_name,
			self::$o->product_name,
			'view_woocommerce_reports',
			self::$menu_slugs['main'],
			array( __CLASS__, 'ap_reports_page' ),
			false,
			21
		);

		// reports menu item
		self::$menu_page_hooks['main'] = add_submenu_page(
			self::$menu_slugs['main'],
			__( 'Reports', 'opentickets-community-edition' ),
			__( 'Reports', 'opentickets-community-edition' ),
			'view_woocommerce_reports',
			self::$menu_slugs['main'],
			array( __CLASS__, 'ap_reports_page' ),
			false,
			21
		);

		// settings menu item
		self::$menu_page_hooks['settings'] = add_submenu_page(
			self::$menu_slugs['main'],
			__( 'Settings', 'opentickets-community-edition' ),
			__( 'Settings', 'opentickets-community-edition' ),
			'manage_options',
			self::$menu_slugs['settings'],
			array( __CLASS__, 'ap_settings_page' )
		);

		// generic function to call some page load logic
		add_action( 'load-' . self::$menu_page_hooks['main'], array( __CLASS__, 'ap_reports_page_head' ) );
		add_action( 'load-' . self::$menu_page_hooks['settings'], array( __CLASS__, 'ap_settings_page_head' ) );
	}

	// get the reports page object
	protected static function _reports_page() {
		// if the page was already loaded, the return it
		if ( is_object( self::$reports ) )
			return self::$reports;

		// otherwise load it
		return self::$reports = require_once( 'admin-reports.php' );
	}

	// page load logic for the reports page
	public static function ap_reports_page_head() {
		$reports = self::_reports_page();
		$reports->on_load();
	}

	// draw the reports page
	public static function ap_reports_page() {
		$reports = self::_reports_page();
		$reports->output();
	}

	public static function vit($v) {
		$p = explode('.', preg_replace('#[^\d]+#', '.', preg_replace('#[a-z]#i', '', $v)));
		return sprintf('%03s%03s%03s', array_shift($p), array_shift($p), array_shift($p));
	}

	// parts of this are copied directly from woocommerce/admin/woocommerce-admin-settings.php
	// the general method is identical, save for the naming
	public static function ap_settings_page() {
		require_once 'admin-settings.php';
		qsot_admin_settings::output();
	}

	public static function ap_settings_page_head() {
		global $current_tab, $current_section;
		require_once 'admin-settings.php';

		// Include settings pages
		qsot_admin_settings::get_settings_pages();

		// Get current tab/section
		$current_tab     = empty( $_GET['tab'] ) ? 'general' : sanitize_title( $_GET['tab'] );
		$current_section = empty( $_REQUEST['section'] ) ? '' : sanitize_title( $_REQUEST['section'] );

		if (empty($_POST)) return;

		qsot_admin_settings::save();
	}

	protected static function _get_reports_charts() {
		$charts = array();

		return apply_filters( 'qsot_reports_charts', $charts );
	}

	public static function nag_stats() {
		$can = current_user_can('manage_options');
		$allowed_already = self::$options->{'qsot-allow-stats'} == 'yes';
		$dismissed = get_user_option('_qsot_info_nag');
		if ($dismissed || $allowed_already || !$can) return;

		wp_enqueue_script('qsot-nag');
		wp_localize_script('qsot-nag', '_qsot_nag_settings', array(
			'title' => __('Allow OpenTickets Stats?','opentickets-community-edition'),
			'question' => __('OpenTickets is always being improved, thanks to users like you. To help us improve this product, would you allow us collect some basic information about your installation, like many others already have? We will not track any of your users\' information, so your security and privacy is still safe. The information we will be tracking is purely about your WordPress installation, and will be used to help us understand which plugins and themes we need to be compatible with.','opentickets-community-edition'),
			'answers' => array(
				array(
					'type' => 'button',
					'label' => esc_attr(__('Yes','opentickets-community-edition')),
					'location' => 'right',
					'action' => 'allow',
					'class' => 'button-primary',
				),
				array(
					'type' => 'link',
					'label' => esc_attr(__('Dismiss','opentickets-community-edition')),
					'location' => 'left',
					'action' => 'dismiss',
				),
			),
			'layout' => '<div class="qsot-nag-box" rel="qsot-nag"><div class="nag-box-inner">'
					.'<div class="nag-title" rel="title"></div>'
					.'<div class="nag-content" rel="content"></div><div class="clear"></div>'
					.'<div class="nag-answers" rel="answers">'
						.'<div class="left" rel="left"></div>'
						.'<div class="right" rel="right"></div>'
					.'</div>'
					.'<div class="clear"></div>'
				.'</div></div>',
		));
	}

	public static function handle_nag_ajax() {
		if (!is_user_logged_in() || !current_user_can('manage_options')) return;
		if (empty($_POST)) return;

		$u = wp_get_current_user();
		$post = wp_parse_args($_POST, array(
			'sa' => 'nothing',
		));
		$sa = $post['sa'];
		$out = array();

		switch ($sa) {
			case 'dismiss':
				update_user_option($u->ID, '_qsot_info_nag', '1');
				$out['msg'] = __('Fair enough. Thanks anyways.','opentickets-community-edition');
			break;

			case 'allow':
				update_user_option($u->ID, '_qsot_info_nag', '1');
				self::$options->{'qsot-allow-stats'} = 'yes';
				$out['msg'] = __('Thanks a bunch.','opentickets-community-edition');
				self::send_all_stats();
			break;
		}

		echo @json_encode($out);
		exit;
	}

	public static function daily_stats() {
		if (self::$options->{'qsot-allow-stats'} == 'yes') self::send_out();
	}

	public static function incremental_stats() {
		if (self::$options->{'qsot-allow-stats'} == 'yes') self::send_all_stats();
	}

	public static function send_all_stats() {
		$fields = array(
			'Title',
			'Author',
			'Author Name',
			'Author URI',
			'Description',
			'Version',
			'Status',
			'Template',
			'Stylesheet',
			'Template Files',
			'Stylesheet Files',
			'Template Dir',
			'Stylesheet Dir',
			'Screenshot',
			'Tags',
			'Theme Root',
			'Theme Root URI',
			'Parent Theme'
		);
		$only_keys = array(
			'Template Files' => 1,
			'Stylesheet Files' => 1
		);
		$current_theme = wp_get_theme();
		$current_theme_title = $current_theme->offsetGet('Title');
		$raw_themes = wp_get_themes();
		$themes = array();
		// get all theme data for all themes
		foreach ( $raw_themes as $theme ) {
			$trecord = array();
			// cycle through the fields we want to capture
			foreach ( $fields as $field ) {
				// find this theme's value or the given field
				$theme_offset = $theme->offsetGet( $field );

				// normalize that value into something meaningful
				$trecord[$field] = isset( $only_keys[ $field ] ) && is_array( $theme_offset ) ? array_keys( $theme_offset ) : $theme_offset;
			}
			$trecord['!!ACTIVE!!'] = (int)($theme->offsetGet('Title') == $current_theme_title);
			$themes[$trecord['Title']] = $trecord;
		}

		$headers = array(
			'qsot-wp' => self::vit(self::$o->{'wp_version'}),
			'qsot-v' => self::vit(self::$o->{'version'}),
			'qsot-wc' => self::$o->{'wc_version'},
			'qsot-php' => self::$o->{'php_version'},
		);

		self::send_out($headers, array('p' => @json_encode(get_option('active_plugins')), 't' => @json_encode($themes)));
	}

	public static function send_out($headers=array(), $post=array()) {
		$headers = is_array($headers) ? $headers : array();
		$headers['qsot-site-url'] = site_url();

		$post = is_array($post) ? $post : array();
		$post['i'] = apply_filters('qsot-count-tickets', 0, array('state' => 'confirmed'));

		$res = wp_remote_post(
			'http://opentickets.com/tr/',
			array(
				'timeout' => 0.1,
				'httpversion' => '1.1',
				'blocking' => false,
				'headers' => $headers,
				'body' => $post,
			)
		);
	}

	protected static function _check_cron() {
		$ts = wp_next_scheduled('qsot_daily_stats');
		if ($ts === false)
			wp_schedule_event(strtotime('tomorrow'), 'daily', 'qsot_daily_stats');
	}

	protected static function _register_post_type($slug, $pt) {
		$labels = array(
			'name' => '%plural%',
			'singular_name' => '%singular%',
			'add_new' => __('Add %singular%','opentickets-community-edition'),
			'add_new_item' => __('Add New %singular%','opentickets-community-edition'),
			'edit_item' => __('Edit %singular%','opentickets-community-edition'),
			'new_item' => __('New %singular%','opentickets-community-edition'),
			'all_items' => __('All %plural%','opentickets-community-edition'),
			'view_item' => __('View %singular%','opentickets-community-edition'),
			'search_items' => __('Search %plural%','opentickets-community-edition'),
			'not_found' =>  __('No %lplural% found','opentickets-community-edition'),
			'not_found_in_trash' => __('No %lplural% found in Trash','opentickets-community-edition'),
			'parent_item_colon' => '',
			'menu_name' => '%plural%'
		);

		$args = array(
			'public' => false,
			'show_ui' => true,
			'menu_position' => 22,
			'supports' => array(
				'title',
				'thumbnail',
			),
			'register_meta_box_cb' => false,
			'permalink_epmask' => EP_PAGES,
		);

		$sr = array();
		if (isset($pt['label_replacements'])) {
			foreach ($pt['label_replacements'] as $k => $v) {
				$sr['%'.$k.'%'] = $v;
				$sr['%l'.$k.'%'] = strtolower($v);
			}
		} else {
			$name = ucwords(preg_replace('#[-_]+#', ' ', $slug));
			$sr = array(
				'%plural%' => $name.'s',
				'%singular%' => $name,
				'%lplural%' => strtolower($name.'s'),
				'%lsingular%' => strtolower($name),
			);
		}
		
		foreach ($labels as $k => $v) $labels[$k] = str_replace(array_keys($sr), array_values($sr), $v);

		if (isset($pt['args']) && (is_string($pt['args']) || is_array($pt['args']))) $args = wp_parse_args($pt['args'], $args);

		$args['labels'] = $labels;
		// slightly different than normal. core WP does not tell the register_meta_box_cb() function the post type, which i think is wrong. it is not relevant here, but what if you
		// have a list of post types that are similar, or a dynamic list of post types of which you do not know all the information of. think of a situation where they were all 
		// so similar that the only difference in the metabox that we defined was the title of the metabox, the content of it was identical, but the title was dependent on the post type.
		// why should you create 3 different functions that declare the exact same metabox, with the exception of the title of the metabox, when it could easily be solved in a single
		// function if you know the post type. i think it is an oversight, and should be considered as a core change. despite that, my method adds that as a second param to the function,
		// assuming we can actually do it. otherwise the passed function is just passed through as is.

		/* do we even need this now? parameter 1 is 'post' .... which has the post type
		if (is_callable($args['register_meta_box_cb'])) {
			// >= PHP5.3.0
			if (self::$o->anonfuncs) {
				$args['register_meta_box_cb'] = function($post) use ($slug, $args) { return call_user_func_array($args['register_meta_box_cb'], array($post, $slug)); };
			// < PHP5.3.0
			} else if (is_string($args['register_meta_box_cb']) || (is_array($args['register_meta_box_cb']) && count($args['register_meta_box_cb']) == 2 && is_string($args['register_meta_box_cb'][0]))) {
				$args['register_meta_box_cb'] = create_function('$a', 'return call_user_func_array('
						.(is_string($args['register_meta_box_cb']) ? '"'.$args['register_meta_box_cb'].'"' : 'array("'.$args['register_meta_box_cb'][0].'", "'.$args['register_meta_box_cb'].'")')
					.', array($a, "'.$slug.'"));');
			}
		}
		*/

		register_post_type($slug, $args);
	}

	protected static function _setup_admin_options() {
		self::$options->def('qsot-allow-stats', 'no');
		self::$options->def( 'qsot-event-permalink-slug', self::$o->core_post_type );

		self::$options->add(array(
			'order' => 100,
			'type' => 'title',
			'title' => __('Global Settings','opentickets-community-edition'),
			'id' => 'heading-general-1',
		));

		self::$options->add(array(
			'order' => 101,
			'id' => 'qsot-allow-stats',
			'type' => 'checkbox',
			'title' => __( 'Allow Statistics', 'opentickets-community-edition' ),
			'desc' => __( 'Allow OpenTickets to gather information about your WordPress installation.', 'opentickets-community-edition' ),
			'desc_tip' => __( 'This information is strictly used to make this product better and more compatible with other plugins.', 'opentickets-community-edition' ),
			'default' => 'no',
		));

		self::$options->add(array(
			'order' => 103,
			'id' => 'qsot-event-permalink-slug',
			//'class' => 'i18n-multilingual', // cant do yet i dont think
			'type' => 'text',
			'title' => __( 'Event Link Slug', 'opentickets-community-edition' ),
			'desc' => __( 'The url slug that is prepended to the event name in the url. (ex: <code>http://example.com/<strong>event</strong>/my-event/</code>)', 'opentickets-community-edition' ),
			'desc_tip' => __( 'This is the segment of the url that preceeds the event name.', 'opentickets-community-edition' ),
			'default' => self::$options->{'qsot-event-permalink-slug'},
		));

		self::$options->add(array(
			'order' => 199,
			'type' => 'sectionend',
			'id' => 'heading-general-1',
		));
	}

	public static function on_activation() {
		self::register_post_types();
	}
}

if (defined('ABSPATH') && function_exists('add_action')) {
	qsot_admin_menu::pre_init();
}
