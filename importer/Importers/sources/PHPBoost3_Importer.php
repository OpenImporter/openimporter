<?php
/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 2.0 Alpha
 */

namespace OpenImporter\Importers\sources;

class PHPBoost3_Importer extends \OpenImporter\Importers\AbstractSourceImporter
{
	protected $setting_file = '/kernel/db/config.php';

	public function getName()
	{
		return 'PHPBoost3';
	}

	public function getVersion()
	{
		return '1.0';
	}

	public function getDbPrefix()
	{
		if (!defined('PREFIX'))
			$this->fetchSetting('sql_host');

		return PREFIX;
	}

	public function dbConnectionData()
	{
		if ($this->path === null)
			return false;

		return array(
			'dbname' => $this->fetchSetting('sql_base'),
			'user' => $this->fetchSetting('sql_login'),
			'password' => $this->fetchSetting('sql_pass'),
			'host' => $this->fetchSetting('sql_host'),
			'driver' => 'pdo_mysql',
		);
	}

	protected function fetchSetting($name)
	{
		if (empty($GLOBALS['sql_host']))
			include($this->path . $this->setting_file);

		return $GLOBALS[$name];
	}

	public function getDbName()
	{
		return $this->fetchSetting('sql_base');
	}

	public function getTableTest()
	{
		return 'member';
	}

	/**
	 * From here on, all the methods are needed helper for the conversion
	 */
	public function preparseTopics($originalRows)
	{
		$rows = array();
		foreach ($originalRows as $row)
		{
			$row['id_member_started'] = (int) $row['id_member_started'];
			$row['id_member_updated'] = (int) $row['id_member_updated'];

			if(empty($row['id_poll']))
				$row['id_poll'] = 0;

			$rows[] = $row;
		}

		return $rows;
	}

	public function preparseMessages($originalRows)
	{
		$rows = array();
		foreach ($originalRows as $row)
		{
			$row['body'] = $this->replaceBbc($row['body']);

			if (!empty($row['modified_time']) && empty($row['modified_name']))
			{
				$row['modified_name'] = 'Guest';
			}

			$rows[] = $row;
		}

		return $rows;
	}

	/**
	 * Utility functions
	 */
	protected function replaceBbc($content)
	{
		$content = preg_replace(
			array(
				'~<strong>~is',
				'~</strong>~is',
				'~<em>~is',
				'~</em>~is',
				'~<strike>~is',
				'~</strike>~is',
				'~\<h3(.+?)\>~is',
				'~</h3>~is',
				'~\<span stype="text-decoration: underline;">(.+?)</span>~is',
				'~\<div class="bb_block">(.+?)<\/div>~is',
				'~\[style=(.+?)\](.+?)\[\/style\]~is',
			),
			array(
				'[b]',
				'[/b]',
				'[i]',
				'[/i]',
				'[s]',
				'[/s]',
				'',
				'',
				'[u]%1[/u]',
				'%1',
				'%1',
			),
			trim($content)
		);

		return $content;
	}
}