<?php if ( __FILE__ == $_SERVER['SCRIPT_FILENAME'] ) die( header( 'Location: /') ); ?>
<?php if ( $venue ): ?>
	<div class="venue-info">
		<table class="map-and-venue two-columns">
			<tbody>
				<tr>
					<td class="column column-left">
						<div class="inner">

							<h2><?php echo apply_filters( 'the_title', $venue->post_title, $venue->ID ) ?></h2>

							<div class="venue-image"><?php echo wp_get_attachment_image( $venue->image_id, array( 249, 9999 ) ) ?></div>

							<ul class="venue-address">

								<li><?php echo WC()->countries->get_formatted_address( array(
									'address_1' => $venue->meta['info']['address1'],
									'address_2' => $venue->meta['info']['address2'],
									'city' => $venue->meta['info']['city'],
									'state' => $venue->meta['info']['state'],
									'postcode' => $venue->meta['info']['postal_code'],
									'country' => $venue->meta['info']['country']
								) ); ?></li>

								<li><?php _e( 'Area:','opentickets-community-edition' ); echo ' ' . apply_filters( 'the_title', $ticket->event_area->post_title, $ticket->event_area->ID ) ?></li>
							</ul>

							<div class="venue-notes">
								<?php echo apply_filters( 'the_content', $venue->meta['info']['notes'] ) ?>
							</div>

						</div>
					</td>

					<td class="column column-right">
						<div class="inner">

							<?php if ( isset( $venue->map_image ) ): ?>

								<?php if ( ! $pdf ): ?>
									<div class="map-wrap"><?php echo $venue->map_image ?></div>
								<?php else: ?>
									<div class="map-wrap"><?php echo $venue->map_image_only ?></div>
								<?php endif; ?>

								<div class="map-extra-instructions"><?php echo apply_filters( 'the_content', $venue->meta['info']['instructions'] ) ?></div>

							<?php endif; ?>
						</div>
					</td>
				</tr>
			</tbody>
		</table>
	</div>
<?php endif; ?>
