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
 * The configurator is just a ValuesBag, the only difference is the $store
 * public property. Let's check if it exists, it's public, it is empty on
 * initialization and accepts a value.
 */
class ConfiguratorTest extends \PHPUnit_Framework_TestCase
{
	public function testStore()
	{
		$object = new Configurator();

		$this->assertTrue(isset($object->store));

		$this->assertEquals(array(), $object->store);

		$object->store = array(1);
		$this->assertEquals(array(1), $object->store);
	}
}