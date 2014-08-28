<?php (__FILE__ == $_SERVER['SCRIPT_FILENAME']) ? die(header('Location: /')) : null;

class qsot_seating_report extends qsot_admin_report {
	protected static $report_name = 'Seating';
	protected static $report_slug = 'seating';
	protected static $report_desc = 'List all seats for a show, as wells as their availability and occupancy.';
	protected static $s2w = array();

	// holder for event plugin options
	protected static $o = null;
	protected static $options = null;
	//protected static $slug = 'woocommerce_page_woocommerce_reports';
	//protected static $page_hook = 'opentickets_page_opentickets-settings';
	protected static $page_hook = 'toplevel_page_opentickets';

	public static function pre_init() {
		$settings_class_name = apply_filters('qsot-settings-class-name', '');
		if (!empty($settings_class_name)) {
			self::$o = call_user_func_array(array($settings_class_name, "instance"), array());
			self::$s2w = array(
				self::$o->{'sc.states.r'} => 'Not Paid',
				self::$o->{'sc.states.c'} => 'Paid',
				self::$o->{'sc.states.o'} => 'Checked In',
			);

			// load all the options, and share them with all other parts of the plugin
			$options_class_name = apply_filters('qsot-options-class-name', '');
			if (!empty($options_class_name)) {
				self::$options = call_user_func_array(array($options_class_name, "instance"), array());
				//self::_setup_admin_options();
			}

			add_filter('qsot-reports', array(__CLASS__, 'add_report'), 10);
			add_filter('woocommerce_order_note_types', array(__CLASS__, 'add_order_note_types'), 10, 2);
			add_action('woocommerce_ajax_save_order_note', array(__CLASS__, 'save_new_order_note_types'), 10, 4);
			add_filter('woocommerce_get_order_note_type', array(__CLASS__, 'get_order_note_type'), 10, 2);

			add_action('qsot-ajax-report-ajax-seating', array(__CLASS__, 'process_ajax'), 10);
			add_filter('qsot-seating-report-get-ticket-data', array(__CLASS__, 'aggregate_ticket_data'), 10, 2);

			add_action('load-'.self::$page_hook, array(__CLASS__, 'load_reports_page_assets'), 10);
			add_action('qsot-load-seating-report-assets', array(__CLASS__, 'possibly_printer_friendly'), 10);
		}
	}

	public static function load_reports_page_assets() {
		ob_start();
		if (function_exists('woocommerce_reports_page')) woocommerce_reports_page();
		ob_end_clean();

		$charts = self::_get_charts();

		$first_tab      = array_keys( $charts );
		$current_tab 	= isset( $_GET['tab'] ) ? sanitize_title( urldecode( $_GET['tab'] ) ) : $first_tab[0];
		
		if ($current_tab != 'seating') return;

		do_action('qsot-load-seating-report-assets');
	}

	protected static function _get_charts() {
		// return woocommerce_get_reports_charts();
		$charts = array();
		return apply_filters( 'qsot_reports_charts', $charts );
	}

	public static function possibly_printer_friendly() {
		if (!isset($_GET['pf'])) return;

		$data = self::_data($_REQUEST);

		self::printer_friendly_header();
		self::_result($data);
		self::printer_friendly_header();

		exit();
	}

	public static function add_order_note_types($list, $order) {
		$list['seating-report-note'] = __('Seating Report note', 'qsot');
		return $list;
	}

	public static function get_order_note_type($type, $note) {
		if (get_comment_meta($note->comment_ID, 'is_seating_report_note', true) == 1) $type = 'seating chart note';
		return $type;
	}

	public static function save_new_order_note_types($comment_id, $note_type, $note, $order) {
		update_comment_meta($comment_id, 'is_seating_report_note', $note_type == 'seating-report-note' ? 1 : 0);
	}

	public static function add_report($list) {
		$list['Seating'] = isset($list['Seating']) ? $list['Seating'] : array();
		$list['Seating'][] = array(
			'title' => self::get('name'),
			'description' => self::get('desc'),
			'function' => array(__CLASS__, 'report'),
		);

		return $list;
	}

