<?php

class elkarte10_to_smf20
{
	/**
	 * we are done :)
	 *
	 * @global Database $db
	 * @global type $boardurl
	 * @return boolean
	 */
	public function doStep3()
	{
		global $db, $boardurl;

		$to_prefix = $this->to_prefix;

		// add some importer information.
		$this->db->query("
			REPLACE INTO {$to_prefix}settings (variable, value)
				VALUES ('import_time', " . time() . "),
					('enable_password_conversion', '1'),
					('imported_from', '" . $_SESSION['import_script'] . "')");

		$writable = (is_writable(dirname(__FILE__)) && is_writable(__FILE__));

		$this->use_template = 'step3';
		$this->params_template = array($this->xml->general->name, $boardurl, $writable);

		unset ($_SESSION['import_steps'], $_SESSION['import_progress'], $_SESSION['import_overall']);
		return true;
	}

	/**
	 * helper function for old attachments
	 *
	 * @param string $filename
	 * @param int $attachment_id
	 * @return string
	 */
	protected function getLegacyAttachmentFilename($filename, $attachment_id)
	{
		// Remove special accented characters - ie. sí (because they won't write to the filesystem well.)
		$clean_name = strtr($filename, 'ŠŽšžŸÀÁÂÃÄÅÇÈÉÊËÌÍÎÏÑÒÓÔÕÖØÙÚÛÜÝàáâãäåçèéêëìíîïñòóôõöøùúûüýÿ', 'SZszYAAAAAACEEEEIIIINOOOOOOUUUUYaaaaaaceeeeiiiinoooooouuuuyy');
		$clean_name = strtr($clean_name, array('Þ' => 'TH', 'þ' => 'th', 'Ð' => 'DH', 'ð' => 'dh', 'ß' => 'ss', 'Œ' => 'OE', 'œ' => 'oe', 'Æ' => 'AE', 'æ' => 'ae', 'µ' => 'u'));

		// Get rid of dots, spaces, and other weird characters.
		$clean_name = preg_replace(array('/\s/', '/[^\w_\.\-]/'), array('_', ''), $clean_name);
			return $attachment_id . '_' . strtr($clean_name, '.', '_') . md5($clean_name);
	}

	/**
	 *
	 * Get the id_member associated with the specified message.
	 * @global type $to_prefix
	 * @global type $db
	 * @param type $messageID
	 * @return int
	 */
	protected function getMsgMemberID($messageID)
	{
		global $to_prefix, $db;

		// Find the topic and make sure the member still exists.
		$result = $this->db->query("
			SELECT IFNULL(mem.id_member, 0)
			FROM {$to_prefix}messages AS m
			LEFT JOIN {$to_prefix}members AS mem ON (mem.id_member = m.id_member)
			WHERE m.id_msg = " . (int) $messageID . "
			LIMIT 1");
		if ($this->db->num_rows($result) > 0)
			list ($memberID) = $this->db->fetch_row($result);
		// The message doesn't even exist.
		else
			$memberID = 0;
		$this->db->free_result($result);

			return $memberID;
	}
}

/**
 * helper function to create an encrypted attachment name
 *
 * @param string $filename
 * @return string
 */
function createAttachmentFilehash($filename)
{
	return sha1(md5($filename . time()) . mt_rand());
}

/**
 * function copy_smileys is used to copy smileys from a source to destination.
 * @param type $source
 * @param type $dest
 * @return type
 */
function copy_smileys($source, $dest)
{
	if (!is_dir($source) || !($dir = opendir($source)))
		return;

	while ($file = readdir($dir))
	{
		if ($file == '.' || $file == '..')
			continue;

		// If we have a directory create it on the destination and copy contents into it!
		if (is_dir($source . DIRECTORY_SEPARATOR . $file))
		{
			if (!is_dir($dest))
				@mkdir($dest . DIRECTORY_SEPARATOR . $file, 0777);
			copy_dir($source . DIRECTORY_SEPARATOR . $file, $dest . DIRECTORY_SEPARATOR . $file);
		}
		else
		{
			if (!is_dir($dest))
				@mkdir($dest . DIRECTORY_SEPARATOR . $file, 0777);
			copy($source . DIRECTORY_SEPARATOR . $file, $dest . DIRECTORY_SEPARATOR . $file);
		}
	}
	closedir($dir);
}