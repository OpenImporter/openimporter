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

class elkarte1_0_importer
{
	public function getDestinationURL($path)
	{
		global $boardurl;

		// Cannot find Settings.php?
		if (!file_exists($path . '/Settings.php'))
			return false;

		// Everything should be alright now... no cross server includes, we hope...
		require_once($path . '/Settings.php');

		return $boardurl;
	}

	public function verifyDbPass($pwd_to_verify)
	{
		global $db_passwd;

		return $db_passwd != $pwd_to_verify;
	}
}

class elkarte1_0_importer_step1 extends Step1BaseImporter
{
	protected $id_attach = null;
	protected $attachmentUploadDir = null;
	protected $avatarUploadDir = null;

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
		if ($special_table == $this->to_prefix . 'attachments')
			$this->specialAttachments();
		// Here we have various bits of custom code for some known problems global to all importers.
		elseif ($special_table == $this->to_prefix . 'members')
			$this->specialMembers($params);
	}

	protected function removeAttachments()
	{
		$to_prefix = $this->to_prefix;

		$this->specialAttachments();

		$result = $this->db->query("
			SELECT value
			FROM {$to_prefix}settings
			WHERE variable = 'attachmentUploadDir'
			LIMIT 1");
		list ($this->attachmentUploadDir) = $this->db->fetch_row($result);
		$this->free_result($result);

		// !!! This should probably be done in chunks too.
		$result = $this->db->query("
			SELECT id_attach, filename
			FROM {$to_prefix}attachments");
		while ($row = $this->db->fetch_assoc($result))
		{
			// We're duplicating this from below because it's slightly different for getting current ones.
			$clean_name = strtr($row['filename'], 'ŠŽšžŸÀÁÂÃÄÅÇÈÉÊËÌÍÎÏÑÒÓÔÕÖØÙÚÛÜÝàáâãäåçèéêëìíîïñòóôõöøùúûüýÿ', 'SZszYAAAAAACEEEEIIIINOOOOOOUUUUYaaaaaaceeeeiiiinoooooouuuuyy');
			$clean_name = strtr($clean_name, array('Þ' => 'TH', 'þ' => 'th', 'Ð' => 'DH', 'ð' => 'dh', 'ß' => 'ss', 'Œ' => 'OE', 'œ' => 'oe', 'Æ' => 'AE', 'æ' => 'ae', 'µ' => 'u'));
			$clean_name = preg_replace(array('/\s/', '/[^\w_\.\-]/'), array('_', ''), $clean_name);
			$enc_name = $row['id_attach'] . '_' . strtr($clean_name, '.', '_') . md5($clean_name) . '.ext';
			$clean_name = preg_replace('~\.[\.]+~', '.', $clean_name);

			if (file_exists($this->attachmentUploadDir . '/' . $enc_name))
				$filename = $this->attachmentUploadDir . '/' . $enc_name;
			else
				$filename = $this->attachmentUploadDir . '/' . $clean_name;

			if (is_file($filename))
				unlink($filename);
		}
		$this->free_result($result);
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

	protected function specialAttachments()
	{
		$to_prefix = $this->to_prefix;

		if (!isset($this->id_attach, $this->attachmentUploadDir, $this->avatarUploadDir))
		{
			$result = $this->db->query("
				SELECT MAX(id_attach) + 1
				FROM {$to_prefix}attachments");
			list ($this->id_attach) = $this->db->fetch_row($result);
			$this->db->free_result($result);

			$result = $this->db->query("
				SELECT value
				FROM {$to_prefix}settings
				WHERE variable = 'attachmentUploadDir'
				LIMIT 1");
			list ($attachmentdir) = $this->db->fetch_row($result);
			$attachment_UploadDir = @unserialize($attachmentdir);
			$this->attachmentUploadDir = !empty($attachment_UploadDir[1]) && is_array($attachment_UploadDir[1]) ? $attachment_UploadDir[1] : $attachmentdir;

			$result = $this->db->query("
				SELECT value
				FROM {$to_prefix}settings
				WHERE variable = 'custom_avatar_dir'
				LIMIT 1");
			list ($this->avatarUploadDir) = $this->db->fetch_row($result);
			$this->db->free_result($result);

			if (empty($this->avatarUploadDir))
				$this->avatarUploadDir = $this->attachmentUploadDir;

			if (empty($this->id_attach))
				$this->id_attach = 1;
		}
	}
}

class elkarte1_0_importer_step2 extends Step2BaseImporter
{
	public function substep0()
	{
		$to_prefix = $this->to_prefix;

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

	public function substep1()
	{
		$to_prefix = $this->to_prefix;

		$request = $this->db->query("
			SELECT id_board, MAX(id_msg) AS id_last_msg, MAX(modified_time) AS last_edited
			FROM {$to_prefix}messages
			GROUP BY id_board");
		$modifyData = array();
		$modifyMsg = array();
		while ($row = $this->db->fetch_assoc($request))
		{
			$this->db->query("
				UPDATE {$to_prefix}boards
				SET id_last_msg = $row[id_last_msg], id_msg_updated = $row[id_last_msg]
				WHERE id_board = $row[id_board]
				LIMIT 1");
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

						$this->db->query("
							UPDATE {$to_prefix}boards
							SET id_msg_updated = $id_msg
							WHERE id_board = $row[id_board]
							LIMIT 1");
					}
					$this->db->free_result($request2);
				}
			}
			$this->db->free_result($request);
		}
	}

	public function substep2()
	{
		$to_prefix = $this->to_prefix;

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
			$this->db->query("
				UPDATE {$to_prefix}boards
				SET member_groups = '" . implode(',', array_unique(array_merge($all_groups, explode(',', $row['member_groups'])))) . "'
				WHERE id_board = $row[id_board]
				LIMIT 1");
		$this->db->free_result($request);
	}

	public function substep3()
	{
		$to_prefix = $this->to_prefix;

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
			$row += $this->db->fetch_assoc($result);
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

	public function substep4()
	{
		$to_prefix = $this->to_prefix;

		$request = $this->db->query("
			SELECT id_group, min_posts
			FROM {$to_prefix}membergroups
			WHERE min_posts != -1
			ORDER BY min_posts DESC");
		$post_groups = array();
		while ($row = $this->db->fetch_assoc($request))
			$post_groups[$row['min_posts']] = $row['id_group'];
		$this->db->free_result($request);

		$request = $this->db->query("
			SELECT id_member, posts
			FROM {$to_prefix}members");
		$mg_updates = array();
		while ($row = $this->db->fetch_assoc($request))
		{
			$group = 4;
			foreach ($post_groups as $min_posts => $group_id)
				if ($row['posts'] >= $min_posts)
				{
					$group = $group_id;
					break;
				}

			$mg_updates[$group][] = $row['id_member'];
		}
		$this->db->free_result($request);

		foreach ($mg_updates as $group_to => $update_members)
			$this->db->query("
				UPDATE {$to_prefix}members
				SET id_post_group = $group_to
				WHERE id_member IN (" . implode(', ', $update_members) . ")
				LIMIT " . count($update_members));
	}

	public function substep5()
	{
		$to_prefix = $this->to_prefix;

		// Needs to be done separately for each board.
		$result_boards = $this->db->query("
			SELECT id_board
			FROM {$to_prefix}boards");
		$boards = array();
		while ($row_boards = $this->db->fetch_assoc($result_boards))
			$boards[] = $row_boards['id_board'];
		$this->db->free_result($result_boards);

		foreach ($boards as $id_board)
		{
			// Get the number of topics, and iterate through them.
			$result_topics = $this->db->query("
				SELECT COUNT(*)
				FROM {$to_prefix}topics
				WHERE id_board = $id_board");
			list ($num_topics) = $this->db->fetch_row($result_topics);
			$this->db->free_result($result_topics);

			// Find how many messages are in the board.
			$result_posts = $this->db->query("
				SELECT COUNT(*)
				FROM {$to_prefix}messages
				WHERE id_board = $id_board");
			list ($num_posts) = $this->db->fetch_row($result_posts);
			$this->db->free_result($result_posts);

			// Fix the board's totals.
			$this->db->query("
				UPDATE {$to_prefix}boards
				SET num_topics = $num_topics, num_posts = $num_posts
				WHERE id_board = $id_board
				LIMIT 1");
		}
	}

	public function substep6()
	{
		$to_prefix = $this->to_prefix;

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
		$to_prefix = $this->to_prefix;

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
		$to_prefix = $this->to_prefix;

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

	public function substep8()
	{
		$to_prefix = $this->to_prefix;

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
				$fixed_boards[$board] = array($parent, $cat_map[$board], $level);
				$solid_parents[] = array($board, $level + 1);
			}
		}

		foreach ($fixed_boards as $board => $fix)
		{
			$this->db->query("
				UPDATE {$to_prefix}boards
				SET id_parent = " . (int) $fix[0] . ", id_cat = " . (int) $fix[1] . ", child_level = " . (int) $fix[2] . "
				WHERE id_board = " . (int) $board . "
				LIMIT 1");
		}

		// Leftovers should be brought to the root. They had weird parents we couldn't find.
		if (count($fixed_boards) < count($cat_map))
		{
			$this->db->query("
				UPDATE {$to_prefix}boards
				SET child_level = 0, id_parent = 0" . (empty($fixed_boards) ? '' : "
				WHERE id_board NOT IN (" . implode(', ', array_keys($fixed_boards)) . ")"));
		}

		// Last check: any boards not in a good category?
		$request = $this->db->query("
			SELECT id_cat
			FROM {$to_prefix}categories");
		$real_cats = array();
		while ($row = $this->db->fetch_assoc($request))
			$real_cats[] = $row['id_cat'];
		$this->db->free_result($request);

		$fix_cats = array();
		foreach ($cat_map as $board => $cat)
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

			$this->db->query("
				UPDATE {$to_prefix}boards
				SET id_cat = " . (int) $catch_cat . "
				WHERE id_cat IN (" . implode(', ', array_unique($fix_cats)) . ")");
		}
	}

	public function substep9()
	{
		$to_prefix = $this->to_prefix;

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
				$this->db->query("
					UPDATE {$to_prefix}boards
					SET board_order = $board_order
					WHERE id_board = $row[id_board]
					LIMIT 1");
		}
		$this->db->free_result($request);
	}

	public function substep11()
	{
		$to_prefix = $this->to_prefix;

		$request = $this->db->query("
			SELECT COUNT(*)
			FROM {$to_prefix}attachments");
		list ($attachments) = $this->db->fetch_row($request);
		$this->db->free_result($request);

		while ($_REQUEST['start'] < $attachments)
		{
			$request = $this->db->query("
				SELECT id_attach, filename, attachment_type
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
				if ($row['attachment_type'] == 1)
				{
					$request2 = $this->db->query("
						SELECT value
						FROM {$to_prefix}settings
						WHERE variable = 'custom_avatar_dir'
						LIMIT 1");
					list ($custom_avatar_dir) = $this->db->fetch_row($request2);
					$this->db->free_result($request2);

					$filename = $custom_avatar_dir . '/' . $row['filename'];
				}
				else
					$filename = $this->getLegacyAttachmentFilename($row['filename'], $row['id_attach']);

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

	/**
	 * helper function for old attachments
	 *
	 * @param string $filename
	 * @param int $attachment_id
	 * @return string
	 */
	protected function getLegacyAttachmentFilename($filename, $attachment_id)
	{
		// Remove special accented characters - ie. sí (because they won't write to the filesystem well.)
		$clean_name = strtr($filename, 'ŠŽšžŸÀÁÂÃÄÅÇÈÉÊËÌÍÎÏÑÒÓÔÕÖØÙÚÛÜÝàáâãäåçèéêëìíîïñòóôõöøùúûüýÿ', 'SZszYAAAAAACEEEEIIIINOOOOOOUUUUYaaaaaaceeeeiiiinoooooouuuuyy');
		$clean_name = strtr($clean_name, array('Þ' => 'TH', 'þ' => 'th', 'Ð' => 'DH', 'ð' => 'dh', 'ß' => 'ss', 'Œ' => 'OE', 'œ' => 'oe', 'Æ' => 'AE', 'æ' => 'ae', 'µ' => 'u'));
			// Get rid of dots, spaces, and other weird characters.
		$clean_name = preg_replace(array('/\s/', '/[^\w_\.\-]/'), array('_', ''), $clean_name);
			return $attachment_id . '_' . strtr($clean_name, '.', '_') . md5($clean_name);
	}
}

class elkarte1_0_importer_step3 extends Step3BaseImporter
{
	public function run($import_script)
	{
		$to_prefix = $this->to_prefix;

		// add some importer information.
		$this->db->query("
			REPLACE INTO {$to_prefix}settings (variable, value)
				VALUES ('import_time', " . time() . "),
					('enable_password_conversion', '1'),
					('imported_from', '" . $import_script . "')");
	}
}

/**
 * helper function to create an encrypted attachment name
 *
 * @param string $filename
 * @return string
 */
function createAttachmentFilehash($filename)
{
	return sha1(md5($filename . time()) . mt_rand());
}

/**
 * function copy_smileys is used to copy smileys from a source to destination.
 * @param type $source
 * @param type $dest
 * @return type
 */
function copy_smileys($source, $dest)
{
	if (!is_dir($source) || !($dir = opendir($source)))
		return;

	while ($file = readdir($dir))
	{
		if ($file == '.' || $file == '..')
			continue;

		// If we have a directory create it on the destination and copy contents into it!
		if (is_dir($source . DIRECTORY_SEPARATOR . $file))
		{
			if (!is_dir($dest))
				@mkdir($dest . DIRECTORY_SEPARATOR . $file, 0777);
			copy_dir($source . DIRECTORY_SEPARATOR . $file, $dest . DIRECTORY_SEPARATOR . $file);
		}
		else
		{
			if (!is_dir($dest))
				@mkdir($dest . DIRECTORY_SEPARATOR . $file, 0777);
			copy($source . DIRECTORY_SEPARATOR . $file, $dest . DIRECTORY_SEPARATOR . $file);
		}
	}
	closedir($dir);
}