<?php
/**
 * @copyright Dreamscapenetworks LLC
 * @link http://dreamscapenetworks.com
 *
 * Database abstraction layer
 */
require_once('Exception/DatabaseException.php');
require_once('db/query.php');
require_once('db/select.php');
require_once('db/insert.php');
require_once('db/delete.php');
require_once('db/replace.php');
require_once('db/update.php');
require_once('db/expression.php');

use \db\query as query;
use \db\delete as delete;
use \db\insert as insert;
use \db\update as update;
use \db\replace as replace;
use \db\select as select;

/**
 * Establish a connection to a database and perform requests
 *
 * HOW TO USE:
 * @link http://wiki.secureapi.com.au/index.php?title=DB_Class
 *
 * // SELECT
 * $result = db::connect('syssql')
 * 		->select($query)
 * 		->execute();
 *
 * $count = $result->row_count();
 *
 * while($row = $result->fetch()) {}
 *
 * $rows = $result->fetch_all();
 * $rows = $result->fetch_all('id');
 * $column = $result->fetch_column_values('name');
 * $value = $result->fetch_value();
 *
 *
 * // DELETE
 * $deleted_amount = db::connect('syssql')
 * 		->delete('member')
 * 		->where('id = :id')
 * 		->binds('id', $_GET['id'])
 * 		->limit(1)
 * 		->execute();
 *
 * // INSERT
 * $inserted_amount = db::connect('syssql')
 * 		->insert('order')
 * 		->values($order_data)
 * 		->execute();
 *
 * // UPDATE
 * $updated_amount = db::connect('syssql')
 * 		->update('member')
 * 		->value('name', $_GET['new_name'])
 * 		->where('id = :id')
 * 		->binds('id', $_GET['member_id'])
 * 		->execute();
 *
 * CONNECTION CONFIGURATION:
 * 		hostname - required
 * 		username - required
 * 		password - required
 *		schema - required
 * 		port - not required, default 3306
 * 		max_query_length - not required, default MAX_QUERY_LENGTH_MB
 * 		fallback - not required, can be alternative hostname or list of alternative connection settings as well
 * 		pdo_options - not required
 */
class db {
	/**
	 * Default maximum length of query in Mb, needed for splitting insert queries
	 */
	const MAX_QUERY_LENGTH_MB = 5;
	const ON_DUPLICATE_IGNORE = 'ignore';

	/**
	 * Date and time formatting rules for quick usage
	 */
	const FORMAT_DATE = 'Y-m-d';
    const FORMAT_TIME = 'H:i:s';
    const FORMAT_DATETIME = 'Y-m-d H:i:s';

	/**
	 * Supported query types. Not listed here are recognized as 'OTHER'
	 */
	public static $query_types = [
		'SELECT',
		'INSERT',
		'UPDATE',
		'DELETE',
		'REPLACE',
	];

	/**
	 * Cache of connections
	 * @type self[][]
	 */
	static private $connections = [];

	/**
	 * Instance of PDO class
	 * @var PDO
	 */
	private $adapter;

	/**
	 * Connection settings
	 */
	private $configuration_name;
	private $hostname;
	private $username;
	private $password;
	private $schema;
	private $port;
	private $max_query_length;

	/**
	 * Debug mode
	 * @var bool
	 */
	private $debug_enabled = false;

	/**
	 * Debugger log
	 * @var array
	 */
	private $debug_log = [];

	/**
	 * Send error reports to bug tracker
	 */
	private $bug_tracker_enabled = true;

	/**
	 * Allows pseudo-embedded transactions
	 * @type int
	 */
	private $transaction_counter = 0;

	/**
	 * Save all set PDO attributes
	 * @var array
	 */
	private $pdo_options;

