<?php if ( __FILE__ == $_SERVER['SCRIPT_FILENAME'] ) die( header( 'Location: /') );

// the base class for all event area types. requires some basic functions and defines basic properties
abstract class QSOT_Base_Event_Area_Zoner {
	protected $stati = array();

	// basic constructor for the area type
	public function __construct() {
		// load the plugin options
		$options = QSOT_Options::instance();

		// setup the base stati
		$this->stati = array(
			'r' => array( 'reserved', 3600, __( 'Reserved', 'opentickets-community-edition' ), __( 'Not Paid', 'opentickets-community-edition' ), 3600 ),
			'c' => array( 'confirmed', 0, __( 'Confirmed', 'opentickets-community-edition' ), __( 'Paid', 'opentickets-community-edition' ), 0 ),
			'o' => array( 'occupied', 0, __( 'Occupied', 'opentickets-community-edition' ), __( 'Checked In', 'opentickets-community-edition' ), 0 ),
		);
		$this->_setup_options();
		$this->stati['r'][1] = intval( $options->{'qsot-reserved-state-timer'} );

		// update the list of stati after all plugins have been loaded
		if ( did_action( 'after_setup_theme' ) )
			$this->update_stati_list();
		else
			add_filter( 'after_setup_theme', array( &$this, 'update_stati_list' ), 10 );
	}

	// register all the assets used by this area type
	public function register_assets() {}

	// enqueue the frontend assets needed by this type
	public function enqueue_assets() {}

	// enqueue the admin assets needed by this type
	public function enqueue_admin_assets() {}

	// after all plugins are loaded, update the stati list for this zoner
	final public function update_stati_list() {
		$this->stati = apply_filters( 'qsot-zoner-stati', $this->stati, get_class( $this ) );
	}

	// get a status from our stati list
	public function get_stati( $key=null ) {
		return is_string( $key ) && isset( $this->stati[ $key ] ) ? $this->stati[ $key ] : ( null === $key ? $this->stati : null );
	}

	// get a list of temporary stati
	public function get_temp_stati() {
		$list = array();
		// find the stati with a non-zero timer
		foreach ( $this->stati as $k => $v )
			if ( $v[4] > 0 )
				$list[ $k ] = $v;
		return $list;
	}

	// current_user is the id we use to lookup tickets in relation to a product in a cart. once we have an order number this pretty much becomes obsolete, but is needed up til that moment
	public static function current_user( $data='' ) {
		return QSOT::current_user( $data );
	}

	// setup the options for allowing timers to be set
	protected function _setup_options() {}

	// define a function to grab the availability for an event
	abstract public function get_availability( $event, $event_area );

	// handle requests to reserve some tickets
	abstract public function reserve( $success, $args );

	// handle requests to confirm some reserved tickets
	abstract public function confirm( $success, $args );

	// handle requests to occupy some confirmed tickets
	abstract public function occupy( $success, $args );

	// handle requests to update some ticket reservations
	abstract public function update( $result, $args, $where );

	// handle requests to remove some ticket reservations
	abstract public function remove( $success, $args );

	// find records that match a search criteria
	abstract public function find( $args );
}
