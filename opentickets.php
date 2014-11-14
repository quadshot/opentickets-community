<?php (__FILE__ == $_SERVER['SCRIPT_FILENAME']) ? die(header('Location: /')) : null;

if (!class_exists('QSOT')):

class QSOT {
	protected static $o = null; // holder for all options of the events plugin
	protected static $ajax = false;
	protected static $memory_error = '';
	protected static $wc_latest = '2.2.0';
	protected static $wc_back_one = '2.1.0';

	protected static $me = '';
	protected static $version = '';
	protected static $plugin_url = '';
	protected static $plugin_dir = '';
	protected static $product_url = '';

	public static function pre_init() {
		// load the settings. theya re required for everything past this point
		$settings_class_name = apply_filters('qsot-settings-class-name', '');
		if (empty($settings_class_name)) return;
		self::$o = call_user_func_array(array($settings_class_name, "instance"), array());

		self::$me = plugin_basename(self::$o->core_file);
		self::$version = self::$o->version;
		self::$plugin_dir = self::$o->core_dir;
		self::$plugin_url = self::$o->core_url;
		self::$product_url = self::$o->product_url;

		if (!self::_memory_check()) return;

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

		add_action('woocommerce_email_classes', array(__CLASS__, 'load_custom_emails'), 2);

		add_filter('woocommerce_locate_template', array(__CLASS__, 'overtake_some_woocommerce_core_templates'), 10, 3);
		add_action('admin_init', array(__CLASS__, 'register_base_admin_assets'), 10);
		add_action('admin_enqueue_scripts', array(__CLASS__, 'load_base_admin_assets'), 10);

		add_action('load-post.php', array(__CLASS__, 'load_assets'), 999);
		add_action('load-post-new.php', array(__CLASS__, 'load_assets'), 999);

		// register js and css assets at the appropriate time
		add_action('init', array(__CLASS__, 'register_assets'), 2);

		add_filter('plugin_action_links', array(__CLASS__, 'plugins_page_actions'), 10, 4);
		
		load_plugin_textdomain( 'qsot', false, dirname( plugin_basename( __FILE__ ) ) . '/langs/' );
	}

	public static function me() { return self::$me; }
	public static function version() { return self::$version; }
	public static function plugin_dir() { return self::$plugin_dir; }
	public static function plugin_url() { return self::$plugin_url; }
	public static function product_url() { return self::$product_url; }

	public static function is_wc_latest() {
		static $answer = null;
		return $answer !== null ? $answer : ($answer = version_compare(self::$wc_latest, WC()->version) <= 0);
	}

