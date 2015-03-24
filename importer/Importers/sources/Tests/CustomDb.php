<?php

namespace OpenImporter\Importers\sources\Tests;

class CustomDb
{
	protected $queries = array();

	public function hasRes($string)
	{
		$key = $this->generateKey($string);
		return array_key_exists($key, $this->queries);
	}

	public function getRes($string)
	{
		$key = $this->generateKey($string);
		if ($this->hasRes($string))
			return $this->queries[$key];
		else
			return null;
	}

	protected function generateKey($string)
	{
		return md5((string) $string);
	}
}