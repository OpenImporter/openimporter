<?php
/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 2.0 Alpha
 */

namespace OpenImporter\Importers\sources;

class WP3 extends \OpenImporter\Importers\AbstractSourceImporter
{
	protected $setting_file = '/wp-config.php';

	public function getName()
	{
		return 'Wordpress 3.x';
	}

	public function getVersion()
	{
		return '1.0';
	}

	public function getDbPrefix()
	{
		return $this->fetchSetting('table_prefix');
	}

	public function dbConnectionData()
	{
		if ($this->path === null)
			return false;

		return array(
			'dbname' => $this->fetchSetting('DB_NAME'),
			'user' => $this->fetchSetting('DB_USER'),
			'password' => $this->fetchSetting('DB_PASSWORD'),
			'host' => $this->fetchSetting('DB_HOST'),
			'driver' => 'pdo_mysqli',
		);
	}

	protected function fetchSetting($name)
	{
		static $content = null;

		if ($content === null)
			$content = file_get_contents($this->path . $this->setting_file);

		if ($name == 'table_prefix')
			$pattern = '\$table_prefix\s*=\*\'(.*?)\';';
		else
			$pattern = 'define\s*\(\s*\'' . $name . '\',\s*\'(.*?)\'\);';
		$match = array();
		preg_match('~' . $pattern . '~i', $content, $match);

		return isset($match[1]) ? $match[1] : '';
	}

	public function getDbName()
	{
		return $this->fetchSetting('DB_NAME');
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