<?php if ( __FILE__ == $_SERVER['SCRIPT_FILENAME'] ) die( header( 'Location: /') );

// admin functionality of the extension fetcher
class QSOT_Extensions_Admin {
	// setup the actions, filters, and base data for this class
	public static function pre_init() {
		// somethings can be used when in the admin OR doing cron... those are loaded here
		if ( is_admin() || ( defined( 'DOING_CRON' ) && DOING_CRON ) ) {
			// base includes dir
			$dir = QSOT::plugin_dir() . 'inc/sys/extensions/';
			// load additionally needed files
			require_once $dir . 'extensions.php';
			require_once $dir . 'api.php';
			require_once $dir . 'updater.php';
			require_once $dir . 'pages/extensions.page.php';

			// load the object that handles the list of plugins that we need to check for updates on, or display on licenses pages and such
			QSOT_Extensions::instance();
		}

		// other things can only be used in the admin. load those here
		if ( is_admin() ) {
		}
	}
}

// security
if ( defined( 'ABSPATH' ) && function_exists( 'add_action' ) )
	QSOT_Extensions_Admin::pre_init();
