<?php (__FILE__ == $_SERVER['SCRIPT_FILENAME']) ? die(header('Location: /')) : null;

if (!class_exists('QSOT')):

class QSOT {
	protected static $o = null; // holder for all options of the events plugin
	protected static $ajax = false;
	protected static $me = '';

	public static function pre_init() {
		// load the settings. theya re required for everything past this point
		$settings_class_name = apply_filters('qsot-settings-class-name', '');
		if (empty($settings_class_name)) return;
		self::$o =& $settings_class_name::instance();

		self::$me = plugin_basename(self::$o->core_file);

		// locale fix
		add_action('plugins_loaded', array(__CLASS__, 'locale'), 4);
		// inject our own autoloader before all others in case we need to overtake some woocommerce autoloaded classes down the line. this may not work with 100% of all classes
		// because we dont actually control the plugin load order, but it should suffice for what we may use it for. if it does not suffice at any time, then we will rethink this
		add_action('plugins_loaded', array(__CLASS__, 'prepend_overtake_autoloader'), 4);
		// load emails when doing ajax request. woocommerce workaround
		add_action('plugins_loaded', array(__CLASS__, 'why_do_i_have_to_do_this'), 4);

		// declare the includes loader function
		add_action('qsot-load-includes', array(__CLASS__, 'load_includes'), 10, 2);

		add_filter('qsot-search-results-group-query', array(__CLASS__, 'only_search_parent_events'), 10, 4);

		// load all other system features and classes used everywhere
		do_action('qsot-load-includes', 'sys');
		// load all other core features
		do_action('qsot-load-includes', 'core');
		// injection point by sub/external plugins to load their stuff, or stuff that is required to be loaded first, or whatever
		// NOTE: this would require that the code that makes use of this hook, loads before this plugin is loaded at all
		do_action('qsot-after-core-includes');

		// load all plugins and modules later on
		add_action('plugins_loaded', array(__CLASS__, 'load_plugins_and_modules'), 5);

		// register the activation function, so that when the plugin is activated, it does some magic described in the activation function
		register_activation_hook(self::$o->core_file, array(__CLASS__, 'activation'));

		// handle ajax
		self::$ajax = is_ajax();
		if (self::$ajax) {
			add_action('init', array(__CLASS__, 'handle_ajax'), 9, 1);
		}
		add_action('wp_head', array(__CLASS__, 'ajax_url'), 1);
		add_action('admin_head', array(__CLASS__, 'ajax_url'), 1);

		add_action('woocommerce_email_classes', array(__CLASS__, 'load_custom_emails'), 2);

		add_filter('woocommerce_locate_template', array(__CLASS__, 'overtake_some_woocommerce_core_templates'), 10, 3);
		add_action('admin_init', array(__CLASS__, 'register_base_admin_assets'), 10);
		add_action('admin_enqueue_scripts', array(__CLASS__, 'load_base_admin_assets'), 10);

		add_action('load-post.php', array(__CLASS__, 'load_assets'), 999);
		add_action('load-post-new.php', array(__CLASS__, 'load_assets'), 999);

		add_filter('plugin_action_links', array(__CLASS__, 'plugins_page_actions'), 10, 4);
	}

	// add the settings page link to the plugins page
	public static function plugins_page_actions($actions, $plugin_file, $plugin_data, $context) {
		if ($plugin_file == self::$me && isset($actions['deactivate'])) {
			$new = array(
				'settings' => sprintf(
					'<a href="%s" title="Visit the License Key settings page">%s</a>',
					esc_attr(apply_filters('qsot-get-menu-page-uri', '', 'settings', true)),
					'Settings'
				),
			);
			$actions = array_merge($new, $actions);
		}

		return $actions;
	}
	
	// defer loading non-core modules and plugins, til after all plugins have loaded, since most of the plugins will not know
	public static function load_plugins_and_modules() {
		do_action('qsot-before-loading-modules-and-plugins');

		// load core post types. required for most stuff
		do_action('qsot-load-includes', '', '#^.*post-type\.class\.php$#i');
		// load everything else
		do_action('qsot-load-includes');

		do_action('qsot-after-loading-modules-and-plugins');
	}

	public static function register_base_admin_assets() {
		wp_register_style('qsot-base-admin', self::$o->core_url.'assets/css/admin/base.css', array(), self::$o->version);
	}

