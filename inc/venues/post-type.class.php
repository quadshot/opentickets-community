<?php if ( __FILE__ == $_SERVER['SCRIPT_FILENAME'] ) die( header( 'Location: /') );

/* Handles the creation of the venues post type, and the basic management page interface and settings */
class qsot_venue_post_type {
	// holder for event plugin options
	protected static $o = null;

	// holder for plugin settings
	protected static $options = null;

	public static function pre_init() {
		// first thing, load all the options, and share them with all other parts of the plugin
		$settings_class_name = apply_filters('qsot-settings-class-name', '');
		if (!class_exists($settings_class_name)) return false;
		self::$o = call_user_func_array(array($settings_class_name, "instance"), array());

		self::$o->venue = apply_filters('qsot-venue-options', array(
			'post_type' => 'qsot-venue',
			'rewrite_slug' => 'venue',
			'meta_key' => array(
				'info' => '_venue_information',
				'social' => '_venue_social_information',
			),
			'defaults' => array(
				'info' => array(
					'address1' => '',
					'address2' => '',
					'city' => '',
					'state' => '',
					'postal_code' => '',
					'country' => '',
					'logo_image_id' => '',
					'notes' => '',
					'instructions' => '',
				),
				'social' => array(
					'phone' => '',
					'website' => '',
					'facebook' => '',
					'twitter' => '',
					'contact_email' => '',
				),
			),
		));

		$mk = self::$o->meta_key;
		self::$o->meta_key = array_merge(is_array($mk) ? $mk : array(), array(
			'venue' => '_venue_id',
		));

		// load all the options, and share them with all other parts of the plugin
		$options_class_name = apply_filters('qsot-options-class-name', '');
		if (!empty($options_class_name)) {
			self::$options = call_user_func_array(array($options_class_name, "instance"), array());
			self::_setup_admin_options();
		}

		// load different assets depending on the page that we are on in the admin
		add_action( 'qsot-admin-load-assets-' . self::$o->core_post_type, array( __CLASS__, 'load_event_venue_assets' ), 10, 2 );
		add_action( 'qsot-admin-load-assets-' . self::$o->{'venue.post_type'}, array( __CLASS__, 'load_venue_admin_assets' ), 10, 2 );

		add_filter('qsot-upcoming-events-query', array(__CLASS__, 'events_query_only_this_venue'), 10, 2);
		add_filter('qsot-events-core-post-types', array(__CLASS__, 'register_post_type'), 3, 1);
		add_action('qsot-events-bulk-edit-settings', array(__CLASS__, 'venue_bulk_edit_settings'), 20, 3);
		add_filter('qsot-events-save-sub-event-settings', array(__CLASS__, 'save_sub_event_settings'), 10, 3);
		add_filter('qsot-load-child-event-settings', array(__CLASS__, 'load_child_event_settings'), 10, 3);
		add_filter('qsot-render-event-agendaWeek-template-details', array(__CLASS__, 'agendaWeek_template_extra'), 10, 2);

		add_action('init', array(__CLASS__, 'register_assets'));
		add_filter('qsot-get-all-venue-meta', array(__CLASS__, 'get_all_venue_meta'), 10, 2);
		add_filter('qsot-get-venue-meta', array(__CLASS__, 'get_venue_meta'), 10, 3);
		add_action('qsot-save-venue-meta', array(__CLASS__, 'save_venue_meta'), 10, 3);
		add_action('save_post', array(__CLASS__, 'save_venue'), 10, 2);

		// special event query stuff
		add_action('pre_get_posts', array(__CLASS__, 'include_exclude_based_on_venue'), 10, 1);

		add_filter('qsot-compile-ticket-info', array(__CLASS__, 'add_venue_data'), 2000, 3);

		add_filter('single_template', array(__CLASS__, 'venue_template_default'), 10, 1);
		add_filter('qsot-venue-map-string', array(__CLASS__, 'map_string'), 10, 3);
		add_filter( 'qsot-get-venue-map', array( __CLASS__, 'get_map' ), 10, 3 );
		add_filter('qsot-get-venue-address', array(__CLASS__, 'get_address'), 10, 2);

		add_filter( 'qsot-formatted-venue-info', array( __CLASS__, 'get_formatted_venue_info' ), 100, 2 );
		add_filter( 'the_content', array( __CLASS__, 'add_venue_info' ), 100, 2 );

		do_action('qsot-restrict-usage', 'qsot-venue');
	}

