<?php (__FILE__ == $_SERVER['SCRIPT_FILENAME']) ? die(header('Location: /')) : null;

if (!class_exists('qsot_event_area')):

class qsot_event_area {
	// holder for event plugin options
	protected static $o = null;

	// holder for non-js-version errors
	protected static $nojs_submission_errors = array();

	public static function pre_init() {
		// first thing, load all the options, and share them with all other parts of the plugin
		$settings_class_name = apply_filters('qsot-settings-class-name', '');
		if (!class_exists($settings_class_name)) return false;
		self::$o =& $settings_class_name::instance();

		self::$o->event_area = apply_filters('qsot-event-area-options', array(
			'post_type' => 'qsot-event-area',
			'rewrite_slug' => false,
			'mk' => array(
				'cap' => '_capacity',
				'po' => '_pricing_options',
				'img' => '_thumbnail_id',
			),
		));

		$mk = self::$o->meta_key;
		self::$o->meta_key = array_merge(is_array($mk) ? $mk : array(), array(
			'event_area' => '_event_area_id',
			'ea_purchased' => '_purchased_ea',
		));

		// register this post type
		add_filter('qsot-events-core-post-types', array(__CLASS__, 'register_post_type'), 4, 1);
		// fetch list of the available event areas based on the venue
		add_filter('qsot-get-venue-event-areas', array(__CLASS__, 'get_venue_event_areas'), 4, 3);
		// allow advanced filtering, based on 'prices like X event' (primarily used in admin when 'changing' a reservation)
		add_action('pre_get_posts', array(__CLASS__, 'post_priced_like'), 10, 1);
		add_action('pre_get_posts', array(__CLASS__, 'post_has_price'), 10, 1);

		// different methods to actually attain the event area information
		add_filter('qsot-get-event-event-area', array(__CLASS__, 'get_event_ea'), 10, 2);
		add_filter('qsot-get-event-area', array(__CLASS__, 'get_event_area_by_id'), 10, 2);
		// draw the event area in the post content
		add_filter('qsot-event-the-content', array(__CLASS__, 'draw_event_area'), 1000, 2);
		// draw the image, if there is one, of the event area
		add_action('qsot-draw-event-area-image', array(__CLASS__, 'draw_event_area_image'), 100, 3);

		// register all js/css
		add_action('init', array(__CLASS__, 'register_assets'), 10);
		// load frontend js/css
		add_action('qsot-frontend-event-assets', array(__CLASS__, 'load_frontend_assets'), 10);
		add_filter('qsot-event-frontend-templates', array(__CLASS__, 'frontend_templates'), 10, 2);

		// update the purchase counts for a given event
		add_action('qsot-confirmed-ticket', array(__CLASS__, 'increment_purchase_count'), 10, 3);
		add_action('qsot-unconfirmed-ticket', array(__CLASS__, 'decrement_purchase_count'), 10, 3);
		add_action('qsot-ticket-selection-update-ticket-after-meta-update', array(__CLASS__, 'maybe_update_purchases'), 10, 4);
		// obtain the appropriate counts for an event. purchased ticket count or available ticket count
		add_filter('qsot-get-event-purchased-tickets', array(__CLASS__, 'get_purchased_tickets'), 100, 3);
		add_filter('qsot-get-event-available-tickets', array(__CLASS__, 'get_available_tickets'), 100, 3);
		// add important meta to events
		add_filter('qsot-event-meta', array(__CLASS__, 'add_event_meta'), 100, 3);
		// get the ticket type id based on the event id
		add_filter('qsot-ea-non-js-ticket-type-id', array(__CLASS__, 'get_ticket_type_id'), 100, 3);

		// intercept non-js form submissions
		add_filter('template_include', array(__CLASS__, 'intercept_no_js_form_submission'), 10, 1);

		// frontend ajax
		add_action('qsot-ajax-qsot-frontend-ticket-selection', array(__CLASS__, 'handle_frontend_ajax'), 10);
		add_action('qsot-ticket-selection-frontend-ajax-r', array(__CLASS__, 'faj_reserve'), 10, 2);
		add_action('qsot-ticket-selection-frontend-ajax-d', array(__CLASS__, 'faj_delete'), 10, 2);

		// allow external access to errors on non-js submissions
		add_filter('qsot-zoner-non-js-error-messages', array(__CLASS__, 'get_no_js_errors'), 10, 1);

		if (is_admin()) {
			// add the metabox to control this post type
			add_action('add_meta_boxes', array(__CLASS__, 'setup_meta_boxes'), 4);

			// load admin assets for the event edit page
			add_action('qsot-events-edit-page-assets', array(__CLASS__, 'load_event_settings_assets'), 10, 2);
			// laod assets for venue edit page
			add_action('qsot-admin-load-assets-qsot-venue', array(__CLASS__, 'load_admin_assets'), 10, 2);
			// js templates for the admin
			add_filter('qsot-event-area-admin-templates', array(__CLASS__, 'admin_templates'), 10, 2);

			// handle admin ajax stuff
			add_action('wp_ajax_qsot-event-area', array(__CLASS__, 'handle_ajax_admin'), 10);
			// load event area list, admin ajax
			add_action('qsot-event-area-admin-ajax-load', array(__CLASS__, 'aaj_load'), 10, 2);
			// save an event area, admin ajax
			add_action('qsot-event-area-admin-ajax-save-item', array(__CLASS__, 'aaj_save_item'), 10, 2);
			// delete an event area, admin ajax
			add_action('qsot-event-area-admin-ajax-delete-item', array(__CLASS__, 'aaj_delete_item'), 10, 2);

			// external admin ajax handlers
			add_filter('qsot-ticket-selection-admin-ajax-load-event', array(__CLASS__, 'aaj_ts_load_event'), 10, 2);
			add_filter('qsot-ticket-selection-admin-ajax-add-tickets', array(__CLASS__, 'aaj_ts_add_tickets'), 10, 2);

			// sub event bulk edit stuff
			add_action('qsot-events-bulk-edit-settings', array(__CLASS__, 'venue_bulk_edit_settings'), 20, 2);
			add_filter('qsot-events-save-sub-event-settings', array(__CLASS__, 'save_sub_event_settings'), 10, 3);
			add_filter('qsot-load-child-event-settings', array(__CLASS__, 'load_child_event_settings'), 10, 3);

			// update order item info. synced with ajax in pricing class
			add_filter('qsot-ticket-selection-admin-update-ticket-meta', array(__CLASS__, 'admin_update_ticket'), 10, 5);
		}
	}

