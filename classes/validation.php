<?php
/**
 * @copyright Dreamscapenetworks LLC
 * @link http://dreamscapenetworks.com
 */

require_once('validation/field.php');
require_once('helpers/validate.php');

use \validation\field;
use \validation\uploaded_file;

/**
 * Class validation is used for validate array of data by specific rules and generate error messages
 *
 * If you use validation rules from folder, need set path
 * validation::set_forms_validation_path('/home/<site>/<your directory>/');
 *
 *
 * $validation = new validation(); or $validation = new validation(<form_id>); - form id not required
 * $validation->add_field('form_field_name')
 * 		->add_rule(validation::NOT_EMPTY)
 * 		->add_rule(validation::IS_STRING, null, 'Text of error')
 * 		->add_rule(validation::MIN_LENGTH, 4, 'Length must be minimum 4 characters')
 * 		->add_rule(validation::CALLBACK, 'some_class::method_name', 'Text of error') // some_class::method_name($field_value, $field_name, array $all_data)
 * 		->add_rule('other_class::next_method', ['param_1', 'param_2']) // other_class::next_method($value, $param_1, $param_2)
 * 		->add_rule([$object, 'method'], 20); // will call $object->method($filed_value, 20)
 *
 * $data = array('form_field_name' => 'value');
 * $validate->is_valid($data);
 *
 * $errors = $validate->get_errors();
 *
 * @see validation::add_field
 * @see \validation\field::add_rule
 * @see validation::get_errors
 *
 */
class validation {
	const CALLBACK = '__CALLBACK__'; // specific validation method with exact format: function($field_value, $filed_name, $all_data_array)

	const NOT_EMPTY = 'not_empty'; /* @see helpers\validate::not_empty() */
	const EQUAL = 'equal'; /* @see helpers\validate::equal() */
	const NOT_EQUAL = 'not_equal'; /* @see helpers\validate::not_equal() */

	const IS_STRING = 'is_string'; /* @see helpers\validate::is_string() */
	const IS_NUMBER = 'is_number'; /* @see helpers\validate::is_number() */
	const IS_ARRAY = 'is_array'; /* @see helpers\validate::is_array() */

	const MIN_LENGTH = 'min_length'; /* @see helpers\validate::min_length() */
	const MAX_LENGTH = 'max_length'; /* @see helpers\validate::max_length() */
	const LENGTH = 'length'; /* @see helpers\validate::length() */

	const IN_ARRAY = 'in_array'; /* @see helpers\validate::in_array() */
	const STRICT_IN_ARRAY = 'strict_in_array'; /* @see helpers\validate::strict_in_array() */
	const NOT_IN_ARRAY = 'not_in_array'; /* @see helpers\validate::not_in_array() */

	/** @see helpers\validate::regex()
	 *
	 * NOTICE: RegExpt must be in defined format:
	 * 	- $regex expression MUST be compatible with javascript regular expression format
	 * 	- use only slash symbols [/] for start and end of expression
	 * 	- only 3 flags acceptable in js: g - Global, m - Multiline, i - Case insensitive
	 * 	- Check regular expressions on https://regex101.com/ for pcre and javascript compatibility before use it
	 */
	const REGEX = 'regex';

	const EMAIL = 'email'; /* @see helpers\validate::email() */
	const EMAIL_MX = 'email_mx'; /* @see helpers\validate::email_with_mx() */

	const URL = 'url_exists'; /* @see helpers\validate::url_exists() */
	const WEBSITE = 'website_exists'; /* @see helpers\validate::website_exists() */

	const IP = 'ip'; /* @see helpers\validate::ip() */
	const IPV4 = 'ipv4'; /* @see helpers\validate::ipv4() */
	const IPV6 = 'ipv6'; /* @see helpers\validate::ipv6() */

	const YEAR = 'year'; /* @see helpers\validate::year() */
	const DATE = 'date'; /* @see helpers\validate::date() */
	const DATE_IN_PAST = 'date_in_past'; /* @see helpers\validate::date_in_past() */

	const ABN = 'abn'; /* @see helpers\validate::abn() */
	const ACN = 'acn'; /* @see helpers\validate::acn() */

	const CALLING_CODE = 'country_calling_code'; /* @see helpers\validate::country_calling_code() */
	const PHONE_NUMBER = 'phone_number'; /* @see helpers\validate::phone_number() */

