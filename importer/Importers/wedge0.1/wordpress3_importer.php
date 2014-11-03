<?php
/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Alpha
 */

class WP3 extends AbstractSourceImporter
{
	protected $setting_file = '/wp-includes/version.php';

	public function getName()
	{
		return 'Wordpress 3.x';
	}

	public function getVersion()
	{
		return 'Wedge 0.1';
	}

	public function getPrefix()
	{
		global $wp_database, $wp_prefix;

		return '`' . $wp_database . '`.' . $wp_prefix;
	}

	public function getTableTest()
	{
		return 'users';
	}
}