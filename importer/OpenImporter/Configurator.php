<?php
/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 2.0 Alpha
 */

namespace OpenImporter\Core

/**
 * The configurator is just a class holding the common configuration
 * info such as the paths (to/from), prefixes, etc.
 * Basically a getter/setter
 */
class Configurator
{
	protected $data = array();

	public function __set($key, $val)
	{
		$this->data[$key] = $val;
	}

	public function __get($key)
	{
		if (isset($this->data[$key]))
			return $this->data[$key];
		else
			return null;
	}

	public function __isset($key)
	{
		return isset($this->data[$key]);
	}
}