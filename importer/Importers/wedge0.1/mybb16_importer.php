<?php
/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Alpha
 */

/**
 * Class mybb16
 * Settings for the MyBB 1.6 system.
 */
class mybb16 extends Importers\AbstractSourceImporter
{
	protected $setting_file = '/inc/config.php';

	public function getName()
	{
		return 'MyBB 1.6';
	}

	public function getVersion()
	{
		return 'Wedge 0.1';
	}

	public function getPrefix()
	{
		// @todo Convert the use of globals to a scan of the file or something similar.
		global $oi_config;

		return '`' . $this->getDbName() . '`.' . $oi_config['database']['table_prefix'];
	}

	public function getDbName()
	{
		// @todo Convert the use of globals to a scan of the file or something similar.
		global $oi_config;

		return $oi_config['database']['database'];
	}

	public function getTableTest()
	{
		return 'users';
	}
}