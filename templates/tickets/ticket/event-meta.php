<?php if ( __FILE__ == $_SERVER['SCRIPT_FILENAME'] ) die( header( 'Location: /') );
	$start_time = QSOT_Utils::local_timestamp( $ticket->event->meta->start );
	$end_time = QSOT_Utils::local_timestamp( $ticket->event->meta->end );
	$same_day = strtotime( 'today', $start_time ) == strtotime( 'today', $end_time );
?>
<ul>
	<li><h2><?php echo $ticket->event->parent_post_title ?></h2></li>
	<li>
		<span class="label"><?php _e( 'Starts:', 'opentickets-community-edition' ) ?></span>
		<span class="value"><?php echo date( __( 'D, F jS, Y', 'opentickets-community-edition' ), $start_time ), __( ' @ ', 'opentickets-community-edition' ), date( __( 'g:ia', 'opentickets-community-edition' ), $start_time ) ?></span>
	</li>
	<li>
		<span class="label"><?php _e( 'Ends:', 'opentickets-community-edition' ) ?></span>
		<?php if ( $same_day ): ?>
			<span class="value"><?php echo __( ' @ ', 'opentickets-community-edition' ), date( __( 'g:ia', 'opentickets-community-edition' ), $end_time ) ?></span>
		<?php else: ?>
			<span class="value"><?php echo date( __( 'D, F jS, Y', 'opentickets-community-edition' ), $end_time ), __( ' @ ', 'opentickets-community-edition' ), date( __( 'g:ia', 'opentickets-community-edition' ), $end_time ) ?></span>
		<?php endif; ?>
	</li>
	<li>
		<span class="label"><?php _e( 'Area:', 'opentickets-community-edition' ) ?></span>
		<span class="value"><?php echo apply_filters( 'the_title', $ticket->event_area->post_title, $ticket->event_area->ID ) ?></span>
	</li>
</ul>
