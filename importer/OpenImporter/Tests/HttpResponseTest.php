<?php
/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 2.0 Alpha
 */

namespace OpenImporter\Importers\sources\Tests;

use OpenImporter\Core\HttpResponse;
use OpenImporter\Core\ResponseHeader;

class HttpResponseTest extends \PHPUnit_Framework_TestCase
{
	/**
	 * @covers OpenImporter\Core\HttpResponse::__set
	 */
	public function testSet()
	{
		$object = new \ReflectionClass('OpenImporter\\Core\\HttpResponse');
		$instance = $object->newInstanceArgs(array(new ResponseHeader()));
		$property = $object->getProperty('data');
		$property->setAccessible(true);

		//We need to create an empty object to pass to
		//ReflectionProperty's getValue method
		$instance->test = 123;

		$this->assertEquals(array('test' => 123), $property->getValue($instance));
	}

	/**
	 * @covers OpenImporter\Core\HttpResponse::__get
	 */
	public function testGet()
	{
		$instance = new HttpResponse(new ResponseHeader());
		$instance->test = 123;

		$this->assertEquals(123, $instance->test);
		$this->assertNull($instance->nontest);
	}
}