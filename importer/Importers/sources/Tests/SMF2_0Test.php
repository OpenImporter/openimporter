<?php

namespace OpenImporter\Importers\sources\Tests;

use Symfony\Component\Yaml\Yaml;
use OpenImporter\Core\ImportException;
use OpenImporter\Importers\sources\SMF2_0_Importer;

require_once(__DIR__ . '/EnvInit.php');
require_once(BASEDIR . '/Importers/sources/SMF2_0_Importer.php');

class SMF2_0Test extends \PHPUnit_Framework_TestCase
{
	protected static $xml = null;
	protected static $yml = null;
	protected $utils = array();

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

	public static function setUpBeforeClass()
	{
		self::$xml = self::read(BASEDIR . '/Importers/sources/SMF2_0_Importer.xml');
		self::$yml = self::getConfig(BASEDIR . '/Importers/importer_skeleton.yml');
	}

	protected function getStepConfig($index)
	{
		$conf = array();
		foreach (self::$yml[$index]['query'] as $key => $val)
		{
			if (is_array($val))
				$conf[] = key($val);
			else
				$conf[] = $val;
		}
		return $conf;
	}

	protected function setUp()
	{
		$this->utils['db'] = new DummyDb();
		// @todo this should be detected from the XML?
		$this->utils['importer'] = new SMF2_0_Importer();
		$this->utils['importer']->setUtils($this->utils['db'], new DummyConfig());
	}

	protected function stepQueryTester($step)
	{
		$id = (string) $step['id'];

		$this_config = $this->getStepConfig($id);
		$tmp = $this->utils['db']->query($step->query);

		$generated = $this->utils['db']->fetch_assoc($tmp);
		$generated = $this->utils['importer']->callMethod('preparse' . ucFirst($id), array($generated));

		foreach ($generated[0] as $key => $entry)
			$this->assertContains($key, $this_config);
	}

	public function testAll()
	{
		foreach (self::$xml->step as $step)
		{
			if (isset($step->query))
			{
				$this->stepQueryTester($step);
			}
		}
	}
}