<?php
/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD https://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0
 */

/**
 * Class SMF2_0
 */
class SMF2_0 extends Importers\AbstractSourceImporter
{
	protected $setting_file = '/Settings.php';

	protected $smf_attach_folders = null;

	protected $_is_nibogo_like = null;

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
		if (!defined('SMF'))
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
	 * Import likes from one of the known addons
	 *
	 * @return array
	 */
	public function fetchLikes()
	{
		if ($this->isNibogo())
			return $this->fetchNibogo();
		else
			return $this->fetchIllori();
	}

	/**
	 * Nibogo version of likes
	 *
	 * @return array
	 */
	protected function fetchNibogo()
	{
		$from_prefix = $this->config->from_prefix;

		$request = $this->db->query("
			SELECT l.id_member, t.id_first_msg, t.id_member_started
			FROM {$from_prefix}likes
				INNER JOIN {$from_prefix}topics AS t ON (t.id_topic = l.id_topic)");
		$return = array();
		while ($row = $this->db->fetch_assoc($request))
			$return[] = array(
				'id_member' => $row['id_member'],
				'id_msg' => $row['id_first_msg'],
				'id_poster' => $row['id_member_started'],
				'like_timestamp' => 0,
			);
		$this->db->free_result($request);

		return $return;
	}

	/**
	 * Illori version of likes
	 *
	 * @return array
	 */
	protected function fetchIllori()
	{
		$from_prefix = $this->config->from_prefix;

		$request = $this->db->query("
			SELECT l.id_member, l.id_message, m.id_member as id_poster
			FROM {$from_prefix}likes AS l
				INNER JOIN {$from_prefix}messages AS m ON (m.id_msg = l.id_message)");
		$return = array();
		while ($row = $this->db->fetch_assoc($request))
			$return[] = array(
				'id_member' => $row['id_member'],
				'id_msg' => $row['id_message'],
				'id_poster' => $row['id_poster'],
				'like_timestamp' => 0,
			);
		$this->db->free_result($request);

		return $return;
	}

	/**
	 * Figure out which likes system may be in use
	 *
	 * @return bool|null
	 */
	protected function isNibogo()
	{
		$from_prefix = $this->config->from_prefix;

		if ($this->_is_nibogo_like !== null)
			return $this->_is_nibogo_like;

		$request = $this->db->query("
			SHOW COLUMNS
			FROM {$from_prefix}likes");
		while ($row = $this->db->fetch_assoc($request))
		{
			// This is Nibogo
			if ($row['Field'] == 'id_topic')
			{
				$this->_is_nibogo_like = true;
				return $this->_is_nibogo_like;
			}
		}

		// Not Nibogo means Illori
		$this->_is_nibogo_like = false;
		return $this->_is_nibogo_like;
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

		if (!is_array($smf_folders))
			$smf_folders = array(1 => $smf_attachments_dir);
	}

	// If something is broken, better try to account for it as well.
	if (isset($smf_folders[$row['id_folder']]))
		$smf_attachments_dir = $smf_folders[$row['id_folder']];
	else
		$smf_attachments_dir = $smf_folders[1];

	// Missing the file hash ... create one
	if (empty($row['file_hash']))
	{
		$row['file_hash'] = createAttachmentFileHash($row['filename']);
		$source_file = $row['filename'];
	}
	else
		$source_file = $row['id_attach'] . '_' . $row['file_hash'];

	// Copy it over
	copy_file($smf_attachments_dir . '/' . $source_file, $attachmentUploadDir . '/' . $row['id_attach'] . '_' . $row['file_hash'] . '.elk');
}
