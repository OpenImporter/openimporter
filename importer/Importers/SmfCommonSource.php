<?php
/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Alpha
 *
 * This file contains code based on:
 *
 * Simple Machines Forum (SMF)
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:	BSD, See included LICENSE.TXT for terms and conditions.
 */

namespace Importers;

use OpenImporter\Configurator;
use OpenImporter\Database;

/**
 * Class SmfCommonSource
 * The class contains code that allows the Importer to obtain settings
 * from software that still has an SMF heritage.
 *
 * @package Importers
 */
abstract class SmfCommonSource
{
	/**
	 * The attachment extension
	 * @var string
	 */
	public $attach_extension = '';

	/**
	 * Path to the forum
	 * @var null
	 */
	protected $path = null;

	/**
	 * @var null|int
	 */
	public $id_attach = null;

	/**
	 * @var null|string
	 */
	public $attachmentUploadDirs = null;

	/**
	 * @var null|string
	 */
	public $avatarUploadDir = null;

	/**
	 * @var Configurator
	 */
	protected $config = null;

	/**
	 * @var Database
	 */
	protected $db = null;

	/**
	 * Set the database and configuration to the class
	 *
	 * @param Database $db
	 * @param Configurator $config
	 */
	public function setParam($db, $config)
	{
		$this->db = $db;
		$this->config = $config;
	}

	abstract public function getName();

	/**
	 * Check that the Settings.php file exists
	 *
	 * @param $path
	 *
	 * @return bool
	 */
	public function checkSettingsPath($path)
	{
		$found = file_exists($path . '/Settings.php');

		if ($found && $this->path === null)
			$this->path = $path;

		return $found;
	}

	/**
	 * The path to our destination forum
	 *
	 * @param $path
	 *
	 * @return bool|string
	 */
	public function getDestinationURL($path)
	{
		// Cannot find Settings.php?
		if (!$this->checkSettingsPath($path))
			return false;

		// Everything should be alright now... no cross server includes, we hope...
		return $this->fetchSetting('boardurl');
	}

