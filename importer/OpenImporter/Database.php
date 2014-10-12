<?php

/**
 * the database class.
 * This class provides an easy wrapper around the common database
 *  functions we work with.
 */
class Database
{

	/**
	 * constructor, connects to the database
	 * @param type $db_server
	 * @param type $db_user
	 * @param type $db_password
	 * @param type $db_persist
	 */
	var $con;
	
	/**
	 * 
	 * @param string $db_server
	 * @param string $db_user
	 * @param string $db_password
	 * @param bool $db_persist
	 */
	public function __construct($db_server, $db_user, $db_password, $db_persist)
	{
		$this->con = mysqli_connect(($db_persist == 1 ? 'p:' : '') . $db_server, $db_user, $db_password);
 
		if (mysqli_connect_error())
 			die('Database error: ' . mysqli_connect_error());
	}

	/**
	 * remove old attachments
	 *
	 * @global type $to_prefix
	 */
	private function _removeAttachments()
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
	 * execute an SQL query
	 *
	 * @global type $import
	 * @global type $to_prefix
	 * @param type $string
	 * @param type $return_error
	 * @return type
	 */
	public function query($string, $return_error = false)
	{
		global $import, $to_prefix;

		// Debugging?
		if (isset($_REQUEST['debug']))
			$_SESSION['import_debug'] = !empty($_REQUEST['debug']);

		if (trim($string) == 'TRUNCATE ' . $to_prefix . 'attachments;')
			$this->_removeAttachments();

		$result = @mysqli_query($this->con, $string);

		if ($result !== false || $return_error)
			return $result;

		$mysql_error = mysqli_error($this->con);
		$mysql_errno = mysqli_errno($this->con);

		if ($mysql_errno == 1016)
		{
			if (preg_match('~(?:\'([^\.\']+)~', $mysql_error, $match) != 0 && !empty($match[1]))
				mysqli_query($this->con, "
					REPAIR TABLE $match[1]");

			$result = mysql_query($string);

			if ($result !== false)
				return $result;
		}
		elseif ($mysql_errno == 2013)
		{
			$result = mysqli_query($this->con, $string);

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

		$import->template->footer();
		die;
	}


	/**
	 * wrapper for mysql_free_result
	 * @param type $result
	 */
	public function free_result($result)

	{
		mysqli_free_result($result);
	}

	/**
	 * wrapper for mysql_fetch_assoc
	 * @param type $result
	 * @return string
	 */
	public function fetch_assoc($result)
	{
		return mysqli_fetch_assoc($result);
	}

	/**
	 * wrapper for mysql_fetch_row
	 * @param type $result
	 * @return type
	 */
	public function fetch_row($result)
	{
		return mysqli_fetch_row($result);
	}

	/**
	 * wrapper for mysql_num_rows
	 * @param type $result
	 * @return integer
	 */
	public function num_rows($result)
	{
		return mysqli_num_rows($result);
	}

	/**
	 * wrapper for mysql_insert_id
	 * @return integer
	 */
	public function insert_id()
	{
		return mysql_insert_id($this->con);
	}
}