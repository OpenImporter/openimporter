<?php
/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 2.0 Alpha
 */

namespace OpenImporter\Importers\sources;

class SeoBoards1_1 extends \OpenImporter\Importers\AbstractSourceImporter
{
	protected $setting_file = '/seo-board_options.php';
	protected $child_level = 0;

	public function getName()
	{
		return 'SEO-Boards 1.1';
	}

	public function getVersion()
	{
		return '1.0';
	}

	public function dbConnectionData()
	{
		if ($this->path === null)
			return false;

		return array(
			'dbname' => $this->fetchSetting('dbname'),
			'user' => $this->fetchSetting('dbuser'),
			'password' => $this->fetchSetting('dbpass'),
			'host' => $this->fetchSetting('dbhost'),
			'driver' => 'pdo_mysql',  // As far as I can tell IPB is MySQL only
		);
	}

	public function getDbPrefix()
	{
		return $this->fetchSetting('dbpref');
	}

	public function getDbName()
	{
		return $this->fetchSetting('dbname');
	}

	public function getTableTest()
	{
		return 'users';
	}

	protected function fetchSetting($name)
	{
		$content = $this->readSettingsFile();

		$match = array();
		preg_match('~\$' . $name . '\s*=\s*\'(.*?)\';~', $content, $match);

		return isset($match[1]) ? $match[1] : '';
	}

	/**
	 * $shaprefix is necessary in order to perform the login.
	 * Because passwords are stored in the db as:
	 *   sha1($shaprefix . $pass)
	 */
	public function codeSettings()
	{
		$rows = array(array(
			'variable' => 'shaprefix',
			'value' => $this->fetchSetting('shaprefix'),
		));

		return $rows;
	}

	/**
	 * From here on, all the methods are needed helper for the conversion
	 */
	public function preparseMembers($originalRows)
	{
		$rows = array();

		foreach ($originalRows as $row)
		{
			if (!empty($row['user_banned']))
				$row['is_activated'] = 11;
			$row['date_registered'] = date('Y-m-d G:i:s', $row['date_registered']);

			unset($row['user_banned']);

			$rows[] = $row;
		}

		return $rows;
	}

	public function preparseBoards($originalRows)
	{
		$rows = array();

		foreach ($originalRows as $row)
		{
			$row['id_cat'] = $this->getRootForum($row['id_board']);
			$row['child_level'] = $this->child_level;

			$this->child_level = 0;
			$rows[] = $row;
		}

		return $rows;
	}

	protected function getRootForum($id)
	{
		$request = $this->db->query("
			SELECT forum_parent
			FROM {$this->config->from_prefix}forums
			WHERE forum_id = $id");
		list ($parent) = $this->db->fetch_row($request);
		$this->db->free_result($request);

		if ($parent == 0)
		{
			$this->child_level--;
			return $id;
		}
		else
		{
			$this->child_level++;
			return $this->getRootForum($parent);
		}
	}

	public function preparseMessages($originalRows)
	{
		$rows = array();

		foreach ($originalRows as $row)
		{
			$row['smileys_enabled'] = (((int) $row['post_text_status']) & 4) != 0 ? 1 : 0;
			unset($row['post_text_status']);

			$rows[] = $row;
		}

		return $rows;
	}
}
