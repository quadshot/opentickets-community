<?php if ( __FILE__ == $_SERVER['SCRIPT_FILENAME'] ) die( header( 'Location: /') );

// handles the deprecation of actions and filters for now
class QSOT_deprecated {
	// mapped list of deprecated actions and filters. new_filter => array( array( old_filter, deprecation_version ) ). structure is in case multiple filters are condensed
	protected static $map = array(
		'woocommerce_order_item_meta_start' => array(
			array( 'qsot-order-item-list-ticket-info', '1.10.20' ),
		),
	);

	// setup the class
	public static function pre_init() {
		foreach ( self::$map as $new_filter => $old_filter_list )
			add_filter( $new_filter, array( __CLASS__, 'handle_deprecation' ), 10, 100 );
	}

	// deprecation handler
	public static function handle_deprecation( $data ) {
		// determine the current filter
		$current_filter = current_filter();

		// figure out if the current filter is actually in our map list
		if ( isset( self::$map[ $current_filter ] ) ) {
			// get a list of this function call's args, for use when calling deprecated filters
			$args = func_get_args();
			array_unshift( $args, null );

			// get the list of all the potential old filters
			$old_filters = (array) self::$map[ $current_filter ];

			// for each matching old filter we have..
			foreach ( $old_filters as $old_filter_info ) {
				list( $old_filter, $deprecation_version ) = $old_filter_info;
				// if there is a register function on that old filter
				if ( has_action( $old_filter ) ) {
					// then call those register functions
					$args[0] = $old_filter;
					$data = call_user_func_array( 'apply_filters', $args );

					// pop the deprecation message
					_deprecated_function(
						sprintf( __( 'The "%s" filter', 'opentickets-community-edition' ), $old_filter ),
						$deprecation_version, 
						sprintf( __( 'The "%s" filter', 'opentickets-community-edition' ), $current_filter )
					);
				}
			}
		}

		return $data;
	}
}

// legacy extension license manager stub. needed for older extensions, like GAMP, until users upgrade
if ( ! class_exists( 'QSOT_addon_registry' ) ):

class QSOT_addon_registry {
	private static $instance = null;

	// create the initial instance of the registry
	public static function pre_init() { self::instance(); }

	// create a registry instance.
	public static function instance() {
		$me = __CLASS__;
		if ( self::$instance !== null && self::$instance instanceof $me )
			return self::$instance;
		self::$instance = new $me();
		return self::$instance;
	}

	// singleton constructor
	public function __construct() {
		if ( self::$instance != null && self::$instance instanceof $me )
			throw new Exception( sprintf( __( 'Only one instance of %s can be created.', 'opentickets-community-edition' ), __CLASS__ ), 501 );
	}

	// stub functions
	public function is_activated( $addon ) {
		_deprecated_function( __CLASS__ . '::is_activated', '1.14.0' );
		return true; // always true, since this is deprecated
	}
	public function force_check() {
		_deprecated_function( __CLASS__ . '::is_activated', '1.14.0' );
		return false;
	}
}

// start the initial registry instance
if ( defined( 'ABSPATH' ) && function_exists( 'add_action' ) )
	QSOT_addon_registry::pre_init();

endif;

if ( defined( 'ABSPATH' ) && function_exists( 'add_action' ) )
	QSOT_deprecated::pre_init();
