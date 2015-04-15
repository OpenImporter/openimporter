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
	protected $config = null;
	protected $current_step = 0;
	protected $do_not_stop = false;
	public $step = array();
	public $start = 0;
	public $max = 0;
	public $substep = 0;
	public $do_steps = null;

	public function __construct(Template $template, Configurator $config, $options)
	{
		$defaults = array(
			'start' => 0,
			'substep' => 0,
			'stop_time' => 5,
		);

		foreach ($defaults as $key => $val)
		{
			$real = isset($options[$key]) ? (int) $options[$key] : $val;

			// This condition covers the vase of stop_time set externally to 0
			if (empty($real))
				$real = $val;

			$this->{$key} = $real;
		}

		$this->start_time = time();
		$this->template = $template;
		$this->config = $config;
	}

	public function doNotStop()
	{
		$this->do_not_stop = true;
	}

	public function setStep($step)
	{
		// Obvious comment is obvious: if the step is not yet set, let's initialize it
		if (!isset($this->step[$step]))
		{
			// If have never encountered that step some good default is fine
			if (empty($this->config->store['progress'][$step]))
			{
				$this->step[$step] = array(
					'substep' => 0,
					'presql' => false,
					'status' => 0,
					'completed' => false,
				);
			}
			// If we know already that step from the past, let's restore it
			else
			{
				$this->step[$step] = $this->config->store['progress'][$step];
			}

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

	public function resetStep()
	{
		$this->step = array();
	}

	public function isStepCompleted()
	{
		return isset($this->step[$this->current_step]) && $this->step[$this->current_step]['completed'];
	}

	public function stepCompleted()
	{
		$this->start = 0;
		$this->step[$this->current_step]['completed'] = true;
	}

	public function isPreSqlDone()
	{
		return !empty($this->step[$this->current_step]['presql']);
	}

	public function preSqlDone()
	{
		$this->step[$this->current_step]['presql'] = true;
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

		$this->store();

		@set_time_limit(300);
		if (is_callable('apache_reset_timeout'))
			apache_reset_timeout();

		if (time() - $this->start_time < $this->stop_time || $this->do_not_stop)
			return;

		throw new PasttimeException($this->template, $bar, $_SESSION['import_progress'], $this->max, $this->current_step, $this->start);
	}

	protected function store()
	{
		$this->config->store['progress'] = $this->step;
	}

	public function advanceSubstep($substep)
	{
		if ($this->step[$this->current_step]['status'] == 0)
			$this->template->status(1, '', true);

		$this->step[$this->current_step]['status'] = 1;
		$this->step[$this->current_step]['substep'] += $substep;
		flush();
	}
}