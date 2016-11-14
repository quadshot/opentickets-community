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
	public static function to_c( $ymd, $dst_adjust=true ) {
		static $off = false;
		// if we are already in c format, then use it
		if ( false !== strpos( $ymd, 'T' ) )
			return $dst_adjust ? self::dst_adjust( $ymd ) : $ymd;

		// if we dont match the legacy format, then bail
		if ( ! preg_match( '#\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}#', $ymd ) )
			return $dst_adjust ? self::dst_adjust( $ymd ) : $ymd;

		// if we never loaded offset before, do it now
		if ( false === $off )
			$off = date_i18n( 'P' );

		$out = str_replace( ' ', 'T', $ymd ) . $off;
		return $dst_adjust ? self::dst_adjust( $out ) : $out;
	}

	/**
	 * Hande daylight savings time
	 * 
	 * Takes a timestamp that has a timezone, and adjusts the timezone for dailylight savings time, if needed.
	 *
	 * @param string $date a timestamp string with a timezone
	 *
	 * @return string a modified timestamp that has an adjusted timezone portion
	 */
	public static function dst_adjust( $string ) {
		// first... assume ALL incoming timestamps are from the NON-DST timezone.
		// then... adjust it if we are currently DST

		// get the parts of the supplied time string
		preg_match( '#^(?P<date>\d{4}-\d{2}-\d{2})(?:T| )(?P<time>\d{2}:\d{2}:\d{2})(?P<tz>.*)?$#', $string, $match );

		// if we dont have a date or time, bail now
		if ( ! isset( $match['date'], $match['time'] ) )
			return $string;
		
		// if the tz is not set, then default to current SITE non-dst offset
		if ( ! isset( $match['tz'] ) )
			$match['tz'] = self::non_dst_tz_offset();

		// adjust the offset based on whether this is dst or not
		$match['tz'] = self::_dst_adjust( $match['tz'] );

		// reconstitute the string and return
		return $match['date'] . 'T' . $match['time'] . $match['tz'];
	}

	// determine if currently in dst time
	public static function in_dst( $time=null ) {
		// update to the site timezone
		$tz_string = get_option( 'timezone_string', 'UTC' );
		$orig_tz_string = date_default_timezone_get();
		if ( $tz_string )
			date_default_timezone_set( $tz_string );

		$time = null === $time ? time() : $time;
		// get the current dst status
		$dst_status = date( 'I', $time );

		// restore the timezone before this calc
		date_default_timezone_set( $orig_tz_string );

		return apply_filters( 'qsot-is-dst', !! $dst_status, $time );
	}

	// get the non-DST timezone
	public static function non_dst_tz_offset() {
		static $offset = null;
		// do this once per page load
		if ( null !== $offset )
			return $offset;

		// update to the site timezone
		$tz_string = get_option( 'timezone_string', 'UTC' );
		$orig_tz_string = date_default_timezone_get();
		if ( $tz_string )
			date_default_timezone_set( $tz_string );

		// get the current offset and dst status
		$current_offset = date( 'P' );
		$dst_status = date( 'I' );

		// calculate the appropriate offset based on the current one and the dst flag
		$offset = self::_dst_adjust( $current_offset, $dst_status );

		// restore the timezone before this calc
		date_default_timezone_set( $orig_tz_string );

		return $offset;
	}

	// accept a mysql or 'c' formatted timestamp, and make it use the current non-dst SITE timezone
	public static function make_non_dst( $string ) {
		static $offset = null;
		if ( null === $offset )
			$offset = self::non_dst_tz_offset();

		// check if the timestamp is valid
		if ( ! preg_match( '#^(\d{4}-\d{2}-\d{2})(?:T| )(\d{2}:\d{2}:\d{2}).*$#', $string ) )
			return $string;

		// first remove an existing timezone
		$string = preg_replace( '#^(\d{4}-\d{2}-\d{2})(?:T| )(\d{2}:\d{2}:\d{2}).*$#', '\1T\2', $string );

		// add the SITE offset to the base string, and return
		return $string . $offset;
	}

	// adjust a timezone offset to account for DST, to get the non-DST timezone
	protected static function _dst_adjust( $offset, $dst=null ) {
		$dst = null === $dst ? self::in_dst() : !!$dst;
		// if it is a dst time offset
		if ( $dst ) {
			preg_match( '#^(?P<hour>[-+]\d{2}):(?P<minute>\d{2})$#', $offset, $match );
			if ( isset( $match['hour'], $match['minute'] ) ) {
				$new_hour = intval( $match['hour'] ) + 1;
				// "spring forward" means the offset is increased by one hour
				$offset = sprintf(
					'%s%02s%02s',
					$new_hour < 0 ? '-' : '+',
					abs( $new_hour ),
					$match['minute']
				);
			}
		}

		return $offset;
	}

	// test dst calcs
	protected static function test_dst_calc() {
		function yes_dst() { return true; }
		function no_dst() { return false; }

		$ts1 = '2016-09-12T14:30:00-08:00';
		$ts2 = '2016-11-12T14:30:00-08:00';

		add_filter( 'qsot-is-dst', 'yes_dst' );
		var_dump( QSOT_Utils::to_c( $ts1, 1 ), QSOT_Utils::to_c( $ts2, 1 ) );
		$time = QSOT_Utils::gmt_timestamp( QSOT_Utils::to_c( $ts1, 1 ), 'from' );
		$time2 = QSOT_Utils::gmt_timestamp( QSOT_Utils::to_c( $ts2, 1 ), 'from' );
		date_default_timezone_set( 'Etc/GMT-1' );
		var_dump( '09-12-dst', date( 'D, F jS, Y g:ia', $time ) );
		var_dump( '11-12-dst', date( 'D, F jS, Y g:ia', $time ) );

		remove_filter( 'qsot-is-dst', 'yes_dst' );
		add_filter( 'qsot-is-dst', 'no_dst' );
		var_dump( QSOT_Utils::to_c( $ts1, 1 ), QSOT_Utils::to_c( $ts2, 1 ) );
		$time = QSOT_Utils::gmt_timestamp( QSOT_Utils::to_c( $ts1, 1 ), 'from' );
		$time2 = QSOT_Utils::gmt_timestamp( QSOT_Utils::to_c( $ts2, 1 ), 'from' );
		date_default_timezone_set( 'UTC' );
		var_dump( '09-12-nodst', date( 'D, F jS, Y g:ia', $time ) );
		var_dump( '11-12-nodst', date( 'D, F jS, Y g:ia', $time ) );
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
	public static function local_timestamp( $date, $dst_adjust=true ) {
		return self::gmt_timestamp( self::to_c( $date, $dst_adjust ), 'from' );
	}

	// code to update all the site event start and end times to the same timezone as the SITE, in non-dst, is currently set to
	public static function normalize_event_times() {
		// get current site timeoffset
		$offset = self::non_dst_tz_offset();

		$perpage = 1000;
		$pg_offset = 1;
		// get a list of all the event ids to update. throttle at 1000 per cycle
		$args = array(
			'post_type' => 'qsot-event',
			'post_status' => array( 'any', 'trash' ),
			'post_parent__not_in' => array( 0 ),
			'fields' => 'ids',
			'posts_per_page' => $perpage,
			'paged' => $pg_offset,
		);

		// grab the next 1000
		while ( $event_ids = get_posts( $args ) ) {
			// inc page for next iteration
			$pg_offset++;
			$args['paged'] = $pg_offset;

			// cycle through all results
			while ( is_array( $event_ids ) && count( $event_ids ) ) {
				// get the next event_id to update
				$event_id = array_shift( $event_ids );

				// get the start and end time of this event from db
				$start = get_post_meta( $event_id, '_start', true );
				$end = get_post_meta( $event_id, '_end', true );
				$orig_values = array( 'start' => $start, 'end' => $end );

				// normalize each time so that it does not include an offset
				$start = preg_replace( '#^(\d{4}-\d{2}-\d{2})(?:T| )(\d{2}:\d{2}:\d{2}).*$#', '\1T\2', $start );
				$end = preg_replace( '#^(\d{4}-\d{2}-\d{2})(?:T| )(\d{2}:\d{2}:\d{2}).*$#', '\1T\2', $end );

				// update each time to include the correct offset
				$start .= $offset;
				$end .= $offset;

				// save both times in the new format
				update_post_meta( $event_id, '_start', $start );
				update_post_meta( $event_id, '_end', $end );
				// save original bad values for posterity
				add_post_meta( $event_id, '_tsFix_update', $orig_values );
			}
		}

		// add a record of the last time this ran
		update_option( '_last_run_otce_normalize_event_times', time() );
	}
}

