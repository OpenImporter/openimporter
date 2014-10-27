<?php

abstract class AbstractSourceImporter
{
	protected $setting_file = '';

	protected $path = '';

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

			return $this->testPath($path);
		}

		if (empty($this->setting_file))
			return true;

		// Error silenced in case of odd server configurations (open_basedir mainly)
		if ($this->testPath($path))
		{
			require_once($path . $this->setting_file);
			return true;
		}
		else
			return false;
	}

	protected function testPath($path)
	{
		$found = @file_exists($path . $this->setting_file);

		if ($found)
			$this->path = $path;

		return $found;
	}

	public function setDefines()
	{
	}

	public function setGlobals()
	{
	}
}