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

namespace OpenImporter\Importers\destinations\Wedge1_0;

use OpenImporter\Core\Files;

/**
 * Class ImporterStep1
 * Does the actual conversion.
 *
 * @package OpenImporter\Importers\destinations\Wedge1_0
 */
class ImporterStep1 extends \OpenImporter\Importers\destinations\SmfCommonOriginStep1
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
				$row[$ip] = $this->prepareIpv6($row[$ip]);
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
				$ipv6ip = $this->prepareIpv6($row[$ip]);

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
	private function prepareIpv6($ip)
	{
		return $ip;
	}

	/**
	 * From here on, all the methods are needed helper for the conversion
	 */

	/**
	 * Until further notice these methods are for table detection
	 */
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
			$source = $row['full_path'] . '/' . $row['system_filename'];

			Files::copy_file($source, $destination);
			unset($row['full_path'], $row['system_filename']);
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

			Files::copy_file($source, $smileys_dir . '/' . $relative_path . '/' . $row['filename']);
		}

		return array();
	}
}