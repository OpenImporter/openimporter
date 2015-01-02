<?php

class DummyDb
{
	public function query($string)
	{
		$array = $this->parseSql($string);

		return new DummyDbResults($array);
	}

	public function fetch_assoc($res)
	{
		return $res->getArray();
	}

	public function fetch_row($res)
	{
		return array_values($res->getArray());
	}

	public function insert()
	{
	}

	protected function parseSql($string)
	{
		// clean tabs
		$string = trim($string);
		// remove SELECT
		$string = trim(substr($string, 6));
		$from = strpos($string, 'FROM {');
		$string = substr($string, 0, $from);
		$chunks = array_map('trim', preg_split('~,(?! [^(]*\))~i', str_replace("\n", '', $string)));

		$array = array();
		foreach ($chunks as $chunk)
		{
			$res = explode(' as ', strtolower($chunk));
			if (count($res) == 2)
				$array[$res[1]] = '';
			else
				$array[$res[0]] = '';
		}

		return $this->trimDots($array);
	}

	protected function trimDots($array)
	{
		$return = array();
		foreach ($array as $key => $val)
		{
			if (strpos($key, '.') === false)
			{
				$return[$key] = $val;
			}
			else
			{
				$exp = explode('.', $key);
				$return[$exp[1]] = $val;
			}
		}

		return $return;
	}
}

class DummyDbResults
{
	protected $array;
	protected $returned = false;

	public function __construct($array)
	{
		$this->array = $array;
	}

	public function setArray($array)
	{
		$this->array = $array;
	}

	public function getArray()
	{
		if ($this->returned)
			return false;

		$this->returned = true;
		return $this->array;
	}
}