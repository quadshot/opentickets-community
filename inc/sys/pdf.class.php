<?php (__FILE__ == $_SERVER['SCRIPT_FILENAME']) ? die(header('Location: /')) : null;

if ( ! class_exists( 'QSOT_pdf' ) ):

if ( ! defined( 'QSOT_DEBUG_PDF' ) )
	define( 'QSOT_DEBUG_PDF', 0 );

class QSOT_pdf {
	// setup our class
	public static function pre_init() {
		// during activation, we need to do a couple things
		add_action( 'qsot-activate', array( __CLASS__, 'on_activate' ), 5000 );
	}

	// allow some pre-processing to occur on html before it gets integrated into a final pdf
	public static function from_html( $html, $title ) {
		// give us soem breathing room
		ini_set( 'max_execution_time', 180 );

		// pre-parse remote or url based assets
		try {
			$html = self::_pre_parse_remote_assets( $html );
		} catch ( Exception $e ) {
			die('error');
			echo '<h1>Problem parsing html.</h1>';
			echo '<h2>' . force_balance_tags( $e->getMessage() ) . '</h2>';
			return;
		}

		// if we are debugging the pdf, then depending on the mode, dump the html contents onw
		if ( QSOT_DEBUG_PDF & 2 ) {
			echo '<pre>';
			echo htmlspecialchars( $html );
			echo '</pre>';
			die();
		}

		// include the library
		require_once QSOT::plugin_dir() . 'libs/dompdf/dompdf_config.inc.php';

		// make and output the pdf
		$pdf = new DOMPDF();
		$pdf->load_html( $html );
		$pdf->render();
		$pdf->stream( sanitize_title_with_dashes( 'ticket-' . $title ) . '.pdf', array( 'Attachment' => 1 ) );
		exit;
	}

	// find any and all remote assets in the html of the pdf, and either cache them locally, and use the local url, or embed them directly into the html
	protected static function _pre_parse_remote_assets( $html ) {
		// next, 'flatten' all styles
		$html = preg_replace_callback( '#\<link([^\>]*?(?:(?:(\'|")[^\2]*?\2)[^\>]*?)*?)\>#s', array( __CLASS__, '_flatten_styles' ), $html );

		// first, find all images in the html, and try to localize them into base64 strings
		$html = preg_replace_callback( '#\<img([^\>]*?(?:(?:(\'|")[^\2]*?\2)[^\>]*?)*?)\>#s', array( __CLASS__, '_parse_image' ), $html );

		return $html;
	}

	// aggregate all css into style tags instead of link tags, and flatten imports
	protected static function _flatten_styles( $match ) {
		// if this is not a stylesheet link tag, then bail
		if ( ! preg_match( '#rel=[\'"]stylesheet[\'"]#', $match[0] ) )
			return $match[0];

		// get the tag atts
		$atts = self::_get_atts( $match[1] );

		// if there is no url then remove and bail
		if ( ! isset( $atts['href'] ) || empty( $atts['href'] ) )
			return '<!-- FAIL CSS 1: ' . $match[0] . ' -->';

		// get css file contents based on url
		$css_contents = self::_get_css_content( $atts['href'] );

		// if there is no content to embed, then remove and bail
		if ( empty( $css_contents ) )
			return '<!-- FAIL CSS 2: ' . $match[0] . ' -->';

		return '<style>' . $css_contents . '</style>';
	}

	// get the contents of a css file, and flatten the @import tags
	protected static function _get_css_content( $url ) {
		// get the image local path
		$local_path = QSOT_cache_helper::find_local_path( $url );

		// if there is not a local file, then bail with empty str
		if ( ! $local_path )
			return '';

		// get the contents of the local file
		$content = ! WP_DEBUG ? @file_get_contents( $local_path ) : file_get_contents( $local_path );

		// if there is no content then return empty content
		if ( ! $content || '' == trim( $content ) )
			return '';

		// flatten any @imports
		$content = preg_replace_callback( '#@import url\((.*?)\);#', array( __CLASS__, '_flatten_at_import' ), $content );

		return $content;
	}

	// flatten any @import tag we found
	public static function _flatten_at_import( $match ) {
		// if there is no import url, then remove and bail
		if ( empty( $match[1] ) )
			return '';

		return self::_get_css_content( $match[1] );
	}

