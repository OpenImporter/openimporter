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
abstract class AbstractSourceSmfImporter extends \OpenImporter\Importers\AbstractSourceImporter
{
	protected $setting_file = '/Settings.php';

	public function getDbPrefix()
	{
		return $this->fetchSetting('db_prefix');
	}

	public function dbConnectionData()
	{
		if ($this->path === null)
			return false;

		return array(
			'dbname' => $this->fetchSetting('db_name'),
			'user' => $this->fetchSetting('db_user'),
			'password' => $this->fetchSetting('db_passwd'),
			'host' => $this->fetchSetting('db_server'),
			'driver' => $this->fetchDriver(),
		);
	}

	protected function fetchDriver()
	{
		$type = $this->fetchSetting('db_type');
		$drivers = array(
			'mysql' => 'pdo_mysql',
			'mysqli' => 'pdo_mysql',
			'postgresql' => 'pdo_pgsql',
			'sqlite' => 'pdo_sqlite',
		);

		return isset($drivers[$type]) ? $drivers[$type] : 'pdo_mysql';
	}

	protected function fetchSetting($name)
	{
		static $content = null;

		if ($content === null)
			$content = file_get_contents($this->path . $this->setting_file);

		$match = array();
		preg_match('~\$' . $name . '\s*=\s*\'(.*?)\';~', $content, $match);

		return isset($match[1]) ? $match[1] : '';
	}

	public function getDbName()
	{
		return $this->fetchSetting('db_name');
	}

	public function getTableTest()
	{
		return 'members';
	}
}