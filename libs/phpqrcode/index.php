<?php    

$debug = false;

if ( $debug === true ) {
	ini_set( 'display_erorrs', 1 );
	ini_set( 'html_errors', 1 );
	error_reporting( E_ALL );
} else {
	ini_set( 'display_erorrs', 0 );
	ini_set( 'html_errors', 0 );
	error_reporting( 0 );
}
/* old abuse protection
$ref = isset($_SERVER['HTTP_REFERER']) ? parse_url($_SERVER['HTTP_REFERER']) : '';
$host = strtolower(isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : ( isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : '' ));
$same_server = isset($_SERVER['SERVER_ADDR'], $_SERVER['REMOTE_ADDR']) && $_SERVER['SERVER_ADDR'] == $_SERVER['REMOTE_ADDR'];
if (!$same_server && !(isset($ref['host']) && $host == strtolower($ref['host']))) die();
*/

if (!isset($_GET['d']) || empty($_GET['d'])) die();
$d = strrev( @base64_decode( str_replace( array( ' ', '-', '_', '~' ), array( '+', '+', '=', '/' ), $_GET['d'] ) ) );
if (empty($d)) die();

// abuse protection
// check that needed values are presend
$d = @json_decode( $d, true );
if ( !is_array( $d ) ) die( $debug ? '<!-- not array -->' : '' );
$sig = isset( $d['sig'], $d['p'], $d['d'] ) ? $d['sig'] : false;
if ( empty( $sig ) ) die( $debug ? '<!-- empty sig -->' : '' );
unset( $d['sig'] );
ksort( $d );

// find defines from the wp-config
function qsot_fetch_defines( $what, $p ) {
	// get current host name for later compare
	$current_host = strtolower( isset( $_SERVER['SERVER_NAME'] ) ? $_SERVER['SERVER_NAME'] : ( isset( $_SERVER['HTTP_HOST'] ) ? $_SERVER['HTTP_HOST'] : false ) );
	if ( empty( $current_host ) ) die( $debug ? '<!-- empty current_host -->' : '' );

	// break down our indicator url
	$p = @parse_url( $p );
	// validate that the indicator url is from our domain
	if ( strtolower( isset( $p['host'] ) ? $p['host'] : '' ) != $current_host ) die( $debug ? '<!-- current host mismatch : ' . $p['host'] . ' / ' . $current_host . ' -->' : '' );

	// otce path. the starting path to look up from
	$otce_path = dirname( dirname( dirname( __FILE__ ) ) );

	// container for the path to the wp-config file
	$path_to_file = '';

	// path to the possibly present custom config, which defines where the wp-config.php file is located, so we dont have to look for it
	$custom_config_path = dirname( $otce_path ). DIRECTORY_SEPARATOR . 'qsot-phpqrconfig.php';
	$custom_config_exists = false;

	// first check if we have a config file added above the otce dir that defines where to look for wp-config
	if ( @file_exists( $custom_config_path ) && is_readable( $custom_config_path ) ) {
		include_once $custom_config_path;
		if ( defined( 'QSOT_WP_CONFIG_LOCATION' ) && @file_exists( QSOT_WP_CONFIG_LOCATION ) && is_readable( QSOT_WP_CONFIG_LOCATION ) ) {
			$path_to_file = QSOT_WP_CONFIG_LOCATION;
			$custom_config_exists = true;
		}
	}

	// if we do not know where the wp-config is yet, then look for it in the most common paths to check
	if ( ! $path_to_file ) {
		$search_paths = array(
			dirname( dirname( dirname( $otce_path ) ) ),
			rtrim( realpath( $_SERVER['DOCUMENT_ROOT'] ), '\\/' ),
			dirname( dirname( dirname( dirname( $otce_path ) ) ) ),
			dirname( realpath( $_SERVER['DOCUMENT_ROOT'] ) ),
		);

		// cycle through all the common paths, and check for the existence of the wp-config.php file
		foreach ( $search_paths as $search_path ) {
			$test_file_path = $search_path . DIRECTORY_SEPARATOR . 'wp-config.php';
			if ( @file_exists( $test_file_path ) && is_readable( $test_file_path ) ) {
				$path_to_file = $test_file_path;
				break;
			}
		}
	}

	// if we still do not have a path to wp-config.php, then try to bruteforce the location by traversing upwards until we find it or cannot go any further
	if ( ! $path_to_file ) {
		$last_path = $next_path = $otce_path;
		while ( ( $next_path = dirname( $next_path ) ) && $next_path != $last_path && is_readable( $next_path ) ) {
			$last_path = $next_path;
			if ( file_exists( $next_path . DIRECTORY_SEPARATOR . 'wp-config.php' ) ) {
				$path_to_file = $next_path . DIRECTORY_SEPARATOR . 'wp-config.php';
			}
		}
	}

	// determine where the wp-config is
	if ( empty( $path_to_file ) || ! file_exists( $path_to_file ) || !is_readable( $path_to_file ) ) die( $debug ? '<!-- missing wp-config -->' : '' );

	// at this point we have the config file. lets try to create that custom config path if we can so we can save ourselves some time later
	if ( ! $custom_config_exists && is_writable( dirname( $custom_config_path ) ) ) {
		// if the config file exists, but does not contain what we need, then just try to remove it
		if ( @file_exists( $custom_config_path ) && is_writable( $custom_config_path ) )
			unlink( $custom_config_path );

		// if we can create the file from scratch, do it now
		if ( ! @file_exists( $custom_config_path ) )
			file_put_contents( $custom_config_path, "<?php if ( ! defined( 'QSOT_WP_CONFIG_LOCATION' ) ) define( 'QSOT_WP_CONFIG_LOCATION', '{$path_to_file}' );" );
	}

	// search the config for the requested defines
	$contents = file_get_contents( $path_to_file );
	$out = array( $path_to_file );
	foreach ( $what as $define ) {
		preg_match_all( '#.*define.*' . preg_quote( $define, '#' ) . '(\'|")\s*,\s*(\'|")([^\2]+?)\2#s', $contents, $matches, PREG_SET_ORDER );
		if ( empty( $matches ) ) {
			$out[] = '';
		} else {
			$out[] = $matches[0][3];
		}
	}

	return $out;
}

// validate signature
list( $wp_config, $key, $salt ) = qsot_fetch_defines( array( 'NONCE_KEY', 'NONCE_SALT' ), $d['p'] );
$test = sha1( $key . @json_encode( $d ) . $salt );
if ( $test != $sig ) die( $debug ? '<!-- hash mismatch : ' . $wp_config . ' = ' . $sig . ' / '. $test . ' -->' : '' );
// end abuse protection


include_once 'qrlib.php';
//QRCode::png($d, false, 'L', 3, 1);
$enc = QRencode::factory('L', 3, 1);

$outfile = false;
try {
	ob_start();
	$tab = $enc->encode($d['d']);
	$err = ob_get_contents();
	ob_end_clean();

	if ($err != '')
		QRtools::log($outfile, $err);

	$maxSize = (int)(QR_PNG_MAXIMUM_SIZE / (count($tab)+2 * $enc->margin));

	QRimage::jpg($tab, $outfile, 2.5/* min(max(1, $enc->size), $maxSize)*/, $enc->margin, 100);
} catch (Exception $e) {
	QRtools::log($outfile, $e->getMessage());
}
