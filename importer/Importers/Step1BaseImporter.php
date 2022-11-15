<?php
/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD https://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0
 */

namespace Importers;

/**
 * The starting point for the first step of any importer.
 * Step 1 is where the actual conversion happens, where the data are moved
 * from the source system to the destination one.
 * It's the only step that <b>shall</s> know about both the systems.
 */
abstract class Step1BaseImporter extends BaseImporter
{
	public function beforeSql($method)
	{
		if (method_exists($this, $method))
		{
			$this->$method();
		}
	}

	public function doSpecialTable($table, $params = null)
	{
		return $params;
	}

	public function fixTexts($row)
	{
		return $row;
	}
}
