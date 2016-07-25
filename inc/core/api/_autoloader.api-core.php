<?php if ( __FILE__ == $_SERVER['SCRIPT_FILENAME'] ) die( header( 'Location: /') );

/*
QSOT API Autoloader

Loads only the endpoints and helpers we need, when we need them, instead of cluttering up memory with unused shit.
*/
class QSOT_API_Autoloader {
	// make this a singleton
	protected static $_instance = null;
	public static function instance() { return ( self::$_instance instanceof self ) ? self::$_instance : ( self::$_instance = new self ); }
	protected function __construct() { $this->initialize(); }

	protected $prefix = 'QSOT_';

	// setup the object
	protected function initialize() {
		spl_autoload_register( array( &$this, 'autoload' ) );
	}

	// handle autoloading for the api
	public function autoload( $classname ) {
		// get the core plugin dir
		static $core_dir = false;
		if ( false === $core_dir )
			$core_dir = trailingslashit( QSOT::plugin_dir() );

		// turn the classname into a filename
		$filename = str_replace( array( '__', '_' ), array( '/', '-' ), strtolower( preg_replace( '#^' . $this->prefix . '#i', '', $classname ) ) ) . '.api.php';

		$found = '';
		// figure out if the file exists
		foreach ( apply_filters( 'qsot-load-includes-dirs', array( $core_dir . 'inc/' ) ) as $dir ) {
			if ( @file_exists( $dir . $filename ) && is_file( $dir . $filename ) && is_readable( $dir . $filename ) ) {
				$found = $dir . $filename;
				break;
			}
		}

		// if we found a file, load it now
		if ( $found )
			require_once $found;
	}
}

// security
if ( defined( 'ABSPATH' ) && function_exists( 'add_action' ) )
	QSOT_API_Autoloader::instance();
