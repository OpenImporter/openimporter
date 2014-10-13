<?php

class elkarte_to_smf20
{
	/**
	 * collects all the important things, the importer can't do anything
	 * without this information.
	 *
	 * @global type $to_prefix
	 * @global type $import_script
	 * @global type $cookie
	 * @param type $error_message
	 * @param type $object
	 * @return boolean|null
	 */
	public function doStep0($error_message = null, $object = false)
	{
		// If these aren't set (from an error..) default to the current directory.
		if (!isset($_POST['path_from']))
			$_POST['path_from'] = dirname(__FILE__);
		if (!isset($_POST['path_to']))
			$_POST['path_to'] = dirname(__FILE__);

		$test_from = empty($this->xml->general->settings);

		foreach ($this->xml->general->settings as $settings_file)
			$test_from |= @file_exists($_POST['path_from'] . $settings_file);

		$test_to = @file_exists($_POST['path_to'] . '/Settings.php');

		// Was an error message specified?
		if ($error_message !== null)
		{
			// @todo why not re-use $this->template?
			$template = new Template();
			$template->header(false);
			$template->error($error_message);
		}

		$this->use_template = 'step0';
		$this->params_template = array($this, $this->_find_steps(), $test_from, $test_to);

		if ($error_message !== null)
		{
			$template->footer();
			exit;
		}

		return;
	}

