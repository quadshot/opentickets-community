<?php if ( __FILE__ == $_SERVER['SCRIPT_FILENAME'] ) die( header( 'Location: /') );

// core functionality of the extension fetcher
class QSOT_Extensions_Core {
	// setup the actions, filters, and base data for this class
	public static function pre_init() {
	}
}

// security
if ( defined( 'ABSPATH' ) && function_exists( 'add_action' ) )
	QSOT_Extensions_Core::pre_init();
