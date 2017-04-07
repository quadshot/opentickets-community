<?php
global $QSOT_WC3;
$hidden_order_itemmeta = apply_filters( 'woocommerce_hidden_order_itemmeta', array(
	'_qty',
	'_tax_class',
	'_product_id',
	'_variation_id',
	'_line_subtotal',
	'_line_subtotal_tax',
	'_line_total',
	'_line_tax',
	'method_id',
	'cost',
) );
?><div class="view">
	<?php do_action('woocommerce_before_view_order_itemmeta', $item_id, $item, $product) /*@@@@LOUSHOU - filter for customizing meta */ ?>
	<?php if ( $meta_data = $QSOT_WC3->order_item_formatted_meta_data( $item, '_' ) ) : ?>
		<table cellspacing="0" class="display_meta">
			<?php foreach ( $meta_data as $meta_id => $meta ) :
				if ( in_array( $meta->key, $hidden_order_itemmeta ) ) {
					continue;
				}
				?>
				<tr>
					<th><?php echo wp_kses_post( $meta->display_key ); ?>:</th>
					<td><?php echo wp_kses_post( force_balance_tags( $meta->display_value ) ); ?></td>
				</tr>
			<?php endforeach; ?>
		</table>
	<?php endif; ?>
	<?php do_action('woocommerce_after_view_order_itemmeta', $item_id, $item, $product) /*@@@@LOUSHOU - filter for customizing meta */ ?>
</div>
<div class="edit" style="display: none;">
	<?php do_action('woocommerce_before_edit_order_itemmeta', $item_id, $item, $product, $order) /*@@@@LOUSHOU - filter for customizing meta */ ?>
	<table class="meta" cellspacing="0">
		<tbody class="meta_items">
			<?php if ( $meta_data = $QSOT_WC3->order_item_formatted_meta_data( $item, '_' ) ) : ?>
				<?php foreach ( $meta_data as $meta_id => $meta ) :
					if ( in_array( $meta->key, $hidden_order_itemmeta ) ) {
						continue;
					}
					?>
					<tr data-meta_id="<?php echo esc_attr( $meta_id ); ?>">
						<td>
							<input type="text" name="meta_key[<?php echo esc_attr( $item_id ); ?>][<?php echo esc_attr( $meta_id ); ?>]" value="<?php echo esc_attr( $meta->key ); ?>" />
							<textarea name="meta_value[<?php echo esc_attr( $item_id ); ?>][<?php echo esc_attr( $meta_id ); ?>]"><?php echo esc_textarea( rawurldecode( $meta->value ) ); ?></textarea>
						</td>
						<td width="1%"><button class="remove_order_item_meta button">&times;</button></td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
		<tfoot>
			<tr>
				<td colspan="4"><button class="add_order_item_meta button"><?php _e( 'Add&nbsp;meta', 'woocommerce' ); ?></button></td>
			</tr>
		</tfoot>
	</table>
</div>
