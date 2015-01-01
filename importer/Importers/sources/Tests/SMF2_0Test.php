<?php
require_once('./EnvInit.php');
require_once('./BaseTest.php');

class SMF2_0Test extends BaseTest
{
	protected static $xml = null;
	protected static $yml = null;

	public static function setUpBeforeClass()
	{
		self::$xml = self::read(BASEDIR . '/Importers/sources/smf2_0_importer.xml');
		self::$yml = self::getConfig(BASEDIR . '/Importers/importer_skeleton.yml');
	}

	public function testMembers()
	{
		$step = $this->getStep('members');
		$this_config = self::$yml['members'];
		$generated = $this->parseSql($step->query);

		foreach ($generated as $entry)
			$this->assertArrayHasKey($entry, $this_config);
	}
}