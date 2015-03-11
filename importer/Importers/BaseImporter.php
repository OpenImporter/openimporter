<?php
/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 2.0 Alpha
 */

/**
 * The starting point for any step of any importer.
 */
abstract class BaseImporter
{
	protected $db = null;
	protected $config = null;

	public function __construct($db, $config)
	{
		$this->db = $db;
		$this->config = $config;
	}

	public function callMethod($method, $params = null)
	{
		if (method_exists($this, $method))
		{
			return call_user_func_array(array($this, $method), array($params));
		}
		else
		{
			return $params;
		}
	}
}