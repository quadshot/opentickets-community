<?php (__FILE__ == $_SERVER['SCRIPT_FILENAME']) ? die(header('Location: /')) : null;

/* Handles the creation of the venues post type, and the basic management page interface and settings */
class qsot_venue_post_type {
	// holder for event plugin options
	protected static $o = null;

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

		// load different assets depending on the page that we are on in the admin
		add_action( 'qsot-admin-load-assets-' . self::$o->core_post_type, array( __CLASS__, 'load_event_venue_assets' ), 10, 2 );
		add_action( 'qsot-admin-load-assets-' . self::$o->{'venue.post_type'}, array( __CLASS__, 'load_venue_admin_assets' ), 10, 2 );

		add_filter('qsot-upcoming-events-query', array(__CLASS__, 'events_query_only_this_venue'), 10, 2);
		add_filter('qsot-events-core-post-types', array(__CLASS__, 'register_post_type'), 3, 1);
		add_action('qsot-events-bulk-edit-settings', array(__CLASS__, 'venue_bulk_edit_settings'), 20, 2);
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
		add_filter('qsot-get-venue-map', array(__CLASS__, 'get_map'), 10, 2);
		add_filter('qsot-get-venue-address', array(__CLASS__, 'get_address'), 10, 2);

		do_action('qsot-restrict-usage', 'qsot-venue');
	}

	public static function venue_template_default($template) {
		$post = get_queried_object();
		if ($post->post_type != 'qsot-venue') return $template;

		return apply_filters('qsot-maybe-override-theme_default', $template, 'single-qsot-venue.php', 'single.php');
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
			return new WP_Error( 'missing_data', __( 'Could not load the venue information for this ticket.', 'opentickets-community-edition' ), array( 'venue_id' => $current->event->meta->venue ) )
		}

		return $current;
	}

	public static function get_map($current, $venue) {
		$venue = get_post($venue);
		if (!is_object($venue) || !isset($venue->post_type) || $venue->post_type !== 'qsot-venue') return $current;

		$venue_info = get_post_meta($venue->ID, '_venue_information', true);
		$map_instructions = isset($venue_info['instructions']) && !empty($venue_info['instructions']) ? '<div class="map-instructions">'.$venue_info['instructions'].'</div>' : '';

		return apply_filters('qsot-venue-map-string', '', $venue_info).$map_instructions;
	}

	public static function get_address($current, $venue) {
		$venue = get_post($venue);
		if (!is_object($venue) || !isset($venue->post_type) || $venue->post_type !== 'qsot-venue') return $current;

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

	// laod the assets we need on the edit venue pages
	public static function load_venue_admin_assets( $exists, $post_id ) {
		wp_enqueue_script( 'qsot-tools' );
	}

	// load the assets we need on the edit events page, for venues
	public static function load_event_venue_assets( $exists, $post_id ) {
		wp_enqueue_script( 'qsot-event-venue-settings' );
	}

	public static function venue_bulk_edit_settings($post, $mb) {
		$vargs = array(
			'post_type' => self::$o->{'venue.post_type'},
			'post_status' => 'publish',
			'posts_per_page' => -1,
		);
		$venues = get_posts($vargs);
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

	public static function get_all_venue_meta($current, $post_id) {
		$all = get_post_custom($post_id);
		$out = array();
		foreach ($all as $k => $v) {
			$key = array_search($k, self::$o->{'venue.meta_key'});
			if ($key === false) $key = $k;
			$out[$key] = maybe_unserialize(array_shift($v));
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

	public static function save_venue_meta($meta, $post_id, $name) {
		$k = isset(self::$o->{'venue.meta_key.'.$name}) ? self::$o->{'venue.meta_key.'.$name} : $name;
		update_post_meta($post_id, $k, $meta);
	}

	public static function mb_venue_information($post, $mb) {
		$info = apply_filters('qsot-get-venue-meta', array(), $post->ID, 'info');
		?>
			<style>
				table.venue-information-table { width:100%; margin:0; }
				table.venue-information-table tbody th { font-weight:bold; text-align:right; white-space:nowrap; vertical-align:top; }
			</style>
			<table class="venue-information-table">
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
						<th><?php _e('State:','opentickets-community-edition') ?></th>
						<td><input type="text" class="widefat" name="venue[info][state]" value="<?php echo esc_attr($info['state']) ?>" /></td>
					</tr>
					<tr>
						<th><?php _e('Postal Code:','opentickets-community-edition') ?></th>
						<td><input type="text" class="widefat" name="venue[info][postal_code]" value="<?php echo esc_attr($info['postal_code']) ?>" /></td>
					</tr>
					<tr>
						<th><?php _e('Country:','opentickets-community-edition') ?></th>
						<td><input type="text" class="widefat" name="venue[info][country]" value="<?php echo esc_attr($info['country']) ?>" /></td>
					</tr>
					<tr>
						<th><?php _e('Logo Image','opentickets-community-edition') ?></th>
						<td rel="iamge-selection">
							<div class="logo-image-preview" rel="image-preview" size="thumbnail"><?php echo force_balance_tags( wp_get_attachment_image( $info['logo_image_id'], array( 150, 150 ) ) ) ?></div>
							<input type="hidden" name="venue[info][logo_image_id]" class="logo-image-id" rel="img-id" value="<?php echo esc_attr( (int) $info['logo_image_id'] ) ?>" />
							<input type="button" value="<?php echo esc_attr( __( 'Select Logo', 'opentickets-community-edition' ) ) ?>" class="button select-image-btn qsot-popmedia" rel="qsot-popmedia" />
							<a href="#remove-img" rel="remove-img" class="remove-image-btn"><?php _e( 'Remove', 'opentickets-community-edition' ) ?></a>
						</td>
					</tr>
					<tr>
						<th><?php _e('Notes:','opentickets-community-edition') ?></th>
						<td>
							<textarea class="widefat tinymce" name="venue[info][notes]"><?php echo $info['notes'] ?></textarea>
							<span class="helper" style="font-size:9px; color:#888888;"><?php _e('This is what is displayed on your tickets about this venue.','opentickets-community-edition') ?></span>
						</td>
					</tr>
					<tr>
						<th><?php _e('Map Instructions:','opentickets-community-edition') ?></th>
						<td>
							<textarea class="widefat tinymce" name="venue[info][instructions]"><?php echo $info['instructions'] ?></textarea>
							<span class="helper" style="font-size:9px; color:#888888;"><?php _e('Displayed below the map on your event tickets. Meant for extra directions.','opentickets-community-edition') ?></span>
						</td>
					</tr>
					<?php do_action('qsot-venue-info-rows', $info, $post, $mb) ?>
				</tbody>
			</table>
			<?php do_action('qsot-venue-info-meta-box', $info, $post, $mb) ?>
		<?php
	}

	public static function mb_venue_social_information($post, $mb) {
		$info = apply_filters('qsot-get-venue-meta', array(), $post->ID, 'info');
		$info = wp_parse_args($info, array(
			'phone' => '',
			'website' => '',
			'facebook' => '',
			'twitter' => '',
			'contact_email' => ''
		));
		?>
			<style>
				table.venue-social-information-table { width:100%; margin:0; }
				table.venue-social-information-table tbody th { font-weight:bold; text-align:right; white-space:nowrap; vertical-align:top; }
			</style>
			<table class="venue-social-information-table">
				<tbody>
					<tr>
						<th width="1%"><?php _e('Phone:','opentickets-community-edition') ?></th>
						<td><input type="text" class="widefat" name="venue[info][phone]" value="<?php echo esc_attr($info['phone']) ?>" /></td>
					</tr>
					<tr>
						<th><?php _e('Website:','opentickets-community-edition') ?></th>
						<td><input type="text" class="widefat" name="venue[info][website]" value="<?php echo esc_attr($info['website']) ?>" /></td>
					</tr>
					<tr>
						<th><?php _e('Facebook:','opentickets-community-edition') ?></th>
						<td><input type="text" class="widefat" name="venue[info][facebook]" value="<?php echo esc_attr($info['facebook']) ?>" /></td>
					</tr>
					<tr>
						<th><?php _e('Twitter:','opentickets-community-edition') ?></th>
						<td><input type="text" class="widefat" name="venue[info][twitter]" value="<?php echo esc_attr($info['twitter']) ?>" /></td>
					</tr>
					<tr>
						<th><?php _e('Contact Email:','opentickets-community-edition') ?></th>
						<td><input type="text" class="widefat" name="venue[info][contact_email]" value="<?php echo esc_attr($info['contact_email']) ?>" /></td>
					</tr>
					<?php do_action('qsot-venue-social-info-rows', $info, $post, $mb) ?>
				</tbody>
			</table>
			<?php do_action('qsot-venue-social-info-meta-box', $info, $post, $mb) ?>
		<?php
	}

	public static function save_venue($post_id, $post) {
		if ($post->post_type != self::$o->{'venue.post_type'}) return;

		if (isset($_POST['venue'], $_POST['venue']['info'])) {
			do_action(
				'qsot-save-venue-meta',
				wp_parse_args(
					$_POST['venue']['info'],
					apply_filters(
						'qsot-get-venue-meta',
						array(),
						$post_id,
						'info'
					)
				),
				$post_id,
				'info'
			);
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
}

if (defined('ABSPATH') && function_exists('add_action')) {
	qsot_venue_post_type:: pre_init();
}
