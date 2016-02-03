<?php

namespace OpenImporter;

/**
 * Temporary class to forward the currently static exception handler to a
 * default exception
 */
class ImportException extends \Exception
{
	public static function exception_handler($e)
	{
		throw new \Exception($e->getMessage(), $e->getCode(), $e);
	}
}