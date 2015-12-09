<?php if ( __FILE__ == $_SERVER['SCRIPT_FILENAME'] ) die( header( 'Location: /') ); ?>
<?php if ( ! $pdf ): ?>
	<div class="actions-list">
		<a href="<?php echo esc_attr( add_query_arg( array( 'frmt' => 'pdf' ) ) ) ?>"><?php _e( 'Download PDF', 'opentickets-community-edition' ) ?></a>
	</div>
<?php endif; ?>
