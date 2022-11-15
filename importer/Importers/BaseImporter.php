<?php
/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD https://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0
 */

namespace Importers;

use OpenImporter\Configurator;
use OpenImporter\Database;

/**
 * The starting point for any step of any importer.
 */
abstract class BaseImporter
{
	/** @var \OpenImporter\Database */
	protected $db;

	/** @var \OpenImporter\Configurator */
	protected $config;

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
