<?php
/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Alpha
 */

/**
 * The starting point for any step of any importer.
 */
abstract class BaseImporter
{
	protected $db = null;
	protected $to_prefix = null;

	public function __construct($db, $to_prefix)
	{
		$this->db = $db;
		$this->to_prefix = $to_prefix;
	}
}