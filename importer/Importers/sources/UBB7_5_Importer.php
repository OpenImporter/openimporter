<?php
/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 2.0 Alpha
 */

namespace OpenImporter\Importers\sources;

class UBB7_5_Importer extends \OpenImporter\Importers\AbstractSourceImporter
{
	protected $setting_file = '/includes/config.inc.php';

	public function getName()
	{
		return 'UBB Threads 7.5.x';
	}

	public function getVersion()
	{
		return '1.0';
	}

	public function getDbPrefix()
	{
		global $db_prefix;

		return '`' . $this->getDbName() . '`.' . $db_prefix;
	}

	public function getDbName()
	{
		global $db_name;

		return $db_name;
	}

	public function getTableTest()
	{
		return 'USERS';
	}

	/**
	 * From here on, all the methods are needed helper for the conversion
	 */
	public function preparseMembers($originalRows)
	{
		$rows = array();
		foreach ($originalRows as $row)
		{
			$row['signature'] = $this->fix_quotes($row['signature'], false);
			$row['birthdate'] = $this->convert_birthdate($row['birthdate']);

			$rows[] = $row;
		}

		return $rows;
	}

	public function preparseBoards($originalRows)
	{
		$rows = array();
		foreach ($originalRows as $row)
		{
			$row['description'] = $this->fix_quotes($row['description'], false);

			$rows[] = $row;
		}

		return $rows;
	}

	public function preparseMessages($originalRows)
	{
		$rows = array();
		foreach ($originalRows as $row)
		{
			$row['subject'] = $this->fix_quotes($row['subject'], false);
			$row['body'] = $this->fix_quotes($row['body'], true);

			$rows[] = $row;
		}

		return $rows;
	}

	public function preparsePollvotes($originalRows)
	{
		$rows = array();
		foreach ($originalRows as $row)
		{
			// A guest?...I hope so, skip it
			$row['id_member'] = (int) $row['id_member'];

			$rows[] = $row;
		}

		return $rows;
	}

	public function preparsePm($originalRows)
	{
		$rows = array();
		foreach ($originalRows as $row)
		{
			$result = $this->db->query("
				SELECT MIN(POST_ID)
				FROM {$this->config->from_prefix}PRIVATE_MESSAGE_POSTS
				WHERE TOPIC_ID = " . $row['TOPIC_ID']);

			unset($row['TOPIC_ID']);
			list($res) = convert_fetch_row($result);
			convert_free_result($result);

			$rows[] = array(
				'id_pm' => $row['id_pm'],
				'id_pm_head' => $res,
				'id_member_from' => $row['id_member_from'],
				'deleted_by_sender' => 0,
				'from_name' => $row['from_name'],
				'msgtime' => $row['msgtime'],
				'subject' => $this->fix_quotes($row['subject'], false),
				'body' => $this->fix_quotes($row['body'], true),
			);
		}

		return $rows;
	}

	public function preparsePmrecipients($originalRows)
	{
		$rows = array();
		foreach ($originalRows as $row)
		{
			// Grab all the "other" users in the conversation
			$result = $this->db->query("
				SELECT DISTINCT(USER_ID)
				FROM {$this->config->from_prefix}PRIVATE_MESSAGE_POSTS
				WHERE TOPIC_ID = " . $row['TOPIC_ID'] . "
					AND USER_ID != " . $row['USER_ID'] . "");

			$ins = array();
			while ($pmrec = $this->db->fetch_assoc($result))
			{
				$ins[$pmrec['USER_ID']] = array(
					'id_pm' => $row['POST_ID'],
					'id_member' => $pmrec['USER_ID'],
				);
			}
			$this->db->free_result($result);

			// And try also with the PRIVATE_MESSAGE_USERS table
			$result = convert_query("
				SELECT DISTINCT(USER_ID)
				FROM {$this->config->from_prefix}PRIVATE_MESSAGE_USERS
				WHERE TOPIC_ID = " . $row['TOPIC_ID'] . "
					AND USER_ID != " . $row['USER_ID'] . "");

			while ($pmrec = $this->db->fetch_assoc($result))
				$ins[$pmrec['USER_ID']] = array(
					'id_pm' => $row['POST_ID'],
					'id_member' => $pmrec['USER_ID'],
				);
			$this->db->free_result($result);

			foreach ($ins as $in)
			{
				$rows[] = array(
					'id_pm' => $in['id_pm'],
					'id_member' => $in['id_member'],
					'labels' => '',
					'bcc' => '',
					'is_read' => 1,
					'is_new' => 0,
					'deleted' => 0,
				);
			}
		}

		return $rows;
	}

	/**
	 * Utility functions
	 */
	protected function fix_quotes($string, $new_lines = true)
	{
		if ($new_lines)
			return strtr(htmlspecialchars($string, ENT_QUOTES), array("\n" => '<br />'));
		else
			return htmlspecialchars($string);
	}

	protected function convert_birthdate($date)
	{
		$tmp_birthdate = explode('/', $date);
		if (count($tmp_birthdate) == 3)
		{
			if (strlen($tmp_birthdate[2]) != 4)
				$tmp_birthdate[2] = '0004';
			return $tmp_birthdate[2] . '-' . str_pad($tmp_birthdate[0], 2, "0", STR_PAD_LEFT) . '-' . str_pad($tmp_birthdate[1], 2, "0", STR_PAD_LEFT);
		}
		else
			return '0001-01-01';
	}
}