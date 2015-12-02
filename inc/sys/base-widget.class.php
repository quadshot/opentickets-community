<?php if ( __FILE__ == $_SERVER['SCRIPT_FILENAME'] ) die( header( 'Location: /') );

// allow external plugins to override this class
if (!class_exists('qsot_base_widget')):

/* Creates an even more streamlined base widget. also adds templating. */
abstract class qsot_events_base_widget extends WP_Widget {
	protected $_base_dir;
	protected $_base_url;
	protected $proper_name = ''; // default proper widget name - i18n cannot be used here, see pre_init()
	protected $short_name = 'new-widget'; // default short widget name
	protected $defaults = array(); // default widget setting defaults
	protected $exclude = array(); // default list of setting keys to exclude from the strip tags filter
	protected $use_cache = false; // whether to use cache or not
	protected $o = array();
	private $start_time;
	protected static $_templates = array(); // cached templates list
	protected static $_template_dirs = array(); // templates dirs list
	protected static $all_widget_time = 0;
	protected static $usemc = false;

	// require the widget to specify a draw function and a settings form function. everything else is optionally overridden
	abstract protected function _widget($args, $instance);
	abstract protected function _form($inst);

	// setup the base class so that it can do what it needs to
	public static function pre_init() {
		self::$proper_name = __('New Widget','opentickets-community-edition');
	}
	
	// wrapper function for core WP widget system to call, that will chain call our widget's form function
	public function form($inst) {
		$inst = $this->_ni($inst);
		return $this->_form($inst);
	}

	// generic update function. this is called when the admin user hit's 'save' on the widget. it's goal is to normalize the settings that the user selected before they are 
	// written to the database, in an attempt to eliminate data curruption of the settings
	public function update($new_inst, $old_inst) {
		// transpose the old instance settings (prior to the save button being hit) over top of the widget default settings
		$inst = $this->_ni($old_inst);
		// transpose the enw settings that the user just selected overtop of the normalized old widget settings
		$inst = wp_parse_args((array)$new_inst, $old_inst);
		// strip tags on settings that dont use them
		$inst = $this->_st($inst);
		return $inst;
	}

	protected function _clear_cache($keys, $methods=false) {
		static $headers = false;
		$methods = empty($methods) ? array('header', 'cookie') : $methods;
		$valid = array('header', 'cookie', 'post', 'get', 'request');
		$ks = $ms = array();

		foreach ($keys as $key) $ks[] = trim(strtolower($key));
		foreach ($methods as $method) {
			$method = trim(strtolower($method));
			if (in_array($method, $valid)) $ms[] = $method;
		}
		if (empty($ms)) return false;

		$clear = false;

		foreach ($ms as $m) {
			switch ($m) {
				case 'header':
					if ($headers === false) {
						$headers = function_exists('getallheaders') ? getallheaders() : array();
						$headers = array_change_key_case($headers);
					}
					foreach ($ks as $k) if (isset($headers[$k])) {
						$clear = true;
						break 3;
					}
				break;

				case 'cookie':
					foreach ($ks as $k) if (isset($_COOKIE[$k]) && $_COOKIE[$k] == 9999) {
						$clear = true;
						break 3;
					}
				break;

				case 'post':
					foreach ($ks as $k) if (isset($_POST[$k]) && $_POST[$k] == 2) {
						$clear = true;
						break 3;
					}
				break;

				case 'get':
					foreach ($ks as $k) if (isset($_GET[$k]) && $_GET[$k] == 2) {
						$clear = true;
						break 3;
					}
				break;

				case 'request':
					foreach ($ks as $k) if (isset($_REQUEST[$k]) && $_REQUEST[$k] == 2) {
						$clear = true;
						break 3;
					}
				break;
			}
		}

		return $clear;
	}