	// construct a new image tag based on the one we found
	public static function _parse_image( $match ) {
		// get the tag atts
		$atts = self::_get_atts( $match[1] );

		// if there is not an src, bail
		if ( ! isset( $atts['src'] ) || empty( $atts['src'] ) )
			return $match[0] . ( WP_DEBUG ? '<!-- NO SRC : BAIL -->' : '' );

		// roll through embeded images
		if ( preg_match( '#^data:image\/#', $atts['src'] ) )
			return $match[0] . ( WP_DEBUG ? '<!-- EMBEDED SRC : BAIL -->' : '' );

		// get the image local path
		$local_path = QSOT_cache_helper::find_local_path( $atts['src'] );

		// if there was not a local path to be found, then remove the image from the output and bail
		if ( '' == $local_path )
			return ( WP_DEBUG ? '<!-- NO LOCAL PATH -->' : '' );

		// next, text that the local file is actually an image
		$img_data = ! WP_DEBUG ? @getimagesize( $local_path ) : getimagesize( $local_path );

		// there was no image data, or is not a vaild supported type, remove and then bail
		if ( ! is_array( $img_data ) || ! isset( $img_data[0], $img_data[1], $img_data[2] ) || ! in_array( $img_data[2], array( IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_JPEG ) ) )
			return ( WP_DEBUG ? '<!-- INVALID IMAGE TYPE : ' . $local_path . ' : ' . ( is_array( $img_data ) ? http_build_query( $img_data ) : 'NOT-ARRAY' ) . ' -->' : '' );

		// set the image path to the local path
		$atts['src'] = $local_path;

		// set the width and height from the data
		if ( ( ! isset( $atts['width'] ) || empty( $atts['width'] ) ) && $img_data[0] )
			$atts['width'] = $img_data[0];
		if ( ( ! isset( $atts['height'] ) || empty( $atts['height'] ) ) && $img_data[1] )
			$atts['height'] = $img_data[1];

		// reconstruct the img tag
		$pieces = array();
		foreach ( $atts as $k => $v )
			$pieces[] = $k . '="' . esc_attr( $v ) . '"';
		$tag = '<img ' . implode( ' ', $pieces ) . ' />';

		// if debugging, the add the path that we are looking for right above the image
		if ( WP_DEBUG && QSOT_DEBUG_PDF & 1 )
			$tag = sprintf(
					'<pre style="width:%spx;height:auto;font-size:10px;border:1px solid #000;word-wrap:break-word;display:block;">%s</pre>',
					$atts['width'],
					implode( '<br/>', str_split( $local_path, ( $atts['width'] / 6 ) - 1 ) )
				) . $tag;

		return $tag;
	}

	// get the attributes from part of a tag element
	protected static function _get_atts( $str ) {
		// get the atts
		preg_match_all( '#([^\s=]+?)=([\'"])([^\2]*?)\2#', $str, $raw_atts, PREG_SET_ORDER );

		// parse the raw atts into key value pairs
		$atts = array();
		if ( count( $raw_atts ) )
			foreach ( $raw_atts as $attr )
				$atts[ $attr[1] ] = $attr[3];

		return $atts;
	}