	public static function is_wc_back_one() {
		static $answer = null;
		return $answer !== null ? $answer : ($answer = version_compare(self::$wc_back_one, WC()->version) <= 0);
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

	// always register our scripts and styles before using them. it is good practice for future proofing, but more importantly, it allows other plugins to use our js if needed.
	// for instance, if an external plugin wants to load something after our js, like a takeover js, they will have access to see our js before we actually use it, and will 
	// actually be able to use it as a dependency to their js. if the js is not yet declared, you cannot use it as a dependency.
	public static function register_assets() {
		$suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';

		// XDate 0.7. used for date calculations when using the FullCalendar plugin. http://arshaw.com/xdate/
		wp_register_script('xdate', self::$o->core_url.'assets/js/utils/third-party/xdate/xdate.dev.js', array('jquery'), '0.7');
		// FullCalendar 1.5.4 jQuery plugin. used for all calendar related interfaces. http://arshaw.com/fullcalendar/
		wp_register_script('fullcalendar', self::$o->core_url.'assets/js/libs/fullcalendar/fullcalendar'.$suffix.'.js', array('jquery','xdate'), '1.5.4');
		wp_register_style('fullcalendar', self::$o->core_url.'assets/css/libs/fullcalendar/fullcalendar.css', array(), '1.5.4');
		// json2 library to add JSON window object in case it does not exist
		wp_register_script('json2', self::$o->core_url.'assets/js/utils/json2.js', array(), 'commit-17');
		// colorpicker
		wp_register_script('jqcolorpicker', self::$o->core_url.'assets/js/libs/cp/colorpicker.js', array('jquery'), '23.05.2009');
		wp_register_style('jqcolorpicker', self::$o->core_url.'assets/css/libs/cp/colorpicker.css', array(), '23.05.2009');
		// generic set of tools for our js work. almost all written by Loushou
		wp_register_script('qsot-tools', self::$o->core_url.'assets/js/utils/tools.js', array('jquery', 'json2', 'xdate'), '0.2-beta');
		// jQueryUI theme for the admin
		wp_register_style('qsot-jquery-ui', self::$o->core_url.'assets/css/libs/jquery/jquery-ui-1.10.1.custom.min.css', array(), '1.10.1');
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
			if (self::is_wc_latest())
				$paths = array(self::$o->core_dir.'/woocommerce/includes/admin/meta-boxes/');
			else
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
		if ( isset( $query['post_type'] ) ) {
			if ( ! isset( $query['post_type'] ) && (
				( is_array( $query['post_type'] ) && in_array( self::$o->core_post_type, $query['post_type'] ) ) ||
				( is_scalar( $query['post_type'] ) && $query['post_type'] == self::$o->core_post_type )
			) ) {
				$query['post_parent'] = 0;
			}
		}
		return $query;
	}

	public static function locale() {
		$locale = apply_filters('plugin_locale', get_locale(), 'woocommerce');
		setlocale(LC_MONETARY, $locale);
	}

	// load all *.class.php files in the inc/ dir, and any other includes dirs that are specified by external plugins (which may or may not be useful, since external plugins
	// should do their own loading of their own files, and not defer that to us), filtered by subdir $group. so if we want to load all *.class.php files in the inc/core/ dir
	// then $group should equal 'core'. equally, if we want to load all *.class.php files in the inc/core/super-special/top-secret/ dir then the $group variable should be
	// set to equal 'core/super-special/top-secret'. NOTE: leaving $group blank, DOES load all *.class.php files in the includes dirs.
	public static function load_includes($group='', $regex='#^.+\.class\.php$#i') {
		//$includer = new QSOT_includer();
		// aggregate a list of includes dirs that will contain files that we need to load
		$dirs = apply_filters('qsot-load-includes-dirs', array(trailingslashit(self::$o->core_dir).'inc/'));
		// cycle through the top-level include folder list
		foreach ($dirs as $dir) {
			// does the subdir $group exist below this context?
			if (file_exists($dir) && ($sdir = trailingslashit($dir).$group) && file_exists($sdir)) {
				//$includer->inc_match($sdir, $regex);
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
		unset($dirs, $iter);
	}

	public static function memory_limit_problem() {
		if (empty(self::$memory_error)) return;

		$msg = str_replace(
			array(
				'%%PRODUCT%%',
			),
			array(
				sprintf('<em><a href="%s" target="_blank">%s</a></em>', esc_attr(self::$o->product_url), force_balance_tags(self::$o->product_name)),
			),
			self::$memory_error
		);

		?>
			<div class="error errors">
				<p class="error">
					<u><strong>Memory Requirement Problem</strong></u><br/>
					<?php echo $msg ?>
				</p>
			</div>
		<?php
	}

	// minimum mmeory required is 48MB
	// attempt to obtain a minimum of 64MB
	// if cannot obtain at least 48MB, then fail with a notice and prevent loading of OpenTickets
	protected static function _memory_check($min=50331648, $recommend=67108864) {
		$allow = true;
		$current_limit = self::memory_limit();

		if ($current_limit < $min && function_exists('ini_set')) {
			ini_set('memory_limit', $recommend);
			$current_limit = self::memory_limit(true);
			if ($current_limit < $min) {
				ini_set('memory_limit', $min);
				$current_limit = self::memory_limit(true);
			}
		}

		if ($current_limit < $min) {
			$allow = false;
			$hmin = '<em><strong>'.round($min / 1048576, 2).'MB</strong></em>';
			$hrec = '<em><strong>'.round($recommend / 1048576, 2).'MB</strong></em>';
			$hcur = '<em><strong>'.round($current_limit / 1048576, 2).'MB</strong></em>';
			self::$memory_error = 'The %%PRODUCT%% plugin <strong>requires</strong> that your server allow at least '.$hmin.' of memory to WordPress. '
				.'We recommend at least '.$hrec.' for optimum performance (as does WooCommerce). '
				.'We tried to raise the memory limit to the minimum for your automatically, but your server settings do not allow it. '
				.'Your server currently only allows '.$hcur.', which is below the minimum. '
				.'We have stopped loading OpenTickets, in an effort maintain access to your site. '
				.'Once you have raised your server settings to at least the minimum memory requirement, we will turn OpenTickets back on automatically.';
		} else if ($current_limit < $recommend) {
			$hmin = '<em><strong>'.round($min / 1048576, 2).'MB</strong></em>';
			$hrec = '<em><strong>'.round($recommend / 1048576, 2).'MB</strong></em>';
			$hcur = '<em><strong>'.round($current_limit / 1048576, 2).'MB</strong></em>';
			self::$memory_error = 'The %%PRODUCT%% plugin <strong>requires</strong> that your server allow at least '.$hmin.' of memory to WordPress. '
				.'Currently, yoru server is set to allow '.$hcur.', which is above the minimum. '
				.'We recommend at least '.$hrec.' for optimum performance (as does WooCommerce). '
				.'If you cannot raise the limit to the recommended amount, or do not wish to, then simply ignore this message.';
		}

		if (!empty(self::$memory_error)) add_action('admin_notices', array(__CLASS__, 'memory_limit_problem'), 100);

		return $allow;
	}

	public static function memory_limit($force=false) {
		static $max = false;

		if ($force || $max === false) {
			$max = self::xb2b( ini_get('memory_limit'), true );
		}

		return $max;
	}

	public static function xb2b( $raw, $fakeit = false ) {
		$out = '';
		$raw = strtolower( $raw );
		preg_match_all( '#^(\d+)(\w*)?$#', $raw, $matches, PREG_SET_ORDER );
		if ( isset( $matches[0] ) ) {
			$out = $matches[0][1];
			$unit = $matches[0][2];
			switch ( $unit ) {
				case 'k': $out *= 1024; break;
				case 'm': $out *= 1048576; break;
				case 'g': $out *= 1073741824; break;
			}
		} else {
			$out = $fakeit ? 32 * 1048576 : $raw;
		}

		return $out;
	}

	public static function compile_frontend_styles() {
		$options = qsot_options::instance();
		$colors = $options->{'qsot-event-frontend-colors'};

		$base_file = QSOT::plugin_dir() . 'assets/css/frontend/event-base.less';
		$less_file = QSOT::plugin_dir() . 'assets/css/frontend/event.less';
		$css_file = QSOT::plugin_dir() . 'assets/css/frontend/event.css';

		// Write less file
		if ( is_writable( $base_file ) && is_writable( dirname( $css_file ) ) ) {
			// Colours changed - recompile less
			if ( ! class_exists( 'lessc' ) )
				include_once( WC()->plugin_path() . '/includes/libraries/class-lessc.php' );
			if ( ! class_exists( 'cssmin' ) )
				include_once( WC()->plugin_path() . '/includes/libraries/class-cssmin.php' );

			try {
				// create the base file
				$css = array();
				foreach ( $colors as $tag => $color )
					$css[] = '@' . $tag . ':' . $color . ';';
				file_put_contents( $base_file, implode( "\n", $css ) );

				// create the core css file
				$less = new lessc;
				$compiled_css = $less->compileFile( $less_file );
				$compiled_css = CssMin::minify( $compiled_css );

				if ( $compiled_css )
					file_put_contents( $css_file, $compiled_css );
			} catch ( Exception $ex ) {
				wp_die( __( 'Could not compile event.less:', 'qsot' ) . ' ' . $ex->getMessage() );
			}
		}
	}

	// do magic - as yet to be determined the need of
	public static function activation() {
		self::load_plugins_and_modules();
		do_action('qsot-activate');
		flush_rewrite_rules();
		self::compile_frontend_styles();
	}
}

// loads a core woo class equivalent of a class this plugin takes over, under a different name, so that it can be extended by this plugin's versions and still use the same original name
if (!function_exists('qsot_underload_core_class')) {
	function qsot_underload_core_class($path, $class_name='') {
		global $woocommerce;

		// eval load WooCommerce Core WC_Coupon class, so that we can change the name, so that we can extend it
		/*
		$content = file_get_contents($woocommerce->plugin_path.$path);
		if ($class_name) {
			$content = preg_replace('#class\s+('.preg_quote($class_name, '#').')(\s|\{)#si', 'class _WooCommerce_Core_\1\2', $content);
		} else {
			preg_match_all('#class\s+([a-z_][a-z0-9_]*)(\s|\{)#si', $content, $matches, PREG_SET_ORDER);
			if (is_array($matches) && count($matches)) foreach ($matches as $match)
				$content = preg_replace('#class\s+('.preg_quote($match[1], '#').')(\s|\{)#si', 'class _WooCommerce_Core_\1\2', $content);
		}
		$content = preg_replace('#^<\?php.*?/\*#s', '/*', $content);
		*/
		$f = fopen($woocommerce->plugin_path.$path, 'r');
		stream_filter_append($f, 'qsot_underload');
		eval(stream_get_contents($f));
		fclose($f);
		unset($content);
	}

	class QSOT_underload_filter extends php_user_filter {
		public static $find = '';

		public function filter($in, $out, &$consumed, $closing) {
			while ($bucket = stream_bucket_make_writeable($in)) {
				$read = $bucket->datalen;
				if (strpos($bucket->data, 'class') !== false) {
					if (empty(self::$find)) $bucket->data = preg_replace('#class\s+([a-z])#si', 'class _WooCommerce_Core_\1', $bucket->data);
					else $bucket->data = preg_replace('#class\s+('.preg_quote(self::$find, '#').')(\s|\{)#si', 'class _WooCommerce_Core_\1\2', $bucket->data);
				}
				if ($consumed == 0) {
					$bucket->data = preg_replace('#^<\?(php)?\s+#s', '', $bucket->data);
				}
				$consumed += $read; //$bucket->datalen;
				stream_bucket_append($out, $bucket);
			}
			return PSFS_PASS_ON;
		}
	}
	if (function_exists('stream_filter_register'))
		stream_filter_register('qsot_underload', 'QSOT_underload_filter');
}

// load one of our classes, and update the 'extends' declaration with the appropriate class name if supplied
if ( ! function_exists( 'qsot_overload_core_class' ) ) {
	function qsot_overload_core_class( $path, $new_under_class_name='' ) {
		global $woocommerce;

		QSOT_overload_filter::$replace = $new_under_class_name;

		$filepath = $path;
		if ( ! file_exists( $filepath ) ) {
			if ( file_exists( dirname( QSOT::plugin_dir() ) . DIRECTORY_SEPARATOR . $path ) )
				$filepath = dirname( QSOT::plugin_dir() ) . DIRECTORY_SEPARATOR . $path;
			else if ( file_exists( QSOT::plugin_dir() . DIRECTORY_SEPARATOR . $path ) )
				$filepath = QSOT::plugin_dir() . DIRECTORY_SEPARATOR . $path;
		}

		if ( file_exists( $filepath ) ) {
			$f = fopen( $filepath, 'r' );
			stream_filter_append( $f, 'qsot_overload' );
			eval( stream_get_contents( $f ) );
			fclose( $f );
			unset( $content );
		} else throw new Exception( 'Could not find overload file [ ' . $path . ' ].' );
	}

	class QSOT_overload_filter extends php_user_filter {
		public static $replace = '';

		public function filter( $in, $out, &$consumed, $closing ) {
			while ( $bucket = stream_bucket_make_writeable( $in ) ) {
				$read = $bucket->datalen;
				if ( !empty( self::$replace ) && strpos( $bucket->data, 'extends' ) !== false ) {
					$bucket->data = preg_replace( '#extends\s+([_a-z][_a-z0-9]*)(\s|\{)#si', 'extends '.self::$replace.'\2', $bucket->data );
				}
				if ( $consumed == 0 ) {
					$bucket->data = preg_replace( '#^<\?(php)?\s+#s', '', $bucket->data );
				}
				$consumed += $read; //$bucket->datalen;
				stream_bucket_append( $out, $bucket );
			}
			return PSFS_PASS_ON;
		}
	}

	if ( function_exists( 'stream_filter_register' ) )
		stream_filter_register( 'qsot_overload', 'QSOT_overload_filter' );
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
