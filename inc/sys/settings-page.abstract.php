<?php if ( __FILE__ == $_SERVER['SCRIPT_FILENAME'] ) die( header( 'Location: /') );

if ( ! class_exists( 'QSOT_Settings_Page' ) ) :

abstract class QSOT_Settings_Page extends WC_Settings_Page {
	// override the WC sections function
	public function output_sections() {
		global $current_section;

		// get a list of page sections
		$sections = $this->get_sections();

		// if there are no sections, then bail
		if ( empty( $sections ) )
			return;

		// get the data about our setting page uri
		$page_uri = apply_filters( 'qsot-get-menu-page-uri', array(), 'settings' );

		echo '<ul class="subsubsub">';

		$array_keys = array_keys( $sections );

		foreach ( $sections as $id => $label ) {
			echo '<li><a href="' . admin_url( $page_uri[0] . '&tab=' . $this->id . '&section=' . sanitize_title( $id ) ) . '" class="' . ( $current_section == $id ? 'current' : '' ) . '">' . $label . '</a> ' . ( end( $array_keys ) == $id ? '' : '|' ) . ' </li> ';
		}

		echo '</ul><br class="clear" />';
	}

	// override for the get_settings function in the parent class
	public function get_settings() {
		$fields = $this->get_page_settings();
		return $this->_add_qtranslate( $fields );
	}

	// add the qtranslate LSB indicators to the settings pages
	protected function _add_qtranslate( $fields ) {
		$need = false;
		$would_need = array( 'textarea', 'wysiwyg', 'text' );
		$fields = (array) $fields;
		// cycle through the fields, and determine if we need a qtranslate
		foreach ( $fields as $field ) {
			if ( in_array( $field['type'], $would_need ) ) {
				$need = true;
				break;
			}
		}

		// if we need qtranslate, add one at the top and bottom
		if ( $need ) {
			array_unshift( $fields, array(
				'order' => 1,
				'type' => 'qtranslate-lsb',
				'id' => 'qsot-qtranslate-top',
			) );
			array_push( $fields, array(
				'order' => PHP_INT_MAX,
				'type' => 'qtranslate-lsb',
				'id' => 'qsot-qtranslate-bottom',
			) );
		}

		return $fields;
	}
}

endif;
