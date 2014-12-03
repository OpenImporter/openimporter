<?php
/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Alpha
 */

/**
 * Settings for the MyBB 1.6 system.
 */
class Viscacha extends AbstractSourceImporter
{
	protected $setting_file = '/data/config.inc.php';

	public function getName()
	{
		return 'Viscacha 0.8';
	}

	public function getVersion()
	{
		return 'ElkArte 1.0';
	}

	public function setDefines()
	{
		define('VISCACHA_CORE', 1);
	}

	public function getPrefix()
	{
		// @todo Convert the use of globals to a scan of the file or something similar.
		global $config;

		return '`' . $this->getDbName() . '`.' . $config['dbprefix'];
	}

	public function getDbName()
	{
		// @todo Convert the use of globals to a scan of the file or something similar.
		global $config;

		return $config['database'];
	}

	public function getTableTest()
	{
		return 'user';
	}
}