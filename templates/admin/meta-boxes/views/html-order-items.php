<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

global $wpdb;

$post = isset( $post ) && is_object( $post ) ? $post : get_post( $order->id );
// Get the payment gateway
$payment_gateway = wc_get_payment_gateway_by_order( $order );

// Get line items
$line_items          = $order->get_items( apply_filters( 'woocommerce_admin_order_item_types', 'line_item' ) );
$line_items_fee      = $order->get_items( 'fee' );
$line_items_shipping = $order->get_items( 'shipping' );

if ( 'yes' == get_option( 'woocommerce_calc_taxes' ) ) {
	$order_taxes         = $order->get_taxes();
	$tax_classes         = array_filter( array_map( 'trim', explode( "\n", get_option( 'woocommerce_tax_classes' ) ) ) );
	$classes_options     = array();
	$classes_options[''] = __( 'Standard', 'qsot' );

	if ( $tax_classes ) {
		foreach ( $tax_classes as $class ) {
			$classes_options[ sanitize_title( $class ) ] = $class;
		}
	}

	// Older orders won't have line taxes so we need to handle them differently :(
	$tax_data = '';
	if ( $line_items ) {
		$check_item = current( $line_items );
		$tax_data   = maybe_unserialize( isset( $check_item['line_tax_data'] ) ? $check_item['line_tax_data'] : '' );
	} elseif ( $line_items_shipping ) {
		$check_item = current( $line_items_shipping );
		$tax_data = maybe_unserialize( isset( $check_item['taxes'] ) ? $check_item['taxes'] : '' );
	} elseif ( $line_items_fee ) {
		$check_item = current( $line_items_fee );
		$tax_data   = maybe_unserialize( isset( $check_item['line_tax_data'] ) ? $check_item['line_tax_data'] : '' );
	}

	$legacy_order     = ! empty( $order_taxes ) && empty( $tax_data ) && ! is_array( $tax_data );
	$show_tax_columns = ! $legacy_order || sizeof( $order_taxes ) === 1;
}
?>
<div class="woocommerce_order_items_wrapper wc-order-items-editable">
	<?php do_action('woocommerce_admin_before_order_items', $post, $order, $data); ?>
	<table cellpadding="0" cellspacing="0" class="woocommerce_order_items">
		<thead>
			<tr>
				<th><input type="checkbox" class="check-column" /></th>
				<th class="item" colspan="2"><?php _e( 'Item', 'qsot' ); ?></th>

				<?php do_action( 'woocommerce_admin_order_item_headers' ); ?>

				<th class="quantity"><?php _e( 'Qty', 'qsot' ); ?></th>

				<th class="line_cost"><?php _e( 'Total', 'qsot' ); ?></th>

				<?php
					if ( isset( $legacy_order ) && ! $legacy_order && 'yes' == get_option( 'woocommerce_calc_taxes' ) ) :
						foreach ( $order_taxes as $tax_id => $tax_item ) :
							$tax_class      = wc_get_tax_class_by_tax_id( $tax_item['rate_id'] );
							$tax_class_name = isset( $classes_options[ $tax_class ] ) ? $classes_options[ $tax_class ] : __( 'Tax', 'qsot' );
							$column_label   = ! empty( $tax_item['label'] ) ? $tax_item['label'] : __( 'Tax', 'qsot' );
							?>
								<th class="line_tax tips" data-tip="<?php
										echo esc_attr( $tax_item['name'] . ' (' . $tax_class_name . ')' );
									?>">
									<?php echo esc_attr( $column_label ); ?>
									<input type="hidden" class="order-tax-id" name="order_taxes[<?php echo $tax_id; ?>]" value="<?php echo esc_attr( $tax_item['rate_id'] ); ?>">
									<a class="delete-order-tax" href="#" data-rate_id="<?php echo $tax_id; ?>"></a>
								</th>
							<?php
						endforeach;
					endif;
				?>

				<?php do_action( 'woocommerce_admin_after_order_item_headers' ); /*@@@@LOUSHOU - allow addition of columns to the end of the list */ ?>
				<th class="wc-order-edit-line-item" width="1%">&nbsp;</th>
			</tr>
		</thead>
		<tbody id="order_line_items">
		<?php
			foreach ( $line_items as $item_id => $item ) {
				$_product  = $order->get_product_from_item( $item );
				$item_meta = $order->get_item_meta( $item_id );

				//include( 'html-order-item.php' );
				//@@@@LOUSHOU - allow overtake of template
				include(apply_filters('qsot-woo-template', 'meta-boxes/views/html-order-item.php', 'admin'));

				do_action( 'woocommerce_order_item_' . $item['type'] . '_html', $item_id, $item );
			}
		?>
		</tbody>
		<tbody id="order_shipping_line_items">
		<?php
			$shipping_methods = WC()->shipping() ? WC()->shipping->load_shipping_methods() : array();
			foreach ( $line_items_shipping as $item_id => $item ) {
				//include( 'html-order-shipping.php' );
				//@@@@LOUSHOU - allow overtake of template
				include(apply_filters('qsot-woo-template', 'meta-boxes/views/html-order-shipping.php', 'admin'));
			}
		?>
		</tbody>
		<tbody id="order_fee_line_items">
		<?php
			foreach ( $line_items_fee as $item_id => $item ) {
				//include( 'html-order-fee.php' );
				//@@@@LOUSHOU - allow overtake of template
				include(apply_filters('qsot-woo-template', 'meta-boxes/views/html-order-fee.php', 'admin'));
			}
		?>
		</tbody>
		<tbody id="order_refunds">
		<?php
			if ( $refunds = $order->get_refunds() ) {
				foreach ( $refunds as $refund ) {
					//include( 'html-order-refund.php' );
					//@@@@LOUSHOU - allow overtake of template
					include(apply_filters('qsot-woo-template', 'meta-boxes/views/html-order-refund.php', 'admin'));
				}
			}
		?>
		</tbody>
	</table>
