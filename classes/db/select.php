<?php
/**
 * @copyright Dreamscapenetworks LLC
 * @link http://dreamscapenetworks.com
 *
 * Database abstraction layer
 */

namespace db;

/**
 * Class for performing SELECT database requests
 */
class select extends query {
	/**
	 * Database response
	 * @type \PDOStatement
	 */
	private $statement;

	/**
	 * Text of query
	 * @type string
	 */
	private $query = '';

	/**
	 * Use the unbuffered connection for the select
	 * @var bool
	 */
	private $unbuffered;

	/**
	 * Set query string and reset object if execute() has already been called
	 *
	 * @param string $query
	 *
	 * @throws \InvalidArgumentException
	 *          - if query is not a string
	 *
	 * @return select
	 */
	public function query($query = null) {
		if (isset($query)) {
			if (!is_string($query)) {
				throw new \InvalidArgumentException('Invalid query value. Queries must be strings.');
			}

			$this->query = $query;

			if ($this->statement) {
				// query has been executed and we need to reset the object
				$this->reset();
			}
		}

		return $this;
	}

	/**
	 * Makes the SELECT query unbuffered.
	 * Works for this query only (not affecting the entire connection)
	 *
	 * @return select
	 */
	public function unbuffered() {
		if (!isset($this->unbuffered_database)) {
			$this->unbuffered_database = \db::connect($this->database->get_configuration_name(), $this->database->is_debug_enabled(), $this->database->is_bug_tracker_enabled(), true);
			$this->unbuffered = true;
		}

		return $this;
	}

	/**
	 * Returns query string.
	 * If request already executed, PDOStatement::$queryString will be returned,
	 * otherwise the query text will be returned
	 *
	 * @return string
	 */
	public function get_query() {
		if ($this->statement) {
			return $this->statement->queryString;
		} else {
			return $this->query;
		}
	}

	/**
	 * Fetch next row from result set. After fetching the last row, the object is reset for reuse.
	 *
	 * @return array|null
	 *      next row or NULL if no rows left
	 */
	public function fetch() {
		if (is_null($this->statement)) {
			throw new \BadMethodCallException('The execute() method must be called before fetch()');
		}

		$result = $this->statement->fetch(\PDO::FETCH_ASSOC);

		// if all results have been fetched, reset object for reuse
		if (!$result) {
			$this->reset();

			return null;
		}

		return $result;
	}

	/**
	 * Fetch all rows from result set as numeric array or with column as key and reset select object for reuse.
	 * If $column_as_key is set, values from this column will be set as keys in the result array.
	 * If no rows were found, empty array will be returned.
	 *
	 * if $column_as_key === null, the result will be:
	 *      array(
	 *          0 => array('id' => 123, 'name' => 'John'),
	 *          1 => array('id' => 321, 'name' => 'Jane'),
	 *      )
	 *
	 * if $column_as_key == 'id', the result will be:
	 *      array(
	 *          '123' => array('id' => 123, 'name' => 'John'),
	 *          '321' => array('id' => 321, 'name' => 'Jane'),
	 *      )
	 *
	 * NOTE: $column_as_key must be a column with index PRIMARY or UNIQUE
	 *
	 * @param string $column_as_key
	 *      Column with unique values for using as keys for resulting array, else array will be with numeric keys
	 *
	 * @throws \InvalidArgumentException
	 *            - if column doesn't exist in result set
	 * 			  - if the column name is not a string
	 *
	 * @return array
	 */
	public function fetch_all($column_as_key = null) {
		if (is_null($this->statement)) {
			throw new \BadMethodCallException('The execute() method must be called before fetch_all()');
		}

		if (!empty($column_as_key) && !is_string($column_as_key)) {
			throw new \InvalidArgumentException('Column name must be a string.');
		}

		$result = $this->statement->fetchAll(\PDO::FETCH_ASSOC);

		if (is_null($column_as_key)) {
			$this->reset();

			return $result;
		}

		if (!empty($result) && !isset($result[0][$column_as_key])) {
			throw new \InvalidArgumentException("Column '{$column_as_key}' does not exist in result set.");
		}

		$keyed_result = [];

		foreach ($result as $row) {
			$keyed_result[$row[$column_as_key]] = $row;
		}

		// reset object for reuse
		$this->reset();

		return $keyed_result;
	}

