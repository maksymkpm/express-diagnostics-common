<?php
/**
 * @copyright Dreamscapenetworks LLC
 * @link http://dreamscapenetworks.com
 *
 * Database abstraction layer
 */

namespace db;

/**
 * Class for performing DELETE database requests
 */
class delete extends query {
	/**
	 * LIMIT statement in query
	 * @type string
	 */
	protected $limit = '';

	/**
	 * Set table name for performing delete query
	 *
	 * @param string $table_name
	 *
	 * @return delete
	 */
	public function from($table_name = null) {
		if ($table_name) {
			$this->set_table($table_name);
		}

		return $this;
	}

	/**
	 * Set WHERE statement for DELETE query. Keyword "WHERE" is not required.
	 *
	 * @param string $conditions
	 *
	 * @return delete
	 */
	public function where($conditions = null) {
		if ($conditions) {
			$this->set_where($conditions);
		}

		return $this;
	}

	/**
	 * Set value for LIMIT statement in DELETE query
	 *
	 * @param int $limit
	 *      Must be greater than zero
	 *
	 * @throws \InvalidArgumentException
	 *      - invalid value for LIMIT, must be greater than zero
	 *
	 * @return delete
	 */
	public function limit($limit) {
		$value = (int)$limit;

		if ($value > 0) {
			$this->limit = 'LIMIT ' . $value;
		} else {
			throw new \InvalidArgumentException("Invalid value for LIMIT: '{$limit}'");
		}

		return $this;
	}

	/**
	 * Perform DELETE query and return amount of deleted rows.
	 *
	 * @return int - amount of deleted rows
	 *
	 * @throws \InvalidArgumentException
	 *      - table name is not set
	 *      - WHERE statement is not set
	 *      - data bind conflict occurred
	 *
	 * @throws \DatabaseException
	 */
	public function execute() {
		// we do not throw DatabaseException, because it is a development time error and we should not catch or log this error
		if (empty($this->where)) {
			throw new \InvalidArgumentException('You are trying to perform a DELETE query without WHERE conditions. If you want to delete all rows in the table, use db::truncate($table), but be aware that the db::truncate() will reset the table\'s autoincrement fields, while the db::delete() - will not');
		}

		if (empty($this->table)) {
			throw new \InvalidArgumentException('You are trying to perform a DELETE query with an empty table name. Use ->from($table_name) for setting table name.');
		}

		// process binding of arrays
		if (!empty($this->bind_arrays)) {
			foreach ($this->bind_arrays as $placeholder => $values) {
				$this->where = $this->inject_array($this->where, $placeholder, $values);
			}
		}

		$query = "DELETE FROM {$this->table}" . PHP_EOL . "{$this->where}" . PHP_EOL . "{$this->limit}";

		$statement = $this->database->query($query, $this->binds);

		$this->clear(); // release memory from binds

		return $statement->rowCount();
	}

	/**
	 * Clear object for reuse
	 */
	protected function clear() {
		parent::clear();

		$this->limit = '';
	}
}
