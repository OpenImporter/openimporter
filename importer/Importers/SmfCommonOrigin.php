<?php
/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 2.0 Alpha
 *
 * This file contains code based on:
 *
 * Simple Machines Forum (SMF)
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:	BSD, See included LICENSE.TXT for terms and conditions.
 */

namespace OpenImporter\Importers;

/**
 * The class contains code that allows the Importer to obtain settings
 * from softwares that still have an SMF heritage.
 */
abstract class SmfCommonOrigin extends \OpenImporter\Importers\AbstractDestinationImporter
{
	public $attach_extension = '';

	public $id_attach = null;
	public $attachmentUploadDirs = null;
	public $avatarUploadDir = null;
	
	public $scriptname = null;

	public function checkSettingsPath($path)
	{
		$found = file_exists($path . '/Settings.php');

		if ($found && $this->path === null)
			$this->path = $path;

		return $found;
	}

	public function getDestinationURL($path)
	{
		// Cannot find Settings.php?
		if (!$this->checkSettingsPath($path))
			return false;

		// Everything should be alright now... no cross server includes, we hope...
		return $this->fetchSetting('boardurl');
	}

	public function getFormFields($path_to = '', $scriptname = '')
	{
		return array(
			'id' => 'path_to',
			'label' => array('path_to', $scriptname),
			'type' => 'text',
			'default' => htmlspecialchars($path_to),
			'correct' => $this->checkSettingsPath($path_to) ? 'right_path' : 'change_path',
			'validate' => true,
		);
	}

	public function verifyDbPass($pwd_to_verify)
	{
		if ($this->path === null)
			return false;

		$db_passwd = $this->fetchSetting('db_passwd');

		return $db_passwd == $pwd_to_verify;
	}

	public function dbConnectionData()
	{
		if ($this->path === null)
			return false;

		return array(
			'dbname' => $this->fetchSetting('db_name'),
			'user' => $this->fetchSetting('db_user'),
			'password' => $this->fetchSetting('db_passwd'),
			'host' => $this->fetchSetting('db_server'),
			'driver' => $this->fetchDriver(),
		);
	}

	public function getDbPrefix()
	{
		return $this->fetchSetting('db_prefix');
	}

