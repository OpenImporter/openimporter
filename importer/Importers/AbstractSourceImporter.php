<?php

abstract class AbstractSourceImporter
{
	public abstract function getName();

	public abstract function getVersion();

	public abstract function getPrefix();

	public abstract function getTableTest();

	public function loadSettings($path, $test = false)
	{
		return null;
	}

	public function setDefines()
	{
	}

	public function setGlobals()
	{
	}
}