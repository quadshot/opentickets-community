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
		$code = $request->post( 'code' );
		// first, if the code is the full checkin url, adjust the code so that it is just the code
		if ( false !== strpos( $code, 'http:' ) || false !== strpos( $code, 'https:' ) ) {
			$parsed = @parse_url( $code );
			$query = array();
			if ( isset( $parsed['query'] ) )
				parse_str( $parsed['query'], $query );

			// if the url path contains the code, use that
			if ( isset( $parsed['path'] ) && strpos( $parsed['path'], 'event-checkin/' ) )
				$code = preg_replace( '#.*event-checkin\/([^\/]+)/?#', '$1', $parsed['path'] );
			// if there was a url param with the code, use that
			else if ( isset( $query['qsot-checkin-packet'] ) )
				$code = $query['qsot-checkin-packet'];
		}
		$code = urldecode( $code );

		// next, parse the packet, if we can, and verify it
		$packet = QSOT_checkin::parse_checkin_packet( $code );

		// process the checkin request
		$results = QSOT_checkin::process_checkin( $packet, $code );

		// if the response was an error, respond in kind
		if ( is_wp_error( $results ) ) {
			$response->set_error( implode( ' ', $results->get_error_messages() ), $results->get_error_code() )->set_data( array( 'success' => false ) )->send();
		// otherwise it was a success, so report that too
		} else {
			$response->set_data( array( 'success' => true ) )->send();
		}
		exit;
	}
}
