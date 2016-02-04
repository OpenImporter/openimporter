<?php
/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Alpha
 */

namespace OpenImporter;

/**
 * Class ImportException
 * Extends the PHP build-in Exception class and catches potential errors
 */
class ImportException extends \Exception
{
	/**
	 * OI Error handler
	 * @param int $code
	 * @param string $string
	 * @param string $file
	 * @param int $line
	 *
	 * @throws ImportException
	 */
	public static function error_handler_callback($code, $string, $file, $line)
	{
		// Not telling?
		if (error_reporting() == 0)
		{
			return;
		}

		// Telling, just convert the error over to an exception
		$exception = new self($string, $code);
		$exception->line = $line;
		$exception->file = $file;

		ImportException::exception_handler($exception);
	}

	/**
	 * OI Exception handler
	 *
	 * @param \Exception $exception
	 * @param null $template
	 */
	public static function exception_handler($exception, $template = null)
	{
		global $oi_import;

		// Keeping secrets
		if (error_reporting() == 0)
		{
			return;
		}

		// Tell just your friends
		if ($template === null)
		{
			if (!empty($oi_import))
			{
				$template = $oi_import->template;
			}
			else
			{
				$template = new Template(null);
			}
		}

		$message = $exception->getMessage();
		$trace = $exception->getTrace();
		$line = $exception->getLine();
		$file = $exception->getFile();

		$template->error($message, isset($trace[0]['args'][1]) ? $trace[0]['args'][1] : null, $line, $file);
	}
}