	/**
	 * the important one, transfer the content from the source forum to our
	 * destination system
	 *
	 * @global type $to_prefix
	 * @global type $global
	 * @return boolean
	 */
	public function doStep1()
	{
		global $to_prefix;

		if ($this->xml->general->globals)
			foreach (explode(',', $this->xml->general->globals) as $global)
				global $$global;

		$this->cookie->set(array($_POST['path_to'], $_POST['path_from']));
		$current_data = '';
		$substep = 0;
		$special_table = null;
		$special_code = null;
		$_GET['substep'] = isset($_GET['substep']) ? (int) @$_GET['substep'] : 0;
		// @TODO: check if this is needed
		//$progress = ($_GET['substep'] ==  0 ? 1 : $_GET['substep']);

		// Skipping steps?
		if (isset($_SESSION['do_steps']))
			$do_steps = $_SESSION['do_steps'];

		//calculate our overall time and create the progress bar
		if(!isset($_SESSION['import_overall']))
		{
			$progress_counter = 0;
			$counter_current_step = 0;

			// loop through each step
			foreach ($this->xml->step as $counts)
			{
				if ($counts->detect)
				{
					$count = $this->_fix_params((string) $counts->detect);
					$request = $this->db->query("
						SELECT COUNT(*)
						FROM $count", true);

					if (!empty($request))
					{
						list ($current) = $this->db->fetch_row($request);
						$this->db->free_result($request);
					}

					$progress_counter = $progress_counter + $current;

					$_SESSION['import_steps'][$counter_current_step]['counter'] = $current;
				}
				$counter_current_step++;
			}
			$_SESSION['import_overall'] = $progress_counter;
		}
		if(!isset($_SESSION['import_progress']))
			$_SESSION['import_progress'] = 0;

		foreach ($this->xml->step as $steps)
		{
			// Reset some defaults
			$current_data = '';
			$special_table = null;
			$special_code = null;

			// Increase the substep slightly...
			pastTime(++$substep);

			$_SESSION['import_steps'][$substep]['title'] = (string) $steps->title;
			if (!isset($_SESSION['import_steps'][$substep]['status']))
				$_SESSION['import_steps'][$substep]['status'] = 0;

			// any preparsing code here?
			if (isset($steps->preparsecode) && !empty($steps->preparsecode))
				$special_code = $this->_fix_params((string) $steps->preparsecode);

			$do_current = $substep >= $_GET['substep'];

			if (!in_array($substep, $do_steps))
			{
				$_SESSION['import_steps'][$substep]['status'] = 2;
				$_SESSION['import_steps'][$substep]['presql'] = true;
			}
			// Detect the table, then count rows.. 
			elseif ($steps->detect)
			{
				$count = $this->_fix_params((string) $steps->detect);
				$table_test = $this->db->query("
					SELECT COUNT(*)
					FROM $count", true);

				if ($table_test === false)
				{
					$_SESSION['import_steps'][$substep]['status'] = 3;
					$_SESSION['import_steps'][$substep]['presql'] = true;
				}
			}

			$this->template->status($substep, $_SESSION['import_steps'][$substep]['status'], $_SESSION['import_steps'][$substep]['title']);

			// do we need to skip this step?
			if ((isset($table_test) && $table_test === false) || !in_array($substep, $do_steps))
			{
				// reset some defaults
				$current_data = '';
				$special_table = null;
				$special_code = null;
				continue;
			}

			// pre sql queries first!!
			if (isset($steps->presql) && !isset($_SESSION['import_steps'][$substep]['presql']))
			{
				$presql = $this->_fix_params((string) $steps->presql);
				$presql_array = explode(';', $presql);
				if (isset($presql_array) && is_array($presql_array))
				{
					array_pop($presql_array);
					foreach ($presql_array as $exec)
						$this->db->query($exec . ';');
				}
				else
					$this->db->query($presql);
				// don't do this twice..
				$_SESSION['import_steps'][$substep]['presql'] = true;
			}

			if ($special_table === null)
			{
				$special_table = strtr(trim((string) $steps->destination), array('{$to_prefix}' => $this->to_prefix));
				$special_limit = 500;
			}
			else
				$special_table = null;

			if (isset($steps->query))
				$current_data = substr(rtrim($this->_fix_params((string) $steps->query)), 0, -1);

			if (isset($steps->options->limit))
				$special_limit = $steps->options->limit;

			if (!$do_current)
			{
				$current_data = '';
				continue;
			}

			// codeblock?
			if (isset($steps->code))
			{
				// execute our code block
				$special_code = $this->_fix_params((string) $steps->code);
				eval($special_code);
				// reset some defaults
				$current_data = '';
				$special_table = null;
				$special_code = null;
				if ($_SESSION['import_steps'][$substep]['status'] == 0)
					$this->template->status($substep, 1, false, true);
				$_SESSION['import_steps'][$substep]['status'] = 1;
				flush();
				continue;
			}

			// sql block?
			if (!empty($steps->query))
			{
				if (strpos($current_data, '{$') !== false)
					$current_data = eval('return "' . addcslashes($current_data, '\\"') . '";');

				if (isset($steps->detect))
				{
					$counter = 0;

					$count = $this->_fix_params((string) $steps->detect);
					$result2 = $this->db->query("
						SELECT COUNT(*)
						FROM $count");
					list ($counter) = $this->db->fetch_row($result2);
					//$this->count->$substep = $counter;
					$this->db->free_result($result2);
				}

				// create some handy shortcuts
				$ignore = ((isset($steps->options->ignore) && $steps->options->ignore == false) || isset($steps->options->replace)) ? false : true;
				$replace = (isset($steps->options->replace) && $steps->options->replace == true) ? true : false;
				$no_add = (isset($steps->options->no_add) && $steps->options->no_add == true) ? true : false;
				$ignore_slashes = (isset($steps->options->ignore_slashes) && $steps->options->ignore_slashes == true) ? true : false;

				if (isset($import_table) && $import_table !== null && strpos($current_data, '%d') !== false)
				{
					preg_match('~FROM [(]?([^\s,]+)~i', (string) $steps->detect, $match);
					if (!empty($match))
					{
						$result = $this->db->query("
							SELECT COUNT(*)
							FROM $match[1]");
						list ($special_max) = $this->db->fetch_row($result);
						$this->db->free_result($result);
					}
					else
						$special_max = 0;
				}
				else
					$special_max = 0;

				if ($special_table === null)
					$this->db->query($current_data);

				else
				{
					// Are we doing attachments? They're going to want a few things...
					if ($special_table == $this->to_prefix . 'attachments')
					{
						if (!isset($id_attach, $attachmentUploadDir, $avatarUploadDir))
						{
							$result = $this->db->query("
								SELECT MAX(id_attach) + 1
								FROM {$to_prefix}attachments");
							list ($id_attach) = $this->db->fetch_row($result);
							$this->db->free_result($result);

							$result = $this->db->query("
								SELECT value
								FROM {$to_prefix}settings
								WHERE variable = 'attachmentUploadDir'
								LIMIT 1");
							list ($attachmentdir) = $this->db->fetch_row($result);
							$attachment_UploadDir = @unserialize($attachmentdir);
							$attachmentUploadDir = !empty($attachment_UploadDir[1]) && is_array($attachment_UploadDir[1]) ? $attachment_UploadDir[1] : $attachmentdir;

							$result = $this->db->query("
								SELECT value
								FROM {$to_prefix}settings
								WHERE variable = 'custom_avatar_dir'
								LIMIT 1");
							list ($avatarUploadDir) = $this->db->fetch_row($result);
							$this->db->free_result($result);

							if (empty($avatarUploadDir))
								$avatarUploadDir = $attachmentUploadDir;

							if (empty($id_attach))
								$id_attach = 1;
						}
					}

					while (true)
					{
						pastTime($substep);

						if (strpos($current_data, '%d') !== false)
							$special_result = $this->db->query(sprintf($current_data, $_REQUEST['start'], $_REQUEST['start'] + $special_limit - 1) . "\n" . 'LIMIT ' . $special_limit);
						else
							$special_result = $this->db->query($current_data . "\n" . 'LIMIT ' . $_REQUEST['start'] . ', ' . $special_limit);

						$rows = array();
						$keys = array();

						if (isset($steps->detect))
							$_SESSION['import_progress'] += $special_limit;

						while ($row = $this->db->fetch_assoc($special_result))
						{
							if ($special_code !== null)
								eval($special_code);

							// Here we have various bits of custom code for some known problems global to all importers.
							if ($special_table == $this->to_prefix . 'members')
							{
								// Let's ensure there are no illegal characters.
								$row['member_name'] = preg_replace('/[<>&"\'=\\\]/is', '', $row['member_name']);
								$row['real_name'] = trim($row['real_name'], " \t\n\r\x0B\0\xA0");

								if (strpos($row['real_name'], '<') !== false || strpos($row['real_name'], '>') !== false || strpos($row['real_name'], '& ') !== false)
									$row['real_name'] = htmlspecialchars($row['real_name'], ENT_QUOTES);
								else
									$row['real_name'] = strtr($row['real_name'], array('\'' => '&#039;'));
							}

							// this is wedge specific stuff and will move at some point.
							// prepare ip address conversion
							if (isset($this->xml->general->ip_to_ipv6))
							{
								$convert_ips = explode(',', $this->xml->general->ip_to_ipv6);
								foreach ($convert_ips as $ip)
								{
									$ip = trim($ip);
									if (array_key_exists($ip, $row))
										$row[$ip] = $this->_prepare_ipv6($row[$ip]);
								}
							}
							// prepare ip address conversion to a pointer
							if (isset($this->xml->general->ip_to_pointer))
							{
								$ips_to_pointer = explode(',', $this->xml->general->ip_to_pointer);
								foreach ($ips_to_pointer as $ip)
								{
									$ip = trim($ip);
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
							}
							// fixing the charset, we need proper utf-8
							$row = fix_charset($row);

							// If we have a message here, we'll want to convert <br /> to <br>.
							if (isset($row['body']))
								$row['body'] = str_replace(array(
										'<br />', '&#039;', '&#39;', '&quot;'
									), array(
										'<br>', '\'', '\'', '"'
									), $row['body']
								);

							if (empty($no_add) && empty($ignore_slashes))
								$rows[] = "'" . implode("', '", addslashes_recursive($row)) . "'";
							elseif (empty($no_add) && !empty($ignore_slashes))
								$rows[] = "'" . implode("', '", $row) . "'";
							else
								$no_add = false;

							if (empty($keys))
								$keys = array_keys($row);
						}

						$insert_ignore = (isset($ignore) && $ignore == true) ? 'IGNORE' : '';
						$insert_replace = (isset($replace) && $replace == true) ? 'REPLACE' : 'INSERT';

						if (!empty($rows))
							$this->db->query("
								$insert_replace $insert_ignore INTO $special_table
									(" . implode(', ', $keys) . ")
								VALUES (" . implode('),
									(', $rows) . ")");
						$_REQUEST['start'] += $special_limit;
						if (empty($special_max) && $this->db->num_rows($special_result) < $special_limit)
							break;
						elseif (!empty($special_max) && $this->db->num_rows($special_result) == 0 && $_REQUEST['start'] > $special_max)
							break;
						$this->db->free_result($special_result);
					}
				}
				$_REQUEST['start'] = 0;
				$special_code = null;
				$current_data = '';
			}
			if ($_SESSION['import_steps'][$substep]['status'] == 0)
				$this->template->status($substep, 1, false, true);

			$_SESSION['import_steps'][$substep]['status'] = 1;
			flush();
		}

		$_GET['substep'] = 0;
		$_REQUEST['start'] = 0;

		return $this->doStep2();
	}

	/**
	 * we have imported the old database, let's recalculate the forum statistics.
	 *
	 * @global Database $db
	 * @global type $to_prefix
	 * @return boolean
	 */
	public function doStep2()
	{
		global $db, $to_prefix;

		$_GET['step'] = '2';

		$this->template->step2();

		if ($_GET['substep'] <= 0)
		{
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

			pastTime(1);
		}

		if ($_GET['substep'] <= 1)
		{
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

			pastTime(2);
		}

		if ($_GET['substep'] <= 2)
		{
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

			pastTime(3);
		}

		if ($_GET['substep'] <= 3)
		{
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

			pastTime(4);
		}

		if ($_GET['substep'] <= 4)
		{
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

			pastTime(5);
		}

		if ($_GET['substep'] <= 5)
		{
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

			pastTime(6);
		}

		// Remove all topics that have zero messages in the messages table.
		if ($_GET['substep'] <= 6)
		{
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

				$_REQUEST['start'] += 200;
				pastTime(6);
			}

			$_REQUEST['start'] = 0;
			pastTime(7);
		}

		// Get the correct number of replies.
		if ($_GET['substep'] <= 7)
		{
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
					$memberStartedID = getMsgMemberID($topicArray['myid_first_msg']);
					$memberUpdatedID = getMsgMemberID($topicArray['myid_last_msg']);

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

				$_REQUEST['start'] += 100;
				pastTime(7);
			}

			$_REQUEST['start'] = 0;
			pastTime(8);
		}

		// Fix id_cat, id_parent, and child_level.
		if ($_GET['substep'] <= 8)
		{
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

			pastTime(9);
		}

		if ($_GET['substep'] <= 9)
		{
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

			pastTime(10);
		}

		if ($_GET['substep'] <= 10)
		{
			$this->db->query("
				ALTER TABLE {$to_prefix}boards
				ORDER BY board_order");

			$this->db->query("
				ALTER TABLE {$to_prefix}smileys
				ORDER BY code DESC");

			pastTime(11);
		}

		if ($_GET['substep'] <= 11)
		{
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
						$filename = getLegacyAttachmentFilename($row['filename'], $row['id_attach']);

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

			$_REQUEST['start'] = 0;
			pastTime(12);
		}

		$this->template->status(12, 1, false, true);

		return $this->doStep3();
	}

	/**
	 * we are done :)
	 *
	 * @global Database $db
	 * @global type $boardurl
	 * @return boolean
	 */
	public function doStep3()
	{
		global $db, $boardurl;

		$to_prefix = $this->to_prefix;

		// add some importer information.
		$this->db->query("
			REPLACE INTO {$to_prefix}settings (variable, value)
				VALUES ('import_time', " . time() . "),
					('enable_password_conversion', '1'),
					('imported_from', '" . $_SESSION['import_script'] . "')");

		$writable = (is_writable(dirname(__FILE__)) && is_writable(__FILE__));

		$this->use_template = 'step3';
		$this->params_template = array($this->xml->general->name, $boardurl, $writable);

		unset ($_SESSION['import_steps'], $_SESSION['import_progress'], $_SESSION['import_overall']);
		return true;
	}
}