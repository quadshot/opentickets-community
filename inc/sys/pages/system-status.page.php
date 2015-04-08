<?php (__FILE__ == $_SERVER['SCRIPT_FILENAME']) ? die(header('Location: /')) : null; // block direct access

// class for displaying the system status page
class QSOT_system_status_page extends QSOT_base_page {
	// holds the singleton instance
	protected static $instance = null;

	// creates the singleton, because only one version of this page should exist
	public static function instance( $force = false ) {
		// force deconstruction of any existing objects, if they exist and we are forcing 
		if ( $force && is_object( self::$instance ) ) {
			unset( self::$instance );
		}

		// if we already have one instance of this page, then return that
		if ( is_object( self::$instance ) ) {
			return self::$instance;
		}

		// otherwise, create a new version of the page
		return new QSOT_system_status_page();
	}

	// the ?page=<slug> of the page
	protected $slug = 'qsot-system-status';

	// the permission that users must have in order to see this item/page
	protected $capability = 'manage_options';

	// determins the order in which this menu item appears under the main nav items
	protected $order = 1;

	// this is a tabbed page
	protected $tabbed = true;

	// setup the page titles and defaults
	public function __construct() {
		// protect the singleton
		if ( is_object( self::$instance ) ) {
			throw new Exception( 'There can only be one instance of the System Status page.', 101 );
		}

		// setup our titles
		$this->menu_title = __( 'System Status', 'opentickets-community-edition' );
		$this->page_title = __( 'System Status', 'opentickets-community-edition' );

		// register our tabs
		$this->_register_tab( 'system-status', array(
			'label' => 'System Status',
			'function' => array( &$this, 'page_system_status' ),
		) );
		$this->_register_tab( 'tools', array(
			'label' => 'Tools',
			'function' => array( &$this, 'page_tools' ),
		) );

		// add page specific actions
		add_action( 'admin_notices', array( &$this, 'admin_notices' ), 1000 );

		// allow base class to perform normal setup
		parent::__construct();
	}

	// handle taredown of object
	public function __destruct() {
		// remove page specific actions
		add_action( 'admin_notices', array( &$this, 'admin_notices' ), 1000 );

		// parent destructs
		parent::__destruct();
	}

	// add noticies to the page, depending on the current tab
	public function admin_notices() {
		if ( ! isset( $_GET['page'] ) || $this->slug != $_GET['page'] ) return;
		$current = $this->_current_tab();

		// if on the system status tab, allow for a mehtod to copy a text version of the report for support tickets
		if ( 'system-status' == $current ) {
			?>
				<div class="updated system-status-report-msg">
					<p>Copy and paste this information into your ticket when contacting support:</p>
					<input type="button" id="show-status-report" class="button" value="Show Report" tar="#system-report-text" />
					<textarea class="widefat" rows="12" id="system-report-text"><?php echo $this->_draw_report( 'text' ) ?></textarea>
				</div>
				<script language="javascript">
					( function( $ ) {
						$( document ).on( 'click', '#show-status-report', function( e ) {
							e.preventDefault();
							var tar = $( $( this ).attr( 'tar' ) );
							if ( 'none' == tar.css( 'display' ) ) tar.fadeIn( {
									duration:300,
									complete:function() { $(this).focus().select(); }
								});
							else tar.fadeOut( 250 );
						} );
					} )( jQuery );
				</script>
			<?php
		}

		if ( 'tools' == $current && isset( $_GET['performed'] ) ) {
			switch ( $_GET['performed'] ) {
				case 'removed-db-table-versions':
					echo sprintf(
						'<div class="updated"><p>%s</p></div>',
						__( 'Purged the OTCE table versions, forcing a reinitialize of the tables.', 'opentickets-community' )
					);
				break;

				case 'resync':
					echo sprintf(
						'<div class="updated"><p>%s</p></div>',
						__( 'The order-item to ticket-table resync has been completed.', 'opentickets-community' )
					);
				break;

				case 'resync-bg':
					echo sprintf(
						'<div class="updated"><p>%s</p></div>',
						__( 'We started the order-item to ticket-table resync. This will take a few minute to complete. You will receive an email upon completion.', 'opentickets-community' )
					);
				break;

				case 'failed-resync':
					echo sprintf(
						'<div class="error"><p>%s</p></div>',
						__( 'A problem occurred during the order-item to ticket-table resync. It did not complete.', 'opentickets-community' )
					);
				break;

				case 'failed-resync-bg':
					echo sprintf(
						'<div class="error"><p>%s</p></div>',
						__( 'We could not start the background process that resyncs your order-items to the ticket-table. Try using the non-background method.', 'opentickets-community' )
					);
				break;
			}
		}
	}

