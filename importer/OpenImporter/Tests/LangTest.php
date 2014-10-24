<?php

class LangTest extends \PHPUnit_Framework_TestCase
{
	public function testLoadLangSuccess()
	{
		$lng = new Lang();

		$_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'en-GB,en;q=0.9,it;q=0.8';
		try
		{
			$lng->loadLang(BASEDIR . '/../../Languages');
		}
		catch (Exception $e)
		{
			$this->fail($e->getMessage());
		}
	}

	/**
	 * @expectedException Exception
	 * @expectedExceptionMessage Unable to detect language file!
	 */
	public function testLoadLangFail()
	{
		$lng = new Lang();

		$_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'it;q=0.8';

		// A non existing directory in order to force the exception
		$lng->loadLang(BASEDIR . '/../../NoLanguages');
	}

	/**
	 * @covers Lang::findLanguage
	 */
	public function testFindLanguage()
	{
		$method = new ReflectionMethod(
			'Lang', 'findLanguage'
		);

		$method->setAccessible(TRUE);

		$path = __DIR__ . '/../../Languages';

		$this->assertEquals(
			$path . '/import_en.xml', $method->invoke(new Lang, $path, array('en' => 1))
		);

		// It doesn't exists, so it should return en by default
		$this->assertEquals(
			$path . '/import_en.xml', $method->invoke(new Lang, $path, array('it' => 1))
		);

		// A non existing directory in order to get a false
		$path = __DIR__ . '/../../NoLanguages';

		$this->assertFalse(
			$method->invoke(new Lang, $path, array('en' => 1))
		);
	}
}

/**
 * Temporary class to forward the currently static exception handler to a
 * default exception
 */
class ImportException extends Exception
{
	public static function exception_handler($e)
	{
		throw new TestingImportException($e->getMessage(), $e->getCode(), $e);
	}
}

class TestingImportException extends Exception
{
}