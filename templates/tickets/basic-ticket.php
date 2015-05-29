<?php
/*
 * Template Name: _Default
 */

$multiple = $ticket->order_item['qty'] > 1;
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
		<title><?php _e('Ticket','opentickets-community-edition'); echo ' - '.$ticket->event->post_title ?> - <?php echo $ticket->product->get_title() ?> - <?php echo $ticket->product->get_price() ?></title>
		<?php wp_print_styles() ?>
	</head>

	<body>
		<div class="page-wrap">
			<?php if (!isset($_GET['frmt']) || $_GET['frmt'] != 'pdf'): ?>
				<div class="actions-list">
					<a href="<?php echo esc_attr(add_query_arg(array('frmt' => 'pdf'))) ?>"><?php _e('Download PDF','opentickets-community-edition') ?></a>
				</div>
			<?php endif; ?>
			<?php for ( $index=0; $index < $ticket->order_item['qty']; $index++ ): ?>
				<div class="ticket-wrap">
					<div class="inner-wrap">
						<table class="ticket">
							<tbody>
								<tr>
									<td colspan="2" class="event-information">
										<ul>
											<li><h2><?php echo $ticket->event->parent_post_title ?></h2></li>
											<?php
												$stime = strtotime( $ticket->event->meta->start );
												$etime = strtotime( $ticket->event->meta->end );
											?>
											<li><?php _e( 'Starts:', 'opentickets-community-edition' ); echo ' ' . date( __( 'D, F jS, Y', 'opentickets-community-edition' ), $stime );?>
											<?php _e( ' @ ', 'opentickets-community-edition' ) ?><?php echo ' ' . date( __( 'g:ia', 'opentickets-community-edition' ), $stime ) ?><?php _e( ' ', 'opentickets-community-edition' ) ?></li>
											<?php
												$format = strtotime( 'today', $stime ) == strtotime( 'today', $etime )
													? __( 'g:ia', 'opentickets-community-edition' )
													: __( 'D, F jS, Y', 'opentickets-community-edition' );
											?>
											<li><?php _e( 'Ends:', 'opentickets-community-edition' ); echo ' ' . date( __( 'g:ia', 'opentickets-community-edition' ), $etime ); ?><?php _e( ' ', 'opentickets-community-edition' );?></li>
											<li><?php _e( 'Area:', 'opentickets-community-edition' ); echo ' ' . $ticket->event_area->post_title ?></li>
										</ul>
									</td>
									<td width="125" rowspan="2" class="qr-code right"><?php echo ( $multiple && isset( $ticket->qr_codes[ $index ] ) ) ? $ticket->qr_codes[ $index ] : $ticket->qr_code ?></td>
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
											<?php if ( $ticket->show_order_number ): ?>
												<li><?php _e('ORDER #','opentickets-community-edition'); echo ' ' .$ticket->order->id ?></li>
											<?php endif; ?>
											<li><?php echo ucwords( implode( ' ', $ticket->names ) ) ?></li>
											<li><?php echo $ticket->product->get_title() ?></li>
											<?php if ( $ticket->order_item['qty'] > 1 ): ?>
												<li>[<?php echo sprintf( __( '%1$s of %2$s', 'opentickets-community-edition' ), $index + 1, $ticket->order_item['qty'] ) ?>]</li>
											<?php endif; ?>
											<li>(<?php echo $ticket->product->get_price_html() ?>)
											</li>
											<?php do_action( 'qsot-ticket-information', $ticket, $multiple ); ?>
										</ul>
									</td>
								</tr>
							</tbody>
						</table>
						<table>
							<tbody>
								<tr><td><a href="<?php echo esc_attr( QSOT::product_url() ) ?>" title="<?php _e('Who is OpenTickets?','opentickets-community-edition') ?>">
									<img src="<?php echo esc_attr( QSOT::plugin_url() . 'assets/imgs/opentickets-tiny.jpg' ) ?>" class="ot-tiny-logo" />
								</a></td><td valign="bottom">
								<a href="<?php echo esc_attr( QSOT::product_url() ) ?>" title="<?php _e('Who is OpenTickets?','opentickets-community-edition') ?>">
									<img src="<?php echo esc_attr( QSOT::plugin_url() . 'assets/imgs/opentickets-tiny.jpg' ) ?>" class="ot-tiny-logo" />
								</a></td><td>
								<a href="<?php echo esc_attr( QSOT::product_url() ) ?>" title="<?php _e('Who is OpenTickets?','opentickets-community-edition') ?>">
									<img src="<?php echo esc_attr( QSOT::plugin_url() . 'assets/imgs/opentickets-tiny.jpg' ) ?>" class="ot-tiny-logo" />
								</a></td><td>
								<a href="<?php echo esc_attr( QSOT::product_url() ) ?>" title="<?php _e('Who is OpenTickets?','opentickets-community-edition') ?>">
									<img src="<?php echo esc_attr( QSOT::plugin_url() . 'assets/imgs/opentickets-tiny.jpg' ) ?>" class="ot-tiny-logo" />
								</a></td><td>
								<a href="<?php echo esc_attr( QSOT::product_url() ) ?>" title="<?php _e('Who is OpenTickets?','opentickets-community-edition') ?>">
									<img src="<?php echo esc_attr( QSOT::plugin_url() . 'assets/imgs/opentickets-tiny.jpg' ) ?>" class="ot-tiny-logo" />
								</a></td><td>
								<a href="<?php echo esc_attr( QSOT::product_url() ) ?>" title="<?php _e('Who is OpenTickets?','opentickets-community-edition') ?>">
									<img src="<?php echo esc_attr( QSOT::plugin_url() . 'assets/imgs/opentickets-tiny.jpg' ) ?>" class="ot-tiny-logo" />
								</a></td></tr>
							</tbody>
						</table>
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
											<li><?php _e('Area:','opentickets-community-edition'); echo ' '.$ticket->event_area->post_title ?></li>
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
