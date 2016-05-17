<?php if ( __FILE__ == $_SERVER['SCRIPT_FILENAME'] ) die( header( 'Location: /') );

// a list of helpers for basic tasks we need throughout the plugin
class QSOT_Utils {
	/** 
	 * Recursively "extend" an array or object
	 *
	 * Accepts two params, performing a task similar to that of array_merge_recursive, only not aggregating lists of values, like shown in this example:
	 * http://php.net/manual/en/function.array-merge-recursive.php#example-5424 under the 'favorite' key.
	 *
	 * @param object|array $a an associative array or simple object
	 * @param object|array $b an associative array or simple object
	 *
	 * @return object|array returns an associative array or simplr object (determined by the type of $a) of a list of merged values, recursively
	 */
	public static function extend( $a, $b ) {
		// start $c with $a or an array if it is not a scalar
		$c = is_object( $a ) || is_array( $a ) ? $a : ( empty( $a ) ? array() : (array) $a );

		// if $b is not an object or array, then bail
		if ( ! is_object( $b ) && ! is_array( $b ) )
			return $c;

		// slightly different syntax based on $a's type
		// if $a is an object, use object syntax
		if ( is_object( $c ) ) {
			foreach ( $b as $k => $v ) {
				$c->$k = is_scalar( $v ) ? $v : self::extend( isset( $a->$k ) ? $a->$k : array(), $v );
			}

		// if $a is an array, use array syntax
		} else if ( is_array( $c ) ) {
			foreach ( $b as $k => $v ) {
				$c[ $k ] = is_scalar( $v ) ? $v : self::extend( isset( $a[ $k ] ) ? $a[ $k ] : array(), $v );
			}   

		// otherwise major fail
		} else {
			throw new Exception( __( 'Could not extend. Invalid type.', 'opentickets-community-edition' ) );
		}

		return $c; 
	}

	/**
	 * Find adjusted timestamp
	 *
	 * Accepts a raw time, in any format accepted by strtotime, and converts it into a timestamp that is adjusted, based on our WordPress settings, so
	 * that when used withe the date() function, it produces a proper GMT time. For instance, this is used when informing the i18n datepicker what the
	 * default date should be. The frontend will auto adjust for the current local timezone, so we must pass in a GMT timestamp to achieve a proper
	 * ending display time.
	 *
	 * @param string $date any string describing a time that strtotime() can understand
	 *
	 * @return int returns a valid timestamp, adjusted for our WordPress timezone setting
	 */
	public static function gmt_timestamp( $date=null, $method='to', $date_format='date' ) {
		// default to the current date
		if ( null === $date )
			$date = date( 'c' );

		// get the strtotime interpretation
		$raw = 'timestamp' == $date_format ? $date : @strtotime( $date );

		// if that failed, then bail
		if ( false === $raw )
			return false;

		// adjust the raw time we got above, to achieve the GMT time
		return $raw + ( ( 'to' == $method ? -1 : 1 ) * ( get_option( 'gmt_offset', 0 ) * HOUR_IN_SECONDS ) );
	}

	/**
	 * Convert date to 'c' format
	 *
	 * Accepts a mysql Y-m-d H:i:s format, and converts it to a system local time 'c' date format.
	 *
	 * @param string $ymd which is a mysql date stamp ('Y-m-d H:i:s')
	 *
	 * @return string new date formatted string using the 'c' date format
	 */
	public static function to_c( $ymd ) {
		static $off = false;
		// if we are already in c format, then use it
		if ( false !== strpos( $ymd, 'T' ) )
			return $ymd;

		// if we dont match the legacy format, then bail
		if ( ! preg_match( '#\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}#', $ymd ) )
			return $ymd;

		// if we never loaded offset before, do it now
		if ( false === $off )
			$off = date_i18n( 'P' );

		return str_replace( ' ', 'T', $ymd ) . $off;
	}

	/**
	 * Local Adjusted time from mysql
	 *
	 * Accepts a mysql timestamp Y-m-d H:i:s, and converts it to a timestamp that is usable in the date() php function to achieve local time translations.
	 *
	 * @param string $date a mysql timestamp in Y-m-d H:i:s format
	 *
	 * @return int a unix-timestamp, adjusted so that it produces accurrate local times for the server
	 */
	public static function local_timestamp( $date ) {
		return self::gmt_timestamp( self::to_c( $date ), 'from' );
	}
}
