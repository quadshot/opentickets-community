<?php if ( __FILE__ == $_SERVER['SCRIPT_FILENAME'] ) die( header( 'Location: /') );

/*
QSOT API

Setup and Handle the requests sent to the api. Provides an interface for API components to register themselves, and for WordPress to dispatch requests to those components.
*/
class QSOT_API {
	// make this a singleton
	protected static $_instance = null;
	public static function instance() { return ( self::$_instance instanceof self ) ? self::$_instance : ( self::$_instance = new self ); }
	protected function __construct() { $this->initialize(); }

	const ALPHANUM = 'alphanum';

	// setup the object
	protected function initialize() {
		// test method
		//add_action( 'init', array( &$this, 'TESTING' ), 1 );

		// if we have not yet generated an api key and secret, then do so now, if we are in the admin
		add_action( 'admin_init', array( &$this, 'maybe_generate_api_credentials' ) );

		// load all the required api core code
		do_action( 'qsot-load-includes', '', '#^.+\.api-core\.php$#i' );

		// add the rewrite hook that intercepts api requests
		do_action( 'qsot-rewriter-add', 'qsot-api', array( 'func' => array( &$this, 'handle_requests' ) ) );
	}

	// determine if the api is diabled
	public static function is_disabled() { return ( defined( 'QSOT_DISABLE_API' ) && QSOT_DISABLE_API ); }

	// ****** REMOVE
	public function TESTING() {
		$token = get_option( 'qsot-app-api-key', '' ) . '|' . get_option( 'qsot-app-api-secret', '' );
		if ( isset( $_GET['test-api'] ) && 'opentickets' == $_GET['test-api'] ) {
			$response = wp_remote_post( home_url( '/qsot-api/checkin/' ), array( 'body' => array( 'auth_token' => $token, 'code' => 'http://ot.dev.dev?qsot-event-checkin=1&qsot-checkin-packet=MDk1ZmRiODE3YzQ4MWNmOTVkNGZlMjdiNGQ5Mjc5Mzk1NWFmZjEzZHwxOjBjODcwNDljNjBjZDlmZDZiY2U1MDZkMjZhMWE2OTllOik-bmFwcy88MDAuMDM-bmFwcy88OzYzIyY-ImxvYm15U3ljbmVycnVjLWVjaXJQLWVjcmVtbW9jb293Ij1zc2FsYyBuYXBzPD4idG51b21hIHRudW9tYS1lY2lyUC1lY3JlbW1vY29vdyI9c3NhbGMgbmFwczwoIHRla2NpVCB5Y2lyUDswMy45MTAxOzM1MTswMjAx' ) ) );
			echo wp_remote_retrieve_body( $response );
			exit;
		} else if ( isset( $_GET['test-api'] ) && 'failure' == $_GET['test-api'] ) {
			$response = wp_remote_post( home_url( '/qsot-api/checkin/' ), array( 'body' => array( 'auth_token' => $token, 'code' => 'http://ot.dev.dev?qsot-event-checkin=1&qsot-checkin-packet=AAAAMDk1ZmRiODE3YzQ4MWNmOTVkNGZlMjdiNGQ5Mjc5Mzk1NWFmZjEzZHwxOjBjODcwNDljNjBjZDlmZDZiY2U1MDZkMjZhMWE2OTllOik-bmFwcy88MDAuMDM-bmFwcy88OzYzIyY-ImxvYm15U3ljbmVycnVjLWVjaXJQLWVjcmVtbW9jb293Ij1zc2FsYyBuYXBzPD4idG51b21hIHRudW9tYS1lY2lyUC1lY3JlbW1vY29vdyI9c3NhbGMgbmFwczwoIHRla2NpVCB5Y2lyUDswMy45MTAxOzM1MTswMjAx' ) ) );
			echo wp_remote_retrieve_body( $response );
			exit;
		}
	}

