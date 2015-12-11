<?php if ( __FILE__ == $_SERVER['SCRIPT_FILENAME'] ) die( header( 'Location: /') ); ?>
<ul>

	<?php if ( $ticket->show_order_number ): ?>
		<li><?php echo sprintf( __( 'Order #%d', 'opentickets-community-edition' ), $ticket->order->id ) ?></li>
	<?php endif; ?>

	<li><?php echo ucwords( implode( ' ', $ticket->names ) ) ?></li>

	<li><?php echo $ticket->product->get_title() ?></li>

	<?php if ( $ticket->order_item['qty'] > 1 ): ?>
		<li>[<?php echo sprintf( __( '%1$s of %2$s', 'opentickets-community-edition' ), $index + 1, $ticket->order_item['qty'] ) ?>]</li>
	<?php endif; ?>

	<li>(<?php echo $ticket->product->get_price_html() ?>)</li>

	<?php do_action( 'qsot-ticket-information', $ticket, $multiple ); ?>

</ul>
