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
 *
 * Copyright (c) 2014, Thorsten Eurich and René-Gilles Deberdt
 * All rights reserved.
 */

namespace OpenImporter\Importers\destinations;

/**
 * The class contains code that allows the Importer to obtain settings
 * from the Wedge installation.
 */
class wedge0_1_importer extends \OpenImporter\Importers\SmfCommonOrigin
{
	public $attach_extension = 'ext';

	public function getName()
	{
		return 'Wedge 0.1';
	}

	public function __construct()
	{
		$this->scriptname = $this->getName();
	}
}

/**
 * Does the actual conversion.
 */
class wedge0_1_importer_step1 extends \OpenImporter\Importers\SmfCommonOriginStep1
{
	public function doSpecialTable($special_table, $params = null)
	{
		// If there is an IP, better convert it to "something"
		$params = $this->doIpConvertion($params);
		$params = $this->doIpPointer($params);

		return parent::doSpecialTable($special_table, $params);
	}

	protected function doIpConvertion($row)
	{
		$convert_ips = array('member_ip', 'member_ip2');

		foreach ($convert_ips as $ip)
		{
			if (array_key_exists($ip, $row))
				$row[$ip] = $this->_prepare_ipv6($row[$ip]);
		}

		return $row;
	}

	protected function doIpPointer($row)
	{
		$to_prefix = $this->config->to_prefix;
		$ips_to_pointer = array('poster_ip');

		foreach ($ips_to_pointer as $ip)
		{
			if (array_key_exists($ip, $row))
			{
				$ipv6ip = $this->_prepare_ipv6($row[$ip]);

				$request2 = $this->db->query("
					SELECT id_ip
					FROM {$to_prefix}log_ips
					WHERE member_ip = '" . $ipv6ip . "'
					LIMIT 1");

				// IP already known?
				if ($this->db->num_rows($request2) != 0)
				{
					list ($id_ip) = $this->db->fetch_row($request2);
					$row[$ip] = $id_ip;
				}
				// insert the new ip
				else
				{
					$this->db->query("
						INSERT INTO {$to_prefix}log_ips
							(member_ip)
						VALUES ('$ipv6ip')");

					$pointer = $this->db->insert_id();
					$row[$ip] = $pointer;
				}

				$this->db->free_result($request2);
			}
		}

		return $row;
	}

	/**
	 * placehoder function to convert IPV4 to IPV6
	 * @todo convert IPV4 to IPV6
	 * @todo move to source file, because it depends on the source for any specific destination
	 * @param string $ip
	 * @return string $ip
	 */
	private function _prepare_ipv6($ip)
	{
		return $ip;
	}

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

	public function tableFriendlyurls()
	{
		return '{$to_prefix}pretty_topic_urls';
	}

	public function tableFriendlyurlcache()
	{
		return '{$to_prefix}pretty_urls_cache';
	}

	/**
	 * From here on we have methods to verify code before inserting it into the db
	 */
	/**
	 * @todo likely broken in Wedge (comes from SMF)
	 */
	public function preparseSettings($originalRows)
	{
		// @todo this list needs review
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

			unset($row['avatartype']);

			$rows[] = $this->prepareRow($this->specialMembers($row), $this->config->to_prefix . 'members');
		}

		return $rows;
	}

	/**
	 * @todo it may be broken in Wedge
	 */
	public function preparseAttachments($originalRows)
	{
		$rows = array();
		foreach ($originalRows as $row)
		{
			$file_hash = $this->createAttachmentFileHash($row['filename']);
			$id_attach = $this->newIdAttach();
			// @todo the name should come from step1_importer
			$destination = $this->getAttachDir($row) . '/' . $id_attach . '_' . $file_hash . '.ext';
			$source = $row['full_path'] . '/' . $row['filename'];

			copy_file($source, $destination);
			$row['file_hash'] = $file_hash;
			$rows[] = $row;
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
			$memberGroups = array_filter(explode(',', $row['member_groups']));
			$groups = array();
			foreach ($memberGroups as $group)
				$groups[] = $this->mapBoardsGroups($group);

			$row['member_groups'] = implode(',', array_filter($groups, function($val) {return $val !== false && $val !== null;}));

			$rows[] = $row;
		}

		return $rows;
	}

	public function preparseAvatars($originalRows)
	{
		// @todo I think I messed up something and deleted the relevant code at some point

		return array();
	}

	public function preparseCustomfields($originalRows)
	{
		$rows = array();
		foreach ($originalRows as $row)
		{
			$row['col_name'] = preg_replace('~[^a-zA-Z0-9\-_]~', '', $row['col_name']);

			$row['field_options'] = implode(',', array_values($row['field_options']));
			if ($row['field_type'] == 'input')
				$row['field_type'] = 'text';

			$rows[] = $row;
		}

		return $rows;
	}

	/**
	 * @todo it may be broken in Wedge
	 */
	public function codeCopysmiley($rows)
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
}

/**
 * Recount statistics, and fixes stuff.
 */
class wedge0_1_importer_step2 extends \OpenImporter\Importers\SmfCommonOriginStep2
{
	public function substep0()
	{
		$to_prefix = $this->config->to_prefix;

		// Get all members with wrong number of personal messages.
		$request = $this->db->query("
			SELECT mem.id_member, COUNT(pmr.id_pm) AS real_num, mem.instant_messages
			FROM {$to_prefix}members AS mem
				LEFT JOIN {$to_prefix}pm_recipients AS pmr ON (mem.id_member = pmr.id_member AND pmr.deleted = 0)
			GROUP BY mem.id_member
			HAVING real_num != instant_messages");
		while ($row = $this->db->fetch_assoc($request))
		{
			$this->db->query("
				UPDATE {$to_prefix}members
				SET instant_messages = $row[real_num]
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

	public function substep12()
	{
		$to_prefix = $this->config->to_prefix;

		$indexes = array(
			'id_topic' => array(
				'name' => 'id_topic',
				'columns' => array('id_topic'),
				'type' => 'primary',
			),
			'last_message' => array(
				'name' => 'last_message',
				'columns' => array('id_last_msg', 'id_board'),
				'type' => 'unique',
			),
			'first_message' => array(
				'name' => 'first_message',
				'columns' => array('id_first_msg', 'id_board'),
				'type' => 'unique',
			),
			'poll' => array(
				'name' => 'poll',
				'columns' => array('ID_POLL', 'id_topic'),
				'type' => 'unique',
			),
			'is_pinned' => array(
				'name' => 'is_pinned',
				'columns' => array('is_pinned'),
				'type' => 'key',
			),
			'id_board' => array(
				'name' => 'id_board',
				'columns' => array('id_board'),
				'type' => 'key',
			),
			'member_started' => array(
				'name' => 'member_started',
				'columns' => array('id_member_started', 'id_board'),
				'type' => 'key',
			),
			'last_message_pinned' => array(
				'name' => 'last_message_pinned',
				'columns' => array('id_board', 'is_pinned', 'id_last_msg'),
				'type' => 'key',
			),
			'board_news' => array(
				'name' => 'board_news',
				'columns' => array('id_board', 'id_first_msg'),
				'type' => 'key',
			),
		);

		foreach ($indexes as $index_info)
			$this->db->alter_table("{$to_prefix}topics", $index_info);

		$_REQUEST['start'] = 0;
		pastTime(13);
	}

	public function substep13()
	{
		$indexes = array(
			'id_msg' => array(
				'name' => 'id_msg',
				'columns' => array('id_msg'),
				'type' => 'primary',
			),
			'id_topic' => array(
				'name' => 'id_topic',
				'columns' => array('id_topic', 'id_msg'),
				'type' => 'unique',
			),
			'id_board' => array(
				'name' => 'id_board',
				'columns' => array('id_board', 'id_msg'),
				'type' => 'unique',
			),
			'id_member' => array(
				'name' => 'id_member',
				'columns' => array('id_member', 'id_msg'),
				'type' => 'unique',
			),
			'ip_index' => array(
				'name' => 'ip_index',
				'columns' => array('poster_ip(15)', 'id_topic'),
				'type' => 'key',
			),
			'participation' => array(
				'name' => 'participation',
				'columns' => array('id_member', 'id_topic'),
				'type' => 'key',
			),
			'show_posts' => array(
				'name' => 'show_posts',
				'columns' => array('id_member', 'id_board'),
				'type' => 'key',
			),
			'id_topic' => array(
				'name' => 'id_topic',
				'columns' => array('id_topic'),
				'type' => 'key',
			),
			'id_member_msg' => array(
				'name' => 'id_member_msg',
				'columns' => array('id_member', 'approved', 'id_msg'),
				'type' => 'key',
			),
			'current_topic' => array(
				'name' => 'current_topic',
				'columns' => array('id_topic', 'id_msg', 'id_member', 'approved'),
				'type' => 'key',
			),
		);

		foreach ($indexes as $index_info)
			$this->db->alter_table("{$to_prefix}messages", $index_info);

		$_REQUEST['start'] = 0;
		pastTime(14);
	}
}

/**
 * Records the conversion
 */
class wedge0_1_importer_step3 extends \OpenImporter\Importers\SmfCommonOriginStep3
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