	public static function register_assets() {
		wp_register_style('qsot-event-frontend', self::$o->core_url.'assets/css/frontend/event.css', array(), '0.1.0');
		wp_register_script('qsot-event-frontend', self::$o->core_url.'assets/js/features/event-area/ui.js', array('qsot-tools'), '0.1.0');
		wp_register_script('event-area-admin', self::$o->core_url.'assets/js/admin/event-area/ui.js', array('qsot-tools'), '0.1.0-beta', true);
		wp_register_script('qsot-event-event-area-settings', self::$o->core_url.'assets/js/admin/event-area/event-settings.js', array('qsot-event-ui'), self::$o->version);
	}

	public static function load_admin_assets($exists, $post_id) {
		wp_enqueue_script('event-area-admin');
		add_action('admin_footer', array(__CLASS__, 'footer_load_admin_assets_settings'));
	}

	public static function footer_load_admin_assets_settings() {
		global $post_ID;
		wp_localize_script('event-area-admin', '_qsot_event_area_settings', array(
			'nonce' => wp_create_nonce('event-areas-for-'.$post_ID),
			'venue_id' => $post_ID,
			'templates' => apply_filters('qsot-event-area-admin-templates', array(), $post_ID),
			'tickets' => apply_filters('qsot-get-all-ticket-products', array()),
			'ajaxurl' => admin_url('admin-ajax.php'),
		));
	}

	public static function load_frontend_assets($post) {
		wp_enqueue_style('qsot-event-frontend');
		if (is_object($post)) {
			$event = apply_filters('qsot-get-event', $post, $post);
			$ticket_id = is_object($event->meta) && is_object($event->meta->_event_area_obj) && is_object($event->meta->_event_area_obj->ticket) ? $event->meta->_event_area_obj->ticket->id : 0;
			wp_enqueue_script('qsot-event-frontend');
			wp_localize_script('qsot-event-frontend', '_qsot_ea_tickets', array(
				'nonce' => wp_create_nonce('frontend-events-ticket-selection-'.$event->ID),
				'edata' => self::_get_frontend_event_data($event),
				'ajaxurl' => site_url('/wp-load.php'),
				'templates' => apply_filters('qsot-event-frontend-templates', array(), $event),
				'messages' => array(
					'available' => array('msg' => 'There are currently <span class="available"></span> <span rel="tt"></span> available.', 'type' => 'msg'),
					'more-available' => array('msg' => 'There are currently <span class="available"></span> more <span rel="tt"></span> available.', 'type' => 'msg'),
					'not-available' => array('msg' => 'We\'re sorry. There are currently no tickets available.', 'type' => 'error'),
					'sold-out' => array('msg' => 'We are sorry. This event is sold out!', 'type' => 'error'),
					'one-moment' => array('msg' => '<h1>One Moment Please...</h1>', 'type' => 'msg'),
				),
				'owns' => $ticket_id ? apply_filters('qsot-zoner-owns-current-user', 0, $event, $ticket_id, self::$o->{'z.states.r'}) : 0,
			));
		}
	}

	public static function load_event_settings_assets($exists, $post_id) {
		wp_enqueue_script('qsot-event-event-area-settings');
	}

	protected static function _get_frontend_event_data($event) {
		$out = array(
			'id' => $event->ID,
			'name' => apply_filters('the_title', $event->post_title),
			'ticket' => false,
			'link' => get_permalink($event->ID),
			'parent_link' => get_permalink($event->post_parent),
			'capacity' => $event->meta->capacity,
			'available' => $event->meta->available,
		);
		if (is_object($event->meta) && is_object($event->meta->_event_area_obj) && is_object($event->meta->_event_area_obj->ticket)) {
			$out['ticket'] = array(
				'name' => $event->meta->_event_area_obj->ticket->get_title(),
				'price' => apply_filters('qsot-price-formatted', $event->meta->_event_area_obj->ticket->get_price()),
			);
		}

		return $out;
	}

	public static function frontend_templates($list, $event) {
		global $woocommerce;
		$cart_url = '#';
		if (is_object($woocommerce) && is_object($woocommerce->cart)) $cart_url = $woocommerce->cart->get_cart_url();

		$max = 1000000;
		if (is_object($event->meta) && is_object($event->meta->available)) $max = $event->meta->available;

		$list['ticket-selection'] = '<div class="ticket-form ticket-selection-section">'
				.'<h3>Step 1: How Many?</h3>'
				.'<div class="availability-message helper"></div>'
				.'<div class="field">'
					.'<span rel="tt"></span>'
					.'<input type="number" step="1" min="0" max="'.$max.'" rel="qty" name="quantity" value="1" class="very-short" />'
					.'<input type="button" value="Reserve Tickets" rel="reserve-btn" />'
				.'</div>'
			.'</div>';

		$list['owns'] = '<div class="ticket-form ticket-selection-section">'
				.'<h3>Step 2: Review</h3>'
				.'<div class="availability-more-message helper"></div>'
				.'<div class="field">'
					.'<a href="#" class="remove-link" rel="remove-btn">X</a>'
					.'<input type="number" step="1" min="0" max="'.$max.'" rel="qty" name="quantity" value="1" class="very-short" />'
					.'<span rel="tt"></span>'
					.'<input type="button" value="Update" rel="update-btn" />'
				.'</div>'
				.'<div class="qsot-form-actions">'
					.'<a href="'.esc_attr($cart_url).'" class="button">Proceed to Cart</a>'
				.'</div>'
			.'</div>';

		$list['msgs'] = '<div class="messages"></div>';
		$list['msg'] = '<div class="message"></div>';
		$list['error'] = '<div class="error"></div>';

		$list['tt'] = '<span class="ticket-description">'
				.'<span class="name" rel="ttname"></span>'
				.'<span class="price" rel="ttprice"></span>'
			.'</span>';

		return $list;
	}

