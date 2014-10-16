<?php

class wbb3_1
{
	public function getName()
	{
		return 'Woltlab Burning Board 3.1';
	}

	public function getVersion()
	{
		return 'ElkArte 1.0';
	}

	public function loadSettings($path)
	{
		// Error silenced in case of odd server configurations (open_basedir mainly)
		if (@file_exists($path . '/wcf/config.inc.php'))
		{
			require_once($path . '/wcf/config.inc.php');
			return true;
		}
		else
			return false;
	}

	public function getPrefix()
	{
		global $dbName;

		return '`' . $dbName . '`.';
	}

	// @todo why $wbb_prefix is not in getPrefix?
	public function getTableTest()
	{
		global $wbb_prefix;

		return $wbb_prefix . 'user';
	}
}