<?php if ( __FILE__ == $_SERVER['SCRIPT_FILENAME'] ) die( header( 'Location: /') );
/**
 * OpenTickets API Settings
 *
 * @author 		Quadshot (modeled from work done by WooThemes)
 * @category 	Admin
 * @package 	OpenTickets/Admin
 * @version   2.5.0
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( ! class_exists( 'QSOT_Settings_API' ) ) :

class QSOT_Settings_API extends QSOT_Settings_Page {
	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id = 'api';
		$this->label = __( 'API', 'opentickets-community-edition' );

		add_action( 'qsot_sections_' . $this->id, array( $this, 'output_sections' ) );
		add_filter( 'qsot_settings_tabs_array', array( $this, 'add_settings_page' ), 20 );
		add_action( 'qsot_settings_' . $this->id, array( $this, 'output' ) );
		add_action( 'qsot_settings_save_' . $this->id, array( $this, 'save' ) );

		if ( ( $styles = WC_Frontend_Scripts::get_styles() ) && array_key_exists( 'woocommerce-general', $styles ) )
			add_action( 'woocommerce_admin_field_frontend_styles', array( $this, 'frontend_styles_setting' ) );
	}

	// list of subnav sections on the general tab
	public function get_sections() {
		$sections = apply_filters( 'qsot-settings-api-sections', array(
			'' => __( 'API Keys', 'opentickets-community-edition' ),
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
		$settings = array();
		// setup som default settings
		if ( QSOT_API::is_disabled() ) {
			// heading
			$settings[] = array(
				'order' => 100,
				'type' => 'title',
				'title' => __( 'API is Disabled', 'opentickets-community-edition' ),
				'id' => 'heading-api-keys-1',
				'page' => 'frontend',
				'section' => '',
			);

			// end section
			$settings[] = array(
				'order' => 199,
				'type' => 'sectionend',
				'id' => 'heading-api-keys-1',
				'page' => '',
			);
		} else {
			// heading
			$settings[] = array(
				'order' => 100,
				'type' => 'title',
				'title' => __( 'App API Credentials', 'opentickets-community-edition' ),
				'id' => 'heading-api-keys-1',
				'page' => 'frontend',
				'section' => '',
			);

			// api key
			$settings[] = array(
				'order' => 105,
				'id' => 'qsot-app-api-key',
				'type' => 'text',
				'title' => __( 'API Key', 'opentickets-community-edition' ),
				'desc' => __( 'API identification key. Used to authenticate remote applications with our OpenTickets API. This is like a username for your App.', 'opentickets-community-edition' ),
				'desc_tip' => __( 'random numbers and letters', 'opentickets-community-edition' ),
				'page' => '',
				'class' => 'widefat',
			);

			// api secret key
			$settings[] = array(
				'order' => 106,
				'id' => 'qsot-app-api-secret',
				'type' => 'text',
				'title' => __( 'API Secret', 'opentickets-community-edition' ),
				'desc' => __( 'API identification secret key. Used to authenticate the identity of your App. This is like a password, so keep it safe.', 'opentickets-community-edition' ),
				'desc_tip' => __( 'random numbers and letters', 'opentickets-community-edition' ),
				'page' => '',
				'class' => 'widefat',
			);

			// end section
			$settings[] = array(
				'order' => 199,
				'type' => 'sectionend',
				'id' => 'heading-api-keys-1',
				'page' => '',
			);
		}

		return apply_filters( 'qsot-get-page-settings', $settings, $this->id, $current_section );
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

return new QSOT_Settings_API();