	public static function admin_templates($list, $venue_id) {
		$list['area-ui'] = '<div class="area-ui">'
				.'<div class="actions top">'
					.'<button class="add-btn button" rel="add-btn">add</button>'
				.'</div>'
				.'<div class="area-list" rel="area-list"></div>'
				.'<div class="actions bottom">'
					.'<button class="add-btn button" rel="add-btn">add</button>'
				.'</div>'
			.'</div>';
		$list['no-areas'] = '<div class="view-area view none-area" rel="view-area">'
				.'<div class="inside">'
					.'<span class="none">There are currently no areas configured. Please add one to continue.</span>'
				.'</div>'
			.'</div>';
		$list['view-area'] = '<div class="view-area view" rel="view-area">'
				.'<div class="inside">'
					.'<div class="image-preview" size="thumb" rel="img-wrap"></div>'
					.'<div class="area-name" rel="area-name"></div>'
					.'<div class="info" rel="info">'
						.'<span class="ticket-name" rel="ttname"></span> @ '
						.'<span class="ticket-price" rel="ttprice"></span> '
						.'(x<span class="capacity" rel="capacity"></span>)'
					.'</div>'
					.'<div class="actions" rel="actions">'
						.'<a href="#" rel="edit-btn">edit</a>'
						.'<span class="divider"> | </span>'
						.'<a href="#" rel="del-btn">delete</a>'
					.'</div>'
				.'</div>'
				.'<div class="clear"></div>'
			.'</div>';
		$list['edit-area'] = '<div class="edit-area edit" rel="edit-area">'
				.'<div class="errors" rel="error-list"></div>'
				.'<input type="hidden" name="area-id[{{id}}]" rel="area-id" value="{{id}}"/>'
				.'<div class="edit-field image-select-wrap" rel="field">'
					.'<label for="img-id[{{id}}]">Event Area Image</label>'
					.'<div>'
						.'<div class="image-preview" size="full" rel="img-wrap"></div>'
						.'<input type="hidden" name="img-id[{{id}}]" value="0" rel="img-id" />'
						.'<div class="clear"></div>'
					.'</div>'
					.'<button class="button" rel="change-img">Select Image</button>'
				.'</div>'
				.'<div class="edit-field area-name-wrap" rel="field">'
					.'<label for="area-name[{{id}}]">Area Name</label>'
					.'<input autocomplete="off" type="text" class="widefat area-name" rel="area-name" name="area-name[{{id}}]" value="" />'
				.'</div>'
				.'<div class="edit-field area-name-wrap" rel="field">'
					.'<label for="capacity[{{id}}]">Capacity</label>'
					.'<input autocomplete="off" type="number" min="0" max="100000" step="1" class="widefat capacity" rel="capacity" name="capacity[{{id}}]" value="" />'
				.'</div>'
				.'<div class="edit-field area-ticket-type" rel="field">'
					.'<label for="area-ticket-type">Available Pricing</label>'
					/*
					.'<div class="price-options-list" rel="list">'
					.'</div>'
					*/
					.'<select class="widefat price-list" rel="ttid" name="price-option-tt-id[{{id}}]"></select>'
				.'</div>'
				.'<div class="actions" rel="actions">'
					.'<button class="button-primary save-btn" rel="save-btn">save</button>'
					.'<button class="button cancel-btn" rel="cancel-btn">cancel</button>'
				.'</div>'
			.'</div>';
		$list['price-option'] = '<div class="price-option-wrap" rel="price-option">'
				.'<table class="price-option-table"><tbody><tr>'
					.'<td class="price-option-name-wrap" width="75%">'
						.'<input type="text" class="widefat price-option-name" name="price-option-name[{{id}}][]" rel="name" value="no-name" />'
					.'</td>'
					.'<td class="price-option-name-wrap" width="25%">'
						.'<select class="widefat" name="price-option-tt-id[{{id}}][]" rel="ttid">'
							.'<option value="0">None</option>'
						.'</select>'
					.'</td>'
				.'</tr></tbody></table>'
			.'</div>';

		return $list;
	}

	public static function handle_frontend_ajax() {
		$post = wp_parse_args($_POST, array('sa' => '', 'event_id' => 0, 'nonce' => ''));
		$post['event_id'] = (int)$post['event_id'];
		$resp = array();
		if ($post['event_id'] > 0 && wp_verify_nonce($post['nonce'], 'frontend-events-ticket-selection-'.$post['event_id'])) {
			if (!empty($post['sa'])) $resp = apply_filters('qsot-ticket-selection-frontend-ajax-'.$post['sa'], $resp, $post);
		} else {
			$resp['s'] = false;
			$resp['e'] = array('Invalid request. Please refresh the page and try again.');
		}
		header('Content-Type: text/json');
		echo @json_encode($resp);
		exit;
	}

	public static function faj_reserve($resp, $data) {
		$resp['s'] = false;
		$resp['e'] = array();

		$event = apply_filters('qsot-get-event', false, $data['event_id']);
		$qty = $data['quantity'];
		if ($qty > 0 && is_object($event) && is_object($event->meta) && is_object($event->meta->_event_area_obj) && is_object($event->meta->_event_area_obj->ticket)) {
			$res = apply_filters('qsot-zoner-reserve-current-user', false, $event, $event->meta->_event_area_obj->ticket->id, $qty);
			if ($res) {
				$resp['s'] = true;
				$resp['m'] = array('Updated your reservations successfully.');
				$resp['data'] = array(
					'owns' => apply_filters('qsot-zoner-owns-current-user', 0, $event, $event->meta->_event_area_obj->ticket->id, self::$o->{'z.states.r'}),
					'available' => $event->meta->available,
				);
				$resp['data']['available_more'] = $resp['data']['available'] - $resp['data']['owns'];
			} else {
				$resp['e'][] = 'Could not update your reservations.';
			}
		} else {
			if ($qty <= 0) $resp['e'][] = 'The quantity must be greater than zero.';
			if (!is_object($event)) $resp['e'][] = 'Could not load that event.';
			if (!is_object($event->meta)) $resp['e'][] = 'A problem occurred when loading that event.';
			if (!is_object($event->meta->_event_area_obj)) $resp['e'][] = 'That event does not have currently have any tickets.';
			if (!is_object($event->meta->_event_area_obj->ticket)) $resp['e'][] = 'The event does not have any tickets.';
		}

		return $resp;
	}

