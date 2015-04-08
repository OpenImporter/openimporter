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