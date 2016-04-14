<?php if ( __FILE__ == $_SERVER['SCRIPT_FILENAME'] ) die( header( 'Location: /') ); // block direct access

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
		return self::$instance = new QSOT_system_status_page();
	}

	// the ?page=<slug> of the page
	protected $slug = 'qsot-system-status';

	// the permission that users must have in order to see this item/page
	protected $capability = 'manage_options';

	// determins the order in which this menu item appears under the main nav items
	protected $order = 1;

	// this is a tabbed page
	protected $tabbed = true;

	// list of known tools
	protected $tools = array();

	// setup the page titles and defaults
	public function __construct() {
		// protect the singleton
		if ( is_object( self::$instance ) ) {
			throw new Exception( __( 'There can only be one instance of the System Status page.', 'opentickets-community-edition' ), 101 );
		}

		// setup our titles
		$this->menu_title = __( 'System Status', 'opentickets-community-edition' );
		$this->page_title = __( 'System Status', 'opentickets-community-edition' );

		// register our tabs
		$this->_register_tab( 'system-status', array(
			'label' => __( 'System Status', 'opentickets-community-edition' ),
			'function' => array( &$this, 'page_system_status' ),
		) );
		$this->_register_tab( 'tools', array(
			'label' => __( 'Tools', 'opentickets-community-edition' ),
			'function' => array( &$this, 'page_tools' ),
		) );
		$this->_register_tab( 'adv-tools', array(
			'label' => __( 'Advanced Tools', 'opentickets-community-edition' ),
			'function' => array( &$this, 'page_adv_tools' ),
		) );

		// add page specific actions
		add_action( 'admin_notices', array( &$this, 'admin_notices' ), 1000 );

		// ajax handler
		add_action( 'wp_ajax_qsot-adv-tools', array( &$this, 'ajax_adv_tools' ), 100 );

		// allow base class to perform normal setup
		parent::__construct();

		// add the default tools
		$this->register_tool(
			'RsOi2Tt',
			array(
				'name' => __( 'Resync order items to tickets table', 'opentickets-community-edition' ),
				'description' => __( 'Looks up all order items that are ticket which have been paid for, and makes sure that they are marked in the tickets table as paid for. <strong>If you have many orders, this could take a while, during which time you should not close this window.</strong>', 'opentickets-community-edition' ),
				'function' => array( &$this, 'tool_RsOi2Tt' ),
				'messages' => array(
					'resync' => $this->_updatedw( __( 'The order-item to ticket-table resync has been completed.', 'opentickets-community-edition' ) ),
					'failed-resync' => $this->_errorw( __( 'A problem occurred during the order-item to ticket-table resync. It did not complete.', 'opentickets-community-edition' ) ),
				),
			)
		);
		$this->register_tool(
			'RsOi2Tt-bg',
			array(
				'name' => __( 'Background: resync order items to tickets table', 'opentickets-community-edition' ),
				'description' => __( 'Same as above, only all processing is done behind the scenes. This takes longer, but does not require that you keep this window open.', 'opentickets-community-edition' ),
				'function' => array( &$this, 'tool_RsOi2Tt_bg' ),
				'messages' => array(
					'resync-bg' => $this->_updatedw( __( 'We started the order-item to ticket-table resync. This will take a few minute to complete. You will receive an email upon completion.', 'opentickets-community-edition' ) ),
					'failed-resync-bg' => $this->_errorw( __( 'We could not start the background process that resyncs your order-items to the ticket-table. Try using the non-background method.', 'opentickets-community-edition' ) ),
				),
			)
		);
		$this->register_tool(
			'UpEASts',
			array(
				'name' => __( 'Update all event area stati', 'opentickets-community-edition' ),
				'description' => __( 'When upgrading from OTCE 1.x to 2.x, some of the event areas may not have gotten their status updated, depending on how the upgrade took place. This tool repairs that.', 'opentickets-community-edition' ),
				'function' => array( &$this, 'tool_UpEASts' ),
				'messages' => array(
					'updated-event-areas' => $this->_updatedw( __( 'All event area stati have been updated.', 'opentickets-community-edition' ) ),
				),
			)
		);
		$this->register_tool(
			'FdbUg',
			array(
				'name' => __( 'Force the DB tables to re-initialize', 'opentickets-community-edition' ),
				'description' => __( 'If you wish to validate the OpenTickets database tables and update them if necessary, use this tool.', 'opentickets-community-edition' ),
				'function' => array( &$this, 'tool_FdbUg' ),
				'messages' => array(
					'removed-db-table-versions' => $this->_updatedw( __( 'Purged the OTCE table versions, forcing a reinitialize of the tables.', 'opentickets-community-edition' ) ),
				),
			)
		);
	}

	// generic wrappers for admin messages
	protected function _updatedw( $str ) { return sprintf( '<div class="updated"><p>%s</p></div>', $str ); }
	protected function _errorw( $str ) { return sprintf( '<div class="error"><p>%s</p></div>', $str ); }

	// handle taredown of object
	public function __destruct() {
		// remove page specific actions
		add_action( 'admin_notices', array( &$this, 'admin_notices' ), 1000 );

		// parent destructs
		parent::__destruct();
	}

	// allow registering of a tool
	public function register_tool( $slug, $args ) {
		// normalize the args
		$args = wp_parse_args( $args, array(
			'slug' => $slug,
			'name' => $slug,
			'description' => '',
			'messages' => array(),
			'function' => array( &$this, 'noop' ),
		) );
		$this->tools[ $slug ] = $args;
	}

	// remove a tool from registration
	public function unregister_tool( $slug ) {
		unset( $this->tools[ $slug ] );
	}

	public function noop( $args ) { return $args; }

	// add noticies to the page, depending on the current tab
	public function admin_notices() {
		if ( ! isset( $_GET['page'] ) || $this->slug != $_GET['page'] ) return;
		$current = $this->_current_tab();

		// if on the system status tab, allow for a mehtod to copy a text version of the report for support tickets
		if ( 'system-status' == $current ) {
			?>
				<div class="updated system-status-report-msg">
					<p><?php _e( 'Copy and paste this information into your ticket when contacting support:', 'opentickets-community-edition' ) ?></p>
					<input type="button" id="show-status-report" class="button" value="<?php _e( 'Show Report', 'opentickets-community-edition' ) ?>" tar="#system-report-text" />
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

		// display error and success messages
		if ( 'tools' == $current && isset( $_GET['performed'] ) ) {
			// print any known messages that were registered in the tools list first
			foreach ( $this->tools as $slug => $tool )
				if ( isset( $tool['messages'], $tool['messages'][ $_GET['performed'] ] ) )
					echo $tool['messages'][ $_GET['performed'] ];

			// then, if there are any registered hooks for this message, run those now
			if ( has_action( 'qsot-ss-performed-' . $_GET['performed'] ) )
				do_action( 'qsot-ss-performed-' . $_GET['performed'], $_GET['performed'] );
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
						<?php foreach ( $this->tools as $slug => $tool ): ?>
							<tr class="tool-item">
								<td><a class="button" href="<?php echo esc_attr( $this->_action_nonce( $slug, add_query_arg( array( 'qsot-tool' => $slug ), $url ) ) ) ?>"><?php echo apply_filters( 'the_title', $tool['name'] ) ?></a></td>
								<td>
									<?php if ( ! empty( $tool['description'] ) ): ?>
										<span class="helper"><?php echo force_balance_tags( $tool['description'] ) ?></span>
									<?php endif ?>
								</td>
							</tr>
						<?php endforeach; ?>

						<?php do_action( 'qsot-system-status-tools' ) ?>
					</tbody>
				</table>
			</div>
		<?php
	}

	// page to display the DANGEROUS, and ADVANCED tools that COULD FUCK UP and entire site or CAUSE CUSTOMER SERVICE ISSUES
	public function page_adv_tools() {
		// if the user does not have permissions, then bail
		if ( ! current_user_can( 'manage_options' ) )
			return;

		// if the disclaimer has not been passed, then pop it now
		if ( ! $this->_verify_disclaimer() ) {
			$this->_pop_disclaimer();
			return;
		}

		// otherwise, display the dangerous tools
		$this->_display_adv_tools();
	}

	// on the advanced tools page, the user may have accepted the disclaimer on the last page load. if so, handle that acceptance now
	protected function _maybe_accepted() {
		// if the current user does not have permissions to do this, then skip this entirely
		if ( ! current_user_can( 'manage_options' ) )
			return;

		// if the button was not clicked, then bail
		if ( ! isset( $_POST['i-understand'] ) )
			return;

		// if the hash does not match what we expected, then bail
		if ( ! wp_verify_nonce( $_POST['i-understand'], 'yes-i-understand' ) )
			return;

		// construct the data for the cookie and store the cookie
		$data = array( 'a' . get_current_user_id(), 'b' . ( time() + HOUR_IN_SECONDS ), 'c' . rand( 0, PHP_INT_MAX ) );
		$nonce = wp_create_nonce( 'yes-i-agree' );
		$hash = md5( AUTH_SALT . @json_encode( $data ) . $nonce );
		$cookie = implode( ':', $data ) . '|' . $hash;
		setcookie( 'qsot-advtools', $cookie, time() + HOUR_IN_SECONDS, '/' );

		// redirect to force recheck of cookie
		wp_safe_redirect( remove_query_arg( array( 'updated' ) ) );
		exit;
	}

	// verify that the user has a recent acceptance of the disclaimer that these tools could mess up the site
	protected function _verify_disclaimer() {
		// does the cookie exist?
		if ( ! isset( $_COOKIE['qsot-advtools'] ) )
			return false;

		// break the cookie up to it's parts which we can use for verification
		@list( $pieces, $hash ) = explode( '|', $_COOKIE['qsot-advtools'] );
		$parts = explode( ':', $pieces );

		// if the hash does not match, then bail
		if ( empty( $parts ) || md5( AUTH_SALT . @json_encode( $parts ) . wp_create_nonce( 'yes-i-agree' ) ) !== $hash )
			return false;

		$expires = intval( substr( $parts[1], 1 ) );
		// if the acceptance is expired, then bail
		if ( time() > $expires )
			return false;

		return true;
	}

	// show the disclaimer that your site could get fucked up
	protected function _pop_disclaimer() {
		?>
			<div class="lou-disclaimer">
				<div class="lou-text"><?php _e( 'The tools provided on this page are moderately dangerous, and can cause problems on your site, if you are not absolutely certain you know what you are doing. Marking tickets as taken, without them being associated to an order, technically corrupts your date in a fashion that could be un-repairable. It can also cause certain automated processes in the system, to not function as expected. Furthermore, removing "reserved" tickets will remove the tickets from any active carts that contain them. This, at the very least, could cause un-expected customer service issues. In short, use these tools at your own risk.', 'opentickets-community-edition' ); ?></div>

				<form method="post" id="lou-disclaimer">
					<input type="hidden" name="i-understand" value="<?php echo esc_attr( wp_create_nonce( 'yes-i-understand' ) ) ?>" />
					<input type="submit" class="button-primary" value="<?php echo esc_attr( __( 'I understand.', 'opentickets-community-edition' ) ) ?>" />
				</div>
			</div>
		<?php
	}

	// display the advanced tools
	protected function _display_adv_tools() {
		?>
			<div class="qsot-ajax-form-wrapper">
				<form class="qsot-ajax-form" id="load-event-ticket-info" data-action="qsot-adv-tools" data-sa="load-event" data-target="#results">
					<div class="field">
						<label><?php _e( 'Select an Event', 'opentickets-community-edition' ) ?></label>
						<input type="hidden" name="event_id" value="" class="use-select2" data-action="qsot-adv-tools" data-sa="find-events" />
					</div>

					<div class="field actions right">
						<input type="submit" class="button-primary" value="<?php echo esc_attr( __( 'Load Event Info', 'opentickets-community-edition' ) ) ?>" />
					</div>
				</form>
			</div>

			<div id="results"></div>
		<?php
	}

	// handle the ajax request for the advanced tools page
	public function ajax_adv_tools() {
		// if the current user cannot access this stuff, then bail
		if ( ! current_user_can( 'manage_options' ) )
			wp_send_json( array( 's' => false, 'reason' => __( 'access denied.', 'opentickets-community-edition' ) ) );
		// if the nonce is not set, or does not match, then bail
		if ( ! isset( $_POST['_n'], $_POST['sa'] ) || ! wp_verify_nonce( $_POST['_n'], 'yes-do-system-status-ajax' ) )
			wp_send_json( array( 's' => false, 'reason' => __( 'request security failsed.', 'opentickets-community-edition' ) ) );

		$sa = $_POST['sa'];
		// do something different depending on the Sub Action
		switch ( $sa ) {
			case 'find-events':
				// if there is no query, return no results
				if ( ! isset( $_POST['q'] ) || empty( $_POST['q'] ) )
					wp_send_json( array( 's' => true, 'r' => array() ) );

				// find all child ids that match
				$event_ids = get_posts( array(
					'post_status' => 'publish',
					'post_type' => 'qsot-event',
					'post_parent__not_in' => array( 0 ),
					'posts_per_page' => -1,
					'paged' => $_POST['page'],
					'fields' => 'ids',
					's' => $_POST['q'],
					'meta_key' => '_start',
					'order' => 'asc',
					'orderby' => 'meta_value',
					'meta_type' => 'DATETIME',
				) );

				$results = array();
				// construct the results
				foreach ( $event_ids as $id ) {
					$post = get_post( $id );
					$results[] = array(
						'id' => $id,
						'text' => apply_filters( 'the_title', $post->post_title, $id ) . ' (' . sprintf( '#%d', $id ) . ')',
					);
				}

				// send the response
				wp_send_json( array(
					's' => true,
					'r' => $results,
				) );
			break;

			case 'find-orders':
				$none = array( 'id' => 0, 'text' => __( '(none)', 'opentickets-community-edition' ) );
				// if there is no query, return no results
				if ( ! isset( $_POST['q'] ) || empty( $_POST['q'] ) )
					wp_send_json( array( 's' => true, 'r' => array() ) );

				$qs = preg_split( '#\s+#', $_POST['q'] );
				$by_id = $by_name = $by_meta = array();
				$ids = array_filter( array_map( 'absint', $qs ) );
				// find posts that match
				$by_id = count( $ids ) ? get_posts( array(
					'post_type' => 'shop_order',
					'post_status' => 'wc-completed',
					'posts_per_page' => -1,
					'fields' => 'ids',
					'post__in' => $ids,
				) ) : array();
				$by_name = get_posts( array(
					'post_type' => 'shop_order',
					'post_status' => 'wc-completed',
					'posts_per_page' => -1,
					'fields' => 'ids',
					's' => implode( ' ', $qs ),
				) );
				$by_meta = get_posts( array(
					'post_type' => 'shop_order',
					'post_status' => 'wc-completed',
					'posts_per_page' => -1,
					'fields' => 'ids',
					'meta_query' => array(
						'relation' => 'OR',
						array(
							'key' => '_billing_email',
							'value' => implode( '%', $qs ),
							'compare' => 'LIKE',
						),
						array(
							'key' => '_billing_first_name',
							'value' => implode( '%', $qs ),
							'compare' => 'LIKE',
						),
						array(
							'key' => '_billing_last_name',
							'value' => implode( '%', $qs ),
							'compare' => 'LIKE',
						),
					),
				) );
				$order_ids = array_unique( array_merge( $by_id, $by_name, $by_meta ) );

				$results = array( $none );
				// construct the results array
				foreach ( $order_ids as $order_id ) {
					$order = wc_get_order( $order_id );
					$results[] = array( 'id' => $order_id, 'text' => sprintf( __( 'Order #%d (%s %s, %s)', 'opentickets-community-edition' ), $order_id, $order->billing_first_name, $order->billing_last_name, $order->billing_email ) );
				}

				// render response
				wp_send_json( array(
					's' => true,
					'r' => $results,
				) );
			break;

			case 'find-order-items':
				$none = array( 'id' => 0, 'text' => __( '(none)', 'opentickets-community-edition' ) );
				// if there is no query, return no results
				if ( ! isset( $_POST['q'] ) || empty( $_POST['q'] ) )
					wp_send_json( array( 's' => true, 'r' => array() ) );

				$qs = preg_split( '#\s+#', $_POST['q'] );
				// setup the base query parts
				global $wpdb;
				$fields = array( 'oi.*' );
				$tables = array( $wpdb->prefix . 'woocommerce_order_items oi' );
				$join = array();
				$where = array( $wpdb->prepare( 'and oi.order_item_type = %s and oi.order_item_name like %s', 'line_item', '%' . implode( '%', $qs ) . '%' ) );
				$groupby = array();
				$orderby = array();
				$limit = sprintf( 'limit %d offset %d', 40, 40 * ( absint( $_POST['page'] ) - 1 ) );

				// add the order_id to the query if it was passed
				if ( isset( $_POST['order_id'] ) )
					$where[] = $wpdb->prepare( 'and oi.order_id = %d', $_POST['order_id'] );

				// construct the list of fields that plugins can modify
				$list = array( 'fields', 'tables', 'join', 'where', 'groupby', 'orderby', 'limit' );
				$parts = compact( $list );
				$parts = apply_filters( 'qsot-system-status-adv-tools-find-order-items-query', $parts, $_POST );
				foreach ( $list as $key )
					$$key = $parts[ $key ];

				// finalize the fields into strings
				$fields = implode( ', ', $fields );
				$tables = implode( ', ', $tables );
				$join = ( $join = implode( ' ', $join ) ) ? ' join ' . $join : '';
				$where = implode( ' ', $where );
				$orderby = ( $orderby = implode( ', ', $orderby ) ) ? ' order by ' . $orderby : '';
				$groupby = ( $groupby = implode( ', ', $groupby ) ) ? ' group by ' . $groupby : '';

				// run the query to find the order_item_ids
				$ois = $wpdb->get_results( 'select ' . $fields . ' from ' . $tables . $join . ' where 1=1 ' . $where . $groupby . $orderby . ' ' . $limit );
				$oi_ids = array();
				if ( count( $ois ) ) foreach ( $ois as $oi ) $oi_ids[] = $oi->order_item_id;

				$oi_meta = array();
				// if there are order item ids, then fetch all their metas
				if ( count( $oi_ids ) ) {
					$all_meta = $wpdb->get_results( 'select * from ' . $wpdb->prefix . 'woocommerce_order_itemmeta where order_item_id in (' . implode( ',', $oi_ids ) . ')' );
					// if there is meta, then organize it
					if ( count( $all_meta ) ) {
						foreach ( $all_meta as $meta ) {
							if ( ! isset( $oi_meta[ $meta->order_item_id ] ) )
								$oi_meta[ $meta->order_item_id ] = array();
							$oi_meta[ $meta->order_item_id ][ $meta->meta_key ] = $meta->meta_value;
						}
					}
				}

				$results = array( $none );
				// construct the results
				if ( count( $ois ) ) foreach ( $ois as $oi ) {
					// if there is no product id or quantity, then skip this item
					if ( ! isset( $oi_meta[ $oi->order_item_id ], $oi_meta[ $oi->order_item_id ]['_product_id'], $oi_meta[ $oi->order_item_id ]['_qty'] ) )
						continue;

					// load the product
					$product = wc_get_product( $oi_meta[ $oi->order_item_id ]['_product_id'] );
					$qty = intval( $oi_meta[ $oi->order_item_id ]['_qty'] );

					$event = '';
					// if there is an event id, then load the event_title
					if ( isset( $oi_meta[ $oi->order_item_id ]['_event_id'] ) )
						$event = get_the_title( $oi_meta[ $oi->order_item_id ]['_event_id'] );

					$text = apply_filters(
						'qsot-system-status-adv-tools-order-item-result', 
						sprintf( __( '%s x %d %s [Order #%d]', 'opentickets-community-edition' ), $product->get_title(), $qty, $event ? '(' . $event . ')' : '', $oi->order_id ),
						$product,
						$oi,
						$oi_meta[ $oi->order_item_id ]
					);
					$results[] = array( 'id' => $oi->order_item_id, 'text' => $text );
				}

				// render response
				wp_send_json( array(
					's' => true,
					'r' => $results,
				) );
			break;

			case 'load-event':
				// if there is no event passed, then bail
				if ( ! isset( $_POST['event_id'] ) || intval( $_POST['event_id'] ) < 0 )
					wp_send_json( array( 's' => false, 'reason' => __( 'invalid request.', 'opentickets-community-edition' ) ) );

				// send the response
				wp_send_json( array(
					's' => true,
					'r' => $this->_render_event_breakdown_form( $_POST['event_id'] ),
				) );
			break;

			case 'release':
				// if there was no event id supplied, then hard fail
				if ( ! isset( $_POST['id'] ) || intval( $_POST['event_id'] ) < 0 )
					wp_send_json( array( 's' => false, 'reason' => __( 'invalid request.', 'opentickets-community-edition' ) ) );

				// if there is no id supplied, then bail
				if ( ! isset( $_POST['id'] ) || empty( $_POST['id'] ) )
					wp_send_json( array( 's' => true, 'r' => $this->_render_event_breakdown_form( $_POST['event_id'], __( 'No such ticket.', 'opentickets-community-edition' ) ) ) );

				// otherwise, release the ticket, no matter the costs
				$result = $this->_release_seat( $_POST['id'] );

				// if the result was not successful, tnen send a message saying why
				if ( is_wp_error( $result ) )
					wp_send_json( array( 's' => true, 'r' => $this->_render_event_breakdown_form( $_POST['event_id'], $result->get_error_message(), false ) ) );

				// otherwise, send a result with a positive message
				wp_send_json( array( 's' => true, 'r' => $this->_render_event_breakdown_form( $_POST['event_id'], __( 'That ticket has been released.', 'opentickets-community-edition' ) ) ) );
			break;

			case 'add-ticket':
				// if the minimum data is not present, then bail
				if ( ! isset( $_POST['event_id'], $_POST['quantity'], $_POST['ticket_type_id'], $_POST['state'] ) || $_POST['event_id'] <= 0 || $_POST['quantity'] <= 0 || $_POST['ticket_type_id'] <= 0 || empty( $_POST['state'] ) )
					wp_send_json( array( 's' => false, 'reason' => __( 'invalid request.', 'opentickets-community-edition' ) ) );

				@list( $time, $mille ) = explode( '.', microtime( true ) );
				$mille = substr( $mille, 0, 4 );
				// gather all the data we need for an entry
				$data = apply_filters( 'qsot-system-status-adv-tools-add-ticket-data', array(
					'event_id' => absint( $_POST['event_id'] ),
					'order_id' => isset( $_POST['order_id'] ) && intval( $_POST['order_id'] ) > 0 ? intval( $_POST['order_id'] ) : 0,
					'order_item_id' => isset( $_POST['order_item_id'] ) && intval( $_POST['order_item_id'] ) > 0 ? intval( $_POST['order_item_id'] ) : 0,
					'quantity' => absint( $_POST['quantity'] ),
					'ticket_type_id' => absint( $_POST['ticket_type_id'] ),
					'state' => $_POST['state'],
					'mille' => $mille,
					'session_customer_id' => uniqid( 'adv-tools-' ),
				), $_POST );

				global $wpdb;
				// insert the row
				$res = $wpdb->insert( $wpdb->qsot_event_zone_to_order, $data );

				// render response
				wp_send_json( array(
					's' => true,
					'r' => $this->_render_event_breakdown_form( $_POST['event_id'], $res ? __( 'Inserted new ticket', 'opentickets-community-edition' ) : __( 'New ticket failed.', 'opentickets-community-edition' ), !!$res ),
				) );
			break;
		}

		wp_send_json( array( 's' => false, 'reason' => __( 'invalid request.', 'opentickets-community-edition' ) ) );
	}

	// process a request to release a seat
	protected function _release_seat( $id ) {
		// breakdown the id
		$parsed = explode( ':', $id );
		$parsed = apply_filters( 'qsot-system-status-adv-tools-release-seat-id', array(
			'event_id' => absint( $parsed[0] ),
			'order_id' => absint( $parsed[1] ),
			'quantity' => intval( $parsed[2] ),
			'product_id' => absint( $parsed[3] ),
			'session_id' => trim( $parsed[4] ),
		), $id );

		// allow plugins to override this
		$result = apply_filters( 'qsot-system-status-adv-tools-release-seat', null, $parsed, $id );

		// if there was a change from plugins, then use it
		if ( null !== $result )
			return $result;

		// validate the request
		if ( empty( $parsed['event_id'] ) )
			return new WP_Error( 'unknown_event', __( 'The event id was invalid.', 'opentickets-community-edition' ) );
		if ( $parsed['quantity'] <= 0 )
			return new WP_Error( 'unknown_quantity', __( 'The quantity was invalid.', 'opentickets-community-edition' ) );
		if ( empty( $parsed['product_id'] ) )
			return new WP_Error( 'unknown_product', __( 'The product id was invalid.', 'opentickets-community-edition' ) );
		if ( '' == $parsed['session_id'] )
			return new WP_Error( 'unknown_session', __( 'The session id was invalid.', 'opentickets-community-edition' ) );

		global $wpdb;
		// lookup the row we are requesting, to verify it exists
		$row = $wpdb->get_row( $wpdb->prepare(
			'select * from ' . $wpdb->qsot_event_zone_to_order . ' where event_id = %d and order_id = %d and quantity = %d and ticket_type_id = %d and session_customer_id = %d',
			$parsed['event_id'],
			$parsed['order_id'],
			$parsed['quantity'],
			$parsed['product_id'],
			$parsed['session_id']
		) );

		// if there is no matching row, then bail
		if ( ! is_object( $row ) || is_wp_error( $row ) )
			return new WP_Error( 'no_match', __( 'Could not find that DB record.', 'opentickets-community-edition' ) );

		// otherwise, kill that row completely
		$wpdb->query( $wpdb->prepare(
			'delete from ' . $wpdb->qsot_event_zone_to_order . ' where event_id = %d and order_id = %d and quantity = %d and ticket_type_id = %d and session_customer_id = %d limit 1',
			$parsed['event_id'],
			$parsed['order_id'],
			$parsed['quantity'],
			$parsed['product_id'],
			$parsed['session_id']
		) );

		return true;
	}

	// render the form where the user can change the states of all tickets
	protected function _render_event_breakdown_form( $event_id, $msg='', $msg_good=true ) {
		// let plugins override this
		$response = apply_filters( 'qsot-adv-tools-event-breakdown-render', null, $event_id );

		// if there was a response created outside of this file, then use it instead
		if ( null !== $response )
			return $response;

		// load the event
		$event = apply_filters( 'qsot-get-event', false, $event_id );

		// if there is no event, then bail
		if ( ! is_object( $event ) )
			return '<h3>' . __( 'No such event.', 'opentickets-community-edition' ) . '</h3>';

		// get the event area, area type and zoner
		$event_area = apply_filters( 'qsot-event-area-for-event', false, $event->ID );
		$area_type = is_object( $event_area ) && ! is_wp_error( $event_area ) && isset( $event_area->area_type ) ? $event_area->area_type : false;
		$zoner = is_object( $area_type ) && ! is_wp_error( $area_type ) ? $area_type->get_zoner() : false;
		$stati = is_object( $zoner ) && ! is_wp_error( $zoner ) ? $zoner->get_stati() : array();

		// if any of those dont exist, then bail
		if ( ! is_object( $event_area ) || ! is_object( $area_type ) || ! is_object( $zoner ) || is_wp_error( $event_area ) || is_wp_error( $area_type ) || is_wp_error( $zoner ) )
			return '<h3>' . __( 'Could not load that event\'s data.', 'opentickets-community-edition' ) . '</h3>';

		global $wpdb;
		// load all the ticket to event to order associations
		$assoc = $wpdb->get_results( $wpdb->prepare( 'select * from ' . $wpdb->qsot_event_zone_to_order . ' where event_id = %d', $event_id ) );

		// get all opentickets options
		$settings_class_name = apply_filters( 'qsot-settings-class-name', '' );
		if ( empty( $settings_class_name ) || ! class_exists( $settings_class_name ) )
			return '<h3>' . __( 'A problem occurred, and your request cannot be processed.', 'opentickets-community-edition' ) . '</h3>';
		$opts = call_user_func_array( array( $settings_class_name, 'instance' ), array() );

		$ticket_types = array();
		// get all the ticket types
		$raw_ticket_types = $area_type->get_ticket_type( array( 'event' => $event ) );
		if ( is_array( $raw_ticket_types ) ) {
			foreach ( $raw_ticket_types as $sub_group => $list )
				foreach ( $list as $ticket_type )
					$ticket_types[ $ticket_type->id ] = $ticket_type;
		} else if ( is_object( $raw_ticket_types ) && ! is_wp_error( $raw_ticket_types ) ) {
			$ticket_types = array( $raw_ticket_types->id => $raw_ticket_types );
		}

		ob_start();
		// render the results
		?>
			<div id="qsot-save-results">
				<?php if ( ! empty( $msg ) ): ?>
					<div class="msg msg-<?php echo $msg_good ? 'good' : 'bad' ?>"><?php echo force_balance_tags( $msg ); ?></div>
				<?php endif; ?>
			</div>

			<div class="qsot-ajax-form">
				<table cellspacing="0" class="widefat" role="event" data-id="<?php echo esc_attr( $event_id ) ?>">
					<thead>
						<tr>
							<th><?php _e( 'Order', 'opentickets-community-edition' ) ?></th>
							<?php do_action( 'qsot-system-status-adv-tools-table-headers' ); ?>
							<th><?php _e( 'Ticket Type', 'opentickets-community-edition' ) ?></th>
							<th><?php _e( 'Quantity', 'opentickets-community-edition' ) ?></th>
							<th><?php _e( 'Status', 'opentickets-community-edition' ) ?></th>
							<th><?php _e( 'Actions', 'opentickets-community-edition' ) ?></th>
						</tr>
					</thead>

					<tbody>
						<?php if ( count( $assoc ) ): ?>
							<?php foreach ( $assoc as $row ): ?>
								<tr role="entry" data-row="<?php echo esc_attr( apply_filters(
									'qsot-system-status-adv-tools-table-row-id',
									sprintf( '%s:%s:%s:%s:%s', $row->event_id, $row->order_id, $row->quantity, $row->ticket_type_id, $row->session_customer_id ),
									$row
								) ) ?>">
									<td title="<?php echo esc_attr( $row->session_customer_id ) ?>"><?php
										echo $row->order_id > 0
												? sprintf(
													'<a href="%s" title="%s" target="_blank">%s</a> (item:%s)',
													get_edit_post_link( $row->order_id ),
													__( 'Edit this Order', 'opentickets-community-edition' ),
													$row->order_id,
													$row->order_item_id
												)
												: ( 'confirmed' == $row->state ? __( 'No Order (unassociated)', 'opentickets-community-edition' ) : __( 'No Order (in a cart)', 'opentickets-community-edition' ) );
									?></td>

									<?php do_action( 'qsot-system-status-adv-tools-table-row-columns', $row ); ?>

									<td><?php
										$product = wc_get_product( $row->ticket_type_id );
										if ( is_object( $product ) && ! is_wp_error( $product ) )
											echo sprintf( '<a href="%s" title="%s" target="_blank">%s</a>', get_edit_post_link( $product->id ), __( 'Edit this Ticket', 'opentickets-community-edition' ), $product->get_title() );
										else
											echo __( '(unknown ticket type)', 'opentickets-community-edition' );
									?></td>
									<td><?php echo $row->quantity ?></td>
									<td><?php echo $row->state ?></td>
									<td>
										<?php /* <a href="javascript:void();" role="edit-btn" class="button"><?php _e( 'Edit', 'opentickets-community-edition' ) ?></a> */ ?>
										<a href="javascript:void();" data-action="qsot-adv-tools" data-sa="release" data-target="#results" role="release-btn" class="button"><?php _e( 'Release', 'opentickets-community-edition' ) ?></a>
										<?php do_action( 'qsot-system-status-adv-tools-table-row-actions', $row ); ?>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php else: ?>
							<td colspan="5"><?php _e( 'No DB records for this event.', 'opentickets-community-edition' ) ?></td>
						<?php endif; ?>
					</tbody>
				</table>
			</div>

			<form class="qsot-ajax-form" data-target="#results" data-action="qsot-adv-tools" data-sa="add-ticket">
				<h3><?php _e( 'Add a Ticket', 'qsot-adv-tools' ) ?></h3>

				<?php $none = array( 'id' => 0, 'text' => __( '(none)', 'opentickets-community-edition' ) ); ?>

				<div class="constrict-fields">
					<div class="field">
						<label><?php _e( 'Order', 'opentickets-community-edition' ) ?></label>
						<input type="hidden" class="use-select2" data-init-value="<?php echo esc_attr( @json_encode( $none ) ) ?>" data-action="qsot-adv-tools" data-sa="find-orders" name="order_id" />
						<div class="helper"><?php _e( 'Leave this blank, or set it to 0, if you want the ticket to be completely unassociated.', 'opentickets-community-edition' ) ?></div>
					</div>

					<div class="field">
						<label><?php _e( 'Order Item', 'opentickets-community-edition' ) ?></label>
						<input type="hidden" class="use-select2" name="order_item_id"
								data-init-value="<?php echo esc_attr( @json_encode( $none ) ) ?>" data-action="qsot-adv-tools" data-sa="find-order-items" data-add="[name='order_id']" data-minchar="0" />
						<div class="helper"><?php _e( 'This can be hard to find, but is required if you want this new ticket to have an association.', 'opentickets-community-edition' ) ?></div>
					</div>

					<div class="field">
						<label><?php _e( 'Quantity', 'opentickets-community-edition' ) ?></label>
						<input type="number" class="widefat" value="0" name="quantity" />
						<div class="helper"><?php _e( 'The number of these tickets you want to reserve. Must be greater than 0.', 'opentickets-community-edition' ) ?></div>
					</div>

					<?php
						$ticket_types = apply_filters( 'qsot-system-status-adv-tools-add-ticket-ticket-types', $ticket_types, $event );
					?>

					<div class="field">
						<label><?php _e( 'Ticket Type', 'opentickets-community-edition' ) ?></label>
						<select class="widefat" name="ticket_type_id">
							<?php foreach ( $ticket_types as $ticket ): if ( ! is_object( $ticket ) || ! isset( $ticket->id ) ) continue; ?>
								<option value="<?php echo esc_attr( $ticket->id ); ?>"><?php echo $ticket->get_title() ?></option>
							<?php endforeach; ?>
						</select>
						<div class="helper"><?php _e( 'Select the ticket type for this new reservation. Keep in mind, all available type are listed here.', 'opentickets-community-edition' ) ?></div>
					</div>

					<div class="field">
						<label><?php _e( 'Ticket Status', 'opentickets-community-edition' ) ?></label>
						<select class="widefat" name="state">
							<?php foreach ( $stati as $abbr => $settings ): ?>
								<option value="<?php echo esc_attr( $settings[0] ); ?>" <?php selected( 'confirmed', $settings[0] ) ?>><?php echo $settings[2] ?></option>
							<?php endforeach; ?>
						</select>
						<div class="helper"><?php _e( 'The status you want the ticket to have.', 'opentickets-community-edition' ) ?></div>
					</div>

					<?php do_action( 'qsot-system-status-adv-tools-add-ticket-fields', $event ) ?>

					<div class="field actions right">
						<input type="hidden" value="<?php echo esc_attr( $event->ID ) ?>" name="event_id" />
						<input type="submit" class="button-primary" value="<?php echo esc_attr( __( 'Add Ticket', 'opentickets-community-edition' ) ) ?>" />
					</div>
				</div>
			</form>
		<?php

		$out = ob_get_contents();
		ob_end_clean();

		return trim( $out );
	}

	// enqueue assets we need for this page
	public function enqueue_assets() {
		// reusable data
		$url = QSOT::plugin_url() . 'assets/';
		$version = QSOT::version();

		// enqueuing
		wp_enqueue_style( 'select2' );
		wp_enqueue_script( 'qsot-system-status', $url . 'js/admin/system-status.js', array( 'qsot-tools', 'select2' ), $version );
		wp_localize_script( 'qsot-system-status', '_qsot_system_status', array(
			'nonce' => wp_create_nonce( 'yes-do-system-status-ajax' ),
			'str' => array(
				'No results found.' => __( 'No results found.', 'opentickets-community-edition' ),
				'Loading...' => __( 'Loading...', 'opentickets-community-edition' ),
			),
		) );
	}

	// handle actions before the page starts drawing
	public function page_head() {
		// maybe create the disclaimer imprint
		$this->_maybe_accepted();

		$current = $this->_current_tab();
		$args = array();

		// if we are on the tools page, and the current user can manage wp options
		if ( 'tools' == $current && current_user_can( 'manage_options' ) ) {
			$processed = false;

			// if the tool requested is on our list, then handle it appropriately
			if ( isset( $_GET['qsot-tool'] ) ) {
				// run known functions first
				if ( isset( $this->tools[ $_GET['qsot-tool'] ] ) )
					@list( $processed, $args ) = call_user_func( $this->tools[ $_GET['qsot-tool'] ]['function'], array( $processed, $args ), $args );

				// run additionall functions if they exist
				if ( has_action( 'qsot-ss-tool-' . $_GET['qsot-tool'] ) )
					list( $processed, $args ) = apply_filters( 'qsot-ss-tool-' . $_GET['qsot-tool'], array( $processed, $args ), $args );
			}

			// if one of the actions was actually processed, then redirect, which protects the 'refresh-resubmit' situtation
			if ( $processed ) {
				wp_safe_redirect( add_query_arg( $args, remove_query_arg( array( 'updated', 'performed', 'qsot-tool', 'qsotn' ) ) ) );
				exit;
			}
		}
	}

	// handle the resync requests
	public function tool_RsOi2Tt( $result, $args ) {
		if ( $this->_verify_action_nonce( 'RsOi2Tt' ) ) {
			$state = $_GET['state'] == 'bg' ? '-bg' : '';
			if ( $this->_perform_resync_order_items_to_ticket_table( $state, $result ) ) $result[1]['performed'] = 'resync' . $state;
			else $result[1]['performed'] = 'failed-resync' . $state;
			$result[0] = true;
		}
		return $result;
	}

	// repair all event area stati
	public function tool_UpEASts( $result, $args ) {
		// check that the repair can run
		if ( ! $this->_verify_action_nonce( 'UpEASts' ) )
			return $result;

		global $wpdb;
		// otherwise, perform the repair
		$q = 'select c.id, p.post_status as parent_status from '. $wpdb->posts . ' c join ' . $wpdb->posts . ' p on p.id = c.post_parent where c.post_type = "qsot-event-area"';
		$raw = $wpdb->get_results( $q );

		// perform the updates
		foreach ( $raw as $row )
			$wpdb->update( $wpdb->posts, array( 'post_status' => $row->parent_status ), array( 'id' => $row->id ) );

		$result[0] = true;
		$result[1]['performed'] = 'updated-event-areas';

		return $result;
	}

	// handle the force repair db tables request
	public function tool_FdbUg( $result, $args ) {
		if ( $this->_verify_action_nonce( 'FdbUg' ) ) {
			delete_option( '_qsot_upgrader_db_table_versions' );
			$result[1]['performed'] = 'removed-db-table-versions';
			$result[0] = true;
		}
		return $result;
	}

	// empty all files from a directory (skips subdirs)
	protected function _empty_dir( $path ) {
		// track how many files have been missed
		$missed = 0;

		// if we are not debugging, then hide potential warnings
		if ( ! WP_DEBUG ) {
			// if the path is a dir and writable and openable
			if ( is_writable( $path ) && is_dir( $path ) && ( $dir = @opendir( $path ) ) ) {
				$path = trailingslashit( $path );
				// find all the files in the dir
				while ( $file_basename = @readdir( $dir ) ) {
					$filename = $path . $file_basename;
					// if this item is not a regular file, then skip it
					if ( is_dir( $filename ) || is_link( $filename ) )
						continue;
					// if the file is not writable or cannot be removed, then skip it and tally a failure
					if ( ! is_writable( $filename ) || ! @unlink( $filename ) )
						$missed++;
				}
			}
		} else {
			if ( is_writable( $path ) && is_dir( $path ) && ( $dir = opendir( $path ) ) ) {
				$path = trailingslashit( $path );
				while ( $file_basename = readdir( $dir ) ) {
					$filename = $path . $file_basename;
					if ( is_dir( $filename ) || is_link( $filename ) )
						continue;
					if ( ! is_writable( $filename ) || ! unlink( $filename ) )
						$missed++;
				}
			}
		}

		if ( $missed > 0 )
			throw new Exception( __( 'Missed one or more file during cache purge.', 'opentickets-community-edition' ) );
	}

	// handles the resynce process request
	protected function _perform_resync_order_items_to_ticket_table( $in_bg, $result ) {
		if ( $in_bg ) {
			return $this->_attempt_backport_request();
		} else {
			// could run a long time
			ini_set( 'max_execution_time', 600 );

			// print a container to hold the debugging output
			echo '<html><head><title>' . __( 'Rsync Results', 'opentickets-community-edition' ) . '</title><style>',
					'.button { border-radius:3px; border-style:solid; border-width:1px; box-sizing:border-box; cursor:pointer; display:inline-block; font-size:13px; height:28px; line-height:26px; ',
						'margin:0; padding:0 10px 1px; text-decoration:none; white-space:nowrap; background:#0085ba none repeat scroll 0 0; border-color:#0073aa #006799 #006799; box-shadow:0 1px 0 #006799; ',
						'color:#fff; text-decoration:none; text-shadow:0 -1px 1px #006799, 1px 0 1px #006799, 0 1px 1px #006799, -1px 0 1px #006799; }',
					'</style></head><body><pre>';

			// actually perform the resync
			$res = $this->_do_resync( true );

			// update the resulting url query args, and create the url to link to
			$result[1]['performed'] = $res ? 'resync' : 'failed-resync';
			$url = add_query_arg( $result[1], remove_query_arg( array( 'updated', 'performed', 'qsot-tool', 'qsotn' ) ) );

			// close the main interior container
			echo '</pre>';

			// add a button the continue to the final url
			echo sprintf( '<a class="button" href="%s">%s</a>', esc_attr( $url ), __( 'Return to Tools Page', 'opentickets-community-edition' ) );

			// close up the container
			echo '</body></html>';

			exit;
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
	protected function _do_resync( $print=false ) {
		// increase the run time timeout limit, cause this could take a while
		global $wpdb;
		$per = 500; // limit all big queries to a certain number of rows at a time

		// fetch the default information for all new rows
		$u = wp_get_current_user();
		$user_id = $u->ID ? $u->ID : 1; // session_customer_id
		$since = current_time( 'mysql' ); // since

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
			// if we are writing results debug, do it now
			if ( $print )
				echo $wpdb->prepare( $oq, 'shop_order', $per, $offset ), "\nORDER IDS:\n", implode( ',', $order_ids ), "\n-----------\n";

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

				// if we debugging, then print the order item ids
				if ( $print )
					echo "ORDER_ITEM_IDS:\n", implode( ',', $item_ids ), "\n-----------\n";

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
					$update = apply_filters( 'qsot-system-status-tools-RsOi2Tt-update-data', array(
						'event_id' => isset( $item['_event_id'] ) ? $item['_event_id'] : 0,
						'ticket_type_id' => isset( $item['_product_id'] ) ? $item['_product_id'] : 0,
						'quantity' => isset( $item['_qty'] ) ? $item['_qty'] : 0,
						'order_item_id' => $item_id,
						'order_id' => $item_to_order_map[ $item_id ],
						'session_customer_id' => $user_id,
						'since' => get_the_date( 'Y-m-d H:i:s', $item_to_order_map[ $item_id ] ),
						'state' => 'confirmed',
					), $item, $item_id, $item_to_order_map[ $item_id ] );

					// add this event to the list of events that need processing later
					if ( $update['event_id'] ) {
						$event_ids[ $update['event_id'] ] = 1;
					}

					// make a list of data to validate if an entry already exists or not
					$where = apply_filters( 'qsot-system-status-tools-RsOi2Tt-exists-where', array(
						'event_id' => $update['event_id'],
						'ticket_type_id' => $update['ticket_type_id'],
						'quantity' => $update['quantity'],
						'order_id' => $update['order_id'],
					), $update, $item, $item_id, $order_id );

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

					// when debugging, print a record for this update item
					if ( $print )
						echo $exists ? '<span style="color:#008800">' : '<span style="color:#880000">', $testq . $where_str, "\n", $exists ? 'exists' : 'does not exist', "</span>\n";

					// if we have a matching record, then skip this one
					if ( $exists ) continue;

					// otherwise, create a new record that represents this order_item
					$res = $wpdb->insert( $wpdb->qsot_event_zone_to_order, $update );

					// if debugging, write the result of the insert request
					if ( $print )
						echo $res ? '<span style="color:#008800">' : '<span style="color:#880000">', '<strong>',
								'**RESULT: ', $res ? 'created' : 'could not create',
								"</strong></span>\n",
								$wpdb->last_error ? '<span style="color:#880000">--' . $wpdb->last_error . "</span>\n" : '';
				}
			}

			// clear all the caches
			$this->_clear_caches();
		}

		// update the availability counts for all the affected events
		foreach ( $event_ids as $event_id => $_ ) {
			$total = apply_filters( 'qsot-count-tickets', 0, array( 'state' => $o->{'z.states.c'}, 'event_id' => $event_id ) );
			update_post_meta( $event_id, '_purchases_ea', $total );
		}

		return true;
	}

	// clear the db and wp_cache caches, because in a long loop they can grow enormous, consuming a lot of memory
	protected function _clear_caches() {
		global $wpdb, $wp_object_cache;
		// clear our the query cache, cause it can be huge
		$wpdb->flush();

		// clear out the wp_cache cache, if we are using the core wp method, which is an internal associative array
		if ( isset( $wp_object_cache->cache ) && is_array( $wp_object_cache->cache ) ) {
			unset( $wp_object_cache->cache );
			$wp_object_cache->cache = array();
		}
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
