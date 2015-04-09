<?php (__FILE__ == $_SERVER['SCRIPT_FILENAME']) ? die(header('Location: /')) : null;

if (!class_exists('qsot_db_upgrader')):

class qsot_db_upgrader {
	protected static $_version = '0.1.1';
	protected static $upgrade_messages = array();
	protected static $on_header = array();
	protected static $_table_versions_key = '_qsot_upgrader_db_table_versions';

	public static function pre_init() {
		add_action('admin_init', array(__CLASS__, 'admin_init'), 100);
	}

	public static function admin_init() {
		self::_maybe_update_db();
	}

	protected static function _maybe_update_db() {
		global $wpdb, $charset_collate;

		$versions = get_option(self::$_table_versions_key, array());
		$tables = array();
		$tables = apply_filters('qsot-upgrader-table-descriptions', $tables);

		$existing_tables = $wpdb->get_col( 'show tables' );

		$needs_update = false;
		foreach ( $tables as $tname => $table ) {
			if ( isset( $table['version'] ) && ( ! in_array( $tname, $existing_tables ) || ! isset( $versions[ $tname ] ) || version_compare( $versions[ $tname ], $table['version'] ) < 0 ) ) {
				$needs_update = true;
				break;
			}
		}
		
		if (!$needs_update) return;

		include_once trailingslashit(ABSPATH).'wp-admin/includes/upgrade.php';

		$pre_sql = $sql = array();
		$utabs = array();
		foreach ( $tables as $table_name => &$desc ) {
			if ( isset( $desc['version'] ) && ( ! in_array( $table_name, $existing_tables ) || ! isset( $versions[ $table_name] ) || version_compare( $versions[ $table_name ], $desc['version'] ) < 0 ) ) {
				if (isset($desc['fields']) && is_array($desc['fields']) && !empty($desc['fields']) && isset($desc['keys']) && is_array($desc['keys']) && !empty($desc['keys'])) {
					$pre_update = isset( $desc['pre-update'] ) ? $desc['pre-update'] : array();
					$pre_update_sql = self::_pre_update_sql( $pre_update, $table_name, $existing_tables, $versions, $desc );
					$pre_sql = array_merge( $pre_sql, $pre_update_sql );

					$fields = $desc['fields'];
					$keys = $desc['keys'];
					$sql_fields = array();
					foreach ($fields as $name => $field) {
						$fields[$name] = $field = wp_parse_args($field, array('type' => 'int(10)', 'null' => 'no', 'default' => '', 'extra' => ''));
						$sql_fields[] = sprintf(
							'%s %s %s %s %s',
							$name,
							$field['type'],
							$field['null'] == 'no' ? 'not null' : 'null',
							self::_default($field['default'], $field['type']),
							$field['extra']
						);
					}
					$desc['fields'] = $fields;

					$sql[] = "CREATE TABLE {$table_name} (\n".implode(",\n", $sql_fields).",\n".implode(",\n", $keys)."\n)$charset_collate;";
					$utabs[] = $table_name;
				}
			}
		}

		if ( ! empty( $pre_sql ) ) {
			foreach ( $pre_sql as $q ) 
				$wpdb->query( $q );
		}

		self::$upgrade_messages[] = sprintf(__('The DB tables [%s] are not at the most current versions. Attempting to upgrade them.','opentickets-community-edition'), implode(', ', $utabs));
		dbDelta($sql);

		if (is_admin() && isset($_GET['debug_delta']) && $_GET['debug_delta'] == 9999) {
			global $EZSQL_ERROR;
			self::$on_header = array('sql' => $sql, 'errors' => $EZSQL_ERROR);
			add_action('admin_notices', array(__CLASS__, 'a_debug'), 50);
		}

		foreach ($utabs as $table_name) {
			$table_desc = $tables[$table_name];
			if (
					isset($table_desc['version']) &&
					isset($table_desc['fields']) && is_array($table_desc['fields']) && !empty($table_desc['fields']) &&
					isset($table_desc['keys']) && is_array($table_desc['keys']) && !empty($table_desc['keys'])
			) {
				$fields = $table_desc['fields'];
				$keys = $table_desc['keys'];

				$res = $wpdb->get_results('describe '.$table_name);
				if (!is_array($res)) return;
				$readable = array();
				foreach ($res as $row) $readable[$row->Field] = $row;

				$pass = true;
				$reason = false;
				foreach ($fields as $name => $field) {
					if (!isset($readable[$name])) {
						$pass = false;
						$reason = 'readable not set ['.$name.'] '.var_export($readable, true);
						break;
					}
					$found = $readable[$name];
					if (strtolower(trim($field['type'])) != strtolower(trim($found->Type))) {
						$pass = false;
						$reason = __('types dont match','opentickets-community-edition').'  ['.$name.'] ['.strtolower(trim($field['type'])).'] : ['.strtolower(trim($found->Type)).']';
						break;
					}
					if (strtolower(trim($field['null'])) != strtolower(trim($found->Null))) {
						$pass = false;
						$reason = __('nulls dont match','opentickets-community-edition').' ['.$name.'] ['.strtolower(trim($field['null'])).'] : ['.strtolower(trim($found->Null)).']';
						break;
					}
					if (strtolower(trim($field['extra'])) != strtolower(trim($found->Extra))) {
						$pass = false;
						$reason = __('extras dont match','opentickets-community-edition').' ['.$name.'] ['.strtolower(trim($field['extra'])).'] : ['.strtolower(trim($found->Extra)).']';
						break;
					}
				}

				if (!$pass) {
					self::$upgrade_messages[] = sprintf(__('Update to DB, table [%s], was NOT successful. %s','opentickets-community-edition'), $table_name, "<pre>$reason</pre>");
				} else {
					do_action('qsot-db-upgrade-'.$table_name.'-success', $table_desc['version']);
					self::$upgrade_messages[] = sprintf(__('Update to DB, table [%s], was successful.','opentickets-community-edition'), $table_name);
					if (isset($table_desc['version']))
						$versions[$table_name] = $table_desc['version'];
				}
			}
		}
		update_option(self::$_table_versions_key, $versions);
		add_action('admin_notices', array(__CLASS__, 'a_admin_update_notice'));
	}

