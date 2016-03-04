<?php if ( __FILE__ == $_SERVER['SCRIPT_FILENAME'] ) die( header( 'Location: /') ); ?>
<div class="ticket-form ticket-selection-section">

	<?php do_action( 'qsot-gaea-before-ticket-selection-form', $args ) ?>

	<div class="form-inner reserve">
		<div class="title-wrap">
			<h3><?php _e( 'Step 1: How Many?', 'opentickets-community-edition' ) ?></h3>
		</div>
		<div class="field">
			<label class="section-heading"><?php _e( 'Reserve some tickets:', 'opentickets-community-edition' ) ?></label>
			<div class="availability-message helper"></div>
			<span rel="tt"></span>
			<?php if ( 1 !== intval( $limit ) ): ?>
				<input type="number" step="1" min="0" max="<?php echo $max ?>" rel="qty" name="quantity" value="1" class="very-short" />
			<?php else: ?>
				<input type="hidden" rel="qty" name="quantity" value="1" /> <?php _e( 'x', 'opentickets-community-edition' ) . ' 1' ?>
			<?php endif; ?>
			<input type="button" value="<?php echo esc_attr( apply_filters( 'qsot-get-option-value', __( 'Reserve', 'opentickets-community-edition' ), 'qsot-reserve-button-text' ) ) ?>" rel="reserve-btn" class="button" />
		</div>
	</div>

	<?php do_action( 'qsot-gaea-after-ticket-selection-form', $args ) ?>

</div>
