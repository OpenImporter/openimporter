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

use OpenImporter\Core\Utils;

class ImporterStep2 extends \OpenImporter\Importers\destinations\SmfCommonOriginStep2
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

			Utils::pastTime(0);
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

			Utils::pastTime(0);
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

			Utils::pastTime(0);
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

			Utils::pastTime(0);
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

			Utils::pastTime(0);
		}
		$this->db->free_result($request);
	}
}