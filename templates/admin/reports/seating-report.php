<?php if (isset($tickets) && is_array($tickets)): ?>
	<h1><?php echo apply_filters('the_title', $event->post_title) ?></h1>

	<?php do_action('qsot-above-report-html', 'qsotc-seating', $req, $csv) ?>

	<table class="widefat ticket-list">
		<thead>
			<tr>
				<?php foreach ($fields as $field => $label): ?>
					<th><a href="#" sort="<?php echo esc_attr($field) ?>" class="sorter" title="<?php _e('Sort by','opentickets-community-edition'); echo esc_attr($label) ?>"><?php echo $label ?></a></th>
				<?php endforeach; ?>
			</tr>
		</thead>

		<tbody>
			<?php foreach ($rows as $row): ?>
				<tr>
					<?php foreach ($fields as $field => $label): ?>
						<?php switch ($field) {
							case 'purchaser': ?>
								<td>
									<?php if (!empty($row['_user_link'])): ?>
										<a href="<?php echo esc_attr($row['_user_link']) ?>" title="<?php _e('Edit User','opentickets-community-edition') ?>"><?php echo $row['purchaser'] ?></a>
									<?php else: ?>
										<?php echo $row['purchaser'] ?>
									<?php endif; ?>
								</td>
							<?php break; ?>
							<?php case 'order_id': ?>
								<td>
									<?php $out = !empty($row['order_id']) ? '#'.$row['order_id'] : '-' ?>
									<?php if (!empty($row['_order_link'])): ?>
										<a href="<?php echo esc_attr($row['_order_link']) ?>" title="<?php _e('Edit Order','opentickets-community-edition') ?>"><?php echo $out ?></a>
									<?php else: ?>
										<?php echo $out ?>
									<?php endif; ?>
								</td>
							<?php break; ?>
							<?php case 'quantity': ?>
								<td>
									<?php echo $row['quantity'] ?>
								</td>
							<?php break; ?>
							<?php case 'ticket_type': ?>
								<td>
									<?php if (!empty($row['_product_link'])): ?>
										<a href="<?php echo esc_attr($row['_product_link']) ?>" title="<?php _e('Edit Product','opentickets-community-edition') ?>"><?php echo $row['ticket_type'] ?></a>
									<?php else: ?>
										<?php echo $row['ticket_type'] ?>
									<?php endif; ?>
								</td>
							<?php break; ?>
							<?php case 'email': ?><td><a href="mailto:<?php echo esc_attr($row['email']) ?>"><?php echo $row['email'] ?></a></td><?php break; ?>
							<?php case 'phone': ?><td><?php echo $row['phone'] ?></td><?php break; ?>
							<?php case 'address': ?><td><?php echo $row['address'] ?></td><?php break; ?>
							<?php case 'note': ?>
								<td>
									<?php echo $row['note'] ?>
								</td>
							<?php break; ?>
							<?php case 'state': ?>
								<td width="1%" class="status">
									<?php echo $row['state'] ?>
								</td>
							<?php break; ?>
							<?php default: ?>
								<?php do_action('qsot-seating-report-display-column', $field, $row); ?>
							<?php break; ?>
						<?php } // switch ?>
					<?php endforeach; ?>
				</tr>
			<?php endforeach; ?>
		</tbody>

		<?php do_action('qsot-end-of-seating-report', $event, $tickets, $req) ?>

	</table>

	<?php do_action('qsot-below-report-html', 'qsotc-seating', $req, $csv) ?>

	<?php do_action('qsot-below-seating-report', $event, $req, $tickets); ?>
<?php endif; ?>
