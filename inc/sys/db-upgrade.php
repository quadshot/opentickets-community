<?php if ( __FILE__ == $_SERVER['SCRIPT_FILENAME'] ) die( header( 'Location: /') );

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

		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			$ERROR_STATUS = $wpdb->show_errors;
			$wpdb->show_errors = false;
		}

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
		self::dbDelta($sql);

		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			$wpdb->show_errors = $ERROR_STATUS;
		}

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
		if ( WP_DEBUG )
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

	/** COPIED FROM /wp-admin/includes/upgrade.php @ version 4.2.3 -- modified to handle Null updates
	 * Modifies the database based on specified SQL statements.
	 *
	 * Useful for creating new tables and updating existing tables to a new structure.
	 *
	 * @since 1.5.0
	 *
	 * @param string|array $queries Optional. The query to run. Can be multiple queries
	 *                              in an array, or a string of queries separated by
	 *                              semicolons. Default empty.
	 * @param bool         $execute Optional. Whether or not to execute the query right away.
	 *                              Default true.
	 * @return array Strings containing the results of the various update queries.
	 */
	public static function dbDelta( $queries = '', $execute = true ) {
		global $wpdb;

		if ( in_array( $queries, array( '', 'all', 'blog', 'global', 'ms_global' ), true ) )
				$queries = wp_get_db_schema( $queries );

		// Separate individual queries into an array
		if ( !is_array($queries) ) {
			$queries = explode( ';', $queries );
			$queries = array_filter( $queries );
		}

		/**
		 * Filter the dbDelta SQL queries.
		 *
		 * @since 3.3.0
		 *
		 * @param array $queries An array of dbDelta SQL queries.
		 */
		$queries = apply_filters( 'dbdelta_queries', $queries );

		$cqueries = array(); // Creation Queries
		$iqueries = array(); // Insertion Queries
		$for_update = array();

		// Create a tablename index for an array ($cqueries) of queries
		foreach($queries as $qry) {
			if ( preg_match( "|CREATE TABLE ([^ ]*)|", $qry, $matches ) ) {
				$cqueries[ trim( $matches[1], '`' ) ] = $qry;
				$for_update[$matches[1]] = 'Created table '.$matches[1];
			} elseif ( preg_match( "|CREATE DATABASE ([^ ]*)|", $qry, $matches ) ) {
				array_unshift( $cqueries, $qry );
			} elseif ( preg_match( "|INSERT INTO ([^ ]*)|", $qry, $matches ) ) {
				$iqueries[] = $qry;
			} elseif ( preg_match( "|UPDATE ([^ ]*)|", $qry, $matches ) ) {
				$iqueries[] = $qry;
			} else {
				// Unrecognized query type
			}
		}

		/**
		 * Filter the dbDelta SQL queries for creating tables and/or databases.
		 *
		 * Queries filterable via this hook contain "CREATE TABLE" or "CREATE DATABASE".
		 *
		 * @since 3.3.0
		 *
		 * @param array $cqueries An array of dbDelta create SQL queries.
		 */
		$cqueries = apply_filters( 'dbdelta_create_queries', $cqueries );

		/**
		 * Filter the dbDelta SQL queries for inserting or updating.
		 *
		 * Queries filterable via this hook contain "INSERT INTO" or "UPDATE".
		 *
		 * @since 3.3.0
		 *
		 * @param array $iqueries An array of dbDelta insert or update SQL queries.
		 */
		$iqueries = apply_filters( 'dbdelta_insert_queries', $iqueries );

		$global_tables = $wpdb->tables( 'global' );
		foreach ( $cqueries as $table => $qry ) {
			// Upgrade global tables only for the main site. Don't upgrade at all if DO_NOT_UPGRADE_GLOBAL_TABLES is defined.
			if ( in_array( $table, $global_tables ) && ( !is_main_site() || defined( 'DO_NOT_UPGRADE_GLOBAL_TABLES' ) ) ) {
				unset( $cqueries[ $table ], $for_update[ $table ] );
				continue;
			}

			// Fetch the table column structure from the database
			$suppress = $wpdb->suppress_errors();
			$tablefields = $wpdb->get_results("DESCRIBE {$table};");
			$wpdb->suppress_errors( $suppress );

			if ( ! $tablefields )
				continue;

			// Clear the field and index arrays.
			$cfields = $indices = array();

			// Get all of the field names in the query from between the parentheses.
			preg_match("|\((.*)\)|ms", $qry, $match2);
			$qryline = trim($match2[1]);

			// Separate field lines into an array.
			$flds = explode("\n", $qryline);

			// todo: Remove this?
			//echo "<hr/><pre>\n".print_r(strtolower($table), true).":\n".print_r($cqueries, true)."</pre><hr/>";

			// For every field line specified in the query.
			foreach ($flds as $fld) {

				// Extract the field name.
				preg_match("|^([^ ]*)|", trim($fld), $fvals);
				$fieldname = trim( $fvals[1], '`' );

				// Verify the found field name.
				$validfield = true;
				switch (strtolower($fieldname)) {
				case '':
				case 'primary':
				case 'index':
				case 'fulltext':
				case 'unique':
				case 'key':
					$validfield = false;
					$indices[] = trim(trim($fld), ", \n");
					break;
				}
				$fld = trim($fld);

				// If it's a valid field, add it to the field array.
				if ($validfield) {
					$cfields[strtolower($fieldname)] = trim($fld, ", \n");
				}
			}

			// For every field in the table.
			foreach ($tablefields as $tablefield) {

				// If the table field exists in the field array ...
				if (array_key_exists(strtolower($tablefield->Field), $cfields)) {

					// Get the field type from the query.
					preg_match("|".$tablefield->Field." ([^ ]*( unsigned)?)|i", $cfields[strtolower($tablefield->Field)], $matches);
					$fieldtype = $matches[1];

					// @@@@LOUSHOU - get the null status of the column
					$currentnull = strtolower( $tablefield->Null );
					$fieldisnull = ( ! preg_match( '#(not\s+null)#i', $cfields[ strtolower( $tablefield->Field ) ] ) ) ? 'yes' : 'no';
					$fieldnull = ( 'yes' == $fieldisnull ) ? '' : 'NOT NULL';

					// Is actual field type different from the field type in query?
					if ($tablefield->Type != $fieldtype) {
						// Add a query to change the column type
						// @@@@LOUSHOU - changed query to actually update the field, and added the ability to change the null status
						$cqueries[] = "ALTER TABLE {$table} MODIFY {$tablefield->Field} {$fieldtype} {$fieldnull}";
						$for_update[$table.'.'.$tablefield->Field] = "Changed type of {$table}.{$tablefield->Field} from {$tablefield->Type} to {$fieldtype}"
								. ( $currentnull != $fieldisnull ? ", and null from {$currentnull} to {$fieldisnull}" : '' );
					// @@@@LOUSHOU - added else if to handle non-changed type, but changed null scenario
					} else if ( $currentnull != $fieldisnull )  {
						$cqueries[] = "ALTER TABLE {$table} MODIFY {$tablefield->Field} {$tablefield->Type} {$fieldnull}";
						$for_update[$table.'.'.$tablefield->Field] = "Changed null of {$table}.{$tablefield->Field} from {$currentnull} to {$fieldisnull}";
					}

					// Get the default value from the array
						// todo: Remove this?
						//echo "{$cfields[strtolower($tablefield->Field)]}<br>";
					if (preg_match("| DEFAULT '(.*?)'|i", $cfields[strtolower($tablefield->Field)], $matches)) {
						$default_value = $matches[1];
						if ($tablefield->Default != $default_value) {
							// Add a query to change the column's default value
							$cqueries[] = "ALTER TABLE {$table} ALTER COLUMN {$tablefield->Field} SET DEFAULT '{$default_value}'";
							$for_update[$table.'.'.$tablefield->Field] = "Changed default value of {$table}.{$tablefield->Field} from {$tablefield->Default} to {$default_value}";
						}
					}

					// Remove the field from the array (so it's not added).
					unset($cfields[strtolower($tablefield->Field)]);
				} else {
					// This field exists in the table, but not in the creation queries?
				}
			}

			// For every remaining field specified for the table.
			foreach ($cfields as $fieldname => $fielddef) {
				// Push a query line into $cqueries that adds the field to that table.
				$cqueries[] = "ALTER TABLE {$table} ADD COLUMN $fielddef";
				$for_update[$table.'.'.$fieldname] = 'Added column '.$table.'.'.$fieldname;
			}

			// Index stuff goes here. Fetch the table index structure from the database.
			$tableindices = $wpdb->get_results("SHOW INDEX FROM {$table};");

			if ($tableindices) {
				// Clear the index array.
				$index_ary = array();

				// For every index in the table.
				foreach ($tableindices as $tableindex) {

					// Add the index to the index data array.
					$keyname = $tableindex->Key_name;
					$index_ary[$keyname]['columns'][] = array('fieldname' => $tableindex->Column_name, 'subpart' => $tableindex->Sub_part);
					$index_ary[$keyname]['unique'] = ($tableindex->Non_unique == 0)?true:false;
				}

				// For each actual index in the index array.
				foreach ($index_ary as $index_name => $index_data) {

					// Build a create string to compare to the query.
					$index_string = '';
					if ($index_name == 'PRIMARY') {
						$index_string .= 'PRIMARY ';
					} elseif ( $index_data['unique'] ) {
						$index_string .= 'UNIQUE ';
					}
					$index_string .= 'KEY ';
					if ($index_name != 'PRIMARY') {
						$index_string .= $index_name;
					}
					$index_columns = '';

					// For each column in the index.
					foreach ($index_data['columns'] as $column_data) {
						if ($index_columns != '') $index_columns .= ',';

						// Add the field to the column list string.
						$index_columns .= $column_data['fieldname'];
						if ($column_data['subpart'] != '') {
							$index_columns .= '('.$column_data['subpart'].')';
						}
					}

					// The alternative index string doesn't care about subparts
					$alt_index_columns = preg_replace( '/\([^)]*\)/', '', $index_columns );

					// Add the column list to the index create string.
					$index_strings = array(
						"$index_string ($index_columns)",
						"$index_string ($alt_index_columns)",
					);

					foreach( $index_strings as $index_string ) {
						if ( ! ( ( $aindex = array_search( $index_string, $indices ) ) === false ) ) {
							unset( $indices[ $aindex ] );
							break;
							// todo: Remove this?
							//echo "<pre style=\"border:1px solid #ccc;margin-top:5px;\">{$table}:<br />Found index:".$index_string."</pre>\n";
						}
					}
					// todo: Remove this?
					//else echo "<pre style=\"border:1px solid #ccc;margin-top:5px;\">{$table}:<br /><b>Did not find index:</b>".$index_string."<br />".print_r($indices, true)."</pre>\n";
				}
			}

			// For every remaining index specified for the table.
			foreach ( (array) $indices as $index ) {
				// Push a query line into $cqueries that adds the index to that table.
				$cqueries[] = "ALTER TABLE {$table} ADD $index";
				$for_update[] = 'Added index ' . $table . ' ' . $index;
			}

			// Remove the original table creation query from processing.
			unset( $cqueries[ $table ], $for_update[ $table ] );
		}

		$allqueries = array_merge($cqueries, $iqueries);
		if ($execute) {
			foreach ($allqueries as $query) {
				// todo: Remove this?
				//echo "<pre style=\"border:1px solid #ccc;margin-top:5px;\">".print_r($query, true)."</pre>\n";
				$wpdb->query($query);
			}
		}

		return $for_update;
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
