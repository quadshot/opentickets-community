<?php (__FILE__ == $_SERVER['SCRIPT_FILENAME']) ? die(header('Location: /')) : null;
/**
 * Plugin Name: OpenTickets Community Edition
 * Plugin URI:  http://opentickets.com/
 * Description: Event Management and Online Ticket Sales Platform
 * Version:     1.11.1
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
		require_once 'inc/sys/registry.php';
		require_once 'inc/sys/deprecated.php';

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
			'version' => '1.11.1',
			'min_wc_version' => '2.2.0',
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
		if ( $version !== self::$o->version )
			update_option( 'opentickets_community_edition_version', self::$o->version );
	}

	//run out activation code upon woocommerce activation, if woocommerce is activated AFTER OpenTickets
	public static function wc_activation() {
		require_once 'opentickets.php';
		QSOT::activation();
	}

	protected static function _is_woocommerce_active() {
		$active = get_option('active_plugins');
		$network = defined( 'MULTISITE' ) && MULTISITE ? get_site_option( 'active_sitewide_plugins' ) : array();
		$active = is_array( $active ) ? $active : array();
		$network = is_array( $network ) ? $network : array();
		$active = array_merge( array_keys( $network ), $active );

		$is_active = in_array('woocommerce/woocommerce.php', $active);
		// search for github-zip versions
		if ( ! $is_active ) {
			foreach ( $active as $plugin_path ) {
				if ( preg_match( '#^woocommerce-(master|[\d\.]+(-(alpha|beta|RC\d+)(-\d+)?)?)/woocommerce\.php$#', $plugin_path ) ) {
					$is_active = true;
					break;
				}
			}
		}
		return $is_active;
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
