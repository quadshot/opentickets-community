<?php (__FILE__ == $_SERVER['SCRIPT_FILENAME']) ? die(header('Location: /')) : null;

class qsot_admin_settings {
	private $start_date;
	private $end_date;

	/**
	 * Handles output of the reports page in admin.
	 */
	public function output() {
		$reports        = $this->get_reports();
		$first_tab      = array_keys( $reports );
		$current_tab    = ! empty( $_GET['tab'] ) && is_scalar( $_GET['tab'] ) && isset( $reports[ $_GET['tab'] ] ) ? sanitize_title( $_GET['tab'] ) : $first_tab[0];
		$current_report = isset( $_GET['report'] ) ? sanitize_title( $_GET['report'] ) : current( array_keys( $reports[ $current_tab ]['reports'] ) );

		include_once( $GLOBALS['woocommerce']->plugin_path() . '/includes/admin/reports/class-wc-admin-report.php' );
		include_once( 'views/html-admin-page-reports.php' );
	}

	/**
	 * Returns the definitions for the reports to show in admin.
	 *
	 * @return array
	 */
	public function get_reports() {
		$reports = array();

		//$reports = apply_filters( 'qsot-reports', $reports);
		$reports = apply_filters( 'qsot_admin_reports', $reports );

		// Backwards compat
		$reports = apply_filters( 'qsot_reports_charts', $reports );

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
