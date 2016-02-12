<?php if ( __FILE__ == $_SERVER['SCRIPT_FILENAME'] ) die( header( 'Location: /') );

// handle remote file downloads, using curl or fopen if either is available
class qsot_remote_file {
	public static $contents;
	public static $headers;

	protected static $context = null;
	protected static $ch = null;

	// setup the class
	public static function pre_init() {
		register_shutdown_function( array( __CLASS__, 'on_shutdown' ) );
	}

	// wrapper function to generically get a remote url's content
	public static function get_contents( $url ) {
		$is_local_file = self::_smells_local( $url );

		// prefer cURL, since it is pretty common, and is more likely to be enabled since it is not linked to many security problems
		if ( ! $is_local_file && function_exists( 'curl_init' ) ) {
			self::curl( $url );
		// fallback on fopen (file_get_contents) just in case 'allow_url_fopen' is actually enabled on a server
		} else if ( $is_local_file || ini_get( 'allow_url_fopen' ) ) {
			self::file_get_contents( $url );
		// fail, because we don't have anothe method to use
		} else {
			throw new Exception( 'Either "allow_url_fopen" must be enabled in php.ini, or you must install the cURL library, in order for DOMPDF to work (and create pdf files).' );
		}

		return self::$contents;
	}

	/*
	// test if the supplied url is actually a local file
	protected static function _smells_local( $url ) {
		$purl = @parse_url( $url );
		if ( ( ! isset( $purl['scheme'] ) || 'file' == $purl['scheme'] ) && file_exists( $url ) )
			return true;
		return false;
	}
	*/

	// determine if a url is of a local resource
	protected static function _smells_local( $url ) {
		// patch to translate new style file paths to old style file paths, for older versions of php compatibility
		$url = preg_replace( '#^file://([^\/])#', 'file:///$1', $url );

		// hide any failure messages that may pop
		$parsed_url = @parse_url( $url );

		// if there is no url host, then it is assumed that the host is the local host, meaning it is a local file
		if ( ! isset( $parsed_url['host'] ) )
			return true;

		// if the scheme is present, and set to 'file' then it is definitely supposed to be a local asset
		if ( isset( $parsed_url['scheme'] ) && 'file' === strtolower( $parsed_url['scheme'] ) )
			return true;

		// on windows servers d:/path/to/file gets registerd as a url with scheme d and path /path/to/file. we need to compensate for this
		// do this by a regex test to see if the path starts with the path to the installation
		$test_path = preg_replace( '#^' . preg_quote( ABSPATH, '#' ) . '#', '', $url );
		if ( $test_path != $url )
			return true;

		// figure out the host and path of both urls. this will help determine if this asset lives at a local path. the site_url() could be a host with a path, if the installation is in a subdir
		$local_url = function_exists( 'site_url' ) ? site_url() : '';
		$url = explode( '/', $url, 3 );
		$local_url = explode( '/', $local_url, 3 );
		$remote_path = strtolower( end( $url ) );
		$local_path = strtolower( end( $local_url ) );

		// if the local path is present at the beginning of the remote path string, then it is a local path
		if ( 0 === strpos( $remote_path, $local_path ) )
			return true;

		// otherwise it is most logically a remote file
		return false;
	}

	// grab the list of headers returned
	public static function get_headers() {
		return self::$headers;
	}

	// setup a reuseable context for fopen method
	protected static function _get_context() {
		if ( is_null( self::$context ) ) {
			self::$context = stream_context_create( array(
				'http' => array(
					'method' => 'GET',
					'timeout' => 3,
				),
			) );
		}

		return self::$context;
	}

	// fopen method. grab response and headers
	public static function file_get_contents( $url ) {
		self::$contents = file_get_contents( $url, false, self::_get_context() );
		self::$headers = $http_response_header;
	}

	// setup a reuseable curl handler
	protected static function _ch() {
		if ( is_null( self::$ch ) ) {
			self::$ch = curl_init();
		}

		return self::$ch;
	}

	// send the curl request, grab the response, and separate the headers, in case we need them
	public static function curl( $url ) {
		self::_ch();

		curl_setopt( self::$ch, CURLOPT_URL, $url );
		curl_setopt( self::$ch, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt( self::$ch, CURLOPT_FOLLOWLOCATION, 1 );
		curl_setopt( self::$ch, CURLOPT_MAXREDIRS, 8 );
		curl_setopt( self::$ch, CURLINFO_HEADER, 1 );
		curl_setopt( self::$ch, CURLOPT_TIMEOUT, 3 );

		// get response
		$contents = curl_exec( self::$ch );
		// measure the header size, using a method that is most universal
		$header_size = strlen( $contents ) - curl_getinfo( self::$ch, CURLINFO_SIZE_DOWNLOAD );
		// store headers and content separately
		self::$headers = explode( "\r\n", trim( substr( $contents, 0, $header_size ) ) );
		self::$contents = substr( $contents, $header_size );
	}

	// during the shutdown process, kill the curl connection
	public static function on_shutdown() {
		if ( ! is_null( self::$ch ) ) curl_close( self::$ch );
	}
}

// setup the class when this file loads
qsot_remote_file::pre_init();
