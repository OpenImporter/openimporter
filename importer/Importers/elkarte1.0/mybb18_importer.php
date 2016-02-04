<?php
/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Alpha
 */

/**
 * Class mybb18
 * Settings for the MyBB 1.8 system.
 */
class mybb18 extends Importers\AbstractSourceImporter
{
	protected $setting_file = '/inc/config.php';

	public function getName()
	{
		return 'MyBB 1.8';
	}

	public function getVersion()
	{
		return 'ElkArte 1.0';
	}

	public function getPrefix()
	{
		global $config;

		return '`' . $this->getDbName() . '`.' . $config['database']['table_prefix'];
	}

	public function getDbName()
	{
		global $config;

		return $config['database']['database'];
	}

	public function getTableTest()
	{
		return 'users';
	}
}