<?php
/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD https://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0
 */

/**
 * Class WP3
 * Wordpress 3.x
 */
class WP3 extends Importers\AbstractSourceImporter
{
	protected $setting_file = '/wp-includes/version.php';

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
		global $wp_prefix;

		return '`' . $this->getDbName() . '`.' . $wp_prefix;
	}

	public function getDbName()
	{
		global $wp_database;

		return $wp_database;
	}

	public function getTableTest()
	{
		return 'users';
	}
}
