<?php (__FILE__ == $_SERVER['SCRIPT_FILENAME']) ? die(header('Location: /')) : null;

if (!class_exists('qsot_zoner')):

// handles reservations, creation, update, and deletion
class qsot_zoner {
	protected static $o = null;
	protected static $debug = false;
	protected static $log_file = null;
	protected static $def_owns = array();

	public static function pre_init() {
		$settings_class_name = apply_filters('qsot-settings-class-name', '');
		if (empty($settings_class_name) || !class_exists($settings_class_name)) return;
		self::$o = call_user_func_array(array($settings_class_name, 'instance'), array());
		
		self::$o->z = apply_filters('qsot-zoner-settings', array(
			'states' => array(
				'o' => 'occupied', // confirmed seat that has been actually occupied, via checkin feature. lasts indefinitely
				'c' => 'confirmed', // user confirmed seat, and paid for it (if applicable). lasts indefinitely
				'r' => 'reserved', // user has selected a price for a seat, but has not yet completed the purchase. lasts 1 hour
			),
			'state_timeouts' => array( // in seconds
				'o' => 0, // never
				'c' => 0, // never
				'r' => 3600, // 1 hour
			),
		));

		foreach ( self::$o->{'z.states'} as $k => $v )
			self::$def_owns[ $v ] = 0;

		// setup the db tables for the zone reserver
		self::setup_table_names();
		add_action( 'switch_blog', array( __CLASS__, 'setup_table_names' ), PHP_INT_MAX, 2 );
		add_filter('qsot-upgrader-table-descriptions', array(__CLASS__, 'setup_tables'), 10);

		// state timeouts
		add_filter('qsot-temporary-zone-states', array(__CLASS__, 'temporary_zone_states'), 10, 1);
		add_filter('qsot-permanent-zone-states', array(__CLASS__, 'permanent_zone_states'), 10, 1);

		// reservation functions
		add_filter( 'qsot-event-reserved-or-confirmed-since', array( __CLASS__, 'get_event_reserved_or_confirmed_since' ), 1000, 5 );
		add_filter( 'qsot-event-reserved-since-current-user', array( __CLASS__, 'get_event_reserved_since_current_user' ), 1000, 5 );
		add_filter('qsot-zoner-item-data-keys', array(__CLASS__, 'item_data_keys_to_maintain'), 10, 2);
		add_action('qsot-zoner-clear-locks', array(__CLASS__, 'clear_locks'), 10, 2);
		add_filter('qsot-zoner-reserve-current-user', array(__CLASS__, 'reserve_current_user'), 10, 4);
		add_filter('qsot-zoner-reserve', array(__CLASS__, 'reserve'), 10, 6);
		add_filter('qsot-zoner-owns-current-user', array(__CLASS__, 'owns_current_user'), 10, 4);
		add_filter('qsot-zoner-owns', array(__CLASS__, 'owns'), 10, 7);
		add_filter('qsot-zoner-ownerships-current-user', array(__CLASS__, 'ownerships_current_user'), 10, 5);
		add_filter('qsot-zoner-ownerships', array(__CLASS__, 'ownerships'), 10, 7);
		add_filter('qsot-zoner-update-reservation', array(__CLASS__, 'update_reservation'), 10, 3);
		add_filter('qsot-zoner-current-user', array(__CLASS__, 'current_user'), 10, 3);
		add_filter('qsot-zoner-order-event-qty-state', array(__CLASS__, 'get_state_from_order_event_quantity'), 10, 4);
		add_filter( 'qsot-can-add-tickets-to-cart', array( __CLASS__, 'maybe_enforce_event_ticket_purchase_limit' ), 10, 3 );

		// determine if the item could be a ticket
		add_filter('qsot-item-is-ticket', array(__CLASS__, 'item_is_ticket'), 100, 2);

		// checkin code
		add_filter('qsot-is-already-occupied', array(__CLASS__, 'is_occupied'), 1000, 4);
		add_filter('qsot-occupy-sold', array(__CLASS__, 'occupy_sold'), 1000, 5);

		// stats
		add_filter('qsot-count-tickets', array(__CLASS__, 'count_tickets'), 1000, 2);

		// add owns data to ticket object for ticket and checkin templates
		add_filter( 'qsot-compile-ticket-info', array( __CLASS__, 'add_owns_to_ticket' ), 1000000, 3 );
	}

	// when loading the ticket info, we need to add the owns information to the ticket object. mostly used in checkin process
	public static function add_owns_to_ticket( $ticket, $oiid, $order_id ) {
		// validate that we have all the data we need to do this step
		if ( ! is_object( $ticket ) || ! is_numeric( $oiid ) )
			return $ticket;

		// fetch the order item based on the order_item_id and order_id
		$order = wc_get_order( $order_id );
		$ois = $order->get_items();
		$oi = isset( $ois[ $oiid ] ) ? $ois[ $oiid ] : null;
		if ( empty( $oi ) )
			return $ticket;

		// add the owns info
		$ticket->owns = apply_filters( 'qsot-zoner-owns', array(), $ticket->event, $ticket->order_item['product_id'], '*', false, $order_id, $oiid );

		// overlay the result on top the defaults
		$ticket->owns = wp_parse_args( $ticket->owns, self::$def_owns );
		return $ticket;
	}

	public static function get_state_from_order_event_quantity($state, $order_id, $event_id, $qty) {
		global $wpdb;

		$q = $wpdb->prepare('select `state` from '.$wpdb->qsot_event_zone_to_order.' where order_id = %d and event_id = %d and quantity = %d limit 1', $order_id, $event_id, $qty);
		$res = $wpdb->get_var($q);

		return $res ? $res : $state;
	}

	public static function count_tickets($current, $args='') {
		$args = wp_parse_args($args, array(
			'state' => '*',
			'event_id' => '',
		));

		global $wpdb;

		$q = 'select state, sum(quantity) tot from '.$wpdb->qsot_event_zone_to_order.' where 1=1';
		if ( !empty( $args['event_id'] ) ) {
			if ( is_array( $args['event_id'] ) ) {
				$ids = array_filter( array_map( 'absint', $args['event_id'] ) );
				if ( ! empty( $ids ) )
					$q .= ' and event_id in (' . implode(',', $ids) . ')';
			} else if ( (int)$args['event_id'] > 0 ) {
				$q .= $wpdb->prepare( ' and event_id = %d', $args['event_id'] );
			}
		}
		$q .= ' group by state';

		$rows = $wpdb->get_results($q);
		$out = array();

		if (empty($rows)) return (!empty($args['state']) && $args['state'] != '*') ? 0 : $out;

		foreach ($rows as $row) $out[$row->state] = $row->tot;

		if (!empty($args['state']) && $args['state'] != '*') return isset($out[$args['state']]) ? $out[$args['state']] : 0;

		return $out;
	}

