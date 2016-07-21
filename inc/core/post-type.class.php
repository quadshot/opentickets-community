<?php if ( __FILE__ == $_SERVER['SCRIPT_FILENAME'] ) die( header( 'Location: /') );

/* Handles the creation of the qsot (events) post type. Also handles the builtin metaboxes, event save actions, and general admin interface setup for events. */
class qsot_post_type {
	// holder for event plugin options
	protected static $o = null;
	protected static $options = null;

	public static function pre_init() {
		$settings_class_name = apply_filters('qsot-settings-class-name', '');
		if (!empty($settings_class_name)) {
			self::$o = call_user_func_array(array($settings_class_name, "instance"), array());

			$mk = self::$o->meta_key;
			self::$o->meta_key = array_merge(is_array($mk) ? $mk : array(), array(
				'start' => '_start',
				'end' => '_end',
				'capacity' => '_capacity',
				'purchase_limit' => '_purchase_limit',
			));

			// load all the options, and share them with all other parts of the plugin
			$options_class_name = apply_filters('qsot-options-class-name', '');
			if (!empty($options_class_name)) {
				self::$options = call_user_func_array(array($options_class_name, "instance"), array());
				self::_setup_admin_options();
			}

			add_action( 'load-options-permalink.php', array( __CLASS__, 'permalink_settings_page' ), 1000 );

			// setup the post type at the appropriate time
			//add_action('init', array(__CLASS__, 'register_post_type'), 1);
			add_filter('qsot-events-core-post-types', array(__CLASS__, 'register_post_type'), 1, 1);
			// register js and css assets at the appropriate time
			add_action('init', array(__CLASS__, 'register_assets'), 2);
			// hook to load assets on the frontend for events
			add_action('wp', array(__CLASS__, 'load_frontend_assets'), 1000);
			// hook into the edit page loading, and add our needed js and css for the admin interface
			add_action('load-post.php', array(__CLASS__, 'load_edit_page_assets'), 999);
			add_action('load-post-new.php', array(__CLASS__, 'load_edit_page_assets'), 999);
			// handle default post list for events
			add_action('load-edit.php', array(__CLASS__, 'intercept_event_list_page'), 10);
			add_filter('manage_'.self::$o->core_post_type.'_posts_columns', array(__CLASS__, 'post_columns'), 10, 1);
			add_filter('views_edit-'.self::$o->core_post_type, array(__CLASS__, 'adjust_post_list_views'), 10, 1);
			add_filter('_admin_menu', array(__CLASS__, 'patch_menu'), 10);
			add_action('admin_head', array(__CLASS__, 'patch_menu_second_hack'), 10);
			// handle saving of the parent event post
			add_action( 'save_post', array( __CLASS__, 'save_event' ), 999, 2 );
			// protect save_event() from 'remove_action' global variable violation bug, brought to light by using OpenTickets in tandem with TheEventCalendar
			// ** when calling remove_action() from a function that is called by that action, the $GLOBALS['wp_filter'] is directly modified.
			// ** at the same time the do_action/apply_filters function uses this exact same variable to cycle through the function callback list.
			// ** when you remove the last function attached to a given priority on a tag, deleting that prioiry's key, causes the foreach loop
			// ** in our do_action & apply_filters functions to skip over the next priority, because what would have been the nex priority falls
			// ** down the array into the current priority's slot, making php think the next one is actually the one that is two away.
			// ** I cannot replicate this problem in independent tests, outside wp, but I can reliably get this to happen within.
			add_action( 'save_post', 'qsot_noop', 998 );

			// action that is used to actually handle the saving of sub-events
			add_action( 'qsot-save-sub-events', array( __CLASS__, 'handle_save_sub_events' ), 100, 4 );

			// obtain start and end date range based on criteria
			add_filter('qsot-event-date-range', array(__CLASS__, 'get_date_range'), 100, 2);

			// filter to add the metadata to an event post object
			add_filter('qsot-get-event', array(__CLASS__, 'get_event'), 10, 2);
			add_filter('qsot-event-add-meta', array(__CLASS__, 'add_meta'), 10, 1);
			add_filter('the_posts', array(__CLASS__, 'the_posts_add_meta'), 10, 2);

			// add the 'hidden' post status, which allows all logged in users to see the permalink if they know the url, but block all searching on th frontend
			add_action('init', array(__CLASS__, 'register_post_statuses'), 1);
			// blcok all searching
			add_filter('posts_where', array(__CLASS__, 'hide_hidden_posts_where'), 10000, 2);

			// automatically use the parent event thumbnail if one is not defined for the child event
			add_filter('post_thumbnail_html', array(__CLASS__, 'cascade_thumbnail'), 10, 5);
			add_filter('get_post_metadata', array(__CLASS__, 'cascade_thumbnail_id'), 10, 4);

			// intercept the template for the event, and allow our base template to be used as the fallback instead of the single.php page
			add_filter('template_include', array(__CLASS__, 'template_include'), 10, 1);

			// special event query stuff
			add_action( 'parse_query', array( __CLASS__, 'adjust_wp_query_vars' ), PHP_INT_MAX, 1 );
			add_filter('posts_where_request', array(__CLASS__, 'events_query_where'), 10, 2);
			//add_filter('posts_join_request', array(__CLASS__, 'events_query_join'), 10, 2);
			add_filter('posts_orderby_request', array(__CLASS__, 'events_query_orderby'), 10, 2);
			add_filter('posts_fields_request', array(__CLASS__, 'events_query_fields'), 10, 2);

			add_filter('the_content', array(__CLASS__, 'the_content'), 10, 1);

			add_filter('qsot-can-sell-tickets-to-event', array(__CLASS__, 'check_event_sale_time'), 10, 2);
			add_filter('qsot-can-sell-tickets-to-event', array(__CLASS__, 'check_event_sale_time_hard_stop'), 20, 2);

			// get the event availability
			add_filter( 'qsot-get-availability', array( __CLASS__, 'get_availability' ), 10, 2 );
			add_filter( 'qsot-get-availability-text', array( __CLASS__, 'get_availability_text' ), 10, 4 );

			// events may have a purchase limit set. this filter finds that limit
			add_filter( 'qsot-event-ticket-purchase-limit', array( __CLASS__, 'event_ticket_purchasing_limit' ), 10, 2 );

			// if we are restricting editing of the quantity for tickets, then do so
			add_filter( 'woocommerce_cart_item_quantity', array( __CLASS__, 'maybe_prevent_ticket_quantity_edit' ), 1000, 3 );

			add_action('add_meta_boxes', array(__CLASS__, 'core_setup_meta_boxes'), 10, 1);

			add_filter('qsot-order-id-from-order-item-id', array(__CLASS__, 'order_item_id_to_order_id'), 10, 2);

			// 'social' plugin hack
			add_filter('social_broadcasting_enabled_post_types', array(__CLASS__, 'enable_social_sharing'), 10, 1);

			do_action('qsot-restrict-usage', self::$o->core_post_type);

			// add event name to item lists
			add_action( 'woocommerce_order_item_meta_start', array( __CLASS__, 'add_event_name_to_emails' ), 10, 3 );
			add_action('woocommerce_get_item_data', array(__CLASS__, 'add_event_name_to_cart'), 10, 2);

			// order by meta_value cast to date
			add_filter('posts_orderby', array(__CLASS__, 'wp_query_orderby_meta_value_date'), 10, 2);

			// work around for core hierarchical permalink bug - loushou
			// https://core.trac.wordpress.org/ticket/29615
			add_filter('post_type_link', array(__CLASS__, 'qsot_event_link'), 1000, 4);

			// handle adjacent_post_link logic
			add_filter( 'get_previous_post_where', array( __CLASS__, 'adjacent_post_link_where' ), 1000, 3 );
			add_filter( 'get_previous_post_join', array( __CLASS__, 'adjacent_post_link_join' ), 1000, 3 );
			add_filter( 'get_previous_post_sort', array( __CLASS__, 'adjacent_post_link_sort' ), 1000, 1 );
			add_filter( 'get_next_post_where', array( __CLASS__, 'adjacent_post_link_where' ), 1000, 3 );
			add_filter( 'get_next_post_join', array( __CLASS__, 'adjacent_post_link_join' ), 1000, 3 );
			add_filter( 'get_next_post_sort', array( __CLASS__, 'adjacent_post_link_sort' ), 1000, 1 );

			// get the settings of whether the current child event should show the date and time in the details page title
			add_filter( 'qsot-show-date-time', array( __CLASS__, 'get_show_date_time' ), 10, 2 );
			// add the date/time to the end of a title if the seetings permit
			add_filter( 'the_title', array( __CLASS__, 'the_title_date_time' ), 10, 2 );

			// add parent events to the category and tag pages
			add_filter( 'pre_get_posts', array( __CLASS__, 'events_in_categories_and_tags' ), 10, 1 );
		}
	}

	// work around for non-page hierarchical post type 'default permalink' bug i found - loushou
	// https://core.trac.wordpress.org/ticket/29615
	public static function qsot_event_link($permalink, $post, $leavename, $sample) {
		$post_type = get_post_type_object($post->post_type);

		if (!$post_type->hierarchical) return $permalink;

		// copied and slightly modified to actually work with WP_Query() from wp-includes/link-template.php @ get_post_permalink()
		global $wp_rewrite;

		$post_link = $wp_rewrite->get_extra_permastruct($post->post_type);
		$draft_or_pending = isset($post->post_status) && in_array( $post->post_status, array( 'draft', 'pending', 'auto-draft' ) );
		$slug = get_page_uri($post->ID);

		if ( !empty($post_link) && ( !$draft_or_pending || $sample ) ) {
			if ( ! $leavename )
				$post_link = str_replace("%$post->post_type%", $slug, $post_link);
			$post_link = home_url( user_trailingslashit($post_link) );
		} else {
			if ( $post_type->query_var && ( isset($post->post_status) && !$draft_or_pending ) )
				$post_link = add_query_arg($post_type->query_var, $slug, '');
			else
				$post_link = add_query_arg(array('post_type' => $post->post_type, 'p' => $post->ID), '');
			$post_link = home_url($post_link);
		}

		return $post_link;
	}

	// handle adjacent_post_link 'where' logic
	public static function adjacent_post_link_where( $where, $in_same_term, $excluded_terms ) {
		$post = get_post();
		// only make changes if we are talking about event posts
		if ( self::$o->core_post_type == $post->post_type ) {
			global $wpdb;

			// using start date as the sorter not the post_date
			$start = get_post_meta( $post->ID, '_start', true );
			$format = $wpdb->prepare( 'cast( qspm.meta_value as datetime ) $1 %s AND', $start );
			$where = preg_replace( '#p\.post_date ([^\s]+) .*?AND#', $format, $where );

			// only get child events, if viewing child events, and only get parent events, if viewing parent events
			if ( $post->post_parent ) {
				$where = preg_replace( '#(AND p.post_type = )#', 'AND p.post_parent != 0 \1', $where );
			} else {
				$where = preg_replace( '#(AND p.post_type = )#', 'AND p.post_parent = 0 \1', $where );
			}
		}

		return $where;
	}

	// handle adjacent_post_link 'join' logic
	public static function adjacent_post_link_join( $join, $in_same_term, $excluded_terms ) {
		$post = get_post();
		// only make changes if we are talking about event posts
		if ( self::$o->core_post_type == $post->post_type ) {
			global $wpdb;
			// using start date as the sorter not the post_date
			$join .= $wpdb->prepare( ' inner join ' . $wpdb->postmeta . ' as qspm on qspm.post_id = p.ID AND qspm.meta_key = %s', '_start' );
		}

		return $join;
	}

	// handle adjacent_post_link 'sort' logic
	public static function adjacent_post_link_sort( $orderby ) {
		$post = get_post();
		// only make changes if we are talking about event posts
		if ( self::$o->core_post_type == $post->post_type ) {
			$orderby = preg_replace( '#ORDER BY .*? ([^\s]+) LIMIT#', 'ORDER BY cast( qspm.meta_value as datetime ) \1 LIMIT', $orderby );
		}

		return $orderby;
	}

	// add the event name (and possibly date/time) to the order items in the cart
	public static function add_event_name_to_cart( $list, $item ) {
		// if we have an event to display the name of
		if ( isset( $item['event_id'] ) ) {
			// load the event
			$event = apply_filters( 'qsot-get-event', false, $item['event_id'] );

			// if the vent actually exists, then
			if ( is_object( $event ) ) {
				// add the event label to the list of meta data to display for this cart item
				$list[] = array(
					'name' => __( 'Event', 'opentickets-community-edition' ),
					'display' => sprintf( // add event->ID param so that date/time can be added appropriately
						'<a href="%s" title="%s">%s</a>',
						get_permalink( $event->ID ),
						__( 'View this event', 'opentickets-community-edition' ),
						apply_filters( 'the_title', $event->post_title, $event->ID )
					),
				);
			}
		}

		return $list;
	}

	public static function add_event_name_to_emails($item_id, $item, $order) {
		if (!isset($item['event_id']) || empty($item['event_id'])) return;
		$event = apply_filters('qsot-get-event', false, $item['event_id']);
		if (!is_object($event)) return;

		echo sprintf(
			'<br/><small><strong>' . __( 'Event', 'opentickets-community-edition' ) . '</strong>: <a class="event-link" href="%s" target="_blank" title="%s">%s</a></small>',
			get_permalink( $event->ID ),
			__('View this event','opentickets-community-edition'),
			apply_filters( 'the_title', $event->post_title, $event->ID )
		);
	}

	public static function patch_menu() {
		global $menu, $submenu;

		foreach ($menu as $ind => $mitem) {
			if (isset($mitem[5]) && $mitem[5] == 'menu-posts-'.self::$o->core_post_type) {
				$key = $menu[$ind][2];
				$new_key = $menu[$ind][2] = add_query_arg(array('post_parent' => 0), $key);
				if (isset($submenu[$key])) {
					$submenu[$new_key] = $submenu[$key];
					unset($submenu[$key]);
					foreach ($submenu[$new_key] as $sind => $sitem) {
						if ($sitem[2] == $key) {
							$submenu[$new_key][$sind][2] = $new_key;
							break;
						}
					}
				}
				break;
			}
		}
	}

	public static function patch_menu_second_hack() {
		global $parent_file;

		if ($parent_file == 'edit.php?post_type='.self::$o->core_post_type) $parent_file = add_query_arg(array('post_parent' => 0), $parent_file);
	}

