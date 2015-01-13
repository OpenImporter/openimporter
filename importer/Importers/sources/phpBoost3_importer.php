<?php
/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Alpha
 */

class PHPBoost3 extends AbstractSourceImporter
{
	public function getName()
	{
		return 'PHPBoost3';
	}

	public function getVersion()
	{
		return 'ElkArte 1.0';
	}

	public function getPrefix()
	{
		global $boost_prefix;

		return '`' . $this->getDbName() . '`.' . $boost_prefix;
	}

	public function getDbName()
	{
		global $boost_database;

		return $boost_database;
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
			$row['body'] = boost_replace_bbc($row['body']);

			if (!empty($row['modified_time']) && empty($row['modified_name']))
			{
				$row['modified_name'] = 'Guest';
			}

			$rows[] = $row;
		}

		return $rows;
	}
}

/**
 * Utility functions
 */
function boost_replace_bbc($content)
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