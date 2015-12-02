<?php if ( __FILE__ == $_SERVER['SCRIPT_FILENAME'] ) die( header( 'Location: /') );

// handles the update checker and updating process for all our extensions
class QSOT_Extensions_Updater {
	protected static $ns = 'qsot-';
	// the core wordpress updater url. this is the url that we sniff for on the http_response filter, to trigger another request to our server
	protected static $core_wp_update_api_urls = array(
		'http://api.wordpress.org/plugins/update-check/1.1/',
		'https://api.wordpress.org/plugins/update-check/1.1/',
	);

	// setup the actions, filters, and basic data needed for this class
	public static function pre_init() {
		// run our update checker during the initial stages of the wp.org checker
		add_filter( 'pre_http_request', array( __CLASS__, 'check_for_updates' ), 10000, 3 );

		// infuse our last extension update check response into the list of plugin updates for the site
		//add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'add_extension_updates_to_plugin_updates' ), 10000, 1 );
		add_filter( 'site_transient_update_plugins', array( __CLASS__, 'add_extension_updates_to_plugin_updates' ), 10000, 1 );

		// add a filter to load known plugin information data from the db instead of hitting some api
		add_filter( 'plugins_api', array( __CLASS__, 'known_plugin_information' ), 1000, 3 );
		
		// add filter to help when testing with a local server
		add_filter( 'http_request_host_is_external', array( __CLASS__, 'local_server' ), 10, 3 );
	}

	// overcome core core wp rule that domains that resolve to 127.0.0.1 are not allowed
	public static function local_server( $allowed, $host, $url ) {
		// figure out the server url domain
		$server = QSOT_Extensions::get_server_url();
		$server = @parse_url( $server );

		// if the host is teh same as the server host, then allow it
		return $allowed || ( isset( $server['host'] ) && $server['host'] == $host );
	}

	// intercepts the core wp.org update requests. during the initial stages of that request, we perform our own request.
	// we need to do it this way, because the user could be hard blocked from wp.org, which would cause our old check to never run
	public static function check_for_updates( $response, $request, $url ) {
		// bail first if this is not an updater url
		if ( ! in_array( $url, self::$core_wp_update_api_urls ) )
			return $response;

		// maybe trigger our additional updater logic
		$extra = self::_maybe_extension_updates();

		// if there are no extra updates to add to the list, or if there are any errors, then bail right here
		if ( empty( $extra ) || ! is_array( $extra ) || ( isset( $extra['errors'] ) && ! empty( $extra['errors'] ) ) )
			return $response;

		// store the result for later use
		update_option( self::$ns . 'updater-response', @base64_encode( @json_encode( $extra ) ), 'no' );

		return $response;
	}

	// uring the process of setting the plugin updates transient, we need to infuse our latest QSOT extension updates into the list
	public static function add_extension_updates_to_plugin_updates( $updates_list ) {
		// get our stored extension updates
		$ext_updates = get_option( self::$ns . 'updater-response', array() );
		$ext_updates = is_string( $ext_updates ) ? @json_decode( @base64_decode( $ext_updates ), true ) : $ext_updates;

		// if there are no extension updates to add, then bail
		if ( ! is_array( $ext_updates ) || ! isset( $ext_updates['plugins'] ) )
			return $updates_list;

		// merge the results
		foreach ( array( 'plugins' => 'response', 'no_update' => 'no_update', 'translations' => 'translations' ) as $from => $to )
			foreach ( $ext_updates[ $from ] as $slug => $data )
				$updates_list->{ $to }[ $slug ] = (object) $data;

		return $updates_list;
	}