// date formatter utils
class QSOT_Date_Formats {
	// map of php-date format letters to base date-time-segment-type
	protected static $php_format_map = array(
		// days
		'd' => 'd',
		'D' => 'd',
		'j' => 'd',
		'l' => 'd',
		'N' => 'd',
		'S' => 'd',
		'w' => 'd',
		'z' => 'd',

		// month
		'F' => 'm',
		'm' => 'm',
		'M' => 'm',
		'n' => 'm',
		't' => 'm',

		// Year
		'y' => 'Y',
		'Y' => 'Y',
		'o' => 'Y',

		// hour
		'g' => 'h',
		'G' => 'h',
		'h' => 'h',
		'H' => 'h',

		// minute
		'i' => 'i',

		// second
		's' => 's',

		// meridiem
		'a' => 'a',
		'A' => 'a',

		// timezone
		'O' => 'z',
		'P' => 'z',
		'T' => 'z',
		'Z' => 'z',

		// nothing important
		'W' => false,
		'B' => false,
		'u' => false,
		'e' => false,
		'I' => false,
		'c' => false,
		'r' => false,
		'U' => false,
	);

	// list of php-formats used within our plugin that might be customized
	public static $php_custom_date_formats = array(
		'D, F jS, Y',
	);
	public static $php_custom_time_formats = array(
		'g:ia',
	);
	public static $moment_custom_date_formats = array(
		'mm-dd-yy',
	);
	public static $moment_custom_time_formats = array(
	);
	public static $jqueryui_custom_date_formats = array(
	);
	public static $jqueryui_custom_time_formats = array(
	);

