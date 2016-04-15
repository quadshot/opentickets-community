<?php if ( __FILE__ == $_SERVER['SCRIPT_FILENAME'] ) die( header( 'Location: /') );

// load the ajax handler class. performs most authentication and request validation for registered ajax requests
class QSOT_Ajax {
	// container for the singleton instance
	protected static $instance = null;

	// list of actions by Sub-Action
	protected $by_sa = array();

	// get the singleton instance
	public static function instance() {
		// if the instance already exists, use it
		if ( isset( self::$instance ) && self::$instance instanceof QSOT_Ajax )
			return self::$instance;

		// otherwise, start a new instance
		return self::$instance = new QSOT_Ajax();
	}

	// constructor. handles instance setup, and multi instance prevention
	public function __construct() {
		// if there is already an instance of this object, then bail now
		if ( isset( self::$instance ) && self::$instance instanceof QSOT_General_Admission_Area_Type )
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


	// setup the object
	public function initialize() {
		// hook used to intercept most ajax
		add_action( 'wp_ajax_qsot-ajax', array( &$this, 'handle_request' ) );
		add_action( 'wp_ajax_nopriv_qsot-ajax', array( &$this, 'handle_request' ) );
		add_action( 'wp_ajax_qsot-admin-ajax', array( &$this, 'handle_request' ) );
		add_action( 'wp_ajax_nopriv_qsot-admin-ajax', array( &$this, 'handle_request' ) );
		add_action( 'wp_ajax_qsot-frontend-ajax', array( &$this, 'handle_request' ) );
		add_action( 'wp_ajax_nopriv_qsot-frontend-ajax', array( &$this, 'handle_request' ) );

		// register some generic requests
		$this->register( 'find-product', array( &$this, 'aj_find_product' ), array( array( 'edit_products' ) ) );
	}

	// destroy the object
	public function deinitialize() {
		remove_action( 'wp_ajax_qsot-ajax', array( &$this, 'handle_request' ) );
		remove_action( 'wp_ajax_nopriv_qsot-ajax', array( &$this, 'handle_request' ) );
	}

	// handle the basic validation of the ajax requests and basic security
	public function handle_request() {
		// figure out if there is an sa in the request. if not, bail
		if ( ! ( $sa = $_REQUEST['sa'] ) || ! isset( $this->by_sa[ $sa ] ) )
			//die(var_dump(1, $sa, $this->by_sa));
			return;

		$action = str_replace( 'wp_ajax_', '', str_replace( 'wp_ajax_nopriv_', '', current_action() ) );
		// make sure there is an nonce present that matches. the frist level basic security
		if ( ! isset( $_REQUEST['_n'] ) || ! wp_verify_nonce( $_REQUEST['_n'], 'do-' . $action ) )
			//die(var_dump(2, $action, wp_create_nonce( 'do-' . $action ), $_REQUEST));
			return;

		$event = false;
		// if there was an event_id supplied, then load the event now, and it's event area
		if ( isset( $_REQUEST['event_id'] ) && intval( $_REQUEST['event_id'] ) > 0 ) {
			$event = apply_filters( 'qsot-get-event', $event, $_REQUEST['event_id'] );
			if ( is_object( $event ) ) {
				$ea_id = intval( get_post_meta( $event->ID, '_event_area_id', true ) );
				if ( $ea_id > 0 ) {
					$event->event_area = get_post( $ea_id );
					$event->event_area->area_type = QSOT_Post_Type_Event_Area::instance()->event_area_type_from_event_area( $event->event_area );
					$event->event_area->zoner = $event->event_area->area_type->get_zoner();
				}
			}
		}

		// sort the handlers for the sa, by their specified ordering
		ksort( $this->by_sa[ $sa ], SORT_NUMERIC );

		$ran_one = false;
		$out = array( 's' => false, 'e' => array() );
		// cycle through the sorted list. for each item, validate the security for calling it. if security passes, then call it, otherwise continue
		foreach ( $this->by_sa[ $sa ] as $handlers ) {
			// for each handler that we have in the list, run the handler if applicable
			foreach ( $handlers as $handler ) {
				// if this handler is only good for certain actions, then make sure that we are on one of those actions
				if ( is_array( $handler['only_for'] ) && count( $handler['only_for'] ) && ! in_array( $action, $handler['only_for'] ) )
					continue;

				// if the current user has access to this handler, then call it
				if ( self::_passes_security( $handler['req'] ) ) {
					$out = call_user_func( $handler['func'], $out, $event );
					$ran_one = true;
				}
			}
		}

		// if we did not run any handlers, then fail no matter what
		if ( ! $ran_one )
			$out['s'] = false;
		// otherwise, if we ran at least one function, check and see if we need to update the NONCE for the next request.
		// this is needed because WooCommerce messes with the NONCE value when a user changes state from anonymous guest, to anonymous customer
		else if ( ( $new_nonce = wp_create_nonce( 'do-' . $action ) ) && $new_nonce !== $_REQUEST['_n'] )
			$out['_nn'] = $new_nonce;

		// if there are no error messages, just remove the key from the response
		if ( empty( $out['e'] ) )
			unset( $out['e'] );

		wp_send_json( $out );
	}
	
	// determine if the current user passes our defined security checks
	protected function _passes_security( $security ) {
		// if there is no security requirement, then they pass automatically
		if ( empty( $security ) )
			return true;

		$pass = true;
		// otherwise, test every requirement
		foreach ( $security as $can_user ) {
			// if this security requirement is not in the right format, then skip it
			if ( ! is_array( $can_user ) || ! count( $can_user ) )
				continue;
			$can_user = array_values( $can_user );

			// get the capability we are supposed to check for on this pass
			$capability = array_shift( $can_user );

			// figure out the various params that could be associated with the security check, like an ID for edit_post, etc...
			$params = array();
			foreach ( $can_user as $post_key )
				$params[] = isset( $_POST[ $post_key ] ) ? $_POST[ $post_key ] : null;

			// construct the final argument list to send to our permission check funciton
			$args = array_merge( array( $capability ), $params );

			// do the check. if it fails, then halt other checks and hard fail
			if ( ! call_user_func_array( 'current_user_can', $args ) ) {
				$pass = false;
				break;
			}
		}

		return $pass;
	}

	// allow registration of sub action handlers
	public function register( $sa, $func, $requirements=array(), $order=null, $only_for=null ) {
		// sanitize input
		$sa = trim( $sa );
		$requirements = (array)$requirements;
		
		// validate input
		if ( empty( $sa ) )
			return new WP_Error( __( 'You must provide the sub-action name.', 'qs-software-manager' ) );
		if ( ! is_callable( $func ) )
			return new WP_Error( __( 'You must supply a valid handler callback.', 'qs-software-manager' ) );;
		
		// if the sa handler list is not present, create it
		if ( ! isset( $this->by_sa ) || ! is_array( $this->by_sa ) )
			$this->by_sa = array();

		// create an entry for the handler
		$order = ( null == $order ) ? 10 : $order;
		$this->by_sa[ $sa ][ $order ] = isset( $this->by_sa[ $sa ][ $order ] ) ? $this->by_sa[ $sa ][ $order ] : array();
		$this->by_sa[ $sa ][ $order ][] = array(
			// should be an array of capabilities to test in order to be able to user this handler. Example:
			// array(
			//   array( 'edit_posts' ), // user has the 'edit_posts' capability
			//   array( 'edit_post', 'post_id' ), // user can edit the post that has id $_POST['post_id']
			// );
			'req' => $requirements,
			// a callback that will be run if the user has access to this handler. Examples:
			// 'my_awesome_handler'
			// array( 'my_awesome_class', 'my_awesome_handler' )
			// array( &$this, 'my_awesome_handler' )
			'func' => $func,
			// you can also define that this ajax sub-action is only good for some specific actions
			// 'my-action'
			// array( 'my-action', 'your-action' )
			'only_for' => $only_for ? (array)$only_for : null,
		);

		return true;
	}

	//allow deregistration of a sub action handler
	public function unregister( $sa, $func, $order=null ) {
		// sanitize input
		$sa = trim( $sa );

		// if there is no key for this sa, then bail
		if ( ! is_scalar( $sa ) || ! isset( $this->by_sa[ $sa ] ) )
			return;

		// if the order was supplied, check only the handlers for that order number
		if ( null !== $order ) {
			// if that order does not exist, bail
			if ( ! isset( $this->by_sa[ $sa ][ $order ] ) )
				return;

			$new_list = array();
			// check each entry, and remove the matching one
			foreach ( $this->by_sa[ $sa ][ $order ] as $handler )
				if ( $handler['func'] != $func )
					$new_list[] = $handler;

			$this->by_sa[ $sa ][ $order ] = $new_list;
		// otherwise, check all lists
		} else {
			foreach ( $this->by_sa[ $sa ] as $order => $handler ) {
				$found = false;
				$new_list = array();

				// cycle through all handlers in this list. save non-matching ones, and remove matching ones
				foreach ( $this->by_sa[ $sa ][ $order ] as $handler )
					if ( ! $handler['func'] == $func )
						$found = true;
					else
						$new_list[] = $handler;

				// if the handler was found in this round, then update the handler list and end our search
				if ( $found ) {
					$this->by_sa[ $sa ][ $order ] = $new_list;
					break;
				}
			}
		}
	}

	// generic ajax method to find products based on a search string
	public function aj_find_product( $resp ) {
		// get the search string
		$search = isset( $_REQUEST['q'] ) ? $_REQUEST['q'] : '';

		// setup serch args
		$args1 = array(
			'post_type' => 'product',
 			'post_status' => array( 'publish' ),
			'perm' => 'readable',
			'posts_per_page' -1,
			'meta_query' => array(
				array( 'key' => '_sku', 'value' => $search, 'compare' => 'LIKE' ),
			),
			'fields' => 'ids',
		);

		// if the current user can read private posts, add private as a post status
		if ( current_user_can( 'read_private_posts' ) )
			$args1['post_status'][] = 'private';

		// construct the first query
		$ids1 = get_posts( $args1 );


		// setup the second group of search args
		$args2 = array(
			'post_type' => 'product',
			'post_status' => array( 'publish' ),
			'perm' => 'readable',
			'posts_per_page' -1,
			's' => $search,
			'fields' => 'ids',
		);

		// if the current user can read private posts, add private as a post status
		if ( current_user_can( 'read_private_posts' ) )
			$args2['post_status'][] = 'private';

		// second search
		$ids2 = get_posts( $args2 );

		// combine results
		$ids = array_filter( array_unique( array_merge( $ids1, $ids2 ) ) );

		// if there are no results, then bail
		if ( empty( $ids ) ) {
			$resp['e'][] = __( 'No Results found', 'opentickets-community-edition' );
			return $resp;
		}
		
		// get all results
		$posts = get_posts( array( 'post__in' => $ids, 'post_type' => 'any', 'post_status' => 'any', 'posts_per_page' => -1 ) );

		// if there are no results, then bail
		if ( empty( $posts ) ) {
			$resp['e'][] = __( 'No Results found', 'opentickets-community-edition' );
			return $resp;
		}

		// construct the results
		$resp['r'] = array();
		foreach ( $posts as $post )
			$resp['r'][] = array( 'id' => $post->ID, 'text' => apply_filters( 'the_title', $post->post_title, $post->ID ) );
		$resp['s'] = true;

		return $resp;
	}
}

// security
if ( defined( 'ABSPATH' ) && function_exists( 'add_action' ) )
	QSOT_Ajax::instance();
