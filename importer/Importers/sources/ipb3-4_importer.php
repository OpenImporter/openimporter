<?php
/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Alpha
 */

class IPB3_4 extends AbstractSourceImporter
{
	protected $setting_file = '/conf_global.php';

	protected $smf_attach_folders = null;

	protected $is_disabled = false;
	protected $is_banned = false;
	protected $groupsTranslation = null;
	protected $permissionGroupsTranslation = null;

	public function getName()
	{
		return 'IPB3_4';
	}

	public function getVersion()
	{
		return '1.0';
	}

	public function setDefines()
	{
	}

	public function getPrefix()
	{
		$db_name = $this->getDbName();
		$db_prefix = $this->fetchSetting('sql_tbl_prefix');
		return '`' . $db_name . '`.' . $db_prefix;
	}

	public function getDbName()
	{
		return $this->fetchSetting('sql_database');
	}

	public function getTableTest()
	{
		return 'members';
	}

	protected function fetchSetting($name)
	{
		static $content = null;

		if ($content === null)
			$content = file_get_contents($this->path . '/conf_global.php');

		$match = array();
		preg_match('~\$INFO\[\'' . $name . '\'\]\s*=\s*\'(.*?)\';~', $content, $match);

		return isset($match[1]) ? $match[1] : '';
	}

	protected function html2bbc($text)
	{
		$text = preg_replace(
			array(
				'~<!--QuoteBegin.*?-->.+?<!--QuoteEBegin-->~is',
				'~<!--QuoteEnd-->.+?<!--QuoteEEnd-->~is',
				'~<!--quoteo\(post=(.+?):date=(.+?):name=(.+?)\)-->.+?<!--quotec-->~is',
				'~<!--quoteo-->.+?<!--quotec-->~is',
				'~<!--c1-->.+?<!--ec1-->~is',
				'~<!--c2-->.+?<!--ec2-->~is',
				'~<!--coloro:.+?--><span style=\'color:([^;]+?)\'><!--/coloro-->~is',
				'~<!--coloro:.+?--><span style="color:([^;]+?)"><!--/coloro-->~is',
				'~<!--colorc--></span><!--/colorc-->~is',
				'~<!--fonto:.+?><span style=\'font-family:([^;]+?)\'><!--/fonto-->~is',
				'~<!--fonto:.+?><span style="font-family:([^;]+?)"><!--/fonto-->~is',
				'~<!--fontc--></span><!--/fontc-->~is',
				'~<!--sizeo:.+?><span style=\'font-size:([^;]+?)\'><!--/sizeo-->~is',
				'~<!--sizeo:.+?><span style="font-size:([^;]+?)"><!--/sizeo-->~is',
				'~<!--sizeo:.+?><span style="font-size:([^;]+?);line-height:100%"><!--/sizeo-->~is',
				'~<!--sizec--></span><!--/sizec-->~is',
				'~<([/]?)ul>~is',
				'~<ol type=\'a\'>~s',
				'~<ol type=\'A\'>~s',
				'~<ol type=\'1\'>~s',
				'~<ol type=\'i\'>~s',
				'~<ol type=\'I\'>~s',
				'~</ol>~is',
				'~<img src=".+?" style="vertical-align:middle" emoid=".+?" border="0" alt="(.+?)" />~i',
				'~<img src=\'~i',
				'~\' border=\'0\' alt=\'(.+?)\'( /)?' . '>~i',
				'~<img src="~i',
				'~" border="0" alt="(.+?)"( /)?' . '>~i',
				'~<!--emo&(.+?)-->.+?<!--endemo-->~i',
				'~<strike>.+?</strike>~is',
				'~<a href="mailto:.+?">.+?</a>~is',
				'~<a href="(.+?)" target="_blank">(.+?)</a>~is',
				'~<a href=\'(.+?)\' target=\'_blank\'>(.+?)</a>~is',
				'~<p>(.+?)</p>~is',
			),
			array(
				'[quote]',
				'[/quote]',
				'[quote=$3]',
				'[quote]',
				'[code]',
				'[/code]',
				'[color=$1]',
				'[color=$1]',
				'[/color]',
				'[font=$1]',
				'[font=$1]',
				'[/font]',
				'[size=$1]',
				'[size=$1]',
				'[size=$1]',
				'[/size]',
				'[$1list]',
				'[list type=lower-alpha]',
				'[list type=upper-alpha]',
				'[list type=decimal]',
				'[list type=lower-roman]',
				'[list type=upper-roman]',
				'[/list]',
				'$2',
				'[img]',
				'[/img]',
				'[img]',
				'[/img]',
				'$1',
				'[s]$1[/s]',
				'[email=$1]$2[/email]',
				'[url=$1]$2[/url]',
				'[url=$1]$2[/url]',
				'$1<br />',
			), ltrim(stripslashes($text)));
		return strtr(strtr($text, '<>', '[]'), array('[br /]' => '<br />'));
	}

