<?php
/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD https://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0
 */

namespace OpenImporter;

/**
 * Class Database
 *
 * This class provides an easy wrapper around the common database
 * functions we work with.
 *
 * @class Database
 */
class Database
{
	/** @var \mysqli */
	protected $connect;

	/** @var bool Allows to run a query two times on certain errors. */
	protected $second_try = true;

	/**
	 * Database constructor.
	 *
	 * @param string $db_server
	 * @param string $db_user
	 * @param string $db_password
	 * @param bool|int $db_persist
	 */
	public function __construct($db_server, $db_user, $db_password, $db_persist)
	{
		$this->connect = mysqli_connect(($db_persist == 1 ? 'p:' : '') . $db_server, $db_user, $db_password);

		if (mysqli_connect_error())
		{
			die('Database error: ' . mysqli_connect_error());
		}
	}

	/**
	 * Execute an SQL query.
	 *
	 * @param string $string
	 * @param bool $return_error
	 *
	 * @return \mysqli_result
	 */
	public function query($string, $return_error = false)
	{
		$result = @mysqli_query($this->connect, $string);

		if ($result !== false || $return_error)
		{
			$this->second_try = true;

			return $result;
		}

		return $this->sendError($string);
	}

	/**
	 * Returns the last MySQL error occurred with the current connection.
	 *
	 * @return string
	 */
	public function getLastError()
	{
		return mysqli_error($this->connect);
	}

	/**
	 * Analyze and sends an error.
	 *
	 * @param string $string
	 *
	 * @throws DatabaseException If a SQL fails
	 *
	 * @return \mysqli_result
	 */
	protected function sendError($string)
	{
		$mysql_error = mysqli_error($this->connect);
		$mysql_errno = mysqli_errno($this->connect);

		// 1016: Can't open file '....MYI'
		// 2013: Lost connection to server during query.
		if ($this->second_try && in_array($mysql_errno, array(1016, 2013)))
		{
			$this->second_try = false;

			// Try to repair the table and run the query again.
			if ($mysql_errno === 1016 && preg_match('~(?:\'([^.\']+)~', $mysql_error, $match) !== 0 && !empty($match[1]))
			{
				mysqli_query($this->connect, "
					REPAIR TABLE $match[1]");
			}

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
		{
			$_GET['start'] = $_REQUEST['start'];
		}

		$query_string = '';
		foreach ($_GET as $k => $v)
		{
			$query_string .= '&' . $k . '=' . $v;
		}

		if (trim($query_string) !== '')
		{
			$query_string = '?' . strtr(substr($query_string, 1), array('&' => '&amp;'));
		}

		return $_SERVER['PHP_SELF'] . $query_string;
	}

	/**
	 * Wrapper for mysqli_free_result.
	 *
	 * @param \mysqli_result $result
	 */
	public function free_result($result)
	{
		mysqli_free_result($result);
	}

	/**
	 * Wrapper for mysqli_fetch_assoc.
	 *
	 * @param \mysqli_result $result
	 *
	 * @return array
	 */
	public function fetch_assoc($result)
	{
		return mysqli_fetch_assoc($result);
	}

	/**
	 * wrapper for mysqli_fetch_row
	 *
	 * @param \mysqli_result $result
	 *
	 * @return array
	 */
	public function fetch_row($result)
	{
		return mysqli_fetch_row($result);
	}

	/**
	 * wrapper for mysqli_num_rows
	 *
	 * @param \mysqli_result $result
	 *
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
		return mysqli_insert_id($this->connect);
	}

	/**
	 * Add an index.
	 *
	 * @param string $table_name
	 * @param array $index_info
	 *
	 * @return bool
	 */
	public function add_index($table_name, $index_info)
	{
		// No columns = no index.
		if (empty($index_info['columns']))
		{
			return false;
		}

		$columns = implode(',', $index_info['columns']);

		$index_info['name'] = $this->calculateIndexName($index_info);

		if ($this->indexExists($table_name, $index_info))
		{
			return false;
		}

		// If we're here we know we don't have the index - so just add it.
		if (!empty($index_info['type']) && $index_info['type'] == 'primary')
		{
			$this->query('
				ALTER TABLE ' . $table_name . '
				ADD PRIMARY KEY (' . $columns . ')');
		}
		else
		{
			if (!isset($index_info['type']) || !in_array($index_info['type'], array('unique', 'index', 'key')))
			{
				$type = 'INDEX';
			}
			else
			{
				$type = strtoupper($index_info['type']);
			}

			$this->query('
				ALTER TABLE ' . $table_name . '
				ADD ' . $type . ' ' . $index_info['name'] . ' (' . $columns . ')');
		}
	}

	/**
	 * Set a name for the index
	 *
	 * @param $index_info
	 *
	 * @return string
	 */
	protected function calculateIndexName($index_info)
	{
		// No name - make it up!
		if (empty($index_info['name']))
		{
			// No need for primary.
			if (isset($index_info['type']) && $index_info['type'] == 'primary')
			{
				return '';
			}

			return implode('_', $index_info['columns']);
		}

		return $index_info['name'];
	}

	/**
	 * Check if an index exists or not
	 *
	 * @param string $table_name
	 * @param string $index_info
	 *
	 * @return bool
	 */
	protected function indexExists($table_name, $index_info)
	{
		// Let's get all our indexes.
		$indexes = $this->list_indexes($table_name, true);

		// Do we already have it?
		foreach ($indexes as $index)
		{
			if ($index['name'] === $index_info['name']
				|| ($index['type'] === 'primary' && isset($index_info['type']) && $index_info['type'] === 'primary'))
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * Get index information.
	 *
	 * @param string $table_name
	 * @param bool $detail
	 *
	 * @return array
	 */
	public function list_indexes($table_name, $detail = false)
	{
		$result = $this->query("
			SHOW KEYS
			FROM {$table_name}");

		$indexes = array();
		while ($row = $this->fetch_assoc($result))
		{
			if (!$detail)
			{
				$indexes[] = $row['Key_name'];
			}
			else
			{
				// This is the first column we've seen?
				if (empty($indexes[$row['Key_name']]))
				{
					$indexes[$row['Key_name']] = array(
						'name' => $row['Key_name'],
						'type' => $this->determineIndexType($row),
						'columns' => array(),
					);
				}

				// Is it a partial index?
				if (!empty($row['Sub_part']))
				{
					$indexes[$row['Key_name']]['columns'][] = $row['Column_name'] . '(' . $row['Sub_part'] . ')';
				}
				else
				{
					$indexes[$row['Key_name']]['columns'][] = $row['Column_name'];
				}
			}
		}
		$this->free_result($result);

		return $indexes;
	}

	/**
	 * What is the index type?
	 *
	 * @param string[] $row
	 *
	 * @return string
	 */
	protected function determineIndexType($row)
	{
		if ($row['Key_name'] === 'PRIMARY')
		{
			return 'primary';
		}

		if (empty($row['Non_unique']))
		{
			return 'unique';
		}

		if (isset($row['Index_type']) && $row['Index_type'] === 'FULLTEXT')
		{
			return 'fulltext';
		}

		return 'index';
	}
}
