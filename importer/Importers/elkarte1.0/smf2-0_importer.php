<?php
/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Alpha
 */

class SMF2_0 extends AbstractSourceImporter
{
	protected $setting_file = '/Settings.php';

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

	public function getPrefix()
	{
		$db_name = $this->fetchSetting('db_name');
		$db_prefix = $this->fetchSetting('db_prefix');
		return '`' . $db_name . '`.' . $db_prefix;
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
}

function moveAttachment($row, $db, $from_prefix, $attachmentUploadDir)
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

	// If something is broken, better account for it as well.
	if (isset($smc_folders[$row['id_folder']]))
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

	copy_file($smf_attachments_dir . '/' . $source_file, $attachmentUploadDir . '/' . $row['id_attach'] . '_' . $row['file_hash'] . '.elk');
}