<?php if ( __FILE__ == $_SERVER['SCRIPT_FILENAME'] ) die( header( 'Location: /') );
/**
 * Plugin Name: OpenTickets Community Edition
 * Plugin URI:  http://opentickets.com/
 * Description: Event Management and Online Ticket Sales Platform
 * Version:     2.4.5
 * Author:      Quadshot Software LLC
 * Author URI:  http://quadshot.com/
 * Copyright:   Copyright (C) 2009-2014 Quadshot Software LLC
 * License: GNU General Public License, version 3 (GPL-3.0)
 * License URI: http://www.gnu.org/copyleft/gpl.html
 *
 * An event managment and online ticket sales platform, built on top of WooCommerce.
 */

/* Primary class for controlling the events post type. Loads all pieces of the Events puzzle. */
class opentickets_community_launcher {
	protected static $o = null; // holder for all options of the events plugin
	protected static $active = false;

	// list of extensions to perform compatibility checks on
	protected static $compatibility_checks = array(
		array( 'file' => 'qsot-ga-multi-price/qsot-ga-multi-price.php', 'min_version' => '2.0.0' ),
		array( 'file' => 'qsot-seating/qsot-seating.php', 'min_version' => '2.0.0' ),
		array( 'file' => 'qsot-box-office/qsot-box-office.php', 'min_version' => '2.0.0' ),
		array( 'file' => 'qsot-display-options/qsot-display-options.php', 'min_version' => '2.0.0' ),
		array( 'file' => 'qsot-coupons/qsot-coupons.php', 'min_version' => '2.0.0' ),
	);

	// test comment
	// initialize/load everything related to the core plugin
	public static function pre_init() {
		// load the db upgrader, so that all plugins can interface with it before it does it's magic
		require_once 'inc/sys/db-upgrade.php';
		// load the internal core settings sub plugin early, since it controls all the plugin settings, and the object extender, cause it is important
		require_once 'inc/sys/utils.php';
		require_once 'inc/sys/settings.php';
		require_once 'inc/sys/options.php';
		require_once 'inc/sys/templates.php';
		require_once 'inc/sys/rewrite.php';
		require_once 'inc/sys/deprecated.php';
		require_once 'inc/sys/ajax.php';

		// load the settings object
		$settings_class_name = apply_filters('qsot-settings-class-name', '');
		if (empty($settings_class_name)) return;
		self::$o = call_user_func_array(array($settings_class_name, "instance"), array());
		// set the base settings for the plugin
		self::$o->set(false, array(
			'product_name' => 'OpenTickets',
			'product_url' => 'http://opentickets.com/',
			'settings_page_uri' => '/admin.php?page=opentickets-settings',
			'pre' => 'qsot-',
			'fctm' => 'fc',
			'always_reserve' => 0,
			'version' => '2.4.5',
			'min_wc_version' => '2.6.1',
			'core_post_type' => 'qsot-event',
			'core_post_rewrite_slug' => 'event',
			'core_file' => __FILE__,
			'core_dir' => trailingslashit(plugin_dir_path(__FILE__)),
			'core_url' => trailingslashit(plugin_dir_url(__FILE__)),
			'anonfuncs' => version_compare(PHP_VERSION, '5.3.0') >= 0,
			'php_version' => PHP_VERSION,
			'wc_version' => get_option('woocommerce_version', '0.0.0'),
			'wp_version' => $GLOBALS['wp_version'],
		));

		// check the current version, and update the db value of that version number if it is not correct, but only on admin pages
		if ( is_admin() )
			self::_check_version();

		// require woocommerce
		if (self::_is_woocommerce_active()) {
			if (self::_has_woocommerce_min_version()) {
				// patch CORS issue where SSL forced admin prevents CORS from validating, making the calendar not work, and pretty much any ajax request on the frontend
				//self::maybe_patch_CORS();
				if ( isset( $_GET['test'] ) )
					self::otce_2_0_0_compatibility_check();

				// load opentickets
				require_once 'opentickets.php';
			} else {
				add_action('admin_notices', array(__CLASS__, 'requires_woocommerce_min_version'), 10);
			}
		} else {
			add_action('admin_notices', array(__CLASS__, 'requires_woocommerce'), 10);
			$me = plugin_basename(self::$o->core_file);
			$wc = substr($me, 0, strpos($me, 'opentickets-community')).implode(DIRECTORY_SEPARATOR, array('woocommerce', 'woocommerce.php'));
			add_action('activate_'.$wc, array(__CLASS__, 'wc_activation'), 0);
		}

		// remove the keychain functionality
		add_action( 'plugins_loaded', array( __CLASS__, 'remove_keychain' ), -1 );

		// deactivate extensions that are outdated and are incompatible with OTCE 2.0.0
		add_action( 'activated_plugin', array( __CLASS__, 'otce_2_0_0_compatibility_check' ), -10 );
		add_action( 'deactivated_plugin', array( __CLASS__, 'otce_2_0_0_compatibility_check' ), -10 );
		add_action( 'admin_notices', array( __CLASS__, 'maybe_otce_2_0_0_compatibility_message' ), -10 );
	}

