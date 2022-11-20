<?php
/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD https://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0
 */

/**
 * Class SMF2_1
 */
class SMF2_1 extends Importers\AbstractSourceImporter
{
	protected $setting_file = '/Settings.php';

	protected $smf_attach_folders = null;

	public function getName()
	{
		return 'SMF 2.1';
	}

	public function getVersion()
	{
		return 'ElkArte 1.1';
	}

	public function setDefines()
	{
		if (!defined('SMF'))
		{
			define('SMF', 1);
		}
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
				SELECT 
				    value
				FROM {$from_prefix}settings
				WHERE variable='attachmentUploadDir';");
			list ($smf_attachments_dir) = $this->db->fetch_row($request);

			$this->smf_attach_folders = @json_decode($smf_attachments_dir, true);

			if (!is_array($this->smf_attach_folders))
			{
				$this->smf_attach_folders = array(1 => $smf_attachments_dir);
			}
		}

		return $this->smf_attach_folders;
	}

	/**
	 * Import likes from the 2.1 table
	 *
	 * @return array
	 */
	public function fetchLikes()
	{
		$from_prefix = $this->config->from_prefix;

		$request = $this->db->query("
			SELECT 
			    l.id_member, l.content_id AS id_msg, m.id_member AS id_poster, l.like_time AS like_timestamp
			FROM {$from_prefix}user_likes AS l
				INNER JOIN {$from_prefix}messages AS m ON (m.id_msg = l.content_id)
			WHERE content_type = 'msg'");
		$return = array();
		while ($row = $this->db->fetch_assoc($request))
		{
			$return[] = array(
				'id_member' => $row['id_member'],
				'id_msg' => $row['id_msg'],
				'id_poster' => $row['id_poster'],
				'like_timestamp' => $row['like_timestamp'],
			);
		}
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
	static $smf_folders = null;

	// We need to know where the attachments are located
	if ($smf_folders === null)
	{
		$request = $db->query("
			SELECT 
			    value
			FROM {$from_prefix}settings
			WHERE variable='attachmentUploadDir';");
		list ($smf_attachments_dir) = $db->fetch_row($request);

		$smf_folders = @json_decode($smf_attachments_dir, true);

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
		$source_file = $row['id_attach'] . '_' . $row['file_hash'] . '.dat';
	}

	// Copy it over
	copy_file($smf_attachments_dir . '/' . $source_file, $attachmentUploadDir . '/' . $row['id_attach'] . '_' . $row['file_hash'] . '.elk');
}

/**
 * This function is called via the preparse setting in the XML importer, imedialty after the query
 *
 * @param array $row
 * @param \OpenImporter\Database $db
 * @param string $from_prefix
 */
function cust_fields(&$row, $db, $from_prefix)
{
	// We need to grab personal_text from smf members and
	// cust_gender and cust_loca from themes
	$request = $db->query("
		SELECT 
		    variable, value
		FROM {$from_prefix}themes
		WHERE id_member = $row[id_member]");

	while ($profile = $db->fetch_assoc($request))
	{
		if ($profile['variable'] === 'cust_loca')
		{
			$row['location'] = $profile['value'];
		}

		if ($profile['variable'] === 'cust_gender')
		{
			if ($profile['value'] === 'Male')
			{
				$row['gender'] = 1;
			}

			if ($profile['value'] === 'Female')
			{
				$row['gender'] = 2;
			}
		}
	}
	$db->free_result($request);
}

/**
 * Partial support smf 2.1 smileys
 *
 * This function is called via the preparse setting in the XML importer.  It allows
 * one to manipulate the query result.  Here we fill in the filename to the row result
 *
 * @param array $row
 * @param \OpenImporter\Database $db
 * @param string $from_prefix
 */
function smiley_mess(&$row, $db, $from_prefix)
{
	// We need to grab the filename from the smiley_files and "pick" an extension
	$request = $db->query("
		SELECT 
		    id_smiley, smiley_set, filename
		FROM {$from_prefix}smiley_files
		WHERE id_smiley = $row[id_smiley]");

	while ($smile = $db->fetch_assoc($request))
	{
		// The name will be right, but the extension may not be ... but the support
		// for smileys is too different
		$row['filename'] = $smile['filename'];
		break;
	}
	$db->free_result($request);
}

function dtoptoip(&$row, $db, $from_prefix)
{
	$row['member_ip'] = inet_dtop($row['member_ip']);
	$row['member_ip2'] = inet_dtop($row['member_ip2']);
}