	public static function faj_delete($resp, $data) {
		$resp['s'] = false;
		$resp['e'] = array();

		$event = apply_filters('qsot-get-event', false, $data['event_id']);
		if (is_object($event) && is_object($event->meta) && is_object($event->meta->_event_area_obj) && is_object($event->meta->_event_area_obj->ticket)) {
			$customer_id = apply_filters('qsot-zoner-current-user', md5(isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : time()));
			$where = array(
				'customer_id' => $customer_id,
				'event_id' => $event->ID,
				'ticket_type_id' => $event->meta->_event_area_obj->ticket->id,
				'state' => self::$o->{'z.states.r'},
			);
			if ($data['quantity'] <= 0) {
				$set = array(
					'qty' => 0,
					'_delete' => true,
				);
			} else {
				$set = array(
					'qty' => $data['quantity'],
				);
			}
			$res = apply_filters('qsot-zoner-update-reservation', false, $where, $set);
			$owns = apply_filters('qsot-zoner-owns-current-user', 0, $event, $event->meta->_event_area_obj->ticket->id, self::$o->{'z.states.r'});

			if ($data['quantity'] <= 0) {
				if (!$owns && $res) {
					$resp['s'] = true;
					$resp['m'] = array('Updated your reservations successfully.');
					$resp['data'] = array(
						'owns' => $owns,
						'available' => $event->meta->available,
					);
					$resp['data']['available_more'] = $resp['data']['available'] - $resp['data']['owns'];
				} else {
					if ($owns) $resp['e'][] = 'A problem occurred when trying to remove your reservations.';
					else $resp['e'][] = 'Could not update your reservations.';
				}
			} else {
				if (!$res || !$owns) {
					if ($owns) $resp['e'][] = 'A problem occurred when trying to update your reservations.';
					else $resp['e'][] = 'Could not update your reservations.';
				} else {
					$resp['s'] = true;
					$resp['m'] = array('Updated your reservations successfully.');
					$resp['data'] = array(
						'owns' => $owns,
						'available' => $event->meta->available,
					);
					$resp['data']['available_more'] = $resp['data']['available'] - $resp['data']['owns'];
				}
			}
		} else {
			if (!is_object($event)) $resp['e'][] = 'Could not load that event.';
			if (!is_object($event->meta)) $resp['e'][] = 'A problem occurred when loading that event.';
			if (!is_object($event->meta->_event_area_obj)) $resp['e'][] = 'That event does not have currently have any tickets.';
			if (!is_object($event->meta->_event_area_obj->ticket)) $resp['e'][] = 'The event does not have any tickets.';
		}

		return $resp;
	}

	public static function venue_bulk_edit_settings($post, $mb) {
		$eaargs = array(
			'post_type' => self::$o->{'event_area.post_type'},
			'post_status' => array('publish', 'inherit'),
			'posts_per_page' => -1,
		);
		$areas = get_posts($eaargs);
		?>
			<div class="setting-group">
				<div class="setting" rel="setting-main" tag="event-area">
					<div class="setting-current">
						<span class="setting-name">Area / Price:</span>
						<span class="setting-current-value" rel="setting-display"></span>
						<a href="#" rel="setting-edit" scope="[rel=setting]" tar="[rel=form]">Edit</a>
						<input type="hidden" name="settings[event-area]" value="" scope="[rel=setting-main]" rel="event-area" />
					</div>
					<div class="setting-edit-form hide-if-js" rel="setting-form">
						<select name="event-area" class="widefat">
							<option value="0">-None-</option>
							<?php foreach ($areas as $area): ?>
								<?php
									$ticket = get_product(get_post_meta($area->ID, self::$o->{'event_area.mk.po'}, true));
									$capacity = get_post_meta($area->ID, self::$o->{'event_area.mk.cap'}, true);
								?>
								<?php if (is_object($ticket)): ?>
									<option value="<?php echo esc_attr($area->ID) ?>" venue-id="<?php echo $area->post_parent ?>" capacity="<?php echo $capacity ?>"><?php
										echo esc_attr($area->post_title).' / '.$ticket->get_title()
												.' ('.apply_filters('qsot-price-formatted', $ticket->get_price()).')'
									?></option>
								<?php else: ?>
									<option value="<?php echo esc_attr($area->ID) ?>" venue-id="<?php echo $area->post_parent ?>" capacity="0"><?php
										echo esc_attr($area->post_title).' / '.apply_filters('the_title', '(no ticket selected)')
												.' ('.apply_filters('qsot-price-formatted', 0).')'
									?></option>
								<?php endif; ?>
							<?php endforeach; ?>
						</select>
						<div class="edit-setting-actions">
							<input type="button" class="button" rel="setting-save" value="OK" />
							<a href="#" rel="setting-cancel">Cancel</a>
						</div>
					</div>
				</div>
			</div>
		<?php
	}

	public static function save_sub_event_settings($settings, $parent_id, $parent) {
		if (isset($settings['submitted'], $settings['submitted']->event_area)) {
			$settings['meta'][self::$o->{'meta_key.event_area'}] = $settings['submitted']->event_area;
		}

		return $settings;
	}

	public static function load_child_event_settings($settings, $defs, $event) {
		if (is_object($event) && isset($event->ID)) {
			$ea_id = get_post_meta($event->ID, self::$o->{'meta_key.event_area'}, true);
			$settings['event-area'] = (int)$ea_id;
			if ($ea_id) $settings['capacity'] = get_post_meta($ea_id, self::$o->{'event_area.mk.cap'}, true);
		}

		return $settings;
	}

	public static function handle_ajax_admin() {
		$post = wp_parse_args($_POST, array('sa' => '', 'venue_id' => 0, 'nonce' => ''));
		$resp = array();
		if (wp_verify_nonce($post['nonce'], 'event-areas-for-'.$post['venue_id'])) {
			if (!empty($post['sa'])) $resp = apply_filters('qsot-event-area-admin-ajax-'.$post['sa'], $resp, $post);
		} else {
			$resp['s'] = false;
			$resp['e'] = array('Invalid request. Please refresh the page and try again.');
		}
		header('Content-Type: text/json');
		echo @json_encode($resp);
		exit;
	}

	public static function aaj_load($resp, $data) {
		$resp['s'] = false;
		$resp['e'] = array();

		$vid = isset($data['venue_id']) ? $data['venue_id'] : 0;
		if (empty($vid)) {
			$resp['e'][] = 'Could not find the venue you specified ['.$vid.'].';
			return $resp;
		}

		$resp['list'] = array_values(apply_filters('qsot-get-venue-event-areas', array(), $vid));

		return $resp;
	}
	
	public static function aaj_delete_item($resp, $data) {
		$resp['s'] = false;
		$resp['e'] = array();

		$ea_ids = isset($data['area-id']) ? $data['area-id'] : array();
		if (count($ea_ids)) {
			foreach ($ea_ids as $ea_id) {
				wp_delete_post($ea_id);
				$resp['s'] = true;
			}
		}

		return $resp;
	}
	