	// wrapper widget function that core WP calls when it is ready to draw the widget. this wrapper contains code that will cache the result of the draw for a period of time,
	// in an attempt to improve performance on a site wide scale, since some of the widgets are heavy.
	public function widget($args, $instance) {
		// start debuggin draw/calculation time
		$this->_start_time();

		$instance = $this->_ni($instance);

		if ($this->use_cache) {
			// get the name of this class, which will be used to make the widget cache key name
			$class = get_class($this);
			// create a cache key name for this widget, and allow other plugins and sub plugins to modify it if needed
			$key = apply_filters('widget-cache-key-'.$class, apply_filters('widget-cache-key',
				// unique name based on the generic widget info, and the settings that this instance holds, so that each instance can be cached independently.
				// the name can be no more than 250 characters, which is a memcache limitiation
				substr(substr($this->short_name, 0, 4).md5($this->proper_name.implode('.', array_keys($instance))).implode('.', $instance), 0, 250),
				$class
			));
			// determine if the widget cache is being forced to be recalculated by the end user
			$clear_cache = $this->_clear_cache(array('clear_cache', 'clear_widget_cache'));
			$now = time();
			$html = '';
			$expired = $from_cache = $cache = false;

			// if the cache is not being manually cleared
			if (!$clear_cache) {
				// load the cache for this widget
				$cache = self::$usemc ?  wp_cache_get($key, 'sidebar-widgets') : get_transient(md5($key));
				// if the cache is in the correct format
				if (is_array($cache) && isset($cache['html'], $cache['expire'])) {
					// and if the cache is about to expire
					if ($now > $cache['expire'] - rand(0, 20)) { // if the cache is set to exipre within about 20 seconds from now
						// then push the timer back an hour and make this client redraw the cache
						$expired = true;
						$cache['expire'] = $now + 3600 + rand(0, 300);
						self::$usemc ? wp_cache_set($key, $cache, 'sidebar-widgets', 0) : set_transient(md5($key), $cache, 0);
						$cache = false;
					}
				// if the cache is not in the right format, then pretend there is no cache at all
				} else $cache = false;
			}

			if (!$clear_cache && !$expired && $cache !== false && is_array($cache) && isset($cache['html'], $cache['expire'])) {
				// if the cache is still good then use the cache
				$html = $cache['html'];
				$from_cache = true;
			} else {
				// if the cache is NOT good, manually cleared, or gone, then redraw the cache and use the redraw
				ob_start();
				// call the widget draw function
				$this->_widget($args, $instance);
				$html = ob_get_contents();
				ob_end_clean();
				// setup the cache in the proper format
				$cache = array(
					'expire' => $now + 3600 + rand(0, 300),
					'html' => $html,
				);
				// set the cache with the new cache value
				self::$usemc ? wp_cache_set($key, $cache, 'sidebar-widgets', 0) : set_transient(md5($key), $cache, 0);
			}

			echo $html;
		} else {
			$this->_widget($args, $instance);
			$key = 'non-cached-'.$this->short_name;
			$from_cache = false;
		}

		$this->_end_time($from_cache);
	}

	// start the timer for drawing this widget
	private function _start_time() {
		$this->start_time = microtime(true) * 1000;
	}

	// end the timer for drawing this widget and then draw out some debug info about the time it took and the process it followed
	private function _end_time($from_cache=false) {
		$end_time = microtime(true) * 1000;
		$diff = $end_time - $this->start_time;
		self::$all_widget_time += $diff;

		echo '<!-- WC:'.($from_cache ? '' : 'NOT-').'FC:'.$this->proper_name.':'.$diff.'ms;'.self::$all_widget_time.'ms; -->';
	}

	// sets up the base info about a specific widget
	protected function _setup_widget($class, $file) {
		// first thing, load all the options, and share them with all other parts of the plugin
		$settings_class_name = apply_filters('qsot-settings-class-name', '');
		$this->o =& call_user_func_array(array($settings_class_name, "instance"), array());
		// used for templates and for assets like js/css/imgs
		$this->_base_dir = $this->o->core_dir;
		$this->_base_url = $this->o->core_url;
		// cache the templates for this widget
		$this->_cache_templates();
	}

