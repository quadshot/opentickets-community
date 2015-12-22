<?php if ( __FILE__ == $_SERVER['SCRIPT_FILENAME'] ) die( header( 'Location: /') );
	$args['multiple'] = $multiple = $ticket->order_item['qty'] > 1;
?>
<?php for ( $index=0; $index < $ticket->order_item['qty']; $index++ ): // for each ticket in this group ?>
	<?php $args['index'] = $index; // set the index so it can be passed on to the sub-templates if needed ?>
	<div class="ticket-wrap">
		<div class="inner-wrap">
			<table class="ticket">
				<tbody>
					<tr>

						<td colspan="2" class="event-information">
							<?php QSOT_Templates::include_template( 'tickets/ticket/event-meta.php', $args ) ?>
						</td>

						<td width="125" rowspan="2" class="qr-code right">

							<?php QSOT_Templates::include_template( 'tickets/ticket/qr-code.php', $args ) ?>

							<div class="personalization right">

								<?php QSOT_Templates::include_template( 'tickets/ticket/personalization.php', $args ) ?>

							</div>
						</td>
					</tr>
					<tr>
						<?php QSOT_Templates::include_template( 'tickets/ticket/images.php', $args ) ?>
					</tr>
				</tbody>
			</table>

			<?php QSOT_Templates::include_template( 'tickets/ticket/branding.php', $args ) ?>

		</div>
	</div>
<?php endfor; ?>
