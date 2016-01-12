<?php if ( __FILE__ == $_SERVER['SCRIPT_FILENAME'] ) die( header( 'Location: /') );

if (!class_exists('QSOT_Templates')):

class QSOT_Templates {
	protected static $o = null; // holder for all options of the events plugin

	public static function pre_init() {
		// load the settings. theya re required for everything past this point
		$settings_class_name = apply_filters('qsot-settings-class-name', '');
		if (empty($settings_class_name)) return;
		self::$o = call_user_func_array(array($settings_class_name, "instance"), array());

		// qsot template locator. checks theme first, then our templates dir
		add_filter('qsot-locate-template', array(__CLASS__, 'locate_template'), 10, 4);

		// similar to above, only specifically for templates that we may have overriden from woo.... like admin templates
		add_filter('woocommerce_locate_template', array(__CLASS__, 'wc_locate_template'), 10, 3);

		// add our plugin page templates to the list of acceptable page templates
		add_filter( 'init', array( __CLASS__, 'rig_theme_page_template_cache' ), 0 );

		// add our templates to the list of templates to show in the admin, which is crosschecked with the list of acceptable page templates
		add_filter( 'theme_page_templates', array( __CLASS__, 'add_extra_templates' ), 10, 1 );

		// load our plugin page templates when they are needed, since core does not allow doing this from a plugin dir itself
		add_filter( 'template_include', array( __CLASS__, 'intercept_page_template_request' ), 100, 1 );
	}

	// we need to add our plugin's callendar template to the theme cache of page templates, because for some dumb reason, core started requiring that a page template exist in the theme in order to appear on the list.
	// this function allows us to use our page template that is packaged with the plugin, regardless of whether it lives in the theme or not
	// this is needed because if you look in WP_Theme::get_page_templates(), there is no way to inject a page template if there is not one in the theme directory
	public static function rig_theme_page_template_cache() {
		// get a list of page templates not in the theme
		$extras = apply_filters('qsot-templates-page-templates', array());

		// load the exisitng list of templates for the theme. start by loading the theme
		$theme = wp_get_theme();
		// build the cache hash, used for the cache keys. copied from WP_Theme::__construct()
		$cache_hash = md5( $theme->get_theme_root() . '/' . $theme->get_stylesheet() );
		// force the cache key to generate, in case it hasn't already
		$theme->get_page_templates();
		// fetch the current list of templates. copied from WP_Theme::cache_get
		$list = wp_cache_get( 'page_templates-' . $cache_hash, 'themes' );

		// add our list to the original list
		$list = array_merge( is_array( $list ) ? $list : array(), $extras );
		// save the list again, once our page templates have been added. copied from WP_Theme::cache_add
		wp_cache_set( 'page_templates-' . $cache_hash, $list, 'themes', 1800 );
		// profit!
	}

	// add our page template to the list of page templates
	public static function add_extra_templates( $list ) {
		$extras = apply_filters( 'qsot-templates-page-templates', array() );
		return array_merge( $list, $extras );
	}

	// when a page template that existing in our plugin is requested, we need to load it from the plugin if it does not exist in the theme dir. this function handles that
	public static function intercept_page_template_request( $current ) {
		// only perform this logic if the current requested assset is a page
		if ( ! is_page() )
			return $current;

		// get a list of our plugin page templates
		$intercept = apply_filters( 'qsot-templates-page-templates', array() );

		// find the name of the template requested by this page
		$template = get_page_template_slug();

		// if the template is on the list of templates inside our plugin, then
		if ( isset( $intercept[ $template ] ) ) {
			$templates = array();

			// add our file to a list of files to search for in the plugin template dir
			if ( $template && 0 === validate_file( $template ) )
				$templates[] = $template;

			// find any files that match the filename in the stylesheet dir, then the theme dir, then our plugin dir. if none are found, then use whatever the $current was when the function was called
			$current = apply_filters( 'qsot-locate-template', $current, $templates );
		}

		return $current;
	}

	// locate a given template. first check the theme for it, then our plugin dirs for fallbacks
	public static function locate_template( $current='', $files=array(), $load=false, $require_once=false ) {
		// normalize the list of potential files
		$files = ! empty( $files ) ? (array)$files : $files;

		// if we have a list of files
		if ( is_array( $files ) && count( $files ) ) {
			// first search the theme
			$templ = locate_template( $files, $load, $require_once );

			// if there was not a matching file in the theme, then search our backup dirs
			if ( empty( $templ ) ) {
				// aggregate a list of backup dirs to search
				$dirs = apply_filters( 'qsot-template-dirs', array( self::$o->core_dir . 'templates/' ) );
				$qsot_path = '';

				// add the legacy directory within the theme that holds the legacy OTCE templates
				array_unshift( $dirs, get_stylesheet_directory() . '/' . $qsot_path, get_template_directory() . '/' . $qsot_path );

				// for each file in the list, try to find it in each backup dir
				foreach ( $files as $file ) {
					// normalize the filename, and skip any empty ones
					$file = trim( $file );
					if ( '' === $file )
						continue;

					// check each backup dir for this file
					foreach ( $dirs as $dir ) {
						$dir = trailingslashit( $dir );
						// if the file exists, then use that one, and bail the remainder of the search
						if ( file_exists( $dir . $file ) && is_readable( $dir . $file ) ) {
							$templ = $dir . $file;
							break 2;
						}
					}
				}
			}

			// if there is a template found, and we are being asked to include it, the include it, by either 'require' or 'include' depending on the passed params
			if ( ! empty( $templ ) && $load ) {
				if ( $require_once )
					require_once $templ;
				else
					include $templ;
			}

			// if we found a template, make sure to update the return value with the full path to the file
			if ( ! empty( $templ ) )
				$current = $templ;
		}

		return $current;
	}

