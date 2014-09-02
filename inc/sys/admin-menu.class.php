<?php (__FILE__ == $_SERVER['SCRIPT_FILENAME']) ? die(header('Location: /')) : null;

class qsot_admin_menu {
	protected static $o = array();
	protected static $options = array();
	protected static $menu_slugs = array(
		'main' => 'opentickets',
		'settings' => 'opentickets-settings',
	);
	protected static $menu_page_hooks = array(
		'main' => 'toplevel_page_opentickets',
		'settings' => 'opentickets_page_opentickets-settings',
	);
	protected static $menu_page_uri = '';

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

			add_filter('woocommerce_screen_ids', array(__CLASS__, 'load_woocommerce_admin_assets'), 10);
			add_filter('woocommerce_reports_screen_ids', array(__CLASS__, 'load_woocommerce_admin_assets'), 10);
			add_filter('qsot-get-menu-page-uri', array(__CLASS__, 'menu_page_uri'), 10, 3);
			add_filter('qsot-get-menu-slug', array(__CLASS__, 'menu_page_slug'), 10, 2);

			add_action('admin_menu', array(__CLASS__, 'create_menu_items'), 11);
			add_action('admin_menu', array(__CLASS__, 'rename_first_menu_item'), 11);
			add_action('admin_menu', array(__CLASS__, 'repair_menu_order'), PHP_INT_MAX);
			add_action('qsot_daily_stats', array(__CLASS__, 'daily_stats'), 1000);
			add_action('activate_plugin', array(__CLASS__, 'incremental_stats'), 1000, 2);
			add_action('deactivate_plugin', array(__CLASS__, 'incremental_stats'), 1000, 2);
			add_action('switch_theme', array(__CLASS__, 'incremental_stats'), 1000, 2);

