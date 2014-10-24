<?php

/**
 * The starting point for the second step of any importer.
 */
abstract class Step3BaseImporter extends BaseImporter
{
	abstract public function run($import_script);
}