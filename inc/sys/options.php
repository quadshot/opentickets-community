<?php (__FILE__ == $_SERVER['SCRIPT_FILENAME']) ? die(header('Location: /')) : null;

class qsot_options {
	protected static $instance = null;
	protected static $option_key = '__qsot-event-options';
	protected static $tab_slug = 'general';

	protected $defaults = array();
	protected $options = array();
	protected $values = array();

	public static function pre_init() {
		self::$instance =& self::instance();
		//add_action('qsot_settings_start', array(__CLASS__, 'woo_settings_tab'), 10);
		add_filter('qsot_settings_tabs_array', array(__CLASS__, 'add_woo_tab'), 10, 1);
		//add_action('qsot_settings_tabs_'.self::$tab_slug, array(__CLASS__, 'draw_woo_tab_panel'), 10);
		add_filter('qsot_general_settings', array(__CLASS__, 'get_all_general_settings'), 10, 1);

		add_filter('qsot-get-option-value', array(__CLASS__, 'get_option_value'), 10, 2);

		// declare the initial value of the settings class name, which will be used elsewhere in the plugin. NOTE: this can be overriden by external plugins
		add_filter('qsot-options-class-name', array(__CLASS__, 'class_name'), 0, 0);
	}

	// give the default name of the events subplugin class name
	public static function class_name() { return __CLASS__; }

	public static function &instance($force=false) {
		$obj = self::$instance;
		$class= __CLASS__;

		if ($force || !is_object(self::$instance) || !is_a(self::$instance, __CLASS__)) {
			$obj = new $class();
		}

		return $obj;
	}

	public static function get_option_value($current, $option_name) {
		$o =& qsot_options::instance();
		return $o->{$option_name};
	}

	public function __construct() {
		$this->tab_slug = self::$tab_slug;
	}

	public function __set($name, $value) {
		$this->values[$name] = $value;

		$pairs = array();
		if (strstr($name, '[')) {
			parse_str($name, $oarray);
			$option_name = current(array_keys($oarray));
			$pairs[$option_name] = get_option($option_name, array());
			$pairs[$option_name] = !is_array($pairs[$option_name]) ? array() : $pairs[$option_name];
			$key = key($oarray[$option_name]);
			$pairs[$option_name][$key] = $value;
		} else {
			$pairs[$option_name] = $value;
		}

		foreach ($pairs as $k => $v) update_option($k, $v);
	}
	public function __get($name) {
		if (!isset($this->values[$name]) || empty($this->values[$name])) $this->values[$name] = get_option($name, isset($this->defaults[$name]) ? $this->defaults[$name] : '');
		return empty($this->values[$name]) && isset($this->defaults[$name]) ? $this->defaults[$name] : $this->values[$name];
	}
	public function __isset($name) {
		if (isset($this->values[$name])) return true;
		elseif (isset($this->defaults[$name])) return true;
		else {
			$test = md5(rand(0, PHP_INT_MAX).time());
			$v = get_option($name, $test);
			if ($test == $v) return false;
			else {
				$this->values[$name] = $v;
				return true;
			}
		}
	}
	public function __unset($name) {
		$this->set($name, '');
	}

	public function add($args) {
		static $odef = 10;
		$args = wp_parse_args($args, array(
			'title' => '',
			'order' => $odef++,
			'id' => false,
			'class' => '',
			'style' => '',
			'default' => '',
			'type' => 'text',
			'desc' => '',
			'desc_tip' => false,
		));
		$this->options[] = $args;
	}

	public function get_ordered() {
		$pri = array();
		foreach ($this->options as $o) $pri[] = $o['order'];
		$o = $this->options;
		array_multisort($pri, SORT_ASC, SORT_NUMERIC, $o);
		return $o;
	}

	public function get($id=false) {
		if (empty($id) || $id == '_') return $this->get_ordered();
		else {
			$res = null;
			foreach ($this->options as $o) {
				if ($o['id'] == $id) {
					$res = $o;
					break;
				}
			}
			return $res;
		}
	}

	public function refresh($keys=array()) {
		$keys = is_array($keys) ? $keys : ( empty($keys) ? array() : (array)$keys );
		$keys = empty($keys) ? array_keys($this->values) : $keys;

		foreach ($keys as $k) {
			unset($this->values[$k]);
			$this->{$k};
		}
	}

	public function remove($id) {
		$o = $this->options;
		$this->options = array();

		foreach ($o as $opt) {
			if ($opt['id'] != $id) {
				$this->options[] = $opt;
			}
		}
	}

	public function def($key, $value) {
		if (!is_array($key)) $key = array($key => $value);
		$this->defaults = array_merge($this->defaults, $key);
	}

	public static function add_woo_tab($current) {
		if (!isset($current[self::$tab_slug])) $current[self::$tab_slug] = self::$instance->product_name;
		return $current;
	}

	public static function draw_woo_tab_panel() {
		global $qsot_settings;
		woocommerce_admin_fields($qsot_settings[self::$tab_slug]);
	}

	public static function woo_settings_tab() {
		global $qsot_settings;

		$options =& qsot_options::instance();
		$o = $options->get_ordered();
		
		$qsot_settings[self::$tab_slug] = apply_filters('qsot-woocommerce-settings', $o);
	}

	public static function get_all_general_settings($settings) {
		$options =& qsot_options::instance();
		$o = $options->get_ordered();
		
		$settings = array_merge($settings, apply_filters('qsot-woocommerce-settings', $o));
		return $settings;
	}
}

if (defined('ABSPATH') && function_exists('add_action')) {
	qsot_options::pre_init();
}
