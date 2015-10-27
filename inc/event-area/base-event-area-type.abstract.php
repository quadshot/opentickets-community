<?php ( __FILE__ == $_SERVER['SCRIPT_FILENAME'] ) ? die( header( 'Location: /' ) ) : null;

// the base class for all event area types. requires some basic functions and defines basic properties
abstract class QSOT_Base_Event_Area_Type {
	// an incremeneted value so that every area type can have it's own priority by default
	protected static $inc_priority = 1;

	// this specific area type's priority
	protected $priority = 0;

	// the priority to use when determining the type of an arae we dont know the type of
	protected $find_priority = PHP_INT_MAX;

	// name and slug of this area type
	protected $name = '';
	protected $slug = '';

	// basic constructor for the area type
	public function __construct() {
		$this->priority = self::$inc_priority++;
		$this->slug = sanitize_title_with_dashes( 'area-type-' . $this->priority );
		$this->name = sprintf( __( 'Area Type %d', 'opentickets-community-edition' ), $this->priority );
	}

	// get the priority of this area type
	public function get_priority() { return $this->priority; }

	// get the slug of this area type
	public function get_slug() { return $this->slug; }

	// get the name of this area type
	public function get_name() { return $this->name; }

	// get the find priority of this area type. this will determine the order in which this type is tested, to determine the type of an unknown typed event area
	public function get_find_priority() { return $find_priority; }

	// function used to determine if the supplied post matches this type's needed data. used to determine the type of the event area, when the type is not stored in meta
	abstract public function post_is_this_type( $post );
}
