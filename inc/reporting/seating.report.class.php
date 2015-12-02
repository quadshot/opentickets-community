<?php if ( __FILE__ == $_SERVER['SCRIPT_FILENAME'] ) die( header( 'Location: /') );

if ( ! class_exists( 'QSOT_New_Seating_Report' ) ):

// new, faster, more efficient seating report
class QSOT_New_Seating_Report extends QSOT_Admin_Report {
	protected $limit = 200;
	protected $offset = 0;
	protected $event_id = 0;
	protected $event = null;
	protected $options = null;

	// initialization specific to this report
	public function init() {
		// setup the report namings
		$this->group_name = $this->name = __( 'Seating', 'opentickets-community-edition' );
		$this->group_slug = $this->slug = 'seating';

		// setup a map of seat states to descriptive words
		$class = apply_filters( 'qsot-settings-class-name', '' );
		$this->options = call_user_func( array( $class, 'instance' ) );
		$this->state_map = array();
		if ( is_object( $this->options ) ) {
			$this->state_map = apply_filters( 'qsot-' . $this->slug . '-report-state-map', array(
				$this->options->{'z.states.r'} => __( 'Not Paid', 'opentickets-community-edition' ),
				$this->options->{'z.states.c'} => __( 'Paid', 'opentickets-community-edition' ),
				$this->options->{'z.states.o'} => __( 'Checked In', 'opentickets-community-edition' ),
			) );
		}
	}

	// individual reports should define their own set of columns to display in html
	public function html_report_columns() {
		return apply_filters( 'qsot-' . $this->slug . '-report-html-columns', array(
			'purchaser' => array( 'title' => __( 'Purchaser', 'opentickets-community-edition' ) ),
			'order_id' => array( 'title' => __( 'Order #', 'opentickets-community-edition' ) ),
			'ticket_type' => array( 'title' => __( 'Ticket Type', 'opentickets-community-edition' ) ),
			'quantity' => array( 'title' => __( 'Quantity', 'opentickets-community-edition' ) ),
			'email' => array( 'title' => __( 'Email', 'opentickets-community-edition' ) ),
			'phone' => array( 'title' => __( 'Phone', 'opentickets-community-edition' ) ),
			'address' => array( 'title' => __( 'Address', 'opentickets-community-edition' ) ),
			'note' => array( 'title' => __( 'Note', 'opentickets-community-edition' ) ),
			'state' => array( 'title' => __( 'Status', 'opentickets-community-edition' ) ),
		) );
	}

	// individual reports should define their own set of columns to add to the csv
	public function csv_report_columns() {
		return apply_filters( 'qsot-' . $this->slug . '-report-csv-columns', array(
			'purchaser' => __( 'Purchaser', 'opentickets-community-edition' ),
			'order_id' => __( 'Order #', 'opentickets-community-edition' ),
			'ticket_type' => __( 'Ticket Type', 'opentickets-community-edition' ),
			'quantity' => __( 'Quantity', 'opentickets-community-edition' ),
			'email' => __( 'Email', 'opentickets-community-edition' ),
			'phone' => __( 'Phone', 'opentickets-community-edition' ),
			'address' => __( 'Address', 'opentickets-community-edition' ),
			'note' => __( 'Note', 'opentickets-community-edition' ),
			'state' => __( 'Status', 'opentickets-community-edition' ),
			'event' => __( 'Event', 'opentickets-community-edition' ),
			'ticket_link' => __( 'Ticket Url', 'opentickets-community-edition' ),
		) );
	}

