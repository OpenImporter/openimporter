<?php
/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD https://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0
 */

namespace OpenImporter;

/**
 * Class Configurator
 *
 * The configurator is used to hold common configuration information
 * such as the paths (to/from), prefixes, etc.
 * Basically a generic getter/setter
 *
 * @class Configurator
 */
class Configurator
{
	/** @var mixed|null via magic */
	public $source;

	/** @var mixed|null via magic */
	public $destination;

	/** @var string (via magic) The table prefix for our destination database */
	public $to_prefix;

	/** @var string (via magic) The table prefix for our source database */
	public $from_prefix;

	/** @var string The path to the source forum. */
	public $path_from;

	/** @var string The path to the destination forum. */
	public $path_to;

	/** @var string (via magic) The script recipe we are using */
	public $script;

	/** @var string (via magic, see import.php) */
	public $lang_dir;

	/** @var int (via magic, see importer.php) */
	public $step;

	/** @var int (via magic, see importer.php) */
	public $start;

	/** @var string (via magic, see importer.php) */
	public $boardurl;

	/** @var array */
	protected $data = array();

	/**
	 * Gets a data value via "magic" method
	 *
	 * @param string $key
	 *
	 * @return mixed|null
	 */
	public function __get($key)
	{
		return $this->data[$key] ?? null;
	}

	/**
	 * Sets a data value via "magic" method
	 *
	 * @param string $key
	 * @param string $val
	 */
	public function __set($key, $val)
	{
		$this->data[$key] = $val;
	}

	/**
	 * Checks if a value is et
	 *
	 * @param string $key
	 *
	 * @return bool
	 */
	public function __isset($key)
	{
		return isset($this->data[$key]);
	}
}
