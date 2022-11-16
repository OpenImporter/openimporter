<?php
/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD https://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0
 */

/**
 * Class Wedge1_0
 */
class Wedge1_0 extends Importers\AbstractSourceImporter
{
	protected $setting_file = '/Settings.php';

	protected $smf_attach_folders = null;

	public function getName()
	{
		return 'Wedge 1.0';
	}

	public function getVersion()
	{
		return 'ElkArte 1.0';
	}

	public function setDefines()
	{
		if (!defined('WEDGE'))
			define('WEDGE', 1);
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

	public function fetchSetting($name)
	{
		static $content = null;

		if ($content === null)
			$content = file_get_contents($this->path . $this->setting_file);

		$match = array();
		preg_match('~\$' . $name . '\s*=\s*\'(.*?)\';~', $content, $match);

		return isset($match[1]) ? $match[1] : '';
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
				$this->smf_attach_folders = array(1 => $smf_attachments_dir);
		}

		return $this->smf_attach_folders;
	}

	/**
	 * Import likes from the Wedge table
	 *
	 * @return array
	 */
	public function fetchLikes()
	{
		$from_prefix = $this->config->from_prefix;

		$request = $this->db->query("
			SELECT l.id_member, l.id_content AS id_msg, m.id_member AS id_poster, l.like_time AS like_timestamp
			FROM {$from_prefix}likes AS l
				INNER JOIN {$from_prefix}messages AS m ON (m.id_msg = l.id_content)
			WHERE content_type = 'post'");
		$return = array();
		while ($row = $this->db->fetch_assoc($request))
			$return[] = array(
				'id_member' => $row['id_member'],
				'id_msg' => $row['id_msg'],
				'id_poster' => $row['id_poster'],
				'like_timestamp' => $row['like_timestamp'],
			);
		$this->db->free_result($request);

		return $return;
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
	static $wedge_folders = null;

	// We need to know where the attachments are located
	if ($wedge_folders === null)
	{
		$request = $db->query("
			SELECT value
			FROM {$from_prefix}settings
			WHERE variable='attachmentUploadDir';");
		list ($wedge_attachments_dir) = $db->fetch_row($request);

		$wedge_folders = @unserialize($wedge_attachments_dir);

		if (!is_array($wedge_folders))
			$wedge_folders = array(1 => $wedge_attachments_dir);
	}

	// If something is broken, better try to account for it as well.
	if (isset($wedge_folders[$row['id_folder']]))
		$wedge_attachments_dir = $wedge_folders[$row['id_folder']];
	else
		$wedge_attachments_dir = $wedge_folders[1];

	// Missing the file hash ... create one
	if (empty($row['file_hash']))
	{
		$row['file_hash'] = createAttachmentFileHash($row['filename']);
		$source_file = $row['filename'];
	}
	else
		$source_file = $row['id_attach'] . '_' . $row['file_hash'] . '.ext';

	// Copy it over
	copy_file($wedge_attachments_dir . '/' . $source_file, $attachmentUploadDir . '/' . $row['id_attach'] . '_' . $row['file_hash'] . '.elk');
}
