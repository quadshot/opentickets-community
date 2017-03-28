<?php if ( __FILE__ == $_SERVER['SCRIPT_FILENAME'] ) die( header( 'Location: /') );

// page to handle settings for date-time formatting within our plugin
if ( ! class_exists( 'QSOT_Date_Settings_Page' ) ):
class QSOT_Date_Settings_Page extends QSOT_Settings_Page {
	// make ths a singleton
	protected static $_instance = null;
	public static function instance() { return ( self::$_instance instanceof self ) ? self::$_instance : ( self::$_instance = new self ); }
	// eventually change this to protected, ot make a true singleton. other pages need conversion
	public function __construct() {
		$this->id = 'dates';
		$this->label = __( 'Dates', 'opentickets-community-edition' );

		$this->_setup_settings_page_hooks();

		add_filter( 'qsot-get-page-settings', array( &$this, 'settings_for_page_section' ), 10, 3 );
	}

	// render the fields and description for the page
	public function output() {
		global $current_section;
		// write a different header depending on the section of the page
		switch ( $current_section ) {
			// php custom formats page
			case 'php-custom':
				?>
					<div class="page-description">
						<p><?php echo sprintf(
							__( 'All of these settings can be customized using a %sPHP date format%s. You can find full documentation of PHP date formatting, on %sthe php date function page%s.', 'opentickets-community-edition' ),
							'<u>',
							'</u>',
							'<a target="_blank" href="http://php.net/manual/en/function.date.php" title="Visit the PHP documentation page">',
							'</a>'
						) ?></p>
					</div>
				<?php
			break;

			// moment js custom formats page
			case 'moment-custom':
				?>
					<div class="page-description">
						<p><?php echo sprintf(
							__( 'All of these settings can be customized using a %sMomentJS date format%s. You can find full documentation of MomentJS date formatting, on %sthe MomentJS API page%s.', 'opentickets-community-edition' ),
							'<u>',
							'</u>',
							'<a target="_blank" href="http://momentjs.com/docs/#/displaying/" title="Visit the MomentJS documentation page">',
							'</a>'
						) ?></p>
					</div>
				<?php
			break;

			// jqueryui custom formats page
			case 'jqueryui-custom':
				?>
					<div class="page-description">
						<p><?php echo sprintf(
							__( 'All of these settings can be customized using a %sjQueryUI date format%s. You can find full documentation of jQueryUI date formatting, on %sthe dateFormat function API page%s.', 'opentickets-community-edition' ),
							'<u>',
							'</u>',
							'<a target="_blank" href="http://api.jqueryui.com/datepicker/#utility-formatDate" title="Visit the jQueryUI documentation page">',
							'</a>'
						) ?></p>
					</div>
				<?php
			break;
		}

		$settings = $this->get_settings();

		WC_Admin_Settings::output_fields( $settings );
	}
	
	// get all the settings for a given section of this page
	public function settings_for_page_section( $current, $page='', $section='' ) {
		// if not this page, skip
		if ( $page !== $this->id )
			return $current;

		static $all_settings = false;
		// load all the settings, only once
		if ( false == $all_settings )
			$all_settings = $this->_get_all_page_settings();

		// return only the settings for the given section
		return isset( $all_settings[ $section ] ) ? $all_settings[ $section ] : ( '' == $section && isset( $all_settings['general'] ) ? $all_settings['general'] : array() );
	}

	// list of subnav sections on the dates tab
	public function get_sections() {
		$sections = apply_filters( 'qsot-settings-general-sections', array(
			'' => __( 'General Formatting', 'opentickets-community-edition' ),
			'php-custom' => __( 'PHP Custom', 'opentickets-community-edition' ),
			'moment-custom' => __( 'MomentJS Custom', 'opentickets-community-edition' ),
			'jqueryui-custom' => __( 'jQueryUI Custom', 'opentickets-community-edition' ),
		) );

		return $sections;
	}