	// when starting to run the report, make sure our position counters are reset and that we know what event we are running this thing for
	protected function _starting() {
		$this->offset = 0;
		$this->event_id = max( 0, intval( $_REQUEST['event_id'] ) );
		$this->event = $this->event_id ? get_post( $this->event_id ) : (object)array( 'post_title' => __( '(unknown event)', 'opentickets-community-edition' ) );;

		// if this is the printer friendly version, display the report title
		if ( $this->is_printer_friendly() ) {
			?><h2><?php echo sprintf( __( 'Seating Report: %s', 'opentickets-community-edition' ), apply_filters( 'the_title', $this->event->post_title, $this->event->ID ) ) ?></h2><?php
		}

		// add messages for deprecated filters
		add_action( 'qsot-load-seating-report-assets', array( __CLASS__, 'deprectated_filter' ), -1 );
		add_action( 'qsot-seating-report-get-ticket-data', array( __CLASS__, 'deprectated_filter' ), -1 );
		add_action( 'qsotc-seating-report-compile-rows-occupied', array( __CLASS__, 'deprectated_filter' ), -1 );
		add_action( 'qsot-seating-report-compile-rows-lines', array( __CLASS__, 'deprectated_filter' ), -1 );
		add_action( 'qsotc-seating-report-compile-rows-available', array( __CLASS__, 'deprectated_filter' ), -1 );
		add_action( 'qsot-seating-report-compile-rows-available', array( __CLASS__, 'deprectated_filter' ), -1 );
		add_action( 'qsot-seating-report-fields', array( __CLASS__, 'deprectated_filter' ), -1 );
		add_action( 'qsotc-seating-report-csv-row', array( __CLASS__, 'deprectated_filter' ), -1 );
	}

	// send warnings about deprecated filters
	public function deprectated_filter( $val ) {
		$replacement = null;
		// determine if there is a one to one replacement
		switch ( current_filter() ) {
			case 'qsotc-seating-report-csv-row': $replacement = 'filter:' . 'qsot-' . $this->slug . '-report-csv-row'; break;
			case 'qsot-seating-report-fields': $replacement = 'filter:' . 'qsot-' . $this->slug . '-report-csv-columns OR qsot-' . $this->slug . '-report-html-columns'; break;
			case 'qsot-seating-report-compile-rows-lines':
			case 'qsotc-seating-report-compile-rows-occupied': $replacement = 'filter:' . 'qsot-' . $this->slug . '-report-data-row'; break;
			case 'qsot-seating-report-compile-rows-available':
			case 'qsotc-seating-report-compile-rows-available': $replacement = 'filter:' . 'qsot-' . $this->slug . '-report-before-html-footer'; break;
		}

		// pop the error
		_deprecated_function( 'filter:' . current_filter(), '1.13.0', null );

		// pass through
		return $val;
	}

	// handle the ajax requests for this report
	protected function _process_ajax() {
		// if the parent_id changed, then just pop a new form
		if ( isset( $_REQUEST['reload-form'] ) || ! isset( $_REQUEST['parent_event_id'], $_REQUEST['last_parent_id'] ) || empty( $_REQUEST['parent_event_id'] ) || $_REQUEST['parent_event_id'] != $_REQUEST['last_parent_id'] ) {
			$this->_form();
			exit;
		}

		// otherwise, pop the results table
		$this->_results();
		exit;
	}

	// augment the printerfriendly url
	public function printer_friendly_url() {
		// get the base printer friendly url from the parent class
		$url = QSOT_Admin_Report::printer_friendly_url();

		// add our special params
		$url = add_query_arg( array(
			'parent_event_id' => $_REQUEST['parent_event_id'],
			'last_parent_id' => $_REQUEST['last_parent_id'],
			'event_id' => $_REQUEST['event_id']
		), $url );

		return $url;
	}

