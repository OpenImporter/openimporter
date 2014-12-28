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
class mybb16 extends AbstractSourceImporter
{
	protected $setting_file = '/inc/config.php';

	public function getName()
	{
		return 'MyBB 1.6';
	}

	public function getVersion()
	{
		return '1.0';
	}

	public function getPrefix()
	{
		// @todo Convert the use of globals to a scan of the file or something similar.
		global $config;

		return '`' . $this->getDbName() . '`.' . $config['database']['table_prefix'];
	}

	public function getDbName()
	{
		// @todo Convert the use of globals to a scan of the file or something similar.
		global $config;

		return $config['database']['database'];
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
			if (!preg_match('/\d{4}-\d{2}-\d{2}/', $row['birthdate']))
				$row['birthdate'] = '0001-01-01';

			$rows[] = $row;
		}

		return $rows;
	}

	public function preparsePolloptions($originalRows)
	{
		$rows = array();
		foreach ($originalRows as $row)
		{
			$options = explode('||~|~||', $row['opt']);
			$votes = explode('||~|~||', $row['votes']);

			$id_poll = $row['id_poll'];
			for ($i = 0, $n = count($options); $i < $n; $i++)
			{
				$rows[] = array(
					'id_poll' => $id_poll,
					'id_choice' => ($i + 1),
					'label' => '"'. addslashes($options[$i]). '"',
					'votes' => @$votes[$i],
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
			if(empty($row['from_name']))
				$row['from_name'] = 'Guest';

			$rows[] = $row;
		}
		return $rows;
	}

	public function preparseAttachments($originalRows)
	{
		$rows = array();

		foreach ($originalRows as $row)
		{
			if (!isset($mybb_attachment_dir))
			{
				$result = $db->query("
					SELECT value
					FROM {$this->config->source->from_prefix}settings
					WHERE name = 'uploadspath'
					LIMIT 1");
				list ($mybb_attachment_dir) = $db->fetch_row($result);
				$db->free_result($result);

				$mybb_attachment_dir = $this->config->path_from . ltrim($mybb_attachment_dir, '.');
			}

			//create some useful shortcuts, we start with images..
			$ext = strtolower(substr(strrchr($row['filename'], '.'), 1));
			if (!in_array($ext, array('jpg', 'jpeg', 'gif', 'png', 'bmp')))
				$ext = '';

			$source = $mybb_attachment_dir . '/' . $row['attachname'];
			$width = 0;
			$height = 0;

			// Is image? we need a thumbnail
			if (!empty($ext))
			{
				list ($width, $height) = getimagesize($source);
				if(empty($width))
				{
					$width = 0;
					$height = 0;
				}
			}

			//prepare our insert
			$rows[] = array(
				'id_attach' => $id_attach,
				'id_thumb' => 0,
				'id_msg' => $row['id_msg'],
				'id_member' => 0, //@todo check
				'attachment_type' => 0,
				'filename' => $row['filename'],
				'file_hash' => '',
				'size' => filesize($source),
				'downloads' => $row['downloads'],
				'width' => $width,
				'height' => $height,
				'fileext' => $ext,
				'mime_type' => '',
				'id_folder' => 0,
				'full_path' => $mybb_attachment_dir,
			);
		}

		return $rows;
	}

	public function preparseAvatars($originalRows)
	{
		$rows = array();
		foreach ($originalRows as $row)
		{
			$source_name = basename(strtok(ltrim($row['filename'], '.'), '?'));
			$full_path = $this->config->path_from . DIRECTORY_SEPARATOR . dirname($row['filename']);

			$rows[] = array(
				'id_member' => $row['id_member'],
				'filename' => $source_name,
				'full_path' => $full_path,
			);
		}

		return $rows;
	}
}