<?php if ( __FILE__ == $_SERVER['SCRIPT_FILENAME'] ) die( header( 'Location: /') ); ?>
<div class="add-tickets-ui" rel="add-ui">
	<div class="ticket-form ts-section">
		<span class="ticket-name" rel="ttname"></span>
		<input type="number" min="1" max="100000" step="1" rel="ticket-count" name="qty" value="1" />
		<input type="button" class="button" rel="add-btn" value="<?php echo esc_attr( __( 'Add Tickets', 'opentickets-community-edition' ) ) ?>" />
	</div>
	<div class="image-wrap" rel="image-wrap"></div>
</div>
