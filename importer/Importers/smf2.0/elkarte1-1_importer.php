<?php
/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD https://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0
 */

/**
 * Class elkarte1_1
 */
class elkarte1_1 extends Importers\AbstractSourceImporter
{
	protected $setting_file = '/Settings.php';

	protected $smf_attach_folders = null;

	public function getName()
	{
		return 'elkarte1_1';
	}

	public function getVersion()
	{
		return 'SMF 2.0';
	}

	public function setDefines()
	{

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

	public function fetchSetting($name)
	{
		static $content = null;

		if ($content === null)
		{
			$content = file_get_contents($this->path . '/Settings.php');
		}

		$match = array();
		preg_match('~\$' . $name . '\s*=\s*\'(.*?)\';~', $content, $match);

		return isset($match[1]) ? $match[1] : '';
	}

	public function getTableTest()
	{
		return 'members';
	}

	/**
	 * Read the attachment directory structure from the source db
	 *
	 * @return array|null
	 */
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
			{
				$this->smf_attach_folders = array(1 => $smf_attachments_dir);
			}
		}

		return $this->smf_attach_folders;
	}
}

/**
 * Copy attachments from the source to our destination
 *
 * @param array $row
 * @param \OpenImporter\Database $db
 * @param string $from_prefix
 * @param string $attachmentUploadDir
 */
function moveAttachment(&$row, $db, $from_prefix, $attachmentUploadDir)
{
	static $smf_folders = null;


	// We need to know where the attachments are located
	if ($smf_folders === null)
	{
		$request = $db->query("
			SELECT value
			FROM {$from_prefix}settings
			WHERE variable='attachmentUploadDir';");
		list ($smf_attachments_dir) = $db->fetch_row($request);

		$smf_folders = @unserialize($smf_attachments_dir);

		//	print_r($smf_folders);

		if (!is_array($smf_folders))
		{
			$smf_folders = array(1 => $smf_attachments_dir);
		}
	}


	// If something is broken, better try to account for it as well.
	if (isset($smf_folders[$row['id_folder']]))
	{
		$smf_attachments_dir = $smf_folders[$row['id_folder']];
	}
	else
	{
		$smf_attachments_dir = $smf_folders[1];
	}

	// Missing the file hash ... create one
	if (empty($row['file_hash']))
	{
		$row['file_hash'] = createAttachmentFileHash($row['filename']);
		$source_file = $row['filename'];
	}
	else
	{
		$source_file = $row['id_attach'] . '_' . $row['file_hash'];
	}

	// Copy it over
	if (file_exists($smf_attachments_dir . '/' . $source_file . '.elk'))
	{
		copy_file($smf_attachments_dir . '/' . $source_file . '.elk', $attachmentUploadDir . '/' . $row['id_attach'] . '_' . $row['file_hash']);
	}

	if (file_exists($smf_attachments_dir . '/' . $source_file . '.elk_thumb'))
	{
		copy_file($smf_attachments_dir . '/' . $source_file . '.elk_thumb', $attachmentUploadDir . '/' . $row['id_attach'] . '_' . $row['file_hash'] . '_thumb');
	}
}