	// draw the page for the system report
	public function page_system_status() {
		?>
			<div class="inner">
				<?php $this->_draw_report( 'html' ); ?>
			</div>
		<?php
	}

	// draw the page with the tools on it
	public function page_tools() {
		$url = remove_query_arg( array( 'updated', 'performed', 'qsot-tool', 'qsotn' ) );
		?>
			<div class="inner">
				<table>
					<tbody>
						<tr class="tool-item">
							<td>
								<a class="button" href="<?php echo esc_attr( $this->_action_nonce( 'RsOi2Tt', add_query_arg( array( 'qsot-tool' => 'RsOi2Tt' ), $url ) ) ) ?>"><?php
									_e( 'Resync order items to tickets table', 'opentickets-community-edition' )
								?></a>
							</td>
							<td>
								<span class="helper"><?php _e( 'Looks up all order items that are ticket which have been paid for, and makes sure that they are marked in the tickets table as paid for. <strong>If you have many orders, this could take a while, during which time you should not close this window.</strong>', 'openticket-community-edition' ) ?></span>
							</td>
						</tr>

						<tr class="tool-item">
							<td>
								<a class="button" href="<?php echo esc_attr( $this->_action_nonce( 'RsOi2Tt', add_query_arg( array( 'qsot-tool' => 'RsOi2Tt', 'state' => 'bg' ), $url ) ) ) ?>"><?php
									_e( 'Background: resync order items to tickets table', 'opentickets-community-edition' )
								?></a>
							</td>
							<td>
								<span class="helper"><?php _e( 'Same as above, only all processing is done behind the scenes. This takes longer, but does not require that you keep this window open.', 'openticket-community-edition' ) ?></span>
							</td>
						</tr>

						<tr class="tool-item">
							<td>
								<a class="button" href="<?php echo esc_attr( $this->_action_nonce( 'FdbUg', add_query_arg( array( 'qsot-tool' => 'FdbUg' ), $url ) ) ) ?>"><?php
									_e( 'Force the DB tables to re-initialize', 'opentickets-community-edition' )
								?></a>
							</td>
							<td>
								<span class="helper"><?php _e( 'In some very rare cases, you may need to force the db tables to be recreated. This button, does that.', 'openticket-community-edition' ) ?></span>
							</td>
						</tr>
					</tbody>
				</table>
			</div>
		<?php
	}

	// handle actions before the page starts drawing
	public function page_head() {
		$current = $this->_current_tab();
		$args = array();

		// if we are on the tools page, and the current user can manage wp options
		if ( 'tools' == $current && current_user_can( 'manage_options' ) ) {
			$processed = false;

			// if the tool requested is on our list, then handle it appropriately
			if ( isset( $_GET['qsot-tool'] ) ) switch ( $_GET['qsot-tool'] ) {
				case 'RsOi2Tt': // resync tool
					if ( $this->_verify_action_nonce( 'RsOi2Tt' ) ) {
						$state = $_GET['state'] == 'bg' ? '-bg' : '';
						if ( $this->_perform_resync_order_items_to_ticket_table() ) $args['performed'] = 'resync' . $state;
						else $args['performed'] = 'failed-resync' . $state;
						$processed = true;
					}
				break;

				case 'FdbUg':
					if ( $this->_verify_action_nonce( 'FdbUg' ) ) {
						delete_option( '_qsot_upgrader_db_table_versions' );
						$args['performed'] = 'removed-db-table-versions';
						$processed = true;
					}
				break;
			}

			// if one of the actions was actually processed, then redirect, which protects the 'refresh-resubmit' situtation
			if ( $processed ) {
				wp_safe_redirect( add_query_arg( $args, remove_query_arg( array( 'updated', 'performed', 'qsot-tool', 'qsotn' ) ) ) );
				exit;
			}
		}
	}

