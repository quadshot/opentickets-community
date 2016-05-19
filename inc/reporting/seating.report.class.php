<?php if ( __FILE__ == $_SERVER['SCRIPT_FILENAME'] ) die( header( 'Location: /') );

if ( ! class_exists( 'QSOT_New_Seating_Report' ) ):

// new, faster, more efficient seating report
class QSOT_New_Seating_Report extends QSOT_Admin_Report {
	protected $limit = 200;
	protected $offset = 0;
	protected $event_id = 0;
	protected $event = null;
	protected $state_map = array();

	// initialization specific to this report
	public function init() {
		// setup the report namings
		$this->group_name = $this->name = __( 'Attendees', 'opentickets-community-edition' );
		$this->group_slug = $this->slug = 'seating';

		// add a note type to order notes, that allows a note to show up on this report in it's own column
		add_filter( 'woocommerce_order_note_types', array( &$this, 'add_order_note_types' ), 10, 2 );
		add_action( 'wp_insert_comment', array( &$this, 'save_new_order_note_types' ), 10, 4 );
		add_filter( 'woocommerce_get_order_note_type', array( &$this, 'get_order_note_type' ), 10, 2 );

		// add the ajax handle for this report
		$aj = QSOT_Ajax::instance();
		$aj->register( $this->slug, array( &$this, 'handle_ajax' ), 'edit_posts', 10, 'qsot-admin-report-ajax' );
	}

	// add the seating chart order note type
	public function add_order_note_types( $list, $order ) {
		$list['seating-report-note'] = __( 'Attendee Report note', 'opentickets-community-edition' );
		return $list;
	}

	// get the order note type for display
	public function get_order_note_type( $type, $note ) {
		if ( get_comment_meta( $note->comment_ID, 'is_seating_report_note', true ) == 1 ) $type = 'attendee report note';
		return $type;
	}

