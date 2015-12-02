<?php if ( __FILE__ == $_SERVER['SCRIPT_FILENAME'] ) die( header( 'Location: /') );

/* Settings Singleton. Contains all the settings used throughout the events plugin. INTERNAL SETTINGS ONLY. for the user-input settings (admin page) checkout qsot_options */
class qsot_settings {
	protected static $initialized = false; // has this class been initialized? prevents the class from being initialized multiple times on accident (could cause setting overwritting)
	protected static $instance = null; // holds the singleton instance
	protected static $settings_key = '_qsot-event-settings'; // setting_name from the wp_settings table that stores the saved settings

	protected $settings = array(); // holds the settings while this session is active

	// setup this class
	public static function pre_init() {
		if (!self::$initialized) { // if we have not yet been initialized, then initialize
			self::$initialized = true;
			self::instance(); // generate the singleton instance
		}
		// declare the initial value of the settings class name, which will be used elsewhere in the plugin. NOTE: this can be overriden by external plugins
		add_filter('qsot-settings-class-name', array(__CLASS__, 'settings_class_name'), 0, 0);

		// public filter to fetch settings
		add_filter( 'qsot-setting', array( __CLASS__, 'get_setting' ), 10, 2 );
	}

	// give the default name of the events subplugin class name
	public static function settings_class_name() { return __CLASS__; }

	// initialize the singleton instance
	public static function &instance() {
		$class = __CLASS__;
		// if the instance does not yet exist, create it
		if (!isset(self::$instance) || !is_a(self::$instance, $class))
			self::$instance = new $class();
		// return the instance
		return self::$instance;
	}

	// fetch a setting value
	public static function get_setting( $current, $name ) {
		$res = self::instance()->{ $name };
		return null === $res ? $current : $res;
	}

	// setup the object
	public function __construct($settings='') {
		// load the settings
		$this->_load_settings($settings);
	}

	// magic to shorten the syntax on the settings object
	public function __set($name, $value) { $this->set($name, $value); }
	public function __get($name) { return $this->get($name); }
	public function __isset($name) { return $this->_isset($name); }
	public function __unset($name) { $this->_unset($name); }

	// load the settings from the db, and transpose any passed initial settings
	protected function _load_settings($new_settings='') {
		// load the settings from the db
		$settings = get_option(self::$settings_key);
		$this->settings = is_array($settings) ? $settings : array();
		// transpose passed settings
		$this->_set_all(wp_parse_args($new_settings, array()));
	}

	// special case to set all the settings. only called if the $name var is left blank when calling the 'set' method
	protected function _set_all( $settings ) {
		// use the existing settings as a base value, and transpose the new settings on top of them
		if ( ! empty( $settings ) )
			$this->settings = QSOT_Utils::extend( $this->settings, $settings );
	}

	// @OPTION-SYNTAX
	// sets an setting in the setting tree
	// for nested setting setting, accepts '.' delimited key names. consider the following setting tree
	// $settings = array(
	//   'top-level' => array(
	//     'nested-value' => 'some-value',
	//   ),
	// );
	// to set the ['top-level']['nested-value'] setting, without having to set the entire ['top-level'] setting (php limitation on magic methods), you can simply call:
	//   $settings->set('top-level.nested-value', 'some-other-value');
	// alternatively, you can also use a shorter, yet more complicated syntax
	//   $settings->{'top-level.nested-value'} = 'some-other-value';
	// both achieve the same result
	public function set($name, $value) {
		// special case of an empty name, means we want to set the top level of the setting tree to the given value
		if (empty($name)) {
			$this->_set_all($value);
		// if we have a name specified, we need to set that value in the setting tree
		} else {
			// convert the name into a list of nested keys in the settings array
			$name = is_array($name) ? $name : explode('.', $name);
			// call the recursive setting setter function
			$this->_set($name, $value, $this->settings);
		}
	}

	// @NAMES-ARRAY
	// recursively sets the setting value, based on the list of $names, which are nested array keys in the setting tree. from the example given in the comments of the 
	// set method above, marked with @OPTION-SYNTAX, we would have a $names variable here of:
	// $names = array(
	//   'top-level',
	//   'nested-value',
	// );
	protected function _set($names, $value, &$from) {
		// get this level's key name
		$name = array_shift($names);
		// if there are more levels to travel down
		if (count($names)) {
			// if the current level setting key has a current value
			if (isset($from[$name])) {
				// if the current level setting key value is an array, then travel down further
				if (is_array($from[$name])) $this->_set($names, $value, $from[$name]);
				// if it is not an array
				else {
					// make it an array (the sloppy way)
					$from[$name] = (array)$from[$name];
					// continue traveling down the tree
					$this->_set($names, $value, $from[$name]);
				}
			// if there is no current value for this level of the setting tree
			} else {
				// create a value that allows us to travel down further
				$from[$name] = array();
				// continue to travel down the tree
				$this->_set($names, $value, $from[$name]);
			}
		// if this is the last level to travel down, then set the value here
		} else {
			$from[$name] = $value;
		}
	}

	// gets an settings value based on the setting name, the syntax of which is described above the 'set' method above, marked with @OPTION-SYNTAX
	public function get($name) {
		// convert the name into a list of nested keys in the settings array
		$name = is_array($name) ? $name : explode('.', $name);
		// return the value generated from the recursive setting getter
		return $this->_get($name, $this->settings);
	}

	// recursively finds an setting value based on the $names array. for information on the $names array look above the '_set' method above, marked @NAMES-ARRAY
	protected function _get($names, &$from) {
		// get the setting key for this level of the setting tree
		$name = array_shift($names);
		// if we are looking for something deeper in the tree
		if (count($names)) {
			// dig deeper into the tree for the setting
			return isset($from[$name]) ? $this->_get($names, $from[$name]) : null;
		// if this is the level of the setting tree that we need the setting from
		} else {
			// return this setting's value
			return isset($from[$name]) ? $from[$name] : null;
		}
	}

	protected function _isset($name) {
		// convert the name into a list of nested keys in the settings array
		$name = is_array($name) ? $name : explode('.', $name);

		// setup a non-recursive loop to check if isset
		$iss = false;
		// copy settings for use in the non-recursive loop
		$from = $this->settings;
		// if we still have name pieces to drudge through
		while (($n = array_shift($name)) !== null) {
			// make sure that this level of the setting tree isset, because if this is above the one we are looking for, and it is not set, then logically, the one we need cannot be set
			if (!isset($from[$n])) break;
			// if this is the level that we are actually testing, and it passed the first check, then isset is true, since the value exists
			if (count($name) == 0) $iss = true;
			// reset the setting tree to be used in the next iteration
			$from = $from[$n];
		}

		// return whether we found it to be set or not
		return $iss;
	}

	protected function _unset($name) {
		// convert the name into a list of nested keys in the settings array
		$name = is_array($name) ? $name : explode('.', $name);

		// non-recursive method to unset a nested value in an array
		// copy the settings for use in the loop
		$from = $this->settings;
		// while we still have keys to check down the setting tree
		while (($n = array_shift($name)) !== null) {
			// if this level of the tree is not set, then logically it is impossible for the the piece we are looking for to be set, so no further action is required
			if (!isset($from[$n])) break;
			// if this is the key we are looking for to unset, then do so, unset it
			if (count($name) == 0) unset($from[$n]);
			// reset the setting tree to be used in the next iteration
			$from = $from[$n];
		}
	}
}

// trickery
if (defined('ABSPATH') && function_exists('add_action')) {
	qsot_settings::pre_init();
}