	/**
	 * Return instance of database class for provided configuration.
	 *
	 * @param string $database
	 *      The name of configuration for database connection from configuration group "database".
	 * @param bool $debug_enabled
	 *      Flag of debug mode. In debug mode class measures the time for performing queries @see self::get_debug_info
	 *      also saves backtrace for operation with transactions @see self::get_transaction_backtrace()
	 *
	 * @param bool $bug_tracking_enabled
	 * 		If FALSE we will not send message to the bug tracker
	 *
	 * @param bool $unbuffered - use unbuffered selects
	 *
	 * @throws InvalidArgumentException - invalid configuration or method parameters
	 * @throws DatabaseException - connection error
	 *
	 * @return db
	 */
	public static function connect($database, $debug_enabled = false, $bug_tracking_enabled = true, $unbuffered = false) {
		if (!is_string($database) || empty($database)) {
			throw new InvalidArgumentException('Connection name should be a string');
		}

		$connection_name = $unbuffered ? 'unbuffered' : 'buffered';

		if (!isset(self::$connections[$database][$connection_name])) {
			$connection_settings = [
				PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
				PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
				PDO::ATTR_PERSISTENT => false, // We don't use persistent connections by default. Persistent connections are to be set through configuration
			];

			// get and check properties
			$username = (string) config::get("database.{$database}.username");
			$password = (string) config::get("database.{$database}.password");
			$schema = (string) config::get("database.{$database}.schema");
			$hostname = (string) config::get("database.{$database}.hostname");
			$port = (int) config::get("database.{$database}.port", 3306);
			$max_query_length = (int) config::get("database.{$database}.max_query_length", self::MAX_QUERY_LENGTH_MB);
			$pdo_options = config::get("database.{$database}.pdo_options", $connection_settings);

			$connection_settings = array_replace($connection_settings, $pdo_options);
			$connection_settings[PDO::ATTR_ERRMODE] = PDO::ERRMODE_EXCEPTION;

			if (isset($connection_settings[PDO::MYSQL_ATTR_USE_BUFFERED_QUERY]) && $connection_settings[PDO::MYSQL_ATTR_USE_BUFFERED_QUERY] == false) {
				throw new InvalidArgumentException('Using PDO::MYSQL_ATTR_USE_BUFFERED_QUERY connection attribute in configuration is forbidden. Please, use the db/select::unbuffered() method to make unbuffered queries.');
			}

			if ($unbuffered) {
				$connection_settings[PDO::MYSQL_ATTR_USE_BUFFERED_QUERY] = false;
			}

			foreach (['hostname', 'username', 'password', 'schema'] as $property) {
				if (empty($$property)) {
					throw new InvalidArgumentException('Required connection parameter "' . $property . '" is not set or empty for database configuration: ' . $database);
				}
			}

			if ($max_query_length < 1) {
				throw new InvalidArgumentException('Parameter "max_query_length" is not an integer for database connection: ' . $database);
			}

			$connection = new self($database, $hostname, $username, $password, $schema, $port, $max_query_length);

			// change bug tracker settings for the current connection
			$connection->bug_tracker_enabled = (bool)$bug_tracking_enabled;

			try {
				$connection->open($connection_settings);
				// if error occurred, error() called and it saves error in bug tracker (if it is enabled) and throws DatabaseException
			} catch (DatabaseException $exception) {
				// cannot connect, try to use fallback connection
				$fallback = config::get("database.{$database}.fallback");

				if (!$fallback) {
					// fallback connection is not set, bubble up existing exception
					throw $exception;
				}

				if (is_string($fallback)) {
					// fallback is an alternative host, using other properties from main connection
					$hostname = $fallback;
				} else if (is_array($fallback)) {
					// fallback is a list of properties for database connection
					$hostname = (string) config::get("database.{$database}.fallback.hostname"); // required
					$username = (string) config::get("database.{$database}.fallback.username", $username); // not required, default from main connection
					$password = (string) config::get("database.{$database}.fallback.password", $password); // not required, default from main connection
					$port = (int) config::get("database.{$database}.fallback.port", $port); // not required, default from main connection
					$max_query_length = (int) config::get("database.{$database}.fallback.max_query_length", $max_query_length); // not required, default from main connection
					$pdo_options = config::get("database.{$database}.pdo_options", $connection_settings);

					$connection_settings = array_replace($connection_settings, $pdo_options);
					$connection_settings[PDO::ATTR_ERRMODE] = PDO::ERRMODE_EXCEPTION;

					if (isset($connection_settings[PDO::MYSQL_ATTR_USE_BUFFERED_QUERY]) && $connection_settings[PDO::MYSQL_ATTR_USE_BUFFERED_QUERY] == false) {
						throw new InvalidArgumentException('Using PDO::MYSQL_ATTR_USE_BUFFERED_QUERY connection attribute in configuration is forbidden. Please, use the db/select::unbuffered() method to make unbuffered queries.');
					}

					if ($unbuffered) {
						$connection_settings[PDO::MYSQL_ATTR_USE_BUFFERED_QUERY] = false;
					}
				} else {
					throw new InvalidArgumentException('Fallback property for database connection "' . $database . '" is invalid, it must be a hostname or an array of database connection settings.');
				}

				// we use schema from main connection as default, because it is fallback connection and we're expecting the same schema
				$connection = new self($database, $hostname, $username, $password, $schema, $port, $max_query_length);

				// change bug tracker settings for the current connection
				$connection->bug_tracker_enabled = (bool)$bug_tracking_enabled;

				// if error occurred, error() is called and it sends error details to bug tracker (if enabled) and throws DatabaseException
				$connection->open($connection_settings);
			}

			self::$connections[$database][$connection_name] = $connection;
		}

		// change debug mode for the current query
		if ($debug_enabled) {
			self::$connections[$database][$connection_name]->debug_enable();
		} else {
			self::$connections[$database][$connection_name]->debug_disable();
		}

		// change bug tracker settings for the current connection
		self::$connections[$database][$connection_name]->bug_tracker_enabled = (bool)$bug_tracking_enabled;

		return self::$connections[$database][$connection_name];
	}

