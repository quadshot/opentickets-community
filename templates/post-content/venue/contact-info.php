<?php (__FILE__ == $_SERVER['SCRIPT_FILENAME']) ? die(header('Location: /')) : null; ?>
<?php
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

	// if there is no valid contact info, then bail
	if ( empty( $address ) )
		return;
?>
<h3><?php echo __( 'Physical Address:', 'opentickets-community-edition' ) ?></h3>
<div class="venue-address"><?php echo $address ?></div>
<?php if ( 'yes' === apply_filters( 'qsot-get-option-value', 'no', 'qsot-venue-show-notes' ) ): ?>
	<div class="venue-notes"><?php echo apply_filters( 'the_content', $meta_value['notes'], -1 ) ?></div>
<?php endif; ?>
