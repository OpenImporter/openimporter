<?php

namespace OpenImporter\Importers\sources\Tests;

use OpenImporter\Core\DummyLang;

class DummyLangTest extends \PHPUnit_Framework_TestCase
{
	/**
	 * @covers OpenImporter\Core\DummyLang::loadLang
	 */
	public function testLoadLang()
	{
		$lng = new DummyLang();

		$this->assertNull($lng->loadLang(BASEDIR . '/Languages'));
	}

	/**
	 * @covers OpenImporter\Core\DummyLang::has
	 */
	public function testHas()
	{
		$lng = new DummyLang();

		$this->assertTrue($lng->has('anything'));
	}

	/**
	 * @covers OpenImporter\Core\DummyLang::__get
	 */
	public function testGetter()
	{
		$lng = new DummyLang();

		$this->assertEquals('test', $lng->test);
	}

	/**
	 * @covers OpenImporter\Core\DummyLang::get
	 */
	public function testGet()
	{
		$lng = new DummyLang();

		$this->assertEquals('test', $lng->get('test'));
		$this->assertEquals('test val1', $lng->get(array('test', 'val1')));
		$this->assertEquals('test val1 val2', $lng->get(array('test', 'val1', 'val2')));
	}

	/**
	 * @covers OpenImporter\Core\DummyLang::getAll
	 */
	public function testGetAll()
	{
		$lng = new DummyLang();

		$this->assertEquals(array(), $lng->getAll());
	}
}