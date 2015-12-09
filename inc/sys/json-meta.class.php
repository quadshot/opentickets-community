<?php if ( __FILE__ == $_SERVER['SCRIPT_FILENAME'] ) die( header( 'Location: /') );

class qsot_json_meta {
	public static function pre_init() {
		add_filter('qsot-add-meta', array(__CLASS__, 'f_add_meta'), 11, 6);
		add_filter('qsot-update-meta', array(__CLASS__, 'f_update_meta'), 11, 6);
		add_filter('qsot-delete-meta', array(__CLASS__, 'f_delete_meta'), 11, 6);
		add_filter('qsot-get-meta', array(__CLASS__, 'f_get_meta'), 11, 5);
	}

	public static function f_add_meta($current, $meta_type, $object_id, $meta_key, $meta_value, $unique=false) {
		return self::add_meta($meta_type, $object_id, $meta_key, $meta_value, $unique);
	}
	public static function f_update_meta($current, $meta_type, $object_id, $meta_key, $meta_value, $prev_value='') {
		return self::update_meta($meta_type, $object_id, $meta_key, $meta_value, $prev_value);
	}
	public static function f_delete_meta($current, $meta_type, $object_id, $meta_key, $meta_value='', $delete_all=false) {
		return self::delete_meta($meta_type, $object_id, $meta_key, $meta_value, $delete_all);
	}
	public static function f_get_meta($current, $meta_type, $object_id, $meta_key = '', $single = false) {
		return self::get_meta($meta_type, $object_id, $meta_key, $single);
	}

