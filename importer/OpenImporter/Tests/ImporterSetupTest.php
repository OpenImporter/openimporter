<?php
/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 2.0 Alpha
 */

namespace OpenImporter\Importers\sources\Tests;

use OpenImporter\Core\ImporterSetup;
use OpenImporter\Core\Configurator;
use OpenImporter\Core\Lang;

class ImporterSetupTest extends \PHPUnit_Framework_TestCase
{
	public function testSetNamespace()
	{
		$config = new Configurator();
		$lang = new Lang();
		$object = new \ReflectionClass('OpenImporter\\Core\\ImporterSetup');
		$instance = $object->newInstanceArgs(array($config, $lang, array()));
		$property = $object->getProperty('i_namespace');
		$property->setAccessible(true);

		//We need to create an empty object to pass to
		//ReflectionProperty's getValue method
		$instance->setNamespace('astring');

		$this->assertEquals('astring', $property->getValue($instance));
	}

	public function testGetXml()
	{
		$config = new Configurator();
		$lang = new Lang();
		$instance = new ImporterSetup($config, $lang, array());
		$instance->xml = 'xmlproperty';

		$this->assertEquals('xmlproperty', $instance->getXml());
	}

	public function testGetDb()
	{
		$config = new Configurator();
		$lang = new Lang();
		$object = new \ReflectionClass('OpenImporter\\Core\\ImporterSetup');
		$instance = $object->newInstanceArgs(array($config, $lang, array()));
		$property = $object->getProperty('db');
		$property->setAccessible(true);
		$property->setValue($instance, 'dbproperty');

		$this->assertEquals('dbproperty', $instance->getDb());
	}

	public function testGetSourceDb()
	{
		$config = new Configurator();
		$lang = new Lang();
		$object = new \ReflectionClass('OpenImporter\\Core\\ImporterSetup');
		$instance = $object->newInstanceArgs(array($config, $lang, array()));
		$property = $object->getProperty('source_db');
		$property->setAccessible(true);
		$property->setValue($instance, 'dbproperty');

		$this->assertEquals('dbproperty', $instance->getSourceDb());
	}

	public function testGetBaseClass()
	{
		$config = new Configurator();
		$lang = new Lang();
		$object = new \ReflectionClass('OpenImporter\\Core\\ImporterSetup');
		$instance = $object->newInstanceArgs(array($config, $lang, array()));
		$property = $object->getProperty('_importer_base_class_name');
		$property->setAccessible(true);
		$property->setValue($instance, 'base_class_name');

		$this->assertEquals('base_class_name', $instance->getBaseClass());
	}
}