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

	// clear out any temporary locks that have expired
	public static function clear_locks( $event_id=0, $customer_id=false ) {
		global $wpdb;
		// require either required basic information type
		if ( empty( $event_id ) && empty( $customer_id ) )
			return;

		// get a list of all the expirable states
		$temp_states = $this->get_temp_stati();

		// cycle through the list of temp states and remove any expired keys with those states based on the supplied information
		foreach ( $temp_states as $state ) {
			// if this is not a temp state, then skip it
			if ( $state[1] >= 0 )
				continue;

			// build a query that will find all locks that have expired, based on the supplied criteria. we fetch the list so that we can
			// notify other sources that these locks are going away (such as other plugins, or upgrades to this plugin)
			$q = $wpdb->prepare( 'select * from ' . $wpdb->qsot_event_zone_to_order . ' where state = %s and since < NOW() - INTERVAL %d SECOND', $state[0], $state[1] );

			// if the event was supplied, reduce the list to only ones for this event
			if ( ! empty( $event_id ) )
				$q .= $wpdb->prepare( ' and event_id = %d', $event_id );

			// if the customer id was supplied then, add that to the sql
			if ( ! empty( $customer_id ) ) {
				if ( is_array( $customer_id ) )
					$q .= ' and session_customer_id in(\'' . implode( '\',\'', array_map( 'esc_sql', $customer_id ) ) . '\')';
				else
					$q .= $wpdb->prepare( ' and session_customer_id = %s', $customer_id );
			}

			// fetch a list of existing locks.
			$locks = $wpdb->get_results( $q );

			// if there are no locks to remove, then skip this item
			if ( empty( $locks ) )
				continue;

			// tell everyone that the locks are going away
			do_action( 'qsot-removing-zone-locks', $locks, $state[0], $event_id, $customer_id );

			// delete the locks we said we would delete in the above action.
			// this is done in this manner, because we need to only delete the ones we told others about.
			// technically, if the above action call takes too long, other locks could have expired by the time we get to delete them.
			// thus we need to explicitly delete ONLY the ones we told everyone we were deleting, so that none are removed without the others being notified.
			$q = 'delete from ' . $wpdb->qsot_event_zone_to_order . ' where '; // base query
			$wheres = array(); // holder for queries defining each specific row to delete

			// cycle through all the locks we said we would delete
			foreach ( $locks as $lock ) {
				// aggregate a partial where statement, that specifically identifies this row, using all fields for positive id
				$fields = array();
				foreach ( $lock as $k => $v )
					$fields[] = $wpdb->prepare( $k.' = %s', $v );
				if ( ! empty( $fields ) )
					$wheres[] = implode( ' and ', $fields );
			}

			// if we have where statements for at least one row to remove
			if ( ! empty( $wheres ) ) {
				// glue the query together, and run it to delete the rows
				$q .= '(' . implode( ') or (', $wheres ) . ')';
				$wpdb->query( $q );
			}
		}
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