	// based on $args, determine the start and ending date of a set of events
	public static function get_date_range($current, $args='') {
		$args = wp_parse_args( apply_filters('qsot-event-date-range-args', $args), array(
			'event_id' => 0,
			'with__self' => false,
			'year__only' => false,
			'include__only' => array(),
		));

		extract($args);

		$event_id = absint($event_id);
		$with__self = !!$with__self;
		$year__only = !!$year__only;
		$include__only = wp_parse_id_list($include__only);

		global $wpdb;

		$fields = array();
		$join = array();
		$where = array();
		$fmt = 'Y-m-d H:i:s';

		if ($year__only) {
			$fields[] = 'min(year(cast(pm.meta_value as datetime))) as min_val';
			$fields[] = 'max(year(cast(pm.meta_value as datetime))) as max_val';
			$fmt = 'Y';
		} else {
			$fields[] = 'min(cast(pm.meta_value as datetime)) as min_val';
			$fields[] = 'max(cast(pm.meta_value as datetime)) as max_val';
		}

		$join[] = $wpdb->prepare($wpdb->postmeta.' pm on pm.post_id = p.id and (pm.meta_key = %s or pm.meta_key = %s)', self::$o->{'meta_key.start'}, self::$o->{'meta_key.end'});

		if ($event_id) {
			if ($with__self) $where[] = $wpdb->prepare('and ( p.id = %d or p.post_parent = %d )', $event_id, $event_id);
			else $where[] = $where[] = $wpdb->prepare('and p.post_parent = %d', $event_id);
		} else if (!empty($include__only)) {
			$where[] = 'p.id in ('.implode(',', $include__only).')';
		}

		$pieces = array( 'where', 'fields', 'join' );

		foreach ($pieces as $piece)
			$$piece = apply_filters('qsot-event-date-range-'.$piece, $$piece);

		$clauses = (array) apply_filters('qsot-event-date-range-clauses', compact( $pieces ), $args);
		foreach ($pieces as $piece)
			$$piece = isset($clauses[$piece]) ? $clauses[$piece] : '';

		$fields  = !empty($fields) ? ( is_array($fields) ? implode(', ', $fields) : $fields ) : '*';
		$where  = !empty($where) ? $wpdb->prepare(' where p.post_type = %s ', self::$o->core_post_type).( is_array($where) ? implode(' ', $where) : $where ) : '';
		$join  = !empty($join) ? ' join '.( is_array($join) ? implode(' ', $join) : $join ) : '';

		$query = apply_filters(
			'qsot-event-date-range-request',
			'select '.$fields.' from '.$wpdb->posts.' p '.$join.' '.$where,
			compact($pieces),
			$args
		);

		$results = $wpdb->get_row($query, ARRAY_N);
		$today = date_i18n($fmt);

		return is_array($results) && count($results) == 2 ? $results : array($today, $today);
	}

	// figure out the timestamp of when to stop selling tickets for a given event, based on the event settings and the global settings. then determine if we are past that cut off or not
	public static function check_event_sale_time( $current, $event_id ) {
		// grab the value stored on the event
		$formula = get_post_meta( $event_id, '_stop_sales_before_show', true );

		// if there is no value stored on the event, or it is zero, then try to load the setting on the parent event, or the global setting
		if ( empty( $formula ) ) {
			// get the event post
			$post = get_post( $event_id );
			$parent_id = $post->post_parent;

			// try to load it from the parent event
			if ( $parent_id )
				$formula = get_post_meta( $parent_id, '_stop_sales_before_show', true );

			// if we still have no formula, then load the global setting
			if ( empty( $formula ) )
				$formula = apply_filters( 'qsot-get-option-value', '', 'qsot-stop-sales-before-show' );
		}

		// grab the start time of the event, so that we can use it in the timestamp calc
		$start = get_post_meta( $event_id, '_start', true );

		// determine if now() + time offset, is still less than the beginning of the show
		$stime = strtotime( $start );
		$time = current_time('timestamp');
		$adjust_time = strtotime( $formula, $time );
		if ( false == $adjust_time )
			$adjust_time = $time;

		// are we past it or not?
		return $adjust_time < $stime;
	}

	// check to see if we are past the hardstop date for sales on this event
	public static function check_event_sale_time_hard_stop( $current, $event_id ) {
		// only do this check if the value is true currently
		if ( ! $current )
			return $current;

		// get the hardstop time
		$hard_stop = get_post_meta( $event_id, '_stop_sales_hard_stop', true );

		// if the individual event does not have a hardstop set, check the parent
		if ( empty( $hard_stop ) ) {
			// get the event post
			$post = get_post( $event_id );
			$parent_id = $post->post_parent;

			// try to load it from the parent event
			if ( $parent_id )
				$hard_stop = get_post_meta( $parent_id, '_stop_sales_hard_stop', true );
		}

		// if there is still no hardstop, then bail
		if ( empty( $hard_stop ) )
			return $current;

		// get the stop time
		$stop_time = strtotime( $hard_stop );
		if ( false == $stop_time )
			return $current;

		// get the current time
		$time = current_time('timestamp');

		// determine if the current time is still before the hardstop
		return $time < $stop_time;
	}

	// adjust timestamp for time offset
	protected static function _offset( $ts, $dir=1 ) { return $ts + ( $dir * get_option( 'gmt_offset', 0 ) * HOUR_IN_SECONDS ); }

	// determine the offset description string
	protected static function _offset_str() {
		// get the offset
		$offset = get_option( 'gmt_offset', 0 );

		// get the sign of the offset
		$sign = $offset < 0 ? '-' : '+';

		// get the suffix, either :00 or :30
		$offset = abs( $offset );
		$floored = floor( $offset );
		$suffix = $offset == $floored ? ':00' : ':30';

		return sprintf( '%s%02s%s', $sign, $offset, $suffix );
	}

	// on the frontend, lets show the parent events in category and tag pages
	public static function events_in_categories_and_tags( $q ) {
		// if the option to show the parent envets on the homepage is not checked, then do not modify the query with this function
		if ( 'yes' !== self::$options->{'qsot-events-on-homepage'} )
			return $q;

		// alias the query vars to a shorter variable name (not required)
		$v = $q->query_vars;

		// do not make any changes to the query, if a specific POST
		// has been requested
		if ( ( isset( $v['name'] ) && ! empty( $v['name'] ) ) || ( isset( $v['p'] ) && ! empty( $v['p'] ) ) )
			return $q;

		// do not make any changes to the query, if a specific PAGE
		// has been requested
		if ( ( isset( $v['pagename'] ) && ! empty( $v['pagename'] ) ) || ( isset( $v['page_id'] ) && ! empty( $v['page_id'] ) ) )
			return $q;

		// when not in the admin, and processing the main page query
		if ( ! is_admin() && $q->is_main_query() ) {
			// if the list of post types was not supplied, and this is the homepage, then create one that uses 'post' and 'qsot-event' (event post type)
			if ( ( is_home() || is_front_page() ) && ( ! isset( $v['post_type'] ) || empty( $v['post_type'] ) ) ) {
				// make sure that the home page generic queries add events the list of post types to display
				$v['post_type'] = array( 'post', 'qsot-event' );

				// only show parent events. this has the unfortunate side effect of limiting other post types to parents only too... but this should only conflict with very very few plugins, and nothing core WP
				$v['post_parent'] = isset( $v['post_parent'] ) && ! empty( $v['post_parent'] ) ? $v['post_parent'] : '';
			// if the post type list is set and 'post' is the only type specified, then add the event post type to the list of possible post types to query for
			} else if ( isset( $v['post_type'] ) && ( $types = array_filter( (array)$v['post_type'] ) ) && 1 == count( $types ) && in_array( 'post', $types ) ) {
				$v['post_type'] = $types;
				$v['post_type'][] = 'qsot-event';
			}
		}

		// reassign the query vars back to the long name
		$q->query_vars = $q->query = $v;

		return $q;
	}

	public static function intercept_event_list_page() {
		if (isset($_GET['post_type']) && $_GET['post_type'] == self::$o->core_post_type) {
			add_action('pre_get_posts', array(__CLASS__, 'add_post_parent_query_var'), 10, 1);
		}
	}

	public static function add_post_parent_query_var(&$q) {
		if (isset($_GET['post_parent'])) {
			$q->query_vars['post_parent'] = $_GET['post_parent'];
		}
	}

	public static function post_columns($columns) {
		if (isset($_GET['post_parent']) && $_GET['post_parent'] == 0) {
			add_action('manage_'.self::$o->core_post_type.'_posts_custom_column', array(__CLASS__, 'post_columns_contents'), 10, 2);
			$final = array();
			foreach ($columns as $col => $val) {
				$final[$col] = $val;
				if ($col == 'title') $final['child-event-count'] = __('Events','opentickets-community-edition');
			}
			$columns = $final;
		}

		return $columns;
	}

	public static function post_columns_contents($column, $post_id) {
		global $wpdb;

		switch ($column) {
			case 'child-event-count':
				$total = (int)$wpdb->get_var($wpdb->prepare('select count(id) from '.$wpdb->posts.' where post_parent = %d and post_type = %s', $post_id, self::$o->core_post_type));
				echo $total;
			break;
		}
	}

	public static function adjust_post_list_views($views) {
		$post_counts = self::_count_posts();
		$post_counts["0"] = isset($post_counts["0"]) && is_numeric($post_counts["0"]) ? $post_counts["0"] : 0;
		$current = isset($_GET['post_parent']) && $_GET['post_parent'] == 0 ? ' class="current"' : '';

		$new_views = array(
			'only-parents' => sprintf(
				'<a href="%s"'.$current.'>%s (%d)</a>',
				'edit.php?post_type='.self::$o->core_post_type.'&post_parent=0',
				__('Top Level Events','opentickets-community-edition'),
				$post_counts["0"]
			),
		);

		foreach ($views as $slug => $view) {
			$new_views[$slug] = $current ? preg_replace('#(class="[^"]*)current([^"]*")#', '\1\2', $view) : $view;
		}

		return $new_views;
	}

	protected static function _count_posts() {
		global $wpdb;

		$return = array();
		$res = $wpdb->get_results($wpdb->prepare('select post_parent, count(post_type) as c from '.$wpdb->posts.' where post_type = %s group by post_parent', self::$o->core_post_type));
		foreach ($res as $row) $return["{$row->post_parent}"] = $row->c;

		return $return;
	}

	public static function enable_social_sharing($list) {
		$list[] = self::$o->core_post_type;
		return array_filter(array_unique($list));
	}

	// insert the event synopsis into the post content of the child events, so it is displayed on the individual event pages, when the synopsis options are turned on
	public static function the_content( $content ) {
		// if this is not a single event page, then bail now
		if ( ! is_singular( self::$o->core_post_type ) )
			return $content;

		// get the event post
		$post = get_post();

		// if the post has a password, then require it
		if ( post_password_required( $post ) )
			return $content;

		// if this is a child event post, then ...
		if ( ( $event = get_post() ) && is_object( $event ) && $event->post_type == self::$o->core_post_type && $event->post_parent != 0 ) {
			// if we are supposed to show the synopsis, then add it
			if ( self::$options->{'qsot-single-synopsis'} && 'no' != self::$options->{'qsot-single-synopsis'} ) {
				// emulate that the 'current post' is actually the parent post, so that we can run the the_content filters, without an infinite recursion loop
				$q = clone $GLOBALS['wp_query'];
				$p = clone $GLOBALS['post'];
				$GLOBALS['post'] = get_post( $event->post_parent );
				setup_postdata( $GLOBALS['post'] );

				// get the parent post content, and pass it through the appropriate filters for texturization
				$content = apply_filters( 'the_content', get_the_content() );

				// restore the original post
				$GLOBALS['wp_query'] = $q;
				$GLOBALS['post'] = $p;
				setup_postdata( $p );
			}

			// inform other classes and plugins of our new content
			$content = apply_filters( 'qsot-event-the-content', $content, $event );
		}

		return $content;
	}

	// when doing a wp_query, we need to check if some of our special query args are present, and adjust the params accordingly
	public static function adjust_wp_query_vars( &$query ) {
		$qv = wp_parse_args( $query->query_vars, array( 'start_date_after' => '', 'start_date_before' => '' ) );
		// if either the start or end date is present, then ...
		if ( ! empty( $qv['start_date_after'] ) || ! empty( $qv['start_date_before'] ) ) {
			$query->query_vars['meta_query'] = isset( $query->query_vars['meta_query'] ) && is_array( $query->query_vars['meta_query'] ) ? $query->query_vars['meta_query'] : array( 'relation' => 'OR' );

			// if both the start and end dates are present, then add a meta query for between
			if ( ! empty( $qv['start_date_after'] ) && ! empty( $qv['start_date_before'] ) ) {
				$query->query_vars['meta_query'][] = array( 'key' => '_start', 'value' => array( $qv['start_date_after'], $qv['start_date_before'] ), 'compare' => 'BETWEEN', 'type' => 'DATETIME' );
			// otherwise, if only the start date is present, then add a rule for that
			} else if ( ! empty( $qv['start_date_after'] ) ) {
				$query->query_vars['meta_query'][] = array( 'key' => '_start', 'value' => $qv['start_date_after'], 'compare' => '>=', 'type' => 'DATETIME' );
			// otherwise, only the end rule can be present, so add a rule for that
			} else {
				$query->query_vars['meta_query'][] = array( 'key' => '_start', 'value' => $qv['start_date_before'], 'compare' => '<=', 'type' => 'DATETIME' );
			}
		}
	}

	public static function wp_query_orderby_meta_value_date($orderby, $query) {
		if (
				isset($query->query_vars['orderby'], $query->query_vars['meta_key'])
				&& $query->query_vars['orderby'] == 'meta_value_date'
				&& !empty($query->query_vars['meta_key'])
		) {
			$order = strtolower(isset($query->query_vars['order']) ? $query->query_vars['order'] : 'asc');
			$order = in_array($order, array('asc', 'desc')) ? $order : 'asc';
			$orderby = 'cast(mt1.meta_value as datetime) '.$order;
		}
		return $orderby;
	}

	public static function events_query_where($where, $q) {
		global $wpdb;

		if (isset($q->query_vars['post_parent__not_in']) && !empty($q->query_vars['post_parent__not_in'])) {
			$ppni = $q->query_vars['post_parent__not_in'];
			if (is_string($ppni)) $ppni = preg_split('#\s*,\s*', $ppni);
			if (is_array($ppni)) {
				$where .= ' AND ('.$wpdb->posts.'.post_parent not in ('.implode(',', array_map('absint', $ppni)).') )';
			}
		}

		if (isset($q->query_vars['post_parent__in']) && !empty($q->query_vars['post_parent__in'])) {
			$ppi = $q->query_vars['post_parent__in'];
			if (is_string($ppi)) $ppi = preg_split('#\s*,\s*', $ppi);
			if (is_array($ppi)) {
				$where .= ' AND ('.$wpdb->posts.'.post_parent in ('.implode(',', array_map('absint', $ppi)).') )';
			}
		}

		if (isset($q->query_vars['post_parent__not']) && $q->query_vars['post_parent__not'] !== '') {
			$ppn = $q->query_vars['post_parent__not'];
			if (is_scalar($ppn)) {
				$where .= $wpdb->prepare(' AND ('.$wpdb->posts.'.post_parent != %s) ', $ppn);
			}
		}

		return $where;
	}

/*
	public static function events_query_join($join, $q) {
		global $wpdb;

		if (
			(isset($q->query_vars['start_date_after']) && strtotime($q->query_vars['start_date_after']) > 0) ||
			(isset($q->query_vars['start_date_before']) && strtotime($q->query_vars['start_date_before']) > 0)
		){
			$join .= $wpdb->prepare(' join '.$wpdb->postmeta.' as qssda on qssda.post_id = '.$wpdb->posts.'.ID and qssda.meta_key = %s ', self::$o->{'meta_key.start'});
		}

		return $join;
	}
*/

