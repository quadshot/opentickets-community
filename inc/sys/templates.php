<?php (__FILE__ == $_SERVER['SCRIPT_FILENAME']) ? die(header('Location: /')) : null;

if (!class_exists('qsot_templates')):

class qsot_templates {
	protected static $o = null; // holder for all options of the events plugin

	public static function pre_init() {
		// load the settings. theya re required for everything past this point
		$settings_class_name = apply_filters('qsot-settings-class-name', '');
		if (empty($settings_class_name)) return;
		self::$o =& $settings_class_name::instance();

		// qsot template locator. checks theme first, then our templates dir
		add_filter('qsot-locate-template', array(__CLASS__, 'locate_template'), 10, 4);

		// similar to above, only specifically for templates that we may have overriden from woo.... like admin templates
		add_filter('qsot-woo-template', array(__CLASS__, 'locate_woo_template'), 10, 2);
	}

	public static function locate_template($current='', $files=array(), $load=false, $require_once=false) {
		if (is_array($files) && count($files)) {
			$templ = locate_template($files, $load, $require_once);
			if (empty($templ)) {
				$dirs = apply_filters('qsot-template-dirs', array(
					get_stylesheet_directory().'/templates/',
					get_template_directory().'/templates/',
					self::$o->core_dir.'templates/',
				));
				foreach ($dirs as $dir) {
					$dir = trailingslashit($dir);
					foreach ($files as $file) {
						if (file_exists($dir.$file) && is_readable($dir.$file)) {
							$templ = $dir.$file;
							break 2;
						}
					}
				}
				if (!empty($templ) && $load) {
					if ($require_once) require_once $templ;
					else include $templ;
				}
			}
			if (!empty($templ)) $current = $templ;
		}

		return $current;
	}

	public static function locate_woo_template($name, $type=false) {
		global $woocommerce;

		$found = locate_template(array($name), false, false);
		if (!$found) {
			$woodir = trailingslashit($woocommerce->plugin_path);
			switch ($type) {
				case 'admin': $qsot_path = 'templates/admin/'; $woo_path = 'includes/admin/'; break;
				default: $qsot_path = 'templates/'; $woo_path = 'templates/';
			}

			$dirs = apply_filters('qsot-template-dirs', array(
				get_stylesheet_directory().'/'.$qsot_path,
				get_template_directory().'/'.$qsot_path,
				self::$o->core_dir.$qsot_path,
				$woodir.$woo_path,
			), $qsot_path, $woo_path);

			foreach ($dirs as $dir) {
				if (file_exists(($file = trailingslashit($dir).$name))) {
					$found = $file;
					break;
				}
			}
		}

		return $found;
	}
}

if (defined('ABSPATH') && function_exists('add_action')) {
	qsot_templates::pre_init();
}

endif;