	// control the form for this report
	public function form() {
		// determine whether we need the second part of the form or not
		$extended_form = QSOT_Admin_Report::_verify_run_report();

		// check if the parent event_id was was submitted, becuase it is requried to get a list of child events
		$parent_event_id = max( 0, intval( isset( $_REQUEST['parent_event_id'] ) ? $_REQUEST['parent_event_id'] : 0 ) );

		$parents = $children = $parent_data = $selected_parent = $child_data = array();
		// get a list of the parent events
		$parents = get_posts( array(
			'post_type' => 'qsot-event',
			'post_status' => array( 'publish', 'private', 'hidden' ),
			'posts_per_page' => -1,
			'post_parent' => 0,
			'orderby' => 'title',
			'order' => 'asc',
			'fields' => 'ids',
		) );

		// construct a list of parent events, which contains their ids, titles, and start years
		foreach ( $parents as $parent_id ) {
			// get the year when the vent starts
			$year = date( 'Y', strtotime( get_post_meta( $parent_id, '_start', true ) ) );

			// construct this parent record
			$temp = array(
				'id' => $parent_id,
				'text' => get_the_title( $parent_id ) . ' (' . $year . ')',
				'year' => $year,
			);
			$parent_data[] = $temp;

			// if this parent is selected one, then mark it as such
			if ( $parent_id == $parent_event_id )
				$selected_parent = $temp;
		}

		// if we need a list of the child events for the supplied 
		if ( $parent_event_id && $extended_form ) {
			$children = get_posts( array(
				'post_type' => 'qsot-event',
				'post_status' => array( 'publish', 'private', 'hidden' ),
				'posts_per_page' => -1,
				'post_parent' => $parent_event_id,
				'orderby' => 'title',
				'order' => 'asc',
			) );

			// construct a list of child events, which contains their ids and titles
			foreach ( $children as $child ) {
				// construct this child record
				$temp = array(
					'id' => $child->ID,
					'text' => apply_filters( 'the_title', $child->post_title, $child->ID )
				);
				$child_data[] = $temp;
			}
		}

		$this_year = intval( date( 'Y' ) );
		$submitted_year = isset( $_REQUEST['year'] ) ? intval( $_REQUEST['year'] ) : $this_year;
		// draw the form
		?>
			<div class="main-form">
				<label for="year"><?php _e( 'Year:', 'opentickets-community-edition' ) ?></label>
				<select name="year" id="year" class="filter-list" data-filter-what="#parent_event_id">
					<option value="all"><?php _e( '[All Years]', 'opentickets-community-edition' ) ?></option>
					<?php for ( $i = $this_year - 10; $i < $this_year + 10; $i++ ): ?>
						<option value="<?php echo $i ?>" <?php selected( $i, $submitted_year ) ?>><?php echo $i ?></option>
					<?php endfor; ?>
				</select>

				<label for="parent_event_id"><?php _e( 'Event:', 'opentickets-community-edition' ) ?></label>
				<input type="hidden" class="use-select2" style="width:100%; max-width:450px; display:inline-block !important;" name="parent_event_id" id="parent_event_id" data-minchar="0"
						<?php if ( ! empty( $selected_parent ) ): ?>data-init-value="<?php echo esc_attr( @json_encode( $selected_parent ) ) ?>" <?php endif; ?>
						data-init-placeholder="<?php echo esc_attr( __( 'Select an Event', 'opentickets-community-edition' ) ) ?>" data-filter-by="#year" data-array="<?php echo esc_attr( @json_encode( $parent_data ) ) ?>" />
				<input type="hidden" name="last_parent_id" value="<?php echo esc_attr( $parent_event_id ) ?>" />

				<input type="<?php echo $extended_form ? 'button' : 'submit' ?>" class="button pop-loading-bar refresh-form" data-target="#report-form" data-scope="form"
						value="<?php echo esc_attr( __( 'Lookup Showings', 'opentickets-community-edition' ) ) ?>" />
			</div>

			<div class="extended-form">
				<?php if ( $extended_form ): ?>
					<label for="event_id"><?php _e( 'Showing:', 'opentickets-community-edition' ) ?></label>
					<input type="hidden" class="use-select2" style="width:100%; max-width:450px; display:inline-block !important;" name="event_id" id="event_id"
							data-init-placeholder="<?php echo esc_attr( __( 'Select an Event', 'opentickets-community-edition' ) ) ?>" data-minchar="0" data-array="<?php echo esc_attr( @json_encode( $child_data ) ) ?>" />

					<input type="submit" class="button-primary" value="<?php echo esc_attr( __( 'Show Report', 'opentickets-community-edition' ) ) ?>" />
				<?php endif; ?>
			</div>
		<?php
	}

