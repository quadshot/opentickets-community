<?php ( __FILE__ == $_SERVER['SCRIPT_FILENAME'] ) ? die( header( 'Location: /' ) ) : null;

// class to handle the basic general admission event area type
class QSOT_General_Admission_Area_Type extends QSOT_Base_Event_Area_Type {
	// container for the singleton instance
	protected static $instance = null;

	// get the singleton instance
	public static function instance() {
		// if the instance already exists, use it
		if ( isset( self::$instance ) && self::$instance instanceof QSOT_General_Admission_Area_Type )
			return self::$instance;

		// otherwise, start a new instance
		return self::$instance = new QSOT_General_Admission_Area_Type();
	}

	// constructor. handles instance setup, and multi instance prevention
	public function __construct() {
		// if there is already an instance of this object, then bail now
		if ( isset( self::$instance ) && self::$instance instanceof QSOT_General_Admission_Area_Type )
			throw new Exception( sprintf( __( 'There can only be one instance of the %s object at a time.', 'opentickets-community-edition' ), __CLASS__ ), 12000 );

		// otherwise, set this as the known instance
		self::$instance = $this;

		// defaults from parent
		parent::__construct();

		// and call the intialization function
		$this->initialize();
	}

	// destructor. handles instance destruction
	public function __destruct() {
		$this->deinitialize();
	}


	// setup the object
	public function initialize() {
		// setup the object description
		$this->priority = 1;
		$this->find_priority = PHP_INT_MAX;
		$this->slug = 'general-admission';
		$this->name = __( 'General Admission', 'opentickets-community-edition' );

		// after all the plugins have loaded, register this type
		add_action( 'plugins_loaded', array( &$this, 'plugins_loaded' ), 10 );
	}

	// destroy the object
	public function deinitialize() {
		remove_action( 'plugins_loaded', array( &$this, 'plugins_loaded' ), 10 );
	}

	// register this area type after all plugins have loaded
	public function plugins_loaded() {
		do_action_ref_array( 'qsot-register-event-area-type', array( &$this ) );
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

if ( defined( 'ABSPATH' ) && function_exists( 'add_action' ) )
	QSOT_General_Admission_Area_Type::instance();
