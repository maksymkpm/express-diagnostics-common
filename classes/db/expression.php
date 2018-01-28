<?php
/**
 * @copyright Dreamscapenetworks LLC
 * @link http://dreamscapenetworks.com
 *
 * Database abstraction layer
 */

namespace db;

/**
 * SQL language expression
 *
 * @package db
 */
class expression {

	protected $expression;

	/**
	 * Constructor
	 *
	 * NOTE: better to use shortcut db::expression('NULL');
	 *
	 * @param $expression
	 * 		- MySQL expression
	 */
	public function __construct($expression) {
		if (!is_string($expression)) {
			throw new \InvalidArgumentException('SQL Expression must be a string');
		}

		$this->expression = $expression;
	}

	public function __toString() {
		return $this->expression;
	}
}
