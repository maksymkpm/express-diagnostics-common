<?php
/**
 * @copyright Dreamscapenetworks LLC
 * @link http://dreamscapenetworks.com
 */
namespace validation;
use ArrayAccess;
use \helpers;
use RuntimeException;

require_once(__DIR__ . '/../validation.php');

/**
 * Container for a list of rules for one data field
 *
 * @package validate
 *
 * @property-read string field_name
 * @property-read array[] rules
 */
class field {
	const VALIDATE_CLASS = 'helpers\validate::';
	/**
	 * Field name
	 * @var string
	 */
	private $field_name;

	/**
	 * List of rules
	 * @var array
	 */
	private $rules = [];

	/**
	 * Error message
	 * @var string
	 */
	private $error;

	/**
	 * Condition to check field or not
	 * @var array
	 */
	private $condition;

	/**
	 * Name of field for validating with rules
	 *
	 * @param $field_name
	 * @param array $condition
	 *
	 */
	public function __construct($field_name, array $condition = []) {
		if (!is_string($field_name)) {
			throw new \InvalidArgumentException('Field name must be a string');
		}

		$field_name = trim($field_name);

		if (empty($field_name)) {
			throw new \InvalidArgumentException('Field name must be not empty');
		}

		$this->field_name = $field_name;
		$this->condition = $condition;
	}

	/**
	 * Returns error message for broken rule.
	 * If all rules were kept, returns FALSE.
	 *
	 * @return bool|string
	 */
	public function get_error() {
		return is_null($this->error) ? false : $this->error;
	}

	/**
	 * Returns condition array to check field or not.
	 *
	 * @return array
	 */
	public function get_condition() {
		return $this->condition;
	}

	/**
	 * Add validation rule
	 *
	 * @param $method
	 * 		Callback method or validation::CALLBACK for predefined callback function format
	 *		- constants from class "validation" @see \validation
	 *		- validation::CALLBACK constant
	 *
	 * @param null $parameters
	 * 		One or more parameters for callback method, or if we use validation::CALLBACK, it is a callback method.
	 * 		- scalar value for one parameter
	 * 		- array with list of parameters
	 * 		- if $method == validation::CALLBACK, it is callback function with predefined format function($field_value, $field_name, $all_data_array)
	 *
	 * @param string $error_message
	 * 		error message if rule will not keep
	 *
	 * @throws \InvalidArgumentException
	 *
	 * @return field
	 */
	public function add_rule($method, $parameters = null, $error_message) {
		if ($method == \validation::CALLBACK) {
			// specific formatted callback function, $parameters is callback function, the list of parameters
			// is predefined: filed_value, field_name, all_validated_data_array
			if (!is_callable($parameters)) {
				throw new \InvalidArgumentException('For validate::CALLBACK rule was set wrong callback method');
			}
		} else {
			// check method
			if (!is_string($method) && !is_array($method)) {
				throw new \InvalidArgumentException('Property "method" can be string with function name or array with object and function name.');
			}

			if ((!is_array($method) && !is_callable(self::VALIDATE_CLASS . $method)) && !is_callable($method)) {
				$method = is_array($method) ? 'array(object, function)' : $method;
				throw new \InvalidArgumentException('Property method "' . $method . '" is not exist in class helpers\validate nor global scope.');
			}

			// check parameters
			if (is_null($parameters)) {
				$parameters = [];
			} else if (is_scalar($parameters)) {
				$parameters = [$parameters];
			} else if (!is_array($parameters)) {
				throw new \InvalidArgumentException('Property "parameters" can be scalar or array');
			}
		}

		// check error message
		if (!is_string($error_message)) {
			throw new \InvalidArgumentException('Error message must be a string');
		}

		$error_message = trim($error_message);

		if (empty($error_message)) {
			$error_message = 'The validation rule ' . (is_array($method) ? 'array(object, function)' : $method) . ' is not pass.';
		}

		$rule = [
			'method' => $method,
			'parameters' => $parameters,
			'message' => $error_message,
		];

		// not_empty rule need to be the first rule of the field
		if ($method == \validation::NOT_EMPTY) {
			array_unshift($this->rules, $rule);
		} else {
			$this->rules[] = $rule;
		}

		return $this;
	}