</div>
<div class="wc-order-data-row wc-order-totals-items wc-order-items-editable">
	<?php
		$coupons = $order->get_items( array( 'coupon' ) );
		if ( $coupons ) {
			?>
			<div class="wc-used-coupons">
				<ul class="wc_coupon_list"><?php
					foreach ( $coupons as $item_id => $item ) {
						$post_id = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_title = %s AND post_type = 'shop_coupon' AND post_status = 'publish' LIMIT 1;", $item['name'] ) );

						$link = $post_id ? add_query_arg( array( 'post' => $post_id, 'action' => 'edit' ), admin_url( 'post.php' ) ) : add_query_arg( array( 's' => $item['name'], 'post_status' => 'all', 'post_type' => 'shop_coupon' ), admin_url( 'edit.php' ) );

						echo '<li class="tips code" data-tip="' . esc_attr( wc_price( $item['discount_amount'] ) ) . '"><a href="' . esc_url( $link ) . '"><span>' . esc_html( $item['name'] ). '</span></a></li>';
					}
				?></ul>
			</div>
			<?php
		}
	?>
	<table class="wc-order-totals">
		<tr>
			<td class="label"><?php _e( 'Shipping', 'qsot' ); ?> <span class="tips" data-tip="<?php _e( 'This is the shipping and handling total costs for the order.', 'qsot' ); ?>">[?]</span>:</td>
			<td class="total"><?php echo wc_price( $order->get_total_shipping() ); ?></td>
			<td width="1%"></td>
		</tr>

		<?php do_action( 'woocommerce_admin_order_totals_after_shipping', $order->id ); ?>

		<?php if ( 'yes' == get_option( 'woocommerce_calc_taxes' ) ) : ?>
			<?php foreach ( $order->get_tax_totals() as $code => $tax ) : ?>
				<tr>
					<td class="label"><?php echo $tax->label; ?>:</td>
					<td class="total"><?php echo $tax->formatted_amount; ?></td>
					<td width="1%"></td>
				</tr>
			<?php endforeach; ?>
		<?php endif; ?>

		<?php do_action( 'woocommerce_admin_order_totals_after_tax', $order->id ); ?>

		<tr>
			<td class="label"><?php _e( 'Order Discount', 'qsot' ); ?> <span class="tips" data-tip="<?php _e( 'This is the total discount applied after tax.', 'qsot' ); ?>">[?]</span>:</td>
			<td class="total">
				<div class="view"><?php echo wc_price( $order->get_total_discount() ); ?></div>
				<div class="edit" style="display: none;">
					<input type="text" class="wc_input_price" id="_order_discount" name="_order_discount" placeholder="<?php echo wc_format_localized_price( 0 ); ?>" value="<?php echo ( isset( $data['_order_discount'][0] ) ) ? esc_attr( wc_format_localized_price( $data['_order_discount'][0] ) ) : ''; ?>" />
					<div class="clear"></div>
				</div>
			</td>
			<td><?php if ( $order->is_editable() ) : ?><div class="wc-order-edit-line-item-actions"><a class="edit-order-item" href="#"></a></div><?php endif; ?></td>
		</tr>

		<?php do_action( 'woocommerce_admin_order_totals_after_discount', $order->id ); ?>

		<tr>
			<td class="label"><?php _e( 'Order Total', 'qsot' ); ?>:</td>
			<td class="total">
				<div class="view"><?php echo wc_price( $order->get_total() ); ?></div>
				<div class="edit" style="display: none;">
					<input type="text" class="wc_input_price" id="_order_total" name="_order_total" placeholder="<?php echo wc_format_localized_price( 0 ); ?>" value="<?php echo ( isset( $data['_order_total'][0] ) ) ? esc_attr( wc_format_localized_price( $data['_order_total'][0] ) ) : ''; ?>" />
					<div class="clear"></div>
				</div>
			</td>
			<td><?php if ( $order->is_editable() ) : ?><div class="wc-order-edit-line-item-actions"><a class="edit-order-item" href="#"></a></div><?php endif; ?></td>
		</tr>

		<?php do_action( 'woocommerce_admin_order_totals_after_total', $order->id ); ?>

		<tr>
			<td class="label refunded-total"><?php _e( 'Refunded', 'qsot' ); ?>:</td>
			<td class="total refunded-total">-<?php echo wc_price( $order->get_total_refunded() ); ?></td>
			<td width="1%"></td>
		</tr>

		<?php do_action( 'woocommerce_admin_order_totals_after_refunded', $order->id ); ?>

	</table>
	<div class="clear"></div>
