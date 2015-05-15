<?php
/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 2.0 Alpha
 */

namespace OpenImporter\Core;

/**
 * A generic class that define simple setter and getter
 */
class ValuesBag
{
	/**
	 * The array that holds all the data collected by the object.
	 *
	 * @var mixed[]
	 */
	protected $data = array();

	/**
	 * Setter
	 *
	 * @param string|int $key
	 * @param string|int|bool|null|object $val
	 */
	public function __set($key, $val)
	{
		$this->data[$key] = $val;
	}

	/**
	 * Getter
	 *
	 * @param string|int $key
	 * @return string|int|bool|null|object
	 */
	public function __get($key)
	{
		if (isset($this->data[$key]))
			return $this->data[$key];
		else
			return null;
	}

	/**
	 * Tests if the key is set.
	 *
	 * @param string|int $key
	 * @return bool
	 */
	public function __isset($key)
	{
		return array_key_exists($key, $this->data);
	}
}