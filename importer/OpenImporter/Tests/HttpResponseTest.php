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

		$this->assertEquals(array(array(
			'message' => 'test_error',
			'trace' => false,
			'line' => false,
			'file' => false
		)), $property->getValue($instance));

		// Reset
		$instance = $object->newInstanceArgs(array(new ResponseHeader()));

		$instance->addErrorParam(array('test', 'test_error'));

		$this->assertEquals(array(array(
			'message' => array('test', 'test_error'),
			'trace' => false,
			'line' => false,
			'file' => false
		)), $property->getValue($instance));
	}

	public function testGetErrors()
	{
		$instance = new HttpResponse(new ResponseHeader());
		$instance->addErrorParam('test_error');
		$instance->addErrorParam(array('test %1$s', 'test_error'));

		$this->assertEquals(array(array(
			'message' => 'test_error',
			'trace' => false,
			'line' => false,
			'file' => false
		),array(
			'message' => 'test test_error',
			'trace' => false,
			'line' => false,
			'file' => false
		)), $instance->getErrors());
	}

	public function testAddHeader()
	{
		$headers = new DummyResponseHeaderForHttpResponseTest();
		$instance = new HttpResponse($headers);

		$instance->addHeader('key', 'val');

		$this->assertEquals(array('key' => 'val'), $headers->get());
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testSendHeader()
	{
		$headers = new ResponseHeader();
		$instance = new HttpResponse($headers);

		$instance->addHeader('key', 'val');
		$instance->sendHeaders();
		$this->assertEquals( array( 'key: val' ), xdebug_get_headers() );
	}
}

class DummyResponseHeaderForHttpResponseTest extends ResponseHeader
{
	public function get()
	{
		return $this->headers;
	}
}