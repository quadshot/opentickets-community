<?php if ( __FILE__ == $_SERVER['SCRIPT_FILENAME'] ) die( header( 'Location: /') );
/**
 * OpenTickets General Settings
 *
 * @author 		Quadshot (modeled from work done by WooThemes)
 * @category 	Admin
 * @package 	OpenTickets/Admin
 * @version   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( ! class_exists( 'qsot_Settings_General' ) ) :

class qsot_Settings_General extends QSOT_Settings_Page {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id    = 'general';
		$this->label = __( 'General', 'opentickets-community-edition' );

		add_action( 'qsot_sections_' . $this->id, array( $this, 'output_sections' ) );
		add_filter( 'qsot_settings_tabs_array', array( $this, 'add_settings_page' ), 20 );
		add_action( 'qsot_settings_' . $this->id, array( $this, 'output' ) );
		add_action( 'qsot_settings_save_' . $this->id, array( $this, 'save' ) );

		if ( ( $styles = WC_Frontend_Scripts::get_styles() ) && array_key_exists( 'woocommerce-general', $styles ) )
			add_action( 'woocommerce_admin_field_frontend_styles', array( $this, 'frontend_styles_setting' ) );
	}

	// list of subnav sections on the general tab
	public function get_sections() {
		$sections = apply_filters( 'qsot-settings-general-sections', array(
			'' => __( 'Site Wide', 'opentickets-community-edition' ),
			'reservations' => __( 'Reservations', 'opentickets-community-edition' ),
			'wc-emails' => __( 'WooCommerce Emails', 'opentickets-community-edition' ),
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

	/**
	 * Save settings
	 */
	public function save() {
		$settings = $this->get_settings();

		WC_Admin_Settings::save_fields( $settings );
	}

}

endif;

return new qsot_Settings_General();