	# patterns for domains matching
	const PATTERN_FQDN = "/^\\s*(?!-)(?:[a-zA-Z\\d\\-]{0,62}[a-zA-Z\\d]\\.){1,126}(?!\\d+)[a-zA-Z\\d]{2,63}\\s*$/";
	const PATTERN_DOMAIN = "/^\\s*(?!-)(?![^.]{63,})[a-zA-Z0-9- ]*[0-9A-Za-z ](?:\\.(?!\\d+|-)(?![^.]{63,})[a-zA-Z0-9- ]*[0-9A-Za-z ])*\\s*$/";
	//const PATTERN_DOMAIN = "/(?!\\.)^(?:\\.?(?!\\d+|-)(?![a-z0-9-]+-{63,})(?![a-zA-Z0-9-]{65,})[a-z0-9-]+[a-z0-9])+$/i";
	const PATTERN_BULK_DOMAINS = "/^\\s*(?:[0-9A-Za-z- ]+(?:\\.[ 0-9A-Za-z-]+)*\\s*(\\s+[0-9A-Za-z- ]+(?:\\.[0-9A-Za-z- ]+)*)*)\\s*$/";
	const PATTERN_BULK_FQDN = "/^\\s*(?:[0-9A-Za-z- ]+(?:\\.[ 0-9A-Za-z-]+)+\\s*(\\s+[0-9A-Za-z- ]+(?:\\.[0-9A-Za-z- ]+)+)*)\\s*$/";
	const PATTERN_URL = "/^\\s*(?:([A-Za-z]+):)?(\\/{0,3})(?!-)(?:[a-zA-Z\\d\\-]{0,62}[a-zA-Z\\d]\\.){1,126}(?!\\d+)[a-zA-Z\\d]{2,63}(?::(\\d+))?(?:\\/([^?#]*))?(?:\\?([^#]*))?(?:#(.*))?\\s*$/";

	public static $validation_constants; // array with validation constants

	/**
	 * List of rules that can be used only on backend and will not send to frontend
	 *
	 * @var array
	 */
	private static $backend_rules = [
		self::CALLBACK => self::CALLBACK,
		self::IS_STRING => self::IS_STRING,
		self::IS_ARRAY => self::IS_ARRAY,
		self::EMAIL_MX => self::EMAIL_MX,
		self::URL => self::URL,
		self::WEBSITE => self::WEBSITE,
	];

	/**
	 * Array with path to directories with validation files
	 *
	 * @var array
	 */
	private static $form_rules_path = [];

	/**
	 * List of rules for forms on current page
	 *
	 * @var validation[]
	 */
	private static $forms_validation_on_page = [];

	/**
	 * List of fields
	 *
	 * @var validation\field[]
	 */
	private $fields = [];

	/**
	 * List of 'file' fields
	 *
	 * @var validation\uploaded_file[]
	 */
	private $files = [];

	/**
	 * Form id
	 *
	 * @var string
	 */
	private $form_id;

	/**
	 * Condition for validate field
	 *
	 * array(field_name, rule, value)
	 *
	 * @var array
	 */
	private $condition = [];

	/**
	 * List of errors
	 *
	 * @var array
	 */
	private $errors = [];

	#region Methods providing data for frontend validation
	/**
	 * Set path of form validation files
	 *
	 * @param $path
	 */
	public static function set_forms_validation_path($path) {
		if (empty($path) || !is_string($path)) {
			throw new InvalidArgumentException('Property "$path" must be a string.');
		}

		$real_path = realpath(rtrim($path, '/'));

		if (!is_dir($real_path)){
			throw new InvalidArgumentException('This is not a directory: ' . $path);
		}

		// check for duplicate
		if (in_array($real_path, self::$form_rules_path)) {
			return;
		}

		// add at the beginning  f the list of sources, because this source should have more priority
		array_unshift(self::$form_rules_path, $real_path);
	}

	/**
	 * Load rules for the form and return validation object
	 *
	 * @param $form_id
	 *
	 * @return validation
	 *
	 * @throws Exception
	 * 			- path of files not set
	 * 			- wrong property $form_id
	 * 			- file not found
	 * 			- validation object not found in file
	 * 			- wrong validation object in file
	 */
	public static function get_form_rules($form_id) {
		if (empty($form_id) || !is_string($form_id)) {
			throw new InvalidArgumentException('Property "$form_id" must be a string.');
		}

		if (empty(self::$form_rules_path)) {
			throw new InvalidArgumentException('Path to files with validation rules is not set, use validation::set_forms_validation_path().');
		}

		foreach(self::$form_rules_path as $path){
			$filename = $path . '/' . $form_id . '.php';

			if (file_exists($filename)) {
				$file = $filename;
				break;
			}
		}

		if (!isset($file)) {
			throw new InvalidArgumentException('File with rules for form "' . $form_id . '" not found.');
		}

		$validation = include($file);

		if ($validation instanceof validation) {
			if ($form_id != $validation->get_form_id()) {
				throw new Exception('Wrong form_id, in file: ' . $file);
			}

			return $validation;
		}

		throw new Exception('Validation file does not return validation object: ' . $file);
	}

