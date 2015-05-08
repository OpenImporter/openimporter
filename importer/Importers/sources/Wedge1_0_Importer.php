<?php
/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 2.0 Alpha
 */

namespace OpenImporter\Importers\sources;

use OpenImporter\Core\Files;

class Wedge1_0_Importer extends \OpenImporter\Importers\AbstractSourceSmfImporter
{
	protected $setting_file = '/Settings.php';

	protected $wedge_attach_folders = null;

	public function getName()
	{
		return 'Wedge1_0';
	}

	public function getVersion()
	{
		return '1.0';
	}

	public function setDefines()
	{
		if (!defined('WEDGE'))
			define('WEDGE', 1);
	}

	/**
	 * @override Wedge supports only MySQL
	 */
	protected function fetchDriver()
	{
		return 'pdo_mysql';
	}

	public function getAttachmentDirs()
	{
		if ($this->wedge_attach_folders === null)
		{
			$request = $this->db->query("
				SELECT value
				FROM {$this->config->from_prefix}settings
				WHERE variable='attachmentUploadDir';");
			list ($smf_attachments_dir) = $this->db->fetch_row($request);

			$this->wedge_attach_folders = @unserialize($smf_attachments_dir);

			if (!is_array($this->wedge_attach_folders))
				$this->wedge_attach_folders = array(1 => $smf_attachments_dir);
		}

		return $this->wedge_attach_folders;
	}

	public function getAttachDir($row)
	{
		if ($this->wedge_attach_folders === null)
			$this->getAttachmentDirs();

		if (!empty($row['id_folder']) && !empty($this->wedge_attach_folders[$row['id_folder']]))
			return $this->wedge_attach_folders[$row['id_folder']];
		else
			return $this->wedge_attach_folders[1];
	}

	/**
	 * From here on, all the methods are needed helper for the conversion
	 */
	public function preparseMembers($originalRows)
	{
		$rows = array();
		foreach ($originalRows as $row)
		{
			$data = @unserialize($row['data']);
			unset($row['data']);

			$row['message_labels'] = !empty($data['pmlabs']) ? $data['pmlabs'] : '';
			$row['date_registered'] = date('Y-m-d G:i:s', $row['date_registered']);
			if (!empty($data['secret']))
			{
				$question = explode('|', $data['secret']);
				$row['secret_question'] = $question[0];
				$row['secret_answer'] = $question[1];
			}
			else
			{
				$row['secret_question'] = '';
				$row['secret_answer'] = '';
			}
			$row['member_ip'] = $this->ipmasktoipv6($row['member_ip']);
			$row['member_ip2'] = $this->ipmasktoipv6($row['member_ip2']);

			$rows[] = $row;
		}

		return $rows;
	}

	public function preparseAttachments($originalRows)
	{
		$rows = array();
		foreach ($originalRows as $row)
		{
			$row['full_path'] = $this->getAttachDir($row);
			$row['system_filename'] = $row['id_attach'] . '_' . $row['file_hash'] . '.ext';

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
			3 => 2,
		);

		return isset($known[$group]) ? $known[$group] : $group + 10;
	}

	protected function ipmasktoipv6($ip)
	{
		if (strpos($ip, '.') === false)
			return implode(':', explode("\n", chunk_split($ip, 4, "\n")));
		else
			return $this->ipmasktoipv6('00000000000000000000ffff' . bin2hex(inet_pton($ip)));
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

			$row['member_groups'] = implode(',', $groups);

			$rows[] = $row;
		}

		return $rows;
	}

	public function codeSettings()
	{
		// @todo this list comes from the SMF 1.1 importer, to review
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

		$request = $this->db->query("
			SELECT variable, value
			FROM {$this->config->from_prefix}settings;");

		$rows = array();
		while ($row = $this->db->fetch_assoc($request))
		{
			if (in_array($row['variable'], $do_import))
			{
				$rows[] = array(
					'variable' => $row['variable'],
					'value' => $row['value'],
				);
			}
		}
		$this->db->free_result($request);

		return $rows;
	}

	public function codeAvatars()
	{
		$rows = array();

		$avatarg = $this->db->query("
			SELECT value
			FROM {$this->config->from_prefix}settings
			WHERE variable = 'avatar_directory';");
		list ($smf_avatarg) = $this->db->fetch_row($avatarg);
		$this->db->free_result($avatarg);

		$avatar_gallery = array();
		if (!empty($smf_avatarg) && file_exists($smf_avatarg))
			$avatar_gallery = Files::get_files_recursive($smf_avatarg);

		foreach ($avatar_gallery as $file)
		{
			$file = str_replace('\\', '/', $file);
			$rows[] = array(
				'id_member' => 0,
				'basedir' => $smf_avatarg,
				'full_path' => dirname($file),
				'filename' => basename($file),
				'type' => 'gallery',
			);
		}

		$avatarg = $this->db->query("
			SELECT value
			FROM {$this->config->from_prefix}settings
			WHERE variable = 'custom_avatar_dir';");
		list ($smf_avatarg) = $this->db->fetch_row($avatarg);
		$this->db->free_result($avatarg);

		$avatar_custom = array();
		if (!empty($smf_avatarg) && file_exists($smf_avatarg))
			$avatar_custom = Files::get_files_recursive($smf_avatarg);

		foreach ($avatar_custom as $file)
		{
			$file = str_replace('\\', '/', $file);
			preg_match('~avatar_(\d+)_\d+~i', $file, $match);
			if (!empty($match[1]))
				$id_member = $match[1];
			else
				$id_member = 0;

			$rows[] = array(
				'id_member' => $id_member,
				'basedir' => $smf_avatarg,
				'full_path' => dirname($file),
				'filename' => basename($file),
				'type' => 'upload',
			);
		}

		return $rows;
	}

	public function codeCopysmiley()
	{
		$request = $this->db->query("
			SELECT value
			FROM {$this->config->from_prefix}settings
			WHERE variable = 'smileys_dir';");
		list ($smf_smileys_dir) = $this->db->fetch_row($request);

		if (!empty($smf_smileys_dir) && file_exists($smf_smileys_dir))
		{
			$smf_smileys_dir = str_replace('\\', '/', $smf_smileys_dir);
			$smiley = array();
			$files = Files::get_files_recursive($smf_smileys_dir);
			foreach ($files as $file)
			{
				$file = str_replace('\\', '/', $file);
				$smiley[] = array(
					'basedir' => $smf_smileys_dir,
					'full_path' => dirname($file),
					'filename' => basename($file),
				);
			}
			if (!empty($smiley))
				return array($smiley);
			else
				return false;
		}
		else
			return false;
	}
}