	public static function process_ajax() {
		$data = self::_data($_POST);
		header('Content-Type: text/html');

		switch ($_POST['raction']) {
			case 'extended-form':
				self::_form_extended($data);
			break;

			case 'show-results':
				self::_result($data);
			break;
		}

		die();
	}

	public static function _data($data) {
		return wp_parse_args($data, array(
			'parent_event' => 0,
			'showing' => 0,
		));
	}

	public static function report() {
		$data = self::_data($_POST);
		self::_form($data);
	}

	protected static function _form($data) {
		$range = date('Y'); //isset($data['range']) ? $data['range'] : false;
		$this_y = intval(date('Y'));
		$miny = $this_y - 5;
		$maxy = $this_y + 5;
		/*
		if (empty($range)) {
			$range = $this_y;
			$after_date = ($this_y-1).'-12-31 23:59:59';
			$before_date = ($this_y+1).'-01-01 00:00:00';
		} else {
			if (is_numeric($range)) {
				$after_date = ($range-1).'-12-31 23:59:59';
				$before_date = ($range+1).'-01-01 00:00:00';
			} else {
				$after_date = '0000-00-00 00:00:00';
				$before_date = '9999-12-31 23:59:59';
			}
		}
		$old_range = isset($data['old-range']) ? $data['old-range'] : $this_y;
		if ($old_range != $range) $data['parent_event'] = 0;
		*/

		$parents = get_posts(array(
			'posts_per_page' => -1,
			'post_type' => self::$o->core_post_type,
			'orderby' => 'title',
			'order' => 'asc',
			'post_parent' => 0,
			'post_status' => array('publish', 'hidden', 'private'),
			//'start_date_after' => $after_date,
			//'start_date_before' => $before_date,
			'suppress_filters' => false,
		));
		?>
			<div class="form-container" style="margin-bottom:15px;">
				<form method="post" action="">
					<label for="range">Year</label>
					<input type="hidden" name ="old-range" value="<?php echo esc_attr($range) ?>" />
					<select name="range" id="range" class="filter-list" limit="#event">
						<option value="all">[All Years]</option>
						<?php for ($i=$miny; $i<=$maxy; $i++): ?>
							<option value="<?php echo esc_attr($i) ?>" <?php selected($i, $range) ?>><?php echo $i ?></option>
						<?php endfor; ?>
					</select>

					<label for="event"><?php _e('Event:', 'qsot') ?></label>
					<div style="display:none;">
						<select class="event-pool" rel="event-pool">
							<?php foreach ($parents as $parent): ?>
								<?php $lval = date('Y', strtotime(get_post_meta($parent->ID, '_start', true))); ?>
								<option lvalue="<?php echo $lval ?>" value="<?php echo esc_attr($parent->ID) ?>" <?php echo selected($parent->ID, $data['parent_event']) ?>><?php echo esc_html($parent->post_title) ?></option>
							<?php endforeach; ?>
						</select>
					</div>

					<select name="parent_event" id="event" rel="event-list" pool="[rel='event-pool']"></select>
					<input type="hidden" name="action" value="extended-form" />
					<input type="hidden" name="report" value="seating" />
					<input type="submit" value="Lookup Showings" />
				</form>
				<div class="form-extended" id="form_extended"></div>
			</div>
			<div class="report-result" id="report_result"></div>
		<?php
	}

	protected static function _form_extended($data) {
		$shows = get_posts(array(
			'posts_per_page' => -1,
			'post_type' => self::$o->core_post_type,
			'post_status' => array('publish', 'hidden'),
			'orderby' => 'title',
			'order' => 'asc',
			'post_parent' => $data['parent_event'],
			'suppress_filters' => false,
		));

		$sort = isset($_REQUEST['sort']) ? $_REQUEST['sort'] : '';

		?>
			<form method="post" action="">
				<label for="showing"><?php _e('Showing:', 'qsot') ?></label>
				<select name="showing" id="showing">
					<?php foreach ($shows as $show): ?>
						<option value="<?php echo esc_attr($show->ID) ?>" <?php echo selected($show->ID, $data['showing']) ?>><?php echo esc_html($show->post_title) ?></option>
					<?php endforeach; ?>
				</select>
				<input type="hidden" name="parent_event" value="<?php echo esc_attr($data['parent_event']) ?>" />
				<input type="hidden" name="action" value="show-results" />
				<input type="hidden" name="sort" value="<?php echo $sort ?>" />
				<input type="hidden" name="report" value="seating" />
				<input type="submit" value="Show Report" />
			</form>
		<?php
	}

