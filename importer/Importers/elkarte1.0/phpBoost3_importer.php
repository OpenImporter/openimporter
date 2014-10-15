<?php

class PHPBoost3
{
	public function getName()
	{
		return 'PHPBoost3';
	}

	public function getVersion()
	{
		return 'ElkArte 1.0';
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
		global $boost_database, $boost_prefix;

		return '`' . $boost_database '`.' $boost_prefix;
	}

	public function getTableTest()
	{
		global $boost_prefix;

		return $boost_prefix . 'member';
	}
}