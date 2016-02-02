<?php
/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Alpha
 */

/**
 * Class phpBB3
 */
class phpBB3 extends Importers\AbstractSourceImporter
{
	protected $setting_file = '/config.php';

	public function getName()
	{
		return 'phpBB3';
	}

	public function getVersion()
	{
		return 'ElkArte 1.0';
	}

	public function setDefines()
	{
		if (!defined('IN_PHPBB'))
		{
			define('IN_PHPBB', 1);
		}
	}

	public function getPrefix()
	{
		global $table_prefix;

		return '`' . $this->getDbName() . '`.' . $table_prefix;
	}

	public function getDbName()
	{
		global $dbname;

		return $dbname;
	}

	public function getTableTest()
	{
		return 'users';
	}
}

// Utility functions specific to phpbb

/**
 * @param int $percent
 *
 * @return int
 */
function percent_to_px($percent)
{
	return intval(11 * (intval($percent) / 100.0));
}

/**
 * Normalize BBC
 *
 * @param string $message
 *
 * @return mixed|string
 */
function phpbb_replace_bbc($message)
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
			'[size=' . percent_to_px("\1") . 'px]',
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