</div>
<div class="wc-order-data-row wc-order-bulk-actions">
	<p class="bulk-actions">
		<select>
			<option value=""><?php _e( 'Actions', 'qsot' ); ?></option>
			<?php if ( $order->is_editable() ) : ?>
				<optgroup label="<?php _e( 'Edit', 'qsot' ); ?>">
					<option value="delete"><?php _e( 'Delete selected line item(s)', 'qsot' ); ?></option>
				</optgroup>
			<?php endif; ?>
			<optgroup label="<?php _e( 'Stock Actions', 'qsot' ); ?>">
				<option value="reduce_stock"><?php _e( 'Reduce line item stock', 'qsot' ); ?></option>
				<option value="increase_stock"><?php _e( 'Increase line item stock', 'qsot' ); ?></option>
			</optgroup>
		</select>

		<button type="button" class="button do_bulk_action wc-reload" title="<?php _e( 'Apply', 'qsot' ); ?>"><span><?php _e( 'Apply', 'qsot' ); ?></span></button>
	</p>
	<p class="add-items">
		<?php if ( $order->is_editable() ) : ?>
			<button type="button" class="button add-line-item"><?php _e( 'Add line item(s)', 'qsot' ); ?></button>
		<?php endif; ?>
		<?php if ( 'yes' == get_option( 'woocommerce_calc_taxes' ) && $order->is_editable() ) : ?>
			<button type="button" class="button add-order-tax"><?php _e( 'Add Tax', 'qsot' ); ?></button>
		<?php endif; ?>
		<?php if ( ( $order->get_total() - $order->get_total_refunded() ) > 0 ) : ?>
			<button type="button" class="button refund-items"><?php _e( 'Refund', 'qsot' ); ?></button>
		<?php endif; ?>
		<?php
			// allow other plugins to add buttons here
			do_action( 'woocommerce_order_item_add_action_buttons', $order );
		?>
		<?php if ( $order->is_editable() ) : ?>
		<button type="button" class="button button-primary calculate-tax-action"><?php _e( 'Calculate Taxes', 'qsot' ); ?></button>
		<button type="button" class="button button-primary calculate-action"><?php _e( 'Calculate Total', 'qsot' ); ?></button>
		<?php endif; ?>
	</p>
