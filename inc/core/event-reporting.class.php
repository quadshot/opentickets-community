<?php if ( __FILE__ == $_SERVER['SCRIPT_FILENAME'] ) die( header( 'Location: /') );

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

			add_action( 'qsot_admin_reports', array( __CLASS__, 'extra_reports' ), 10 );
			//add_action('load-woocommerce_page_woocommerce_reports', array(__CLASS__, 'load_assets'), 10);
			add_action( 'load-toplevel_page_opentickets', array( __CLASS__, 'load_assets' ), 10 );
			add_action('init', array(__CLASS__, 'register_assets'), 10);

			// handle the reporting ajaz request
			$aj = QSOT_Ajax::instance();
			add_action( 'wp_ajax_qsot-admin-report-ajax', array( &$aj, 'handle_request' ) );
			add_action( 'wp_ajax_nopriv_qsot-admin-report-ajax', array( &$aj, 'handle_request' ) );

			// add the printerfriendly links to the report link output
			add_action( 'qsot-report-links', array( __CLASS__, 'add_view_links' ), 10, 2 );
		}
	}

	// add the extra view links to the top and bottom of the report results tables. for now this is just printerfriendly links
	public static function add_view_links( $csv_file, $report ) {
		// if this is the printerfriendly version, then dont add the links
		if ( $report->is_printer_friendly() )
			return;

		// construct the printer friendly url
		$url = $report->printer_friendly_url( $csv_file, $report );

		// add the printer-friendly link
		echo sprintf(
			' | <a href="%s" title="%s" target="_blank">%s</a>',
			$url,
			__( 'View a printer-friendly version of this report.', 'opentickets-community-edition' ),
			__( 'Printer-Friendly Report', 'opentickets-community-edition' )
		);
	}

	// register all the scripts and css that may be used on the basic reporting pages
	public static function register_assets() {
		// reuseable bits
		$url = QSOT::plugin_url();
		$version = QSOT::version();

		// register the js
		wp_register_script( 'qsot-report-ajax', $url . 'assets/js/admin/report/ajax.js', array( 'qsot-tools', 'jquery-ui-datepicker', 'tablesorter' ), $version );
	}

	// tell wordpress to load the assets we previously registered
	public static function load_assets() {
		wp_enqueue_script( 'qsot-report-ajax' );
		wp_localize_script( 'qsot-report-ajax', '_qsot_report_ajax', array(
			'_n' => wp_create_nonce( 'do-qsot-admin-report-ajax' ),
			'str' => array(
				'Loading...' => __( 'Loading...', 'opentickets-community-edition' ),
			),
		) );
	}

	public static function extra_reports($reports) {
		$event_reports = (array)apply_filters('qsot-reports', array());
		foreach ($event_reports as $slug => $settings) {
			if (!isset($settings['charts']) || empty($settings['charts'])) continue;
			$name = isset($settings['title']) ? $settings['title'] : $slug;
			$slug = sanitize_title_with_dashes($slug);
			$reports[$slug] = array(
				'title' => $name,
				'charts' => $settings['charts'],
			);
		}

		return $reports;
	}
}

// the base report class. creates a shell of all the functionality every report needs, and allows the reports themselves to do the heavy lifting
abstract class QSOT_Admin_Report {
	protected static $report_index = 0;

	protected $order = 10; // report order
	protected $group_name = ''; // display name of the group this report belongs to
	protected $group_slug = ''; // unique slug of the group this report belongs to
	protected $name = ''; // display name of the report
	protected $slug = ''; // unique slug of the report
	protected $description = ''; // short description of this report

	// setup the core object
	public function __construct() {
		// setup the default basic report info
		self::$report_index++;
		$this->group_name = sprintf( __( 'Report %s', 'opentickets-community-edition' ), self::$report_index );
		$this->group_slug = 'report-' . self::$report_index;
		$this->name = sprintf( __( 'Report %s', 'opentickets-community-edition' ), self::$report_index );
		$this->slug = 'report-' . self::$report_index;

		// add this object as a report
		add_filter( 'qsot-reports', array( &$this, 'register_report' ), $this->order );

		// allow reports to do some independent initialization
		$this->init();
	}

	// getter for slug, name and description
	public function slug() { return $this->slug; }
	public function name() { return $this->name; }
	public function description() { return $this->description; }

	// overrideable function to allow additional initializations
	public function init() {}

	// generic ajax processing function, which should be overridden by reports that use ajax
	protected function _process_ajax() {}

	// validate and pass on the ajax requests for this report
	public function handle_ajax() {
		// if the current user does not have permissions to run the report, then bail
		//if ( ! current_user_can( 'view_woocommerce_reports' ) )
			//return $this->_error( new WP_Error( 'no_permission', __( 'You do not have permission to use this report.', 'opentickets-community-edition' ) ) );

		// if the ajax request does not validate, then bail
		//if ( ! $this->_verify_run_report( true ) )
			//return $this->_error( new WP_Error( 'no_permission', __( 'You do not have permission to use this report.', 'opentickets-community-edition' ) ) );

		// pass the request on to the processing function 
		$this->_process_ajax();
	}

