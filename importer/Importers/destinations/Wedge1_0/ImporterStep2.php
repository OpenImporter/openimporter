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
}