<?php (__FILE__ == $_SERVER['SCRIPT_FILENAME']) ? die(header('Location: /')) : null;
// track version since the lib does not do it itself... sigh
define( 'DOMPDF_VERSION', '0.6.1' );
require_once 'dompdf.custom.functions.php';

define( 'DOMPDF_CHROOT', realpath( DOMPDF_DIR . '/../' ) );

if ( @file_exists( DOMPDF_CHROOT . 'wp.dompdf.config.php' ) ) {
	require_once DOMPDF_CHROOT . 'wp.dompdf.config.php';
}

if ( ! defined( 'DOMPDF_ENABLE_REMOTE' ) )
	define("DOMPDF_ENABLE_REMOTE", true);
if ( ! defined( 'DOMPDF_ENABLE_IMAGICK' ) )
	define("DOMPDF_ENABLE_IMAGICK", false);
if ( ! defined( 'DOMPDF_LOG_OUTPUT_FILE' ) )
	define( 'DOMPDF_LOG_OUTPUT_FILE', '' );

//define("DOMPDF_TEMP_DIR", "/tmp");
//define("DOMPDF_FONT_DIR", DOMPDF_DIR."/lib/fonts/");
//define("DOMPDF_FONT_CACHE", DOMPDF_DIR."/lib/fonts/");
//define("DOMPDF_UNICODE_ENABLED", true);
//define("DOMPDF_PDF_BACKEND", "PDFLib");
//define("DOMPDF_DEFAULT_MEDIA_TYPE", "print");
//define("DOMPDF_DEFAULT_PAPER_SIZE", "letter");
//define("DOMPDF_DEFAULT_FONT", "serif");
//define("DOMPDF_DPI", 72);
//define("DOMPDF_ENABLE_PHP", true);
//define("DOMPDF_ENABLE_CSS_FLOAT", true);
//define("DOMPDF_ENABLE_JAVASCRIPT", false);
//define("DEBUGPNG", true);
//define("DEBUGKEEPTEMP", true);
//define("DEBUGCSS", true);
//define("DEBUG_LAYOUT", true);
//define("DEBUG_LAYOUT_LINES", false);
//define("DEBUG_LAYOUT_BLOCKS", false);
//define("DEBUG_LAYOUT_INLINE", false);
//define("DOMPDF_FONT_HEIGHT_RATIO", 1.0);
//define("DEBUG_LAYOUT_PADDINGBOX", false);
//define("DOMPDF_LOG_OUTPUT_FILE", dirname(__FILE__)."/log.htm");
//define("DOMPDF_ENABLE_HTML5PARSER", true);
//define("DOMPDF_ENABLE_FONTSUBSETTING", true);

// DOMPDF authentication
//define("DOMPDF_ADMIN_USERNAME", "user");
//define("DOMPDF_ADMIN_PASSWORD", "password");
