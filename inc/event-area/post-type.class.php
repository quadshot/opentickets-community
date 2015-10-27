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


	// container for all the registered event area types, ordered by priority
	protected $area_types = array();
	protected $find_order = array();

	// initialize the object. maybe add actions and filters
	public function initialize() {
		// action to register the post type
		add_action( 'init', array( &$this, 'register_post_type' ), 10000 );

		// area type registration and deregistration
		add_action( 'qsot-register-event-area-type', array( &$this, 'register_event_area_type' ), 1000, 1 );
		add_action( 'qsot-deregister-event-area-type', array( &$this, 'deregister_event_area_type' ), 1000, 1 );

		// add the generic event area type metabox
		add_action( 'add_meta_box_qsot-event-area', array( &$this, 'add_meta_boxes' ), 1000 );
	}

	// deinitialize the object. remove actions and filter
	public function deinitialize() {
		remove_action( 'init', array( &$this, 'register_post_type' ), 10000 );
		remove_action( 'qsot-register-event-area-type', array( &$this, 'register_event_area_type' ), 1000 );
		remove_action( 'qsot-deregister-event-area-type', array( &$this, 'deregister_event_area_type' ), 1000 );
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

		register_post_type( 'qsot-event-area', $args );
	}

	// function to obtain a list of all the registered event area types
	public function get_event_area_types( $desc_order=false ) {
		// return a list of event_types ordered by priority, either asc (default) or desc (param)
		return ! $desc ? $this->area_types : array_reverse( $this->area_types );
	}

	// allow registration of an event area type
	public function register_event_area_type( &$type_object ) {
		// make sure that the submitted type uses the base class
		if ( ! ( $type_object instanceof QSOT_Base_Event_Area_Type ) )
			throw new Exception( __( 'The supplied event type does not use the QSOT_Base_Event_Type parent class.', 'opentickets-community-edition' ), 12100 );

		// figure out the slug and display name of the submitted event type
		$slug = $type_object->get_slug();

		// add the event area type to the list
		$this->area_types[ $slug ] = $type_object;

		// determine the 'fidn order' for use when searching for the appropriate type
		uasort( $this->area_types, array( &$this, 'uasort_find_priority' ) );
		$this->find_order = array_keys( $this->area_types );

		// sort the list by priority
		uasort( $this->area_types, array( &$this, 'uasort_priority' ) );
	}

	// allow an event area type to be unregistered
	public function deregister_event_area_type( $type ) {
		$slug = '';
		// figure out the slug
		if ( is_string( $type ) )
			$slug = $type;
		elseif ( is_object( $type ) && $type instanceof QSOT_Base_Event_Area_type )
			$slug = $type->get_slug();

		// if there was no slug found, bail
		if ( empty( $slug ) )
			return;

		// if the slug does not coorespond with a registered area type, bail
		if ( ! isset( $this->area_types[ $slug ] ) )
			return;

		unset( $this->area_types[ $slug ] );
	}

	// sort items by $obj->priority()
	public function uasort_priority( $a, $b ) { return $a->get_priority() - $b->get_priority(); }

	// sort items by $obj->find_priority()
	public function uasort_find_priority( $a, $b ) {
		$A = $a->get_find_priority();
		$B = $b->get_find_priority();
		return ( $A !== $B ) ? $A - $B : $a->get_priority() - $b->get_priority();
	}

	// add the event area type metaboxes
	public function add_meta_boxes() {
		add_meta_box(
			'qsot-event-area-type',
			__( 'Event Area Type', 'opentickets-community-edition' ),
			array( &$this, 'mb_render_event_area_type' ),
			'qsot-event-area',
			'side',
			'high'
		);
	}

	// figure out the event area type, based on the post
	public function event_area_type_from_post( $post ) {
		// if there are no event area types registered, then bail
		if ( empty( $this->area_types ) )
			return new WP_Error( 'no_types', __( 'There are no registered event area types.', 'opentickets-community-edition' ) );

		// see if the meta value is set, and valid
		$current = get_post_meta( $post->ID, '_qsot-event-area-type', true );

		// if it is set and valid, then use that
		if ( isset( $current ) && is_string( $current ) && ! empty( $current ) && isset( $this->area_types[ $current ] ) )
			return $this->area_types[ $current ];

		// otherwise, cycle through the find type list, and find the first matching type
		foreach ( $this->find_order as $slug )
			if ( $this->area_types[ $slug ]->post_is_this_type( $post ) )
				return $this->area_types[ $slug ];

		// if no match was found, then just use the type with the highest priority (least specific)
		$current = end( $this->find_order );
		return $this->area_types[ $current ];
	}

	// draw the metabox that shows the current value for the event area type, and allows that value to be changed
	public function mb_render_event_area_type( $post ) {
		// get the current value
		$current = $this->event_area_type_from_post( $post );

		// if there was a problem finding the current type, then display the error
		if ( is_wp_error( $current ) ) {
			foreach ( $current->get_error_codes() as $code )
				foreach ( $current->get_error_messages( $code ) as $msg )
					echo sprintf( '<p>%s</p>', force_balance_tags( $msg ) );
			return;
		}

		// if there is no current type, bail because something is wrong
		if ( empty( $current ) ) {
			echo '<p>' . __( 'There are no registered event area types.', 'opentickets-community-edition' ) . '</p>';
			return;
		}

		$current_slug = $current->get_slug();

		?>
			<ul class="area-types-list">
				<?php foreach ( $this->area_types as $slug => $type ): ?>
					<li class="area-type-<?php echo esc_attr( $slug ) ?>">
						<input type="radio" name="qsot-event-area-type" value="<?php echo esc_attr( $slug ) ?>" id="area-type-<?php echo esc_attr( $slug ) ?>" <?php checked( $current_slug, $slug ) ?> />
						<label for="area-type-<?php echo esc_attr( $slug ) ?>"><?php echo force_balance_tags( $type->get_name() ) ?></label>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php
	}
}

if ( defined( 'ABSPATH' ) && function_exists( 'add_action' ) )
	QSOT_Post_Type_Event_Area::instance();
