<?php

/**
 * Class to determine application execution environment
 *
 * To find out if this development or production environment or get current environment name use these methods:
 * @see Environment::isProduction() - bool
 * @see Environment::isDevelopment() - bool
 * @see Environment::isStaging() - bool
 * @see Environment::isTesting() - bool
 * @see Environment::get() - string (DEVELOPMENT|PRODUCTION|STAGING|TESTING)
 *
 * To find out if application is run under apache or command line interface:
 * @see evnironment::isCli() - bool
 *
 * After initializing this, the auto loader will load classes from this directory and you don't need to use require or include statements
 */

final class Environment {
	const PRODUCTION = 'PRODUCTION';
	const DEVELOPMENT = 'DEVELOPMENT';
	const STAGING = 'STAGING';
	const TESTING = 'TESTING';

	private static $environment; // DEVELOPMENT|PRODUCTION|STAGING|TESTING
	private static $invocation; // cli|web
	private static $isInitialized = false; //is class initialized flag

	/**
	 * Common environment initialization
	 * Needs to be called first of all
	 *
	 * @param $environment
	 * @throws Exception
	 */
	public static function initialize(string $environment) {
		if (self::$isInitialized) {
			throw new Exception('Class will not be initialize twice');
		}

		self::set($environment);
		self::$invocation = (php_sapi_name() === 'cli') ? 'cli' : 'web';
		self::$isInitialized = true;
	}

	/**
	 * Check if application was called from the command line interface
	 *
	 * @return bool
	 */
	public static function isCli(): bool {
		return (self::$invocation === 'cli');
	}

	/**
	 * Check if application is in DEVELOPMENT environment
	 *
	 * @return bool
	 */
	public static function isDevelopment(): bool {
		return (self::$environment === self::DEVELOPMENT);
	}

	/**
	 * Check if application is in PRODUCTION environment
	 *
	 * @return bool
	 */
	public static function isProduction(): bool {
		return (self::$environment === self::PRODUCTION);
	}

	/**
	 * Check if application is in STAGING environment
	 *
	 * @return bool
	 */
	public static function isStaging(): bool {
		return (self::$environment === self::STAGING);
	}

	/**
	 * Check if application is in TESTING environment
	 *
	 * @return bool
	 */
	public static function isTesting(): bool {
		return (self::$environment === self::TESTING);
	}

	/**
	 * Get application environment
	 *
	 * @return string
	 */
	public static function get(): string {
		return self::$environment;
	}

	/**
	 * Dummy setter for environment type
	 * @param $environment
	 */
	public static function set(string $environment) {
		self::$environment = $environment;
	}
}