	// when a site uses the 'FORCE_SSL_ADMIN' constant, or hasany of the random plugins that force ssl in the admin, a bad situation occurs, in terms of ajax.
	// most of the time in this scenario, the frontend of the site is over HTTP while the admin is being forced to HTTPS. however, if plugins are properly designed, all their
	// ajax requests use the /wp-admin/admin-ajax.php ajax target url. this presents a problem, because now ALL AJAX request hit HTTPS://site.com/wp-admin/admin-ajax.php .
	// at first glance this is not an issue, but once you start seeing that your ajax requests on the frontend stop working, you start getting concerned.
	// the problem here is this:
	//   CORS is active in most modern browsers. CORS _denies_ all ajax responses that are considered 'not the same domain'. unfortunately, one of the things that makes
	//   two domains 'not the same domain' is the protocol that is being used on each. thus, if you make an ajax request from the homepage (HTTP://site.com/) to the proper ajax
	//   url (HTTPS://site.com/wp-admin/admin-ajax.php) you get blocked by CORS because the requesting page is using a different protocol than the requested page. (HTTP to HTTPS)
	// this is a core wordpress bug. to work around the problem, you have two options:
	//   - allow ajax requests from all urls on the net to hit your site ( Access-Control-Allow-Origin: * ), or
	//   - allow for HTTP and HTTPS to be considered the same url, by sniffing the requester origin, and spitting it back out as the allowed origin
	// we chose to use the more secure version of this. so here is the work around
	public static function maybe_patch_CORS() {
		// fetch all the headers we have sent already. we do this because we do not want to send the header again if some other plugin does this already, or if core WP gets an unexpected patch
		$sent = self::_sent_headers();

		// if the allow-control-allow-origin header is not already sent, then attempt to add it if we can
		if ( ! isset( $sent['access-control-allow-origin'] ) ) {
			// figure out the site url, so we can determine if we should allow the origin access to this resource, by comparing the origin to our site domain
			$surl = @parse_url( site_url() );

			// get all the request headers
			$headers = self::_get_headers();

			// test if the 'origin' request header DOMAIN matches our site url DOMAIN, regardless of protocol
			if ( isset( $headers['origin'] ) && ( $ourl = @parse_url( $headers['origin'] ) ) && $our['host'] == $surl['host'] ) {
				// if it does, allow this origin access
				header( 'Access-Control-Allow-Origin: ' . $headers['origin'] );
			}
		}
	}