	/**
	 * Destroy connection and release memory. Useful method for daemons if you need release connection and memory.
	 *
	 * @param $database
	 *      The name of configuration for database connection from configuration group "database".
	 */
	public static function disconnect($database) {
		$database = (string)$database;

		if (isset(self::$connections[$database]['buffered'])) {
			$adapter = &self::$connections[$database]['buffered']->adapter;

			if ($adapter->inTransaction()) {
				$adapter->rollBack();
			}

			$adapter = null;
		}

		if (isset(self::$connections[$database]['unbuffered'])) {
			$adapter = &self::$connections[$database]['unbuffered']->adapter;

			if ($adapter->inTransaction()) {
				$adapter->rollBack();
			}

			$adapter = null;
		}

		unset(self::$connections[$database]);
	}

	/**
	 * Gets the query type as a first word of the query (e.g. INSERT, DELETE, UPDATE...)
	 *
	 * @param string $query
	 * @return string
	 */
	public static function get_query_type($query) {
		$query_type = strtoupper(strtok(trim($query), " \n\t"));

		return in_array($query_type, self::$query_types) ? $query_type : 'OTHER';
	}

	/**
	 * Private constructor disallows creating instance directly
	 *
	 * @param string $configuration_name
	 * @param string $hostname
	 * @param string $username
	 * @param string $password
	 * @param string $schema
	 * @param string $port
	 * @param string $max_query_length_mb
	 */
	private function __construct($configuration_name, $hostname, $username, $password, $schema, $port, $max_query_length_mb) {
		$this->configuration_name = $configuration_name;
		$this->username = $username;
		$this->password = $password;
		$this->schema = $schema;
		$this->hostname = $hostname;
		$this->port = $port;
		$this->max_query_length = $max_query_length_mb * 1024 * 1024;
	}

	private function __sleep() {} // serialize($instance); - Warning: Invalid callback test::__sleep, cannot access private method test::__sleep()

	private function __wakeup() {} // unserialize($serialized_data); - Warning: Invalid callback test::__wakeup, cannot access private method test::__wakeup()

