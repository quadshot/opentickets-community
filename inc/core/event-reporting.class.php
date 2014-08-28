<?php (__FILE__ == $_SERVER['SCRIPT_FILENAME']) ? die(header('Location: /')) : null;

class qsot_reporting {
	// holder for event plugin options
	protected static $o = null;
	protected static $options = null;

	public static function pre_init() {
		$settings_class_name = apply_filters('qsot-settings-class-name', '');
		if (!empty($settings_class_name)) {
			self::$o = call_user_func_array(array($settings_class_name, "instance"), array());

			// load all the options, and share them with all other parts of the plugin
			$options_class_name = apply_filters('qsot-options-class-name', '');
			if (!empty($options_class_name)) {
				self::$options = call_user_func_array(array($options_class_name, "instance"), array());
				//self::_setup_admin_options();
			}

			add_action('qsot_reports_charts', array(__CLASS__, 'extra_reports'), 10);
			//add_action('load-woocommerce_page_woocommerce_reports', array(__CLASS__, 'load_assets'), 10);
			add_action('load-toplevel_page_opentickets', array(__CLASS__, 'load_assets'), 10);
			add_action('init', array(__CLASS__, 'register_assets'), 10);

			add_action('wp_ajax_report_ajax', array(__CLASS__, 'process_ajax'), 10);

			add_action('qsot-above-report-html', array(__CLASS__, 'add_view_links'), 10, 3);
			add_action('qsot-below-report-html', array(__CLASS__, 'add_view_links'), 10, 3);
		}
	}

	public static function add_view_links($report_type, $req, $csv) {
		if (isset($req, $req['pf'])) return;
		$links = array();

		if (is_array($csv) && isset($csv['url']) && !empty($csv['url']))
			$links['csv'] = sprintf('<a href="%s" title="Download">%s</a>', esc_attr($csv['url']), 'Download CSV');

		if (isset($req)) {
			$link_data = array('pf' => 1);
			$link_data['showing'] = $req['showing'];
			if (isset($req['sort']) && !empty($req['sort'])) $link_data['sort'] = $req['sort'];
			$link = add_query_arg($link_data, $_SERVER['HTTP_REFERER']);
			$links['pf'] = sprintf('<a href="%s" title="%s" target="_blank">%s</a>', esc_attr($link), 'Printer Friendly Version', 'Printer Friendly Version');
		}
		?>
		<?php if (count($links)): ?>
			<div class="extra-actions">
				<?php echo implode(' | ', array_values($links)) ?>
			</div>
		<?php endif;
	}

	public static function process_ajax() {
		$report = $_POST['report'];
		$action = 'qsot-ajax-report-ajax'.(empty($report) ? '' : '-'.$report);
		if (has_action($action)) ini_set('max_execution_time', 1500);
		do_action($action);
	}

	public static function register_assets() {
		wp_register_script('qsot-report-ajax', self::$o->core_url.'assets/js/admin/report/ajax.js', array('qsot-tools', 'jquery-ui-datepicker'));
	}

	public static function load_assets() {
		wp_enqueue_script('qsot-report-ajax');
	}

	public static function extra_reports($reports) {
		$event_reports = apply_filters('qsot-reports', array());
		foreach ($event_reports as $name => $charts) {
			$slug = sanitize_title_with_dashes($name);
			$reports[$slug] = array(
				'title' => $name,
				'charts' => $charts,
			);
		}

		return $reports;
	}
}

abstract class qsot_admin_report {
	protected static $report_name = 'Report';
	protected static $report_slug = 'report';
	protected static $report_desc = '';

	protected static $csv_settings = array(
		'url' => '',
		'dir' => '',
		'enabled' => false,
	);