	// list of 'states' (db table field) that are considered temporary, and expire
	public static function temporary_zone_states($list) {
		static $ours = false;
		if ($ours === false) {
			$ours = array();
			foreach (self::$o->{'z.state_timeouts'} as $k => $time)
				if ($time > 0)
					$ours[] = self::$o->{'z.states.'.$k};
		}
		return is_array($list) ? array_unique(array_merge($list, $ours)) : $ours;
	}

	// list of 'states' (db table field) that are considered permanent, and never expire
	public static function permanent_zone_states($list) {
		static $ours = false;
		if ($ours === false) {
			$ours = array();
			foreach (self::$o->{'z.state_timeouts'} as $k => $time)
				if ($time <= 0)
					$ours[] = self::$o->{'z.states.'.$k};
		}
		return is_array($list) ? array_unique(array_merge($list, $ours)) : $ours;
	}

	// determine if all of a given ticket have been marked as occupied or not
	public static function is_occupied($current, $order_id, $event_id, $oiid) {
		$order = new WC_Order($order_id);
		$event = apply_filters('qsot-get-event', false, $event_id);
		if (!is_object($order) || !is_object($event) || !isset($order->id)) return false;

		$order_items = $order->get_items();
		$oi = isset($order_items[$oiid]) ? $order_items[$oiid] : false;
		if (!is_array($oi) || !isset($oi['event_id'])) return false;

		// if there are confirms still, then the user can still checkin, because they are not all occupied yet
		$confirms = apply_filters('qsot-zoner-owns', array(), $event, $oi['product_id'], self::$o->{'z.states.c'}, false, $order_id, $oiid);
		
		return !$confirms;
	}

	// if there are 'confirmed' seats that are not checked in yet (occupied) that match the given criteria, then check them in
	public static function occupy_sold($current, $order_id, $event_id, $oiid, $qty) {
		$order = new WC_Order($order_id);
		$event = apply_filters('qsot-get-event', false, $event_id);
		if (!is_object($order) || !is_object($event) || !isset($order->id)) return false;

		$order_items = $order->get_items();
		$oi = isset($order_items[$oiid]) ? $order_items[$oiid] : false;
		if (!is_array($oi) || !isset($oi['event_id'])) return false;

		// get a list of all states that have entries for this ticket purchase
		$all = apply_filters('qsot-zoner-owns', array(), $event, $oi['product_id'], '*', false, $order_id, $oiid);

		// if there are none in the 'confirm' category, then either we have a non-ticket (unlikely) or they are all checked in already. either way, fail.
		if (!isset($all[self::$o->{'z.states.c'}]) || (int)$all[self::$o->{'z.states.c'}] < $qty) return false;
		$confirms = apply_filters('qsot-zoner-ownerships', array(), $event, $oi['product_id'], self::$o->{'z.states.c'}, false, $order_id, $oiid);
		if (empty($confirms)) return false;

		// if there a none already checked in, then insert a row to be updated
		if (!isset($all[self::$o->{'z.states.o'}])) {
			global $wpdb;
			$wpdb->insert(
				$wpdb->qsot_event_zone_to_order,
				array(
					'event_id' => $event_id,
					'ticket_type_id' => $oi['product_id'],
					'quantity' => 0,
					'state' => self::$o->{'z.states.o'},
					'session_customer_id' => $confirms[0]->session_customer_id,
					'order_id' => $order_id,
					'order_item_id' => $oiid,
				)
			);
			$all[self::$o->{'z.states.o'}] = 0;
		}

		$res_dec = apply_filters(
			'qsot-zoner-update-reservation',
			false,
			// removed qty param because $order_item_id param is specific enough to target a single row, which is what we need here
			array('event_id' => $event_id, 'state' => self::$o->{'z.states.c'}, 'order_id' => $order_id, 'order_item_id' => $oiid),
			array('qty' => '::DEC::')
		);

		$res_inc = apply_filters(
			'qsot-zoner-update-reservation',
			false,
			// removed qty param because $order_item_id param is specific enough to target a single row, which is what we need here
			array('event_id' => $event_id, 'state' => self::$o->{'z.states.o'}, 'order_id' => $order_id, 'order_item_id' => $oiid),
			array('qty' => '::INC::')
		);

		return $res_inc && $res_dec;
	}

	// is the order item marked as a ticket would be marked?
	public static function item_is_ticket($is, $item) {
		if (!isset($item['event_id']) || empty($item['event_id'])) return false;
		return $is;
	}

	// clear out any temporary locks that have expired
	public static function clear_locks($event_id=0, $customer_id=false) {
		global $wpdb;
		// require either required basic information type
		if (empty($event_id) && empty($customer_id)) return;

		// get a list of expireable states, and format it for quick, reliable use later
		$temp_states = array_flip(apply_filters('qsot-temporary-zone-states', array()));

		// cycle through each state type
		foreach (self::$o->{'z.states'} as $k => $name) {
			// if it is not a temporary state, pass
			if (!isset($temp_states[$name])) continue;

			// get the timeout of the state
			$timeout = (int)self::$o->{'z.state_timeouts.'.$k};

			// if there is a defined, positive timeout, then we need to remove any temporary locks for this state, that have surpassed that timeout
			if ($timeout > 0) {
				// build a query that will find all locks that have expired, based on the supplied criteria. we fetch the list so that we can
				// notify other sources that these locks are going away (such as other plugins, or upgrades to this plugin)
				$q = $wpdb->prepare('select * from '.$wpdb->qsot_event_zone_to_order.' where state = %s and since < NOW() - INTERVAL %d SECOND', self::$o->{'z.states.'.$k}, $timeout);
				// if the event was supplied, reduce the list to only ones for this event
				if (!empty($event_id)) $q .= $wpdb->prepare(' and event_id = %d', $event_id);
				// if the customer id was supplied then, add that to the sql
				if (!empty($customer_id)) {
					if (is_array($customer_id)) $q .= ' and session_customer_id in(\''.implode('\',\'', array_map('esc_sql', $customer_id)).'\')';
					else $q .= $wpdb->prepare(' and session_customer_id = %s', $customer_id);
				}
				// fetch a list of existing locks.
				$locks = $wpdb->get_results($q);

				// tell everyone that the locks are going away
				do_action('qsot-removing-zone-locks', $locks, self::$o->{'z.states.'.$k}, $event_id, $customer_id);

				// delete the locks we said we would delete in the above action.
				// this is done in this manner, because we need to only delete the ones we told others about.
				// technically, if the above action call takes too long, other locks could have expired by the time we get to delete them.
				// thus we need to explicitly delete ONLY the ones we told everyone we were deleting, so that none are removed without the others being notified.
				$q = 'delete from '.$wpdb->qsot_event_zone_to_order.' where '; // base query
				$wheres = array(); // holder for queries defining each specific row to delete
				// cycle through all the locks we said we would delete
				foreach ($locks as $lock) {
					// aggregate a partial where statement, that specifically identifies this row, using all fields for positive id
					$fields = array();
					foreach ($lock as $k => $v) $fields[] = $wpdb->prepare($k.' = %s', $v);
					if (!empty($fields)) $wheres[] = implode(' and ', $fields);
				}
				// if we have where statements for at least one row to remove
				if (!empty($wheres)) {
					// glue the query together, and run it to delete the rows
					$q .= '('.implode(') or (', $wheres).')';
					$wpdb->query($q);
				}
			}
		}
	}

