<?php

class SMF2_0
{
	public function getName()
	{
		return 'SMF2_0';
	}

	public function getVersion()
	{
		return 'ElkArte 1.0';
	}

	public function setDefines()
	{
		define('SMF', 1);
	}

	public function loadSettings($path)
	{
		// Error silenced in case of odd server configurations (open_basedir mainly)
		if (@file_exists($path . '/Settings.php'))
		{
			require_once($path . '/Settings.php');
			return true;
		}
		else
			return false;
	}

	public function getPrefix()
	{
		global $db_name, $db_prefix;

		return '`' . $db_name . '`.' . $db_prefix;
	}

	public function getTableTest()
	{
		return 'members';
	}
}

function moveAttachment($row, $db, $from_prefix, $attachmentUploadDir)
{
	static $smf_folders = null;

	if ($smf_folders === null
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

	$smf_attachments_dir = $smf_folders[$row['id_folder']];

	if (empty($row['file_hash']))
	{
		$row['file_hash'] = createAttachmentFileHash($row['filename']);
		$source_file = $row['filename'];
	}
	else
		$source_file = $row['id_attach'] . '_' . $row['file_hash'];

	copy_file($smf_attachments_dir . '/' . $source_file, $attachmentUploadDir . '/' . $row['id_attach'] . '_' . $row['file_hash'] . '.elk');
}