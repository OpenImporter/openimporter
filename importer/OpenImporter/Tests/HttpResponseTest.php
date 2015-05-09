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
	 * @covers OpenImporter\Core\HttpResponse::addErrorParam
	 */
	public function testAddErrorParam()
	{
		$object = new \ReflectionClass('OpenImporter\\Core\\HttpResponse');
		$instance = $object->newInstanceArgs(array(new ResponseHeader()));
		$property = $object->getProperty('error_params');
		$property->setAccessible(true);

		$instance->addErrorParam('test_error');

		$this->assertEquals(array('test_error'), $property->getValue($instance));

		// Reset
		$instance = $object->newInstanceArgs(array(new ResponseHeader()));

		$instance->addErrorParam(array('test', 'test_error'));

		$this->assertEquals(array(array('test', 'test_error')), $property->getValue($instance));
	}
	/**
	 * @covers OpenImporter\Core\HttpResponse::addErrorParam
	 */
	public function testGetErrors()
	{
		$instance = new HttpResponse(new ResponseHeader());
		$instance->addErrorParam('test_error');
		$instance->addErrorParam(array('test %1$s', 'test_error'));

		$this->assertEquals(array('test_error', 'test test_error'), $instance->getErrors());
	}
}