	// tell woocommerce what item meta to keep when a users moves from page to page, so that we dont lose vital ticket data
	public static function item_data_keys_to_maintain($current) {
		$current = is_array($current) ? $current : array();
		$current[] = 'event_id';
		return $current;
	}

	// current_user is the id we use to lookup tickets in relation to a product in a cart. once we have an order number this pretty much becomes obsolete
	public static function current_user($current, $order_id=false, $data='') {
		$woocommerce = WC();
		$res = false;
		$data = wp_parse_args($data, array('customer_user' => false));
		if ($data['customer_user']) $res = $data['customer_user'];
		if (empty($res) && (int)$order_id > 0) $res = get_post_meta($order_id, '_customer_user', true);
		if (empty($res) && is_object($woocommerce->session)) $res = $woocommerce->session->get_customer_id();
		if (empty($res)) $res = $current;

		return $res;
	}

	// determine the number of reserved or confirmed tickets for a given even, as of a specific time 'qsot-event-reserved-or-confirmed-since'
	public static function get_event_reserved_or_confirmed_since( $current, $event_id, $since=false, $customer_id=false, $ticket_type_id=false ) {
		global $wpdb;
		// normalize the input
		$event_id = absint( $event_id );
		$now = false;
		if ( empty( $since ) ) {
			$parts = array( current_time( 'mysql' ) );
			$now = true;
		} else {
			$parts = explode( '.', $since );
		}
		$since = array_shift( $parts );
		$mille = intval( current( $parts ) );

		// figure out the total number of tickets reserved or confirmed before the given time
		$q = $wpdb->prepare(
			'select sum( quantity ) cnt from ' . $wpdb->qsot_event_zone_to_order . ' where event_id = %s and '
				. $wpdb->prepare( $now ? 'since <= %s ' : '( since < %s or ( since = %s and mille < %s ) )', $since, $since, $mille )
				. 'and ( state = %s or ( state = %s',
			$event_id,
			self::$o->{'z.states.c'},
			self::$o->{'z.states.r'}
		);

		// if the customer_id was supplied, then exclude records for that specific customer that are not yet paid for
		if ( $customer_id ) {
			$q .= $wpdb->prepare( ' and ( session_customer_id != %s', $customer_id );

			if ( $ticket_type_id )
				$q .= $wpdb->prepare( ' or ticket_type_id != %d', $ticket_type_id );

			$q .= ' )';
		}
		$q .= ' ) )';

		// return the total number of sold tickets to that point, making sure to not allow negative numbers
		return max( 0, $wpdb->get_var( $q ) );
	}

	// determine the number of reserved or confirmed tickets for a given even, as of a specific time, but only for the specified user 'qsot-event-reserved-or-confirmed-since-current-user'
	public static function get_event_reserved_since_current_user( $current, $event_id, $since=false, $customer_id=false, $ticket_type_id=false ) {
		global $wpdb;
		// normalize the input
		$event_id = absint( $event_id );
		$now = false;
		if ( empty( $since ) ) {
			$parts = array( current_time( 'mysql' ) );
			$now = true;
		} else {
			$parts = explode( '.', $since );
		}
		$since = array_shift( $parts );
		$mille = intval( current( $parts ) );

		// figure out the total number of tickets reserved or confirmed before the given time
		$q = $wpdb->prepare(
			'select sum( quantity ) cnt from ' . $wpdb->qsot_event_zone_to_order . ' where event_id = %s and '
				. $wpdb->prepare( $now ? 'since <= %s ' : '( since < %s or ( since = %s and mille < %s ) ) ', $since, $since, $mille )
				. 'and state = %s ',
			$event_id,
			self::$o->{'z.states.r'}
		);

		// if the customer_id was supplied, then use that information to limit the result
		if ( $customer_id ) {
			$q .= $wpdb->prepare( ' and session_customer_id = %s', $customer_id );

			if ( $ticket_type_id )
				$q .= $wpdb->prepare( ' and ticket_type_id = %d', $ticket_type_id );
		}

		// return the total number of sold tickets to that point, making sure to not allow negative numbers
		return max( 0, $wpdb->get_var( $q ) );
	}

	// obtain lock on a certain number of seats
	protected static function _obtain_lock( $event, $data=array() ) {
		global $wpdb;
		// normalize the lock data
		$data = apply_filters( 'qsot-lock-data', wp_parse_args( $data, array(
			'event_id' => $event->ID,
			'ticket_type_id' => 0,
			'quantity' => 0,
			'customer_id' => '',
			'order_id' => '',
		) ), $event );

		// create a unique id for this temporary lock, so that we can easily id the lock and remove it after the lock has passed inspection
		$uniq_lock_id = uniqid( 'temp-lock-', true );

		// obtain a temporary lock of the requested quantity. this will be used in a moment to determine if the user has the ability to reserve this number of tickets
		$wpdb->insert(
			$wpdb->qsot_event_zone_to_order,
			array(
				'event_id' => $data['event_id'],
				'ticket_type_id' => $data['ticket_type_id'],
				'state' => self::$o->{'z.states.r'},
				'mille' => QSOT::mille(),
				'quantity' => $data['quantity'],
				'session_customer_id' => $uniq_lock_id,
				'order_id' => $data['order_id'],
			)
		);
		return $wpdb->get_row( $wpdb->prepare( 'select * from ' . $wpdb->qsot_event_zone_to_order . ' where session_customer_id = %s', $uniq_lock_id ) );
	}

	// add a reservation for the current user
	public static function reserve_current_user($success, $event, $ticket_type_id, $count) {
		// idetify the current user
		$customer_id = apply_filters('qsot-zoner-current-user', md5(isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : time()));
		$success = false;
		// add the reservation
		return apply_filters('qsot-zoner-reserve', $success, $event, $ticket_type_id, $count, $customer_id);
	}
	