	// during activation
	public static function on_activate() {
		// determine the cache dir name
		$u = wp_upload_dir();
		$base_dir_name = 'qsot-dompdf-fonts-' . substr( sha1( site_url() ), 21, 5 );
		$final_path = $u['basedir'] . DIRECTORY_SEPARATOR . $base_dir_name . DIRECTORY_SEPARATOR;

		try {
			$font_path = QSOT_cache_helper::create_find_path( $final_path, 'fonts' );
			if ( ! is_writable( $font_path ) )
				throw new Exception( sprintf( __( 'The %s path is not writable. Please update the permissions to allow write access.', 'opentickets-community-edition' ), 'fonts' ) );
		} catch ( Exception $e ) {
			// just fail. we can go without the custom config
			return;
		}

		// make sure that the libs dir is also writable
		$libs_dir = QSOT::plugin_dir() . 'libs/';
		if ( ! @file_exists( $libs_dir ) || ! is_dir( $libs_dir ) || ! is_writable( $libs_dir ) ) 
			return;

		// find all the fonts that come with the lib we packaged with the plugin, and move them to the new fonts dir, if they are not already there
		$remove_files = $updated_files = array();
		$core_fonts_dir = $libs_dir . 'dompdf/lib/fonts/';
		// open the core included fonts dir
		if ( @file_exists( $core_fonts_dir ) && is_writable( $core_fonts_dir ) && ( $dir = opendir( $core_fonts_dir ) ) ) {
			// find all the files in the dir
			while ( $file_basename = readdir( $dir ) ) {
				$filename = $core_fonts_dir . $file_basename;
				$new_filename = $font_path . $file_basename;
				// if the current file is a dir or link, skip it
				if ( is_dir( $filename ) || is_link( $filename ) )
					continue;

				// overwrite any existing copy of the file, with the new version from the updated plugin
				if ( copy( $filename, $new_filename ) ) {
					$remove_files[] = $filename;
					$updated_files[] = basename( $filename );
				}
			}

			file_put_contents( $font_path . 'updated', 'updated on ' . date( 'Y-m-d H:i:s' ) . ":\n" . implode( "\n", $remove_files ) );

			// attempt to create the new custom config file
			if ( $config_file = fopen( $libs_dir . 'wp.dompdf.config.php', 'w+' ) ) {
				// create variable names to use in the heredoc
				$variable_names = array(
					'$_SERVER["SCRIPT_FILENAME"]',
				);

				// generate the contents of the config file
				$contents = <<<CONTENTS
<?php ( __FILE__ == {$variable_names[0]} ) ? die( header( 'Location: /' ) ) : null;
if ( ! defined( 'DOMPDF_FONT_DIR' ) )
	define( 'DOMPDF_FONT_DIR', '$font_path' );
if ( ! defined( 'DOMPDF_FONT_CACHE' ) )
	define( 'DOMPDF_FONT_CACHE', '$font_path' );
CONTENTS;
				
				// write the config file, and close it
				fwrite( $config_file, $contents, strlen( $contents ) );
				fclose( $config_file );

				// remove any files that are marked to be removed, now that we have successfully written them to the new location, and pointed DOMPDF at them
				/* skip this for now */
				/*
				if ( is_array( $remove_files ) && count( $remove_files ) )
					foreach ( $remove_files as $remove_file )
						if ( is_writable( $remove_file ) && ! is_dir( $remove_file ) && ! is_link( $remove_file ) )
							@unlink( $remove_file );
				*/
			}
		}
	}
}

if ( defined( 'ABSPATH' ) && function_exists( 'add_action' ) )
	QSOT_pdf::pre_init();

endif;

if ( ! class_exists( 'QSOT_cache_helper' ) ):

class QSOT_cache_helper {
	// cached asset map, original_url => local_file_path
	protected static $cache_map = array();

	// cache path
	protected static $cache_path = false;

	// is this a force recache?
	protected static $force_recache = null;

