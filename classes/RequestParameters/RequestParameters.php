<?php

namespace RequestParameters;

/**
 * Class RequestParameters
 */
abstract class RequestParameters implements \ArrayAccess {
	/**
	 * Field mapping array camelCase -> under_score
	 *
	 * @var array
	 */
	protected $__fieldMapping = [];

	/**
	 * RequestParameters constructor.
	 *
	 * @param array $properties
	 */
	public function __construct(array $properties = null) {
		if ($properties !== null) {
			$this->setProperty($properties);
		}
	}

	public function setProperty(array $property) {
		foreach ($property as $propertyName => $propertyValue) {
			$this->$propertyName = $propertyValue;
		}
	}

	public function __set($propertyName, $propertyValue) {
		if (!$this->offsetExists($propertyName)) {
			return;
		}

		$this->$propertyName = $propertyValue;
	}

	public function __get($propertyName) {
		if (!$this->offsetExists($propertyName)) {
			return null;
		}

		return $this->$propertyName;
	}

	public function __isset($name) {
		return $this->offsetExists($name);
	}

	public function offsetExists($offset) {
		return property_exists($this, $offset);
	}

	public function offsetGet($offset) {
		return $this->$offset;
	}

	public function offsetSet($offset, $value) {
		$this->$offset = $value;
	}

	public function offsetUnset($offset) {
		$this->$offset = null;
	}

	/**
	 * Returns only not null properties
	 * @note BE SURE THAT YOUR PROPERTY NAMES IN PARAMS EQUALS DB FIELD NAMES
	 * @param array $mergeData
	 * @param array $fieldsFilter
	 * @return array
	 */
	public function getModified($mergeData = [], $fieldsFilter= []) {
		$result = is_array($mergeData) ? $mergeData : [];

		if (!is_array($fieldsFilter)) {
			$fieldsFilter = [$fieldsFilter];
		}

		foreach (get_object_vars($this) as $key => $value) {
			if ($key == '__fieldMapping' || in_array($key, $fieldsFilter)) {
				continue;
			}

			if (!is_null($value)) {
				$real_key = !empty($this->__fieldMapping[$key]) ? $this->__fieldMapping[$key] : $key;
				$result[$real_key] = $value;
			}
		}

		return $result;
	}
}
