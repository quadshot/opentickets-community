<?php
/**
 * Order Data
 *
 * Functions for displaying the order items meta box.
 *
 * @author      WooThemes / Quadshot
 * @category    Admin
 * @package     WooCommerce/Admin/Meta Boxes
 * @version     2.1.0 / 1.5
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

qsot_underload_core_class('/includes/admin/meta-boxes/class-wc-meta-box-order-items.php');

class WC_Meta_Box_Order_Items extends _WooCommerce_Core_WC_Meta_Box_Order_Items {

	/**
	 * Output the metabox
	 */
	public static function output( $post ) {
		global $thepostid, $theorder;

		if ( ! is_object( $theorder ) ) {
			$theorder = wc_get_order( $thepostid );
		}

		$order = $theorder;
		$data  = get_post_meta( $post->ID );

		//include( 'views/html-order-items.php' );
		//@@@@LOUSHOU - allow overtake of template
		if ( $template = QSOT_Templates::locate_woo_template( 'meta-boxes/views/html-order-items.php', 'admin' ) )
			include( $template );
	}

	/**
	 * Save meta box data
	 */
	public static function save( $post_id, $post ) {
		wc_save_order_items( $post_id, $_POST );

		// tell plugins order items were saved
		do_action( 'woocommerce_saved_order_items', $post_id, $_POST );
	}

}