	// handle all the api requests
	public function handle_requests( $value, $qvar, $all_data, $query_vars ) {
		try {
			$response = new QSOT_API_Response();
			// create a request object, and validate that the request is valid
			$request = new QSOT_API_Request();
			QSOT_API_Authorization_Validator::instance()->validate( $request );

			// figure out the classname of the request that needs to be handled
			$classname = $this->_request_class_name( $value );

			// if the classname was not found, try to find a matching action
			if ( is_wp_error( $classname ) ) {
				$action_name = preg_replace( '#^qsot_core__api__#', '', strtolower( $classname->get_error_data() ) );

				// first give the opportunity to validate the request. this should throw an exception on failure
				if ( has_action( 'qsot-api-validate-' . $action_name ) )
					do_action( 'qsot-api-validate-' . $action_name, $request );

				// perform the action request now
				if ( has_action( $action_name ) )
					do_action( 'qsot-api-' . $action_name, $request, $response );
			// otherwise, handle the request
			} else {
				$object = new $classname();

				// only process the request if the object is an api endpoint
				if ( $object instanceof QSOT_API_Endpoint ) {
					// first validate the request. throws an exception on failure
					$object->validate( $request );

					// then handle the request
					$object->handle( $request, $response );
				}
			}
		} catch ( Exception $e ) {
			$response->set_error( $e->getMessage(), null, $e->getCode() )->send();
			exit;
		}

		$response->set_error( __( 'API request invalid', 'opentickets-community-edition' ), null, 400 )->send();
		exit;
	}

	// calculate the classname from the request endpoint
	protected function _request_class_name( $value ) {
		// if the value is non-scalar, bail
		if ( ! is_scalar( $value ) || is_bool( $value ) )
			throw new QSOT_API_Exception( __( 'Invalid request type', 'opentickets-community-edition' ), 403, 'invalid_request' );

		// try to construct a class name
		$classname = 'QSOT_Core__API__' . preg_replace( '#[^\w\d]#', '_', $value );
		if ( ! class_exists( $classname ) )
			return new WP_Error( 'not_found', __( 'API endpoint not found', 'opentickets-community-edition' ), $classname );

		return $classname;
	}

	// possibly generate api credentials, if we have not done so already, and if the api is not disabled
	public function maybe_generate_api_credentials() {
		// only do this in the admin
		if ( ! is_admin() || defined( 'DOING_AJAX' ) )
			return;

		// if the api is disabled, do nothing
		if ( QSOT_API::is_disabled() )
			return;

		$key = get_option( 'qsot-app-api-key', '' );
		$secret = get_option( 'qsot-app-api-secret', '' );
		// check if the credentials have already been generated 
		if ( '' !== $key && '' !== $secret )
			return;

		// otherwise, generate them now
		update_option( 'qsot-app-api-key', $this->_gen_key( 32, self::ALPHANUM ) );
		update_option( 'qsot-app-api-secret', $this->_gen_key( 64, self::ALPHANUM ) );
	}

	// generate a random sequence of characters, based on the given character set
	protected function _gen_key( $length, $set='alphanum' ) {
		$use_set = '';
		$set_len = 0;
		// determine the proper character set to use
		switch ( $set ) {
			default:
			case 'alphanum':
				$use_set = str_shuffle( 'abcdefghijklmnopqrstuvwxyz0123456789' );
				$set_len = strlen( $use_set );
			break;
		}

		$final = '';
		// cycle through the number of requeted chars
		for ( $i = 0; $i < $length; $i++ )
			$final .= $use_set{ rand( 0, $set_len-1 ) };

		return $final;
	}
}

// api endpoint interface
interface QSOT_API_Endpoint {
	// handle the request from the user
	public function handle( &$request, &$response );

	// validate the request from the user
	public function validate( &$request );
}

// special exception type that includes extra data
class QSOT_API_Exception extends Exception {
	protected $extra_data = null;

	// override the constuctor to include data
	public function __construct( $message = null, $code = 0, $data = null, Exception $previous = null ) {
		parent::__construct( $message, $code, $previous );
		$this->extra_data = $data;
	}

	// get the data from the exception
	public function getData() {
		return $this->extra_data;
	}
}

// security
if ( defined( 'ABSPATH' ) && function_exists( 'add_action' ) )
	QSOT_API::instance();