	// augment the run report verification check, so that it requires the event_id and that the last_parent_id equals the submitted parent_event_id
	protected function _verify_run_report( $only_orig=false ) {
		// first check if it passes standard validation
		if ( ! parent::_verify_run_report() )
			return false;

		// if we passed the above and are being asked for only the original results, then succeed now
		if ( $only_orig )
			return true;

		// check that our event_id is present
		if ( ! isset( $_REQUEST['event_id'] ) || intval( $_REQUEST['event_id'] ) <= 0 )
			return false;

		// finally verify that the parent event was not changed
		if ( ! isset( $_REQUEST['parent_event_id'], $_REQUEST['last_parent_id'] ) || empty( $_REQUEST['parent_event_id'] ) || $_REQUEST['parent_event_id'] != $_REQUEST['last_parent_id'] )
			return false;

		return true;
	}

	// the report should define a function to get a partial list of rows to process for this report. for instance, we don't want to have one group of 1,000,000 rows, run all at once, because
	// the memory implications on that are huge. instead we would need to run it in discreet groups of 1,000 or 10,000 rows at a time, depending on the processing involved
	public function more_rows(){
		global $wpdb;

		// valid states
		$in = "'" . implode( "','", array_filter( array_map( 'trim', array_keys( $this->state_map ) ) ) ) . "'";

		// grab the next group of matches
		$rows = $wpdb->get_results( $wpdb->prepare(
			'select * from ' . $wpdb->qsot_event_zone_to_order . ' where event_id = %d and state in (' . $in . ') order by since limit %d offset %d',
			$this->event_id,
			$this->limit,
			$this->offset
		) );

		// increment the offset for the next loop
		$this->offset += $this->limit;

		return $rows;
	}

	// the report should define a function to process a group of results, which it contructed in the more_rows() method
	public function aggregate_row_data( array $group ) {
		$order_ids = $order_item_ids = array();
		// create a list of order_ids and order_item_ids, based on the rows in this group
		foreach ( $group as $row ) {
			$order_ids[] = $row->order_id;
			$order_item_ids[] = $row->order_item_id;
		}

		// normalize the lists
		$order_ids = array_filter( array_map( 'absint', $order_ids ) );
		$order_item_ids = array_filter( array_map( 'absint', $order_item_ids ) );

		// get all the order meta, for all orders, and then index it by order_id
		$order_meta = $this->_get_order_meta( $order_ids );

		// get all the ticket codes, based on the order_item_ids, indexed by the order_item_ids
		$ticket_codes = $this->_get_ticket_codes( $order_item_ids );

		// get all order item meta, for all order items, and index it by order_item_id
		//$order_item_meta = $this->_get_order_item_meta( $order_item_ids );

		// get all the seating report comments by order_id
		$report_comments = $this->_get_comments_by_order( $order_ids );

		$final = array();
		// finally, put it all together
		foreach ( $group as $row ) {
			$final[] = apply_filters( 'qsot-' . $this->slug . '-report-data-row', array(
				'purchaser' => $this->_order_meta( $order_meta, 'name', $row ),
				'order_id' => $row->order_id ? $row->order_id : '-',
				'ticket_type' => $this->_ticket_type( $row->ticket_type_id ),
				'quantity' => $row->quantity ? $row->quantity : '-',
				'email' => $this->_order_meta( $order_meta, '_billing_email', $row ),
				'phone' => $this->_order_meta( $order_meta, '_billing_phone', $row ),
				'address' => $this->_order_meta( $order_meta, 'address', $row ),
				'note' => isset( $report_comments[ $row->order_id ] ) ? $report_comments[ $row->order_id ] : '',
				'state' => isset( $this->state_map[ $row->state ] ) ? $this->state_map[ $row->state ] : '-',
				'event' => apply_filters( 'the_title', $this->event->post_title, $this->event->ID ),
				'ticket_link' => isset( $ticket_codes[ $row->order_item_id ] ) ? apply_filters( 'qsot-get-ticket-link-from-code', $ticket_codes[ $row->order_item_id ], $ticket_codes[ $row->order_item_id ] ) : '',
				'_raw' => $row,
			), $row, $this->event, isset( $order_meta[ $row->order_id ] ) ? $order_meta[ $row->order_id ] : array() );
		}

		return $final;
	}