</div>
<div class="wc-order-data-row wc-order-add-item" style="display:none;">
	<button type="button" class="button add-order-item"><?php _e( 'Add product(s)', 'qsot' ); ?></button>
	<button type="button" class="button add-order-fee"><?php _e( 'Add fee', 'qsot' ); ?></button>
	<button type="button" class="button add-order-shipping"><?php _e( 'Add shipping cost', 'qsot' ); ?></button>
	<button type="button" class="button cancel-action"><?php _e( 'Cancel', 'qsot' ); ?></button>
	<button type="button" class="button button-primary save-action"><?php _e( 'Save', 'qsot' ); ?></button>
	<?php
		// allow other plugins to add buttons here
		do_action( 'woocommerce_order_item_add_line_buttons', $order );
	?>
</div>
<?php if ( ( $order->get_total() - $order->get_total_refunded() ) > 0 ) : ?>
<div class="wc-order-data-row wc-order-refund-items" style="display: none;">
	<table class="wc-order-totals">
		<tr>
			<td class="label"><label for="restock_refunded_items"><?php _e( 'Restock refunded items', 'qsot' ); ?>:</label></td>
			<td class="total"><input type="checkbox" id="restock_refunded_items" name="restock_refunded_items" checked="checked" /></td>
		</tr>
		<tr>
			<td class="label"><?php _e( 'Amount already refunded', 'qsot' ); ?>:</td>
			<td class="total">-<?php echo wc_price( $order->get_total_refunded() ); ?></td>
		</tr>
		<tr>
			<td class="label"><?php _e( 'Total available to refund', 'qsot' ); ?>:</td>
			<td class="total"><?php echo wc_price( $order->get_total() - $order->get_total_refunded() ); ?></td>
		</tr>
		<tr>
			<td class="label"><label for="refund_amount"><?php _e( 'Refund amount', 'qsot' ); ?>:</label></td>
			<td class="total">
				<input type="text" class="text" id="refund_amount" name="refund_amount" class="wc_input_price" />
				<div class="clear"></div>
			</td>
		</tr>
		<tr>
			<td class="label"><label for="refund_reason"><?php _e( 'Reason for refund (optional)', 'qsot' ); ?>:</label></td>
			<td class="total">
				<input type="text" class="text" id="refund_reason" name="refund_reason" />
				<div class="clear"></div>
			</td>
		</tr>
	</table>
	<div class="clear"></div>
	<div class="refund-actions">
		<?php if ( false !== $payment_gateway && $payment_gateway->supports( 'refunds' ) ) : ?>
		<button type="button" class="button button-primary do-api-refund"><?php printf( _x( 'Refund %s via %s', 'Refund $amount', 'qsot' ), '<span class="wc-order-refund-amount">' . wc_price( 0 ) . '</span>', $order->payment_method_title ); ?></button>
		<?php endif; ?>
		<button type="button" class="button button-primary do-manual-refund"><?php _e( 'Refund manually', 'qsot' ); ?></button>
		<button type="button" class="button cancel-action"><?php _e( 'Cancel', 'qsot' ); ?></button>
		<div class="clear"></div>
	</div>
