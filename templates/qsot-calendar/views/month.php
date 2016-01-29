<?php if ( __FILE__ == $_SERVER['SCRIPT_FILENAME'] ) die( header( 'Location: /') ); ?>
<div class="event-item">
	<div class="heading">
		<span class="fc-title"></span>
	</div>
	<div class="meta">
		<div data-format="<?php echo esc_attr( __( 'h:mma', 'opentickets-community-edition' ) ) ?>" class="fc-time"></div>
		<div class="fc-availability">
			<span class="words"></span>
			<span class="num"></span>
		</div>
	</div>
	<?php if ( 'yes' === apply_filters( 'qsot-get-option-value', 'no', 'qsot-calendar-show-image-on-month' ) ): ?>
		<div class="fc-img"></div>
	<?php endif; ?>
</div>