	// calculate the availability, based on the total number of tickets sold, subtracted from the total available
	protected function _available() {
		global $wpdb;

		// valid states
		$in = "'" . implode( "','", array_filter( array_map( 'trim', array_keys( $this->state_map ) ) ) ) . "'";

		// find the total sold
		$total = $wpdb->get_var( $wpdb->prepare( 'select * from ' . $wpdb->qsot_event_zone_to_order . ' where event_id = %d and state in (' . $in . ')', $this->event_id ) );

		// get the event capacity
		$capacity = intval( get_post_meta( get_post_meta( $this->event->ID, $this->options->{'meta_key.event_area'}, true ), $this->options->{'event_area.mk.cap'}, true ) );

		return max( 0, $capacity - $total );
	}

	// add the number of available tickets to the end of the table
	protected function _before_html_footer( $all_html_rows ) {
		// get the number of available tickets
		$available = $this->_available();

		// get the list of columns
		$columns = $this->html_report_columns();

		// print an empty row, making sure to label and quantify it as an availbility count
		echo '<tr>';

		foreach ( $columns as $col => $label ) {
			echo '<td>';

			switch ( $col ) {
				case 'purchaser': echo __( 'AVAILABLE', 'opentickets-community-edition' ); break;
				case 'quantity': echo $available; break;
				default: echo '-'; break;
			}

			echo '</td>';
		}

		echo '</tr>';

		do_action( 'qsot-' . $this->slug . '-report-before-html-footer', $all_html_rows, $this );
	}

	// get a very specific piece of order meta from the list of order meta, based on the list, a specific grouping name, and the order id
	protected function _order_meta( $all_meta, $key, $row, $default='-' ) {
		// find the order_id from the row
		$order_id = $row->order_id;

		// get the meta for just this one order
		$meta = isset( $all_meta[ $order_id ] ) ? $all_meta[ $order_id ] : false;

		// either piece together specific groupings of meta, or return the exact meta value
		switch ( $key ) {
			default: return isset( $meta[ $key ] ) ? $meta[ $key ] : ''; break;

			// a display name for the purchaser
			case 'name':
				$names = array();
				// attempt to use the billing name
				if ( isset( $meta['_billing_first_name'] ) )
					$names[] = $meta['_billing_first_name'];
				if ( isset( $meta['_billing_last_name'] ) )
					$names[] = $meta['_billing_last_name'];

				// fall back on the cart identifier
				return ! empty( $names ) ? implode( ' ', $names ) : '';
			break;

			// the address for the purchaser
			case 'address':
				$addresses = array();
				if ( isset( $meta['_billing_address_1'] ) )
					$addresses[] = $meta['_billing_address_1'];
				if ( isset( $meta['_billing_address_2'] ) )
					$addresses[] = $meta['_billing_address_2'];

				return implode( ' ', $addresses );
			break;
		}
	}

	// get the specific product title for the ticket type of this line item
	protected function _ticket_type( $product_id, $default='-' ) {
		// cache a list of products. this should never get too big on one page load, so it is fine to be internal cache
		static $products = array();

		// if the product was already loaded, just use it
		if ( isset( $products[ $product_id ] ) )
			return $products[ $product_id ];

		// otherwise load the product please
		$temp = wc_get_product( $product_id );

		// if the product does not exist, then store and return the default value
		if ( is_wp_error( $temp ) )
			return $products[ $product_id ] = $default;

		// otherwise return and store the product title
		return $products[ $product_id ] = $temp->get_title();
	}

