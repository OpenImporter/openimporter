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

/**
 * this class extends the standard php exception class and catches errors 
 */
class import_exception extends Exception
{
	/**
	 * @name error_handler_callback
	 * @param type $code
	 * @param type $string
	 * @param type $file
	 * @param type $line
	 * @param type $context
	 * @throws self 
	 * 
	 * replacement function for error_handler
	 */
	public static function error_handler_callback($code, $string, $file, $line, $context)
	{
		$e = new self($string, $code);
		$e->line = $line;
		$e->file = $file;
		throw $e;
	}

	/**
	 * @name exception_handler
	 * @global type $import
	 * @param type $exception 
	 * 
	 * exception_handler, displays exceptions inside a template. Even if there
	 * is an error, we still want a nice look ;)
	 */
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