	// register this report, with our report list
	public function register_report( $list ) {
		// add the main key for this report, which we will then add the actual report to.
		// this structure is snatched from WC, which will allow for report grouping in later versions
		$list[ $this->group_slug ] = isset( $list[ $this->group_slug ] ) ? $list[ $this->group_slug ] : array( 'title' => $this->group_name, 'charts' => array() );

		// now add this specific report chart to the group
		$list[ $this->group_slug ]['charts'][ $this->slug ] = array(
			'title' => $this->name,
			'description' => $this->description,
			'function' => array( &$this, 'show_shell' ),
			'pf_function' => array( &$this, 'printer_friendly' ),
		);

		return $list;
	}

	// show the report page shell
	public function show_shell() {
		// if the current user does not have permissions to run the report, then bail
		if ( ! current_user_can( 'view_woocommerce_reports' ) )
			return $this->_error( new WP_Error( 'no_permission', __( 'You do not have permission to use this report.', 'opentickets-community-edition' ) ) );

		// draw the shell of the form, and allow the individual report to specify some fields
		?>
			<div class="report-form" id="report-form"><?php $this->_form() ?></div>
			<div class="report-results" id="report-results"><?php $this->_results() ?></div>
		<?php
	}

	// determine if this request is a printerfriendly request
	public function is_printer_friendly() {
		return isset( $_GET['pf'] ) && 1 == $_GET['pf'];
	}

	// construct a printer friendly url for this report
	public function printer_friendly_url( $csv_file, $report ) {
		return add_query_arg(
			array( 'pf' => 1, 'tab' => $this->slug, '_n' => wp_create_nonce( 'do-qsot-admin-report-ajax' ) ),
			admin_url( apply_filters( 'qsot-get-menu-page-uri', '', 'main', true ) )
		);
	}

	// draw the actual form shell, and allow the individual report to control the fields
	protected function _form() {
		?>
			<form method="post" action="<?php echo esc_attr( remove_query_arg( array( 'updated' ) ) ) ?>" class="qsot-ajax-form">
				<input type="hidden" name="_n" value="<?php echo esc_attr( wp_create_nonce( 'do-qsot-admin-report-ajax' ) ) ?>" />
				<input type="hidden" name="sa" value="<?php echo esc_attr( $this->slug ) ?>" />

				<?php $this->form() ?>
			</form>
		<?php
	}

	// verify that we should be running the report right now, based on the submitted data
	protected function _verify_run_report( $only_orig=false ) {
		// if the nonce or report name is not set, bail
		if ( ! isset( $_REQUEST['_n'], $_REQUEST['sa'] ) )
			return false;

		// if the report name does not match this report, bail
		if ( $_REQUEST['sa'] !== $this->slug )
			return false;

		// if the nonce does not match, then bail
		if ( ! wp_verify_nonce( $_REQUEST['_n'], 'do-qsot-admin-report-ajax' ) )
			return false;

		return true;
	}

	// draw any errors that are passed
	protected function _error( WP_Error $error ) {
		?>
			<div class="report-errors">
				<?php foreach ( $error->get_error_codes() as $code ): ?>
					<?php foreach ( $error->get_error_messages( $code ) as $message ): ?>
						<div class="error"><?php echo force_balance_tags( $message ) ?></div>
					<?php endforeach; ?>
				<?php endforeach; ?>
			</div>
		<?php
	}

	// start the process of generating the results
	protected function _results() {
		// if the report is not supposed to run yet, then bail
		if ( ! $this->_verify_run_report() )
			return;

		// start the csv output file. if that fails, there is no point in continuing
		if ( ! ( $csv_file = $this->_open_csv_file( '', '', true ) ) )
			return $this->_error( new WP_Error( 'no_csv_file', __( 'Could not open the CSV file path. Aborting report generation.', 'opentickets-community-edition' ) ) );
		elseif ( is_wp_error( $csv_file ) )
			return $this->_error( $csv_file );

		// tell the report is about to start running
		$this->_starting();

		// add the header row to the csv
		$this->_csv_header_row( $csv_file );

		// draw the csv link
		$this->_csv_link( $csv_file );

		// draw the html version header
		$this->_html_report_header();

		$all_html_rows = 0;
		// run the report, while there are still rows to process
		while ( $group = $this->more_rows() )
			$all_html_rows += $this->_handle_row_group( $group, $csv_file );

		// before we close the footer, allow reportss to add some logic
		$this->_before_html_footer( $all_html_rows );

		// draw the html version footer
		$this->_html_report_footer();

		// draw the csv link
		$this->_csv_link( $csv_file );

		// close the csv file
		$this->_close_csv_file( $csv_file );

		// tell the report that it is done running
		$this->_finished();
	}