	public static function load_base_admin_assets() {
		wp_enqueue_style('qsot-base-admin');
	}

	// when on the edit single event page in the admin, we need to queue up certain aseets (previously registered) so that the page actually works properly
	public static function load_assets() {
		// is this a new event or an existing one? we can check this by determining the post_id, if there is one (since WP does not tell us)
		$post_id = 0;
		$post_type = 'post';
		// if there is a post_id in the admin url, and the post it represents is of our event post type, then this is an existing post we are just editing
		if (isset($_REQUEST['post'])) {
			$post_id = $_REQUEST['post'];
			$existing = true;
			$post_type = get_post_type($_REQUEST['post']);
		// if there is not a post_id but this is the edit page of our event post type, then we still need to load the assets
		} else if (isset($_REQUEST['post_type'])) {
			$existing = false;
			$post_type = $_REQUEST['post_type'];
		// if this is not an edit page of our post type, then we need none of these assets loaded
		} else return;

		// allow sub/external plugins to load their own stuff right now
		do_action('qsot-admin-load-assets-'.$post_type, $existing, $post_id);
	}

	public static function prepend_overtake_autoloader() {
		spl_autoload_register(array(__CLASS__, 'special_autoloader'), true, true);
	}

	public static function why_do_i_have_to_do_this() {
		/// retarded loading work around for the emails core template ONLY in ajax mode, for sending core emails from ajax mode...... wtf
		if (defined('DOING_AJAX') && DOING_AJAX && isset($_POST['action']) && $_POST['action'] == 'woocommerce_remove_order_item' && class_exists('WC_Emails')) new WC_Emails();
	}

	public static function load_custom_emails($list) {
		do_action('qsot-load-includes', '', '#^.+\.email\.php$#i');
		return $list;
	}

	public static function special_autoloader($class) {
		$class = strtolower($class);

		if (strpos($class, 'wc_gateway_') === 0) {
			$paths = array(self::$o->core_dir.'/woocommerce/includes/gateways/'.trailingslashit(substr(str_replace('_', '-', $class), 11)));
			$paths = apply_filters('qsot-woocommerce-gateway-paths', $paths, $paths, $class);
			$file = 'class-'.str_replace('_', '-', $class).'.php';

			foreach ($paths as $path) {
				if (is_readable($path.$file)) {
					include_once($path.$file);
					return;
				}
			}
		} elseif (strpos($class, 'wc_shipping_') === 0) {
			$paths = array(self::$o->core_dir.'/woocommerce/includes/shipping/'.trailingslashit(substr(str_replace('_', '-', $class), 12)));
			$paths = apply_filters('qsot-woocommerce-shipping-paths', $paths, $paths, $class);
			$file = 'class-'.str_replace('_', '-', $class).'.php';

			foreach ($paths as $path) {
				if (is_readable($path.$file)) {
					include_once($path.$file);
					return;
				}
			}
		} elseif (strpos($class, 'wc_shortcode_') === 0) {
			$paths = array(self::$o->core_dir.'/woocommerce/includes/shortcodes/');
			$paths = apply_filters('qsot-woocommerce-shortcode-paths', $paths, $paths, $class);
			$file = 'class-'.str_replace('_', '-', $class).'.php';

			foreach ($paths as $path) {
				if (is_readable($path.$file)) {
					include_once($path.$file);
					return;
				}
			}
		} elseif (strpos($class, 'wc_meta_box_') === 0) {
			$paths = array(self::$o->core_dir.'/woocommerce/includes/admin/post-types/meta-boxes/');
			$paths = apply_filters('qsot-woocommerce-meta-box-paths', $paths, $paths, $class);
			$file = 'class-'.str_replace('_', '-', $class).'.php';

			foreach ($paths as $path) {
				if (is_readable($path.$file)) {
					include_once($path.$file);
					return;
				}
			}
		}

		if (strpos($class, 'wc_') === 0) {
			$paths = array(self::$o->core_dir.'/woocommerce/includes/');
			$paths = apply_filters('qsot-woocommerce-class-paths', $paths, $paths, $class);
			$file = 'class-'.str_replace('_', '-', $class).'.php';

			foreach ($paths as $path) {
				if (is_readable($path.$file)) {
					include_once($path.$file);
					return;
				}
			}
		}
	}

