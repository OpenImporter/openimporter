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
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

/**
 * The database class.
 *
 * This class provides an easy wrapper around the common database
 * functions we work with.
 */
class Database
{
	/**
	 * The database connection
	 * @var Doctrine\DBAL\Connection
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
	 * @param object $con
	 */
	public function __construct($con)
	{
		$this->con = $con;
	}

	/**
	 * Execute an SQL query.
	 *
	 * @param string $string
	 * @param bool $allow_second_try
	 * @return \Doctrine\DBAL\Driver\Statement
	 */
	public function query($string, $allow_second_try = false)
	{
		if (substr($string, -1, 1) !== ';')
			$string .= ';';

		try
		{
			$result = $this->con->query($string);
		}
		catch (\Exception $e)
		{
			return $this->sendError($e->getMessage(), $allow_second_try);
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

	/**
	 * Inserts a set of data into a table using a certain method (type)
	 *
	 * @param string $table The table name
	 * @param mixed[] $data The array of data
	 * @param string $type The way the data are going to be inserted
	 *                 update/replace/ignore/anything
	 */
	public function insert($table, $data, $type)
	{
		if ($type === 'update' || $type === 'replace')
			$this->con->update($table, $data, $data);
		elseif ($type === 'ignore')
			$this->insertIgnore($table, $data);
		else
			$this->con->insert($table, $data);
	}

	/**
	 * Executes an INSERT IGNORE
	 *
	 * @param string $table The table name
	 * @param mixed[] $data The array of data
	 * @throws \Exception in case something is wrong
	 */
	public function insertIgnore($table, $data)
	{
		try
		{
			$this->con->insert($table, $data);
		}
		catch (ConstraintViolationException $e)
		{
			return;
		}
		catch (\Exception $e)
		{
			throw $e;
		}
	}

	/**
	 * Analyze and sends an error.
	 *
	 * @param string $string
	 * @param bool $allow_second_try
	 * @throws DatabaseException If a SQL fails
	 * @return type
	 */
	protected function sendError($string, $allow_second_try)
	{
		$error = $this->con->errorInfo();
		$errno = $this->con->errorCode();

		// @todo MySQL specific errors, check Doctrine DBAL documentation
		// 1016: Can't open file '....MYI'
		// 2013: Lost connection to server during query.
		if (in_array($errno, array(1016, 2013)) && $allow_second_try)
		{
			// Try to repair the table and run the query again.
			if ($errno == 1016 && preg_match('~(?:\'([^\.\']+)~', $error[2], $match) != 0 && !empty($match[1]))
				$this->con->query("
					REPAIR TABLE $match[1]");

			return $this->query($string, false);
		}

		throw new DatabaseException(nl2br(htmlspecialchars(trim($string))), nl2br(htmlspecialchars($error[2])));
	}

	/**
	 * Wrapper for free_result.
	 *
	 * @param Statement $result
	 */
	public function free_result(Statement $result)
	{
		$result->closeCursor();
	}

	/**
	 * Wrapper for fetch_assoc.
	 *
	 * @param Statement $result
	 * @return mixed[]
	 */
	public function fetch_assoc(Statement $result)
	{
		return $result->fetch(\PDO::FETCH_ASSOC);
	}

	/**
	 * wrapper for fetch_row
	 *
	 * @param Statement $result
	 * @return mixed[]
	 */
	public function fetch_row(Statement $result)
	{
		return $result->fetch(\PDO::FETCH_NUM);
	}

	/**
	 * wrapper for num_rows
	 *
	 * @param Statement $result
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
}