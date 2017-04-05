<?php if ( __FILE__ == $_SERVER['SCRIPT_FILENAME'] ) die( header( 'Location: /') );

// woocommerce 3.0 fucked us. we have to work around all their undocumented, sudden, kneejerk, pointless changes
class QSOT_WC3_Sigh {
	// make this a singleton
	protected static $_instance = null;
	public static function instance() { return ( self::$_instance instanceof self ) ? self::$_instance : ( self::$_instance = new self ); }
	public function __construct() {
	}

	public $wc3 = null;
	// check if this is WC3 or higher
	public function is_wc3() {
		if ( null === $this->wc3 )
			$this->wc3 = defined( 'WC_VERSION' ) && version_compare( '3.0.0', WC_VERSION ) <= 0;
		return $this->wc3;
	}

	// emergency override, to translate new order_item format into something coherent we can use for legacy code
	public function order_item( $item ) {
		// if the item is in the new format, translate it to the old format
		if ( $this->is_wc3() && $item instanceof WC_Data ) {
			$item_class = get_class( $item );
			// get the data
			$data = $item->get_data();

			// transform all the data
			foreach ( $data['meta_data'] as $meta ) {
				$key = '_' == $meta->key{0} ? substr( $meta->key, 1 ) : $meta->key;
				if ( ! isset( $data[ $key ] ) ) {
					$data[ $key ] = $meta->value;
				}
			}

			// map legacy keys
			$data['qty'] = $data['quantity'];
			$data['type'] = strtolower( preg_replace( '#^wc_order_item_(.*?)$#i', '$1', $item_class ) );

			$item = $data;
		}

		return $item;
	}

	// mergency override to get the order_id from an order, since wc unwittingly changed it to only be accessible via function in wc3
	public function order_id( $order ) {
		if ( $this->is_wc3() )
			return $order->get_id();

		return $order->id;
	}

	// get a piece of order data
	public function order_data( $order, $key ) {
		// legacy
		if ( ! $this->is_wc3() )
			return $order->$key;

		// new methods
		$key = '_' !== $key{0} ? $key : substr( $key, 1 );
		if ( 'completed_date' === $key ) {
			return $order->get_date_completed() ? gmdate( 'Y-m-d H:i:s', $order->get_date_completed()->getOffsetTimestamp() ) : '';
		} elseif ( 'paid_date' === $key ) {
			return $order->get_date_paid() ? gmdate( 'Y-m-d H:i:s', $order->get_date_paid()->getOffsetTimestamp() ) : '';
		} elseif ( 'modified_date' === $key ) {
			return $order->get_date_modified() ? gmdate( 'Y-m-d H:i:s', $order->get_date_modified()->getOffsetTimestamp() ) : '';
		} elseif ( 'order_date' === $key ) {
			return $order->get_date_created() ? gmdate( 'Y-m-d H:i:s', $order->get_date_created()->getOffsetTimestamp() ) : '';
		} elseif ( 'id' === $key ) {
			return $order->get_id();
		} elseif ( 'post' === $key ) {
			return get_post( $order->get_id() );
		} elseif ( 'status' === $key ) {
			return $order->get_status();
		} elseif ( 'post_status' === $key ) {
			return get_post_status( $order->get_id() );
		} elseif ( 'customer_message' === $key || 'customer_note' === $key ) {
			return $order->get_customer_note();
		} elseif ( in_array( $key, array( 'user_id', 'customer_user' ) ) ) {
			return $order->get_customer_id();
		} elseif ( 'tax_display_cart' === $key ) {
			return get_option( 'woocommerce_tax_display_cart' );
		} elseif ( 'display_totals_ex_tax' === $key ) {
			return 'excl' === get_option( 'woocommerce_tax_display_cart' );
		} elseif ( 'display_cart_ex_tax' === $key ) {
			return 'excl' === get_option( 'woocommerce_tax_display_cart' );
		} elseif ( 'cart_discount' === $key ) {
			return $order->get_total_discount();
		} elseif ( 'cart_discount_tax' === $key ) {
			return $order->get_discount_tax();
		} elseif ( 'order_tax' === $key ) {
			return $order->get_cart_tax();
		} elseif ( 'order_shipping_tax' === $key ) {
			return $order->get_shipping_tax();
		} elseif ( 'order_shipping' === $key ) {
			return $order->get_shipping_total();
		} elseif ( 'order_total' === $key ) {
			return $order->get_total();
		} elseif ( 'order_type' === $key ) {
			return $order->get_type();
		} elseif ( 'order_currency' === $key ) {
			return $order->get_currency();
		} elseif ( 'order_version' === $key ) {
			return $order->get_version();
	 	} elseif ( is_callable( array( $order, "get_{$key}" ) ) ) {
			return $order->{"get_{$key}"}();
		} else {
			return get_post_meta( $order->get_id(), '_' . $key, true );
		}
	}
}

// public access function
function QSOT_WC3() { return QSOT_WC3_Sigh::instance(); }
