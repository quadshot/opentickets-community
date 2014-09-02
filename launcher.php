<?php (__FILE__ == $_SERVER['SCRIPT_FILENAME']) ? die(header('Location: /')) : null;
/**
 * Plugin Name: OpenTickets Community Edition
 * Plugin URI:  http://opentickets.com/
 * Description: Event Management and Online Ticket Sales Platform
 * Version:     1.2.5
 * Author:      Quadshot Software LLC
 * Author URI:  http://quadshot.com/
 * License: GNU General Public License, version 3 (GPL-3.0)
 * License URI: http://opensource.org/licenses/gpl-3.0.html
 * Copyright Quadshot Software LLC 2009-2014
 */

/* Primary class for controlling the events post type. Loads all pieces of the Events puzzle. */
class opentickets_community_launcher {
	protected static $o = null; // holder for all options of the events plugin

	// initialize/load everything related to the core plugin
	public static function pre_init() {
		// load the db upgrader, so that all plugins can interface with it before it does it's magic
		require_once 'inc/sys/db-upgrade.php';
		// load the internal core settings sub plugin early, since it controls all the plugin settings, and the object extender, cause it is important
		require_once 'inc/sys/obj.php';
		require_once 'inc/sys/settings.php';
		require_once 'inc/sys/options.php';
		require_once 'inc/sys/templates.php';
		require_once 'inc/sys/registry.php';

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
			'version' => '1.2.5',
			'min_wc_version' => '2.1.0',
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

		// require woocommerce
		if (self::_is_woocommerce_active()) {
			if (self::_has_woocommerce_min_version()) {
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

	//run out activation code upon woocommerce activation, if woocommerce is activated AFTER OpenTickets
	public static function wc_activation() {
		require_once 'opentickets.php';
		QSOT::activation();
	}

	protected static function _is_woocommerce_active() {
		$active = get_option('active_plugins');
		$is_active = in_array('woocommerce/woocommerce.php', $active);
		return $is_active;
	}

	protected static function _has_woocommerce_min_version() {
		return version_compare(self::$o->{'wc_version'}, self::$o->min_wc_version) >= 0;
	}

	public static function requires_woocommerce_min_version() {
		?>
			<div class="error errors">
				<p class="error">
					<u><strong>Required Plugin Not Up-to-date</strong></u><br/>
					The
					<em><a href="<?php echo esc_attr(self::$o->product_url) ?>" target="_blank"><?php echo force_balance_tags(self::$o->product_name) ?></a></em>
					plugin <strong>requires</strong> that
					<em><a href="http://www.woothemes.com/woocommerce/" target="_blank">WooCommerce</a></em>
					be at least at version <u><strong><?php echo self::$o->min_wc_version ?></strong></u>;
					you are currently running version <em><?php echo get_option('woocommerce_version', '0.0.0') ?></em>.
					Because of this, the
					<em><a href="<?php echo esc_attr(self::$o->product_url) ?>" target="_blank"><?php echo force_balance_tags(self::$o->product_name) ?></a></em>
					plugin has not initialized any of its functionality.
					To enable the features of this plugin, simply install and activate the latest version of
					<em><a href="http://www.woothemes.com/woocommerce/" target="_blank">WooCommerce</a></em>.
				</p>
			</div>
		<?php
	}

	public static function requires_woocommerce() {
		?>
			<div class="error errors">
				<p class="error">
					<u><strong>Missing Required Plugin</strong></u><br/>
					The
					<em><a href="<?php echo esc_attr(self::$o->product_url) ?>" target="_blank"><?php echo force_balance_tags(self::$o->product_name) ?></a></em>
					plugin <strong>requires</strong> that
					<em><a href="http://www.woothemes.com/woocommerce/" target="_blank">WooCommerce</a></em>
					be activated in order to perform most vital functions;
					therefore, the plugin has not initialized any of its functionality.
					To enable the features of this plugin, simply install and activate
					<em><a href="http://www.woothemes.com/woocommerce/" target="_blank">WooCommerce</a></em>.
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