	// load the 'plugin information' about our known plugins from the database instead of an api
	public static function known_plugin_information( $result, $action, $args ) {
		// if the slug is not supplied, then skip this, since we have no idea what plugin the request is for without a slug
		if ( ! is_object( $args ) || ! isset( $args->slug ) )
			return $result;

		// get a list of our installed plugins that we need to handle here
		$installed = QSOT_Extensions::instance()->get_installed();

		// get the slug map, since we are given a slug here, but can only lookup the needed data by filename
		$map = QSOT_Extensions::instance()->get_slug_map();

		// if the requested plugin is not in our list of plugins to manage (in the slug map really), then bail now
		if ( ! isset( $map[ $args->slug ] ) )
			return $result;

		$item = $map[ $args->slug ];
		$file = $item['file'];
		// load the list of installed plugins, and double verify that the file information is present. if not, bail
		$installed = QSOT_Extensions::instance()->get_installed();
		if ( ! isset( $installed[ $file ] ) )
			return $result;

		// normalize the plugin data from installed list
		$plugin = wp_parse_args( $installed[ $file ], array(
			'Name' => '',
			'Author' => '',
			'AuthorURI' => '',
			'PluginURI' => '',
		) );

		// otherwise, aggregate the needed information for the basic response
		$result = array(
			'name' => $plugin['Name'],
			'slug' => $args->slug,
			'version' => $item['version'],
			'author' => ! empty( $plugin['AuthorURI'] ) ? sprintf( '<a href="%s">%s</a>', $plugin['AuthorURI'], $plugin['Author'] ) : $plugin['Author'],
			'author_profile' => $plugin['AuthorURI'],
			'contributors' => array(
				$plugin['Author'] => $plugin['AuthorURI'],
			),
			'requires' => '',
			'tested' => '',
			'compatibility' => array(),
			'rating' => 100,
			'num_ratings'=> 100,
			/*
			'ratings' => array(
				5 => 0,
				4 => 0,
				3 => 0,
				2 => 0,
				1 => 0,
			),
			*/
			'active_installs' => 100,
			'last_updated' => '',
			'added' => '',
			'homepage' => $plugin['PluginURI'],
			'sections' => array(),
			'download_link' => $item['link'],
			'tags' => array(),
			'donate_link' => '',
			'banners' => array()
		);

		// maybe update some of the fields if the information has become available from the server
		$maybe_update_fields = array(
			'rating', // the average rating
			'ratings', // the rating system
			'num_ratings', // the total number of ratings we have
			'active_installs', // the tally of number of active installs we have
			'compatibility', // list of compatibility voting results
			'last_updated', // the last date the plugin was updated
			'added', // when the plugin was added to the list of available plugins
			'banners', // list of banner images used in the plugin description
		);

		// list of readme headers to update if they exist
		$maybe_update_readme_fields = array(
			'requires', // minimum WP version required
			'tested', // max version the plugin has been tested to
			'donate_link', // donation link
		);

		// update any fields that are present from the information from the server
		foreach ( $maybe_update_fields as $field )
			if ( isset( $plugin['_known'][ $field ] ) )
				$result[ $field ] = $plugin['_known'][ $field ];

		// if there are any banner images defined, use them
		if ( isset( $plugin['_known']['images'], $plugin['_known']['images']['banner_images'] ) && ! empty( $plugin['_known']['images']['banner_images'] ) ) {
			// get the first banner image defined
			$img = current( $plugin['_known']['images']['banner_images'] );

			// if there actually is a relative path to use here, then continue
			if ( isset( $img['icon_rel_path'] ) && ! empty( $img['icon_rel_path'] ) ) {
				// figure out the uploads dir
				$u = wp_upload_dir();

				// use that banner image for both low and high res, at least until we have a better way of doing this
				$result['banners']['high'] = $result['banners']['low'] = trailingslashit( $u['baseurl'] ) . $img['icon_rel_path'];
			}
		}

		// add the various parts of the readme file, if they are present
		if ( isset( $plugin['_known']['readme'] ) && ! empty( $plugin['_known']['readme'] ) ) {
			$readme = $plugin['_known']['readme'];

			// if the 'sections' are set, then update them
			if ( isset( $readme['sections'] ) && ! empty( $readme['sections'] ) )
				$result['sections'] = $readme['sections'];
			unset( $result['sections']['upgrade-notice'] );

			// if the 'headers' are set, then cycle through them and pick out the ones we can use
			if ( isset( $readme['headers'] ) && ! empty( $readme['headers'] ) )
				foreach ( $maybe_update_readme_fields as $field )
					if ( isset( $readme['headers'][ $field ] ) && ! empty( $readme['headers'][ $field ] ) )
						$result[ $field ] = $readme['headers'][ $field ];

			// update the tags if they are present
			if ( isset( $readme['headers']['tags'] ) && ( $tags = array_filter( array_map( 'trim', $readme['headers']['tags'] ) ) ) )
				$result['tags'] = $tags;

			// update the contributors if present
			if ( isset( $readme['headers']['contributors'] ) && is_array( $readme['headers']['contributors'] ) && ! empty( $readme['headers']['contributors'] ) )
				foreach ( $readme['headers']['contributors'] as $contributor )
					$result['contributors'][ $contributor ] = sprintf( 'https://profiles.wordpress.org/%s', $contributor );
		}

		return (object)$result;
	}

