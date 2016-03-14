<?php if ( __FILE__ == $_SERVER['SCRIPT_FILENAME'] ) die( header( 'Location: /') );

// a generic query class for finding matching reservations in the ticket lookup table
class QSOT_Zoner_Query {
	// container for the singleton instance
	protected static $instance = null;

	// get the singleton instance
	public static function instance() {
		// if the instance already exists, use it
		if ( isset( self::$instance ) && self::$instance instanceof QSOT_Zoner_Query )
			return self::$instance;

		// otherwise, start a new instance
		return self::$instance = new QSOT_Zoner_Query();
	}

	// constructor. handles instance setup, and multi instance prevention
	public function __construct() {
		// if there is already an instance of this object, then bail now
		if ( isset( self::$instance ) && self::$instance instanceof QSOT_Zoner_Query )
			throw new Exception( sprintf( __( 'There can only be one instance of the %s object at a time.', 'opentickets-community-edition' ), __CLASS__ ), 12000 );

		// otherwise, set this as the known instance
		self::$instance = $this;

		// and call the intialization function
		$this->initialize();
	}

	// destructor. handles instance destruction
	public function __destruct() {
		$this->deinitialize();
	}


	// initialize the object. maybe add actions and filters
	public function initialize() {
	}

	// deinitialize the object. remove actions and filter
	public function deinitialize() {
	}

	// break a time value into the timestamp and the mille seconds
	protected function _break_time( $time ) {
		$output = array( 'time' => $time, 'mille' => 0 );
		// split the timestamp into the two parts, timestamp and mille
		$time = explode( '.', $time );

		// if the mille is set, use it
		if ( count( $time ) > 1 )
			$output['mille'] = absint( array_pop( $time ) );

		// set the timestamp to just the mysql timestamp
		$output['time'] = current( $time );

		// normalize that time stamp into a mysql format
		if ( is_numeric( $output['time'] ) )
			$output['time'] = date( 'Y-m-d H:i:s', $output['time'] );

		// validate the timestamp, and scratch it if not valid
		if ( ! preg_match( '#\d{4}-\d{2}-\d{2}( \d{2}:\d{2}:\d{2})?#', $output['time'] ) || strtotime( $output['time'] ) <= 0 )
			$output['time'] = '';

		return $output;
	}