	private function __clone() {} // prevent cloning of the object

	/**
	 * Assigns an internal adapter to the object.
	 * @param $connection_settings
	 * @throws DatabaseException
	 */
	protected function open($connection_settings) {
		$this->pdo_options = $connection_settings;
		$data_source_name = "mysql:host={$this->hostname};port={$this->port};";

		if ($this->schema != '') {
			$data_source_name .= "dbname={$this->schema};";
		}

		try {
			// Block xdebug messages to appear on connection error. Only catch block works with exceptions
			$this->adapter = @new PDO($data_source_name, $this->username, $this->password, $connection_settings);
		} catch (PDOException $exception) {
			$this->error($exception);
		}
	}

	/**
	 * Returns maximum allowed length of query text in bytes
	 *
	 * @return int
	 */
	public function get_max_query_length() {
		return $this->max_query_length;
	}

	/**
	 * Return hostname of the connection
	 *
	 * @return string
	 */
	public function get_hostname() {
		return $this->hostname;
	}

	/**
	 * Return name of the configuration properties
	 *
	 * @return string
	 */
	public function get_configuration_name() {
		return $this->configuration_name;
	}

	/**
	 * Do a PING request to see whether the connection is still active
	 *
	 * @return bool
	 */
	public function ping() {
		if (is_null($this->adapter)) {
			return false;
		}

		try {
			$this->adapter->query('SELECT 1');
		} catch (PDOException $exception) {
			return false;
		}

		return true;
	}

	/**
	 * Initiates a transaction.
	 *
	 * @return db
	 *
	 * @throws DatabaseException - if failed to start a transaction
	 */
	public function begin() {
		if ($this->transaction_counter == 0) {
			if (!$this->adapter->beginTransaction()) {
				$this->error('Failed to start a transaction');
			}
		}

		//increment counter so we know which 'layer' of transaction we are in
		$this->transaction_counter++;

		if ($this->debug_enabled) {
			$this->debug_log['transactions']['begin_' . $this->transaction_counter] = array_slice(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS),0,4);
		}

