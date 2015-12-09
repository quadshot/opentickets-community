<?php if ( __FILE__ == $_SERVER['SCRIPT_FILENAME'] ) die( header( 'Location: /') ); ?>
<div class="ts-section info-wrap" rel="wrap">
	<div class="row"><span class="label"><?php _e( 'Event:', 'opentickets-community-edition' ) ?></span> <span class="value event-name" rel="name"></span></div>
	<div class="row"><span class="label"><?php _e( 'Date:', 'opentickets-community-edition' ) ?></span> <span class="value event-date" rel="date"></span></div>
	<div class="event-capacity" rel="capacity">
		<span class="field"><span class="label"><?php _e( 'Capacity:', 'opentickets-community-edition' ) ?></span> <span class="value total-capacity" rel="total"></span></span>
		<span class="field"><span class="label"><?php _e( 'Available:', 'opentickets-community-edition' ) ?></span> <span class="value available" rel="available"></span></span>
	</div>
</div>
