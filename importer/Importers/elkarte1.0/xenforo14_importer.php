<?php
/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD https://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0
 */

/**
 * Class XenForo1_4
 */
class XenForo1_4 extends Importers\AbstractSourceImporter
{
	protected $setting_file = '/library/config.php';

	public function getName()
	{
		return 'XenForo 1.4';
	}

	public function getVersion()
	{
		return 'ElkArte 1.0';
	}

	public function getPrefix()
	{
		$db_name = $this->getDbName();

		// Seems to only be xf_ in recent versions
		$db_prefix = 'xf_';

		return '`' . $db_name . '`.' . $db_prefix;
	}

	public function getDbName()
	{
		return $this->fetchSetting('dbname');
	}

	public function getTableTest()
	{
		return 'user';
	}

	protected function fetchSetting($name)
	{
		static $content = null;

		if ($content === null)
		{
			$content = file_get_contents($this->path . $this->setting_file);
		}

		$match = array();
		preg_match('~\$config\[\'db\'\]\[\'' . $name . '\'\]\s*=\s*\'(.*?)\';~', $content, $match);

		return $match[1] ?? '';
	}

	public function xen_copy_files($dir, $row, $id_attach, $destination_path, $thumb = false)
	{
		// Extra details
		list($ext, $basename, $mime_type) = attachment_type($row['filename']);

		// Prep for the copy
		$file = xen_attach_filename($row) . ($thumb ? '.' . $ext : '.data');
		$source = $dir . '/' . $file;
		$file_hash = createAttachmentFileHash($file);
		$destination = $destination_path . '/' . $id_attach . '_' . $file_hash . '.elk';
		$type = 0;

		// Copy it over
		copy_file($source, $destination);

		// If its an image, then make sure it has legit width/height
		if (!empty($ext))
		{
			list ($width) = getimagesize($destination);
			if (!empty($width))
			{
				$type = ($thumb) ? 3 : 0;
			}
		}

		// Prepare our insert
		return array(
			'id_attach' => $id_attach,
			'id_thumb' => !$thumb && !empty($row['thumbnail_width']) ? ++$id_attach : 0,
			'size' => file_exists($destination) ? filesize($destination) : 0,
			'filename' => $basename . '.' . ($thumb ? $ext . '_thumb' : $ext),
			'file_hash' => $file_hash,
			'file_ext' => $ext,
			'mime_type' => $mime_type,
			'attachment_type' => $type,
			'id_msg' => $row['content_id'],
			'downloads' => $row['view_count'],
			'width' => $thumb ? $row['thumbnail_width'] : $row['width'],
			'height' => $thumb ? $row['thumbnail_height'] : $row['height']
		);
	}

	public function fetchLikes()
	{
		$from_prefix = $this->config->from_prefix;

		$request = $this->db->query("
			SELECT
				likes, like_users, post_id, user_id, post_date
			FROM {$from_prefix}post
			WHERE likes != '0'");
		$return = array();
		while ($row = $this->db->fetch_assoc($request))
		{
			$likers = unserialize($row['like_users']);
			foreach ($likers as $likes)
			{
				$return[] = array(
					'id_member' => $likes['user_id'],
					'id_msg' => $row['post_id'],
					'id_poster' => $row['user_id'],
					'like_timestamp' => $row['post_date'],
				);
			}
		}

		return $return;
	}
}

// Utility Functions

/**
 * Converts a binary string containing IPv4 back to standard format
 *
 * I'm not really sure what is being stored in the DB but found
 * 7f 00 00 01 for 127.0.0.1 which is a dec2hex(long2ip($ip))
 *
 * @param string $ip IP data
 *
 * @return bool|string
 */
function convertIp($ip)
{
	if (strlen($ip) === 8)
	{
		return long2ip(hexdec($ip));
	}

	if (strlen($ip) === 4)
	{
		$parts = array();
		foreach (str_split($ip) AS $char)
		{
			$parts[] = ord($char);
		}

		return implode('.', $parts);
	}

	if (preg_match('/^[0-9]+$/', $ip))
	{
		return long2ip($ip);
	}

	return '127.0.0.1';
}

/**
 * Return the subdir/filename combo to a given file
 *
 * Does not include the .data, .jpg, etc
 *
 * @param $row
 *
 * @return string
 */
function xen_attach_filename($row)
{
	$subdir = floor($row['data_id'] / 1000);
	return $subdir . "/{$row['data_id']}-{$row['file_hash']}";
}
