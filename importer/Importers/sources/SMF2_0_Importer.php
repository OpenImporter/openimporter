<?php
/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 2.0 Alpha
 */

namespace OpenImporter\Importers\sources;

class SMF2_0_Importer extends \OpenImporter\Importers\AbstractSourceSmfImporter
{
	protected $smf_attach_folders = null;

	protected $_is_nibogo_like = null;

	public function getName()
	{
		return 'SMF2_0';
	}

	public function getVersion()
	{
		return '1.0';
	}

	public function getAttachmentDirs()
	{
		if ($this->smf_attach_folders === null)
		{
			$request = $this->db->query("
				SELECT value
				FROM {$this->config->from_prefix}settings
				WHERE variable='attachmentUploadDir';");
			list ($smf_attachments_dir) = $this->db->fetch_row($request);

			$this->smf_attach_folders = @unserialize($smf_attachments_dir);

			if (!is_array($this->smf_attach_folders))
				$this->smf_attach_folders = array(1 => $smf_attachments_dir);
		}

		return $this->smf_attach_folders;
	}

	public function getAttachDir($row)
	{
		if ($this->smf_attach_folders === null)
			$this->getAttachmentDirs();

		if (!empty($row['id_folder']) && !empty($this->smf_attach_folders[$row['id_folder']]))
			return $this->smf_attach_folders[$row['id_folder']];
		else
			return $this->smf_attach_folders[1];
	}

	public function fetchLikes()
	{
		if ($this->isNibogo())
			return $this->fetchNibogo();
		else
			return $this->fetchIllori();
	}

	protected function fetchNibogo()
	{
		$request = $this->db->query("
			SELECT l.id_member, t.id_first_msg, t.id_member_started
			FROM {$this->config->from_prefix}likes
				INNER JOIN {$this->config->from_prefix}topics AS t ON (t.id_topic = l.id_topic)");
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

	protected function fetchIllori()
	{
		$request = $this->db->query("
			SELECT l.id_member, l.id_message, m.id_member as id_poster
			FROM {$this->config->from_prefix}likes AS l
				INNER JOIN {$this->config->from_prefix}messages AS m ON (m.id_msg = l.id_message)");
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

	protected function isNibogo()
	{
		if ($this->_is_nibogo_like !== null)
			return $this->_is_nibogo_like;

		$request = $this->db->query("
			SHOW COLUMNS
			FROM {$this->config->from_prefix}likes");
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

	/**
	 * From here on, all the methods are needed helper for the conversion
	 */
	public function preparseAttachments($originalRows)
	{
		$rows = array();
		foreach ($originalRows as $row)
		{
			$row['full_path'] = $this->getAttachDir($row);

			$rows[] = $row;
		}

		return $rows;
	}

	public function codeLikes()
	{
		return $this->fetchLikes();
	}
}