</div>
<?php endif; ?>

<script type="text/template" id="wc-modal-add-products">
	<div class="wc-backbone-modal">
		<div class="wc-backbone-modal-content">
			<section class="wc-backbone-modal-main" role="main">
				<header>
					<h1><?php echo __( 'Add products', 'qsot' ); ?></h1>
				</header>
				<article>
					<form action="" method="post">
						<select id="add_item_id" class="ajax_chosen_select_products_and_variations" multiple="multiple" data-placeholder="<?php _e( 'Search for a product&hellip;', 'qsot' ); ?>" style="width: 96%;"></select>
					</form>
				</article>
				<footer>
					<div class="inner">
						<button id="btn-cancel" class="button button-large"><?php echo __( 'Cancel' , 'qsot' ); ?></button>
						<button id="btn-ok" class="button button-primary button-large"><?php echo __( 'Add' , 'qsot' ); ?></button>
					</div>
				</footer>
			</section>
		</div>
	</div>
	<div class="wc-backbone-modal-backdrop">&nbsp;</div>
</script>

<script type="text/template" id="wc-modal-add-tax">
	<div class="wc-backbone-modal">
		<div class="wc-backbone-modal-content">
			<section class="wc-backbone-modal-main" role="main">
				<header>
					<h1><?php _e( 'Add tax', 'qsot' ); ?></h1>
				</header>
				<article>
					<form action="" method="post">
						<table class="widefat">
							<thead>
								<tr>
									<th>&nbsp;</th>
									<th><?php _e( 'Rate name', 'qsot' ); ?></th>
									<th><?php _e( 'Tax class', 'qsot' ); ?></th>
									<th><?php _e( 'Rate code', 'qsot' ); ?></th>
									<th><?php _e( 'Rate %', 'qsot' ); ?></th>
								</tr>
							</thead>
						<?php
							$rates = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}woocommerce_tax_rates ORDER BY tax_rate_name LIMIT 100" );

							foreach ( $rates as $rate ) {
								echo '
									<tr>
										<td><input type="radio" id="add_order_tax_' . absint( $rate->tax_rate_id ) . '" name="add_order_tax" value="' . absint( $rate->tax_rate_id ) . '" /></td>
										<td><label for="add_order_tax_' . absint( $rate->tax_rate_id ) . '">' . WC_Tax::get_rate_label( $rate ) . '</label></td>
										<td>' . ( isset( $classes_options[ $rate->tax_rate_class ] ) ? $classes_options[ $rate->tax_rate_class ] : '-' ) . '</td>
										<td>' . WC_Tax::get_rate_code( $rate ) . '</td>
										<td>' . WC_Tax::get_rate_percent( $rate ) . '</td>
									</tr>
								';
							}
						?>
						</table>
						<?php if ( absint( $wpdb->get_var( "SELECT COUNT(tax_rate_id) FROM {$wpdb->prefix}woocommerce_tax_rates;" ) ) > 100 ) : ?>
							<p>
								<label for="manual_tax_rate_id"><?php _e( 'Or, enter tax rate ID:', 'qsot' ); ?></label><br/>
								<input type="number" name="manual_tax_rate_id" id="manual_tax_rate_id" step="1" placeholder="<?php _e( 'Optional', 'qsot' ); ?>" />
							</p>
						<?php endif; ?>
					</form>
				</article>
				<footer>
					<div class="inner">
						<button id="btn-cancel" class="button button-large"><?php echo __( 'Cancel' , 'qsot' ); ?></button>
						<button id="btn-ok" class="button button-primary button-large"><?php echo __( 'Add' , 'qsot' ); ?></button>
					</div>
				</footer>
			</section>
		</div>
	</div>
	<div class="wc-backbone-modal-backdrop">&nbsp;</div>
</script>

<?php do_action( 'woocommerce_order_items_extra_modals', $order ) ?>
