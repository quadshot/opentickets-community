<?php if ( __FILE__ == ['SCRIPT_FILENAME'] ) die( header( 'Location: /') );

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
			echo '<li><a href="' . admin_url( $page_uri[0] . '&tab=' . $this->id . '&section=' . sanitize_title( $id ) ) . '" class="' . ( $current_section == $id ? 'current' : '' ) . '">' . $label . '</a> ' . ( end( $array_keys ) == $id ? '' : '|' ) . ' </li>';
		}

		echo '</ul><br class="clear" />';
	}
}

endif;
