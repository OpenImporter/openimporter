<?php
/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 2.0 Alpha
 */

namespace OpenImporter\Importers;

/**
 * This abstract class is the base for any php importer file.
 *
 * It provides some common necessary methods and some default properties
 * so that Importer can do its job without having to test for existinance
 * of methods every two/three lines of code.
 */
abstract class AbstractSourceImporter implements SourceImporterInterface
{
	protected $setting_file = '';

	protected $path = '';

	protected $db = null;
	protected $config = null;

	public function setUtils($db, $config)
	{
		$this->db = $db;
		$this->config = $config;
	}

	abstract public function getName();

	abstract public function getVersion();

	abstract public function getDbPrefix();

	abstract public function getDbName();

	abstract public function getTableTest();

	abstract public function dbConnectionData();

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

		// Error silenced in case of odd server configurations (open_basedir mainly)
		if ($this->testPath($path))
		{
			include($path . $this->setting_file);
			return true;
		}
		else
			return false;
	}

	protected function readSettingsFile()
	{
		static $content = null;

		if ($content === null)
			$content = file_get_contents($this->path . $this->setting_file);

		return $content;
	}

	protected function testPath($path)
	{
		$found = file_exists($path . $this->setting_file);

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

	public function callMethod($method, $params = null)
	{
		if (method_exists($this, $method))
		{
			return call_user_func_array(array($this, $method), array($params));
		}
		else
		{
			return $params;
		}
	}
}