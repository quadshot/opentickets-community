<?php if ( __FILE__ == $_SERVER['SCRIPT_FILENAME'] ) die( header( 'Location: /' ) );

// creates the special roles used by our plugin
class qsot_capabilities {
	// setup the actions and filters used by this class
	public static function pre_init() {
		// when the plugins are loaded, we need to run some logic
		add_action( 'plugins_loaded', array( __CLASS__, 'plugins_loaded' ), 5 );

		// if we are in the admin and there is a request to debug the roles, add that debug to the footer
		if ( is_admin() && ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX ) && isset( $_GET['role-debug'] ) ) {
			add_action( 'admin_footer', array( __CLASS__, 'debug_roles' ), 10000 );
		}

		// adjust the menu items for the box office roles, by adding the order to the toplevel admin menu instead of under woocommerce menu
		//add_action('admin_menu', array(__CLASS__, 'cleanup_menus_for_box_office_roles'), 1000);
	}

	// when the plugin loads, run the role checker
	public static function plugins_loaded() {
		self::_check_for_role_updates();
	}

	// run a check to see if the roles need to be updated. if they do, run the update now
	protected static function _check_for_role_updates() {
		// current ot version
		$version = QSOT::version();

		// last version we updated roles on
		$last_version = get_option( '_qsot-last-roles-update', '' );

		// if the roles have not been updated for this version, do it now
		if ( ( current_user_can( 'manage_options' ) && isset( $_GET['perms_force'] ) && '1' == $_GET['perms_force'] ) || version_compare( $last_version, $version ) < 0 ) {
			self::_add_core_qsot_roles();
			update_option( '_qsot-last-roles-update', $version );
		}
	}

	// dump all the information about our special roles
	public static function debug_roles() {
		echo '<pre style="padding-left:170px;">';
		print_r(get_role('box-office'));
		print_r(get_role('box-office-manager'));
		print_r(get_role('content-manager'));
		print_r(get_role('administrator'));
		print_r(get_role('editor'));
		echo '</pre>';
	}

