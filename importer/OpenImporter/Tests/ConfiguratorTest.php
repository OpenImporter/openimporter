<?php
/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 2.0 Alpha
 */

namespace OpenImporter\Importers\sources\Tests;

use OpenImporter\Core\Configurator;

/**
 * The configurator is just a class holding the common configuration
 * info such as the paths (to/from), prefixes, etc.
 * Basically a getter/setter
 *
 * @property string $lang_dir
 * @property string $importers_dir
 */
class ConfiguratorTest extends \PHPUnit_Framework_TestCase
{
	/**
	 * @covers OpenImporter\Core\Configurator::__set
	 */
	public function testSet()
	{
		$object = new \ReflectionClass('OpenImporter\\Core\\Configurator');
		$property = $object->getProperty('data');
		$property->setAccessible(true);

		//We need to create an empty object to pass to
		//ReflectionProperty's getValue method
		$config = new Configurator();
		$config->test = 123;

		$this->assertEquals(array('test' => 123), $property->getValue($config));
	}

	/**
	 * @covers OpenImporter\Core\Configurator::__isset
	 */
	public function testIsset()
	{
		$config = new Configurator();
		$config->test = 123;

		$this->assertTrue(isset($config->test));
		$this->assertFalse(isset($config->nontest));
	}

	/**
	 * @covers OpenImporter\Core\Configurator::__get
	 */
	public function testGet()
	{
		$config = new Configurator();
		$config->test = 123;

		$this->assertEquals(123, $config->test);
		$this->assertNull($config->nontest);
	}
}