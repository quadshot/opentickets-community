<?php if ( __FILE__ == $_SERVER['SCRIPT_FILENAME'] ) die( header( 'Location: /') );
/*
@package Openticket/Templates/EventArea
@version 2.2.6
*/

$show_available_qty = apply_filters( 'qsot-get-option-value', true, 'qsot-show-available-quantity' );

// figure out the purchase limit for the event
$limit = apply_filters( 'qsot-event-ticket-purchase-limit', 0, $event->ID );

// find the zoner for the area, and the availability for this event
$zoner = isset( $area->area_type ) && is_object( $area->area_type ) ? $area->area_type->get_zoner() : false;
$available = is_object( $zoner ) ? $zoner->get_availability( $event, $area ) : '';
?>

<div class="qsfix"></div>

<div class="qsot-event-area-ticket-selection">

	<?php do_action( 'qsot-before-ticket-selection-form', $event, $area, $reserved ) ?>

	<?php if ( is_object( $area->ticket ) && ! is_wp_error( $area->ticket ) && ! $area->is_soldout ): ?>

		<div class="qsot-ticket-selection show-if-js"></div>

		<div class="hide-if-js remove-if-js no-js-message">
			<p>
				<?php _e( 'For a better experience, certain features of javascript area required. Currently you either do not have these features, or you do not have them enabled. Despite this, you can still purchase your tickets, using 2 simple steps.', 'opentickets-community-edition' ) ?>
			</p>
			<p>
				<?php _e( '<strong>STEP 1:</strong> Below, enter the number of tickets you wish to purchase, then click <span class="button-name">Reserve Tickets</span>.', 'opentickets-community-edition' ) ?>
			</p>
			<p>
				<?php _e( '<strong>STEP 2:</strong> Finally, once you have successfully Reserved your Tickets, click <span class="button-name">Proceed to Cart</span> to complete your order.', 'opentickets-community-edition' ) ?>
			</p>
		</div>

		<?php do_action( 'qsot-after-ticket-selection-no-js-message', $event, $area, $reserved ); ?>

		<div class="event-area-image"><?php do_action( 'qsot-draw-event-area-image', $event, $area, $reserved ) ?></div>

		<?php if ( ( $errors = apply_filters( 'qsot-zoner-non-js-error-messages', array() ) ) && count( $errors ) ): ?>

			<div class="messages">
				<?php foreach ( $errors as $e ): ?>
					<div class="error"><?php echo $e ?></div>
				<?php endforeach; ?>
			</div>

			<div class="qs-shim"></div>

		<?php endif; ?>

		<?php if ( isset( $_GET['rmvd'] ) ): ?>

			<div class="messages">
				<div class="msg"><?php _e( 'Successfully removed your reservations.', 'opentickets-community-edition' ) ?></div>
			</div>

			<div class="qs-shim"></div>

		<?php endif; ?>

		<div class="event-area-ticket-selection-form empty-if-js woocommerce" rel="ticket-selection">

			<?php if ( empty( $reserved ) ): ?>
				<div class="step-one ticket-selection-section">

					<div class="form-inner">

						<form class="submittable" action="<?php echo esc_attr( remove_query_arg( array( 'rmvd' ) ) ) ?>" method="post">

							<div class="title-wrap">
								<h3><?php _e( '<span class="step-name">STEP 1</span>: How many?', 'opentickets-community-edition' ) ?></h3>
							</div>

							<div class="field">
								<label class="section-heading"><?php echo __( 'Reserve some tickets:', 'opentickets-community-edition' ) ?></label>

								<div class="availability-message helper">
									<?php printf(
										__( 'Currently, there are <span class="available">%s</span> "<span class="ticket-name">%s</span>" (<span class="ticket-price">%s</span>) available for purchase.', 'opentickets-community-edition' ),
										( 'yes' == $show_available_qty ) ? $available : '',
										$area->ticket->get_title(),
										wc_price( $area->ticket->get_price() )
									) ?>
								</div>

								<span class="tt">
									<?php printf(
										'<span class="ticket-description"><span class="name" rel="ttname">%s</span><span class="price" rel="ttprice">%s</span></span>',
										$area->ticket->get_title(),
										wc_price( $area->ticket->get_price() )
									) ?>
								</span>

								<?php if ( 1 !== intval( $limit ) ): ?>

									<?php
										$max = 1000000;
										$max = is_numeric( $available ) && $available > 0 ? min( $max, $available ) : $max;
										$max = isset( $event->meta->purchase_limit ) && is_numeric( $event->meta->purchase_limit ) && $event->meta->purchase_limit > 0 ? min( $max, $event->meta->purchase_limit ) : $max;
									?>
									<input type="number" min="0" max="<?php echo $max ?>" step="1" class="very-short" name="ticket-count" value="1" />

								<?php else: ?>

									<input type="hidden" rel="qty" name="quantity" value="1" /> <?php __( 'x', 'opentickets-community-edition' ) ?> 1

								<?php endif; ?>

								<?php do_action('qsot-event-area-ticket-selection-no-js-step-one', $event, $area, $reserved); ?>

								<input type="button" value="<?php echo esc_attr( apply_filters( 'qsot-get-option-value', __( 'Reserve', 'opentickets-community-edition' ), 'qsot-reserve-button-text' ) ) ?>" rel="reserve-btn" class="button" />

								<?php wp_nonce_field( 'ticket-selection-step-one', 'submission' ) ?>

								<input type="hidden" name="qsot-step" value="1" />

							</div>

						</form>

					</div>

				</div>

			<?php endif; ?>

			<?php if ( ! empty( $reserved ) && ! is_wp_error( $reserved ) ): ?>

				<div class="step-two ticket-selection-section">
					<div class="form-inner">

						<form class="submittable" action="<?php echo esc_attr( remove_query_arg( array( 'rmvd' ) ) ) ?>" method="post">

							<div class="title-wrap">
								<h3><?php _e( '<span class="step-name">STEP 2</span>: Review', 'opentickets-community-edition' ) ?></h3>
							</div>

							<div class="field">

								<label class="section-heading"><?php echo __( 'You currently have:', 'opentickets-community-edition' ) ?></label>

								<div class="availability-message helper">
									<?php printf(
										__( 'Currently, there are <span class="available">%s</span> more "<span class="ticket-name">%s</span>" (<span class="ticket-price">%s</span>) available for purchase.', 'opentickets-community-edition' ),
										( 'yes' == $show_available_qty ) ? $available : '',
										$area->ticket->get_title(),
										wc_price( $area->ticket->get_price() )
									) ?>
								</div>

								<span class="tt">
									<a href="<?php echo esc_attr( add_query_arg( array( 'remove_reservations' => 1, 'submission' => wp_create_nonce( 'ticket-selection-step-two' ) ) ) ) ?>" class="remove-link">X</a>
									<?php printf(
										'<span class="ticket-description"><span class="name" rel="ttname">%s</span><span class="price" rel="ttprice">%s</span></span>',
										$area->ticket->get_title(),
										wc_price( $area->ticket->get_price() )
									) ?>
								</span>

								<?php if ( 'yes' == apply_filters( 'qsot-get-option-value', 'no', 'qsot-locked-reservations' ) ): ?>

									<span rel="qty"><?php echo htmlspecialchars( $reserved ) ?></span>

								<?php else: ?>

									<?php if ( 1 !== intval( $limit ) ): ?>

										<input type="number" min="0" max="<?php echo $available ?>" step="1" class="very-short" name="ticket-count"
												value="<?php echo esc_attr( isset( $reserved[ $area->ticket->id ] ) ? $reserved[ $area->ticket->id ] : 0 ) ?>" />
										<input type="button" value="<?php echo esc_attr( apply_filters( 'qsot-get-option-value', __( 'Update', 'opentickets-community-edition' ), 'qsot-update-button-text' ) ) ?>" rel="update-btn" class="button" />

										<?php wp_nonce_field('ticket-selection-step-two', 'submission') ?>

										<input type="hidden" name="qsot-step" value="2" />

									<?php else: ?>

										<input type="hidden" rel="qty" name="ticket-count" value="1" /> <?php __( 'x', 'opentickets-community-edition' ) ?> 1

									<?php endif; ?>

								<?php endif; ?>

								<?php do_action( 'qsot-event-area-ticket-selection-no-js-step-two', $event, $area, $reserved ); ?>

							</div>

						</form>

					</div>

					<div class="qsot-form-actions actions">
						<a href="<?php echo esc_attr(WC()->cart->get_cart_url()) ?>" class="button"><?php echo apply_filters( 'qsot-get-option-value', __( 'Proceed to Cart', 'opentickets-community-edition' ), 'qsot-proceed-button-text' ) ?></a>
					</div>
				</div>

			<?php endif; ?>
		</div>

	<?php elseif ($area->is_soldout): ?>

		<div class="event-area-image"><?php do_action( 'qsot-draw-event-area-image', $event, $area, $reserved ) ?></div>
		<p><?php _e( 'We are sorry. This event is sold out!', 'opentickets-community-edition' ) ?></p>

	<?php else: ?>

		<div class="event-area-image"><?php do_action( 'qsot-draw-event-area-image', $event, $area, $reserved ) ?></div>
		<p><?php _e( 'We are sorry. There are currently no tickets available for this event. Check back soon!', 'opentickets-community-edition' ) ?></p>

	<?php endif; ?>
	
	<?php do_action( 'qsot-after-ticket-selection-form', $event, $area, $reserved ); ?>

	<script>
		if (typeof jQuery == 'function') (function($) {
			$('.remove-if-js').remove();
			$('.empty-if-js').empty();
		})(jQuery);
	</script>

</div>

<div class="qsfix"></div>
