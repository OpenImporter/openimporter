<?php

class mybb16 extends AbstractSourceImporter
{
	protected $setting_file = '/inc/config.php';

	public function getName()
	{
		return 'MyBB 1.6';
	}

	public function getVersion()
	{
		return 'ElkArte 1.0';
	}

	public function getPrefix()
	{
		global $config;

		return '`' . $config['database']['database'] . '`.' . $config['database']['table_prefix'];
	}

	public function getTableTest()
	{
		return 'users';
	}
}