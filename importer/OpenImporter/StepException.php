<?php
/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 2.0 Alpha
 */

namespace OpenImporter\Core;

/**
 * class DatabaseException extends the build-in Exception class and
 * catches potential errors
 */
class StepException extends \Exception
{
	protected $template;

	public function __construct(Template $template)
	{
		$this->template = $template;
	}

	public function doExit()
	{
		$this->template->footer();
	}
}