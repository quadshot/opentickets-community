<?php if ( __FILE__ == $_SERVER['SCRIPT_FILENAME'] ) die( header( 'Location: /') );

// class to handle the basic general admission event area type
class QSOT_General_Admission_Zoner extends QSOT_Base_Event_Area_Zoner {
	// container for the singleton instance
	protected static $instance = null;

	// get the singleton instance
	public static function instance() {
		// if the instance already exists, use it
		if ( isset( self::$instance ) && self::$instance instanceof QSOT_General_Admission_Zoner )
			return self::$instance;

		// otherwise, start a new instance
		return self::$instance = new QSOT_General_Admission_Zoner();
	}

	// constructor. handles instance setup, and multi instance prevention
	public function __construct() {
		// if there is already an instance of this object, then bail now
		if ( isset( self::$instance ) && self::$instance instanceof QSOT_General_Admission_Zoner )
			throw new Exception( sprintf( __( 'There can only be one instance of the %s object at a time.', 'opentickets-community-edition' ), __CLASS__ ), 12000 );

		// otherwise, set this as the known instance
		self::$instance = $this;

		// defaults from parent
		parent::__construct();

		// and call the intialization function
		$this->initialize();
	}

	// destructor. handles instance destruction
	public function __destruct() {
		$this->deinitialize();
	}


	// setup the object
	public function initialize() {
		add_filter( 'qsot-can-add-tickets-to-cart', array( &$this, 'enforce_purchase_limit' ), 10, 3 );
	}

	// destroy the object
	public function deinitialize() {
		remove_filter( 'qsot-can-add-tickets-to-cart', array( &$this, 'enforce_purchase_limit' ), 10 );
	}

	// obtain lock on a certain number of tickets
	protected function _obtain_lock( $data=array() ) {
		global $wpdb;
		// normalize the lock data
		$data = apply_filters( 'qsot-lock-data', wp_parse_args( $data, array(
			'event_id' => 0,
			'ticket_type_id' => 0,
			'quantity' => 0,
			'customer_id' => '',
			'order_id' => '',
		) ) );

		// if there is not at least basic required info, then bail
		if ( $data['event_id'] <= 0 || $data['ticket_type_id'] <= 0 || $data['quantity'] <= 0 )
			return new WP_Error( 'missing_data', __( 'Some or all of the required data is missing', 'opentickets-community-edition' ) );

		// create a unique id for this temporary lock, so that we can easily id the lock and remove it after the lock has passed inspection
		$uniq_lock_id = uniqid( 'temp-lock-', true );

		// obtain a temporary lock of the requested quantity. this will be used in a moment to determine if the user has the ability to reserve this number of tickets
		$wpdb->insert(
			$wpdb->qsot_event_zone_to_order,
			array(
				'event_id' => $data['event_id'],
				'ticket_type_id' => $data['ticket_type_id'],
				'state' => $this->stati['r'][0],
				'mille' => QSOT::mille(),
				'quantity' => $data['quantity'],
				'session_customer_id' => $uniq_lock_id,
				'order_id' => $data['order_id'],
			)
		);
		return $wpdb->get_row( $wpdb->prepare( 'select * from ' . $wpdb->qsot_event_zone_to_order . ' where session_customer_id = %s', $uniq_lock_id ) );
	}

	// remove a previously obtained lock
	protected function _remove_lock( $lock ) {
		// if the supplied data is not a lock, then bail
		if ( ! is_object( $lock ) || ! isset( $lock->session_customer_id, $lock->mille ) )
			return new WP_Error( 'invalid_lock', __( 'The supplied lock, was not valid.', 'opentickets-community-edition' ) );

		global $wpdb;
		// remove the lock
		$wpdb->delete( $wpdb->qsot_event_zone_to_order, array( 'session_customer_id' => $lock->session_customer_id, 'mille' => $lock->mille ) );
	}