	// add the reservation, but only if there are enough seats available to do so
	public static function reserve($success, $event, $ticket_type_id, $count, $customer_id, $order_id=0) {
		global $wpdb;

		// first, event is required infromation
		$event = get_post( $event );
		if ( ! is_object( $event ) || ! isset( $event->post_type ) || $event->post_type !== self::$o->core_post_type )
			return false;

		// if for some reason the count is negative (due to external plugin or hacker funny business) then do nothing, becasue it is impossible to have -1 seats
		if ( $count < 0 ) {
			return false;
		// if setting the reservation count to 0, then delete reservations if they already exist
		} else if ( 0 == $count ) {
			$res = $wpdb->delete(
				$wpdb->qsot_event_zone_to_order,
				array(
					'event_id' => $event->ID,
					'ticket_type_id' => $ticket_type_id,
					'state' => self::$o->{'z.states.r'},
					'session_customer_id' => $customer_id,
					'order_id' => $order_id
				)
			);
			$success = true;
		// if count is > 0 then
		} else {
			// the next two lines prevent someone from entering 1000000000 over and over again to lock up the event reservation queue. limit the lock to a maximum of the event limit, if there is one
			// first, get the ticket purchase limit if there is one for this event
			$limit = apply_filters( 'qsot-event-ticket-purchase-limit', 0, $event->ID );

			// figure out how many to make the lock for
			$lock_for = $limit <= 0 ? $count : max( 0, min( $limit, $count ) );

			// obtain a lock for the seats they requested
			$lock_record = self::_obtain_lock( $event, array( 'ticket_type_id' => 0, 'quantity' => $lock_for, 'customer_id' => $customer_id, 'order_id' => $order_id ) );

			// if our settings prevent the user from modifying existing reservations, then prevent it here by not allowing a new number to be set
			if ( 'yes' == apply_filters( 'qsot-get-option-value', 'no', 'qsot-locked-reservations' ) ) {
				// count tickets for this event that the user currently has
				$owned_prior_to_lock = apply_filters( 'qsot-event-reserved-since-current-user', 0, $event->ID, $lock_record->since, $customer_id );

				// if there were reservations prior, then dont allow them to change
				if ( $owned_prior_to_lock > 0 ) {
					// clean up first
					$wpdb->delete( $wpdb->qsot_event_zone_to_order, array( 'session_customer_id' => $lock_record->session_customer_id ) );
					return new WP_Error( 9, __( 'You are not allowed to modify your reservations, except to delete them, after you have chosen them initially.', 'opentickets-community-edition' ) );
				}
			}

			// now count how many total tickets have been reserved for this event, prior to the lock being acquired, which do not belong to the current user
			$total_prior_to_lock = apply_filters( 'qsot-event-reserved-or-confirmed-since', 0, $event->ID, $lock_record->since, $customer_id, $ticket_type_id );

			// now, compare the total before the lock, to the capacity, and see if we have the ability to reserve the tickets or not
			$capacity = isset( $event->meta, $event->meta->capacity ) ? intval( $event->meta->capacity ) : intval( get_post_meta(
				get_post_meta( $event->ID, self::$o->{'meta_key.event_area'}, true ), // the event capacity is actually the 'event AREA capacity'
				self::$o->{'event_area.mk.cap'},
				true
			) );
			$remainder = $capacity - $total_prior_to_lock;

			// if there are not enough tickets available to allow the user to have the amount they need, then fail, and allow them none **** NEEDS WORK
			if ( $remainder <= 0 ) {
				// clean up first
				$wpdb->delete( $wpdb->qsot_event_zone_to_order, array( 'session_customer_id' => $lock_record->session_customer_id ) );
				// error out
				return new WP_Error( 5, __( 'There are no tickets available to reserve.', 'opentickets-community-edition' ) );
			}

			// figure out the maximum number of seats this person is allows to purchase
			$quantity = max( 0, min( $remainder, $count ) );

			// check to see if this user has the ability to actually add this number of tickets to their cart currently. could have a per event ticket limit
			$can_add_to_cart = apply_filters( 'qsot-can-add-tickets-to-cart', true, $event, array(
				'ticket_type_id' => $ticket_type_id,
				'customer_id' => $customer_id,
				'order_id' => $order_id,
				'quantity' => $quantity,
			) );
			// if you just flat out cannot add tickets, or you can only add 0 tickets, then generic error out
			if ( ! $can_add_to_cart ) {
				// clean up first
				$wpdb->delete( $wpdb->qsot_event_zone_to_order, array( 'session_customer_id' => $lock_record->session_customer_id ) );
				// return a generic error
				return new WP_Error( 6, __( 'Could not reserve those tickets.', 'opentickets-community-edition' ) );
			// if there is a actual error reason given for why you cannot add the tickets, then pass that along
			} else if ( is_wp_error( $can_add_to_cart ) ) {
				// clean up first
				$wpdb->delete( $wpdb->qsot_event_zone_to_order, array( 'session_customer_id' => $lock_record->session_customer_id ) );
				// pass the error along
				return $can_add_to_cart;
			// if the number that the user is allowed, is less than the max we calculated above, simply update the amount
			} else if ( is_numeric( $can_add_to_cart ) && $can_add_to_cart < $quantity ) {
				$quantity = $can_add_to_cart;
			}

			// at this point the user has obtained a valid lock, and can now actaully have the tickets. proceed with the reservation process
			// first, remove any previous rows that this user had for this event/ticket_type combo. this will eliminate the 'double counting' of this person's reservations moving forward
			$wpdb->delete(
				$wpdb->qsot_event_zone_to_order,
				array(
					'session_customer_id' => $customer_id,
					'event_id' => $event->ID,
					'ticket_type_id' => $ticket_type_id,
					'state' => self::$o->{'z.states.r'},
					'order_id' => $order_id
				)
			);

			// now update the lock record with our new reservation info, transforming it into the new reservation row for this user
			$wpdb->update(
				$wpdb->qsot_event_zone_to_order,
				array(
					'session_customer_id' => $customer_id,
					'event_id' => $event->ID,
					'ticket_type_id' => $ticket_type_id,
					'state' => self::$o->{'z.states.r'},
					'order_id' => $order_id,
					'quantity' => $quantity,
				),
				array(
					'session_customer_id' => $lock_record->session_customer_id
				)
			);

			$success = true;
		}

		return $success;
	}

