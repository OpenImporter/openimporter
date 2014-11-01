<?php
/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Alpha
 */

class vBulletin_4 extends AbstractSourceImporter
{
	protected $setting_file = '/includes/config.php';

	public function getName()
	{
		return 'vBulletin 4';
	}

	public function getVersion()
	{
		return 'Wedge 0.1';
	}

	public function getPrefix()
	{
		global $config;

		return '`' . $config['Database']['dbname'] . '`.' . $config['Database']['tableprefix'];
	}

	public function getTableTest()
	{
		return 'user';
	}
}

/**
 * Utility functions
 */
function vb4_replace_bbc($content)
{
	$content = preg_replace(
		array(
			'~\[(quote)=([^\]]+)\]~i',
			'~\[(.+?)=&quot;(.+?)&quot;\]~is',
			'~\[INDENT\]~is',
			'~\[/INDENT\]~is',
			'~\[LIST=1\]~is',
		),
		array(
			'[$1=&quot;$2&quot;]',
			'[$1=$2]',
			'	',
			'',
			'[list type=decimal]',
		), strtr($content, array('"' => '&quot;')));

	// fixing Code tags
	$replace = array();
	
	preg_match('~\[code\](.+?)\[/code\]~is', $content, $matches);
	foreach ($matches as $temp)
		$replace[$temp] = htmlspecialchars($temp);
	$content = substr(strtr($content, $replace), 0, 65534);

	return $content;
}