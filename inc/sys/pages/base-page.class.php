<?php if ( __FILE__ == $_SERVER['SCRIPT_FILENAME'] ) die( header( 'Location: /') ); // block direct access

// loads the admin pages that exist on the admin menu for opentickets
class QSOT_base_page_launcher {
	// setup the primary actions and filters for this class
	public static function pre_init() {
		// defer loading of the pages until after all plugins are loaded
		add_action( 'plugins_loaded', array( __CLASS__, 'plugins_loaded' ), 10000 );
	}

	// defered loading / actions / filters
	public static function plugins_loaded() {
		// if are in the admin or running a verified backport request
		if ( self::is_backport_request() || is_admin() ) {
			// load all the 'pages'
			do_action( 'qsot-load-includes', 'sys/pages', '#^.+\.page\.php$#i' );
		}
	}

	// simple check to see if this is a backport request
	public static function is_backport_request() {
		if ( ! isset( $_GET['qsot-in-background'], $_GET['qsotn'] ) ) {
			return false;
		}

		if ( get_option( 'qsot-backport-request', '' ) != $_GET['qsotn'] . '::' . $_GET['qsot-in-background'] ) {
			return false;
		}

		return true;
	}
}

// base page class. contains all the generic functions that are shared between pages
abstract class QSOT_base_page {
	// the ?page=<slug> of the page
	protected $slug = 'qsot-base-page';

	// the text that appears on the menu, which links to this page
	protected $menu_title = '';

	// text that appears in the titlebar when this page is active
	protected $page_title = '';

	// the permission that users must have in order to see this item/page
	protected $capability = 'manage_options';

	// the icon that shows in this page's upper right corner
	protected $icon = '';

	// wheterh or not this page is tabbed instead of a generic page
	protected $tabbed = false;

	// container to hold settings for the tabs, if they exist
	protected $tabs = array();

	// holds the current page tab name
	protected $current_tab = array();

	// determins the order in which this menu item appears under the main nav items
	protected $order = 0;

	// setup the basic settings for the page
	public function __construct() {
		// if this is a backport request, then attempt to handle it
		if ( $this->is_backport_request() || ( isset( $_GET['debug'] ) && $_GET['debug'] == 1 ) ) {
			ignore_user_abort( 1 ); // do not die when the request is terminated from the user end
			ini_set( 'max_execution_time', 3600 );
			$this->_backport();
		}

		// setup the fallback titles, in case none are specified in the child class
		$this->menu_title = empty( $this->menu_title ) ? __( 'Base Page', 'opentickets-community-edition' ) : $this->menu_title;
		$this->page_title = empty( $this->page_title ) ? __( 'Base Page', 'opentickets-community-edition' ) : $this->page_title;

		// add the hook to setup the menu item based on the settings
		add_action( 'admin_menu', array( &$this, 'register_page' ), 1000 + $this->order );
	}

	// handle taredown of this object
	public function __destruct() {
		// remove all hooks that were previously registered for this page
		remove_action( 'admin_menu', array( &$this, 'register_page' ), 1000 + $this->order );
	}

	// setup the menu item
	public function register_page() {
		// if this is a tabbed page, add the current tab name to the page titlebar
		if ( $this->tabbed ) {
			$current_tab = $this->_current_tab();
			if ( isset( $this->tabs[ $current_tab ] ) ) {
				$this->page_title .= ' - ' . $this->tabs[ $current_tab ]['label'];
			}
		}

		// register the menu item as normal, and store the $hook that is returned, which will be used in a second
		$hook = add_submenu_page(
			apply_filters( 'qsot-get-menu-slug', '', 'main' ),
			$this->page_title,
			$this->menu_title,
			$this->capability,
			$this->slug,
			array( &$this, 'draw_page' )
		);

		// use the hook we fetched above to setup functions that run on page load, which will save the page (if needed) and which will allow enqueuing any needed assets
		add_action( 'admin_print_scripts-' . $hook, array( &$this, 'enqueue_assets' ), 10 );
		add_action( 'load-' . $hook, array( &$this, 'page_save' ), 9 );
		add_action( 'load-' . $hook, array( &$this, 'page_head' ), 20 );
	}

	// basic page shell
	public function draw_page() {
		if ( $this->tabbed ) $this->_draw_tabbed_page();
		else $this->_draw_page();
	}

	// draw the basic tabbed page shell
	protected function _draw_tabbed_page() {
		$current_tab = $this->_current_tab();
		$func = isset( $this->tabs[ $current_tab ] ) ? $this->tabs[ $current_tab ]['function'] : array( &$this, 'page' );
		?>
			<div class="wrap qsot-page page-<?php echo esc_attr( $current_tab ) ?>">
				<?php $this->_draw_tab_nav() ?>
				<div class="inside">
					<?php call_user_func( $func ) ?>
				</div>
			</div>
		<?php
	}

