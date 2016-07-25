<?php if ( __FILE__ == $_SERVER['SCRIPT_FILENAME'] ) die( header( 'Location: /') );

/*
QSOT API Response

Accepts data and returns a response that is in the format that the api should be able to understand.
*/
class QSOT_API_Response {
	protected $_error = null;
	protected $_error_code = null;
	protected $_status_code = null;
	protected $_data = null;

	// setup the object
	public function __construct() {
	}

	// set the error message for the response
	public function set_error( $message, $code=null, $status=403 ) {
		$this->_error = $message;
		$this->_error_code = $code;
		$this->_status_code = $status;
		return $this;
	}

	// set the response data
	public function set_data( Array $data ) {
		$this->_data = $data;
		return $this;
	}

	// send the response to the client
	public function send() {
		// if the status is set, send that to the end user now
		if ( $this->_status_code )
			status_header( $this->_status_code );

		// send the header indicating the data type returned
		header( 'Content-Type: application/json' );

		$package = array();
		// add any error message to the response
		if ( isset( $this->_error ) ) {
			$error = array( 'message' => $this->_error );
			if ( isset( $this->_error_code ) )
				$error['code'] = $this->_error_code;
			$package['error'] = $error;
		}

		// add the data to the response
		if ( isset( $this->_data ) )
			$package['data'] = $this->_data;

		echo @json_encode( $package );
		exit;
	}
}
