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

class Utils
{
	protected $time_start = 0;

	public static function setStart()
	{
		self::$time_start = time();
	}

	/**
	 * Checks if we've passed a time limit..
	 *
	 * @param int|null $substep
	 * @param int $stop_time
	 * @return null
	 */
	public static function pastTime($substep = null, $stop_time = 5)
	{
		global $import;

		if (isset($_GET['substep']) && $_GET['substep'] < $substep)
			$_GET['substep'] = $substep;

		// some details for our progress bar
		if (isset($import->count->$substep) && $import->count->$substep > 0 && isset($_REQUEST['start']) && $_REQUEST['start'] > 0 && isset($substep))
			$bar = round($_REQUEST['start'] / $import->count->$substep * 100, 0);
		else
			$bar = false;

		@set_time_limit(300);
		if (is_callable('apache_reset_timeout'))
			apache_reset_timeout();

		if (time() - self::$time_start < $stop_time)
			return;

		Throw new PasttimeException($import->template, $bar, $_SESSION['import_progress'], $_SESSION['import_overall']);
	}

	/**
	 * @todo apparently unused
	 *
	 * helper function for storing vars that need to be global
	 *
	 * @param string $variable
	 * @param string $value
	 */
	public static function store_global($variable, $value)
	{
		$_SESSION['store_globals'][$variable] = $value;
	}

	public static function print_dbg($val)
	{
		echo '<pre>';
		print_r($val);
		echo '</pre>';
	}
}