	// when saving the note on the order, make sure to mark our meta if the note type requires it
	public function save_new_order_note_types( $comment_id, $comment ) {
		update_comment_meta( $comment_id, 'is_seating_report_note', ( is_admin() && isset( $_POST['note_type'] ) && 'seating-report-note' == $_POST['note_type'] ) ? 1 : 0 );
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
		), $this->event );
	}

	// individual reports should define their own set of columns to add to the csv
	public function csv_report_columns() {
		// figure out the setting for the method used to create the qr codes
		$option = apply_filters( 'qsot-get-option-value', 'checkin-url', 'qsot-ticket-qr-mode' );
		$is_url = 'checkin-url' == $option;

		// return the list of columns
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
			'ticket_link' => $is_url ? __( 'Ticket Url', 'opentickets-community-edition' ) : __( 'Ticket Code', 'openticket-community-edition' ),
		), $this->event );
	}

	// when starting to run the report, make sure our position counters are reset and that we know what event we are running this thing for
	protected function _starting() {
		$this->offset = 0;
		$this->event_id = max( 0, intval( $_REQUEST['event_id'] ) );
		$this->event = $this->event_id ? get_post( $this->event_id ) : (object)array( 'post_title' => __( '(unknown event)', 'opentickets-community-edition' ) );;

		// get the list of valid state types for this event
		$this->state_map = apply_filters( 'qsot-' . $this->slug . '-report-state-map', array(), $this->event_id );

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
	public function printer_friendly_url( $csv_file, $report ) {
		// get the base printer friendly url from the parent class
		$url = QSOT_Admin_Report::printer_friendly_url( $csv_file, $report );

		// add our special params
		$url = add_query_arg( array(
			'sa' => $this->slug,
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
			$year = date( 'Y', QSOT_Utils::local_timestamp( get_post_meta( $parent_id, '_start', true ) ) );

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
				'meta_key' => '_start',
				'meta_type' => 'DATETIME',
				'orderby' => array( 'meta_value' => 'asc', 'title' => 'asc' ),
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
	public function more_rows() {
		global $wpdb;

		// find a list of the valid states that are permanent states. we do not want 'reserved' seats or 'interest' seats showing here
		$valid = array();
		foreach ( $this->state_map as $key => $state )
			//if ( 0 == $state[4] )
				$valid[] = $key;
		$valid = array_filter( array_map( 'trim', $valid ) );
		// if there are no valid states, bail now
		if ( empty( $valid ) )
			return array();
		$in = "'" . implode( "','", $valid ) . "'";

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
		// get a list of possible order stati
		$wc_order_stati = wc_get_order_statuses();

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

		// get all the order stati
		$order_stati = $this->_get_order_stati( $order_ids );

		// get all the ticket codes, based on the order_item_ids, indexed by the order_item_ids
		$ticket_codes = $this->_get_ticket_codes( $order_item_ids );

		// get all order item meta, for all order items, and index it by order_item_id
		//$order_item_meta = $this->_get_order_item_meta( $order_item_ids );

		// get all the seating report comments by order_id
		$report_comments = $this->_get_comments_by_order( $order_ids );

		$final = array();
		// finally, put it all together
		foreach ( $group as $row ) {
			$status = '-';
			// determine the appropriate status to display
			if ( ! isset( $order_stati[ $row->order_id ] ) )
				$status = __( '(no-order)', 'opentickets-community-edition' );
			else if ( 'wc-completed' == $order_stati[ $row->order_id ] )
				$status = isset( $this->state_map[ $row->state ] ) ? $this->state_map[ $row->state ][3] : $wc_order_stati[ 'wc-completed' ];
			else if ( isset( $this->state_map[ $row->state ] ) && $this->state_map[ $row->state ][4] > 0 )
				$status = $this->state_map[ $row->state ][3];
			else if ( isset( $order_stati[ $row->order_id ], $wc_order_stati[ $order_stati[ $row->order_id ] ] ) )
				$status = $wc_order_stati[ $order_stati[ $row->order_id ] ];

			$final[] = apply_filters( 'qsot-' . $this->slug . '-report-data-row', array(
				'purchaser' => $this->_order_meta( $order_meta, 'name', $row ),
				'order_id' => $row->order_id ? $row->order_id : '-',
				'ticket_type' => $this->_ticket_type( $row->ticket_type_id ),
				'quantity' => $row->quantity ? $row->quantity : '-',
				'email' => $this->_order_meta( $order_meta, '_billing_email', $row ),
				'phone' => $this->_order_meta( $order_meta, '_billing_phone', $row ),
				'address' => $this->_order_meta( $order_meta, 'address', $row ),
				'note' => isset( $report_comments[ $row->order_id ] ) ? $report_comments[ $row->order_id ] : '',
				'state' => $status,
				'event' => apply_filters( 'the_title', $this->event->post_title, $this->event->ID ),
				'ticket_link' => isset( $ticket_codes[ $row->order_item_id ] ) ? apply_filters( 'qsot-get-ticket-link-from-code', $ticket_codes[ $row->order_item_id ], $ticket_codes[ $row->order_item_id ] ) : '',
				'_raw' => $row,
			), $row, $this->event, isset( $order_meta[ $row->order_id ] ) ? $order_meta[ $row->order_id ] : array() );
		}

		return $final;
	}

	// get a map of all the order stati id=>status
	protected function _get_order_stati( $order_ids ) {
		// if there are no orders, then bail
		if ( empty( $order_ids ) )
			return array();

		global $wpdb;
		// grab the raw list of stati
		$raw = $wpdb->get_results( 'select id, post_status from ' . $wpdb->posts . ' where id in( ' . implode( ',', $order_ids ) . ' )' );

		$map = array();
		// organize the results
		while ( $row = array_pop( $raw ) )
			$map[ $row->id . '' ] = $row->post_status;

		return $map;
	}

	// calculate the availability, based on the total number of tickets sold, subtracted from the total available
	protected function _available() {
		global $wpdb;

		// valid states
		$in = "'" . implode( "','", array_filter( array_map( 'trim', array_keys( $this->state_map ) ) ) ) . "'";

		// find the total sold
		$total = $wpdb->get_var( $wpdb->prepare( 'select sum(quantity) from ' . $wpdb->qsot_event_zone_to_order . ' where event_id = %d and state in (' . $in . ')', $this->event_id ) );

		// get the event capacity
		$capacity = intval( get_post_meta( get_post_meta( $this->event->ID, '_event_area_id', true ), '_capacity', true ) );

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
		if ( ! is_object( $temp ) || is_wp_error( $temp ) )
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
		remove_action( 'comment_feed_where', array( 'WC_Comments', 'exclude_order_comments_from_feed_where' ) );
		remove_action( 'comment_feed_where', array( 'WC_Comments', 'exclude_webhook_comments_from_feed_where' ) );
		remove_filter( 'comments_clauses', array( 'WC_Comments', 'exclude_order_comments' ), 10, 1 );
		remove_filter( 'comments_clauses', array( 'WC_Comments', 'exclude_webhook_comments' ), 10, 1 );
		$comment_ids = get_comments( array(
			'post__in' => $order_ids,
			'approve' => 'approve',
			'type' => 'order_note',
			'post_type' => 'shop_order',
			'meta_query' => array(
				array( 'key' => 'is_seating_report_note', 'value' => '1' ),
			),
			'orderby' => 'comment_date_gmt',
			'order' => 'desc',
			'number' => null,
			'fields' => 'ids'
		) );
		add_action( 'comment_feed_where', array( 'WC_Comments', 'exclude_order_comments_from_feed_where' ) );
		add_action( 'comment_feed_where', array( 'WC_Comments', 'exclude_webhook_comments_from_feed_where' ) );
		add_filter( 'comments_clauses', array( 'WC_Comments', 'exclude_order_comments' ), 10, 1 );
		add_filter( 'comments_clauses', array( 'WC_Comments', 'exclude_webhook_comments' ), 10, 1 );

		// if there are none, then bail now
		if ( empty( $comment_ids ) )
			return array();

		global $wpdb;
		// otherwise, get a list of all matched comments, and organize them by order_id
		$raw_comments = $wpdb->get_results( 'select comment_post_id order_id, comment_content from ' . $wpdb->comments . ' where comment_id in(' . implode( ',', $comment_ids ) . ') order by comment_date asc' );

		$final = array();
		// index the final list
		while ( $row = array_shift( $raw_comments ) )
			$final[ $row->order_id ] = $row->comment_content;

		return $final;
	}

	// get all the ticket codes, indexed by the order_item_id
	protected function _get_ticket_codes( $order_item_ids ) {
		if ( empty( $order_item_ids ) )
			return array();
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

	// take the resulting group of row datas, and create entries in the csv for them
	protected function _csv_render_rows( $group, $csv_file ) {
		// if the csv file descriptor has gone away, then bail (could happen because of filters)
		if ( ! is_array( $csv_file ) || ! isset( $csv_file['fd'] ) || ! is_resource( $csv_file['fd'] ) )
			return;

		// get a list of the csv fields to add, and their order
		$columns = $this->csv_report_columns();
		$cnt = count( $columns );

		$index = 0;
		// cycle through the roup of rows, and create the csv entries
		if ( is_array( $group ) ) foreach ( $group as $row ) {
			$data = array();
			for ( $i = 0; $i < $row['_raw']->quantity; $i++ ) {
				$index += 1;

				// get all the ticket codes for this quantity of line items
				$codes = apply_filters( 'qsot-get-ticket-qr-data', '', array(
					'order_item_id' => $row['_raw']->order_item_id,
					'order_id' => $row['_raw']->order_id,
					'event_id' => $row['_raw']->event_id,
					'product' => wc_get_product( $row['_raw']->ticket_type_id ),
					'index' => $index,
				) );

				// for each code we found for this line item, we need to make a separate row
				for ( $j = 0; $j < count( $codes ); $j++ ) {
					$csv_row = array();
					// create a list of data to add to the csv, based on the order of the columns we need, and the data for this row's data
					foreach ( $columns as $col => $__ ) {
						// update some rows with special values
						switch ( $col ) {
							// get the ticket code for this row
							case 'ticket_link':
								$csv_row[] = is_array( $codes ) ? $codes[ $j ] : '-';
							break;

							// since we are breaking these rows down to individual line items, we need to update the line item quantity to 1, since it represents a single ticket
							case 'quantity':
								$csv_row[] = 1;
							break;

							// default the purchaser to a cart id
							case 'purchaser':
								$csv_row[] = isset( $row[ $col ] ) && $row[ $col ]
										? ( '-' == $row[ $col ] ? ' ' . $row[ $col ] : $row[ $col ] ) // fix '-' being translated as a number in OOO
										: sprintf( __( 'Unpaid Cart: %s', 'opentickets-community-edition' ), $row['_raw']->session_customer_id );
							break;

							// pass all other data thorugh
							default:
								$csv_row[] = isset( $row[ $col ] ) && $row[ $col ] ? ( '-' == $row[ $col ] ? ' ' . $row[ $col ] : $row[ $col ] ) : '';
							break;
						}
					}
				}

				// allow manipulation of this data
				$data[] = apply_filters( 'qsot-' . $this->slug . '-report-csv-row', $csv_row, $row, $columns );
			}

			// add each found row to the csv, if there are any to add
			if ( ! empty( $data ) )
				foreach ( $data as $csv_row )
					if ( is_array( $csv_row ) && count( $csv_row ) == $cnt )
						fputcsv( $csv_file['fd'], $csv_row );
		}
	}
}

endif;

return new QSOT_New_Seating_Report();