	protected function additionalGroups($string)
	{
		$temp = explode(',', $string);
		$groups = array();
		foreach ($temp as $grp)
		{
			$groups[] = $this->mapGroups($grp);
		}
		$groups = array_filter($groups, function($val) { return $val !== false; });
		return implode(',', array_unique($groups));
	}

	protected function mapGroups($group_id)
	{
		$this->createGroupsTranslation();

		if (empty($group_id))
			return;

		if (isset($this->groupsTranslation[$group_id]))
			$group = $this->groupsTranslation[$group_id];
		elseif ($group_id > 5)
			$group = $group_id + 3;
		else
			$group = $group_id;

		return $group;
	}

	protected function createGroupsTranslation()
	{
		if ($this->groupsTranslation !== null)
			return;

		$this->groupsTranslation = array(
// 		$this->fetchSetting('banned_group') => ???,
			$this->fetchSetting('admin_group') => 1,
			$this->fetchSetting('guest_group') => -1,
			$this->fetchSetting('member_group') => 0,
// 			$this->fetchSetting('auth_group') => 1,
		);
		$this->is_banned = $this->fetchSetting('banned_group');
		$this->is_disabled = $this->fetchSetting('auth_group');
	}

	protected function mapPermissionGroups($group_id)
	{
		$this->createPermissionGroupsTranslation();

		if (empty($group_id))
			return;

		if (isset($this->permissionGroupsTranslation[$group_id]))
			$group = $this->permissionGroupsTranslation[$group_id];
		elseif ($group_id > 5)
			$group = $group_id + 3;
		else
			$group = $group_id;

		return $group;
	}