	// handle the subgroup of rows, while running the report. return the number of rows we generated
	protected function _handle_row_group( $group, $csv_file ) {
		// gather all the information that is used to create both csv and html versions of the report, for the found rows
		$data = $this->aggregate_row_data( $group );

		// add this group of results to the csv report
		$this->_csv_render_rows( $data, $csv_file );

		// render the html table rows for this group
		$all_html_rows = $this->_html_report_rows( $data );

		// clean up the memory
		$this->_clean_memory();

		return $all_html_rows;
	}

	// start and finish functions, overrideable by the individual report
	protected function _starting() {}
	protected function _finished() {}

	// allow reports to add stuff to the bottom of the table if needed
	protected function _before_html_footer( $all_html_rows ) {
		// if no html rows were printed, then print a row indicating that
		if ( empty( $all_html_rows ) ) {
			$columns = count( $this->html_report_columns() );
			echo '<tr><td colspan="' . $columns . '">' . __( 'There are no tickts sold for this event yet.', 'opentickets-community-edition' ) . '</td></tr>';
		}
	}

	// because this can accumulate a lot of memory usage over time, we need to occassionally clear out our internal caches to compensate
	protected function _clean_memory() {
		global $wpdb, $wp_object_cache;
		// clear our the query cache, cause it can be huge
		$wpdb->flush();

		// clear out the wp_cache cache, if we are using the core wp method, which is an internal associative array
		if ( isset( $wp_object_cache->cache ) && is_array( $wp_object_cache->cache ) ) {
			unset( $wp_object_cache->cache );
			$wp_object_cache->cache = array();
		}
	}

	// render the group of resulting data as rows for our output table
	protected function _html_report_rows( $group ) {
		$total = 0;
		// get our list of html columns
		$columns = $this->html_report_columns();
		$cnt = count( $columns );

		// cycle through the group of resulting rows, and draw the table row for each
		if ( is_array( $group ) ) foreach ( $group as $row ) {
			$total =+ $this->_html_report_row( $row, $columns, $cnt );
		}

		return $total;
	}

	// render a single report row, based on some supplied row data
	protected function _html_report_row( $row, $columns=false, $cnt=false ) {
		// normalize the input
		if ( empty( $columns ) ) {
			$columns = $this->html_report_columns();
			$cnt = count( $columns );
		}

		$data = array();
		// cycle through thre required columns, and aggregate only the data we need for the data, in the order in which it should appear
		foreach ( $columns as $col => $__ )
			$data[ $col ] = isset( $row[ $col ] ) ? $row[ $col ] : '';

		// allow manipulation of this data
		$data = apply_filters( 'qsot-' . $this->slug . '-report-html-row', $data, $row, $columns );

		// if there is a row to display, the do os now
		if ( is_array( $data ) && count( $data ) == $cnt ) {
			echo '<tr>';

			foreach ( $data as $col => $value ) {
				echo '<td>';

				switch ( $col ) {
					// link the order id if present
					case 'order_id':
						echo $row[ $col ] > 0 ? sprintf( '<a href="%s" target="_blank" title="%s">%s</a>', get_edit_post_link( $value ), esc_attr( __( 'Edit order', 'opentickets-community-edition' ) ), $value ) : $value;
					break;

					// default the purchaser name to the cart id
					case 'purchaser':
						echo ! empty( $value )
								? $value
								: sprintf(
									'<span title="%s">%s</span>',
									esc_attr( sprintf( __( 'Cart Session ID: %s', 'opentickets-community-edition' ), $row['_raw']->session_customer_id ) ),
									__( 'Temporary Cart', 'opentickets-community-edition' )
								);
					break;

					// allow a filter on all other columns
					default:
						echo apply_filters( 'qsot-' . $this->slug . '-report-column-' . $col . '-value', '' == strval( $value ) ? '&nbsp;' : force_balance_tags( strval( $value ) ), $data, $row );
					break;
				}

				echo '</td>';
			}

			echo '</tr>';

			return 1;
		}

		return 0;
	}

