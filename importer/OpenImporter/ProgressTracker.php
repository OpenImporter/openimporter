<?php
/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 2.0 Alpha
 *
 * This file contains code based on:
 *
 * Simple Machines Forum (SMF)
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:	BSD, See included LICENSE.TXT for terms and conditions.
 */

namespace OpenImporter\Core;

class ProgressTracker
{
	protected $start_time = 0;
	protected $stop_time = 5;
	protected $template = null;
	protected $current_step = 0;
	public $step = array();
	public $start = 0;
	public $substep = 0;

	public function __construct(Template $template, $start, $substep, $stop_time = 5)
	{
		$this->start_time = time();
		$this->stop_time = $stop_time;
		$this->template = $template;
		$this->start = $start;
		$this->substep = $substep;
	}

	public function setStep($step)
	{
		if (!isset($this->step[$step]))
		{
			$this->step[$step] = array(
				'substep' => 0,
				'presql' => true,
				'status' => 0,
			);
			$this->current_step = $step;
		}
	}

	protected function initBar($start = 0, $substep = 0)
	{
		// some details for our progress bar
		if (isset($this->step[$substep]) && $this->step[$substep] > 0 && $start > 0 && isset($substep))
			$bar = round($start / $this->step[$substep] * 100, 0);
		else
			$bar = false;

		return $bar;
	}

	/**
	 * Checks if we've passed a time limit..
	 *
	 * @param int|null $substep
	 * @return null
	 */
	public function pastTime($substep = null)
	{
		// some details for our progress bar
		$bar = $this->initBar($start, $substep);

		@set_time_limit(300);
		if (is_callable('apache_reset_timeout'))
			apache_reset_timeout();

		if (time() - $this->start_time < $this->stop_time)
			return;

		throw new PasttimeException($this->template, $bar, $_SESSION['import_progress'], $_SESSION['import_overall'], $this->current_step, $this->substep, $this->start);
	}
}