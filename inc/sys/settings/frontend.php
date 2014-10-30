<?php
/**
 * OpenTickets General Settings
 *
 * @author 		Quadshot (modeled from work done by WooThemes)
 * @category 	Admin
 * @package 	OpenTickets/Admin
 * @version   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( ! class_exists( 'qsot_Settings_Frontend' ) ) :

class qsot_Settings_Frontend extends WC_Settings_Page {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id    = 'frontend';
		$this->label = __( 'Frontend', 'qsot' );

		add_filter( 'qsot_settings_tabs_array', array( $this, 'add_settings_page' ), 20 );
		add_action( 'qsot_settings_' . $this->id, array( $this, 'output' ) );
		add_action( 'qsot_settings_save_' . $this->id, array( $this, 'save' ) );

		if ( ( $styles = WC_Frontend_Scripts::get_styles() ) && array_key_exists( 'woocommerce-general', $styles ) )
			add_action( 'woocommerce_admin_field_qsot_frontend_styles', array( $this, 'frontend_styles_setting' ) );
	}

	/**
	 * Get settings array
	 *
	 * @return array
	 */
	public function get_settings() {
		return apply_filters( 'qsot-get-page-settings', array(), $this->id );
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

				if ( is_writable( $base_file ) && is_writable( $css_file ) ) {
					$options = qsot_options::instance();

					// Get settings
					$colors = array_map( 'esc_attr', (array) $options->{'qsot-event-frontend-colors'} );

					// Defaults
					if ( empty( $colors['form_bg'] ) ) $colors['form_bg'] = '#f4f4f4';
					if ( empty( $colors['form_border'] ) ) $colors['form_border'] = '#888888';
					if ( empty( $colors['form_action_bg'] ) ) $colors['form_action_bg'] = '#888888';
					if ( empty( $colors['form_helper'] ) ) $colors['form_helper'] = '#757575';

					if ( empty( $colors['good_msg_bg'] ) ) $colors['good_msg_bg'] = '#eeffee';
					if ( empty( $colors['good_msg_border'] ) ) $colors['good_msg_border'] = '#008800';
					if ( empty( $colors['good_msg_text'] ) ) $colors['good_msg_text'] = '#008800';

					if ( empty( $colors['bad_msg_bg'] ) ) $colors['bad_msg_bg'] = '#ffeeee';
					if ( empty( $colors['bad_msg_border'] ) ) $colors['bad_msg_border'] = '#880000';
					if ( empty( $colors['bad_msg_text'] ) ) $colors['bad_msg_text'] = '#880000';

					if ( empty( $colors['remove_bg'] ) ) $colors['remove_bg'] = '#880000';
					if ( empty( $colors['remove_border'] ) ) $colors['remove_border'] = '#660000';
					if ( empty( $colors['remove_text'] ) ) $colors['remove_text'] = '#ffffff';

					// Show inputs
					$this->color_picker(
						__( 'Form BG', 'qsot' ),
						'qsot_frontend_css_form_bg',
						$colors['form_bg'],
						__( 'Background color of the "reserve some tickets" form on the event page.', 'qsot' )
					);
					$this->color_picker(
						__( 'Form Border', 'qsot' ),
						'qsot_frontend_css_form_border',
						$colors['form_border'],
						__( 'Border color around the "reserve some tickets" form.', 'qsot' )
					);
					$this->color_picker(
						__( 'Action BG', 'qsot' ),
						'qsot_frontend_css_form_action_bg',
						$colors['form_action_bg'],
						__( 'Background of the "action" section, below the "reserve some tickets" form, where the proceed to cart button appears.', 'qsot' )
					);
					$this->color_picker(
						__( 'Helper', 'qsot' ),
						'qsot_frontend_css_form_helper',
						$colors['form_helper'],
						__( 'Text color of the "helper text" on the "reserve some tickets" form.', 'qsot' )
					);
					echo '<div class="clear"></div>';

					$this->color_picker(
						__( 'Bad BG', 'qsot' ),
						'qsot_frontend_css_bad_msg_bg',
						$colors['bad_msg_bg'],
						__( 'Background color of the error message block on the "reserve some tickets" form.', 'qsot' )
					);
					$this->color_picker(
						__( 'Bad Border', 'qsot' ),
						'qsot_frontend_css_bad_msg_border',
						$colors['bad_msg_border'],
						__( 'Border color around the error message block on the "reserve some tickets" form.', 'qsot' )
					);
					$this->color_picker(
						__( 'Bad Text', 'qsot' ),
						'qsot_frontend_css_bad_msg_text',
						$colors['bad_msg_text'],
						__( 'Text color of the error message block on the "reserve some tickets" form.', 'qsot' )
					);
					echo '<div class="clear"></div>';

					$this->color_picker(
						__( 'Good BG', 'qsot' ),
						'qsot_frontend_css_good_msg_bg',
						$colors['good_msg_bg'],
						__( 'Background color of the success message block on the "reserve some tickets" form.', 'qsot' )
					);
					$this->color_picker(
						__( 'Good Border', 'qsot' ),
						'qsot_frontend_css_good_msg_border',
						$colors['good_msg_border'],
						__( 'Border color around the success message block on the "reserve some tickets" form.', 'qsot' )
					);
					$this->color_picker(
						__( 'Good Text', 'qsot' ),
						'qsot_frontend_css_good_msg_text',
						$colors['good_msg_text'],
						__( 'Text color of the success message block on the "reserve some tickets" form.', 'qsot' )
					);
					echo '<div class="clear"></div>';

					$this->color_picker(
						__( 'Remove BG', 'qsot' ),
						'qsot_frontend_css_remove_bg',
						$colors['remove_bg'],
						__( 'Background color of the remove reservation button on the "reserve some tickets" form.', 'qsot' )
					);
					$this->color_picker(
						__( 'Remove Border', 'qsot' ),
						'qsot_frontend_css_remove_border',
						$colors['remove_border'],
						__( 'Border color around the remove reservation button on the "reserve some tickets" form.', 'qsot' )
					);
					$this->color_picker(
						__( 'Remove Text', 'qsot' ),
						'qsot_frontend_css_remove_text',
						$colors['remove_text'],
						__( 'Text color of the remove reservation button on the "reserve some tickets" form.', 'qsot' )
					);
				} else {
					echo '<span class="description">' . sprintf(
						__( 'To edit colours %s and %s need to be writable. See <a href="%s">the Codex</a> for more information.', 'qsot' ),
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
	function color_picker( $name, $id, $value, $desc = '' ) {
		echo '<div class="color_box"><strong><img class="help_tip" data-tip="' . esc_attr( $desc ) . '" src="' . WC()->plugin_url() . '/assets/images/help.png" height="16" width="16" /> ' . esc_html( $name ) . '</strong>
	   		<input name="' . esc_attr( $id ). '" id="' . esc_attr( $id ) . '" type="text" value="' . esc_attr( $value ) . '" class="colorpick" /> <div id="colorPickerDiv_' . esc_attr( $id ) . '" class="colorpickdiv"></div>
	    </div>';
	}

	/**
	 * Save settings
	 */
	public function save() {
		$settings = $this->get_settings();

		WC_Admin_Settings::save_fields( $settings );

		if ( isset( $_POST['qsot_frontend_css_form_bg'] ) ) {

			// Save settings
			$colors = array();
			foreach ( array( 'form_bg', 'form_border', 'form_action_bg', 'form_helper' ) as $k )
				$colors[$k] = ! empty( $_POST[ 'qsot_frontend_css_' . $k ] ) ? wc_format_hex( $_POST[ 'qsot_frontend_css_' . $k ] ) : '';

			foreach ( array( 'good_msg', 'bad_msg', 'remove' ) as $K )
				foreach ( array( '_bg', '_border', '_text' ) as $k )
					$colors[ $K . $k ] = ! empty( $_POST[ 'qsot_frontend_css_' . $K . $k ] ) ? wc_format_hex( $_POST[ 'qsot_frontend_css_' . $K . $k ] ) : '';

			// Check the colors.
			$valid_colors = true;
			foreach ( $colors as $color ) {
				if ( ! preg_match( '/^#[a-f0-9]{6}$/i', $color ) ) {
					$valid_colors = false;
					WC_Admin_Settings::add_error( sprintf( __( 'Error saving the Frontend Styles, %s is not a valid color, please use only valid colors code.', 'qsot' ), $color ) );
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