	// take the resulting group of row datas, and create entries in the csv for them
	protected function _csv_render_rows( $group, $csv_file ) {
		// if the csv file descriptor has gone away, then bail (could happen because of filters)
		if ( ! is_array( $csv_file ) || ! isset( $csv_file['fd'] ) || ! is_resource( $csv_file['fd'] ) )
			return;

		// get a list of the csv fields to add, and their order
		$columns = $this->csv_report_columns();
		$cnt = count( $columns );

		// cycle through the roup of rows, and create the csv entries
		if ( is_array( $group ) ) foreach ( $group as $row ) {
			$data = array();
			// create a list of data to add to the csv, based on the order of the columns we need, and the data for this row
			foreach ( $columns as $col => $__ ) {
				// update some rows with special values
				switch ( $col ) {
					// default the purchaser to a cart id
					case 'purchaser':
						$data[] = isset( $row[ $col ] ) && $row[ $col ]
								? ( '-' == $row[ $col ] ? ' ' . $row[ $col ] : $row[ $col ] ) // fix '-' being translated as a number in OOO
								: sprintf( __( 'Unpaid Cart: %s', 'opentickets-community-edition' ), $row['_raw']->session_customer_id );
					break;

					// pass all other data thorugh
					default:
						$data[] = isset( $row[ $col ] ) && $row[ $col ] ? ( '-' == $row[ $col ] ? ' ' . $row[ $col ] : $row[ $col ] ) : '';
					break;
				}
			}

			// allow manipulation of this data
			$data = apply_filters( 'qsot-' . $this->slug . '-report-csv-row', $data, $row, $columns );

			// add this row to the csv, if there is a row to add
			if ( is_array( $data ) && count( $data ) == $cnt )
				fputcsv( $csv_file['fd'], $data );
		}
	}

	// draw the link to the csv, based off of the passed csv file data
	protected function _csv_link( $file ) {
		// if this is the printerfriendly version, then do not add the links
		if ( $this->is_printer_friendly() )
			return;

		// only print the link if the url is part of the data we got
		if ( ! is_array( $file ) || ! isset( $file['url'] ) || empty( $file['url'] ) )
			return;

		// render the link
		?>
			<div class="report-links">
				<a href="<?php echo esc_attr( $file['url'] ) ?>" title="<?php _e( 'Download this CSV', 'opentickets-community-edition' ) ?>"><?php _e( 'Download this CSV', 'opentickets-community-edition' ) ?></a>
				<?php do_action( 'qsot-report-links', $file, $this ) ?>
				<?php do_action( 'qsot-' . $this->slug . '-report-links', $file, $this ) ?>
			</div>
		<?php
	}

	// generate a filename for the csv for this report
	protected function _csv_filename( $id='', $id_prefix='' ) {
		return 'report-' . $this->slug . ( $id ? '-' . $id_prefix . $id : '' ) . '-' . wp_create_nonce( 'run-report-' . @json_encode( $_REQUEST ) ) . '.csv';
	}

	// add the header row to the csv
	protected function _csv_header_row( $file ) {
		$columns = $this->csv_report_columns();
		fputcsv( $file['fd'], array_values( $columns ) );
	}

	// start the csv file
	protected function _open_csv_file( $id='', $id_prefix='', $skip_headers=false ) {
		// get the csv file path. make it if it does not exist yet
		$csv_path = $this->_csv_path();

		// if we could not find or create the csv file path, then bail now
		if ( is_wp_error( $csv_path ) )
			return $csv_path;

		// determine the file path and url
		$basename = $this->_csv_filename( $id, $id_prefix );
		$file = array(
			'path' => $csv_path['path'] . $basename,
			'url' => $csv_path['url'] . $basename,
			'fd' => null,
			'id' => $id,
		);

		// attempt to create a new csv file for this report. if that is successful, then add the column headers and return all the file info now
		if ( $file['fd'] = fopen( $file['path'], 'w+' ) ) {
			if ( ! $skip_headers )
				$this->_csv_header_row( $file );
			return $file;
		}

		// otherwise, bail with an error
		return new WP_Error( 'file_permissions', sprintf( __( 'Could not open the file [%s] for writing. Please verify the file permissions allow writing.', 'opentickets-community-edition' ), $file['path'] ) );
	}

	// close the csv file
	protected function _close_csv_file( $file ) {
		// only try to close open files
		if ( is_array( $file ) && isset( $file['fd'] ) && is_resource( $file['fd'] ) )
			fclose( $file['fd'] );
	}

	// find or create teh csv report file path, and return the path and url of it
	protected function _csv_path() {
		// get all the informaiton about the uploads dir
		$u = wp_upload_dir();
		$u['baseurl'] = trailingslashit( $u['baseurl'] );
		$u['basedir'] = trailingslashit( $u['basedir'] );

		// see if the report cache path already exists. if so, use it in a response now
		if ( @file_exists( $u['basedir'] . 'report-cache/' ) && is_dir( $u['basedir'] . 'report-cache/' ) && is_writable( $u['basedir'] . 'report-cache/' ) )
			return array(
				'path' => $u['basedir'] . 'report-cache/',
				'url' => $u['baseurl'] . 'report-cache/',
			);
		// if the dir exists, but is not writable, then bail with an appropriate error
		elseif ( @file_exists( $u['basedir'] . 'report-cache/' ) && is_dir( $u['basedir'] . 'report-cache/' ) && ! is_writable( $u['basedir'] . 'report-cache/' ) )
			return new WP_Error(
				'file_permissions',
				sprintf( __( 'The report cache directory [%s] is not writable. Please update the file permissions to allow writing.', 'opentickets-community-edition' ), $u['basedir'] . 'report-cache/' )
			);
		// if the file exists, but is not a directory, then bail with an appropriate error
		elseif ( @file_exists( $u['basedir'] . 'report-cache' ) && ! is_dir( $u['basedir'] . 'report-cache' ) )
			return new WP_Error( 'wrong_file_type', sprintf( __( 'Please remove (or move) the file [%s] and run the report again.', 'opentickets-community-edition' ), $u['basedir'] . 'report-cache' ) );
		// the file does not exist, and we cannot create it
		elseif ( ! @file_exists( $u['basedir'] . 'report-cache/' ) && ! is_writable( $u['basedir'] ) )
			return new WP_Error( 'file_permissions', __( 'Could not create a new directory inside your uploads folder. Update the file permissions to allow writing.', 'opentickets-community-edition' ) );

		// at the point the file does not exist, and we have write permissions to create it. do so now. if that fails, error out
		if ( ! mkdir( $u['basedir'] . 'report-cache/', 0777, true ) )
			return new WP_Error( 'file_permissions', __( 'Could not create a new directory inside your uploads folder. Update the file permissions to allow writing.', 'opentickets-community-edition' ) );

		return array(
			'path' => $u['basedir'] . 'report-cache/',
			'url' => $u['baseurl'] . 'report-cache/',
		);
	}

