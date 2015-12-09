<?php if ( __FILE__ == $_SERVER['SCRIPT_FILENAME'] ) die( header( 'Location: /') ); ?>
<div class="action-list" rel="btns">
	<input type="button" class="button" rel="change-btn" value="<?php echo esc_attr( __( 'Different Event', 'opentickets-community-edition' ) ) ?>"/>
	<input type="button" class="button" rel="use-btn" value="<?php echo esc_attr( __( 'Use This Event', 'opentickets-community-edition' ) ) ?>"/>
</div>