	protected function createPermissionGroupsTranslation()
	{
		if ($this->permissionGroupsTranslation !== null)
			return;

		$known = array(
// 			'Validating Forum Set' => ???,
			'Member Forum Set' => 0,
			'Guest Forum Set' => -1,
			'Admin Forum Set' => 1,
			'Banned Forum Set' => 3,
			'Moderator Forum Set' => 2,
		);

		$result = $this->db->query("
			SELECT perm_id, perm_name
			FROM {$this->config->from_prefix}forum_perms");

		$this->permissionGroupsTranslation = array();
		// We want to stay on the safe side: either set it to a group we know, or admin-only
		while ($row = $this->db->fetch_assoc($result))
			$this->permissionGroupsTranslation[$row['perm_id']] = isset($known[$row['perm_name']]) ? $known[$row['perm_name']] : 1;
		$this->db->free_result($result);
	}

	protected function getAttachDir()
	{
		$result = $this->db->query("
			SELECT conf_value
			FROM {$this->config->from_prefix}core_sys_conf_settings
			WHERE conf_key = 'upload_dir'
			LIMIT 1");
		list ($attach_dir) = $this->db->fetch_row($result);
		$this->db->free_result($result);

		return $attach_dir;
	}

	/**
	 * From here on, all the methods are needed helper for the conversion
	 */
	public function preparseMembers($originalRows)
	{
		$rows = array();

		$this->createGroupsTranslation();

		foreach ($originalRows as $row)
		{
			// @todo check avatars conversion
			// @todo find a way to port titles (post-based groups)
			// @todo warn_level what is the value?

			$row['signature'] = $this->html2bbc($row['signature']);

			$row['additional_groups'] = $this->additionalGroups($row['additional_groups']);

			$row['date_registered'] = date('Y-m-d', $row['date_registered']);

			$row['id_group'] = $this->mapGroups($row['id_group']);

			// @todo verify
			if ($this->is_disabled)
				$row['is_activated'] = 0;

			if ($this->is_banned)
				$row['is_activated'] = $row['is_activated'] + 10;

			// That's pretty much a guess, @todo verify!
			if (!empty($row['pm_ignore_list']))
				$row['pm_ignore_list'] = implode(',', unserialize($row['pm_ignore_list']));

			$rows[] = $row;
		}

		return $rows;
	}

	public function preparseMessages($originalRows)
	{
		$rows = array();

		foreach ($originalRows as $row)
		{
			$row['body'] = $this->html2bbc($row['body']);

			$rows[] = $row;
		}

		return $rows;
	}

	public function preparseAttachments($originalRows)
	{
		$rows = array();
		$attach_dir = $this->getAttachDir();

		foreach ($originalRows as $row)
		{
			$row['full_path'] = $attach_dir . '/' . $row['attach_location'];
			unset($row['attach_location']);

			$rows[] = $row;
		}

		return $rows;
	}

	public function preparseBoards($originalRows)
	{
		$rows = array();
		$request = $this->db->query("
			SELECT id AS id_cat
			FROM {$this->config->from_prefix}forums
			WHERE parent_id = -1");

		$cats = array();
		while ($row = $this->db->fetch_assoc($request))
			$cats[] = $row['id_cat'];
		$this->db->free_result($request);

		foreach ($originalRows as $row)
		{
			if ($row['perm_view'] == '*')
			{
				$this->createPermissionGroupsTranslation();
				$groups = $this->permissionGroupsTranslation;
			}
			else
			{
				$perm_view = array_filter(explode(',', $row['perm_view']));
				$groups = array();
				foreach ($perm_view as $perm)
					$groups[] = $this->mapPermissionGroups($perm);
			}
			unset($row['perm_view']);
			$row['member_groups'] = implode(',', array_unique($groups));

			if (isset($cats[$row['id_parent']]))
			{
				$row['id_cat'] = $cats[$row['id_parent']];
				$row['id_parent'] = 0;
			}

			$rows[] = $row;
		}

		return $rows;
	}

	public function preparseTopics($originalRows)
	{
		$rows = array();

		foreach ($originalRows as $row)
		{
			$request = $this->db->query("
				SELECT COUNT(*)
				FROM {$this->config->from_prefix}posts
				WHERE topic_id = $row[id_topic]
					AND queued = 1");
			list ($unapproved) = $this->db->fetch_row($request);
			$this->db->free_result($request);

			$row['unapproved_posts'] = (int) $unapproved;

			$row['approved'] = $row['approved'] > 3 ? 3 : $row['approved'];

			$rows[] = $row;
		}

		return $rows;
	}

	public function preparsePolloptions($originalRows)
	{
		$rows = array();

		foreach ($originalRows as $row)
		{
			$choices = @unserialize(stripslashes($row['choices']));

			if (is_array($choices))
			{
				foreach ($choices as $choice)
				{
					// Put the slashes back
					$choice = addslashes_recursive($choice);

					// Now go ahead with our choices and votes
					foreach($choice['choice'] AS $choiceid => $label)
					{
						// The keys of the id_choice array correspond to the keys of the choice array,
						// which are the id_choice values
						$votes = $choice['votes'][$choiceid];

						// @todo: try to work around the multiple-questions-per-poll issue...
/*						if(isset($current_choices[$row['id_poll']][$choiceid]))
							continue;
						else
							$current_choices[$row['id_poll']][$choiceid] = $label;*/

						// Finally - a row of information!
						$rows[] = array(
							'id_poll' => $row['pid'],
							'id_choice' => $choiceid,
							'label' => addslashes($label),
							'votes' => $votes,
						);
					}
				}
			}
		}

		return $rows;
	}

	public function preparsePollvotes($originalRows)
	{
		$rows = array();

		foreach ($originalRows as $row)
		{
			$choices = @unserialize($row['member_choices']);

			if (is_array($choices))
			{
				foreach ($choices as $pid => $pchoices)
				{
					if ($row['id_poll'] != $pid)
						continue;

					foreach ($pchoices as $choice)
					{
						// Finally - a row of information!
						$rows[] = array(
							'id_poll' => $row['id_poll'],
							'id_member' => $row['id_member'],
							'id_choice' => $choice,
						);
					}
				}
			}
		}

		return $rows;
	}

	public function preparsePm($originalRows)
	{
		$rows = array();

		foreach ($originalRows as $row)
		{
			$row['body'] = $this->html2bbc($row['body']);

			$rows[] = $row;
		}

		return $rows;
	}

	public function preparsePmrecipients($originalRows)
	{
		$rows = array();

		foreach ($originalRows as $row)
		{
			$invited_members = @unserialize($row['mt_invited_members']);

			$rows[] = array(
				'id_pm' => $row['id_pm'],
				'id_member' => ($row['msg_author_id'] == $row['id_member']) ? $row['mt_starter_id'] : $row['id_member'],
				'labels' => $row['labels'],
				'is_read' => $row['is_read'],
			);

			if (is_array($invited_members) && !empty($invited_members))
			{
				foreach ($invited_members as $invited => $id)
				{
					if (!empty($invited))
					{
						$rows[] = array(
							'id_pm' => $row['id_pm'],
							'id_member' => ($row['msg_author_id'] == $id) ? $row['mt_starter_id'] : $id,
							'labels' => $row['labels'],
							'is_read' => $row['is_read'],
						);
					}
				}
			}
		}

		return $rows;
	}

	public function preparseBoardmods($originalRows)
	{
		$rows = array();

		foreach ($originalRows as $row)
		{
			if (!empty($row['id_member']))
				$rows[] = $row;
		}

		return $rows;
	}

	public function codeCopysmiley()
	{
		$source_base_dir = $this->config->path_from . '/public/style_emoticons';

		if (!empty($source_base_dir) && file_exists($source_base_dir))
		{
			$request = $this->db->query("
				SELECT image, emo_set
				FROM {$this->config->from_prefix}emoticons
				WHERE variable = 'smileys_dir';");

			$smiley = array();
			while ($row = $this->db->fetch_assoc($request))
			{
				if (file_exists($source_base_dir . '/' . $row['emo_set'] . '/' . $row['image']))
				{
					$smiley[] = array(
						'basedir' => $source_base_dir,
						'full_path' => $source_base_dir . '/' . $row['emo_set'],
						'filename' => $row['image'],
					);
				}
			}
		}

		if (!empty($smiley))
			return array($smiley);
		else
			return false;
	}

	public function preparseCustomfields($originalRows)
	{
		$rows = array();

		foreach ($originalRows as $row)
		{
			if ($row['field_type'] == 'drop')
				$row['field_type'] = 'select';

			if (!empty($row['field_options']))
			{
				$options = explode('|', $row['field_options']);
				$row['field_options'] = array();
				foreach ($options as $option)
				{
					list($key, $val) = explode('=', $option);
					$row['field_options'][$key] = $val;
				}
			}

			if (empty($row['pf_member_hide']) && !empty($row['pf_member_edit']) && empty($row['pf_admin_only']))
				$row['private'] = 0;
			elseif (!empty($row['pf_member_hide']) && empty($row['pf_member_edit']) && !empty($row['pf_admin_only']))
				$row['private'] = 3;
			// @todo this is dubious, probably pf_admin_only should be empty as well (or ignored)
			elseif (empty($row['pf_member_hide']) && (empty($row['pf_member_edit']) || !empty($row['pf_admin_only'])))
				$row['private'] = 1;
			elseif (!empty($row['pf_member_hide']) && !empty($row['pf_member_edit']) && empty($row['pf_admin_only']))
				$row['private'] = 2;
			// In case we don't know: most restrictive
			else
				$row['private'] = 3;

			unset($row['pf_member_hide'], $row['pf_member_edit'], $row['pf_admin_only']);

			$rows[] = $row;
		}

		return $rows;
	}

	public function preparseCustomfieldsdata($originalRows)
	{
		$rows = array();
		$fields = array();

		$request = $this->db->query("
			SELECT pf_id, pf_key
			FROM {$this->config->from_prefix}pfields_data");

		while ($row = $this->db->fetch_assoc($request))
			$fields[$row['pf_id']] = $row;

		foreach ($originalRows as $row)
		{
			foreach ($row as $key => $val)
			{
				if (substr($key, 0, 5) == 'field')
				{
					$rows[] = array(
						'id_member' => $row['member_id'],
						'variable' => $fields[substr($key, 6)]['pf_key'],
						'value' => $val,
					);
				}
			}
		}

		return $rows;
	}
}