	// draw the report result header, in html form
	protected function _html_report_header( $use_sorter=true ) {
		$sorter = $use_sorter ? 'use-tablesorter' : '';
		// construct the header of the resulting table
		?>
			<table class="widefat <?php echo $sorter ?>" cellspacing="0">
				<thead><?php $this->_html_report_columns( true ) ?></thead>
				<tbody>
		<?php
	}

	// draw the report result footer, in html form
	protected function _html_report_footer() {
		// construct the footer of the resulting table
		?>
				</tbody>
				<tfoot><?php $this->_html_report_columns() ?></tfoot>
			</table>
		<?php
	}

	// draw the html columns
	protected function _html_report_columns( $header=false ) {
		// get a list of the report columns
		$columns = $this->html_report_columns();

		// render the columns row
		?>
			<tr>
				<?php foreach ( $columns as $column => $args ): ?>
					<?php
						// normalize the column args
						$args = wp_parse_args( $args, array(
							'title' => $column,
							'classes' => '',
							'attr' => '',
						) );
					?>
					<th class="col-<?php echo $column . ( $args['classes'] ? ' ' . esc_attr( $args['classes'] ) : '' ) ?>" <?php echo ( $args['attr'] ? ' ' . $args['attr'] : '' ); ?>>
						<?php echo force_balance_tags( $args['title'] ) ?>
						<?php if ( $header ): ?>
							<span class="dashicons dashicons-sort"></span>
							<span class="dashicons dashicons-arrow-up"></span>
							<span class="dashicons dashicons-arrow-down"></span>
						<?php endif; ?>
					</th>
				<?php endforeach; ?>
			</tr>
		<?php
	}

