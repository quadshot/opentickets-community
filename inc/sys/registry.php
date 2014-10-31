<?php (__FILE__ == $_SERVER['SCRIPT_FILENAME']) ? die(header('Location: /')) : null;

if (!class_exists('QSOT_addon_registry')):

class QSOT_addon_registry {
	private static $instance = null;

	public static function pre_init() {
		self::instance();
	}

	public static function instance() {
		$me = __CLASS__;
		if (self::$instance !== null && self::$instance instanceof $me) return self::$instance;
		self::$instance = new $me();
		return self::$instance;
	}

	public function __construct() {
		if (self::$instance != null && self::$instance instanceof $me)
			throw new Exception(sprintf(__('Only one instance of %s can be created.','opentickets-community-edition'), __CLASS__), 501);
	}

	public function is_activated($addon) { static $state = null; return $state == null ? ( $state = ! is_admin() ) : $state; }
	public function force_check() { return false; }
}

if (defined('ABSPATH') && function_exists('add_action')) QSOT_addon_registry::pre_init();

endif;
