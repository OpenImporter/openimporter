<?php
/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 2.0 Alpha
 */

namespace OpenImporter\Importers\sources;

class wbb3_1 extends \OpenImporter\Importers\AbstractSourceImporter
{
	protected $setting_file = '/wc/config.inc.php';
	protected $userOptions = array();

	public function getName()
	{
		return 'Woltlab Burning Board 3.1';
	}

	public function getVersion()
	{
		return '1.0';
	}

	public function getPrefix()
	{
		return '`' . $this->getDbName() . '`.';
	}

	public function getDbName()
	{
		global $dbName;

		return $dbName;
	}

	// @todo why $wbb_prefix is not in getPrefix?
	public function getTableTest()
	{
		global $wbb_prefix;

		return $wbb_prefix . 'user';
	}

	protected function fetchUserOptions()
	{
		// @todo I'm not sure this goes to global.
		// in any case it should be converted in to a property of config->source
		global $wcf_prefix;

		if (!empty($this->userOptions))
			return;

		$this->userOptions = array();
		$request = $db->query("
			SELECT optionName, optionID
			FROM{$from_prefix}{$wcf_prefix}user_option");

		while ($wbbOpt = $db->fetch_assoc($request))
			$this->userOptions[$wbbOpt['optionName']]= $wbbOpt['optionID'];

		$db->free_result($request);
	}

	protected function fixUserGroupId($id_group)
	{
		$request = $this->db->query("
			SELECT groupID
			FROM {$this->config->from_prefix}{$wcf_prefix}user_to_groups
			WHERE userID = $row[id_member]");

		while ($groups = $this->db->fetch_assoc($request))
		{
			if (in_array('4', $groups))
				$id_group = '1';
			elseif (in_array('5', $groups))
				$id_group = '2';
			elseif (in_array('6', $groups))
				$id_group = '2';
		}
		$this->db->free_result($request);

		return $id_group;
	}

	/**
	 * From here on, all the methods are needed helper for the conversion
	 */
	public function preparseMembers($originalRows)
	{
		// @todo I'm not sure this goes to global.
		// in any case it should be converted in to a property of config->source
		global $wcf_prefix;

		$this->fetchUserOptions();
		$rows = array();
		foreach ($originalRows as $row)
		{
			$row['id_group'] = $this->fixUserGroupId($row['id_group']);

			$row['signature'] = $this->replace_bbc($row['signature']);

			/* load wbb userOptions */
			$request = $this->db->query("
				SELECT *
				FROM {$this->config->from_prefix}{$wcf_prefix}user_option_value
				WHERE userID = $row[id_member]");

			$options = $this->db->fetch_assoc($request);
			$this->db->free_result($request);

			/* now we can fix some profile options*/
			$row['birthdate'] = $options['userOption'. $this->userOptions['birthday']];
			$row['show_online'] = !empty($options['userOption'. $this->userOptions['invisible']]) ? (int) $options['userOption'. $this->userOptions['invisible']] : 0;
			$row['hide_email'] = (int) $options['userOption'. $this->userOptions['hideEmailAddress']];
			$row['location'] = !empty($options['userOption'. $this->userOptions['location']]) ? $options['userOption'. $this->userOptions['location']] : '';
			$row['gender'] = !empty($options['userOption'. $this->userOptions['gender']])? $options['userOption'. $this->userOptions['gender']] : 0;
			$row['website_title'] = $options['userOption'. $this->userOptions['homepage']];
			$row['website_url'] = $options['userOption'. $this->userOptions['homepage']];
			/* fix invalid birthdates */
			if(!preg_match('/\d{4}-\d{2}-\d{2}/', $row['birthdate']))
				$row['birthdate'] = '0001-01-01';

			$rows[] = $row;
		}

		return $rows;
	}

	public function preparseTopics($originalRows)
	{
		$rows = array();
		foreach ($originalRows as $row)
		{
			$request = $db->query("
				SELECT
					pollID
				FROM {$from_prefix}{$wbb_prefix}post
				WHERE threadID = $row[id_topic] AND pollID > 0
				GROUP BY threadID");

			list ($pollID) = $db->fetch_row($request);
			$db->free_result($request);
			if ($pollID > 0)
				$row['id_poll'] = $pollID;

			$rows[] = $row;
		}

		return $rows;
	}

	public function preparseMessages($originalRows)
	{
		$rows = array();
		foreach ($originalRows as $row)
		{
			$row['body'] = $this->replace_bbc($row['body']);

			$rows[] = $row;
		}

		return $rows;
	}

	public function preparsePolloptions($originalRows)
	{
		$rows = array();
		foreach ($originalRows as $row)
		{
			if (!isset($_SESSION['convert_last_poll']) || $_SESSION['convert_last_poll'] != $row['id_poll'])
			{
				$_SESSION['convert_last_poll'] = $row['id_poll'];
				$_SESSION['convert_last_choice'] = 0;
			}
			$row['id_choice'] = ++$_SESSION['convert_last_choice'];

			$rows[] = $row;
		}

		return $rows;
	}

	public function preparsePm($originalRows)
	{
		$rows = array();
		foreach ($originalRows as $row)
		{
			$row['body'] = $this->replace_bbc($row['body']);

			$rows[] = $row;
		}

		return $rows;
	}

	/**
	 * Utility functions
	 */
	function replace_bbc($message)
	{
		$message = preg_replace(
			array(
				'~\[size=(.+?)\]~is',
				'~\[align=left\](.+?)\[\/align\]~is',
				'~\[align=right\](.+?)\[\/align\]~is',
				'~\[align=center\](.+?)\[\/align\]~is',
				'~\[align=justify\](.+?)\[\/align\]~is',
				'~.Geneva, Arial, Helvetica, sans-serif.~is',
				'~.Tahoma, Arial, Helvetica, sans-serif.~is',
				'~.Arial, Helvetica, sans-serif.~is',
				'~.Chicago, Impact, Compacta, sans-serif.~is',
				'~.Comic Sans MS, sans-serif.~is',
				'~.Courier New, Courier, mono.~is',
				'~.Georgia, Times New Roman, Times, serif.~is',
				'~.Helvetica, Verdana, sans-serif.~is',
				'~.Impact, Compacta, Chicago, sans-serif.~is',
				'~.Lucida Sans, Monaco, Geneva, sans-serif.~is',
				'~.Times New Roman, Times, Georgia, serif.~is',
				'~.Trebuchet MS, Arial, sans-serif.~is',
				'~.Verdana, Helvetica, sans-serif.~is',
				'~\[list=1\]\[\*\]~is',
				'~\[list\]\[\*\]~is',
				'~\[\*\]~is',
				'~\[\/list\]~is',
				'~\[attach\](.+?)\[\/attach\]~is'
			),
			array(
				'[size=$1pt]',
				'[left]$1[/left]',
				'[right]$1[/right]',
				'[center]$1[/center]',
				'$1',
				'Geneva',
				'Tahoma',
				'Arial',
				'Chicago',
				'Comic Sans MS',
				'Courier New',
				'Georgia',
				'Helvetica',
				'Impact',
				'Lucida Sans',
				'Times New Roman',
				'Trebuchet MS',
				'Verdana',
				'[list type=decimal][li]',
				'[list][li]',
				'[/li][li]',
				'[/li][/list]',
				'',
			),
			trim($message)
		);
		return $message;
	}
}