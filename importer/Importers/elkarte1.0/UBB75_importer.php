<?php

class UBB_7_5 extends AbstractSourceImporter
{
	public function getName()
	{
		return 'UBB Threads 7.5.x';
	}

	public function getVersion()
	{
		return 'ElkArte 1.0';
	}

	public function loadSettings($path, $test = false)
	{
		if ($test)
			return @file_exists($path . '/includes/config.inc.php');

		// Error silenced in case of odd server configurations (open_basedir mainly)
		if (@file_exists($path . '/includes/config.inc.php'))
		{
			require_once($path . '/includes/config.inc.php');
			return true;
		}
		else
			return false;
	}

	public function getPrefix()
	{
		global $db_name, $db_prefix;

		return '`' . $db_name . '`.' . $db_prefix;
	}

	public function getTableTest()
	{
		return 'USERS';
	}
}

/**
 * Utility functions
 */
function fix_quotes($string, $new_lines = true)
{
	if ($new_lines)
		return strtr(htmlspecialchars($string, ENT_QUOTES), array("\n" => '<br />'));
	else
		return htmlspecialchars($string);
}

function convert_birthdate($date)
{
	$tmp_birthdate = explode('/', $date);
	if (count($tmp_birthdate) == 3)
	{
		if (strlen($tmp_birthdate[2]) != 4)
			$tmp_birthdate[2] = '0004';
		return $tmp_birthdate[2] . '-' . str_pad($tmp_birthdate[0], 2, "0", STR_PAD_LEFT) . '-' . str_pad($tmp_birthdate[1], 2, "0", STR_PAD_LEFT);
	}
	else
		return '0001-01-01';
}