	/**
	 * Register form for current frontend page.
	 * Load form rules for later render for frontend.
	 * @see get_form_rules()
	 * @see get_frontend_rules()
	 *
	 * @param $form_id
	 *
	 * @return validation
	 *
	 * @throws Exception
	 */
	public static function register_frontend_form($form_id) {
		if (!isset(self::$forms_validation_on_page[$form_id])) {
			self::$forms_validation_on_page[$form_id] = self::get_form_rules($form_id);
		}

		return self::$forms_validation_on_page[$form_id];
	}

	/**
	 * Check if we already register some rules for frontend forms
	 *
	 * @return bool
	 */
	public static function if_frontend_registered() {
		return count(self::$forms_validation_on_page) > 0;
	}

	/**
	 * Create json object with rules for all forms on the page. If no one form have been registered, 'null' will be returned.
	 *
	 * @param bool|false $debug_mode - debug mode
	 * @return string
	 */
	public static function get_frontend_rules($debug_mode = false) {
		$forms_list = [];

		if ((bool) $debug_mode) {
			$forms_list['debug'] = true;
		}

		foreach (self::$forms_validation_on_page as $validation) {
			if (empty($validation->fields) && empty($validation->files)) {
				continue;
			}

			$forms_list[$validation->form_id] = $validation->to_array();
		}

		if (empty($forms_list)) {
			return '{}';
		}

		return json_encode($forms_list);
	}
	#endregion

	/**
	 * @param string $form_id
	 */
	public function __construct($form_id = '') {
		if (!is_string($form_id)) {
			throw new InvalidArgumentException('Form id must be string or empty');
		}

		$this->form_id = trim($form_id);

		if (!isset(self::$validation_constants)) {
			$class = new ReflectionClass($this);
			self::$validation_constants = array_flip($class->getConstants());
		}
	}

	/**
	 * Register form rules for frontend from the object, if rules are not in file but created manually
	 *
	 * @return validation
	 */
	public function register() {
		if (empty($this->form_id)) {
			throw new BadMethodCallException('Form ID is not set and this validation object cannot be registered for frontend.');
		}

		return self::$forms_validation_on_page[$this->form_id] = $this;
	}

	/**
	 * Returns a list of errors or empty array if there are not any error
	 *
	 * @return array
	 * 		Array with validation error or false if validation success
	 *
	 * 		array [
	 *			'field_name_1' => 'Text of message of broken rule for field_name_1',
	 *			'field_name_2' => 'Text of message of broken rule for field_name_2',
	 * 			...
	 * 			'eligibility[au_normal][eligibility_number]' => 'Business Number may not be empty',
	 * 		]
	 */
	public function get_errors() {
		foreach($this->errors as $key => $error) {
			$new_key = self::convert_dots($key);

			if ($new_key != $key) {
				$this->errors[$new_key] = $error;

				unset($this->errors[$key]);
			}
		}

		return $this->errors;
	}

	/**
	 * Return form id
	 *
	 * @return string
	 */
	public function get_form_id() {
		return $this->form_id;
	}

	/**
	 * returns an array with form_id and errors as used in frontend validation
	 * @return array
	 */
	public function get_errors_data()
	{
		$errors =  $this->get_errors();
		if (empty($errors)) {
			return [];
		}
		return [
				'form_id' => $this->get_form_id(),
				'errors' => $errors,
		];
	}

	/**
	 * Method to add condition to the field. Use it before add_field method.
	 *
	 *  Example:
	 * $validate->if_field('user', validation::MAX_LENGTH, 10)
	 * 		->add_field('username')
	 *		->add_rule('not_empty', null, 'Username cannot be empty')
	 * 		->add_rule('min_length', 4, 'Username cannot be less than 4 characters');
	 *
	 * @param $field_name
	 * @param $rule
	 * @param null|mixed $parameters
	 *
	 * @return validation object
	 */
	public function if_field($field_name, $rule, $parameters = null) {
		if (!empty($this->condition)) {
			throw new BadMethodCallException('The validation::if_field() method can not be called twice');
		}

		if (!is_string($field_name)) {
			throw new InvalidArgumentException('Property "field_name" must be a string');
		}

		if (!is_string($rule) || (!isset(self::$validation_constants[$rule]) && !is_callable($rule))) {
			throw new InvalidArgumentException('Property "rule" must be a constant of validation class or callable function');
		}

		if (!empty($parameters) && !is_array($parameters) && !is_scalar($parameters)) {
			throw new InvalidArgumentException('Parameters must be an scalar or array');
		}

		$this->condition = [$field_name, $rule, $parameters];

		return $this;
	}

