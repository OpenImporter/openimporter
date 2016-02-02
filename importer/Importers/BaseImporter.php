<?php
/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Alpha
 */

namespace Importers;

use OpenImporter\Configurator;
use OpenImporter\Database;

/**
 * The starting point for any step of any importer.
 */
abstract class BaseImporter
{
	/**
	 * @var Database
	 */
	protected $db = null;

	/**
	 * @var Configurator
	 */
	protected $config = null;

	/**
	 * BaseImporter constructor.
	 *
	 * @param Database $db
	 * @param Configurator $config
	 */
	public function __construct($db, $config)
	{
		$this->db = $db;
		$this->config = $config;
	}
}