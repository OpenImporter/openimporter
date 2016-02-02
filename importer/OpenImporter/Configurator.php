<?php
/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Alpha
 */

namespace OpenImporter;

/**
 * Class Configurator
 * The configurator is used to hold common configuration information
 * such as the paths (to/from), prefixes, etc.
 * Basically a generic getter/setter
 *
 * @property string lang_dir
 * @property string path_to
 * @property string path_from
 * @property string script
 * @property string to_prefix
 * @property string from_prefix
 * @property object source
 * @property object destination
 *
 * @package OpenImporter
 */
class Configurator
{
	/**
	 * @var array
	 */
	protected $data = array();

	/**
	 * Sets a data value via "magic" method
	 *
	 * @param $key
	 *
	 * @param $val
	 */
	public function __set($key, $val)
	{
		$this->data[$key] = $val;
	}

	/**
	 * Gets a data value via "magic" method
	 *
	 * @param $key
	 *
	 * @return mixed|null
	 */
	public function __get($key)
	{
		if (isset($this->data[$key]))
		{
			return $this->data[$key];
		}
		else
		{
			return null;
		}
	}

	/**
	 * Checks if a value is et
	 * @param $key
	 *
	 * @return bool
	 */
	public function __isset($key)
	{
		return isset($this->data[$key]);
	}
}