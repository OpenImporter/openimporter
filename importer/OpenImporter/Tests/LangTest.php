<?php

use OpenImporter\Lang;

class LangTest extends \PHPUnit_Framework_TestCase
{
	public function run(PHPUnit_Framework_TestResult $result = NULL)
	{
		$this->setPreserveGlobalState(false);
		return parent::run($result);
	}

	public function testLoadLangSuccess()
	{
		$lng = new Lang();

		$_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'en-GB,en;q=0.9,it;q=0.8';
		try
		{
			$lng->loadLang(BASEDIR . '/Languages');
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
		$lng->loadLang(BASEDIR . '/NoLanguages');
	}

	/**
	 * @expectedException OpenImporter\ImportException
	 * @expectedExceptionMessage XML-Syntax error in file:
	 */
	public function testLoadLangBadXML()
	{
		$lng = new Lang();

		$_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'en';

		$lng->loadLang(BASEDIR . '/OpenImporter/Tests');
	}

	/**
	 * @covers OpenImporter\Lang::findLanguage
	 */
	public function testFindLanguage()
	{
		$method = new ReflectionMethod(
			Lang::class, 'findLanguage'
		);

		$method->setAccessible(true);

		$path = BASEDIR . '/Languages';

		$this->assertEquals(
			$path . '/import_en.xml', $method->invoke(new Lang, $path, array('en'))
		);

		// It doesn't exists, so it should return en by default
		$this->assertEquals(
			$path . '/import_en.xml', $method->invoke(new Lang, $path, array('it'))
		);

		// A non existing directory in order to get a false
		$path = BASEDIR . '/NoLanguages';

		$this->assertFalse(
			$method->invoke(new Lang, $path, array('en'))
		);
	}

	/**
	 * @covers OpenImporter\Lang::set
	 */
	public function testSet()
	{
		$method = new ReflectionMethod(
			Lang::class, 'set'
		);

		$method->setAccessible(true);

		$invoke_lang = new Lang();
		$this->assertTrue(
			$method->invoke($invoke_lang, 'testing', 'testing')
		);

		$strings = $invoke_lang->getAll();
		$this->assertTrue(isset($strings['testing']));
		$this->assertEquals('testing', $strings['testing']);
	}

	public function testGetAll()
	{
		$method = new ReflectionMethod(
			Lang::class, 'set'
		);

		$method->setAccessible(true);

		$invoke_lang = new Lang();
		$tests = array(
			'testing' => 'testing',
			'testing2' => 'testing2',
		);
		foreach ($tests as $key => $val)
		{
			$method->invoke($invoke_lang, $key, $val);
		}

		$strings = $invoke_lang->getAll();
		$this->assertEquals(2, count($strings));
		$equal = true;
		$keys = array_keys($tests);
		$vals = array_values($tests);
		foreach ($strings as $key => $val)
		{
			// Just one is enough to have everything wrong
			if (!in_array($key, $keys) || !in_array($val, $vals))
			{
				$equal = false;
				break;
			}
		}
		$this->assertTrue($equal);
	}

	/**
	 * @runInSeparateProcess
	 * @covers OpenImporter\Lang::set
	 * @expectedException Exception
	 * @expectedExceptionMessage Unable to set language string for <em>testing</em>. It was already set.
	 */
	public function testSetException()
	{
		require_once(TESTDIR . '/TestOiException.php');
		$method = new ReflectionMethod(
			Lang::class, 'set'
		);

		$method->setAccessible(true);

		$invoke_lang = new Lang();
		$method->invoke($invoke_lang, 'testing', 'testing');
		// setting the same twice should throw an Exception
		$method->invoke($invoke_lang, 'testing', 'testing');
	}

	/**
	 * @covers OpenImporter\Lang::get
	 */
	public function testGet()
	{
		$method = new ReflectionMethod(
			Lang::class, 'set'
		);

		$method->setAccessible(true);

		$invoke_lang = new Lang();
		$method->invoke($invoke_lang, 'testing', 'testing');

		// An existing string
		$this->assertEquals('testing', $invoke_lang->get('testing'));

		// A non existing one should return the key
		$this->assertEquals('random', $invoke_lang->get('random'));

		$method->invoke($invoke_lang, 'testing_array', 'testing %s');

		// An existing string
		$this->assertEquals('testing sprintf\'ed', $invoke_lang->get(array('testing_array', 'sprintf\'ed')));
	}

	/**
	 * @covers OpenImporter\Lang::__get
	 */
	public function testGetter()
	{
		$method = new ReflectionMethod(
			Lang::class, 'set'
		);

		$method->setAccessible(true);

		$invoke_lang = new Lang();
		$method->invoke($invoke_lang, 'testing', 'testing');

		// An existing string
		$this->assertEquals('testing', $invoke_lang->testing);

		// A non existing one, its the key
		$this->assertEquals('random', $invoke_lang->random);
	}
}