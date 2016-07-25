<?php if ( __FILE__ == $_SERVER['SCRIPT_FILENAME'] ) die( header( 'Location: /') );

/*
QSOT API

Setup and Handle the requests sent to the api. Provides an interface for API components to register themselves, and for WordPress to dispatch requests to those components.
*/
class QSOT_API {
	// make this a singleton
	protected static $_instance = null;
	public static function instance() { return ( self::$_instance instanceof self ) ? self::$_instance : ( self::$_instance = new self ); }
	protected __construct() { $this->initialize(); }

	// setup the object
	protected function initialize() {
		// load all the required api core code
		do_action( 'qsot-load-includes', '', '#^.+\.api-core\.php$#i' );

		// add the rewrite hook that intercepts api requests
		do_action( 'qsot-rewriter-add', 'qsot-api', array( 'func' => array( &$this, 'handle_requests' ) ) );
	}

	// handle all the api requests
	public function handle_requests( $value, $qvar, $all_data, $query_vars ) {
		try {
			// create a request object, and validate that the request is valid
			$request = new QSOT_API_Request();
			QSOT_API_Request_Validator::instance()->validate( $request );

			// figure out the classname of the request that needs to be handled
			$classname = $this->_request_class_name( $value );

			// if the classname was not found, try to find a matching action
			if ( is_wp_error( $classname ) ) {
				$action_name = preg_replace( '#^qsot_api_#', '', strtolower( $classname ) );

				// first give the opportunity to validate the request. this should throw an exception on failure
				if ( has_action( 'qsot-api-validate-' . $action_name ) )
					do_action( 'qsot-api-validate-' . $action_name );

				// perform the action request now
				if ( has_action( $action_name ) )
					do_action( 'qsot-api-' . $action_name );
			// otherwise, handle the request
			} else {
				$object = new $classname();

				// first validate the request. throws an exception on failure
				$object->validate();

				// then handle the request
				$object->handle();
			}
		} catch ( Exception $e ) {
			status_header( $e->getCode() );
			echo @json_encode( array( 'error' => $e->getMessage() ) );
			exit;
		}

		exit;
	}

	// calculate the classname from the request endpoint
	protected function _request_class_name( $value ) {
		// if the value is non-scalar, bail
		if ( ! is_scalar( $value ) || is_bool( $value ) )
			throw new Exception( __( 'Invalid request type', 'opentickets-community-edition' ), 403 );

		// try to construct a class name
		$classname = 'QSOT_API_' . preg_replace( '#[^\w\d]#', '_', $value );
		if ( ! class_exists( $classname ) )
			return new WP_Error( 'not_found', __( 'API endpoint not found', 'opentickets-community-edition' ) );

		return $classname;
	}
}

// security
if ( defined( 'ABSPATH' ) && function_exists( 'add_action' ) )
	QSOT_API::instance();