	public static function events_query_fields($fields, $q) {
		return $fields;
	}

	public static function events_query_orderby($orderby, $q) {
		global $wpdb;

		if (isset($q->query_vars['special_order']) && strlen($q->query_vars['special_order'])) {
			//$orderby = preg_split('#\s*,\s*#', $orderby);
			$orderby = $q->query_vars['special_order'];
			//$orderby = implode(', ', $orderby);
		}

		return $orderby;
	}

	// add the event metadata to event type posts, preventing the need to call this 'meta addtion' code elsewhere
	public static function the_posts_add_meta( $posts, $q ) {
		foreach ( $posts as $i => $post ) {
			if ( $post->post_type == self::$o->core_post_type ) {
				$posts[ $i ] = apply_filters( 'qsot-event-add-meta', $post, $post->ID );
			}
		}

		return $posts;
	}

	public static function get_event($current, $event_id) {
		$event = get_post($event_id);

		if (is_object($event) && isset($event->post_type) && $event->post_type == self::$o->core_post_type) {
			$event->parent_post_title = get_the_title( $event->post_parent );
			$event = apply_filters('qsot-event-add-meta', $event, $event_id);
		} else {
			$event = $current;
		}

		return $event;
	}

	// determine the availability of an event
	public static function get_availability( $count=0, $event_id=0 ) {
		// normalize the event_id to anumber
		if ( is_object( $event_id ) )
			$event_id = $event_id->ID;

		// use the global post if the event_id is not supplied
		if ( ! is_numeric( $event_id ) || $event_id <= 0 )
			$event_id = isset( $GLOBALS['post'] ) && is_object( $GLOBALS['post'] ) ? $GLOBALS['post']->ID : $event_id;

		// bail if the event id does not exist
		if ( ! is_numeric( $event_id ) || $event_id <= 0 )
			return $count;

		$ea_id = intval( get_post_meta( $event_id, '_event_area_id', true ) );
		$capacity = intval( get_post_meta( $ea_id, '_capacity', true ) );
		// fetch the total number of reservations for this event
		$purchases = intval( get_post_meta( $event_id, '_purchases_ea', true ) );

		return $capacity - $purchases;
	}

	// determine a language equivalent for describing the number of remaining tickets
	public static function get_availability_text( $current, $available, $event_id=null ) {
		// normalize the args
		if ( null === $event_id && self::$o->core_post_type == get_post_type() )
			$event_id = get_the_ID();
		if ( null === $event_id )
			return $current;
		$available = max( 0, (int)$available );

		// get the capacity and calculate the ratio of remaining tickets
		$capacity = max( 0, (int)get_post_meta( $event_id, self::$o->{'meta_key.capacity'}, true ) );
		$percent = 100 * ( ( $capacity ) ? $available / $capacity : 1 );
		// always_reserve is the number of tickets kept as a buffer, usually reserved for staff, but occassionally used as an overflow buffer for high selling events
		$adjust = 100 * ( ( $capacity ) ? self::$o->always_reserve / $capacity : 0.5 );

		// if the event is sold 
		if ( $percent <= apply_filters( 'qsot-availability-threshold-sold-out', $adjust ) )
			$current = __( 'sold-out', 'opentickets-community-edition' );
		// if the event is less than 30% available, then it is low availability
		else if ( $percent < apply_filters( 'qsot-availability-threshold-low', 30 ) )
			$current = __( 'low', 'opentickets-community-edition' );
		// if the event is less than 65% available but more than 29%, then it is low availability
		else if ( $percent < apply_filters( 'qsot-availability-threshold-low', 65 ) )
			$current = __( 'medium', 'opentickets-community-edition' );
		// otherwise, the number of sold tickets so far is inconsequential, so the availability is 'high'
		else
			$current = __( 'high', 'opentickets-community-edition' );

		return $current;
	}

	// figure out the current ticket purchasing limit for this event
	public static function event_ticket_purchasing_limit( $current, $event_id ) {
		// first, check the specific event, and see if there are settings specificly limiting it's purchase limit. if there is one, then use it
		$elimit = intval( get_post_meta( $event_id, self::$o->{'meta_key.purchase_limit'}, true ) );
		if ( $elimit > 0 )
			return $elimit;
		// if the value is negative, then this event specifically has no limit
		else if ( $elimit < 0 )
			return 0;

		// next check the parent event. if there is a limit there, then use it
		$event = get_post( $event_id );
		if ( is_object( $event ) && isset( $event->post_parent ) && $event->post_parent > 0 ) {
			$elimit = intval( get_post_meta( $event->post_parent, self::$o->{'meta_key.purchase_limit'}, true ) );
			if ( $elimit > 0 )
				return $elimit;
			// if the value is negative, then this event specifically, and all child events, are supposed to have no limit
			else if ( $elimit < 0 )
				return $elimit;
		}

		// as a last ditch effort, try to find the global setting and use it
		$elimit = apply_filters( 'qsot-get-option-value', 0, 'qsot-event-purchase-limit' );
		if ( $elimit > 0 )
			return $elimit;

		return $current;
	}

	// maybe prevent editing the quantity of tickets in the cart, based on settings
	public static function maybe_prevent_ticket_quantity_edit( $current, $cart_item_key, $cart_item=array() ) {
		// figure out the limit for this event
		$limit = isset( $cart_item['event_id'] ) ? apply_filters( 'qsot-event-ticket-purchase-limit', 0, $cart_item['event_id'] ) : 0;

		// there are two conditions when the quantity should not be editable:
		// 1) if the settings lock the user into keeping the quantity they initially selected
		// 2) if the purchase limit of the tickets is set to 1, meaning if it is in the cart, they are at the limit
		if ( 1 !== intval( $limit ) && 'no' == apply_filters( 'qsot-get-option-value', 'no', 'qsot-locked-reservations' ) )
			return $current;

		// check if this is a ticket. if not bail
		$product = wc_get_product( $cart_item['product_id'] );
		if ( ! is_object( $product ) || 'yes' != $product->ticket )
			return $current;

		// at this point, we need to restrict editing. so just return what to show in the column
		return '<div style="text-align:center;">' . $cart_item['quantity'] . '</div>';
	}

	public static function add_meta($event) {
		if (is_object($event) && isset($event->ID, $event->post_type) && $event->post_type == self::$o->core_post_type) {
			$km = self::$o->meta_key;
			$m = array();
			$meta = get_post_meta($event->ID);
			foreach ($meta as $k => $v) {
				if (($pos = array_search($k, $km)) !== false) $k = $pos;
				$m[$k] = maybe_unserialize(array_shift($v));
			}

			// get the proper capacity from the event_area
			if ( isset( $m['_event_area_id'] ) && intval( $m['_event_area_id'] ) > 0 ) {
				$m['_event_area_id'] = intval( $m['_event_area_id'] );
				$m['capacity'] = get_post_meta( $m['_event_area_id'], '_capacity', true );
			} else {
				$m['_event_area_id'] = 0;
			}

			$m = wp_parse_args($m, array('purchases' => 0, 'capacity' => 0));
			$m['available'] = apply_filters( 'qsot-get-availability', 0, $event->ID );
			$m['availability'] = apply_filters( 'qsot-get-availability-text', __( 'available', 'opentickets-community-edition' ), $m['available'], $event->ID );
			$m = apply_filters('qsot-event-meta', $m, $event, $meta);
			if (isset($m['_event_area_obj'], $m['_event_area_obj']->ticket, $m['_event_area_obj']->ticket->id))
				$m['reserved'] = apply_filters('qsot-zoner-owns', 0, $event, $m['_event_area_obj']->ticket->id, self::$o->{'z.states.r'});
			else
				$m['reserved'] = 0;
			$event->meta = (object)$m;

			$image_id = get_post_thumbnail_id($event->ID);
			$image_id = empty($image_id) ? get_post_thumbnail_id($event->post_parent) : $image_id;
			$event->image_id = $image_id;
		}

		return $event;
	}

	public static function order_item_id_to_order_id($order_id, $order_item_id) {
		static $cache = array();

		if (!isset($cache["{$order_id}"])) {
			global $wpdb;
			$q = $wpdb->prepare('select order_id from '.$wpdb->prefix.'woocommerce_order_items where order_item_id = %d', $order_item_id);
			$cache["{$order_id}"] = (int)$wpdb->get_var($q);
		}

		return $cache["{$order_id}"];
	}

	public static function cascade_thumbnail($html, $post_id, $post_thumbnail_id, $size, $attr) {
		if (empty($html) || empty($post_thumbnail_id)) {
			$post = get_post($post_id);
			if (is_object($post) && isset($post->post_type) && $post->post_type == self::$o->core_post_type && !empty($post->post_parent)) {
				$html = get_the_post_thumbnail($post->post_parent, $size, $attr);
			}
		}

		return $html;
	}

	// find an appropriate thumbnail based on the supplied info
	public static function cascade_thumbnail_id( $current, $object_id, $key, $single ) {
		// if we are not looking up the thumbnail_id, bail immediately
		if ( '_thumbnail_id' !== $key )
			return $current;

		// if the thumb was already found, bail now
		if ( $current )
			return $current;

		static $map = array();

		// if we have not looked up the post type for the supplied object_id yet, then look it up now
		if ( ! isset( $map[ $object_id . '' ] ) ) {
			$obj = get_post( $object_id );
			// if the post was loaded
			if ( is_object( $obj ) && ! is_wp_error( $obj ) && $obj->ID == $object_id )
				$map[$object_id.''] = $obj->post_type;
			// otherwise, cache something at least so we dont keep looking it up
			else
				$map[ $object_id . '' ] = '_unknown_post_type';
		}

		// if the supplied object is an event, and it is not a parent event, then...
		if ( $map[ $object_id . '' ] == self::$o->core_post_type && $key == '_thumbnail_id' && $parent_id = wp_get_post_parent_id( $object_id ) ) {
			// prevent weird recursion
			remove_filter( 'get_post_metadata', array( __CLASS__, 'cascade_thumbnail_id' ), 10 );

			// lookup this event's thumb
			$this_value = get_post_meta( $object_id, $key, $single );

			// restore thumbnail cascade
			add_filter( 'get_post_metadata', array( __CLASS__, 'cascade_thumbnail_id' ), 10, 4 );

			// if we did not find a thumb for this specific event, try to lookup the parent event's thumb
			if ( empty( $this_value ) )
				$current = get_post_meta( $parent_id, $key, $single );
			else
				$current = $this_value;
		}

		return $current;
	}

	public static function template_include($template) {
		if (is_singular(self::$o->core_post_type)) {
			$post = get_post();
			$files = array(
				'single-'.self::$o->core_post_type.'.php',
			);
			if ($post->post_parent != 0) array_unshift($files, 'single-'.self::$o->core_post_type.'-child.php');

			$tmpl = apply_filters('qsot-locate-template', '', $files);
			if (!empty($tmpl)) $template = $tmpl;
		}

		return $template;
	}

	// always register our scripts and styles before using them. it is good practice for future proofing, but more importantly, it allows other plugins to use our js if needed.
	// for instance, if an external plugin wants to load something after our js, like a takeover js, they will have access to see our js before we actually use it, and will
	// actually be able to use it as a dependency to their js. if the js is not yet declared, you cannot use it as a dependency.
	public static function register_assets() {
		$suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';

		// main event ui js. combines all the moving parts to make the date/time selection process more user friendly than other crappy event plugins
		wp_register_script('qsot-event-ui', self::$o->core_url.'assets/js/admin/event-ui.js', array('qsot-tools', 'fullcalendar'), self::$o->version);
		// initialization js. initializes all the moving parts. called at the top of the edit event page
		wp_register_script('qsot-events-admin-edit-page', self::$o->core_url.'assets/js/admin/edit-page.js', array('qsot-event-ui', 'jquery-ui-datepicker'), self::$o->version);
		// general additional styles for the event ui interface
		wp_register_style('qsot-admin-styles', self::$o->core_url.'assets/css/admin/ui.css', array('qsot-jquery-ui'), self::$o->version);
		// ajax js
		wp_register_script('qsot-frontend-ajax', self::$o->core_url.'assets/js/utils/ajax.js', array('qsot-tools'), self::$o->version);
	}

	public static function load_frontend_assets(&$wp) {
		if (is_singular(self::$o->core_post_type) && ($post = get_post()) && $post->post_parent != 0) {
			do_action('qsot-frontend-event-assets', $post);
		}
	}

	// need three main statuses for events.
	// Published - obvious, and needs no explanation
	// Private - Admin Only equivalent. this is a status that only editors and above can see when searching or browsing to the permalink. we will extend this to affect the calendar and grids also.
	// Hidden (new) - only logged in users can see this, and only if they know the permalink. security by obscurity.
	// CHANGE 6-27-13: Hidden should be completely public to anyone with the url, regardless of logged in status
	public static function register_post_statuses() {
		$slug = 'hidden'; // status name
		// status settings
		$args = array(
			'label' => _x('Hidden', 'post', 'qsot'), // nice label for the admin
			// @WHY-PUBLIC
			// though this is actually a 'private' type, which would otherwise be hidden, we must make it public. the reason is because core wordpress at 3.5.2 does not currently have a method to
			// clearly define rules for post statuses. so in order to bypass the core filtering in the WP_Query::get_posts(), which determines the visibility of a post (query.php@3.5.1 on line 2699),
			// so that we can make our own fancy filtering code, we MUST make it public, and then post-filter it.
			'public' => true,
			'publicly_queryable' => false, // not 100% sure what this actually does, because you can goto the permalink, search, and see in admin with this at false
			// @WHY-NOT-EXCLUDE-FROM-SEARCH
			// again, this seems counter intuitive, but it is needed. currently serves a dual purpose. purpose #1: in our event ui interface, we have a settable field called 'visibility' (aka status)
			// which queries WP for all post statuses that meet a list of specific criteria. one of those is that it is searchable, because we want to exclude several post statuses from that list
			// which are not search able. making this true would defeat the purpose of adding it, for that reason alone. purpose #2: again because core WP does not have a good way to granularly
			// control what a post status does, we must make our status by pass core WP filtering so that we can do our own filtering. we need this status to be searchable by users with the
			// read_private_pages capability, but not to anyone without read_private_pages permissions. to do this we need to allow WP add it to the SQL query for everyone, and then filter it out
			// manually for those who cant use it.
			'exclude_from_search' => false,
			'show_in_admin_all_list' => true, // do not hide from the admin events list
			'show_in_admin_status_list' => true, // allow the all events list to be filtered by this status
			'label_count' => _n_noop('Hidden <span class="count">(%s)</span>', 'Hidden <span class="count">(%s)</span>'), // show the count of hidden events in the admin events list page
		);
		// register the event
		register_post_status($slug, $args);
	}