	// we may need to enforce a per-event ticket limit. check that here
	public function enforce_purchase_limit( $current, $deprecated_event=null, $args='' ) {
		// deprecate arg
		if ( null !== $deprecated_event )
			_deprecated_argument( __FUNCTION__, 'OpenTickets 2.0', __( 'Argument #2 is obsolete.', 'opentickets-community-edition' ) );

		// normalize the args
		$args = wp_parse_args( $args, array(
			'event_id' => 0,
			'ticket_type_id' => 0,
			'order_id' => 0,
			'customer_id' => '',
			'quantity' => 1,
			'state' => $this->stati['r'][0]
		) );
		$args['event_id'] = is_numeric( $args['event_id'] ) && $args['event_id'] > 0 ? (int) $args['event_id'] : ( is_object( $args['event_id'] ) && isset( $args['event_id']->ID ) ? $args['event_id']->ID : false );
		$args['ticket_type_id'] = is_numeric( $args['ticket_type_id'] ) && $args['ticket_type_id'] > 0
				? $args['ticket_type_id']
				: ( is_object( $args['ticket_type_id'] ) && isset( $args['ticket_type_id']->id ) ? $args['ticket_type_id']->id : false );

		// if the basic information is not present, then bail
		if ( $args['event_id'] <= 0 )
			return new WP_Error( 'invalid_event', __( 'Could not find that event.', 'opentickets-community-edition' ) );

		// figure out the event ticket limit, if any
		$limit = apply_filters( 'qsot-event-ticket-purchase-limit', 0, $args['event_id'] );

		// if there is no limit, then bail
		if ( $limit <= 0 )
			return $current;

		// determine how many tickets they currently have for this event
		$find_args = $args;
		$find_args['fields'] = 'total';
		unset( $find_args['quantity'], $find_args['ticket_type_id'] );
		$total_for_event = $this->find( $find_args );

		// if the current total they have is great than or equal to the event limit, then bail with an error stating that they are already at the limit
		if ( $args['quantity'] > $total_for_event && $total_for_event >= $limit )
			return new WP_Error( 10, __( 'You have reached the ticket limit for this event.', 'opentickets-community-edition' ) );
		else if ( $args['quantity'] > $limit )
			return $limit;
		
		// if we get this far, then they are allowed
		return true;
	}

	// find some rows, based on some search criteria
	public function find( $args ) {
		return QSOT_Zoner_Query::instance()->find( $args );
	}

