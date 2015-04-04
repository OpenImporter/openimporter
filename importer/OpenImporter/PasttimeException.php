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
class PasttimeException extends \Exception
{
	protected $template;
	protected $bar;
	protected $import_progress;
	protected $import_overall;

	public function __construct($template, $bar, $import_progress, $import_overall)
	{
		$this->template = $template;
		$this->bar = $bar;
		$this->import_progress = $import_progress;
		$this->import_overall = $import_overall;
	}

	public function doExit()
	{
		$template->time_limit($this->template, $this->bar, $this->import_progress, $this->import_overall);
		$template->footer();
	}
}