<?php (__FILE__ == $_SERVER['SCRIPT_FILENAME']) ? die(header('Location: /')) : null;

require_once $GLOBALS['woocommerce']->plugin_path() . '/includes/admin/class-wc-admin-settings.php';

class qsot_admin_settings extends WC_Admin_Settings {

	private static $settings = array();
	private static $errors   = array();
	private static $messages = array();

	// setup the pages, by loading their classes and assets and such
	public static function get_settings_pages() {
		// load the settings pages, if they are not already loaded
		if ( empty( self::$settings ) ) {
			// load the admin page assets from our plugin
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'load_admin_page_assets' ), 1000 );

			// load the woocommerce wysiwyg field js
			add_action( 'woocommerce_admin_field_wysiwyg', array( __CLASS__, 'field_wysiwyg' ) );

			$settings = array();

			// load the woocoomerce settings api
			include_once( $GLOBALS['woocommerce']->plugin_path() . '/includes/admin/settings/class-wc-settings-page.php' );

			// load the various settings pages
			$settings[] = include( 'settings/general.php' );
			$settings[] = include( 'settings/frontend.php' );

			// allow adding of other pages if needed
			self::$settings = apply_filters( 'qsot_get_settings_pages', array_filter($settings) );
		}

		return self::$settings;
	}

	// load the admin page assets, depending on the page we are viewing
	public static function load_admin_page_assets( $hook ) {
		// if the current page is the settings page, then load our settings js
		$settings = apply_filters( 'qsot-get-menu-page-uri', array(), 'settings' );
		if ( isset( $settings[1] ) && $hook == $settings[1] ) {
			wp_enqueue_media();
			wp_enqueue_script( 'qsot-admin-settings' );
			wp_enqueue_style( 'qsot-admin-settings' );
		}
	}

	public static function save() {
		global $current_section, $current_tab;

		if ( empty( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( $_REQUEST['_wpnonce'], 'qsot-settings' ) )
			die( __( 'Action failed. Please refresh the page and retry.','opentickets-community-edition' ) );

		// Trigger actions
		do_action( 'qsot_settings_save_' . $current_tab );
		do_action( 'qsot_update_options_' . $current_tab );
		do_action( 'qsot_update_options' );

		self::add_message( __( 'Your settings have been saved.','opentickets-community-edition' ) );
		self::check_download_folder_protection();

		do_action( 'qsot_settings_saved' );

		wp_safe_redirect( apply_filters( 'qsot-settings-save-redirect', add_query_arg( array( 'updated' => 1 ) ), $current_tab ) );
		exit;
	}

	public static function field_wysiwyg( $args ) {
		$args = wp_parse_args( $args, array(
			'id' => '',
			'title' => '',
			'default' => '',
			'class' => '',
		) );
		if ( empty( $args['id'] ) ) return;

		$args['title'] = ( empty( $args['title'] ) ) ? ucwords( implode( ' ', explode( '-', str_replace( '_', '-', $args['id'] ) ) ) ) : $args['title'];

		?><tr valign="top" class="woocommerce_wysiwyg">
			<th scope="row" class="titledesc">
				<?php echo force_balance_tags( $args['title'] ) ?>
			</th>
			<td class="forminp"><?php
				wp_editor(
					get_option( $args['id'], $args['default'] ),
					$args['id'],
					array(
						'quicktags' => false,
						'teeny' => true,
						'textarea_name' => $args['id'],
						'textarea_rows' => 2,
						'media_buttons' => false,
						'wpautop' => false,
						'editor_class' => $args['class'],
						'tinymce' => array( 'wp_autoresize_on' => '', 'paste_as_text' => true ),
					)   
				);
			?></td>
		</tr><?php
	}

	/**
	 * Add a message
	 * @param string $text
	 */
	public static function add_message( $text ) {
		self::$messages[] = $text;
	}

	/**
	 * Add an error
	 * @param string $text
	 */
	public static function add_error( $text ) {
		self::$errors[] = $text;
	}

	/**
	 * Output messages + errors
	 */
	public static function show_messages() {
		if ( sizeof( self::$errors ) > 0 ) {
			foreach ( self::$errors as $error )
				echo '<div id="message" class="error fade"><p><strong>' . esc_html( $error ) . '</strong></p></div>';
		} elseif ( sizeof( self::$messages ) > 0 ) {
			foreach ( self::$messages as $message )
				echo '<div id="message" class="updated fade"><p><strong>' . esc_html( $message ) . '</strong></p></div>';
		}
	}

	public static function output() {
		global $current_section, $current_tab;

		do_action( 'qsot_settings_start' );

		wp_enqueue_script( 'qsot_settings', WC()->plugin_url() . '/assets/js/admin/settings.min.js', array( 'jquery', 'jquery-ui-datepicker', 'jquery-ui-sortable', 'iris' ), WC()->version, true );

		wp_localize_script( 'woocommerce_settings', 'woocommerce_settings_params', array(
			'i18n_nav_warning' => __( 'The changes you made will be lost if you navigate away from this page.','opentickets-community-edition' )
		) );

		// Include settings pages
		//self::get_settings_pages();

		// Get current tab/section
		//$current_tab     = empty( $_GET['tab'] ) ? 'general' : sanitize_title( $_GET['tab'] );
		//$current_section = empty( $_REQUEST['section'] ) ? '' : sanitize_title( $_REQUEST['section'] );

		// Save settings if data has been posted
		//if ( ! empty( $_POST ) )
			//self::save();

		// Add any posted messages
		if ( ! empty( $_GET['wc_error'] ) )
			self::add_error( stripslashes( $_GET['wc_error'] ) );

		 if ( ! empty( $_GET['wc_message'] ) )
			self::add_message( stripslashes( $_GET['wc_message'] ) );

		self::show_messages();

		// Get tabs for the settings page
		$tabs = apply_filters( 'qsot_settings_tabs_array', array() );

		include 'views/html-admin-settings.php';
	}
}
