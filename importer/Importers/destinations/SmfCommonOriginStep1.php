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

namespace OpenImporter\Importers\destinations;

abstract class SmfCommonOriginStep1 extends Step1BaseImporter
{
	protected $beforeOnce = array();

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
		return $params;
	}

	public function removeAttachments()
	{
		$to_prefix = $this->config->to_prefix;

		// !!! This should probably be done in chunks too.
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
			$this->createAttachFoldersStructure($this->config->source->getAttachmentDirs());
	}

	/**
	 * helper function to create an encrypted attachment name
	 *
	 * @param string $filename
	 * @return string
	 */
	protected function createAttachmentFilehash($filename)
	{
		return sha1(md5($filename . time()) . mt_rand());
	}

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
		$this->config->destination->newMaxIdAttach();

		// The one to return
		return $this->config->destination->id_attach;
	}

	public function moveAvatar($row, $source, $filename)
	{
		$avatar_attach_folder = $this->getAvatarFolderId($row);

		if (empty($row['id_member']))
		{
			$avatarg = $this->db->query("
				SELECT value
				FROM {$this->config->to_prefix}settings
				WHERE variable = 'avatar_directory';");
			list ($elk_avatarg) = $this->db->fetch_row($avatarg);
			$this->db->free_result($avatarg);
			if (!empty($elk_avatarg))
			{
				$destination = str_replace($row['basedir'], $elk_avatarg, $row['basedir'] . '/' . $row['filename']);
				copy_file($row['basedir'] . '/' . $row['filename'], $destination);
			}
			return false;
		}

		if ($avatar_attach_folder === false)
		{
			$extensions = array(
				'1' => 'gif',
				'2' => 'jpg',
				'3' => 'png',
				'6' => 'bmp'
			);

			$sizes = @getimagesize($source);
			$extension = isset($sizes[2]) && isset($extensions[$sizes[2]]) ? $extensions[$sizes[2]] : 'bmp';
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
			$file_hash = $this->createAttachmentFileHash($filename);
			$id_attach = $this->newIdAttach();

			$destination = $this->getAvatarDir($row) . '/' . $id_attach . '_' . $file_hash . '.' . $this->config->destination->attach_extension;

			$return = array(
				'id_attach' => $id_attach,
				'size' => filesize($source),
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

	/**
	 * From here on, all the methods are needed helper for the conversion
	 */

	public function beforeMembers()
	{
		$this->db->query("
			TRUNCATE {$this->config->to_prefix}members");
	}

	public function beforeAttachments()
	{
		$this->db->query("
			TRUNCATE {$this->config->to_prefix}attachments");
		$this->removeAttachments();

		$this->config->destination->specialAttachments();
	}

	public function beforeCategories()
	{
		$this->db->query("
			TRUNCATE {$this->config->to_prefix}categories");
	}

	public function beforeCollapsedcats()
	{
		$this->db->query("
			TRUNCATE {$this->config->to_prefix}collapsed_categories");
	}

	public function beforeBoards()
	{
		$this->db->query("
			TRUNCATE {$this->config->to_prefix}boards");

		// The following removes any board-specific permission setting.
		$this->db->query("
			DELETE FROM {$this->config->to_prefix}board_permissions
			WHERE id_profile > 4");
	}

	public function beforeTopics()
	{
		$this->db->query("
			TRUNCATE {$this->config->to_prefix}topics");
	}

	public function beforeMessages()
	{
		$this->db->query("
			TRUNCATE {$this->config->to_prefix}messages");
	}

	public function beforePolls()
	{
		$this->db->query("
			TRUNCATE {$this->config->to_prefix}polls");
	}

	public function beforePolloptions()
	{
		$this->db->query("
			TRUNCATE {$this->config->to_prefix}poll_choices");
	}

	public function beforePollvotes()
	{
		$this->db->query("
			TRUNCATE {$this->config->to_prefix}log_polls");
	}

	public function beforePm()
	{
		$this->db->query("
			TRUNCATE {$this->config->to_prefix}personal_messages");
	}

	public function beforePmrecipients()
	{
		$this->db->query("
			TRUNCATE {$this->config->to_prefix}pm_recipients");
	}

	public function beforePmrules()
	{
		$this->db->query("
			TRUNCATE {$this->config->to_prefix}pm_rules");
	}

	public function beforeBoardmods()
	{
		$this->db->query("
			TRUNCATE {$this->config->to_prefix}moderators");
	}

	public function beforeMarkreadboards()
	{
		$this->db->query("
			TRUNCATE {$this->config->to_prefix}log_boards");
	}

	public function beforeMarkreadtopics()
	{
		$this->db->query("
			TRUNCATE {$this->config->to_prefix}log_topics");
	}

	public function beforeMarkread()
	{
		$this->db->query("
			TRUNCATE {$this->config->to_prefix}log_mark_read");
	}

	public function beforeNotifications()
	{
		// This should be done only once.
		if (!empty($this->beforeOnce['Notifications']))
			return;

		$this->beforeOnce['Notifications'] = true;
		$this->db->query("
			TRUNCATE {$this->config->to_prefix}log_notify;");
	}

	public function beforeMembergroups()
	{
		$this->db->query("
			TRUNCATE {$this->config->to_prefix}membergroups");
	}

	public function beforePermissionprofiles()
	{
		$this->db->query("
			TRUNCATE {$this->config->to_prefix}permission_profiles");
	}

	public function beforePermissions()
	{
		$this->db->query("
			TRUNCATE {$this->config->to_prefix}permissions");
	}

	public function beforePermissionboards()
	{
		$this->db->query("
			TRUNCATE {$this->config->to_prefix}board_permissions");
	}

	public function beforeSmiley()
	{
		$this->db->query("
			TRUNCATE {$this->config->to_prefix}smileys");
	}

	public function beforeCopysmiley()
	{
		// @todo probably to remove
	}

	public function beforeStatistics()
	{
		$this->db->query("
			TRUNCATE {$this->config->to_prefix}log_activity");
	}

	public function beforeLogactions()
	{
		$this->db->query("
			TRUNCATE {$this->config->to_prefix}log_actions");
	}

	public function beforeReports()
	{
		$this->db->query("
			TRUNCATE {$this->config->to_prefix}log_reported");
	}

	public function beforeReportscomments()
	{
		$this->db->query("
			TRUNCATE {$this->config->to_prefix}log_reported_comments");
	}

	public function beforeSpiderhits()
	{
		$this->db->query("
			TRUNCATE {$this->config->to_prefix}log_spider_hits");
	}

	public function beforeSpiderstats()
	{
		$this->db->query("
			TRUNCATE {$this->config->to_prefix}log_spider_stats");
	}

	public function beforePaidsubscriptions()
	{
		$this->db->query("
			TRUNCATE {$this->config->to_prefix}subscriptions");
	}

	public function beforeFriendlyurls()
	{
		$this->db->query("
			TRUNCATE {$this->config->to_prefix}pretty_topic_urls");
	}

	public function beforeFriendlyurlcache()
	{
		$this->db->query("
			TRUNCATE {$this->config->to_prefix}pretty_urls_cache");
	}

	public function beforeCustomfields()
	{
		$this->db->query("
			TRUNCATE {$this->config->to_prefix}custom_fields");
	}

	public function beforeCustomfieldsdata()
	{
		$this->db->query("
			TRUNCATE {$this->config->to_prefix}custom_fields_data");
	}

	public function beforeLikes()
	{
		if (!empty($this->beforeOnce['Likes']))
			return;

		$this->beforeOnce['Likes'] = true;
		$this->db->query("
			TRUNCATE {$this->config->to_prefix}message_likes");
	}
}