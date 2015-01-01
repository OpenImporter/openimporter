<?php
use Symfony\Component\Yaml\Yaml;

class BaseTest extends \PHPUnit_Framework_TestCase
{
	protected static function read($file)
	{
		$xml = simplexml_load_file($file, 'SimpleXMLElement', LIBXML_NOCDATA);
		if (!$xml)
			throw new ImportException('XML-Syntax error in file: ' . $file);

		return $xml;
	}

	protected static function getConfig($file)
	{
		return Yaml::parse(file_get_contents($file));
	}

	protected function getStep($name)
	{
		foreach (self::$xml->step as $step)
		{
			if ($step['id'] == $name)
				return $step;
		}
	}

	protected function parseSql($string)
	{
		// clean tabs
		$string = trim($string);
		// remove SELECT
		$string = trim(substr($string, 0, 6));
		$from = strpos($string, 'FROM {');
		$string = substr($string, 0, $from);
		$chunks = array_map('trim', explode(',', $string));

		$array = array();
		foreach ($chunks as $chunk)
		{
			$res = explode(' as ', $chunk);
			if (count($res) == 2)
				$array[] = $res[1];
			else
				$array[] = $res[0];
		}

		return $array;
	}
}