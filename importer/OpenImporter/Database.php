<?php
/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Alpha
 */

/**
 * The database class.
 *
 * This class provides an easy wrapper around the common database
 * functions we work with.
 */
class Database
{
	/**
	 * Constructor, connects to the database.
	 *
	 * @param type $db_server
	 * @param type $db_user
	 * @param type $db_password
	 * @param type $db_persist
	 */
	protected $con;

	/**
	 * Allows to run a query two times on certain errors.
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
	 * Execute an SQL query.
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
	 * Returns the last MySQL error occurrend with the current connection.
	 *
	 * @return string
	 */
	public function getLastError()
	{
		return mysqli_error($this->con);
	}

	/**
	 * Analyze and sends an error.
	 *
	 * @param string $string
	 * @throws DatabaseException If a SQL fails
	 * @return type
	 */
	protected function sendError($string)
	{
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

		$action_url = $this->buildActionUrl();

		throw new DatabaseException('
				<b>Unsuccessful!</b><br />
				This query:<blockquote>' . nl2br(htmlspecialchars(trim($string))) . ';</blockquote>
				Caused the error:<br />
				<blockquote>' . nl2br(htmlspecialchars($mysql_error)) . '</blockquote>
				<form action="' . $action_url . '" method="post">
					<input type="submit" value="Try again" />
				</form>
			</div>');
	}

	/**
	 * Puts together the url used in the DatabaseException of sendError to go
	 * back to the last step.
	 *
	 * @return string
	 */
	protected function buildActionUrl()
	{
		// @todo $_GET and $_REQUEST
		// Get the query string so we pass everything.
		if (isset($_REQUEST['start']))
			$_GET['start'] = $_REQUEST['start'];

		$query_string = '';
		foreach ($_GET as $k => $v)
			$query_string .= '&' . $k . '=' . $v;

		if (strlen($query_string) != 0)
			$query_string = '?' . strtr(substr($query_string, 1), array('&' => '&amp;'));

		return $_SERVER['PHP_SELF'] . $query_string;
	}

	/**
	 * Wrapper for mysqli_free_result.
	 *
	 * @param object $result
	 */
	public function free_result($result)
	{
		mysqli_free_result($result);
	}

	/**
	 * Wrapper for mysqli_fetch_assoc.
	 *
	 * @param object $result
	 * @return mixed[]
	 */
	public function fetch_assoc($result)
	{
		return mysqli_fetch_assoc($result);
	}

	/**
	 * wrapper for mysqli_fetch_row
	 *
	 * @param object $result
	 * @return mixed[]
	 */
	public function fetch_row($result)
	{
		return mysqli_fetch_row($result);
	}

	/**
	 * wrapper for mysqli_num_rows
	 *
	 * @param object $result
	 * @return integer
	 */
	public function num_rows($result)
	{
		return mysqli_num_rows($result);
	}

	/**
	 * wrapper for mysqli_insert_id
	 *
	 * @return integer
	 */
	public function insert_id()
	{
		return mysqli_insert_id($this->con);
	}

	/**
	 * Add an index.
	 *
	 * @param string $table_name
	 * @param mixed[] $index_info
	 * @param mixed[] $parameters default array()
	 * @param string $if_exists default 'update'
	 * @param string $error default 'fatal'
	 */
	public function add_index($table_name, $index_info)
	{
		// No columns = no index.
		if (empty($index_info['columns']))
			return false;
		$columns = implode(',', $index_info['columns']);

		// No name - make it up!
		if (empty($index_info['name']))
		{
			// No need for primary.
			if (isset($index_info['type']) && $index_info['type'] == 'primary')
				$index_info['name'] = '';
			else
				$index_info['name'] = implode('_', $index_info['columns']);
		}
		else
			$index_info['name'] = $index_info['name'];

		// Let's get all our indexes.
		$indexes = $this->list_indexes($table_name, true);

		// Do we already have it?
		foreach ($indexes as $index)
		{
			if ($index['name'] == $index_info['name'] || ($index['type'] == 'primary' && isset($index_info['type']) && $index_info['type'] == 'primary'))
			{
				return false;
			}
		}

		// If we're here we know we don't have the index - so just add it.
		if (!empty($index_info['type']) && $index_info['type'] == 'primary')
		{
			$this->query('', '
				ALTER TABLE ' . $table_name . '
				ADD PRIMARY KEY (' . $columns . ')',
				array(
					'security_override' => true,
				)
			);
		}
		else
		{
			if (!isset($index_info['type'] || !in_array($index_info['type'], array('unique', 'index', 'key'))))
				$type = 'INDEX';
			else
				$type = strtoupper($index_info['type']);

			$this->query('', '
				ALTER TABLE ' . $table_name . '
				ADD ' . $type . ' ' . $index_info['name'] . ' (' . $columns . ')',
				array(
					'security_override' => true,
				)
			);
		}
	}
	/**
	 * Get index information.
	 *
	 * @param string $table_name
	 * @param bool $detail
	 * @param mixed[] $parameters
	 * @return mixed
	 */
	public function list_indexes($table_name, $detail = false, $parameters = array())
	{
		$result = $this->query('', "
			SHOW KEYS
			FROM {$table_name}");

		$indexes = array();
		while ($row = $this->fetch_assoc($result))
		{
			if (!$detail)
				$indexes[] = $row['Key_name'];
			else
			{
				// What is the type?
				if ($row['Key_name'] == 'PRIMARY')
					$type = 'primary';
				elseif (empty($row['Non_unique']))
					$type = 'unique';
				elseif (isset($row['Index_type']) && $row['Index_type'] == 'FULLTEXT')
					$type = 'fulltext';
				else
					$type = 'index';

				// This is the first column we've seen?
				if (empty($indexes[$row['Key_name']]))
				{
					$indexes[$row['Key_name']] = array(
						'name' => $row['Key_name'],
						'type' => $type,
						'columns' => array(),
					);
				}

				// Is it a partial index?
				if (!empty($row['Sub_part']))
					$indexes[$row['Key_name']]['columns'][] = $row['Column_name'] . '(' . $row['Sub_part'] . ')';
				else
					$indexes[$row['Key_name']]['columns'][] = $row['Column_name'];
			}
		}
		$this->free_result($result);

		return $indexes;
	}

}