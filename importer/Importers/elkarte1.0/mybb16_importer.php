<?php

class mybb16
{
	public function getName()
	{
		return 'MyBB 1.6';
	}

	public function getVersion()
	{
		return 'ElkArte 1.0';
	}

	public function loadSettings($path)
	{
		// Error silenced in case of odd server configurations (open_basedir mainly)
		if (@file_exists($path . '/inc/config.php'))
		{
			require_once($path . '/inc/config.php');
			return true;
		}
		else
			return false;
	}

	public function getPrefix()
	{
		global $config;

		return '`' . $config['database']['database'] '`.' $config['database']['table_prefix'];
	}

	public function getTableTest()
	{
		return $from_prefix . 'users';
	}
}