	// as discussed above, in the post_status declaration (marked @WHY-NOT-EXCLUDE-FROM-SEARCH), we need to allow WP to add the hidden status to all relevant queries, and then filter it out
	// again for users that it does not apply to. since we want anyone with the read_private_pages to be able to see it, but anyone without to not see it, we need it there by default (so we don't
	// have to modify core WP) and then filter it out for anyone cannot see it. to do this we need to assert specific conditions are true, and if they do not pass, we need to filter out
	// the status from the query.
	public static function hide_hidden_posts_where($where, &$query) {
		// first, before thinking about making changes to the query, make sure that we are actually querying for our event post type. there are two cases where our event post type could be
		// being queried for, but we are only concerned with one. i'll explain both. the one we are not concerned with: if the where clause does not specifically filter for post_type, then
		// we could technically get an event post in the result. we are not concerned with this, because, except for some rare outlier situations and intentional circumventing of this rule,
		// the only time that a query should not specifically filter for post_type is when we are visiting a 'single' page, which we have a separate software driven filter for. THE ONE WE DO
		// CARE ABOUT: when the where clause implicitly states that we are querying by post_type. in this case, we are most likely doing a search or on some sort of list page. when that is
		// the case, we need to first make sure that we are querying our event post type, before we even think about changing the where clause. we do this with some specially crafted regex.
		$querying_events = false; // default is that we are not querying for our post type
		$parts = explode(' AND ', $where); // break the where clause into more logical sub blocks, so that we can test for our post_type filtering
		// foreach block, check if the block is testing the post type. if it is, additionally make sure that our post type is in the list of post types being tested for. if it is, they we
		// need to perform our additional checkes to determine if we need to filter this where statement to remove our special status.
		foreach ($parts as $part) if (preg_match('#post_type\s+(in|=)\s+.*'.self::$o->core_post_type.'#i', $part)) $querying_events = true;

		// our only other check is whether the current user can read_private_pages. if we are querying for our event post type, and the current user does not have our special capability
		if ($querying_events && is_user_logged_in() && !apply_filters( 'qsot-show-hidden-events', current_user_can( 'edit_posts' ) )) {
			// then craft a new where statement that is identical to the old one, minus our special status, based on how WP3.5.1 currently constructs the query
			$new_parts = array();
			// for each of the parts of the where statement, that we made above
			foreach ($parts as $part) {
				// test if it is the part that handles the post_status. if it is, then
				if (preg_match('#post_status.*(\'|")hidden\1#', $part)) {
					// remove our public 'hidden' post status from the query, since this user cannot see it, and add that piece of the where statement back to the where statement list
					$new_parts[] = preg_replace('#(\s+or)?\s+[^\s]+post_status\s+[^\s]+\s+(\'|")hidden\2#i', '', $part);
				} else {
					// if this is not the piece that handles the post_status filtering, then passthru this piece of the query, unmodified
					$new_parts[] = $part;
				}
			}
			// paste all the filtered pieces of the where statement together again
			$where = implode(' AND ', $new_parts);
		}

		// return the either unmodified or filtered where statement
		return $where;
	}

	// register our events post type
	public static function register_post_type( $list) {
		// needs to be it's own local variable, so that we can pass it as a 'used' variable to the anonymous function we make later
		$corept = self::$o->core_post_type;
		$rwslug = get_option( 'qsot-event-permalink-slug' );
		$rwslug = empty( $rwslug ) ? $corept : $rwslug;

		$list[$corept] = array(
			'label_replacements' => array(
				'plural' => __('Events','opentickets-community-edition'), // plural version of the proper name, used in the slightly modified labels in my _register_post_type method
				'singular' => __('Event','opentickets-community-edition'), // singular version of the proper name, used in the slightly modified labels in my _register_post_type method
			),
			'args' => array( // almost all of these are passed through to the core regsiter_post_type function, and follow the same guidelines defined on wordpress.org
				'public' => true,
				'menu_position' => 21.1,
				'supports' => array(
					'title',
					'editor',
					'thumbnail',
					'author',
					'excerpt',
					'custom-fields',
				),
				'hierarchical' => true,
				'rewrite' => array( 'slug' => $rwslug ),
				//'register_meta_box_cb' => array(__CLASS__, 'core_setup_meta_boxes'),
				//'capability_type' => 'event',
				'show_ui' => true,
				'taxonomies' => array( 'category', 'post_tag' ),
				'permalink_epmask' => EP_PAGES,
			),
		);

		return $list;
	}

	// when on the edit single event page in the admin, we need to queue up certain aseets (previously registered) so that the page actually works properly
	public static function load_edit_page_assets() {
		// is this a new event or an existing one? we can check this by determining the post_id, if there is one (since WP does not tell us)
		$post_id = 0;
		// if there is a post_id in the admin url, and the post it represents is of our event post type, then this is an existing post we are just editing
		if (isset($_REQUEST['post']) && get_post_type($_REQUEST['post']) == self::$o->core_post_type) {
			$post_id = $_REQUEST['post'];
			$existing = true;
		// if there is not a post_id but this is the edit page of our event post type, then we still need to load the assets
		} else if (isset($_REQUEST['post_type']) && $_REQUEST['post_type'] == self::$o->core_post_type) {
			$existing = false;
		// if this is not an edit page of our post type, then we need none of these assets loaded
		} else return;

		// remove the cascade thumbnail logic because it interfers with the child event thumb selection
		remove_filter( 'post_thumbnail_html', array( __CLASS__, 'cascade_thumbnail' ), 10, 5 );
		remove_filter( 'get_post_metadata', array( __CLASS__, 'cascade_thumbnail_id' ), 10, 4 );

		wp_enqueue_script( 'set-post-thumbnail' );
		// load the eit page js, which also loads all it's dependencies
		wp_enqueue_script('qsot-events-admin-edit-page');
		// load the fullcalendar styles and the misc interface styling
		wp_enqueue_style('fullcalendar');
		wp_enqueue_style('qsot-admin-styles');

		wp_localize_script( 'qsot-event-ui', '_qsot_event_ui_settings', array(
			'frmts' => array(
				'MM-dd-yyyy' => __( 'MM-dd-yyyy', 'opentickets-community-edition' ),
				'ddd MM-dd-yyyy' => __( 'ddd MM-dd-yyyy', 'opentickets-community-edition' ),
				'hh:mmtt' => __( 'hh:mmtt', 'opentickets-community-edition' ),
			),
			'tz' => get_option('timezone_string'),
			'str' => array(
				'New Event Date' => __( 'New Event Date', 'opentickets-community-edition' ),
			),
		) );

		// use the loacalize script trick to send misc settings to the event ui script, based on the current post, and allow sub/external plugins to modify this
		@list( $events, $first ) = self::_child_event_settings($post_id);
		wp_localize_script('qsot-events-admin-edit-page', '_qsot_settings', apply_filters('qsot-event-admin-edit-page-settings', array(
			'first' => $first,
			'events' => $events, // all children events
			'templates' => self::_ui_templates($post_id), // all templates used by the ui js
		), $post_id));

		// allow sub/external plugins to load their own stuff right now
		do_action('qsot-events-edit-page-assets', $existing, $post_id);
	}

	// load a list of all the child events to teh given event. this will be sent to the js event ui interface as settings to aid in construction of the interface
	protected static function _child_event_settings($post_id) {
		$list = array();
		// if there is no post_id then return an empty list
		if ( empty( $post_id ) )
			return array( $list, '' );

		$post = get_post( $post_id );
		// if the post_id passed does not exist, then return an empty list
		if ( ! is_object( $post ) || ! isset( $post->post_title ) )
			return array( $list, '' );

		// default settings for the passed lit of subevent objects. modifiable by sub/external plugins, so they can add their own settings
		$defs = apply_filters('qsot-load-child-event-settings-defaults', array(
			'title' => $post->post_title,
			'start' => '0000-00-00 00:00:00',
			'allDay' => false,
			'editable' => true,
			'status' => 'pending', // status
			'visibility' => 'public', // visibiltiy
			'password' => '', // protected password
			'pub_date' => '', // date to publish
			'capacity' => 0, // max occupants
			'post_id' => -1, // sub event post_id used for lookup during save process
			'edit_link' => '', // edit individual event link
			'view_link' => '', // view individual event link
			'purchase_limit' => '', // limit the number of tickets that can be purchased on a single order for this event
		), $post_id);

		// args to load a list of child events using WP_Query
		$pargs = array(
			'post_type' => self::$o->core_post_type, // event post type (exclude images and other sub posts)
			'post_status' => 'any', // any status, including our special one
			'post_parent' => $post->ID, // children to this main event
			'posts_per_page' => -1, // all of them, not limited to 5 (like the default)
		);
		// get the list
		$events = get_posts($pargs);

		$earliest = PHP_INT_MAX;
		// foreach sub event we found, do some stuff
		foreach ( $events as $event ) {
			// load the meta, and reduce the list to only the first value for each piece of meta (since there is rarely any duplicates)
			$meta = get_post_meta( $event->ID );
			foreach ( $meta as $k => $v )
				$meta[ $k ] = array_shift( $v );

			// determine the start & end date for the item. default to the _start meta value, and fallback on the post slug (super bad if this ever gets used. mainly for recovery purposes)
			$start = isset( $meta[ self::$o->{'meta_key.start'} ] )
					? $meta[ self::$o->{'meta_key.start'} ]
					: date( 'Y-m-d H:i:s', strtotime( preg_replace( '#(\d{4}-\d{2}-\d{2})_(\d{1,2})-(\d{2})((a|p)m)#', '\1 \2:\3\4', $event->post_name ) ) );
			$start = QSOT_Utils::to_c( $start );
			$earliest = min( strtotime( $start ), $earliest );
			$end = isset( $meta[ self::$o->{'meta_key.end'} ] )
					? $meta[ self::$o->{'meta_key.end'} ]
					: date( 'Y-m-d H:i:s', strtotime( '+1 hour', $start ) );
			$end = QSOT_Utils::to_c( $end );

			// add an item to the list, by transposing the loaded settings for this sub event over the list of default settings, and then allowing sub/external plugins to modify them
			// to add their own settings for the interface.
			$list[] = apply_filters( 'qsot-load-child-event-settings', wp_parse_args( array(
				'start' => $start,
				'status' => in_array( $event->post_status, array( 'hidden', 'private' ) ) ? 'publish' : $event->post_status,
				'visibility' => in_array( $event->post_status, array( 'hidden', 'private' ) ) ? $event->post_status : ( $event->post_password ? 'protected' : 'public' ),
				'password' => $event->post_password,
				'pub_date' => $event->post_date,
				'capacity' => isset( $meta[ self::$o->{'meta_key.capacity'} ] ) ? $meta[ self::$o->{'meta_key.capacity'} ] : 0,
				'end' => $end,
				'post_id' => $event->ID,
				'edit_link' => get_edit_post_link( $event->ID ),
				'view_link' => get_permalink( $event->ID ),
				'purchase_limit' => get_post_meta( $event->ID, self::$o->{'meta_key.purchase_limit'}, true ),
			), $defs ), $defs, $event );
		}

		// return the generated list
		return array($list, $earliest == PHP_INT_MAX ? '' : date('Y-m-d H:i:s', $earliest));
	}

	// generate the core templates used by the event ui js
	protected static function _ui_templates($post_id) {
		$list = array();

		// default individual event block on any view without a specifically defined template
		$list['render_event'] = '<div class="'.self::$o->fctm.'-event-item">'
				.'<div class="'.self::$o->fctm.'-event-item-header">'
					.'<span class="'.self::$o->fctm.'-event-time"></span>' // time of the event
					.'<span class="'.self::$o->fctm.'-separator"> </span>'
					.'<span class="'.self::$o->fctm.'-capacity"></span>' // event max occupants
					.'<span class="'.self::$o->fctm.'-separator"> </span>'
					.'<span class="'.self::$o->fctm.'-visibility"></span>' // status
					.'<div class="remove" rel="remove">X</div>' // remove button
				.'</div>'
				.'<div class="'.self::$o->fctm.'-event-item-content">'
					.'<span class="'.self::$o->fctm.'-event-title"></span>' // title of the event
				.'</div>'
				.'<div class="'.self::$o->fctm.'-event-item-footer">'
				.'</div>'
			.'</div>';

		// extended, slightly modified version of the above template, specifically for the agendaWeek view of the calendar
		$list['render_event_agendaWeek'] = '<div class="'.self::$o->fctm.'-event-item">'
				.'<div class="'.self::$o->fctm.'-event-item-header">'
					.'<span class="'.self::$o->fctm.'-event-time"></span>' // time range of the event, in extended form
					.'<div class="remove" rel="remove">X</div>' // remove button
				.'</div>'
				.'<div class="'.self::$o->fctm.'-event-item-content">'
					.'<div class="'.self::$o->fctm.'-section">'
						.'<span class="'.self::$o->fctm.'-event-title"></span>' // event title
					.'</div>'
					.'<div class="'.self::$o->fctm.'-section">'
						.'<span>'.__('Max','opentickets-community-edition').': </span><span class="'.self::$o->fctm.'-capacity"></span>' // event max occupants
					.'</div>'
					.'<div class="'.self::$o->fctm.'-section">'
						.'<span>'.__('Status','qsot').': </span><span class="'.self::$o->fctm.'-visibility"></span>' // status
					.'</div>'
					.apply_filters('qsot-render-event-agendaWeek-template-details', '', $post_id)
				.'</div>'
				.'<div class="'.self::$o->fctm.'-event-item-footer">'
				.'</div>'
			.'</div>';

		// allow sub/external plugins to modify this list if they wish
		return apply_filters('qsot-ui-templates', $list, $post_id);
	}

	// when rendering the title for an event, see if the date/tiem is required. if so, append it
	public static function the_title_date_time( $title, $post_id=0 ) {
		// if the post_id is not supplied, then there is no way to know if we could possibly need this
		if ( (int)$post_id <= 0 )
			return $title;

		// if the post is not an event, then bail
		if ( self::$o->core_post_type != get_post_type( $post_id ) )
			return $title;

		// if this is not a child event, then bail, because parent posts dont need dates or times
		if ( wp_get_post_parent_id( $post_id ) <= 0 )
			return $title;

		// figure out if the date or time is needed. if not, bail
		$needs = apply_filters( 'qsot-show-date-time', array( 'date' => true, 'time' => false ), $post_id );
		if ( ! $needs['date'] && ! $needs['time'] )
			return $title;

		// otherwise figure out what bits need to be added
		$start = QSOT_Utils::local_timestamp( get_post_meta( $post_id, '_start', true ) );
		// bail if the date is invalid
		if ( 0 == $start )
			return $title;

		// add the date/time to the title
		$date = date( __( 'm/d/Y', 'opentickets-community-edition' ), $start );
		$time = date( __( 'g:ia', 'opentickets-community-edition' ), $start );
		$format = '%1$s';
		if ( $needs['date'] )
			$format .= ' ' . __( 'on %2$s', 'opentickets-community-edition' );
		if ( $needs['time'] )
			$format .= ' ' . __( '@ %3$s', 'opentickets-community-edition' );
		
		return sprintf( $format, $title, $date, $time );
	}

