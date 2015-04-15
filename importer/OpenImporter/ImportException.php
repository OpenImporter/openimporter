<?php
/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 2.0 Alpha
 */

namespace OpenImporter\Core;

use OpenImporter\Core\Template;
use OpenImporter\Core\DummyLang;
use OpenImporter\Core\Configurator;

/**
 * class ImportException extends the build-in Exception class and
 * catches potential errors
 */
class ImportException extends \Exception
{
	protected static $import = null;

	public function doExit($template = null)
	{
		self::exceptionHandler($this, $template);
	}

	public static function setImportManager(ImportManager $import)
	{
		self::$import = $import;
	}

	public static function errorHandlerCallback($code, $string, $file, $line)
	{
		if (error_reporting() == 0)
			return;

		$e = new self($string, $code);
		$e->line = $line;
		$e->file = $file;
		throw $e;
	}

	/**
	 * @param \Exception $exception
	 */
	public static function exceptionHandler($exception, $template = null)
	{
		if (error_reporting() == 0)
			return;

		if ($template === null)
		{
			if (!empty(self::$import))
				$template = self::$import->template;
			else
				$template = new Template(new DummyLang(), new Configurator());
		}
		$message = $exception->getMessage();
		$trace = $exception->getTrace();
		$line = $exception->getLine();
		$file = $exception->getFile();
		$template->error($message, isset($trace[0]['args'][1]) ? $trace[0]['args'][1] : null, $line, $file);
	}
}