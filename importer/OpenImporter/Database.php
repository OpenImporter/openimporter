<?php
/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 2.0 Alpha
 */

namespace OpenImporter\Core;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Driver\Statement;
use OpenImporter\Core\DatabaseException;

/**
 * The database class.
 *
 * This class provides an easy wrapper around the common database
 * functions we work with.
 */
class Database
{
	/**
	 *
	 */
	protected $con;

	/**
	 * Allows to run a query two times on certain errors.
	 *
	 * @var bool
	 */
	protected $second_try = true;

	/**
	 * Constructor, connects to the database.
	 *
	 * @param string[] $connectionParams
	 */
	public function __construct($connectionParams)
	{
		$config = new Configuration();
		$this->con = DriverManager::getConnection($connectionParams, $config);
	}

	/**
	 * Execute an SQL query.
	 *
	 * @param string $string
	 * @param bool $return_error
	 * @return bool|null|\Doctrine\DBAL\Driver\Statement
	 */
	public function query($string, $return_error = false)
	{
		if (substr($string, -1, 1) !== ';')
			$string .= ';';

		try
		{
			$result = $this->con->query($string);
		}
		catch (\Exception $e)
		{
			if ($return_error)
			{
				$this->second_try = true;
				return false;
			}
			else
			{
				return $this->sendError($e->getMessage());
			}
		}

		return $result;
	}

	/**
	 * Returns the code of last error occurrend with the current connection.
	 *
	 * @return string
	 */
	public function getLastError()
	{
		return $this->con->errorCode();
	}

	public function insert($table, $data, $type)
	{
		if ($type === 'update' || $type === 'replace')
			$this->conn->update($table, $data);
		elseif ($type === 'ignore')
			$this->insertIgnore($table, $data);
		else
			$this->con->insert($table, $data);
	}

	public function insertIgnore($table, $data)
	{
		try
		{
			$this->con->insert($table, $data);
		}
		catch(Doctrine_Connection_Exception $e)
		{
			if($e->getPortableCode() != Doctrine::ERR_ALREADY_EXISTS)
			{
					throw $e;
			}
		}
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
		$error = $this->con->errorInfo();
		$errno = $this->con->errorCode();

		// @todo MySQL specific errors, check Doctrine DBAL documentation
		// 1016: Can't open file '....MYI'
		// 2013: Lost connection to server during query.
		if (in_array($errno, array(1016, 2013)) && $this->second_try)
		{
			$this->second_try = false;

			// Try to repair the table and run the query again.
			if ($errno == 1016 && preg_match('~(?:\'([^\.\']+)~', $error[2], $match) != 0 && !empty($match[1]))
				$this->con->query("
					REPAIR TABLE $match[1]");

			return $this->query($string, false);
		}

		$action_url = $this->buildActionUrl();

		throw new DatabaseException('
				<b>Unsuccessful!</b><br />
				This query:<blockquote>' . nl2br(htmlspecialchars(trim($string))) . ';</blockquote>
				Caused the error:<br />
				<blockquote>' . nl2br(htmlspecialchars($error[2])) . '</blockquote>
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
	 * Wrapper for free_result.
	 *
	 * @param object $result
	 */
	public function free_result(Statement $result)
	{
		$result->closeCursor();
	}

	/**
	 * Wrapper for fetch_assoc.
	 *
	 * @param object $result
	 * @return mixed[]
	 */
	public function fetch_assoc(Statement $result)
	{
		return $result->fetch(\PDO::FETCH_ASSOC);
	}

	/**
	 * wrapper for fetch_row
	 *
	 * @param object $result
	 * @return mixed[]
	 */
	public function fetch_row(Statement $result)
	{
		return $result->fetch(\PDO::FETCH_NUM);
	}

	/**
	 * wrapper for num_rows
	 *
	 * @param object $result
	 * @return integer
	 */
	public function num_rows(Statement $result)
	{
		return $result->rowCount();
	}

	/**
	 * wrapper for insert_id
	 *
	 * @return integer
	 */
	public function insert_id()
	{
		return $this->con->lastInsertId();
	}

	/**
	 * Add an index.
	 *
	 * @param string $table_name
	 * @param mixed[] $index_info
	 */
	public function add_index($table_name, $index_info)
	{
		// No columns = no index.
		if (empty($index_info['columns']))
			return false;
		$columns = implode(',', $index_info['columns']);

		$index_info['name'] = $this->calculateIndexName($index_info);

		if ($this->indexExists($table_name, $index_info))
			return false;

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
				$type = 'INDEX';
			else
				$type = strtoupper($index_info['type']);

			$this->query('
				ALTER TABLE ' . $table_name . '
				ADD ' . $type . ' ' . $index_info['name'] . ' (' . $columns . ')');
		}
	}

	protected function calculateIndexName($index_info)
	{
		// No name - make it up!
		if (empty($index_info['name']))
		{
			// No need for primary.
			if (isset($index_info['type']) && $index_info['type'] == 'primary')
				return '';
			else
				return implode('_', $index_info['columns']);
		}
		else
			return $index_info['name'];
	}

	protected function indexExists($table_name, $index_info)
	{
		// Let's get all our indexes.
		$indexes = $this->list_indexes($table_name, true);

		// Do we already have it?
		foreach ($indexes as $index)
		{
			if ($index['name'] == $index_info['name'] || ($index['type'] == 'primary' && isset($index_info['type']) && $index_info['type'] == 'primary'))
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
	 * @return mixed
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
				$indexes[] = $row['Key_name'];
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
					$indexes[$row['Key_name']]['columns'][] = $row['Column_name'] . '(' . $row['Sub_part'] . ')';
				else
					$indexes[$row['Key_name']]['columns'][] = $row['Column_name'];
			}
		}
		$this->free_result($result);

		return $indexes;
	}

	/**
	 * What is the type?
	 *
	 * @param string[] $row
	 */
	protected function determineIndexType($row)
	{
		if ($row['Key_name'] == 'PRIMARY')
			return 'primary';
		elseif (empty($row['Non_unique']))
			return 'unique';
		elseif (isset($row['Index_type']) && $row['Index_type'] == 'FULLTEXT')
			return 'fulltext';
		else
			return 'index';
	}
}