	// fetch the current settings for a sub-event's 'show_date' and 'show_time' settings
	public static function get_show_date_time( $current, $post_id ) {
		// get the cache for this, if it has already been fetched, cause this can get overly heavy
		$current = false; //wp_cache_get( 'show-date-time-' . $post_id, 'qsot' );
		if ( false && false !== $current )
			return $current;

		// if we are on the page load of an admin page, then always show date and time
		if ( is_admin() && ! ( defined( 'DOING_AJAX' ) && DOING_AJAX ) )
			return array( 'date' => true, 'time' => true );

		// find the current values
		$current = array(
			'date' => get_post_meta( $post_id, '_qsot_show_date', true ),
			'time' => get_post_meta( $post_id, '_qsot_show_time', true ),
		);

		// if there is no value for either, then try to determine it from the parent
		if ( '' === $current['date'] || '' === $current['time'] ) {
			$parent_id = wp_get_post_parent_id( $post_id );
			if ( '' === $current['date'] )
				$current['date'] = get_post_meta( $parent_id, '_qsot_show_date', true );
			if ( '' === $current['time'] )
				$current['time'] = get_post_meta( $parent_id, '_qsot_show_time', true );
		}

		// if we are still empty, default to global options
		if ( '' === $current['date'] || '' === $current['time'] ) {
			if ( '' === $current['date'] )
				$current['date'] = 'yes' === self::$options->{'qsot-show-date'};
			if ( '' === $current['time'] )
				$current['time'] = 'yes' === self::$options->{'qsot-show-time'};
		}

		$final = array( 'date' => !!$current['date'], 'time' => !!$current['time'] );

		// update the cache
		wp_cache_set( 'show-date-time-' . $post_id, $final, 'qsot', DAY_IN_SECONDS );

		return $final;
	}

	// save function for the parent events
	public static function save_event( $post_id, $post ) {
		if ( $post->post_type != self::$o->core_post_type ) return; // only run for our event post type
		if ( $post->post_parent != 0 ) return; // this is only for parent event posts

		// if there were settings for the sub events sent, then process those settings, on the next action
		// on next action because apparently recursive calls to save_post action causes the outermost loop to skip everything after the function that caused the recursion
		if ( isset( $_POST['_qsot_event_settings'], $_POST['_qsot_event_settings'] ) )
			add_action( 'wp_insert_post', array( __CLASS__, 'save_sub_events' ), 100, 3 );

		// if the 'show date' and 'show time' settings are present, update them as needed, on the next action
		// on next action because apparently recursive calls to save_post action causes the outermost loop to skip everything after the function that caused the recursion
		if ( isset( $_POST['qsot-event-title-settings'] ) && wp_verify_nonce( $_POST['qsot-event-title-settings'], 'qsot-event-title' ) )
			add_action( 'wp_insert_post', array( __CLASS__, 'save_event_title_settings' ), 100, 3 );
	}

	// save the event title settings from the 'Event Titles' metabox
	public static function save_event_title_settings( $post_id, $post, $was_updated ) {
		remove_action( 'wp_insert_post', array( __CLASS__, 'save_event_title_settings' ), 100 );
		$data = $_POST;

		// figure out the current settings
		//$current = apply_filters( 'qsot-show-date-time', array( 'date' => true, 'time' => false ), $post_id );

		// figure out the new settings
		//   true = yes show the date/time
		//   false = no do not show the date/time
		//   '' = use the global settings
		$new_settings = array(
			'date' => isset( $data['qsot-show-date'] ) && '' !== $data['qsot-show-date'] && $data['qsot-show-date'] ? !!$data['qsot-show-date'] : '',
			'time' => isset( $data['qsot-show-time'] ) && '' !== $data['qsot-show-time'] && $data['qsot-show-time'] ? !!$data['qsot-show-time'] : '',
		);

		update_post_meta( $post_id, '_qsot_show_date', $new_settings['date'] );
		update_post_meta( $post_id, '_qsot_show_time', $new_settings['time'] );

		// clear the cache
		wp_cache_delete( 'show-date-time-' . $post_id, 'qsot' );
	}

	// handle the saving of sub events, when a parent event is saved in the admin
	public static function save_sub_events( $post_id, $post, $was_updated ) {
		remove_action( 'wp_insert_post', array( __CLASS__, 'save_sub_events' ), 100 );
		$data = $_POST;

		// expand the json data
		foreach ( $data['_qsot_event_settings'] as $ind => $item )
			$data['_qsot_event_settings'][ $ind ] = @json_decode( stripslashes( $item ) );

		do_action( 'qsot-save-sub-events', $post_id, $post, $data, current_user_can( 'publish_posts' ) );
	}

	// actually does the saving of sub events
	public static function handle_save_sub_events( $post_id, $post, $data, $can_pub=null ) {
		$need_lookup = $deletes = $updates = $matched = array();
		$at_least_one_new = false;
		// default post_arr to send to wp_insert_post
		$defs = array(
			'post_type' => self::$o->core_post_type,
			'post_status' => 'pending',
			'post_password' => '',
			'post_parent' => $post_id,
		);
		$now = time();

		$can_pub = null !== $can_pub ? $can_pub : current_user_can( 'publish_posts' );

		// cycle through all the subevent settings that were sent. some will be new, some will be modified, some will be modified but have lost their post id. determine what each is,
		// and properly group them for possible later processing
		foreach ( $data['_qsot_event_settings'] as $item ) {
			// expand the settings
			$tmp = ! is_scalar( $item ) ? $item : @json_decode( stripslashes( $item ) );

			// if the settings are a valid set of settings, then continue with this item
			if ( is_object( $tmp ) ) {
				// change the title to be the date, which makes for better permalinks
				$tmp->title = date( _x( 'Y-m-d_gia', 'Permalink date', 'opentickets-community-edition' ), QSOT_Utils::local_timestamp( $tmp->start ) );
				// if the post_id was passed in with the settings, then we know what subevent post to modify with these settings already. therefore we do not need to match it up to an existing
				// subevent or create a new subevent. lets throw it directly into the update list for usage later
				if ( isset( $tmp->post_id ) && is_numeric( $tmp->post_id ) && $tmp->post_id > 0 ) {
					// if the post is marked as having been deleted, then add it to the deletes list
					if ( isset( $tmp->deleted ) && is_numeric( $tmp->deleted ) && 1 == $tmp->deleted ) {
						$deletes[] = $tmp->post_id;
					// otherwise, add it to the updates list
					} else {
						// load the existing post, so that we can fetch the original author and content
						$orig = get_post( $tmp->post_id );

						// parse the date so that we can use it to make a proper post_title
						$d = strtotime( $tmp->start );

						// if the post is set to publish in the future, then adjust the status
						$pub = strtotime( $tmp->pub_date );
						if ( $pub > $now ) $tmp->status = 'future';

						// restrict publish to only those who have permissions
						$tmp->status = 'publish' == $tmp->status && ! $can_pub ? 'pending' : $tmp->status;

						// add the settings to the list of posts to update
						$update_item = array(
							'post_arr' => wp_parse_args( array(
								// be sure to set the id of the post to update, otherwise we get a completely new post
								'ID' => $tmp->post_id,
								// use the title from the parent event. later, during display, the date and time will be added to the title if needed
								'post_title' => $post->post_title,
								// set the post status of the event
								'post_status' => in_array( $tmp->visibility, array( 'public', 'protected' ) ) ? $tmp->status : $tmp->visibility,
								// protected events have passwords
								'post_password' => 'protected' == $tmp->visibility ? $tmp->password : '',
								// use that normalized title we made earlier, as to create a pretty url
								'post_name' => $orig->post_name, //$tmp->title,
								'post_date' => $tmp->pub_date == '' || $tmp->pub_date == 'now' ? '' : date_i18n( 'Y-m-d H:i:s', strtotime( $tmp->pub_date ) ),
								// use the original author and content, so that they are not overridden
								'post_content' => $orig->post_content,
								'post_author' => $orig->post_author,
							), $defs),
							'meta' => array( // setup the meta to save
								self::$o->{'meta_key.capacity'} => $tmp->capacity, // max occupants
								self::$o->{'meta_key.end'} => $tmp->end, // end time, for lookup and display purposes later
								self::$o->{'meta_key.start'} => $tmp->start, // start time for lookup and display purposes later
								self::$o->{'meta_key.purchase_limit'} => $tmp->purchase_limit, // the specific child event purchase limit
							),
							'submitted' => $tmp,
						);
						$updates[] = $update_item;
						$matched[] = $tmp->post_id; // track the post_ids we have matched up to settings. will use later to determine subevents to delete
					}
				// if no post id was passed, then we need to attempt to match this item up to an existing subevent
				} else {
					// add this item to the needs lookup list
					$need_lookup[ $tmp->title ] = $tmp;
				}
			}
		}

		// if there are subevent settings that did not contain a post_id, we need to attempt to match them up to existing, unmatched subevents of this event
		if ( count( $need_lookup ) > 0 ) {
			// args for looking up existing subevents to this event
			$args = array(
				'post_type' => self::$o->core_post_type, // of event post type
				'post_parent' => $post_id, // is a child of this event
				'post_status' => 'any', // any status, including our special hidden status
				'posts_per_page' => -1, // lookup all of them, not just some
			);

			// if any hve yet been matched, exclude them from the lookup
			if ( ! empty( $matched ) )
				$args['post__not_in'] = $matched;

			// fetch the list of existing, unmatched subevents
			$existing = get_posts( $args );

			// if there are any existing, unmatched subevents, then
			if ( is_array( $existing ) && count( $existing ) ) {
				// cycle through the list of them
				foreach ( $existing as $exist ) {
					// if the name of this subevent match any of the normalized names of the passed subevent settings, then lets assume that they are a match, so that we dont needlessly create extra
					// subevents just because we are missing a post_id above
					if ( isset( $need_lookup[ $exist->post_name ] ) ) {
						$tmp = $need_lookup[ $exist->post_name ];
						// get the date in timestamp form so that we can use it to make a pretty title
						$d = strtotime( $tmp->start );
						// if the post is set to publish in the future, then adjust the status
						$pub = strtotime( $tmp->pub_date );
						if ( $pub > $now ) $tmp->status = 'future';

						// restrict publish to only those who have permissions
						$tmp->status = 'publish' == $tmp->status && ! $can_pub ? 'pending' : $tmp->status;

						// remove the settings from the list that needs a match up, since we just matched it
						unset( $need_lookup[ $exist->post_name ] );
						// add the settings to the list of posts to update
						$updates[] = array(
							'post_arr' => wp_parse_args( array(
								// be sure to set the post_id so that we don't create a new post
								'ID' => $exist->ID,
								// use the title of the parent post as the base name, and then add the date/time when needed later
								'post_title' => $post->post_title,
								// use the normalized event slug for pretty urls
								'post_name' => $exist->post_name, //$tmp->title,
								// set the post status of the event
								'post_status' => in_array( $tmp->visibility, array( 'public', 'protected' ) ) ? $tmp->status : $tmp->visibility,
								// protected events have passwords
								'post_password' => 'protected' == $tmp->visibility ? $tmp->password : '',
								// update to the proper publish date
								'post_date' => $tmp->pub_date == '' || $tmp->pub_date == 'now' ? '' : date_i18n( 'Y-m-d H:i:s', strtotime( $tmp->pub_date ) ),
								// use the original author and content
								'post_content' => $exist->post_content,
								'post_author' => $exist->post_author,
							), $defs ),
							'meta' => array( // set the meta
								self::$o->{'meta_key.capacity'} => $tmp->capacity, // occupant capacity
								self::$o->{'meta_key.end'} => $tmp->end, // event end date/time for later lookup and display
								self::$o->{'meta_key.start'} => $tmp->start, // event start data/time for later lookup and display
								self::$o->{'meta_key.purchase_limit'} => $tmp->purchase_limit, // the specific child event purchase limit
							),
							'submitted' => $tmp,
						);
						$matched[] = $exist->ID; // mark as matched
					}
				}
			}

			// if there are still un matched sub event settings (always will be on new events with sub events)
			if ( count( $need_lookup ) ) {
				// cycle through them
				foreach ( $need_lookup as $k => $item ) {
					$at_least_one_new = true;
					// get the date in timestamp form so that we can use it to make a pretty title
					$d = strtotime( $item->start );
					// if the post is set to publish in the future, then adjust the status
					$pub = strtotime( $item->pub_date );
					if ( $pub > $now ) $item->status = 'future';

					// restrict publish to only those who have permissions
					$item->status = 'publish' == $item->status && ! $can_pub ? 'pending' : $item->status;

					// add the settings to the list of posts to update/insert
					$updates[] = array(
						'post_arr' => wp_parse_args( array( // will INSERT because there is no post_id
							// use the parent event title, and then add the date and time as needed later
							'post_title' => $post->post_title,
							// user pretty url slug
							'post_name' => $item->title,
							// set the post status of the event
							'post_status' => in_array( $item->visibility, array( 'public', 'protected' ) ) ? $item->status : $item->visibility,
							// protected events have passwords
							'post_password' => 'protected' == $item->visibility ? $item->password : '',
							// set the appropriate publish date
							'post_date' => $item->pub_date == '' || $item->pub_date == 'now' ? '' : date_i18n( 'Y-m-d H:i:s', strtotime( $item->pub_date ) ),
							// use the same post_author as the parent post
							'post_author' => $post->post_author,
						), $defs ),
						'meta' => array( // set meta
							self::$o->{'meta_key.capacity'} => $item->capacity, // occupant copacity
							self::$o->{'meta_key.end'} => $item->end, // end data for lookup and display
							self::$o->{'meta_key.start'} => $item->start, // start date for lookup and display
							self::$o->{'meta_key.purchase_limit'} => $tmp->purchase_limit, // the specific child event purchase limit
						),
						'submitted' => $item,
					);
				}
			}
		}

		// if the event ui has marked that there have been events removed, then remove any unmatched events, assuming that any we did not get settings for, should be removed.
		if ( isset( $data['events-removed'] ) && $data['events-removed'] == 1 ) {
			// remove non-matched/non-updated sub posts before updating existing posts and creating new ones
			// args to lookup posts to remove
			$args = array(
				'post_type' => self::$o->core_post_type, // must be an event
				'post_parent' => $post_id, // and a child of the current event
				'post_status' => 'any', // can be of any status, even our special ones
				'posts_per_page' => -1, // fetch a comprehensive list
				'fields' => 'ids', // return only a list of ids
			);

			// only fetch the unmatched ones
			if ( ! empty( $matched ) )
				$args['post__not_in'] = $matched;

			// get the list of unmatched items, which are to be deleted
			$additional_deletes = get_posts( $args );

			// add each found item to the official deletes list
			foreach ( $additional_deletes as $delete )
				$deletes[] = $delete;
		}

		$deletes = array_filter( array_unique( $deletes ) );
		// if there are any posts in the list designated for deletion, then delete them now
		if ( ! empty( $deletes ) && is_array( $deletes ) ) {
			// delete all posts in the list
			foreach ( $deletes as $del_id ) {
				if ( current_user_can( 'delete_post', $del_id ) && apply_filters( 'qsot-event-can-be-deleted', true, $del_id ) ) {
					wp_delete_post( $del_id, true );

					do_action( 'qsot-events-delete-sub-event', $del_id );
				}
			}
		}

		// for every item in the update list, either update or create a subevent
		foreach ( $updates as $update ) {
			// allow injection/modification of the insert/update data
			$update = apply_filters( 'qsot-events-save-sub-event-settings', $update, $post_id, $post );

			// if there is data to update, then
			if ( isset( $update['post_arr'] ) && is_array( $update['post_arr'] ) ) {
				// insert/update the sub-event
				$event_id = wp_insert_post( $update['post_arr'] );

				// if the update was a success, then
				if ( is_numeric( $event_id ) ) {
					// update/add the meta to the new subevent
					foreach ( $update['meta'] as $k => $v )
						update_post_meta( $event_id, $k, $v );

					// keep track of the earliest start time of all sub events
					if ( isset( $update['meta'][ self::$o->{'meta_key.start'} ] ) )
						$start_date = empty( $start_date )
								? strtotime( $update['meta'][ self::$o->{'meta_key.start'} ] )
								: min( $start_date, strtotime( $update['meta'][ self::$o->{'meta_key.start'} ] ) );

					// keep track of the latest end time of all sub events
					if ( isset( $update['meta'][ self::$o->{'meta_key.end'} ] ) )
						$end_date = empty( $end_date )
								? strtotime( $update['meta'][ self::$o->{'meta_key.end'} ] )
								: max( $end_date, strtotime( $update['meta'][ self::$o->{'meta_key.end'} ] ) );

					// notify externals of an update to the sub event
					do_action( 'qsot-events-save-sub-event', $event_id, $update, $post_id, $post );
				}
			}
		}

		// update the start and end time fo the parent event
		$current_start = get_post_meta( $post_id, self::$o->{'meta_key.start'}, true );
		$current_end = get_post_meta( $post_id, self::$o->{'meta_key.end'}, true );

		// calculate the real start and end times over all child events
		@list( $actual_start, $actual_end ) = apply_filters( 'qsot-event-date-range', array(), array( 'event_id' => $post_id ) );

		$submit_start_date = $submit_end_date = array();
		if ( ! $at_least_one_new ) {
			if ( isset( $data['_qsot_start_date'] ) && ! empty( $data['_qsot_start_date'] ) ) $submit_start_date[] = $data['_qsot_start_date'];
			if ( isset( $data['_qsot_start_time'] ) && ! empty( $data['_qsot_start_time'] ) ) $submit_start_date[] = $data['_qsot_start_time'];
			if ( isset( $data['_qsot_end_date'] ) && ! empty( $data['_qsot_end_date'] ) ) $submit_end_date[] = $data['_qsot_end_date'];
			if ( isset( $data['_qsot_end_time'] ) && ! empty( $data['_qsot_end_time'] ) ) $submit_end_date[] = $data['_qsot_end_time'];
		}

		$submit_start_date = count( $submit_start_date ) == 2 ? implode( ' ', $submit_start_date ) : '';
		$submit_end_date = count( $submit_end_date ) == 2 ? implode( ' ', $submit_end_date ) : '';

		// if new start an end times did not get submitted (or the submitted value is not changed from what it was) then update the value to the real start and end time, based on all child events
		if ( $submit_start_date == $current_start && $current_start != $actual_start )
			$submit_start_date = $actual_start;
		if ( $submit_end_date == $current_end && $current_end != $actual_end )
			$submit_end_date = $actual_end;

		// if we have a min start time and max end time, then save them to the main parent event, for use in lookup of the featured event ordering
		if ( ! empty( $submit_start_date ) )
			update_post_meta( $post_id, self::$o->{'meta_key.start'}, $submit_start_date );
		else if ( ! empty( $actual_start ) )
			update_post_meta( $post_id, self::$o->{'meta_key.start'}, $actual_start );

		if ( ! empty( $submit_end_date ) )
			update_post_meta( $post_id, self::$o->{'meta_key.end'}, $submit_end_date );
		else if ( ! empty( $actual_end ) )
			update_post_meta( $post_id, self::$o->{'meta_key.end'}, $actual_end );

		// save the stop selling formula
		$formula = isset( $data['_stop_sales_before_show'] ) ? $data['_stop_sales_before_show'] : get_option( 'qsot-stop-sales-before-show', '2 hours' );
		update_post_meta( $post_id, '_stop_sales_before_show', $formula );

		// save the hard_stop time
		$hard_stop = trim( implode( ' ', array(
			( isset( $data['_stop_sales_hard_stop_date'] ) ? $data['_stop_sales_hard_stop_date'] : '' ),
			( isset( $data['_stop_sales_hard_stop_time'] ) ? $data['_stop_sales_hard_stop_time'] : '' ),
		) ) );
		update_post_meta( $post_id, '_stop_sales_hard_stop', $hard_stop );

		if ( isset( $_POST['_qsot_ticket_sales_settings'] ) && wp_verify_nonce( $_POST['_qsot_ticket_sales_settings'], 'save-ticket-sales-settings' ) ) {
			// mapped list of settings to their post meta and site options
			$map = array(
				'formula' => array( '_stop_sales_before_show', 'qsot-stop-sales-before-show' ),
				'purchase_limit' => array( self::$o->{'meta_key.purchase_limit'}, 'qsot-event-purchase-limit' ),
			);

			// cycle through the map. deterine the appropriate value to save. then update the setting as such
			foreach ( $map as $k => $pair ) {
				@list( $meta_key, $option_name ) = $pair;
				// figure out the appropriate value
				$value = isset( $_POST[ $meta_key ] ) ? $_POST[ $meta_key ] : ''; //( ! empty( $option_name ) ?  apply_filters( 'qsot-get-option-value', '', $option_name ) : '' );

				// update the post meta to reflect the correct value
				update_post_meta( $post_id, $meta_key, $value );
			}
		}
	}