	protected static function _pre_update_sql( $pre_update, $table_name, $existing_tables, $versions, $desc ) {
		if ( empty( $pre_update ) ) return array();
		$out = array();
		
		if ( is_array( $pre_update ) ) foreach ( $pre_update as $type => $conditions ) {
			switch ( $type ) {
				case 'when':
					if ( is_array( $conditions ) ) foreach ( $conditions as $condition => $scenarios ) {
						switch ( $condition ) {
							case 'always':
								if ( is_array( $scenarios ) ) foreach ( $scenarios as $value => $run ) {
									if ( @is_callable( $run ) ) call_user_func( $run, isset( $versions[ $table_name ] ) ? $versions[ $table_name ] : '0.0.0' );
									else if ( is_string( $run ) ) $out[] = $run . ';';
								}
							break;

							case 'exists':
								if ( is_array( $scenarios ) ) foreach ( $scenarios as $value => $run ) {
									if ( in_array( $table_name, $existing_tables ) ) {
										if ( @is_callable( $run ) ) call_user_func( $run, isset( $versions[ $table_name ] ) ? $versions[ $table_name ] : '0.0.0' );
										else if ( is_string( $run ) ) $out[] = $run . ';';
									}
								}
							break;

							case 'version <':
								if ( is_array( $scenarios ) ) foreach ( $scenarios as $value => $run ) {
									if ( ! in_array( $table_name, $existing_tables ) || ! isset( $versions[ $table_name ] ) || version_compare( $versions[ $table_name ], $value ) < 0 ) {
										if ( @is_callable( $run ) ) call_user_func( $run, isset( $versions[ $table_name ] ) ? $versions[ $table_name ] : '0.0.0' );
										else if ( is_string( $run ) ) $out[] = $run . ';';
									}
								}
							break;

							case 'version >':
								if ( is_array( $scenarios ) ) foreach ( $scenarios as $value => $run ) {
									if ( in_array( $table_name, $existing_tables ) && isset( $versions[ $table_name ] ) && version_compare( $versions[ $table_name ], $value ) > 0 ) {
										if ( @is_callable( $run ) ) call_user_func( $run, isset( $versions[ $table_name ] ) ? $versions[ $table_name ] : '0.0.0' );
										else if ( is_string( $run ) ) $out[] = $run . ';';
									}
								}
							break;
						}
					}
				break;
			}
		}

		return $out;
	}

