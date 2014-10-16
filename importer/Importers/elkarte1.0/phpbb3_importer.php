<?php

class phpBB3
{
	public function getName()
	{
		return 'phpBB3';
	}

	public function getVersion()
	{
		return 'ElkArte 1.0';
	}

	public function setDefines()
	{
		define('IN_PHPBB', 1);
	}

	public function loadSettings($path)
	{
		// Error silenced in case of odd server configurations (open_basedir mainly)
		if (@file_exists($path . '/config.php'))
		{
			require_once($path . '/config.php');
			return true;
		}
		else
			return false;
	}

	public function getPrefix()
	{
		global $dbname, $table_prefix;

		return '`' . $dbname . '`.' . $table_prefix;
	}

	public function getTableTest()
	{
		return 'users';
	}
}