	// find some rows, based on some search criteria
	public function find( $args ) {
		global $wpdb;

		// normalize the args
		$args = apply_filters( 'qsot-zoner-query-args', wp_parse_args( $args, array(
			'event_id' => '',
			'ticket_type_id' => '',
			'quantity' => '',
			'order_id' => '',
			'order_item_id' => '',
			'customer_id' => '',
			'state' => '',
			'where__extra' => '',
			'before' => '',
			'after' => '',
			'orderby' => 'since asc',
			'limit' => 0,
			'offset' => 0,
			'fields' => 'all',
		) ), $args );

		$fields = $join = $where = $groupby = $having = $orderby = array();
		$limit = '';
		$fields[] = 'ezo.*';
		// build the query based on the supplied args
		// if the any of the numberic keys are supplied, then use them
		foreach ( array( 'event_id', 'ticket_type_id', 'order_id', 'order_item_id', 'quantity' ) as $key ) {
			if ( '' !== $args[ $key ] ) {
				if ( is_array( $args[ $key ] ) ) {
					$ids = array_map( 'absint', $args[ $key ] );
					if ( ! empty( $ids ) )
						$where[] = 'and ' . $key . ' in (' . implode( ',', $ids ) . ')';
				} else if ( is_numeric( $args[ $key ] ) ) {
					$where[] = $wpdb->prepare( 'and ' . $key . ' = %d', $args[ $key ] );
				}
			}
		}

		// if any of the textual keys are provided, use them
		foreach ( array( 'customer_id', 'state' ) as $key ) {
			$table_key = $key;
			if ( 'customer_id' == $key )
				$table_key = 'session_customer_id';
			if ( '' !== $args[ $key ] ) {
				if ( is_array( $args[ $key ] ) ) {
					$strings = array_filter( array_map( 'trim', array_map( 'esc_sql', $args[ $key ] ) ) );
					if ( ! empty( $strings ) )
						$where[] = 'and ' . $table_key . ' in (\'' . implode( "','", $strings ) . '\')';
				} else if ( '*' == $args[ $key ] ) {
				} else {
					$where[] = $wpdb->prepare( 'and ' . $table_key . ' = %s', $args[ $key ] );
				}
			}
		}

		// handle before and after, in relation to the 'since' field
		if ( '' !== $args['before'] ) {
			$op = '<';
			$args['before'] = $this->_break_time( $args['before'] );
			if ( isset( $args['before']['time'], $args['before']['mille'] ) && $args['before']['time'] ) {
				$where[] = $wpdb->prepare(
					'and ( since ' . $op . ' %s or ( since = %s and mille ' . $op . ' %d ) )',
					$args['before']['time'],
					$args['before']['time'],
					$args['before']['mille']
				);
			}
		}
		if ( '' !== $args['after'] ) {
			$op = '>';
			$args['after'] = $this->_break_time( $args['after'] );
			if ( isset( $args['after']['time'], $args['after']['mille'] ) && $args['after']['time'] ) {
				$where[] = $wpdb->prepare(
					'and ( since ' . $op . ' %s or ( since = %s and mille ' . $op . ' %d ) )',
					$args['after']['time'],
					$args['after']['time'],
					$args['after']['mille']
				);
			}
		}

		// if there was an orderby specified, then use it
		if ( '' !== $args['orderby'] ) {
			if ( is_array( $args['orderby'] ) )
				$orderby = $args['orderby'];
			else
				$orderby[] = $args['orderby'];
		}

		// if a limit was specified, add it
		if ( $args['limit'] > 0 ) {
			if ( $args['offset'] > 0 ) {
				$limit = sprintf( ' limit %d offset %d', $args['limit'], $args['offset'] );
			} else {
				$limit = sprintf( ' limit %d', $args['limit'] );
			}
		}

		// add our extra wheres to the mix, if they exist
		if ( is_array( $args['where__extra'] ) )
			$where = array_merge( $where, $args['where__extra'] );

		// adjust fields and groupby based on 'fields' requested
		switch ( $args['fields'] ) {
			case 'total':
				$fields = array( 'sum(ezo.quantity) total' );
			break;

			case 'total-by-ticket-type':
				$fields = array( 'sum(ezo.quantity) quantity', 'ezo.ticket_type_id' );
				$groupby = array( 'ezo.ticket_type_id' );
			break;

			case 'total-by-state':
				$fields = array( 'sum(ezo.quantity) quantity', 'ezo.state' );
				$groupby = array( 'ezo.state' );
			break;
		}

		// let externals modify the query parts
		$pieces = array( 'fields', 'join', 'where', 'groupby', 'having', 'orderby', 'limit' );
		$parts = apply_filters( 'qsot-zoner-query-find-query-parts', compact( $pieces ), $args );
		foreach ( $pieces as $piece )
			if ( isset( $parts[ $piece ] ) )
				$$piece = $parts[ $piece ];

		// make all the query parts into strings
		$fields = implode( ', ', $fields );
		$join = implode( ' ', $join );
		$where = implode( ' ', $where );
		$groupby = $groupby ? 'group by ' . implode( ', ', $groupby ) : '';
		$having = implode( ' ', $having );
		$orderby = $orderby ? 'order by ' . implode( ', ', $orderby ) : '';
		
		// construct the query
		$query = "select {$fields} from {$wpdb->qsot_event_zone_to_order} ezo {$join} where 1=1 {$where} {$groupby} {$having} {$orderby} {$limit}";

		// allow modification of the query
		$query = apply_filters( 'qsot-zoner-query-find-query', $query, $args );

		// get the results
		switch ( $args['fields'] ) {
			case 'total':
				$result = $wpdb->get_var( $query );
			break;

			default:
				$result = null;
				// allow external modification, based on fields arg
				if ( has_action( 'qsot-zoner-query-get-results-' . $args['fields'] ) ) {
					$parts = compact( array( 'fields', 'join', 'where', 'groupby', 'having', 'orderby', 'limit' ) );
					$result = apply_filters( 'qsot-zoner-query-get-results-' . $args['fields'], $query, $parts, $args );
				}

				// if there was no external modification, then just do the norm
				$result = null === $result ? $wpdb->get_results( $query ) : $result;
			break;
		}

		// allow external return handling
		if ( has_action( 'qsot-zoner-query-return-' . $args['fields'] ) )
			return apply_filters( 'qsot-zoner-query-find-results', apply_filters( 'qsot-zoner-query-return-' . $args['fields'], $result, $args ), $args );

		// if we are being asked for just the total tickets, then tally that up now, and return it
		if ( 'total' == $args['fields'] ) {
			return apply_filters( 'qsot-zoner-query-find-results', (int)$result, $args );
		}

		// if we are being asked for a total broken down by ticket_type, then aggregate that result, and return it now
		if ( 'total-by-ticket-type' == $args['fields'] ) {
			$total = array();
			foreach ( $result as $row ) {
				if ( ! isset( $total[ $row->ticket_type_id ] ) )
					$total[ $row->ticket_type_id ] = $row->quantity;
				else
					$total[ $row->ticket_type_id ] += $row->quantity;
			}
			return apply_filters( 'qsot-zoner-query-find-results', $total, $args );
		}

		// if we are being asked for a total broken down by state, then aggregate that result, and return it now
		if ( 'total-by-state' == $args['fields'] ) {
			$total = array();
			foreach ( $result as $row ) {
				if ( ! isset( $total[ $row->state ] ) )
					$total[ $row->state ] = $row->quantity;
				else
					$total[ $row->state ] += $row->quantity;
			}
			return apply_filters( 'qsot-zoner-query-find-results', $total, $args );
		}

		// otherwise, assume they want all fields
		return apply_filters( 'qsot-zoner-query-find-results', $result, $args );
	}
}

if ( defined( 'ABSPATH' ) && function_exists( 'add_action' ) )
	QSOT_Zoner_Query::instance();