	public static function aaj_save_item($resp, $data) {
		$resp['s'] = false;
		$resp['e'] = array();

		$venue_id = isset($data['venue_id']) ? $data['venue_id'] : 0;
		if (!$venue_id) {
			$resp['e'][] = 'Could not find that venue.';
			return $resp;
		}

		$items = array();

		foreach ($data['area-id'] as $id => $ids) {
			if (!empty($data['area-name'][$id.''])) {
				$items[$id.''] = array(
					'id' => $id,
					'image_id' => $data['img-id'][$id.''],
					'name' => $data['area-name'][$id.''],
					'capacity' => $data['capacity'][$id.''],
					'ttid' => $data['price-option-tt-id'][$id.''],
				);
			}
		}

		if (empty($items)) {
			$resp['e'][] = 'Could not save the item'.(count($data['area-id']) == 1 ? '' : 's').' because not enough information was provided.';
			return $resp;
		}

		$resp['items'] = array();

		foreach ($items as $item) {
			$args = array(
				'post_parent' => $venue_id,
				'post_title' => $item['name'],
				'post_type' => self::$o->{'event_area.post_type'},
				'post_status' => 'inherit',
			);
			if ($item['id'] > 0) $args['ID'] = $item['id'];
			$id = wp_insert_post($args);
			if ($id) {
				$resp['s'] = true;
				update_post_meta($id, self::$o->{'event_area.mk.cap'}, $item['capacity']);
				update_post_meta($id, self::$o->{'event_area.mk.img'}, $item['image_id']);
				update_post_meta($id, self::$o->{'event_area.mk.po'}, $item['ttid']);
				$resp['items'][$item['id']] = apply_filters('qsot-get-venue-event-areas', array(), $venue_id, $id);
			} else {
				$resp['e'][] = 'There was a problem saving the area ['.$item['area-name'].'].';
			}
		}

		return $resp;
	}

	public static function aaj_ts_load_event($resp, $data) {
		$resp['s'] = false;
		$resp['e'] = array();

		$event_id = $data['eid'];
		$oiid = $data['oiid'];
		$oid = $data['order_id'];

		$event = apply_filters('qsot-get-event', false, $event_id);
		$order = new WC_Order($oid);
		if (is_object($event) && is_object($order)) {
			$customer_id = $data['customer_user'];
			if (empty($customer_id)) $customer_id = get_post_meta($order->id, '_customer_id', true);
			if (empty($customer_id)) $customer_id = md5($order->id);
			$resp['s'] = true;
			$resp['data'] = array(
				'id' => $event->ID,
				'name' => apply_filters('the_title', $event->post_title),
			);
			$resp['data']['_link'] = sprintf('<a href="%s" target="_blank">%s</a>', get_edit_post_link($event->ID), $resp['data']['name']);
			$resp['data']['_html_date'] = sprintf(
				'<span class="from">%s</span> - <span class="to">%s</span>',
				date_i18n('D, F jS, Y h:ia', strtotime($event->meta->start)),
				date_i18n('D, F jS, Y h:ia', strtotime($event->meta->end))
			);
			$resp['data']['_capacity'] = $event->meta->capacity;
			$resp['data']['_available'] = $event->meta->available;
			$resp['data']['_imgs'] = array();
			$resp['data']['_raw'] = $event;
			if (is_object($event->meta->_event_area_obj) && isset($event->meta->_event_area_obj->meta, $event->meta->_event_area_obj->meta['_thumbnail_id'])) {
				//$img_info = get_post_meta(get_post_thumbnail_id($event->meta->_event_area_obj->ID), '_wp_attachment_metadata', true);
				$img_info = get_post_meta($event->meta->_event_area_obj->meta['_thumbnail_id'], '_wp_attachment_metadata', true);
				$resp['data']['_image_info_raw'] = $img_info;
				if (isset($img_info['file']) && is_array($img_info) && isset($img_info['sizes']) && is_array($img_info['sizes'])) {
					$u = wp_upload_dir();
					$base_file = $img_info['file'];
					$file_path = trailingslashit(trailingslashit($u['baseurl']).str_replace(basename($base_file), '', $base_file));
					foreach ($img_info['sizes'] as $k => $info) {
						$resp['data']['_imgs'][$k] = array(
							'url' => $file_path.$info['file'],
							'width' => $info['width'],
							'height' => $info['height'],
						);
					}
					$resp['data']['_imgs']['full'] = array(
						'url' => trailingslashit($u['baseurl']).$base_file,
						'width' => $img_info['width'],
						'height' => $img_info['height'],
					);
				}
			}
			$resp['data']['_owns'] = 0;
			$owns = apply_filters('qsot-zoner-owns', 0, $event, $event->meta->_event_area_obj->ticket->id, false, false, $order->id);
			if (is_array($owns)) foreach ($owns as $state => $cnt) $resp['data']['_owns'] += $cnt;
		}

		return $resp;
	}

	public static function aaj_ts_add_tickets($resp, $data) {
		$resp['s'] = false;
		$resp['e'] = array();

		$oid = $data['order_id'];
		$eid = $data['eid'];
		$qty = $data['qty'];
		$event = apply_filters('qsot-get-event', false, $eid);
		$order = new WC_Order($oid);

		if ($qty > 0 && is_object($event) && is_object($order)) {
			$customer_id = $data['customer_user'];
			if (empty($customer_id)) $customer_id = get_post_meta($order->id, '_customer_id', true);
			if (empty($customer_id)) $customer_id = md5($order->id);
			$res = apply_filters('qsot-zoner-reserve', false, $event, $event->meta->_event_area_obj->ticket->id, $qty, $customer_id, $oid);
			if ($res) {
				do_action('qsot-order-admin-added-tickets', $order, $event, $event->meta->_event_area_obj->ticket->id, $qty);
				$resp['s'] = true;
			}
		} else {
			if ($qty <= 0) $resp['e'][] = 'The quantity must be greater than zero.';
			if (!is_object($event)) $resp['e'][] = 'Could not find that event.';
			if (!is_object($order)) $resp['e'][] = 'That is not a valid order.';
		}

		return $resp;
	}