	protected static function _ticket_ois_from_event($event_id) {
		global $wpdb;
		$tickets = $ticket_types = $order_ids = array();

		$q = 'select order_item_id from '.$wpdb->prefix.'woocommerce_order_itemmeta where meta_key = %s and meta_value = %s limit %d offset %d';
		$offset = 0;
		$per = 100;
		while ($ticket_ids = $wpdb->get_col($wpdb->prepare($q, '_event_id', $event_id, $per, $offset))) {
			$offset += $per;
			$ticket_meta = is_array($ticket_ids) && count($ticket_ids)
				? $wpdb->get_results('select order_item_id, meta_key, meta_value from '.$wpdb->prefix.'woocommerce_order_itemmeta where order_item_id in ('.implode(',', $ticket_ids).')')
				: array();

			foreach ($ticket_meta as $row) {
				if (!isset($tickets[$row->order_item_id])) $tickets[$row->order_item_id] = array();
				if ($row->meta_key == '_product_id' && $row->meta_value) $ticket_types[$row->meta_value] = 1;
				$tickets[$row->order_item_id][$row->meta_key] = $row->meta_value;
			}

			$oinfo = is_array($ticket_ids) && count($ticket_ids)
				? $wpdb->get_results('select order_item_id, order_id, order_item_name from '.$wpdb->prefix.'woocommerce_order_items where order_item_id in ('.implode(',', $ticket_ids).')')
				: array();
			
			foreach ($oinfo as $row) {
				$order_ids[$row->order_id] = 1;
				$tickets[$row->order_item_id]['_order_item_id'] = $row->order_item_id;
				$tickets[$row->order_item_id]['_order_id'] = $row->order_id;
				$tickets[$row->order_item_id]['_order_item_name'] = $row->order_item_name;
			}
		}


		return array($tickets, array_keys($ticket_types), array_keys($order_ids));
	}

	protected static function _get_order_info($tickets, $order_ids) {
		global $wpdb;
		$orders = array();

		remove_action('comments_clauses', 'woocommerce_exclude_order_comments');
		foreach ($order_ids as $order_id) {
			$order = new WC_Order($order_id);
			if (empty($order->id)) continue;
			$order->seeating_report_note = null;
			$comments = null;
			$comments = get_comments(array(
				'post_id' => $order_id,
				'approve' => 'approve',
				'type' => 'order_note',
				'meta_query' => array(
					array('key' => 'is_seating_report_note', 'value' => '1', 'compare' => '='),
				),
				'orderby' => 'comment_date_gmt',
				'order' => 'desc',
				'number' => 1,
			));
			if (is_array($comments) && count($comments)) $order->seating_report_note = array_shift($comments);
			$orders[$order_id] = (object)array(
				'id' => $id,
				'seating_report_note' => $order->seating_report_note,
				'billing_first_name' => $order->billing_first_name,
				'billing_last_name' => $order->billing_last_name,
			);
		}

		foreach ($tickets as $id => $ticket) {
			if (isset($ticket['_order_id'], $orders[$ticket['_order_id']]) && !empty($orders[$ticket['_order_id']]->id)) {
				$ticket['billing_first_name'] = $orders[$ticket['_order_id']]->billing_first_name;
				$ticket['billing_last_name'] = $orders[$ticket['_order_id']]->billing_last_name;
				$ticket['state'] = apply_filters('qsot-get-zone-states', array(), $ticket['_order_id'], $ticket['_event_id'], $ticket['_zone_id']);
				$tickets[$id] = $ticket;
			}
		}
		
		uasort($tickets, array(__CLASS__, '_by_billing_info'));

		return array($tickets, $orders);
	}

	protected static function _get_ticket_types($ids) {
		$types = array();
		foreach ($ids as $id) {
			$product = get_product($id);
			if (is_object($product) && !is_wp_error($product)) $types[$id.''] = $product;
		}
		return $types;
	}

