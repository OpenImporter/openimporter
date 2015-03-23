<?php
use Symfony\Component\Yaml\Yaml;
use OpenImporter\Core

require_once(__DIR__ . '/EnvInit.php');
require_once(BASEDIR . '/Importers/sources/mybb16_importer.php');

class mybb16Test extends \PHPUnit_Framework_TestCase
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
		self::$xml = self::read(BASEDIR . '/Importers/sources/mybb16_importer.xml');
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
		$this->utils['db'] = new DummyDb(new CustomDbValues());
		// @todo this should be detected from the XML?
		$this->utils['importer'] = new mybb16();
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

class CustomDbValues extends CustomDb
{
	protected $queries = array();

	public function __construct()
	{
		$this->config = new DummyConfig();

		$this->queries = array(
			md5("
					SELECT value
					FROM {$this->config->source->from_prefix}settings
					WHERE name = 'uploadspath'
					LIMIT 1") => array(array(
				'value' => BASEDIR . '/Importers/sources'
			)),
			md5('
			SELECT pid AS id_msg, downloads, filename, filesize, attachname
			FROM {$from_prefix}attachments;
		') => array(array(
				'id_msg' => 1,
				'downloads' => 0,
				'filename' => 'mybb16_importer.php',
				'filesize' => 0,
				'attachname' => 'mybb16_importer.php'
			))
		);
	}
}