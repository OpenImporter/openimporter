<?php
/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD https://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0
 */

/**
 * Class XenForo1_1
 */
class XenForo1_1 extends Importers\AbstractSourceImporter
{
	protected $setting_file = '/includes/config.php';

	public function getName()
	{
		return 'XenForo 1.1';
	}

	public function getVersion()
	{
		return 'ElkArte 1.0';
	}

	public function getPrefix()
	{
		global $xf_prefix;

		return '`' . $this->getDbName() . '`.' . $xf_prefix;
	}

	public function getDbName()
	{
		global $xf_database;

		return $xf_database;
	}

	public function getTableTest()
	{
		return 'user';
	}
}