	public static function admin_update_ticket($meta, $oiid, $item, $order, $data) {
		$event_id = $data['eid'];

		$event = apply_filters('qsot-get-event', false, $event_id);
		if (is_object($event) && is_array($item)) {
			$customer_user = get_post_meta($oid, '_customer_user', true);
			$where = array(
				'ticket_type_id' => $item['product_id'],
				'qty' => $item['qty'],
				'order_id' => $oid,
				'customer_id' => $customer_user,
				'event_id' => $item['event_id'],
				'state' => array(self::$o->{'z.states.c'}, self::$o->{'z.states.o'}),
			);
			$change_to = array(
				'event_id' => $event_id,
			);
			$res = apply_filters('qsot-zoner-update-reservation', false, $where, $change_to);
			if ($res) {
				$meta['event_id'] = $event_id;
			}
		}

		return $meta;
	}

	public static function get_venue_event_areas($list, $venue_id, $area_id=false) {
		$eas_args = array(
			'post_type' => self::$o->{'event_area.post_type'},
			'post_parent' => $venue_id,
			'post_status' => 'any',
			'orderby' => 'date',
			'order' => 'asc',
		);
		if ($area_id)
			$eas_args['include'] = is_array($area_id) ? implode(',', array_map('absint', $area_id)) : $area_id;
		$eas = get_posts($eas_args);

		$final = array();
		foreach ($eas as $ea) {
			$meta = get_post_custom($ea->ID);
			foreach ($meta as $k => $v) $meta[$k] = array_shift($v);

			$ea->meta = wp_parse_args($meta, array(
				self::$o->{'event_area.mk.cap'} => 0,
				self::$o->{'event_area.mk.po'} => 0,
				self::$o->{'event_area.mk.img'} => 0,
			));
			//$ea->meta[self::$o->{'event_area.mk.po'}] = is_array($ea->meta[self::$o->{'event_area.mk.po'}]) ? $ea->meta[self::$o->{'event_area.mk.po'}] : array();

			$ea->imgs = array(
				'full' => wp_get_attachment_image_src($ea->meta[self::$o->{'event_area.mk.img'}], 'full'),
				'thumb' => wp_get_attachment_image_src($ea->meta[self::$o->{'event_area.mk.img'}], array(150, 150)),
			);

			$final[$ea->ID] = $ea;
		}

		return $area_id ? $final[$area_id.''] : $final;
	}

	public static function mb_venue_event_areas() {
		?>
			<style>
				#available-event-areas { border-color:#cccccc; }
				#available-event-areas.closed { border-color:#e5e5e5; }
				#available-event-areas .inside { padding:0; margin:0; }
				#available-event-areas .image-preview { background-color:#cccccc; border:1px solid #888888; float:left; min-width:75px; min-height:75px; }
				#available-event-areas .item { padding:0.5em 0; border-top:1px dotted #888888; }
				#available-event-areas .item:first-child { padding-top:0; border-top:0; }
				#available-event-areas .view .image-preview { width:75px; height:75px; overflow:hidden; }
				#available-event-areas .view .image-preview img { width:75px; height:auto; }
				#available-event-areas .edit .image-preview { margin-bottom:3px; }
				#available-event-areas .view .area-name,
				#available-event-areas .view .info,
				#available-event-areas .view .actions { margin-left:83px; }
				#available-event-areas .viewing .view { display:block; }
				#available-event-areas .viewing .edit { display:none; }
				#available-event-areas .adding .view,
				#available-event-areas .editing .view { display:none; }
				#available-event-areas .adding .edit,
				#available-event-areas .editing .edit { display:block; }
				#available-event-areas .edit .errors .error { background-color:#ffeeee; margin:0 0 5px; }
				#available-event-areas .edit .edit-field { margin-bottom:5px; }
				#available-event-areas .area-ui > .actions { background-color:#f4f4f4; padding:0.3em 0.5em; }
				#available-event-areas .area-ui > .actions.top { border-bottom:1px solid #cccccc; }
				#available-event-areas .area-ui > .actions.bottom { border-top:1px solid #cccccc; }
				#available-event-areas .area-ui > .area-list { padding:0.5em; }
				#available-event-areas button { margin-right:5px; }
			</style>

			<div class="venue-event-area-admin" rel="event-area-admin">
			</div>
		<?php
	}

	public static function setup_meta_boxes() {
		$screens = array('qsot-venue');
		foreach ($screens as $screen) {
			add_meta_box(
				'available-event-areas',
				'Event Areas',
				array(__CLASS__, 'mb_venue_event_areas'),
				$screen,
				'normal',
				'high'
			);
		}
	}

	public static function add_event_meta($m, $event, $raw_meta) {
		$m['_event_area_obj'] = apply_filters('qsot-get-event-event-area', false, $event->ID);
		$m['capacity'] = $m['purchases'] = $m['available'] = 0;
		$m['availability'] = 'sold-out';
		if (is_object($m['_event_area_obj'])) {
			$m['capacity'] = $m['_event_area_obj']->meta['purchased'] + $m['_event_area_obj']->meta['available'];
			$m['purchases'] = $m['_event_area_obj']->meta['purchased'];
			$m['available'] = $m['_event_area_obj']->meta['available'];
			switch (true) {
				case $m['available'] >= ($m['capacity'] - self::$o->always_reserve) * 0.65: $m['availability'] = 'high'; break;
				case $m['available'] >= ($m['capacity'] - self::$o->always_reserve) * 0.30: $m['availability'] = 'medium'; break;
				case $m['available'] <= self::$o->always_reserve: $m['availability'] = 'sold-out'; break;
				default: $m['availability'] = 'low'; break;
			}
		}
		return $m;
	}

	public static function get_event_ea($current, $event_id) {
		$ea_id = get_post_meta($event_id, self::$o->{'meta_key.event_area'}, true);
		if (!empty($ea_id)) {
			$current = apply_filters('qsot-get-event-area', $current, $ea_id);
			if (is_object($current)) {
				$ttid = get_post_meta($ea_id, self::$o->{'event_area.mk.po'}, true);
				$current->meta['purchased'] = apply_filters('qsot-get-event-purchased-tickets', 0, $event_id, $ttid);
				$current->meta['available'] = apply_filters('qsot-get-event-available-tickets', 0, $event_id, $ttid);
				$current->is_soldout = ($current->meta['available'] <= 0);
			}
		}

		return $current;
	}

	public static function maybe_update_purchases($oiid, $from, $to, $order) {
		//qsot-confirmed-ticket
		if (isset($to['event_id'], $from['event_id'])) {
			do_action('qsot-unconfirmed-ticket', $order, $from, $oiid);
			$item = wp_parse_args($to, $from);
			do_action('qsot-confirmed-ticket', $order, $item, $oiid);
		}
	}

