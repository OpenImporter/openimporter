<?php

class XenForo1_1 extends AbstractSourceImporter
{
	protected $setting_file = '/includes/config.php';

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
		global $xf_database, $xf_prefix;

		return '`' . $xf_database . '`.' . $xf_prefix;
	}

	public function getTableTest()
	{
		return 'user';
	}
}