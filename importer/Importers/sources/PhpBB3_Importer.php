<?php
/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 2.0 Alpha
 */

namespace OpenImporter\Importers\sources;

class PhpBB3_Importer extends \OpenImporter\Importers\AbstractSourceImporter
{
	protected $setting_file = '/config.php';

	public function getName()
	{
		return 'phpBB3';
	}

	public function getVersion()
	{
		return '1.0';
	}

	public function setDefines()
	{
		define('IN_PHPBB', 1);
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
			'dbname' => $this->fetchSetting('dbname'),
			'user' => $this->fetchSetting('dbuser'),
			'password' => $this->fetchSetting('dbpasswd'),
			'host' => $this->fetchSetting('dbhost'),
			'driver' => $this->fetchDriver(),
			'test_table' => $this->getTableTest(),
			'system_name' => $this->getname(),
		);
	}

	public function getTableTest()
	{
		return '{db_prefix}users';
	}

	protected function fetchDriver()
	{
		$type = $this->fetchSetting('dbms');
		$drivers = array(
// 			'firebird' => '', // Not supported by Doctrine DBAL (yet)
			'mssql' => 'pdo_sqlsrv',
			'mssql_odbc' => 'pdo_sqlsrv',
			'mssqlnative' => 'pdo_sqlsrv',
			'mysql' => 'pdo_mysql',
			'mysqli' => 'pdo_mysql',
			'oracle' => 'pdo_oci',
			'postgres' => 'pdo_pgsql',
			'sqlite' => 'pdo_sqlite',
		);

		return isset($drivers[$type]) ? $drivers[$type] : 'pdo_mysql';
	}

	public function getDbName()
	{
		return $this->fetchSetting('dbname');
	}

	protected function fetchSetting($name)
	{
		$content = $this->readSettingsFile();

		$match = array();
		preg_match('~\$' . $name . '\s*=\s*\'(.*?)\';~', $content, $match);

		return isset($match[1]) ? $match[1] : '';
	}

	protected function fixBbc($body, $bbc_replace)
	{
		$body = $this->replaceBbc($body);
		$body = str_replace($bbc_replace, '', $body);

		return $body;
	}

	/**
	 * From here on, all the methods are needed helper for the conversion
	 */
	public function preparseMembers($originalRows)
	{
		$rows = array();
		foreach ($originalRows as $row)
		{
			$row['body'] = $this->fixBbc($row['signature'], $row['tmp_bbc_replace']);
			unset($row['tmp_bbc_replace']);

			$rows[] = $row;
		}

		return $rows;
	}

	public function preparseMessages($originalRows)
	{
		$rows = array();
		foreach ($originalRows as $row)
		{
			$row['body'] = $this->fixBbc($row['body'], $row['tmp_bbc_replace']);
			unset($row['tmp_bbc_replace']);

			$rows[] = $row;
		}

		return $rows;
	}

	public function preparsePm($originalRows)
	{
		$rows = array();
		foreach ($originalRows as $row)
		{
			$row['body'] = $this->fixBbc($row['body'], $row['tmp_bbc_replace']);
			unset($row['tmp_bbc_replace']);

			$rows[] = $row;
		}

		return $rows;
	}

	/**
	 * Utility functions
	 */
	protected function percentToPx($percent)
	{
		return intval(11*(intval($percent)/100.0));
	}

	protected function replaceBbc($message)
	{
		$message = preg_replace(
			array(
				'~\[quote=&quot;(.+?)&quot;\:(.+?)\]~is',
				'~\[quote\:(.+?)\]~is',
				'~\[/quote\:(.+?)\]~is',
				'~\[b\:(.+?)\]~is',
				'~\[/b\:(.+?)\]~is',
				'~\[i\:(.+?)\]~is',
				'~\[/i\:(.+?)\]~is',
				'~\[u\:(.+?)\]~is',
				'~\[/u\:(.+?)\]~is',
				'~\[url\:(.+?)\]~is',
				'~\[/url\:(.+?)\]~is',
				'~\[url=(.+?)\:(.+?)\]~is',
				'~\[/url\:(.+?)\]~is',
				'~\<a(.+?) href="(.+?)">(.+?)</a>~is',
				'~\[img\:(.+?)\]~is',
				'~\[/img\:(.+?)\]~is',
				'~\[size=(.+?)\:(.+?)\]~is',
				'~\[/size\:(.+?)?\]~is',
				'~\[color=(.+?)\:(.+?)\]~is',
				'~\[/color\:(.+?)\]~is',
				'~\[code=(.+?)\:(.+?)\]~is',
				'~\[code\:(.+?)\]~is',
				'~\[/code\:(.+?)\]~is',
				'~\[list=(.+?)\:(.+?)\]~is',
				'~\[list\:(.+?)\]~is',
				'~\[/list\:(.+?)\]~is',
				'~\[\*\:(.+?)\]~is',
				'~\[/\*\:(.+?)\]~is',
				'~\<img src=\"{SMILIES_PATH}/(.+?)\" alt=\"(.+?)\" title=\"(.+?)\" /\>~is',
			),
			array(
				'[quote author="$1"]',
				'[quote]',
				'[/quote]',
				'[b]',
				'[/b]',
				'[i]',
				'[/i]',
				'[u]',
				'[/u]',
				'[url]',
				'[/url]',
				'[url=$1]',
				'[/url]',
				'[url=$2]$3[/url]',
				'[img]',
				'[/img]',
				'[size=' . $this->percentToPx("\1") . 'px]',
				'[/size]',
				'[color=$1]',
				'[/color]',
				'[code=$1]',
				'[code]',
				'[/code]',
				'[list type=$1]',
				'[list]',
				'[/list]',
				'[li]',
				'[/li]',
				'$2',
			), $message);

		$message = preg_replace('~\[size=(.+?)px\]~is', "[size=" . ('\1' > '99' ? 99 : '"\1"') . "px]", $message);

		$message = strtr($message, array(
			'[list type=1]' => '[list type=decimal]',
			'[list type=a]' => '[list type=lower-alpha]',
		));
		$message = stripslashes($message);

		return $message;
	}
}