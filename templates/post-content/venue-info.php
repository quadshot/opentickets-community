<?php if ( __FILE__ == $_SERVER['SCRIPT_FILENAME'] ) die( header( 'Location: /') ); ?>
<?php do_action( 'qsot-before-venue-info-outside', $venue_id, $meta ) ?>
<div class="venue-info">
	<?php do_action( 'qsot-before-venue-info-inside', $venue_id, $meta ) ?>

	<?php
		foreach ( $meta as $meta_key => $meta_value ):
			$sub_template = '';

			// determine the filename of the sub template to include for this meta data's display
			switch ( $meta_key ) {
				case 'info':
					if ( 'yes' == apply_filters( 'qsot-get-option-value', 'no', 'qsot-venue-show-address' ) )
						$sub_template = 'post-content/venue/contact-info.php';
				break;

				case 'social':
					if ( 'yes' == apply_filters( 'qsot-get-option-value', 'no', 'qsot-venue-show-social' ) )
						$sub_template = 'post-content/venue/social.php';
				break;
			}

			// allow modification of template name
			$sub_template = apply_filters( 'qsot-venue-data-template', $sub_template, $meta_key, $meta_value );

			// find the actual path to the template file
			$sub_template = apply_filters( 'qsot-locate-template', '', array( $sub_template ), false, false );

			// only include the template if a filename is present. file_exists check is done elsewhere
			if ( $sub_template )
				include $sub_template;
		endforeach;
	?>

	<?php do_action( 'qsot-after-venue-info-inside', $venue_id, $meta ) ?>
</div>
<?php do_action( 'qsot-after-venue-info-outside', $venue_id, $meta ) ?>
