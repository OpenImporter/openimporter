<?php

namespace OpenImporter\Importers\sources\Tests;

class DummyDb
{
	protected $customValues = null;

	public function __construct($customValues = null)
	{
		$this->customValues = $customValues;
	}

	public function query($string)
	{
		if ($this->customValues !== null && $this->customValues->hasRes($string))
			$array = $this->customValues->getRes($string);
		else
			$array = array($this->parseSql($string));

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

	public function free_result()
	{
	}

	protected function parseSql($string)
	{
		$string = $this->stripUseless($string);
		$chunks = $this->splitToChunks($string);

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

	protected function splitToChunks($string)
	{
		$len = strlen($string);
		$pos = -1;
		$in = 0;
		$in_for = 0;
		$chunk = '';
		$chunks = array();

		while ($pos < $len)
		{
			$pos++;
			if ($pos === ($len - 1))
			{
				$chunks[] = $chunk;
				break;
			}

			if ($in)
			{
				switch ($in_for)
				{
					// Parentheses
					case 1:
					{
						if ($string[$pos] === ')')
							$in--;
						elseif ($string[$pos] === '(')
							$in++;
						break;
					}
					// Single quotes
					case 2:
					{
						if ($string[$pos] === '\'')
							$in--;
						break;
					}
					default:
					{
					}
				}
// 				$in = max($in, 0);
			}
			else
			{
				if ($string[$pos] === '(')
				{
					$in++;
					$in_for = 1;
				}
				elseif ($string[$pos] === '\'')
				{
					$in++;
					$in_for = 2;
				}

				if (empty($in) && $string[$pos] === ',')
				{
					$chunks[] = $chunk;
					$chunk = '';
					continue;
				}
			}
			$chunk .= $string[$pos];
		}

		return array_map('trim', $chunks);
	}

	protected function stripUseless($string)
	{
		// clean tabs
		$string = trim($string);
		// remove SELECT
		$string = trim(substr($string, 6));
		$from = strpos($string, 'FROM ');
		$string = substr($string, 0, $from);

		// Remove excessive spaces and make it a single line
		$string = implode(' ', array_map('trim', explode("\n", $string)));

		return $string;
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
	protected $returned = 0;

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
		if ($this->returned >= count($this->array))
			return false;

		$current = $this->array[$this->returned];
		$this->returned++;
		return $current;
	}
}