	// add the venue information below the venue description
	public static function add_venue_info( $content, $post_id=false ) {
		// if the current post is not a venue, then skip this function
		$post_id = ! empty( $post_id ) ? $post_id : get_the_ID();
		if ( self::$o->{'venue.post_type'} != get_post_type( $post_id ) )
			return $content;

		return $content . apply_filters( 'qsot-formatted-venue-info', '', array( 'venue_id' => $post_id ) );
	}

	// find the appropriate template to display the venue post type
	public static function venue_template_default( $template ) {
		// only dod this for single venue posts
		if ( ! is_singular( self::$o->{'venue.post_type'} ) ) return $template;

		// find the appropriate template
		// first try to find the individual post type template
		$templ = apply_filters( 'qsot-locate-template', '', array(
			'single-qsot-venue.php',
		) );
		// if that failed, try the fallbacks
		$templ = '' !== $templ ? $templ : apply_filters( 'qsot-locate-template', '', array(
			$template,
			'single.php',
		) );

		return $templ ? $templ : $template;
	}

	// get a formatted version of the venue information
	public static function get_formatted_venue_info( $current, $args ) {
		$args = wp_parse_args( $args, array(
			'venue_id' => get_the_ID(),
			'only_meta' => false,
		) );
		extract( $args );

		// start collecting output so it can be returned
		ob_start();

		// fetch the venue post, the meta, and determine the template to use
		$venue = get_post( $venue_id );
		if ( ! is_object( $venue ) || ! isset( $venue->ID, $venue->post_type ) || self::$o->{'venue.post_type'} != $venue->post_type )
			return $current;

		$only_meta = is_string( $only_meta ) ? preg_split( '#\s*,\s*#', $only_meta ) : $only_meta;
		$only_meta = is_array( $only_meta ) ? array_filter( array_map( 'strtolower', $only_meta ) ) : $only_meta;
		$meta = apply_filters( 'qsot-get-all-venue-meta', array(), $venue_id );
		if ( is_array( $only_meta ) && count( $only_meta ) )
			foreach ( $meta as $k => $v )
				if ( ! in_array( strtolower( $k ), $only_meta ) )
					unset( $meta[ $k ] );

		$template = apply_filters( 'qsot-locate-template', '', array( 'post-content/venue-info.php' ), false, false );

		// if there is a template, include it
		if ( $template )
			include $template;

		// fetch the output
		$out = ob_get_contents();
		ob_end_clean();

		return $current . $out;
	}

	// add the data about the venue to the ticket information. used to create the ticket output
	public static function add_venue_data( $current, $oiid, $order_id ) {
		// skip this function if the ticket is not loaded yet, or if it is a wp_error
		if ( ! is_object( $current ) || is_wp_error( $current ) )
			return $current;

		// skip this function if the event information is not present either
		if ( ! isset( $current->event, $current->event->meta ) )
			return $current;

		// load the venue
		$venue = get_post( $current->event->meta->venue );

		// if the venue was loaded, then populate the venue information
		if ( is_object( $venue ) && isset( $venue->ID ) ) {
			$venue->meta = apply_filters( 'qsot-get-all-venue-meta', array(), $venue->ID );
			$venue->image_id = isset( $venue->meta['info'], $venue->meta['info']['logo_image_id'] ) && $venue->meta['info']['logo_image_id'] ? $venue->meta['info']['logo_image_id'] : get_post_thumbnail_id( $venue->ID );
			$venue->map_image = apply_filters( 'qsot-venue-map-string', '', $venue->meta['info'] );
			$venue->map_image_only = apply_filters( 'qsot-venue-map-string', '', $venue->meta['info'], array( 'type' => 'img' ) );
			$current->venue = $venue;
		} else {
			return new WP_Error( 'missing_data', __( 'Could not load the venue information for this ticket.', 'opentickets-community-edition' ), array( 'venue_id' => $current->event->meta->venue ) );
		}

		return $current;
	}

	// get the venue map
	public static function get_map( $current, $venue, $include_instructions=true) {
		// load the venue, and bail if this is not a venue
		$venue = get_post( $venue );
		if ( ! is_object( $venue ) || ! isset( $venue->post_type ) || $venue->post_type !== self::$o->{'venue.post_type'} )
			return $current;

		// load the map address
		$venue_info = get_post_meta( $venue->ID, '_venue_information', true );

		// load the map instructions if they need to be printed
		$map_instructions = $include_instructions && isset( $venue_info['instructions'] ) && ! empty( $venue_info['instructions'] )
			? '<div class="map-instructions">'.$venue_info['instructions'].'</div>'
			: '';

		return apply_filters( 'qsot-venue-map-string', '', $venue_info ) . $map_instructions;
	}

