<?php if ( __FILE__ == $_SERVER['SCRIPT_FILENAME'] ) die( header( 'Location: /') );

// class to handle interaction with the api of the qs-software-maanger server
class QSOT_Extensions_API {
	protected static $_instance = null;

	// default class args
	protected static $defs = array(
		'apiurl' => 'http://opentickets.com/',
		'endpoint_marker' => 'qssm-api',
	);

	// constianer for the class args
	protected $args = array();

	// last timer for the last request
	public $last_timer = null;

	// setup the basic actions, filters, and settings used by the class
	public static function pre_init() {
		QSOT_Extensions_API::instance( array(
			'apiurl' => QSOT_Extensions::get_server_url(),
		) );
	}

	// create the singleton of this object
	public static function instance( $args='' ) {
		// figure out the current class
		$class = __CLASS__;

		// if we already have an instance, use that
		if ( isset( self::$_instance ) && self::$_instance instanceof $class ) {
			self::$_instance->set_args( $args );
			return self::$_instance;
		}

		// otherwise create one and return it
		return self::$_instance = new QSOT_Extensions_API( $args );
	}

	// constructor for the object. setup all the basic data and do the singleton check
	public function __construct( $args='' ) {
		// figure out the current class
		$class = __CLASS__;

		// only one instance of this class can be active at a time
		if ( isset( self::$_instance ) && self::$_instance instanceof $class )
			throw new Exception( __( 'Only one instance of the OpenTickets Extensions object is allowed at a time.', 'opentickets-community-edition' ) );

		// update the instance
		self::$_instance = $this;

		// set the args for this instance
		$this->set_args( $args );
	}

	// set the args for this instance
	public function set_args( $args='' ) {
		// normalize the args
		$this->args = $this->_normalize_args( $args );
	}

	// method to run the activation sequence for a license
	public function activate( $data ) {
		// figure out the current domain, because it gets added to all requests
		$su = @parse_url( site_url() );
		if ( ! is_array( $su ) ||  empty( $su['host'] ) )
			return new WP_Error( 'invalid_site', __( 'Could not determine the site url. API request failed.', 'opentickets-community-edition' ) );

		// if the required qact key is not present, bail
		if ( ! isset( $data['activate'] ) || empty( $data['activate'] ) )
			return new WP_Error( 'missing_params', __( 'The API request is missing a required parameter.', 'opentickets-community-edition' ) );

		$final = array( 'qact' => array() );
		// verify the minimum infos are present for each activation request. if anything is missing, skip the activation request silently
		foreach ( $data['activate'] as $fkey => $item ) {
			// create a temporary entry of all the data assigned to their appropriate keys
			$tmp = array(
				'qd' => $su['host'],
				'qkey' => isset( $item['license'] ) && is_scalar( $item['license'] ) ? trim( $item['license'] ) : '',
				'qem' => isset( $item['email'] ) && is_scalar( $item['email'] ) ? trim( $item['email'] ) : '',
				'qf' => isset( $item['file'] ) && is_scalar( $item['file'] ) ? trim( $item['file'] ) : '',
				'qv' => isset( $item['version'] ) && is_scalar( $item['version'] ) ? trim( $item['version'] ) : '',
			);

			// if none of the required data is empty, then add the item to the final data list
			if ( ! empty( $tmp['qkey'] ) && ! empty( $tmp['qem'] ) && ! empty( $tmp['qf'] ) && ! empty( $tmp['qv'] ) && ! empty( $tmp['qd'] ) )
				$final['qact'][ $fkey ] = $tmp;
		}

		// if there were finally no valid items to activate, then bail with an error
		if ( empty( $final['qact'] ) )
			return new WP_Error( 'no_activation_request', __( 'You must supply at least one software to activate.', 'opentickets-community-edition' ) );

		// fetch the response
		$response = $this->_fetch( 'ACT', $final );

		// if the response hard failed, then bail with a global activation error
		if ( is_wp_error( $response ) )
			return $response;

		// record the time of the last api request
		$this->last_timer = isset( $response['t'] ) ? $response['t'] : null;

		$result = array();
		// parse the request, and construct an appropriate response
		// cycle through all the software activation requests, and get all the response data for that one item individually
		foreach ( $final['qact'] as $file => $req ) {
			$tmp = array();
			// if the request was successful, there will be data for it in the 'activated' key
			if ( isset( $response['activated'][ $file ] ) ) {
				$tmp['verification_code'] = isset( $response['activated'][ $file ]['verification_code'] ) ? $response['activated'][ $file ]['verification_code'] : '';
				$tmp['expires'] = isset( $response['activated'][ $file ]['expires'] ) ? $response['activated'][ $file ]['expires'] : '';
			}

			// if there are errors for this item, they will be in the 'es' key
			if ( isset( $response['es'][ $file ] ) && ! empty( $response['es'][ $file ] ) ) {
				$tmp['errors'] = $response['es'][ $file ];
				$tmp['verification_hash'] = $tmp['expires'] = '';
			}

			// add the item to the response
			$result[ $file ] = $tmp;
		}

		return $result;
	}