	public static function add_meta($meta_type, $object_id, $meta_key, $meta_value, $unique=false) {
		if (!$meta_key || !$meta_type) return false;
		if (!$object_id = absint($object_id)) return false;
		if (!$table = self::_get_table_name($meta_type)) return false;
		global $wpdb;

		$column = esc_sql($meta_type.'_id');
		// expected_slashed ($meta_key)
		$meta_key = stripslashes($meta_key);
		$meta_value = stripslashes_deep($meta_value);
		$meta_value = sanitize_meta( $meta_key, $meta_value, $meta_type );

		if (null !== ($check = apply_filters("add_{$meta_type}_metadata", null, $object_id, $meta_key, $meta_value, $unique))) return $check;
		if ($unique && $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE meta_key = %s AND $column = %d", $meta_key, $object_id))) return false;

		$_meta_value = $meta_value;
		$meta_value = self::_maybe_json_encode($meta_value);

		do_action("add_{$meta_type}_meta", $object_id, $meta_key, $_meta_value);
		$result = $wpdb->insert($table, array($column => $object_id, 'meta_key' => $meta_key, 'meta_value' => $meta_value));
		if (!$result) return false;

		$mid = (int)$wpdb->insert_id;
		wp_cache_delete($object_id, $meta_type.'_meta');
		do_action("added_{$meta_type}_meta", $mid, $object_id, $meta_key, $_meta_value);

		return $mid;
	}

	public static function update_meta($meta_type, $object_id, $meta_key, $meta_value, $prev_value = '') {
		if (!$meta_type || !$meta_key) return false;
		if (!$object_id = absint($object_id)) return false;
		if (!$table = self::_get_table_name($meta_type)) return false;
		global $wpdb;

		$column = esc_sql($meta_type.'_id');
		$id_column = apply_filters('qsot-meta-id-field', 'meta_id', $meta_type);
		// expected_slashed ($meta_key)
		$meta_key = stripslashes($meta_key);
		$passed_value = $meta_value;
		$meta_value = stripslashes_deep($meta_value);
		$meta_value = sanitize_meta($meta_key, $meta_value, $meta_type);

		if (null !== ($check = apply_filters("update_{$meta_type}_metadata", null, $object_id, $meta_key, $meta_value, $prev_value))) return (bool)$check;

		if (!$meta_id = $wpdb->get_var($wpdb->prepare("SELECT $id_column FROM $table WHERE meta_key = %s AND $column = %d", $meta_key, $object_id)))
			return self::add_meta($meta_type, $object_id, $meta_key, $passed_value);

		// Compare existing value to new value if no prev value given and the key exists only once.
		if (empty($prev_value)) {
			$old_value = self::get_meta($meta_type, $object_id, $meta_key);
			if (count($old_value) == 1 && $old_value[0] === $meta_value) return false;
		}

		$_meta_value = $meta_value;
		$meta_value = self::_maybe_json_encode($meta_value);
		$data  = compact('meta_value');
		$where = array($column => $object_id, 'meta_key' => $meta_key);

		if (!empty($prev_value)) {
			$prev_value = self::_maybe_json_encode($prev_value);
			$where['meta_value'] = $prev_value;
		}

		do_action("update_{$meta_type}_meta", $meta_id, $object_id, $meta_key, $_meta_value);
		$wpdb->update($table, $data, $where);
		wp_cache_delete($object_id, $meta_type.'_meta');
		do_action("updated_{$meta_type}_meta", $meta_id, $object_id, $meta_key, $_meta_value);

		return true;
	}

	public static function delete_meta($meta_type, $object_id, $meta_key, $meta_value = '', $delete_all = false) {
		if (!$meta_type || !$meta_key) return false;
		if ((!$object_id = absint($object_id)) && !$delete_all) return false;
		if (!$table = self::_get_table_name($meta_type)) return false;
		global $wpdb;

		$type_column = esc_sql($meta_type.'_id');
		$id_column = apply_filters('qsot-meta-id-field', 'meta_id', $meta_type);
		// expected_slashed ($meta_key)
		$meta_key = stripslashes($meta_key);
		$meta_value = stripslashes_deep($meta_value);

		if (null !== ($check = apply_filters("delete_{$meta_type}_metadata", null, $object_id, $meta_key, $meta_value, $delete_all))) return (bool)$check;

		$_meta_value = $meta_value;
		$meta_value = self::_maybe_json_encode($meta_value);

		$query = $wpdb->prepare("SELECT $id_column FROM $table WHERE meta_key = %s", $meta_key);
		if (!$delete_all) $query .= $wpdb->prepare(" AND $type_column = %d", $object_id);
		if ($meta_value) $query .= $wpdb->prepare(" AND meta_value = %s", $meta_value);
		$meta_ids = $wpdb->get_col($query);
		if (!count($meta_ids)) return false;

		if ($delete_all) $object_ids = $wpdb->get_col($wpdb->prepare("SELECT $type_column FROM $table WHERE meta_key = %s", $meta_key));

		do_action("delete_{$meta_type}_meta", $meta_ids, $object_id, $meta_key, $_meta_value);
		$query = "DELETE FROM $table WHERE $id_column IN( ".implode(',', $meta_ids)." )";
		$count = $wpdb->query($query);

		if (!$count) return false;

		if ($delete_all) foreach ((array)$object_ids as $o_id) wp_cache_delete($o_id, $meta_type.'_meta');
		else wp_cache_delete($object_id, $meta_type.'_meta');
		do_action("deleted_{$meta_type}_meta", $meta_ids, $object_id, $meta_key, $_meta_value);

		return true;
	}

	public static function get_meta($meta_type, $object_id, $meta_key = '', $single = false) {
		if (!$meta_type) return false;
		if (!$object_id = absint($object_id)) return false;

		$check = apply_filters("get_{$meta_type}_metadata", null, $object_id, $meta_key, $single);
		if (null !== $check) {
			if ($single && is_array($check)) return $check[0];
			else return $check;
		}

		$meta_cache = wp_cache_get($object_id, $meta_type.'_meta');
		if (!$meta_cache) {
			$meta_cache = self::update_meta_cache($meta_type, array($object_id));
			$meta_cache = $meta_cache[$object_id];
		}

		if (!$meta_key) {
			if ($single) return array_map(array(__CLASS__, '_maybe_json_decode'), array_map(array(__CLASS__, '_single_value'), $meta_cache));
			else return $meta_cache;
		}

		if (isset($meta_cache[$meta_key])) {
			if ($single) return self::_maybe_json_decode($meta_cache[$meta_key][0]);
			else return array_map(array(__CLASS__, '_maybe_json_decode'), $meta_cache[$meta_key]);
		}

		if ($single) return '';
		else return array();
	}

	public static function _single_value($value) {
		return is_array($value) ? array_shift($value) : $value;
	}

	public static function update_meta_cache($meta_type, $object_ids) {
		if (empty($meta_type) || empty($object_ids)) return false;
		if (!$table = self::_get_table_name($meta_type)) return false;
		global $wpdb;

		$column = esc_sql($meta_type.'_id');

		if (!is_array($object_ids)) {
			$object_ids = preg_replace('|[^0-9,]|', '', $object_ids);
			$object_ids = explode(',', $object_ids);
		}

		$object_ids = array_map('intval', $object_ids);

		$cache_key = $meta_type.'_meta';
		$ids = array();
		$cache = array();
		foreach ($object_ids as $id) {
			$cached_object = wp_cache_get($id, $cache_key);
			if (false === $cached_object) $ids[] = $id;
			else $cache[$id] = $cached_object;
		}

		if (empty($ids)) return $cache;

		// Get meta info
		$id_list = join(',', $ids);
		$meta_list = $wpdb->get_results($wpdb->prepare("SELECT $column, meta_key, meta_value FROM $table WHERE $column IN ($id_list)", $meta_type), ARRAY_A);

		if (!empty($meta_list)) {
			foreach ($meta_list as $metarow) {
				$mpid = intval($metarow[$column]);
				$mkey = $metarow['meta_key'];
				$mval = $metarow['meta_value'];

				// Force subkeys to be array type:
				if (!isset($cache[$mpid]) || !is_array($cache[$mpid])) $cache[$mpid] = array();
				if (!isset($cache[$mpid][$mkey]) || !is_array($cache[$mpid][$mkey])) $cache[$mpid][$mkey] = array();

				// Add a value to the current pid/key:
				$cache[$mpid][$mkey][] = $mval;
			}
		}

		foreach ($ids as $id) {
			if (!isset($cache[$id])) $cache[$id] = array();
			wp_cache_add($id, $cache[$id], $cache_key);
		}

		return $cache;
	}

	public static function _maybe_json_encode($data) {
		return @json_encode($data);
	}

	public static function _maybe_json_decode($str) {
		$d = @json_decode($str);
		return $d !== null || $str === 'null' ? $d : $str;
	}

	protected static function _get_table_name($meta_type) {
		global $wpdb;
		$table = $meta_type.'_meta';
		return isset($wpdb->$table) ? $wpdb->$table : apply_filters('qsot-meta-table-name', false, $meta_type);
	}
}

if (!function_exists('maybe_json_decode')):
	function maybe_json_decode($str) {
		$d = @json_decode($str);
		return $d !== null || $str === 'null' ? $d : $str;
	}
endif;

if (defined('ABSPATH') && function_exists('add_action')) {
	qsot_json_meta::pre_init();
}
