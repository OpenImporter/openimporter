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
		return '1.0';
	}

	public function getPrefix()
	{
		global $config;

		return '`' . $this->getDbName() . '`.' . $config['Database']['tableprefix'];
	}

	public function getDbName()
	{
		global $config;

		return $config['Database']['dbname'];
	}

	public function getTableTest()
	{
		return 'user';
	}

	/**
	 * From here on, all the methods are needed helper for the conversion
	 */
	public function preparseMembers($originalRows)
	{
		$rows = array();
		foreach ($originalRows as $row)
		{
			$row['signature'] = vb4_replace_bbc($row['signature']);

			$rows[] = $row;
		}

		return $rows;
	}

	public function preparseBoards($originalRows)
	{
		$rows = array();
		$request = $this->db->query("
			SELECT forumid AS id_cat
			FROM {$this->config->from_prefix}forum
			WHERE parentid = -1");

		$cats = array();
		while ($row = $this->db->fetch_assoc($request))
			$cats[$row['id_cat']] = $row['id_cat'];
		$this->db->free_result($request);

		foreach ($originalRows as $row)
		{
			if (isset($cats[$row['id_parent']]))
			{
				$row['id_cat'] = $cats[$row['id_parent']];
				$row['id_parent'] = 0;
			}

			$rows[] = $row;
		}

		return $rows;
	}

	public function preparseMessages($originalRows)
	{
		$rows = array();
		foreach ($originalRows as $row)
		{
			$row['body'] = vb4_replace_bbc($row['body']);

			$rows[] = $row;
		}

		return $rows;
	}

	public function preparsePolloptions($originalRows)
	{
		$rows = array();
		foreach ($originalRows as $row)
		{
			$options = explode('|||', $row['options']);
			$votes = explode('|||', $row['votes']);

			$id_poll = $row['id_poll'];
			for ($i = 0, $n = count($options); $i < $n; $i++)
			{
				$rows[] = array(
					'id_poll' => $id_poll,
					'id_choice' => ($i + 1),
					'label' => substr(addslashes($options[$i]), 1, 255),
					'votes' => (is_numeric($votes[$i]) ? $votes[$i] : 0),
				);
			}
		}

		return $rows;
	}

	public function preparsePm($originalRows)
	{
		$rows = array();
		foreach ($originalRows as $row)
		{
			$row['body'] = vb4_replace_bbc($row['body']);

			$rows[] = $row;
		}

		return $rows;
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