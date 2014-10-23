<?php

namespace OpenImporter;

/**
 * The starting point for the second step of any importer.
 */
abstract class Step2BaseImporter extends BaseImporter
{
	abstract public function run();
}