	// remove the keychaning plugin functionality
	public static function remove_keychain() {
		// only do this if the keychain plugin is active
		if ( ! self::_is_plugin_active( 'qsot-keychain', 'qsot-keychain.php' ) || ! class_exists( 'QSOT_addon_registry' ) )
			return;

		// get the keychain object
		$instance = QSOT_addon_registry::instance();

		// remove license checker class actions and filters that are always loaded
		remove_action( 'qsot-register-addon', array( &$instance, 'register_addon' ), 10 );
		remove_action( 'qsot_settings_save_lics', array( &$instance, 'save_keys' ), 10 );
		remove_action( 'admin_notices', array( &$instance, 'enter_key_nag' ), 20 );
		remove_action( 'qsot-after-loading-modules-and-plugins', array( &$instance, 'bundle' ), 10 );
		remove_action( 'activated_plugin', array( &$instance, 'after_initial_pluging_activation' ), 100 );

		// remove keychain launcher class actions and filters that are always loaded
		remove_action( 'admin_notices', array( 'QSOT_keychain', 'admin_notices' ), 10 );

		if ( is_admin() ) {
			// remove license checker class actions and filters that are only loaded in the admin
			remove_action( 'admin_init', array( &$instance, 'maybe_force_check' ), 0 );

			// remove keychain launcher class actions and filters that are only loaded in the admin
			remove_filter( 'qsot_get_settings_pages', array( 'QSOT_keychain', 'add_settings_page' ), 10, 1 );
			remove_filter( 'pre_update_option_active_plugins', array( 'QSOT_keychain', 'load_me_first' ), 10, 2 );
			remove_filter( 'plugin_action_links', array( 'QSOT_keychain', 'plugins_page_actions' ), 10, 4 );
		}

		// add a message showing that keychain is active, and that it is now obsolete
		add_action( 'admin_notices', array( __CLASS__, 'notice_keychain_is_obsolete' ), -1 );
	}

	// on page load, if there are any plugins that are active, that are known to not be compatible with otce 2.0.0, we need to deactivate them, add a message to the admin, and redirect
	public static function otce_2_0_0_compatibility_check( $redirect=false ) {
		// only do this in the admin
		if ( ! is_admin() )
			return;

		// require the needed file, if the get_plugins() is not available
		if ( ! function_exists( 'get_plugins' ) )
			require_once ABSPATH . 'wp-admin/includes/plugin.php';

		// get a list of all the installed plugins, so that we can test them for compatibility
		$installed = get_plugins();

		$bad_names = array();
		$bad_keys = array();
		// perform a series of compatibility checks, and add any that fail to a couple lists for later use
		foreach ( self::$compatibility_checks as $item ) {
			if ( isset( $installed[ $item['file'] ] ) && version_compare( $item['min_version'], $installed[ $item['file'] ]['Version'] ) > 0 ) {
				$bad_names[] = $installed[ $item['file'] ]['Name'];
				$bad_keys[] = $item['file'];
			}
		}

		// add a message that indicates that the plugins were deactivated because they are out of date and have compatibility issues with OTCE, and should be updated
		update_option( 'otce_2_0_0_compatibility_issues', $bad_names );

		$active = self::_find_active_plugins( true );
		$need_deactivation = array_intersect( $active, $bad_keys );
		// if there are not any compatibility issues, then bail now
		if ( empty( $need_deactivation ) )
			return;

		// update the active plugins
		update_option( 'active_plugins', array_diff( $active, $need_deactivation ) );

		if ( is_bool( $redirect ) && true === $redirect ) {
			wp_redirect( remove_query_arg( 'test' ) );
			exit;
		}
	}

	// maybe show a message about installed plugins that have known compatibility issues with OTCE 2.0.0 (mostly old versions of extensions)
	public static function maybe_otce_2_0_0_compatibility_message() {
		// get the name list of all installed plugins that have compatibility problems with OTCE 2.0.0
		$list = get_option( 'otce_2_0_0_compatibility_issues', array() );

		// if there are none, then bail
		if ( empty( $list ) )
			return;

		// otherwise, pop an error
		?>
			<div class="error">
				<p><?php _e( 'There are installed plugins that need to be updated before they can be used with OpenTickets Community Edition 2.0.0 or higher. Some may have been deactivated automatically:', 'opentickets-community-edition' ) ?></p>
				<p><strong><em><?php echo implode( ', ', $list ) ?></em></strong></p>
			</div>
		<?php
	}

