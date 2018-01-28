<?php
/**
 * @copyright Dreamscapenetworks LLC
 * @link http://dreamscapenetworks.com
 *
 * Database abstraction layer
 */

namespace db;

/**
 * Class for performing UPDATE database requests
 */
class update extends query {
	/**
	 * New values
	 * @type array
	 */
	private $values = [];

	/**
	 * Set table for performing the query
	 *
	 * @param string $table_name
	 *
	 * @return update
	 */
	public function table($table_name = null) {
		if (!is_null($table_name)) {
			$this->set_table($table_name);
		}

		return $this;
	}

	/**
	 * Set WHERE statement for UPDATE query
	 *
	 * @param string $conditions
	 *
	 * @return update
	 */
	public function where($conditions = null) {
		if ($conditions) {
			$this->set_where($conditions);
		}

		return $this;
	}

	/**
	 * Set value for UPDATE query
	 *
	 * @param string $parameter
	 * @param string|int|float $value
	 *
	 * @throws \InvalidArgumentException
	 *          - value is not scalar
	 *          - parameter name contains invalid characters or is empty
	 *
	 * @return update
	 */
	public function value($parameter, $value) {
		if (is_null($value)) {
			$error_message = "DB WARNING: missed value for the column '{$parameter}'. If you sure to insert NULL, please use db::expression('NULL').";
			trigger_error($error_message, E_USER_WARNING);

			$value = \db::expression('NULL');
		}

		if (!is_scalar($value) && !$value instanceof expression) {
			throw new \InvalidArgumentException("Invalid parameter '{$parameter}'. Only scalar values or expression object can be bound.");
		}

		$parameter = (string)$parameter;

		if (empty($parameter) || preg_match('/[^a-z0-9_-]/i', $parameter)) {
			throw new \InvalidArgumentException("Parameter name '{$parameter}' contains invalid characters or is empty.");
		}

		$this->values[$parameter] = $value;

		return $this;
	}

	/**
	 * Add one or more parameters for updating.
	 *
	 * @param array $values
	 *      array('name' => 'John', 'surname' => 'Brown', 'age' => 30)
	 *
	 * @throws \InvalidArgumentException
	 *
	 * @return update
	 */
	public function values(array $values) {
		if (!empty($values)) {
			if (isset($values[0])) {
				throw new \InvalidArgumentException('Invalid values array passed. Values array must have key-value pairs.');
			}

			foreach ($values as $parameter => $value) {
				if (!empty($parameter)) {
					$this->value($parameter, $value);
				} else {
					throw new \InvalidArgumentException('Invalid values array passed. Values array cannot have empty keys');
				}
			}
		}

		return $this;
	}

	/**
	 * Execute UPDATE query
	 *
	 * @return int - amount of updated rows
	 *
	 * @throws \InvalidArgumentException
	 *          - table name is not set
	 *          - value list is empty
	 *          - if there is the same parameter name in both of values and binds
	 *
	 * @throws \DatabaseException
	 */
	public function execute() {
		if (empty($this->table)) {
			throw new \InvalidArgumentException('You are trying to perform an UPDATE query with an empty table name. Use ->table($table_name) for setting table name.');
		}

		if (empty($this->values)) {
			throw new \InvalidArgumentException('Cannot perform UPDATE query with an empty value list.');
		}

		$query = "UPDATE {$this->table} SET" . PHP_EOL;

		$values = [];

		foreach ($this->values as $name => $value) {
			$auto_generated_name = $name . "_auto_generated"; // this is added to distinguish the auto generated placeholders from the manually added ones

			if (isset($this->binds[':' . $auto_generated_name])) {
				throw new \InvalidArgumentException("The same placeholder '{$name}' exists in both 'values' and 'binds'.");
			}

			if (in_array(trim($value), $this->mysql_functions) || $value instanceof expression) {
				$values[] = "`{$name}` = {$value}" . PHP_EOL;
			} else {
				$values[] = "`{$name}` = :{$auto_generated_name}" . PHP_EOL;
				$this->binds[':' . $auto_generated_name] = $value;
			}
		}

		$query .= join(",", $values);

		if (!empty($this->where)) {
			// process binding of arrays
			if (!empty($this->bind_arrays)) {
				foreach ($this->bind_arrays as $placeholder => $values) {
					$this->where = $this->inject_array($this->where, $placeholder, $values);
				}
			}

			$query .= $this->where;
		} else {
			$query = trim($query); // remove last EOL
		}

		$statement = $this->database->query($query, $this->binds);

		$this->clear(); // free memory from values and binds

		return $statement->rowCount();
	}

	/**
	 * Clear object for reuse. You can set other values and bindings and perform query again.
	 */
	protected function clear() {
		parent::clear();

		$this->values = [];
	}
}
