<div class="qsot-event-area-ticket-selection">
	<?php do_action('qsot-before-ticket-selection-form', $event, $area, $reserved); ?>

	<?php if (is_object($area->ticket) && !$area->is_soldout): ?>
		<div class="qsot-ticket-selection show-if-js"></div>
		<div class="hide-if-js remove-if-js no-js-message">
			<p>
				<?php _e('For a better experience, certain features of javascript area required. Currently you either do not have these features, or you do not have them enabled. Despite this, you can still purchase your tickets, using 2 simple steps.','opentickets-community-edition') ?>
			</p>
			<p>
				<?php _e('<strong>STEP 1:</strong> Below, enter the number of tickets you wish to purchase, then click <span class="button-name">Reserve Tickets</span>.','opentickets-community-edition') ?>
			</p>
			<p>
				<?php _e('<strong>STEP 2:</strong> Finally, once you have successfully Reserved your Tickets, click <span class="button-name">Proceed to Cart</span> to complete your order.','opentickets-community-edition') ?>
			</p>
		</div>

		<?php do_action('qsot-after-ticket-selection-no-js-message', $event, $area, $reserved); ?>

		<div class="event-area-image"><?php do_action('qsot-draw-event-area-image', $event, $area, $reserved) ?></div>

		<div class="event-area-ticket-selection-form empty-if-js woocommerce" rel="ticket-selection">
			<?php if (($errors = apply_filters('qsot-zoner-non-js-error-messages', array())) && count($errors)): ?>
				<ul class="form-errors">
					<?php foreach ($errors as $e): ?>
						<li class="error"><?php echo $e ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>

			<?php if (isset($_GET['rmvd'])): ?>
				<ul class="form-removed">
					<li class="msg"><?php _e('Successfully removed your reservations.','opentickets-community-edition') ?></li>
				</ul>
			<?php endif; ?>

			<?php if (empty($reserved)): ?>
				<div class="step-one ticket-selection-section">
					<div class="form-inner">
						<form class="submittable" action="<?php echo esc_attr(remove_query_arg(array('rmvd'))) ?>" method="post">
							<h3><?php _e('<span class="step-name">STEP 1</span>: How many?','opentickets-community-edition') ?></h3>

							<div class="field">
								<div class="helper">
									<?php printf(__('Currently, there are <span class="available">%s</span> "<span class="ticket-name">%s</span>" (<span class="ticket-price">%s</span>) available for purchase.','opentickets-community-edition'),  $area->meta['available'], $area->ticket->get_title(), wc_price($area->ticket->get_price())); ?>
								</div>
								<label for="ticket-count">
									<?php printf(__('How many "<span class="ticket-name">%s</span>" (<span class="ticket-price">%s</span>)?','opentickets-community-edition'), $area->ticket->get_title(), wc_price($area->ticket->get_price())); ?>
								</label>
								<input type="number" min="0" max="<?php echo $area->meta['available'] ?>" step="1" class="very-short" name="ticket-count" value="1" />
							</div>

							<?php do_action('qsot-event-area-ticket-selection-no-js-step-one', $event, $area, $reserved); ?>

							<div class="qsot-form-actions">
								<?php wp_nonce_field('ticket-selection-step-one', 'submission') ?>
								<input type="hidden" name="qsot-step" value="1" />
								<input type="submit" value="<?php _e('Reserve Tickets','opentickets-community-edition') ?>" class="button" />
							</div>
						</form>
					</div>
				</div>
			<?php endif; ?>

			<?php if (!empty($reserved)): ?>
				<div class="step-two ticket-selection-section">
					<div class="form-inner">
						<h3><?php _e('<span class="step-name">STEP 2</span>: Review','opentickets-community-edition') ?></h3>

						<div class="field">
							<div class="helper">
								<?php printf(__('Currently, there are <span class="available">%s</span> more "<span class="ticket-name">%s</span>" (<span class="ticket-price">%s</span>) available for purchase.','opentickets-community-edition'), $area->meta['available'] - $reserved, $area->ticket->get_title(), wc_price($area->ticket->get_price())); ?>
							</div>

							<form class="submittable" action="<?php echo esc_attr(remove_query_arg(array('rmvd'))) ?>" method="post">
								<label><?php _e('You currently have:','opentickets-community-edition') ?></label>
								<div class="you-have">
									<a href="<?php echo esc_attr(add_query_arg(array('remove_reservations' => 1, 'submission' => wp_create_nonce('ticket-selection-step-two')))) ?>" class="remove-link">X</a>

									<input type="number" min="0" max="<?php echo $area->meta['available'] ?>" step="1" class="very-short" name="ticket-count" value="<?php echo $reserved ?>" />
									<label for="ticket-count">
										"<span class="ticket-name"><?php echo $area->ticket->get_title() ?></span>"
										(<span class="ticket-price"><?php echo wc_price($area->ticket->get_price()) ?></span>).
									</label>

									<?php wp_nonce_field('ticket-selection-step-two', 'submission') ?>
									<input type="hidden" name="qsot-step" value="2" />
									<input type="submit" value="<?php _e('Update','opentickets-community-edition') ?>" class="button" />
								</div>
							</form>
						</div>
					</div>

					<?php do_action('qsot-event-area-ticket-selection-no-js-step-two', $event, $area, $reserved); ?>

					<div class="qsot-form-actions actions">
						<a href="<?php echo esc_attr($woocommerce->cart->get_cart_url()) ?>" class="button">Proceed to Cart</a>
					</div>
				</div>
			<?php endif; ?>
		</div>
	<?php elseif ($area->is_soldout): ?>
		<div class="event-area-image"><?php do_action('qsot-draw-event-area-image', $event, $area, $reserved) ?></div>
		<p><?php _e('We are sorry. This event is sold out!','opentickets-community-edition') ?></p>
	<?php else: ?>
		<div class="event-area-image"><?php do_action('qsot-draw-event-area-image', $event, $area, $reserved) ?></div>
		<p><?php _e('We are sorry. There are currently no tickets available for this event. Check back soon!','opentickets-community-edition') ?></p>
	<?php endif; ?>
	
	<?php do_action('qsot-after-ticket-selection-form', $event, $area, $reserved); ?>

	<script>
		if (typeof jQuery == 'function') (function($) {
			$('.remove-if-js').remove();
			$('.empty-if-js').empty();
			$('.hide-if-js').hide();
			$('.show-if-js').show();
		})(jQuery);
	</script>
</div>
