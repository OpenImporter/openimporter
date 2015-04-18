<?php
/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 2.0 Alpha
 */

namespace OpenImporter\Importers\sources\Tests;

use OpenImporter\Core\ResponseHeader;

class ResponseHeaderTest extends \PHPUnit_Framework_TestCase
{
	/**
	 * @covers OpenImporter\Core\ResponseHeader::set
	 */
	public function testSet()
	{
		$object = new \ReflectionClass('OpenImporter\\Core\\ResponseHeader');
		$property = $object->getProperty('headers');
		$property->setAccessible(true);

		//We need to create an empty object to pass to
		//ReflectionProperty's getValue method
		$instance = new ResponseHeader();
		$instance->set('test', 'something');
		$this->assertEquals(array('test' => 'something'), $property->getValue($instance));

		$instance = new ResponseHeader();
		$instance->set('test:something');
		$this->assertEquals(array('test' => 'something'), $property->getValue($instance));

		$instance = new ResponseHeader();
		$instance->set(' test : something ');
		$this->assertEquals(array('test' => 'something'), $property->getValue($instance));

		$instance = new ResponseHeader();
		$instance->set('test');
		$this->assertEquals(array('test' => null), $property->getValue($instance));
	}

	/**
	 * @covers OpenImporter\Core\ResponseHeader::get
	 */
	public function testGet()
	{
		$instance = new ResponseHeader();
		$instance->set('test', 'something');

		$this->assertEquals(array('test: something'), $instance->get('test'));
	}

	/**
	 * @covers OpenImporter\Core\ResponseHeader::remove
	 */
	public function testRemove()
	{
		$instance = new ResponseHeader();
		$instance->set('test', 'something');
		$instance->set('test2', 'remove');
		$instance->remove('test2');

		$this->assertEquals(array('test: something'), $instance->get('test'));
	}
}