	// we may need to enforce a per-event ticket limit. check that here
	public static function maybe_enforce_event_ticket_purchase_limit( $current, $event, $args ) {
		// normalize the args
		$event = get_post( $event );
		$args = wp_parse_args( $args, array(
			'ticket_type_id' => 0,
			'order_id' => 0,
			'customer_id' => '',
			'quantity' => 1,
			'state' => self::$o->{'z.states.r'},
		) );

		// validate the event
		if ( $event->post_type !== self::$o->core_post_type )
			return $curretn;

		// figure out the event ticket limit, if any
		$limit = apply_filters( 'qsot-event-ticket-purchase-limit', 0, $event->ID );

		// if there is no limit, then bail
		if ( $limit <= 0 )
			return $current;

		// determine how many tickets they currently have for this event
		$total_for_event = apply_filters( 'qsot-zoner-owns', 0, $event, $args['ticket_type_id'], self::$o->{'z.states.r'}, $args['customer_id'], $args['order_id'] );
		if ( is_array( $total_for_event ) )
			$total_for_event = array_sum( $total_for_event );

		// if the current total they have is great than or equal to the event limit, then bail with an error stating that they are already at the limit
		if ( $args['quantity'] > $total_for_event && $total_for_event >= $limit )
			return new WP_Error( 10, __( 'You have reached the ticket limit for this event.', 'opentickets-community-edition' ) );
		else if ( $args['quantity'] > $limit )
			return $limit;
		
		// if we get this far, then they are allowed
		return true;
	}

	// get the total reservations for each zone that the current user owns (based on event, ticket type, and state)
	public static function owns_current_user($current, $event, $ticket_type_id, $state) {
		$customer_id = apply_filters('qsot-zoner-current-user', md5(isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : time()));
		$success = false;
		return apply_filters('qsot-zoner-owns', $success, $event, $ticket_type_id, $state, $customer_id);
	}

	// get the total reservations for each zone that a given user owns (based on event, ticket type, state, customer_id or order)
	public static function owns($current, $event, $ticket_type_id=false, $state=false, $customer_id=false, $order_id=false, $order_item_id=false) {
		global $wpdb;

		// event is required information here
		$event = is_numeric($event) && $event > 0 ? apply_filters('qsot-get-event', false, $event) : $event;
		if (!is_object($event)) return 0;
		
		// generate an sql statement that will pull out the reservation list based on the supplied information
		$q = $wpdb->prepare('select sum(quantity) as cnt, state, ticket_type_id from '.$wpdb->qsot_event_zone_to_order.' where event_id = %d', $event->ID);

		// if the ticket type was supplied, then only get the tickets of that type for the event
		if ( ! empty( $ticket_type_id ) && $ticket_type_id != '*' ) {
			if ( is_array( $ticket_type_id ) ) $q .= ' and ticket_type_id in (' . implode( ',', array_map( 'absint', $ticket_type_id ) ) . ')';
			else $q .= $wpdb->prepare( ' and ticket_type_id = %d', $ticket_type_id );
		}

		// if the state was supplied, or it is 'all' states (*) then, add that to the query
		if (!empty($state) && $state != '*') {
			if (is_array($state)) $q .= ' and state in (\''.implode('\',\'', array_map('esc_sql', $state)).'\')';
			else $q .= $wpdb->prepare(' and state = %s', $state);
		}

		$subs = array();
		// if customer_id is supplied, add it to the query
		if (!empty($customer_id)) {
			if (is_array($customer_id)) $subs[] = 'session_customer_id in(\''.implode('\',\'', array_map('esc_sql', $customer_id)).'\')';
			else $subs[] = $wpdb->prepare('session_customer_id = %s', $customer_id);
		}
		// if order_id is supplied, add it to the query
		if (!empty($order_id)) {
			if (is_array($order_id)) $subs[] = 'order_id in(\''.implode('\',\'', array_map('esc_sql', $order_id)).'\')';
			else $subs[] = $wpdb->prepare('order_id = %s', $order_id);
		}
		// if order_item_id is supplied, add it to the query
		if (!empty($order_item_id)) {
			if (is_array($order_item_id)) $subs[] = 'order_item_id in(\''.implode('\',\'', array_map('esc_sql', $order_item_id)).'\')';
			else $subs[] = $wpdb->prepare('order_item_id = %s', $order_item_id);
		}
		
		if (!empty($subs)) $q .= ' and ('.implode(' or ', $subs).') ';

		$q .= ' group by state, ticket_type_id';

		// allow other plugins to add their logic
		$q = apply_filters('qsot-zoner-owns-query', $q, $event, $ticket_type_id, $state, $customer_id);

		// get the tallied results, grouped by state
		$counts = $wpdb->get_results($q);
		$indexed = array();
		// format the results in a useable form
		foreach ($counts as $count) {
			$indexed[$count->state] = isset( $indexed[$count->state] ) ? $indexed[$count->state] : array();
			$indexed[$count->state][$count->ticket_type_id] = $count->cnt;
		}

		if ( ! is_array($ticket_type_id) && ! empty( $ticket_type_id ) && $ticket_type_id != '*' ) {
			foreach ( $indexed as $st => $cnts )
				$indexed[$st] = array_sum( array_values( $cnts ) );

			// if all results were requested, or if there are no results, then just return the whole list
			if (empty($state) || $state == '*' || is_array($state)) return $indexed;

			// otherwise, we need to only return a specific resultset, based on requested state
			return isset($indexed[$state]) ? $indexed[$state] : 0;
		} else {
			// if all results were requested, or if there are no results, then just return the whole list
			if (empty($state) || $state == '*' || is_array($state)) return $indexed;

			// otherwise, we need to only return a specific resultset, based on requested state
			return isset($indexed[$state]) ? $indexed[$state] : array();
		}
	}

	// get the list of actual zones that the current user has reservations for (based on event, ticket type, and state)
	public static function ownerships_current_user($current, $event=0, $ticket_type_id=0, $state=false, $order_id=false) {
		// determine current user
		$customer_id = apply_filters('qsot-zoner-current-user', md5(isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : time()));
		// get the zone list
		return apply_filters('qsot-zoner-ownerships', $current, $event, $ticket_type_id, $state, $customer_id, $order_id);
	}

