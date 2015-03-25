<?php
/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 2.0 Alpha
 */

namespace OpenImporter\Importers\sources;

class XenForo1_1 extends \OpenImporter\Importers\AbstractSourceImporter
{
	protected $setting_file = '/library/config.php';

	public function getName()
	{
		return 'XenForo 1.1';
	}

	public function getVersion()
	{
		return '1.0';
	}

	public function getDbPrefix()
	{
		return 'xf_';
	}

	public function getDbName()
	{
		return $this->fetchSetting('dbname');
	}

	public function getTableTest()
	{
		return 'user';
	}

	public function dbConnectionData()
	{
		if ($this->path === null)
			return false;

		return array(
			'dbname' => $this->fetchSetting('dbname'),
			'user' => $this->fetchSetting('username'),
			'password' => $this->fetchSetting('password'),
			'host' => $this->fetchSetting('host'),
			'driver' => 'pdo_mysqli',
		);
	}

	protected function fetchSetting($name)
	{
		if (empty($GLOBALS['config']['db']))
			require_once($this->path . $this->setting_file);

		return $GLOBALS['config']['db'][$name];
	}

	/**
	 * From here on, all the methods are needed helper for the conversion
	 */
	public function preparseMembers($originalRows)
	{
		$rows = array();
		foreach ($originalRows as $row)
		{
			$pass = unserialize($row['tmp']);

			if (isset($pass['hash']))
				$row['passwd'] = $pass['hash'];
			else
				$row['passwd'] = sha1(md5(mktime()));

			if	(isset($pass['salt']))
				$row['password_salt'] = $pass['salt'];
			else
				$row['password_salt'] = '';
			unset($row['tmp']);

			$rows[] = $row;
		}

		return $rows;
	}

	public function preparseBoards($originalRows)
	{
		$rows = array();
		foreach ($originalRows as $row)
		{
			$request = $this->db->query("
				SELECT
					thread_id, last_post_id
				FROM {$this->config->from_prefix}thread
				WHERE node_id  = $row[id_board]
				ORDER BY thread_id DESC
				LIMIT 1");

				list(, $row['id_last_msg']) = $this->db->fetch_row($request);
				$this->db->free_result($request);

			$rows[] = $row;
		}

		return $rows;
	}
}