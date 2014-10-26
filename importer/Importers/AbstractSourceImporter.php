<?php

abstract class AbstractSourceImporter
{
	protected $setting_file = '';

	public abstract function getName();

	public abstract function getVersion();

	public abstract function getPrefix();

	public abstract function getTableTest();

	public function loadSettings($path, $test = false)
	{
		if ($test)
		{
			if (empty($this->setting_file))
				return null;

			return @file_exists($path . $this->setting_file);
		}

		if (empty($this->setting_file))
			return true;

		// Error silenced in case of odd server configurations (open_basedir mainly)
		if (@file_exists($path . $this->setting_file))
		{
			require_once($path . $this->setting_file);
			return true;
		}
		else
			return false;
	}

	public function setDefines()
	{
	}

	public function setGlobals()
	{
	}
}