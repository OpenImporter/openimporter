<?php
/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Alpha
 */

namespace Importers;

use OpenImporter\Configurator;
use OpenImporter\Database;

/**
 * This abstract class is the base for any php importer file.
 *
 * It provides some common necessary methods and some default properties
 * so that Importer can do its job without having to test for existence
 * of methods every two/three lines of code.
 */
abstract class AbstractSourceImporter
{
	/**
	 * Settings file name
	 * @var string
	 */
	protected $setting_file = '';

	/**
	 * Path to source
	 * @var string
	 */
	protected $path = '';

	/**
	 * @var Database
	 */
	protected $db = null;

	/**
	 * @var Configurator
	 */
	protected $config = null;

	public function setUtils($db, $config)
	{
		$this->db = $db;
		$this->config = $config;
	}

	public abstract function getName();

	public abstract function getVersion();

	public abstract function getPrefix();

	public abstract function getDbName();

	public abstract function getTableTest();

	public function loadSettings($path, $test = false)
	{
		if ($test)
		{
			if (empty($this->setting_file))
				return null;

			return $this->testPath($path);
		}

		if (empty($this->setting_file))
			return true;

		if ($this->testPath($path))
		{
			global $config;

			// Holds (generally) the source forum config values from the require file
			// @todo something better in the future
			if (empty($config))
			{
				$config = array();

				// Load the $config values
				require_once($path . $this->setting_file);
			}

			return true;
		}
		else
			return false;
	}

	protected function testPath($path)
	{
		$found = @file_exists($path . $this->setting_file);

		if ($found)
			$this->path = $path;

		return $found;
	}

	public function setDefines()
	{
	}

	public function setGlobals()
	{
	}
}