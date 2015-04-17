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
 * The configurator is just a class holding the common configuration
 * info such as the paths (to/from), prefixes, etc.
 * Basically a getter/setter
 *
 * @property string $lang_dir
 * @property string $importers_dir
 * @property \OpenImporter\Importers\destinations\DestinationImporterInterface $destination
 * @property \OpenImporter\Importers\SourceImporterInterface $source
 * @property string $to_prefix
 * @property string $from_prefix
 * @property OpenImporter\Core\Configurator $progress
 */
class Configurator
{
	/**
	 * The array that holds all the data collected by the object.
	 *
	 * @var mixed[]
	 */
	protected $data = array();

	/**
	 * Data stored here will be saved in the $_SESSION array to allow pass them
	 * from page to page.
	 *
	 * @var mixed[]
	 */
	public $store = array();

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
		return isset($this->data[$key]);
	}
}