	// handles the resynce process request
	protected function _perform_resync_order_items_to_ticket_table() {
		if ( 'bg' == $_GET['state'] ) {
			return $this->_attempt_backport_request();
		} else {
			ini_set( 'max_execution_time', 600 );
			return $this->_do_resync();
		}
	}

	// handle the backport request
	protected function _backport() {
		if ( ! isset( $_GET['qsot-in-background'] ) ) die( 'no.' );
		$user = get_user_by( 'id', $_GET['qsot-in-background'] );
		if ( ! is_object( $user ) ) die( 'no.' );

		$notify_email = $user->user_email;

		$success = $this->_do_resync();

		if ( ! $success ) {
			$subject = __( 'FAILED:', 'opentickets-community-edition' ) . ' ' . __( 'Background Order-Item -> Tickets Table', 'opentickets-community-edition' );
			$message = sprintf(
				__( 'There was a problem trying to resync your order-items to the ticket-table. Try using the non-background version. If that also fails, report a bug on <a href="%">the forums</a>.', 'opentickets-community-edition' ) . "\n\n",
				esc_attr( 'https://wordpress.org/support/plugin/opentickets-community-edition' )
			);
		} else {
			$subject = __( 'SUCCESS:', 'opentickets-community-edition' ) . ' ' . __( 'Background Order-Item -> Tickets Table', 'opentickets-community-edition' );
			$message = __( 'Your order-items have been succesfully resynced with the ticket-table.', 'opentickets-community-edition' ) . "\n\n";
		}

		$subject = '[' . date_i18n( __( 'm-d-Y', 'opentickets-community-edition' ) ) . '] ' . $subject;
		$purl = @parse_url( site_url() );
		$headers = array( 'From: Opentickets Background Process <background@' . $purl['host'] . '>' );
		wp_mail( $notify_email, $subject, $message, $headers );

		die();
	}

	// attempt to start the backend process that handles the resync. this will allow the user to continue using their browser for other things, while we run our script
	protected function _attempt_backport_request() {
		// get the current user so we can add it to our verification code, and so that we can pass the user_id to the script, which will be used to find the email address to notify
		$u = wp_get_current_user();

		// update the db with the nonce and user, so that we can check against that as a security measure for the backport request, which should mitigate abuse
		update_option( 'qsot-backport-request', $_GET['qsotn'] . '::' . $u->ID );

		// construct the url
		$purl = @parse_url( add_query_arg( array( 'qsot-in-background' => $u->ID ) ) );
		$url = site_url( '?' . $purl['query'] );

		// do the request and if we get an error message back, it failed
		$resp = wp_remote_get( $url, array(
			'timeout' => 2,
			'blocking' => false,
		) );

		// respond to the caller with a status of whether this was successful
		return ! is_wp_error( $resp );
	}

