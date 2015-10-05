<?php (__FILE__ == $_SERVER['SCRIPT_FILENAME']) ? die(header('Location: /')) : null;

// class to maintain a list of plugins that may or may not need updating and may or may not be installed
// this is only needed in the admin or cron, where update queries are processed
class QSOT_Extensions {
	protected static $_instance = null;
	protected static $ns = 'qsot-';

	protected $all = array();
	protected $known = array();
	protected $installed = array();
	protected $active = array();
	protected $slug_map = null;

	// setup the actions, filters, and basic data for the class
	public static function pre_init() {
	}

	// setup the singleton for this lass
	public static function instance() {
		// figure out the current class
		$class = __CLASS__;

		// if we already have an instance, use that
		if ( isset( self::$_instance ) && self::$_instance instanceof $class )
			return self::$_instance;

		// otherwise create one and return it
		return self::$_instance = new QSOT_Extensions();
	}

	// constructor for the object. sets up the defaults and such
	public function __construct() {
		// figure out the current class
		$class = __CLASS__;

		// only one instance of this class can be active at a time
		if ( isset( self::$_instance ) && self::$_instance instanceof $class )
			throw new Exception( __( 'Only one instance of the OpenTickets Extensions object is allowed at a time.', 'opentickets-community-edition' ) );

		// update the instance
		self::$_instance = $this;

		$this->reset();

		// load the list of all installed plugins on this isntallation
		$this->_load_all_plugins();

		// load the list of all installed plugins that we need to do something for.
		// this list is obtained once a week +/- 1 day, or if 1) we have never obtained it before and we are checking for updates, or 2) we are being asked to force re-check
		$this->_load_known_plugins();

		// figure out which of our known plugins are installed
		$this->_load_installed_and_active();

		// setup the actions and filters that use this object
		$this->_setup_actions_and_filters();
	}

	// setup the actions and filters we use
	protected function _setup_actions_and_filters() {
		// add a filter that loads the licenses settings page, if we have any extensions installed that we need to worry about
		add_filter( 'qsot_get_settings_pages', array( &$this, 'maybe_load_licenses_page' ), 10000, 1 );
	}

	// get the list of licenses. this contains the base_file, license, email, verification_hash, version, and expiration of each registered license
	public function get_licenses() {
		$email = get_bloginfo( 'admin_email' );
		$licenses = array();
		// get the license information for each installed plugin we care about
		foreach ( $this->installed as $file ) {
			$licenses[ $file ] = wp_parse_args( get_option( self::$ns . 'licenses-' . md5( $file ), array() ), array(
				'license' => '',
				'email' => $email,
				'base_file' => '',
				'version' => '',
				'verification_code' => '',
				'expires' => '',
			) );
		}

		return $licenses;
	}

	// save the licenses that are supplied to the function
	public function save_licenses( $licenses ) {
		// cycle through the list of license information, and save each item in the list
		foreach ( $licenses as $file => $license )
			update_option( self::$ns . 'licenses-' . md5( $file ), $license, 'no' );
	}

	// public method to fetch a list of all the plugins that are installed that we need to handle updates for
	public function get_installed() {
		$data = array();
		// construct a list of all intalled plugins and all relevant data
		foreach ( $this->installed as $file ) {
			$data[ $file ] = $this->all[ $file ];
			$data[ $file ]['_known'] = $this->known[ $file ];
		}

		return $data;
	}

	// get the slug map, mapping the plugin slug to the plugin file. used primarily during the 'plugin_information' flow
	public function get_slug_map( $force_refresh=false ) {
		// if this is not a force refresh, and we have a cache already, then use that
		if ( ! $force_refresh && null !== $this->slug_map )
			return $this->slug_map;

		$this->slug_map = array();
		// otherwise, lookup the last plugin update cache, and create the slug map from that
		$last_update = get_site_transient( 'update_plugins' );

		// construct a list by cycling through the last response and buiding it based on that information
		if ( is_object( $last_update ) ) {
			if ( isset( $last_update->response ) && is_array( $last_update->response ) )
				foreach ( $last_update->response as $file => $data )
					if ( isset( $data->slug ) )
						$this->slug_map[ $data->slug ] = array( 'file' => $file, 'version' => $data->new_version, 'link' => $data->package );

			if ( isset( $last_update->no_update ) && is_array( $last_update->no_update ) )
				foreach ( $last_update->no_update as $file => $data )
					if ( isset( $data->slug ) )
						$this->slug_map[ $data->slug ] = array( 'file' => $file, 'version' => $data->new_version, 'link' => $data->package );
		}

		return $this->slug_map;
	}

