<?php if ( __FILE__ == $_SERVER['SCRIPT_FILENAME'] ) die( header( 'Location: /') ); ?>
<?php
	// load the address
	$address = trim( WC()->countries->get_formatted_address( array(
		'first_name' => '',
		'last_name' => '',
		'company' => apply_filters( 'the_title', $venue->post_title ),
		'address_1' => $meta_value['address1'],
		'address_2' => $meta_value['address2'],
		'city' => $meta_value['city'],
		'state' => $meta_value['state'],
		'postcode' => $meta_value['postal_code'],
		'country' => $meta_value['country'],
	) ) );

	// load the map
	$map = apply_filters( 'qsot-get-venue-map', '', $venue, false );

	// setup the switches based on our options
	$show_map = ( 'yes' === apply_filters( 'qsot-get-option-value', 'no', 'qsot-venue-show-map' ) );
	$show_address = ( 'yes' === apply_filters( 'qsot-get-option-value', 'no', 'qsot-venue-show-address' ) );
	$show_notes = ( 'yes' === apply_filters( 'qsot-get-option-value', 'no', 'qsot-venue-show-notes' ) );

	// render
?>

<?php if ( ( $show_map || $show_address ) && ! empty( $address ) ): ?>
	<div class="clear"></div>

	<h3><?php echo __( 'Physical Address:', 'opentickets-community-edition' ) ?></h3>

	<?php if ( $show_address ): ?>
		<div class="venue-address"><?php echo $address ?></div>
	<?php endif; ?>

	<?php if ( $show_map ): ?>
		<div class="venue-map"><?php echo $map ?></div>
	<?php endif; ?>

	<br/>
<?php endif; ?>

<?php if ( $show_notes && ! empty( $meta_value['notes'] ) ): ?>
	<h3><?php echo __( 'Notes:', 'opentickets-community-edition' ) ?></h3>
	<div class="venue-notes"><?php echo apply_filters( 'the_content', $meta_value['notes'], -1 ) ?></div>
<?php endif; ?>