	public static function increment_purchase_count($order, $item, $item_id) {
		if (!isset($item['event_id'])) return;
		$current = get_post_meta($item['event_id'], self::$o->{'meta_key.ea_purchased'}, true);
		update_post_meta($item['event_id'], self::$o->{'meta_key.ea_purchased'}, $current + $item['qty']);
	}

	public static function decrement_purchase_count($order, $item, $item_id) {
		if (!isset($item['event_id'])) return;
		$current = get_post_meta($item['event_id'], self::$o->{'meta_key.ea_purchased'}, true);
		update_post_meta($item['event_id'], self::$o->{'meta_key.ea_purchased'}, $current - $item['qty'] < 0 ? 0 : $current - $item['qty']);
	}

	public static function get_purchased_tickets($current, $event_id, $ttid) {
		static $cache = array();

		$event_id = is_object($event_id) ? $event_id->ID : $event_id;
		if (!isset($cache[$event_id.''])) {
			$cache[$event_id.''] = get_post_meta($event_id, self::$o->{'meta_key.ea_purchased'}, true);
		}

		return $cache[$event_id.''];
	}

	public static function get_available_tickets($current, $event_id, $ttid) {
		static $cache = array();

		$event_id = is_object($event_id) ? $event_id->ID : $event_id;
		if (!isset($cache[$event_id.''])) {
			$purchased = (int)apply_filters('qsot-get-event-purchased-tickets', 0, $event_id, $ttid);
			$cache[$event_id.''] = (int)get_post_meta(
				(int)get_post_meta($event_id, self::$o->{'meta_key.event_area'}, true),
				self::$o->{'event_area.mk.cap'},
				true
			) - $purchased;
		}

		return $cache[$event_id.''];
	}

	public static function get_event_area_by_id($current, $ea_id) {
		$area = !empty($ea_id) ? get_post($ea_id) : false;

		if (is_object($area)) {
			$current = $area;
			$current->meta = array();
			$m = get_post_custom($current->ID);
			foreach (self::$o->{'event_area.mk'} as $k => $v) {
				$current->meta[$v] = isset($m[$v]) ? array_shift($m[$v]) : '';
			}
			$current->ticket = false;
			if (!empty($current->meta[self::$o->{'event_area.mk.po'}])) {
				$current->ticket = get_product($current->meta[self::$o->{'event_area.mk.po'}]);
				$current->ticket->_display_title = $current->ticket->get_title();
				$current->ticket->_display_price = apply_filters('qsot-price-formatted', $current->ticket->get_price());
			}
		}

		return $current;
	}

	public static function draw_event_area_image($event, $area, $reserved) {
		$ea_id = (int)get_post_meta($event->ID, self::$o->{'meta_key.event_area'}, true);
		if ($ea_id <= 0) return;
		$ea = get_post($ea_id);

		$thumb_id = get_post_meta($ea_id, self::$o->{'event_area.mk.img'}, true);
		if ($thumb_id > 0) {
			list($thumb_url, $w, $h, $rs) = wp_get_attachment_image_src($thumb_id, 'full');
			if ($thumb_url) {
				?>
				<div class="event-area-image-wrap">
					<img src="<?php echo esc_attr($thumb_url) ?>" class="event-area-image" alt="Image of the <?php echo esc_attr(apply_filters('the_title', $ea->post_title)) ?>" />
				</div>
				<?php
			}
		}
	}

	public static function draw_event_area($content, $event) {
		$out = '';

		if (apply_filters('qsot-can-sell-tickets-to-event', false, $event->ID)) {
			global $woocommerce;

			$area = apply_filters('qsot-get-event-event-area', false, $event->ID);
			$reserved = apply_filters('qsot-zoner-owns-current-user', 0, $event->ID, $area->ticket->post->ID, self::$o->{'z.states.r'});

			if (is_object($area)) {
				ob_start();
				$template = apply_filters('qsot-locate-template', '', array('post-content/event-area.php'), false, false);
				if (!empty($template)) include $template;
				$out = ob_get_contents();
				ob_end_clean();
			}

			$out = apply_filters('qsot-no-js-seat-selection-form', $out, $area, $event, $interests, $reserved);
		} else {
			$out = '<p><strong>We are sorry. Online registration for this event has closed.</strong></p>';
		}

		return $out.$content;
	}

	public static function get_ticket_type_id($current, $event, $post_data=array()) {
		$ea_id = (int)get_post_meta($event->ID, self::$o->{'meta_key.event_area'}, true);
		if ($ea_id <= 0) return $current;

		$ea = get_post($ea_id);
		if (!is_object($ea) || $ea->post_type != self::$o->{'event_area.post_type'}) return $current;

		$ttid = (int)get_post_meta($ea_id, self::$o->{'event_area.mk.po'}, true);
		return $ttid;
	}

	public static function post_has_price(&$q) {
		if (!in_array(self::$o->core_post_type, (array)$q->query_vars['post_type'])) return;
		if (!isset($q->query_vars['has_price'])) return;
		$q->query_vars['has_price'] = array_filter(array_map('absint', (array)$q->query_vars['has_price']));
		if (empty($q->query_vars['has_price'])) return;

		$ea_ids = get_posts(array(
			'post_type' => self::$o->{'event_area.post_type'},
			'post_status' => array('publish', 'inherit'),
			'posts_per_page' => -1,
			'fields' => 'ids',
			'suppress_filters' => false,
			'meta_query' => array(
				array(
					'key' => self::$o->{'event_area.mk.po'},
					'value' => $ttid,
					'compare' => '=',
					'type' => 'UNSIGNED',
				),
			),
		));
		if (empty($ea_ids)) return;

		$args = array(
			'post_type' => self::$o->core_post_type,
			'post_status' => 'publish',
			'posts_per_page' => -1,
			'fields' => 'ids',
			'meta_query' => array(
				array(
					'key' => self::$o->{'meta_key.event_area'},
					'value' => $ea_ids,
					'compare' => 'IN',
					'type' => 'UNSIGNED',
				),
			),
		);
		if (current_user_can('see_hidden_events')) $args['post_status'] = array('hidden', 'publish');

		$eids = get_posts($args);
		$q->query_vars['post__in'] = isset($q->query_vars['post__in']) ? array_merge($q->query_vars['post__in'], $eids) : $eids;
	}

