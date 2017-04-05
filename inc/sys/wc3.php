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
			// get the data
			$data = $item->get_data();

			// transform all the data
			foreach ( $data['meta_data'] as $meta ) {
				$key = '_' == $meta->key{0} ? substr( $meta->key, 1 ) : $meta->key;
				if ( ! isset( $data[ $key ] ) ) {
					$data[ $key ] = $meta->value;
				}
			}

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
}

// public access function
function QSOT_WC3() { return QSOT_WC3_Sigh::instance(); }
