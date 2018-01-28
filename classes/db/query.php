<?php
/**
 * @copyright Dreamscapenetworks LLC
 * @link http://dreamscapenetworks.com
 *
 * Database abstraction layer
 */

namespace db;

/**
 * Base abstract class for all database query classes
 */
abstract class query {
	const QUERY_SELECT = 'select';
	const QUERY_DELETE = 'delete';
	const QUERY_INSERT = 'insert';
	const QUERY_UPDATE = 'update';
	const QUERY_REPLACE = 'replace';
	/**
	 * Table name
	 * @type string
	 */
	protected $table;
	/**
	 * WHERE statement
	 * @type string
	 */
	protected $where = '';
	/**
	 * Binds data
	 * @type array
	 */
	protected $binds = [];
	/**
	 * Binds for array binding
	 * @type array
	 */
	protected $bind_arrays = [];
	/**
	 * Not bound values
	 * @var array
	 */
	protected $mysql_functions = [
		'NULL',
		'NOW()',
		'UTC_TIMESTAMP()',
		'CURDATE()',
	];
	/**
	 * Instance of database connection
	 * @type \db
	 */
	protected $database;
	/**
	 * Instance of unbuffered database
	 * @type \db
	 */
	protected $unbuffered_database;

	/**
	 * Protecting against direct creating the object
	 *
	 * @param \db $database
	 */
	private function __construct(\db $database) {
		$this->database = $database;
	}

	private function __sleep() {} // serialize($instance); - Warning: Invalid callback test::__sleep, cannot access private method test::__sleep()

	private function __wakeup() {} // unserialize($ser); - Warning: Invalid callback test::__wakeup, cannot access private method test::__wakeup()

	private function __clone() {} // prevent clone the object

	/**
	 * Create instance of database query
	 *
	 * @param string $query_type
	 *        query::QUERY_* constant
	 * @param \db $database
	 *        Instance of the database connection class
	 *
	 * @throws \InvalidArgumentException
	 *      - $query_type is invalid
	 *      - $database is not an instance if database object
	 *
	 * @return select|insert|delete|replace|update
	 */
	public static function create($query_type, \db $database) {
		if (!is_string($query_type)) {
			throw new \InvalidArgumentException('Parameter "query_type" must be one of the constants \db\query::QUERY_*');
		}

		// switch is quicker on 38-45% than if (!in_array()) {}
		switch ($query_type) {
			case self::QUERY_DELETE:
			case self::QUERY_INSERT:
			case self::QUERY_REPLACE:
			case self::QUERY_SELECT:
			case self::QUERY_UPDATE:
				break;
			default:
				throw new \InvalidArgumentException('Parameter "query_type" must be one of the constants \db\query::QUERY_*');
		}

		$query_type = __NAMESPACE__ . '\\' . $query_type;
		$query_object = new $query_type($database);

		return $query_object;
	}

	/**
	 * Add one binding parameter
	 *
	 * @param string $parameter
	 *        Name of the parameter, leading ":" is not required
	 * @param string|int|float|\DateTime $value
	 *        Value of the parameter
	 *
	 * @return select|insert|delete|replace|update
	 *
	 * @throws \InvalidArgumentException
	 *      - value is not scalar or DateTime object
	 *      - if parameter with the same name is already bound
	 */
	protected function bind($parameter, $value) {
		$parameter = (string) $parameter;

		if (!empty($parameter)) {
			if ($parameter[0] !== ':') {
				$parameter = ':' . $parameter;
			}
		} else {
			throw new \InvalidArgumentException('Empty string passed as parameter');
		}

		if (!is_scalar($value)) {
			if (is_array($value)) {
				throw new \InvalidArgumentException('You try bind array as parameter "' . $parameter . '", if you sure to bind a list of values, use method bind_array($parameter, $array)');
			} else if ($value instanceof \DateTime) {
				$value = $value->format(\db::FORMAT_DATETIME);
			} else {
				throw new \InvalidArgumentException("Trying to bind not scalar nor DateTime as parameter '{$parameter}'");
			}
		}

		if (isset($this->binds[$parameter])) {
			throw new \InvalidArgumentException("Parameter '{$parameter}' has been already bound.");
		}

		$this->binds[$parameter] = $value;

		return $this;
	}

