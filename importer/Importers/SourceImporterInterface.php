<?php
/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 2.0 Alpha
 */

namespace OpenImporter\Importers;

/**
 * This is the interface that any Source class should implement.
 */
interface SourceImporterInterface
{
	public function setUtils($db, $config);

	public function setField($key, $val);

	public function getAllFields();

	public function getField($key);

	public function getName();

	public function getVersion();

	public function getDbPrefix();

	public function getDbName();

	public function dbConnectionData();

	public function loadSettings($path, $test = false);

	public function setDefines();

	public function setGlobals();

	public function callMethod($method, $params = null);
}