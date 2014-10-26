<?php

class WP3 extends AbstractSourceImporter
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
		if (@file_exists($path . '/wp-includes/version.php'))
		{
			// We can't load wp-config.php,
			// there's a require_once at the end of that file which
			// would load the enire wp stuff an break the importer engine.
			require_once($path . '/wp-includes/version.php');
			return true;
		}
		else
			return false;
	}

	public function getPrefix()
	{
		global $wp_database, $wp_prefix;

		return '`' . $wp_database . '`.' . $wp_prefix;
	}

	public function getTableTest()
	{
		return 'users';
	}
}