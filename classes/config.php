<?php
/**
 * @copyright Dreamscapenetworks LLC
 * @link http://dreamscapenetworks.com
 * Date: 12/01/14
 */

/**
 * Prevent to use configuration files directly.
 * At the beginning of every configuration file we must and a line:
 * defined('CONFIGURATION_LOADED') or die('Forbidden');
 */
define('CONFIGURATION_LOADED', 1);

/**
 * Class for getting configuration parameters
 *
 * HOW TO USE:
 * @see config::add_source($source)
 * @see config::get($property_path, $default = null)
 * @see config::get_source($property_path)
 */
abstract class config {
	/**
	 * Configuration files sources list
	 * @var string[]
	 */
	private static $sources = [];

	/**
	 * Cached groups of parameters
	 * @var array[]
	 */
	private static $groups = [];

	/**
	 * Get config property by path.
	 *
	 * HOW TO USE:
	 * $username = config::get('database.main.username'); // will return "root"
	 * 		group name: "database"
	 * 		config file name: "database.php"
	 * 		config file: [
	 * 						'main' => [
	 * 							'username' => 'root',
	 * 							...
	 * 					]];
	 *
	 * // default value
	 * $port = config::get('database.default.port', 3305); // if parameter 'port' is omitted in config, will return 3305 instead of NULL
	 *
	 * @param string $property_path
	 * 		Path to the property separated with dot, ex: "database.main.username" or just "database" for getting whole group of properties
	 * @param mixed $default
	 * 		If property is not found, NULL will be returned. If provide default value, than if property is nit exist, default value will be returned.
	 *
	 * @return mixed|null
	 *
	 * @throws Exception
	 * 		- if $property_path is not a string
	 * 		- no one source has been provided
	 * 		- configuration file has a wrong format
	 */
	public static function get($property_path, $default = null) {
		list($group_name, $path) = self::parse_path($property_path);

		$group = self::load_group($group_name);

		// if group is not exist we should return $default
		if ($group === false) {
			return $default;
		}

		// if user do not provide a path we will return whole group
		if (empty($path)) {
			return $group;
		}

		// find the path in array and return property or null if path is not exists
		$result = self::path($group, $path);

		return is_null($result) ? $default : $result;
	}

	/**
	 * Manually add source for loading config data. Later added sources have
	 * higher priority and will hide/cover properties' values if it have a property with the same path.
	 *
	 * @param string $source
	 *
	 * @throws InvalidArgumentException
	 * 		- $source is not a string
	 * 		- $source is not a valid directory path
	 */
	public static function add_source($source) {
		if (!is_string($source)) {
			throw new InvalidArgumentException('Argument $source is not a string');
		}

		$source = realpath($source);

		if (!is_dir($source)) {
			throw new InvalidArgumentException('Argument $source is not a valid directory path');
		}

		// check for duplicate
		if (in_array($source, self::$sources)) {
			return;
		}

		// add at the end of the list of sources, because this source should have more priority
		self::$sources[] = $source;

		// clear cache for margin new source of configuration data with existing
		self::$groups = [];
	}

	/**
	 * Debug information about source, for understanding from which source we get value for this path.
	 * Returns value and list of sources which influence on building the result.
	 *
	 * @param string $property_path
	 *
	 * @return string
	 * 		debug information
	 *
	 * @throws Exception
	 * 		- configuration file has a wrong format and does not return an array
	 */
	public static function get_source($property_path) {
		$info = '';
		$value = false;

		list($group_name, $path) = self::parse_path($property_path);

		foreach (self::$sources as $source) {
			$file_path = $source . DIRECTORY_SEPARATOR . $group_name . '.php';

			if (!file_exists($file_path)) {
				continue;
			}

			$group = include($file_path);

			if (!is_array($group)) {
				throw new Exception('Config file "' . $file_path . '" has the wrong format and does not return an array');
			}

			$result = self::path($group, $path);

			if (is_null($result)) {
				continue;
			}

			if (is_scalar($result) || is_scalar($value)) {
				// rewrite previous source value
				$value = $result;
			} else {
				// arrays will combine
				$value = self::merge_configs($value, $result);
			}

			$info .= (empty($info) ? '' : "\n") . $source;
		}

		if (empty($info)) {
			$info = 'No one source contains the property';
			$value = 'NULL';
		}

		return "PROPERTY: {$property_path}\nLOADED FROM:\n{$info}\nVALUE IS:\n" . print_r($value, true);
	}