	// method to reserve some tickets
	public function reserve( $success, $args ) {
		// normalize input data
		$args = wp_parse_args( $args, array(
			'event_id' => false,
			'ticket_type_id' => 0,
			'quantity' => 0,
			'customer_id' => '',
			'order_id' => '',
		) );

		$args['event_id'] = is_numeric( $args['event_id'] ) && $args['event_id'] > 0 ? (int) $args['event_id'] : ( is_object( $args['event_id'] ) && isset( $args['event_id']->ID ) ? $args['event_id']->ID : false );
		$args['ticket_type_id'] = is_numeric( $args['ticket_type_id'] ) && $args['ticket_type_id'] > 0
				? $args['ticket_type_id']
				: ( is_object( $args['ticket_type_id'] ) && isset( $args['ticket_type_id']->id ) ? $args['ticket_type_id']->id : false );
		$args['quantity'] = max( 0, $args['quantity'] );
		$args['customer_id'] = empty( $args['customer_id'] ) ? $this->current_user() : $args['customer_id'];

		// if we are being asked to remove the reservations, then call our removal func
		if ( $args['quantity'] <= 0 )
			return $this->remove( $args );

		// figure out the limit of the number of tickets a user can get for this event
		$hard_limit = apply_filters( 'qsot-event-ticket-purchase-limit', 0, $args['event_id'] );

		$lock_args = $args;
		// user that limit and the requested quantity to find out the actual quantity the user can select
		$lock_args['quantity'] = $hard_limit <= 0 ? $args['quantity'] : max( 0, min( $hard_limit, $args['quantity'] ) );

		// obtain the lock
		$lock = $this->_obtain_lock( $lock_args );

		// if there was a problem obtaining the lock, then bail
		if ( is_wp_error( $lock ) || ! is_object( $lock ) )
			return apply_filters( 'qsot-gaea-zoner-reserve-results', is_wp_error( $lock ) ? $lock : false, $args );

		// store the qty we are using for the lock, for later comparison/use
		$lock_for = $lock->quantity;

		// if the settings is set that forces users to stick with their original selection, then account for that now
		if ( 'yes' == apply_filters( 'qsot-get-option-value', 'no', 'qsot-locked-reservations' ) ) {
			// normalize the search params
			$find_args = $lock_args;
			unset( $find_args['quantity'] );
			$find_args['before'] = $lock->since . '.' . $lock->mille;
			$find_args['state'] = $this->stati['r'][0];

			// find all the records this user has for this event on this cart so far.
			$records = $this->find( $find_args );

			// if there are any previous records, then remove the lock and bail
			if ( count( $records ) ) {
				$this->_remove_lock( $lock );
				return apply_filters(
					'qsot-gaea-zoner-reserve-results',
					new WP_Error( 9, __( 'You are not allowed to modify your reservations, except to delete them, after you have chosen them initially.', 'opentickets-community-edition' ) ),
					$args
				);
			}
		}

		// determine the capacity for the event
		$capacity = apply_filters( 'qsot-get-event-capacity', 0, $args['event_id'] );
		//$ea_id = get_post_meta( $args['event_id'], '_event_area_id', true );
		//$capacity = $ea_id > 0 ? get_post_meta( $ea_id, '_capacity', true ) : 0;

		// tally all records for this event before this lock.
		$total_before_lock = $this->find( array(
			'event_id' => $args['event_id'],
			'state' => '*',
			'fields' => 'total',
			'before' => $lock->since . '.' . $lock->mille,
		) ); // remove $lock_for, because this query includes the lock itself now
		$my_total_before_lock = $this->find( array(
			'event_id' => $args['event_id'],
			'state' => '*',
			'order_id' => array_unique( array( 0, isset( WC()->session->order_awaiting_payment ) ? absint( WC()->session->order_awaiting_payment ) : 0 ) ),
			'fields' => 'total',
			'before' => $lock->since . '.' . $lock->mille,
			'ticket_type_id' => $args['ticket_type_id'],
			'customer_id' => $args['customer_id'],
		) );

		// figure out the total available for the event, at the point of the lock. if there is no capacity, then default to the amount in the lock
		$remainder = $capacity > 0 ? $capacity - $total_before_lock + $my_total_before_lock : $lock_for;

		// if the total is greater than or equal to the max capacity for this event, then we do not have enough tickets to issue, so bail
		if ( $capacity > 0 && $remainder <= 0 ) {
			$this->_remove_lock( $lock );
			return apply_filters( 'qsot-gaea-zoner-reserve-results', new WP_Error( 5, __( 'There are no tickets available to reserve.', 'opentickets-community-edition' ) ), $args );
		}

		// figure out the final value for the quantity. this will be checked below and adjusted accordingly
		$final_qty = max( 0, min( $remainder, $lock_for ) );

		// check if this amount can be added to the cart. could run into purchase limit
		$can_add_to_cart = apply_filters( 'qsot-can-add-tickets-to-cart', true, null, $lock_args );

		// handle the response from our check
		// if there was an error, cleanup and bail
		if ( is_wp_error( $can_add_to_cart ) ) {
			$this->_remove_lock( $lock );
			return apply_filters( 'qsot-gaea-zoner-reserve-results', $can_add_to_cart, $args );
		// if there was a fail but no error, then cleanup and bail
		} else if ( ! $can_add_to_cart ) {
			$this->_remove_lock( $lock );
			return apply_filters( 'qsot-gaea-zoner-reserve-results', new WP_Error( 6, __( 'Could not reserve those tickets.', 'opentickets-community-edition' ) ), $args );
		// if there was a change in the quantity to use, then adjust the quantity accordingly
		} else if ( is_numeric( $can_add_to_cart ) && $can_add_to_cart < $lock_for ) {
			$final_qty = $can_add_to_cart;
		}

		// store the final quantity we used
		$args['final_qty'] = $final_qty;

		global $wpdb;

		// at this point the user has obtained a valid lock, and can now actaully have the tickets. proceed with the reservation process
		// first, remove any previous rows that this user had for this event/ticket_type combo. this will eliminate the 'double counting' of this person's reservations moving forward
		$wpdb->delete(
			$wpdb->qsot_event_zone_to_order,
			array(
				'session_customer_id' => $args['customer_id'],
				'event_id' => $args['event_id'],
				'ticket_type_id' => $args['ticket_type_id'],
				'state' => $this->stati['r'][0], // reserved
				'order_id' => $args['order_id'],
			)
		);
		// needed because now all tickets on orders are in confirmed status
		$wpdb->delete(
			$wpdb->qsot_event_zone_to_order,
			array(
				'session_customer_id' => $args['customer_id'],
				'event_id' => $args['event_id'],
				'ticket_type_id' => $args['ticket_type_id'],
				'state' => $this->stati['c'][0], // confirmed
				'order_id' => $args['order_id'],
			)
		);

		// now update the lock record with our new reservation info, transforming it into the new reservation row for this user
		$wpdb->update(
			$wpdb->qsot_event_zone_to_order,
			array(
				'session_customer_id' => $args['customer_id'],
				'event_id' => $args['event_id'],
				'ticket_type_id' => $args['ticket_type_id'],
				'state' => $this->stati['r'][0],
				'order_id' => $args['order_id'],
				'quantity' => $final_qty,
			),
			array(
				'session_customer_id' => $lock->session_customer_id
			)
		);

		return apply_filters( 'qsot-gaea-zoner-reserve-results', $final_qty, $args );
	}

