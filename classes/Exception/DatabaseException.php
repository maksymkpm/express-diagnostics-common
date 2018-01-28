<?php
/**
 * @copyright Dreamscapenetworks LLC
 * @link http://dreamscapenetworks.com
 *
 * Database abstraction layer
 */

/**
 * Thrown in case of database connection failure or executing query failure
 */
class DatabaseException extends Exception {
	/**
	 * Failed query string (or empty if error with connection or transaction)
	 * @type string
	 */
	private $query;

	/**
	 * Data bound in the query
	 * @type array
	 */
	private $binds;

	/**
	 * Hostname of the database server
	 * @var string
	 */
	private $host;

	/**
	 * Name of the connection properties in the configuration system
	 * @var string
	 */
	private $configuration_name;

	/**
	 * PDO connection attributes
	 * @var array
	 */
	private $pdo_options;

	#region PDO OPTIONS reference
	private $pdo_options_reference = [
		PDO::ATTR_AUTOCOMMIT => 'PDO::ATTR_AUTOCOMMIT',
		PDO::ATTR_CASE => 'PDO::ATTR_CASE',
		PDO::ATTR_ERRMODE => 'PDO::ATTR_ERRMODE',
		PDO::ATTR_ORACLE_NULLS => 'PDO::ATTR_ORACLE_NULLS',
		PDO::ATTR_PERSISTENT => 'PDO::ATTR_PERSISTENT',
		PDO::ATTR_PREFETCH => 'PDO::ATTR_PREFETCH',
		PDO::ATTR_TIMEOUT => 'PDO::ATTR_TIMEOUT',
		PDO::ATTR_DEFAULT_FETCH_MODE => 'PDO::ATTR_DEFAULT_FETCH_MODE',
	];

	private $pdo_options_values_reference = [
		PDO::ATTR_ERRMODE => [
			PDO::ERRMODE_SILENT => 'PDO::ERRMODE_SILENT',
			PDO::ERRMODE_WARNING => 'PDO::ERRMODE_WARNING',
			PDO::ERRMODE_EXCEPTION => 'PDO::ERRMODE_EXCEPTION',
		],
		PDO::ATTR_DEFAULT_FETCH_MODE => [
			PDO::FETCH_ASSOC => 'PDO::FETCH_ASSOC',
			PDO::FETCH_BOTH => 'PDO::FETCH_BOTH',
			PDO::FETCH_BOUND => 'PDO::FETCH_BOUND',
			PDO::FETCH_CLASS => 'PDO::FETCH_CLASS',
			PDO::FETCH_INTO => 'PDO::FETCH_INTO',
			PDO::FETCH_LAZY => 'PDO::FETCH_LAZY',
			PDO::FETCH_NAMED => 'PDO::FETCH_NAMED',
			PDO::FETCH_NUM => 'PDO::FETCH_NUM',
			PDO::FETCH_OBJ => 'PDO::FETCH_OBJ',
		],
		PDO::ATTR_CASE => [
			PDO::CASE_NATURAL => 'PDO::CASE_NATURAL',
			PDO::CASE_LOWER => 'PDO::CASE_LOWER',
			PDO::CASE_UPPER => 'PDO::CASE_UPPER',
		],
		PDO::ATTR_AUTOCOMMIT => [
			true => 'true',
			false => 'false',
		],
		PDO::ATTR_ORACLE_NULLS => [
			true => 'true',
			false => 'false',
		],
		PDO::ATTR_PERSISTENT => [
			true => 'true',
			false => 'false',
		],
	];

	#endregion

	// These constants are used to truncate long INSERT and REPLACE queries for error reporting
	const INSERT_REPLACE_QUERY_MAX_LENGTH = 2048;
	const INSERT_REPLACE_BINDS_MAX_COUNT = 5;

	/**
	 * @param string $host
	 *      Database host name
	 * @param string $configuration_name
	 *      Database connection configuration name
	 * @param string $message
	 *      Text of error
	 * @param string $query
	 *      Text of query which threw the exception
	 * @param array $binds
	 *      Bound data from the query
	 * @param array $pdo_options
	 * 		PDO attributes
	 */
	public function __construct($host, $configuration_name, $message, $query = '', array $binds = null, array $pdo_options = []) {
		$message = "Host: '{$host}'; Connection name: '{$configuration_name}'; Error: {$message}";

		parent::__construct($message);

		$this->host = $host;
		$this->configuration_name = $configuration_name;

		if ((stripos(ltrim($query), 'insert') === 0) || (stripos(ltrim($query), 'replace') === 0)) {
			$query_length = strlen($query);

			if ($query_length > self::INSERT_REPLACE_QUERY_MAX_LENGTH) {
				$query = substr($query, 0, self::INSERT_REPLACE_QUERY_MAX_LENGTH) . "... (query length {$query_length} bytes)";
			}

			if (count($binds) > self::INSERT_REPLACE_BINDS_MAX_COUNT) {
				$binds = array_slice($binds, 0, self::INSERT_REPLACE_BINDS_MAX_COUNT);
			}
		}

		$this->query = $query;
		$this->binds = $binds;
		$this->pdo_options = $pdo_options;
	}

	/**
	 * Database host producing the error
	 * @return string
	 */
	public function getDatabaseHost() {
		return $this->host;
	}

	/**
	 * Name of the database configuration properties in the configuration system
	 * @return string
	 */
	public function getConfigurationName() {
		return $this->configuration_name;
	}

	/**
	 * Query text which produced the error (or empty if error occurred during connection or transaction failure events)
	 * @return string
	 */
	public function getQuery() {
		$length = strlen($this->query);

		// check if the query length is greater than 2k and truncate it if so
		if ($length > 2048) {
			return substr($this->query, 0, 2048) . "... (query length: {$length} bytes)";
		}

		return $this->query;
	}

	/**
	 * Data bound to the query
	 * @return array|null
	 */
	public function getBinds() {
		return $this->binds;
	}

	/**
	 * Get all set PDO connection attributes
	 * @return array
	 */
	public function getPDOOptions() {
		$result = [];

		foreach ($this->pdo_options as $option => $value) {
			if (isset($this->pdo_options_reference[$option])) {
				if (isset($this->pdo_options_values_reference[$option][$value])) {
					$result[$this->pdo_options_reference[$option]] = $this->pdo_options_values_reference[$option][$value];
				} else {
					$result[$this->pdo_options_reference[$option]] = (string)$value;
				}
			} else {
				$result[(string)$option] = (string)$value;
			}
		}

		return $result;
	}
}