	// pop a message showing that keychain is now obsolete, and should be removed
	public static function notice_keychain_is_obsolete() {
		$plugin_file = 'qsot-keychain/qsot-keychain.php';
		$context = 'all';
		$page = 1;
		$s = '';
		?>
			<div class="error">
				<p><?php echo sprintf(
					__( 'We noticed that you still have the %sOpenTickets - Keychain%s plugin active. This plugin is now obsolete. All of it\'s functionality has been disabled. The plugin should be %sdeactivated%s and removed.', 'opentickets-community-edition' ),
					'<strong><em>',
					'</em></strong>',
					sprintf(
						'<a href="%s" title="%s">',
						wp_nonce_url( admin_url( 'plugins.php?action=deactivate&amp;plugin=' . $plugin_file . '&amp;plugin_status=' . $context . '&amp;paged=' . $page . '&amp;s=' . $s ), 'deactivate-plugin_' . $plugin_file ),
						__( 'Deactivate the OpenTickets - Keychain plugin', 'opentickets-community-edition' )
					),
					'</a>'
				) ?></p>
			</div>
		<?php
	}

	// gather all the headers that have been sent already
	protected static function _sent_headers() {
		$headers = array();
		$list = headers_list();

		// format the headers into array( 'header-name' => 'header value' ) form, making sure to normalize the header-names to lowercase for comparisons later
		foreach ( $list as $header ) {
			$parts = array_map( 'trim', explode( ':', $header, 2 ) );
			$key = strtolower( $parts[0] );
			$headers[ $key ] = implode( ':', $parts );
		}

		return $headers;
	}

	// cross server method to fetch all the request headers
	protected static function _get_headers() {
		// serve cached values if we have called this function before
		static $headers = false;
		if ( is_array( $headers ) ) return $headers;

		// if we are using apache, then there is a function for getting all the request headers already. just normalize the header-name to lowercase and pass it on through
		if ( function_exists( 'getallheaders' ) ) return $headers = array_change_key_case( getallheaders() );

		$headers = array();
		// on other webservers, we may nt have that function, so just pull all the header information out of the $_SERVER[] superglobal, becasue they will definitely be present there
		foreach ( $_SERVER as $key => $value ) {
			// look for http headers marked with HTTP_ prefix
			if ( substr( $key, 0, 5 ) == 'HTTP_' ) {
				$key = str_replace( ' ', '-', strtolower( str_replace( '_', ' ', substr( $key, 5 ) ) ) );
				$headers[ $key ] = $value;
			// special case for the content-type header
			} elseif ( $key == "CONTENT_TYPE" ) {
				$headers["content-type"] = $value;
			// special case for the content-length header
			} elseif ( $key == "CONTENT_LENGTH" ) {
				$headers["content-length"] = $value;
			}
		}
		return $headers;
	}

	// update the recorded version, so that other plugins do not have to do fancy lookups to find it
	protected static function _check_version() {
		$version = get_option( 'opentickets_community_edition_version', '' );
		if ( $version !== self::$o->version ) {
			update_option( 'opentickets_community_edition_version', self::$o->version );
			add_action( 'plugins_loaded', array( __CLASS__, 'run_updates' ), 1 );
		}
	}

	// after a successful plugin update, we may need to run some code to handle some upgrade logic
	public static function run_updates() {
		do_action( 'qsot-otce-updated', self::$o->version );
	}

	//run out activation code upon woocommerce activation, if woocommerce is activated AFTER OpenTickets
	public static function wc_activation() {
		require_once 'opentickets.php';
		QSOT::activation();
	}

	// fill a static var with the list of all active plugins
	protected static function _find_active_plugins( $only_this_site=false ) {
		// get a list of this site's active plugins
		$active = get_option( 'active_plugins', array() );
		if ( $only_this_site )
			return $active;

		// if we do not have a comleted active plugins list then...
		if ( false === self::$active ) {
			// also get a list of the network plugins active
			$network = defined( 'MULTISITE' ) && MULTISITE ? get_site_option( 'active_sitewide_plugins' ) : array();
			$network = is_array( $network ) ? $network : array();
			$active = is_array( $active ) ? $active : array();

			// update the internal full list cache
			self::$active = array_merge( array_keys( $network ), $active );
		}
	}

