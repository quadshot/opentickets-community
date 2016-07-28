<?php if ( __FILE__ == $_SERVER['SCRIPT_FILENAME'] ) die( header( 'Location: /') );

/*
QSOT API Authorization Validator

Validates that a request has supplied the proper authorization.
*/
class QSOT_API_Authorization_Validator {
	// make this a singleton
	protected static $_instance = null;
	public static function instance() { return ( self::$_instance instanceof self ) ? self::$_instance : ( self::$_instance = new self ); }
	protected function __construct() { $this->initialize(); }

	// setup the object
	protected function initialize() {
	}

	// validate that the appropriate credentials have been passed with the request
	public function validate( QSOT_API_Request $request ) {
		// if the api is disabled, account for that here
		if ( QSOT_API::is_disabled() )
			throw new QSOT_API_Exception( __( 'API is Disabled', 'opentickets-community-edition' ), 403, 'api_disabled' );

		// get the credentials from the request
		$auth_token = $request->post( 'auth_token' );
		@list( $app_id, $app_secret ) = explode( '|', $auth_token );

		// if either is missing, bail now
		if ( ! isset( $app_id ) || empty( $app_id ) || ! isset( $app_id ) || empty( $app_secret ) )
			throw new QSOT_API_Exception( __( 'You must supply your API credentials with every request.', 'openticket-community-edition' ), 401, 'missing_credentials' );

		// if the supplied credentials are not valid, then bail
		if ( ! $this->_are_valid_credentials( $app_id, $app_secret ) )
			throw new QSOT_API_Exception( __( 'Your credentials are invalid.', 'opentickets-community-edition' ), 401, 'invalid_credentials' );

		return true;
	}

	// validate whether the supplied credentials are valid or not
	protected function _are_valid_credentials( $app_id, $app_secret ) {
		// load api creds from db
		$key = get_option( 'qsot-app-api-key', '' );
		$secret = get_option( 'qsot-app-api-secret', '' );

		// if there are no creds generated, then bail
		if ( '' === $key || '' === $secret )
			return false;

		// otherwise, test credentials
		return $key == $app_id && $secret == $app_secret;
	}
}