/*
	public static function cleanup_menus_for_box_office_roles() {
		global $menu, $submenu;

		foreach ($menu as $ind => $top_level_item) {
			if (preg_match('#^orders#i', $top_level_item[0])) $menu[$ind][0] = __('Orders', 'qsot');
		}
	}
*/

	// add our core roles rules
	protected static function _add_core_qsot_roles() {
		$editor = get_role('editor');
		$roles = array();

		$bocaps = $editor->capabilities;

		// basic additional caps for BOX OFFICE users
		$bo_add_caps = array(
			'create_users',
			'box_office',
			'edit_users',
			'list_users',
			'manage_woocommerce_orders',
			'manage_woocommerce_coupons',
			'manage_woocommerce_products',
			'view_woocommerce_reports',
		);

		// post type related caps
		$pts = array('shop_order');
		$ptcaps = array('edit_%pt%', 'read_%pt%', 'delete_%pt%', 'edit_%pt%s', 'edit_others_%pt%s', 'publish_%pt%s', 'read_private_%pt%s', 'delete_%pt%s', 'delete_private_%pt%s',
			'delete_published_%pt%s', 'delete_others_%pt%s', 'edit_private_%pt%s', 'edit_published_%pt%s');
		foreach ($pts as $pt) 
			foreach ($ptcaps as $ptcap)
				$bo_add_caps[] = str_replace('%pt%', $pt, $ptcap);

		// post type related caps with limited access
		$pts = array('product', 'shop_coupon');
		$ptcaps = array('read_%pt%', 'read_private_%pt%s');
		foreach ($pts as $pt) 
			foreach ($ptcaps as $ptcap)
				$bo_add_caps[] = str_replace('%pt%', $pt, $ptcap);

		// taxonomy related caps
		$taxes = array('product_terms', 'shop_order_terms', 'shop_coupon_terms');
		//$taxcaps = array('manage_%tax%', 'edit_%tax%', 'delete_%tax%', 'assign_%tax%');
		$taxcaps = array('assign_%tax%');
		foreach ($taxes as $tax)
			foreach ($taxcaps as $taxcap)
				$bo_add_caps[] = str_replace('%tax%', $tax, $taxcap);

		// eliminate dup roles
		$bo_add_caps = array_unique($bo_add_caps);

		// assign them all active
		foreach ($bo_add_caps as $cap) $bocaps[$cap] = 1;

		// hard remove some
		$remove = array('delete_posts', 'delete_others_posts', 'edit_others_posts', 'publish_posts', 'delete_pages', 'delete_others_pages');
		foreach ($remove as $role) unset($bocaps[$role]); // $bocaps[$role] = 0;

		// add box-office role to list
		$roles['box-office'] = array(
			'name' => __('Box Office', 'qsot'),
			'caps' => $bocaps,
		);

		
		// additional roles for BOX OFFICE MANAGER users
		$bomcaps = $bocaps;

		// basic additional caps
		$bom_add_caps = array('delete_posts', 'delete_others_posts', 'edit_others_posts', 'publish_posts', 'delete_pages', 'delete_others_pages', 'box_office_manager');

		// post type related caps
		$pts = array('product', 'shop_order', 'shop_coupon');
		$ptcaps = array('edit_%pt%', 'read_%pt%', 'delete_%pt%', 'edit_%pt%s', 'edit_others_%pt%s', 'publish_%pt%s', 'read_private_%pt%s', 'delete_%pt%s', 'delete_private_%pt%s',
			'delete_published_%pt%s', 'delete_others_%pt%s', 'edit_private_%pt%s', 'edit_published_%pt%s');
		foreach ($pts as $pt) 
			foreach ($ptcaps as $ptcap)
				$bom_add_caps[] = str_replace('%pt%', $pt, $ptcap);

		// taxonomy related caps
		$taxes = array('product_terms', 'shop_order_terms', 'shop_coupon_terms');
		$taxcaps = array('manage_%tax%', 'edit_%tax%', 'delete_%tax%', 'assign_%tax%');
		foreach ($taxes as $tax)
			foreach ($taxcaps as $taxcap)
				$bom_add_caps[] = str_replace('%tax%', $tax, $taxcap);

		// unique the list of roles and add them to the actual list
		$bom_add_caps = array_unique($bom_add_caps);
		foreach ($bom_add_caps as $cap) $bomcaps[$cap] = 1;

		// hard remove some
		$remove = array('box_office');
		foreach ($remove as $role) unset($bocaps[$role]); // $bocaps[$role] = 0;

		// add box office manager role
		$roles['box-office-manager'] = array(
			'name' => __('Box Office Manager', 'qsot'),
			'caps' => $bomcaps,
		);

		
		// additional roles for CONTENT MANAGER users
		$cocaps = $bomcaps;

		// remove static roles
		$remove = array('create_users', 'edit_users', 'list_users', 'manage_woocommerce_orders', 'manage_woocommerce_coupons');
		foreach ($remove as $role) unset($cocaps[$role]);

		// remove post type related roles
		$pts = array('shop_order', 'shop_coupon');
		$ptcaps = array('edit_%pt%', 'read_%pt%', 'delete_%pt%', 'edit_%pt%s', 'edit_others_%pt%s', 'publish_%pt%s', 'read_private_%pt%s', 'delete_%pt%s', 'delete_private_%pt%s',
			'delete_published_%pt%s', 'delete_others_%pt%s', 'edit_private_%pt%s', 'edit_published_%pt%s');
		foreach ($pts as $pt) 
			foreach ($ptcaps as $ptcap)
				unset($cocaps[str_replace('%pt%', $pt, $ptcap)]);

		// basic additional caps
		$com_add_caps = array('edit_theme_options');

		// unique the list of roles and add them to the actual list
		$com_add_caps = array_unique($com_add_caps);
		foreach ($com_add_caps as $cap) $cocaps[$cap] = 1;

		// add content manager role
		$roles['content-manager'] = array(
			'name' => __('Content Manager', 'qsot'),
			'caps' => $cocaps,
		);

		$roles = apply_filters('qsot-roles-to-add', $roles);

		foreach ($roles as $slug => $role) {
			remove_role($slug);
			add_role($slug, $role['name'], $role['caps']);
		}
	}
}

if (defined('ABSPATH') && function_exists('add_action')) {
	qsot_capabilities::pre_init();
}