	// actually perform the syncing process of order item tickets to the tickets table
	protected function _do_resync() {
		// increase the run time timeout limit, cause this could take a while
		global $wpdb;
		$per = 500; // limit all big queries to a certain number of rows at a time

		// fetch the default information for all new rows
		$u = wp_get_current_user();
		$user_id = $u->ID ? $u->ID : 1; // session_customer_id
		$since = current_time( 'mysql' ); // since

		// load the core settings
		$settings_class = apply_filters( 'qsot-settings-class-name', '' );
		if ( ! empty( $settings_class ) ) {
			$o = call_user_func( array( $settings_class, 'instance' ) );
		} else return false;

		// container for the list of event_ids that need their availability recalculated
		$event_ids = array();

		// git a list of product ids that represent all tickets
		$ticket_type_ids = array_filter( array_map( 'absint', apply_filters( 'qsot-get-all-ticket-products', array(), 'ids' ) ) );
		if ( empty( $ticket_type_ids ) ) return false; // if there are no tickets, then there is literally nothign to do here

		// order stati that should have 'confirmed' tickets
		$confirmed_stati = array( 'wc-completed', 'wc-on-hold', 'wc-processing' );

		// get list of order its that should have confirmed tickets
		$oq = 'select id from ' . $wpdb->posts . ' where post_type = %s and post_status in ("' . implode( '","', $confirmed_stati ) . '") limit %d offset %d';

		// get list of order item id and order id pairs, from the orders that need confirmed tickets
		$oiq = 'select oi.order_id, oi.order_item_id from ' . $wpdb->prefix . 'woocommerce_order_items oi join ' . $wpdb->prefix . 'woocommerce_order_itemmeta oim on oi.order_item_id = oim.order_item_id '
				. 'where oim.meta_key = %s and oim.meta_value in (' . implode( ',', $ticket_type_ids ) . ') and oi.order_id in (%%ORDER_IDS%%) limit %d offset %d';

		// base query to see if a record already exists
		$testq = 'select count(order_id) from ' . $wpdb->qsot_event_zone_to_order . ' test where 1=1';

		// start at the first record
		$offset = 0;

		// while there are more orders to process, doing them a little at a time
		while ( ( $order_ids = $wpdb->get_col( $wpdb->prepare( $oq, 'shop_order', $per, $offset ) ) ) ) {
			// dont forget to increase our position in the list
			$offset += $per;

			// sanitize the list of order ids
			$order_ids = array_filter( array_map( 'absint', $order_ids ) );
			if ( empty( $order_ids ) ) continue; // if there are none, then there is nothing to do with this group

			// start at the beginning of the list or oder items
			$oi_off = 0;
			
			// while there are still order items to be from the list of orders taht need confirmed tickets
			while ( ( $pairs = $wpdb->get_results( $wpdb->prepare( str_replace( '%%ORDER_IDS%%', implode( ',', $order_ids ), $oiq ), '_product_id', $per, $oi_off ), ARRAY_N ) ) ) {
				// dont forget to increase our position in the list
				$oi_off += $per;
				$item_ids = $item_to_order_map = array();

				// create a map of order_item_id => order_id, and aggregate a list of the order_item_ids that we need all the meta for
				while ( ( $pair = array_pop( $pairs ) ) ) {
					$item_ids[] = $pair[1];
					$item_to_order_map[ $pair[1] . '' ] = $pair[0];
				}

				// sanitize the list of order item ids, and if we have none, then there is nothing to do here
				$item_ids = array_filter( array_map( 'absint', $item_ids ) );
				if ( empty( $item_ids ) ) continue;

				// get all the meta for all the items we are currently working with
				$items = $this->_items_from_ids( $item_ids );
				unset( $item_ids ); // free some memory

				// create a list of updates that need to be tested and possible processed
				$updates = array();
				// while: we have items to process, we have an item with meta, and we have an order_item_id for this item
				while ( count( $items ) && ( $item = end( $items ) ) && ( $item_id = key( $items ) ) ) {
					unset( $items[ $item_id ] ); // free memory

					// generate a list of all the data we can insert into the ticket table
					$update = array(
						'event_id' => isset( $item['_event_id'] ) ? $item['_event_id'] : 0,
						'ticket_type_id' => isset( $item['_product_id'] ) ? $item['_product_id'] : 0,
						'quantity' => isset( $item['_qty'] ) ? $item['_qty'] : 0,
						'order_item_id' => $item_id,
						'order_id' => $item_to_order_map[ $item_id ],
						'session_customer_id' => $user_id,
						'since' => $since,
						'state' => $o->{'z.states.c'},
					);

					// add this event to the list of events that need processing later
					if ( $update['event_id'] ) {
						$event_ids[ $update['event_id'] ] = 1;
					}

					// make a list of data to validate if an entry already exists or not
					$where = array(
						'event_id' => $update['event_id'],
						'ticket_type_id' => $update['ticket_type_id'],
						'quantity' => $update['quantity'],
						'order_id' => $update['order_id'],
					);

					// add the update and test to the list of updates
					$updates[] = array( $where, $update );
				}

				// while we have updates to process
				while ( ( list( $where, $update ) = array_pop( $updates ) ) ) {
					// piece together the where statement to uniquely identify this specific record
					$where_str = '';
					foreach ( $where as $key => $value ) {
						$where_str .= $wpdb->prepare( ' and `' . $key . '` = %s', $value );
					}

					// run the query to count the records that match (should be 1 or 0)
					$exists = $wpdb->get_var( $testq . $where_str );

					// if we have a matching record, then skip this one
					if ( $exists ) continue;

					// otherwise, create a new record that represents this order_item
					$wpdb->insert( $wpdb->qsot_event_zone_to_order, $update );
				}
			}
		}

		// update the availability counts for all the affected events
		foreach ( $event_ids as $event_id => $_ ) {
			$total = apply_filters( 'qsot-count-tickets', 0, array( 'state' => $o->{'z.states.c'}, 'event_id' => $event_id ) );
			update_post_meta( $event_id, $o->{'meta_key.ea_purchased'}, $total );
		}

		return true;
	}

