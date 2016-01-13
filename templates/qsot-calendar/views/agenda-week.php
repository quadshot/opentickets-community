<?php if ( __FILE__ == $_SERVER['SCRIPT_FILENAME'] ) die( header( 'Location: /') ); ?>
<div class="event-item">
	<div class="heading fc-content">
		<div data-format="<?php echo esc_attr( __( 'h:mma', 'opentickets-community-edition' ) ) ?>" class="fc-time"></div>
		<div class="fc-title"></div>
	</div>
	<div class="meta">
		<div class="fc-availability">
			<span class="words"></span>
			<span class="num"></span>
		</div>
	</div>
</div>
