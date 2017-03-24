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
		return $raw + ( ( 'to' == $method ? -1 : 1 ) * ( ( get_option( 'gmt_offset', 0 ) + ( self::in_dst( $date ) ? '1' : 0 ) ) * HOUR_IN_SECONDS ) );
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
		$time = ! is_numeric( $time ) ? strtotime( $time ) : $time;
		// get the current dst status
		$dst_status = date( 'I', $time );

		// restore the timezone before this calc
		if ( $tz_string )
			date_default_timezone_set( $orig_tz_string );

		return apply_filters( 'qsot-is-dst', !! $dst_status, $time );
	}

	// make a timestamp UTC
	public static function make_utc( $timestamp ) {
		static $site = false;
		// site settings
		if ( false === $site )
			$site = self::site_offset();

		// return an adjusted time, by accepting the hour/minute and date from the frontend, changing the tz to the site tz, and converting to utc
		return date( 'c', strtotime( self::change_offset( $timestamp, self::number_to_tz( $site[ self::in_dst( $timestamp ) ? 'dst' : 'non' ] ) ) ) );
	}

	// get the timestamp adjusted for the site timezone
	public static function site_time( $timestamp=false ) {
		$timestamp = false === $timestamp ? time() : $timestamp;
		$off = self::site_offset();
		return $timestamp + ( $off[ $off['in_dst'] ? 'dst' : 'non' ] * HOUR_IN_SECONDS );
	}

	// get the site's offset informatino
	public static function site_offset() {
		static $site = false;
		// site settings
		if ( false === $site ) {
			$site = array(
				'non' => get_option( 'gmt_offset', 0 ), //self::tz_to_number( self::non_dst_tz_offset() ),
				'dst' => get_option( 'gmt_offset', 0 ), //self::tz_to_number( self::dst_tz_offset() ),
				'in_dst' => self::in_dst(),
			);
		}
		return $site;
	}

	// determine the difference in timezone for the specified timestamp and the site settings, in seconds
	public static function tz_diff( $timestamp, $zero_if_utc=false ) {
		static $site = false;
		// site settings
		if ( false === $site )
			$site = self::site_offset();

		// timestamp tz
		$tz = preg_replace( '#(\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2})#', '', $timestamp );
		if ( $tz == $timestamp || ! strlen( $tz ) )
			return 0;

		// flatten tz to number
		$tz = self::tz_to_number( $tz ) + ( $site['in_dst'] ? 1 : 0 );

		// return the difference in seconds
		return $zero_if_utc && 0 == $tz ? 0 : ( $site[ self::in_dst( $timestamp ) ? 'dst' : 'non' ] - $tz ) * HOUR_IN_SECONDS;
	}

	// convert a timezone string to a numeric value
	// input must be in format: [+-]\d{2}:\d{2}
	public static function tz_to_number( $string ) {
		$sign = substr( $string, 0, 1 );
		// break hours and minutes
		list( $hours, $minutes ) = explode( ':', substr( $string, 1 ) );

		// make a number from hours and minutes
		$number = absint( $hours ) + ( absint( $minutes ) > 0 ? 0.5 : 0 );

		// return with sign
		return $sign . $number;
	}

	// number to timezone
	public static function number_to_tz( $number ) {
		return sprintf(
			'%s%02s:%02s',
			$number < 0 ? '-' : '+',
			absint( floor( $number ) ),
			$number - floor( $number ) > 0 ? '30' : '00'
		);
	}

	// make a fake datestamp UTC
	public static function fake_utc_date( $datestamp ) {
		return self::change_offset( date( 'c', self::local_timestamp( $datestamp ) ), '+00:00' );
	}

	// change the offset of a timestamp
	public static function change_offset( $date, $new_offset='+00:00' ) {
		// get the date and time portion of the stamp
		preg_match( '#^(?P<stamp>\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2})#', $date, $match );

		// if we have a result, compile the new time
		if ( isset( $match['stamp'] ) )
			return $match['stamp'] . $new_offset;

		return $date;
	}

	// get the non-DST timezone
	public static function non_dst_tz_offset( $format='%s%02s:%02s' ) {
		static $offset = null;
		// do this once per page load
		if ( null !== $offset )
			return $offset;

		// get the numeric offset from the db
		$offset = get_option( 'gmt_offset', 0 );

		return $offset = self::number_to_tz( $offset );
	}

	// get the DST timezone
	public static function dst_tz_offset( $format='%s%02s:%02s' ) {
		static $offset = null;
		// do this once per page load
		if ( null !== $offset )
			return $offset;

		// get the numeric offset from the db
		$offset = get_option( 'gmt_offset', 0 ) + 1;

		return $offset = self::number_to_tz( $offset );
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
	public static function local_timestamp( $date, $dst_adjust=true, $from_utc=true ) {
		static $tz_string = false, $offset = false, $site = false;
		// get the current system offset values if they have not already been fetched
		if ( false === $tz_string || false === $offset || false === $site ) {
			$tz_string = get_option( 'timezone_string', 'UTC' );
			$offset = get_option( 'gmt_offset', 0 );
			$site = self::site_offset();
		}

		// get the gmt unix timestamp of the time
		$ts = strtotime( $date );

		// adjust the time, based on the offset
		$ts = $ts + ( ( $dst_adjust && $site['in_dst'] ? $site['dst'] : $site['non'] ) * HOUR_IN_SECONDS );

		return $ts;
	}

	// accept a datestamp, and make it local
	public static function local_datestamp( $date, $dst_adjust=true, $from_utc=true ) {
		// timestamp
		$ts = self::local_timestamp( $date, $dst_adjust, $from_utc );

		// construct an appropriate ISO datestamp representing the calculated date
		return date( 'Y-m-d\TH:i:s', $ts ) . self::local_time_offset();
	}

	// get the local time zone adjust for this site
	public static function local_time_offset( $relative_to=false) {
		// if no relative time was sent, use now
		$relative_to = false !== $relative_to ? $relative_to : time();

		$offset_number = get_option( 'gmt_offset', 0 );
		// if this is dst, adjust the offset number
		if ( self::in_dst( $relative_to ) )
			$offset_number += 1;

		// construct the offset
		$offset = sprintf( '%s%02s:%02s', $offset_number < 0 ? '-' : '+', floor( abs( $offset_number ) ), 0 != abs( $offset_number ) - floor( abs( $offset_number ) ) ? '30' : '00' );

		return $offset;
	}

	// accept a user input value for a time, and convert it to 24 hour time
	public static function to_24_hour( $raw_time ) {
		// get the various time parts
		preg_match( '#^(?P<hour>\d{1,2})(?:[^\dAPMapm]?(?<minute>\d{1,2}))?(?:[^\dAPMapm]?(?<second>\d{1,2}))?(?P<meridiem>[APMapm]+)?$#', $raw_time, $match );

		// if we have no matches of at least the hour field, then bail
		if ( ! is_array( $match ) || ! isset( $match['hour'] ) )
			return '';

		$match['hour'] = absint( $match['hour'] );
		// update the hour based on meridiem if present
		if ( isset( $match['meridiem'] ) ) {
			$mer = strtolower( $match['meridiem'] );
			// if it is pm and the hour is not 12pm, then add 12 hours
			if ( in_array( $mer, array( 'p', 'pm' ) ) && 12 != $match['hour'] )
				$match['hour'] = $match['hour'] + 12;
			// if it is am and the hour is 12am, make it a 0, for midnight
			if ( in_array( $mer, array( 'a', 'am' ) ) && 12 == $match['hour'] )
				$match['hour'] = 0;
		}

		// glue it back up and fill it in
		return sprintf(
			'%02s:%02s:%02s',
			$match['hour'],
			isset( $match['minute'] ) ? absint( $match['minute'] ) : 0,
			isset( $match['second'] ) ? absint( $match['second'] ) : 0
		);
	}

	public static $normalize_version = '2.0';
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

		$force = isset( $_COOKIE, $_COOKIE['qs-force'] ) && '1' == $_COOKIE['qs-force'];

		// grab the next 1000
		while ( $event_ids = get_posts( $args ) ) {
			// inc page for next iteration
			$pg_offset++;
			$args['paged'] = $pg_offset;

			// cycle through all results
			while ( is_array( $event_ids ) && count( $event_ids ) ) {
				// get the next event_id to update
				$event_id = array_shift( $event_ids );

				// see if this thing has gone through a tsfix before
				$tsfix = get_post_meta( $event_id, '_tsFix_update', true );

				// if not, setup a tsfix update key now
				if ( ! $tsfix ) {
					// get the start and end time of this event from db
					$start = get_post_meta( $event_id, '_start', true );
					$end = get_post_meta( $event_id, '_end', true );
					$orig_values = array( 'start' => $start, 'end' => $end );

					// if the old values have a timezone designation, just skip this item, unless being forced
					if ( ! $force && '+' == substr( $orig_values['start'], '-6', 1 ) )
						continue;

					add_post_meta( $event_id, '_tsFix_update', $orig_values );
				}

				// normalize the timestamp to UTC
				$start = date( 'Y-m-d\TH:i:s', strtotime( $start ) ) . '+00:00';
				$end = date( 'Y-m-d\TH:i:s', strtotime( $end ) ) . '+00:00';

				// save both times in the new format
				update_post_meta( $event_id, '_start', $start );
				update_post_meta( $event_id, '_end', $end );
			}
		}

		// add a record of the last time this ran
		update_option( '_last_run_otce_normalize_event_times', time() . '|' . self::$normalize_version );
	}

	// restore ticket times from a tsfix value
	public static function restore_event_times() {
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

		$force = isset( $_COOKIE, $_COOKIE['qs-force'] ) && '1' == $_COOKIE['qs-force'];

		// grab the next 1000
		while ( $event_ids = get_posts( $args ) ) {
			// inc page for next iteration
			$pg_offset++;
			$args['paged'] = $pg_offset;

			// cycle through all results
			while ( is_array( $event_ids ) && count( $event_ids ) ) {
				// get the next event_id to update
				$event_id = array_shift( $event_ids );

				// see if this thing has gone through a tsfix before
				$tsfix = get_post_meta( $event_id, '_tsFix_update', true );

				// if it hasnt, bail
				if ( ! is_array( $tsfix ) || ! isset( $tsfix['start'], $tsfix['end'] ) )
					continue;

				// save the current, potentially munged values, incase we have to restore a restore
				add_post_meta( $event_id, '_resTsFix_update', array( 'start' => get_post_meta( $event_id, '_start', true ), 'end' => get_post_meta( $event_id, '_end', true ) ) );

				$start = $end = '';
				// if the original dates have a timezone... then they are effect up and need to be adjusted to assume the timezone is wrong
				if ( '+' == substr( $tsfix['start'], '-6', 1 ) ) {
					$start = explode( '+', $tsfix['start'] );
					$start = current( $start );
					$end = explode( '+', $tsfix['end'] );
					$end = current( $end );
				// otherwise, assume the dates were for the site timezone, and update the ts
				} else {
					$start = str_replace( 'T', ' ', $tsfix['start'] );
					$end = str_replace( 'T', ' ', $tsfix['end'] );
				}

				// save both times in the new format
				update_post_meta( $event_id, '_start', $start . $offset );
				update_post_meta( $event_id, '_end', $end . $offset );
			}
		}

		// add a record of the last time this ran
		update_option( '_last_run_otce_restore_event_times', time() . '|' . self::$normalize_version );
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

	// map of moment-date format letters to base date-time-segment-type
	protected static $moment_format_map = array(
		// days
		'd' => 'd',
		'do' => 'd',
		'dd' => 'd',
		'ddd' => 'd',
		'dddd' => 'd',
		'D' => 'd',
		'Do' => 'd',
		'DD' => 'd',
		'DDD' => 'd',
		'DDDo' => 'd',
		'DDDD' => 'd',
		'e' => 'd',
		'E' => 'd',

		// month
		'M' => 'm',
		'Mo' => 'm',
		'MM' => 'm',
		'MMM' => 'm',
		'MMMM' => 'm',

		// Year
		'Y' => 'Y',
		'YY' => 'Y',
		'YYYY' => 'Y',

		// hour
		'h' => 'h',
		'hh' => 'h',
		'H' => 'h',
		'HH' => 'h',
		'k' => 'h',
		'kk' => 'h',

		// minute
		'm' => 'i',
		'mm' => 'i',

		// second
		's' => 's',
		'ss' => 's',

		// meridiem
		'a' => 'a',
		'A' => 'a',

		// timezone
		'z' => 'z',
		'zz' => 'z',
		'Z' => 'z',
		'ZZ' => 'z',

		// nothing important
		'-' => false,
		'/' => false,
		'.' => false,
		' ' => false,
		'x' => false,
		'X' => false,
		'S' => false,
		'SS' => false,
		'SSS' => false,
		'SSSS' => false,
		'SSSSS' => false,
		'gg' => false,
		'gggg' => false,
		'GG' => false,
		'GGGG' => false,
		'W' => false,
		'Wo' => false,
		'WW' => false,
		'w' => false,
		'wo' => false,
		'ww' => false,
	);

	// map of jquery-date format letters to base date-time-segment-type
	protected static $jquery_format_map = array(
		// days
		'd' => 'd',
		'dd' => 'd',
		'o' => 'd',
		'oo' => 'd',
		'D' => 'd',
		'DD' => 'd',

		// month
		'm' => 'm',
		'mm' => 'm',
		'M' => 'm',
		'MM' => 'm',

		// Year
		'y' => 'Y',
		'yy' => 'Y',

		// nothing important
		'@' => false,
		'!' => false,
		'-' => false,
		'/' => false,
		'.' => false,
		' ' => false,
	);

	// list of php-formats used within our plugin that might be customized
	public static $php_custom_date_formats = array(
		'D, F jS, Y',
		'D, F jS, Y h:ia',
		'D, F jS, Y \@ g:ia',
		'F jS, Y',
		'l jS \\o\\f F Y, h:ia',
		'Y-m-d_gia',
		'Y-m-d',
		'm-d-Y',
		'm/d/Y',
	);
	public static $php_custom_time_formats = array(
		'g:ia',
		'h:ia',
		'H:i:s',
	);
	public static $moment_custom_date_formats = array(
		'MM-dd-YY',
	);
	public static $moment_custom_time_formats = array(
	);
	public static $jqueryui_custom_date_formats = array(
		'mm-dd-yy',
	);
	public static $jqueryui_custom_time_formats = array(
	);

	// report whether this site uses DST or not, according to settings
	public static function use_dst() { return get_option( 'qsot-use-dst', 'yes' ) == 'yes'; }

	// report date and time format settings
	public static function maybe_translate( $string ) {
		static $trans_func = null;
		if ( null === $trans_func )
			$trans_func = function_exists( 'qtranxf_useCurrentLanguageIfNotFoundUseDefaultLanguage' ) ? 'qtranxf_useCurrentLanguageIfNotFoundUseDefaultLanguage' : false;
		return ! $trans_func ? $string : call_user_func( $trans_func, $string );
	}
	public static function date_format() { return self::maybe_translate( get_option( 'qsot-date-format', 'm-d-Y' ) ); }
	public static function hour_format() { return self::maybe_translate( get_option( 'qsot-hour-format', '12-hour' ) ); }
	public static function is_12_hour() { return get_option( 'qsot-hour-format', '12-hour' ) == '12-hour'; }
	public static function is_24_hour() { return get_option( 'qsot-hour-format', '12-hour' ) == '24-hour'; }

	// load all custom formats from the db
	protected static function _load_custom_formats( $type='php' ) {
		$formats = array();
		foreach ( self::$php_custom_date_formats as $format )
			if ( $value = get_option( 'qsot-custom-' . $type . '-date-format-' . sanitize_title_with_dashes( $format ), '' ) )
				$formats[ $format ] = self::maybe_translate( $value );
		foreach ( self::$php_custom_time_formats as $format )
			if ( $value = get_option( 'qsot-custom-' . $type . '-date-format-' . sanitize_title_with_dashes( $format ), '' ) )
				$formats[ $format ] = self::maybe_translate( $value );
		return $formats;
	}

	// reorder a time format, based on the settings
	public static function php_date_format( $format='m-d-Y', $sep=' ' ) {
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
		$conversion[ $format ] = trim( $date_format . $sep . $time_format );
		return $conversion[ $format ];
	}

	// reorder the time format, based on the settings, for a momentjs format
	public static function moment_date_format( $format='MM-dd-YY', $sep=' ' ) {
		static $conversions = false;
		// load all custom formats from db, the first time this function is called
		if ( false === $conversions )
			$conversions = self::_load_custom_formats( 'moment' );
		// only do this conversion once per input format
		if ( isset( $conversions[ $format ] ) )
			return $conversions[ $format ];

		$regex = array();
		// construct the regex
		foreach ( self::$moment_format_map as $key => $group )
			$regex[] = preg_quote( $key, '#' );
		usort( $regex, array( __CLASS__, 'sort_by_length_r' ) );

		// break the requested format into parts
		preg_match_all( '#(' . implode( '|', $regex ) . ')#s', $format, $matches, PREG_SET_ORDER );

		$segment = array();
		$last_segment = '';
		// cycle through the matches and organize them into segments
		foreach ( $matches as $match ) {
			$key = isset( self::$moment_format_map[ $match[1] ] ) ? self::$moment_format_map[ $match[1] ] : false;
			// if the part is does not have a segment, then skip it
			if ( ! $key ) {
				// if the last segment is present, then add this part to it, and continue
				if ( $last_segment && isset( $segment[ $last_segment ] ) )
					$segment[ $last_segment ] .= $match[1];
				continue;
			}

			// create a segment for this key if it does not exist yet
			if ( ! isset( $segment[ $key ] ) )
				$segment[ $key ] = '';
			$last_segment = $key;

			// add this part to the segment
			$segment[ $key ] .= $match[1];
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
		// adjust for weird 'k' format
		$time_format = str_replace( 'K', 'k', $time_format );
		// continue with other time formats
		if ( isset( $segment['i'] ) )
			$time_format .= $segment['i'];
		if ( isset( $segment['s'] ) )
			$time_format .= $segment['s'];
		if ( ! $is_24_hour && isset( $segment['a'] ) )
			$time_format .= $segment['a'];
		if ( isset( $segment['z'] ) )
			$time_format .= $segment['z'];

		// glue that shit together, and return
		$conversion[ $format ] = trim( $date_format . $sep . $time_format );
		return $conversion[ $format ];
	}

	// reorder the time format, based on the settings, for a jquery format
	public static function jquery_date_format( $format='mm-dd-yy' ) {
		static $conversions = false;
		// load all custom formats from db, the first time this function is called
		if ( false === $conversions )
			$conversions = self::_load_custom_formats( 'jquery' );
		// only do this conversion once per input format
		if ( isset( $conversions[ $format ] ) )
			return $conversions[ $format ];

		$regex = array();
		// construct the regex
		foreach ( self::$jquery_format_map as $key => $group )
			$regex[] = preg_quote( $key, '#' );
		usort( $regex, array( __CLASS__, 'sort_by_length_r' ) );

		// break the requested format into parts
		preg_match_all( '#(' . implode( '|', $regex ) . ')#s', $format, $matches, PREG_SET_ORDER );

		$segment = array();
		$last_segment = '';
		// cycle through the matches and organize them into segments
		foreach ( $matches as $match ) {
			$key = isset( self::$jquery_format_map[ $match[1] ] ) ? self::$jquery_format_map[ $match[1] ] : false;
			// if the part is does not have a segment, then skip it
			if ( ! $key ) {
				// if the last segment is present, then add this part to it, and continue
				if ( $last_segment && isset( $segment[ $last_segment ] ) )
					$segment[ $last_segment ] .= $match[1];
				continue;
			}

			// create a segment for this key if it does not exist yet
			if ( ! isset( $segment[ $key ] ) )
				$segment[ $key ] = '';
			$last_segment = $key;

			// add this part to the segment
			$segment[ $key ] .= $match[1];
		}

		$date_format_array = explode( '-', self::date_format() );
		$date_format = '';
		// reorder the date portion of the format, based on the settings
		foreach ( $date_format_array as $segment_key )
			$date_format .= isset( $segment[ $segment_key ] ) ? $segment[ $segment_key ] : '';

		// NOTE: jquery does not have time formats

		// glue that shit together, and return
		$conversion[ $format ] = trim( $date_format );
		return $conversion[ $format ];
	}

	// sort a list of strings by length, desc
	public static function sort_by_length_r( $a, $b ) {
		return strlen( $b ) - strlen( $a );
	}
}
