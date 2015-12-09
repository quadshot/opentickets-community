<?php if ( __FILE__ == $_SERVER['SCRIPT_FILENAME'] ) die( header( 'Location: /') ); ?>
<div class="clear"></div>
<h3><?php echo __( 'Contact Information:', 'opentickets-community-edition' ) ?></h3>
<dl class="no-formatting">
	<?php foreach ( $meta_value as $key => $value ): $value = trim( $value ); ?>
		<?php if ( '' !== $value ): ?>

			<?php
				// determine how to display the label and value of the field
				$label = $key;
				$display = $value;
				switch ( $key ) {
					case 'phone':
						$label = __( 'Phone:', 'opentickets-community-edition' );
						$display = wc_format_phone_number( $display );
					break;

					case 'website':
						$label = __( 'Website:', 'opentickets-community-edition' );
						$display = sprintf( '<a href="%1$s" title="%2$s">%1$s</a>', htmlspecialchars( esc_url( $display ) ), sprintf( __( 'Visit the %s', 'opentickets-community-edition' ), $label ) );
					break;

					case 'facebook':
						$label = __( 'Facebook:', 'opentickets-community-edition' );
						$display = sprintf( '<a href="%1$s" title="%2$s">%1$s</a>', htmlspecialchars( esc_url( $display ) ), sprintf( __( 'Visit the %s', 'opentickets-community-edition' ), $label ) );
					break;

					case 'twitter':
						$label = __( 'Twitter:', 'opentickets-community-edition' );
						$display = sprintf( '<a href="%1$s" title="%2$s">%1$s</a>', htmlspecialchars( esc_url( $display ) ), sprintf( __( 'Visit the %s', 'opentickets-community-edition' ), $label ) );
					break;

					case 'contact_email':
						$label = __( 'Contact Email:', 'opentickets-community-edition' );
						$display = sprintf( '<a href="mailto:%1$s" title="%2$s">%1$s</a>', htmlspecialchars( esc_url( $display ) ), __( 'Send an email', 'opentickets-community-edition' ) );
					break;


					default:
					break;
				}

				// allow modification before display
				list( $label, $display ) = apply_filters( 'qsot-venue-contact-info-' . $key, array( $label, $display ), $label, $display, $venue, $meta_value, $meta );
			?>

			<?php if ( '' != $label && '' != $display ): // if we actually have a key value pair to display, then do so ?>
				<dt><?php echo $label ?></dt>
				<dd><?php echo $display ?></dd>
			<?php endif; ?>

		<?php endif; ?>
	<?php endforeach; ?>
</dl>
