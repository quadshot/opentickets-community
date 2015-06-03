<?php
/*
 * Template Name: _Default
 */

$multiple = $ticket->order_item['qty'] > 1;
$pdf = ( isset( $_GET['frmt'] ) && 'pdf' == strtolower( $_GET['frmt'] ) );

// determine the branding images before hand since they are reused
$brand_imgs = array();
for ( $i = 0; $i < 5; $i++ ) {
	$bid = isset( $branding_image_ids[ $i ] ) ? $branding_image_ids[ $i ] : 0;
	$brand_imgs[ $i ] = '';
	if ( 'noimg' !== $bid ) {
		$brand_imgs[ $i ] = apply_filters( 'qsot-ticket-branding-image', wp_get_attachment_image( $bid, array( 90, 99999 ), false, array( 'class' => 'branding-img' ) ), $bid, $i, $pdf );
		$brand_imgs[ $i ] = ! empty( $brand_imgs[ $i ] )
			? $brand_imgs[ $i ]
			: '<a href="' . esc_attr( QSOT::product_url() ) . '" title="' . __( 'Who is OpenTickets?', 'opentickets-community-edition' ) . '">'
					.'<img src="' . esc_attr( QSOT::plugin_url() . 'assets/imgs/opentickets-tiny.jpg' ) . '" class="ot-tiny-logo branding-img" />'
				. '</a>';
	}
	$brand_imgs[ $i ] = empty( $brand_imgs[ $i ] ) ? '<div class="fake-branding-img">&nbsp;</div>' : $brand_imgs[ $i ];
}

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
		<title><?php sprintf(
			__( '%s - %s - %s - %s', 'opentickets-community-edition' ),
			__( 'Ticket', 'opentickets-community-edition' ),
			$ticket->event->post_title,
			$ticket->product->get_title(),
			$ticket->product->get_price()
		) ?></title>
		<?php wp_print_styles() ?>
	</head>

	<body <?php echo ( $pdf ) ? 'class="pdf"' : '' ?> >
		<div class="page-wrap">
			<?php if ( ! $pdf ): ?>
				<div class="actions-list">
					<a href="<?php echo esc_attr( add_query_arg( array( 'frmt' => 'pdf' ) ) ) ?>"><?php _e( 'Download PDF', 'opentickets-community-edition' ) ?></a>
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
												$same_day = strtotime( 'today', $stime ) == strtotime( 'today', $etime );
											?>
											<li><?php
												echo '<span class="label">' . __( 'Starts:', 'opentickets-community-edition' ) . '</span>'
														. '<span class="value">'
															. ' ' . date( __( 'D, F jS, Y', 'opentickets-community-edition' ), $stime ) . __( ' @ ', 'opentickets-community-edition' )
															. ' ' . date( __( 'g:ia', 'opentickets-community-edition' ), $stime )
														. '</span>';
											?></li>
											<li><?php
												echo '<span class="label">' . __( 'Ends:', 'opentickets-community-edition' ) . '</span>'
														. '<span class="value">'
															. ( $same_day ? '' : ' ' . date( __( 'D, F jS, Y', 'opentickets-community-edition' ), $stime ) . __( ' @ ', 'opentickets-community-edition' ) )
															. ' ' . date( __( 'g:ia', 'opentickets-community-edition' ), $stime )
														. '</span>';
											?></li>
											<li><?php echo '<span class="label">' . __( 'Area:', 'opentickets-community-edition' ) . '</span><span class="value"> ' . $ticket->event_area->post_title . '</span>' ?></li>
										</ul>
									</td>
									<td width="125" rowspan="3" class="qr-code right">
										<?php echo ( $multiple && isset( $ticket->qr_codes[ $index ] ) ) ? $ticket->qr_codes[ $index ] : $ticket->qr_code ?>
										<div class="personalization right">
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
										</div>
									</td>
								</tr>
								<tr>
									<td rowspan="2" class="event-image">
										<?php echo wp_get_attachment_image($ticket->event->image_id, array(225, 9999)) ?>
									</td>
									<td rowspan="2" class="venue-image">
										<?php echo wp_get_attachment_image($ticket->venue->image_id, array(225, 9999)) ?>
									</td>
								</tr>
							</tbody>
						</table>
						<table class="branding">
							<tbody>
								<tr>
									<?php for ( $i = 0; $i < 5; $i++ ): ?>
										<td valign="bottom"><?php echo force_balance_tags( $brand_imgs[ $i ] ) ?></td>
									<?php endfor; ?>
									<td valign="bottom"><a href="<?php echo esc_attr( QSOT::product_url() ) ?>" title="<?php _e('Who is OpenTickets?','opentickets-community-edition') ?>">
										<img src="<?php echo esc_attr( QSOT::plugin_url() . 'assets/imgs/opentickets-tiny.jpg' ) ?>" class="ot-tiny-logo branding-img" />
									</a></td>
								</tr>
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
										<?php if ( isset( $ticket->venue->map_image ) ): ?>
											<?php if ( ! $pdf ): ?>
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