	// figure out the local path of the asset, based on the found url
	public static function find_local_path( $url ) {
		static $local = false, $local_url = false, $whats_old_in_seconds = false;

		// determine if this is a force recache
		if ( null === self::$force_recache )
			self::$force_recache = isset( $_COOKIE, $_COOKIE['qsot-recache'] ) && '1' == $_COOKIE['qsot-recache'];

		// figure out what is considered old, in seconds
		if ( false === $whats_old_in_seconds )
			$whats_old_in_seconds = apply_filters( 'qsot-whats-old-in-seconds', 7 * DAY_IN_SECONDS );

		// figure out the local url
		if ( false === $local )
			$local = ! WP_DEBUG ? @parse_url( $local_url = site_url() ) : parse_url( $local_url = site_url() );

		// if we have already cached the local path of this asset, then just use the cached value
		if ( isset( self::$cache_map[ $url ] ) )
			return self::$cache_map[ $url ];

		// parse the src
		$parsed_url = ! WP_DEBUG ? @parse_url( $url ) : parse_url( $url );

		// if the parse failed, something is majorly effed up with the image, so remove the image from the html and bail
		if ( false == $parsed_url )
			return self::$cache_map[ $url ] = '';

		// try to find/create a relevant local copy of the src

		// if this is a URL to a resource outside of this site, then
		if ( ! self::_is_local_file( $parsed_url, $local, $url, $local_url ) ) {
			// figure out the cache dir now, since we definitely need it
			if ( false === self::$cache_path )
				self::create_find_cache_dir();
			if ( false === self::$cache_path )
				return self::$cache_map[ $url ] = '';

			// figure out the target filename, based on the url
			$local_filename = self::_local_filename_from_url( $url, $parsed_url );
			if ( empty( $local_filename ) )
				return self::$cache_map[ $url ] = '';
			$local_filename = self::$cache_path . $local_filename;

			// if the file does not already exist, or if it is old, then fetch and store a new copy
			$age = self::_get_file_age( $local_filename );
			if ( self::$force_recache || ! $age || $age > $whats_old_in_seconds ) {
				// setup the http api args
				$args = array(
					'timeout' => 5,
					'redirection' => 3,
				);

				// get the final response
				$response = wp_remote_get( html_entity_decode( $url ), $args );
				if ( WP_DEBUG && is_wp_error( $response ) )
					die( var_dump( 'WP_Error on wp_remote_get( "'. $url . '", ' . @json_encode( $args ) . ' )', $response ) );
				$response = is_array( $response ) && isset( $response['body'] ) ? $response['body'] : '';

				// if there was not a valid response, then bail now
				if ( empty( $response ) )
					return self::$cache_map[ $url ] = '';

				// write the data to the local file. on failure, bail
				$test = ! WP_DEBUG ? ! @file_put_contents( $local_filename, $response ) : ! file_put_contents( $local_filename, $response );
				if ( $test )
					return self::$cache_map[ $url ] = '';
			}

			// update the cached filename, and return the new file path
			self::$cache_map[ $url ] = $local_filename;
			return self::$cache_map[ $url ] = 'file://' . $local_filename;
		}

		// if it is not obviously a remote asset, assume it is local, and start trying to find the actual filename
		// if the path is empty, bail
		if ( '' == $parsed_url['path'] || '/' == $parsed_url['path'] || '\\' == $parsed_url['path'] )
			return self::$cache_map[ $url ] = '';

		// if it is a relative path, then figure out the part of the path that would make it absolute
		$extra_path = '';
		if ( '/' !== $parsed_url['path']{0} && '\\' !== $parsed_url['path']{0} ) {
			// find the request path
			$req_purl = ! WP_DEBUG ? @parse_url( isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '' ) : parse_url( isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '' );

			// if there is a paht, and if the request_uri is a dir, then use the 'path' as the extra_path. otherwise it is probably a file, so get the parent dir of the file
			$test_path = realpath( ABSPATH . $req_purl['path'] );
			if ( '' != $req_purl['path'] && $test_path )
				$extra_path = trailingslashit( is_dir( $test_path ) ? $req_purl['path'] : dirname( $req_purl['path'] ) );
		}

		// adjust the parsed_url path to account for the wordpress install being in a subdir of the domain
		$parsed_path = $parsed_url['path'];
		if ( ( '/' === $parsed_path{0} || '\\' === $parsed_path{0} ) && isset( $local['path'] ) && ( $local_adjust = untrailingslashit( $local['path'] ) ) && 0 === strpos( $parsed_path, $local_adjust ) )
			$parsed_path = substr( $parsed_path, strlen( $local_adjust ) );

		// if the file exists, is a file, and is readable, then success
		$test_path = realpath( ABSPATH . $extra_path . $parsed_path );
		$test = ! WP_DEBUG
				? $test_path && @file_exists( $test_path ) && @is_file( $test_path ) && @is_readable( $test_path )
				: $test_path && file_exists( $test_path ) && is_file( $test_path ) && is_readable( $test_path );
		if ( $test )
			return self::$cache_map[ $url ] = 'file://' . $test_path;

		// otherwise bail on absolute paths
		return self::$cache_map[ $url ] = '';
	}

	// determine if a url is of a local resource
	protected static function _is_local_file( $parsed_url, $parsed_local, $url, $local_url ) {
		// if there is no url host, then it is assumed that the host is the local host, meaning it is a local file
		if ( ! isset( $parsed_url['host'] ) )
			return true;

		// if the scheme is present, and set to 'file' then it is definitely supposed to be a local asset
		if ( isset( $parsed_url['scheme'] ) && 'file' === strtolower( $parsed_url['scheme'] ) )
			return true;

		// figure out the host and path of both urls. this will help determine if this asset lives at a local path. the site_url() could be a host with a path, if the installation is in a subdir
		$remote_path = strtolower( end( explode( '/', $url, 3 ) ) );
		$local_path = strtolower( end( explode( '/', $local_url, 3 ) ) );

		// if the local path is present at the beginning of the remote path string, then it is a local path
		if ( 0 === strpos( $remote_path, $local_path ) )
			return true;

		// otherwise it is most logically a remote file
		return false;
	}

