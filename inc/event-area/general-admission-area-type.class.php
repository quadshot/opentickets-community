<?php ( __FILE__ == $_SERVER['SCRIPT_FILENAME'] ) ? die( header( 'Location: /' ) ) : null;

// class to handle the basic general admission event area type
class QSOT_General_Admission_Area_Type extends QSOT_Base_Event_Area_Type {
	// setup the class
	public function __construct() {
		$this->priority = 1;
		$this->find_priority = PHP_INT_MAX;
		$this->slug = 'general-admission';
		$this->name = __( 'General Admission', 'opentickets-community-edition' );
	}

	// determine if the supplied post could be of this area type
	public function post_is_this_type( $post ) {
		// if this is not an event area, then it cannot be
		if ( 'qsot-event-area' != $post->post_type )
			return false;

		// otherwise, it is
		return true;
	}
}
