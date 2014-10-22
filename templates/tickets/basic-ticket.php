<?php
/*
 * Template Name: _Default
 */
?><!DOCTYPE html>
<!--[if IE 6]>
<html id="ie6" <?php language_attributes(); ?>>
<![endif]-->
<!--[if IE 7]>
<html id="ie7" <?php language_attributes(); ?>>
<![endif]-->
<!--[if IE 8]>
<html id="ie8" <?php language_attributes(); ?>>
<![endif]-->
<!--[if !(IE 6) | !(IE 7) | !(IE 8)  ]><!-->
<html>
	<head>
		<title>Ticket - <?php echo $ticket->event->post_title ?> - <?php echo $ticket->product->get_title() ?> - <?php echo $ticket->product->get_price() ?></title>
		<?php wp_print_styles() ?>
	</head>

	<body>
		<div class="page-wrap">
			<?php if (!isset($_GET['frmt']) || $_GET['frmt'] != 'pdf'): ?>
				<div class="actions-list">
					<a href="<?php echo esc_attr(add_query_arg(array('frmt' => 'pdf'))) ?>">Download PDF</a>
				</div>
			<?php endif; ?>
			<?php for ($index=0; $index < $ticket->order_item['qty']; $index++): ?>
				<div class="ticket-wrap">
					<div class="inner-wrap">
						<table class="ticket">
							<tbody>
								<tr>
									<td colspan="2" class="event-information">
										<ul>
											<li><h2><?php echo $ticket->event->parent_post_title ?></h2></li>
											<li>Date: <?php echo date('D, F jS, Y @ h:ia ', strtotime($ticket->event->meta->start)); ?></li>
											<li>Area: <?php echo $ticket->event_area->post_title ?></li>
										</ul>
									</td>
									<td width="125" rowspan="2" class="qr-code right"><?php echo $ticket->qr_code ?></td>
								</tr>
								<tr>
									<td rowspan="2" class="event-image">
										<?php echo wp_get_attachment_image($ticket->event->image_id, array(225, 9999)) ?>
									</td>
									<td rowspan="2" class="venue-image">
										<?php echo wp_get_attachment_image($ticket->venue->image_id, array(225, 9999)) ?>
									</td>
								</tr>
								<tr>
									<td class="personalization right">
										<ul>
											<li><?php echo ucwords($ticket->order->billing_first_name.' '.$ticket->order->billing_last_name) ?></li>
											<li>
												<?php echo $ticket->product->get_title() ?>
												<?php if ($ticket->order_item['qty'] > 1): ?>
													[<?php echo $index+1 ?> of <?php echo $ticket->order_item['qty'] ?>]
												<?php endif; ?>
												(<?php echo $ticket->product->get_price_html() ?>)
											</li>
										</ul>
									</td>
								</tr>
							</tbody>
						</table>
						<a href="<?php echo esc_attr( QSOT::product_url() ) ?>" title="Who is OpenTickets?">
							<img src="<?php echo esc_attr( QSOT::plugin_url() . 'assets/imgs/opentickets-tiny.png' ) ?>" class="ot-tiny-logo" />
						</a>
					</div>
				</div>
			<?php endfor; ?>

			<?php if (isset($ticket->venue)): ?>
				<div class="venue-info">
					<table class="map-and-venue two-columns">
						<tbody>
							<tr>
								<td class="column column-left">
									<div class="inner">
										<h2><?php echo $ticket->venue->post_title ?></h2>

										<ul class="venue-address">
											<li><?php echo $ticket->venue->meta['info']['address1'] ?></li>
											<?php if (!empty($ticket->venue->meta['info']['address2'])): ?>
												<li><?php echo $ticket->venue->meta['info']['address2'] ?></li>
											<?php endif; ?>
											<li><?php echo sprintf(
												'%s, %s %s %s',
												$ticket->venue->meta['info']['city'],
												$ticket->venue->meta['info']['state'],
												$ticket->venue->meta['info']['postal_code'],
												$ticket->venue->meta['info']['country']
											); ?></li>
											<li>Area: <?php echo $ticket->event_area->post_title ?></li>
										</ul>

										<div class="venue-notes">
											<?php echo apply_filters('the_content', $ticket->venue->meta['info']['notes']) ?>
										</div>
									</div>
								</td>

								<td class="column column-right">
									<div class="inner">
										<?php if (isset($ticket->venue->map_image)): ?>
											<?php if (!isset($_GET['frmt']) || $_GET['frmt'] != 'pdf'): ?>
												<div class="map-wrap"><?php echo $ticket->venue->map_image ?></div>
											<?php else: ?>
												<div class="map-wrap"><?php echo $ticket->venue->map_image_only ?></div>
											<?php endif; ?>
											<div class="map-extra-instructions"><?php echo $ticket->venue->meta['info']['instructions'] ?></div>
										<?php endif; ?>
									</div>
								</td>
							</tr>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
		</div>
	</body>
</html>