	// generic printer friendly header
	protected function _printer_friendly_header() {
		define( 'IFRAME_REQUEST', true );
		// direct copy from /wp-admin/admin-header.php
		global $title, $hook_suffix, $current_screen, $wp_locale, $pagenow, $wp_version,
			$update_title, $total_update_count, $parent_file;

		// Catch plugins that include admin-header.php before admin.php completes.
		if ( empty( $current_screen ) )
			set_current_screen();

		get_admin_page_title();
		$title = esc_html( strip_tags( $title ) );

		if ( is_network_admin() )
			$admin_title = sprintf( __( 'Network Admin: %s' ), esc_html( get_current_site()->site_name ) );
		elseif ( is_user_admin() )
			$admin_title = sprintf( __( 'User Dashboard: %s' ), esc_html( get_current_site()->site_name ) );
		else
			$admin_title = get_bloginfo( 'name' );

		if ( $admin_title == $title )
			$admin_title = sprintf( __( '%1$s &#8212; WordPress' ), $title );
		else
			$admin_title = sprintf( __( '%1$s &lsaquo; %2$s &#8212; WordPress' ), $title, $admin_title );

		/**
		 * Filter the title tag content for an admin page.
		 *
		 * @since 3.1.0
		 *
		 * @param string $admin_title The page title, with extra context added.
		 * @param string $title       The original page title.
		 */
		$admin_title = apply_filters( 'admin_title', $admin_title, $title );

		wp_user_settings();

		_wp_admin_html_begin();
		?>
		<title><?php echo $admin_title; ?></title>
		<?php

		wp_enqueue_style( 'colors' );
		wp_enqueue_style( 'ie' );
		wp_enqueue_script('utils');
		wp_enqueue_script( 'svg-painter' );

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
		<meta name="viewport" content="width=device-width,initial-scale=1.0">
		<?php

		/**
		 * Enqueue scripts for all admin pages.
		 *
		 * @since 2.8.0
		 *
		 * @param string $hook_suffix The current admin page.
		 */
		do_action( 'admin_enqueue_scripts', $hook_suffix );

		/**
		 * Fires when styles are printed for a specific admin page based on $hook_suffix.
		 *
		 * @since 2.6.0
		 */
		do_action( "admin_print_styles-$hook_suffix" );

		/**
		 * Fires when styles are printed for all admin pages.
		 *
		 * @since 2.6.0
		 */
		do_action( 'admin_print_styles' );

		/**
		 * Fires when scripts are printed for a specific admin page based on $hook_suffix.
		 *
		 * @since 2.1.0
		 */
		do_action( "admin_print_scripts-$hook_suffix" );

		/**
		 * Fires when scripts are printed for all admin pages.
		 *
		 * @since 2.1.0
		 */
		do_action( 'admin_print_scripts' );

		/**
		 * Fires in head section for a specific admin page.
		 *
		 * The dynamic portion of the hook, `$hook_suffix`, refers to the hook suffix
		 * for the admin page.
		 *
		 * @since 2.1.0
		 */
		do_action( "admin_head-$hook_suffix" );

		/**
		 * Fires in head section for all admin pages.
		 *
		 * @since 2.1.0
		 */
		do_action( 'admin_head' );

		if ( get_user_setting('mfold') == 'f' )
			$admin_body_class .= ' folded';

		if ( !get_user_setting('unfold') )
			$admin_body_class .= ' auto-fold';

		if ( is_admin_bar_showing() )
			$admin_body_class .= ' admin-bar';

		if ( is_rtl() )
			$admin_body_class .= ' rtl';

		if ( $current_screen->post_type )
			$admin_body_class .= ' post-type-' . $current_screen->post_type;

		if ( $current_screen->taxonomy )
			$admin_body_class .= ' taxonomy-' . $current_screen->taxonomy;

		$admin_body_class .= ' branch-' . str_replace( array( '.', ',' ), '-', floatval( $wp_version ) );
		$admin_body_class .= ' version-' . str_replace( '.', '-', preg_replace( '/^([.0-9]+).*/', '$1', $wp_version ) );
		$admin_body_class .= ' admin-color-' . sanitize_html_class( get_user_option( 'admin_color' ), 'fresh' );
		$admin_body_class .= ' locale-' . sanitize_html_class( strtolower( str_replace( '_', '-', get_locale() ) ) );

		if ( wp_is_mobile() )
			$admin_body_class .= ' mobile';

		if ( is_multisite() )
			$admin_body_class .= ' multisite';

		if ( is_network_admin() )
			$admin_body_class .= ' network-admin';

		$admin_body_class .= ' no-customize-support no-svg';

		?>
		</head>
		<?php
		/**
		 * Filter the CSS classes for the body tag in the admin.
		 *
		 * This filter differs from the {@see 'post_class'} and {@see 'body_class'} filters
		 * in two important ways:
		 *
		 * 1. `$classes` is a space-separated string of class names instead of an array.
		 * 2. Not all core admin classes are filterable, notably: wp-admin, wp-core-ui,
		 *    and no-js cannot be removed.
		 *
		 * @since 2.3.0
		 *
		 * @param string $classes Space-separated list of CSS classes.
		 */
		$admin_body_classes = apply_filters( 'admin_body_class', '' );
		?>
		<body class="wp-admin wp-core-ui no-js printer-friendly-report <?php echo $admin_body_classes . ' ' . $admin_body_class; ?>">
		<div id="wpwrap">
		<div class="inner-wrap">
		<?php
	}

	// draw the printer friendly footer
	protected function _printer_friendly_footer() {
		?>
			</div>
			</div>
			</body>
			</html>
		<?php
	}

	// draw the complete printer friendly version
	public function printer_friendly() {
		// if the current user does not have permissions to run the report, then bail
		if ( ! current_user_can( 'view_woocommerce_reports' ) )
			return $this->_error( new WP_Error( 'no_permission', __( 'You do not have permission to use this report.', 'opentickets-community-edition' ) ) );

		// draw the results
		$this->_printer_friendly_header();
		$this->_results();
		$this->_printer_friendly_footer();
	}

	// get the order item meta data
	protected function _order_item_meta_from_oiid_list( $oiids ) {
		global $wpdb;
		$rows = array();
		// grab all the meta for the matched order items, if any
		if ( ! empty( $oiids ) ) {
			$meta = $wpdb->get_results( 'select * from ' . $wpdb->prefix . 'woocommerce_order_itemmeta where order_item_id in (' . implode( ',', array_keys( $oiids ) ) . ')' );
			
			// index all the meta by the order_item_id
			foreach ( $meta as $row ) {
				if ( ! isset( $rows[ $row->order_item_id ] ) )
					$rows[ $row->order_item_id ] = (object)array( 'order_item_id' => $row->order_item_id, 'order_id' => $oiids[ $row->order_item_id ], $row->meta_key => $row->meta_value );
				else
					$rows[ $row->order_item_id ]->{ $row->meta_key } = $row->meta_value;
			}
		}

		return $rows;
	}

