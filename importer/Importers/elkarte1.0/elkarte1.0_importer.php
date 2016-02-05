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

/**
 * The class contains code that allows the Importer to obtain settings
 * from the ElkArte installation.
 */
class elkarte1_0_importer extends Importers\SmfCommonSource
{
	/**
	 * @var string
	 */
	public $attach_extension = 'elk';

	/**
	 * @return string
	 */
	public function getName()
	{
		return 'ElkArte 1.0';
	}
}

class elkarte1_0_importer_step1 extends Importers\SmfCommonSourceStep1
{
}

class elkarte1_0_importer_step2 extends Importers\SmfCommonSourceStep2
{
	/**
	 * Repair any wrong number of personal messages
	 */
	public function substep0()
	{
		$to_prefix = $this->config->to_prefix;

		// Get all members with wrong number of personal messages and fix it
		$request = $this->db->query("
			SELECT
				mem.id_member, COUNT(pmr.id_pm) AS real_num, mem.personal_messages
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
			SELECT
				mem.id_member, COUNT(pmr.id_pm) AS real_num, mem.unread_messages
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

	/**
	 * Count the topic likes based on first message id
	 */
	public function substep100()
	{
		$to_prefix = $this->config->to_prefix;

		// Set the number of topic likes based on likes to the first message in the topic
		$request = $this->db->query("
			SELECT
				COUNT(*) AS count, t.id_topic
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

	/**
	 * Validate / Update member likes received
	 */
	public function substep101()
	{
		$to_prefix = $this->config->to_prefix;

		// Update the likes each member has received based on liked messages
		$request = $this->db->query("
			SELECT
				COUNT(*) AS count, id_poster
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

	/**
	 * Validate / Update likes given by a member
	 */
	public function substep102()
	{
		$to_prefix = $this->config->to_prefix;

		// Update the likes each member has given
		$request = $this->db->query("
			SELECT
				COUNT(*) AS count, id_member
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

class elkarte1_0_importer_step3 extends Importers\SmfCommonSourceStep3
{
}