	// aggregate all the order item meta for all order_item_ids ($ids)
	protected function _items_from_ids( $ids ) {
		global $wpdb;
		$indexed = array();

		// grab ALL meta for ALL ids
		$q = 'select order_item_id, meta_key, meta_value from ' . $wpdb->prefix . 'woocommerce_order_itemmeta where order_item_id in (' . implode( ',', $ids ) . ')';
		$all = $wpdb->get_results( $q, ARRAY_N );

		// index the meta key value pairs by the order_item_id
		while ( ( $item = array_pop( $all ) ) ) {
			if ( ! isset( $indexed[ $item[0] ] ) ) $indexed[ $item[0] ] = array( $item[1] => $item[2] );
			else $indexed[ $item[0] ][ $item[1] ] = $item[2];
		}

		// return the indexed list
		return $indexed;
	}

	// add an nonce to action urls
	protected function _action_nonce( $tool='', $url=null ) {
		$nonce = wp_create_nonce( $this->slug . '-tools-action-' . $tool );
		return add_query_arg( array( 'qsotn' => $nonce ), $url );
	}

	// verify the nonce on action urls
	protected function _verify_action_nonce( $tool= '' ) {
		if ( ! isset( $_GET['qsotn'] ) ) return false;
		return wp_verify_nonce( $_GET['qsotn'], $this->slug . '-tools-action-' . $tool );
	}

	// draw the system report in the specified format
	protected function _draw_report( $format='html' ) {
		// aggregate the report information
		$report = $this->_get_report();

		// based on the specified format, draw the report
		switch ( $format ) {
			default:
			case 'html': $this->_draw_html_report( $report ); break;
			case 'text': $this->_draw_text_report( $report ); break;
			case 'array': return $report; break;
		}
	}

	// normalizes the individual stats
	protected function _normalize_stat( $stat ) {
		static $def_data = array( 'msg' => '', 'extra' => '', 'type' => '' );
		return wp_parse_args( $stat, $def_data );
	}

	// draw an html table that displays the report
	protected function _draw_html_report( $report ) {
		$ind = 0;
		?>
			<table class="widefat qsot-status-table" id="status-table">
				<?php foreach ( $report as $group ): /* foreach stat group in the report */ ?>
					<?php
						$heading = $group['.heading']; // extract the heading
						$items = $group['.items']; // and extract the individual report items
					?>
					<?php /* create the heading row */ ?>
					<thead>
						<tr>
							<th colspan="2"><?php echo force_balance_tags( $heading['label'] ) ?></th>
						</tr>
					</thead>
					<?php /* create one row for each stat, with the stat label and it's value and extra html */ ?>
					<tbody>
						<?php foreach ( $items as $label => $data ): $data = $this->_normalize_stat( $data ); $ind++; ?>
							<tr class="<?php echo $ind % 2 == 1 ? 'odd' : '' ?>">
								<td><?php echo force_balance_tags( $label ) ?>:</td>
								<td><span class="msg <?php echo esc_attr( $data['type'] ) ?>"><?php echo $data['msg'] . ' ' . $data['extra'] ?></span></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				<?php endforeach; ?>
			</table>
		<?php
	}

