<?php
/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 2.0 Alpha
 */

namespace OpenImporter\Core;

/**
 * class DatabaseException extends the build-in Exception class and
 * catches potential errors
 */
class DatabaseException extends \Exception
{
	protected $query = '';
	protected $error_string = '';

	public function __construct($query, $error, $message = "", $code = 0, \Exception $previous = null)
	{
		parent::__construct($message, $code, $previous);

		$this->query = $query;
		$this->error_string = $error;
	}

	public function getQuery()
	{
		return $this->query;
	}

	public function getErrorString()
	{
		return $this->error_string;
	}
}