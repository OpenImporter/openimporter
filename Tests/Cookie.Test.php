<?php


use OpenImporter\Cookie;

/**
 * We need Cooooookies..
 */
class CookieTest extends PHPUnit\Framework\TestCase
{
	/**
	 * Prepare what is necessary to use in these tests.
	 *
	 * setUp() is run automatically by the testing framework before each test method.
	 */
	protected function setUp(): void
	{
	}

	/**
	 * cleanup data we no longer need at the end of the tests in this class.
	 *
	 * tearDown() is run automatically by the testing framework after each test method.
	 */
	protected function tearDown(): void
	{
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testSet()
	{
		$cookie = new Cookie();

		$result = $cookie->set('testSet');
		$this->assertTrue($result);
		$this->assertEquals(serialize('testSet'), $_COOKIE['openimporter_cookie']);

		$result = $cookie->set('testSet', 'another_name');
		$this->assertTrue($result);
		$this->assertEquals(serialize('testSet'), $_COOKIE['another_name']);

		$result = $cookie->set(null);
		$this->assertFalse($result);
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testGet()
	{
		$cookie = new Cookie();

		$cookie->set('testGet');
		$result = $cookie->get('openimporter_cookie');
		$this->assertEquals('testGet', $result);

		$cookie->set('testGet', 'another_name');
		$result = $cookie->get('another_name');
		$this->assertEquals('testGet', $result);

		$result = $cookie->get('random');
		$this->assertFalse($result);
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testDestroy()
	{
		$cookie = new Cookie();

		$cookie->set('testDestroy');
		$result = $cookie->destroy();
		$this->assertTrue($result);
		$this->assertFalse(isset($_COOKIE['openimporter_cookie']));

		$cookie->set('testDestroy', 'another_name');
		$result = $cookie->destroy('another_name');
		$this->assertTrue($result);
		$this->assertFalse(isset($_COOKIE['another_name']));
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testExtend()
	{
		$cookie = new Cookie();

		$cookie->set('testSet');
		$result = $cookie->extend('testExtend');
		$this->assertTrue($result);
		$this->assertEquals(serialize(array('testSet', 'testExtend')), $_COOKIE['openimporter_cookie']);

		$cookie->set('testSet', 'another_name');
		$result = $cookie->extend('testExtend', 'another_name');
		$this->assertTrue($result);
		$this->assertEquals(serialize(array('testSet', 'testExtend')), $_COOKIE['another_name']);

		$cookie->set('testSet');
		$result = $cookie->extend(null);
		$this->assertFalse($result);
	}
}