	// draw a text version of the report that can be copied to the clipboard
	protected function _draw_text_report( $report ) {
		$strs = array();

		// foreach stat group in the report
		foreach ( $report as $group ) {
			$heading = $group['.heading']; // extract the header
			$items = $group['.items']; // and extract the items

			// add the header to the list of stats
			$strs[] = '== ' . $heading['label'] . ' ==';

			// add one line for each stat, with a label and a value
			foreach ( $items as $label => $data ) {
				$data = $this->_normalize_stat( $data );
				$msg = ! empty( $data['txt'] ) ? $data['txt'] : $data['msg'];
				$strs[] = sprintf( '  * %s: [%s] %s', $label, $data['type'], $msg );
			}

			// add some spacing below each group
			$strs[] = $strs[] = '';
		}

		// print out the results
		echo implode( "\n", $strs );
	}

	// aggregate a list of stats to display on the stat reports
	protected function _get_report() {
		global $wpdb;
		$groups = array();

		// environment group
		$group = $this->_new_group( __( 'Environment', 'opentickets-community-edition' ) );
		$items = array();

		$items['Home URL'] = $this->_new_item( home_url() );
		$items['Site URL'] = $this->_new_item( site_url() );
		$items['WC Version'] = $this->_new_item( WC()->version );
		$items['WP Version'] = $this->_new_item( $GLOBALS['wp_version'] );
		$items['WP Multisite Enabled'] = $this->_new_item( defined( 'WP_ALLOW_MULTISITE' ) && WP_ALLOW_MULTISITE );
		$items['Wev Server Info'] = $this->_new_item( $_SERVER['SERVER_SOFTWARE'] . ' ++ ' . $_SERVER['SERVER_PROTOCOL'] );
		$items['PHP Version'] = $this->_new_item( PHP_VERSION );
		if ( $wpdb->is_mysql ) {
			$items['MySQL Version'] = $this->_new_item( $wpdb->db_version() );
		}
		$items['WP Acitve Plugins'] = $this->_new_item( count( get_option( 'active_plugins' ) ) );

		$mem = ini_get( 'memory_limit' );
		$mem_b = QSOT::xb2b( $mem );
		$msg = '';
		$type = 'good';
		$extra = '';
		if ( $mem_b < 50331648 ) {
			$msg = sprintf(
				__( 'You have less than the required amount of memory allocated. The minimum required amount is 48MB. You currently have %s.', 'opentickets-community-edition' ),
				$mem
			);
			$type = 'bad';
			$extra = sprintf(
				__( 'Please <a href="%s">increase your memory allocation</a> to at least 48MB.', 'opentickets-community-edition' ),
				'http://codex.wordpress.org/Editing_wp-config.php#Increasing_memory_allocated_to_PHP'
			);
		} else if ( $mem_b < 67108864 ) {
			$msg = sprintf(
				__( 'You have more than the minimum required memory, but we still recommend you use allocate at least 64MB. You currently have %s.', 'opentickets-community-edition' ),
				$mem
			);
			$type = 'bad';
			$extra = sprintf(
				__( 'We strongly recommend that you <a href="%s">increase your memory allocation</a> to at least 48MB.', 'opentickets-community-edition' ),
				esc_attr( 'http://codex.wordpress.org/Editing_wp-config.php#Increasing_memory_allocated_to_PHP' )
			);
		} else {
			$msg = sprintf( __( 'You have more than the required minimum memory of 64MB. Your current total is %s.', 'opentickets-community-edition' ), $mem );
		}
		$items['WP Memory Limit'] = $this->_new_item( $msg, $type, $extra );
		
		$items['WP Debug Mode'] = $this->_new_item( defined( 'WP_DEBUG' ) && WP_DEBUG );
		$items['WP Language'] = $this->_new_item( get_locale() );
		$items['WP Max Upload Size'] = $this->_new_item( ini_get( 'uplaod_max_filesize' ) );
		$items['WP Max Post Size'] = $this->_new_item( ini_get( 'post_max_size' ) );
		$items['PHP Max Execution Time'] = $this->_new_item( ini_get( 'max_execution_time' ) );
		$items['PHP Max Input Vars'] = $this->_new_item( ini_get( 'max_input_vars' ) );

		$u = wp_upload_dir();
		$msg = 'Uploads directory IS writable';
		$type = 'good';
		$extra = ' (' . $u['basedir'] . ')';
		if ( ! is_writable( $u['basedir'] ) ) {
			$msg = 'Uploads directory IS NOT writable';
			$type = 'bad';
			$extra = sprintf(
				' (' . $u['basedir'] . ')'
				. __( 'Having your uploads directory writable not only allows you to upload your media files, but also allows OpenTickets (and other plugins) to store their file caches. Please <a href="%s">make your uploads directory writable</a> for these reasons.', 'opentickets-community-edition' ),
				esc_attr( 'http://codex.wordpress.org/Changing_File_Permissions' )
			);
		}
		$items['WP Uploads Writable'] = $this->_new_item( $msg, $type, $extra );

		$items['Default Timezone'] = $this->_new_item( date_default_timezone_get() );

		$group['.items'] = $items;
		$groups[] = $group;


		$group = $this->_new_group( __( 'Software', 'opentickets-community-edition' ) );
		$items = array();

		list( $html, $text ) = self::_get_plugin_list();
		$items['Active Plugins'] = $this->_new_item( implode( ', <br/>', $html ), 'neutral', '', "\n   + " . implode( ",\n   + ", $text ) );
		list( $html, $text ) = self::_get_theme_list();
		$items['Acitve Theme'] = $this->_new_item( implode( ', <br/>', $html ), 'neutral', '', "\n   + " . implode( ",\n   + ", $text ) );

		$group['.items'] = $items;
		$groups[] = $group;


		$group = $this->_new_group( __( 'Data', 'opentickets-community-edition' ) );
		$items = array();

		$list = self::_get_event_areas();
		$items['Event Areas'] = $this->_new_item( implode( ', ', $list ), 'neutral', '', "\n   + " . implode( ",\n   + ", $list ) );
		$list = self::_get_ticket_products();
		$items['Ticket Products'] = $this->_new_item( implode( ', ', $list ), 'neutral', '', "\n   + " . implode( ",\n   + ", $list ) );

		$group['.items'] = $items;
		$groups[] = $group;


		return apply_filters( 'qsot-system-status-stats', $groups );
	}

