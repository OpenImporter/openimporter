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

namespace OpenImporter\Importers\destinations\ElkArte1_0;

use OpenImporter\Core\Files;
use OpenImporter\Core\Strings;

class ImporterStep1 extends \OpenImporter\Importers\destinations\SmfCommonOriginStep1
{

	/**
	 * From here on, all the methods are needed helper for the conversion
	 */

	/**
	 * Until further notice these methods are for table detection
	 */
	/**
	 * In case the avatar is an attachment, we try to store it into the
	 * attachments table.
	 */
	public function tableAvatars()
	{
		return '{$to_prefix}attachments';
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

	public function tableAlerts()
	{
		return '{$to_prefix}log_mentions';
	}

	public function beforeAlerts()
	{
		$this->db->query("
			TRUNCATE {$this->config->to_prefix}log_mentions");
	}

	/**
	 * Overriders the default TRUNCATE since Elk doesn't have that table.
	 */
	public function beforeFriendlyurls()
	{
	}

	/**
	 * Overriders the default TRUNCATE since Elk doesn't have that table.
	 */
	public function beforeFriendlyurlcache()
	{
	}

	public function tableFriendlyurls()
	{
		return '';
	}

	public function tableFriendlyurlcache()
	{
		return '';
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

	public function preparseCategories($originalRows)
	{
		$rows = array();
		foreach ($originalRows as $row)
		{
			$row['can_collapse'] = (int) $row['can_collapse'];
		}
		return $rows;
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
			$row['receive_from'] = (int) $row['pm_receive_from'];
			$row['pm_prefs'] = (int) $row['pm_prefs'];
			$row['time_offset'] = (int) $row['time_offset'];
			$row['notify_regularity'] = (int) $row['notify_regularity'];
			$row['notify_send_body'] = (int) $row['notify_send_body'];
			$row['notify_types'] = (int) $row['notify_types'];
			$row['warning'] = (int) $row['warning'];

			if (!isset($row['openid_uri']))
				$row['openid_uri'] = '';

			if (!isset($row['date_registered']))
				$row['date_registered'] = 0;
			else
				$row['date_registered'] = strtotime($row['date_registered']);

			$row['gender'] = $this->translateGender($row['gender']);
			unset($row['avatartype'], $row['language'], $row['pm_receive_from']);

			$rows[] = $this->prepareRow($this->specialMembers($row), $this->config->to_prefix . 'members');
		}

		return $rows;
	}

	public function preparsePools($originalRows)
	{
		$rows = array();
		foreach ($originalRows as $row)
		{
			$row['guest_vote'] = (int) $row['guest_vote'];

			$rows[] = $row;
		}

		return $rows;
	}

	protected function translateGender($gender)
	{
		if ($gender === 'Male')
			return 1;
		elseif ($gender === 'Female')
			return 2;
		else
			return 0;
	}

	protected function mapBoardsGroups($group)
	{
		$known = array(
			-1 => -1,
			0 => 0,
			1 => 1,
			2 => 3,
		);

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
		$rows = array();

		foreach ($originalRows as $row)
		{
			$memberGroups = explode(',', $row['member_groups']);
			$groups = array();
			foreach ($memberGroups as $group)
				$groups[] = $this->mapBoardsGroups($group);

			$row['member_groups'] = implode(',', array_filter($groups, function($val) {return $val !== false && $val !== null;}));
			$row['child_level'] = (int) $row['child_level'];
			$row['id_last_msg'] = (int) $row['id_last_msg'];
			$row['id_msg_updated'] = (int) $row['id_msg_updated'];
			$row['id_profile'] = (int) $row['id_profile'];
			$row['count_posts'] = (int) $row['count_posts'];
			$row['id_theme'] = (int) $row['id_theme'];
			$row['override_theme'] = (int) $row['override_theme'];
			$row['unapproved_posts'] = (int) $row['unapproved_posts'];
			$row['unapproved_topics'] = (int) $row['unapproved_topics'];
			$row['id_parent'] = (int) $row['id_parent'];

			if (empty($row['id_profile']))
				$row['id_profile'] = 1;

			$rows[] = $row;
		}

		return $rows;
	}

	public function preparseAttachments($originalRows)
	{
		$rows = array();

		foreach ($originalRows as $row)
		{
			$file_hash = $this->createAttachmentFileHash($row['filename']);

			// Ensure the id_attach is the one we want... I think.
			if (empty($row['id_attach']))
				$row['id_attach'] = $this->newIdAttach();

			// @todo the name should come from step1_importer
			$destination = $this->getAttachDir($row) . '/' . $row['id_attach'] . '_' . $file_hash . '.elk';
			$source = $row['full_path'] . '/' . $row['system_filename'];

			Files::copy_file($source, $destination);
			unset($row['full_path'], $row['system_filename']);
			$row['file_hash'] = $file_hash;
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

			Files::copy_file($source, $smileys_dir . '/' . $relative_path . '/' . $row['filename']);
		}

		return array();
	}

	public function preparseTopics($originalRows)
	{
		$rows = array();
		foreach ($originalRows as $row)
		{
			if (!isset($row['approved']))
				$row['approved'] = 0;

			if ($row['approved'] == 0)
				$row['approved'] = 1;
			elseif ($row['approved'] == 1)
				$row['approved'] = 0;
			// @todo deal with soft-deleted topics!
			elseif ($row['approved'] > 1)
				continue;

			$row['id_previous_board'] = (int) $row['id_previous_board'];
			$row['id_previous_topic'] = (int) $row['id_previous_topic'];
			$row['unapproved_posts'] = (int) $row['unapproved_posts'];

			$rows[] = $row;
		}

		return $rows;
	}

	public function preparseMessages($originalRows)
	{
		$rows = array();
		foreach ($originalRows as $row)
		{
			if (!isset($row['approved']))
				$row['approved'] = 0;

			if ($row['approved'] == 0)
				$row['approved'] = 1;
			elseif ($row['approved'] == 1)
				$row['approved'] = 0;
			// @todo deal with soft-deleted topics!
			elseif ($row['approved'] > 1)
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
			$row['vieworder'] = $row['position'];
			unset($row['position']);

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

	public function preparseAlerts($originalRows)
	{
		$rows = array();
		foreach ($originalRows as $row)
		{
			$alert_type = $this->convertAlertsTypes($row['type']);

			// If we don't know (yet?) the alert, better skip it
			if (empty($alert_type))
				continue;

			$rows[] = array(
				'id_mention' => $row['id_alert'],
				'id_member' => $row['id_member'],
				'id_msg' => $row['id_target'],
				'status' => $this->convertAlertsStatus($row['id_alert'], $row['accessibility'], $row['visibility']),
				'id_member_from' => $row['id_member_from'],
				'log_time' => $row['alert_time'],
				'mention_type' => $alert_type,
			);
		}

		return $rows;
	}

	protected function convertAlertsTypes($type)
	{
		switch ($type)
		{
			case 'like':
				return $type;
			case 'mention':
				return 'men';
			default:
				return false;
		}
	}

	protected function convertAlertsStatus($status, $accessibility, $visibility)
	{
		switch ($status)
		{
			case 'read':
				$final_status = 1;
				break;
			case 'unread':
			case 'new':
				$final_status = 0;
				break;
			case 'deleted':
				$final_status = 2;
				break;
			default:
				$final_status = 0;
				break;
		}

		if (!$visibility)
			$final_status += 10;

		if (!$accessibility)
			$final_status = -1 * ($final_status + 1);

		return $final_status;
	}
}