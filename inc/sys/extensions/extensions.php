<?php if ( __FILE__ == $_SERVER['SCRIPT_FILENAME'] ) die( header( 'Location: /') );

// class to maintain a list of plugins that may or may not need updating and may or may not be installed
// this is only needed in the admin or cron, where update queries are processed
class QSOT_Extensions {
	protected static $_instance = null;
	protected static $ns = 'qsot-';

	protected static $server_url = 'http://opentickets.com/';

	protected $all = array();
	protected $known = array();
	protected $installed = array();
	protected $active = array();
	protected $slug_map = null;

	public $known_request_time = null;

	// setup the actions, filters, and basic data for the class
	public static function pre_init() {
	}

	// fetch the server url, so that it is can be used throughout the code
	public static function get_server_url() { return self::$server_url; }

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

		// maybe force a plugin update check
		$this->_maybe_force_plugin_update_check();

		// load the list of all installed plugins on this isntallation
		$this->_load_all_plugins();

		// convert old keys from keychain plugin, to new keys built in to core
		$this->_convert_old_keys();

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

		// after plugins have been installed, update our plugins list
		add_action( 'upgrader_post_install', array( &$this, 'purge_plugins_list' ), 1000 );

		// ad a hook to display the message about conveerted license keys
		add_action( 'admin_notices', array( &$this, 'maybe_converted_key_message' ), 1000 );
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

	// get a list of all the plugins we know exist, from our special repo
	public function get_known() {
		return $this->known;
	}

	// get a list of all the extensions that were installed and have activated licenses on this site
	public function get_activated() {
		$list = array();
		// cycle through the licenses, and aggregate a list of the ones that are installed and active
		foreach ( $this->get_licenses() as $file => $data )
			if ( isset( $data['verification_code'] ) && ! empty( $data['verification_code'] ) )
				$list[] = $file;

		return $list;
	}