	protected static function _default($v, $t) {
		$noescape = array('CURRENT_TIMESTAMP');
		
		$def = '';
		if (preg_match('#(int|decimal|float|double)#', $t) != 0) $def = '0';
		elseif (preg_match('#(date|time)#', $t) != 0) $def = '0000-00-00 00:00:00';

		if (in_array($v, $noescape)) $v = "default {$v}";
		elseif (($c = preg_replace('#^CONST:\|([^\|]+)\|$#', '\1', $v)) && $c != $v) $v = "default {$c}";
		elseif (($f = preg_replace('#^FUNC:\|([^\|]+)\|$#', '\1', $v)) && $f != $v) $v = "default {$f}";
		elseif ($v === '') $v = '';
		else $v = "default '{$def}'";

		return $v;
	}

	public static function a_admin_update_notice() {
		?>
		<?php if (!empty(self::$upgrade_messages)): ?>
			<div class="updated" id="lou-notes-update-msg">
				<?php foreach (self::$upgrade_messages as $msg): ?>
					<p><?= $msg ?></p>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
		<?php
	}

	public static function a_debug() {
		?>
		<?php if (!empty(self::$on_header)): ?>
			<?= var_dump(self::$on_header) ?>
		<?php endif; ?>
		<?php
	}
}

if (defined('ABSPATH') && function_exists('add_action')) {
	qsot_db_upgrader::pre_init();
}

endif;

/*
// exmple of interfacing with this class

class example_class {
	public static function pre_init() {
    global $wpdb;
    $wpdb->example_table = $wpdb->base_prefix.'example_table';
    $wpdb->example_table_meta = $wpdb->base_prefix.'example_table_meta';
    add_filter('qsot-upgrader-table-descriptions', array(__CLASS__, 'setup_db_tables'), 10); 
  }

  public static function setup_db_tables($tables) {
    global $wpdb;
    $tables[$wpdb->seating_chart_meta] = array(
      'version' => '0.1.0',
      'fields' => array(
        'meta_id' => array('type' => 'bigint(20) unsigned', 'extra' => 'auto_increment'),
        'example_table_id' => array('type' => 'bigint(20) unsigned', 'default' => '0'),
        'meta_key' => array('type' => 'varchar(255)'),
        'meta_value' => array('type' => 'text'),
      ),   
      'keys' => array(
        'PRIMARY KEY  (meta_id)',
        'KEY et_id (example_table_id)',
        'KEY mk (meta_key)',
      )    
    );   
    $tables[$wpdb->seating_chart_seat_meta] = array(
      'version' => '0.1.1',
      'fields' => array(
        'id' => array('type' => 'bigint(20) unsigned', 'extra' => 'auto_increment'),
        'example_item_slug' => array('type' => 'varchar(255)'),
        'example_item_title' => array('type' => 'varchar(255)'),
        'example_item_content' => array('type' => 'text'),
      ),   
      'keys' => array(
        'PRIMARY KEY  (id)',
        'KEY slug (example_item_slug)',
      )    
    );   

    return $tables;
	}
}

if (defined('ABSPATH') && function_exists('add_action')) {
	example_class::pre_init();
}

*/
