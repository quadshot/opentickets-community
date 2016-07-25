<?php if ( __FILE__ == $_SERVER['SCRIPT_FILENAME'] ) die( header( 'Location: /') );

/*
QSOT API Autoloader

Loads only the endpoints and helpers we need, when we need them, instead of cluttering up memory with unused shit.
*/
class QSOT_API_Autoloader {
	// make this a singleton
	protected static $_instance = null;
	public static function instance() { return ( self::$_instance instanceof self ) ? self::$_instance : ( self::$_instance = new self ); }
	protected __construct() { $this->initialize(); }

	protected $prefix = 'QSOT_API_';

	// setup the object
	protected function initialize() {
		spl_autoload_register( array( &$this, 'autoload' ) );
	}

	// handle autoloading for the api
	public function autoload( $classname ) {
		// turn the classname into a filename
		$filename = str_replace( array( '__', '_' ), array( '/', '-' ), preg_replace( '#^' . $this->prefix . '#i', '', $classname ) ) . '.api.php';

		// figure out if the file exists
	}
}

// security
if ( defined( 'ABSPATH' ) && function_exists( 'add_action' ) )
	QSOT_API_Autoloader::instance();
