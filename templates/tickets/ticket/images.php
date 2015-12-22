<?php if ( __FILE__ == $_SERVER['SCRIPT_FILENAME'] ) die( header( 'Location: /') );
	$left = wp_get_attachment_image( $ticket->image_id_left, array( 225, 9999 ) );
	$left = ! empty( $left ) ? $left : '<div class="faux-image left"><div>';
	$right = wp_get_attachment_image( $ticket->image_id_right, array( 225, 9999 ) );
	$right = ! empty( $right ) ? $right : '<div class="faux-image right"><div>';
?>

<td class="event-image"><?php echo force_balance_tags( $left ) ?></td>

<td class="venue-image"><?php echo force_balance_tags( $right ) ?></td>