	// add meta boxes on the edit event pages
	public static function core_setup_meta_boxes( $post_type ) {
		global $post;

		// some only belong on parent event edit pages
		if ( is_object( $post ) && $post->post_parent == 0 ) {
			// metabox for assigning child events. contains calendar and event list
			add_meta_box(
				'event-date-time',
				_x( 'Event Date Time Settings', 'metabox title', 'opentickets-community-edition' ),
				array( __CLASS__, 'mb_event_date_time_settings' ),
				self::$o->core_post_type,
				'normal',
				'high'
			);

			// allows control to show or not show the date and time of child events when they are shown on their details pages on the frontend
			add_meta_box(
				'qsot-child-show-date-time',
				_x( 'Event Titles', 'metabox title', 'opentickets-community-edition' ),
				array( __CLASS__, 'mb_event_title_settings' ),
				self::$o->core_post_type,
				'side',
				'core'
			);
			
			// allows controlling of when to stop sales to an event online. applies to all child events
			add_meta_box(
				'qsot-ticket-sales-settings',
				_x( 'Ticket Sales Settings', 'metabox title', 'opentickets-community-edition' ),
				array( __CLASS__, 'mb_ticket_sales_settings' ),
				self::$o->core_post_type,
				'side',
				'core'
			);

			// allows specification of when the events (includes child events) start and stop. controls chronology of dislay order
			add_meta_box(
				'event-run-date-range',
				_x( 'Event Run Date Range', 'metabox title', 'opentickets-community-edition' ),
				array( __CLASS__, 'mb_event_run_date_range' ),
				self::$o->core_post_type,
				'side',
				'core'
			);
		// setup the child event metaboxes
		/*
		} else if ( is_object( $post ) && 0 != $post->post_parent ) {
			add_meta_box(
				'qsot-single-event-settings',
				_x( 'Event Settings', 'metabox title', 'opentickets-community-edition' ),
				array( __CLASS__, 'mb_single_event_settings' ),
				self::$o->core_post_type,
				'normal',
				'high'
			);
		*/
		}
	}

	// metabox for editing a single event's settings
	public static function mb_single_event_settings( $post, $mb ) {
		// adjust the start and end times for our WP offset setting
		$start_raw = QSOT_Utils::gmt_timestamp( get_post_meta( $post->ID, '_start', true ) );
		$end_raw = QSOT_Utils::gmt_timestamp( get_post_meta( $post->ID, '_end', true ) );

		// create the various date parts
		$start = date( 'c', $start_raw );
		$start_time = date( 'H:i:s', $start_raw );
		$end = date( 'c', $end_raw );
		$end_time = date( 'H:i:s', $end_raw );
	}

	// render the metabox that allows control over whether event titles include the date and time
	public static function mb_event_title_settings( $post, $mb ) {
		// load the current settings
		$current = array(
			'show_date' => get_post_meta( $post->ID, '_qsot_show_date', true ),
			'show_time' => get_post_meta( $post->ID, '_qsot_show_time', true ),
		);

		$dis_date = ( '' === $current['show_date'] ) ? 'disabled="disabled"' : '';
		$dis_time = ( '' === $current['show_time'] ) ? 'disabled="disabled"' : '';

		// render the form fields
		?>
			<div class="qsot-mb">
				<input type="hidden" name="qsot-event-title-settings" value="<?php echo esc_attr( wp_create_nonce( 'qsot-event-title' ) ) ?>" />

				<div class="field">
					<label><?php _ex( 'Show Date', 'metabox field heading', 'opentickets-community-edition' ) ?></label>
					<input type="hidden" name="qsot-show-date" value="" />

					<div class="option-row">
						<span class="cb-wrap">
							<input type="checkbox"name="qsot-show-date" value="" <?php checked( !!$dis_date, true ) ?> data-toggle-disabled="[role='date-option']" scope=".field" />
							<span class="cb-text"><?php _ex( 'Use the site wide default setting', 'checkbox description', 'opentickets-community-edition' ) ?></span>
						</span><br/>
					</div>

					<div class="option-row">
						<span class="cb-wrap">
							<input role="date-option" type="radio" name="qsot-show-date" value="1" <?php checked( !!$current['show_date'], true ); echo $dis_date; ?> />
							<span class="cb-text"><?php _ex( 'Yes, show the date', 'radio button description', 'opentickets-community-edition' ) ?></span>
						</span>
						<span class="cb-wrap">
							<input role="date-option" type="radio" name="qsot-show-date" value="0" <?php checked( !!$current['show_date'], false ); echo $dis_date; ?> />
							<span class="cb-text"><?php _ex( 'No, hide the date', 'radio button description', 'opentickets-community-edition' ) ?></span>
						</span>
					</div>

					<div class="helper"><?php echo sprintf( _x(
						'Decided whether to use the global "%s" setting, or if you want to specify a specific value for these events.',
						'field helper text',
						'opentickets-community-edition'
					), _x( 'Show Date', 'option name', 'opentickets-community-edition' ) ) ?></div>
				</div>

				<div class="field">
					<label><?php _ex( 'Show Time', 'metabox field heading', 'opentickets-community-edition' ) ?></label>
					<input type="hidden" name="qsot-show-time" value="" />

					<div class="option-row">
						<span class="cb-wrap">
							<input type="checkbox"name="qsot-show-time" value="" <?php checked( !!$dis_time, true ) ?> data-toggle-disabled="[role='time-option']" scope=".field" />
							<span class="cb-text"><?php _ex( 'Use the site wide default setting', 'checkbox description', 'opentickets-community-edition' ) ?></span>
						</span>
					</div>

					<div class="option-row">
						<span class="cb-wrap">
							<input role="time-option" type="radio" name="qsot-show-time" value="1" <?php checked( !!$current['show_time'], true ); echo $dis_time; ?> />
							<span class="cb-text"><?php _ex( 'Yes, show the time', 'radio button description', 'opentickets-community-edition' ) ?></span>
						</span>
						<span class="cb-wrap">
							<input role="time-option" type="radio" name="qsot-show-time" value="0" <?php checked( !!$current['show_time'], false ); echo $dis_time; ?> />
							<span class="cb-text"><?php _ex( 'No, hide the time', 'radio button description', 'opentickets-community-edition' ) ?></span>
						</span>
					</div>

					<div class="helper"><?php echo sprintf( _x(
						'Decided whether to use the global "%s" setting, or if you want to specify a specific value for these events.',
						'field helper text',
						'opentickets-community-edition'
					), _x( 'Show Time', 'option name', 'opentickets-community-edition' ) ) ?></div>
				</div>
			</div>
		<?php
	}

	// metabox to allow control of various settings directly dealing with the selling of tickets
	public static function mb_ticket_sales_settings( $post, $mb ) {
		// mapped list of settings to their post meta and site options
		$map = array(
			'formula' => array( '_stop_sales_before_show', 'qsot-stop-sales-before-show' ),
			'hard_stop' => array( '_stop_sales_hard_stop', '' ),
			'purchase_limit' => array( self::$o->{'meta_key.purchase_limit'}, 'qsot-event-purchase-limit' ),
		);

		$options = array();
		// cycle through the map and derive the appropriate current value for the option
		foreach ( $map as $k => $pair ) {
			list( $meta_key, $option_name ) = $pair;
			// check the parent event for a defined setting
			$value = get_post_meta( $post->ID, $meta_key, true );

			// if there is not one set, then check for a global setting if there is one
			//if ( empty( $value ) && ! empty( $option_name ) )
				//$value = apply_filters( 'qsot-get-option-value', $value, $option_name );

			// register whatever value we found
			$options[ $k ] = $value;
		}

		@list( $options['hard_stop_date'], $options['hard_stop_time'] ) = empty( $options['hard_stop'] ) ? array( '', '' ) : explode( ' ', $options['hard_stop'], 2 );

		?>
			<div class="qsot-mb">
				<input type="hidden" name="_qsot_ticket_sales_settings" value="<?php echo wp_create_nonce( 'save-ticket-sales-settings' ) ?>" />

				<div class="field">
					<div class="label"><label><?php _e( 'Purchase Limit', 'opentickets-community-edition' ) ?></label></div>
					<input type="number" step="1" class="widefat" name="<?php echo esc_attr( self::$o->{'meta_key.purchase_limit'} ) ?>" value="<?php echo esc_attr( $options['purchase_limit'] ) ?>" />

					<div class="helper">
						<?php _e( 'The maximum number of tickets, for each child event, that a given cart can have in it. To use the site-wide global setting, use "0". To force no-limit, use "-1".', 'opentickets-community-edition' ) ?>
					</div>
				</div>

				<div class="field">
					<div class="label"><label><?php echo __( 'Stop Sales Before - Formula', 'opentickets-community-edition' ); ?>:</label></div>
					<input type="text" class="widefat" name="_stop_sales_before_show" value="<?php echo esc_attr( $options['formula'] ) ?>" />

					<div class="helper">
						<?php _e( 'This is the formula to calculate when tickets should stop being sold on the frontend for this show. For example, if you wish to stop selling tickets 2 hours and 30 minutes before the show, use: <code>2 hours 30 minutes</code>. Valid units include: hour, hours, minute, minutes, second, seconds, day, days, week, weeks, month, months, year, years. Leave the formula empty to just use the Global Setting for this formula.', 'opentickets-community-edition' ); ?>
					</div>
				</div>

				<div class="field">
					<div class="label"><label><?php echo __( 'Stop Sales - Hard Stop', 'opentickets-community-edition' ); ?>:</label></div>
					<table cellspacing="0">
						<tbody>
							<tr>
								<td width="60%">
									<input type="text" class="use-i18n-datepicker widefat" name="_stop_sales_hard_stop_date" value=""
											data-init-date="<?php echo $options['hard_stop'] ? esc_attr( date( 'Y-m-d\TH:i:s', self::_offset( strtotime( $options['hard_stop'] ), -1 ) ) . '+0000' ) : '' ?>" scope="td"
											data-display-format="<?php echo esc_attr( __( 'mm-dd-yy', 'opentickets-community-edition' ) ) ?>" data-allow-blank="1" />
								</td>
								<td width="1%"><?php _e( '@', 'opentickets-community-edition' ) ?></td>
								<td width="39%">
									<input type="text" class="widefat use-timepicker" name="_stop_sales_hard_stop_time" value="<?php echo esc_attr( $options['hard_stop_time'] ) ?>" />
								</td>
							</tr>
						</tbody>
					</table>
					<div class="helper">
						<?php _e( 'This is the date and time, after which no tickets to this event should be sold.', 'opentickets-community-edition' ); ?>
					</div>
				</div>

			</div>
		<?php
	}