		return $this;
	}

	/**
	 * Commits a transaction.
	 *
	 * @return db
	 *
	 * @throws DatabaseException
	 *      - if commit is called without beginning a transaction first
	 *      - if error occurred while database commit is running
	 */
	public function commit() {
		if ($this->transaction_counter == 0) {
			// transaction has not begun
			$this->error('Commit called without active transaction.');
		}

		if ($this->debug_enabled) {
			$this->debug_log['transactions']['commit_' . $this->transaction_counter] = array_slice(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS),0,4);
		}

		// decrement counter, only the last call of commit should be processed
		$this->transaction_counter--;

		if ($this->transaction_counter == 0) {
			if (!$this->adapter->commit()) {
				$this->error('Failed to commit a transaction');
			}
		}

		return $this;
	}

	/**
	 * Rolls back a transaction.
	 *
	 * @return db
	 *
	 * @throws DatabaseException
	 *      - if rollback is called without beginning a transaction first
	 *      - if error occurred while database rollback is running
	 */
	public function rollback() {
		if ($this->transaction_counter == 0) {
			// transaction has not begun
			$this->error('Rollback called without active transaction.');
		}

		if ($this->debug_enabled) {
			$this->debug_log['transactions']['rollback'] = array_slice(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS),0,4);
		}

		// clear counter and make rollback, any error in embedded transaction must cancel operation
		$this->transaction_counter = 0;

		if ($this->adapter) {
			if (!$this->adapter->rollBack()) {
				$this->error('Failed to rollback a transaction');
			}
		}

		return $this;
	}

	/**
	 * Low level interface for performing a query.
	 * This method prepares the query by binding data and then executes it.
	 *
	 * @param string $query
	 * @param array $binds
	 *
	 * @return PDOStatement
	 *      result of executing the query
	 *
	 * @throws DatabaseException
	 *      - if preparing the query has failed
	 *      - if query completed with error
	 */
	public function query($query, array $binds = []) {
		try {
			// save start time of the query
			if ($this->debug_enabled) {
				$start_time = microtime(true);
			}

			// execute query
			if (empty($binds)) {
				$statement = $this->adapter->query($query);
			} else {
				foreach ($binds as &$bind) {
					if ($bind instanceof DateTime) {
						$bind = $bind->format(self::FORMAT_DATETIME);
					}
				}

				$statement = $this->adapter->prepare($query);
				$statement->execute($binds);
			}

			// save debug info
			if ($this->debug_enabled) {
				if (!empty($binds) && in_array(self::get_query_type($query), ['INSERT', 'REPLACE'])) {
					$binds = array_slice($binds, 0, 20);
				}

				$this->debug_log['queries'][] = [
					'execution_time' => round(microtime(true) - $start_time, 3),
					'connection' => $this->adapter->getAttribute(PDO::ATTR_CONNECTION_STATUS),
					'query' => $statement->queryString,
					'binds' => $binds,
				];
			}

			return $statement;
		} catch (PDOException $exception) {
			$this->disconnect($this->configuration_name);
			$this->error($exception, $query, $binds); // throws DatabaseException
		}
	}

	/**
	 * Factory method for SELECT database request object.
	 * For executing the request you should call execute() method
	 * @see select::execute();
	 *
	 * @param string|null $query
	 * @param array $binds
	 *
	 * @return select
	 */
	public function select($query = null, array $binds = []) {
		return query::create(query::QUERY_SELECT, $this)
			->query($query)
			->binds($binds);
	}

	/**
	 * Factory method for DELETE database request object.
	 * For executing the request you should call execute() method
	 * @see delete::execute();
	 *
	 * @param string $table - table name
	 * @param string $where - WHERE statement. "WHERE" keyword is not required
	 * @param array $binds
	 *
	 * @return delete
	 */
	public function delete($table = null, $where = null, array $binds = []) {
		return query::create(query::QUERY_DELETE, $this)
			->from($table)
			->where($where)
			->binds($binds);
	}

	/**
	 * Factory method for INSERT database request object.
	 * For executing the request you should call execute() method
	 * @see insert::execute();
	 *
	 * @param string $table - table name
	 * @param array $values - one or more rows with values
	 * @param db ::ON_DUPLICATE_* $on_duplicate
	 *
	 * @return insert
	 */
	public function insert($table = null, $values = [], $on_duplicate = null) {
		return query::create(query::QUERY_INSERT, $this)
			->into($table)
			->on_duplicate($on_duplicate)
			->values($values);
	}

	/**
	 * Factory method for UPDATE database request object.
	 * For executing the request you should call execute() method
	 * @see update::execute();
	 *
	 * @param string $table - table name
	 * @param array $values - new values
	 * @param string $where - WHERE statement, "WHERE" keyword is not required
	 * @param array $binds - binds data for WHERE statement
	 *
	 * @return update
	 */
	public function update($table = null, array $values = [], $where = null, array $binds = []) {
		return query::create(query::QUERY_UPDATE, $this)
			->table($table)
			->values($values)
			->where($where)
			->binds($binds);
	}

	/**
	 * Factory method for REPLACE database request object.
	 * For executing the request you should call execute() method
	 * @see replace::execute();
	 *
	 * @param string $table - table name
	 * @param array $values - new values
	 * @param array $binds - binds data for WHERE statement
	 *
	 * @return replace
	 */
	public function replace($table = null, array $values = [], $binds = []) {
		return query::create(query::QUERY_REPLACE, $this)
			->table($table)
			->values($values)
			->binds($binds);
	}

	/**
	 * Create SQL expression which will not be bound or sanitized
	 *
	 * ->update('domain')
	 * ->value('expiry_date', db::expression('NOW() + INTERVAL :months MONTH'))
	 * ->where('id = :id')
	 * ->bind('months', $_GET['months'])
	 * ->bind('id', $_GET['id'])
	 *
	 * @param $expression
	 *
	 * @return \db\expression
	 */
	public static function expression($expression) {
		return new \db\expression($expression);
	}

	/**
	 * Performs a TRUNCATE query on the table.
	 * Note: this does not work within transactions
	 *
	 * @param string $table
	 *
	 * @throws DatabaseException
	 */
	public function truncate($table) {
		$table =  str_replace('`', '', $table);
		$table = explode('.', $table);
		$table = '`' . $table[0] . '`' . (isset($table[1]) ? '.`' . $table[1] . '`' : '') ;
		$this->query('TRUNCATE TABLE ' . $table);
	}

	/**
	 * Returns the auto increment value of the last query.
	 * If last query was not INSERT, returned value is uncertain.
	 *
	 * @return string
	 */
	public function last_insert_id() {
		return $this->adapter->lastInsertId();
	}

	/**
	 * @param PDOException|string $exception - exception object or error message
	 * @param string $query - text of query
	 * @param array $binds - array of bindings
	 *
	 * @throws DatabaseException
	 *          in any case throw exception!!!
	 */
	public function error($exception, $query = '', array $binds = null) {
		$message = ($exception instanceof Exception) ? $exception->getMessage() : (string)$exception;
		$exception = new DatabaseException($this->hostname, $this->configuration_name, $message, $query, $binds, $this->pdo_options);

		throw $exception;
	}

	/**
	 * Manually enable bug tracker.
	 * If database error is occurred, request to the bug tracker will be send.
	 *
	 * @return self
	 */
	public function bug_tracker_enable() {
		$this->bug_tracker_enabled = true;

		return $this;
	}

	/**
	 * Manually disable bug tracker.
	 * If database error is occurred, request to the bug tracker will NOT be send
	 *
	 * @return self
	 */
	public function bug_tracker_disable() {
		$this->bug_tracker_enabled = false;

		return $this;
	}

	/**
	 * Enable debug mode for connection. In debug mode every query string is collected and performance time is measured.
	 * Backtrace information about operation with transactions is also collected.
	 * @see self::get_debug_info()
	 *
	 * @return self
	 */
	public function debug_enable() {
		$this->debug_enabled = true;

		return $this;
	}

	/**
	 * Disable debug mode for connection.
	 *
	 * @return self
	 */
	public function debug_disable() {
		$this->debug_enabled = false;
		$this->debug_log = [];

		return $this;
	}

	/**
	 * Returns debug mode toggle state
	 *
	 * @return bool
	 */
	public function is_debug_enabled() {
		return $this->debug_enabled;
	}

	/**
	 * Returns bug tracker enabled toggle state
	 *
	 * @return bool
	 */
	public function is_bug_tracker_enabled() {
		return $this->bug_tracker_enabled;
	}

	/**
	 * Returns collected debug info
	 * 		[
	 *			'queries' => [
	 * 				[0] => [
	 * 					'execution_time' => 0.123, // execution time in seconds
	 * 					'connection' => 'mysql.server.com TCP/IP', // remote host and connection type
	 * 					'query' => 'SELECT ...',
	 * 				],
	 * 				...
	 * 			],
	 * 			'transactions' => [
	 * 				'begin_1' => [..], // back trace where begin() method was called
	 * 				'commit_1' => [..], // back trace where commit() method was called
	 * 				'rollback' => [..], // back trace where rollback() method was called
	 * 			],
	 * 		]
	 *
	 * @return array|null
	 * 		array of debug information if debug mode is enabled, otherwise null
	 */
	public function get_debug_info() {
		return $this->debug_enabled ? $this->debug_log : null;
	}

	/**
	 * Returns backtrace of embedded transactions.
	 * NOTE: backtrace collected only in debug mode
	 *
	 * @return null|array
	 * 		array with backtrace information if debug mode is enabled, otherwise null
	 */
	public function get_transaction_backtrace() {
		if (!$this->debug_enabled) {
			return null;
		}

		return isset($this->debug_log['transactions']) ? $this->debug_log['transactions'] : [];
	}
}
