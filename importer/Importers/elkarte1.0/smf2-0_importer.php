<?php

class SMF2_0
{
	public function getName()
	{
		return 'SMF2_0';
	}

	public function getVersion()
	{
		return 'ElkArte 1.0';
	}

	public function setDefines()
	{
		define('SMF', 1);
	}

	public function loadSettings($path)
	{
		// Error silenced in case of odd server configurations (open_basedir mainly)
		if (@file_exists($path . '/Settings.php'))
		{
			require_once($path . '/Settings.php');
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
		return 'members';
	}
}