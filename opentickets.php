<?php if ( __FILE__ == $_SERVER['SCRIPT_FILENAME'] ) die( header( 'Location: /') );

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

		//add_action( 'plugins_loaded', array( __CLASS__, 'admin_deactivate' ), 0 );

		if (!self::_memory_check()) return;

		// load the text domain after all plugins have loaded
		add_action( 'plugins_loaded', array( __CLASS__, 'load_textdomain' ), 4 );

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

		// load all the abstract classes
		do_action( 'qsot-load-includes', '', '#^.+\.abstract-dep\.php$#i' );

		// load the core area_types
		do_action( 'qsot-load-includes', '', '#^.+area-type\.class.php$#i' );

		// load all plugins and modules later on
		add_action('plugins_loaded', array(__CLASS__, 'load_plugins_and_modules'), 5);

		// register the activation function, so that when the plugin is activated, it does some magic described in the activation function
		register_activation_hook(self::$o->core_file, array(__CLASS__, 'activation'));
		add_action( 'upgrader_process_complete', array( __CLASS__, 'maybe_activation_on_upgrade' ), 10, 2 );

		add_action('woocommerce_email_classes', array(__CLASS__, 'load_custom_emails'), 2);

		add_filter('woocommerce_locate_template', array(__CLASS__, 'overtake_some_woocommerce_core_templates'), 10, 3);
		add_action('admin_init', array(__CLASS__, 'register_base_admin_assets'), 10);
		add_action('admin_enqueue_scripts', array(__CLASS__, 'load_base_admin_assets'), 10);

		add_action('load-post.php', array(__CLASS__, 'load_assets'), 999);
		add_action('load-post-new.php', array(__CLASS__, 'load_assets'), 999);

		// register js and css assets at the appropriate time
		add_action('init', array(__CLASS__, 'register_assets'), -1000);

		add_filter('plugin_action_links', array(__CLASS__, 'plugins_page_actions'), 10, 4);

		// polyfill the hide/show js functions in the head tag, since some themes apparently don't have this
		add_action( 'wp_head', array( __CLASS__, 'polyfill_hideshow_js' ), 0 );

		// add an admin footer promo text bit. sorry guys we need this
		add_action( 'admin_footer_text', array( __CLASS__, 'admin_footer_text' ), PHP_INT_MAX, 1 );

		// if in the admin, make sure to record the first date this user has been using this software
		if ( is_admin() )
			self::_check_used_since();
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

	public static function is_wc_at_least( $version ) {
		static $answers = array();
		return ( isset( $answers[ $version ] ) ) ? $answers[ $version ] : ( $answers[ $version ] = version_compare( $version, WC()->version ) <= 0 );
	}

	public static function admin_deactivate() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) return;
		if ( ! isset( $_COOKIE['ot-deactivate'] ) || 'opentickets' != $_COOKIE['ot-deactivate'] ) return;
		
		$me = plugin_basename( self::$o->core_file );
		$active = get_option( 'active_plugins' );
		$out = array_diff( $active, array( $me ) );
		update_option( 'active_plugins', $out );
		setcookie( 'ot-deactivate', '', 1, '/' );

		wp_safe_redirect( add_query_arg( array( 'updated' => 1 ) ) );
		exit;
	}

	// add the settings page link to the plugins page
	public static function plugins_page_actions($actions, $plugin_file, $plugin_data, $context) {
		if ($plugin_file == self::$me && isset($actions['deactivate'])) {
			$new = array(
				'settings' => sprintf(
					__('<a href="%s" title="Visit the License Key settings page">Settings</a>','opentickets-community-edition'),
					esc_attr(apply_filters('qsot-get-menu-page-uri', '', 'settings', true))
				),
			);
			$actions = array_merge($new, $actions);
		}

		return $actions;
	}
	
	// defer loading non-core modules and plugins, til after all plugins have loaded, since most of the plugins will not know
	public static function load_plugins_and_modules() {
		// load all other core features
		do_action('qsot-load-includes', 'core');
		// injection point by sub/external plugins to load their stuff, or stuff that is required to be loaded first, or whatever
		// NOTE: this would require that the code that makes use of this hook, loads before this plugin is loaded at all
		do_action('qsot-after-core-includes');

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

	// apparently some themes do not implement basic js hide/show features for showing and hideing content with css
	public static function polyfill_hideshow_js() {
		?><script language="javascript">document.write( '<style>.js .hide-if-js { display:none; } .js .show-if-js { display:block; }</style>' )</script><?php
	}

	// always register our scripts and styles before using them. it is good practice for future proofing, but more importantly, it allows other plugins to use our js if needed.
	// for instance, if an external plugin wants to load something after our js, like a takeover js, they will have access to see our js before we actually use it, and will 
	// actually be able to use it as a dependency to their js. if the js is not yet declared, you cannot use it as a dependency.
	public static function register_assets() {
		$suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';

		// XDate 0.7. used for date calculations when using the FullCalendar plugin. http://arshaw.com/xdate/
		wp_register_script('xdate', self::$o->core_url.'assets/js/utils/third-party/xdate/xdate.dev.js', array('jquery'), '0.7');
		// json2 library to add JSON window object in case it does not exist
		wp_register_script('json2', self::$o->core_url.'assets/js/utils/json2.js', array(), 'commit-17');
		// colorpicker
		wp_register_script('jqcolorpicker', self::$o->core_url.'assets/js/libs/cp/colorpicker.js', array('jquery'), '23.05.2009');
		wp_register_style('jqcolorpicker', self::$o->core_url.'assets/css/libs/cp/colorpicker.css', array(), '23.05.2009');
		// jQueryUI theme for the admin
		wp_register_style('qsot-jquery-ui', self::$o->core_url.'assets/css/libs/jquery/jquery-ui-1.10.1.custom.min.css', array(), '1.10.1');

		// generic set of tools for our js work. almost all written by Loushou
		wp_register_script( 'qsot-core-tools', self::$o->core_url . 'assets/js/utils/tools.js', array( 'jquery', 'json2', 'xdate', 'jquery-ui-datepicker' ), '0.2.0-beta' );
		// backbone modal, ripped from core WC, and modified to work for our causes
		wp_register_script( 'qsot-backbone-modal', self::$o->core_url . 'assets/js/utils/backbone-modal.js', array( 'underscore', 'backbone', 'qsot-core-tools' ), '0.1.0-beta', 1 );

		// select2 lib, since WC cannot seem to decide on a select overtake lib
		wp_register_script( 'select2', self::$o->core_url . 'assets/js/libs/select2/select2.js', array( 'jquery' ), '3.5.4' );
		wp_register_style( 'select2', self::$o->core_url . 'assets/js/libs/select2/select2.css', array(), '3.5.4' );

		// tablesorter plugin
		wp_register_script( 'tablesorter', self::$o->core_url . 'assets/js/libs/jquery-tablesorter/jquery.tablesorter' . $suffix . '.js', array( 'jquery' ), '2.0.3' );

		// admin specific tools
		wp_register_script( 'qsot-admin-tools', self::$o->core_url . 'assets/js/utils/admin-tools.js', array( 'qsot-backbone-modal', 'select2' ), self::$o->version );

		// create the generic qsot-tools bucket
		$requirements = array( 'qsot-core-tools' );
		if ( is_admin() ) $requirements[] = 'qsot-admin-tools';
		wp_register_script( 'qsot-tools', false, $requirements, self::$o->version );
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

	// current_user is the id we use to lookup tickets in relation to a product in a cart. once we have an order number this pretty much becomes obsolete, but is needed up til that moment
	public static function current_user( $data='' ) {
		$res = '';

		// get the core woocommerce object, because we will use it as the creator of this id, if available
		$woocommerce = WC();

		// normalize our extra data
		$data = wp_parse_args( $data, array( 'customer_user' => '', 'order_id' => '' ) );

		// if the customer_user is set in our data, then use it
		if ( $data['customer_user'] )
			return $data['customer_user'];

		// if we have the order_id, then use it to lookup the customer_user
		if ( (int)$data['order_id'] > 0 )
			$res = get_post_meta( $data['order_id'], '_customer_user', true );

		// if the woocommerce session is in use, then pull the id from that session
		if ( empty( $res ) && isset( $woocommerce->session ) && is_object( $woocommerce->session ) )
			$res = $woocommerce->session->get_customer_id();

		// if we still dont have an id, then make some shit up
		if ( empty( $res ) )
			$res = md5( ( isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : time() ) . ( isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : rand( 0, PHP_INT_MAX ) ) );

		return $res;
	}

	// get the max packet size from mysql, if we can
	public static function max_packet() {
		$found = null;
		// attempt to fetch the value from cache, if we already grabbed it from the db
		$cache = wp_cache_get( 'max-packe', 'db-value', false, $found );

		// if the value was not cached yet, then do so now
		if ( false === $found || ( null === $found && false === $cache ) ) {
			global $wpdb;

			// find the var in the mysql settings
			$res = $wpdb->get_row( 'show variables like "max_allowed_packet"' );

			// determine the value
			$cache = is_object( $res ) && isset( $res->Value ) ? $res->Value : 1048576;

			// save it in the cache
			wp_cache_set( 'max-packet', $cache, 'db-value', 3600 );
		}

		return $cache;
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
		$default_path = WC()->plugin_path().'/templates/';
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

	// get a list of OTCE core pages
	public static function core_screen_ids() {
		return array(
			'edit-qsot-event', // admin event list page
			'qsot-event', // individual admin edit event page
			'edit-qsot-venue', // admin venue list page
			'qsot-venue', // individual admin edit venue page
			'edit-qsot-event-area', // admin even area list page 'seating extension'
			'qsot-event-area', // individual admin edit event-area page
			'toplevel_page_opentickets', // opentickets toplevel page (reports currently)
			'opentickets_page_opentickets-settings', // opentickets settings page
			'opentickets_page_qsot-system-status', // opentickets system status page
			'opentickets_page_qsot-extensions', // opentickets extensions page
			'updates-core', // the WP updater page. yeah shameless i know
			'plugins', // the WP plugins page. yeah shameless i know
		);
	}

	// add a promo to the admin footer, which asks for a rating on wp.org
	public static function admin_footer_text( $text ) {
		// figure out the current page we are on
		$current_screen = get_current_screen();

		$action = 'do-nothing';
		// determine if we should be: doing nothing to, adding to, or replacing the footer text based on the page
		if ( isset( $current_screen->id ) ) {
			// copmletely replace the footer on OpenTickets pages
			if ( in_array( $current_screen->id, self::core_screen_ids() ) )
				$action = 'replace';
			// add to the footer if on a WooCommerce page
			else if ( in_array( $current_screen->id, wc_get_screen_ids() ) )
				$action = 'add-to';
		}

		// now, based on the action we calculated, do something with the footer text
		switch ( $action ) {
			default:
			case 'do-nothing': break;

			case 'replace':
				$text = sprintf(
					__( 'If you like %1$sOpenTickets Community Edition%2$s please leave us a %3$s&#9733;&#9733;&#9733;&#9733;&#9733;%2$s rating. A huge thank you from %4$sQuadshot%2$s in advance!', 'opentickets-community-edition' ),
					'<a href="http://opentickets.com/community-edition/" target="_blank" class="otce-brand-link">',
					'</a>',
					'<a href="https://wordpress.org/support/view/plugin-reviews/opentickets-community-edition?filter=5#postform" target="_blank" class="otce-rating-link" data-rated="'
							. esc_attr__( 'Thanks :)', 'opentickets-community-edition' ) . '">',
					'<a href="http://quadshot.com" target="_blank" class="otce-author-link">'
				);
			break;

			case 'add-to':
				$text .= '<br/> ' . sprintf(
					__( 'Also, if you like %1$sOpenTickets Community Edition%2$s, please leave them a %3$s&#9733;&#9733;&#9733;&#9733;&#9733;%2$s rating. %4$sQuadshot%2$s sends you a huge thank you as well!', 'opentickets-community-edition' ),
					'<a href="http://opentickets.com/community-edition/" target="_blank" class="otce-brand-link">',
					'</a>',
					'<a href="https://wordpress.org/support/view/plugin-reviews/opentickets-community-edition?filter=5#postform" target="_blank" class="otce-rating-link" data-rated="'
							. esc_attr__( 'Thanks :)', 'opentickets-community-edition' ) . '">',
					'<a href="http://quadshot.com" target="_blank" class="otce-author-link">'
				);
			break;
		}

		// return the final value
		return $text;
	}

	public static function load_textdomain() {
		$domain = 'opentickets-community-edition';
		$locale = apply_filters( 'plugin_locale', get_locale(), $domain );

		// first load any custom language file defined in the site languages path
		load_textdomain( $domain, WP_LANG_DIR . '/plugins/' . $domain . '/custom-' . $domain . '-' . $locale . '.mo' );

		// load the translation after all plugins have been loaded. fixes the multilingual issues
		load_plugin_textdomain( $domain, false, dirname( plugin_basename( __FILE__ ) ) . '/langs/' );
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
					<u><strong><?php _e('Memory Requirement Problem','opentickets-community-edition') ?></strong></u><br/>
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
			self::$memory_error = sprintf(__('The %%%%PRODUCT%%%% plugin <strong>requires</strong> that your server allow at least %s of memory to WordPress. We recommend at least %s for optimum performance (as does WooCommerce). We tried to raise the memory limit to the minimum for your automatically, but your server settings do not allow it. Your server currently only allows %s, which is below the minimum. We have stopped loading OpenTickets, in an effort maintain access to your site. Once you have raised your server settings to at least the minimum memory requirement, we will turn OpenTickets back on automatically.', 'opentickets-community-edition'),
				$hmin,
				$hrec,
				$hcur
			);
				
		} else if ($current_limit < $recommend) {
			$hmin = '<em><strong>'.round($min / 1048576, 2).'MB</strong></em>';
			$hrec = '<em><strong>'.round($recommend / 1048576, 2).'MB</strong></em>';
			$hcur = '<em><strong>'.round($current_limit / 1048576, 2).'MB</strong></em>';
			self::$memory_error = sprintf(__('The %%%%PRODUCT%%%% plugin <strong>requires</strong> that your server allow at least %s of memory to WordPress. Currently, yoru server is set to allow %s, which is above the minimum. We recommend at least %s for optimum performance (as does WooCommerce). If you cannot raise the limit to the recommended amount, or do not wish to, then simply ignore this message.','opentickets-community-edition'),
				$hmin,
				$hcur,
				$hrec
			);
		}

		if (!empty(self::$memory_error)) add_action('admin_notices', array(__CLASS__, 'memory_limit_problem'), 100);

		return $allow;
	}

	// get the current number of milliseconds during execution. used for 'since' in the reservation table, mostly
	public static function mille() {
		// get the current microtime
		$when = explode( '.', microtime( true ) );
		return (int)end( $when );
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

	// get the color defaults
	public static function default_colors() {
		return array(
			// ticket selection ui
			'form_bg' => '#f4f4f4',
			'form_border' => '#888888',
			'form_action_bg' => '#888888',
			'form_helper' => '#757575',

			'good_msg_bg' => '#eeffee',
			'good_msg_border' => '#008800',
			'good_msg_text' => '#008800',

			'bad_msg_bg' => '#ffeeee',
			'bad_msg_border' => '#880000',
			'bad_msg_text' => '#880000',

			'remove_bg' => '#880000',
			'remove_border' => '#660000',
			'remove_text' => '#ffffff',

			// calendar defaults
			'calendar_item_bg' => '#f0f0f0',
			'calendar_item_border' => '#577483',
			'calendar_item_text' => '#577483',
			'calendar_item_bg_hover' => '#577483',
			'calendar_item_border_hover' => '#577483',
			'calendar_item_text_hover' => '#ffffff',

			'past_calendar_item_bg' => '#ffffff',
			'past_calendar_item_border' => '#bbbbbb',
			'past_calendar_item_text' => '#bbbbbb',
			'past_calendar_item_bg_hover' => '#ffffff',
			'past_calendar_item_border_hover' => '#bbbbbb',
			'past_calendar_item_text_hover' => '#bbbbbb',
		);
	}

	// fetch and compile the current settings for the frontend colors. make sure to apply known defaults
	public static function current_colors() {
		$options = qsot_options::instance();
		$colors = $options->{'qsot-event-frontend-colors'};
		$defaults = self::default_colors();

		return wp_parse_args( $colors, $defaults );
	}

	public static function compile_frontend_styles() {
		$colors = self::current_colors();

		$pdir = QSOT::plugin_dir();
		$base_file = $pdir . 'assets/css/frontend/event-base.less';
		$files = array(
			array( $pdir . 'assets/css/frontend/event.less', $pdir . 'assets/css/frontend/event.css' ),
			array( $pdir . 'assets/css/features/calendar/calendar.less', $pdir . 'assets/css/features/calendar/calendar.css' ),
		);

		// Write less file
		if ( is_writable( $base_file ) ) {
			try {
				// first check if lessc is available
				$file = self::$plugin_dir . 'libs/css/lessc.php';
				if ( self::_check_one_lib( $file, 'LESSC' ) )
					include $file;
				
				// then check if cssmin is available
				$file = self::$plugin_dir . 'libs/css/cssmin.php';
				if ( self::_check_one_lib( $file, 'CSSMIN' ) )
					include $file;
			} catch ( Exception $e ) {
				// upon failure to find a library, just fail, with no message, until we can figure out a good way to transport the message
				return;
			}

			try {
				// create the base file
				$css = array();
				foreach ( $colors as $tag => $color )
					$css[] = '@' . $tag . ':' . $color . ';';
				file_put_contents( $base_file, implode( "\n", $css ) );

				foreach ( $files as $file_group ) {
					list( $less_file, $css_file ) = $file_group;
					if ( is_writable( dirname( $css_file ) ) ) {
						try {
							// create the core css file
							$less = new lessc;
							$compiled_css = $less->compileFile( $less_file );
							$compiled_css = CssMin::minify( $compiled_css );

							if ( $compiled_css )
								file_put_contents( $css_file, $compiled_css );
						} catch ( Exception $ex ) {
							wp_die( sprintf( __( 'Could not compile stylesheet %s. [%s]', 'opentickets-community-edition' ), $less_file, $ex->getMessage() ) );
						}
					} else {
						wp_die( sprintf( __( 'Could not write to stylesheet file %s.', 'opentickets-community-edition' ), $css_file ) );
					}
				}
			} catch ( Exception $ex ) {
				wp_die( sprintf( __( 'Could not write colors to file %s. [%s]', 'opentickets-community-edition' ), $base_file, $ex->getMessage() ) );
			}
		}
	}

	// test if a descreet lib exists
	protected static function _check_one_lib( $file, $lib_name, $context=false ) {
		// set the default context for the error message
		$context = $context ? $context : __( 'the installation process', 'opentickets-community-edition' );

		// if we ar not in debug mode
		if ( ! WP_DEBUG ) {
			if ( ! @file_exists( $file ) || ! is_readable( $file ) || is_dir( $file ) )
				throw new Exception( sprintf( __( 'Could not find the needed library %s, for use in %s.', 'opentickets-community-edition' ), $lib_name, $context ) );
		// if we ARE in debug mode
		} else {
			if ( ! file_exists( $file ) || ! is_readable( $file ) || is_dir( $file ) )
				throw new Exception( sprintf( __( 'Could not find the needed library %s, for use in %s.', 'opentickets-community-edition' ), $lib_name, $context ) );
		}

		return true;
	}

	// make sure we record the earliest known date that this site started using our software
	protected static function _check_used_since() {
		// get the current value
		$current = get_option( '_qsot_used_since', '' );

		// if the current value does not exist, then try to set it to the publish date of the first event
		if ( '' == $current || ! strtotime( $current ) ) {
			global $wpdb;
			$event_date = $wpdb->get_var( $wpdb->prepare( 'select post_date from ' . $wpdb->posts . ' where post_type = %s order by post_date asc, id asc', self::$o->core_post_type ) );
			// if we found the date, then use it
			if ( $event_date )
				update_option( '_qsot_used_since', $current = $event_date );
			// otherwise, use today
			else
				update_option( '_qsot_used_since', current_time( 'mysql' ) );
		}
	}

	// maybe run the activation sequence on plugin update
	public static function maybe_activation_on_upgrade( $upgrader, $extra ) {
		// if the extra indicates that this upgrade is not for a plugin, then bail
		if ( 'plugin' !== $extra['type'] )
			return;

		// if the extra indicates that the upgrade is not for THIS plugin, then bail
		if ( ! isset( $extra['plugin'] ) || $extra['plugin'] !== self::$o->core_file )
			return;

		// run the activation sequence
		self::activation();
	}

	// do magic 
	public static function activation() {
		self::load_plugins_and_modules();

		OpenTickets_Community_Launcher::otce_2_0_0_compatibility_check();

		do_action('qsot-activate');
		flush_rewrite_rules();

		ob_start();
		self::compile_frontend_styles();
		$out = ob_get_contents();
		ob_end_clean();
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG )
			file_put_contents( 'compile.log', $out );
	}
}

// dummy noop function. literally does nothing (meant for actions and filters)
if ( ! function_exists( 'qsot_noop' ) ):
	function qsot_noop( $first ) { return $first; };
endif;

// loads a core woo class equivalent of a class this plugin takes over, under a different name, so that it can be extended by this plugin's versions and still use the same original name
if (!function_exists('qsot_underload_core_class')) {
	function qsot_underload_core_class($path, $class_name='') {
		$woocommerce = WC();
		// eval load WooCommerce Core WC_Coupon class, so that we can change the name, so that we can extend it
		$f = fopen( $woocommerce->plugin_path() . $path, 'r' );
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
	if ( !function_exists('wp_set_auth_cookie') ):
		if ( version_compare($GLOBALS['wp_version'], '4.0') < 0 ) :
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
		else:
			/** 4.0 and higher version - NOTE: I need a better way to do this.
			 * Sets the authentication cookies based on user ID.
			 *
			 * The $remember parameter increases the time that the cookie will be kept. The
			 * default the cookie is kept without remembering is two days. When $remember is
			 * set, the cookies will be kept for 14 days or two weeks.
			 *
			 * @since 2.5.0
			 *
			 * @param int $user_id User ID
			 * @param bool $remember Whether to remember the user
			 * @param mixed $secure  Whether the admin cookies should only be sent over HTTPS.
			 *                       Default is_ssl().
			 */
			function wp_set_auth_cookie($user_id, $remember = false, $secure = '') {
				if ( $remember ) {
					/**
					 * Filter the duration of the authentication cookie expiration period.
					 *
					 * @since 2.8.0
					 *
					 * @param int  $length   Duration of the expiration period in seconds.
					 * @param int  $user_id  User ID.
					 * @param bool $remember Whether to remember the user login. Default false.
					 */
					$expiration = time() + apply_filters( 'auth_cookie_expiration', 14 * DAY_IN_SECONDS, $user_id, $remember );

					/*
					 * Ensure the browser will continue to send the cookie after the expiration time is reached.
					 * Needed for the login grace period in wp_validate_auth_cookie().
					 */
					$expire = $expiration + ( 12 * HOUR_IN_SECONDS );
				} else {
					/** This filter is documented in wp-includes/pluggable.php */
					$expiration = time() + apply_filters( 'auth_cookie_expiration', 2 * DAY_IN_SECONDS, $user_id, $remember );
					$expire = 0;
				}

				$expire = apply_filters( 'auth_cookie_expire_time', $expire, $user_id, $remember, $expiration );

				if ( '' === $secure ) {
					$secure = is_ssl();
				}

				// Frontend cookie is secure when the auth cookie is secure and the site's home URL is forced HTTPS.
				$secure_logged_in_cookie = $secure && 'https' === parse_url( get_option( 'home' ), PHP_URL_SCHEME );

				/**
				 * Filter whether the connection is secure.
				 *
				 * @since 3.1.0
				 *
				 * @param bool $secure  Whether the connection is secure.
				 * @param int  $user_id User ID.
				 */
				$secure = apply_filters( 'secure_auth_cookie', $secure, $user_id );

				/**
				 * Filter whether to use a secure cookie when logged-in.
				 *
				 * @since 3.1.0
				 *
				 * @param bool $secure_logged_in_cookie Whether to use a secure cookie when logged-in.
				 * @param int  $user_id                 User ID.
				 * @param bool $secure                  Whether the connection is secure.
				 */
				$secure_logged_in_cookie = apply_filters( 'secure_logged_in_cookie', $secure_logged_in_cookie, $user_id, $secure );

				if ( $secure ) {
					$auth_cookie_name = SECURE_AUTH_COOKIE;
					$scheme = 'secure_auth';
				} else {
					$auth_cookie_name = AUTH_COOKIE;
					$scheme = 'auth';
				}

				$manager = WP_Session_Tokens::get_instance( $user_id );
				$current_cookie = wp_parse_auth_cookie('', 'logged_in');
				if (!$current_cookie || !isset($current_cookie['token'])) {
					$token = $manager->create( $expiration );
				} else {
					$token = $current_cookie['token'];
					$sess = $manager->get($token);
					$sess['expiration'] = $expiration;
					$manager->update($token, $sess);
				}

				$auth_cookie = wp_generate_auth_cookie( $user_id, $expiration, $scheme, $token );
				$logged_in_cookie = wp_generate_auth_cookie( $user_id, $expiration, 'logged_in', $token );

				/**
				 * Fires immediately before the authentication cookie is set.
				 *
				 * @since 2.5.0
				 *
				 * @param string $auth_cookie Authentication cookie.
				 * @param int    $expire      Login grace period in seconds. Default 43,200 seconds, or 12 hours.
				 * @param int    $expiration  Duration in seconds the authentication cookie should be valid.
				 *                            Default 1,209,600 seconds, or 14 days.
				 * @param int    $user_id     User ID.
				 * @param string $scheme      Authentication scheme. Values include 'auth', 'secure_auth', or 'logged_in'.
				 */
				do_action( 'set_auth_cookie', $auth_cookie, $expire, $expiration, $user_id, $scheme );

				/**
				 * Fires immediately before the secure authentication cookie is set.
				 *
				 * @since 2.6.0
				 *
				 * @param string $logged_in_cookie The logged-in cookie.
				 * @param int    $expire           Login grace period in seconds. Default 43,200 seconds, or 12 hours.
				 * @param int    $expiration       Duration in seconds the authentication cookie should be valid.
				 *                                 Default 1,209,600 seconds, or 14 days.
				 * @param int    $user_id          User ID.
				 * @param string $scheme           Authentication scheme. Default 'logged_in'.
				 */
				do_action( 'set_logged_in_cookie', $logged_in_cookie, $expire, $expiration, $user_id, 'logged_in' );

				setcookie($auth_cookie_name, $auth_cookie, $expire, PLUGINS_COOKIE_PATH, COOKIE_DOMAIN, $secure, true);
				setcookie($auth_cookie_name, $auth_cookie, $expire, ADMIN_COOKIE_PATH, COOKIE_DOMAIN, $secure, true);
				setcookie(LOGGED_IN_COOKIE, $logged_in_cookie, $expire, COOKIEPATH, COOKIE_DOMAIN, $secure_logged_in_cookie, true);
				if ( COOKIEPATH != SITECOOKIEPATH )
					setcookie(LOGGED_IN_COOKIE, $logged_in_cookie, $expire, SITECOOKIEPATH, COOKIE_DOMAIN, $secure_logged_in_cookie, true);
			}
		endif;
	endif;
	
	QSOT::pre_init();
}

endif;
