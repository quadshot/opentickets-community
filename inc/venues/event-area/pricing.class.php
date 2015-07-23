<?php (__FILE__ == $_SERVER['SCRIPT_FILENAME']) ? die(header('Location: /')) : null;

if (!class_exists('qsot_seat_pricing')):

/* handles the pricing control for seating chart and zones */
class qsot_seat_pricing {
	// holder for event plugin options
	protected static $o = null;
	protected static $options = null;

	public static function pre_init() {
		require_once 'post-type.class.php';
		// load all the options, and share them with all other parts of the plugin
		$options_class_name = apply_filters('qsot-options-class-name', '');
		if (!empty($options_class_name)) {
			self::$options = call_user_func_array(array($options_class_name, "instance"), array());
			//self::_setup_admin_options();
		}

		$settings_class_name = apply_filters('qsot-settings-class-name', '');
		if (!empty($settings_class_name)) {
			self::$o = call_user_func_array(array($settings_class_name, "instance"), array());

			add_filter( 'qsot-get-all-ticket-products', array( __CLASS__, 'get_all_ticket_products' ), 100, 2 );
			add_filter('qsot-price-formatted', array(__CLASS__, 'formatted_price'), 10, 1);

			add_filter('product_type_options', array(__CLASS__, 'add_ticket_product_type_option'), 999);
			add_action('woocommerce_process_product_meta', array(__CLASS__, 'save_product_meta'), 999, 2);
			add_action('woocommerce_order_item_needs_processing', array(__CLASS__, 'tickets_dont_need_processing'), 10, 3);

			add_action('init', array(__CLASS__, 'register_assets'), 10);

			// cart/order control
			add_filter('qsot-zoner-reserve-current-user', array(__CLASS__, 'reserve_current_user'), 100, 4);
			add_action('init', array(__CLASS__, 'sync_cart_tickets'), 6);
			add_action( 'woocommerce_cart_loaded_from_session', array( __CLASS__, 'sync_cart_tickets' ), 6 );
			add_action( 'qsot-sync-cart', array( __CLASS__, 'sync_cart_tickets' ), 10 );
			add_action('woocommerce_order_status_changed', array(__CLASS__, 'order_status_changed'), 100, 3);
			add_action('woocommerce_order_status_changed', array(__CLASS__, 'order_status_changed_cancel'), 101, 3);
			add_action('woocommerce_before_cart_item_quantity_zero', array(__CLASS__, 'delete_cart_ticket'), 10, 1);
			add_action('woocommerce_cart_item_removed', array(__CLASS__, 'delete_cart_ticket'), 10, 1);
			add_filter('woocommerce_get_cart_item_from_session', array(__CLASS__, 'load_item_data'), 20, 3);
			add_action('woocommerce_add_order_item_meta', array(__CLASS__, 'add_item_meta'), 10, 3);
			add_action('woocommerce_ajax_add_order_item_meta', array(__CLASS__, 'add_item_meta'), 10, 3);
			add_filter('woocommerce_hidden_order_itemmeta', array(__CLASS__, 'hide_item_meta'), 10, 1);
			add_action('woocommerce_before_view_order_itemmeta', array(__CLASS__, 'before_view_item_meta'), 10, 3);
			add_action('woocommerce_before_edit_order_itemmeta', array(__CLASS__, 'before_edit_item_meta'), 10, 3);
			add_filter('qsot-get-order-id-from-oiid', array(__CLASS__, 'oid_from_oiid'), 10, 3);

			// when adding a ticket to an order, we need to update the event order to zone table with the new data
			add_action( 'woocommerce_add_order_item_meta', array( __CLASS__, 'update_ticket_order_information' ), 100000, 3 );

			// when removing an item from the cart, we need to update reservations
			add_action( 'woocommerce_cart_item_removed', array( __CLASS__, 'upon_cart_remove_item' ), 5, 2 );
			add_action('wp_ajax_woocommerce_remove_order_item', array(__CLASS__, 'aj_admin_remove_order_item'), 5);
			add_action('before_delete_post', array(__CLASS__, 'before_delete_order'), 5, 1);

			add_filter('qsot-item-is-ticket', array(__CLASS__, 'item_is_ticket'), 10, 2);

			if ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX ) {
				add_action('woocommerce_after_cart_item_quantity_update', array(__CLASS__, 'not_more_than_available'), 10, 2);
			}

			if (is_admin()) {
				// admin order editing
				add_action('qsot-admin-load-assets-shop_order', array(__CLASS__, 'load_assets_edit_order'), 10, 2);
				add_filter('qsot-ticket-selection-templates', array(__CLASS__, 'ticket_selection_templates'), 10, 3);
				add_filter('qsot-order-admin-added-tickets', array(__CLASS__, 'reserve_admin'), 100, 4);

				// admin ajax
				add_filter('wp_ajax_qsot-admin-ticket-selection', array(__CLASS__, 'handle_admin_ajax'), 10);
				add_filter('qsot-ticket-selection-admin-ajax-update-ticket', array(__CLASS__, 'aaj_ts_update_ticket'), 10, 2);
				add_filter('qsot-ticket-selection-admin-ajax-update-order-items', array(__CLASS__, 'aaj_ts_update_order_items'), 10, 2);
				add_action('woocommerce_order_item_add_line_buttons', array(__CLASS__, 'add_tickets_button'), 10, 3);
			}
		}
	}

	public static function register_assets() {
		wp_register_script('qsot-admin-ticket-selection', self::$o->core_url.'assets/js/admin/order/ticket-selection.js', array('qsot-tools', 'jquery-ui-dialog', 'qsot-frontend-calendar'), '0.1.0');
	}

	// executes AFTER the zoner::reserve_current_user method, and adds the ticket to the cart on a reported success (or removes it on $count = 0)
	public static function reserve_current_user( $success, $event, $ticket_type_id=0, $count=0 ) {
		if ( ! $success ) return $success;

		$defs = array(
			'event' => null,
			'ticket_type_id' => 0,
			'count' => 0,
		);
		$args = array();

		// idetify the current user
		$defs['customer_id'] = apply_filters( 'qsot-zoner-current-user', md5( ( isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] . ':' : rand( 0, PHP_INT_MAX ) ) . ':' . time() ) );

		// noramlize all arguments
		if ( is_array( $event ) ) {
			$args = wp_parse_args( $event, $defs );
		} else {
			$args = wp_parse_args( array(
				'event' => $event,
				'ticket_type_id' => $ticket_type_id,
				'count' => $count,
			), $defs );
		}

		extract( $args );

		$event = ! is_object( $event ) && (int) $event ? get_post( $event ) : $event;
		if ( ! is_object( $event ) ) return $success;
		
		$woocommerce = WC();
		
		$data = array(
			'event_id' => $event->ID,
		);

		if ( is_object( $woocommerce->cart ) ) {
			// Generate a ID based on product ID, variation ID, variation data, and other cart item data
			$cart_id = $woocommerce->cart->generate_cart_id( $ticket_type_id, '', '', $data );
			
			// See if this product and its options is already in the cart
			$cart_item_key = $woocommerce->cart->find_product_in_cart( $cart_id );

			if ($count == 0) {
				if ($cart_item_key) {
					$woocommerce->cart->set_quantity( $cart_item_key, 0 );
				}
			} else {
				if ($cart_item_key) {
					$woocommerce->cart->set_quantity( $cart_item_key, $count );
				} else {
					$woocommerce->cart->add_to_cart( $ticket_type_id, $count, '', '', $data );
				}
			}
		}

		return $success;
	}

	// when an item is removed from the cart, we need to remove the reservations from the database
	public static function upon_cart_remove_item( $cart_item_key, $cart ) {
		$item = $cart->removed_cart_contents[ $cart_item_key ];
		if ( isset( $item['event_id'] ) ) {
			apply_filters( 'qsot-zoner-reserve-current-user', false, $item['event_id'], $item['product_id'], 0 );
		}
	}

	public static function reserve_admin($order, $event, $ticket_type_id, $count) {
		$event = !is_object($event) && (int)$event ? get_post($event) : $event;
		if (!is_object($event)) return $success;

		$current_item_id = 0;
		foreach ($order->get_items() as $oiid => $item) {
			if (!apply_filters('qsot-item-is-ticket', false, $item)) continue;
			if ($item['event_id'] != $event->ID || $item['product_id'] != $ticket_type_id) continue;
			$current_item_id = $oiid;
			break;
		}

		$product = get_product($ticket_type_id);
		$pt = $product->is_taxable();
		$ti = get_option('woocommerce_prices_include_tax') === 'no' ? false : true;
		$pex = $product->get_price_excluding_tax() * $count;
		$stax = $tax = 0;
		if ($pt && $ti) {
			$tax_rates = $_tax->get_rates( $this->get_tax_class() );
			$taxes = $_tax->calc_tax( $pex, $tax_rates, false );
			$stax = $tax = $_tax->get_tax_total( $taxes );
		}

		if ($current_item_id <= 0) {
			$item = array(
				'product_id' => $product->id,
				'variation_id' => isset($product->variation_id) ? $product->variation_id : '',
				'name' => $product->get_title(),
				'qty' => $count,
				'event_id' => $event->ID,
				'tax_class' => $product->get_tax_class(),
				'line_subtotal' => wc_format_decimal($pex),
				'line_subtotal_tax' => $stax,
				'line_total' => wc_format_decimal($pex),
				'line_tax' => $tax,
			);
			$item = apply_filters('qsot-reserve-admin-item-meta', $item, $product, $event, $order, $ticket_type_id, $count);
			$current_item_id = self::_add_order_item($order->id, $item);
		} else {
			wc_update_order_item_meta($current_item_id, '_qty', $count);
			wc_update_order_item_meta($current_item_id, '_line_subtotal', wc_format_decimal($pex));
			wc_update_order_item_meta($current_item_id, '_line_total', wc_format_decimal($pex));
			wc_update_order_item_meta($current_item_id, '_line_subtotal_tax', wc_format_decimal($stax));
			wc_update_order_item_meta($current_item_id, '_line_tax', wc_format_decimal($tax));
		}

		do_action( 'qsot-reserve-admin-order-item', $current_item_id, $item, $order->id, $event, $product, $count );
	}

	protected static function _add_order_item($order_id, $item) {
		do_action('qsot-ajax-before-add-order-item', $order_id, $item);

		// Add line item
		$item_id = wc_add_order_item( $order_id, array(
	 		'order_item_name' => $item['name'],
	 		'order_item_type' => 'line_item'
	 	) );

	 	// Add line item meta
	 	if ( $item_id ) {
		 	wc_update_order_item_meta( $item_id, '_qty', $item['qty'] );
		 	wc_update_order_item_meta( $item_id, '_tax_class', $item['tax_class'] );
		 	wc_update_order_item_meta( $item_id, '_product_id', $item['product_id'] );
		 	wc_update_order_item_meta( $item_id, '_variation_id', $item['variation_id'] );
		 	wc_update_order_item_meta( $item_id, '_line_subtotal', $item['line_subtotal'] );
		 	wc_update_order_item_meta( $item_id, '_line_subtotal_tax', $item['line_subtotal_tax'] );
		 	wc_update_order_item_meta( $item_id, '_line_total', $item['line_total'] );
		 	wc_update_order_item_meta( $item_id, '_line_tax', $item['line_tax'] );
	 	}

		do_action( 'woocommerce_ajax_add_order_item_meta', $item_id, $item, $order_id );

		return $item_id;
	}

	public static function before_delete_order($order_id) {
		if (get_post_type($order_id) != 'shop_order') return;
		$order = new WC_Order($order_id);
		if (!is_object($order)) return;
		self::_unconfirm_tickets($order, '*');
	}

	public static function aj_admin_remove_order_item() {
		global $wpdb;
		check_ajax_referer('order-item', 'security');

		$oiids = array_filter(wp_parse_id_list($_POST['order_item_ids']));
		if (!$oiids) return;

		$oids = $wpdb->get_col('select distinct order_id from '.$wpdb->prefix.'woocommerce_order_items where order_item_id in('.implode(',', $oiids).')');
		if (!$oids) return;

		foreach ($oids as $oid) {
			$order = new WC_Order($oid);
			self::_unconfirm_tickets($order, $oiids);
		}
	}

	public static function order_status_changed($order_id, $old_status, $new_status) {
		if (in_array($new_status, apply_filters('qsot-zoner-confirmed-statuses', array('on-hold', 'processing', 'completed')))) {
			$woocommerce = WC();
			$cuids = array();
			if (($customer_id = is_object($woocommerce->session) ? $woocommerce->session->get_customer_id() : '')) $cuids[] = $customer_id;
			if (($ocuid = get_post_meta($order_id, '_customer_user', true))) $cuids[] = $ocuid;
			if (empty($cuids)) return; // required. otherwise ALL reserved tickets for this event will be updated to confirmed... which is wrong

			$order = wc_get_order($order_id);
			
			foreach ($order->get_items() as $item_id => $item) {
				if (!apply_filters('qsot-item-is-ticket', false, $item)) continue;
				$res = apply_filters(
					'qsot-zoner-update-reservation',
					false,
					// completed or reserved, because we may be going from a confirmed status to a confirmed status.
					// if only reserved is used, then any already confirmed tickets get deleted
					array(
						'event_id' => $item['event_id'],
						'qty' => $item['qty'],
						'state' => array( self::$o->{'z.states.r'}, self::$o->{'z.states.c'} ),
						'order_id' => array( 0, $order_id ),
						'order_item_id' => $item_id,
						'ticket_type_id' => $item['product_id'],
						//'customer_id' => $cuids,
					),
					array(
						'state' => self::$o->{'z.states.c'},
						'order_id' => $order_id,
						//'customer_id' => empty( $ocuid ) ? $customer_id : $ocuid,
						'order_item_id' => $item_id
					)
				);

				do_action('qsot-confirmed-ticket', $order, $item, $item_id);
			}
		}
	}
	
	// separate function to handle the order status changes to 'cancelled'
	public static function order_status_changed_cancel( $order_id, $old_status, $new_status ) {
		// if the order is actually getting cancelled, or any other status that should be considered an 'unconfirm' step
		if ( in_array( $new_status, apply_filters( 'qsot-zoner-unconfirm-statuses', array( 'cancelled' ) ) ) ) {
			$order = wc_get_order( $order_id );
			// unconfirmed the seats
			self::_unconfirm_tickets( $order, '*', true, array( 'new_status' => $new_status, 'old_status' => $old_status ) );
		}
	}

	// unconfirm seats that used to be on an order
	protected static function _unconfirm_tickets( $order, $oiids, $modify_meta=false, $modify_meta_extra=array() ) {
		// for each order item
		foreach ( $order->get_items() as $oiid => $item ) {
			// make sure that this order item is one that should be cancelled
			if ( $oiids !== '*' && ( is_array( $oiids ) && ! in_array( absint( $oiid ), $oiids ) ) )
				continue;

			// if this order item is not a ticket, then skip it
			if ( ! apply_filters( 'qsot-item-is-ticket', false, $item ) )
				continue;
			
			// aggregate the information about the reservation to change
			$where = array( 'event_id' => $item['event_id'], 'ticket_type_id' => $item['product_id'], 'order_id' => $order->id, 'qty' => $item['qty'], 'order_item_id' => array( 0, $oiid ) );
			$ostatus = $order->get_status();

			// actaully perform the update that removes the reservations
			$res = apply_filters(
				'qsot-zoner-update-reservation',
				false,
				$where,
				array( 'qty' => 0, '_delete' => true )
			);

			// if we are being asked to modify the meta for these items as well, then do so
			if ( $modify_meta ) {
				$delete_meta = apply_filters( 'qsot-zoner-unconfirm-ticket-delete-meta', array( '_event_id' ), $oiid, $item, $order, $order->id, $modify_meta_extra );
				$zero_meta = apply_filters( 'qsot-zoner-unconfirm-ticket-zero-meta', array(), $oiid, $item, $order, $order->id, $modify_meta_extra );
				if ( ! empty( $delete_meta ) )
					foreach ( $delete_meta as $k )
						wc_delete_order_item_meta( $oiid, $k );
				if ( ! empty( $zero_meta ) )
					foreach ( $zero_meta as $k )
						wc_update_order_item_meta( $oiid, $k, 0 );
			}

			// let other plugins know that this happened
			do_action( 'qsot-unconfirmed-ticket', $order, $item, $oiid );
		}
	}

	public static function item_is_ticket($is, $item) {
		if ( ! isset( $item['product_id'] ) || ( ! isset( $item['qty'] ) && ! isset( $item['quantity'] ) ) ) return false;
		$ticket = get_post_meta( $item['product_id'], '_ticket', true );
		return $ticket == 'yes';
	}

	public static function sync_cart_tickets() {
		$woocommerce = WC();

		if (is_object($woocommerce) && is_object($woocommerce->session)) {
			$customer_id = $woocommerce->session->get_customer_id();
			do_action('qsot-zoner-clear-locks', 0, $customer_id);
		}

		if ( ( ( defined( 'DOING_AJAX' ) && DOING_AJAX ) || !is_admin() ) && is_object($woocommerce) && is_object($woocommerce->cart)) {
			$indexed = array();
			$ownerships = apply_filters('qsot-zoner-ownerships-current-user', array(), 0, 0, apply_filters('qsot-temporary-zone-states', array(self::$o->{'z.states.r'})), 0);

			foreach ($ownerships as $state => $state_ownerships) {
				foreach ($state_ownerships as $ownership) {
					$indexed[$ownership->event_id] = isset($indexed[$ownership->event_id]) && is_array($indexed[$ownership->event_id]) ? $indexed[$ownership->event_id] : array();
					$indexed[$ownership->event_id][$ownership->ticket_type_id] = 
						isset($indexed[$ownership->event_id], $indexed[$ownership->event_id][$state], $indexed[$ownership->event_id][$state][$ownership->ticket_type_id])
								&& is_array($indexed[$ownership->event_id][$state][$ownership->ticket_type_id])
							? $indexed[$ownership->event_id][$state][$ownership->ticket_type_id]
							: array();
					$indexed[$ownership->event_id][$ownership->ticket_type_id][] = $ownership;
				}
			}

			foreach ($woocommerce->cart->cart_contents as $key => $item) {
				if ( ! isset( $item['event_id'] ) ) continue;
				$eid = $item['event_id'];
				$pid = $item['product_id'];

				if ( ! isset( $indexed[$eid], $indexed[$eid][$pid] ) )
					$woocommerce->cart->set_quantity( $key, 0 );
				else
					$woocommerce->cart->set_quantity( $key, array_sum( wp_list_pluck( $indexed[$eid][$pid], 'quantity' ) ) );
			}
		}
	}

	public static function delete_cart_ticket($item_key) {
		$woocommerce = WC();
		if (!is_object($woocommerce) || !is_object($woocommerce->cart)) return;

		// get the cart item, whereever it exists
    if ( isset( $woocommerce->cart->removed_cart_contents[ $item_key ] ) ) {
      $item = $woocommerce->cart->removed_cart_contents[ $item_key ];
      // do not allow restoring, because, someone could have swooped in and grabbed the seat during the layover
      unset( $woocommerce->cart->removed_cart_contents[ $item_key ] );
    } else if ( isset( $woocommerce->cart->cart_contents[ $item_key ] ) ) {
      $item = $woocommerce->cart->cart_contents[ $item_key ];
    }
		if (empty($item)) return;

		$cuids = array();
		if (($customer_id = is_object($woocommerce->session) ? $woocommerce->session->get_customer_id() : '')) $cuids[] = $customer_id;
		if (is_user_logged_in()) $cuids[] = get_current_user_id();
		if (empty($cuids)) return; // required. otherwise ALL reserved tickets for this event will be updated to confirmed... which is wrong

		if (isset($item['event_id'])) {
			$res = apply_filters(
				'qsot-zoner-update-reservation',
				false,
				array(
					'event_id' => $item['event_id'],
					'qty' => $item['quantity'],
					'state' => self::$o->{'z.states.r'},
					'customer_id' => $cuids,
					'ticket_type_id' => $item['product_id']
				),
				array('qty' => 0, '_delete' => true)
			);
		}
	}

	public static function not_more_than_available($item_key, $quantity) {
		$woocommerce = WC();

		$starting_quantity = $woocommerce->cart->cart_contents[$item_key]['_starting_quantity'];
		$add_qty = $quantity - $starting_quantity;
		$needs_change = false;

		$event_id = isset($woocommerce->cart->cart_contents[$item_key]['event_id']) ? $woocommerce->cart->cart_contents[$item_key]['event_id'] : false;
		if (!$event_id) return;
		$event = apply_filters('qsot-get-event', false, $event_id);
		if (!is_object($event) || !isset($event->meta, $event->meta->available, $event->meta->reserved)) return;

		if ($add_qty > 0 && $event->meta->available - $event->meta->reserved - $add_qty < 0) {
			$needs_change = true;

			$woocommerce->cart->cart_contents[$item_key]['quantity'] = $event->meta->available - $event->meta->reserved + $starting_quantity > 0
				? $event->meta->available - $event->meta->reserved + $starting_quantity
				: 0;
			$product = wc_get_product( $woocommerce->cart->cart_contents[$item_key]['product_id'] );

			if ( $woocommerce->cart->cart_contents[$item_key]['quantity'] > 0 ) {
				wc_add_notice( sprintf(
					__('There were only %d of the %s for %s available. We reserved all of them for you instead of the %d you requested.','opentickets-community-edition'),
					$woocommerce->cart->cart_contents[$item_key]['quantity'],
					$product->get_title(),
					$event->post_title,
					$quantity
				), 'error' );
			} else {
				wc_add_notice( sprintf(
					__('There were no %s for %s available to give you. We have removed it from your cart.','opentickets-community-edition'),
					$product->get_title(),
					$event->post_title
				), 'error' );
			}
		}

		if ( $needs_change ) {
			if ($woocommerce->cart->cart_contents[$item_key]['quantity'] <= 0) {
				$woocommerce->cart->set_quantity($item_key, 0);
			} else {
				$cuids = array();
				if (($customer_id = is_object($woocommerce->session) ? $woocommerce->session->get_customer_id() : '')) $cuids[] = $customer_id;
				if ( isset( WC()->session ) && ( $order_id = absint( WC()->session->order_awaiting_payment ) ) && ($ocuid = get_post_meta($order_id, '_customer_user', true))) $cuids[] = $ocuid;

				// required. otherwise ALL reserved tickets for this event will be updated to confirmed... which is wrong
				if (!empty($cuids)) {
					$res = apply_filters(
						'qsot-zoner-update-reservation',
						false,
						array('event_id' => $event_id, 'order_id' => 0, 'state' => '*', 'customer_id' => $cuids),
						array('qty' => $quantity)
					);
				}
			}
		}
	}

	// get the order_id from the order_item_id
	protected static function _oid_from_oiid( $oiid ) {
		// create the cache key. caching is important here because it saves us a db query
		$key = 'oiid2oid-' . $oiid;

		// fetch the cache
		$oid = (int) wp_cache_get( $key );

		// if there is no cache, then create it
		if ( $oid <= 0 ) {
			global $wpdb;
			$oid = $wpdb->get_var( $wpdb->prepare( 'select order_id from ' . $wpdb->prefix . 'woocommerce_order_items where order_item_id = %d', $oiid ) );
			wp_cache_set( $key, $oid, 300 );
		}

		// return the found id
		return $oid;
	}

	// when we are adding meta data to the order item, check if the item is a ticket. if it is, then update the order to event zone table with the appropriate data
	public static function update_ticket_order_information( $item_id, $item, $cart_item_key ) {
		// if this items is a ticket
		if ( isset( $item['event_id'] ) ) {
			// fetch the order_id from the order_item_id
			$order_id = self::_oid_from_oiid( $item_id );
			
			// get the unique identifier of this user, which is reported in the table we are updating
			$session_id = WC()->session->get_customer_id();

			// update the data in the table so that the order_id and the order_item_id are correct
			$res = apply_filters(
				'qsot-zoner-update-reservation',
				false,
				array( 'event_id' => $item['event_id'], 'state' => '*', 'customer_id' => $session_id, 'ticket_type_id' => $item['product_id'], 'qty' => $item['quantity'] ),
				array( 'order_id' => $order_id, 'order_item_id' => $item_id )
			);
		}
	}

	public static function load_item_data($current, $values, $key) {
		foreach (apply_filters('qsot-zoner-item-data-keys', array()) as $k)
			if (isset($values[$k])) $current[$k] = $values[$k];
		$current['_starting_quantity'] = $current['quantity'];
		return $current;
	}

	public static function add_item_meta($item_id, $values) {
		foreach (apply_filters('qsot-zoner-item-data-keys', array()) as $k) {
			if (!isset($values[$k])) continue;
			wc_update_order_item_meta($item_id, '_'.$k, $values[$k]);
		}
	}

	public static function hide_item_meta($list) {
		$list[] = '_event_id';
		return $list;
	}

	public static function before_view_item_meta($item_id, $item, $product) {
		self::_draw_item_ticket_info($item_id, $item, $product, false);
	}

	public static function before_edit_item_meta($item_id, $item, $product) {
		self::_draw_item_ticket_info($item_id, $item, $product, true);
	}

	// add the relevant ticket information and meta to each order item that needs it, along with a change button for event swaps
	protected static function _draw_item_ticket_info( $item_id, $item, $product, $edit=false ) {
		// if the product is not a ticket, then never display event meta
		if ( $product->ticket != 'yes' )
			return;

		// find the event name, defaulting to '(no event selected)'
		$event_display = '<span class="event-name">' . __( '(no event selected)', 'opentickets-community-edition' ) . '</span>';
		$event_id = 0;
		// if there is an event id, then 
		if ( isset( $item['event_id'] ) ) {
			// load the event
			$event = get_post( $item['event_id'] );

			// if the event exists, then
			if ( is_object( $event ) ) {
				// update the event display name
				$event_display = sprintf(
					'<a rel="edit-event" target="_blank" href="%s">%s</a>',
					get_edit_post_link( $event->ID ),
					apply_filters( 'the_title', $event->post_title, $event->ID )
				);
				$event_id = $event->ID;
			}
		}
		?>
			<div class="meta-list ticket-info" rel="ticket-info">
				<?php if ($edit): ?>
					<div><a href="#" class="button change-ticket"
						item-id="<?php echo esc_attr( $item_id ) ?>"
						event-id="<?php echo esc_attr( $event_id ) ?>"
						qty="<?php echo esc_attr( $item['qty'] ) ?>">Change</a></div>
				<?php endif; ?>
				<div class="info"><strong><?php _e( 'Event:', 'opentickets-community-edition' ) ?></strong> <?php echo $event_display ?></div>
				<?php do_action( 'qsot-ticket-item-meta', $item_id, $item, $product ) ?>
			</div>
		<?php
	}

	public static function get_all_ticket_products( $list, $format='objects' ) {
		$args = array(
			'post_type' => 'product',
			'post_status' => 'publish',
			'posts_per_page' => -1,
			'orderby' => 'title',
			'order' => 'asc',
			'fields' => 'ids',
			'meta_query' => array(
				array(
					'key' => '_ticket',
					'value' => 'yes',
					'compare' => '=',
				),
			),
		);

		$ids = get_posts($args);
		if ( 'ids' == $format ) return $ids;

		$tickets = array();
		foreach ($ids as $id) {
			$ticket = get_product($id);
			if (!is_object($ticket)) continue;
			$ticket->post->meta = array();
			$ticket->post->meta['price_raw'] = $ticket->price;
			$ticket->post->meta['price_html'] = wc_price($ticket->post->meta['price_raw']);
			$ticket->post->meta['price'] = apply_filters('qsot-price-formatted', $ticket->post->meta['price_raw']);
			$ticket->post->proper_name = $ticket->get_title();
			$tickets[''.$ticket->post->ID] = $ticket;
		}

		return $tickets;
	}

	public static function load_assets_edit_order($exists, $order_id) {
		wp_enqueue_script('qsot-frontend-calendar');
		wp_enqueue_style('qsot-frontend-calendar-style');

		do_action('qsot-calendar-settings', get_post($order_id), true, '');

		//wp_enqueue_style('qsot-jquery-ui');
		wp_enqueue_style( 'wp-jquery-ui-dialog' );
		wp_enqueue_script('qsot-admin-ticket-selection');
		wp_localize_script('qsot-admin-ticket-selection', '_qsot_admin_ticket_selection', array(
			'nonce' => wp_create_nonce('edit-order-ticket-selection'),
			'templates' => apply_filters('qsot-ticket-selection-templates', array(), $exists, $order_id),
		));
	}

	public static function ticket_selection_templates($list, $exists, $order_id) {
		$list['dialog-shell'] = '<div class="ticket-selection-dialog" title="Select Ticket">'
				.'<div class="errors" rel="errors"></div>'
				.'<div class="event-info" rel="info"></div>'
				.'<div class="actions" rel="actions"></div>'
				.'<div class="display-transition" rel="transition"></div>'
				.'<div class="display-event" rel="event-wrap"></div>'
				.'<div class="display-calendar" rel="calendar-wrap"></div>'
			.'</div>';

		$list['info'] = '<div class="ts-section info-wrap" rel="wrap">'
				.'<div class="row"><span class="label">'.__('Event:','opentickets-community-edition').' </span> <span class="value event-name" rel="name"></span></div>'
				.'<div class="row"><span class="label">'.__('Date:','opentickets-community-edition').' </span> <span class="value event-date" rel="date"></span></div>'
				.'<div class="event-capacity" rel="capacity">'
					.'<span class="field"><span class="label">'.__('Capacity:','opentickets-community-edition').' </span> <span class="value total-capacity" rel="total"></span></span>'
					.'<span class="field"><span class="label">'.__('Available:','opentickets-community-edition').' </span> <span class="value available" rel="available"></span></span>'
				.'</div>'
			.'</div>';

		$list['actions:change'] = '<div class="action-list" rel="btns">'
				.'<input type="button" class="button" rel="change-btn" value="'.__('Different Event','opentickets-community-edition').'"/>'
				.'<input type="button" class="button" rel="use-btn" value="'.__('Use This Event','opentickets-community-edition').'"/>'
			.'</div>';

		$list['actions:add'] = '<div class="action-list" rel="btns">'
				.'<input type="button" class="button" rel="change-btn" value="'.__('Different Event','opentickets-community-edition').'"/>'
			.'</div>';

		$list['inner:change'] = '<div class="image-wrap" rel="image-wrap"></div>';

		$list['inner:add'] = '<div class="add-tickets-ui" rel="add-ui">'
				.'<div class="ticket-form ts-section">'
					.'<span class="ticket-name" rel="ttname"></span>'
					.'<input type="number" min="1" max="100000" step="1" rel="ticket-count" name="qty" value="1" />'
					.'<input type="button" class="button" rel="add-btn" value="'.__('Add Tickets','opentickets-community-edition').'" />'
				.'</div>'
				.'<div class="image-wrap" rel="image-wrap"></div>'
			.'</div>';

		$list['transition'] = '<h1 class="loading">'.__('Loading. One moment please...','opentickets-community-edition').'</h1>';

		return $list;
	}

	public static function handle_admin_ajax() {
		$post = wp_parse_args($_POST, array('sa' => '', 'order_id' => 0, 'nonce' => ''));
		$resp = array();
		if (wp_verify_nonce($post['nonce'], 'edit-order-ticket-selection')) {
			if (!empty($post['sa'])) $resp = apply_filters('qsot-ticket-selection-admin-ajax-'.$post['sa'], $resp, $post);
		} else {
			$resp['s'] = false;
			$resp['e'] = array( __('Invalid request. Please refresh the page and try again.','opentickets-community-edition') );
		}
		header('Content-Type: text/json');
		echo @json_encode($resp);
		exit;
	}

	public static function aaj_ts_update_order_items($resp, $data) {
		$resp['s'] = false;
		$resp['e'] = array();

		$oid = $data['order_id'];
		if ($oid > 0) {
			$order = new WC_Order($oid);
			$resp['i'] = array();
			$resp['s'] = true;
			
			foreach ($order->get_items(array('line_item', 'fee')) as $item_id => $item) {
				$out = '';
				ob_start();

				$class = apply_filters('woocommerce_admin_order_items_class', 'new_row', $item, $order);
				switch ($item['type']) {
					case 'line_item' :
						$_product = $order->get_product_from_item($item);
						$template = QSOT::is_wc_latest()
							? 'meta-boxes/views/html-order-item.php'
							: 'post-types/meta-boxes/views/html-order-item.php';
						include(apply_filters('qsot-woo-template', $template, 'admin'));
					break;
					case 'fee' :
						$template = QSOT::is_wc_latest()
							? 'meta-boxes/views/html-order-fee.php'
							: 'post-types/meta-boxes/views/html-order-fee.php';
						include(apply_filters('qsot-woo-template', $template, 'admin'));
					break;
					case 'shipping' :
						$template = QSOT::is_wc_latest()
							? 'meta-boxes/views/html-order-shipping.php'
							: 'post-types/meta-boxes/views/html-order-shipping.php';
						include(apply_filters('qsot-woo-template', $template, 'admin'));
					break;
				}
				do_action('woocommerce_order_item_'.$item['type'].'_html');

				$out = trim(ob_get_contents());
				ob_end_clean();
				$resp['i'][] = $out;
			}
		} else {
			$resp['e'][] = __('Invalid order number.','opentickets-community-edition');
		}

		return $resp;
	}

	public static function aaj_ts_update_ticket($resp, $data) {
		$resp['s'] = false;
		$resp['e'] = array();

		$oiid = $data['oiid'];
		$oid = $data['order_id'];
		if (self::_is_order_item($oiid) && $oid > 0) {
			$order = new WC_Order($oid);
			$items = $order->get_items();
			if (isset($items[$oiid.''])) {
				$item = $items[$oiid.''];
				$meta = array();

				$event_id = $data['eid'];

				$event = apply_filters('qsot-get-event', false, $event_id);
				if (is_object($event) && is_array($item)) {
					$where = array(
						'ticket_type_id' => $item['product_id'],
						'qty' => $item['qty'],
						'order_id' => $oid,
						'event_id' => $item['event_id'],
						'state' => array(self::$o->{'z.states.c'}, self::$o->{'z.states.o'}),
					);
					$change_to = array(
						'event_id' => $event_id,
					);
					$res = apply_filters('qsot-zoner-update-reservation', false, $where, $change_to);
					if ($res) {
						$meta['event_id'] = $event_id;
						wc_update_order_item_meta($oiid, '_event_id', $event_id);
					}

					do_action('qsot-ticket-selection-update-ticket-after-meta-update', $oiid, $item, $meta, $order);

					$resp['s'] = true;
					$resp['updated'] = $meta;
					$resp['data'] = $item;
					$resp['data']['__order_item_id'] = $oiid;
					$event->_edit_url = get_edit_post_link($event_id);
					$event->post_title = apply_filters('the_title', $event->post_title);
					$resp['event'] = $event;
				} else {
					$resp['e'] = __('Could not find the new event.','opentickets-community-edition');
				}
			} else {
				$resp['e'] = __('Could not find that order item on the order.','opentickets-community-edition');
			}
		} else {
			$resp['e'] = __('The order item does not appear to be valid.','opentickets-community-edition');
		}

		return $resp;
	}

	public static function add_tickets_button($order) {
		?><button type="button" class="button add-order-tickets" rel="add-tickets-btn"><?php _e( 'Add tickets','opentickets-community-edition' ); ?></button><?php
	}

	public static function oid_from_oiid($oid, $oiid, $force=false) {
		static $lookup = array();

		if ($force || !isset($lookup[$oiid.''])) {
			global $wpdb;

			$q = $wpdb->prepare('select order_id from '.$wpdb->prefix.'woocommerce_order_items where order_item_id = %d', $oiid);
			$lookup[$oiid.''] = $wpdb->get_var($q);
		}

		return $lookup[$oiid.''];
	}

	protected static function _is_order_item($oiid) {
		global $wpdb;

		$q = $wpdb->prepare('select order_item_id from '.$wpdb->prefix.'woocommerce_order_items where order_item_id = %d', $oiid);
		
		return (int)$wpdb->get_var($q);
	}

	public static function formatted_price($price) {
		$num_decimals = absint( get_option( 'woocommerce_price_num_decimals' ) );
		$currency = isset( $args['currency'] ) ? $args['currency'] : '';
		$currency_symbol = get_woocommerce_currency_symbol($currency);
		$decimal_sep = get_option( 'woocommerce_price_decimal_sep' );
		$thousands_sep = get_option( 'woocommerce_price_thousand_sep' );

		$price = apply_filters( 'raw_woocommerce_price', floatval( $price ) );
		$price = apply_filters( 'formatted_woocommerce_price', number_format( $price, $num_decimals, $decimal_sep, $thousands_sep ), $price, $num_decimals, $decimal_sep, $thousands_sep );

		if ( apply_filters( 'woocommerce_price_trim_zeros', true ) && $num_decimals > 0 ) {
			$price = wc_trim_zeros( $price );
		}

		$return = sprintf( get_woocommerce_price_format(), $currency_symbol, $price );

		return $return;
	}

	public static function add_ticket_product_type_option($list) {
		$list['ticket'] = array(
			'id' => '_ticket',
			'wrapper_class' => 'show_if_simple',
			'label' => __('Ticket', 'qsot'),
			'description' => sprintf( __('Allows this product to be assigned as a ticket, when configuring pricing on a seating chart, for the %s plugin.','opentickets-community-edition'), self::$o->product_name),
		);
	
		return $list;
	}

	public static function save_product_meta($post_id, $post) {
		$is_ticket = isset($_POST['_ticket']) ? 'yes' : 'no';
		update_post_meta($post_id, '_ticket', $is_ticket);
		// auto hide all tickets. they should not be purchasable without an event
		if ($is_ticket == 'yes')
			update_post_meta($post_id, '_visibility', 'hidden');
	}

	public static function tickets_dont_need_processing($is, $product, $order_id) {
		if (get_post_meta($product->id, '_ticket', true) == 'yes') $is = false;
		return $is;
	}
}

if (defined('ABSPATH') && function_exists('add_action')) {
	qsot_seat_pricing::pre_init();
}

endif;