	protected function fetchDriver()
	{
		$type = $this->fetchSetting('db_type');
		$drivers = array(
			'mysql' => 'pdo_mysql',
			'mysqli' => 'pdo_mysql',
			'postgresql' => 'pdo_pgsql',
			'sqlite' => 'pdo_sqlite',
		);

		return isset($drivers[$type]) ? $drivers[$type] : 'pdo_mysql';
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
	 * helper function for old (SMF) attachments and some new ones
	 *
	 * @param string $filename
	 * @param int $attachment_id
	 * @param bool $legacy if true returns legacy SMF file name (default true)
	 * @return string
	 */
	public function getLegacyAttachmentFilename($filename, $attachment_id, $legacy = true)
	{
		// Remove special accented characters - ie. sí (because they won't write to the filesystem well.)
		$clean_name = strtr($filename, 'ŠŽšžŸÀÁÂÃÄÅÇÈÉÊËÌÍÎÏÑÒÓÔÕÖØÙÚÛÜÝàáâãäåçèéêëìíîïñòóôõöøùúûüýÿ', 'SZszYAAAAAACEEEEIIIINOOOOOOUUUUYaaaaaaceeeeiiiinoooooouuuuyy');
		$clean_name = strtr($clean_name, array('Þ' => 'TH', 'þ' => 'th', 'Ð' => 'DH', 'ð' => 'dh', 'ß' => 'ss', 'Œ' => 'OE', 'œ' => 'oe', 'Æ' => 'AE', 'æ' => 'ae', 'µ' => 'u'));

			// Get rid of dots, spaces, and other weird characters.
		$clean_name = preg_replace(array('/\s/', '/[^\w_\.\-]/'), array('_', ''), $clean_name);

		if ($legacy)
		{
			// @todo not sure about that one
			$clean_name = preg_replace('~\.[\.]+~', '.', $clean_name);
			return $attachment_id . '_' . strtr($clean_name, '.', '_') . md5($clean_name);
		}
		else
		{
			return $attachment_id . '_' . strtr($clean_name, '.', '_') . md5($clean_name) . '.' . $this->attach_extension;
		}
	}

	public function specialAttachments($force = false)
	{
		$to_prefix = $this->config->to_prefix;

		if ($force === true || !isset($this->id_attach, $this->attachmentUploadDirs, $this->avatarUploadDir))
		{
			$this->newMaxIdAttach();

			$result = $this->db->query("
				SELECT value
				FROM {$to_prefix}settings
				WHERE variable = 'attachmentUploadDir'
				LIMIT 1");
			list ($attachmentdir) = $this->db->fetch_row($result);
			$attachment_UploadDir = @unserialize($attachmentdir);

			$this->attachmentUploadDirs = !empty($attachment_UploadDir) ? $attachment_UploadDir : array(1 => $attachmentdir);
			foreach ($this->attachmentUploadDirs as $key => $val)
				$this->attachmentUploadDirs[$key] = str_replace('\\', '/', $val);

			$result = $this->db->query("
				SELECT value
				FROM {$to_prefix}settings
				WHERE variable = 'custom_avatar_dir'
				LIMIT 1");
			list ($this->avatarUploadDir) = $this->db->fetch_row($result);
			$this->db->free_result($result);

			if (empty($this->avatarUploadDir))
				$this->avatarUploadDir = null;
			else
				$this->avatarUploadDir = str_replace('\\', '/', $this->avatarUploadDir);
		}
	}

	public function newMaxIdAttach()
	{
		$result = $this->db->query("
			SELECT MAX(id_attach) + 1
			FROM {$this->config->to_prefix}attachments");
		list ($this->id_attach) = $this->db->fetch_row($result);
		$this->db->free_result($result);

		if (empty($this->id_attach))
			$this->id_attach = 1;
	}

	public function getAvatarDir($row)
	{
		if ($this->avatarUploadDir === null)
			return $this->getAttachDir($row);
		else
			return $this->avatarUploadDir;
	}

	public function getAttachDir($row)
	{
		$this->specialAttachments();

		if (!empty($row['id_folder']) && !empty($this->attachmentUploadDirs[$row['id_folder']]))
			return $this->attachmentUploadDirs[$row['id_folder']];
		else
			return $this->attachmentUploadDirs[1];
	}

	public function getAllAttachDirs()
	{
		if ($this->attachmentUploadDirs === null)
			$this->specialAttachments();

		return $this->attachmentUploadDirs;
	}

	/**
	 * Until further notice these methods are for table detection
	 */
	public function tableMembers()
	{
		return '{$to_prefix}members';
	}

	public function tableAttachments()
	{
		return '{$to_prefix}attachments';
	}

	public function tableCategories()
	{
		return '{$to_prefix}categories';
	}

	public function tableCollapsedcats()
	{
		return '{$to_prefix}collapsed_categories';
	}

	public function tableBoards()
	{
		return '{$to_prefix}boards';
	}

	public function tableTopics()
	{
		return '{$to_prefix}topics';
	}

	public function tableMessages()
	{
		return '{$to_prefix}messages';
	}

	public function tablePolls()
	{
		return '{$to_prefix}polls';
	}

	public function tablePolloptions()
	{
		return '{$to_prefix}poll_choices';
	}

	public function tablePollvotes()
	{
		return '{$to_prefix}log_polls';
	}

	public function tablePm()
	{
		return '{$to_prefix}personal_messages';
	}

	public function tablePmrecipients()
	{
		return '{$to_prefix}pm_recipients';
	}

	public function tablePmrules()
	{
		return '{$to_prefix}pm_rules';
	}

	public function tableBoardmods()
	{
		return '{$to_prefix}moderators';
	}

	public function tableMarkreadboards()
	{
		return '{$to_prefix}log_boards';
	}

	public function tableMarkreadtopics()
	{
		return '{$to_prefix}log_topics';
	}

	public function tableMarkread()
	{
		return '{$to_prefix}log_mark_read';
	}

	public function tableNotifications()
	{
		return '{$to_prefix}log_notify';
	}

	public function tableMembergroups()
	{
		return '{$to_prefix}membergroups';
	}

	public function tableGroupdmods()
	{
		return '{$to_prefix}group_moderators';
	}

	public function tablePermissionprofiles()
	{
		return '{$to_prefix}permission_profiles';
	}

	public function tablePermissions()
	{
		return '{$to_prefix}permissions';
	}

	public function tablePermissionboards()
	{
		return '{$to_prefix}board_permissions';
	}

	public function tableSmiley()
	{
		return '{$to_prefix}smileys';
	}

	public function tableStatistics()
	{
		return '{$to_prefix}log_activity';
	}

	public function tableLogactions()
	{
		return '{$to_prefix}log_actions';
	}

	public function tableReports()
	{
		return '{$to_prefix}log_reported';
	}

	public function tableReportscomments()
	{
		return '{$to_prefix}log_reported_comments';
	}

	public function tableSpiderhits()
	{
		return '{$to_prefix}log_spider_hits';
	}

	public function tableSpiderstats()
	{
		return '{$to_prefix}log_spider_stats';
	}

	public function tablePaidsubscriptions()
	{
		return '{$to_prefix}subscriptions';
	}
}