	// if we have any known plugins installed (even if not active), we should have the license page visible, so that licenses can be added
	public function maybe_load_licenses_page( $pages ) {
		// if there are no installed plugins, thne bail
		if ( empty( $this->installed ) )
			return $pages;

		// otherwise, add our licenses page
		$pages['licenses'] = include_once( QSOT::plugin_dir() . 'inc/sys/extensions/settings/licenses.php' ); 

		return $pages;
	}

	// reset all internal data
	public function reset() {
		// reset all internal containers
		$this->all = $this->known = $this->installed = $this->active = array();
	}

	// public method to trigger a new fetch of the known plugins, and a recalc of installed an active plugins we need to be concerned with
	public function force_refresh_known_plugins() {
		$this->_refresh_known_plugins();
		$this->_load_installed_and_active();
	}

	// figure out which of the installed plugins are plugins that we need to manually check for updates on (or that need to show as already installed on the marketplace)
	public function _load_installed_and_active() {
		// cycle through the "known" plugins, and check if those plugins are already installed on this system
		foreach ( $this->known as $file => $data ) {
			// if that plugin is installed, add it to our installed list
			if ( isset( $this->all[ $file ] ) )
				$this->installed[] = $file;
		}

		// from the installed list, and the 'active_plugins' list, determine which of our known plugins are currently active
		$active_plugins = $this->_get_all_active_plugins();
		$this->active = array_intersect( $active_plugins, $this->installed );
	}

	// obtain a list of all active plugins, by combining several lists together
	protected function _get_all_active_plugins() {
		// load the base active_plugins list, because we know this one has stuff in it
		$active = get_option( 'active_plugins', array() );

		// next, load the sitewide network plugins
		$network = defined( 'MULTISITE' ) && MULTISITE ? get_site_option( 'active_sitewide_plugins' ) : array();

		// normalize the lists
		$active = is_array( $active ) ? $active : array();
		$network = is_array( $network ) ? $network : array();

		// merge th lists
		$active = array_merge( array_keys( $network ), $active );

		return $active;
	}

	// load the list of plugins we know we need to take action on
	protected function _load_known_plugins() {
		// get the current cached list of known plugins
		$cache = get_option( self::$ns . 'known-plugins', array() );

		// get the timestamp that the current list expires on
		$expires = get_option( self::$ns . 'known-plugins-expires', 0 );

		// if we are not expired yet, then return the list we have stored in cache
		if ( time() < $expires )
			return $this->known = $cache;

		// now, do a new fetch
		$this->_refresh_known_plugins();
	}

	// trigger a new fetch of the knowns plugins list
	protected function _refresh_known_plugins() {
		// if we are expired, then update the list's expiration now (so that we dont have 10000 page requests generating a new fetch; dog pile)
		update_option( self::$ns . 'known-plugins-expires', time() + WEEK_IN_SECONDS + ( rand( 0, 2 * DAY_IN_SECONDS ) - DAY_IN_SECONDS ) );

		// get the api instance
		$api = QSOT_Extensions_API::instance();

		// fetch the list of plugins that we know we need to handle stuff for
		$results = $api->get_available( array( 'categories' => array( 'opentickets' ) ) );

		// if the response was an error, then just do nothing further
		if ( is_wp_error( $results ) )
			return;

		// otherwise, update the known plugins list, and it's cache (make sure not to autoload)
		$this->known = $this->_handle_icons( $results );
		update_option( self::$ns . 'known-plugins', $this->known, 'no' );
	}

	// load a list of all installed plugins on the system, and all their relevant information
	protected function _load_all_plugins() {
		// attempt to load this list from our internal cache, stored in a non-autoloaded wp_options key
		$cache = get_option( self::$ns . 'installed-plugins', '' );

		// if this cache is not empty, then use it, because it should, in theory, be up to date
		if ( ! empty( $cache ) && is_array( $cache ) )
			return $this->all = $cache;

		// otherwise, generate the list now, because it is needed, and store it in the same cache for later usage
		// load any missing required files
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		// get the list
		$this->all = get_plugins();

		// save it to cache, and make sure to NOT auto load this, because it could be large, and force even more of a bottleneck on the wp_options table, than other plugins already do
		update_option( self::$ns . 'installed-plugins', $this->all, 'no' );
	}