	// method to deactivate a given license
	public function deactivate( $data ) {
		// figure out the current site url
		$su = @parse_url( site_url() );
		if ( ! is_array( $su ) ||  empty( $su['host'] ) )
			return new WP_Error( 'invalid_site', __( 'Could not determine the site url. API request failed.', 'opentickets-community-edition' ) );

		// structure the request data
		$req = array(
			'qd' => $su['host'],
			'qem' => trim( isset( $data['email'] ) ? $data['email'] : get_option( 'admin_email' ) ),
			'qkey' => trim( isset( $data['license'] ) ? $data['license'] : '' ),
			'qh' => trim( isset( $data['verification_code'] ) ? $data['verification_code'] : '' ),
			'qv' => trim( isset( $data['version'] ) ? $data['version'] : '' ),
			'qf' => trim( isset( $data['file'] ) ? $data['file'] : '' ),
		);

		// if we are missing the domain or email, then bail now and do nothing
		if ( empty( $req['qem'] ) || ! is_email( $req['qem'] ) )
			return new WP_Error( 'missing_email', __( 'You must supply a valid email address.', 'opentickets-community-edition' ) );
		if ( empty( $req['qkey'] ) )
			return new WP_Error( 'missing_license', __( 'You must supply the license key for this software.', 'opentickets-community-edition' ) );
		if ( empty( $req['qh'] ) )
			return new WP_Error( 'missing_verification_code', __( 'The previous activation verification code is not present.', 'opentickets-community-edition' ) );
		if ( empty( $req['qf'] ) )
			return new WP_Error( 'missing_file', __( 'The main software file is missing.', 'opentickets-community-edition' ) );
		if ( empty( $req['qv'] ) )
			return new WP_Error( 'missing_version', __( 'The version of the software is missing.', 'opentickets-community-edition' ) );

		// otherwise, fetch the response
		$response = $this->_fetch( 'DCT', $req );

		// if the response hard failed, then pass through
		if ( is_wp_error( $response ) )
			return $response;

		// record the time of the last api request
		$this->last_timer = isset( $response['t'] ) ? $response['t'] : null;

		// if the response was NOT successful, then bail
		if ( ! $response['success'] )
			return $this->_failed_response( $response );

		unset( $response['success'], $response['t'] );

		// if no specific type is requested, then return all results
		if ( empty( $type ) )
			return $response;

		// otherwise only return the type of response we were asked for
		return isset( $response[ $type ] ) ? $response[ $type ] : array();
	}

