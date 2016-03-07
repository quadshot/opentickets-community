<?php if ( __FILE__ == $_SERVER['SCRIPT_FILENAME'] ) die( header( 'Location: /') );
/**
 * OpenTickets Frontend Settings
 *
 * @author 		Quadshot (modeled from work done by WooThemes)
 * @category 	Admin
 * @package 	OpenTickets/Admin
 * @version   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( ! class_exists( 'qsot_Settings_Frontend' ) ) :

class qsot_Settings_Frontend extends QSOT_Settings_Page {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id    = 'frontend';
		$this->label = __( 'Frontend', 'opentickets-community-edition' );

		add_action( 'qsot_sections_' . $this->id, array( $this, 'output_sections' ) );
		add_filter( 'qsot_settings_tabs_array', array( $this, 'add_settings_page' ), 20 );
		add_action( 'qsot_settings_' . $this->id, array( $this, 'output' ) );
		add_action( 'qsot_settings_save_' . $this->id, array( $this, 'save' ) );

		if ( ( $styles = WC_Frontend_Scripts::get_styles() ) && array_key_exists( 'woocommerce-general', $styles ) )
			add_action( 'woocommerce_admin_field_qsot_frontend_styles', array( $this, 'frontend_styles_setting' ) );

		add_action( 'woocommerce_admin_field_qsot-image-ids', array( $this, 'image_ids_setting' ), 1000, 1 );
	}

	// list of subnav sections on the general tab
	public function get_sections() {
		$sections = apply_filters( 'qsot-settings-general-sections', array(
			'' => __( 'Events', 'opentickets-community-edition' ),
			'styles' => __( 'Styles & Colors', 'opentickets-community-edition' ),
			'calendar' => __( 'Calendar', 'opentickets-community-edition' ),
			'tickets' => __( 'Tickets', 'opentickets-community-edition' ),
			'venues' => __( 'Venues', 'opentickets-community-edition' ),
			'my-account' => __( 'My Account', 'opentickets-community-edition' ),
		) );

		return $sections;
	}

	/**
	 * Get settings array
	 *
	 * @return array
	 */
	public function get_page_settings() {
		global $current_section;
		return apply_filters( 'qsot-get-page-settings', array(), $this->id, $current_section );
	}

	// draw the 'qsot-image-ids' setting fields
	public function image_ids_setting( $value ) {
		$current = get_option( $value['id'], '' );
		$current = is_scalar( $current ) ? explode( ',', $current ) : $current;

		// normalize the args
		$value = wp_parse_args( $value, array(
			'static_at_front' => false,
			'static_at_end' => true,
			'hide_static' => false,
			'static_url' => QSOT::plugin_url() . 'assets/imgs/opentickets-tiny.jpg',
		) );

		// add to the description if any images are static and unchangeable
		if ( ! $value['hide_static'] && $value['static_at_front'] )
			$value['desc'] .= ' ' . __( 'The first image cannot be changed.', 'opentickets-community-edition' );
		elseif ( ! $value['hide_static'] && $value['static_at_end'] )
			$value['desc'] .= ' ' . __( 'The last image cannot be changed.', 'opentickets-community-edition' );

		$offset = 0;
		?>
			<tr valign="top" class="qsot-image-ids">
				<th scope="row" class="titledesc">
					<?php echo force_balance_tags( $value['title'] ) ?>
					<?php if ( isset( $value['desc_tip'] ) ): ?>
						<img class="help_tip" data-tip="<?php echo esc_attr( $value['desc_tip'] ) ?>" src="<?php echo WC()->plugin_url() ?>/assets/images/help.png" height="16" width="16" />
					<?php endif; ?>
				</th>
				<td>
					<p class="description"><?php echo $value['desc'] ?></p>

					<?php if ( ! $value['hide_static'] && $value['static_at_front'] ): // if there is a static image up front, then show that there is, and that it is not editable ?>
						<?php $tag = sprintf( '<img src="%s" class="static-img" />', $value['static_url'] ) ?>
						<div class="image-id-selection">
							<label><?php echo sprintf( __( 'Image #%s', 'opentickets-community-edition' ), 1 ) ?></label>
							<div class="preview-img" rel="image-preview"><?php echo $tag ?></div>
							<div class="clear"></div>
							<span class="not-editable"><em><?php _e( 'static image', 'opentickets-community-edition' ) ?></em></span>
						</div>
					<?php $offset = 1; endif; ?>

					<?php for ( $i = $offset; $i < $value['count'] + $offset; $i++ ): ?>
						<?php
							$img_id = isset( $current[ $i ] ) ? $current[ $i ] : 0;
							$tag = is_numeric( $img_id ) ? wp_get_attachment_image( $img_id, array( 90, 15 ), false ) : '';
						?>
						<div class="image-id-selection <?php echo ( 'noimg' == $img_id ) ? 'no-img' : '' ?>" rel="image-select">
							<label><?php echo sprintf( __( 'Image #%s', 'opentickets-community-edition' ), $i + 1 ) ?></label>
							<div class="preview-img" rel="image-preview"><?php echo $tag ?></div>
							<div class="clear"></div>
							<input type="hidden" name="<?php echo esc_attr( $value['id'] . '[' . $i . ']' ) ?>" value="<?php echo esc_attr( $img_id ) ?>" class="image-id" rel="img-id" />
							<input type="button" class="button select-button qsot-popmedia" value="Select Image" rel="select-image-btn" scope="[rel='image-select']" /><br/>
							<a href="#remove-img" class="remove-img" rel="remove-img" scope="[rel='image-select']"><?php _e( 'remove image', 'opentickets-community-edition' ) ?></a><br/>
							<a href="#no-img" class="no-image" rel="no-img" scope="[rel='image-select']"><?php _e( 'no image', 'opentickets-community-edition' ) ?></a>
						</div>
					<?php endfor; ?>

					<?php if ( ! $value['hide_static'] && $value['static_at_end'] ): // if there is a static image at the end, then show that there is, and that it is not editable ?>
						<?php $tag = sprintf( '<img src="%s" class="static-img" />', $value['static_url'] ) ?>
						<div class="image-id-selection">
							<label><?php echo sprintf( __( 'Image #%s', 'opentickets-community-edition' ), $value['count'] + $offset + 1 ) ?></label>
							<div class="preview-img" rel="image-preview"><?php echo $tag ?></div>
							<div class="clear"></div>
							<span class="not-editable"><em><?php _e( 'static image', 'opentickets-community-edition' ) ?></em></span>
						</div>
					<?php $offset = 1; endif; ?>

					<div class="clear"></div>
				</td>
			</tr>
		<?php
	}

	/**
	 * Output the frontend styles settings.
	 *
	 * @access public
	 * @return void
	 */
	public function frontend_styles_setting() {
		?><tr valign="top" class="woocommerce_frontend_css_colors">
			<th scope="row" class="titledesc">
				<?php _e( 'Frontend Styles', 'qsot' ); ?>
			</th>
			<td class="forminp"><?php
				$base_file = QSOT::plugin_dir() . 'assets/css/frontend/event-base.less';
				$css_file = QSOT::plugin_dir() . 'assets/css/frontend/event.css';

				if ( is_writable( $base_file ) && is_writable( dirname( $css_file ) ) ) {
					$options = qsot_options::instance();

					// Get settings
					$colors = array_map( 'esc_attr', QSOT::current_colors() );
					$defaults = array_map( 'esc_attr', QSOT::default_colors() );

					// Show inputs
					echo '<div class="color-selection">';
					echo '<h4>' . __( 'Ticket Selection UI', 'opentickets-community-edition' ) . '</h4>';
					$this->color_picker(
						__( 'Form BG','opentickets-community-edition'),
						'qsot_frontend_css_form_bg',
						$colors['form_bg'], $defaults['form_bg'],
						__( 'Background color of the "reserve some tickets" form on the event page.','opentickets-community-edition' )
					);
					$this->color_picker(
						__( 'Form Border','opentickets-community-edition' ),
						'qsot_frontend_css_form_border',
						$colors['form_border'], $defaults['form_border'],
						__( 'Border color around the "reserve some tickets" form.','opentickets-community-edition' )
					);
					$this->color_picker(
						__( 'Action BG','opentickets-community-edition' ),
						'qsot_frontend_css_form_action_bg',
						$colors['form_action_bg'], $defaults['form_action_bg'],
						__( 'Background of the "action" section, below the "reserve some tickets" form, where the proceed to cart button appears.','opentickets-community-edition' )
					);
					$this->color_picker(
						__( 'Helper','opentickets-community-edition' ),
						'qsot_frontend_css_form_helper',
						$colors['form_helper'], $defaults['form_helper'],
						__( 'Text color of the "helper text" on the "reserve some tickets" form.','opentickets-community-edition' )
					);
					echo '<div class="clear"></div>';

					$this->color_picker(
						__( 'Bad BG','opentickets-community-edition' ),
						'qsot_frontend_css_bad_msg_bg',
						$colors['bad_msg_bg'], $defaults['bad_msg_bg'],
						__( 'Background color of the error message block on the "reserve some tickets" form.','opentickets-community-edition' )
					);
					$this->color_picker(
						__( 'Bad Border','opentickets-community-edition' ),
						'qsot_frontend_css_bad_msg_border',
						$colors['bad_msg_border'], $defaults['bad_msg_border'],
						__( 'Border color around the error message block on the "reserve some tickets" form.','opentickets-community-edition' )
					);
					$this->color_picker(
						__( 'Bad Text','opentickets-community-edition' ),
						'qsot_frontend_css_bad_msg_text',
						$colors['bad_msg_text'], $defaults['bad_msg_text'],
						__( 'Text color of the error message block on the "reserve some tickets" form.','opentickets-community-edition' )
					);
					echo '<div class="clear"></div>';

					$this->color_picker(
						__( 'Good BG','opentickets-community-edition' ),
						'qsot_frontend_css_good_msg_bg',
						$colors['good_msg_bg'], $defaults['good_msg_bg'],
						__( 'Background color of the success message block on the "reserve some tickets" form.','opentickets-community-edition' )
					);
					$this->color_picker(
						__( 'Good Border','opentickets-community-edition' ),
						'qsot_frontend_css_good_msg_border',
						$colors['good_msg_border'], $defaults['good_msg_border'],
						__( 'Border color around the success message block on the "reserve some tickets" form.','opentickets-community-edition' )
					);
					$this->color_picker(
						__( 'Good Text','opentickets-community-edition' ),
						'qsot_frontend_css_good_msg_text',
						$colors['good_msg_text'], $defaults['good_msg_text'],
						__( 'Text color of the success message block on the "reserve some tickets" form.','opentickets-community-edition' )
					);
					echo '<div class="clear"></div>';

					$this->color_picker(
						__( 'Remove BG','opentickets-community-edition' ),
						'qsot_frontend_css_remove_bg',
						$colors['remove_bg'], $defaults['remove_bg'],
						__( 'Background color of the remove reservation button on the "reserve some tickets" form.','opentickets-community-edition' )
					);
					$this->color_picker(
						__( 'Remove Border','opentickets-community-edition' ),
						'qsot_frontend_css_remove_border',
						$colors['remove_border'], $defaults['remove_border'],
						__( 'Border color around the remove reservation button on the "reserve some tickets" form.','opentickets-community-edition' )
					);
					$this->color_picker(
						__( 'Remove Text','opentickets-community-edition' ),
						'qsot_frontend_css_remove_text',
						$colors['remove_text'], $defaults['remove_text'],
						__( 'Text color of the remove reservation button on the "reserve some tickets" form.','opentickets-community-edition' )
					);
					echo '<div class="clear"></div>';
					echo '<a href="#" rel="reset-colors">' . __( 'reset colors', 'opentickets-commnunity-edition' ) . '</a>';
					echo '</div>';

					// calendar
					echo '<div class="color-selection">';
					echo '<h4>' . __( 'Calendar', 'openticket-community-edition' ) . '</h4>';
					$this->color_picker(
						__( 'Calendar Item BG','opentickets-community-edition' ),
						'qsot_frontend_css_calendar_item_bg',
						$colors['calendar_item_bg'], $defaults['calendar_item_bg'],
						__( 'The background color of the active items shown on the calendar.','opentickets-community-edition' )
					);
					$this->color_picker(
						__( 'Calendar Item Border','opentickets-community-edition' ),
						'qsot_frontend_css_calendar_item_border',
						$colors['calendar_item_border'], $defaults['calendar_item_border'],
						__( 'The border color of the active items shown on the calendar.','opentickets-community-edition' )
					);
					$this->color_picker(
						__( 'Calendar Item Text','opentickets-community-edition' ),
						'qsot_frontend_css_calendar_item_text',
						$colors['calendar_item_text'], $defaults['calendar_item_text'],
						__( 'The text color of the active items shown on the calendar.','opentickets-community-edition' )
					);
					$this->color_picker(
						__( 'Calendar Item BG Hover','opentickets-community-edition' ),
						'qsot_frontend_css_calendar_item_bg_hover',
						$colors['calendar_item_bg_hover'], $defaults['calendar_item_bg_hover'],
						__( 'The HOVER background color of the active items shown on the calendar.','opentickets-community-edition' )
					);
					$this->color_picker(
						__( 'Calendar Item Border Hover','opentickets-community-edition' ),
						'qsot_frontend_css_calendar_item_border_hover',
						$colors['calendar_item_border_hover'], $defaults['calendar_item_border_hover'],
						__( 'The HOVER border color of the active items shown on the calendar.','opentickets-community-edition' )
					);
					$this->color_picker(
						__( 'Calendar Item Text Hover','opentickets-community-edition' ),
						'qsot_frontend_css_calendar_item_text_hover',
						$colors['calendar_item_text_hover'], $defaults['calendar_item_text_hover'],
						__( 'The HOVER text color of the active items shown on the calendar.','opentickets-community-edition' )
					);

					echo '<div class="clear"></div>';
					$this->color_picker(
						__( 'Past Item BG','opentickets-community-edition' ),
						'qsot_frontend_css_past_calendar_item_bg',
						$colors['past_calendar_item_bg'], $defaults['past_calendar_item_bg'],
						__( 'The background color of the items shown on the calendar, that have already passed.','opentickets-community-edition' )
					);
					$this->color_picker(
						__( 'Past Item Border','opentickets-community-edition' ),
						'qsot_frontend_css_past_calendar_item_border',
						$colors['past_calendar_item_border'], $defaults['past_calendar_item_border'],
						__( 'The border color of the active items shown on the calendari, that have already passed.','opentickets-community-edition' )
					);
					$this->color_picker(
						__( 'Past Item Text','opentickets-community-edition' ),
						'qsot_frontend_css_past_calendar_item_text',
						$colors['past_calendar_item_text'], $defaults['past_calendar_item_text'],
						__( 'The text color of the active items shown on the calendar, that have already passed.','opentickets-community-edition' )
					);
					$this->color_picker(
						__( 'Past Item BG Hover','opentickets-community-edition' ),
						'qsot_frontend_css_past_calendar_item_bg_hover',
						$colors['past_calendar_item_bg_hover'], $defaults['past_calendar_item_bg_hover'],
						__( 'The HOVER background color of the items shown on the calendar, that have already passed.','opentickets-community-edition' )
					);
					$this->color_picker(
						__( 'Past Item Border Hover','opentickets-community-edition' ),
						'qsot_frontend_css_past_calendar_item_border_hover',
						$colors['past_calendar_item_border_hover'], $defaults['past_calendar_item_border_hover'],
						__( 'The HOVER border color of the active items shown on the calendari, that have already passed.','opentickets-community-edition' )
					);
					$this->color_picker(
						__( 'Past Item Text Hover','opentickets-community-edition' ),
						'qsot_frontend_css_past_calendar_item_text_hover',
						$colors['past_calendar_item_text_hover'], $defaults['past_calendar_item_text_hover'],
						__( 'The HOVER text color of the active items shown on the calendar, that have already passed.','opentickets-community-edition' )
					);
					echo '<div class="clear"></div>';
					echo '<a href="#" rel="reset-colors">' . __( 'reset colors', 'opentickets-commnunity-edition' ) . '</a></div>';
				} else {
					echo '<span class="description error-msg">' . sprintf(
						__( 'To edit colours %s and %s need to be writable. See <a href="%s">the Codex</a> for more information.','opentickets-community-edition' ),
						'<code>opentickets-community-edition/assets/css/frontend/event-base.less</code>',
						'<code>event.css</code>',
						'http://codex.wordpress.org/Changing_File_Permissions'
					) . '</span>';
				}

			?></td>
		</tr><?php
	}

	/**
	 * Output a colour picker input box.
	 *
	 * @access public
	 * @param mixed $name
	 * @param mixed $id
	 * @param mixed $value
	 * @param string $desc (default: '')
	 * @return void
	 */
	function color_picker( $name, $id, $value, $default = '', $desc = '' ) {
		$default = ! empty( $default ) ? $default : $value;
		echo '<div class="color_box"><strong><img class="help_tip" data-tip="' . esc_attr( $desc ) . '" src="' . WC()->plugin_url() . '/assets/images/help.png" height="16" width="16" /> ' . esc_html( $name ) . '</strong>'
	   		. '<input data-default="' . esc_attr( $default ) . '" name="' . esc_attr( $id ). '" id="' . esc_attr( $id ) . '" type="text" value="' . esc_attr( $value ) . '"
						class="clrpick" style="background-color:' . esc_attr( $value ) . '" />'
				. '<div id="colorPickerDiv_' . esc_attr( $id ) . '" class="colorpickdiv"></div>'
				. '<span class="cb-wrap"><input type="checkbox" rel="transparent" name="' . esc_attr( $id ) . '_transparent" value="1" ' . ( 'transparent' == $value ? 'checked="checked"' : '' ) . ' />transparent</span>'
	    . '</div>';
	}

	/**
	 * Save settings
	 */
	public function save() {
		$settings = $this->get_settings();

		$filtered_settings = $image_id_fields = array();
		// filter out the image ids types, because WC barfs on itself over them
		foreach ( $settings as $field ) {
			if ( 'qsot-image-ids' == $field['type'] ) {
				$image_id_fields[] = $field;
			} else {
				$filtered_settings[] = $field;
			}
		}

		// only allow wc to save the 'safe' ones
		WC_Admin_Settings::save_fields( $filtered_settings );

		// handle any image id fields
		foreach ( $image_id_fields as $field ) {
			// if the field did not have any values passed, then skip it
			if ( ! isset( $_POST[ $field['id'] ] ) )
				continue;

			$raw_values = $_POST[ $field['id'] ];
			// next sanitize the individual values for the field
			$values = array_filter( $raw_values );

			// allow modification of the data
			$values = apply_filters( 'woocommerce_admin_settings_sanitize_option', $values, $field, $raw_values );
			$values = apply_filters( 'woocommerce_admin_settings_sanitize_option_' . $field['id'], $values, $field, $raw_values );

			// update the value
			update_option( $field['id'], $values );
		}

		if ( isset( $_POST['qsot_frontend_css_form_bg'] ) ) {

			// Save settings
			$colors = array();
			foreach ( array( 'form_bg', 'form_border', 'form_action_bg', 'form_helper' ) as $k )
				if ( isset( $_POST[ 'qsot_frontend_css_' . $k . '_transparent' ] ) && $_POST[ 'qsot_frontend_css_' . $k . '_transparent' ] )
					$colors[ $k ] = 'transparent';
				else if ( ! empty( $_POST[ 'qsot_frontend_css_' . $k ] ) )
					$colors[ $k ] = 'transparent' == $_POST[ 'qsot_frontend_css_' . $k ] ? 'transparent' : wc_format_hex( $_POST[ 'qsot_frontend_css_' . $k ] );
				else
					$colors[ $k ] = '';

			foreach ( array( 'good_msg', 'bad_msg', 'remove' ) as $K )
				foreach ( array( '_bg', '_border', '_text' ) as $k )
					if ( isset( $_POST[ 'qsot_frontend_css_' . $K. $k . '_transparent' ] ) && $_POST[ 'qsot_frontend_css_' . $K. $k . '_transparent' ] )
						$colors[ $K . $k ] = 'transparent';
					else if ( ! empty( $_POST[ 'qsot_frontend_css_' . $K . $k ] ) )
						$colors[ $K . $k ] = 'transparent' == $_POST[ 'qsot_frontend_css_' . $K . $k ] ? 'transparent' : wc_format_hex( $_POST[ 'qsot_frontend_css_' . $K . $k ] );
					else
						$colors[ $K . $k ] = '';

			foreach ( array( 'past_calendar_item', 'calendar_item' ) as $K )
				foreach ( array( '_bg', '_border', '_text', '_bg_hover', '_border_hover', '_text_hover' ) as $k )
					if ( isset( $_POST[ 'qsot_frontend_css_' . $K. $k . '_transparent' ] ) && $_POST[ 'qsot_frontend_css_' . $K. $k . '_transparent' ] )
						$colors[ $K . $k ] = 'transparent';
					else if ( ! empty( $_POST[ 'qsot_frontend_css_' . $K . $k ] ) )
						$colors[ $K . $k ] = 'transparent' == $_POST[ 'qsot_frontend_css_' . $K . $k ] ? 'transparent' : wc_format_hex( $_POST[ 'qsot_frontend_css_' . $K . $k ] );
					else
						$colors[ $K . $k ] = '';

			// Check the colors.
			$valid_colors = true;
			foreach ( $colors as $color ) {
				if ( 'transparent' != $color && ! preg_match( '/^#[a-f0-9]{6}$/i', $color ) ) {
					$valid_colors = false;
					WC_Admin_Settings::add_error( sprintf( __( 'Error saving the Frontend Styles, %s is not a valid color, please use only valid colors code.','opentickets-community-edition' ), $color ) );
					break;
				}
			}

			if ( $valid_colors ) {
				$old_colors = get_option( 'woocommerce_frontend_css_colors' );

				$options = qsot_options::instance();
				$options->{'qsot-event-frontend-colors'} = $colors;

				if ( $old_colors != $colors ) {
					QSOT::compile_frontend_styles();
				}
			}
		}
	}

}

endif;

return new qsot_Settings_Frontend();
