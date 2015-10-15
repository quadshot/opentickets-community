<?php ( __FILE__ == $_SERVER['SCRIPT_FILENAME'] ) ? die( header( 'Location: /' ) ) : null;

// controls the core functionality of the evet area post type
class QSOT_Post_Type_Event_Area {
	// container for the singleton instance
	protected static $instance = null;

	// get the singleton instance
	public static function instance() {
		// if the instance already exists, use it
		if ( isset( self::$instance ) && self::$instance instanceof QSOT_Post_Type_Event_Area )
			return self::$instance;

		// otherwise, start a new instance
		return self::$instance = new QSOT_Post_Type_Event_Area();
	}

	// constructor. handles instance setup, and multi instance prevention
	public function __construct() {
		// if there is already an instance of this object, then bail now
		if ( isset( self::$instance ) && self::$instance instanceof QSOT_Post_Type_Event_Area )
			throw new Exception( sprintf( __( 'There can only be one instance of the %s object at a time.', 'opentickets-community-edition' ), __CLASS__ ), 12000 );

		// otherwise, set this as the known instance
		self::$instance = $this;

		// and call the intialization function
		$this->initialize();
	}

	// destructor. handles instance destruction
	public function __destruct() {
		$this->deinitialize();
	}


	// container for all the registered event types, ordered by priority
	protected $event_types = array();

	// initialize the object. maybe add actions and filters
	public function initialize() {
		// action to register the post type
		add_action( 'init', array( &$this, 'register_post_type' ), 10000 );
	}

	// deinitialize the object. remove actions and filter
	public function deinitialize() {
		remove_action( 'init', array( &$this, 'register_post_type' ), 10000 );
	}

	// register the post type with wordpress
	public function register_post_type() {
		// singular and plural forms of the name of this post type
		$single = __( 'Event Area', 'opentickets-community-edition' );
		$plural = __( 'Event Areas', 'opentickets-community-edition' );

		// create a list of labels to use for this post type
		$labels = array(
			'name' => $plural,
			'singular_name' => $single,
			'menu_name' => $plural,
			'name_admin_bar' => $single,
			'add_new' => sprintf( __( 'Add New %s', 'qs-software-manager' ), '' ),
			'add_new_item' => sprintf( __( 'Add New %s', 'qs-software-manager' ), $single),
			'new_item' => sprintf( __( 'New %s', 'qs-software-manager' ), $single ),
			'edit_item' => sprintf( __( 'Edit %s', 'qs-software-manager' ), $single ),
			'view_item' => sprintf( __( 'View %s', 'qs-software-manager' ), $single ),
			'all_items' => sprintf( __( 'All %s', 'qs-software-manager' ), $plural ),
			'search_items' => sprintf( __( 'Search %s', 'qs-software-manager' ), $plural ),
			'parent_item_colon' => sprintf( __( 'Parent %s:', 'qs-software-manager' ), $plural ),
			'not_found' => sprintf( __( 'No %s found.', 'qs-software-manager' ), strtolower( $plural ) ),
			'not_found_in_trash' => sprintf( __( 'No %s found in Trash.', 'qs-software-manager' ), strtolower( $plural ) ),
		);

		// list of args that define the post typ
		$args = apply_filters( 'qsot-event-area-post-type-args', array(
			'label' => $plural,
			'labels' => $labels,
			'description' => __( 'Represents a specific physical location that an event can take place. For instance, a specific conference room at a hotel.', 'opentickets-community-edition' ),
			'public' => false,
			'publicly_queryable' => false,
			'show_ui' => false,
			'show_in_menu' => false,
			'query_var' => false,
			'rewrite' => array( 'slug' => 'event-area' ),
			'capability_type' => 'post',
			'has_archive' => true,
			'hierarchical' => false,
			'menu_position' => null,
			'supports' => array( 'title' )
		) );

	}

	// function to obtain a list of all the registered event area types
	public function get_event_area_types( $desc_order=false ) {
		// return a list of event_types ordered by priority, either asc (default) or desc (param)
		return ! $desc ? $this->event_types : array_reverse( $this->event_types );
	}

	// determine if any of the registered event_area_types want the post type to have it's

	// sort items by $obj->priority
	public function usort_priority( $a, $b ) { return ( isset( $a->priority ) ? $a->priority : 0 ) - ( isset( $b->priority ) ? $b->priority : 0 ); }
}
