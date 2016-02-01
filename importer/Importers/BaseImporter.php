<?php
/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Alpha
 */

namespace Importers;

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
}