	/**
	 * Add one binding parameter, if two parameters provided, or
	 * add list of binding parameters, as pairs key -> value
	 *
	 * @param string|array $bind
	 *        - if string - Name of the parameter, leading ":" is not required
	 * 		  - if array - array of binding parameters in key => value format
	 * @param string|int|float|\DateTime $value
	 *        - value of the parameter if the $bind is string
	 *
	 * @return select|insert|delete|replace|update
	 *
	 * @throws \InvalidArgumentException
	 *      - value is not scalar or DateTime object
	 *      - if parameter with the same name is already bound
	 *      - if value of one of the parameters is not scalar or DateTime object
	 */
	public function binds($bind, $value = null) {
		if (!is_null($value)) {
			if (!is_scalar($bind)) {
				throw new \InvalidArgumentException('Parameter bind must be a string if value is not null');
			} else {
				$bind = [(string)$bind => $value];
			}
		}

		if (!is_array($bind)) {
			throw new \InvalidArgumentException("If single parameter passed it must be an array('parameter' => 'value')");
		}

		foreach ($bind as $parameter => $value) {
			$this->bind($parameter, $value);
		}

		return $this;
	}

	/**
	 * Bind array as parameter, for example for IN (:array) statement.
	 *
	 * HOW TO USE:
	 * in query: ->where('name IN (:names)')
	 * binding array with data: ->bind_array('names', array('John', 'Stephen', 'Michael'));
	 *
	 * will replace "IN (:names)" to "IN (:names_1, :names_2, :names_3)"
	 * and add binds ->bindParam(':names_1', 'John'), ->bindParam(':names_2', 'Stephen'), ->bindParam(':names_3', 'Michael')
	 *
	 * @param string $parameter
	 *        Placeholder in query, the leading ":" is not required
	 * @param array $values
	 *        A list of values
	 *
	 * @return select|insert|delete|replace|update
	 *
	 * @throws \InvalidArgumentException
	 *      - if parameter with the same name is already bound
	 */
		public function bind_array($parameter, array $values) {
		$parameter = (string) $parameter;

		if ($parameter[0] !== ':') {
			$parameter = ':' . $parameter;
		}

		if (isset($this->bind_arrays[$parameter])) {
			throw new \InvalidArgumentException("Parameter '{$parameter}' has been already bound with bind_array.");
		}

		$this->bind_arrays[$parameter] = array_values($values);

		return $this;
	}

	/**
	 * Internal method for sanitize table name
	 *
	 * @param string $table_name
	 */
	protected function set_table($table_name) {
		if (!empty($table_name) && is_string($table_name) && !preg_match('/[^a-z0-9_.`]/i', $table_name)) {
			if ($table_name[0] != '`') {
				$table_name = '`' . str_replace('.', '`.`', $table_name) . '`';
			}

			$this->table = $table_name;

			return;
		}

		throw new \InvalidArgumentException('Invalid table name passed');
	}

	/**
	 * Internal method for safety set WHERE condition in query string
	 *
	 * @param string $conditions
	 */
	protected function set_where($conditions) {
		$conditions = trim($conditions);

		if (empty($conditions)) {
			return;
		}

		if (stripos($conditions, 'where ') !== 0) {
			$conditions = 'WHERE ' . $conditions;
		}

		$this->where = $conditions;
	}

	/**
	 * Inject array of values in query
	 *
	 * @param $query
	 * @param $placeholder
	 * @param array $values
	 *
	 * @return string
	 *
	 * @throws \InvalidArgumentException
	 *      - if placeholder for array of values is not set in query
	 *      - placeholder already exists in bind array
	 */
	protected function inject_array($query, $placeholder, array $values) {
		if (strpos($query, $placeholder) === false) {
			throw new \InvalidArgumentException("Error injecting array for binding. Placeholder '{$placeholder}' was not found in the query.");
		}

		$placeholders = [];

		foreach ($values as $key => $value) {
			$parameter = "{$placeholder}_{$key}";

			$this->bind($parameter, $value);
			$placeholders[] = $parameter;
		}

		$query = preg_replace('/' . $placeholder . '\b/', implode(', ', $placeholders), $query);

		return $query;
	}

	/**
	 * Execute the query
	 *
	 * @return mixed
	 *
	 * @throws \DatabaseException
	 */
	abstract public function execute();

	/**
	 * Clear object after executing query.
	 * It allows to prevent execute the same query twice and also to reuse this object for other query.
	 */
	protected function clear() {
		$this->table = null;
		$this->where = '';
		$this->binds = [];
		$this->bind_arrays = [];
	}
}
