<?php
/**
 * Order Data
 *
 * Functions for displaying the order data meta box.
 *
 * @author 		WooThemes
 * @category 	Admin
 * @package 	WooCommerce/Admin/Meta Boxes
 * @version     2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( QSOT::is_wc_at_least( '2.3' ) ) { // in 2.3 we have enough hooks in the template ot not need an override here anymore
	return;
} else if ( QSOT::is_wc_at_least( '2.2' ) ) {
	qsot_underload_core_class('/includes/admin/meta-boxes/class-wc-meta-box-order-data.php');
} else {
	_deprecated_file(__FILE__, 'OTv1.9', '', 'We no longer need to overtake the WC_Meta_Box_Order_Data class, as of OTCE v1.9.');
	die();
}

if ( ! QSOT::is_wc_at_least( '2.3' ) ):
/**
 * WC_Meta_Box_Order_Data
 */
class WC_Meta_Box_Order_Data extends _WooCommerce_Core_WC_Meta_Box_Order_Data {
}

endif;
