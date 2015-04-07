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

use OpenImporter\Core\Utils;

/**
 * Recount statistics, and fixes stuff.
 */
class ImporterStep2 extends \OpenImporter\Importers\destinations\SmfCommonOriginStep2
{
	public function substep0()
	{
		// Get all members with wrong number of personal messages.
		$request = $this->db->query("
			SELECT mem.id_member, COUNT(pmr.id_pm) AS real_num, mem.instant_messages
			FROM {$this->config->to_prefix}members AS mem
				LEFT JOIN {$this->config->to_prefix}pm_recipients AS pmr ON (mem.id_member = pmr.id_member AND pmr.deleted = 0)
			GROUP BY mem.id_member
			HAVING real_num != instant_messages");
		while ($row = $this->db->fetch_assoc($request))
		{
			$this->db->query("
				UPDATE {$this->config->to_prefix}members
				SET instant_messages = $row[real_num]
				WHERE id_member = $row[id_member]
				LIMIT 1");

			$this->config->progress->pastTime(0);
		}
		$this->db->free_result($request);

		$request = $this->db->query("
			SELECT mem.id_member, COUNT(pmr.id_pm) AS real_num, mem.unread_messages
			FROM {$this->config->to_prefix}members AS mem
				LEFT JOIN {$this->config->to_prefix}pm_recipients AS pmr ON (mem.id_member = pmr.id_member AND pmr.deleted = 0 AND pmr.is_read = 0)
			GROUP BY mem.id_member
			HAVING real_num != unread_messages");
		while ($row = $this->db->fetch_assoc($request))
		{
			$this->db->query("
				UPDATE {$this->config->to_prefix}members
				SET unread_messages = $row[real_num]
				WHERE id_member = $row[id_member]
				LIMIT 1");

			$this->config->progress->pastTime(0);
		}
		$this->db->free_result($request);
	}

	public function substep12()
	{
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
			$this->db->alter_table("{$this->config->to_prefix}topics", $index_info);

		$_REQUEST['start'] = 0;
		$this->config->progress->pastTime(13);
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
			$this->db->alter_table("{$this->config->to_prefix}messages", $index_info);

		$_REQUEST['start'] = 0;
		$this->config->progress->pastTime(14);
	}
}