	// draw the basic 'un-tabbed' page shell
	protected function _draw_page() {
		?>
			<div class="wrap">
				<h2><?php echo $this->page_title ?></h2>
				<div class="inside">
					<form action="<?php echo remove_query_arg( 'updated' ) ?>" method="post">
						<?php $this->page() ?>
						<?php $this->_nonce() ?>
						<input type="submit" value="Save Options" class="button-primary" />
					</form>
				</div>
			</div>
		<?php
	}

	// tab registration function, which adds a new tab to the current list of tabs
	protected function _register_tab( $name, $settings='' ) {
		static $index = 1;

		// normalize the name
		$name = sanitize_title_with_dashes( $name );
		if ( empty( $name ) ) return;

		// normalize the settings
		$settings = wp_parse_args( $settings, array(
			'slug' => $name,
			'function' => array( &$this, 'page' ),
			'label' => 'New Tab ' . ( $index++ ),
		) );

		// add it to the list
		$this->tabs[ $name ] = $settings;
	}

	// remove tab from list of current tabs
	protected function _unregister_tab( $name ) {
		// normalize the name
		$name = sanitize_title_with_dashes( $name );
		if ( empty( $name ) ) return;

		// remove the tab from the list
		if ( isset( $this->tabs[ $name ] ) ) {
			unset( $this->tabs[ $name ] );
		}
	}

	// fetch the current tab, based on the url
	protected function _current_tab() {
		// if we already calculated this, then return the cached value
		if ( ! empty( $this->current_tab ) ) {
			return $this->current_tab;
		}

		// fetch the current tab name from the url, and then sanitize it so that it is a valid tab
		$this->current_tab = isset( $_GET['tab'] ) ? $_GET['tab'] : '';
		if ( empty( $this->current_tab ) || ! isset( $this->tabs[ $this->current_tab ] ) ) {
			$this->current_tab = current( array_keys( $this->tabs ) );
		}

		return $this->current_tab;
	}

	protected function _draw_tab_nav() {
		$current_tab = $this->_current_tab();
		?>
			<h2 class="nav-tab-wrapper">
				<?php foreach ( $this->tabs as $slug => $settings ): ?>
					<a href="<?php echo esc_attr( add_query_arg( array( 'tab' => $slug ) ) ) ?>" class="nav-tab <?php echo $slug == $this->current_tab ? 'nav-tab-active' : '' ?>"><?php echo $settings['label'] ?></a>
				<?php endforeach; ?>
			</h2>
		<?php
	}

	// base enqueue assets logic
	public function enqueue_assets() {}

	// base function that draws the contents of the page
	public function page() {}

	// stub function for registering/enqueuing assets
	public function page_head() {}

	// generic save function. looks for specific fields and then saves the settings described by those fields
	public function page_save() {
		// if the page was not properly submitted, then just skip this step
		if ( ! $this->_verify_nonce() ) return false;

		// if there is nothing to save for this page
		if ( ! isset( $_POST[ $this->slug ] ) || ! is_array( $_POST[ $this->slug ] ) ) return false;

		$saved = false;

		// cycle through the list of options that need saving, and save them
		foreach ( $_POST[ $this->slug ] as $option_name => $new_value ) {
			update_option( $option_name, $new_value );
			$saved = true;
		}

		// if there were options saved, then refresh the page with a message saying so, which also prevents the 'refresh resubmit' situtation
		if ( $saved ) {
			wp_safe_redirect( add_query_arg( array( 'updated' => 1 ) ) );
			exit;
		}
	}

	// draw a generic nonce on the page
	protected function _nonce() {
		?><input type="hidden" name="<?php echo $this->slug . '-save-now' ?>" value="<?php echo wp_create_nonce( 'save-settings-' . $this->slug ) ?>" /><?php
	}

	// verify the generic nonce that was drawn on the page. used on submit to make sure the page was actually submitted
	protected function _verify_nonce() {
		if ( ! isset( $_POST[ $this->slug . '-save-now' ] ) ) return false;
		return wp_verify_nonce( $_POST[ $this->slug . '-save-now' ], 'save-settings-' . $this->slug );
	}

	// construct a name for a field, which may appear on the page.
	// the resulting name is what our generic save function looks for when the page is submitted
	protected function _name( $field ) {
		$extra = '';
		// if the field name contains a [] block, then append that to the end of the resulting name, so that an array is returned
		if ( false !== ( $pos = strpos( $field, '[' ) ) ) {
			$extra = substr( $field, $pos );
			$field = substr( $field, 0, $pos );
		}

		// glue the pieces of the naem together for the final name
		return $this->slug . '[' . $field . ']' . $extra;
	}

	// create a sanitized id that describe a specific field
	protected function _id( $field ) {
		return sanitize_title_with_dashes( $this->slug . '-' . $field );
	}

	protected function _backport() {}

	// simple check to see if this is a backport request
	public function is_backport_request() {
		return QSOT_base_page_launcher::is_backport_request();
	}
}

if ( defined( 'ABSPATH' ) && function_exists( 'add_action' ) ) QSOT_base_page_launcher::pre_init();