	// build a local filename based on the remote url path
	protected static function _local_filename_from_url( $url, $purl ) {
		// find out the extension of the file
		$ext = '';
		if ( isset( $purl['path'] ) ) {
			$basename = $purl['path'];
			if ( ! empty( $basename ) ) {
				$basename = explode( '.', $basename );
				if ( count( $basename ) > 1 )
					$ext = end( $basename ) . '.';
			}
		}

		// create and return a unique, secure, but consistent cache name for the file
		return $ext . sha1( AUTH_SALT . $url );
	}

	// determine the age of a local file
	protected static function _get_file_age( $path ) {
		// get the file modify time
		$ftime = ! WP_DEBUG || ! ( QSOT_DEBUG_PDF & 4 ) ? @filemtime( $path ) : filemtime( $path );

		// if there was no file modify time, then the file does not exist, for bail
		if ( false === $ftime )
			return false;

		// adjust for the windows DST bug http://bugs.php.net/bug.php?id=40568
		$ftime_dst = ( date( 'I', $ftime ) == 1 );
		$system_dst = ( date( 'I' ) == 1 );

		// calculate the DST bug adjustment
		$adjustment = 0;
		if ( ! $ftime_dst && $system_dst )
			$adjustment = 3600;
		else if ( $ftime_dst && ! $system_dst )
			$adjustment = -3600;

		// finalize ftime
		$ftime = $ftime + $adjustment;

		return time() - $ftime;
	}

	// create or find the cache dir
	public static function create_find_cache_dir() {
		// if we already have a cache path reutrn it
		if ( ! empty( self::$cache_path ) )
			return self::$cache_path;

		// determine the cache dir name
		$u = wp_upload_dir();
		$base_dir_name = 'qsot-cache-' . substr( sha1( site_url() ), 10, 10 );
		$final_path = $u['basedir'] . DIRECTORY_SEPARATOR . $base_dir_name . DIRECTORY_SEPARATOR;

		return self::$cache_path = trailingslashit( self::create_find_path( $final_path ) );
	}

	// create or find a path
	public static function create_find_path( $final_path, $path_name='cache' ) {
		// if the path already exists, just return the path
		$test = ! WP_DEBUG ? @file_exists( $final_path ) && @is_dir( $final_path ) && @is_readable( $final_path ) : file_exists( $final_path ) && is_dir( $final_path ) && is_readable( $final_path );
		if ( $test )
			return trailingslashit( $final_path );

		// if the path is simply not readable, exception saying that
		$test = ! WP_DEBUG ? @file_exists( $final_path ) && ! @is_readable( $final_path ) : file_exists( $final_path ) && ! is_readable( $final_path );
		if ( $test )
			throw new Exception( sprintf( __( 'The %s path exists, but it cannot be read. Please update the permissions to allow read access.', 'opentickets-community-edition' ), $path_name ) );

		// if the path is there, but is not a dir, exception saying that
		$test = ! WP_DEBUG ? @file_exists( $final_path ) && ! @is_dir( $final_path ) : file_exists( $final_path ) && ! is_dir( $final_path );
		if ( $test )
			throw new Exception( sprintf( __( 'The %s path exists, but is not a dir. Please remove or rename the existing file, and create a directory with the cache path name.', 'opentickets-community-edition' ), $path_name ) );

		// at this point the path probably does not exist. try to create it.

		// first check if we have permission to create it
		$parent_dir = dirname( $final_path );
		$test = ! WP_DEBUG ? ! @is_writable( $parent_dir ) : ! is_writable( $parent_dir );
		if ( $test )
			throw new Exception( sprintf( __( 'Could not create the %s path directory. Please update the permissions to allow write access.', 'opentickets-community-edition' ), $path_name ) );

		// attempt to create a new dir for the path
		$test = ! WP_DEBUG ? ! @mkdir( $final_path ) : ! mkdir( $final_path );
		if ( $test )
			throw new Exception( sprintf( __( 'Unable to create the %s path directory.', 'opentickets-community-edition' ), $path_name ) );

		// if thei new path is not writable (unlikely) then fail
		$test = ! WP_DEBUG ? ! @is_writeable( $final_path ) : ! is_writeable( $final_path );
		if ( $test )
			throw new Exception( sprintf( __( 'The %s path is not writable. Please update the permissions to allow write access.', 'opentickets-community-edition' ), $path_name ) );

		return $final_path;
	}
}

endif;