	// remove a reservation based on specified criteria
	public function remove( $success, $args ) {
		// normalize input data
		$args = wp_parse_args( $args, array(
			'event_id' => false,
			'ticket_type_id' => '',
			'quantity' => '',
			'customer_id' => '',
			'order_id' => '',
			'order_item_id' => '',
			'state' => '',
		) );

		$args['event_id'] = is_numeric( $args['event_id'] ) && $args['event_id'] > 0 ? (int) $args['event_id'] : ( is_object( $args['event_id'] ) && isset( $args['event_id']->ID ) ? $args['event_id']->ID : false );
		$args['ticket_type_id'] = is_numeric( $args['ticket_type_id'] ) && $args['ticket_type_id'] > 0
				? $args['ticket_type_id']
				: ( is_object( $args['ticket_type_id'] ) && isset( $args['ticket_type_id']->id ) ? $args['ticket_type_id']->id : false );

		// if there is not at least the basic information and some level of specificity, then bail
		if ( $args['event_id'] <= 0 || ( empty( $args['customer_id'] ) && empty( $args['order_id'] ) && empty( $args['order_item_id'] ) ) )
			return apply_filters( 'qsot-gaea-zoner-remove-results', new WP_Error( 'missing_data', __( 'Missing some or all required data.', 'opentickets-community-edition' ) ), $args );

		// find all matching records
		$records = $this->find( $args );

		global $wpdb;
		// delete all matching records
		foreach ( $records as $record )
			$wpdb->delete( $wpdb->qsot_event_zone_to_order, (array)$record );

		return apply_filters( 'qsot-gaea-zoner-remove-results', true, $args );
	}

