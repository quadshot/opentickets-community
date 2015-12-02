<?php if ( __FILE__ == $_SERVER['SCRIPT_FILENAME'] ) die( header( 'Location: /') );

class qsot_admin_settings {
	private $start_date;
	private $end_date;

	// container for all the reports
	private $reports = array();

	// container for all the request args
	private $first_tab = null;
	private $current_tab = null;
	private $curretn_report = null;

	// during page load, we may want to run the printer frinedly version of the report
	public function on_load() {
		// gather the list of reports, which should be registered by now
		$this->reports = $this->get_reports();

		// create a list of request args
		$this->first_tab = array_keys( $this->reports );
		$this->current_tab = ! empty( $_GET['tab'] ) && is_scalar( $_GET['tab'] ) && isset( $this->reports[ $_GET['tab'] ] ) ? sanitize_title( $_GET['tab'] ) : $this->first_tab[0];
		$this->current_report = isset( $_GET['report'] ) ? sanitize_title( $_GET['report'] ) : current( array_keys( $this->reports[ $this->current_tab ]['reports'] ) );

		// if this is the printer-friendly version, then handle that now
		if ( isset( $_REQUEST['pf'] ) && 1 == $_REQUEST['pf'] ) {
			// if the report exists
			if ( isset( $this->reports[ $this->current_tab ], $this->reports[ $this->current_tab ]['reports'], $this->reports[ $this->current_tab ]['reports'][ $this->current_report ] )) {
				$report = $this->reports[ $this->current_tab ]['reports'][ $this->current_report ];
				// and if the report has a printer friendly function declared
				if ( isset( $report['pf_function'] ) && is_callable( $report['pf_function'] ) ) {
					// call it
					call_user_func( $report['pf_function'] );
					exit;
				}
			}
		}
	}

	/**
	 * Handles output of the reports page in admin.
	 */
	public function output() {
		$reports = $this->reports;
		$first_tab = $this->first_tab;
		$current_tab = $this->current_tab;
		$current_report = $this->current_report;

		include_once( WC()->plugin_path() . '/includes/admin/reports/class-wc-admin-report.php' );
		include_once( 'views/html-admin-page-reports.php' );
	}

	/**
	 * Returns the definitions for the reports to show in admin.
	 *
	 * @return array
	 */
	public function get_reports() {
		$reports = array();

		// Backwards compat
		$reports = apply_filters( 'qsot_reports_charts', $reports );

		//$reports = apply_filters( 'qsot-reports', $reports);
		$reports = apply_filters( 'qsot_admin_reports', $reports );

		foreach ( $reports as $key => $report_group ) {
			if ( isset( $reports[ $key ]['charts'] ) )
				$reports[ $key ]['reports'] = $reports[ $key ]['charts'];

			foreach ( $reports[ $key ]['reports'] as $report_key => $report ) {
				if ( isset( $reports[ $key ]['reports'][ $report_key ]['function'] ) )
					$reports[ $key ]['reports'][ $report_key ]['callback'] = $reports[ $key ]['reports'][ $report_key ]['function'];
			}
		}

		return $reports;
	}

	/**
	 * Get a report from our reports subfolder
	 */
	public function get_report( $name ) {
	}
}
return new qsot_admin_settings();
