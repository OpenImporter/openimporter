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
	public $count = array();

	public function __construct(Template $template, $stop_time = 5)
	{
		$this->start_time = time();
		$this->stop_time = $stop_time;
		$this->template = $template;
	}

	/**
	 * Checks if we've passed a time limit..
	 *
	 * @param int|null $substep
	 * @return null
	 */
	public function pastTime($substep = null)
	{
		if (isset($_GET['substep']) && $_GET['substep'] < $substep)
			$_GET['substep'] = $substep;

		// some details for our progress bar
		if (isset($this->count[$substep]) && $this->count[$substep] > 0 && isset($_REQUEST['start']) && $_REQUEST['start'] > 0 && isset($substep))
			$bar = round($_REQUEST['start'] / $this->count[$substep] * 100, 0);
		else
			$bar = false;

		@set_time_limit(300);
		if (is_callable('apache_reset_timeout'))
			apache_reset_timeout();

		if (time() - $this->start_time < $this->stop_time)
			return;

		throw new PasttimeException($this->template, $bar, $_SESSION['import_progress'], $_SESSION['import_overall']);
	}
}