	// method to grab the available extensions
	public function get_available( $data, $type='plugins' ) {
		// figure out the current site url
		$su = @parse_url( site_url() );
		if ( ! is_array( $su ) ||  empty( $su['host'] ) )
			return new WP_Error( 'invalid_site', __( 'Could not determine the site url. API request failed.', 'opentickets-community-edition' ) );

		// structure the request data
		$req = array(
			'qd' => $su['host'],
			'qem' => isset( $data['email'] ) ? $data['email'] : get_option( 'admin_email' ),
			'qc' => isset( $data['categories'] ) ? implode( ',', array_filter( array_map( 'trim', is_array( $data['categories'] ) ? $data['categories'] : explode( ',', $data['categories'] ) ) ) ) : array(),
			'qi' => array(), //isset( $data['image_hashes'] ) ? $data['image_hashes'] : array(),
			'qi_urls' => true,
		);

		// if we are missing the domain or email, then bail now and do nothing
		if ( empty( $req['qd'] ) )
			return new WP_Error( 'invalid_site', __( 'Could not determine the site domain.', 'opentickets-community-edition' ) );
		if ( empty( $req['qem'] ) )
			return new WP_Error( 'missing_email', __( 'An email address is required, to fetch a list of available extensions.', 'opentickets-community-edition' ) );

		// otherwise, fetch the response
		$response = $this->_fetch( 'ASC', $req );

		// if the response hard failed, then pass through
		if ( is_wp_error( $response ) )
			return $response;

		// record the time of the last api request
		$this->last_timer = isset( $response['t'] ) ? $response['t'] : null;

		// if the response was NOT successful, then bail
		if ( ! $response['success'] )
			return $this->_failed_response( $response );

		unset( $response['success'], $response['t'] );

		// if no specific type is requested, then return all results
		if ( empty( $type ) )
			return $response;

		// otherwise only return the type of response we were asked for
		return isset( $response[ $type ] ) ? $response[ $type ] : array();
	}

	// method to grab the available extensions
	public function get_updates( $data ) {
		$result = array( 'plugins' => array(), 'themes' => array(), 'translations' => array(), 'no_update' => array() );
		// figure out the current site url
		$su = @parse_url( site_url() );

		// structure the request data
		$domain = isset( $su['host'] ) ? $su['host'] : $su['path'];
		$admin_email = get_option( 'admin_email' );

		// if we are missing the domain or email, then bail now and do nothing
		if ( empty( $domain ) )
			return new WP_Error( 'invalid_site', __( 'Could not determine the site domain.', 'opentickets-community-edition' ) );

		$check = array();
		// construct the check list
		foreach ( $data['plugins'] as $file => $plugin ) {
			$check[ $file ] = array(
				'qf' => $file,
				'qd' => $domain,
				'qem' => isset( $plugin['email'] ) ? $plugin['email'] : $admin_email,
				'qv' => $plugin['version'],
			);

			// if we have the hash and license info, then add that to the item's request
			if ( isset( $plugin['verification_code'] ) )
				$check[ $file ]['qh'] = $plugin['verification_code'];
			if ( isset( $plugin['license'] ) )
				$check[ $file ]['qkey'] = $plugin['license'];
		}

		// if there is nothing to check, then bail
		if ( empty( $check ) )
			return $result;

		// otherwise, fetch the response
		$response = $this->_fetch( 'CHK', array( 'qcheck' => $check ) );

		// if the response hard failed, then pass through
		if ( is_wp_error( $response ) )
			return $response;

		// record the time of the last api request
		$this->last_timer = isset( $response['t'] ) ? $response['t'] : null;

		// if the response was NOT successful, then bail
		if ( ! $response['success'] )
			return $this->_failed_response( $response );

		unset( $response['success'], $response['t'] );

		// set out results
		foreach ( $result as $key => $list )
			if ( isset( $response[ $key ] ) )
				$result[ $key ] = $response[ $key ];

		// otherwise only return the type of response we were asked for
		return $result;
	}

