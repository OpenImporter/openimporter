<?php

/**
 * The starting point for the first step of any importer.
 */
abstract class Step1BaseImporter extends BaseImporter
{
	public function doSpecialTable($table, $params = null)
	{
		return $params;
	}

	public function fixTexts($row)
	{
		return $row;
	}
}