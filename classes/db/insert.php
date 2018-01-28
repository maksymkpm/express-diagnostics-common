<?php
/**
 * @copyright Dreamscapenetworks LLC
 * @link http://dreamscapenetworks.com
 *
 * Database abstraction layer
 */

namespace db;

use \db;

/**
 * Class for performing INSERT database requests
 */
class insert extends query {
	/**
	 * Values for insert
	 * @type array
	 */
	private $values = [];

	/**
	 * @type db::ON_DUPLICATE_*
	 */
	private $on_duplicate;

	/**
	 * Set table name for INSERT query
	 *
	 * @param string $table_name
	 *
	 * @return insert
	 */
	public function into($table_name = null) {
		if ($table_name) {
			$this->set_table($table_name);
		}

		return $this;
	}

	/**
	 * Set IGNORE keyword in query
	 *
	 * With the IGNORE keyword, the insert statement does not abort even if errors occur during the insert.
	 * Rows for which 'duplicate key' conflicts occur on a unique key value are not updated. Rows updated to values
	 * that would cause data conversion errors are updated to the closest valid values instead.
	 *
	 * @param string $on_duplicate
	 *        \database::ON_DUPLICATE_* constant
	 *
	 * @throws \InvalidArgumentException
	 *          - if $on_duplicate is invalid
	 *
	 * @return insert
	 */
	public function on_duplicate($on_duplicate = null) {
		if (isset($on_duplicate) && $on_duplicate != db::ON_DUPLICATE_IGNORE) {
					throw new \InvalidArgumentException('Parameter $on_duplicate must be one of database::ON_DUPLICATE_*');
		}

		if (isset($on_duplicate)) {
			$this->on_duplicate = $on_duplicate;
		}

		return $this;
	}

	/**
	 * Add one or more rows for insert into table.
	 * You can call this method several times, all values will be collected and inserted.
	 * Large amounts of data will be split/chunked into several queries and queries will be wrapped into a transaction.
	 * This means all values will be inserted or nothing will.
	 *
	 * @param array $rows
	 *      one row - array('name' => 'John', 'surname' => 'Brown', 'age' => 30)
	 *
	 *      bunch of rows - array(
	 *          array('name' => 'John', 'surname' => 'Brown', 'sex' => 'male'),
	 *          array('name' => 'Jane', 'surname' => 'White', 'sex' => 'female'),
	 *      )
	 *
	 * @throws \InvalidArgumentException
	 *          - passed more than one rows and one or more rows is not an array
	 *
	 * @return insert
	 */
	public function values(array $rows) {
		if (!empty($rows)) {
			if (!isset($rows[0])) {
				$rows = [$rows];
			}

			foreach ($rows as $row) {
				if (!is_array($row)) {
					throw new \InvalidArgumentException('Invalid row value. All insert data must be within an array.');
				} else {
					foreach($row as $key => $value) {
						if(empty($key)) {
							throw new \InvalidArgumentException("Empty key passed for value {$value}");
						}
					}
				}

				$this->values[] = $row;
			}
		}

		return $this;
	}