	// allows control over the start and end time of a run of events
	public static function mb_event_run_date_range( $post, $mb ) {
		// adjust the start and end times for our WP offset setting
		$start = QSOT_Utils::to_c( get_post_meta( $post->ID, '_start', true ) );
		$end = QSOT_Utils::to_c( get_post_meta( $post->ID, '_end', true ) );
		$start_raw = QSOT_Utils::gmt_timestamp( $start, 'from' );
		$end_raw = QSOT_Utils::gmt_timestamp( $end, 'from' );

		// create the various date parts
		$start_time = $start_raw > 0 ? date_i18n( 'H:i:s', $start_raw ) : '00:00:00';
		$end_time = $end_raw > 0 ? date_i18n( 'H:i:s', $end_raw ) : '00:00:00';

		?>
			<div class="qsot-mb">
				<div class="field">
					<label><?php _e( 'Start Date/Time', 'opentickets-community-edition' ) ?>:</label>
					<table cellspacing="0">
						<tbody>
							<tr>
								<td width="60%">
									<input type="text" class="use-i18n-datepicker widefat" name="_qsot_start_date" value="" data-init-date="<?php echo esc_attr( $start ) ?>" scope="td"
											data-display-format="<?php echo esc_attr( __( 'mm-dd-yy', 'opentickets-community-edition' ) ) ?>" />
								</td>
								<td width="1%"><?php _e( '@', 'opentickets-community-edition' ) ?></td>
								<td width="39%">
									<input type="text" class="widefat use-timepicker" name="_qsot_start_time" value="<?php echo esc_attr( $start_time ) ?>" />
								</td>
							</tr>
						</tbody>
					</table>
				</div>
				<div class="field">
					<label><?php _e( 'End Date/Time:', 'opentickets-community-edition' ) ?></label>
					<table cellspacing="0">
						<tbody>
							<tr>
								<td width="60%">
									<input type="text" class="use-i18n-datepicker widefat" name="_qsot_end_date" value="" data-init-date="<?php echo esc_attr( $end ) ?>" scope="td"
											data-display-format="<?php echo esc_attr( __( 'mm-dd-yy', 'opentickets-community-edition' ) ) ?>" />
								</td>
								<td width="1%"><?php _e('@','opentickets-community-edition') ?></td>
								<td width="39%">
									<input type="text" class="widefat use-timepicker" name="_qsot_end_time" value="<?php echo esc_attr($end_time) ?>" />
								</td>
							</tr>
						</tbody>
					</table>
				</div>
			</div>
		<?php
	}

	public static function mb_event_date_time_settings($post, $mb) {
		$now = current_time( 'timestamp' );
		$end = strtotime( '+1 hour', $now );
		$one_week = strtotime( '+1 week', $now );
		?>
			<div class="<?php echo self::$o->pre ?>event-date-time-wrapper events-ui">
				<input type="hidden" name="save-recurrence" value="1"/>
				<div class="option-scope">
					<div class="option-sub above hide-if-js" rel="add">
						<table class="event-date-time-settings settings-table">
							<tbody>
								<tr>
									<td width="35%">
										<h4><?php _e('Basic Settings','opentickets-community-edition') ?></h4>
										<div class="date-time-block subsub">

											<input type="text" class="use-i18n-datepicker date-text" name="start-date" scope="td" data-link-with=".repeat-options [role='from']"
													data-display-format="<?php echo esc_attr( __( 'mm-dd-yy', 'opentickets-community-edition' ) ) ?>"
													value="<?php echo date( __( 'm-d-Y', 'opentickets-community-edition' ), $now ) ?>" title="<?php _e('Start Date','opentickets-community-edition') ?>" role="from" />
											<input type="text" class="time-text" name="start-time" value="<?php echo date(__('h:ia','opentickets-community-edition'), $now) ?>" title="<?php _e('Start Time','opentickets-community-edition') ?>" />

											<?php _e('to','opentickets-community-edition') ?><br/>

											<input type="text" class="use-i18n-datepicker date-text" name="end-date" scope="td" data-link-with=".repeat-options [role='from']"
													data-display-format="<?php echo esc_attr( __( 'mm-dd-yy', 'opentickets-community-edition' ) ) ?>"
													value="<?php echo date( __( 'm-d-Y', 'opentickets-community-edition' ), $end ) ?>" title="<?php _e('End Date','opentickets-community-edition') ?>" role="to" />
											<input type="text" class="time-text" name="end-time" value="<?php echo date(__('h:ia','opentickets-community-edition'), $end) ?>" title="<?php _e('End Time','opentickets-community-edition') ?>" />

										</div>

										<div class="event-settings-block subsub">
											<span class="cb-wrap">
												<input type="checkbox" name="repeat" value="1" class="togvis" tar=".repeat-options" scope=".option-sub" auto="auto" />
												<span class="cb-text"><?php _e('Repeat','opentickets-community-edition') ?>...</span>
											</span>
										</div>

										<?php do_action('qsot-events-basic-settings', $post, $mb) ?>
									</td>

									<td>
										<div class="repeat-options hide-if-js">
											<h4><?php _e('Repeat','opentickets-community-edition') ?></h4>
											<div class="repeat-settings subsub">
												<table class="repeat-settings-wrapper settings-list">
													<tbody>
														<tr>
															<th><?php _e('Repeats','opentickets-community-edition') ?>:</th>
															<td>
																<select name="repeats" class="togvis" tar=".repeat-options-%VAL%" scope=".repeat-settings" auto="auto">
																	<?php /* <option value="daily">Daily</option> */ ?>
																	<option value="weekly" <?php selected(true, true) ?>><?php _e('Weekly','opentickets-community-edition') ?></option>
																</select>
															</td>
														</tr>

														<tr>
															<th><?php _e('Repeats Every','opentickets-community-edition') ?>:</th>
															<td>
																<select name="repeat-every">
																	<?php for ($i=1; $i<=30; $i++): ?>
																		<option value="<?php echo $i; ?>"><?php echo $i; ?></option>
																	<?php endfor; ?>
																</select>
																<span class="every-descriptor repeat-options-daily hide-if-js"><?php _e('days','opentickets-community-edition') ?></span>
																<span class="every-descriptor repeat-options-weekly hide-if-js"><?php _e('weeks','opentickets-community-edition') ?></span>
															</td>
														</tr>

														<tr class="hide-if-js repeat-options-weekly">
															<th><?php _e('Repeat on','opentickets-community-edition') ?>:</th>
															<td>
																<span class="cb-wrap">
																	<input type="checkbox" name="repeat-on[]" value="0" <?php selected(date('w', $now), 0) ?> />
																	<span class="cb-text"><?php _e('Su','opentickets-community-edition') ?></span>
																</span>
																<span class="cb-wrap">
																	<input type="checkbox" name="repeat-on[]" value="1" <?php selected(date('w', $now), 1) ?> />
																	<span class="cb-text"><?php _e('Mo','opentickets-community-edition') ?></span>
																</span>
																<span class="cb-wrap">
																	<input type="checkbox" name="repeat-on[]" value="2" <?php selected(date('w', $now), 2) ?> />
																	<span class="cb-text"><?php _e('Tu','opentickets-community-edition') ?></span>
																</span>
																<span class="cb-wrap">
																	<input type="checkbox" name="repeat-on[]" value="3" <?php selected(date('w', $now), 3) ?> />
																	<span class="cb-text"><?php _e('We','opentickets-community-edition') ?></span>
																</span>
																<span class="cb-wrap">
																	<input type="checkbox" name="repeat-on[]" value="4" <?php selected(date('w', $now), 4) ?> />
																	<span class="cb-text"><?php _e('Th','opentickets-community-edition') ?></span>
																</span>
																<span class="cb-wrap">
																	<input type="checkbox" name="repeat-on[]" value="5" <?php selected(date('w', $now), 5) ?> />
																	<span class="cb-text"><?php _e('Fr','opentickets-community-edition') ?></span>
																</span>
																<span class="cb-wrap">
																	<input type="checkbox" name="repeat-on[]" value="6" <?php selected(date('w', $now), 6) ?> />
																	<span class="cb-text"><?php _e('Sa','opentickets-community-edition') ?></span>
																</span>
															</td>
														</tr>

														<tr>
															<th><?php _e('Starts on','opentickets-community-edition') ?>:</th>
															<td>
																<input type="text" class="widefat date-text use-i18n-datepicker ends-on" name="repeat-starts" scope=".repeat-options"
																		data-display-format="<?php echo esc_attr( __( 'mm-dd-yy', 'opentickets-community-edition' ) ) ?>"
																		value="<?php echo esc_attr( date( __( 'm-d-Y', 'opentickets-community-edition' ), $now ) ) ?>" role="from" />
															</td>
														</tr>

														<tr>
															<th><?php _e('Ends','opentickets-community-edition') ?>:</th>
															<td>
																<ul>
																	<li>
																		<span class="cb-wrap">
																			<input type="radio" name="repeat-ends-type" value="on" checked="checked" />
																			<span class="cb-text"><?php _e('On','opentickets-community-edition') ?>:</span>
																		</span>
																		<input type="text" class="widefat date-text use-i18n-datepicker" name="repeat-ends-on" scope=".repeat-options"
																				data-display-format="<?php echo esc_attr( __( 'mm-dd-yy', 'opentickets-community-edition' ) ) ?>"
																				value="<?php echo esc_attr( date( __( 'm-d-Y', 'opentickets-community-edition' ), $one_week ) ) ?>" role="to" />
																	</li>
																	<li>
																		<span class="cb-wrap">
																			<input type="radio" name="repeat-ends-type" value="after" />
																			<span class="cb-text"><?php _e('After','opentickets-community-edition') ?>:</span>
																		</span>
																		<input type="number" class="widefat date-text focus-check" data-scope="li" data-target="input[type='radio']" name="repeat-ends-after" value="15" />
																		<span> <?php _e('occurences','opentickets-community-edition') ?></span>
																	</li>
																	<?php do_action('qsot-events-repeat-ends-type', $post, $mb) ?>
																</ul>
															</td>
														</tr>

														<?php do_action('qsot-events-repeat-options', $post, $mb) ?>
													</tbody>
												</table>
											</div>
										</div>
									</td>
								</tr>

								<?php do_action('qsot-events-date-time-settings-rows', $post, $mb) ?>
							</tbody>
						</table>

						<div class="clear"></div>
						<div class="actions">
							<input type="button" value="<?php _e('Add to Calendar','opentickets-community-edition') ?>" class="action button button-primary" rel="add-btn" />
						</div>
						<ul class="messages" rel="messages">
						</ul>
					</div>
				</div>

				<div class="<?php echo self::$o->pre ?>event-calendar-wrap option-sub no-border">
					<div class="<?php echo self::$o->pre ?>event-calendar" rel="calendar"></div>
				</div>

				<div class="option-sub" rel="settings">
					<table class="event-settings settings-table">
						<tbody>
							<tr>
								<td width="1%" class="date-selection-column">
									<h4><?php _e('Event Date/Times','opentickets-community-edition') ?></h4>
									<div class="event-date-time-list-view" rel="event-list"></div>
								</td>

								<td>
									<div class="bulk-edit-settings hide-if-js" rel="settings-main-form">
										<h4><?php _e('Settings','opentickets-community-edition') ?></h4>
										<div class="settings-form">
											<div class="setting-group">
												<div class="setting" rel="setting-main" tag="status">
													<div class="setting-current">
														<span class="setting-name"><?php _e('Status:','opentickets-community-edition') ?></span>
														<span class="setting-current-value" rel="setting-display"></span>
														<a class="edit-btn" href="#" rel="setting-edit" scope="[rel=setting]" tar="[rel=form]"><?php _e('Edit','opentickets-community-edition') ?></a>
														<input type="hidden" name="settings[status]" value="" scope="[rel=setting-main]" rel="status" />
													</div>
													<div class="setting-edit-form" rel="setting-form">
														<select name="status">
															<option value="publish" data-only-if="status=,publish,pending,draft,hidden,private"><?php _e('Published','opentickets-community-edition') ?></option>
															<option value="private" data-only-if="status=private"><?php _e('Privately Published','opentickets-community-edition') ?></option>
															<option value="future" data-only-if="status=future"><?php _e('Scheduled','opentickets-community-edition') ?></option>
															<?php do_action( 'qsot-event-setting-custom-status', $post, $mb ) ?>
															<option value="pending"><?php _e('Pending Review','opentickets-community-edition') ?></option>
															<option value="draft"><?php _e('Draft','opentickets-community-edition') ?></option>
														</select>
														<div class="edit-setting-actions">
															<input type="button" class="button" rel="setting-save" value="<?php _e('OK','opentickets-community-edition') ?>" />
															<a href="#" rel="setting-cancel"><?php _e('Cancel','opentickets-community-edition') ?></a>
														</div>
													</div>
												</div>

												<div class="setting" rel="setting-main" tag="visibility">
													<div class="setting-current">
														<span class="setting-name"><?php _e('Visibility','opentickets-community-edition') ?>:</span>
														<span class="setting-current-value" rel="setting-display"></span>
														<a href="#" rel="setting-edit" scope="[rel=setting]" tar="[rel=form]"><?php _e('Edit','opentickets-community-edition') ?></a>
														<input type="hidden" name="settings[visibility]" value="" scope="[rel=setting-main]" rel="visibility" />
													</div>
													<div class="setting-edit-form" rel="setting-form">
														<div class="cb-wrap" title="<?php _e('Viewable to the public','opentickets-community-edition') ?>">
															<input type="radio" name="visibility" value="public" />
															<span class="cb-text"><?php _e('Public','opentickets-community-edition') ?></span>
														</div>
														<div class="cb-wrap" title="<?php _e('Visible on the calendar, but only those with the password can view to make reservations','opentickets-community-edition') ?>">
															<input type="radio" name="visibility" value="protected" />
															<span class="cb-text"><?php _e('Password Protected','opentickets-community-edition') ?></span>
															<div class="extra" data-only-if="visibility=protected">
																<label><?php _e('Password:','opentickets-community-edition') ?></label><br/>
																<input type="text" name="password" value="" rel="password" />
															</div>
														</div>
														<div class="cb-wrap" title="<?php _e('Hidden from the calendar, but open to anyone with the url','opentickets-community-edition') ?>">
															<input type="radio" name="visibility" value="hidden" />
															<span class="cb-text"><?php _e('Hidden','opentickets-community-edition') ?></span>
														</div>
														<div class="cb-wrap" title="<?php _e('Only logged in admin users or the event author can view it','opentickets-community-edition') ?>">
															<input type="radio" name="visibility" value="private" />
															<span class="cb-text"><?php _e('Private','opentickets-community-edition') ?></span>
														</div>
														<div class="edit-setting-actions">
															<input type="button" class="button" rel="setting-save" value="<?php _e('OK','opentickets-community-edition') ?>" />
															<a href="#" rel="setting-cancel"><?php _e('Cancel','opentickets-community-edition') ?></a>
														</div>
													</div>
												</div>

												<div class="setting" rel="setting-main" tag="pub_date">
													<div class="setting-current">
														<span class="setting-name"><?php _e('Publish Date:','opentickets-community-edition') ?></span>
														<span class="setting-current-value" rel="setting-display"></span>
														<a class="edit-btn" href="#" rel="setting-edit" scope="[rel=setting]" tar="[rel=form]"><?php _e('Edit','opentickets-community-edition') ?></a>
														<input type="hidden" name="settings[pub_date]" value="" scope="[rel=setting-main]" rel="pub_date" />
													</div>
													<div class="setting-edit-form" rel="setting-form">
														<input type="hidden" name="pub_date" value="" />
														<div class="date-edit" tar="[name='pub_date']" scope="[rel='setting-form']">
															<select rel="month">
																<option value="1">01 - <?php _e('January','opentickets-community-edition') ?></option>
																<option value="2">02 - <?php _e('February','opentickets-community-edition') ?></option>
																<option value="3">03 - <?php _e('March','opentickets-community-edition') ?></option>
																<option value="4">04 - <?php _e('April','opentickets-community-edition') ?></option>
																<option value="5">05 - <?php _e('May','opentickets-community-edition') ?></option>
																<option value="6">06 - <?php _e('June','opentickets-community-edition') ?></option>
																<option value="7">07 - <?php _e('July','opentickets-community-edition') ?></option>
																<option value="8">08 - <?php _e('August','opentickets-community-edition') ?></option>
																<option value="9">09 - <?php _e('September','opentickets-community-edition') ?></option>
																<option value="10">10 - <?php _e('October','opentickets-community-edition') ?></option>
																<option value="11">11 - <?php _e('November','opentickets-community-edition') ?></option>
																<option value="12">12 - <?php _e('December','opentickets-community-edition') ?></option>
															</select>
															<input type="text" rel="day" value="" size="2" />,
															<input type="text" rel="year" value="" size="4" class="year" /> <?php _e('@','opentickets-community-edition') ?>
															<input type="text" rel="hour" value="" size="2" /> :
															<input type="text" rel="minute" value="" size="2" />
														</div>
														<div class="edit-setting-actions">
															<input type="button" class="button" rel="setting-save" value="<?php _e('OK','opentickets-community-edition') ?>" />
															<a href="#" rel="setting-cancel"><?php _e('Cancel','opentickets-community-edition') ?></a>
														</div>
													</div>
												</div>

												<div class="setting" rel="setting-main" tag="purchase_limit">
													<div class="setting-current">
														<span class="setting-name"><?php _e( 'Purchase Limit', 'opentickets-community-edition' ) ?>:</span>
														<span class="setting-current-value" rel="setting-display"></span>
														<a href="#" rel="setting-edit" scope="[rel=setting]" tar="[rel=form]"><?php _e('Edit','opentickets-community-edition') ?></a>
														<input type="hidden" name="settings[purchase_limit]" value="" scope="[rel='setting-main']" rel="purchase_limit" />
													</div>
													<div class="setting-edit-form" rel="setting-form">
														<input type="number" name="purchase_limit" step="1" value="" />
														<div class="helper"><?php _e( 'Use "" or "0" to indicate usage of the site-wide global purchase limit. Use "-1" to force an unlimited purchase limit.', 'opentickets-community-edition' ) ?></div>
														<div class="edit-setting-actions">
															<input type="button" class="button" rel="setting-save" value="<?php _e('OK','opentickets-community-edition') ?>" />
															<a href="#" rel="setting-cancel"><?php _e('Cancel','opentickets-community-edition') ?></a>
														</div>
													</div>
												</div>

											</div>

											<?php
												$extra_settings = apply_filters( 'qsot-events-bulk-edit-settings', array(), $post, $mb );
												echo implode( '', array_values( $extra_settings ) );
											?>
										</div>
									</div>
								</td>
							</tr>
						</tbody>
					</table>
				</div>

				<?php do_action('qsot-events-more-settings') ?>
			</div>
		<?php
	}