	// actually perform the request to fetch the response from the server
	protected function _fetch( $endpoint, $data ) {
		// figure out the appropriate url to send the request to
		$url = add_query_arg( array( $this->args['endpoint_marker'] => $endpoint ), $this->args['apiurl'] );

		// send the request and fetch the response
		$response = wp_remote_post( $url, array(
			'timeout' => 28,
			'redirection' => 3,
			'user-agent' => $this->_user_agent(),
			'body' => $data,
		) );

		// if the response failed, then error out
		if ( ! is_array( $response ) || ! isset( $response['body'] ) )
			return new WP_Error( 'no_response', __( 'No response from the server.', 'opentickets-community-edition' ), $response );

		// if the response code is not set, or it is not 200, then bail 
		if ( ! isset( $response['response'], $response['response']['code'] ) || 200 != $response['response']['code'] )
			return new WP_Error( 'invalid_response', __( 'The response we received from the server was invalid.', 'opentickets-community-edition' ), $response );

		// get the response body, and parse it, which should be json
		$parsed = @json_decode( wp_remote_retrieve_body( $response ), true );

		// if the response is not an array, then bail
		if ( ! is_array( $parsed ) )
			return new WP_Error( 'invalid_response', __( 'The response we received from the server was invalid.', 'opentickets-community-edition' ), $response );

		return $parsed;
	}

	// get a remote image and return the image content we fetched
	public function get_remote_image( $img_url ) {
		$img_url = trim( $img_url );
		// if the url is empty, bail
		if ( empty( $img_url ) )
			return new WP_Error( 'no_url', __( 'No url was supplied.', 'opentickets-community-edition' ) );;

		// get the remote image data
		$response = wp_remote_get( $img_url, array(
			'timeout' => 10,
			'redirection' => 3,
			'user-agent' => $this->_user_agent(),
		) );

		// if the response is not in the format we expect, the bail
		if ( ! is_array( $response ) || ! isset( $response['body'] ) )
			return new WP_Error( 'no_response', __( 'No response from the server.', 'opentickets-community-edition' ), $response );

		// if the response code is not set, or it is not 200, then bail 
		if ( ! isset( $response['response'], $response['response']['code'] ) || 200 != $response['response']['code'] )
			return new WP_Error( 'invalid_response', __( 'The response we received from the server was invalid.', 'opentickets-community-edition' ), $response );

		return wp_remote_retrieve_body( $response );
	}

	// figure out the user-agent to send in the api
	protected function _user_agent() {
		static $agent = false;
		// if the agent was already calculated, don't do it again
		if ( false !== $agent )
			return $agent;

		// base user agent should be the api identifier and the site url
		$agent = sprintf( 'QSSM API (%s)', site_url() );

		// if this is the cli, mark it as such
		if ( defined( 'PHP_SAPI' ) && 'cli' == PHP_SAPI ) {
			if ( function_exists( 'gethostname' ) )
				$agent .= sprintf( ' / CLI (%s[%s])', gethostname(), gethostbyname( gethostname() ) );
			else
				$agent .= sprintf( ' / CLI (%s[%s])', php_uname( 'n' ), gethostbyname( php_uname( 'n' ) ) );
		} else if ( isset( $_SERVER['SERVER_ADDR'] ) ) {
			$agent .= sprintf( ' / WEB (%s)', $_SERVER['SERVER_ADDR'] );
		}

		// add the system information
		$system = function_exists( 'posix_uname' ) ? posix_uname() : array();
		$agent .= ' / ' . implode( ', ', array_values( $system ) );

		return $agent;
	}

	// failed response. input the failed response, and create a wp_error to describe it
	protected function _failed_response( $response ) {
		// create the base error
		$error = new WP_Error( 'unsuccessful', __( 'The response we received from the server was unsuccessful.', 'opentickets-community-edition' ) );

		// if there are any errors attached, then add them to the error list
		if ( isset( $response['e'] ) && is_array( $response['e'] ) && ! empty( $response['e'] ) )
			foreach ( $response['e'] as $emsg )
				foreach ( (array)$emsg['msgs'] as $msg )
					$error->add( $emsg['code'], $msg );

		return $error;
	}

	// normalize the args, on top of the existing args and defaults
	protected function _normalize_args( $args ) {
		// overlay the new args on top of the old args first
		$new_args = wp_parse_args( $args, $this->args );

		// then put that result on top of the defaults for the final result
		$new_args = wp_parse_args( $new_args, self::$defs );

		return $new_args;
	}
}

// security
if ( defined( 'ABSPATH' ) && function_exists( 'add_action' ) )
	QSOT_Extensions_API::pre_init();