	// get the list of actual zones that the _given_ user (and/or order_id, and/or order_item_id) has reservations for (based on event, ticket type, and state)
	public static function ownerships($current, $event=0, $ticket_type_id=0, $state=false, $customer_id=false, $order_id=false, $order_item_id=false) {
		global $wpdb;

		$event = is_numeric($event) && $event > 0 ? get_post($event) : $event;

		// build a query to pull the whole list zones this user owns
		$q = 'select * from '.$wpdb->qsot_event_zone_to_order.' where 1=1';
		// if the event was supplied, add it to the query to narrow the results
		if (is_object($event)) {
			$q .= $wpdb->prepare(' and event_id = %d', $event->ID);
		}
		// if the ticket type (product_id) was supplied, add to the query to narrow results
		if ($ticket_type_id > 0) {
			$q .= $wpdb->prepare(' and ticket_type_id = %d', $ticket_type_id);
		}
		// if the state was supplied, add it to the query to narrow....
		if (!empty($state) && $state != '*') {
			if (is_array($state)) $q .= ' and state in (\''.implode('\',\'', array_map('esc_sql', $state)).'\')';
			else $q .= $wpdb->prepare(' and state = %s', $state);
		}
		// if for specific user, add it to query....
		if (!empty($customer_id)) {
			if (is_array($customer_id)) $q .= ' and session_customer_id in(\''.implode('\',\'', array_map('esc_sql', $customer_id)).'\')';
			else $q .= $wpdb->prepare(' and session_customer_id = %s', $customer_id);
		}
		// if for specific order_id, add it to query....
		if (!empty($order_id)) {
			if (is_array($order_id)) $q .= ' and order_id in(\''.implode('\',\'', array_map('esc_sql', $order_id)).'\')';
			else $q .= $wpdb->prepare(' and order_id = %s', $order_id);
		}
		// if for specific order_item_id, add it to query....
		if (!empty($order_item_id)) {
			if (is_array($order_item_id)) $q .= ' and order_item_id in(\''.implode('\',\'', array_map('esc_sql', $order_item_id)).'\')';
			else $q .= $wpdb->prepare(' and order_item_id = %s', $order_item_id);
		}

		// allow external plugins to add their logic here
		$q = apply_filters('qsot-zoner-ownerships-query', $q, $event, $ticket_type_id, $state, $customer_id, $order_id, $order_item_id);

		// fetch the list
		$raw = $wpdb->get_results($q);
		$indexed = array();
		// organize the list
		foreach ($raw as $row) {
			$indexed[$row->state] = isset($indexed[$row->state]) && is_array($indexed[$row->state]) ? $indexed[$row->state] : array();
			$indexed[$row->state][] = $row;
		}

		// we have no results, we requested all results, or we requested multiple state results, then just return the whole resultset
		if (empty($indexed) || $state == '*' || is_array($state)) return $indexed;

		// otherwise only return the specified results
		return isset($indexed[$state]) ? $indexed[$state] : array();
	}

