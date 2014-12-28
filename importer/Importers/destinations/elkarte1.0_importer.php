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

/**
 * The class contains code that allows the Importer to obtain settings
 * from the ElkArte installation.
 */
class elkarte1_0_importer extends SmfCommonOrigin
{
	public $attach_extension = 'elk';

	public function getName()
	{
		return 'ElkArte 1.0';
	}
}

class elkarte1_0_importer_step1 extends SmfCommonOriginStep1
{

	/**
	 * From here on, all the methods are needed helper for the conversion
	 */

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

	/**
	 * In case the avatar is an attachment, we try to store it into the
	 * attachments table.
	 */
	public function tableAvatars()
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

	public function tablePermissionboardss()
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

	public function tableCustomfields()
	{
		return '{$to_prefix}custom_fields';
	}

	public function tableCustomfieldsdata()
	{
		return '{$to_prefix}custom_fields_data';
	}

	public function tableLikes()
	{
		return '{$to_prefix}message_likes';
	}

	/**
	 * From here on we have methods to verify code before inserting it into the db
	 */
	public function preparseMembers($originalRows)
	{
		$rows = array();
		foreach ($originalRows as $row)
		{
			// avatartype field is used temporary to dertermine the type of avatar
			if ($row['avatartype'] != 'remote')
				$row['avatar'] = '';

			unset($row['avatartype']);

			$rows[] = $this->prepareRow($this->specialMembers($row), null, $this->config->to_prefix . 'members');
		}
	}

	public function preparseAttachments($originalRows)
	{
		$rows = array();
		foreach ($originalRows as $row)
		{
			$file_hash = createAttachmentFileHash($row['filename']);
			$id_attach = $this->newIdAttach();
			// @todo the name should come from step1_importer
			$destination = $this->getAttachDir($row) . DIRECTORY_SEPARATOR . $id_attach . '_' . $file_hash . '.elk';
			$source = $row['full_path'] . DIRECTORY_SEPARATOR . $row['filename'];

			copy_file($source, $destination);
			$rows[] = $row;
		}

		return $rows;
	}

	public function preparseAvatars($originalRows)
	{
		$rows = array();
		foreach ($originalRows as $row)
		{
			$source = $row['full_path'] . DIRECTORY_SEPARATOR. $row['filename'];
			$upload_result = $this->moveAvatar($row, $source, $row['filename']);

			if (!empty($upload_result))
			{
				$rows[] = $upload_result;
			}
		}

		return $rows;
	}
}

class elkarte1_0_importer_step2 extends SmfCommonOriginStep2
{
	public function substep0()
	{
		$to_prefix = $this->config->to_prefix;

		// Get all members with wrong number of personal messages.
		$request = $this->db->query("
			SELECT mem.id_member, COUNT(pmr.id_pm) AS real_num, mem.personal_messages
			FROM {$to_prefix}members AS mem
				LEFT JOIN {$to_prefix}pm_recipients AS pmr ON (mem.id_member = pmr.id_member AND pmr.deleted = 0)
			GROUP BY mem.id_member
			HAVING real_num != personal_messages");
		while ($row = $this->db->fetch_assoc($request))
		{
			$this->db->query("
				UPDATE {$to_prefix}members
				SET personal_messages = $row[real_num]
				WHERE id_member = $row[id_member]
				LIMIT 1");

			pastTime(0);
		}
		$this->db->free_result($request);

		$request = $this->db->query("
			SELECT mem.id_member, COUNT(pmr.id_pm) AS real_num, mem.unread_messages
			FROM {$to_prefix}members AS mem
				LEFT JOIN {$to_prefix}pm_recipients AS pmr ON (mem.id_member = pmr.id_member AND pmr.deleted = 0 AND pmr.is_read = 0)
			GROUP BY mem.id_member
			HAVING real_num != unread_messages");
		while ($row = $this->db->fetch_assoc($request))
		{
			$this->db->query("
				UPDATE {$to_prefix}members
				SET unread_messages = $row[real_num]
				WHERE id_member = $row[id_member]
				LIMIT 1");

			pastTime(0);
		}
		$this->db->free_result($request);
	}

	public function substep101()
	{
		$to_prefix = $this->config->to_prefix;

		$request = $this->db->query("
			SELECT COUNT(*) AS count, t.id_topic
			FROM {$to_prefix}message_likes AS ml
				INNER JOIN {$to_prefix}topics AS t ON (t.id_first_msg = ml.id_msg)
			GROUP BY t.id_topic");
		while ($row = $this->db->fetch_assoc($request))
		{
			$this->db->query("
				UPDATE {$to_prefix}topics
				SET num_likes = $row[count]
				WHERE id_topic = $row[id_topic]
				LIMIT 1");

			pastTime(0);
		}
		$this->db->free_result($request);
	}

	public function substep102()
	{
		$to_prefix = $this->config->to_prefix;

		$request = $this->db->query("
			SELECT COUNT(*) AS count, id_poster
			FROM {$to_prefix}message_likes
			GROUP BY id_poster");
		while ($row = $this->db->fetch_assoc($request))
		{
			$this->db->query("
				UPDATE {$to_prefix}members
				SET likes_received = $row[count]
				WHERE id_member = $row[id_poster]
				LIMIT 1");

			pastTime(0);
		}
		$this->db->free_result($request);
	}

	public function substep103()
	{
		$to_prefix = $this->config->to_prefix;

		$request = $this->db->query("
			SELECT COUNT(*) AS count, id_member
			FROM {$to_prefix}message_likes
			GROUP BY id_member");
		while ($row = $this->db->fetch_assoc($request))
		{
			$this->db->query("
				UPDATE {$to_prefix}members
				SET likes_given = $row[count]
				WHERE id_member = $row[id_member]
				LIMIT 1");

			pastTime(0);
		}
		$this->db->free_result($request);
	}
}

class elkarte1_0_importer_step3 extends SmfCommonOriginStep3
{
}