	// report whether this site uses DST or not, according to settings
	public static function use_dst() { return get_option( 'qsot-use-dst', 'yes' ) == 'yes'; }

	// report date and time format settings
	public static function date_format() { return get_option( 'qsot-date-format', 'm-d-Y' ); }
	public static function hour_format() { return get_option( 'qsot-hour-format', '12-hour' ); }
	public static function is_12_hour() { return get_option( 'qsot-hour-format', '12-hour' ) == '12-hour'; }
	public static function is_24_hour() { return get_option( 'qsot-hour-format', '12-hour' ) == '24-hour'; }

	// load all custom formats from the db
	protected static function _load_custom_formats() {
		$formats = array();
		foreach ( self::$php_custom_date_formats as $format )
			if ( $value = get_option( 'qsot-custom-php-date-format-' . sanitize_title_with_dashes( $format ), '' ) )
				$formats[ $format ] = $value;
		foreach ( self::$php_custom_time_formats as $format )
			if ( $value = get_option( 'qsot-custom-php-date-format-' . sanitize_title_with_dashes( $format ), '' ) )
				$formats[ $format ] = $value;
		return $formats;
	}

	// reorder a time format, based on the settings
	public static function php_date_format( $format='m-d-Y' ) {
		static $conversions = false;
		// load all custom formats from db, the first time this function is called
		if ( false === $conversions )
			$conversions = self::_load_custom_formats();
		// only do this conversion once per input format
		if ( isset( $conversions[ $format ] ) )
			return $conversions[ $format ];

		$segment = array();
		$last_segment = false;
		$i = 0;
		$ii = strlen( $format );
		// break up the requested format into parts
		for ( $i = 0; $i < $ii; $i++ ) {
			// if the next char is a back slash, skip it and the next letter
			if ( '\\' == $format[ $i ] ) {
				if ( $last_segment )
					$segment[ $last_segment ] .= substr( $format, $i, 2 );
				$i++;
				continue;
			}

			// if the next letter is not in the php format map, or is irrelevant, then skip it
			if ( ! isset( self::$php_format_map[ $format[ $i ] ] ) ) {
				if ( $last_segment )
					$segment[ $last_segment ] .= substr( $format, $i, 1 );
				continue;
			}

			// otherwise, add this letter to the relevant segment
			$last_segment = self::$php_format_map[ $format[ $i ] ];
			if ( ! isset( $segment[ self::$php_format_map[ $format[ $i ] ] ] ) )
				$segment[ self::$php_format_map[ $format[ $i ] ] ] = '';
			$segment[ self::$php_format_map[ $format[ $i ] ] ] .= $format[ $i ];
		}

		$date_format_array = explode( '-', self::date_format() );
		$date_format = '';
		// reorder the date portion of the format, based on the settings
		foreach ( $date_format_array as $segment_key )
			$date_format .= isset( $segment[ $segment_key ] ) ? $segment[ $segment_key ] : '';

		$time_format = '';
		$is_24_hour = self::is_24_hour();
		// construct the time format
		if ( $is_24_hour && isset( $segment['h'] ) )
			$time_format .= strtoupper( $segment['h'] );
		elseif ( ! $is_24_hour && isset( $segment['h'] ) )
			$time_format .= strtolower( $segment['h'] );
		if ( isset( $segment['i'] ) )
			$time_format .= $segment['i'];
		if ( isset( $segment['s'] ) )
			$time_format .= $segment['s'];
		if ( ! $is_24_hour && isset( $segment['a'] ) )
			$time_format .= $segment['a'];
		if ( isset( $segment['z'] ) )
			$time_format .= $segment['z'];

		// glue that shit together, and return
		$conversion[ $format ] = trim( $date_format . ' ' . $time_format );
		return $conversion[ $format ];
	}
}