	// mark some reserved tickets as confirmed
	public function confirm( $success, $args ) {
		// normalize input data
		$args = wp_parse_args( $args, array(
			'event_id' => false,
			'ticket_type_id' => 0,
			'quantity' => 0,
			'customer_id' => '',
			'order_id' => '',
		) );

		$args['event_id'] = is_numeric( $args['event_id'] ) && $args['event_id'] > 0 ? (int) $args['event_id'] : ( is_object( $args['event_id'] ) && isset( $args['event_id']->ID ) ? $args['event_id']->ID : false );
		$args['ticket_type_id'] = is_numeric( $args['ticket_type_id'] ) && $args['ticket_type_id'] > 0
				? $args['ticket_type_id']
				: ( is_object( $args['ticket_type_id'] ) && isset( $args['ticket_type_id']->id ) ? $args['ticket_type_id']->id : false );
		$args['quantity'] = max( 0, $args['quantity'] );
		$args['customer_id'] = empty( $args['customer_id'] ) ? $this->current_user() : $args['customer_id'];

		// find the matching reserved row
		$find_args = $args;
		$find_args['state'] = $this->stati['r'][0];
		$row = $this->find( $find_args );
		$row = current( $row );

		// if there is no matching row, then bail
		if ( empty( $row ) || ! is_object( $row ) )
			return apply_filters( 'qsot-gaea-zoner-confirm-results', new WP_Error( 'missing_data', __( 'We could not find those reservations. Could not confirm them.', 'opentickets-community-edition' ) ), $args );

		global $wpdb;
		// otherwise, update the row to be confirmed
		$wpdb->update( $wpdb->qsot_event_zone_to_order, array( 'state' => $this->stati['c'][0] ), (array)$row );

		return apply_filters( 'qsot-gaea-zoner-confirm-results', true, $args );
	}

	// mark some confirmed tickets as occupied
	public function occupy( $success, $args ) {
		// normalize input data
		$args = wp_parse_args( $args, array(
			'event_id' => false,
			'ticket_type_id' => 0,
			'quantity' => 1,
			'customer_id' => '',
			'order_id' => '',
			'order_item_id' => '',
		) );

		$args['event_id'] = is_numeric( $args['event_id'] ) && $args['event_id'] > 0 ? (int) $args['event_id'] : ( is_object( $args['event_id'] ) && isset( $args['event_id']->ID ) ? $args['event_id']->ID : false );
		$args['ticket_type_id'] = is_numeric( $args['ticket_type_id'] ) && $args['ticket_type_id'] > 0
				? $args['ticket_type_id']
				: ( is_object( $args['ticket_type_id'] ) && isset( $args['ticket_type_id']->id ) ? $args['ticket_type_id']->id : false );
		$args['quantity'] = max( 0, intval( $args['quantity'] ) );

		// find the matching confirmed row
		$find_args = $args;
		unset( $find_args['quantity'] );
		$find_args['state'] = $this->stati['c'][0];
		$confirmed = $this->find( $find_args );
		$confirmed = current( $confirmed );

		// if there is no matching row, then bail
		if ( empty( $confirmed ) || ! is_object( $confirmed ) )
			return apply_filters( 'qsot-gaea-zoner-occupy-results', new WP_Error( 'missing_data', __( 'We could not find those reservations. Could not check them in.', 'opentickets-community-edition' ), $args ), $args );

		// find matching occupied rows
		$find_args['state'] = $this->stati['o'][0];
		$occupied = $this->find( $find_args );
		$occupied = is_array( $occupied ) && count( $occupied ) ? current( $occupied ) : null;

		global $wpdb;
		// if there is no occupied row, then create an empty one for this record
		if ( null === $occupied ) {
			$occupied = clone $confirmed;
			$occupied->state = $this->stati['o'][0];
			$occupied->quantity = 0;
			$wpdb->insert( $wpdb->qsot_event_zone_to_order, (array) $occupied );
		}

		// update both rows with their new quantities. if the confirm row should have quantity 0, then remove it instead
		$wpdb->update( $wpdb->qsot_event_zone_to_order, array( 'quantity' => $occupied->quantity + $args['quantity'] ), (array)$occupied );
		$new_confirmed = $confirmed->quantity - $args['quantity'];
		if ( $new_confirmed <= 0 )
			$wpdb->delete( $wpdb->qsot_event_zone_to_order, (array)$confirmed );
		else
			$wpdb->update( $wpdb->qsot_event_zone_to_order, array( 'quantity' => $new_confirmed ), (array)$confirmed );

		return apply_filters( 'qsot-gaea-zoner-occupy-results', true, $args );
	}