	public static function printer_friendly_header($args='') {
		define('IFRAME_REQUEST', true);
		// In case admin-header.php is included in a function.
		global $title, $hook_suffix, $current_screen, $wp_locale, $pagenow, $wp_version,
			$current_site, $update_title, $total_update_count, $parent_file;

		// Catch plugins that include admin-header.php before admin.php completes.
		if ( empty( $current_screen ) )
			set_current_screen();

		get_admin_page_title();
		$title = esc_html( strip_tags( $title ) );

		if ( is_network_admin() )
			$admin_title = __( 'Network Admin' );
		elseif ( is_user_admin() )
			$admin_title = __( 'Global Dashboard' );
		else
			$admin_title = get_bloginfo( 'name' );

		if ( $admin_title == $title )
			$admin_title = sprintf( __( '%1$s &#8212; WordPress' ), $title );
		else
			$admin_title = sprintf( __( '%1$s &lsaquo; %2$s &#8212; WordPress' ), $title, $admin_title );

		$admin_title = apply_filters( 'admin_title', $admin_title, $title );

		wp_user_settings();

		_wp_admin_html_begin();
		?>
		<title><?php echo $admin_title; ?></title>
		<?php

		wp_enqueue_style( 'colors' );
		wp_enqueue_style( 'ie' );
		wp_enqueue_script('utils');

		$admin_body_class = preg_replace('/[^a-z0-9_-]+/i', '-', $hook_suffix);
		?>
		<script type="text/javascript">
		addLoadEvent = function(func){if(typeof jQuery!="undefined")jQuery(document).ready(func);else if(typeof wpOnload!='function'){wpOnload=func;}else{var oldonload=wpOnload;wpOnload=function(){oldonload();func();}}};
		var ajaxurl = '<?php echo admin_url( 'admin-ajax.php', 'relative' ); ?>',
			pagenow = '<?php echo $current_screen->id; ?>',
			typenow = '<?php echo $current_screen->post_type; ?>',
			adminpage = '<?php echo $admin_body_class; ?>',
			thousandsSeparator = '<?php echo addslashes( $wp_locale->number_format['thousands_sep'] ); ?>',
			decimalPoint = '<?php echo addslashes( $wp_locale->number_format['decimal_point'] ); ?>',
			isRtl = <?php echo (int) is_rtl(); ?>;
		</script>
		<?php

		do_action('admin_enqueue_scripts', $hook_suffix);
		do_action("admin_print_styles-$hook_suffix");
		do_action('admin_print_styles');
		do_action("admin_print_scripts-$hook_suffix");
		do_action('admin_print_scripts');
		do_action("admin_head-$hook_suffix");
		do_action('admin_head');

		if ( get_user_setting('mfold') == 'f' )
			$admin_body_class .= ' folded';

		if ( !get_user_setting('unfold') )
			$admin_body_class .= ' auto-fold';

		if ( is_admin_bar_showing() )
			$admin_body_class .= ' admin-bar';

		if ( is_rtl() )
			$admin_body_class .= ' rtl';

		$admin_body_class .= ' branch-' . str_replace( array( '.', ',' ), '-', floatval( $wp_version ) );
		$admin_body_class .= ' version-' . str_replace( '.', '-', preg_replace( '/^([.0-9]+).*/', '$1', $wp_version ) );
		$admin_body_class .= ' admin-color-' . sanitize_html_class( get_user_option( 'admin_color' ), 'fresh' );
		$admin_body_class .= ' locale-' . sanitize_html_class( strtolower( str_replace( '_', '-', get_locale() ) ) );

		if ( wp_is_mobile() )
			$admin_body_class .= ' mobile';

		$admin_body_class .= ' no-customize-support';

		?>
		</head>
		<body class="wp-admin wp-core-ui no-js <?php echo apply_filters( 'admin_body_class', '' ) . " $admin_body_class"; ?>">
		<div id="wpwrap">
		<div class="inner-wrap" style="padding:8px;width:9.5in;">
		<?php
	}

	public static function printer_friendly_footer($args='') {
		?>
			</div>
			</div>
			</body>
			</html>
		<?php
	}

	protected static function _save_report_cache($order_id, $key, $data) {
		update_post_meta($order_id, '_report_cache_'.$key, $data);
	}

	protected static function _get_report_cache($order_id, $key) {
		return get_post_meta($order_id, '_report_cache_'.$key, true);
	}

	protected static function _inc_template($template, $_args) {
		extract($_args);
		$template = apply_filters('qsot-locate-template', '', $template, false, false);
		if (!empty($template)) include $template;
	}