	// aggregate information about the event areas
	protected function _get_event_areas() {
		$out = array();

		$args = array(
			'post_type' => 'qsot-event-area',
			'post_status' => 'any',
			'posts_per_page' => -1,
		);
		$ea_posts = get_posts( $args );

		foreach ( $ea_posts as $ea_post ) {
			$price = get_post_meta( $ea_post->ID, '_pricing_options', true );
			$out[] = '"' . apply_filters( 'the_title', $ea_post->post_title ) . '" [' . ( $price > 0 ? '#' . $price : 'NONE' ) . '] (' . $ea_post->post_status . ')';
		}

		return $out;
	}

	// aggregate a list of ticket products
	protected function _get_ticket_products() {
		$out = array();

		$args = array(
			'post_type' => 'product',
			'post_status' => 'publish',
			'fields' => 'ids',
			'meta_query' => array(
				array( 'key' => '_ticket', 'value' => 'yes', 'compare' => '=' ),
			),
			'posts_per_page' => -1,
		);
		$ticket_product_ids = get_posts( $args );

		foreach ( $ticket_product_ids as $id ) {
			$p = wc_get_product( $id );
			$eas = self::_count_eas_with_price( $id );

			$out[] = '#' . $id . ' "' . $p->get_title() . '" (' . $p->get_price() . ') [' . $eas . ' EA]';
		}

		return $out;
	}

	protected function _count_eas_with_price( $product_id ) {
		global $wpdb;

		$q = $wpdb->prepare( 'select count(distinct post_id) from ' . $wpdb->postmeta . ' where meta_key = %s and meta_value = %d', '_pricing_options', $product_id );
		return (int) $wpdb->get_var( $q );
	}

