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

namespace OpenImporter\Importers\destination;

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

	public function __construct()
	{
		$this->scriptname = $this->getName();
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
	public function preparseSettings($originalRows)
	{
		// @todo this list needs review (I don't remember any enablePinnedTopics in Elk)
		$do_import = array(
			'news',
			'compactTopicPagesContiguous',
			'compactTopicPagesEnable',
			'enablePinnedTopics',
			'todayMod',
			'enablePreviousNext',
			'pollMode',
			'enableVBStyleLogin',
			'enableCompressedOutput',
			'attachmentSizeLimit',
			'attachmentPostLimit',
			'attachmentNumPerPostLimit',
			'attachmentDirSizeLimit',
			'attachmentExtensions',
			'attachmentCheckExtensions',
			'attachmentShowImages',
			'attachmentEnable',
			'attachmentEncryptFilenames',
			'attachmentThumbnails',
			'attachmentThumbWidth',
			'attachmentThumbHeight',
			'censorIgnoreCase',
			'mostOnline',
			'mostOnlineToday',
			'mostDate',
			'allow_disableAnnounce',
			'trackStats',
			'userLanguage',
			'titlesEnable',
			'topicSummaryPosts',
			'enableErrorLogging',
			'max_image_width',
			'max_image_height',
			'onlineEnable',
			'smtp_host',
			'smtp_port',
			'smtp_username',
			'smtp_password',
			'mail_type',
			'timeLoadPageEnable',
			'totalMembers',
			'totalTopics',
			'totalMessages',
			'simpleSearch',
			'censor_vulgar',
			'censor_proper',
			'enablePostHTML',
			'enableEmbeddedFlash',
			'xmlnews_enable',
			'xmlnews_maxlen',
			'hotTopicPosts',
			'hotTopicVeryPosts',
			'registration_method',
			'send_validation_onChange',
			'send_welcomeEmail',
			'allow_editDisplayName',
			'allow_hideOnline',
			'guest_hideContacts',
			'spamWaitTime',
			'pm_spam_settings',
			'reserveWord',
			'reserveCase',
			'reserveUser',
			'reserveName',
			'reserveNames',
			'autoLinkUrls',
			'banLastUpdated',
			'avatar_max_height_external',
			'avatar_max_width_external',
			'avatar_action_too_large',
			'avatar_max_height_upload',
			'avatar_max_width_upload',
			'avatar_resize_upload',
			'avatar_download_png',
			'failed_login_threshold',
			'oldTopicDays',
			'edit_wait_time',
			'edit_disable_time',
			'autoFixDatabase',
			'allow_guestAccess',
			'time_format',
			'number_format',
			'enableBBC',
			'max_messageLength',
			'signature_settings',
			'autoOptMaxOnline',
			'defaultMaxMessages',
			'defaultMaxTopics',
			'defaultMaxMembers',
			'enableParticipation',
			'recycle_enable',
			'recycle_board',
			'maxMsgID',
			'enableAllMessages',
			'fixLongWords',
			'who_enabled',
			'time_offset',
			'cookieTime',
			'lastActive',
			'requireAgreement',
			'unapprovedMembers',
			'package_make_backups',
			'databaseSession_enable',
			'databaseSession_loose',
			'databaseSession_lifetime',
			'search_cache_size',
			'search_results_per_page',
			'search_weight_frequency',
			'search_weight_age',
			'search_weight_length',
			'search_weight_subject',
			'search_weight_first_message',
			'search_max_results',
			'search_floodcontrol_time',
			'permission_enable_deny',
			'permission_enable_postgroups',
			'mail_next_send',
			'mail_recent',
			'settings_updated',
			'next_task_time',
			'warning_settings',
			'admin_features',
			'last_mod_report_action',
			'pruningOptions',
			'cache_enable',
			'reg_verification',
			'enable_buddylist',
			'birthday_email',
			'globalCookies',
			'default_timezone',
			'memberlist_updated',
			'latestMember',
			'latestRealName',
			'db_mysql_group_by_fix',
			'rand_seed',
			'mostOnlineUpdated',
			'search_pointer',
			'spider_name_cache',
			'modlog_enabled',
			'disabledBBC',
			'latest_member',
			'latest_real_name',
			'total_members',
			'total_messages',
			'max_msg_id',
			'total_topics',
			'disable_hash_time',
			'latestreal_name',
			'disableHashTime',
		);
		foreach ($originalRows as $row)
		{
			if (in_array($row['variable'], $do_import))
			{
				$this->db->query("
					REPLACE INTO {$this->config->to_prefix}settings
						(variable, value)
					VALUES('$row[variable]', '" . addcslashes($row['value'], '\'\\"') . "')");
			}
		}

		return array();
	}

	public function preparseMembers($originalRows)
	{
		$rows = array();
		foreach ($originalRows as $row)
		{

			// avatartype field is used temporary to dertermine the type of avatar
			if ($row['avatartype'] != 'remote')
				$row['avatar'] = '';

			$row['lngfile'] = $row['language'];
			$row['receive_from'] = $row['pm_receive_from'];

			unset($row['avatartype'], $row['language'], $row['pm_receive_from']);

			$rows[] = $this->prepareRow($this->specialMembers($row), null, $this->config->to_prefix . 'members');
		}

		return $rows;
	}

	protected function mapBoardsGroups($group)
	{
		$known = array(
			-1 => -1,
			0 => 0,
			1 => 1,
			2 => 3,
		);

		$new_group = null;
		if (isset($known[$group]))
			$new_group = $known[$group];
		else
		{
			$new_group = $group - 10;
			if ($new_group == 1)
				$new_group = null;
		}

		return $new_group;
	}

	public function preparseBoards($originalRows)
	{
		foreach ($originalRows as $row)
		{
			$memberGroups = explode(',', $row['member_groups']);
			$groups = array();
			foreach ($memberGroups as $group)
				$groups[] = $this->mapBoardsGroups($group);

			$row['member_groups'] = implode(',', array_filter($groups, function($val) {return $val !== false && $val !== null;}));

			$rows[] = $row;
		}

		return $rows;
	}

	public function preparseAttachments($originalRows)
	{
		$rows = array();
		foreach ($originalRows as $row)
		{
			$file_hash = createAttachmentFileHash($row['filename']);
			$id_attach = $this->newIdAttach();
			// @todo the name should come from step1_importer
			$destination = $this->getAttachDir($row) . '/' . $id_attach . '_' . $file_hash . '.elk';
			$source = $row['full_path'] . '/' . $row['filename'];

			// Ensure the id_attach is the one we want... I think.
			if (empty($row['id_attach']))
				$row['id_attach'] = $id_attach;

			copy_file($source, $destination);
			unset($row['full_path']);
			$rows[] = $row;
		}

		return $rows;
	}

	public function preparseAvatars($originalRows)
	{
		$rows = array();
		foreach ($originalRows as $row)
		{
			$source = $row['full_path'] . '/' . $row['filename'];
			$upload_result = $this->moveAvatar($row, $source, $row['filename']);

			if (!empty($upload_result))
			{
				$rows[] = $upload_result;
			}
		}

		return $rows;
	}

	public function preparseCopysmiley($rows)
	{
		$request = $this->db->query("
			SELECT value
			FROM {$this->config->to_prefix}settings
			WHERE variable='smileys_dir';");
		list ($smileys_dir) = $this->db->fetch_row($request);
		$this->db->free_result($request);

		foreach ($rows as $row)
		{
			$source = $row['full_path'] . '/' . $row['filename'];
			$relative_path = str_replace($row['basedir'], '', $row['full_path']);

			copy_file($source, $smileys_dir . '/' . $relative_path . '/' . $row['filename']);
		}

		return array();
	}

	public function preparseTopics($originalRows)
	{
		$rows = array();
		foreach ($originalRows as $row)
		{
			// @todo deal with soft-deleted topics!
			if ($row['approved'] > 1)
				continue;
			$rows[] = $row;
		}

		return $rows;
	}

	public function preparseMessages($originalRows)
	{
		$rows = array();
		foreach ($originalRows as $row)
		{
			// @todo deal with soft-deleted messages!
			if ($row['approved'] > 1)
				continue;

			if (empty($row['icon']))
				$row['icon'] = 'xx';

			$rows[] = $row;
		}

		return $rows;
	}

	public function preparseCustomfields($originalRows)
	{
		$rows = array();
		foreach ($originalRows as $row)
		{
			$row['col_name'] = preg_replace('~[^a-zA-Z0-9\-_]~', '', $row['col_name']);

			if (!empty($row['field_options']))
				$row['field_options'] = implode(',', array_values($row['field_options']));

			if ($row['field_type'] == 'input')
				$row['field_type'] = 'text';

			// @todo add a list of valid masks and check on that
			if (empty($row['mask']))
				$row['mask'] = 'nohtml';

			$row['enclose'] = str_replace('{content}', '{INPUT}', $row['enclose']);
				$row['field_type'] = 'text';

			$rows[] = $row;
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