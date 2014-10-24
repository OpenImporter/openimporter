<?php

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