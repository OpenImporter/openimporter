<?php
/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Alpha
 */

/**
 * Settings for the MyBB 1.6 system.
 */
class Viscacha extends AbstractSourceImporter
{
	protected $setting_file = '/data/config.inc.php';

	public function getName()
	{
		return 'Viscacha 0.8';
	}

	public function getVersion()
	{
		return 'ElkArte 1.0';
	}

	public function setDefines()
	{
		define('VISCACHA_CORE', 1);
	}

	public function getPrefix()
	{
		// @todo Convert the use of globals to a scan of the file or something similar.
		global $config;

		return '`' . $this->getDbName() . '`.' . $config['dbprefix'];
	}

	public function getDbName()
	{
		// @todo Convert the use of globals to a scan of the file or something similar.
		global $config;

		return $config['database'];
	}

	public function getTableTest()
	{
		return 'user';
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

			// @todo: are you sure?
			// id_topic becomes the id_poll? ??? Odd design.
			if(!empty($row['id_poll']))
				$row['id_poll'] = $row['id_topic'];
			else
				$row['id_poll'] = 0;

			$rows[] = $row;
		}

		return $rows;
	}

	public function preparsePm($originalRows)
	{
		$rows = array();
		foreach ($originalRows as $row)
		{
			$row['body'] = preg_replace(
				array(
					'~\[list=ol\]~is',
					'~\[ot\](.+?)\[\/ot\]~is',
				),
				array(
					'[list type=lower-alpha]',
					'$1',
				),
				trim($row['body'])
			);

			$rows[] = $row;
		}

		return $rows;
	}

	public function preparseMessages($originalRows)
	{
		$rows = array();
		foreach ($originalRows as $row)
		{
			$row['body'] = preg_replace(
				array(
					'~\[list=ol\]~is',
					'~\[ot\](.+?)\[\/ot\]~is',
				),
				array(
					'[list type=lower-alpha]',
					'$1',
				),
				trim($row['body'])
			);
			if (!empty($row['modified_name']))
			{
				$editdata = explode("\t", $row['modified_name']);
				$row['modified_name'] = $editdata[0];
				$row['modified_time'] = $editdata[1];
			}
			else
				$row['modified_time'] = 0;

			$row['id_member'] = (int) $row['id_member'];

			if(empty($row['poster_name']))
				$row['poster_name'] = 'Guest';

			if(empty($row['poster_email']))
				$row['poster_email'] = '';

			$rows[] = $row;
		}

		return $rows;
	}

	public function preparsePm($originalRows)
	{
		$rows = array();
		foreach ($originalRows as $row)
		{
			$row['body'] = preg_replace(
				array(
					'~\[list=ol\]~is',
					'~\[ot\](.+?)\[\/ot\]~is',
				),
				array(
					'[list type=lower-alpha]',
					'$1',
				),
				trim($row['body'])
			);

			$rows[] = $row;
		}

		return $rows;
	}

	public function preparsePolls($originalRows)
	{
		$rows = array();
		foreach ($originalRows as $row)
		{
			$row['id_member'] = (int) $row['id_member'];

			$rows[] = $row;
		}

		return $rows;
	}

	public function preparsePolloptions($originalRows)
	{
		$rows = array();
		foreach ($originalRows as $row)
		{
			$request = $this->db->query("
				SELECT count(*)
				FROM {$from_prefix}votes
				WHERE aid = " . $row['id_choice']);

			list ($count) = $this->db->fetch_row($request);
			$this->db->free_result($request);
			$row['votes'] = $count;

			$rows[] = $row;
		}

		return $rows;
	}

	public function preparseAttachments($originalRows)
	{
		$rows = array();
		foreach ($originalRows as $row)
		{
			$source = $this->config->path_from . '/uploads/topics/' . $row['filename'];

			$rows[] = array(
				'id_attach' => 0,
				'size' => filesize($source),
				'filename' => $row['filename'],
				'file_hash' => '',
				'id_msg' => $row['id_msg'],
				'downloads' => $row['downloads'],
			);
		}

		return $rows;
	}

	public function preparseAvatars($originalRows)
	{
		$rows = array();
		foreach ($originalRows as $row)
		{
			$file = explode('/', $row['filenamepath']);
			$row['filename'] = end($file);

			$source = dirname($this->config->path_from . '/' . $row['filenamepath']);

			$rows[] = array(
				'id_member' => $row['id_member'],
				'filename' => $row['filename'],
				'full_path' => $source,
			);
		}

		return $rows;
	}
}