	/**
	 * Add field for validating. Add rules to this field.
	 * Example:
	 * $validate->add_field('username')
	 * 		->add_rule('not_empty', null, 'Username cannot be empty')
	 * 		->add_rule('min_length', 4, 'Username cannot be less than 4 characters')
	 * 		->add_rule('max_length', 20, 'Username cannot be more than 20 characters')
	 * 		->add_rule('regex', '/[a-z][a-z0-9_-]+/i', 'Username can contain only letters, numbers and _-, also must begin from letter');
	 *
	 * @param string $field_name
	 *
	 * @return field
	 * @throws InvalidArgumentException
	 */
	public function add_field($field_name) {
		$condition = $this->condition;
		$this->condition = [];

		return $this->fields[] = new field(self::convert_dots($field_name), $condition);
	}

	/**
	 * Add a file field to validation
	 * Example::
	 * $calidation->add_file('upload')
	 *      ->add_rule(validation::FILE_COUNT, 5, 'Can upload maximum 5 files')
	 *      ->add_rule(validation::FILE_SIZE, 10240, 'The total of 10 RB can be uploaded')
	 *      ->add_rule(validation::FILE_TYPE, array('gif', 'jpg', 'png'), 'Only images can be uploaded');
	 *
	 * @param string $field_name
	 * @return uploaded_file
	 */
	public function add_file($field_name) {
		$condition = $this->condition;
		$this->condition = [];

		return $this->files[] = new uploaded_file(self::convert_dots($field_name), $condition);
	}

	/**
	 * Check data for valid with rules. Returns TRUE if data are valid and FALSE if not.
	 * Error list you can get with get_errors method
	 *
	 * @see validation::get_errors()
	 *
	 * @param array|ArrayAccess $data
	 * 		array with data which should be validated
	 *
	 * @return bool
	 */
	public function is_valid($data) {
		if(!is_array($data) && !($data instanceof ArrayAccess)){
			throw new RuntimeException('$data must be array or ArrayAccess object');
		}

		$this->errors = [];
		$is_valid = true;

		foreach ($this->fields as $field) {
			$condition = $field->get_condition();

			if (!empty($condition)){
				$condition_field = new field($condition[0]);

				$condition_field->add_rule(self::NOT_EMPTY, null, 'False condition')
								->add_rule($condition[1], $condition[2], 'False condition');

				if (!$condition_field->is_valid($condition_field->array_path($condition_field->field_name, $data), $data)) {
					continue;
				}
			}

			$field_data = $field->array_path($field->field_name, $data);

			if (is_scalar($field_data) || empty($field_data)) {
				if (!$field->is_valid($field_data, $data)) {
					$is_valid = false;
					$this->errors[$field->field_name] = $field->get_error();
				}
			} else {
				$numeric_keys = array_filter(array_keys($field_data), function($key) { return((int) $key === $key); });
				$filtered_data = array_intersect_key($field_data, array_flip($numeric_keys));

				foreach ($filtered_data as $index => $value) {
					if (!$field->is_valid($value, $data)) {
						$is_valid = false;
						$this->errors[$field->field_name][] = [
							'index' => $index,
							'message' => $field->get_error(),
						];
					}
				}
			}
		}

		return $is_valid;
	}

	/**
	 * Check uploaded files for valid with rules. Returns TRUE if the uploaded files are valid and FALSE if not.
	 * Error list you can get with get_errors method
	 *
	 * @see validation::get_errors()
	 *
	 * @param array $data
	 * 		array with data which should be validated - must be a $_FILES array
	 *
	 * @return bool
	 */
	public function is_valid_upload(array $data) {
		$is_valid = true;

		foreach ($this->files as $file) {
			$condition = $file->get_condition();

			if (!empty($condition)){
				$condition_field = new field($condition[0]);

				$condition_field->add_rule(self::NOT_EMPTY, null, 'False condition')
					->add_rule($condition[1], $condition[2], 'False condition');

				if (!$condition_field->is_valid($condition_field->array_path($condition_field->field_name, $data), $data)) {
					continue;
				}
			}

			$file_data = $file->array_path($file->field_name, $data);

			if (empty($file_data)) {
				return $is_valid;
			} else {
				foreach ($file_data as $value) {
					if (!$file->is_valid($value, $file_data)) {
						$this->errors[$file->field_name] = $file->get_error();
						$is_valid = false;

						continue;
					}
				}
			}
		}

		return $is_valid;
	}


