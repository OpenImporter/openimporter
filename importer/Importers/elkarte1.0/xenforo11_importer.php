<?php

class XenForo1_1
{
	public function getName()
	{
		return 'Wordpress 3.x';
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
		global $xf_database, $xf_prefix;

		return '`' . $xf_database '`.' . $xf_prefix;
	}

	public function getTableTest()
	{
		return 'user';
	}
}