	public static function get_address($current, $venue) {
		$venue = get_post($venue);
		if ( ! is_object( $venue ) || ! isset( $venue->post_type ) || $venue->post_type !== self::$o->{'venue.post_type'} ) return $current;

		$kmap = array(
			'address1' => __('Address','opentickets-community-edition'),
			'address2' => __('Address 2','opentickets-community-edition'),
			'city' => __('City','opentickets-community-edition'),
			'state' => __('State','opentickets-community-edition'),
			'postal_code' => __('Postal Code','opentickets-community-edition'),
			'country' => __('Country','opentickets-community-edition'),
		);
		$venue_info = get_post_meta($venue->ID, '_venue_information', true);
		$out = array();
		foreach ($kmap as $k => $label)
			if (isset($venue_info[$k]) && !empty($venue_info[$k]))
				$out[] = '<li><span class="address-label">'.$label.':</span> <span class="address-value">'.$venue_info[$k].'</span></li>';

		return '<ul class="address-info">'.implode('', $out).'</ul>';
	}

	public static function map_string($_, $data, $settings='') {
		static $id = 0;

		$settings = wp_parse_args($settings, apply_filters('qsot-default-map-settings', array(
			'type' => 'map',
			'height' => 400,
			'width' => 400,
			'color' => 'green',
			'label' => '',
			'zoom' => 14,
			'class' => '',
			'id' => $id++,
		), $data));

		$d = array();
		foreach (array('address', 'city', 'state', 'postal_code', 'country') as $k) {
			if ($k == 'address') {
				$v = (isset($data['address1']) ? $data['address1'] : '').(isset($data['address2']) ? ' '.$data['address2'] : '');
			} else {
				$v = isset($data[$k]) ? $data[$k] : '';
			}
			if (!empty($v)) $d[] = $v;
		}
		$string = implode(',', $d);

		$url = sprintf(
			'http://maps.google.com/maps?q=%s',
			htmlentities2(urlencode($string))
		);

		$map_uri = 'http://maps.googleapis.com/maps/api/staticmap?'.htmlentities2(sprintf(
			'center=%s&zoom=%s&size=%sx%s&maptype=roadmap&markers=%s&sensor=false&format=jpg',
			urlencode($string),
			urlencode($settings['zoom']),
			urlencode($settings['width']),
			urlencode($settings['height']),
			sprintf('color:%s%%7Clabel:%s%%7C%s', urlencode($settings['color']), urlencode($settings['label']), urlencode($string))
		));

		$out = '';
		switch ($settings['type']) {
			case 'url': $out = $url; break;

			case 'img':
				$out = sprintf(
					'<img id="%s" src="%s" />',
					'venue-map-'.$settings['id'],
					$map_uri
				);
			break;

			default:
			case 'map':
				$out = sprintf(
					'<a href="%s" target="_blank"><img id="%s" src="%s" /></a>',
					$url,
					'venue-map-'.$settings['id'],
					$map_uri
				);
			break;
		}

		return $out;
	}

	public static function include_exclude_based_on_venue(&$q) {
		global $wpdb;

		if (isset($q->query_vars['only_venue']) || isset($q->query_vars['only_venue__in'])) {
			$ov = isset($q->query_vars['only_venue__in']) ? $q->query_vars['only_venue__in'] : $q->query_vars['only_venue'];
			$ov = is_array($ov) ? $ov : preg_split('#\s*,\s*#', $ov);
			$sql = $wpdb->prepare('select post_id from '.$wpdb->postmeta.' where meta_key = %s and meta_value in ('.implode(',', array_map('absint', $ov)).')', self::$o->{'meta_key.venue'});
			$ids = $wpdb->get_col($sql);
			if (!empty($ids)) $q->query_vars['post__in'] = array_merge($q->query_vars['post__in'], $ids);
		} else if (isset($q->query_vars['only_venue__not']) || isset($q->query_vars['only_venue__not_in'])) {
			$ov = isset($q->query_vars['only_venue__not_in']) ? $q->query_vars['only_venue__not'] : $q->query_vars['only_venue'];
			$ov = is_array($ov) ? $ov : preg_split('#\s*,\s*#', $ov);
			$sql = $wpdb->prepare('select post_id from '.$wpdb->postmeta.' where meta_key = %s and meta_value in ('.implode(',', array_map('absint', $ov)).')', self::$o->{'meta_key.venue'});
			$ids = $wpdb->get_col($sql);
			if (!empty($ids)) $q->query_vars['post__not_in'] = array_merge($q->query_vars['post__not_in'], $ids);
		}
	}