	// format a number to a specific number of decimals
	public function format_number( $number, $decimals=2 ) {
		$decimals = max( 0, $decimals );
		// create the sprintf format based on the decimals and the currency settings
		$frmt = $decimals ? '%01' . wc_get_price_decimal_separator() . $decimals . 'f' : '%d';

		return sprintf( $frmt, $number );
	}

	// get a very specific piece of order meta from the list of order meta, based on the list, a specific grouping name, and the order id
	protected function _order_meta( $all_meta, $key, $row, $default='-' ) {
		// find the order_id from the row
		$order_id = $row->order_id;

		// get the meta for just this one order
		$meta = isset( $all_meta[ $order_id ] ) ? $all_meta[ $order_id ] : false;

		// either piece together specific groupings of meta, or return the exact meta value
		switch ( $key ) {
			default: return isset( $meta[ $key ] ) && '' !== $meta[ $key ] ? $meta[ $key ] : __( '(none)', 'opentickets-community-edition' ); break;

			// a display name for the purchaser
			case 'name':
				$names = array();
				// attempt to use the billing name
				if ( isset( $meta['_billing_first_name'] ) )
					$names[] = $meta['_billing_first_name'];
				if ( isset( $meta['_billing_last_name'] ) )
					$names[] = $meta['_billing_last_name'];

				// fall back on the cart identifier
				$names = trim( implode( ' ', $names ) );
				return ! empty( $names ) ? $names : __( '(no-name/guest)', 'opentickets-community-edition' );
			break;

			// the address for the purchaser
			case 'address':
				$addresses = array();
				if ( isset( $meta['_billing_address_1'] ) )
					$addresses[] = $meta['_billing_address_1'];
				if ( isset( $meta['_billing_address_2'] ) )
					$addresses[] = $meta['_billing_address_2'];

				$addresses = trim( implode( ' ', $addresses ) );
				return ! empty( $addresses ) ? $addresses : __( '(none)', 'opentickets-community-edition' );
			break;
		}
	}

	// fetch all order meta, indexed by order_id
	protected function _get_order_meta( $order_ids ) {
		// if there are no order_ids, then bail now
		if ( empty( $order_ids ) )
			return array();

		global $wpdb;
		// get all the post meta for all orders
		$all_meta = $wpdb->get_results( 'select * from ' . $wpdb->postmeta . ' where post_id in (' . implode( ',', $order_ids ) . ') order by meta_id desc' );

		$final = array();
		// organize all results by order_id => meta_key => meta_value
		foreach ( $all_meta as $row ) {
			// make sure we have a row for this order_id already
			$final[ $row->post_id ] = isset( $final[ $row->post_id ] ) ? $final[ $row->post_id ] : array();

			// update this meta key with it's value
			$final[ $row->post_id ][ $row->meta_key ] = $row->meta_value;
		}

		return $final;
	}

	// each report should control it's own form
	abstract public function form();

	// individual reports should define their own set of columns to display in html
	abstract public function html_report_columns();

	// individual reports should define their own set of columns to add to the csv
	abstract public function csv_report_columns();

	// the report should define a function to get a partial list of rows to process for this report. for instance, we don't want to have one group of 1,000,000 rows, run all at once, because
	// the memory implications on that are huge. instead we would need to run it in discreet groups of 1,000 or 10,000 rows at a time, depending on the processing involved
	abstract public function more_rows();

	// the report should define a function to process a group of results, which it contructed in the more_rows() method
	abstract public function aggregate_row_data( array $group );
}

