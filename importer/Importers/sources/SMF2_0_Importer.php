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
		$return = false;

		$request = $this->db->query("
			SHOW COLUMNS
			FROM {$this->config->from_prefix}likes");
		while ($row = $this->db->fetch_assoc($request))
		{
			// This is Nibogo
			if ($row['Field'] == 'id_topic')
			{
				$return = true;
				break;
			}
		}
		$this->db->free_result($request);

		return $return;
	}

	protected function isThankYouMod()
	{
		$db_name_str = $this->config->source->getDbName();

		$result = $this->db->query("
			SHOW TABLES
			FROM `{$db_name_str}`
			LIKE '{$table}'");

		if (!($result instanceof \Doctrine\DBAL\Driver\Statement) || $this->db->num_rows($result) == 0)
			return false;
		else
			return true;
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
			$row['system_filename'] = $row['id_attach'] . '_' . $row['file_hash'];

			$rows[] = $row;
		}

		return $rows;
	}

	public function preparseTopics($originalRows)
	{
		$rows = array();
		foreach ($originalRows as $row)
		{
			if ($row['approved'] == 0)
				$row['approved'] = 1;
			else
				$row['approved'] = 0;

			$rows[] = $row;
		}

		return $rows;
	}

	public function preparseMessages($originalRows)
	{
		$rows = array();
		foreach ($originalRows as $row)
		{
			if ($row['approved'] == 0)
				$row['approved'] = 1;
			else
				$row['approved'] = 0;

			$rows[] = $row;
		}

		return $rows;
	}

	public function codeLikes()
	{
		if ($this->isThankYouMod())
		{
			return false;
		}
		else
		{
			return $this->fetchLikes();
		}
	}
}