	// maybe trigger our manual extensions updates, if we are being forced to or if the timer has expired
	protected static function _maybe_extension_updates() {
		// figure out the expiration of our last fetch, and if this is a force request
		$expires = get_option( 'qsot-extensions-updater-last-expires', 0 );
		$is_force = isset( $_GET['force-check'] ) && 1 == $_GET['force-check'];

		// if the last fetch is not expired, and this is not a force request, then bail
		if ( ! $is_force && time() < $expires )
			return array();

		// otherwise, run our update fetch request
		// first, aggregate a list of plugin data we need to check for updates on
		$plugins = array();
		$raw_plugins = QSOT_Extensions::instance()->get_installed();
		$licenses = QSOT_Extensions::instance()->get_licenses();
		if ( is_array( $raw_plugins ) && count( $raw_plugins ) ) foreach( $raw_plugins as $file => $plugin ) {
			$plugins[ $file ] = array(
				'file' => $file,
				'version' => $plugin['Version'],
			);
			if ( isset( $licenses[ $file ], $licenses[ $file ]['license'], $licenses[ $file ]['verification_code'] ) && ! empty( $licenses[ $file ]['license'] ) && ! empty( $licenses[ $file ]['verification_code'] ) ) {
				$plugins[ $file ]['license'] = $licenses[ $file ]['license'];
				$plugins[ $file ]['verification_code'] = $licenses[ $file ]['verification_code'];
			}
		}

		// if there are no plugins to get updates on, bail
		if ( empty( $plugins ) )
			return array();

		// then get the api object
		$api = QSOT_Extensions_API::instance();

		// then fetch the updates
		$extra = $api->get_updates( array( 'plugins' => $plugins ) );

		// infuse our known data into any response package urls, so that when the updater runs, it hits the proper url that contains all the info the server needs for verification
		if ( is_array( $extra ) ) {
			// figure out the current site url
			$su = @parse_url( site_url() );

			// structure the request data
			$domain = isset( $su['host'] ) ? $su['host'] : '';

			// run through each returned group: plugins, themes, translation, and no_update (currently)
			foreach ( $extra as $type => $list ) {
				// run through each item in the group
				foreach ( $list as $file => $data ) {
					// if there is a package url, then update the url by replacing our clientside known data, which is needed by the server when the url is actually hit for an update request
					if ( ! empty( $data['package'] ) ) {
						// get the plugin data we have from our request
						$plugin = isset( $plugins[ $file ] ) ? $plugins[ $file ] : array();

						// if we have plugin data, then use it
						if ( ! empty( $plugin ) ) {
							$replacements = array(
								'{KEY}' => isset( $plugin['license'] ) ? rawurlencode( $plugin['license'] ) : '',
								'{HASH}' => isset( $plugin['verification_code'] ) ? $plugin['verification_code'] : '',
								'{DOMAIN}' => $domain,
								'{FILE}' => $file,
							);
							$data['package'] = str_replace( array_keys( $replacements ), array_values( $replacements ), $data['package'] );

							$list[ $file ] = $data;
						}
					}
				}

				// update the group with any changes
				$extra[ $type ] = $list;
			}
		}

		return $extra;
	}
}

// security
if ( defined( 'ABSPATH' ) && function_exists( 'add_action' ) )
	QSOT_Extensions_Updater::pre_init();