	/**
	 * Fetch values of the one column from the query result and reset select object for reuse.
	 * If no rows were found, empty array will be returned.
	 *
	 * @param string $column_name
	 * @param string $key_column_name (optional)
	 *
	 * @throws \InvalidArgumentException
	 *            - if column is not exists in result set
	 * 			  - if the column name is not a string
	 *
	 * @return array
	 *        One layer array with values from specific column
	 */
	public function fetch_column_values($column_name, $key_column_name = null) {
		if (is_null($this->statement)) {
			throw new \BadMethodCallException('The execute() method must be called before fetch_column_values()');
		}

		if (empty($column_name) || !is_string($column_name)) {
			throw new \InvalidArgumentException('Column name must be a string.');
		}

		$result = $this->statement->fetchAll(\PDO::FETCH_ASSOC);

		if (!empty($result) && !array_key_exists($column_name, $result[0])) {
			throw new \InvalidArgumentException("Column '{$column_name}' does not exist in result set.");
		}

		$result_column = [];

		if (is_null($key_column_name)) {
			foreach ($result as $row) {
				$result_column[] = $row[$column_name];
			}
		} else {
			if (!is_string($key_column_name)) {
				throw new \InvalidArgumentException('Column key must be a string.');
			}

			if (!empty($result) && !isset($result[0][$key_column_name])) {
				throw new \InvalidArgumentException("Column '{$key_column_name}' does not exist in result set.");
			}

			foreach ($result as $row) {
				$result_column[$row[$key_column_name]] = $row[$column_name];
			}
		}

		// reset object for reuse
		$this->reset();

		return $result_column;
	}

	/**
	 * Get scalar value from query result, or NULL if no result returned by the query
	 *
	 * @param string $column
	 *      The column which must be returned, or if NULL passed, returns the value of the first (left) column in the column list
	 *
	 * @throws \InvalidArgumentException
	 *          - if passed column is not found in the result row
	 * 			- if the column name is not a string
	 *
	 * @return string|null
	 */
	public function fetch_value($column = null) {
		if (is_null($this->statement)) {
			throw new \BadMethodCallException('The execute() method must be called before fetch_value()');
		}

		if (!is_null($column) && !is_string($column)) {
			throw new \InvalidArgumentException('Column name must be a string.');
		}

		$row = $this->fetch();

		// clear statement and object can be reused
		$this->reset();

		if (empty($row)) {
			return null;
		}

		if (is_null($column)) {
			return reset($row);
		}

		if (array_key_exists($column, $row)) {
			return $row[$column];
		}

		throw new \InvalidArgumentException("The column with name '{$column}' does not exist in resulted row.");
	}

	/**
	 * Return amount of rows in the last result set.
	 * Use this method before calling fetch, because fetch methods reset the object.
	 *
	 * @return int
	 */
	public function row_count() {
		if (!$this->statement) {
			return null; // query is not executed yet
		}

		return $this->statement->rowCount();
	}

	/**
	 * Performs the query and returns itself.
	 *
	 * @return select
	 *
	 * @throws \BadFunctionCallException
	 *          - call execute twice without reset object
	 *
	 * @throws \InvalidArgumentException
	 *          - query string is empty or not set
	 */
	public function execute() {
		if ($this->statement) {
			throw new \BadFunctionCallException('Query already called. Use methods "fetch", "fetch_column_values", "fetch_value" or "fetch_all" to retrieve data from result set');
		}

		if (empty($this->query)) {
			throw new \InvalidArgumentException('Query string is not set. Use method "query" for setting query string.');
		}

		// process binding of arrays
		if (!empty($this->bind_arrays)) {
			foreach ($this->bind_arrays as $placeholder => $values) {
				$this->query = $this->inject_array($this->query, $placeholder, $values);
			}
		}

		if ($this->unbuffered) {
			$this->statement = $this->unbuffered_database->query($this->query, $this->binds);
		} else {
			$this->statement = $this->database->query($this->query, $this->binds);
		}

		$this->clear(); // release memory from binds

		return $this;
	}

	/**
	 * Reset query for reuse with different bind data.
	 * After fetching all rows, the methods is called automatically.
	 */
	public function reset() {
		$this->clear();
		$this->statement = null;
		$this->unbuffered = false;
	}
}
