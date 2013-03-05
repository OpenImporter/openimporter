<?php

/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *  
 * @version 1.0 Alpha
 * 
 * class import_exception extends the build-in Exception class and
 * catches potential errors
 */

if (!defined('OPENIMPORTER'))
	die('No direct access allowed...');

class import_exception extends Exception
{
	public static function error_handler_callback($code, $string, $file, $line, $context)
	{
		$e = new self($string, $code);
		$e->line = $line;
		$e->file = $file;
		throw $e;
	}

	public static function exception_handler($exception)
	{
		global $import;

		$message = $exception->getMessage();
		$trace = $exception->getTrace();
		$line = $exception->getLine();
		$file = $exception->getFile();
		$import->template->error($message, $trace[0]['args'][1], $line, $file);
	}
}