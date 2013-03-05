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

if (!defined('OPENIMPORTER'))
	die('No direct access allowed...');

class database
{
	/**
	 * constructor, connets to our database
	 * @param type $db_server
	 * @param type $db_user
	 * @param type $db_password
	 * @param type $db_persist 
	 */
	public function __construct($db_server, $db_user, $db_password, $db_persist)
	{
		if ($db_persist == 1)
			$this->con = mysql_pconnect ($db_server, $db_user, $db_password) or die (mysql_error());
		else
			$this->con = mysql_connect ($db_server, $db_user, $db_password) or die (mysql_error());
	}

	/**
	 * @name removeAttachments
	 * @global type $to_prefix 
	 * 
	 * Removes old attachments
	 */
	private function removeAttachments()
	{
		global $to_prefix;

		$result = $this->query("
			SELECT value
			FROM {$to_prefix}settings
			WHERE variable = 'attachmentUploadDir'
			LIMIT 1");
		list ($attachmentUploadDir) = $this->fetch_row($result);
		$this->free_result($result);

		// !!! This should probably be done in chunks too.
		$result = $this->query("
			SELECT id_attach, filename
			FROM {$to_prefix}attachments");
		while ($row = $this->fetch_assoc($result))
		{
			// We're duplicating this from below because it's slightly different for getting current ones.
			$clean_name = strtr($row['filename'], 'ŠŽšžŸÀÁÂÃÄÅÇÈÉÊËÌÍÎÏÑÒÓÔÕÖØÙÚÛÜÝàáâãäåçèéêëìíîïñòóôõöøùúûüýÿ', 'SZszYAAAAAACEEEEIIIINOOOOOOUUUUYaaaaaaceeeeiiiinoooooouuuuyy');
			$clean_name = strtr($clean_name, array('Þ' => 'TH', 'þ' => 'th', 'Ð' => 'DH', 'ð' => 'dh', 'ß' => 'ss', 'Œ' => 'OE', 'œ' => 'oe', 'Æ' => 'AE', 'æ' => 'ae', 'µ' => 'u'));
			$clean_name = preg_replace(array('/\s/', '/[^\w_\.\-]/'), array('_', ''), $clean_name);
			$enc_name = $row['id_attach'] . '_' . strtr($clean_name, '.', '_') . md5($clean_name) . '.ext';
			$clean_name = preg_replace('~\.[\.]+~', '.', $clean_name);

			if (file_exists($attachmentUploadDir . '/' . $enc_name))
				$filename = $attachmentUploadDir . '/' . $enc_name;
			else
				$filename = $attachmentUploadDir . '/' . $clean_name;

			if (is_file($filename))
				unlink($filename);
		}
		$this->free_result($result);
	}

	/**
	 * @name query
	 * @global type $import
	 * @global type $to_prefix
	 * @param type $string
	 * @param type $return_error
	 * @return type 
	 * 
	 * query the database 
	 */
	public function query($string, $return_error = false)
	{
		global $import, $to_prefix;

		// Debugging?
		if (isset($_REQUEST['debug']))
			$_SESSION['import_debug'] = !empty($_REQUEST['debug']);

		if (trim($string) == 'TRUNCATE ' . $to_prefix . 'attachments;')
			$this->removeAttachments();

		$result = @mysql_query($string);

		if ($result !== false || $return_error)
			return $result;

		$mysql_error = mysql_error();
		$mysql_errno = mysql_errno();

		if ($mysql_errno == 1016)
		{
			if (preg_match('~(?:\'([^\.\']+)~', $mysql_error, $match) != 0 && !empty($match[1]))
				mysql_query("
					REPAIR TABLE $match[1]");

			$result = mysql_query($string);

			if ($result !== false)
				return $result;
		}
		elseif ($mysql_errno == 2013)
		{
			$result = mysql_query($string);

			if ($result !== false)
				return $result;
		}

		// Get the query string so we pass everything.
		if (isset($_REQUEST['start']))
			$_GET['start'] = $_REQUEST['start'];
		$query_string = '';
		foreach ($_GET as $k => $v)
			$query_string .= '&' . $k . '=' . $v;
		if (strlen($query_string) != 0)
			$query_string = '?' . strtr(substr($query_string, 1), array('&' => '&amp;'));

		echo '
				<b>Unsuccessful!</b><br />
				This query:<blockquote>' . nl2br(htmlspecialchars(trim($string))) . ';</blockquote>
				Caused the error:<br />
				<blockquote>' . nl2br(htmlspecialchars($mysql_error)) . '</blockquote>
				<form action="', $_SERVER['PHP_SELF'], $query_string, '" method="post">
					<input type="submit" value="Try again" />
				</form>
			</div>';
		die;
	}

	/**
	 * @name free_result
	 * @param type $result 
	 * 
	 * wrapper function for mysql_free_result
	 */
	public function free_result($result)
	{
		mysql_free_result($result);
	}

	/**
	 * @name fetch_assoc
	 * @param type $result
	 * @return type
	 * 
	 * wrapper function for mysql_fetch_assoc
	 */
	public function fetch_assoc($result)
	{
		return mysql_fetch_assoc($result);
	}

	/**
	 * @name fetch_row
	 * @param type $result
	 * @return type 
	 * 
	 * wrapper function for mysql_fetch_row
	 */
	public function fetch_row($result)
	{
		return mysql_fetch_row($result);
	}

	/**
	 * @name num_rows
	 * @param type $result
	 * @return type 
	 * 
	 * wrapper for mysql_num_rows
	 */
	public function num_rows($result)
	{
		return mysql_num_rows($result);
	}
	
	/**
	 * @name insert_id
	 * @return type 
	 * 
	 * wrapper for mysql_insert_id
	 */
	public function insert_id()
	{
		return mysql_insert_id();
	}
}