	// update/delete existing reservations. called from UPDATE RESERVATION button on the frontend
	public static function update_reservation( $success, $where, $set ) {
		global $wpdb;
		$is_delete = false;

		// generate the 'where statement' pieces for the sql query to perform the actual update
		$wheres = array();
		// if the order id was given, add it
		if ( isset( $where['order_item_id'] ) ) {
			if ( is_array( $where['order_item_id'] ) )
				$wheres[] = ' and order_item_id in(' . implode( ',', $where['order_item_id'] ) . ')';
			else
				$wheres['order_item_id'] = $wpdb->prepare( ' and order_item_id = %d', $where['order_item_id'] );
		}
		// if the order id was given, add it
		if ( isset( $where['order_id'] ) ) {
			if ( is_array( $where['order_id'] ) )
				$wheres[] = ' and order_id in(' . implode( ',', $where['order_id'] ) . ')';
			else
				$wheres['order_id'] = $wpdb->prepare( ' and order_id = %d', $where['order_id'] );
		}
		// if the customer was specified, add it
		if ( isset( $where['customer_id'] ) ) {
			if ( is_array( $where['customer_id'] ) )
				$wheres[] = ' and session_customer_id in(\'' . implode( '\',\'', $where['customer_id'] ) . '\')';
			else
				$wheres['customer_id'] = $wpdb->prepare( ' and session_customer_id = %s', $where['customer_id'] );
		}
		// if the state was given, add it
		if ( isset( $where['state'] ) && $where['state'] != '*' ) {
			if ( is_array( $where['state'] ) )
				$wheres[] = ' and state in (\'' . implode( '\',\'', $where['state'] ) . '\')';
			else
				$wheres['state'] = $wpdb->prepare( ' and state = %s', $where['state'] );
		}
		// if the event id was given, add it
		if ( isset( $where['event_id'] ) ) {
			if ( is_array( $where['event_id'] ) )
				$wheres[] = ' and event_id in (' . implode( ',', $where['event_id'] ) . ')';
			else
				$wheres['event_id'] = $wpdb->prepare( ' and event_id = %d', $where['event_id'] );
		}
		// if the product_id of the ticket type was given, add it
		if ( isset( $where['ticket_type_id'] ) ) {
			if ( is_array( $where['ticket_type_id'] ) )
				$wheres[] = ' and ticket_type_id in (' . implode( ',', $where['ticket_type_id'] ) . ')';
			else
				$wheres['ticket_type_id'] = $wpdb->prepare( ' and ticket_type_id = %d', $where['ticket_type_id'] );
		}
		// if a quantity was specified, add it
		if ( isset( $where['qty'] ) ) {
			if ( is_array( $where['qty'] ) )
				$wheres[] = ' and qty in (' . implode( ',', $where['qty'] ) . ')';
			else
				$wheres['qty'] = $wpdb->prepare( ' and quantity = %d', $where['qty'] );
		}
		// allow external plugins to modify this with their logic
		$wheres = apply_filters( 'qsot-zoner-update-reservation-wheres', $wheres, $set, $where );

		// generate the 'where statement' pieces for the sql query to perform the removal of existing records, prior to the actual update
		$set_wheres = array();
		// if the order id was given, add it
		if ( isset( $set['order_item_id'] ) ) {
			if ( is_array( $set['order_item_id'] ) )
				$set_wheres[] = ' and order_item_id in(' . implode( ',', $set['order_item_id'] ) . ')';
			else
				$set_wheres['order_item_id'] = $wpdb->prepare( ' and order_item_id = %d', $set['order_item_id'] );
		}
		// if the order id was given, add it
		if ( isset( $set['order_id'] ) ) {
			if ( is_array( $set['order_id'] ) )
				$set_wheres[] = ' and order_id in(' . implode( ',', $set['order_id'] ) . ')';
			else
				$set_wheres['order_id'] = $wpdb->prepare( ' and order_id = %d', $set['order_id'] );
		}
		// if the customer was specified, add it
		if ( isset( $set['customer_id'] ) ) {
			if ( is_array( $set['customer_id'] ) )
				$set_wheres[] = ' and session_customer_id in(\'' . implode( '\',\'', $set['customer_id'] ) . '\')';
			else
				$set_wheres['customer_id'] = $wpdb->prepare( ' and session_customer_id = %s', $set['customer_id'] );
		}
		// if a specific state (or group of states) was requested, add it
		if ( isset( $set['state'] ) && $set['state'] != '*' ) {
			if ( is_array( $set['state'] ) )
				$set_wheres[] = ' and state in (\'' . implode( '\',\'', $set['state'] ) . '\')';
			else
				$set_wheres['state'] = $wpdb->prepare( ' and state = %s', $set['state'] );
		}
		// if the event id was given, add it
		if ( isset( $set['event_id'] ) ) {
			if ( is_array( $set['event_id'] ) )
				$set_wheres[] = ' and event_id in (' . implode( ',', $set['event_id'] ) . ')';
			else
				$set_wheres['event_id'] = $wpdb->prepare( ' and event_id = %d', $set['event_id'] );
		}
		// if the product_id of the ticket type was specified, add it
		if ( isset( $set['ticket_type_id'] ) ) {
			if ( is_array( $set['ticket_type_id'] ) )
				$set_wheres[] = ' and ticket_type_id in (' . implode( ',', $set['ticket_type_id'] ) . ')';
			else
				$set_wheres['ticket_type_id'] = $wpdb->prepare( ' and ticket_type_id = %d', $set['ticket_type_id'] );
		}

		// normalize the where statements for the update and deletion prior to update
		if ( ! empty( $set_wheres ) ) {
			$set_wheres = wp_parse_args( $set_wheres, $wheres );
			// we will be updating the quantity, so we c
			unset( $set_wheres['qty'] );
		}

		// start by getting the current entries that match this wheres list
		$current = $wpdb->get_results( 'select * from ' . $wpdb->qsot_event_zone_to_order . ' where 1=1' . implode( '', array_values( $wheres ) ) );
		// and by tallying up the current total from the current listings
		$total = 0;
		foreach ( $current as $item )
			$total += $item->quantity;

		// start by cleaning up any dupe rows that match, condensing them all to a single row. do the operations in this order so that the user does not lose seats at high traffic
		if ( count( $current ) > 0 ) {
			// update the first row with the total
			$wpdb->query( $wpdb->prepare( 'update ' . $wpdb->qsot_event_zone_to_order . ' set quantity = %d where 1=1' . implode( '', array_values( $wheres ) ) . ' order by since asc limit 1', $total ) );
			// then delete the dupes
			$wpdb->query( $wpdb->prepare( 'delete from ' . $wpdb->qsot_event_zone_to_order . ' where 1=1' . implode( '', array_values( $wheres ) ) . ' order by since desc limit %d', count( $current ) - 1 ) );
		}

		$limit = '';
		// if we are trying to delete the reservations, then start the update query as a delete statement
		if ( isset( $set['_delete'] ) || ( isset( $set['qty'] ) && ! in_array( $set['qty'], array( '::INC::', '::DEC::' ) ) && $set['qty'] <= 0 ) ) {
			$q = 'delete from ' . $wpdb->qsot_event_zone_to_order . ' where 1=1';

			// also update passed information for the action below
			$set['_qty'] = $set['qty'];
			$set['qty'] = 0;
			$is_delete = true;
		// otherwise this is an actual, genuine update to the reservations, so make the query an update query
		} else {
			// if our settings say that the user cannot edit their reservations, then prevent it here
			if ( isset( $set['state'] ) && self::$o->{'z.states.r'} == $set['state'] && 'yes' == apply_filters( 'qsot-get-option-value', 'no', 'qsot-locked-reservations' ) )
				return false;

			$q = 'update ' . $wpdb->qsot_event_zone_to_order . ' set ';

			// create the update sql
			$pairs = array();
			if ( isset( $set['order_item_id'] ) )
				$pairs[] = $wpdb->prepare( ' order_item_id = %d', $set['order_item_id'] );
			if ( isset( $set['order_id'] ) )
				$pairs[] = $wpdb->prepare( ' order_id = %d', $set['order_id'] );
			if ( isset( $set['customer_id'] ) )
				$pairs[] = $wpdb->prepare( ' session_customer_id = %s', $set['customer_id'] );
			if ( isset( $set['state'] ) )
				$pairs[] = $wpdb->prepare( ' state = %s', $set['state'] );
			if ( isset( $set['event_id'] ) )
				$pairs[] = $wpdb->prepare( ' event_id = %d', $set['event_id'] );
			if ( isset( $set['ticket_type_id'] ) )
				$pairs[] = $wpdb->prepare( ' ticket_type_id = %d', $set['ticket_type_id'] );

			// normalize the qty, in the special DEC and INC scenarios (from checkin)
			if ( isset( $set['qty'] ) ) {
				if ( $set['qty'] == '::DEC::' )
					$set['qty'] = $total - 1;
				else if ( $set['qty'] == '::INC::' )
					$set['qty'] = $total + 1;

				// if the request is to update a 'reserved' quantity, then
				if ( isset( $where['state'], $where['event_id'] ) && self::$o->{'z.states.r'} == $where['state'] ) {
					// load the event info
					$event = get_post( $where['event_id'] );

					$order_id = $customer_id = '';
					// obtain a lock for the seats they requested
					$lock_args = array( 'ticket_type_id' => 0, 'quantity' => $set['qty'] );
					if ( isset( $where['customer_id'] ) )
						$customer_id = $lock_args['customer_id'] = $where['customer_id'];
					if ( isset( $where['order_id'] ) && '*' != $where['order_id'] && ! is_array( $where['order_id'] ) )
						$order_id = $lock_args['order_id'] = $order_id;
					$lock_record = self::_obtain_lock( $event, $lock_args );

					// now count how many total tickets have been reserved for this event, prior to the lock being acquired, which do not belong to the current user
					$total_prior_to_lock = apply_filters( 'qsot-event-reserved-or-confirmed-since', 0, $event->ID, $lock_record->since, $customer_id );

					// remove the lock asap as to not interfere with other sessions
					$wpdb->delete( $wpdb->qsot_event_zone_to_order, array( 'session_customer_id' => $lock_record->session_customer_id ) );

					// now, compare the total before the lock, to the capacity, and see if we have the ability to reserve the tickets or not
					$capacity = isset( $event->meta, $event->meta->capacity ) ? intval( $event->meta->capacity ) : intval( get_post_meta(
						get_post_meta( $event->ID, self::$o->{'meta_key.event_area'}, true ), // the event capacity is actually the 'event AREA capacity'
						self::$o->{'event_area.mk.cap'},
						true
					) );
					$remainder = $capacity - $total_prior_to_lock;

					// if there are not enough tickets available to allow the user to have the amount they need, then fail, and allow them none **** NEEDS WORK
					if ( $remainder <= 0 )
						return false;

					// figure out the maximum number of seats this person is allows to purchase
					$max_quantity = max( 0, min( $remainder, $set['qty'] ) );

					// check to see if this user has the ability to actually add this number of tickets to their cart currently. could have a per event ticket limit
					$can_add_to_cart = apply_filters( 'qsot-can-add-tickets-to-cart', true, $event, array(
						'ticket_type_id' => isset( $set['ticket_type_id'] ) ? $set['ticket_type_id'] : 0,
						'customer_id' => $customer_id,
						'order_id' => $order_id,
						'quantity' => $max_quantity,
					) );
					// if you just flat out cannot add tickets, or you can only add 0 tickets, then generic error out
					if ( ! $can_add_to_cart ) {
						// clean up first
						$wpdb->delete( $wpdb->qsot_event_zone_to_order, array( 'session_customer_id' => $lock_record->session_customer_id ) );
						// return a generic error
						return false; //new WP_Error( 6, __( 'Could not reserve those tickets.', 'opentickets-community-edition' ) );
					// if there is a actual error reason given for why you cannot add the tickets, then pass that along
					} else if ( is_wp_error( $can_add_to_cart ) ) {
						// clean up first
						$wpdb->delete( $wpdb->qsot_event_zone_to_order, array( 'session_customer_id' => $lock_record->session_customer_id ) );
						// pass the error along
						return $can_add_to_cart;
					// if the number that the user is allowed, is less than the max we calculated above, simply update the amount
					} else if ( is_numeric( $can_add_to_cart ) && $can_add_to_cart < $max_quantity ) {
						$max_quantity = $can_add_to_cart;
					}

					// update the requested quantity to the max they are allowed to purchase, upto the amount they requested
					$set['qty'] = $max_quantity;
				}

				// update the UPDATE pair with the max we can acquire, upto what they requested
				$pairs[] = $wpdb->prepare( ' quantity = %d', $set['qty'] );
			}

			// allow other plugins to add their own update stuff
			$pairs = apply_filters( 'qsot-zoner-update-reservation-sets', $pairs, $set, $where );

			// glue it all together
			$q .= implode( ',', $pairs ) . ' where 1=1';
			$limit = ' limit 1';
		}

		// if we actually have updates to make, because we actually have data to filter our updated set by (in other words, do not delete all records accidentally)
		if ( is_array( $wheres ) && count( $wheres ) ) { // safegaurd against deleting all records
			// figure out the difference in tickets
			$difference = isset( $set['qty'] ) ? -$total - $set['qty'] : 0;

			// at this point, they have permission to grade their number of seats, so update the record
			$rm_wheres = $wheres;
			$wheres = array_values( $wheres );

			// now actually run the query
			$q .= implode( '', $wheres ) . $limit;
			$res = $wpdb->query( $q );

			// remove any empty rows that may have beenl eft behind by a ::INC:: or ::DEC::
			$rm_wheres['qty'] = ' and quantity = 0';
			$wpdb->query( 'delete from ' . $wpdb->qsot_event_zone_to_order . ' where 1=1' . implode( '', array_values( $rm_wheres ) ) );

			// commit the query. why do i have to do this now, when it was never required before.
			$wpdb->query('commit');
			$success = $res !== false;
	
			// notify other plugins of our success
			if ( $success )
				do_action( 'qsot-zoner-after-update-reservation', $difference, isset( $set['qty'] ) ? $set['qty'] : '', $where, $set );
		}

		return $success;
	}