	public static function wc_locate_template($current, $template_name, $template_path) {
		$name = $template_name;
		$found = self::locate_woo_template( $name );
		return $found ? $found : $current;
	}

	// created to track down a specific theme issue
	protected static function _lg( $msg ) {
		?>
			<script>
				( function() { var args = <?php echo @json_encode( func_get_args() ) ?>; if ( console && console.log && 'function' == typeof console.log ) console.log.apply( console.log, args ); } )();
			</script>
		<?php
	}

	// locate a template that comes with core woocommerce. this template could have a potential override template in this plugin
	public static function locate_woo_template( $name, $type='' ) {
		$found = null;
		$key = $type . ':' . md5( $name );

		// if we found the file previously, then just use the cache instead of continuously looking for it
		$cache = wp_cache_get( $key, 'qsot-wc-templates', false, $found );
		if ( ( isset( $found ) && $found ) || ( ! isset( $found ) && false !== $cache ) ) // account for memcached plugin
			return $cache;

		// see if the file is overridden in the theme. if it is, just use that
		$found = locate_template( array( $name ), false, false );
		if ( ! empty( $found ) ) {
			$found = apply_filters( 'qsot-woo-template', $found, $type, $name );
			wp_cache_set( $key, $found, 'qsot-wc-templates', 3600 );
			return $found;
		}

		// otherwise, try to find the appropriate file, by first checking our plugin, then checking WC core
		// find the core WC dir
		$woodir = trailingslashit( WC()->plugin_path() );

		// depending on the type of template we need, choose a specific subdir to check
		switch ( $type ) {
			case 'admin':
				$qsot_path = 'templates/admin/';
				$woo_path = 'includes/admin/';
			break;

			default:
				$qsot_path = 'templates/';
				$woo_path = 'templates/';
			break;
		}

		// construct a cascading list of dirs to check for the appropriate file in
		$dirs = apply_filters( 'qsot-template-dirs', array(
			get_stylesheet_directory() . '/woocommerce/',
			get_template_directory() . '/woocommerce/',
			get_stylesheet_directory() . '/templates/',
			get_template_directory() . '/templates/',
			QSOT::plugin_dir() . $qsot_path,
			$woodir . $woo_path,
		), $qsot_path, $woo_path, 'woocommerce' );

		// force the theme to be the prime override, using our special subpath as a context
		array_unshift( $dirs, get_stylesheet_directory() . '/' . $qsot_path, get_template_directory() . '/' . $qsot_path );

		// cycle through the dir list, until we find the first matching file
		foreach ( $dirs as $dir ) {
			if ( file_exists( ( $file = trailingslashit( $dir ) . $name ) ) ) {
				$found = $file;
				break;
			}
		}

		// allow modification, and then store in cache
		$found = apply_filters( 'qsot-woo-template', $found, $type, $name );
		wp_cache_set( $key, $found, 'qsot-wc-templates', 3600 );

		return $found;
	}

	// include a template, and make specific $args local vars
	public static function include_template( $template, $args=array(), $echo__output=true, $lookup=true ) {
		// get the template from the template filename
		if ( $lookup )
			$template = apply_filters( 'qsot-locate-template', false, (array) $template, false, false );

		// extract args to local vars
		extract( $args );

		// capture the output
		ob_start();
		include $template;
		$out = ob_get_contents();
		ob_end_clean();

		// if we are being asked to print the output then do so now
		if ( $echo__output )
			echo $out;

		return trim( preg_replace( '#>\s+<#s', '> <', $out ) );
	}

	// include a template part
	public static function maybe_include_template( $template_name, $args=array(), $echo__output=false ) {
		// get the template from the template filename
		$template = apply_filters( 'qsot-locate-template', false, array( $template_name ), false, false );

		// extract the vars to use in this template
		extract( $args );

		// if there is no defined template, then bail
		if ( empty( $template ) )
			return '';

		return self::include_template( $template, $args, $echo__output, false );
	}

	// include a WC template part
	public static function maybe_include_wc_template( $template_name, $args=array(), $echo__output=false, $template_type ) {
		// get the template from the template filename
		$template = apply_filters( 'qsot-woo-template', false, array( $template_name ), false, false );

		// extract the vars to use in this template
		extract( $args );

		// if there is no defined template, then bail
		if ( empty( $template ) )
			return '';

		return self::include_template( $template, $args, $echo__output, false );
	}
}

if (defined('ABSPATH') && function_exists('add_action')) {
	QSOT_Templates::pre_init();
}

endif;
