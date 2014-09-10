<?php if (isset($tickets) && is_array($tickets) && !empty($tickets)): ?>
	<h2><?php echo apply_filters('qsot_my_account_my_upcoming_tickets_title', __('Upcoming Tickets', 'qsot')); ?></h2>

	<?php if ($display_format == 'as_list'): ?>

		<ul class="ticket-list">
			<?php foreach ($tickets as $ticket): ?>
				<?php
					$name = sprintf(
						'%s (%s @ %s)',
						$ticket->zone->fullname,
						$ticket->product->post->post_title,
						money_format('%.2n', $ticket->_line_subtotal)
					);
				?>
				<li>
					<a href="<?php echo esc_attr(site_url($ticket->_ticket_link)) ?>" title="View your ticket"><?php echo $name ?></a>
					for <a href="<?php echo get_permalink($ticket->event->ID) ?>" title="Visit the Event page"><?php echo apply_filters('the_title', $ticket->event->post_title); ?></a>
					<?php if (is_admin() && isset($ticket->__order_id) && !empty($ticket->__order_id)): ?>
						(order <a href="<?php echo esc_attr(get_edit_post_link($ticket->__order_id)) ?>" title="Edit order #<?php echo $ticket->__order_id ?>">#<?php echo $ticket->__order_id ?></a>)
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>

	<?php else: ?>

		<table class="event-item section shop_table my_account_upcoming_tickets">
			<?php foreach ($by_event as $event): ?>
				<thead>
					<tr>
            <?php $ticket = current(array_values($event->tickets)) ?>
            <th colspan="<?php echo is_admin() && isset($ticket->__order_id) && !empty($ticket->__order_id) ? '3' : '2' ?>"
                is_admin="<?php echo is_admin() ? 'yes' : 'no' ?>"
                order_id="<?php echo isset($ticket->__order_id) && !empty($ticket->__order_id) ? $ticket->__order_id : '0' ?>">
							<span class="nobr">
								<?php echo apply_filters('the_title', $event->post_title); ?>
								<a href="<?php echo get_permalink($event->ID) ?>" title="Visit the Event page">(View Show Page)</a>
								<?php $link = apply_filters('qsot-get-all-event-tickets-link', '', $event->ID, is_admin() ? $user->ID : 0) ?>
								<?php if (!empty($link)): ?>
									<a href="<?php echo esc_attr($link) ?>" title="Visit all event tickets">(Print ALL Event Tickets)</a>
								<?php endif; ?>
							</span>
						</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($event->tickets as $ticket): ?>
						<?php
							$name = sprintf(
								'%s @ %s',
								$ticket->product->get_title(),
								money_format('%.2n', $ticket->_line_subtotal)
							);
						?>
						<tr>
							<td><a href="<?php echo esc_attr($ticket->permalink) ?>" title="View your ticket"><?php echo $name ?></a></td>
							<td> x <?php echo $ticket->_qty ?></td>
							<?php if (is_admin() && isset($ticket->__order_id) && !empty($ticket->__order_id)): ?>
								<td>
									(order <a href="<?php echo esc_attr(get_edit_post_link($ticket->__order_id)) ?>" title="Edit order #<?php echo $ticket->__order_id ?>">#<?php echo $ticket->__order_id ?></a>)
								</td>
							<?php endif; ?>
						</tr>
					<?php endforeach; ?>
				</tbody>
			<?php endforeach; ?>
		</table>

	<?php endif; ?>

<?php endif; ?>