			if (is_admin()) {
				add_action('admin_enqueue_scripts', array(__CLASS__, 'nag_stats'), 1000);
				add_action('wp_ajax_qsot-nag', array(__CLASS__, 'handle_nag_ajax'), 1000);
				self::_check_cron();
			}
		}
	}

	public static function register_assets() {
		wp_register_script('qsot-nag', self::$o->core_url.'assets/js/admin/nag.js', array('qsot-tools'), self::$o->version);
	}

	public static function load_woocommerce_admin_assets($list) {
		return array_unique(array_merge($list, array_values(self::$menu_page_hooks)));
	}

	public static function menu_page_slug($current, $which='main') {
		return !empty($which) && is_scalar($which) && isset(self::$menu_slugs[$which]) ? self::$menu_slugs[$which] : self::$menu_slugs['main'];
	}

	public static function menu_page_uri($current, $which='main', $omit_hook=false) {
		if (!empty($which) && is_scalar($which) && isset(self::$menu_slugs[$which])) $which = self::$menu_slugs[$which];
		if ($omit_hook) return add_query_arg(array('page' => $which), 'admin.php');
		return array(add_query_arg(array('page' => $which), 'admin.php'), isset(self::$menu_page_hooks[$which]) ? self::$menu_page_hooks[$which] : '');
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

	public static function create_menu_items() {
		self::$menu_page_hooks['main'] = add_menu_page(
			__('Reports', 'qsot'),
			self::$o->product_name,
			'view_woocommerce_reports',
			self::$menu_slugs['main'],
			array(__CLASS__, 'ap_reports_page'),
			false,
			21
		);

		self::$menu_page_hooks['settings'] = add_submenu_page(
			self::$menu_slugs['main'],
			__('Settings', 'qsot'),
			__('Settings', 'qsot'),
			'manage_options',
			self::$menu_slugs['settings'],
			array(__CLASS__, 'ap_settings_page')
		);

		add_action('load-'.self::$menu_page_hooks['settings'], array(__CLASS__, 'ap_settings_page_head'));
	}

	public static function rename_first_menu_item() {
		global $menu, $submenu;

		if (isset($submenu[self::$menu_slugs['main']], $submenu[self::$menu_slugs['main']][0])) {
			$submenu[self::$menu_slugs['main']][0][0] = 'Reports';
		}
	}

	public static function ap_reports_page() {
		$reports = require_once( 'admin-reports.php' );
		$reports->output();
		return;
		global $woocommerce;
		require_once( $woocommerce->plugin_path.'/admin/woocommerce-admin-reports.php' );

		$charts = self::_get_reports_charts();

		$first_tab       = array_keys( $charts );
		$current_tab     = isset( $_GET['tab'] ) ? sanitize_title( urldecode( $_GET['tab'] ) ) : $first_tab[0];
		$charts_tab_keys = array_keys( $charts[ $current_tab ]['charts'] );
		$current_chart   = isset( $_GET['chart'] ) ? sanitize_title( urldecode( $_GET['chart'] ) ) : current( $charts_tab_keys );
		?>
			<div class="wrap ot-reports-page">
				<?php if (!empty($charts)): ?>
					<div class="icon32 icon32-opentickets-reports" id="icon-opentickets"><br /></div><h2 class="nav-tab-wrapper woo-nav-tab-wrapper">
						<?php
							foreach ( $charts as $key => $chart ) {
								echo '<a href="'.admin_url( apply_filters('qsot-get-menu-page-uri', '', 'main', true).'&tab=' . urlencode( $key ) ).'" class="nav-tab ';
								if ( $current_tab == $key ) echo 'nav-tab-active';
								echo '">' . esc_html( $chart[ 'title' ] ) . '</a>';
							}
						?>
						<?php do_action('qsot_reports_tabs'); ?>
					</h2>

					<?php if ( sizeof( $charts[ $current_tab ]['charts'] ) > 1 ) {
						?>
						<ul class="subsubsub">
							<li><?php

								$links = array();

								foreach ( $charts[ $current_tab ]['charts'] as $key => $chart ) {

									$link = '<a href="'.apply_filters('qsot-get-menu-page-uri', '', 'main', true).'&tab=' . urlencode( $current_tab ) . '&amp;chart=' . urlencode( $key ) . '" class="';

									if ( $key == $current_chart ) $link .= 'current';

									$link .= '">' . $chart['title'] . '</a>';

									$links[] = $link;

								}

								echo implode(' | </li><li>', $links);

							?></li>
						</ul>
						<br class="clear" />
						<?php
					}

					if ( isset( $charts[ $current_tab ][ 'charts' ][ $current_chart ] ) ) {

						$chart = $charts[ $current_tab ][ 'charts' ][ $current_chart ];

						if ( ! isset( $chart['hide_title'] ) || $chart['hide_title'] != true )
							echo '<h3>' . $chart['title'] . '</h3>';

						if ( $chart['description'] )
							echo '<p>' . $chart['description'] . '</p>';

						$func = $chart['function'];
						if ( $func && ( is_callable( $func ) ) )
							call_user_func( $func );
					}
					?>
				<?php else: ?>
					<div class="icon32 icon32-opentickets icon32-opentickets-reports" id="icon-opentickets"><br /></div><h2 class="nav-tab-wrapper woo-nav-tab-wrapper">No Reports Available</h2>
					<div class="inside">
						<p>
							There are currently no reports available for <?php echo self::$o->product_name ?>.
						</p>
					</div>
				<?php endif; ?>
			</div>
		<?php
	}

	public static function vit($v) {
		$p = explode('.', preg_replace('#[^\d]+#', '.', preg_replace('#[a-z]#i', '', $v)));
		return sprintf('%s%03s%03s', 10*array_shift($p), 100*array_shift($p), 100*array_shift($p));
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
			'title' => __('Allow OpenTickets Stats?', 'qsot'),
			'question' => __('OpenTickets is always being improved, thanks to users like you. '
				.'To help us improve this product, would you allow us collect some basic information about your installation, like many others already have? '
				.'We will not track any of your users\' information, so your security and privacy is still safe. '
				.'The information we will be tracking is purely about your WordPress installation, '
				.'and will be used to help us understand which plugins and themes we need to be compatible with. ', 'qsot'),
			'answers' => array(
				array(
					'type' => 'button',
					'label' => esc_attr(__('Yes', 'qsot')),
					'location' => 'right',
					'action' => 'allow',
					'class' => 'button-primary',
				),
				array(
					'type' => 'link',
					'label' => esc_attr(__('Dismiss', 'qsot')),
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
				$out['msg'] = 'Fair enough. Thanks anyways.';
			break;

			case 'allow':
				update_user_option($u->ID, '_qsot_info_nag', '1');
				self::$options->{'qsot-allow-stats'} = 'yes';
				$out['msg'] = 'Thanks a bunch.';
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
		$fields = array('Title', 'Author', 'Author Name', 'Author URI', 'Description', 'Version', 'Status', 'Template', 'Stylesheet', 'Template Files', 'Stylesheet Files',
			'Template Dir', 'Stylesheet Dir', 'Screenshot', 'Tags', 'Theme Root', 'Theme Root URI', 'Parent Theme',
		);
		$only_keys = array('Template Files' => 1, 'Stylesheet Files' => 1,);
		$current_theme = wp_get_theme();
		$current_theme_title = $current_theme->offsetGet('Title');
		$raw_themes = get_themes();
		$themes = array();
		foreach ($raw_themes as $theme) {
			$trecord = array();
			foreach ($fields as $field) $trecord[$field] = isset($only_keys[$field]) ? array_keys($theme->offsetGet($field)) : $theme->offsetGet($field);
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
			'add_new' => 'Add %singular%',
			'add_new_item' => 'Add New %singular%',
			'edit_item' => 'Edit %singular%',
			'new_item' => 'New %singular%',
			'all_items' => 'All %plural%',
			'view_item' => 'View %singular%',
			'search_items' => 'Search %plural%',
			'not_found' =>  'No %lplural% found',
			'not_found_in_trash' => 'No %lplural% found in Trash', 
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

		self::$options->add(array(
			'order' => 101,
			'id' => 'qsot-allow-stats',
			'type' => 'checkbox',
			'title' => __('Allow Statistics', 'qsot'),
			'desc' => __('Allow OpenTickets to gather information about your WordPress installation.', 'qsot'),
			'desc_tip' => __('This information is strictly used to make this product better and more compatible with other plugins.', 'qsot'),
			'default' => 'no',
		));
	}
}

if (defined('ABSPATH') && function_exists('add_action')) {
	qsot_admin_menu::pre_init();
}