	public function getFormFields($path_to = '')
	{
		return array(
			'id' => 'path_to',
			'label' => 'path_to_destination',
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

		$db_server = $this->fetchSetting('db_server');
		$db_user = $this->fetchSetting('db_user');
		$db_passwd = $this->fetchSetting('db_passwd');
		$db_persist = $this->fetchSetting('db_persist');
		$db_prefix = $this->fetchSetting('db_prefix');
		$db_name = $this->fetchSetting('db_name');

		return array($db_server, $db_user, $db_passwd, $db_persist, $db_prefix, $db_name);
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
	 * Helper function for old (SMF) attachments and some new ones
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

	/**
	 * Determine the source attachment and avatar directories
	 *
	 * @param bool $force
	 */
	public function specialAttachments($force = false)
	{
		$to_prefix = $this->config->to_prefix;

		// If we don't know the attachment or avatar directories
		if ($force === true || !isset($this->id_attach, $this->attachmentUploadDirs, $this->avatarUploadDir))
		{
			// Attachment starting id
			$result = $this->db->query("
				SELECT MAX(id_attach) + 1
				FROM {$to_prefix}attachments");
			list ($this->id_attach) = $this->db->fetch_row($result);
			$this->db->free_result($result);

			// Attachment directories
			$result = $this->db->query("
				SELECT value
				FROM {$to_prefix}settings
				WHERE variable = 'attachmentUploadDir'
				LIMIT 1");
			list ($attachmentdir) = $this->db->fetch_row($result);
			$attachment_UploadDir = @unserialize($attachmentdir);
			$this->attachmentUploadDirs = !empty($attachment_UploadDir) ? $attachment_UploadDir : array(1 => $attachmentdir);

			// Avatar directories
			$result = $this->db->query("
				SELECT value
				FROM {$to_prefix}settings
				WHERE variable = 'custom_avatar_dir'
				LIMIT 1");
			list ($this->avatarUploadDir) = $this->db->fetch_row($result);
			$this->db->free_result($result);

			if (empty($this->avatarUploadDir))
				$this->avatarUploadDir = null;

			if (empty($this->id_attach))
				$this->id_attach = 1;
		}
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
}

/**
 * Class SmfCommonSourceStep1
 *
 * @package Importers
 */
abstract class SmfCommonSourceStep1 extends Step1BaseImporter
{
	public function fixTexts($row)
	{
		// If we have a message here, we'll want to convert <br /> to <br>.
		if (isset($row['body']))
		{
			$row['body'] = str_replace(array(
					'<br />', '&#039;', '&#39;', '&quot;'
				), array(
					'<br>', '\'', '\'', '"'
				), $row['body']
			);
		}

		return $row;
	}

	public function doSpecialTable($special_table, $params = null)
	{
		// Are we doing attachments? They're going to want a few things...
		if ($special_table == $this->config->to_prefix . 'attachments' && $params === null)
		{
			$this->config->destination->specialAttachments();

			return $params;
		}
		// Here we have various bits of custom code for some known problems global to all importers.
		elseif ($special_table == $this->config->to_prefix . 'members' && $params !== null)
			return $this->specialMembers($params);

		return $params;
	}

	/**
	 * Delete any defined db attachments that exist on disk for the source system
	 */
	public function removeAttachments()
	{
		$to_prefix = $this->config->to_prefix;

		// @todo This should probably be done in chunks too.
		// attachment_type = 1 are avatars.
		$result = $this->db->query("
			SELECT id_attach, filename, id_folder
			FROM {$to_prefix}attachments");

		while ($row = $this->db->fetch_assoc($result))
		{
			$enc_name = $this->config->destination->getLegacyAttachmentFilename($row['filename'], $row['id_attach'], false);

			$attach_dir = $this->getAttachDir($row);

			if (file_exists($attach_dir . '/' . $enc_name))
				$filename = $attach_dir . '/' . $enc_name;
			else
			{
				// @todo this should not be here I think: it's SMF-specific, while this file shouldn't know anything about the source
				$clean_name = $this->config->destination->getLegacyAttachmentFilename($row['filename'], $row['id_attach'], true);
				$filename = $attach_dir . '/' . $clean_name;
			}

			if (is_file($filename))
				@unlink($filename);
		}

		$this->db->free_result($result);

		// This is valid for some of the sources (e.g. Elk/SMF/Wedge), but not for others
		if (method_exists($this->config->source, 'getAttachmentDirs'))
		{
			$this->createAttachFoldersStructure($this->config->source->getAttachmentDirs());
		}
	}

	/**
	 * Create the destination directory structure for the attachments
	 *
	 * @param string[] $folders
	 *
	 * @return bool
	 */
	protected function createAttachFoldersStructure($folders)
	{

		$source_base = $this->guessBase($folders);
		$destination_base = $this->guessBase($this->config->destination->getAllAttachDirs());

		// No idea where to start, better not mess with the filesystem
		// Though if $destination_base is empty it *is* a mess.
		if (empty($source_base) || empty($destination_base))
			return false;

		$dest_folders = str_replace($source_base, $destination_base, $folders);

		// Prepare the directory structure
		foreach ($dest_folders as $folder)
			create_folders_recursive($folder);

		// Save the new structure in the database
		$this->db->query("
			UPDATE {$this->config->to_prefix}settings
			SET value = '" . serialize($dest_folders) . "'
			WHERE variable = 'attachmentUploadDir'
			LIMIT 1");

		// Reload the new directories
		$this->config->destination->specialAttachments(true);
	}

	/**
	 * Determine the base / root folder for attachments
	 *
	 * @param string[] $folders
	 *
	 * @return bool|string
	 */
	protected function guessBase($folders)
	{
		foreach ($folders as $folder)
		{
			if ($this->isCommon($folder, $folders))
			{
				return $folder;
			}
		}

		foreach ($folders as $folder)
		{
			$dir = $folder;
			while (strlen($dir) > 4)
			{
				$dir = dirname($dir);
				if ($this->isCommon($dir, $folders))
					return $dir;
			}
		}

		return false;
	}

	protected function isCommon($dir, $folders)
	{
		foreach ($folders as $folder)
		{
			if (substr($folder, 0, strlen($dir)) !== $dir)
				return false;
		}

		return true;
	}

	public function getAttachDir($row)
	{
		return $this->config->destination->getAttachDir($row);
	}

	public function getAvatarDir($row)
	{
		return $this->config->destination->getAvatarDir($row);
	}

	public function getAvatarFolderId($row)
	{
		// @todo in theory we could be able to find the "current" directory
		if ($this->config->destination->avatarUploadDir === null)
			return 1;
		else
			return false;
	}

	public function newIdAttach()
	{
		// The one to return
		$current = $this->config->destination->id_attach;

		// Increase preparing for the next one
		$this->config->destination->id_attach++;

		return $current;
	}

	public function moveAvatar($row, $source, $filename)
	{
		$avatar_attach_folder = $this->getAvatarFolderId($row);

		if ($avatar_attach_folder === false)
		{
			$extensions = array(
				'1' => 'gif',
				'2' => 'jpg',
				'3' => 'png',
				'6' => 'bmp'
			);

			$sizes = @getimagesize($source);
			$extension = isset($extensions[$sizes[2]]) ? $extensions[$sizes[2]] : 'bmp';
			$file_hash = 'avatar_' . $row['id_member'] . '_' . time() . '.' . $extension;

			$this->db->query("
				UPDATE {$this->config->to_prefix}members
				SET avatar = '$file_hash'
				WHERE id_member = $row[id_member]");

			$destination = $this->getAvatarDir($row) . '/' . $file_hash;

			$return = false;
		}
		else
		{
			$file_hash = createAttachmentFileHash($filename);
			$id_attach = $this->newIdAttach();

			$destination = $this->getAvatarDir($row) . '/' . $id_attach . '_' . $file_hash . '.' . $this->config->destination->attach_extension;

			$return = array(
				'id_attach' => $id_attach,
				'size' => filesize($destination),
				'filename' => '\'' . $row['filename'] . '\'',
				'file_hash' => '\'' . $file_hash . '\'',
				'id_member' => $row['id_member'],
				'id_folder' => $avatar_attach_folder,
			);
		}

		copy_file($source, $destination);

		return $return;
	}

	protected function specialMembers($row)
	{
		// Let's ensure there are no illegal characters.
		$row['member_name'] = preg_replace('/[<>&"\'=\\\]/is', '', $row['member_name']);
		$row['real_name'] = trim($row['real_name'], " \t\n\r\x0B\0\xA0");

		if (strpos($row['real_name'], '<') !== false || strpos($row['real_name'], '>') !== false || strpos($row['real_name'], '& ') !== false)
			$row['real_name'] = htmlspecialchars($row['real_name'], ENT_QUOTES);
		else
			$row['real_name'] = strtr($row['real_name'], array('\'' => '&#039;'));

		return $row;
	}
}

/**
 * Class SmfCommonSourceStep2
 *
 * @package Importers
 */
abstract class SmfCommonSourceStep2 extends Step2BaseImporter
{
	abstract public function substep0();

	public function substep1()
	{
		$to_prefix = $this->config->to_prefix;

		$request = $this->db->query("
			SELECT id_board, MAX(id_msg) AS id_last_msg, MAX(modified_time) AS last_edited
			FROM {$to_prefix}messages
			GROUP BY id_board");

		$modifyData = array();
		$modifyMsg = array();
		while ($row = $this->db->fetch_assoc($request))
		{
			$this->setBoardProperty($row['id_board'], array('id_last_msg' => $row['id_last_msg'], 'id_msg_updated' => $row['id_last_msg']));

			$modifyData[$row['id_board']] = array(
				'last_msg' => $row['id_last_msg'],
				'last_edited' => $row['last_edited'],
			);
			$modifyMsg[] = $row['id_last_msg'];
		}
		$this->db->free_result($request);

		// Are there any boards where the updated message is not the last?
		if (!empty($modifyMsg))
		{
			$request = $this->db->query("
				SELECT id_board, id_msg, modified_time, poster_time
				FROM {$to_prefix}messages
				WHERE id_msg IN (" . implode(',', $modifyMsg) . ")");
			while ($row = $this->db->fetch_assoc($request))
			{
				// Have we got a message modified before this was posted?
				if (max($row['modified_time'], $row['poster_time']) < $modifyData[$row['id_board']]['last_edited'])
				{
					// Work out the ID of the message (This seems long but it won't happen much.
					$request2 = $this->db->query("
						SELECT id_msg
						FROM {$to_prefix}messages
						WHERE modified_time = " . $modifyData[$row['id_board']]['last_edited'] . "
						LIMIT 1");
					if ($this->db->num_rows($request2) != 0)
					{
						list ($id_msg) = $this->db->fetch_row($request2);

						$this->setBoardProperty($row['id_board'], array('id_msg_updated' => $id_msg));
					}
					$this->db->free_result($request2);
				}
			}
			$this->db->free_result($request);
		}
	}

	protected function setBoardProperty($board, $property, $where = null)
	{
		$to_prefix = $this->config->to_prefix;

		$sets = array();
		foreach ($property as $k => $v)
		{
			$sets[] = $k . ' = ' . $v;
		}
		$set = implode(', ', $sets);

		if ($where === null)
		{
			if (empty($board))
				return;

			$where = "id_board = $board";
		}

		$this->db->query("
			UPDATE {$to_prefix}boards
			SET $set
			WHERE $where");
	}

	public function substep2()
	{
		$to_prefix = $this->config->to_prefix;

		$request = $this->db->query("
			SELECT id_group
			FROM {$to_prefix}membergroups
			WHERE min_posts = -1");

		$all_groups = array();
		while ($row = $this->db->fetch_assoc($request))
			$all_groups[] = $row['id_group'];
		$this->db->free_result($request);

		$request = $this->db->query("
			SELECT id_board, member_groups
			FROM {$to_prefix}boards
			WHERE FIND_IN_SET(0, member_groups)");

		while ($row = $this->db->fetch_assoc($request))
		{
			$member_groups = "'" . implode(',', array_unique(array_merge($all_groups, explode(',', $row['member_groups'])))) . "'";
			$this->setBoardProperty($row['id_board'], array('member_groups' => $member_groups));
		}
		$this->db->free_result($request);
	}

	public function substep3()
	{
		$to_prefix = $this->config->to_prefix;

		// Get the number of messages...
		$result = $this->db->query("
			SELECT COUNT(*) AS totalMessages, MAX(id_msg) AS maxMsgID
			FROM {$to_prefix}messages");
		$row = $this->db->fetch_assoc($result);
		$this->db->free_result($result);

		// Update the latest member. (Highest ID_MEMBER)
		$result = $this->db->query("
			SELECT id_member AS latestMember, real_name AS latestreal_name
			FROM {$to_prefix}members
			ORDER BY id_member DESC
			LIMIT 1");

		if ($this->db->num_rows($result))
		{
			$row += $this->db->fetch_assoc($result);
		}
		else
		{
			$row += array('latestMember' => '', 'latestreal_name' => '');
		}

		$this->db->free_result($result);

		// Update the member count.
		$result = $this->db->query("
			SELECT COUNT(*) AS totalMembers
			FROM {$to_prefix}members");
		$row += $this->db->fetch_assoc($result);
		$this->db->free_result($result);

		// Get the number of topics.
		$result = $this->db->query("
			SELECT COUNT(*) AS totalTopics
			FROM {$to_prefix}topics");
		$row += $this->db->fetch_assoc($result);
		$this->db->free_result($result);

		$this->db->query("
			REPLACE INTO {$to_prefix}settings
				(variable, value)
			VALUES ('latestMember', '$row[latestMember]'),
				('latestreal_name', '$row[latestreal_name]'),
				('totalMembers', '$row[totalMembers]'),
				('totalMessages', '$row[totalMessages]'),
				('maxMsgID', '$row[maxMsgID]'),
				('totalTopics', '$row[totalTopics]'),
				('disableHashTime', " . (time() + 7776000) . ")");
	}

	/**
	 * Fix the post-based membergroups
	 */
	public function substep4()
	{
		$to_prefix = $this->config->to_prefix;

		$request = $this->db->query("
			SELECT id_group, min_posts
			FROM {$to_prefix}membergroups
			WHERE min_posts != -1
			ORDER BY min_posts DESC");

		$post_groups = array();
		$max = $this->db->fetch_assoc($request);
		while ($row = $this->db->fetch_assoc($request))
			$post_groups[] = $row;
		$this->db->free_result($request);

		$case = "CASE WHEN posts > " . $max['min_posts'] . " THEN " . $max['id_group'];

		$first = true;
		foreach ($post_groups as $id => $group)
		{
			if ($first)
			{
				$case .= " WHEN posts BETWEEN " . $group['min_posts'] . " AND " . $max['min_posts'] . " THEN " . $group['id_group'];
				$first = false;
			}
			else
				$case .= " WHEN posts BETWEEN " . $group['min_posts'] . " AND " . $post_groups[$id - 1]['min_posts'] . " THEN " . $group['id_group'];
		}
		$case .= " ELSE 4 END";

		$this->db->query("
			UPDATE {$to_prefix}members
			SET id_post_group = $case");
	}

	/**
	 * Fix the boards total posts and topics.
	 */
	public function substep5()
	{
		$to_prefix = $this->config->to_prefix;

		$result_topics = $this->db->query("
			SELECT id_board, COUNT(*) as num_topics
			FROM {$to_prefix}topics
			GROUP BY id_board");

		$updates = array();
		while ($row = $this->db->fetch_assoc($result_topics))
			$updates[$row['id_board']] = array(
				'num_topics' => $row['num_topics']
			);
		$this->db->free_result($result_topics);

		// Find how many messages are in the board.
		$result_posts = $this->db->query("
			SELECT id_board, COUNT(*) as num_posts
			FROM {$to_prefix}messages
			GROUP BY id_board");

		while ($row = $this->db->fetch_assoc($result_posts))
			$updates[$row['id_board']]['num_posts'] = $row['num_posts'];
		$this->db->free_result($result_posts);

		// Fix the board's totals.
		foreach ($updates as $id_board => $vals)
		{
			$this->setBoardProperty($id_board, $vals);
		}
	}

	public function substep6()
	{
		$to_prefix = $this->config->to_prefix;

		while (true)
		{
			$resultTopic = $this->db->query("
				SELECT t.id_topic, COUNT(m.id_msg) AS num_msg
				FROM {$to_prefix}topics AS t
					LEFT JOIN {$to_prefix}messages AS m ON (m.id_topic = t.id_topic)
				GROUP BY t.id_topic
				HAVING num_msg = 0
				LIMIT $_REQUEST[start], 200");

			$numRows = $this->db->num_rows($resultTopic);

			if ($numRows > 0)
			{
				$stupidTopics = array();
				while ($topicArray = $this->db->fetch_assoc($resultTopic))
					$stupidTopics[] = $topicArray['id_topic'];
				$this->db->query("
					DELETE FROM {$to_prefix}topics
					WHERE id_topic IN (" . implode(',', $stupidTopics) . ')
					LIMIT ' . count($stupidTopics));
				$this->db->query("
					DELETE FROM {$to_prefix}log_topics
					WHERE id_topic IN (" . implode(',', $stupidTopics) . ')');
			}
			$this->db->free_result($resultTopic);

			if ($numRows < 200)
				break;

			// @todo this should not deal with $_REQUEST and alike
			$_REQUEST['start'] += 200;
			pastTime(6);
		}
	}

	public function substep7()
	{
		$to_prefix = $this->config->to_prefix;

		while (true)
		{
			$resultTopic = $this->db->query("
				SELECT
					t.id_topic, MIN(m.id_msg) AS myid_first_msg, t.id_first_msg,
					MAX(m.id_msg) AS myid_last_msg, t.id_last_msg, COUNT(m.id_msg) - 1 AS my_num_replies,
					t.num_replies
				FROM {$to_prefix}topics AS t
					LEFT JOIN {$to_prefix}messages AS m ON (m.id_topic = t.id_topic)
				GROUP BY t.id_topic
				HAVING id_first_msg != myid_first_msg OR id_last_msg != myid_last_msg OR num_replies != my_num_replies
				LIMIT $_REQUEST[start], 200");

			$numRows = $this->db->num_rows($resultTopic);

			while ($topicArray = $this->db->fetch_assoc($resultTopic))
			{
				$memberStartedID = $this->getMsgMemberID($topicArray['myid_first_msg']);
				$memberUpdatedID = $this->getMsgMemberID($topicArray['myid_last_msg']);

				$this->db->query("
					UPDATE {$to_prefix}topics
					SET id_first_msg = '$topicArray[myid_first_msg]',
						id_member_started = '$memberStartedID', id_last_msg = '$topicArray[myid_last_msg]',
						id_member_updated = '$memberUpdatedID', num_replies = '$topicArray[my_num_replies]'
					WHERE id_topic = $topicArray[id_topic]
					LIMIT 1");
			}
			$this->db->free_result($resultTopic);

			if ($numRows < 200)
				break;

			// @todo this should not deal with $_REQUEST and alike
			$_REQUEST['start'] += 100;
			pastTime(7);
		}
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
		$to_prefix = $this->config->to_prefix;

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

	/**
	 * Fix the board parents.
	 */
	public function substep8()
	{
		$to_prefix = $this->config->to_prefix;

		// First, let's get an array of boards and parents.
		$request = $this->db->query("
			SELECT id_board, id_parent, id_cat
			FROM {$to_prefix}boards");

		$child_map = array();
		$cat_map = array();
		while ($row = $this->db->fetch_assoc($request))
		{
			$child_map[$row['id_parent']][] = $row['id_board'];
			$cat_map[$row['id_board']] = $row['id_cat'];
		}
		$this->db->free_result($request);

		// Let's look for any boards with obviously invalid parents...
		foreach ($child_map as $parent => $dummy)
		{
			if ($parent != 0 && !isset($cat_map[$parent]))
			{
				// Perhaps it was supposed to be their id_cat?
				foreach ($dummy as $board)
				{
					if (empty($cat_map[$board]))
						$cat_map[$board] = $parent;
				}

				$child_map[0] = array_merge(isset($child_map[0]) ? $child_map[0] : array(), $dummy);
				unset($child_map[$parent]);
			}
		}

		// The above id_parents and id_cats may all be wrong; we know id_parent = 0 is right.
		$solid_parents = array(array(0, 0));
		$fixed_boards = array();
		while (!empty($solid_parents))
		{
			list ($parent, $level) = array_pop($solid_parents);
			if (!isset($child_map[$parent]))
				continue;

			// Fix all of this board's children.
			foreach ($child_map[$parent] as $board)
			{
				if ($parent != 0)
					$cat_map[$board] = $cat_map[$parent];

				$this->setBoardProperty((int) $board, array('id_parent' => (int) $parent, 'id_cat' => (int) $cat_map[$board], 'child_level' => (int) $level));

				$fixed_boards[] = $board;
				$solid_parents[] = array($board, $level + 1);
			}
		}

		// Leftovers should be brought to the root. They had weird parents we couldn't find.
		if (count($fixed_boards) < count($cat_map))
		{
			$this->setBoardProperty(0, array('child_level' => 0, 'id_parent' => 0, 'child_level' => (int) $level), empty($fixed_boards) ? "1=1" : "id_board NOT IN (" . implode(', ', $fixed_boards) . ")");
		}

		$this->fixInexistentCategories($cat_map);
	}

	/**
	 * Assigns any board belonging to a category that doesn't exist
	 * to a newly created category.
	 */
	protected function fixInexistentCategories($cat_map)
	{
		$to_prefix = $this->config->to_prefix;

		// Last check: any boards not in a good category?
		$request = $this->db->query("
			SELECT id_cat
			FROM {$to_prefix}categories");
		$real_cats = array();
		while ($row = $this->db->fetch_assoc($request))
			$real_cats[] = $row['id_cat'];
		$this->db->free_result($request);

		$fix_cats = array();
		foreach ($cat_map as $cat)
		{
			if (!in_array($cat, $real_cats))
				$fix_cats[] = $cat;
		}

		if (!empty($fix_cats))
		{
			$this->db->query("
				INSERT INTO {$to_prefix}categories
					(name)
				VALUES ('General Category')");
			$catch_cat = mysqli_insert_id($this->db->con);

			$this->setBoardProperty(0, array('id_cat' => (int) $catch_cat), "id_cat IN (" . implode(', ', array_unique($fix_cats)) . ")");
		}
	}

	/**
	 * Adjust boards and categories orders.
	 */
	public function substep9()
	{
		$to_prefix = $this->config->to_prefix;

		$request = $this->db->query("
			SELECT c.id_cat, c.cat_order, b.id_board, b.board_order
			FROM {$to_prefix}categories AS c
				LEFT JOIN {$to_prefix}boards AS b ON (b.id_cat = c.id_cat)
			ORDER BY c.cat_order, b.child_level, b.board_order, b.id_board");
		$cat_order = -1;
		$board_order = -1;
		$curCat = -1;
		while ($row = $this->db->fetch_assoc($request))
		{
			if ($curCat != $row['id_cat'])
			{
				$curCat = $row['id_cat'];
				if (++$cat_order != $row['cat_order'])
					$this->db->query("
						UPDATE {$to_prefix}categories
						SET cat_order = $cat_order
						WHERE id_cat = $row[id_cat]
						LIMIT 1");
			}
			if (!empty($row['id_board']) && ++$board_order != $row['board_order'])
				$this->setBoardProperty($row['id_board'], array('board_order' => $board_order));
		}
		$this->db->free_result($request);
	}

	public function substep11()
	{
		$to_prefix = $this->config->to_prefix;

		$request = $this->db->query("
			SELECT COUNT(*)
			FROM {$to_prefix}attachments");
		list ($attachments) = $this->db->fetch_row($request);
		$this->db->free_result($request);

		while ($_REQUEST['start'] < $attachments)
		{
			$request = $this->db->query("
				SELECT id_attach, filename, attachment_type, id_folder
				FROM {$to_prefix}attachments
				WHERE id_thumb = 0
					AND (RIGHT(filename, 4) IN ('.gif', '.jpg', '.png', '.bmp') OR RIGHT(filename, 5) = '.jpeg')
					AND width = 0
					AND height = 0
				LIMIT $_REQUEST[start], 500");

			if ($this->db->num_rows($request) == 0)
				break;

			while ($row = $this->db->fetch_assoc($request))
			{
				$filename = $this->avatarFullPath($row);

				// Probably not one of the imported ones, then?
				if (!file_exists($filename))
					continue;

				$size = @getimagesize($filename);
				$filesize = @filesize($filename);
				if (!empty($size) && !empty($size[0]) && !empty($size[1]) && !empty($filesize))
					$this->db->query("
						UPDATE {$to_prefix}attachments
						SET
							size = " . (int) $filesize . ",
							width = " . (int) $size[0] . ",
							height = " . (int) $size[1] . "
						WHERE id_attach = $row[id_attach]
						LIMIT 1");
			}
			$this->db->free_result($request);

			// More?
			// We can't keep importing the same files over and over again!
			$_REQUEST['start'] += 500;
			pastTime(11);
		}
	}

	protected function avatarFullPath($row)
	{
		$dir = $this->config->destination->getAvatarDir($row);

		if ($row['attachment_type'] == 1)
		{
			// @todo Honestly I'm not sure what the final name looks like
			// I'm pretty sure there could be at least three options:
			//   1) filename
			//   2) avatar_{id_member}_{time()}.{file_extension}
			//   3) {id_attach}_{file_hash}.{->attach_extension}
			$filename = $row['filename'];
		}
		else
			$filename = $this->config->destination->getLegacyAttachmentFilename($row['filename'], $row['id_attach']);

		return $dir . '/' . $filename;
	}
}

/**
 * Class SmfCommonSourceStep3
 *
 * @package Importers
 */
abstract class SmfCommonSourceStep3 extends Step3BaseImporter
{
	public function run($import_script)
	{
		$to_prefix = $this->config->to_prefix;

		// add some importer information.
		$this->db->query("
			REPLACE INTO {$to_prefix}settings (variable, value)
				VALUES ('import_time', " . time() . "),
					('enable_password_conversion', '1'),
					('imported_from', '" . $import_script . "')");
	}
}