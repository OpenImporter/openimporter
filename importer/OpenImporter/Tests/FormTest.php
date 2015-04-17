<?php
/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 2.0 Alpha
 */

namespace OpenImporter\Importers\sources\Tests;

use OpenImporter\Core\Form;
use OpenImporter\Core\DummyLang;

class FormTest extends \PHPUnit_Framework_TestCase
{
	/**
	 * @covers OpenImporter\Core\Form::__set
	 */
	public function testSet()
	{
		$object = new \ReflectionClass('OpenImporter\\Core\\Form');
		$instance = $object->newInstanceArgs(array(new DummyLang()));
		$property = $object->getProperty('data');
		$property->setAccessible(true);

		$instance->test = 123;

		$this->assertEquals(array('test' => 123), $property->getValue($instance));
	}

	/**
	 * @covers OpenImporter\Core\Form::__set
	 *
	 * @expectedException OpenImporter\Core\FormException
	 * @expectedExceptionMessage Use Form::addOptions or Form::addField to set new fields
	 */
	public function testSetError()
	{
		$object = new \ReflectionClass('OpenImporter\\Core\\Form');
		$instance = $object->newInstanceArgs(array(new DummyLang()));
		$property = $object->getProperty('data');
		$property->setAccessible(true);

		$instance->options = 123;
	}

	/**
	 * @covers OpenImporter\Core\Form::__get
	 */
	public function testGet()
	{
		$object = new \ReflectionClass('OpenImporter\\Core\\Form');
		$instance = $object->newInstanceArgs(array(new DummyLang()));
		$instance->test = 123;

		$this->assertEquals(123, $instance->test);
		$this->assertNull($instance->nontest);
	}

	/**
	 * @covers OpenImporter\Core\Form::addSeparator
	 */
	public function testAddSeparator()
	{
		$object = new \ReflectionClass('OpenImporter\\Core\\Form');
		$instance = $object->newInstanceArgs(array(new DummyLang()));
		$property = $object->getProperty('data');
		$property->setAccessible(true);

		$instance->addSeparator();

		$this->assertEquals(array('options' => array(array())), $property->getValue($instance));
	}
}