	public static function aggregate_ticket_data($ticket_data, $data) {
		$event = apply_filters('qsot-get-event', false, $data['showing']);
		if (!is_object($event) || !isset($event->ID)) return $ticket_data;

		list($tickets, $ticket_type_ids, $order_ids) = self::_ticket_ois_from_event($event->ID);
		list($tickets, $orders) = self::_get_order_info($tickets, $order_ids);
		$ticket_types = self::_get_ticket_types($ticket_type_ids);

		return array(
			'event' => $event,
			'tickets' => $tickets,
			'orders' => $orders,
			'ticket_types' => $ticket_types
		);
	}

	protected static function _result($data) {
		if (!empty($data['showing'])) {
			$ticket_data = apply_filters('qsot-seating-report-get-ticket-data', array(), $data);
			if (!isset($ticket_data['event'])) {
				echo '<p><em>Could not find that event.</em></p>';
				return;
			}

			unset($ticket_data['ticket_data'], $ticket_data['data']);
			extract($ticket_data);

			$final = self::_compile_rows($tickets, $orders, $ticket_types, $event);
			$fields = self::_report_fields();

			self::_inc_template(array('admin/reports/seating-report.php'), array(
				'req' => $data,
				'rows' => $final,
				'fields' => $fields,
				'tickets' => $tickets,
				'orders' => $orders,
				'ticket_types' => $ticket_types,
				'event' => $event,
				'csv' => self::_csv(self::_nice_csv_data($tickets, $event, $orders, $ticket_types), $data),
			));
		}
	}

	protected static function _state_text($states) {
		$states = (array)$states;
		$words = array();
		foreach ($states as $k => $v) {
			$words[] = (isset(self::$s2w[$k]) ? self::$s2w[$k] : '-').' ('.$v.')';
		}
		return empty($words) ? '-' : implode(',', $words);
	}

	protected static function _compile_rows($tickets, $orders, $ticket_types, $event) {
		$final = array();
		$fields = self::_report_fields();
	
		$cnt = 0;
		foreach ($tickets as $ticket) {
			$order = $orders[$ticket['_order_id']];
			$ticket_name = isset($ticket_types[$ticket['_product_id']]) && is_object($ticket_types[$ticket['_product_id']]) ? $ticket_types[$ticket['_product_id']]->get_title() : $ticket['_order_item_name'];
			$row = array();
			$row['purchaser'] = $order->billing_last_name.', '.$order->billing_first_name;
			$row['order_id'] = $ticket['_order_id'];
			$row['ticket_type'] = $ticket_name;
			$row['quantity'] = $ticket['_qty'];
			$row['note'] = $order->seating_report_note->comment_content;
			$row['state'] = self::_state_text($ticket['state']);
      $row['email'] = $order->billing_email;
      $row['phone'] = $order->billing_phone;
      $row['address'] = self::_get_address($order);

			$row['_user_link'] = get_edit_user_link($order->customer_user);
			$row['_order_link'] = get_edit_post_link($ticket['_order_id']);
			$row['_ticket_link'] = site_url($ticket['_ticket_link']);
			$row['_product_link'] = get_edit_post_link($ticket['_product_id']);

			$final[] = apply_filters('qsotc-seating-report-compile-rows-occupied', $row, $ticket, $event, $orders, $ticket_types, $fields);
			$cnt += $ticket['_qty'];
		}

		$capacity = isset($event->meta, $event->meta->capacity) ? $event->meta->capacity : 0;
		if ($capacity - $cnt > 0) {
			$row = array();
			$row['purchaser'] = 'AVAILABLE';
			$row['order_id'] = 0;
			$row['ticket_type'] = '-';
			$row['quantity'] = $capacity - $cnt;
			$row['note'] = '-';
			$row['state'] = '-';
      $row['email'] = '-';
      $row['phone'] = '-';
      $row['address'] = '-';

			$row['_user_link'] = '';
			$row['_order_link'] = '';
			$row['_ticket_link'] = '';
			$row['_product_link'] = '';

			$final[] = apply_filters('qsotc-seating-report-compile-rows-available', $row, $zone_id, $count, $event, $orders, $ticket_types, $fields);
		}

		$final = self::_process_sort($final);

		return $final;
	}

