<?php (__FILE__ == $_SERVER['SCRIPT_FILENAME']) ? die(header('Location: /')) : null;

/* Object extender. A different way of combining to sets of parameters, similar to, yet different from, wp_parse_args(). */
class qsobj {
	// the general idea is that values in $obj are used as base values, and values in $obj2 are transposed over top of those base values.
	public static function extend(&$obj, &$obj2, $make_array=true) {
		if (!is_array($obj) && !is_object($obj)) {
			if ($make_array) $obj = (array)$obj;
			else return $obj;
		}
		if (!is_array($obj2) && !is_object($obj2)) $obj2 = (array)$obj2;
		if (is_array($obj)) {
			self::_extendA($obj, $obj2);
		} else if (is_object($obj)) {
			self::_extendO($obj, $obj2);
		} else {
			throw new Exception('Could not extend. Invalid type.');
		}
		return $obj;
	}

	protected static function _extendA(&$obj, &$obj2) {
		foreach ($obj2 as $key => $value) {
			$obj[$key] = $value;
		}
	}

	protected static function _extendO(&$obj, &$obj2) {
		foreach ($obj2 as $key => $value) {
			$obj->$key = $value;
		}
	}
}
