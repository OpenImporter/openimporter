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
 * Class DummyLang
 * Replaces the Lang class if something bad happens, for example if the XML
 * file is not properly formatted or if the directory is wrong
 *
 * @package OpenImporter\Core
 */
class DummyLang extends Lang
{
	/**
	 * Intercepts loading of xml file.
	 *
	 * @param string $path
	 * @return null
	 */
	public function loadLang($path)
	{
		return null;
	}

	/**
	 * Tests if given $key exists in lang
	 *
	 * @param string $key
	 *
	 * @return bool
	 */
	public function has($key)
	{
		return true;
	}

	/**
	 * Returns the specified $key.
	 *
	 * @param string $key Name of the variable
	 *
	 * @return string|null Value of the specified $key
	 */
	public function __get($key)
	{
		return $key;
	}

	/**
	 * Returns the specified $key.
	 *
	 * @param string $key Name of the variable
	 *
	 * @return string|null Value of the specified $key
	 */
	public function get($key)
	{
		if (is_array($key))
		{
			$l_key = array_shift($key);

			return $l_key . ' ' . implode(' ', $key);
		}
		else
		{
			return $key;
		}
	}

	/**
	 * Returns the whole lang as an array.
	 *
	 * @return array Whole lang
	 */
	public function getAll()
	{
		return array();
	}
}