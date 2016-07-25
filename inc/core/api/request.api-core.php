<?php if ( __FILE__ == $_SERVER['SCRIPT_FILENAME'] ) die( header( 'Location: /') );

/*
QSOT API Request Object

Aggregates a request based on the global vars.
*/
class QSOT_API_Request {
	protected $_base = '/';
	protected $_query = array();
	protected $_post = array();
	protected $_cookies = array();
	protected $_files = array();
	protected $_server = array();

	protected $_endpoint = null;
	protected $endpoint_read = false;

	protected $_headers = array();
	protected $headers_read = false;

	protected $_content = null;
	protected $content_read = false;

	// create the object
	public function __construct( $base='/qsot-api/' ) {
		$this->_base = $base;
		$this->_init_request_from_globals( $base );
	}

	// use the global vars to determine the entire request
	protected function _init_request_from_globals() {
		$this->_query = $_GET;
		$this->_post = $_POST;
		$this->_cookies = $_COOKIE;
		$this->_files = $_FILES;
		$this->_get_server();
		$this->_get_content();
		$this->_get_headers();
		$this->_get_endpoint();
	}

	// getters of the various parts of the request
	public function endpoint() { return $this->_get_endpoint(); }
	public function content() { return $this->_get_content(); }
	public function header( $name, $default=null ) { $this->_get_headers(); return is_scalar( $name ) && isset( $this->_headers[ $name ] ) ? $this->_headers[ $name ] : $default; }
	public function server( $name, $default=null ) { $this->_get_server(); return is_scalar( $name ) && isset( $this->_server[ $name ] ) ? $this->_server[ $name ] : $default; }
	public function query( $name, $default=null ) { return is_scalar( $name ) && isset( $this->_query[ $name ] ) ? $this->_query[ $name ] : $default; }
	public function post( $name, $default=null ) { return is_scalar( $name ) && isset( $this->_post[ $name ] ) ? $this->_post[ $name ] : $default; }
	public function files( $name, $default=null ) { return is_scalar( $name ) && isset( $this->_files[ $name ] ) ? $this->_files[ $name ] : $default; }
	public function cookies( $name, $default=null ) { return is_scalar( $name ) && isset( $this->_cookies[ $name ] ) ? $this->_cookies[ $name ] : $default; }

	// get the api endpoint, based on the base 
	protected function _get_endpoint() {
		// if the endpoint has already been calculated, dont do it again
		if ( $this->endpoint_read )
			return $this->_endpoint;

		$this->_get_server();
		// otherwise, calculate it now
		$this->endpoint_read = true;

		$parsed = @parse_url( $this->_server['REQUEST_URI'] );
		// figure out the endpoint from the path
		if ( isset( $parsed['path'] ) && false !== ( $pos = strpos( $parsed['path'], $this->_base ) ) )
			return $this->_endpoint = substr( $parsed['path'], $pos + strlen( $this->_base ) );

		throw new QSOT_API_Exception( __( 'Could not determine the endpoint of the api request.', 'opentickets-community-edition' ), 403, 'invalid_endpoint' );
	}

	// get the content from the request
	protected function _get_content() {
		// if the content has already been read, use it now
		if ( $this->content_read )
			return $this->_content;

		// otherwise read it now
		$this->content_read = true;
		return $this->_content = file_get_contents( 'php://input' );
	}

	// get the headers from the request
	protected function _get_headers() {
		// if the headers have already been read, use them now
		if ( $this->headers_read )
			return $this->_headers;

		// otherwise read them now
		$this->headers_read = true;
		$this->_headers = $headers = array();
		$this->_get_server();
		// mostly copied from https://github.com/bshaffer/oauth2-server-php/blob/v0.9/src/OAuth2/Request.php
		foreach ( $this->_server as $key => $value ) {
			if ( 0 === strpos( $key, 'HTTP_' ) ) {
				$headers[ substr( $key, 5 ) ] = $value;
			}
			// CONTENT_* are not prefixed with HTTP_
			elseif ( in_array( $key, array( 'CONTENT_LENGTH', 'CONTENT_MD5', 'CONTENT_TYPE' ) ) ) {
				$headers[ $key ] = $value;
			}
		}
		if ( isset( $this->_server['PHP_AUTH_USER'] ) ) {
			$headers['PHP_AUTH_USER'] = $server['PHP_AUTH_USER'];
			$headers['PHP_AUTH_PW'] = isset( $server['PHP_AUTH_PW'] ) ? $server['PHP_AUTH_PW'] : '';
		} else {
			/*
			 * php-cgi under Apache does not pass HTTP Basic user/pass to PHP by default
			 * For this workaround to work, add this line to your .htaccess file:
			 * RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
			 *
			 * A sample .htaccess file:
			 * RewriteEngine On
			 * RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
			 * RewriteCond %{REQUEST_FILENAME} !-f
			 * RewriteRule ^(.*)$ app.php [QSA,L]
			 */
			$authorizationHeader = null;
			if ( isset( $server['HTTP_AUTHORIZATION'] ) ) {
				$authorizationHeader = $server['HTTP_AUTHORIZATION'];
			} elseif ( isset( $server['REDIRECT_HTTP_AUTHORIZATION'] ) ) {
				$authorizationHeader = $server['REDIRECT_HTTP_AUTHORIZATION'];
			} elseif ( function_exists( 'apache_request_headers' ) ) {
				$requestHeaders = apache_request_headers();
				// Server-side fix for bug in old Android versions (a nice side-effect of this fix means we don't care about capitalization for Authorization)
				$requestHeaders = array_combine( array_map( 'ucwords', array_keys( $requestHeaders ) ), array_values( $requestHeaders ) );
				if ( isset( $requestHeaders['Authorization'] ) ) {
					$authorizationHeader = trim( $requestHeaders['Authorization'] );
				}
			}
			if ( null !== $authorizationHeader ) {
				$headers['AUTHORIZATION'] = $authorizationHeader;
				// Decode AUTHORIZATION header into PHP_AUTH_USER and PHP_AUTH_PW when authorization header is basic
				if ( 0 === stripos( $authorizationHeader, 'basic' ) ) {
					$exploded = explode( ':', base64_decode( substr( $authorizationHeader, 6 ) ) );
					if ( count( $exploded ) == 2 ) {
						list( $headers['PHP_AUTH_USER'], $headers['PHP_AUTH_PW'] ) = $exploded;
					}
				}
			}
		}

		// PHP_AUTH_USER/PHP_AUTH_PW
		if ( isset( $headers['PHP_AUTH_USER'] ) ) {
			$headers['AUTHORIZATION'] = 'Basic ' . base64_encode( $headers['PHP_AUTH_USER'] . ':' . $headers['PHP_AUTH_PW'] );
		}

		return $this->_headers = $headers;
	}

	// get the server variables
	protected function _get_server() {
		return $this->_server = $_SERVER;
	}
}
