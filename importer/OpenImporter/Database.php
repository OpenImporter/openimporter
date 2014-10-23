<?php

namespace OpenImporter;

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
	protected $con;

	/**
	 * Allows to run a query two times on certain errors
	 *
	 * @var bool
	 */
	protected $second_try = true;
	
	/**
	 * 
	 * @param string $db_server
	 * @param string $db_user
	 * @param string $db_password
	 * @param bool|int $db_persist
	 */
	public function __construct($db_server, $db_user, $db_password, $db_persist)
	{
		$this->con = mysqli_connect(($db_persist == 1 ? 'p:' : '') . $db_server, $db_user, $db_password);
 
		if (mysqli_connect_error())
 			die('Database error: ' . mysqli_connect_error());
	}

	/**
	 * execute an SQL query
	 *
	 * @param string $string
	 * @param bool $return_error
	 * @return type
	 */
	public function query($string, $return_error = false)
	{
		// Debugging?
		if (isset($_REQUEST['debug']))
			$_SESSION['import_debug'] = !empty($_REQUEST['debug']);

		$result = @mysqli_query($this->con, $string);

		if ($result !== false || $return_error)
		{
			$this->second_try = true;
			return $result;
		}
		else
			return $this->sendError($string);
	}

	/**
	 * Analyze and sends an error
	 *
	 * @global string $import
	 * @param string $string
	 * @return type
	 */
	protected function sendError($string)
	{
		global $import;

		$mysql_error = mysqli_error($this->con);
		$mysql_errno = mysqli_errno($this->con);

		// 1016: Can't open file '....MYI'
		// 2013: Lost connection to server during query.
		if (in_array($mysql_errno, array(1016, 2013)) && $this->second_try)
		{
			$this->second_try = false;

			// Try to repair the table and run the query again.
			if ($mysql_errno == 1016 && preg_match('~(?:\'([^\.\']+)~', $mysql_error, $match) != 0 && !empty($match[1]))
				mysqli_query($this->con, "
					REPAIR TABLE $match[1]");

			return $this->query($string, false);
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