	public static function post_priced_like(&$q) {
		if (!in_array(self::$o->core_post_type, (array)$q->query_vars['post_type'])) return;
		if (!isset($q->query_vars['priced_like']) || (int)$q->query_vars['priced_like'] <= 0) return;

		$event = get_post((int)$q->query_vars['priced_like']);
		if (!is_object($event) || $event->post_type != self::$o->core_post_type) return;
		$ttid = apply_filters('qsot-ea-non-js-ticket-type-id', 0, $event);
		if (empty($ttid)) return;

		$ea_ids = get_posts(array(
			'post_type' => self::$o->{'event_area.post_type'},
			'post_status' => array('publish', 'inherit'),
			'posts_per_page' => -1,
			'fields' => 'ids',
			'suppress_filters' => false,
			'meta_query' => array(
				array(
					'key' => self::$o->{'event_area.mk.po'},
					'value' => $ttid,
					'compare' => '=',
					'type' => 'UNSIGNED',
				),
			),
		));
		if (empty($ea_ids)) return;

		$args = array(
			'post_type' => self::$o->core_post_type,
			'post_status' => 'publish',
			'posts_per_page' => -1,
			'fields' => 'ids',
			'meta_query' => array(
				array(
					'key' => self::$o->{'meta_key.event_area'},
					'value' => $ea_ids,
					'compare' => 'IN',
					'type' => 'UNSIGNED',
				),
			),
		);
		if (current_user_can('see_hidden_events')) $args['post_status'] = array('hidden', 'publish');

		$eids = get_posts($args);
		$q->query_vars['post__in'] = isset($q->query_vars['post__in']) ? array_merge($q->query_vars['post__in'], $eids) : $eids;
	}

	public static function get_no_js_errors($current) {
		return count(self::$nojs_submission_errors) ? self::$nojs_submission_errors : array();
	}

	public static function intercept_no_js_form_submission($template) {
		$event = $GLOBALS['post'];
		if (!is_object($event) || $event->post_type != self::$o->core_post_type) return $template;
		do_action('qsot-zoner-clear-locks', $event->ID);

		if (isset($_GET['remove_reservations']) && isset($_GET['submission']) && wp_verify_nonce($_GET['submission'], 'ticket-selection-step-two')) {

			$ticket_type_id = apply_filters('qsot-ea-non-js-ticket-type-id', 0, $event, $_POST);
			apply_filters('qsot-zoner-reserve-current-user', false, $event, $ticket_type_id, 0);

			wp_safe_redirect(add_query_arg(array('rmvd' => 1), remove_query_arg(array('remove_reservations', 'submission'))));
			exit;
		}

		if (!isset($_POST['submission'], $_POST['qsot-step'])) return $template;

		$ticket_type_id = apply_filters('qsot-ea-non-js-ticket-type-id', 0, $event, $_POST);

		switch ($_POST['qsot-step']) {
			case 1:
				if (!wp_verify_nonce($_POST['submission'], 'ticket-selection-step-one')) break;
				$requested_count = $_POST['ticket-count'];
				if ($requested_count > 0) {
					$success = apply_filters('qsot-zoner-reserve-current-user', false, $event, $ticket_type_id, $requested_count);
					if (!$success) {
						$available = apply_filters('qsot-get-event-available-tickets', 0, $event, $ticket_type_id);
						$ticket = get_product($ticket_type_id);
						$ticket_name = sprintf(
							'"<span class="ticket-name">%s</span>" (<span class="ticket-price">%s</span>)',
							is_object($ticket) ? $ticket->get_title() : '(Unknown Ticket Type)',
							is_object($ticket) ? wc_price($ticket->get_price()) : wc_price(0)
						);
						self::$nojs_submission_errors[] = sprintf(
							'There are only <span class="available">%s</span> %s available currently. Could not temporarily reserve %d %s.',
							$available,
							$ticket_name,
							$requested_count,
							$ticket_name
						);
					} else {
						wp_safe_redirect(add_query_arg(array()));
						exit;
					}
				} else {
					self::$nojs_submission_errors[] = 'The number of tickets must be greater than 0.';
				}
			break;

			case 2:
				if (!wp_verify_nonce($_POST['submission'], 'ticket-selection-step-two')) break;
				$requested_count = $_POST['ticket-count'];
				if ($requested_count > 0) {
					$owns = apply_filters('qsot-zoner-owns-current-user', 0, $event, $ticket_type_id, self::$o->{'z.states.r'});
					$success = apply_filters('qsot-zoner-reserve-current-user', false, $event, $ticket_type_id, $requested_count);
					if (!$success) {
						$available = apply_filters('qsot-get-event-available-tickets', 0, $event, $ticket_type_id);
						$ticket = get_product($ticket_type_id);
						$ticket_name = sprintf(
							'"<span class="ticket-name">%s</span>" (<span class="ticket-price">%s</span>)',
							is_object($ticket) ? $ticket->get_title() : '(Unknown Ticket Type)',
							is_object($ticket) ? wc_price($ticket->get_price()) : wc_price(0)
						);
						self::$nojs_submission_errors[] = sprintf(
							'There are only <span class="available">%s</span> more %s available currently. Could not temporarily reserve %d more %s. You still have %d %s.',
							$available - $owns,
							$ticket_name,
							$requested_count,
							$ticket_name,
							$owns,
							$ticket_name
						);
					} else {
						wp_safe_redirect(add_query_arg(array()));
						exit;
					}
				} else {
					self::$nojs_submission_errors[] = 'The number of tickets must be greater than 0.';
				}
			break;
		}

		return $template;
	}

	public static function register_post_type($list) {
		$list[self::$o->{'event_area.post_type'}] = array(
			'label_replacements' => array(
				'plural' => 'Event Areas', // plural version of the proper name
				'singular' => 'Area', // singular version of the proper name
			),
			'args' => array( // almost all of these are passed through to the core regsiter_post_type function, and follow the same guidelines defined on wordpress.org
				'public' => false,
				'menu_position' => 21.35,
				'supports' => array(),
				//'hierarchical' => true,
				'rewrite' => array('slug' => self::$o->{'event_area.rewrite_slug'}),
				//'register_meta_box_cb' => array(__CLASS__, 'setup_meta_boxes'),
				//'capability_type' => 'event',
				'show_ui' => false,
				'permalink_epmask' => EP_PAGES,
			),
		);

		return $list;
	}
}

if (defined('ABSPATH') && function_exists('add_action')) {
	qsot_event_area::pre_init();
}

endif;
