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
		return '1.0';
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

	/**
	 * From here on, all the methods are needed helper for the conversion
	 */
	public function preparseMembers($originalRows)
	{
		$rows = array();
		foreach ($originalRows as $row)
		{
			$request = $this->db->query("
				SELECT meta_value
				FROM {$this->config->from_prefix}usermeta
				WHERE user_id = $row[id_member]
				AND meta_key = 'wp_capabilities'");

			list ($serialized) = $this->db->fetch_row($request);
			$row['id_group'] = array_key_exists('administrator', unserialize($serialized)) ? 1 : 0;
			$this->db->free_result($request);

			$rows[] = $row;
		}

		return $rows;
	}

	public function codeCategories()
	{
		return array(
			'id_cat' => 1,
			'name' => 'General Category',
			'cat_order' => 0,
			'can_collapse' => 1,
		);
	}
}