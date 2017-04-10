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
		if ( ! $this->is_wc3() ) {
			switch ( $key ) {
				case 'currency': return $order->get_order_currency(); break;
				case 'shipping_total': return $order->get_total_shipping(); break;
				default:
					return $order->$key;
				break;
			}
			return null;
		}

		// new methods
		$key = '_' !== $key{0} ? $key : substr( $key, 1 );
		if ( 'completed_date' === $key ) {
			return $order->get_date_completed() ? gmdate( 'Y-m-d H:i:s', $order->get_date_completed()->getOffsetTimestamp() ) : '';
		} elseif ( 'shipping_total' == $key ) {
			return $order->get_shipping_total();
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

	// get a piece of data from an order item
	public function order_item_data( $item, $key ) {
		// if not wc3, then try legacy methods
		if ( ! $this->is_wc3() ) {
			$value = null;
			switch ( $key ) {
				case 'product': $value = wc_get_product( $item['variation_id'] ? $item['variation_id'] : $item['product_id'] ); break;
				case 'quantity': $value = $item['qty']; break;
				case 'taxes': $value = $item['line_tax_data']; break;
				default:
					if ( isset( $item[ $key ] ) )
						$value = $item[ $key ];
					else if ( isset( $item[ '-' . $key ] ) )
						$value = $item[ '_' . $key ];
					else if ( isset( $item[ 'line_' . $key ] ) )
						$value = $item[ 'line_' . $key ];
					else
						$value = null;
				break;
			}
			return apply_filters( 'qsot-wc2-order-item-data', $value, $item, $key );
		}

		// otherwise, try new methods
		switch ( $key ) {
			case 'type': return $item->get_type(); break;
			case 'product': return $item->get_product(); break;
			default:
				if ( is_callable( array( &$item, 'get_' . $key ) ) )
					return apply_filters( 'qsot-wc3-order-item-data', call_user_func( array( &$item, 'get_' . $key ) ), $item, $key );
				else
					return apply_filters( 'qsot-wc3-order-item-data-not-found', null, $item, $key );
			break;
		}

		return null;
	}

	// get the 'formatted meta data' like in wc3
	public function order_item_formatted_meta_data( $item, $hideprefix = '_' ) {
		// if alreayd wc3, use the built in function
		if ( $this->is_wc3() )
			return $item->get_formatted_meta_data( $hideprefix );

		// otherwise, emulate
		$formatted_meta    = array();
		$meta_data         = $item['item_meta_array'];
		$hideprefix_length = ! empty( $hideprefix ) ? strlen( $hideprefix ) : 0;
		$product           = isset( $item['product_id'], $item['variation_id'] ) ? wc_get_product( $item['variation_id'] ? $item['variation_id'] : $item['product_id'] ) : null;

		foreach ( $meta_data as $meta_id => $meta ) {
			if ( empty( $meta_id ) || "" === $meta->value || is_array( $meta->value ) || ( $hideprefix_length && substr( $meta->key, 0, $hideprefix_length ) === $hideprefix ) ) {
				continue;
			}

			$meta->key     = rawurldecode( (string) $meta->key );
			$meta->value   = rawurldecode( (string) $meta->value );
			$attribute_key = str_replace( 'attribute_', '', $meta->key );
			$display_key   = wc_attribute_label( $attribute_key, $product );
			$display_value = $meta->value;

			if ( taxonomy_exists( $attribute_key ) ) {
				$term = get_term_by( 'slug', $meta->value, $attribute_key );
				if ( ! is_wp_error( $term ) && is_object( $term ) && $term->name ) {
					$display_value = $term->name;
				}
			}

			// Skip items with values already in the product details area of the product name
			$value_in_product_name_regex = "/&ndash;.*" . preg_quote( $display_value, '/' ) . "/i";

			if ( $product && preg_match( $value_in_product_name_regex, $this->product_data( $product, 'name' ) ) ) {
				continue;
			}

			$formatted_meta[ $meta_id ] = (object) array(
				'key'           => $meta->key,
				'value'         => $meta->value,
				'display_key'   => apply_filters( 'woocommerce_order_item_display_meta_key', $display_key ),
				'display_value' => wpautop( make_clickable( apply_filters( 'woocommerce_order_item_display_meta_value', $display_value ) ) ),
			);
		}

		return $formatted_meta;
	}

	// get a piece of data from a product
	public function product_data( $product, $key ) {
		// if is wc3, use the function
		if ( $this->is_wc3() ) {
			return call_user_func( array( &$product, 'get_' . $key ) );
		}

		// otherwise figure out the correct response
		switch ( $key ) {
			case 'name': return apply_filters( 'the_title', $product->post->post_title, $product->id ); break;
			default:
				if ( isset( $product->post->$key ) )
					return $product->post->$key;
				else if ( isset( $product->$key ) )
					return $product->$key;
				else if ( isset( $product->{ '_' . $key } ) )
					return $product->{ '_' . $key };
				else
					return null;
			break;
		}

		return null;
	}
}

// public access function
function QSOT_WC3() { return QSOT_WC3_Sigh::instance(); }

// security
if ( defined( 'ABSPATH' ) && function_exists( 'add_action' ) )
	$GLOBALS['QSOT_WC3'] = QSOT_WC3();