	// get all the seating report comments, organized by order_id
	protected function _get_comments_by_order( $order_ids ) {
		// if there are no order ids, then bail now
		if ( empty( $order_ids ) )
			return array();

		// get a list of all seating report note comment ids
		$comment_ids = get_comments( array(
			'post__in' => $order_ids,
			'approve' => 'approve',
			'type' => 'order_note',
			'meta_query' => array(
				array( 'key' => 'is_seating_report_note', 'value' => '1', 'compare' => '=' ),
			),
			'orderby' => 'comment_date_gmt',
			'order' => 'desc',
			'number' => null,
			'fields' => 'ids'
		) );

		// if there are none, then bail now
		if ( empty( $comment_ids ) )
			return array();

		global $wpdb;
		// otherwise, get a list of all matched comments, and organize them by order_id
		$raw_comments = $wpdb->get_results( 'select comment_post_id order_id, comment_content from ' . $wpdb->comments . ' where comment_id in(' . implode( ',', $comment_ids ) . ' order by comment_date asc' );

		$final = array();
		// index the final list
		while ( $row = array_shift( $raw_comments ) )
			$final[ $row->order_id ] = $row->comment_content;

		return $final;
	}

	// fetch all order meta, indexed by order_id
	protected function _get_order_meta( $order_ids ) {
		// if there are no order_ids, then bail now
		if ( empty( $order_ids ) )
			return array();

		global $wpdb;
		// get all the post meta for all orders
		$all_meta = $wpdb->get_results( 'select * from ' . $wpdb->postmeta . ' where post_id in (' . implode( ',', $order_ids ) . ') order by meta_id desc' );

		$final = array();
		// organize all results by order_id => meta_key => meta_value
		foreach ( $all_meta as $row ) {
			// make sure we have a row for this order_id already
			$final[ $row->post_id ] = isset( $final[ $row->post_id ] ) ? $final[ $row->post_id ] : array();

			// update this meta key with it's value
			$final[ $row->post_id ][ $row->meta_key ] = $row->meta_value;
		}

		return $final;
	}

	// get all the ticket codes, indexed by the order_item_id
	protected function _get_ticket_codes( $order_item_ids ) {
		global $wpdb;
		// get the raw list
		$results = $wpdb->get_results( 'select * from ' . $wpdb->qsot_ticket_codes . ' where order_item_id in(' . implode( ',', $order_item_ids ) . ')' );

		$final = array();
		// construct the final list organized by the order_item_id
		while ( $row = array_pop( $results ) )
			$final[ $row->order_item_id ] = $row->ticket_code;

		return $final;
	}

	// fetch all order_item meta, indexed by order_item_id
	protected function _get_order_item_meta( $order_item_ids ) {
		// if there are no order_item_ids, then bail now
		if ( empty( $order_item_ids ) )
			return array();

		global $wpdb;
		// get all the post meta for all orders
		$all_meta = $wpdb->get_results( 'select * from ' . $wpdb->prefix . 'woocommerce_order_itemmeta where order_item_id in (' . implode( ',', $order_item_ids ) . ') order by meta_id desc' );

		$final = array();
		// organize all results by order_item_id => meta_key => meta_value
		foreach ( $all_meta as $row ) {
			// make sure we have a row for this order_item_id already
			$final[ $row->order_item_id ] = isset( $final[ $row->order_item_id ] ) ? $final[ $row->order_item_id ] : array();

			// update this meta key with it's value
			$final[ $row->order_item_id ][ $row->meta_key ] = $row->meta_value;
		}

		return $final;
	}
}

endif;

return new QSOT_New_Seating_Report();