	// determine if a given plugin is active
	public static function _is_plugin_active( $plugin_dir, $plugin_file ) {
		self::_find_active_plugins();

		// check if the regular plugin is active. now DIRECTORY_SEPARATOR here, because wp translates it to '/' for the active plugin arrays
		$is_active = in_array( $plugin_dir . '/' . $plugin_file, self::$active );

		// if the regular plugin is not acitve, check for known direcotry formats for github zip downloads
		if ( ! $is_active ) {
			foreach ( self::$active as $active_plugin ) {
				if ( preg_match( '#^' . preg_quote( $plugin_dir, '#' ) . '-(master|[\d\.]+(-(alpha|beta|RC\d+)(-\d+)?)?)[\/\\\\]' . preg_quote( $plugin_file, '#' ). '$#', $active_plugin ) ) {
					$is_active = true;
					break;
				}
			}
		}

		return $is_active;
	}

	// determine if woocommerce is active
	protected static function _is_woocommerce_active() {
		return self::_is_plugin_active( 'woocommerce', 'woocommerce.php' );
	}

	protected static function _has_woocommerce_min_version() {
		return version_compare(self::$o->{'wc_version'}, self::$o->min_wc_version) >= 0;
	}

	public static function requires_woocommerce_min_version() {
		?>
			<div class="error errors">
				<p class="error">
					<u><strong><?php _e('Required Plugin Not Up-to-date','opentickets-community-edition') ?></strong></u><br/>
					<?php 
						printf(
							__('The <em><a href="%s" target="_blank">%s</a></em> plugin <strong>requires</strong> that <em><a href="http://www.woothemes.com/woocommerce/" target="_blank">WooCommerce</a></em> be at least at version <u><strong>%s</strong></u>; you are currently running version <em>%s</em>. Because of this, the <em><a href="%s" target="_blank">%s</a></em> plugin has not initialized any of its functionality. To enable the features of this plugin, simply install and activate the latest version of <em><a href="http://www.woothemes.com/woocommerce/" target="_blank">WooCommerce</a></em>.','opentickets-community-edition'),
							esc_attr(self::$o->product_url),
							force_balance_tags(self::$o->product_name),
							self::$o->min_wc_version,
							get_option('woocommerce_version', '0.0.0'),
							esc_attr(self::$o->product_url),
							force_balance_tags(self::$o->product_name)
						);
					?>	
				</p>
			</div>
		<?php
	}

	public static function requires_woocommerce() {
		?>
			<div class="error errors">
				<p class="error">
					<u><strong><?php _e('Missing Required Plugin','opentickets-community-edition') ?></strong></u><br/>
					<?php 
						printf(
							__('The <em><a href="%s" target="_blank">%s</a></em> plugin <strong>requires</strong> that <em><a href="http://www.woothemes.com/woocommerce/" target="_blank">WooCommerce</a></em>	be activated in order to perform most vital functions; therefore, the plugin has not initialized any of its functionality. To enable the features of this plugin, simply install and activate <em><a href="http://www.woothemes.com/woocommerce/" target="_blank">WooCommerce</a></em>.','opentickets-community-edition'),
							esc_attr(self::$o->product_url),
							force_balance_tags(self::$o->product_name)							
						);
					?>
				</p>
			</div>
		<?php
	}
}

if (defined('ABSPATH') && function_exists('add_action')) {
	opentickets_community_launcher::pre_init();

	// hack for wordpress-https combined with woocommerce having the setting of force secure checkout:
	// basically for some reason, the 'lost-password' page that woocommerce creates (and only that page) has an infinite redirect loop where woocommerce wants it to be ssl, which it should be
	// and wordpress-https wants it to be non-ssl. even setting the wordpress-https setting on the lost-password page to force ssl DOES NOT make wordpress-https realize that it is being dumb
	// and requesting the wrong thing. ths ONLY work around for this i found is to set the flag on the lost-password post AND add a filter to FORCE wordpress-https to respect its OWN flag.
	// this filter needs to be the last filter that runs, because putting it at 10 does nothing because it is overwritten later
	function _qsot_wordpress_https_hack($current, $post_id, $url) {
		return get_post_meta($post_id, 'force_ssl', true);
	}
	add_filter('force_ssl', '_qsot_wordpress_https_hack', PHP_INT_MAX, 3);
}
