<?php

class vBulletin_4
{
	public function getName()
	{
		return 'vBulletin 4';
	}

	public function getVersion()
	{
		return 'ElkArte 1.0';
	}

	public function loadSettings($path)
	{
		// Error silenced in case of odd server configurations (open_basedir mainly)
		if (@file_exists($path . '/includes/config.php'))
		{
			require_once($path . '/includes/config.php');
			return true;
		}
		else
			return false;
	}

	public function getPrefix()
	{
		global $config;

		return '`' . $config['Database']['dbname'] '`.' $config['Database']['tableprefix'];
	}

	public function getTableTest()
	{
		return 'user';
	}
}