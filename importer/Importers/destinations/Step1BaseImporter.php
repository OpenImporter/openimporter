<?php
/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 2.0 Alpha
 */

namespace OpenImporter\Importers\destinations;

/**
 * The starting point for the first step of any importer.
 * Step 1 is where the actual conversion happens, where the data are moved
 * from the source system to the destination one.
 * It's the only step that <b>shall</s> know about both the systems.
 */
abstract class Step1BaseImporter extends BaseImporter
{
	protected function prepareRow($row, $special_table)
	{
		$row = $this->doSpecialTable($special_table, $row);

		// fixing the charset, we need proper utf-8
		$row = fix_charset($row);

		$row = $this->fixTexts($row);

		return $row;
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