	public static function overtake_some_woocommerce_core_templates($template, $template_name, $template_path='') {
		global $woocommerce;

		$default_path = $woocommerce->plugin_path().'/templates/';
		$default = $default_path.$template_name;

		if (empty($template) || $template == $default) {
			$orpath = self::$o->core_dir.'templates/woocommerce/';
			if (file_exists($orpath.$template_name)) $template = $orpath.$template_name;
		}

		return $template;
	}

	public static function only_search_parent_events($query, $group, $search_term, $page) {
		if ($query['post_type'] == self::$o->core_post_type) {
			$query['post_parent'] = 0;
		}
		return $query;
	}

	public static function locale() {
		$locale = apply_filters('plugin_locale', get_locale(), 'woocommerce');
		setlocale(LC_MONETARY, $locale);
	}

	public static function ajax_url() {
		?><script language="javascript" type="text/javascript" id="sc-ajax-url">var _qsot_ajax_url = '<?php echo site_url('/wp-load.php') ?>';</script><?php
	}

	public static function handle_ajax() {
		if (isset($_POST['action'])) {
			$action = 'qsot-ajax-'.$_POST['action'];
			$sa = isset($_POST['sa']) ? '-'.$_POST['sa'] : '';
			if (has_action($action.$sa)) {
				do_action($action.$sa);
				exit;
			} elseif (has_action($action)) {
				do_action($action);
				exit;
			}
		}
	}

	// load all *.class.php files in the inc/ dir, and any other includes dirs that are specified by external plugins (which may or may not be useful, since external plugins
	// should do their own loading of their own files, and not defer that to us), filtered by subdir $group. so if we want to load all *.class.php files in the inc/core/ dir
	// then $group should equal 'core'. equally, if we want to load all *.class.php files in the inc/core/super-special/top-secret/ dir then the $group variable should be
	// set to equal 'core/super-special/top-secret'. NOTE: leaving $group blank, DOES load all *.class.php files in the includes dirs.
	public static function load_includes($group='', $regex='#^.+\.class\.php$#i') {
		// aggregate a list of includes dirs that will contain files that we need to load
		$dirs = apply_filters('qsot-load-includes-dirs', array(trailingslashit(self::$o->core_dir).'inc/'));
		// cycle through the top-level include folder list
		foreach ($dirs as $dir) {
			// does the subdir $group exist below this context?
			if (file_exists($dir) && ($sdir = trailingslashit($dir).$group) && file_exists($sdir)) {
				// if the subdir exists, then recursively generate a list of all *.class.php files below the given subdir
				$iter = new RegexIterator(
					new RecursiveIteratorIterator(
						new RecursiveDirectoryIterator(
							$sdir
						),
						RecursiveIteratorIterator::SELF_FIRST
					),
					$regex,
					RecursiveRegexIterator::GET_MATCH
				);

				// require every file found
				foreach ($iter as $fullpath => $arr) {
					require_once $fullpath;
				}
			}
		}
	}

	// do magic - as yet to be determined the need of
	public static function activation() {
		do_action('qsot-activate');
	}
}

// loads a core woo class equivalent of a class this plugin takes over, under a different name, so that it can be extended by this plugin's versions and still use the same original name
if (!function_exists('qsot_underload_core_class')) {
	function qsot_underload_core_class($path, $class_name='') {
		global $woocommerce;

		// eval load WooCommerce Core WC_Coupon class, so that we can change the name, so that we can extend it
		$content = file_get_contents($woocommerce->plugin_path.$path);
		if ($class_name) {
			$content = preg_replace('#class\s+('.preg_quote($class_name, '#').')(\s|\{)#si', 'class _WooCommerce_Core_\1\2', $content);
		} else {
			preg_match_all('#class\s+([a-z_][a-z0-9_]*)(\s|\{)#si', $content, $matches, PREG_SET_ORDER);
			if (is_array($matches) && count($matches)) foreach ($matches as $match)
				$content = preg_replace('#class\s+('.preg_quote($match[1], '#').')(\s|\{)#si', 'class _WooCommerce_Core_\1\2', $content);
		}
		$content = preg_replace('#^<\?php.*?/\*#s', '/*', $content);
		eval($content);
	}
}

if (!function_exists('is_ajax')) {
	function is_ajax() {
		if (defined('DOING_AJAX') && DOING_AJAX) return true;
		return (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest');
	}
}

if (defined('ABSPATH') && function_exists('add_action')) {
	QSOT::pre_init();
}

endif;