	protected static function lg() {
		if ( ! self::$debug ) return;
		if ( ! is_resource( self::$log_file ) ) self::$log_file = fopen( QSOT::plugin_dir() . uniqid( 'zoner' ) . '.log', 'a' );
		if ( is_resource( self::$log_file ) ) {
			foreach ( func_get_args() as $arg ) {
				if ( is_scalar( $arg ) ) {
					if ( $arg == '*__f__*' ) {
						$bt = self::bt( true );
						$out = 'BACKTRACE: ' . implode( "\n", $bt );
					} else {
						$out = 'MSG: ' . $arg;
					}
				} else {
					$out = 'DUMP: ' . var_export( $arg, true );
				}
				$out .= "\n";
				fwrite( self::$log_file, $out, strlen( $out ) );
			}
		}
	}

	protected static function bt( $functions_only=false ) {
		$out = array();
		if ( $functions_only ) {
			$bt = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS );
			foreach ( $bt as $call ) {
				$func = ( ( isset( $call['function'] ) ) ? $call['function'] : '<global-scope>' ) . '()';
				if ( isset( $call['class'] ) ) $func = $call['class'] . '::' . $func;
				else if ( isset( $call['object'] ) ) $func = $call['object'] . '[' . ( ( is_object( $call['object'] ) ) ? get_class( $call['object'] ) : 'object' ) . ']->' . $func;
				$file = isset( $call['file'] ) ? $call['file'] : '<no-file>';
				$line = isset( $call['line'] ) ? $call['line'] : '<no-line>';
				$out[] = $func . ' in ' . $file . ' @ ' . $line;
			}
		} else $out = debug_backtrace();

		return $out;
	}

	public static function setup_table_names() {
		global $wpdb;
		$wpdb->qsot_event_zone_to_order = $wpdb->prefix.'qsot_event_zone_to_order';
	}

	public static function setup_tables($tables) {
    global $wpdb;
    $tables[$wpdb->qsot_event_zone_to_order] = array(
      'version' => '1.3.0',
      'fields' => array(
				'event_id' => array('type' => 'bigint(20) unsigned'), // post of type qsot-event
				'order_id' => array('type' => 'bigint(20) unsigned'), // post of type shop_order (woocommerce)
				'quantity' => array('type' => 'smallint(5) unsigned'), // some zones can have more than 1 capacity, so we need a quantity to designate how many were purchased ina given zone
				'state' => array('type' => 'varchar(20)'), // word descriptor for the current state. core states are interest, reserve, confirm, occupied
				'since' => array('type' => 'timestamp', 'default' => 'CONST:|CURRENT_TIMESTAMP|'), // when the last action took place. used for lockout clearing
				'mille' => array( 'type' => 'smallint(4)', 'default' => '0' ), // the mille seconds for 'since'. experimental
				'session_customer_id' => array('type' => 'varchar(150)'), // woo session id for linking a ticket to a user, before the order is actually created (like interest and reserve statuses)
				'ticket_type_id' => array('type' => 'bigint(20) unsigned', 'default' => '0'), // product_id of the woo product that represents the ticket that was purchased/reserved
				'order_item_id' => array('type' => 'bigint(20) unsigned', 'default' => '0'), // order_item_id of the order item that represents this ticket. present after order creation
      ),   
      'keys' => array(
        'KEY evt_id (event_id)',
        'KEY ord_id (order_id)',
        'KEY oiid (order_item_id)',
				'KEY stt (state)',
      ),
			'pre-update' => array(
				'when' => array(
					'exists' => array(
						'alter ignore table ' . $wpdb->qsot_event_zone_to_order . ' drop index `evt_id`',
						'alter ignore table ' . $wpdb->qsot_event_zone_to_order . ' drop index `ord_id`',
						'alter ignore table ' . $wpdb->qsot_event_zone_to_order . ' drop index `oiid`',
						'alter ignore table ' . $wpdb->qsot_event_zone_to_order . ' drop index `stt`',
					),
				),
			),
    );   

    return $tables;
	}
}

if (defined('ABSPATH') && function_exists('add_action')) {
	qsot_zoner::pre_init();
}

endif;