	public static function events_query_only_this_venue($args, $instance) {
		if (is_singular(self::$o->{'venue.post_type'})) {
			$args['only_venue'] = get_the_ID();
		}

		return $args;
	}

	// prepare to load the assets we need on the edit venue pages, by queuing up a later action to do so
	public static function load_venue_admin_assets( $exists, $post_id ) {
		add_action( 'woocommerce_screen_ids', array( __CLASS__, 'load_venue_admin_assets_later_pre' ), 1 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'load_venue_admin_assets_later' ), 1000000 );
	}

	// trick woocommerce into loading it's core assets, so that we can use select2 in the admin. we will probably change this down the road
	public static function load_venue_admin_assets_later_pre( $list ) {
		return array_unique( array_merge( $list, array( self::$o->{'venue.post_type'} ) ) );
	}

	// load the assets needed to edit the venues
	public static function load_venue_admin_assets_later( $hook ) {
		if ( ! function_exists( 'WC' ) ) return;

		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		// load our plugin tools
		wp_enqueue_script( 'qsot-tools' );

		// copied from class-wc-admin-assets.php. used for handling country-state switching
		wp_enqueue_script( 'wc-users', WC()->plugin_url() . '/assets/js/admin/users' . $suffix . '.js', array( 'jquery', 'wc-enhanced-select' ), WC_VERSION, true );
		wp_localize_script(
			'wc-users',
			'wc_users_params',
			array(
				'countries' => json_encode( array_merge( WC()->countries->get_allowed_country_states(), WC()->countries->get_shipping_country_states() ) ),
				'i18n_select_state_text' => esc_attr__( 'Select an option&hellip;', 'woocommerce' ),
			)
		);
	}

	// load the assets we need on the edit events page, for venues
	public static function load_event_venue_assets( $exists, $post_id ) {
		wp_enqueue_script( 'qsot-event-venue-settings' );
	}

	// add the bulk edit settings for the venue. used to select the venue for a group of events
	public static function venue_bulk_edit_settings( $list, $post, $mb ) {
		// get a list of all venues
		$vargs = array(
			'post_type' => self::$o->{'venue.post_type'},
			'post_status' => 'publish',
			'posts_per_page' => -1,
		);
		$venues = get_posts( $vargs );

		// draw the form field
		ob_start();
		?>
			<div class="setting-group">
				<div class="setting" rel="setting-main" tag="venue">
					<div class="setting-current">
						<span class="setting-name"><?php _e('Venue:','opentickets-community-edition') ?></span>
						<span class="setting-current-value" rel="setting-display"></span>
						<a class="edit-btn" href="#" rel="setting-edit" scope="[rel=setting]" tar="[rel=form]"><?php _e('Edit','opentickets-community-edition') ?></a>
						<input type="hidden" name="settings[venue]" value="" scope="[rel=setting-main]" rel="venue" />
					</div>
					<div class="setting-edit-form" rel="setting-form">
						<select name="venue">
							<option value="0">- <?php _e('None','opentickets-community-edition') ?> -</option>
							<?php foreach ($venues as $venue): ?>
								<option value="<?php echo esc_attr($venue->ID) ?>"><?php echo esc_attr($venue->post_title) ?></option>
							<?php endforeach; ?>
						</select>
						<div class="edit-setting-actions">
							<input type="button" class="button" rel="setting-save" value="<?php _e('OK','opentickets-community-edition') ?>" />
							<a href="#" rel="setting-cancel"><?php _e('Cancel','opentickets-community-edition') ?></a>
						</div>
					</div>
				</div>
			</div>
		<?php
		$out = ob_get_contents();
		ob_end_clean();

		// update the list
		$list['venue'] = $out;

		return $list;
	}

	public static function save_sub_event_settings($settings, $parent_id, $parent) {
		if (isset($settings['submitted'], $settings['submitted']->venue)) {
			$settings['meta'][self::$o->{'meta_key.venue'}] = $settings['submitted']->venue;
		}

		return $settings;
	}

	public static function load_child_event_settings($settings, $defs, $event) {
		if (is_object($event) && isset($event->ID)) {
			$venue_id = get_post_meta($event->ID, self::$o->{'meta_key.venue'}, true);
			$settings['venue'] = (int)$venue_id;
		}

		return $settings;
	}

	public static function agendaWeek_template_extra($additional, $post_id) {
		$additional .= '<div class="'.self::$o->fctm.'-section">'
			.'<span>Venue: </span><span class="'.self::$o->fctm.'-venue"></span>' // status
		.'</div>';

		return $additional;
	}

	public static function register_assets() {
		wp_register_script('qsot-event-venue-settings', self::$o->core_url.'assets/js/admin/venue/event-settings.js', array('qsot-event-ui'), self::$o->version);
		wp_register_style('qsot-single-venue-style', self::$o->core_url.'assets/css/frontend/venue.css', array(), self::$o->version);
	}

	public static function setup_meta_boxes($post) {
		add_meta_box(
			'venue-information',
			__('Venue Information','opentickets-community-edition'),
			array(__CLASS__, 'mb_venue_information'),
			$post->post_type,
			'normal',
			'high'
		);

		add_meta_box(
			'venue-social',
			__('Venue Social Information','opentickets-community-edition'),
			array(__CLASS__, 'mb_venue_social_information'),
			$post->post_type,
			'normal',
			'high'
		);

		do_action('qsot-events-venue-metaboxes', $post);
	}

	// fetch and normalize all the venue meta data
	public static function get_all_venue_meta( $current, $post_id ) {
		// get all meta data for the venue
		$all = get_post_custom( $post_id );
		$out = array();

		// for each meta data key values pair
		foreach ( $all as $k => $v ) {
			// if there is a mapped 'short name' for the meta key, use that instead of the long name
			$key = array_search( $k, self::$o->{'venue.meta_key'} );
			if ( $key === false )
				$key = $k;

			// unserialize any arrays that are still in serial form
			$out[ $key ] = maybe_unserialize( current( $v ) );
			
			// if there are default values for the meta key, then normalize the values by overlaying the settings on top of the defaults
			if ( $defaults = self::$o->{ 'venue.defaults.' . $key } )
				$out[ $key ] = wp_parse_args( $out[ $key ], $defaults );
		}

		return $out;
	}

	public static function get_venue_meta($current, $post_id, $name) {
		return self::_get_meta($post_id, $name);
	}

	protected static function _get_meta($post_id, $name) {
		$info = '';

		$k = isset(self::$o->{'venue.meta_key.'.$name}) ? self::$o->{'venue.meta_key.'.$name} : $name;
		if (isset(self::$o->{'venue.defaults.'.$name})) {
			$info = wp_parse_args(get_post_meta($post_id, $k, true), self::$o->{'venue.defaults.'.$name});
		} else {
			$info = wp_parse_args(get_post_meta($post_id, $k, true), array());
		}

		return $info;
	}

	// save venue meta, based on key name
	public static function save_venue_meta( $meta, $post_id, $name ) {
		// determine the appropriate meta key
		$k = isset( self::$o->{ 'venue.meta_key.' . $name } ) ? self::$o->{ 'venue.meta_key.' . $name } : $name;

		// update the metakey with the new data
		update_post_meta( $post_id, $k, $meta );
	}

	public static function mb_venue_information($post, $mb) {
		$info = apply_filters('qsot-get-venue-meta', array(), $post->ID, 'info');
		$country = isset( $info['country'] ) && $info['country'] ? $info['country'] : WC()->countries->get_base_country();
		?>
			<style>
				table.venue-information-table { width:100%; margin:0; }
				table.venue-information-table tbody th { font-weight:bold; text-align:right; white-space:nowrap; vertical-align:top; }
			</style>
			<table class="venue-information-table form-table">
				<tbody>
					<tr>
						<th width="1%"><?php _e('Address:','opentickets-community-edition') ?></th>
						<td><input type="text" class="widefat" name="venue[info][address1]" value="<?php echo esc_attr($info['address1']) ?>" /></td>
					</tr>
					<tr>
						<th><?php _e('Address 2:','opentickets-community-edition') ?></th>
						<td><input type="text" class="widefat" name="venue[info][address2]" value="<?php echo esc_attr($info['address2']) ?>" /></td>
					</tr>
					<tr>
						<th><?php _e('City:','opentickets-community-edition') ?></th>
						<td><input type="text" class="widefat" name="venue[info][city]" value="<?php echo esc_attr($info['city']) ?>" /></td>
					</tr>
					<tr>
						<th><?php _e('State or County:','opentickets-community-edition') ?></th>
						<td>
							<select name="venue[info][state]" class="widefat js_field-state" id="venue_state">
								<option value="" <?php selected( true, empty( $info['state'] ) || 'null' == $info['state'] ) ?>><?php _e( '-- Select a State/County --', 'opentickets-community-edition' ) ?></option>
								<?php $states = WC()->countries->get_states( $country ); if ( is_array( $states ) ) foreach ( $states as $abbr => $name ): ?>
									<option value="<?php echo esc_attr( $abbr ) ?>" <?php selected( $abbr, $info['state'] ) ?>><?php echo force_balance_tags( $name ) ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th><?php _e('Postal Code:','opentickets-community-edition') ?></th>
						<td><input type="text" class="widefat" name="venue[info][postal_code]" value="<?php echo esc_attr($info['postal_code']) ?>" /></td>
					</tr>
					<tr>
						<th><?php _e('Country:','opentickets-community-edition') ?></th>
						<td>
							<select name="venue[info][country]" class="widefat js_field-country">
								<?php $countries = WC()->countries->get_countries(); foreach ( $countries as $abbr => $name ): ?>
									<option value="<?php echo esc_attr( $abbr ) ?>" <?php selected( $abbr, $info['country'] ) ?>><?php echo force_balance_tags( $name ) ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th><?php _e('Logo Image','opentickets-community-edition') ?></th>
						<td rel="image-selection">
							<div class="logo-image-preview" rel="image-preview" size="thumbnail"><?php echo force_balance_tags( wp_get_attachment_image( $info['logo_image_id'], array( 150, 150 ) ) ) ?></div>
							<input type="hidden" name="venue[info][logo_image_id]" class="logo-image-id" rel="img-id" value="<?php echo esc_attr( (int) $info['logo_image_id'] ) ?>" />
							<input type="button" value="<?php echo esc_attr( __( 'Select Logo', 'opentickets-community-edition' ) ) ?>" class="button select-image-btn qsot-popmedia" rel="qsot-popmedia" scope="[rel='image-selection']" />
							<a href="#remove-img" rel="remove-img" class="remove-image-btn" scope="[rel='image-selection']"><?php _e( 'Remove', 'opentickets-community-edition' ) ?></a>
						</td>
					</tr>
					<tr>
						<th><?php _e('Notes:','opentickets-community-edition') ?></th>
						<td>
							<?php
								wp_editor(
									$info['notes'],
									'venue-info-notes',
									array(
										'quicktags' => false,
										'teeny' => true,
										'textarea_name' => 'venue[info][notes]',
										'textarea_rows' => 2,
										'media_buttons' => false,
										'wpautop' => false,
										'editor_class' => 'widefat',
										'tinymce' => array( 'wp_autoresize_on' => '', 'paste_as_text' => true ),
									)   
								);
							?>
							<div class="helper" style="font-size:9px; color:#888888;"><?php _e('This is what is displayed on your tickets about this venue.','opentickets-community-edition') ?></div>
						</td>
					</tr>
					<tr>
						<th><?php _e('Map Instructions:','opentickets-community-edition') ?></th>
						<td>
							<?php
								wp_editor(
									$info['instructions'],
									'venue-info-instructions',
									array(
										'quicktags' => false,
										'teeny' => true,
										'textarea_name' => 'venue[info][instructions]',
										'textarea_rows' => 2,
										'media_buttons' => false,
										'wpautop' => false,
										'editor_class' => 'widefat',
										'tinymce' => array( 'wp_autoresize_on' => '', 'paste_as_text' => true ),
									)   
								);
							?>
							<div class="helper" style="font-size:9px; color:#888888;"><?php _e('Displayed below the map on your event tickets. Meant for extra directions.','opentickets-community-edition') ?></div>
						</td>
					</tr>
					<?php do_action('qsot-venue-info-rows', $info, $post, $mb) ?>
				</tbody>
			</table>
			<?php do_action('qsot-venue-info-meta-box', $info, $post, $mb) ?>
		<?php
	}

	// social / contact info metabox
	public static function mb_venue_social_information( $post, $mb ) {
		// compensate for old typos that lead to bad meta storage
		$old_info = apply_filters( 'qsot-get-venue-meta', array(), $post->ID, 'info' );
		$info = apply_filters( 'qsot-get-venue-meta', array(), $post->ID, 'social' );
		foreach ( $info as $k => $v ) {
			if ( '' == $v && isset( $old_info[ $k ] ) && '' != $old_info[ $k ] ) {
				$info[ $k ] = $old_info[ $k ];
			}
		}

		// normalize the data
		$info = wp_parse_args( $info, array(
			'phone' => '',
			'website' => '',
			'facebook' => '',
			'twitter' => '',
			'contact_email' => ''
		) );

		// render the metabox
		?>
			<style>
				table.venue-social-information-table { width:100%; margin:0; }
				table.venue-social-information-table tbody th { font-weight:bold; text-align:right; white-space:nowrap; vertical-align:top; }
			</style>
			<table class="venue-social-information-table form-table">
				<tbody>
					<tr>
						<th width="1%"><?php _e('Phone:','opentickets-community-edition') ?></th>
						<td><input type="text" class="widefat" name="venue[social][phone]" value="<?php echo esc_attr( $info['phone'] ) ?>" /></td>
					</tr>
					<tr>
						<th><?php _e('Website:','opentickets-community-edition') ?></th>
						<td><input title="<?php echo esc_attr( __( 'Full URL', 'opentickets-community-edition' ) ) ?>" type="text" class="widefat" name="venue[social][website]" value="<?php echo esc_attr( $info['website'] ) ?>" /></td>
					</tr>
					<tr>
						<th><?php _e('Facebook:','opentickets-community-edition') ?></th>
						<td><input title="<?php echo esc_attr( __( 'Full URL', 'opentickets-community-edition' ) ) ?>" type="text" class="widefat" name="venue[social][facebook]" value="<?php echo esc_attr( $info['facebook'] ) ?>" /></td>
					</tr>
					<tr>
						<th><?php _e('Twitter:','opentickets-community-edition') ?></th>
						<td><input title="<?php echo esc_attr( __( 'Full URL', 'opentickets-community-edition' ) ) ?>" type="text" class="widefat" name="venue[social][twitter]" value="<?php echo esc_attr( $info['twitter'] ) ?>" /></td>
					</tr>
					<tr>
						<th><?php _e('Contact Email:','opentickets-community-edition') ?></th>
						<td><input type="text" class="widefat" name="venue[social][contact_email]" value="<?php echo esc_attr( $info['contact_email'] ) ?>" /></td>
					</tr>
					<?php do_action( 'qsot-venue-social-info-rows', $info, $post, $mb ) ?>
				</tbody>
			</table>
			<?php do_action( 'qsot-venue-social-info-meta-box', $info, $post, $mb ) ?>
		<?php
	}

	// when saving the venue posts, try to update the meta data
	public static function save_venue( $post_id, $post ) {
		// only do this for venue posts
		if ( $post->post_type != self::$o->{'venue.post_type'} )
			return;

		// if there is venue data to save
		if ( isset( $_POST['venue'] ) && is_array( $_POST['venue'] ) ) {
			// update the social and contact venue info
			if ( isset( $_POST['venue']['social'] ) ) {
				// aggregate the social and contact info, compensating for old data saving bug
				$old_data = apply_filters( 'qsot-get-venue-meta', array(), $post_id, 'social' );
				$info = apply_filters( 'qsot-get-venue-meta', array(), $post_id, 'info' );
				foreach ( $old_data as $k => $v ) {
					if ( '' === $v && isset( $info[ $k ] ) && '' !== $info[ $k ] )
						$old_data[ $k ] = $info[ $k ];
				}

				$data = wp_parse_args( $_POST['venue']['social'], $old_data);

				// merge the new settings on top of the old ones for the final values, and then save them
				do_action( 'qsot-save-venue-meta', $data, $post_id, 'social' );
			}

			// update the basic venue info
			if ( isset( $_POST['venue']['info'] ) ) {
				// aggregate the new info, and compensate for old data saving bug
				$data = wp_parse_args(
					$_POST['venue']['info'],
					apply_filters( 'qsot-get-venue-meta', array(), $post_id, 'info' )
				);

				$social = apply_filters( 'qsot-get-venue-meta', array(), $post_id, 'social' );
				foreach ( $social as $k => $v )
					unset( $data[ $k ] );

				// merge the new settings on top of the old ones for the final values, and then save them
				do_action( 'qsot-save-venue-meta', $data, $post_id, 'info' );
			}
		}
	}

	public static function register_post_type($list) {
		$list[self::$o->{'venue.post_type'}] = array(
			'label_replacements' => array(
				'plural' => __('Venues','opentickets-community-edition'), // plural version of the proper name
				'singular' => __('Venue','opentickets-community-edition'), // singular version of the proper name
			),
			'args' => array( // almost all of these are passed through to the core regsiter_post_type function, and follow the same guidelines defined on wordpress.org
				'public' => true,
				'menu_position' => 21.3,
				'supports' => array(
					'title',
					'editor',
					'thumbnail',
					'author',
					'excerpt',
					'custom-fields',
				),
				//'hierarchical' => true,
				'rewrite' => array('slug' => self::$o->{'venue.rewrite_slug'}),
				'register_meta_box_cb' => array(__CLASS__, 'setup_meta_boxes'),
				//'capability_type' => 'event',
				'show_ui' => true,
				'taxonomies' => array('category', 'post_tag'),
				'permalink_epmask' => EP_PAGES,
			),
		);

		return $list;
	}

	// setup the options that are available to control tickets. reachable at WPAdmin -> OpenTickets (menu) -> Settings (menu) -> Frontend (tab) -> Venue (heading)
	protected static function _setup_admin_options() {
		// setup the default values
		self::$options->def( 'qsot-venue-show-address', 'yes' );
		self::$options->def( 'qsot-venue-show-map', 'yes' );
		self::$options->def( 'qsot-venue-show-social', 'yes' );
		self::$options->def( 'qsot-venue-show-notes', 'no' );

		// the 'Tickets' heading on the Frontend tab
		self::$options->add( array(
			'order' => 600,
			'type' => 'title',
			'title' => __( 'Venues', 'opentickets-community-edition' ),
			'id' => 'heading-frontend-venues-1',
			'page' => 'frontend',
			'section' => 'venues',
		) );

		// whether or not to show the venue address below the venue description
		self::$options->add( array(
			'order' => 605,
			'id' => 'qsot-venue-show-address',
			'type' => 'checkbox',
			'title' => __( 'Show Address', 'opentickets-community-edition' ),
			'desc' => __( 'Display the venue address below the venue description?', 'opentickets-community-edition' ),
			'default' => 'yes',
			'page' => 'frontend',
			'section' => 'venues',
		) );

		// whether or not to show the venue map below the venue description
		self::$options->add( array(
			'order' => 605,
			'id' => 'qsot-venue-show-map',
			'type' => 'checkbox',
			'title' => __( 'Show Map', 'opentickets-community-edition' ),
			'desc' => __( 'Display the venue MAP below the venue description?', 'opentickets-community-edition' ),
			'default' => 'yes',
			'page' => 'frontend',
			'section' => 'venues',
		) );

		// whether or not to show the venue social contact info below the venue description
		self::$options->add( array(
			'order' => 610,
			'id' => 'qsot-venue-show-social',
			'type' => 'checkbox',
			'title' => __( 'Show Contact Info', 'opentickets-community-edition' ),
			'desc' => __( 'Display the venue contact information and social links below the venue description?', 'opentickets-community-edition' ),
			'default' => 'yes',
			'page' => 'frontend',
			'section' => 'venues',
		) );

		// whether or not to show the venue address below the venue description
		self::$options->add( array(
			'order' => 615,
			'id' => 'qsot-venue-show-notes',
			'type' => 'checkbox',
			'title' => __( 'Show Notes', 'opentickets-community-edition' ),
			'desc' => __( 'Display the additional venue notes (a meta field on the edit venue page) below the venue description?', 'opentickets-community-edition' ),
			'default' => 'no',
			'page' => 'frontend',
			'section' => 'venues',
		) );

		// end the 'Tickets' section on the page
		self::$options->add(array(
			'order' => 699,
			'type' => 'sectionend',
			'id' => 'heading-frontend-venues-1',
			'page' => 'frontend',
			'section' => 'venues',
		));
	}
}

if (defined('ABSPATH') && function_exists('add_action')) {
	qsot_venue_post_type:: pre_init();
}