/*
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
			$admin_title = __('Network Admin','opentickets-community-edition');
		elseif ( is_user_admin() )
			$admin_title = __('Global Dashboard','opentickets-community-edition');
		else
			$admin_title = get_bloginfo( 'name' );

		if ( $admin_title == $title )
			$admin_title = sprintf( __('%1$s &#8212; WordPress','opentickets-community-edition'), $title );
		else
			$admin_title = sprintf( __('%1$s &lsaquo; %2$s &#8212; WordPress','opentickets-community-edition'), $title, $admin_title );

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
		$admin_body_class .= ' version-' . str_replace( '.', '-', preg_replace( '#^([.0-9]+).*#', '$1', $wp_version ) );
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

			$first = current(array_values($data));
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
			echo '<div class="error">';
			printf(__("Could not create the report cache directory. Make sure that the permissions for '%s' allow the webserver to create a directory, and try again.",'opentickets-community-edition'), $path);
			echo '</div>';
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

		if ($max === false) $max = QSOT::memory_limit(true);

		$usage = memory_get_usage();
		if ($usage > $max * $dec) {
			wp_cache_flush();
			$wpdb->queries = array();
		}
	}

	// instance version of report info, used in inheritance
	protected $rep_slug = '';
	protected $rep_name = '';
	protected $rep_desc = '';

	// actually register the report, using the report api format
	public function add_report($reports) {
		$reports[$this->rep_slug] = isset($reports[$this->rep_slug]) ? $reports[$this->rep_slug] : array('title' => $this->rep_name, 'charts' => array());
		$reports[$this->rep_slug]['charts'][] = array(
			'title' => $this->rep_name,
			'description' => $this->rep_desc,
			'function' => array(&$this, 'report'),
		);
		return $reports;
	}

	// display a generic report error
	protected function _report_error($msg='') {
		$msg = $msg ? $msg : __('An un expected error occurred during report generation.','opentickets-community-edition');

		?>
			<div class="report-error">
				<p><?php echo $msg ?></p>
			</div>
		<?php
	}

	// actually display the results of the ran report
	protected function _draw_results($template, $tallies, $csvs, $events, $errors) {
		if (!empty($template)) {
			// if there is a template then send all the needed information to the template for rendering
			$columns = $this->get_display_columns();
			include $template;
		} else {
			// if there is not template (unlikely) then display something for all our hard work
			echo '<p>'.__('Could not find the report result template. Below is the list of raw CSV results.','opentickets-community-edition').'</p><ul>';
			foreach ($csvs as $event_id => $csv) {
				echo sprintf(
					'<li><a href="%s" title="%s">%s</a></li>',
					esc_attr($csv['url']),
					esc_attr(__('View report for','opentickets-community-edition').' '.$events[$event_id.'']['title']),
					__('Report for','opentickets-community-edition').' '.$events[$event_id.'']['title']
				);
			}
			echo '</ul>';
		}
	}

	// list columns for display in the breakdown summary tables
	abstract public function get_display_columns();

	// list columns to add to the csv output file
	abstract public function get_csv_columns();

	// aggregate a list of product names based on sold order items
	protected function _get_product_names($ois) {
		global $wpdb;

		$product_ids = wp_list_pluck($ois, '_product_id');
		$q = 'select id, post_title from '.$wpdb->posts.' where id in ('.implode(',', $product_ids).')';

		return $wpdb->get_results($q, OBJECT_K);
	}

	// create a sane payment method value
	protected function _payment_method($order, $fallback='') {
		return isset($order['_payment_method']) && !empty($order['_payment_method'])
			? $order['_payment_method']
			: ( isset($order['_order_total']) && $order['_order_total'] > 0 ? __('(unknown)','opentickets-community-edition') : __('-free-','opentickets-community-edition') );
	}

	// if the billing information does not exist for an order, but their is an owning user for the order, attempt to pull billing information from user information
	protected function _maybe_fill_from_user($order) {
		$u = get_user_by('id', $order['_customer_user']);
		$meta = get_user_meta($order['_customer_user']);
		$user = array();
		foreach ($order as $k => $v) {
			if (substr($k, 0, 8) != '_billing') continue;
			$k2 = substr($k, 1);
			if (isset($meta[$k2])) $user[$k] = current($meta[$k2]);
		}

		if (!isset($user['_billing_email']) || empty($user['_billing_email'])) $user['_billing_email'] = $u->user_email;
		if (!isset($user['_billing_first_name']) || empty($user['_billing_first_name']))
			$user['_billing_first_name'] = !empty($u->display_name) ? $u->display_name : $u->user_login;

		return array_merge($user, $order);
	}

	// create a sane purchaser entry
	protected function _purchaser($order, $fallback='') {
		$names = array_filter(array(
			isset($order['_billing_first_name']) ? $order['_billing_first_name'] : '',
			isset($order['_billing_last_name']) ? $order['_billing_last_name'] : '',
		));
		$names = $names ? $names : (array)$fallback;
		return implode(' ', $names);
	}

	// pull the email value from order info
	protected function _email($order, $fallback='') {
		return isset($order['_billing_email']) ? $order['_billing_email'] : '';
	}

	// pull the phone number from the order information
	protected function _phone($order, $fallback='') {
		return isset($order['_billing_phone']) ? preg_replace('#[^\d\.x]#i', '', $order['_billing_phone']) : $fallback;
	}

	protected function _check_memory($flush_percent_range=80) { self::_memory_check($flush_percent_range); }

  protected function _address($order) {
		$order = array_merge(array(
			'_billing_address_1' => '',
			'_billing_address_2' => '',
			'_billing_city' => '',
			'_billing_state' => '',
			'_billing_postcode' => '',
			'_billing_country' => '',
		), $order);
    $addr = $order['_billing_address_1'];
    if (!empty($order['_billing_address_2'])) $addr .= "\n".$order['_billing_address_2'];
    $addr .= "\n".$order['_billing_city'].', '.$order['_billing_state'].' '.$order['_billing_postcode'].', '.$order['_billing_country'];
    return $addr;
  }
}
*/

if (defined('ABSPATH') && function_exists('add_action')) {
	qsot_reporting::pre_init();
}