	// handle the icons passed by the api response. we should save copied of them in our uploads dir
	protected function _handle_icons( $data ) {
		// for each response item, handle the icon updating
		foreach ( $data as $ind => $item ) {
			// remove the icon data from the item
			$icon = $item['icon'];
			unset( $item['icon'] );
			$item['icon_rel_path'] = '';

			// maybe update the icon
			$path = $this->_maybe_update_icon( $ind, $icon );
			if ( is_wp_error( $path ) )
				$item['image_path_error'] = $this->_error_to_array( $path );
			else
				$item['icon_rel_path'] = $path;

			$data[ $ind ] = $item;
		}

		return $data;
	}

	// we may need to update or create the icon for this item. do so here
	protected function _maybe_update_icon( $plugin_file, $icon ) {
		// first, find the appropriate dir to store the icon in
		$icon_dir = $this->_icon_dir();

		// if the result was an error, then pass it through for storage
		if ( is_wp_error( $icon_dir ) )
			return $icon_dir;

		// if we failed to get the icon dir, just silently bail (needs an error message display at some point)
		if ( ! is_array( $icon_dir ) || ! isset( $icon_dir['absolute'], $icon_dir['relative'] ) )
			return '';

		// figure out the base, non-extensioned name of the target file for the icon image
		$base = md5( AUTH_SALT . $plugin_file );

		// next, write the file to a temp location, pending an appropriate extension
		file_put_contents( $icon_dir['absolute'] . $base, @base64_decode( $icon ) );

		// figure out the appropriate extension of the file. first, find the mime type
		// start by getting the image information
		$image_data = @getimagesize( $icon_dir['absolute'] . $base );

		// if that image information lookup failed, or does not have the needed values, clean up and bail
		if ( ! is_array( $image_data ) || ! isset( $image_data[2] ) || ! is_numeric( $image_data[2] ) ) {
			@unlink( $icon_dir['absolute'] . $base );
			return new WP_Error( 'invalid_file_data', __( 'The received icon file was not an image we could parse.', 'opentickets-community-edition' ) );
		}

		// attempt to figure out the extension
		$extension = @image_type_to_extension( $image_data[2], false );

		// if that failed, clean up and bail
		if ( ! $extension ) {
			@unlink( $icon_dir['absolute'] . $base );
			return new WP_Error( 'invalid_file_extension', __( 'Could not determine the file extension of the supplied icon image.', 'opentickets-community-edition' ) );;
		}

		// if all is well, rename the file to use the appropriate extension
		rename( $icon_dir['absolute'] . $base, $icon_dir['absolute'] . $base . '.' . $extension );

		return $icon_dir['relative'] . $base . '.' . $extension;
	}

	// figure out the appropriate icon dir on this syste
	protected function _icon_dir() {
		static $icon_dirs = array();
		$blog_id = get_current_blog_id();
		// if we already have this dir cached, then use the cache
		if ( isset( $icon_dirs[ $blog_id ] ) )
			return $icon_dirs[ $blog_id ];

		// otherwise, figure out what the appropriate dir is, and create it if necessary
		$u = wp_upload_dir();
		$relative_path = self::$ns . 'extention-icons/';
		$target_path = trailingslashit( $u['basedir'] ) . $relative_path;

		// if it does not exist, create it
		if ( ! file_exists( $target_path ) )
			if ( ! mkdir( $target_path ) )
				return new WP_Error( 'missing_path', __( 'Could not create the icon cache dir.', 'opentickets-community-edition' ) );

		// if the path still does not exist, then bail
		if ( ! file_exists( $target_path ) || ! is_writable( $target_path ) )
			return new WP_Error( 'path_permissions', __( 'THe icon cache dir is missing or could not be written to.', 'opentickets-community-edition' ) );

		return $icon_dirs[ $blog_id ] = array(
			'absolute' => $target_path,
			'relative' => $relative_path,
		);
	}

	// convert a WP_Error to an array, so that it can be more clearly stored in the wp_options table if needed
	protected function _error_to_array( $error ) {
		$arr = array();
		// cycle through the error codes, and store each list of messages
		foreach ( $error->get_error_codes() as $code ) {
			$arr[ $code ] = array();
			foreach ( $error->get_error_messages( $code ) as $msg ) {
				$arr[ $code ][] = $msg;
			}
		}

		return $arr;
	}
}

// security
if ( defined( 'ABSPATH' ) && function_exists( 'add_action' ) )
	QSOT_Extensions::pre_init();
