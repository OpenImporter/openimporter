<?php
/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0
 */

class SMF1_1 extends Importers\AbstractSourceImporter
{
	protected $setting_file = '/Settings.php';

	protected $smf_attach_folders = null;

	public function getName()
	{
		return 'SMF1_1';
	}

	public function getVersion()
	{
		return 'ElkArte 1.0';
	}

	public function setDefines()
	{
		define('SMF', 1);
	}

	public function getPrefix()
	{
		$db_name = $this->getDbName();
		$db_prefix = $this->fetchSetting('db_prefix');
		return '`' . $db_name . '`.' . $db_prefix;
	}

	public function getDbName()
	{
		return $this->fetchSetting('db_name');
	}

	public function getTableTest()
	{
		return 'members';
	}

	protected function fetchSetting($name)
	{
		static $content = null;

		if ($content === null)
			$content = file_get_contents($this->path . '/Settings.php');

		$match = array();
		preg_match('~\$' . $name . '\s*=\s*\'(.*?)\';~', $content, $match);

		return isset($match[1]) ? $match[1] : '';
	}

	public function getAttachmentDirs()
	{
		if ($this->smf_attach_folders === null)
		{
			$from_prefix = $this->config->from_prefix;

			$request = $this->db->query("
				SELECT value
				FROM {$from_prefix}settings
				WHERE variable='attachmentUploadDir';");
			list ($smf_attachments_dir) = $this->db->fetch_row($request);

			$this->smf_attach_folders = @unserialize($smf_attachments_dir);

			if (!is_array($this->smf_attach_folders))
				$this->smf_attach_folders = array(1 => $smf_attachments_dir);
		}

		return $this->smf_attach_folders;
	}
}

function moveAttachment(&$row, $db, $from_prefix, $attachmentUploadDir)
{
	static $smf_folders = null;

	if ($smf_folders === null)
	{
		$request = $db->query("
			SELECT value
			FROM {$from_prefix}settings
			WHERE variable='attachmentUploadDir';");
		list ($smf_attachments_dir) = $db->fetch_row($request);

		$smf_folders = @unserialize($smf_attachments_dir);
		if (!is_array($smf_folders))
			$smf_folders = array(1 => $smf_attachments_dir);
	}

	// If something is broken, better try to account for it as well.
	if (isset($row['id_folder']) && isset($smf_folders[$row['id_folder']]))
		$smf_attachments_dir = $smf_folders[$row['id_folder']];
	else
		$smf_attachments_dir = $smf_folders[1];

	if (empty($row['file_hash']))
	{
		$row['file_hash'] = createAttachmentFileHash($row['filename']);
		$source_file = $row['filename'];
	}
	else
		$source_file = $row['id_attach'] . '_' . $row['file_hash'];

	if (empty($row['mime_type']))
	{
		$fileext = '';
		$mimetype = '';
		$is_thumb = false;

		if (preg_match('/\.(jpg|jpeg|gif|png)(_thumb)?$/i',$row['filename'],$m))
		{
			$fileext = strtolower($m[1]);
			$is_thumb = !empty($m[2]);

			if (empty($row['mime_type']))
			{
				// AFAIK, all thumbnails got created as PNG
				if ($is_thumb) $mimetype = 'image/png';
				elseif ($fileext == 'jpg') $mimetype = 'image/jpeg';
				else $mimetype = 'image/'.$fileext;
			}
		}
		else if (preg_match('/\.([a-z][a-z0-9]*)$/i',$row['filename'],$m))
		{
			$fileext = strtolower($m[1]);
		}

		if (empty($row['fileext'])) $row['fileext'] = $fileext;

		// try using getimagesize to calculate the mime type, otherwise use the $mimetype set from above
		$size = @getimagesize($filename);

		$row['mime_type'] = empty($size['mime']) ? $mimetype : $size['mime'];
	}

	copy_file($smf_attachments_dir . '/' . $source_file, $attachmentUploadDir . '/' . $row['id_attach'] . '_' . $row['file_hash'] . '.elk');
}