	/**
	 * Getter
	 *
	 * @param $name
	 *
	 * @return array
	 * @throws \InvalidArgumentException
	 */
	public function __get($name) {
		switch ($name) {
			case 'rules':
				return $this->rules;
			case 'field_name':
				return $this->field_name;
		}

		throw new \InvalidArgumentException("Property '{$name}' is not found in class");
	}

	/**
	 * Get value from array by path, separator of array levels is dot.
	 *
	 * $data = array(
	 *		'some_field' => array(
	 *			'some_field2' => array(
	 *				'some_field3' => 777,
	 *				'some_field4' => 0,
	 *			),
	 *			'some_field3' => array(
	 *				'some_field3' => 666
	 *			),
	 *		),
	 *		'some_field2' => 'z'
	 *		'some_field3' => 2,
	 *	);
	 *	Example:
	 *		1) $field_name = 'some_field2',
	 *			- result = 'z'
	 *		2) $field_name = 'some_field[some_field2][some_field3]',
	 *			- result = 777
	 * 		3) $field_name = 'some_field[some_field2]',
	 * 			- result = array('some_field3' => 777, 'some_field4' => 0)
	 * 	    4) $field_name = 'some_field[some_field2][]',
	 * 			- result = array('some_field3' => 777, 'some_field4' => 0)
	 *
	 * @param string $path_in_array 'some_field' or 'some_field[some_field2]' of 'some_field[]'
	 *      - in case of the HTML forms - exactly the 'name' attribute of the input field
	 * @param array $data
	 * @return null|mixed
	 */
	public static function array_path($path_in_array, $data) {
		if(!is_array($data) && !($data instanceof ArrayAccess)){
			throw new RuntimeException('$data must be array or ArrayAccess object');
		}

		//case if field name = first_level[types][value][]
		if (stripos($path_in_array, '[') !== false) {
			$keys = explode('[', str_replace(']', '', $path_in_array));
			$keys = array_filter($keys, function($element) { return !empty($element); });
			$group = $data;
			$value = null;

			do {
				$key = trim(array_shift($keys));

				if (!isset($group[$key])) {
					// key is not exists
					break;
				}

				if (empty($keys)) {
					// end of path and key is found
					$value = $group[$key];
				}

				if (!is_array($group[$key])) {
					// We can not go deeper, user expects more levels in the path
					break;
				}

				// Go deeper
				$group = $group[$key];
			} while ($key);

		} else {
			$value = isset($data[$path_in_array]) ? $data[$path_in_array] : null;
		}

		return $value;
	}

	/**
	 * Check if field has valid value by set rules
	 *
	 * @param mixed $value
	 *        value to validate the field for
	 *
	 * @param array $data
	 *        all input data - for the __CALLBACK__ validation method
	 * @return bool
	 */
	public function is_valid($value, $data = []) {
		if(!is_array($data) && !($data instanceof ArrayAccess)){
			throw new RuntimeException('$data must be array or ArrayAccess object');
		}

		// check if the first rule of the field is not not_empty and value is empty - then valid field
		if ($this->rules[0]['method'] != \validation::NOT_EMPTY && !helpers\validate::not_empty($value)) {
			return true;
		}

		foreach ($this->rules as $rule) {
			if ($rule['method'] == \validation::CALLBACK) {
				// predefined callback function format
				$method = $rule['parameters'];
				$parameters = [$value, $this->field_name, $data];
			} else {

				if (!is_array($rule['method']) && is_callable(self::VALIDATE_CLASS . $rule['method'])) {
					$method = self::VALIDATE_CLASS . $rule['method'];
				} else {
					$method = $rule['method'];
				}

				if (in_array($rule['method'], [\validation::IN_ARRAY, \validation::NOT_IN_ARRAY, \validation::STRICT_IN_ARRAY])) {
					// set value as first parameter and parameters array as the second in in_array function
					$parameters = [
						$value,
						$rule['parameters'],
					];
				}else {
					$parameters = $rule['parameters'];
					array_unshift($parameters, $value); // set value as first parameter in callback function
				}
			}

			try {
				if (call_user_func_array($method, $parameters)) {
					continue;
				}
			} catch (\Exception $e) {}

			$this->error = $rule['message'];

			// do not check other rule if at least one is not pass
			return false;
		}

		return true;
	}
}
