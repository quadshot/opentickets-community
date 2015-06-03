<?php ( __FILE__ == $_SERVER['SCRIPT_FILENAME'] ) ? die( header( 'Location: /' ) ) : null;

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
						sprintf( __( 'The "%s" filter', 'opentickets-community-edition' ), $new_filter )
					);
				}
			}
		}

		return $data;
	}
}

if ( defined( 'ABSPATH' ) && function_exists( 'add_action' ) )
	QSOT_deprecated::pre_init();