	/**
	 * Split property path to group name and path inside the group
	 *
	 * @param string $property_path
	 *
	 * @return array
	 * 		array(
	 * 			0 => group_name,
	 * 			1 => path,
	 * 		)
	 * @throws InvalidArgumentException
	 *  	- not string
	 * 		- empty property path
	 */
	private static function parse_path($property_path) {
		if (!is_string($property_path)) {
			throw new InvalidArgumentException('Property path must be a string');
		} else if (empty($property_path)) {
			throw new InvalidArgumentException('Property path is empty');
		}

		if (strpos($property_path, '.') === false) {
			// it is a group name, like "database"
			return [$property_path, ''];
		}

		// it is a path to the property, like "database.default.username" and we split it for group name and path inside the group.
		return explode('.', $property_path, 2);
	}

	/**
	 * Load the group of configuration parameters
	 *
	 * @param string $group_name
	 *
	 * @return array|false
	 *
	 * @throws Exception
	 * 		- list of sources is empty
	 * 		- configuration file has a wrong format and do not return an array
	 */
	private static function load_group($group_name) {
		if (!isset(self::$groups[$group_name])) {
			$config = false;

			if (empty(self::$sources)) {
				throw new Exception('No configuration sources setup. Use config::add_source().');
			}

			foreach (self::$sources as $source) {
				$file_path = $source . DIRECTORY_SEPARATOR . $group_name . '.php';

				if (!file_exists($file_path)) {
					continue;
				}

				$group = include($file_path);

				if (!is_array($group)) {
					throw new Exception('Config file "' . $file_path . '" has the wrong format and does not return an array');
				}

				// merge configs with replacement of existing fields, it allows us hide specific global config property with local
				// and do not set all properties in local, but set properties which are needed to be rewritten
				$config = $config === false ? $group : self::merge_configs($config, $group);
			}

			self::$groups[$group_name] = $config;
		}

		return self::$groups[$group_name];
	}

	/**
	 * Return property from multi level array
	 *
	 * @param array $group
	 *        array with properties
	 * @param string $path
	 *        dot separated path to the array item
	 *
	 * @return mixed|null
	 */
	private static function path(array $group, $path) {
		if (array_key_exists($path, $group)) {
			// First level path
			return $group[$path];
		}

		$path = trim($path, '. ');
		$keys = explode('.', $path);

		do {
			$key = array_shift($keys);

			if (!isset($group[$key])) {
				// key is not exists
				break;
			}

			if (empty($keys)) {
				// end of path and key is found
				return $group[$key];
			}

			if (!is_array($group[$key])) {
				// We can not go deeper, user expects more levels in config
				break;
			}
			
			// Go deeper
			$group = $group[$key];
		} while ($key);

		return null; // we can return false, but it will disallow us to use FALSE as value for config property
	}

	/**
	 * Merge data from config_2 to config_1 with replace fields recursively
	 *
	 * @param array $config_1
	 * @param array $config_2
	 *
	 * @return array
	 */
	private static function merge_configs(array $config_1, array $config_2) {
		foreach ($config_2 as $key => $value) {
			if (is_array($value) && isset($config_1[$key]) && is_array($config_1[$key])) {
				$config_1[$key] = self::merge_configs($config_1[$key], $value);
			} else {
				$config_1[$key] = $value;
			}
		}

		return $config_1;
	}
}
