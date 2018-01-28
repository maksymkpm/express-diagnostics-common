<?php
/**
 * @copyright Dreamscapenetworks LLC
 * @link http://dreamscapenetworks.com
 */

/**
 * cURL request exception
 */
class CurlException extends Exception {
	/**
	 * messages for http codes
	 * @var array
	 */
	private static $http_code_message = [
		301 => 'Moved Permanently.',
		302 => 'Moved Temporarily.',
		400 => 'Bad request.',
		401 => 'Login failure.',
		403 => 'Forbidden.',
		404 => 'URL is not found.',
		405 => 'Method Not Allowed.',
		408 => 'Request Timeout.',
		413 => 'Request Entity Too Large.',
		414 => 'Request-URI Too Large.',
		429 => 'Too Many Requests.',
		434 => 'Requested host unavailable.',
		500 => 'Internal Server Error.',
		502 => 'Servers may be down or being upgraded.',
		503 => 'Service unavailable.',
		504 => 'Gateway Timeout.',
	];

	/**
	 * Request method: GET, POST, PUT, DELETE
	 * @type string
	 */
	private $method;

	/**
	 * @type string
	 */
	private $url;

	/**
	 * @type array
	 */
	private $parameters = [];

	/**
	 * @param string $method
	 * @param string $url
	 * @param int $http_code
	 * @param string|array $parameters
	 * @param string $message
	 */
	public function __construct($method, $url, $http_code, $parameters = [], $message = '') {
		if (empty($message)) {
			$message = isset(self::$http_code_message[$http_code]) ? self::$http_code_message[$http_code] : 'Undefined error.';
		} else {
			$message = (string)$message;
		}

		$this->method = (string)$method;
		$this->url = (string)$url;
		$this->parameters = $parameters;

		parent::__construct($message, $http_code);
	}

	/**
	 * Returns HTTP code of the response
	 * @return int
	 */
	public function getHTTPCode() {
		return $this->getCode();
	}

	/**
	 * Request URL
	 * @return string
	 */
	public function getUrl() {
		return $this->url;
	}

	/**
	 * Request method, usually it is: GET, POST, PUT or DELETE
	 * @return string
	 */
	public function getMethod() {
		return $this->method;
	}

	/**
	 * List of the request parameters
	 * @return array
	 */
	public function getRequestParameters() {
		return $this->parameters;
	}
}