	// public method to fetch a list of all the plugins that are installed that we need to handle updates for
	public function get_installed( $only_files=false ) {
		// if we only want the list of installed plugin files, then just return that now instead of running through this whole thing
		if ( $only_files )
			return $this->installed;

		$data = array();
		// construct a list of all intalled plugins and all relevant data
		foreach ( $this->installed as $file ) {
			// skip any situations where there is a plugin in the installed list that is not on the all plugins list
			if ( ! isset( $this->all[ $file ] ) )
				continue;
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

	// get a file based slug map
	public function get_file_slug_map( $force_refresh=false ) {
		$result = array();
		// get the normal slug map
		$map = $this->get_slug_map( $force_refresh );

		// construct the file based slug map
		foreach ( $map as $slug => $data )
			if ( isset( $data['file'] ) )
				$result[ $data['file'] ] = $slug;

		return $result;
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
		$cache = is_string( $cache ) ? @json_decode( @base64_decode( $cache ), true ) : $cache;
		$cache = ! is_array( $cache ) ? array() : $cache;

		// if the cache is empty, and we are not on the extensions page, then do not try to load them
		if ( empty( $cache ) && ( ! isset( $_GET['page'] ) || 'qsot-extensions' != $_GET['page'] ) )
			return $this->known = array();

		// get the timestamp that the current list expires on
		$expires = get_option( self::$ns . 'known-plugins-expires', 0 );

		// if we are not expired yet, then return the list we have stored in cache
		if ( time() < $expires ) {
			$this->known_request_time = get_option( self::$ns . 'known-plugins-timer', null );
			return $this->known = $cache;
		}

		// now, do a new fetch
		$this->_refresh_known_plugins();
	}

	// trigger a new fetch of the knowns plugins list
	protected function _refresh_known_plugins() {
		// if we are expired, then update the list's expiration now (so that we dont have 10000 page requests generating a new fetch; dog pile)
		update_option( self::$ns . 'known-plugins-expires', time() + DAY_IN_SECONDS + ( rand( 0, 2 * HOUR_IN_SECONDS ) - HOUR_IN_SECONDS ) );

		// get the api instance
		$api = QSOT_Extensions_API::instance();

		$existing_known = get_option( self::$ns . 'known-plugins', array() );
		$existing_known = is_string( $existing_known ) ? @json_decode( @base64_decode( $existing_known ), true ) : $existing_known;
		// if we already have a list of known plugins, but it is just expired, then we have a special case that can save everyone some bandwidth
		// we can send a list of our exisitng known plugins, and their associated image hashes. if the hashes have not changed on the sending end, then they will not resend the image again, saving bandwidth
		$image_hashes = $this->_maybe_known_image_hashes( $existing_known );

		// fetch the list of plugins that we know we need to handle stuff for
		$results = $api->get_available( array( 'categories' => array( 'opentickets' ), 'image_hashes' => $image_hashes ) );

		// if the response was an error, then just do nothing further
		if ( is_wp_error( $results ) )
			return;

		// otherwise, update the known plugins list, and it's cache (make sure not to autoload)
		$this->known = $this->_handle_images( $results, $existing_known );
		$this->known_request_time = $api->last_timer;
		update_option( self::$ns . 'known-plugins', @base64_encode( @json_encode( $this->known ) ), 'no' );
		update_option( self::$ns . 'known-plugins-timer', $api->last_timer, 'no' );
	}

	// attempt to load a list of image hashes, indexed by their owner plugins
	protected function _maybe_known_image_hashes( $known ) {
		// if there are no knowns in the list, then bail
		if ( empty( $known ) )
			return array();

		// load the uploads dir info, for use when loading the images to take their hashes
		$u = wp_upload_dir();
		$path = trailingslashit( $u['basedir'] );

		$final = array();
		// create a list of image hashes
		foreach ( $known as $file => $data ) {
			// if there are no images on file, then skip this item
			if ( ! isset( $data['images'] ) || empty( $data['images'] ) )
				continue;

			$list = array();
			// cycle through the images and aggregate any local image md5 hashes
			foreach ( $data['images'] as $key => $images ) {
				switch ( $key ) {
					default:
					case 'icon_image':
					case 'store_image':
						if ( isset( $images['icon_rel_path'] ) && ! empty( $images['icon_rel_path'] ) && @file_exists( $path . $images['icon_rel_path'] ) )
							$list[ md5_file( $path . $images['icon_rel_path'] ) ] = $images['icon_rel_path'];
					break;

					case 'banner_images':
					case 'screenshot_images':
						foreach ( $images as $image_ind => $img )
							if ( isset( $img['icon_rel_path'] ) && ! empty( $img['icon_rel_path'] ) && @file_exists( $path . $img['icon_rel_path'] ) )
								$list[ md5_file( $path . $img['icon_rel_path'] ) ] = $img['icon_rel_path'];
					break;
				}
			}

			// if there were image hashes, then add them for this plugin
			if ( $list )
				$final[ $file ] = $list;
		}

		return $final;
	}

	// after a plugin is installed or updated, we need to refresh our list of all installed plugins
	public function purge_plugins_list() {
		wp_cache_delete( 'plugins', 'plugins' );
		$this->_load_all_plugins();
	}

	// load a list of all installed plugins on the system, and all their relevant information
	protected function _load_all_plugins() {
		// attempt to load this list from our internal cache, stored in a non-autoloaded wp_options key
		$cache = wp_cache_get( 'plugins', 'plugins' );

		// if this cache is not empty, then use it, because it should, in theory, be up to date
		if ( ! empty( $cache ) && is_array( $cache ) )
			return $this->all = $cache;

		// otherwise, generate the list now, because it is needed, and store it in the same cache for later usage
		// load any missing required files
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		// get the list
		$this->all = get_plugins();
	}

	// during update to this latest version of the plugin, check to see if we need to force a plugin update check
	protected function _maybe_force_plugin_update_check() {
		// check if the last check was done while this version was installed
		$last_check = get_option( '_qsot_last_forced_plugin_update_check', '' );
		if ( QSOT::version() === $last_check )
			return;

		// otherwise force a check now
		delete_site_transient( 'update_plugins' );
		update_option( '_qsot_last_forced_plugin_update_check', QSOT::version(), 'yes' );
	}

	// handle the icons passed by the api response. we should save copied of them in our uploads dir
	protected function _handle_images( $data, $existing_known ) {
		// for each response item, handle the icon updating
		foreach ( $data as $ind => $item ) {
			// if the iamges block is not set, then skip this item
			if ( ! isset( $item['images'] ) )
				continue;

			// check each image key for changes
			foreach ( $item['images'] as $key => $value ) {
				// do something different depending on the key
				switch ( $key ) {
					default:
					case 'icon_image':
					case 'store_image':
						// determine if the server says that the image changed
						$changed = ! isset( $value['icon_no_change'] ) || ! $value['icon_no_change'];

						// if it did not change, then just use the old value we have on file
						if ( ! $changed && isset( $existing_known[ $ind ], $existing_known[ $ind ]['images'], $existing_known[ $ind ]['images'][ $key ] ) ) {
							$item['images'][ $key ] = array();
							foreach ( array( 'icon_rel_path', 'icon_abs_path' ) as $sub_key )
								if ( isset( $existing_known[ $ind ]['images'][ $key ][ $sub_key ] ) )
									$item['images'][ $key ][ $sub_key ] = $existing_known[ $ind ]['images'][ $key ][ $sub_key ];
							continue;
						}

						// remove the icon data from the item
						$icon = isset( $value['icon'] ) ? $value['icon'] : '';
						$icon_url = isset( $value['icon_url'] ) ? $value['icon_url'] : '';
						unset( $value['icon'] );
						$value['icon_abs_path'] = $value['icon_rel_path'] = '';

						// maybe update the icon
						$path = $this->_maybe_update_icon( $ind, $icon, $icon_url, $key );
						if ( is_wp_error( $path ) )
							$value['image_path_error'] = $this->_error_to_array( $path );
						else
							$value = array_merge( $value, $path );

						// update the item iamge
						$item['images'][ $key ] = $value;
					break;

					case 'banner_images':
					case 'screenshot_images':
						foreach ( $value as $image_ind => $img ) {
							// determine if the server says that the image changed
							$changed = ! isset( $img['icon_no_change'] ) || ! $img['icon_no_change'];

							// if it did not change, then just use the old value we have on file
							if ( ! $changed && isset( $existing_known[ $ind ], $existing_known[ $ind ]['images'], $existing_known[ $ind ]['images'][ $key ], $existing_known[ $ind ]['images'][ $key ][ $image_ind ] ) ) {
								foreach ( array( 'icon_rel_path', 'icon_abs_path' ) as $sub_key )
									if ( isset( $existing_known[ $ind ]['images'][ $key ][ $image_ind ][ $sub_key ] ) )
										$value[ $image_ind ] = $existing_known[ $ind ]['images'][ $key ][ $image_ind ][ $sub_key ];
								continue;
							}

							// remove the icon data from the item
							$icon = isset( $img['icon'] ) ? $img['icon'] : '';
							$icon_url = isset( $img['icon_url'] ) ? $img['icon_url'] : '';
							unset( $img['icon'] );
							$img['icon_abs_path'] = $img['icon_rel_path'] = '';

							// maybe update the icon
							$path = $this->_maybe_update_icon( $ind, $icon, $icon_url, $key . ':' . $image_ind );
							if ( is_wp_error( $path ) )
								$img['image_path_error'] = $this->_error_to_array( $path );
							else
								$img = array_merge( $img, $path );

							// update the specific image
							$value[ $image_ind ] = $img;
						}

						// update the item iamge
						$item['images'][ $key ] = $value;
					break;
				}
			}

			// update the item with the aggregated image info
			$data[ $ind ] = $item;
		}

		return $data;
	}

	// during various page loads, we may need to fetch a remote image, and store it locally. this function will handle that
	public function update_image_from_remote( $plugin_file, $image_key, $image_ind=0 ) {
		// if the plugin file supplied is not known, then bail
		if ( ! isset( $this->known[ $plugin_file ] ) )
			return '';

		// if the image key is not known for that file, or has no remote url, then bail
		if ( ! isset( $this->known[ $plugin_file ]['images'][ $image_key ], $this->known[ $plugin_file ]['images'][ $image_key ]['icon_abs_path'] ) )
			return '';

		// if there is no target path, then bail
		if ( ! isset( $this->known[ $plugin_file ]['images'][ $image_key ]['target'] ) )
			return '';
		$target = $this->known[ $plugin_file ]['images'][ $image_key ]['target'];

		// get the remote image
		$response = QSOT_Extensions_API::instance()->get_remote_image( $this->known[ $plugin_file ]['images'][ $image_key ]['icon_abs_path'] );

		// if the response is a wp_error or empty, then pass it through
		if ( is_wp_error( $response ) || empty( $response ) )
			return $response;

		// otherwise, try to update the file appropriately. start by writing a temp file with the returned contents
		file_put_contents( $target['path'], $response );

		// get the image information from the new new file we created
		$image_data = @getimagesize( $target['path'] );

		// if the file was not an image, or we could not get a full reading on it's properties, then bail
		if ( ! is_array( $image_data ) || ! isset( $image_data[2] ) || ! is_numeric( $image_data[2] ) ) {
			@unlink( $target['path'] );
			return new WP_Error( 'invalid_file_data', __( 'The received icon file was not an image we could parse.', 'opentickets-community-edition' ) );
		}

		// attempt to figure out the extension
		$extension = @image_type_to_extension( $image_data[2], false );

		// if that failed, clean up and bail
		if ( ! $extension ) {
			@unlink( $target['path'] );
			return new WP_Error( 'invalid_file_extension', __( 'Could not determine the file extension of the supplied icon image.', 'opentickets-community-edition' ) );;
		}

		// if all is well, rename the file to use the appropriate extension
		rename( $target['path'], $target['path'] . '.' . $extension );

		// update the known plugins list
		$this->known[ $plugin_file ]['images'][ $image_key ] = array( 'icon_rel_path' => $target['url'] . '.' . $extension );
		update_option( self::$ns . 'known-plugins', @base64_encode( @json_encode( $this->known ) ), 'no' );

		// get the base plugins dir, so that we can return a full local url for the new file
		$u = wp_upload_dir();
		$url = trailingslashit( $u['baseurl'] );

		return $url . $this->known[ $plugin_file ]['images'][ $image_key ]['icon_rel_path'];
	}

	// we may need to update or create the icon for this item. do so here
	protected function _maybe_update_icon( $plugin_file, $icon, $icon_url, $icon_key ) {
		// first, find the appropriate dir to store the icon in
		$icon_dir = $this->_icon_dir();

		// if the result was an error, then pass it through for storage
		if ( is_wp_error( $icon_dir ) )
			return $icon_dir;

		// if we failed to get the icon dir, just silently bail (needs an error message display at some point)
		if ( ! is_array( $icon_dir ) || ! isset( $icon_dir['absolute'], $icon_dir['relative'] ) )
			return '';

		// figure out the base, non-extensioned name of the target file for the icon image
		$base = md5( AUTH_SALT . $plugin_file. $icon_key );

		// if only the icon url was supplied, then we should be creating a cached version of this image later, not now. gather some basic information, and store it for later use
		if ( empty( $icon ) && ! empty( $icon_url ) ) {
			return array(
				'icon_abs_path' => $icon_url,
				'target' => array(
					'path' => $icon_dir['absolute'] . $base,
					'url' => $icon_dir['relative'] . $base,
				),
			);
		}

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

		return array( 'icon_rel_path' => $icon_dir['relative'] . $base . '.' . $extension );
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

	// maybe convert the old license keys to the new license keys
	protected function _convert_old_keys() {
		// check if they have already been converted
		$already_converted = get_option( self::$ns . 'converted-old-keys', 0 );
		if ( $already_converted )
			return;

		// mark the keys as converted now, so that the duplication risk of this task is as little as possible
		update_option( self::$ns . 'converted-old-keys', 1 );

		// load the old keys
		$keys = get_option( '_qsot_keys_' . md5( site_url() ), array() );

		// if there are no keys to convert, then do nothing
		if ( empty( $keys ) )
			return;

		$at_least_one = false;
		// otherwise cycle through the keys, and store the ones that dont have new keys yet
		foreach ( $keys as $file => $data ) {
			// if the plugin name is invalid, then skip it
			if ( '*' == $file || count( explode( '/', $file ) ) != 2 )
				continue;

			// see if this plugin already has a key stored that is valid
			$current = get_option( self::$ns . 'licenses-' . md5( $file ), array() );

			// if it is stored, and valid, then skip this old key
			if ( ! empty( $current ) && is_array( $current ) && isset( $current['verification_code'] ) && ! empty( $current['verification_code'] ) )
				continue;

			// otherwise, construct the new data array, with the information we know
			$arr = array(
				'license' => $data['key'],
				'email' => $data['key-email'],
				'base_file' => $file,
				'version' => isset( $this->all[ $file ] ) ? $this->all[ $file ]['Version'] : '',
				'verification_code' => '',
				'expires' => '',
			);

			// update the stored license information for this file with the newly aggregated data
			update_option( self::$ns . 'licenses-' . md5( $file ), $arr );
			$at_least_one = true;
		}

		// if there was at least one restored license key, then add a message to pop
		if ( $at_least_one )
			update_option( self::$ns . 'converted-msg', 1 );
	}

	// if there is a converted key message, display it now
	public function maybe_converted_key_message() {
		// if the message should not be displayed, then bail
		if ( ! get_option( self::$ns . 'converted-msg', 0 ) )
			return;

		// if they do not have any known plugins installed, then bail
		if ( empty( $this->installed ) ) {
			update_option( self::$ns . 'converted-msg', 1 );
			return;
		}

		// url of the licenses page
		$url = add_query_arg(
			array( 'tab' => 'licenses' ),
			apply_filters( 'qsot-get-menu-page-uri', admin_url( '/' ), 'settings', true )
		);
		?>
			<div class="error">
				<p><?php echo sprintf(
					__( 'The way that %sOpenTickets Community Edition%s handles license keys has changed. Previously a tool called %sOpenTickets - Keychain%s was required. This tool has become obsolete. We have automatically copied over the licenses you previously added to that tool; however, they do require your validation. Please visit the %sLicenses Settings Page%s, verify that your license keys are correct, and "Save Changes" to validate them.', 'opentickets-community-edition' ),
					'<strong><em>',
					'</em></strong>',
					'<em>',
					'</em>',
					sprintf( '<a href="%s" title="%s">', $url, __( 'Visit the Licenses Page', 'opentickets-community-edition' ) ),
					'</a>'
				) ?></p>
			</div>
		<?php
	}
}

// security
if ( defined( 'ABSPATH' ) && function_exists( 'add_action' ) )
	QSOT_Extensions::pre_init();