	protected static function _process_sort($list) {
		$sortby = isset($_REQUEST['sort']) && is_scalar($_REQUEST['sort']) ? $_REQUEST['sort'] : 'purchaser';

		$k = $sortby;
		$valid = false;
		$by = $sub = $sub2 = array();
		foreach ($list as $item) {
			if (isset($item[$k])) {
				$v = explode('-', $item['zone']);
				$sub2[] = array_pop($v);
				$sub[] = implode('-', $v);
				$by[] = strtolower($item[$k]);
				$valid = true;
			} else {
				$by[] = $sub[] = $sub2[] = '';
			}
		}
		if ($valid) {
			if (in_array($k, array('zone'))) array_multisort($sub, SORT_STRING, SORT_ASC, $sub2, SORT_NUMERIC, SORT_ASC, $list);
			else if (in_array($k, array('order_id'))) array_multisort($by, SORT_NUMERIC, SORT_ASC, $sub, SORT_STRING, SORT_ASC, $sub2, SORT_NUMERIC, SORT_ASC, $list);
			else array_multisort($by, SORT_STRING, SORT_ASC, $sub, SORT_STRING, SORT_ASC, $sub2, SORT_NUMERIC, SORT_ASC, $list);
		}
		$front = $back = array();
		foreach ($list as $item) {
			if (empty($item['order_id'])) $back[] = $item;
			else $front[] = $item;
		}
		$list = array_merge($front, $back);

		return $list;
	}

	protected static function _report_fields($csv=false) {
		$basic = array(
			'purchaser' => __('Purchaser'),
			'order_id' => __('Order #'),
			'ticket_type' => __('Ticket Type'),
			'quantity' => __('Quantity'),
      'email' => __('Email'),
      'phone' => __('Phone'),
      'address' => __('Address'),
			'note' => __('Note'),
			'state' => __('Status'),
		);
		if ($csv) {
			$basic['event'] = __('Event');
			$basic['ticket_link'] = __('Ticket Url');
		}
		return apply_filters('qsot-seating-report-fields', $basic);
	}

  protected static function _get_address($order) {
    $addr = $order->billing_address_1;
    if (!empty($order->billing_address_2)) $addr .= '<br/>'.$order->billing_address_2;
    $addr .= '<br/>'.$order->billing_city.', '.$order->billing_state.' '.$order->billing_postcode.', '.$order->billing_country;
    return $addr;
  }

	protected static function _nice_csv_data($tickets, $event, $orders, $ticket_types) {
		$out = array();

		$fields = self::_report_fields(true);

		foreach ($tickets as $ticket) {
			$row = array();

			foreach ($fields as $field => $label) {
				switch ($field) {
					case 'purchaser': $row[$label] = $ticket['billing_first_name'].' '.$ticket['billing_last_name']; break;
					case 'order_id': $row[$label] = $ticket['_order_id']; break;
					case 'event': $row[$label] = apply_filters('the_title', $event->post_title); break;
					case 'ticket_type': $row[$label] = apply_filters('the_title', $ticket_types[$ticket['_product_id']]->post->post_title); break;
					case 'quantity': $row[$label] = $ticket['_qty']; break;
					case 'ticket_link': $row[$label] = site_url($ticket['_ticket_link']); break;
          case 'email': $row[$label] = $orders[$ticket['_order_id']]->billing_email; break;
          case 'phone': $row[$label] = $orders[$ticket['_order_id']]->billing_phone; break;
          case 'address': $row[$label] = self::_get_address($orders[$ticket['_order_id']]); break;
					case 'note': $row[$label] = is_object($orders[$ticket['_order_id']]->seating_report_note) ? $orders[$ticket['_order_id']]->seating_report_note->comment_content : ''; break;
					default: $row = apply_filters('qsotc-seating-report-csv-row', $row, $field, $label, $ticket, $event, $orders, $ticket_types);
				}
			}

			$out[] = $row;
		}

		return $out;
	}

	public static function get($field) {
		$var = 'report_'.$field;
		return isset(self::$$var) ? __(self::$$var, 'qsot') : '';
	}
}

if (defined('ABSPATH') && function_exists('add_action')) {
	qsot_seating_report::pre_init();
}