	protected static function _csv($data, $req, $filename='') {
		$res = array(
			'file' => '',
			'url' => '',
		);

		if (!is_array($data) || !count($data)) return $res;

		self::_csv_location_check();

		if (!self::$csv_settings['enabled']) return $res;

		if (empty($filename)) {
			$user = wp_get_current_user();
			$filename = md5(@json_encode($req)).'-'.$user->ID.'.csv';
		}

		$filepath = self::$csv_settings['dir'].$filename;
		$fileurl = self::$csv_settings['url'].$filename;

		if (($f = fopen($filepath, 'w+'))) {
			$res['file'] = $filepath;
			$res['url'] = $fileurl;

			$first = array_shift(array_values($data));
			$headers = array_keys($first);
			fputcsv($f, $headers);

			foreach ($data as $row) fputcsv($f, $row);

			fclose($f);
		}

		return $res;
	}

	protected static $bad_path = '';
	public static function csv_path_notice() {
		if (self::$bad_path) {
			?><div class="error">
				Could not create the report cache directory.
				Make sure that the permissions for '<?php echo $path ?>' allow the webserver to create a directory, and try again.
			</div><?php
		}
	}

	protected static function _csv_location_check() {
		$res = self::$csv_settings['enabled'];

		if (!$res) {
			$uploads = wp_upload_dir();
			$path = $uploads['basedir'].'/report-cache/';
			if (!file_exists($path)) {
				if (!mkdir($path)) {
					self::$bad_path = $path;
					add_action('admin_notices', array(__CLASS__, 'csv_path_notice'));
				} else $res = true;
			} else if (is_writable($path)) $res = true;
			if ($res) {
				self::$csv_settings['dir'] = $path;
				self::$csv_settings['url'] = $uploads['baseurl'].'/report-cache/';
			}
			self::$csv_settings['enabled'] = $res;
		}
	}

	public static function _by_billing_info($a, $b) {
		$aln = strtolower($a['billing_last_name']);
		$bln = strtolower($b['billing_last_name']);
		if ($aln < $bln) return -1;
		else if ($aln > $bln) return 1;
		else {
			$afn = strtolower($a['billing_first_name']);
			$bfn = strtolower($b['billing_first_name']);
			return $afn < $bfn ? -1 : 1;
		}
	}

	protected static function _memory_check($flush_percent_range=80) {
		global $wpdb;
		static $max = false;
		$dec = $flush_percent_range / 100;

		if ($max === false) {
			$raw = ini_get('memory_limit');
			preg_match_all('#^(\d+)(\s*)?$#', $raw, $matches, PREG_SET_ORDER);
			if (isset($matches[0])) {
				$max = $matches[0][1];
				$unit = $matches[0][2];
				switch (strtolower($unit)) {
					case 'k': $max *= 1024; break;
					case 'm': $max *= 1048576; break;
					case 'g': $max *= 1073741824; break;
				}
			} else {
				$max = 32 * 1048576;
			}
		}

		$usage = memory_get_usage();
		if ($usage > $max * $dec) {
			wp_cache_flush();
			$wpdb->queries = array();
		}
	}
}

if (!function_exists('qsot_datepicker_js')):
function qsot_datepicker_js() {
	global $woocommerce;
	?>
	var dates = jQuery( "#from, #to" ).datepicker({
		defaultDate: "",
		dateFormat: "yy-mm-dd",
		numberOfMonths: 1,
		maxDate: "+0D",
		showButtonPanel: true,
		showOn: "button",
		buttonImage: "<?php echo $woocommerce->plugin_url(); ?>/assets/images/calendar.png",
		buttonImageOnly: true,
		onSelect: function( selectedDate ) {
			var option = this.id == "from" ? "minDate" : "maxDate",
				instance = jQuery( this ).data( "datepicker" ),
				date = jQuery.datepicker.parseDate(
					instance.settings.dateFormat ||
					jQuery.datepicker._defaults.dateFormat,
					selectedDate, instance.settings );
			dates.not( this ).datepicker( "option", option, date );
		}
	});
	<?php
}
endif;

if (defined('ABSPATH') && function_exists('add_action')) {
	qsot_reporting::pre_init();
}
