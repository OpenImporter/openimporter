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
	protected $max;
	protected $step;
	protected $start;

	public function __construct(Template $template, $bar, $import_progress, $max, $step, $start)
	{
		$this->template = $template;
		$this->bar = $bar;
		$this->import_progress = $import_progress;
		$this->max = $max;
		$this->step = $step;
		$this->start = $start;
	}

	public function doExit()
	{
		$this->template->timeLimit($this->bar, $this->import_progress, $this->max, $this->step, $this->start);
		$this->template->footer();
	}
}