<?php if ( __FILE__ == $_SERVER['SCRIPT_FILENAME'] ) die( header( 'Location: /') );

/*
QSOT API Checkin Endpoint

Accepts and Handles the requests to checkin a ticket, via an api request.
*/
class QSOT_Core__API__Checkin implements QSOT_API_Endpoint {
	// validate the request
	public function validate( &$request ) {
		// if the code is not present in the request, throw an error
		$code = $request->post( 'code' );
		if ( empty( $code ) )
			throw new QSOT_API_Exception( __( 'No code was supplied to check-in.', 'opentickets-community-edition' ), 412, 'missing_code' );
	}

	// handle the api request
	public function handle( &$request, &$response ) {
		$response->set_data( array( 'checking in' => 'yes' ) )->send();
		exit;
	}
}
