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
	protected $step;
	protected $substep;
	protected $start;

	public function __construct(Template $template, $bar, $import_progress, $import_overall, $step, $substep, $start)
	{
		$this->template = $template;
		$this->bar = $bar;
		$this->import_progress = $import_progress;
		$this->import_overall = $import_overall;
		$this->step = $step;
		$this->substep = $substep;
		$this->start = $start;
	}

	public function doExit()
	{
		$this->template->timeLimit($this->bar, $this->import_progress, $this->import_overall, $this->step, $this->substep, $this->start);
		$this->template->footer();
	}
}