<?php
/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Alpha
 */

class XenForo1_1 extends AbstractSourceImporter
{
	protected $setting_file = '/includes/config.php';

	public function getName()
	{
		return 'Wordpress 3.x';
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