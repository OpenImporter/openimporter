<?php

class UBB_7_5
{
	public function getName()
	{
		return 'UBB Threads 7.5.x';
	}

	public function getVersion()
	{
		return 'ElkArte 1.0';
	}

	public function loadSettings($path)
	{
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

		return '`' . $db_name '`.' $db_prefix;
	}

	public function getTableTest()
	{
		return 'USERS';
	}
}