	/**
	 * From validation object to array
	 *
	 * @return array
	 */
	public function to_array(){
		if (empty($this->fields) && empty($this->files)) {
			return [];
		}

		$forms_rules = [];

		foreach ($this->fields as $field) {
			if (count($field->rules) == 0) {
				continue;
			}

			foreach ($field->rules as $rule) {
				if ($rule['method'] == self::CALLBACK) {
					continue;
				}

				if (isset(self::$backend_rules[$rule['method']])) {
					continue;
				}

				$row = [
					'rule' => $rule['method'],
					'message' => $rule['message'],
				];

				$field_condition = $field->get_condition();

				if (!empty($field_condition)) {
					$condition['field'] = self::convert_dots($field_condition[0]);
					$condition['rule'] = $field_condition[1];

					if (!empty($field_condition[2])) {
						if ($condition['rule'] == self::REGEX) {
							$field_condition[2] = self::prepare_regexp($field_condition[2]);
						} else if (is_scalar($field_condition[2])) {
							$field_condition[2] = [$field_condition[2]];
						}

						$condition['parameters'] = $field_condition[2];
					} else {
						$condition['parameters'] = [];
					}

					$row['condition']= $condition;
				}

				if ($rule['method'] == self::REGEX) {
					$rule['parameters'] = self::prepare_regexp($rule['parameters'][0]);
				}

				$row['parameters'] = $rule['parameters'];
				$field_name = self::convert_dots($field->field_name);
				$forms_rules[$field_name]['rules'][] = $row;
			}
		}

		foreach ($this->files as $file) {
			if (count($file->rules) == 0) {
				continue;
			}

			foreach ($file->rules as $rule) {
				$row = [
					'rule' => $rule['method'],
					'message' => $rule['message'],
				];

				$field_condition = $file->get_condition();

				if (!empty($field_condition)) {
					$condition['field'] = self::convert_dots($field_condition[0]);
					$condition['rule'] = $field_condition[1];

					if (!empty($field_condition[2])) {
						if ($condition['rule'] == self::REGEX) {
							$field_condition[2] = self::prepare_regexp($field_condition[2]);
						} else if (is_scalar($field_condition[2])) {
							$field_condition[2] = [$field_condition[2]];
						}

						$condition['parameters'] = $field_condition[2];
					} else {
						$condition['parameters'] = [];
					}

					$row['condition']= $condition;
				}

				$row['parameters'] = $rule['parameters'];
				$field_name = self::convert_dots($file->field_name);
				$forms_rules[$field_name]['rules'][] = $row;
			}
		}

		return $forms_rules;
	}

	/**
	 * Convert string - first_level.types.value to first_level[types][value]
	 * this need, for frontend
	 *
	 * @param string $field_name
	 * @return string
	 */
	private static function convert_dots($field_name) {
		if (!is_string($field_name)) {
			throw new InvalidArgumentException('Field_name must be a string');
		}

		if (stripos($field_name, '.')) {
			$field_name = explode('.', $field_name);
			$first_element = array_shift($field_name);
			$last_element = array_pop($field_name);

			if (count($field_name) > 0) {
				$field_name = implode('][', $field_name) . '][';
			} else {
				$field_name = '';
			}

			$field_name = $first_element . '[' . $field_name . $last_element . ']';
		}

		return $field_name;
	}

	/**
	 * Prepare regexp expression for frontend
	 *
	 * @param string $regexp
	 * @return array
	 */
	private static function prepare_regexp($regexp) {
		if (!is_string($regexp)) {
			throw new InvalidArgumentException('RegExp must be a string');
		}

		$expression_start = $regexp{0}; // get the firs symbol of the rule, it is rule separator
		$flags = substr(strrchr($regexp, $expression_start), 1); // get flags from the rule, after the last separator

		if ($flags) {
			$expr = substr($regexp, 0, -strlen($flags)); // get expression without flags
			$expr = trim($expr, $expression_start); // trim separators from the expression
			$result = [$expr, $flags];
		} else {
			$result = [trim($regexp, $expression_start)];
		}

		return $result;
	}
}
