<?php

class WP3 extends AbstractSourceImporter
{
	protected $setting_file = '/wp-includes/version.php';

	public function getName()
	{
		return 'Wordpress 3.x';
	}

	public function getVersion()
	{
		return 'ElkArte 1.0';
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