	// aggregate the relevant theme information
	protected function _get_theme_list() {
		$html = $text = array();

		// fetch the current theme
		$theme = wp_get_theme();
		$is_parent = false;

		// recursively grab a list of themes that are bing loaded. start with the child, and work through all the parent themes
		do {
			// format the theme information
			$th_txt = $theme->get( 'Name' );
			$th_url = $theme->get( 'ThemeURI' );
			$th_link = ! empty( $th_url ) ? sprintf( '<a href="%s">%s</a>', esc_attr( $th_url ), $th_txt ) : $th_txt;

			// format the author information
			$by_txt = $theme->get( 'Author' );
			$by_url = $theme->get( 'AuthorURI' );
			$by_link = ! empty( $by_url ) ? sprintf( '<a href="%s">%s</a>', esc_attr( $by_url ), $by_txt ) : $by_txt;

			// format the two different versions of the information
			$html[] = sprintf(
				__( '%s%s by %s', 'opentickets-community-edition' ),
				$is_parent ? '[' . __( 'PARENT', 'opentickets-community-edition' ) . ']: ' : '',
				$th_link,
				$by_link
			);
			$text[] = sprintf(
				__( '%s%s (%s) by %s (%s)', 'opentickets-community-edition' ),
				$is_parent ? '[' . __( 'PARENT', 'opentickets-community-edition' ) . ']: ' : '',
				$th_txt,
				$th_url,
				$by_txt,
				$by_url
			);

			// if we do another iteration, then we are definitely in a parent theme, so mark it as such
			$is_parent = true;
		} while ( ( $theme = $theme->parent() ) );

		return array( $html, $text );
	}

	// aggregate an array of important information about activated plugins
	protected function _get_plugin_list() {
		$html = $text = array();

		// load the list of active plugins, and all the known information about all plugins
		$ap = get_option( 'active_plugins' );
		$p = get_plugins();

		// cycle through the list of active plugins a
		foreach ( $p as $file => $plugin ) {
			// is the plugin active?
			$on = in_array( $file, $ap );

			// format the author information
			$by_txt = isset( $plugin['Author'] ) ? $plugin['Author'] : '(Unknown Author)';
			$by_url = isset( $plugin['AuthorURI'] ) ? $plugin['PluginURI'] : '';
			$by_link = ! empty( $pl_url ) ? sprintf( '<a href="%s">%s</a>', esc_attr( $pl_url ), $plugin['Author'] ) : $by_txt;

			// format the known plugin information
			$pl_txt = $plugin['Name'];
			$pl_url = isset( $plugin['PluginURI'] ) ? $plugin['PluginURI'] : '';
			$pl_link = ! empty( $pl_url ) ? sprintf( '<a href="%s">%s</a>', esc_attr( $pl_url ), $plugin['Name'] ) : $pl_link;

			// format the two different versions of the information
			$html[] = sprintf(
				__( '<b>%s</b>%s by %s', 'opentickets-community-edition' ),
				$on ? '[' . __( 'ON', 'opentickets-community-edition' ) . ']: ' : '',
				$pl_link,
				$by_link
			);
			$text[] = sprintf(
				__( '%s%s (%s) by %s (%s)', 'opentickets-community-edition' ),
				$on ? '[' . __( 'ON', 'opentickets-community-edition' ) . ']: ' : '',
				$pl_txt,
				$pl_url,
				$by_txt,
				$by_url
			);
		}

		return array( $html, $text );
	}

	// construct a new list item for for the statistics list, based on the supplied information
	protected function _new_item( $value, $type='neutral', $extra='', $txt_version='' ) {
		return array( 'msg' => ( is_bool( $value ) ) ? ( $value ? 'Yes' : 'No' ) : (string) $value, 'type' => $type, 'extra' => $extra, 'txt' => $txt_version );
	}

	// create a new group of list items, which has a specific name
	protected function _new_group( $name ) {
		return array(
			'.heading' => array( 'label' => $name ),
			'.items' => array(),
		);
	}
}

return QSOT_system_status_page::instance();