	// update reservation records based on supplied criteria
	public function update( $result, $args, $set ) {
		// normalize input data
		$args = wp_parse_args( $args, array(
			'event_id' => false,
			'ticket_type_id' => 0,
			'quantity' => '',
			'customer_id' => '',
			'order_id' => '',
			'order_item_id' => '',
			'state' => '',
			'where__extra' => '',
		) );

		$args['event_id'] = is_numeric( $args['event_id'] ) && $args['event_id'] > 0 ? (int) $args['event_id'] : ( is_object( $args['event_id'] ) && isset( $args['event_id']->ID ) ? $args['event_id']->ID : false );
		$args['ticket_type_id'] = is_numeric( $args['ticket_type_id'] ) && $args['ticket_type_id'] > 0
				? $args['ticket_type_id']
				: ( is_object( $args['ticket_type_id'] ) && isset( $args['ticket_type_id']->id ) ? $args['ticket_type_id']->id : false );
		$args['quantity'] = $args['quantity'] ? max( 0, $args['quantity'] ) : $args['quantity'];
		$args['customer_id'] = empty( $args['customer_id'] ) ? '' : $args['customer_id'];

		// find the matching confirmed row
		$find_args = $args;
		$find_args['state'] = '' === $args['state'] ? $this->stati['r'][0] : $args['state'];
		$row = $this->find( $find_args );
		$row = current( $row );

		// if there is no matching row, then bail
		if ( empty( $row ) || ! is_object( $row ) )
			return apply_filters( 'qsot-gaea-zoner-update-results', new WP_Error( 'missing_data', __( 'We could not find those reservations. No update made.', 'opentickets-community-edition' ), array( $args, $find_args ) ), $args );

		// normalize the supplied set data
		$data = array();
		foreach ( array( 'quantity', 'state', 'event_id', 'order_id', 'order_item_id', 'ticket_type_id', 'session_customer_id', 'since' ) as $key )
			if ( isset( $set[ $key ] ) )
				$data[ $key ] = $set[ $key ];

		// if there is no data to set, then bail
		if ( empty( $data ) )
			return apply_filters( 'qsot-gaea-zoner-update-results', new WP_Error( 'missing_data', __( 'There was nothing to update.', 'opentickets-community-edition' ) ), $args );

		// if the quantity is changing then add some additional logic to verify the quantity is valid
		if ( isset( $data['quantity'] ) ) {
			// figure out the limit of the number of tickets a user can get for this event
			$hard_limit = apply_filters( 'qsot-event-ticket-purchase-limit', 0, $args['event_id'] );

			// correct the data's quantity to be the maximum they can purchase, if they are requesting more
			if ( $hard_limit > 0 )
				$data['quantity'] = min( $hard_limit, $data['quantity'] );

			// check if this amount can be added to the cart. could run into purchase limit
			$can_add_to_cart = apply_filters( 'qsot-can-add-tickets-to-cart', true, null, $find_args );

			// handle the response from our check
			// if there was an error, cleanup and bail
			if ( is_wp_error( $can_add_to_cart ) ) {
				return apply_filters( 'qsot-gaea-zoner-reserve-results', $can_add_to_cart, $args );
			// if there was a fail but no error, then cleanup and bail
			} else if ( ! $can_add_to_cart ) {
				return apply_filters( 'qsot-gaea-zoner-reserve-results', new WP_Error( 6, __( 'Could not reserve those tickets.', 'opentickets-community-edition' ) ), $args );
			// if there was a change in the quantity to use, then adjust the quantity accordingly
			} else if ( is_numeric( $can_add_to_cart ) && $can_add_to_cart < $data['quantity'] ) {
				$data['quantity'] = $can_add_to_cart;
			}

			// determine the capacity for the event
			$capacity = apply_filters( 'qsot-get-event-capacity', 0, $args['event_id'] );
			//$ea_id = get_post_meta( $args['event_id'], '_event_area_id', true );
			//$capacity = $ea_id > 0 ? get_post_meta( $ea_id, '_capacity', true ) : 0;

			// tally all records for this event before this record
			$total_before_record = $this->find( array(
				'event_id' => $args['event_id'],
				'state' => '*',
				'fields' => 'total',
				'before' => $row->since . '.' . $row->mille,
			) ); 

			// figure out the the absolute maximum number that the user can reserve, based solely on the previous purchases prior to this record
			$data['quantity'] = min( $capacity > 0 ? $capacity - $total_before_record : $data['quantity'], $data['quantity'] );

			// tally all records for this event that this user owns
			$total_before_record = $this->find( array(
				'event_id' => $args['event_id'],
				'customer_id' => $args['customer_id'],
				'order_id' => $args['order_id'],
				'state' => '*',
				'fields' => 'total',
			) );

			// figure out the the absolute maximum number that the user can reserve, based on previous absolute maximum calcs, and factoring in their own previous reservations for this event in this transation
			// this is needed because GAMP allows multiple different types of tickets to be purchased by the same user on the same transaction
			$data['quantity'] = min( $capacity > 0 ? $capacity - $total_before_record + $row->quantity : $data['quantity'], $data['quantity'] );
		}

		global $wpdb;
		// update the row with the supplied data
		$q = 'update ' . $wpdb->qsot_event_zone_to_order . ' set ';
		$comma = '';
		foreach ( $data as $k => $v ) {
			$q .= $comma . $k . $wpdb->prepare( ' = %s', $v );
			$comma = ',';
		}
		$q .= ' where 1=1';
		foreach ( $row as $k => $v ) {
			if ( is_array( $v ) ) {
				$q .= ' and ' . $k . ' in  (\'' . implode( "','", array_map( 'esc_sql', $v ) ) . '\')';
			} else {
				$q .= ' and ' . $k . $wpdb->prepare( ' = %s', $v );
			}
		}
		$wpdb->query( $q );

		return apply_filters( 'qsot-gaea-zoner-update-results', true, $args );
	}

