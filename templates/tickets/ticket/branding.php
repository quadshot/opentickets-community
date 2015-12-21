<?php if ( __FILE__ == $_SERVER['SCRIPT_FILENAME'] ) die( header( 'Location: /') ); ?>
<table class="branding">
	<tbody>
		<tr>

			<?php for ( $i = 0; $i < 5; $i++ ): ?>
				<td valign="bottom"><?php echo force_balance_tags( $brand_imgs[ $i ] ) ?></td>
			<?php endfor; ?>

			<td valign="bottom"><a href="<?php echo esc_attr( QSOT::product_url() ) ?>" title="<?php _e('Who is OpenTickets?','opentickets-community-edition') ?>">
				<img src="<?php echo esc_attr( QSOT::plugin_url() . 'assets/imgs/opentickets-tiny.jpg' ) ?>" class="ot-tiny-logo branding-img" />
			</a></td>

		</tr>
	</tbody>

</table>

<div class="clear"></div>