	// caches the templates for this specific widget
	protected function _cache_templates() {
		// setup the directory list of the directories that can contain templates for this widget, and allow other plugins, sub plugins, and widgets to control this list
		self::$_template_dirs[$this->short_name] = apply_filters($this->o->pre.$this->short_name.'-template-dirs', array(
			$this->_base_dir.'templates/widgets/',
			get_template_directory().'/templates/widgets/',
			get_stylesheet_directory().'/templates/widgets/',
		));
		// checksum is used to check if this list has changed since the last time the cache was drawn
		$checksum = md5(serialize(self::$_template_dirs[$this->short_name]));
		// allow manual clearing of this cache
		$clear_cache = $this->_clear_cache(array('clear_cache', 'clear_widget_cache'));

		// load the current template cache for this widget
		// w(i)dg(e)t f(ile) c(ache)
		$ckey = '_wdgtfc_'.$this->short_name;
		$templs = get_option($ckey, false);

		// if any of the following are true
		// : manually clearing cache
		// : the cache does not exist
		// : the cache is not in the correct format
		// : the list of template directories has changed
		// : the template list cache has expired
		if ($clear_cache || !is_array($templs) || !isset($templs['checksum'], $templs['templates'], $templs['expire'])
				|| $templs['checksum'] != $checksum || $templs['expire'] < time()) {
			// cycle through the list of template dirs for this widget
			foreach (self::$_template_dirs[$this->short_name] as $dir) {
				$dir = trailingslashit($dir);
				if (file_exists($dir) && is_dir($dir) && is_readable($dir)) {
					// foreach file in the currect directory
					foreach (scandir($dir) as $file) {
						if ($file{0} == '.') continue; // skip hidden
						if (is_dir($dir.$file)) continue; // skip sub dirs
						if (!is_readable($dir.$file)) continue; // skip unreadable
						if (!preg_match('#^'.preg_quote($this->short_name).'.#', $file)) continue; // skip files that are not templates for this widget
						$parts = explode('.', $file);
						$ext = strtolower(trim(array_pop($parts)));
						if ($ext != 'php') continue; // skip non-php files
						array_shift($parts); // shift off the base name, since we know it is the first part based on the above regex
						$label_short = $short = implode('.', $parts);
						if ($label_short == '') $label_short = 'default';
						if (!is_array(self::$_templates[$this->short_name])) self::$_templates[$this->short_name] = array();
						// add the template to the cached list of templates
						self::$_templates[$this->short_name] = array_merge(self::$_templates[$this->short_name], array(
							$label_short => $dir.$this->short_name.'.'.$short.'.'.$ext,
						));
					}
				}
			}
			// update the cache
			update_option($ckey, array('checksum' => $checksum, 'templates' => self::$_templates[$this->short_name], 'expire' => time() + (3600 + rand(0,300))));
		// if the cache is still valid, then just use the cached templates list
		} else {
			self::$_templates[$this->short_name] = $templs['templates'];
		}
	}

	// generic function that can be called to load a template and render it
	protected function _display_widget($args, $inst) {
		// normalize the instance options so that we at least have a template to load
		$inst = wp_parse_args($inst, array('template' => 'default'));
		// bring all array values into local scope as variables
		extract($args);
		extract($inst);
		// find the template file if it exists
		$templ_file = $this->_find_templ($template);
		if (empty($templ_file)) return;
		// if it does exist then load it
		include $templ_file;
	}

	// locates a template based on the template cache and the template name
	protected function _find_templ($templ) {
		// first check to see if there is an override template in the theme, regardless of whether the file exists in the cache or not
		$loc = locate_template(array('/templates/widgets/'.$this->short_name.'.'.$templ.'.php'), false);
		// if there is no override, then check the cache for a file that matches the widget and the template name
		if (empty($loc) && isset(self::$_templates[$this->short_name], self::$_templates[$this->short_name][$templ])) $loc = self::$_templates[$this->short_name][$templ];
		// return anything found, of nothing if nothing is found
		return $loc;
	}

	// normalize instance, template: makes sure that the template that is selected is one that is available in the cached template list for this widget
	protected function _ni_templ($instance, $key='template') {
		$instance = wp_parse_args((array)$instance, $this->defaults);
		// normalize the template key in a special manner
		// make sure that we are using a template that is in the cache
		$instance[$key] = is_string($instance[$key]) && isset(self::$_templates[$this->short_name], self::$_templates[$this->short_name][$instance[$key]])
			? $instance[$key]
			: 'default';
		return $instance;
	}

	// normalize instance: transposes the instance values over the defaults for this widget
	protected function _ni(&$instance) {
		$instance = wp_parse_args((array)$instance, $this->defaults);
		// handled by the find_templ function now
		//$instance = $this->_ni_templ($instance);
		$instance = $this->_st($instance);
		return $instance;
	}

	// recursively strip tags from all widget options, except those that exist on this widget's exclusion list. there is an exclusion list because some options could validly have tags
	protected function _st($data, $exclude=array()) {
		$exclude = array_unique(array_merge($this->exclude, (array)$exclude));
		if (is_object($data) || is_array($data)) {
			foreach ($data as $key => &$element)
				if (!in_array($key, $exclude))
					$element = $this->_st($element);
		} else $data = strip_tags($data);
		return $data;
	}
}

if (defined('ABSPATH') && function_exists('add_action')) {
}

endif;
