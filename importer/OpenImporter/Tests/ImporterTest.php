<?php
/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 2.0 Alpha
 */

namespace OpenImporter\Importers\sources\Tests;

use OpenImporter\Core\Importer;
use OpenImporter\Core\Configurator;
use OpenImporter\Core\Lang;
use OpenImporter\Core\HttpResponse;
use OpenImporter\Core\ResponseHeader;

class ImporterTest extends \PHPUnit_Framework_TestCase
{
	public function testReloadImporter()
	{
		$config = new Configurator();
		$header = new ResponseHeader();
		$response = new HttpResponse($header);
		$instance = new DummyTestReloadImporter($config, $response);

		$this->assertFalse($instance->called);

		$config->script = 'something';
		$instance->reloadImporter();
		$this->assertEquals('something', $instance->called);
	}
}

class DummyTestReloadImporter extends Importer
{
	public $called = false;

	public function loadImporter($files)
	{
		$this->called = 'something';
	}
}