	// the basic settings for the page
	protected function _get_all_page_settings() {
		$sections = array();
		$sections['general'] = array(
			// GENERAL FORMATTING
			// date format ordering
			array(
				'order' => 100,
				'type' => 'title',
				'title' => __( 'Date Format', 'opentickets-community-edition' ),
				'id' => 'heading-date-format',
				'page' => 'dates',
				'section' => '',
			),
			array(
				'order' => 150,
				'id' => 'qsot-date-format',
				'type' => 'radio',
				'title' => __( 'Date Segment Order', 'opentickets-community-edition' ),
				'desc_tip' => __( 'Select the order of the date segments for date displays.', 'opentickets-community-edition' ),
				'options' => array(
					'm-d-Y' => __( 'Month-Day-Year', 'opentickets-community-edition' ),
					'd-m-Y' => __( 'Day-Month-Year', 'opentickets-community-edition' ),
					'Y-m-d' => __( 'Year-Month-Day', 'opentickets-community-edition' ),
				),
				'default' => 'm-d-Y',
				'page' => 'dates',
				'section' => '',
			),
			array(
				'order' => 199,
				'type' => 'sectionend',
				'id' => 'heading-date-format',
				'page' => 'dates',
				'section' => '',
			),
		);

		$sections['general'] = array_merge( $sections['general'], array(
			// time format ordering
			array(
				'order' => 300,
				'type' => 'title',
				'title' => __( 'Time Format', 'opentickets-community-edition' ),
				'id' => 'heading-date-format',
				'page' => 'dates',
				'section' => '',
			),
			array(
				'order' => 320,
				'id' => 'qsot-hour-format',
				'type' => 'radio',
				'title' => __( '12 or 24 hour', 'opentickets-community-edition' ),
				'desc_tip' => __( 'Select which time format to us, 12-hour or 24-hour time.', 'opentickets-community-edition' ),
				'options' => array(
					'12-hour' => __( '12-hour time', 'opentickets-community-edition' ),
					'24-hour' => __( '24-hour time', 'opentickets-community-edition' ),
				),
				'default' => '12-hour',
				'page' => 'dates',
				'section' => '',
			),
			array(
				'order' => 350,
				'id' => 'qsot-use-dst',
				'type' => 'radio',
				'title' => __( 'Acknowledge DST', 'opentickets-community-edition' ),
				'desc_tip' => __( 'Select whether your area of the world uses Daylight Savings Time (DST).', 'opentickets-community-edition' ),
				'options' => array(
					'yes' => __( 'Yes', 'opentickets-community-edition' ),
					'no' => __( 'No', 'opentickets-community-edition' ),
				),
				'default' => 'yes',
				'page' => 'dates',
				'section' => '',
			),
			array(
				'order' => 399,
				'type' => 'sectionend',
				'id' => 'heading-date-format',
				'page' => 'dates',
				'section' => '',
			),
		) );

		// PHP CUSTOM
		// date formats
		$custom_formats = array();
		$custom_formats[] = array(
			'order' => 100,
			'type' => 'title',
			'title' => __( 'Custom Date Formats', 'opentickets-community-edition' ),
			'id' => 'heading-date-format',
			'page' => 'dates',
			'section' => 'php-custom',
		);

		// PHP date name translations
		if ( function_exists( 'qtranxf_useCurrentLanguageIfNotFoundUseDefaultLanguage' ) ) {
			$custom_formats[] = array(
				'order' => 101,
				'id' => 'qsot-custom-php-date-format-locale',
				'type' => 'text',
				'title' => get_locale(),
				'class' => 'i18n-multilingual',
				'default' => '',
				'desc' => __( 'When using qtranslate-x, this field tells PHP what language to use for full and abreeviated month names.', 'opentickets-community-edition' ),
				'page' => 'dates',
				'section' => 'php-custom',
			);
		}

		foreach ( QSOT_Date_Formats::$php_custom_date_formats as $format ) {
			$custom_formats[] = array(
				'order' => 150,
				'id' => 'qsot-custom-php-date-format-' . sanitize_title_with_dashes( $format ),
				'type' => 'text',
				'title' => $format,
				'class' => 'i18n-multilingual',
				'default' => '',
				'page' => 'dates',
				'section' => 'php-custom',
			);
		}
		$custom_formats[] = array(
			'order' => 199,
			'type' => 'sectionend',
			'id' => 'heading-date-format',
			'page' => 'dates',
			'section' => 'php-custom',
		);

		// time formats
		$custom_formats[] = array(
			'order' => 100,
			'type' => 'title',
			'title' => __( 'Custom Time Formats', 'opentickets-community-edition' ),
			'id' => 'heading-date-format',
			'page' => 'dates',
			'section' => 'php-custom',
		);
		foreach ( QSOT_Date_Formats::$php_custom_time_formats as $format ) {
			$custom_formats[] = array(
				'order' => 150,
				'id' => 'qsot-custom-php-date-format-' . sanitize_title_with_dashes( $format ),
				'type' => 'text',
				'title' => $format,
				'class' => 'i18n-multilingual',
				'default' => '',
				'page' => 'dates',
				'section' => 'php-custom',
			);
		}
		$custom_formats[] = array(
			'order' => 199,
			'type' => 'sectionend',
			'id' => 'heading-date-format',
			'page' => 'dates',
			'section' => 'php-custom',
		);
		$sections['php-custom'] = $custom_formats;

		// MOMENTJS CUSTOM
		// date formats
		$custom_formats = array();
		if ( count( QSOT_Date_Formats::$moment_custom_date_formats ) ) {
			$custom_formats[] = array(
				'order' => 100,
				'type' => 'title',
				'title' => __( 'Custom Date Formats', 'opentickets-community-edition' ),
				'id' => 'heading-date-format',
				'page' => 'dates',
				'section' => 'moment-custom',
			);
			foreach ( QSOT_Date_Formats::$moment_custom_date_formats as $format ) {
				$custom_formats[] = array(
					'order' => 150,
					'id' => 'qsot-custom-moment-date-format-' . sanitize_title_with_dashes( $format ),
					'type' => 'text',
					'title' => $format,
					'class' => 'i18n-multilingual',
					'default' => '',
					'page' => 'dates',
					'section' => 'moment-custom',
				);
			}
			$custom_formats[] = array(
				'order' => 199,
				'type' => 'sectionend',
				'id' => 'heading-date-format',
				'page' => 'dates',
				'section' => 'moment-custom',
			);
		}

		// time formats
		if ( count( QSOT_Date_Formats::$moment_custom_time_formats ) ) {
			$custom_formats[] = array(
				'order' => 100,
				'type' => 'title',
				'title' => __( 'Custom Time Formats', 'opentickets-community-edition' ),
				'id' => 'heading-date-format',
				'page' => 'dates',
				'section' => 'moment-custom',
			);
			foreach ( QSOT_Date_Formats::$moment_custom_time_formats as $format ) {
				$custom_formats[] = array(
					'order' => 150,
					'id' => 'qsot-custom-moment-date-format-' . sanitize_title_with_dashes( $format ),
					'type' => 'text',
					'title' => $format,
					'class' => 'i18n-multilingual',
					'default' => '',
					'page' => 'dates',
					'section' => 'moment-custom',
				);
			}
			$custom_formats[] = array(
				'order' => 199,
				'type' => 'sectionend',
				'id' => 'heading-date-format',
				'page' => 'dates',
				'section' => 'moment-custom',
			);
		}
		$sections['moment-custom'] = $custom_formats;

		// JQUERY-UI CUSTOM
		// date formats
		$custom_formats = array();
		if ( count( QSOT_Date_Formats::$jqueryui_custom_date_formats ) ) {
			$custom_formats[] = array(
				'order' => 100,
				'type' => 'title',
				'title' => __( 'Custom Date Formats', 'opentickets-community-edition' ),
				'id' => 'heading-date-format',
				'page' => 'dates',
				'section' => 'jqueryui-custom',
			);
			foreach ( QSOT_Date_Formats::$jqueryui_custom_date_formats as $format ) {
				$custom_formats[] = array(
					'order' => 150,
					'id' => 'qsot-custom-jqueryui-date-format-' . sanitize_title_with_dashes( $format ),
					'type' => 'text',
					'title' => $format,
					'class' => 'i18n-multilingual',
					'default' => '',
					'page' => 'dates',
					'section' => 'jqueryui-custom',
				);
			}
			$custom_formats[] = array(
				'order' => 199,
				'type' => 'sectionend',
				'id' => 'heading-date-format',
				'page' => 'dates',
				'section' => 'jqueryui-custom',
			);
		}

		// time formats
		if ( count( QSOT_Date_Formats::$jqueryui_custom_date_formats ) ) {
			$custom_formats[] = array(
				'order' => 100,
				'type' => 'title',
				'title' => __( 'Custom Time Formats', 'opentickets-community-edition' ),
				'id' => 'heading-date-format',
				'page' => 'dates',
				'section' => 'jqueryui-custom',
			);
			foreach ( QSOT_Date_Formats::$jqueryui_custom_time_formats as $format ) {
				$custom_formats[] = array(
					'order' => 150,
					'id' => 'qsot-custom-jqueryui-date-format-' . sanitize_title_with_dashes( $format ),
					'type' => 'text',
					'title' => $format,
					'class' => 'i18n-multilingual',
					'default' => '',
					'page' => 'dates',
					'section' => 'jqueryui-custom',
				);
			}
			$custom_formats[] = array(
				'order' => 199,
				'type' => 'sectionend',
				'id' => 'heading-date-format',
				'page' => 'dates',
				'section' => 'jqueryui-custom',
			);
		}
		$sections['jqueryui-custom'] = $custom_formats;

		return $sections;
	}
}
endif;

// security
if ( defined( 'ABSPATH' ) && function_exists( 'add_action' ) )
	return QSOT_Date_Settings_Page::instance();
else
	return;