	// find out how many tickets are available for a given event
	public function get_availability( $event, $event_area ) {
		// maintain an internal cache for this function, which definitely blows out after this request is over
		static $cache = array();

		// if the saught value is cached, then use it
		if ( isset( $cache[ $event->ID ] ) )
			return $cache[ $event->ID ];

		// otherwise, calculate and store it
		// start by grabbing the capacity
		//$capacity = intval( isset( $event_area->meta['_capacity'] ) ? $event_area->meta['_capacity'] : 0 );
		$event->event_area = $event_area;
		$capacity = apply_filters( 'qsot-get-event-capacity', 0, $event );

		// if there is no capacity, then there is an infinite number of tickets left, which we will cap at 1000000 at a time
		if ( $capacity <= 0 )
			return 1000000;

		// otherwise, lookup how manu have been taken so far
		$taken = $this->find( array( 'fields' => 'total', 'event_id' => $event->ID ) );

		return $capacity - $taken;
	}

	// setup the options for allowing timers to be set
	protected function _setup_options() {
		// the the plugin settings object
		$options = QSOT_Options::instance();

		// setup the default values, based on the default timers already established
		$options->def( 'qsot-reserved-state-timer', $this->stati['r'][4] );

		// update db option if it was reset to a blank value
		if ( '' === get_option( 'qsot-reserved-state-timer', '' ) )
			update_option( 'qsot-reserved-state-timer', $this->stati['r'][4] );

		// add the setting section for these timers
		$options->add( array(
			'order' => 500, 
			'type' => 'title',
			'title' => __( 'State Timers', 'opentickets-community-edition' ),
			'id' => 'heading-state-timers',
			'page' => 'general',
			'section' => 'reservations',
		) ); 

		// Reserved timer
		$options->add( array(
			'order' => 505, 
			'id' => 'qsot-reserved-state-timer',
			'default' => $options->{'qsot-reserved-state-timer'},
			'type' => 'text',
			'title' => __( '"Reserved" timer (in seconds)', 'opentickets-community-edition' ),
			'desc' => __( 'The maximum length of time between the moment the user selects a ticket, until they decide to pay for the ticket. After this time expires, if the user has not promised to pay, the tickets will be removed from their cart, and released back into the ticket pool.', 'opentickets-community-edition' ),
			'page' => 'general',
			'section' => 'reservations',
		) ); 

		// End state timers
		$options->add( array(
			'order' => 599, 
			'type' => 'sectionend',
			'id' => 'heading-state-timers',
			'page' => 'general',
			'section' => 'reservations',
		) ); 
	}
}