	public static function add_permalinks_settings_page_settings() {
		$current = array(
			'permalink_slug' => get_option( 'qsot_event_permalink_slug' ),
		);
		?>
			<table class="form-table">
				<tbody>
					<tr>
						<th scope="row"><label for="qsot_event_permalink_slug"><?php _e('Event base','opentickets-community-edition') ?></label></th>
						<td>
							<input id="qsot_event_permalink_slug" class="widefat" type="text" name="qsot_event_permalink_slug" value="<?php echo esc_attr( $current['permalink_slug'] ) ?>" /><br/>
							<span class="description">
								<?php printf(__('Enter a custom base to use. This url segment will prefix the event name in the url.<br/><code>example: %s</code>','opentickets-community-edition'), site_url( '/<strong>event</strong>/my-event_' . date_i18n( 'Y-m-d_H00a' ) . '/' ) ) ?>
							</span>
						</td>
					</tr>
				</tbody>
			</table>
			<input type="hidden" name="qsot_event_permalinks_settings" value="<?php echo esc_attr( wp_create_nonce( 'qsot-permalink-settings' ) ) ?>" />
		<?php
	}

	public static function permalink_settings_page() {
		self::_maybe_save_permalink_settings();
		global $wp_settings_sections, $wp_settings_fields;

		$wp_settings_sections['permalink']['opentickets-permalink'] = array(
			'id' => 'opentickets-permalink',
			'title' => __('Event permalink base','opentickets-community-edition'),
			'callback' => array(
				__CLASS__,
				'add_permalinks_settings_page_settings',
			),
		);
	}

	protected static function _maybe_save_permalink_settings() {
		if ( isset( $_POST['qsot_event_permalinks_settings'] ) && wp_verify_nonce( $_POST['qsot_event_permalinks_settings'], 'qsot-permalink-settings' ) ) {
			update_option( 'qsot_event_permalink_slug', $_POST['qsot_event_permalink_slug'] );
		}
	}

	// add items to the OpenTickets -> Settings page
	protected static function _setup_admin_options() {
		$colors = array();

		// primary seat selection form colors
		$colors['form_bg'] = '#f4f4f4';
		$colors['form_border'] = '#888888';
		$colors['form_action_bg'] = '#888888';
		$colors['form_helper'] = '#757575';

		// when something positive happens, a message appears. these colors control how that looks
		$colors['good_msg_bg'] = '#eeffee';
		$colors['good_msg_border'] = '#008800';
		$colors['good_msg_text'] = '#008800';

		// when something negative happens, a message appears. these colors control how that looks
		$colors['bad_msg_bg'] = '#ffeeee';
		$colors['bad_msg_border'] = '#880000';
		$colors['bad_msg_text'] = '#880000';

		// this controls the color of the remove buttons
		$colors['remove_bg'] = '#880000';
		$colors['remove_border'] = '#660000';
		$colors['remove_text'] = '#ffffff';

		// whether to display the event synopsis (updated on the parent event edit page) on the child event details pages
		self::$options->def( 'qsot-single-synopsis', 'no' );
		self::$options->def( 'qsot-synopsis-position', 'below' );

		// formula to determine when to stop selling tickets before a show launches. like '1 hour' means stop oune hour before the show starts
		self::$options->def( 'qsot-stop-sales-before-show', '' );

		// container for all the color settings above
		self::$options->def( 'qsot-event-frontend-colors', $colors );

		// whether to show the date and time in the titles of the child event details pages
		self::$options->def( 'qsot-show-date', 'yes' );
		self::$options->def( 'qsot-show-time', 'no' );

		// default for whether to show availability counts to the end user
		self::$options->def( 'qsot-show-available-quantity', 'yes' );

		// control how many tickets a user can buy, and if they can edit that number once they decide on it
		self::$options->def( 'qsot-event-purchase-limit', 0 );
		self::$options->def( 'qsot-locked-reservations', 'no' );

		self::$options->add(array(
			'order' => 100,
			'type' => 'title',
			'title' => __('Event Display', 'opentickets-community-edition'),
			'id' => 'heading-frontend-general-1',
			'page' => 'frontend',
		));

		self::$options->add(array(
			'order' => 105,
			'id' => 'qsot-stop-sales-before-show',
			'type' => 'text',
			'title' => __('Stop Sales Before Show','opentickets-community-edition'),
			'desc' => __('Amount of time to stop sales for a show, before show time. (ie: stop sales two hour before show time <code>2 hours</code>)','opentickets-community-edition'),
			'desc_tip' => __('valid units: hour, hours, minute, minutes, second, seconds, day, days, week, weeks, month, months, year, years','opentickets-community-edition'),
			'page' => 'frontend',
		));

		self::$options->add(array(
			'order' => 110,
			'id' => 'qsot-single-synopsis',
			'type' => 'checkbox',
			'title' => __('Single Event Synopsis','opentickets-community-edition'),
			'desc' => __('Show event synopsis on single event pages','opentickets-community-edition'),
			'desc_tip' => __('By default, just the event logo, and the event pricing options are shown. This feature will additionally show the description of the event to the user.','opentickets-community-edition'),
			'default' => 'no',
			'page' => 'frontend',
		));

		self::$options->add( array(
			'id' => 'qsot-synopsis-position',
			'order' => 125,
			'type' => 'radio',
			'title' => __( 'Single Event Synopsis Position', 'opentickets-community-edition' ),
			'desc_tip' => __( 'The display position of the Event Synopsis on the single event page.', 'opentickets-community-edition' ),
			'options' => array(
				'above' => __( 'Above the Ticket Selection UI', 'opentickets-community-edition' ),
				'below' => __( 'Below the Ticket Selection UI', 'opentickets-community-edition' ),
			),
			'default' => 'below',
			'page' => 'frontend',
		) );

		// setting for controlling display of the date in the event titles
		self::$options->add( array(
			'order' => 130,
			'id' => 'qsot-show-date',
			'type' => 'checkbox',
			'title' => __( 'Show Date', 'opentickets-community-edition' ),
			'desc' => __( 'Show the date in the title of events, on the event details pages.', 'opentickets-community-edition' ),
			'desc_tip' => __( 'This is the difference between a title reading "My Event" and "My Event on 09/12/2015".', 'opentickets-community-edition' ),
			'default' => 'yes',
			'page' => 'frontend',
		) );

		// setting for controlling display of the time in the event titles
		self::$options->add( array(
			'order' => 131,
			'id' => 'qsot-show-time',
			'type' => 'checkbox',
			'title' => __( 'Show Time', 'opentickets-community-edition' ),
			'desc' => __( 'Show the time in the title of events, on the event details pages.', 'opentickets-community-edition' ),
			'desc_tip' => __( 'This is the difference between a title reading "My Event" and "My Event @ 6:00pm".', 'opentickets-community-edition' ),
			'default' => 'no',
			'page' => 'frontend',
		) );

		self::$options->add( array(
			'order' => 150,
			'id' => 'qsot-events-on-homepage',
			'type' => 'checkbox',
			'title' => __( 'Show Events on Home', 'opentickets-community-edition' ),
			'desc' => __( 'Show the parent events on the homepage, mixed in with the posts.', 'opentickets-community-edition' ),
			'desc_tip' => __( 'Checking this will cause the parent events to be displayed on the homepage, mixed in with the posts.', 'opentickets-community-edition' ),
			'default' => 'no',
			'page' => 'frontend',
		) );

		self::$options->add( array(
			'order' => 299,
			'type' => 'sectionend',
			'id' => 'heading-frontend-general-2',
			'page' => 'frontend',
		) );

		// colors tab
		self::$options->add(array(
			'order' => 100,
			'type' => 'title',
			'title' => __('Colors', 'opentickets-community-edition'),
			'id' => 'heading-frontend-colors-1',
			'page' => 'frontend',
			'section' => 'styles',
		));

		self::$options->add(array(
			'order' => 190,
			'id' => 'qsot-event-frontend-colors',
			'type' => 'qsot_frontend_styles',
			'page' => 'frontend',
			'section' => 'styles',
		));

		self::$options->add(array(
			'order' => 199,
			'type' => 'sectionend',
			'id' => 'heading-frontend-colors-1',
			'page' => 'frontend',
			'section' => 'styles',
		));


		// show the availabitility quantity to end users
		self::$options->add(array(
			'order' => 120,
			'id' => 'qsot-show-available-quantity',
			'type' => 'checkbox',
			'title' => __( 'Availability Quantity', 'opentickets-community-edition' ),
			'desc' => __( 'Yes, show the quantity that is available, on the frontend, when showing the availability.', 'opentickets-community-edition' ),
			'default' => 'yes',
		));


		// add the setting section for teh abstract reservation features
		self::$options->add( array(
			'order' => 100, 
			'type' => 'title',
			'title' => __( 'Limitations', 'opentickets-community-edition' ),
			'id' => 'heading-limitations',
			'page' => 'general',
			'section' => 'reservations',
		) ); 

		// enforce a limit on the number of tickets per event, per order, that a user can purchase
		self::$options->add( array(
			'order' => 125,
			'id' => 'qsot-event-purchase-limit',
			'type' => 'number',
			'custom_attributes' => array( 'step' => 1, 'min' => 0 ),
			'title' => __( 'Per Event, Per Order Ticket Purchase Limit', 'opentickets-community-edition' ),
			'desc' => __( 'A positive number here tells the software to enforce a purchase limit. Users will be restricted to only buying upto X tickets per event, per order. Setting to 0 means no limit.', 'opentickets-community-edition' ),
			'default' => 0,
			'page' => 'general',
			'section' => 'reservations',
		) );

		// prevent end users from modifying the quantity of their reservations after they chose the number initially. they are still allowed to delete the reservations
		self::$options->add( array(
			'order' => 127,
			'id' => 'qsot-locked-reservations',
			'type' => 'checkbox',
			'title' => __( 'Locked-in Reservations', 'opentickets-community-edition' ),
			'desc' => __( 'Checking this box means that once the end user chooses a quantity of tickets to purchase, they cannot modify that quantity. They can still delete their reservations and start over though.', 'opentickets-community-edition' ),
			'default' => 'no',
			'page' => 'general',
			'section' => 'reservations',
		) );

		// End state timers
		self::$options->add( array(
			'order' => 199, 
			'type' => 'sectionend',
			'id' => 'heading-limitations',
			'page' => 'general',
			'section' => 'reservations',
		) ); 
	}
}

if (defined('ABSPATH') && function_exists('add_action')) {
	qsot_post_type::pre_init();
}
