<?php
/**
 * OpenTickets General Settings
 *
 * @author 		Quadshot (modeled from work done by WooThemes)
 * @category 	Admin
 * @package 	OpenTickets/Admin
 * @version   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( ! class_exists( 'qsot_Settings_Lics' ) ) :

class qsot_Settings_Lics extends WC_Settings_Page {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id    = 'lics';
		$this->label = __( 'Lic'.'ens'.'es', 'qsot' );

		add_filter( 'qsot_settings_tabs_array', array( $this, 'add_settings_page' ), 20 );
		add_action( 'qsot_settings_' . $this->id, array( $this, 'output' ) );
		add_action( 'qsot_settings_save_' . $this->id, array( $this, 'save' ) );
		add_filter('qso'.'t_l'.'ic_'.'set'.'tin'.'gs', array(__CLASS__, 'core_settings'), 10);

		if ( ( $styles = WC_Frontend_Scripts::get_styles() ) && array_key_exists( 'woocommerce-general', $styles ) )
			add_action( 'woocommerce_admin_field_frontend_styles', array( $this, 'frontend_styles_setting' ) );
	}

	/**
	 * Get settings array
	 *
	 * @return array
	 */
	public function get_settings() {
		return apply_filters( 'qsot_lic_settings', array()); // End general settings
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
				<?php _e( 'Frontend Styles', 'woocommerce' ); ?>
			</th>
		    <td class="forminp"><?php

				$base_file		= WC()->plugin_path() . '/assets/css/woocommerce-base.less';
				$css_file		= WC()->plugin_path() . '/assets/css/woocommerce.css';

				if ( is_writable( $base_file ) && is_writable( $css_file ) ) {

					// Get settings
					$colors = array_map( 'esc_attr', (array) get_option( 'woocommerce_frontend_css_colors' ) );

					// Defaults
					if ( empty( $colors['primary'] ) ) $colors['primary'] = '#ad74a2';
					if ( empty( $colors['secondary'] ) ) $colors['secondary'] = '#f7f6f7';
					if ( empty( $colors['highlight'] ) ) $colors['highlight'] = '#85ad74';
					if ( empty( $colors['content_bg'] ) ) $colors['content_bg'] = '#ffffff';
		            if ( empty( $colors['subtext'] ) ) $colors['subtext'] = '#777777';

					// Show inputs
		    		$this->color_picker( __( 'Primary', 'woocommerce' ), 'woocommerce_frontend_css_primary', $colors['primary'], __( 'Call to action buttons/price slider/layered nav UI', 'woocommerce' ) );
		    		$this->color_picker( __( 'Secondary', 'woocommerce' ), 'woocommerce_frontend_css_secondary', $colors['secondary'], __( 'Buttons and tabs', 'woocommerce' ) );
		    		$this->color_picker( __( 'Highlight', 'woocommerce' ), 'woocommerce_frontend_css_highlight', $colors['highlight'], __( 'Price labels and Sale Flashes', 'woocommerce' ) );
		    		$this->color_picker( __( 'Content', 'woocommerce' ), 'woocommerce_frontend_css_content_bg', $colors['content_bg'], __( 'Your themes page background - used for tab active states', 'woocommerce' ) );
		    		$this->color_picker( __( 'Subtext', 'woocommerce' ), 'woocommerce_frontend_css_subtext', $colors['subtext'], __( 'Used for certain text and asides - breadcrumbs, small text etc.', 'woocommerce' ) );

		    	} else {
		    		echo '<span class="description">' . __( 'To edit colours <code>woocommerce/assets/css/woocommerce-base.less</code> and <code>woocommerce.css</code> need to be writable. See <a href="http://codex.wordpress.org/Changing_File_Permissions">the Codex</a> for more information.', 'woocommerce' ) . '</span>';
		    	}

		    ?></td>
		</tr><?php
	}

	public static function core_settings($settings) {
		$m = $n = 100;
		$settings_class_name = apply_filters('qsot-settings-class-name', '');
		if (!empty($settings_class_name)) {
			$o =& $settings_class_name::instance();
			if (is_object($o)) {
				$licsets = array();
				$addons = $o->addons;
				if (!empty($addons)) {
					foreach ($addons as $addon => $asets) {
						if (!empty($asets['code'])) {
							$licsets[] = array(
								'title' => $asets['name'].' Li'.'cen'.'se Em'.'ail', 'order' => ($n += 10), 'id' => $asets['slug'].'-li'.'c-em'.'ail', 'class' => 'widefat', 'style' => '', 'default' => '',
								'type' => 'text', 'desc' => '', 'desc'.'_tip' => '',
							);
							$licsets[] = array(
								'title' => $asets['name'].' Li'.'cen'.'se K'.'ey', 'order' => ($n += 10), 'id' => $asets['slug'].'-lic', 'class' => 'widefat', 'style' => '', 'default' => '',
								'type' => 'text', 'desc' => '', 'desc'.'_tip' => '',
							);
						}
					}
				}

				if (!empty($licsets)) {
					array_unshift($licsets, array(
						'title' => 'Lic'.'ens'.'e K'.'ey'.'s', 'order' => $m, 'id' => 'hea'.'din'.'g-l'.'ic', 'class' => 'widefat', 'style' => '', 'default' => '',
						'type' => 'title', 'desc' => '', 'desc'.'_tip' => '',
					));
					array_push($licsets, array(
						'title' => 'Lic'.'ens'.'e K'.'ey'.'s', 'order' => $m, 'id' => 'hea'.'din'.'g-l'.'ic', 'class' => 'widefat', 'style' => '', 'default' => '',
						'type' => 'sect'.'ion'.'end', 'desc' => '', 'desc'.'_tip' => '',
					));
					$settings = array_merge($settings, $licsets);
				}
			}
		}
		return $settings;
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
	}

}

endif;

$qsot_addons = apply_filters('qsot-get-addons', array());

return !empty($qsot_addons) ? new qsot_Settings_Lics() : null;