	/**
	 * Perform INSERT query.
	 * Large amounts of data will be split/chunked into several queries and queries will be wrapped into a transaction.
	 * This means all values will be inserted or nothing will.
	 *
	 * @param \database::ON_DUPLICATE_* $on_duplicate
	 *
	 * @return int - amount of inserted rows
	 *
	 * @throws \InvalidArgumentException
	 *      - table name is not set
	 *      - empty values list
	 *      - one or more column names contain wrong characters
	 *      - amount of data in a row is too large and cannot be processed with insert
	 *      - some rows do not have values for some columns
	 *
	 * @throws \Exception
	 */
	public function execute($on_duplicate = null) {
		if (empty($this->table)) {
			throw new \InvalidArgumentException('You are trying to perform an INSERT query with an empty table name. Use ->into($table_name) for setting table name.');
		}

		if (empty($this->values)) {
			throw new \InvalidArgumentException('Cannot perform INSERT query with an empty value list.');
		}

		$this->on_duplicate($on_duplicate);

		$ignore = ($this->on_duplicate == \db::ON_DUPLICATE_IGNORE) ? ' IGNORE' : ' ';
		$columns = array_keys(reset($this->values));

		// validate column names
		foreach ($columns as $column) {
			if (preg_match('/[^a-z0-9_-]/i', $column)) {
				throw new \InvalidArgumentException("Column name '{$column}' contains invalid characters.");
			}
		}

		$header_query = "INSERT{$ignore} INTO {$this->table} (`" . implode('`, `', $columns) . '`) VALUES' . PHP_EOL;

		// split values on parts by query size
		$allowed_length = ($this->database->get_max_query_length() - strlen($header_query)) * 0.95; // minus 5% for shadowing special chars during binding
		$parts = []; // parts of values for performing in separate queries
		$current_part = [];
		$part_length = 0;

		foreach ($this->values as $row) {
			$row_length = strlen("('" . implode("', '", $row) . "')," . PHP_EOL);

			if ($row_length > $allowed_length) {
				// row contains data which is too large
				// this is a runtime error (not development time error) and we should process it like a database error
				$error = 'Too much data to insert in one row. Attempted to insert ' . round($row_length / 1024, 0) . ' Kb in one row when a maximum of ' . round($allowed_length / 1024, 0) . ' Kb is allowed.';
				$this->database->error($error, $header_query, $row); // throws DatabaseException
			}

			$part_length += $row_length;

			if ($part_length < $allowed_length) {
				// continue filling current part
				$current_part[] = $row;
			} else {
				// begin new part
				$parts[] = $current_part;
				$current_part = [$row];
				$part_length = $row_length;
			}
		}

		$parts[] = $current_part; // tail

		if (count($parts) == 1) {
			$total_insert = $this->partial_insert($header_query, $parts[0], $columns);
			$this->clear(); // free memory from values

			return $total_insert;
		}

		try {
			$total_insert = 0;

			// begin transaction
			$this->database->begin();

			foreach ($parts as $part) {
				$total_insert += $this->partial_insert($header_query, $part, $columns);
			}

			// commit transaction
			$this->database->commit();
			$this->clear(); // free memory from values

			return $total_insert;
		} catch (\Exception $exception) {
			// just catch
		}

		// something went wrong
		$this->database->rollback();

		// we should bubble up original exception, because user is waiting for an exception if something goes wrong
		throw $exception;
	}

	/**
	 * Perform INSERT query with chunked values
	 *
	 * @param string $header_query
	 * @param array $values
	 * @param array $columns
	 *
	 * @return int - amount of inserted rows
	 *
	 * @throws \InvalidArgumentException
	 *              - One of the rows does not contain value for a column
	 *
	 * @throws \DatabaseException
	 */
	private function partial_insert($header_query, $values, $columns) {
		$binds = [];
		$lines = [];

		foreach ($values as $i => $row) {
			$placeholders = [];

			foreach ($columns as $column) {
				if (!array_key_exists($column, $row)) {
					throw new \InvalidArgumentException("One of the rows does not contain value for the column '{$column}'.");
				}

				if (is_null($row[$column])) {
					$row[$column] = db::expression('NULL');
					$error_message = "DB WARNING: missed value for the column '{$column}'. If you sure to insert NULL, please use db::expression('NULL').";
					trigger_error($error_message, E_USER_WARNING);
				}

				$value = $row[$column];

				if (in_array(trim($value), $this->mysql_functions) || $value instanceof expression) {
					$placeholders[] = (string)$value;
					$binds += $this->binds;
				} else {
					$name = ":{$column}_{$i}";
					$binds[$name] = $value;
					$placeholders[] = $name;
				}
			}

			$lines[] = '(' . implode(', ', $placeholders) . '),';
		}

		$query = $header_query . implode(PHP_EOL, $lines);
		$query = rtrim($query, ',');
		$statement = $this->database->query($query, $binds);

		return $statement->rowCount();
	}

	/**
	 * Clear object for reuse. After you can set other values and perform insert.
	 */
	protected function clear() {
		parent::clear();

		$this->values = [];
		$this->on_duplicate = null;
	}
}
